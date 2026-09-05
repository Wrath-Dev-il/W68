@push('styles')
    <link rel="stylesheet" href="{{ asset('css/audit-trail.css') }}?v={{ time() }}">
@endpush

@section('audit_trail_content')
    <div id="audit-trail-page" class="space-y-6" data-data-url="{{ route('admin.audit-trail.data') }}" data-purge-at="{{ $audit_purge_at ?? '' }}">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h2 class="text-lg font-extrabold text-slate-900 tracking-tight uppercase">Audit Trail</h2>
                    <p class="mt-1 text-xs text-slate-500 font-semibold">Track actions across modules and users.</p>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-11 h-11 rounded-2xl bg-maroon-900 flex items-center justify-center shadow-inner">
                        <i data-lucide="file-spreadsheet" class="w-6 h-6 text-goldlining-400"></i>
                    </div>
                </div>
            </div>

            <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50/80 px-4 py-3">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-widest text-amber-700">Deletion Timer</p>
                        <p id="audit-purge-status" class="mt-1 text-xs font-semibold text-amber-900">Waiting for scheduled auto delete.</p>
                    </div>
                    <div class="shrink-0 rounded-xl bg-white/80 border border-amber-200 px-4 py-2 text-center">
                        <p class="text-[10px] font-black uppercase tracking-widest text-amber-600">Countdown</p>
                        <p id="audit-purge-countdown" class="mt-1 text-sm font-extrabold tracking-wide text-amber-900">--:--:--</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-5">
            @foreach([
                ['key' => 'product', 'label' => 'Product', 'icon' => 'package'],
                ['key' => 'supplier', 'label' => 'Supplier', 'icon' => 'truck'],
                ['key' => 'customer', 'label' => 'Customer', 'icon' => 'users'],
                ['key' => 'forwarder', 'label' => 'Forwarder', 'icon' => 'plane'],
                ['key' => 'inventory', 'label' => 'Inventory', 'icon' => 'boxes'],
                ['key' => 'purchase_note', 'label' => 'Purchase Note', 'icon' => 'file-text'],
                ['key' => 'purchase_order', 'label' => 'Purchase Order', 'icon' => 'shopping-bag'],
                ['key' => 'purchase_return', 'label' => 'Purchase Return', 'icon' => 'undo-2'],
                ['key' => 'sales_note', 'label' => 'Sales Note', 'icon' => 'receipt'],
                ['key' => 'sales_order', 'label' => 'Sales Order', 'icon' => 'file-symlink'],
                ['key' => 'consign_inv', 'label' => 'Consign Inv.', 'icon' => 'file-digit'],
                ['key' => 'sales_return', 'label' => 'Sales Return', 'icon' => 'corner-up-left'],
                ['key' => 'waybill', 'label' => 'Waybill', 'icon' => 'map'],
                ['key' => 'payments', 'label' => 'Payments', 'icon' => 'credit-card'],
                ['key' => 'pay_check_v', 'label' => 'Pay. Check. V', 'icon' => 'book-check'],
                ['key' => 'exp_check_v', 'label' => 'Exp. Check. V', 'icon' => 'receipt'],
            ] as $card)
                <button type="button" class="audit-card bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between group hover:border-slate-200 transition-all hover:shadow-md hover:scale-[1.01] duration-200 overflow-hidden text-left" data-module="{{ $card['key'] }}">
                    <div class="space-y-0.5 min-w-0">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Module</span>
                        <h3 class="text-sm font-extrabold text-slate-800 tracking-tight truncate">{{ $card['label'] }}</h3>
                        <p class="text-[10px] text-slate-400 font-semibold">Click to filter table</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-maroon-900 flex items-center justify-center shadow-inner group-hover:scale-105 transition-transform duration-300 flex-shrink-0 ml-3">
                        <i data-lucide="{{ $card['icon'] }}" class="w-6 h-6 text-goldlining-400"></i>
                    </div>
                </button>
            @endforeach
        </div>

        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-50 bg-slate-50/50 flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <h3 class="text-xs font-bold text-slate-700 uppercase tracking-widest">Audit Logs</h3>
                    <p id="audit-active-filter" class="mt-1 text-[10px] text-slate-400 font-semibold truncate">Showing: All</p>
                </div>
                <div class="flex items-center gap-2">
                    <button id="audit-clear-filters" type="button" class="px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 text-[10px] font-black uppercase tracking-widest hover:border-slate-300">Clear Filters</button>
                    <button id="audit-refresh" type="button" class="px-4 py-2 rounded-xl bg-maroon-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-maroon-800">Refresh</button>
                </div>
            </div>

            <div class="overflow-auto audit-table-scroll">
                <table class="min-w-full divide-y divide-slate-100 text-xs">
                    <thead class="bg-white sticky top-0 z-10">
                        <tr class="bg-white">
                            <th class="px-4 py-3 text-left text-[10px] font-black text-slate-600 uppercase tracking-widest min-w-[260px]">
                                <span>Name</span>
                                <input id="audit-search-name" class="audit-col-search-input mt-2" placeholder="Search name">
                            </th>
                            <th class="px-4 py-3 text-left text-[10px] font-black text-slate-600 uppercase tracking-widest min-w-[200px]">
                                <span>User Name</span>
                                <input id="audit-search-username" class="audit-col-search-input mt-2" placeholder="Search user">
                            </th>
                            <th class="px-4 py-3 text-left text-[10px] font-black text-slate-600 uppercase tracking-widest w-[190px]">
                                <span>Time &amp; Date</span>
                                <input id="audit-search-datetime" class="audit-col-search-input mt-2" placeholder="YYYY-MM-DD">
                            </th>
                            <th class="px-4 py-3 text-left text-[10px] font-black text-slate-600 uppercase tracking-widest w-[160px]">
                                <span>Action</span>
                                <input id="audit-search-action" class="audit-col-search-input mt-2" placeholder="Search action">
                            </th>
                            <th class="px-4 py-3 text-left text-[10px] font-black text-slate-600 uppercase tracking-widest w-[170px]">
                                <span>Module</span>
                                <input id="audit-search-module" class="audit-col-search-input mt-2" placeholder="Search module">
                            </th>
                        </tr>
                    </thead>
                    <tbody id="audit-tbody" class="bg-white divide-y divide-slate-50">
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-slate-400 text-xs font-semibold">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-slate-100 bg-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="text-[10px] text-slate-500 font-semibold">
                    <span id="audit-pagination-label">Showing 0 to 0 of 0</span>
                    <span class="mx-2 text-slate-300">•</span>
                    <span class="uppercase tracking-widest font-black text-slate-400">50 / page</span>
                </div>
                <div class="flex items-center gap-2">
                    <button id="audit-prev" type="button" class="px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 text-[10px] font-black uppercase tracking-widest hover:border-slate-300 disabled:opacity-50 disabled:cursor-not-allowed">Prev</button>
                    <div class="px-4 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-700 text-[10px] font-black uppercase tracking-widest">
                        <span id="audit-page-label">1</span>
                        <span class="mx-1 text-slate-300">/</span>
                        <span id="audit-last-page-label">1</span>
                    </div>
                    <button id="audit-next" type="button" class="px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 text-[10px] font-black uppercase tracking-widest hover:border-slate-300 disabled:opacity-50 disabled:cursor-not-allowed">Next</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/audit-trail.js') }}?v={{ time() }}"></script>
@endpush

@include('partials.admin.admin_sidebar_navbar')
