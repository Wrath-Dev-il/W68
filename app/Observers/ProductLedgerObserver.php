<?php

namespace App\Observers;

use App\Jobs\SyncShopeeProductInventory;
use App\Models\ProductLedger;
use App\Services\ProductStockSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductLedgerObserver
{
    public function created(ProductLedger $ledger): void
    {
        $this->syncAfterLedgerCommit([$ledger->product_id], 'created', (int) ($ledger->id ?? 0));

        if (!config('services.shopee.auto_stock_sync', false)) {
            Log::info('[SHOPEE STOCK SYNC] Observer skipped - automatic stock sync is disabled.', [
                'product_id' => $ledger->product_id,
            ]);
            return;
        }

        SyncShopeeProductInventory::dispatch($ledger->product_id)
            ->onQueue('shopee-stock')
            ->afterCommit();
    }

    public function updated(ProductLedger $ledger): void
    {
        $this->syncAfterLedgerCommit([
            $ledger->getOriginal('product_id'),
            $ledger->product_id,
        ], 'updated', (int) ($ledger->id ?? 0));
    }

    public function deleted(ProductLedger $ledger): void
    {
        $this->syncAfterLedgerCommit([$ledger->product_id], 'deleted', (int) ($ledger->id ?? 0));
    }

    private function syncAfterLedgerCommit(array $productIds, string $event, int $ledgerId): void
    {
        $productIds = collect($productIds)
            ->map(fn ($productId) => (int) $productId)
            ->filter(fn ($productId) => $productId > 0)
            ->unique()
            ->values()
            ->all();

        if ($productIds === []) {
            return;
        }

        DB::connection('ledger')->afterCommit(function () use ($productIds, $event, $ledgerId): void {
            try {
                ProductStockSyncService::syncProducts($productIds);

                Log::info('[PRODUCT STOCK REAL-TIME SYNC] Latest ledger balance copied to products.on_hand.', [
                    'event' => $event,
                    'product_ids' => $productIds,
                    'ledger_id' => $ledgerId,
                ]);
            } catch (\Throwable $e) {
                Log::error('[PRODUCT STOCK REAL-TIME SYNC] Immediate synchronization failed.', [
                    'event' => $event,
                    'product_ids' => $productIds,
                    'ledger_id' => $ledgerId,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
