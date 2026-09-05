<?php $__env->startPush('styles'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/archive.css')); ?>?v=<?php echo e(time()); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('archived_content'); ?>
    <div id="archive-page" class="space-y-6" data-data-url="<?php echo e(route('admin.archived.data')); ?>" data-restore-url="<?php echo e(route('admin.archived.restore')); ?>">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h2 class="text-lg font-extrabold text-slate-900 tracking-tight uppercase">Archived</h2>
                    <p class="mt-1 text-xs text-slate-500 font-semibold">Auto delete data within 30 days</p>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-11 h-11 rounded-2xl bg-maroon-900 flex items-center justify-center shadow-inner">
                        <i data-lucide="archive" class="w-6 h-6 text-goldlining-400"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-5">
            <?php $__currentLoopData = [
                ['key' => 'product', 'label' => 'Product', 'icon' => 'package'],
                ['key' => 'supplier', 'label' => 'Supplier', 'icon' => 'truck'],
                ['key' => 'customer', 'label' => 'Customer', 'icon' => 'users'],
                ['key' => 'forwarder', 'label' => 'Forwarder', 'icon' => 'plane'],
                ['key' => 'inventory', 'label' => 'Inventory', 'icon' => 'boxes'],
                ['key' => 'purchase_note', 'label' => 'Purchase Note', 'icon' => 'file-text'],
                ['key' => 'purchase_return', 'label' => 'Purchase Return', 'icon' => 'undo-2'],
                ['key' => 'sales_note', 'label' => 'Sales Note', 'icon' => 'receipt'],
            ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $card): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <button type="button" class="archive-card bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between group hover:border-slate-200 transition-all hover:shadow-md hover:scale-[1.01] duration-200 overflow-hidden text-left" data-category="<?php echo e($card['key']); ?>">
                    <div class="space-y-0.5 min-w-0">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Archived</span>
                        <h3 class="text-sm font-extrabold text-slate-800 tracking-tight truncate"><?php echo e($card['label']); ?></h3>
                        <p class="text-[10px] text-slate-400 font-semibold">Click to filter table</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-maroon-900 flex items-center justify-center shadow-inner group-hover:scale-105 transition-transform duration-300 flex-shrink-0 ml-3">
                        <i data-lucide="<?php echo e($card['icon']); ?>" class="w-6 h-6 text-goldlining-400"></i>
                    </div>
                </button>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>

        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-50 bg-slate-50/50 flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <h3 class="text-xs font-bold text-slate-700 uppercase tracking-widest">Archived Data</h3>
                    <p id="archive-active-filter" class="mt-1 text-[10px] text-slate-400 font-semibold truncate">Showing: All</p>
                </div>
                <div class="flex items-center gap-2">
                    <button id="archive-clear-filters" type="button" class="px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 text-[10px] font-black uppercase tracking-widest hover:border-slate-300">Clear Filters</button>
                    <button id="archive-refresh" type="button" class="px-4 py-2 rounded-xl bg-maroon-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-maroon-800">Refresh</button>
                </div>
            </div>

            <div class="overflow-auto archive-table-scroll">
                <table class="min-w-full divide-y divide-slate-100 text-xs">
                    <thead class="bg-white sticky top-0 z-10">
                        <tr class="bg-white">
                            <th class="px-4 py-3 text-left text-[10px] font-black text-slate-600 uppercase tracking-widest w-[170px]">
                                <div class="flex items-center justify-between gap-2">
                                    <span>ID</span>
                                    <button id="archive-id-info-btn" type="button" class="archive-id-info-btn" title="ID Guide">
                                        <i data-lucide="info" class="w-4 h-4"></i>
                                    </button>
                                </div>
                                <input id="search-id" class="archive-col-search-input mt-2" placeholder="Search ID / Code">
                            </th>
                            <th class="px-4 py-3 text-left text-[10px] font-black text-slate-600 uppercase tracking-widest min-w-[260px]">
                                <span>Data Name</span>
                                <input id="search-name" class="archive-col-search-input mt-2" placeholder="Search name">
                            </th>
                            <th class="px-4 py-3 text-left text-[10px] font-black text-slate-600 uppercase tracking-widest w-[160px]">
                                <span>Date Deleted</span>
                                <input id="search-deleted" class="archive-col-search-input mt-2" placeholder="YYYY-MM-DD">
                            </th>
                            <th class="px-4 py-3 text-left text-[10px] font-black text-slate-600 uppercase tracking-widest w-[150px]">
                                <span>Remaining</span>
                                <input id="search-remaining" class="archive-col-search-input mt-2" placeholder="Days left">
                            </th>
                            <th class="px-4 py-3 text-center text-[10px] font-black text-slate-600 uppercase tracking-widest w-[150px]">
                                <span>Action</span>
                                <div class="mt-2 h-[30px]"></div>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="archive-tbody" class="bg-white divide-y divide-slate-50">
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-slate-400 text-xs font-semibold">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-slate-100 bg-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="text-[10px] text-slate-500 font-semibold">
                    <span id="archive-pagination-label">Showing 0 to 0 of 0</span>
                    <span class="mx-2 text-slate-300">•</span>
                    <span class="uppercase tracking-widest font-black text-slate-400">50 / page</span>
                </div>
                <div class="flex items-center gap-2">
                    <button id="archive-prev" type="button" class="px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 text-[10px] font-black uppercase tracking-widest hover:border-slate-300 disabled:opacity-50 disabled:cursor-not-allowed">Prev</button>
                    <div class="px-4 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-700 text-[10px] font-black uppercase tracking-widest">
                        <span id="archive-page-label">1</span>
                        <span class="mx-1 text-slate-300">/</span>
                        <span id="archive-last-page-label">1</span>
                    </div>
                    <button id="archive-next" type="button" class="px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 text-[10px] font-black uppercase tracking-widest hover:border-slate-300 disabled:opacity-50 disabled:cursor-not-allowed">Next</button>
                </div>
            </div>
        </div>

        <div id="archive-confirm-restore-modal" class="fixed inset-0 z-[3000] hidden items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl overflow-hidden">
                <div class="p-6 text-center">
                    <div class="mx-auto w-16 h-16 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center">
                        <i data-lucide="rotate-ccw" class="w-9 h-9"></i>
                    </div>
                    <h3 class="mt-5 text-lg font-black text-slate-800 uppercase tracking-widest">Restore Data</h3>
                    <p id="archive-confirm-text" class="mt-2 text-sm font-semibold text-slate-500">Are you sure you want to restore this item?</p>
                    <div class="mt-6 flex items-center justify-center gap-3">
                        <button id="archive-confirm-no" type="button" class="px-6 py-3 rounded-xl bg-slate-100 text-slate-700 text-[10px] font-black uppercase tracking-widest hover:bg-slate-200">No</button>
                        <button id="archive-confirm-yes" type="button" class="px-8 py-3 rounded-xl bg-maroon-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-maroon-800">Yes</button>
                    </div>
                </div>
            </div>
        </div>

        <div id="archive-success-modal" class="fixed inset-0 z-[3010] hidden items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl overflow-hidden">
                <div class="p-8 text-center">
                    <div class="mx-auto w-16 h-16 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        <i data-lucide="check-circle-2" class="w-9 h-9"></i>
                    </div>
                    <h3 class="mt-5 text-lg font-black text-slate-800 uppercase tracking-widest">Success</h3>
                    <p id="archive-success-message" class="mt-2 text-sm font-semibold text-slate-500">The item was restored successfully.</p>
                    <button id="archive-success-done" type="button" class="mt-6 px-8 py-3 rounded-xl bg-maroon-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-maroon-800">Done</button>
                </div>
            </div>
        </div>

        <div id="archive-id-guide-modal" class="fixed inset-0 z-[3020] hidden items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"></div>
            <div class="relative w-full max-w-2xl rounded-2xl bg-white shadow-2xl overflow-hidden">
                <div class="p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <h3 class="text-sm font-black text-slate-900 uppercase tracking-widest">ID Guide</h3>
                            <p class="mt-1 text-xs text-slate-500 font-semibold">How ID is displayed per data type</p>
                        </div>
                        <button id="archive-id-guide-close" type="button" class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700">
                            <i data-lucide="x" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                        <?php $__currentLoopData = [
                            ['Product', 'Product Code'],
                            ['Supplier', 'Supplier Code'],
                            ['Customer', 'Customer Code'],
                            ['Forwarder', 'Forwarder Code'],
                            ['Inventory', 'Product Code'],
                            ['Purchase Note', 'Purchase Number'],
                            ['Purchase Return', 'Purchase Return Number'],
                            ['Sales Note', 'Sales Note Number'],
                            ['Sales Return', 'Sales Return Number'],
                            ['Waybill', 'Waybill Number'],
                            ['Payments', 'Payment Number'],
                            ['Pay. Check. V', 'ID'],
                            ['Exp. Check. V', 'ID'],
                        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="archive-guide-row">
                                <div class="font-black text-slate-800 uppercase tracking-widest text-[10px]"><?php echo e($row[0]); ?></div>
                                <div class="text-slate-500 font-semibold"><?php echo e($row[1]); ?></div>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                    <div class="mt-6 flex justify-end">
                        <button id="archive-id-guide-ok" type="button" class="px-8 py-3 rounded-xl bg-maroon-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-maroon-800">Ok</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script src="<?php echo e(asset('js/archive.js')); ?>?v=<?php echo e(time()); ?>"></script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('partials.admin.admin_sidebar_navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php /**PATH C:\xampp\htdocs\hatdog\resources\views\Admin\System Security\Archive.blade.php ENDPATH**/ ?>