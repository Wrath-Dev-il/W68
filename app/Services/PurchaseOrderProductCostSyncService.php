<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PurchaseOrderProductCostSyncService
{
    private const MAX_UPDATE_ATTEMPTS = 3;

    /**
     * Re-sync every product cost associated with a saved Purchase Order.
     */
    public function syncPurchaseOrder(int $purchaseOrderId): array
    {
        $summary = $this->emptySummary();
        $summary['po_id'] = $purchaseOrderId;

        if ($purchaseOrderId <= 0) {
            return $this->withFailure(
                $summary,
                '',
                'Invalid Purchase Order ID.'
            );
        }

        try {
            $purchaseOrder = DB::connection('purchase')
                ->table('purchase_orders')
                ->where('id', $purchaseOrderId)
                ->first();

            if (!$purchaseOrder) {
                return $this->withFailure(
                    $summary,
                    '',
                    'Purchase Order was not found.'
                );
            }

            $items = DB::connection('purchase')
                ->table('purchase_order_items')
                ->where('purchase_order_id', $purchaseOrderId)
                ->orderBy('id')
                ->get();

            if ($items->isEmpty()) {
                $summary['message'] = 'Purchase Order has no items to re-sync.';
                return $summary;
            }

            $summary = $this->syncItems($items);
            $summary['po_id'] = $purchaseOrderId;

            if ($summary['success']) {
                $summary['message'] = $summary['warning_count'] > 0
                    ? 'Product costs were re-synced with warnings.'
                    : 'Product costs were re-synced successfully.';
            } else {
                $summary['message'] = 'One or more product costs could not be re-synced.';
            }

            return $summary;
        } catch (Throwable $error) {
            Log::error('Purchase Order product cost re-sync initialization failed', [
                'po_id' => $purchaseOrderId,
                'error' => $error->getMessage(),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
            ]);

            return $this->withFailure(
                $summary,
                '',
                $error->getMessage()
            );
        }
    }

    /**
     * Sync product costs for a collection of Purchase Order items.
     */
    public function syncItems(iterable $items): array
    {
        $summary = $this->emptySummary();

        foreach ($items as $item) {
            $summary['attempted_count']++;

            $itemId = (int) data_get($item, 'id', 0);
            $productId = (int) data_get($item, 'product_id', 0);
            $productCode = $this->cleanCode(data_get($item, 'product_code', ''));
            $unitPrice = data_get($item, 'unit_price');

            if ($unitPrice === null || $unitPrice === '' || !is_numeric($unitPrice)) {
                $this->addFailure(
                    $summary,
                    $productCode,
                    'Missing or invalid unit price.',
                    $itemId,
                    $productId
                );
                continue;
            }

            try {
                $product = $this->findMasterlistProduct($productId, $productCode);

                if (!$product) {
                    /*
                     * Historical Purchase Orders can contain placeholder or stale
                     * product references. Do not make the entire saved PO unusable
                     * because a product can no longer be resolved. The warning is
                     * returned to the caller so the record can still be reviewed.
                     */
                    $this->addWarning(
                        $summary,
                        $productCode,
                        'Matching masterlist product was not found; this item was skipped.',
                        $itemId,
                        $productId
                    );
                    continue;
                }

                $resolvedProductId = (int) $product->id;
                $cost = round((float) $unitPrice, 2);

                $this->updateProductCostWithRetry($resolvedProductId, $cost);

                $summary['synced_count']++;

                if ($productId > 0 && $productId !== $resolvedProductId) {
                    $this->addWarning(
                        $summary,
                        $productCode,
                        "The saved product ID {$productId} was stale; product {$resolvedProductId} was matched by code.",
                        $itemId,
                        $productId
                    );
                }
            } catch (Throwable $error) {
                Log::error('Purchase Order product cost sync failed', [
                    'item_id' => $itemId,
                    'product_id' => $productId,
                    'product_code' => $productCode,
                    'unit_price' => $unitPrice,
                    'error' => $error->getMessage(),
                    'file' => $error->getFile(),
                    'line' => $error->getLine(),
                ]);

                $this->addFailure(
                    $summary,
                    $productCode,
                    $error->getMessage(),
                    $itemId,
                    $productId
                );
            }
        }

        $summary['success'] = $summary['failed_count'] === 0;

        return $summary;
    }

    /**
     * Resolve a product by its saved ID first, then by normalized product code,
     * and finally by normalized part number.
     */
    private function findMasterlistProduct(int $productId, string $productCode): ?object
    {
        $products = DB::connection('masterlist')->table('products');

        if ($productId > 0) {
            $product = (clone $products)
                ->select(['id', 'product_code', 'part_number'])
                ->where('id', $productId)
                ->first();

            if ($product) {
                return $product;
            }
        }

        if ($productCode === '') {
            return null;
        }

        $normalizedCode = strtolower($productCode);

        $product = (clone $products)
            ->select(['id', 'product_code', 'part_number'])
            ->whereRaw('LOWER(TRIM(product_code)) = ?', [$normalizedCode])
            ->orderByRaw("CASE WHEN status = 'Active' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();

        if ($product) {
            return $product;
        }

        return (clone $products)
            ->select(['id', 'product_code', 'part_number'])
            ->whereRaw('LOWER(TRIM(part_number)) = ?', [$normalizedCode])
            ->orderByRaw("CASE WHEN status = 'Active' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();
    }

    /**
     * Update the cost with limited retries for transient MySQL lock errors.
     */
    private function updateProductCostWithRetry(int $productId, float $cost): void
    {
        $lastError = null;

        for ($attempt = 1; $attempt <= self::MAX_UPDATE_ATTEMPTS; $attempt++) {
            try {
                $query = DB::connection('masterlist')
                    ->table('products')
                    ->where('id', $productId);

                if (!$query->exists()) {
                    throw new \RuntimeException("Masterlist product {$productId} no longer exists.");
                }

                try {
                    $query->update([
                        'cost' => $cost,
                        'updated_at' => now(),
                    ]);
                } catch (Throwable $error) {
                    /* Support older product tables that do not have updated_at. */
                    if (!$this->isMissingUpdatedAtColumn($error)) {
                        throw $error;
                    }

                    DB::connection('masterlist')
                        ->table('products')
                        ->where('id', $productId)
                        ->update(['cost' => $cost]);
                }

                return;
            } catch (Throwable $error) {
                $lastError = $error;

                if (!$this->isRetryableDatabaseError($error) || $attempt >= self::MAX_UPDATE_ATTEMPTS) {
                    throw $error;
                }

                usleep(150000 * $attempt);
            }
        }

        if ($lastError) {
            throw $lastError;
        }
    }

    private function isRetryableDatabaseError(Throwable $error): bool
    {
        $message = strtolower($error->getMessage());
        $code = (string) $error->getCode();

        return in_array($code, ['1205', '1213', '40001'], true)
            || str_contains($message, 'deadlock')
            || str_contains($message, 'lock wait timeout');
    }

    private function isMissingUpdatedAtColumn(Throwable $error): bool
    {
        $message = strtolower($error->getMessage());

        return str_contains($message, 'updated_at')
            && (
                str_contains($message, 'unknown column')
                || str_contains($message, 'column not found')
            );
    }

    private function cleanCode(mixed $value): string
    {
        $value = trim((string) $value);

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }

    private function emptySummary(): array
    {
        return [
            'success' => true,
            'attempted_count' => 0,
            'synced_count' => 0,
            'skipped_count' => 0,
            'failed_count' => 0,
            'warning_count' => 0,
            'failures' => [],
            'warnings' => [],
        ];
    }

    private function withFailure(
        array $summary,
        string $productCode,
        string $message,
        int $itemId = 0,
        int $productId = 0
    ): array {
        $this->addFailure($summary, $productCode, $message, $itemId, $productId);
        $summary['success'] = false;

        return $summary;
    }

    private function addFailure(
        array &$summary,
        string $productCode,
        string $message,
        int $itemId = 0,
        int $productId = 0
    ): void {
        $summary['failed_count']++;
        $summary['failures'][] = [
            'item_id' => $itemId,
            'product_id' => $productId,
            'product_code' => $productCode,
            'message' => $message,
        ];
    }

    private function addWarning(
        array &$summary,
        string $productCode,
        string $message,
        int $itemId = 0,
        int $productId = 0
    ): void {
        $summary['skipped_count']++;
        $summary['warning_count']++;
        $summary['warnings'][] = [
            'item_id' => $itemId,
            'product_id' => $productId,
            'product_code' => $productCode,
            'message' => $message,
        ];
    }
}
