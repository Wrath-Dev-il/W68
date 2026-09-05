<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalesOrderLedgerService
{
    const SOURCE_TYPE = 'sales_order';

    /**
     * Record an OUT movement for a Sales Order item.
     *
     * @param array $params
     *   - product_id        int
     *   - customer_id       int|null
     *   - customer_name     string
     *   - transaction_number string (sales_number)
     *   - reference_number  string (invoice number)
     *   - source_id         int (sales_orders.id)
     *   - source_item_id    int (sales_order_items.id)
     *   - previous_actual_qty float (from persisted sales_order_item before update)
     *   - new_actual_qty    float
     *   - oum               string
     *   - unit_price        float
     *   - cost              float
     *   - remarks_prefix    string (optional prefix for remarks)
     *
     * @return array{created: bool, movement_out: float, balance_stock: float, ledger_id: int|null}
     */
    public static function recordOutMovement(array $params): array
    {
        $productId = (int) ($params['product_id'] ?? 0);
        if ($productId <= 0) {
            throw new \InvalidArgumentException('Valid product_id is required.');
        }

        $customerId = $params['customer_id'] ?? null;
        $customerName = $params['customer_name'] ?? '';
        $transactionNumber = $params['transaction_number'] ?? '';
        $referenceNumber = $params['reference_number'] ?? '';
        $sourceType = $params['source_type'] ?? static::SOURCE_TYPE;
        $sourceId = (int) ($params['source_id'] ?? 0);
        $sourceItemId = (int) ($params['source_item_id'] ?? 0);
        $previousActualQty = (float) ($params['previous_actual_qty'] ?? 0);
        $newActualQty = (float) ($params['new_actual_qty'] ?? 0);
        $oum = $params['oum'] ?? 'PCS';
        $unitPrice = (float) ($params['unit_price'] ?? 0);
        $cost = (float) ($params['cost'] ?? 0);
        $remarksPrefix = $params['remarks_prefix'] ?? 'Sales Order Processing';

        $movementOut = $newActualQty - $previousActualQty;

        if ($movementOut < 0) {
            throw new \RuntimeException(
                "SalesOrderLedgerService: negative movement ($movementOut) for product $productId. "
                . "Use the correction workflow for decreases. "
                . "previous_actual_qty=$previousActualQty, new_actual_qty=$newActualQty"
            );
        }

        if ($movementOut == 0) {
            return [
                'created' => false,
                'movement_out' => 0,
                'balance_stock' => 0,
                'ledger_id' => null,
            ];
        }

        $idempotencyKey = static::buildIdempotencyKey(
            $sourceType,
            $sourceId,
            $sourceItemId,
            $newActualQty
        );

        // Advisory lock for product-level serialization
        $lockName = 'product-ledger-' . $productId;
        $lockTimeout = 10;

        try {
            $locked = DB::connection('ledger')->selectOne(
                'SELECT GET_LOCK(?, ?) AS locked',
                [$lockName, $lockTimeout]
            );

            if (!($locked->locked ?? false)) {
                throw new \RuntimeException(
                    "Could not acquire advisory lock '$lockName' for product $productId."
                );
            }

            // Idempotency check: if this exact movement already exists, skip
            if ($idempotencyKey) {
                $existing = DB::connection('ledger')
                    ->table('product_ledgers')
                    ->where('idempotency_key', $idempotencyKey)
                    ->exists();

                if ($existing) {
                    Log::info('[SALES ORDER LEDGER] Duplicate skipped by idempotency key', [
                        'idempotency_key' => $idempotencyKey,
                        'product_id' => $productId,
                    ]);

                    $existingRow = DB::connection('ledger')
                        ->table('product_ledgers')
                        ->where('idempotency_key', $idempotencyKey)
                        ->first();

                    return [
                        'created' => false,
                        'movement_out' => (float) ($existingRow->quantity_out ?? 0),
                        'balance_stock' => (float) ($existingRow->balance_stock ?? 0),
                        'ledger_id' => (int) ($existingRow->id ?? 0),
                        'duplicate' => true,
                    ];
                }
            }

            // Get latest ledger balance for this product (locked scope)
            $latestLedger = DB::connection('ledger')
                ->table('product_ledgers')
                ->where('product_id', $productId)
                ->orderByDesc('date')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $previousBalance = $latestLedger ? (float) $latestLedger->balance_stock : 0;

            $newBalance = $previousBalance - $movementOut;

            // Validate: do not allow negative stock if existing business rules forbid it
            // The caller should validate before calling this method

            $ledgerId = DB::connection('ledger')
                ->table('product_ledgers')
                ->insertGetId([
                    'product_id' => $productId,
                    'customer_id' => $customerId,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId ?: null,
                    'source_item_id' => $sourceItemId ?: null,
                    'processed_actual_qty' => $newActualQty,
                    'idempotency_key' => $idempotencyKey ?: null,
                    'transaction_type' => 'OUT',
                    'date' => now()->toDateString(),
                    'transaction_number' => $transactionNumber,
                    'reference_number' => $referenceNumber,
                    'entity_name' => $customerName,
                    'quantity_in' => 0,
                    'quantity_out' => $movementOut,
                    'balance_stock' => $newBalance,
                    'oum' => $oum,
                    'price' => $unitPrice,
                    'cost' => $cost,
                    'remarks' => $remarksPrefix . ' - Invoice: ' . $referenceNumber,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            Log::info('[SALES ORDER LEDGER] OUT movement recorded', [
                'product_id' => $productId,
                'movement_out' => $movementOut,
                'previous_balance' => $previousBalance,
                'new_balance' => $newBalance,
                'ledger_id' => $ledgerId,
                'idempotency_key' => $idempotencyKey,
            ]);

            // Backdated recalculation: if this entry's date is before the latest entry's date,
            // recalculate all subsequent rows
            static::recalculateIfBackdated($productId, now()->toDateString());

            return [
                'created' => true,
                'movement_out' => $movementOut,
                'balance_stock' => $newBalance,
                'ledger_id' => $ledgerId,
            ];
        } finally {
            // Always release the lock
            DB::connection('ledger')->selectOne(
                'SELECT RELEASE_LOCK(?) AS released',
                [$lockName]
            );
        }
    }

    /**
     * Build a deterministic idempotency key for a Sales Order movement.
     */
    public static function buildIdempotencyKey(
        string $sourceType,
        int $sourceId,
        int $sourceItemId,
        float $processedActualQty
    ): string {
        return sprintf(
            '%s:%d:item:%d:actual:%s',
            $sourceType,
            $sourceId,
            $sourceItemId,
            rtrim(rtrim(number_format($processedActualQty, 4, '.', ''), '0'), '.')
        );
    }

    /**
     * Recalculate all ledger rows for a product from a given date onward.
     */
    public static function recalculateFrom(int $productId, string $fromDate): void
    {
        $rows = DB::connection('ledger')
            ->table('product_ledgers')
            ->where('product_id', $productId)
            ->where('date', '>=', $fromDate)
            ->orderBy('date')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        // Get balance from the row just before the recalculation range
        $prevRow = DB::connection('ledger')
            ->table('product_ledgers')
            ->where('product_id', $productId)
            ->where('date', '<', $fromDate)
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        $runningBalance = $prevRow ? (float) $prevRow->balance_stock : 0;

        $updatedCount = 0;
        foreach ($rows as $row) {
            $runningBalance = $runningBalance + (float) $row->quantity_in - (float) $row->quantity_out;

            if (((float) $row->balance_stock) !== $runningBalance) {
                DB::connection('ledger')
                    ->table('product_ledgers')
                    ->where('id', $row->id)
                    ->update([
                        'balance_stock' => $runningBalance,
                        'updated_at' => now(),
                    ]);
                $updatedCount++;
            }
        }

        if ($updatedCount > 0) {
            Log::info('[SALES ORDER LEDGER] Backdated recalculation', [
                'product_id' => $productId,
                'from_date' => $fromDate,
                'rows_updated' => $updatedCount,
            ]);
        }
    }

    /**
     * Check if the newly inserted date requires backdated recalculation,
     * and if so, recalculate all later rows.
     */
    public static function recalculateIfBackdated(int $productId, string $insertedDate): void
    {
        $latestRow = DB::connection('ledger')
            ->table('product_ledgers')
            ->where('product_id', $productId)
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if (!$latestRow) {
            return;
        }

        // If the inserted row's date is before the latest row's date, recalculate
        if ($insertedDate < $latestRow->date) {
            static::recalculateFrom($productId, $insertedDate);
        }
    }

    /**
     * Record an IN movement for stock returns/corrections.
     *
     * @param array $params
     *   - product_id          int
     *   - customer_id         int|null
     *   - customer_name       string
     *   - transaction_number  string
     *   - reference_number    string
     *   - source_id           int|null
     *   - source_item_id      int|null
     *   - quantity_in         float (the amount being returned)
     *   - oum                 string
     *   - unit_price          float
     *   - cost                float
     *   - remarks             string
     *
     * @return array{created: bool, quantity_in: float, balance_stock: float, ledger_id: int|null}
     */
    public static function recordInMovement(array $params): array
    {
        $productId = (int) ($params['product_id'] ?? 0);
        if ($productId <= 0) {
            throw new \InvalidArgumentException('Valid product_id is required.');
        }

        $quantityIn = (float) ($params['quantity_in'] ?? 0);
        if ($quantityIn <= 0) {
            return ['created' => false, 'quantity_in' => 0, 'balance_stock' => 0, 'ledger_id' => null];
        }

        $lockName = 'product-ledger-' . $productId;
        $lockTimeout = 10;

        try {
            $locked = DB::connection('ledger')->selectOne(
                'SELECT GET_LOCK(?, ?) AS locked',
                [$lockName, $lockTimeout]
            );

            if (!($locked->locked ?? false)) {
                throw new \RuntimeException(
                    "Could not acquire advisory lock '$lockName' for product $productId."
                );
            }

            $latestLedger = DB::connection('ledger')
                ->table('product_ledgers')
                ->where('product_id', $productId)
                ->orderByDesc('date')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $previousBalance = $latestLedger ? (float) $latestLedger->balance_stock : 0;
            $newBalance = $previousBalance + $quantityIn;

            $ledgerId = DB::connection('ledger')
                ->table('product_ledgers')
                ->insertGetId([
                    'product_id' => $productId,
                    'customer_id' => $params['customer_id'] ?? null,
                    'source_type' => $params['source_type'] ?? null,
                    'source_id' => isset($params['source_id']) ? (int) $params['source_id'] : null,
                    'source_item_id' => isset($params['source_item_id']) ? (int) $params['source_item_id'] : null,
                    'idempotency_key' => $params['idempotency_key'] ?? null,
                    'transaction_type' => 'IN',
                    'date' => now()->toDateString(),
                    'transaction_number' => $params['transaction_number'] ?? '',
                    'reference_number' => $params['reference_number'] ?? '',
                    'entity_name' => $params['customer_name'] ?? '',
                    'quantity_in' => $quantityIn,
                    'quantity_out' => 0,
                    'balance_stock' => $newBalance,
                    'oum' => $params['oum'] ?? 'PCS',
                    'price' => $params['unit_price'] ?? 0,
                    'cost' => $params['cost'] ?? 0,
                    'remarks' => $params['remarks'] ?? 'Stock Return',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            Log::info('[SALES ORDER LEDGER] IN movement recorded', [
                'product_id' => $productId,
                'quantity_in' => $quantityIn,
                'previous_balance' => $previousBalance,
                'new_balance' => $newBalance,
                'ledger_id' => $ledgerId,
                'remarks' => $params['remarks'] ?? 'Stock Return',
            ]);

            static::recalculateIfBackdated($productId, now()->toDateString());

            return [
                'created' => true,
                'quantity_in' => $quantityIn,
                'balance_stock' => $newBalance,
                'ledger_id' => $ledgerId,
            ];
        } finally {
            DB::connection('ledger')->selectOne(
                'SELECT RELEASE_LOCK(?) AS released',
                [$lockName]
            );
        }
    }

    /**
     * Get the latest ledger balance for a product.
     * Returns null if no ledger entries exist.
     */
    public static function getLatestBalance(int $productId): ?float
    {
        $row = DB::connection('ledger')
            ->table('product_ledgers')
            ->where('product_id', $productId)
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        return $row ? (float) $row->balance_stock : null;
    }
}
