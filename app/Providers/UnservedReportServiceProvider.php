<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class UnservedReportServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')
            ->prefix('special')
            ->name('special.')
            ->group(base_path('routes/unserved-report.php'));
    }
}
