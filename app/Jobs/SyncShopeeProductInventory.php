<?php

namespace App\Jobs;

use App\Models\OnlineProduct;
use App\Models\ProductLedger;
use App\Services\ShopeeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncShopeeProductInventory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 3;
    public int $productId;

    private int $maxConvergenceIterations = 3;

    public function __construct(int $productId)
    {
        $this->productId = $productId;
        $this->onQueue('shopee-stock');
    }

    public function middleware(): array
    {
        return [
            new WithoutOverlapping('shopee-stock-' . $this->productId, 3, 180),
        ];
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(ShopeeService $shopee): void
    {
        Log::info('[SHOPEE STOCK SYNC] Job started', ['product_id' => $this->productId]);

        if (!config('services.shopee.auto_stock_sync', false)) {
            Log::info('[SHOPEE STOCK SYNC] Job skipped because automatic stock sync is disabled.', ['product_id' => $this->productId]);
            return;
        }

        $connection = OnlineProduct::on('masterlist')
            ->where('product_id', $this->productId)
            ->where('is_converted', 1)
            ->first();

        if (!$connection) {
            Log::info('[SHOPEE STOCK SYNC] No connected online product found', ['product_id' => $this->productId]);
            return;
        }

        $itemId = null;
        if (preg_match('/^SHOPEE-(\d+)$/i', $connection->product_code, $m)) {
            $itemId = (int) $m[1];
        }

        if (!$itemId) {
            Log::warning('[SHOPEE STOCK SYNC] Missing Shopee item ID — invalid product_code format', [
                'product_id' => $this->productId,
                'product_code' => $connection->product_code,
            ]);
            return;
        }

        Log::info('[SHOPEE STOCK SYNC] Connection found', [
            'product_id' => $this->productId,
            'item_id' => $itemId,
            'is_converted' => $connection->is_converted,
        ]);

        $itemInfo = $shopee->getRawItemBaseInfo($itemId);
        if (!$itemInfo['success']) {
            Log::error('[SHOPEE STOCK SYNC] Failed to fetch Shopee item info', [
                'product_id' => $this->productId,
                'item_id' => $itemId,
                'error' => $itemInfo['error'],
            ]);
            $this->fail(new \Exception('Shopee item info fetch failed: ' . $itemInfo['error']));
            return;
        }

        $itemData = $itemInfo['data'];
        $hasModels = !empty($itemData['has_model']) || !empty($itemData['tier_variation']);
        $modelId = null;

        if ($hasModels) {
            $modelId = $this->resolveModelId($connection, $itemData);
            if ($modelId === null) {
                Log::warning('[SHOPEE STOCK SYNC] Variation product skipped — no model_id mapping', [
                    'product_id' => $this->productId,
                    'item_id' => $itemId,
                ]);
                $this->fail(new \Exception('Variation product has no model_id stored in connection. Variation sync requires migration.'));
                return;
            }
        }

        $iteration = 0;
        do {
            $latestLedger = ProductLedger::on('ledger')
                ->where('product_id', $this->productId)
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if (!$latestLedger) {
                Log::warning('[SHOPEE STOCK SYNC] No Product Ledger row found', ['product_id' => $this->productId]);
                $this->fail(new \Exception('No Product Ledger record found for product_id=' . $this->productId));
                return;
            }

            $stock = max(0, (int) $latestLedger->balance_stock);

            Log::info('[SHOPEE STOCK SYNC] Latest ledger', [
                'product_id' => $this->productId,
                'ledger_id' => $latestLedger->id,
                'ledger_date' => $latestLedger->date,
                'balance_stock' => $latestLedger->balance_stock,
                'normalized_stock' => $stock,
                'iteration' => $iteration + 1,
            ]);

            Log::info('[SHOPEE STOCK SYNC] Calling Shopee updateItemStock', [
                'product_id' => $this->productId,
                'item_id' => $itemId,
                'model_id' => $modelId ?? 0,
                'stock' => $stock,
            ]);

            $result = $shopee->updateItemStock($itemId, $stock, $modelId);

            Log::info('[SHOPEE STOCK SYNC] Shopee response', [
                'product_id' => $this->productId,
                'item_id' => $itemId,
                'success' => $result['success'],
                'error' => $result['error'] ?? null,
                'response' => $result['response'] ?? null,
            ]);

            if (!$result['success']) {
                Log::error('[SHOPEE STOCK SYNC] Stock update failed', [
                    'product_id' => $this->productId,
                    'item_id' => $itemId,
                    'model_id' => $modelId,
                    'stock' => $stock,
                    'error' => $result['error'],
                ]);
                $this->fail(new \Exception('Shopee stock update failed: ' . $result['error']));
                return;
            }

            $recheckLedger = ProductLedger::on('ledger')
                ->where('product_id', $this->productId)
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            $sentStock = $stock;
            $latestStock = $recheckLedger ? (int) $recheckLedger->balance_stock : $sentStock;
            $converged = ($latestStock === $sentStock);
            $iteration++;

            Log::info('[SHOPEE STOCK SYNC] Convergence check', [
                'product_id' => $this->productId,
                'iteration' => $iteration,
                'stock_sent' => $sentStock,
                'stock_after_request' => $latestStock,
                'converged' => $converged,
            ]);

            if (!$converged && $iteration >= $this->maxConvergenceIterations) {
                self::dispatch($this->productId)
                    ->onQueue('shopee-stock')
                    ->delay(now()->addSeconds(2));

                Log::info('[SHOPEE STOCK SYNC] Product changed during maximum convergence iterations; follow-up job dispatched.', [
                    'product_id' => $this->productId,
                    'item_id' => $itemId,
                    'stock_sent' => $sentStock,
                    'latest_stock' => $latestStock,
                    'iterations' => $iteration,
                ]);
                return;
            }
        } while (!$converged);

        Log::info('[SHOPEE STOCK SYNC] Stock update completed and converged', [
            'product_id' => $this->productId,
            'item_id' => $itemId,
            'model_id' => $modelId,
            'stock' => $stock,
            'ledger_id' => $latestLedger->id,
            'iterations' => $iteration,
        ]);
    }

    private function resolveModelId(OnlineProduct $connection, array $itemData): ?int
    {
        if (!empty($connection->shopee_model_id)) {
            return (int) $connection->shopee_model_id;
        }

        return null;
    }
}
