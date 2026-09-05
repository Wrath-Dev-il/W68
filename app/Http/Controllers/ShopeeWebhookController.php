<?php

namespace App\Http\Controllers;

use App\Jobs\ShopeePriceWebhookSyncJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopeeWebhookController extends Controller
{
    public function handleProductUpdate(Request $request)
    {
        $rawPayload = $request->getContent();

        if (!$this->hasValidSignature($request, $rawPayload)) {
            Log::warning('Shopee webhook rejected: invalid signature.', [
                'ip' => $request->ip(),
                'headers' => array_keys($request->headers->all()),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $payload = $request->json()->all();
        if (empty($payload)) {
            $payload = $request->all();
        }

        $itemIds = $this->extractItemIds($payload);
        if (empty($itemIds)) {
            Log::info('Shopee webhook accepted without item_id.', [
                'payload_keys' => array_keys($payload),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Webhook accepted, but no item_id was present.',
                'queued' => 0,
            ]);
        }

        foreach ($itemIds as $itemId) {
            ShopeePriceWebhookSyncJob::dispatchSync((string) $itemId, $payload);
        }

        return response()->json([
            'success' => true,
            'message' => 'Shopee webhook accepted.',
            'processed' => count($itemIds),
            'item_ids' => array_values($itemIds),
        ]);
    }

    private function hasValidSignature(Request $request, string $rawPayload): bool
    {
        $secret = (string) config('services.shopee.webhook_secret', '');
        if ($secret === '') {
            return false;
        }

        $provided = $request->header('X-Shopee-Signature')
            ?: $request->header('X-Shopee-Sign')
            ?: $request->header('X-Shopee-Hmac-Sha256')
            ?: $request->header('Authorization');

        if (!$provided) {
            return false;
        }

        $provided = trim((string) $provided);
        if (str_starts_with(strtolower($provided), 'bearer ')) {
            $provided = trim(substr($provided, 7));
        }

        $expected = hash_hmac('sha256', $rawPayload, $secret);

        return hash_equals($expected, $provided);
    }

    private function extractItemIds(array $payload): array
    {
        $candidates = [
            data_get($payload, 'item_id'),
            data_get($payload, 'product_id'),
            data_get($payload, 'data.item_id'),
            data_get($payload, 'data.product_id'),
            data_get($payload, 'data.item.item_id'),
            data_get($payload, 'data.product.item_id'),
            data_get($payload, 'response.item_id'),
        ];

        foreach ((array) data_get($payload, 'item_id_list', []) as $itemId) {
            $candidates[] = $itemId;
        }

        foreach ((array) data_get($payload, 'data.item_id_list', []) as $itemId) {
            $candidates[] = $itemId;
        }

        foreach ((array) data_get($payload, 'data.items', []) as $item) {
            if (is_array($item)) {
                $candidates[] = $item['item_id'] ?? $item['product_id'] ?? null;
            }
        }

        foreach ((array) data_get($payload, 'items', []) as $item) {
            if (is_array($item)) {
                $candidates[] = $item['item_id'] ?? $item['product_id'] ?? null;
            }
        }

        return collect($candidates)
            ->flatten()
            ->map(fn ($itemId) => trim((string) $itemId))
            ->filter(fn ($itemId) => $itemId !== '')
            ->unique()
            ->values()
            ->all();
    }
}
