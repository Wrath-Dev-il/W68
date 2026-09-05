<div id="page-expense-cheque-voucher" class="ecv-page space-y-6">
    <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4 bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
        <div>
            <p class="text-[10px] font-black uppercase tracking-widest text-maroon-700">Accounting</p>
            <h2 class="mt-1 text-xl font-black text-slate-900">Expense Cheque Voucher</h2>
            <p class="text-xs font-semibold text-slate-500 mt-1">Track monthly, yearly, and one-time expense vouchers.</p>
        </div>
        <button type="button" id="ecv-open-add-btn" class="inline-flex items-center justify-center gap-2 rounded-xl bg-maroon-900 px-5 py-3 text-[10px] font-black uppercase tracking-widest text-white shadow-sm hover:bg-maroon-800">
            <i data-lucide="plus" class="h-4 w-4 text-goldlining-400"></i>
            <span>Add Expense</span>
        </button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        @foreach ([
            ['id' => 'total', 'label' => 'Total Suppliers', 'icon' => 'receipt-text'],
            ['id' => 'monthly', 'label' => 'Monthly', 'icon' => 'calendar-days'],
            ['id' => 'yearly', 'label' => 'Yearly', 'icon' => 'calendar-range'],
            ['id' => 'one-time', 'label' => 'One Time', 'icon' => 'badge-check'],
        ] as $card)
            <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ $card['label'] }}</p>
                        <p id="ecv-stat-{{ $card['id'] }}" class="mt-3 text-2xl font-black text-slate-900">0</p>
                    </div>
                    <div class="ecv-icon-box"><i data-lucide="{{ $card['icon'] }}" class="h-5 w-5"></i></div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/70 px-5 py-4">
            <div>
                <h3 class="text-xs font-black uppercase tracking-widest text-slate-700">Expense Vouchers</h3>
                <p class="mt-1 text-[10px] font-semibold text-slate-400">Column filters apply before pagination.</p>
            </div>
            <span id="ecv-page-summary" class="text-[10px] font-black uppercase tracking-widest text-slate-400">Showing 0-0 of 0</span>
        </div>
        <div class="overflow-x-auto ecv-scroll">
            <table class="w-full min-w-[920px] text-left">
                <thead>
                    <tr class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-500">
                        <th class="px-4 py-4">Voucher No</th>
                        <th class="px-4 py-4">Name</th>
                        <th class="px-4 py-4 text-right">Total Amount</th>
                        <th class="px-4 py-4">Remarks</th>
                        <th class="px-4 py-4 text-center">Action</th>
                    </tr>
                    <tr class="border-y border-slate-100 bg-white">
                        <th class="px-4 py-2"><input class="ecv-col-search" data-ecv-filter="voucherNo" type="text" placeholder="Search voucher"></th>
                        <th class="px-4 py-2"><input class="ecv-col-search" data-ecv-filter="name" type="text" placeholder="Search name"></th>
                        <th class="px-4 py-2"><input class="ecv-col-search text-right" data-ecv-filter="totalAmount" type="text" placeholder="Search amount"></th>
                        <th class="px-4 py-2"><input class="ecv-col-search" data-ecv-filter="remarks" type="text" placeholder="Search remarks"></th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody id="ecv-table-body" class="divide-y divide-slate-100 text-xs"></tbody>
            </table>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-t border-slate-100 bg-slate-50/60 px-5 py-4">
            <p class="text-xs font-bold text-slate-500">50 data per page</p>
            <div id="ecv-pagination" class="flex flex-wrap items-center gap-1"></div>
        </div>
    </div>

    {{-- ADD EXPENSE MODAL --}}
    <div id="ecv-add-modal" class="fixed inset-0 z-[650] hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" data-ecv-close="add"></div>
        <form id="ecv-add-form" class="relative flex max-h-[94vh] w-full max-w-6xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
            <div class="flex items-center justify-between bg-maroon-900 px-6 py-4 text-white">
                <div class="flex items-center gap-3">
                    <div class="ecv-modal-icon"><i data-lucide="receipt" class="h-5 w-5"></i></div>
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-widest">Add Expense</h3>
                        <p class="mt-0.5 text-[11px] font-semibold text-white/60">Create one voucher with one or more particulars.</p>
                    </div>
                </div>
                <button type="button" class="rounded-lg p-2 hover:bg-white/10" data-ecv-close="add"><i data-lucide="x" class="h-5 w-5"></i></button>
            </div>

            <div class="ecv-scroll flex-1 overflow-y-auto p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="space-y-1">
                        <label class="ecv-label">Voucher No</label>
                        <span id="ecv-voucher-preview" class="ecv-tag">ECV-0001</span>
                    </div>
                    <div class="space-y-1">
                        <label class="ecv-label">Name</label>
                        <button type="button" id="ecv-open-supplier-btn" class="ecv-field flex items-center justify-between text-left">
                            <span id="ecv-supplier-name">Search supplier</span>
                            <i data-lucide="search" class="h-4 w-4 text-slate-400"></i>
                        </button>
                        <input id="ecv-supplier-code" type="hidden">
                    </div>
                    <div class="space-y-1">
                        <label for="ecv-remarks" class="ecv-label">Remarks</label>
                        <input id="ecv-remarks" class="ecv-field" type="text" placeholder="Voucher remarks">
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3 border-t border-slate-100 pt-5">
                    <div>
                        <h4 class="text-xs font-black uppercase tracking-widest text-slate-700">Particulars</h4>
                        <p class="mt-1 text-[10px] font-semibold text-slate-400">Each group is saved under the same Voucher No.</p>
                    </div>
                    <button type="button" id="ecv-add-particular-btn" class="inline-flex items-center gap-2 rounded-xl bg-slate-800 px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-white hover:bg-slate-700">
                        <i data-lucide="plus" class="h-4 w-4"></i><span>Add</span>
                    </button>
                </div>
                <div id="ecv-add-particulars-container" class="space-y-4"></div>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4">
                <button type="button" class="rounded-xl bg-slate-200 px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-600 hover:bg-slate-300" data-ecv-close="add">Cancel</button>
                <button type="submit" class="rounded-xl bg-maroon-900 px-6 py-2.5 text-[10px] font-black uppercase tracking-widest text-white hover:bg-maroon-800">Save Expense</button>
            </div>
        </form>
    </div>

    {{-- VIEW VOUCHER MODAL --}}
    <div id="ecv-view-modal" class="fixed inset-0 z-[675] hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" data-ecv-close="view"></div>
        <div class="relative flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
            <div class="flex items-center justify-between bg-maroon-900 px-6 py-4 text-white">
                <div>
                    <h3 class="text-sm font-black uppercase tracking-widest">Expense Voucher Details</h3>
                    <p id="ecv-view-heading" class="mt-0.5 text-[11px] font-semibold text-white/60">---</p>
                </div>
                <button type="button" class="rounded-lg p-2 hover:bg-white/10" data-ecv-close="view"><i data-lucide="x" class="h-5 w-5"></i></button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 border-b border-slate-100 bg-slate-50 px-6 py-4 text-xs">
                <div><span class="ecv-label">Voucher No</span><strong id="ecv-view-voucher-no" class="mt-1 block text-slate-800">---</strong></div>
                <div><span class="ecv-label">Name</span><strong id="ecv-view-name" class="mt-1 block text-slate-800">---</strong></div>
                <div><span class="ecv-label">Remarks</span><strong id="ecv-view-remarks" class="mt-1 block text-slate-800">---</strong></div>
            </div>
            <div class="ecv-scroll flex-1 overflow-auto p-6">
                <table class="w-full min-w-[900px] text-left">
                    <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Particular</th>
                            <th class="px-4 py-3">Billing Date</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3 text-right">Paid Amount</th>
                            <th class="px-4 py-3">Payment Method</th>
                            <th class="px-4 py-3 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="ecv-view-items-body" class="divide-y divide-slate-100 text-xs"></tbody>
                </table>
            </div>
            <div class="flex items-center justify-end border-t border-slate-100 bg-slate-50 px-6 py-4">
                <button type="button" class="rounded-xl bg-slate-200 px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-600 hover:bg-slate-300" data-ecv-close="view">Close</button>
            </div>
        </div>
    </div>

    {{-- ADD MORE PARTICULARS MODAL --}}
    <div id="ecv-add-more-modal" class="fixed inset-0 z-[690] hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" data-ecv-close="add-more"></div>
        <form id="ecv-add-more-form" class="relative flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
            <div class="flex items-center justify-between bg-maroon-900 px-6 py-4 text-white">
                <div>
                    <h3 class="text-sm font-black uppercase tracking-widest">Add More Particulars</h3>
                    <p id="ecv-add-more-heading" class="mt-0.5 text-[11px] font-semibold text-white/60">---</p>
                </div>
                <button type="button" class="rounded-lg p-2 hover:bg-white/10" data-ecv-close="add-more"><i data-lucide="x" class="h-5 w-5"></i></button>
            </div>
            <div class="ecv-scroll flex-1 overflow-y-auto p-6 space-y-4">
                <div class="flex justify-end">
                    <button type="button" id="ecv-add-more-row-btn" class="inline-flex items-center gap-2 rounded-xl bg-slate-800 px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-white hover:bg-slate-700">
                        <i data-lucide="plus" class="h-4 w-4"></i><span>Add More</span>
                    </button>
                </div>
                <div id="ecv-add-more-container" class="space-y-4"></div>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4">
                <button type="button" class="rounded-xl bg-slate-200 px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-600 hover:bg-slate-300" data-ecv-close="add-more">Cancel</button>
                <button type="submit" class="rounded-xl bg-emerald-600 px-6 py-2.5 text-[10px] font-black uppercase tracking-widest text-white hover:bg-emerald-700">Save</button>
            </div>
        </form>
    </div>

    {{-- SELECT SUPPLIER MODAL --}}
    <div id="ecv-supplier-modal" class="fixed inset-0 z-[700] hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" data-ecv-close="supplier"></div>
        <div class="relative w-full max-w-2xl overflow-hidden rounded-xl bg-white shadow-2xl">
            <div class="flex items-center justify-between bg-maroon-900 px-6 py-4 text-white">
                <div>
                    <h3 class="text-sm font-black uppercase tracking-widest">Select Supplier</h3>
                    <p class="mt-0.5 text-[11px] font-semibold text-white/60">Choose the supplier for this expense.</p>
                </div>
                <button type="button" class="rounded-lg p-2 hover:bg-white/10" data-ecv-close="supplier"><i data-lucide="x" class="h-5 w-5"></i></button>
            </div>
            <div class="p-5">
                <input id="ecv-supplier-search" class="ecv-field" type="text" placeholder="Search supplier code or name">
                <div class="mt-4 max-h-[420px] overflow-y-auto rounded-xl border border-slate-200 ecv-scroll">
                    <table class="w-full text-left">
                        <thead class="sticky top-0 bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-500">
                            <tr><th class="px-4 py-3">Supplier Code</th><th class="px-4 py-3">Supplier Name</th></tr>
                        </thead>
                        <tbody id="ecv-supplier-body" class="divide-y divide-slate-100 text-xs"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- CONFIRM MODAL --}}
    <div id="ecv-confirm-modal" class="fixed inset-0 z-[750] hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"></div>
        <div class="relative w-full max-w-md overflow-hidden rounded-xl bg-white shadow-2xl">
            <div class="p-6 text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-amber-100"><i data-lucide="alert-triangle" class="h-7 w-7 text-amber-600"></i></div>
                <h3 id="ecv-confirm-title" class="text-sm font-black uppercase tracking-widest text-slate-800">Confirm Action</h3>
                <p id="ecv-confirm-message" class="mt-2 text-xs font-semibold text-slate-500">Are you sure you want to proceed?</p>
            </div>
            <div class="flex items-center justify-center gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4">
                <button type="button" id="ecv-confirm-cancel" class="rounded-xl bg-slate-200 px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-600 hover:bg-slate-300">Cancel</button>
                <button type="button" id="ecv-confirm-yes" class="rounded-xl bg-maroon-900 px-6 py-2.5 text-[10px] font-black uppercase tracking-widest text-white hover:bg-maroon-800">Confirm</button>
            </div>
        </div>
    </div>

    {{-- SUCCESS MODAL --}}
    <div id="ecv-success-modal" class="fixed inset-0 z-[760] hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"></div>
        <div class="relative w-full max-w-md overflow-hidden rounded-xl bg-white shadow-2xl">
            <div class="p-6 text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100"><i data-lucide="check-circle-2" class="h-7 w-7 text-emerald-600"></i></div>
                <h3 id="ecv-success-title" class="text-sm font-black uppercase tracking-widest text-slate-800">Success</h3>
                <p id="ecv-success-message" class="mt-2 text-xs font-semibold text-slate-500">Operation completed successfully.</p>
            </div>
            <div class="flex items-center justify-center border-t border-slate-100 bg-slate-50 px-6 py-4">
                <button type="button" id="ecv-success-ok" class="rounded-xl bg-emerald-600 px-8 py-2.5 text-[10px] font-black uppercase tracking-widest text-white hover:bg-emerald-700">OK</button>
            </div>
        </div>
    </div>
</div>
