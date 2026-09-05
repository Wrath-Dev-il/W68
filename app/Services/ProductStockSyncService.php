<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductStockSyncService
{
    public static function syncProduct(int $productId): ?int
    {
        if ($productId <= 0) {
            return null;
        }

        $balances = self::latestBalances([$productId]);
        $balance = (int) ($balances[$productId] ?? 0);

        $current = DB::connection('masterlist')
            ->table('products')
            ->where('id', $productId)
            ->value('on_hand');

        if ($current === null) {
            return $balance;
        }

        $current = (int) $current;
        if ($current === $balance) {
            return $balance;
        }

        DB::connection('masterlist')
            ->table('products')
            ->where('id', $productId)
            ->update([
                'on_hand' => $balance,
                'updated_at' => now(),
            ]);

        Log::info('[PRODUCT STOCK SYNC] Synced product.', [
            'product_id' => $productId,
            'previous_on_hand' => $current,
            'new_on_hand' => $balance,
        ]);

        self::clearProductMasterStatsCache();

        return $balance;
    }

    /**
     * Return the latest ledger balance for each requested product.
     *
     * IMPORTANT: this query runs entirely on the ledger connection.  The
     * masterlist database may live on another MariaDB service in production,
     * so no cross-database SQL is used here.
     *
     * @return array<int,int> product_id => balance_stock
     */
    public static function latestBalances(iterable $productIds, int $chunkSize = 1000): array
    {
        $ids = collect($productIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $balances = [];
        $chunkSize = max(1, $chunkSize);

        $ids->chunk($chunkSize)->each(function ($chunk) use (&$balances) {
            $chunkIds = $chunk->values()->all();
            $placeholders = implode(',', array_fill(0, count($chunkIds), '?'));

            // Two-stage MAX keeps the exact old ordering semantics:
            // latest date first, then highest id on that date.
            $rows = DB::connection('ledger')->select("\n                SELECT pl.product_id, pl.balance_stock\n                FROM product_ledgers pl\n                INNER JOIN (\n                    SELECT latest_date.product_id, MAX(same_date.id) AS max_id\n                    FROM (\n                        SELECT product_id, MAX(`date`) AS max_date\n                        FROM product_ledgers\n                        WHERE product_id IN ({$placeholders})\n                        GROUP BY product_id\n                    ) latest_date\n                    INNER JOIN product_ledgers same_date\n                        ON same_date.product_id = latest_date.product_id\n                       AND same_date.`date` = latest_date.max_date\n                    GROUP BY latest_date.product_id\n                ) latest ON latest.max_id = pl.id\n            ", $chunkIds);

            foreach ($rows as $row) {
                $balances[(int) $row->product_id] = (int) ($row->balance_stock ?? 0);
            }
        });

        return $balances;
    }

    public static function syncProducts(iterable $productIds, int $chunkSize = 1000): int
    {
        $synced = 0;
        $chunkSize = max(1, $chunkSize);

        collect($productIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->chunk($chunkSize)
            ->each(function ($chunk) use (&$synced) {
                $ids = $chunk->values()->all();
                if ($ids === []) {
                    return;
                }

                $balances = self::latestBalances($ids, count($ids));
                $current = DB::connection('masterlist')
                    ->table('products')
                    ->whereIn('id', $ids)
                    ->pluck('on_hand', 'id');

                $changes = [];
                foreach ($current as $id => $onHand) {
                    $id = (int) $id;
                    $target = (int) ($balances[$id] ?? 0);
                    if ((int) $onHand !== $target) {
                        $changes[$id] = $target;
                    }
                }

                if ($changes === []) {
                    return;
                }

                // IDs and stock values are explicitly cast to integers, so the
                // CASE expression is safe and updates the whole chunk in one
                // masterlist query without reaching into the ledger database.
                $case = 'CASE `id` ';
                foreach ($changes as $id => $balance) {
                    $case .= 'WHEN ' . (int) $id . ' THEN ' . (int) $balance . ' ';
                }
                $case .= 'END';

                $idList = implode(',', array_map('intval', array_keys($changes)));
                $updated = DB::connection('masterlist')->update("\n                    UPDATE `products`\n                    SET `on_hand` = {$case}, `updated_at` = NOW()\n                    WHERE `id` IN ({$idList})\n                ");

                $synced += (int) $updated;
            });

        self::clearProductMasterStatsCache();

        return $synced;
    }

    public static function syncAllLedgerProducts(int $chunkSize = 1000, ?callable $progress = null): array
    {
        $startTime = microtime(true);

        $productIds = DB::connection('ledger')
            ->table('product_ledgers')
            ->whereNotNull('product_id')
            ->distinct()
            ->orderBy('product_id')
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        $totalLedgerProducts = $productIds->count();
        $synced = 0;
        $processed = 0;

        $productIds->chunk(max(1, $chunkSize))->each(function ($chunk) use (&$synced, &$processed, $totalLedgerProducts, $progress, $chunkSize) {
            $synced += self::syncProducts($chunk, $chunkSize);
            $processed += $chunk->count();

            if ($progress) {
                $progress($processed, $totalLedgerProducts, $synced);
            }
        });

        self::clearProductMasterStatsCache();

        $duration = round(microtime(true) - $startTime, 2);

        Log::info('[PRODUCT STOCK SYNC] syncAll completed.', [
            'ledger_products' => $totalLedgerProducts,
            'updated_products' => $synced,
            'duration_sec' => $duration,
        ]);

        return [
            'ledger_products' => $totalLedgerProducts,
            'synced_products' => $synced,
            'missing_products' => max(0, $totalLedgerProducts - $synced),
            'duration_sec' => $duration,
        ];
    }

    public static function syncAll(int $chunkSize = 1000, ?callable $progress = null): array
    {
        return self::syncAllLedgerProducts($chunkSize, $progress);
    }

    public static function clearProductMasterStatsCache(): void
    {
        Cache::forget('admin_product_master_stats_v4');
        Cache::forget('admin_product_master_stats_v3');
    }
}
