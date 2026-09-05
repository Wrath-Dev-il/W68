@extends('partials.user_account.user_sidebar_navbar')

@push('styles')
    @vite(['resources/css/Waybill.css'])
@endpush

@section('waybill_content')
<div id="page-waybill-root" class="space-y-8 animate-fade-in text-slate-800">

    <!-- HEADER -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-maroon tracking-tight">Waybill Management</h1>
            <p class="text-sm text-slate-500">Manage and process waybills.</p>
        </div>
    </div>

    <!-- DASHBOARD CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between hover-card-trigger">
            <div class="space-y-1">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">No Waybill</p>
                <h3 id="wb-card-total" class="text-2xl font-extrabold text-slate-800">---</h3>
            </div>
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-maroon shadow-inner">
                <i data-lucide="truck" class="w-7 h-7 text-gold"></i>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between hover-card-trigger">
            <div class="space-y-1">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">New Waybill</p>
                <h3 id="wb-card-new" class="text-2xl font-extrabold text-slate-800">---</h3>
            </div>
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-maroon shadow-inner">
                <i data-lucide="file-plus" class="w-7 h-7 text-gold"></i>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between hover-card-trigger">
            <div class="space-y-1">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Over Due</p>
                <h3 id="wb-card-overdue" class="text-2xl font-extrabold text-slate-800">---</h3>
            </div>
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-maroon shadow-inner">
                <i data-lucide="alert-triangle" class="w-7 h-7 text-gold"></i>
            </div>
        </div>
    </div>

    <!-- TABS -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden text-slate-800">
        <div class="p-5 border-b border-slate-50 bg-slate-50/30">
            <div class="flex items-center space-x-4 flex-wrap">
                <button onclick="switchMainTab('pending')" id="tab-btn-pending" class="px-6 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all duration-200 bg-maroon text-white shadow-md">Pending Orders</button>
                <button onclick="switchMainTab('history')" id="tab-btn-history" class="px-6 py-2.5 rounded-xl text-xs font-bold uppercase tracking-widest transition-all duration-200 text-slate-500 hover:bg-slate-50">Waybill History</button>
            </div>
        </div>
    </div>

    <!-- PENDING ORDERS VIEW -->
    <div id="view-pending" class="main-tab-view">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden text-slate-800">
            <div class="p-5 border-b border-slate-50 flex justify-between items-center bg-slate-50/30">
                <h2 class="font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="list" class="w-4 h-4 text-maroon"></i>
                    Pending Orders
                </h2>
                <div class="flex items-center space-x-2">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Showing:</span>
                    <span class="px-3 py-1 bg-maroon/10 text-maroon text-[10px] font-bold rounded-lg uppercase">Pending Queue</span>
                </div>
            </div>
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50/80 border-b border-slate-100">
                        <tr>
                            <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Customer</th>
                            <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Active Invoices</th>
                            <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Action</th>
                        </tr>
                        <tr class="bg-slate-50/30 border-b border-slate-100">
                            <th class="p-2 px-5"><input type="text" data-col="customer_name" onkeyup="searchPendingWaybills(0, this.value)" placeholder="Search Customer..." class="column-search-input"></th>
                            <th class="p-2 px-5"><input type="text" data-col="invoice_number" onkeyup="searchPendingWaybills(1, this.value)" placeholder="Search Invoice..." class="column-search-input"></th>
                            <th class="p-2 px-5"></th>
                        </tr>
                    </thead>
                    <tbody id="wb-main-tbody" class="divide-y divide-slate-100 text-sm">
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-slate-100 bg-slate-50/30 flex justify-between items-center text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                <p id="pending-page-info">Showing 0 of 0 Invoices</p>
                <div class="flex space-x-2">
                    <button onclick="changePendingPage(-1)" id="pending-prev-btn" class="px-3 py-1 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-50">Prev</button>
                    <button onclick="changePendingPage(1)" id="pending-next-btn" class="px-3 py-1 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-50">Next</button>
                </div>
            </div>
        </div>
    </div>

    <!-- WAYBILL HISTORY VIEW -->
    <div id="view-history" class="main-tab-view hidden">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden text-slate-800">
            <div class="p-5 border-b border-slate-50 flex justify-between items-center bg-slate-50/30">
                <h2 class="font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="history" class="w-4 h-4 text-maroon"></i>
                    Waybill History
                </h2>
                <div id="history-actions" class="hidden flex items-center space-x-3">
                    <button onclick="batchPrintWaybills()" class="px-4 py-2 bg-maroon text-white text-[10px] font-bold rounded-xl hover:bg-maroon-800 transition-all shadow-lg flex items-center space-x-2">
                        <i data-lucide="printer" class="w-3.5 h-3.5 text-gold"></i>
                        <span>Print Selected</span>
                    </button>
                </div>
            </div>
            <div class="px-5 py-4 bg-slate-50/30 border-b border-slate-100">
                <div class="relative">
                    <input type="text" id="wb-history-search" onkeyup="searchWaybillHistoryMain(this.value)" placeholder="Search invoice, customer, forwarder, waybill sequence/no, or order..." class="w-full px-4 py-2.5 pl-10 border border-slate-200 rounded-xl text-xs outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon/30 transition-all">
                    <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                    <button id="wb-history-search-clear" type="button" onclick="clearHistorySearch()" class="absolute right-3 top-1/2 -translate-y-1/2 hidden text-slate-400 hover:text-slate-600 transition-all"><i data-lucide="x" class="w-4 h-4"></i></button>
                </div>
            </div>
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50/80 border-b border-slate-100">
                        <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                            <th class="p-4 px-6 w-12"><input type="checkbox" onchange="toggleSelectAllWaybills(this)" class="accent-maroon cursor-pointer"></th>
                            <th class="p-4 px-6">Waybill Sequence</th>
                            <th class="p-4 px-6">Waybill No.</th>
                            <th class="p-4 px-6">Customer</th>
                            <th class="p-4 px-6">Invoices</th>
                            <th class="p-4 px-6">Date</th>
                            <th class="p-4 px-6 text-center">Action</th>
                        </tr>
                        <tr class="bg-slate-50/30 border-b border-slate-100">
                            <th class="p-2 px-5"></th>
                            <th class="p-2 px-5"><input type="text" data-col="waybill_sequence" onkeyup="searchWaybillHistory(1, this.value)" placeholder="Search Seq..." class="column-search-input"></th>
                            <th class="p-2 px-5"><input type="text" data-col="waybill_no" onkeyup="searchWaybillHistory(2, this.value)" placeholder="Search WB No..." class="column-search-input"></th>
                            <th class="p-2 px-5"><input type="text" data-col="customer_name" onkeyup="searchWaybillHistory(3, this.value)" placeholder="Search Customer..." class="column-search-input"></th>
                            <th class="p-2 px-5"><input type="text" data-col="invoice_numbers" onkeyup="searchWaybillHistory(4, this.value)" placeholder="Search Invoice..." class="column-search-input"></th>
                            <th class="p-2 px-5"></th>
                            <th class="p-2 px-5"></th>
                        </tr>
                    </thead>
                    <tbody id="wb-history-tbody" class="divide-y divide-slate-100 text-sm">
                        <tr><td colspan="7" class="p-8 text-center text-slate-300 italic">No waybill history found.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-slate-100 bg-slate-50/30 flex justify-between items-center text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                <p id="history-page-info">Showing 0 of 0 Waybills</p>
                <div class="flex space-x-2">
                    <button onclick="changeHistoryPage(-1)" id="history-prev-btn" class="px-3 py-1 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-50">Prev</button>
                    <button onclick="changeHistoryPage(1)" id="history-next-btn" class="px-3 py-1 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-50">Next</button>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ============== WAYBILL MODAL (3-STEP) ============== -->
<div id="wb-waybill-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('wb-waybill-modal', false)" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-4xl w-full modal-animate-in mx-4 flex flex-col max-h-[95vh]">

        <!-- Modal Header -->
        <div class="bg-maroon p-6 flex justify-between items-center text-white border-b-4 border-gold">
            <div class="flex items-center space-x-4">
                <div class="p-3 bg-white/10 rounded-2xl border-2 border-gold shadow-sm">
                    <i data-lucide="map" class="w-6 h-6 text-gold"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold tracking-tight">Waybill Setup</h3>
                    <p class="text-[10px] text-white/60 uppercase tracking-widest font-bold">Create waybill</p>
                </div>
            </div>
            <button onclick="toggleModal('wb-waybill-modal', false)" class="text-white/70 hover:text-white"><i data-lucide="x" class="w-6 h-6"></i></button>
        </div>

        <!-- Step Progress -->
        <div class="px-6 pt-6 pb-4 bg-slate-50 border-b border-slate-100">
            <div class="flex items-center justify-center space-x-6">
                <div class="flex items-center space-x-2">
                    <div id="wbstep-indicator-1" class="step-indicator active">1</div>
                    <span id="wbstep-label-1" class="text-[10px] font-bold text-maroon uppercase tracking-widest">Basic Info</span>
                </div>
                <div class="w-16 h-0.5 bg-slate-200 relative">
                    <div id="wbstep-progress-1" class="absolute top-0 left-0 h-full bg-gold transition-all" style="width:0%"></div>
                </div>
                <div class="flex items-center space-x-2">
                    <div id="wbstep-indicator-2" class="step-indicator pending">2</div>
                    <span id="wbstep-label-2" class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Select SO</span>
                </div>
                <div class="w-16 h-0.5 bg-slate-200 relative">
                    <div id="wbstep-progress-2" class="absolute top-0 left-0 h-full bg-gold transition-all" style="width:0%"></div>
                </div>
                <div class="flex items-center space-x-2">
                    <div id="wbstep-indicator-3" class="step-indicator pending">3</div>
                    <span id="wbstep-label-3" class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Cargo Info</span>
                </div>
            </div>
        </div>

        <!-- STEP 1: Basic Information -->
        <div id="wb-step-1" class="wb-step-content flex flex-col md:flex-row p-8 overflow-y-auto flex-1 min-h-0 gap-8">
            <div class="w-full md:w-[30%] space-y-4">
                <div class="p-6 bg-maroon rounded-3xl shadow-xl text-white space-y-6 relative overflow-hidden">
                    <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 bg-gold/10 rounded-full blur-2xl"></div>
                    <h4 class="text-sm font-bold uppercase tracking-widest text-gold border-b border-white/10 pb-3">Basic Information</h4>
                    <div>
                        <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Waybill Sequence</p>
                        <p id="wb-display-no" class="text-xs font-mono font-bold tracking-wider">00000001</p>
                    </div>
                    <div>
                        <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Date</p>
                        <p id="wb-display-date" class="text-xs font-bold">---</p>
                    </div>
                    <div>
                        <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Customer Name</p>
                        <p id="wb-display-customer" class="text-sm font-bold text-gold break-words leading-snug">---</p>
                    </div>
                    <div>
                        <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Active Invoices</p>
                        <p id="wb-display-so" class="text-xs font-bold">---</p>
                    </div>
                </div>
            </div>
            <div class="w-full md:w-[70%] space-y-4">
                <div class="p-6 bg-white rounded-3xl border border-slate-100 shadow-sm space-y-5">
                    <h4 class="text-xs font-bold uppercase tracking-widest text-maroon border-b border-slate-100 pb-3">Forwarder Information</h4>
                    <div class="relative">
                        <label class="text-[9px] uppercase font-bold text-slate-500 tracking-widest mb-1.5 block">Forwarder</label>
                        <input type="text" id="wb-forwarder" autocomplete="off" placeholder="Enter forwarder name..." class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon/30 transition-all">
                        <div id="forwarder-suggestions" class="absolute z-[100] left-0 right-0 mt-1 bg-white border border-slate-100 rounded-xl shadow-xl hidden overflow-hidden max-h-48 overflow-y-auto">
                        </div>
                    </div>
                    <div class="mt-5">
                        <label class="text-[9px] uppercase font-bold text-slate-500 tracking-widest mb-1.5 block">Waybill No.</label>
                        <input type="text" id="wb-waybill-no" name="waybill_no" placeholder="Enter external tracking no..." maxlength="255" class="w-full md:w-1/2 px-4 py-3 border border-slate-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon/30 transition-all">
                        <p class="text-[10px] text-slate-400 mt-1">Enter the forwarder's external tracking or waybill number.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- STEP 2: Select Sales Order -->
        <div id="wb-step-2" class="wb-step-content hidden flex flex-col gap-6 p-8 overflow-y-auto flex-1 min-h-0">
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="p-4 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
                    <h4 class="text-xs font-bold text-slate-800 uppercase tracking-widest">Selected Sales Order</h4>
                    <button onclick="openSOModal()" class="px-4 py-2 bg-maroon text-white text-[9px] font-bold rounded-lg hover:bg-maroon-800 transition-all flex items-center space-x-1">
                        <i data-lucide="search" class="w-3 h-3 text-gold"></i>
                        <span>Browse Orders</span>
                    </button>
                </div>
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50/80 border-b border-slate-100">
                            <tr class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">
                                <th class="p-4">Invoice No</th>
                                <th class="p-4">Items</th>
                                <th class="p-4 text-right">Total Amount</th>
                                <th class="p-4 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="wb-selected-so-tbody" class="text-xs divide-y divide-slate-50">
                            <tr><td colspan="4" class="p-4 text-center text-slate-300 italic">No orders selected.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-6 px-2">
                <div class="flex items-center space-x-2">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-widest">Total Value:</span>
                    <span id="wb-total-value" class="text-lg font-extrabold text-maroon">₱ 0.00</span>
                </div>
                <div class="flex items-center space-x-2">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-widest">% to Declare:</span>
                    <input type="number" id="wb-pct-declare" value="100" min="0" max="100" step="1" oninput="calcDeclared()" class="w-20 px-3 py-2 border border-slate-200 rounded-lg text-sm text-center font-bold outline-none focus:ring-2 focus:ring-maroon/20">
                </div>
                <div class="flex items-center space-x-2">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-widest">Declared Value:</span>
                    <input type="number" id="wb-declared-value" step="0.01" min="0" class="w-40 px-3 py-2 border border-amber-200 rounded-lg text-sm text-right font-bold text-maroon outline-none focus:ring-2 focus:ring-amber-300 bg-amber-50">
                </div>
            </div>
        </div>

        <!-- STEP 3: Cargo Information -->
        <div id="wb-step-3" class="wb-step-content hidden flex flex-col gap-6 p-8 overflow-y-auto flex-1 min-h-0">
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="p-4 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
                    <h4 class="text-xs font-bold text-slate-800 uppercase tracking-widest">Cargo Information</h4>
                    <button onclick="addCargoRow()" class="px-3 py-1.5 bg-maroon text-white text-[9px] font-bold rounded-lg hover:bg-maroon-800 transition-all flex items-center space-x-1">
                        <i data-lucide="plus" class="w-3 h-3 text-gold"></i>
                        <span>Add Item</span>
                    </button>
                </div>
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50/80 border-b border-slate-100">
                            <tr class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">
                                <th class="p-4 text-center" style="width: 8rem; min-width: 8rem;">QTY</th>
                                <th class="p-4 text-center w-24">UNIT</th>
                                <th class="p-4">CARGO DESCRIPTION</th>
                                <th class="p-4 text-center w-16"></th>
                            </tr>
                        </thead>
                        <tbody id="wb-cargo-tbody" class="text-xs divide-y divide-slate-50">
                            <tr>
                                <td class="p-2 px-3 text-center"><input type="number" value="1" min="1" class="wb-cargo-qty w-full px-2 py-1.5 border border-slate-200 rounded-lg text-center font-bold outline-none focus:ring-2 focus:ring-maroon/20" style="min-width: 7.25rem;"></td>
                                <td class="p-2 px-3 text-center"><input type="text" value="PCS" class="wb-cargo-unit w-full px-2 py-1.5 border border-slate-200 rounded-lg text-center uppercase outline-none focus:ring-2 focus:ring-maroon/20"></td>
                                <td class="p-2 px-3"><input type="text" placeholder="Enter cargo description..." class="wb-cargo-desc w-full px-2 py-1.5 border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-maroon/20"></td>
                                <td class="p-2 px-3 text-center"><button onclick="this.closest('tr').remove()" class="p-1.5 bg-red-50 text-red-400 hover:bg-red-100 hover:text-red-600 rounded-lg transition-all"><i data-lucide="x" class="w-3.5 h-3.5"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="p-6 bg-slate-50 border-t border-slate-100 flex justify-between items-center">
            <button id="wb-btn-back" onclick="goToStep(currentStep - 1)" class="hidden px-6 py-2.5 text-xs font-bold text-slate-400 hover:text-maroon transition-all uppercase tracking-widest flex items-center space-x-2">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                <span>Back</span>
            </button>
            <div class="flex space-x-3 ml-auto">
                <button onclick="toggleModal('wb-waybill-modal', false)" class="px-6 py-2.5 text-xs font-bold text-slate-400 hover:text-slate-600 transition-all uppercase tracking-widest">Cancel</button>
                <button id="wb-btn-next" onclick="goToStep(2)" class="px-8 py-2.5 bg-maroon text-white rounded-xl text-xs font-bold hover:bg-maroon-800 transition-all shadow-lg flex items-center space-x-2 uppercase tracking-widest">
                    <span id="wb-next-text">Next Step</span>
                    <i data-lucide="chevron-right" class="w-4 h-4 text-gold"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============== SELECT SALES ORDER MODAL ============== -->
<div id="wb-so-select-modal" class="fixed inset-0 z-[60] hidden flex items-center justify-center">
    <div onclick="toggleModal('wb-so-select-modal', false)" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-5xl w-full modal-animate-in mx-4 flex flex-col max-h-[90vh]">
        <div class="bg-maroon p-5 flex justify-between items-center text-white border-b-4 border-gold">
            <h3 class="text-sm font-bold uppercase tracking-widest flex items-center space-x-2">
                <i data-lucide="shopping-cart" class="w-5 h-5 text-gold"></i>
                <span>Sales Order</span>
            </h3>
            <button type="button" onclick="toggleModal('wb-so-select-modal', false)" class="text-white/70 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>

        <div class="px-6 pt-4 pb-2">
            <div class="relative">
                <input
                    type="text"
                    id="wb-so-search"
                    oninput="searchSOModal()"
                    placeholder="Search invoice, order no., customer, date, or amount..."
                    autocomplete="off"
                    class="w-full px-4 py-2.5 pl-10 border border-slate-200 rounded-xl text-xs outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon/30 transition-all"
                >
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                <button type="button" id="wb-so-search-clear" onclick="clearSOSearch()" class="absolute right-3 top-1/2 -translate-y-1/2 hidden text-slate-400 hover:text-slate-600 transition-all" aria-label="Clear search">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
            <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-2">
                    <input
                        type="date"
                        id="wb-so-specific-date"
                        class="px-3 py-2 border border-slate-200 rounded-xl text-[10px] font-bold text-slate-600 outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon/30 transition-all"
                        aria-label="Specific invoice date"
                    >
                    <button type="button" onclick="selectInvoicesByDate()" class="px-4 py-2 bg-maroon text-white text-[10px] font-bold rounded-xl hover:bg-maroon-800 transition-all shadow-sm flex items-center gap-2 uppercase tracking-widest">
                        <i data-lucide="calendar-check" class="w-4 h-4 text-gold"></i>
                        <span>Specific Date</span>
                    </button>
                </div>
                <div class="flex items-center gap-2 text-[9px] font-bold uppercase tracking-widest text-slate-400">
                    <span class="inline-block h-3 w-3 rounded-sm" style="background-color:#4b0b12;"></span>
                    <span>Dark maroon = duplicate invoice</span>
                </div>
            </div>
        </div>

        <div class="p-6 pt-2 overflow-y-auto flex-1">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50/95 border-b border-slate-100 sticky top-0 z-10">
                    <tr class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">
                        <th class="p-3 text-center w-12">
                            <input id="wb-so-select-all" type="checkbox" onchange="toggleAllSO(this)" class="accent-maroon cursor-pointer" aria-label="Select all visible invoices">
                        </th>
                        <th class="p-3">
                            <button type="button" onclick="sortSOModal('invoice')" class="group flex items-center gap-1.5 uppercase tracking-widest hover:text-maroon transition-colors">
                                <span>Invoice No</span>
                                <span id="wb-so-sort-invoice" class="text-[11px] text-slate-300 group-hover:text-maroon">↕</span>
                            </button>
                        </th>
                        <th class="p-3">
                            <button type="button" onclick="sortSOModal('customer')" class="group flex items-center gap-1.5 uppercase tracking-widest hover:text-maroon transition-colors">
                                <span>Customer</span>
                                <span id="wb-so-sort-customer" class="text-[11px] text-slate-300 group-hover:text-maroon">↕</span>
                            </button>
                        </th>
                        <th class="p-3 text-right">
                            <button type="button" onclick="sortSOModal('amount')" class="group ml-auto flex items-center gap-1.5 uppercase tracking-widest hover:text-maroon transition-colors">
                                <span>Total Amount</span>
                                <span id="wb-so-sort-amount" class="text-[11px] text-slate-300 group-hover:text-maroon">↕</span>
                            </button>
                        </th>
                        <th class="p-3">
                            <button type="button" onclick="sortSOModal('date')" class="group flex items-center gap-1.5 uppercase tracking-widest hover:text-maroon transition-colors">
                                <span>Date</span>
                                <span id="wb-so-sort-date" class="text-[11px] text-slate-300 group-hover:text-maroon">↕</span>
                            </button>
                        </th>
                        <th class="p-3 text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="wb-so-tbody" class="text-xs divide-y divide-slate-50"></tbody>
            </table>
            <div id="wb-so-no-results" class="hidden p-8 text-center text-slate-300 italic">No invoices match your search.</div>
        </div>

        <div class="p-4 border-t border-slate-100 bg-slate-50/60 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-x-5 gap-y-1 text-[10px] uppercase tracking-widest">
                <div class="text-slate-500">
                    Selected invoices:
                    <span id="wb-so-selected-count" class="font-bold text-maroon">0</span>
                </div>
                <div class="text-slate-500">
                    Selected amount:
                    <span id="wb-so-selected-total" class="font-bold text-maroon">₱ 0.00</span>
                </div>
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="toggleModal('wb-so-select-modal', false)" class="px-4 py-2.5 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest">Cancel</button>
                <button type="button" onclick="confirmSelectedSO()" class="px-6 py-2.5 bg-maroon text-white text-xs font-bold rounded-xl hover:bg-maroon-800 transition-all uppercase tracking-widest shadow-lg flex items-center space-x-2">
                    <i data-lucide="check" class="w-4 h-4 text-gold"></i>
                    <span>Confirm Selection</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============== CONFIRM MODAL ============== -->
<div id="wb-confirm-modal" class="fixed inset-0 z-[100] hidden flex items-center justify-center">
    <div onclick="toggleModal('wb-confirm-modal', false)" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-sm sm:w-full modal-animate-in mx-4">
        <div class="p-6 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-amber-50 mb-4">
                <i data-lucide="alert-triangle" class="h-6 w-6 text-amber-600"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-800 mb-2 tracking-tight uppercase">Confirm Waybill</h3>
            <p class="text-xs text-slate-500 mb-4 leading-relaxed">Are you sure you want to finalize this waybill?</p>
        </div>
        <div class="bg-slate-50 px-6 py-4 flex flex-col sm:flex-row-reverse gap-2">
            <button onclick="finalizeWaybill()" class="w-full px-4 py-2 bg-maroon hover:bg-maroon-800 text-white text-xs font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg">Yes, Confirm</button>
            <button onclick="toggleModal('wb-confirm-modal', false)" class="w-full px-4 py-2 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest">No, Review</button>
        </div>
    </div>
</div>

<!-- ============== SUCCESS MODAL ============== -->
<div id="wb-success-modal" class="fixed inset-0 z-[120] hidden flex items-center justify-center">
    <div onclick="toggleModal('wb-success-modal', false)" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-sm sm:w-full modal-animate-in mx-4">
        <div class="p-8 text-center">
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-emerald-50 mb-6">
                <i data-lucide="check-circle" class="h-10 w-10 text-emerald-500"></i>
            </div>
            <h3 class="text-xl font-bold text-slate-800 mb-2">Success!</h3>
            <p class="text-xs text-slate-500 leading-relaxed">The waybill has been created successfully.</p>
        </div>
        <div class="p-6 pt-0">
            <button onclick="toggleModal('wb-success-modal', false); location.reload()" class="w-full px-4 py-3 bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg">Great, Continue</button>
        </div>
    </div>
</div>

<!-- ============== EDIT WAYBILL MODAL (3-STEP) ============== -->
<div id="wb-edit-modal" class="fixed inset-0 z-[100] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="editCloseModal()" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-4xl w-full modal-animate-in mx-4 flex flex-col max-h-[95vh]">

        <!-- Modal Header -->
        <div class="bg-amber-600 p-6 flex justify-between items-center text-white border-b-4 border-amber-300">
            <div class="flex items-center space-x-4">
                <div class="p-3 bg-white/10 rounded-2xl border-2 border-amber-200/30">
                    <i data-lucide="edit-3" class="w-6 h-6"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold tracking-tight">Edit Waybill</h3>
                    <p class="text-[10px] text-white/60 uppercase tracking-widest font-bold">Waybill Sequence: <span id="edit-waybill-number"></span></p>
                </div>
            </div>
            <button onclick="editCloseModal()" class="text-white/70 hover:text-white"><i data-lucide="x" class="w-6 h-6"></i></button>
        </div>

        <!-- Step Progress -->
        <div class="px-6 pt-6 pb-4 bg-slate-50 border-b border-slate-100">
            <div class="flex items-center justify-center space-x-6">
                <div class="flex items-center space-x-2">
                    <div id="edit-step-indicator-1" class="step-indicator active">1</div>
                    <span id="edit-step-label-1" class="text-[10px] font-bold text-amber-700 uppercase tracking-widest">Basic Info</span>
                </div>
                <div class="w-16 h-0.5 bg-slate-200 relative">
                    <div id="edit-step-progress-1" class="absolute top-0 left-0 h-full bg-gold transition-all" style="width:0%"></div>
                </div>
                <div class="flex items-center space-x-2">
                    <div id="edit-step-indicator-2" class="step-indicator pending">2</div>
                    <span id="edit-step-label-2" class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Select Invoices</span>
                </div>
                <div class="w-16 h-0.5 bg-slate-200 relative">
                    <div id="edit-step-progress-2" class="absolute top-0 left-0 h-full bg-gold transition-all" style="width:0%"></div>
                </div>
                <div class="flex items-center space-x-2">
                    <div id="edit-step-indicator-3" class="step-indicator pending">3</div>
                    <span id="edit-step-label-3" class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Cargo Info</span>
                </div>
            </div>
        </div>

        <!-- STEP 1: Basic Information -->
        <div id="edit-step-1" class="edit-step-content flex flex-col md:flex-row p-8 overflow-y-auto flex-1 min-h-0 gap-8">
            <div class="w-full md:w-[30%] space-y-4">
                <div class="p-6 bg-amber-600 rounded-3xl shadow-xl text-white space-y-6 relative overflow-hidden">
                    <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 bg-amber-300/10 rounded-full blur-2xl"></div>
                    <h4 class="text-sm font-bold uppercase tracking-widest text-amber-200 border-b border-white/10 pb-3">Waybill Info</h4>
                    <div>
                        <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Waybill No.</p>
                        <p id="edit-display-no" class="text-xs font-mono font-bold tracking-wider">---</p>
                    </div>
                    <div>
                        <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Date</p>
                        <p id="edit-display-date" class="text-xs font-bold">---</p>
                    </div>
                    <div>
                        <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Customer Name</p>
                        <p id="edit-display-customer" class="text-sm font-bold text-amber-200 break-words leading-snug">---</p>
                    </div>
                    <div>
                        <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Contact</p>
                        <p id="edit-display-contact" class="text-xs font-bold">---</p>
                    </div>
                </div>
            </div>
            <div class="w-full md:w-[70%] space-y-4">
                <div class="p-6 bg-white rounded-3xl border border-slate-100 shadow-sm space-y-5">
                    <h4 class="text-xs font-bold uppercase tracking-widest text-amber-700 border-b border-slate-100 pb-3">Edit Information</h4>
                    <div class="grid grid-cols-3 gap-5">
                        <div>
                            <label class="text-[9px] uppercase font-bold text-slate-500 tracking-widest mb-1.5 block">Waybill Date</label>
                            <input type="date" id="edit-waybill-date" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-amber-300/30 focus:border-amber-400 transition-all">
                        </div>
                        <div>
                            <label class="text-[9px] uppercase font-bold text-slate-500 tracking-widest mb-1.5 block">Waybill No.</label>
                            <input type="text" id="edit-waybill-no-input" name="waybill_no" placeholder="External Waybill No..." class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-amber-300/30 focus:border-amber-400 transition-all">
                        </div>
                        <div>
                            <label class="text-[9px] uppercase font-bold text-slate-500 tracking-widest mb-1.5 block">Forwarder</label>
                            <input type="text" id="edit-forwarder" autocomplete="off" placeholder="Enter forwarder name..." class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-amber-300/30 focus:border-amber-400 transition-all">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-5">
                        <div>
                            <label class="text-[9px] uppercase font-bold text-slate-500 tracking-widest mb-1.5 block">Total Value</label>
                            <input type="number" id="edit-total-value" step="0.01" min="0" oninput="editCalcDeclared()" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-amber-300/30 focus:border-amber-400 transition-all">
                        </div>
                        <div>
                            <label class="text-[9px] uppercase font-bold text-slate-500 tracking-widest mb-1.5 block">Declared Value</label>
                            <input type="number" id="edit-declared-value" step="0.01" min="0" class="w-full px-4 py-3 border border-amber-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-amber-300/30 focus:border-amber-400 transition-all bg-amber-50">
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-widest">% to Declare:</span>
                        <input type="number" id="edit-pct-declare" value="100" min="0" max="100" step="1" oninput="editCalcDeclared()" class="w-20 px-3 py-2 border border-slate-200 rounded-lg text-sm text-center font-bold outline-none focus:ring-2 focus:ring-amber-300/30">
                    </div>
                </div>
            </div>
        </div>

        <!-- STEP 2: Select Invoices -->
        <div id="edit-step-2" class="edit-step-content hidden flex flex-col gap-6 p-8 overflow-y-auto flex-1 min-h-0">
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="p-4 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
                    <h4 class="text-xs font-bold text-slate-800 uppercase tracking-widest">Attached Invoices</h4>
                    <button onclick="editOpenSOModal()" class="px-4 py-2 bg-amber-600 text-white text-[9px] font-bold rounded-lg hover:bg-amber-700 transition-all flex items-center space-x-1">
                        <i data-lucide="search" class="w-3 h-3"></i>
                        <span>Browse Orders</span>
                    </button>
                </div>
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50/80 border-b border-slate-100">
                            <tr class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">
                                <th class="p-4">Invoice No</th>
                                <th class="p-4 text-right">Total Amount</th>
                                <th class="p-4 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="edit-attached-invoices-tbody" class="text-xs divide-y divide-slate-50">
                            <tr><td colspan="3" class="p-4 text-center text-slate-300 italic">Loading invoices...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="flex items-center space-x-2 px-2">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-widest">Total Value:</span>
                <span id="edit-total-value-display" class="text-lg font-extrabold text-amber-600">₱ 0.00</span>
            </div>
        </div>

        <!-- STEP 3: Cargo Information -->
        <div id="edit-step-3" class="edit-step-content hidden flex flex-col gap-6 p-8 overflow-y-auto flex-1 min-h-0">
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="p-4 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
                    <h4 class="text-xs font-bold text-slate-800 uppercase tracking-widest">Cargo Information</h4>
                    <button onclick="editAddCargoRow()" class="px-3 py-1.5 bg-amber-600 text-white text-[9px] font-bold rounded-lg hover:bg-amber-700 transition-all flex items-center space-x-1">
                        <i data-lucide="plus" class="w-3 h-3"></i>
                        <span>Add Item</span>
                    </button>
                </div>
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50/80 border-b border-slate-100">
                            <tr class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">
                                <th class="p-4 text-center" style="width: 8rem; min-width: 8rem;">QTY</th>
                                <th class="p-4 text-center w-24">UNIT</th>
                                <th class="p-4">CARGO DESCRIPTION</th>
                                <th class="p-4 text-center w-16"></th>
                            </tr>
                        </thead>
                        <tbody id="edit-cargo-tbody" class="text-xs divide-y divide-slate-50">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="p-6 bg-slate-50 border-t border-slate-100 flex justify-between items-center">
            <button id="edit-btn-back" onclick="editGoToStep(editCurrentStep - 1)" class="hidden px-6 py-2.5 text-xs font-bold text-slate-400 hover:text-amber-600 transition-all uppercase tracking-widest flex items-center space-x-2">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                <span>Back</span>
            </button>
            <div class="flex space-x-3 ml-auto">
                <button onclick="editCloseModal()" class="px-6 py-2.5 text-xs font-bold text-slate-400 hover:text-slate-600 transition-all uppercase tracking-widest">Cancel</button>
                <button id="edit-btn-next" onclick="editGoToStep(2)" class="px-8 py-2.5 bg-amber-600 text-white rounded-xl text-xs font-bold hover:bg-amber-700 transition-all shadow-lg flex items-center space-x-2 uppercase tracking-widest">
                    <span id="edit-next-text">Next Step</span>
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============== EDIT SELECT SALES ORDER MODAL ============== -->
<div id="edit-so-select-modal" class="fixed inset-0 z-[110] hidden flex items-center justify-center">
    <div onclick="toggleModal('edit-so-select-modal', false)" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-5xl w-full modal-animate-in mx-4 flex flex-col max-h-[90vh]">
        <div class="bg-amber-600 p-5 flex justify-between items-center text-white border-b-4 border-amber-300">
            <h3 class="text-sm font-bold uppercase tracking-widest flex items-center space-x-2">
                <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                <span>Available Invoices</span>
            </h3>
            <button onclick="toggleModal('edit-so-select-modal', false)" class="text-white/70 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <div class="px-6 pt-4 pb-2">
            <div class="relative">
                <input type="text" id="edit-so-search" onkeyup="searchEditSOModal()" placeholder="Search invoice, order no., or customer..." class="w-full px-4 py-2.5 pl-10 border border-slate-200 rounded-xl text-xs outline-none focus:ring-2 focus:ring-amber-300/30 focus:border-amber-400 transition-all">
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                <button id="edit-so-search-clear" type="button" onclick="clearEditSOSearch()" class="absolute right-3 top-1/2 -translate-y-1/2 hidden text-slate-400 hover:text-slate-600 transition-all"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
        </div>
        <div class="p-6 pt-2 overflow-y-auto flex-1">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50/80 border-b border-slate-100 sticky top-0">
                    <tr class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">
                        <th class="p-3 text-center w-12"><input type="checkbox" onchange="document.querySelectorAll('#edit-so-tbody input[type=checkbox]').forEach(cb => { cb.checked = this.checked; editSelectSO(cb); })" class="accent-amber-600 cursor-pointer"></th>
                        <th class="p-3">Invoice No</th>
                        <th class="p-3 text-right">Total Amount</th>
                    </tr>
                </thead>
                <tbody id="edit-so-tbody" class="text-xs divide-y divide-slate-50">
                    <tr><td colspan="3" class="p-4 text-center text-slate-300 italic">Loading available invoices...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="p-4 bg-slate-50 border-t border-slate-100 flex justify-end">
            <button onclick="editConfirmSelectedSO()" class="px-6 py-2.5 bg-amber-600 text-white rounded-xl text-xs font-bold hover:bg-amber-700 transition-all shadow-lg uppercase tracking-widest">Add Selected</button>
        </div>
    </div>
</div>

<!-- ============== EDIT CONFIRM MODAL ============== -->
<div id="wb-edit-confirm-modal" class="fixed inset-0 z-[110] hidden flex items-center justify-center">
    <div onclick="toggleModal('wb-edit-confirm-modal', false)" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-sm sm:w-full modal-animate-in mx-4">
        <div class="p-6 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-amber-50 mb-4">
                <i data-lucide="alert-triangle" class="h-6 w-6 text-amber-600"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-800 mb-2 tracking-tight uppercase">Confirm Changes</h3>
            <p class="text-xs text-slate-500 mb-4 leading-relaxed">Are you sure you want to update this waybill?</p>
        </div>
        <div class="bg-slate-50 px-6 py-4 flex flex-col sm:flex-row-reverse gap-2">
            <button onclick="saveEditWaybill()" class="w-full px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg">Yes, Save</button>
            <button onclick="toggleModal('wb-edit-confirm-modal', false); toggleModal('wb-edit-modal', true)" class="w-full px-4 py-2 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest">No, Go Back</button>
        </div>
    </div>
</div>

<!-- ============== EDIT SUCCESS MODAL ============== -->
<div id="wb-edit-success-modal" class="fixed inset-0 z-[120] hidden flex items-center justify-center">
    <div onclick="closeEditSuccess()" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-sm sm:w-full modal-animate-in mx-4">
        <div class="p-8 text-center">
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-emerald-50 mb-6">
                <i data-lucide="check-circle" class="h-10 w-10 text-emerald-500"></i>
            </div>
            <h3 class="text-xl font-bold text-slate-800 mb-2">Success!</h3>
            <p class="text-xs text-slate-500 leading-relaxed">The waybill has been updated successfully.</p>
        </div>
        <div class="p-6 pt-0">
            <button onclick="closeEditSuccess()" class="w-full px-4 py-3 bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg">Great, Continue</button>
        </div>
    </div>
</div>

<!-- ============== DELETE CONFIRM MODAL ============== -->
<div id="wb-delete-confirm-modal" class="fixed inset-0 z-[1000] hidden flex items-center justify-center">
    <div onclick="toggleModal('wb-delete-confirm-modal', false)" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-sm sm:w-full modal-animate-in mx-4">
        <div class="p-6 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-50 mb-4">
                <i data-lucide="alert-triangle" class="h-6 w-6 text-red-600"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-800 mb-2 tracking-tight uppercase">Delete Waybill</h3>
            <div id="wb-delete-confirm-body" class="text-xs text-slate-500 mb-4 leading-relaxed space-y-1">
                <p>Are you sure you want to delete this waybill?</p>
                <p class="font-mono font-bold text-red-600">Waybill Sequence: <span id="wb-delete-wb-sequence"></span></p>
                <p class="font-mono font-bold text-red-600">Waybill No.: <span id="wb-delete-wb-number"></span></p>
                <p><span class="font-semibold">Invoice:</span> <span id="wb-delete-invoice"></span></p>
                <p><span class="font-semibold">Customer:</span> <span id="wb-delete-customer"></span></p>
                <p class="text-[10px] text-slate-400 mt-2">This action cannot be undone.</p>
            </div>
        </div>
        <div class="bg-slate-50 px-6 py-4 flex flex-col sm:flex-row-reverse gap-2">
            <button id="wb-confirm-delete-btn" onclick="confirmDeleteWaybill()" class="w-full px-4 py-2 bg-red-600 hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg">
                <span id="wb-delete-btn-text">Delete Waybill</span>
                <svg id="wb-delete-spinner" class="hidden animate-spin h-4 w-4 mx-auto text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            </button>
            <button onclick="toggleModal('wb-delete-confirm-modal', false)" class="w-full px-4 py-2 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest">Cancel</button>
        </div>
    </div>
</div>

<!-- ============== DELETE SUCCESS MODAL ============== -->
<div id="wb-delete-success-modal" class="fixed inset-0 z-[1000] hidden flex items-center justify-center">
    <div onclick="toggleModal('wb-delete-success-modal', false); location.reload()" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-sm sm:w-full modal-animate-in mx-4">
        <div class="p-8 text-center">
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-emerald-50 mb-6">
                <i data-lucide="check-circle" class="h-10 w-10 text-emerald-500"></i>
            </div>
            <h3 class="text-xl font-bold text-slate-800 mb-2">Deleted!</h3>
            <p class="text-xs text-slate-500 leading-relaxed">The waybill has been deleted successfully.</p>
        </div>
        <div class="bg-slate-50 px-6 py-4 flex justify-center">
            <button onclick="toggleModal('wb-delete-success-modal', false); location.reload()" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg">Continue</button>
        </div>
    </div>
</div>

<!-- ============== VIEW ITEMS MODAL ============== -->
<div id="wb-view-items-modal" class="fixed inset-0 z-[1100] hidden flex items-center justify-center">
    <div onclick="toggleModal('wb-view-items-modal', false)" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-6xl w-full modal-animate-in mx-4 flex flex-col max-h-[90vh]">
        <div class="bg-maroon p-5 flex justify-between items-center text-white border-b-4 border-gold">
            <h3 class="text-sm font-bold uppercase tracking-widest flex items-center space-x-2">
                <i data-lucide="eye" class="w-5 h-5 text-gold"></i>
                <span id="wb-view-items-title">View Items - SO-0000001</span>
            </h3>
            <button onclick="toggleModal('wb-view-items-modal', false)" class="text-white/70 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <div class="p-6 overflow-y-auto flex-1">
            <table class="w-full text-left border-collapse" id="wb-view-items-table">
                <thead class="bg-slate-50/80 border-b border-slate-100 sticky top-0">
                    <tr class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">
                        <th class="p-3">Product Code</th>
                        <th class="p-3">Part Number</th>
                        <th class="p-3">Description</th>
                        <th class="p-3">Application</th>
                        <th class="p-3">Unit</th>
                        <th class="p-3 text-right">Unit Price</th>
                        <th class="p-3 text-right">Qty</th>
                        <th class="p-3 text-right">Sub Amount</th>
                    </tr>
                    <tr class="bg-slate-50/30 border-b border-slate-100">
                        <th class="p-1 px-3"><input type="text" onkeyup="filterTable('wb-view-items-tbody', 0, this.value)" placeholder="Search..." class="column-search-input text-[10px]"></th>
                        <th class="p-1 px-3"><input type="text" onkeyup="filterTable('wb-view-items-tbody', 1, this.value)" placeholder="Search..." class="column-search-input text-[10px]"></th>
                        <th class="p-1 px-3"><input type="text" onkeyup="filterTable('wb-view-items-tbody', 2, this.value)" placeholder="Search..." class="column-search-input text-[10px]"></th>
                        <th class="p-1 px-3"><input type="text" onkeyup="filterTable('wb-view-items-tbody', 3, this.value)" placeholder="Search..." class="column-search-input text-[10px]"></th>
                        <th class="p-1 px-3"><input type="text" onkeyup="filterTable('wb-view-items-tbody', 4, this.value)" placeholder="Search..." class="column-search-input text-[10px]"></th>
                        <th class="p-1 px-3"><input type="text" onkeyup="filterTable('wb-view-items-tbody', 5, this.value)" placeholder="Search..." class="column-search-input text-[10px]"></th>
                        <th class="p-1 px-3"><input type="text" onkeyup="filterTable('wb-view-items-tbody', 6, this.value)" placeholder="Search..." class="column-search-input text-[10px]"></th>
                        <th class="p-1 px-3"><input type="text" onkeyup="filterTable('wb-view-items-tbody', 7, this.value)" placeholder="Search..." class="column-search-input text-[10px]"></th>
                    </tr>
                </thead>
                <tbody id="wb-view-items-tbody" class="text-xs divide-y divide-slate-50">
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100 flex justify-end">
            <button onclick="toggleModal('wb-view-items-modal', false)" class="px-6 py-2.5 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest">Close</button>
        </div>
    </div>
</div>

<!-- ============== VIEW WAYBILL HISTORY MODAL ============== -->
<div id="wb-history-view-modal" class="fixed inset-0 z-[150] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('wb-history-view-modal', false)" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-6xl w-full modal-animate-in mx-4 flex flex-col max-h-[90vh]">
        <!-- Header -->
        <div class="bg-maroon p-5 flex justify-between items-center text-white border-b-4 border-gold">
            <div class="flex items-center space-x-3">
                <i data-lucide="file-text" class="w-5 h-5 text-gold"></i>
                <h3 class="text-sm font-bold uppercase tracking-widest">Waybill Details</h3>
            </div>
            <button onclick="toggleModal('wb-history-view-modal', false)" class="text-white/70 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>

        <div class="flex flex-1 overflow-hidden">
            <!-- LEFT SIDE (30%) -->
            <div class="w-[30%] bg-slate-50 border-r border-slate-100 flex flex-col">
                <div class="flex border-b border-slate-200">
                    <button onclick="switchViewModalTab('customer')" id="view-tab-btn-customer" class="flex-1 py-3 text-[10px] font-bold uppercase tracking-widest text-maroon border-b-2 border-maroon bg-white">Customer Info</button>
                    <button onclick="switchViewModalTab('waybill')" id="view-tab-btn-waybill" class="flex-1 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400 border-b-2 border-transparent hover:text-maroon">Waybill Info</button>
                </div>
                
                <div class="p-6 flex-1 overflow-y-auto">
                    <!-- Tab 1: Customer Info -->
                    <div id="view-modal-tab-customer" class="space-y-6">
                        <div>
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Customer Name</p>
                            <p id="view-wb-customer-name" class="text-sm font-bold text-slate-700">---</p>
                        </div>
                        <div>
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Address</p>
                            <p id="view-wb-customer-address" class="text-xs text-slate-600 leading-relaxed">---</p>
                        </div>
                        <div>
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">TIN</p>
                            <p id="view-wb-customer-tin" class="text-xs text-slate-600">---</p>
                        </div>
                        <div>
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Tell No.</p>
                            <p id="view-wb-customer-phone" class="text-xs text-slate-600">---</p>
                        </div>
                    </div>

                    <!-- Tab 2: Waybill Info -->
                    <div id="view-modal-tab-waybill" class="hidden space-y-6">
                        <div>
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Waybill Sequence</p>
                            <p id="view-wb-sequence" class="text-sm font-mono font-bold text-maroon">---</p>
                        </div>
                        <div>
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Waybill No.</p>
                            <p id="view-wb-no" class="text-sm font-mono font-bold text-maroon">---</p>
                        </div>
                        <div>
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Forwarder</p>
                            <p id="view-wb-forwarder" class="text-xs text-slate-600">---</p>
                        </div>
                        <div>
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Shipper</p>
                            <p id="view-wb-shipper" class="text-xs text-slate-600">W68 AUTOPARTS & SERVICE CENTER</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4 pt-4 border-t border-slate-200">
                            <div>
                                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Total Invoices</p>
                                <p id="view-wb-total-invoices" class="text-sm font-bold text-slate-700">0</p>
                            </div>
                            <div>
                                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Total Items</p>
                                <p id="view-wb-total-items" class="text-sm font-bold text-slate-700">0</p>
                            </div>
                        </div>
                        <div>
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Total Cargo</p>
                            <p id="view-wb-total-cargo" class="text-sm font-bold text-slate-700">0</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT SIDE (70%) -->
            <div class="w-[70%] flex flex-col">
                <div class="flex border-b border-slate-100 px-6">
                    <button onclick="switchViewModalRightTab('invoices')" id="view-right-tab-btn-invoices" class="py-4 px-6 text-[10px] font-bold uppercase tracking-widest text-maroon border-b-2 border-maroon">Invoices</button>
                    <button onclick="switchViewModalRightTab('cargo')" id="view-right-tab-btn-cargo" class="py-4 px-6 text-[10px] font-bold uppercase tracking-widest text-slate-400 border-b-2 border-transparent hover:text-maroon">Cargo Information</button>
                </div>

                <div class="p-6 flex-1 overflow-hidden flex flex-col">
                    <!-- Right Tab 1: Invoices -->
                    <div id="view-modal-right-tab-invoices" class="flex-1 flex flex-col">
                        <div class="overflow-x-auto custom-scrollbar flex-1 border border-slate-100 rounded-xl">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-slate-50 sticky top-0">
                                    <tr class="text-[9px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100">
                                        <th class="p-3">Invoice No</th>
                                        <th class="p-3">Items (Total Products)</th>
                                        <th class="p-3 text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="view-wb-invoices-tbody" class="text-xs divide-y divide-slate-50">
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Right Tab 2: Cargo -->
                    <div id="view-modal-right-tab-cargo" class="hidden flex-1 flex flex-col">
                        <div class="overflow-x-auto custom-scrollbar flex-1 border border-slate-100 rounded-xl">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-slate-50 sticky top-0">
                                    <tr class="text-[9px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100">
                                        <th class="p-3 w-16 text-center">QTY</th>
                                        <th class="p-3 w-24 text-center">UNIT</th>
                                        <th class="p-3">CARGO DESCRIPTION</th>
                                    </tr>
                                </thead>
                                <tbody id="view-wb-cargo-tbody" class="text-xs divide-y divide-slate-50">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="p-4 bg-slate-50 border-t border-slate-100 flex justify-end">
            <button onclick="toggleModal('wb-history-view-modal', false)" class="px-6 py-2 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-100 transition-all uppercase tracking-widest">Close</button>
        </div>
    </div>
</div>

<!-- ============== INVOICE PRODUCT LIST MODAL (NESTED) ============== -->
<div id="wb-invoice-products-modal" class="fixed inset-0 z-[200] hidden flex items-center justify-center">
    <div onclick="toggleModal('wb-invoice-products-modal', false)" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-4xl w-full modal-animate-in mx-4 flex flex-col max-h-[80vh]">
        <div class="bg-maroon p-4 flex justify-between items-center text-white border-b-2 border-gold">
            <h4 class="text-xs font-bold uppercase tracking-widest flex items-center space-x-2">
                <i data-lucide="package" class="w-4 h-4 text-gold"></i>
                <span id="invoice-products-title">Invoice Products</span>
            </h4>
            <button onclick="toggleModal('wb-invoice-products-modal', false)" class="text-white/70 hover:text-white"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
        <div class="p-6 overflow-y-auto flex-1">
            <table class="w-full text-left border-collapse border border-slate-100 rounded-lg">
                <thead class="bg-slate-50 sticky top-0">
                    <tr class="text-[9px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100">
                        <th class="p-3">Product Code</th>
                        <th class="p-3">Description</th>
                        <th class="p-3 text-right">Qty</th>
                        <th class="p-3">Unit</th>
                    </tr>
                    <tr class="bg-slate-50/50 border-b border-slate-100">
                        <th class="p-2"><input type="text" onkeyup="filterTable('invoice-products-tbody', 0, this.value)" placeholder="Search Code..." class="column-search-input text-[9px]"></th>
                        <th class="p-2"><input type="text" onkeyup="filterTable('invoice-products-tbody', 1, this.value)" placeholder="Search Desc..." class="column-search-input text-[9px]"></th>
                        <th class="p-2"></th>
                        <th class="p-2"></th>
                    </tr>
                </thead>
                <tbody id="invoice-products-tbody" class="text-xs divide-y divide-slate-50">
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection

@section('extra_content')
<!-- PRINT RECEIPT TEMPLATE -->
<div id="waybill-print-template" class="hidden print:block bg-white p-8 text-slate-900 font-serif text-[13px]" style="width: 210mm; min-height: 297mm; margin: 0 auto;">
    <div class="flex justify-between items-start mb-8">
        <div class="space-y-1 w-1/2">
            <div class="flex">
                <span class="w-20 font-bold">to:</span>
                <span id="print-customer-name" contenteditable="true" spellcheck="false" class="border-b border-slate-300 flex-1 min-w-0 min-h-5 h-auto whitespace-normal break-words leading-5"></span>
            </div>
            <div class="flex items-start">
                <span class="w-20 font-bold shrink-0">address:</span>
                <div class="flex-1 border-b border-slate-300 min-h-[1.25rem]">
                    <span id="print-customer-address" contenteditable="true" spellcheck="false" class="block whitespace-normal break-words leading-5 min-w-0 min-h-5 h-auto"></span>
                </div>
            </div>
            <div class="flex">
                <span class="w-20 font-bold">Tell No:</span>
                <span id="print-customer-tell" contenteditable="true" spellcheck="false" class="border-b border-slate-300 flex-1 min-w-0 min-h-5 h-auto whitespace-normal break-words leading-5"></span>
            </div>
            <div class="flex">
                <span class="w-20 font-bold">Date::</span>
                <span id="print-date" contenteditable="true" spellcheck="false" class="border-b border-slate-300 flex-1 min-w-0 min-h-5 h-auto whitespace-normal break-words leading-5"></span>
            </div>
        </div>
        <div class="space-y-1 w-1/2 ml-8">
            <div class="flex">
                <span class="w-24 font-bold">Shipper:</span>
                <span id="print-shipper" contenteditable="true" spellcheck="false" class="border-b border-slate-300 flex-1 min-w-0 min-h-5 h-auto whitespace-normal break-words leading-5"></span>
            </div>
            <div class="flex">
                <span class="w-24 font-bold">Forwarder:</span>
                <span id="print-forwarder" contenteditable="true" spellcheck="false" class="border-b border-slate-300 flex-1 min-w-0 min-h-5 h-auto whitespace-normal break-words leading-5"></span>
            </div>
            <div class="flex">
                <span class="w-24 font-bold">WAYBILL NO.</span>
                <span id="print-waybill-no" contenteditable="true" spellcheck="false" class="border-b border-slate-300 flex-1 font-bold min-w-0 min-h-5 h-auto whitespace-normal break-words leading-5"></span>
            </div>
            <div class="flex">
                <span class="w-24 font-bold">Total:</span>
                <span id="print-total" contenteditable="true" spellcheck="false" class="border-b border-slate-300 flex-1 min-w-0 min-h-5 h-auto whitespace-normal break-words leading-5"></span>
            </div>
        </div>
    </div>

    <table class="w-full border-collapse mb-8 text-[13px] border border-slate-800">
        <thead>
            <tr class="bg-slate-100">
                <th class="py-2 px-3 text-left w-20 border border-slate-800">QTY</th>
                <th class="py-2 px-3 text-left w-24 border border-slate-800">UNIT</th>
                <th class="py-2 px-3 text-left border border-slate-800">CARGO DESCRIPTION</th>
            </tr>
        </thead>
        <tbody id="print-cargo-items">
            <!-- Items will be injected here -->
        </tbody>
    </table>

    <div class="mt-auto pt-12">
        <div id="print-invoice-cells" class="flex flex-wrap items-center">
            <!-- Invoice cells will be injected here -->
        </div>
        <div class="flex mt-10 ml-[65%] items-center">
            <span class="font-bold mr-2 whitespace-nowrap">Received By:</span>
            <div class="border-b border-slate-800 flex-1 h-8"></div>
        </div>
    </div>
</div>

<style>
    @media print {
        body * { visibility: hidden; }
        #waybill-print-template, #waybill-print-template * { visibility: visible; }
        #waybill-print-template {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            display: block !important;
        }
        @page { size: auto; margin: 0; }
    }
</style>
@endsection

@push('scripts')
    <script>
        window.waybillRoutes = {
            stats: "{{ route('regular.waybill.stats') }}",
            pending: "{{ route('regular.waybill.pending') }}",
            soItems: "{{ route('regular.waybill.so-items', ':id') }}",
            finalize: "{{ route('regular.waybill.finalize') }}",
            forwarderSuggestions: "{{ route('regular.waybill.forwarder-suggestions') }}",
            history: "{{ route('regular.waybill.history') }}",
            historyDetails: "{{ route('regular.waybill.history-details', ':id') }}",
            batchPrint: "{{ route('regular.waybill.batch-print') }}",
            update: "{{ route('regular.waybill.update', ':id') }}",
            editData: "{{ route('regular.waybill.edit-data', ':id') }}"
        };
        window.csrfToken = "{{ csrf_token() }}";

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof loadWaybillDashboard === 'function') loadWaybillDashboard();
            if (typeof loadPendingWaybills === 'function') loadPendingWaybills();
        });
    </script>
    <script src="{{ asset('js/Waybill.js') }}?v={{ time() }}"></script>
@endpush

