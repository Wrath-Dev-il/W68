<?php $__env->startPush('styles'); ?>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');
        #page-dashboard {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
    </style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('dashboard_content'); ?>
    <!-- ONE-TIME WELCOME MODAL -->
    <?php if(!session()->has('welcome_shown')): ?>
    <div id="welcome-modal" class="fixed inset-0 z-[100] flex items-center justify-center" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-md transition-opacity"></div>
        <div class="relative bg-white rounded-3xl shadow-2xl transform transition-all sm:max-w-lg sm:w-full modal-animate-in overflow-hidden border border-slate-100">
            <!-- Decorative Header Background -->
            <div class="absolute top-0 left-0 right-0 h-32 bg-maroon-gradient"></div>
            
            <div class="relative pt-12 pb-8 px-8 text-center">
                <!-- Profile Picture / Icon -->
                <div class="relative inline-block mb-6">
                    <div class="w-32 h-32 rounded-full border-4 border-white shadow-xl overflow-hidden bg-slate-50 flex items-center justify-center mx-auto">
                        <?php if(!empty($welcome_profile_picture_url)): ?>
                            <img src="<?php echo e($welcome_profile_picture_url); ?>" alt="<?php echo e($welcome_full_name); ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <?php if($welcome_gender === 'Male'): ?>
                                <svg class="w-20 h-20 text-slate-300" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                </svg>
                            <?php elseif($welcome_gender === 'Female'): ?>
                                <svg class="w-20 h-20 text-slate-300" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                </svg>
                            <?php else: ?>
                                <svg class="w-20 h-20 text-slate-300" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                                </svg>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <!-- Status Indicator -->
                    <div class="absolute bottom-1 right-2 w-6 h-6 bg-emerald-500 border-4 border-white rounded-full"></div>
                </div>

                <!-- Welcome Text -->
                <div class="space-y-2">
                    <h2 class="text-xs font-bold text-maroon uppercase tracking-[0.2em]">Welcome Back, <?php echo e($welcome_account_type_label); ?></h2>
                    <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight"><?php echo e($welcome_full_name); ?></h1>
                    <p class="text-sm text-slate-500 max-w-xs mx-auto pt-2">You have successfully logged into the Hatdog Enterprise Management System.</p>
                </div>

                <!-- Action Button -->
                <div class="mt-10">
                    <button onclick="closeWelcomeModal()" class="w-full py-4 bg-maroon hover:bg-maroon-800 text-white font-bold rounded-2xl shadow-lg shadow-maroon/20 transition-all transform hover:scale-[1.02] active:scale-[0.98] flex items-center justify-center gap-3">
                        <span>Continue to Dashboard</span>
                        <svg class="w-5 h-5 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php session(['welcome_shown' => true]); ?>
    <?php endif; ?>

    <!-- 8 DASHBOARD CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        
        <!-- Card 1: TOTAL PRODUCT -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between transition-all duration-200 hover:shadow-md hover:border-slate-200">
            <div class="space-y-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Product</span>
                <h3 class="text-3xl font-extrabold text-slate-800 tracking-tight"><?php echo e(number_format($total_products ?? 0)); ?></h3>
                <div class="flex items-center gap-1.5 mt-2">
                    <span class="text-emerald-500 font-semibold text-sm flex items-center gap-0.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                        +12.4%
                    </span>
                    <span class="text-xs text-slate-400">vs last month</span>
                </div>
            </div>
            <div class="w-16 h-16 rounded-2xl flex items-center justify-center bg-maroon shadow-inner flex-shrink-0 ml-4">
                <!-- Gold Box Icon -->
                <svg class="w-8 h-8 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
            </div>
        </div>

        <!-- Card 2: TOTAL LOW STOCK PRODUCT -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between transition-all duration-200 hover:shadow-md hover:border-slate-200">
            <div class="space-y-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Low Stock Product</span>
                <h3 class="text-3xl font-extrabold text-slate-800 tracking-tight"><?php echo e(number_format($low_stock_count ?? 0)); ?></h3>
                <div class="flex items-center gap-1.5 mt-2">
                    <span class="text-red-500 font-semibold text-sm flex items-center gap-0.5">
                        <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                        -4.2%
                    </span>
                    <span class="text-xs text-slate-400">vs last month</span>
                </div>
            </div>
            <div class="w-16 h-16 rounded-2xl flex items-center justify-center bg-maroon shadow-inner flex-shrink-0 ml-4">
                <!-- Gold Warning Alert Icon -->
                <svg class="w-8 h-8 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
        </div>

        <!-- Card 3: TOTAL SUPPLIER -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between transition-all duration-200 hover:shadow-md hover:border-slate-200">
            <div class="space-y-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Supplier</span>
                <h3 class="text-3xl font-extrabold text-slate-800 tracking-tight"><?php echo e(number_format($total_suppliers ?? 0)); ?></h3>
                <div class="flex items-center gap-1.5 mt-2">
                    <span class="text-emerald-500 font-semibold text-sm flex items-center gap-0.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                        +5.3%
                    </span>
                    <span class="text-xs text-slate-400">vs last month</span>
                </div>
            </div>
            <div class="w-16 h-16 rounded-2xl flex items-center justify-center bg-maroon shadow-inner flex-shrink-0 ml-4">
                <!-- Gold Truck/Supplier Icon -->
                <svg class="w-8 h-8 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                </svg>
            </div>
        </div>

        <!-- Card 4: TOTAL CUSTOMER -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between transition-all duration-200 hover:shadow-md hover:border-slate-200">
            <div class="space-y-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Customer</span>
                <h3 class="text-3xl font-extrabold text-slate-800 tracking-tight"><?php echo e(number_format($total_customers ?? 0)); ?></h3>
                <div class="flex items-center gap-1.5 mt-2">
                    <span class="text-emerald-500 font-semibold text-sm flex items-center gap-0.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                        +18.7%
                    </span>
                    <span class="text-xs text-slate-400">vs last month</span>
                </div>
            </div>
            <div class="w-16 h-16 rounded-2xl flex items-center justify-center bg-maroon shadow-inner flex-shrink-0 ml-4">
                <!-- Gold Customer/Users Icon -->
                <svg class="w-8 h-8 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </div>
        </div>

        <!-- Card 5: TOTAL SALES (current) -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between transition-all duration-200 hover:shadow-md hover:border-slate-200">
            <div class="space-y-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Sales <span class="text-[10px] text-slate-400 lowercase">(current)</span></span>
                <h3 class="text-3xl font-extrabold text-slate-800 tracking-tight"><?php echo e(number_format($total_sales ?? 0)); ?></h3>
                <div class="flex items-center gap-1.5 mt-2">
                    <span class="text-emerald-500 font-semibold text-sm flex items-center gap-0.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                        +8.1%
                    </span>
                    <span class="text-xs text-slate-400">vs last week</span>
                </div>
            </div>
            <div class="w-16 h-16 rounded-2xl flex items-center justify-center bg-maroon shadow-inner flex-shrink-0 ml-4">
                <!-- Gold Document/Order Icon -->
                <svg class="w-8 h-8 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
        </div>

        <!-- Card 6: TOTAL ONLINE INVOICE (current) -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between transition-all duration-200 hover:shadow-md hover:border-slate-200">
            <div class="space-y-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Online Invoice <span class="text-[10px] text-slate-400 lowercase">(current)</span></span>
                <h3 class="text-3xl font-extrabold text-slate-800 tracking-tight"><?php echo e(number_format($total_online_invoice ?? 0)); ?></h3>
                <div class="flex items-center gap-1.5 mt-2">
                    <span class="text-emerald-500 font-semibold text-sm flex items-center gap-0.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                        +22.3%
                    </span>
                    <span class="text-xs text-slate-400">vs last month</span>
                </div>
            </div>
            <div class="w-16 h-16 rounded-2xl flex items-center justify-center bg-maroon shadow-inner flex-shrink-0 ml-4">
                <!-- Gold Cash Invoice Icon (Credit Card/Money) -->
                <svg class="w-8 h-8 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </div>
        </div>

        <!-- Card 7: TOTAL SALES RETURN (current) -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between transition-all duration-200 hover:shadow-md hover:border-slate-200">
            <div class="space-y-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Sales Return <span class="text-[10px] text-slate-400 lowercase">(current)</span></span>
                <h3 class="text-3xl font-extrabold text-slate-800 tracking-tight"><?php echo e(number_format($total_sales_return ?? 0)); ?></h3>
                <div class="flex items-center gap-1.5 mt-2">
                    <span class="text-red-500 font-semibold text-sm flex items-center gap-0.5">
                        <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                        -50.0%
                    </span>
                    <span class="text-xs text-slate-400">vs last week</span>
                </div>
            </div>
            <div class="w-16 h-16 rounded-2xl flex items-center justify-center bg-maroon shadow-inner flex-shrink-0 ml-4">
                <!-- Gold Return/Refresh Icon -->
                <svg class="w-8 h-8 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z" />
                </svg>
            </div>
        </div>

        <!-- Card 8: TOTAL WAYBILL (to process) -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between transition-all duration-200 hover:shadow-md hover:border-slate-200">
            <div class="space-y-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Waybill <span class="text-[10px] text-amber-600 font-medium bg-amber-50 px-1.5 py-0.5 rounded ml-1 lowercase">(to process)</span></span>
                <h3 class="text-3xl font-extrabold text-slate-800 tracking-tight"><?php echo e(number_format($total_waybill ?? 0)); ?></h3>
                <div class="flex items-center gap-1.5 mt-2">
                    <span class="text-emerald-500 font-semibold text-sm flex items-center gap-0.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                        +14.2%
                    </span>
                    <span class="text-xs text-slate-400">vs yesterday</span>
                </div>
            </div>
            <div class="w-16 h-16 rounded-2xl flex items-center justify-center bg-maroon shadow-inner flex-shrink-0 ml-4">
                <!-- Gold Waybill/Clipboard/Map Icon -->
                <svg class="w-8 h-8 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                </svg>
            </div>
        </div>

    </div>

    <!-- LOWER SECTION: TABLES (Left) & DONUT CHARTS (Right) -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-8 mt-8">
        
        <!-- TABLES COMPONENT (Takes 2/3 column space on large screens) -->
        <div class="xl:col-span-2 bg-white rounded-2xl border border-slate-100 shadow-sm flex flex-col min-h-[720px] xl:min-h-[720px] overflow-hidden">
            
            <!-- Table Control Panel (Tabs + Search Bar) -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 p-4 border-b border-slate-100 bg-slate-50/50 flex-shrink-0">
                    <!-- Navigation Tabs with Dynamic Counter Badges -->
                    <div class="flex bg-slate-200/60 p-1 rounded-xl">
                        <button id="tab1-btn" onclick="switchTab('tab1')" class="inline-flex items-center px-4 py-2 text-xs md:text-sm font-semibold rounded-lg transition-all duration-150 bg-white text-slate-900 shadow-sm gap-2">
                            <span>New Added Items</span>
                            <span id="tab1-count" class="bg-maroon text-white text-[10px] font-bold px-2 py-0.5 rounded-full transition-all duration-150"><?php echo e($newly_added_count ?? 0); ?></span>
                        </button>
                        <button id="tab2-btn" onclick="switchTab('tab2')" class="inline-flex items-center px-4 py-2 text-xs md:text-sm font-semibold rounded-lg transition-all duration-150 text-slate-600 hover:text-slate-900 gap-2">
                            <span>Low Stock Items</span>
                            <span id="tab2-count" class="bg-slate-300 text-slate-700 text-[10px] font-bold px-2 py-0.5 rounded-full transition-all duration-150"><?php echo e($low_stock_count ?? 0); ?></span>
                        </button>
                    </div>
                    
                    <!-- Search Bar (Beside Tables) -->
                    <div class="relative flex-1 max-w-xs">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input type="text" id="table-search" oninput="handleSearch()" placeholder="Search item code or desc..." class="block w-full pl-9 pr-3 py-2 border border-slate-200 rounded-xl bg-white placeholder-slate-400 text-sm focus:outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon transition-colors duration-150">
                    </div>
                </div>

            <!-- TABLE CONTENT (scrollable body only) -->
            <div class="overflow-y-auto flex-1 min-h-0">
                
                <!-- Tab 1 Table: New Added Items -->
                <table id="table-tab1" class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 text-slate-400 text-xs font-bold uppercase tracking-wider sticky top-0 bg-white z-10">
                            <th class="py-2.5 px-6">Item Code</th>
                            <th class="py-2.5 px-6">Category</th>
                            <th class="py-2.5 px-6">Available QTY</th>
                            <th class="py-2.5 px-6">Description</th>
                            <th class="py-2.5 px-6 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody id="tab1-tbody" class="divide-y divide-slate-100 text-sm">
                            <!-- Populated Dynamically via JS -->
                        </tbody>
                    </table>

                    <!-- Tab 2 Table: Low Stock Items -->
                    <table id="table-tab2" class="w-full text-left border-collapse hidden">
                        <thead>
                            <tr class="border-b border-slate-100 text-slate-400 text-xs font-bold uppercase tracking-wider sticky top-0 bg-white z-10">
                            <th class="py-2.5 px-6">Item Code</th>
                            <th class="py-2.5 px-6">Category</th>
                            <th class="py-2.5 px-6">Available QTY</th>
                            <th class="py-2.5 px-6">Description</th>
                            <th class="py-2.5 px-6 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody id="tab2-tbody" class="divide-y divide-slate-100 text-sm">
                            <!-- Populated Dynamically via JS -->
                        </tbody>
                    </table>

                    <!-- Empty State Container -->
                    <div id="no-results" class="hidden py-12 px-6 text-center">
                        <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <h3 class="mt-4 text-sm font-semibold text-slate-900">No matching items</h3>
                        <p class="mt-1 text-xs text-slate-500">Try adjusting your search query.</p>
                    </div>
            </div>
            
            <!-- TABLE FOOTER -->
            <div class="p-4 border-t border-slate-100 bg-slate-50/30 flex items-center justify-between flex-shrink-0 gap-4 flex-wrap">
                <span id="table-range-info" class="text-xs text-slate-500 font-medium">Showing 0-0 of 0</span>
                <div class="flex items-center gap-2">
                    <button id="table-prev-page" onclick="changeTablePage(-1)" class="px-3 py-1.5 text-xs font-bold rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-100 transition-all">Prev</button>
                    <span id="table-page-indicator" class="text-xs font-semibold text-slate-600 min-w-[90px] text-center">Page 1 of 1</span>
                    <button id="table-next-page" onclick="changeTablePage(1)" class="px-3 py-1.5 text-xs font-bold rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-100 transition-all">Next</button>
                </div>
                <button onclick="alert('Navigating to full Product List module...')" class="inline-flex items-center gap-1.5 text-xs font-semibold text-maroon hover:text-maroon-hover transition-colors">
                    GO TO Product List
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>

        </div>

        <!-- DONUT GRAPHS (Takes 1/3 column space on large screens) -->
        <div class="space-y-8">
            
            <!-- Graph 1: Overall QTY Distribution -->
            <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Overall QTY Distribution</h3>
                        <span class="text-[10px] font-medium text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">Products</span>
                    </div>
                    <p class="text-xs text-slate-500 mb-6">Percentage distribution quantity of overall products in inventory.</p>
                    
                    <div class="relative h-60 w-full flex items-center justify-center">
                        <canvas id="productQtyDonut"></canvas>
                    </div>
                </div>
            </div>

            <!-- Graph 2: Financial Document Allocation -->
            <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Document Distribution</h3>
                        <span class="text-[10px] font-medium text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">Financials</span>
                    </div>
                    <p class="text-xs text-slate-500 mb-6">Total ratio representation of Cash Invoices, Sales Orders, and Consignment Invoices.</p>
                    
                    <div class="relative h-60 w-full flex items-center justify-center">
                        <canvas id="financialDocDonut"></canvas>
                    </div>
                </div>
            </div>

        </div>

    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script>
        let tab1Data = [];
        let tab2Data = [];
        const dashboardTableDataUrl = <?php echo json_encode($dashboard_table_data_url ?? null, 15, 512) ?>;
        const PAGE_SIZE = 50;

        let activeTab = 'tab1';
        const currentPageByTab = { tab1: 1, tab2: 1 };
        const filteredDataByTab = {
            tab1: [...tab1Data],
            tab2: [...tab2Data]
        };

        // Initialize lists, search counters, and charts
        window.addEventListener('DOMContentLoaded', () => {
            showTableLoadingState();
            updateCounters();
            initDonutCharts();
            loadDashboardTableData();
        });

        async function loadDashboardTableData() {
            if (!dashboardTableDataUrl) {
                showTableErrorState('Dashboard table data route is unavailable.');
                return;
            }

            try {
                const response = await fetch(dashboardTableDataUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const payload = await response.json();
                if (!response.ok) {
                    throw new Error(payload.message || 'Unable to load dashboard table data.');
                }

                tab1Data = Array.isArray(payload.tab1_data) ? payload.tab1_data : [];
                tab2Data = Array.isArray(payload.tab2_data) ? payload.tab2_data : [];
                filteredDataByTab.tab1 = [...tab1Data];
                filteredDataByTab.tab2 = [...tab2Data];
                currentPageByTab.tab1 = 1;
                currentPageByTab.tab2 = 1;
                updateCounters();
                renderTable('tab1', filteredDataByTab.tab1);
                renderTable('tab2', filteredDataByTab.tab2);
            } catch (error) {
                showTableErrorState(error.message || 'Unable to load dashboard table data.');
            }
        }

        function showTableLoadingState() {
            const tbody1 = document.getElementById('tab1-tbody');
            const tbody2 = document.getElementById('tab2-tbody');
            const loadingRow = `
                <tr>
                    <td colspan="5" class="py-10 px-6 text-center text-sm font-semibold text-slate-400">
                        Loading dashboard table data...
                    </td>
                </tr>
            `;

            if (tbody1) tbody1.innerHTML = loadingRow;
            if (tbody2) tbody2.innerHTML = loadingRow;
            const noResults = document.getElementById('no-results');
            if (noResults) noResults.classList.add('hidden');
        }

        function showTableErrorState(message) {
            const tbody1 = document.getElementById('tab1-tbody');
            const tbody2 = document.getElementById('tab2-tbody');
            const errorRow = `
                <tr>
                    <td colspan="5" class="py-10 px-6 text-center text-sm font-semibold text-red-500">
                        ${message}
                    </td>
                </tr>
            `;

            if (tbody1) tbody1.innerHTML = errorRow;
            if (tbody2) tbody2.innerHTML = errorRow;
        }

        // Update the count pills next to the Tab titles
        function updateCounters() {
            const count1 = document.getElementById('tab1-count');
            const count2 = document.getElementById('tab2-count');
            if (count1) count1.textContent = tab1Data.length;
            if (count2) count2.textContent = tab2Data.length;
        }

        // Tab Switching logic
        function switchTab(tabId) {
            activeTab = tabId;
            const tab1Btn = document.getElementById('tab1-btn');
            const tab2Btn = document.getElementById('tab2-btn');
            const tab1Count = document.getElementById('tab1-count');
            const tab2Count = document.getElementById('tab2-count');
            const table1 = document.getElementById('table-tab1');
            const table2 = document.getElementById('table-tab2');

            // Reset Search input text when changing tabs
            const searchInput = document.getElementById('table-search');
            if (searchInput) searchInput.value = '';
            currentPageByTab[tabId] = 1;
            filteredDataByTab[tabId] = tabId === 'tab1' ? [...tab1Data] : [...tab2Data];

            if (tabId === 'tab1') {
                if (tab1Btn) tab1Btn.className = "inline-flex items-center px-4 py-2 text-xs md:text-sm font-semibold rounded-lg transition-all duration-150 bg-white text-slate-900 shadow-sm gap-2";
                if (tab2Btn) tab2Btn.className = "inline-flex items-center px-4 py-2 text-xs md:text-sm font-semibold rounded-lg transition-all duration-150 text-slate-600 hover:text-slate-900 gap-2";
                
                if (tab1Count) tab1Count.className = "bg-maroon text-white text-[10px] font-bold px-2 py-0.5 rounded-full transition-all duration-150";
                if (tab2Count) tab2Count.className = "bg-slate-300 text-slate-700 text-[10px] font-bold px-2 py-0.5 rounded-full transition-all duration-150";

                if (table1) table1.classList.remove('hidden');
                if (table2) table2.classList.add('hidden');
                renderTable('tab1', filteredDataByTab.tab1);
            } else {
                if (tab1Btn) tab1Btn.className = "inline-flex items-center px-4 py-2 text-xs md:text-sm font-semibold rounded-lg transition-all duration-150 text-slate-600 hover:text-slate-900 gap-2";
                if (tab2Btn) tab2Btn.className = "inline-flex items-center px-4 py-2 text-xs md:text-sm font-semibold rounded-lg transition-all duration-150 bg-white text-slate-900 shadow-sm gap-2";
                
                if (tab1Count) tab1Count.className = "bg-slate-300 text-slate-700 text-[10px] font-bold px-2 py-0.5 rounded-full transition-all duration-150";
                if (tab2Count) tab2Count.className = "bg-maroon text-white text-[10px] font-bold px-2 py-0.5 rounded-full transition-all duration-150";

                if (table1) table1.classList.add('hidden');
                if (table2) table2.classList.remove('hidden');
                renderTable('tab2', filteredDataByTab.tab2);
            }
        }

        // Render Table Utility
        function renderTable(tabId, data) {
            const tbody = document.getElementById(`${tabId}-tbody`);
            if (!tbody) return;
            tbody.innerHTML = '';
            
            const noResults = document.getElementById('no-results');
            const totalItems = data.length;
            const totalPages = Math.max(1, Math.ceil(totalItems / PAGE_SIZE));
            currentPageByTab[tabId] = Math.min(currentPageByTab[tabId], totalPages);
            const page = currentPageByTab[tabId];

            if (totalItems === 0) {
                if (noResults) noResults.classList.remove('hidden');
                updateTablePaginationUI(0, 0, 0, 1, tabId);
                return;
            } else {
                if (noResults) noResults.classList.add('hidden');
            }

            const startIndex = (page - 1) * PAGE_SIZE;
            const endIndex = Math.min(startIndex + PAGE_SIZE, totalItems);
            const pagedData = data.slice(startIndex, endIndex);

            pagedData.forEach(item => {
                const tr = document.createElement('tr');
                tr.className = "h-10 hover:bg-slate-50/60 transition-colors";
                
                let qtyBadgeColor = "bg-slate-100 text-slate-800";
                if (tabId === 'tab2' || item.qty <= 10) {
                    qtyBadgeColor = "bg-rose-50 text-red-600 border border-rose-100";
                } else if (item.qty > 100) {
                    qtyBadgeColor = "bg-emerald-50 text-emerald-700 border border-emerald-100";
                }

                tr.innerHTML = `
                    <td class="py-2 px-6 font-mono font-bold text-slate-900 text-xs leading-tight">${item.code}</td>
                    <td class="py-2 px-6 text-slate-500 leading-tight">${item.category}</td>
                    <td class="py-2 px-6">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ${qtyBadgeColor}">
                            ${item.qty} units
                        </span>
                    </td>
                    <td class="py-2 px-6 text-slate-600 font-medium max-w-[200px] truncate leading-tight" title="${item.desc}">${item.desc}</td>
                    <td class="py-2 px-6 text-right">
                        <button onclick="alert('Viewing profile of ${item.code}')" class="text-maroon hover:text-maroon-hover hover:underline text-xs font-bold transition-all">
                            View Product
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            updateTablePaginationUI(startIndex + 1, endIndex, totalItems, totalPages, tabId);
        }

        function updateTablePaginationUI(start, end, total, totalPages, tabId) {
            const rangeInfo = document.getElementById('table-range-info');
            const pageIndicator = document.getElementById('table-page-indicator');
            const prevBtn = document.getElementById('table-prev-page');
            const nextBtn = document.getElementById('table-next-page');
            const page = currentPageByTab[tabId] || 1;

            if (rangeInfo) rangeInfo.textContent = `Showing ${start}-${end} of ${total}`;
            if (pageIndicator) pageIndicator.textContent = `Page ${page} of ${totalPages}`;
            if (prevBtn) prevBtn.disabled = page <= 1 || total === 0;
            if (nextBtn) nextBtn.disabled = page >= totalPages || total === 0;
        }

        function changeTablePage(direction) {
            const sourceData = filteredDataByTab[activeTab] || [];
            const totalPages = Math.max(1, Math.ceil(sourceData.length / PAGE_SIZE));
            const nextPage = currentPageByTab[activeTab] + direction;
            currentPageByTab[activeTab] = Math.max(1, Math.min(totalPages, nextPage));
            renderTable(activeTab, sourceData);
        }

        // Search mechanism (Filters table items)
        function handleSearch() {
            const searchInput = document.getElementById('table-search');
            if (!searchInput) return;
            const query = searchInput.value.toLowerCase().trim();
            const sourceData = activeTab === 'tab1' ? tab1Data : tab2Data;
            
            const filteredData = sourceData.filter(item => 
                item.code.toLowerCase().includes(query) || 
                item.desc.toLowerCase().includes(query) ||
                item.category.toLowerCase().includes(query)
            );

            filteredDataByTab[activeTab] = filteredData;
            currentPageByTab[activeTab] = 1;
            renderTable(activeTab, filteredData);
        }

        // Chart.js Implementations
        function initDonutCharts() {
            const productEl = document.getElementById('productQtyDonut');
            if (productEl) {
                const productCtx = productEl.getContext('2d');
                new Chart(productCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode($category_distribution_labels ?? [], 15, 512) ?>,
                        datasets: [{
                            data: <?php echo json_encode($category_distribution_values ?? [], 15, 512) ?>,
                            backgroundColor: [
                                '#4A0A15', // Maroon
                                '#7a1626', // Lighter Maroon
                                '#FFC72C', // Gold
                                '#ffd561', // Gold light
                                '#1e293b', // Slate
                                '#cbd5e1'  // Slate light
                            ],
                            borderWidth: 2,
                            borderColor: '#ffffff',
                            hoverOffset: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    boxWidth: 12,
                                    padding: 15,
                                    font: {
                                        family: 'Plus Jakarta Sans',
                                        size: 11,
                                        weight: '600'
                                    }
                                }
                            }
                        },
                        cutout: '70%'
                    }
                });
            }

            const financeEl = document.getElementById('financialDocDonut');
            if (financeEl) {
                const financeCtx = financeEl.getContext('2d');
                new Chart(financeCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode($document_distribution_labels ?? [], 15, 512) ?>,
                        datasets: [{
                            data: <?php echo json_encode($document_distribution_values ?? [], 15, 512) ?>,
                            backgroundColor: [
                                '#4A0A15', // Maroon
                                '#FFC72C', // Gold
                                '#475569'  // Charcoal
                            ],
                            borderWidth: 2,
                            borderColor: '#ffffff',
                            hoverOffset: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    boxWidth: 12,
                                    padding: 15,
                                    font: {
                                        family: 'Plus Jakarta Sans',
                                        size: 11,
                                        weight: '600'
                                    }
                                }
                            }
                        },
                        cutout: '70%'
                    }
                });
            }
        }

        // Welcome Modal Logic
        function closeWelcomeModal() {
            const modal = document.getElementById('welcome-modal');
            if (modal) {
                modal.classList.add('opacity-0', 'pointer-events-none');
                setTimeout(() => {
                    modal.remove();
                }, 300);
            }
        }
    </script>
<?php $__env->stopPush(); ?>



<?php echo $__env->make('partials.user_account.user_sidebar_navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\hatdog\resources\views/Regular_User/dashboard/Dashboard.blade.php ENDPATH**/ ?>