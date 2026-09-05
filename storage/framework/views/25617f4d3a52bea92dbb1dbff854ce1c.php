<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title>Developer Control Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?php echo e(asset('css/developer_control_panel.css')); ?>?v=<?php echo e(time()); ?>">
    <script src="<?php echo e(asset('js/developer_control_panel.js')); ?>?v=<?php echo e(time()); ?>" defer></script>
    <script>
        window.devArchiveRoutes = {
            data: "<?php echo e(url('/admin/system-security/archived/data')); ?>",
            restore: "<?php echo e(url('/admin/system-security/archived/restore')); ?>"
        };
        window.developerInventoryRoutes = {
            mismatches: "<?php echo e(route('developer.inventory-control.stock-mismatches')); ?>",
            syncMismatchBase: "<?php echo e(url('/developer/stock-mismatches')); ?>",
            syncAll: "<?php echo e(route('developer.inventory-control.stock-mismatches.sync-all')); ?>",
            autoSyncStatus: "<?php echo e(route('developer.inventory-control.auto-sync-status')); ?>",
            purchaseLedgerMismatches: "<?php echo e(route('developer.inventory-control.purchase-ledger-mismatches')); ?>",
            purchaseLedgerSyncBase: "<?php echo e(url('/developer/purchase-ledger-mismatches')); ?>",
            purchaseLedgerSyncAll: "<?php echo e(route('developer.inventory-control.purchase-ledger-mismatches.sync-all')); ?>",
            purchaseReturnLedgerMismatches: "<?php echo e(route('developer.inventory-control.purchase-return-ledger-mismatches')); ?>",
            purchaseReturnLedgerSyncBase: "<?php echo e(url('/developer/purchase-return-ledger-mismatches')); ?>",
            purchaseReturnLedgerSyncAll: "<?php echo e(route('developer.inventory-control.purchase-return-ledger-mismatches.sync-all')); ?>",
            products: "<?php echo e(route('developer.inventory-control.products')); ?>",
            ledgerEntries: "<?php echo e(route('developer.inventory-control.ledger-entries')); ?>",
            ledgerStore: "<?php echo e(route('developer.inventory-control.ledger-store')); ?>",
            ledgerBase: "<?php echo e(url('/developer/product-ledgers')); ?>"
        };
    </script>
</head>
<body class="developer-shell min-h-screen bg-slate-100 text-slate-900" data-theme="<?php echo e($activeTheme ?? 'default'); ?>" data-theme-now-ms="<?php echo e($themeNowMs ?? now('Asia/Manila')->getTimestampMs()); ?>">
    <?php echo $__env->make('partials.global.w68-loader', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php
        $firstName = is_array($user) ? ($user['User_First_Name'] ?? 'Developer') : ($user->User_First_Name ?? 'Developer');
        $lastName = is_array($user) ? ($user['User_Last_Name'] ?? '') : ($user->User_Last_Name ?? '');
        $fullName = trim($firstName . ' ' . $lastName);
        $themeCountdownMap = collect($themeCountdowns ?? [])->keyBy('key');
    ?>

    <div class="min-h-screen">
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/92 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-lg bg-[#4A0A15] text-[#FFC72C] shadow-sm">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 3l8 4v5c0 5-3.4 8.5-8 9-4.6-.5-8-4-8-9V7l8-4z"></path>
                            <path d="M9 12l2 2 4-5"></path>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-wider text-[#4A0A15]">Developer Control Panel</p>
                        <h1 class="truncate text-xl font-extrabold text-slate-950 sm:text-2xl"><?php echo e($fullName ?: 'Developer'); ?></h1>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" id="importDataBtn" class="inline-flex items-center gap-2 rounded-lg bg-[#4A0A15] px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#3a0810]">
                        <svg class="h-4 w-4 text-[#FFC72C]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <path d="M7 10l5 5 5-5"></path>
                            <path d="M12 15V3"></path>
                        </svg>
                        <span>Import Data</span>
                    </button>
                    <a href="<?php echo e(url('/logout')); ?>" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:border-[#4A0A15] hover:text-[#4A0A15]">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <path d="M16 17l5-5-5-5"></path>
                            <path d="M21 12H9"></path>
                        </svg>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <section class="grid grid-cols-1 gap-5 md:grid-cols-3">
                <article class="developer-stat-card">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Data</span>
                        <strong class="mt-2 block text-3xl font-extrabold tracking-tight text-slate-900"><?php echo e(number_format($stats['total_data'] ?? 0)); ?></strong>
                        <p class="mt-2 text-xs font-semibold text-emerald-600">Records across active modules</p>
                    </div>
                    <div class="developer-stat-icon">
                        <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 3h7v7H3z"></path>
                            <path d="M14 3h7v7h-7z"></path>
                            <path d="M14 14h7v7h-7z"></path>
                            <path d="M3 14h7v7H3z"></path>
                        </svg>
                    </div>
                </article>

                <article class="developer-stat-card">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Database</span>
                        <strong class="mt-2 block text-3xl font-extrabold tracking-tight text-slate-900"><?php echo e(number_format($stats['total_database'] ?? 0)); ?></strong>
                        <p class="mt-2 text-xs font-semibold text-sky-600">Configured core databases</p>
                    </div>
                    <div class="developer-stat-icon">
                        <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <ellipse cx="12" cy="5" rx="8" ry="3"></ellipse>
                            <path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5"></path>
                            <path d="M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"></path>
                        </svg>
                    </div>
                </article>

                <article class="developer-stat-card">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Suspicious Attempts</span>
                        <strong class="mt-2 block text-3xl font-extrabold tracking-tight text-slate-900"><?php echo e(number_format($stats['suspicious_attempts'] ?? 0)); ?></strong>
                        <p class="mt-2 text-xs font-semibold text-rose-600">Failed login or OTP attempts</p>
                    </div>
                    <div class="developer-stat-icon">
                        <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10.3 3.3L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.3a2 2 0 0 0-3.4 0z"></path>
                            <path d="M12 9v4"></path>
                            <path d="M12 17h.01"></path>
                        </svg>
                    </div>
                </article>
            </section>

            <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1.25fr_0.75fr]">
                <div class="developer-panel">
                    <div class="developer-panel-heading">
                        <div>
                            <h2>Module Data</h2>
                            <p><?php echo e(number_format($stats['total_data'] ?? 0)); ?> active records</p>
                        </div>
                        <button type="button" class="developer-secondary-btn" id="openImportSecondary">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 5v14"></path>
                                <path d="M5 12h14"></path>
                            </svg>
                            Import
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        <div class="developer-mini-stat">
                            <span>Logins</span>
                            <strong><?php echo e(number_format($stats['logins'] ?? 0)); ?></strong>
                        </div>
                        <div class="developer-mini-stat">
                            <span>Products</span>
                            <strong id="developerProductCount"><?php echo e(number_format($stats['products'] ?? 0)); ?></strong>
                        </div>
                        <div class="developer-mini-stat">
                            <span>Suppliers</span>
                            <strong><?php echo e(number_format($stats['suppliers'] ?? 0)); ?></strong>
                        </div>
                        <div class="developer-mini-stat">
                            <span>Customers</span>
                            <strong><?php echo e(number_format($stats['customers'] ?? 0)); ?></strong>
                        </div>
                        <div class="developer-mini-stat">
                            <span>Forwarders</span>
                            <strong><?php echo e(number_format($stats['forwarders'] ?? 0)); ?></strong>
                        </div>
                    </div>
                </div>

                <div class="developer-panel">
                    <div class="developer-panel-heading">
                        <div>
                            <h2>Data-ups</h2>
                            <p>6:00 PM <?php echo e($backup['timezone'] ?? 'Asia/Manila'); ?></p>
                        </div>
                        <button
                            type="button"
                            id="runDataUpBtn"
                            class="developer-secondary-btn"
                            data-run-url="<?php echo e(route('developer.data-ups.run')); ?>"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 12a9 9 0 0 1-9 9"></path>
                                <path d="M3 12a9 9 0 0 1 9-9"></path>
                                <path d="M21 3v6h-6"></path>
                                <path d="M3 21v-6h6"></path>
                            </svg>
                            Run Now
                        </button>
                    </div>

                    <div class="space-y-3">
                        <div class="developer-backup-row">
                            <span>Next data-up</span>
                            <strong><?php echo e($backup['next_at'] ?? 'Today 06:00 PM'); ?></strong>
                        </div>
                        <div class="developer-backup-row">
                            <span>Stored backups</span>
                            <strong id="backupCount"><?php echo e(number_format($backup['count'] ?? 0)); ?></strong>
                        </div>
                        <div class="developer-backup-row">
                            <span>Retention</span>
                            <strong><?php echo e($backup['retention_days'] ?? 7); ?> days</strong>
                        </div>
                        <div class="developer-backup-row">
                            <span>Latest</span>
                            <strong id="latestBackup"><?php echo e($backup['latest'] ?? 'None yet'); ?></strong>
                        </div>
                    </div>

                    <div class="developer-backup-divider"></div>

                    <div class="developer-download-grid">
                        <button type="button" class="developer-download-btn" data-download-url="<?php echo e(route('developer.data-ups.download', ['type' => 'masterlist'])); ?>">
                            <span class="developer-download-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="7 10 12 15 17 10"></polyline>
                                    <line x1="12" y1="15" x2="12" y2="3"></line>
                                </svg>
                            </span>
                            Download Masterlist
                        </button>
                        <button type="button" class="developer-download-btn" data-download-url="<?php echo e(route('developer.data-ups.download', ['type' => 'purchase'])); ?>">
                            <span class="developer-download-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="7 10 12 15 17 10"></polyline>
                                    <line x1="12" y1="15" x2="12" y2="3"></line>
                                </svg>
                            </span>
                            Download Purchase
                        </button>
                        <button type="button" class="developer-download-btn" data-download-url="<?php echo e(route('developer.data-ups.download', ['type' => 'sales'])); ?>">
                            <span class="developer-download-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="7 10 12 15 17 10"></polyline>
                                    <line x1="12" y1="15" x2="12" y2="3"></line>
                                </svg>
                            </span>
                            Download Sales
                        </button>
                        <button type="button" class="developer-download-btn" data-download-url="<?php echo e(route('developer.data-ups.download', ['type' => 'accounting'])); ?>">
                            <span class="developer-download-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="7 10 12 15 17 10"></polyline>
                                    <line x1="12" y1="15" x2="12" y2="3"></line>
                                </svg>
                            </span>
                            Download Accounting
                        </button>
                        <button type="button" class="developer-download-btn" data-download-url="<?php echo e(route('developer.data-ups.download', ['type' => 'ledger'])); ?>">
                            <span class="developer-download-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="7 10 12 15 17 10"></polyline>
                                    <line x1="12" y1="15" x2="12" y2="3"></line>
                                </svg>
                            </span>
                            Download Ledger
                        </button>
                        <button type="button" class="developer-download-all-btn" data-download-url="<?php echo e(route('developer.data-ups.download-all')); ?>">
                            <span class="developer-download-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="7 10 12 15 17 10"></polyline>
                                    <line x1="12" y1="15" x2="12" y2="3"></line>
                                </svg>
                            </span>
                            Download All
                        </button>
                    </div>
                </div>
            </section>

            <section class="developer-panel">
                <div class="developer-panel-heading">
                    <div>
                        <h2>System Theme</h2>
                        <p id="themeStatus">Default</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6" data-theme-save-url="<?php echo e(route('developer.system-theme.save')); ?>" data-manual-default-window="<?php echo e($themeState['manual_default_window'] ?? ''); ?>" data-last-auto-window="<?php echo e($themeState['last_auto_window'] ?? ''); ?>">
                    <button type="button" class="developer-theme-option" data-theme-option="default">
                        <span class="theme-swatch theme-default"></span>
                        <span class="developer-theme-copy">
                            <strong>Default</strong>
                            <small>Default System Icons</small>
                            <small>No countdown</small>
                        </span>
                    </button>
                    <?php ($christmasCountdown = $themeCountdownMap->get('christmas')); ?>
                    <button type="button" class="developer-theme-option" data-theme-option="christmas">
                        <span class="theme-swatch theme-christmas"></span>
                        <span class="developer-theme-copy">
                            <strong>Christmas</strong>
                            <small>Event Date: <?php echo e($christmasCountdown['date_label'] ?? 'Dec 25, 2026'); ?></small>
                            <small>Theme Starts: <?php echo e($christmasCountdown['theme_start_label'] ?? 'Dec 10, 2026'); ?></small>
                            <small>Theme Ends: <?php echo e($christmasCountdown['theme_end_label'] ?? 'Dec 29, 2026'); ?></small>
                            <small class="theme-countdown" data-theme-countdown="christmas" data-target="<?php echo e($christmasCountdown['target_iso'] ?? '2026-12-25T00:00:00+08:00'); ?>" data-auto-start="<?php echo e($christmasCountdown['theme_start_iso'] ?? '2026-12-10T00:00:00+08:00'); ?>" data-theme-end="<?php echo e($christmasCountdown['theme_end_iso'] ?? '2026-12-29T23:59:59+08:00'); ?>" data-target-ms="<?php echo e($christmasCountdown['target_ms'] ?? ''); ?>" data-auto-start-ms="<?php echo e($christmasCountdown['theme_start_ms'] ?? ''); ?>" data-theme-end-ms="<?php echo e($christmasCountdown['theme_end_ms'] ?? ''); ?>" data-priority="<?php echo e($christmasCountdown['priority'] ?? 3); ?>" data-window-key="<?php echo e($christmasCountdown['window_key'] ?? 'christmas:2026-12-25'); ?>">Remaining Until Event: --h --m --s</small>
                        </span>
                    </button>
                    <?php ($holyWeekCountdown = $themeCountdownMap->get('holy-week')); ?>
                    <button type="button" class="developer-theme-option" data-theme-option="holy-week">
                        <span class="theme-swatch theme-holy"></span>
                        <span class="developer-theme-copy">
                            <strong>Holy Week</strong>
                            <small>Event Date: <?php echo e($holyWeekCountdown['date_label'] ?? 'Mar 26, 2027'); ?></small>
                            <small>Theme Starts: <?php echo e($holyWeekCountdown['theme_start_label'] ?? 'Mar 11, 2027'); ?></small>
                            <small>Theme Ends: <?php echo e($holyWeekCountdown['theme_end_label'] ?? 'Mar 31, 2027'); ?></small>
                            <small class="theme-countdown" data-theme-countdown="holy-week" data-target="<?php echo e($holyWeekCountdown['target_iso'] ?? '2027-03-26T00:00:00+08:00'); ?>" data-auto-start="<?php echo e($holyWeekCountdown['theme_start_iso'] ?? '2027-03-11T00:00:00+08:00'); ?>" data-theme-end="<?php echo e($holyWeekCountdown['theme_end_iso'] ?? '2027-03-31T23:59:59+08:00'); ?>" data-target-ms="<?php echo e($holyWeekCountdown['target_ms'] ?? ''); ?>" data-auto-start-ms="<?php echo e($holyWeekCountdown['theme_start_ms'] ?? ''); ?>" data-theme-end-ms="<?php echo e($holyWeekCountdown['theme_end_ms'] ?? ''); ?>" data-priority="<?php echo e($holyWeekCountdown['priority'] ?? 4); ?>" data-window-key="<?php echo e($holyWeekCountdown['window_key'] ?? 'holy-week:2027-03-26'); ?>">Remaining Until Event: --h --m --s</small>
                        </span>
                    </button>
                    <?php ($halloweenCountdown = $themeCountdownMap->get('halloween')); ?>
                    <button type="button" class="developer-theme-option" data-theme-option="halloween">
                        <span class="theme-swatch theme-halloween"></span>
                        <span class="developer-theme-copy">
                            <strong>Halloween</strong>
                            <small>Event Date: <?php echo e($halloweenCountdown['date_label'] ?? 'Oct 31, 2026'); ?></small>
                            <small>Theme Starts: <?php echo e($halloweenCountdown['theme_start_label'] ?? 'Oct 16, 2026'); ?></small>
                            <small>Theme Ends: <?php echo e($halloweenCountdown['theme_end_label'] ?? 'Nov 05, 2026'); ?></small>
                            <small class="theme-countdown" data-theme-countdown="halloween" data-target="<?php echo e($halloweenCountdown['target_iso'] ?? '2026-10-31T00:00:00+08:00'); ?>" data-auto-start="<?php echo e($halloweenCountdown['theme_start_iso'] ?? '2026-10-16T00:00:00+08:00'); ?>" data-theme-end="<?php echo e($halloweenCountdown['theme_end_iso'] ?? '2026-11-05T23:59:59+08:00'); ?>" data-target-ms="<?php echo e($halloweenCountdown['target_ms'] ?? ''); ?>" data-auto-start-ms="<?php echo e($halloweenCountdown['theme_start_ms'] ?? ''); ?>" data-theme-end-ms="<?php echo e($halloweenCountdown['theme_end_ms'] ?? ''); ?>" data-priority="<?php echo e($halloweenCountdown['priority'] ?? 5); ?>" data-window-key="<?php echo e($halloweenCountdown['window_key'] ?? 'halloween:2026-10-31'); ?>">Remaining Until Event: --h --m --s</small>
                        </span>
                    </button>
                    <?php ($newYearCountdown = $themeCountdownMap->get('new-year')); ?>
                    <button type="button" class="developer-theme-option" data-theme-option="new-year">
                        <span class="theme-swatch theme-new-year"></span>
                        <span class="developer-theme-copy">
                            <strong>New Year</strong>
                            <small>Event Date: <?php echo e($newYearCountdown['date_label'] ?? 'Jan 01, 2027'); ?></small>
                            <small>Theme Starts: <?php echo e($newYearCountdown['theme_start_label'] ?? 'Dec 30, 2026'); ?></small>
                            <small>Theme Ends: <?php echo e($newYearCountdown['theme_end_label'] ?? 'Jan 10, 2027'); ?></small>
                            <small class="theme-countdown" data-theme-countdown="new-year" data-target="<?php echo e($newYearCountdown['target_iso'] ?? '2027-01-01T00:00:00+08:00'); ?>" data-auto-start="<?php echo e($newYearCountdown['theme_start_iso'] ?? '2026-12-30T00:00:00+08:00'); ?>" data-theme-end="<?php echo e($newYearCountdown['theme_end_iso'] ?? '2027-01-10T23:59:59+08:00'); ?>" data-target-ms="<?php echo e($newYearCountdown['target_ms'] ?? ''); ?>" data-auto-start-ms="<?php echo e($newYearCountdown['theme_start_ms'] ?? ''); ?>" data-theme-end-ms="<?php echo e($newYearCountdown['theme_end_ms'] ?? ''); ?>" data-priority="<?php echo e($newYearCountdown['priority'] ?? 1); ?>" data-window-key="<?php echo e($newYearCountdown['window_key'] ?? 'new-year:2027-01-01'); ?>">Remaining Until Event: --h --m --s</small>
                        </span>
                    </button>
                    <?php ($chineseNewYearCountdown = $themeCountdownMap->get('chinese-new-year')); ?>
                    <button type="button" class="developer-theme-option" data-theme-option="chinese-new-year">
                        <span class="theme-swatch theme-chinese-new-year"></span>
                        <span class="developer-theme-copy">
                            <strong>Chinese New Year</strong>
                            <small>Event Date: <?php echo e($chineseNewYearCountdown['date_label'] ?? 'Feb 06, 2027'); ?></small>
                            <small>Theme Starts: <?php echo e($chineseNewYearCountdown['theme_start_label'] ?? 'Jan 22, 2027'); ?></small>
                            <small>Theme Ends: <?php echo e($chineseNewYearCountdown['theme_end_label'] ?? 'Feb 11, 2027'); ?></small>
                            <small class="theme-countdown" data-theme-countdown="chinese-new-year" data-target="<?php echo e($chineseNewYearCountdown['target_iso'] ?? '2027-02-06T00:00:00+08:00'); ?>" data-auto-start="<?php echo e($chineseNewYearCountdown['theme_start_iso'] ?? '2027-01-22T00:00:00+08:00'); ?>" data-theme-end="<?php echo e($chineseNewYearCountdown['theme_end_iso'] ?? '2027-02-11T23:59:59+08:00'); ?>" data-target-ms="<?php echo e($chineseNewYearCountdown['target_ms'] ?? ''); ?>" data-auto-start-ms="<?php echo e($chineseNewYearCountdown['theme_start_ms'] ?? ''); ?>" data-theme-end-ms="<?php echo e($chineseNewYearCountdown['theme_end_ms'] ?? ''); ?>" data-priority="<?php echo e($chineseNewYearCountdown['priority'] ?? 2); ?>" data-window-key="<?php echo e($chineseNewYearCountdown['window_key'] ?? 'chinese-new-year:2027-02-06'); ?>">Remaining Until Event: --h --m --s</small>
                        </span>
                    </button>
                </div>
            </section>

            <!-- Online Report Ledger Sync Stats -->
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500">Online Report Ledger Sync</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Product ledger sync status for online reports</p>
                    </div>
                    <button onclick="refreshOnlineReportSyncStats()" class="inline-flex items-center gap-1.5 rounded-lg bg-[#4A0A15] px-3 py-1.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#3a0810]">
                        <svg class="h-3.5 w-3.5 text-[#FFC72C]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 4v6h6"></path>
                            <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
                        </svg>
                        Refresh
                    </button>
                </div>
                <div id="online-report-sync-stats" class="grid grid-cols-2 md:grid-cols-5 gap-3">
                    <div class="rounded-xl bg-slate-50 p-3 text-center">
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Total Reports</p>
                        <p id="ors-total-reports" class="text-xl font-extrabold text-slate-800 mt-1">-</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3 text-center">
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Total Items</p>
                        <p id="ors-total-items" class="text-xl font-extrabold text-slate-800 mt-1">-</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3 text-center">
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Synced</p>
                        <p id="ors-synced-items" class="text-xl font-extrabold text-green-600 mt-1">-</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3 text-center">
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Missing</p>
                        <p id="ors-missing-items" class="text-xl font-extrabold text-red-600 mt-1">-</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3 text-center">
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Last Sync</p>
                        <p id="ors-last-sync" class="text-xs font-bold text-slate-600 mt-1">-</p>
                    </div>
                </div>

                <!-- Unsynced Reports List -->
                <div id="ors-unsynced-container" class="mt-4 hidden">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">Unsynced Reports</h4>
                        <span id="ors-unsynced-count" class="text-xs font-semibold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full">0</span>
                    </div>
                    <div id="ors-unsynced-list" class="max-h-48 overflow-y-auto space-y-1 text-xs"></div>
                </div>

                <!-- Sync Progress Bar -->
                <div id="ors-progress-container" class="mt-4 hidden">
                    <div class="flex items-center justify-between mb-1">
                        <span id="ors-progress-label" class="text-xs font-semibold text-slate-600">Syncing...</span>
                        <span id="ors-progress-percent" class="text-xs font-bold text-slate-800">0%</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                        <div id="ors-progress-bar" class="bg-emerald-500 h-2.5 rounded-full transition-all duration-300" style="width:0%"></div>
                    </div>
                    <div class="flex justify-between mt-1">
                        <span id="ors-elapsed-time" class="text-xs text-slate-400">Elapsed: 0s</span>
                        <span id="ors-remaining-time" class="text-xs text-slate-400">Remaining: --</span>
                    </div>
                    <div id="ors-current-status" class="mt-1 text-xs text-slate-500"></div>
                </div>

                <div class="mt-3">
                    <button id="ors-sync-btn" onclick="syncAllUnsyncedReports()" class="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-800 transition hover:bg-amber-100">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M23 4v6h-6"></path>
                            <path d="M1 20v-6h6"></path>
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                        </svg>
                        Sync Unsynced
                    </button>
                </div>
            </div>


            <section class="developer-panel" id="purchase-ledger-sync-panel">
                <div class="developer-panel-heading">
                    <div>
                        <h2>Purchase Ledger Synchronizer</h2>
                        <p><span id="purchaseLedgerMissingCount">0</span> Purchase Order item movements are missing from Supplier Ledger</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="developer-secondary-btn" id="refreshPurchaseLedgerBtn">Refresh</button>
                        <button type="button" class="developer-primary-btn" id="syncAllPurchaseLedgerBtn">Sync All</button>
                    </div>
                </div>
                <div class="developer-table-scroll">
                    <table class="developer-table w-full" style="min-width: 1180px;">
                        <thead>
                            <tr>
                                <th>Sync</th>
                                <th>Supplier Name</th>
                                <th>Missing Supplier Invoice</th>
                                <th>Product Code</th>
                                <th>Part Number</th>
                                <th>Unit</th>
                                <th class="text-right">Cost</th>
                                <th>Date</th>
                            </tr>
                            <tr class="developer-filter-row">
                                <th></th>
                                <th><input id="purchaseLedgerFilterSupplier" class="developer-column-filter" placeholder="Search supplier"></th>
                                <th><input id="purchaseLedgerFilterInvoice" class="developer-column-filter" placeholder="Search invoice"></th>
                                <th><input id="purchaseLedgerFilterProductCode" class="developer-column-filter" placeholder="Search product code"></th>
                                <th><input id="purchaseLedgerFilterPartNumber" class="developer-column-filter" placeholder="Search part number"></th>
                                <th><input id="purchaseLedgerFilterUnit" class="developer-column-filter" placeholder="Search unit"></th>
                                <th><input id="purchaseLedgerFilterCost" class="developer-column-filter" placeholder="Search cost"></th>
                                <th><input id="purchaseLedgerFilterDate" class="developer-column-filter" placeholder="YYYY-MM-DD"></th>
                            </tr>
                        </thead>
                        <tbody id="purchaseLedgerTableBody">
                            <tr><td colspan="8" class="developer-empty-cell">Loading missing Purchase Ledger rows...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="developer-pagination-row">
                    <span id="purchaseLedgerPageInfo">Page 1 of 1 · 50 per page</span>
                    <div class="flex gap-2">
                        <button type="button" class="developer-secondary-btn" id="purchaseLedgerPrevBtn">Previous</button>
                        <button type="button" class="developer-secondary-btn" id="purchaseLedgerNextBtn">Next</button>
                    </div>
                </div>
                <p class="developer-warning-copy">The synchronizer compares <strong>core4_purchase.purchase_orders / purchase_order_items</strong> with <strong>core4_ledger.supplier_ledgers / supplier_ledger_items</strong>. Sync uses the Purchase Order as the source of truth, adopts matching legacy unlinked rows when possible, and preserves the original PO item timestamp for Cost Date.</p>
            </section>


            <section class="developer-panel" id="purchase-return-ledger-sync-panel">
                <div class="developer-panel-heading">
                    <div>
                        <h2>Purchase Return Synchronizer</h2>
                        <p><span id="purchaseReturnLedgerMissingCount">0</span> Purchase Return item movement(s) are missing from Product Ledger in the selected date range</p>
                    </div>
                    <div class="flex flex-wrap items-end gap-2">
                        <div>
                            <label class="developer-form-label" for="purchaseReturnSyncDateFrom">From Date</label>
                            <input id="purchaseReturnSyncDateFrom" type="date" class="developer-form-control developer-compact-control">
                        </div>
                        <div>
                            <label class="developer-form-label" for="purchaseReturnSyncDateTo">To Date</label>
                            <input id="purchaseReturnSyncDateTo" type="date" class="developer-form-control developer-compact-control">
                        </div>
                        <div>
                            <label class="developer-form-label" for="purchaseReturnSyncMode">Sync As</label>
                            <select id="purchaseReturnSyncMode" class="developer-form-control developer-compact-control">
                                <option value="OUT">OUT</option>
                                <option value="JUNK">JUNK</option>
                            </select>
                        </div>
                        <button type="button" class="developer-secondary-btn" id="refreshPurchaseReturnLedgerBtn">Refresh</button>
                        <button type="button" class="developer-primary-btn" id="syncAllPurchaseReturnLedgerBtn">Sync All Missing</button>
                    </div>
                </div>

                <div class="developer-table-scroll">
                    <table class="developer-table w-full" style="min-width: 1180px;">
                        <thead>
                            <tr>
                                <th>Sync</th>
                                <th>Return No.</th>
                                <th>Supplier</th>
                                <th>Product Code</th>
                                <th>Part Number</th>
                                <th class="text-right">Return QTY</th>
                                <th>Unit</th>
                                <th>Return Date</th>
                            </tr>
                        </thead>
                        <tbody id="purchaseReturnLedgerTableBody">
                            <tr><td colspan="8" class="developer-empty-cell">Choose a date range to load missing Purchase Returns...</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="developer-pagination-row">
                    <span id="purchaseReturnLedgerPageInfo">Page 1 of 1 · 50 per page</span>
                    <div class="flex gap-2">
                        <button type="button" class="developer-secondary-btn" id="purchaseReturnLedgerPrevBtn">Previous</button>
                        <button type="button" class="developer-secondary-btn" id="purchaseReturnLedgerNextBtn">Next</button>
                    </div>
                </div>

                <p class="developer-warning-copy">
                    This synchronizer reads the original <strong>core4_purchase.purchase_returns.date</strong> and writes that exact date to <strong>core4_ledger.product_ledgers.date</strong>. If a Purchase Return is dated <strong>01-08-2026</strong>, the synchronized Product Ledger transaction remains <strong>01-08-2026</strong>, not today's date. Choose <strong>OUT</strong> to deduct the full Return QTY from Balance Stock, or <strong>JUNK</strong> to record the full Return QTY in the JUNK column without changing Balance Stock. Only missing Purchase Return items are shown and synchronized.
                </p>
            </section>


            <section class="developer-panel" id="stock-mismatch-panel">
                <div class="developer-panel-heading">
                    <div>
                        <h2>Incorrect Balance Stock Sync</h2>
                        <p><span id="stockMismatchCount">0</span> products differ from the latest Product Ledger balance</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <input id="stockMismatchSearch" type="search" class="developer-form-control developer-compact-control" placeholder="Search item code or part number">
                        <button type="button" class="developer-secondary-btn" id="refreshStockMismatchesBtn">Refresh</button>
                        <button type="button" class="developer-primary-btn" id="syncAllStockBtn">Sync All</button>
                    </div>
                </div>
                <div class="developer-table-scroll">
                    <table class="developer-table w-full">
                        <thead>
                            <tr>
                                <th>Item Code</th>
                                <th>Part Number</th>
                                <th class="text-right">Product Inventory</th>
                                <th class="text-right">Ledger Inventory</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="stockMismatchTableBody">
                            <tr><td colspan="5" class="developer-empty-cell">Loading stock mismatches...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="developer-pagination-row">
                    <span id="stockMismatchPageInfo">Page 1 of 1</span>
                    <div class="flex gap-2">
                        <button type="button" class="developer-secondary-btn" id="stockMismatchPrevBtn">Previous</button>
                        <button type="button" class="developer-secondary-btn" id="stockMismatchNextBtn">Next</button>
                    </div>
                </div>
                <div class="developer-auto-sync-status" id="autoSyncStatusCard">
                    <div>
                        <span class="developer-status-dot" id="autoSyncStatusDot"></span>
                        <strong id="autoSyncStatusLabel">Checking real-time auto sync...</strong>
                    </div>
                    <p id="autoSyncStatusDetails">Product inventory should update immediately after Product Ledger changes commit.</p>
                    <div class="developer-auto-sync-meta">
                        <span>Pending mismatches: <strong id="autoSyncMismatchCount">—</strong></span>
                        <span>Latest ledger activity: <strong id="autoSyncLatestActivity">—</strong></span>
                    </div>
                </div>
                <p class="developer-warning-copy">Manual Sync and Sync All copy the latest <strong>product_ledgers.balance_stock</strong> into <strong>products.on_hand</strong>. The five-minute polling schedule is removed.</p>
            </section>

            <section class="developer-panel" id="product-ledger-editor-panel">
                <div class="developer-panel-heading">
                    <div>
                        <h2>Product Ledger Editor</h2>
                        <p>Developer-only manual Sales and Purchase ledger maintenance</p>
                    </div>
                    <button type="button" class="developer-primary-btn" id="openLedgerEntryModalBtn" disabled>Add Entry</button>
                </div>

                <div class="developer-ledger-filters developer-ledger-filters-modal-selector">
                    <div class="developer-selected-product-card">
                        <span class="developer-form-label">Selected Product</span>
                        <strong id="selectedProductCode">No product selected</strong>
                        <small id="selectedProductDetails">Use the Product Selector modal.</small>
                        <input type="hidden" id="ledgerProductId">
                    </div>
                    <div>
                        <label class="developer-form-label">Product Selector</label>
                        <button type="button" class="developer-secondary-btn w-full justify-center" id="openProductSelectorBtn">Select Product</button>
                    </div>
                    <div>
                        <label class="developer-form-label" for="ledgerActionSelect">Action</label>
                        <select id="ledgerActionSelect" class="developer-form-control">
                            <option value="sales">Sales</option>
                            <option value="purchase">Purchase</option>
                        </select>
                    </div>
                    <div class="developer-balance-card">
                        <span>Latest Ledger Balance</span>
                        <strong id="selectedProductLedgerBalance">—</strong>
                    </div>
                </div>

                <div class="developer-table-scroll mt-4">
                    <table class="developer-table developer-ledger-table w-full">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date</th>
                                <th>Entity Name</th>
                                <th>Transaction No.</th>
                                <th>Reference No.</th>
                                <th id="ledgerQuantityHeading">Qty Out</th>
                                <th>Balance Stock</th>
                                <th>OUM</th>
                                <th id="ledgerPriceHeading">Price</th>
                                <th>Online / Local</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="productLedgerTableBody">
                            <tr><td colspan="12" class="developer-empty-cell">Select a product to load its ledger entries.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="developer-pagination-row">
                    <span id="productLedgerPageInfo">Page 1 of 1</span>
                    <div class="flex gap-2">
                        <button type="button" class="developer-secondary-btn" id="productLedgerPrevBtn" disabled>Previous</button>
                        <button type="button" class="developer-secondary-btn" id="productLedgerNextBtn" disabled>Next</button>
                    </div>
                </div>
                <p class="developer-warning-copy">Adding or editing an entry honors the entered Balance Stock and recalculates the product's ledger chain. Deleting an entry is permanent.</p>
            </section>

            <section class="developer-panel" id="archive-panel">
                <div class="developer-panel-heading">
                    <div>
                        <h2>Archived Records</h2>
                        <p>View and restore archived data</p>
                    </div>
                    <button type="button" class="developer-secondary-btn" id="loadArchiveBtn">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                            <line x1="12" y1="22.08" x2="12" y2="12"></line>
                        </svg>
                        Load Archives
                    </button>
                </div>
                <div id="archive-table-wrapper" class="max-h-80 overflow-auto rounded-lg border border-slate-200">
                    <table class="developer-table w-full" id="archive-table">
                        <thead>
                            <tr>
                                <th>Module</th>
                                <th>Data Name</th>
                                <th>Archived At</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="archive-tbody">
                            <tr><td colspan="4" class="text-center text-sm text-slate-400 py-8">Click "Load Archives" to fetch data.</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 text-[11px] text-slate-400 italic">Inventory module records are not restorable.</p>
            </section>
        </main>
    </div>

    <div id="importModal" class="developer-modal hidden" aria-hidden="true">
        <div class="developer-modal-panel">
            <div class="developer-modal-header">
                <div>
                    <h2>Import Data</h2>
                    <p>Excel source file</p>
                </div>
                <button type="button" class="developer-icon-btn" data-modal-close="importModal" aria-label="Close import modal">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6L6 18"></path>
                        <path d="M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form id="importForm" class="space-y-5" method="POST" enctype="multipart/form-data" data-import-url="<?php echo e(route('developer.import')); ?>">
                <div>
                    <label for="importTarget" class="developer-form-label">Import for</label>
                    <select id="importTarget" name="target" class="developer-form-control" required>
                        <option value="product-list">Product List</option>
                        <option value="supplier-list">Supplier List</option>
                        <option value="customer-list">Customer List</option>
                        <option value="forwarder-list">Forwarder List</option>
                        <option value="coming-soon">Coming Soon</option>
                    </select>
                </div>

                <div>
                    <label for="excelFile" class="developer-form-label">Choose from Excel</label>
                    <input id="excelFile" name="excel_file" type="file" class="developer-form-control" accept=".xlsx" required>
                </div>

                <div id="productImportOptions" class="developer-import-options">
                    <div class="rounded-lg border border-slate-200 bg-slate-50/50 p-3">
                        <span class="mb-2 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Expected Excel Columns</span>
                        <div class="flex flex-wrap gap-1.5">
                            <span class="rounded bg-white px-2 py-1 text-[10px] font-bold text-slate-700 shadow-sm">ITEMCODE</span>
                            <span class="rounded bg-white px-2 py-1 text-[10px] font-bold text-slate-700 shadow-sm">PART NUMBER</span>
                            <span class="rounded bg-white px-2 py-1 text-[10px] font-bold text-slate-700 shadow-sm">DESCRIPTION</span>
                            <span class="rounded bg-white px-2 py-1 text-[10px] font-bold text-slate-700 shadow-sm">APPLICATION</span>
                            <span class="rounded bg-[#4A0A15] px-2 py-1 text-[10px] font-bold text-[#FFC72C] shadow-sm">POSITION</span>
                            <span class="rounded bg-white px-2 py-1 text-[10px] font-bold text-slate-700 shadow-sm">CATEGORY</span>
                            <span class="rounded bg-white px-2 py-1 text-[10px] font-bold text-slate-700 shadow-sm">PRICE</span>
                        </div>
                        <p class="mt-1.5 text-[9px] text-slate-400 italic">POSITION is optional. Files without it will import with an empty value.</p>
                    </div>

                    <div>
                        <span class="developer-form-label">Product List Type</span>
                        <div class="developer-radio-grid">
                            <label class="developer-radio-option">
                                <input type="radio" name="product_age" value="new" checked>
                                <span>
                                    <strong>New</strong>
                                    <small>Set status to Newly and use today's date.</small>
                                </span>
                            </label>
                            <label class="developer-radio-option">
                                <input type="radio" name="product_age" value="old">
                                <span>
                                    <strong>Old</strong>
                                    <small>Use the date selected below.</small>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label for="importDate" class="developer-form-label">Date</label>
                        <input id="importDate" name="import_date" type="date" class="developer-form-control" disabled>
                        <p id="importDateHint" class="developer-form-hint">Newly added products automatically use today's date.</p>
                    </div>
                </div>

                <div class="developer-modal-actions">
                    <button type="button" class="developer-secondary-btn" data-modal-close="importModal">Cancel</button>
                    <button type="submit" class="developer-primary-btn">Import</button>
                </div>
            </form>
        </div>
    </div>

    <div id="confirmModal" class="developer-modal hidden" aria-hidden="true">
        <div class="developer-modal-panel developer-modal-sm">
            <div class="developer-modal-icon">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 3v12"></path>
                    <path d="M7 10l5 5 5-5"></path>
                    <path d="M5 21h14"></path>
                </svg>
            </div>
            <h2 class="text-center text-lg font-extrabold text-slate-950">Confirm Import</h2>
            <p id="confirmImportText" class="mt-2 text-center text-sm text-slate-500">Proceed with this import?</p>
            <div class="mt-6 grid grid-cols-2 gap-3">
                <button type="button" class="developer-secondary-btn justify-center" id="confirmNoBtn">No</button>
                <button type="button" class="developer-primary-btn justify-center" id="confirmYesBtn">Yes</button>
            </div>
        </div>
    </div>

    <div id="successModal" class="developer-modal hidden" aria-hidden="true">
        <div class="developer-modal-panel developer-modal-sm">
            <div class="developer-modal-icon developer-modal-success">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 6L9 17l-5-5"></path>
                </svg>
            </div>
            <h2 class="text-center text-lg font-extrabold text-slate-950">Success</h2>
            <p id="successText" class="mt-2 text-center text-sm text-slate-500">Data imported successfully.</p>
            <button type="button" class="developer-primary-btn mt-6 w-full justify-center" data-modal-close="successModal">Done</button>
        </div>
    </div>

    <div id="archiveConfirmModal" class="developer-modal hidden" aria-hidden="true">
        <div class="developer-modal-panel developer-modal-sm">
            <div class="developer-modal-icon">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 3v12"></path>
                    <path d="M7 10l5 5 5-5"></path>
                    <path d="M5 21h14"></path>
                </svg>
            </div>
            <h2 class="text-center text-lg font-extrabold text-slate-950">Restore Record</h2>
            <p id="archiveConfirmText" class="mt-2 text-center text-sm text-slate-500">Restore this archived record?</p>
            <div class="mt-6 grid grid-cols-2 gap-3">
                <button type="button" class="developer-secondary-btn justify-center" id="archiveRestoreNoBtn">No</button>
                <button type="button" class="developer-primary-btn justify-center" id="archiveRestoreYesBtn">Yes</button>
            </div>
        </div>
    </div>

    <div id="archiveSuccessModal" class="developer-modal hidden" aria-hidden="true">
        <div class="developer-modal-panel developer-modal-sm">
            <div class="developer-modal-icon developer-modal-success">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 6L9 17l-5-5"></path>
                </svg>
            </div>
            <h2 class="text-center text-lg font-extrabold text-slate-950">Restored</h2>
            <p id="archiveSuccessText" class="mt-2 text-center text-sm text-slate-500">Record restored successfully.</p>
            <button type="button" class="developer-primary-btn mt-6 w-full justify-center" id="archiveSuccessDoneBtn">Done</button>
        </div>
    </div>

    <div id="orsSyncSuccessModal" class="developer-modal hidden" aria-hidden="true">
        <div class="developer-modal-panel developer-modal-sm">
            <div class="developer-modal-icon developer-modal-success">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 6L9 17l-5-5"></path>
                </svg>
            </div>
            <h2 class="text-center text-lg font-extrabold text-slate-950">Sync Complete</h2>
            <p id="orsSyncSuccessText" class="mt-2 text-center text-sm text-slate-500">All unsynced reports synced successfully.</p>
            <button type="button" class="developer-primary-btn mt-6 w-full justify-center" data-modal-close="orsSyncSuccessModal">Done</button>
        </div>
    </div>


    <div id="syncAllStockConfirmModal" class="developer-modal hidden" aria-hidden="true">
        <div class="developer-modal-panel developer-modal-sm">
            <div class="developer-modal-icon">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"></path><path d="M1 20v-6h6"></path><path d="M3.5 9a9 9 0 0 1 14.9-3.4L23 10M1 14l4.6 4.4A9 9 0 0 0 20.5 15"></path></svg>
            </div>
            <h2 class="text-center text-lg font-extrabold text-slate-950">Confirm Sync All</h2>
            <p class="mt-2 text-center text-sm text-slate-500">Synchronize every product inventory value from the latest Product Ledger balance?</p>
            <div class="mt-6 grid grid-cols-2 gap-3">
                <button type="button" class="developer-secondary-btn justify-center" id="syncAllStockCancelBtn">Cancel</button>
                <button type="button" class="developer-primary-btn justify-center" id="syncAllStockConfirmBtn">Sync All</button>
            </div>
        </div>
    </div>

    <div id="syncAllPurchaseLedgerConfirmModal" class="developer-modal hidden" aria-hidden="true">
        <div class="developer-modal-panel developer-modal-sm">
            <div class="developer-modal-icon">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"></path><path d="M1 20v-6h6"></path><path d="M3.5 9a9 9 0 0 1 14.9-3.4L23 10M1 14l4.6 4.4A9 9 0 0 0 20.5 15"></path></svg>
            </div>
            <h2 class="text-center text-lg font-extrabold text-slate-950">Confirm Purchase Ledger Sync All</h2>
            <p class="mt-2 text-center text-sm text-slate-500">Synchronize every currently missing received Purchase Order into Supplier Ledger? Existing linked ledger rows will be updated/adopted instead of duplicated.</p>
            <div class="mt-6 grid grid-cols-2 gap-3">
                <button type="button" class="developer-secondary-btn justify-center" id="syncAllPurchaseLedgerCancelBtn">Cancel</button>
                <button type="button" class="developer-primary-btn justify-center" id="syncAllPurchaseLedgerConfirmBtn">Sync All</button>
            </div>
        </div>
    </div>

    <div id="purchaseLedgerSyncSuccessModal" class="developer-modal hidden" aria-hidden="true">
        <div class="developer-modal-panel developer-modal-sm">
            <div class="developer-modal-icon developer-modal-success">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"></path></svg>
            </div>
            <h2 class="text-center text-lg font-extrabold text-slate-950">Purchase Ledger Sync Complete</h2>
            <p id="purchaseLedgerSyncSuccessText" class="mt-2 text-center text-sm text-slate-500">All missing Purchase Ledger rows synchronized.</p>
            <button type="button" class="developer-primary-btn mt-6 w-full justify-center" data-modal-close="purchaseLedgerSyncSuccessModal">Done</button>
        </div>
    </div>

    <div id="syncAllPurchaseReturnLedgerConfirmModal" class="developer-modal hidden" aria-hidden="true">
        <div class="developer-modal-panel developer-modal-sm">
            <div class="developer-modal-icon">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"></path><path d="M1 20v-6h6"></path><path d="M3.5 9a9 9 0 0 1 14.9-3.4L23 10M1 14l4.6 4.4A9 9 0 0 0 20.5 15"></path></svg>
            </div>
            <h2 class="text-center text-lg font-extrabold text-slate-950">Confirm Purchase Return Sync</h2>
            <p id="syncAllPurchaseReturnLedgerConfirmText" class="mt-2 text-center text-sm text-slate-500">Synchronize all missing Purchase Return items in this date range?</p>
            <div class="mt-6 grid grid-cols-2 gap-3">
                <button type="button" class="developer-secondary-btn justify-center" id="syncAllPurchaseReturnLedgerCancelBtn">Cancel</button>
                <button type="button" class="developer-primary-btn justify-center" id="syncAllPurchaseReturnLedgerConfirmBtn">Sync All Missing</button>
            </div>
        </div>
    </div>

    <div id="purchaseReturnLedgerSyncSuccessModal" class="developer-modal hidden" aria-hidden="true">
        <div class="developer-modal-panel developer-modal-sm">
            <div class="developer-modal-icon developer-modal-success">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"></path></svg>
            </div>
            <h2 class="text-center text-lg font-extrabold text-slate-950">Purchase Return Sync Complete</h2>
            <p id="purchaseReturnLedgerSyncSuccessText" class="mt-2 text-center text-sm text-slate-500">Missing Purchase Return rows synchronized.</p>
            <button type="button" class="developer-primary-btn mt-6 w-full justify-center" data-modal-close="purchaseReturnLedgerSyncSuccessModal">Done</button>
        </div>
    </div>

    <div id="productSelectorModal" class="developer-modal hidden" aria-hidden="true">
        <div class="developer-modal-panel developer-modal-xl">
            <div class="developer-modal-header">
                <div>
                    <h2>Product Selector</h2>
                    <p>Search all products before pagination. Twenty-five products are shown per page.</p>
                </div>
                <button type="button" class="developer-icon-btn" data-modal-close="productSelectorModal" aria-label="Close product selector">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18"></path><path d="M6 6l12 12"></path></svg>
                </button>
            </div>
            <div class="developer-table-scroll developer-product-selector-scroll">
                <table class="developer-table developer-product-selector-table w-full">
                    <thead>
                        <tr>
                            <th>Product Code</th>
                            <th>Part Number</th>
                            <th>Description</th>
                            <th>Application</th>
                            <th>Action</th>
                        </tr>
                        <tr class="developer-filter-row">
                            <th><input id="productFilterCode" class="developer-column-filter" placeholder="Search code"></th>
                            <th><input id="productFilterPartNumber" class="developer-column-filter" placeholder="Search part number"></th>
                            <th><input id="productFilterDescription" class="developer-column-filter" placeholder="Search description"></th>
                            <th><input id="productFilterApplication" class="developer-column-filter" placeholder="Search application"></th>
                            <th><button type="button" class="developer-secondary-btn developer-filter-clear-btn" id="clearProductFiltersBtn">Clear</button></th>
                        </tr>
                    </thead>
                    <tbody id="productSelectorTableBody">
                        <tr><td colspan="5" class="developer-empty-cell">Loading products...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="developer-pagination-row">
                <span id="productSelectorPageInfo">Page 1 of 1</span>
                <div class="flex gap-2">
                    <button type="button" class="developer-secondary-btn" id="productSelectorPrevBtn">Previous</button>
                    <button type="button" class="developer-secondary-btn" id="productSelectorNextBtn">Next</button>
                </div>
            </div>
        </div>
    </div>

    <div id="stockSyncConfirmModal" class="developer-modal hidden" aria-hidden="true">
        <div class="developer-modal-panel developer-modal-sm">
            <div class="developer-modal-icon">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"></path><path d="M1 20v-6h6"></path><path d="M3.5 9a9 9 0 0 1 14.9-3.4L23 10M1 14l4.6 4.4A9 9 0 0 0 20.5 15"></path></svg>
            </div>
            <h2 class="text-center text-lg font-extrabold text-slate-950">Confirm Manual Sync</h2>
            <p id="stockSyncConfirmText" class="mt-2 text-center text-sm text-slate-500">Synchronize this product?</p>
            <div class="mt-6 grid grid-cols-2 gap-3">
                <button type="button" class="developer-secondary-btn justify-center" id="stockSyncCancelBtn">Cancel</button>
                <button type="button" class="developer-primary-btn justify-center" id="stockSyncConfirmBtn">Sync Now</button>
            </div>
        </div>
    </div>

    <div id="stockSyncSuccessModal" class="developer-modal hidden" aria-hidden="true">
        <div class="developer-modal-panel developer-modal-sm">
            <div class="developer-modal-icon developer-modal-success">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"></path></svg>
            </div>
            <h2 class="text-center text-lg font-extrabold text-slate-950">Sync Successful</h2>
            <p id="stockSyncSuccessText" class="mt-2 text-center text-sm text-slate-500">Product inventory synchronized.</p>
            <button type="button" class="developer-primary-btn mt-6 w-full justify-center" data-modal-close="stockSyncSuccessModal">Done</button>
        </div>
    </div>

    <div id="ledgerEntryModal" class="developer-modal hidden" aria-hidden="true">
        <div class="developer-modal-panel developer-modal-lg">
            <div class="developer-modal-header">
                <div>
                    <h2 id="ledgerEntryModalTitle">Add Product Ledger Entry</h2>
                    <p id="ledgerEntryProductText">Select a product first.</p>
                </div>
                <button type="button" class="developer-icon-btn" data-modal-close="ledgerEntryModal" aria-label="Close ledger modal">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18"></path><path d="M6 6l12 12"></path></svg>
                </button>
            </div>
            <form id="ledgerEntryForm" class="space-y-4">
                <input type="hidden" id="ledgerEntryId">
                <div class="developer-form-grid">
                    <div>
                        <label class="developer-form-label">ID</label>
                        <input id="ledgerEntryIdDisplay" class="developer-form-control" value="Auto Increment" disabled>
                    </div>
                    <div>
                        <label class="developer-form-label">Transaction Type</label>
                        <input id="ledgerTransactionType" class="developer-form-control" value="OUT" disabled>
                    </div>
                    <div>
                        <label class="developer-form-label" for="ledgerEntryDate">Date</label>
                        <input id="ledgerEntryDate" type="date" class="developer-form-control" required>
                    </div>
                    <div>
                        <label class="developer-form-label" for="ledgerEntryCreatedAt">Created At</label>
                        <input id="ledgerEntryCreatedAt" type="datetime-local" class="developer-form-control" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="developer-form-label" for="ledgerEntityName">Entity Name</label>
                        <input id="ledgerEntityName" class="developer-form-control" maxlength="255" required>
                    </div>
                    <div>
                        <label class="developer-form-label" for="ledgerTransactionNumber">Transaction Number</label>
                        <input id="ledgerTransactionNumber" class="developer-form-control" maxlength="255" required>
                    </div>
                    <div>
                        <label class="developer-form-label" for="ledgerReferenceNumber">Reference Number</label>
                        <input id="ledgerReferenceNumber" class="developer-form-control" maxlength="255">
                    </div>
                    <div>
                        <label id="ledgerQuantityLabel" class="developer-form-label" for="ledgerQuantity">Qty Out</label>
                        <input id="ledgerQuantity" type="number" min="1" step="1" class="developer-form-control" required>
                    </div>
                    <div>
                        <label class="developer-form-label" for="ledgerBalanceStock">Balance Stock</label>
                        <input id="ledgerBalanceStock" type="number" step="1" class="developer-form-control" required>
                    </div>
                    <div>
                        <label class="developer-form-label" for="ledgerOum">OUM</label>
                        <input id="ledgerOum" class="developer-form-control" maxlength="255">
                    </div>
                    <div>
                        <label id="ledgerPriceLabel" class="developer-form-label" for="ledgerPrice">Price</label>
                        <input id="ledgerPrice" type="number" min="0" step="0.01" class="developer-form-control" required>
                    </div>
                </div>
                <div>
                    <span class="developer-form-label">Channel</span>
                    <div class="developer-radio-grid">
                        <label class="developer-radio-option"><input type="radio" name="ledger_channel" value="online" checked><span><strong>Online</strong><small>Manual online transaction.</small></span></label>
                        <label class="developer-radio-option"><input type="radio" name="ledger_channel" value="local"><span><strong>Local</strong><small>Manual local transaction.</small></span></label>
                    </div>
                </div>
                <div class="developer-modal-actions">
                    <button type="button" class="developer-secondary-btn" data-modal-close="ledgerEntryModal">Cancel</button>
                    <button type="submit" class="developer-primary-btn" id="saveLedgerEntryBtn">Save Entry</button>
                </div>
            </form>
        </div>
    </div>

    <div id="ledgerDeleteConfirmModal" class="developer-modal hidden" aria-hidden="true">
        <div class="developer-modal-panel developer-modal-sm">
            <div class="developer-modal-icon">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 14H6L5 6"></path></svg>
            </div>
            <h2 class="text-center text-lg font-extrabold text-slate-950">Permanently Delete Entry</h2>
            <p id="ledgerDeleteConfirmText" class="mt-2 text-center text-sm text-slate-500">Delete this product ledger entry?</p>
            <div class="mt-6 grid grid-cols-2 gap-3">
                <button type="button" class="developer-secondary-btn justify-center" id="ledgerDeleteCancelBtn">Cancel</button>
                <button type="button" class="developer-primary-btn justify-center" id="ledgerDeleteConfirmBtn">Delete</button>
            </div>
        </div>
    </div>

    <div id="developerToast" class="developer-toast" role="status" aria-live="polite">
        <span id="developerToastText">Ready</span>
    </div>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\hatdog\resources\views/Developer/developer_control_panel.blade.php ENDPATH**/ ?>