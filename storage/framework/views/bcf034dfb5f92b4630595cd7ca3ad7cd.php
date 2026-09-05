

<?php $__env->startPush('styles'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/Forwarder_Master-list.css')); ?>?v=<?php echo e(time()); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('forwarder_master_content'); ?>
<script>
    // Global function to fix malformed URLs for Forwarder module
    (function() {
        const originalFetch = window.fetch;
        window.fetch = function(input, init) {
            if (typeof input === 'string') {
                // If the URL starts with /regular/masterlist/forwarder and we are in /hatdog/public
                if (input.startsWith('/regular/masterlist/forwarder') && window.location.pathname.includes('/hatdog/public')) {
                    input = '/hatdog/public' + input;
                }
            }
            return originalFetch(input, init);
        };
    })();
</script>
<div id="page-forwarder-master-root" class="space-y-8 animate-fade-in p-6">

    <!-- HEADER SECTION -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
        <div>
            <h2 class="text-lg font-bold text-slate-800 tracking-tight">Forwarder Master List</h2>
            <p class="text-xs text-slate-400">Logistics and shipment tracking database.</p>
        </div>
        
        <div class="flex items-center space-x-3">
            <button onclick="openAddForwarderModal()" class="px-4 py-2.5 bg-maroon text-white text-xs font-bold rounded-xl shadow-lg flex items-center space-x-2">
                <i data-lucide="plus-circle" class="w-4 h-4 text-gold"></i>
                <span>Add Forwarder</span>
            </button>
        </div>
    </div>

    <!-- DASHBOARD CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
            <div class="space-y-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Forward</span>
                <h3 id="stat-total-forward" class="text-2xl font-extrabold text-slate-800">0</h3>
            </div>
            <div class="w-12 h-12 rounded-xl bg-maroon-gradient flex items-center justify-center shadow-lg">
                <i data-lucide="plane" class="w-6 h-6 text-gold"></i>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
            <div class="space-y-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Monthly Forward</span>
                <h3 id="stat-monthly-forward" class="text-2xl font-extrabold text-maroon">0</h3>
            </div>
            <div class="w-12 h-12 rounded-xl bg-maroon-gradient flex items-center justify-center shadow-lg">
                <i data-lucide="calendar" class="w-6 h-6 text-gold"></i>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
            <div class="space-y-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Annual Forward</span>
                <h3 id="stat-annual-forward" class="text-2xl font-extrabold text-maroon">0</h3>
            </div>
            <div class="w-12 h-12 rounded-xl bg-maroon-gradient flex items-center justify-center shadow-lg">
                <i data-lucide="trending-up" class="w-6 h-6 text-gold"></i>
            </div>
        </div>
    </div>

    <!-- TABLE VIEW -->
    <div id="forwarder-table-container" class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/50 text-slate-400 text-[10px] font-bold uppercase tracking-widest">
                        <th class="py-5 px-6">Code</th>
                        <th class="py-5 px-6">Name</th>
                        <th class="py-5 px-6">Address</th>
                        <th class="py-5 px-6">Contact No#</th>
                        <th class="py-5 px-6">Contact Person</th>
                        <th class="py-5 px-6 text-center">Action</th>
                    </tr>
                    <tr class="bg-white border-b border-slate-100">
                        <th class="p-2 px-6"><input type="text" id="col-search-code" placeholder="Search Code..." class="column-search-input text-[10px]"></th>
                        <th class="p-2 px-6"><input type="text" id="col-search-name" placeholder="Search Name..." class="column-search-input text-[10px]"></th>
                        <th class="p-2 px-6"><input type="text" id="col-search-address" placeholder="Search Address..." class="column-search-input text-[10px]"></th>
                        <th class="p-2 px-6"><input type="text" id="col-search-contact" placeholder="Search Contact..." class="column-search-input text-[10px]"></th>
                        <th class="p-2 px-6"><input type="text" id="col-search-person" placeholder="Search Person..." class="column-search-input text-[10px]"></th>
                        <th class="p-2 px-6"></th>
                    </tr>
                </thead>
                <tbody id="forwarder-tbody" class="divide-y divide-slate-100 text-sm text-slate-600">
                    <!-- JS Rendered -->
                </tbody>
            </table>
        </div>

        <!-- PAGINATION SECTION -->
        <div class="p-4 border-t border-slate-100 bg-slate-50/30 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                Showing <span id="fw-page-from" class="text-maroon">0</span> to <span id="fw-page-to" class="text-maroon">0</span> of <span id="fw-page-total" class="text-maroon">0</span> entries
            </div>
            <div id="fw-pagination-container" class="flex items-center space-x-1">
                <!-- Pagination buttons will be inserted here -->
            </div>
        </div>
    </div>

    <!-- MODALS -->

    <!-- ADD FORWARDER MODAL -->
    <div id="add-forwarder-modal" class="fixed inset-0 z-[900] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div onclick="toggleModal('add-forwarder-modal', false)" class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-3xl shadow-2xl max-w-lg w-full overflow-hidden flex flex-col">
                <div class="bg-maroon-gradient px-8 py-5 flex items-center justify-between">
                    <div class="flex items-center space-x-3 text-white">
                        <i data-lucide="plus-circle" class="w-6 h-6 text-gold"></i>
                        <h3 id="forwarder-modal-title" class="text-sm font-bold uppercase tracking-widest">Add Forwarder</h3>
                    </div>
                    <button onclick="toggleModal('add-forwarder-modal', false)" class="text-white/70 hover:text-white p-2 hover:bg-white/10 rounded-full">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>
                <form id="add-forwarder-form" onsubmit="saveForwarder(event)" class="p-8 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Code</label>
                            <input type="text" name="code" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Name</label>
                            <input type="text" name="name" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Contact Number</label>
                        <input type="text" name="contactNo" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Contact Person</label>
                        <input type="text" name="contactPerson" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Address</label>
                        <textarea name="address" rows="3" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon resize-none"></textarea>
                    </div>
                    <div class="pt-4 flex justify-end space-x-3">
                        <button type="button" onclick="toggleModal('add-forwarder-modal', false)" class="px-6 py-2.5 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-xl">Cancel</button>
                        <button type="submit" class="px-6 py-2.5 bg-maroon text-white text-[10px] font-bold rounded-xl shadow-lg flex items-center gap-2">
                            <i id="forwarder-submit-icon" data-lucide="plus-circle" class="w-4 h-4 text-gold"></i>
                            <span id="forwarder-submit-text">Save Forwarder</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- VIEW FORWARDER MODAL -->
    <div id="view-forwarder-modal" class="fixed inset-0 z-[900] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div onclick="toggleModal('view-forwarder-modal', false)" class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-3xl shadow-2xl max-w-[90vw] w-full h-[85vh] overflow-hidden flex flex-col">
                <div class="bg-maroon-gradient px-8 py-4 flex items-center justify-between">
                    <div class="flex items-center space-x-3 text-white">
                        <i data-lucide="plane" class="w-6 h-6 text-gold"></i>
                        <h3 class="text-sm font-bold uppercase tracking-widest">Forwarder Dashboard Profile</h3>
                    </div>
                    <button onclick="toggleModal('view-forwarder-modal', false)" class="text-white/70 hover:text-white p-2 hover:bg-white/10 rounded-full">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>

                <div class="flex flex-1 overflow-hidden">
                    <!-- LEFT SIDE (30%) -->
                    <div class="w-[30%] bg-slate-50 border-r border-slate-200 p-8 overflow-y-auto custom-scrollbar">
                        <div class="text-center pb-8 border-b border-slate-200 mb-8">
                            <div class="w-24 h-24 rounded-3xl bg-white shadow-md mx-auto mb-4 flex items-center justify-center border border-slate-100">
                                <i data-lucide="truck" class="w-10 h-10 text-maroon/20"></i>
                            </div>
                            <h4 id="view-fw-name" class="text-lg font-extrabold text-slate-800">Forwarder Name</h4>
                            <span id="view-fw-code" class="px-3 py-1 rounded-full text-[9px] font-bold uppercase tracking-tighter bg-slate-200">CODE</span>
                        </div>
                        <div class="space-y-6">
                            <div><p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Address</p><p id="view-fw-address" class="text-sm text-slate-700 font-semibold mt-1"></p></div>
                            <div><p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Contact No#</p><p id="view-fw-contact" class="text-sm text-slate-700 font-semibold mt-1"></p></div>
                            <div><p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Contact Person</p><p id="view-fw-person" class="text-sm text-slate-700 font-semibold mt-1"></p></div>
                        </div>
                    </div>

                    <!-- RIGHT SIDE (70%) -->
                    <div class="w-[70%] bg-white flex flex-col overflow-hidden p-8">
                        <div class="flex items-center justify-between mb-6">
                            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-widest flex items-center gap-2">
                                <i data-lucide="book-open" class="w-4 h-4 text-maroon"></i>
                                Ledger Transactions
                            </h4>
                            <div class="flex items-center gap-3">
                                <input type="date" id="ledger-from" class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-[10px] outline-none">
                                <span class="text-slate-300 text-[10px]">to</span>
                                <input type="date" id="ledger-to" class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-[10px] outline-none">
                                <button onclick="filterLedgerByDate()" class="px-4 py-1.5 bg-maroon text-white text-[10px] font-bold rounded-lg shadow-md">Apply</button>
                            </div>
                        </div>

                        <div class="flex-1 overflow-hidden border border-slate-100 rounded-2xl shadow-sm flex flex-col">
                            <div class="overflow-x-auto flex-1 custom-scrollbar">
                                <table class="w-full text-left border-collapse min-w-[800px]">
                                    <thead class="sticky top-0 bg-slate-50 text-slate-400 text-[9px] font-bold uppercase tracking-widest z-10">
                                        <tr class="border-b">
                                            <th class="py-3 px-4">Date</th>
                                            <th class="py-3 px-4">Code</th>
                                            <th class="py-3 px-4">Module Type</th>
                                            <th class="py-3 px-4">Name</th>
                                            <th class="py-3 px-4">Title</th>
                                            <th class="py-3 px-4 text-right">Debit Amount</th>
                                            <th class="py-3 px-4 text-right">Credit Amount</th>
                                        </tr>
                                        <tr class="bg-white border-b">
                                            <th class="p-1.5 px-4"><input type="text" onkeyup="filterLedgerTable()" class="ledger-search text-[8px]" placeholder="..."></th>
                                            <th class="p-1.5 px-4"><input type="text" onkeyup="filterLedgerTable()" class="ledger-search text-[8px]" placeholder="..."></th>
                                            <th class="p-1.5 px-4"><input type="text" onkeyup="filterLedgerTable()" class="ledger-search text-[8px]" placeholder="..."></th>
                                            <th class="p-1.5 px-4"><input type="text" onkeyup="filterLedgerTable()" class="ledger-search text-[8px]" placeholder="..."></th>
                                            <th class="p-1.5 px-4"><input type="text" onkeyup="filterLedgerTable()" class="ledger-search text-[8px]" placeholder="..."></th>
                                            <th class="p-1.5 px-4"><input type="text" onkeyup="filterLedgerTable()" class="ledger-search text-[8px]" placeholder="..."></th>
                                            <th class="p-1.5 px-4"><input type="text" onkeyup="filterLedgerTable()" class="ledger-search text-[8px]" placeholder="..."></th>
                                        </tr>
                                    </thead>
                                    <tbody id="ledger-tbody" class="divide-y text-[11px] text-slate-600"></tbody>
                                    <tfoot class="sticky bottom-0 bg-slate-100 font-extrabold text-slate-800 text-[11px]">
                                        <tr class="border-b border-slate-200">
                                            <td colspan="5" class="py-3 px-4 text-right uppercase tracking-widest text-slate-400">Total Transactions:</td>
                                            <td id="ledger-total-count" colspan="2" class="py-3 px-4 text-left text-maroon">0</td>
                                        </tr>
                                        <tr>
                                            <td colspan="5" class="py-3 px-4 text-right uppercase tracking-widest">Summary Totals:</td>
                                            <td id="ledger-total-debit" class="py-3 px-4 text-right text-red-600">0.00</td>
                                            <td id="ledger-total-credit" class="py-3 px-4 text-right text-emerald-600">0.00</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CONFIRMATION MODAL -->
    <div id="confirm-modal" class="fixed inset-0 z-[950] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div onclick="closeConfirmModal()" class="fixed inset-0 bg-maroon-900/40 backdrop-blur-[2px]"></div>
            <div class="modal-animate-in relative bg-white rounded-3xl shadow-2xl max-w-sm w-full p-8 text-center space-y-6">
                <div id="confirm-icon-container" class="w-20 h-20 rounded-full bg-slate-50 mx-auto flex items-center justify-center shadow-inner">
                    <i id="confirm-icon" data-lucide="help-circle" class="w-10 h-10 text-slate-400"></i>
                </div>
                <div>
                    <h3 id="confirm-title" class="text-lg font-extrabold text-slate-800 tracking-tight">Are you sure?</h3>
                    <p id="confirm-message" class="text-xs text-slate-400 font-medium leading-relaxed mt-2">This action will update the database records.</p>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="closeConfirmModal()" class="flex-1 py-3 bg-slate-100 text-slate-700 text-[10px] font-bold rounded-xl hover:bg-slate-200 transition-all uppercase tracking-widest">Cancel</button>
                    <button id="confirm-action-btn" class="flex-1 py-3 bg-maroon text-white text-[10px] font-bold rounded-xl shadow-lg hover:shadow-maroon/20 transition-all uppercase tracking-widest">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <!-- SUCCESS MODAL -->
    <div id="success-modal" class="fixed inset-0 z-[1000] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div onclick="toggleModal('success-modal', false)" class="fixed inset-0 bg-emerald-900/20 backdrop-blur-[2px]"></div>
            <div class="modal-animate-in relative bg-white rounded-3xl shadow-2xl max-w-sm w-full p-8 text-center space-y-6 border-b-8 border-emerald-500">
                <div class="w-20 h-20 rounded-full bg-emerald-50 mx-auto flex items-center justify-center shadow-inner animate-bounce">
                    <i data-lucide="check-circle-2" class="w-10 h-10 text-emerald-500"></i>
                </div>
                <div>
                    <h3 id="success-title" class="text-lg font-extrabold text-slate-800 tracking-tight">Success!</h3>
                    <p id="success-message" class="text-xs text-slate-400 font-medium leading-relaxed mt-2">Forwarder has been registered successfully.</p>
                </div>
                <button onclick="toggleModal('success-modal', false)" class="w-full py-3 bg-emerald-500 text-white text-[10px] font-bold rounded-xl shadow-lg shadow-emerald-500/20 hover:bg-emerald-600 transition-all uppercase tracking-widest">Great, thanks!</button>
            </div>
        </div>
    </div>

</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script>
        window.forwarderRoutes = {
            data: '<?php echo e(route("regular.forwarder-master.data")); ?>',
            create: '<?php echo e(route("regular.forwarder-master.create")); ?>',
            update: '<?php echo e(route("regular.forwarder-master.update", ["id" => ":id"])); ?>',
            delete: '<?php echo e(route("regular.forwarder-master.delete", ["id" => ":id"])); ?>'
        };
    </script>
    <script src="<?php echo e(asset('js/Forwarder_Master-list.js')); ?>?v=<?php echo e(time()); ?>"></script>
<?php $__env->stopPush(); ?>



<?php echo $__env->make('partials.user_account.user_sidebar_navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\hatdog\resources\views/Regular_User/master_list/Forwarder_Master-list.blade.php ENDPATH**/ ?>