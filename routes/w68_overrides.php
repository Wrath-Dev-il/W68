<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| W68 HostForge route overrides
|--------------------------------------------------------------------------
|
| W68_ONLINE_PRODUCT_DIRECT_FETCH_20261007
| W68_ONLINE_PRODUCT_ZERO_UNCONVERTED_FAST_PATH_20261007
| W68_ONLINE_PRODUCT_REMOVE_ROUTE_FALLBACK_20261007
|
| The Online Product tab must not boot a nested Laravel request or resolve and
| execute another named route. On HostForge those paths produced 503/500
| responses. This handler now performs the read directly for Admin, Regular,
| and Special users.
|
*/
$w68DirectOnlineProductFetch = function (Request $request) {
    try {
        $user = session('user');
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
                'message' => 'Unauthorized',
            ], 403);
        }

        if (is_array($user)) {
            $user = (object) $user;
        }

        if (!in_array((int) ($user->account_type ?? 0), [1, 2, 3], true)) {
            return response()->json([
                'success' => false,
                'error' => 'Forbidden',
                'message' => 'Forbidden',
            ], 403);
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(50, (int) $request->query('per_page', 20)));

        $convertedQuery = DB::connection('masterlist')
            ->table('online_products')
            ->where(function ($query) {
                $query->whereNotNull('product_id')
                    ->orWhere('is_converted', 1);
            });

        $convertedCount = (int) (clone $convertedQuery)->count();
        $convertedCodes = (clone $convertedQuery)
            ->whereNotNull('product_code')
            ->pluck('product_code')
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->filter()
            ->flip();

        $service = app(\App\Services\ShopeeService::class);
        if (!$service->isReady()) {
            return response()->json([
                'success' => false,
                'error' => 'Shopee API is not configured.',
                'message' => 'Shopee API is not configured.',
                'products' => [],
                'total' => 0,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => 1,
            ]);
        }

        // First request only one item so we can obtain Shopee total_count with
        // the smallest possible payload. If all Shopee items are already
        // converted, stop here and do not perform the normal 20-item fetch.
        $probe = $service->searchProducts(1, 1);
        if (($probe['success'] ?? false) !== true) {
            $message = (string) ($probe['error'] ?? 'Unable to reach Shopee.');
            Log::warning('W68 Online Product Shopee probe failed.', ['error' => $message]);

            return response()->json([
                'success' => false,
                'error' => $message,
                'message' => $message,
                'products' => [],
                'total' => 0,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => 1,
                'w68_direct_fetch' => true,
            ]);
        }

        $catalogTotal = max(0, (int) ($probe['total'] ?? 0));
        if ($catalogTotal <= $convertedCount) {
            return response()->json([
                'success' => true,
                'source' => 'shopee',
                'products' => [],
                'total' => 0,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => 1,
                'next_offset' => '',
                'catalog_total' => $catalogTotal,
                'converted_total' => $convertedCount,
                'w68_fast_path' => true,
                'w68_direct_fetch' => true,
            ]);
        }

        // There are still possible unconverted items. Fetch the requested
        // Shopee page directly; never delegate to the Admin named route.
        $result = $service->searchProducts($page, $perPage);
        if (($result['success'] ?? false) !== true) {
            $message = (string) ($result['error'] ?? 'Unable to load Shopee products.');
            Log::warning('W68 Online Product direct fetch failed.', ['error' => $message]);

            return response()->json([
                'success' => false,
                'error' => $message,
                'message' => $message,
                'products' => [],
                'total' => max(0, $catalogTotal - $convertedCount),
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => max(1, (int) ceil(max(0, $catalogTotal - $convertedCount) / $perPage)),
                'w68_direct_fetch' => true,
            ]);
        }

        $products = collect($result['products'] ?? [])
            ->map(function ($product) {
                if (!is_array($product)) {
                    return $product;
                }

                $itemId = trim((string) ($product['id'] ?? ''));
                if (!isset($product['product_code']) && $itemId !== '') {
                    $product['product_code'] = 'SHOPEE-' . $itemId;
                }
                if (!isset($product['category']) && isset($product['category_id'])) {
                    $product['category'] = 'Category #' . $product['category_id'];
                }
                $product['source'] = $product['source'] ?? 'shopee';

                return $product;
            })
            ->filter(function ($product) use ($convertedCodes) {
                if (!is_array($product)) {
                    return false;
                }

                $itemId = trim((string) ($product['id'] ?? ''));
                $code = strtoupper(trim((string) ($product['product_code'] ?? ($itemId !== '' ? 'SHOPEE-' . $itemId : ''))));

                return $code === '' || !$convertedCodes->has($code);
            })
            ->values()
            ->all();

        $remainingTotal = max(0, $catalogTotal - $convertedCount);

        return response()->json([
            'success' => true,
            'source' => 'shopee',
            'products' => $products,
            'total' => $remainingTotal,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($remainingTotal / $perPage)),
            'catalog_total' => $catalogTotal,
            'converted_total' => $convertedCount,
            'w68_direct_fetch' => true,
        ]);
    } catch (\Throwable $e) {
        Log::error('W68 Online Product direct fetch exception.', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        // Return JSON with HTTP 200 so the browser receives the actual error
        // payload instead of HostForge replacing it with a generic 500 page.
        return response()->json([
            'success' => false,
            'error' => 'Online Product fetch failed: ' . $e->getMessage(),
            'message' => 'Online Product fetch failed: ' . $e->getMessage(),
            'products' => [],
            'total' => 0,
            'per_page' => max(1, min(50, (int) $request->query('per_page', 20))),
            'current_page' => max(1, (int) $request->query('page', 1)),
            'last_page' => 1,
            'w68_direct_fetch' => true,
        ]);
    }
};

Route::get('/admin/masterlist/online-product-config/fetch-online', $w68DirectOnlineProductFetch);
Route::get('/special/master-list/online-product-config/fetch-online', $w68DirectOnlineProductFetch);
Route::get('/regular/master-list/online-product-config/fetch-online', $w68DirectOnlineProductFetch);
