<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class OnlineReportProductLedgerSyncService
{
    public static function verifyByReportId(int $reportId): array
    {
        $report = self::report($reportId);
        $expected = self::expectedStructure($report);

        $salesOrders = DB::connection('sales')
            ->table('sales_orders')
            ->where('order_number', 'like', 'ONL-' . $reportId . '-%')
            ->orderBy('id')
            ->get();

        $ordersByNumber = $salesOrders->groupBy('order_number');
        $orderProblems = [];
        $resolvedOrders = [];

        foreach ($expected['orders'] as $expectedOrder) {
            $matches = $ordersByNumber->get($expectedOrder['order_number'], collect());
            if ($matches->count() !== 1) {
                $orderProblems[] = [
                    'order_number' => $expectedOrder['order_number'],
                    'expected' => 1,
                    'actual' => $matches->count(),
                ];
                continue;
            }
            $resolvedOrders[$expectedOrder['index']] = $matches->first();
        }

        $expectedItemCount = 0;
        $actualItemCount = 0;
        $itemProblems = [];
        $resolvedItems = [];

        foreach ($expected['orders'] as $expectedOrder) {
            $expectedItemCount += count($expectedOrder['items']);
            $order = $resolvedOrders[$expectedOrder['index']] ?? null;
            if (!$order) {
                foreach ($expectedOrder['items'] as $item) {
                    $itemProblems[] = [
                        'order_number' => $expectedOrder['order_number'],
                        'product_id' => $item['product_id'],
                        'reason' => 'sales_order_missing',
                    ];
                }
                continue;
            }

            $actualItems = DB::connection('sales')
                ->table('sales_order_items')
                ->where('sales_order_id', $order->id)
                ->orderBy('id')
                ->get();
            $actualItemCount += $actualItems->count();
            $byProduct = $actualItems->groupBy(fn ($row) => (int) $row->product_id);

            foreach ($expectedOrder['items'] as $expectedItem) {
                $matches = $byProduct->get($expectedItem['product_id'], collect());
                if ($matches->count() !== 1) {
                    $itemProblems[] = [
                        'order_number' => $expectedOrder['order_number'],
                        'product_id' => $expectedItem['product_id'],
                        'expected' => 1,
                        'actual' => $matches->count(),
                        'reason' => $matches->isEmpty() ? 'missing' : 'duplicate',
                    ];
                    continue;
                }

                $row = $matches->first();
                $expectedOut = $expectedItem['actual_qty'] + $expectedItem['additional_qty'];
                $actualOut = (int) $row->actual_qty + (int) $row->additional_qty;
                $matchesSnapshot =
                    (int) $row->product_id === $expectedItem['product_id']
                    && (int) $row->actual_qty === $expectedItem['actual_qty']
                    && (int) $row->additional_qty === $expectedItem['additional_qty']
                    && abs((float) $row->unit_price - $expectedItem['unit_price']) < 0.005
                    && $actualOut === $expectedOut;

                if (!$matchesSnapshot) {
                    $itemProblems[] = [
                        'order_number' => $expectedOrder['order_number'],
                        'product_id' => $expectedItem['product_id'],
                        'sales_order_item_id' => (int) $row->id,
                        'reason' => 'snapshot_mismatch',
                    ];
                }

                $resolvedItems[(int) $row->id] = [
                    'row' => $row,
                    'order' => $order,
                    'expected' => $expectedItem,
                ];
            }
        }

        $ledgerProblems = [];
        $existingUniqueLedgerEntries = 0;
        $duplicateLedgerEntries = 0;

        foreach ($resolvedItems as $itemId => $resolved) {
            $expectedQty = (int) $resolved['row']->actual_qty + (int) $resolved['row']->additional_qty;
            $ledgerRows = DB::connection('ledger')
                ->table('product_ledgers')
                ->where('source_type', 'online_report')
                ->where('source_id', $reportId)
                ->where('source_item_id', $itemId)
                ->orderBy('id')
                ->get();

            if ($ledgerRows->count() === 1) {
                $existingUniqueLedgerEntries++;
                $ledger = $ledgerRows->first();
                if (
                    (int) $ledger->product_id !== (int) $resolved['row']->product_id
                    || strtoupper(trim((string) $ledger->transaction_type)) !== 'OUT'
                    || (int) $ledger->quantity_in !== 0
                    || (int) $ledger->quantity_out !== $expectedQty
                ) {
                    $ledgerProblems[] = [
                        'sales_order_item_id' => $itemId,
                        'product_id' => (int) $resolved['row']->product_id,
                        'reason' => 'ledger_mismatch',
                    ];
                }
            } elseif ($ledgerRows->isEmpty()) {
                $ledgerProblems[] = [
                    'sales_order_item_id' => $itemId,
                    'product_id' => (int) $resolved['row']->product_id,
                    'reason' => 'ledger_missing',
                ];
            } else {
                $existingUniqueLedgerEntries++;
                $duplicateLedgerEntries += $ledgerRows->count() - 1;
                $ledgerProblems[] = [
                    'sales_order_item_id' => $itemId,
                    'product_id' => (int) $resolved['row']->product_id,
                    'reason' => 'ledger_duplicate',
                    'count' => $ledgerRows->count(),
                ];
            }
        }

        $reportComplete = true;
        $ordersComplete = empty($orderProblems) && count($resolvedOrders) === count($expected['orders']);
        $itemsComplete = empty($itemProblems) && $actualItemCount === $expectedItemCount;
        $ledgerComplete = empty($ledgerProblems) && $existingUniqueLedgerEntries === count($resolvedItems);
        $verified = $reportComplete && $ordersComplete && $itemsComplete && $ledgerComplete;

        $tableProgress = [
            'online_reports' => self::progressRow(1, 1, $reportComplete),
            'sales_orders' => self::progressRow(count($expected['orders']), count($resolvedOrders), $ordersComplete),
            'sales_order_items' => self::progressRow($expectedItemCount, $actualItemCount, $itemsComplete),
            'product_ledgers' => self::progressRow($expectedItemCount, $existingUniqueLedgerEntries, $ledgerComplete),
        ];

        return [
            'verified' => $verified,
            'online_report_id' => $reportId,
            'expected_entries' => $expectedItemCount,
            'existing_entries' => $existingUniqueLedgerEntries,
            'missing_entries' => max(0, $expectedItemCount - $existingUniqueLedgerEntries),
            'duplicate_entries' => $duplicateLedgerEntries,
            'total_items' => $expectedItemCount,
            'synced_items' => $ledgerComplete ? $expectedItemCount : max(0, $existingUniqueLedgerEntries - count($ledgerProblems)),
            'missing_items' => array_values(array_filter($ledgerProblems, fn ($p) => ($p['reason'] ?? '') === 'ledger_missing')),
            'table_progress' => $tableProgress,
            'problems' => [
                'sales_orders' => $orderProblems,
                'sales_order_items' => $itemProblems,
                'product_ledgers' => $ledgerProblems,
            ],
        ];
    }

    public static function syncByReportId(int $reportId): array
    {
        self::report($reportId);
        $lockName = 'hatdog:online-report-sync:' . $reportId;
        $lockAcquired = self::acquireNamedLock('sales', $lockName, 15);
        if (!$lockAcquired) {
            throw new RuntimeException('Online Report is already being synchronized. Please retry after the current sync finishes.');
        }

        $insertedLedgerEntries = 0;
        $updatedLedgerEntries = 0;
        $duplicatesRemoved = 0;
        $errors = [];

        try {
            $salesResult = self::ensureSalesRows($reportId);
            $ledgerResult = self::ensureLedgerRows($reportId);
            $insertedLedgerEntries = $ledgerResult['inserted_entries'];
            $updatedLedgerEntries = $ledgerResult['updated_entries'];
            $duplicatesRemoved = $ledgerResult['duplicates_removed'];

            $verify = self::verifyByReportId($reportId);
            $status = $verify['verified'] ? 'completed' : 'failed';

            DB::connection('sales')->table('online_report_product_ledger_syncs')->insert([
                'report_id' => $reportId,
                'status' => $status,
                'items_processed' => (int) ($verify['expected_entries'] ?? 0),
                'items_synced' => (int) ($verify['existing_entries'] ?? 0),
                'items_failed' => $verify['verified'] ? 0 : (int) ($verify['missing_entries'] ?? 0),
                'errors' => $verify['verified'] ? null : json_encode($verify['problems'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'synced_at' => self::nowManila(),
                'created_at' => self::nowManila(),
                'updated_at' => self::nowManila(),
            ]);

            return [
                'status' => $verify['verified'] ? ($insertedLedgerEntries > 0 ? 'synced' : 'already_synced') : 'failed',
                'inserted_entries' => $insertedLedgerEntries,
                'updated_entries' => $updatedLedgerEntries,
                'duplicates_removed' => $duplicatesRemoved,
                'sales_orders_created' => $salesResult['sales_orders_created'],
                'sales_orders_updated' => $salesResult['sales_orders_updated'],
                'sales_order_items_created' => $salesResult['sales_order_items_created'],
                'sales_order_items_updated' => $salesResult['sales_order_items_updated'],
                'errors' => $errors,
                'verified' => (bool) $verify['verified'],
                'table_progress' => $verify['table_progress'],
            ];
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
            try {
                DB::connection('sales')->table('online_report_product_ledger_syncs')->insert([
                    'report_id' => $reportId,
                    'status' => 'failed',
                    'items_processed' => 0,
                    'items_synced' => 0,
                    'items_failed' => 1,
                    'errors' => json_encode($errors, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'synced_at' => null,
                    'created_at' => self::nowManila(),
                    'updated_at' => self::nowManila(),
                ]);
            } catch (\Throwable $ignored) {
            }
            throw $e;
        } finally {
            self::releaseNamedLock('sales', $lockName);
        }
    }

    /**
     * Reconcile an edited Online Report as an authoritative item snapshot.
     *
     * Existing items are updated in place. Newly added items create their
     * Sales Order Item + Product Ledger OUT movement. Removed items delete the
     * matching Product Ledger movement and Sales Order Item. Any affected
     * running balances are rebuilt chronologically so product stock stays
     * consistent after an old Online Invoice is edited.
     */
    public static function reconcileEditedReport(int $reportId): array
    {
        self::report($reportId);
        $lockName = 'hatdog:online-report-edit:' . $reportId;
        $lockAcquired = self::acquireNamedLock('sales', $lockName, 15);
        if (!$lockAcquired) {
            throw new RuntimeException('Online Report is already being edited or synchronized. Please retry after the current operation finishes.');
        }

        try {
            $salesResult = self::ensureSalesRows($reportId, true);
            $deletedLedgerResult = self::deleteLedgerRowsForRemovedItems(
                $reportId,
                (array) ($salesResult['deleted_items'] ?? [])
            );
            $createdItemIds = array_map('intval', (array) ($salesResult['created_item_ids'] ?? []));
            $ledgerResult = self::ensureLedgerRows($reportId, true, $createdItemIds);
            $verify = self::verifyByReportId($reportId);

            if (!($verify['verified'] ?? false)) {
                throw new RuntimeException(
                    'Online Invoice was edited, but verification found a Sales Order or Product Ledger mismatch.'
                );
            }

            DB::connection('sales')->table('online_report_product_ledger_syncs')->insert([
                'report_id' => $reportId,
                'status' => 'completed',
                'items_processed' => (int) ($verify['expected_entries'] ?? 0),
                'items_synced' => (int) ($verify['existing_entries'] ?? 0),
                'items_failed' => 0,
                'errors' => null,
                'synced_at' => self::nowManila(),
                'created_at' => self::nowManila(),
                'updated_at' => self::nowManila(),
            ]);

            return [
                'verified' => true,
                'sales_orders_created' => (int) ($salesResult['sales_orders_created'] ?? 0),
                'sales_orders_updated' => (int) ($salesResult['sales_orders_updated'] ?? 0),
                'sales_order_items_created' => (int) ($salesResult['sales_order_items_created'] ?? 0),
                'sales_order_items_updated' => (int) ($salesResult['sales_order_items_updated'] ?? 0),
                'sales_order_items_deleted' => (int) ($salesResult['sales_order_items_deleted'] ?? 0),
                'product_ledgers_updated' => (int) ($ledgerResult['updated_entries'] ?? 0),
                'product_ledgers_created' => (int) ($ledgerResult['inserted_entries'] ?? 0),
                'product_ledgers_deleted' => (int) ($deletedLedgerResult['deleted_entries'] ?? 0),
                'duplicates_removed' => (int) ($ledgerResult['duplicates_removed'] ?? 0),
                'table_progress' => $verify['table_progress'] ?? [],
            ];
        } finally {
            self::releaseNamedLock('sales', $lockName);
        }
    }

    public static function getSyncStats(): array
    {
        $totalReports = (int) DB::connection('sales')->table('online_reports')->count();
        $syncedReports = (int) DB::connection('sales')
            ->table('online_report_product_ledger_syncs')
            ->where('status', 'completed')
            ->distinct()
            ->count('report_id');
        $failedReports = (int) DB::connection('sales')
            ->table('online_report_product_ledger_syncs')
            ->where('status', 'failed')
            ->distinct()
            ->count('report_id');

        return [
            'total_reports' => $totalReports,
            'synced_reports' => $syncedReports,
            'unsynced_reports' => max(0, $totalReports - $syncedReports),
            'failed_reports' => $failedReports,
            'sync_percentage' => $totalReports > 0 ? round(($syncedReports / $totalReports) * 100, 2) : 100,
        ];
    }

    public static function getUnsyncedReports(): array
    {
        return DB::connection('sales')
            ->table('online_reports as r')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('online_report_product_ledger_syncs as s')
                    ->whereColumn('s.report_id', 'r.id')
                    ->where('s.status', 'completed');
            })
            ->orderByDesc('r.id')
            ->limit(100)
            ->get(['r.id', 'r.sales_note_ids', 'r.invoice_numbers', 'r.status', 'r.created_at'])
            ->map(fn ($row) => (array) $row)
            ->values()
            ->all();
    }

    private static function ensureSalesRows(int $reportId, bool $deleteUnexpectedItems = false): array
    {
        $report = self::report($reportId);
        $expected = self::expectedStructure($report);
        $createdOrders = 0;
        $updatedOrders = 0;
        $createdItems = 0;
        $updatedItems = 0;
        $deletedItemsCount = 0;
        $createdItemIds = [];
        $deletedItems = [];

        DB::connection('sales')->transaction(function () use (
            $reportId,
            $expected,
            $deleteUnexpectedItems,
            &$createdOrders,
            &$updatedOrders,
            &$createdItems,
            &$updatedItems,
            &$deletedItemsCount,
            &$createdItemIds,
            &$deletedItems
        ) {
            foreach ($expected['orders'] as $expectedOrder) {
                $orders = DB::connection('sales')
                    ->table('sales_orders')
                    ->where('order_number', $expectedOrder['order_number'])
                    ->lockForUpdate()
                    ->get();

                if ($orders->count() > 1) {
                    throw new RuntimeException('Duplicate Sales Orders already exist for ' . $expectedOrder['order_number'] . '. Sync stopped to avoid deleting valid history.');
                }

                if ($orders->isEmpty()) {
                    $salesOrderId = DB::connection('sales')->table('sales_orders')->insertGetId([
                        'sales_note_id' => $expectedOrder['sales_note_id'],
                        'order_number' => $expectedOrder['order_number'],
                        'customer_id' => $expectedOrder['customer_id'],
                        'customer_name' => $expectedOrder['customer_name'],
                        'invoice_numbers' => $expectedOrder['invoice_number'],
                        'total_amount' => 0,
                        'status' => 'Confirmed',
                        'created_at' => $expectedOrder['created_at'],
                        'updated_at' => self::nowManila(),
                    ]);
                    $createdOrders++;
                } else {
                    $salesOrderId = (int) $orders->first()->id;
                    DB::connection('sales')->table('sales_orders')->where('id', $salesOrderId)->update([
                        'sales_note_id' => $expectedOrder['sales_note_id'],
                        'customer_id' => $expectedOrder['customer_id'],
                        'customer_name' => $expectedOrder['customer_name'],
                        'invoice_numbers' => $expectedOrder['invoice_number'],
                        'status' => 'Confirmed',
                        'updated_at' => self::nowManila(),
                    ]);
                    $updatedOrders++;
                }

                $expectedProductIds = [];
                $orderTotal = 0.0;

                foreach ($expectedOrder['items'] as $item) {
                    if (isset($expectedProductIds[$item['product_id']])) {
                        throw new RuntimeException('Online Report contains the same product more than once in ' . $expectedOrder['order_number'] . '. Sync stopped to prevent duplicate Sales Order Items.');
                    }
                    $expectedProductIds[$item['product_id']] = true;

                    $rows = DB::connection('sales')
                        ->table('sales_order_items')
                        ->where('sales_order_id', $salesOrderId)
                        ->where('product_id', $item['product_id'])
                        ->lockForUpdate()
                        ->get();

                    if ($rows->count() > 1) {
                        throw new RuntimeException('Duplicate Sales Order Items already exist for product ID ' . $item['product_id'] . ' in ' . $expectedOrder['order_number'] . '. Sync stopped to avoid compounding the duplicate.');
                    }

                    $payload = [
                        'sales_order_id' => $salesOrderId,
                        'product_id' => $item['product_id'],
                        'price_code' => $item['price_code'],
                        'product_code' => $item['product_code'],
                        'description' => $item['description'],
                        'quantity' => $item['actual_qty'],
                        'actual_qty' => $item['actual_qty'],
                        'additional_qty' => $item['additional_qty'],
                        'oum' => $item['oum'],
                        'unit_price' => $item['unit_price'],
                        'discount' => $item['discount'],
                        'additional_discount' => $item['additional_discount'],
                        'subtotal' => $item['subtotal'],
                        'particulars' => $item['particulars'],
                        'updated_at' => self::nowManila(),
                    ];

                    if ($rows->isEmpty()) {
                        $payload['created_at'] = $expectedOrder['created_at'];
                        $newItemId = (int) DB::connection('sales')->table('sales_order_items')->insertGetId($payload);
                        $createdItemIds[] = $newItemId;
                        $createdItems++;
                    } else {
                        DB::connection('sales')->table('sales_order_items')->where('id', $rows->first()->id)->update($payload);
                        $updatedItems++;
                    }
                    $orderTotal += $item['subtotal'];
                }

                $actualRows = DB::connection('sales')
                    ->table('sales_order_items')
                    ->where('sales_order_id', $salesOrderId)
                    ->lockForUpdate()
                    ->get(['id', 'product_id']);

                foreach ($actualRows as $actualRow) {
                    if (isset($expectedProductIds[(int) $actualRow->product_id])) {
                        continue;
                    }

                    if (!$deleteUnexpectedItems) {
                        throw new RuntimeException(
                            'Unexpected Sales Order Item ID ' . $actualRow->id . ' exists in '
                            . $expectedOrder['order_number'] . '. Sync stopped instead of deleting existing data automatically.'
                        );
                    }

                    $deletedItems[] = [
                        'sales_order_item_id' => (int) $actualRow->id,
                        'sales_order_id' => $salesOrderId,
                        'product_id' => (int) $actualRow->product_id,
                        'order_number' => (string) $expectedOrder['order_number'],
                        'sales_note_id' => (int) ($expectedOrder['sales_note_id'] ?? 0),
                    ];

                    DB::connection('sales')
                        ->table('sales_order_items')
                        ->where('id', (int) $actualRow->id)
                        ->delete();
                    $deletedItemsCount++;
                }

                DB::connection('sales')->table('sales_orders')->where('id', $salesOrderId)->update([
                    'total_amount' => round($orderTotal, 2),
                    'updated_at' => self::nowManila(),
                ]);

                if ($expectedOrder['sales_note_id']) {
                    $note = DB::connection('sales')->table('sales_notes')->where('id', $expectedOrder['sales_note_id'])->lockForUpdate()->first();
                    if ($note) {
                        $remarks = trim((string) ($note->remarks ?? ''));
                        $invoiceMarker = trim((string) $expectedOrder['invoice_number']) !== ''
                            ? 'Invoice: ' . trim((string) $expectedOrder['invoice_number'])
                            : '';
                        if ($invoiceMarker !== '' && stripos($remarks, $invoiceMarker) === false) {
                            $remarks = trim($remarks . ($remarks !== '' ? ' | ' : '') . $invoiceMarker);
                        }
                        $hasItems = DB::connection('sales')->table('sales_note_items')->where('sales_note_id', $note->id)->exists();
                        DB::connection('sales')->table('sales_notes')->where('id', $note->id)->update([
                            'remarks' => $remarks !== '' ? $remarks : $note->remarks,
                            'status' => $hasItems ? 'Closed' : $note->status,
                            'updated_at' => self::nowManila(),
                        ]);
                    }
                }
            }
        });

        return [
            'sales_orders_created' => $createdOrders,
            'sales_orders_updated' => $updatedOrders,
            'sales_order_items_created' => $createdItems,
            'sales_order_items_updated' => $updatedItems,
            'sales_order_items_deleted' => $deletedItemsCount,
            'created_item_ids' => array_values(array_unique(array_map('intval', $createdItemIds))),
            'deleted_items' => $deletedItems,
        ];
    }

    private static function ensureLedgerRows(int $reportId, bool $allowInsert = true, array $forceInsertItemIds = []): array
    {
        $items = DB::connection('sales')
            ->table('sales_orders as so')
            ->join('sales_order_items as soi', 'soi.sales_order_id', '=', 'so.id')
            ->where('so.order_number', 'like', 'ONL-' . $reportId . '-%')
            ->orderBy('so.id')
            ->orderBy('soi.id')
            ->get([
                'so.id as sales_order_id',
                'so.order_number',
                'so.customer_id',
                'so.customer_name',
                'so.invoice_numbers',
                'so.created_at as sales_order_created_at',
                'soi.id as sales_order_item_id',
                'soi.product_id',
                'soi.product_code',
                'soi.actual_qty',
                'soi.additional_qty',
                'soi.oum',
                'soi.unit_price',
            ]);

        // Legacy Online Report ledger rows used the Sales Note sales_number as
        // transaction_number and did not have source_type/source_id/source_item_id.
        // Build a conservative report-index -> sales_number map so an edit can
        // adopt and update that exact historical row instead of inserting a new one.
        $report = self::report($reportId);
        $reportNoteIds = array_values(array_filter(array_map(
            fn ($value) => (int) trim((string) $value),
            explode(',', (string) $report->sales_note_ids)
        ), fn ($value) => $value > 0));
        $legacySalesNumberByIndex = [];
        if (!empty($reportNoteIds)) {
            $noteRows = DB::connection('sales')->table('sales_notes')
                ->whereIn('id', $reportNoteIds)
                ->get(['id', 'sales_number'])
                ->keyBy(fn ($row) => (int) $row->id);
            foreach ($reportNoteIds as $index => $noteId) {
                $legacySalesNumberByIndex[$index] = trim((string) ($noteRows->get($noteId)->sales_number ?? ''));
            }
        }

        $inserted = 0;
        $updated = 0;
        $duplicatesRemoved = 0;
        $links = [];
        $affectedLedgerIdsByProduct = [];
        $latestBalances = [];

        DB::connection('ledger')->transaction(function () use (
            $reportId,
            $items,
            &$inserted,
            &$updated,
            &$duplicatesRemoved,
            &$links,
            &$affectedLedgerIdsByProduct,
            &$latestBalances,
            $legacySalesNumberByIndex,
            $allowInsert,
            $forceInsertItemIds
        ) {
            foreach ($items as $item) {
                $productId = (int) $item->product_id;
                $itemId = (int) $item->sales_order_item_id;
                $qtyOut = max(0, (int) $item->actual_qty + (int) $item->additional_qty);
                $createdAt = self::normalizeTimestamp($item->sales_order_created_at);
                $ledgerDate = substr($createdAt, 0, 10);
                $idempotencyKey = 'online_report:' . $reportId . ':item:' . $itemId . ':actual:' . $qtyOut;

                $product = DB::connection('masterlist')->table('products')->where('id', $productId)->first(['cost']);
                $cost = (float) ($product->cost ?? 0);

                $rows = DB::connection('ledger')
                    ->table('product_ledgers')
                    ->where('source_type', 'online_report')
                    ->where('source_id', $reportId)
                    ->where('source_item_id', $itemId)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                // First fallback: use the durable report -> Sales Order Item ->
                // Product Ledger link, even if an older row has not yet been
                // stamped with source_type/source_id/source_item_id.
                if ($rows->isEmpty()) {
                    $link = DB::connection('sales')->table('online_report_product_ledger_links')
                        ->where('report_id', $reportId)
                        ->where('sales_order_item_id', $itemId)
                        ->orderByDesc('id')
                        ->first();
                    if ($link && (int) ($link->product_ledger_id ?? 0) > 0) {
                        $linkedRow = DB::connection('ledger')->table('product_ledgers')
                            ->where('id', (int) $link->product_ledger_id)
                            ->lockForUpdate()
                            ->first();
                        if ($linkedRow) {
                            $rows = collect([$linkedRow]);
                        }
                    }
                }

                // Normal sync may recover by idempotency key. For Edit Online
                // Invoice this only identifies an already-existing row; it still
                // never permits a new Product Ledger transaction.
                if ($rows->isEmpty()) {
                    $sameKey = DB::connection('ledger')->table('product_ledgers')
                        ->where('idempotency_key', $idempotencyKey)
                        ->lockForUpdate()
                        ->first();
                    if ($sameKey) {
                        $rows = collect([$sameKey]);
                    }
                }

                // Legacy fallback for old Online Reports: transaction_number was
                // the Sales Note sales_number and remarks was Online Report Generation.
                // Newly-created Sales Order Items skip this fallback so a newly-added
                // product always receives its own fresh Product Ledger movement.
                $forceInsertItem = in_array($itemId, array_map('intval', $forceInsertItemIds), true);
                if ($rows->isEmpty() && !$forceInsertItem) {
                    $orderIndex = null;
                    if (preg_match('/^ONL-' . preg_quote((string) $reportId, '/') . '-(\d+)$/', (string) $item->order_number, $matches)) {
                        $orderIndex = (int) $matches[1];
                    }
                    $legacySalesNumber = $orderIndex !== null
                        ? trim((string) ($legacySalesNumberByIndex[$orderIndex] ?? ''))
                        : '';

                    if ($legacySalesNumber !== '') {
                        $legacyRows = DB::connection('ledger')->table('product_ledgers')
                            ->where('product_id', $productId)
                            ->where('transaction_type', 'OUT')
                            ->where('transaction_number', $legacySalesNumber)
                            ->where('remarks', 'like', 'Online Report Generation%')
                            ->orderBy('id')
                            ->lockForUpdate()
                            ->get();

                        if ($legacyRows->count() === 1) {
                            $rows = $legacyRows;
                        } elseif ($legacyRows->count() > 1) {
                            throw new RuntimeException(
                                'Edit stopped because more than one legacy Product Ledger row matches product ID ' . $productId
                                . ' for Online Report #' . $reportId . '. No new Product Ledger transaction was created.'
                            );
                        }
                    }
                }

                if ($rows->count() > 1) {
                    $keep = $rows->first();
                    $duplicateIds = $rows->slice(1)->pluck('id')->map(fn ($id) => (int) $id)->all();
                    DB::connection('ledger')->table('product_ledgers')->whereIn('id', $duplicateIds)->delete();
                    $duplicatesRemoved += count($duplicateIds);
                    $rows = collect([$keep]);
                }

                $existingLedgerRow = $rows->isNotEmpty() ? $rows->first() : null;
                if (!$allowInsert && !$existingLedgerRow) {
                    throw new RuntimeException(
                        'Existing Product Ledger row was not found for Online Report #' . $reportId
                        . ', Sales Order Item #' . $itemId . ', product ID ' . $productId
                        . '. Edit stopped so no new Product Ledger transaction would be created.'
                    );
                }

                // An edit must keep the original ledger movement in the same
                // chronological position. This is what lets the balance rebuild
                // correctly from that old invoice through the newest invoice.
                if ($existingLedgerRow && !empty($existingLedgerRow->date)) {
                    $ledgerDate = (string) $existingLedgerRow->date;
                }

                $payload = [
                    'product_id' => $productId,
                    'supplier_id' => null,
                    'customer_id' => $item->customer_id ? (int) $item->customer_id : null,
                    'source_type' => 'online_report',
                    'source_id' => $reportId,
                    'source_item_id' => $itemId,
                    'processed_actual_qty' => $qtyOut,
                    'transaction_type' => 'OUT',
                    'date' => $ledgerDate,
                    'transaction_number' => $existingLedgerRow
                        ? (string) ($existingLedgerRow->transaction_number ?? $item->order_number)
                        : (string) $item->order_number,
                    'reference_number' => (string) ($item->invoice_numbers ?? ''),
                    'entity_name' => (string) ($item->customer_name ?? ''),
                    'quantity_in' => 0,
                    'quantity_out' => $qtyOut,
                    'oum' => (string) ($item->oum ?? ''),
                    'price' => (float) ($item->unit_price ?? 0),
                    'cost' => $cost,
                    'remarks' => 'Online Report Generation - Invoice: ' . (string) ($item->invoice_numbers ?? ''),
                    'idempotency_key' => $idempotencyKey,
                    'updated_at' => self::nowManila(),
                ];

                if ($rows->isEmpty()) {
                    if (!$allowInsert) {
                        throw new RuntimeException('Edit Online Invoice is not allowed to insert a new Product Ledger transaction.');
                    }
                    $payload['balance_stock'] = 0;
                    $payload['created_at'] = $createdAt;
                    $ledgerId = (int) DB::connection('ledger')->table('product_ledgers')->insertGetId($payload);
                    $inserted++;
                } else {
                    $ledgerId = (int) $rows->first()->id;
                    DB::connection('ledger')->table('product_ledgers')->where('id', $ledgerId)->update($payload);
                    $updated++;
                }

                $affectedLedgerIdsByProduct[$productId][] = $ledgerId;
                $links[] = [
                    'report_id' => $reportId,
                    'sales_order_id' => (int) $item->sales_order_id,
                    'sales_order_item_id' => $itemId,
                    'product_ledger_id' => $ledgerId,
                    'product_id' => $productId,
                    'quantity' => $qtyOut,
                ];
            }

            foreach ($affectedLedgerIdsByProduct as $productId => $ledgerIds) {
                $latestBalances[(int) $productId] = self::recalculateBalancesFromAffectedRow((int) $productId, $ledgerIds);
            }
        });

        DB::connection('sales')->transaction(function () use ($reportId, $links) {
            foreach ($links as $link) {
                DB::connection('sales')->table('online_report_product_ledger_links')
                    ->where('report_id', $reportId)
                    ->where('sales_order_item_id', $link['sales_order_item_id'])
                    ->delete();
                DB::connection('sales')->table('online_report_product_ledger_links')->insert($link + [
                    'created_at' => self::nowManila(),
                    'updated_at' => self::nowManila(),
                ]);
            }
        });

        foreach ($latestBalances as $productId => $balance) {
            try {
                DB::connection('masterlist')->table('products')->where('id', $productId)->update([
                    'on_hand' => $balance,
                    'updated_at' => self::nowManila(),
                ]);
            } catch (\Throwable $ignored) {
                // Product Ledger remains authoritative even if the cache-like masterlist value cannot be updated.
            }
        }

        return [
            'inserted_entries' => $inserted,
            'updated_entries' => $updated,
            'duplicates_removed' => $duplicatesRemoved,
        ];
    }

    /**
     * Delete Product Ledger movements that belong to Sales Order Items removed
     * from an edited Online Invoice, then rebuild stock from the earliest
     * deleted movement forward.
     */
    private static function deleteLedgerRowsForRemovedItems(int $reportId, array $deletedItems): array
    {
        if (empty($deletedItems)) {
            return ['deleted_entries' => 0, 'affected_products' => []];
        }

        $report = self::report($reportId);
        $reportNoteIds = array_values(array_filter(array_map(
            fn ($value) => (int) trim((string) $value),
            explode(',', (string) $report->sales_note_ids)
        ), fn ($value) => $value > 0));

        $legacySalesNumberByIndex = [];
        if (!empty($reportNoteIds)) {
            $noteRows = DB::connection('sales')->table('sales_notes')
                ->whereIn('id', $reportNoteIds)
                ->get(['id', 'sales_number'])
                ->keyBy(fn ($row) => (int) $row->id);

            foreach ($reportNoteIds as $index => $noteId) {
                $legacySalesNumberByIndex[$index] = trim((string) ($noteRows->get($noteId)->sales_number ?? ''));
            }
        }

        $deletedEntries = 0;
        $deletedRowsByProduct = [];
        $deletedItemIds = [];

        DB::connection('ledger')->transaction(function () use (
            $reportId,
            $deletedItems,
            $legacySalesNumberByIndex,
            &$deletedEntries,
            &$deletedRowsByProduct,
            &$deletedItemIds
        ) {
            foreach ($deletedItems as $deletedItem) {
                $itemId = (int) ($deletedItem['sales_order_item_id'] ?? 0);
                $productId = (int) ($deletedItem['product_id'] ?? 0);
                $orderNumber = trim((string) ($deletedItem['order_number'] ?? ''));
                if ($itemId <= 0 || $productId <= 0) {
                    continue;
                }

                $deletedItemIds[] = $itemId;
                $ledgerIds = DB::connection('ledger')->table('product_ledgers')
                    ->where('source_type', 'online_report')
                    ->where('source_id', $reportId)
                    ->where('source_item_id', $itemId)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $linkedLedgerIds = DB::connection('sales')->table('online_report_product_ledger_links')
                    ->where('report_id', $reportId)
                    ->where('sales_order_item_id', $itemId)
                    ->pluck('product_ledger_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $ledgerIds = array_values(array_unique(array_filter(array_merge($ledgerIds, $linkedLedgerIds))));

                // Conservative fallback for pre-link legacy Online Reports.
                if (empty($ledgerIds)) {
                    $orderIndex = null;
                    if ($orderNumber !== ''
                        && preg_match('/^ONL-' . preg_quote((string) $reportId, '/') . '-(\d+)$/', $orderNumber, $matches)
                    ) {
                        $orderIndex = (int) $matches[1];
                    }

                    $legacySalesNumber = $orderIndex !== null
                        ? trim((string) ($legacySalesNumberByIndex[$orderIndex] ?? ''))
                        : '';

                    if ($legacySalesNumber !== '') {
                        $legacyRows = DB::connection('ledger')->table('product_ledgers')
                            ->where('product_id', $productId)
                            ->where('transaction_type', 'OUT')
                            ->where('transaction_number', $legacySalesNumber)
                            ->where('remarks', 'like', 'Online Report Generation%')
                            ->orderBy('id')
                            ->lockForUpdate()
                            ->get(['id', 'product_id', 'date', 'created_at']);

                        if ($legacyRows->count() > 1) {
                            throw new RuntimeException(
                                'Delete stopped because more than one legacy Product Ledger row matches product ID '
                                . $productId . ' for Online Report #' . $reportId . '.'
                            );
                        }

                        if ($legacyRows->count() === 1) {
                            $ledgerIds[] = (int) $legacyRows->first()->id;
                        }
                    }
                }

                if (empty($ledgerIds)) {
                    continue;
                }

                $rowsToDelete = DB::connection('ledger')->table('product_ledgers')
                    ->whereIn('id', $ledgerIds)
                    ->where('product_id', $productId)
                    ->lockForUpdate()
                    ->get(['id', 'product_id', 'date', 'created_at']);

                if ($rowsToDelete->isEmpty()) {
                    continue;
                }

                foreach ($rowsToDelete as $row) {
                    $deletedRowsByProduct[$productId][] = [
                        'id' => (int) $row->id,
                        'date' => $row->date,
                        'created_at' => $row->created_at,
                    ];
                }

                DB::connection('ledger')->table('product_ledgers')
                    ->whereIn('id', $rowsToDelete->pluck('id')->all())
                    ->delete();

                $deletedEntries += $rowsToDelete->count();
            }
        });

        if (!empty($deletedItemIds)) {
            DB::connection('sales')->table('online_report_product_ledger_links')
                ->where('report_id', $reportId)
                ->whereIn('sales_order_item_id', array_values(array_unique($deletedItemIds)))
                ->delete();
        }

        $affectedProducts = [];
        foreach ($deletedRowsByProduct as $productId => $deletedRows) {
            $balance = self::recalculateBalancesAfterDeletedRows((int) $productId, $deletedRows);
            $affectedProducts[] = (int) $productId;

            try {
                DB::connection('masterlist')->table('products')->where('id', (int) $productId)->update([
                    'on_hand' => $balance,
                    'updated_at' => self::nowManila(),
                ]);
            } catch (\Throwable $ignored) {
                // Product Ledger remains authoritative.
            }
        }

        return [
            'deleted_entries' => $deletedEntries,
            'affected_products' => array_values(array_unique($affectedProducts)),
        ];
    }

    private static function recalculateBalancesAfterDeletedRows(int $productId, array $deletedRows): int
    {
        if (empty($deletedRows)) {
            return (int) (DB::connection('ledger')->table('product_ledgers')
                ->where('product_id', $productId)
                ->orderByDesc('date')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->value('balance_stock') ?? 0);
        }

        usort($deletedRows, fn ($a, $b) => self::compareLedgerPosition($a, $b));
        $earliestDeleted = $deletedRows[0];

        $rows = DB::connection('ledger')
            ->table('product_ledgers')
            ->where('product_id', $productId)
            ->orderBy('date')
            ->orderByRaw('COALESCE(created_at, "1970-01-01 00:00:00") ASC')
            ->orderBy('id')
            ->get(['id', 'date', 'created_at', 'quantity_in', 'quantity_out', 'balance_stock']);

        if ($rows->isEmpty()) {
            return 0;
        }

        $startIndex = null;
        foreach ($rows as $index => $row) {
            if (self::compareLedgerPosition($row, $earliestDeleted) > 0) {
                $startIndex = $index;
                break;
            }
        }

        if ($startIndex === null) {
            return (int) ($rows->last()->balance_stock ?? 0);
        }

        $balance = $startIndex > 0 ? (int) ($rows[$startIndex - 1]->balance_stock ?? 0) : 0;
        for ($i = $startIndex, $count = $rows->count(); $i < $count; $i++) {
            $row = $rows[$i];
            $balance += (int) ($row->quantity_in ?? 0) - (int) ($row->quantity_out ?? 0);
            if ((int) ($row->balance_stock ?? 0) !== $balance) {
                DB::connection('ledger')->table('product_ledgers')->where('id', $row->id)->update([
                    'balance_stock' => $balance,
                    'updated_at' => self::nowManila(),
                ]);
            }
        }

        return $balance;
    }

    private static function compareLedgerPosition($left, $right): int
    {
        $value = static function ($row, string $key) {
            if (is_array($row)) {
                return $row[$key] ?? null;
            }
            return $row->{$key} ?? null;
        };

        $leftDate = (string) ($value($left, 'date') ?? '');
        $rightDate = (string) ($value($right, 'date') ?? '');
        $dateCompare = strcmp($leftDate, $rightDate);
        if ($dateCompare !== 0) {
            return $dateCompare;
        }

        $leftCreatedAt = (string) ($value($left, 'created_at') ?? '1970-01-01 00:00:00');
        $rightCreatedAt = (string) ($value($right, 'created_at') ?? '1970-01-01 00:00:00');
        $createdCompare = strcmp($leftCreatedAt, $rightCreatedAt);
        if ($createdCompare !== 0) {
            return $createdCompare;
        }

        return ((int) ($value($left, 'id') ?? 0)) <=> ((int) ($value($right, 'id') ?? 0));
    }

    private static function recalculateBalancesFromAffectedRow(int $productId, array $affectedLedgerIds): int
    {
        $affected = array_fill_keys(array_map('intval', $affectedLedgerIds), true);
        $rows = DB::connection('ledger')
            ->table('product_ledgers')
            ->where('product_id', $productId)
            ->orderBy('date')
            ->orderByRaw('COALESCE(created_at, "1970-01-01 00:00:00") ASC')
            ->orderBy('id')
            ->get(['id', 'quantity_in', 'quantity_out', 'balance_stock']);

        $startIndex = null;
        foreach ($rows as $index => $row) {
            if (isset($affected[(int) $row->id])) {
                $startIndex = $index;
                break;
            }
        }

        if ($startIndex === null) {
            return (int) ($rows->last()->balance_stock ?? 0);
        }

        $balance = $startIndex > 0 ? (int) $rows[$startIndex - 1]->balance_stock : 0;
        for ($i = $startIndex, $count = $rows->count(); $i < $count; $i++) {
            $row = $rows[$i];
            $balance += (int) $row->quantity_in - (int) $row->quantity_out;
            if ((int) $row->balance_stock !== $balance) {
                DB::connection('ledger')->table('product_ledgers')->where('id', $row->id)->update([
                    'balance_stock' => $balance,
                    'updated_at' => self::nowManila(),
                ]);
            }
        }

        return $balance;
    }

    private static function expectedStructure(object $report): array
    {
        $noteIds = array_values(array_filter(array_map(
            fn ($id) => (int) trim((string) $id),
            explode(',', (string) $report->sales_note_ids)
        ), fn ($id) => $id > 0));
        $notesData = self::decodeJson($report->notes_data);
        $invoiceNumbers = self::decodeJson($report->invoice_numbers);
        $prices = self::decodeJson($report->prices);

        $orders = [];
        foreach ($noteIds as $index => $noteId) {
            $noteRow = DB::connection('sales')->table('sales_notes')->where('id', $noteId)->first();
            $noteData = is_array($notesData[$index] ?? null) ? $notesData[$index] : [];
            $invoice = trim((string) ($invoiceNumbers[$index] ?? ''));
            $customerId = (int) ($noteData['customer_id'] ?? ($noteRow->customer_id ?? 0));
            $customerName = (string) ($noteData['customer_name'] ?? ($noteRow->customer_name ?? ''));
            $items = [];

            foreach ((array) ($noteData['items'] ?? []) as $item) {
                if (!is_array($item)) continue;
                $productId = (int) ($item['product_id'] ?? 0);
                if ($productId <= 0) continue;
                $product = DB::connection('masterlist')->table('products')->where('id', $productId)->first([
                    'product_code', 'description', 'price_online', 'cost'
                ]);
                if (!$product) {
                    throw new RuntimeException('Master-list product ID ' . $productId . ' was not found while synchronizing Online Report #' . $report->id . '.');
                }
                $actualQty = max(0, (int) ($item['quantity'] ?? 0));
                $bonusQty = max(0, (int) ($item['additional_qty'] ?? 0));
                // Preserve the original Online Report generator rule: an explicit report price
                // wins; otherwise use the current master-list online price.
                $price = array_key_exists((string) $productId, $prices)
                    ? (float) $prices[(string) $productId]
                    : (float) ($product->price_online ?? 0);
                $discount = (float) ($item['discount'] ?? 0);
                $additionalDiscount = (float) ($item['additional_discount'] ?? 0);
                $subtotal = round($actualQty * $price, 2);

                $items[] = [
                    'product_id' => $productId,
                    'price_code' => trim((string) ($item['price_code'] ?? '')) ?: null,
                    'product_code' => trim((string) ($item['product_code'] ?? '')) ?: (string) $product->product_code,
                    'description' => (string) ($item['description'] ?? $product->description ?? ''),
                    'actual_qty' => $actualQty,
                    'additional_qty' => $bonusQty,
                    'oum' => (string) ($item['oum'] ?? ''),
                    'unit_price' => $price,
                    'discount' => $discount,
                    'additional_discount' => $additionalDiscount,
                    'subtotal' => $subtotal,
                    'particulars' => (string) ($item['particulars'] ?? ''),
                ];
            }

            $orders[] = [
                'index' => $index,
                'sales_note_id' => $noteId,
                'order_number' => 'ONL-' . $report->id . '-' . $index,
                'customer_id' => $customerId,
                'customer_name' => $customerName,
                'invoice_number' => $invoice,
                'items' => $items,
                'created_at' => self::normalizeTimestamp($report->created_at),
            ];
        }

        return ['orders' => $orders];
    }

    private static function report(int $reportId): object
    {
        $report = DB::connection('sales')->table('online_reports')->where('id', $reportId)->first();
        if (!$report) {
            throw new RuntimeException('Online Report #' . $reportId . ' was not found.');
        }
        return $report;
    }

    private static function progressRow(int $expected, int $actual, bool $complete): array
    {
        $percent = $expected <= 0 ? 100 : min(100, (int) floor(($actual / max(1, $expected)) * 100));
        return [
            'expected' => $expected,
            'actual' => $actual,
            'percent' => $complete ? 100 : $percent,
            'status' => $complete ? 'complete' : 'incomplete',
        ];
    }

    private static function decodeJson($value): array
    {
        if (is_array($value)) return $value;
        if (is_object($value)) return (array) $value;
        if (!is_string($value) || trim($value) === '') return [];
        $decoded = json_decode($value, true);
        if (!is_array($decoded)) return [];
        return $decoded;
    }

    private static function normalizeTimestamp($value): string
    {
        if ($value) {
            $text = trim((string) $value);
            if ($text !== '') return $text;
        }
        return self::nowManila();
    }

    private static function nowManila(): string
    {
        return now('Asia/Manila')->format('Y-m-d H:i:s');
    }

    private static function acquireNamedLock(string $connection, string $name, int $timeout): bool
    {
        $row = DB::connection($connection)->selectOne('SELECT GET_LOCK(?, ?) AS acquired', [$name, $timeout]);
        return (int) ($row->acquired ?? 0) === 1;
    }

    private static function releaseNamedLock(string $connection, string $name): void
    {
        try {
            DB::connection($connection)->selectOne('SELECT RELEASE_LOCK(?) AS released', [$name]);
        } catch (\Throwable $ignored) {
        }
    }
}
