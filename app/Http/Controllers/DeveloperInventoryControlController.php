<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ProductStockSyncService;
use App\Services\PurchaseReturnLedgerSynchronizerService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DeveloperInventoryControlController extends Controller
{
    public function stockMismatches(Request $request): JsonResponse
    {
        $this->ensureDeveloper($request);

        $search = trim((string) $request->query('search', ''));
        $perPage = max(10, min(100, (int) $request->query('per_page', 25)));
        $pageNumber = max(1, (int) $request->query('page', 1));

        $mismatches = $this->stockMismatchRows($search);
        $total = $mismatches->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $pageRows = $mismatches->slice(($pageNumber - 1) * $perPage, $perPage)->values();

        return response()->json([
            'success' => true,
            'data' => $pageRows->all(),
            'pagination' => [
                'current_page' => $pageNumber,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ]);
    }

    public function syncProductInventory(Request $request, int $productId): JsonResponse
    {
        $this->ensureDeveloper($request);

        $product = Product::on('masterlist')->findOrFail($productId);
        $before = (int) $product->on_hand;
        $ledgerInventory = (int) ProductStockSyncService::syncProduct($productId);

        Log::notice('Developer manually synchronized product inventory from product ledger.', [
            'product_id' => $productId,
            'product_code' => $product->product_code,
            'before' => $before,
            'after' => $ledgerInventory,
            'developer' => $this->developerIdentifier($request),
        ]);

        return response()->json([
            'success' => true,
            'message' => "{$product->product_code} synchronized successfully.",
            'product_id' => $productId,
            'product_inventory' => $ledgerInventory,
            'ledger_inventory' => $ledgerInventory,
        ]);
    }

    public function syncAllProductInventory(Request $request): JsonResponse
    {
        $this->ensureDeveloper($request);

        $before = $this->countStockMismatches();
        $processed = 0;
        $updated = 0;

        Product::on('masterlist')
            ->select('id')
            ->orderBy('id')
            ->chunkById(1000, function ($products) use (&$processed, &$updated) {
                $ids = $products->pluck('id')->map(fn ($id) => (int) $id)->all();
                $processed += count($ids);
                $updated += ProductStockSyncService::syncProducts($ids, 1000);
            });

        $after = $this->countStockMismatches();

        Log::notice('Developer synchronized all product inventory values from latest ledger balances.', [
            'processed_products' => $processed,
            'updated_products' => $updated,
            'mismatches_before' => $before,
            'mismatches_after' => $after,
            'developer' => $this->developerIdentifier($request),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Sync All completed. {$processed} products checked; {$after} mismatches remain.",
            'processed_products' => $processed,
            'updated_products' => $updated,
            'mismatches_before' => $before,
            'mismatches_after' => $after,
        ]);
    }

    public function autoSyncStatus(Request $request): JsonResponse
    {
        $this->ensureDeveloper($request);

        $latestLedgerRow = DB::connection('ledger')->table('product_ledgers')
            ->selectRaw('MAX(COALESCE(updated_at, created_at)) AS latest_activity')
            ->first();
        $latestLedgerAt = $latestLedgerRow?->latest_activity;

        $active = class_exists(\App\Observers\ProductLedgerObserver::class)
            && class_exists(ProductStockSyncService::class);

        return response()->json([
            'success' => true,
            'active' => $active,
            'mode' => 'event_driven_after_commit',
            'label' => $active ? 'Real-time auto sync is active' : 'Real-time auto sync is unavailable',
            'details' => 'Product inventory synchronizes immediately after Product Ledger changes commit. No five-minute polling is scheduled.',
            'latest_ledger_activity' => $latestLedgerAt,
            'mismatch_count' => $this->countStockMismatches(),
            'scheduled_polling' => false,
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $this->ensureDeveloper($request);

        $perPage = 25;
        $filters = [
            'product_code' => trim((string) $request->query('product_code', '')),
            'part_number' => trim((string) $request->query('part_number', '')),
            'description' => trim((string) $request->query('description', '')),
            'application' => trim((string) $request->query('application', '')),
        ];

        $query = Product::on('masterlist')
            ->select(['id', 'product_code', 'part_number', 'description', 'application', 'unit', 'cost'])
            ->orderBy('product_code')
            ->orderBy('id');

        foreach ($filters as $column => $value) {
            if ($value !== '') {
                $query->where($column, 'like', "%{$value}%");
            }
        }

        $page = $query->paginate($perPage);
        $productIds = collect($page->items())->pluck('id')->map(fn ($id) => (int) $id)->all();
        $balances = $this->latestLedgerBalances($productIds);

        return response()->json([
            'success' => true,
            'products' => collect($page->items())->map(fn ($product) => [
                'id' => (int) $product->id,
                'product_code' => (string) $product->product_code,
                'part_number' => (string) ($product->part_number ?? ''),
                'description' => (string) ($product->description ?? ''),
                'application' => (string) ($product->application ?? ''),
                'unit' => (string) ($product->unit ?? ''),
                'cost' => (float) ($product->cost ?? 0),
                'ledger_inventory' => (int) ($balances[(int) $product->id] ?? 0),
            ])->values(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            'filters' => $filters,
        ]);
    }

    public function purchaseReturnLedgerMismatches(
        Request $request,
        PurchaseReturnLedgerSynchronizerService $synchronizer
    ): JsonResponse {
        $this->ensureDeveloper($request);

        $validated = Validator::make($request->query(), [
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ])->validate();

        try {
            return response()->json($synchronizer->paginateMissing(
                (string) $validated['date_from'],
                (string) $validated['date_to'],
                (int) ($validated['page'] ?? 1),
                (int) ($validated['per_page'] ?? 50)
            ));
        } catch (\Throwable $e) {
            Log::error('Purchase Return ledger mismatch list failed.', [
                'date_from' => $validated['date_from'],
                'date_to' => $validated['date_to'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function syncPurchaseReturnLedgerItem(
        Request $request,
        int $purchaseReturnItemId,
        PurchaseReturnLedgerSynchronizerService $synchronizer
    ): JsonResponse {
        $this->ensureDeveloper($request);

        $validated = Validator::make($request->all(), [
            'mode' => ['required', 'string', 'in:OUT,JUNK,out,junk'],
        ])->validate();

        $result = $synchronizer->syncItem(
            $purchaseReturnItemId,
            strtoupper((string) $validated['mode'])
        );

        Log::notice('Developer synchronized a missing Purchase Return into Product Ledger.', [
            'purchase_return_item_id' => $purchaseReturnItemId,
            'return_number' => $result['return_number'] ?? null,
            'return_date' => $result['return_date'] ?? null,
            'mode' => $result['mode'] ?? null,
            'ledger_id' => $result['ledger_id'] ?? null,
            'synced' => $result['synced'] ?? false,
            'developer' => $this->developerIdentifier($request),
        ]);

        $displayDate = !empty($result['return_date'])
            ? Carbon::parse((string) $result['return_date'])->format('d-m-Y')
            : '';

        return response()->json(array_merge($result, [
            'message' => !empty($result['synced'])
                ? sprintf(
                    '%s synchronized as %s on %s.',
                    (string) ($result['return_number'] ?? 'Purchase Return'),
                    (string) ($result['mode'] ?? ''),
                    $displayDate
                )
                : sprintf(
                    '%s is already present in Product Ledger; no duplicate was inserted.',
                    (string) ($result['return_number'] ?? 'Purchase Return')
                ),
        ]));
    }

    public function syncAllPurchaseReturnLedgerItems(
        Request $request,
        PurchaseReturnLedgerSynchronizerService $synchronizer
    ): JsonResponse {
        $this->ensureDeveloper($request);

        $validated = Validator::make($request->all(), [
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'mode' => ['required', 'string', 'in:OUT,JUNK,out,junk'],
        ])->validate();

        $dateFrom = (string) $validated['date_from'];
        $dateTo = (string) $validated['date_to'];
        $mode = strtoupper((string) $validated['mode']);
        $result = $synchronizer->syncRange($dateFrom, $dateTo, $mode);

        Log::notice('Developer synchronized missing Purchase Returns into Product Ledger by date range.', [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'mode' => $mode,
            'synced' => $result['synced'] ?? 0,
            'already_synced' => $result['already_synced'] ?? 0,
            'affected_products' => $result['affected_products'] ?? 0,
            'developer' => $this->developerIdentifier($request),
        ]);

        $displayFrom = Carbon::parse($dateFrom)->format('d-m-Y');
        $displayTo = Carbon::parse($dateTo)->format('d-m-Y');

        return response()->json(array_merge($result, [
            'message' => sprintf(
                'Purchase Return sync complete: %d missing item(s) synchronized as %s from %s to %s.',
                (int) ($result['synced'] ?? 0),
                $mode,
                $displayFrom,
                $displayTo
            ),
        ]));
    }

    public function purchaseLedgerMismatches(Request $request): JsonResponse
    {
        $this->ensureDeveloper($request);

        $filters = [
            'supplier_name' => trim((string) $request->query('supplier_name', '')),
            'supplier_invoice' => trim((string) $request->query('supplier_invoice', '')),
            'product_code' => trim((string) $request->query('product_code', '')),
            'part_number' => trim((string) $request->query('part_number', '')),
            'unit' => trim((string) $request->query('unit', '')),
            'cost' => trim((string) $request->query('cost', '')),
            'date' => trim((string) $request->query('date', '')),
        ];

        $rows = $this->purchaseLedgerMismatchRows($filters);
        $pageNumber = max(1, (int) $request->query('page', 1));
        $perPage = 50;
        $total = $rows->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $pageRows = $rows->slice(($pageNumber - 1) * $perPage, $perPage)->values();

        return response()->json([
            'success' => true,
            'data' => $pageRows->map(fn ($row) => [
                'purchase_order_id' => (int) $row->purchase_order_id,
                'purchase_order_item_id' => (int) $row->purchase_order_item_id,
                'po_number' => (string) ($row->po_number ?? ''),
                'supplier_name' => trim((string) ($row->supplier_name ?? '')) ?: ('Supplier #' . (int) $row->supplier_id),
                'supplier_invoice' => (string) ($row->supplier_invoice_number ?? ''),
                'product_code' => (string) ($row->product_code ?? ''),
                'part_number' => (string) ($row->part_number ?? ''),
                'unit' => trim((string) ($row->po_unit ?? '')) ?: (string) ($row->product_unit ?? ''),
                'cost' => (float) ($row->unit_price ?? 0),
                'date' => (string) ($row->date ?? ''),
                'actual_quantity' => (float) ($row->actual_quantity ?? 0),
            ])->all(),
            'filters' => $filters,
            'pagination' => [
                'current_page' => $pageNumber,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ]);
    }

    public function syncPurchaseSupplierLedger(Request $request, int $purchaseOrderId): JsonResponse
    {
        $this->ensureDeveloper($request);

        $purchaseOrder = DB::connection('purchase')->table('purchase_orders')
            ->where('id', $purchaseOrderId)
            ->first();
        abort_if(!$purchaseOrder, 404, 'Purchase Order not found.');

        $items = DB::connection('purchase')->table('purchase_order_items')
            ->where('purchase_order_id', $purchaseOrderId)
            ->where('actual_quantity', '>', 0)
            ->orderBy('id')
            ->get();

        abort_if($items->isEmpty(), 422, 'This Purchase Order has no received items to synchronize.');

        $supplier = DB::connection('masterlist')->table('suppliers')
            ->where('id', $purchaseOrder->supplier_id)
            ->first(['id', 'name']);
        $productUnits = DB::connection('masterlist')->table('products')
            ->whereIn('id', $items->pluck('product_id')->filter()->unique()->values()->all())
            ->pluck('unit', 'id')
            ->all();

        $result = DB::connection('ledger')->transaction(function () use ($purchaseOrder, $items, $productUnits) {
            $now = now('Asia/Manila')->format('Y-m-d H:i:s');
            $sourceParentCreatedAt = $purchaseOrder->created_at
                ?: ((string) $purchaseOrder->date . ' 00:00:00');

            $supplierLedger = DB::connection('ledger')->table('supplier_ledgers')
                ->where('source_purchase_order_id', $purchaseOrder->id)
                ->lockForUpdate()
                ->first();

            if (!$supplierLedger) {
                $supplierLedger = DB::connection('ledger')->table('supplier_ledgers')
                    ->whereNull('source_purchase_order_id')
                    ->where('supplier_id', $purchaseOrder->supplier_id)
                    ->where('transaction_code', $purchaseOrder->po_number)
                    ->where('module_type', 'Purchase Order')
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();
            }

            $parentPayload = [
                'source_purchase_order_id' => (int) $purchaseOrder->id,
                'supplier_id' => (int) $purchaseOrder->supplier_id,
                'date' => $purchaseOrder->date ?: now('Asia/Manila')->toDateString(),
                'transaction_code' => (string) $purchaseOrder->po_number,
                'module_type' => 'Purchase Order',
                'title' => 'Purchase Order Processing - ' . ((string) ($purchaseOrder->status ?? 'Synced')),
                'credit_amount' => round((float) ($purchaseOrder->actual_total_amount ?? 0), 2),
                'debit_amount' => 0,
                'reference_no' => trim((string) ($purchaseOrder->reference_number ?? '')) ?: null,
                'updated_at' => $now,
            ];

            $parentCreated = false;
            if ($supplierLedger) {
                DB::connection('ledger')->table('supplier_ledgers')
                    ->where('id', $supplierLedger->id)
                    ->update($parentPayload);
                $supplierLedgerId = (int) $supplierLedger->id;
            } else {
                $supplierLedgerId = (int) DB::connection('ledger')->table('supplier_ledgers')->insertGetId($parentPayload + [
                    'created_at' => $sourceParentCreatedAt,
                ]);
                $parentCreated = true;
            }

            $expectedRows = $this->expectedSupplierLedgerRows($purchaseOrder, $items, $supplierLedgerId, $productUnits);
            $sourceIds = collect($expectedRows)->pluck('source_purchase_order_item_id')->map(fn ($id) => (int) $id)->all();

            $directRows = DB::connection('ledger')->table('supplier_ledger_items')
                ->whereIn('source_purchase_order_item_id', $sourceIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn ($row) => (int) $row->source_purchase_order_item_id);

            $legacyRows = DB::connection('ledger')->table('supplier_ledger_items')
                ->where('supplier_ledger_id', $supplierLedgerId)
                ->whereNull('source_purchase_order_item_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $usedLegacyIds = [];
            $inserted = 0;
            $adopted = 0;
            $updated = 0;

            foreach ($expectedRows as $expected) {
                $sourceItemId = (int) $expected['source_purchase_order_item_id'];
                $direct = $directRows->get($sourceItemId);
                $payload = $expected;
                $createdAt = $payload['created_at'];
                unset($payload['created_at']);
                $payload['updated_at'] = $now;

                if ($direct) {
                    DB::connection('ledger')->table('supplier_ledger_items')
                        ->where('id', $direct->id)
                        ->update($payload);
                    $updated++;
                    continue;
                }

                $normalizedCode = strtolower(trim((string) ($expected['product_code'] ?? '')));
                $legacy = $legacyRows->first(function ($row) use ($expected, $normalizedCode, $usedLegacyIds) {
                    if (in_array((int) $row->id, $usedLegacyIds, true)) {
                        return false;
                    }

                    $sameProductId = (int) ($row->product_id ?? 0) > 0
                        && (int) ($row->product_id ?? 0) === (int) ($expected['product_id'] ?? 0);
                    $sameCode = $normalizedCode !== ''
                        && strtolower(trim((string) ($row->product_code ?? ''))) === $normalizedCode;

                    return $sameProductId || $sameCode;
                });

                if ($legacy) {
                    $usedLegacyIds[] = (int) $legacy->id;
                    DB::connection('ledger')->table('supplier_ledger_items')
                        ->where('id', $legacy->id)
                        ->update($payload);
                    $adopted++;
                    continue;
                }

                DB::connection('ledger')->table('supplier_ledger_items')->insert($payload + [
                    'created_at' => $createdAt,
                ]);
                $inserted++;
            }

            return [
                'supplier_ledger_id' => $supplierLedgerId,
                'parent_created' => $parentCreated,
                'items_inserted' => $inserted,
                'items_adopted' => $adopted,
                'items_updated' => $updated,
            ];
        });

        Log::notice('Developer synchronized missing Purchase Order supplier-ledger rows.', [
            'purchase_order_id' => $purchaseOrderId,
            'po_number' => $purchaseOrder->po_number,
            'supplier_id' => $purchaseOrder->supplier_id,
            'supplier_name' => $supplier->name ?? null,
            'supplier_invoice_number' => $purchaseOrder->supplier_invoice_number,
            'sync_result' => $result,
            'developer' => $this->developerIdentifier($request),
        ]);

        $changed = (int) $result['items_inserted'] + (int) $result['items_adopted'];
        $message = 'Purchase Order ' . $purchaseOrder->po_number . ' supplier ledger synchronized.';
        if ($changed > 0) {
            $message .= ' ' . $changed . ' missing item' . ($changed === 1 ? '' : 's') . ' repaired.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'purchase_order_id' => $purchaseOrderId,
            'po_number' => (string) $purchaseOrder->po_number,
            'supplier_name' => (string) ($supplier->name ?? ''),
        ] + $result);
    }

    public function syncAllPurchaseSupplierLedgers(Request $request): JsonResponse
    {
        $this->ensureDeveloper($request);
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $purchaseOrderIds = $this->purchaseLedgerMismatchRows([])
            ->pluck('purchase_order_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($purchaseOrderIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Purchase Ledger is already synchronized. No missing Purchase Orders were found.',
                'purchase_orders_synced' => 0,
                'items_inserted' => 0,
                'items_adopted' => 0,
                'items_updated' => 0,
                'parents_created' => 0,
                'failed' => 0,
            ]);
        }

        $summary = [
            'purchase_orders_synced' => 0,
            'items_inserted' => 0,
            'items_adopted' => 0,
            'items_updated' => 0,
            'parents_created' => 0,
            'failed' => 0,
        ];
        $failures = [];

        foreach ($purchaseOrderIds as $purchaseOrderId) {
            try {
                $response = $this->syncPurchaseSupplierLedger($request, $purchaseOrderId);
                $payload = $response->getData(true);

                $summary['purchase_orders_synced']++;
                $summary['items_inserted'] += (int) ($payload['items_inserted'] ?? 0);
                $summary['items_adopted'] += (int) ($payload['items_adopted'] ?? 0);
                $summary['items_updated'] += (int) ($payload['items_updated'] ?? 0);
                $summary['parents_created'] += !empty($payload['parent_created']) ? 1 : 0;
            } catch (\Throwable $e) {
                $summary['failed']++;
                $failures[] = [
                    'purchase_order_id' => $purchaseOrderId,
                    'message' => $e->getMessage(),
                ];
                Log::error('Developer Purchase Ledger Sync All failed for a Purchase Order.', [
                    'purchase_order_id' => $purchaseOrderId,
                    'error' => $e->getMessage(),
                    'developer' => $this->developerIdentifier($request),
                ]);
            }
        }

        Log::notice('Developer synchronized all missing Purchase Order supplier-ledger rows.', [
            'summary' => $summary,
            'failures' => $failures,
            'developer' => $this->developerIdentifier($request),
        ]);

        return response()->json([
            'success' => $summary['failed'] === 0,
            'message' => $summary['failed'] === 0
                ? 'Purchase Ledger Sync All completed successfully.'
                : 'Purchase Ledger Sync All completed with some failures.',
            'failures' => $failures,
        ] + $summary);
    }

    public function ledgerEntries(Request $request): JsonResponse
    {
        $this->ensureDeveloper($request);

        $validator = Validator::make($request->query(), [
            'product_id' => ['required', 'integer', 'min:1'],
            'action' => ['required', 'in:sales,purchase'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $productId = (int) $request->query('product_id');
        $action = (string) $request->query('action');
        $transactionType = $action === 'sales' ? 'OUT' : 'IN';
        $perPage = max(5, min(100, (int) $request->query('per_page', 15)));

        $product = Product::on('masterlist')->select(['id', 'product_code', 'part_number', 'unit'])->findOrFail($productId);
        $page = DB::connection('ledger')->table('product_ledgers')
            ->where('product_id', $productId)
            ->where('transaction_type', $transactionType)
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $items = collect($page->items())->map(fn ($row) => $this->serializeLedgerRow($row, $action))->values();

        return response()->json([
            'success' => true,
            'product' => [
                'id' => (int) $product->id,
                'product_code' => (string) $product->product_code,
                'part_number' => (string) $product->part_number,
                'unit' => (string) ($product->unit ?? ''),
                'latest_balance' => $this->latestLedgerBalance($productId),
            ],
            'action' => $action,
            'data' => $items,
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function storeLedgerEntry(Request $request): JsonResponse
    {
        $this->ensureDeveloper($request);
        $data = $this->validateLedgerPayload($request);
        $product = Product::on('masterlist')->findOrFail((int) $data['product_id']);

        $ledgerId = DB::connection('ledger')->transaction(function () use ($data, $product) {
            $action = $data['action'];
            $quantity = (int) $data['quantity'];
            $price = (float) $data['price'];
            $createdAt = Carbon::parse($data['created_at'], 'Asia/Manila')->format('Y-m-d H:i:s');

            $ledgerId = DB::connection('ledger')->table('product_ledgers')->insertGetId([
                'product_id' => (int) $product->id,
                'supplier_id' => null,
                'customer_id' => null,
                'source_type' => 'developer_manual',
                'source_id' => null,
                'source_item_id' => null,
                'processed_actual_qty' => $quantity,
                'transaction_type' => $action === 'sales' ? 'OUT' : 'IN',
                'date' => $data['date'],
                'transaction_number' => $data['transaction_number'],
                'reference_number' => $data['reference_number'] ?: null,
                'entity_name' => $data['entity_name'],
                'quantity_in' => $action === 'purchase' ? $quantity : 0,
                'quantity_out' => $action === 'sales' ? $quantity : 0,
                'balance_stock' => (int) $data['balance_stock'],
                'oum' => $data['oum'] ?: ($product->unit ?: null),
                'price' => $action === 'sales' ? $price : 0,
                'cost' => $action === 'purchase' ? $price : (float) ($product->cost ?? 0),
                'remarks' => $this->manualRemarks($action, $data['channel']),
                'idempotency_key' => 'developer_manual:' . Str::uuid(),
                'created_at' => $createdAt,
                'updated_at' => now('Asia/Manila')->format('Y-m-d H:i:s'),
            ]);

            $this->recalculateBalancesUsingAnchor((int) $product->id, (int) $ledgerId, (int) $data['balance_stock']);

            return (int) $ledgerId;
        });

        $latestBalance = $this->syncMasterInventoryFromLedger((int) $product->id);
        $row = DB::connection('ledger')->table('product_ledgers')->find($ledgerId);

        Log::warning('Developer manually created a product ledger row.', [
            'ledger_id' => $ledgerId,
            'product_id' => $product->id,
            'action' => $data['action'],
            'developer' => $this->developerIdentifier($request),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product ledger entry added successfully.',
            'entry' => $this->serializeLedgerRow($row, $data['action']),
            'latest_balance' => $latestBalance,
        ]);
    }

    public function updateLedgerEntry(Request $request, int $ledgerId): JsonResponse
    {
        $this->ensureDeveloper($request);
        $data = $this->validateLedgerPayload($request);
        $product = Product::on('masterlist')->findOrFail((int) $data['product_id']);

        DB::connection('ledger')->transaction(function () use ($data, $product, $ledgerId) {
            $existing = DB::connection('ledger')->table('product_ledgers')->where('id', $ledgerId)->lockForUpdate()->first();
            abort_if(!$existing, 404, 'Product ledger entry not found.');
            abort_if((int) $existing->product_id !== (int) $product->id, 422, 'The selected product does not match this ledger entry.');

            $action = $data['action'];
            $quantity = (int) $data['quantity'];
            $price = (float) $data['price'];
            $createdAt = Carbon::parse($data['created_at'], 'Asia/Manila')->format('Y-m-d H:i:s');

            DB::connection('ledger')->table('product_ledgers')->where('id', $ledgerId)->update([
                'transaction_type' => $action === 'sales' ? 'OUT' : 'IN',
                'date' => $data['date'],
                'transaction_number' => $data['transaction_number'],
                'reference_number' => $data['reference_number'] ?: null,
                'entity_name' => $data['entity_name'],
                'quantity_in' => $action === 'purchase' ? $quantity : 0,
                'quantity_out' => $action === 'sales' ? $quantity : 0,
                'processed_actual_qty' => $quantity,
                'balance_stock' => (int) $data['balance_stock'],
                'oum' => $data['oum'] ?: ($product->unit ?: null),
                'price' => $action === 'sales' ? $price : 0,
                'cost' => $action === 'purchase' ? $price : (float) ($existing->cost ?? $product->cost ?? 0),
                'remarks' => $this->manualRemarks($action, $data['channel'], (string) ($existing->remarks ?? '')),
                'updated_at' => now('Asia/Manila')->format('Y-m-d H:i:s'),
                'created_at' => $createdAt,
            ]);

            $this->recalculateBalancesUsingAnchor((int) $product->id, $ledgerId, (int) $data['balance_stock']);
        });

        $latestBalance = $this->syncMasterInventoryFromLedger((int) $product->id);
        $row = DB::connection('ledger')->table('product_ledgers')->find($ledgerId);

        Log::warning('Developer manually updated a product ledger row.', [
            'ledger_id' => $ledgerId,
            'product_id' => $product->id,
            'developer' => $this->developerIdentifier($request),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product ledger entry updated successfully.',
            'entry' => $this->serializeLedgerRow($row, $data['action']),
            'latest_balance' => $latestBalance,
        ]);
    }

    public function destroyLedgerEntry(Request $request, int $ledgerId): JsonResponse
    {
        $this->ensureDeveloper($request);

        $deleted = DB::connection('ledger')->transaction(function () use ($ledgerId) {
            $rows = $this->orderedLedgerRowsForProductByLedgerId($ledgerId, true);
            $target = $rows->firstWhere('id', $ledgerId);
            abort_if(!$target, 404, 'Product ledger entry not found.');

            $first = $rows->first();
            $openingBalance = $first
                ? (int) $first->balance_stock - (int) $first->quantity_in + (int) $first->quantity_out
                : 0;

            DB::connection('ledger')->table('product_ledgers')->where('id', $ledgerId)->delete();
            $this->recalculateBalancesFromOpening((int) $target->product_id, $openingBalance);

            return $target;
        });

        $latestBalance = $this->syncMasterInventoryFromLedger((int) $deleted->product_id);

        Log::warning('Developer permanently deleted a product ledger row.', [
            'ledger_id' => $ledgerId,
            'product_id' => $deleted->product_id,
            'developer' => $this->developerIdentifier($request),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product ledger entry permanently deleted.',
            'latest_balance' => $latestBalance,
        ]);
    }

    private function validateLedgerPayload(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'product_id' => ['required', 'integer', 'min:1'],
            'action' => ['required', 'in:sales,purchase'],
            'date' => ['required', 'date'],
            'entity_name' => ['required', 'string', 'max:255'],
            'transaction_number' => ['required', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'balance_stock' => ['required', 'integer'],
            'oum' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'created_at' => ['required', 'date'],
            'channel' => ['required', 'in:online,local'],
        ]);

        if ($validator->fails()) {
            abort(response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422));
        }

        return $validator->validated();
    }

    private function recalculateBalancesUsingAnchor(int $productId, int $anchorId, int $desiredAnchorBalance): void
    {
        $rows = $this->orderedLedgerRows($productId, true);
        $cumulativeThroughAnchor = 0;
        $anchorFound = false;

        foreach ($rows as $row) {
            $cumulativeThroughAnchor += (int) $row->quantity_in - (int) $row->quantity_out;
            if ((int) $row->id === $anchorId) {
                $anchorFound = true;
                break;
            }
        }

        abort_unless($anchorFound, 404, 'Product ledger anchor entry not found.');
        $openingBalance = $desiredAnchorBalance - $cumulativeThroughAnchor;
        $this->recalculateRows($rows, $openingBalance);
    }

    private function recalculateBalancesFromOpening(int $productId, int $openingBalance): void
    {
        $this->recalculateRows($this->orderedLedgerRows($productId, true), $openingBalance);
    }

    private function recalculateRows($rows, int $openingBalance): void
    {
        $runningBalance = $openingBalance;
        $connection = DB::connection('ledger');
        $updatedAt = now('Asia/Manila')->format('Y-m-d H:i:s');

        foreach ($rows as $row) {
            $runningBalance += (int) $row->quantity_in - (int) $row->quantity_out;
            if ((int) $row->balance_stock !== $runningBalance) {
                $connection->table('product_ledgers')->where('id', $row->id)->update([
                    'balance_stock' => $runningBalance,
                    'updated_at' => $updatedAt,
                ]);
            }
        }
    }

    private function orderedLedgerRows(int $productId, bool $lock = false)
    {
        $query = DB::connection('ledger')->table('product_ledgers')
            ->where('product_id', $productId)
            ->orderBy('date')
            ->orderByRaw("COALESCE(created_at, '1970-01-01 00:00:00') ASC")
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get(['id', 'product_id', 'quantity_in', 'quantity_out', 'balance_stock']);
    }

    private function orderedLedgerRowsForProductByLedgerId(int $ledgerId, bool $lock = false)
    {
        $target = DB::connection('ledger')->table('product_ledgers')->where('id', $ledgerId)->first(['product_id']);
        abort_if(!$target, 404, 'Product ledger entry not found.');

        return $this->orderedLedgerRows((int) $target->product_id, $lock);
    }

    private function latestLedgerBalance(int $productId): int
    {
        $row = DB::connection('ledger')->table('product_ledgers')
            ->where('product_id', $productId)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first(['balance_stock']);

        return (int) ($row->balance_stock ?? 0);
    }

    private function latestLedgerBalances(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        return DB::connection('ledger')->table('product_ledgers')
            ->whereIn('product_id', $productIds)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get(['product_id', 'balance_stock'])
            ->groupBy('product_id')
            ->map(fn ($rows) => (int) ($rows->first()->balance_stock ?? 0))
            ->all();
    }

    private function syncMasterInventoryFromLedger(int $productId): int
    {
        return (int) ProductStockSyncService::syncProduct($productId);
    }

    private function serializeLedgerRow(object $row, string $action): array
    {
        $remarks = (string) ($row->remarks ?? '');
        $channel = str_contains(strtoupper($remarks), 'CHANNEL=ONLINE') ? 'online' : 'local';
        $createdAt = $row->created_at ? Carbon::parse($row->created_at, 'Asia/Manila') : null;

        return [
            'id' => (int) $row->id,
            'product_id' => (int) $row->product_id,
            'action' => $action,
            'transaction_type' => (string) $row->transaction_type,
            'date' => (string) $row->date,
            'entity_name' => (string) ($row->entity_name ?? ''),
            'transaction_number' => (string) ($row->transaction_number ?? ''),
            'reference_number' => (string) ($row->reference_number ?? ''),
            'quantity' => $action === 'sales' ? (int) $row->quantity_out : (int) $row->quantity_in,
            'quantity_in' => (int) $row->quantity_in,
            'quantity_out' => (int) $row->quantity_out,
            'balance_stock' => (int) $row->balance_stock,
            'oum' => (string) ($row->oum ?? ''),
            'price' => (float) ($action === 'sales' ? $row->price : $row->cost),
            'channel' => $channel,
            'source_type' => (string) ($row->source_type ?? ''),
            'created_at' => $createdAt?->format('Y-m-d H:i:s'),
            'created_at_input' => $createdAt?->format('Y-m-d\TH:i'),
        ];
    }

    private function manualRemarks(string $action, string $channel, string $existingRemarks = ''): string
    {
        $marker = 'Developer Control Panel manual ' . strtoupper($action) . ' ledger entry | channel=' . strtoupper($channel);
        $existing = trim(preg_replace('/Developer Control Panel manual (SALES|PURCHASE) ledger entry \| channel=(ONLINE|LOCAL)/i', '', $existingRemarks) ?? '');

        return $existing === '' ? $marker : $marker . ' | ' . ltrim($existing, " |\t\n\r\0\x0B");
    }

    private function expectedSupplierLedgerRows(object $purchaseOrder, $items, int $supplierLedgerId, array $productUnits = []): array
    {
        $parentTotal = round((float) ($purchaseOrder->actual_total_amount ?? 0), 2);
        $itemValues = collect($items)->values();
        $baseTotal = round((float) $itemValues->sum(fn ($item) => (float) ($item->actual_subtotal ?? 0)), 2);
        $remainingParent = $parentTotal;
        $remainingBase = $baseTotal;
        $lastAllocatableIndex = $itemValues
            ->filter(fn ($item) => round((float) ($item->actual_subtotal ?? 0), 2) > 0)
            ->keys()
            ->last();

        $rows = [];
        foreach ($itemValues as $index => $item) {
            $actualQuantity = (float) ($item->actual_quantity ?? 0);
            $unitPrice = (float) ($item->unit_price ?? 0);
            $gross = round($actualQuantity * $unitPrice, 2);
            $lineBase = round((float) ($item->actual_subtotal ?? 0), 2);

            if ($parentTotal <= 0 || $baseTotal <= 0 || $lineBase <= 0) {
                $allocatedSubtotal = 0.0;
            } elseif ($index === $lastAllocatableIndex || $remainingBase <= 0) {
                $allocatedSubtotal = round($remainingParent, 2);
            } else {
                $allocatedSubtotal = round($parentTotal * ($lineBase / $baseTotal), 2);
                $remainingParent = round($remainingParent - $allocatedSubtotal, 2);
                $remainingBase = round($remainingBase - $lineBase, 2);
            }

            $discountAmount = round(max(0, $gross - $allocatedSubtotal), 2);
            $discountPercent = $gross > 0
                ? round(($discountAmount / $gross) * 100, 2)
                : 0.0;
            $sourceCreatedAt = $item->created_at
                ?: ($purchaseOrder->created_at ?: ((string) $purchaseOrder->date . ' 00:00:00'));

            $rows[] = [
                'source_purchase_order_item_id' => (int) $item->id,
                'supplier_ledger_id' => $supplierLedgerId,
                'product_id' => (int) ($item->product_id ?? 0) ?: null,
                'product_code' => trim((string) ($item->product_code ?? '')) ?: null,
                'unit' => trim((string) ($item->unit ?? '')) ?: (trim((string) ($productUnits[(int) ($item->product_id ?? 0)] ?? '')) ?: null),
                'description' => $item->description ?? null,
                'quantity' => (float) ($item->quantity ?? 0),
                'actual_quantity' => $actualQuantity,
                'unit_price' => $unitPrice,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
                'subtotal' => $allocatedSubtotal,
                'created_at' => $sourceCreatedAt,
            ];
        }

        return $rows;
    }

    private function stockMismatchRows(string $search = '')
    {
        $query = Product::on('masterlist')
            ->select(['products.id', 'products.product_code', 'products.part_number', 'products.on_hand']);

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('products.product_code', 'like', "%{$search}%")
                    ->orWhere('products.part_number', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('products.product_code')->get();
        $balances = ProductStockSyncService::latestBalances($products->pluck('id'));

        return $products->map(function ($product) use ($balances) {
            $ledgerInventory = (int) ($balances[(int) $product->id] ?? 0);
            return [
                'id' => (int) $product->id,
                'item_code' => (string) $product->product_code,
                'part_number' => (string) $product->part_number,
                'product_inventory' => (int) $product->on_hand,
                'ledger_inventory' => $ledgerInventory,
                'difference' => $ledgerInventory - (int) $product->on_hand,
            ];
        })->filter(fn ($row) => $row['product_inventory'] !== $row['ledger_inventory'])->values();
    }

    private function purchaseLedgerMismatchRows(array $filters)
    {
        $query = DB::connection('purchase')
            ->table('purchase_order_items as poi')
            ->join('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
            ->select([
                'po.id as purchase_order_id',
                'po.po_number',
                'po.supplier_invoice_number',
                'po.supplier_id',
                'po.date',
                'poi.id as purchase_order_item_id',
                'poi.product_id',
                'poi.product_code',
                'poi.unit as po_unit',
                'poi.unit_price',
                'poi.actual_quantity',
            ])
            ->where('poi.actual_quantity', '>', 0);

        if (($filters['supplier_invoice'] ?? '') !== '') {
            $query->where('po.supplier_invoice_number', 'like', '%' . $filters['supplier_invoice'] . '%');
        }
        if (($filters['product_code'] ?? '') !== '') {
            $query->where('poi.product_code', 'like', '%' . $filters['product_code'] . '%');
        }
        if (($filters['cost'] ?? '') !== '') {
            $query->whereRaw('CAST(poi.unit_price AS CHAR) LIKE ?', ['%' . $filters['cost'] . '%']);
        }
        if (($filters['date'] ?? '') !== '') {
            $query->whereRaw("DATE_FORMAT(po.date, '%Y-%m-%d') LIKE ?", ['%' . $filters['date'] . '%']);
        }

        $rows = $query
            ->orderByDesc('po.date')
            ->orderByDesc('po.id')
            ->orderBy('poi.id')
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $suppliers = DB::connection('masterlist')->table('suppliers')
            ->whereIn('id', $rows->pluck('supplier_id')->map(fn ($id) => (int) $id)->filter()->unique()->values()->all())
            ->pluck('name', 'id');
        $products = DB::connection('masterlist')->table('products')
            ->whereIn('id', $rows->pluck('product_id')->map(fn ($id) => (int) $id)->filter()->unique()->values()->all())
            ->get(['id', 'part_number', 'unit'])
            ->keyBy('id');

        $rows = $rows->map(function ($row) use ($suppliers, $products) {
            $product = $products->get((int) $row->product_id);
            $row->supplier_name = (string) ($suppliers->get((int) $row->supplier_id) ?? '');
            $row->part_number = (string) ($product->part_number ?? '');
            $row->product_unit = (string) ($product->unit ?? '');
            return $row;
        });

        if (($filters['supplier_name'] ?? '') !== '') {
            $needle = mb_strtolower($filters['supplier_name']);
            $rows = $rows->filter(fn ($row) => str_contains(mb_strtolower((string) $row->supplier_name), $needle));
        }
        if (($filters['part_number'] ?? '') !== '') {
            $needle = mb_strtolower($filters['part_number']);
            $rows = $rows->filter(fn ($row) => str_contains(mb_strtolower((string) $row->part_number), $needle));
        }
        if (($filters['unit'] ?? '') !== '') {
            $needle = mb_strtolower($filters['unit']);
            $rows = $rows->filter(function ($row) use ($needle) {
                $unit = trim((string) ($row->po_unit ?? '')) ?: (string) ($row->product_unit ?? '');
                return str_contains(mb_strtolower($unit), $needle);
            });
        }

        $rows = $rows->values();
        if ($rows->isEmpty()) {
            return $rows;
        }

        $itemIds = $rows->pluck('purchase_order_item_id')->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        $poIds = $rows->pluck('purchase_order_id')->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        $poNumbers = $rows->pluck('po_number')->map(fn ($v) => trim((string) $v))->filter()->unique()->values()->all();
        $supplierIds = $rows->pluck('supplier_id')->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();

        $ledgerRows = DB::connection('ledger')->table('supplier_ledger_items as sli')
            ->join('supplier_ledgers as sl', 'sl.id', '=', 'sli.supplier_ledger_id')
            ->where(function ($query) use ($itemIds, $poIds, $poNumbers, $supplierIds) {
                $query->whereIn('sli.source_purchase_order_item_id', $itemIds)
                    ->orWhere(function ($legacy) use ($poIds, $poNumbers, $supplierIds) {
                        $legacy->whereNull('sli.source_purchase_order_item_id')
                            ->where(function ($parent) use ($poIds, $poNumbers, $supplierIds) {
                                $parent->whereIn('sl.source_purchase_order_id', $poIds)
                                    ->orWhere(function ($oldParent) use ($poNumbers, $supplierIds) {
                                        $oldParent->whereNull('sl.source_purchase_order_id')
                                            ->whereIn('sl.transaction_code', $poNumbers)
                                            ->whereIn('sl.supplier_id', $supplierIds)
                                            ->where('sl.module_type', 'Purchase Order');
                                    });
                            });
                    });
            })
            ->get([
                'sli.source_purchase_order_item_id', 'sli.product_id', 'sli.product_code',
                'sl.source_purchase_order_id', 'sl.supplier_id', 'sl.transaction_code', 'sl.module_type',
            ]);

        $directIds = $ledgerRows->pluck('source_purchase_order_item_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->flip();
        $legacyRows = $ledgerRows->filter(fn ($row) => empty($row->source_purchase_order_item_id))->values();

        return $rows->filter(function ($row) use ($directIds, $legacyRows) {
            if ($directIds->has((int) $row->purchase_order_item_id)) {
                return false;
            }

            $code = mb_strtolower(trim((string) ($row->product_code ?? '')));
            $present = $legacyRows->contains(function ($ledger) use ($row, $code) {
                $parentMatches = (int) ($ledger->source_purchase_order_id ?? 0) === (int) $row->purchase_order_id
                    || (empty($ledger->source_purchase_order_id)
                        && (int) ($ledger->supplier_id ?? 0) === (int) $row->supplier_id
                        && (string) ($ledger->module_type ?? '') === 'Purchase Order'
                        && mb_strtolower(trim((string) ($ledger->transaction_code ?? ''))) === mb_strtolower(trim((string) ($row->po_number ?? ''))));

                if (!$parentMatches) {
                    return false;
                }

                $sameProductId = (int) ($ledger->product_id ?? 0) > 0
                    && (int) ($ledger->product_id ?? 0) === (int) $row->product_id;
                $sameCode = $code !== ''
                    && mb_strtolower(trim((string) ($ledger->product_code ?? ''))) === $code;

                return $sameProductId || $sameCode;
            });

            return !$present;
        })->values();
    }

    private function countStockMismatches(): int
    {
        return $this->stockMismatchRows()->count();
    }

    private function ensureDeveloper(Request $request): void
    {
        $user = $request->session()->get('user');
        $accountType = is_array($user) ? ($user['account_type'] ?? null) : ($user->account_type ?? null);
        abort_if(!$user || (int) $accountType !== 4, 403, 'Forbidden');
    }

    private function developerIdentifier(Request $request): string
    {
        $user = $request->session()->get('user');
        if (is_array($user)) {
            return (string) ($user['User_ID'] ?? $user['login_ID'] ?? $user['username'] ?? 'developer');
        }

        return (string) ($user->User_ID ?? $user->login_ID ?? $user->username ?? 'developer');
    }
}
