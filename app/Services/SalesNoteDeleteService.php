<?php

namespace App\Services;

use App\Models\SalesNote;
use App\Models\SalesOrder;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class SalesNoteDeleteService
{
    public function delete($id, $user)
    {
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (is_array($user)) {
            $user = (object) $user;
        }

        try {
            $note = SalesNote::on('sales')->with('items')->lockForUpdate()->find($id);
            if (!$note) {
                return response()->json(['success' => false, 'message' => 'Sales note not found.'], 404);
            }

            // Check if this sales note has processed orders (actual_qty > 0)
            $hasProcessedOrders = SalesOrder::on('sales')
                ->where('sales_note_id', $note->id)
                ->whereHas('items', function ($q) {
                    $q->where('actual_qty', '>', 0);
                })
                ->exists();

            if ($hasProcessedOrders) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete this Sales Note because it has already been processed (orders with actual quantities exist).',
                ], 422);
            }

            if (!Schema::connection('ledger')->hasTable('archived_records')) {
                throw new \Exception('Archive table is missing. Please run the archive migration first.');
            }

            DB::connection('sales')->beginTransaction();
            DB::connection('ledger')->beginTransaction();
            DB::connection('masterlist')->beginTransaction();

            $salesNumber = $note->sales_number;
            $items = $note->items->map(fn ($item) => $item->toArray())->values()->all();

            $deletedBy = $user->name ?? $user->username ?? $user->User_ID ?? 'Admin';
            $displayId = (string) ($salesNumber ?? $note->id);
            $dataName = trim((string) ($note->customer_name ?? ''));
            $actor = hatdogAuditActor($user);
            $customer = null;
            if (!empty($note->customer_id)) {
                $customer = Customer::on('masterlist')->find($note->customer_id);
            }
            if ($dataName === '') {
                $dataName = 'Sales Note ' . $displayId;
            }

            $existingArchive = DB::connection('ledger')
                ->table('archived_records')
                ->where('module', 'sales_note')
                ->whereNull('restored_at')
                ->where('source_connection', 'sales')
                ->where('source_table', 'sales_notes')
                ->where('source_id', $note->id)
                ->first();

            if ($existingArchive) {
                throw new \Exception('This sales note is already archived.');
            }

            $salesOrders = DB::connection('sales')->table('sales_orders')
                ->where(function ($q) use ($note, $salesNumber) {
                    $q->where('sales_note_id', $note->id)
                      ->orWhere('order_number', $salesNumber);
                })
                ->get();

            $orderIds = $salesOrders->pluck('id')->values()->all();
            $invoiceNumbers = $salesOrders->pluck('invoice_numbers')->filter()->values()->all();

            $salesReturns = collect();
            if (!empty($invoiceNumbers)) {
                $salesReturns = DB::connection('sales')->table('sales_returns')
                    ->where(function ($q) use ($invoiceNumbers, $salesNumber) {
                        foreach ($invoiceNumbers as $invNo) {
                            $q->orWhere('invoice_no', $invNo);
                        }
                        $q->orWhere('invoice_no', $salesNumber);
                    })
                    ->get();
            } else {
                $salesReturns = DB::connection('sales')->table('sales_returns')
                    ->where('invoice_no', $salesNumber)
                    ->get();
            }

            $returnIds = $salesReturns->pluck('id')->values()->all();
            $returnNumbers = $salesReturns->pluck('return_number')->filter()->values()->all();

            $salesOrderItems = collect();
            if (!empty($orderIds)) {
                $salesOrderItems = DB::connection('sales')->table('sales_order_items')
                    ->whereIn('sales_order_id', $orderIds)
                    ->get();
            }
            $salesReturnItems = collect();
            if (!empty($returnIds)) {
                $salesReturnItems = DB::connection('sales')->table('sales_return_items')
                    ->whereIn('sales_return_id', $returnIds)
                    ->get();
            }

            $archivedData = [
                'sales_note' => $note->toArray(),
                'items' => $items,
                'sales_orders' => $salesOrders->toArray(),
                'sales_order_items' => $salesOrderItems->toArray(),
                'sales_returns' => $salesReturns->toArray(),
                'sales_return_items' => $salesReturnItems->toArray(),
            ];

            $allTransNumbers = collect([$salesNumber])
                ->merge($returnNumbers)
                ->unique()
                ->filter()
                ->values()
                ->all();

            if (!empty($allTransNumbers)) {
                $outLedgers = DB::connection('ledger')->table('product_ledgers')
                    ->whereIn('transaction_number', $allTransNumbers)
                    ->where('transaction_type', 'OUT')
                    ->get();

                foreach ($outLedgers as $entry) {
                    $product = Product::on('masterlist')
                        ->where('id', $entry->product_id)->lockForUpdate()->first();
                    if ($product) {
                        $product->on_hand = max(0, (int)$product->on_hand + (int)$entry->quantity_out);
                        $product->save();
                    }
                }

                $inLedgers = DB::connection('ledger')->table('product_ledgers')
                    ->whereIn('transaction_number', $allTransNumbers)
                    ->where('transaction_type', 'IN')
                    ->get();

                foreach ($inLedgers as $entry) {
                    $product = Product::on('masterlist')
                        ->where('id', $entry->product_id)->lockForUpdate()->first();
                    if ($product) {
                        $product->on_hand = max(0, (int)$product->on_hand - (int)$entry->quantity_in);
                        $product->save();
                    }
                }

                DB::connection('ledger')->table('product_ledgers')
                    ->whereIn('transaction_number', $allTransNumbers)
                    ->delete();

                $affectedSalesArchiveProductIds = [];
                foreach ($outLedgers as $entry) {
                    $pid = (int) ($entry->product_id ?? 0);
                    if ($pid > 0) $affectedSalesArchiveProductIds[$pid] = true;
                }
                foreach ($inLedgers as $entry) {
                    $pid = (int) ($entry->product_id ?? 0);
                    if ($pid > 0) $affectedSalesArchiveProductIds[$pid] = true;
                }
                if (!empty($affectedSalesArchiveProductIds)) {
                    try {
                        ProductStockSyncService::syncProducts(array_keys($affectedSalesArchiveProductIds));
                    } catch (\Throwable $e) {
                        Log::warning('SalesNoteDeleteService: syncProducts failed (non-fatal), stock already adjusted manually. ' . $e->getMessage());
                    }
                }
            }

            if (!empty($returnIds)) {
                DB::connection('sales')->table('sales_return_items')
                    ->whereIn('sales_return_id', $returnIds)
                    ->delete();

                DB::connection('sales')->table('sales_returns')
                    ->whereIn('id', $returnIds)
                    ->delete();
            }

            if (!empty($orderIds)) {
                DB::connection('sales')->table('sales_order_items')
                    ->whereIn('sales_order_id', $orderIds)
                    ->delete();

                DB::connection('sales')->table('sales_orders')
                    ->whereIn('id', $orderIds)
                    ->delete();
            }

            DB::connection('ledger')->table('archived_records')->insert([
                'module' => 'sales_note',
                'source_connection' => 'sales',
                'source_table' => 'sales_notes',
                'source_id' => $note->id,
                'display_id' => $displayId,
                'data_name' => $dataName,
                'archived_data' => json_encode($archivedData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'deleted_by' => (string) $deletedBy,
                'deleted_at' => now(),
                'expires_at' => now()->addDays(30),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            hatdogWriteAuditTrail([
                'module' => 'sales_note',
                'action' => 'Deleted',
                'source_connection' => 'sales',
                'source_table' => 'sales_notes',
                'source_id' => $note->id,
                'display_id' => $displayId,
                'record_name' => $dataName,
                'user_name' => $actor['name'],
                'user_identifier' => $actor['identifier'],
                'audit_data' => [
                    'before' => [
                        'sales_note' => $note->toArray(),
                        'customer' => $customer?->toArray(),
                        'items' => $items,
                        'cascaded_orders' => $salesOrders->map(fn ($o) => (array) $o)->values()->all(),
                        'cascaded_returns' => $salesReturns->map(fn ($r) => (array) $r)->values()->all(),
                    ],
                ],
            ]);

            $note->items()->delete();
            $note->delete();

            DB::connection('masterlist')->commit();
            DB::connection('ledger')->commit();
            DB::connection('sales')->commit();

            $deletedCounts = [
                'sales_notes' => 1,
                'sales_note_items' => count($items),
                'sales_orders' => $salesOrders->count(),
                'sales_order_items' => $salesOrderItems->count(),
                'sales_returns' => $salesReturns->count(),
                'sales_return_items' => $salesReturnItems->count(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Sales Note and all related Sales Orders, Order Items, Sales Returns, and Return Items archived and deleted successfully.',
                'deleted' => $deletedCounts,
            ]);
        } catch (\Throwable $e) {
            if (DB::connection('masterlist')->transactionLevel() > 0) DB::connection('masterlist')->rollBack();
            if (DB::connection('ledger')->transactionLevel() > 0) DB::connection('ledger')->rollBack();
            if (DB::connection('sales')->transactionLevel() > 0) DB::connection('sales')->rollBack();
            Log::error('SalesNoteDeleteService error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
