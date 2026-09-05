<?php

namespace App\Services;

use App\Models\OnlineProduct;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ShopeePriceSyncService
{
    public const FALLBACK_INTERVAL_MINUTES = 1;

    public function syncFromShopee(int $pageSize = 50, bool $dryRun = false): array
    {
        return $this->syncLinkedShopeeItems($dryRun);
    }

    public function pushSystemPriceToShopee(Product $product, string $source = 'product_master_user_update'): array
    {
        $startedAt = now('Asia/Manila');
        $mode = $source;
        $product->refresh();

        $linkedProducts = OnlineProduct::query()
            ->where('product_id', $product->id)
            ->get();

        if ($linkedProducts->isEmpty()) {
            $result = $this->buildResult(
                true,
                'Product online price saved locally. No linked Shopee product was found.',
                $this->emptySummary([], 0),
                $startedAt,
                $mode,
                [
                    'sync_status' => 'skipped',
                    'skip_reason' => 'not_linked',
                    'source' => $source,
                    'product_id' => $product->id,
                    'system_price_online' => (float) ($product->price_online ?? 0),
                ]
            );
            $this->storeStatus($result, $mode);
            return $result;
        }

        if ($linkedProducts->count() > 1) {
            $result = $this->buildResult(
                true,
                'Product online price saved locally, but Shopee sync was skipped because multiple online products are linked to this item.',
                $this->emptySummary(['Ambiguous online product link.'], 0),
                $startedAt,
                $mode,
                [
                    'sync_status' => 'skipped',
                    'skip_reason' => 'ambiguous_link',
                    'source' => $source,
                    'product_id' => $product->id,
                    'online_product_ids' => $linkedProducts->pluck('id')->values()->all(),
                    'system_price_online' => (float) ($product->price_online ?? 0),
                ]
            );
            $this->storeStatus($result, $mode);
            return $result;
        }

        $onlineProduct = $linkedProducts->first();
        $itemId = $this->extractItemIdFromOnlineProduct($onlineProduct);
        $newPrice = round((float) ($product->price_online ?? 0), 2);

        if (!$itemId) {
            $result = $this->buildResult(
                false,
                'Product online price saved locally, but Shopee sync failed because the linked online product has no SHOPEE-{item_id} code.',
                $this->emptySummary(['Missing Shopee item_id on linked online product.'], 0),
                $startedAt,
                $mode,
                [
                    'sync_status' => 'failed',
                    'source' => $source,
                    'product_id' => $product->id,
                    'online_product_id' => $onlineProduct->id,
                    'system_price_online' => $newPrice,
                ]
            );
            $this->storeStatus($result, $mode);
            return $result;
        }

        $service = new ShopeeService();
        if (!$service->isReady()) {
            $missing = $service->getMissingCredentials();
            $result = $this->buildResult(
                false,
                'Product online price saved locally, but Shopee API is not configured. Missing: ' . implode(', ', $missing),
                $this->emptySummary(['Missing Shopee config: ' . implode(', ', $missing)], 0),
                $startedAt,
                $mode,
                [
                    'sync_status' => 'failed',
                    'source' => $source,
                    'product_id' => $product->id,
                    'online_product_id' => $onlineProduct->id,
                    'item_id' => $itemId,
                    'system_price_online' => $newPrice,
                ]
            );
            $this->storeStatus($result, $mode);
            return $result;
        }

        $baseInfo = $service->getRawItemBaseInfo($itemId);
        if (($baseInfo['success'] ?? false) !== true || empty($baseInfo['item'])) {
            $error = $baseInfo['error'] ?? 'Shopee item could not be fetched before price update.';
            $result = $this->buildResult(
                false,
                'Product online price saved locally, but Shopee item lookup failed: ' . $error,
                $this->emptySummary([$error], 0),
                $startedAt,
                $mode,
                [
                    'sync_status' => 'failed',
                    'source' => $source,
                    'product_id' => $product->id,
                    'online_product_id' => $onlineProduct->id,
                    'item_id' => $itemId,
                    'system_price_online' => $newPrice,
                ]
            );
            $this->storeStatus($result, $mode);
            return $result;
        }

        if ($this->shopeeItemHasVariations((array) $baseInfo['item'])) {
            $result = $this->buildResult(
                true,
                'Product online price saved locally, but Shopee sync was skipped because this Shopee item has variations and no model_id is stored locally.',
                $this->emptySummary(['Variation item skipped because model_id is not stored.'], 1),
                $startedAt,
                $mode,
                [
                    'sync_status' => 'skipped',
                    'skip_reason' => 'variation_without_model_id',
                    'source' => $source,
                    'product_id' => $product->id,
                    'online_product_id' => $onlineProduct->id,
                    'item_id' => $itemId,
                    'system_price_online' => $newPrice,
                    'shopee_item_name' => $baseInfo['item']['item_name'] ?? $baseInfo['item']['name'] ?? null,
                ]
            );
            $this->storeStatus($result, $mode);
            return $result;
        }

        $oldOnlinePrice = round((float) ($onlineProduct->price ?? 0), 2);
        $oldShopeePrice = $this->extractShopeeItemPrice((array) $baseInfo['item']);
        $updateResult = $service->updateItemPrice($itemId, $newPrice);

        if (($updateResult['success'] ?? false) !== true) {
            $error = $updateResult['error'] ?? 'Shopee price update failed.';
            $result = $this->buildResult(
                false,
                'Product online price saved locally, but Shopee price update failed: ' . $error,
                $this->emptySummary([$error], 1),
                $startedAt,
                $mode,
                [
                    'sync_status' => 'failed',
                    'source' => $source,
                    'product_id' => $product->id,
                    'online_product_id' => $onlineProduct->id,
                    'item_id' => $itemId,
                    'old_online_product_price' => $oldOnlinePrice,
                    'old_shopee_price' => $oldShopeePrice,
                    'new_system_price_online' => $newPrice,
                ]
            );
            $this->storeStatus($result, $mode);
            return $result;
        }

        $onlineProduct->price = $newPrice;
        $onlineProduct->save();

        $result = $this->buildResult(
            true,
            'Product online price pushed to Shopee successfully.',
            [
                'total_shopee_items_fetched' => 1,
                'matched_online_products' => 1,
                'updated_online_product_prices' => $oldOnlinePrice === $newPrice ? 0 : 1,
                'updated_linked_system_product_prices' => 0,
                'updated_shopee_item_prices' => 1,
                'skipped_unmatched_items' => 0,
                'errors' => [],
            ],
            $startedAt,
            $mode,
            [
                'sync_status' => 'success',
                'source' => $source,
                'product_id' => $product->id,
                'online_product_id' => $onlineProduct->id,
                'item_id' => $itemId,
                'old_online_product_price' => $oldOnlinePrice,
                'old_shopee_price' => $oldShopeePrice,
                'new_system_price_online' => $newPrice,
                'new_online_product_price' => $newPrice,
                'new_shopee_price' => $newPrice,
            ]
        );
        $this->storeStatus($result, $mode);

        return $result;
    }

    public function syncFullCatalogFromShopee(int $pageSize = 50, bool $dryRun = false): array
    {
        $service = new ShopeeService();
        $startedAt = now('Asia/Manila');

        if (!$service->isReady()) {
            $missing = $service->getMissingCredentials();
            $result = [
                'success' => false,
                'message' => 'Shopee API is not configured. Missing: ' . implode(', ', $missing),
                'summary' => $this->emptySummary(['Missing Shopee config: ' . implode(', ', $missing)]),
                'matching_fields' => $this->matchingFields(),
                'started_at' => $startedAt->toDateTimeString(),
                'finished_at' => now('Asia/Manila')->toDateTimeString(),
                'timezone' => 'Asia/Manila',
                'mode' => 'full',
                'dry_run' => $dryRun,
            ];
            $this->storeStatus($result, 'fallback');
            return $result;
        }

        $fetched = $service->getAllProducts($pageSize);
        if (($fetched['success'] ?? false) !== true) {
            $result = [
                'success' => false,
                'message' => $fetched['error'] ?? 'Unable to fetch Shopee products.',
                'summary' => $this->emptySummary([$fetched['error'] ?? 'Unable to fetch Shopee products.'], count($fetched['products'] ?? [])),
                'matching_fields' => $this->matchingFields(),
                'started_at' => $startedAt->toDateTimeString(),
                'finished_at' => now('Asia/Manila')->toDateTimeString(),
                'timezone' => 'Asia/Manila',
                'mode' => 'full',
                'pages_fetched' => $fetched['pages_fetched'] ?? null,
                'truncated' => (bool) ($fetched['truncated'] ?? false),
                'dry_run' => $dryRun,
            ];
            $this->storeStatus($result, 'fallback');
            return $result;
        }

        $summary = $this->syncProducts($fetched['products'] ?? [], $dryRun);
        $result = [
            'success' => true,
            'message' => $dryRun
                ? 'Shopee price sync dry run completed.'
                : 'Shopee prices synced into online products and linked system products.',
            'summary' => $summary,
            'matching_fields' => $this->matchingFields(),
            'online_price_field' => 'online_products.price',
            'system_price_field' => 'products.price_online',
            'mapping_table' => 'online_products.product_id',
            'started_at' => $startedAt->toDateTimeString(),
            'finished_at' => now('Asia/Manila')->toDateTimeString(),
            'timezone' => 'Asia/Manila',
            'mode' => 'full',
            'pages_fetched' => $fetched['pages_fetched'] ?? null,
            'truncated' => (bool) ($fetched['truncated'] ?? false),
            'dry_run' => $dryRun,
        ];
        $this->storeStatus($result, 'fallback');
        return $result;
    }

    public function syncLinkedShopeeItems(bool $dryRun = false): array
    {
        $service = new ShopeeService();
        $startedAt = now('Asia/Manila');
        $linkedProducts = OnlineProduct::query()
            ->whereNotNull('product_id')
            ->select(['id', 'product_id', 'product_code', 'name', 'sku', 'price'])
            ->get();

        $itemIds = $linkedProducts
            ->map(fn ($product) => $this->extractItemIdFromOnlineProduct($product))
            ->filter()
            ->unique()
            ->values();

        if (!$service->isReady()) {
            $missing = $service->getMissingCredentials();
            $result = $this->buildResult(
                false,
                'Shopee API is not configured. Missing: ' . implode(', ', $missing),
                $this->emptySummary(['Missing Shopee config: ' . implode(', ', $missing)], 0),
                $startedAt,
                'fallback',
                ['linked_items_checked' => $itemIds->count(), 'dry_run' => $dryRun]
            );
            $this->storeStatus($result, 'fallback');
            return $result;
        }

        if ($itemIds->isEmpty()) {
            $result = $this->buildResult(
                true,
                'No linked Shopee items found for fallback sync.',
                $this->emptySummary([], 0),
                $startedAt,
                'fallback',
                ['linked_items_checked' => 0, 'dry_run' => $dryRun]
            );
            $this->storeStatus($result, 'fallback');
            return $result;
        }

        $fetched = $service->getProductsByItemIds($itemIds->all());
        if (($fetched['success'] ?? false) !== true) {
            $result = $this->buildResult(
                false,
                $fetched['error'] ?? 'Unable to fetch linked Shopee items.',
                $this->emptySummary([$fetched['error'] ?? 'Unable to fetch linked Shopee items.'], 0),
                $startedAt,
                'fallback',
                ['linked_items_checked' => $itemIds->count(), 'dry_run' => $dryRun]
            );
            $this->storeStatus($result, 'fallback');
            return $result;
        }

        $summary = $this->syncProducts($fetched['products'] ?? [], $dryRun, $linkedProducts);
        $result = $this->buildResult(
            true,
            $dryRun
                ? 'Linked Shopee item fallback dry run completed.'
                : 'Linked Shopee item fallback sync completed.',
            $summary,
            $startedAt,
            'fallback',
            [
                'linked_items_checked' => $itemIds->count(),
                'item_ids' => $itemIds->all(),
                'dry_run' => $dryRun,
            ]
        );

        $this->storeStatus($result, 'fallback');
        return $result;
    }

    public function syncShopeeItemPrice($itemId, bool $dryRun = false, string $mode = 'webhook'): array
    {
        $service = new ShopeeService();
        $startedAt = now('Asia/Manila');
        $cleanItemId = trim((string) $itemId);

        if ($cleanItemId === '') {
            $result = $this->buildResult(
                false,
                'Shopee webhook did not include an item_id.',
                $this->emptySummary(['Missing item_id.'], 0),
                $startedAt,
                $mode,
                ['item_id' => null, 'dry_run' => $dryRun]
            );
            $this->storeStatus($result, $mode);
            return $result;
        }

        if (!$service->isReady()) {
            $missing = $service->getMissingCredentials();
            $result = $this->buildResult(
                false,
                'Shopee API is not configured. Missing: ' . implode(', ', $missing),
                $this->emptySummary(['Missing Shopee config: ' . implode(', ', $missing)], 0),
                $startedAt,
                $mode,
                ['item_id' => $cleanItemId, 'dry_run' => $dryRun]
            );
            $this->storeStatus($result, $mode);
            return $result;
        }

        $fetched = $service->getProductByItemId($cleanItemId);
        if (($fetched['success'] ?? false) !== true || empty($fetched['product'])) {
            $error = $fetched['error'] ?? 'Shopee item was not found.';
            $result = $this->buildResult(
                false,
                $error,
                $this->emptySummary([$error], 0),
                $startedAt,
                $mode,
                ['item_id' => $cleanItemId, 'dry_run' => $dryRun]
            );
            $this->storeStatus($result, $mode);
            return $result;
        }

        $summary = $this->syncProducts([$fetched['product']], $dryRun);
        $result = $this->buildResult(
            true,
            $dryRun ? 'Shopee item price webhook dry run completed.' : 'Shopee item price synced.',
            $summary,
            $startedAt,
            $mode,
            [
                'item_id' => $cleanItemId,
                'item_name' => $fetched['product']['name'] ?? null,
                'item_price' => $fetched['product']['price'] ?? null,
                'dry_run' => $dryRun,
            ]
        );

        $this->storeStatus($result, $mode);
        Log::info('Shopee item price sync finished', [
            'mode' => $mode,
            'item_id' => $cleanItemId,
            'summary' => $summary,
        ]);

        return $result;
    }

    public function syncProducts(iterable $shopeeProducts, bool $dryRun = false, ?Collection $onlineProducts = null): array
    {
        $normalizedProducts = collect($shopeeProducts)
            ->map(fn ($product) => $this->normalizeShopeeProduct((array) $product))
            ->filter(fn ($product) => !empty($product['id']) || !empty($product['product_code']) || !empty($product['sku']) || !empty($product['name']))
            ->values();

        $summary = $this->emptySummary([], $normalizedProducts->count());

        DB::connection('masterlist')->transaction(function () use ($normalizedProducts, &$summary, $dryRun, $onlineProducts) {
            $onlineProducts = $onlineProducts ?: OnlineProduct::query()
                ->select(['id', 'product_id', 'product_code', 'name', 'sku', 'price'])
                ->lockForUpdate()
                ->get();

            $indexes = $this->buildIndexes($onlineProducts);
            $usedOnlineProductIds = [];

            foreach ($normalizedProducts as $shopeeProduct) {
                $price = $shopeeProduct['price'] ?? null;
                if (!is_numeric($price)) {
                    $summary['errors'][] = 'Skipped Shopee item without numeric price: ' . ($shopeeProduct['id'] ?? $shopeeProduct['name'] ?? 'unknown');
                    $summary['skipped_unmatched_items']++;
                    continue;
                }

                $onlineProduct = $this->findOnlineProductForShopeeItem($shopeeProduct, $indexes, $usedOnlineProductIds);
                if (!$onlineProduct) {
                    $summary['skipped_unmatched_items']++;
                    continue;
                }

                $usedOnlineProductIds[$onlineProduct->id] = true;
                $summary['matched_online_products']++;
                $newPrice = round((float) $price, 2);
                $oldOnlinePrice = round((float) $onlineProduct->price, 2);

                if ($oldOnlinePrice !== $newPrice) {
                    if (!$dryRun) {
                        $onlineProduct->price = $newPrice;
                        $onlineProduct->save();
                    }
                    $summary['updated_online_product_prices']++;
                }

                if (!empty($onlineProduct->product_id)) {
                    $oldSystemPrice = Product::whereKey((int) $onlineProduct->product_id)->value('price_online');
                    if (round((float) $oldSystemPrice, 2) !== $newPrice) {
                        if (!$dryRun) {
                            Product::whereKey((int) $onlineProduct->product_id)->update(['price_online' => $newPrice]);
                        }
                        $summary['updated_linked_system_product_prices']++;
                    }
                }
            }
        });

        return $summary;
    }

    public function latestStatus(): array
    {
        $path = $this->statusPath();
        if (!File::exists($path)) {
            return [
                'success' => null,
                'message' => 'Shopee price sync has not run yet.',
                'summary' => $this->emptySummary(),
                'matching_fields' => $this->matchingFields(),
                'finished_at' => null,
                'timezone' => 'Asia/Manila',
            ];
        }

        $payload = json_decode((string) File::get($path), true);
        if (is_array($payload)) {
            if (isset($payload['latest']) && is_array($payload['latest'])) {
                return array_merge($payload['latest'], [
                    'latest' => $payload['latest'],
                    'webhook' => $payload['webhook'] ?? null,
                    'fallback' => $payload['fallback'] ?? null,
                ]);
            }

            return $payload;
        }

        return [
            'success' => false,
            'message' => 'Shopee sync status file could not be read.',
            'summary' => $this->emptySummary(['Invalid status file.']),
            'matching_fields' => $this->matchingFields(),
            'finished_at' => null,
            'timezone' => 'Asia/Manila',
        ];
    }

    public function latestWebhookStatus(): ?array
    {
        return $this->latestStatus()['webhook'] ?? null;
    }

    public function latestFallbackStatus(): ?array
    {
        return $this->latestStatus()['fallback'] ?? null;
    }

    public function isStatusOlderThanMinutes(int $minutes, string $mode = 'fallback'): bool
    {
        $status = $mode === 'webhook' ? $this->latestWebhookStatus() : $this->latestFallbackStatus();
        $finishedAt = $status['finished_at'] ?? ($mode === 'fallback' ? ($this->latestStatus()['finished_at'] ?? null) : null);
        if (!$finishedAt) {
            return true;
        }

        return \Carbon\Carbon::parse($finishedAt, 'Asia/Manila')
            ->addMinutes($minutes)
            ->lte(now('Asia/Manila'));
    }

    public function storeStatus(array $status, string $mode = 'fallback'): void
    {
        $path = $this->statusPath();
        File::ensureDirectoryExists(dirname($path));
        $existing = [];
        if (File::exists($path)) {
            $decoded = json_decode((string) File::get($path), true);
            if (is_array($decoded)) {
                $existing = isset($decoded['latest']) ? $decoded : ['latest' => $decoded];
            }
        }

        $existing['latest'] = $status;
        $existing[$mode] = $status;
        File::put($path, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function matchingFields(): array
    {
        return [
            'item_id' => 'online_products.product_code with SHOPEE-{item_id}',
            'sku_product_code' => 'online_products.sku / online_products.product_code',
            'name_fallback' => 'exact online_products.name',
        ];
    }

    private function emptySummary(array $errors = [], int $fetched = 0): array
    {
        return [
            'total_shopee_items_fetched' => $fetched,
            'matched_online_products' => 0,
            'updated_online_product_prices' => 0,
            'updated_linked_system_product_prices' => 0,
            'skipped_unmatched_items' => 0,
            'errors' => $errors,
        ];
    }

    private function buildResult(bool $success, string $message, array $summary, $startedAt, string $mode, array $extra = []): array
    {
        return array_merge([
            'success' => $success,
            'message' => $message,
            'summary' => $summary,
            'matching_fields' => $this->matchingFields(),
            'online_price_field' => 'online_products.price',
            'system_price_field' => 'products.price_online',
            'mapping_table' => 'online_products.product_id',
            'started_at' => $startedAt->toDateTimeString(),
            'finished_at' => now('Asia/Manila')->toDateTimeString(),
            'timezone' => 'Asia/Manila',
            'mode' => $mode,
        ], $extra);
    }

    private function extractItemIdFromOnlineProduct(OnlineProduct $onlineProduct): ?string
    {
        foreach ([$onlineProduct->product_code, $onlineProduct->sku] as $value) {
            $value = trim((string) $value);
            if (preg_match('/^SHOPEE-(\d+)$/i', $value, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    private function shopeeItemHasVariations(array $item): bool
    {
        if (($item['has_model'] ?? false) === true) {
            return true;
        }

        $tierVariations = $item['tier_variation'] ?? $item['tier_variation_list'] ?? [];
        if (is_array($tierVariations) && count($tierVariations) > 0) {
            return true;
        }

        foreach ((array) ($item['price_info'] ?? []) as $priceInfo) {
            if (is_array($priceInfo) && !empty($priceInfo['model_id'])) {
                return true;
            }
        }

        return false;
    }

    private function extractShopeeItemPrice(array $item): ?float
    {
        $priceInfo = $item['price_info'][0] ?? null;
        if (!is_array($priceInfo)) {
            return null;
        }

        $price = $priceInfo['current_price'] ?? $priceInfo['original_price'] ?? null;
        return is_numeric($price) ? round((float) $price, 2) : null;
    }

    private function normalizeShopeeProduct(array $product): array
    {
        $itemId = $product['id'] ?? $product['item_id'] ?? null;
        $productCode = trim((string) ($product['product_code'] ?? ''));
        if ($productCode === '' && $itemId) {
            $productCode = 'SHOPEE-' . $itemId;
        }
        if ($productCode === '') {
            $productCode = trim((string) ($product['sku'] ?? $product['item_sku'] ?? ''));
        }

        return array_merge($product, [
            'id' => $itemId,
            'product_code' => $productCode,
            'sku' => $product['sku'] ?? $product['item_sku'] ?? $productCode,
            'name' => $product['name'] ?? $product['item_name'] ?? $productCode,
            'price' => $product['price'] ?? 0,
        ]);
    }

    private function buildIndexes(Collection $onlineProducts): array
    {
        $indexes = [
            'product_code' => [],
            'sku' => [],
            'name' => [],
        ];

        foreach ($onlineProducts as $onlineProduct) {
            $productCodeKey = $this->syncKey($onlineProduct->product_code);
            if ($productCodeKey !== '' && !isset($indexes['product_code'][$productCodeKey])) {
                $indexes['product_code'][$productCodeKey] = $onlineProduct;
            }

            $skuKey = $this->syncKey($onlineProduct->sku);
            if ($skuKey !== '' && !isset($indexes['sku'][$skuKey])) {
                $indexes['sku'][$skuKey] = $onlineProduct;
            }

            $nameKey = Str::lower(trim((string) $onlineProduct->name));
            if ($nameKey !== '' && !isset($indexes['name'][$nameKey])) {
                $indexes['name'][$nameKey] = $onlineProduct;
            }
        }

        return $indexes;
    }

    private function findOnlineProductForShopeeItem(array $shopeeProduct, array $indexes, array $usedOnlineProductIds): ?OnlineProduct
    {
        $itemId = trim((string) ($shopeeProduct['id'] ?? $shopeeProduct['item_id'] ?? ''));
        $productCode = trim((string) ($shopeeProduct['product_code'] ?? ''));
        $sku = trim((string) ($shopeeProduct['sku'] ?? $shopeeProduct['item_sku'] ?? ''));
        $name = Str::lower(trim((string) ($shopeeProduct['name'] ?? $shopeeProduct['item_name'] ?? '')));

        $matchGroups = [
            'item_id' => array_filter([
                $itemId !== '' ? 'SHOPEE-' . $itemId : '',
                $itemId,
            ]),
            'code_sku' => array_filter([$productCode, $sku]),
            'name' => array_filter([$name]),
        ];

        foreach ($matchGroups['item_id'] as $key) {
            $match = $indexes['product_code'][$this->syncKey($key)] ?? null;
            if ($match && !isset($usedOnlineProductIds[$match->id])) {
                return $match;
            }
        }

        foreach ($matchGroups['code_sku'] as $key) {
            $matchKey = $this->syncKey($key);
            $match = $indexes['product_code'][$matchKey] ?? $indexes['sku'][$matchKey] ?? null;
            if ($match && !isset($usedOnlineProductIds[$match->id])) {
                return $match;
            }
        }

        foreach ($matchGroups['name'] as $key) {
            $match = $indexes['name'][$key] ?? null;
            if ($match && !isset($usedOnlineProductIds[$match->id])) {
                return $match;
            }
        }

        return null;
    }

    private function syncKey($value): string
    {
        return strtoupper(trim((string) $value));
    }

    private function statusPath(): string
    {
        return storage_path('app/shopee-price-sync-status.json');
    }
}
