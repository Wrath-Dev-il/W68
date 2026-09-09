<?php

namespace App\Providers;

use App\Models\Login;
use App\Models\ProductLedger;
use App\Observers\ProductLedgerObserver;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // W68_PAYMENTS_PHASE1_TIMING_20260909
        // READ-ONLY diagnostics for the Active Payments request.
        // Does not alter queries, payment formulas, balances, statuses or database data.
        if (!$this->app->runningInConsole() && request()->is('admin/payments/data')) {
            $__w68PaymentsStartedAt = microtime(true);
            $__w68PaymentsPage = max(1, (int) request()->query('page', 1));
            $__w68PaymentsHasSearch = trim((string) request()->query('search', '')) !== '';

            $__w68PaymentsPerf = [
                'query_count' => 0,
                'query_ms' => 0.0,
                'connections' => [],
            ];

            \Illuminate\Support\Facades\DB::listen(
                function ($query) use (&$__w68PaymentsPerf) {
                    $connectionName = 'unknown';

                    try {
                        if (
                            isset($query->connection) &&
                            method_exists($query->connection, 'getName')
                        ) {
                            $connectionName = (string) $query->connection->getName();
                        } elseif (isset($query->connectionName)) {
                            $connectionName = (string) $query->connectionName;
                        }
                    } catch (\Throwable $e) {
                        $connectionName = 'unknown';
                    }

                    $queryMs = (float) ($query->time ?? 0);

                    $__w68PaymentsPerf['query_count']++;
                    $__w68PaymentsPerf['query_ms'] += $queryMs;

                    if (!isset($__w68PaymentsPerf['connections'][$connectionName])) {
                        $__w68PaymentsPerf['connections'][$connectionName] = [
                            'query_count' => 0,
                            'query_ms' => 0.0,
                        ];
                    }

                    $__w68PaymentsPerf['connections'][$connectionName]['query_count']++;
                    $__w68PaymentsPerf['connections'][$connectionName]['query_ms'] += $queryMs;
                }
            );

            $this->app->terminating(
                function () use (
                    $__w68PaymentsStartedAt,
                    $__w68PaymentsPage,
                    $__w68PaymentsHasSearch,
                    &$__w68PaymentsPerf
                ) {
                    $durationMs = (microtime(true) - $__w68PaymentsStartedAt) * 1000;
                    $queryMs = (float) $__w68PaymentsPerf['query_ms'];

                    foreach ($__w68PaymentsPerf['connections'] as &$connectionStats) {
                        $connectionStats['query_ms'] = round(
                            (float) $connectionStats['query_ms'],
                            1
                        );
                    }
                    unset($connectionStats);

                    ksort($__w68PaymentsPerf['connections']);

                    \Illuminate\Support\Facades\Log::info(
                        'W68_PAYMENTS_PERF',
                        [
                            'endpoint' => '/admin/payments/data',
                            'page' => $__w68PaymentsPage,
                            'search' => $__w68PaymentsHasSearch,
                            'duration_ms' => round($durationMs, 1),
                            'query_count' => (int) $__w68PaymentsPerf['query_count'],
                            'query_ms' => round($queryMs, 1),
                            'php_other_ms' => round(max(0, $durationMs - $queryMs), 1),
                            'peak_memory_mb' => round(
                                memory_get_peak_usage(true) / 1048576,
                                1
                            ),
                            'connections' => $__w68PaymentsPerf['connections'],
                        ]
                    );
                }
            );
        }

        ProductLedger::observe(ProductLedgerObserver::class);

        View::composer([
            'partials.admin.admin_sidebar_navbar',
            'partials.user_account.user_sidebar_navbar',
            'partials.special_user.special_sidebar_navbar',
        ], function ($view) {
            $sidebarUser = $view->getData()['user'] ?? session('user');

            if (is_array($sidebarUser)) {
                $sidebarUser = (object) $sidebarUser;
            }

            $sidebarFirstName = trim((string) ($sidebarUser->User_First_Name ?? 'Admin'));
            $sidebarMiddleName = trim((string) ($sidebarUser->User_Middle_Name ?? ''));
            $sidebarLastName = trim((string) ($sidebarUser->User_Last_Name ?? 'User'));
            $sidebarAccountType = (int) ($sidebarUser->account_type ?? ($view->getData()['account_type'] ?? 1));
            $sidebarFullName = trim(collect([$sidebarFirstName, $sidebarMiddleName, $sidebarLastName])->filter()->implode(' '));
            $sidebarRoleLabel = match ($sidebarAccountType) {
                1 => 'Admin',
                2 => 'Employee',
                3 => 'Employee',
                4 => 'Developer',
                default => 'System User',
            };
            $sidebarProfilePictureUrl = null;

            try {
                if ($sidebarUser && Schema::hasTable('logins')) {
                    $sidebarProfileQuery = Login::query()
                        ->select(['login_ID', 'User_ID', 'User_First_Name', 'User_Middle_Name', 'User_Last_Name', 'account_type']);

                    $hasProfilePictureColumn = Schema::hasColumn('logins', 'profile_picture');
                    $hasProfilePictureMimeColumn = Schema::hasColumn('logins', 'profile_picture_mime');

                    if ($hasProfilePictureColumn) {
                        $sidebarProfileQuery->addSelect('profile_picture');
                    }

                    if ($hasProfilePictureMimeColumn) {
                        $sidebarProfileQuery->addSelect('profile_picture_mime');
                    }

                    if (!empty($sidebarUser->login_ID)) {
                        $sidebarProfileQuery->where('login_ID', $sidebarUser->login_ID);
                    } elseif (!empty($sidebarUser->User_ID)) {
                        $sidebarProfileQuery->where('User_ID', $sidebarUser->User_ID);
                    }

                    $sidebarFreshUser = $sidebarProfileQuery->first();

                    if ($sidebarFreshUser) {
                        $sidebarFirstName = trim((string) ($sidebarFreshUser->User_First_Name ?? $sidebarFirstName));
                        $sidebarMiddleName = trim((string) ($sidebarFreshUser->User_Middle_Name ?? $sidebarMiddleName));
                        $sidebarLastName = trim((string) ($sidebarFreshUser->User_Last_Name ?? $sidebarLastName));
                        $sidebarAccountType = (int) ($sidebarFreshUser->account_type ?? $sidebarAccountType);
                        $sidebarFullName = trim(collect([$sidebarFirstName, $sidebarMiddleName, $sidebarLastName])->filter()->implode(' '));
                        $sidebarRoleLabel = match ($sidebarAccountType) {
                            1 => 'Admin',
                            2 => 'Employee',
                            3 => 'Employee',
                            4 => 'Developer',
                            default => 'System User',
                        };

                        if ($hasProfilePictureColumn && !empty($sidebarFreshUser->profile_picture)) {
                            $sidebarProfilePictureUrl = 'data:' . ($hasProfilePictureMimeColumn ? ($sidebarFreshUser->profile_picture_mime ?: 'image/png') : 'image/png')
                                . ';base64,' . base64_encode($sidebarFreshUser->profile_picture);
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Keep the navbar resilient even if the profile lookup fails.
            }

            $sidebarInitialsSource = $sidebarLastName !== '' ? $sidebarLastName : ($sidebarMiddleName !== '' ? $sidebarMiddleName : $sidebarFirstName);
            $sidebarInitials = strtoupper(substr($sidebarFirstName, 0, 1) . substr($sidebarInitialsSource, 0, 1));
            $sidebarAvatarUrl = $sidebarProfilePictureUrl ?: ('https://placehold.co/40x40/4a0612/ffffff?text=' . rawurlencode($sidebarInitials ?: 'AD'));

            $view->with([
                'sidebarAvatarUrl' => $sidebarAvatarUrl,
                'sidebarFullName' => $sidebarFullName ?: 'Admin User',
                'sidebarRoleLabel' => $sidebarRoleLabel,
            ]);
        });
    }
}
