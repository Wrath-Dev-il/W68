<?php if(request()->routeIs('admin.expense-cheque-voucher', 'regular.expense-cheque-voucher', 'special.expense-cheque-voucher')): ?>
<div id="page-expense-cheque-voucher" data-ecv-version="2" class="ecv-page space-y-6">
    <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4 bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
        <div>
            <p class="text-[10px] font-black uppercase tracking-widest text-maroon-700">Accounting</p>
            <h2 class="mt-1 text-xl font-black text-slate-900">Expense Cheque Voucher</h2>
            <p class="mt-1 text-xs font-semibold text-slate-500">One voucher header, multiple expense items, and multiple bank details.</p>
        </div>
        <button type="button" id="ecv2-open-add-btn" class="inline-flex items-center justify-center gap-2 rounded-xl bg-maroon-900 px-5 py-3 text-[10px] font-black uppercase tracking-widest text-white shadow-sm hover:bg-maroon-800">
            <i data-lucide="plus" class="h-4 w-4 text-goldlining-400"></i><span>Add Expense Voucher</span>
        </button>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/70 px-5 py-4">
            <div><h3 class="text-xs font-black uppercase tracking-widest text-slate-700">Expense Voucher Details</h3><p class="mt-1 text-[10px] font-semibold text-slate-400">Each search runs before the 50-row pagination.</p></div>
            <span id="ecv2-page-summary" class="text-[10px] font-black uppercase tracking-widest text-slate-400">Showing 0-0 of 0</span>
        </div>
        <div class="overflow-x-auto ecv-scroll">
            <table class="w-full min-w-[1650px] text-left">
                <thead>
                    <tr class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-500">
                        <th class="px-4 py-4">Voucher No.</th>
                        <th class="px-4 py-4">Supplier Name</th>
                        <th class="px-4 py-4">Particulars</th>
                        <th class="px-4 py-4">Remarks</th>
                        <th class="px-4 py-4">Check Date</th>
                        <th class="px-4 py-4">Bank</th>
                        <th class="px-4 py-4">Check No.</th>
                        <th class="px-4 py-4 text-right">Check Amount</th>
                        <th class="px-4 py-4 text-center">Action</th>
                    </tr>
                    <tr class="border-y border-slate-100 bg-white">
                        <th class="px-4 py-2"><input class="ecv-col-search" data-ecv2-filter="voucherNo" placeholder="Search voucher"></th>
                        <th class="px-4 py-2"><input class="ecv-col-search" data-ecv2-filter="supplierName" placeholder="Search supplier"></th>
                        <th class="px-4 py-2"><input class="ecv-col-search" data-ecv2-filter="particular" placeholder="Search particulars"></th>
                        <th class="px-4 py-2"><input class="ecv-col-search" data-ecv2-filter="remarks" placeholder="Search remarks"></th>
                        <th class="px-4 py-2"><input class="ecv-col-search" data-ecv2-filter="checkDateSearch" placeholder="Search date"></th>
                        <th class="px-4 py-2"><input class="ecv-col-search" data-ecv2-filter="bankSearch" placeholder="Search bank"></th>
                        <th class="px-4 py-2"><input class="ecv-col-search" data-ecv2-filter="checkNoSearch" placeholder="Search check no."></th>
                        <th class="px-4 py-2"><input class="ecv-col-search text-right" data-ecv2-filter="checkAmountSearch" placeholder="Search amount"></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="ecv2-table-body" class="divide-y divide-slate-100 text-xs"></tbody>
            </table>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-t border-slate-100 bg-slate-50/60 px-5 py-4">
            <p class="text-xs font-bold text-slate-500">50 data per page</p>
            <div id="ecv2-pagination" class="flex flex-wrap items-center gap-1"></div>
        </div>
    </div>

    <div id="ecv2-form-modal" class="fixed inset-0 z-[650] hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" data-ecv2-close="form"></div>
        <form id="ecv2-form" class="relative flex max-h-[95vh] w-full max-w-7xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
            <div class="flex flex-wrap items-center justify-between gap-4 bg-maroon-900 px-6 py-4 text-white">
                <div class="min-w-[220px] flex-1"><h3 id="ecv2-form-title" class="text-sm font-black uppercase tracking-widest">Add Expense Voucher</h3><p id="ecv2-form-subtitle" class="mt-0.5 text-[11px] font-semibold text-white/60">Create one voucher with expense items and bank details.</p></div>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <div class="min-w-[135px] rounded-lg border border-white/15 bg-white/10 px-3 py-2"><p class="text-[9px] font-black uppercase tracking-widest text-white/55">Expense Items Total</p><p id="ecv2-form-expense-total" class="mt-0.5 text-sm font-black text-white">₱0.00</p></div>
                    <div class="min-w-[135px] rounded-lg border border-white/15 bg-white/10 px-3 py-2"><p class="text-[9px] font-black uppercase tracking-widest text-white/55">Bank Details Total</p><p id="ecv2-form-bank-total" class="mt-0.5 text-sm font-black text-white">₱0.00</p></div>
                    <div class="min-w-[135px] rounded-lg border border-goldlining-400/40 bg-goldlining-400/15 px-3 py-2"><p class="text-[9px] font-black uppercase tracking-widest text-goldlining-200">Remaining</p><p id="ecv2-form-remaining" class="mt-0.5 text-sm font-black text-goldlining-100">₱0.00</p></div>
                    <button type="button" class="rounded-lg p-2 hover:bg-white/10" data-ecv2-close="form"><i data-lucide="x" class="h-5 w-5"></i></button>
                </div>
            </div>
            <div class="ecv-scroll flex-1 overflow-y-auto p-6 space-y-6">
                <input id="ecv2-edit-id" type="hidden">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 rounded-xl border border-slate-200 bg-white p-4">
                    <div class="space-y-1"><label class="ecv-label">Voucher No.</label><span id="ecv2-voucher-no" class="ecv-tag">ECV-0001</span></div>
                    <div class="space-y-1 xl:col-span-2"><label class="ecv-label">Supplier</label><button type="button" id="ecv2-open-supplier-btn" class="ecv-field flex items-center justify-between text-left"><span id="ecv2-supplier-name">Search supplier</span><i data-lucide="search" class="h-4 w-4 text-slate-400"></i></button></div>
                    <div class="space-y-1"><label class="ecv-label">Particular</label><input id="ecv2-particular" class="ecv-field" type="text" placeholder="Example: COMMISSION 1" required></div>
                    <div class="space-y-1"><label class="ecv-label">Ref</label><input id="ecv2-ref" class="ecv-field" type="text" placeholder="Reference / remarks"></div>
                    <div class="space-y-1"><label class="ecv-label">Date</label><input id="ecv2-date" class="ecv-field" type="date" required></div>
                    <div class="space-y-1 md:col-span-2 xl:col-span-3"><label class="ecv-label">Total</label><input id="ecv2-total" class="ecv-field bg-slate-100 font-black" type="text" value="₱0.00" readonly></div>
                </div>

                <section class="rounded-xl border border-slate-200 bg-slate-50/40 p-4">
                    <div class="mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div><h4 class="text-xs font-black uppercase tracking-widest text-slate-700">Expense Items</h4><p class="mt-1 text-[10px] font-semibold text-slate-400">Line amount = QTY × Unit Price. Voucher Total is calculated automatically.</p></div>
                        <button type="button" id="ecv2-add-expense-item-btn" class="rounded-lg bg-slate-800 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-white hover:bg-slate-700">Add Expense Item</button>
                    </div>
                    <div id="ecv2-expense-items" class="space-y-3"></div>
                </section>

                <section class="rounded-xl border border-slate-200 bg-slate-50/40 p-4">
                    <div class="mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div><h4 class="text-xs font-black uppercase tracking-widest text-slate-700">Bank Details</h4><p class="mt-1 text-[10px] font-semibold text-slate-400">Add as many check/payment detail groups as needed. Credit Amount may be positive or negative; negative values deduct from the bank total.</p></div>
                        <button type="button" id="ecv2-add-bank-btn" class="rounded-lg bg-slate-800 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-white hover:bg-slate-700">Add More</button>
                    </div>
                    <div id="ecv2-bank-details" class="space-y-3"></div>
                </section>
            </div>
            <div class="flex justify-end gap-3 border-t border-slate-100 px-6 py-4">
                <button type="button" class="rounded-xl bg-slate-100 px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-600" data-ecv2-close="form">Cancel</button>
                <button type="submit" id="ecv2-save-btn" class="rounded-xl bg-maroon-900 px-6 py-2.5 text-[10px] font-black uppercase tracking-widest text-white">Save Voucher</button>
            </div>
        </form>
    </div>

    <div id="ecv2-view-modal" class="fixed inset-0 z-[660] hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" data-ecv2-close="view"></div>
        <div class="relative flex max-h-[94vh] w-full max-w-6xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
            <div class="flex flex-wrap items-center justify-between gap-4 bg-maroon-900 px-6 py-4 text-white">
                <div class="min-w-[220px] flex-1"><h3 class="text-sm font-black uppercase tracking-widest">View Voucher</h3><p id="ecv2-view-heading" class="mt-1 text-[11px] font-semibold text-white/65"></p></div>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <div class="min-w-[135px] rounded-lg border border-white/15 bg-white/10 px-3 py-2"><p class="text-[9px] font-black uppercase tracking-widest text-white/55">Expense Items Total</p><p id="ecv2-view-expense-total" class="mt-0.5 text-sm font-black text-white">₱0.00</p></div>
                    <div class="min-w-[135px] rounded-lg border border-white/15 bg-white/10 px-3 py-2"><p class="text-[9px] font-black uppercase tracking-widest text-white/55">Bank Details Total</p><p id="ecv2-view-bank-total" class="mt-0.5 text-sm font-black text-white">₱0.00</p></div>
                    <div class="min-w-[135px] rounded-lg border border-goldlining-400/40 bg-goldlining-400/15 px-3 py-2"><p class="text-[9px] font-black uppercase tracking-widest text-goldlining-200">Remaining</p><p id="ecv2-view-remaining" class="mt-0.5 text-sm font-black text-goldlining-100">₱0.00</p></div>
                    <button type="button" class="rounded-lg p-2 hover:bg-white/10" data-ecv2-close="view"><i data-lucide="x" class="h-5 w-5"></i></button>
                </div>
            </div>
            <div class="ecv-scroll flex-1 overflow-y-auto p-6 space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div><span class="ecv-label">Voucher No.</span><p id="ecv2-view-voucher-no" class="mt-1 font-black text-slate-800"></p></div>
                    <div><span class="ecv-label">Supplier</span><p id="ecv2-view-supplier" class="mt-1 font-black text-slate-800"></p></div>
                    <div><span class="ecv-label">Particular</span><p id="ecv2-view-particular" class="mt-1 font-black text-slate-800"></p></div>
                    <div><span class="ecv-label">Ref</span><p id="ecv2-view-ref" class="mt-1 font-bold text-slate-600"></p></div>
                    <div><span class="ecv-label">Date</span><p id="ecv2-view-date" class="mt-1 font-bold text-slate-600"></p></div>
                    <div><span class="ecv-label">Total</span><p id="ecv2-view-total" class="mt-1 font-black text-slate-900"></p></div>
                </div>
                <div>
                    <h4 class="mb-3 text-xs font-black uppercase tracking-widest text-slate-700">Expense Items</h4>
                    <div class="overflow-x-auto rounded-xl border border-slate-200 ecv-scroll"><table class="w-full min-w-[700px] text-left"><thead class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-500"><tr><th class="px-4 py-3">Particular Items</th><th class="px-4 py-3">Unit</th><th class="px-4 py-3 text-right">QTY</th><th class="px-4 py-3 text-right">Amount</th></tr></thead><tbody id="ecv2-view-items-body" class="divide-y divide-slate-100 text-xs"></tbody></table></div>
                </div>
                <div>
                    <h4 class="mb-3 text-xs font-black uppercase tracking-widest text-slate-700">Bank Details</h4>
                    <div class="overflow-x-auto rounded-xl border border-slate-200 ecv-scroll"><table class="w-full min-w-[850px] text-left"><thead class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-500"><tr><th class="px-4 py-3">Bank Name</th><th class="px-4 py-3">Check No.</th><th class="px-4 py-3">Check Date</th><th class="px-4 py-3 text-right">Credit Amount</th></tr></thead><tbody id="ecv2-view-bank-body" class="divide-y divide-slate-100 text-xs"></tbody></table></div>
                </div>
            </div>
            <div class="flex justify-end gap-3 border-t border-slate-100 px-6 py-4"><button type="button" class="rounded-xl bg-slate-100 px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-600" data-ecv2-close="view">Close</button></div>
        </div>
    </div>

    <div id="ecv2-supplier-modal" class="fixed inset-0 z-[700] hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" data-ecv2-close="supplier"></div>
        <div class="relative w-full max-w-2xl overflow-hidden rounded-xl bg-white shadow-2xl">
            <div class="flex items-center justify-between bg-maroon-900 px-6 py-4 text-white"><h3 class="text-sm font-black uppercase tracking-widest">Select Supplier</h3><button type="button" class="rounded-lg p-2 hover:bg-white/10" data-ecv2-close="supplier"><i data-lucide="x" class="h-5 w-5"></i></button></div>
            <div class="p-5"><input id="ecv2-supplier-search" class="ecv-field" type="text" placeholder="Search supplier code or name"><div class="mt-4 max-h-[420px] overflow-y-auto rounded-xl border border-slate-200 ecv-scroll"><table class="w-full text-left"><thead class="sticky top-0 bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-500"><tr><th class="px-4 py-3">Supplier Code</th><th class="px-4 py-3">Supplier Name</th></tr></thead><tbody id="ecv2-supplier-body" class="divide-y divide-slate-100 text-xs"></tbody></table></div></div>
        </div>
    </div>

    <div id="ecv2-confirm-modal" class="fixed inset-0 z-[750] hidden items-center justify-center p-4"><div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"></div><div class="relative w-full max-w-md overflow-hidden rounded-xl bg-white shadow-2xl"><div class="p-6 text-center"><div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-amber-100"><i data-lucide="alert-triangle" class="h-7 w-7 text-amber-600"></i></div><h3 id="ecv2-confirm-title" class="text-sm font-black uppercase tracking-widest text-slate-800"></h3><p id="ecv2-confirm-message" class="mt-2 text-xs font-semibold text-slate-500"></p></div><div class="flex items-center justify-center gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4"><button type="button" id="ecv2-confirm-cancel" class="rounded-xl bg-slate-200 px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-600">Cancel</button><button type="button" id="ecv2-confirm-yes" class="rounded-xl bg-maroon-900 px-6 py-2.5 text-[10px] font-black uppercase tracking-widest text-white">Confirm</button></div></div></div>
    <div id="ecv2-success-modal" class="fixed inset-0 z-[750] hidden items-center justify-center p-4"><div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"></div><div class="relative w-full max-w-md overflow-hidden rounded-xl bg-white shadow-2xl"><div class="p-6 text-center"><div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100"><i data-lucide="check-circle-2" class="h-7 w-7 text-emerald-600"></i></div><h3 id="ecv2-success-title" class="text-sm font-black uppercase tracking-widest text-slate-800"></h3><p id="ecv2-success-message" class="mt-2 text-xs font-semibold text-slate-500"></p></div><div class="flex justify-center border-t border-slate-100 bg-slate-50 px-6 py-4"><button type="button" id="ecv2-success-ok" class="rounded-xl bg-emerald-600 px-8 py-2.5 text-[10px] font-black uppercase tracking-widest text-white">OK</button></div></div></div>
</div>
<?php else: ?>
<div id="page-expense-cheque-voucher" class="ecv-page space-y-6">
    <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4 bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
        <div>
            <p class="text-[10px] font-black uppercase tracking-widest text-maroon-700">Accounting</p>
            <h2 class="mt-1 text-xl font-black text-slate-900">Expense Cheque Voucher</h2>
            <p class="mt-1 text-xs font-semibold text-slate-500">Create vouchers with multiple editable particulars groups.</p>
        </div>
        <button type="button" id="ecv-open-add-btn" class="inline-flex items-center justify-center gap-2 rounded-xl bg-maroon-900 px-5 py-3 text-[10px] font-black uppercase tracking-widest text-white shadow-sm hover:bg-maroon-800">
            <i data-lucide="plus" class="h-4 w-4 text-goldlining-400"></i><span>Add Expense</span>
        </button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <?php $__currentLoopData = [
            ['id' => 'total', 'label' => 'Total Suppliers', 'icon' => 'receipt-text'],
            ['id' => 'monthly', 'label' => 'Monthly', 'icon' => 'calendar-days'],
            ['id' => 'yearly', 'label' => 'Yearly', 'icon' => 'calendar-range'],
            ['id' => 'one-time', 'label' => 'One Time', 'icon' => 'badge-check'],
        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $card): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div><p class="text-[10px] font-black uppercase tracking-widest text-slate-400"><?php echo e($card['label']); ?></p><p id="ecv-stat-<?php echo e($card['id']); ?>" class="mt-3 text-2xl font-black text-slate-900">0</p></div>
                    <div class="ecv-icon-box"><i data-lucide="<?php echo e($card['icon']); ?>" class="h-5 w-5"></i></div>
                </div>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/70 px-5 py-4">
            <div><h3 class="text-xs font-black uppercase tracking-widest text-slate-700">Expense Vouchers</h3><p class="mt-1 text-[10px] font-semibold text-slate-400">Search applies before pagination.</p></div>
            <span id="ecv-page-summary" class="text-[10px] font-black uppercase tracking-widest text-slate-400">Showing 0-0 of 0</span>
        </div>
        <div class="overflow-x-auto ecv-scroll">
            <table class="w-full min-w-[920px] text-left">
                <thead>
                    <tr class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-500">
                        <th class="px-4 py-4">Voucher No</th><th class="px-4 py-4">Name</th><th class="px-4 py-4 text-right">Total Amount</th><th class="px-4 py-4">Remarks</th><th class="px-4 py-4 text-center">Actions</th>
                    </tr>
                    <tr class="border-y border-slate-100 bg-white">
                        <th class="px-4 py-2"><input class="ecv-col-search" data-ecv-filter="voucherNo" placeholder="Search voucher"></th>
                        <th class="px-4 py-2"><input class="ecv-col-search" data-ecv-filter="name" placeholder="Search name"></th>
                        <th class="px-4 py-2"><input class="ecv-col-search text-right" data-ecv-filter="totalAmount" placeholder="Search amount"></th>
                        <th class="px-4 py-2"><input class="ecv-col-search" data-ecv-filter="remarks" placeholder="Search remarks"></th><th></th>
                    </tr>
                </thead>
                <tbody id="ecv-table-body" class="divide-y divide-slate-100 text-xs"></tbody>
            </table>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-t border-slate-100 bg-slate-50/60 px-5 py-4">
            <p class="text-xs font-bold text-slate-500">50 data per page</p><div id="ecv-pagination" class="flex flex-wrap items-center gap-1"></div>
        </div>
    </div>

    <div id="ecv-add-modal" class="fixed inset-0 z-[650] hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" data-ecv-close="add"></div>
        <form id="ecv-add-form" class="relative flex max-h-[94vh] w-full max-w-6xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
            <div class="flex items-center justify-between bg-maroon-900 px-6 py-4 text-white">
                <div><h3 class="text-sm font-black uppercase tracking-widest">Add Expense Voucher</h3><p class="mt-0.5 text-[11px] font-semibold text-white/60">Add one or more particulars groups.</p></div>
                <button type="button" class="rounded-lg p-2 hover:bg-white/10" data-ecv-close="add"><i data-lucide="x" class="h-5 w-5"></i></button>
            </div>
            <div class="ecv-scroll flex-1 overflow-y-auto p-6 space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 rounded-xl border border-slate-200 bg-white p-4">
                    <div class="space-y-1"><label class="ecv-label">Voucher No</label><span id="ecv-voucher-preview" class="ecv-tag">ECV-0001</span></div>
                    <div class="space-y-1 xl:col-span-2"><label class="ecv-label">Supplier</label><button type="button" id="ecv-open-supplier-btn" class="ecv-field flex items-center justify-between text-left"><span id="ecv-supplier-name">Search supplier</span><i data-lucide="search" class="h-4 w-4 text-slate-400"></i></button><input id="ecv-supplier-code" type="hidden"></div>
                    <div class="space-y-1"><label class="ecv-label">Remarks / Ref</label><input id="ecv-remarks" class="ecv-field" type="text" placeholder="Reference or remarks"></div>
                </div>
                <div class="flex items-center justify-between"><h4 class="text-xs font-black uppercase tracking-widest text-slate-700">Particulars Groups</h4><button type="button" id="ecv-add-particular-btn" class="rounded-lg bg-slate-800 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-white hover:bg-slate-700">Add Group</button></div>
                <div id="ecv-add-particulars-container" class="space-y-4"></div>
            </div>
            <div class="flex justify-end gap-3 border-t border-slate-100 px-6 py-4"><button type="button" class="rounded-xl bg-slate-100 px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-600" data-ecv-close="add">Cancel</button><button type="submit" class="rounded-xl bg-maroon-900 px-6 py-2.5 text-[10px] font-black uppercase tracking-widest text-white">Save Voucher</button></div>
        </form>
    </div>

    <div id="ecv-view-modal" class="fixed inset-0 z-[660] hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" data-ecv-close="view"></div>
        <div class="relative flex max-h-[94vh] w-full max-w-7xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
            <div class="flex items-center justify-between bg-maroon-900 px-6 py-4 text-white"><div><h3 class="text-sm font-black uppercase tracking-widest">Expense Voucher Details</h3><p id="ecv-view-heading" class="mt-1 text-[11px] font-semibold text-white/65"></p></div><button type="button" class="rounded-lg p-2 hover:bg-white/10" data-ecv-close="view"><i data-lucide="x" class="h-5 w-5"></i></button></div>
            <div class="ecv-scroll flex-1 overflow-y-auto p-6 space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div><span class="ecv-label">Voucher No</span><p id="ecv-view-voucher-no" class="mt-1 font-black text-slate-800"></p></div><div><span class="ecv-label">Supplier</span><p id="ecv-view-name" class="mt-1 font-black text-slate-800"></p></div><div><span class="ecv-label">Remarks</span><p id="ecv-view-remarks" class="mt-1 font-bold text-slate-600"></p></div>
                </div>
                <div class="overflow-x-auto rounded-xl border border-slate-200 ecv-scroll">
                    <table class="w-full min-w-[1600px] text-left"><thead class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-500"><tr><th class="px-4 py-3">Invoice No</th><th class="px-4 py-3">Particulars</th><th class="px-4 py-3">Unit</th><th class="px-4 py-3 text-right">Unit Price</th><th class="px-4 py-3 text-right">Qty</th><th class="px-4 py-3">Billing Date</th><th class="px-4 py-3">Type</th><th class="px-4 py-3 text-right">Amount</th><th class="px-4 py-3 text-right">Paid</th><th class="px-4 py-3">Payment</th><th class="px-4 py-3 text-center">Action</th></tr></thead><tbody id="ecv-view-items-body" class="divide-y divide-slate-100 text-xs"></tbody></table>
                </div>
            </div>
            <div class="flex justify-between gap-3 border-t border-slate-100 px-6 py-4"><button type="button" id="ecv-view-add-more-btn" class="rounded-xl bg-maroon-900 px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-white">Add More Particulars</button><button type="button" class="rounded-xl bg-slate-100 px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-600" data-ecv-close="view">Close</button></div>
        </div>
    </div>

    <div id="ecv-add-more-modal" class="fixed inset-0 z-[670] hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" data-ecv-close="add-more"></div>
        <form id="ecv-add-more-form" class="relative flex max-h-[94vh] w-full max-w-6xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
            <div class="flex items-center justify-between bg-maroon-900 px-6 py-4 text-white"><div><h3 class="text-sm font-black uppercase tracking-widest">Add More Particulars</h3><p id="ecv-add-more-heading" class="mt-1 text-[11px] font-semibold text-white/65"></p></div><button type="button" class="rounded-lg p-2 hover:bg-white/10" data-ecv-close="add-more"><i data-lucide="x" class="h-5 w-5"></i></button></div>
            <div class="ecv-scroll flex-1 overflow-y-auto p-6 space-y-4"><div class="flex justify-end"><button type="button" id="ecv-add-more-row-btn" class="rounded-lg bg-slate-800 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-white">Add Group</button></div><div id="ecv-add-more-container" class="space-y-4"></div></div>
            <div class="flex justify-end gap-3 border-t border-slate-100 px-6 py-4"><button type="button" class="rounded-xl bg-slate-100 px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-600" data-ecv-close="add-more">Cancel</button><button type="submit" class="rounded-xl bg-maroon-900 px-6 py-2.5 text-[10px] font-black uppercase tracking-widest text-white">Save Particulars</button></div>
        </form>
    </div>

    <div id="ecv-edit-particular-modal" class="fixed inset-0 z-[690] hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" data-ecv-close="edit-particular"></div>
        <form id="ecv-edit-particular-form" class="relative flex max-h-[94vh] w-full max-w-5xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
            <div class="flex items-center justify-between bg-slate-900 px-6 py-4 text-white"><div><h3 class="text-sm font-black uppercase tracking-widest">Edit Particular Group</h3><p id="ecv-edit-particular-heading" class="mt-1 text-[11px] font-semibold text-white/65"></p></div><button type="button" class="rounded-lg p-2 hover:bg-white/10" data-ecv-close="edit-particular"><i data-lucide="x" class="h-5 w-5"></i></button></div>
            <div class="ecv-scroll flex-1 overflow-y-auto p-6"><input id="ecv-edit-particular-id" type="hidden"><div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                <div class="space-y-1"><label class="ecv-label">Invoice No</label><input id="ecv-edit-invoice-no" class="ecv-field" type="text"></div>
                <div class="space-y-1 md:col-span-2 xl:col-span-3"><label class="ecv-label">Particulars</label><input id="ecv-edit-particulars" class="ecv-field" type="text" required></div>
                <div class="space-y-1"><label class="ecv-label">Unit</label><input id="ecv-edit-unit" class="ecv-field" type="text" placeholder="PC, SET, LOT..."></div>
                <div class="space-y-1"><label class="ecv-label">Unit Price</label><input id="ecv-edit-unit-price" class="ecv-field" type="number" min="0" step="0.01"></div>
                <div class="space-y-1"><label class="ecv-label">Qty</label><input id="ecv-edit-qty" class="ecv-field" type="number" min="0" step="0.0001"></div>
                <div class="space-y-1"><label class="ecv-label">Billing Date</label><input id="ecv-edit-billing-date" class="ecv-field" type="date"></div>
                <div class="space-y-1"><label class="ecv-label">Expense Type</label><select id="ecv-edit-expense-type" class="ecv-field"><option>Monthly</option><option>Yearly</option><option>One time</option></select></div>
                <div class="space-y-1"><label class="ecv-label">Amount</label><input id="ecv-edit-amount" class="ecv-field" type="number" min="0" step="0.01" required></div>
                <div class="space-y-1"><label class="ecv-label">Paid Amount</label><input id="ecv-edit-paid-amount" class="ecv-field" type="number" min="0" step="0.01" required></div>
                <div class="space-y-1"><label class="ecv-label">Payment Method</label><select id="ecv-edit-payment-method" class="ecv-field"><option>Gcash</option><option>Cash</option><option>Bank</option></select></div>
                <div id="ecv-edit-bank-fields" class="hidden md:col-span-2 xl:col-span-4 grid grid-cols-1 md:grid-cols-3 gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4"><div class="space-y-1"><label class="ecv-label">Account No.</label><input id="ecv-edit-account-no" class="ecv-field bg-white" type="text"></div><div class="space-y-1"><label class="ecv-label">Check Date</label><input id="ecv-edit-check-date" class="ecv-field bg-white" type="date"></div><div class="space-y-1"><label class="ecv-label">Check No.</label><input id="ecv-edit-check-no" class="ecv-field bg-white" type="text"></div></div>
            </div></div>
            <div class="flex justify-end gap-3 border-t border-slate-100 px-6 py-4"><button type="button" class="rounded-xl bg-slate-100 px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-600" data-ecv-close="edit-particular">Cancel</button><button type="submit" class="rounded-xl bg-slate-800 px-6 py-2.5 text-[10px] font-black uppercase tracking-widest text-white">Update Group</button></div>
        </form>
    </div>

    <div id="ecv-supplier-modal" class="fixed inset-0 z-[700] hidden items-center justify-center p-4"><div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" data-ecv-close="supplier"></div><div class="relative w-full max-w-2xl overflow-hidden rounded-xl bg-white shadow-2xl"><div class="flex items-center justify-between bg-maroon-900 px-6 py-4 text-white"><h3 class="text-sm font-black uppercase tracking-widest">Select Supplier</h3><button type="button" class="rounded-lg p-2 hover:bg-white/10" data-ecv-close="supplier"><i data-lucide="x" class="h-5 w-5"></i></button></div><div class="p-5"><input id="ecv-supplier-search" class="ecv-field" type="text" placeholder="Search supplier code or name"><div class="mt-4 max-h-[420px] overflow-y-auto rounded-xl border border-slate-200 ecv-scroll"><table class="w-full text-left"><thead class="sticky top-0 bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-500"><tr><th class="px-4 py-3">Supplier Code</th><th class="px-4 py-3">Supplier Name</th></tr></thead><tbody id="ecv-supplier-body" class="divide-y divide-slate-100 text-xs"></tbody></table></div></div></div></div>

    <div id="ecv-confirm-modal" class="fixed inset-0 z-[750] hidden items-center justify-center p-4"><div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"></div><div class="relative w-full max-w-md overflow-hidden rounded-xl bg-white shadow-2xl"><div class="p-6 text-center"><div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-amber-100"><i data-lucide="alert-triangle" class="h-7 w-7 text-amber-600"></i></div><h3 id="ecv-confirm-title" class="text-sm font-black uppercase tracking-widest text-slate-800"></h3><p id="ecv-confirm-message" class="mt-2 text-xs font-semibold text-slate-500"></p></div><div class="flex items-center justify-center gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4"><button type="button" id="ecv-confirm-cancel" class="rounded-xl bg-slate-200 px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-600">Cancel</button><button type="button" id="ecv-confirm-yes" class="rounded-xl bg-maroon-900 px-6 py-2.5 text-[10px] font-black uppercase tracking-widest text-white">Confirm</button></div></div></div>
    <div id="ecv-success-modal" class="fixed inset-0 z-[750] hidden items-center justify-center p-4"><div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"></div><div class="relative w-full max-w-md overflow-hidden rounded-xl bg-white shadow-2xl"><div class="p-6 text-center"><div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100"><i data-lucide="check-circle-2" class="h-7 w-7 text-emerald-600"></i></div><h3 id="ecv-success-title" class="text-sm font-black uppercase tracking-widest text-slate-800"></h3><p id="ecv-success-message" class="mt-2 text-xs font-semibold text-slate-500"></p></div><div class="flex justify-center border-t border-slate-100 bg-slate-50 px-6 py-4"><button type="button" id="ecv-success-ok" class="rounded-xl bg-emerald-600 px-8 py-2.5 text-[10px] font-black uppercase tracking-widest text-white">OK</button></div></div></div>
</div>

<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\hatdog\resources\views/partials/accounting/expense-cheque-voucher-content.blade.php ENDPATH**/ ?>