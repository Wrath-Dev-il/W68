<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShopeeWebhookController;

Route::post('/shopee/webhook', [ShopeeWebhookController::class, 'handleProductUpdate']);

Route::post('/public/api/shopee/webhook', [ShopeeWebhookController::class, 'handleProductUpdate']);

Route::post('/hatdog/public/api/shopee/webhook', [ShopeeWebhookController::class, 'handleProductUpdate']);
