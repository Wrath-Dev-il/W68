<?php

namespace App\Services;

use App\Models\SalesOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalesOrderLedgerReconciliationService
{
    /**
     * Make the saved Sales Order item snapshot authoritative for its
     * product_ledgers OUT movements. Removed items are physically deleted;
     * added items create source-linked OUT rows; QTY/unit/price changes update
     * the same source-linked row and all affected running balances are rebuilt.
     */
    public function reconcile(SalesOrder $salesOrder): array
    {
        $so = $salesOrder->fresh(['items']);
        if (!$so) {
            throw new \RuntimeException('Sales Order could not be reloaded for ledger reconciliation.');
        }

        $items = $so->items()->orderBy('id')->get();
        $existingRows = $this->candidateRows($so);
        $unmatchedIds = $existingRows->pluck('id')->map(fn ($id) => (int) $id)->flip();

        $affectedProductIds = $existingRows->pluck('product_id')
            ->merge($items->pluck('product_id'))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $created = 0;
        $updated = 0;

        foreach ($items as $item) {
            $movementOut = (float) ($item->actual_qty ?? 0) + (float) ($item->additional_qty ?? 0);
            $row = $this->matchRow($existingRows, $unmatchedIds, (int) $item->id, (int) $item->product_id);

            // A zero-stock-movement line may remain on the invoice, but it must
            // not retain a stale OUT ledger movement from an earlier edit.
            if ($movementOut <= 0) {
                if ($row) {
                    $unmatchedIds->put((int) $row->id, true);
                }
                continue;
            }

            $payload = [
                'product_id' => (int) $item->product_id,
                'customer_id' => $so->customer_id ? (int) $so->customer_id : null,
                'supplier_id' => null,
                'source_type' => SalesOrderLedgerService::SOURCE_TYPE,
                'source_id' => (int) $so->id,
                'source_item_id' => (int) $item->id,
                'processed_actual_qty' => $movementOut,
                'idempotency_key' => 'sales_order:' . $so->id . ':item:' . $item->id,
                'transaction_type' => 'OUT',
                'transaction_number' => (string) ($so->order_number ?? ''),
                'reference_number' => (string) ($so->invoice_numbers ?? ''),
                'entity_name' => (string) ($so->customer_name ?? ''),
                'quantity_in' => 0,
                'quantity_out' => $movementOut,
                'oum' => trim((string) ($item->oum ?? '')) ?: 'PCS',
                'price' => (float) ($item->unit_price ?? 0),
                'cost' => 0,
                'remarks' => 'Sales Order Processing - reconciled from saved invoice item',
                'updated_at' => now(),
            ];

            if ($row) {
                // Preserve the original movement date/time for an existing
                // invoice line; only the business values are corrected.
                DB::connection('ledger')->table('product_ledgers')
                    ->where('id', $row->id)
                    ->update($payload);
                $unmatchedIds->forget((int) $row->id);
                $updated++;
            } else {
                $payload['date'] = now()->toDateString();
                $payload['balance_stock'] = 0;
                $payload['created_at'] = now();
                DB::connection('ledger')->table('product_ledgers')->insert($payload);
                $created++;
            }
        }

        $deletedIds = $unmatchedIds->keys()
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $deleted = 0;
        if (!empty($deletedIds)) {
            $deleted = DB::connection('ledger')->table('product_ledgers')
                ->whereIn('id', $deletedIds)
                ->delete();
        }

        foreach ($affectedProductIds as $productId) {
            $this->recalculateProductBalance((int) $productId);
            ProductStockSyncService::syncProduct((int) $productId);
        }

        return [
            'product_ledgers_created' => $created,
            'product_ledgers_updated' => $updated,
            'product_ledgers_deleted' => (int) $deleted,
            'affected_product_ids' => $affectedProductIds->all(),
        ];
    }

    private function candidateRows(SalesOrder $so): Collection
    {
        $invoice = trim((string) ($so->invoice_numbers ?? ''));

        return DB::connection('ledger')->table('product_ledgers')
            ->where(function ($query) use ($so, $invoice) {
                $query->where(function ($sourceQuery) use ($so) {
                    $sourceQuery->whereIn('source_type', ['sales_order', 'sales-order'])
                        ->where('source_id', $so->id);
                });

                // Legacy fallback: only the same sales number + exact invoice,
                // so another invoice from the same Sales Note is never touched.
                if ($invoice !== '') {
                    $query->orWhere(function ($legacyQuery) use ($so, $invoice) {
                        $legacyQuery->whereNull('source_id')
                            ->where('transaction_type', 'OUT')
                            ->where('transaction_number', (string) $so->order_number)
                            ->where('reference_number', $invoice);
                    });
                }
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    private function matchRow(Collection $rows, Collection $unmatchedIds, int $itemId, int $productId): ?object
    {
        $available = $rows->filter(fn ($row) => $unmatchedIds->has((int) $row->id));

        $row = $available->first(fn ($candidate) => (int) ($candidate->source_item_id ?? 0) === $itemId);
        if ($row) {
            return $row;
        }

        // Product fallback is only safe for legacy rows that were never linked
        // to a concrete Sales Order item. A row already linked to a different
        // source_item_id represents an old/deleted invoice line and must be
        // hard-deleted instead of recycled for a newly added line.
        return $available->first(function ($candidate) use ($productId) {
            return (int) ($candidate->source_item_id ?? 0) === 0
                && (int) ($candidate->product_id ?? 0) === $productId;
        });
    }

    private function recalculateProductBalance(int $productId): void
    {
        if ($productId <= 0) {
            return;
        }

        $rows = DB::connection('ledger')->table('product_ledgers')
            ->where('product_id', $productId)
            ->orderBy('date')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'quantity_in', 'quantity_out', 'balance_stock']);

        $running = 0.0;
        foreach ($rows as $row) {
            $running += (float) ($row->quantity_in ?? 0) - (float) ($row->quantity_out ?? 0);
            if (abs((float) ($row->balance_stock ?? 0) - $running) > 0.0001) {
                DB::connection('ledger')->table('product_ledgers')
                    ->where('id', $row->id)
                    ->update([
                        'balance_stock' => $running,
                        'updated_at' => now(),
                    ]);
            }
        }
    }
}
