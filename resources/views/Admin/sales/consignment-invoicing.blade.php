@push('styles')
    <link rel="stylesheet" href="{{ asset('css/Sales-Order.css') }}?v={{ time() }}">
@endpush

@section('cons_invoice_content')
<div id="page-cons-invoice-root" class="space-y-8 animate-fade-in text-slate-800">

    <!-- HEADER -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-maroon tracking-tight">Consignment Invoicing</h1>
            <p class="text-sm text-slate-500">Manage and process consignment invoices from active notes.</p>
        </div>
        <div class="flex items-center space-x-3">
            <button onclick="toggleModal('ci-filter-modal', true)" class="px-4 py-2.5 bg-white border border-slate-200 hover:border-maroon-200 hover:bg-slate-50 text-slate-700 hover:text-maroon text-xs font-bold rounded-xl shadow-sm flex items-center space-x-2 transition-all">
                <i data-lucide="sliders-horizontal" class="w-4 h-4 text-slate-500"></i>
                <span>Filter</span>
            </button>
        </div>
    </div>

    <!-- DASHBOARD CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between hover-card-trigger">
            <div class="space-y-1">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Open Notes</p>
                <h3 id="card-open-notes" class="text-2xl font-extrabold text-slate-800">---</h3>
            </div>
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-maroon shadow-inner">
                <i data-lucide="folder-open" class="w-7 h-7 text-gold"></i>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between hover-card-trigger">
            <div class="space-y-1">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Invoiced</p>
                <h3 id="card-invoiced" class="text-2xl font-extrabold text-slate-800">---</h3>
            </div>
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-maroon shadow-inner">
                <i data-lucide="file-check" class="w-7 h-7 text-gold"></i>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between hover-card-trigger">
            <div class="space-y-1">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Consignment Notes</p>
                <h3 id="card-consignment-notes" class="text-2xl font-extrabold text-slate-800">---</h3>
            </div>
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-maroon shadow-inner">
                <i data-lucide="file-digit" class="w-7 h-7 text-gold"></i>
            </div>
        </div>
    </div>

    <!-- TABS -->
    <div class="flex space-x-3 border-b border-slate-200 pb-0">
        <button onclick="switchConsignmentTab('active')" id="ci-tab-active" class="consignment-tab active px-6 py-3 text-xs font-bold rounded-t-xl uppercase tracking-widest">Consignment Notes</button>
        <button onclick="switchConsignmentTab('history')" id="ci-tab-history" class="consignment-tab px-6 py-3 text-xs font-bold rounded-t-xl uppercase tracking-widest">Sale's Order History</button>
    </div>

    <!-- TAB 1: CONSIGNMENT NOTES -->
    <div id="ci-tab-content-active" class="ci-tab-content">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden text-slate-800">
            <div class="p-5 border-b border-slate-50 flex justify-between items-center bg-slate-50/30">
                <h2 class="font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="list" class="w-4 h-4 text-maroon"></i>
                    Consignment Notes
                </h2>
                <div class="flex items-center space-x-2">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Showing:</span>
                    <span class="px-3 py-1 bg-maroon/10 text-maroon text-[10px] font-bold rounded-lg uppercase">Open / Partial</span>
                </div>
            </div>
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50/80 border-b border-slate-100">
                        <tr>
                            <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">SN NO.</th>
                            <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Customer Name</th>
                            <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Issue Date</th>
                            <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-right">Invoice Total</th>
                            <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Action</th>
                        </tr>
                        <tr class="bg-slate-50/30 border-b border-slate-100">
                            <th class="p-2 px-5"><input type="text" id="ci-filter-sn" placeholder="Search SN..." class="column-search-input" oninput="filterConsignmentTable()"></th>
                            <th class="p-2 px-5"><input type="text" id="ci-filter-customer" placeholder="Search Name..." class="column-search-input" oninput="filterConsignmentTable()"></th>
                            <th class="p-2 px-5"><input type="text" id="ci-filter-date" placeholder="Search Date..." class="column-search-input" oninput="filterConsignmentTable()"></th>
                            <th class="p-2 px-5"><input type="text" id="ci-filter-total" placeholder="Search Total..." class="column-search-input" oninput="filterConsignmentTable()"></th>
                            <th class="p-2 px-5"></th>
                        </tr>
                    </thead>
                    <tbody id="ci-active-notes-tbody" class="divide-y divide-slate-100 text-sm">
                        <tr><td colspan="5" class="p-4 text-center text-slate-300 italic">Loading consignment notes...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/30">
                <span id="ci-active-page-info" class="text-[10px] text-slate-400 font-bold">Page 1 of 1</span>
                <div class="flex space-x-2">
                    <button onclick="prevCIActivePage()" id="ci-active-prev-btn" class="px-3 py-1.5 bg-white border border-slate-200 text-slate-500 rounded-lg text-[10px] font-bold hover:bg-maroon hover:text-white transition-all disabled:opacity-30" disabled>&#8592; Prev</button>
                    <button onclick="nextCIActivePage()" id="ci-active-next-btn" class="px-3 py-1.5 bg-white border border-slate-200 text-slate-500 rounded-lg text-[10px] font-bold hover:bg-maroon hover:text-white transition-all disabled:opacity-30" disabled>Next &#8594;</button>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 2: SALES ORDER HISTORY -->
    <div id="ci-tab-content-history" class="ci-tab-content hidden">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden text-slate-800">
            <div class="p-5 border-b border-slate-50 flex justify-between items-center bg-slate-50/30">
                <h2 class="font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="history" class="w-4 h-4 text-maroon"></i>
                    Sale's Order History
                </h2>
            </div>
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50/80 border-b border-slate-100">
                        <tr>
                            <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Date Issue</th>
                            <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Invoice No.</th>
                            <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Waybill No</th>
                            <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Waybill Date</th>
                            <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Name</th>
                            <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-right">Total Amount</th>
                            <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Remarks</th>
                            <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Action</th>
                        </tr>
                        <tr class="bg-slate-50/30 border-b border-slate-100">
                            <th class="p-2 px-5"><input type="text" id="ci-h-filter-date" placeholder="Search Date..." class="column-search-input" oninput="filterConsignmentHistoryTable()"></th>
                            <th class="p-2 px-5"><input type="text" id="ci-h-filter-invoice" placeholder="Search Invoice..." class="column-search-input" oninput="filterConsignmentHistoryTable()"></th>
                            <th class="p-2 px-5"><input type="text" id="ci-h-filter-waybill" placeholder="Search Waybill..." class="column-search-input" oninput="filterConsignmentHistoryTable()"></th>
                            <th class="p-2 px-5"><input type="text" id="ci-h-filter-wdate" placeholder="Search W.Date..." class="column-search-input" oninput="filterConsignmentHistoryTable()"></th>
                            <th class="p-2 px-5"><input type="text" id="ci-h-filter-name" placeholder="Search Name..." class="column-search-input" oninput="filterConsignmentHistoryTable()"></th>
                            <th class="p-2 px-5"><input type="text" id="ci-h-filter-amount" placeholder="Search Amount..." class="column-search-input" oninput="filterConsignmentHistoryTable()"></th>
                            <th class="p-2 px-5"><input type="text" id="ci-h-filter-remarks" placeholder="Search Remarks..." class="column-search-input" oninput="filterConsignmentHistoryTable()"></th>
                            <th class="p-2 px-5"></th>
                        </tr>
                    </thead>
                    <tbody id="ci-history-orders-tbody" class="divide-y divide-slate-100 text-sm">
                        <tr><td colspan="8" class="p-4 text-center text-slate-300 italic">Loading history...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/30">
                <span id="ci-history-page-info" class="text-[10px] text-slate-400 font-bold">Page 1 of 1</span>
                <div class="flex space-x-2">
                    <button onclick="prevCIHistoryPage()" id="ci-history-prev-btn" class="px-3 py-1.5 bg-white border border-slate-200 text-slate-500 rounded-lg text-[10px] font-bold hover:bg-maroon hover:text-white transition-all disabled:opacity-30" disabled>&#8592; Prev</button>
                    <button onclick="nextCIHistoryPage()" id="ci-history-next-btn" class="px-3 py-1.5 bg-white border border-slate-200 text-slate-500 rounded-lg text-[10px] font-bold hover:bg-maroon hover:text-white transition-all disabled:opacity-30" disabled>Next &#8594;</button>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ==================== MODALS ==================== -->

<!-- PROCEED MODAL (3-STEP) -->
<div id="ci-proceed-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('ci-proceed-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-6xl w-full modal-animate-in mx-4 flex flex-col max-h-[95vh]">

        <!-- Modal Header -->
        <div class="bg-maroon p-6 flex justify-between items-center text-white border-b-4 border-gold">
            <div class="flex items-center space-x-4">
                <div class="p-3 bg-white/10 rounded-2xl border-2 border-gold shadow-sm">
                    <i data-lucide="file-digit" class="w-6 h-6 text-gold"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold tracking-tight">Consignment Invoicing</h3>
                    <p class="text-[10px] text-white/60 uppercase tracking-widest font-bold">Process consignment invoice</p>
                </div>
            </div>
            <button onclick="toggleModal('ci-proceed-modal', false)" class="text-white/70 hover:text-white transition-colors">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <!-- Step Progress -->
        <div class="px-6 pt-6 pb-4 bg-slate-50 border-b border-slate-100">
            <div class="flex items-center justify-center space-x-6">
                <div class="flex items-center space-x-2">
                    <div id="cistep-indicator-1" class="step-indicator active">1</div>
                    <span id="cistep-label-1" class="text-[10px] font-bold text-maroon uppercase tracking-widest">Invoice Info</span>
                </div>
                <div class="w-16 h-0.5 bg-slate-200 relative">
                    <div id="cistep-progress-1" class="absolute top-0 left-0 h-full bg-gold transition-all duration-500" style="width:0%"></div>
                </div>
                <div class="flex items-center space-x-2">
                    <div id="cistep-indicator-2" class="step-indicator pending">2</div>
                    <span id="cistep-label-2" class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Item List</span>
                </div>
                <div class="w-16 h-0.5 bg-slate-200 relative">
                    <div id="cistep-progress-2" class="absolute top-0 left-0 h-full bg-gold transition-all duration-500" style="width:0%"></div>
                </div>
                <div class="flex items-center space-x-2">
                    <div id="cistep-indicator-3" class="step-indicator pending">3</div>
                    <span id="cistep-label-3" class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Review</span>
                </div>
            </div>
        </div>

        <!-- Step 1: Invoice Info -->
        <div id="cistep-1" class="ci-step-content flex flex-col md:flex-row gap-8 p-8 overflow-y-auto flex-1 min-h-0">
            <div class="w-full md:w-[30%] space-y-4">
                <div class="p-6 bg-maroon rounded-3xl shadow-xl text-white space-y-6 relative overflow-hidden">
                    <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 bg-gold/10 rounded-full blur-2xl"></div>
                    <h4 class="text-sm font-bold uppercase tracking-widest text-gold border-b border-white/10 pb-3">Note Invoicing Info</h4>
                    <div class="space-y-4">
                        <h5 class="text-[10px] uppercase text-gold/70 font-bold tracking-widest border-b border-white/10 pb-2">Order Info</h5>
                        <div>
                            <p class="text-[9px] uppercase text-white/50 font-bold mb-1">SN NO.</p>
                            <p id="ci-p-sn-no" class="text-xs font-mono font-bold tracking-wider">---</p>
                        </div>
                        <div>
                            <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Customer Name</p>
                            <p id="ci-p-customer-name" class="text-sm font-bold text-gold">---</p>
                        </div>
                        <div>
                            <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Issue Date</p>
                            <p id="ci-p-issue-date" class="text-xs font-bold">---</p>
                        </div>
                        <div>
                            <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Grand Total</p>
                            <p id="ci-p-grand-total" class="text-xs font-bold">---</p>
                        </div>
                        <div>
                            <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Net Total</p>
                            <p id="ci-p-net-total" class="text-xs font-bold text-gold">---</p>
                        </div>
                        <div>
                            <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Note Type</p>
                            <p id="ci-p-note-type" class="text-xs font-bold">Sale's Order</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span id="ci-p-rush-badge" class="px-3 py-1 bg-white/10 text-gold text-[9px] font-bold rounded-full uppercase tracking-widest hidden">Rush</span>
                            <span id="ci-p-note-status" class="px-3 py-1 bg-white/10 text-white text-[9px] font-bold rounded-full uppercase tracking-widest">---</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="w-full md:w-[70%] space-y-6">
                <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="p-4 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-widest">Invoicing</h4>
                        <button onclick="addCIInvoiceInput()" class="px-3 py-1.5 bg-maroon text-white text-[9px] font-bold rounded-lg hover:bg-maroon-800 transition-all flex items-center space-x-1">
                            <i data-lucide="plus" class="w-3 h-3 text-gold"></i>
                            <span>Add Invoice No</span>
                        </button>
                    </div>
                    <div class="p-6 space-y-4" id="ci-invoice-inputs-container">
                        <div class="ci-invoice-row flex items-center space-x-3">
                            <div class="flex-1">
                                <p class="text-[9px] text-slate-400 font-bold mb-1 ml-1 uppercase">Invoice No.</p>
                                <input type="text" class="ci-invoice-input w-full px-3 py-2.5 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-maroon/20 outline-none transition-all" placeholder="Enter invoice number...">
                            </div>
                            <button onclick="this.closest('.ci-invoice-row').remove()" class="mt-5 p-2 bg-red-50 text-red-400 hover:bg-red-100 hover:text-red-600 rounded-lg transition-all">
                                <i data-lucide="x" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 2: Item List -->
        <div id="cistep-2" class="ci-step-content hidden flex flex-col gap-6 p-8 overflow-y-auto flex-1 min-h-0">
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="p-4 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
                    <h4 class="text-xs font-bold text-slate-800 uppercase tracking-widest">Item List</h4>
                    <span class="px-3 py-1 bg-maroon/10 text-maroon text-[9px] font-bold rounded-full uppercase">Additional QTY</span>
                </div>
                <div class="overflow-auto custom-scrollbar" style="max-height:300px">
                    <table class="w-full text-left border-collapse">
                        <thead class="sticky top-0 bg-slate-50 z-10">
                            <tr class="text-[9px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100">
                                <th class="p-4 text-center w-10"><input type="checkbox" id="ci-select-all-items" class="accent-maroon cursor-pointer" checked></th>
                                <th class="p-4">Item Code</th>
                                <th class="p-4">Description</th>
                                <th class="p-4 text-center">QTY</th>
                                <th class="p-4 text-center">Actual QTY</th>
                                <th class="p-4 text-center">Additional QTY</th>
                                <th class="p-4 text-center">UNIT</th>
                                <th class="p-4 text-right">Unit Price</th>
                                <th class="p-4 text-center">%Disc</th>
                                <th class="p-4 text-right">Sub Total</th>
                                <th class="p-4">Particulars</th>
                            </tr>
                            <tr class="bg-slate-50/30">
                                <th class="p-2 px-3 text-center"></th>
                                <th class="p-2 px-3"><input type="text" onkeyup="filterTable('ci-proceed-items-tbody', 1, this.value)" placeholder="Code..." class="column-search-input"></th>
                                <th class="p-2 px-3"><input type="text" onkeyup="filterTable('ci-proceed-items-tbody', 2, this.value)" placeholder="Desc..." class="column-search-input"></th>
                                <th class="p-2 px-3"><input type="text" onkeyup="filterTable('ci-proceed-items-tbody', 3, this.value)" placeholder="QTY..." class="column-search-input text-center"></th>
                                <th class="p-2 px-3"><input type="text" onkeyup="filterTable('ci-proceed-items-tbody', 4, this.value)" placeholder="Act..." class="column-search-input text-center"></th>
                                <th class="p-2 px-3"><input type="text" onkeyup="filterTable('ci-proceed-items-tbody', 5, this.value)" placeholder="Add..." class="column-search-input text-center"></th>
                                <th class="p-2 px-3"><input type="text" onkeyup="filterTable('ci-proceed-items-tbody', 6, this.value)" placeholder="Unit..." class="column-search-input text-center"></th>
                                <th class="p-2 px-3"><input type="text" onkeyup="filterTable('ci-proceed-items-tbody', 7, this.value)" placeholder="Price..." class="column-search-input text-right"></th>
                                <th class="p-2 px-3"><input type="text" onkeyup="filterTable('ci-proceed-items-tbody', 8, this.value)" placeholder="Disc..." class="column-search-input text-center"></th>
                                <th class="p-2 px-3"><input type="text" onkeyup="filterTable('ci-proceed-items-tbody', 9, this.value)" placeholder="SubTotal..." class="column-search-input text-right"></th>
                                <th class="p-2 px-3"><input type="text" onkeyup="filterTable('ci-proceed-items-tbody', 10, this.value)" placeholder="Notes..." class="column-search-input"></th>
                            </tr>
                        </thead>
                        <tbody id="ci-proceed-items-tbody" class="text-xs divide-y divide-slate-50">
                            <tr><td colspan="11" class="p-4 text-center text-slate-300 italic">No items loaded.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="flex justify-end px-4">
                <div class="flex items-center space-x-3 bg-amber-50 border border-amber-200 rounded-xl px-5 py-3">
                    <i data-lucide="percent" class="w-5 h-5 text-amber-600"></i>
                    <label class="text-[10px] font-bold text-amber-700 uppercase tracking-widest">Additional Discount (%)</label>
                    <input type="number" id="ci-addl-discount" value="0" min="0" max="100" step="0.01" class="w-20 px-3 py-2 border border-amber-300 rounded-lg text-sm text-center font-bold outline-none focus:ring-2 focus:ring-amber-300 bg-white">
                </div>
            </div>
        </div>

        <!-- Step 3: Review -->
        <div id="cistep-3" class="ci-step-content hidden flex flex-col md:flex-row gap-8 p-8 overflow-y-auto flex-1 min-h-0">
            <div class="w-full md:w-[30%] space-y-4">
                <div class="p-6 bg-maroon rounded-3xl shadow-xl text-white space-y-6 relative overflow-hidden">
                    <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 bg-gold/10 rounded-full blur-2xl"></div>
                    <h4 class="text-sm font-bold uppercase tracking-widest text-gold border-b border-white/10 pb-3">Note Invoicing Info</h4>
                    <div class="space-y-4">
                        <div>
                            <p class="text-[9px] uppercase text-white/50 font-bold mb-1">SN NO.</p>
                            <p id="ci-rev-sn-no" class="text-xs font-mono font-bold tracking-wider">---</p>
                        </div>
                        <div>
                            <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Customer Name</p>
                            <p id="ci-rev-customer" class="text-sm font-bold text-gold">---</p>
                        </div>
                        <div>
                            <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Invoice No(s)</p>
                            <p id="ci-rev-invoices" class="text-xs font-bold">---</p>
                        </div>
                        <div>
                            <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Issue Date</p>
                            <p id="ci-rev-date" class="text-xs font-bold">---</p>
                        </div>
                        <div>
                            <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Grand Total</p>
                            <p id="ci-rev-grand" class="text-xs font-bold">---</p>
                        </div>
                        <div>
                            <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Net Total</p>
                            <p id="ci-rev-net" class="text-xs font-bold text-gold">---</p>
                        </div>
                        <div>
                            <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Note Type</p>
                            <p id="ci-rev-type" class="text-xs font-bold">Sale's Order</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span id="ci-rev-rush" class="px-3 py-1 bg-white/10 text-gold text-[9px] font-bold rounded-full uppercase tracking-widest hidden">Rush</span>
                            <span id="ci-rev-status" class="px-3 py-1 bg-white/10 text-white text-[9px] font-bold rounded-full uppercase tracking-widest">---</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="w-full md:w-[70%] space-y-6">
                <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="p-4 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-widest">Item List</h4>
                        <span class="px-3 py-1 bg-maroon/10 text-maroon text-[9px] font-bold rounded-full uppercase">Review</span>
                    </div>
                    <div class="overflow-x-auto max-h-[350px] custom-scrollbar">
                        <table class="w-full text-left border-collapse">
                            <thead class="sticky top-0 bg-slate-50 z-10">
                                <tr class="text-[9px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100">
                                    <th class="p-4 text-center w-10">Addl</th>
                                    <th class="p-4">Item Code</th>
                                    <th class="p-4">Description</th>
                                    <th class="p-4 text-center">QTY</th>
                                    <th class="p-4 text-center">Actual QTY</th>
                                    <th class="p-4 text-center">Add QTY</th>
                                    <th class="p-4 text-center">UNIT</th>
                                    <th class="p-4 text-right">Unit Price</th>
                                    <th class="p-4 text-center">%Disc</th>
                                    <th class="p-4 text-right">Subtotal</th>
                                    <th class="p-4">Particulars</th>
                                </tr>
                                <tr class="bg-slate-50/30">
                                    <th class="p-2 px-3 text-center"></th>
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('ci-review-tbody', 1, this.value)" placeholder="Code..." class="column-search-input"></th>
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('ci-review-tbody', 2, this.value)" placeholder="Desc..." class="column-search-input"></th>
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('ci-review-tbody', 3, this.value)" placeholder="QTY..." class="column-search-input text-center"></th>
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('ci-review-tbody', 4, this.value)" placeholder="Act..." class="column-search-input text-center"></th>
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('ci-review-tbody', 5, this.value)" placeholder="Add..." class="column-search-input text-center"></th>
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('ci-review-tbody', 6, this.value)" placeholder="Unit..." class="column-search-input text-center"></th>
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('ci-review-tbody', 7, this.value)" placeholder="Price..." class="column-search-input text-right"></th>
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('ci-review-tbody', 8, this.value)" placeholder="Disc..." class="column-search-input text-center"></th>
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('ci-review-tbody', 9, this.value)" placeholder="SubTotal..." class="column-search-input text-right"></th>
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('ci-review-tbody', 10, this.value)" placeholder="Notes..." class="column-search-input"></th>
                                </tr>
                            </thead>
                            <tbody id="ci-review-tbody" class="text-xs divide-y divide-slate-50">
                                <tr><td colspan="11" class="p-4 text-center text-slate-300 italic">No items.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-6 bg-slate-50 border-t border-slate-100 flex justify-between items-center">
                        <div class="flex items-center space-x-8">
                            <div>
                                <p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Gross Total</p>
                                <h3 id="ci-rev-gross" class="text-lg font-bold text-slate-600 tracking-tight">₱ 0.00</h3>
                            </div>
                            <div class="text-slate-300 text-xl font-light">-</div>
                            <div>
                                <p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Total Discount</p>
                                <h3 id="ci-rev-discount" class="text-lg font-bold text-emerald-600 tracking-tight">₱ 0.00</h3>
                            </div>
                            <div class="text-slate-300 text-xl font-light">=</div>
                            <div>
                                <p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Net Total (PHP)</p>
                                <h3 id="ci-rev-net-total" class="text-2xl font-extrabold text-maroon tracking-tight">₱ 0.00</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Footer (Fixed) -->
        <div class="p-6 bg-slate-50 border-t border-slate-100 flex justify-between items-center">
            <div>
                <button id="ci-p-btn-back" onclick="ciProceedGoToStep(currentStep - 1)" class="hidden px-6 py-2.5 text-xs font-bold text-slate-400 hover:text-maroon transition-all uppercase tracking-widest flex items-center space-x-2">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    <span id="ci-p-back-text">Back</span>
                </button>
            </div>
            <div class="flex space-x-3">
                <button onclick="toggleModal('ci-proceed-modal', false)" class="px-6 py-2.5 text-xs font-bold text-slate-400 hover:text-slate-600 transition-all uppercase tracking-widest">Cancel</button>
                <button id="ci-p-btn-print-receipt" onclick="openCIPrintReceiptModal()" class="hidden px-6 py-2.5 bg-white border-2 border-amber-500 text-amber-600 rounded-xl text-xs font-bold hover:bg-amber-50 transition-all uppercase tracking-widest flex items-center space-x-2">
                    <i data-lucide="receipt" class="w-4 h-4"></i>
                    <span>Print Receipt</span>
                </button>
                <button id="ci-p-btn-next" onclick="ciProceedGoToStep(2)" class="px-8 py-2.5 bg-maroon text-white rounded-xl text-xs font-bold hover:bg-maroon-800 transition-all shadow-lg flex items-center space-x-2 uppercase tracking-widest">
                    <span id="ci-p-next-text">Next Step</span>
                    <i data-lucide="chevron-right" class="w-4 h-4 text-gold"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- PRINT RECEIPT TYPE MODAL -->
<div id="ci-print-receipt-modal" class="fixed inset-0 z-[110] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('ci-print-receipt-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-sm sm:w-full modal-animate-in mx-4">
        <div class="bg-maroon p-5 flex justify-between items-center text-white border-b-4 border-gold">
            <h3 class="text-sm font-bold uppercase tracking-widest">Print Receipt</h3>
            <button onclick="toggleModal('ci-print-receipt-modal', false)" class="text-white/70 hover:text-white transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <p class="text-xs text-slate-500">Select receipt type:</p>
            <label class="flex items-center space-x-3 p-4 border-2 border-slate-200 rounded-xl cursor-pointer hover:border-maroon transition-all">
                <input type="radio" name="ci-receipt-print-type" value="invoice" class="accent-maroon">
                <div>
                    <span class="text-sm font-bold text-slate-700">Sale's Invoice</span>
                    <p class="text-[10px] text-slate-400">With VAT</p>
                </div>
            </label>
            <label class="flex items-center space-x-3 p-4 border-2 border-slate-200 rounded-xl cursor-pointer hover:border-maroon transition-all">
                <input type="radio" name="ci-receipt-print-type" value="order" checked class="accent-maroon">
                <div>
                    <span class="text-sm font-bold text-slate-700">Sale's Order</span>
                    <p class="text-[10px] text-slate-400">Without VAT</p>
                </div>
            </label>
        </div>
        <div class="p-4 border-t border-slate-100 flex justify-end space-x-3">
            <button onclick="toggleModal('ci-print-receipt-modal', false)" class="px-4 py-2.5 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest">Cancel</button>
            <button onclick="submitCIPrintReceipt()" class="px-6 py-2.5 bg-maroon text-white text-xs font-bold rounded-xl hover:bg-maroon-800 transition-all uppercase tracking-widest shadow-lg shadow-maroon/20 flex items-center space-x-2">
                <i data-lucide="printer" class="w-4 h-4"></i>
                <span>Print</span>
            </button>
        </div>
    </div>
</div>

<!-- CONFIRM ORDER MODAL -->
<div id="ci-confirm-modal" class="fixed inset-0 z-[100] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('ci-confirm-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-sm sm:w-full modal-animate-in mx-4">
        <div class="p-6 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-amber-50 mb-4">
                <i data-lucide="alert-triangle" class="h-6 w-6 text-amber-600"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-800 mb-2 tracking-tight uppercase">Confirm Order</h3>
            <p class="text-xs text-slate-500 mb-4 leading-relaxed">Are you sure you want to proceed this Consignment Invoice? This action will record the transaction.</p>
        </div>
        <div class="bg-slate-50 px-6 py-4 flex flex-col sm:flex-row-reverse gap-2">
            <button onclick="finalizeConsignmentInvoice()" class="w-full px-4 py-2 bg-maroon hover:bg-maroon-800 text-white text-xs font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg shadow-maroon/20">Yes, Confirm</button>
            <button onclick="toggleModal('ci-confirm-modal', false)" class="w-full px-4 py-2 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest">No, Review</button>
        </div>
    </div>
</div>

<!-- SUCCESS MODAL -->
<div id="ci-success-modal" class="fixed inset-0 z-[120] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('ci-success-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-sm sm:w-full modal-animate-in mx-4">
        <div class="p-8 text-center">
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-emerald-50 mb-6">
                <i data-lucide="check-circle" class="h-10 w-10 text-emerald-500"></i>
            </div>
            <h3 class="text-xl font-bold text-slate-800 mb-2">Success!</h3>
            <p class="text-xs text-slate-500 leading-relaxed">The Consignment Invoice has been successfully processed.</p>
        </div>
        <div class="p-6 pt-0">
            <button onclick="toggleModal('ci-success-modal', false); location.reload()" class="w-full px-4 py-3 bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg shadow-emerald-200">Great, Continue</button>
        </div>
    </div>
</div>

<!-- VIEW MODAL -->
<div id="ci-view-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('ci-view-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-6xl w-full modal-animate-in mx-4 flex flex-col max-h-[95vh]">
        <div class="bg-maroon p-6 flex justify-between items-center text-white border-b-4 border-gold">
            <div class="flex items-center space-x-4">
                <div class="p-3 bg-white/10 rounded-2xl border-2 border-gold shadow-sm">
                    <i data-lucide="eye" class="w-6 h-6 text-gold"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold tracking-tight">Consignment Invoice Details</h3>
                    <p id="ci-view-label" class="text-[10px] text-white/60 uppercase tracking-widest font-bold">Transaction: ---</p>
                </div>
            </div>
            <button onclick="toggleModal('ci-view-modal', false)" class="text-white/70 hover:text-white transition-colors">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        <div class="flex flex-col md:flex-row h-full gap-8 p-8 overflow-y-auto">
            <div class="w-full md:w-[30%] space-y-4">
                <!-- View Sub Tabs -->
                <div class="flex space-x-1 bg-slate-100 p-1 rounded-xl">
                    <button onclick="switchCIViewSubTab('ci-view-details', this)" class="ci-view-sub-tab active flex-1 px-3 py-2 text-[9px] font-bold rounded-lg uppercase tracking-widest bg-white text-maroon shadow-sm">History Details</button>
                    <button onclick="switchCIViewSubTab('ci-view-invoicing', this)" class="ci-view-sub-tab flex-1 px-3 py-2 text-[9px] font-bold rounded-lg uppercase tracking-widest text-slate-500 hover:text-maroon">Invoice Info</button>
                </div>
                <div id="ci-view-tab-details" class="ci-view-sub-content">
                    <div class="p-6 bg-maroon rounded-3xl shadow-xl text-white space-y-6 relative overflow-hidden">
                        <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 bg-gold/10 rounded-full blur-2xl"></div>
                        <h4 class="text-sm font-bold uppercase tracking-widest text-gold border-b border-white/10 pb-3">History Details</h4>
                        <div class="space-y-4">
                            <div>
                                <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Date Issue</p>
                                <p id="ci-view-h-date" class="text-xs font-bold">---</p>
                            </div>
                            <div>
                                <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Invoice No.</p>
                                <p id="ci-view-h-invoice" class="text-xs font-bold text-gold">---</p>
                            </div>
                            <div>
                                <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Waybill No</p>
                                <p id="ci-view-h-waybill" class="text-xs font-bold">---</p>
                            </div>
                            <div>
                                <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Waybill Date</p>
                                <p id="ci-view-h-waybill-date" class="text-xs font-bold">---</p>
                            </div>
                            <div>
                                <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Customer Name</p>
                                <p id="ci-view-h-name" class="text-sm font-bold text-gold">---</p>
                            </div>
                            <div>
                                <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Total Amount</p>
                                <p id="ci-view-h-total" class="text-xs font-bold">---</p>
                            </div>
                            <div>
                                <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Remarks</p>
                                <p id="ci-view-h-remarks" class="text-[10px] text-white/70 italic">---</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="ci-view-tab-invoicing" class="ci-view-sub-content hidden">
                    <div class="p-6 bg-maroon rounded-3xl shadow-xl text-white space-y-6 relative overflow-hidden">
                        <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 bg-gold/10 rounded-full blur-2xl"></div>
                        <h4 class="text-sm font-bold uppercase tracking-widest text-gold border-b border-white/10 pb-3">Note Invoicing Info</h4>
                        <div class="space-y-4">
                            <div>
                                <p class="text-[9px] uppercase text-white/50 font-bold mb-1">SN NO.</p>
                                <p id="ci-view-i-sn" class="text-xs font-mono font-bold">---</p>
                            </div>
                            <div>
                                <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Customer Name</p>
                                <p id="ci-view-i-customer" class="text-sm font-bold text-gold">---</p>
                            </div>
                            <div>
                                <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Issue Date</p>
                                <p id="ci-view-i-date" class="text-xs font-bold">---</p>
                            </div>
                            <div>
                                <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Invoice No(s)</p>
                                <p id="ci-view-i-invoices" class="text-xs font-bold">---</p>
                            </div>
                            <div>
                                <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Note Type</p>
                                <p id="ci-view-i-type" class="text-xs font-bold">Sale's Order</p>
                            </div>
                            <div>
                                <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Status</p>
                                <p id="ci-view-i-status" class="text-xs font-bold text-gold">---</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="w-full md:w-[70%] space-y-6">
                <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="p-4 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-widest">Item List</h4>
                        <span id="ci-view-count" class="px-3 py-1 bg-maroon/10 text-maroon text-[9px] font-bold rounded-full uppercase">0 items</span>
                    </div>
                    <div class="overflow-auto custom-scrollbar max-h-[400px]">
                        <table class="w-full text-left border-collapse">
                            <thead class="sticky top-0 bg-slate-50 z-10">
                                <tr class="text-[9px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100">
                                    <th class="p-4">Item Code</th>
                                    <th class="p-4">Description</th>
                                    <th class="p-4 text-center">QTY</th>
                                    <th class="p-4 text-center">Actual QTY</th>
                                    <th class="p-4 text-center">Add QTY</th>
                                    <th class="p-4 text-center">UNIT</th>
                                    <th class="p-4 text-right">Unit Price</th>
                                    <th class="p-4 text-center">%Disc</th>
                                    <th class="p-4 text-right">Subtotal</th>
                                    <th class="p-4">Particulars</th>
                                </tr>
                                <tr class="bg-slate-50/30">
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('ci-view-order-tbody', 0, this.value)" placeholder="Code..." class="column-search-input"></th>
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('ci-view-order-tbody', 1, this.value)" placeholder="Desc..." class="column-search-input"></th>
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('ci-view-order-tbody', 2, this.value)" placeholder="QTY..." class="column-search-input text-center"></th>
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('ci-view-order-tbody', 3, this.value)" placeholder="Act..." class="column-search-input text-center"></th>
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('ci-view-order-tbody', 4, this.value)" placeholder="Add..." class="column-search-input text-center"></th>
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('ci-view-order-tbody', 5, this.value)" placeholder="Unit..." class="column-search-input text-center"></th>
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('ci-view-order-tbody', 6, this.value)" placeholder="Price..." class="column-search-input text-right"></th>
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('ci-view-order-tbody', 7, this.value)" placeholder="Disc..." class="column-search-input text-center"></th>
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('ci-view-order-tbody', 8, this.value)" placeholder="SubTotal..." class="column-search-input text-right"></th>
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('ci-view-order-tbody', 9, this.value)" placeholder="Notes..." class="column-search-input"></th>
                                </tr>
                            </thead>
                            <tbody id="ci-view-order-tbody" class="text-xs divide-y divide-slate-50">
                                <tr><td colspan="10" class="p-4 text-center text-slate-300 italic">No items.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 bg-slate-50 border-t border-slate-100 flex justify-end">
                        <h3 id="ci-view-total" class="text-lg font-extrabold text-maroon">₱ 0.00</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- FILTER MODAL -->
<div id="ci-filter-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('ci-filter-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-md sm:w-full modal-animate-in mx-4">
        <div class="bg-maroon p-5 flex justify-between items-center text-white border-b-2 border-gold">
            <h3 class="text-sm font-bold uppercase tracking-widest flex items-center gap-2">
                <i data-lucide="sliders-horizontal" class="w-4 h-4 text-gold"></i>
                Filter Records
            </h3>
            <button onclick="toggleModal('ci-filter-modal', false)" class="text-white/70 hover:text-white transition-colors">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        <div class="p-6 space-y-6">
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Filter by Date Range</label>
                <div class="grid grid-cols-2 gap-3 text-slate-800">
                    <div>
                        <p class="text-[9px] text-slate-400 font-bold mb-1 ml-1 uppercase">From</p>
                        <input type="date" id="ci-filter-date-from" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-maroon/20 outline-none transition-all">
                    </div>
                    <div>
                        <p class="text-[9px] text-slate-400 font-bold mb-1 ml-1 uppercase">To</p>
                        <input type="date" id="ci-filter-date-to" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-maroon/20 outline-none transition-all">
                    </div>
                </div>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Filter by Status</label>
                <select id="ci-filter-status" class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-maroon/20 outline-none transition-all">
                    <option value="">All Status</option>
                    <option value="Open">Open</option>
                    <option value="Partial">Partial</option>
                    <option value="Closed">Closed</option>
                    <option value="Surplus">Surplus</option>
                </select>
            </div>
        </div>
        <div class="p-6 pt-0 flex space-x-3">
            <button onclick="resetCIFilters()" class="flex-1 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] font-bold rounded-xl transition-all uppercase tracking-widest">Reset</button>
            <button onclick="applyCIFilters()" class="flex-1 px-4 py-2.5 bg-maroon text-white hover:bg-maroon-800 text-[10px] font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg shadow-maroon/20">Apply Filters</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
    <script>
        let currentStep = 1;
        let ciCurrentNoteId = null;
        let ciNoteData = null;

        window.ciProceedGoToStep = function(step) {
            currentStep = step;
            document.querySelectorAll('.ci-step-content').forEach(el => el.classList.add('hidden'));
            document.getElementById('cistep-' + step).classList.remove('hidden');

            [1, 2, 3].forEach(i => {
                const ind = document.getElementById('cistep-indicator-' + i);
                const lbl = document.getElementById('cistep-label-' + i);
                const prog = document.getElementById('cistep-progress-' + i);
                if (i < step) {
                    ind.className = 'step-indicator completed';
                    lbl.className = 'text-[10px] font-bold text-emerald-600 uppercase tracking-widest';
                    if (prog) prog.style.width = '100%';
                } else if (i === step) {
                    ind.className = 'step-indicator active';
                    lbl.className = 'text-[10px] font-bold text-maroon uppercase tracking-widest';
                    if (prog) prog.style.width = '100%';
                } else {
                    ind.className = 'step-indicator pending';
                    lbl.className = 'text-[10px] font-bold text-slate-400 uppercase tracking-widest';
                    if (prog) prog.style.width = '0%';
                }
            });

            const btnBack = document.getElementById('ci-p-btn-back');
            const btnNext = document.getElementById('ci-p-btn-next');
            const nextText = document.getElementById('ci-p-next-text');
            const backText = document.getElementById('ci-p-back-text');
            const nextIcon = document.querySelector('#ci-p-btn-next i');
            const btnPrint = document.getElementById('ci-p-btn-print-receipt');

            if (step === 1) {
                btnBack.classList.add('hidden');
                btnPrint?.classList.add('hidden');
                btnNext.onclick = () => ciProceedGoToStep(2);
                nextText.innerText = 'Next Step';
                if(nextIcon) nextIcon.setAttribute('data-lucide', 'chevron-right');
            } else if (step === 2) {
                btnBack.classList.remove('hidden');
                btnPrint?.classList.add('hidden');
                backText.innerText = 'Back to edit';
                btnNext.onclick = () => ciProceedGoToStep(3);
                nextText.innerText = 'Review Order';
                if(nextIcon) nextIcon.setAttribute('data-lucide', 'eye');
            } else {
                btnBack.classList.remove('hidden');
                btnPrint?.classList.remove('hidden');
                backText.innerText = 'Back to edit';
                btnNext.onclick = () => toggleModal('ci-confirm-modal', true);
                nextText.innerText = 'Confirm Order';
                if(nextIcon) nextIcon.setAttribute('data-lucide', 'check-circle');
            }
            if (step === 3) populateCIReview();
            if (typeof lucide !== 'undefined') lucide.createIcons();
        };

        window.switchConsignmentTab = function(tab) {
            document.querySelectorAll('.ci-tab-content').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.consignment-tab').forEach(el => el.classList.remove('active'));
            document.getElementById('ci-tab-' + tab)?.classList.add('active');
            document.getElementById('ci-tab-content-' + tab)?.classList.remove('hidden');
            if (tab === 'history') loadCIHistory();
        };

        window.addCIInvoiceInput = function() {
            const container = document.getElementById('ci-invoice-inputs-container');
            const row = document.createElement('div');
            row.className = 'ci-invoice-row flex items-center space-x-3';
            row.innerHTML = `
                <div class="flex-1">
                    <p class="text-[9px] text-slate-400 font-bold mb-1 ml-1 uppercase">Invoice No.</p>
                    <input type="text" class="ci-invoice-input w-full px-3 py-2.5 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-maroon/20 outline-none transition-all" placeholder="Enter invoice number...">
                </div>
                <button onclick="this.closest('.ci-invoice-row').remove()" class="mt-5 p-2 bg-red-50 text-red-400 hover:bg-red-100 hover:text-red-600 rounded-lg transition-all">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            `;
            container.appendChild(row);
            if (typeof lucide !== 'undefined') lucide.createIcons();
        };

        window.openCIConsignmentProceed = function(id) {
            ciCurrentNoteId = id;
            toggleModal('ci-proceed-modal', true);
            ciProceedGoToStep(1);
            loadCINoteDetail(id);
        };

        window.openCIViewOrder = function(id) {
            toggleModal('ci-view-modal', true);
            loadCIOrderDetail(id);
        };

        window.switchCIViewSubTab = function(tabId, btn) {
            document.querySelectorAll('.ci-view-sub-content').forEach(el => el.classList.add('hidden'));
            document.getElementById(tabId)?.classList.remove('hidden');
            document.querySelectorAll('.ci-view-sub-tab').forEach(el => {
                el.classList.remove('active', 'bg-white', 'text-maroon', 'shadow-sm');
                el.classList.add('text-slate-500');
            });
            if (btn) { btn.classList.add('active', 'bg-white', 'text-maroon', 'shadow-sm'); btn.classList.remove('text-slate-500'); }
        };

        function loadCIDashboard() {
            fetch('{{ route("admin.consignment-invoice.dashboard") }}')
                .then(r => r.json())
                .then(res => {
                    if (!res.success) return;
                    document.getElementById('card-open-notes').textContent = res.open_notes ?? '0';
                    document.getElementById('card-invoiced').textContent = res.invoiced ?? '0';
                    document.getElementById('card-consignment-notes').textContent = res.consignment_notes ?? '0';
                }).catch(() => {});
        }

        function loadCIActiveNotes() {
            const tbody = document.getElementById('ci-active-notes-tbody');
            tbody.innerHTML = '<tr><td colspan="5" class="p-4 text-center text-slate-300 italic">Loading...</td></tr>';
            fetch('{{ route("admin.consignment-invoice.active") }}')
                .then(r => r.json())
                .then(res => {
                    if (!res.success || !res.notes.length) {
                        tbody.innerHTML = '<tr><td colspan="5" class="p-4 text-center text-slate-300 italic">No consignment notes found.</td></tr>';
                        return;
                    }
                    tbody.innerHTML = res.notes.map(n => `
                        <tr class="hover:bg-slate-50/50 transition-all">
                            <td class="p-4 px-6 text-xs font-mono font-bold text-slate-700">${n.sales_number || '---'}</td>
                            <td class="p-4 px-6 text-xs text-slate-600">${n.customer_name || '---'}</td>
                            <td class="p-4 px-6 text-xs text-slate-500">${n.order_date || '---'}</td>
                            <td class="p-4 px-6 text-xs font-bold text-right text-slate-700">₱${(n.net_total || 0).toLocaleString('en-US', {minimumFractionDigits:2})}</td>
                            <td class="p-4 px-6 text-center">
                                <button onclick="openCIConsignmentProceed(${n.id})" class="px-4 py-1.5 bg-maroon text-white rounded-lg text-[9px] font-bold hover:bg-maroon-800 transition-all shadow-sm uppercase tracking-widest flex items-center space-x-1.5 mx-auto">
                                    <i data-lucide="arrow-right-circle" class="w-3.5 h-3.5 text-gold"></i>
                                    <span>Proceed</span>
                                </button>
                            </td>
                        </tr>
                    `).join('');
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                }).catch(() => { tbody.innerHTML = '<tr><td colspan="5" class="p-4 text-center text-red-300 italic">Failed to load.</td></tr>'; });
        }

        function loadCIHistory() {
            const tbody = document.getElementById('ci-history-orders-tbody');
            tbody.innerHTML = '<tr><td colspan="8" class="p-4 text-center text-slate-300 italic">Loading...</td></tr>';
            fetch('{{ route("admin.consignment-invoice.history") }}')
                .then(r => r.json())
                .then(res => {
                    if (!res.success || !res.orders.length) {
                        tbody.innerHTML = '<tr><td colspan="8" class="p-4 text-center text-slate-300 italic">No history found.</td></tr>';
                        return;
                    }
                    tbody.innerHTML = res.orders.map(o => `
                        <tr class="hover:bg-slate-50/50 transition-all">
                            <td class="p-4 px-6 text-xs text-slate-500">${o.date_issue}</td>
                            <td class="p-4 px-6 text-xs font-mono font-bold text-slate-700">${o.invoice_no}</td>
                            <td class="p-4 px-6 text-xs text-slate-500">${o.waybill_no}</td>
                            <td class="p-4 px-6 text-xs text-slate-500">${o.waybill_date}</td>
                            <td class="p-4 px-6 text-xs text-slate-600">${o.customer_name}</td>
                            <td class="p-4 px-6 text-xs font-bold text-right text-slate-700">₱${(o.total_amount || 0).toLocaleString('en-US', {minimumFractionDigits:2})}</td>
                            <td class="p-4 px-6 text-xs text-slate-400 italic">${o.remarks}</td>
                            <td class="p-4 px-6 text-center">
                                <button onclick="openCIViewOrder(${o.id})" class="px-3 py-1.5 bg-maroon/10 text-maroon rounded-lg text-[9px] font-bold hover:bg-maroon hover:text-white transition-all uppercase tracking-widest flex items-center space-x-1.5 mx-auto">
                                    <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                    <span>View</span>
                                </button>
                            </td>
                        </tr>
                    `).join('');
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                }).catch(() => { tbody.innerHTML = '<tr><td colspan="8" class="p-4 text-center text-red-300 italic">Failed to load.</td></tr>'; });
        }

        function recalcCIRowSubtotal(row) {
            const checked = row.querySelector('.ci-item-checkbox')?.checked || false;
            const actualQty = parseFloat(row.querySelector('.ci-item-actual-qty')?.value) || 0;
            const price = parseFloat(row.querySelector('.ci-item-price')?.value) || 0;
            const disc = parseFloat(row.querySelector('.ci-item-disc')?.value) || 0;
            const addlDisc = parseFloat(document.getElementById('ci-addl-discount')?.value) || 0;
            const subtotal = actualQty * price * (1 - disc / 100) * (checked ? (1 - addlDisc / 100) : 1);
            row.querySelector('.ci-item-subtotal').value = subtotal.toFixed(2);
        }

        function loadCINoteDetail(id) {
            ciCurrentNoteId = id;
            document.getElementById('ci-p-sn-no').textContent = '---';
            document.getElementById('ci-p-customer-name').textContent = '---';
            document.getElementById('ci-p-issue-date').textContent = '---';
            document.getElementById('ci-p-grand-total').textContent = '---';
            document.getElementById('ci-p-net-total').textContent = '---';
            document.getElementById('ci-p-note-type').textContent = 'Sale\'s Order';
            document.getElementById('ci-p-rush-badge').classList.add('hidden');
            document.getElementById('ci-p-note-status').textContent = '---';
            document.getElementById('ci-invoice-inputs-container').innerHTML = `
                <div class="ci-invoice-row flex items-center space-x-3">
                    <div class="flex-1">
                        <p class="text-[9px] text-slate-400 font-bold mb-1 ml-1 uppercase">Invoice No.</p>
                        <input type="text" class="ci-invoice-input w-full px-3 py-2.5 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-maroon/20 outline-none transition-all" placeholder="Enter invoice number...">
                    </div>
                    <button onclick="this.closest('.ci-invoice-row').remove()" class="mt-5 p-2 bg-red-50 text-red-400 hover:bg-red-100 hover:text-red-600 rounded-lg transition-all">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
            `;
            const itemsTbody = document.getElementById('ci-proceed-items-tbody');
            itemsTbody.innerHTML = '<tr><td colspan="11" class="p-4 text-center text-slate-300 italic">Loading items...</td></tr>';

            fetch('{{ route("admin.consignment-invoice.note-detail", ["id" => "__ID__"]) }}'.replace('__ID__', id))
                .then(r => r.json())
                .then(res => {
                    if (!res.success || !res.note) {
                        itemsTbody.innerHTML = '<tr><td colspan="11" class="p-4 text-center text-red-300 italic">Failed to load note.</td></tr>';
                        return;
                    }
                    ciNoteData = res.note;
                    document.getElementById('ci-p-sn-no').textContent = res.note.sales_number || '---';
                    document.getElementById('ci-p-customer-name').textContent = res.note.customer_name || '---';
                    document.getElementById('ci-p-issue-date').textContent = res.note.order_date || '---';
                    document.getElementById('ci-p-grand-total').textContent = '₱' + (res.note.gross_total || 0).toLocaleString('en-US', {minimumFractionDigits:2});
                    document.getElementById('ci-p-net-total').textContent = '₱' + (res.note.net_total || 0).toLocaleString('en-US', {minimumFractionDigits:2});
                    document.getElementById('ci-p-note-type').textContent = 'Sale\'s Order';
                    if (res.note.is_rush) {
                        document.getElementById('ci-p-rush-badge').classList.remove('hidden');
                    }
                    document.getElementById('ci-p-note-status').textContent = res.note.status || '---';

                    if (!res.note.items || !res.note.items.length) {
                        itemsTbody.innerHTML = '<tr><td colspan="11" class="p-4 text-center text-slate-300 italic">No items.</td></tr>';
                        return;
                    }
                    itemsTbody.innerHTML = res.note.items.map((item, idx) => `
                        <tr>
                            <td class="p-2 px-3 text-center"><input type="checkbox" class="ci-item-checkbox accent-maroon cursor-pointer" checked data-idx="${idx}"><input type="hidden" value="${item.product_id || ''}" class="ci-item-product-id"></td>
                            <td class="p-2 px-3"><input type="text" value="${(item.product_code||'').replace(/"/g,'&quot;')}" class="ci-item-code text-xs w-full px-2 py-1 border border-slate-200 rounded-lg focus:ring-2 focus:ring-maroon/20 outline-none font-bold text-maroon" data-idx="${idx}"></td>
                            <td class="p-2 px-3"><input type="text" value="${(item.description||'').replace(/"/g,'&quot;')}" class="ci-item-desc text-xs w-full px-2 py-1 border border-slate-200 rounded-lg focus:ring-2 focus:ring-maroon/20 outline-none text-slate-700" data-idx="${idx}"></td>
                            <td class="p-2 px-3"><input type="number" value="${item.quantity}" min="1" class="ci-item-qty text-xs w-16 px-2 py-1 border border-slate-200 rounded-lg focus:ring-2 focus:ring-maroon/20 outline-none text-center font-bold" data-idx="${idx}"></td>
                            <td class="p-2 px-3"><input type="number" value="${item.quantity}" min="1" class="ci-item-actual-qty text-xs w-16 px-2 py-1 border border-amber-200 rounded-lg focus:ring-2 focus:ring-amber-300 outline-none text-center font-bold bg-amber-50" data-idx="${idx}"></td>
                            <td class="p-2 px-3"><input type="number" value="${item.additional_qty || 0}" min="0" class="ci-item-add-qty text-xs w-16 px-2 py-1 border border-slate-200 rounded-lg focus:ring-2 focus:ring-maroon/20 outline-none text-center" data-idx="${idx}"></td>
                            <td class="p-2 px-3"><input type="text" value="${(item.oum||'').replace(/"/g,'&quot;')}" class="ci-item-oum text-xs w-16 px-2 py-1 border border-slate-200 rounded-lg focus:ring-2 focus:ring-maroon/20 outline-none text-center uppercase text-slate-500" data-idx="${idx}"></td>
                            <td class="p-2 px-3"><input type="number" value="${item.unit_price}" step="0.01" min="0" class="ci-item-price text-xs w-full px-2 py-1 border border-slate-200 rounded-lg focus:ring-2 focus:ring-maroon/20 outline-none text-right" data-idx="${idx}"></td>
                            <td class="p-2 px-3"><input type="number" value="${item.discount}" step="0.01" min="0" max="100" class="ci-item-disc text-xs w-14 px-2 py-1 border border-slate-200 rounded-lg focus:ring-2 focus:ring-maroon/20 outline-none text-center" data-idx="${idx}"></td>
                            <td class="p-2 px-3"><input type="text" value="${parseFloat(item.subtotal).toFixed(2)}" class="ci-item-subtotal text-xs w-full px-2 py-1 border border-slate-200 rounded-lg focus:ring-2 focus:ring-maroon/20 outline-none text-right font-bold text-maroon" data-idx="${idx}" readonly></td>
                            <td class="p-2 px-3"><input type="text" class="ci-item-particulars text-xs w-full px-2 py-1 border border-slate-200 rounded-lg focus:ring-2 focus:ring-maroon/20 outline-none" placeholder="Notes..." value="${(item.particulars||'').replace(/"/g,'&quot;')}" data-idx="${idx}"></td>
                        </tr>
                    `).join('');

                    itemsTbody.querySelectorAll('.ci-item-qty, .ci-item-actual-qty, .ci-item-price, .ci-item-disc, .ci-item-checkbox').forEach(el => {
                        el.addEventListener('input', function() { recalcCIRowSubtotal(this.closest('tr')); });
                        if (el.classList.contains('ci-item-checkbox')) {
                            el.addEventListener('change', function() { recalcCIRowSubtotal(this.closest('tr')); });
                        }
                    });
                    document.getElementById('ci-select-all-items')?.addEventListener('change', function() {
                        itemsTbody.querySelectorAll('.ci-item-checkbox').forEach(cb => cb.checked = this.checked);
                        itemsTbody.querySelectorAll('.ci-item-checkbox').forEach(cb => recalcCIRowSubtotal(cb.closest('tr')));
                    });
                    document.getElementById('ci-addl-discount')?.addEventListener('input', function() {
                        itemsTbody.querySelectorAll('.ci-item-actual-qty').forEach(el => recalcCIRowSubtotal(el.closest('tr')));
                    });
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                }).catch(() => { itemsTbody.innerHTML = '<tr><td colspan="11" class="p-4 text-center text-red-300 italic">Failed to load items.</td></tr>'; });
        }

        function populateCIReview() {
            const note = ciNoteData;
            if (!note) return;
            document.getElementById('ci-rev-sn-no').textContent = note.sales_number;
            document.getElementById('ci-rev-customer').textContent = note.customer_name;
            document.getElementById('ci-rev-date').textContent = note.order_date;
            document.getElementById('ci-rev-grand').textContent = '₱' + (note.gross_total || 0).toLocaleString('en-US', {minimumFractionDigits:2});
            document.getElementById('ci-rev-net').textContent = '₱' + (note.net_total || 0).toLocaleString('en-US', {minimumFractionDigits:2});
            document.getElementById('ci-rev-type').textContent = note.so_type || "Sale's Order";
            document.getElementById('ci-rev-status').textContent = note.status;
            const invoices = Array.from(document.querySelectorAll('.ci-invoice-input')).map(inp => inp.value).filter(v => v.trim());
            document.getElementById('ci-rev-invoices').textContent = invoices.length > 0 ? invoices.join(', ') : '---';
            const rushEl = document.getElementById('ci-rev-rush');
            if (note.is_rush) { rushEl.classList.remove('hidden'); rushEl.textContent = 'Rush'; }
            else { rushEl.classList.add('hidden'); }

            const rtbody = document.getElementById('ci-review-tbody');
            const sourceRows = document.querySelectorAll('#ci-proceed-items-tbody tr');
            let gross = 0, totalDiscAmt = 0, addlDiscAmt = 0;
            const addlDisc = parseFloat(document.getElementById('ci-addl-discount')?.value) || 0;

            if (sourceRows.length > 0 && sourceRows[0].cells.length > 1) {
                rtbody.innerHTML = Array.from(sourceRows).map(row => {
                    const checked = row.querySelector('.ci-item-checkbox')?.checked || false;
                    const code = row.querySelector('.ci-item-code')?.value || '';
                    const desc = row.querySelector('.ci-item-desc')?.value || '';
                    const qty = parseInt(row.querySelector('.ci-item-qty')?.value) || 0;
                    const actualQty = parseInt(row.querySelector('.ci-item-actual-qty')?.value) || 0;
                    const addQty = parseInt(row.querySelector('.ci-item-add-qty')?.value) || 0;
                    const unit = row.querySelector('.ci-item-oum')?.value || '';
                    const price = parseFloat(row.querySelector('.ci-item-price')?.value) || 0;
                    const disc = parseFloat(row.querySelector('.ci-item-disc')?.value) || 0;
                    const sub = parseFloat(row.querySelector('.ci-item-subtotal')?.value) || 0;
                    const particulars = row.querySelector('.ci-item-particulars')?.value || '';
                    const itemGross = price * actualQty;
                    const itemDiscAmt = itemGross * (disc / 100);
                    const itemBeforeAddl = itemGross - itemDiscAmt;
                    const itemAddlDisc = checked ? (itemBeforeAddl * (addlDisc / 100)) : 0;
                    gross += itemGross;
                    totalDiscAmt += itemDiscAmt;
                    addlDiscAmt += itemAddlDisc;
                    const badgeClass = actualQty !== qty ? 'text-amber-600 font-bold' : 'text-slate-700';
                    return `<tr><td class="p-4 text-center">${checked ? '✓' : ''}</td><td class="p-4 font-bold text-maroon">${code}</td><td class="p-4 text-slate-700">${desc}</td><td class="p-4 text-center">${qty}</td><td class="p-4 text-center ${badgeClass}">${actualQty}</td><td class="p-4 text-center text-amber-600 font-bold">+${addQty}</td><td class="p-4 text-center uppercase text-slate-500">${unit}</td><td class="p-4 text-right">₱ ${price.toLocaleString('en-US', {minimumFractionDigits: 2})}</td><td class="p-4 text-center text-slate-400">${disc}%</td><td class="p-4 text-right font-bold text-maroon">₱ ${sub.toLocaleString('en-US', {minimumFractionDigits: 2})}</td><td class="p-4 text-slate-500 italic text-[10px]">${particulars || '---'}</td></tr>`;
                }).join('');
                if (addlDisc > 0) {
                    const addlRow = document.createElement('tr');
                    addlRow.innerHTML = `<td colspan="9" class="p-4 text-right text-amber-600 font-bold text-xs">Additional Discount (${addlDisc}%):</td><td class="p-4 text-right font-bold text-amber-600 text-xs">-₱ ${addlDiscAmt.toLocaleString('en-US', {minimumFractionDigits: 2})}</td><td></td>`;
                    rtbody.appendChild(addlRow);
                }
            }
            const netAfterAll = gross - totalDiscAmt - addlDiscAmt;
            document.getElementById('ci-rev-gross').textContent = '₱ ' + gross.toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('ci-rev-discount').textContent = '₱ ' + totalDiscAmt.toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('ci-rev-net-total').textContent = '₱ ' + netAfterAll.toLocaleString('en-US', {minimumFractionDigits: 2});
        }

        window.finalizeConsignmentInvoice = function() {
            if (!ciCurrentNoteId || !ciNoteData) return;
            const invoices = Array.from(document.querySelectorAll('.ci-invoice-input')).map(inp => inp.value).filter(v => v.trim());
            const addlDisc = parseFloat(document.getElementById('ci-addl-discount')?.value) || 0;
            const items = Array.from(document.querySelectorAll('#ci-proceed-items-tbody tr')).filter(row => row.cells.length > 1).map(row => {
                const checked = row.querySelector('.ci-item-checkbox')?.checked || false;
                return {
                    product_code: row.querySelector('.ci-item-code')?.value || '',
                    description: row.querySelector('.ci-item-desc')?.value || '',
                    quantity: parseInt(row.querySelector('.ci-item-qty')?.value) || 0,
                    actual_qty: parseInt(row.querySelector('.ci-item-actual-qty')?.value) || 0,
                    additional_qty: parseInt(row.querySelector('.ci-item-add-qty')?.value) || 0,
                    oum: (row.querySelector('.ci-item-oum')?.value || '').trim(),
                    unit_price: parseFloat(row.querySelector('.ci-item-price')?.value) || 0,
                    discount: parseFloat(row.querySelector('.ci-item-disc')?.value) || 0,
                    additional_discount: checked ? addlDisc : 0,
                    subtotal: parseFloat(row.querySelector('.ci-item-subtotal')?.value) || 0,
                    particulars: row.querySelector('.ci-item-particulars')?.value || '',
                };
            });
            if (items.length === 0) { alert('No items to process.'); return; }

            fetch('{{ route("admin.consignment-invoice.proceed") }}', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''},
                body: JSON.stringify({ sales_note_id: ciCurrentNoteId, invoices, items })
            })
            .then(r => r.json())
            .then(res => {
                toggleModal('ci-confirm-modal', false);
                if (res.success) {
                    toggleModal('ci-proceed-modal', false);
                    setTimeout(() => toggleModal('ci-success-modal', true), 300);
                    loadCIDashboard();
                    loadCIActiveNotes();
                    loadCIHistory();
                } else {
                    alert('Error: ' + (res.message || 'Unknown error'));
                }
            })
            .catch(err => { alert('Request failed: ' + err.message); toggleModal('ci-confirm-modal', false); });
        };

        window.openCIPrintReceiptModal = function() {
            toggleModal('ci-print-receipt-modal', true);
        };

        window.submitCIPrintReceipt = function() {
            const printType = document.querySelector('input[name="ci-receipt-print-type"]:checked')?.value || 'order';
            const note = ciNoteData;
            if (!note) { alert('No order data available.'); return; }

            const addlDisc = parseFloat(document.getElementById('ci-addl-discount')?.value) || 0;
            const items = Array.from(document.querySelectorAll('#ci-proceed-items-tbody tr'))
                .filter(row => row.cells.length > 1)
                .map(row => {
                    const actualQty = parseInt(row.querySelector('.ci-item-actual-qty')?.value) || 0;
                    const price = parseFloat(row.querySelector('.ci-item-price')?.value) || 0;
                    const disc = parseFloat(row.querySelector('.ci-item-disc')?.value) || 0;
                    const printSubtotal = actualQty * price * (1 - disc / 100);
                    return {
                        product_code: row.querySelector('.ci-item-code')?.value || '',
                        description: row.querySelector('.ci-item-desc')?.value || '',
                        quantity: parseInt(row.querySelector('.ci-item-qty')?.value) || 0,
                        actual_qty: actualQty,
                        oum: (row.querySelector('.ci-item-oum')?.value || '').trim(),
                        unit_price: price,
                        discount: disc,
                        additional_discount: 0,
                        subtotal: printSubtotal,
                    };
                });

            const printSubtotals = items.reduce((sum, i) => sum + i.subtotal, 0);
            const grossTotal = printSubtotals;

            const addlDiscRate = parseFloat(document.getElementById('ci-addl-discount')?.value) || 0;
            let totalAddlDiscAmt = 0;
            document.querySelectorAll('#ci-proceed-items-tbody tr').forEach(row => {
                if (row.cells.length < 2) return;
                const checked = row.querySelector('.ci-item-checkbox')?.checked || false;
                if (!checked) return;
                const aq = parseInt(row.querySelector('.ci-item-actual-qty')?.value) || 0;
                const pr = parseFloat(row.querySelector('.ci-item-price')?.value) || 0;
                const dc = parseFloat(row.querySelector('.ci-item-disc')?.value) || 0;
                totalAddlDiscAmt += aq * pr * (1 - dc / 100) * (addlDiscRate / 100);
            });
            const netTotal = grossTotal - totalAddlDiscAmt;
            const date = document.getElementById('ci-rev-date')?.textContent || note.order_date || '';

            toggleModal('ci-print-receipt-modal', false);

            fetch('{{ route("admin.consignment-invoice.receipt-print") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept': 'text/html',
                },
                body: JSON.stringify({
                    print_type: printType,
                    customer_id: note.customer_id || '',
                    customer_name: note.customer_name || '',
                    date: date,
                    sales_number: note.sales_number || '',
                    gross_total: grossTotal.toString(),
                    net_total: netTotal.toString(),
                    items: JSON.stringify(items),
                    rush_text: note.is_rush ? 'RUSH' : '',
                    total_addl_discount: totalAddlDiscAmt.toString(),
                    addl_discount_rate: addlDiscRate.toString(),
                }),
            })
            .then(resp => resp.text())
            .then(html => {
                const blob = new Blob([html], { type: 'text/html' });
                const blobUrl = URL.createObjectURL(blob);
                const win = window.open(blobUrl, 'receipt_print');
                if (!win) { alert('Please allow popups for this site.'); return; }
                win.focus();
            })
            .catch(e => { console.error('Receipt print error:', e); alert('Error loading receipt. Check console for details.'); });
        };

        function loadCIOrderDetail(id) {
            document.getElementById('ci-view-h-date').textContent = '---';
            document.getElementById('ci-view-h-invoice').textContent = '---';
            document.getElementById('ci-view-h-waybill').textContent = '---';
            document.getElementById('ci-view-h-waybill-date').textContent = '---';
            document.getElementById('ci-view-h-name').textContent = '---';
            document.getElementById('ci-view-h-total').textContent = '---';
            document.getElementById('ci-view-h-remarks').textContent = '---';
            document.getElementById('ci-view-i-sn').textContent = '---';
            document.getElementById('ci-view-i-customer').textContent = '---';
            document.getElementById('ci-view-i-date').textContent = '---';
            document.getElementById('ci-view-i-invoices').textContent = '---';
            document.getElementById('ci-view-i-type').textContent = 'Sale\'s Order';
            document.getElementById('ci-view-i-status').textContent = '---';
            document.getElementById('ci-view-total').textContent = '₱ 0.00';
            document.getElementById('ci-view-count').textContent = '0 items';
            document.getElementById('ci-view-order-tbody').innerHTML = '<tr><td colspan="10" class="p-4 text-center text-slate-300 italic">Loading...</td></tr>';

            fetch('{{ route("admin.consignment-invoice.detail", ["id" => "__ID__"]) }}'.replace('__ID__', id))
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        document.getElementById('ci-view-order-tbody').innerHTML = '<tr><td colspan="10" class="p-4 text-center text-red-300 italic">Failed to load.</td></tr>';
                        return;
                    }
                    const o = res.order;
                    const n = res.note;
                    document.getElementById('ci-view-label').textContent = 'Transaction: ' + (o.order_number || '---');
                    document.getElementById('ci-view-h-date').textContent = o.date_issue;
                    document.getElementById('ci-view-h-invoice').textContent = o.invoice_no;
                    document.getElementById('ci-view-h-waybill').textContent = o.waybill_no;
                    document.getElementById('ci-view-h-waybill-date').textContent = o.waybill_date;
                    document.getElementById('ci-view-h-name').textContent = o.customer_name;
                    document.getElementById('ci-view-h-total').textContent = '₱' + (o.total_amount || 0).toLocaleString('en-US', {minimumFractionDigits:2});
                    document.getElementById('ci-view-h-remarks').textContent = o.remarks;
                    if (n) {
                        document.getElementById('ci-view-i-sn').textContent = n.sales_number || '---';
                        document.getElementById('ci-view-i-customer').textContent = n.customer_name || '---';
                        document.getElementById('ci-view-i-date').textContent = n.order_date || '---';
                        document.getElementById('ci-view-i-invoices').textContent = o.invoice_numbers || '---';
                        document.getElementById('ci-view-i-type').textContent = 'Sale\'s Order';
                        document.getElementById('ci-view-i-status').textContent = n.status || '---';
                    }
                    if (o.items && o.items.length) {
                        const itemsHtml = o.items.map(item => `
                            <tr>
                                <td class="p-4 text-xs font-mono font-bold text-slate-700">${item.product_code}</td>
                                <td class="p-4 text-xs text-slate-600">${item.description || '---'}</td>
                                <td class="p-4 text-xs text-center text-slate-700">${item.quantity || 0}</td>
                                <td class="p-4 text-xs text-center ${(item.actual_qty || item.quantity) !== item.quantity ? 'text-amber-600 font-bold' : 'text-slate-700'}">${item.actual_qty || item.quantity}</td>
                                <td class="p-4 text-xs text-center text-amber-600 font-bold">${item.additional_qty || 0}</td>
                                <td class="p-4 text-xs text-center text-slate-500">${item.oum || 'PCS'}</td>
                                <td class="p-4 text-xs text-right text-slate-700">₱${(item.unit_price || 0).toLocaleString('en-US', {minimumFractionDigits:2})}</td>
                                <td class="p-4 text-xs text-center text-slate-500">${item.discount || 0}%</td>
                                <td class="p-4 text-xs text-right font-bold text-slate-700">₱${(item.subtotal || 0).toLocaleString('en-US', {minimumFractionDigits:2})}</td>
                                <td class="p-4 text-xs text-slate-400 italic">${item.particulars || ''}</td>
                            </tr>
                        `).join('');
                        document.getElementById('ci-view-order-tbody').innerHTML = itemsHtml;
                        document.getElementById('ci-view-count').textContent = o.items.length + ' items';
                        document.getElementById('ci-view-total').textContent = '₱' + (o.total_amount || 0).toLocaleString('en-US', {minimumFractionDigits:2});
                    } else {
                        document.getElementById('ci-view-order-tbody').innerHTML = '<tr><td colspan="10" class="p-4 text-center text-slate-300 italic">No items.</td></tr>';
                    }
                }).catch(() => { document.getElementById('ci-view-order-tbody').innerHTML = '<tr><td colspan="9" class="p-4 text-center text-red-300 italic">Failed to load.</td></tr>'; });
        }

        window.filterConsignmentTable = function() {
            const tbody = document.getElementById('ci-active-notes-tbody');
            if (!tbody) return;
            const sn = document.getElementById('ci-filter-sn')?.value.toLowerCase() || '';
            const customer = document.getElementById('ci-filter-customer')?.value.toLowerCase() || '';
            const date = document.getElementById('ci-filter-date')?.value.toLowerCase() || '';
            const total = document.getElementById('ci-filter-total')?.value.toLowerCase() || '';
            Array.from(tbody.getElementsByTagName('tr')).forEach(row => {
                if (row.cells.length < 4) return;
                const show = (!sn || (row.cells[0]?.textContent || '').toLowerCase().includes(sn)) &&
                    (!customer || (row.cells[1]?.textContent || '').toLowerCase().includes(customer)) &&
                    (!date || (row.cells[2]?.textContent || '').toLowerCase().includes(date)) &&
                    (!total || (row.cells[3]?.textContent || '').toLowerCase().includes(total));
                row.style.display = show ? '' : 'none';
            });
        };

        window.filterConsignmentHistoryTable = function() {
            const tbody = document.getElementById('ci-history-orders-tbody');
            if (!tbody) return;
            const date = document.getElementById('ci-h-filter-date')?.value.toLowerCase() || '';
            const invoice = document.getElementById('ci-h-filter-invoice')?.value.toLowerCase() || '';
            const waybill = document.getElementById('ci-h-filter-waybill')?.value.toLowerCase() || '';
            const wdate = document.getElementById('ci-h-filter-wdate')?.value.toLowerCase() || '';
            const name = document.getElementById('ci-h-filter-name')?.value.toLowerCase() || '';
            const amount = document.getElementById('ci-h-filter-amount')?.value.toLowerCase() || '';
            const remarks = document.getElementById('ci-h-filter-remarks')?.value.toLowerCase() || '';
            Array.from(tbody.getElementsByTagName('tr')).forEach(row => {
                if (row.cells.length < 7) return;
                const show = (!date || (row.cells[0]?.textContent || '').toLowerCase().includes(date)) &&
                    (!invoice || (row.cells[1]?.textContent || '').toLowerCase().includes(invoice)) &&
                    (!waybill || (row.cells[2]?.textContent || '').toLowerCase().includes(waybill)) &&
                    (!wdate || (row.cells[3]?.textContent || '').toLowerCase().includes(wdate)) &&
                    (!name || (row.cells[4]?.textContent || '').toLowerCase().includes(name)) &&
                    (!amount || (row.cells[5]?.textContent || '').toLowerCase().includes(amount)) &&
                    (!remarks || (row.cells[6]?.textContent || '').toLowerCase().includes(remarks));
                row.style.display = show ? '' : 'none';
            });
        };

        window.resetCIFilters = function() {
            document.getElementById('ci-filter-date-from').value = '';
            document.getElementById('ci-filter-date-to').value = '';
            document.getElementById('ci-filter-status').value = '';
            toggleModal('ci-filter-modal', false);
        };

        window.applyCIFilters = function() { toggleModal('ci-filter-modal', false); };

        window.filterTable = function(tbodyId, colIndex, value) {
            const tbody = document.getElementById(tbodyId);
            if (!tbody) return;
            const filter = value.toLowerCase();
            Array.from(tbody.getElementsByTagName('tr')).forEach(row => {
                const cell = row.getElementsByTagName('td')[colIndex];
                if (!cell) return;
                row.style.display = (cell.textContent || '').toLowerCase().includes(filter) ? '' : 'none';
            });
        };

        window.prevCIActivePage = function() {};
        window.nextCIActivePage = function() {};
        window.prevCIHistoryPage = function() {};
        window.nextCIHistoryPage = function() {};

        document.addEventListener('DOMContentLoaded', function() {
            loadCIDashboard();
            loadCIActiveNotes();
        });
    </script>
    <script src="{{ asset('js/Sales-Order.js') }}?v={{ time() }}"></script>
@endpush

@include('partials.admin.admin_sidebar_navbar')
