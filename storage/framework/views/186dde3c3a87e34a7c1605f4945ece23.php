<?php $__env->startPush('styles'); ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
    <style>
        #page-payable-cheque-voucher { font-family: 'Inter', sans-serif; }
        .pcv-search { width: 100%; border: 1px solid #e2e8f0; border-radius: .5rem; padding: .45rem .65rem; font-size: 10px; font-weight: 700; outline: none; }
        .pcv-search:focus, .pcv-field:focus { border-color: rgba(74,10,21,.45); box-shadow: 0 0 0 3px rgba(74,10,21,.08); }
        .pcv-field { width: 100%; min-width: 0; border: 1px solid #e2e8f0; background: #f8fafc; border-radius: .75rem; padding: .7rem .85rem; font-size: 12px; font-weight: 700; color: #334155; outline: none; }
        .pcv-tag { display: inline-flex; align-items: center; gap: .35rem; width: max-content; border: 1px solid #fde68a; background: #fffbeb; color: #92400e; border-radius: 999px; padding: .35rem .65rem; font-size: 10px; font-weight: 900; text-transform: uppercase; }
        .pcv-step-dot { position: relative; z-index: 2; width: 2.25rem; height: 2.25rem; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 900; border: 2px solid rgba(255,255,255,.30); background: #8b1d2c; color: rgba(255,255,255,.72); box-shadow: 0 0 0 8px #8b1d2c; }
        .pcv-step-dot.active, .pcv-step-dot.done { background: #FFC72C; color: #4A0A15; border-color: #FFC72C; }
        .pcv-stepper-line { z-index: 0; pointer-events: none; }
        .pcv-stepper-item { position: relative; z-index: 1; }
        .pcv-step-one, .pcv-step-one-grid { min-height: 0; }
        .pcv-left-pane, .pcv-right-pane { min-height: 0; overflow-y: auto; overscroll-behavior: contain; }
        .pcv-right-pane { overflow-x: hidden; }
        @media (min-width: 1024px) {
            .pcv-left-pane, .pcv-right-pane { height: 100%; }
            .pcv-right-pane { min-width: 0; }
        }
        .pcv-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
        .pcv-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
        #pcv-supplier-map { min-height: 190px; z-index: 1; }
        @keyframes gold-blink { 0%, 100% { box-shadow: 0 0 0 0 rgba(255, 199, 44, 0.7); } 50% { box-shadow: 0 0 0 8px rgba(255, 199, 44, 0); } }
        @keyframes gold-blink-bg { 0%, 100% { background-color: transparent; } 50% { background-color: rgba(255, 199, 44, 0.25); } }
        .pcv-gold-blink { animation: gold-blink 1.2s ease-in-out infinite; }
        .pcv-gold-blink-row { animation: gold-blink-bg 1.2s ease-in-out infinite; }
    </style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('payable_cheque_voucher_content'); ?>
<div id="page-payable-cheque-voucher" class="space-y-6">
    <!-- Dashboard filter cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <button type="button" data-dashboard-filter="pending" class="dashboard-card dashboard-card-active text-left bg-yellow-400 border border-yellow-500 rounded-xl p-4 shadow-sm hover:bg-yellow-300 transition-all">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-widest text-maroon-900">Pending Invoices</span>
                <span class="w-9 h-9 rounded-lg bg-yellow-300 text-maroon-800 flex items-center justify-center"><i data-lucide="file-text" class="w-4 h-4"></i></span>
            </div>
            <div class="mt-4 flex items-end justify-between gap-3">
                <span id="pcv-dash-pending" class="text-3xl font-black text-maroon-900">0</span>
                <span class="text-[10px] font-black text-maroon-700 uppercase pb-1">Suppliers</span>
            </div>
        </button>
        <button type="button" data-dashboard-filter="old" class="dashboard-card dashboard-card-inactive text-left bg-maroon-900 border border-maroon-800 rounded-xl p-4 shadow-sm hover:bg-maroon-800 transition-all">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-widest text-white">Old Invoices</span>
                <span class="w-9 h-9 rounded-lg bg-white/10 text-goldlining-400 flex items-center justify-center"><i data-lucide="archive" class="w-4 h-4"></i></span>
            </div>
            <div class="mt-4 flex items-end justify-between gap-3">
                <span id="pcv-dash-old" class="text-3xl font-black text-white">0</span>
                <span class="text-[10px] font-black text-yellow-400 uppercase pb-1">Suppliers</span>
            </div>
        </button>
        <button type="button" data-dashboard-filter="sudden_returns" class="dashboard-card dashboard-card-inactive text-left bg-maroon-900 border border-maroon-800 rounded-xl p-4 shadow-sm hover:bg-maroon-800 transition-all">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-widest text-white">Suddenly Returns</span>
                <span class="w-9 h-9 rounded-lg bg-white/10 text-goldlining-400 flex items-center justify-center"><i data-lucide="rotate-cw" class="w-4 h-4"></i></span>
            </div>
            <div class="mt-4 flex items-end justify-between gap-3">
                <span id="pcv-dash-sudden" class="text-3xl font-black text-white">0</span>
                <span class="text-[10px] font-black text-yellow-400 uppercase pb-1">Suppliers</span>
            </div>
        </button>
    </div>

    <!-- Global search -->
    <div class="relative">
        <i data-lucide="search" class="absolute left-3 top-2.5 w-4 h-4 text-slate-400"></i>
        <input id="pcv-global-search" type="text" placeholder="Search invoice no. across Active Payables and History..." class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold outline-none focus:border-maroon-700 focus:ring-4 focus:ring-maroon-900/5">
    </div>

    <!-- Tabs -->
    <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
        <div class="flex items-center space-x-4">
            <button id="pcv-tab-payables" type="button" class="px-5 py-2.5 bg-yellow-400 text-maroon-900 text-[10px] font-bold rounded-xl uppercase tracking-widest shadow-sm transition-all hover:bg-yellow-300 flex items-center gap-2">
                <i data-lucide="wallet" class="w-4 h-4"></i>
                <span>Payables</span>
            </button>
            <button id="pcv-tab-history" type="button" class="px-5 py-2.5 bg-maroon-900 text-yellow-400 text-[10px] font-bold rounded-xl uppercase tracking-widest transition-all hover:bg-maroon-800 flex items-center gap-2">
                <i data-lucide="clock" class="w-4 h-4"></i>
                <span>History</span>
            </button>
        </div>
    </div>

    <div id="pcv-payables-panel">

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto pcv-scroll">
                <table class="w-full min-w-[980px] text-left">
                    <thead>
                        <tr class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-500 border-b border-slate-200">
                            <th class="px-5 py-4">Supplier</th>
                            <th class="px-5 py-4 text-right">Not Paid Invoice</th>
                            <th class="px-5 py-4 text-right">Partial Paid</th>
                            <th class="px-5 py-4 text-right">Total Amount</th>
                            <th class="px-5 py-4 text-center">Action</th>
                        </tr>
                        <tr class="bg-white border-b border-slate-100">
                            <th class="px-5 py-2"><input data-main-col="supplier" class="pcv-search" type="text" placeholder="Search Supplier..."></th>
                            <th class="px-5 py-2"><input data-main-col="notPaid" class="pcv-search text-right" type="text" placeholder="Search Not Paid..."></th>
                            <th class="px-5 py-2"><input data-main-col="partialPaid" class="pcv-search text-right" type="text" placeholder="Search Partial..."></th>
                            <th class="px-5 py-2"><input data-main-col="totalAmount" class="pcv-search text-right" type="text" placeholder="Search Amount..."></th>
                            <th class="px-5 py-2"></th>
                        </tr>
                    </thead>
                    <tbody id="pcv-main-tbody" class="divide-y divide-slate-100 text-sm"></tbody>
                </table>
            </div>
            <div class="px-5 py-4 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/60">
                <p class="text-xs font-bold text-slate-500">Showing <span id="pcv-main-from" class="text-maroon-700">0</span> to <span id="pcv-main-to" class="text-maroon-700">0</span> of <span id="pcv-main-total" class="text-maroon-700">0</span> suppliers</p>
                <div id="pcv-main-pagination" class="flex items-center gap-1"></div>
            </div>
        </div>
    </div>

    <div id="pcv-history-panel" class="hidden">
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto pcv-scroll">
                <table class="w-full min-w-[1250px] text-left">
                    <thead>
                        <tr class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-500 border-b border-slate-200">
                            <th class="px-4 py-4">PCV</th>
                            <th class="px-4 py-4">Supplier Name</th>
                            <th class="px-4 py-4 text-right">Invoice Amount</th>
                            <th class="px-4 py-4 text-right">Return Total</th>
                            <th class="px-4 py-4 text-right">Amount Paid</th>
                            <th class="px-4 py-4 text-right">Remaining</th>
                            <th class="px-4 py-4 text-center">Status</th>
                            <th class="px-4 py-4">Date</th>
                        </tr>
                        <tr class="bg-white border-b border-slate-100 text-[9px] font-black uppercase tracking-wider text-slate-400">
                            <th class="px-4 py-2"><input data-hist-col="pcv" class="pcv-search" type="text" placeholder="Search PCV..."></th>
                            <th class="px-4 py-2"><input data-hist-col="supplier_name" class="pcv-search" type="text" placeholder="Search Supplier..."></th>
                            <th class="px-4 py-2"><input data-hist-col="invoice_amount" class="pcv-search text-right" type="text" placeholder="Search Amount..."></th>
                            <th class="px-4 py-2"><input data-hist-col="return_total" class="pcv-search text-right" type="text" placeholder="Search Return..."></th>
                            <th class="px-4 py-2"><input data-hist-col="amount_paid" class="pcv-search text-right" type="text" placeholder="Search Paid..."></th>
                            <th class="px-4 py-2"><input data-hist-col="remaining" class="pcv-search text-right" type="text" placeholder="Search Remaining..."></th>
                            <th class="px-4 py-2"><input data-hist-col="status" class="pcv-search text-center" type="text" placeholder="Search Status..."></th>
                            <th class="px-4 py-2"><input data-hist-col="date" class="pcv-search" type="text" placeholder="YYYY-MM-DD"></th>
                        </tr>
                    </thead>
                    <tbody id="pcv-history-tbody" class="divide-y divide-slate-100 text-sm"></tbody>
                </table>
            </div>
            <div class="px-5 py-4 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/60">
                <p class="text-xs font-bold text-slate-500">Showing <span id="pcv-history-from" class="text-maroon-700">0</span> to <span id="pcv-history-to" class="text-maroon-700">0</span> of <span id="pcv-history-total" class="text-maroon-700">0</span> PCVs</p>
                <div id="pcv-history-pagination" class="flex items-center gap-1"></div>
            </div>
        </div>
    </div>
    <div id="pcv-proceed-modal" class="fixed inset-0 z-[600] hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-950/65 backdrop-blur-sm" onclick="window.pcvCloseModal()"></div>
        <div class="relative bg-white w-full h-[95vh] max-w-[1400px] rounded-2xl shadow-2xl flex flex-col overflow-hidden">
            <div class="bg-maroon-900 text-white px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-white/10 flex items-center justify-center"><i data-lucide="file-check-2" class="w-5 h-5 text-goldlining-400"></i></div>
                    <div><h3 class="text-sm font-black uppercase tracking-widest">Proceed Voucher</h3><p id="pcv-modal-supplier-title" class="text-[11px] font-semibold text-white/60 mt-0.5">Supplier</p></div>
                </div>
                <div class="flex items-center gap-2 sm:gap-3 ml-auto mr-2">
                    <div class="hidden sm:block rounded-lg border border-white/15 bg-white/10 px-3 py-2 text-right min-w-[135px]">
                        <div class="text-[8px] font-black uppercase tracking-widest text-white/55">Invoice Amount</div>
                        <div id="pcv-header-invoice-amount" class="text-xs font-black text-goldlining-300 mt-0.5">PHP 0.00</div>
                    </div>
                    <div class="hidden sm:block rounded-lg border border-emerald-300/25 bg-emerald-400/10 px-3 py-2 text-right min-w-[125px]">
                        <div class="text-[8px] font-black uppercase tracking-widest text-white/55">Payment Total</div>
                        <div id="pcv-header-payment-total" class="text-xs font-black text-emerald-300 mt-0.5">PHP 0.00</div>
                    </div>
                    <div class="hidden sm:block rounded-lg border border-amber-300/25 bg-amber-400/10 px-3 py-2 text-right min-w-[125px]">
                        <div class="text-[8px] font-black uppercase tracking-widest text-white/55">Remaining</div>
                        <div id="pcv-header-remaining" class="text-xs font-black text-amber-300 mt-0.5">PHP 0.00</div>
                    </div>
                </div>
                <button type="button" onclick="window.pcvCloseModal()" class="w-9 h-9 rounded-lg hover:bg-white/10 flex items-center justify-center flex-shrink-0"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <div class="px-6 py-4 bg-maroon-800 border-b border-white/10">
                <div class="relative max-w-2xl mx-auto">
                    <div class="pcv-stepper-line absolute top-[1.125rem] left-8 right-8 h-0.5 bg-white/12"></div>
                    <div id="pcv-step-line-active" class="pcv-stepper-line absolute top-[1.125rem] left-8 right-8 h-0.5 origin-left scale-x-0 bg-goldlining-400 transition-transform duration-500"></div>
                    <div id="pcv-stepper-grid" class="relative grid grid-cols-5 gap-4">
                    <?php $__currentLoopData = [1 => 'Supplier', 2 => 'Sudden Return', 3 => 'Invoices', 4 => 'Payment', 5 => 'Review']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $step => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div id="pcv-stepper-item-<?php echo e($step); ?>" class="pcv-stepper-item flex flex-col items-center gap-2">
                            <span id="pcv-step-dot-<?php echo e($step); ?>" class="pcv-step-dot <?php echo e($step === 1 ? 'active' : ''); ?>"><?php echo e($step); ?></span>
                            <span class="text-[9px] font-black uppercase tracking-widest text-white/70"><?php echo e($label); ?></span>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
            </div>

            <div class="flex-1 min-h-0 overflow-y-auto pcv-scroll">
                <div id="pcv-step-1" class="pcv-step pcv-step-one p-6">
                    <div class="pcv-step-one-grid grid grid-cols-1 lg:grid-cols-[40fr_60fr] gap-6">
                        <div class="pcv-left-pane pcv-scroll bg-slate-50 border border-slate-200 rounded-xl p-5 space-y-4">
                            <h4 class="text-xs font-black uppercase tracking-widest text-maroon-800">Supplier Details</h4>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="space-y-1"><label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Voucher No.</label><span id="pcv-voucher-no" class="pcv-tag">PCV-0000</span></div>
                                <div class="space-y-1"><label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Date</label><span id="pcv-voucher-date" class="pcv-tag">2026-06-01</span></div>
                            </div>
                            <div class="space-y-3">
                                <div><label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Supplier Name</label><p id="pcv-supplier-name" class="mt-1 text-sm font-black text-slate-800">---</p></div>
                                <div><label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Contact</label><p id="pcv-supplier-contact" class="mt-1 text-sm font-bold text-slate-700">---</p></div>
                                <div><label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Contact Person</label><p id="pcv-supplier-person" class="mt-1 text-sm font-bold text-slate-700">---</p></div>
                                <div><label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Address</label><p id="pcv-supplier-address" class="mt-1 text-xs font-bold leading-relaxed text-slate-700 bg-white border border-slate-200 rounded-lg p-3">---</p></div>
                            </div>
                            <div id="pcv-supplier-map" class="rounded-xl border border-slate-200 overflow-hidden bg-slate-100"></div>
                        </div>
                        <div class="pcv-right-pane pcv-scroll bg-white border border-slate-200 rounded-xl p-5 space-y-4">
                            <h4 class="text-xs font-black uppercase tracking-widest text-maroon-800">Voucher Reference</h4>
                            <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                                <div><label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Ref No.</label><input id="pcv-ref-no" class="pcv-field mt-1" type="text" placeholder="Enter reference number"></div>
                                <div><label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Particulars</label><input id="pcv-particulars" class="pcv-field mt-1" type="text" placeholder="Enter particulars"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="pcv-step-2" class="pcv-step hidden p-6">
                    <div class="w-full space-y-5">
                        <h4 class="text-xs font-black uppercase tracking-widest text-maroon-800">Step 2 — Sudden Return</h4>
                        <p id="pcv-step-2-empty" class="text-sm font-bold text-slate-400 py-8 text-center">No sudden returns detected.</p>
                        <div id="pcv-step-2-content" class="hidden">
                            <p class="text-xs font-bold text-slate-500 mb-4">The following returns were found but not deducted from previous vouchers. Tick the ones you want to apply to this voucher and enter remarks.</p>
                            <div class="overflow-x-auto border border-slate-200 rounded-xl">
                                <table class="w-full text-left text-xs">
                                    <thead>
                                        <tr class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-500 border-b border-slate-200">
                                            <th class="px-4 py-3 text-center w-10"><input id="pcv-sudden-select-all" type="checkbox" class="accent-amber-700"></th>
                                            <th class="px-4 py-3">Return No.</th>
                                            <th class="px-4 py-3">Slip No.</th>
                                            <th class="px-4 py-3">Invoice No.</th>
                                            <th class="px-4 py-3">Returned Items</th>
                                            <th class="px-4 py-3">Date</th>
                                            <th class="px-4 py-3 text-right">Total Amount</th>
                                            <th class="px-4 py-3">Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody id="pcv-sudden-returns-tbody" class="divide-y divide-slate-100"></tbody>
                                </table>
                            </div>
                            <div class="mt-4 flex justify-between items-center">
                                <div class="text-xs font-bold text-slate-500">Selected Total: <span id="pcv-sudden-selected-total" class="text-amber-700 font-black">PHP 0.00</span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="pcv-step-3" class="pcv-step hidden p-6">
                    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                        <div class="overflow-x-auto pcv-scroll">
                            <table class="w-full min-w-[1100px] text-left">
                                <thead>
                                    <tr class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-500 border-b border-slate-200">
                                        <th class="px-4 py-4 text-center"><input id="pcv-select-all-invoices" type="checkbox" class="accent-maroon-900"></th>
                                        <th class="px-4 py-4">Purchase No.</th>
                                        <th class="px-4 py-4">Invoice No.</th>
                                        <th class="px-4 py-4">Invoice Date</th>
                                        <th class="px-4 py-4 text-right">Amount Paid</th>
                                        <th class="px-4 py-4 text-right">Discount</th>
                                        <th class="px-4 py-4 text-right">Return Amount</th>
                                        <th class="px-4 py-4 text-right">Total Returns</th>
                                        <th class="px-4 py-4 text-right">SR Deduction</th>
                                        <th class="px-4 py-4 text-right">Addl Disc</th>
                                        <th class="px-4 py-4">RS Details</th>
                                        <th class="px-4 py-4 text-right">Amount Due</th>
                                        <th class="px-4 py-4 text-right">Remaining</th>
                                        <th class="px-4 py-4">Remarks</th>
                                    </tr>
                                    <tr class="bg-white border-b border-slate-100">
                                        <th class="px-4 py-2"></th>
                                        <th class="px-4 py-2"><input data-invoice-col="purchaseNo" class="pcv-search" type="text" placeholder="Search Purchase..."></th>
                                        <th class="px-4 py-2"><input data-invoice-col="invoiceNo" class="pcv-search" type="text" placeholder="Search Invoice..."></th>
                                        <th class="px-4 py-2"><input data-invoice-col="date" class="pcv-search" type="text" placeholder="Search Date..."></th>
                                        <th class="px-4 py-2"><input data-invoice-col="amountPaid" class="pcv-search text-right" type="text" placeholder="Search Paid..."></th>
                                        <th class="px-4 py-2"><input data-invoice-col="discount1" class="pcv-search text-right" type="text" placeholder="Disc..."></th>
                                        <th class="px-4 py-2"><input data-invoice-col="returnAmount" class="pcv-search text-right" type="text" placeholder="Return Amt..."></th>
                                        <th class="px-4 py-2"><input data-invoice-col="totalReturns" class="pcv-search text-right" type="text" placeholder="Returns..."></th>
                                        <th class="px-4 py-2"><input data-invoice-col="suddenReturnDeduction" class="pcv-search text-right" type="text" placeholder="SR Ded..."></th>
                                        <th class="px-4 py-2"><input data-invoice-col="additionalDiscountDeduction" class="pcv-search text-right" type="text" placeholder="Addl Disc..."></th>
                                        <th class="px-4 py-2"><input data-invoice-col="rsDetails" class="pcv-search" type="text" placeholder="Search RS..."></th>
                                        <th class="px-4 py-2"><input data-invoice-col="amountDue" class="pcv-search text-right" type="text" placeholder="Search Due..."></th>
                                        <th class="px-4 py-2"><input data-invoice-col="remaining" class="pcv-search text-right" type="text" placeholder="Remaining..."></th>
                                        <th class="px-4 py-2"><input data-invoice-col="remarks" class="pcv-search" type="text" placeholder="Search Remarks..."></th>
                                    </tr>
                                </thead>
                                <tbody id="pcv-invoice-tbody" class="divide-y divide-slate-100 text-xs"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="mt-4 px-5 py-4 border border-slate-200 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/60">
                        <p class="text-xs font-bold text-slate-500">Showing <span id="pcv-invoice-from" class="text-maroon-700">0</span> to <span id="pcv-invoice-to" class="text-maroon-700">0</span> of <span id="pcv-invoice-total" class="text-maroon-700">0</span> invoices</p>
                        <div id="pcv-invoice-pagination" class="flex items-center gap-1"></div>
                    </div>
                    <div id="pcv-step-2-summary" class="hidden mt-4">
                        <div class="bg-white border border-slate-200 rounded-xl p-5">
                            <h5 class="text-[10px] font-black uppercase tracking-widest text-maroon-800 mb-3">Total Summary</h5>
                            <div id="pcv-summary-content" class="text-xs font-mono"></div>
                        </div>
                    </div>
                </div>

                <div id="pcv-step-4" class="pcv-step hidden p-6">
                    <div class="max-w-3xl space-y-5">
                        <h4 class="text-xs font-black uppercase tracking-widest text-maroon-800">Payment Details</h4>
                        <div>
                            <div class="flex items-center justify-between gap-3">
                                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Payment Method</label>
                                <button id="pcv-add-payment-btn" type="button" onclick="window.pcvAddPaymentDetail()" class="px-4 py-2 rounded-xl bg-maroon-900 text-yellow-300 text-[10px] font-black uppercase tracking-widest hover:bg-maroon-800 transition-colors flex items-center gap-2">
                                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Add Payment
                                </button>
                            </div>
                            <div class="mt-2 grid grid-cols-1 sm:grid-cols-4 gap-3">
                                <?php $__currentLoopData = ['Gcash', 'Cash', 'Cheque', 'Bank Transfer']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $method): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <label class="flex items-center gap-3 border border-slate-200 rounded-xl p-4 cursor-pointer hover:border-maroon-200">
                                        <input name="pcv-payment-method" type="radio" value="<?php echo e($method); ?>" class="accent-maroon-900" <?php echo e($method === 'Gcash' ? 'checked' : ''); ?>>
                                        <span class="text-sm font-black text-slate-700"><?php echo e($method); ?></span>
                                    </label>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                        </div>
                        <div id="pcv-bank-fields" class="hidden grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div><label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Account No</label><input id="pcv-account-no" class="pcv-field mt-1" type="text"></div>
                            <div><label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Bank Name</label><input id="pcv-bank-name" class="pcv-field mt-1" type="text"></div>
                            <div><label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Check No</label><input id="pcv-check-no" class="pcv-field mt-1" type="text"></div>
                            <div><label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Check Date</label><input id="pcv-check-date" class="pcv-field mt-1" type="date"></div>
                            <div><label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Credit Amount</label><input id="pcv-bank-credit" class="pcv-field mt-1" type="number" step="0.01"></div>
                            <div class="md:col-span-2 flex items-center gap-3 pt-2">
                                <button type="button" onclick="window.pcvOpenRecModal()" class="px-5 py-2.5 rounded-xl bg-blue-600 text-white text-[10px] font-black uppercase tracking-widest hover:bg-blue-700 transition-colors flex items-center gap-2 shadow-sm">
                                    <i data-lucide="history" class="w-3.5 h-3.5"></i>
                                    Recommendation
                                </button>
                                <span class="text-[10px] font-semibold text-slate-400">Use previously used bank/account details</span>
                            </div>
                        </div>
                        <div id="pcv-cash-fields" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div id="pcv-gcash-reference-wrap"><label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Reference Number</label><input id="pcv-gcash-reference-no" class="pcv-field mt-1" type="text" placeholder="GCash reference number"></div>
                            <div><label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Date</label><input id="pcv-payment-date" class="pcv-field mt-1" type="date"></div>
                            <div><label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Credit Amount</label><input id="pcv-cash-credit" class="pcv-field mt-1" type="number" step="0.01"></div>
                        </div>
                        <div id="pcv-extra-payments" class="space-y-4"></div>
                        <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 cursor-pointer">
                            <input id="pcv-show-check-summary" type="checkbox" class="h-4 w-4 accent-maroon-900">
                            <span>
                                <span class="block text-[10px] font-black uppercase tracking-widest text-slate-700">Print Check Summary</span>
                                <span class="block text-[10px] font-semibold text-slate-400 mt-0.5">When enabled, filled Check No. entries print in a separate Check No. / Check Amount table.</span>
                            </span>
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 flex items-center justify-between gap-4">
                                <div>
                                    <span class="block text-[10px] font-black uppercase tracking-widest text-amber-700">Invoice Amount</span>
                                    <span class="block text-[9px] font-bold text-amber-600/75 mt-1">Amount after returns and discounts</span>
                                </div>
                                <span id="pcv-invoice-amount" class="text-xl font-black text-amber-700">PHP 0.00</span>
                            </div>
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 flex items-center justify-between gap-4">
                                <span class="text-[10px] font-black uppercase tracking-widest text-emerald-700">Payment Total</span>
                                <span id="pcv-payment-total" class="text-xl font-black text-emerald-700">PHP 0.00</span>
                            </div>
                            <div class="rounded-xl border border-amber-200 bg-amber-50/70 px-5 py-4 flex items-center justify-between gap-4">
                                <div>
                                    <span class="block text-[10px] font-black uppercase tracking-widest text-amber-800">Remaining</span>
                                    <span class="block text-[9px] font-bold text-amber-600/75 mt-1">Invoice Amount minus Payment Total</span>
                                </div>
                                <span id="pcv-payment-remaining" class="text-xl font-black text-amber-800">PHP 0.00</span>
                            </div>
                        </div>
                    </div>
                    <div id="pcv-step-4-review" class="hidden mt-6 border-t border-slate-200 pt-6">
                        <h4 class="text-xs font-black uppercase tracking-widest text-maroon-800 mb-4">Review</h4>
                        <div id="pcv-review-content-step4" class="grid grid-cols-1 lg:grid-cols-3 gap-4"></div>
                    </div>
                </div>

                <div id="pcv-step-5" class="pcv-step hidden p-6">
                    <h4 class="text-xs font-black uppercase tracking-widest text-maroon-800 mb-4">Review</h4>
                    <div id="pcv-review-content" class="grid grid-cols-1 lg:grid-cols-3 gap-4"></div>
                </div>
            </div>

            <div class="flex-shrink-0 px-6 py-4 border-t border-slate-200 bg-white flex items-center justify-between gap-3">
                <button id="pcv-back-btn" type="button" onclick="window.pcvChangeStep(-1)" class="hidden px-6 py-2.5 rounded-xl bg-slate-100 text-slate-600 text-[10px] font-black uppercase tracking-widest hover:bg-slate-200">Back</button>
                <div class="flex items-center gap-2 ml-auto">
                     <input id="pcv-additional-discount" type="number" min="0" max="100" step="0.01" value="0" placeholder="Additional Discount (%)" class="hidden w-36 text-right border border-blue-500 rounded-lg px-3 py-2.5 text-xs font-bold outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-200 bg-blue-50 text-blue-800">
                    <button id="pcv-next-btn" type="button" onclick="window.pcvChangeStep(1)" class="px-8 py-2.5 rounded-xl bg-maroon-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-maroon-800">Next</button>
                    <button id="pcv-print-btn" type="button" onclick="window.pcvPrintPreview('pcv-review-frame')" class="hidden px-6 py-2.5 rounded-xl bg-slate-700 text-white text-[10px] font-black uppercase tracking-widest hover:bg-slate-600 flex items-center gap-2">
                        <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                        Print
                    </button>
                    <button id="pcv-final-btn" type="button" onclick="window.pcvFinalizeVoucher()" class="hidden px-8 py-2.5 rounded-xl bg-emerald-600 text-white text-[10px] font-black uppercase tracking-widest hover:bg-emerald-700">Proceed</button>
                </div>
            </div>
        </div>
    </div>

    <div id="pcv-rec-modal" class="fixed inset-0 z-[800] hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-950/65 backdrop-blur-sm" onclick="window.pcvCloseRecModal()"></div>
        <div class="relative bg-white w-full max-w-3xl max-h-[80vh] rounded-2xl shadow-2xl flex flex-col overflow-hidden">
            <div class="bg-blue-600 text-white px-6 py-4 flex items-center justify-between flex-shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-white/10 flex items-center justify-center"><i data-lucide="history" class="w-5 h-5"></i></div>
                    <div><h3 class="text-sm font-black uppercase tracking-widest">Payment Recommendations</h3><p class="text-[11px] font-semibold text-white/60 mt-0.5">Previously used bank accounts for this supplier</p></div>
                </div>
                <button type="button" onclick="window.pcvCloseRecModal()" class="w-9 h-9 rounded-lg hover:bg-white/10 flex items-center justify-center"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="flex-shrink-0 px-6 py-3 border-b border-slate-200 bg-white">
                <input type="text" id="pcv-rec-search" placeholder="Search bank name, account no, or payment method..." class="pcv-search">
            </div>
            <div class="flex-1 overflow-y-auto pcv-scroll p-6">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-[10px] font-black uppercase tracking-widest text-slate-500 border-b border-slate-200">
                            <th class="pb-3 pr-4">Bank Name</th>
                            <th class="pb-3 pr-4">Account No.</th>
                            <th class="pb-3 pr-4">Method</th>
                            <th class="pb-3 pr-4">Last Used</th>
                            <th class="pb-3 pr-4">#</th>
                            <th class="pb-3">Action</th>
                        </tr>
                    </thead>
                    <tbody id="pcv-rec-tbody"></tbody>
                </table>
                <div id="pcv-rec-empty" class="hidden text-center py-12">
                    <div class="text-slate-300 text-4xl mb-3"><i data-lucide="inbox" class="w-12 h-12 mx-auto"></i></div>
                    <p class="text-sm font-bold text-slate-400">No previous bank account recommendations for this supplier.</p>
                </div>
                <div id="pcv-rec-loading" class="text-center py-12 text-sm font-bold text-slate-400">Loading recommendations...</div>
            </div>
            <div class="flex-shrink-0 px-6 py-4 border-t border-slate-200 bg-white flex items-center justify-end">
                <button type="button" onclick="window.pcvCloseRecModal()" class="px-6 py-2.5 rounded-xl bg-slate-100 text-slate-600 text-[10px] font-black uppercase tracking-widest hover:bg-slate-200">Close</button>
            </div>
        </div>
    </div>

    <div id="pcv-confirm-modal" class="fixed inset-0 z-[700] hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"></div>
        <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl overflow-hidden">
            <div class="bg-maroon-900 p-5 text-white">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-xl bg-white/10 flex items-center justify-center">
                        <i data-lucide="shield-check" class="w-5 h-5 text-goldlining-400"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-widest">Confirm Voucher</h3>
                        <p class="text-[10px] font-semibold text-white/60 mt-1">Review before posting to accounting.</p>
                    </div>
                </div>
            </div>
            <div class="p-6 space-y-4">
                <p id="pcv-confirm-message" class="text-sm text-slate-600 font-semibold leading-relaxed">Are you sure you want to proceed with this voucher?</p>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="window.pcvCloseConfirmModal()" class="px-5 py-2.5 rounded-xl bg-slate-100 text-slate-600 text-[10px] font-black uppercase tracking-widest hover:bg-slate-200">Cancel</button>
                    <button id="pcv-confirm-submit-btn" type="button" onclick="window.pcvSubmitVoucher()" class="px-5 py-2.5 rounded-xl bg-maroon-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-maroon-800">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <div id="pcv-success-modal" class="fixed inset-0 z-[700] hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"></div>
        <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl overflow-hidden">
            <div class="p-8 text-center">
                <div class="mx-auto w-16 h-16 rounded-2xl bg-emerald-50 text-emerald-500 flex items-center justify-center">
                    <i data-lucide="check-circle-2" class="w-9 h-9"></i>
                </div>
                <h3 class="mt-5 text-lg font-black text-slate-800 uppercase tracking-widest">Voucher Posted</h3>
                <p id="pcv-success-message" class="mt-2 text-sm font-semibold text-slate-500">The payable cheque voucher was saved successfully.</p>
                <button type="button" onclick="window.pcvCloseSuccessModal()" class="mt-6 px-8 py-3 rounded-xl bg-maroon-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-maroon-800">Done</button>
            </div>
        </div>
    </div>

    <div id="pcv-return-modal" class="fixed inset-0 z-[700] hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" onclick="window.pcvCloseReturnModal()"></div>
        <div class="relative w-full max-w-5xl max-h-[85vh] bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col">
            <div class="bg-maroon-900 text-white px-6 py-4 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-white/10 flex items-center justify-center"><i data-lucide="alert-triangle" class="w-5 h-5 text-goldlining-400"></i></div>
                    <div><h3 class="text-sm font-black uppercase tracking-widest">Return Info</h3><p id="pcv-return-supplier" class="text-[11px] font-semibold text-white/60 mt-0.5">Supplier</p></div>
                </div>
                <button type="button" onclick="window.pcvCloseReturnModal()" class="w-9 h-9 rounded-lg hover:bg-white/10 flex items-center justify-center"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="flex-1 min-h-0 overflow-hidden flex flex-col lg:flex-row">
                <div class="lg:w-[30%] shrink-0 p-5 bg-slate-50 border-b lg:border-b-0 lg:border-r border-slate-200 overflow-y-auto pcv-scroll">
                    <div id="pcv-return-list" class="space-y-3"></div>
                </div>
                <div class="flex-1 p-5 overflow-y-auto pcv-scroll">
                    <div id="pcv-return-detail-placeholder" class="h-full flex items-center justify-center text-sm font-bold text-slate-400">Select a return from the left panel</div>
                    <div id="pcv-return-detail" class="hidden">
                        <div class="mb-4 grid grid-cols-2 gap-4 text-xs">
                            <div><span class="font-black text-slate-400 uppercase text-[10px] tracking-widest">Return No.</span><p id="pcv-ret-no" class="font-black text-slate-800 mt-1">---</p></div>
                            <div><span class="font-black text-slate-400 uppercase text-[10px] tracking-widest">Invoice</span><p id="pcv-ret-invoice" class="font-bold text-slate-700 mt-1">---</p></div>
                            <div><span class="font-black text-slate-400 uppercase text-[10px] tracking-widest">Total Amount</span><p id="pcv-ret-total" class="font-black text-slate-800 mt-1">---</p></div>
                            <div><span class="font-black text-slate-400 uppercase text-[10px] tracking-widest">Remarks</span><p id="pcv-ret-remarks" class="font-bold text-slate-600 mt-1">---</p></div>
                        </div>
                        <h5 class="text-[10px] font-black uppercase tracking-widest text-maroon-800 mb-3">Returned Items</h5>
                        <div class="overflow-x-auto border border-slate-200 rounded-xl">
                            <table class="w-full min-w-[500px] text-left text-xs">
                                <thead>
                                    <tr class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-500">
                                        <th class="px-4 py-3">Product Code</th>
                                        <th class="px-4 py-3">Application</th>
                                        <th class="px-4 py-3">Description</th>
                                        <th class="px-4 py-3 text-right">QTY</th>
                                        <th class="px-4 py-3 text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody id="pcv-return-items-tbody" class="divide-y divide-slate-100"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
<?php $__env->stopSection(); ?>

<div id="pcv-view-modal" class="fixed inset-0 z-[700] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" onclick="window.pcvCloseViewModal()"></div>
    <div class="relative w-full max-w-[1450px] h-[92vh] bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col">
        <div class="bg-maroon-900 text-white px-6 py-4 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-white/10 flex items-center justify-center"><i data-lucide="file-text" class="w-5 h-5 text-goldlining-400"></i></div>
                <div><h3 class="text-sm font-black uppercase tracking-widest">PCV Details</h3><p id="pcv-view-voucher-title" class="text-[11px] font-semibold text-white/60 mt-0.5">Voucher</p></div>
            </div>
            <div class="flex items-center gap-2">
                <button id="pcv-view-print-btn" type="button" onclick="window.pcvViewPrintCurrent()" class="px-3 py-2 rounded-lg bg-white/10 hover:bg-white/20 text-[9px] font-black uppercase tracking-widest flex items-center gap-1.5"><i data-lucide="printer" class="w-3.5 h-3.5"></i> Print</button>
                <button id="pcv-view-edit-btn" type="button" onclick="window.pcvViewEditCurrent()" class="px-3 py-2 rounded-lg bg-yellow-400 text-maroon-900 hover:bg-yellow-300 text-[9px] font-black uppercase tracking-widest flex items-center gap-1.5"><i data-lucide="edit-3" class="w-3.5 h-3.5"></i> Edit</button>
                <button id="pcv-view-delete-btn" type="button" onclick="window.pcvViewDeleteCurrent()" class="px-3 py-2 rounded-lg bg-rose-600 hover:bg-rose-500 text-white text-[9px] font-black uppercase tracking-widest flex items-center gap-1.5"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete</button>
                <button type="button" onclick="window.pcvCloseViewModal()" class="w-9 h-9 rounded-lg hover:bg-white/10 flex items-center justify-center"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
        </div>
        <div class="shrink-0 border-b border-slate-200 bg-slate-50/70 p-4">
            <div id="pcv-view-summary" class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3"></div>
        </div>
        <div class="shrink-0 border-b border-slate-200 bg-white px-5 py-3 flex flex-wrap gap-2">
            <button id="pcv-view-tab-invoice" type="button" onclick="window.pcvSwitchViewTab('invoice')" class="px-5 py-2.5 rounded-xl bg-yellow-400 text-maroon-900 text-[10px] font-black uppercase tracking-widest">Invoice</button>
            <button id="pcv-view-tab-ledger" type="button" onclick="window.pcvSwitchViewTab('ledger')" class="px-5 py-2.5 rounded-xl bg-maroon-900 text-yellow-300 text-[10px] font-black uppercase tracking-widest">Ledger</button>
        </div>
        <div class="flex-1 overflow-y-auto pcv-scroll p-5 space-y-5">
            <div id="pcv-view-panel-invoice">
                <div id="pcv-view-invoice-list">
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="w-full min-w-[1050px] text-left text-xs">
                            <thead>
                                <tr class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-500 border-b border-slate-200">
                                    <th class="px-4 py-3">Invoice No</th><th class="px-4 py-3">Invoice Date</th><th class="px-4 py-3 text-right">Returns</th><th class="px-4 py-3 text-right">Return Amount</th><th class="px-4 py-3 text-right">Amount Due</th><th class="px-4 py-3 text-right">Amount Paid</th>
                                </tr>
                                <tr id="pcv-view-invoice-filter-row" class="bg-white border-b border-slate-100">
                                    <th class="p-1"><input data-col="0" oninput="window.pcvFilterViewTable('pcv-view-invoice-tbody','pcv-view-invoice-filter-row')" class="pcv-search" placeholder="Search invoice..."></th>
                                    <th class="p-1"><input data-col="1" oninput="window.pcvFilterViewTable('pcv-view-invoice-tbody','pcv-view-invoice-filter-row')" class="pcv-search" placeholder="Search date..."></th>
                                    <th class="p-1"><input data-col="2" oninput="window.pcvFilterViewTable('pcv-view-invoice-tbody','pcv-view-invoice-filter-row')" class="pcv-search text-right" placeholder="Search returns..."></th>
                                    <th class="p-1"><input data-col="3" oninput="window.pcvFilterViewTable('pcv-view-invoice-tbody','pcv-view-invoice-filter-row')" class="pcv-search text-right" placeholder="Search return amount..."></th>
                                    <th class="p-1"><input data-col="4" oninput="window.pcvFilterViewTable('pcv-view-invoice-tbody','pcv-view-invoice-filter-row')" class="pcv-search text-right" placeholder="Search due..."></th>
                                    <th class="p-1"><input data-col="5" oninput="window.pcvFilterViewTable('pcv-view-invoice-tbody','pcv-view-invoice-filter-row')" class="pcv-search text-right" placeholder="Search paid..."></th>
                                </tr>
                            </thead>
                            <tbody id="pcv-view-invoice-tbody" class="divide-y divide-slate-100"></tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-[10px] font-semibold text-slate-400">Click an invoice row to open its item list.</p>
                </div>
                <div id="pcv-view-item-list" class="hidden">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <button type="button" onclick="window.pcvBackToInvoiceList()" class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-[10px] font-black uppercase tracking-widest hover:bg-slate-200 flex items-center gap-2"><i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Back to Invoices</button>
                        <h4 id="pcv-view-item-title" class="text-[10px] font-black uppercase tracking-widest text-maroon-800">Item List</h4>
                    </div>
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="w-full min-w-[1350px] text-left text-xs">
                            <thead>
                                <tr class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-500 border-b border-slate-200">
                                    <th class="px-3 py-3">Product Code</th><th class="px-3 py-3">Part No</th><th class="px-3 py-3 text-right">Qty</th><th class="px-3 py-3">Unit</th><th class="px-3 py-3 text-right">Cost</th><th class="px-3 py-3 text-right">Return Qty</th><th class="px-3 py-3 text-right">Return Amount</th><th class="px-3 py-3 text-right">Invoice Amount</th><th class="px-3 py-3 text-right">Total Amount</th>
                                </tr>
                                <tr id="pcv-view-item-filter-row" class="bg-white border-b border-slate-100">
                                    <th class="p-1"><input data-col="0" oninput="window.pcvFilterViewTable('pcv-view-item-tbody','pcv-view-item-filter-row')" class="pcv-search" placeholder="Search..."></th><th class="p-1"><input data-col="1" oninput="window.pcvFilterViewTable('pcv-view-item-tbody','pcv-view-item-filter-row')" class="pcv-search" placeholder="Search..."></th><th class="p-1"><input data-col="2" oninput="window.pcvFilterViewTable('pcv-view-item-tbody','pcv-view-item-filter-row')" class="pcv-search" placeholder="Search..."></th><th class="p-1"><input data-col="3" oninput="window.pcvFilterViewTable('pcv-view-item-tbody','pcv-view-item-filter-row')" class="pcv-search" placeholder="Search..."></th><th class="p-1"><input data-col="4" oninput="window.pcvFilterViewTable('pcv-view-item-tbody','pcv-view-item-filter-row')" class="pcv-search" placeholder="Search..."></th><th class="p-1"><input data-col="5" oninput="window.pcvFilterViewTable('pcv-view-item-tbody','pcv-view-item-filter-row')" class="pcv-search" placeholder="Search..."></th><th class="p-1"><input data-col="6" oninput="window.pcvFilterViewTable('pcv-view-item-tbody','pcv-view-item-filter-row')" class="pcv-search" placeholder="Search..."></th><th class="p-1"><input data-col="7" oninput="window.pcvFilterViewTable('pcv-view-item-tbody','pcv-view-item-filter-row')" class="pcv-search" placeholder="Search..."></th><th class="p-1"><input data-col="8" oninput="window.pcvFilterViewTable('pcv-view-item-tbody','pcv-view-item-filter-row')" class="pcv-search" placeholder="Search..."></th>
                                </tr>
                            </thead>
                            <tbody id="pcv-view-item-tbody" class="divide-y divide-slate-100"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div id="pcv-view-panel-ledger" class="hidden">
                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full min-w-[1450px] text-left text-xs">
                        <thead>
                            <tr class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-500 border-b border-slate-200">
                                <th class="px-3 py-3">Invoice No</th><th class="px-3 py-3">Product Code</th><th class="px-3 py-3">Part Number</th><th class="px-3 py-3">Transaction</th><th class="px-3 py-3">Transaction No</th><th class="px-3 py-3 text-right">Debit</th><th class="px-3 py-3 text-right">Credit</th><th class="px-3 py-3">Remarks</th><th class="px-3 py-3">Date</th>
                            </tr>
                            <tr id="pcv-view-ledger-filter-row" class="bg-white border-b border-slate-100">
                                <th class="p-1"><input data-col="0" oninput="window.pcvFilterViewTable('pcv-view-ledger-tbody','pcv-view-ledger-filter-row')" class="pcv-search" placeholder="Search..."></th><th class="p-1"><input data-col="1" oninput="window.pcvFilterViewTable('pcv-view-ledger-tbody','pcv-view-ledger-filter-row')" class="pcv-search" placeholder="Search..."></th><th class="p-1"><input data-col="2" oninput="window.pcvFilterViewTable('pcv-view-ledger-tbody','pcv-view-ledger-filter-row')" class="pcv-search" placeholder="Search..."></th><th class="p-1"><input data-col="3" oninput="window.pcvFilterViewTable('pcv-view-ledger-tbody','pcv-view-ledger-filter-row')" class="pcv-search" placeholder="Search..."></th><th class="p-1"><input data-col="4" oninput="window.pcvFilterViewTable('pcv-view-ledger-tbody','pcv-view-ledger-filter-row')" class="pcv-search" placeholder="Search..."></th><th class="p-1"><input data-col="5" oninput="window.pcvFilterViewTable('pcv-view-ledger-tbody','pcv-view-ledger-filter-row')" class="pcv-search" placeholder="Search..."></th><th class="p-1"><input data-col="6" oninput="window.pcvFilterViewTable('pcv-view-ledger-tbody','pcv-view-ledger-filter-row')" class="pcv-search" placeholder="Search..."></th><th class="p-1"><input data-col="7" oninput="window.pcvFilterViewTable('pcv-view-ledger-tbody','pcv-view-ledger-filter-row')" class="pcv-search" placeholder="Search..."></th><th class="p-1"><input data-col="8" oninput="window.pcvFilterViewTable('pcv-view-ledger-tbody','pcv-view-ledger-filter-row')" class="pcv-search" placeholder="Search..."></th>
                            </tr>
                        </thead>
                        <tbody id="pcv-view-ledger-tbody" class="divide-y divide-slate-100"></tbody>
                    </table>
                </div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="flex items-center justify-between gap-3 mb-4"><h4 class="text-[10px] font-black uppercase tracking-widest text-maroon-800">Payment Details</h4><span id="pcv-view-payment-total" class="text-sm font-black text-emerald-700">PHP 0.00</span></div>
                <div id="pcv-view-payment-details" class="grid grid-cols-1 lg:grid-cols-2 gap-3"></div>
            </div>
        </div>
    </div>
</div>

<div id="pcv-print-modal" class="fixed inset-0 z-[700] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" onclick="window.pcvClosePrintModal()"></div>
    <div class="relative w-full max-w-3xl max-h-[85vh] bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col">
        <div class="bg-maroon-900 text-white px-6 py-4 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-white/10 flex items-center justify-center"><i data-lucide="printer" class="w-5 h-5 text-goldlining-400"></i></div>
                <div><h3 class="text-sm font-black uppercase tracking-widest">Select Voucher to Print</h3><p class="text-[11px] font-semibold text-white/60 mt-0.5">Click a row to generate print layout</p></div>
            </div>
            <button type="button" onclick="window.pcvClosePrintModal()" class="w-9 h-9 rounded-lg hover:bg-white/10 flex items-center justify-center"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <div class="flex-1 overflow-y-auto pcv-scroll p-6 flex flex-col">
            <!-- TASK 5: Filter Dropdown -->
            <div class="mb-4 flex items-center gap-3">
                <label for="pcv-print-filter" class="text-xs font-black uppercase tracking-widest text-slate-600">Filter:</label>
                <select id="pcv-print-filter" class="px-3 py-2 text-xs font-bold border border-slate-300 rounded-lg bg-white text-slate-800 focus:outline-none focus:ring-2 focus:ring-maroon-500">
                    <option value="all">All Vouchers</option>
                    <option value="with">With Sudden Return</option>
                    <option value="without">Without Sudden Return</option>
                </select>
            </div>
            <div class="overflow-x-auto border border-slate-200 rounded-xl flex-1">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-500 border-b border-slate-200">
                            <th class="px-4 py-3">Voucher No.</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3 text-right">Total Invoice</th>
                            <th class="px-4 py-3">Date</th>
                        </tr>
                        <tr class="bg-white border-b border-slate-100">
                            <th class="px-4 py-2"><input data-print-col="voucher_no" class="pcv-search" type="text" placeholder="Search Voucher..."></th>
                            <th class="px-4 py-2"><input data-print-col="total_amount" class="pcv-search text-right" type="text" placeholder="Search Amount..."></th>
                            <th class="px-4 py-2"><input data-print-col="total_invoices" class="pcv-search text-right" type="text" placeholder="Search Invoices..."></th>
                            <th class="px-4 py-2"><input data-print-col="voucher_date" class="pcv-search" type="text" placeholder="Search Date..."></th>
                        </tr>
                    </thead>
                    <tbody id="pcv-print-table-tbody" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>
            <div class="mt-4 px-5 py-4 border border-slate-200 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/60 shrink-0">
                <p class="text-xs font-bold text-slate-500">Showing <span id="pcv-print-from" class="text-maroon-700">0</span> to <span id="pcv-print-to" class="text-maroon-700">0</span> of <span id="pcv-print-total" class="text-maroon-700">0</span> vouchers</p>
                <div id="pcv-print-pagination" class="flex items-center gap-1"></div>
            </div>
        </div>
    </div>
</div>

<!-- Delete List Modal -->
<div id="pcv-delete-modal" class="fixed inset-0 z-[700] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" onclick="window.pcvCloseDeleteModal()"></div>
    <div class="relative w-full max-w-3xl max-h-[85vh] bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col">
        <div class="bg-rose-600 text-white px-6 py-4 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-white/10 flex items-center justify-center"><i data-lucide="trash-2" class="w-5 h-5 text-white"></i></div>
                <div><h3 class="text-sm font-black uppercase tracking-widest">Select Voucher to Delete</h3><p class="text-[11px] font-semibold text-white/60 mt-0.5">Click a row to delete the voucher</p></div>
            </div>
            <button type="button" onclick="window.pcvCloseDeleteModal()" class="w-9 h-9 rounded-lg hover:bg-white/10 flex items-center justify-center"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <div class="flex-1 overflow-y-auto pcv-scroll p-6 flex flex-col">
            <div class="overflow-x-auto border border-slate-200 rounded-xl flex-1">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-500 border-b border-slate-200">
                            <th class="px-4 py-3">Voucher No.</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3 text-right">Total Invoice</th>
                            <th class="px-4 py-3">Date</th>
                        </tr>
                    </thead>
                    <tbody id="pcv-delete-table-tbody" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>
            <div class="mt-4 px-5 py-4 border border-slate-200 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/60 shrink-0">
                <p class="text-xs font-bold text-slate-500">Showing <span id="pcv-delete-from" class="text-rose-700">0</span> to <span id="pcv-delete-to" class="text-rose-700">0</span> of <span id="pcv-delete-total" class="text-rose-700">0</span> vouchers</p>
                <div id="pcv-delete-pagination" class="flex items-center gap-1"></div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="pcv-delete-confirm-modal" class="fixed inset-0 z-[800] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"></div>
    <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl overflow-hidden">
        <div class="bg-rose-600 p-5 text-white">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-white/10 flex items-center justify-center"><i data-lucide="alert-triangle" class="w-5 h-5 text-white"></i></div>
                <div>
                    <h3 class="text-sm font-black uppercase tracking-widest">Confirm Delete</h3>
                    <p class="text-[10px] font-semibold text-white/60 mt-1">This action cannot be undone.</p>
                </div>
            </div>
        </div>
        <div class="p-6 space-y-4">
            <p id="pcv-delete-confirm-message" class="text-sm text-slate-600 font-semibold leading-relaxed">Are you sure you want to delete this voucher? This action will remove this voucher from the PCV history.</p>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="window.pcvCloseDeleteConfirmModal()" class="px-5 py-2.5 rounded-xl bg-slate-100 text-slate-600 text-[10px] font-black uppercase tracking-widest hover:bg-slate-200">Cancel</button>
                <button id="pcv-delete-confirm-submit-btn" type="button" onclick="window.pcvSubmitDeleteVoucher()" class="px-5 py-2.5 rounded-xl bg-rose-600 text-white text-[10px] font-black uppercase tracking-widest hover:bg-rose-700">Confirm Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Success Modal -->
<div id="pcv-delete-success-modal" class="fixed inset-0 z-[800] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"></div>
    <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl overflow-hidden">
        <div class="p-8 text-center">
            <div class="mx-auto w-16 h-16 rounded-2xl bg-emerald-50 text-emerald-500 flex items-center justify-center"><i data-lucide="check-circle-2" class="w-9 h-9"></i></div>
            <h3 class="mt-5 text-lg font-black text-slate-800 uppercase tracking-widest">Voucher Deleted</h3>
            <p id="pcv-delete-success-message" class="mt-2 text-sm font-semibold text-slate-500">PCV deleted successfully.</p>
            <button type="button" onclick="window.pcvCloseDeleteSuccessModal()" class="mt-6 px-8 py-3 rounded-xl bg-rose-600 text-white text-[10px] font-black uppercase tracking-widest hover:bg-rose-700">Done</button>
        </div>
    </div>
</div>

<div id="pcv-print-layout" class="hidden">
    <div id="pcv-print-content" style="font-family: 'Courier New', monospace; font-size: 13px; width: 800px; margin: 0 auto; padding: 30px 20px;">
        <div style="text-align: center; margin-bottom: 20px;">
            <div style="font-size: 20px; font-weight: bold; font-family: 'Arial Black', Arial, sans-serif; letter-spacing: 1px;">W68 AUTO PARTS &amp; SERVICE CENTER</div>
            <div style="font-size: 15px; font-weight: bold;">48 TIMOTHY ST. MULTINATIONAL VILLAGE PARAÑAQUE CITY</div>
            <div style="font-size: 15px; font-weight: bold;">Tel. Nos. 8553-9092 / 8829-0480 Mobile No. 0917-3239-605 Mobile/Viber. 0949-8818-468</div>
            <div style="font-size: 14px; font-weight: bold; margin-top: 18px; letter-spacing: 3px;">CHECK VOUCHER</div>
        </div>

        <table style="width: 100%; font-size: 13px; margin-bottom: 15px;">
            <tr>
                <td style="width: 50%;"><span style="font-weight: bold;">SUPPLIER:</span> <span id="print-supplier"></span></td>
                <td style="width: 50%; text-align: right;"><span style="font-weight: bold;">VOUCHER NO.:</span> <span id="print-voucher-no"></span></td>
            </tr>
            <tr>
                <td><span style="font-weight: bold;">ADDRESS:</span> <span id="print-address"></span></td>
                <td style="text-align: right;"><span style="font-weight: bold;">DATE:</span> <span id="print-date"></span></td>
            </tr>
        </table>

        <table style="width: 100%; font-size: 13px; border-collapse: collapse; margin-bottom: 8px;">
            <thead>
                <tr style="border-bottom: 1px solid #000; border-top: 1px solid #000;">
                    <th style="padding: 5px 3px; text-align: left; font-weight: bold; border-left: 1px solid #000; border-right: 1px solid #000;">INVOICE NO.</th>
                    <th style="padding: 5px 3px; text-align: right; font-weight: bold; border-right: 1px solid #000;">AMOUNT</th>
                    <th style="padding: 5px 3px; text-align: center; font-weight: bold; border-right: 1px solid #000;">Slip No.</th>
                    <th style="padding: 5px 3px; text-align: right; font-weight: bold; border-right: 1px solid #000;">RETURN AMOUNT</th>
                    <th style="padding: 5px 3px; text-align: right; font-weight: bold; border-right: 1px solid #000;">RETURNED ITEMS</th>
                    <th style="padding: 5px 3px; text-align: left; font-weight: bold; border-right: 1px solid #000;">RS DETAILS</th>
                    <th style="padding: 5px 3px; text-align: center; font-weight: bold; border-right: 1px solid #000;">REMARKS</th>
                    <th style="padding: 5px 3px; text-align: right; font-weight: bold; border-right: 1px solid #000;">TOTAL AMOUNT</th>
                </tr>
            </thead>
            <tbody id="print-invoice-rows"></tbody>
        </table>

        <div style="border-top: 1px solid #000; margin-bottom: 10px;"></div>

        <div style="text-align: right; font-size: 13px; font-weight: bold; margin-bottom: 5px;">
            INVOICE PAID TOTAL: <span id="print-grand-total"></span>
        </div>

        <div style="text-align: right; font-size: 13px; font-weight: bold; margin-bottom: 5px; display: none;" id="print-total-amount-section">
            TOTAL AMOUNT: <span id="print-total-amount"></span>
        </div>

        <table style="width: 100%; font-size: 13px; border-collapse: collapse; margin-bottom: 12px;">
            <thead>
                <tr style="border-bottom: 1px solid #000; border-top: 1px solid #000;">
                    <th style="padding: 4px 3px; font-weight: bold; border-left: 1px solid #000; border-right: 1px solid #000;">ACCOUNT NO.</th>
                    <th style="padding: 4px 3px; font-weight: bold; border-right: 1px solid #000;">BANK NAME</th>
                    <th style="padding: 4px 3px; font-weight: bold; border-right: 1px solid #000;">CHECK NO.</th>
                    <th style="padding: 4px 3px; font-weight: bold; border-right: 1px solid #000;">CHECK DATE</th>
                    <th style="padding: 4px 3px; font-weight: bold; border-right: 1px solid #000;">CHECK AMOUNT</th>
                </tr>
            </thead>
            <tbody id="print-payment-rows"></tbody>
        </table>
        <div id="print-check-summary-section" style="margin-bottom: 12px; font-size: 13px; display: none;">
            <div style="font-weight: bold; text-align: center; padding: 6px; border: 1px solid #000; border-bottom: 0;">CHECK SUMMARY</div>
            <table style="width: 100%; font-size: 12px; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="padding: 5px 6px; text-align: left; border: 1px solid #000;">CHECK NO.</th>
                        <th style="padding: 5px 6px; text-align: right; border: 1px solid #000;">CHECK AMOUNT</th>
                    </tr>
                </thead>
                <tbody id="print-check-summary-rows"></tbody>
            </table>
        </div>

        <div style="text-align: right; font-size: 13px; font-weight: bold; margin-bottom: 5px; color: #2563eb; display: none;" id="print-addl-disc-section">
            ADDITIONAL DISCOUNT: - <span id="print-addl-disc-amount"></span>
        </div>

        <div id="print-total-discount-section" style="text-align: right; font-size: 13px; font-weight: bold; margin-bottom: 8px; display: none;">
            TOTAL DISCOUNT: <span id="print-discount-label"></span>
        </div>

        <div id="print-sudden-returns-section" style="margin-bottom: 12px; font-size: 13px; display: none;">
            <div style="font-weight: bold; margin-bottom: 6px;">SUDDEN RETURNS</div>
            <table style="width: 100%; font-size: 12px; border-collapse: collapse;">
                <thead>
                    <tr style="border-top: 1px solid #000; border-bottom: 1px solid #000;">
                        <th style="padding: 4px 6px; text-align: left; border-left: 1px solid #000; border-right: 1px solid #000;">RETURN NO.</th>
                        <th style="padding: 4px 6px; text-align: left; border-right: 1px solid #000;">PO NO.</th>
                        <th style="padding: 4px 6px; text-align: left; border-right: 1px solid #000;">REMARKS</th>
                        <th style="padding: 4px 6px; text-align: right; border-right: 1px solid #000;">RETURN AMOUNT</th>
                    </tr>
                </thead>
                <tbody id="print-sudden-return-rows"></tbody>
            </table>
        </div>

        <div style="text-align: right; font-size: 13px; font-weight: bold; margin-bottom: 5px; display: none;" id="print-suddenly-return-line">
            SUDDENLY RETURN: - <span id="print-suddenly-return-amount"></span>
        </div>

        <div style="border-top: 1px solid #000; margin-bottom: 8px;"></div>

        <div style="text-align: right; font-size: 13px; font-weight: bold; margin-bottom: 30px;">
            NET AMOUNT: <span id="print-total"></span>
        </div>

        <div style="border-top: 3px solid #000; width: 100%; margin: 50px 0 15px 0;"></div>

        <div style="display: flex; justify-content: space-between; font-size: 13px; margin-top: 60px;">
            <div style="text-align: center; width: 22%;">
                <div style="border-top: 1px solid #000; padding-top: 5px;">PREPARED BY</div>
            </div>
            <div style="text-align: center; width: 22%;">
                <div style="border-top: 1px solid #000; padding-top: 5px;">CHECKED BY</div>
            </div>
            <div style="text-align: center; width: 22%;">
                <div style="border-top: 1px solid #000; padding-top: 5px;">APPROVED BY</div>
            </div>
            <div style="text-align: center; width: 22%;">
                <div style="border-top: 1px solid #000; padding-top: 5px;">RECEIVED BY</div>
            </div>
        </div>
    </div>
</div>

<div id="pcv-edit-modal" class="fixed inset-0 z-[700] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-950/65 backdrop-blur-sm" onclick="window.pcvCloseEditModal()"></div>
    <div class="relative bg-white w-full max-w-5xl max-h-[90vh] rounded-2xl shadow-2xl flex flex-col overflow-hidden">
        <div class="bg-amber-600 text-white px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-white/10 flex items-center justify-center"><i data-lucide="edit-3" class="w-5 h-5"></i></div>
                <div><h3 class="text-sm font-black uppercase tracking-widest">Edit Voucher</h3><p id="pcv-edit-modal-title" class="text-[11px] font-semibold text-white/60 mt-0.5">Voucher</p></div>
            </div>
            <button type="button" onclick="window.pcvCloseEditModal()" class="w-9 h-9 rounded-lg hover:bg-white/10 flex items-center justify-center"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <div class="flex-1 min-h-0 overflow-y-auto pcv-scroll p-6">
            <div id="pcv-edit-content" class="space-y-6"></div>
        </div>
        <div class="flex-shrink-0 px-6 py-4 border-t border-slate-200 bg-white flex items-center justify-end gap-3">
            <button type="button" onclick="window.pcvCloseEditModal()" class="px-6 py-2.5 rounded-xl bg-slate-100 text-slate-600 text-[10px] font-black uppercase tracking-widest hover:bg-slate-200">Cancel</button>
            <button id="pcv-save-edit-btn" type="button" onclick="window.pcvSaveEditedVoucher()" class="px-8 py-2.5 rounded-xl bg-amber-600 text-white text-[10px] font-black uppercase tracking-widest hover:bg-amber-700">Save Changes</button>
        </div>
    </div>
</div>

<div id="pcv-edit-confirm-modal" class="fixed inset-0 z-[800] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"></div>
    <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl overflow-hidden">
        <div class="bg-amber-600 p-5 text-white">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-white/10 flex items-center justify-center">
                    <i data-lucide="alert-circle" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-sm font-black uppercase tracking-widest">Confirm Edit</h3>
                    <p class="text-[10px] font-semibold text-white/60 mt-1">Please review changes before saving.</p>
                </div>
            </div>
        </div>
        <div class="p-6 space-y-4">
            <p id="pcv-edit-confirm-message" class="text-sm text-slate-600 font-semibold leading-relaxed">Are you sure you want to save these changes to the voucher?</p>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="window.pcvCloseEditConfirmModal()" class="px-5 py-2.5 rounded-xl bg-slate-100 text-slate-600 text-[10px] font-black uppercase tracking-widest hover:bg-slate-200">Cancel</button>
                <button id="pcv-edit-confirm-submit-btn" type="button" onclick="window.pcvSubmitEditedVoucher()" class="px-5 py-2.5 rounded-xl bg-amber-600 text-white text-[10px] font-black uppercase tracking-widest hover:bg-amber-700">Confirm Save</button>
            </div>
        </div>
    </div>
</div>

<div id="pcv-edit-success-modal" class="fixed inset-0 z-[800] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"></div>
    <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl overflow-hidden">
        <div class="p-8 text-center">
            <div class="mx-auto w-16 h-16 rounded-2xl bg-emerald-50 text-emerald-500 flex items-center justify-center">
                <i data-lucide="check-circle-2" class="w-9 h-9"></i>
            </div>
            <h3 class="mt-5 text-lg font-black text-slate-800 uppercase tracking-widest">Changes Saved</h3>
            <p id="pcv-edit-success-message" class="mt-2 text-sm font-semibold text-slate-500">The voucher has been updated successfully.</p>
            <button type="button" onclick="window.pcvCloseEditSuccessModal()" class="mt-6 px-8 py-2.5 rounded-xl bg-emerald-600 text-white text-[10px] font-black uppercase tracking-widest hover:bg-emerald-700">Close</button>
        </div>
    </div>
</div>

<iframe id="pcv-print-frame" class="hidden"></iframe>

<?php $__env->startPush('scripts'); ?>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script src="<?php echo e(asset('js/Payable-Cheque-Voucher-Enhancement.js')); ?>?v=<?php echo e(time()); ?>"></script>
    <script>
        // IMMEDIATE FIX: Force remove hidden class from any iframe when page loads
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                const allIframes = document.querySelectorAll('iframe[id*="review"]');
                allIframes.forEach(iframe => {
                    iframe.classList.remove('hidden');
                    iframe.style.display = 'block';
                    iframe.style.visibility = 'visible';
                });
            }, 500);
        });
    </script>
    <script>
        window.pcvRoutes = {
            data: "<?php echo e(route('special.payable-cheque-voucher.data')); ?>",
            supplier: "<?php echo e(url('/special/accounting/payable-cheque-voucher/supplier')); ?>",
            process: "<?php echo e(route('special.payable-cheque-voucher.process')); ?>",
            returns: "<?php echo e(url('/special/accounting/payable-cheque-voucher/returns')); ?>",
            suddenReturns: "<?php echo e(url('/special/accounting/payable-cheque-voucher/sudden-returns')); ?>",
            history: "<?php echo e(url('/special/accounting/payable-cheque-voucher/history')); ?>",
            historyDetail: "<?php echo e(url('/special/accounting/payable-cheque-voucher/history')); ?>",
            show: "<?php echo e(url('/special/accounting/payable-cheque-voucher/show')); ?>",
            updateNew: "<?php echo e(url('/special/accounting/payable-cheque-voucher/update-new')); ?>",
            draftSave: "<?php echo e(url('/special/accounting/payable-cheque-voucher/draft/save')); ?>",
            draftGet: "<?php echo e(url('/special/accounting/payable-cheque-voucher/draft/get')); ?>",
            draftClear: "<?php echo e(url('/special/accounting/payable-cheque-voucher/draft/clear')); ?>",
            recommendations: "<?php echo e(url('/special/accounting/payable-cheque-voucher/supplier')); ?>"
        };

        (() => {
            const perPage = 50;
            const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
            const today = new Date().toISOString().slice(0, 10);
            const $ = (id) => document.getElementById(id);
            let currentSupplier, currentStep = 1, mainPage = 1, invoicePage = 1, map, marker;
            let suppliers = [], suddenReturns = [], selectedSuddenReturns = [], additionalDiscount = 0;
            let additionalPayments = [];
            let globalInvoiceSearch = '', invoiceSearchTimer = null;
            let pcvDashboardFilter = 'pending';
            const mainFilters = {}, invoiceFilters = {};
            const routes = () => window.pcvRoutes || {};
            const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const tv = (v) => String(v ?? '').toLowerCase();
            const btn = (page, active = false) => `px-3 py-2 rounded-lg text-[10px] font-black border ${active ? 'bg-maroon-900 border-maroon-900 text-white' : 'bg-white border-slate-200 text-slate-600'}`;
            const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&', '<': '<', '>': '>', '"': '"', "'": '&#039;' }[char]));
            function suddenReturnAmount(returnRow) {
                return Number(returnRow?.return_amount ?? returnRow?.total_amount ?? 0);
            }
            function suddenReturnPoNumber(returnRow) {
                return returnRow?.po_number || returnRow?.purchase_no || (returnRow?.po_id ? `PO-${returnRow.po_id}` : '---');
            }
            function groupSuddenReturns(returnRows) {
                const groups = {};
                (returnRows || []).forEach(sr => {
                    const key = sr.return_number || sr.id || 'unknown';
                    if (!groups[key]) {
                        groups[key] = {
                            return_number: sr.return_number,
                            po_number: suddenReturnPoNumber(sr),
                            remarks: sr.remarks || '',
                            total_amount: 0
                        };
                    }
                    groups[key].total_amount += suddenReturnAmount(sr);
                });
                return Object.values(groups);
            }
            function selectedInvoicePoIds() {
                const invoices = currentSupplier?.invoices || [];
                const selected = invoices.filter(inv => inv.selected);
                const source = selected.length ? selected : invoices;
                return source.map(inv => inv.sourceId).filter(Boolean);
            }
            function returnedItemsSummary(items) {
                const rows = Array.isArray(items) ? items : [];
                if (!rows.length) return '---';
                return rows.map((item) => {
                    const qty = Number(item.quantity || 0);
                    const code = item.product_code || item.description || 'Item';
                    return `${code}${qty > 0 ? ` (${qty})` : ''}`;
                }).join(', ');
            }
            function numericAmount(value) {
                const amount = Number(value);
                return Number.isFinite(amount) ? amount : 0;
            }
            function clampPercent(value) {
                return Math.min(100, Math.max(0, numericAmount(value)));
            }
            function formatPercent(value) {
                return clampPercent(value).toLocaleString('en-PH', { maximumFractionDigits: 2 });
            }
            function readAdditionalDiscountPercent() {
                const input = $('pcv-additional-discount');
                const percent = clampPercent(input?.value || 0);
                if (input && input.value !== '' && numericAmount(input.value) !== percent) input.value = percent;
                return percent;
            }
            function calculateAdditionalDiscountAmount(firstRemainingTotal, addlDiscPercent) {
                return Math.max(0, numericAmount(firstRemainingTotal)) * (clampPercent(addlDiscPercent) / 100);
            }
            function calculateDisplayTotals(invoiceTotal, globalDiscAmount, suddenReturnRows, addlDiscPercent, addlDiscAmountOverride = null) {
                const suddenReturnsTotal = (suddenReturnRows || []).reduce((sum, row) => sum + suddenReturnAmount(row), 0);
                const additionalDiscountPercent = clampPercent(addlDiscPercent);
                const rawAdditionalDiscount = addlDiscAmountOverride === null || addlDiscAmountOverride === undefined
                    ? calculateAdditionalDiscountAmount(invoiceTotal, additionalDiscountPercent)
                    : numericAmount(addlDiscAmountOverride);
                const additionalDiscountAmount = Math.min(invoiceTotal, Math.max(0, rawAdditionalDiscount));
                const afterAddlDisc = Math.max(0, invoiceTotal - additionalDiscountAmount);
                let netAfterSuddenReturns = afterAddlDisc - suddenReturnsTotal;
                let excessiveAmount = 0;
                if (netAfterSuddenReturns < 0) {
                    netAfterSuddenReturns = 0;
                }
                const finalTotal = Math.max(0, netAfterSuddenReturns - Number(globalDiscAmount || 0));
                
                return {
                    suddenReturnsTotal,
                    netAfterSuddenReturns,
                    excessiveAmount,
                    additionalDiscountPercent,
                    additionalDiscount: additionalDiscountAmount,
                    finalTotal,
                };
            }
            function buildSuddenReturnRowsMarkup(returnRows, rowClass = '') {
                return groupSuddenReturns(returnRows).map((sr) => `
                    <tr class="${rowClass}">
                        <td class="px-4 py-2 font-bold text-slate-700">${escapeHtml(sr.return_number || '---')}</td>
                        <td class="px-4 py-2 font-bold text-slate-600">${escapeHtml(sr.po_number)}</td>
                        <td class="px-4 py-2">${escapeHtml(sr.remarks || '---')}</td>
                        <td class="px-4 py-2 text-right font-black text-rose-600">- ${peso.format(sr.total_amount)}</td>
                    </tr>
                `).join('');
            }
            function buildSuddenReturnPrintSection(returnRows) {
                if (!(returnRows || []).length) return '';
                return `
                    <div style="margin-bottom: 12px; font-size: 13px;">
                        <div style="font-weight: bold; margin-bottom: 6px;">SUDDEN RETURNS</div>
                        <table style="width: 100%; font-size: 12px; border-collapse: collapse;">
                            <thead>
                                <tr style="border-top: 1px solid #000; border-bottom: 1px solid #000;">
                                    <th style="padding: 4px 6px; text-align: left; border-left: 1px solid #000; border-right: 1px solid #000;">RETURN NO.</th>
                                    <th style="padding: 4px 6px; text-align: left; border-right: 1px solid #000;">PO NO.</th>
                                    <th style="padding: 4px 6px; text-align: left; border-right: 1px solid #000;">REMARKS</th>
                                    <th style="padding: 4px 6px; text-align: right; border-right: 1px solid #000;">RETURN AMOUNT</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${groupSuddenReturns(returnRows).map((sr) => `
                                    <tr>
                                        <td style="padding: 4px 6px; border-left: 1px solid #000; border-right: 1px solid #000; border-bottom: 1px solid #000;">${escapeHtml(sr.return_number || '---')}</td>
                                        <td style="padding: 4px 6px; border-right: 1px solid #000; border-bottom: 1px solid #000;">${escapeHtml(sr.po_number)}</td>
                                        <td style="padding: 4px 6px; border-right: 1px solid #000; border-bottom: 1px solid #000;">${escapeHtml(sr.remarks || '---')}</td>
                                        <td style="padding: 4px 6px; text-align: right; font-weight: bold; color: #b91c1c; border-right: 1px solid #000; border-bottom: 1px solid #000;">- ${peso.format(sr.total_amount)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            }

            function paginate(containerId, total, page, cb) {
                const pages = Math.max(1, Math.ceil(total / perPage)), c = document.getElementById(containerId);
                c.innerHTML = [`<button data-page="${Math.max(1, page - 1)}" class="${btn(page - 1)}" ${page === 1 ? 'disabled' : ''}>Prev</button>`]
                    .concat(Array.from({ length: pages }, (_, i) => `<button data-page="${i + 1}" class="${btn(i + 1, i + 1 === page)}">${i + 1}</button>`))
                    .concat(`<button data-page="${Math.min(pages, page + 1)}" class="${btn(page + 1)}" ${page === pages ? 'disabled' : ''}>Next</button>`).join('');
                c.querySelectorAll('button[data-page]').forEach((b) => b.addEventListener('click', () => cb(Number(b.dataset.page))));
            }
            function filteredSuppliers() {
                let filtered = suppliers.filter((s) => Object.entries(mainFilters).every(([k, v]) => !v || tv(s[k]).includes(tv(v))));
                if (pcvDashboardFilter === 'old') {
                    filtered = filtered.filter(s => s.hasOldInvoices);
                } else if (pcvDashboardFilter === 'sudden_returns') {
                    filtered = filtered.filter(s => s.hasSuddenReturns);
                }
                return filtered;
            }
            function updateDashboardCards() {
                document.querySelectorAll('.dashboard-card').forEach(card => {
                    const filter = card.dataset.dashboardFilter;
                    const isActive = filter === pcvDashboardFilter;
                    const label = card.querySelector('span:first-child');
                    const count = card.querySelector('.text-3xl');
                    const countLabel = card.querySelector('.pb-1');
                    const iconWrap = card.querySelector('.w-9');
                    if (isActive) {
                        card.className = 'dashboard-card dashboard-card-active text-left bg-yellow-400 border border-yellow-500 rounded-xl p-4 shadow-sm hover:bg-yellow-300 transition-all';
                        if (label) label.className = 'text-[10px] font-black uppercase tracking-widest text-maroon-900';
                        if (count) count.className = 'text-3xl font-black text-maroon-900';
                        if (countLabel) countLabel.className = 'text-[10px] font-black text-maroon-700 uppercase pb-1';
                        if (iconWrap) iconWrap.className = 'w-9 h-9 rounded-lg bg-yellow-300 text-maroon-800 flex items-center justify-center';
                    } else {
                        card.className = 'dashboard-card dashboard-card-inactive text-left bg-maroon-900 border border-maroon-800 rounded-xl p-4 shadow-sm hover:bg-maroon-800 transition-all';
                        if (label) label.className = 'text-[10px] font-black uppercase tracking-widest text-white';
                        if (count) count.className = 'text-3xl font-black text-white';
                        if (countLabel) countLabel.className = 'text-[10px] font-black text-yellow-400 uppercase pb-1';
                        if (iconWrap) iconWrap.className = 'w-9 h-9 rounded-lg bg-white/10 text-goldlining-400 flex items-center justify-center';
                    }
                });
            }
            function updateDashboardCounts() {
                const total = suppliers.length;
                const oldCount = suppliers.filter(s => s.hasOldInvoices).length;
                const srCount = suppliers.filter(s => s.hasSuddenReturns).length;
                const pendingEl = document.getElementById('pcv-dash-pending');
                const oldEl = document.getElementById('pcv-dash-old');
                const srEl = document.getElementById('pcv-dash-sudden');
                if (pendingEl) pendingEl.textContent = total;
                if (oldEl) oldEl.textContent = oldCount;
                if (srEl) srEl.textContent = srCount;
            }
            function renderMain() {
                const rows = filteredSuppliers(), start = (mainPage - 1) * perPage;
                document.getElementById('pcv-main-tbody').innerHTML = rows.slice(start, start + perPage).map((s) => `
                    <tr class="${s.hasSuddenReturns ? 'pcv-gold-blink-row' : ''} hover:bg-slate-50">
                        <td class="px-5 py-4"><div class="font-black text-slate-800">${escapeHtml(s.supplier)}</div><div class="text-[10px] font-bold text-slate-400 mt-1">${escapeHtml(s.contactPerson || '---')}</div></td>
                        <td class="px-5 py-4 text-right font-black text-rose-600">${s.notPaid}</td>
                        <td class="px-5 py-4 text-right font-black text-amber-600">${s.partialPaid}</td>
                        <td class="px-5 py-4 text-right font-black text-slate-800">${peso.format(s.totalAmount)}</td>
                        <td class="px-5 py-4 text-center"><button data-proceed="${s.id}" class="px-4 py-2 rounded-lg bg-maroon-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-maroon-800">Proceed</button></td>
                    </tr>`).join('') || '<tr><td colspan="5" class="px-5 py-12 text-center text-sm font-bold text-slate-400">No supplier records found.</td></tr>';
                document.getElementById('pcv-main-from').textContent = rows.length ? start + 1 : 0;
                document.getElementById('pcv-main-to').textContent = Math.min(start + perPage, rows.length);
                document.getElementById('pcv-main-total').textContent = rows.length;
                paginate('pcv-main-pagination', rows.length, mainPage, (p) => { mainPage = p; renderMain(); });
                document.querySelectorAll('[data-proceed]').forEach((b) => b.addEventListener('click', () => openModal(Number(b.dataset.proceed))));
            }
            function getGlobalDiscount() {
                return 0;
            }
            function calcDiscountedAmount(amountDue, discount1) {
                let amt = Number(amountDue || 0);
                const d1 = Number(discount1 || 0);
                if (d1 > 0) amt = amt - (amt * d1 / 100);
                return amt;
            }
            function invoiceBaseTotal(invoices) {
                return (invoices || []).reduce((sum, inv) => sum + calcDiscountedAmount(inv.amountDue, inv.discount1), 0);
            }
            function userPaidAmount(inv) {
                return numericAmount(inv?.userPaidAmount ?? inv?.amountPaid ?? 0);
            }
            function selectedReturnAmountForInvoice(inv, returnRows = selectedSuddenReturns) {
                const sourceId = String(inv?.sourceId ?? inv?.purchase_order_id ?? '');
                if (!sourceId) return 0;
                return (returnRows || [])
                    .filter(sr => String(sr?.po_id ?? sr?.purchase_order_id ?? '') === sourceId)
                    .reduce((sum, sr) => sum + suddenReturnAmount(sr), 0);
            }
            function invoiceCalculationAmount(inv, returnRows = selectedSuddenReturns) {
                return userPaidAmount(inv) + selectedReturnAmountForInvoice(inv, returnRows);
            }
            function computedPaidAmount(inv) {
                return numericAmount(inv?.correctedPaidAmount ?? inv?.amountPaid ?? userPaidAmount(inv));
            }
            function computedPaidTotal(invoices) {
                return (invoices || []).reduce((sum, inv) => sum + computedPaidAmount(inv), 0);
            }
            function payableAmountAfterReturns(inv) {
                if (inv?.afterSR !== undefined) return numericAmount(inv.afterSR);
                if (inv?.finalAmountDue !== undefined) return numericAmount(inv.finalAmountDue);
                const afterInvoiceDiscount = calcDiscountedAmount(inv?.amountDue, inv?.discount1);
                return Math.max(0, afterInvoiceDiscount - numericAmount(inv?.suddenReturnDeduction || 0));
            }
            function payableTotalAfterReturns(invoices) {
                return (invoices || []).reduce((sum, inv) => sum + payableAmountAfterReturns(inv), 0);
            }
            function syncCreditAmountFromNet(invoices, globalDiscAmount = 0) {
                const invoiceTotal = (invoices || []).reduce((sum, inv) => sum + invoiceCalculationAmount(inv), 0);
                const totals = calculateDisplayTotals(invoiceTotal, globalDiscAmount, selectedSuddenReturns, additionalDiscount);
                const netAmount = totals.finalTotal;
                // Credit Amount is intentionally user-entered. Recalculating invoice totals must
                // never overwrite what the user typed in Payment Details.
                updatePaymentTotal();
                return netAmount;
            }
            function calculatePaidTotals(invoices, addlDiscPercent, globalDiscAmount = 0) {
                const checkedPaidTotal = (invoices || []).reduce((sum, inv) => sum + userPaidAmount(inv), 0);
                const payableTotal = payableTotalAfterReturns(invoices);
                const additionalDiscountPercent = clampPercent(addlDiscPercent);
                const additionalDiscount = Math.min(payableTotal, calculateAdditionalDiscountAmount(payableTotal, additionalDiscountPercent));
                const finalTotal = Math.max(0, payableTotal - additionalDiscount - numericAmount(globalDiscAmount));
                return {
                    checkedPaidTotal,
                    payableTotal,
                    additionalDiscountPercent,
                    additionalDiscount,
                    finalTotal,
                };
            }
            function recalculateVoucherDeductions() {
                additionalDiscount = readAdditionalDiscountPercent();
                const checkedInvoices = (currentSupplier?.invoices || []).filter(i => i.selected);
                const checkedCount = checkedInvoices.length;
                (currentSupplier?.invoices || []).forEach(inv => {
                    delete inv.suddenReturnDeduction;
                    delete inv.additionalDiscountDeduction;
                    delete inv.finalAmountDue;
                    delete inv.remainingBalance;
                    delete inv.correctedAmountPaid;
                    delete inv.correctedPaidAmount;
                    delete inv.remainingBeforeAdditionalDiscount;
                    delete inv.additionalDiscountShare;
                    delete inv.appliedAdditionalDiscount;
                    delete inv.afterSR;
                    if (!inv.selected && inv.userPaidAmount !== undefined) inv.amountPaid = inv.userPaidAmount;
                });
                if (!checkedCount) {
                    syncCreditAmountFromNet([]);
                    return;
                }
                const suddenReturnTotal = selectedSuddenReturns.reduce((sum, sr) => sum + suddenReturnAmount(sr), 0);
                const srShare = suddenReturnTotal / checkedCount;
                // Step 1: Compute amount after invoice discount and SR deduction
                checkedInvoices.forEach(inv => {
                    if (inv.userPaidAmount === undefined) inv.userPaidAmount = numericAmount(inv.amountPaid || 0);
                    const afterD1 = calcDiscountedAmount(inv.amountDue, inv.discount1);
                    inv.suddenReturnDeduction = srShare;
                    const afterSR = Math.max(0, afterD1 - srShare);
                    inv.afterSR = afterSR;
                });
                // Step 2: Additional Discount is NOT applied per invoice row — only total-level summary
                checkedInvoices.forEach(inv => {
                    const afterSR = Number(inv.afterSR || 0);
                    const basePaid = userPaidAmount(inv);
                    inv.amountPaid = basePaid;
                    inv.correctedPaidAmount = basePaid;
                    inv.finalAmountDue = afterSR;
                    inv.additionalDiscountDeduction = 0;
                    inv.additionalDiscountShare = 0;
                    inv.remainingBalance = Math.max(0, afterSR - basePaid);
                    inv.remarks = inv.remainingBalance <= 0.005 ? 'Paid' : basePaid > 0.005 ? 'Partial Paid' : 'Not Paid';
                });
                syncCreditAmountFromNet(checkedInvoices);
            }
            function filteredInvoices() {
                return (currentSupplier?.invoices || []).filter((i) => Object.entries(invoiceFilters).every(([k, v]) => !v || tv(i[k]).includes(tv(v))));
            }
            function renderStep2Summary() {
                const selected = selectedVoucherInvoices();
                const summaryEl = $('pcv-summary-content');
                if (!selected.length || !summaryEl) { $('pcv-step-2-summary')?.classList.add('hidden'); return; }
                $('pcv-step-2-summary')?.classList.remove('hidden');
                const lines = selected.map((i) => {
                    return `<div class="text-right font-black text-slate-800">${peso.format(invoiceCalculationAmount(i))}</div>`;
                }).join('');
                const totalBeforeGlob = selected.reduce((sum, inv) => sum + invoiceCalculationAmount(inv), 0);
                const globalDisc = getGlobalDiscount();
                const globalDiscAmount = totalBeforeGlob * globalDisc / 100;
                const suddenReturnTotal = selectedSuddenReturns.reduce((sum, sr) => sum + suddenReturnAmount(sr), 0);
                const addlDiscPercent = readAdditionalDiscountPercent();
                const totals = calculateDisplayTotals(totalBeforeGlob, globalDiscAmount, selectedSuddenReturns, addlDiscPercent);
                const discDisplay = $('pcv-total-discount-display');
               
                const afterDisc = Math.max(0, totalBeforeGlob - totals.additionalDiscount);
                summaryEl.innerHTML = `
                    <div class="space-y-1 text-xs font-mono">
                        ${lines}
                        <div class="border-t border-dashed border-slate-400 my-2"></div>
                        <div class="flex justify-between font-black text-slate-800"><span>INVOICE PAID TOTAL</span><span>${peso.format(totalBeforeGlob)}</span></div>
                        ${totals.additionalDiscount > 0 ? `<div class="flex justify-between text-blue-600 font-bold"><span>- Additional Disc (${formatPercent(totals.additionalDiscountPercent)}%)</span><span>${peso.format(totals.additionalDiscount)}</span></div>` : ''}
                        <div class="flex justify-between font-bold text-slate-800"><span>TOTAL AMOUNT</span><span>${peso.format(afterDisc)}</span></div>
                        ${suddenReturnTotal > 0 ? `<div class="flex justify-between text-rose-600 font-bold"><span>SUDDENLY RETURN</span><span>${peso.format(suddenReturnTotal)}</span></div>` : ''}
                        ${globalDisc > 0 ? `<div class="flex justify-between text-amber-600 font-bold"><span>- Disc ${peso.format(globalDiscAmount)} (${globalDisc}%)</span><span></span></div>` : ''}
                        <div class="border-t border-dashed border-slate-400 my-2"></div>
                        <div class="flex justify-between text-emerald-600 font-black text-sm"><span>NET AMOUNT</span><span>${peso.format(totals.finalTotal)}</span></div>
                    </div>`;
            }
            function renderInvoices() {
                    const rows = filteredInvoices(), start = (invoicePage - 1) * perPage;
                recalculateVoucherDeductions();
                document.getElementById('pcv-invoice-tbody').innerHTML = rows.slice(start, start + perPage).map((i) => {
                    const amt = Number(i.amountDue || 0);
                    const inputPaid = userPaidAmount(i);
                    const paid = Number(i.correctedPaidAmount ?? i.amountPaid ?? inputPaid);
                    const d1 = Number(i.discount1 || 0);
                    const retAmt = Number(i.returnAmount || 0);
                    const totalRet = Number(i.totalReturns || 0);
                    const originalAmt = amt + retAmt;
                    const d1Amt = amt * d1 / 100;
                    const netDue = amt - d1Amt;
                    // Always recompute remarks from live data (never trust server-side i.remarks alone)
                    const eps = 0.005;
                    const computedRemarks = paid >= netDue - eps ? 'Paid' : paid > eps ? 'Partial Paid' : 'Not Paid';
                    const remaining = (i.remainingBalance !== undefined) ? Math.max(0, i.remainingBalance) : (paid >= netDue - eps ? 0 : Math.max(0, netDue - paid));
                    const isFullyPaid = remaining <= eps;
                    i.remarks = isFullyPaid ? 'Paid' : computedRemarks;
                    const displayRemarks = i.remarks;
                    const srDed = Number(i.suddenReturnDeduction || 0);
                    const addlDed = Number(i.additionalDiscountDeduction || 0);
                    let dueDisplay = '';
                    if (d1 > 0 && retAmt > 0) {
                        dueDisplay = `<div><span class="font-black text-slate-800">${peso.format(originalAmt)}</span> <span class="text-amber-600 font-bold">- ${d1}%</span> <span class="text-amber-700 font-bold">(${peso.format(d1Amt)})</span> <span class="text-rose-600 font-bold">- return ${peso.format(retAmt)}</span> <span class="font-black text-slate-800">=${peso.format(netDue)}</span></div>`;
                        if (isFullyPaid) dueDisplay += `<div class="font-black text-emerald-600">${peso.format(netDue)} - ${peso.format(paid)} = ${peso.format(remaining)}</div>`;
                    } else if (d1 > 0) {
                        dueDisplay = `<div><span class="font-black text-slate-800">${peso.format(originalAmt)}</span> <span class="text-amber-600 font-bold">- ${d1}%</span> <span class="text-amber-700 font-bold">(${peso.format(d1Amt)})</span> <span class="font-black text-slate-800">=${peso.format(netDue)}</span></div>`;
                        if (isFullyPaid) dueDisplay += `<div class="font-black text-emerald-600">${peso.format(netDue)} - ${peso.format(paid)} = ${peso.format(remaining)}</div>`;
                    } else if (retAmt > 0) {
                        dueDisplay = `<div><span class="font-black text-slate-800">${peso.format(originalAmt)}</span> <span class="text-rose-600 font-bold">- return ${peso.format(retAmt)}</span> <span class="font-black text-slate-800">=${peso.format(amt)}</span></div>`;
                        if (isFullyPaid) dueDisplay += `<div class="font-black text-emerald-600">${peso.format(amt)} - ${peso.format(paid)} = ${peso.format(remaining)}</div>`;
                    } else {
                        dueDisplay = `<span class="font-black text-slate-800">${peso.format(amt)}</span>`;
                    }
                    return `
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-center"><input type="checkbox" data-invoice-check="${i.id}" class="accent-maroon-900" ${i.selected ? 'checked' : ''}></td>
                        <td class="px-4 py-3 font-black text-slate-700">${escapeHtml(i.purchaseNo)}</td><td class="px-4 py-3 font-bold text-slate-600">${escapeHtml(i.invoiceNo)}</td>
                        <td class="px-4 py-3 font-bold text-slate-500 whitespace-nowrap">${escapeHtml(i.date || '---')}</td>
                        <td class="px-4 py-3 text-right"><input data-amount-paid="${i.id}" type="number" min="0" step="0.01" value="${inputPaid}" class="w-24 text-right border border-slate-200 rounded-lg px-2 py-1.5 text-xs font-bold outline-none"></td>
                        <td class="px-4 py-3 text-right"><input data-discount1="${i.id}" type="number" min="0" max="100" step="0.01" value="${d1}" class="w-16 text-right border border-slate-200 rounded-lg px-2 py-1.5 text-xs font-bold outline-none"></td>
                        <td class="px-4 py-3 text-right font-black text-rose-600">${retAmt > 0 ? peso.format(retAmt) : '---'}</td>
                    <td class="px-4 py-3 text-right font-black text-slate-700">${totalRet > 0 ? `<span class="cursor-pointer inline-flex items-center gap-1.5 hover:text-maroon-700 transition-colors" data-return-info="${i.sourceId}" title="View return details">${totalRet}<i data-lucide="alert-triangle" class="w-4 h-4 text-amber-500"></i></span>` : '---'}</td>
                    <td class="px-4 py-3 text-right font-black ${srDed > 0 ? 'text-rose-700' : 'text-slate-300'}">${srDed > 0 ? peso.format(srDed) : '---'}</td>
                    <td class="px-4 py-3 text-right font-black ${addlDed > 0 ? 'text-blue-700' : 'text-slate-300'}">${addlDed > 0 ? peso.format(addlDed) : '---'}</td>
                    <td class="px-4 py-3"><input data-rs-details="${i.id}" type="text" value="${escapeHtml(i.rsDetails || '')}" class="w-28 border border-slate-200 rounded-lg px-2 py-1.5 text-xs font-bold outline-none"></td>
                    <td class="px-4 py-3 text-right"><div class="text-xs leading-tight">${dueDisplay}</div></td>
                        <td class="px-4 py-3 text-right font-black ${remaining <= 0 ? 'text-emerald-600' : 'text-amber-600'}">${peso.format(Math.max(0, remaining))}</td>
                        <td class="px-4 py-3 font-bold ${isFullyPaid ? 'text-emerald-600' : displayRemarks === 'Partial Paid' ? 'text-amber-600' : 'text-rose-600'}">${displayRemarks}</td>
                    </tr>`;
                }).join('');
                document.getElementById('pcv-invoice-from').textContent = rows.length ? start + 1 : 0;
                document.getElementById('pcv-invoice-to').textContent = Math.min(start + perPage, rows.length);
                document.getElementById('pcv-invoice-total').textContent = rows.length;
                paginate('pcv-invoice-pagination', rows.length, invoicePage, (p) => { invoicePage = p; renderInvoices(); });
                document.querySelectorAll('[data-invoice-check]').forEach((c) => c.addEventListener('change', () => {
                    const row = currentSupplier.invoices.find((i) => String(i.id) === c.dataset.invoiceCheck);
                    if (!row) return;
                    row.selected = c.checked;
                    if (c.checked) {
                        recalculateVoucherDeductions();
                        const calculatedRemaining = payableAmountAfterReturns(row);
                        row.userPaidAmount = calculatedRemaining;
                        row.amountPaid = calculatedRemaining;
                    } else {
                        row.userPaidAmount = 0;
                        row.amountPaid = 0;
                    }
                    renderInvoices();
                    fetchSuddenReturns(currentSupplier.id);
                }));
                document.querySelectorAll('[data-discount1]').forEach((input) => {
                    input.addEventListener('input', () => {
                        const row = currentSupplier.invoices.find((i) => String(i.id) === input.dataset.discount1);
                        const tr = input.closest('tr');
                        if (row && tr) {
                            row.discount1 = Number(input.value || 0);
                            recalculateVoucherDeductions();
                            updateRowCells(row, tr);
                            renderStep2Summary();
                        }
                    });
                });
                document.querySelectorAll('[data-amount-paid]').forEach((input) => {
                    input.addEventListener('input', () => {
                        const row = currentSupplier.invoices.find((i) => String(i.id) === input.dataset.amountPaid);
                        const tr = input.closest('tr');
                        if (row && tr) {
                            row.userPaidAmount = Number(input.value || 0);
                            row.amountPaid = row.userPaidAmount;
                            recalculateVoucherDeductions();
                            updateRowCells(row, tr);
                            renderStep2Summary();
                        }
                    });
                    input.addEventListener('blur', () => {
                        if (input.value !== '') input.value = Number(input.value || 0).toFixed(2);
                    });
                });
                document.querySelectorAll('[data-return-info]').forEach((el) => el.addEventListener('click', () => openReturnModal(Number(el.dataset.returnInfo))));
                document.querySelectorAll('[data-rs-details]').forEach((input) => input.addEventListener('change', () => { const row = currentSupplier.invoices.find((i) => String(i.id) === input.dataset.rsDetails); if (row) { row.rsDetails = input.value; } }));
                renderStep2Summary();
            }
            function updateRowCells(row, tr) {
                // Always read current values directly from DOM inputs for accuracy
                const amtPaidInput = tr.querySelector('[data-amount-paid]');
                const disc1Input = tr.querySelector('[data-discount1]');
                if (amtPaidInput) {
                    row.userPaidAmount = Number(amtPaidInput.value || 0);
                    row.amountPaid = row.userPaidAmount;
                }
                if (disc1Input) row.discount1 = Number(disc1Input.value || 0);

                const amt = Number(row.amountDue || 0);
                const d1 = Number(row.discount1 || 0);
                const retAmt = Number(row.returnAmount || 0);
                const originalAmt = amt + retAmt;
                const d1Amt = amt * d1 / 100;
                const netDue = amt - d1Amt;
                const paid = Number(row.correctedPaidAmount ?? row.amountPaid ?? userPaidAmount(row));
                const eps = 0.005;
                const computedRemarks = paid >= netDue - eps ? 'Paid' : paid > eps ? 'Partial Paid' : 'Not Paid';
                const remaining = (row.remainingBalance !== undefined) ? Math.max(0, row.remainingBalance) : (paid >= netDue - eps ? 0 : Math.max(0, netDue - paid));
                const isFullyPaid = remaining <= eps;
                const remarks = isFullyPaid ? 'Paid' : computedRemarks;
                row.remarks = remarks;
                let dueDisplay = '';
                if (d1 > 0 && retAmt > 0) {
                    dueDisplay = `<div><span class="font-black text-slate-800">${peso.format(originalAmt)}</span> <span class="text-amber-600 font-bold">- ${d1}%</span> <span class="text-amber-700 font-bold">(${peso.format(d1Amt)})</span> <span class="text-rose-600 font-bold">- return ${peso.format(retAmt)}</span> <span class="font-black text-slate-800">=${peso.format(netDue)}</span></div>`;
                    if (isFullyPaid) dueDisplay += `<div class="font-black text-emerald-600">${peso.format(netDue)} - ${peso.format(paid)} = ${peso.format(remaining)}</div>`;
                } else if (d1 > 0) {
                    dueDisplay = `<div><span class="font-black text-slate-800">${peso.format(originalAmt)}</span> <span class="text-amber-600 font-bold">- ${d1}%</span> <span class="text-amber-700 font-bold">(${peso.format(d1Amt)})</span> <span class="font-black text-slate-800">=${peso.format(netDue)}</span></div>`;
                    if (isFullyPaid) dueDisplay += `<div class="font-black text-emerald-600">${peso.format(netDue)} - ${peso.format(paid)} = ${peso.format(remaining)}</div>`;
                } else if (retAmt > 0) {
                    dueDisplay = `<div><span class="font-black text-slate-800">${peso.format(originalAmt)}</span> <span class="text-rose-600 font-bold">- return ${peso.format(retAmt)}</span> <span class="font-black text-slate-800">=${peso.format(amt)}</span></div>`;
                    if (isFullyPaid) dueDisplay += `<div class="font-black text-emerald-600">${peso.format(amt)} - ${peso.format(paid)} = ${peso.format(remaining)}</div>`;
                } else {
                    dueDisplay = `<span class="font-black text-slate-800">${peso.format(amt)}</span>`;
                }
                const srDed = Number(row.suddenReturnDeduction || 0);
                const addlDed = Number(row.additionalDiscountDeduction || 0);
                tr.children[7].innerHTML = `<span class="font-black ${srDed > 0 ? 'text-rose-700' : 'text-slate-300'}">${srDed > 0 ? peso.format(srDed) : '---'}</span>`;
                tr.children[8].innerHTML = `<span class="font-black ${addlDed > 0 ? 'text-blue-700' : 'text-slate-300'}">${addlDed > 0 ? peso.format(addlDed) : '---'}</span>`;
                tr.children[10].innerHTML = `<div class="text-xs leading-tight">${dueDisplay}</div>`;
                tr.children[11].innerHTML = `<span class="font-black ${remaining === 0 ? 'text-emerald-600' : 'text-amber-600'}">${peso.format(remaining)}</span>`;
                tr.children[12].innerHTML = `<span class="font-bold ${isFullyPaid ? 'text-emerald-600' : remarks === 'Partial Paid' || remarks === 'Partial' ? 'text-amber-600' : 'text-rose-600'}">${remarks}</span>`;
            }
            async function locate(address) {
                try { const r = await fetch(`https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(address)}`), d = await r.json(); if (d[0]) return [Number(d[0].lat), Number(d[0].lon)]; } catch (e) { console.warn(e); }
                return [14.5995, 120.9842];
            }
            async function renderMap(address) {
                if (!window.L) return;
                const coords = await locate(address);
                setTimeout(() => {
                    if (!map) { map = L.map('pcv-supplier-map').setView(coords, 13); L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors' }).addTo(map); marker = L.marker(coords).addTo(map); }
                    else { map.setView(coords, 13); marker.setLatLng(coords); map.invalidateSize(); }
                }, 120);
            }
            function paymentMethod() { return document.querySelector('input[name="pcv-payment-method"]:checked')?.value || 'Gcash'; }
            function primaryPaymentDetail() {
                const method = paymentMethod();
                const bank = ['Cheque', 'Bank Transfer'].includes(method);
                return {
                    payment_method: method,
                    account_no: bank ? ($('pcv-account-no')?.value || '') : null,
                    bank_name: bank ? ($('pcv-bank-name')?.value || '') : null,
                    check_no: bank ? ($('pcv-check-no')?.value || '') : null,
                    check_date: bank ? ($('pcv-check-date')?.value || null) : null,
                    payment_date: bank ? null : ($('pcv-payment-date')?.value || null),
                    reference_no: method === 'Gcash' ? ($('pcv-gcash-reference-no')?.value || '') : null,
                    credit_amount: Number(bank ? ($('pcv-bank-credit')?.value || 0) : ($('pcv-cash-credit')?.value || 0)),
                };
            }
            function normalizedPaymentDetail(payment = {}) {
                return {
                    payment_method: payment.payment_method || 'Cheque',
                    account_no: payment.account_no || '',
                    bank_name: payment.bank_name || '',
                    check_no: payment.check_no || '',
                    check_date: payment.check_date ? String(payment.check_date).slice(0, 10) : '',
                    payment_date: payment.payment_date ? String(payment.payment_date).slice(0, 10) : today,
                    reference_no: (payment.payment_method || 'Cheque') === 'Gcash' ? (payment.reference_no || '') : '',
                    credit_amount: Number(payment.credit_amount || 0),
                };
            }
            function voucherPaymentDetails() {
                return [primaryPaymentDetail(), ...additionalPayments.map(normalizedPaymentDetail)];
            }
            function paymentDetailsTotal() {
                return voucherPaymentDetails().reduce((sum, payment) => sum + Number(payment.credit_amount || 0), 0);
            }
            function showCheckSummaryEnabled() {
                return Boolean($('pcv-show-check-summary')?.checked);
            }
            function checkSummaryRows(payments) {
                const paymentList = Array.isArray(payments) ? payments : [];
                const paymentTotal = paymentList.reduce((sum, payment) => sum + Number(payment?.credit_amount || 0), 0);
                const grouped = new Map();

                paymentList.forEach((payment) => {
                    const checkNo = String(payment?.check_no || '').trim();
                    if (!checkNo) return;
                    const current = grouped.get(checkNo) || { check_no: checkNo, check_amount: 0 };
                    current.check_amount += Number(payment?.credit_amount || 0);
                    grouped.set(checkNo, current);
                });

                const rows = Array.from(grouped.values());
                if (!rows.length) return [];

                // Check Summary must reconcile to Payment Total. If there is only one
                // filled Check No., its Check Amount is exactly the full Payment Total.
                // With multiple checks, preserve each check's captured amount and put
                // any unassigned payment amount into the first check so the sum still
                // equals Payment Total.
                const assignedTotal = rows.reduce((sum, row) => sum + Number(row.check_amount || 0), 0);
                rows[0].check_amount += paymentTotal - assignedTotal;

                return rows;
            }
            function buildCheckSummaryPrintSection(payments, enabled = true) {
                const rows = checkSummaryRows(payments);
                if (!enabled || !rows.length) return '';
                return `
                    <div style="margin-bottom: 12px; font-size: 13px;">
                        <div style="font-weight: bold; text-align: center; padding: 6px; border: 1px solid #000; border-bottom: 0;">CHECK SUMMARY</div>
                        <table style="width: 100%; font-size: 12px; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th style="padding: 5px 6px; text-align: left; border: 1px solid #000;">CHECK NO.</th>
                                    <th style="padding: 5px 6px; text-align: right; border: 1px solid #000;">CHECK AMOUNT</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${rows.map((payment) => `<tr>
                                    <td style="padding: 5px 6px; border: 1px solid #000;">${escapeHtml(String(payment.check_no || '').trim())}</td>
                                    <td style="padding: 5px 6px; text-align: right; font-weight: bold; border: 1px solid #000;">${peso.format(Number(payment.check_amount || 0))}</td>
                                </tr>`).join('')}
                            </tbody>
                        </table>
                    </div>`;
            }
            function populateStaticCheckSummary(payments, enabled) {
                const section = $('print-check-summary-section');
                const tbody = $('print-check-summary-rows');
                if (!section || !tbody) return;
                const rows = checkSummaryRows(payments);
                if (!enabled || !rows.length) {
                    section.style.display = 'none';
                    tbody.innerHTML = '';
                    return;
                }
                section.style.display = 'block';
                tbody.innerHTML = rows.map((payment) => `<tr>
                    <td style="padding: 5px 6px; border: 1px solid #000;">${escapeHtml(String(payment.check_no || '').trim())}</td>
                    <td style="padding: 5px 6px; text-align: right; font-weight: bold; border: 1px solid #000;">${peso.format(Number(payment.check_amount || 0))}</td>
                </tr>`).join('');
            }
            function pcvInvoiceAmount() {
                const selected = (currentSupplier?.invoices || []).filter(inv => inv.selected);
                if (!selected.length) return 0;
                const invoiceTotal = selected.reduce((sum, inv) => sum + invoiceCalculationAmount(inv), 0);
                const globalDisc = getGlobalDiscount();
                const globalDiscAmount = invoiceTotal * globalDisc / 100;
                return calculateDisplayTotals(
                    invoiceTotal,
                    globalDiscAmount,
                    selectedSuddenReturns,
                    readAdditionalDiscountPercent()
                ).finalTotal;
            }
            function updatePaymentTotal() {
                const total = paymentDetailsTotal();
                const invoiceAmount = pcvInvoiceAmount();
                const remaining = Math.max(0, invoiceAmount - total);
                const paymentEl = $('pcv-payment-total');
                const invoiceAmountEl = $('pcv-invoice-amount');
                const remainingEl = $('pcv-payment-remaining');
                const headerPaymentEl = $('pcv-header-payment-total');
                const headerInvoiceAmountEl = $('pcv-header-invoice-amount');
                const headerRemainingEl = $('pcv-header-remaining');
                if (paymentEl) paymentEl.textContent = peso.format(total);
                if (invoiceAmountEl) invoiceAmountEl.textContent = peso.format(invoiceAmount);
                if (remainingEl) remainingEl.textContent = peso.format(remaining);
                if (headerPaymentEl) headerPaymentEl.textContent = peso.format(total);
                if (headerInvoiceAmountEl) headerInvoiceAmountEl.textContent = peso.format(invoiceAmount);
                if (headerRemainingEl) headerRemainingEl.textContent = peso.format(remaining);
                return total;
            }
            function renderAdditionalPayments() {
                const container = $('pcv-extra-payments');
                if (!container) return;
                container.innerHTML = additionalPayments.map((payment, idx) => {
                    const p = normalizedPaymentDetail(payment);
                    const isBank = ['Cheque', 'Bank Transfer'].includes(p.payment_method);
                    const isGcash = p.payment_method === 'Gcash';
                    return `
                        <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-5" data-extra-payment-card="${idx}">
                            <div class="flex items-center justify-between gap-3 mb-4">
                                <div class="text-[10px] font-black uppercase tracking-widest text-maroon-800">Additional Payment ${idx + 2}</div>
                                <button type="button" data-remove-payment="${idx}" class="px-3 py-1.5 rounded-lg bg-rose-50 text-rose-700 text-[10px] font-black uppercase tracking-widest hover:bg-rose-100">Remove</button>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Payment Method</label>
                                    <select data-extra-payment-field="payment_method" data-extra-payment-index="${idx}" class="pcv-field mt-1">
                                        ${['Gcash', 'Cash', 'Cheque', 'Bank Transfer'].map(method => `<option value="${method}" ${p.payment_method === method ? 'selected' : ''}>${method}</option>`).join('')}
                                    </select>
                                </div>
                                <div class="${isBank ? '' : 'hidden'}" data-extra-bank-field="${idx}"><label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Account No</label><input data-extra-payment-field="account_no" data-extra-payment-index="${idx}" class="pcv-field mt-1" type="text" value="${escapeHtml(p.account_no)}"></div>
                                <div class="${isBank ? '' : 'hidden'}" data-extra-bank-field="${idx}"><label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Bank Name</label><input data-extra-payment-field="bank_name" data-extra-payment-index="${idx}" class="pcv-field mt-1" type="text" value="${escapeHtml(p.bank_name)}"></div>
                                <div class="${isBank ? '' : 'hidden'}" data-extra-bank-field="${idx}"><label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Check No</label><input data-extra-payment-field="check_no" data-extra-payment-index="${idx}" class="pcv-field mt-1" type="text" value="${escapeHtml(p.check_no)}"></div>
                                <div class="${isBank ? '' : 'hidden'}" data-extra-bank-field="${idx}"><label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Check Date</label><input data-extra-payment-field="check_date" data-extra-payment-index="${idx}" class="pcv-field mt-1" type="date" value="${escapeHtml(p.check_date)}"></div>
                                <div class="${isGcash ? '' : 'hidden'}" data-extra-gcash-field="${idx}"><label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Reference Number</label><input data-extra-payment-field="reference_no" data-extra-payment-index="${idx}" class="pcv-field mt-1" type="text" value="${escapeHtml(p.reference_no)}" placeholder="GCash reference number"></div>
                                <div class="${isBank ? 'hidden' : ''}" data-extra-cash-field="${idx}"><label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Date</label><input data-extra-payment-field="payment_date" data-extra-payment-index="${idx}" class="pcv-field mt-1" type="date" value="${escapeHtml(p.payment_date)}"></div>
                                <div><label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Credit Amount</label><input data-extra-payment-field="credit_amount" data-extra-payment-index="${idx}" class="pcv-field mt-1" type="number" step="0.01" value="${Number(p.credit_amount || 0)}"></div>
                            </div>
                        </div>`;
                }).join('');

                container.querySelectorAll('[data-extra-payment-field]').forEach(input => {
                    const eventName = input.tagName === 'SELECT' ? 'change' : 'input';
                    input.addEventListener(eventName, () => {
                        const idx = Number(input.dataset.extraPaymentIndex);
                        const field = input.dataset.extraPaymentField;
                        if (!additionalPayments[idx]) return;
                        additionalPayments[idx][field] = field === 'credit_amount' ? Number(input.value || 0) : input.value;
                        if (field === 'payment_method') renderAdditionalPayments();
                        updatePaymentTotal();
                    });
                });
                container.querySelectorAll('[data-remove-payment]').forEach(btn => btn.addEventListener('click', () => {
                    additionalPayments.splice(Number(btn.dataset.removePayment), 1);
                    renderAdditionalPayments();
                    updatePaymentTotal();
                }));
                if (window.lucide) window.lucide.createIcons();
            }
            window.pcvAddPaymentDetail = function() {
                additionalPayments.push(normalizedPaymentDetail({ payment_method: 'Cheque', payment_date: today, credit_amount: 0 }));
                renderAdditionalPayments();
                updatePaymentTotal();
            };
            window.pcvGetPaymentDetails = () => voucherPaymentDetails().map(normalizedPaymentDetail);
            window.pcvSetPaymentDetails = function(payments) {
                const list = Array.isArray(payments) && payments.length ? payments.map(normalizedPaymentDetail) : [];
                if (list.length) {
                    const primary = list[0];
                    const radio = document.querySelector(`input[name="pcv-payment-method"][value="${primary.payment_method}"]`);
                    if (radio) radio.checked = true;
                    const bank = ['Cheque', 'Bank Transfer'].includes(primary.payment_method);
                    if ($('pcv-account-no')) $('pcv-account-no').value = primary.account_no || '';
                    if ($('pcv-bank-name')) $('pcv-bank-name').value = primary.bank_name || '';
                    if ($('pcv-check-no')) $('pcv-check-no').value = primary.check_no || '';
                    if ($('pcv-check-date')) $('pcv-check-date').value = primary.check_date || '';
                    if ($('pcv-payment-date')) $('pcv-payment-date').value = primary.payment_date || today;
                    if ($('pcv-gcash-reference-no')) $('pcv-gcash-reference-no').value = primary.reference_no || '';
                    if ($('pcv-bank-credit')) $('pcv-bank-credit').value = Number(primary.credit_amount || 0).toFixed(2);
                    if ($('pcv-cash-credit')) $('pcv-cash-credit').value = Number(primary.credit_amount || 0).toFixed(2);
                }
                additionalPayments = list.slice(1);
                updatePaymentFields();
                renderAdditionalPayments();
                updatePaymentTotal();
            };
            function updatePaymentFields() {
                const method = paymentMethod();
                const bank = ['Cheque','Bank Transfer'].includes(method);
                const gcash = method === 'Gcash';
                document.getElementById('pcv-bank-fields').classList.toggle('hidden', !bank);
                document.getElementById('pcv-cash-fields').classList.toggle('hidden', bank);
                document.getElementById('pcv-gcash-reference-wrap')?.classList.toggle('hidden', !gcash);
                updatePaymentTotal();
            }
            function renderReview(contentId, frameId) {
                console.log('=== renderReview START ===');
                console.log('contentId:', contentId);
                console.log('frameId:', frameId);
                console.log('currentSupplier:', currentSupplier);
                
                if (!currentSupplier || !currentSupplier.invoices) {
                    console.error('currentSupplier or invoices not available!');
                    alert('Error: Supplier data not available. Please start over.');
                    return;
                }
                
                recalculateVoucherDeductions();
                const selected = selectedVoucherInvoices();
                console.log('selected invoices count:', selected.length);
                console.log('selected invoices:', selected);
                
                const reviewPayments = voucherPaymentDetails();
                const reviewPaymentTotal = reviewPayments.reduce((sum, payment) => sum + Number(payment.credit_amount || 0), 0);
                const paymentReviewRows = reviewPayments.map((payment, idx) => {
                    const bank = ['Cheque', 'Bank Transfer'].includes(payment.payment_method);
                    const gcash = payment.payment_method === 'Gcash';
                    const detail = bank
                        ? `${escapeHtml(payment.bank_name || '---')} / ${escapeHtml(payment.account_no || '---')} / ${escapeHtml(payment.check_no || '---')} / ${escapeHtml(payment.check_date || '---')}`
                        : gcash
                            ? `Ref: ${escapeHtml(payment.reference_no || '---')} / ${escapeHtml(payment.payment_date || '---')}`
                            : escapeHtml(payment.payment_date || '---');
                    return `<tr class="border-b border-slate-100"><td class="py-2 pr-3 font-black text-slate-700">${idx + 1}</td><td class="py-2 pr-3 font-bold text-slate-600">${escapeHtml(payment.payment_method || '---')}</td><td class="py-2 pr-3 font-semibold text-slate-500">${detail}</td><td class="py-2 text-right font-black ${Number(payment.credit_amount || 0) < 0 ? 'text-rose-600' : 'text-slate-800'}">${peso.format(Number(payment.credit_amount || 0))}</td></tr>`;
                }).join('');
                const invoiceLines = selected.map((i) => {
                    const amt = invoiceCalculationAmount(i);
                    return `<div class="flex justify-between font-black text-slate-800"><span>${escapeHtml(i.invoiceNo)}</span><span>${peso.format(amt)}</span></div>`;
                }).join('');
                const totalBeforeGlob = selected.reduce((sum, inv) => sum + invoiceCalculationAmount(inv), 0);
                const globalDisc = getGlobalDiscount();
                let globalDiscAmount = 0;
                if (globalDisc > 0) {
                    globalDiscAmount = totalBeforeGlob * globalDisc / 100;
                }
                const reviewTotals = calculateDisplayTotals(totalBeforeGlob, globalDiscAmount, selectedSuddenReturns, additionalDiscount);
                const suddenReturnCard = selectedSuddenReturns.length ? `
                    <div class="rounded-xl border border-slate-200 p-5 bg-white lg:col-span-2">
                        <h5 class="text-[10px] font-black uppercase tracking-widest text-maroon-800 mb-4">Sudden Returns</h5>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs whitespace-nowrap">
                                <thead>
                                    <tr class="text-[10px] font-black uppercase tracking-widest text-slate-500 border-b border-slate-200">
                                        <th class="px-4 py-2">Return No.</th>
                                        <th class="px-4 py-2">PO No.</th>
                                        <th class="px-4 py-2">Remarks</th>
                                        <th class="px-4 py-2 text-right">Return Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">${buildSuddenReturnRowsMarkup(selectedSuddenReturns, 'hover:bg-slate-50')}</tbody>
                            </table>
                        </div>
                    </div>`
                    : '';
                const totalsCard = `
                    <div class="rounded-xl border border-slate-200 p-5 bg-white">
                        <h5 class="text-[10px] font-black uppercase tracking-widest text-maroon-800 mb-4">Totals</h5>
                        <div class="space-y-2 text-xs font-mono text-right">
                            <div class="flex justify-between font-black text-slate-800"><span>Checked Paid Total</span><span>${peso.format(totalBeforeGlob)}</span></div>
                            ${selectedSuddenReturns.length ? `<div class="flex justify-between font-bold text-rose-600"><span>Sudden Return Applied</span><span>${peso.format(selectedSuddenReturns.reduce((sum, sr) => sum + suddenReturnAmount(sr), 0))}</span></div>` : ''}
                            ${reviewTotals.additionalDiscount > 0 ? `<div class="flex justify-between font-bold text-blue-600"><span>Additional Disc (${formatPercent(reviewTotals.additionalDiscountPercent)}%)</span><span>- ${peso.format(reviewTotals.additionalDiscount)}</span></div>` : ''}
                            ${globalDisc > 0 ? `<div class="flex justify-between font-bold text-amber-600"><span>Total Discount</span><span>- ${peso.format(globalDiscAmount)} (${globalDisc}%)</span></div>` : ''}
                            <div class="border-t border-dashed border-slate-300 pt-2 flex justify-between font-black text-amber-600 text-sm"><span>Invoice Amount</span><span>${peso.format(reviewTotals.finalTotal)}</span></div>
                            <div class="flex justify-between font-black text-emerald-700"><span>Payment Total</span><span>- ${peso.format(reviewPaymentTotal)}</span></div>
                            <div class="border-t border-dashed border-slate-300 pt-2 flex justify-between font-black text-slate-900 text-sm"><span>Remaining</span><span>${peso.format(Math.max(0, reviewTotals.finalTotal - reviewPaymentTotal))}</span></div>
                            ${reviewTotals.excessiveAmount > 0 ? `<div class="flex justify-between font-bold text-orange-600"><span>Excessive</span><span>${peso.format(reviewTotals.excessiveAmount)}</span></div>` : ''}
                        </div>
                    </div>`;
                const targetContent = contentId || 'pcv-review-content';
                const printFrameId = frameId || 'pcv-review-frame';
                document.getElementById(targetContent).innerHTML = `
                    <div class="rounded-xl border border-slate-200 p-5 bg-white"><h5 class="text-[10px] font-black uppercase tracking-widest text-maroon-800 mb-4">Step 1</h5><dl class="space-y-3 text-xs">${[['Voucher No.', $('pcv-voucher-no').textContent.trim()], ['Supplier', currentSupplier.supplier], ['Ref No.', $('pcv-ref-no').value || '---'], ['Particulars', $('pcv-particulars').value || '---']].map(([l,v]) => `<div><dt class="font-black text-slate-400 uppercase">${escapeHtml(l)}</dt><dd class="font-bold text-slate-800">${escapeHtml(v)}</dd></div>`).join('')}</dl></div>
                    <div class="rounded-xl border border-slate-200 p-5 bg-white lg:col-span-2"><h5 class="text-[10px] font-black uppercase tracking-widest text-maroon-800 mb-4">Print Preview</h5><iframe id="${printFrameId}" class="w-full border border-slate-100 rounded-lg bg-white" style="height: 800px; display: block;"></iframe></div>
                    <div class="rounded-xl border border-slate-200 p-5 bg-white"><h5 class="text-[10px] font-black uppercase tracking-widest text-maroon-800 mb-4">Payment Details</h5><div class="overflow-x-auto"><table class="w-full text-xs"><thead><tr class="text-[10px] font-black uppercase tracking-widest text-slate-400 border-b border-slate-200"><th class="py-2 pr-3 text-left">#</th><th class="py-2 pr-3 text-left">Method</th><th class="py-2 pr-3 text-left">Details</th><th class="py-2 text-right">Credit</th></tr></thead><tbody>${paymentReviewRows}</tbody></table></div><div class="mt-3 pt-3 border-t border-slate-200 space-y-2"><div class="flex justify-between text-sm font-black text-amber-700"><span>Invoice Amount</span><span>${peso.format(reviewTotals.finalTotal)}</span></div><div class="flex justify-between text-sm font-black text-emerald-700"><span>Payment Total</span><span>${peso.format(reviewPaymentTotal)}</span></div><div class="flex justify-between text-sm font-black text-amber-800"><span>Remaining</span><span>${peso.format(Math.max(0, reviewTotals.finalTotal - reviewPaymentTotal))}</span></div></div></div>
                    ${suddenReturnCard}
                    ${totalsCard}`;
                generateReviewPrintPreview(selected, totalBeforeGlob, globalDisc, globalDiscAmount, printFrameId);
            }
            function generateReviewPrintPreview(selected, totalBeforeGlob, globalDisc, globalDiscAmount, frameId) {
                console.log('=== generateReviewPrintPreview START ===');
                console.log('selected:', selected);
                console.log('totalBeforeGlob:', totalBeforeGlob);
                console.log('frameId:', frameId);
                
                // CRITICAL FIX: Use setTimeout to ensure iframe is rendered before writing to it
                setTimeout(() => {
                const grandTotal = (selected || []).reduce((sum, inv) => sum + invoiceCalculationAmount(inv), 0);
                const previewGlobalDiscAmount = grandTotal * Number(globalDisc || 0) / 100;
                const previewTotals = calculateDisplayTotals(grandTotal, previewGlobalDiscAmount, selectedSuddenReturns, additionalDiscount);
                const afterDiscAmount = Math.max(0, grandTotal - previewTotals.additionalDiscount);
                const suddenReturnSection = buildSuddenReturnPrintSection(selectedSuddenReturns);
                
                // Payment Details Section - multiple payment rows, negative credits included.
                const previewPayments = voucherPaymentDetails();
                const previewPaymentTotal = previewPayments.reduce((sum, payment) => sum + Number(payment.credit_amount || 0), 0);
                const previewNetAfterPayment = Math.max(0, previewTotals.finalTotal - previewPaymentTotal);
                let paymentDetailsHtml = `
                    <table style="width: 100%; font-size: 13px; margin-top: 20px; margin-bottom: 6px; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th colspan="6" style="padding: 8px; font-weight: bold; text-align: center; border: 1px solid #000;">PAYMENT DETAILS</th>
                            </tr>
                            <tr>
                                <th style="padding: 6px; border: 1px solid #000;">METHOD</th>
                                <th style="padding: 6px; border: 1px solid #000;">ACCOUNT NO.</th>
                                <th style="padding: 6px; border: 1px solid #000;">BANK NAME</th>
                                <th style="padding: 6px; border: 1px solid #000;">CHECK NO.</th>
                                <th style="padding: 6px; border: 1px solid #000;">DATE</th>
                                <th style="padding: 6px; border: 1px solid #000; text-align: right;">CREDIT AMOUNT</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${previewPayments.map(payment => {
                                const isBank = ['Cheque', 'Bank Transfer'].includes(payment.payment_method);
                                const date = isBank ? (payment.check_date || '---') : (payment.payment_date || '---');
                                return `<tr>
                                    <td style="padding: 6px; border: 1px solid #000;">${escapeHtml(payment.payment_method || '---')}</td>
                                    <td style="padding: 6px; border: 1px solid #000;">${escapeHtml(isBank ? (payment.account_no || '---') : '---')}</td>
                                    <td style="padding: 6px; border: 1px solid #000;">${escapeHtml(isBank ? (payment.bank_name || '---') : '---')}</td>
                                    <td style="padding: 6px; border: 1px solid #000;">${escapeHtml(isBank ? (payment.check_no || '---') : '---')}</td>
                                    <td style="padding: 6px; border: 1px solid #000;">${escapeHtml(date)}</td>
                                    <td style="padding: 6px; border: 1px solid #000; text-align: right; font-weight: bold;">${peso.format(Number(payment.credit_amount || 0))}</td>
                                </tr>`;
                            }).join('')}
                        </tbody>
                    </table>
                    `;
                const checkSummaryHtml = buildCheckSummaryPrintSection(previewPayments, showCheckSummaryEnabled());

                const invoiceRows = selected.map((inv) => {
                    const invAmt = invoiceCalculationAmount(inv);
                    const paid = Number(inv.amountPaid || 0);
                    const invoiceReturns = (selectedSuddenReturns || []).filter(sr => String(sr.po_id) === String(inv.sourceId));
                    const hasRegularReturn = !invoiceReturns.length && Number(inv.unusedReturnAmount || 0) > 0;
                    const retAmt = hasRegularReturn ? Number(inv.unusedReturnAmount || 0) : invoiceReturns.reduce((sum, sr) => sum + suddenReturnAmount(sr), 0);
                    const totalRet = hasRegularReturn ? Number(inv.unusedReturnCount || 0) : invoiceReturns.length;
                    const returnNumber = hasRegularReturn ? (inv.returnNumber || '') : invoiceReturns.map(sr => sr.return_number).filter(Boolean).join(', ');
                    const slipNo = hasRegularReturn ? (inv.slipNo || '') : invoiceReturns.map(sr => sr.slip_no).filter(Boolean).join(', ');
                    const returnedItems = hasRegularReturn ? [] : invoiceReturns.flatMap(sr => sr.items || []);
                    const returnedItemsText = hasRegularReturn ? (totalRet > 0 ? String(totalRet) : '---') : (returnedItems.length ? returnedItemsSummary(returnedItems) : '---');
                    const rsDetails = String(inv.rsDetails || '').trim() || (returnedItems.length ? returnedItemsSummary(returnedItems) : '---');
                    const remaining = Number(inv.remainingBalance || 0);
                    const remarks = remaining <= 0.005 ? 'Paid' : (inv.remarks || 'Not Paid');
                    const displayAmt = invAmt > 0 ? invAmt : (Number(inv.unusedReturnAmount || 0) > 0 ? Number(inv.invoiceAmount || 0) : invAmt);
                    return `<tr>
                        <td class="px-2 py-1" style="border: 1px solid #000;">${escapeHtml(inv.invoiceNo)}</td>
                        <td class="px-2 py-1 text-right" style="border: 1px solid #000;">${peso.format(displayAmt)}</td>
                        <td class="px-2 py-1 text-center" style="border: 1px solid #000;">${escapeHtml(slipNo || '---')}</td>
                        <td class="px-2 py-1 text-right" style="border: 1px solid #000;">${retAmt > 0 ? peso.format(retAmt) : '---'}</td>
                        <td class="px-2 py-1 text-left" style="border: 1px solid #000;">${escapeHtml(retAmt > 0 ? returnedItemsText : (totalRet > 0 ? String(totalRet) : '---'))}</td>
                        <td class="px-2 py-1 text-left" style="border: 1px solid #000;">${escapeHtml(rsDetails || '---')}</td>
                        <td class="px-2 py-1 text-center font-bold" style="border: 1px solid #000; ${remaining <= 0.005 ? 'color: #059669;' : ''}">${escapeHtml(remarks)}</td>
                        <td class="px-2 py-1 text-right font-bold" style="border: 1px solid #000;">${peso.format(paid)}</td>
                    </tr>`;
                }).join('');
                const printContent = `
                    <div style="font-family: 'Courier New', monospace; font-size: 13px; width: 800px; margin: 0 auto; padding: 30px 20px;">
                        <div style="text-align: center; margin-bottom: 20px;">
                            <div style="font-size: 20px; font-weight: bold; font-family: 'Arial Black', Arial, sans-serif; letter-spacing: 1px;">W68 AUTO PARTS &amp; SERVICE CENTER</div>
                            <div style="font-size: 15px; font-weight: bold;">48 TIMOTHY ST. MULTINATIONAL VILLAGE PARAN&#209;AQUE CITY</div>
                            <div style="font-size: 15px; font-weight: bold;">Tel. Nos. 8553-9092 / 8829-0480 Mobile No. 0917-3239-605 Mobile/Viber. 0949-8818-468</div>
                            <div style="font-size: 14px; font-weight: bold; margin-top: 18px; letter-spacing: 3px;">CHECK VOUCHER</div>
                        </div>
                        <table style="width: 100%; font-size: 13px; margin-bottom: 15px;">
                            <tr>
                                <td style="width: 50%;"><span style="font-weight: bold;">SUPPLIER:</span> ${escapeHtml(currentSupplier.supplier)}</td>
                                <td style="width: 50%; text-align: right;"><span style="font-weight: bold;">VOUCHER NO.:</span> ${escapeHtml($('pcv-voucher-no').textContent.trim())}</td>
                            </tr>
                            <tr>
                                <td><span style="font-weight: bold;">ADDRESS:</span> ${escapeHtml(currentSupplier.address || '---')}</td>
                                <td style="text-align: right;"><span style="font-weight: bold;">DATE:</span> ${$('pcv-voucher-date').textContent.trim()}</td>
                            </tr>
                        </table>
                        <table style="width: 100%; font-size: 13px; border-collapse: collapse; margin-bottom: 8px;">
                            <thead>
                                <tr style="border-bottom: 1px solid #000; border-top: 1px solid #000;">
                                    <th style="padding: 5px 3px; text-align: left; font-weight: bold; border-left: 1px solid #000; border-right: 1px solid #000;">INVOICE NO.</th>
                                    <th style="padding: 5px 3px; text-align: right; font-weight: bold; border-right: 1px solid #000;">AMOUNT</th>
                                    <th style="padding: 5px 3px; text-align: center; font-weight: bold; border-right: 1px solid #000;">SLIP NO.</th>
                                    <th style="padding: 5px 3px; text-align: right; font-weight: bold; border-right: 1px solid #000;">RETURN AMOUNT</th>
                                    <th style="padding: 5px 3px; text-align: right; font-weight: bold; border-right: 1px solid #000;">RETURNED ITEMS</th>
                                    <th style="padding: 5px 3px; text-align: left; font-weight: bold; border-right: 1px solid #000;">RS DETAILS</th>
                                    <th style="padding: 5px 3px; text-align: center; font-weight: bold; border-right: 1px solid #000;">REMARKS</th>
                                    <th style="padding: 5px 3px; text-align: right; font-weight: bold; border-right: 1px solid #000;">TOTAL AMOUNT</th>
                                </tr>
                            </thead>
                            <tbody>${invoiceRows}</tbody>
                        </table>
                        <div style="border-top: 1px solid #000; margin-bottom: 10px;"></div>
                        <div style="text-align: right; font-size: 13px; font-weight: bold; margin-bottom: 5px;">INVOICE PAID TOTAL: ${peso.format(grandTotal)}</div>
                        ${previewTotals.additionalDiscount > 0 ? `<div style="text-align: right; font-size: 13px; font-weight: bold; color: #2563eb; margin-bottom: 5px;">ADDITIONAL DISCOUNT (${formatPercent(previewTotals.additionalDiscountPercent)}%): - ${peso.format(previewTotals.additionalDiscount)}</div>` : ''}
                        <div style="text-align: right; font-size: 13px; font-weight: bold; margin-bottom: 5px;">TOTAL AMOUNT: ${peso.format(afterDiscAmount)}</div>
                        ${globalDisc > 0 ? `<div style="text-align: right; font-size: 13px; font-weight: bold; color: #b45309; margin-bottom: 20px;">TOTAL DISCOUNT: - ${peso.format(previewGlobalDiscAmount)} (${globalDisc}%)</div>` : ''}
                        ${paymentDetailsHtml}
                        ${checkSummaryHtml}
                        ${suddenReturnSection}
                        ${previewTotals.suddenReturnsTotal > 0 ? `<div style="text-align: right; font-size: 13px; font-weight: bold; color: #b91c1c; margin-bottom: 5px;">SUDDENLY RETURN: - ${peso.format(previewTotals.suddenReturnsTotal)}</div>` : ''}
                        <div style="text-align: right; font-size: 13px; font-weight: bold; margin-bottom: 30px;">NET AMOUNT: ${peso.format(previewNetAfterPayment)}</div>
                        ${previewTotals.excessiveAmount > 0 ? `<div style="text-align: right; font-size: 13px; font-weight: bold; color: #ea580c; margin-bottom: 30px;">EXCESSIVE: ${peso.format(previewTotals.excessiveAmount)}</div>` : ''}
                        <div style="border-top: 3px solid #000; width: 100%; margin: 50px 0 15px 0;"></div>
                        <div style="display: flex; justify-content: space-between; font-size: 13px; margin-top: 60px;">
                            <div style="text-align: center; width: 22%;"><div style="border-top: 1px solid #000; padding-top: 5px;">PREPARED BY</div></div>
                            <div style="text-align: center; width: 22%;"><div style="border-top: 1px solid #000; padding-top: 5px;">CHECKED BY</div></div>
                            <div style="text-align: center; width: 22%;"><div style="border-top: 1px solid #000; padding-top: 5px;">APPROVED BY</div></div>
                            <div style="text-align: center; width: 22%;"><div style="border-top: 1px solid #000; padding-top: 5px;">RECEIVED BY</div></div>
                        </div>
                    </div>`;
                const targetFrame = frameId || 'pcv-review-frame';
                console.log('targetFrame ID:', targetFrame);
                const frame = $(targetFrame);
                console.log('frame element:', frame);
                
                if (!frame) {
                    console.error('Frame element not found! ID:', targetFrame);
                    alert('Print preview frame not found. Please try again.');
                    return;
                }
                
                // FORCE VISIBILITY - Remove hidden class and set styles
                frame.classList.remove('hidden');
                frame.style.display = 'block';
                frame.style.visibility = 'visible';
                frame.style.opacity = '1';
                
                if (frame) {
                    console.log('Writing to iframe...');
                    console.log('printContent length:', printContent.length);
                    const doc = frame.contentDocument || frame.contentWindow.document;
                    doc.open();
                    doc.write('<html><head><style>body { margin: 0; padding: 0; } table { border-collapse: collapse; } td, th { padding: 4px 8px; }</style></head><body>' + printContent + '</body></html>');
                    doc.close();
                    console.log('=== generateReviewPrintPreview END - SUCCESS ===');
                }
                }, 100); // 100ms delay to ensure DOM is ready
            }
            function updateStep() {
                const hasReturns = suddenReturns.length > 0;
                document.querySelectorAll('.pcv-step').forEach((s) => s.classList.add('hidden'));
                document.getElementById(`pcv-step-${currentStep}`).classList.remove('hidden');
                
                [1,2,3,4,5].forEach((i) => { 
                    const d = document.getElementById(`pcv-step-dot-${i}`); 
                    if (d) { 
                        d.classList.toggle('active', i === currentStep); 
                        if (!hasReturns && i === 2) {
                            d.classList.toggle('done', currentStep > 2);
                        } else {
                            d.classList.toggle('done', i < currentStep);
                        }
                    } 
                });
                
                // Update progress line
                const line = $('pcv-step-line-active');
                if (line) {
                    let progress = 0;
                    if (hasReturns) {
                        progress = (currentStep - 1) / 4;
                    } else {
                        if (currentStep === 1) progress = 0;
                        else if (currentStep === 3) progress = 1/3;
                        else if (currentStep === 4) progress = 2/3;
                        else if (currentStep === 5) progress = 1;
                    }
                    line.style.transform = `scaleX(${progress})`;
                }
                // Ensure step 2 label matches new order
                const dot2Label = document.querySelector('#pcv-stepper-item-2 .pcv-step-label');
                if (dot2Label) dot2Label.textContent = 'Sudden Return';
                
                $('pcv-back-btn').classList.toggle('hidden', currentStep === 1);
                const onInvoiceStep = currentStep === 3;
                $('pcv-additional-discount').classList.toggle('hidden', !onInvoiceStep);
                if (onInvoiceStep) renderInvoices();
                
                // Show Next button on all steps except step 5 (review)
                $('pcv-next-btn').classList.toggle('hidden', currentStep === 5);
                
                // Show Print and Proceed buttons only on step 5 (review)
                $('pcv-print-btn').classList.toggle('hidden', currentStep !== 5);
                $('pcv-final-btn').classList.toggle('hidden', currentStep !== 5);
                
                // Render review on step 5
                if (currentStep === 5) {
                    renderReview();
                }
                
                // Handle step 4 - always just payment details, no review merged
                if (currentStep === 4) {
                    const step4Review = $('pcv-step-4-review');
                    if (step4Review) {
                        step4Review.classList.add('hidden'); // Never show review in step 4
                    }
                }
                
                if (currentStep === 1 && map) setTimeout(() => map.invalidateSize(), 100); 
                if (window.lucide) window.lucide.createIcons();
                
                const dot2 = $('pcv-step-dot-2');
                if (dot2) { dot2.classList.toggle('pcv-gold-blink', hasReturns && currentStep !== 2 && currentStep !== 4 && currentStep !== 5); }
            }
            async function loadSuppliers(invoiceSearch = globalInvoiceSearch) {
                const tbody = $('pcv-main-tbody');
                if (tbody) tbody.innerHTML = '<tr><td colspan="5" class="px-5 py-12 text-center text-sm font-bold text-slate-400">Loading supplier payables...</td></tr>';
                try {
                    const params = new URLSearchParams();
                    const invoice = String(invoiceSearch || '').trim();
                    if (invoice) params.set('invoice', invoice);
                    const dataUrl = params.toString() ? `${routes().data}?${params.toString()}` : routes().data;
                    const res = await fetch(dataUrl);
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Unable to load supplier payables.');
                    suppliers = data.suppliers || [];

                    updateDashboardCounts();
                    updateDashboardCards();
                    renderMain();
                } catch (error) {
                    if (tbody) tbody.innerHTML = `<tr><td colspan="5" class="px-5 py-12 text-center text-sm font-bold text-red-500">${escapeHtml(error.message)}</td></tr>`;
                }
            }
            async function openModal(id) {
                currentSupplier = suppliers.find((s) => Number(s.id) === Number(id)); if (!currentSupplier) return;
                currentStep = 1; invoicePage = 1; suddenReturns = []; selectedSuddenReturns = [];
                pcvToggleStep3Stepper(false);
                currentSupplier.invoices.forEach((i) => { i.selected = false; i.discount1 = 0; i.discount2 = 0; });
                $('pcv-proceed-modal').classList.remove('hidden'); $('pcv-proceed-modal').classList.add('flex');
                $('pcv-modal-supplier-title').textContent = currentSupplier.supplier;
                $('pcv-additional-discount').value = 0;
                additionalDiscount = 0;
                $('pcv-invoice-tbody').innerHTML = '<tr><td colspan="14" class="px-4 py-10 text-center text-xs font-bold text-slate-400">Loading supplier invoices...</td></tr>';
                try {
                    const res = await fetch(`${routes().supplier}/${id}`);
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Unable to load supplier details.');
                    currentSupplier = {
                        ...currentSupplier,
                        ...data.supplier,
                        supplier: data.supplier.supplier,
                        invoices: (data.invoices || []).map((invoice) => ({ ...invoice, userPaidAmount: 0, amountPaid: 0, discount1: 0, discount2: 0, rsDetails: invoice.rsDetails || '', returnNumber: invoice.returnNumber || '', selected: false }))
                    };
                    $('pcv-voucher-no').textContent = data.voucher_no || `PCV-${String(id).padStart(4, '0')}`;
                    fetchSuddenReturns(id);
                } catch (error) {
                    $('pcv-invoice-tbody').innerHTML = `<tr><td colspan="14" class="px-4 py-10 text-center text-xs font-bold text-red-500">${escapeHtml(error.message)}</td></tr>`;
                }
                $('pcv-modal-supplier-title').textContent = currentSupplier.supplier; $('pcv-voucher-date').textContent = today; $('pcv-supplier-name').textContent = currentSupplier.supplier; $('pcv-supplier-contact').textContent = currentSupplier.contact || '---'; $('pcv-supplier-person').textContent = currentSupplier.contactPerson || '---'; $('pcv-supplier-address').textContent = currentSupplier.address || '---';
                $('pcv-ref-no').value = ''; $('pcv-particulars').value = ''; $('pcv-payment-date').value = today; $('pcv-check-date').value = today; if ($('pcv-gcash-reference-no')) $('pcv-gcash-reference-no').value = ''; $('pcv-cash-credit').value = ''; $('pcv-bank-credit').value = '';
                additionalPayments = [];
                if ($('pcv-show-check-summary')) $('pcv-show-check-summary').checked = false;
                renderAdditionalPayments();
                updatePaymentTotal();
                updateStep(); renderInvoices(); renderMap(currentSupplier.address); if (window.lucide) window.lucide.createIcons();
            }
            window.pcvPrintPreview = (frameId) => {
                const frame = $(frameId);
                if (!frame) {
                    alert('Print preview not available');
                    return;
                }
                try {
                    const frameWindow = frame.contentWindow || frame;
                    frameWindow.focus();
                    frameWindow.print();
                } catch (error) {
                    console.error('Print error:', error);
                    alert('Unable to print. Please try again.');
                }
            };
            window.pcvChangeStep = (delta) => {
                let next = currentStep + delta;
                const hasReturns = suddenReturns.length > 0;
                const maxStep = 5;
                
                if (delta > 0) {
                    if (hasReturns) {
                        next = currentStep + 1;
                    } else {
                        if (currentStep === 1) {
                            next = 3;
                        } else {
                            next = currentStep + 1;
                        }
                    }
                } else {
                    if (hasReturns) {
                        next = currentStep - 1;
                    } else {
                        if (currentStep === 3) {
                            next = 1;
                        } else {
                            next = currentStep - 1;
                        }
                    }
                }
                
                currentStep = Math.min(maxStep, Math.max(1, next));
                updateStep();
            };
            window.pcvMarkSelectedPaid = () => {
                currentSupplier.invoices.filter((i) => i.selected).forEach((i) => {
                    const netDue = calcDiscountedAmount(i.amountDue, i.discount1);
                    i.userPaidAmount = netDue;
                    i.amountPaid = netDue;
                    i.remarks = i.userPaidAmount >= netDue ? 'Paid' : i.userPaidAmount > 0 ? 'Partial Paid' : 'Not Paid';
                });
                renderInvoices();
            };
            function selectedVoucherInvoices() {
                recalculateVoucherDeductions();
                const selectedReturnPoIds = new Set((selectedSuddenReturns || []).map(sr => String(sr.po_id)));
                return (currentSupplier?.invoices || []).filter((i) => i.selected && (userPaidAmount(i) > 0 || Number(i.unusedReturnAmount || 0) > 0 || selectedReturnPoIds.has(String(i.sourceId))));
            }
            function voucherPayload(invoices) {
                const bank = ['Cheque','Bank Transfer'].includes(paymentMethod());
                const globDisc = getGlobalDiscount();
                const totalPaid = invoices.reduce((s, inv) => s + Number(inv.amountPaid || 0), 0);
                // Use window-level authoritative source for selected sudden returns
                const srForPayload = window.pcv_selectedSR || selectedSuddenReturns || [];
                const invoiceTotal = invoices.reduce((s, inv) => s + invoiceCalculationAmount(inv, srForPayload), 0);
                const globDiscAmt = invoiceTotal * globDisc / 100;
                const payloadTotals = calculateDisplayTotals(invoiceTotal, globDiscAmt, srForPayload, additionalDiscount);
                
                const payload = {
                    supplier_id: currentSupplier.id,
                    supplier_name: currentSupplier.supplier,
                    voucher_no: $('pcv-voucher-no').textContent.trim(),
                    voucher_date: $('pcv-voucher-date').textContent.trim(),
                    reference_no: $('pcv-ref-no').value,
                    particulars: $('pcv-particulars').value,
                    payment_method: paymentMethod(),
                    total_paid: totalPaid,
                    global_discount: globDisc,
                    global_discount_amount: globDiscAmt,
                    additional_discount: additionalDiscount,
                    additional_discount_amount: payloadTotals.additionalDiscount,
                    show_check_summary: showCheckSummaryEnabled(),
                    invoices: invoices.map(inv => {
                        const invoiceReturns = srForPayload.filter(sr => String(sr.po_id) === String(inv.sourceId));
                        const hasSelectedReturns = invoiceReturns.length > 0;
                        const selectedReturnAmount = invoiceReturns.reduce((sum, sr) => sum + suddenReturnAmount(sr), 0);
                        const returnedItems = invoiceReturns.flatMap(sr => sr.items || []);
                        const selectedReturnDetails = String(inv.rsDetails || '').trim()
                            || (returnedItems.length ? returnedItemsSummary(returnedItems) : '');
                        return {
                            ...inv,
                            amountDue: invoiceCalculationAmount(inv, srForPayload),
                            returnAmount: hasSelectedReturns ? selectedReturnAmount : Number(inv.unusedReturnAmount ?? inv.returnAmount ?? 0),
                            totalReturns: hasSelectedReturns ? invoiceReturns.length : Number(inv.unusedReturnCount ?? inv.totalReturns ?? 0),
                            returnNumber: hasSelectedReturns ? invoiceReturns.map(sr => sr.return_number).filter(Boolean).join(', ') : String(inv.returnNumber || ''),
                            slipNo: hasSelectedReturns ? invoiceReturns.map(sr => sr.slip_no).filter(Boolean).join(', ') : String(inv.slipNo || ''),
                            rsDetails: selectedReturnDetails,
                            suddenReturnDeduction: inv.suddenReturnDeduction || 0,
                            additionalDiscountDeduction: inv.additionalDiscountDeduction || 0,
                            finalAmountDue: inv.finalAmountDue || 0,
                            remainingBalance: inv.remainingBalance || 0
                        };
                    }),
                    sudden_returns: srForPayload.map((sr) => ({ po_id: sr.po_id, return_number: sr.return_number, total_amount: sr.total_amount, remarks: sr.remarks || '' })),
                    payments: voucherPaymentDetails(),
                    // Legacy single-payment payload kept for backward compatibility.
                    payment: primaryPaymentDetail(),
                    payment_total: paymentDetailsTotal()
                };
                
                console.log('=== VOUCHER PAYLOAD ===');
                console.log('Selected Sudden Returns:', selectedSuddenReturns);
                console.log('Payload sudden_returns:', payload.sudden_returns);
                console.log('Full Payload:', payload);
                
                return payload;
            }
            function pcvToggleModal(id, show) {
                const modal = $(id);
                if (!modal) return;
                modal.classList.toggle('hidden', !show);
                modal.classList.toggle('flex', show);
                if (show && window.lucide) window.lucide.createIcons();
            }
            window.pcvCloseReturnModal = () => pcvToggleModal('pcv-return-modal', false);
            let returnsCache = {};
            async function openReturnModal(poId) {
                pcvToggleModal('pcv-return-modal', true);
                $('pcv-return-supplier').textContent = currentSupplier?.supplier || 'Supplier';
                $('pcv-return-list').innerHTML = '<div class="text-xs font-bold text-slate-400 py-4 text-center">Loading...</div>';
                $('pcv-return-detail').classList.add('hidden');
                $('pcv-return-detail-placeholder').classList.remove('hidden');
                try {
                    if (!returnsCache[poId]) {
                        const res = await fetch(`${routes().returns}/${poId}`);
                        const data = await res.json();
                        if (!data.success) throw new Error(data.message || 'Unable to load return data.');
                        returnsCache[poId] = data;
                    }
                    const data = returnsCache[poId];
                    const returns = data.returns || [];
                    if (!returns.length) {
                        $('pcv-return-list').innerHTML = '<div class="text-xs font-bold text-slate-400 py-4 text-center">No returns found.</div>';
                        return;
                    }
                    $('pcv-return-list').innerHTML = returns.map((r, idx) => `
                        <div data-ret-idx="${idx}" class="bg-white border border-slate-200 rounded-xl p-4 cursor-pointer hover:border-maroon-300 hover:shadow-sm transition-all ${idx === 0 ? 'ring-2 ring-maroon-900 border-maroon-900' : ''}">
                            <div class="text-xs font-black text-slate-800">${escapeHtml(r.return_number)}</div>
                            <div class="text-[10px] font-bold text-slate-400 mt-1">${r.date || '---'} &middot; ${peso.format(r.total_amount)}</div>
                        </div>
                    `).join('');
                    document.querySelectorAll('#pcv-return-list [data-ret-idx]').forEach((el) => el.addEventListener('click', () => {
                        const idx = Number(el.dataset.retIdx);
                        document.querySelectorAll('#pcv-return-list [data-ret-idx]').forEach((e) => { e.classList.remove('ring-2', 'ring-maroon-900', 'border-maroon-900'); });
                        el.classList.add('ring-2', 'ring-maroon-900', 'border-maroon-900');
                        showReturnDetail(returns[idx]);
                    }));
                    showReturnDetail(returns[0]);
                } catch (error) {
                    $('pcv-return-list').innerHTML = `<div class="text-xs font-bold text-red-500 py-4 text-center">${escapeHtml(error.message)}</div>`;
                }
            }
            function showReturnDetail(ret) {
                $('pcv-return-detail').classList.remove('hidden');
                $('pcv-return-detail-placeholder').classList.add('hidden');
                $('pcv-ret-no').textContent = ret.return_number || '---';
                $('pcv-ret-invoice').textContent = ret.invoice_no || ret.po_number || '---';
                $('pcv-ret-total').textContent = peso.format(ret.total_amount || 0);
                $('pcv-ret-remarks').textContent = ret.remarks || '---';
                const items = ret.items || [];
                $('pcv-return-items-tbody').innerHTML = items.length
                    ? items.map((item) => `<tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-bold text-slate-700">${escapeHtml(item.product_code || '---')}</td>
                        <td class="px-4 py-3 text-slate-500">${escapeHtml(item.application || '---')}</td>
                        <td class="px-4 py-3 text-slate-500">${escapeHtml(item.description || item.product_name || '---')}</td>
                        <td class="px-4 py-3 text-right font-black text-slate-700">${Number(item.quantity || 0).toLocaleString('en-PH')}</td>
                        <td class="px-4 py-3 text-right font-black text-slate-700">${peso.format(Number(item.amount || item.subtotal || 0))}</td>
                    </tr>`).join('')
                    : '<tr><td colspan="5" class="px-4 py-8 text-center text-xs font-bold text-slate-400">No items found.</td></tr>';
                if (window.lucide) window.lucide.createIcons();
            }
            function updateSuddenSelectedTotal() {
                const total = selectedSuddenReturns.reduce((s, r) => s + Number(r.total_amount || 0), 0);
                $('pcv-sudden-selected-total').textContent = peso.format(total);
            }
            function pcvToggleStep3Stepper(show) {
                const grid = $('pcv-stepper-grid');
                const item = $('pcv-stepper-item-2');
                if (!grid || !item) return;
                if (show) {
                    item.classList.remove('hidden');
                    grid.classList.replace('grid-cols-4', 'grid-cols-5');
                } else {
                    item.classList.add('hidden');
                    grid.classList.replace('grid-cols-5', 'grid-cols-4');
                }
            }
            async function fetchSuddenReturns(supplierId) {
                try {
                    // Get PO IDs from selected invoices
                    const selectedPoIds = selectedInvoicePoIds();
                    
                    // Build URL with po_ids parameter
                    const params = new URLSearchParams();
                    selectedPoIds.forEach(id => params.append('po_ids[]', id));
                    const url = `${routes().suddenReturns}/${supplierId}${selectedPoIds.length ? '?' + params.toString() : ''}`;
                    
                    const res = await fetch(url);
                    const data = await res.json();
                    if (!data.success) return;
                    suddenReturns = data.returns || [];
                    const emptyEl = $('pcv-step-2-empty');
                    const contentEl = $('pcv-step-2-content');
                    if (!suddenReturns.length) {
                        if (emptyEl) emptyEl.classList.remove('hidden');
                        if (contentEl) contentEl.classList.add('hidden');
                        pcvToggleStep3Stepper(false);
                        return;
                    }
                    if (emptyEl) emptyEl.classList.add('hidden');
                    if (contentEl) contentEl.classList.remove('hidden');
                    pcvToggleStep3Stepper(true);
                    const tbody = $('pcv-sudden-returns-tbody');
                    tbody.innerHTML = suddenReturns.map((r, idx) => `
                        <tr class="hover:bg-amber-50">
                            <td class="px-4 py-3 text-center"><input type="checkbox" data-sr-check="${idx}" class="accent-amber-700"></td>
                            <td class="px-4 py-3 font-black text-slate-700">${escapeHtml(r.return_number)}</td>
                            <td class="px-4 py-3 font-bold text-slate-700">${escapeHtml(r.slip_no || '---')}</td>
                            <td class="px-4 py-3 font-bold text-slate-600">${escapeHtml(r.invoice_no || r.po_number || '---')}</td>
                            <td class="px-4 py-3 font-semibold text-slate-600 max-w-[260px] whitespace-normal">${escapeHtml(returnedItemsSummary(r.items))}</td>
                            <td class="px-4 py-3 font-semibold text-slate-600">${r.date || '---'}</td>
                            <td class="px-4 py-3 text-right font-black text-rose-600">${peso.format(r.total_amount)}</td>
                            <td class="px-4 py-3"><input type="text" data-sr-remarks="${idx}" class="w-full border border-slate-200 rounded-lg px-2 py-1.5 text-xs font-bold outline-none" placeholder="Enter remarks"></td>
                        </tr>
                    `).join('');
                    $('pcv-sudden-select-all').addEventListener('change', (e) => {
                        const checked = e.target.checked;
                        tbody.querySelectorAll('[data-sr-check]').forEach((cb) => { cb.checked = checked; cb.dispatchEvent(new Event('change')); });
                    });
                    tbody.querySelectorAll('[data-sr-check]').forEach((cb) => cb.addEventListener('change', () => {
                        const idx = Number(cb.dataset.srCheck);
                        const remarksInput = tbody.querySelector(`[data-sr-remarks="${idx}"]`);
                        if (cb.checked) {
                            if (!selectedSuddenReturns.find((r) => r.return_number === suddenReturns[idx].return_number)) {
                                selectedSuddenReturns.push({ ...suddenReturns[idx], remarks: remarksInput ? remarksInput.value : '' });
                            }
                            const invoice = currentSupplier?.invoices?.find((i) => String(i.sourceId) === String(suddenReturns[idx]?.po_id));
                            if (invoice) invoice.selected = true;
                        } else {
                            selectedSuddenReturns = selectedSuddenReturns.filter((r) => r.return_number !== suddenReturns[idx].return_number);
                        }
                        window.pcv_selectedSR = JSON.parse(JSON.stringify(selectedSuddenReturns));
                        updateSuddenSelectedTotal();
                        recalculateVoucherDeductions();
                        renderInvoices();
                        if (typeof renderStep2Summary === 'function') renderStep2Summary();
                    }));
                    tbody.querySelectorAll('[data-sr-remarks]').forEach((input) => input.addEventListener('input', () => {
                        const idx = Number(input.dataset.srRemarks);
                        const sr = selectedSuddenReturns.find((r) => r.return_number === suddenReturns[idx]?.return_number);
                        if (sr) sr.remarks = input.value;
                    }));
                    selectedSuddenReturns = [];
                    updateSuddenSelectedTotal();
                } catch (e) {
                    console.warn('Sudden returns fetch error:', e);
                }
            }
            window.pcvApplySuddenReturns = () => {
                recalculateVoucherDeductions();
                window.pcvChangeStep(1);
            };
            window.pcvSkipSuddenReturns = () => {
                selectedSuddenReturns = [];
                recalculateVoucherDeductions();
                window.pcvChangeStep(1);
            };
            window.pcvCloseConfirmModal = () => pcvToggleModal('pcv-confirm-modal', false);
            window.pcvCloseSuccessModal = () => pcvToggleModal('pcv-success-modal', false);
            window.pcvFinalizeVoucher = () => {
                recalculateVoucherDeductions();
                const invoices = selectedVoucherInvoices();
                if (!invoices.length) { alert('Please select at least one invoice or sudden return.'); return; }
                const selectedPoSet = new Set(invoices.map(inv => String(inv.sourceId)));
                const unmatchedReturn = (selectedSuddenReturns || []).find(sr => !selectedPoSet.has(String(sr.po_id)));
                if (unmatchedReturn) {
                    alert(`Please select the matching invoice for return ${unmatchedReturn.return_number}.`);
                    return;
                }
                const total = invoices.reduce((sum, invoice) => sum + invoiceCalculationAmount(invoice), 0);
                const globalDisc = getGlobalDiscount();
                const globalDiscAmount = total * globalDisc / 100;
                const confirmTotals = calculateDisplayTotals(total, globalDiscAmount, selectedSuddenReturns, additionalDiscount);
                const deductionParts = [`${peso.format(total)} checked paid total`];
                if (selectedSuddenReturns.length) deductionParts.push(`- ${peso.format(selectedSuddenReturns.reduce((sum, sr) => sum + suddenReturnAmount(sr), 0))} sudden return`);
                if (confirmTotals.additionalDiscount > 0) deductionParts.push(`- ${peso.format(confirmTotals.additionalDiscount)} addl disc. (${formatPercent(confirmTotals.additionalDiscountPercent)}%)`);
                if (globalDiscAmount > 0) deductionParts.push(`- ${peso.format(globalDiscAmount)} ${globalDisc}% disc.`);
                if (window.editingVoucherId) {
                    // Edit mode
                    $('pcv-confirm-message').textContent = `Save changes to ${$('pcv-voucher-no').textContent.trim()} for ${currentSupplier.supplier} netting ${peso.format(confirmTotals.finalTotal)} (${deductionParts.join(' ')})? This will update the existing voucher.`;
                    pcvToggleModal('pcv-confirm-modal', true);
                    // Replace confirm button's onclick to call edit submit instead of new voucher submit
                    const submitBtn = $('pcv-confirm-submit-btn');
                    if (submitBtn) {
                        // Save original onclick to restore later
                        if (!window._pcvOriginalConfirmOnClick) {
                            window._pcvOriginalConfirmOnClick = submitBtn.getAttribute('onclick');
                        }
                        submitBtn.setAttribute('onclick', 'window.pcvSubmitEditedVoucher()');
                    }
                } else {
                    // New voucher mode - restore original onclick if saved
                    const submitBtn = $('pcv-confirm-submit-btn');
                    if (submitBtn && window._pcvOriginalConfirmOnClick) {
                        submitBtn.setAttribute('onclick', window._pcvOriginalConfirmOnClick);
                    }
                    $('pcv-confirm-message').textContent = `Proceed with ${$('pcv-voucher-no').textContent.trim()} for ${currentSupplier.supplier} netting ${peso.format(confirmTotals.finalTotal)} (${deductionParts.join(' ')})? This will post the voucher to accounting.`;
                    pcvToggleModal('pcv-confirm-modal', true);
                }
            };
            window.pcvSubmitVoucher = async () => {
                // If in edit mode, let pcvSubmitEditedVoucher handle it
                if (window.editingVoucherId) { console.log('Edit mode - deferring to pcvSubmitEditedVoucher'); return; }
                const invoices = selectedVoucherInvoices();
                if (!invoices.length) { window.pcvCloseConfirmModal(); alert('Please select at least one invoice and enter an amount paid.'); return; }
                const btn = $('pcv-confirm-submit-btn');
                const oldText = btn?.textContent;
                if (btn) { btn.disabled = true; btn.textContent = 'Posting...'; }
                try {
                    const res = await fetch(routes().process, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                        body: JSON.stringify(voucherPayload(invoices))
                    });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Unable to save voucher.');
                    window.pcvCloseConfirmModal();
                    window.pcvCloseModal();
                    await loadSuppliers();
                    $('pcv-success-message').textContent = `${$('pcv-voucher-no').textContent.trim()} was posted successfully.`;
                    pcvToggleModal('pcv-success-modal', true);
                } catch (error) {
                    window.pcvCloseConfirmModal();
                    alert(error.message);
                } finally {
                    if (btn) { btn.disabled = false; btn.textContent = oldText || 'Confirm'; }
                }
            };
            document.addEventListener('DOMContentLoaded', () => {
                loadSuppliers();
                $('pcv-global-search').addEventListener('input', (event) => {
                    globalInvoiceSearch = String(event.target.value || '').trim();
                    pcvDashboardFilter = 'pending';
                    updateDashboardCards();
                    mainPage = 1;
                    histPage = 1;
                    clearTimeout(invoiceSearchTimer);
                    invoiceSearchTimer = setTimeout(() => {
                        loadSuppliers(globalInvoiceSearch);
                        loadHistory(1);
                    }, 250);
                });
                document.querySelectorAll('[data-main-col]').forEach((i) => i.addEventListener('input', () => { mainFilters[i.dataset.mainCol] = i.value; mainPage = 1; renderMain(); }));
                document.querySelectorAll('[data-invoice-col]').forEach((i) => i.addEventListener('input', () => { invoiceFilters[i.dataset.invoiceCol] = i.value; invoicePage = 1; renderInvoices(); }));
                document.querySelectorAll('[data-print-col]').forEach((i) => i.addEventListener('input', () => { printFilters[i.dataset.printCol] = i.value; printPage = 1; renderPrintTable(); }));
                $('pcv-select-all-invoices').addEventListener('change', (e) => {
                    const checked = e.target.checked;
                    const rows = filteredInvoices();
                    rows.forEach((i) => {
                        i.selected = checked;
                        if (!checked) {
                            i.userPaidAmount = 0;
                            i.amountPaid = 0;
                        }
                    });
                    if (checked) {
                        recalculateVoucherDeductions();
                        rows.forEach((i) => {
                            const calculatedRemaining = payableAmountAfterReturns(i);
                            i.userPaidAmount = calculatedRemaining;
                            i.amountPaid = calculatedRemaining;
                        });
                    }
                    renderInvoices();
                    if (currentSupplier?.id) fetchSuddenReturns(currentSupplier.id);
                });
                $('pcv-additional-discount').addEventListener('input', () => renderInvoices());
                document.querySelectorAll('input[name="pcv-payment-method"]').forEach((i) => i.addEventListener('change', updatePaymentFields));
                $('pcv-bank-credit')?.addEventListener('input', updatePaymentTotal);
                $('pcv-cash-credit')?.addEventListener('input', updatePaymentTotal);
                updatePaymentFields();
                renderAdditionalPayments();
                updatePaymentTotal();
                if (window.lucide) window.lucide.createIcons();
            });

            // --- Tab switching ---
            const pcvTabPayables = $('pcv-tab-payables');
            const pcvTabHistory = $('pcv-tab-history');
            const pcvPayablesPanel = $('pcv-payables-panel');
            const pcvHistoryPanel = $('pcv-history-panel');
            function switchTab(tab) {
                const isPayables = tab === 'payables';
                pcvPayablesPanel.classList.toggle('hidden', !isPayables);
                pcvHistoryPanel.classList.toggle('hidden', isPayables);
                [pcvTabPayables, pcvTabHistory].forEach((b) => {
                    const active = b.dataset.tab === tab;
                    b.className = 'px-5 py-2.5 text-[10px] font-bold rounded-xl uppercase tracking-widest transition-all flex items-center gap-2' +
                        (active
                            ? ' bg-yellow-400 text-maroon-900 shadow-sm hover:bg-yellow-300'
                            : ' bg-maroon-900 text-yellow-400 hover:bg-maroon-800');
                });
                if (!isPayables) loadHistory();
            }
            pcvTabPayables.dataset.tab = 'payables';
            pcvTabHistory.dataset.tab = 'history';
            pcvTabPayables.addEventListener('click', () => switchTab('payables'));
            pcvTabHistory.addEventListener('click', () => switchTab('history'));

            // --- PCV History (one row per voucher) ---
            let vouchers = [], histPage = 1, histTotal = 0, histFrom = 0, histTo = 0;
            const histPerPage = 50, histFilters = {};
            let histSearchTimer = null;

            async function loadHistory(page = 1) {
                const tbody = $('pcv-history-tbody');
                if (!tbody) return;
                histPage = Math.max(1, Number(page || 1));
                tbody.innerHTML = '<tr><td colspan="8" class="px-5 py-12 text-center text-sm font-bold text-slate-400">Loading PCV history...</td></tr>';
                try {
                    const params = new URLSearchParams({ page: String(histPage), per_page: String(histPerPage) });
                    if (globalInvoiceSearch) params.set('invoice', globalInvoiceSearch);
                    Object.entries(histFilters).forEach(([key, value]) => {
                        if (String(value || '').trim() !== '') params.set(key, String(value).trim());
                    });
                    const res = await fetch(`${routes().history}?${params.toString()}`);
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Unable to load PCV history.');
                    vouchers = data.rows || [];
                    histPage = Number(data.page || 1);
                    histTotal = Number(data.total || 0);
                    histFrom = Number(data.from || 0);
                    histTo = Number(data.to || 0);
                    renderHistory();
                } catch (error) {
                    tbody.innerHTML = `<tr><td colspan="8" class="px-5 py-12 text-center text-sm font-bold text-red-500">${escapeHtml(error.message)}</td></tr>`;
                    histTotal = 0; histFrom = 0; histTo = 0;
                    renderHistoryPagination();
                }
            }

            function renderHistoryPagination() {
                $('pcv-history-from').textContent = histFrom;
                $('pcv-history-to').textContent = histTo;
                $('pcv-history-total').textContent = histTotal;
                paginate('pcv-history-pagination', histTotal, histPage, (page) => loadHistory(page));
            }

            function renderHistory() {
                const tbody = $('pcv-history-tbody');
                const statusClass = (status) => status === 'Paid'
                    ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                    : status === 'Partial'
                        ? 'bg-amber-50 text-amber-700 border-amber-200'
                        : 'bg-rose-50 text-rose-700 border-rose-200';

                tbody.innerHTML = vouchers.map((v) => `
                    <tr data-history-row="${v.id}" class="cursor-pointer hover:bg-maroon-50 transition-colors" title="Click to view PCV details">
                        <td class="px-4 py-4 font-black text-maroon-700 whitespace-nowrap">${escapeHtml(v.pcv || v.voucher_no)}</td>
                        <td class="px-4 py-4 font-black text-slate-800 min-w-[260px]">${escapeHtml(v.supplier_name)}</td>
                        <td class="px-4 py-4 text-right font-black text-slate-700">${peso.format(Number(v.invoice_amount || 0))}</td>
                        <td class="px-4 py-4 text-right font-black ${Number(v.return_total || 0) > 0 ? 'text-rose-600' : 'text-slate-500'}">${peso.format(Number(v.return_total || 0))}</td>
                        <td class="px-4 py-4 text-right font-black text-emerald-700">${peso.format(Number(v.amount_paid || 0))}</td>
                        <td class="px-4 py-4 text-right font-black ${Number(v.remaining || 0) > 0 ? 'text-amber-700' : 'text-slate-500'}">${peso.format(Number(v.remaining || 0))}</td>
                        <td class="px-4 py-4 text-center"><span class="inline-flex px-3 py-1 rounded-full border text-[9px] font-black uppercase tracking-widest ${statusClass(v.status)}">${escapeHtml(v.status || '---')}</span></td>
                        <td class="px-4 py-4 font-bold text-slate-600 whitespace-nowrap">${escapeHtml(v.date || v.voucher_date || '---')}</td>
                    </tr>`).join('') || '<tr><td colspan="8" class="px-5 py-12 text-center text-sm font-bold text-slate-400">No PCV history records found.</td></tr>';

                renderHistoryPagination();

                tbody.querySelectorAll('[data-history-row]').forEach(row => row.addEventListener('click', () => openViewModal(Number(row.dataset.historyRow))));
                if (window.lucide) window.lucide.createIcons();
            }

            window.pcvRefreshHistoryData = () => loadHistory(histPage);

            // --- PCV View Modal: Invoice / Item List / Ledger / Payment Details ---
            let currentViewVoucherId = null;
            let currentViewVoucherNo = '';
            let currentViewLedgerLoaded = false;

            window.pcvCloseViewModal = () => {
                $('pcv-view-modal')?.classList.add('hidden');
                $('pcv-view-modal')?.classList.remove('flex');
                currentViewVoucherId = null;
                currentViewVoucherNo = '';
                currentViewLedgerLoaded = false;
            };

            window.pcvViewPrintCurrent = () => {
                if (!currentViewVoucherId) return;
                generatePrintLayout(currentViewVoucherId);
            };
            window.pcvViewEditCurrent = () => {
                if (!currentViewVoucherId) return;
                const voucherId = currentViewVoucherId;
                window.pcvCloseViewModal();
                if (typeof window.pcvOpenEditModal === 'function') window.pcvOpenEditModal(voucherId);
                else showNotification('Edit functionality is not available. Refresh the page and try again.', 'error');
            };
            window.pcvViewDeleteCurrent = () => {
                if (!currentViewVoucherId) return;
                pcvConfirmDeleteVoucher(currentViewVoucherId, currentViewVoucherNo || 'this PCV');
            };

            window.pcvFilterViewTable = function(tbodyId, filterRowId) {
                const tbody = $(tbodyId), filterRow = $(filterRowId);
                if (!tbody || !filterRow) return;
                const filters = Array.from(filterRow.querySelectorAll('input[data-col]')).map(input => tv(input.value));
                Array.from(tbody.querySelectorAll('tr')).forEach(row => {
                    if (row.dataset.loading === '1') return;
                    const cells = Array.from(row.children);
                    const visible = filters.every((filter, index) => !filter || tv(cells[index]?.textContent || '').includes(filter));
                    row.classList.toggle('hidden', !visible);
                });
            };

            window.pcvBackToInvoiceList = function() {
                $('pcv-view-item-list')?.classList.add('hidden');
                $('pcv-view-invoice-list')?.classList.remove('hidden');
                if (window.lucide) window.lucide.createIcons();
            };

            window.pcvSwitchViewTab = function(tab) {
                const invoice = tab !== 'ledger';
                $('pcv-view-panel-invoice')?.classList.toggle('hidden', !invoice);
                $('pcv-view-panel-ledger')?.classList.toggle('hidden', invoice);
                const invoiceBtn = $('pcv-view-tab-invoice');
                const ledgerBtn = $('pcv-view-tab-ledger');
                if (invoiceBtn) invoiceBtn.className = `px-5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest ${invoice ? 'bg-yellow-400 text-maroon-900' : 'bg-maroon-900 text-yellow-300'}`;
                if (ledgerBtn) ledgerBtn.className = `px-5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest ${!invoice ? 'bg-yellow-400 text-maroon-900' : 'bg-maroon-900 text-yellow-300'}`;
                if (!invoice && currentViewVoucherId && !currentViewLedgerLoaded) loadViewLedger(currentViewVoucherId);
                if (window.lucide) window.lucide.createIcons();
            };

            async function openViewModal(voucherId) {
                currentViewVoucherId = voucherId;
                currentViewLedgerLoaded = false;
                pcvToggleModal('pcv-view-modal', true);
                window.pcvSwitchViewTab('invoice');
                window.pcvBackToInvoiceList();
                $('pcv-view-summary').innerHTML = '<div class="col-span-full py-4 text-center text-xs font-bold text-slate-400">Loading PCV...</div>';
                $('pcv-view-invoice-tbody').innerHTML = '<tr data-loading="1"><td colspan="6" class="px-4 py-8 text-center text-xs font-bold text-slate-400">Loading invoices...</td></tr>';
                $('pcv-view-ledger-tbody').innerHTML = '<tr data-loading="1"><td colspan="9" class="px-4 py-8 text-center text-xs font-bold text-slate-400">Open Ledger tab to load ledger.</td></tr>';
                $('pcv-view-payment-details').innerHTML = '<div class="text-xs font-bold text-slate-400">Loading payment details...</div>';
                try {
                    const res = await fetch(`${routes().historyDetail}/${voucherId}`);
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Unable to load PCV.');
                    const v = data.voucher || {}, invoices = data.invoices || [], payments = data.payments || [], totals = data.totals || {};
                    currentViewVoucherId = Number(v.id || voucherId);
                    currentViewVoucherNo = v.voucher_no || 'PCV';
                    $('pcv-view-voucher-title').textContent = `${currentViewVoucherNo} • ${v.supplier_name || 'Supplier'}`;

                    const remaining = Number(totals.remaining || 0);
                    const status = remaining <= 0.005 ? 'Paid' : Number(totals.amount_paid || 0) > 0 ? 'Partial' : 'Unpaid';
                    const summaryCards = [
                        ['PCV', v.voucher_no || '---', 'text-maroon-800'],
                        ['Invoice Amount', peso.format(Number(totals.invoice_amount || 0)), 'text-slate-800'],
                        ['Return Total', peso.format(Number(totals.return_total || 0)), 'text-rose-700'],
                        ['Amount Paid', peso.format(Number(totals.amount_paid || 0)), 'text-emerald-700'],
                        ['Remaining', peso.format(remaining), remaining > 0 ? 'text-amber-700' : 'text-slate-600'],
                        ['Status / Date', `${status} • ${v.voucher_date || '---'}`, status === 'Paid' ? 'text-emerald-700' : 'text-amber-700'],
                    ];
                    $('pcv-view-summary').innerHTML = summaryCards.map(([label, value, klass]) => `<div class="rounded-xl border border-slate-200 bg-white px-4 py-3"><div class="text-[8px] font-black uppercase tracking-widest text-slate-400">${label}</div><div class="mt-1 text-xs font-black ${klass}">${escapeHtml(value)}</div></div>`).join('');

                    $('pcv-view-invoice-tbody').innerHTML = invoices.map(inv => `
                        <tr class="hover:bg-maroon-50 cursor-pointer transition-colors" data-view-invoice-id="${inv.id}" data-view-invoice-no="${escapeHtml(inv.invoice_no)}">
                            <td class="px-4 py-3 font-black text-maroon-700">${escapeHtml(inv.invoice_no || '---')}</td>
                            <td class="px-4 py-3 font-bold text-slate-600">${escapeHtml(inv.invoice_date || '---')}</td>
                            <td class="px-4 py-3 text-right font-black text-slate-700">${Number(inv.total_returns || 0)}</td>
                            <td class="px-4 py-3 text-right font-black ${Number(inv.return_amount || 0) > 0 ? 'text-rose-600' : 'text-slate-500'}">${peso.format(Number(inv.return_amount || 0))}</td>
                            <td class="px-4 py-3 text-right font-black text-slate-700">${peso.format(Number(inv.amount_due || 0))}</td>
                            <td class="px-4 py-3 text-right font-black text-emerald-700">${peso.format(Number(inv.amount_paid || 0))}</td>
                        </tr>`).join('') || '<tr><td colspan="6" class="px-4 py-8 text-center text-xs font-bold text-slate-400">No invoices attached to this PCV.</td></tr>';
                    $('pcv-view-invoice-tbody').querySelectorAll('[data-view-invoice-id]').forEach(row => row.addEventListener('click', () => loadViewInvoiceItems(voucherId, Number(row.dataset.viewInvoiceId), row.dataset.viewInvoiceNo || 'Invoice')));

                    const paymentTotal = payments.reduce((sum, payment) => sum + Number(payment.credit_amount || 0), 0);
                    $('pcv-view-payment-total').textContent = peso.format(paymentTotal);
                    $('pcv-view-payment-details').innerHTML = payments.map((payment, idx) => {
                        const method = payment.payment_method || '---';
                        const isBank = ['Cheque', 'Bank Transfer'].includes(method);
                        const isGcash = method === 'Gcash';
                        let fields = '';
                        if (isBank) {
                            fields = `<div><span class="text-slate-400">Bank Name:</span> <b>${escapeHtml(payment.bank_name || '---')}</b></div><div><span class="text-slate-400">Account No.:</span> <b>${escapeHtml(payment.account_no || '---')}</b></div><div><span class="text-slate-400">Check No.:</span> <b>${escapeHtml(payment.check_no || '---')}</b></div><div><span class="text-slate-400">Check Date:</span> <b>${escapeHtml(payment.check_date || '---')}</b></div>`;
                        } else if (isGcash) {
                            fields = `<div><span class="text-slate-400">Reference No.:</span> <b>${escapeHtml(payment.reference_no || '---')}</b></div><div><span class="text-slate-400">Date:</span> <b>${escapeHtml(payment.payment_date || '---')}</b></div>`;
                        } else {
                            fields = `<div><span class="text-slate-400">Date:</span> <b>${escapeHtml(payment.payment_date || '---')}</b></div>`;
                        }
                        return `<div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4"><div class="flex items-center justify-between gap-3 mb-3"><span class="text-[10px] font-black uppercase tracking-widest text-maroon-800">Payment ${idx + 1} • ${escapeHtml(method)}</span><span class="font-black ${Number(payment.credit_amount || 0) < 0 ? 'text-rose-600' : 'text-emerald-700'}">${peso.format(Number(payment.credit_amount || 0))}</span></div><div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px] text-slate-600">${fields}</div></div>`;
                    }).join('') || '<div class="text-xs font-bold text-slate-400">No payment-detail rows found.</div>';
                } catch (error) {
                    $('pcv-view-summary').innerHTML = `<div class="col-span-full py-6 text-center text-sm font-bold text-red-500">${escapeHtml(error.message)}</div>`;
                    $('pcv-view-invoice-tbody').innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-xs font-bold text-red-500">Unable to load invoices.</td></tr>';
                }
                if (window.lucide) window.lucide.createIcons();
            }

            async function loadViewInvoiceItems(voucherId, invoiceId, invoiceNo) {
                $('pcv-view-invoice-list')?.classList.add('hidden');
                $('pcv-view-item-list')?.classList.remove('hidden');
                $('pcv-view-item-title').textContent = `${invoiceNo} • Item List`;
                $('pcv-view-item-tbody').innerHTML = '<tr data-loading="1"><td colspan="9" class="px-4 py-8 text-center text-xs font-bold text-slate-400">Loading item list...</td></tr>';
                try {
                    const res = await fetch(`${routes().historyDetail}/${voucherId}/invoice/${invoiceId}/items`);
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Unable to load invoice items.');
                    const items = data.items || [];
                    $('pcv-view-item-tbody').innerHTML = items.map(item => `
                        <tr class="${Number(item.return_qty || 0) > 0 ? 'bg-amber-50' : 'hover:bg-slate-50'}">
                            <td class="px-3 py-3 font-black text-slate-800">${escapeHtml(item.product_code || '---')}</td><td class="px-3 py-3 font-bold text-slate-600">${escapeHtml(item.part_number || '---')}</td><td class="px-3 py-3 text-right font-bold">${Number(item.qty || 0).toLocaleString('en-PH')}</td><td class="px-3 py-3 font-bold text-slate-600">${escapeHtml(item.unit || '---')}</td><td class="px-3 py-3 text-right font-black">${peso.format(Number(item.cost || 0))}</td><td class="px-3 py-3 text-right font-black ${Number(item.return_qty || 0) > 0 ? 'text-rose-600' : 'text-slate-400'}">${Number(item.return_qty || 0).toLocaleString('en-PH')}</td><td class="px-3 py-3 text-right font-black ${Number(item.return_amount || 0) > 0 ? 'text-rose-600' : 'text-slate-400'}">${peso.format(Number(item.return_amount || 0))}</td><td class="px-3 py-3 text-right font-black text-slate-700">${peso.format(Number(item.invoice_amount || 0))}</td><td class="px-3 py-3 text-right font-black text-emerald-700">${peso.format(Number(item.total_amount || 0))}</td>
                        </tr>`).join('') || '<tr><td colspan="9" class="px-4 py-8 text-center text-xs font-bold text-slate-400">No item records found for this invoice.</td></tr>';
                } catch (error) {
                    $('pcv-view-item-tbody').innerHTML = `<tr><td colspan="9" class="px-4 py-8 text-center text-xs font-bold text-red-500">${escapeHtml(error.message)}</td></tr>`;
                }
                if (window.lucide) window.lucide.createIcons();
            }

            async function loadViewLedger(voucherId) {
                const tbody = $('pcv-view-ledger-tbody');
                tbody.innerHTML = '<tr data-loading="1"><td colspan="9" class="px-4 py-8 text-center text-xs font-bold text-slate-400">Loading supplier ledger...</td></tr>';
                try {
                    const res = await fetch(`${routes().historyDetail}/${voucherId}/ledger`);
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Unable to load ledger.');
                    const rows = data.ledger || [];
                    tbody.innerHTML = rows.map(row => {
                        const txnClass = row.transaction === 'PURCHASE RETURN' ? 'text-rose-700' : row.transaction === 'PAID' ? 'text-emerald-700' : 'text-maroon-700';
                        return `<tr class="hover:bg-slate-50"><td class="px-3 py-3 font-black text-slate-700">${escapeHtml(row.invoice_no || '---')}</td><td class="px-3 py-3 font-bold text-slate-700">${escapeHtml(row.product_code || '---')}</td><td class="px-3 py-3 font-bold text-slate-600">${escapeHtml(row.part_number || '---')}</td><td class="px-3 py-3 font-black ${txnClass}">${escapeHtml(row.transaction || '---')}</td><td class="px-3 py-3 font-bold text-slate-600">${escapeHtml(row.transaction_no || '---')}</td><td class="px-3 py-3 text-right font-black text-rose-700">${Number(row.debit || 0) ? peso.format(Number(row.debit || 0)) : '---'}</td><td class="px-3 py-3 text-right font-black text-emerald-700">${Number(row.credit || 0) ? peso.format(Number(row.credit || 0)) : '---'}</td><td class="px-3 py-3 font-semibold text-slate-500">${escapeHtml(row.remarks || '---')}</td><td class="px-3 py-3 font-bold text-slate-600">${escapeHtml(row.date || '---')}</td></tr>`;
                    }).join('') || '<tr><td colspan="9" class="px-4 py-8 text-center text-xs font-bold text-slate-400">No supplier-ledger transactions found.</td></tr>';
                    currentViewLedgerLoaded = true;
                } catch (error) {
                    tbody.innerHTML = `<tr><td colspan="9" class="px-4 py-8 text-center text-xs font-bold text-red-500">${escapeHtml(error.message)}</td></tr>`;
                }
            }

            // --- Print Modal ---
            window.pcvClosePrintModal = () => { $('pcv-print-modal')?.classList.add('hidden'); $('pcv-print-modal')?.classList.remove('flex'); };
            
            let printPage = 1;
            const printPerPage = 50;
            const printFilters = {};
            let printFilter = 'all'; // TASK 5: Filter state for sudden returns
            let supplierVouchers = []; // Store vouchers for the selected supplier

            function filteredPrintVouchers() {
                let filtered = supplierVouchers.filter((v) => Object.entries(printFilters).every(([k, fv]) => {
                    if (!fv) return true;
                    if (k === 'total_amount') {
                        return peso.format(v.total_amount).toLowerCase().includes(tv(fv)) || String(v.total_amount).includes(fv);
                    }
                    return tv(v[k]).includes(tv(fv));
                }));
                
                // TASK 5: Apply sudden return filter
                if (printFilter === 'with') {
                    filtered = filtered.filter(v => v.has_sudden_returns);
                } else if (printFilter === 'without') {
                    filtered = filtered.filter(v => !v.has_sudden_returns);
                }
                
                return filtered;
            }

            function renderPrintTable() {
                const rows = filteredPrintVouchers(), start = (printPage - 1) * printPerPage;
                const tbody = $('pcv-print-table-tbody');
                tbody.innerHTML = rows.slice(start, start + printPerPage).map((v) => {
                    // TASK 4: Gold highlight for vouchers with sudden returns
                    const rowClass = v.has_sudden_returns ? 'bg-amber-100' : '';
                    return `
                    <tr class="cursor-pointer hover:bg-maroon-50 ${rowClass}" data-print-vid="${v.id}">
                        <td class="px-4 py-3 font-black text-maroon-700">${escapeHtml(v.voucher_no)}</td>
                        <td class="px-4 py-3 text-right font-black text-slate-800">${peso.format(v.total_amount)}</td>
                        <td class="px-4 py-3 text-right font-bold text-slate-700">${v.total_invoices}</td>
                        <td class="px-4 py-3 font-bold text-slate-600">${v.voucher_date || '---'}</td>
                    </tr>`;
                }).join('') || '<tr><td colspan="4" class="px-4 py-8 text-center text-xs font-bold text-slate-400">No vouchers found.</td></tr>';
                
                $('pcv-print-from').textContent = rows.length ? start + 1 : 0;
                $('pcv-print-to').textContent = Math.min(start + printPerPage, rows.length);
                $('pcv-print-total').textContent = rows.length;
                paginate('pcv-print-pagination', rows.length, printPage, (p) => { printPage = p; renderPrintTable(); });

                tbody.querySelectorAll('[data-print-vid]').forEach((row) => {
                    row.addEventListener('click', () => generatePrintLayout(Number(row.dataset.printVid)));
                });
            }

            async function openPrintModal(supplierId) {
                pcvToggleModal('pcv-print-modal', true);
                $('pcv-print-table-tbody').innerHTML = '<tr><td colspan="4" class="px-4 py-8 text-center text-xs font-bold text-slate-400">Loading vouchers...</td></tr>';
                
                try {
                    // TASK 3: Fetch vouchers for the specific supplier
                    const res = await fetch(`<?php echo e(url('/special/accounting/payable-cheque-voucher/history/supplier')); ?>/${supplierId}/vouchers`);
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Unable to load vouchers.');
                    supplierVouchers = data.vouchers || [];
                } catch (error) {
                    $('pcv-print-table-tbody').innerHTML = `<tr><td colspan="4" class="px-4 py-8 text-center text-xs font-bold text-red-500">${escapeHtml(error.message)}</td></tr>`;
                    return;
                }
                
                // Reset filters
                document.querySelectorAll('[data-print-col]').forEach((input) => {
                    input.value = '';
                    printFilters[input.dataset.printCol] = '';
                });
                
                // TASK 5: Reset sudden return filter dropdown
                const filterDropdown = $('pcv-print-filter');
                if (filterDropdown) {
                    filterDropdown.value = 'all';
                    printFilter = 'all';
                }
                printPage = 1;
                renderPrintTable();
            }

            // --- Edit Selection Modal ---
            let editSupplierVouchers = []; // Store vouchers for edit selection
            
            async function openEditSelectionModal(supplierId) {
                pcvToggleModal('pcv-print-modal', true);
                $('pcv-print-table-tbody').innerHTML = '<tr><td colspan="4" class="px-4 py-8 text-center text-xs font-bold text-slate-400">Loading vouchers...</td></tr>';
                
                try {
                    // Fetch vouchers for the specific supplier
                    const res = await fetch(`<?php echo e(url('/special/accounting/payable-cheque-voucher/history/supplier')); ?>/${supplierId}/vouchers`);
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Unable to load vouchers.');
                    editSupplierVouchers = data.vouchers || [];
                } catch (error) {
                    $('pcv-print-table-tbody').innerHTML = `<tr><td colspan="4" class="px-4 py-8 text-center text-xs font-bold text-red-500">${escapeHtml(error.message)}</td></tr>`;
                    return;
                }
                
                // Reset filters
                document.querySelectorAll('[data-print-col]').forEach((input) => {
                    input.value = '';
                    printFilters[input.dataset.printCol] = '';
                });
                
                // Reset sudden return filter dropdown
                const filterDropdown = $('pcv-print-filter');
                if (filterDropdown) {
                    filterDropdown.value = 'all';
                    printFilter = 'all';
                }
                
                printPage = 1;
                renderEditSelectionTable();
            }
            
            function renderEditSelectionTable() {
                const rows = editSupplierVouchers.filter((v) => Object.entries(printFilters).every(([k, fv]) => {
                    if (!fv) return true;
                    if (k === 'total_amount') {
                        return peso.format(v.total_amount).toLowerCase().includes(tv(fv)) || String(v.total_amount).includes(fv);
                    }
                    return tv(v[k]).includes(tv(fv));
                }));
                
                // Apply sudden return filter
                let filtered = rows;
                if (printFilter === 'with') {
                    filtered = filtered.filter(v => v.has_sudden_returns);
                } else if (printFilter === 'without') {
                    filtered = filtered.filter(v => !v.has_sudden_returns);
                }
                
                const start = (printPage - 1) * printPerPage;
                const tbody = $('pcv-print-table-tbody');
                tbody.innerHTML = filtered.slice(start, start + printPerPage).map((v) => {
                    const rowClass = v.has_sudden_returns ? 'bg-amber-100' : '';
                    return `
                    <tr class="cursor-pointer hover:bg-maroon-50 ${rowClass}" data-edit-vid="${v.id}">
                        <td class="px-4 py-3 font-black text-maroon-700">${escapeHtml(v.voucher_no)}</td>
                        <td class="px-4 py-3 text-right font-black text-slate-800">${peso.format(v.total_amount)}</td>
                        <td class="px-4 py-3 text-right font-bold text-slate-700">${v.total_invoices}</td>
                        <td class="px-4 py-3 font-bold text-slate-600">${v.voucher_date || '---'}</td>
                    </tr>`;
                }).join('') || '<tr><td colspan="4" class="px-4 py-8 text-center text-xs font-bold text-slate-400">No vouchers found.</td></tr>';
                
                $('pcv-print-from').textContent = filtered.length ? start + 1 : 0;
                $('pcv-print-to').textContent = Math.min(start + printPerPage, filtered.length);
                $('pcv-print-total').textContent = filtered.length;
                paginate('pcv-print-pagination', filtered.length, printPage, (p) => { printPage = p; renderEditSelectionTable(); });

                tbody.querySelectorAll('[data-edit-vid]').forEach((row) => {
                    row.addEventListener('click', () => {
                        const voucherId = Number(row.dataset.editVid);
                        window.pcvClosePrintModal();
                        // Call the edit modal function from external JS file
                        if (typeof window.pcvOpenEditModal === 'function') {
                            window.pcvOpenEditModal(voucherId);
                        } else {
                            alert('Edit functionality not available. Please refresh the page.');
                        }
                    });
                });
                
                if (window.lucide) window.lucide.createIcons();
            }

            // --- Delete Modal ---
            let deleteSupplierVouchers = [];
            let deleteVoucherId = null;
            let deleteVoucherNo = '';
            let deletePage = 1;
            const deletePerPage = 50;

            async function pcvOpenDeleteModal(supplierId) {
                pcvToggleModal('pcv-delete-modal', true);
                $('pcv-delete-table-tbody').innerHTML = '<tr><td colspan="4" class="px-4 py-8 text-center text-xs font-bold text-slate-400">Loading vouchers...</td></tr>';
                try {
                    const res = await fetch(`<?php echo e(url('/special/accounting/payable-cheque-voucher/history/supplier')); ?>/${supplierId}/vouchers`);
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Unable to load vouchers.');
                    deleteSupplierVouchers = data.vouchers || [];
                } catch (error) {
                    $('pcv-delete-table-tbody').innerHTML = `<tr><td colspan="4" class="px-4 py-8 text-center text-xs font-bold text-red-500">${escapeHtml(error.message)}</td></tr>`;
                    return;
                }
                deletePage = 1;
                pcvRenderDeleteTable();
            }

            function pcvRenderDeleteTable() {
                const rows = deleteSupplierVouchers;
                const start = (deletePage - 1) * deletePerPage;
                const tbody = $('pcv-delete-table-tbody');
                tbody.innerHTML = rows.slice(start, start + deletePerPage).map((v) => {
                    return `
                    <tr class="cursor-pointer hover:bg-rose-50" data-delete-vid="${v.id}" data-delete-vno="${escapeHtml(v.voucher_no)}">
                        <td class="px-4 py-3 font-black text-maroon-700">${escapeHtml(v.voucher_no)}</td>
                        <td class="px-4 py-3 text-right font-black text-slate-800">${peso.format(v.total_amount)}</td>
                        <td class="px-4 py-3 text-right font-bold text-slate-700">${v.total_invoices}</td>
                        <td class="px-4 py-3 font-bold text-slate-600">${v.voucher_date || '---'}</td>
                    </tr>`;
                }).join('') || '<tr><td colspan="4" class="px-4 py-8 text-center text-xs font-bold text-slate-400">No vouchers found.</td></tr>';

                $('pcv-delete-from').textContent = rows.length ? start + 1 : 0;
                $('pcv-delete-to').textContent = Math.min(start + deletePerPage, rows.length);
                $('pcv-delete-total').textContent = rows.length;
                paginate('pcv-delete-pagination', rows.length, deletePage, (p) => { deletePage = p; pcvRenderDeleteTable(); });

                tbody.querySelectorAll('[data-delete-vid]').forEach((row) => {
                    row.addEventListener('click', () => {
                        const vid = Number(row.dataset.deleteVid);
                        const vno = row.dataset.deleteVno;
                        pcvConfirmDeleteVoucher(vid, vno);
                    });
                });

                if (window.lucide) window.lucide.createIcons();
            }

            window.pcvCloseDeleteModal = () => {
                $('pcv-delete-modal')?.classList.add('hidden');
                $('pcv-delete-modal')?.classList.remove('flex');
                deleteVoucherId = null;
                deleteVoucherNo = '';
            };

            function pcvConfirmDeleteVoucher(voucherId, voucherNo) {
                deleteVoucherId = voucherId;
                deleteVoucherNo = voucherNo;
                $('pcv-delete-confirm-message').textContent = `Are you sure you want to delete ${voucherNo}? This action will remove this voucher from the PCV history.`;
                pcvToggleModal('pcv-delete-confirm-modal', true);
            }

            window.pcvCloseDeleteConfirmModal = () => {
                pcvToggleModal('pcv-delete-confirm-modal', false);
            };

            window.pcvSubmitDeleteVoucher = async function() {
                if (!deleteVoucherId) return;
                const btn = $('pcv-delete-confirm-submit-btn');
                const oldText = btn?.textContent;
                if (btn) { btn.disabled = true; btn.textContent = 'Deleting...'; }
                try {
                    const res = await fetch(`<?php echo e(url('/special/accounting/payable-cheque-voucher/delete')); ?>/${deleteVoucherId}`, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken(),
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Unable to delete voucher.');
                    // Close confirm modal
                    pcvToggleModal('pcv-delete-confirm-modal', false);
                    // Close delete list/detail modals
                    window.pcvCloseDeleteModal();
                    window.pcvCloseViewModal();
                    // Show success
                    $('pcv-delete-success-message').textContent = data.message || 'PCV deleted successfully.';
                    pcvToggleModal('pcv-delete-success-modal', true);
                } catch (error) {
                    pcvToggleModal('pcv-delete-confirm-modal', false);
                    showNotification(error.message || 'Failed to delete voucher', 'error');
                } finally {
                    if (btn) { btn.disabled = false; btn.textContent = oldText || 'Confirm Delete'; }
                }
            };

            window.pcvCloseDeleteSuccessModal = () => {
                pcvToggleModal('pcv-delete-success-modal', false);
                // Refresh the history table
                if (typeof loadHistory === 'function') {
                    loadHistory();
                } else {
                    window.location.reload();
                }
            };

            async function generatePrintLayout(voucherId) {
                try {
                    const res = await fetch(`${routes().historyDetail}/${voucherId}`);
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message);
                    const v = data.voucher, invoices = data.invoices || [], suddenReturnRows = data.sudden_returns || [];
                    $('print-supplier').textContent = v.supplier_name || '---';
                    $('print-voucher-no').textContent = v.voucher_no || '---';
                    $('print-address').textContent = v.supplier_address || '---';
                    $('print-date').textContent = v.voucher_date || '---';
                    const invoiceRows = invoices.map((inv) => {
                        const invAmt = Number(inv.invoice_amount || 0);
                        const paid = Number(inv.amount_paid || 0);
                        const retAmt = Number(inv.return_amount || 0);
                        const totalRet = Number(inv.total_returns || 0);
                        return `<tr>
                            <td class="px-2 py-1" style="border: 1px solid #000;">${escapeHtml(inv.invoice_no)}</td>
                            <td class="px-2 py-1 text-right" style="border: 1px solid #000;">${peso.format(invAmt)}</td>
                            <td class="px-2 py-1 text-center" style="border: 1px solid #000;">${escapeHtml(inv.slip_no || '---')}</td>
                            <td class="px-2 py-1 text-right" style="border: 1px solid #000;">${retAmt > 0 ? peso.format(retAmt) : '---'}</td>
                            <td class="px-2 py-1 text-left" style="border: 1px solid #000;">${escapeHtml(inv.returned_items || (totalRet > 0 ? String(totalRet) : '---'))}</td>
                            <td class="px-2 py-1 text-left" style="border: 1px solid #000;">${escapeHtml(inv.rs_details || '---')}</td>
                            <td class="px-2 py-1 text-center" style="border: 1px solid #000;">${escapeHtml(inv.remarks || '---')}</td>
                            <td class="px-2 py-1 text-right font-bold" style="border: 1px solid #000;">${peso.format(paid)}</td>
                        </tr>`;
                    }).join('');
                    $('print-invoice-rows').innerHTML = invoiceRows;
                    const grandTotal = invoices.reduce((s, i) => s + calcDiscountedAmount(i.amount_due, i.discount_1), 0);
                    $('print-grand-total').textContent = peso.format(grandTotal);
                    const globalDisc = Number(v.global_discount || 0);
                    const globalDiscAmt = Number(v.global_discount_amount || 0);
                    const printAddlDiscPercent = Number(v.additional_discount || 0);
                    const printAddlDiscAmount = Number(v.additional_discount_amount || 0);
                    const printTotals = calculateDisplayTotals(grandTotal, globalDiscAmt, suddenReturnRows, printAddlDiscPercent, printAddlDiscAmount);
                    const afterDiscAmount = Math.max(0, grandTotal - printTotals.additionalDiscount);
                    const totalDiscountSection = $('print-total-discount-section');
                    const totalDiscountLabel = $('print-discount-label');
                    if (globalDisc > 0 && globalDiscAmt > 0) {
                        if (totalDiscountSection) totalDiscountSection.style.display = 'block';
                        if (totalDiscountLabel) totalDiscountLabel.textContent = `- ${peso.format(globalDiscAmt)} (${globalDisc}%)`;
                    } else {
                        if (totalDiscountSection) totalDiscountSection.style.display = 'none';
                        if (totalDiscountLabel) totalDiscountLabel.textContent = '';
                    }
                    const addlDiscSection = $('print-addl-disc-section');
                    const addlDiscAmountEl = $('print-addl-disc-amount');
                    if (printTotals.additionalDiscount > 0 && addlDiscSection && addlDiscAmountEl) {
                        addlDiscSection.style.display = 'block';
                        addlDiscAmountEl.textContent = `${peso.format(printTotals.additionalDiscount)} (${formatPercent(printTotals.additionalDiscountPercent)}%)`;
                    } else if (addlDiscSection) {
                        addlDiscSection.style.display = 'none';
                    }
                    const totalAmountSection = $('print-total-amount-section');
                    const totalAmountEl = $('print-total-amount');
                    if (totalAmountSection && totalAmountEl) {
                        totalAmountSection.style.display = 'block';
                        totalAmountEl.textContent = peso.format(afterDiscAmount);
                    }
                    // NET AMOUNT is set after Payment Details are loaded so the payment total is deducted.
                    const printSuddenSection = $('print-sudden-returns-section');
                    const printSuddenRows = $('print-sudden-return-rows');
                    const printSuddenlyLine = $('print-suddenly-return-line');
                    const printSuddenlyAmount = $('print-suddenly-return-amount');
                    if (printSuddenSection && printSuddenRows) {
                        if (suddenReturnRows.length) {
                            printSuddenSection.style.display = 'block';
                            const groupedReturns = groupSuddenReturns(suddenReturnRows);
                            printSuddenRows.innerHTML = groupedReturns.map((sr) => `
                                <tr>
                                    <td style="padding: 4px 6px; border-left: 1px solid #000; border-right: 1px solid #000; border-bottom: 1px solid #000;">${escapeHtml(sr.return_number || '---')}</td>
                                    <td style="padding: 4px 6px; border-right: 1px solid #000; border-bottom: 1px solid #000;">${escapeHtml(sr.po_number)}</td>
                                    <td style="padding: 4px 6px; border-right: 1px solid #000; border-bottom: 1px solid #000;">${escapeHtml(sr.remarks || '---')}</td>
                                    <td style="padding: 4px 6px; text-align: right; font-weight: bold; color: #b91c1c; border-right: 1px solid #000; border-bottom: 1px solid #000;">- ${peso.format(sr.total_amount)}</td>
                                </tr>
                            `).join('');
                            if (printSuddenlyLine && printSuddenlyAmount) {
                                printSuddenlyLine.style.display = 'block';
                                printSuddenlyAmount.textContent = peso.format(printTotals.suddenReturnsTotal);
                            }
                        } else {
                            printSuddenSection.style.display = 'none';
                            printSuddenRows.innerHTML = '';
                            if (printSuddenlyLine) printSuddenlyLine.style.display = 'none';
                        }
                    }

                    const paymentRows = Array.isArray(data.payments) && data.payments.length
                        ? data.payments
                        : (data.payment ? [data.payment] : []);
                    const paymentTbody = $('print-payment-rows');
                    if (paymentTbody) {
                        paymentTbody.innerHTML = paymentRows.map((payment) => {
                            const method = payment.payment_method || v.payment_method || '---';
                            const isBank = ['Cheque', 'Bank Transfer'].includes(method);
                            const date = isBank ? (payment.check_date || '---') : (payment.payment_date || '---');
                            return `<tr>
                                <td style="padding: 4px 3px; border-left: 1px solid #000; border-right: 1px solid #000; border-bottom: 1px solid #000;">${escapeHtml(isBank ? (payment.account_no || '---') : method)}</td>
                                <td style="padding: 4px 3px; border-right: 1px solid #000; border-bottom: 1px solid #000;">${escapeHtml(isBank ? (payment.bank_name || '---') : '---')}</td>
                                <td style="padding: 4px 3px; border-right: 1px solid #000; border-bottom: 1px solid #000;">${escapeHtml(isBank ? (payment.check_no || '---') : '---')}</td>
                                <td style="padding: 4px 3px; border-right: 1px solid #000; border-bottom: 1px solid #000;">${escapeHtml(date)}</td>
                                <td style="padding: 4px 3px; text-align: right; border-right: 1px solid #000; border-bottom: 1px solid #000; font-weight: bold;">${peso.format(Number(payment.credit_amount || 0))}</td>
                            </tr>`;
                        }).join('');
                    }
                    const savedPaymentTotal = paymentRows.reduce((sum, payment) => sum + Number(payment.credit_amount || 0), 0);
                    populateStaticCheckSummary(paymentRows, Boolean(Number(v.show_check_summary || 0)));
                    if ($('print-total')) $('print-total').textContent = peso.format(Math.max(0, printTotals.finalTotal - savedPaymentTotal));

                    window.pcvClosePrintModal();
                    setTimeout(() => {
                        const printContent = $('pcv-print-content')?.cloneNode(true);
                        if (!printContent) return;
                        printContent.classList.remove('hidden');
                        const frame = $('pcv-print-frame');
                        frame.style.width = '850px';
                        frame.style.height = '1100px';
                        const doc = frame.contentDocument || frame.contentWindow.document;
                        doc.open();
                        doc.write(`<html><head><style>body { margin: 0; padding: 0; } table { border-collapse: collapse; } td, th { padding: 4px 8px; }</style></head><body>${printContent.outerHTML}</body></html>`);
                        doc.close();
                        setTimeout(() => { frame.contentWindow.print(); }, 300);
                    }, 200);
                } catch (error) {
                    alert(error.message);
                }
            }

            // --- History search ---
            document.querySelectorAll('[data-hist-col]').forEach((i) => i.addEventListener('input', () => {
                histFilters[i.dataset.histCol] = i.value;
                histPage = 1;
                clearTimeout(histSearchTimer);
                histSearchTimer = setTimeout(() => loadHistory(1), 250);
            }));
            
            // --- Print modal filter inputs ---
            document.querySelectorAll('[data-print-col]').forEach((i) => i.addEventListener('input', () => { printFilters[i.dataset.printCol] = i.value; printPage = 1; renderPrintTable(); }));
            
            // TASK 5: Print modal sudden return filter dropdown
            const printFilterDropdown = $('pcv-print-filter');
            if (printFilterDropdown) {
                printFilterDropdown.addEventListener('change', () => { printFilter = printFilterDropdown.value; printPage = 1; renderPrintTable(); });
            }
            
            // Tab switching (yellow active / maroon inactive)
            $('pcv-tab-payables').addEventListener('click', () => {
                // Switch panels
                $('pcv-payables-panel').classList.remove('hidden');
                $('pcv-history-panel').classList.add('hidden');
                // Update tab styles - First reset both tabs to inactive
                $('pcv-tab-payables').className = 'px-5 py-2.5 bg-maroon-900 text-yellow-400 text-[10px] font-bold rounded-xl uppercase tracking-widest transition-all hover:bg-maroon-800 flex items-center gap-2';
                $('pcv-tab-history').className = 'px-5 py-2.5 bg-maroon-900 text-yellow-400 text-[10px] font-bold rounded-xl uppercase tracking-widest transition-all hover:bg-maroon-800 flex items-center gap-2';
                // Then apply active state to clicked tab
                $('pcv-tab-payables').className = 'px-5 py-2.5 bg-yellow-400 text-maroon-900 text-[10px] font-bold rounded-xl uppercase tracking-widest shadow-sm transition-all hover:bg-yellow-300 flex items-center gap-2';
                // Re-initialize lucide icons to update colors
                if (window.lucide) window.lucide.createIcons();
            });
            $('pcv-tab-history').addEventListener('click', () => {
                // Switch panels
                $('pcv-payables-panel').classList.add('hidden');
                $('pcv-history-panel').classList.remove('hidden');
                // Load history if not loaded
                if (!vouchers || !vouchers.length) loadHistory();
                // Update tab styles - First reset both tabs to inactive
                $('pcv-tab-payables').className = 'px-5 py-2.5 bg-maroon-900 text-yellow-400 text-[10px] font-bold rounded-xl uppercase tracking-widest transition-all hover:bg-maroon-800 flex items-center gap-2';
                $('pcv-tab-history').className = 'px-5 py-2.5 bg-maroon-900 text-yellow-400 text-[10px] font-bold rounded-xl uppercase tracking-widest transition-all hover:bg-maroon-800 flex items-center gap-2';
                // Then apply active state to clicked tab
                $('pcv-tab-history').className = 'px-5 py-2.5 bg-yellow-400 text-maroon-900 text-[10px] font-bold rounded-xl uppercase tracking-widest shadow-sm transition-all hover:bg-yellow-300 flex items-center gap-2';
                // Re-initialize lucide icons to update colors
                if (window.lucide) window.lucide.createIcons();
            });

            // Dashboard filter card click handlers
            document.querySelectorAll('[data-dashboard-filter]').forEach(card => {
                card.addEventListener('click', () => {
                    pcvDashboardFilter = card.dataset.dashboardFilter;
                    mainPage = 1;
                    updateDashboardCards();
                    renderMain();
                    if (window.lucide) window.lucide.createIcons();
                });
            });

            // Expose internal state & functions for edit modal
            window.pcvVoucherPayload = voucherPayload;
            window.pcvSelectedVoucherInvoices = selectedVoucherInvoices;
            window.pcvSetEditState = function(state) {
                if (state.currentSupplier !== undefined) currentSupplier = state.currentSupplier;
                if (state.selectedSuddenReturns !== undefined) {
                    selectedSuddenReturns = state.selectedSuddenReturns;
                    window.pcv_selectedSR = JSON.parse(JSON.stringify(state.selectedSuddenReturns));
                }
                if (state.suddenReturns !== undefined) suddenReturns = state.suddenReturns;
                if (state.additionalDiscount !== undefined) additionalDiscount = state.additionalDiscount;
                if (state.currentStep !== undefined) currentStep = state.currentStep;
            };
            window.pcvRefreshUI = function() { updateStep(); renderInvoices(); };
            window.pcvCallFetchSuddenReturns = function(supplierId) {
                const params = new URLSearchParams();
                if (window.editingVoucherId) {
                    // Edit mode: don't filter by po_ids to allow returns from all POs (po_id may differ from invoice purchase_order_id)
                    params.append('voucher_id', window.editingVoucherId);
                } else {
                    const selectedPoIds = selectedInvoicePoIds();
                    selectedPoIds.forEach(id => params.append('po_ids[]', id));
                }
                const url = `${routes().suddenReturns}/${supplierId}?${params.toString()}`;
                fetch(url).then(r => r.json()).then(data => {
                    if (!data.success) return;
                    suddenReturns = data.returns || [];
                    // Force-inject current voucher's own grouped returns (if editing)
                    // so they always appear in Step 2 regardless of API filtering.
                    if (window.pcvEditPrecheckedReturns && window.pcvEditPrecheckedReturns.length) {
                        window.pcvEditPrecheckedReturns.forEach(vsr => {
                            if (!suddenReturns.find(r => r.return_number === vsr.return_number)) {
                                // Look up PO number from current supplier invoices
                                let poNumber = '---';
                                if (currentSupplier?.invoices) {
                                    const inv = currentSupplier.invoices.find(i => String(i.sourceId) === String(vsr.po_id));
                                    if (inv) poNumber = inv.purchaseNo || '---';
                                }
                                suddenReturns.push({
                                    id: 'v-' + vsr.return_number,
                                    po_id: vsr.po_id,
                                    po_number: poNumber,
                                    return_number: vsr.return_number,
                                    slip_no: vsr.slip_no || '',
                                    date: vsr.date || null,
                                    total_amount: vsr.total_amount,
                                    remarks: vsr.remarks || '',
                                    items: Array.isArray(vsr.items) ? vsr.items : [],
                                });
                            }
                        });

                        // Replace edit prechecked placeholders with the full Purchase Return rows
                        // so Slip No., Returned Items, date, and return amount survive Edit -> Proceed Voucher.
                        const richPrechecked = window.pcvEditPrecheckedReturns.map((saved) => {
                            const rich = suddenReturns.find((row) =>
                                String(row.return_number || '') === String(saved.return_number || '')
                                && String(row.po_id || '') === String(saved.po_id || '')
                            ) || suddenReturns.find((row) => String(row.return_number || '') === String(saved.return_number || ''));
                            return {
                                ...(rich || saved),
                                ...saved,
                                slip_no: (rich?.slip_no || saved.slip_no || ''),
                                date: (rich?.date || saved.date || null),
                                total_amount: Number(saved.total_amount ?? rich?.total_amount ?? 0),
                                items: Array.isArray(rich?.items) && rich.items.length ? rich.items : (Array.isArray(saved.items) ? saved.items : []),
                                remarks: saved.remarks || rich?.remarks || '',
                            };
                        });
                        window.pcvEditPrecheckedReturns = richPrechecked;
                        selectedSuddenReturns = JSON.parse(JSON.stringify(richPrechecked));
                        window.pcv_selectedSR = JSON.parse(JSON.stringify(richPrechecked));
                    }
                    const emptyEl = $('pcv-step-2-empty');
                    const contentEl = $('pcv-step-2-content');
                    if (!suddenReturns.length) {
                        if (emptyEl) emptyEl.classList.remove('hidden');
                        if (contentEl) contentEl.classList.add('hidden');
                        pcvToggleStep3Stepper(false);
                        updateStep();
                        return;
                    }
                    if (emptyEl) emptyEl.classList.add('hidden');
                    if (contentEl) contentEl.classList.remove('hidden');
                    pcvToggleStep3Stepper(true);
                    const tbody = $('pcv-sudden-returns-tbody');
                    tbody.innerHTML = suddenReturns.map((r, idx) => {
                        const isPrechecked = window.pcvEditPrecheckedReturns && window.pcvEditPrecheckedReturns.some(sr => sr.return_number === r.return_number);
                        return `
                            <tr class="hover:bg-amber-50">
                                <td class="px-4 py-3 text-center"><input type="checkbox" data-sr-check="${idx}" class="accent-amber-700" ${isPrechecked ? 'checked' : ''}></td>
                                <td class="px-4 py-3 font-black text-slate-700">${escapeHtml(r.return_number)}</td>
                                <td class="px-4 py-3 font-bold text-slate-700">${escapeHtml(r.slip_no || '---')}</td>
                                <td class="px-4 py-3 font-bold text-slate-600">${escapeHtml(r.invoice_no || r.po_number || '---')}</td>
                                <td class="px-4 py-3 font-semibold text-slate-600 max-w-[260px] whitespace-normal">${escapeHtml(returnedItemsSummary(r.items))}</td>
                                <td class="px-4 py-3 font-semibold text-slate-600">${r.date || '---'}</td>
                                <td class="px-4 py-3 text-right font-black text-rose-600">${peso.format(r.total_amount)}</td>
                                <td class="px-4 py-3"><input type="text" data-sr-remarks="${idx}" class="w-full border border-slate-200 rounded-lg px-2 py-1.5 text-xs font-bold outline-none" placeholder="Enter remarks" value="${isPrechecked ? (window.pcvEditPrecheckedReturns.find(sr => sr.return_number === r.return_number)?.remarks || '') : ''}"></td>
                            </tr>
                        `;
                    }).join('');
                    $('pcv-sudden-select-all').addEventListener('change', (e) => {
                        const checked = e.target.checked;
                        tbody.querySelectorAll('[data-sr-check]').forEach((cb) => { cb.checked = checked; cb.dispatchEvent(new Event('change')); });
                    });
                    tbody.querySelectorAll('[data-sr-check]').forEach((cb) => cb.addEventListener('change', () => {
                        const idx = Number(cb.dataset.srCheck);
                        const remarksInput = tbody.querySelector(`[data-sr-remarks="${idx}"]`);
                        if (cb.checked) {
                            if (!selectedSuddenReturns.find((r) => r.return_number === suddenReturns[idx].return_number)) {
                                selectedSuddenReturns.push({ ...suddenReturns[idx], remarks: remarksInput ? remarksInput.value : '' });
                            }
                            const invoice = currentSupplier?.invoices?.find((i) => String(i.sourceId) === String(suddenReturns[idx]?.po_id));
                            if (invoice) invoice.selected = true;
                        } else {
                            selectedSuddenReturns = selectedSuddenReturns.filter((r) => r.return_number !== suddenReturns[idx].return_number);
                        }
                        window.pcv_selectedSR = JSON.parse(JSON.stringify(selectedSuddenReturns));
                        updateSuddenSelectedTotal();
                        recalculateVoucherDeductions();
                        renderInvoices();
                        if (typeof renderStep2Summary === 'function') renderStep2Summary();
                    }));
                    tbody.querySelectorAll('[data-sr-remarks]').forEach((input) => input.addEventListener('input', () => {
                        const idx = Number(input.dataset.srRemarks);
                        const sr = selectedSuddenReturns.find((r) => r.return_number === suddenReturns[idx]?.return_number);
                        if (sr) sr.remarks = input.value;
                    }));
                    // Ensure prechecked returns are in selectedSuddenReturns
                    // (belt-and-suspenders in case pcvSetEditState didn't set it)
                    if (window.pcvEditPrecheckedReturns && window.pcvEditPrecheckedReturns.length) {
                        window.pcvEditPrecheckedReturns.forEach(vsr => {
                            if (!selectedSuddenReturns.find(r => r.return_number === vsr.return_number)) {
                                selectedSuddenReturns.push({ ...vsr });
                            }
                        });
                    }
                    window.pcv_selectedSR = JSON.parse(JSON.stringify(selectedSuddenReturns));
                    updateSuddenSelectedTotal();
                    updateStep();
                }).catch(e => console.warn('Sudden returns fetch error:', e));
            };
            window.pcvCloseModal = () => {
                $('pcv-proceed-modal').classList.add('hidden');
                $('pcv-proceed-modal').classList.remove('flex');
                // Reset edit state on close
                isEditMode = false;
                selectedSuddenReturns = [];
                currentSupplier = null;
                window.pcv_selectedSR = null;
                if (window.pcvEditPrecheckedReturns) window.pcvEditPrecheckedReturns = null;
                // Restore confirm button onclick for new voucher mode
                const submitBtn = $('pcv-confirm-submit-btn');
                if (submitBtn && window._pcvOriginalConfirmOnClick) {
                    submitBtn.setAttribute('onclick', window._pcvOriginalConfirmOnClick);
                }
            };

            // ==================== RECOMMENDATION MODAL ====================
            window.pcvOpenRecModal = async function() {
                const sid = currentSupplier?.id;
                if (!sid) {
                    alert('Please select a supplier first.');
                    return;
                }
                const modal = $('pcv-rec-modal');
                if (!modal) return;
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                $('pcv-rec-tbody').innerHTML = '';
                $('pcv-rec-empty').classList.add('hidden');
                $('pcv-rec-loading').classList.remove('hidden');
                $('pcv-rec-search').value = '';
                try {
                    const res = await fetch(routes().recommendations + '/' + sid + '/payment-recommendations', {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const json = await res.json();
                    $('pcv-rec-loading').classList.add('hidden');
                    if (!json.success || !json.data?.length) {
                        $('pcv-rec-empty').classList.remove('hidden');
                        return;
                    }
                    renderRecs(json.data);
                } catch (e) {
                    $('pcv-rec-loading').classList.add('hidden');
                    console.error('Recommendations error:', e);
                    $('pcv-rec-empty').classList.remove('hidden');
                }
            };
            function renderRecs(data) {
                const tbody = $('pcv-rec-tbody');
                tbody.innerHTML = data.map(r => `
                    <tr class="border-b border-slate-100 hover:bg-blue-50 transition-colors">
                        <td class="py-3 pr-4 text-sm font-bold text-slate-700">${escapeHtml(r.bank_name)}</td>
                        <td class="py-3 pr-4 text-sm font-mono text-slate-600">${escapeHtml(r.account_no)}</td>
                        <td class="py-3 pr-4"><span class="pcv-tag">${escapeHtml(r.payment_method)}</span></td>
                        <td class="py-3 pr-4 text-sm text-slate-500">${r.last_used_at ? r.last_used_at.slice(0,10) : '---'}</td>
                        <td class="py-3 pr-4 text-sm font-bold text-slate-600">${r.used_count}</td>
                        <td class="py-3"><button type="button" class="pcv-use-rec-btn px-4 py-1.5 rounded-lg bg-blue-600 text-white text-[10px] font-black uppercase tracking-widest hover:bg-blue-700 transition-colors" data-bank-name="${escapeHtml(r.bank_name)}" data-account-no="${escapeHtml(r.account_no)}" data-payment-method="${escapeHtml(r.payment_method || '')}">Use</button></td>
                    </tr>
                `).join('');
            }
            window.pcvUseRec = function(bankName, accountNo, recommendationMethod = '') {
                const normalizedMethod = recommendationMethod === 'Bank' ? 'Bank Transfer' : recommendationMethod;
                if (['Cheque', 'Bank Transfer'].includes(normalizedMethod)) {
                    const radio = document.querySelector(`input[name="pcv-payment-method"][value="${normalizedMethod}"]`);
                    if (radio) {
                        radio.checked = true;
                        radio.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
                $('pcv-account-no').value = accountNo || '';
                $('pcv-bank-name').value = bankName || '';
                window.pcvCloseRecModal();
                // Toast notification
                const n = document.createElement('div');
                n.className = 'fixed top-4 right-4 bg-emerald-500 text-white px-6 py-3 rounded-lg shadow-lg z-[999] text-[11px] font-black';
                n.textContent = 'Bank details filled from recommendation';
                document.body.appendChild(n);
                setTimeout(() => { n.style.opacity = '0'; setTimeout(() => n.remove(), 300); }, 3000);
            };
            window.pcvCloseRecModal = function() {
                const modal = $('pcv-rec-modal');
                if (modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }
            };
            // Delegated click for Use buttons (safe, no inline onclick with escaped strings)
            document.addEventListener('click', function(e) {
                const btn = e.target.closest('.pcv-use-rec-btn');
                if (!btn) return;
                window.pcvUseRec(btn.dataset.bankName || '', btn.dataset.accountNo || '', btn.dataset.paymentMethod || '');
            });
            // Search listener
            document.addEventListener('input', function(e) {
                if (e.target.id === 'pcv-rec-search') {
                    const q = e.target.value.toLowerCase();
                    let visible = 0;
                    document.querySelectorAll('#pcv-rec-tbody tr').forEach(row => {
                        const match = row.textContent.toLowerCase().includes(q);
                        row.style.display = match ? '' : 'none';
                        if (match) visible++;
                    });
                    const empty = $('pcv-rec-empty');
                    if (empty) {
                        if (visible === 0 && $('pcv-rec-tbody').children.length > 0) {
                            empty.classList.remove('hidden');
                            empty.querySelector('p').textContent = 'No recommendations match your search.';
                        } else if ($('pcv-rec-tbody').children.length > 0) {
                            empty.classList.add('hidden');
                        }
                    }
                }
            });
        })();
    </script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('partials.special_user.special_sidebar_navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php /**PATH C:\xampp\htdocs\hatdog\resources\views\Special_User\Accounting\Payable-Cheque-Voucher.blade.php ENDPATH**/ ?>