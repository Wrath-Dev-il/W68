<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| W68 HostForge route overrides
|--------------------------------------------------------------------------
|
| W68_ONLINE_PRODUCT_DIRECT_FETCH_20261007
| W68_ONLINE_PRODUCT_ZERO_UNCONVERTED_FAST_PATH_20261007
|
| Regular/Special normally proxy the Online Product fetch into the Admin route
| through a nested Laravel request. HostForge can terminate the Shopee-backed
| request with HTTP 503. These overrides are loaded after routes/web.php.
|
| The Online tab only shows Shopee products that are NOT yet linked/converted.
| Before asking Shopee for a full page of item details, make the same tiny
| one-item catalog probe used by the dashboard to obtain total_count. When the
| Shopee catalog total is already less than or equal to the number of linked
| online_products, there cannot be an unconverted item, so return an empty
| successful page immediately. This avoids the unnecessary heavy fetch that was
| producing the HostForge 503 while the dashboard correctly showed 0 remaining.
|
| The same handler is registered for Admin, Regular, and Special so all three
| roles behave identically.
|
*/
$w68DirectOnlineProductFetch = function (Request $request) {
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
    $perPage = max(1, min(100, (int) $request->query('per_page', 20)));

    try {
        $linkedCount = (int) \Illuminate\Support\Facades\DB::connection('masterlist')
            ->table('online_products')
            ->whereNotNull('product_id')
            ->count();

        $service = new \App\Services\ShopeeService();
        if ($service->isReady()) {
            // Lightweight probe: page_size=1 keeps the Shopee response tiny but
            // still returns total_count. The dashboard already uses this shape.
            $probe = $service->searchProducts(1, 1);

            if (($probe['success'] ?? false) === true) {
                $shopeeTotal = max(0, (int) ($probe['total'] ?? 0));

                if ($shopeeTotal <= $linkedCount) {
                    return response()->json([
                        'success' => true,
                        'source' => 'shopee',
                        'products' => [],
                        'total' => 0,
                        'per_page' => $perPage,
                        'current_page' => $page,
                        'last_page' => 1,
                        'next_offset' => '',
                        'catalog_total' => $shopeeTotal,
                        'converted_total' => $linkedCount,
                        'w68_fast_path' => true,
                    ]);
                }
            }
        }
    } catch (\Throwable $e) {
        // Do not turn an optimization failure into a page failure. Fall through
        // to the original Admin implementation below and log the diagnostic.
        Log::warning('W68 Online Product fast-path probe failed.', [
            'error' => $e->getMessage(),
        ]);
    }

    // There are potentially unconverted products, so use the existing Admin
    // business logic. The named route still references the original route from
    // routes/web.php because these URI overrides intentionally have no names.
    $targetRoute = app('router')->getRoutes()->getByName('admin.prod-online-config.fetch-online');
    $targetAction = $targetRoute ? $targetRoute->getAction('uses') : null;

    if (!$targetAction instanceof \Closure) {
        Log::error('W68 direct Online Product fetch could not resolve the Admin route action.');

        return response()->json([
            'success' => false,
            'error' => 'Online Product route unavailable',
            'message' => 'Online Product route unavailable.',
        ], 500);
    }

    return $targetAction($request);
};

Route::get('/admin/masterlist/online-product-config/fetch-online', $w68DirectOnlineProductFetch);
Route::get('/special/master-list/online-product-config/fetch-online', $w68DirectOnlineProductFetch);
Route::get('/regular/master-list/online-product-config/fetch-online', $w68DirectOnlineProductFetch);
