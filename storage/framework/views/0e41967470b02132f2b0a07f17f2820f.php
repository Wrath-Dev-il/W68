

<?php $__env->startPush('styles'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/sales-report.css')); ?>?v=<?php echo e(time()); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('sales_report_content'); ?>
<div id="page-sales-report" class="rep-page space-y-6">
    <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4 bg-white border border-slate-200 rounded-xl p-5 shadow-sm no-print">
        <div>
            <p class="text-[10px] font-black uppercase tracking-widest text-maroon-700">Reports Engine</p>
            <h2 class="mt-1 text-xl font-black text-slate-900">Statement Of Account</h2>
            <p class="text-xs font-semibold text-slate-500 mt-1">Unpaid Customer Invoices List</p>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm no-print">
        <h3 class="text-xs font-black uppercase tracking-widest text-slate-700 mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
            <i data-lucide="filter" class="w-4 h-4 text-maroon-700"></i>
            Report Configuration
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-2 md:col-span-2">
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Report Type</label>
                <label class="sales-report-radio-row">
                    <input type="radio" name="sales-report-type" value="statement-of-account" checked class="sales-report-radio-input">
                    <span class="sales-report-radio-ui"></span>
                    <span class="text-xs font-semibold text-slate-700">Statement of Account (SOA)</span>
                </label>
            </div>

            <div class="space-y-2 md:col-span-2">
                <label for="sales-report-date-type" class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Date Type</label>
                <div class="relative">
                    <select id="sales-report-date-type" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all appearance-none cursor-pointer">
                        <option value="monthly">Monthly</option>
                        <option value="annual">Annual</option>
                        <option value="as-of">As of</option>
                        <option value="from-to">From TO</option>
                    </select>
                    <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-slate-400">
                        <i data-lucide="chevron-down" class="w-4 h-4"></i>
                    </div>
                </div>
            </div>

            <div id="sales-report-date-inputs" class="sales-report-date-inputs md:col-span-2"></div>

            <div class="space-y-2 relative">
                <label for="sales-report-customer" class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Customer</label>
                <div class="sales-report-searchable" data-endpoint="<?php echo e(route('special.sales-report.customers')); ?>" data-type="object" data-input-id="sales-report-customer" data-default-label="Select All">
                    <div class="relative">
                        <input id="sales-report-customer" type="text" value="Select All" autocomplete="off" placeholder="Type to search or click ▼ to browse..." class="sales-report-input w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-10 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="building-2" class="w-4 h-4"></i>
                        </div>
                        <button type="button" class="sales-report-toggle absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-maroon-700 transition-colors">
                            <i data-lucide="chevron-down" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <div class="sales-report-dropdown absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl z-50 overflow-hidden divide-y divide-slate-100 hidden"></div>
                </div>
            </div>

            <div class="space-y-2 relative">
                <label for="sales-report-description" class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Description</label>
                <div class="sales-report-searchable" data-endpoint="<?php echo e(route('special.sales-report.descriptions')); ?>" data-type="text" data-input-id="sales-report-description">
                    <div class="relative">
                        <input id="sales-report-description" type="text" autocomplete="off" placeholder="Type to search or click ▼ to browse..." class="sales-report-input w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-10 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="search" class="w-4 h-4"></i>
                        </div>
                        <button type="button" class="sales-report-toggle absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-maroon-700 transition-colors">
                            <i data-lucide="chevron-down" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <div class="sales-report-dropdown absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl z-50 overflow-hidden divide-y divide-slate-100 hidden"></div>
                </div>
            </div>

            <div class="space-y-2 relative">
                <label for="sales-report-category" class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Category</label>
                <div class="sales-report-searchable" data-endpoint="<?php echo e(route('special.sales-report.categories')); ?>" data-type="text" data-input-id="sales-report-category">
                    <div class="relative">
                        <input id="sales-report-category" type="text" autocomplete="off" placeholder="Type to search or click ▼ to browse..." class="sales-report-input w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-10 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="tag" class="w-4 h-4"></i>
                        </div>
                        <button type="button" class="sales-report-toggle absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-maroon-700 transition-colors">
                            <i data-lucide="chevron-down" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <div class="sales-report-dropdown absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl z-50 overflow-hidden divide-y divide-slate-100 hidden"></div>
                </div>
            </div>

            <div class="space-y-2 relative">
                <label for="sales-report-agent" class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Agent</label>
                <div class="sales-report-searchable" data-endpoint="<?php echo e(route('special.sales-report.agents')); ?>" data-type="text" data-input-id="sales-report-agent">
                    <div class="relative">
                        <input id="sales-report-agent" type="text" autocomplete="off" placeholder="Type to search or click ▼ to browse..." class="sales-report-input w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-10 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="user-round" class="w-4 h-4"></i>
                        </div>
                        <button type="button" class="sales-report-toggle absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-maroon-700 transition-colors">
                            <i data-lucide="chevron-down" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <div class="sales-report-dropdown absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl z-50 overflow-hidden divide-y divide-slate-100 hidden"></div>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-3 mt-6 pt-4 border-t border-slate-100">
            <button type="button" id="sales-report-clear-btn" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-5 py-3 text-[10px] font-black uppercase tracking-widest text-slate-500 hover:bg-slate-50 transition-all">
                <i data-lucide="x-circle" class="h-4 w-4"></i>
                <span>Clear Filters</span>
            </button>
            <button type="button" id="sales-report-generate-btn" class="inline-flex items-center justify-center gap-2 rounded-xl bg-maroon-900 px-6 py-3 text-[10px] font-black uppercase tracking-widest text-white shadow-md hover:bg-maroon-800 transition-all">
                <i data-lucide="printer" class="h-4 w-4 text-goldlining-400"></i>
                <span>Print Report</span>
            </button>
        </div>
    </div>

    <div id="sales-report-toast" class="sales-report-toast hidden">
        <i data-lucide="sparkles" class="h-4 w-4"></i>
        <span id="sales-report-toast-text">Report action is ready.</span>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script>
        window.salesReportRoutes = {
            customers: "<?php echo e(route('special.sales-report.customers')); ?>",
            descriptions: "<?php echo e(route('special.sales-report.descriptions')); ?>",
            categories: "<?php echo e(route('special.sales-report.categories')); ?>",
            agents: "<?php echo e(route('special.sales-report.agents')); ?>",
            printData: "<?php echo e(route('special.sales-report.print-data')); ?>",
            printSoa: "<?php echo e(route('special.sales-report.print-soa')); ?>"
        };
    </script>
    <script src="<?php echo e(asset('js/sales-report.js')); ?>?v=<?php echo e(time()); ?>"></script>
<?php $__env->stopPush(); ?>






<?php echo $__env->make('partials.special_user.special_sidebar_navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\hatdog\resources\views/Special_User/Reports/sales-report.blade.php ENDPATH**/ ?>