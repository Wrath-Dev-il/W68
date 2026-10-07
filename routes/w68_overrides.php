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
|
| The Regular/Special Online Product Config routes in routes/web.php proxy
| into the Admin route by booting a nested Laravel request through
| app()->handle(). On HostForge the Shopee-backed fetch can be terminated by
| the platform as HTTP 503 before that nested request returns.
|
| These routes are loaded AFTER routes/web.php, so only the two read-only
| fetch-online URLs are replaced. The Admin business logic remains the single
| source of truth and is invoked directly with the current Request/session.
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

    if (!in_array((int) ($user->account_type ?? 0), [2, 3], true)) {
        return response()->json([
            'success' => false,
            'error' => 'Forbidden',
            'message' => 'Forbidden',
        ], 403);
    }

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

Route::get('/special/master-list/online-product-config/fetch-online', $w68DirectOnlineProductFetch);
Route::get('/regular/master-list/online-product-config/fetch-online', $w68DirectOnlineProductFetch);
