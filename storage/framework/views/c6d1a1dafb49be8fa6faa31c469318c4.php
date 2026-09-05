<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <base href="<?php echo e(url('/')); ?>/">
    <title>Enterprise Portal | Collapsible Maroon Dashboard</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Lucide Icons for modern, crisp UI assets -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Chart.js CDN for interactive donut graphs -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        maroon: {
                            DEFAULT: '#4A0A15',
                            hover: '#3a0810',
                            light: '#fdf2f4',
                            50: '#fdf2f3',
                            100: '#fbe4e6',
                            200: '#f7ccd1',
                            300: '#f0a4ae',
                            400: '#e37383',
                            500: '#cf485c',
                            600: '#ba3146',
                            700: '#9c2436',
                            800: '#82202f',
                            900: '#4a0612', // Custom Dark Maroon requested
                            950: '#30020a',
                        },
                        gold: {
                            DEFAULT: '#FFC72C',
                            light: '#FFF9E6'
                        },
                        goldlining: {
                            400: '#facc15', // Yellow
                            500: '#eab308', // Darker Yellow
                            600: '#ca8a04', // Rich Gold
                        }
                    }
                }
            }
        }
    </script>
    <!-- Custom Style Sheet -->
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/admin_sidebar_navbar.css']); ?>
    <?php echo $__env->yieldPushContent('styles'); ?>
    <script>
    window.addEventListener('error', function(e) {
        if (e.filename && e.filename.includes('product')) {
            console.warn('ERROR SOURCE:', e.filename, '| LINE:', e.lineno, '| COL:', e.colno, '| MESSAGE:', e.message);
            console.warn('STACK:', e.error?.stack);
            console.warn('INITIATOR:', e.target?.outerHTML || e.target?.tagName || 'unknown');
        }
        // Also catch script load failures (e.target is the script/link element)
        if (e.target && (e.target.tagName === 'SCRIPT' || e.target.tagName === 'LINK')) {
            console.error('RESOURCE LOAD FAILED:', e.target.tagName, e.target.src || e.target.href);
        }
    }, true);
    </script>
</head>
<?php
    $activeSystemTheme = function_exists('hatdogActiveSystemTheme') ? hatdogActiveSystemTheme() : 'default';
    $systemThemeIconMap = [
        'default' => ['notification' => null],
        'christmas' => ['notification' => 'build/assets/images/SYSTEM DESIGNS/XMASS/snowflakes.gif'],
        'holy-week' => ['notification' => 'build/assets/images/SYSTEM DESIGNS/HOLYWEEK/religious.gif'],
        'halloween' => ['notification' => 'build/assets/images/SYSTEM DESIGNS/HOLLOWEEN/spiderweb.gif'],
        'new-year' => ['notification' => 'build/assets/images/SYSTEM DESIGNS/NEWYEAR/new-years-eye.gif'],
        'chinese-new-year' => ['notification' => 'build/assets/images/SYSTEM DESIGNS/chinese new year/lantern.gif'],
    ];
    $activeSystemThemeIcons = $systemThemeIconMap[$activeSystemTheme] ?? $systemThemeIconMap['default'];
    $dateTimeThemeDesignMap = [
        'christmas' => [
            'asset' => 'build/assets/images/SYSTEM DESIGNS/XMASS/snowman.gif',
            'class' => 'theme-datetime-snow',
            'alt' => 'Christmas snow animation',
        ],
        'holy-week' => [
            'asset' => 'build/assets/images/SYSTEM DESIGNS/HOLYWEEK/peace-dove.gif',
            'class' => 'theme-datetime-holy',
            'alt' => 'Holy Week design',
        ],
        'halloween' => [
            'asset' => 'build/assets/images/SYSTEM DESIGNS/HOLLOWEEN/cauldron.gif',
            'class' => 'theme-datetime-halloween',
            'alt' => 'Halloween cauldron design',
        ],
        'new-year' => [
            'asset' => 'build/assets/images/SYSTEM DESIGNS/NEWYEAR/fireworks.gif',
            'class' => 'theme-datetime-fireworks',
            'alt' => 'New Year fireworks animation',
        ],
        'chinese-new-year' => [
            'asset' => 'build/assets/images/SYSTEM DESIGNS/chinese new year/dragon.gif',
            'class' => 'theme-datetime-dragon',
            'alt' => 'Chinese New Year dragon animation',
        ],
    ];
    $activeDateTimeThemeDesign = $dateTimeThemeDesignMap[$activeSystemTheme] ?? null;
    $systemThemeAssetSrc = function (?string $path): ?string {
        if (!$path) {
            return null;
        }

        $version = \Illuminate\Support\Facades\File::exists(public_path($path))
            ? \Illuminate\Support\Facades\File::lastModified(public_path($path))
            : time();

        return asset($path) . '?v=' . $version;
    };
?>
<body class="bg-gray-50 text-gray-800 font-sans h-screen flex flex-col overflow-hidden" data-system-theme="<?php echo e($activeSystemTheme); ?>">
    <?php echo $__env->make('partials.global.w68-loader', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>


    <div class="flex flex-1 h-screen overflow-hidden">
        
        <!-- SIDEBAR CONTAINER -->
        <!-- Starts off-canvas or slim on mobile, expandable sidebar on larger layouts -->
        <aside id="sidebar" class="sidebar-transition bg-maroon-900 border-r-4 border-goldlining-500 w-64 flex flex-col h-full text-white z-[250] shadow-2xl relative no-print">
            
            <!-- Sidebar Header & Fold Toggle -->
            <div class="p-4 border-b border-maroon-800 flex items-center justify-between min-h-[70px]">
                <div class="flex items-center space-x-3 overflow-hidden" id="sidebar-logo-block">
                    <img src="<?php echo e(asset('build/assets/images/sidebar_logo.png')); ?>" alt="Logo" class="w-10 h-10 object-contain rounded-lg flex-shrink-0">
                    <span class="font-extrabold text-lg tracking-wide whitespace-nowrap text-goldlining-400 uppercase">w68</span>
                </div>
                <!-- Mini collapse button inside sidebar for large screens -->
                <button id="toggle-sidebar-desktop" class="p-1.5 rounded-lg bg-maroon-800 hover:bg-goldlining-500 hover:text-maroon-950 transition-colors hidden md:block" title="Toggle Sidebar">
                    <i id="toggle-icon" data-lucide="chevron-left" class="w-5 h-5"></i>
                </button>
            </div>

            <!-- Navigation Links Scroll Container -->
            <div class="flex-1 min-h-0 overflow-y-auto px-3 pt-4 pb-28 space-y-1.5 scroll-pb-28" id="nav-container">
                
                <!-- DASHBOARD (Standard Link) -->
                <div>
                    <a href="<?php echo e(route('special.dashboard')); ?>" class="nav-item flex items-center space-x-3 px-3 py-3 rounded-lg transition-all duration-150 group <?php echo e(Route::currentRouteName() === 'special.dashboard' ? 'active bg-goldlining-500 text-maroon-950 font-semibold shadow' : 'hover:bg-maroon-800 text-gray-100'); ?>" title="Dashboard">
                        <i data-lucide="layout-dashboard" class="w-5 h-5 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm transition-opacity duration-200">Dashboard</span>
                    </a>
                </div>

                <!-- MASTER LIST (DROPDOWN) -->
                <div class="menu-dropdown">
                    <button onclick="toggleDropdown('dropdown-master', this)" class="w-full flex items-center justify-between px-3 py-3 rounded-lg hover:bg-maroon-800 text-gray-100 hover:text-white transition-all group" title="Master List">
                        <div class="flex items-center space-x-3">
                            <i data-lucide="database" class="w-5 h-5 text-goldlining-400 group-hover:scale-110 transition-transform flex-shrink-0"></i>
                            <span class="sidebar-text text-sm whitespace-nowrap">Master List</span>
                        </div>
                        <i data-lucide="chevron-down" class="dropdown-chevron w-4 h-4 text-goldlining-500 rotate-transition sidebar-text <?php echo e(in_array(Route::currentRouteName(), ['regular.prod-master', 'regular.supplier-master', 'regular.customer-master', 'regular.forwarder-master', 'special.prod-master', 'special.prod-online-config', 'special.supplier-master', 'special.customer-master', 'special.forwarder-master']) ? 'rotate-180' : ''); ?>"></i>
                    </button>
                    <div id="dropdown-master" class="submenu-transition overflow-hidden bg-maroon-950 bg-opacity-40 rounded-md mt-1 pl-4 space-y-1" style="<?php echo e(in_array(Route::currentRouteName(), ['regular.prod-master', 'regular.supplier-master', 'regular.customer-master', 'regular.forwarder-master', 'special.prod-master', 'special.prod-online-config', 'special.supplier-master', 'special.customer-master', 'special.forwarder-master']) ? 'max-height: 500px;' : 'max-height: 0px;'); ?>">
                        <a href="<?php echo e(route('special.prod-master')); ?>" class="flex items-center space-x-2 py-2 px-3 text-xs rounded transition-all <?php echo e(Route::currentRouteName() === 'special.prod-master' ? 'bg-goldlining-500 text-maroon-950 font-semibold shadow' : 'text-gray-300 hover:text-goldlining-400 hover:translate-x-1'); ?>" data-page="prod-master">
                            <i data-lucide="package" class="w-3.5 h-3.5 <?php echo e(Route::currentRouteName() === 'special.prod-master' ? 'text-maroon-950' : ''); ?>"></i>
                            <span>Product Master List</span>
                        </a>
                         <a href="<?php echo e(route('special.prod-online-config')); ?>" class="flex items-center space-x-2 py-2 px-3 text-xs rounded transition-all <?php echo e(Route::currentRouteName() === 'special.prod-online-config' ? 'bg-goldlining-500 text-maroon-950 font-semibold shadow' : 'text-gray-300 hover:text-goldlining-400 hover:translate-x-1'); ?>" data-page="prod-online-config">
                            <i data-lucide="refresh-cw" class="w-3.5 h-3.5 <?php echo e(Route::currentRouteName() === 'special.prod-online-config' ? 'text-maroon-950' : 'text-cyan-400'); ?>"></i>
                            <span>Online Product Config</span>
                        </a>
                        <a href="<?php echo e(route('special.supplier-master')); ?>" class="flex items-center space-x-2 py-2 px-3 text-xs rounded transition-all <?php echo e(Route::currentRouteName() === 'special.supplier-master' ? 'bg-goldlining-500 text-maroon-950 font-semibold shadow' : 'text-gray-300 hover:text-goldlining-400 hover:translate-x-1'); ?>" data-page="supplier-master">
                            <i data-lucide="truck" class="w-3.5 h-3.5 <?php echo e(Route::currentRouteName() === 'special.supplier-master' ? 'text-maroon-950' : 'text-blue-400'); ?>"></i>
                            <span>Supplier Master List</span>
                        </a>
                        <a href="<?php echo e(route('special.customer-master')); ?>" class="flex items-center space-x-2 py-2 px-3 text-xs rounded transition-all <?php echo e(Route::currentRouteName() === 'special.customer-master' ? 'bg-goldlining-500 text-maroon-950 font-semibold shadow' : 'text-gray-300 hover:text-goldlining-400 hover:translate-x-1'); ?>" data-page="customer-master">
                            <i data-lucide="users" class="w-3.5 h-3.5 <?php echo e(Route::currentRouteName() === 'special.customer-master' ? 'text-maroon-950' : 'text-emerald-400'); ?>"></i>
                            <span>Customer Master List</span>
                        </a>
                        <a href="<?php echo e(route('special.forwarder-master')); ?>" class="flex items-center space-x-2 py-2 px-3 text-xs rounded transition-all <?php echo e(Route::currentRouteName() === 'special.forwarder-master' ? 'bg-goldlining-500 text-maroon-950 font-semibold shadow' : 'text-gray-300 hover:text-goldlining-400 hover:translate-x-1'); ?>" data-page="forwarder-master">
                            <i data-lucide="plane" class="w-3.5 h-3.5 <?php echo e(Route::currentRouteName() === 'special.forwarder-master' ? 'text-maroon-950' : 'text-emerald-400'); ?>"></i>
                            <span>Forwarder Master List</span>
                        </a>
                    </div>
                </div>

                <!-- INVENTORY MANAGEMENT (DROPDOWN) -->
                <div class="menu-dropdown">
                    <button onclick="toggleDropdown('dropdown-inventory', this)" class="w-full flex items-center justify-between px-3 py-3 rounded-lg hover:bg-maroon-800 text-gray-100 hover:text-white transition-all group" title="Inventory Management">
                        <div class="flex items-center space-x-3">
                            <i data-lucide="boxes" class="w-5 h-5 text-goldlining-400 group-hover:scale-110 transition-transform flex-shrink-0"></i>
                            <span class="sidebar-text text-sm whitespace-nowrap">Inventory Management</span>
                        </div>
                        <i data-lucide="chevron-down" class="dropdown-chevron w-4 h-4 text-goldlining-500 rotate-transition sidebar-text <?php echo e(in_array(Route::currentRouteName(), ['special.inv-adjust', 'special.sales-junk', 'special.item-convert']) ? 'rotate-180' : ''); ?>"></i>
                    </button>
                    <div id="dropdown-inventory" class="submenu-transition overflow-hidden bg-maroon-950 bg-opacity-40 rounded-md mt-1 pl-4 space-y-1" style="<?php echo e(in_array(Route::currentRouteName(), ['special.inv-adjust', 'special.sales-junk', 'special.item-convert']) ? 'max-height: 500px;' : 'max-height: 0px;'); ?>">
                        <a href="<?php echo e(route('special.inv-adjust')); ?>" class="flex items-center space-x-2 py-2 px-3 text-xs rounded transition-all <?php echo e(Route::currentRouteName() === 'special.inv-adjust' ? 'bg-goldlining-500 text-maroon-950 font-semibold shadow' : 'text-gray-300 hover:text-goldlining-400 hover:translate-x-1'); ?>" data-page="inv-adjust">
                            <i data-lucide="sliders-horizontal" class="w-3.5 h-3.5 <?php echo e(Route::currentRouteName() === 'special.inv-adjust' ? 'text-maroon-950' : ''); ?>"></i>
                            <span>Inventory Adjustment</span>
                        </a>
                        <a href="<?php echo e(route('special.sales-junk')); ?>" class="flex items-center space-x-2 py-2 px-3 text-xs rounded transition-all <?php echo e(Route::currentRouteName() === 'special.sales-junk' ? 'bg-goldlining-500 text-maroon-950 font-semibold shadow' : 'text-gray-300 hover:text-goldlining-400 hover:translate-x-1'); ?>" data-page="sales-junk">
                            <i data-lucide="archive" class="w-3.5 h-3.5 <?php echo e(Route::currentRouteName() === 'special.sales-junk' ? 'text-maroon-950' : ''); ?>"></i>
                            <span>Junk Items</span>
                        </a>
                         <a href="<?php echo e(route('special.item-convert')); ?>" class="flex items-center space-x-2 py-2 px-3 text-xs rounded transition-all <?php echo e(Route::currentRouteName() === 'special.item-convert' ? 'bg-goldlining-500 text-maroon-950 font-semibold shadow' : 'text-gray-300 hover:text-goldlining-400 hover:translate-x-1'); ?>" data-page="item-convert">
                            <i data-lucide="shuffle" class="w-3.5 h-3.5 <?php echo e(Route::currentRouteName() === 'special.item-convert' ? 'text-maroon-950' : ''); ?>"></i>
                            <span>Convert Items</span>
                        </a>   
                    </div>
                </div>
                <!-- PURCHASING (DROPDOWN) -->
                <div class="menu-dropdown">
                    <button onclick="toggleDropdown('dropdown-purchasing', this)" class="w-full flex items-center justify-between px-3 py-3 rounded-lg hover:bg-maroon-800 text-gray-100 hover:text-white transition-all group" title="Purchasing">
                        <div class="flex items-center space-x-3">
                            <i data-lucide="shopping-cart" class="w-5 h-5 text-goldlining-400 group-hover:scale-110 transition-transform flex-shrink-0"></i>
                            <span class="sidebar-text text-sm whitespace-nowrap">Purchasing</span>
                        </div>
                        <i data-lucide="chevron-down" class="dropdown-chevron w-4 h-4 text-goldlining-500 rotate-transition sidebar-text <?php echo e(in_array(Route::currentRouteName(), ['special.purchase-note', 'special.purchase-order', 'special.purchase-return', 'special.purchase-viber']) ? 'rotate-180' : ''); ?>"></i>
                    </button>
                    <div id="dropdown-purchasing" class="submenu-transition overflow-hidden bg-maroon-950 bg-opacity-40 rounded-md mt-1 pl-4 space-y-1" style="<?php echo e(in_array(Route::currentRouteName(), ['special.purchase-note', 'special.purchase-order', 'special.purchase-return', 'special.purchase-viber']) ? 'max-height: 500px;' : 'max-height: 0px;'); ?>">
                          <a href="<?php echo e(route('special.purchase-viber')); ?>" class="flex items-center space-x-2 py-2 px-3 text-xs rounded transition-all <?php echo e(Route::currentRouteName() === 'special.purchase-viber' ? 'bg-goldlining-500 text-maroon-950 font-semibold shadow' : 'text-gray-300 hover:text-goldlining-400 hover:translate-x-1'); ?>" data-page="viber-list">
                            <i data-lucide="file-text" class="w-3.5 h-3.5 <?php echo e(Route::currentRouteName() === 'special.purchase-viber' ? 'text-maroon-950' : ''); ?>"></i>
                            <span>Purchase Entry</span>
                        </a>
                        <a href="<?php echo e(route('special.purchase-note')); ?>" class="flex items-center space-x-2 py-2 px-3 text-xs rounded transition-all <?php echo e(Route::currentRouteName() === 'special.purchase-note' ? 'bg-goldlining-500 text-maroon-950 font-semibold shadow' : 'text-gray-300 hover:text-goldlining-400 hover:translate-x-1'); ?>" data-page="purch-note">
                            <i data-lucide="file-text" class="w-3.5 h-3.5 <?php echo e(Route::currentRouteName() === 'special.purchase-note' ? 'text-maroon-950' : ''); ?>"></i>
                            <span>Purchasing Note</span>
                        </a>
                        <a href="<?php echo e(route('special.purchase-order')); ?>" class="flex items-center space-x-2 py-2 px-3 text-xs rounded transition-all <?php echo e(Route::currentRouteName() === 'special.purchase-order' ? 'bg-goldlining-500 text-maroon-950 font-semibold shadow' : 'text-gray-300 hover:text-goldlining-400 hover:translate-x-1'); ?>" data-page="purch-order">
                            <i data-lucide="shopping-bag" class="w-3.5 h-3.5 <?php echo e(Route::currentRouteName() === 'special.purchase-order' ? 'text-maroon-950' : ''); ?>"></i>
                            <span>Purchase Order</span>
                        </a>
                        <a href="<?php echo e(route('special.purchase-return')); ?>" class="flex items-center space-x-2 py-2 px-3 text-xs rounded transition-all <?php echo e(Route::currentRouteName() === 'special.purchase-return' ? 'bg-goldlining-500 text-maroon-950 font-semibold shadow' : 'text-gray-300 hover:text-goldlining-400 hover:translate-x-1'); ?>" data-page="purch-return">
                            <i data-lucide="undo-2" class="w-3.5 h-3.5 <?php echo e(Route::currentRouteName() === 'special.purchase-return' ? 'text-maroon-950' : ''); ?>"></i>
                            <span>Purchase Return</span>
                        </a>
                    </div>
                </div>

                <!-- SALES (DROPDOWN) -->
                <div class="menu-dropdown">
                    <button onclick="toggleDropdown('dropdown-sales', this)" class="w-full flex items-center justify-between px-3 py-3 rounded-lg hover:bg-maroon-800 text-gray-100 hover:text-white transition-all group" title="Sales">
                        <div class="flex items-center space-x-3">
                            <i data-lucide="banknote" class="w-5 h-5 text-goldlining-400 group-hover:scale-110 transition-transform flex-shrink-0"></i>
                            <span class="sidebar-text text-sm whitespace-nowrap">Sales</span>
                        </div>
                        <i data-lucide="chevron-down" class="dropdown-chevron w-4 h-4 text-goldlining-500 rotate-transition sidebar-text <?php echo e(in_array(Route::currentRouteName(), ['regular.sales-note', 'regular.sales-order', 'regular.consignment-invoice', 'regular.sales-return', 'regular.waybill', 'special.sales-note', 'special.sales-order', 'special.consignment-invoice', 'special.sales-return', 'special.waybill']) ? 'rotate-180' : ''); ?>"></i>
                    </button>
                    <div id="dropdown-sales" class="submenu-transition overflow-hidden bg-maroon-950 bg-opacity-40 rounded-md mt-1 pl-4 space-y-1" style="<?php echo e(in_array(Route::currentRouteName(), ['regular.sales-note', 'regular.sales-order', 'regular.consignment-invoice', 'regular.sales-return', 'regular.waybill', 'special.sales-note', 'special.sales-order', 'special.consignment-invoice', 'special.sales-return', 'special.waybill']) ? 'max-height: 500px;' : 'max-height: 0px;'); ?>">
                        <a href="<?php echo e(route('special.sales-note')); ?>" class="flex items-center space-x-2 py-2 px-3 text-xs rounded transition-all <?php echo e(Route::currentRouteName() === 'special.sales-note' ? 'bg-goldlining-500 text-maroon-950 font-semibold shadow' : 'text-gray-300 hover:text-goldlining-400 hover:translate-x-1'); ?>" data-page="sales-note">
                            <i data-lucide="receipt" class="w-3.5 h-3.5 <?php echo e(Route::currentRouteName() === 'special.sales-note' ? 'text-maroon-950' : ''); ?>"></i>
                            <span>Sales Note</span>
                        </a>
                        <a href="<?php echo e(route('special.sales-order')); ?>" class="flex items-center space-x-2 py-2 px-3 text-xs rounded transition-all <?php echo e(Route::currentRouteName() === 'special.sales-order' ? 'bg-goldlining-500 text-maroon-950 font-semibold shadow' : 'text-gray-300 hover:text-goldlining-400 hover:translate-x-1'); ?>" data-page="sales-order">
                            <i data-lucide="file-symlink" class="w-3.5 h-3.5 <?php echo e(Route::currentRouteName() === 'special.sales-order' ? 'text-maroon-950' : ''); ?>"></i>
                            <span>Sales Order</span>
                        </a>
                        <a href="<?php echo e(route('special.consignment-invoice')); ?>" class="flex items-center space-x-2 py-2 px-3 text-xs rounded transition-all <?php echo e(Route::currentRouteName() === 'special.consignment-invoice' ? 'bg-goldlining-500 text-maroon-950 font-semibold shadow' : 'text-gray-300 hover:text-goldlining-400 hover:translate-x-1'); ?>" data-page="cons-invoice">
                            <i data-lucide="file-digit" class="w-3.5 h-3.5 <?php echo e(Route::currentRouteName() === 'special.consignment-invoice' ? 'text-maroon-950' : ''); ?>"></i>
                            <span>Consignment Invoice</span>
                        </a>
                        <a href="<?php echo e(route('special.sales-return')); ?>" class="flex items-center space-x-2 py-2 px-3 text-xs <?php echo e(Request::routeIs('special.sales-return') ? 'text-goldlining-400 bg-maroon/20' : 'text-gray-300'); ?> hover:text-goldlining-400 hover:translate-x-1 transition-all rounded" data-page="sales-return">
                            <i data-lucide="corner-up-left" class="w-3.5 h-3.5"></i>
                            <span>Sales Return</span>
                        </a>

                        <a href="<?php echo e(route('special.waybill')); ?>" class="flex items-center space-x-2 py-2 px-3 text-xs rounded transition-all <?php echo e(Route::currentRouteName() === 'special.waybill' ? 'bg-goldlining-500 text-maroon-950 font-semibold shadow' : 'text-gray-300 hover:text-goldlining-400 hover:translate-x-1'); ?>" data-page="waybill">
                            <i data-lucide="map" class="w-3.5 h-3.5 <?php echo e(Route::currentRouteName() === 'special.waybill' ? 'text-maroon-950' : ''); ?>"></i>
                            <span>Waybill</span>
                        </a>
                    </div>
                </div>

                <!-- ACCOUNTING (DROPDOWN) -->
                <div class="menu-dropdown">
                    <button onclick="toggleDropdown('dropdown-accounting', this)" class="w-full flex items-center justify-between px-3 py-3 rounded-lg hover:bg-maroon-800 <?php echo e(in_array(Route::currentRouteName(), ['regular.payments', 'regular.payable-cheque-voucher', 'regular.expense-cheque-voucher', 'special.payments', 'special.payable-cheque-voucher', 'special.expense-cheque-voucher']) ? 'bg-maroon-800 text-white shadow-inner' : 'text-gray-100'); ?> hover:text-white transition-all group" title="Accounting">
                        <div class="flex items-center space-x-3">
                            <i data-lucide="calculator" class="w-5 h-5 text-goldlining-400 group-hover:scale-110 transition-transform flex-shrink-0"></i>
                            <span class="sidebar-text text-sm whitespace-nowrap <?php echo e(in_array(Route::currentRouteName(), ['regular.payments', 'regular.payable-cheque-voucher', 'regular.expense-cheque-voucher', 'special.payments', 'special.payable-cheque-voucher', 'special.expense-cheque-voucher']) ? 'font-bold text-goldlining-400' : ''); ?>">Accounting</span>
                        </div>
                        <i data-lucide="chevron-down" class="dropdown-chevron w-4 h-4 text-goldlining-500 rotate-transition sidebar-text <?php echo e(in_array(Route::currentRouteName(), ['regular.payments', 'regular.payable-cheque-voucher', 'regular.expense-cheque-voucher', 'special.payments', 'special.payable-cheque-voucher', 'special.expense-cheque-voucher']) ? 'rotate-180' : ''); ?>"></i>
                    </button>
                    <div id="dropdown-accounting" class="submenu-transition overflow-hidden bg-maroon-950 bg-opacity-40 rounded-md mt-1 pl-4 space-y-1" style="<?php echo e(in_array(Route::currentRouteName(), ['regular.payments', 'regular.payable-cheque-voucher', 'regular.expense-cheque-voucher', 'special.payments', 'special.payable-cheque-voucher', 'special.expense-cheque-voucher']) ? 'max-height: 500px;' : 'max-height: 0px;'); ?>">
                        <a href="<?php echo e(route('special.payments')); ?>" class="flex items-center space-x-2 py-2 px-3 text-xs rounded transition-all <?php echo e(Route::currentRouteName() === 'special.payments' ? 'bg-goldlining-500 text-maroon-950 font-semibold shadow' : 'text-gray-300 hover:text-goldlining-400 hover:translate-x-1'); ?>" data-page="payments">
                            <i data-lucide="credit-card" class="w-3.5 h-3.5 <?php echo e(Route::currentRouteName() === 'special.payments' ? 'text-maroon-950' : ''); ?>"></i>
                            <span>Payments</span>
                        </a>
                        <a href="<?php echo e(route('special.payable-cheque-voucher')); ?>" class="flex items-center space-x-2 py-2 px-3 text-xs rounded transition-all <?php echo e(Route::currentRouteName() === 'special.payable-cheque-voucher' ? 'bg-goldlining-500 text-maroon-950 font-semibold shadow' : 'text-gray-300 hover:text-goldlining-400 hover:translate-x-1'); ?>" data-page="pay-cheque">
                            <i data-lucide="book-check" class="w-3.5 h-3.5 <?php echo e(Route::currentRouteName() === 'special.payable-cheque-voucher' ? 'text-maroon-950' : ''); ?>"></i>
                            <span>Payable Cheque Voucher</span>
                        </a>
                        <a href="<?php echo e(route('special.expense-cheque-voucher')); ?>" class="flex items-center space-x-2 py-2 px-3 text-xs rounded transition-all <?php echo e(Route::currentRouteName() === 'special.expense-cheque-voucher' ? 'bg-goldlining-500 text-maroon-950 font-semibold shadow' : 'text-gray-300 hover:text-goldlining-400 hover:translate-x-1'); ?>" data-page="exp-cheque">
                            <i data-lucide="receipt" class="w-3.5 h-3.5 <?php echo e(Route::currentRouteName() === 'special.expense-cheque-voucher' ? 'text-maroon-950' : ''); ?>"></i>
                            <span>Expense Cheque Voucher</span>
                        </a>
                    </div>
                </div>

                <!-- REPORTS (DROPDOWN) -->
                <div class="menu-dropdown">
                    <button onclick="toggleDropdown('dropdown-reports', this)" class="w-full flex items-center justify-between px-3 py-3 rounded-lg hover:bg-maroon-800 <?php echo e(in_array(Route::currentRouteName(), ['regular.sales-report', 'regular.inventory-reports', 'regular.accounts-receivable', 'special.sales-report', 'special.inventory-reports', 'special.accounts-receivable', 'special.unserved-report', 'special.cost-report', 'special.top-products']) ? 'bg-maroon-800 text-white shadow-inner' : 'text-gray-100'); ?> hover:text-white transition-all group" title="Reports">
                        <div class="flex items-center space-x-3">
                            <i data-lucide="trending-up" class="w-5 h-5 text-goldlining-400 group-hover:scale-110 transition-transform flex-shrink-0"></i>
                            <span class="sidebar-text text-sm whitespace-nowrap <?php echo e(in_array(Route::currentRouteName(), ['regular.sales-report', 'regular.inventory-reports', 'regular.accounts-receivable', 'special.sales-report', 'special.inventory-reports', 'special.accounts-receivable', 'special.unserved-report', 'special.cost-report', 'special.top-products']) ? 'font-bold text-goldlining-400' : ''); ?>">Reports</span>
                        </div>
                        <i data-lucide="chevron-down" class="dropdown-chevron w-4 h-4 text-goldlining-500 rotate-transition sidebar-text <?php echo e(in_array(Route::currentRouteName(), ['regular.sales-report', 'regular.inventory-reports', 'regular.accounts-receivable', 'special.sales-report', 'special.inventory-reports', 'special.accounts-receivable', 'special.unserved-report', 'special.cost-report', 'special.top-products']) ? 'rotate-180' : ''); ?>"></i>
                    </button>
                    <div id="dropdown-reports" class="submenu-transition overflow-hidden bg-maroon-950 bg-opacity-40 rounded-md mt-1 pl-4 space-y-1" style="<?php echo e(in_array(Route::currentRouteName(), ['regular.sales-report', 'regular.inventory-reports', 'regular.accounts-receivable', 'special.sales-report', 'special.inventory-reports', 'special.accounts-receivable', 'special.unserved-report', 'special.cost-report', 'special.top-products']) ? 'max-height: 500px;' : 'max-height: 0px;'); ?>">
                        <a href="<?php echo e(route('special.sales-report')); ?>" class="flex items-center space-x-2 py-2 px-3 text-xs rounded transition-all <?php echo e(Route::currentRouteName() === 'special.sales-report' ? 'bg-goldlining-500 text-maroon-950 font-semibold shadow' : 'text-gray-300 hover:text-goldlining-400 hover:translate-x-1'); ?>" data-page="rep-sales">
                            <i data-lucide="bar-chart-3" class="w-3.5 h-3.5 <?php echo e(Route::currentRouteName() === 'special.sales-report' ? 'text-maroon-950' : ''); ?>"></i>
                            <span>Account Receivable</span>
                        </a>
                        <a href="<?php echo e(route('special.inventory-reports')); ?>" class="flex items-center space-x-2 py-2 px-3 text-xs rounded transition-all <?php echo e(Route::currentRouteName() === 'special.inventory-reports' ? 'bg-goldlining-500 text-maroon-950 font-semibold shadow' : 'text-gray-300 hover:text-goldlining-400 hover:translate-x-1'); ?>" data-page="rep-inv">
                            <i data-lucide="package-search" class="w-3.5 h-3.5 <?php echo e(Route::currentRouteName() === 'special.inventory-reports' ? 'text-maroon-950' : ''); ?>"></i>
                            <span>Inventory Reports</span>
                        </a>

                        <a href="<?php echo e(route('special.unserved-report')); ?>" class="flex items-center space-x-2 py-2 px-3 text-xs rounded transition-all <?php echo e(Route::currentRouteName() === 'special.unserved-report' ? 'bg-goldlining-500 text-maroon-950 font-semibold shadow' : 'text-gray-300 hover:text-goldlining-400 hover:translate-x-1'); ?>" data-page="rep-unserved">
                            <i data-lucide="arrow-down-left" class="w-3.5 h-3.5 <?php echo e(Route::currentRouteName() === 'special.unserved-report' ? 'text-maroon-950' : ''); ?>"></i>
                            <span>Unserved Details</span>
                        </a>

                        <a href="<?php echo e(route('special.accounts-receivable')); ?>" class="flex items-center space-x-2 py-2 px-3 text-xs rounded transition-all <?php echo e(Route::currentRouteName() === 'special.accounts-receivable' ? 'bg-goldlining-500 text-maroon-950 font-semibold shadow' : 'text-gray-300 hover:text-goldlining-400 hover:translate-x-1'); ?>" data-page="rep-ar">
                            <i data-lucide="arrow-down-left" class="w-3.5 h-3.5 <?php echo e(Route::currentRouteName() === 'special.accounts-receivable' ? 'text-maroon-950' : ''); ?>"></i>
                            <span>Accounts Receivable Reports</span>
                        </a>

                        <a href="<?php echo e(route('special.cost-report')); ?>" class="flex items-center space-x-2 py-2 px-3 text-xs rounded transition-all <?php echo e(Route::currentRouteName() === 'special.cost-report' ? 'bg-goldlining-500 text-maroon-950 font-semibold shadow' : 'text-gray-300 hover:text-goldlining-400 hover:translate-x-1'); ?>" data-page="rep-cost">
                            <i data-lucide="receipt" class="w-3.5 h-3.5 <?php echo e(Route::currentRouteName() === 'special.cost-report' ? 'text-maroon-950' : ''); ?>"></i>
                            <span>Cost Report</span>
                        </a>
                        <a href="<?php echo e(route('special.top-products')); ?>" class="flex items-center space-x-2 py-2 px-3 text-xs rounded transition-all <?php echo e(Route::currentRouteName() === 'special.top-products' ? 'bg-goldlining-500 text-maroon-950 font-semibold shadow' : 'text-gray-300 hover:text-goldlining-400 hover:translate-x-1'); ?>" data-page="rep-top-products">
                            <i data-lucide="trophy" class="w-3.5 h-3.5 <?php echo e(Route::currentRouteName() === 'special.top-products' ? 'text-maroon-950' : ''); ?>"></i>
                            <span>Top Products</span>
                        </a>

                    </div>
                </div>

            </div>
        </aside>

        <!-- MAIN WORKSPACE CONTENT CONTAINER -->
        <main class="flex-1 flex flex-col min-w-0 overflow-hidden">
            
            <!-- TOP NAVIGATION BAR -->
            <header class="bg-white border-b border-gray-200 h-[70px] px-6 flex items-center justify-between flex-shrink-0 relative z-20 shadow-sm no-print">
                <!-- Left Hand Utility Action -->
                <div class="flex items-center space-x-4">
                    <button id="toggle-sidebar-mobile" class="p-2 rounded-lg hover:bg-gray-100 md:hidden text-gray-600 focus:outline-none" title="Menu">
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                    <!-- Live Date and Time Display -->
                    <div
                        id="theme-datetime-card"
                        data-system-theme="<?php echo e($activeSystemTheme); ?>"
                        class="hidden sm:flex items-center space-x-2.5 text-xs md:text-sm text-gray-600 bg-gray-50 border border-gray-200 px-4 py-2 rounded-lg font-medium shadow-sm relative overflow-hidden <?php echo e($activeDateTimeThemeDesign ? 'pr-16' : ''); ?>"
                    >
                        <?php if($activeDateTimeThemeDesign): ?>
                            <span class="theme-datetime-animation-wrap" aria-hidden="true">
                                <img
                                    src="<?php echo e($systemThemeAssetSrc($activeDateTimeThemeDesign['asset'])); ?>"
                                    class="theme-datetime-animation <?php echo e($activeDateTimeThemeDesign['class']); ?>"
                                    alt="<?php echo e($activeDateTimeThemeDesign['alt']); ?>"
                                >
                            </span>
                        <?php endif; ?>
                        <i data-lucide="calendar" class="w-4 h-4 text-maroon-900"></i>
                        <span id="live-datetime" class="font-sans">Loading system time...</span>
                    </div>
                </div>

                <!-- Right Side Widgets: Notification, Profile Trigger -->
                <div class="flex items-center space-x-3">
                    
                    <!-- Notification Bell -->
                    <div class="relative">
                        <button id="notif-btn" class="p-2 rounded-full hover:bg-gray-100 text-gray-600 relative transition-all" title="View Notifications">
                            <span id="rush-notif-badge" class="hidden absolute top-0 right-0 z-20 bg-red-500 text-white font-bold rounded-full w-4 h-4 flex items-center justify-center text-[9px] animate-bounce shadow-sm ring-1 ring-white">0</span>
                            <?php if($activeSystemThemeIcons['notification']): ?>
                                <img src="<?php echo e($systemThemeAssetSrc($activeSystemThemeIcons['notification'])); ?>" id="bell-icon" alt="<?php echo e($activeSystemTheme); ?> notification" class="system-theme-navbar-icon">
                            <?php else: ?>
                                <i data-lucide="bell" id="bell-icon" class="w-5.5 h-5.5"></i>
                            <?php endif; ?>
                        </button>
                        
                        <!-- Notification Panel (Hidden by default) -->
                        <div id="notif-dropdown" class="hidden absolute right-0 mt-3 w-80 bg-white border border-gray-200 rounded-xl shadow-xl z-50 overflow-hidden transform origin-top-right transition-all">
                            <div class="p-3 bg-maroon-900 text-white font-bold flex justify-between items-center text-sm border-b-2 border-goldlining-500">
                                <span>Rush Orders Overdue</span>
                                <span id="rush-notif-count" class="bg-goldlining-500 text-maroon-900 text-xs px-1.5 py-0.5 rounded-full font-semibold">0 New</span>
                            </div>
                            <div id="rush-notif-list" class="max-h-72 overflow-y-auto divide-y divide-gray-100">
                                <div class="p-8 text-center text-gray-400 text-sm">
                                    <i data-lucide="check-circle" class="w-8 h-8 mx-auto mb-2 text-gray-300"></i>
                                    <p>No urgent notifications</p>
                                </div>
                                <a href="#" class="hidden p-3.5 hover:bg-gray-50 transition-colors">
                                    <div class="flex space-x-3">
                                        <div class="bg-red-100 text-red-600 p-1.5 rounded-lg h-fit"><i data-lucide="alert-octagon" class="w-4 h-4"></i></div>
                                        <div>
                                            <p class="text-xs font-semibold text-gray-800">Suspicious Login Blocked</p>
                                            <p class="text-[10px] text-gray-500 mt-0.5">IP 192.168.1.45 • 2 mins ago</p>
                                        </div>
                                    </div>
                                </a>
                                <a href="#" class="hidden p-3.5 hover:bg-gray-50 transition-colors">
                                    <div class="flex space-x-3">
                                        <div class="bg-green-100 text-green-600 p-1.5 rounded-lg h-fit"><i data-lucide="refresh-cw" class="w-4 h-4"></i></div>
                                        <div>
                                            <p class="text-xs font-semibold text-gray-800">System database sync success</p>
                                            <p class="text-[10px] text-gray-500 mt-0.5">Automated cron • 45 mins ago</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Subtle Divider -->
                    <div class="h-6 w-px bg-gray-200 mx-1"></div>

                    <!-- Combined Profile Dropdown Container -->
                    <div class="relative">
                        <!-- Profile Interactive Button -->
                        <button id="profile-dropdown-btn" class="flex items-center space-x-2.5 cursor-pointer hover:bg-gray-50 transition-all p-1.5 rounded-lg focus:outline-none" title="User Menu">
                            <img src="<?php echo e($sidebarAvatarUrl ?? ''); ?>" alt="<?php echo e($sidebarFullName ?? ''); ?>" class="w-9 h-9 rounded-full border-2 border-maroon-900 shadow-sm object-cover">
                            <div class="hidden lg:block text-left">
                                <h5 class="text-xs font-bold text-gray-800 leading-tight"><?php echo e($sidebarFullName ?? 'User'); ?></h5>
                                <span class="text-[10px] text-gray-500 block"><?php echo e($sidebarRoleLabel ?? 'Staff'); ?></span>
                            </div>
                        </button>
                        
                        <!-- Profile Action Dropdown matched with sidebar color profile and gold lining -->
                        <div id="profile-dropdown" class="hidden absolute right-0 mt-3 w-48 bg-maroon-900 border border-goldlining-500 rounded-xl shadow-xl z-50 overflow-hidden py-1 text-white">
                            <a href="<?php echo e(route('special.profile')); ?>" class="flex items-center space-x-2.5 px-4 py-2.5 text-sm text-gray-100 hover:bg-maroon-800 hover:text-goldlining-400 transition-colors">
                                <i data-lucide="user" class="w-4 h-4 text-goldlining-400"></i>
                                <span>Profile</span>
                            </a>
                            <a href="#" onclick="switchView('settings')" class="flex items-center space-x-2.5 px-4 py-2.5 text-sm text-gray-100 hover:bg-maroon-800 hover:text-goldlining-400 transition-colors">
                                <i data-lucide="settings" class="w-4 h-4 text-goldlining-400"></i>
                                <span>Settings</span>
                            </a>
                            <hr class="border-maroon-800">
                            <button type="button" onclick="toggleLogoutModal(true)" class="flex items-center space-x-2.5 px-4 py-2.5 text-sm text-red-300 hover:bg-red-950/50 hover:text-red-200 transition-colors w-full text-left">
                                <i data-lucide="log-out" class="w-4 h-4 text-red-400"></i>
                                <span>Sign Out</span>
                            </button>
                        </div>
                    </div>

                </div>
            </header>

            <!-- SCROLLABLE WORKSPACE CONTAINER -->
            <div id="workspace" class="flex-1 overflow-y-auto p-6 md:p-8 space-y-6">
                
                <!-- BREADCRUMB INDICATOR -->
                <div class="flex items-center justify-between">
                    <div>
                        <nav class="flex text-xs font-medium text-gray-500 space-x-2" aria-label="Breadcrumb">
                            <span>Portal</span>
                            <span>/</span>
                            <span id="breadcrumb-current" class="text-maroon-700 font-semibold">
                                <?php if(Route::currentRouteName() === 'admin.usm'): ?>
                                    System Security / USM
                                <?php elseif(in_array(Route::currentRouteName(), ['regular.prod-master', 'special.prod-master'])): ?>
                                    Master List / Product Master
                                <?php elseif(in_array(Route::currentRouteName(), ['regular.supplier-master', 'special.supplier-master'])): ?>
                                    Master List / Supplier Master
                                <?php elseif(in_array(Route::currentRouteName(), ['regular.customer-master', 'special.customer-master'])): ?>
                                    Master List / Customer Master
                                <?php elseif(in_array(Route::currentRouteName(), ['regular.forwarder-master', 'special.forwarder-master'])): ?>
                                    Master List / Forwarder Master
                                <?php elseif(in_array(Route::currentRouteName(), ['regular.inv-adjust', 'special.inv-adjust'])): ?>
                                    Inventory / Inventory Adjustment
                                <?php elseif(Route::currentRouteName() === 'special.sales-junk'): ?>
                                    Inventory / Junk Items
                                <?php elseif(Route::currentRouteName() === 'special.item-convert'): ?>
                                    Inventory / Convert Items
                                <?php elseif(in_array(Route::currentRouteName(), ['regular.purchase-note', 'special.purchase-note'])): ?>
                                    Purchasing / Purchasing Note
                                <?php elseif(in_array(Route::currentRouteName(), ['regular.purchase-order', 'special.purchase-order'])): ?>
                                    Purchasing / Purchase Order
                                <?php elseif(in_array(Route::currentRouteName(), ['regular.purchase-return', 'special.purchase-return'])): ?>
                                    Purchasing / Purchase Return
                                <?php elseif(Route::currentRouteName() === 'special.purchase-viber'): ?>
                                    Purchasing / Purchase Entry
                                <?php elseif(in_array(Route::currentRouteName(), ['regular.sales-note', 'special.sales-note'])): ?>
                                    Sales / Sales Note
                                <?php elseif(in_array(Route::currentRouteName(), ['regular.sales-order', 'special.sales-order'])): ?>
                                    Sales / Sales Order
                                <?php elseif(in_array(Route::currentRouteName(), ['regular.sales-return', 'special.sales-return'])): ?>
                                    Sales / Sales Return
                                <?php elseif(in_array(Route::currentRouteName(), ['regular.consignment-invoice', 'special.consignment-invoice'])): ?>
                                    Sales / Consignment Invoice
                                <?php elseif(in_array(Route::currentRouteName(), ['regular.waybill', 'special.waybill'])): ?>
                                    Sales / Waybill
                                <?php elseif(Route::currentRouteName() === 'special.profile'): ?>
                                    Profile / Admin Profile
                                <?php elseif(in_array(Route::currentRouteName(), ['regular.payments', 'special.payments'])): ?>
                                    Accounting / Payments
                                <?php elseif(in_array(Route::currentRouteName(), ['regular.payable-cheque-voucher', 'special.payable-cheque-voucher'])): ?>
                                    Accounting / Payable Cheque Voucher
                                <?php elseif(in_array(Route::currentRouteName(), ['regular.expense-cheque-voucher', 'special.expense-cheque-voucher'])): ?>
                                    Accounting / Expense Cheque Voucher
                                <?php elseif(in_array(Route::currentRouteName(), ['regular.sales-report', 'special.sales-report'])): ?>
                                    Statement Of Account
                                <?php elseif(in_array(Route::currentRouteName(), ['regular.inventory-reports', 'special.inventory-reports'])): ?>
                                    Reports / Inventory Reports
                                <?php elseif(Route::currentRouteName() === 'special.unserved-report'): ?>
                                    Reports / Unserved Details
                                <?php elseif(in_array(Route::currentRouteName(), ['regular.accounts-receivable', 'special.accounts-receivable'])): ?>
                                    Reports / Accounts Receivable
                                <?php elseif(in_array(Route::currentRouteName(), ['regular.cost-report', 'special.cost-report'])): ?>
                                    Reports / Cost Report
                                <?php elseif(Route::currentRouteName() === 'special.top-products'): ?>
                                    Reports / Top Products
                                <?php else: ?>
                                    Dashboard
                                <?php endif; ?>
                            </span>
                        </nav>
                        <h1 id="workspace-title" class="text-2xl md:text-3xl font-extrabold text-gray-900 tracking-tight mt-1">
                            <?php if(Route::currentRouteName() === 'admin.usm'): ?>
                                User Management System
                            <?php elseif(in_array(Route::currentRouteName(), ['regular.prod-master', 'special.prod-master'])): ?>
                                Product Master List
                            <?php elseif(in_array(Route::currentRouteName(), ['regular.supplier-master', 'special.supplier-master'])): ?>
                                Supplier Master List
                            <?php elseif(in_array(Route::currentRouteName(), ['regular.customer-master', 'special.customer-master'])): ?>
                                Customer Master List
                            <?php elseif(in_array(Route::currentRouteName(), ['regular.forwarder-master', 'special.forwarder-master'])): ?>
                                Forwarder Master List
                            <?php elseif(in_array(Route::currentRouteName(), ['regular.inv-adjust', 'special.inv-adjust'])): ?>
                                Inventory Adjustment
                            <?php elseif(Route::currentRouteName() === 'special.sales-junk'): ?>
                                Junk Items
                            <?php elseif(Route::currentRouteName() === 'special.item-convert'): ?>
                                Convert Items
                            <?php elseif(in_array(Route::currentRouteName(), ['regular.purchase-note', 'special.purchase-note'])): ?>
                                Purchasing Note
                            <?php elseif(in_array(Route::currentRouteName(), ['regular.purchase-order', 'special.purchase-order'])): ?>
                                Purchase Order
                            <?php elseif(in_array(Route::currentRouteName(), ['regular.purchase-return', 'special.purchase-return'])): ?>
                                Purchase Return
                            <?php elseif(Route::currentRouteName() === 'special.purchase-viber'): ?>
                                Purchase Entry
                            <?php elseif(in_array(Route::currentRouteName(), ['regular.sales-note', 'special.sales-note'])): ?>
                                Sales Note
                            <?php elseif(in_array(Route::currentRouteName(), ['regular.sales-order', 'special.sales-order'])): ?>
                                Sales Order
                            <?php elseif(in_array(Route::currentRouteName(), ['regular.sales-return', 'special.sales-return'])): ?>
                                Sales Return
                            <?php elseif(in_array(Route::currentRouteName(), ['regular.consignment-invoice', 'special.consignment-invoice'])): ?>
                                Consignment Invoice
                            <?php elseif(in_array(Route::currentRouteName(), ['regular.waybill', 'special.waybill'])): ?>
                                Waybill
                            <?php elseif(Route::currentRouteName() === 'admin.archived'): ?>
                                Archived
                            <?php elseif(Route::currentRouteName() === 'admin.sales-report'): ?>
                                Statement Of Account
                            <?php elseif(Route::currentRouteName() === 'admin.inventory-reports'): ?>
                                Inventory Reports
                            <?php elseif(Route::currentRouteName() === 'admin.accounts-receivable'): ?>
                                Accounts Receivable
                            <?php elseif(Route::currentRouteName() === 'admin.datasync'): ?>
                                Data Sync
                            <?php elseif(Route::currentRouteName() === 'admin.audit-trail'): ?>
                                Audit Trail
                            <?php elseif(in_array(Route::currentRouteName(), ['regular.payments', 'special.payments'])): ?>
                                Payments Management
                            <?php elseif(in_array(Route::currentRouteName(), ['regular.payable-cheque-voucher', 'special.payable-cheque-voucher'])): ?>
                                Payable Cheque Voucher
                            <?php elseif(in_array(Route::currentRouteName(), ['regular.expense-cheque-voucher', 'special.expense-cheque-voucher'])): ?>
                                Expense Cheque Voucher
                            <?php elseif(in_array(Route::currentRouteName(), ['regular.sales-report', 'special.sales-report'])): ?>
                                Stament Of Account
                            <?php elseif(in_array(Route::currentRouteName(), ['regular.inventory-reports', 'special.inventory-reports'])): ?>
                                Inventory Reports
                            <?php elseif(Route::currentRouteName() === 'special.unserved-report'): ?>
                                Unserved Details
                            <?php elseif(in_array(Route::currentRouteName(), ['regular.accounts-receivable', 'special.accounts-receivable'])): ?>
                                Accounts Receivable
                            <?php elseif(in_array(Route::currentRouteName(), ['regular.cost-report', 'special.cost-report'])): ?>
                                Cost Report
                            <?php elseif(Route::currentRouteName() === 'special.top-products'): ?>
                                Top Products Sales
                            <?php else: ?>
                                Dashboard Overview
                            <?php endif; ?>
                        </h1>
                    </div>
                </div>

                <!-- PAGE: DASHBOARD (Active by Default) -->
                <div id="page-dashboard" class="page-view space-y-6 <?php echo e(in_array(Route::currentRouteName(), ['regular.dashboard', 'special.dashboard']) ? '' : 'hidden'); ?>">
                    <?php echo $__env->yieldContent('dashboard_content'); ?>
                </div>

                <!-- PAGE: SYSTEM SECURITY -> USM -->
                <div id="page-usm" class="page-view space-y-6 <?php echo e(Route::currentRouteName() === 'admin.usm' ? '' : 'hidden'); ?>">
                    <?php echo $__env->yieldContent('usm_content'); ?>
                </div>
                
                <!-- PAGE: SYSTEM SECURITY -> ARCHIVED -->
                <div id="page-archived" class="page-view space-y-6 <?php echo e(Route::currentRouteName() === 'admin.archived' ? '' : 'hidden'); ?>">
                    <?php echo $__env->yieldContent('archived_content'); ?>
                </div>
                
                <!-- PAGE: SYSTEM SECURITY -> DATA SYNC -->
                <div id="page-datasync" class="page-view space-y-6 <?php echo e(Route::currentRouteName() === 'admin.datasync' ? '' : 'hidden'); ?>">
                    <?php echo $__env->yieldContent('datasync_content'); ?>
                </div>

                <!-- PAGE: MASTER LIST -> PRODUCT MASTER -->
                <div id="page-prod-master-container" class="page-view space-y-6 <?php echo e(in_array(Route::currentRouteName(), ['regular.prod-master', 'special.prod-master']) ? '' : 'hidden'); ?>">
                    <?php echo $__env->yieldContent('prod_master_content'); ?>
                </div>

                <!-- PAGE: MASTER LIST -> ONLINE PRODUCT CONFIG -->
                <div id="page-prod-online-config-container" class="page-view space-y-6 <?php echo e(Route::currentRouteName() === 'special.prod-online-config' ? '' : 'hidden'); ?>">
                    <?php echo $__env->yieldContent('prod_online_config_content'); ?>
                </div>

                <!-- PAGE: MASTER LIST -> SUPPLIER MASTER -->
                <div id="page-supplier-master-container" class="page-view space-y-6 <?php echo e(in_array(Route::currentRouteName(), ['regular.supplier-master', 'special.supplier-master']) ? '' : 'hidden'); ?>">
                    <?php echo $__env->yieldContent('supplier_master_content'); ?>
                </div>

                <!-- PAGE: MASTER LIST -> CUSTOMER MASTER -->
                <div id="page-customer-master-container" class="page-view space-view space-y-6 <?php echo e(in_array(Route::currentRouteName(), ['regular.customer-master', 'special.customer-master']) ? '' : 'hidden'); ?>">
                    <?php echo $__env->yieldContent('customer_master_content'); ?>
                </div>

                <!-- PAGE: PURCHASING -> PURCHASING NOTE -->
                <div id="page-purchase-note-container" class="page-view space-y-6 <?php echo e(in_array(Route::currentRouteName(), ['regular.purchase-note', 'special.purchase-note']) ? '' : 'hidden'); ?>">
                    <?php echo $__env->yieldContent('purchase_note_content'); ?>
                </div>

                <!-- PAGE: PURCHASING -> PURCHASE ORDER -->
                <div id="page-purchase-order-container" class="page-view space-y-6 <?php echo e(in_array(Route::currentRouteName(), ['regular.purchase-order', 'special.purchase-order']) ? '' : 'hidden'); ?>">
                    <?php echo $__env->yieldContent('purchase_order_content'); ?>
                </div>

                <!-- PAGE: PURCHASING -> PURCHASE RETURN -->
                <div id="page-purchase-return-container" class="page-view space-y-6 <?php echo e(in_array(Route::currentRouteName(), ['regular.purchase-return', 'special.purchase-return']) ? '' : 'hidden'); ?>">
                    <?php echo $__env->yieldContent('purchase_return_content'); ?>
                </div>

                <!-- PAGE: PURCHASING -> PURCHASE VIBER LIST -->
                <div id="page-purchase-viber-container" class="page-view space-y-6 <?php echo e(in_array(Route::currentRouteName(), ['special.purchase-viber']) ? '' : 'hidden'); ?>">
                    <?php echo $__env->yieldContent('purchase_viber_content'); ?>
                </div>

                <!-- PAGE: SALES -> SALES NOTE -->
                <div id="page-sales-note-container" class="page-view space-y-6 <?php echo e(in_array(Route::currentRouteName(), ['regular.sales-note', 'special.sales-note', 'admin.sales-note.online-prices', 'admin.sales-note.report-redirect']) ? '' : 'hidden'); ?>">
                    <?php echo $__env->yieldContent('sales_note_content'); ?>
                </div>

                <!-- PAGE: SALES -> SALES ORDER -->
                <div id="page-sales-order-container" class="page-view space-y-6 <?php echo e(in_array(Route::currentRouteName(), ['regular.sales-order', 'special.sales-order']) ? '' : 'hidden'); ?>">
                    <?php echo $__env->yieldContent('sales_order_content'); ?>
                </div>

                <!-- PAGE: SALES -> CONSIGNMENT INVOICE -->
                <div id="page-cons-invoice" class="page-view space-y-6 <?php echo e(in_array(Route::currentRouteName(), ['regular.consignment-invoice', 'special.consignment-invoice']) ? '' : 'hidden'); ?>">
                    <?php echo $__env->yieldContent('cons_invoice_content'); ?>
                </div>

                <!-- PAGE: SALES -> WAYBILL -->
                <div id="page-waybill" class="page-view space-y-6 <?php echo e(in_array(Route::currentRouteName(), ['regular.waybill', 'special.waybill']) ? '' : 'hidden'); ?>">
                    <?php echo $__env->yieldContent('waybill_content'); ?>
                </div>

                <!-- PAGE: MASTER LIST -> FORWARDER MASTER -->
                <div id="page-forwarder-master-container" class="page-view space-y-6 <?php echo e(in_array(Route::currentRouteName(), ['regular.forwarder-master', 'special.forwarder-master']) ? '' : 'hidden'); ?>">
                    <?php echo $__env->yieldContent('forwarder_master_content'); ?>
                </div>

                <!-- PAGE: INVENTORY -> INVENTORY ADJUSTMENT -->
                <div id="page-inv-adjust-container" class="page-view space-y-6 <?php echo e(in_array(Route::currentRouteName(), ['regular.inv-adjust', 'special.inv-adjust']) ? '' : 'hidden'); ?>">
                    <?php echo $__env->yieldContent('inventory_adjustment_content'); ?>
                </div>

                <!-- PAGE: INVENTORY -> JUNK ITEMS -->
                <div id="page-sales-junk-container" class="page-view space-y-6 <?php echo e(in_array(Route::currentRouteName(), ['special.sales-junk', 'warehouse.sales-junk']) ? '' : 'hidden'); ?>">
                    <?php echo $__env->yieldContent('sales_junk_content'); ?>
                </div>

                <!-- PAGE: INVENTORY -> ITEM CONVERT -->
                <div id="page-item-convert-container" class="page-view space-y-6 <?php echo e(in_array(Route::currentRouteName(), ['special.item-convert', 'warehouse.item-convert']) ? '' : 'hidden'); ?>">
                    <?php echo $__env->yieldContent('item_convert_content'); ?>
                </div>

                <!-- PAGE: SALES -> SALES RETURN -->
                <div id="page-sales-return-container" class="page-view space-y-6 <?php echo e(in_array(Route::currentRouteName(), ['regular.sales-return', 'special.sales-return']) ? '' : 'hidden'); ?>">
                    <?php echo $__env->yieldContent('sales_return_content'); ?>
                </div>

                <!-- PAGE: ACCOUNTING -> PAYMENTS -->
                <div id="page-payments" class="page-view space-y-6 <?php echo e(in_array(Route::currentRouteName(), ['regular.payments', 'special.payments']) ? '' : 'hidden'); ?>">
                    <?php echo $__env->yieldContent('payments_content'); ?>
                </div>

                <!-- PAGE: ADMIN PROFILE -->
                <div id="page-admin-profile" class="page-view space-y-6 <?php echo e(Route::currentRouteName() === 'special.profile' ? '' : 'hidden'); ?>">
                    <?php echo $__env->yieldContent('admin_profile_content'); ?>
                </div>

                <!-- PAGE: ACCOUNTING -> PAYABLE CHEQUE VOUCHER -->
                <div id="page-payable-cheque-voucher-container" class="page-view space-y-6 <?php echo e(in_array(Route::currentRouteName(), ['regular.payable-cheque-voucher', 'special.payable-cheque-voucher']) ? '' : 'hidden'); ?>">
                    <?php echo $__env->yieldContent('payable_cheque_voucher_content'); ?>
                </div>

                <!-- PAGE: ACCOUNTING -> EXPENSE CHEQUE VOUCHER -->
                <div id="page-exp-cheque" class="page-view space-y-6 <?php echo e(in_array(Route::currentRouteName(), ['regular.expense-cheque-voucher', 'special.expense-cheque-voucher']) ? '' : 'hidden'); ?>">
                    <?php echo $__env->yieldContent('expense_cheque_voucher_content'); ?>
                </div>
                <!-- PAGE: REPORTS -> INVENTORY REPORTS -->
                <div id="page-rep-inv" class="page-view space-y-6 <?php echo e(in_array(Route::currentRouteName(), ['regular.inventory-reports', 'special.inventory-reports']) ? '' : 'hidden'); ?>">
                    <?php echo $__env->yieldContent('inventory_reports_content'); ?>
                </div>
                <!-- PAGE: REPORTS -> SALES REPORTS -->
                <div id="page-rep-sales" class="page-view space-y-6 <?php echo e(in_array(Route::currentRouteName(), ['regular.sales-report', 'special.sales-report', 'special.top-products']) ? '' : 'hidden'); ?>">
                    <?php echo $__env->yieldContent('sales_report_content'); ?>
                </div>
                <!-- PAGE: REPORTS -> UNSERVED DETAILS -->
                <div id="page-rep-unserved" class="page-view space-y-6 <?php echo e(Route::currentRouteName() === 'special.unserved-report' ? '' : 'hidden'); ?>">
                    <?php echo $__env->yieldContent('unserved_report_content'); ?>
                </div>
                <!-- PAGE: REPORTS -> ACCOUNTS RECEIVABLE -->
                <div id="page-rep-ar" class="page-view space-y-6 <?php echo e(in_array(Route::currentRouteName(), ['regular.accounts-receivable', 'special.accounts-receivable']) ? '' : 'hidden'); ?>">
                    <?php echo $__env->yieldContent('accounts_receivable_content'); ?>
                </div>
                <!-- PAGE: REPORTS -> COST REPORT -->
                <div id="page-rep-cost" class="page-view space-y-6 <?php echo e(in_array(Route::currentRouteName(), ['regular.cost-report', 'special.cost-report']) ? '' : 'hidden'); ?>">
                    <?php echo $__env->yieldContent('cost_report_content'); ?>
                </div>

                <!-- PAGE: GENERIC DYNAMIC CONTENT PLACEHOLDER -->
                <div id="page-placeholder" class="page-view hidden space-y-6">
                    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-8 min-h-[400px] flex flex-col justify-center items-center text-center">
                        <div class="p-4 bg-maroon-50 text-maroon-900 rounded-full mb-4">
                            <i data-lucide="file-question" class="w-8 h-8 text-maroon-800"></i>
                        </div>
                        <h3 id="placeholder-page-name" class="text-lg font-bold text-gray-900">Workspace View</h3>
                        <p class="text-sm text-gray-500 max-w-md mt-1">This section is completely set up and waiting for your custom layout or API integration to populate data.</p>
                    </div>
                </div>

            </div>

            <!-- Toast alert box for modern visual response -->
            <div id="toast-message" class="fixed bottom-6 right-6 bg-maroon-950 text-white border-l-4 border-goldlining-500 px-4 py-3.5 rounded-xl shadow-2xl flex items-center space-x-3 transform translate-y-20 opacity-0 transition-all duration-300 pointer-events-none z-50">
                <i data-lucide="check-circle" class="w-5 h-5 text-goldlining-400"></i>
                <span id="toast-text" class="text-xs font-bold">Operation Successful</span>
            </div>

            <!-- LOGOUT CONFIRMATION MODAL -->
            <div id="confirm-logout-modal" class="fixed inset-0 z-[100] overflow-y-auto hidden">
                <div class="flex items-center justify-center min-h-screen p-4">
                    <div class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
                    <div class="relative bg-white rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl">
                        <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4 text-amber-600">
                            <i data-lucide="log-out" class="w-8 h-8"></i>
                        </div>
                        <h3 class="text-lg font-bold text-slate-800 mb-2">Confirm Logout</h3>
                        <p class="text-sm text-slate-500 mb-6">Are you sure you want to end your current session? Any unsaved changes may be lost.</p>
                        <div class="flex gap-3 justify-center">
                            <button onclick="toggleLogoutModal(false)" class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-bold rounded-xl">Cancel</button>
                            <button onclick="executeLogout()" class="px-6 py-2 bg-maroon text-white text-xs font-bold rounded-xl shadow-md">Yes, Logout</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- LOGOUT SUCCESS MODAL -->
            <div id="success-logout-modal" class="fixed inset-0 z-[110] overflow-y-auto hidden">
                <div class="flex items-center justify-center min-h-screen p-4">
                    <div class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
                    <div class="relative bg-white rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl">
                        <div class="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4 text-emerald-600">
                            <i data-lucide="check-circle" class="w-8 h-8"></i>
                        </div>
                        <h3 class="text-lg font-bold text-slate-800 mb-2">Logged Out</h3>
                        <p class="text-sm text-slate-500 mb-6">You have been securely logged out. Redirecting to login...</p>
                        <div class="flex justify-center">
                            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-maroon"></div>
                        </div>
                    </div>
                </div>
            </div>

            <?php echo $__env->yieldContent('extra_content'); ?>
            <?php echo $__env->yieldPushContent('modals'); ?>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

    <!-- Floating Chat Button & Chat Box -->
    <div id="chat-float-btn" class="fixed bottom-6 right-6 w-14 h-14 bg-maroon-900 border-2 border-goldlining-500 rounded-full flex items-center justify-center cursor-grab shadow-2xl z-[200] select-none" style="touch-action:none;">
        <i data-lucide="message-circle" class="w-7 h-7 text-goldlining-400 pointer-events-none"></i>
    </div>

    <div id="chat-box" class="fixed hidden flex flex-col" style="width:360px;height:520px;z-index:200;border-radius:16px;background:#fff;border:1px solid #e5e7eb;box-shadow:0 20px 60px rgba(0,0,0,0.25);overflow:hidden;">
        <div class="bg-maroon-900 text-white px-4 py-3 flex items-center justify-between flex-shrink-0" id="chat-header" style="cursor:move;">
            <span class="font-bold text-sm flex items-center gap-2"><i data-lucide="message-circle" class="w-4 h-4 text-goldlining-400"></i> AI Chat</span>
            <div class="flex items-center gap-2">
                <span id="chat-usage-indicator" class="text-[10px] text-gray-300 bg-maroon-800 px-2 py-0.5 rounded-full hidden">-</span>
                <button id="chat-close-btn" class="text-gray-300 hover:text-white p-0.5 text-lg leading-none" type="button">&times;</button>
            </div>
        </div>

        <div id="chat-messages" class="flex-1 overflow-y-auto p-3 flex flex-col gap-3 text-sm" style="background:#f9fafb;">
            <div class="flex items-center justify-center h-full text-gray-400 text-xs">Start a conversation with AI</div>
        </div>

        <div id="chat-typing" class="hidden px-3 py-1.5 text-xs text-gray-500 flex items-center gap-2 bg-white border-t border-gray-100">
            <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0s"></span>
            <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0.15s"></span>
            <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0.3s"></span>
            <span>AI is thinking...</span>
        </div>

        <div id="chat-image-preview" class="hidden px-3 py-2 bg-gray-50 border-t border-gray-200 flex items-center gap-2 text-xs text-gray-600">
            <span id="chat-image-name" class="flex-1 truncate"></span>
            <button id="chat-image-remove" class="text-red-500 hover:text-red-700 font-bold">&times;</button>
        </div>

        <div id="chat-voice-status" class="hidden px-3 py-1.5 bg-red-50 border-t border-gray-200 flex items-center gap-2 text-xs text-red-600">
            <span class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
            <span id="chat-voice-text">Listening...</span>
            <span id="chat-voice-interim" class="text-gray-400 italic truncate flex-1 text-right"></span>
        </div>

        <div class="border-t border-gray-200 p-2 flex items-center gap-2 bg-white flex-shrink-0">
            <button id="chat-image-btn" class="p-2 rounded-lg hover:bg-gray-100 text-gray-500 flex-shrink-0" title="Upload image">
                <i data-lucide="image" class="w-4 h-4"></i>
            </button>
            <input type="file" id="chat-image-input" accept="image/*" class="hidden">
            <button id="chat-voice-btn" class="p-2 rounded-lg hover:bg-gray-100 text-gray-500 flex-shrink-0" title="Voice input">
                <i data-lucide="mic" class="w-4 h-4"></i>
            </button>
            <input type="text" id="chat-input" placeholder="Ask anything..." class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-goldlining-500" style="min-width:0;">
            <button id="chat-send-btn" class="bg-maroon-900 text-white px-3 py-2 rounded-lg text-xs font-bold hover:bg-maroon-800 flex-shrink-0">Send</button>
        </div>

        <div id="chat-usage-bar" class="hidden px-3 py-1 bg-gray-50 border-t border-gray-200 flex items-center justify-between text-[10px] text-gray-500 flex-shrink-0">
            <span id="chat-usage-text">API: -</span>
            <span id="chat-reset-text">Reset: -</span>
        </div>
    </div>

    <script>
        function toggleLogoutModal(show) {
            const modal = document.getElementById('confirm-logout-modal');
            if (modal) {
                if (show) modal.classList.remove('hidden');
                else modal.classList.add('hidden');
            }
        }

        function executeLogout() {
            toggleLogoutModal(false);
            window.location.href = '<?php echo e(url("/logout")); ?>';
        }

        // ── Gemini AI Chat ──
        (function(){
            var btn = document.getElementById('chat-float-btn');
            var box = document.getElementById('chat-box');
            var closeBtn = document.getElementById('chat-close-btn');
            var messagesEl = document.getElementById('chat-messages');
            var inputEl = document.getElementById('chat-input');
            var sendBtn = document.getElementById('chat-send-btn');
            var typingEl = document.getElementById('chat-typing');
            var usageBar = document.getElementById('chat-usage-bar');
            var usageText = document.getElementById('chat-usage-text');
            var resetText = document.getElementById('chat-reset-text');
            var usageIndicator = document.getElementById('chat-usage-indicator');
            var imageBtn = document.getElementById('chat-image-btn');
            var imageInput = document.getElementById('chat-image-input');
            var imagePreview = document.getElementById('chat-image-preview');
            var imageName = document.getElementById('chat-image-name');
            var imageRemove = document.getElementById('chat-image-remove');
            var voiceBtn = document.getElementById('chat-voice-btn');

            if (!btn) return;

            var chatHistory = [];
            try {
                chatHistory = JSON.parse(sessionStorage.getItem('ai_chat_history')) || [];
            } catch (e) {
                chatHistory = [];
            }
            var pendingImage = null;
            var pendingImageMime = null;
            var isSending = false;
            var isRecording = false;
            var recognition = null;

            // Restore saved chat icon position
            try {
                var savedX = sessionStorage.getItem('ai_chat_pos_x');
                var savedY = sessionStorage.getItem('ai_chat_pos_y');
                if (savedX && savedY) {
                    btn.style.left = savedX;
                    btn.style.top = savedY;
                    btn.style.bottom = 'auto';
                    btn.style.right = 'auto';
                }
            } catch (e) {}

            // Drag logic
            var offsetX = 0, offsetY = 0, startX = 0, startY = 0;
            var dragged = false;
            var gap = 16;

            function positionBox() {
                var br = btn.getBoundingClientRect();
                var spaceLeft = br.left;
                var spaceRight = window.innerWidth - br.right;
                var bx = spaceRight >= box.offsetWidth + gap + 10
                    ? br.right + gap
                    : br.left - gap - box.offsetWidth;
                bx = Math.max(4, Math.min(bx, window.innerWidth - box.offsetWidth - 4));
                var by = br.top + (br.height / 2) - (box.offsetHeight / 2);
                by = Math.max(4, Math.min(by, window.innerHeight - box.offsetHeight - 4));
                box.style.left = bx + 'px';
                box.style.top = by + 'px';
                box.style.bottom = 'auto';
                box.style.right = 'auto';
            }

            function onDragStart(e) {
                var ev = e.touches ? e.touches[0] : e;
                startX = ev.clientX;
                startY = ev.clientY;
                offsetX = ev.clientX - btn.getBoundingClientRect().left;
                offsetY = ev.clientY - btn.getBoundingClientRect().top;
                dragged = false;
                btn.classList.remove('cursor-grab');
                btn.classList.add('cursor-grabbing');
                document.addEventListener('mousemove', onDragMove);
                document.addEventListener('mouseup', onDragEnd);
                document.addEventListener('touchmove', onDragMove, { passive: false });
                document.addEventListener('touchend', onDragEnd);
            }

            function onDragMove(e) {
                var ev = e.touches ? e.touches[0] : e;
                var dx = ev.clientX - startX;
                var dy = ev.clientY - startY;
                if (Math.abs(dx) > 5 || Math.abs(dy) > 5) dragged = true;
                var left = ev.clientX - offsetX;
                var top = ev.clientY - offsetY;
                left = Math.max(0, Math.min(left, window.innerWidth - btn.offsetWidth));
                top = Math.max(0, Math.min(top, window.innerHeight - btn.offsetHeight));
                btn.style.left = left + 'px';
                btn.style.top = top + 'px';
                btn.style.bottom = 'auto';
                btn.style.right = 'auto';
                if (box && !box.classList.contains('hidden')) positionBox();
                if (e.cancelable) e.preventDefault();
            }

            function onDragEnd() {
                btn.classList.remove('cursor-grabbing');
                btn.classList.add('cursor-grab');
                document.removeEventListener('mousemove', onDragMove);
                document.removeEventListener('mouseup', onDragEnd);
                document.removeEventListener('touchmove', onDragMove);
                document.removeEventListener('touchend', onDragEnd);
                if (!dragged) {
                    box.classList.toggle('hidden');
                    if (!box.classList.contains('hidden')) {
                        positionBox();
                        fetchUsage();
                        inputEl.focus();
                    }
                    lucide.createIcons();
                } else {
                    try {
                        sessionStorage.setItem('ai_chat_pos_x', btn.style.left);
                        sessionStorage.setItem('ai_chat_pos_y', btn.style.top);
                    } catch (e) {}
                }
            }

            btn.addEventListener('mousedown', onDragStart);
            btn.addEventListener('touchstart', onDragStart, { passive: true });

            if (closeBtn) {
                closeBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    box.classList.add('hidden');
                });
            }

            // ── Chat Functions ──

            function escapeHtml(text) {
                var d = document.createElement('div');
                d.textContent = text;
                return d.innerHTML;
            }

            function addMessage(role, text, skipRedirect) {
                if (skipRedirect === undefined) skipRedirect = false;
                var placeholder = messagesEl.querySelector('.flex.items-center.justify-center.h-full');
                if (placeholder) placeholder.remove();

                var div = document.createElement('div');
                var isUser = role === 'user';
                div.className = 'flex ' + (isUser ? 'justify-end' : 'justify-start') + ' gap-2';

                var bubble = document.createElement('div');
                var bg = isUser ? 'bg-maroon-900 text-white' : 'bg-white text-gray-800 border border-gray-200';
                bubble.className = 'max-w-[85%] px-3 py-2 rounded-2xl text-xs leading-relaxed ' + bg;
                bubble.style.wordBreak = 'break-word';
                bubble.style.whiteSpace = 'pre-wrap';

                if (isUser) {
                    bubble.textContent = text;
                } else {
                    bubble.innerHTML = marked.parse(text, { breaks: true });

                    // ── Auto-redirect logic ──
                    var redirectLink = null;
                    bubble.querySelectorAll('a').forEach(function(a) {
                        var linkText = (a.textContent || '').trim();
                        if (linkText === '[REDIRECT]' || linkText === 'REDIRECT') {
                            redirectLink = a;
                        } else {
                            a.setAttribute('target', '_blank');
                            a.setAttribute('rel', 'noopener');
                        }
                    });

                    if (redirectLink) {
                        var redirectUrl = redirectLink.getAttribute('href');
                        var pill = document.createElement('span');
                        pill.style.cssText = 'display:inline-flex;align-items:center;gap:5px;background:#4A0A15;color:#FFC72C;font-size:10px;font-weight:700;padding:3px 10px;border-radius:999px;margin-top:6px;';
                        pill.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg> Redirecting...';
                        redirectLink.replaceWith(pill);

                        if (!skipRedirect) {
                            setTimeout(function() {
                                window.location.href = redirectUrl;
                            }, 900);
                        }
                    }
                }

                div.appendChild(bubble);
                messagesEl.appendChild(div);
                messagesEl.scrollTop = messagesEl.scrollHeight;
            }

            function setLoading(loading) {
                isSending = loading;
                typingEl.classList.toggle('hidden', !loading);
                sendBtn.disabled = loading;
                sendBtn.classList.toggle('opacity-50', loading);
                inputEl.disabled = loading;
                if (!loading) inputEl.focus();
            }

            function speakAIResponse(text) {
                if (!window.speechSynthesis) return;
                window.speechSynthesis.cancel();
                var clean = text.replace(/[*#`\[\]()>|~_\-]/g, ' ')
                    .replace(/\s+/g, ' ').trim();
                if (clean.length === 0) return;
                var utterance = new SpeechSynthesisUtterance(clean);
                utterance.lang = 'en-US';
                utterance.rate = 1.0;
                utterance.pitch = 1.0;
                var vs = document.getElementById('chat-voice-status');
                var vt = document.getElementById('chat-voice-text');
                var vi = document.getElementById('chat-voice-interim');
                if (vs && vt) {
                    vs.classList.remove('hidden');
                    vt.textContent = 'Speaking...';
                    if (vi) vi.textContent = '';
                }
                utterance.onend = function() {
                    if (isRecording && vt) {
                        vt.textContent = 'Listening...';
                        if (vi) vi.textContent = 'Waiting for speech...';
                    }
                };
                utterance.onerror = function() {
                    if (isRecording && vt) {
                        vt.textContent = 'Listening...';
                        if (vi) vi.textContent = 'Waiting for speech...';
                    }
                };
                window.speechSynthesis.speak(utterance);
            }

            function sendMessage() {
                var text = inputEl.value.trim();
                if (!text && !pendingImage) return;
                if (isSending) return;

                var messageText = text || '(image attached)';
                addMessage('user', messageText);
                inputEl.value = '';

                // ── Slow Moving Products trigger ──
                if (/^slow moving products$/i.test(text)) {
                    addMessage('model', 'Slow moving products are products with little or no outgoing movement.');
                    setLoading(true);
                    var genUrl = '<?php echo e(route("special.ai.slow-moving-products.generate")); ?>';
                    fetch(genUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    }).then(function(r) { return r.json(); }).then(function(data) {
                        setLoading(false);
                        if (data.success) {
                            addMessage('model', data.message);
                            if (data.attachments && data.attachments.length > 0) {
                                data.attachments.forEach(function(att) { addFileAttachment(att); });
                            }
                        } else {
                            addMessage('model', 'Unable to generate the Excel report.');
                        }
                    }).catch(function(err) {
                        setLoading(false);
                        addMessage('model', 'Unable to generate the Excel report.');
                    });
                    clearImage();
                    return;
                }

                setLoading(true);

                fetch('<?php echo e(route("admin.chat.send")); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        message: text || 'Describe this image',
                        image: pendingImage,
                        image_mime: pendingImageMime,
                        history: chatHistory
                    })
                }).then(function(r) { return r.json(); }).then(function(data) {
                    setLoading(false);
                    clearImage();
                    if (data.success) {
                        chatHistory.push({ role: 'user', text: messageText });
                        chatHistory.push({ role: 'model', text: data.text });
                        try {
                            sessionStorage.setItem('ai_chat_history', JSON.stringify(chatHistory));
                        } catch (e) {}
                        addMessage('model', data.text);
                        // Render file attachments if present
                        if (data.attachments && data.attachments.length > 0) {
                            data.attachments.forEach(function(att) { addFileAttachment(att); });
                        }
                        updateUsage(data.dailyRemaining, data.dailyLimit, data.resetTime);
                        if (isRecording && window.speechSynthesis) {
                            speakAIResponse(data.text);
                        }
                    } else {
                        addMessage('model', 'Error: ' + (data.error || 'Failed to get response'));
                    }
                }).catch(function(err) {
                    setLoading(false);
                    addMessage('model', 'Network error: ' + err.message);
                });
            }

            function updateUsage(remaining, limit, reset) {
                var show = (remaining !== undefined && remaining !== null);
                usageBar.classList.toggle('hidden', !show);
                usageIndicator.classList.toggle('hidden', !show);
                if (show) {
                    usageText.textContent = 'API: ' + remaining + '/' + limit + ' remaining';
                    resetText.textContent = 'Reset: ' + reset;
                    usageIndicator.textContent = remaining + '/' + limit;
                }
            }

            function fetchUsage() {
                fetch('<?php echo e(route("admin.chat.usage")); ?>', {
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
                }).then(function(r) { return r.json(); }).then(function(data) {
                    updateUsage(data.dailyRemaining, data.dailyLimit, data.resetTime);
                }).catch(function() {});
            }

            function clearImage() {
                pendingImage = null;
                pendingImageMime = null;
                imagePreview.classList.add('hidden');
                imageInput.value = '';
            }

            // ── File attachment card ──
            function addFileAttachment(att) {
                var div = document.createElement('div');
                div.className = 'flex justify-start gap-2';

                var card = document.createElement('div');
                card.className = 'max-w-[85%] px-3 py-2 rounded-2xl text-xs bg-white text-gray-800 border border-gray-200';

                var sizeStr = '';
                if (att.file_size) {
                    var kb = Math.round(att.file_size / 1024);
                    sizeStr = kb >= 1024 ? (kb / 1024).toFixed(1) + ' MB' : kb + ' KB';
                }
                var metaParts = ['Microsoft Excel Spreadsheet'];
                if (att.record_count) metaParts.push(att.record_count + ' products');
                if (sizeStr) metaParts.push(sizeStr);

                card.innerHTML =
                    '<div class="ai-file-attachment cursor-pointer" data-download-url="' + escapeHtml(att.download_url || '') + '" data-filename="' + escapeHtml(att.file_name || '') + '">' +
                        '<div class="flex items-center gap-3 p-1">' +
                            '<div class="flex-shrink-0 w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">' +
                                '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>' +
                            '</div>' +
                            '<div class="flex-1 min-w-0">' +
                                '<div class="font-medium text-gray-900 truncate">' + escapeHtml(att.file_name || '') + '</div>' +
                                '<div class="text-gray-500 truncate">' + escapeHtml(metaParts.join(' • ')) + '</div>' +
                            '</div>' +
                            '<button class="ai-file-dl-btn flex-shrink-0 w-8 h-8 bg-gray-100 hover:bg-gray-200 rounded-full flex items-center justify-center transition-colors">' +
                                '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>' +
                            '</button>' +
                        '</div>' +
                    '</div>';

                card.querySelector('.ai-file-attachment').addEventListener('click', function(e) {
                    downloadFile(this.dataset.downloadUrl, this.dataset.filename);
                });

                div.appendChild(card);
                messagesEl.appendChild(div);
                messagesEl.scrollTop = messagesEl.scrollHeight;
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }

            function downloadFile(url, filename) {
                if (!url) return;
                var a = document.createElement('a');
                a.href = url;
                a.download = filename || '';
                a.style.display = 'none';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            }

            // ── Image Upload ──
            if (imageBtn && imageInput) {
                imageBtn.addEventListener('click', function() { imageInput.click(); });

                imageInput.addEventListener('change', function() {
                    var file = imageInput.files[0];
                    if (!file) return;
                    if (!file.type.startsWith('image/')) { clearImage(); return; }
                    imageName.textContent = file.name;
                    imagePreview.classList.remove('hidden');
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        pendingImage = e.target.result.split(',')[1];
                        pendingImageMime = file.type;
                    };
                    reader.readAsDataURL(file);
                });

                if (imageRemove) {
                    imageRemove.addEventListener('click', clearImage);
                }
            }

            // ── Voice Conversation Mode ──
            if (voiceBtn) {
                var SpeechRecognitionAPI = window.SpeechRecognition || window.webkitSpeechRecognition;
                var voiceStatus = document.getElementById('chat-voice-status');
                var voiceText = document.getElementById('chat-voice-text');
                var voiceInterim = document.getElementById('chat-voice-interim');
                var silenceTimer = null;
                var voiceFinalTranscript = '';

                if (SpeechRecognitionAPI) {
                    recognition = new SpeechRecognitionAPI();
                    recognition.lang = 'en-US';
                    recognition.interimResults = true;
                    recognition.continuous = true;
                    recognition.maxAlternatives = 1;

                    recognition.onresult = function(event) {
                        var interim = '';
                        var final = '';
                        for (var i = event.resultIndex; i < event.results.length; i++) {
                            if (event.results[i].isFinal) {
                                final += event.results[i][0].transcript + ' ';
                            } else {
                                interim += event.results[i][0].transcript;
                            }
                        }
                        if (final) {
                            voiceFinalTranscript += final;
                        }
                        if (interim) {
                            voiceInterim.textContent = voiceFinalTranscript + interim;
                        } else {
                            voiceInterim.textContent = voiceFinalTranscript || 'Waiting for speech...';
                        }

                        if (silenceTimer) clearTimeout(silenceTimer);
                        silenceTimer = setTimeout(function() {
                            var text = voiceFinalTranscript.trim();
                            if (text.length > 0) {
                                inputEl.value = text;
                                sendMessage();
                            }
                        }, 1500);
                    };

                    recognition.onerror = function(event) {
                        if (event.error === 'no-speech') return;
                        if (event.error === 'aborted') return;
                        stopVoiceMode();
                    };

                    recognition.onend = function() {
                        if (isRecording) {
                            try { recognition.start(); } catch(e) {}
                        }
                    };

                    function startVoiceMode() {
                        voiceFinalTranscript = '';
                        voiceInterim.textContent = 'Listening...';
                        isRecording = true;
                        voiceStatus.classList.remove('hidden');
                        voiceBtn.classList.add('text-red-500', 'animate-pulse');
                        voiceBtn.title = 'Click to stop voice conversation';
                        try { recognition.start(); } catch(e) {
                            stopVoiceMode();
                        }
                    }

                    function stopVoiceMode() {
                        isRecording = false;
                        if (silenceTimer) clearTimeout(silenceTimer);
                        voiceStatus.classList.add('hidden');
                        voiceBtn.classList.remove('text-red-500', 'animate-pulse');
                        voiceBtn.title = 'Voice conversation';
                        try { recognition.stop(); } catch(e) {}
                        if (window.speechSynthesis) window.speechSynthesis.cancel();
                    }

                    voiceBtn.addEventListener('click', function() {
                        if (isRecording) {
                            stopVoiceMode();
                        } else {
                            startVoiceMode();
                        }
                    });

                } else {
                    voiceBtn.classList.add('opacity-30', 'cursor-not-allowed');
                    voiceBtn.title = 'Voice not supported in this browser';
                }
            }

            function createLucideIcon(name, className) {
                var i = document.createElement('i');
                i.setAttribute('data-lucide', name);
                i.setAttribute('class', className);
                return i;
            }

            // ── Event Listeners ──
            sendBtn.addEventListener('click', sendMessage);
            inputEl.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });

            // Initialize chat history rendering
            if (chatHistory.length > 0) {
                chatHistory.forEach(function(msg) {
                    addMessage(msg.role, msg.text, true);
                });
            }

        })();

        // Universal Smart Routing URL Interceptor
        window.addEventListener('DOMContentLoaded', function() {
            var urlParams = new URLSearchParams(window.location.search);
            
            setTimeout(function() {
                urlParams.forEach(function(value, key) {
                    var match = key.match(/^(?:search|filter)\[([^\]]+)\]$/);
                    if (match && value) {
                        var colKey = match[1];
                        var inputField = document.querySelector('input[data-col="' + colKey + '"], input[name="' + colKey + '"], .column-search-input[data-col="' + colKey + '"]');
                        
                        if (!inputField) {
                            var allInputs = document.querySelectorAll('.column-search-input, input[placeholder]');
                            for (var i = 0; i < allInputs.length; i++) {
                                var placeholder = (allInputs[i].getAttribute('placeholder') || '').toLowerCase();
                                if (placeholder.includes(colKey.toLowerCase())) {
                                    inputField = allInputs[i];
                                    break;
                                }
                            }
                        }

                        if (inputField) {
                            var parentTabPane = inputField.closest('.main-tab-view, .tab-pane, [id^="view-"], [id^="tab-"]');
                            if (parentTabPane && parentTabPane.classList.contains('hidden')) {
                                var paneId = parentTabPane.id || '';
                                var tabName = paneId.replace('view-', '').replace('tab-content-', '').replace('tab-', '').replace('pane-', '');
                                if (tabName) {
                                    var autoTabBtn = document.getElementById('tab-btn-' + tabName) || document.getElementById('tab-' + tabName) || document.querySelector('[data-tab="' + tabName + '"]');
                                    if (autoTabBtn) {
                                        autoTabBtn.click();
                                    }
                                }
                            }

                            inputField.value = value;
                            inputField.dispatchEvent(new Event('input', { bubbles: true }));
                            inputField.dispatchEvent(new KeyboardEvent('keyup', { bubbles: true, key: 'Enter' }));
                        }
                    }
                });

                var tab = urlParams.get('tab');
                if (tab) {
                    var tabBtn = document.querySelector('[data-tab="' + tab + '"]');
                    if (!tabBtn) {
                        tabBtn = document.getElementById('tab-btn-' + tab) || document.getElementById('tab-' + tab);
                    }
                    if (tabBtn) {
                        tabBtn.click();
                    }
                }
            }, 250);
        });
    </script>
    <?php echo app('Illuminate\Foundation\Vite')(['resources/js/admin_sidebar_navbar.js']); ?>
    <?php echo $__env->yieldPushContent('scripts'); ?>

    <!-- Special User Notification System -->
    <script>
        function loadSpecialNotifications() {
            const baseUrl = document.querySelector('base')?.getAttribute('href') || '';
            const rushUrl = baseUrl + 'special/sales/sales-note/rush-notifications';
            const invoiceUrl = baseUrl + 'special/accounting/payments/overdue-notifications';

            Promise.all([
                fetch(rushUrl).then(r => {
                    if (!r.ok) throw new Error(`Rush API error: ${r.status}`);
                    return r.json();
                }),
                fetch(invoiceUrl).then(r => {
                    if (!r.ok) throw new Error(`Invoice API error: ${r.status}`);
                    return r.json();
                })
            ])
            .then(([rushData, invoiceData]) => {
                const rushNotifications = rushData.success ? rushData.notifications : [];
                const invoiceNotifications = invoiceData.success ? invoiceData.notifications : [];
                const totalCount = rushNotifications.length + invoiceNotifications.length;
                const badge = document.getElementById('rush-notif-badge');
                const badgeCount = document.getElementById('rush-notif-count');
                const bellIcon = document.getElementById('bell-icon');
                const notifList = document.getElementById('rush-notif-list');

                if (!badge || !badgeCount || !bellIcon || !notifList) return;

                if (totalCount > 0) {
                    badge.textContent = totalCount;
                    badge.classList.remove('hidden');
                    badge.classList.add('flex');
                    badgeCount.textContent = `${totalCount} New`;
                    bellIcon.classList.add('rush-bell-ring');

                    let html = '';
                    if (rushNotifications.length > 0) {
                        html += '<div class="px-3 py-2 bg-red-50 border-b border-red-100"><p class="text-[10px] font-bold text-red-700 uppercase">Rush Orders Overdue</p></div>';
                        rushNotifications.forEach(note => {
                            const statusClass = note.status === 'Partial' ? 'bg-blue-100 text-blue-600' : 'bg-amber-100 text-amber-600';
                            html += `
                                <a href="javascript:void(0)" onclick="navigateToSpecialRushNote('${String(note.sales_number || '').replace(/'/g, "\\'")}')" class="block p-3.5 hover:bg-gray-50 transition-colors cursor-pointer border-b border-gray-100">
                                    <div class="flex space-x-3">
                                        <div class="bg-red-100 text-red-600 p-1.5 rounded-lg h-fit">
                                            <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-xs font-semibold text-gray-800">Rush Order - ${note.sales_number}</p>
                                            <p class="text-[10px] text-gray-600 mt-0.5">${note.customer_name || '---'}</p>
                                            <div class="flex items-center gap-2 mt-1">
                                                <span class="px-2 py-0.5 ${statusClass} text-[9px] font-bold rounded-full uppercase">${note.status || 'Open'}</span>
                                                <p class="text-[10px] text-red-500 font-bold">${note.days_overdue || 0} days overdue</p>
                                            </div>
                                        </div>
                                    </div>
                                </a>`;
                        });
                    }

                    if (invoiceNotifications.length > 0) {
                        html += '<div class="px-3 py-2 bg-orange-50 border-b border-orange-100 mt-2"><p class="text-[10px] font-bold text-orange-700 uppercase">Overdue Invoices</p></div>';
                        invoiceNotifications.forEach(invoice => {
                            const customerName = String(invoice.customer_name || '').replace(/'/g, "\\'");
                            html += `
                                <a href="javascript:void(0)" onclick="navigateToSpecialInvoice('${customerName}')" class="block p-3.5 hover:bg-gray-50 transition-colors cursor-pointer border-b border-gray-100">
                                    <div class="flex space-x-3">
                                        <div class="bg-orange-100 text-orange-600 p-1.5 rounded-lg h-fit">
                                            <i data-lucide="credit-card" class="w-4 h-4"></i>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-xs font-semibold text-gray-800">${invoice.customer_name || '---'}</p>
                                            <p class="text-[10px] text-gray-600 mt-0.5">${invoice.order_number || '---'}</p>
                                            <div class="flex items-center gap-2 mt-1">
                                                <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[9px] font-bold rounded-full">PHP ${parseFloat(invoice.total_amount || 0).toLocaleString()}</span>
                                                <p class="text-[10px] text-orange-500 font-bold">${invoice.days_overdue || 0} days past ${invoice.customer_terms || 0}-day terms</p>
                                            </div>
                                        </div>
                                    </div>
                                </a>`;
                        });
                    }

                    notifList.innerHTML = html;
                } else {
                    badge.classList.add('hidden');
                    badge.classList.remove('flex');
                    badgeCount.textContent = '0 New';
                    bellIcon.classList.remove('rush-bell-ring');
                    notifList.innerHTML = `
                        <div class="p-8 text-center text-gray-400 text-sm">
                            <i data-lucide="check-circle" class="w-8 h-8 mx-auto mb-2 text-gray-300"></i>
                            <p>No urgent notifications</p>
                        </div>`;
                }

                window.overdueRushNotes = rushNotifications.map(n => n.sales_number);
                window.overdueInvoices = invoiceNotifications.map(i => i.order_number);
                window.dispatchEvent(new CustomEvent('rushNotificationsLoaded', {
                    detail: { overdueRushNotes: window.overdueRushNotes }
                }));

                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            })
            .catch(error => {
                console.error('Error loading special notifications:', error);
                const badge = document.getElementById('rush-notif-badge');
                const badgeCount = document.getElementById('rush-notif-count');
                const bellIcon = document.getElementById('bell-icon');
                const notifList = document.getElementById('rush-notif-list');
                if (badge) {
                    badge.classList.add('hidden');
                    badge.classList.remove('flex');
                }
                if (badgeCount) badgeCount.textContent = '0 New';
                if (bellIcon) bellIcon.classList.remove('rush-bell-ring');
                if (notifList) {
                    notifList.innerHTML = `
                        <div class="p-8 text-center text-gray-400 text-sm">
                            <i data-lucide="alert-circle" class="w-8 h-8 mx-auto mb-2 text-gray-300"></i>
                            <p>Unable to load notifications</p>
                        </div>`;
                }
            });
        }

        function navigateToSpecialRushNote(salesNumber) {
            sessionStorage.setItem('highlightRushNote', salesNumber);
            window.location.href = 'special/sales/sales-order';
        }

        function navigateToSpecialInvoice(customerName) {
            sessionStorage.setItem('highlightCustomerName', customerName || '');
            sessionStorage.setItem('highlightType', 'invoice');
            window.location.href = 'special/accounting/payments';
        }

        document.addEventListener('DOMContentLoaded', function() {
            loadSpecialNotifications();
            setInterval(loadSpecialNotifications, 120000);
        });
    </script>

    <!-- Special Notification and Date/Time Theme CSS -->
    <style>
        @keyframes bellRing {
            0%, 100% { transform: rotate(0deg); }
            10%, 30%, 50%, 70%, 90% { transform: rotate(-10deg); }
            20%, 40%, 60%, 80% { transform: rotate(10deg); }
        }

        .rush-bell-ring {
            animation: bellRing 1s ease-in-out infinite;
        }

        .system-theme-navbar-icon {
            width: 2.125rem;
            height: 2.125rem;
            object-fit: contain;
            display: block;
            position: relative;
            z-index: 1;
            pointer-events: none;
        }

        #theme-datetime-card {
            position: relative;
            overflow: hidden;
            min-height: 2.375rem;
            isolation: isolate;
            transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
        }

        #theme-datetime-card #live-datetime,
        #theme-datetime-card i {
            position: relative;
            z-index: 2;
        }

        #theme-datetime-card .theme-datetime-animation-wrap {
            position: absolute;
            inset: 0;
            z-index: 1;
            pointer-events: none;
            overflow: hidden;
        }

        #theme-datetime-card .theme-datetime-animation {
            position: absolute;
            z-index: 1;
            pointer-events: none;
            object-fit: contain;
            opacity: 0.34;
        }

        #theme-datetime-card[data-system-theme="christmas"] {
            background: linear-gradient(135deg, #eff6ff, #f8fafc 55%, #ecfdf5);
            border-color: #bfdbfe;
            color: #0f172a;
        }

        #theme-datetime-card[data-system-theme="christmas"] i {
            color: #166534;
        }

        #theme-datetime-card[data-system-theme="christmas"] .theme-datetime-snow {
            right: 0.35rem;
            top: 50%;
            width: 2.8rem;
            height: 2.8rem;
            transform: translateY(-50%);
            animation: themeDateTimeFloat 4.5s ease-in-out infinite;
        }

        #theme-datetime-card[data-system-theme="holy-week"] {
            background: linear-gradient(135deg, #faf5ff, #fff7ed);
            border-color: #d7b56d;
            color: #3b0764;
        }

        #theme-datetime-card[data-system-theme="holy-week"] i {
            color: #5b21b6;
        }

        #theme-datetime-card[data-system-theme="holy-week"] .theme-datetime-holy {
            right: 0.4rem;
            top: 50%;
            width: 2.75rem;
            height: 2.75rem;
            transform: translateY(-50%);
            opacity: 0.26;
            animation: themeDateTimePulse 5s ease-in-out infinite;
        }

        #theme-datetime-card[data-system-theme="halloween"] {
            background: linear-gradient(135deg, #052e16, #4B0082 58%, #9932CC);
            border-color: #22c55e;
            color: #dcfce7;
        }

        #theme-datetime-card[data-system-theme="halloween"] i {
            color: #86efac;
        }

        #theme-datetime-card[data-system-theme="halloween"] .theme-datetime-halloween {
            right: 0.2rem;
            top: 50%;
            width: 3.25rem;
            height: 3.25rem;
            transform: translateY(-50%);
            opacity: 0.72;
            filter: drop-shadow(0 0 8px rgba(134, 239, 172, 0.85));
            animation: themeDateTimeFloat 5.5s ease-in-out infinite;
        }

        #theme-datetime-card[data-system-theme="new-year"] {
            background: linear-gradient(135deg, #111827, #1d4ed8 58%, #f59e0b);
            border-color: #f6d365;
            color: #fff7ed;
        }

        #theme-datetime-card[data-system-theme="new-year"] i {
            color: #fef3c7;
        }

        #theme-datetime-card[data-system-theme="new-year"] .theme-datetime-fireworks {
            right: 0.3rem;
            top: 50%;
            width: 3rem;
            height: 3rem;
            transform: translateY(-50%);
            opacity: 0.4;
            animation: themeDateTimePulse 2.8s ease-in-out infinite;
        }

        #theme-datetime-card[data-system-theme="chinese-new-year"] {
            background: linear-gradient(135deg, #7f1d1d, #b91c1c 58%, #facc15);
            border-color: #facc15;
            color: #fff7ed;
        }

        #theme-datetime-card[data-system-theme="chinese-new-year"] i {
            color: #fef3c7;
        }

        #theme-datetime-card[data-system-theme="chinese-new-year"] .theme-datetime-dragon {
            right: 0.25rem;
            top: 50%;
            width: 3rem;
            height: 3rem;
            transform: translateY(-50%);
            opacity: 0.42;
            animation: themeDateTimeDragon 4s ease-in-out infinite;
        }

        @keyframes themeDateTimeFloat {
            0%, 100% { transform: translateY(-50%) translateX(0); }
            50% { transform: translateY(-56%) translateX(-0.2rem); }
        }

        @keyframes themeDateTimePulse {
            0%, 100% { transform: translateY(-50%) scale(0.96); }
            50% { transform: translateY(-50%) scale(1.04); }
        }

        @keyframes themeDateTimeDragon {
            0%, 100% { transform: translateY(-50%) translateX(0) scale(0.96); }
            50% { transform: translateY(-52%) translateX(-0.25rem) scale(1.04); }
        }

        @keyframes blinkRed {
            0%, 100% { background-color: #fee2e2; }
            50% { background-color: #fca5a5; }
        }

        .rush-overdue-row {
            animation: blinkRed 1.5s ease-in-out infinite;
        }

        .rush-overdue-row td {
            color: #991b1b !important;
            font-weight: 600;
        }
    </style>

    <script>
        window.addEventListener('load', function () {
            const sidebar = document.getElementById('sidebar');
            const desktopToggleBtn = document.getElementById('toggle-sidebar-desktop');

            if (!sidebar || !desktopToggleBtn) return;
            if (window.innerWidth < 768) return;

            const isHovered = sidebar.matches(':hover');
            const isExpanded = sidebar.classList.contains('w-64');

            if (!isHovered && isExpanded) {
                desktopToggleBtn.click();
            }
        });
    </script>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\hatdog\resources\views/partials/special_user/special_sidebar_navbar.blade.php ENDPATH**/ ?>