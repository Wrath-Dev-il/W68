<?php

namespace App\Jobs;

use App\Services\ShopeePriceSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ShopeePriceWebhookSyncJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public string $itemId,
        public array $payload = []
    ) {
        $this->onQueue('shopee');
    }

    public function handle(ShopeePriceSyncService $syncService): void
    {
        $result = $syncService->syncShopeeItemPrice($this->itemId, false, 'webhook');

        if (($result['success'] ?? false) !== true) {
            Log::warning('Shopee webhook item sync did not complete successfully.', [
                'item_id' => $this->itemId,
                'message' => $result['message'] ?? null,
                'payload_keys' => array_keys($this->payload),
            ]);
        }
    }
}
