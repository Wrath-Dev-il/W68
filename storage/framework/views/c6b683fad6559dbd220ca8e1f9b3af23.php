<?php $__env->startPush('styles'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/Customer_Master-list.css')); ?>?v=<?php echo e(time()); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('customer_master_content'); ?>
<script>
    // Global function to fix malformed URLs for Customer module
    (function() {
        const originalFetch = window.fetch;
        window.fetch = function(input, init) {
            if (typeof input === 'string') {
                // If the URL starts with /regular/masterlist/customer and we are in /hatdog/public
                if (input.startsWith('/regular/masterlist/customer') && window.location.pathname.includes('/hatdog/public')) {
                    input = '/hatdog/public' + input;
                }
            }
            return originalFetch(input, init);
        };
    })();
</script>
<div id="page-customer-master-root" class="space-y-8 animate-fade-in p-6">

    <!-- HEADER SECTION -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
        <div>
            <h2 class="text-lg font-bold text-slate-800 tracking-tight">Customer Master Database</h2>
            <p class="text-xs text-slate-400">Relationship management and transaction monitoring.</p>
        </div>
        
        <div class="flex items-center space-x-3">
            <!-- View Switcher -->
            <div class="flex bg-slate-100 p-1 rounded-xl border border-slate-200">
                <button id="view-table-btn" onclick="toggleView('table')" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all bg-maroon-gradient text-white shadow-sm">
                    <i data-lucide="list" class="w-4 h-4 inline-block mr-1"></i> Table
                </button>
                <button id="view-card-btn" onclick="toggleView('card')" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all bg-white text-slate-600">
                    <i data-lucide="layout-grid" class="w-4 h-4 inline-block mr-1"></i> Cards
                </button>
            </div>

            <button onclick="toggleModal('filter-modal', true)" class="px-4 py-2.5 bg-white border border-slate-200 text-slate-700 text-xs font-bold rounded-xl shadow-sm flex items-center space-x-2">
                <i data-lucide="sliders-horizontal" class="w-4 h-4"></i>
                <span>Filter</span>
            </button>
            
            <button onclick="openAddCustomerModal()" class="px-4 py-2.5 bg-maroon-gradient text-white text-xs font-bold rounded-xl shadow-lg flex items-center space-x-2">
                <i data-lucide="plus-circle" class="w-4 h-4 text-gold"></i>
                <span>Add Customer</span>
            </button>
        </div>
    </div>

    <!-- STATS GRID -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
            <div class="space-y-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Customer</span>
                <h3 id="stat-total-customers" class="text-2xl font-extrabold text-slate-800">0</h3>
            </div>
            <div class="w-12 h-12 rounded-xl bg-maroon-gradient flex items-center justify-center shadow-lg">
                <i data-lucide="users" class="w-6 h-6 text-gold"></i>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
            <div class="space-y-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Regular</span>
                <h3 id="stat-total-regular" class="text-2xl font-extrabold text-blue-600">0</h3>
            </div>
            <div class="w-12 h-12 rounded-xl bg-maroon-gradient flex items-center justify-center shadow-lg">
                <i data-lucide="user-check" class="w-6 h-6 text-gold"></i>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
            <div class="space-y-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">B2B Customer</span>
                <h3 id="stat-total-b2b" class="text-2xl font-extrabold text-red-600">0</h3>
            </div>
            <div class="w-12 h-12 rounded-xl bg-maroon-gradient flex items-center justify-center shadow-lg">
                <i data-lucide="building-2" class="w-6 h-6 text-gold"></i>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
            <div class="space-y-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Casual</span>
                <h3 id="stat-total-casual" class="text-2xl font-extrabold text-emerald-600">0</h3>
            </div>
            <div class="w-12 h-12 rounded-xl bg-maroon-gradient flex items-center justify-center shadow-lg">
                <i data-lucide="user-plus" class="w-6 h-6 text-gold"></i>
            </div>
        </div>
    </div>

    <!-- MAIN SEARCH FOR LIST -->
    <div class="flex justify-end">
        <div class="relative w-full max-w-md">
            <input type="text" id="main-search-input" onkeyup="filterMainSearch()" placeholder="Search by name, person or address..." class="w-full px-5 py-3 pl-12 bg-white border border-slate-200 rounded-2xl text-sm shadow-sm outline-none focus:border-maroon focus:ring-4 focus:ring-maroon/5 transition-all">
            <i data-lucide="search" class="w-5 h-5 absolute left-4 top-3 text-slate-400"></i>
        </div>
    </div>

    <!-- TABLE VIEW -->
    <div id="customer-table-container" class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/50 text-slate-400 text-[10px] font-bold uppercase tracking-widest">
                        <th class="py-5 px-6">Name</th>
                        <th class="py-5 px-6">Contact No#</th>
                        <th class="py-5 px-6">Contact Person</th>
                        <th class="py-5 px-6">Address</th>
                        <th class="py-5 px-6">Type</th>
                        <th class="py-5 px-6 text-center">Action</th>
                    </tr>
                    <tr class="bg-white border-b border-slate-100">
                        <th class="p-2 px-6"><input type="text" id="col-search-name" onkeyup="filterColumnSearch()" placeholder="Search Name..." class="column-search-input text-[10px]"></th>
                        <th class="p-2 px-6"><input type="text" id="col-search-contact" onkeyup="filterColumnSearch()" placeholder="Search Contact..." class="column-search-input text-[10px]"></th>
                        <th class="p-2 px-6"><input type="text" id="col-search-person" onkeyup="filterColumnSearch()" placeholder="Search Person..." class="column-search-input text-[10px]"></th>
                        <th class="p-2 px-6"><input type="text" id="col-search-address" onkeyup="filterColumnSearch()" placeholder="Search Address..." class="column-search-input text-[10px]"></th>
                        <th class="p-2 px-6"><input type="text" id="col-search-type" onkeyup="filterColumnSearch()" placeholder="Search Type..." class="column-search-input text-[10px]"></th>
                        <th class="p-2 px-6"></th>
                    </tr>
                </thead>
                <tbody id="customer-tbody" class="divide-y divide-slate-100 text-sm text-slate-600">
                    <!-- JS Rendered -->
                </tbody>
            </table>
        </div>
    </div>
    <div id="customer-table-pagination" class="p-4 border-t border-slate-100"></div>

    <!-- CARD VIEW -->
    <div id="customer-card-container" class="hidden">
        <div id="customer-card-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <!-- JS Rendered -->
        </div>
    </div>
    <div id="customer-card-pagination" class="p-4"></div>

    <!-- ================= MODALS ================= -->

    <!-- VIEW MODAL (30/70 SPLIT) -->
    <div id="view-customer-modal" class="fixed inset-0 z-[900] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div onclick="toggleModal('view-customer-modal', false)" class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-3xl shadow-2xl max-w-[95vw] w-full h-[90vh] overflow-hidden flex flex-col">
                <!-- Header -->
                <div class="bg-maroon-gradient px-8 py-4 flex items-center justify-between">
                    <div class="flex items-center space-x-3 text-white">
                        <i data-lucide="user-check" class="w-6 h-6 text-gold"></i>
                        <h3 class="text-sm font-bold uppercase tracking-widest">Customer Dashboard Profile</h3>
                    </div>
                    <button onclick="toggleModal('view-customer-modal', false)" class="text-white/70 hover:text-white p-2 hover:bg-white/10 rounded-full">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>

                <div class="flex flex-1 overflow-hidden">
                    <!-- RIGHT SIDE (30%): CUSTOMER INFO -->
                    <div class="w-[30%] bg-slate-50 border-r border-slate-200 p-6 overflow-y-auto custom-scrollbar">
                        <!-- Detail Tabs -->
                        <div class="flex p-1 bg-white border border-slate-200 rounded-xl mb-6 shadow-sm">
                            <button onclick="switchDetailTab('info')" id="detail-tab-info" class="flex-1 py-2 rounded-lg text-[10px] font-bold uppercase tracking-widest transition-all bg-maroon text-white">Customer Info</button>
                            <button onclick="switchDetailTab('bank')" id="detail-tab-bank" class="flex-1 py-2 rounded-lg text-[10px] font-bold uppercase tracking-widest transition-all text-slate-400">Bank Info</button>
                        </div>

                        <!-- Tab 1: Info -->
                        <div id="detail-content-info" class="space-y-6">
                            <div class="text-center pb-6 border-b border-slate-200">
                                <div class="w-24 h-24 rounded-3xl bg-white shadow-md mx-auto mb-3 flex items-center justify-center border border-slate-100">
                                    <i data-lucide="user" class="w-10 h-10 text-maroon/20"></i>
                                </div>
                                <h4 id="view-cust-name" class="text-lg font-extrabold text-slate-800">Name</h4>
                                <span id="view-cust-type" class="px-3 py-1 rounded-full text-[9px] font-bold uppercase tracking-tighter bg-slate-200">Type</span>
                            </div>
                            <div class="grid gap-4">
                                <div><p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Contact No#</p><p id="view-cust-contact" class="text-sm text-slate-700 font-semibold"></p></div>
                                <div><p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Contact Person</p><p id="view-cust-person" class="text-sm text-slate-700 font-semibold"></p></div>
                                <div><p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Address</p><p id="view-cust-address" class="text-sm text-slate-700 font-semibold leading-relaxed"></p></div>
                                <div><p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">TIN</p><p id="view-cust-tin" class="text-sm text-slate-700 font-mono"></p></div>
                                <div><p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Pricing Remarks</p><p id="view-cust-remarks" class="text-sm text-maroon font-bold italic"></p></div>
                                <div><p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Terms</p><p id="view-cust-terms" class="text-sm text-slate-700 font-semibold"></p></div>
                            </div>
                        </div>

                        <!-- Tab 2: Bank -->
                        <div id="detail-content-bank" class="hidden space-y-6 animate-fade-in">
                            <h5 class="text-xs font-bold text-slate-800 uppercase tracking-widest border-b pb-2">Financial Credentials</h5>
                            <div class="grid gap-4 bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
                                <div><p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Bank Code</p><p id="view-bank-code" class="text-sm text-slate-700 font-bold"></p></div>
                                <div><p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Account No</p><p id="view-bank-acc" class="text-sm text-slate-700 font-mono"></p></div>
                                <div><p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Bank Name</p><p id="view-bank-name" class="text-sm text-slate-700 font-bold"></p></div>
                                <div><p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Bank Phone</p><p id="view-bank-contact" class="text-sm text-slate-700"></p></div>
                                <div><p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Bank Person</p><p id="view-bank-person" class="text-sm text-slate-700"></p></div>
                                <div><p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Bank Address</p><p id="view-bank-address" class="text-sm text-slate-700"></p></div>
                            </div>
                        </div>
                    </div>

                    <!-- LEFT SIDE (70%): TABLES -->
                    <div class="w-[70%] bg-white flex flex-col overflow-hidden">
                        <div class="flex items-center px-6 border-b border-slate-100 bg-slate-50/30">
                            <button onclick="switchViewTab('purchase')" id="tab-btn-purchase" class="px-8 py-5 text-[10px] font-bold uppercase tracking-widest border-b-2 border-maroon text-maroon">Purchase History</button>
                            <button onclick="switchViewTab('ledger')" id="tab-btn-ledger" class="px-8 py-5 text-[10px] font-bold uppercase tracking-widest border-b-2 border-transparent text-slate-400">Ledger / Payment History</button>
                            <button onclick="switchViewTab('notes')" id="tab-btn-notes" class="px-8 py-5 text-[10px] font-bold uppercase tracking-widest border-b-2 border-transparent text-slate-400">Notes</button>
                        </div>

                        <div class="flex-1 p-6 overflow-hidden flex flex-col space-y-4">
                            <!-- Filters -->
                            <div id="customer-history-filters" class="flex items-center gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200 shadow-sm">
                                <div class="flex items-center gap-2"><span class="text-[10px] font-bold text-slate-400">From</span><input type="date" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs outline-none"></div>
                                <div class="flex items-center gap-2"><span class="text-[10px] font-bold text-slate-400">To</span><input type="date" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs outline-none"></div>
                                <button class="px-4 py-1.5 bg-maroon text-white text-[10px] font-bold rounded-lg shadow-md">Apply</button>
                            </div>

                            <!-- Tab 1 Content: Purchase History -->
                            <div id="view-container-purchase" class="flex-1 overflow-hidden border border-slate-100 rounded-2xl shadow-sm flex flex-col">
                                <div class="overflow-x-auto flex-1 custom-scrollbar">
                                    <table class="w-full text-left border-collapse min-w-[1200px]">
                                        <thead class="sticky top-0 bg-slate-50 text-slate-400 text-[9px] font-bold uppercase tracking-widest z-10">
                                            <tr class="border-b">
                                                <th class="py-3 px-4">SO No.</th><th class="py-3 px-4">Date</th><th class="py-3 px-4">Inv No</th><th class="py-3 px-4">Item Code</th><th class="py-3 px-4">Description</th><th class="py-3 px-4 text-center">QTY</th><th class="py-3 px-4 text-center">QTY+</th><th class="py-3 px-4">Unit</th><th class="py-3 px-4 text-right">Unit Price</th><th class="py-3 px-4 text-center">%Disc</th><th class="py-3 px-4 text-right">Sub Total</th><th class="py-3 px-4">Particulars</th>
                                            </tr>
                                            <tr class="bg-white border-b">
                                                <th class="p-1.5 px-4"><input type="text" id="purchase-search-onhand" onkeyup="filterPurchaseSearch()" class="column-search-input text-[8px]" placeholder="..."></th>
                                                <th class="p-1.5 px-4"><input type="text" id="purchase-search-date" onkeyup="filterPurchaseSearch()" class="column-search-input text-[8px]" placeholder="..."></th>
                                                <th class="p-1.5 px-4"><input type="text" id="purchase-search-inv" onkeyup="filterPurchaseSearch()" class="column-search-input text-[8px]" placeholder="..."></th>
                                                <th class="p-1.5 px-4"><input type="text" id="purchase-search-code" onkeyup="filterPurchaseSearch()" class="column-search-input text-[8px]" placeholder="..."></th>
                                                <th class="p-1.5 px-4"><input type="text" id="purchase-search-desc" onkeyup="filterPurchaseSearch()" class="column-search-input text-[8px]" placeholder="..."></th>
                                                <th class="p-1.5 px-4"><input type="text" id="purchase-search-qty" onkeyup="filterPurchaseSearch()" class="column-search-input text-[8px]" placeholder="..."></th>
                                                <th class="p-1.5 px-4"><input type="text" id="purchase-search-qplus" onkeyup="filterPurchaseSearch()" class="column-search-input text-[8px]" placeholder="..."></th>
                                                <th class="p-1.5 px-4"><input type="text" id="purchase-search-unit" onkeyup="filterPurchaseSearch()" class="column-search-input text-[8px]" placeholder="..."></th>
                                                <th class="p-1.5 px-4"><input type="text" id="purchase-search-price" onkeyup="filterPurchaseSearch()" class="column-search-input text-[8px]" placeholder="..."></th>
                                                <th class="p-1.5 px-4"><input type="text" id="purchase-search-disc" onkeyup="filterPurchaseSearch()" class="column-search-input text-[8px]" placeholder="..."></th>
                                                <th class="p-1.5 px-4"><input type="text" id="purchase-search-total" onkeyup="filterPurchaseSearch()" class="column-search-input text-[8px]" placeholder="..."></th>
                                                <th class="p-1.5 px-4"><input type="text" id="purchase-search-part" onkeyup="filterPurchaseSearch()" class="column-search-input text-[8px]" placeholder="..."></th>
                                            </tr>
                                        </thead>
                                        <tbody id="purchase-history-tbody" class="divide-y text-[11px] text-slate-600"></tbody>
                                        <tfoot class="sticky bottom-0 bg-slate-100 font-extrabold text-slate-800 text-[11px]">
                                            <tr>
                                                <td colspan="10" class="py-3 px-4 text-right uppercase tracking-widest">Total Purchase History:</td>
                                                <td id="purchase-total-amount" class="py-3 px-4 text-right text-maroon">0.00</td>
                                                <td class="py-3 px-4"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>

                            <div id="view-container-ledger" class="hidden flex-1 overflow-hidden border border-slate-100 rounded-2xl shadow-sm flex flex-col">
                                <div class="overflow-x-auto flex-1 custom-scrollbar">
                                    <table class="w-full text-left border-collapse min-w-[1000px]">
                                        <thead class="sticky top-0 bg-slate-50 text-slate-400 text-[9px] font-bold uppercase tracking-widest z-10">
                                            <tr class="border-b">
                                                <th class="py-3 px-4">Date</th>
                                                <th class="py-3 px-4">Code</th>
                                                <th class="py-3 px-4">Module Type</th>
                                                <th class="py-3 px-4">Title</th>
                                                <th class="py-3 px-4 text-right">Debit Amount</th>
                                                <th class="py-3 px-4 text-right">Credit Amount</th>
                                                <th class="py-3 px-4 text-right">Balance</th>
                                            </tr>
                                            <tr class="bg-white border-b">
                                                <th class="p-1.5 px-4"><input type="text" id="ledger-search-date" onkeyup="filterLedgerSearch()" class="column-search-input text-[8px]" placeholder="Search..."></th>
                                                <th class="p-1.5 px-4"><input type="text" id="ledger-search-code" onkeyup="filterLedgerSearch()" class="column-search-input text-[8px]" placeholder="Search..."></th>
                                                <th class="p-1.5 px-4"><input type="text" id="ledger-search-module" onkeyup="filterLedgerSearch()" class="column-search-input text-[8px]" placeholder="Search..."></th>
                                                <th class="p-1.5 px-4"><input type="text" id="ledger-search-title" onkeyup="filterLedgerSearch()" class="column-search-input text-[8px]" placeholder="Search..."></th>
                                                <th class="p-1.5 px-4"><input type="text" id="ledger-search-debit" onkeyup="filterLedgerSearch()" class="column-search-input text-[8px]" placeholder="Search..."></th>
                                                <th class="p-1.5 px-4"><input type="text" id="ledger-search-credit" onkeyup="filterLedgerSearch()" class="column-search-input text-[8px]" placeholder="Search..."></th>
                                                <th class="p-1.5 px-4"><input type="text" id="ledger-search-balance" onkeyup="filterLedgerSearch()" class="column-search-input text-[8px]" placeholder="Search..."></th>
                                            </tr>
                                        </thead>
                                        <tbody id="ledger-tbody" class="divide-y text-[11px] text-slate-600"></tbody>
                                        <tfoot class="sticky bottom-0 bg-slate-100 font-extrabold text-slate-800 text-[11px]">
                                            <tr>
                                                <td colspan="4" class="py-3 px-4 text-right uppercase tracking-widest">Total Ledger:</td>
                                                <td id="ledger-total-debit" class="py-3 px-4 text-right text-red-600">0.00</td>
                                                <td id="ledger-total-credit" class="py-3 px-4 text-right text-emerald-600">0.00</td>
                                                <td id="ledger-total-balance" class="py-3 px-4 text-right text-maroon">0.00</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                            <!-- Tab 3 Content: Customer Notes -->
                            <div id="view-container-notes" class="hidden flex-1 overflow-hidden border border-slate-100 rounded-2xl shadow-sm flex flex-col bg-slate-50/40">
                                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 bg-white">
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-700">Customer Notes</p>
                                        <p class="mt-1 text-[9px] font-semibold text-slate-400">The note saves automatically when you leave the text box.</p>
                                    </div>
                                    <div id="customer-notes-status" class="text-[9px] font-black uppercase tracking-widest text-slate-400">Not loaded</div>
                                </div>
                                <div class="flex-1 p-5 overflow-hidden">
                                    <textarea id="customer-notes-textarea" maxlength="16000" onblur="saveCustomerNotes()" class="w-full h-full min-h-[260px] resize-none rounded-2xl border border-slate-200 bg-white p-5 text-sm leading-relaxed text-slate-700 outline-none transition-all focus:border-maroon focus:ring-4 focus:ring-maroon/5" placeholder="Write notes for this customer..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ADD CUSTOMER MODAL (WIZARD) -->
    <div id="add-customer-modal" class="fixed inset-0 z-[900] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div onclick="toggleModal('add-customer-modal', false)" class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-3xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden flex flex-col">
                <!-- Header -->
                <div class="bg-maroon-gradient px-8 py-5 flex items-center justify-between">
                    <div class="flex items-center space-x-3 text-white">
                        <i data-lucide="plus-circle" class="w-6 h-6 text-gold"></i>
                        <div>
                            <h3 id="customer-modal-title" class="text-sm font-bold uppercase tracking-widest">Register New Customer</h3>
                            <p id="wizard-step-title" class="text-[10px] text-white/60 font-medium uppercase tracking-tighter">Step 1: Customer Information</p>
                        </div>
                    </div>
                    <button onclick="toggleModal('add-customer-modal', false)" class="text-white/70 hover:text-white p-2 hover:bg-white/10 rounded-full transition-all">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>

                <!-- Wizard Progress -->
                <div class="px-8 pt-6">
                    <div class="flex items-center justify-between relative">
                        <div class="absolute top-1/2 left-0 w-full h-0.5 bg-slate-100 -translate-y-1/2 z-0"></div>
                        <div id="wizard-progress-bar" class="absolute top-1/2 left-0 w-0 h-0.5 bg-maroon -translate-y-1/2 z-0 transition-all duration-500"></div>
                        
                        <div class="relative z-10 flex flex-col items-center">
                            <div id="step-dot-1" class="w-8 h-8 rounded-full bg-maroon text-white flex items-center justify-center text-xs font-bold transition-all duration-300 shadow-lg shadow-maroon/20">1</div>
                            <span class="text-[8px] font-bold text-maroon uppercase mt-1 tracking-tighter">Info</span>
                        </div>
                        <div class="relative z-10 flex flex-col items-center">
                            <div id="step-dot-2" class="w-8 h-8 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center text-xs font-bold transition-all duration-300">2</div>
                            <span class="text-[8px] font-bold text-slate-400 uppercase mt-1 tracking-tighter">Bank</span>
                        </div>
                        <div class="relative z-10 flex flex-col items-center">
                            <div id="step-dot-3" class="w-8 h-8 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center text-xs font-bold transition-all duration-300">3</div>
                            <span class="text-[8px] font-bold text-slate-400 uppercase mt-1 tracking-tighter">Review</span>
                        </div>
                    </div>
                </div>

                <!-- Form Content -->
                <form id="add-customer-form" onsubmit="handleWizardSubmit(event)" class="flex-1 overflow-y-auto custom-scrollbar p-8">
                    <!-- Step 1: Customer Information -->
                    <div id="wizard-step-1" class="space-y-6 animate-fade-in">
                        <div class="space-y-4">
                            <div class="group">
                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Name</label>
                                <input type="text" name="name" id="wizard-name" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon focus:ring-4 focus:ring-maroon/5 transition-all" placeholder="Enter full name or company">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Contact No#</label>
                                    <input type="text" name="contactNo" id="wizard-contactNo" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon focus:ring-4 focus:ring-maroon/5 transition-all" placeholder="09xx-xxx-xxxx">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Contact Person</label>
                                    <input type="text" name="contactPerson" id="wizard-contactPerson" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon focus:ring-4 focus:ring-maroon/5 transition-all" placeholder="PIC Name">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Address</label>
                                <textarea name="address" id="wizard-address" rows="2" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon focus:ring-4 focus:ring-maroon/5 transition-all resize-none" placeholder="Complete address..."></textarea>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">TIN</label>
                                    <input type="text" name="tin" id="wizard-tin" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon focus:ring-4 focus:ring-maroon/5 transition-all" placeholder="xxx-xxx-xxx-xxx">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Type</label>
                                    <select name="type" id="wizard-type" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon focus:ring-4 focus:ring-maroon/5 transition-all appearance-none">
                                        <option value="Regular">Regular</option>
                                        <option value="B2B Customer">B2B Customer</option>
                                        <option value="Casual">Casual</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Pricing Remarks</label>
                                <input type="text" name="pricingRemarks" id="wizard-remarks" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon focus:ring-4 focus:ring-maroon/5 transition-all" placeholder="e.g. VIP 10% Discount">
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Bank Information -->
                    <div id="wizard-step-2" class="hidden space-y-6 animate-fade-in">
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Bank Code</label>
                                    <input type="text" name="bankCode" id="wizard-bankCode" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon focus:ring-4 focus:ring-maroon/5 transition-all" placeholder="e.g. BPI-001">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Account No</label>
                                    <input type="text" name="bankAccNo" id="wizard-bankAccNo" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon focus:ring-4 focus:ring-maroon/5 transition-all font-mono" placeholder="xxxx-xxxx-xx">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Bank Name</label>
                                <input type="text" name="bankName" id="wizard-bankName" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon focus:ring-4 focus:ring-maroon/5 transition-all" placeholder="Bank branch/name">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Bank Contact</label>
                                    <input type="text" name="bankContact" id="wizard-bankContact" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon focus:ring-4 focus:ring-maroon/5 transition-all" placeholder="Phone">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Bank Person</label>
                                    <input type="text" name="bankPerson" id="wizard-bankPerson" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon focus:ring-4 focus:ring-maroon/5 transition-all" placeholder="Bank Manager/Teller">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Bank Address</label>
                                <textarea name="bankAddress" id="wizard-bankAddress" rows="2" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon focus:ring-4 focus:ring-maroon/5 transition-all resize-none" placeholder="Bank branch address..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Review -->
                    <div id="wizard-step-3" class="hidden space-y-6 animate-fade-in">
                        <div class="bg-slate-50 rounded-2xl p-6 border border-slate-100 space-y-6">
                            <div>
                                <h4 class="text-[10px] font-bold text-maroon uppercase tracking-widest border-b border-maroon/10 pb-2 mb-4">Customer Details</h4>
                                <div class="grid grid-cols-2 gap-y-4 text-xs">
                                    <div><p class="text-slate-400 font-bold uppercase text-[8px]">Name</p><p id="review-name" class="text-slate-700 font-extrabold"></p></div>
                                    <div><p class="text-slate-400 font-bold uppercase text-[8px]">Type</p><p id="review-type" class="text-slate-700 font-extrabold"></p></div>
                                    <div><p class="text-slate-400 font-bold uppercase text-[8px]">Contact</p><p id="review-contact" class="text-slate-700"></p></div>
                                    <div><p class="text-slate-400 font-bold uppercase text-[8px]">Person</p><p id="review-person" class="text-slate-700"></p></div>
                                    <div class="col-span-2"><p class="text-slate-400 font-bold uppercase text-[8px]">Address</p><p id="review-address" class="text-slate-700 leading-relaxed"></p></div>
                                </div>
                            </div>
                            <div>
                                <h4 class="text-[10px] font-bold text-maroon uppercase tracking-widest border-b border-maroon/10 pb-2 mb-4">Bank Details</h4>
                                <div class="grid grid-cols-2 gap-y-4 text-xs">
                                    <div><p class="text-slate-400 font-bold uppercase text-[8px]">Bank</p><p id="review-bankName" class="text-slate-700 font-extrabold"></p></div>
                                    <div><p class="text-slate-400 font-bold uppercase text-[8px]">Account</p><p id="review-bankAcc" class="text-slate-700 font-mono"></p></div>
                                    <div><p class="text-slate-400 font-bold uppercase text-[8px]">Bank Code</p><p id="review-bankCode" class="text-slate-700"></p></div>
                                    <div><p class="text-slate-400 font-bold uppercase text-[8px]">Bank Person</p><p id="review-bankPerson" class="text-slate-700"></p></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Buttons -->
                    <div class="mt-10 pt-6 border-t border-slate-100 flex items-center justify-between">
                        <button type="button" id="wizard-prev-btn" onclick="wizardMove(-1)" class="invisible px-6 py-3 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-xl hover:bg-slate-200 transition-all uppercase tracking-widest flex items-center space-x-2">
                            <i data-lucide="chevron-left" class="w-4 h-4"></i>
                            <span>Previous</span>
                        </button>
                        
                        <div class="flex items-center space-x-3">
                            <button type="button" onclick="toggleModal('add-customer-modal', false)" class="px-6 py-3 text-slate-400 text-[10px] font-bold hover:text-slate-600 transition-all uppercase tracking-widest">Cancel</button>
                            <button type="button" id="wizard-next-btn" onclick="handleWizardNext()" class="px-10 py-3 bg-maroon text-white text-[10px] font-bold rounded-xl shadow-lg hover:shadow-maroon/20 transition-all flex items-center space-x-2 uppercase tracking-widest">
                                <span id="next-btn-text">Next Step</span>
                                <i data-lucide="chevron-right" id="next-btn-icon" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                </form>
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
                    <button onclick="closeConfirmModal()" class="flex-1 py-3 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-xl hover:bg-slate-200 transition-all uppercase tracking-widest">Cancel</button>
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
                    <p id="success-message" class="text-xs text-slate-400 font-medium leading-relaxed mt-2">Customer has been registered successfully.</p>
                </div>
                <button onclick="toggleModal('success-modal', false)" class="w-full py-3 bg-emerald-500 text-white text-[10px] font-bold rounded-xl shadow-lg shadow-emerald-500/20 hover:bg-emerald-600 transition-all uppercase tracking-widest">Great, thanks!</button>
            </div>
        </div>
    </div>

</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script>
        window.customerRoutes = {
            data: '<?php echo e(route("regular.customer-master.data")); ?>',
            create: '<?php echo e(route("regular.customer-master.create")); ?>',
            update: '<?php echo e(route("regular.customer-master.update", ["id" => ":id"])); ?>',
            delete: '<?php echo e(route("regular.customer-master.delete", ["id" => ":id"])); ?>',
            purchaseHistory: '<?php echo e(route("regular.customer-master.purchase-history", ["id" => ":id"])); ?>',
            paymentHistory: '<?php echo e(route("regular.customer-master.payment-history", ["id" => ":id"])); ?>',
            paymentHistoryDetail: '<?php echo e(route("regular.customer-master.payment-history-detail", ["id" => ":id", "salesOrderId" => ":salesOrderId"])); ?>',
            notes: '<?php echo e(url("/regular/master-list/customer-master/notes/:id")); ?>'
        };
    </script>
    <script src="<?php echo e(asset('js/Customer_Master-list.js')); ?>?v=<?php echo e(time()); ?>"></script>
<?php $__env->stopPush(); ?>




<?php echo $__env->make('partials.special_user.special_sidebar_navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\hatdog\resources\views\Special_User\master_list\Customer_Master-list.blade.php ENDPATH**/ ?>