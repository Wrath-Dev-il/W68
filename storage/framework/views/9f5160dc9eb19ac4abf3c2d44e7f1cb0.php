

<?php $__env->startPush('styles'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/accounts-receivable.css')); ?>?v=<?php echo e(time()); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('accounts_receivable_content'); ?>
<div id="page-accounts-receivable" class="rep-page space-y-6" data-print-url="<?php echo e(route('regular.accounts-receivable.print-data')); ?>">
    <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4 bg-white border border-slate-200 rounded-xl p-5 shadow-sm no-print">
        <div>
            <p class="text-[10px] font-black uppercase tracking-widest text-maroon-700">Reports Engine</p>
            <h2 class="mt-1 text-xl font-black text-slate-900">Accounts Receivable</h2>
            <p class="text-xs font-semibold text-slate-500 mt-1">Generate accounts receivable report with date and filter controls.</p>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm no-print">
        <h3 class="text-xs font-black uppercase tracking-widest text-slate-700 mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
            <i data-lucide="filter" class="w-4 h-4 text-maroon-700"></i>
            Report Configuration
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-2 md:col-span-2">
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Radio Button</label>
                <label class="ar-radio-row">
                    <input type="radio" name="ar-report-type" value="ar-by-agent" checked class="ar-radio-input">
                    <span class="ar-radio-ui"></span>
                    <span class="text-xs font-semibold text-slate-700">Account Receivable by Agent</span>
                </label>
            </div>

            <div class="space-y-2 md:col-span-2">
                <label for="ar-date-type" class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Date Type</label>
                <div class="relative">
                    <select id="ar-date-type" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all appearance-none cursor-pointer">
                        <option value="annual">Annual</option>
                        <option value="monthly">Monthly</option>
                        <option value="as-of">As of</option>
                        <option value="from-to">From TO</option>
                    </select>
                    <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-slate-400">
                        <i data-lucide="chevron-down" class="w-4 h-4"></i>
                    </div>
                </div>
            </div>

            <div id="ar-date-inputs" class="md:col-span-2"></div>

            <div class="space-y-2 relative">
                <label for="ar-customer" class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Customer</label>
                <div class="ar-searchable" data-endpoint="<?php echo e(route('regular.sales-report.customers')); ?>" data-type="object" data-input-id="ar-customer" data-default-label="Select All">
                    <input type="hidden" id="ar-customer-id" value="">
                    <div class="relative">
                        <input id="ar-customer" type="text" value="Select All" autocomplete="off" placeholder="Type to search or click ▼ to browse..." class="ar-input w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-10 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="building-2" class="w-4 h-4"></i>
                        </div>
                        <button type="button" class="ar-toggle absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-maroon-700 transition-colors">
                            <i data-lucide="chevron-down" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <div class="ar-dropdown absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl z-50 overflow-hidden divide-y divide-slate-100 hidden"></div>
                </div>
            </div>

            <div class="space-y-2 relative">
                <label for="ar-agent" class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Agent (Salesman)</label>
                <div class="ar-searchable" data-endpoint="<?php echo e(route('regular.sales-report.agents')); ?>" data-type="text" data-input-id="ar-agent" data-default-label="Select All">
                    <div class="relative">
                        <input id="ar-agent" type="text" value="Select All" autocomplete="off" placeholder="Type to search or click ▼ to browse..." class="ar-input w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-10 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="user-round" class="w-4 h-4"></i>
                        </div>
                        <button type="button" class="ar-toggle absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-maroon-700 transition-colors">
                            <i data-lucide="chevron-down" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <div class="ar-dropdown absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl z-50 overflow-hidden divide-y divide-slate-100 hidden"></div>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-3 mt-6 pt-4 border-t border-slate-100">
            <button type="button" id="ar-print-btn" class="inline-flex items-center justify-center gap-2 rounded-xl bg-maroon-900 px-6 py-3 text-[10px] font-black uppercase tracking-widest text-white shadow-md hover:bg-maroon-800 transition-all">
                <i data-lucide="printer" class="h-4 w-4 text-goldlining-400"></i>
                <span>Print</span>
            </button>
        </div>
    </div>

    <div id="ar-print-area" class="ar-print-layout">
        <div class="ar-print-sheet">
            <div class="ar-print-header">
                <div class="ar-print-company">W68 AUTOPARTS &amp; SERVICE CENTER</div>
                <div class="ar-print-title">Accounts Receivable By Agent</div>
                <div class="ar-print-meta">
                    <span>[<span id="ar-print-date-type">Date Type</span>]</span>
                    <span>[<span id="ar-print-date-value">Date</span>]</span>
                </div>
            </div>

            <div id="ar-print-sections" class="ar-print-sections">
                <div class="ar-print-empty">No accounts receivable found.</div>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script src="<?php echo e(asset('js/accounts-receivable.js')); ?>?v=<?php echo e(time()); ?>"></script>
<?php $__env->stopPush(); ?>



<?php echo $__env->make('partials.user_account.user_sidebar_navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\hatdog\resources\views\Regular_User\Reports\Accounts-Receivable.blade.php ENDPATH**/ ?>