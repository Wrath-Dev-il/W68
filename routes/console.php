<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('data-ups:run', function () {
    $backupDir = storage_path('app/data-ups');
    File::ensureDirectoryExists($backupDir);

    $connections = ['masterlist', 'purchase', 'sales', 'accounting', 'ledger'];
    $excludeTables = ['migrations', 'cache', 'cache_locks', 'failed_jobs', 'jobs', 'job_batches', 'password_reset_tokens', 'sessions'];
    $createdFiles = [];

    foreach ($connections as $connection) {
        try {
            $tables = collect(DB::connection($connection)->select('SHOW TABLES'))
                ->map(fn ($row) => array_values((array) $row)[0] ?? null)
                ->filter()
                ->reject(fn ($t) => in_array($t, $excludeTables))
                ->values();

            $payload = [
                'connection' => $connection,
                'database' => config("database.connections.{$connection}.database"),
                'created_at' => now('Asia/Manila')->toDateTimeString(),
                'timezone' => 'Asia/Manila',
                'tables' => [],
            ];

            foreach ($tables as $table) {
                $payload['tables'][$table] = DB::connection($connection)->table($table)->get()->toArray();
            }

            $fileName = now('Asia/Manila')->format('Y-m-d_His')."_{$connection}.json";
            $filePath = $backupDir.DIRECTORY_SEPARATOR.$fileName;

            File::put($filePath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $createdFiles[] = $fileName;
        } catch (\Throwable $e) {
            $this->error("{$connection} backup failed: {$e->getMessage()}");
        }
    }

    $deleteBefore = now('Asia/Manila')->subDays(7)->timestamp;
    $deletedCount = 0;

    foreach (File::files($backupDir) as $file) {
        if ($file->getMTime() < $deleteBefore) {
            File::delete($file->getPathname());
            $deletedCount++;
        }
    }

    $this->info('Created data-up files: '.(count($createdFiles) ? implode(', ', $createdFiles) : 'none'));
    $this->info("Deleted expired data-up files: {$deletedCount}");

    return count($createdFiles) > 0 ? 0 : 1;
})->purpose('Back up core system data and remove data-up files older than 7 days.');

Artisan::command('shopee:sync-prices {--dry-run : Fetch and match Shopee prices without saving changes} {--full : Fetch the full Shopee catalog instead of linked items only} {--page-size=50 : Shopee API page size for --full mode, max 50}', function () {
    @set_time_limit(0);

    $lock = Cache::lock('shopee-price-sync', 600);
    if (!$lock->get()) {
        $this->warn('Shopee price sync is already running. Skipped this run.');
        return 0;
    }

    try {
        $service = app(\App\Services\ShopeePriceSyncService::class);
        $result = $this->option('full')
            ? $service->syncFullCatalogFromShopee((int) $this->option('page-size'), (bool) $this->option('dry-run'))
            : $service->syncFromShopee((int) $this->option('page-size'), (bool) $this->option('dry-run'));
        $summary = $result['summary'] ?? [];

        $this->info($result['message'] ?? 'Shopee price sync finished.');
        $this->line('Mode: '.($result['mode'] ?? ($this->option('full') ? 'full' : 'fallback')));
        $this->line('Linked Shopee items checked: '.($result['linked_items_checked'] ?? 'n/a'));
        $this->line('Items fetched: '.($summary['total_shopee_items_fetched'] ?? 0));
        $this->line('Matched online products: '.($summary['matched_online_products'] ?? 0));
        $this->line('Updated online_products: '.($summary['updated_online_product_prices'] ?? 0));
        $this->line('Updated linked products.price_online: '.($summary['updated_linked_system_product_prices'] ?? 0));
        $this->line('Skipped/unmatched: '.($summary['skipped_unmatched_items'] ?? 0));

        foreach (($summary['errors'] ?? []) as $error) {
            $this->error($error);
        }

        return ($result['success'] ?? false) ? 0 : 1;
    } finally {
        $lock->release();
    }
})->purpose('Pull latest Shopee prices into online_products and linked products.price_online.');

Artisan::command('shopee:sync-inventory-reconciliation', function () {
    if (!config('services.shopee.auto_stock_sync', false)) {
        $this->warn('Shopee auto stock sync is disabled (SHOPEE_AUTO_STOCK_SYNC=false).');
        return 0;
    }

    $lock = Cache::lock('shopee-inventory-reconciliation', 600);
    if (!$lock->get()) {
        $this->warn('Inventory reconciliation is already running. Skipped this run.');
        return 0;
    }

    try {
        $connectedProducts = \App\Models\OnlineProduct::on('masterlist')
            ->where('is_converted', 1)
            ->whereNotNull('product_id')
            ->pluck('product_id', 'id');

        if ($connectedProducts->isEmpty()) {
            $this->info('No connected products found.');
            return 0;
        }

        $dispatched = 0;
        $skipped = 0;
        $batch = 0;

        foreach ($connectedProducts as $onlineId => $productId) {
            $skip = false;

            $latestLedger = \App\Models\ProductLedger::on('ledger')
                ->where('product_id', $productId)
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if (!$latestLedger) {
                $skipped++;
                continue;
            }

            $onlineProduct = \App\Models\OnlineProduct::on('masterlist')->find($onlineId);
            if (!$onlineProduct) {
                $skipped++;
                continue;
            }

            $itemId = null;
            if (preg_match('/^SHOPEE-(\d+)$/i', $onlineProduct->product_code, $m)) {
                $itemId = (int) $m[1];
            }
            if (!$itemId) {
                $skipped++;
                continue;
            }

            \App\Jobs\SyncShopeeProductInventory::dispatch($productId)->afterCommit();
            $dispatched++;
            $batch++;

            if ($batch % 25 === 0) {
                sleep(1);
            }
        }

        $this->info("Inventory reconciliation complete. Dispatched: {$dispatched}, Skipped: {$skipped}");
        return 0;
    } finally {
        $lock->release();
    }
})->purpose('Reconcile Shopee inventory by dispatching sync jobs for all connected products.');

Artisan::command('products:sync-ledger-stock {--product-id= : Sync one product id only} {--product-ids= : Comma-separated product IDs to sync} {--chunk=1000 : Products per update batch}', function () {
    @set_time_limit(0);
    $startTime = microtime(true);

    $productId = $this->option('product-id');
    $productIds = $this->option('product-ids');
    $chunkSize = max(1, (int) $this->option('chunk'));

    if ($productId !== null && $productId !== '') {
        $balance = \App\Services\ProductStockSyncService::syncProduct((int) $productId);

        if ($balance === null) {
            $this->warn("No ledger balance found for product_id {$productId}. Setting on_hand to 0.");
            return 1;
        }

        $this->info("Synced product_id {$productId} to on_hand {$balance}.");
        return 0;
    }

    if ($productIds !== null && $productIds !== '') {
        $ids = array_map('intval', array_filter(explode(',', $productIds), fn ($v) => is_numeric(trim($v))));
        if (empty($ids)) {
            $this->warn('No valid product IDs provided.');
            return 1;
        }
        $this->line("Syncing " . count($ids) . " specific product IDs...");
        $synced = \App\Services\ProductStockSyncService::syncProducts($ids);
        $duration = round(microtime(true) - $startTime, 2);
        $this->info("Done. {$synced} products updated in {$duration}s.");
        return 0;
    }

    $lastReported = 0;
    $result = \App\Services\ProductStockSyncService::syncAllLedgerProducts($chunkSize, function ($processed, $total, $synced) use (&$lastReported) {
        if ($processed === $total || $processed - $lastReported >= 5000) {
            $this->line("Processed {$processed}/{$total} ledger products; updated {$synced} master products.");
            $lastReported = $processed;
        }
    });

    $duration = round(microtime(true) - $startTime, 2);
    $alreadyCorrect = max(0, $result['ledger_products'] - $result['synced_products']);
    $this->info("Ledger stock sync complete in {$duration}s.");
    $this->line("  Products checked:  {$result['ledger_products']}");
    $this->line("  Products updated:  {$result['synced_products']}");
    $this->line("  Already correct:   {$alreadyCorrect}");
    $this->line("  Missing products:  {$result['missing_products']}");

    return 0;
})->purpose('Sync products.on_hand from the latest product ledger balance_stock.');

Artisan::command('ledger:repair-sales-order-stock {--dry-run : Preview changes without modifying data} {--from= : Only process items created after this date (Y-m-d)} {--product-id= : Only process a specific product ID} {--order-id= : Only process a specific sales order ID} {--exclude-transaction= : Comma-separated transaction/item IDs to exclude}', function () {
    @set_time_limit(0);
    $dryRun = (bool) $this->option('dry-run');
    $fromDate = $this->option('from');
    $filterProductId = $this->option('product-id') ? (int) $this->option('product-id') : null;
    $filterOrderId = $this->option('order-id') ? (int) $this->option('order-id') : null;
    $excludeTransaction = $this->option('exclude-transaction');

    $this->info('========================================');
    $this->info('  SALES ORDER STOCK REPAIR');
    $this->info('========================================');
    if ($dryRun) $this->warn('  DRY RUN - no changes will be made');
    $this->line('');

    // Build query for processed sales order items
    $soItemsQuery = DB::connection('sales')->table('sales_order_items as soi')
        ->join('sales_orders as so', 'soi.sales_order_id', '=', 'so.id')
        ->where('soi.actual_qty', '>', 0)
        ->select([
            'soi.id as item_id',
            'soi.sales_order_id',
            'soi.product_id',
            'soi.actual_qty',
            'soi.quantity as ordered_qty',
            'soi.oum',
            'soi.unit_price',
            'soi.subtotal',
            'so.customer_id',
            'so.customer_name',
            'so.order_number',
            'so.invoice_numbers',
            'soi.created_at',
        ])
        ->orderBy('soi.id');

    if ($filterProductId) $soItemsQuery->where('soi.product_id', $filterProductId);
    if ($filterOrderId) $soItemsQuery->where('soi.sales_order_id', $filterOrderId);
    if ($fromDate) $soItemsQuery->where('soi.created_at', '>=', $fromDate . ' 00:00:00');
    if ($excludeTransaction) {
        $excludeIds = array_map('intval', array_filter(explode(',', $excludeTransaction), fn ($v) => is_numeric(trim($v))));
        if (!empty($excludeIds)) $soItemsQuery->whereNotIn('soi.id', $excludeIds);
    }

    // Use chunking to avoid memory blowup
    $processedCount = 0;
    $missingCount = 0;
    $correctCount = 0;
    $insertedCount = 0;
    $incorrectOutCount = 0;
    $unresolvedCount = 0;
    $affectedProducts = [];
    $unresolvedDetails = [];

    $this->info('Scanning sales order items for missing ledger rows...');

    $soItemsQuery->chunk(500, function ($items) use ($dryRun, &$processedCount, &$missingCount, &$correctCount, &$insertedCount, &$incorrectOutCount, &$unresolvedCount, &$affectedProducts, &$unresolvedDetails) {
        $itemIds = $items->pluck('item_id')->toArray();

        // Batch-load existing ledger rows
        $ledgerRows = DB::connection('ledger')->table('product_ledgers')
            ->where('source_type', 'sales_order')
            ->whereIn('source_item_id', $itemIds)
            ->orderBy('id')
            ->get()
            ->groupBy('source_item_id');

        foreach ($items as $item) {
            $processedCount++;
            $pid = (int) $item->product_id;
            $itemId = (int) $item->item_id;
            $actualQty = (float) $item->actual_qty;

            if ($pid <= 0) continue;

            $existingLedgers = $ledgerRows->get($itemId);
            $rowCount = $existingLedgers ? count($existingLedgers) : 0;

            if ($rowCount === 0) {
                $missingCount++;
                // For proceed mode, each item is processed once per invoice
                // so expected previous_actual_qty = 0, movementOut = actual_qty
                $movementOut = $actualQty;

                if (!$dryRun) {
                    $latestLedger = DB::connection('ledger')->table('product_ledgers')
                        ->where('product_id', $pid)
                        ->orderByDesc('date')
                        ->orderByDesc('created_at')
                        ->orderByDesc('id')
                        ->first();

                    $previousBalance = $latestLedger ? (float) $latestLedger->balance_stock : 0;
                    $newBalance = $previousBalance - $movementOut;
                    $firstInvoice = explode(',', $item->invoice_numbers ?? '')[0];

                    DB::connection('ledger')->table('product_ledgers')->insert([
                        'product_id' => $pid,
                        'customer_id' => $item->customer_id,
                        'source_type' => 'sales_order',
                        'source_id' => $item->sales_order_id,
                        'source_item_id' => $itemId,
                        'processed_actual_qty' => $actualQty,
                        'idempotency_key' => SalesOrderLedgerService::buildIdempotencyKey(
                            'sales_order', (int) $item->sales_order_id, $itemId, $actualQty
                        ),
                        'transaction_type' => 'OUT',
                        'date' => now()->toDateString(),
                        'transaction_number' => $item->order_number ?? '',
                        'reference_number' => trim($firstInvoice),
                        'entity_name' => $item->customer_name ?? '',
                        'quantity_in' => 0,
                        'quantity_out' => $movementOut,
                        'balance_stock' => $newBalance,
                        'oum' => $item->oum ?? 'PCS',
                        'price' => $item->unit_price ?? 0,
                        'cost' => 0,
                        'remarks' => 'Repaired: missing Sales Order stock movement - Invoice: ' . trim($firstInvoice),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $insertedCount++;
                $affectedProducts[] = $pid;
                if ($dryRun) {
                    $this->line("  WOULD INSERT product #{$pid}, OUT={$movementOut} (SO item #{$itemId})");
                }
            } else {
                // Verify cumulative OUT matches actual_qty
                $totalOut = $existingLedgers->sum('quantity_out');
                if ((float) $totalOut === $actualQty) {
                    $correctCount++;
                } else {
                    $incorrectOutCount++;
                    $unresolvedCount++;
                    $unresolvedDetails[] = "Item #{$itemId}: product #{$pid}, total ledger OUT={$totalOut}, actual_qty={$actualQty}";
                    if ($dryRun) {
                        $this->warn("  INCORRECT product #{$pid}: ledger OUT={$totalOut}, expected={$actualQty} (SO item #{$itemId})");
                    }
                }
            }
        }
    });

    $this->line('');
    $this->line("Checked {$processedCount} processed items.");

    // Recalculate balance_stock for affected products
    $affectedProducts = array_unique($affectedProducts);
    if (!empty($affectedProducts) && !$dryRun) {
        $this->info('Recalculating balance_stock for affected products...');
        $recalcCount = 0;
        foreach ($affectedProducts as $rpId) {
            $firstRow = DB::connection('ledger')->table('product_ledgers')
                ->where('product_id', $rpId)
                ->orderBy('date')
                ->orderBy('id')
                ->first();
            if ($firstRow) {
                SalesOrderLedgerService::recalculateFrom($rpId, $firstRow->date);
                $recalcCount++;
            }
        }
        $this->info("Recalculated {$recalcCount} product ledgers.");
    }

    // Sync products.on_hand
    if (!empty($affectedProducts) && !$dryRun) {
        $this->info('Syncing products.on_hand...');
        $syncCount = 0;
        foreach ($affectedProducts as $spId) {
            try {
                ProductStockSyncService::syncProduct($spId);
                $syncCount++;
            } catch (\Throwable $e) {
                $this->warn("  Failed to sync product #{$spId}: {$e->getMessage()}");
            }
        }
        $this->info("Synced {$syncCount} products.");
    }

    // Report
    $this->line('');
    $this->info('========================================');
    $this->info('  REPAIR SUMMARY');
    $this->info('========================================');
    $this->line("  Total items checked:     {$processedCount}");
    $this->line("  Correct ledger rows:     {$correctCount}");
    $this->line("  Missing ledger rows:     {$missingCount}");
    $this->line("  Missing rows inserted:   {$insertedCount}");
    $this->line("  Incorrect OUT values:    {$incorrectOutCount}");
    $this->line("  Unresolved:              {$unresolvedCount}");
    $this->line("  Products affected:       " . count($affectedProducts));
    if (!empty($unresolvedDetails)) {
        $this->warn('Unresolved details:');
        foreach (array_slice($unresolvedDetails, 0, 50) as $detail) {
            $this->warn("  - {$detail}");
        }
        if (count($unresolvedDetails) > 50) {
            $this->warn("  ... and " . (count($unresolvedDetails) - 50) . " more unresolved items (see log for full list)");
        }
    }

    return 0;
})->purpose('Detect and repair missing or incorrect Product Ledger entries for Sales Order stock movements.');

Schedule::command('data-ups:run')
    ->dailyAt('18:00')
    ->timezone('Asia/Manila')
    ->withoutOverlapping();

Schedule::command('shopee:sync-prices')
    ->everyMinute()
    ->timezone('Asia/Manila')
    ->withoutOverlapping();

// Product stock synchronization is event-driven and runs immediately after
// Product Ledger transactions commit. The Artisan command remains available
// only for an explicit manual repair or one-time reconciliation.

Schedule::command('shopee:sync-inventory-reconciliation')
    ->cron('0 */6 * * *')
    ->timezone('Asia/Manila')
    ->withoutOverlapping();
