<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class UnservedReportServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // W68_UNSERVED_ALL_USERS_ROUTE_PROVIDER_FIX_20260918
        foreach (['special', 'admin', 'regular'] as $prefix) {
            Route::middleware('web')
                ->prefix($prefix)
                ->name($prefix . '.')
                ->group(base_path('routes/unserved-report.php'));
        }
    }
}