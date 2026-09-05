@push('styles')
    <link rel="stylesheet" href="{{ asset('css/Purchase-Order.css') }}?v={{ time() }}">
@endpush

@section('purchase_order_content')
<script>
    window.purchaseRoutes = {
        process: '{{ route("admin.purchase-order.process") }}',
        updatePO: '{{ route("admin.purchase-order.update") }}',
        openNotesUrl: '{{ route("admin.purchase.open-notes") }}',
        dashboardDataUrl: '{{ route("admin.purchase-order.dashboard") }}',
        activeDataUrl: '{{ route("admin.purchase-order.active") }}',
        historyDataUrl: '{{ route("admin.purchase-order.history") }}',
        poItemsUrl: '{{ route("admin.purchase-order.po-items", ["poId" => ":poId"]) }}',
        poDetailUrl: '{{ route("admin.purchase-order.detail", ["poId" => ":poId"]) }}',
        forceCloseUrl: '{{ route("admin.purchase-order.force-close.submit", ["purchaseNoteId" => ":purchaseNoteId"]) }}',
        resyncCostsUrl: '{{ route("admin.purchase-order.resync-product-costs", ["poId" => ":poId"]) }}',
    };
    window.purchaseOrderForceCloseEnabled = true;
</script>
<div id="page-purchase-order-root" class="space-y-8 animate-fade-in text-slate-800">
    
    <!-- DASHBOARD CARDS -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <!-- Total Active P.O -->
                <div id="total-active-po" class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex items-center space-x-4 hover-card-trigger">
                    <div class="p-3 bg-maroon rounded-xl border-2 border-gold shadow-sm">
                        <i data-lucide="package" class="w-6 h-6 text-gold"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Active P.O</p>
                        <h3 class="text-2xl font-extrabold text-slate-800">0</h3>
                    </div>
                </div>
                <!-- Total Open -->
                <div id="total-open" class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex items-center space-x-4 hover-card-trigger">
                    <div class="p-3 bg-maroon rounded-xl border-2 border-gold shadow-sm">
                        <i data-lucide="folder-open" class="w-6 h-6 text-gold"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Open</p>
                        <h3 class="text-2xl font-extrabold text-slate-800">0</h3>
                    </div>
                </div>
                <!-- Total Partial -->
                <div id="total-partial" class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex items-center space-x-4 hover-card-trigger">
                    <div class="p-3 bg-maroon rounded-xl border-2 border-gold shadow-sm">
                        <i data-lucide="pie-chart" class="w-6 h-6 text-gold"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Partial</p>
                        <h3 class="text-2xl font-extrabold text-slate-800">0</h3>
                    </div>
                </div>
                <!-- Total Purchase Orders -->
                <div id="total-purchase-orders" class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex items-center space-x-4 hover-card-trigger">
                    <div class="p-3 bg-maroon rounded-xl border-2 border-gold shadow-sm">
                        <i data-lucide="shopping-cart" class="w-6 h-6 text-gold"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Purchase Orders</p>
                        <h3 class="text-2xl font-extrabold text-slate-800">0</h3>
                    </div>
                </div>
            </div>

            <!-- TAB NAVIGATION -->
            <div class="flex items-center space-x-2 bg-white p-2 rounded-2xl border border-slate-100 shadow-sm w-fit">
                <button onclick="switchTab('active')" id="tab-btn-active" class="px-6 py-2.5 rounded-xl text-xs font-bold transition-all duration-200 bg-maroon text-white shadow-md">
                    Active Purchase Order
                </button>
                <button onclick="switchTab('history')" id="tab-btn-history" class="px-6 py-2.5 rounded-xl text-xs font-bold transition-all duration-200 text-slate-500 hover:bg-slate-50">
                    Purchase History
                </button>
            </div>

            <!-- TAB CONTENT: ACTIVE PURCHASE ORDER -->
            <div id="tab-content-active" class="space-y-6">
                <!-- MAIN ACTION BAR -->
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800">Active Purchase Orders</h2>
                        <p class="text-xs text-slate-400">Track and manage your current active purchase orders.</p>
                    </div>
                    
                    <div class="flex items-center space-x-3">
                        <button onclick="toggleModal('filter-modal', true)" class="px-4 py-2.5 bg-white border border-slate-200 hover:border-maroon-200 hover:bg-slate-50 text-slate-700 hover:text-maroon text-xs font-bold rounded-xl shadow-sm flex items-center space-x-2 transition-all">
                            <i data-lucide="sliders-horizontal" class="w-4 h-4 text-slate-500"></i>
                            <span>Filter</span>
                        </button>
                    </div>
                </div>

                <!-- MAIN TABLE SECTION -->
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden text-slate-800">
                    <div class="overflow-x-auto custom-scrollbar">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-slate-50/80 border-b border-slate-100">
                                <tr>
                                    <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-nowrap">Supplier Code</th>
                                    <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-nowrap">Supplier Name</th>
                                    <th class="p-4 px-6 text-[10px] font-bold text-maroon uppercase tracking-widest text-nowrap">P.O NO.</th>
                                    <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center text-nowrap">Date Issued</th>
                                    <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center text-nowrap">Currency</th>
                                    <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center text-nowrap">Status</th>
                                    <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center text-nowrap">Action</th>
                                </tr>
                                <tr class="bg-slate-50/30 border-b border-slate-100">
                                    <th class="p-2 px-5"><input type="text" id="col-search-supplierCode" data-col="supplierCode" onkeyup="filterTable('main-purchase-order-tbody', 0, this.value)" placeholder="Search Code..." class="column-search-input"></th>
                                    <th class="p-2 px-5"><input type="text" id="col-search-name" data-col="name" onkeyup="filterTable('main-purchase-order-tbody', 1, this.value)" placeholder="Search Name..." class="column-search-input"></th>
                                    <th class="p-2 px-5"><input type="text" id="col-search-transNo" data-col="transNo" onkeyup="filterTable('main-purchase-order-tbody', 2, this.value)" placeholder="Search P.O NO..." class="column-search-input font-bold text-maroon"></th>
                                    <th class="p-2 px-5"><input type="text" id="col-search-date" data-col="date" onkeyup="filterTable('main-purchase-order-tbody', 3, this.value)" placeholder="Search Date..." class="column-search-input"></th>
                                    <th class="p-2 px-5"><input type="text" id="col-search-currency" data-col="currency" onkeyup="filterTable('main-purchase-order-tbody', 4, this.value)" placeholder="Search Currency..." class="column-search-input"></th>
                                    <th class="p-2 px-5"><input type="text" id="col-search-status" data-col="status" onkeyup="filterTable('main-purchase-order-tbody', 5, this.value)" placeholder="Search Status..." class="column-search-input"></th>
                                    <th class="p-2 px-5"></th>
                                </tr>
                            </thead>
                            <tbody id="main-purchase-order-tbody" class="divide-y divide-slate-100 text-sm">
                                <!-- JS rendered -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div id="active-po-pagination" class="p-4 border-t border-slate-100 bg-white rounded-b-2xl"></div>
            </div>

            <!-- TAB CONTENT: PURCHASE HISTORY -->
            <div id="tab-content-history" class="hidden space-y-6">
                <!-- HISTORY ACTION BAR -->
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800">Purchase History</h2>
                        <p class="text-xs text-slate-400">View and review all completed purchase orders.</p>
                    </div>
                    
                    <div class="flex items-center space-x-3">
                        <button onclick="toggleModal('history-filter-modal', true)" class="px-4 py-2.5 bg-white border border-slate-200 hover:border-maroon-200 hover:bg-slate-50 text-slate-700 hover:text-maroon text-xs font-bold rounded-xl shadow-sm flex items-center space-x-2 transition-all">
                            <i data-lucide="sliders-horizontal" class="w-4 h-4 text-slate-500"></i>
                            <span>Filter History</span>
                        </button>
                    </div>
                </div>

                <!-- HISTORY TABLE SECTION -->
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden text-slate-800">
                    <div class="overflow-x-auto custom-scrollbar">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-slate-50/80 border-b border-slate-100">
                                <tr>
                                    <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-nowrap">Date Issue</th>
                                    <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-nowrap">Receiving No.</th>
                                    <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-nowrap">Supplier Invoice</th>
                                    <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-nowrap">Name</th>
                                    <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-right text-nowrap">Total Amount</th>
                                    <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-nowrap">Remarks</th>
                                    <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center text-nowrap">Action</th>
                                </tr>
                                <tr class="bg-slate-50/30 border-b border-slate-100">
                                    <th class="p-2 px-5"><input type="text" id="col-search-h-date" onkeyup="filterTable('history-purchase-order-tbody', 0, this.value)" placeholder="Search Date..." class="column-search-input"></th>
                                    <th class="p-2 px-5"><input type="text" id="col-search-h-recNo" name="receiving_number" onkeyup="filterTable('history-purchase-order-tbody', 1, this.value)" placeholder="Search Rec..." class="column-search-input"></th>
                                    <th class="p-2 px-5"><input type="text" id="col-search-h-invNo" name="supplier_invoice_number" onkeyup="filterTable('history-purchase-order-tbody', 2, this.value)" placeholder="Search Inv..." class="column-search-input"></th>
                                    <th class="p-2 px-5"><input type="text" id="col-search-h-name" onkeyup="filterTable('history-purchase-order-tbody', 3, this.value)" placeholder="Search Name..." class="column-search-input"></th>
                                    <th class="p-2 px-5"><input type="text" id="col-search-h-totalAmount" onkeyup="filterTable('history-purchase-order-tbody', 4, this.value)" placeholder="Search Amount..." class="column-search-input text-right"></th>
                                    <th class="p-2 px-5"><input type="text" id="col-search-h-remarks" onkeyup="filterTable('history-purchase-order-tbody', 5, this.value)" placeholder="Search Remarks..." class="column-search-input"></th>
                                    <th class="p-2 px-5"></th>
                                </tr>
                            </thead>
                            <tbody id="history-purchase-order-tbody" class="divide-y divide-slate-100 text-sm">
                                <!-- JS rendered -->
                            </tbody>
                        </table>
                    </div>
                    <div id="history-po-pagination" class="p-4 border-t border-slate-100 bg-white rounded-b-2xl"></div>
                </div>
            </div>
</div>

<!-- ==================== MODALS ==================== -->

<!-- 1. PROCEED MODAL (3 STEPS) -->
<div id="proceed-modal" class="fixed inset-0 z-[300] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="checkAndCloseProceedModal()" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm z-[300]"></div>
    <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-6xl w-full modal-animate-in mx-4 flex flex-col max-h-[90vh] z-[301]">
        
        <!-- Modal Header -->
        <div class="bg-maroon p-6 flex justify-between items-center text-white border-b-4 border-gold">
            <div class="flex items-center space-x-4">
                <div class="p-3 bg-white/10 rounded-2xl border-2 border-gold shadow-sm">
                    <i data-lucide="file-check" class="w-6 h-6 text-gold"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold tracking-tight">Purchase Order Processing</h3>
                    <p class="text-[10px] text-white/60 uppercase tracking-widest font-bold">Follow the steps to finalize your order</p>
                </div>
            </div>
            
            <!-- Step Indicators -->
            <div class="flex items-center space-x-8 mr-12">
                <div class="flex flex-col items-center space-y-2">
                    <div id="step-indicator-1" class="step-indicator active">1</div>
                    <span class="text-[8px] font-bold uppercase tracking-widest text-white/70">Basic Info</span>
                </div>
                <div class="w-12 h-0.5 bg-white/20 rounded-full mb-4"></div>
                <div class="flex flex-col items-center space-y-2">
                    <div id="step-indicator-2" class="step-indicator pending">2</div>
                    <span class="text-[8px] font-bold uppercase tracking-widest text-white/40">Items</span>
                </div>
                <div class="w-12 h-0.5 bg-white/20 rounded-full mb-4"></div>
                <div class="flex flex-col items-center space-y-2">
                    <div id="step-indicator-3" class="step-indicator pending">3</div>
                    <span class="text-[8px] font-bold uppercase tracking-widest text-white/40">Review</span>
                </div>
            </div>

            <button onclick="checkAndCloseProceedModal()" class="text-white/70 hover:text-white transition-colors bg-white/10 p-2 rounded-xl">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <!-- Modal Content (Scrollable) -->
        <div class="flex-1 overflow-y-auto custom-scrollbar p-8">
            
            <!-- STEP 1: BASIC INFORMATION -->
            <div id="po-step-1" class="po-step-content space-y-6">
                <div class="flex flex-col lg:flex-row gap-8">
                    <!-- Left Column (30%) - Automated Reference -->
                    <div class="lg:w-[30%] space-y-4">
                        <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100 space-y-4 shadow-sm">
                            <h4 class="text-xs font-bold text-maroon uppercase tracking-widest flex items-center space-x-2">
                                <i data-lucide="info" class="w-4 h-4 text-gold"></i>
                                <span>Reference (Auto)</span>
                            </h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Trans. No# (Control)</label>
                                    <input type="text" id="proceed-control-no" readonly class="w-full px-4 py-2.5 bg-slate-100/50 border border-slate-200 rounded-xl text-sm font-mono text-slate-500 outline-none cursor-not-allowed" value="TR-2024-001">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1 text-maroon">P.O NO.</label>
                                    <input type="text" id="proceed-pn-no" readonly class="w-full px-4 py-2.5 bg-maroon/5 border border-maroon/20 rounded-xl text-sm font-black font-mono text-maroon outline-none cursor-not-allowed" placeholder="0000001">
                                </div>
                            </div>
                        </div>

                        <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100 space-y-4 shadow-sm">
                            <h4 class="text-xs font-bold text-maroon uppercase tracking-widest flex items-center space-x-2">
                                <i data-lucide="truck" class="w-4 h-4 text-gold"></i>
                                <span>Supplier (Auto)</span>
                            </h4>
                            <div class="space-y-4 text-slate-800">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Code</label>
                                    <input type="text" id="proceed-supplier-code" readonly class="w-full px-4 py-2.5 bg-slate-100/50 border border-slate-200 rounded-xl text-sm text-slate-500 outline-none cursor-not-allowed" placeholder="SUP-001">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Name</label>
                                    <input type="text" id="proceed-supplier-name" readonly class="w-full px-4 py-2.5 bg-slate-100/50 border border-slate-200 rounded-xl text-sm text-slate-500 outline-none cursor-not-allowed" placeholder="Supplier Name">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column (70%) - Fillable Data -->
                    <div class="lg:w-[70%] space-y-4">
                        <div class="p-6 bg-white rounded-3xl border border-slate-100 space-y-6 shadow-md relative overflow-hidden">
                            <div class="absolute top-0 right-0 w-32 h-32 bg-maroon/5 rounded-full -mr-16 -mt-16"></div>
                            
                            <div class="flex justify-between items-center">
                                <h4 class="text-xs font-bold text-maroon uppercase tracking-widest flex items-center space-x-2">
                                    <i data-lucide="file-text" class="w-4 h-4 text-gold"></i>
                                    <span>Invoicing & Financials</span>
                                </h4>
                                <div class="flex items-center space-x-3">
                                    <div class="flex items-center space-x-2">
                                        <label class="text-[10px] font-bold text-slate-400 uppercase">Currency</label>
                                        <select id="proceed-currency" onchange="handleCurrencyChange(this)" class="px-3 py-1.5 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-maroon/20 focus:border-maroon transition-all text-maroon bg-maroon/5 outline-none">
                                            <option value="PHP">PHP (₱)</option>
                                            <option value="USD">USD ($)</option>
                                            <option value="TWD">TWD (NT$)</option>
                                        </select>
                                    </div>
                                    <div id="conv-rate-wrapper" class="flex items-center space-x-2 hidden">
                                        <label class="text-[10px] font-bold text-slate-400 uppercase">Rate</label>
                                        <input type="number" id="proceed-conv-rate" oninput="handleConversionRateChange(this)" step="0.0001" min="0" placeholder="1.0000" class="w-24 px-3 py-1.5 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-maroon/20 focus:border-maroon transition-all text-maroon bg-maroon/5 outline-none">
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div id="existing-invoice-panel" class="hidden rounded-2xl border border-amber-200 bg-amber-50/80 p-4">
                                    <p class="text-[10px] font-bold text-amber-700 uppercase tracking-widest mb-1">Existing Invoice</p>
                                    <p id="existing-invoice-display" class="text-xs font-mono font-bold text-amber-900 break-words">---</p>
                                </div>

                                <div class="flex items-center justify-between">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest">Supplier Invoice Number(s)</label>
                                    <button onclick="addInvoiceInput()" class="px-3 py-1 bg-maroon text-white text-[9px] font-black rounded-full hover:bg-maroon-800 transition-all flex items-center space-x-1 shadow-sm">
                                        <i data-lucide="plus" class="w-3 h-3"></i>
                                        <span>ADD INVOICE</span>
                                    </button>
                                </div>
                                
                                <div id="invoice-inputs-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                    <div class="relative group">
                                        <input type="text" class="supplier-invoice-input w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm font-mono focus:ring-2 focus:ring-maroon/20 focus:border-maroon transition-all text-slate-800 bg-slate-50/50" placeholder="Invoice #1">
                                    </div>
                                </div>
                            </div>

                            <div class="pt-4 border-t border-slate-100 flex justify-between items-center">
                                <div>
                                    <p class="text-[9px] font-bold text-slate-400 uppercase mb-1">Estimated Total Amount</p>
                                    <div class="flex items-baseline space-x-1">
                                        <span class="text-xs font-bold text-slate-400"></span>
                                        <input type="text" id="proceed-total-amount" readonly class="text-2xl font-black text-slate-800 bg-transparent border-none outline-none p-0 w-48" value="0.00">
                                    </div>
                                </div>
                                <div class="text-right">
                                    <label for="proceed-transaction-date" class="block text-[9px] font-bold text-slate-400 uppercase mb-1">Transaction Date</label>
                                    <input type="date" id="proceed-transaction-date" value="{{ now()->format('Y-m-d') }}" class="px-3 py-2 border border-slate-200 rounded-xl text-sm font-bold text-slate-700 bg-white focus:ring-2 focus:ring-maroon/20 focus:border-maroon outline-none">
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-6 bg-slate-50/50 rounded-2xl border border-slate-100 italic text-slate-400 text-[11px] flex items-start space-x-3">
                            <i data-lucide="help-circle" class="w-4 h-4 flex-shrink-0 mt-0.5"></i>
                            <p>You can add multiple supplier invoices if this purchase order is fulfilled across multiple billing statements. The total amount is automatically calculated from the items you process in the next step.</p>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end pt-6 border-t border-slate-100">
                    <button onclick="goToStep(2)" class="px-12 py-3.5 bg-maroon text-white hover:bg-maroon-800 text-xs font-bold rounded-2xl shadow-xl transition-all flex items-center space-x-3 group">
                        <span class="tracking-widest">NEXT: VERIFY PURCHASED ITEMS</span>
                        <i data-lucide="arrow-right" class="w-4 h-4 text-gold group-hover:translate-x-1 transition-transform"></i>
                    </button>
                </div>
            </div>

            <!-- STEP 2: PURCHASED ITEMS -->
            <div id="po-step-2" class="po-step-content hidden space-y-6">
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto custom-scrollbar">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-slate-50 border-b border-slate-100">
                                <tr>
                                    <th class="p-3 px-4 text-center">
                                        <input type="checkbox" id="select-all-po-items" onchange="toggleAllPOItems(this.checked)" class="w-4 h-4 rounded border-slate-300 text-maroon focus:ring-maroon cursor-pointer" title="Select All">
                                    </th>
                                    <th class="p-3 px-4 text-[9px] font-bold text-slate-500 uppercase tracking-widest">Item Code</th>
                                    <th class="p-3 px-4 text-[9px] font-bold text-slate-500 uppercase tracking-widest">Part No</th>
                                    <th class="p-3 px-4 text-[9px] font-bold text-slate-500 uppercase tracking-widest">Description</th>
                                    <th class="p-3 px-4 text-[9px] font-bold text-slate-500 uppercase tracking-widest text-center">QTY</th>
                                    <th class="p-3 px-4 text-[9px] font-bold text-maroon uppercase tracking-widest text-center">Actual QTY</th>
                                    <th class="p-3 px-4 text-[9px] font-bold text-slate-500 uppercase tracking-widest text-center">Unit</th>
                                    <th class="p-3 px-4 text-[9px] font-bold text-slate-500 uppercase tracking-widest">Unit Cost</th>
                                    <th class="p-3 px-4 text-[9px] font-bold text-slate-500 uppercase tracking-widest">Unit Cost (F)</th>
                                    <th class="p-3 px-4 text-[9px] font-bold text-slate-500 uppercase tracking-widest">% Disc</th>
                                    <th class="p-3 px-4 text-[9px] font-bold text-slate-500 uppercase tracking-widest text-right">Sub Total</th>
                                    <th class="p-3 px-4 text-[9px] font-bold text-maroon uppercase tracking-widest text-right">Actual Sub Total</th>
                                    <th class="p-3 px-4 text-[9px] font-bold text-slate-500 uppercase tracking-widest text-right">Sub Total (F)</th>
                                </tr>
                            </thead>
                            <tbody id="po-items-tbody" class="divide-y divide-slate-100 text-xs">
                                <!-- Dynamic Rows -->
                                <tr>
                                    <td class="p-3 px-4 font-bold text-maroon">ITEM-001</td>
                                    <td class="p-3 px-4 text-slate-500 font-mono">PN-X99</td>
                                    <td class="p-3 px-4 text-slate-700">Industrial Bearing A1</td>
                                    <td class="p-3 px-4 text-center">
                                        <input type="number" value="10" oninput="updateCalculations()" class="item-qty w-20 px-2 py-1.5 border border-slate-200 rounded text-center focus:ring-1 focus:ring-maroon/20">
                                    </td>
                                    <td class="p-3 px-4 text-center">
                                        <input type="number" value="10" oninput="updateCalculations()" class="item-actual-qty w-20 px-2 py-1.5 border border-maroon/30 rounded text-center focus:ring-1 focus:ring-maroon bg-maroon/5 font-bold text-maroon">
                                    </td>
                                    <td class="p-3 px-4 text-center text-slate-500">PCS</td>
                                    <td class="p-3 px-4">
                                        <input type="number" value="150.00" oninput="updateCalculations()" class="item-unit-cost w-32 px-2 py-1.5 border border-slate-200 rounded focus:ring-1 focus:ring-maroon/20">
                                    </td>
                                    <td class="p-3 px-4">
                                        <div class="flex items-center space-x-1">
                                            <select onchange="updateCalculations()" class="item-currency-f px-1 py-1.5 border border-slate-200 rounded text-[10px] focus:ring-1 focus:ring-maroon outline-none bg-slate-50">
                                                <option value="PHP">PHP</option>
                                                <option value="USD">USD</option>
                                                <option value="TWD">TWD</option>
                                            </select>
                                            <input type="number" value="0.00" oninput="updateCalculations()" class="item-unit-cost-f w-32 px-2 py-1.5 border border-slate-200 rounded focus:ring-1 focus:ring-maroon/20 bg-slate-50 font-bold text-maroon" placeholder="0.00">
                                        </div>
                                    </td>
                                    <td class="p-3 px-4">
                                        <input type="number" value="0" oninput="updateCalculations()" class="item-disc w-20 px-2 py-1.5 border border-slate-200 rounded text-center focus:ring-1 focus:ring-maroon/20">
                                    </td>
                                    <td class="p-3 px-4 text-right font-bold text-slate-800"> <span class="item-subtotal">1,500.00</span></td>
                                    <td class="p-3 px-4 text-right font-bold text-maroon">
                                        <div class="actual-subtotal-container hidden">
                                            ₱ <span class="item-actual-subtotal">1,500.00</span>
                                        </div>
                                        <span class="no-actual-change text-slate-300 italic text-[10px]">---</span>
                                    </td>
                                    <td class="p-3 px-4 text-right font-bold text-maroon"><span class="foreign-symbol">$</span> <span class="item-subtotal-f">0.00</span></td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-slate-50/50 border-t border-slate-100">
                                <tr>
                                    <td colspan="9" class="p-4 text-right text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Deductions</td>
                                    <td id="po-items-total-deduction-php" class="p-4 text-right text-base font-bold text-slate-500 italic"> 0.00</td>
                                </tr>
                                <tr>
                                    <td colspan="9" class="p-4 text-right text-[10px] font-bold text-maroon uppercase tracking-widest">Total Deductions (Foreign)</td>
                                    <td id="po-items-total-deduction-f" class="p-4 text-right text-base font-bold text-maroon italic"><span class="foreign-symbol">$</span> 0.00</td>
                                </tr>
                                <tr class="bg-slate-100/50">
                                    <td colspan="9" class="p-4 text-right text-[10px] font-bold text-slate-600 uppercase tracking-widest border-t border-slate-200">Grand Total </td>
                                    <td id="po-items-grand-total" class="p-4 text-right text-lg font-extrabold text-slate-800 tracking-tight border-t border-slate-200"></td>
                                </tr>
                                <tr class="bg-maroon/5">
                                    <td colspan="9" class="p-4 text-right text-[10px] font-bold text-maroon uppercase tracking-widest border-t border-slate-200">Actual Grand Total </td>
                                    <td id="po-items-actual-grand-total" class="p-4 text-right text-lg font-black text-maroon tracking-tight border-t border-slate-200"> 1,500.00</td>
                                </tr>
                                <tr>
                                    <td colspan="9" class="p-4 text-right text-[10px] font-bold text-maroon uppercase tracking-widest">Grand Total (Foreign)</td>
                                    <td id="po-items-grand-total-f" class="p-4 text-right text-lg font-extrabold text-maroon tracking-tight"><span class="foreign-symbol">$</span> 0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Additional Discount Section -->
                <div class="bg-gradient-to-r from-amber-50 to-white p-6 rounded-2xl border-2 border-amber-200 shadow-md">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="p-3 bg-amber-500 rounded-xl shadow-sm">
                                <i data-lucide="percent" class="w-5 h-5 text-white"></i>
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-amber-900 uppercase tracking-widest">Additional Discount</h4>
                                <p class="text-[10px] text-amber-600">Applied after all item discounts</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-4">
                            <div class="flex items-center space-x-2">
                                <label class="text-xs font-bold text-amber-900">Discount %:</label>
                                <input type="number" id="additional-discount-percent" value="0" min="0" max="100" step="0.01" oninput="updateCalculations()" class="w-24 px-3 py-2 border-2 border-amber-300 rounded-xl text-sm font-bold focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition-all text-amber-900 bg-white outline-none">
                            </div>
                            <div class="text-right">
                                <p class="text-[9px] font-bold text-amber-600 uppercase">Discount Amount</p>
                                <p id="additional-discount-amount" class="text-lg font-black text-amber-900">₱ 0.00</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-between pt-6">
                    <button onclick="goToStep(1)" class="px-8 py-3 bg-white border border-slate-200 text-slate-500 hover:bg-slate-50 text-xs font-bold rounded-2xl transition-all flex items-center space-x-2">
                        <i data-lucide="chevron-left" class="w-4 h-4"></i>
                        <span>BACK TO BASIC INFO</span>
                    </button>
                    <button onclick="goToStep(3)" class="px-10 py-3 bg-maroon text-white hover:bg-maroon-800 text-xs font-bold rounded-2xl shadow-lg transition-all flex items-center space-x-2">
                        <span>NEXT: REVIEW ORDER</span>
                        <i data-lucide="eye" class="w-4 h-4 text-gold"></i>
                    </button>
                </div>
            </div>

            <!-- STEP 3: REVIEW INFORMATION -->
            <div id="po-step-3" class="po-step-content hidden space-y-6">
                <div class="flex flex-col lg:flex-row min-h-[500px] bg-white rounded-3xl border border-slate-100 shadow-xl overflow-hidden">
                    <!-- Left side (30%) - Basic Information -->
                    <div class="lg:w-[30%] p-8 bg-slate-50 border-r border-slate-100 space-y-8">
                        <div>
                            <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-4">Basic Information</h4>
                            <div class="space-y-4">
                                <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
                                    <p class="text-[9px] font-bold text-slate-400 uppercase mb-1">Supplier Code</p>
                                    <p id="review-supplier-code" class="text-sm font-bold text-maroon">SUP-001</p>
                                </div>
                                <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
                                    <p class="text-[9px] font-bold text-slate-400 uppercase mb-1">Supplier Name</p>
                                    <p id="review-supplier-name" class="text-sm font-bold text-slate-700">ABC Supplier</p>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
                                        <p class="text-[9px] font-bold text-slate-400 uppercase mb-1">Control No.</p>
                                        <p id="review-control-no" class="text-xs font-mono text-slate-600">TR-2024-001</p>
                                    </div>
                                    <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
                                        <p class="text-[9px] font-bold text-slate-400 uppercase mb-1">PN NO.</p>
                                        <p id="review-pn-no" class="text-xs font-mono text-slate-600">PN-2024-001</p>
                                    </div>
                                    <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm col-span-2">
                                        <p class="text-[9px] font-bold text-slate-400 uppercase mb-1">Supplier Invoice No.</p>
                                        <p id="review-supplier-invoice" class="text-xs font-mono text-slate-600">---</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right side (70%) - Print Layout Preview -->
                    <div class="lg:w-[70%] p-8 flex flex-col">
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Print Layout Preview</h4>
                            <button onclick="enlargePrintLayout()" class="px-4 py-2 bg-maroon text-white hover:bg-maroon-800 text-xs font-bold rounded-xl shadow-md flex items-center space-x-2 transition-all">
                                <i data-lucide="maximize-2" class="w-4 h-4 text-gold"></i>
                                <span>ENLARGE PREVIEW</span>
                            </button>
                        </div>
                        
                        <!-- Print Layout Preview (Scaled Down) -->
                        <div class="flex-1 overflow-hidden border-2 border-slate-200 rounded-2xl bg-white shadow-inner">
                            <div id="print-preview-container" class="h-full overflow-auto custom-scrollbar p-4" style="transform-origin: top left;">
                                <div id="print-layout-content" style="width: 8.5in; transform: scale(0.7); transform-origin: top left;">
                                    <!-- Print Layout Content -->
                                    @include('partials.purchase.charge-receiving-layout')
                                </div>
                            </div>
                        </div>

                        <!-- Buttons inside right side panel -->
                        <div class="flex justify-end space-x-4 pt-6 border-t border-slate-100 mt-6">
                            <button onclick="goToStep(2)" class="px-8 py-3 bg-white border border-slate-200 text-slate-500 hover:bg-slate-50 text-xs font-bold rounded-2xl transition-all flex items-center space-x-2">
                                <i data-lucide="edit-3" class="w-4 h-4"></i>
                                <span>EDIT DETAILS</span>
                            </button>
                            <button id="force-close-note-btn" onclick="openForceClosePurchaseNoteModal()" class="hidden px-8 py-3 bg-red-600 text-white hover:bg-red-700 text-xs font-bold rounded-2xl shadow-lg transition-all flex items-center space-x-2">
                                <i data-lucide="archive-x" class="w-4 h-4 text-gold"></i>
                                <span>FORCED CLOSE NOTE</span>
                            </button>
                            <button id="confirm-proceed-btn" onclick="confirmProceed()" class="px-12 py-3 bg-green-600 text-white hover:bg-green-700 text-xs font-bold rounded-2xl shadow-lg transition-all flex items-center space-x-2">
                                <i data-lucide="check-circle" class="w-4 h-4 text-gold"></i>
                                <span>CONFIRM & FINALIZE</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- FORCE CLOSE PURCHASE NOTE CONFIRMATION MODAL -->
<div id="force-close-confirm-modal" class="fixed inset-0 z-[530] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="closeForceClosePurchaseNoteModal()" class="fixed inset-0 bg-slate-950/80 transition-opacity backdrop-blur-sm z-[530]"></div>
    <div class="relative bg-white rounded-3xl shadow-2xl max-w-lg w-full mx-4 overflow-hidden modal-animate-in z-[531] border-t-8 border-red-600">
        <div class="bg-maroon p-5 flex items-center justify-between text-white border-b-4 border-gold">
            <div class="flex items-center gap-3">
                <i data-lucide="triangle-alert" class="w-6 h-6 text-gold"></i>
                <h3 class="text-base font-extrabold uppercase tracking-widest">Confirm Forced Close Note</h3>
            </div>
            <button type="button" onclick="closeForceClosePurchaseNoteModal()" class="text-white/80 hover:text-white p-2 rounded-xl bg-white/10">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <div class="p-6 space-y-5">
            <div class="rounded-2xl border border-red-100 bg-red-50 p-4 text-sm text-red-800 leading-relaxed">
                <p class="font-extrabold uppercase tracking-wide mb-2">Purchase Note <span id="fc-note-number">---</span></p>
                <p>This will permanently delete the remaining Purchase Note items with quantity greater than zero and change the note status from <strong>Partial</strong> to <strong>Closed</strong>.</p>
                <p class="mt-3"><strong>Already invoiced Purchase Orders and their items will be preserved and will not be deleted or changed.</strong></p>
                <p class="mt-3 font-bold">This action cannot be undone.</p>
            </div>

            <label class="flex gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 cursor-pointer">
                <input id="fc-ack-checkbox" type="checkbox" onchange="toggleForceCloseConfirmButton()" class="mt-1 h-4 w-4 rounded border-slate-300 text-red-600 focus:ring-red-500">
                <span>I understand that the remaining Purchase Note items will be permanently deleted while invoiced items are preserved.</span>
            </label>

            <div class="flex gap-3">
                <button type="button" onclick="closeForceClosePurchaseNoteModal()" class="flex-1 px-4 py-3 bg-slate-100 text-slate-600 rounded-2xl text-xs font-bold hover:bg-slate-200 uppercase tracking-widest">Cancel</button>
                <button id="fc-confirm-btn" type="button" onclick="submitForceClosePurchaseNote()" disabled class="flex-1 px-4 py-3 bg-red-600 text-white rounded-2xl text-xs font-bold hover:bg-red-700 uppercase tracking-widest shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">Yes, Force Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ENLARGE PRINT LAYOUT MODAL -->
<div id="enlarge-print-modal" class="fixed z-[450] hidden" role="dialog" aria-modal="true" style="left: 0; right: 0; top: 0; bottom: 0;">
    <div onclick="toggleModal('enlarge-print-modal', false)" class="fixed bg-slate-900/90 transition-opacity backdrop-blur-sm z-[450]" style="left: 0; right: 0; top: 0; bottom: 0;"></div>
    <div class="fixed flex items-center justify-center z-[451]" style="left: 0; right: 0; top: 0; bottom: 0; padding: 20px;">
        <div class="relative bg-white rounded-2xl overflow-hidden shadow-2xl transform transition-all modal-animate-in flex flex-col" style="width: calc(100vw - 40px); height: calc(100vh - 40px); max-width: 1400px;">
            <!-- Header -->
            <div class="bg-maroon p-5 flex justify-between items-center text-white border-b-4 border-gold flex-shrink-0">
                <div class="flex items-center space-x-3">
                    <i data-lucide="file-text" class="w-5 h-5 text-gold"></i>
                    <h3 class="text-lg font-bold uppercase tracking-widest">Charge Receiving - Print Layout</h3>
                </div>
                <div class="flex items-center space-x-3">
                    <button onclick="printChargeReceiving()" class="px-4 py-2 bg-white text-maroon hover:bg-gold hover:text-maroon text-xs font-bold rounded-xl shadow-md flex items-center space-x-2 transition-all">
                        <i data-lucide="printer" class="w-4 h-4"></i>
                        <span>PRINT</span>
                    </button>
                    <button onclick="toggleModal('enlarge-print-modal', false)" class="text-white/70 hover:text-white transition-colors p-2 bg-white/10 rounded-xl">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>
            </div>
            
            <!-- iFrame Container -->
            <div class="flex-1 overflow-hidden bg-slate-100 p-4">
                <iframe id="print-layout-iframe" class="w-full h-full bg-white rounded-xl shadow-lg" frameborder="0"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- 2. SUPPLIER SEARCH MODAL -->
<div id="supplier-search-modal" class="fixed inset-0 z-[60] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('supplier-search-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-4xl w-full modal-animate-in mx-4">
        <div class="bg-maroon p-5 flex justify-between items-center text-white border-b-2 border-gold">
            <div class="flex items-center space-x-3">
                <i data-lucide="truck" class="w-5 h-5 text-gold"></i>
                <h3 class="text-sm font-bold uppercase tracking-widest">Select Supplier</h3>
            </div>
            <button onclick="toggleModal('supplier-search-modal', false)" class="text-white/70 hover:text-white transition-colors">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <div class="relative">
                <i data-lucide="search" class="absolute left-3 top-2.5 w-4 h-4 text-slate-400"></i>
                <input type="text" id="supplier-general-search" onkeyup="filterSupplierTable()" placeholder="Search by name or code..." class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-maroon/20 focus:border-maroon outline-none transition-all text-slate-800 bg-slate-50">
            </div>
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="overflow-x-auto custom-scrollbar max-h-[400px]">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead class="sticky top-0 bg-slate-50 border-b border-slate-100 text-slate-800 z-10">
                            <tr>
                                <th class="p-3 px-4 font-bold text-slate-500 uppercase tracking-widest">Supplier Code</th>
                                <th class="p-3 px-4 font-bold text-slate-500 uppercase tracking-widest">Name</th>
                                <th class="p-3 px-4 font-bold text-slate-500 uppercase tracking-widest">Contact</th>
                                <th class="p-3 px-4 font-bold text-slate-500 uppercase tracking-widest">Address</th>
                            </tr>
                            <tr class="bg-slate-50/50">
                                <th class="p-2 px-3"><input type="text" onkeyup="filterSupplierTableByCol(0, this.value)" placeholder="Search Code..." class="column-search-input"></th>
                                <th class="p-2 px-3"><input type="text" onkeyup="filterSupplierTableByCol(1, this.value)" placeholder="Search Name..." class="column-search-input"></th>
                                <th class="p-2 px-3"><input type="text" onkeyup="filterSupplierTableByCol(2, this.value)" placeholder="Search Contact..." class="column-search-input"></th>
                                <th class="p-2 px-3"><input type="text" onkeyup="filterSupplierTableByCol(3, this.value)" placeholder="Search Address..." class="column-search-input"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr onclick="selectSupplier('SUP-001', 'ABC Supplier')" class="hover:bg-slate-50 cursor-pointer transition-colors">
                                <td class="p-3 px-4 font-bold text-maroon">SUP-001</td>
                                <td class="p-3 px-4 font-bold text-slate-700">ABC Supplier</td>
                                <td class="p-3 px-4 text-slate-500">09123456789</td>
                                <td class="p-3 px-4 text-slate-500 truncate max-w-[200px]">123 Street, Manila</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
    </div>
</div>

<!-- 3. FILTER MODAL -->
<div id="filter-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('filter-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-md sm:w-full modal-animate-in mx-4">
        <div class="bg-maroon p-5 flex justify-between items-center text-white border-b-2 border-gold">
            <div class="flex items-center space-x-3">
                <i data-lucide="filter" class="w-5 h-5 text-gold"></i>
                <h3 class="text-sm font-bold uppercase tracking-widest">Filter Options</h3>
            </div>
            <button onclick="toggleModal('filter-modal', false)" class="text-white/70 hover:text-white transition-colors">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2">Filter by Date Range</label>
                <div class="grid grid-cols-2 gap-3">
                    <input type="date" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-maroon/20 outline-none">
                    <input type="date" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-maroon/20 outline-none">
                </div>
            </div>
            <div class="pt-4 flex justify-end">
                <button onclick="toggleModal('filter-modal', false)" class="px-8 py-2.5 bg-maroon text-white text-xs font-bold rounded-xl shadow-lg hover:bg-maroon-800 transition-all">Apply Filter</button>
            </div>
        </div>
    </div>
</div>

<!-- 4b. PROCESSING / RE-SYNC MODAL -->
<div id="po-sync-modal" class="fixed inset-0 z-[420] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-950/85 transition-opacity backdrop-blur-md z-[420]"></div>
    <div class="relative bg-white rounded-3xl p-8 shadow-2xl transform transition-all max-w-lg w-full modal-animate-in mx-4 border-t-8 border-maroon z-[421]">
        <div class="flex items-start gap-4 mb-6">
            <div id="po-sync-spinner" class="h-14 w-14 rounded-2xl bg-maroon/10 border border-maroon/20 flex items-center justify-center shrink-0">
                <i data-lucide="loader-2" class="h-7 w-7 text-maroon animate-spin"></i>
            </div>
            <div id="po-sync-success-icon" class="h-14 w-14 rounded-2xl bg-green-100 border border-green-200 hidden items-center justify-center shrink-0">
                <i data-lucide="check-circle" class="h-7 w-7 text-green-600"></i>
            </div>
            <div id="po-sync-error-icon" class="h-14 w-14 rounded-2xl bg-red-100 border border-red-200 hidden items-center justify-center shrink-0">
                <i data-lucide="circle-alert" class="h-7 w-7 text-red-600"></i>
            </div>
            <div>
                <h3 id="po-sync-title" class="text-lg font-extrabold text-slate-800 uppercase tracking-widest">Analyzing Purchase Order</h3>
                <p id="po-sync-message" class="text-sm text-slate-500 leading-relaxed mt-1">Please wait while the transaction is being recorded.</p>
            </div>
        </div>

        <div class="space-y-3 mb-7">
            <div data-po-sync-step="purchase_orders" class="po-sync-step flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
                <span class="text-xs font-bold text-slate-600">Purchase Order</span>
                <span class="po-sync-step-status text-[10px] font-bold uppercase tracking-widest text-slate-400">Pending</span>
            </div>
            <div data-po-sync-step="purchase_order_items" class="po-sync-step flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
                <span class="text-xs font-bold text-slate-600">Purchase Order Items</span>
                <span class="po-sync-step-status text-[10px] font-bold uppercase tracking-widest text-slate-400">Pending</span>
            </div>
            <div data-po-sync-step="product_ledgers" class="po-sync-step flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
                <span class="text-xs font-bold text-slate-600">Product Ledger</span>
                <span class="po-sync-step-status text-[10px] font-bold uppercase tracking-widest text-slate-400">Pending</span>
            </div>
            <div data-po-sync-step="supplier_ledgers" class="po-sync-step flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
                <span class="text-xs font-bold text-slate-600">Supplier Ledger</span>
                <span class="po-sync-step-status text-[10px] font-bold uppercase tracking-widest text-slate-400">Pending</span>
            </div>
            <div data-po-sync-step="product_costs" class="po-sync-step flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
                <span class="text-xs font-bold text-slate-600">Product Cost</span>
                <span class="po-sync-step-status text-[10px] font-bold uppercase tracking-widest text-slate-400">Pending</span>
            </div>
        </div>

        <div class="flex gap-3">
            <button id="po-sync-close-btn" onclick="toggleModal('po-sync-modal', false)" class="hidden flex-1 px-4 py-3 bg-slate-100 text-slate-600 rounded-2xl text-xs font-bold hover:bg-slate-200 transition-all uppercase tracking-widest">Close</button>
            <button id="po-sync-retry-btn" onclick="retryPurchaseOrderSync()" class="hidden flex-1 px-4 py-3 bg-maroon text-white rounded-2xl text-xs font-bold hover:bg-maroon-800 transition-all shadow-lg uppercase tracking-widest">Re-Sync</button>
        </div>
    </div>
</div>

<!-- 5. HISTORY VIEW MODAL (30/70 SPLIT) -->
<div id="history-view-modal" class="fixed inset-0 z-[60] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('history-view-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-6xl w-full modal-animate-in mx-4 flex flex-col max-h-[90vh]">
        <div class="bg-maroon p-5 flex justify-between items-center text-white border-b-2 border-gold">
            <div class="flex items-center space-x-3">
                <i data-lucide="history" class="w-5 h-5 text-gold"></i>
                <h3 class="text-sm font-bold uppercase tracking-widest">Purchase History Detail</h3>
            </div>
            <button onclick="toggleModal('history-view-modal', false)" class="text-white/70 hover:text-white transition-colors bg-white/10 p-2 rounded-xl">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        
        <div class="flex-1 overflow-y-auto custom-scrollbar p-8">
            <div class="flex flex-col lg:flex-row min-h-[500px] bg-white rounded-3xl border border-slate-100 shadow-xl overflow-hidden">
                <!-- Left Side (30%) - Summary -->
                <div class="lg:w-[30%] p-8 bg-slate-50 border-r border-slate-100 space-y-6">
                    <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-4">Transaction Summary</h4>
                    <div class="space-y-4">
                        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
                            <p class="text-[9px] font-bold text-slate-400 uppercase mb-1">Date Issue</p>
                            <p id="h-view-date" class="text-sm font-bold text-slate-700">---</p>
                        </div>
                        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
                            <p class="text-[9px] font-bold text-slate-400 uppercase mb-1">Receiving No.</p>
                            <p id="h-view-receiving" class="text-sm font-mono text-maroon font-bold">---</p>
                        </div>
                        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
                            <p class="text-[9px] font-bold text-slate-400 uppercase mb-1">PO NO.</p>
                            <p id="h-view-po" class="text-sm font-mono text-slate-700 font-bold">---</p>
                        </div>
                        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
                            <p class="text-[9px] font-bold text-slate-400 uppercase mb-1">Supplier Invoice</p>
                            <p id="h-view-invoice" class="text-sm font-mono text-slate-700 font-bold">---</p>
                        </div>
                        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
                            <p class="text-[9px] font-bold text-slate-400 uppercase mb-1">Supplier Name</p>
                            <p id="h-view-name" class="text-sm font-bold text-slate-700">---</p>
                        </div>
                    </div>
                </div>

                <!-- Right Side (70%) - Items Table -->
                <div class="lg:w-[70%] p-8 flex flex-col space-y-4">
                    <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Itemized Breakdown</h4>
                    <div class="flex-1 overflow-x-auto custom-scrollbar border border-slate-100 rounded-2xl bg-white shadow-inner">
                        <table class="w-full text-left border-collapse">
                            <thead class="sticky top-0 bg-slate-50 border-b border-slate-100 z-10">
                                <tr>
                                    <th class="p-3 text-[9px] font-bold text-slate-500 uppercase tracking-widest">Item Code</th>
                                    <th class="p-3 text-[9px] font-bold text-slate-500 uppercase tracking-widest">Part No.</th>
                                    <th class="p-3 text-[9px] font-bold text-slate-500 uppercase tracking-widest">Description</th>
                                    <th class="p-3 text-[9px] font-bold text-slate-500 uppercase tracking-widest text-center">QTY</th>
                                    <th class="p-3 text-[9px] font-bold text-slate-500 uppercase tracking-widest text-center">Unit</th>
                                    <th class="p-3 text-[9px] font-bold text-slate-500 uppercase tracking-widest text-right">Unit Cost</th>
                                    <th class="p-3 text-[9px] font-bold text-slate-500 uppercase tracking-widest text-right">Unit Cost (F)</th>
                                    <th class="p-3 text-[9px] font-bold text-slate-500 uppercase tracking-widest text-center">%Disc</th>
                                    <th class="p-3 text-[9px] font-bold text-slate-500 uppercase tracking-widest text-right">Sub Total</th>
                                    <th class="p-3 text-[9px] font-bold text-slate-500 uppercase tracking-widest text-right">Sub Total (F)</th>
                                </tr>
                            </thead>
                            <tbody id="history-view-items-tbody" class="divide-y divide-slate-100 text-[11px]">
                                <!-- Populated via JS -->
                            </tbody>
                            <tfoot class="bg-slate-50 border-t-2 border-slate-200">
                                <tr>
                                    <td colspan="8" class="p-3 text-right font-bold text-slate-500 uppercase tracking-widest text-[9px]">Total</td>
                                    <td id="h-view-subtotal-sum" class="p-3 text-right font-bold text-slate-700 whitespace-nowrap">₱ 0.00</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colspan="8" class="p-3 text-right font-bold text-amber-600 uppercase tracking-widest text-[9px]">Additional Discount</td>
                                    <td id="h-view-footer-additional-discount" class="p-3 text-right font-bold text-amber-700 whitespace-nowrap">0%</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colspan="8" class="p-3 text-right font-bold text-maroon uppercase tracking-widest text-[9px]">Grand Total (<span id="h-view-gt-currency-label">PHP</span>)</td>
                                    <td id="h-view-grand-total-php" class="p-3 text-right font-bold text-maroon whitespace-nowrap"> 0.00</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="bg-slate-50 p-6 flex justify-end">
            <button onclick="toggleModal('history-view-modal', false)" class="px-8 py-3 bg-white border border-slate-200 text-slate-600 hover:bg-slate-100 text-xs font-bold rounded-2xl transition-all shadow-sm">CLOSE VIEW</button>
        </div>
    </div>
</div>

<!-- 6. HISTORY FILTER MODAL -->
<div id="history-filter-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('history-filter-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-md w-full modal-animate-in mx-4 border-t-8 border-maroon">
        <div class="bg-maroon p-5 flex justify-between items-center text-white border-b-2 border-gold">
            <div class="flex items-center space-x-3">
                <i data-lucide="sliders-horizontal" class="w-5 h-5 text-gold"></i>
                <h3 class="text-sm font-bold uppercase tracking-widest">Filter History</h3>
            </div>
            <button onclick="toggleModal('history-filter-modal', false)" class="text-white/70 hover:text-white transition-colors">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        <div class="p-6 space-y-6">
            <div class="space-y-4">
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2">Filter by Date Range</label>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="relative">
                            <input type="date" id="history-filter-date-start" class="w-full pl-3 pr-3 py-2.5 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-maroon/20 outline-none text-slate-700 bg-slate-50">
                        </div>
                        <div class="relative">
                            <input type="date" id="history-filter-date-end" class="w-full pl-3 pr-3 py-2.5 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-maroon/20 outline-none text-slate-700 bg-slate-50">
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2">Filter by Supplier Name</label>
                    <input type="text" id="history-filter-name" placeholder="Enter name..." class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-maroon/20 outline-none text-slate-700 bg-slate-50">
                </div>
            </div>
            <div class="pt-4 flex space-x-3">
                <button onclick="resetHistoryFilters()" class="flex-1 py-3 bg-slate-100 text-slate-500 text-[10px] font-bold rounded-xl hover:bg-slate-200 transition-all uppercase tracking-widest">Reset</button>
                <button onclick="applyHistoryFilters()" class="flex-1 py-3 bg-maroon text-white text-[10px] font-bold rounded-xl shadow-lg hover:bg-maroon-800 transition-all uppercase tracking-widest">Apply Filter</button>
            </div>
        </div>
    </div>
</div>

<!-- 6b. DISCARD CHANGES CONFIRMATION MODAL -->
<div id="discard-proceed-modal" class="fixed inset-0 z-[350] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-900/80 transition-opacity backdrop-blur-md z-[350]"></div>
    <div class="relative bg-white rounded-3xl text-center p-8 shadow-2xl transform transition-all max-w-sm w-full modal-animate-in mx-4 border-t-8 border-amber-500 z-[351]">
        <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-amber-100 mb-6">
            <i data-lucide="alert-triangle" class="h-10 w-10 text-amber-500"></i>
        </div>
        <h3 class="text-xl font-extrabold text-slate-800 mb-2 tracking-tight uppercase">Discard Changes?</h3>
        <p class="text-sm text-slate-500 mb-8 leading-relaxed">You have unsaved information in this form. Are you sure you want to close and discard all entered data?</p>
        <div class="flex space-x-3">
            <button onclick="toggleModal('discard-proceed-modal', false)" class="flex-1 px-4 py-3 bg-slate-100 text-slate-600 rounded-2xl text-xs font-bold hover:bg-slate-200 transition-all uppercase tracking-widest">No, Keep Editing</button>
            <button onclick="confirmDiscardProceedModal()" class="flex-1 px-4 py-3 bg-amber-500 text-white rounded-2xl text-xs font-bold hover:bg-amber-600 transition-all shadow-lg uppercase tracking-widest">Yes, Discard</button>
        </div>
    </div>
</div>

<!-- 7. SUCCESS MODAL -->
<div id="success-modal" class="fixed inset-0 z-[80] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('success-modal', false)" class="fixed inset-0 bg-slate-900/80 transition-opacity backdrop-blur-md"></div>
    <div class="relative bg-white rounded-3xl text-center p-10 shadow-2xl transform transition-all max-w-md w-full modal-animate-in mx-4 border-t-8 border-green-500">
        <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-green-100 mb-6 animate-bounce">
            <i data-lucide="check-circle" class="h-12 w-12 text-green-500"></i>
        </div>
        <h3 class="text-2xl font-extrabold text-slate-800 mb-2">Success!</h3>
        <p id="success-msg" class="text-sm text-slate-500 mb-8 leading-relaxed">The Purchase Order has been successfully processed and recorded in the system.</p>
        <button id="success-reload-btn" onclick="completeSuccessfulPOProcess()" class="w-full px-4 py-4 bg-maroon text-white rounded-2xl text-xs font-bold hover:bg-maroon-800 transition-all shadow-xl uppercase tracking-widest">Continue &amp; Reload</button>
    </div>
</div>

<!-- 8. EDIT PURCHASE ORDER MODAL (3 STEPS) -->
<div id="edit-modal" class="fixed inset-0 z-[300] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="checkAndCloseEditModal()" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm z-[300]"></div>
    <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-6xl w-full modal-animate-in mx-4 flex flex-col max-h-[90vh] z-[301]">
        
        <!-- Modal Header -->
        <div class="bg-blue-600 p-6 flex justify-between items-center text-white border-b-4 border-blue-400">
            <div class="flex items-center space-x-4">
                <div class="p-3 bg-white/10 rounded-2xl border-2 border-blue-400 shadow-sm">
                    <i data-lucide="edit" class="w-6 h-6 text-blue-200"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold uppercase tracking-widest">Edit Purchase Order</h3>
                    <p class="text-xs text-blue-100 tracking-wide">Modify purchase order details and items</p>
                </div>
            </div>
            <button onclick="checkAndCloseEditModal()" class="text-white/70 hover:text-white transition-colors">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <!-- Step Indicators -->
        <div class="bg-slate-50 px-8 py-4 border-b border-slate-200">
            <div class="flex items-center justify-between max-w-2xl mx-auto">
                <div id="edit-step-indicator-1" class="flex items-center space-x-2 flex-1">
                    <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold shadow-sm">1</div>
                    <span class="text-xs font-bold text-blue-600 uppercase tracking-wide hidden sm:inline">Order Info</span>
                </div>
                <div class="h-0.5 flex-1 bg-slate-200 mx-2"></div>
                <div id="edit-step-indicator-2" class="flex items-center space-x-2 flex-1 justify-center">
                    <div class="w-8 h-8 rounded-full bg-slate-300 text-slate-500 flex items-center justify-center text-xs font-bold">2</div>
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wide hidden sm:inline">Edit Items</span>
                </div>
                <div class="h-0.5 flex-1 bg-slate-200 mx-2"></div>
                <div id="edit-step-indicator-3" class="flex items-center space-x-2 flex-1 justify-end">
                    <div class="w-8 h-8 rounded-full bg-slate-300 text-slate-500 flex items-center justify-center text-xs font-bold">3</div>
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wide hidden sm:inline">Review</span>
                </div>
            </div>
        </div>

        <!-- Modal Body (Scrollable Steps) -->
        <div class="flex-1 overflow-y-auto custom-scrollbar">
            <!-- STEP 1: Order Information -->
            <div id="edit-step-1" class="p-8">
                <h4 class="text-base font-bold text-slate-700 mb-4 uppercase tracking-wider">Purchase Order Information</h4>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-2 uppercase tracking-wider">Supplier Code</label>
                        <input type="text" id="edit-supplier-code" readonly class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all bg-slate-50 text-slate-600">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-2 uppercase tracking-wider">Supplier Name</label>
                        <input type="text" id="edit-supplier-name" readonly class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all bg-slate-50 text-slate-600">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-2 uppercase tracking-wider">Receiving No.</label>
                        <input type="text" id="edit-control-no" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-2 uppercase tracking-wider">P.N No.</label>
                        <input type="text" id="edit-pn-no" readonly class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all bg-slate-50 text-slate-600">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-2 uppercase tracking-wider">Transaction Date</label>
                        <input type="date" id="edit-transaction-date" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all bg-white">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-2 uppercase tracking-wider">Currency</label>
                        <select id="edit-currency" onchange="handleEditCurrencyChange(this)" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all bg-white">
                            <option value="PHP">PHP - Philippine Peso (₱)</option>
                            <option value="USD">USD - US Dollar ($)</option>
                            <option value="TWD">TWD - Taiwan Dollar (NT$)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-2 uppercase tracking-wider">Conversion Rate</label>
                        <input type="number" id="edit-conv-rate" value="1" step="0.01" onchange="handleEditConversionRateChange(this)" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                    </div>
                </div>

                <div class="mt-6">
                    <label class="block text-xs font-bold text-slate-600 mb-2 uppercase tracking-wider">Supplier Invoice Numbers</label>
                    <div id="edit-invoice-inputs-container" class="space-y-2">
                        <div class="relative group">
                            <input type="text" class="supplier-invoice-input w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm font-mono focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-slate-800 bg-slate-50/50" placeholder="Invoice #1">
                        </div>
                    </div>
                    <button onclick="addEditInvoiceInput()" class="mt-3 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded-xl transition-all flex items-center space-x-2">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        <span>Add Another Invoice</span>
                    </button>
                </div>
            </div>

            <!-- STEP 2: Edit Items -->
            <div id="edit-step-2" class="p-8 hidden">
                <div class="flex justify-between items-center mb-4">
                    <h4 class="text-base font-bold text-slate-700 uppercase tracking-wider">Purchase Order Items</h4>
                    <div class="text-xs text-slate-500">
                        Currency: <span id="edit-current-currency-label" class="font-bold text-blue-600">PHP</span>
                    </div>
                </div>

                <!-- Additional Discount Section -->
                <div class="bg-amber-50 border-2 border-amber-200 rounded-xl p-4 mb-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <i data-lucide="percent" class="w-5 h-5 text-amber-600"></i>
                            <div>
                                <label class="block text-xs font-bold text-amber-800 uppercase tracking-wider">Additional Discount</label>
                                <p class="text-[10px] text-amber-600">Applied after all item discounts</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-4">
                            <div>
                                <input type="number" id="edit-additional-discount-percent" value="0" step="0.01" min="0" max="100" oninput="updateEditCalculations()" class="w-24 px-3 py-2 border-2 border-amber-300 rounded-lg text-sm font-bold text-amber-800 text-center focus:ring-2 focus:ring-amber-400 focus:border-amber-400">
                                <span class="text-xs font-bold text-amber-700 ml-1">%</span>
                            </div>
                            <div class="text-right">
                                <div class="text-[10px] text-amber-600 uppercase tracking-wide">Amount</div>
                                <div id="edit-additional-discount-amount" class="text-sm font-bold text-amber-800">₱ 0.00</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto custom-scrollbar border border-slate-200 rounded-xl">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead class="bg-slate-50 border-b-2 border-slate-200">
                            <tr>
                                <th class="p-3 px-4 text-[10px] font-bold text-slate-600 uppercase tracking-widest">Item Code</th>
                                <th class="p-3 px-4 text-[10px] font-bold text-slate-600 uppercase tracking-widest">Part No.</th>
                                <th class="p-3 px-4 text-[10px] font-bold text-slate-600 uppercase tracking-widest">Description</th>
                                <th class="p-3 px-4 text-[10px] font-bold text-slate-600 uppercase tracking-widest text-center">Qty</th>
                                <th class="p-3 px-4 text-[10px] font-bold text-blue-600 uppercase tracking-widest text-center">Actual Qty</th>
                                <th class="p-3 px-4 text-[10px] font-bold text-slate-600 uppercase tracking-widest text-center">Unit</th>
                                <th class="p-3 px-4 text-[10px] font-bold text-slate-600 uppercase tracking-widest">Unit Cost (PHP)</th>
                                <th class="p-3 px-4 text-[10px] font-bold text-slate-600 uppercase tracking-widest">Unit Cost (Foreign)</th>
                                <th class="p-3 px-4 text-[10px] font-bold text-slate-600 uppercase tracking-widest text-center">% Disc</th>
                                <th class="p-3 px-4 text-[10px] font-bold text-slate-600 uppercase tracking-widest text-right">Subtotal (PHP)</th>
                                <th class="p-3 px-4 text-[10px] font-bold text-blue-600 uppercase tracking-widest text-right">Actual Subtotal</th>
                                <th class="p-3 px-4 text-[10px] font-bold text-slate-600 uppercase tracking-widest text-right">Subtotal (Foreign)</th>
                            </tr>
                        </thead>
                        <tbody id="edit-items-tbody" class="divide-y divide-slate-100">
                            <!-- Items will be rendered here -->
                        </tbody>
                        <tfoot class="bg-slate-50 border-t-2 border-slate-200">
                            <tr>
                                <td colspan="9" class="p-3 px-4 text-right text-xs font-bold text-slate-600 uppercase tracking-wider">Total Item Discount:</td>
                                <td class="p-3 px-4 text-right font-bold text-slate-700" id="edit-items-total-deduction-php">₱ 0.00</td>
                                <td class="p-3 px-4 text-right font-bold text-blue-700" id="edit-items-total-deduction-actual">₱ 0.00</td>
                                <td class="p-3 px-4 text-right font-bold text-slate-700" id="edit-items-total-deduction-f"><span class="foreign-symbol">$</span> 0.00</td>
                            </tr>
                            <tr class="bg-blue-50">
                                <td colspan="9" class="p-3 px-4 text-right text-xs font-bold text-slate-700 uppercase tracking-wider">Grand Total:</td>
                                <td class="p-3 px-4 text-right font-extrabold text-slate-800 text-sm" id="edit-items-grand-total">₱ 0.00</td>
                                <td class="p-3 px-4 text-right font-extrabold text-blue-700 text-sm" id="edit-items-actual-grand-total">₱ 0.00</td>
                                <td class="p-3 px-4 text-right font-extrabold text-slate-800 text-sm" id="edit-items-grand-total-f"><span class="foreign-symbol">$</span> 0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- STEP 3: Review & Preview -->
            <div id="edit-step-3" class="p-8 hidden">
                <h4 class="text-base font-bold text-slate-700 mb-4 uppercase tracking-wider">Review & Preview</h4>
                
                <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4 mb-6">
                    <div class="flex items-start space-x-3">
                        <i data-lucide="info" class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5"></i>
                        <div>
                            <p class="text-xs font-bold text-blue-800">Review Your Changes</p>
                            <p class="text-xs text-blue-600 mt-1">Please review all modifications before saving. Changes will update the purchase order permanently.</p>
                        </div>
                    </div>
                </div>

                <!-- Print Layout Preview (Scaled 70%) -->
                <div style="transform: scale(0.7); transform-origin: top center; width: 142.857%; margin-left: -21.4285%;">
                    @include('partials.purchase.charge-receiving-layout')
                </div>

                <div class="mt-8 flex justify-center space-x-4">
                    <button onclick="enlargeEditPrintLayout()" class="px-6 py-3 bg-slate-600 hover:bg-slate-700 text-white text-xs font-bold rounded-xl shadow-md flex items-center space-x-2 transition-all">
                        <i data-lucide="maximize-2" class="w-4 h-4"></i>
                        <span>ENLARGE PREVIEW</span>
                    </button>
                    <button onclick="printEditChargeReceiving()" class="px-6 py-3 bg-maroon hover:bg-maroon/90 text-white text-xs font-bold rounded-xl shadow-md flex items-center space-x-2 transition-all">
                        <i data-lucide="printer" class="w-4 h-4"></i>
                        <span>PRINT LAYOUT</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Footer (Navigation Buttons) -->
        <div class="bg-slate-50 p-6 border-t border-slate-200 flex justify-between items-center">
            <button id="edit-btn-back" onclick="goToEditStep(window.editState?.currentStep - 1)" class="hidden px-6 py-2.5 bg-white border-2 border-slate-300 hover:border-slate-400 text-slate-700 rounded-xl text-xs font-bold transition-all uppercase tracking-widest flex items-center space-x-2">
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
                <span>Back</span>
            </button>
            <div class="flex-1"></div>
            <div class="flex items-center space-x-3">
                <button onclick="checkAndCloseEditModal()" class="px-6 py-2.5 text-xs font-bold text-slate-400 hover:text-slate-600 transition-all uppercase tracking-widest">Cancel</button>
                <button id="edit-btn-next" onclick="goToEditStep(window.editState?.currentStep + 1)" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition-all uppercase tracking-widest flex items-center space-x-2 shadow-md">
                    <span>Next: Edit Items</span>
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </button>
                <button id="edit-btn-save" onclick="saveEditedPO()" class="hidden px-6 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-xl text-xs font-bold transition-all uppercase tracking-widest flex items-center space-x-2 shadow-md">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>Save Changes</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 9. EDIT CONFIRMATION MODAL -->
<div id="edit-confirm-modal" class="fixed inset-0 z-[350] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-900/80 transition-opacity backdrop-blur-md z-[350]"></div>
    <div class="relative bg-white rounded-3xl text-center p-8 shadow-2xl transform transition-all max-w-sm w-full modal-animate-in mx-4 border-t-8 border-green-500 z-[351]">
        <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-green-100 mb-6">
            <i data-lucide="check-circle-2" class="h-10 w-10 text-green-500"></i>
        </div>
        <h3 class="text-xl font-extrabold text-slate-800 mb-2 tracking-tight uppercase">Save Changes?</h3>
        <p class="text-sm text-slate-500 mb-8 leading-relaxed">Are you sure you want to save all modifications to this purchase order?</p>
        <div class="flex space-x-3">
            <button onclick="toggleModal('edit-confirm-modal', false)" class="flex-1 px-4 py-3 bg-slate-100 text-slate-600 rounded-2xl text-xs font-bold hover:bg-slate-200 transition-all uppercase tracking-widest">Cancel</button>
            <button onclick="confirmSaveEdit()" class="flex-1 px-4 py-3 bg-green-600 text-white rounded-2xl text-xs font-bold hover:bg-green-700 transition-all shadow-lg uppercase tracking-widest">Yes, Save</button>
        </div>
    </div>
</div>

<!-- 10. DISCARD EDIT CHANGES MODAL -->
<div id="discard-edit-modal" class="fixed inset-0 z-[350] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-900/80 transition-opacity backdrop-blur-md z-[350]"></div>
    <div class="relative bg-white rounded-3xl text-center p-8 shadow-2xl transform transition-all max-w-sm w-full modal-animate-in mx-4 border-t-8 border-amber-500 z-[351]">
        <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-amber-100 mb-6">
            <i data-lucide="alert-triangle" class="h-10 w-10 text-amber-500"></i>
        </div>
        <h3 class="text-xl font-extrabold text-slate-800 mb-2 tracking-tight uppercase">Discard Changes?</h3>
        <p class="text-sm text-slate-500 mb-8 leading-relaxed">You have unsaved changes. Are you sure you want to close and discard all modifications?</p>
        <div class="flex space-x-3">
            <button onclick="toggleModal('discard-edit-modal', false)" class="flex-1 px-4 py-3 bg-slate-100 text-slate-600 rounded-2xl text-xs font-bold hover:bg-slate-200 transition-all uppercase tracking-widest">No, Keep Editing</button>
            <button onclick="confirmDiscardEdit()" class="flex-1 px-4 py-3 bg-amber-500 text-white rounded-2xl text-xs font-bold hover:bg-amber-600 transition-all shadow-lg uppercase tracking-widest">Yes, Discard</button>
        </div>
    </div>
</div>

<!-- 8. EDIT HISTORY MODAL (3 STEPS) -->
<div id="edit-history-modal" class="fixed inset-0 z-[300] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="checkAndCloseEditModal()" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm z-[300]"></div>
    <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-6xl w-full modal-animate-in mx-4 flex flex-col max-h-[90vh] z-[301]">
        
        <!-- Modal Header -->
        <div class="bg-blue-600 p-6 flex justify-between items-center text-white border-b-4 border-blue-800">
            <div class="flex items-center space-x-4">
                <div class="p-3 bg-white/10 rounded-2xl border-2 border-blue-800 shadow-sm">
                    <i data-lucide="edit" class="w-6 h-6 text-white"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold uppercase tracking-widest">Edit Purchase Order</h3>
                    <p class="text-xs text-blue-100 mt-1">Modify purchase order details and items</p>
                </div>
            </div>
            <button onclick="checkAndCloseEditModal()" class="text-white/70 hover:text-white transition-colors">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <!-- Step Indicator -->
        <div class="bg-slate-50 p-4 border-b border-slate-200">
            <div class="flex items-center justify-center space-x-2">
                <div id="edit-step-indicator-1" class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold">1</div>
                    <span class="ml-2 text-xs font-bold text-blue-600">Order Info</span>
                </div>
                <div class="w-12 h-0.5 bg-slate-300" id="edit-step-line-1"></div>
                <div id="edit-step-indicator-2" class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-slate-300 text-slate-500 flex items-center justify-center text-xs font-bold">2</div>
                    <span class="ml-2 text-xs font-bold text-slate-400">Edit Items</span>
                </div>
                <div class="w-12 h-0.5 bg-slate-300" id="edit-step-line-2"></div>
                <div id="edit-step-indicator-3" class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-slate-300 text-slate-500 flex items-center justify-center text-xs font-bold">3</div>
                    <span class="ml-2 text-xs font-bold text-slate-400">Review & Save</span>
                </div>
            </div>
        </div>

        <!-- Step Content Container -->
        <div class="flex-1 overflow-y-auto p-6 bg-slate-50/30" style="max-height: calc(90vh - 200px);">
            
            <!-- STEP 1: Order Information -->
            <div id="edit-step-1-content" class="space-y-6">
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                    <h4 class="text-sm font-bold text-slate-700 mb-4 uppercase tracking-wider flex items-center">
                        <i data-lucide="info" class="w-4 h-4 mr-2 text-blue-600"></i>
                        Order Information
                    </h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-2">Supplier Code</label>
                            <input type="text" id="edit-supplier-code" readonly class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50 text-slate-600">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-2">Supplier Name</label>
                            <input type="text" id="edit-supplier-name" readonly class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50 text-slate-600">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-2">Receiving No.</label>
                            <input type="text" id="edit-control-no" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-2">P.N. No.</label>
                            <input type="text" id="edit-pn-no" readonly class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50 text-slate-600">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-2">Currency</label>
                            <select id="edit-currency" onchange="handleEditCurrencyChange(this)" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                <option value="PHP">PHP (₱)</option>
                                <option value="USD">USD ($)</option>
                                <option value="TWD">TWD (NT$)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-2">Conversion Rate</label>
                            <input type="number" id="edit-conv-rate" value="1" step="0.01" onchange="handleEditConversionRateChange(this)" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        </div>
                    </div>
                </div>

                <!-- Supplier Invoice Numbers -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider flex items-center">
                            <i data-lucide="file-text" class="w-4 h-4 mr-2 text-blue-600"></i>
                            Supplier Invoice Numbers
                        </h4>
                        <button onclick="addEditInvoiceInput()" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-bold rounded-lg flex items-center space-x-1 transition-all">
                            <i data-lucide="plus" class="w-3 h-3"></i>
                            <span>Add Invoice</span>
                        </button>
                    </div>
                    <div id="edit-invoice-inputs-container" class="space-y-2">
                        <div class="relative group">
                            <input type="text" class="edit-supplier-invoice-input w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm font-mono focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-slate-800 bg-slate-50/50" placeholder="Invoice #1">
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 2: Edit Items -->
            <div id="edit-step-2-content" class="hidden space-y-6">
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto custom-scrollbar">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-blue-50 border-b-2 border-blue-200">
                                <tr>
                                    <th class="p-3 px-4 text-[10px] font-bold text-slate-600 uppercase">Code</th>
                                    <th class="p-3 px-4 text-[10px] font-bold text-slate-600 uppercase">Part No</th>
                                    <th class="p-3 px-4 text-[10px] font-bold text-slate-600 uppercase min-w-[200px]">Description</th>
                                    <th class="p-3 px-4 text-[10px] font-bold text-slate-600 uppercase text-center">Qty</th>
                                    <th class="p-3 px-4 text-[10px] font-bold text-blue-600 uppercase text-center">Actual Qty</th>
                                    <th class="p-3 px-4 text-[10px] font-bold text-slate-600 uppercase text-center">Unit</th>
                                    <th class="p-3 px-4 text-[10px] font-bold text-slate-600 uppercase">Unit Cost (PHP)</th>
                                    <th class="p-3 px-4 text-[10px] font-bold text-slate-600 uppercase">Unit Cost (Foreign)</th>
                                    <th class="p-3 px-4 text-[10px] font-bold text-slate-600 uppercase text-center">% Disc</th>
                                    <th class="p-3 px-4 text-[10px] font-bold text-slate-600 uppercase text-right">Subtotal (PHP)</th>
                                    <th class="p-3 px-4 text-[10px] font-bold text-blue-600 uppercase text-right">Actual Subtotal</th>
                                    <th class="p-3 px-4 text-[10px] font-bold text-slate-600 uppercase text-right">Foreign Total</th>
                                </tr>
                            </thead>
                            <tbody id="edit-po-items-tbody" class="divide-y divide-slate-100 text-xs">
                                <!-- Items will be rendered here -->
                            </tbody>
                            <tfoot class="bg-slate-50 border-t-2 border-slate-200">
                                <tr>
                                    <td colspan="9" class="p-3 px-4 text-right text-xs font-bold text-slate-600 uppercase">Total Deduction:</td>
                                    <td class="p-3 px-4 text-right text-xs font-bold text-slate-800" id="edit-po-items-total-deduction-php">₱ 0.00</td>
                                    <td class="p-3 px-4"></td>
                                    <td class="p-3 px-4 text-right text-xs font-bold text-blue-600" id="edit-po-items-total-deduction-f"><span class="foreign-symbol">$</span> 0.00</td>
                                </tr>
                                <tr class="bg-blue-50">
                                    <td colspan="9" class="p-3 px-4 text-right text-sm font-extrabold text-slate-700 uppercase">Grand Total:</td>
                                    <td class="p-3 px-4 text-right text-sm font-extrabold text-slate-900" id="edit-po-items-grand-total">₱ 0.00</td>
                                    <td class="p-3 px-4 text-right text-sm font-extrabold text-blue-600" id="edit-po-items-actual-grand-total">₱ 0.00</td>
                                    <td class="p-3 px-4 text-right text-sm font-extrabold text-blue-600" id="edit-po-items-grand-total-f"><span class="foreign-symbol">$</span> 0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Additional Discount -->
                <div class="bg-amber-50 border-2 border-amber-300 rounded-2xl p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <i data-lucide="percent" class="w-5 h-5 text-amber-600"></i>
                            <div>
                                <h4 class="text-sm font-bold text-amber-900">Additional Discount</h4>
                                <p class="text-[10px] text-amber-700">Applied after item discounts</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-4">
                            <div class="flex items-center space-x-2">
                                <input type="number" id="edit-po-addl-disc-percent" value="0" step="0.01" min="0" max="100" oninput="updateEditCalculations()" class="w-24 px-3 py-2 border-2 border-amber-400 rounded-lg text-sm font-bold text-amber-900 text-center focus:ring-2 focus:ring-amber-500" placeholder="0">
                                <span class="text-sm font-bold text-amber-900">%</span>
                            </div>
                            <div class="px-4 py-2 bg-white border-2 border-amber-400 rounded-lg">
                                <span class="text-[10px] text-amber-700 font-bold">Amount:</span>
                                <span id="edit-po-addl-disc-amount" class="ml-2 text-sm font-extrabold text-amber-900">₱ 0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 3: Review & Save -->
            <div id="edit-step-3-content" class="hidden space-y-6">
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                    <h4 class="text-sm font-bold text-slate-700 mb-4 uppercase tracking-wider flex items-center">
                        <i data-lucide="check-square" class="w-4 h-4 mr-2 text-blue-600"></i>
                        Review Changes
                    </h4>
                    
                    <!-- Print Layout Preview (70% scale) -->
                    <div style="transform: scale(0.7); transform-origin: top center; margin-bottom: -200px;">
                        @include('partials.purchase.charge-receiving-layout')
                    </div>

                    <!-- Enlarge Button -->
                    <div class="flex justify-center mt-4">
                        <button onclick="enlargeEditPrintLayout()" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-xl shadow-lg flex items-center space-x-2 transition-all">
                            <i data-lucide="maximize-2" class="w-4 h-4"></i>
                            <span>ENLARGE PREVIEW</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Buttons -->
        <div class="bg-white p-5 border-t border-slate-200 flex justify-between items-center">
            <button onclick="editGoToStep(window.editPoState.currentStep - 1)" id="edit-btn-prev" class="hidden px-6 py-2.5 bg-slate-100 text-slate-600 hover:bg-slate-200 text-xs font-bold rounded-xl transition-all uppercase tracking-widest">
                Previous
            </button>
            <div class="flex-1"></div>
            <div class="flex space-x-3">
                <button onclick="checkAndCloseEditModal()" class="px-6 py-2.5 text-xs font-bold text-slate-400 hover:text-slate-600 transition-all uppercase tracking-widest">Cancel</button>
                <button onclick="editGoToStep(window.editPoState.currentStep + 1)" id="edit-btn-next" class="px-6 py-2.5 bg-blue-600 text-white hover:bg-blue-700 text-xs font-bold rounded-xl transition-all uppercase tracking-widest shadow-md">
                    Next: Edit Items
                </button>
                <button onclick="confirmEditSave()" id="edit-btn-save" class="hidden px-6 py-2.5 bg-green-600 text-white hover:bg-green-700 text-xs font-bold rounded-xl transition-all uppercase tracking-widest shadow-md flex items-center space-x-2">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>Save Changes</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 9. EDIT CONFIRMATION MODAL -->
<div id="edit-confirm-modal" class="fixed inset-0 z-[350] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-900/80 transition-opacity backdrop-blur-md z-[350]"></div>
    <div class="relative bg-white rounded-3xl text-center p-8 shadow-2xl transform transition-all max-w-sm w-full modal-animate-in mx-4 border-t-8 border-green-500 z-[351]">
        <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-green-100 mb-6">
            <i data-lucide="save" class="h-10 w-10 text-green-500"></i>
        </div>
        <h3 class="text-xl font-extrabold text-slate-800 mb-2 tracking-tight uppercase">Save Changes?</h3>
        <p class="text-sm text-slate-500 mb-8 leading-relaxed">Are you sure you want to save the changes to this purchase order?</p>
        <div class="flex space-x-3">
            <button onclick="toggleModal('edit-confirm-modal', false)" class="flex-1 px-4 py-3 bg-slate-100 text-slate-600 rounded-2xl text-xs font-bold hover:bg-slate-200 transition-all uppercase tracking-widest">Cancel</button>
            <button onclick="saveEditChanges()" class="flex-1 px-4 py-3 bg-green-500 text-white rounded-2xl text-xs font-bold hover:bg-green-600 transition-all shadow-lg uppercase tracking-widest">Yes, Save</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
    <script>
        window.purchaseRoutes = {
            process: '{{ route("admin.purchase-order.process") }}',
            updatePO: '{{ route("admin.purchase-order.update") }}',
            itemsUrl: '{{ url("/admin/purchase/purchase-order/items") }}',
            openNotesUrl: '{{ route("admin.purchase.open-notes") }}',
            activeDataUrl: '{{ route("admin.purchase-order.active") }}',
            historyDataUrl: '{{ route("admin.purchase-order.history") }}',
            detailsByNumberUrl: '{{ url("/admin/purchase/purchase-note/details-by-number") }}',
            dashboardDataUrl: '{{ route("admin.purchase-order.dashboard") }}',
            poItemsUrl: '{{ route("admin.purchase-order.po-items", ["poId" => ":poId"]) }}',
            poDetailUrl: '{{ route("admin.purchase-order.detail", ["poId" => ":poId"]) }}',
            forceCloseUrl: '{{ route("admin.purchase-order.force-close.submit", ["purchaseNoteId" => ":purchaseNoteId"]) }}',
            resyncCostsUrl: '{{ route("admin.purchase-order.resync-product-costs", ["poId" => ":poId"]) }}'
        };
    </script>
    <script src="{{ asset('js/Purchase-Order.js') }}?v={{ time() }}"></script>
@endpush

@include('partials.admin.admin_sidebar_navbar')
