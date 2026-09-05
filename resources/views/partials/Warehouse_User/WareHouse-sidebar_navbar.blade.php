<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <base href="{{ url('/') }}/">
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
    @vite(['resources/css/admin_sidebar_navbar.css'])
    @stack('styles')
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
<body class="bg-gray-50 text-gray-800 font-sans h-screen flex flex-col overflow-hidden">
    @include('partials.global.w68-loader')


    <div class="flex flex-1 h-screen overflow-hidden">
        
        <!-- SIDEBAR CONTAINER -->
        <!-- Starts off-canvas or slim on mobile, expandable sidebar on larger layouts -->
        <aside id="sidebar" class="sidebar-transition bg-maroon-900 border-r-4 border-goldlining-500 w-64 flex flex-col h-full text-white z-[250] shadow-2xl relative no-print">
            
            <!-- Sidebar Header & Fold Toggle -->
            <div class="p-4 border-b border-maroon-800 flex items-center justify-between min-h-[70px]">
                <div class="flex items-center space-x-3 overflow-hidden" id="sidebar-logo-block">
                    <img src="{{ asset('build/assets/images/sidebar_logo.png') }}" alt="Logo" class="w-10 h-10 object-contain rounded-lg flex-shrink-0">
                    <span class="font-extrabold text-lg tracking-wide whitespace-nowrap text-goldlining-400 uppercase">w68</span>
                </div>
                <!-- Mini collapse button inside sidebar for large screens -->
                <button id="toggle-sidebar-desktop" class="p-1.5 rounded-lg bg-maroon-800 hover:bg-goldlining-500 hover:text-maroon-950 transition-colors hidden md:block" title="Toggle Sidebar">
                    <i id="toggle-icon" data-lucide="chevron-left" class="w-5 h-5"></i>
                </button>
            </div>

            <!-- Navigation Links Scroll Container -->
            <div class="flex-1 min-h-0 overflow-y-auto px-3 pt-4 pb-28 space-y-1.5 scroll-pb-28" id="nav-container">
                
                
                
            

                <div class="menu-dropdown">
                    <button onclick="toggleDropdown('dropdown-inventory', this)" class="w-full flex items-center justify-between px-3 py-3 rounded-lg hover:bg-maroon-800 text-gray-100 hover:text-white transition-all group" title="Inventory Management">
                        <div class="flex items-center space-x-3">
                            <i data-lucide="boxes" class="w-5 h-5 text-goldlining-400 group-hover:scale-110 transition-transform flex-shrink-0"></i>
                            <span class="sidebar-text text-sm whitespace-nowrap">Inventory Management</span>
                        </div>
                        <i data-lucide="chevron-down" class="dropdown-chevron w-4 h-4 text-goldlining-500 rotate-transition sidebar-text {{ in_array(Route::currentRouteName(), ['warehouse.inv-adjust', 'warehouse.sales-junk', 'warehouse.item-convert']) ? 'rotate-180' : '' }}"></i>
                    </button>
                    <div id="dropdown-inventory" class="submenu-transition overflow-hidden bg-maroon-950 bg-opacity-40 rounded-md mt-1 pl-4 space-y-1" style="{{ in_array(Route::currentRouteName(), ['warehouse.inv-adjust', 'warehouse.sales-junk', 'warehouse.item-convert']) ? 'max-height: 500px;' : 'max-height: 0px;' }}">
                        <a href="{{ route('warehouse.inv-adjust') }}" class="flex items-center space-x-2 py-2 px-3 text-xs rounded transition-all {{ Route::currentRouteName() === 'warehouse.inv-adjust' ? 'bg-goldlining-500 text-maroon-950 font-semibold shadow' : 'text-gray-300 hover:text-goldlining-400 hover:translate-x-1' }}" data-page="inv-adjust">
                            <i data-lucide="sliders-horizontal" class="w-3.5 h-3.5 {{ Route::currentRouteName() === 'warehouse.inv-adjust' ? 'text-maroon-950' : '' }}"></i>
                            <span>Inventory Adjustment</span>
                        </a>
                        <a href="{{ route('warehouse.sales-junk') }}" class="flex items-center space-x-2 py-2 px-3 text-xs rounded transition-all {{ Route::currentRouteName() === 'warehouse.sales-junk' ? 'bg-goldlining-500 text-maroon-950 font-semibold shadow' : 'text-gray-300 hover:text-goldlining-400 hover:translate-x-1' }}" data-page="sales-junk">
                            <i data-lucide="archive" class="w-3.5 h-3.5 {{ Route::currentRouteName() === 'warehouse.sales-junk' ? 'text-maroon-950' : '' }}"></i>
                            <span>Junk Items</span>
                        </a>
                         <a href="{{ route('warehouse.item-convert') }}" class="flex items-center space-x-2 py-2 px-3 text-xs rounded transition-all {{ Route::currentRouteName() === 'warehouse.item-convert' ? 'bg-goldlining-500 text-maroon-950 font-semibold shadow' : 'text-gray-300 hover:text-goldlining-400 hover:translate-x-1' }}" data-page="item-convert">
                            <i data-lucide="shuffle" class="w-3.5 h-3.5 {{ Route::currentRouteName() === 'warehouse.item-convert' ? 'text-maroon-950' : '' }}"></i>
                            <span>Convert Items</span>
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
                    <div class="hidden sm:flex items-center space-x-2.5 text-xs md:text-sm text-gray-600 bg-gray-50 border border-gray-200 px-4 py-2 rounded-lg font-medium shadow-sm">
                        <i data-lucide="calendar" class="w-4 h-4 text-maroon-900"></i>
                        <span id="live-datetime" class="font-sans">Loading system time...</span>
                    </div>
                </div>

                <!-- Right Side Widgets: Notification, Profile Trigger -->
                <div class="flex items-center space-x-3">
                    
                    <!-- Notification Bell -->
                    <div class="relative">
                        <button id="notif-btn" class="p-2 rounded-full hover:bg-gray-100 text-gray-600 relative transition-all" title="View Notifications">
                            <span class="absolute top-1 right-1 bg-red-500 text-white font-bold rounded-full w-4 h-4 flex items-center justify-center text-[9px] animate-bounce">3</span>
                            <i data-lucide="bell" class="w-5.5 h-5.5"></i>
                        </button>
                        
                        <!-- Notification Panel (Hidden by default) -->
                        <div id="notif-dropdown" class="hidden absolute right-0 mt-3 w-80 bg-white border border-gray-200 rounded-xl shadow-xl z-50 overflow-hidden transform origin-top-right transition-all">
                            <div class="p-3 bg-maroon-900 text-white font-bold flex justify-between items-center text-sm border-b-2 border-goldlining-500">
                                <span>Recent System Alerts</span>
                                <span class="bg-goldlining-500 text-maroon-900 text-xs px-1.5 py-0.5 rounded-full font-semibold">3 New</span>
                            </div>
                            <div class="max-h-72 overflow-y-auto divide-y divide-gray-100">
                                <a href="#" class="block p-3.5 hover:bg-gray-50 transition-colors">
                                    <div class="flex space-x-3">
                                        <div class="bg-red-100 text-red-600 p-1.5 rounded-lg h-fit"><i data-lucide="alert-octagon" class="w-4 h-4"></i></div>
                                        <div>
                                            <p class="text-xs font-semibold text-gray-800">Suspicious Login Blocked</p>
                                            <p class="text-[10px] text-gray-500 mt-0.5">IP 192.168.1.45 • 2 mins ago</p>
                                        </div>
                                    </div>
                                </a>
                                <a href="#" class="block p-3.5 hover:bg-gray-50 transition-colors">
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
                            @if($sidebarAvatarUrl)
                                <img src="{{ $sidebarAvatarUrl }}" alt="{{ $sidebarFullName }}" class="w-9 h-9 rounded-full border-2 border-maroon-900 shadow-sm object-cover">
                            @else
                                <div class="w-9 h-9 rounded-full border-2 border-maroon-900 bg-maroon-800 flex items-center justify-center">
                                    <i data-lucide="user" class="w-5 h-5 text-goldlining-400"></i>
                                </div>
                            @endif
                            <div class="hidden lg:block text-left">
                                <h5 class="text-xs font-bold text-gray-800 leading-tight">{{ $sidebarFullName }}</h5>
                                <span class="text-[10px] text-gray-500 block">{{ $sidebarRoleLabel }}</span>
                            </div>
                        </button>
                        
                        <!-- Profile Action Dropdown matched with sidebar color profile and gold lining -->
                        <div id="profile-dropdown" class="hidden absolute right-0 mt-3 w-48 bg-maroon-900 border border-goldlining-500 rounded-xl shadow-xl z-50 overflow-hidden py-1 text-white">
                            <a href="{{ route('warehouse.profile') }}" class="flex items-center space-x-2.5 px-4 py-2.5 text-sm text-gray-100 hover:bg-maroon-800 hover:text-goldlining-400 transition-colors">
                                <i data-lucide="user" class="w-4 h-4 text-goldlining-400"></i>
                                <span>Profile</span>
                            </a>
                            <a href="#" onclick="switchView('settings')" class="flex items-center space-x-2.5 px-4 py-2.5 text-sm text-gray-100 hover:bg-maroon-800 hover:text-goldlining-400 transition-colors">
                                <i data-lucide="settings" class="w-4 h-4 text-goldlining-400"></i>
                                <span>Settings</span>
                            </a>
                            <hr class="border-maroon-800">
                            <a href="#" onclick="toggleLogoutModal(true)" class="flex items-center space-x-2.5 px-4 py-2.5 text-sm text-red-300 hover:bg-red-950/50 hover:text-red-200 transition-colors">
                                <i data-lucide="log-out" class="w-4 h-4 text-red-400"></i>
                                <span>Sign Out</span>
                            </a>
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
                                @if(Route::currentRouteName() === 'admin.usm')
                                    System Security / USM
                                @elseif(Route::currentRouteName() === 'admin.prod-master')
                                    Master List / Product Master
                                @elseif(Route::currentRouteName() === 'admin.purchase-note')
                                    Purchasing / Purchasing Note
                                @elseif(Route::currentRouteName() === 'admin.purchase-order')
                                    Purchasing / Purchase Order
                                @elseif(Route::currentRouteName() === 'admin.purchase-return')
                                    Purchasing / Purchase Return
                                @elseif(Route::currentRouteName() === 'admin.sales-note')
                                    Sales / Sales Note
                                @elseif(Route::currentRouteName() === 'admin.profile')
                                    Profile / Admin Profile
                                @elseif(Route::currentRouteName() === 'admin.payments')
                                    Accounting / Payments
                                @elseif(Route::currentRouteName() === 'admin.payable-cheque-voucher')
                                    Accounting / Payable Cheque Voucher
                                @elseif(Route::currentRouteName() === 'admin.expense-cheque-voucher')
                                    Accounting / Expense Cheque Voucher
                                @elseif(Route::currentRouteName() === 'admin.sales-junk')
                                    Inventory / Junk Items
                                @elseif(Route::currentRouteName() === 'warehouse.profile')
                                    Profile / Warehouse Profile
                                @elseif(Route::currentRouteName() === 'warehouse.inv-adjust')
                                    Inventory / Inventory Adjustment
                                @elseif(Route::currentRouteName() === 'warehouse.sales-junk')
                                    Inventory / Junk Items
                                @elseif(Route::currentRouteName() === 'warehouse.item-convert')
                                    Inventory / Convert Items
                                @else
                                    Dashboard
                                @endif
                            </span>
                        </nav>
                        <h1 id="workspace-title" class="text-2xl md:text-3xl font-extrabold text-gray-900 tracking-tight mt-1">
                            @if(Route::currentRouteName() === 'admin.usm')
                                User Management System
                            @elseif(Route::currentRouteName() === 'admin.prod-master')
                                Product Master List
                            @elseif(Route::currentRouteName() === 'admin.purchase-note')
                                Purchasing Note
                            @elseif(Route::currentRouteName() === 'admin.purchase-order')
                                Purchase Order
                            @elseif(Route::currentRouteName() === 'admin.purchase-return')
                                Purchase Return
                            @elseif(Route::currentRouteName() === 'admin.sales-note')
                                Sales Note
                            @elseif(Route::currentRouteName() === 'admin.profile')
                                Administrator Profile
                            @elseif(Route::currentRouteName() === 'admin.payments')
                                Payments Management
                            @elseif(Route::currentRouteName() === 'admin.payable-cheque-voucher')
                                Payable Cheque Voucher
                            @elseif(Route::currentRouteName() === 'admin.expense-cheque-voucher')
                                Expense Cheque Voucher
                            @elseif(Route::currentRouteName() === 'admin.archived')
                                Archived
                            @elseif(Route::currentRouteName() === 'admin.sales-report')
                                Sales Reports
                            @elseif(Route::currentRouteName() === 'admin.inventory-reports')
                                Inventory Reports
                            @elseif(Route::currentRouteName() === 'admin.accounts-receivable')
                                Accounts Receivable
                            @elseif(Route::currentRouteName() === 'admin.datasync')
                                Data Sync
                            @elseif(Route::currentRouteName() === 'admin.audit-trail')
                                Audit Trail
                            @elseif(Route::currentRouteName() === 'admin.sales-junk')
                                Junk Items
                            @elseif(Route::currentRouteName() === 'warehouse.profile')
                                Warehouse Profile
                            @elseif(Route::currentRouteName() === 'warehouse.inv-adjust')
                                Inventory Adjustment
                            @elseif(Route::currentRouteName() === 'warehouse.sales-junk')
                                Junk Items
                            @elseif(Route::currentRouteName() === 'warehouse.item-convert')
                                Convert Items
                            @else
                                Dashboard Overview
                            @endif
                        </h1>
                    </div>
                </div>

                <!-- PAGE: DASHBOARD (Active by Default) -->
                <div id="page-dashboard" class="page-view space-y-6 {{ Route::currentRouteName() === 'admin.dashboard' ? '' : 'hidden' }}">
                    @yield('dashboard_content')
                </div>

                <!-- PAGE: WAREHOUSE DASHBOARD -->
                <div id="page-warehouse-dashboard" class="page-view space-y-6 {{ Route::currentRouteName() === 'warehouse.dashboard' ? '' : 'hidden' }}">
                    @yield('warehouse_dashboard_content')
                </div>

                <!-- PAGE: SYSTEM SECURITY -> USM -->
                <div id="page-usm" class="page-view space-y-6 {{ Route::currentRouteName() === 'admin.usm' ? '' : 'hidden' }}">
                    @yield('usm_content')
                </div>
                
                <!-- PAGE: SYSTEM SECURITY -> ARCHIVED -->
                <div id="page-archived" class="page-view space-y-6 {{ Route::currentRouteName() === 'admin.archived' ? '' : 'hidden' }}">
                    @yield('archived_content')
                </div>
                
                <!-- PAGE: SYSTEM SECURITY -> DATA SYNC -->
                <div id="page-datasync" class="page-view space-y-6 {{ Route::currentRouteName() === 'admin.datasync' ? '' : 'hidden' }}">
                    @yield('datasync_content')
                </div>

                <!-- PAGE: MASTER LIST -> PRODUCT MASTER -->
                <div id="page-prod-master-container" class="page-view space-y-6 {{ Route::currentRouteName() === 'admin.prod-master' ? '' : 'hidden' }}">
                    @yield('prod_master_content')
                </div>

                <!-- PAGE: MASTER LIST -> SUPPLIER MASTER -->
                <div id="page-supplier-master-container" class="page-view space-y-6 {{ Route::currentRouteName() === 'admin.supplier-master' ? '' : 'hidden' }}">
                    @yield('supplier_master_content')
                </div>

                <!-- PAGE: MASTER LIST -> CUSTOMER MASTER -->
                <div id="page-customer-master-container" class="page-view space-view space-y-6 {{ Route::currentRouteName() === 'admin.customer-master' ? '' : 'hidden' }}">
                    @yield('customer_master_content')
                </div>

                <!-- PAGE: PURCHASING -> PURCHASING NOTE -->
                <div id="page-purchase-note-container" class="page-view space-y-6 {{ Route::currentRouteName() === 'admin.purchase-note' ? '' : 'hidden' }}">
                    @yield('purchase_note_content')
                </div>

                <!-- PAGE: PURCHASING -> PURCHASE ORDER -->
                <div id="page-purchase-order-container" class="page-view space-y-6 {{ Route::currentRouteName() === 'admin.purchase-order' ? '' : 'hidden' }}">
                    @yield('purchase_order_content')
                </div>

                <!-- PAGE: PURCHASING -> PURCHASE RETURN -->
                <div id="page-purchase-return-container" class="page-view space-y-6 {{ Route::currentRouteName() === 'admin.purchase-return' ? '' : 'hidden' }}">
                    @yield('purchase_return_content')
                </div>

                <!-- PAGE: SALES -> SALES NOTE -->
                <div id="page-sales-note-container" class="page-view space-y-6 {{ in_array(Route::currentRouteName(), ['admin.sales-note', 'admin.sales-note.online-prices', 'admin.sales-note.report-redirect']) ? '' : 'hidden' }}">
                    @yield('sales_note_content')
                </div>

                <!-- PAGE: SALES -> SALES ORDER -->
                <div id="page-sales-order-container" class="page-view space-y-6 {{ Route::currentRouteName() === 'admin.sales-order' ? '' : 'hidden' }}">
                    @yield('sales_order_content')
                </div>

                <!-- PAGE: SALES -> CONSIGNMENT INVOICE -->
                <div id="page-cons-invoice" class="page-view space-y-6 {{ Route::currentRouteName() === 'admin.consignment-invoice' ? '' : 'hidden' }}">
                    @yield('cons_invoice_content')
                </div>

                <!-- PAGE: SALES -> WAYBILL -->
                <div id="page-waybill" class="page-view space-y-6 {{ Route::currentRouteName() === 'admin.waybill' ? '' : 'hidden' }}">
                    @yield('waybill_content')
                </div>

                <!-- PAGE: MASTER LIST -> FORWARDER MASTER -->
                <div id="page-forwarder-master-container" class="page-view space-y-6 {{ Route::currentRouteName() === 'admin.forwarder-master' ? '' : 'hidden' }}">
                    @yield('forwarder_master_content')
                </div>

                <!-- PAGE: INVENTORY -> INVENTORY ADJUSTMENT -->
                <div id="page-inv-adjust-container" class="page-view space-y-6 {{ Route::currentRouteName() === 'warehouse.inv-adjust' ? '' : 'hidden' }}">
                    @yield('inventory_adjustment_content')
                </div>

                <!-- PAGE: INVENTORY -> JUNK ITEMS -->
                <div id="page-sales-junk-container" class="page-view space-y-6 {{ Route::currentRouteName() === 'warehouse.sales-junk' ? '' : 'hidden' }}">
                    @yield('sales_junk_content')
                </div>

                <!-- PAGE: INVENTORY -> ITEM CONVERT -->
                <div id="page-item-convert-container" class="page-view space-y-6 {{ Route::currentRouteName() === 'warehouse.item-convert' ? '' : 'hidden' }}">
                    @yield('item_convert_content')
                </div>

                <!-- PAGE: SALES -> SALES RETURN -->
                <div id="page-sales-return-container" class="page-view space-y-6 {{ Route::currentRouteName() === 'admin.sales-return' ? '' : 'hidden' }}">
                    @yield('sales_return_content')
                </div>

                <!-- PAGE: ACCOUNTING -> PAYMENTS -->
                <div id="page-payments" class="page-view space-y-6 {{ Route::currentRouteName() === 'admin.payments' ? '' : 'hidden' }}">
                    @yield('payments_content')
                </div>

                <!-- PAGE: WAREHOUSE PROFILE -->
                <div id="page-warehouse-profile" class="page-view space-y-6 {{ Route::currentRouteName() === 'warehouse.profile' ? '' : 'hidden' }}">
                    @yield('profile_content')
                </div>

                <!-- PAGE: ACCOUNTING -> PAYABLE CHEQUE VOUCHER -->
                <div id="page-payable-cheque-voucher-container" class="page-view space-y-6 {{ Route::currentRouteName() === 'admin.payable-cheque-voucher' ? '' : 'hidden' }}">
                    @yield('payable_cheque_voucher_content')
                </div>

                <!-- PAGE: ACCOUNTING -> EXPENSE CHEQUE VOUCHER -->
                <div id="page-exp-cheque" class="page-view space-y-6 {{ Route::currentRouteName() === 'admin.expense-cheque-voucher' ? '' : 'hidden' }}">
                    @yield('expense_cheque_voucher_content')
                </div>
                <!-- PAGE: REPORTS -> INVENTORY REPORTS -->
                <div id="page-rep-inv" class="page-view space-y-6 {{ Route::currentRouteName() === 'admin.inventory-reports' ? '' : 'hidden' }}">
                    @yield('inventory_reports_content')
                </div>
                <!-- PAGE: REPORTS -> SALES REPORTS -->
                <div id="page-rep-sales" class="page-view space-y-6 {{ Route::currentRouteName() === 'admin.sales-report' ? '' : 'hidden' }}">
                    @yield('sales_report_content')
                </div>
                <!-- PAGE: REPORTS -> ACCOUNTS RECEIVABLE -->
                <div id="page-rep-ar" class="page-view space-y-6 {{ Route::currentRouteName() === 'admin.accounts-receivable' ? '' : 'hidden' }}">
                    @yield('accounts_receivable_content')
                </div>
                <!-- PAGE: INVENTORY -> INVENTORY LIST -->
                <div id="page-inv-list" class="page-view hidden space-y-6">
                    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-8 min-h-[400px] flex flex-col justify-center items-center text-center">
                        <div class="p-4 bg-blue-50 text-blue-900 rounded-full mb-4">
                            <i data-lucide="clipboard-list" class="w-8 h-8 text-blue-700"></i>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900">Inventory Database List</h3>
                        <p class="text-sm text-gray-500 max-w-md mt-1">Manage physical holdings, warehouses occupancy rates, stock audits, and multi-zone tracking inside this container.</p>
                    </div>
                </div>

                <!-- PAGE: SYSTEM SECURITY -> AUDIT TRAIL -->
                <div id="page-audit" class="page-view space-y-6 {{ Route::currentRouteName() === 'admin.audit-trail' ? '' : 'hidden' }}">
                    @yield('audit_trail_content')
                </div>

                <!-- PAGE: USER SETTINGS -->
                <div id="page-settings" class="page-view hidden space-y-6">
                    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-8 min-h-[400px] flex flex-col justify-center items-center text-center">
                        <div class="p-4 bg-gray-100 text-gray-700 rounded-full mb-4">
                            <i data-lucide="settings" class="w-8 h-8"></i>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900">System Preferences</h3>
                        <p class="text-sm text-gray-500 max-w-md mt-1">Adjust administrative permissions, security triggers, notifications rules, and look-and-feel preferences here.</p>
                    </div>
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

            @yield('extra_content')
        </main>
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
            window.location.href = '{{ url("/logout") }}';
        }
    </script>
    @vite(['resources/js/admin_sidebar_navbar.js'])
    @stack('scripts')
    <script>
        // Fallback: enforce desktop default folded state on full page load.
        // This works even when an old built JS bundle is still being served.
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
