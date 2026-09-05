<?php $__env->startPush('styles'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/Purchase-Return.css')); ?>?v=<?php echo e(time()); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('purchase_return_content'); ?>
<div id="page-purchase-return-root" class="space-y-8 animate-fade-in text-slate-800">
    
    <!-- HEADER SECTION -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-maroon tracking-tight">Purchase Return Management</h1>
            <p class="text-sm text-slate-500">Track and manage your purchase returns and credit notes.</p>
        </div>
        <div class="flex items-center space-x-3">
            <button onclick="toggleModal('filter-modal', true)" class="px-4 py-2.5 bg-white border border-slate-200 hover:border-maroon-200 hover:bg-slate-50 text-slate-700 hover:text-maroon text-xs font-bold rounded-xl shadow-sm flex items-center space-x-2 transition-all">
                <i data-lucide="sliders-horizontal" class="w-4 h-4 text-slate-500"></i>
                <span>Filter</span>
            </button>
            <button onclick="resetPurchaseReturnForm(); toggleModal('add-return-modal', true); goToStep(1);" class="px-5 py-2.5 bg-maroon text-white hover:bg-maroon-800 text-xs font-bold rounded-xl shadow-md flex items-center space-x-2 transition-all">
                <i data-lucide="plus-circle" class="w-4 h-4 text-gold"></i>
                <span>Add Return</span>
            </button>
        </div>
    </div>

    <!-- DASHBOARD CARDS -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Total Returns -->
        <div id="total-returns-card" class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex items-center space-x-4 hover-card-trigger">
            <div class="p-3 bg-maroon rounded-xl border-2 border-gold shadow-sm">
                <i data-lucide="undo-2" class="w-6 h-6 text-gold"></i>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Returns</p>
                <h3 id="total-returns-count" class="text-2xl font-extrabold text-slate-800">0</h3>
            </div>
        </div>
        <!-- Total Supplier -->
        <div id="total-supplier-card" class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex items-center space-x-4 hover-card-trigger">
            <div class="p-3 bg-maroon rounded-xl border-2 border-gold shadow-sm">
                <i data-lucide="truck" class="w-6 h-6 text-gold"></i>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Supplier</p>
                <h3 id="total-supplier-count" class="text-2xl font-extrabold text-slate-800">0</h3>
            </div>
        </div>
    </div>

    <!-- MAIN TABLE SECTION: Purchase List History -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden text-slate-800">
        <div class="p-5 border-b border-slate-50 flex justify-between items-center">
            <h2 class="font-bold text-slate-800">Purchase Return History</h2>
            <div class="flex items-center space-x-2">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Showing:</span>
                <span class="px-3 py-1 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-lg uppercase">All Records</span>
            </div>
        </div>
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50/80 border-b border-slate-100">
                    <tr>
                        <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Invoice No.</th>
                        <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Supplier Name</th>
                        <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Contact No#</th>
                        <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Contact Person</th>
                        <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Billing Address</th>
                        <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Action</th>
                    </tr>
                    <tr class="bg-slate-50/30 border-b border-slate-100">
                        <th class="p-2 px-5"><input type="text" name="return_number" onkeyup="filterTable('main-return-tbody', 0, this.value)" placeholder="Search Inv..." class="column-search-input"></th>
                        <th class="p-2 px-5"><input type="text" onkeyup="filterTable('main-return-tbody', 1, this.value)" placeholder="Search Name..." class="column-search-input"></th>
                        <th class="p-2 px-5"><input type="text" onkeyup="filterTable('main-return-tbody', 2, this.value)" placeholder="Search Contact..." class="column-search-input"></th>
                        <th class="p-2 px-5"><input type="text" onkeyup="filterTable('main-return-tbody', 3, this.value)" placeholder="Search Person..." class="column-search-input"></th>
                        <th class="p-2 px-5"><input type="text" onkeyup="filterTable('main-return-tbody', 4, this.value)" placeholder="Search Address..." class="column-search-input"></th>
                        <th class="p-2 px-5"></th>
                    </tr>
                </thead>
                <tbody id="main-return-tbody" class="divide-y divide-slate-100 text-sm">
                    <tr class="hover:bg-slate-50 transition-colors group">
                        <td class="p-4 px-6 font-mono text-xs text-maroon font-bold">INV-2024-001</td>
                        <td class="p-4 px-6 text-slate-700 font-bold">ABC Supplier</td>
                        <td class="p-4 px-6 text-slate-600">09123456789</td>
                        <td class="p-4 px-6 text-slate-600">John Smith</td>
                        <td class="p-4 px-6 text-slate-500">123 Street, Manila City</td>
                        <td class="p-4 px-6 text-center">
                            <button onclick="viewReturnDetail({id: 1})" class="p-2 bg-slate-100 text-slate-500 hover:bg-maroon hover:text-white rounded-lg transition-all shadow-sm">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ==================== MODALS ==================== -->

    <!-- 1. ADD RETURN MODAL (MULTI-STEP) -->
    <div id="add-return-modal" class="fixed inset-0 z-[400] hidden flex items-center justify-center" role="dialog" aria-modal="true">
        <div onclick="toggleModal('add-return-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-5xl w-full modal-animate-in mx-4 flex flex-col max-h-[90vh]">
            
            <!-- Modal Header -->
            <div class="bg-maroon p-6 flex justify-between items-center text-white border-b-4 border-gold">
                <div class="flex items-center space-x-4">
                    <div class="p-3 bg-white/10 rounded-2xl border-2 border-gold shadow-sm">
                        <i data-lucide="undo-2" class="w-6 h-6 text-gold"></i>
                    </div>
                    <div>
                        <h3 id="add-return-modal-title" class="text-xl font-bold tracking-tight">Purchase Return Processing</h3>
                        <p id="add-return-modal-subtitle" class="text-[10px] text-white/60 uppercase tracking-widest font-bold">Follow the steps to record return</p>
                    </div>
                </div>

                <!-- Step Indicators -->
                <div class="flex items-center space-x-6 mr-12 relative">
                    <div class="absolute top-4 left-0 w-full h-0.5 bg-white/20 -z-0"></div>
                    <div id="step-progress-line" class="absolute top-4 left-0 h-0.5 bg-gold transition-all duration-500 -z-0" style="width: 0%;"></div>
                    
                    <div class="relative flex justify-between w-64">
                        <div class="flex flex-col items-center space-y-2">
                            <div id="step-indicator-1" class="step-indicator active">1</div>
                            <span class="text-[8px] font-bold uppercase tracking-widest text-white/70">Basic Info</span>
                        </div>
                        <div class="flex flex-col items-center space-y-2">
                            <div id="step-indicator-2" class="step-indicator pending">2</div>
                            <span class="text-[8px] font-bold uppercase tracking-widest text-white/40">Items</span>
                        </div>
                        <div class="flex flex-col items-center space-y-2">
                            <div id="step-indicator-3" class="step-indicator pending">3</div>
                            <span class="text-[8px] font-bold uppercase tracking-widest text-white/40">Review</span>
                        </div>
                    </div>
                </div>

                <button onclick="toggleModal('add-return-modal', false)" class="text-white/70 hover:text-white transition-colors bg-white/10 p-2 rounded-xl">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>

            <!-- Modal Body (Scrollable) -->
            <div class="flex-1 overflow-y-auto custom-scrollbar p-8">
                
                <!-- STEP 1: BASIC INFO -->
                <div id="return-step-1" class="return-step-content space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2 tracking-widest">Return Number</label>
                                <input type="text" id="new-return-no" value="00000001" readonly class="w-full px-4 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-sm font-mono text-slate-500">
                            </div>
                            <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2 tracking-widest">Slip No.</label>
                                <input type="text" id="new-slip-no" maxlength="100" placeholder="e.g. RS-0001" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-mono text-slate-800 focus:ring-2 focus:ring-maroon/20 focus:border-maroon outline-none transition-all">
                            </div>
                            <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2 tracking-widest">Return Date</label>
                                <input type="date" id="new-return-date" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-maroon/20 focus:border-maroon outline-none transition-all">
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2 tracking-widest">Search Invoice</label>
                                <div class="flex space-x-2">
                                    <input type="text" id="new-invoice-no" readonly placeholder="Click search to find invoice..." class="flex-1 px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-mono text-slate-800">
                                    <button onclick="loadPurchaseInvoices()" class="px-4 py-2.5 bg-maroon text-white rounded-xl hover:bg-maroon-800 transition-all shadow-md">
                                        <i data-lucide="search" class="w-4 h-4 text-gold"></i>
                                    </button>
                                </div>
                                <input type="text" id="new-supplier-name" readonly placeholder="Supplier Name" class="w-full mt-3 px-4 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-sm text-slate-500">
                            </div>
                        </div>
                    </div>
                    <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100">
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2 tracking-widest">Remarks</label>
                        <textarea id="new-remarks" rows="3" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-maroon/20 focus:border-maroon outline-none transition-all" placeholder="Enter return reason or notes..."></textarea>
                    </div>
                </div>

                <!-- STEP 2: ITEMS -->
                <div id="return-step-2" class="return-step-content hidden space-y-6">
                    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-slate-50 border-b border-slate-100">
                                    <tr>
                                        <th class="p-4 px-6 w-12"><input type="checkbox" class="rounded border-slate-300 text-maroon focus:ring-maroon"></th>
                                        <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Item Code</th>
                                        <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Description</th>
                                        <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">QTY</th>
                                        <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">UNIT</th>
                                        <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-right">UNIT PRICE</th>
                                        <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">%Disc</th>
                                        <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-right">Sub Total</th>
                                    </tr>
                                </thead>
                                <tbody id="return-items-tbody" class="divide-y divide-slate-100 text-sm">
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="p-4 px-6"><input type="checkbox" checked class="rounded border-slate-300 text-maroon focus:ring-maroon"></td>
                                        <td class="p-4 px-6 font-bold text-maroon">ITM-001</td>
                                        <td class="p-4 px-6 text-slate-700">Sample Item Description</td>
                                        <td class="p-4 px-6 text-center">
                                            <input type="number" value="10" min="1" oninput="updateReturnItemSubtotal(this)" class="item-qty w-20 px-3 py-1.5 border border-slate-200 rounded-lg text-center focus:ring-2 focus:ring-maroon/20 focus:border-maroon outline-none transition-all">
                                        </td>
                                        <td class="p-4 px-6 text-center uppercase text-slate-500">---</td>
                                        <td class="p-4 px-6 text-right item-price">₱ 1,500.00</td>
                                        <td class="p-4 px-6 text-center item-disc">0%</td>
                                        <td class="p-4 px-6 text-right font-bold text-maroon item-subtotal">₱ 15,000.00</td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-slate-50 border-t-2 border-slate-200">
                                    <tr>
                                        <td colspan="7" class="p-3 px-6 text-right text-xs font-bold text-slate-600 uppercase tracking-wider">Total:</td>
                                        <td class="p-3 px-6 text-right font-bold text-slate-700" id="return-total">₱ 0.00</td>
                                    </tr>
                                    <tr>
                                        <td colspan="7" class="p-3 px-6 text-right text-xs font-bold text-slate-600 uppercase tracking-wider">Additional Discount:</td>
                                        <td class="p-3 px-6 text-right font-bold text-orange-600">
                                            <input type="number" id="return-additional-discount-input" value="0" min="0" max="100" step="0.01" oninput="updateReturnTotals()" class="w-24 px-3 py-1.5 border border-orange-200 rounded-lg text-right text-orange-600 font-bold text-xs focus:ring-2 focus:ring-orange/20 focus:border-orange outline-none transition-all">
                                            <span id="return-additional-discount-amount" class="ml-1 text-orange-600">% (₱ 0.00)</span>
                                        </td>
                                    </tr>
                                    <tr class="bg-blue-50">
                                        <td colspan="7" class="p-3 px-6 text-right text-xs font-bold text-slate-700 uppercase tracking-wider">Grand Total:</td>
                                        <td class="p-3 px-6 text-right font-extrabold text-maroon text-sm" id="return-grand-total">₱ 0.00</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- STEP 3: DETAILS REVIEW (30/70 SPLIT) -->
                <div id="return-step-3" class="return-step-content hidden h-full">
                    <div class="flex flex-col md:flex-row h-full gap-8">
                        <!-- Left Side (30% Summary) -->
                        <div class="w-full md:w-[30%] space-y-4">
                            <div class="p-6 bg-maroon rounded-3xl shadow-xl text-white space-y-6 relative overflow-hidden">
                                <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 bg-gold/10 rounded-full blur-2xl"></div>
                                <h4 class="text-sm font-bold uppercase tracking-widest text-gold border-b border-white/10 pb-3">Return Summary</h4>
                                <div class="space-y-4">
                                    <div>
                                        <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Return No.</p>
                                        <p id="rev-return-no" class="text-sm font-mono font-bold tracking-wider">---</p>
                                    </div>
                                    <div>
                                        <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Return Date</p>
                                        <p id="rev-return-date" class="text-sm font-bold">---</p>
                                    </div>
                                    <div>
                                        <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Invoice No.</p>
                                        <p id="rev-invoice-no" class="text-sm font-mono font-bold">---</p>
                                    </div>
                                    <div>
                                        <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Supplier Name</p>
                                        <p id="rev-supplier-name" class="text-sm font-bold">---</p>
                                    </div>
                                    <div>
                                        <p class="text-[9px] uppercase text-white/50 font-bold mb-1">Remarks</p>
                                        <p id="rev-remarks" class="text-xs italic text-white/70 leading-relaxed">---</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Side (70% Items Table) -->
                        <div class="w-full md:w-[70%] space-y-6">
                            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                                <div class="p-4 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
                                    <h4 class="text-xs font-bold text-slate-800 uppercase tracking-widest">Items Table</h4>
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
                                                <th class="p-4 text-right">Unit Price</th>
                                                <th class="p-4 text-center">%Disc</th>
                                                <th class="p-4 text-right">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody id="review-items-tbody" class="text-xs divide-y divide-slate-50">
                                            <!-- Dynamic Rows -->
                                        </tbody>
                                    </table>
                                </div>
                                <div class="p-6 bg-slate-50 border-t border-slate-100 flex justify-between items-center">
                                    <div class="space-y-1">
                                        <div class="flex items-center space-x-6">
                                            <p class="text-[10px] text-slate-400 font-bold uppercase">Subtotal:</p>
                                            <span id="rev-subtotal" class="text-sm font-bold text-slate-700">₱ 0.00</span>
                                        </div>
                                        <div class="flex items-center space-x-6">
                                            <p class="text-[10px] text-orange-500 font-bold uppercase">Additional Discount:</p>
                                            <span id="rev-additional-discount" class="text-sm font-bold text-orange-600">0.00% (₱ 0.00)</span>
                                        </div>
                                        <div class="flex items-center space-x-6 pt-1 border-t border-slate-200">
                                            <p class="text-xs text-slate-600 font-bold uppercase">Grand Total (PHP):</p>
                                            <h3 id="rev-grand-total" class="text-2xl font-extrabold text-maroon tracking-tight">₱ 0.00</h3>
                                        </div>
                                    </div>
                                    <button onclick="submitReturn()" class="px-10 py-3 bg-maroon text-white rounded-2xl text-xs font-bold hover:bg-maroon-800 transition-all shadow-xl shadow-maroon/20 flex items-center space-x-3 uppercase tracking-widest">
                                        <i data-lucide="check-circle" class="w-5 h-5 text-gold"></i>
                                        <span>Submit Return</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Modal Footer (Fixed) -->
            <div class="p-6 bg-slate-50 border-t border-slate-100 flex justify-between items-center">
                <div id="footer-back-btn-container">
                    <button id="btn-back" onclick="goToStep(Math.max(1, currentStep - 1))" class="hidden px-6 py-2.5 text-xs font-bold text-slate-400 hover:text-maroon transition-all uppercase tracking-widest flex items-center space-x-2">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        <span>Back</span>
                    </button>
                </div>
                <div class="flex space-x-3">
                    <button onclick="toggleModal('add-return-modal', false)" class="px-6 py-2.5 text-xs font-bold text-slate-400 hover:text-slate-600 transition-all uppercase tracking-widest">Cancel</button>
                    <button id="btn-next" onclick="goToStep(2)" class="px-8 py-2.5 bg-maroon text-white rounded-xl text-xs font-bold hover:bg-maroon-800 transition-all shadow-lg flex items-center space-x-2 uppercase tracking-widest">
                        <span id="next-text">Next Step</span>
                        <i data-lucide="chevron-right" class="w-4 h-4 text-gold"></i>
                    </button>
                </div>
            </div>

        </div>
    </div>

    <!-- 1.1 INVOICE SEARCH MODAL -->
    <div id="invoice-search-modal" class="fixed inset-0 z-[950] hidden flex items-center justify-center" role="dialog" aria-modal="true">
        <div onclick="toggleModal('invoice-search-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all max-w-4xl w-full modal-animate-in mx-4">
            <div class="bg-maroon p-5 flex justify-between items-center text-white border-b-2 border-gold">
                <div class="flex items-center space-x-3">
                    <i data-lucide="file-search" class="w-5 h-5 text-gold"></i>
                    <h3 class="text-sm font-bold uppercase tracking-widest">Select Invoice (Owner)</h3>
                </div>
                <button onclick="toggleModal('invoice-search-modal', false)" class="text-white/70 hover:text-white transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div class="p-6">
                <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto custom-scrollbar max-h-[400px]">
                        <table class="w-full text-left border-collapse">
                            <thead class="sticky top-0 bg-slate-50 border-b border-slate-100 z-10">
                                <tr>
                                    <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Invoice No</th>
                                    <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Customer Name</th>
                                    <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Contact No#</th>
                                    <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Contact Person</th>
                                    <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Billing Address</th>
                                </tr>
                                <tr class="bg-slate-50/50">
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('invoice-search-tbody', 0, this.value)" placeholder="Search Inv..." class="column-search-input"></th>
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('invoice-search-tbody', 1, this.value)" placeholder="Search Name..." class="column-search-input"></th>
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('invoice-search-tbody', 2, this.value)" placeholder="Search Contact..." class="column-search-input"></th>
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('invoice-search-tbody', 3, this.value)" placeholder="Search Person..." class="column-search-input"></th>
                                    <th class="p-2 px-3"><input type="text" onkeyup="filterTable('invoice-search-tbody', 4, this.value)" placeholder="Search Address..." class="column-search-input"></th>
                                </tr>
                            </thead>
                            <tbody id="invoice-search-tbody" class="divide-y divide-slate-100 text-xs text-slate-700">
                                <tr><td colspan="5" class="p-4 text-center text-slate-300 italic">Search for invoices to load data.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. VIEW RETURN MODAL -->
    <div id="view-return-modal" class="fixed inset-0 z-[300] hidden flex items-center justify-center" role="dialog" aria-modal="true">
        <div onclick="toggleModal('view-return-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-5xl w-full modal-animate-in mx-4">
            <div class="bg-slate-50 p-6 flex justify-between items-center border-b border-slate-100">
                <div class="flex items-center space-x-3 text-slate-800">
                    <div class="p-2 bg-maroon rounded-lg">
                        <i data-lucide="eye" class="w-5 h-5 text-gold"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold">Return Details View</h3>
                        <p id="view-transaction-label" class="text-[10px] text-slate-400 uppercase tracking-widest">Transaction: ---</p>
                    </div>
                </div>
                <button onclick="toggleModal('view-return-modal', false)" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div class="p-8 space-y-6">
                <!-- Header Info -->
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Return No.</p>
                        <p id="view-return-no" class="text-sm font-bold text-slate-800">---</p>
                    </div>
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Slip No.</p>
                        <p id="view-slip-no" class="text-sm font-bold font-mono text-maroon">---</p>
                    </div>
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Return Date</p>
                        <p id="view-return-date" class="text-sm font-bold text-slate-800">---</p>
                    </div>
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Invoice No.</p>
                        <p id="view-invoice-no" class="text-sm font-bold text-maroon">---</p>
                    </div>
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Supplier</p>
                        <p id="view-supplier-name" class="text-sm font-bold text-slate-800">---</p>
                    </div>
                </div>
                <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Remarks</p>
                    <p id="view-remarks" class="text-sm text-slate-600 italic">---</p>
                </div>
                <!-- Items Table -->
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="p-4 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-widest">Return Items</h4>
                        <span id="view-items-count" class="px-3 py-1 bg-maroon/10 text-maroon text-[9px] font-bold rounded-full uppercase">0 items</span>
                    </div>
                    <div class="overflow-x-auto max-h-[350px] custom-scrollbar">
                        <table class="w-full text-left border-collapse">
                            <thead class="sticky top-0 bg-slate-50 z-10">
                                <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">
                                    <th class="p-4 px-6">Item Code</th>
                                    <th class="p-4 px-6">Description</th>
                                    <th class="p-4 px-6 text-center">QTY</th>
                                    <th class="p-4 px-6 text-center">Unit</th>
                                    <th class="p-4 px-6 text-right">Unit Price</th>
                                    <th class="p-4 px-6 text-center">%Disc</th>
                                    <th class="p-4 px-6 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody id="view-items-tbody" class="divide-y divide-slate-100 text-sm">
                                <tr><td colspan="7" class="p-4 text-center text-slate-300 italic">No items to display.</td></tr>
                            </tbody>
                            <tfoot class="sticky bottom-0 bg-slate-100 border-t-2 border-slate-200">
                                <tr class="text-sm font-bold text-slate-600">
                                    <td colspan="6" class="p-3 px-6 text-right uppercase tracking-wider">Subtotal:</td>
                                    <td id="view-subtotal-amount" class="p-3 px-6 text-right">₱ 0.00</td>
                                </tr>
                                <tr class="text-sm font-bold text-orange-600">
                                    <td colspan="6" class="p-3 px-6 text-right uppercase tracking-wider">Additional Discount:</td>
                                    <td id="view-additional-discount" class="p-3 px-6 text-right">0.00% (₱ 0.00)</td>
                                </tr>
                                <tr class="text-sm font-extrabold text-slate-700 bg-blue-50">
                                    <td colspan="6" class="p-4 px-6 text-right uppercase tracking-wider">Total:</td>
                                    <td id="view-total-amount" class="p-4 px-6 text-right text-maroon">₱ 0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. FILTER MODAL -->
    <div id="filter-modal" class="fixed inset-0 z-[60] hidden flex items-center justify-center" role="dialog" aria-modal="true">
        <div onclick="toggleModal('filter-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-md sm:w-full modal-animate-in mx-4">
            <div class="bg-maroon p-5 flex justify-between items-center text-white border-b-2 border-gold">
                <h3 class="text-sm font-bold uppercase tracking-widest flex items-center gap-2">
                    <i data-lucide="sliders-horizontal" class="w-4 h-4 text-gold"></i>
                    Filter Options
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
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Filter by Discount Range (%)</label>
                    <div class="grid grid-cols-2 gap-3 text-slate-800">
                        <div>
                            <p class="text-[9px] text-slate-400 font-bold mb-1 ml-1 uppercase">Min %</p>
                            <input type="number" placeholder="0" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-maroon/20 outline-none transition-all">
                        </div>
                        <div>
                            <p class="text-[9px] text-slate-400 font-bold mb-1 ml-1 uppercase">Max %</p>
                            <input type="number" placeholder="100" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-maroon/20 outline-none transition-all">
                        </div>
                    </div>
                </div>
            </div>
            <div class="p-6 pt-0 flex space-x-3">
                <button onclick="toggleModal('filter-modal', false)" class="flex-1 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] font-bold rounded-xl transition-all uppercase tracking-widest">Reset</button>
                <button onclick="toggleModal('filter-modal', false)" class="flex-1 px-4 py-2.5 bg-maroon text-white hover:bg-maroon-800 text-[10px] font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg shadow-maroon/20">Apply Filters</button>
            </div>
        </div>
    </div>

    <!-- 4. CONFIRMATION MODAL -->
    <div id="confirm-return-modal" class="fixed inset-0 z-[600] hidden flex items-center justify-center" role="dialog" aria-modal="true">
        <div onclick="toggleModal('confirm-return-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-sm sm:w-full modal-animate-in mx-4">
            <div class="p-6 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-amber-50 mb-4">
                    <i data-lucide="alert-triangle" class="h-6 w-6 text-amber-600"></i>
                </div>
                <h3 id="confirm-modal-title" class="text-lg font-bold text-slate-800 mb-2 tracking-tight uppercase">Confirm Return</h3>
                <p id="confirm-modal-message" class="text-xs text-slate-500 mb-4 leading-relaxed">Are you sure you want to finalize this purchase return? This action will record the transaction in the system.</p>
            </div>
            <div class="bg-slate-50 px-6 py-4 flex flex-col sm:flex-row-reverse gap-2">
                <button onclick="finalizeReturn()" class="w-full px-4 py-2 bg-maroon hover:bg-maroon-800 text-white text-xs font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg shadow-maroon/20">Yes, Confirm</button>
                <button onclick="toggleModal('confirm-return-modal', false)" class="w-full px-4 py-2 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest">No, Review</button>
            </div>
        </div>
    </div>

    <!-- 4.1 DELETE CONFIRMATION MODAL -->
    <div id="delete-return-confirm-modal" class="fixed inset-0 z-[700] hidden flex items-center justify-center" role="dialog" aria-modal="true">
        <div onclick="toggleModal('delete-return-confirm-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-sm sm:w-full modal-animate-in mx-4">
            <div class="p-6 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-50 mb-4">
                    <i data-lucide="trash-2" class="h-6 w-6 text-red-600"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-2 tracking-tight uppercase">Delete Purchase Return</h3>
                <p class="text-xs text-slate-500 mb-4 leading-relaxed">Are you sure you want to archive this purchase return? This action will move the record to Archive.</p>
            </div>
            <div class="bg-slate-50 px-6 py-4 flex flex-col sm:flex-row-reverse gap-2">
                <button onclick="confirmDeletePurchaseReturn()" class="w-full px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg shadow-red-200">Yes, Delete</button>
                <button onclick="toggleModal('delete-return-confirm-modal', false)" class="w-full px-4 py-2 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest">Cancel</button>
            </div>
        </div>
    </div>

    <!-- 5. SUCCESS MODAL -->
    <div id="success-modal" class="fixed inset-0 z-[800] hidden flex items-center justify-center" role="dialog" aria-modal="true">
        <div onclick="toggleModal('success-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-sm sm:w-full modal-animate-in mx-4">
            <div class="p-8 text-center">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-emerald-50 mb-6">
                    <i data-lucide="check-circle" class="h-10 w-10 text-emerald-500"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-800 mb-2">Success!</h3>
                <p id="success-modal-message" class="text-xs text-slate-500 leading-relaxed">The Purchase Return has been successfully processed and recorded in the system.</p>
            </div>
            <div class="p-6 pt-0">
                <button onclick="toggleModal('success-modal', false)" class="w-full px-4 py-3 bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg shadow-emerald-200">Great, Continue</button>
            </div>
        </div>
    </div>

<!-- ============== PRINT TEMPLATE (HIDDEN) ============== -->
<div id="print-area" class="hidden print:block p-6 text-xs font-sans bg-white">
    <style>
        @media print {
            @page { margin: 0.3in; size: portrait; }
            body { margin: 0; padding: 0; background: #fff !important; }
            body * { visibility: hidden; }
            #print-area, #print-area * { visibility: visible; }
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
                padding: 6px 0;
            }
            .print-slip:last-child { page-break-after: avoid; }
            .print-title { font-size: 16pt; font-weight: 900; text-align: center; margin-bottom: 10px; }
            .print-header-table { width: 100%; margin-bottom: 8px; border-collapse: collapse; }
            .print-header-table td { padding: 1px 0; font-size: 9pt; width: 50%; }
            .print-header-table td:last-child { text-align: right; }
            .print-header-table .label { font-weight: bold; }
            .print-header-table .value { font-weight: bold; }
            .print-divider { border-top: 1px dashed #000; margin: 4px 0; }
            .print-items-table { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 8pt; }
            .print-items-table th { border: 1px solid #000; padding: 4px 3px; text-align: center; font-weight: bold; background: #f0f0f0; }
            .print-items-table td { border: 1px solid #000; padding: 3px; text-align: left; }
            .print-items-table td.r { text-align: right; }
            .print-items-table td.c { text-align: center; }
            .print-totals { width: 100%; margin-top: 6px; }
            .print-totals td { padding: 2px 6px; font-size: 9pt; }
            .print-totals .l { text-align: right; font-weight: bold; }
            .print-totals .r { text-align: right; font-weight: bold; width: 180px; }
            .print-totals .grand-total-row td { border-top: 2px solid #000; padding-top: 4px; font-size: 11pt; }
        }
    </style>
    <div id="print-slips-container"></div>
</div>

<template id="single-print-slip">
    <div class="print-slip">
        <div class="print-title">PURCHASE RETURN</div>

        <table class="print-header-table">
            <tr>
                <td><span class="label">Supplier Invoice:</span><span class="value p-invoice-no" style="color:red;font-weight:bold">---</span></td>
                <td style="text-align:right"><span class="label">Return No:</span><span class="value p-return-no" style="color:red;font-weight:bold">---</span></td>
            </tr>
            <tr>
                <td><span class="label">Supplier:</span><span class="value p-supplier">---</span></td>
                <td style="text-align:right"><span class="label">Date:</span><span class="value p-date">---</span></td>
            </tr>
        </table>

        <div class="print-divider"></div>

        <table class="print-items-table">
            <thead>
                <tr>
                    <th style="width:30px">QTY</th>
                    <th style="width:30px">UNIT</th>
                    <th style="width:calc(80px + 1cm)">ITEM CODE</th>
                    <th>DESCRIPTION</th>
                    <th style="width:70px">UNIT PRICE</th>
                    <th style="width:50px">DISCOUNT</th>
                    <th style="width:70px">SUB TOTAL</th>
                </tr>
            </thead>
            <tbody class="p-items-tbody"></tbody>
        </table>

        <div class="print-divider"></div>

        <table class="print-totals">
            <tr>
                <td class="l">SUB TOTAL:</td>
                <td class="r p-subtotal">₱ 0.00</td>
            </tr>
            <tr>
                <td class="l">ADDITIONAL DISCOUNT:</td>
                <td class="r p-additional-discount">0.00% (₱ 0.00)</td>
            </tr>
            <tr class="grand-total-row">
                <td class="l">GRAND TOTAL:</td>
                <td class="r p-grand-total">₱ 0.00</td>
            </tr>
        </table>
    </div>
</template>

</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script>
        window.returnRoutes = {
            invoicesUrl: '<?php echo e(route("regular.purchase-return.invoices")); ?>',
            poItemsUrl: '<?php echo e(route("regular.purchase-return.po-items", ["poId" => ":poId"])); ?>',
            processUrl: '<?php echo e(route("regular.purchase-return.process")); ?>',
            updateUrl: '<?php echo e(route("regular.purchase-return.update", ["id" => ":id"])); ?>',
            deleteUrl: '<?php echo e(route("regular.purchase-return.delete", ["id" => ":id"])); ?>',
            historyUrl: '<?php echo e(route("regular.purchase-return.history")); ?>',
            dashboardUrl: '<?php echo e(route("regular.purchase-return.dashboard")); ?>',
            detailUrl: '<?php echo e(route("regular.purchase-return.detail", ["id" => ":id"])); ?>'
        };
        window.csrfToken = '<?php echo e(csrf_token()); ?>';
    </script>
    <script src="<?php echo e(asset('js/Purchase-Return.js')); ?>?v=<?php echo e(filemtime(public_path('js/Purchase-Return.js'))); ?>"></script>
    <script>
        // Track current step globally in this view
        let currentStep = 1;
        
        // Wrap the original goToStep to handle local UI state
        const originalGoToStep = window.goToStep;
        window.goToStep = function(step) {
            currentStep = step;
            originalGoToStep(step);
            
            // Update modal buttons
            const btnBack = document.getElementById('btn-back');
            const btnNext = document.getElementById('btn-next');
            const nextText = document.getElementById('next-text');
            const nextIcon = document.querySelector('#btn-next i');

            if (step === 1) {
                btnBack.classList.add('hidden');
                btnNext.onclick = () => goToStep(2);
                nextText.innerText = 'Next Step';
                if(nextIcon) nextIcon.setAttribute('data-lucide', 'chevron-right');
            } else if (step === 2) {
                btnBack.classList.remove('hidden');
                btnNext.onclick = () => goToStep(3);
                nextText.innerText = 'Review Return';
                if(nextIcon) nextIcon.setAttribute('data-lucide', 'eye');
            } else {
                btnBack.classList.remove('hidden');
                // On last step, the primary action is in the review panel, 
                // but we can make the footer button "Submit" too for convenience
                btnNext.onclick = () => submitReturn();
                nextText.innerText = 'Finalize Return';
                if(nextIcon) nextIcon.setAttribute('data-lucide', 'check-circle');
            }
            
            if (typeof lucide !== 'undefined') lucide.createIcons();
        };
    </script>
<?php $__env->stopPush(); ?>



<?php echo $__env->make('partials.user_account.user_sidebar_navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\hatdog\resources\views\Regular_User\Purchase\Purchase-Return.blade.php ENDPATH**/ ?>