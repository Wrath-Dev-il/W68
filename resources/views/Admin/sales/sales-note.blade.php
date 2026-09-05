@push('styles')
    <link rel="stylesheet" href="{{ asset('css/Sales-Note.css') }}?v={{ time() }}">
@endpush

@section('sales_note_content')
<div id="page-sales-note-root" class="space-y-8 animate-fade-in text-slate-800">
    
    <!-- HEADER SECTION -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-maroon tracking-tight">Sales Note Management</h1>
            <p class="text-sm text-slate-500">Track and manage your sales orders and customer notes.</p>
        </div>
        <div class="flex items-center space-x-3">
            <button onclick="toggleModal('filter-modal', true)" class="px-4 py-2.5 bg-white border border-slate-200 hover:border-maroon-200 hover:bg-slate-50 text-slate-700 hover:text-maroon text-xs font-bold rounded-xl shadow-sm flex items-center space-x-2 transition-all">
                <i data-lucide="sliders-horizontal" class="w-4 h-4 text-slate-500"></i>
                <span>Filter</span>
            </button>
            <button onclick="generateNoteReport()" class="px-4 py-2.5 bg-white border border-slate-200 hover:border-maroon-200 hover:bg-slate-50 text-slate-700 hover:text-maroon text-xs font-bold rounded-xl shadow-sm flex items-center space-x-2 transition-all">
                <i data-lucide="file-text" class="w-4 h-4 text-slate-500"></i>
                <span>Generate Note Report</span>
            </button>
            <button onclick="toggleModal('add-sales-note-modal', true); goToStep(1);" class="px-5 py-2.5 bg-maroon text-white hover:bg-maroon-800 text-xs font-bold rounded-xl shadow-md flex items-center space-x-2 transition-all">
                <i data-lucide="plus-circle" class="w-4 h-4 text-gold"></i>
                <span>Add Sales Note</span>
            </button>
        </div>
    </div>

    <!-- DASHBOARD CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Sales Note -->
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between hover-card-trigger">
            <div class="space-y-1">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Sales Note</p>
                <h3 id="card-total-sales" class="text-2xl font-extrabold text-slate-800">---</h3>
            </div>
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-maroon shadow-inner">
                <i data-lucide="receipt" class="w-7 h-7 text-gold"></i>
            </div>
        </div>
        <!-- Total Open -->
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between hover-card-trigger">
            <div class="space-y-1">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Open</p>
                <h3 id="card-total-open" class="text-2xl font-extrabold text-slate-800">---</h3>
            </div>
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-maroon shadow-inner">
                <i data-lucide="folder-open" class="w-7 h-7 text-gold"></i>
            </div>
        </div>
        <!-- Total Partial -->
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between hover-card-trigger">
            <div class="space-y-1">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Partial</p>
                <h3 id="card-total-partial" class="text-2xl font-extrabold text-slate-800">---</h3>
            </div>
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-maroon shadow-inner">
                <i data-lucide="pie-chart" class="w-7 h-7 text-gold"></i>
            </div>
        </div>
        <!-- Total Closed -->
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between hover-card-trigger">
            <div class="space-y-1">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Closed</p>
                <h3 id="card-total-closed" class="text-2xl font-extrabold text-slate-800">---</h3>
            </div>
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-maroon shadow-inner">
                <i data-lucide="check-circle" class="w-7 h-7 text-gold"></i>
            </div>
        </div>
    </div>

    <!-- MAIN TABLE SECTION -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden text-slate-800">
        <div class="p-5 border-b border-slate-50 bg-slate-50/30">
            <div class="flex items-center space-x-4">
                <button onclick="switchSalesNoteTab('active')" id="sn-tab-active" class="px-5 py-2.5 bg-maroon text-white text-[10px] font-bold rounded-xl uppercase tracking-widest shadow-sm transition-all hover:bg-maroon-800">Sales Note List</button>
                <button onclick="switchSalesNoteTab('history')" id="sn-tab-history" class="px-5 py-2.5 bg-white border border-slate-200 text-slate-500 text-[10px] font-bold rounded-xl uppercase tracking-widest transition-all hover:border-maroon hover:text-maroon">Sales Note History</button>
            </div>
        </div>
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50/80 border-b border-slate-100">
                    <tr>
                        <th class="p-4 px-4 w-12"><input type="checkbox" onchange="toggleAllCheckboxes(this.checked)" class="w-4 h-4 rounded border-slate-300 text-maroon focus:ring-maroon cursor-pointer"></th>
                        <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Date Issue</th>
                        <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Trans No#</th>
                        <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Name</th>
                        <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Total Amount</th>
                        <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center text-nowrap">Sale's Man</th>
                        <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Status</th>
                        <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-nowrap">Checked By</th>
                        <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Remarks</th>
                        <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Action</th>
                    </tr>
                    <tr class="bg-slate-50/30 border-b border-slate-100">
                        <th class="p-2 px-4"></th>
                        <th class="p-2 px-5"><input type="text" id="col-search-date" placeholder="Search Date..." class="column-search-input"></th>
                        <th class="p-2 px-5"><input type="text" id="col-search-trans" placeholder="Search Trans..." class="column-search-input"></th>
                        <th class="p-2 px-5"><input type="text" id="col-search-name" placeholder="Search Name..." class="column-search-input"></th>
                        <th class="p-2 px-5"><input type="text" id="col-search-amount" placeholder="Search Amount..." class="column-search-input"></th>
                        <th class="p-2 px-5"><input type="text" id="col-search-salesman" placeholder="Search Salesman..." class="column-search-input"></th>
                        <th class="p-2 px-5"><input type="text" id="col-search-status" placeholder="Search Status..." class="column-search-input"></th>
                        <th class="p-2 px-5"><input type="text" id="col-search-checked" placeholder="Search Checked..." class="column-search-input"></th>
                        <th class="p-2 px-5"><input type="text" id="col-search-remarks" placeholder="Search Remarks..." class="column-search-input"></th>
                        <th class="p-2 px-5"></th>
                    </tr>
                </thead>
                <tbody id="main-sales-tbody" class="divide-y divide-slate-100 text-sm">
                    <tr><td colspan="10" class="p-4 text-center text-slate-300 italic">Loading sales notes...</td></tr>
                </tbody>
            </table>
        </div>

        <!-- PAGINATION SECTION -->
        <div class="p-4 border-t border-slate-100 bg-slate-50/30 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                Showing <span id="sn-page-from" class="text-maroon">0</span> to <span id="sn-page-to" class="text-maroon">0</span> of <span id="sn-page-total" class="text-maroon">0</span> entries
            </div>
            <div id="sn-pagination-container" class="flex items-center space-x-1">
                <!-- Pagination buttons will be inserted here -->
            </div>
        </div>
    </div>

    <!-- ==================== MODALS ==================== -->

    <!-- 1. ADD SALES NOTE MODAL (MULTI-STEP) -->
    <div id="add-sales-note-modal" class="fixed inset-0 z-[2000] hidden flex items-center justify-center" role="dialog" aria-modal="true">
        <div onclick="toggleModal('add-sales-note-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all w-[96vw] max-w-none modal-animate-in mx-2 flex flex-col max-h-[95vh]">
            
            <!-- Modal Header -->
            <div class="bg-maroon p-6 flex justify-between items-center text-white border-b-4 border-gold">
                <div class="flex items-center space-x-4">
                    <div class="p-3 bg-white/10 rounded-2xl border-2 border-gold shadow-sm">
                        <i data-lucide="receipt" class="w-6 h-6 text-gold"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold tracking-tight">Sales Note Processing</h3>
                        <p class="text-[10px] text-white/60 uppercase tracking-widest font-bold">Follow the steps to record sale</p>
                    </div>
                </div>

                <!-- Step Indicators -->
                <div class="flex items-center space-x-8 mr-12 relative">
                    <div class="absolute top-4 left-0 w-full h-0.5 bg-white/20 -z-0"></div>
                    <div id="step-progress-line" class="absolute top-4 left-0 h-0.5 bg-gold transition-all duration-500 -z-0" style="width: 0%;"></div>
                    
                    <div class="relative flex justify-between" style="width: 420px;">
                        <div class="flex flex-col items-center space-y-2">
                            <div id="step-indicator-1" class="step-indicator active">1</div>
                            <span class="text-[8px] font-bold uppercase tracking-widest text-white/70">Customer Info</span>
                        </div>
                        <div class="flex flex-col items-center space-y-2">
                            <div id="step-indicator-2" class="step-indicator pending">2</div>
                            <span class="text-[8px] font-bold uppercase tracking-widest text-white/40 step-label" data-step="2">Select Items</span>
                        </div>
                        <div class="flex flex-col items-center space-y-2">
                            <div id="step-indicator-3" class="step-indicator pending">3</div>
                            <span class="text-[8px] font-bold uppercase tracking-widest text-white/40 step-label" data-step="3">Review</span>
                        </div>
                        <div class="flex flex-col items-center space-y-2" data-step="4" style="display: none;">
                            <div id="step-indicator-4" class="step-indicator pending">4</div>
                            <span class="text-[8px] font-bold uppercase tracking-widest text-white/40 step-label" data-step="4">Review</span>
                        </div>
                    </div>
                </div>

                <button onclick="toggleModal('add-sales-note-modal', false)" class="text-white/70 hover:text-white transition-colors bg-white/10 p-2 rounded-xl">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>

            <!-- Modal Body (Scrollable) -->
            <div class="flex-1 overflow-y-auto custom-scrollbar p-8">
                
                <!-- STEP 1: CUSTOMER INFO -->
                <div id="sales-step-1" class="sales-step-content space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2 tracking-widest">Sale's Number</label>
                            <input type="text" id="new-sales-no" value="00000001" readonly class="w-full px-4 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-sm font-mono text-slate-500">
                        </div>
                        <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2 tracking-widest">Order Date</label>
                            <input type="date" id="new-sales-date" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm outline-none transition-all">
                        </div>
                        <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2 tracking-widest">S.O Type</label>
                            <select id="new-so-type" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm outline-none transition-all focus:ring-2 focus:ring-maroon/20 focus:border-maroon">
                                <option value="Sales Order">Sales Order</option>
                                <option value="Consignment">Consignment</option>
                            </select>
                        </div>
                        <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2 tracking-widest">Customer</label>
                            <div class="flex space-x-2">
                                <input type="text" id="new-customer-name" placeholder="Search customer..." readonly class="flex-1 px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm outline-none cursor-pointer hover:border-maroon transition-all" onclick="loadCustomers(1)">
                                <button onclick="loadCustomers()" class="px-4 py-2.5 bg-maroon text-white rounded-xl hover:bg-maroon-800 transition-all shadow-md">
                                    <i data-lucide="search" class="w-4 h-4 text-gold"></i>
                                </button>
                            </div>
                            <input type="hidden" id="new-customer-code">
                            <input type="hidden" id="new-customer-id">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2 tracking-widest">Salesman</label>
                            <input type="text" id="new-salesman" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm outline-none">
                        </div>
                        <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2 tracking-widest">Prepared By</label>
                            <input type="text" id="new-prepared-by" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm outline-none">
                        </div>
                        <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2 tracking-widest">Checked By</label>
                            <input type="text" id="new-checked-by" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm outline-none">
                        </div>
                        <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2 tracking-widest">Packed By</label>
                            <input type="text" id="new-packed-by" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm outline-none">
                        </div>
                    </div>

                    <div class="flex items-center space-x-4 p-5 bg-slate-50 rounded-2xl border border-slate-100">
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Is this a RUSH order?</span>
                        <div class="relative inline-block w-12 mr-2 align-middle select-none transition duration-200 ease-in">
                            <input type="checkbox" id="new-is-rush" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer outline-none transition-all duration-300 right-6 checked:right-0 checked:border-gold border-slate-300"/>
                            <label for="new-is-rush" class="toggle-label block overflow-hidden h-6 rounded-full bg-slate-300 cursor-pointer transition-all duration-300"></label>
                        </div>
                        <span class="text-[10px] font-bold text-slate-300 mx-2">|</span>
                        <button type="button" onclick="toggleOnlineMode()" class="flex items-center space-x-2 cursor-pointer select-none bg-transparent border-0">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Online</span>
                            <div class="relative pointer-events-none">
                                <div class="w-9 h-5 bg-slate-200 rounded-full transition-colors" id="online-toggle-track"></div>
                                <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform" id="online-toggle-thumb"></div>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- STEP 2: LINKED PARTIAL (Only shown if customer has partial notes) -->
                <div id="sales-step-2" class="sales-step-content hidden space-y-6">
                    <div class="p-6 bg-gradient-to-br from-amber-50 to-gold/10 rounded-2xl border border-amber-200">
                        <h4 class="text-sm font-bold text-slate-700 uppercase tracking-widest mb-2 flex items-center space-x-2">
                            <i data-lucide="link" class="w-4 h-4 text-amber-600"></i>
                            <span>Linked Partial Sales Notes</span>
                        </h4>
                        <p class="text-xs text-slate-500">This customer has partial sales notes with remaining items. Click a row to highlight it, then click "View" to see and convert items.</p>
                    </div>

                    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
                        <table class="w-full">
                            <thead class="bg-gradient-to-r from-maroon to-maroon-800 text-white">
                                <tr>
                                    <th class="p-3 px-4 text-left text-[10px] font-bold uppercase tracking-widest">Sales No#</th>
                                    <th class="p-3 px-4 text-center text-[10px] font-bold uppercase tracking-widest">Total Items</th>
                                    <th class="p-3 px-4 text-right text-[10px] font-bold uppercase tracking-widest">Remaining Amount</th>
                                    <th class="p-3 px-4 text-center text-[10px] font-bold uppercase tracking-widest">Action</th>
                                </tr>
                            </thead>
                            <tbody id="partial-notes-tbody" class="text-xs">
                                <tr><td colspan="4" class="p-4 text-center text-slate-400">Loading partial notes...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- STEP 3: SELECT ITEMS (formerly Step 2) -->
                <div id="sales-step-3" class="sales-step-content hidden space-y-6">
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="flex-1 relative">
                            <input type="text" onclick="loadProducts()" placeholder="Search and select items to add..." readonly class="w-full pl-12 pr-4 py-3 bg-white border border-slate-200 rounded-2xl text-sm cursor-pointer hover:border-maroon transition-all shadow-sm">
                            <i data-lucide="search" class="absolute left-4 top-3.5 w-5 h-5 text-slate-400"></i>
                        </div>
                        <button onclick="deleteSelectedItems()" class="px-4 py-3 bg-red-500 hover:bg-red-600 text-white text-xs font-bold rounded-xl shadow-sm transition-all flex items-center space-x-2">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                            <span>Delete Selected</span>
                        </button>
                    </div>

                    <div class="flex items-center space-x-3 mb-2">
                        <label class="text-[10px] font-bold text-slate-400 uppercase">Overall QTY</label>
                        <input type="number" id="overall-qty-input" oninput="applyOverallQty()" min="0" placeholder="Set QTY for selected items" class="w-48 px-3 py-1.5 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-maroon/20 focus:border-maroon outline-none transition-all">
                    </div>

                    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-slate-50 border-b border-slate-100">
                                    <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                                        <th class="p-4 px-6 w-12"><input type="checkbox" id="select-all-step2" class="rounded border-slate-300 text-maroon focus:ring-maroon"></th>
                                        <th class="p-4 px-6">Item Code</th>
                                        <th class="p-4 px-6">Description</th>
                                        <th class="p-4 px-6 text-center">QTY</th>
                                        <th class="p-4 px-6 text-center">UNIT</th>
                                        <th class="p-4 px-6 text-center">PRICE CODE</th>
                                        <th class="p-4 px-6 text-right">UNIT PRICE</th>
                                        <th class="p-4 px-6 text-right">SUB TOTAL</th>
                                        <th class="p-4 px-6 text-center w-52">Disc % / Bonus</th>
                                    </tr>
                                    <tr class="bg-slate-50/50 border-b border-slate-100">
                                        <th class="p-2 px-6"></th>
                                        <th class="p-2 px-6"><input type="text" onkeyup="filterTable('selected-items-tbody', 1, this.value)" placeholder="Code..." class="column-search-input"></th>
                                        <th class="p-2 px-6"><input type="text" onkeyup="filterTable('selected-items-tbody', 2, this.value)" placeholder="Desc..." class="column-search-input"></th>
                                        <th colspan="6"></th>
                                    </tr>
                                </thead>
                                <tbody id="selected-items-tbody" class="divide-y divide-slate-100 text-sm">
                                    <!-- Dynamic Rows Added via JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- STEP 4: DETAILS REVIEW (30/70 SPLIT) - formerly Step 3 -->
                <div id="sales-step-4" class="sales-step-content hidden h-full">
                    <div class="flex flex-col md:flex-row h-full gap-8">
                        <!-- Left Side (30% Customer Summary) -->
                        <div class="w-full md:w-[30%] space-y-4">
                            <div class="p-6 bg-maroon rounded-3xl shadow-xl text-white space-y-6 relative overflow-hidden">
                                <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 bg-gold/10 rounded-full blur-2xl"></div>
                                <h4 class="text-sm font-bold uppercase tracking-widest text-gold border-b border-white/10 pb-3">Customer Information</h4>
                            <div class="space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Sales No.</p>
                                        <p id="rev-sales-no" class="text-xs font-mono font-bold tracking-wider">---</p>
                                    </div>
                                    <div>
                                        <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Order Date</p>
                                        <p id="rev-sales-date" class="text-xs font-bold">---</p>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-[9px] uppercase text-white/50 font-bold mb-1">S.O Type</p>
                                    <p id="rev-so-type" class="text-xs font-bold text-gold">---</p>
                                </div>
                                    <div>
                                        <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Customer Name</p>
                                        <p id="rev-customer-name" class="text-sm font-bold text-gold">---</p>
                                    </div>
                                    <div>
                                        <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Salesman</p>
                                        <p id="rev-salesman" class="text-xs font-bold">---</p>
                                    </div>
                                    <div class="grid grid-cols-3 gap-2">
                                        <div>
                                            <p class="text-[8px] uppercase text-white/40 font-bold">Prep By</p>
                                            <p id="rev-prepared-by" class="text-[10px] font-bold">---</p>
                                        </div>
                                        <div>
                                            <p class="text-[8px] uppercase text-white/40 font-bold">Chk By</p>
                                            <p id="rev-checked-by" class="text-[10px] font-bold">---</p>
                                        </div>
                                        <div>
                                            <p class="text-[8px] uppercase text-white/40 font-bold">Pack By</p>
                                            <p id="rev-packed-by" class="text-[10px] font-bold">---</p>
                                        </div>
                                    </div>
                                    <div class="pt-2">
                                        <span id="rev-is-rush" class="px-3 py-1 bg-white/10 text-gold text-[9px] font-bold rounded-full uppercase tracking-widest">---</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Side (70% Items Table) -->
                        <div class="w-full md:w-[70%] space-y-6">
                            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                                <div class="p-4 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
                                    <h4 class="text-xs font-bold text-slate-800 uppercase tracking-widest">Selected Item table</h4>
                                    <span class="px-3 py-1 bg-maroon/10 text-maroon text-[9px] font-bold rounded-full uppercase">Review List</span>
                                </div>
                                <div class="overflow-x-auto max-h-[350px] custom-scrollbar">
                                    <table class="w-full text-left border-collapse">
                                        <thead class="sticky top-0 bg-slate-50 z-10">
                                            <tr class="text-[9px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100">
                                                <th class="p-4">Item Code</th>
                                                <th class="p-4">Description</th>
                                                <th class="p-4 text-center">QTY</th>
                                                <th class="p-4 text-center">Unit</th>
                                                <th class="p-4 text-center">Price Code</th>
                                                <th class="p-4 text-right">Unit Price</th>
                                                <th class="p-4 text-center">Disc %</th>
                                                <th class="p-4 text-center">Bonus</th>
                                                <th class="p-4 text-right">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody id="review-items-tbody" class="text-xs divide-y divide-slate-50">
                                            <!-- Dynamic Rows -->
                                        </tbody>
                                    </table>
                                </div>
                                <div class="p-6 bg-slate-50 border-t border-slate-100 flex justify-between items-center">
                                    <div class="flex items-center space-x-8">
                                        <div>
                                            <p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Gross Total</p>
                                            <h3 id="rev-gross-total" class="text-lg font-bold text-slate-600 tracking-tight">₱ 0.00</h3>
                                        </div>
                                        <div class="text-slate-300 text-xl font-light">-</div>
                                        <div>
                                            <p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Total Discount</p>
                                            <h3 id="rev-total-discount" class="text-lg font-bold text-emerald-600 tracking-tight">₱ 0.00</h3>
                                        </div>
                                        <div class="text-slate-300 text-xl font-light">=</div>
                                        <div>
                                            <p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Net Total (PHP)</p>
                                            <h3 id="rev-grand-total" class="text-2xl font-extrabold text-maroon tracking-tight">₱ 0.00</h3>
                                        </div>
                                    </div>
                                    <div class="flex space-x-3">
                                        <button onclick="goToStep(window._hasPartialNotes ? 3 : 2)" class="px-8 py-3 bg-white border border-slate-200 text-slate-600 rounded-2xl text-xs font-bold hover:bg-slate-50 transition-all uppercase tracking-widest">Edit</button>
                                        <button onclick="submitSalesNote()" class="px-10 py-3 bg-maroon text-white rounded-2xl text-xs font-bold hover:bg-maroon-800 transition-all shadow-xl shadow-maroon/20 flex items-center space-x-3 uppercase tracking-widest">
                                            <i data-lucide="check-circle" class="w-5 h-5 text-gold"></i>
                                            <span>Confirm Details</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Modal Footer (Fixed) -->
            <div class="p-6 bg-slate-50 border-t border-slate-100 flex justify-between items-center">
                <div id="footer-back-btn-container">
                    <button id="btn-back" onclick="goToStep(currentStep - 1)" class="hidden px-6 py-2.5 text-xs font-bold text-slate-400 hover:text-maroon transition-all uppercase tracking-widest flex items-center space-x-2">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        <span>Back</span>
                    </button>
                </div>
                <div class="flex space-x-3">
                    <button onclick="toggleModal('add-sales-note-modal', false)" class="px-6 py-2.5 text-xs font-bold text-slate-400 hover:text-slate-600 transition-all uppercase tracking-widest">Cancel</button>
                    <button id="btn-next" onclick="goToStep(2)" class="px-8 py-2.5 bg-maroon text-white rounded-xl text-xs font-bold hover:bg-maroon-800 transition-all shadow-lg flex items-center space-x-2 uppercase tracking-widest">
                        <span id="next-text">Next Step</span>
                        <i data-lucide="chevron-right" class="w-4 h-4 text-gold"></i>
                    </button>
                </div>
            </div>

        </div>
    </div>

    <!-- 1.2 EDIT SALES NOTE MODAL (MULTI-STEP) -->
    <div id="edit-sales-note-modal" class="fixed inset-0 z-[2000] hidden flex items-center justify-center" role="dialog" aria-modal="true">
        <div onclick="toggleModal('edit-sales-note-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all w-[96vw] max-w-none modal-animate-in mx-2 flex flex-col max-h-[95vh]">
            
            <!-- Modal Header -->
            <div class="bg-maroon p-6 flex justify-between items-center text-white border-b-4 border-gold">
                <div class="flex items-center space-x-4">
                    <div class="p-3 bg-white/10 rounded-2xl border-2 border-gold shadow-sm">
                        <i data-lucide="edit" class="w-6 h-6 text-gold"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold tracking-tight">Edit Sales Note</h3>
                        <p class="text-[10px] text-white/60 uppercase tracking-widest font-bold">Follow the steps to modify sale</p>
                    </div>
                </div>

                <!-- Step Indicators -->
                <div class="flex items-center space-x-8 mr-12 relative">
                    <div class="absolute top-4 left-0 w-full h-0.5 bg-white/20 -z-0"></div>
                    <div id="edit-step-progress-line" class="absolute top-4 left-0 h-0.5 bg-gold transition-all duration-500 -z-0" style="width: 0%;"></div>
                    
                    <div class="relative flex justify-between" style="width: 420px;">
                        <div class="flex flex-col items-center space-y-2">
                            <div id="edit-step-indicator-1" class="step-indicator active">1</div>
                            <span class="text-[8px] font-bold uppercase tracking-widest text-white/70">Customer Info</span>
                        </div>
                        <div class="flex flex-col items-center space-y-2">
                            <div id="edit-step-indicator-2" class="step-indicator pending">2</div>
                            <span class="text-[8px] font-bold uppercase tracking-widest text-white/40">Select Invoice</span>
                        </div>
                        <div class="flex flex-col items-center space-y-2">
                            <div id="edit-step-indicator-3" class="step-indicator pending">3</div>
                            <span class="text-[8px] font-bold uppercase tracking-widest text-white/40">Edit Items</span>
                        </div>
                        <div class="flex flex-col items-center space-y-2">
                            <div id="edit-step-indicator-4" class="step-indicator pending">4</div>
                            <span class="text-[8px] font-bold uppercase tracking-widest text-white/40">Review</span>
                        </div>
                    </div>
                </div>

                <button onclick="toggleModal('edit-sales-note-modal', false)" class="text-white/70 hover:text-white transition-colors bg-white/10 p-2 rounded-xl">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>

            <!-- Modal Body (Scrollable) -->
            <div class="flex-1 overflow-y-auto custom-scrollbar p-8">
                
                <!-- STEP 1: CUSTOMER INFO -->
                <div id="edit-sales-step-1" class="edit-sales-step-content space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2 tracking-widest">Sale's Number</label>
                            <input type="text" id="edit-sales-no" readonly class="w-full px-4 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-sm font-mono text-slate-500">
                        </div>
                        <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2 tracking-widest">Order Date</label>
                            <input type="date" id="edit-sales-date" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm outline-none transition-all">
                        </div>
                        <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2 tracking-widest">S.O Type</label>
                            <select id="edit-so-type" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm outline-none transition-all focus:ring-2 focus:ring-maroon/20 focus:border-maroon">
                                <option value="Sales Order">Sales Order</option>
                                <option value="Consignment">Consignment</option>
                            </select>
                        </div>
                        <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2 tracking-widest">Customer</label>
                            <div class="flex space-x-2">
                                <input type="text" id="edit-customer-name" placeholder="Search customer..." readonly class="flex-1 px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm outline-none cursor-pointer hover:border-maroon transition-all" onclick="loadCustomers(1)">
                                <button onclick="loadCustomers()" class="px-4 py-2.5 bg-maroon text-white rounded-xl hover:bg-maroon-800 transition-all shadow-md">
                                    <i data-lucide="search" class="w-4 h-4 text-gold"></i>
                                </button>
                            </div>
                            <input type="hidden" id="edit-customer-code">
                            <input type="hidden" id="edit-customer-id">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2 tracking-widest">Salesman</label>
                            <input type="text" id="edit-salesman" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm outline-none">
                        </div>
                        <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2 tracking-widest">Prepared By</label>
                            <input type="text" id="edit-prepared-by" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm outline-none">
                        </div>
                        <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2 tracking-widest">Checked By</label>
                            <input type="text" id="edit-checked-by" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm outline-none">
                        </div>
                        <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2 tracking-widest">Packed By</label>
                            <input type="text" id="edit-packed-by" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm outline-none">
                        </div>
                    </div>

                    <div class="flex items-center space-x-4 p-5 bg-slate-50 rounded-2xl border border-slate-100">
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Is this a RUSH order?</span>
                        <div class="relative inline-block w-12 mr-2 align-middle select-none transition duration-200 ease-in">
                            <input type="checkbox" id="edit-is-rush" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer outline-none transition-all duration-300 right-6 checked:right-0 checked:border-gold border-slate-300"/>
                            <label for="edit-is-rush" class="toggle-label block overflow-hidden h-6 rounded-full bg-slate-300 cursor-pointer transition-all duration-300"></label>
                        </div>
                        <span class="text-[10px] font-bold text-slate-300 mx-2">|</span>
                        <button type="button" onclick="toggleEditOnlineMode()" class="flex items-center space-x-2 cursor-pointer select-none bg-transparent border-0">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Online</span>
                            <div class="relative pointer-events-none">
                                <div class="w-9 h-5 bg-slate-200 rounded-full transition-colors" id="edit-online-toggle-track"></div>
                                <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform" id="edit-online-toggle-thumb"></div>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- STEP 2: SELECT INVOICE (NEW) -->
                <div id="edit-sales-step-2" class="edit-sales-step-content hidden space-y-6">
                    <div class="p-6 bg-gradient-to-br from-maroon/5 to-gold/5 rounded-2xl border border-maroon/10">
                        <h4 class="text-sm font-bold text-slate-700 uppercase tracking-widest mb-4 flex items-center space-x-2">
                            <i data-lucide="file-text" class="w-5 h-5 text-maroon"></i>
                            <span>Select Invoice to Edit</span>
                        </h4>
                        <p class="text-xs text-slate-500 mb-6">This sales note has multiple invoices. Please select the invoice you want to edit.</p>
                        
                        <div id="invoice-selection-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <!-- Invoice cards will be loaded here via JavaScript -->
                            <div class="col-span-full text-center py-8 text-slate-400 italic text-sm">
                                Loading invoices...
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 3: EDIT ITEMS (formerly Step 2) -->
                <div id="edit-sales-step-3" class="edit-sales-step-content hidden space-y-6">
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="flex-1 relative">
                            <input type="text" onclick="loadProducts()" placeholder="Search and select items to add..." readonly class="w-full pl-12 pr-4 py-3 bg-white border border-slate-200 rounded-2xl text-sm cursor-pointer hover:border-maroon transition-all shadow-sm">
                            <i data-lucide="search" class="absolute left-4 top-3.5 w-5 h-5 text-slate-400"></i>
                        </div>
                        <button onclick="deleteSelectedItems()" class="px-4 py-3 bg-red-500 hover:bg-red-600 text-white text-xs font-bold rounded-xl shadow-sm transition-all flex items-center space-x-2">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                            <span>Delete Selected</span>
                        </button>
                    </div>

                    <div class="flex items-center space-x-3 mb-2">
                        <label class="text-[10px] font-bold text-slate-400 uppercase">Overall QTY</label>
                        <input type="number" id="edit-overall-qty-input" oninput="applyOverallQty()" min="0" placeholder="Set QTY for selected items" class="w-48 px-3 py-1.5 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-maroon/20 focus:border-maroon outline-none transition-all">
                    </div>

                    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-slate-50 border-b border-slate-100">
                                    <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                                        <th class="p-4 px-6 w-12"><input type="checkbox" id="edit-select-all-step2" class="rounded border-slate-300 text-maroon focus:ring-maroon"></th>
                                        <th class="p-4 px-6">Item Code</th>
                                        <th class="p-4 px-6">Description</th>
                                        <th class="p-4 px-6 text-center">QTY</th>
                                        <th class="p-4 px-6 text-center edit-actual-qty-col">Actual QTY</th>
                                        <th class="p-4 px-6 text-center">UNIT</th>
                                        <th class="p-4 px-6 text-center">PRICE CODE</th>
                                        <th class="p-4 px-6 text-right">UNIT PRICE</th>
                                        <th class="p-4 px-6 text-right">SUB TOTAL</th>
                                        <th class="p-4 px-6 text-center w-100">Disc % / Bonus</th>
                                        <th class="p-4 px-6 text-center w-12">Action</th>
                                    </tr>
                                    <tr class="bg-slate-50/50 border-b border-slate-100">
                                        <th class="p-2 px-6"></th>
                                        <th class="p-2 px-6"><input type="text" onkeyup="filterTable('edit-selected-items-tbody', 1, this.value)" placeholder="Code..." class="column-search-input"></th>
                                        <th class="p-2 px-6"><input type="text" onkeyup="filterTable('edit-selected-items-tbody', 2, this.value)" placeholder="Desc..." class="column-search-input"></th>
                                        <th id="edit-items-filter-tail" colspan="8"></th>
                                    </tr>
                                </thead>
                                <tbody id="edit-selected-items-tbody" class="divide-y divide-slate-100 text-sm">
                                    <!-- Dynamic Rows Added via JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- STEP 4: DETAILS REVIEW (formerly Step 3 - 30/70 SPLIT) -->
                <div id="edit-sales-step-4" class="edit-sales-step-content hidden h-full">
                    <div class="flex flex-col md:flex-row h-full gap-8">
                        <!-- Left Side (30% Customer Summary) -->
                        <div class="w-full md:w-[30%] space-y-4">
                            <div class="p-6 bg-maroon rounded-3xl shadow-xl text-white space-y-6 relative overflow-hidden">
                                <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 bg-gold/10 rounded-full blur-2xl"></div>
                                <h4 class="text-sm font-bold uppercase tracking-widest text-gold border-b border-white/10 pb-3">Customer Information</h4>
                                <div class="space-y-4">
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Sales No.</p>
                                            <p id="edit-rev-sales-no" class="text-xs font-mono font-bold tracking-wider">---</p>
                                        </div>
                                        <div>
                                            <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Order Date</p>
                                            <p id="edit-rev-sales-date" class="text-xs font-bold">---</p>
                                        </div>
                                    </div>
                                    <div>
                                        <p class="text-[9px] uppercase text-white/50 font-bold mb-1">S.O Type</p>
                                        <p id="edit-rev-so-type" class="text-xs font-bold text-gold">---</p>
                                    </div>
                                    <div>
                                        <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Customer Name</p>
                                        <p id="edit-rev-customer-name" class="text-sm font-bold text-gold">---</p>
                                    </div>
                                    <div>
                                        <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Salesman</p>
                                        <p id="edit-rev-salesman" class="text-xs font-bold">---</p>
                                    </div>
                                    <div class="grid grid-cols-3 gap-2">
                                        <div>
                                            <p class="text-[8px] uppercase text-white/40 font-bold">Prep By</p>
                                            <p id="edit-rev-prepared-by" class="text-[10px] font-bold">---</p>
                                        </div>
                                        <div>
                                            <p class="text-[8px] uppercase text-white/40 font-bold">Chk By</p>
                                            <p id="edit-rev-checked-by" class="text-[10px] font-bold">---</p>
                                        </div>
                                        <div>
                                            <p class="text-[8px] uppercase text-white/40 font-bold">Pack By</p>
                                            <p id="edit-rev-packed-by" class="text-[10px] font-bold">---</p>
                                        </div>
                                    </div>
                                    <div class="pt-2">
                                        <span id="edit-rev-is-rush" class="px-3 py-1 bg-white/10 text-gold text-[9px] font-bold rounded-full uppercase tracking-widest">---</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Side (70% Items Table) -->
                        <div class="w-full md:w-[70%] space-y-6">
                            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                                <div class="p-4 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
                                    <h4 class="text-xs font-bold text-slate-800 uppercase tracking-widest">Selected Item table</h4>
                                    <span class="px-3 py-1 bg-maroon/10 text-maroon text-[9px] font-bold rounded-full uppercase">Review List</span>
                                </div>
                                <div class="overflow-x-auto max-h-[350px] custom-scrollbar">
                                    <table class="w-full text-left border-collapse">
                                        <thead class="sticky top-0 bg-slate-50 z-10">
                                            <tr class="text-[9px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100">
                                                <th class="p-4">Item Code</th>
                                                <th class="p-4">Description</th>
                                                <th class="p-4 text-center">QTY</th>
                                                <th class="p-4 text-center edit-review-actual-qty-col">Actual QTY</th>
                                                <th class="p-4 text-center">Unit</th>
                                                <th class="p-4 text-center">Price Code</th>
                                                <th class="p-4 text-right">Unit Price</th>
                                                <th class="p-4 text-center">Disc %</th>
                                                <th class="p-4 text-center">Bonus</th>
                                                <th class="p-4 text-right">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody id="edit-review-items-tbody" class="text-xs divide-y divide-slate-50">
                                            <!-- Dynamic Rows -->
                                        </tbody>
                                    </table>
                                </div>
                                <div class="p-6 bg-slate-50 border-t border-slate-100 flex justify-between items-center">
                                    <div class="flex items-center space-x-8">
                                        <div>
                                            <p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Gross Total</p>
                                            <h3 id="edit-rev-gross-total" class="text-lg font-bold text-slate-600 tracking-tight">₱ 0.00</h3>
                                        </div>
                                        <div class="text-slate-300 text-xl font-light">-</div>
                                        <div>
                                            <p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Total Discount</p>
                                            <h3 id="edit-rev-total-discount" class="text-lg font-bold text-emerald-600 tracking-tight">₱ 0.00</h3>
                                        </div>
                                        <div class="text-slate-300 text-xl font-light">=</div>
                                        <div>
                                            <p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Net Total (PHP)</p>
                                            <h3 id="edit-rev-grand-total" class="text-2xl font-extrabold text-maroon tracking-tight">₱ 0.00</h3>
                                        </div>
                                    </div>
                                    <div class="flex space-x-3">
                                        <button onclick="goToEditStep(2)" class="px-8 py-3 bg-white border border-slate-200 text-slate-600 rounded-2xl text-xs font-bold hover:bg-slate-50 transition-all uppercase tracking-widest">Edit</button>
                                        <button onclick="submitEditSalesNote()" class="px-10 py-3 bg-maroon text-white rounded-2xl text-xs font-bold hover:bg-maroon-800 transition-all shadow-xl shadow-maroon/20 flex items-center space-x-3 uppercase tracking-widest">
                                            <i data-lucide="check-circle" class="w-5 h-5 text-gold"></i>
                                            <span>Confirm Details</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Modal Footer (Fixed) -->
            <div class="p-6 bg-slate-50 border-t border-slate-100 flex justify-between items-center">
                <div id="edit-footer-back-btn-container">
                    <button id="edit-btn-back" onclick="goToEditStep(editCurrentStep - 1)" class="hidden px-6 py-2.5 text-xs font-bold text-slate-400 hover:text-maroon transition-all uppercase tracking-widest flex items-center space-x-2">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        <span>Back</span>
                    </button>
                </div>
                <div class="flex space-x-3">
                    <button onclick="toggleModal('edit-sales-note-modal', false)" class="px-6 py-2.5 text-xs font-bold text-slate-400 hover:text-slate-600 transition-all uppercase tracking-widest">Cancel</button>
                    <button id="edit-btn-next" onclick="goToEditStep(2)" class="px-8 py-2.5 bg-maroon text-white rounded-xl text-xs font-bold hover:bg-maroon-800 transition-all shadow-lg flex items-center space-x-2 uppercase tracking-widest">
                        <span id="edit-next-text">Select Invoice</span>
                        <i data-lucide="chevron-right" class="w-4 h-4 text-gold"></i>
                    </button>
                </div>
            </div>

        </div>
    </div>

    <!-- EDIT CONFIRMATION MODAL -->
    <div id="edit-confirm-details-modal" class="fixed inset-0 z-[2020] hidden flex items-center justify-center" role="dialog" aria-modal="true">
        <div onclick="toggleModal('edit-confirm-details-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-md sm:w-full modal-animate-in mx-4">
            <div class="bg-maroon p-5 flex items-center space-x-3 text-white border-b-2 border-gold">
                <div class="p-2 bg-white/10 rounded-xl">
                    <i data-lucide="check-circle" class="w-5 h-5 text-gold"></i>
                </div>
                <h3 class="text-base font-bold uppercase tracking-widest">Confirm Sales Note Changes</h3>
            </div>
            <div class="p-6">
                <div id="edit-confirm-summary" class="space-y-3">
                    <div class="flex justify-between items-center p-3 bg-emerald-50 rounded-xl">
                        <span class="text-xs font-bold text-emerald-700 uppercase tracking-wider">Items to Add</span>
                        <span id="edit-confirm-add-count" class="text-sm font-extrabold text-emerald-600">0</span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-blue-50 rounded-xl">
                        <span class="text-xs font-bold text-blue-700 uppercase tracking-wider">Items to Update</span>
                        <span id="edit-confirm-update-count" class="text-sm font-extrabold text-blue-600">0</span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-red-50 rounded-xl">
                        <span class="text-xs font-bold text-red-700 uppercase tracking-wider">Items to Remove</span>
                        <span id="edit-confirm-delete-count" class="text-sm font-extrabold text-red-600">0</span>
                    </div>
                    <div class="border-t border-slate-200 pt-3 mt-3">
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-bold text-slate-600 uppercase tracking-wider">New Total Amount</span>
                            <span id="edit-confirm-total" class="text-lg font-extrabold text-maroon">₱ 0.00</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-slate-50 px-6 py-4 flex flex-col sm:flex-row-reverse gap-2">
                <button id="edit-confirm-finalize-btn" onclick="finalizeEditSalesNote()" class="w-full px-4 py-2 bg-maroon hover:bg-maroon-800 text-white text-xs font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg shadow-maroon/20 flex items-center justify-center space-x-2">
                    <i data-lucide="check" class="w-4 h-4 text-gold"></i>
                    <span>Confirm</span>
                </button>
                <button onclick="toggleModal('edit-confirm-details-modal', false)" class="w-full px-4 py-2 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest">Cancel</button>
            </div>
        </div>
    </div>

    <!-- 1.1 ITEM LIST MODAL -->
    <div id="item-list-modal" class="fixed inset-0 z-[2010] hidden flex items-center justify-center" role="dialog" aria-modal="true">
        <div onclick="toggleModal('item-list-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all max-w-5xl w-full modal-animate-in mx-4">
            <div class="bg-maroon p-5 flex justify-between items-center text-white border-b-2 border-gold">
                <div class="flex items-center space-x-3">
                    <i data-lucide="package" class="w-5 h-5 text-gold"></i>
                    <h3 class="text-sm font-bold uppercase tracking-widest">Item List (Select Item)</h3>
                </div>
                <button onclick="toggleModal('item-list-modal', false)" class="text-white/70 hover:text-white transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div class="p-6">
                <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto custom-scrollbar max-h-[500px]">
                        <table class="w-full text-left border-collapse">
                            <thead class="sticky top-0 bg-slate-50 border-b border-slate-100 z-10">
                                <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                                    <th class="p-3 px-4 w-12"><input type="checkbox" id="select-all-items" onchange="toggleAllItemCheckboxes(this.checked)" class="rounded border-slate-300 text-maroon focus:ring-maroon"></th>
                                    <th class="p-3 px-4">Item Code</th>
                                    <th class="p-3 px-4">Description</th>
                                    <th class="p-3 px-4">PART NO#</th>
                                    <th class="p-3 px-4">APPLICATION</th>
                                    <th class="p-3 px-4 text-center">Unit</th>
                                    <th class="p-3 px-4 text-right">Price</th>
                                    <th class="p-3 px-4 text-center">On Hand</th>
                                </tr>
                                <tr class="bg-slate-50/50">
                                    <th class="p-2 px-3"></th>
                                    <th class="p-2 px-3"><input type="text" oninput="filterItemTable()" placeholder="Code..." class="column-search-input" data-col="1"></th>
                                    <th class="p-2 px-3"><input type="text" oninput="filterItemTable()" placeholder="Desc..." class="column-search-input" data-col="2"></th>
                                    <th class="p-2 px-3"><input type="text" oninput="filterItemTable()" placeholder="Part..." class="column-search-input" data-col="3"></th>
                                    <th class="p-2 px-3"><input type="text" oninput="filterItemTable()" placeholder="App..." class="column-search-input" data-col="4"></th>
                                    <th class="p-2 px-3"><input type="text" oninput="filterItemTable()" placeholder="Unit..." class="column-search-input" data-col="5"></th>
                                    <th class="p-2 px-3"><input type="text" oninput="filterItemTable()" placeholder="Price..." class="column-search-input" data-col="6"></th>
                                    <th class="p-2 px-3"><input type="text" oninput="filterItemTable()" placeholder="Stock..." class="column-search-input" data-col="7"></th>
                                </tr>
                            </thead>
                            <tbody id="item-list-tbody" class="divide-y divide-slate-100 text-xs text-slate-700">
                                <tr><td colspan="8" class="p-4 text-center text-slate-300 italic">Search to load items.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="flex items-center justify-between mt-4">
                    <div class="flex items-center space-x-2">
                        <span id="item-page-info" class="text-[10px] text-slate-400 font-bold">Page 1 of 1</span>
                        <button onclick="prevItemPage()" id="item-prev-btn" class="px-3 py-1 bg-slate-100 text-slate-500 rounded-lg text-[10px] font-bold hover:bg-maroon hover:text-white transition-all disabled:opacity-30" disabled>&#8592; Prev</button>
                        <button onclick="nextItemPage()" id="item-next-btn" class="px-3 py-1 bg-slate-100 text-slate-500 rounded-lg text-[10px] font-bold hover:bg-maroon hover:text-white transition-all disabled:opacity-30" disabled>Next &#8594;</button>
                    </div>
                    <button onclick="addSelectedItems()" class="px-6 py-2 bg-maroon text-white rounded-xl text-xs font-bold hover:bg-maroon-800 transition-all shadow-md flex items-center space-x-2">
                        <i data-lucide="plus" class="w-4 h-4 text-gold"></i>
                        <span>Add Selected Items</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 1.2 CUSTOMER SEARCH MODAL -->
    <div id="customer-search-modal" class="fixed inset-0 z-[2010] hidden flex items-center justify-center" role="dialog" aria-modal="true">
        <div onclick="toggleModal('customer-search-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all max-w-5xl w-full modal-animate-in mx-4">
            <div class="bg-maroon p-5 flex justify-between items-center text-white border-b-2 border-gold">
                <div class="flex items-center space-x-3">
                    <i data-lucide="users" class="w-5 h-5 text-gold"></i>
                    <h3 class="text-sm font-bold uppercase tracking-widest">Customer List (Select Customer)</h3>
                </div>
                <button onclick="toggleModal('customer-search-modal', false)" class="text-white/70 hover:text-white transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div class="p-6">
                <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto custom-scrollbar max-h-[500px]">
                        <table class="w-full text-left border-collapse">
                            <thead class="sticky top-0 bg-slate-50 border-b border-slate-100 z-10">
                                <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                                    <th class="p-3 px-4">Customer Code</th>
                                    <th class="p-3 px-4">Name</th>
                                    <th class="p-3 px-4">Contact No#</th>
                                    <th class="p-3 px-4">Contact Person</th>
                                    <th class="p-3 px-4">Billing Address</th>
                                </tr>
                                <tr class="bg-slate-50/50">
                                    <th class="p-2 px-3"><input type="text" data-col="code" onkeyup="filterCustomerTable()" placeholder="Code..." class="column-search-input"></th>
                                    <th class="p-2 px-3"><input type="text" data-col="name" onkeyup="filterCustomerTable()" placeholder="Name..." class="column-search-input"></th>
                                    <th class="p-2 px-3"><input type="text" data-col="contact_number" onkeyup="filterCustomerTable()" placeholder="Contact..." class="column-search-input"></th>
                                    <th class="p-2 px-3"><input type="text" data-col="contact_person" onkeyup="filterCustomerTable()" placeholder="Person..." class="column-search-input"></th>
                                    <th class="p-2 px-3"><input type="text" data-col="address" onkeyup="filterCustomerTable()" placeholder="Address..." class="column-search-input"></th>
                                </tr>
                            </thead>
                            <tbody id="customer-list-tbody" class="divide-y divide-slate-100 text-xs text-slate-700">
                                <tr><td colspan="5" class="p-4 text-center text-slate-300 italic">Search to load customers.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="flex justify-between items-center mt-4 text-xs">
                    <button onclick="prevCustomerPage()" id="customer-prev-btn" class="px-3 py-1 bg-slate-100 text-slate-500 rounded-lg text-[10px] font-bold hover:bg-maroon hover:text-white transition-all disabled:opacity-30" disabled>&#8592; Prev</button>
                    <span id="customer-page-info" class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Page 1 of 1</span>
                    <button onclick="nextCustomerPage()" id="customer-next-btn" class="px-3 py-1 bg-slate-100 text-slate-500 rounded-lg text-[10px] font-bold hover:bg-maroon hover:text-white transition-all disabled:opacity-30" disabled>Next &#8594;</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 1.5 VIEW PARTIAL NOTE ITEMS MODAL -->
    <div id="view-partial-items-modal" class="fixed inset-0 z-[2010] hidden flex items-center justify-center" role="dialog" aria-modal="true">
        <div onclick="toggleModal('view-partial-items-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-5xl w-full modal-animate-in mx-4">
            <div class="bg-gradient-to-r from-amber-600 to-amber-700 p-6 flex justify-between items-center text-white border-b-4 border-amber-800">
                <div class="flex items-center space-x-4">
                    <div class="p-3 bg-white/10 rounded-2xl border-2 border-white/30 shadow-sm">
                        <i data-lucide="package" class="w-6 h-6 text-white"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold tracking-tight">Partial Sales Note Details</h3>
                        <p class="text-[10px] text-white/70 uppercase tracking-widest font-bold mt-1">
                            Sales No: <span id="partial-note-number" class="text-gold">---</span>
                        </p>
                    </div>
                </div>
                <button onclick="toggleModal('view-partial-items-modal', false)" class="text-white/70 hover:text-white transition-colors bg-white/10 p-2 rounded-xl">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>

            <div class="p-8 max-h-[70vh] overflow-y-auto custom-scrollbar">
                <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
                    <table class="w-full">
                        <thead class="bg-gradient-to-r from-maroon to-maroon-800 text-white">
                            <tr>
                                <th class="p-3 px-4 text-left text-[10px] font-bold uppercase tracking-widest">Product Code</th>
                                <th class="p-3 px-4 text-left text-[10px] font-bold uppercase tracking-widest">Description</th>
                                <th class="p-3 px-4 text-center text-[10px] font-bold uppercase tracking-widest">Note Qty</th>
                                <th class="p-3 px-4 text-center text-[10px] font-bold uppercase tracking-widest">Remaining Qty</th>
                                <th class="p-3 px-4 text-right text-[10px] font-bold uppercase tracking-widest">Remaining Amount</th>
                                <th class="p-3 px-4 text-center text-[10px] font-bold uppercase tracking-widest">Action</th>
                            </tr>
                        </thead>
                        <tbody id="partial-items-tbody" class="text-xs">
                            <tr><td colspan="5" class="p-4 text-center text-slate-400">Loading items...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="p-6 bg-slate-50 border-t border-slate-100 flex items-center justify-center space-x-3">
                <button onclick="toggleModal('view-partial-items-modal', false)" class="px-6 py-2.5 text-xs font-bold text-slate-400 hover:text-slate-600 transition-all uppercase tracking-widest">
                    Close
                </button>
                <button onclick="convertAllPartialItems()" class="px-6 py-2.5 bg-maroon hover:bg-maroon-800 text-white text-xs font-bold rounded-xl shadow-sm transition-all uppercase tracking-widest flex items-center space-x-2">
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    <span>Convert All Items</span>
                </button>
            </div>
        </div>
    </div>

    <!-- 2. CONFIRMATION MODAL -->
    <div id="confirm-details-modal" class="fixed inset-0 z-[2020] hidden flex items-center justify-center" role="dialog" aria-modal="true">
        <div onclick="toggleModal('confirm-details-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-sm sm:w-full modal-animate-in mx-4">
            <div class="p-6 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-amber-50 mb-4">
                    <i data-lucide="alert-triangle" class="h-6 w-6 text-amber-600"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-2 tracking-tight uppercase">Confirm Details</h3>
                <p class="text-xs text-slate-500 mb-4 leading-relaxed">Are you sure you want to finalize this Sales Note? This action will record the transaction in the system.</p>
            </div>
            <div class="bg-slate-50 px-6 py-4 flex flex-col sm:flex-row-reverse gap-2">
                <button onclick="finalizeSalesNote()" class="w-full px-4 py-2 bg-maroon hover:bg-maroon-800 text-white text-xs font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg shadow-maroon/20">Yes, Confirm</button>
                <button onclick="toggleModal('confirm-details-modal', false)" class="w-full px-4 py-2 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest">No, Review</button>
            </div>
        </div>
    </div>

    <!-- 3. SUCCESS MODAL -->
    <div id="success-modal" class="fixed inset-0 z-[2030] hidden flex items-center justify-center" role="dialog" aria-modal="true">
        <div onclick="toggleModal('success-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-sm sm:w-full modal-animate-in mx-4">
            <div class="p-8 text-center">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-emerald-50 mb-6">
                    <i data-lucide="check-circle" class="h-10 w-10 text-emerald-500"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-800 mb-2">Success!</h3>
                <p class="text-xs text-slate-500 leading-relaxed">The Sales Note has been successfully processed and recorded in the system.</p>
            </div>
            <div class="p-6 pt-0">
                <button onclick="toggleModal('success-modal', false); location.reload()" class="w-full px-4 py-3 bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg shadow-emerald-200">Great, Continue</button>
            </div>
        </div>
    </div>

    <!-- 3.5 VIEW SALES NOTE MODAL (Step 3 style) -->
    <div id="view-sales-modal" class="fixed inset-0 z-[2000] hidden flex items-center justify-center" role="dialog" aria-modal="true">
        <div onclick="toggleModal('view-sales-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all w-[96vw] max-w-none modal-animate-in mx-2 flex flex-col max-h-[95vh]">
            <!-- Modal Header -->
            <div class="bg-maroon p-6 flex justify-between items-center text-white border-b-4 border-gold">
                <div class="flex items-center space-x-4">
                    <div class="p-3 bg-white/10 rounded-2xl border-2 border-gold shadow-sm">
                        <i data-lucide="eye" class="w-6 h-6 text-gold"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold tracking-tight">Sales Note Details</h3>
                        <p id="view-transaction-label" class="text-[10px] text-white/60 uppercase tracking-widest font-bold">Transaction: ---</p>
                    </div>
                </div>
                <button onclick="toggleModal('view-sales-modal', false)" class="text-white/70 hover:text-white transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <!-- Body (30/70 Split) -->
            <div class="flex flex-col md:flex-row h-full gap-8 p-8 overflow-y-auto">
                <!-- Left Side (30% Customer Summary) -->
                <div class="w-full md:w-[30%] space-y-4">
                    <div class="p-6 bg-maroon rounded-3xl shadow-xl text-white space-y-6 relative overflow-hidden">
                        <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 bg-gold/10 rounded-full blur-2xl"></div>
                        <h4 class="text-sm font-bold uppercase tracking-widest text-gold border-b border-white/10 pb-3">Customer Information</h4>
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-[9px] uppercase text-white/50 font-bold mb-1">S.O. No.</p>
                                    <p id="view-sales-no" class="text-xs font-mono font-bold tracking-wider">---</p>
                                </div>
                                <div>
                                    <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Order Date</p>
                                    <p id="view-sales-date" class="text-xs font-bold">---</p>
                                </div>
                            </div>
                            <div>
                                <p class="text-[9px] uppercase text-white/50 font-bold mb-1">S.O Type</p>
                                <p id="view-so-type" class="text-xs font-bold text-gold">---</p>
                            </div>
                            <div>
                                <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Customer Name</p>
                                <p id="view-customer-name" class="text-sm font-bold text-gold">---</p>
                            </div>
                            <div>
                                <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Salesman</p>
                                <p id="view-salesman" class="text-xs font-bold">---</p>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <div>
                                    <p class="text-[8px] uppercase text-white/40 font-bold">Prep By</p>
                                    <p id="view-prepared-by" class="text-[10px] font-bold">---</p>
                                </div>
                                <div>
                                    <p class="text-[8px] uppercase text-white/40 font-bold">Chk By</p>
                                    <p id="view-checked-by" class="text-[10px] font-bold">---</p>
                                </div>
                                <div>
                                    <p class="text-[8px] uppercase text-white/40 font-bold">Pack By</p>
                                    <p id="view-packed-by" class="text-[10px] font-bold">---</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 pt-2">
                                <span id="view-is-rush" class="px-3 py-1 bg-white/10 text-gold text-[9px] font-bold rounded-full uppercase tracking-widest">---</span>
                                <span id="view-status" class="px-3 py-1 bg-white/10 text-white text-[9px] font-bold rounded-full uppercase tracking-widest">---</span>
                            </div>
                            <div>
                                <p class="text-[8px] uppercase text-white/40 font-bold mb-1">Remarks</p>
                                <p id="view-remarks" class="text-[10px] text-white/70 italic">---</p>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Right Side (70% Items Table) -->
                <div class="w-full md:w-[70%] space-y-6">
                    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                        <div class="p-4 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
                            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-widest">Sales Items</h4>
                            <span id="view-items-count" class="px-3 py-1 bg-maroon/10 text-maroon text-[9px] font-bold rounded-full uppercase">0 items</span>
                        </div>
                        <div class="overflow-x-auto max-h-[400px] custom-scrollbar">
                            <table class="w-full text-left border-collapse">
                                <thead class="sticky top-0 bg-slate-50 z-10">
                                    <tr class="text-[9px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100">
                                        <th class="p-4">Item Code</th>
                                        <th class="p-4">Description</th>
                                        <th class="p-4 text-center">QTY</th>
                                        <th class="p-4 text-center">On Hand</th>
                                        <th class="p-4 text-center">Unit</th>
                                        <th class="p-4 text-center">Price Code</th>
                                        <th class="p-4 text-right">Unit Price</th>
                                        <th class="p-4 text-center">Disc %</th>
                                        <th class="p-4 text-center">Bonus</th>
                                        <th class="p-4 text-right">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody id="view-items-tbody" class="text-xs divide-y divide-slate-50">
                                    <tr><td colspan="10" class="p-4 text-center text-slate-300 italic">No items to display.</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="p-6 bg-slate-50 border-t border-slate-100 flex justify-between items-center">
                            <div class="flex items-center space-x-8">
                                <div>
                                    <p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Gross Total</p>
                                    <h3 id="view-gross-total" class="text-lg font-bold text-slate-600 tracking-tight">₱ 0.00</h3>
                                </div>
                                <div class="text-slate-300 text-xl font-light">-</div>
                                <div>
                                    <p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Total Discount</p>
                                    <h3 id="view-total-discount" class="text-lg font-bold text-emerald-600 tracking-tight">₱ 0.00</h3>
                                </div>
                                <div class="text-slate-300 text-xl font-light">=</div>
                                <div>
                                    <p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Net Total (PHP)</p>
                                    <h3 id="view-grand-total" class="text-2xl font-extrabold text-maroon tracking-tight">₱ 0.00</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. FILTER MODAL -->
    <div id="filter-modal" class="fixed inset-0 z-[2000] hidden flex items-center justify-center" role="dialog" aria-modal="true">
        <div onclick="toggleModal('filter-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-md sm:w-full modal-animate-in mx-4">
            <div class="bg-maroon p-5 flex justify-between items-center text-white border-b-2 border-gold">
                <h3 class="text-sm font-bold uppercase tracking-widest flex items-center gap-2">
                    <i data-lucide="sliders-horizontal" class="w-4 h-4 text-gold"></i>
                    Filter Records
                </h3>
                <button onclick="toggleModal('filter-modal', false)" class="text-white/70 hover:text-white transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div class="p-6 space-y-6">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Filter by Date Range</label>
                    <div class="grid grid-cols-2 gap-3 text-slate-800">
                        <div>
                            <p class="text-[9px] text-slate-400 font-bold mb-1 ml-1 uppercase">From</p>
                            <input type="date" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-maroon/20 outline-none transition-all">
                        </div>
                        <div>
                            <p class="text-[9px] text-slate-400 font-bold mb-1 ml-1 uppercase">To</p>
                            <input type="date" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-maroon/20 outline-none transition-all">
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Filter by Status</label>
                    <select class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-maroon/20 outline-none transition-all">
                        <option value="">All Status</option>
                        <option value="OPEN">OPEN</option>
                        <option value="CLOSED">CLOSED</option>
                        <option value="PARTIAL">PARTIAL</option>
                        <option value="SURPLUS">SURPLUS</option>
                    </select>
                </div>
            </div>
            <div class="p-6 pt-0 flex space-x-3">
                <button onclick="toggleModal('filter-modal', false)" class="flex-1 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] font-bold rounded-xl transition-all uppercase tracking-widest">Reset</button>
                <button onclick="toggleModal('filter-modal', false)" class="flex-1 px-4 py-2.5 bg-maroon text-white hover:bg-maroon-800 text-[10px] font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg shadow-maroon/20">Apply Filters</button>
            </div>
        </div>
    </div>

</div>

<!-- NOTE REPORT MODAL -->
<div id="note-report-modal" class="fixed inset-0 z-[2030] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('note-report-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-5xl w-full modal-animate-in mx-4 flex flex-col max-h-[90vh]">
        <div class="bg-maroon p-5 flex justify-between items-center text-white border-b-4 border-gold">
            <div class="flex items-center space-x-4">
                <div class="p-3 bg-white/10 rounded-2xl border-2 border-gold shadow-sm">
                    <i data-lucide="file-text" class="w-6 h-6 text-gold"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold tracking-tight">Sales Note Report</h3>
                    <p id="note-report-subtitle" class="text-[10px] text-white/60 uppercase tracking-widest font-bold">Selected sales notes summary</p>
                </div>
            </div>
            <button onclick="toggleModal('note-report-modal', false)" class="text-white/70 hover:text-white transition-colors bg-white/10 p-2 rounded-xl">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <!-- Step 1: Setup -->
        <div id="report-setup-step" class="flex-1 overflow-y-auto custom-scrollbar p-8">
            <div class="max-w-lg mx-auto space-y-8">
                <!-- Report Mode -->
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-4">Report Mode</label>
                    <div class="grid grid-cols-2 gap-4">
                        <label class="cursor-pointer">
                            <input type="radio" name="report-type" value="note" checked class="hidden peer">
                            <div class="p-5 border-2 border-slate-200 rounded-2xl peer-checked:border-maroon peer-checked:bg-maroon/5 peer-checked:shadow-md transition-all text-center">
                                <i data-lucide="receipt" class="w-8 h-8 text-maroon mx-auto mb-2"></i>
                                <span class="block text-sm font-bold text-slate-700 peer-checked:text-maroon">NOTE</span>
                                <span class="text-[10px] text-slate-400">Print all items in selected note(s)</span>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="report-type" value="note-unserved" class="hidden peer">
                            <div class="p-5 border-2 border-slate-200 rounded-2xl peer-checked:border-maroon peer-checked:bg-maroon/5 peer-checked:shadow-md transition-all text-center">
                                <i data-lucide="badge-percent" class="w-8 h-8 text-maroon mx-auto mb-2"></i>
                                <span class="block text-sm font-bold text-slate-700 peer-checked:text-maroon">NOTE UNSERVED</span>
                                <span class="text-[10px] text-slate-400">Print remaining unserved items only</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Date Range -->
                <div class="p-6 bg-slate-50 rounded-2xl border border-slate-200 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">OUT Column Dates (Choose any 3 years)</label>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Date 1</label>
                                <input type="number" id="report-out-date1" value="2025" min="2000" max="3000" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon" placeholder="e.g. 2025">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Date 2</label>
                                <input type="number" id="report-out-date2" value="2026" min="2000" max="3000" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon" placeholder="e.g. 2026">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Date 3 (Optional)</label>
                                <select id="report-out-date3" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon bg-white">
                                    <option value="">None</option>
                                    <option value="2020">2020</option>
                                    <option value="2021">2021</option>
                                    <option value="2022">2022</option>
                                    <option value="2023">2023</option>
                                    <option value="2024">2024</option>
                                    <option value="2025">2025</option>
                                    <option value="2026">2026</option>
                                    <option value="2027">2027</option>
                                    <option value="2028">2028</option>
                                    <option value="2029">2029</option>
                                    <option value="2030">2030</option>
                                </select>
                            </div>
                        </div>
                        <p class="text-[10px] text-slate-400 mt-2">These years will appear as column headers in the OUT section (e.g., 25, 26, 27)</p>
                    </div>
                </div>

                <!-- Online Price Toggle -->
                <div class="p-6 bg-slate-50 rounded-2xl border border-slate-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-sm font-bold text-slate-700">Add Online Price?</h4>
                            <p class="text-[10px] text-slate-400">Input online prices for selected products</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="report-add-online-price" class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-maroon"></div>
                        </label>
                    </div>
                </div>

                <!-- Payment Type -->
                <div class="p-6 bg-slate-50 rounded-2xl border border-slate-200">
                    <div>
                        <h4 class="text-sm font-bold text-slate-700 mb-3">Payment Type</h4>
                        <div class="flex space-x-6">
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="radio" name="report-payment-type" value="none" checked class="accent-maroon">
                                <span class="text-sm font-bold text-slate-700">None</span>
                            </label>
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="radio" name="report-payment-type" value="cod" class="accent-maroon">
                                <span class="text-sm font-bold text-slate-700">COD</span>
                            </label>
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="radio" name="report-payment-type" value="cod-30" class="accent-maroon">
                                <span class="text-sm font-bold text-slate-700">COD 30 Days</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <button onclick="submitReportSetup()" class="w-full px-6 py-3.5 bg-maroon text-white text-xs font-bold rounded-xl shadow-lg shadow-maroon/20 hover:bg-maroon-800 transition-all uppercase tracking-widest">
                    <i data-lucide="arrow-right" class="w-4 h-4 inline mr-2"></i>
                    Submit
                </button>
            </div>
        </div>

        <!-- Step 2: Content -->
        <div id="report-content-step" class="flex-1 overflow-y-auto custom-scrollbar p-6 hidden">
            <!-- Confirmed Online Price Badge -->
            <div id="online-price-confirmed-badge" class="hidden mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center space-x-3">
                <i data-lucide="check-circle" class="w-5 h-5 text-emerald-500"></i>
                <span class="text-xs font-bold text-emerald-700">Online prices confirmed</span>
            </div>
            <div id="note-report-content" class="space-y-4">
                <p class="text-center text-slate-400 italic">Loading report...</p>
            </div>
        </div>

        <!-- Footer -->
        <div id="report-setup-footer" class="p-4 border-t border-slate-100 flex justify-end">
            <button onclick="toggleModal('note-report-modal', false)" class="px-4 py-2.5 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest">Cancel</button>
        </div>
        <div id="report-content-footer" class="p-4 border-t border-slate-100 flex justify-between items-center hidden">
            <button onclick="goToReportSetup()" class="px-4 py-2.5 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest flex items-center space-x-2">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                <span>Back</span>
            </button>
            <div class="flex space-x-3">
                <button onclick="toggleModal('note-report-modal', false)" class="px-4 py-2.5 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest">Close</button>
                <button onclick="printNoteReport()" class="px-4 py-2.5 bg-maroon text-white text-xs font-bold rounded-xl shadow-md hover:bg-maroon-800 transition-all uppercase tracking-widest flex items-center space-x-2">
                    <i data-lucide="printer" class="w-4 h-4"></i>
                    <span>Print</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- DELETE CONFIRMATION MODAL -->
<div id="delete-confirm-modal" class="fixed inset-0 z-[2040] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('delete-confirm-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-sm sm:w-full modal-animate-in mx-4">
        <div class="p-8 text-center">
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-50 mb-6">
                <i data-lucide="alert-triangle" class="h-10 w-10 text-red-500"></i>
            </div>
            <h3 class="text-xl font-bold text-slate-800 mb-2">Delete Sales Note?</h3>
            <p id="delete-note-info" class="text-sm font-bold text-maroon mb-2"></p>
            <p class="text-xs text-slate-500 leading-relaxed">Deleting this Sales Note will also archive all related Sales Orders, Sales Order Items, Sales Returns, and Return Items. These can be restored later from the Archived page. Continue?</p>
            <input type="hidden" id="delete-note-id">
        </div>
        <div class="p-6 pt-0 flex space-x-3">
            <button onclick="toggleModal('delete-confirm-modal', false)" class="flex-1 px-4 py-2.5 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest">Cancel</button>
            <button onclick="confirmDeleteNote()" class="flex-1 px-4 py-2.5 bg-red-500 hover:bg-red-600 text-white text-xs font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg shadow-red-200">Delete</button>
        </div>
    </div>
</div>

<!-- DELETE SUCCESS MODAL -->
<div id="delete-success-modal" class="fixed inset-0 z-[2050] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('delete-success-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-sm sm:w-full modal-animate-in mx-4">
        <div class="p-8 text-center">
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-emerald-50 mb-6">
                <i data-lucide="check-circle" class="h-10 w-10 text-emerald-500"></i>
            </div>
            <h3 class="text-xl font-bold text-slate-800 mb-2">Deleted!</h3>
            <p class="text-xs text-slate-500 leading-relaxed">The sales note has been deleted successfully.</p>
        </div>
        <div class="p-6 pt-0">
            <button onclick="toggleModal('delete-success-modal', false); location.reload()" class="w-full px-4 py-3 bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg shadow-emerald-200">Great, Continue</button>
        </div>
    </div>
</div>

<!-- DELETE SELECTED ITEMS CONFIRMATION MODAL -->
<div id="delete-items-modal" class="fixed inset-0 z-[2060] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('delete-items-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-sm sm:w-full modal-animate-in mx-4">
        <div class="p-8 text-center">
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-50 mb-6">
                <i data-lucide="trash-2" class="h-10 w-10 text-red-500"></i>
            </div>
            <h3 class="text-xl font-bold text-slate-800 mb-2">Delete Item(s)</h3>
            <p id="delete-items-count" class="text-xs text-slate-500 leading-relaxed">Are you sure you want to delete 1 selected item(s)?</p>
        </div>
        <div class="p-6 pt-0 flex space-x-3">
            <button onclick="toggleModal('delete-items-modal', false)" class="flex-1 px-4 py-2.5 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest">Cancel</button>
            <button onclick="confirmDeleteItems()" class="flex-1 px-4 py-2.5 bg-red-500 hover:bg-red-600 text-white text-xs font-bold rounded-xl shadow-md transition-all uppercase tracking-widest">Delete</button>
        </div>
    </div>
</div>

<!-- PASSWORD MODAL (for deleting existing edit items) -->
<div id="edit-password-modal" class="fixed inset-0 z-[2080] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('edit-password-modal', false);" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-sm sm:w-full modal-animate-in mx-4">
        <div class="p-6 text-center">
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-2xl bg-red-500 mb-4">
                <i data-lucide="lock" class="h-8 w-8 text-white"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-800 mb-2">Confirm Deletion</h3>
            <p class="text-xs text-slate-500 leading-relaxed mb-4">This item has already been processed. Enter your password to delete it.</p>
            <div class="space-y-3 text-left">
                <div id="edit-password-error-msg" class="text-xs text-red-500 hidden mb-2">Incorrect password. Please try again.</div>
                <div id="edit-password-input-container"></div>
            </div>
        </div>
        <div class="bg-slate-50 px-6 py-4 flex flex-col sm:flex-row-reverse gap-2">
            <button onclick="verifyPasswordEditItemDelete()" class="w-full px-4 py-2 bg-red-500 hover:bg-red-600 text-white text-xs font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg shadow-red-200">Confirm</button>
            <button onclick="toggleModal('edit-password-modal', false); document.getElementById('edit-password-input-container').innerHTML=''" class="w-full px-4 py-2 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest">Cancel</button>
        </div>
    </div>
</div>

<!-- 4.5 STOCK WARNING MODAL -->
<div id="stock-warning-modal" class="fixed inset-0 z-[2070] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('stock-warning-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-4xl w-full modal-animate-in mx-4 flex flex-col max-h-[90vh]">
        <div class="bg-maroon p-6 flex justify-between items-center text-white border-b-4 border-gold">
            <div class="flex items-center space-x-4">
                <div class="p-3 bg-white/10 rounded-2xl border-2 border-gold shadow-sm">
                    <i data-lucide="alert-triangle" class="w-6 h-6 text-gold"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold tracking-tight">Stock is not enough</h3>
                    <p class="text-[10px] text-white/60 uppercase tracking-widest font-bold">
                        <span id="stock-warning-count">0</span> item(s) need adjustment
                    </p>
                </div>
            </div>
            <button onclick="toggleModal('stock-warning-modal', false)" class="text-white/70 hover:text-white transition-colors bg-white/10 p-2 rounded-xl">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <div class="p-6 overflow-auto">
            <p class="text-xs text-slate-500 mb-4 font-semibold">
                Adjust the QTY / Bonus or replace the item(s) below before confirming this sales note.
            </p>
            <div class="border border-slate-200 rounded-2xl overflow-hidden">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50">
                        <tr class="text-[10px] font-black uppercase tracking-widest text-slate-400 border-b border-slate-100">
                            <th class="p-3">Item Code</th>
                            <th class="p-3">Description</th>
                            <th class="p-3 text-center">Requested</th>
                            <th class="p-3 text-center">Available</th>
                            <th class="p-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody id="stock-warning-tbody" class="divide-y divide-slate-100">
                        <tr><td colspan="5" class="p-4 text-center text-slate-300 italic">No data.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="p-6 pt-0 flex space-x-3">
            <button id="stock-warning-close-btn" onclick="toggleModal('stock-warning-modal', false)" class="flex-1 px-4 py-2.5 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest">Close</button>
            <button id="stock-warning-goback-btn" onclick="goBackFromStockWarning()" class="hidden flex-1 px-4 py-2.5 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest">Go Back</button>
            <button id="stock-warning-proceed-btn" onclick="proceedLowStockAdd()" class="hidden flex-1 px-4 py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg shadow-amber-200">Proceed Anyway</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
    <script>
        window.salesNotePriceCodeEnabled = true;
        window.salesRoutes = {
            customersUrl: '{{ route("admin.sales-note.customers") }}',
            productsUrl: '{{ route("admin.sales-note.products") }}',
            processUrl: '{{ route("admin.sales-note.process") }}',
            stockCheckUrl: '{{ route("admin.sales-note.check-stock") }}',
            dashboardUrl: '{{ route("admin.sales-note.dashboard") }}',
            activeUrl: '{{ route("admin.sales-note.active") }}',
            historyUrl: '{{ route("admin.sales-note.history") }}',
            detailUrl: '{{ route("admin.sales-note.detail", ["id" => ":id"]) }}',
            deleteUrl: '{{ route("admin.sales-note.delete", ["id" => ":id"]) }}',
            updateUrl: '{{ route("admin.sales-note.update", ["id" => ":id"]) }}',
            reportUrl: '{{ route("admin.sales-note.report") }}',
            checkPasswordUrl: '/check-password',
            onlinePricesUrl: '{{ route("admin.sales-note.online-prices") }}',
            printUrl: '{{ route("admin.sales-note.print") }}',
            salesOrderUrl: '{{ route("admin.sales-order") }}',
            customerPartialsUrl: '{{ route("admin.sales-note.customer.partials", ["customerId" => ":customerId"]) }}',
            remainingItemsUrl: '{{ route("admin.sales-note.remaining-items", ["noteId" => ":noteId"]) }}'
        };
        window.csrfToken = '{{ csrf_token() }}';
        window.reportParams = {
            ids: '{{ $report_ids ?? "" }}',
            type: '{{ $report_type ?? "note" }}',
            outDate1: '{{ $report_out_date1 ?? date("Y") }}',
            outDate2: '{{ $report_out_date2 ?? date("Y") + 1 }}',
            outDate3: '{{ $report_out_date3 ?? "" }}',
            onlinePrices: '{{ $online_prices_confirmed ?? false }}' === '1'
        };

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof loadSalesDashboard === 'function') loadSalesDashboard();
            if (typeof loadSalesActive === 'function') loadSalesActive();
            @if(isset($online_prices_confirmed) && $online_prices_confirmed)
            if (window.reportParams.ids) {
                setTimeout(function() {
                    openReportAfterOnlinePrices(window.reportParams.ids, window.reportParams.type, window.reportParams.outDate1, window.reportParams.outDate2, window.reportParams.outDate3);
                }, 500);
            }
            @endif
        });
    </script>
    <script src="{{ asset('js/Sales-Note.js') }}?v={{ time() }}"></script>
    <script>
        // No range helpers needed - using free-choice date inputs
        
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize if needed
        });
    </script>
    <script>
        // Local Step Tracking
        let currentStep = 1;
        const originalGoToStep = window.goToStep;
        window.goToStep = function(step) {
            currentStep = step;
            originalGoToStep(step);
            
            const btnBack = document.getElementById('btn-back');
            const btnNext = document.getElementById('btn-next');
            const nextText = document.getElementById('next-text');
            const nextIcon = document.querySelector('#btn-next i');
            const totalSteps = window._totalSteps || 3;
            const reviewStep = totalSteps;

            if (step === 1) {
                btnBack.classList.add('hidden');
                btnNext.onclick = () => goToStep(2);
                nextText.innerText = 'Next Step';
                if(nextIcon) nextIcon.setAttribute('data-lucide', 'chevron-right');
            } else if (step < reviewStep) {
                btnBack.classList.remove('hidden');
                btnNext.onclick = () => goToStep(step + 1);
                if (step === reviewStep - 1) {
                    nextText.innerText = 'Review Sale';
                    if(nextIcon) nextIcon.setAttribute('data-lucide', 'eye');
                } else {
                    nextText.innerText = 'Next Step';
                    if(nextIcon) nextIcon.setAttribute('data-lucide', 'chevron-right');
                }
            } else {
                btnBack.classList.remove('hidden');
                btnNext.onclick = () => submitSalesNote();
                nextText.innerText = 'Confirm Sale';
                if(nextIcon) nextIcon.setAttribute('data-lucide', 'check-circle');
            }
            if (typeof lucide !== 'undefined') lucide.createIcons();
        };

        // Edit Modal Step Tracking
        let editCurrentStep = 1;
        window.activeEditNoteId = null;
        window._editOnlineMode = false;

        window.goToEditStep = function(step) {
            // Step 2: Load invoices when entering invoice selection
            if (step === 2) {
                loadInvoicesForEdit();
            }
            
            // Step 4: Validate stock before going to review
            if (step === 4 && !validateEditSelectedItemsStock(true)) {
                return;
            }

            document.querySelectorAll('.edit-sales-step-content').forEach(el => el.classList.add('hidden'));
            const target = document.getElementById(`edit-sales-step-${step}`);
            if (target) {
                target.classList.remove('hidden');
                updateEditStepIndicators(step);
                if (step === 4) populateEditReviewData();
            }

            editCurrentStep = step;
            
            const btnBack = document.getElementById('edit-btn-back');
            const btnNext = document.getElementById('edit-btn-next');
            const nextText = document.getElementById('edit-next-text');
            const nextIcon = document.querySelector('#edit-btn-next i');

            if (step === 1) {
                btnBack.classList.add('hidden');
                btnNext.onclick = () => goToEditStep(2);
                nextText.innerText = 'Select Invoice';
                if(nextIcon) nextIcon.setAttribute('data-lucide', 'chevron-right');
            } else if (step === 2) {
                btnBack.classList.remove('hidden');
                btnNext.classList.add('hidden'); // Hide next until invoice is selected
            } else if (step === 3) {
                btnBack.classList.remove('hidden');
                btnNext.classList.remove('hidden');
                btnNext.onclick = () => goToEditStep(4);
                nextText.innerText = 'Review Changes';
                if(nextIcon) nextIcon.setAttribute('data-lucide', 'eye');
            } else if (step === 4) {
                btnBack.classList.remove('hidden');
                btnNext.classList.remove('hidden');
                btnNext.onclick = () => submitEditSalesNote();
                nextText.innerText = 'Confirm Changes';
                if(nextIcon) nextIcon.setAttribute('data-lucide', 'check-circle');
            }
            if (typeof lucide !== 'undefined') lucide.createIcons();
        };

        function updateEditStepIndicators(currentStep) {
            const steps = [1, 2, 3, 4];
            const widths = ['0%', '33.33%', '66.66%', '100%'];
            steps.forEach(step => {
                const el = document.getElementById(`edit-step-indicator-${step}`);
                if (!el) return;
                el.classList.remove('active', 'completed', 'pending');
                if (step < currentStep) {
                    el.classList.add('completed');
                    el.innerHTML = '<i data-lucide="check" class="w-4 h-4 text-white"></i>';
                } else if (step === currentStep) {
                    el.classList.add('active');
                    el.innerText = step;
                } else {
                    el.classList.add('pending');
                    el.innerText = step;
                }
            });
            const line = document.getElementById('edit-step-progress-line');
            if (line) line.style.width = widths[currentStep - 1];
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }

        /**
         * Load all invoices for the current sales note (Step 2)
         * 3 scenarios: Open (no invoices), Partial (invoices + remaining items), Closed (only invoices)
         */
        window.loadInvoicesForEdit = async function() {
            const noteId = window.activeEditNoteId;
            if (!noteId) {
                console.error('No active edit note ID');
                return;
            }

            const grid = document.getElementById('invoice-selection-grid');
            if (!grid) return;

            grid.innerHTML = '<div class="col-span-full text-center py-8 text-slate-400 italic text-sm">Loading invoices...</div>';

            try {
                const url = `{{ url('/admin/sales/sales-note') }}/${noteId}/invoices`;
                const res = await fetch(url);
                const result = await res.json();

                if (!result.success) {
                    grid.innerHTML = '<div class="col-span-full text-center py-8 text-red-400 italic text-sm">Error loading data.</div>';
                    return;
                }

                const invoices = result.invoices || [];
                const remainingItems = result.remaining_items || [];
                const remainingCount = result.remaining_items_count || 0;
                const noteStatus = result.note?.status || '';
                let cardsHtml = '';

                if (invoices.length === 0) {
                    // Scenario 1: Open note - no invoices exist
                    cardsHtml = `
                        <div class="col-span-full text-center py-12 text-slate-400">
                            <div class="p-6 bg-white rounded-2xl border-2 border-dashed border-slate-200 max-w-md mx-auto">
                                <div class="p-4 bg-maroon/5 rounded-2xl inline-flex mb-4">
                                    <i data-lucide="file-text" class="w-10 h-10 text-maroon/40"></i>
                                </div>
                                <h5 class="text-sm font-bold text-slate-600 mb-2">No Invoices Found</h5>
                                <p class="text-xs text-slate-400 mb-6">This sales note has never been processed. You can edit the items directly.</p>
                                <button onclick="proceedEditWithoutInvoice()" 
                                        class="px-8 py-3 bg-maroon text-white text-xs font-bold rounded-2xl hover:bg-maroon-800 transition-all shadow-lg shadow-maroon/20 flex items-center space-x-2 mx-auto">
                                    <i data-lucide="edit" class="w-4 h-4 text-gold"></i>
                                    <span>Proceed to Edit Items</span>
                                </button>
                            </div>
                        </div>
                    `;
                } else {
                    // Render invoice cards
                    cardsHtml = invoices.map(invoice => `
                        <div class="invoice-card p-5 bg-white rounded-2xl border-2 border-slate-200 hover:border-maroon hover:shadow-lg transition-all cursor-pointer group"
                             onclick="selectInvoiceForEdit(${invoice.id}, '${(invoice.invoice_numbers || '').replace(/'/g, "\\'")}')">
                            <div class="flex items-start justify-between mb-3">
                                <div class="p-2.5 bg-maroon/10 rounded-xl group-hover:bg-maroon group-hover:shadow-lg transition-all">
                                    <i data-lucide="file-text" class="w-5 h-5 text-maroon group-hover:text-white"></i>
                                </div>
                                <span class="px-2.5 py-1 bg-slate-100 text-slate-600 text-[9px] font-bold rounded-full uppercase tracking-wider group-hover:bg-gold group-hover:text-white transition-all">
                                    ${invoice.status}
                                </span>
                            </div>
                            <h5 class="text-xs font-bold text-slate-800 mb-2 uppercase tracking-wide">Invoice #${invoice.invoice_numbers || ''}</h5>
                            <div class="space-y-1.5 text-[11px] text-slate-500">
                                <div class="flex justify-between">
                                    <span>Total Amount:</span>
                                    <span class="font-bold text-maroon">₱ ${parseFloat(invoice.total_amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2})}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Items:</span>
                                    <span class="font-bold text-slate-700">${invoice.item_count || 0}</span>
                                </div>
                            </div>
                            <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between">
                                <span class="text-[9px] text-slate-400 uppercase tracking-widest">Click to edit</span>
                                <i data-lucide="arrow-right" class="w-3.5 h-3.5 text-slate-300 group-hover:text-maroon group-hover:translate-x-1 transition-all"></i>
                            </div>
                        </div>
                    `).join('');

                    // Scenario 2: Show "Remaining Items" card for any status when items exist
                    if (remainingCount > 0) {
                        cardsHtml += `
                            <div class="invoice-card p-5 bg-gradient-to-br from-amber-50 to-gold/10 rounded-2xl border-2 border-dashed border-amber-300 hover:border-amber-500 hover:shadow-lg transition-all cursor-pointer group"
                                 onclick="proceedEditRemainingItems()">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="p-2.5 bg-amber-100 rounded-xl group-hover:bg-amber-500 group-hover:shadow-lg transition-all">
                                        <i data-lucide="package" class="w-5 h-5 text-amber-600 group-hover:text-white"></i>
                                    </div>
                                    <span class="px-2.5 py-1 bg-amber-100 text-amber-700 text-[9px] font-bold rounded-full uppercase tracking-wider">${remainingCount} items</span>
                                </div>
                                <h5 class="text-xs font-bold text-amber-800 mb-2 uppercase tracking-wide">Remaining Items</h5>
                                <p class="text-[11px] text-amber-600/70 mb-3">Edit the uninvoiced items from this sales note</p>
                                <div class="pt-3 border-t border-amber-200 flex items-center justify-between">
                                    <span class="text-[9px] text-amber-500 uppercase tracking-widest">Click to edit remaining</span>
                                    <i data-lucide="arrow-right" class="w-3.5 h-3.5 text-amber-400 group-hover:text-amber-600 group-hover:translate-x-1 transition-all"></i>
                                </div>
                            </div>
                        `;
                    }
                }

                grid.innerHTML = cardsHtml;
                if (typeof lucide !== 'undefined') lucide.createIcons();
            } catch (e) {
                console.error('Error loading invoices:', e);
                grid.innerHTML = '<div class="col-span-full text-center py-8 text-red-400 italic text-sm">Error loading invoices. Please try again.</div>';
            }
        };

        /**
         * Select an invoice and load its items (go to Step 3)
         */
        function getEditRowActualQty(row) {
            return parseFloat(row.querySelector('.item-actual-qty')?.value ?? row.getAttribute('data-actual-qty') ?? 0) || 0;
        }

        function renderEditActualQtyCell(item) {
            const actualQty = Number(item?.actual_qty ?? 0);
            return `
                <td class="p-4 px-6 text-center edit-actual-qty-col" data-actual-qty-cell="${actualQty}">
                    ${actualQty > 0 ? `<input type="number" value="${actualQty}" class="item-actual-qty w-20 px-3 py-1.5 border border-amber-200 rounded-lg text-center font-bold text-amber-700 bg-amber-50" readonly>` : ''}
                </td>
            `;
        }

        function syncEditActualQtyColumnVisibility() {
            const rows = Array.from(document.querySelectorAll('#edit-selected-items-tbody tr'));
            const shouldShow = rows.some(row => getEditRowActualQty(row) > 0);
            document.querySelectorAll('#edit-sales-step-3 .edit-actual-qty-col').forEach(el => {
                el.classList.toggle('hidden', !shouldShow);
            });
            const tail = document.getElementById('edit-items-filter-tail');
            if (tail) tail.colSpan = shouldShow ? 8 : 7;
        }

        window.selectInvoiceForEdit = async function(invoiceId, invoiceNumber) {
            window.activeEditInvoiceId = invoiceId;
            window.activeEditInvoiceNumber = invoiceNumber;
            window._editAddedProductCodes = new Set();
            window._editDeletedProductCodes = new Set();
            window._pendingDeletedItemIds = new Set();

            try {
                const url = `{{ url('/admin/sales/sales-note/invoice') }}/${invoiceId}/items`;
                const res = await fetch(url);
                const result = await res.json();

                if (result.success) {
                    // Clear and populate items in Step 3
                    const tbody = document.getElementById('edit-selected-items-tbody');
                    tbody.innerHTML = '';

                    window._originalItems = [];
                    if (result.items && result.items.length > 0) {
                        result.items.forEach(item => {
                            const salesNoteItemId = item.sales_note_item_id || '';
                            window._originalItems.push({ 
                                item_id: salesNoteItemId, 
                                qty: item.quantity, 
                                price: item.unit_price, 
                                price_code: item.price_code || '',
                                disc: item.discount || 0, 
                                bonus: item.additional_qty || 0 
                            });

                            const row = document.createElement('tr');
                            row.setAttribute('data-product-id', item.product_id || 0);
                            row.setAttribute('data-item-id', salesNoteItemId);
                            row.setAttribute('data-sales-order-item-id', item.sales_order_item_id || item.id || '');
                            row.setAttribute('data-actual-qty', Number(item.actual_qty ?? 0));
                            row.setAttribute('data-remaining-qty', Number(item.remaining_qty ?? Math.max(0, Number(item.quantity || 0) - Number(item.actual_qty || 0))));
                            row.dataset.onHand = String(Number(item.on_hand ?? 0));
                            row.className = "hover:bg-slate-50 transition-colors";
                            row.innerHTML = `
                                <td class="p-4 px-6"><input type="checkbox" checked class="rounded border-slate-300 text-maroon focus:ring-maroon"></td>
                                <td class="p-4 px-6 font-bold text-maroon product-code">${item.product_code}</td>
                                <td class="p-4 px-6 text-slate-700 product-desc">${item.description}</td>
                                <td class="p-4 px-6 text-center"><input type="number" value="${item.quantity}" min="0" oninput="updateEditSalesItemSubtotal(this)" onchange="updateEditSalesItemSubtotal(this)" class="item-qty w-20 px-3 py-1.5 border border-slate-200 rounded-lg text-center focus:ring-2 focus:ring-maroon/20 outline-none transition-all"></td>
                                ${renderEditActualQtyCell(item)}
                                <td class="p-4 px-6 text-center"><input type="text" value="${item.oum}" class="item-unit w-20 px-3 py-1.5 border border-slate-200 rounded-lg text-center focus:ring-2 focus:ring-maroon/20 outline-none transition-all"></td>
                                ${window.renderSalesNotePriceCodeCell ? window.renderSalesNotePriceCodeCell(item, 'updateEditSalesItemSubtotal(this)') : ''}
                                <td class="p-4 px-6 text-right"><input type="number" value="${item.unit_price}" oninput="updateEditSalesItemSubtotal(this)" onchange="updateEditSalesItemSubtotal(this)" class="item-price w-28 px-3 py-1.5 border border-slate-200 rounded-lg text-right focus:ring-2 focus:ring-maroon/20 outline-none transition-all"></td>
                                <td class="p-4 px-6 text-right"><input type="number" value="${item.subtotal}" class="item-subtotal w-32 px-3 py-1.5 border border-slate-200 rounded-lg text-right font-bold text-maroon bg-slate-50" readonly></td>
                                <td class="p-4 px-6"><div class="flex flex-col space-y-1"><input type="number" placeholder="Bonus" value="${item.additional_qty || ''}" oninput="updateEditSalesItemSubtotal(this)" onchange="updateEditSalesItemSubtotal(this)" class="bonus-input w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md"><input type="number" placeholder="Disc %" value="${item.discount || ''}" oninput="updateEditSalesItemSubtotal(this)" onchange="updateEditSalesItemSubtotal(this)" class="item-disc w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md"></div></td>
                                <td class="p-4 px-6 text-center"><button onclick="removeEditItemRow(this)" class="p-1.5 bg-red-50 text-red-500 hover:bg-red-600 hover:text-white rounded-lg transition-all" title="Remove item"><i data-lucide="x" class="w-3.5 h-3.5"></i></button></td>
                            `;
                            tbody.appendChild(row);
                        });
                    }

                    syncEditActualQtyColumnVisibility();
                    if (typeof lucide !== 'undefined') setTimeout(() => lucide.createIcons(), 50);

                    // Move to Step 3 (Edit Items)
                    goToEditStep(3);
                } else {
                    alert('Error loading invoice items: ' + (result.message || 'Unknown error'));
                }
            } catch (e) {
                console.error('Error selecting invoice:', e);
                alert('Error loading invoice items. Please try again.');
            }
        };

        /**
         * Load the note's own remaining items for editing (Partial note - uninvoiced items)
         */
        window.proceedEditRemainingItems = async function() {
            window.activeEditInvoiceId = null;
            window.activeEditInvoiceNumber = 'Remaining Items';
            window._editAddedProductCodes = new Set();
            window._editDeletedProductCodes = new Set();
            window._pendingDeletedItemIds = new Set();

            try {
                const url = `{{ url('/admin/sales/sales-note') }}/${window.activeEditNoteId}/invoices`;
                const res = await fetch(url);
                const result = await res.json();

                if (result.success) {
                    const items = result.remaining_items || [];
                    const tbody = document.getElementById('edit-selected-items-tbody');
                    tbody.innerHTML = '';
                    window._originalItems = [];

                    items.forEach(item => {
                        window._originalItems.push({ 
                            item_id: item.id, 
                            qty: item.quantity, 
                            price: item.unit_price, 
                            price_code: item.price_code || '',
                            disc: item.discount || 0, 
                            bonus: item.additional_qty || 0 
                        });

                        const row = document.createElement('tr');
                        row.setAttribute('data-product-id', item.product_id || 0);
                        row.setAttribute('data-item-id', item.id || '');
                        row.setAttribute('data-sales-order-item-id', item.sales_order_item_id || '');
                        row.setAttribute('data-actual-qty', Number(item.actual_qty ?? 0));
                        row.setAttribute('data-remaining-qty', Number(item.remaining_qty ?? item.quantity ?? 0));
                        row.dataset.onHand = String(Number(item.on_hand ?? 0));
                        row.className = "hover:bg-slate-50 transition-colors";
                        row.innerHTML = `
                            <td class="p-4 px-6"><input type="checkbox" checked class="rounded border-slate-300 text-maroon focus:ring-maroon"></td>
                            <td class="p-4 px-6 font-bold text-maroon product-code">${item.product_code}</td>
                            <td class="p-4 px-6 text-slate-700 product-desc">${item.description}</td>
                            <td class="p-4 px-6 text-center"><input type="number" value="${item.quantity}" min="0" oninput="updateEditSalesItemSubtotal(this)" onchange="updateEditSalesItemSubtotal(this)" class="item-qty w-20 px-3 py-1.5 border border-slate-200 rounded-lg text-center focus:ring-2 focus:ring-maroon/20 outline-none transition-all"></td>
                            ${renderEditActualQtyCell(item)}
                            <td class="p-4 px-6 text-center"><input type="text" value="${item.oum}" class="item-unit w-20 px-3 py-1.5 border border-slate-200 rounded-lg text-center focus:ring-2 focus:ring-maroon/20 outline-none transition-all"></td>
                            ${window.renderSalesNotePriceCodeCell ? window.renderSalesNotePriceCodeCell(item, 'updateEditSalesItemSubtotal(this)') : ''}
                            <td class="p-4 px-6 text-right"><input type="number" value="${item.unit_price}" oninput="updateEditSalesItemSubtotal(this)" onchange="updateEditSalesItemSubtotal(this)" class="item-price w-28 px-3 py-1.5 border border-slate-200 rounded-lg text-right focus:ring-2 focus:ring-maroon/20 outline-none transition-all"></td>
                            <td class="p-4 px-6 text-right"><input type="number" value="${item.subtotal}" class="item-subtotal w-32 px-3 py-1.5 border border-slate-200 rounded-lg text-right font-bold text-maroon bg-slate-50" readonly></td>
                            <td class="p-4 px-6"><div class="flex flex-col space-y-1"><input type="number" placeholder="Bonus" value="${item.additional_qty || ''}" oninput="updateEditSalesItemSubtotal(this)" onchange="updateEditSalesItemSubtotal(this)" class="bonus-input w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md"><input type="number" placeholder="Disc %" value="${item.discount || ''}" oninput="updateEditSalesItemSubtotal(this)" onchange="updateEditSalesItemSubtotal(this)" class="item-disc w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md"></div></td>
                            <td class="p-4 px-6 text-center"><button onclick="removeEditItemRow(this)" class="p-1.5 bg-red-50 text-red-500 hover:bg-red-600 hover:text-white rounded-lg transition-all" title="Remove item"><i data-lucide="x" class="w-3.5 h-3.5"></i></button></td>
                        `;
                        tbody.appendChild(row);
                    });

                    syncEditActualQtyColumnVisibility();
                    if (typeof lucide !== 'undefined') setTimeout(() => lucide.createIcons(), 50);
                    goToEditStep(3);
                } else {
                    alert('Error loading remaining items: ' + (result.message || 'Unknown error'));
                }
            } catch (e) {
                console.error('Error loading remaining items:', e);
                alert('Error loading remaining items. Please try again.');
            }
        };

        /**
         * Proceed to edit items without selecting an invoice (Open note)
         */
        window.proceedEditWithoutInvoice = async function() {
            window.activeEditInvoiceId = null;
            window.activeEditInvoiceNumber = 'Open Note';
            window._editAddedProductCodes = new Set();
            window._editDeletedProductCodes = new Set();
            window._pendingDeletedItemIds = new Set();

            try {
                const url = `{{ url('/admin/sales/sales-note') }}/${window.activeEditNoteId}/invoices`;
                const res = await fetch(url);
                const result = await res.json();

                if (result.success) {
                    const items = result.remaining_items || [];
                    const tbody = document.getElementById('edit-selected-items-tbody');
                    tbody.innerHTML = '';
                    window._originalItems = [];

                    items.forEach(item => {
                        window._originalItems.push({ 
                            item_id: item.id, 
                            qty: item.quantity, 
                            price: item.unit_price, 
                            price_code: item.price_code || '',
                            disc: item.discount || 0, 
                            bonus: item.additional_qty || 0 
                        });

                        const row = document.createElement('tr');
                        row.setAttribute('data-product-id', item.product_id || 0);
                        row.setAttribute('data-item-id', item.id || '');
                        row.setAttribute('data-sales-order-item-id', item.sales_order_item_id || '');
                        row.setAttribute('data-actual-qty', Number(item.actual_qty ?? 0));
                        row.setAttribute('data-remaining-qty', Number(item.remaining_qty ?? item.quantity ?? 0));
                        row.dataset.onHand = String(Number(item.on_hand ?? 0));
                        row.className = "hover:bg-slate-50 transition-colors";
                        row.innerHTML = `
                            <td class="p-4 px-6"><input type="checkbox" checked class="rounded border-slate-300 text-maroon focus:ring-maroon"></td>
                            <td class="p-4 px-6 font-bold text-maroon product-code">${item.product_code}</td>
                            <td class="p-4 px-6 text-slate-700 product-desc">${item.description}</td>
                            <td class="p-4 px-6 text-center"><input type="number" value="${item.quantity}" min="0" oninput="updateEditSalesItemSubtotal(this)" onchange="updateEditSalesItemSubtotal(this)" class="item-qty w-20 px-3 py-1.5 border border-slate-200 rounded-lg text-center focus:ring-2 focus:ring-maroon/20 outline-none transition-all"></td>
                            ${renderEditActualQtyCell(item)}
                            <td class="p-4 px-6 text-center"><input type="text" value="${item.oum}" class="item-unit w-20 px-3 py-1.5 border border-slate-200 rounded-lg text-center focus:ring-2 focus:ring-maroon/20 outline-none transition-all"></td>
                            ${window.renderSalesNotePriceCodeCell ? window.renderSalesNotePriceCodeCell(item, 'updateEditSalesItemSubtotal(this)') : ''}
                            <td class="p-4 px-6 text-right"><input type="number" value="${item.unit_price}" oninput="updateEditSalesItemSubtotal(this)" onchange="updateEditSalesItemSubtotal(this)" class="item-price w-28 px-3 py-1.5 border border-slate-200 rounded-lg text-right focus:ring-2 focus:ring-maroon/20 outline-none transition-all"></td>
                            <td class="p-4 px-6 text-right"><input type="number" value="${item.subtotal}" class="item-subtotal w-32 px-3 py-1.5 border border-slate-200 rounded-lg text-right font-bold text-maroon bg-slate-50" readonly></td>
                            <td class="p-4 px-6"><div class="flex flex-col space-y-1"><input type="number" placeholder="Bonus" value="${item.additional_qty || ''}" oninput="updateEditSalesItemSubtotal(this)" onchange="updateEditSalesItemSubtotal(this)" class="bonus-input w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md"><input type="number" placeholder="Disc %" value="${item.discount || ''}" oninput="updateEditSalesItemSubtotal(this)" onchange="updateEditSalesItemSubtotal(this)" class="item-disc w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md"></div></td>
                            <td class="p-4 px-6 text-center"><button onclick="removeEditItemRow(this)" class="p-1.5 bg-red-50 text-red-500 hover:bg-red-600 hover:text-white rounded-lg transition-all" title="Remove item"><i data-lucide="x" class="w-3.5 h-3.5"></i></button></td>
                        `;
                        tbody.appendChild(row);
                    });

                    syncEditActualQtyColumnVisibility();
                    if (typeof lucide !== 'undefined') setTimeout(() => lucide.createIcons(), 50);
                    goToEditStep(3);
                } else {
                    alert('Error loading note items: ' + (result.message || 'Unknown error'));
                }
            } catch (e) {
                console.error('Error loading note items:', e);
                alert('Error loading note items. Please try again.');
            }
        };

        window.toggleEditOnlineMode = function() {
            window._editOnlineMode = !window._editOnlineMode;
            const track = document.getElementById('edit-online-toggle-track');
            const thumb = document.getElementById('edit-online-toggle-thumb');
            if (track && thumb) {
                track.className = window._editOnlineMode ? 'w-9 h-5 bg-emerald-400 rounded-full transition-colors' : 'w-9 h-5 bg-slate-200 rounded-full transition-colors';
                thumb.style.transform = window._editOnlineMode ? 'translateX(16px)' : 'translateX(0)';
            }
        };

        window.updateEditSalesItemSubtotal = function(input) {
            const row = input.closest('tr');
            const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
            const actualQty = getEditRowActualQty(row);
            const price = parseFloat(row.querySelector('.item-price').value) || 0;
            const disc = parseFloat(row.querySelector('.item-disc').value) || 0;
            row.setAttribute('data-remaining-qty', Math.max(0, qty - actualQty));
            row.querySelector('.item-subtotal').value = (qty * price * (1 - disc / 100)).toFixed(2);

            const pid = parseInt(row.getAttribute('data-product-id')) || 0;
            if (!window._proceedLowStock) {
                const issues = getEditSelectedItemsStockIssues();
                if (issues.some(i => (parseInt(i.product_id) || 0) === pid)) {
                    const now = Date.now();
                    if (!window._lastStockModalAt || (now - window._lastStockModalAt) > 1200) {
                        window._lastStockModalAt = now;
                        showStockWarningModal(issues, true);
                    }
                }
            }
        };

        function getEditSelectedItemsStockIssues() {
            const requestedByPid = new Map();
            const metaByPid = new Map();
            const availableByPid = new Map();

            document.querySelectorAll('#edit-selected-items-tbody tr').forEach(row => {
                if (row.style.display === 'none') return;
                if (!window._editOnlineMode && !row.querySelector('input[type="checkbox"]')?.checked) return;
                const pid = parseInt(row.getAttribute('data-product-id')) || 0;
                const code = row.querySelector('.product-code')?.innerText || '';
                const desc = row.querySelector('.product-desc')?.innerText || '';
                const qty = parseInt(row.querySelector('.item-qty')?.value) || 0;
                const bonus = parseInt(row.querySelector('.bonus-input')?.value) || 0;

                if (!pid) {
                    requestedByPid.set(0, (requestedByPid.get(0) || 0) + (qty + bonus));
                    metaByPid.set(0, { product_code: code, description: desc });
                    availableByPid.set(0, 0);
                    return;
                }

                const requested = qty + bonus;
                requestedByPid.set(pid, (requestedByPid.get(pid) || 0) + requested);
                if (!metaByPid.has(pid)) metaByPid.set(pid, { product_code: code, description: desc });
                if (!availableByPid.has(pid)) {
                    const onHand = Number(row.dataset.onHand ?? row.getAttribute('data-on-hand') ?? 0);
                    availableByPid.set(pid, onHand);
                }
            });

            const issues = [];
            for (const [pid, requestedQty] of requestedByPid.entries()) {
                const available = Number(availableByPid.get(pid) ?? 0);
                if (available < Number(requestedQty || 0)) {
                    const meta = metaByPid.get(pid) || { product_code: '', description: '' };
                    issues.push({
                        product_id: pid,
                        product_code: meta.product_code,
                        description: meta.description,
                        requested_qty: Number(requestedQty || 0),
                        available_stock: available,
                        reason: available <= 0 ? 'Out of stock' : 'Not enough stock',
                    });
                }
            }
            return issues;
        }

        function validateEditSelectedItemsStock(showModal) {
            if (window._proceedLowStock) return true;
            const issues = getEditSelectedItemsStockIssues();
            if (issues.length === 0) return true;
            if (showModal) {
                showStockWarningModal(issues, true);
            }
            return false;
        }

        function populateEditReviewData() {
            document.getElementById('edit-rev-sales-no').innerText = document.getElementById('edit-sales-no').value;
            document.getElementById('edit-rev-sales-date').innerText = document.getElementById('edit-sales-date').value;
            document.getElementById('edit-rev-so-type').innerText = document.getElementById('edit-so-type').value;
            document.getElementById('edit-rev-customer-name').innerText = document.getElementById('edit-customer-name').value || '---';
            document.getElementById('edit-rev-salesman').innerText = document.getElementById('edit-salesman').value || '---';
            document.getElementById('edit-rev-prepared-by').innerText = document.getElementById('edit-prepared-by').value || '---';
            document.getElementById('edit-rev-checked-by').innerText = document.getElementById('edit-checked-by').value || '---';
            document.getElementById('edit-rev-packed-by').innerText = document.getElementById('edit-packed-by').value || '---';
            document.getElementById('edit-rev-is-rush').innerText = document.getElementById('edit-is-rush').checked ? 'YES (RUSH)' : 'NO';

            const reviewTbody = document.getElementById('edit-review-items-tbody');
            const sourceTbody = document.getElementById('edit-selected-items-tbody');
            if (reviewTbody && sourceTbody) {
                reviewTbody.innerHTML = '';
                const rows = sourceTbody.querySelectorAll('tr');
                const showActualQtyInReview = Array.from(rows).some(row => getEditRowActualQty(row) > 0);
                let grossTotal = 0;
                let totalDiscount = 0;
                document.querySelectorAll('.edit-review-actual-qty-col').forEach(el => {
                    el.classList.toggle('hidden', !showActualQtyInReview);
                });

                rows.forEach(row => {
                    if (row.style.display === 'none') return;
                    const code = row.querySelector('.product-code').innerText;
                    const desc = row.querySelector('.product-desc').innerText;
                    const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
                    const actualQty = getEditRowActualQty(row);
                    const unit = row.querySelector('.item-unit').value;
                    const price = parseFloat(row.querySelector('.item-price').value) || 0;
                    const discPercent = parseFloat(row.querySelector('.item-disc').value) || 0;
                    const bonus = row.querySelector('.bonus-input')?.value || '0';
                    const subtotal = parseFloat(row.querySelector('.item-subtotal').value) || 0;
                    const rowGross = qty * price;
                    const rowDiscount = rowGross * (discPercent / 100);
                    grossTotal += rowGross;
                    totalDiscount += rowDiscount;

                    const newRow = document.createElement('tr');
                    newRow.className = "border-b border-slate-100";
                    newRow.innerHTML = `
                        <td class="p-3 text-slate-700 font-bold">${code}</td>
                        <td class="p-3 text-slate-600">${desc}</td>
                        <td class="p-3 text-center">${qty}</td>
                        ${showActualQtyInReview ? `<td class="p-3 text-center text-amber-600 font-bold">${actualQty > 0 ? actualQty : ''}</td>` : ''}
                        <td class="p-3 text-center uppercase">${unit}</td>
                        ${window.renderSalesNotePriceCodeDisplayCell ? window.renderSalesNotePriceCodeDisplayCell(row) : ''}
                        <td class="p-3 text-right">₱ ${price.toLocaleString()}</td>
                        <td class="p-3 text-center text-emerald-600 font-bold">${discPercent}%</td>
                        <td class="p-3 text-center text-amber-600 font-bold">${bonus}</td>
                        <td class="p-3 text-right font-bold text-maroon">₱ ${subtotal.toLocaleString()}</td>
                    `;
                    reviewTbody.appendChild(newRow);
                });

                const netTotal = grossTotal - totalDiscount;
                if (document.getElementById('edit-rev-gross-total')) document.getElementById('edit-rev-gross-total').innerText = `₱ ${grossTotal.toLocaleString(undefined, {minimumFractionDigits: 2})}`;
                if (document.getElementById('edit-rev-total-discount')) document.getElementById('edit-rev-total-discount').innerText = `₱ ${totalDiscount.toLocaleString(undefined, {minimumFractionDigits: 2})}`;
                if (document.getElementById('edit-rev-grand-total')) document.getElementById('edit-rev-grand-total').innerText = `₱ ${netTotal.toLocaleString(undefined, {minimumFractionDigits: 2})}`;
            }
        }

        /**
         * Remove item row from edit modal. Removes directly without password.
         */
        window.removeEditItemRow = function(btn) {
            const row = btn.closest('tr');
            if (!row) return;
            const itemId = row.getAttribute('data-item-id');
            const code = row.querySelector('.product-code')?.innerText || '';
            window._editDeletedProductCodes = window._editDeletedProductCodes || new Set();
            if (code) window._editDeletedProductCodes.add(code);
            if (itemId && itemId !== '0' && itemId !== '') {
                if (!window._pendingDeletedItemIds) window._pendingDeletedItemIds = new Set();
                window._pendingDeletedItemIds.add(itemId);
                row.style.display = 'none';
            } else {
                row.remove();
            }
            syncEditActualQtyColumnVisibility();
            if (typeof lucide !== 'undefined') setTimeout(() => lucide.createIcons(), 50);
        };

        window.editSalesNote = async function(id) {
            window.activeEditNoteId = id;
            window._proceedLowStock = false;
            window._editOnlineMode = false;
            window._editAddedProductCodes = new Set();
            window._editDeletedProductCodes = new Set();
            window._pendingDeletedItemIds = new Set();
            
            const track = document.getElementById('edit-online-toggle-track');
            const thumb = document.getElementById('edit-online-toggle-thumb');
            if (track && thumb) {
                track.className = 'w-9 h-5 bg-slate-200 rounded-full transition-colors';
                thumb.style.transform = 'translateX(0)';
            }
            
            // Fetch Details
            try {
                const url = window.salesRoutes.detailUrl.replace(':id', id);
                const res = await fetch(url);
                const r = await res.json();
                
                if (r.success) {
                    const note = r.note;
                    
                    document.getElementById('edit-sales-no').value = note.sales_number;
                    document.getElementById('edit-sales-date').value = note.order_date;
                    document.getElementById('edit-so-type').value = note.so_type;
                    document.getElementById('edit-customer-name').value = note.customer_name;
                    document.getElementById('edit-customer-id').value = note.customer_id;
                    document.getElementById('edit-customer-code').value = '';
                    
                    document.getElementById('edit-salesman').value = note.salesman || '';
                    document.getElementById('edit-prepared-by').value = note.prepared_by || '';
                    document.getElementById('edit-checked-by').value = note.checked_by || '';
                    document.getElementById('edit-packed-by').value = note.packed_by || '';
                    document.getElementById('edit-is-rush').checked = note.is_rush ? true : false;
                    
                    // Clear selected items tbody (will be loaded after invoice selection)
                    const tbody = document.getElementById('edit-selected-items-tbody');
                    tbody.innerHTML = '';
                    syncEditActualQtyColumnVisibility();
                    
                    // Clear original items tracker
                    window._originalItems = [];
                    
                    if (typeof lucide !== 'undefined') setTimeout(() => lucide.createIcons(), 50);
                    
                    toggleModal('edit-sales-note-modal', true);
                    goToEditStep(1);
                } else {
                    alert('Error: ' + r.message);
                }
            } catch (e) {
                console.error(e);
                alert('An error occurred while fetching details.');
            }
        };

        window.submitEditSalesNote = function() {
            if (!window._editOnlineMode && !validateEditSelectedItemsStock(true)) return;

            let addCount = 0, updateCount = 0, deleteCount = 0;
            let totalAmount = 0;
            window._pendingDeletedItemIds = window._pendingDeletedItemIds || new Set();
            document.querySelectorAll('#edit-selected-items-tbody tr').forEach(row => {
                if (row.style.display === 'none') return;
                const itemId = row.getAttribute('data-item-id') || '';
                const isChecked = window._editOnlineMode || row.querySelector('input[type="checkbox"]').checked;
                const subtotal = parseFloat(row.querySelector('.item-subtotal').value) || 0;
                if (isChecked) {
                    totalAmount += subtotal;
                    if (!itemId) {
                        addCount++;
                    } else {
                        const qty = parseInt(row.querySelector('.item-qty').value) || 0;
                        const price = parseFloat(row.querySelector('.item-price').value) || 0;
                        const disc = parseFloat(row.querySelector('.item-disc').value) || 0;
                        const bonus = parseInt(row.querySelector('.bonus-input').value) || 0;
                        const priceCode = window.getSalesNoteRowPriceCode ? (window.getSalesNoteRowPriceCode(row) || '') : '';
                        let changed = true;
                        const orig = window._originalItems?.find(o => String(o.item_id) === String(itemId));
                        if (orig) {
                            changed = !(orig.qty === qty && orig.price === price && (orig.price_code || '') === priceCode && orig.disc === disc && orig.bonus === bonus);
                        }
                        if (changed) updateCount++;
                    }
                } else if (itemId) {
                    if (!window._pendingDeletedItemIds.has(itemId)) {
                        window._pendingDeletedItemIds.add(itemId);
                    }
                }
            });
            deleteCount = window._pendingDeletedItemIds ? window._pendingDeletedItemIds.size : 0;

            document.getElementById('edit-confirm-add-count').textContent = addCount;
            document.getElementById('edit-confirm-update-count').textContent = updateCount;
            document.getElementById('edit-confirm-delete-count').textContent = deleteCount;
            document.getElementById('edit-confirm-total').textContent = '₱ ' + totalAmount.toLocaleString(undefined, {minimumFractionDigits: 2});

            toggleModal('edit-confirm-details-modal', true);
            if (typeof lucide !== 'undefined') setTimeout(() => lucide.createIcons(), 50);
        };

        window.finalizeEditSalesNote = async function() {
            const items = [];
            window._pendingDeletedItemIds = window._pendingDeletedItemIds || new Set();
            document.querySelectorAll('#edit-selected-items-tbody tr').forEach(row => {
                if (row.style.display === 'none') return;
                const itemId = row.getAttribute('data-item-id') || '';
                const isChecked = window._editOnlineMode || row.querySelector('input[type="checkbox"]').checked;
                if (!isChecked) {
                    if (itemId) window._pendingDeletedItemIds.add(itemId);
                    return;
                }
                const salesOrderItemId = row.getAttribute('data-sales-order-item-id') || '';
                const qty = parseInt(row.querySelector('.item-qty').value) || 0;
                const actualQty = parseInt(getEditRowActualQty(row)) || 0;
                const remainingQty = Math.max(0, qty - actualQty);
                const price = parseFloat(row.querySelector('.item-price').value) || 0;
                const priceCode = window.getSalesNoteRowPriceCode ? (window.getSalesNoteRowPriceCode(row) || '') : '';
                const disc = parseFloat(row.querySelector('.item-disc').value) || 0;
                const bonus = parseInt(row.querySelector('.bonus-input').value) || 0;

                let changed = true;
                if (itemId && window._originalItems) {
                    const orig = window._originalItems.find(o => String(o.item_id) === String(itemId));
                    if (orig) {
                        changed = !(orig.qty === qty && orig.price === price && (orig.price_code || '') === priceCode && orig.disc === disc && orig.bonus === bonus);
                    }
                }

                items.push({
                    sales_note_item_id: itemId || null,
                    sales_order_item_id: salesOrderItemId || null,
                    product_id: parseInt(row.getAttribute('data-product-id')) || 0,
                    product_code: row.querySelector('.product-code').innerText,
                    description: row.querySelector('.product-desc').innerText,
                    quantity: qty,
                    actual_qty: actualQty,
                    remaining_qty: remainingQty,
                    oum: row.querySelector('.item-unit').value,
                    price_code: priceCode || null,
                    unit_price: price,
                    discount: disc,
                    additional_qty: bonus,
                    subtotal: parseFloat(row.querySelector('.item-subtotal').value) || 0,
                    _changed: changed,
                });
            });

            if (!window._editOnlineMode && items.length === 0) { alert('Please select at least one item.'); return; }

            try {
                if (!window._proceedLowStock && !window._editOnlineMode) {
                    if (!validateEditSelectedItemsStock(true)) return;

                    const stockOk = await checkSalesNoteStock(items);
                    if (!stockOk) return;
                }

                const updateUrl = window.salesRoutes.updateUrl.replace(':id', window.activeEditNoteId);

                const deletedItemIds = window._pendingDeletedItemIds ? Array.from(window._pendingDeletedItemIds).map(Number).filter(id => id > 0) : [];

                const res = await fetch(updateUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.csrfToken || '' },
                    body: JSON.stringify({
                        sales_order_id: window.activeEditInvoiceId || null,
                        so_type: document.getElementById('edit-so-type').value,
                        order_date: document.getElementById('edit-sales-date').value,
                        customer_id: parseInt(document.getElementById('edit-customer-id').value) || 0,
                        customer_name: document.getElementById('edit-customer-name').value,
                        salesman: document.getElementById('edit-salesman').value,
                        prepared_by: document.getElementById('edit-prepared-by').value,
                        checked_by: document.getElementById('edit-checked-by').value,
                        packed_by: document.getElementById('edit-packed-by').value,
                        is_rush: document.getElementById('edit-is-rush').checked,
                        gross_total: parseFloat(document.getElementById('edit-rev-gross-total').innerText.replace(/[₱,]/g, '')) || 0,
                        total_discount: parseFloat(document.getElementById('edit-rev-total-discount').innerText.replace(/[₱,]/g, '')) || 0,
                        net_total: parseFloat(document.getElementById('edit-rev-grand-total').innerText.replace(/[₱,]/g, '')) || 0,
                        remarks: '',
                        items: items,
                        deleted_item_ids: deletedItemIds,
                        ignore_stock: window._proceedLowStock || window._editOnlineMode || false,
                    }),
                });
                let result;
                try {
                    result = await res.json();
                } catch (e) {
                    alert('Server error (HTTP ' + res.status + '). The response was not valid JSON. Check server logs for details.');
                    return;
                }
                if (!res.ok) {
                    if (result && Array.isArray(result.stock_issues) && result.stock_issues.length > 0) {
                        showStockWarningModal(result.stock_issues, true);
                        return;
                    }
                    const errors = result.errors ? Object.values(result.errors).flat().join('\n') : '';
                    alert('Error: ' + (result.message || 'Request failed') + (errors ? '\n\nDetails:\n' + errors : ''));
                    return;
                }
                if (result.success) {
                    toggleModal('edit-confirm-details-modal', false);
                    toggleModal('edit-sales-note-modal', false);

                    if (!window.activeEditInvoiceId) {
                        const noteId = window.activeEditNoteId;
                        const noteData = {
                            noteId: noteId,
                            sales_number: document.getElementById('edit-sales-no')?.value || '',
                            customer_name: document.getElementById('edit-customer-name')?.value || '',
                            status: 'Open',
                        };
                        sessionStorage.setItem('proceed_note_data', JSON.stringify(noteData));
                        window.location.href = window.salesRoutes.salesOrderUrl || '/admin/sales/sales-order';
                        return;
                    }

                    const backendAddedItems = Array.isArray(result.added_items) ? result.added_items : [];
                    const backendDeletedItems = Array.isArray(result.deleted_items) ? result.deleted_items : [];
                    const backendUpdatedItems = Array.isArray(result.updated_items) ? result.updated_items : [];
                    const redirectSalesOrderId = parseInt(result.sales_order_id || window.activeEditInvoiceId || 0, 10);

                    // Backend diff is authoritative, matching the Purchase Note →
                    // Purchase Order deferred-finalization flow.
                    sessionStorage.setItem('so_edit_changes', JSON.stringify({
                        salesOrderId: redirectSalesOrderId,
                        addedItems: backendAddedItems,
                        deletedItems: backendDeletedItems,
                        updatedItems: backendUpdatedItems,
                    }));

                    const addedCodes = backendAddedItems.map(item => item.product_code).filter(Boolean);
                    const deletedCodes = backendDeletedItems.map(item => item.product_code).filter(Boolean);
                    const params = new URLSearchParams({
                        editNoteId: window.activeEditNoteId,
                        salesOrderId: redirectSalesOrderId || window.activeEditInvoiceId
                    });
                    // Keep URL hints as a fallback for old cached browser assets.
                    if (addedCodes.length > 0) params.set('addedItems', addedCodes.join(','));
                    if (deletedCodes.length > 0) params.set('deletedItems', deletedCodes.join(','));
                    window.location.href = (window.salesRoutes.salesOrderUrl || '/admin/sales/sales-order') + '?' + params.toString();
                } else {
                    alert('Error: ' + (result.message || 'Failed to update sales note.'));
                }
            } catch (e) {
                console.error(e);
                alert('An error occurred: ' + e.message);
            }
        };
    </script>
@endpush

@include('partials.admin.admin_sidebar_navbar')
