@extends('partials.user_account.user_sidebar_navbar')

@push('styles')
    @vite(['resources/css/Sales-Return.css'])
    <style>
        @keyframes blink-red {
            0%, 100% { background-color: rgba(239, 68, 68, 0.05); border-color: rgb(239, 68, 68); }
            50% { background-color: rgba(239, 68, 68, 0.2); border-color: rgb(220, 38, 38); }
        }
        .blink-red {
            animation: blink-red 1.5s ease-in-out infinite;
        }
    </style>
@endpush

@section('sales_return_content')
<div class="flex-1 flex flex-col min-h-screen bg-slate-50/50">
    <!-- Header Section -->
    <div class="p-8 bg-white border-b border-slate-100 shadow-sm relative overflow-hidden">
        <div class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-maroon/5 rounded-full blur-3xl"></div>
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 relative z-10">
            <div>
                <nav class="flex items-center space-x-2 text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">
                    <a href="#" class="hover:text-maroon transition-colors">Portal</a>
                    <i data-lucide="chevron-right" class="w-3 h-3"></i>
                    <span class="text-maroon">Sales Return</span>
                </nav>
                <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Sales Return Overview</h1>
                <p class="text-slate-500 text-xs font-medium mt-1">Manage and track product returns from customers.</p>
            </div>
            
            <button onclick="toggleModal('add-return-modal', true)" class="px-6 py-3 maroon-gradient text-white rounded-2xl text-xs font-bold hover:shadow-2xl hover:shadow-maroon/30 transition-all flex items-center space-x-2 group active:scale-95">
                <div class="p-1.5 bg-white/20 rounded-lg group-hover:rotate-90 transition-transform duration-500">
                    <i data-lucide="plus" class="w-4 h-4 text-gold"></i>
                </div>
                <span class="uppercase tracking-widest">Add Return</span>
            </button>
        </div>
    </div>

    <!-- Stats Section -->
    <div class="p-8 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm card-hover">
            <div class="flex items-center space-x-5">
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-maroon shadow-inner shrink-0">
                    <i data-lucide="refresh-ccw" class="w-7 h-7 text-gold"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Returns</p>
                    <h3 id="dash-total-returns" class="text-2xl font-black text-slate-800 mt-0.5">0</h3>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t border-slate-100 grid grid-cols-2 gap-3 text-[9px] uppercase tracking-wider">
                <div><p class="text-slate-400 font-bold">Current Year</p><p id="dash-total-year" class="mt-1 text-sm font-black text-slate-700">0</p></div>
                <div><p class="text-slate-400 font-bold">Current Month</p><p id="dash-total-month" class="mt-1 text-sm font-black text-slate-700">0</p></div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm card-hover">
            <div class="flex items-center space-x-5">
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-maroon shadow-inner shrink-0">
                    <i data-lucide="shopping-bag" class="w-7 h-7 text-gold"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Online Return</p>
                    <h3 id="dash-online-returns" class="text-2xl font-black text-slate-800 mt-0.5">0</h3>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t border-slate-100 grid grid-cols-2 gap-3 text-[9px] uppercase tracking-wider">
                <div><p class="text-slate-400 font-bold">Current Year</p><p id="dash-online-year" class="mt-1 text-sm font-black text-maroon">0</p></div>
                <div><p class="text-slate-400 font-bold">Current Month</p><p id="dash-online-month" class="mt-1 text-sm font-black text-maroon">0</p></div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm card-hover">
            <div class="flex items-center space-x-5">
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-maroon shadow-inner shrink-0">
                    <i data-lucide="store" class="w-7 h-7 text-gold"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Local Return</p>
                    <h3 id="dash-local-returns" class="text-2xl font-black text-slate-800 mt-0.5">0</h3>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t border-slate-100 grid grid-cols-2 gap-3 text-[9px] uppercase tracking-wider">
                <div><p class="text-slate-400 font-bold">Current Year</p><p id="dash-local-year" class="mt-1 text-sm font-black text-maroon">0</p></div>
                <div><p class="text-slate-400 font-bold">Current Month</p><p id="dash-local-month" class="mt-1 text-sm font-black text-maroon">0</p></div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm card-hover">
            <div class="flex items-center space-x-5">
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-maroon shadow-inner shrink-0">
                    <i data-lucide="trending-up" class="w-7 h-7 text-gold"></i>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Return Average</p>
                    <h3 id="dash-return-average" class="text-2xl font-black text-slate-800 mt-0.5">0%</h3>
                    <p class="text-[9px] text-slate-400 font-medium mt-2">Returns vs. all sales orders</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Table Section -->
    <div class="px-8 pb-8">
        <div class="bg-white rounded-[2rem] border border-slate-100 shadow-sm overflow-hidden flex flex-col min-h-[500px]">
            <div class="flex items-center justify-between px-8 py-6 border-b border-slate-100 bg-slate-50/50">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-maroon/10 rounded-xl flex items-center justify-center">
                        <i data-lucide="list" class="w-5 h-5 text-maroon"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Return Items List</h3>
                        <p class="text-[10px] text-slate-400 font-medium">Manage and track returned products</p>
                    </div>
                </div>
                <button onclick="printSelectedReturns()" class="px-4 py-2 bg-amber-500 text-white rounded-xl font-bold text-[10px] hover:bg-amber-600 transition-all flex items-center space-x-2 shadow-sm shadow-amber-200">
                    <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                    <span>PRINT SELECTED</span>
                </button>
            </div>
            <div class="overflow-x-auto custom-scrollbar flex-1">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50/80 border-b border-slate-100">
                        <tr class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">
                            <th class="p-5 text-center w-12"><input type="checkbox" id="select-all-returns" class="accent-maroon cursor-pointer"></th>
                            <th class="p-5">Invoice No.</th>
                            <th class="p-5">Customer</th>
                            <th class="p-5">Return Date</th>
                            <th class="p-5 text-center">Items</th>
                            <th class="p-5 text-center">Action</th>
                        </tr>
                        <tr class="bg-slate-50/30 border-b border-slate-100">
                            <th class="p-2 px-5"></th>
                            <th class="p-2 px-5"><input type="text" onkeyup="searchSalesReturn(0, this.value)" placeholder="Search Invoice..." class="column-search-input text-[9px] w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-maroon/10"></th>
                            <th class="p-2 px-5"><input type="text" onkeyup="searchSalesReturn(1, this.value)" placeholder="Search Customer..." class="column-search-input text-[9px] w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-maroon/10"></th>
                            <th class="p-2 px-5"></th>
                            <th class="p-2 px-5"></th>
                            <th class="p-2 px-5"></th>
                        </tr>
                    </thead>
                    <tbody id="sales-return-tbody" class="text-xs divide-y divide-slate-50">
                        <tr><td colspan="6" class="p-12 text-center text-slate-300 italic">No sales returns found.</td></tr>
                    </tbody>
                </table>
            </div>
            <!-- Pagination -->
            <div class="p-4 border-t border-slate-100 bg-slate-50/30 flex justify-between items-center text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                <p id="return-page-info">Showing Page 0 of 0 (0 Total Returns)</p>
                <div class="flex space-x-2">
                    <button onclick="changeReturnPage(-1)" id="return-prev-btn" class="px-3 py-1 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-50 transition-all">Prev</button>
                    <button onclick="changeReturnPage(1)" id="return-next-btn" class="px-3 py-1 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-50 transition-all">Next</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============== ADD RETURN MODAL ============== -->
<div id="add-return-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <div onclick="toggleModal('add-return-modal', false)" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-[2.5rem] text-left overflow-hidden shadow-2xl transform transition-all max-w-5xl w-full modal-animate-in flex flex-col max-h-[90vh]">
        <!-- Modal Header -->
        <div class="bg-maroon p-6 flex justify-between items-center text-white border-b-4 border-gold relative overflow-hidden">
            <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
            <div class="flex items-center space-x-3 relative z-10">
                <div class="p-2 bg-white/10 rounded-xl">
                    <i data-lucide="corner-up-left" class="w-5 h-5 text-gold"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold uppercase tracking-widest">Process Sales Return</h3>
                    <p class="text-[9px] text-white/60 font-medium uppercase tracking-wider">New Return Request</p>
                </div>
            </div>
            <button onclick="toggleModal('add-return-modal', false)" class="text-white/70 hover:text-white transition-colors relative z-10">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Step Indicator -->
        <div class="px-8 py-6 bg-slate-50 border-b border-slate-100">
            <div class="flex items-center justify-center space-x-4">
                <div class="flex items-center space-x-2">
                    <div id="rstep-indicator-1" class="step-indicator active">1</div>
                    <span id="rstep-label-1" class="text-[10px] font-bold text-maroon uppercase tracking-widest">Select Invoices</span>
                </div>
                <div class="w-20 h-0.5 bg-slate-200 relative">
                    <div id="rstep-progress-1" class="absolute top-0 left-0 h-full bg-maroon transition-all" style="width:0%"></div>
                </div>
                <div class="flex items-center space-x-2">
                    <div id="rstep-indicator-2" class="step-indicator pending">2</div>
                    <span id="rstep-label-2" class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Edit Items</span>
                </div>
                <div class="w-20 h-0.5 bg-slate-200 relative">
                    <div id="rstep-progress-2" class="absolute top-0 left-0 h-full bg-maroon transition-all" style="width:0%"></div>
                </div>
                <div class="flex items-center space-x-2">
                    <div id="rstep-indicator-3" class="step-indicator pending">3</div>
                    <span id="rstep-label-3" class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Review</span>
                </div>
            </div>
        </div>

        <!-- STEP 1: Select Invoices -->
        <div id="return-step-1" class="return-step-content p-8 space-y-6 overflow-y-auto flex-1">
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-6 space-y-4">
                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest flex items-center space-x-2">
                    <i data-lucide="file-text" class="w-3.5 h-3.5 text-maroon"></i>
                    <span>Select Invoice</span>
                </label>
                <div class="relative">
                    <input type="text" id="invoice-search-input" readonly onclick="openInvoiceSearchModal()" placeholder="Click to browse and select invoices..." class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-bold text-slate-700 cursor-pointer hover:bg-slate-100 transition-all outline-none">
                    <div class="absolute right-4 top-1/2 -translate-y-1/2 p-2 bg-maroon text-white rounded-xl">
                        <i data-lucide="search" class="w-4 h-4 text-gold"></i>
                    </div>
                </div>
                <!-- selected invoice tags -->
                <div id="selected-invoice-tags" class="flex flex-wrap gap-2 min-h-[32px]">
                </div>
            </div>
            <div id="step-1-empty" class="p-12 text-center space-y-4">
                <div class="mx-auto w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center">
                    <i data-lucide="mouse-pointer-2" class="w-8 h-8 text-slate-300"></i>
                </div>
                <p class="text-slate-400 text-xs font-medium italic">Please select invoices to proceed with the return process.</p>
            </div>
        </div>

        <!-- STEP 2: Selected Invoice items Table -->
        <div id="return-step-2" class="return-step-content hidden p-8 space-y-6 overflow-y-auto flex-1">
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden flex flex-col min-h-[400px]">
                <!-- Dynamic Tabs Container -->
                <div id="invoice-tabs-container" class="flex border-b border-slate-100 bg-slate-50/50">
                    <!-- Tabs will be injected here -->
                </div>
                
                <div class="flex-1 overflow-x-auto custom-scrollbar" id="invoice-items-content">
                    <!-- Table content will be injected here -->
                </div>
            </div>
        </div>

        <!-- STEP 3: Review -->
        <div id="return-step-3" class="return-step-content hidden p-8 space-y-6 overflow-y-auto flex-1">
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden flex flex-col min-h-[300px]">
                <div id="review-tabs-container" class="flex border-b border-slate-100 bg-slate-50/50">
                </div>
                <div class="flex-1 overflow-x-auto custom-scrollbar" id="review-items-content">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                    <p class="text-[9px] font-bold text-slate-400 uppercase mb-2">Total Items to Return</p>
                    <p class="text-lg font-black text-slate-800" id="review-total-items">0 Items</p>
                </div>
                <div class="p-4 bg-maroon/5 rounded-2xl border border-maroon/10">
                    <p class="text-[9px] font-bold text-maroon uppercase mb-2">Total Return Amount</p>
                    <p class="text-lg font-black text-maroon" id="review-total-amount">₱ 0.00</p>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="p-8 bg-slate-50 border-t border-slate-100 flex justify-between items-center">
            <button id="r-btn-back" onclick="goToReturnStep(currentReturnStep - 1)" class="hidden px-8 py-3 text-[10px] font-bold text-slate-400 hover:text-maroon transition-all uppercase tracking-widest flex items-center space-x-2">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                <span>Back</span>
            </button>
            <div class="flex space-x-3 ml-auto">
                <button onclick="toggleModal('add-return-modal', false)" class="px-8 py-3 text-[10px] font-bold text-slate-400 hover:text-slate-600 transition-all uppercase tracking-widest">Cancel</button>
                <button id="r-btn-next" onclick="goToReturnStep(currentReturnStep + 1)" class="px-10 py-3 bg-maroon text-white rounded-2xl text-[10px] font-bold hover:shadow-xl hover:shadow-maroon/20 transition-all flex items-center space-x-2 uppercase tracking-widest group">
                    <span id="r-next-text">Next Step</span>
                    <i data-lucide="chevron-right" class="w-4 h-4 text-gold group-hover:translate-x-1 transition-transform"></i>
                </button>
                <button id="r-btn-finalize" onclick="finalizeReturn()" class="hidden px-10 py-3 bg-green-600 text-white rounded-2xl text-[10px] font-bold hover:shadow-xl hover:shadow-green-600/20 transition-all flex items-center space-x-2 uppercase tracking-widest group">
                    <span>Finalize Return</span>
                    <i data-lucide="check" class="w-4 h-4 text-white group-hover:scale-110 transition-transform"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============== INVOICE SEARCH MODAL ============== -->
<div id="invoice-search-modal" class="fixed inset-0 z-[60] hidden flex items-center justify-center p-4">
    <div onclick="toggleModal('invoice-search-modal', false)" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-[2rem] text-left overflow-hidden shadow-2xl transform transition-all max-w-4xl w-full modal-animate-in flex flex-col max-h-[80vh]">
        <div class="bg-maroon p-5 flex justify-between items-center text-white border-b-4 border-gold">
            <h3 class="text-xs font-bold uppercase tracking-widest flex items-center space-x-2">
                <i data-lucide="search" class="w-4 h-4 text-gold"></i>
                <span>Invoices</span>
            </h3>
            <button onclick="toggleModal('invoice-search-modal', false)" class="text-white/70 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <div class="p-6 bg-slate-50 border-b border-slate-100">
            <div class="relative">
                <i data-lucide="search" class="w-4 h-4 absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" id="invoice-search-query-input" placeholder="Search by Invoice No, Customer, or Date..." oninput="handleInvoiceSearchInput()" autocomplete="off" class="w-full pl-11 pr-4 py-3 bg-white border border-slate-200 rounded-xl text-xs outline-none focus:ring-2 focus:ring-maroon/10 transition-all">
            </div>
        </div>
        <div class="p-6 overflow-y-auto flex-1 custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50 border-b border-slate-100 sticky top-0 z-10">
                    <tr class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">
                        <th class="p-4 text-center w-12"><input type="checkbox" class="accent-maroon cursor-pointer"></th>
                        <th class="p-4">Invoice No.</th>
                        <th class="p-4">Customer</th>
                        <th class="p-4 text-right">Total Amount</th>
                        <th class="p-4">Date</th>
                    </tr>
                </thead>
                <tbody id="invoice-search-tbody" class="text-xs divide-y divide-slate-50">
                    <!-- Dynamic Rows -->
                </tbody>
            </table>
            <div id="invoice-pagination" class="flex items-center justify-between pt-4 pb-1 flex-wrap gap-2">
                <p id="invoice-pagination-info" class="text-[10px] font-bold text-slate-400 uppercase tracking-wider"></p>
                <div class="flex items-center space-x-2">
                    <button id="invoice-prev-page" onclick="invoicePrevPage()" class="px-4 py-2 bg-white border border-slate-200 text-slate-600 text-[10px] font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest disabled:opacity-40 disabled:cursor-not-allowed">Previous</button>
                    <span id="invoice-page-indicator" class="text-[10px] font-bold text-slate-600 px-2"></span>
                    <button id="invoice-next-page" onclick="invoiceNextPage()" class="px-4 py-2 bg-white border border-slate-200 text-slate-600 text-[10px] font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
                </div>
            </div>
        </div>
        <div class="p-4 border-t border-slate-100 flex justify-end space-x-3">
            <button onclick="toggleModal('invoice-search-modal', false)" class="px-6 py-2.5 bg-white border border-slate-200 text-slate-600 text-[10px] font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest">Cancel</button>
            <button onclick="confirmInvoiceSelection()" class="px-8 py-2.5 bg-maroon text-white text-[10px] font-bold rounded-xl hover:bg-maroon-800 transition-all uppercase tracking-widest shadow-lg flex items-center space-x-2">
                <i data-lucide="check" class="w-4 h-4 text-gold"></i>
                <span>Confirm Selection</span>
            </button>
        </div>
    </div>
</div>

<!-- ============== SUCCESS MODAL ============== -->
<div id="return-success-modal" class="fixed inset-0 z-[70] hidden flex items-center justify-center p-4">
    <div onclick="toggleModal('return-success-modal', false)" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl p-10 text-center shadow-2xl transform transition-all max-w-sm w-full modal-animate-in">
        <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-green-50 mb-6">
            <i data-lucide="check-circle" class="w-10 h-10 text-green-500"></i>
        </div>
        <h3 class="text-xl font-black text-slate-800 tracking-tight mb-2 uppercase">Return Success</h3>
        <p class="text-slate-500 text-xs font-medium mb-8">The sales return has been processed and recorded successfully.</p>
        <button onclick="location.reload()" class="w-full py-4 bg-maroon text-white rounded-2xl text-xs font-bold uppercase tracking-widest hover:bg-maroon-800 transition-all shadow-lg">Back to List</button>
    </div>
</div>

<!-- ============== VIEW SALES RETURN MODAL ============== -->
<div id="view-return-modal" class="fixed inset-0 z-[150] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('view-return-modal', false)" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-5xl w-full modal-animate-in mx-4 flex flex-col max-h-[85vh]">
        <!-- Header -->
        <div class="bg-maroon p-5 flex justify-between items-center text-white border-b-4 border-gold">
            <div class="flex items-center space-x-3">
                <i data-lucide="file-text" class="w-5 h-5 text-gold"></i>
                <h3 class="text-sm font-bold uppercase tracking-widest">Sales Return Details</h3>
            </div>
            <button onclick="toggleModal('view-return-modal', false)" class="text-white/70 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>

        <div class="p-8 overflow-y-auto flex-1 space-y-8">
            <!-- Header Info Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="space-y-4">
                    <div>
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Return Number</p>
                        <p id="v-return-no" class="text-sm font-mono font-bold text-maroon">---</p>
                    </div>
                    <div>
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Invoice Number</p>
                        <p id="v-invoice-no" class="text-sm font-bold text-slate-700">---</p>
                    </div>
                </div>
                <div class="space-y-4">
                    <div>
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Customer Name</p>
                        <p id="v-customer-name" class="text-sm font-bold text-slate-700">---</p>
                    </div>
                    <div>
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Return Date</p>
                        <p id="v-return-date" class="text-xs text-slate-600">---</p>
                    </div>
                </div>
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-2">General Remarks</p>
                    <p id="v-general-remarks" class="text-xs text-slate-600 italic leading-relaxed">---</p>
                </div>
            </div>

            <!-- Items Table -->
            <div class="border border-slate-100 rounded-2xl overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50">
                        <tr class="text-[9px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100">
                            <th class="p-4">Product Code</th>
                            <th class="p-4">Description</th>
                            <th class="p-4 text-center">Return Qty</th>
                            <th class="p-4">Item Remarks</th>
                            <th class="p-4 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="v-return-items-tbody" class="text-xs divide-y divide-slate-50">
                    </tbody>
                </table>
            </div>

            <!-- Totals -->
            <div class="flex justify-end pt-4 border-t border-slate-100">
                <div class="w-64 space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Items</span>
                        <span id="v-total-items" class="text-sm font-bold text-slate-700">0</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Subtotal</span>
                        <span id="v-subtotal" class="text-sm font-bold text-slate-700">₱ 0.00</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Refund</span>
                        <span id="v-total-amount" class="text-lg font-black text-maroon">₱ 0.00</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="p-5 bg-slate-50 border-t border-slate-100 flex justify-end">
            <button onclick="toggleModal('view-return-modal', false)" class="px-8 py-2.5 bg-white border border-slate-200 text-slate-600 text-[10px] font-bold rounded-xl hover:bg-slate-100 transition-all uppercase tracking-widest">Close</button>
        </div>
    </div>
</div>

<!-- ============== EDIT RETURN MODAL ============== -->
<div id="edit-return-modal" class="fixed inset-0 z-[200] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('edit-return-modal', false)" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-6xl w-full modal-animate-in mx-4 flex flex-col max-h-[90vh]">
        <div class="bg-maroon p-5 flex justify-between items-center text-white border-b-4 border-gold">
            <div class="flex items-center space-x-3">
                <i data-lucide="edit" class="w-5 h-5 text-gold"></i>
                <h3 class="text-sm font-bold uppercase tracking-widest">Edit Sales Return</h3>
            </div>
            <button onclick="toggleModal('edit-return-modal', false)" class="text-white/70 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>

        <div class="p-6 overflow-y-auto flex-1 space-y-6">
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Return Number</p>
                    <p id="edit-return-no" class="text-sm font-mono font-bold text-maroon">---</p>
                </div>
                <div>
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Invoice Number</p>
                    <p id="edit-invoice-no" class="text-sm font-bold text-slate-700">---</p>
                </div>
                <div>
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Customer Name</p>
                    <p id="edit-customer-name" class="text-sm font-bold text-slate-700">---</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50 border-b border-slate-100">
                            <tr class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">
                                <th class="p-4">Product Code</th>
                                <th class="p-4">Description</th>
                                <th class="p-4 text-center">Return QTY</th>
                                <th class="p-4 text-center">Good Items</th>
                                <th class="p-4">Remarks</th>
                                <th class="p-4 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody id="edit-return-items-tbody" class="text-xs divide-y divide-slate-50">
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-2">General Remarks</p>
                <textarea id="edit-general-remarks" placeholder="Enter general reason for return..." class="w-full px-4 py-3 text-xs bg-white border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-maroon/10 h-20 resize-none"></textarea>
            </div>
        </div>

        <div class="p-5 bg-slate-50 border-t border-slate-100 flex justify-end space-x-3">
            <button onclick="toggleModal('edit-return-modal', false)" class="px-6 py-2.5 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest">Cancel</button>
            <button onclick="confirmEditReturn()" class="px-8 py-2.5 bg-maroon text-white text-xs font-bold rounded-xl hover:bg-maroon-800 transition-all uppercase tracking-widest shadow-lg flex items-center space-x-2">
                <i data-lucide="save" class="w-4 h-4 text-gold"></i>
                <span>Save Changes</span>
            </button>
        </div>
    </div>
</div>

<!-- ============== EDIT RETURN CONFIRM MODAL ============== -->
<div id="edit-return-confirm-modal" class="fixed inset-0 z-[210] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('edit-return-confirm-modal', false)" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-sm sm:w-full modal-animate-in mx-4">
        <div class="p-6 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-amber-50 mb-4">
                <i data-lucide="alert-triangle" class="h-6 w-6 text-amber-600"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-800 mb-2 tracking-tight uppercase">Confirm Changes</h3>
            <p class="text-xs text-slate-500 mb-6 leading-relaxed">Are you sure you want to update this Sales Return? Changes to return quantities and good items will update stock and ledger records.</p>
            <div class="flex justify-center space-x-3">
                <button onclick="toggleModal('edit-return-confirm-modal', false)" class="px-6 py-2.5 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest">Cancel</button>
                <button onclick="submitEditReturn()" class="px-8 py-2.5 bg-maroon text-white text-xs font-bold rounded-xl hover:bg-maroon-800 transition-all uppercase tracking-widest shadow-lg flex items-center space-x-2">
                    <i data-lucide="check" class="w-4 h-4 text-gold"></i>
                    <span>Confirm Update</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============== EDIT RETURN SUCCESS MODAL ============== -->
<div id="edit-return-success-modal" class="fixed inset-0 z-[220] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('edit-return-success-modal', false)" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl p-10 text-center shadow-2xl transform transition-all max-w-sm w-full modal-animate-in">
        <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-green-50 mb-6">
            <i data-lucide="check-circle" class="w-10 h-10 text-green-500"></i>
        </div>
        <h3 class="text-xl font-black text-slate-800 tracking-tight mb-2 uppercase">Return Updated</h3>
        <p class="text-slate-500 text-xs font-medium mb-8">The sales return has been updated successfully.</p>
        <button onclick="location.reload()" class="w-full py-4 bg-maroon text-white rounded-2xl text-xs font-bold uppercase tracking-widest hover:bg-maroon-800 transition-all shadow-lg">Back to List</button>
    </div>
</div>

<!-- ============== DELETE RETURN CONFIRM MODAL ============== -->
<div id="delete-return-confirm-modal" class="fixed inset-0 z-[1000] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('delete-return-confirm-modal', false)" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-sm sm:w-full modal-animate-in mx-4">
        <div class="p-6 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-50 mb-4">
                <i data-lucide="alert-triangle" class="h-6 w-6 text-red-600"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-800 mb-2 tracking-tight uppercase">Delete Return</h3>
            <p class="text-xs text-slate-500 mb-6 leading-relaxed">Are you sure you want to delete this Sales Return? This will permanently remove the record and reverse the stock adjustment.</p>
            <div class="flex justify-center space-x-3">
                <button onclick="toggleModal('delete-return-confirm-modal', false)" class="px-6 py-2.5 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest">Cancel</button>
                <button id="confirm-delete-return-btn" onclick="submitDeleteReturn()" class="px-8 py-2.5 bg-red-600 text-white text-xs font-bold rounded-xl hover:bg-red-700 transition-all uppercase tracking-widest shadow-lg flex items-center space-x-2">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                    <span>Delete</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============== DELETE RETURN SUCCESS MODAL ============== -->
<div id="delete-return-success-modal" class="fixed inset-0 z-[1000] hidden flex items-center justify-center" role="dialog" aria-modal="true">
    <div onclick="toggleModal('delete-return-success-modal', false)" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl p-10 text-center shadow-2xl transform transition-all max-w-sm w-full modal-animate-in">
        <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-green-50 mb-6">
            <i data-lucide="check-circle" class="w-10 h-10 text-green-500"></i>
        </div>
        <h3 class="text-xl font-black text-slate-800 tracking-tight mb-2 uppercase">Return Deleted</h3>
        <p class="text-slate-500 text-xs font-medium mb-8">The sales return has been deleted successfully.</p>
        <button onclick="location.reload()" class="w-full py-4 bg-maroon text-white rounded-2xl text-xs font-bold uppercase tracking-widest hover:bg-maroon-800 transition-all shadow-lg">Back to List</button>
    </div>
</div>

<!-- ============== PRINT TEMPLATE (HIDDEN) ============== -->
<div id="print-area" class="hidden print:block p-8 text-sm font-sans bg-white">
    <style>
        @media print {
            @page { margin: 0.5in; size: auto; }
            body { margin: 0; padding: 0; background: #fff !important; font-size: 13px !important; }
            body * { visibility: hidden; }
            #print-area, #print-area * { visibility: visible; font-size: 13px !important; }
            #print-area { 
                position: absolute; 
                left: 0; 
                top: 0; 
                width: 100%; 
                display: block !important; 
                background: #fff !important;
            }
            .print-slip { 
                page-break-after: always; 
                page-break-inside: avoid;
                padding: 10px 0;
                display: block !important;
            }
            .print-slip:last-child { 
                page-break-after: avoid !important; 
            }
            .print-table { width: 100%; border-collapse: collapse; margin-top: 20px; border: 1px solid #000; }
            .print-table th, .print-table td { border: 1px solid #000; padding: 8px; text-align: left; font-size: 13px !important; }
            .text-red-600 { color: #dc2626 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .font-bold { font-weight: bold !important; }
            .uppercase { text-transform: uppercase !important; }
        }
    </style>
    <div id="print-slips-container">
        <!-- Multiple slips will be injected here for bulk printing -->
    </div>
</div>

<!-- Template for a single slip (used by JS) -->
<template id="single-slip-template">
    <div class="print-slip">
        <div class="flex justify-between mb-10">
            <div class="space-y-2">
                <div class="flex"><span class="w-32 font-bold uppercase">Customer</span><span class="p-customer">: ---</span></div>
                <div class="flex"><span class="w-32 font-bold uppercase">Address</span><span class="p-address">: ---</span></div>
            </div>
            <div class="p-4 border-2 border-black rounded-lg space-y-2 min-w-[300px]">
                <div class="flex justify-between"><span class="font-bold uppercase">Return No</span><span class="p-return-no font-bold text-red-600 ml-4">: ---</span></div>
                <div class="flex justify-between"><span class="font-bold uppercase">Date</span><span class="p-date ml-4">: ---</span></div>
                <div class="flex justify-between border-t border-black pt-2"><span class="font-bold uppercase">Invoice No.</span><span class="p-invoice-no font-bold text-red-600 ml-4">: ---</span></div>
            </div>
        </div>

        <div class="mb-8">
            <p class="font-bold uppercase mb-2">Reason for Cancellation:</p>
            <p class="p-reason italic p-4 bg-slate-50 border border-slate-100 rounded-lg min-h-[60px]">---</p>
        </div>

        <table class="print-table">
            <thead>
                <tr class="uppercase text-xs bg-slate-100">
                    <th class="w-12">Qty</th>
                    <th class="w-12">Unit</th>
                    <th style="width:auto">Description</th>
                    <th class="w-28 text-right">Unit Price</th>
                    <th class="w-20 text-center">Disc %</th>
                    <th class="w-28 text-right">Sub Total</th>
                </tr>
            </thead>
            <tbody class="p-items-tbody">
                <!-- Items injected here -->
            </tbody>
        </table>

        <div class="mt-10 border-t-2 border-black pt-6">
            <div class="flex flex-col items-end space-y-2">
                <div class="flex w-64 justify-between">
                    <span class="uppercase font-bold">Subtotal</span>
                    <span class="p-subtotal font-bold">₱ 0.00</span>
                </div>
                <div class="flex w-64 justify-between p-addl-disc-row">
                    <span class="uppercase font-bold">Additional Disc (<span class="p-addl-disc-pct">0</span>%)</span>
                    <span class="p-addl-disc-amt font-bold text-red-600">- ₱ 0.00</span>
                </div>
                <div class="flex w-64 justify-between border-t border-black pt-2">
                    <span class="uppercase font-bold">Total Amount</span>
                    <span class="p-total-amount font-bold">₱ 0.00</span>
                </div>
                <div class="flex w-64 justify-between">
                    <span class="uppercase font-bold">Report Total</span>
                    <span class="p-report-total font-black text-lg">₱ 0.00</span>
                </div>
            </div>
        </div>
    </div>
</template>

@endsection

@push('scripts')
    <script>
        window.salesReturnRoutes = {
            invoices: "{{ route('regular.sales-return.invoices') }}",
            items: "{{ route('regular.sales-return.items', ':id') }}",
            history: "{{ route('regular.sales-return.history') }}",
            historyDetail: "{{ route('regular.sales-return.history-detail', ':id') }}",
            finalize: "{{ route('regular.sales-return.finalize') }}",
            dashboard: "{{ route('regular.sales-return.dashboard') }}",
            editData: "{{ route('regular.sales-return.edit-data', ':id') }}",
            update: "{{ route('regular.sales-return.update', ':id') }}",
            delete: "{{ route('regular.sales-return.delete', ':id') }}",
        };
        window.csrfToken = "{{ csrf_token() }}";
    </script>
    <script src="{{ asset('js/Sales-Return.js') }}?v={{ filemtime(public_path('js/Sales-Return.js')) }}"></script>
@endpush


