<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Ensure Storage Directories Exist (Deployment Fix)
|--------------------------------------------------------------------------
*/
$basePath = dirname(__DIR__);
$storagePaths = [
    $basePath . '/storage/framework/sessions',
    $basePath . '/storage/framework/views',
    $basePath . '/storage/framework/cache',
    $basePath . '/storage/framework/cache/data',
    $basePath . '/storage/logs',
];

foreach ($storagePaths as $path) {
    if (!is_dir($path)) {
        @mkdir($path, 0775, true);
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // W68_HOSTFORGE_ROUTE_OVERRIDES_20261007
            // Loaded after routes/web.php so targeted HostForge-safe routes can
            // replace only the original matching URI/method registrations.
            Route::middleware('web')->group(base_path('routes/w68_overrides.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'api/shopee/webhook',
            'public/api/shopee/webhook',
            'hatdog/public/api/shopee/webhook',
            'webhooks/shopee',
            'hatdog/public/webhooks/shopee',
            'admin/purchase/purchase-note/verify-password',
            'hatdog/public/admin/purchase/purchase-note/verify-password',
            '*/purchase/purchase-note/verify-password',
            'check-password',
            'hatdog/public/check-password',
            'admin/purchase/purchase-note',
            'hatdog/public/admin/purchase/purchase-note',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
