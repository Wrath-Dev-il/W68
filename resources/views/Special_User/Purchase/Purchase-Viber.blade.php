@extends('partials.special_user.special_sidebar_navbar')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/purchase-viber.css') }}?v={{ time() }}">
@endpush

@section('purchase_viber_content')

<div id="page-purchase-viber-root" class="space-y-6 animate-fade-in text-slate-800">

    {{-- Dashboard Status Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
        <div class="viber-dashboard-card flex flex-col items-center justify-center p-4 rounded-xl border-2 cursor-pointer transition-all bg-maroon-950 border-maroon-800/50" data-tab="entry" onclick="switchViberTab('entry')">
            <i data-lucide="file-plus" class="viber-card-icon w-5 h-5 text-yellow-400 mb-1"></i>
            <span class="viber-card-count text-2xl font-extrabold text-yellow-400">0</span>
            <span class="viber-card-label text-xs font-bold uppercase tracking-wider mt-0.5 text-white/80">Entry</span>
        </div>
        <div class="viber-dashboard-card flex flex-col items-center justify-center p-4 rounded-xl border-2 cursor-pointer transition-all bg-maroon-900 border-maroon-800" data-tab="to_shipped" onclick="switchViberTab('to_shipped')">
            <i data-lucide="truck" class="viber-card-icon w-5 h-5 text-yellow-400 mb-1"></i>
            <span class="viber-card-count text-2xl font-extrabold text-yellow-400">0</span>
            <span class="viber-card-label text-xs font-bold uppercase tracking-wider mt-0.5 text-white/80">To Shipped</span>
        </div>
        <div class="viber-dashboard-card flex flex-col items-center justify-center p-4 rounded-xl border-2 cursor-pointer transition-all bg-maroon-900 border-maroon-800" data-tab="arrived" onclick="switchViberTab('arrived')">
            <i data-lucide="package-check" class="viber-card-icon w-5 h-5 text-yellow-400 mb-1"></i>
            <span class="viber-card-count text-2xl font-extrabold text-yellow-400">0</span>
            <span class="viber-card-label text-xs font-bold uppercase tracking-wider mt-0.5 text-white/80">Arrived</span>
        </div>
        <div class="viber-dashboard-card flex flex-col items-center justify-center p-4 rounded-xl border-2 cursor-pointer transition-all bg-maroon-900 border-maroon-800" data-tab="not_arrived" onclick="switchViberTab('not_arrived')">
            <i data-lucide="package-x" class="viber-card-icon w-5 h-5 text-yellow-400 mb-1"></i>
            <span class="viber-card-count text-2xl font-extrabold text-yellow-400">0</span>
            <span class="viber-card-label text-xs font-bold uppercase tracking-wider mt-0.5 text-white/80">Not Arrived</span>
        </div>
    </div>

    {{-- Supplier Tabs Bar --}}
    <div id="supplier-tabs-wrapper" class="hidden flex items-center gap-1.5 flex-wrap">
        <div id="supplier-tabs-container" class="flex items-center gap-1 flex-wrap flex-1 min-w-0"></div>
    </div>

    {{-- Action Bar --}}
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <button onclick="printEntrySupplierItems()" id="viber-print-entry-btn" class="hidden">
                <i data-lucide="printer" class="w-4 h-4"></i>
                <span>Print</span>
            </button>
        </div>
        <div class="relative flex-1 max-w-xs">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i data-lucide="search" class="w-4 h-4 text-slate-400"></i>
            </div>
            <input type="text" id="viber-search-input" oninput="handleViberSearch(this)" placeholder="Search item code, part no, description..." class="block w-full pl-9 pr-3 py-2 border border-slate-200 rounded-xl bg-white placeholder-slate-400 text-sm focus:outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon transition-colors">
        </div>
    </div>

    {{-- Main Table Card --}}
    <div id="viber-main-table-card" class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">

        {{-- Empty: No Supplier --}}
        <div id="viber-empty-no-supplier" class="flex flex-col items-center justify-center py-16">
            <i data-lucide="building-2" class="w-12 h-12 text-slate-300 mb-3"></i>
            <p class="text-sm font-medium text-slate-500">No supplier added yet.</p>
            <p class="text-xs text-slate-400 mt-1">No supplier records are available.</p>
        </div>

        {{-- Empty: No Supplier Selected --}}
        <div id="viber-empty-no-selection" class="hidden flex flex-col items-center justify-center py-16">
            <i data-lucide="file-input" class="w-12 h-12 text-slate-300 mb-3"></i>
            <p class="text-sm font-medium text-slate-500">Select a supplier tab to view items.</p>
        </div>

        {{-- Empty: Supplier Has No Items --}}
        <div id="viber-empty-no-items" class="hidden flex flex-col items-center justify-center py-16">
            <i data-lucide="package-open" class="w-12 h-12 text-slate-300 mb-3"></i>
            <p class="text-sm font-medium text-slate-500">No items added for this supplier.</p>
            <p class="text-xs text-slate-400 mt-1">No items are currently listed for this supplier.</p>
        </div>

        {{-- Table Area --}}
        <div id="viber-table-area" class="hidden">
            <div class="overflow-x-auto viber-scroll">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/80 text-slate-400 text-[10px] font-bold uppercase tracking-wider">
                                <th class="py-3 px-4">Item Code</th>
                                <th class="py-3 px-4">Part No#</th>
                                <th class="py-3 px-4">Description</th>
                                <th class="py-3 px-4">Application</th>
                                <th class="py-3 px-4 text-right">Last Cost</th>
                                <th class="py-3 px-4 text-right">New Cost</th>
                                <th class="py-3 px-4 text-center">Order Qty</th>
                                <th class="py-3 px-4 w-16">Unit</th>
                                <th class="py-3 px-4">Ordered Date</th>
                                <th class="py-3 px-4">Remarks</th>
                            </tr>
                            {{-- Column Filters --}}
                            <tr class="border-b border-slate-100 bg-white">
                                <th class="py-2 px-4"><input type="text" oninput="filterViberColumn(this, 'item_code')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-maroon/20"></th>
                                <th class="py-2 px-4"><input type="text" oninput="filterViberColumn(this, 'part_no')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-maroon/20"></th>
                                <th class="py-2 px-4"><input type="text" oninput="filterViberColumn(this, 'description')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-maroon/20"></th>
                                <th class="py-2 px-4"><input type="text" oninput="filterViberColumn(this, 'application')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-maroon/20"></th>
                                <th class="py-2 px-4"><input type="text" oninput="filterViberColumn(this, 'last_cost')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md text-right focus:outline-none focus:ring-1 focus:ring-maroon/20"></th>
                                <th class="py-2 px-4"><input type="text" oninput="filterViberColumn(this, 'new_cost')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md text-right focus:outline-none focus:ring-1 focus:ring-maroon/20"></th>
                                <th class="py-2 px-4"><input type="text" oninput="filterViberColumn(this, 'order_qty')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md text-center focus:outline-none focus:ring-1 focus:ring-maroon/20"></th>
                                <th class="py-2 px-4"><input type="text" oninput="filterViberColumn(this, 'oum_unit')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-maroon/20"></th>
                                <th class="py-2 px-4"><input type="text" oninput="filterViberColumn(this, 'ordered_date')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-maroon/20"></th>
                                <th class="py-2 px-4"><input type="text" oninput="filterViberColumn(this, 'remarks')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-maroon/20"></th>
                            </tr>
                    </thead>
                    <tbody id="viber-table-body" class="divide-y divide-slate-100 text-sm">
                    </tbody>
                </table>
            </div>

            {{-- Pagination Footer --}}
            <div class="p-4 border-t border-slate-100 bg-slate-50/30 flex items-center justify-between gap-4 flex-wrap">
                <span id="table-range-info" class="text-xs text-slate-500 font-medium">Showing 0-0 of 0</span>
                <div class="flex items-center gap-2">
                    <button id="table-prev-btn" onclick="changePage(-1)" class="px-3 py-1.5 text-xs font-bold rounded-lg border border-slate-100 text-slate-300 cursor-not-allowed">Prev</button>
                    <span id="table-page-indicator" class="text-xs font-semibold text-slate-600 min-w-[90px] text-center">Page 1 of 1</span>
                    <button id="table-next-btn" onclick="changePage(1)" class="px-3 py-1.5 text-xs font-bold rounded-lg border border-slate-100 text-slate-300 cursor-not-allowed">Next</button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- To Shipped Suppliers Table --}}
<div id="viber-to-shipped-area" class="hidden space-y-4">
    <div id="viber-suppliers-area" class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 bg-slate-50/30">
            <h3 class="text-sm font-bold text-slate-700">Suppliers</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/80 text-slate-400 text-[10px] font-bold uppercase tracking-wider">
                        <th class="py-3 px-4">Supplier Code</th>
                        <th class="py-3 px-4">Supplier Name</th>
                        <th class="py-3 px-4 text-center">Ordered Items</th>
                        <th class="py-3 px-4 text-center">Arrived</th>
                        <th class="py-3 px-4 text-center">Partial</th>
                        <th class="py-3 px-4 text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="viber-suppliers-tbody" class="divide-y divide-slate-100 text-sm"></tbody>
            </table>
        </div>
    </div>

    <div id="viber-to-shipped-items-area" class="hidden bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 bg-slate-50/30">
            <div class="flex items-center gap-3 mb-3">
                <button onclick="backToSuppliers()" class="p-1.5 hover:bg-slate-200 rounded-lg transition-colors" title="Back to suppliers">
                    <i data-lucide="arrow-left" class="w-4 h-4 text-slate-500"></i>
                </button>
                <h3 class="text-sm font-bold text-slate-700">Selected Items</h3>
            </div>
            <div class="flex items-center gap-1 bg-slate-100/50 rounded-xl p-0.5 w-fit">
                <button id="viber-ready-tab-btn" onclick="switchToShippedTab('ready')" class="px-4 py-1.5 text-xs font-bold rounded-lg bg-maroon text-white shadow-sm transition-all">Ready to Shipped Items</button>
                <button id="viber-shipped-tab-btn" onclick="switchToShippedTab('shipped')" class="px-4 py-1.5 text-xs font-bold rounded-lg text-slate-500 hover:text-slate-700 transition-all">Shipped Items</button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/80 text-slate-400 text-[10px] font-bold uppercase tracking-wider">
                        <th class="py-3 px-4">Item Code</th>
                        <th class="py-3 px-4">Part No</th>
                        <th class="py-3 px-4">Description</th>
                                <th class="py-3 px-4">Application</th>
                                <th class="py-3 px-4 text-right">Last Cost</th>
                        <th class="py-3 px-4 text-right">New Cost</th>
                        <th class="py-3 px-4 text-center">Order QTY</th>
                        <th class="py-3 px-4 w-16">Unit</th>
                        <th class="py-3 px-4">Order Date</th>
                    </tr>
                </thead>
                <tbody id="viber-to-shipped-items-tbody" class="divide-y divide-slate-100 text-sm"></tbody>
            </table>
        </div>
    </div>
</div>

{{-- Arrived Invoiced Items Area --}}
<div id="viber-arrived-invoiced-area" class="hidden space-y-4">
    <div id="arrived-supplier-tabs-wrapper" class="hidden flex items-center gap-1.5 flex-wrap">
        <div id="arrived-supplier-tabs-container" class="flex items-center gap-1 flex-wrap flex-1 min-w-0"></div>
    </div>

    <div id="viber-arrived-invoiced-card" class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div id="arrived-empty-no-suppliers" class="flex flex-col items-center justify-center py-16">
            <i data-lucide="package-check" class="w-12 h-12 text-slate-300 mb-3"></i>
            <p class="text-sm font-medium text-slate-500">No invoiced items found.</p>
            <p class="text-xs text-slate-400 mt-1">Items must have a Purchase Order to appear here.</p>
        </div>
        <div id="arrived-empty-no-selection" class="hidden flex flex-col items-center justify-center py-16">
            <i data-lucide="file-input" class="w-12 h-12 text-slate-300 mb-3"></i>
            <p class="text-sm font-medium text-slate-500">Select a supplier tab to view invoiced items.</p>
        </div>
        <div id="arrived-empty-no-items" class="hidden flex flex-col items-center justify-center py-16">
            <i data-lucide="package-open" class="w-12 h-12 text-slate-300 mb-3"></i>
            <p class="text-sm font-medium text-slate-500">No invoiced items for this supplier.</p>
        </div>

        <div id="arrived-table-area" class="hidden">
            <div class="overflow-x-auto viber-scroll">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/80 text-slate-400 text-[10px] font-bold uppercase tracking-wider">
                            <th class="py-3 px-4">Item Code</th>
                            <th class="py-3 px-4">Part No#</th>
                            <th class="py-3 px-4">Description</th>
                            <th class="py-3 px-4">Application</th>
                            <th class="py-3 px-4 text-right">Last Cost</th>
                            <th class="py-3 px-4 text-right">New Cost</th>
                            <th class="py-3 px-4 text-center">Order Qty</th>
                            <th class="py-3 px-4 w-16">Unit</th>
                            <th class="py-3 px-4 text-center">Actual QTY</th>
                            <th class="py-3 px-4">Ordered Date</th>
                            <th class="py-3 px-4">PO Number</th>
                            <th class="py-3 px-4">PO Date</th>
                        </tr>
                        <tr class="border-b border-slate-100 bg-white">
                            <th class="py-2 px-4"><input type="text" oninput="filterArrivedColumn(this, 'item_code')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-emerald-500/20"></th>
                            <th class="py-2 px-4"><input type="text" oninput="filterArrivedColumn(this, 'part_no')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-emerald-500/20"></th>
                            <th class="py-2 px-4"><input type="text" oninput="filterArrivedColumn(this, 'description')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-emerald-500/20"></th>
                            <th class="py-2 px-4"><input type="text" oninput="filterArrivedColumn(this, 'application')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-emerald-500/20"></th>
                            <th class="py-2 px-4"><input type="text" oninput="filterArrivedColumn(this, 'last_cost')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md text-right focus:outline-none focus:ring-1 focus:ring-emerald-500/20"></th>
                            <th class="py-2 px-4"><input type="text" oninput="filterArrivedColumn(this, 'new_cost')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md text-right focus:outline-none focus:ring-1 focus:ring-emerald-500/20"></th>
                            <th class="py-2 px-4"><input type="text" oninput="filterArrivedColumn(this, 'order_qty')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md text-center focus:outline-none focus:ring-1 focus:ring-emerald-500/20"></th>
                            <th class="py-2 px-4"><input type="text" oninput="filterArrivedColumn(this, 'oum_unit')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-emerald-500/20"></th>
                            <th class="py-2 px-4"><input type="text" oninput="filterArrivedColumn(this, 'actual_qty')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md text-center focus:outline-none focus:ring-1 focus:ring-emerald-500/20"></th>
                            <th class="py-2 px-4"><input type="text" oninput="filterArrivedColumn(this, 'ordered_date')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-emerald-500/20"></th>
                            <th class="py-2 px-4"><input type="text" oninput="filterArrivedColumn(this, 'po_number')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-emerald-500/20"></th>
                            <th class="py-2 px-4"><input type="text" oninput="filterArrivedColumn(this, 'po_date')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-emerald-500/20"></th>
                        </tr>
                    </thead>
                    <tbody id="arrived-table-body" class="divide-y divide-slate-100 text-sm"></tbody>
                </table>
            </div>

            <div class="p-4 border-t border-slate-100 bg-slate-50/30 flex items-center justify-between gap-4 flex-wrap">
                <span id="arrived-range-info" class="text-xs text-slate-500 font-medium">Showing 0-0 of 0</span>
                <div class="flex items-center gap-2">
                    <button id="arrived-prev-btn" onclick="changeArrivedPage(-1)" class="px-3 py-1.5 text-xs font-bold rounded-lg border border-slate-100 text-slate-300 cursor-not-allowed">Prev</button>
                    <span id="arrived-page-indicator" class="text-xs font-semibold text-slate-600 min-w-[90px] text-center">Page 1 of 1</span>
                    <button id="arrived-next-btn" onclick="changeArrivedPage(1)" class="px-3 py-1.5 text-xs font-bold rounded-lg border border-slate-100 text-slate-300 cursor-not-allowed">Next</button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- SELECT SUPPLIER MODAL --}}
<div id="select-supplier-modal" class="fixed inset-0 z-[100] hidden">
    <div class="viber-modal-overlay fixed inset-0" onclick="closeSelectSupplier()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[85vh] flex flex-col overflow-hidden">
            <div class="flex items-center justify-between p-5 border-b border-slate-100 flex-shrink-0">
                <h3 class="text-sm font-bold text-slate-800">Select Supplier</h3>
                <button onclick="closeSelectSupplier()" class="p-1.5 hover:bg-slate-100 rounded-lg transition-colors">
                    <i data-lucide="x" class="w-4 h-4 text-slate-400"></i>
                </button>
            </div>
            <div class="p-4 border-b border-slate-100 flex-shrink-0">
                <div class="relative">
                    <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                    <input type="text" id="supplier-search-input" oninput="filterSuppliers(this)" placeholder="Search supplier code, name, contact..." class="w-full pl-9 pr-3 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-maroon/20">
                </div>
            </div>
            <div class="overflow-y-auto flex-1 viber-scroll">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/80 text-slate-400 text-[10px] font-bold uppercase tracking-wider sticky top-0">
                            <th class="py-3 px-4">Supplier Code</th>
                            <th class="py-3 px-4">Supplier Name</th>
                            <th class="py-3 px-4">Contact No#</th>
                            <th class="py-3 px-4">Contact Person</th>
                            <th class="py-3 px-4">Billing Address</th>
                        </tr>
                    </thead>
                    <tbody id="supplier-table-body"></tbody>
                </table>
            </div>
            <div class="p-4 border-t border-slate-100 flex items-center justify-between flex-shrink-0 bg-slate-50/50">
                <span class="text-xs text-slate-400">Click a row to select a supplier</span>
                <button onclick="confirmSelectSupplier()" class="px-5 py-2 bg-maroon text-white text-xs font-bold rounded-xl hover:bg-maroon-800 transition-all shadow-sm">Confirm Selection</button>
            </div>
        </div>
    </div>
</div>

{{-- ADD ITEMS MODAL (2-Step) --}}
<div id="add-items-modal" class="fixed inset-0 z-[110] hidden">
    <div class="viber-modal-overlay fixed inset-0" onclick="closeAddItems()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col overflow-hidden">
            {{-- Header --}}
            <div class="flex items-center justify-between p-5 border-b border-slate-100 flex-shrink-0">
                <div>
                    <h3 class="text-sm font-bold text-slate-800">Add Items</h3>
                    <p id="modal-step-title" class="text-xs text-slate-400 mt-0.5">Step 1: Search & Select Items</p>
                </div>
                <button onclick="closeAddItems()" class="p-1.5 hover:bg-slate-100 rounded-lg transition-colors">
                    <i data-lucide="x" class="w-4 h-4 text-slate-400"></i>
                </button>
            </div>

            {{-- Step 1: Search Items --}}
            <div id="modal-step-1">
                <div class="p-4 border-b border-slate-100 flex-shrink-0">
                    <div class="relative">
                        <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                        <input type="text" id="modal-search-input" oninput="filterModalItems(this)" placeholder="Search item code, part no, description..." class="w-full pl-9 pr-3 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-maroon/20">
                    </div>
                </div>
                <div class="overflow-y-auto viber-scroll" style="max-height: 55vh;">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/80 text-slate-400 text-[10px] font-bold uppercase tracking-wider sticky top-0">
                                <th class="py-3 px-4 w-12"><i data-lucide="check-square" class="w-3.5 h-3.5"></i></th>
                                <th class="py-3 px-4">Item Code
                                    <input type="text" oninput="filterModalColumn('item_code', this)" placeholder="Filter item code..." class="modal-col-filter mt-1 block w-full px-2 py-1 text-[10px] font-normal text-slate-600 border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-maroon/20 placeholder:text-slate-300">
                                </th>
                                <th class="py-3 px-4">Part No#
                                    <input type="text" oninput="filterModalColumn('part_number', this)" placeholder="Filter part no..." class="modal-col-filter mt-1 block w-full px-2 py-1 text-[10px] font-normal text-slate-600 border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-maroon/20 placeholder:text-slate-300">
                                </th>
                                <th class="py-3 px-4">Description
                                    <input type="text" oninput="filterModalColumn('description', this)" placeholder="Filter description..." class="modal-col-filter mt-1 block w-full px-2 py-1 text-[10px] font-normal text-slate-600 border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-maroon/20 placeholder:text-slate-300">
                                </th>
                                <th class="py-3 px-4">Application
                                    <input type="text" oninput="filterModalColumn('application', this)" placeholder="Filter application..." class="modal-col-filter mt-1 block w-full px-2 py-1 text-[10px] font-normal text-slate-600 border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-maroon/20 placeholder:text-slate-300">
                                </th>
                                <th class="py-3 px-4">Brand
                                    <input type="text" oninput="filterModalColumn('brand', this)" placeholder="Filter brand..." class="modal-col-filter mt-1 block w-full px-2 py-1 text-[10px] font-normal text-slate-600 border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-maroon/20 placeholder:text-slate-300">
                                </th>
                                <th class="py-3 px-4 w-16">Unit
                                </th>
                            </tr>
                        </thead>
                        <tbody id="modal-items-tbody"></tbody>
                    </table>
                </div>
                <div class="p-4 border-t border-slate-100 flex items-center justify-between flex-shrink-0 bg-slate-50/50">
                    <div class="flex items-center gap-3">
                        <span id="modal-selected-count" class="text-xs font-semibold text-maroon">0 item(s) selected</span>
                        <span id="modal-items-info" class="text-xs text-slate-400">Page 1 of 1</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button id="modal-prev-btn" onclick="modalChangePage(-1)" class="px-3 py-1.5 text-xs font-bold rounded-lg border border-slate-100 text-slate-300 cursor-not-allowed">Prev</button>
                        <button id="modal-next-btn" onclick="modalChangePage(1)" class="px-3 py-1.5 text-xs font-bold rounded-lg border border-slate-100 text-slate-300 cursor-not-allowed">Next</button>
                        <button onclick="goToModalStep2()" class="px-5 py-2 bg-maroon text-white text-xs font-bold rounded-xl hover:bg-maroon-800 transition-all shadow-sm ml-2">Next</button>
                    </div>
                </div>
            </div>

            {{-- Step 2: Set Costs & Quantities --}}
            <div id="modal-step-2" class="hidden">
                <div class="overflow-y-auto viber-scroll" style="max-height: 60vh;">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/80 text-slate-400 text-[10px] font-bold uppercase tracking-wider sticky top-0">
                                <th class="py-3 px-4">Item Code</th>
                                <th class="py-3 px-4">Part No#</th>
                                <th class="py-3 px-4">Description</th>
                                <th class="py-3 px-4">Application</th>
                                <th class="py-3 px-4">Brand</th>
                                <th class="py-3 px-4 text-right">Last Cost</th>
                                <th class="py-3 px-4 text-right">New Cost</th>
                                <th class="py-3 px-4 text-center">Order Qty</th>
                                <th class="py-3 px-4 w-16">Unit</th>
                                <th class="py-3 px-4">Ordered Date</th>
                                <th class="py-3 px-4">Remarks</th>
                            </tr>
                        </thead>
                        <tbody id="modal-confirm-tbody"></tbody>
                    </table>
                </div>
                <div class="p-4 border-t border-slate-100 flex items-center justify-between flex-shrink-0 bg-slate-50/50">
                    <button onclick="goToModalStep1()" class="px-4 py-2 bg-white border border-slate-200 text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all">Back</button>
                    <button onclick="saveModalItems()" class="px-5 py-2 bg-maroon text-white text-xs font-bold rounded-xl hover:bg-maroon-800 transition-all shadow-sm">Add Selected Items</button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Not Arrived Items Area --}}
<div id="viber-not-arrived-area" class="hidden space-y-4">
    <div id="not-arrived-supplier-tabs-wrapper" class="hidden flex items-center gap-1.5 flex-wrap">
        <div id="not-arrived-supplier-tabs-container" class="flex items-center gap-1 flex-wrap flex-1 min-w-0"></div>
    </div>

    <div id="viber-not-arrived-card" class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div id="not-arrived-empty-no-suppliers" class="flex flex-col items-center justify-center py-16">
            <i data-lucide="package-x" class="w-12 h-12 text-slate-300 mb-3"></i>
            <p class="text-sm font-medium text-slate-500">No not-arrived items found.</p>
            <p class="text-xs text-slate-400 mt-1">Items in Purchase Orders with incomplete arrivals will appear here.</p>
        </div>
        <div id="not-arrived-empty-no-selection" class="hidden flex flex-col items-center justify-center py-16">
            <i data-lucide="file-input" class="w-12 h-12 text-slate-300 mb-3"></i>
            <p class="text-sm font-medium text-slate-500">Select a supplier tab to view not-arrived items.</p>
        </div>
        <div id="not-arrived-empty-no-items" class="hidden flex flex-col items-center justify-center py-16">
            <i data-lucide="package-open" class="w-12 h-12 text-slate-300 mb-3"></i>
            <p class="text-sm font-medium text-slate-500">All items for this supplier are fully arrived.</p>
        </div>

        <div id="not-arrived-table-area" class="hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 bg-slate-50/40">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Not Arrived Items</span>
            </div>
            <div class="overflow-x-auto viber-scroll">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/80 text-slate-400 text-[10px] font-bold uppercase tracking-wider">
                            <th class="py-3 px-4">Item Code</th>
                            <th class="py-3 px-4">Part No#</th>
                            <th class="py-3 px-4">Description</th>
                            <th class="py-3 px-4">Application</th>
                            <th class="py-3 px-4 text-right">Last Cost</th>
                            <th class="py-3 px-4 text-right">New Cost</th>
                            <th class="py-3 px-4 text-center">Order Qty</th>
                            <th class="py-3 px-4 w-16">Unit</th>
                            <th class="py-3 px-4">Ordered Date</th>
                            <th class="py-3 px-4">PO Number</th>
                            <th class="py-3 px-4">PO Date</th>
                            <th class="py-3 px-4 text-center">Actual Qty</th>
                        </tr>
                        <tr class="border-b border-slate-100 bg-white">
                            <th class="py-2 px-4"><input type="text" oninput="filterNotArrivedColumn(this, 'item_code')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-amber-500/20"></th>
                            <th class="py-2 px-4"><input type="text" oninput="filterNotArrivedColumn(this, 'part_no')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-amber-500/20"></th>
                            <th class="py-2 px-4"><input type="text" oninput="filterNotArrivedColumn(this, 'description')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-amber-500/20"></th>
                            <th class="py-2 px-4"><input type="text" oninput="filterNotArrivedColumn(this, 'application')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-amber-500/20"></th>
                            <th class="py-2 px-4"><input type="text" oninput="filterNotArrivedColumn(this, 'last_cost')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md text-right focus:outline-none focus:ring-1 focus:ring-amber-500/20"></th>
                            <th class="py-2 px-4"><input type="text" oninput="filterNotArrivedColumn(this, 'new_cost')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md text-right focus:outline-none focus:ring-1 focus:ring-amber-500/20"></th>
                            <th class="py-2 px-4"><input type="text" oninput="filterNotArrivedColumn(this, 'order_qty')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md text-center focus:outline-none focus:ring-1 focus:ring-amber-500/20"></th>
                            <th class="py-2 px-4"><input type="text" oninput="filterNotArrivedColumn(this, 'oum_unit')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-amber-500/20"></th>
                            <th class="py-2 px-4"><input type="text" oninput="filterNotArrivedColumn(this, 'ordered_date')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-amber-500/20"></th>
                            <th class="py-2 px-4"><input type="text" oninput="filterNotArrivedColumn(this, 'po_number')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-amber-500/20"></th>
                            <th class="py-2 px-4"><input type="text" oninput="filterNotArrivedColumn(this, 'po_date')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-amber-500/20"></th>
                            <th class="py-2 px-4"><input type="text" oninput="filterNotArrivedColumn(this, 'actual_qty')" placeholder="Filter..." class="w-full px-2 py-1 text-[10px] border border-slate-200 rounded-md text-center focus:outline-none focus:ring-1 focus:ring-amber-500/20"></th>
                        </tr>
                    </thead>
                    <tbody id="not-arrived-table-body" class="divide-y divide-slate-100 text-sm"></tbody>
                </table>
            </div>

            <div class="p-4 border-t border-slate-100 bg-slate-50/30 flex items-center justify-between gap-4 flex-wrap">
                <span id="not-arrived-range-info" class="text-xs text-slate-500 font-medium">Showing 0-0 of 0</span>
                <div class="flex items-center gap-2">
                    <button id="not-arrived-prev-btn" onclick="changeNotArrivedPage(-1)" class="px-3 py-1.5 text-xs font-bold rounded-lg border border-slate-100 text-slate-300 cursor-not-allowed">Prev</button>
                    <span id="not-arrived-page-indicator" class="text-xs font-semibold text-slate-600 min-w-[90px] text-center">Page 1 of 1</span>
                    <button id="not-arrived-next-btn" onclick="changeNotArrivedPage(1)" class="px-3 py-1.5 text-xs font-bold rounded-lg border border-slate-100 text-slate-300 cursor-not-allowed">Next</button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ROLLBACK CONFIRMATION MODAL --}}
<div id="rollback-modal" class="fixed inset-0 z-[120] hidden">
    <div class="viber-modal-overlay fixed inset-0" onclick="closeRollbackModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 text-center">
            <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-red-50 flex items-center justify-center">
                <i data-lucide="rotate-ccw" class="w-7 h-7 text-red-500"></i>
            </div>
            <h3 class="text-base font-bold text-slate-800">Rollback Not Arrived Items</h3>
            <p class="text-sm text-slate-500 mt-2">Are you sure you want to rollback all Not Arrived items? Remaining quantities will be returned to Ready to Shipped Items and the current partial note will be closed.</p>
            <div class="flex items-center justify-center gap-3 mt-6">
                <button onclick="closeRollbackModal()" class="px-5 py-2 bg-white border border-slate-200 text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all">Cancel</button>
                <button id="confirm-rollback-btn" onclick="confirmRollback()" class="px-5 py-2 bg-red-600 text-white text-xs font-bold rounded-xl hover:bg-red-700 transition-all shadow-sm">Confirm Rollback</button>
            </div>
        </div>
    </div>
</div>

{{-- ROLLBACK SUCCESS MODAL --}}
<div id="rollback-success-modal" class="fixed inset-0 z-[120] hidden">
    <div class="viber-modal-overlay fixed inset-0" onclick="closeRollbackSuccessModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 text-center">
            <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-emerald-50 flex items-center justify-center">
                <i data-lucide="check-circle" class="w-7 h-7 text-emerald-500"></i>
            </div>
            <h3 class="text-base font-bold text-slate-800">Rollback Completed</h3>
            <p class="text-sm text-slate-500 mt-2" id="rollback-success-message">Remaining quantities were returned to Ready to Shipped Items.</p>
            <div class="flex items-center justify-center gap-3 mt-6">
                <button onclick="closeRollbackSuccessModal()" class="px-5 py-2 bg-emerald-600 text-white text-xs font-bold rounded-xl hover:bg-emerald-700 transition-all shadow-sm">OK</button>
            </div>
        </div>
    </div>
</div>

{{-- DELETE SUPPLIER CONFIRMATION MODAL --}}
<div id="delete-supplier-modal" class="fixed inset-0 z-[120] hidden">
    <div class="viber-modal-overlay fixed inset-0" onclick="closeDeleteSupplierModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 text-center">
            <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-red-50 flex items-center justify-center">
                <i data-lucide="alert-triangle" class="w-7 h-7 text-red-500"></i>
            </div>
            <h3 class="text-base font-bold text-slate-800">Delete Supplier</h3>
            <p class="text-sm text-slate-500 mt-2">Are you sure you want to delete this supplier tab? All items under this supplier will also be deleted.</p>
            <div class="flex items-center justify-center gap-3 mt-6">
                <button onclick="closeDeleteSupplierModal()" class="px-5 py-2 bg-white border border-slate-200 text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all">Cancel</button>
                <button id="confirm-delete-supplier-btn" onclick="confirmDeleteSupplier()" class="px-5 py-2 bg-red-600 text-white text-xs font-bold rounded-xl hover:bg-red-700 transition-all shadow-sm">Confirm Delete</button>
            </div>
        </div>
    </div>
</div>

{{-- DELETE ITEM CONFIRMATION MODAL --}}
<div id="delete-item-modal" class="fixed inset-0 z-[120] hidden">
    <div class="viber-modal-overlay fixed inset-0" onclick="closeDeleteItemModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 text-center">
            <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-red-50 flex items-center justify-center">
                <i data-lucide="trash-2" class="w-7 h-7 text-red-500"></i>
            </div>
            <h3 class="text-base font-bold text-slate-800">Delete Item</h3>
            <p class="text-sm text-slate-500 mt-2">Are you sure you want to delete this item?</p>
            <div class="flex items-center justify-center gap-3 mt-6">
                <button onclick="closeDeleteItemModal()" class="px-5 py-2 bg-white border border-slate-200 text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all">Cancel</button>
                <button id="confirm-delete-item-btn" onclick="confirmDeleteItem()" class="px-5 py-2 bg-red-600 text-white text-xs font-bold rounded-xl hover:bg-red-700 transition-all shadow-sm">Confirm Delete</button>
            </div>
        </div>
    </div>
</div>

{{-- DUPLICATE WARNING MODAL --}}
<div id="duplicate-warning-modal" class="fixed inset-0 z-[130] hidden">
    <div class="viber-modal-overlay fixed inset-0" onclick="cancelDuplicateContinue()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 text-center">
            <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-amber-50 flex items-center justify-center">
                <i data-lucide="alert-triangle" class="w-7 h-7 text-amber-500"></i>
            </div>
            <h3 class="text-base font-bold text-slate-800">Duplicate Item Warning</h3>
            <p class="text-sm text-slate-500 mt-2">Warning: This item already exists in this supplier table. Duplicate items are allowed, but please review before saving.</p>
            <div class="flex items-center justify-center gap-3 mt-6">
                <button onclick="cancelDuplicateContinue()" class="px-5 py-2 bg-white border border-slate-200 text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all">Cancel</button>
                <button id="confirm-duplicate-continue-btn" onclick="confirmDuplicateContinue()" class="px-5 py-2 bg-amber-600 text-white text-xs font-bold rounded-xl hover:bg-amber-700 transition-all shadow-sm">Continue Anyway</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        window.csrfToken = '{{ csrf_token() }}';
        window.viberReadOnly = true;
        window.viberRoutes = {
            suppliers: '{{ route("special.purchase-viber.suppliers") }}',
            products: '{{ route("special.purchase-viber.products") }}',
            list: '{{ route("special.purchase-viber.list") }}',
            storeSupplier: '{{ route("special.purchase-viber.store-supplier") }}',
            storeItems: '{{ route("special.purchase-viber.store-items") }}',
            items: '{{ route("special.purchase-viber.items", ["viberListId" => ":viberListId"]) }}',
            destroySupplier: '{{ route("special.purchase-viber.destroy", ["id" => ":id"]) }}',
            destroyItem: '{{ route("special.purchase-viber.items.destroy", ["id" => ":id"]) }}',
            updateItem: '{{ route("special.purchase-viber.items.update", ["id" => ":id"]) }}',
            prepareNote: '{{ route("special.purchase-viber.prepare-note") }}',
            counts: '{{ route("special.purchase-viber.counts") }}',
            invoicedItems: '{{ route("special.purchase-viber.invoiced-items") }}',
            notArrivedItems: '{{ route("special.purchase-viber.not-arrived-items") }}',
            notArrivedRollback: '{{ route("special.purchase-viber.not-arrived-rollback") }}',
            purchaseNote: '{{ route("special.purchase-note") }}',
            shippedPrintItems: '{{ route("special.purchase-viber.shipped-print-items", ["viberListId" => ":viberListId"]) }}',
        };
    </script>
    <script src="{{ asset('js/Purchase-Viber.js') }}?v={{ time() }}"></script>
@endpush

@endsection


