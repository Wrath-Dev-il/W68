@extends('partials.special_user.special_sidebar_navbar')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/Sales-Order.css') }}?v={{ time() }}">
@endpush

@section('sales_order_content')
<div id="page-sales-order-root" class="space-y-8 animate-fade-in text-slate-800">

    <!-- HEADER -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-maroon tracking-tight">Sale's Order</h1>
            <p class="text-sm text-slate-500">Manage and process sales orders from active notes.</p>
        </div>
        <div class="flex items-center space-x-3">
            <button onclick="toggleModal('filter-modal', true)" class="px-4 py-2.5 bg-white border border-slate-200 hover:border-maroon-200 hover:bg-slate-50 text-slate-700 hover:text-maroon text-xs font-bold rounded-xl shadow-sm flex items-center space-x-2 transition-all">
                <i data-lucide="sliders-horizontal" class="w-4 h-4 text-slate-500"></i>
                <span>Filter</span>
            </button>
            <button onclick="openOnlineReport()" class="px-4 py-2.5 bg-goldlining-500 hover:bg-goldlining-600 text-maroon-900 text-xs font-bold rounded-xl shadow-sm flex items-center space-x-2 transition-all">
                <i data-lucide="globe" class="w-4 h-4"></i>
                <span>Online Report</span>
            </button>
        </div>
    </div>

    <!-- DASHBOARD CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div onclick="filterActiveByStatus('Open')" class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between hover-card-trigger cursor-pointer transition-all hover:shadow-md hover:border-maroon/30">
            <div class="space-y-1">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Open Notes</p>
                <h3 id="card-open-notes" class="text-2xl font-extrabold text-slate-800">---</h3>
            </div>
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-maroon shadow-inner">
                <i data-lucide="folder-open" class="w-7 h-7 text-gold"></i>
            </div>
        </div>
        <div onclick="filterActiveByStatus('Partial')" class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between hover-card-trigger cursor-pointer transition-all hover:shadow-md hover:border-maroon/30">
            <div class="space-y-1">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Partial Sales Order</p>
                <h3 id="card-partial-po" class="text-2xl font-extrabold text-slate-800">---</h3>
            </div>
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-maroon shadow-inner">
                <i data-lucide="pie-chart" class="w-7 h-7 text-gold"></i>
            </div>
        </div>
        <div onclick="filterActiveByStatus('Closed')" class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between hover-card-trigger cursor-pointer transition-all hover:shadow-md hover:border-maroon/30">
            <div class="space-y-1">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Closed Sales Order</p>
                <h3 id="card-closed-po" class="text-2xl font-extrabold text-slate-800">---</h3>
            </div>
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-maroon shadow-inner">
                <i data-lucide="check-circle" class="w-7 h-7 text-gold"></i>
            </div>
        </div>
        <div onclick="filterActiveByStatus('All')" class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between hover-card-trigger cursor-pointer transition-all hover:shadow-md hover:border-maroon/30">
            <div class="space-y-1">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">TOTAL Sales Order</p>
                <h3 id="card-total-so" class="text-2xl font-extrabold text-slate-800">---</h3>
            </div>
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-maroon shadow-inner">
                <i data-lucide="shopping-bag" class="w-7 h-7 text-gold"></i>
            </div>
        </div>
    </div>

    <!-- TABS -->
    <div class="flex space-x-3 border-b border-slate-200 pb-0">
        <button onclick="switchSalesOrderTab('active')" id="tab-active" class="sales-order-tab active px-6 py-3 text-xs font-bold rounded-t-xl uppercase tracking-widest">Active Notes</button>
        <button onclick="switchSalesOrderTab('history')" id="tab-history" class="sales-order-tab px-6 py-3 text-xs font-bold rounded-t-xl uppercase tracking-widest">Sale's Order History</button>
        <button onclick="switchSalesOrderTab('online-invoices')" id="tab-online-invoices" class="sales-order-tab px-6 py-3 text-xs font-bold rounded-t-xl uppercase tracking-widest">Online Invoices</button>
        <button onclick="switchSalesOrderTab('all')" id="tab-all" class="sales-order-tab px-6 py-3 text-xs font-bold rounded-t-xl uppercase tracking-widest">All Orders</button>
    </div>

    <!-- TAB 1: ACTIVE NOTES -->
    <div id="tab-content-active" class="tab-content">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden text-slate-800">
            <div class="p-5 border-b border-slate-50 flex justify-between items-center bg-slate-50/30">
                <h2 class="font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="list" class="w-4 h-4 text-maroon"></i>
                    Active Notes
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
                                <th class="p-4 px-6 w-12"><input type="checkbox" id="select-all-active" class="rounded border-slate-300 text-maroon focus:ring-maroon"></th>
                                <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">SN NO.</th>
                                <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Customer Name</th>
                                <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Issue Date</th>
                                <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Status</th>
                                <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-right">Invoice Total</th>
                                <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Action</th>
                            </tr>
                            <tr class="bg-slate-50/30 border-b border-slate-100">
                                <th class="p-2 px-5"></th>
                                <th class="p-2 px-5"><input type="text" onkeyup="filterTable('active-notes-tbody', 1, this.value)" placeholder="Search SN..." class="column-search-input"></th>
                                <th class="p-2 px-5"><input type="text" onkeyup="filterTable('active-notes-tbody', 2, this.value)" placeholder="Search Name..." class="column-search-input"></th>
                                <th class="p-2 px-5"><input type="text" onkeyup="filterTable('active-notes-tbody', 3, this.value)" placeholder="Search Date..." class="column-search-input"></th>
                                <th class="p-2 px-5"><input type="text" onkeyup="filterTable('active-notes-tbody', 4, this.value)" placeholder="Search Status..." class="column-search-input"></th>
                                <th class="p-2 px-5"><input type="text" onkeyup="filterTable('active-notes-tbody', 5, this.value)" placeholder="Search Total..." class="column-search-input"></th>
                                <th class="p-2 px-5"></th>
                            </tr>
                        </thead>
                        <tbody id="active-notes-tbody" class="divide-y divide-slate-100 text-sm">
                            <tr><td colspan="7" class="p-4 text-center text-slate-300 italic">Loading active notes...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/30">
                <span id="active-page-info" class="text-[10px] text-slate-400 font-bold">Page 1 of 1</span>
                <div class="flex space-x-2">
                    <button onclick="prevActivePage()" id="active-prev-btn" class="px-3 py-1.5 bg-white border border-slate-200 text-slate-500 rounded-lg text-[10px] font-bold hover:bg-maroon hover:text-white transition-all disabled:opacity-30" disabled>&#8592; Prev</button>
                    <button onclick="nextActivePage()" id="active-next-btn" class="px-3 py-1.5 bg-white border border-slate-200 text-slate-500 rounded-lg text-[10px] font-bold hover:bg-maroon hover:text-white transition-all disabled:opacity-30" disabled>Next &#8594;</button>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 2: SALES ORDER HISTORY -->
    <div id="tab-content-history" class="tab-content hidden">
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
                            <th class="p-4 px-6 w-12"><input type="checkbox" id="select-all-history" class="rounded border-slate-300 text-maroon focus:ring-maroon"></th>
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
                            <th class="p-2 px-5"></th>
                            <th class="p-2 px-5"><input type="text" onkeyup="searchHistoryField(1, this.value)" placeholder="Search Date..." class="column-search-input"></th>
                            <th class="p-2 px-5"><input type="text" onkeyup="searchHistoryField(2, this.value)" placeholder="Search Invoice..." class="column-search-input"></th>
                            <th class="p-2 px-5"><input type="text" onkeyup="searchHistoryField(3, this.value)" placeholder="Search Waybill..." class="column-search-input"></th>
                            <th class="p-2 px-5"><input type="text" onkeyup="searchHistoryField(4, this.value)" placeholder="Search W.Date..." class="column-search-input"></th>
                            <th class="p-2 px-5"><input type="text" onkeyup="searchHistoryField(5, this.value)" placeholder="Search Name..." class="column-search-input"></th>
                            <th class="p-2 px-5"><input type="text" onkeyup="searchHistoryField(6, this.value)" placeholder="Search Amount..." class="column-search-input"></th>
                            <th class="p-2 px-5"><input type="text" onkeyup="searchHistoryField(7, this.value)" placeholder="Search Remarks..." class="column-search-input"></th>
                            <th class="p-2 px-5"></th>
                        </tr>
                    </thead>
                    <tbody id="history-orders-tbody" class="divide-y divide-slate-100 text-sm">
                        <tr><td colspan="9" class="p-4 text-center text-slate-300 italic">Loading history...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/30">
                <div class="flex items-center space-x-3">
                    <span id="history-page-info" class="text-[10px] text-slate-400 font-bold">Page 1 of 1</span>
                    <select onchange="changeHistoryPerPage(this.value)" class="text-[10px] border border-slate-200 rounded-lg px-2 py-1 bg-white text-slate-600 font-bold">
                        <option value="25">25</option>
                        <option value="50" selected>50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div class="flex space-x-2">
                    <button onclick="prevHistoryPage()" id="history-prev-btn" class="px-3 py-1.5 bg-white border border-slate-200 text-slate-500 rounded-lg text-[10px] font-bold hover:bg-maroon hover:text-white transition-all disabled:opacity-30" disabled>&#8592; Prev</button>
                    <button onclick="nextHistoryPage()" id="history-next-btn" class="px-3 py-1.5 bg-white border border-slate-200 text-slate-500 rounded-lg text-[10px] font-bold hover:bg-maroon hover:text-white transition-all disabled:opacity-30" disabled>Next &#8594;</button>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 3: ONLINE INVOICES -->
    <div id="tab-content-online-invoices" class="tab-content hidden">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden text-slate-800">
            <div class="p-5 border-b border-slate-50 flex justify-between items-center bg-slate-50/30">
                <h2 class="font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="receipt" class="w-4 h-4 text-maroon"></i>
                    Online Invoices
                </h2>
            </div>
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50/80 border-b border-slate-100">
                        <tr>
                            <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Date Created</th>
                            <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Invoice No.</th>
                            <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Sales Notes</th>
                            <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Customer</th>
                            <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Items</th>
                            <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-right">Total Amount</th>
                            <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Created By</th>
                            <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Action</th>
                        </tr>
                        <tr class="bg-slate-50/30 border-b border-slate-100">
                            <th class="p-2 px-5"><input type="text" onkeyup="searchOiField(0, this.value)" placeholder="Search Date..." class="column-search-input"></th>
                            <th class="p-2 px-5"><input type="text" onkeyup="searchOiField(1, this.value)" placeholder="Search Invoice..." class="column-search-input"></th>
                            <th class="p-2 px-5"><input type="text" onkeyup="searchOiField(2, this.value)" placeholder="Search SN..." class="column-search-input"></th>
                            <th class="p-2 px-5"><input type="text" onkeyup="searchOiField(3, this.value)" placeholder="Search Customer..." class="column-search-input"></th>
                            <th class="p-2 px-5"></th>
                            <th class="p-2 px-5"></th>
                            <th class="p-2 px-5"><input type="text" onkeyup="searchOiField(6, this.value)" placeholder="Search Creator..." class="column-search-input"></th>
                            <th class="p-2 px-5"></th>
                        </tr>
                    </thead>
                    <tbody id="online-invoices-tbody" class="divide-y divide-slate-100 text-sm">
                        <tr><td colspan="8" class="p-4 text-center text-slate-300 italic">Loading online invoices...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/30">
                <div class="flex items-center space-x-3">
                    <span id="oi-page-info" class="text-[10px] text-slate-400 font-bold">Page 1 of 1</span>
                    <select onchange="changeOiPerPage(this.value)" class="text-[10px] border border-slate-200 rounded-lg px-2 py-1 bg-white text-slate-600 font-bold">
                        <option value="25">25</option>
                        <option value="50" selected>50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div class="flex space-x-2">
                    <button onclick="prevOiPage()" id="oi-prev-btn" class="px-3 py-1.5 bg-white border border-slate-200 text-slate-500 rounded-lg text-[10px] font-bold hover:bg-maroon hover:text-white transition-all disabled:opacity-30" disabled>&#8592; Prev</button>
                    <button onclick="nextOiPage()" id="oi-next-btn" class="px-3 py-1.5 bg-white border border-slate-200 text-slate-500 rounded-lg text-[10px] font-bold hover:bg-maroon hover:text-white transition-all disabled:opacity-30" disabled>Next &#8594;</button>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 4: ALL ORDERS (Dashboard filtered) -->
    <div id="tab-content-all" class="tab-content hidden">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden text-slate-800">
            <div class="p-5 border-b border-slate-50 flex justify-between items-center bg-slate-50/30">
                <h2 class="font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="layers" class="w-4 h-4 text-maroon"></i>
                    All Orders
                </h2>
                <div id="all-orders-filter-badge" class="text-[10px] text-maroon font-bold uppercase tracking-widest bg-maroon/5 px-3 py-1 rounded-full">Showing: All</div>
            </div>
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50/80 border-b border-slate-100">
                        <tr>
                            <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">SN NO.</th>
                            <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Customer Name</th>
                            <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Issue Date</th>
                            <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Salesman</th>
                            <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-right">Total Amount</th>
                            <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Status</th>
                            <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Action</th>
                        </tr>
                        <tr class="bg-slate-50/30 border-b border-slate-100">
                            <th class="p-2 px-5"><input type="text" onkeyup="searchAllOrdersField(0, this.value)" placeholder="Search SN..." class="column-search-input"></th>
                            <th class="p-2 px-5"><input type="text" onkeyup="searchAllOrdersField(1, this.value)" placeholder="Search Customer..." class="column-search-input"></th>
                            <th class="p-2 px-5"><input type="text" onkeyup="searchAllOrdersField(2, this.value)" placeholder="Search Date..." class="column-search-input"></th>
                            <th class="p-2 px-5"><input type="text" onkeyup="searchAllOrdersField(3, this.value)" placeholder="Search Salesman..." class="column-search-input"></th>
                            <th class="p-2 px-5"></th>
                            <th class="p-2 px-5"><input type="text" onkeyup="searchAllOrdersField(5, this.value)" placeholder="Search Status..." class="column-search-input"></th>
                            <th class="p-2 px-5"></th>
                        </tr>
                    </thead>
                    <tbody id="all-orders-tbody" class="divide-y divide-slate-100 text-sm">
                        <tr><td colspan="7" class="p-4 text-center text-slate-300 italic">Loading orders...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/30">
                <div class="flex items-center space-x-3">
                    <span id="all-orders-page-info" class="text-[10px] text-slate-400 font-bold">Page 1 of 1</span>
                    <select onchange="changeAllOrdersPerPage(this.value)" class="text-[10px] border border-slate-200 rounded-lg px-2 py-1 bg-white text-slate-600 font-bold">
                        <option value="25">25</option>
                        <option value="50" selected>50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div class="flex space-x-2">
                    <button onclick="prevAllOrdersPage()" id="all-orders-prev-btn" class="px-3 py-1.5 bg-white border border-slate-200 text-slate-500 rounded-lg text-[10px] font-bold hover:bg-maroon hover:text-white transition-all disabled:opacity-30" disabled>&#8592; Prev</button>
                    <button onclick="nextAllOrdersPage()" id="all-orders-next-btn" class="px-3 py-1.5 bg-white border border-slate-200 text-slate-500 rounded-lg text-[10px] font-bold hover:bg-maroon hover:text-white transition-all disabled:opacity-30" disabled>Next &#8594;</button>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ==================== MODALS ==================== -->

<!-- VIEW SALES ORDER DETAILS MODAL -->
<div id="view-sales-order-modal" class="fixed inset-0 z-[300] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('view-sales-order-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
    <div style="width:96vw;max-width:96vw;" class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-7xl w-full modal-animate-in mx-4 flex flex-col max-h-[90vh]">
        <div class="bg-maroon p-5 flex justify-between items-center text-white">
            <div class="flex items-center space-x-3">
                <div class="p-2 bg-white/10 rounded-2xl">
                    <i data-lucide="file-text" class="w-5 h-5 text-gold"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold tracking-tight">Sales Order Details</h3>
                    <p class="text-[9px] text-white/60 uppercase tracking-widest font-bold">View order information</p>
                </div>
            </div>
            <button onclick="toggleModal('view-sales-order-modal', false)" class="text-white/70 hover:text-white transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <div class="p-6 overflow-y-auto">
            <div class="grid grid-cols-2 gap-4 mb-5">
                <div class="bg-slate-50/50 p-4 rounded-xl border border-slate-100">
                    <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mb-1">SN No.</p>
                    <p id="view-so-sn-no" class="text-sm font-bold text-maroon">---</p>
                </div>
                <div class="bg-slate-50/50 p-4 rounded-xl border border-slate-100">
                    <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mb-1">Customer Name</p>
                    <p id="view-so-customer" class="text-sm font-bold text-slate-800">---</p>
                </div>
                <div class="bg-slate-50/50 p-4 rounded-xl border border-slate-100">
                    <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mb-1">Issue Date</p>
                    <p id="view-so-date" class="text-sm font-bold text-slate-800">---</p>
                </div>
                <div class="bg-slate-50/50 p-4 rounded-xl border border-slate-100">
                    <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mb-1">Salesman</p>
                    <p id="view-so-salesman" class="text-sm font-bold text-slate-800">---</p>
                </div>
                <div class="bg-slate-50/50 p-4 rounded-xl border border-slate-100">
                    <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mb-1">Total Amount</p>
                    <p id="view-so-total" class="text-sm font-bold text-maroon">---</p>
                </div>
                <div class="bg-slate-50/50 p-4 rounded-xl border border-slate-100">
                    <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mb-1">Status</p>
                    <div id="view-so-status" class="text-sm">---</div>
                </div>
                <div class="bg-slate-50/50 p-4 rounded-xl border border-slate-100">
                    <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mb-1">Rush</p>
                    <p id="view-so-rush" class="text-sm font-bold text-slate-800">---</p>
                </div>
            </div>
            <h4 class="font-bold text-slate-800 text-xs uppercase tracking-widest mb-3 flex items-center gap-2">
                <i data-lucide="package" class="w-4 h-4 text-maroon"></i> Order Items
            </h4>
            <div class="overflow-x-auto border border-slate-200 rounded-xl">
                <table class="w-full text-left">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="p-3 px-4 text-[9px] font-bold text-slate-500 uppercase tracking-widest" style="min-width:150px;width:150px;white-space:normal;word-break:break-word;overflow-wrap:anywhere;">Item Code</th>
                            <th class="p-3 px-4 text-[9px] font-bold text-slate-500 uppercase tracking-widest" style="min-width:150px;width:150px;white-space:normal;word-break:break-word;overflow-wrap:anywhere;">Price Code</th>
                            <th class="p-3 px-4 text-[9px] font-bold text-slate-500 uppercase tracking-widest">Description</th>
                            <th class="p-3 px-4 text-[9px] font-bold text-slate-500 uppercase tracking-widest text-center">QTY</th>
                            <th class="p-3 px-4 text-[9px] font-bold text-slate-500 uppercase tracking-widest text-center">UOM</th>
                            <th class="p-3 px-4 text-[9px] font-bold text-slate-500 uppercase tracking-widest text-right">Unit Price</th>
                            <th class="p-3 px-4 text-[9px] font-bold text-slate-500 uppercase tracking-widest text-center">Disc</th>
                            <th class="p-3 px-4 text-[9px] font-bold text-slate-500 uppercase tracking-widest text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="view-so-items-tbody" class="divide-y divide-slate-100">
                        <tr><td colspan="8" class="p-4 text-center text-slate-300 italic">Loading items...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="p-4 border-t border-slate-100 flex justify-end bg-slate-50/30">
            <button onclick="toggleModal('view-sales-order-modal', false)" class="px-5 py-2 bg-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-300 transition-all">Close</button>
        </div>
    </div>
</div>

<!-- PROCEED MODAL (3-STEP) -->
<div id="proceed-order-modal" class="fixed inset-0 z-[400] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('proceed-order-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
    <div style="width:96vw;max-width:96vw;" class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-7xl w-full modal-animate-in mx-4 flex flex-col max-h-[95vh]">

        <!-- Modal Header -->
        <div class="bg-maroon p-6 flex justify-between items-center text-white border-b-4 border-gold">
            <div class="flex items-center space-x-4">
                <div class="p-3 bg-white/10 rounded-2xl border-2 border-gold shadow-sm">
                    <i data-lucide="file-symlink" class="w-6 h-6 text-gold"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold tracking-tight">Sales Order Processing</h3>
                    <p class="text-[10px] text-white/60 uppercase tracking-widest font-bold">Proceed sales note to order</p>
                </div>
            </div>
            <button onclick="toggleModal('proceed-order-modal', false)" class="text-white/70 hover:text-white transition-colors">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <!-- Step Progress -->
        <div class="px-6 pt-6 pb-4 bg-slate-50 border-b border-slate-100">
            <div class="flex items-center justify-center space-x-6">
                <div class="flex items-center space-x-2">
                    <div id="pstep-indicator-1" class="step-indicator active">1</div>
                    <span id="pstep-label-1" class="text-[10px] font-bold text-maroon uppercase tracking-widest">Invoice Info</span>
                </div>
                <div class="w-16 h-0.5 bg-slate-200 relative">
                    <div id="pstep-progress-1" class="absolute top-0 left-0 h-full bg-gold transition-all duration-500" style="width:0%"></div>
                </div>
                <div class="flex items-center space-x-2">
                    <div id="pstep-indicator-2" class="step-indicator pending">2</div>
                    <span id="pstep-label-2" class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Item List</span>
                </div>
                <div class="w-16 h-0.5 bg-slate-200 relative">
                    <div id="pstep-progress-2" class="absolute top-0 left-0 h-full bg-gold transition-all duration-500" style="width:0%"></div>
                </div>
                <div class="flex items-center space-x-2">
                    <div id="pstep-indicator-3" class="step-indicator pending">3</div>
                    <span id="pstep-label-3" class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Review</span>
                </div>
            </div>
        </div>

        <!-- Step 1: Invoice Info -->
        <div id="proceed-step-1" class="proceed-step-content flex flex-col md:flex-row gap-8 p-8 overflow-y-auto flex-1 min-h-0">
            <div class="w-full md:w-[30%] space-y-4">
                <div class="p-6 bg-maroon rounded-3xl shadow-xl text-white space-y-6 relative overflow-hidden">
                    <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 bg-gold/10 rounded-full blur-2xl"></div>
                    <h4 class="text-sm font-bold uppercase tracking-widest text-gold border-b border-white/10 pb-3">Order Info</h4>
                    <div class="space-y-4">
                        <div>
                            <p class="text-[9px] uppercase text-white/50 font-bold mb-1">SN NO.</p>
                            <p id="p-sn-no" class="text-xs font-mono font-bold tracking-wider">---</p>
                        </div>
                        <div>
                            <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Customer Name</p>
                            <p id="p-customer-name" class="text-sm font-bold text-gold">---</p>
                        </div>
                        <div>
                            <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Issue Date</p>
                            <p id="p-issue-date" class="text-xs font-bold">---</p>
                        </div>
                        <div>
                            <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Grand Total</p>
                            <p id="p-grand-total" class="text-xs font-bold">---</p>
                        </div>
                        <div>
                            <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Net Total</p>
                            <p id="p-net-total" class="text-xs font-bold text-gold">---</p>
                        </div>
                        <div>
                            <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Note Type</p>
                            <p id="p-note-type" class="text-xs font-bold">Sale's Order</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span id="p-rush-badge" class="px-3 py-1 bg-white/10 text-gold text-[9px] font-bold rounded-full uppercase tracking-widest hidden">Rush</span>
                            <span id="p-note-status" class="px-3 py-1 bg-white/10 text-white text-[9px] font-bold rounded-full uppercase tracking-widest">---</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="w-full md:w-[70%] space-y-6">
                <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="p-4 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-widest">Invoicing</h4>
                        <button onclick="addInvoiceInput()" class="px-3 py-1.5 bg-maroon text-white text-[9px] font-bold rounded-lg hover:bg-maroon-800 transition-all flex items-center space-x-1">
                            <i data-lucide="plus" class="w-3 h-3 text-gold"></i>
                            <span>Add Invoice No</span>
                        </button>
                    </div>
                    <div class="p-6 space-y-4" id="invoice-inputs-container">
                        <div class="invoice-row flex items-center space-x-3">
                            <div class="flex-1">
                                <p class="text-[9px] text-slate-400 font-bold mb-1 ml-1 uppercase">Invoice No.</p>
                                <input type="text" id="proceed-invoice-number" class="invoice-input w-full px-3 py-2.5 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-maroon/20 outline-none transition-all" placeholder="Enter invoice number...">

                            </div>
                            <button onclick="this.closest('.invoice-row').remove()" class="mt-5 p-2 bg-red-50 text-red-400 hover:bg-red-100 hover:text-red-600 rounded-lg transition-all">
                                <i data-lucide="x" class="w-4 h-4"></i>
                            </button>
                        </div>
                        <div class="pt-2 border-t border-slate-100">
                            <p class="text-[9px] text-slate-400 font-bold mb-2 ml-1 uppercase">Sales Order Date <span class="text-red-500">*</span></p>
                            <input type="date" id="proceed-order-date" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-maroon/20 outline-none transition-all" required>
                            <p class="text-[9px] text-slate-400 mt-2 ml-1">This date belongs to the Sales Order and is used by both Sales Order and Sales Invoice print.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 2: Item List -->
        <div id="proceed-step-2" class="proceed-step-content hidden flex flex-col gap-6 p-8 overflow-y-auto flex-1 min-h-0">
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm flex flex-col min-h-0">
                <div class="p-4 bg-slate-50 border-b border-slate-100 flex justify-between items-center flex-shrink-0">
                    <h4 class="text-xs font-bold text-slate-800 uppercase tracking-widest">Item List</h4>
                    <span class="px-3 py-1 bg-maroon/10 text-maroon text-[9px] font-bold rounded-full uppercase">Additional QTY</span>
                </div>
                    <div class="overflow-auto custom-scrollbar flex-1 min-h-0">
                        <table class="w-full text-left border-collapse">
                            <thead class="sticky top-0 bg-slate-50 z-10">
                                <tr class="text-[9px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100">
                                    <th class="p-4 text-center w-10"><input type="checkbox" id="select-all-items" class="accent-maroon cursor-pointer" checked></th>
                                    <th class="p-4" style="min-width:150px;width:150px;white-space:normal;word-break:break-word;overflow-wrap:anywhere;">Item Code</th>
                                    <th class="p-4" style="min-width:150px;width:150px;white-space:normal;word-break:break-word;overflow-wrap:anywhere;">Price Code</th>
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
                                <th class="p-2 px-3"><input type="text" onkeyup="filterTable('proceed-items-tbody', 1, this.value)" placeholder="Code..." class="column-search-input"></th>
                                <th class="p-2 px-3"><input type="text" onkeyup="filterTable('proceed-items-tbody', 2, this.value)" placeholder="Price..." class="column-search-input"></th>
                                <th class="p-2 px-3"><input type="text" onkeyup="filterTable('proceed-items-tbody', 3, this.value)" placeholder="Desc..." class="column-search-input"></th>
                                <th class="p-2 px-3"><input type="text" onkeyup="filterTable('proceed-items-tbody', 4, this.value)" placeholder="QTY..." class="column-search-input text-center"></th>
                                <th class="p-2 px-3"><input type="text" onkeyup="filterTable('proceed-items-tbody', 5, this.value)" placeholder="Act..." class="column-search-input text-center"></th>
                                <th class="p-2 px-3"><input type="text" onkeyup="filterTable('proceed-items-tbody', 6, this.value)" placeholder="Add..." class="column-search-input text-center"></th>
                                <th class="p-2 px-3"><input type="text" onkeyup="filterTable('proceed-items-tbody', 7, this.value)" placeholder="Unit..." class="column-search-input text-center"></th>
                                <th class="p-2 px-3"><input type="text" onkeyup="filterTable('proceed-items-tbody', 8, this.value)" placeholder="Price..." class="column-search-input text-right"></th>
                                <th class="p-2 px-3"><input type="text" onkeyup="filterTable('proceed-items-tbody', 9, this.value)" placeholder="Disc..." class="column-search-input text-center"></th>
                                <th class="p-2 px-3"><input type="text" onkeyup="filterTable('proceed-items-tbody', 10, this.value)" placeholder="SubTotal..." class="column-search-input text-right"></th>
                                <th class="p-2 px-3"><input type="text" onkeyup="filterTable('proceed-items-tbody', 11, this.value)" placeholder="Notes..." class="column-search-input"></th>
                            </tr>
                        </thead>
                        <tbody id="proceed-items-tbody" class="text-xs divide-y divide-slate-50">
                            <tr><td colspan="12" class="p-4 text-center text-slate-300 italic">No items loaded.</td></tr>
                        </tbody>
                    </table>
                    </div>
            </div>
            <div class="flex flex-col xl:flex-row xl:items-center xl:justify-end gap-3 px-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="min-w-[190px] bg-slate-50 border border-slate-200 rounded-xl px-5 py-3">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Gross Total</p>
                        <p id="proceed-step2-gross-total" class="mt-1 text-base font-extrabold text-slate-800">₱ 0.00</p>
                    </div>
                    <div class="min-w-[190px] bg-maroon/5 border border-maroon/20 rounded-xl px-5 py-3">
                        <p class="text-[10px] font-bold text-maroon/60 uppercase tracking-widest">Net Total (PHP)</p>
                        <p id="proceed-step2-net-total" class="mt-1 text-base font-extrabold text-maroon">₱ 0.00</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3 bg-amber-50 border border-amber-200 rounded-xl px-5 py-3">
                    <i data-lucide="percent" class="w-5 h-5 text-amber-600"></i>
                    <label class="text-[10px] font-bold text-amber-700 uppercase tracking-widest">Additional Discount (%)</label>
                    <input type="number" id="proceed-addl-discount" value="0" min="0" max="100" step="0.01" class="w-20 px-3 py-2 border border-amber-300 rounded-lg text-sm text-center font-bold outline-none focus:ring-2 focus:ring-amber-300 bg-white">
                </div>
            </div>
        </div>

        <!-- Step 3: Review -->
        <div id="proceed-step-3" class="proceed-step-content hidden flex flex-col md:flex-row gap-8 p-8 overflow-y-auto flex-1 min-h-0">
            <div class="w-full md:w-[30%] space-y-4">
                <div class="p-6 bg-maroon rounded-3xl shadow-xl text-white space-y-6 relative overflow-hidden">
                    <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 bg-gold/10 rounded-full blur-2xl"></div>
                    <h4 class="text-sm font-bold uppercase tracking-widest text-gold border-b border-white/10 pb-3">Note Invoicing Info</h4>
                    <div class="space-y-4">
                        <div>
                            <p class="text-[9px] uppercase text-white/50 font-bold mb-1">SN NO.</p>
                            <p id="rev-p-sn-no" class="text-xs font-mono font-bold tracking-wider">---</p>
                        </div>
                        <div>
                            <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Customer Name</p>
                            <p id="rev-p-customer" class="text-sm font-bold text-gold">---</p>
                        </div>
                        <div>
                            <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Invoice No(s)</p>
                            <p id="rev-p-invoices" class="text-xs font-bold">---</p>
                        </div>
                        <div>
                            <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Issue Date</p>
                            <p id="rev-p-date" class="text-xs font-bold">---</p>
                        </div>
                        <div>
                            <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Grand Total</p>
                            <p id="rev-p-grand" class="text-xs font-bold">---</p>
                        </div>
                        <div>
                            <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Net Total</p>
                            <p id="rev-p-net" class="text-xs font-bold text-gold">---</p>
                        </div>
                        <div>
                            <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Note Type</p>
                            <p id="rev-p-type" class="text-xs font-bold">Sale's Order</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span id="rev-p-rush" class="px-3 py-1 bg-white/10 text-gold text-[9px] font-bold rounded-full uppercase tracking-widest hidden">Rush</span>
                            <span id="rev-p-status" class="px-3 py-1 bg-white/10 text-white text-[9px] font-bold rounded-full uppercase tracking-widest">---</span>
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
                                    <th class="p-4" style="min-width:150px;width:150px;white-space:normal;word-break:break-word;overflow-wrap:anywhere;">Item Code</th>
                                    <th class="p-4" style="min-width:150px;width:150px;white-space:normal;word-break:break-word;overflow-wrap:anywhere;">Price Code</th>
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
                            </thead>
                            <tbody id="review-proceed-tbody" class="text-xs divide-y divide-slate-50">
                                <tr><td colspan="12" class="p-4 text-center text-slate-300 italic">No items.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-6 bg-slate-50 border-t border-slate-100 flex justify-between items-center">
                        <div class="flex items-center space-x-8">
                            <div>
                                <p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Gross Total</p>
                                <h3 id="rev-p-gross" class="text-lg font-bold text-slate-600 tracking-tight">₱ 0.00</h3>
                            </div>
                            <div class="text-slate-300 text-xl font-light">-</div>
                            <div>
                                <p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Total Discount</p>
                                <h3 id="rev-p-discount" class="text-lg font-bold text-emerald-600 tracking-tight">₱ 0.00</h3>
                            </div>
                            <div class="text-slate-300 text-xl font-light">=</div>
                            <div>
                                <p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Net Total (PHP)</p>
                                <h3 id="rev-p-net-total" class="text-2xl font-extrabold text-maroon tracking-tight">₱ 0.00</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Footer (Fixed) -->
        <div class="p-6 bg-slate-50 border-t border-slate-100 flex justify-between items-center">
            <div>
                <button id="p-btn-back" onclick="proceedGoToStep(currentStep - 1)" class="hidden px-6 py-2.5 text-xs font-bold text-slate-400 hover:text-maroon transition-all uppercase tracking-widest flex items-center space-x-2">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    <span>Back</span>
                </button>
            </div>
            <div class="flex space-x-3">
                <button onclick="toggleModal('proceed-order-modal', false)" class="px-6 py-2.5 text-xs font-bold text-slate-400 hover:text-slate-600 transition-all uppercase tracking-widest">Cancel</button>

                <button id="sales-force-close-note-btn" onclick="openSalesForceCloseModal()" class="hidden px-6 py-2.5 bg-red-600 text-white rounded-xl text-xs font-bold hover:bg-red-700 transition-all shadow-lg flex items-center space-x-2 uppercase tracking-widest">
                    <i data-lucide="ban" class="w-4 h-4"></i>
                    <span>Force Close Note</span>
                </button>

                <button id="p-btn-next" onclick="proceedGoToStep(2)" class="px-8 py-2.5 bg-maroon text-white rounded-xl text-xs font-bold hover:bg-maroon-800 transition-all shadow-lg flex items-center space-x-2 uppercase tracking-widest">
                    <span id="p-next-text">Next Step</span>
                    <i data-lucide="chevron-right" class="w-4 h-4 text-gold"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- PRINT RECEIPT TYPE MODAL -->
<div id="print-receipt-modal" class="fixed inset-0 z-[450] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('print-receipt-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-md sm:w-full modal-animate-in mx-4">
        <div class="bg-maroon p-5 flex justify-between items-center text-white border-b-4 border-gold">
            <h3 class="text-sm font-bold uppercase tracking-widest">Print Receipt</h3>
            <button onclick="toggleModal('print-receipt-modal', false)" class="text-white/70 hover:text-white transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <p class="text-xs text-slate-500">Select receipt type:</p>
            <label class="flex items-center space-x-3 p-4 border-2 border-slate-200 rounded-xl cursor-pointer hover:border-maroon transition-all">
                <input type="radio" name="receipt-print-type" value="invoice" class="accent-maroon">
                <div>
                    <span class="text-sm font-bold text-slate-700">Sale's Invoice</span>
                    <p class="text-[10px] text-slate-400">With VAT</p>
                </div>
            </label>
            <label class="flex items-center space-x-3 p-4 border-2 border-slate-200 rounded-xl cursor-pointer hover:border-maroon transition-all">
                <input type="radio" name="receipt-print-type" value="order" checked class="accent-maroon">
                <div>
                    <span class="text-sm font-bold text-slate-700">Sale's Order</span>
                    <p class="text-[10px] text-slate-400">Without VAT</p>
                </div>
            </label>
        </div>
        <div class="p-4 border-t border-slate-100 flex justify-end space-x-3">
            <button onclick="toggleModal('print-receipt-modal', false)" class="px-4 py-2.5 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest">Cancel</button>
            <button onclick="submitPrintReceipt()" class="px-6 py-2.5 bg-maroon text-white text-xs font-bold rounded-xl hover:bg-maroon-800 transition-all uppercase tracking-widest shadow-lg shadow-maroon/20 flex items-center space-x-2">
                <i data-lucide="printer" class="w-4 h-4"></i>
                <span>Print</span>
            </button>
        </div>
    </div>
</div>

<!-- CONFIRM ORDER MODAL -->
<div id="confirm-order-modal" class="fixed inset-0 z-[500] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('confirm-order-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-sm sm:w-full modal-animate-in mx-4">
        <div class="p-6 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-amber-50 mb-4">
                <i data-lucide="alert-triangle" class="h-6 w-6 text-amber-600"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-800 mb-2 tracking-tight uppercase">Confirm Order</h3>
            <p class="text-xs text-slate-500 mb-4 leading-relaxed">Are you sure you want to proceed this Sales Order? This action will record the transaction.</p>
        </div>
        <div class="bg-slate-50 px-6 py-4 flex flex-col sm:flex-row-reverse gap-2">
            <button onclick="finalizeSalesOrder()" class="w-full px-4 py-2 bg-maroon hover:bg-maroon-800 text-white text-xs font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg shadow-maroon/20">Yes, Confirm</button>
            <button onclick="toggleModal('confirm-order-modal', false)" class="w-full px-4 py-2 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest">No, Review</button>
        </div>
    </div>
</div>

<!-- FORCE CLOSE SALES NOTE CONFIRMATION MODAL -->
<div id="force-close-sales-note-modal" class="fixed inset-0 z-[1000] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="closeSalesForceCloseModal()" class="fixed inset-0 bg-slate-900/70 transition-opacity backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-lg w-full modal-animate-in mx-4">
        <div class="bg-red-600 p-6 text-white">
            <div class="flex items-center space-x-3">
                <div class="p-2.5 bg-white/10 rounded-xl">
                    <i data-lucide="triangle-alert" class="w-6 h-6"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold tracking-tight uppercase">Force Close Sales Note?</h3>
                    <p class="text-[10px] text-white/70 uppercase tracking-widest font-bold mt-1">Partial notes only</p>
                </div>
            </div>
        </div>
        <div class="p-6 space-y-5">
            <div class="rounded-2xl border border-red-100 bg-red-50 p-4 text-xs text-red-700 leading-relaxed">
                This will permanently remove every <strong>remaining unserved quantity</strong> from the Sales Note. Already invoiced items in <strong>Sales Orders and Sales Order Items will be preserved</strong>. The Sales Note status will change from <strong>Partial</strong> to <strong>Closed</strong>.
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                    <p class="text-[9px] font-bold uppercase tracking-widest text-slate-400">Sales Note</p>
                    <p id="sales-fc-note-number" class="mt-1 text-sm font-bold text-maroon">---</p>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                    <p class="text-[9px] font-bold uppercase tracking-widest text-slate-400">Customer</p>
                    <p id="sales-fc-customer" class="mt-1 text-sm font-bold text-slate-700">---</p>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                    <p class="text-[9px] font-bold uppercase tracking-widest text-slate-400">Remaining Item Lines</p>
                    <p id="sales-fc-item-count" class="mt-1 text-sm font-bold text-red-600">0</p>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                    <p class="text-[9px] font-bold uppercase tracking-widest text-slate-400">Remaining QTY Removed</p>
                    <p id="sales-fc-qty" class="mt-1 text-sm font-bold text-red-600">0</p>
                </div>
            </div>
            <p class="text-[10px] text-slate-400 leading-relaxed">This action cannot be undone. No invoiced Sales Order record or invoiced Sales Order Item will be deleted by this action.</p>
        </div>
        <div class="p-5 bg-slate-50 border-t border-slate-100 flex justify-end gap-3">
            <button type="button" onclick="closeSalesForceCloseModal()" class="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-100 transition-all uppercase tracking-widest">Cancel</button>
            <button type="button" id="sales-fc-confirm-btn" onclick="confirmSalesForceCloseNote()" class="px-6 py-2.5 bg-red-600 text-white text-xs font-bold rounded-xl hover:bg-red-700 transition-all shadow-lg uppercase tracking-widest flex items-center gap-2">
                <i data-lucide="ban" class="w-4 h-4"></i>
                <span>Yes, Force Close</span>
            </button>
        </div>
    </div>
</div>

<!-- SUCCESS MODAL -->
<div id="success-order-modal" class="fixed inset-0 z-[500] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('success-order-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-sm sm:w-full modal-animate-in mx-4">
        <div class="p-8 text-center">
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-emerald-50 mb-6">
                <i data-lucide="check-circle" class="h-10 w-10 text-emerald-500"></i>
            </div>
            <h3 class="text-xl font-bold text-slate-800 mb-2">Success!</h3>
            <p class="text-xs text-slate-500 leading-relaxed">The Sales Order has been successfully processed.</p>
        </div>
        <div class="p-6 pt-0">
            <button onclick="toggleModal('success-order-modal', false); reloadAllSalesOrderTabs()" class="w-full px-4 py-3 bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg shadow-emerald-200">Great, Continue</button>
        </div>
    </div>
</div>

<!-- VIEW ORDER MODAL -->
<div id="view-order-modal" class="fixed inset-0 z-[300] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('view-order-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
    <div style="width:96vw;max-width:96vw;" class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-7xl w-full modal-animate-in mx-4 flex flex-col max-h-[95vh]">
        <div class="bg-maroon p-6 flex justify-between items-center text-white border-b-4 border-gold">
            <div class="flex items-center space-x-4">
                <div class="p-3 bg-white/10 rounded-2xl border-2 border-gold shadow-sm">
                    <i data-lucide="eye" class="w-6 h-6 text-gold"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold tracking-tight">Sales Order Details</h3>
                    <p id="view-order-label" class="text-[10px] text-white/60 uppercase tracking-widest font-bold">Transaction: ---</p>
                </div>
            </div>
            <button onclick="toggleModal('view-order-modal', false)" class="text-white/70 hover:text-white transition-colors">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        <div class="flex flex-col md:flex-row h-full gap-8 p-8 overflow-y-auto">
            <div class="w-full md:w-[30%] space-y-4">
                <!-- View Sub Tabs -->
                <div class="flex space-x-1 bg-slate-100 p-1 rounded-xl">
                    <button onclick="switchViewSubTab('view-details', this)" class="view-sub-tab active flex-1 px-3 py-2 text-[9px] font-bold rounded-lg uppercase tracking-widest bg-white text-maroon shadow-sm">History Details</button>
                    <button onclick="switchViewSubTab('view-invoicing', this)" class="view-sub-tab flex-1 px-3 py-2 text-[9px] font-bold rounded-lg uppercase tracking-widest text-slate-500 hover:text-maroon">Invoice Info</button>
                </div>
                <div id="view-tab-view-details" class="view-sub-content">
                    <div class="p-6 bg-maroon rounded-3xl shadow-xl text-white space-y-6 relative overflow-hidden">
                        <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 bg-gold/10 rounded-full blur-2xl"></div>
                        <h4 class="text-sm font-bold uppercase tracking-widest text-gold border-b border-white/10 pb-3">History Details</h4>
                        <div class="space-y-4">
                            <div>
                                <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Date Issue</p>
                                <p id="view-h-date" class="text-xs font-bold">---</p>
                            </div>
                            <div>
                                <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Invoice No.</p>
                                <p id="view-h-invoice" class="text-xs font-bold text-gold">---</p>
                            </div>
                            <div>
                                <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Waybill No</p>
                                <p id="view-h-waybill" class="text-xs font-bold">---</p>
                            </div>
                            <div>
                                <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Waybill Date</p>
                                <p id="view-h-waybill-date" class="text-xs font-bold">---</p>
                            </div>
                            <div>
                                <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Customer Name</p>
                                <p id="view-h-name" class="text-sm font-bold text-gold">---</p>
                            </div>
                            <div>
                                <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Total Amount</p>
                                <p id="view-h-total" class="text-xs font-bold">---</p>
                            </div>
                            <div>
                                <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Remarks</p>
                                <p id="view-h-remarks" class="text-[10px] text-white/70 italic">---</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="view-tab-view-invoicing" class="view-sub-content hidden">
                    <div class="p-6 bg-maroon rounded-3xl shadow-xl text-white space-y-6 relative overflow-hidden">
                        <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 bg-gold/10 rounded-full blur-2xl"></div>
                        <h4 class="text-sm font-bold uppercase tracking-widest text-gold border-b border-white/10 pb-3">Note Invoicing Info</h4>
                        <div class="space-y-4">
                            <div>
                                <p class="text-[9px] uppercase text-white/50 font-bold mb-1">SN NO.</p>
                                <p id="view-i-sn" class="text-xs font-mono font-bold">---</p>
                            </div>
                            <div>
                                <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Customer Name</p>
                                <p id="view-i-customer" class="text-sm font-bold text-gold">---</p>
                            </div>
                            <div>
                                <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Issue Date</p>
                                <p id="view-i-date" class="text-xs font-bold">---</p>
                            </div>
                            <div>
                                <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Invoice No(s)</p>
                                <p id="view-i-invoices" class="text-xs font-bold">---</p>
                            </div>
                            <div>
                                <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Note Type</p>
                                <p id="view-i-type" class="text-xs font-bold">Sale's Order</p>
                            </div>
                            <div>
                                <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Status</p>
                                <p id="view-i-status" class="text-xs font-bold text-gold">---</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="w-full md:w-[70%] space-y-4">
                <div class="flex items-center gap-2 bg-slate-100 p-1 rounded-xl w-fit">
                    <button id="view-order-items-tab" onclick="switchSalesOrderDetailTab('items', this)" class="sales-order-detail-tab active px-4 py-2 text-[10px] font-bold rounded-lg uppercase tracking-widest bg-white text-maroon shadow-sm">Order Items</button>
                    <button id="view-order-movement-tab" onclick="switchSalesOrderDetailTab('movement', this)" class="sales-order-detail-tab px-4 py-2 text-[10px] font-bold rounded-lg uppercase tracking-widest text-slate-500 hover:text-maroon">Ledger</button>
                </div>

                <div id="view-order-items-panel">
                <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="p-4 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-widest">Item List</h4>
                        <span id="view-order-count" class="px-3 py-1 bg-maroon/10 text-maroon text-[9px] font-bold rounded-full uppercase">0 items</span>
                    </div>
                <div class="overflow-auto custom-scrollbar max-h-[400px]">
                        <table class="w-full text-left border-collapse">
                            <thead class="sticky top-0 bg-slate-50 z-10">
                                <tr class="text-[9px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100">
                                    <th class="p-4" style="min-width:150px;width:150px;white-space:normal;word-break:break-word;overflow-wrap:anywhere;">Item Code</th>
                                    <th class="p-4" style="min-width:150px;width:150px;white-space:normal;word-break:break-word;overflow-wrap:anywhere;">Price Code</th>
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
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('view-order-tbody', 0, this.value)" placeholder="Code..." class="column-search-input"></th>
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('view-order-tbody', 1, this.value)" placeholder="Desc..." class="column-search-input"></th>
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('view-order-tbody', 2, this.value)" placeholder="QTY..." class="column-search-input text-center"></th>
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('view-order-tbody', 3, this.value)" placeholder="Act..." class="column-search-input text-center"></th>
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('view-order-tbody', 4, this.value)" placeholder="Add..." class="column-search-input text-center"></th>
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('view-order-tbody', 5, this.value)" placeholder="Unit..." class="column-search-input text-center"></th>
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('view-order-tbody', 6, this.value)" placeholder="Price..." class="column-search-input text-right"></th>
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('view-order-tbody', 7, this.value)" placeholder="Disc..." class="column-search-input text-center"></th>
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('view-order-tbody', 8, this.value)" placeholder="SubTotal..." class="column-search-input text-right"></th>
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('view-order-tbody', 9, this.value)" placeholder="Notes..." class="column-search-input"></th>
                                </tr>
                            </thead>
                            <tbody id="view-order-tbody" class="text-xs divide-y divide-slate-50">
                                <tr><td colspan="11" class="p-4 text-center text-slate-300 italic">No items.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 bg-slate-50 border-t border-slate-100 flex justify-end">
                        <h3 id="view-order-total" class="text-lg font-extrabold text-maroon">₱ 0.00</h3>
                    </div>
                </div>
                </div>

                <div id="view-order-movement-panel" class="hidden bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="p-4 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-widest">Ledger</h4>
                        <span id="view-order-movement-count" class="px-3 py-1 bg-maroon/10 text-maroon text-[9px] font-bold rounded-full uppercase">0 transactions</span>
                    </div>
                    <div class="overflow-auto custom-scrollbar max-h-[430px]">
                        <table class="w-full text-left border-collapse min-w-[950px]">
                            <thead class="sticky top-0 bg-slate-50 z-10">
                                <tr class="text-[9px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100">
                                    <th class="p-3">Transaction</th>
                                    <th class="p-3">Transaction No.</th>
                                    <th class="p-3 text-right">Debit</th>
                                    <th class="p-3 text-right">Credit</th>
                                    <th class="p-3">Remarks</th>
                                    <th class="p-3">Date</th>
                                </tr>
                                <tr class="bg-slate-50/70 border-b border-slate-100">
                                    <th class="p-2"><input type="text" data-movement-col="0" oninput="filterSalesOrderMovement()" placeholder="Search transaction..." class="movement-column-search column-search-input"></th>
                                    <th class="p-2"><input type="text" data-movement-col="1" oninput="filterSalesOrderMovement()" placeholder="Search no..." class="movement-column-search column-search-input"></th>
                                    <th class="p-2"><input type="text" data-movement-col="2" oninput="filterSalesOrderMovement()" placeholder="Search debit..." class="movement-column-search column-search-input text-right"></th>
                                    <th class="p-2"><input type="text" data-movement-col="3" oninput="filterSalesOrderMovement()" placeholder="Search credit..." class="movement-column-search column-search-input text-right"></th>
                                    <th class="p-2"><input type="text" data-movement-col="4" oninput="filterSalesOrderMovement()" placeholder="Search remarks..." class="movement-column-search column-search-input"></th>
                                    <th class="p-2"><input type="text" data-movement-col="5" oninput="filterSalesOrderMovement()" placeholder="Search date..." class="movement-column-search column-search-input"></th>
                                </tr>
                            </thead>
                            <tbody id="view-order-movement-tbody" class="text-xs divide-y divide-slate-50">
                                <tr><td colspan="6" class="p-5 text-center text-slate-300 italic">No ledger movement found.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- EDIT SALES ORDER MODAL -->
<div id="edit-sales-order-modal" class="fixed inset-0 z-[300] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('edit-sales-order-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
    <div style="width:96vw;max-width:96vw;" class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-7xl w-full modal-animate-in mx-4 flex flex-col max-h-[95vh]">
        <div class="bg-maroon p-6 flex justify-between items-center text-white border-b-4 border-gold">
            <div class="flex items-center space-x-4">
                <div class="p-3 bg-white/10 rounded-2xl border-2 border-gold shadow-sm">
                    <i data-lucide="pencil" class="w-6 h-6 text-gold"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold tracking-tight">Edit Sales Order</h3>
                    <p class="text-[10px] text-white/60 uppercase tracking-widest font-bold">Update order details and items</p>
                </div>
            </div>
            <button onclick="toggleModal('edit-sales-order-modal', false)" class="text-white/70 hover:text-white transition-colors">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <div class="flex flex-col md:flex-row gap-6 p-6 overflow-y-auto flex-1 min-h-0">
            <div class="w-full md:w-[28%] space-y-4">
                <div class="p-6 bg-maroon rounded-3xl shadow-xl text-white space-y-5 relative overflow-hidden">
                    <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 bg-gold/10 rounded-full blur-2xl"></div>
                    <h4 class="text-sm font-bold uppercase tracking-widest text-gold border-b border-white/10 pb-3">Order Info</h4>
                    <div>
                        <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Order No.</p>
                        <p id="edit-so-order-no" class="text-xs font-mono font-bold tracking-wider">---</p>
                    </div>
                    <div>
                        <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Customer</p>
                        <p id="edit-so-customer" class="text-sm font-bold text-gold">---</p>
                    </div>
                    <div>
                        <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Date</p>
                        <p id="edit-so-date" class="text-xs font-bold">---</p>
                    </div>
                    <div>
                        <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Status</p>
                        <p id="edit-so-status" class="text-xs font-bold text-amber-300">---</p>
                    </div>
                </div>

                <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-4 space-y-3">
                    <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-2">Header Fields</h4>
                    <div>
                        <p class="text-[9px] text-slate-400 font-bold mb-1 uppercase">Invoice No(s)</p>
                        <input type="text" id="edit-so-invoices" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-maroon/20 outline-none transition-all" placeholder="INV-001, INV-002...">
                    </div>
                    <div>
                        <p class="text-[9px] text-slate-400 font-bold mb-1 uppercase">Sales Order Date <span class="text-red-500">*</span></p>
                        <input type="date" id="edit-so-order-date" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-maroon/20 outline-none transition-all" required>
                    </div>
                    <div>
                        <p class="text-[9px] text-slate-400 font-bold mb-1 uppercase">Waybill No</p>
                        <input type="text" id="edit-so-waybill-no" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-maroon/20 outline-none transition-all" placeholder="Waybill number">
                    </div>
                    <div>
                        <p class="text-[9px] text-slate-400 font-bold mb-1 uppercase">Waybill Date</p>
                        <input type="date" id="edit-so-waybill-date" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-maroon/20 outline-none transition-all">
                    </div>
                    <div>
                        <p class="text-[9px] text-slate-400 font-bold mb-1 uppercase">Remarks</p>
                        <textarea id="edit-so-remarks" rows="2" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-maroon/20 outline-none transition-all resize-none" placeholder="Remarks..."></textarea>
                    </div>
                </div>
            </div>

            <div class="w-full md:w-[72%] space-y-4">
                <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="p-4 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-widest flex items-center gap-2">
                            <i data-lucide="package" class="w-4 h-4 text-maroon"></i>
                            Item List
                        </h4>
                        <span class="px-3 py-1 bg-amber-50 text-amber-600 text-[9px] font-bold rounded-full uppercase border border-amber-200">Editable</span>
                    </div>
                    <div class="overflow-auto custom-scrollbar" style="max-height:380px">
                        <table class="w-full text-left border-collapse">
                            <thead class="sticky top-0 bg-slate-50 z-10">
                                <tr class="text-[9px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100">
                                    <th class="p-4 text-center w-10"><input type="checkbox" id="select-all-edit-items" class="accent-maroon cursor-pointer" checked></th>
                                    <th class="p-4" style="min-width:150px;width:150px;white-space:normal;word-break:break-word;overflow-wrap:anywhere;">Item Code</th>
                                    <th class="p-4" style="min-width:150px;width:150px;white-space:normal;word-break:break-word;overflow-wrap:anywhere;">Price Code</th>
                                    <th class="p-4">Description</th>
                                    <th class="p-4 text-center">QTY</th>
                                    <th class="p-4 text-center">Actual QTY</th>
                                    <th class="p-4 text-center">Addl QTY</th>
                                    <th class="p-4 text-center">UNIT</th>
                                    <th class="p-4 text-right">Unit Price</th>
                                    <th class="p-4 text-center">%Disc</th>
                                    <th class="p-4 text-right">Sub Total</th>
                                    <th class="p-4">Particulars</th>
                                </tr>
                            </thead>
                            <tbody id="edit-so-items-tbody" class="text-xs divide-y divide-slate-50">
                                <tr><td colspan="12" class="p-4 text-center text-slate-300 italic">Loading items...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 bg-slate-50 border-t border-slate-100 flex justify-between items-center">
                        <span class="text-[10px] text-slate-400 font-bold uppercase">Edit the items as needed</span>
                        <div class="flex items-center space-x-3">
                            <p class="text-[10px] text-slate-400 font-bold uppercase">Total Amount:</p>
                            <h3 id="edit-so-total-amount" class="text-lg font-extrabold text-maroon">₱ 0.00</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="p-6 bg-slate-50 border-t border-slate-100 flex justify-between items-center">
            <button onclick="toggleModal('edit-sales-order-modal', false)" class="px-6 py-2.5 text-xs font-bold text-slate-400 hover:text-slate-600 transition-all uppercase tracking-widest">Cancel</button>
            <button onclick="confirmEditSalesOrder()" class="px-8 py-2.5 bg-maroon text-white rounded-xl text-xs font-bold hover:bg-maroon-800 transition-all shadow-lg flex items-center space-x-2 uppercase tracking-widest">
                <i data-lucide="check-circle" class="w-4 h-4 text-gold"></i>
                <span>Update Order</span>
            </button>
        </div>
    </div>
</div>

<!-- CONFIRM EDIT ORDER MODAL -->
<div id="confirm-edit-order-modal" class="fixed inset-0 z-[500] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('confirm-edit-order-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-sm sm:w-full modal-animate-in mx-4">
        <div class="p-6 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-amber-50 mb-4">
                <i data-lucide="alert-triangle" class="h-6 w-6 text-amber-600"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-800 mb-2 tracking-tight uppercase">Confirm Update</h3>
            <p id="confirm-edit-text" class="text-xs text-slate-500 mb-4 leading-relaxed">Are you sure you want to update this Sales Order?</p>
        </div>
        <div class="bg-slate-50 px-6 py-4 flex flex-col sm:flex-row-reverse gap-2">
            <button id="confirm-edit-btn" onclick="finalizeEditSalesOrder()" class="w-full px-4 py-2 bg-maroon hover:bg-maroon-800 text-white text-xs font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg shadow-maroon/20">Yes, Update</button>
            <button onclick="toggleModal('confirm-edit-order-modal', false)" class="w-full px-4 py-2 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest">Cancel</button>
        </div>
    </div>
</div>

<!-- SUCCESS EDIT ORDER MODAL -->
<div id="success-edit-order-modal" class="fixed inset-0 z-[500] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('success-edit-order-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-sm sm:w-full modal-animate-in mx-4">
        <div class="p-8 text-center">
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-emerald-50 mb-6">
                <i data-lucide="check-circle" class="h-10 w-10 text-emerald-500"></i>
            </div>
            <h3 class="text-xl font-bold text-slate-800 mb-2">Success!</h3>
            <p id="success-edit-text" class="text-xs text-slate-500 leading-relaxed">The Sales Order has been successfully updated.</p>
        </div>
        <div class="p-6 pt-0">
            <button onclick="toggleModal('success-edit-order-modal', false); reloadAllSalesOrderTabs()" class="w-full px-4 py-3 bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg shadow-emerald-200">Great, Continue</button>
        </div>
    </div>
</div>

<!-- FILTER MODAL -->
<div id="filter-modal" class="fixed inset-0 z-[300] hidden flex items-center justify-center" role="dialog" aria-modal="true">
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
                        <input type="date" id="filter-date-from" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-maroon/20 outline-none transition-all">
                    </div>
                    <div>
                        <p class="text-[9px] text-slate-400 font-bold mb-1 ml-1 uppercase">To</p>
                        <input type="date" id="filter-date-to" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-maroon/20 outline-none transition-all">
                    </div>
                </div>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Filter by Status</label>
                <select id="filter-status" class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-maroon/20 outline-none transition-all">
                    <option value="">All Status</option>
                    <option value="Open">Open</option>
                    <option value="Partial">Partial</option>
                    <option value="Closed">Closed</option>
                    <option value="Surplus">Surplus</option>
                </select>
            </div>
        </div>
        <div class="p-6 pt-0 flex space-x-3">
            <button onclick="resetFilters()" class="flex-1 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] font-bold rounded-xl transition-all uppercase tracking-widest">Reset</button>
            <button onclick="applyFilters()" class="flex-1 px-4 py-2.5 bg-maroon text-white hover:bg-maroon-800 text-[10px] font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg shadow-maroon/20">Apply Filters</button>
        </div>
    </div>
</div>

<!-- ONLINE REPORT GENERATION MODAL -->
<div id="online-report-modal" class="fixed inset-0 z-[130] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('online-report-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-6xl w-full modal-animate-in mx-4 flex flex-col max-h-[90vh]">
        <!-- Header -->
        <div class="bg-maroon p-5 flex justify-between items-center text-white border-b-4 border-gold">
            <div class="flex items-center space-x-4">
                <div class="p-3 bg-white/10 rounded-2xl border-2 border-gold shadow-sm">
                    <i data-lucide="globe" class="w-6 h-6 text-gold"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold tracking-tight">Online Report Generation</h3>
                    <p id="online-report-subtitle" class="text-[10px] text-white/60 uppercase tracking-widest font-bold">Step 1: Basic Info</p>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <div class="flex items-center space-x-2">
                    <div id="or-step-1-indicator" class="w-8 h-8 rounded-full bg-gold text-maroon font-bold flex items-center justify-center text-sm shadow">1</div>
                    <span id="or-step-1-label" class="text-xs font-bold text-white">Basic Info</span>
                </div>
                <div class="w-8 h-0.5 bg-white/30"></div>
                <div class="flex items-center space-x-2">
                    <div id="or-step-2-indicator" class="w-8 h-8 rounded-full bg-white/20 text-white/60 font-bold flex items-center justify-center text-sm">2</div>
                    <span id="or-step-2-label" class="text-xs font-bold text-white/60">Selected Items</span>
                </div>
            </div>
            <button onclick="toggleModal('online-report-modal', false)" class="text-white/70 hover:text-white transition-colors bg-white/10 p-2 rounded-xl">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <!-- STEP 1: Basic Info -->
        <div id="or-step-1-content" class="flex-1 overflow-y-auto custom-scrollbar p-8 space-y-8">
            <!-- Date Range -->
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-3">Sale's Date Range</label>
                <div id="date-range-container" class="space-y-3">
                    <!-- Dynamic date range rows inserted here -->
                </div>
                <button onclick="addDateRangeRow()" class="mt-2 px-4 py-2.5 bg-goldlining-500 hover:bg-goldlining-600 text-maroon-900 text-xs font-bold rounded-xl shadow-sm transition-all flex items-center space-x-2">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>Add</span>
                </button>
            </div>

            <!-- Sales Notes -->
            <div>
                <div class="overflow-x-auto border border-slate-200 rounded-xl">
                    <table class="w-full text-left">
                        <thead class="bg-slate-100">
                            <tr>
                                <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">SN No.</th>
                                <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Invoice NO.</th>
                                <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Address</th>
                            </tr>
                        </thead>
                        <tbody id="or-notes-tbody" class="divide-y divide-slate-100 text-sm">
                            <tr><td colspan="3" class="p-6 text-center text-slate-300 italic">Loading sales notes...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- STEP 2: Selected Items -->
        <div id="or-step-2-content" class="flex-1 overflow-y-auto custom-scrollbar p-8 hidden space-y-6">
            <div class="flex justify-end">
                <div class="min-w-[230px] bg-maroon/5 border border-maroon/20 rounded-2xl px-5 py-3 text-right">
                    <p class="text-[10px] font-bold text-maroon/60 uppercase tracking-widest">Total Amount</p>
                    <p id="or-total-amount" class="mt-1 text-lg font-extrabold text-maroon">₱ 0.00</p>
                </div>
            </div>
            <div id="or-items-loading" class="text-center text-slate-400 italic py-8">Loading items...</div>
            <div id="or-items-container" class="space-y-8"></div>
        </div>

        <!-- Footer Step 1 -->
        <div id="or-footer-1" class="p-4 border-t border-slate-100 flex justify-between items-center">
            <button onclick="toggleModal('online-report-modal', false)" class="px-4 py-2.5 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest">Cancel</button>
            <button onclick="goToOnlineReportStep2()" class="px-6 py-2.5 bg-maroon text-white text-xs font-bold rounded-xl shadow-md hover:bg-maroon-800 transition-all flex items-center space-x-2 uppercase tracking-widest">
                <span>Next</span>
                <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </button>
        </div>
        <!-- Footer Step 2 -->
        <div id="or-footer-2" class="p-4 border-t border-slate-100 hidden justify-between items-center">
            <button onclick="goToOnlineReportStep1()" class="px-4 py-2.5 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all flex items-center space-x-2 uppercase tracking-widest">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                <span>Back</span>
            </button>
            <button onclick="generateOnlineReceipt()" class="px-6 py-2.5 bg-goldlining-500 hover:bg-goldlining-600 text-maroon-900 text-xs font-bold rounded-xl shadow-md hover:shadow-lg transition-all flex items-center space-x-2 uppercase tracking-widest">
                <i data-lucide="file-check" class="w-4 h-4"></i>
                <span>Generate Receipt</span>
            </button>
        </div>
    </div>
</div>

<!-- DELETE ONLINE INVOICE CONFIRMATION MODAL -->
<div id="confirm-delete-oi-modal" class="fixed inset-0 z-[150] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('confirm-delete-oi-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-md w-full modal-animate-in mx-4">
        <div class="bg-red-600 p-6 text-white">
            <div class="flex items-center space-x-3 mb-2">
                <div class="p-2.5 bg-white/10 rounded-xl">
                    <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold tracking-tight">DELETE ONLINE INVOICE?</h3>
                    <p class="text-[10px] text-white/60 uppercase tracking-widest font-bold">This action cannot be undone</p>
                </div>
            </div>
        </div>
        <div class="p-6 space-y-4">
            <div class="bg-slate-50 rounded-xl p-4 space-y-2 border border-slate-200">
                <div class="flex justify-between items-center">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Invoice</span>
                    <span id="delete-oi-invoice" class="text-xs font-bold text-slate-800 text-right">---</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Customer</span>
                    <span id="delete-oi-customer" class="text-xs font-bold text-slate-800 text-right">---</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Date</span>
                    <span id="delete-oi-date" class="text-xs font-bold text-slate-800 text-right">---</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Items</span>
                    <span id="delete-oi-items" class="text-xs font-bold text-slate-800 text-right">---</span>
                </div>
            </div>
            <p class="text-xs text-slate-500">This will permanently delete the selected Online Invoice and its related Product Ledger records. Sales Orders and Sales Notes will not be affected.</p>
        </div>
        <div class="p-4 border-t border-slate-100 flex justify-end space-x-3 bg-slate-50/30">
            <button onclick="toggleModal('confirm-delete-oi-modal', false)" class="px-5 py-2 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest">Cancel</button>
            <button id="confirm-delete-oi-btn" class="px-5 py-2 bg-red-600 text-white text-xs font-bold rounded-xl hover:bg-red-700 transition-all uppercase tracking-widest flex items-center space-x-2">
                <i data-lucide="trash-2" class="w-4 h-4"></i>
                <span>Delete</span>
            </button>
        </div>
    </div>
</div>

<!-- DELETE SALES ORDER CONFIRMATION MODAL (History) -->
<div id="confirm-delete-so-modal" class="fixed inset-0 z-[150] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('confirm-delete-so-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-md w-full modal-animate-in mx-4">
        <div class="bg-red-600 p-6 text-white">
            <div class="flex items-center space-x-3 mb-2">
                <div class="p-2.5 bg-white/10 rounded-xl">
                    <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold tracking-tight">DELETE SALES ORDER?</h3>
                    <p class="text-[10px] text-white/60 uppercase tracking-widest font-bold">This action cannot be undone</p>
                </div>
            </div>
        </div>
        <div class="p-6 space-y-4">
            <div class="bg-slate-50 rounded-xl p-4 space-y-2 border border-slate-200">
                <div class="flex justify-between items-center">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Invoice</span>
                    <span id="delete-so-invoice" class="text-xs font-bold text-slate-800 text-right">---</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Customer</span>
                    <span id="delete-so-customer" class="text-xs font-bold text-slate-800 text-right">---</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Date</span>
                    <span id="delete-so-date" class="text-xs font-bold text-slate-800 text-right">---</span>
                </div>
            </div>
            <p class="text-xs text-slate-500">This will permanently delete this Sales Order and its related Product Ledger entries. The source Sales Note will be reopened.</p>
        </div>
        <div class="p-4 border-t border-slate-100 flex justify-end space-x-3 bg-slate-50/30">
            <button onclick="toggleModal('confirm-delete-so-modal', false)" class="px-5 py-2 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest">Cancel</button>
            <button id="confirm-delete-so-btn" class="px-5 py-2 bg-red-600 text-white text-xs font-bold rounded-xl hover:bg-red-700 transition-all uppercase tracking-widest flex items-center space-x-2">
                <i data-lucide="trash-2" class="w-4 h-4"></i>
                <span>Delete</span>
            </button>
        </div>
    </div>
</div>

<!-- COUNTER PART SELECTION MODAL -->
<div id="counter-part-modal" class="fixed inset-0 z-[140] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('counter-part-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-5xl w-full modal-animate-in mx-4 flex flex-col max-h-[85vh]">
        <div class="bg-maroon p-5 flex justify-between items-center text-white border-b-4 border-gold flex-shrink-0">
            <div class="flex items-center space-x-3">
                <div class="p-2.5 bg-white/10 rounded-xl">
                    <i data-lucide="package-search" class="w-5 h-5 text-gold"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold tracking-tight">Item Counter Part</h3>
                    <p class="text-[10px] text-white/60 uppercase tracking-widest font-bold">Select a counter part item</p>
                </div>
            </div>
            <button onclick="toggleModal('counter-part-modal', false)" class="text-white/70 hover:text-white transition-colors bg-white/10 p-2 rounded-xl">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto custom-scrollbar p-6">
            <div class="overflow-x-auto border border-slate-200 rounded-xl">
                <table class="w-full text-left">
                    <thead class="bg-slate-100 sticky top-0 z-10">
                        <tr>
                            <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Item Code</th>
                            <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Part Number</th>
                            <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Description</th>
                            <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Application</th>
                            <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Category</th>
                        </tr>
                        <tr class="bg-white border-b border-slate-100">
                            <th class="p-1 px-3"><input type="text" data-cp-col="0" onkeyup="filterCounterPartTable()" placeholder="Search Code..." class="cp-search-input w-full py-1 text-[9px] border-slate-100 rounded"></th>
                            <th class="p-1 px-3"><input type="text" data-cp-col="1" onkeyup="filterCounterPartTable()" placeholder="Search Part..." class="cp-search-input w-full py-1 text-[9px] border-slate-100 rounded"></th>
                            <th class="p-1 px-3"><input type="text" data-cp-col="2" onkeyup="filterCounterPartTable()" placeholder="Search Desc..." class="cp-search-input w-full py-1 text-[9px] border-slate-100 rounded"></th>
                            <th class="p-1 px-3"><input type="text" data-cp-col="3" onkeyup="filterCounterPartTable()" placeholder="Search App..." class="cp-search-input w-full py-1 text-[9px] border-slate-100 rounded"></th>
                            <th class="p-1 px-3"><input type="text" data-cp-col="4" onkeyup="filterCounterPartTable()" placeholder="Search Cat..." class="cp-search-input w-full py-1 text-[9px] border-slate-100 rounded"></th>
                        </tr>
                    </thead>
                    <tbody id="cp-tbody" class="divide-y divide-slate-50 text-sm">
                        <tr><td colspan="5" class="p-6 text-center text-slate-300 italic">Loading items...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="p-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/30 flex-shrink-0">
            <span id="cp-page-info" class="text-[10px] text-slate-400 font-bold">Page 1 of 1</span>
            <div class="flex space-x-2">
                <button onclick="prevCounterPartPage()" id="cp-prev-btn" class="px-3 py-1.5 bg-white border border-slate-200 text-slate-500 rounded-lg text-[10px] font-bold hover:bg-maroon hover:text-white transition-all disabled:opacity-30" disabled>&#8592; Prev</button>
                <button onclick="nextCounterPartPage()" id="cp-next-btn" class="px-3 py-1.5 bg-white border border-slate-200 text-slate-500 rounded-lg text-[10px] font-bold hover:bg-maroon hover:text-white transition-all disabled:opacity-30" disabled>Next &#8594;</button>
            </div>
        </div>
    </div>
</div>

<!-- ITEM LIST MODAL (for Online Report - notes with 0 items) -->
<div id="or-item-list-modal" class="fixed inset-0 z-[150] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="closeORItemListModal()" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-6xl w-full modal-animate-in mx-4 flex flex-col max-h-[85vh]">
        <div class="bg-maroon p-5 flex justify-between items-center text-white border-b-4 border-gold flex-shrink-0">
            <div class="flex items-center space-x-3">
                <div class="p-2.5 bg-white/10 rounded-xl">
                    <i data-lucide="shopping-bag" class="w-5 h-5 text-gold"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold tracking-tight">Item List</h3>
                    <p class="text-[10px] text-white/60 uppercase tracking-widest font-bold">Select items to add to this sales note</p>
                </div>
            </div>
            <button onclick="closeORItemListModal()" class="text-white/70 hover:text-white transition-colors bg-white/10 p-2 rounded-xl">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto custom-scrollbar p-6">
            <div class="overflow-x-auto border border-slate-200 rounded-xl">
                <table class="w-full text-left">
                    <thead class="bg-slate-100 sticky top-0 z-10">
                        <tr>
                            <th class="p-3 px-4 w-12"><input type="checkbox" id="or-select-all-items" onchange="toggleORSelectAll(this.checked)" class="rounded border-slate-300 text-maroon focus:ring-maroon"></th>
                            <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Product Code</th>
                            <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Product Name</th>
                            <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Part Number</th>
                            <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Description</th>
                            <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Application</th>
                            <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Position</th>
                            <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Specification</th>
                            <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">QTY</th>
                        </tr>
                        <tr class="bg-white border-b border-slate-100">
                            <th class="p-1 px-3"></th>
                            <th class="p-1 px-3"><input type="text" data-or-col="1" onkeyup="filterORItemTable()" placeholder="Search Code..." class="or-item-search w-full py-1 text-[9px] border-slate-100 rounded"></th>
                            <th class="p-1 px-3"><input type="text" data-or-col="2" onkeyup="filterORItemTable()" placeholder="Search Name..." class="or-item-search w-full py-1 text-[9px] border-slate-100 rounded"></th>
                            <th class="p-1 px-3"><input type="text" data-or-col="3" onkeyup="filterORItemTable()" placeholder="Search Part..." class="or-item-search w-full py-1 text-[9px] border-slate-100 rounded"></th>
                            <th class="p-1 px-3"><input type="text" data-or-col="4" onkeyup="filterORItemTable()" placeholder="Search Desc..." class="or-item-search w-full py-1 text-[9px] border-slate-100 rounded"></th>
                            <th class="p-1 px-3"><input type="text" data-or-col="5" onkeyup="filterORItemTable()" placeholder="Search App..." class="or-item-search w-full py-1 text-[9px] border-slate-100 rounded"></th>
                            <th class="p-1 px-3"><input type="text" data-or-col="6" onkeyup="filterORItemTable()" placeholder="Search Pos..." class="or-item-search w-full py-1 text-[9px] border-slate-100 rounded"></th>
                            <th class="p-1 px-3"><input type="text" data-or-col="7" onkeyup="filterORItemTable()" placeholder="Search Spec..." class="or-item-search w-full py-1 text-[9px] border-slate-100 rounded"></th>
                            <th class="p-1 px-3"></th>
                        </tr>
                    </thead>
                    <tbody id="or-item-list-tbody" class="divide-y divide-slate-50 text-sm">
                        <tr><td colspan="9" class="p-6 text-center text-slate-300 italic">Loading items...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="p-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/30 flex-shrink-0">
            <div class="flex items-center space-x-3">
                <span id="or-item-page-info" class="text-[10px] text-slate-400 font-bold">Page 1 of 1</span>
                <span id="or-item-selected-count" class="text-[10px] font-bold text-emerald-600">0 selected</span>
            </div>
            <div class="flex space-x-2">
                <button onclick="prevORItemPage()" id="or-item-prev-btn" class="px-3 py-1.5 bg-white border border-slate-200 text-slate-500 rounded-lg text-[10px] font-bold hover:bg-maroon hover:text-white transition-all disabled:opacity-30" disabled>&#8592; Prev</button>
                <button onclick="addORSelectedItems()" class="px-4 py-1.5 bg-maroon text-white rounded-lg text-[10px] font-bold hover:bg-maroon-800 transition-all shadow-md flex items-center space-x-1">
                    <i data-lucide="plus" class="w-3 h-3"></i>
                    <span>Add Selected</span>
                </button>
                <button onclick="nextORItemPage()" id="or-item-next-btn" class="px-3 py-1.5 bg-white border border-slate-200 text-slate-500 rounded-lg text-[10px] font-bold hover:bg-maroon hover:text-white transition-all disabled:opacity-30" disabled>Next &#8594;</button>
            </div>
        </div>
    </div>
</div>

<!-- STOCK WARNING MODAL -->
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
                The items below have insufficient stock. You cannot proceed with this sales order.
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
            <button id="stock-warning-close-btn" onclick="toggleModal('stock-warning-modal', false)" class="flex-1 px-4 py-2.5 bg-maroon hover:bg-maroon-600 border border-maroon text-white text-xs font-bold rounded-xl transition-all uppercase tracking-widest">Close</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
    <script>
        window.salesOrderRoutes = {
            activeUrl: '{{ route("special.sales-order.active") }}',
            proceedUrl: '{{ route("special.sales-order.proceed") }}',
            forceCloseNoteUrl: '{{ route("special.sales-order.force-close-note", ["salesNoteId" => ":salesNoteId"]) }}',
            historyUrl: '{{ route("special.sales-order.history") }}',
            dashboardUrl: '{{ route("special.sales-order.dashboard") }}',
            detailUrl: '{{ route("special.sales-order.detail", ["id" => ":id"]) }}',
            editDetailUrl: '{{ route("special.sales-order.edit-detail", ["salesOrderId" => ":salesOrderId"]) }}',
            updateUrl: '{{ route("special.sales-order.update", ["salesOrderId" => ":salesOrderId"]) }}',
            ledgerUrl: '{{ route("special.sales-order.ledger", ["salesNumber" => ":salesNumber"]) }}',
            receiptPrintUrl: '{{ route("special.sales-order.receipt-print") }}',
            reportUrl: '{{ route("special.sales-note.report") }}',
            onlinePricesUrl: '{{ route("special.sales-note.online-prices") }}',
            productsUrl: '{{ route("special.sales-note.products") }}',
            counterPartProductsUrl: '{{ route("special.sales-order.counter-part-products") }}',
            onlineGenerateUrl: '{{ route("special.sales-order.online-generate") }}',
            onlinePrintUrl: '{{ route("special.sales-order.online-print", ["id" => ":id"]) }}',
            onlineInvoicesUrl: '{{ route("special.sales-order.online-invoices") }}',
            onlineInvoicesDataUrl: '{{ route("special.sales-order.online-invoices-data") }}',
            onlineInvoiceEditUrl: '{{ route("special.sales-order.online-edit", ["id" => ":id"]) }}',
            onlineInvoiceUpdateUrl: '{{ route("special.sales-order.online-update", ["id" => ":id"]) }}',
            onlineInvoiceDeleteUrl: '{{ route("special.sales-order.online-destroy", ["id" => ":id"]) }}',
            onlineInvoiceMinSearchLength: 3,
            allOrdersDataUrl: '{{ route("special.sales-order.all-orders-data") }}',
            historyDataUrl: '{{ route("special.sales-order.history-data") }}',
            historyDeleteUrl: '{{ route("special.sales-order.history-destroy", ["id" => ":id"]) }}',
            allUrl: '{{ route("special.sales-order.all") }}',
            productPriceUrl: '{{ route("special.sales-order.product-price", ["id" => ":id"]) }}',
            priceCodesUrl: '{{ route("special.sales-order.price-codes", ["productId" => ":productId"]) }}',
            stockCheckUrl: '{{ route("special.sales-order.check-stock") }}',
            editStockCheckUrl: '{{ route("special.sales-order.edit-check-stock") }}',
            noteInvoicesUrl: '{{ route("special.sales-note.invoices", ["noteId" => ":noteId"]) }}',
            onlineVerifyLedgerUrl: '{{ route("special.sales-order.online-report.verify-product-ledger", ["onlineReport" => ":reportId"]) }}',
        };
        window.csrfToken = '{{ csrf_token() }}';

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof loadSalesOrderDashboard === 'function') loadSalesOrderDashboard();
            if (typeof loadActiveNotes === 'function') loadActiveNotes();
            if (typeof loadSalesOrderHistory === 'function') loadSalesOrderHistory();
            if (typeof loadOnlineInvoices === 'function') loadOnlineInvoices();

            document.getElementById('select-all-active')?.addEventListener('change', function() {
                document.querySelectorAll('#active-notes-tbody .note-checkbox').forEach(cb => cb.checked = this.checked);
            });
            document.getElementById('select-all-history')?.addEventListener('change', function() {
                document.querySelectorAll('#history-orders-tbody .note-checkbox').forEach(cb => cb.checked = this.checked);
            });

            // Auto-open edit modal if redirected from sales-note edit
            const urlParams = new URLSearchParams(window.location.search);
            const salesOrderId = urlParams.get('salesOrderId');
            if (salesOrderId) {
                setTimeout(() => {
                    if (typeof switchSalesOrderTab === 'function') switchSalesOrderTab('history');
                    setTimeout(() => editSalesOrder(parseInt(salesOrderId)), 400);
                }, 600);
            }
        });
    </script>
    <script src="{{ asset('js/Sales-Order.js') }}?v={{ time() }}"></script>
    <script>
        let currentStep = 1;
        const originalProceedGoTo = window.proceedGoToStep;
        window.proceedGoToStep = async function(step) {
            // Call original function first and check if it succeeds
            if (typeof originalProceedGoTo === 'function') {
                const result = await originalProceedGoTo(step);
                if (result === false) {
                    // Original function blocked the step change
                    return false;
                }
            }
            
            // Only update currentStep if the original function succeeded
            currentStep = step;

            const btnBack = document.getElementById('p-btn-back');
            const btnNext = document.getElementById('p-btn-next');
            const nextText = document.getElementById('p-next-text');
            const nextIcon = document.querySelector('#p-btn-next i');

            if (step === 1) {
                btnBack.classList.add('hidden');
                btnNext.onclick = async () => { await proceedGoToStep(2); };
                nextText.innerText = 'Next Step';
                if(nextIcon) nextIcon.setAttribute('data-lucide', 'chevron-right');
            } else if (step === 2) {
                btnBack.classList.remove('hidden');
                btnNext.onclick = async () => { await proceedGoToStep(3); };
                nextText.innerText = 'Review Order';
                if(nextIcon) nextIcon.setAttribute('data-lucide', 'eye');
            } else {
                btnBack.classList.remove('hidden');
                btnNext.onclick = () => toggleModal('confirm-order-modal', true);
                nextText.innerText = 'Confirm Order';
                if(nextIcon) nextIcon.setAttribute('data-lucide', 'check-circle');
            }
            if (typeof lucide !== 'undefined') lucide.createIcons();
            return true;
        };
    </script>
@endpush
