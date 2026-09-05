<?php $__env->startPush('styles'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/sync.css')); ?>?v=<?php echo e(time()); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('datasync_content'); ?>
    <div id="sync-page" class="space-y-6" data-config-url="<?php echo e(route('admin.datasync.config')); ?>" data-run-url="<?php echo e(route('admin.datasync.run')); ?>">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="min-w-0">
                    <h2 class="text-lg font-extrabold text-slate-900 tracking-tight uppercase">Data Sync</h2>
                    <p class="mt-1 text-xs text-slate-500 font-semibold">Choose a dashboard to view its tables and run sync.</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-2xl bg-maroon-900 flex items-center justify-center shadow-inner">
                        <i data-lucide="refresh-cw" class="w-6 h-6 text-goldlining-400"></i>
                    </div>
                    <div class="sync-mode-toggle">
                        <button id="sync-mode-manual" type="button" class="sync-mode-btn is-active" data-mode="manual">
                            <i data-lucide="hand" class="w-4 h-4"></i>
                            <span>Manual</span>
                        </button>
                        <button id="sync-mode-auto" type="button" class="sync-mode-btn" data-mode="auto">
                            <i data-lucide="zap" class="w-4 h-4"></i>
                            <span>Auto</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="sync-dashboards" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-5">
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
                <div class="text-xs font-semibold text-slate-400">Loading dashboards...</div>
                <div class="w-12 h-12 rounded-xl bg-maroon-900 flex items-center justify-center shadow-inner">
                    <i data-lucide="loader" class="w-6 h-6 text-goldlining-400"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-50 bg-slate-50/50 flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <h3 class="text-xs font-bold text-slate-700 uppercase tracking-widest">Tables</h3>
                    <p id="sync-active-dashboard" class="mt-1 text-[10px] text-slate-400 font-semibold truncate">Select a dashboard</p>
                </div>
                <div class="flex items-center gap-2">
                    <button id="sync-refresh" type="button" class="px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 text-[10px] font-black uppercase tracking-widest hover:border-slate-300">Reload</button>
                    <button id="sync-run-all" type="button" class="px-4 py-2 rounded-xl bg-maroon-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-maroon-800 disabled:opacity-50 disabled:cursor-not-allowed" disabled>Sync All</button>
                </div>
            </div>

            <div class="overflow-auto sync-table-scroll">
                <table class="min-w-full divide-y divide-slate-100 text-xs">
                    <thead class="bg-white sticky top-0 z-10">
                        <tr class="bg-white">
                            <th class="px-4 py-3 text-left text-[10px] font-black text-slate-600 uppercase tracking-widest w-[140px]">Connection</th>
                            <th class="px-4 py-3 text-left text-[10px] font-black text-slate-600 uppercase tracking-widest w-[220px]">Table</th>
                            <th class="px-4 py-3 text-left text-[10px] font-black text-slate-600 uppercase tracking-widest min-w-[280px]">Description</th>
                            <th class="px-4 py-3 text-left text-[10px] font-black text-slate-600 uppercase tracking-widest w-[200px]">Last Sync</th>
                            <th class="px-4 py-3 text-center text-[10px] font-black text-slate-600 uppercase tracking-widest w-[160px]">Action</th>
                        </tr>
                    </thead>
                    <tbody id="sync-tbody" class="bg-white divide-y divide-slate-50">
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-slate-400 text-xs font-semibold">Select a dashboard to view tables</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script src="<?php echo e(asset('js/sync.js')); ?>?v=<?php echo e(time()); ?>"></script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('partials.admin.admin_sidebar_navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php /**PATH C:\xampp\htdocs\hatdog\resources\views\Admin\System Security\Sync.blade.php ENDPATH**/ ?>