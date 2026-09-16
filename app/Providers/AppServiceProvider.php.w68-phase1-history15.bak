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
