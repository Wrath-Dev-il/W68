<?php $__env->startPush('styles'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/usm.css')); ?>?v=<?php echo e(time()); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('usm_content'); ?>
<script>
    try { window.dbUsers = JSON.parse(atob('<?php echo e(base64_encode(json_encode($dbUsers->toArray()))); ?>')); } catch(e) { window.dbUsers = []; }
    try { window.loggedInAdmin = JSON.parse(atob('<?php echo e(base64_encode(json_encode($user))); ?>')); } catch(e) { window.loggedInAdmin = null; }
    window.usmOnlineUserIds = <?php echo json_encode($onlineUserIds->values()->toArray(), 15, 512) ?>;
    window.csrfToken = '<?php echo e(csrf_token()); ?>';
    window.usmRoutes = {
        create: '<?php echo e(route("admin.usm.create")); ?>',
        resetPassword: '<?php echo e(route("admin.usm.reset-password")); ?>',
        delete: '<?php echo e(route("admin.usm.delete")); ?>'
    };
</script>
<div id="page-usm-root" class="space-y-8 animate-fade-in">

    <!-- SUB-HEADER INTERACTIVE PANEL -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
        <div>
            <h2 class="text-lg font-bold text-slate-800">Security Credentials & Accounts Administration</h2>
            <p class="text-xs text-slate-400">Configure roles, track authentication frequencies, and manage enterprise security access permissions.</p>
        </div>
        
        <!-- HEADER QUICK ACTIONS -->
        <div class="flex items-center space-x-3">
            <!-- Filter Button -->
            <button onclick="toggleModal('filter-modal', true)" class="px-4 py-2.5 bg-white border border-slate-200 hover:border-maroon-200 hover:bg-slate-50 text-slate-700 hover:text-maroon text-xs font-bold rounded-xl shadow-sm flex items-center space-x-2 transition-all">
                <i data-lucide="sliders-horizontal" class="w-4 h-4 text-slate-500"></i>
                <span>Filter Accounts</span>
            </button>
            
            <!-- Add User Button -->
            <button onclick="toggleModal('add-user-modal', true)" class="px-4 py-2.5 bg-maroon hover:bg-maroon-hover text-white text-xs font-bold rounded-xl shadow-lg flex items-center space-x-2 transition-all hover:shadow-xl">
                <i data-lucide="user-plus" class="w-4 h-4 text-gold"></i>
                <span>Add User</span>
            </button>
        </div>
    </div>

    <!-- 3 SYSTEM DASHBOARD STATS CARDS -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        
        <!-- Stat Card 1: TOTAL ADMINS -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between transition-all duration-200 hover:shadow-md hover:border-slate-200 hover-card-trigger">
            <div class="space-y-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Admins</span>
                <h3 id="card-count-admin" class="text-3xl font-extrabold text-slate-800 tracking-tight">0</h3>
                <div class="flex items-center gap-1.5 mt-2 bg-rose-50/50 px-2 py-1 rounded-lg border border-rose-100/50 w-max">
                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse mr-1"></span>
                    <span id="card-sub-admin" class="text-[10px] font-bold text-rose-700 tracking-wide font-mono uppercase">Online: 0 | Offline: 0</span>
                </div>
            </div>
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-maroon-gradient shadow-lg flex-shrink-0 ml-4 border border-goldlining-500/20">
                <i data-lucide="shield-alert" class="w-6 h-6 text-gold"></i>
            </div>
        </div>

        <!-- Stat Card 2: TOTAL EMPLOYEES -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between transition-all duration-200 hover:shadow-md hover:border-slate-200 hover-card-trigger">
            <div class="space-y-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Employees</span>
                <h3 id="card-count-employee" class="text-3xl font-extrabold text-slate-800 tracking-tight">0</h3>
                <div class="flex items-center gap-1.5 mt-2 bg-amber-50/50 px-2 py-1 rounded-lg border border-amber-100/50 w-max">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse mr-1"></span>
                    <span id="card-sub-employee" class="text-[10px] font-bold text-amber-700 tracking-wide font-mono uppercase">Online: 0 | Offline: 0</span>
                </div>
            </div>
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-maroon-gradient shadow-lg flex-shrink-0 ml-4 border border-goldlining-500/20">
                <i data-lucide="users-round" class="w-6 h-6 text-gold"></i>
            </div>
        </div>

        <!-- Stat Card 3: TOTAL CUSTOMERS -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between transition-all duration-200 hover:shadow-md hover:border-slate-200 hover-card-trigger">
            <div class="space-y-1">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Customers</span>
                <h3 id="card-count-customer" class="text-3xl font-extrabold text-slate-800 tracking-tight">0</h3>
                <div class="flex items-center gap-1.5 mt-2 bg-emerald-50/50 px-2 py-1 rounded-lg border border-emerald-100/50 w-max">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse mr-1"></span>
                    <span id="card-sub-customer" class="text-[10px] font-bold text-emerald-700 tracking-wide font-mono uppercase">Online: 0 | Offline: 0</span>
                </div>
            </div>
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-maroon-gradient shadow-lg flex-shrink-0 ml-4 border border-goldlining-500/20">
                <i data-lucide="fingerprint" class="w-6 h-6 text-gold"></i>
            </div>
        </div>

    </div>

    <!-- MAIN INTERACTIVE DATATABLES WORKSPACE -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden flex flex-col justify-between">
        
        <!-- Table Control Tabs Panel -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between border-b border-slate-100 bg-slate-50/50">
            <!-- Navigation Tabs -->
            <div class="flex flex-wrap overflow-hidden">
                <button data-tab="admin" class="usm-tab-btn tab-active px-5 py-3 text-sm font-bold flex items-center space-x-2 transition-all duration-200 border-r border-slate-100">
                    <i data-lucide="shield-check" class="w-4 h-4"></i>
                    <span>Administrators</span>
                    <span id="badge-count-admin" class="bg-maroon text-white text-[10px] font-bold px-2 py-0.5 rounded-full transition-all duration-150">0</span>
                </button>
                
                <button data-tab="employee" class="usm-tab-btn tab-inactive px-5 py-3 text-sm font-semibold flex items-center space-x-2 transition-all duration-200 border-r border-slate-100">
                    <i data-lucide="contact-2" class="w-4 h-4"></i>
                    <span>Employees</span>
                    <span id="badge-count-employee" class="bg-slate-200 text-slate-700 text-[10px] font-bold px-2 py-0.5 rounded-full transition-all duration-150">0</span>
                </button>
                
                <button data-tab="customer" class="usm-tab-btn tab-inactive px-5 py-3 text-sm font-semibold flex items-center space-x-2 transition-all duration-200">
                    <i data-lucide="shopping-bag" class="w-4 h-4"></i>
                    <span>Customers</span>
                    <span id="badge-count-customer" class="bg-slate-200 text-slate-700 text-[10px] font-bold px-2 py-0.5 rounded-full transition-all duration-150">0</span>
                </button>
            </div>
            
            <div class="p-3 pr-5 text-right">
                <span class="text-[10px] text-slate-400 font-semibold tracking-wider uppercase">Active Security Profiles</span>
            </div>
        </div>

        <!-- TABLE WORKSPACES CONTAINER -->
        <div class="overflow-x-auto custom-scrollbar">

            <!-- 1. ADMIN TABLE CONTAINER -->
            <div id="table-container-admin" class="min-w-full">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/20 text-slate-400 text-xs font-bold uppercase tracking-wider">
                            <th class="py-3 px-5">User ID</th>
                            <th class="py-3 px-5">Name</th>
                            <th class="py-3 px-5">Password</th>
                            <th class="py-3 px-5">Last Change Date</th>
                            <th class="py-3 px-5">Status</th>
                            <th class="py-3 px-5 text-center">Action</th>
                        </tr>
                        <!-- Column Specific Search Input Row -->
                        <tr class="border-b border-slate-100 bg-slate-50/10">
                            <th class="p-2 px-5"><input type="text" data-col="userId" placeholder="Search ID..." class="column-search-input"></th>
                            <th class="p-2 px-5"><input type="text" data-col="name" placeholder="Search Name..." class="column-search-input"></th>
                            <th class="p-2 px-5"><input type="text" data-col="password" placeholder="Search Password..." class="column-search-input"></th>
                            <th class="p-2 px-5"><input type="text" data-col="lastChange" placeholder="Search Date..." class="column-search-input"></th>
                            <th class="p-2 px-5"><input type="text" data-col="status" placeholder="Search Status..." class="column-search-input"></th>
                            <th class="p-2 px-5"></th>
                        </tr>
                    </thead>
                    <tbody id="usm-tbody-admin" class="divide-y divide-slate-100 text-sm">
                        <!-- Rendered Reactively in JS -->
                    </tbody>
                </table>
                <div id="no-results-admin" class="hidden py-12 px-6 text-center">
                    <i data-lucide="inbox" class="mx-auto h-12 w-12 text-slate-300"></i>
                    <h3 class="mt-4 text-sm font-semibold text-slate-900">No matching administrators found</h3>
                    <p class="mt-1 text-xs text-slate-500 text-center">Adjust column queries or reset page filters.</p>
                </div>
            </div>

            <!-- 2. EMPLOYEE TABLE CONTAINER -->
            <div id="table-container-employee" class="min-w-full hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/20 text-slate-400 text-xs font-bold uppercase tracking-wider">
                            <th class="py-3 px-5">User ID</th>
                            <th class="py-3 px-5">Name</th>
                            <th class="py-3 px-5">Password</th>
                            <th class="py-3 px-5">Last Change Date</th>
                            <th class="py-3 px-5">Status</th>
                            <th class="py-3 px-5 text-center">Action</th>
                        </tr>
                        <!-- Column Specific Search Input Row -->
                        <tr class="border-b border-slate-100 bg-slate-50/10">
                            <th class="p-2 px-5"><input type="text" data-col="userId" placeholder="Search ID..." class="column-search-input"></th>
                            <th class="p-2 px-5"><input type="text" data-col="name" placeholder="Search Name..." class="column-search-input"></th>
                            <th class="p-2 px-5"><input type="text" data-col="password" placeholder="Search Password..." class="column-search-input"></th>
                            <th class="p-2 px-5"><input type="text" data-col="lastChange" placeholder="Search Date..." class="column-search-input"></th>
                            <th class="p-2 px-5"><input type="text" data-col="status" placeholder="Search Status..." class="column-search-input"></th>
                            <th class="p-2 px-5"></th>
                        </tr>
                    </thead>
                    <tbody id="usm-tbody-employee" class="divide-y divide-slate-100 text-sm">
                        <!-- Rendered Reactively in JS -->
                    </tbody>
                </table>
                <div id="no-results-employee" class="hidden py-12 px-6 text-center">
                    <i data-lucide="inbox" class="mx-auto h-12 w-12 text-slate-300"></i>
                    <h3 class="mt-4 text-sm font-semibold text-slate-900">No matching employees found</h3>
                    <p class="mt-1 text-xs text-slate-500 text-center">Adjust column queries or reset page filters.</p>
                </div>
            </div>

            <!-- 3. CUSTOMER TABLE CONTAINER -->
            <div id="table-container-customer" class="min-w-full hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/20 text-slate-400 text-xs font-bold uppercase tracking-wider">
                            <th class="py-3 px-5">User ID</th>
                            <th class="py-3 px-5">Name</th>
                            <th class="py-3 px-5">Login Frequency</th>
                            <th class="py-3 px-5">Status</th>
                        </tr>
                        <!-- Column Specific Search Input Row -->
                        <tr class="border-b border-slate-100 bg-slate-50/10">
                            <th class="p-2 px-5"><input type="text" data-col="userId" placeholder="Search ID..." class="column-search-input"></th>
                            <th class="p-2 px-5"><input type="text" data-col="name" placeholder="Search Name..." class="column-search-input"></th>
                            <th class="p-2 px-5"><input type="text" data-col="loginFrequency" placeholder="Search Frequency..." class="column-search-input"></th>
                            <th class="p-2 px-5"><input type="text" data-col="status" placeholder="Search Status..." class="column-search-input"></th>
                        </tr>
                    </thead>
                    <tbody id="usm-tbody-customer" class="divide-y divide-slate-100 text-sm">
                        <!-- Rendered Reactively in JS -->
                    </tbody>
                </table>
                <div id="no-results-customer" class="hidden py-12 px-6 text-center">
                    <i data-lucide="inbox" class="mx-auto h-12 w-12 text-slate-300"></i>
                    <h3 class="mt-4 text-sm font-semibold text-slate-900">No matching customers found</h3>
                    <p class="mt-1 text-xs text-slate-500 text-center">Adjust column queries or reset page filters.</p>
                </div>
            </div>

        </div>

        <!-- FOOTER MATCH SUMMARY -->
        <div class="p-5 border-t border-slate-100 bg-slate-50/30 flex items-center justify-between flex-shrink-0">
            <span class="text-xs text-slate-500 font-medium">Real-time reactive security table database</span>
            <span class="text-[10px] font-bold text-maroon hover:underline flex items-center cursor-pointer gap-1">
                <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
                Data cryptographically secured
            </span>
        </div>

    </div>

    <!-- ==================== MODALS SECTION ==================== -->

    <!-- A. ADD USER MODAL -->
    <div id="add-user-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            
            <!-- Dark backdrop overlay -->
            <div onclick="toggleModal('add-user-modal', false)" class="fixed inset-0 bg-maroon-900/60 transition-opacity backdrop-blur-sm" aria-hidden="true"></div>

            <!-- Trick standard center alignment -->
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <!-- Modal Content Panel -->
            <div class="modal-animate-in inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border-2 border-goldlining-500/20">
                <!-- Header -->
                <div class="bg-maroon-gradient px-6 py-4 flex items-center justify-between border-b border-goldlining-500">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="user-plus" class="w-5 h-5 text-gold"></i>
                        <h3 class="text-sm font-bold text-white uppercase tracking-wider">Register Security Account</h3>
                    </div>
                    <button onclick="toggleModal('add-user-modal', false)" class="text-slate-300 hover:text-white transition-colors">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <!-- Form Fields -->
                <form id="add-user-form">
                    <div class="bg-white px-6 py-6 space-y-4">
                        
                        <!-- User ID input -->
                        <div>
                            <label for="new-user-id" class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">User ID <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 font-mono text-xs font-semibold">@</span>
                                <input type="text" id="new-user-id" required placeholder="ADM-0099 or EMP-0101" class="block w-full pl-8 pr-3 py-2 border border-slate-200 rounded-xl bg-white text-sm focus:outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon transition-colors font-mono">
                            </div>
                        </div>

                        <!-- Names elements row -->
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label for="new-first-name" class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">First Name <span class="text-red-500">*</span></label>
                                <input type="text" id="new-first-name" required placeholder="Alex" class="block w-full px-3 py-2 border border-slate-200 rounded-xl bg-white text-sm focus:outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon transition-colors">
                            </div>
                            <div>
                                <label for="new-middle-name" class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Middle Name</label>
                                <input type="text" id="new-middle-name" placeholder="John" class="block w-full px-3 py-2 border border-slate-200 rounded-xl bg-white text-sm focus:outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon transition-colors">
                            </div>
                            <div>
                                <label for="new-last-name" class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Last Name <span class="text-red-500">*</span></label>
                                <input type="text" id="new-last-name" required placeholder="Davidson" class="block w-full px-3 py-2 border border-slate-200 rounded-xl bg-white text-sm focus:outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon transition-colors">
                            </div>
                        </div>

                        <!-- Password input -->
                        <div>
                            <label for="new-password" class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Password <span class="text-red-500">*</span></label>
                            <input type="password" id="new-password" required placeholder="••••••••••••" class="block w-full px-3 py-2 border border-slate-200 rounded-xl bg-white text-sm focus:outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon transition-colors font-mono">
                        </div>

                        <!-- Account type and Gender row -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="new-account-type" class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Account Type <span class="text-red-500">*</span></label>
                                <select id="new-account-type" class="block w-full px-3 py-2 border border-slate-200 rounded-xl bg-white text-sm focus:outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon transition-colors">
                                    <option value="admin">Administrator</option>
                                    <option value="employee_with_pricelist">Employee With Pricelist</option>
                                    <option value="worker">Worker</option>
                                </select>
                            </div>
                            <div>
                                <label for="new-gender" class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Gender <span class="text-red-500">*</span></label>
                                <select id="new-gender" required class="block w-full px-3 py-2 border border-slate-200 rounded-xl bg-white text-sm focus:outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon transition-colors">
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Actions -->
                    <div class="bg-slate-50 px-6 py-4 flex items-center justify-end space-x-3 border-t border-slate-100">
                        <button type="button" onclick="toggleModal('add-user-modal', false)" class="px-4 py-2 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 text-xs font-bold rounded-xl transition-all">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2 bg-maroon hover:bg-maroon-hover text-white text-xs font-bold rounded-xl shadow-md transition-all">
                            Submit Credentials
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- B. FILTER MODAL -->
    <div id="filter-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            
            <!-- Dark backdrop overlay -->
            <div onclick="toggleModal('filter-modal', false)" class="fixed inset-0 bg-maroon-900/60 transition-opacity backdrop-blur-sm" aria-hidden="true"></div>

            <!-- Center alignment trick -->
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <!-- Modal Panel -->
            <div class="modal-animate-in inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full border-2 border-goldlining-500/20">
                
                <!-- Header -->
                <div class="bg-maroon-gradient px-6 py-4 flex items-center justify-between border-b border-goldlining-500">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="sliders-horizontal" class="w-5 h-5 text-gold"></i>
                        <h3 class="text-sm font-bold text-white uppercase tracking-wider">Filter Parameters</h3>
                    </div>
                    <button onclick="toggleModal('filter-modal', false)" class="text-slate-300 hover:text-white transition-colors">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <!-- Filters -->
                <div class="bg-white px-6 py-6 space-y-4">
                    
                    <!-- Account Type Filter -->
                    <div>
                        <label for="filter-account-type" class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Filter by Account Type</label>
                        <select id="filter-account-type" class="block w-full px-3 py-2 border border-slate-200 rounded-xl bg-white text-sm focus:outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon transition-colors">
                            <option value="all">All Account Types</option>
                            <option value="admin">Administrator Only</option>
                            <option value="employee">Employee Only</option>
                            <option value="customer">Customer Only</option>
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <label for="filter-status" class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Filter by Status</label>
                        <select id="filter-status" class="block w-full px-3 py-2 border border-slate-200 rounded-xl bg-white text-sm focus:outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon transition-colors">
                            <option value="all">All Statuses</option>
                            <option value="Online">Online Accounts</option>
                            <option value="Offline">Offline Accounts</option>
                        </select>
                    </div>
                </div>

                <!-- Actions -->
                <div class="bg-slate-50 px-6 py-4 flex items-center justify-between border-t border-slate-100">
                    <button id="reset-filters-btn" class="text-xs text-maroon hover:underline font-bold transition-all">
                        Reset Filters
                    </button>
                    <div class="flex items-center space-x-2">
                        <button onclick="toggleModal('filter-modal', false)" class="px-4 py-2 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 text-xs font-bold rounded-xl transition-all">
                            Cancel
                        </button>
                        <button id="apply-filters-btn" class="px-5 py-2 bg-maroon hover:bg-maroon-hover text-white text-xs font-bold rounded-xl shadow-md transition-all">
                            Apply Matrix
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- C. CONFIRM ADD USER MODAL -->
    <div id="confirm-add-modal" class="fixed inset-0 z-[60] overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div onclick="toggleModal('confirm-add-modal', false)" class="fixed inset-0 bg-maroon-900/60 transition-opacity backdrop-blur-sm" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="modal-animate-in inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full border-2 border-goldlining-500/20">
                <div class="bg-white px-6 py-6 text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-amber-100 mb-4">
                        <i data-lucide="alert-circle" class="h-6 w-6 text-amber-600"></i>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800 mb-2">Confirm Account Creation</h3>
                    <p class="text-sm text-slate-500">Are you sure you want to register this new security account in the database?</p>
                </div>
                <div class="bg-slate-50 px-6 py-4 flex items-center justify-center space-x-3 border-t border-slate-100">
                    <button onclick="toggleModal('confirm-add-modal', false)" class="px-4 py-2 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 text-xs font-bold rounded-xl transition-all">
                        Review Again
                    </button>
                    <button id="confirm-add-submit-btn" class="px-5 py-2 bg-maroon hover:bg-maroon-hover text-white text-xs font-bold rounded-xl shadow-md transition-all">
                        Yes, Create Account
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- D. SUCCESS ADD USER MODAL -->
    <div id="success-add-modal" class="fixed inset-0 z-[70] overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-maroon-900/60 transition-opacity backdrop-blur-sm" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="modal-animate-in inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full border-2 border-goldlining-500/20">
                <div class="bg-white px-6 py-6 text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-emerald-100 mb-4">
                        <i data-lucide="check-circle-2" class="h-6 w-6 text-emerald-600"></i>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800 mb-2">Account Created!</h3>
                    <p id="success-add-msg" class="text-sm text-slate-500">The security account has been successfully registered and is now active.</p>
                </div>
                <div class="bg-slate-50 px-6 py-4 flex items-center justify-center border-t border-slate-100">
                    <button onclick="toggleModal('success-add-modal', false)" class="px-8 py-2 bg-maroon hover:bg-maroon-hover text-white text-xs font-bold rounded-xl shadow-md transition-all">
                        Great, Continue
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- E. CONFIRM RESET PASSWORD MODAL -->
    <div id="confirm-reset-modal" class="fixed inset-0 z-[60] overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div onclick="toggleModal('confirm-reset-modal', false)" class="fixed inset-0 bg-maroon-900/60 transition-opacity backdrop-blur-sm" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="modal-animate-in inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full border-2 border-goldlining-500/20">
                <div class="bg-white px-6 py-6 text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-amber-100 mb-4">
                        <i data-lucide="key-round" class="h-6 w-6 text-amber-600"></i>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800 mb-2">Confirm Password Reset</h3>
                    <p id="confirm-reset-msg" class="text-sm text-slate-500">Are you sure you want to update the security credentials for this account?</p>
                </div>
                <div class="bg-slate-50 px-6 py-4 flex items-center justify-center space-x-3 border-t border-slate-100">
                    <button onclick="toggleModal('confirm-reset-modal', false)" class="px-4 py-2 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 text-xs font-bold rounded-xl transition-all">
                        Cancel
                    </button>
                    <button id="confirm-reset-submit-btn" class="px-5 py-2 bg-maroon hover:bg-maroon-hover text-white text-xs font-bold rounded-xl shadow-md transition-all">
                        Confirm Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- F. SUCCESS RESET PASSWORD MODAL -->
    <div id="success-reset-modal" class="fixed inset-0 z-[70] overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-maroon-900/60 transition-opacity backdrop-blur-sm" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="modal-animate-in inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full border-2 border-goldlining-500/20">
                <div class="bg-white px-6 py-6 text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-emerald-100 mb-4">
                        <i data-lucide="shield-check" class="h-6 w-6 text-emerald-600"></i>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800 mb-2">Password Updated!</h3>
                    <p id="success-reset-msg" class="text-sm text-slate-500">The security credentials have been successfully updated in the database.</p>
                </div>
                <div class="bg-slate-50 px-6 py-4 flex items-center justify-center border-t border-slate-100">
                    <button onclick="toggleModal('success-reset-modal', false)" class="px-8 py-2 bg-maroon hover:bg-maroon-hover text-white text-xs font-bold rounded-xl shadow-md transition-all">
                        Done
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- G. CONFIRM DELETE USER MODAL -->
    <div id="confirm-delete-modal" class="fixed inset-0 z-[60] overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div onclick="toggleModal('confirm-delete-modal', false)" class="fixed inset-0 bg-maroon-900/60 transition-opacity backdrop-blur-sm" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="modal-animate-in inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full border-2 border-goldlining-500/20">
                <div class="bg-white px-6 py-6 text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                        <i data-lucide="trash-2" class="h-6 w-6 text-red-600"></i>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800 mb-2">Confirm Account Deletion</h3>
                    <p id="confirm-delete-msg" class="text-sm text-slate-500">Are you sure you want to permanently remove this security account? This action is irreversible.</p>
                </div>
                <div class="bg-slate-50 px-6 py-4 flex items-center justify-center space-x-3 border-t border-slate-100">
                    <button onclick="toggleModal('confirm-delete-modal', false)" class="px-4 py-2 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 text-xs font-bold rounded-xl transition-all">
                        No, Keep Account
                    </button>
                    <button id="confirm-delete-submit-btn" class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-xl shadow-md transition-all">
                        Yes, Delete Permanently
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- H. SUCCESS DELETE USER MODAL -->
    <div id="success-delete-modal" class="fixed inset-0 z-[70] overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-maroon-900/60 transition-opacity backdrop-blur-sm" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="modal-animate-in inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full border-2 border-goldlining-500/20">
                <div class="bg-white px-6 py-6 text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-emerald-100 mb-4">
                        <i data-lucide="user-x" class="h-6 w-6 text-emerald-600"></i>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800 mb-2">Account Deleted</h3>
                    <p id="success-delete-msg" class="text-sm text-slate-500">The security account has been successfully purged from the system.</p>
                </div>
                <div class="bg-slate-50 px-6 py-4 flex items-center justify-center border-t border-slate-100">
                    <button onclick="toggleModal('success-delete-modal', false)" class="px-8 py-2 bg-maroon hover:bg-maroon-hover text-white text-xs font-bold rounded-xl shadow-md transition-all">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script src="<?php echo e(asset('js/usm.js')); ?>?v=<?php echo e(time()); ?>"></script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('partials.admin.admin_sidebar_navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php /**PATH C:\xampp\htdocs\hatdog\resources\views\Admin\System Security\USM.blade.php ENDPATH**/ ?>