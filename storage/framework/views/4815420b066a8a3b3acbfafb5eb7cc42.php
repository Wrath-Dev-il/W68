<?php $__env->startPush('styles'); ?>
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/inventory_adjustment.css']); ?>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('inventory_adjustment_content'); ?>
<script>
    window.adjustmentRoutes = {
        fetch: '<?php echo e(route("admin.inv-adjust.data")); ?>',
        adjust: '<?php echo e(route("admin.inv-adjust.save")); ?>',
        ledger: '<?php echo e(route("admin.prod-master.ledger", ["id" => ":id"])); ?>'
    };
</script>
<div id="page-inventory-adjustment-root" class="space-y-8 animate-fade-in p-6">

    <!-- HEADER SECTION -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
        <div>
            <h2 class="text-lg font-bold text-slate-800 tracking-tight text-maroon">Inventory Adjustment</h2>
            <p class="text-xs text-slate-400 font-medium">Reconcile physical stock levels and track correction history.</p>
        </div>
        
        <div class="flex items-center space-x-3">
            <button onclick="toggleModal('filter-modal', true)" class="px-4 py-2.5 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-200 transition-all flex items-center space-x-2">
                <i data-lucide="filter" class="w-4 h-4"></i>
                <span>Filter Options</span>
            </button>
        </div>
    </div>

    <!-- DASHBOARD CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between group hover:border-slate-200 transition-all">
            <div class="space-y-1">
                <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest">Total Items</span>
                <h3 id="stat-total-items" class="text-3xl font-black text-slate-800">0</h3>
            </div>
            <div class="w-14 h-14 rounded-2xl bg-maroon flex items-center justify-center shadow-inner">
                <i data-lucide="package" class="w-7 h-7 text-gold"></i>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between group hover:border-slate-200 transition-all">
            <div class="space-y-1">
                <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest">New Items</span>
                <h3 id="stat-new-items" class="text-3xl font-black text-slate-800">0</h3>
            </div>
            <div class="w-14 h-14 rounded-2xl bg-maroon flex items-center justify-center shadow-inner">
                <i data-lucide="sparkles" class="w-7 h-7 text-gold"></i>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between group hover:border-slate-200 transition-all">
            <div class="space-y-1">
                <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest">Old Items</span>
                <h3 id="stat-old-items" class="text-3xl font-black text-slate-800">0</h3>
            </div>
            <div class="w-14 h-14 rounded-2xl bg-maroon flex items-center justify-center shadow-inner">
                <i data-lucide="clock" class="w-7 h-7 text-gold"></i>
            </div>
        </div>
    </div>

    <!-- MAIN TABLE -->
    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-slate-400 text-[10px] font-bold uppercase tracking-widest">
                        <th class="py-5 px-6">Product Code</th>
                        <th class="py-5 px-6">Name</th>
                        <th class="py-5 px-6">Description</th>
                        <th class="py-5 px-6">Application</th>
                        <th class="py-5 px-6 text-center">Actual Qty</th>
                        <th class="py-5 px-6 text-center">Qty Diff</th>
                        <th class="py-5 px-6 text-center">Action</th>
                    </tr>
                    <tr class="bg-white border-b border-slate-100">
                        <th class="p-2 px-6"><input type="text" onkeyup="filterAdjTable()" class="col-search-input text-[10px]" placeholder="Code..."></th>
                        <th class="p-2 px-6"><input type="text" onkeyup="filterAdjTable()" class="col-search-input text-[10px]" placeholder="Name..."></th>
                        <th class="p-2 px-6"><input type="text" onkeyup="filterAdjTable()" class="col-search-input text-[10px]" placeholder="Desc..."></th>
                        <th class="p-2 px-6"><input type="text" onkeyup="filterAdjTable()" class="col-search-input text-[10px]" placeholder="App..."></th>
                        <th class="p-2 px-6"><input type="text" onkeyup="filterAdjTable()" class="col-search-input text-[10px]" placeholder="Qty..."></th>
                        <th class="p-2 px-6"><input type="text" onkeyup="filterAdjTable()" class="col-search-input text-[10px]" placeholder="Diff..."></th>
                        <th class="p-2 px-6"></th>
                    </tr>
                </thead>
                <tbody id="adjustment-tbody" class="divide-y divide-slate-100 text-xs text-slate-600 font-medium">
                    <!-- JS Rendered -->
                </tbody>
            </table>
        </div>
        <!-- FOOTER PAGINATION & SUMMARY -->
        <div class="p-5 border-t border-slate-100 bg-slate-50/30 flex items-center justify-between flex-shrink-0">
            <span id="pagination-info" class="text-xs text-slate-500 font-medium">Showing 0 to 0 of 0 entries</span>
            <div id="pagination-container" class="flex items-center space-x-2">
                <!-- Buttons rendered in JS -->
            </div>
        </div>
    </div>

    <!-- MODALS -->

    <!-- ADJUST MODAL -->
    <div id="adjust-modal" class="fixed inset-0 z-50 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div onclick="toggleModal('adjust-modal', false)" class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-3xl shadow-2xl max-w-md w-full overflow-hidden flex flex-col">
                <div class="bg-maroon-gradient px-8 py-5 flex items-center justify-between">
                    <div class="flex items-center space-x-3 text-white">
                        <i data-lucide="sliders-horizontal" class="w-6 h-6 text-gold"></i>
                        <h3 class="text-sm font-bold uppercase tracking-widest">Adjust Inventory</h3>
                    </div>
                    <button onclick="toggleModal('adjust-modal', false)" class="text-white/70 hover:text-white p-2 hover:bg-white/10 rounded-full transition-all">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>
                <div class="p-8 space-y-6">
                    <div class="text-center pb-4 border-b border-slate-100">
                        <h4 id="adj-item-name" class="text-lg font-black text-slate-800">Product Name</h4>
                        <p id="adj-item-code" class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">ITEM-CODE</p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-6">
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-bold text-slate-400 uppercase ml-1">Actual QTY</label>
                            <div id="adj-actual-qty" class="w-full px-4 py-3 bg-slate-50 border border-slate-100 rounded-xl text-lg font-black text-slate-800 shadow-inner">0</div>
                        </div>
                        <div class="space-y-1.5 text-right">
                            <label class="text-[10px] font-bold text-slate-400 uppercase mr-1">Difference</label>
                            <div id="adj-diff-qty" class="w-full px-4 py-3 bg-slate-50 border border-slate-100 rounded-xl text-lg font-black text-maroon shadow-inner">0</div>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-[10px] font-bold text-slate-400 uppercase ml-1">New Adjusted Quantity</label>
                        <input type="number" id="adj-input-qty" oninput="calculateAdjTotal()" class="w-full px-6 py-4 bg-white border-2 border-slate-100 rounded-2xl text-2xl font-black text-slate-800 outline-none focus:border-maroon focus:ring-4 focus:ring-maroon/5 transition-all" placeholder="0">
                    </div>

                    <div class="p-4 bg-maroon/5 rounded-2xl border border-maroon/10 flex items-center justify-between">
                        <span class="text-[10px] font-bold text-maroon uppercase tracking-widest">Projected Total</span>
                        <span id="adj-total-projected" class="text-xl font-black text-maroon tracking-tighter">0</span>
                    </div>

                    <div class="pt-4 grid grid-cols-2 gap-3">
                        <button onclick="toggleModal('adjust-modal', false)" class="py-3.5 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-xl hover:bg-slate-200 transition-all uppercase tracking-widest">Cancel</button>
                        <button onclick="confirmAdjustment()" class="py-3.5 bg-maroon text-white text-[10px] font-bold rounded-xl shadow-lg hover:shadow-maroon/20 transition-all uppercase tracking-widest">Adjust Now</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- VIEW HISTORY MODAL -->
    <div id="history-modal" class="fixed inset-0 z-50 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div onclick="toggleModal('history-modal', false)" class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-3xl shadow-2xl max-w-[95vw] w-full h-[90vh] overflow-hidden flex flex-col">
                <div class="bg-maroon-gradient px-8 py-4 flex items-center justify-between">
                    <div class="flex items-center space-x-3 text-white">
                        <i data-lucide="history" class="w-6 h-6 text-gold"></i>
                        <div>
                            <h3 class="text-sm font-bold uppercase tracking-widest">Adjustment History</h3>
                            <p id="history-item-title" class="text-[10px] text-white/60 font-medium uppercase tracking-tighter">Product Name</p>
                        </div>
                    </div>
                    <button onclick="toggleModal('history-modal', false)" class="text-white/70 hover:text-white p-2 hover:bg-white/10 rounded-full transition-all">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>

                <div class="flex-1 p-8 overflow-hidden flex flex-col space-y-4">
                    <div class="overflow-x-auto flex-1 custom-scrollbar border border-slate-100 rounded-2xl">
                        <table class="w-full text-left border-collapse min-w-[1500px]">
                            <thead class="sticky top-0 bg-slate-50 text-slate-400 text-[9px] font-bold uppercase tracking-widest z-10">
                                <tr class="border-b">
                                    <th class="py-4 px-4">Module Type</th>
                                    <th class="py-4 px-4">Date</th>
                                    <th class="py-4 px-4">Trans Number</th>
                                    <th class="py-4 px-4">Ref Number</th>
                                    <th class="py-4 px-4">Name</th>
                                    <th class="py-4 px-4 text-emerald-600">In</th>
                                    <th class="py-4 px-4 text-red-600">Out</th>
                                    <th class="py-4 px-4 font-black text-slate-800">Running Total</th>
                                    <th class="py-4 px-4">UOM</th>
                                    <th class="py-4 px-4 text-right">Selling Price</th>
                                    <th class="py-4 px-4 text-right">Cost Price</th>
                                    <th class="py-4 px-4">Remarks</th>
                                </tr>
                                <tr class="bg-white border-b">
                                    <th class="p-1.5 px-4"><input type="text" onkeyup="filterHistoryTable()" class="hist-search text-[8px]" placeholder="..."></th>
                                    <th class="p-1.5 px-4"><input type="text" onkeyup="filterHistoryTable()" class="hist-search text-[8px]" placeholder="..."></th>
                                    <th class="p-1.5 px-4"><input type="text" onkeyup="filterHistoryTable()" class="hist-search text-[8px]" placeholder="..."></th>
                                    <th class="p-1.5 px-4"><input type="text" onkeyup="filterHistoryTable()" class="hist-search text-[8px]" placeholder="..."></th>
                                    <th class="p-1.5 px-4"><input type="text" onkeyup="filterHistoryTable()" class="hist-search text-[8px]" placeholder="..."></th>
                                    <th class="p-1.5 px-4"><input type="text" onkeyup="filterHistoryTable()" class="hist-search text-[8px]" placeholder="..."></th>
                                    <th class="p-1.5 px-4"><input type="text" onkeyup="filterHistoryTable()" class="hist-search text-[8px]" placeholder="..."></th>
                                    <th class="p-1.5 px-4"><input type="text" onkeyup="filterHistoryTable()" class="hist-search text-[8px]" placeholder="..."></th>
                                    <th class="p-1.5 px-4"><input type="text" onkeyup="filterHistoryTable()" class="hist-search text-[8px]" placeholder="..."></th>
                                    <th class="p-1.5 px-4"><input type="text" onkeyup="filterHistoryTable()" class="hist-search text-[8px]" placeholder="..."></th>
                                    <th class="p-1.5 px-4"><input type="text" onkeyup="filterHistoryTable()" class="hist-search text-[8px]" placeholder="..."></th>
                                    <th class="p-1.5 px-4"><input type="text" onkeyup="filterHistoryTable()" class="hist-search text-[8px]" placeholder="..."></th>
                                </tr>
                            </thead>
                            <tbody id="history-tbody" class="divide-y text-[10px] text-slate-600 font-medium">
                                <!-- JS Rendered -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FILTER MODAL -->
    <div id="filter-modal" class="fixed inset-0 z-[60] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div onclick="toggleModal('filter-modal', false)" class="fixed inset-0 bg-maroon-900/40 backdrop-blur-[2px]"></div>
            <div class="modal-animate-in relative bg-white rounded-3xl shadow-2xl max-w-sm w-full p-8 space-y-6">
                <div class="flex items-center space-x-3 text-maroon mb-2 border-b pb-4">
                    <i data-lucide="filter" class="w-5 h-5"></i>
                    <h3 class="text-sm font-bold uppercase tracking-widest">Filter Items</h3>
                </div>
                
                <div class="space-y-4">
                    <div class="space-y-2">
                        <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">By Status</label>
                        <div class="flex flex-wrap gap-2">
                            <button onclick="setFilterStatus('All')" class="px-4 py-2 rounded-xl text-[10px] font-bold bg-maroon text-white filter-status-btn">All</button>
                            <button onclick="setFilterStatus('New')" class="px-4 py-2 rounded-xl text-[10px] font-bold bg-slate-50 text-slate-500 filter-status-btn">New Items</button>
                            <button onclick="setFilterStatus('Old')" class="px-4 py-2 rounded-xl text-[10px] font-bold bg-slate-50 text-slate-500 filter-status-btn">Old Items</button>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">By Date Range</label>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="date" id="filter-date-from" class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-lg text-[10px] outline-none">
                            <input type="date" id="filter-date-to" class="w-full px-3 py-2 bg-slate-50 border border-slate-100 rounded-lg text-[10px] outline-none">
                        </div>
                    </div>
                </div>

                <div class="pt-4 flex gap-3">
                    <button onclick="resetFilters()" class="flex-1 py-3 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-xl uppercase tracking-widest transition-all hover:bg-slate-200">Reset</button>
                    <button onclick="applyFilters()" class="flex-1 py-3 bg-maroon text-white text-[10px] font-bold rounded-xl shadow-lg uppercase tracking-widest transition-all hover:shadow-maroon/20">Apply Filter</button>
                </div>
            </div>
        </div>
    </div>

    <!-- CONFIRMATION MODAL -->
    <div id="confirm-modal" class="fixed inset-0 z-[100] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-3xl shadow-2xl max-w-sm w-full p-8 text-center space-y-6">
                <div class="w-20 h-20 rounded-full bg-amber-50 mx-auto flex items-center justify-center shadow-inner">
                    <i data-lucide="alert-triangle" class="w-10 h-10 text-amber-500"></i>
                </div>
                <div>
                    <h3 class="text-lg font-black text-slate-800 tracking-tight">Confirm Adjustment?</h3>
                    <p class="text-xs text-slate-400 font-medium leading-relaxed mt-2">This will update the physical stock levels and record an entry in the audit trail.</p>
                </div>
                <div class="flex items-center gap-3 pt-2">
                    <button onclick="toggleModal('confirm-modal', false)" class="flex-1 py-3.5 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-xl hover:bg-slate-200 transition-all uppercase tracking-widest">No, Cancel</button>
                    <button id="confirm-yes-btn" class="flex-1 py-3.5 bg-maroon text-white text-[10px] font-bold rounded-xl shadow-lg hover:shadow-maroon/20 transition-all uppercase tracking-widest">Yes, Adjust</button>
                </div>
            </div>
        </div>
    </div>

    <!-- SUCCESS MODAL -->
    <div id="success-modal" class="fixed inset-0 z-[110] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div onclick="toggleModal('success-modal', false)" class="fixed inset-0 bg-emerald-900/20 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-3xl shadow-2xl max-w-sm w-full p-10 text-center space-y-6 border-b-8 border-emerald-500">
                <div class="w-24 h-24 rounded-full bg-emerald-50 mx-auto flex items-center justify-center shadow-inner animate-pulse">
                    <i data-lucide="check-circle" class="w-12 h-12 text-emerald-500"></i>
                </div>
                <div>
                    <h3 class="text-xl font-black text-slate-800 tracking-tight">Adjustment Complete!</h3>
                    <p class="text-xs text-slate-400 font-medium leading-relaxed mt-2">The inventory levels have been synchronized successfully.</p>
                </div>
                <button onclick="toggleModal('success-modal', false)" class="w-full py-4 bg-emerald-500 text-white text-[10px] font-bold rounded-xl shadow-lg shadow-emerald-500/20 hover:bg-emerald-600 transition-all uppercase tracking-widest">Confirm & Close</button>
            </div>
        </div>
    </div>

</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <?php echo app('Illuminate\Foundation\Vite')(['resources/js/inventory_adjustment.js']); ?>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('partials.Warehouse_User.WareHouse-sidebar_navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php /**PATH C:\xampp\htdocs\hatdog\resources\views\Warehouse_User\inventory\inventory_adjustment.blade.php ENDPATH**/ ?>