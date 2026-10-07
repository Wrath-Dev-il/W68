<?php

namespace App\Services;

use App\Models\ShopeeCredential;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopeeService
{
    private const HTTP_TIMEOUT_SECONDS = 8;
    private const CONNECT_TIMEOUT_SECONDS = 4;
    private const REFRESH_EARLY_SECONDS = 600;

    protected string $partnerId;
    protected string $partnerKey;
    protected string $accessToken;
    protected string $refreshToken;
    protected string $shopId;
    protected string $baseUrl;
    protected ?ShopeeCredential $credential = null;
    protected ?string $lastAuthError = null;
    protected bool $reauthorizationRequired = false;

    public function __construct()
    {
        $this->partnerId = trim((string) config('services.shopee.partner_id', ''));
        $this->partnerKey = trim((string) config('services.shopee.partner_key', ''));
        $this->accessToken = trim((string) config('services.shopee.access_token', ''));
        $this->refreshToken = trim((string) config('services.shopee.refresh_token', ''));
        $this->shopId = trim((string) config('services.shopee.shop_id', ''));
        $this->baseUrl = rtrim((string) config('services.shopee.base_url', 'https://partner.shopeemobile.com'), '/');

        $this->reloadStoredCredential();
    }

    public function isReady(): bool
    {
        return $this->partnerId !== ''
            && $this->partnerKey !== ''
            && $this->accessToken !== ''
            && $this->shopId !== '';
    }

    public function getMissingCredentials(): array
    {
        $missing = [];
        if ($this->partnerId === '') $missing[] = 'partner_id';
        if ($this->partnerKey === '') $missing[] = 'partner_key';
        if ($this->accessToken === '') $missing[] = 'access_token';
        if ($this->shopId === '') $missing[] = 'shop_id';
        return $missing;
    }

    public function requiresReauthorization(): bool
    {
        return $this->reauthorizationRequired
            || (bool) ($this->credential?->reauthorization_required ?? false);
    }

    public function getLastAuthError(): ?string
    {
        return $this->lastAuthError ?: ($this->credential?->last_error ?: null);
    }

    public function getAuthorizationStatus(): array
    {
        $this->reloadStoredCredential();

        return [
            'partner_configured' => $this->partnerId !== '' && $this->partnerKey !== '',
            'stored' => $this->credential !== null,
            'shop_id' => $this->credential?->shop_id ?: ($this->shopId !== '' ? $this->shopId : null),
            'access_token_expires_at' => $this->credential?->access_token_expires_at?->toIso8601String(),
            'refresh_token_expires_at' => $this->credential?->refresh_token_expires_at?->toIso8601String(),
            'authorization_expires_at' => $this->credential?->authorization_expires_at?->toIso8601String(),
            'authorized_at' => $this->credential?->authorized_at?->toIso8601String(),
            'last_refreshed_at' => $this->credential?->last_refreshed_at?->toIso8601String(),
            'reauthorization_required' => $this->requiresReauthorization(),
            'last_error' => $this->getLastAuthError(),
        ];
    }

    public function storeAuthorizationTokens(
        string $shopId,
        string $accessToken,
        string $refreshToken,
        int $accessExpiresIn = 14400,
        ?int $refreshExpiresIn = null,
        ?int $authorizationExpiresIn = null
    ): ShopeeCredential {
        $shopId = trim($shopId);
        $accessToken = trim($accessToken);
        $refreshToken = trim($refreshToken);

        if ($shopId === '' || $accessToken === '' || $refreshToken === '') {
            throw new \InvalidArgumentException('Shopee authorization response did not contain complete credentials.');
        }

        $now = now();
        $credential = ShopeeCredential::query()->updateOrCreate(
            ['shop_id' => $shopId],
            [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'access_token_expires_at' => $now->copy()->addSeconds(max(60, $accessExpiresIn)),
                'refresh_token_expires_at' => $refreshExpiresIn && $refreshExpiresIn > 0
                    ? $now->copy()->addSeconds($refreshExpiresIn)
                    : null,
                'authorization_expires_at' => $authorizationExpiresIn && $authorizationExpiresIn > 0
                    ? $now->copy()->addSeconds($authorizationExpiresIn)
                    : null,
                'authorized_at' => $now,
                'last_refreshed_at' => $now,
                'reauthorization_required' => false,
                'last_error' => null,
            ]
        );

        $this->credential = $credential;
        $this->shopId = $shopId;
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;
        $this->reauthorizationRequired = false;
        $this->lastAuthError = null;

        return $credential;
    }

    private function reloadStoredCredential(): void
    {
        try {
            $query = ShopeeCredential::query();
            $credential = null;

            if ($this->shopId !== '') {
                $credential = (clone $query)->where('shop_id', $this->shopId)->first();
            }

            if (!$credential) {
                $credential = $query->latest('updated_at')->first();
            }

            if (!$credential) {
                return;
            }

            $this->credential = $credential;
            $this->shopId = trim((string) $credential->shop_id);
            $this->accessToken = trim((string) $credential->access_token);
            $this->refreshToken = trim((string) $credential->refresh_token);
            $this->reauthorizationRequired = (bool) $credential->reauthorization_required;
            $this->lastAuthError = $credential->last_error ?: null;
        } catch (\Throwable $e) {
            // The migration may not have run yet. Keep the .env values as a
            // temporary fallback so deployment is backwards compatible.
            Log::debug('Shopee DB credential lookup unavailable; using configured fallback.', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function httpClient()
    {
        return Http::connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
            ->timeout(self::HTTP_TIMEOUT_SECONDS)
            ->withOptions(['verify' => false]);
    }

    private function enrichItems(array $itemIds): array
    {
        if (empty($itemIds)) return [];

        $products = [];
        $baseResult = $this->signedRequest('/api/v2/product/get_item_base_info', [
            'item_id_list' => array_slice($itemIds, 0, 50),
        ]);

        if (($baseResult['success'] ?? false) !== true) {
            Log::warning('Shopee item enrichment failed.', [
                'item_count' => count($itemIds),
                'error' => $baseResult['error'] ?? 'Unknown Shopee error',
            ]);
            return [];
        }

        $detailedItems = $baseResult['data']['item_list'] ?? [];
        $idMap = [];
        foreach ($detailedItems as $d) {
            if (isset($d['item_id'])) {
                $idMap[(string) $d['item_id']] = $d;
            }
        }

        foreach ($itemIds as $id) {
            $d = $idMap[(string) $id] ?? [];
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

    public function refreshAccessToken(bool $force = false): bool
    {
        $this->reloadStoredCredential();

        if ($this->partnerId === '' || $this->partnerKey === '' || $this->shopId === '' || $this->refreshToken === '') {
            $this->lastAuthError = 'Shopee refresh credentials are incomplete. Reauthorization is required.';
            Log::warning('Shopee token refresh skipped because required credentials are missing.');
            return false;
        }

        $lock = Cache::lock('w68-shopee-token-refresh-' . $this->shopId, 30);

        try {
            return (bool) $lock->block(5, function () use ($force) {
                // Another process may have refreshed while this request waited.
                $this->reloadStoredCredential();

                if (!$force && $this->credential?->access_token_expires_at) {
                    if ($this->credential->access_token_expires_at->isAfter(now()->addSeconds(self::REFRESH_EARLY_SECONDS))) {
                        return true;
                    }
                }

                if ($this->refreshToken === '') {
                    $this->markAuthFailure('missing_refresh_token', 'Shopee refresh token is missing.');
                    return false;
                }

                $path = '/api/v2/auth/access_token/get';
                $timestamp = time();
                $baseString = $this->partnerId . $path . $timestamp;
                $sign = hash_hmac('sha256', $baseString, $this->partnerKey, false);

                $url = $this->baseUrl . $path . '?' . http_build_query([
                    'partner_id' => (int) $this->partnerId,
                    'timestamp' => $timestamp,
                    'sign' => $sign,
                ]);

                $response = $this->httpClient()->asJson()->post($url, [
                    'refresh_token' => $this->refreshToken,
                    'partner_id' => (int) $this->partnerId,
                    'shop_id' => (int) $this->shopId,
                ]);

                $data = $response->json();
                $data = is_array($data) ? $data : [];

                if (!$response->successful() || !empty($data['error'])) {
                    $errorCode = trim((string) ($data['error'] ?? 'http_' . $response->status()));
                    $message = trim((string) ($data['message'] ?? $this->safeResponseError($response->body())));
                    $this->markAuthFailure($errorCode, $message);
                    Log::error('Shopee token refresh failed.', [
                        'error' => $errorCode,
                        'message' => $message,
                    ]);
                    return false;
                }

                $newAccessToken = trim((string) ($data['access_token'] ?? ''));
                $newRefreshToken = trim((string) ($data['refresh_token'] ?? ''));
                if ($newAccessToken === '' || $newRefreshToken === '') {
                    $this->markAuthFailure('incomplete_token_response', 'Shopee refresh response did not contain complete tokens.');
                    return false;
                }

                $accessExpiresIn = max(60, (int) ($data['expire_in'] ?? 14400));
                $refreshExpiresIn = isset($data['refresh_token_expire_in'])
                    ? max(60, (int) $data['refresh_token_expire_in'])
                    : null;
                $now = now();

                $credential = ShopeeCredential::query()->updateOrCreate(
                    ['shop_id' => $this->shopId],
                    [
                        'access_token' => $newAccessToken,
                        'refresh_token' => $newRefreshToken,
                        'access_token_expires_at' => $now->copy()->addSeconds($accessExpiresIn),
                        'refresh_token_expires_at' => $refreshExpiresIn
                            ? $now->copy()->addSeconds($refreshExpiresIn)
                            : null,
                        'last_refreshed_at' => $now,
                        'reauthorization_required' => false,
                        'last_error' => null,
                    ]
                );

                $this->credential = $credential;
                $this->accessToken = $newAccessToken;
                $this->refreshToken = $newRefreshToken;
                $this->reauthorizationRequired = false;
                $this->lastAuthError = null;

                Log::info('Shopee tokens refreshed and rotated successfully.', [
                    'shop_id' => $this->shopId,
                    'access_token_expires_at' => $credential->access_token_expires_at?->toIso8601String(),
                ]);

                return true;
            });
        } catch (\Throwable $e) {
            $this->lastAuthError = 'Shopee token refresh failed: ' . $e->getMessage();
            Log::error('Shopee token refresh exception.', ['error' => $e->getMessage()]);
            return false;
        } finally {
            try {
                if ($lock->owner()) {
                    $lock->release();
                }
            } catch (\Throwable) {
                // The block() helper normally releases the lock itself.
            }
        }
    }

    private function ensureAccessTokenFresh(): bool
    {
        $this->reloadStoredCredential();

        if ($this->requiresReauthorization()) {
            return false;
        }

        if ($this->accessToken === '') {
            return false;
        }

        if ($this->credential?->access_token_expires_at
            && $this->credential->access_token_expires_at->isBefore(now()->addSeconds(self::REFRESH_EARLY_SECONDS))) {
            return $this->refreshAccessToken(false);
        }

        return true;
    }

    private function markAuthFailure(string $errorCode, string $message): void
    {
        $errorCode = trim($errorCode);
        $message = trim($message);
        $this->lastAuthError = trim($errorCode . ($message !== '' ? ': ' . $message : ''));

        $reauthorize = in_array($errorCode, [
            'refresh_token_expired',
            'invalid_refresh_token',
            'invalid_refresh_token_error',
            'missing_refresh_token',
        ], true);

        if ($reauthorize) {
            $this->reauthorizationRequired = true;
        }

        if ($this->credential) {
            try {
                $this->credential->forceFill([
                    'reauthorization_required' => $reauthorize || $this->credential->reauthorization_required,
                    'last_error' => $this->lastAuthError,
                ])->save();
            } catch (\Throwable $e) {
                Log::warning('Unable to persist Shopee authorization failure state.', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function authorizationFailureResult(): array
    {
        $message = 'Shopee authorization has expired. An administrator must reauthorize Shopee at /admin/shopee/authorization.';
        if ($this->getLastAuthError()) {
            $message .= ' (' . $this->getLastAuthError() . ')';
        }

        return [
            'success' => false,
            'error' => $message,
            'data' => [],
            'reauthorization_required' => true,
            'reauthorize_url' => '/admin/shopee/authorization',
        ];
    }

    private function signedRequest(
        string $path,
        array $params = [],
        string $method = 'GET',
        bool $allowTokenRefresh = true
    ): array {
        if (!$this->ensureAccessTokenFresh()) {
            if ($this->requiresReauthorization()) {
                return $this->authorizationFailureResult();
            }

            return [
                'success' => false,
                'error' => 'Shopee access token is unavailable. Reauthorization may be required.',
                'data' => [],
            ];
        }

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
            $client = $this->httpClient();
            $response = $method === 'POST'
                ? $client->asJson()->post($url, $params)
                : $client->get($url);
            $data = $response->json();
            $data = is_array($data) ? $data : [];

            if (!$response->successful()) {
                $errorCode = (string) ($data['error'] ?? '');
                if ($allowTokenRefresh && $this->isInvalidAccessTokenError($errorCode)) {
                    if ($this->refreshAccessToken(true)) {
                        return $this->signedRequest($path, $params, $method, false);
                    }
                    if ($this->requiresReauthorization()) {
                        return $this->authorizationFailureResult();
                    }
                }

                return [
                    'success' => false,
                    'error' => 'Shopee API error: ' . $this->safeResponseError($response->body()),
                    'data' => [],
                ];
            }

            if (!empty($data['error'])) {
                $errorCode = (string) $data['error'];
                if ($allowTokenRefresh && $this->isInvalidAccessTokenError($errorCode)) {
                    if ($this->refreshAccessToken(true)) {
                        return $this->signedRequest($path, $params, $method, false);
                    }
                    if ($this->requiresReauthorization()) {
                        return $this->authorizationFailureResult();
                    }
                }

                return [
                    'success' => false,
                    'error' => 'Shopee API: ' . $errorCode . ' - ' . (string) ($data['message'] ?? ''),
                    'data' => [],
                ];
            }

            return ['success' => true, 'data' => $data['response'] ?? []];
        } catch (\Throwable $e) {
            Log::error("Shopee API request failed ({$path}): " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Connection to Shopee failed: ' . $e->getMessage(),
                'data' => [],
            ];
        }
    }

    private function safeResponseError(string $body): string
    {
        $body = trim(preg_replace('/\s+/', ' ', $body) ?? '');
        if ($body === '') {
            return 'HTTP request failed';
        }

        return mb_substr($body, 0, 500);
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
                'products' => [],
                'total' => 0,
                'per_page' => $pageSize,
                'current_page' => $page,
                'last_page' => 1,
            ];
        }

        $page = max(1, $page);
        $pageSize = max(1, min(50, $pageSize));
        $offset = ($page - 1) * $pageSize;

        $result = $this->signedRequest('/api/v2/product/get_item_list', [
            'offset' => $offset,
            'page_size' => $pageSize,
            'item_status' => 'NORMAL',
        ]);

        if (($result['success'] ?? false) !== true) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'Shopee product request failed.',
                'products' => [],
                'total' => 0,
                'per_page' => $pageSize,
                'current_page' => $page,
                'last_page' => 1,
                'reauthorization_required' => $result['reauthorization_required'] ?? false,
                'reauthorize_url' => $result['reauthorize_url'] ?? null,
            ];
        }

        $items = is_array($result['data']['item'] ?? null) ? $result['data']['item'] : [];
        $totalCount = max(0, (int) ($result['data']['total_count'] ?? 0));

        $itemIds = array_values(array_filter(array_map(
            fn ($item) => is_array($item) ? ($item['item_id'] ?? null) : null,
            $items
        )));

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
                    'reauthorization_required' => $result['reauthorization_required'] ?? false,
                    'reauthorize_url' => $result['reauthorize_url'] ?? null,
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
                'products' => [],
                'total' => 0,
                'per_page' => $pageSize,
                'next_offset' => '',
            ];
        }

        $keyword = trim($keyword);
        $pageSize = max(1, min(50, $pageSize));
        if ($keyword === '') {
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

        if (($result['success'] ?? false) !== true) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'Shopee product search failed.',
                'products' => [],
                'total' => 0,
                'per_page' => $pageSize,
                'next_offset' => '',
                'reauthorization_required' => $result['reauthorization_required'] ?? false,
                'reauthorize_url' => $result['reauthorize_url'] ?? null,
            ];
        }

        $itemIds = is_array($result['data']['item_id_list'] ?? null)
            ? array_values(array_filter($result['data']['item_id_list']))
            : [];
        $totalCount = max(0, (int) ($result['data']['total_count'] ?? 0));
        $nextOffset = (string) ($result['data']['next_offset'] ?? '');

        $products = $this->enrichItems($itemIds);

        return [
            'success' => true,
            'products' => $products,
            'total' => $totalCount,
            'per_page' => $pageSize,
            'next_offset' => $nextOffset,
        ];
    }
}
