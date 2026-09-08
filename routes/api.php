<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShopeeWebhookController;
use App\Http\Controllers\DataSyncController;
use App\Http\Controllers\RegularProductMasterController;

Route::post('/shopee/webhook', [ShopeeWebhookController::class, 'handleProductUpdate']);
Route::post('/public/api/shopee/webhook', [ShopeeWebhookController::class, 'handleProductUpdate']);
Route::post('/hatdog/public/api/shopee/webhook', [ShopeeWebhookController::class, 'handleProductUpdate']);

/*
|--------------------------------------------------------------------------
| Web-session endpoints exposed under /api
|--------------------------------------------------------------------------
| These are browser calls from authenticated W68 pages, so the web middleware
| provides the existing session and CSRF protection.
*/
Route::middleware('web')->group(function () {
    // HostForge-safe Regular Product Master AJAX data. This bypasses the old
    // cross-database SQL route in routes/web.php without touching the huge file.
    Route::get('/regular-product-master/data', [RegularProductMasterController::class, 'data']);

    Route::prefix('datasync')->group(function () {
        Route::get('/config', [DataSyncController::class, 'config']);
        Route::post('/run', [DataSyncController::class, 'run']);
    });
});

// Server-to-server localhost -> HostForge receiver. Authentication uses the
// shared X-Datasync-Token, not a browser session.
Route::post('/datasync/receive', [DataSyncController::class, 'receive']);
