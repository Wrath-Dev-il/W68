    <!-- PURCHASE NOTE WIZARD (Add / Edit — single modal, 4 steps) -->
    <div id="purchase-note-wizard-modal" class="fixed inset-0 z-[400] hidden flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div onclick="closePurchaseNoteWizard()" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all max-w-6xl w-full modal-animate-in flex flex-col max-h-[92vh]">
            <div class="bg-maroon p-6 flex flex-wrap justify-between items-center gap-4 text-white border-b-4 border-gold shrink-0">
                <div class="flex items-center space-x-4 min-w-0">
                    <div class="p-3 bg-white/10 rounded-2xl border-2 border-gold shadow-sm shrink-0">
                        <i data-lucide="file-text" class="w-6 h-6 text-gold"></i>
                    </div>
                    <div class="min-w-0">
                        <h3 id="pn-wizard-title" class="text-xl font-bold tracking-tight truncate">Add Purchase Note</h3>
                        <p id="pn-wizard-subtitle" class="text-[10px] text-white/60 uppercase tracking-widest font-bold">Follow the steps to complete your purchase note</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4 lg:space-x-8 flex-wrap justify-center">
                    <div id="pn-step-wrapper-1" class="flex flex-col items-center space-y-2">
                        <div id="pn-step-indicator-1" class="step-indicator active">1</div>
                        <span id="pn-step-label-1" class="text-[8px] font-bold uppercase tracking-widest text-white/70">Basic Info</span>
                    </div>
                    <div id="pn-step-sep-1" class="w-10 lg:w-12 h-0.5 bg-white/20 rounded-full mb-4"></div>
                    <div id="pn-step-wrapper-2" class="flex flex-col items-center space-y-2">
                        <div id="pn-step-indicator-2" class="step-indicator pending">2</div>
                        <span id="pn-step-label-2" class="text-[8px] font-bold uppercase tracking-widest text-white/40">Invoices</span>
                    </div>
                    <div id="pn-step-sep-2" class="w-10 lg:w-12 h-0.5 bg-white/20 rounded-full mb-4"></div>
                    <div id="pn-step-wrapper-3" class="flex flex-col items-center space-y-2">
                        <div id="pn-step-indicator-3" class="step-indicator pending">3</div>
                        <span id="pn-step-label-3" class="text-[8px] font-bold uppercase tracking-widest text-white/40">Items</span>
                    </div>
                    <div id="pn-step-sep-3" class="w-10 lg:w-12 h-0.5 bg-white/20 rounded-full mb-4"></div>
                    <div id="pn-step-wrapper-4" class="flex flex-col items-center space-y-2">
                        <div id="pn-step-indicator-4" class="step-indicator pending">4</div>
                        <span id="pn-step-label-4" class="text-[8px] font-bold uppercase tracking-widest text-white/40">Review</span>
                    </div>
                </div>
                <button type="button" onclick="closePurchaseNoteWizard()" class="text-white/70 hover:text-white transition-colors bg-white/10 p-2 rounded-xl shrink-0">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto custom-scrollbar p-6 lg:p-8 min-h-0">
                <!-- Loading overlay for Edit mode -->
                <div id="pn-wizard-loading" class="hidden flex items-center justify-center p-12 min-h-[300px]">
                    <div class="flex flex-col items-center gap-4">
                        <svg class="animate-spin h-12 w-12 text-maroon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="text-sm text-slate-500 font-medium">Loading purchase note...</p>
                    </div>
                </div>
                <!-- STEP 1 -->
                <div id="pn-wizard-step-1" class="pn-wizard-step-content space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Transaction Date</label>
                            <input type="date" id="new-trans-date" class="w-full px-3 py-2 border border-slate-200 rounded-xl bg-white text-sm focus:ring-2 focus:ring-maroon/20 focus:border-maroon transition-all text-slate-800">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">P.O No# (Automated)</label>
                            <input type="text" id="new-po-number" value="0000001" readonly class="w-full px-3 py-2 border border-slate-200 rounded-xl bg-slate-50 text-sm font-mono text-slate-500 cursor-not-allowed outline-none">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Supplier Name (Search)</label>
                        <div class="relative text-slate-800">
                            <i data-lucide="search" class="absolute left-3 top-2.5 w-4 h-4 text-slate-400"></i>
                            <input type="text" id="supplier-search-input-trigger" onclick="openSupplierSearchModal()" placeholder="Click to search supplier..." readonly class="w-full pl-10 pr-4 py-2 border border-slate-200 rounded-xl text-sm cursor-pointer hover:bg-slate-50 transition-all bg-white">
                            <input type="hidden" id="selected-supplier-id">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Remarks</label>
                        <textarea id="new-remarks" rows="3" class="w-full px-4 py-2 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-maroon/20 focus:border-maroon transition-all text-slate-800" placeholder="Enter additional notes..."></textarea>
                    </div>
                    <input type="hidden" id="new-total-amount" value="0.00">
                    <input type="hidden" id="new-po-status" value="Open">
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" onclick="closePurchaseNoteWizard()" class="px-6 py-2.5 text-xs font-bold text-slate-500 hover:text-slate-700 uppercase tracking-widest">Cancel</button>
                        <button type="button" id="pn-step1-next-btn" onclick="goToStep(2)" class="px-8 py-2.5 bg-maroon text-white hover:bg-maroon-800 text-xs font-bold rounded-xl shadow-lg flex items-center gap-2">
                            <span>Next</span>
                            <i data-lucide="chevron-right" class="w-4 h-4 text-gold"></i>
                        </button>
                    </div>
                </div>

                <!-- STEP 2: Invoices (edit mode) or Linked Partial Notes (add mode) -->
                <div id="pn-wizard-step-2" class="pn-wizard-step-content hidden space-y-5">
                    <!-- Linked Partial Notes Section (Add Mode) -->
                    <div id="pn-linked-partial-notes-section" class="hidden space-y-5">
                        <div class="bg-blue-50 p-4 rounded-xl border border-blue-200">
                            <p class="text-[10px] font-bold text-blue-700 uppercase tracking-widest flex items-center gap-2">
                                <i data-lucide="alert-circle" class="w-4 h-4"></i>
                                This supplier has forgotten Partial Purchase Note items. Select items to transfer into this new Purchase Note.
                            </p>
                        </div>
                        <div id="pn-partial-notes-container" class="space-y-4">
                            <div class="text-center text-slate-400 italic text-xs py-8">Loading forgotten partial items...</div>
                        </div>
                        <div class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-4 pt-2">
                            <button type="button" onclick="goToStep(1)" class="flex items-center gap-2 text-slate-500 hover:text-maroon text-xs font-bold uppercase tracking-widest">
                                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                                Back to Basic Info
                            </button>
                            <div class="flex gap-3 justify-end">
                                <button type="button" onclick="closePurchaseNoteWizard()" class="px-6 py-2.5 text-xs font-bold text-slate-500 uppercase tracking-widest">Cancel</button>
                                <button type="button" id="pn-transfer-items-btn" onclick="transferSelectedPartialItems()" class="px-8 py-2.5 bg-blue-600 text-white hover:bg-blue-700 text-xs font-bold rounded-xl shadow-lg flex items-center gap-2">
                                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                                    <span>Transfer Selected Items</span>
                                </button>
                                <button type="button" onclick="goToStep(3)" class="px-8 py-2.5 bg-maroon text-white hover:bg-maroon-800 text-xs font-bold rounded-xl shadow-lg flex items-center gap-2">
                                    <span>Next: Items</span>
                                    <i data-lucide="chevron-right" class="w-4 h-4 text-gold"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <!-- Invoices Section (Edit Mode) -->
                    <div id="pn-invoices-section">
                        <div class="bg-amber-50 p-4 rounded-xl border border-amber-200">
                            <p class="text-[10px] font-bold text-amber-700 uppercase tracking-widest flex items-center gap-2">
                                <i data-lucide="info" class="w-4 h-4"></i>
                                Select an invoice below, then click "Next: Items" to edit its purchase order items.
                            </p>
                        </div>
                        <!-- Filter Status Banner -->
                        <div class="flex items-center justify-between bg-slate-50 p-3 rounded-xl border border-slate-200 text-xs text-slate-700 mt-4">
                            <span class="font-medium text-slate-600">Active Invoice Filter: <span id="pn-invoice-filter-status" class="text-slate-500 font-bold ml-1">All Invoices (No Filter)</span></span>
                            <button type="button" id="pn-clear-invoice-filter-btn" onclick="clearInvoiceFilter()" class="hidden px-2.5 py-1 bg-white hover:bg-slate-100 border border-slate-300 text-slate-600 rounded-lg font-bold text-[10px] uppercase tracking-widest transition-all">Clear Filter</button>
                        </div>
                        <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
                            <div class="overflow-x-auto custom-scrollbar max-h-[min(340px,40vh)]">
                                <table class="w-full text-left border-collapse min-w-[700px]">
                                    <thead class="sticky top-0 bg-slate-50 border-b border-slate-100 z-10">
                                        <tr>
                                            <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase text-center w-12">Select</th>
                                            <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase">PO Number</th>
                                            <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase">Receiving No.</th>
                                            <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase">Supplier Invoice</th>
                                            <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase">Date</th>
                                            <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase text-center">Status</th>
                                            <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase text-center w-24">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="pn-invoices-tbody" class="divide-y divide-slate-100 text-xs text-slate-700">
                                        <tr>
                                            <td colspan="7" class="p-8 text-center text-slate-400 italic">No invoices found for this purchase note.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-4 pt-2">
                            <button type="button" onclick="goToStep(1)" class="flex items-center gap-2 text-slate-500 hover:text-maroon text-xs font-bold uppercase tracking-widest">
                                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                                Back to Basic Info
                            </button>
                            <div class="flex gap-3 justify-end">
                                <button type="button" onclick="closePurchaseNoteWizard()" class="px-6 py-2.5 text-xs font-bold text-slate-500 uppercase tracking-widest">Cancel</button>
                                <button type="button" onclick="goToStep(3)" class="px-8 py-2.5 bg-maroon text-white hover:bg-maroon-800 text-xs font-bold rounded-xl shadow-lg flex items-center gap-2">
                                    <span>Next: Items</span>
                                    <i data-lucide="chevron-right" class="w-4 h-4 text-gold"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 3 (was Step 2): Items -->
                <div id="pn-wizard-step-3" class="pn-wizard-step-content hidden space-y-5">
                    <!-- Filter Status Banner Step 3 -->
                    <div class="flex items-center justify-between bg-slate-50 p-3 rounded-xl border border-slate-200 text-xs text-slate-700">
                        <span class="font-medium text-slate-600 flex items-center gap-2">
                            <i data-lucide="filter" class="w-4 h-4 text-slate-400"></i>
                            Active Invoice Filter: <span id="pn-invoice-filter-status-step3" class="text-slate-500 font-bold ml-1">All Invoices (No Filter)</span>
                        </span>
                        <button type="button" id="pn-clear-invoice-filter-btn-step3" onclick="clearInvoiceFilter()" class="hidden px-2.5 py-1 bg-white hover:bg-slate-100 border border-slate-300 text-slate-600 rounded-lg font-bold text-[10px] uppercase tracking-widest transition-all">Clear Filter</button>
                    </div>
                    <!-- Fallback warning: PO has no items, showing PN items instead -->
                    <div id="pn-po-empty-fallback" class="hidden flex items-center gap-2 bg-amber-50 border border-amber-200 p-3 rounded-xl text-xs text-amber-700">
                        <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0 text-amber-500"></i>
                        <span>This invoice has no items yet. Items shown below are from the purchase note and will be saved to this invoice.</span>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-2 tracking-widest">P.O Items Search Bar</label>
                        <div class="relative">
                            <i data-lucide="search" class="absolute left-3 top-2.5 w-4 h-4 text-slate-400"></i>
                            <input type="text" readonly onclick="openItemSearchModal()" placeholder="Click to search and add items..." class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl text-sm cursor-pointer hover:bg-white transition-all bg-white shadow-sm">
                        </div>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
                        <div class="overflow-x-auto custom-scrollbar max-h-[min(340px,40vh)]">
                            <table class="w-full text-left border-collapse min-w-[920px]">
                                <thead class="sticky top-0 bg-slate-50 border-b border-slate-100 z-10" id="po-items-thead">
                                    <tr>
                                        <th class="p-3 px-3 text-[10px] font-bold text-slate-500 uppercase text-center w-24">Arrangement</th>
                                        <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase">Item Code</th>
                                        <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase">Part No.</th>
                                        <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase">Description</th>
                                        <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase">Unit</th>
                                        <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase w-24">QTY</th>
                                        <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase w-24 hidden" id="actual-qty-header">ACTUAL QTY</th>
                                        <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase w-48">Unit Cost</th>
                                        <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase text-right w-32">Sub Total</th>
                                        <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase text-center w-20">Action</th>
                                    </tr>
                                    <tr id="pn-item-column-search-row" class="bg-slate-50/80 border-t border-slate-100">
                                        <th class="p-2 px-3"></th>
                                        <th class="p-2 px-3"><input type="text" data-pn-item-search="itemCode" oninput="filterPurchaseNoteItems('itemCode', this.value)" placeholder="Search Code..." class="column-search-input text-[9px]"></th>
                                        <th class="p-2 px-3"><input type="text" data-pn-item-search="partNo" oninput="filterPurchaseNoteItems('partNo', this.value)" placeholder="Search Part No..." class="column-search-input text-[9px]"></th>
                                        <th class="p-2 px-3"><input type="text" data-pn-item-search="description" oninput="filterPurchaseNoteItems('description', this.value)" placeholder="Search Description..." class="column-search-input text-[9px]"></th>
                                        <th class="p-2 px-3"><input type="text" data-pn-item-search="unit" oninput="filterPurchaseNoteItems('unit', this.value)" placeholder="Search Unit..." class="column-search-input text-[9px]"></th>
                                        <th class="p-2 px-3"><input type="text" data-pn-item-search="qty" oninput="filterPurchaseNoteItems('qty', this.value)" placeholder="Search QTY..." class="column-search-input text-[9px]"></th>
                                        <th class="p-2 px-3 hidden" id="actual-qty-search-header"><input type="text" data-pn-item-search="actualQty" oninput="filterPurchaseNoteItems('actualQty', this.value)" placeholder="Search Actual..." class="column-search-input text-[9px]"></th>
                                        <th class="p-2 px-3"><input type="text" data-pn-item-search="cost" oninput="filterPurchaseNoteItems('cost', this.value)" placeholder="Search Cost..." class="column-search-input text-[9px]"></th>
                                        <th class="p-2 px-3"><input type="text" data-pn-item-search="subTotal" oninput="filterPurchaseNoteItems('subTotal', this.value)" placeholder="Search Sub Total..." class="column-search-input text-[9px]"></th>
                                        <th class="p-2 px-3"><input type="text" data-pn-item-search="action" oninput="filterPurchaseNoteItems('action', this.value)" placeholder="Search Action..." class="column-search-input text-[9px]"></th>
                                    </tr>
                                </thead>
                                <tbody id="po-items-tbody" class="divide-y divide-slate-100 text-xs text-slate-700"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="flex flex-col sm:flex-row justify-between items-stretch sm:items-end gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                        <button type="button" id="pn-step3-back-btn" onclick="goToStep(2)" class="flex items-center gap-2 text-slate-500 hover:text-maroon text-xs font-bold uppercase tracking-widest">
                            <i data-lucide="arrow-left" class="w-4 h-4"></i>
                            <span id="pn-step3-back-text">Back to Invoices</span>
                        </button>
                        <div class="text-right">
                            <p class="text-[10px] text-slate-400 font-bold uppercase mb-1">Total of Sub Total</p>
                            <h3 id="po-items-grand-total" class="text-2xl font-extrabold text-maroon">₱ 0.00</h3>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" onclick="closePurchaseNoteWizard()" class="px-6 py-2.5 text-xs font-bold text-slate-500 uppercase tracking-widest">Cancel</button>
                        <button type="button" onclick="goToStep(4)" class="px-8 py-2.5 bg-maroon text-white hover:bg-maroon-800 text-xs font-bold rounded-xl shadow-lg flex items-center gap-2">
                            <span>Review</span>
                            <i data-lucide="eye" class="w-4 h-4 text-gold"></i>
                        </button>
                    </div>
                </div>

                <!-- STEP 4 (was Step 3): Review -->
                <div id="pn-wizard-step-4" class="pn-wizard-step-content hidden space-y-5">
                    <!-- Filter Status Banner Step 4 -->
                    <div class="flex items-center justify-between bg-slate-50 p-3 rounded-xl border border-slate-200 text-xs text-slate-700">
                        <span class="font-medium text-slate-600 flex items-center gap-2">
                            <i data-lucide="filter" class="w-4 h-4 text-slate-400"></i>
                            Active Invoice Filter: <span id="pn-invoice-filter-status-step4" class="text-slate-500 font-bold ml-1">All Invoices (No Filter)</span>
                        </span>
                        <button type="button" id="pn-clear-invoice-filter-btn-step4" onclick="clearInvoiceFilter()" class="hidden px-2.5 py-1 bg-white hover:bg-slate-100 border border-slate-300 text-slate-600 rounded-lg font-bold text-[10px] uppercase tracking-widest transition-all">Clear Filter</button>
                    </div>
                    <div class="space-y-3">
                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-widest border-l-4 border-maroon pl-3">Basic Info</h4>
                        <div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase">Trans Date</th>
                                        <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase">P.O No#</th>
                                        <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase">Supplier</th>
                                        <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase">Remarks</th>
                                        <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="text-slate-700">
                                    <tr>
                                        <td id="review-trans-date" class="p-3 px-4"></td>
                                        <td id="review-po-number" class="p-3 px-4 font-mono font-bold text-maroon"></td>
                                        <td id="review-supplier-name" class="p-3 px-4"></td>
                                        <td id="review-remarks" class="p-3 px-4 italic text-slate-500"></td>
                                        <td id="review-po-status" class="p-3 px-4"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-widest border-l-4 border-maroon pl-3">Purchase Items</h4>
                        <div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
                            <div class="overflow-x-auto custom-scrollbar max-h-[min(280px,35vh)]">
                                <table class="w-full text-left text-xs min-w-[700px]">
                                    <thead class="bg-slate-50 sticky top-0">
                                        <tr>
                                            <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase">Item Code</th>
                                            <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase">Part No.</th>
                                            <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase">Description</th>
                                            <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase">Unit</th>
                                            <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase text-center">QTY</th>
                                            <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase text-right">Unit Cost</th>
                                            <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase text-right">Sub Total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="review-items-tbody" class="divide-y divide-slate-100"></tbody>
                                    <tfoot class="bg-slate-50/80">
                                        <tr>
                                            <td colspan="6" class="p-3 px-4 text-right text-[10px] font-bold text-slate-500 uppercase">Grand Total</td>
                                            <td id="review-grand-total" class="p-3 px-4 text-right font-extrabold text-maroon">₱ 0.00</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-4 pt-2">
                        <button type="button" onclick="goToStep(3)" class="flex items-center gap-2 text-slate-500 hover:text-maroon text-xs font-bold uppercase tracking-widest">
                            <i data-lucide="arrow-left" class="w-4 h-4"></i>
                            Back to Items
                        </button>
                        <div class="flex gap-3 justify-end">
                            <button type="button" onclick="closePurchaseNoteWizard()" class="px-6 py-2.5 text-xs font-bold text-slate-500 uppercase tracking-widest">Cancel</button>
                            <button type="button" onclick="handleFinalizePurchaseNote(event)" class="px-10 py-2.5 bg-maroon text-white hover:bg-maroon-800 text-xs font-bold rounded-xl shadow-lg flex items-center gap-2">
                                <i data-lucide="save" class="w-4 h-4 text-gold"></i>
                                <span id="finalize-btn-text">Submit Purchase Note</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

{{-- W68 Purchase Note Arrangement modal: UI only; NO database field --}}
<div id="pn-arrangement-modal" class="fixed inset-0 z-[1300] hidden flex items-center justify-center p-4" role="dialog" aria-modal="true">
    <div onclick="closePurchaseNoteArrangementModal()" class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm"></div>
    <div class="relative w-full max-w-lg overflow-hidden rounded-3xl bg-white shadow-2xl">
        <div class="bg-maroon px-6 py-5 text-white border-b-4 border-gold flex items-center justify-between gap-4">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gold">Item Arrangement</p>
                <h3 class="mt-1 text-lg font-bold">Move Purchase Note Item</h3>
            </div>
            <button type="button" onclick="closePurchaseNoteArrangementModal()" class="rounded-xl bg-white/10 p-2 text-white/70 hover:text-white">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>

        <div class="p-6 space-y-5">
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Current No.</p>
                    <p id="pn-arrangement-current" class="mt-1 text-xl font-black text-maroon">#1</p>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Product Code</p>
                    <p id="pn-arrangement-product-code" class="mt-1 break-all text-sm font-black text-slate-700">N/A</p>
                </div>
            </div>

            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs leading-5 text-amber-800">
                Fill only one field. Example: item <strong>#20</strong> + <strong>Add Before #5</strong> moves it to <strong>#4</strong>. Leave Add After blank. Add After #5 moves it to #6.
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="pn-arrangement-before" class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-slate-500">Add Before No.</label>
                    <input id="pn-arrangement-before" type="number" min="1" step="1" inputmode="numeric" placeholder="Example: 5"
                        class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-800 outline-none focus:border-maroon focus:ring-2 focus:ring-maroon/10">
                </div>
                <div>
                    <label for="pn-arrangement-after" class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-slate-500">Add After No.</label>
                    <input id="pn-arrangement-after" type="number" min="1" step="1" inputmode="numeric" placeholder="Leave blank if using Before"
                        class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-800 outline-none focus:border-maroon focus:ring-2 focus:ring-maroon/10">
                </div>
            </div>

            <p id="pn-arrangement-error" class="hidden rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-bold text-red-600"></p>

            <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                <button type="button" onclick="closePurchaseNoteArrangementModal()" class="rounded-xl px-5 py-2.5 text-xs font-black uppercase tracking-widest text-slate-500 hover:bg-slate-50">Cancel</button>
                <button type="button" onclick="applyPurchaseNoteArrangement()" class="rounded-xl bg-maroon px-6 py-2.5 text-xs font-black uppercase tracking-widest text-white shadow-lg hover:bg-maroon-800">
                    Apply Arrangement
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    #po-items-tbody .pn-arrangement-drop-before > td {
        border-top: 3px solid #800000 !important;
    }
    #po-items-tbody .pn-arrangement-drop-after > td {
        border-bottom: 3px solid #800000 !important;
    }
    #po-items-tbody [data-pn-arrangement-cell] {
        position: sticky;
        left: 0;
        z-index: 2;
    }
</style>

@push('scripts')
    <script src="{{ asset('js/Purchase-Note-Arrangement.js') }}?v={{ time() }}"></script>
@endpush