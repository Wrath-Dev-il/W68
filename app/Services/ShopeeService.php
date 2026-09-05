<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopeeService
{
    protected string $partnerId;
    protected string $partnerKey;
    protected string $accessToken;
    protected string $refreshToken;
    protected string $shopId;
    protected string $baseUrl;

    public function __construct()
    {
        $this->partnerId = config('services.shopee.partner_id');
        $this->partnerKey = config('services.shopee.partner_key');
        $this->accessToken = config('services.shopee.access_token');
        $this->refreshToken = config('services.shopee.refresh_token');
        $this->shopId = config('services.shopee.shop_id');
        $this->baseUrl = config('services.shopee.base_url', 'https://partner.shopeemobile.com');
    }

    public function isReady(): bool
    {
        return !empty($this->partnerId) && !empty($this->partnerKey) && !empty($this->accessToken) && !empty($this->shopId);
    }

    public function getMissingCredentials(): array
    {
        $missing = [];
        if (empty($this->partnerId)) $missing[] = 'partner_id';
        if (empty($this->partnerKey)) $missing[] = 'partner_key';
        if (empty($this->accessToken)) $missing[] = 'access_token';
        if (empty($this->shopId)) $missing[] = 'shop_id';
        return $missing;
    }

    private function enrichItems(array $itemIds): array
    {
        if (empty($itemIds)) return [];

        $products = [];
        $baseResult = $this->signedRequest('/api/v2/product/get_item_base_info', [
            'item_id_list' => array_slice($itemIds, 0, 50),
        ]);

        if ($baseResult['success']) {
            $detailedItems = $baseResult['data']['item_list'] ?? [];
            $idMap = [];
            foreach ($detailedItems as $d) {
                $idMap[$d['item_id']] = $d;
            }

            foreach ($itemIds as $id) {
                $d = $idMap[$id] ?? [];
                $price = $d['price_info'][0] ?? [];
                $image = $d['image'] ?? [];
                $products[] = [
                    'id' => $id,
                    'name' => $d['item_name'] ?? '',
                    'description' => $d['description'] ?? '',
                    'price' => $price['current_price'] ?? $price['original_price'] ?? 0,
                    'currency' => $price['currency'] ?? 'PHP',
                    'stock' => $d['stock_info_v2']['summary_info']['total_available_stock'] ?? $d['stock'] ?? 0,
                    'image_url' => $image['image_url_list'][0] ?? '',
                    'category_id' => $d['category_id'] ?? null,
                    'status' => $d['item_status'] ?? 'NORMAL',
                    'sku' => $d['item_sku'] ?? '',
                ];
            }
        }

        return $products;
    }

    public function getProductsByItemIds(array $itemIds): array
    {
        if (!$this->isReady()) {
            return [
                'success' => false,
                'error' => 'Shopee API not configured. Missing: ' . implode(', ', $this->getMissingCredentials()),
                'products' => [],
            ];
        }

        $itemIds = collect($itemIds)
            ->map(fn ($itemId) => trim((string) $itemId))
            ->filter()
            ->unique()
            ->values();

        if ($itemIds->isEmpty()) {
            return ['success' => true, 'products' => []];
        }

        $products = [];
        foreach ($itemIds->chunk(50) as $chunk) {
            $products = array_merge($products, $this->enrichItems($chunk->all()));
        }

        return [
            'success' => true,
            'products' => $products,
        ];
    }

    public function getProductByItemId($itemId): array
    {
        $result = $this->getProductsByItemIds([$itemId]);
        if (($result['success'] ?? false) !== true) {
            return $result + ['product' => null];
        }

        return [
            'success' => true,
            'product' => $result['products'][0] ?? null,
        ];
    }

    public function getRawItemBaseInfo($itemId): array
    {
        if (!$this->isReady()) {
            return [
                'success' => false,
                'error' => 'Shopee API not configured. Missing: ' . implode(', ', $this->getMissingCredentials()),
                'item' => null,
            ];
        }

        $result = $this->signedRequest('/api/v2/product/get_item_base_info', [
            'item_id_list' => [trim((string) $itemId)],
        ]);

        if (($result['success'] ?? false) !== true) {
            return $result + ['item' => null];
        }

        return [
            'success' => true,
            'item' => $result['data']['item_list'][0] ?? null,
            'data' => $result['data'] ?? [],
        ];
    }

    public function updateItemPrice($itemId, float $price, ?int $modelId = null): array
    {
        if (!$this->isReady()) {
            return [
                'success' => false,
                'error' => 'Shopee API not configured. Missing: ' . implode(', ', $this->getMissingCredentials()),
                'data' => [],
            ];
        }

        $pricePayload = [
            'original_price' => round($price, 2),
        ];
        if ($modelId !== null) {
            $pricePayload['model_id'] = $modelId;
        }

        return $this->signedRequest('/api/v2/product/update_price', [
            'item_id' => (int) $itemId,
            'price_list' => [$pricePayload],
        ], 'POST');
    }

    private function updateEnvValue(string $key, string $value): void
    {
        $path = base_path('.env');
        if (!file_exists($path)) return;
        $content = file_get_contents($path);
        $escapedValue = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
        if (str_contains($content, $key . '=')) {
            $content = preg_replace("/^{$key}=.*/m", "{$key}={$escapedValue}", $content);
        } else {
            $content .= "\n{$key}={$escapedValue}";
        }
        file_put_contents($path, $content);
    }

    public function updateItemStock(int $itemId, int $stock, ?int $modelId = null): array
    {
        if (!$this->isReady()) {
            return [
                'success' => false,
                'error' => 'Shopee API not configured. Missing: ' . implode(', ', $this->getMissingCredentials()),
                'data' => [],
            ];
        }

        $stockEntry = [
            'seller_stock' => [['stock' => max(0, $stock)]],
        ];

        if ($modelId !== null) {
            $stockEntry['model_id'] = $modelId;
        }

        return $this->signedRequest('/api/v2/product/update_stock', [
            'item_id' => (int) $itemId,
            'stock_list' => [$stockEntry],
        ], 'POST');
    }

    public function refreshAccessToken(): bool
    {
        $path = '/api/v2/auth/access_token/get';
        $timestamp = time();
        $baseString = $this->partnerId . $path . $timestamp;
        $sign = hash_hmac('sha256', $baseString, $this->partnerKey, false);

        $url = $this->baseUrl . $path . '?' . http_build_query([
            'partner_id' => (int) $this->partnerId,
            'timestamp' => $timestamp,
            'sign' => $sign,
        ]);

        try {
            $response = Http::timeout(30)->withOptions(['verify' => false])->post($url, [
                'refresh_token' => $this->refreshToken,
                'partner_id' => (int) $this->partnerId,
                'shop_id' => (int) $this->shopId,
            ]);

            if (!$response->successful()) {
                Log::error('Shopee token refresh failed: ' . $response->body());
                return false;
            }

            $data = $response->json();
            if (!empty($data['error'])) {
                Log::error('Shopee token refresh error: ' . $data['error'] . ' - ' . ($data['message'] ?? ''));
                return false;
            }

            $this->accessToken = $data['access_token'];
            $this->refreshToken = $data['refresh_token'];

            $this->updateEnvValue('SHOPEE_ACCESS_TOKEN', $this->accessToken);
            $this->updateEnvValue('SHOPEE_REFRESH_TOKEN', $this->refreshToken);

            Log::info('Shopee tokens refreshed successfully');
            return true;
        } catch (\Exception $e) {
            Log::error('Shopee token refresh exception: ' . $e->getMessage());
            return false;
        }
    }

    private function signedRequest(string $path, array $params = [], string $method = 'GET'): array
    {
        $timestamp = time();
        $baseString = $this->partnerId . $path . $timestamp . $this->accessToken . $this->shopId;
        $sign = hash_hmac('sha256', $baseString, $this->partnerKey, false);

        $common = http_build_query([
            'partner_id' => (int) $this->partnerId,
            'timestamp' => $timestamp,
            'access_token' => $this->accessToken,
            'shop_id' => (int) $this->shopId,
            'sign' => $sign,
        ]);

        $queryStr = $common;
        $method = strtoupper($method);
        if ($method === 'GET') {
            foreach ($params as $key => $val) {
                if (is_array($val)) {
                    foreach ($val as $v) {
                        $queryStr .= '&' . urlencode($key) . '=' . urlencode((string) $v);
                    }
                } else {
                    $queryStr .= '&' . urlencode($key) . '=' . urlencode((string) $val);
                }
            }
        }

        $url = $this->baseUrl . $path . '?' . $queryStr;

        try {
            $client = Http::timeout(30)->withOptions(['verify' => false]);
            $response = $method === 'POST'
                ? $client->asJson()->post($url, $params)
                : $client->get($url);
            $data = $response->json();

            if (!$response->successful()) {
                $errorCode = is_array($data) ? ($data['error'] ?? '') : '';
                if ($this->isInvalidAccessTokenError($errorCode) && $this->refreshAccessToken()) {
                    return $this->signedRequest($path, $params, $method);
                }

                return ['success' => false, 'error' => 'Shopee API error: ' . $response->body(), 'data' => []];
            }

            if (!empty($data['error'])) {
                if ($this->isInvalidAccessTokenError($data['error'])) {
                    if ($this->refreshAccessToken()) {
                        return $this->signedRequest($path, $params, $method);
                    }
                }
                return ['success' => false, 'error' => 'Shopee API: ' . $data['error'] . ' - ' . ($data['message'] ?? ''), 'data' => []];
            }

            return ['success' => true, 'data' => $data['response'] ?? []];
        } catch (\Exception $e) {
            Log::error("Shopee API request failed ({$path}): " . $e->getMessage());
            return ['success' => false, 'error' => 'Connection to Shopee failed: ' . $e->getMessage(), 'data' => []];
        }
    }

    private function isInvalidAccessTokenError(?string $errorCode): bool
    {
        return in_array($errorCode, ['invalid_access_token', 'invalid_acceess_token'], true);
    }

    public function searchProducts(int $page = 1, int $pageSize = 10): array
    {
        if (!$this->isReady()) {
            return [
                'success' => false,
                'error' => 'Shopee API not configured. Missing: ' . implode(', ', $this->getMissingCredentials()),
                'products' => [], 'total' => 0, 'per_page' => $pageSize, 'current_page' => $page, 'last_page' => 1,
            ];
        }

        $offset = ($page - 1) * $pageSize;

        $result = $this->signedRequest('/api/v2/product/get_item_list', [
            'offset' => $offset,
            'page_size' => $pageSize,
            'item_status' => 'NORMAL',
        ]);

        if (!$result['success']) {
            return [
                'success' => false, 'error' => $result['error'],
                'products' => [], 'total' => 0, 'per_page' => $pageSize, 'current_page' => $page, 'last_page' => 1,
            ];
        }

        $items = $result['data']['item'] ?? [];
        $totalCount = $result['data']['total_count'] ?? 0;

        $itemIds = array_map(fn($i) => $i['item_id'], $items);
        $itemIds = array_values(array_filter($itemIds));

        $products = $this->enrichItems($itemIds);

        return [
            'success' => true,
            'products' => $products,
            'total' => $totalCount,
            'per_page' => $pageSize,
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($totalCount / $pageSize)),
        ];
    }

    public function getAllProducts(int $pageSize = 50, int $maxPages = 1000): array
    {
        $pageSize = max(1, min(50, $pageSize));
        $maxPages = max(1, $maxPages);
        $products = [];
        $total = 0;
        $lastPage = 1;

        for ($page = 1; $page <= $maxPages && $page <= $lastPage; $page++) {
            $result = $this->searchProducts($page, $pageSize);

            if (($result['success'] ?? false) !== true) {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Shopee product sync failed.',
                    'products' => $products,
                    'total' => $total,
                    'pages_fetched' => $page - 1,
                ];
            }

            $products = array_merge($products, $result['products'] ?? []);
            $total = (int) ($result['total'] ?? $total);
            $lastPage = max(1, (int) ($result['last_page'] ?? 1));
        }

        return [
            'success' => true,
            'products' => $products,
            'total' => $total,
            'pages_fetched' => min($lastPage, $maxPages),
            'truncated' => $lastPage > $maxPages,
        ];
    }

    public function searchByKeyword(string $keyword, int $pageSize = 10, string $offset = ''): array
    {
        if (!$this->isReady()) {
            return [
                'success' => false,
                'error' => 'Shopee API not configured. Missing: ' . implode(', ', $this->getMissingCredentials()),
                'products' => [], 'total' => 0, 'per_page' => $pageSize, 'next_offset' => '',
            ];
        }

        if (empty(trim($keyword))) {
            return $this->searchProducts(1, $pageSize);
        }

        $params = [
            'page_size' => $pageSize,
            'item_name' => $keyword,
            'item_status' => 'NORMAL',
        ];
        if ($offset !== '') {
            $params['offset'] = $offset;
        }

        $result = $this->signedRequest('/api/v2/product/search_item', $params);

        if (!$result['success']) {
            return [
                'success' => false, 'error' => $result['error'],
                'products' => [], 'total' => 0, 'per_page' => $pageSize, 'next_offset' => '',
            ];
        }

        $itemIds = $result['data']['item_id_list'] ?? [];
        $totalCount = $result['data']['total_count'] ?? 0;
        $nextOffset = $result['data']['next_offset'] ?? '';

        $products = $this->enrichItems($itemIds);

        return [
            'success' => true,
            'products' => $products,
            'total' => $totalCount,
            'per_page' => $pageSize,
            'next_offset' => (string) $nextOffset,
        ];
    }
}
