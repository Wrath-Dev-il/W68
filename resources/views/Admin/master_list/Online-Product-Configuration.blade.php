@push('styles')
    <link rel="stylesheet" href="{{ asset('css/online_product_config.css') }}?v={{ time() }}">
@endpush

@section('prod_online_config_content')

<div id="page-prod-online-config-root" class="space-y-6 animate-fade-in">

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
        <p class="text-[10px] font-black uppercase tracking-widest text-maroon-700 mb-1">Item Converter</p>
        <h2 class="text-xl font-black text-slate-900">Online To System Item Converter</h2>
    </div>

    <div id="online-config-dashboard" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">System Products</p>
            <p id="dashboard-system-products" class="mt-2 text-2xl font-black text-slate-900">...</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Online Products</p>
            <p id="dashboard-online-products" class="mt-2 text-2xl font-black text-slate-900">...</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Total Converted Products</p>
            <p id="dashboard-total-converted-products" class="mt-2 text-2xl font-black text-maroon-900">...</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Online Not Yet Converted Products</p>
            <p id="dashboard-online-not-yet-converted-products" class="mt-2 text-2xl font-black text-amber-600">...</p>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <button onclick="switchTab('system')" id="tab-system" class="tab-btn px-4 py-2 rounded-lg bg-maroon-900 text-white text-[10px] font-black uppercase tracking-widest">System Product</button>
        <button onclick="switchTab('online')" id="tab-online" class="tab-btn px-4 py-2 rounded-lg bg-white border border-slate-200 text-slate-600 text-[10px] font-black uppercase tracking-widest">Online Product</button>
        <button onclick="switchTab('converted')" id="tab-converted" class="tab-btn px-4 py-2 rounded-lg bg-white border border-slate-200 text-slate-600 text-[10px] font-black uppercase tracking-widest">Converted Product</button>
        <p id="sync-shopee-prices-status" class="ml-auto basis-full text-[10px] font-black uppercase tracking-widest text-slate-400 sm:basis-auto">Shopee auto sync: checking...</p>
    </div>

    <div id="tab-system-content" class="tab-content">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100 flex items-center gap-3">
                <div class="relative flex-1 max-w-xs">
                    <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                    <input type="text" id="system-search" placeholder="Search system product..." class="w-full pl-9 pr-3 py-2 border border-slate-200 rounded-lg text-xs font-bold outline-none focus:border-maroon-700 focus:ring-4 focus:ring-maroon-900/5">
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left">
                    <thead>
                        <tr class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-500 border-b border-slate-200">
                            <th class="px-4 py-4">Product Code</th>
                            <th class="px-4 py-4">Part No#</th>
                            <th class="px-4 py-4">Description</th>
                            <th class="px-4 py-4">Application</th>
                            <th class="px-4 py-4">Price Online</th>
                            <th class="px-4 py-4">Action</th>
                        </tr>
                        <tr class="bg-white border-b border-slate-200">
                            <th class="px-4 py-2"><input type="text" data-table="system" data-filter="product_code" placeholder="Search code..." class="column-filter w-full rounded-lg border border-slate-200 px-2 py-1.5 text-[10px] font-bold normal-case tracking-normal outline-none focus:border-maroon-700"></th>
                            <th class="px-4 py-2"><input type="text" data-table="system" data-filter="part_number" placeholder="Search part..." class="column-filter w-full rounded-lg border border-slate-200 px-2 py-1.5 text-[10px] font-bold normal-case tracking-normal outline-none focus:border-maroon-700"></th>
                            <th class="px-4 py-2"><input type="text" data-table="system" data-filter="description" placeholder="Search desc..." class="column-filter w-full rounded-lg border border-slate-200 px-2 py-1.5 text-[10px] font-bold normal-case tracking-normal outline-none focus:border-maroon-700"></th>
                            <th class="px-4 py-2"><input type="text" data-table="system" data-filter="application" placeholder="Search app..." class="column-filter w-full rounded-lg border border-slate-200 px-2 py-1.5 text-[10px] font-bold normal-case tracking-normal outline-none focus:border-maroon-700"></th>
                            <th class="px-4 py-2"><input type="text" data-table="system" data-filter="price_online" placeholder="Search price..." class="column-filter w-full rounded-lg border border-slate-200 px-2 py-1.5 text-[10px] font-bold normal-case tracking-normal outline-none focus:border-maroon-700"></th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody id="system-tbody" class="divide-y divide-slate-100 text-xs">
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400 text-xs font-bold">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
            <div id="system-pagination" class="px-4 py-3 border-t border-slate-100 flex flex-col gap-3 bg-slate-50/60 hidden sm:flex-row sm:items-center sm:justify-between">
                <p id="system-info" class="text-xs font-bold text-slate-500"></p>
                <div class="flex min-w-0 items-center gap-1">
                    <button onclick="systemPage('first')" id="system-first" class="shrink-0 px-3 py-2 rounded-lg text-[10px] font-black border bg-white border-slate-200 text-slate-400">First</button>
                    <button onclick="systemPage('prev')" id="system-prev" class="shrink-0 px-3 py-2 rounded-lg text-[10px] font-black border bg-white border-slate-200 text-slate-400">Prev</button>
                    <div class="min-w-0 max-w-full overflow-x-auto whitespace-nowrap py-1 sm:max-w-[520px]">
                        <span id="system-pages" class="inline-flex items-center gap-1"></span>
                    </div>
                    <button onclick="systemPage('next')" id="system-next" class="shrink-0 px-3 py-2 rounded-lg text-[10px] font-black border bg-white border-slate-200 text-slate-400">Next</button>
                    <button onclick="systemPage('last')" id="system-last" class="shrink-0 px-3 py-2 rounded-lg text-[10px] font-black border bg-white border-slate-200 text-slate-400">Last</button>
                </div>
            </div>
        </div>
    </div>

    <div id="tab-online-content" class="tab-content hidden">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100 flex items-center gap-3">
                <div class="relative flex-1 max-w-xs">
                    <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                    <input type="text" id="online-search" placeholder="Search online product..." class="w-full pl-9 pr-3 py-2 border border-slate-200 rounded-lg text-xs font-bold outline-none focus:border-maroon-700 focus:ring-4 focus:ring-maroon-900/5">
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[600px] text-left">
                    <thead>
                        <tr class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-500 border-b border-slate-200">
                            <th class="px-4 py-4">Product Name</th>
                            <th class="px-4 py-4">Category</th>
                            <th class="px-4 py-4">Price Online</th>
                            <th class="px-4 py-4">Action</th>
                        </tr>
                        <tr class="bg-white border-b border-slate-200">
                            <th class="px-4 py-2"><input type="text" data-table="online" data-filter="name" placeholder="Search name..." class="column-filter w-full rounded-lg border border-slate-200 px-2 py-1.5 text-[10px] font-bold normal-case tracking-normal outline-none focus:border-maroon-700"></th>
                            <th class="px-4 py-2"><input type="text" data-table="online" data-filter="category" placeholder="Search category..." class="column-filter w-full rounded-lg border border-slate-200 px-2 py-1.5 text-[10px] font-bold normal-case tracking-normal outline-none focus:border-maroon-700"></th>
                            <th class="px-4 py-2"><input type="text" data-table="online" data-filter="price" placeholder="Search price..." class="column-filter w-full rounded-lg border border-slate-200 px-2 py-1.5 text-[10px] font-bold normal-case tracking-normal outline-none focus:border-maroon-700"></th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody id="online-tbody" class="divide-y divide-slate-100 text-xs">
                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400 text-xs font-bold">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
            <div id="online-pagination" class="px-4 py-3 border-t border-slate-100 flex flex-col gap-3 bg-slate-50/60 hidden sm:flex-row sm:items-center sm:justify-between">
                <p id="online-info" class="text-xs font-bold text-slate-500"></p>
                <div class="flex min-w-0 items-center gap-1">
                    <button onclick="onlinePage('first')" id="online-first" class="shrink-0 px-3 py-2 rounded-lg text-[10px] font-black border bg-white border-slate-200 text-slate-400">First</button>
                    <button onclick="onlinePage('prev')" id="online-prev" class="shrink-0 px-3 py-2 rounded-lg text-[10px] font-black border bg-white border-slate-200 text-slate-400">Prev</button>
                    <div class="min-w-0 max-w-full overflow-x-auto whitespace-nowrap py-1 sm:max-w-[520px]">
                        <span id="online-pages" class="inline-flex items-center gap-1"></span>
                    </div>
                    <button onclick="onlinePage('next')" id="online-next" class="shrink-0 px-3 py-2 rounded-lg text-[10px] font-black border bg-white border-slate-200 text-slate-400">Next</button>
                    <button onclick="onlinePage('last')" id="online-last" class="shrink-0 px-3 py-2 rounded-lg text-[10px] font-black border bg-white border-slate-200 text-slate-400">Last</button>
                </div>
            </div>
        </div>
    </div>

    <div id="tab-converted-content" class="tab-content hidden">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100 flex items-center gap-3">
                <div class="relative flex-1 max-w-xs">
                    <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                    <input type="text" id="converted-search" placeholder="Search converted product..." class="w-full pl-9 pr-3 py-2 border border-slate-200 rounded-lg text-xs font-bold outline-none focus:border-maroon-700 focus:ring-4 focus:ring-maroon-900/5">
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1050px] text-left">
                    <thead>
                        <tr class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-500 border-b border-slate-200">
                            <th class="px-4 py-4" colspan="3">System Product</th>
                            <th class="px-4 py-4 w-12 text-center">-&gt;</th>
                            <th class="px-4 py-4" colspan="3">Online Product</th>
                        </tr>
                        <tr class="bg-slate-50/50 text-[10px] font-black uppercase tracking-widest text-slate-400 border-b border-slate-200">
                            <th class="px-4 py-2">Product Name</th>
                            <th class="px-4 py-2">Part No#</th>
                            <th class="px-4 py-2">Price</th>
                            <th class="px-4 py-2 text-center">-&gt;</th>
                            <th class="px-4 py-2">Product Name</th>
                            <th class="px-4 py-2">Category</th>
                            <th class="px-4 py-2">Price Online</th>
                        </tr>
                        <tr class="bg-white border-b border-slate-200">
                            <th class="px-4 py-2"><input type="text" data-table="converted" data-filter="system_name" placeholder="Search system..." class="column-filter w-full rounded-lg border border-slate-200 px-2 py-1.5 text-[10px] font-bold normal-case tracking-normal outline-none focus:border-maroon-700"></th>
                            <th class="px-4 py-2"><input type="text" data-table="converted" data-filter="system_part_number" placeholder="Search part..." class="column-filter w-full rounded-lg border border-slate-200 px-2 py-1.5 text-[10px] font-bold normal-case tracking-normal outline-none focus:border-maroon-700"></th>
                            <th class="px-4 py-2"><input type="text" data-table="converted" data-filter="system_price" placeholder="Search price..." class="column-filter w-full rounded-lg border border-slate-200 px-2 py-1.5 text-[10px] font-bold normal-case tracking-normal outline-none focus:border-maroon-700"></th>
                            <th class="px-4 py-2"></th>
                            <th class="px-4 py-2"><input type="text" data-table="converted" data-filter="online_name" placeholder="Search online..." class="column-filter w-full rounded-lg border border-slate-200 px-2 py-1.5 text-[10px] font-bold normal-case tracking-normal outline-none focus:border-maroon-700"></th>
                            <th class="px-4 py-2"><input type="text" data-table="converted" data-filter="online_category" placeholder="Search category..." class="column-filter w-full rounded-lg border border-slate-200 px-2 py-1.5 text-[10px] font-bold normal-case tracking-normal outline-none focus:border-maroon-700"></th>
                            <th class="px-4 py-2"><input type="text" data-table="converted" data-filter="online_price" placeholder="Search price..." class="column-filter w-full rounded-lg border border-slate-200 px-2 py-1.5 text-[10px] font-bold normal-case tracking-normal outline-none focus:border-maroon-700"></th>
                        </tr>
                    </thead>
                    <tbody id="converted-tbody" class="divide-y divide-slate-100 text-xs">
                        <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400 text-xs font-bold">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
            <div id="converted-pagination" class="px-4 py-3 border-t border-slate-100 flex flex-col gap-3 bg-slate-50/60 hidden sm:flex-row sm:items-center sm:justify-between">
                <p id="converted-info" class="text-xs font-bold text-slate-500"></p>
                <div class="flex min-w-0 items-center gap-1">
                    <button onclick="convertedPage('first')" id="converted-first" class="shrink-0 px-3 py-2 rounded-lg text-[10px] font-black border bg-white border-slate-200 text-slate-400">First</button>
                    <button onclick="convertedPage('prev')" id="converted-prev" class="shrink-0 px-3 py-2 rounded-lg text-[10px] font-black border bg-white border-slate-200 text-slate-400">Prev</button>
                    <div class="min-w-0 max-w-full overflow-x-auto whitespace-nowrap py-1 sm:max-w-[520px]">
                        <span id="converted-pages" class="inline-flex items-center gap-1"></span>
                    </div>
                    <button onclick="convertedPage('next')" id="converted-next" class="shrink-0 px-3 py-2 rounded-lg text-[10px] font-black border bg-white border-slate-200 text-slate-400">Next</button>
                    <button onclick="convertedPage('last')" id="converted-last" class="shrink-0 px-3 py-2 rounded-lg text-[10px] font-black border bg-white border-slate-200 text-slate-400">Last</button>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Convert Modal -->
<div id="convert-modal" class="fixed inset-0 z-[900] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeConvertModal()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
            <h3 class="text-sm font-black text-slate-800">Convert Items</h3>
            <button onclick="closeConvertModal()" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <div class="flex-1 overflow-y-auto p-5">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="border border-slate-200 rounded-xl p-5">
                    <h4 id="convert-left-title" class="text-xs font-black uppercase tracking-widest text-maroon-800 mb-4">System Product</h4>
                    <div class="space-y-3" id="convert-left-fields"></div>
                </div>
                <div class="border border-slate-200 rounded-xl p-5">
                    <h4 id="convert-right-title" class="text-xs font-black uppercase tracking-widest text-maroon-800 mb-4">Online Product</h4>
                    <div class="relative mb-3">
                        <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                        <input type="text" id="convert-search-input" placeholder="Search product..." class="w-full pl-9 pr-3 py-2.5 border border-slate-200 rounded-lg text-xs font-bold outline-none focus:border-maroon-700 focus:ring-4 focus:ring-maroon-900/5">
                    </div>
                    <div id="convert-right-fields" class="space-y-3"></div>
                    <button onclick="openSearchModal()" class="mt-4 w-full px-4 py-2.5 rounded-lg bg-maroon-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-maroon-800 transition-all">Search & Select</button>
                </div>
            </div>
        </div>
        <div class="px-5 py-4 border-t border-slate-200 flex justify-end gap-3">
            <button onclick="closeConvertModal()" class="px-6 py-2.5 rounded-lg bg-slate-100 text-slate-600 text-[10px] font-black uppercase tracking-widest hover:bg-slate-200">Cancel</button>
            <button onclick="confirmConvert()" class="px-6 py-2.5 rounded-lg bg-maroon-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-maroon-800">Confirm Convert</button>
        </div>
    </div>
</div>

<!-- Search Modal -->
<div id="search-modal" class="fixed inset-0 z-[950] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeSearchModal()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[80vh] overflow-hidden flex flex-col">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
            <h3 id="search-modal-title" class="text-sm font-black text-slate-800">Search Products</h3>
            <button onclick="closeSearchModal()" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <div class="p-4 border-b border-slate-100">
            <div class="relative">
                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                <input type="text" id="search-modal-input" placeholder="Search by name or category..." class="w-full pl-9 pr-3 py-2.5 border border-slate-200 rounded-lg text-xs font-bold outline-none focus:border-maroon-700 focus:ring-4 focus:ring-maroon-900/5">
            </div>
        </div>
        <div class="overflow-y-auto flex-1">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-500 border-b border-slate-200 sticky top-0">
                        <th class="px-4 py-3" id="search-th-1">Product Name</th>
                        <th class="px-4 py-3" id="search-th-2">Category</th>
                        <th class="px-4 py-3 text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="search-modal-tbody" class="divide-y divide-slate-100 text-xs">
                    <tr><td colspan="3" class="px-4 py-8 text-center text-slate-400 text-xs font-bold">Type to search...</td></tr>
                </tbody>
            </table>
        </div>
        <div id="search-pagination" class="hidden px-4 py-3 border-t border-slate-100 bg-slate-50/60">
            <p id="search-info" class="text-xs font-bold text-slate-500 mb-2"></p>
            <div class="flex items-center gap-1">
                <button onclick="searchPage('prev')" id="search-prev" class="shrink-0 px-3 py-2 rounded-lg text-[10px] font-black border bg-white border-slate-200 text-slate-400">Prev</button>
                <span id="search-pages" class="flex items-center gap-1 overflow-x-auto max-w-[500px] py-1"></span>
                <button onclick="searchPage('next')" id="search-next" class="shrink-0 px-3 py-2 rounded-lg text-[10px] font-black border bg-white border-slate-200 text-slate-400">Next</button>
            </div>
        </div>
    </div>
</div>

<script>
const routeBase = "{{ url('/admin/masterlist/online-product-config') }}";
const PER_PAGE = 20;
const SEARCH_PER_PAGE = 10;
const SHOPEE_SYNC_INTERVAL_SECONDS = 60;

// --- State per tab ---
let systemState = { data: [], page: 1, lastPage: 1, total: 0, search: '', filters: {}, loading: false };
let onlineState  = { data: [], page: 1, lastPage: 1, total: 0, search: '', filters: {}, loading: false, error: '', source: 'database', cursors: [] };
let convertedState = { data: [], page: 1, lastPage: 1, total: 0, search: '', filters: {}, loading: false };
let searchState = { data: [], page: 1, lastPage: 1, total: 0, search: '', loading: false, cursors: [] };
onlineState.cursors[1] = '';
searchState.cursors[1] = ''; // page 1 has no cursor

const dashboardCountMap = {
    system_products: 'dashboard-system-products',
    online_products: 'dashboard-online-products',
    total_converted_products: 'dashboard-total-converted-products',
    online_not_yet_converted_products: 'dashboard-online-not-yet-converted-products',
};

function appendColumnFilters(params, filters) {
    Object.entries(filters || {}).forEach(([key, value]) => {
        const cleanValue = String(value || '').trim();
        if (cleanValue !== '') params.set('filters[' + key + ']', cleanValue);
    });
}

function bindColumnFilters(table, state, loadFn) {
    let timeout;
    document.querySelectorAll('.column-filter[data-table="' + table + '"]').forEach(input => {
        input.addEventListener('input', function () {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                state.filters[this.dataset.filter] = this.value;
                state.page = 1;
                if (table === 'online') {
                    state.error = '';
                    state.cursors = [];
                    state.cursors[1] = '';
                }
                loadFn();
            }, 250);
        });
    });
}

function formatCount(value) {
    return new Intl.NumberFormat().format(Number(value || 0));
}

function formatMoney(value) {
    if (value === null || value === undefined || value === '') return '-';
    const amount = Number(value);
    if (!Number.isFinite(amount)) return '-';
    return '&#8369;' + amount.toFixed(2);
}

function setDashboardCounts(counts) {
    Object.entries(dashboardCountMap).forEach(([key, id]) => {
        const el = document.getElementById(id);
        if (el) el.textContent = formatCount(counts[key]);
    });
}

function loadDashboardCounts() {
    fetch(routeBase + '/dashboard-counts')
        .then(r => r.json())
        .then(res => {
            if (!res.success) return;
            setDashboardCounts(res.counts || res);
        })
        .catch(() => {
            Object.values(dashboardCountMap).forEach(id => {
                const el = document.getElementById(id);
                if (el) el.textContent = '0';
            });
        });
}

function formatShopeeSyncStatus(status, fallback = 'Shopee auto sync: waiting...') {
    if (!status) return fallback;
    const latest = status.latest || status;
    const webhook = status.webhook || (latest.mode === 'webhook' ? latest : null);
    const fallbackStatus = status.fallback || (latest.mode === 'fallback' ? latest : null);
    const lastWebhook = webhook?.finished_at || 'none yet';
    const lastFallback = fallbackStatus?.finished_at || latest.finished_at || 'none yet';
    const lastItem = webhook?.item_id
        ? `SHOPEE-${webhook.item_id}${webhook.item_price !== undefined && webhook.item_price !== null ? ' @ PHP ' + Number(webhook.item_price).toFixed(2) : ''}`
        : 'none yet';
    const webhookErrors = (webhook?.summary?.errors || []).length;
    const fallbackErrors = (fallbackStatus?.summary?.errors || []).length;
    const latestErrors = (latest?.summary?.errors || []).length;
    const errors = webhookErrors + fallbackErrors || latestErrors;

    return `Shopee webhook sync active | Last webhook sync: ${lastWebhook} | Last fallback sync: ${lastFallback} | Last updated item: ${lastItem} | Errors: ${errors}`;
}

function normalizeShopeeSyncStatusPayload(data, latestOverride = null) {
    const latest = latestOverride || data?.status || data?.latest || data;
    return {
        latest,
        webhook: data?.webhook || latest?.webhook || (latest?.mode === 'webhook' ? latest : null),
        fallback: data?.fallback || latest?.fallback || (latest?.mode === 'fallback' ? latest : null),
    };
}

function formatShopeeCountdown(seconds) {
    const remaining = Math.max(0, Number(seconds) || 0);
    const minutes = Math.floor(remaining / 60);
    const secs = remaining % 60;
    return `${minutes}m ${String(secs).padStart(2, '0')}s`;
}

function parseShopeeManilaTimestamp(timestamp) {
    if (!timestamp || typeof timestamp !== 'string') return null;
    const match = timestamp.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})$/);
    if (!match) return null;
    const [, year, month, day, hour, minute, second] = match.map(Number);
    return new Date(year, month - 1, day, hour, minute, second);
}

function resolveShopeeCountdownSeconds(data, status) {
    if (Number.isFinite(Number(data?.seconds_until_stale))) {
        return Math.max(0, Number(data.seconds_until_stale));
    }

    const finishedAt = parseShopeeManilaTimestamp(status?.finished_at);
    if (!finishedAt) return null;

    const nextSyncAt = finishedAt.getTime() + (SHOPEE_SYNC_INTERVAL_SECONDS * 1000);
    return Math.max(0, Math.ceil((nextSyncAt - Date.now()) / 1000));
}

function renderShopeeSyncStatus() {
    const status = document.getElementById('sync-shopee-prices-status');
    if (status) {
        const timerText = Number.isFinite(shopeeSyncCountdownSeconds)
            ? ' | next check in ' + formatShopeeCountdown(shopeeSyncCountdownSeconds)
            : '';
        const statusText = shopeeSyncBaseStatusText + timerText;
        status.textContent = statusText;
        status.classList.toggle('hidden', !statusText);
    }
}

function stopShopeeSyncCountdown() {
    if (shopeeSyncCountdownTimer) {
        clearInterval(shopeeSyncCountdownTimer);
        shopeeSyncCountdownTimer = null;
    }
}

function scheduleShopeeSyncRetry(seconds = 15) {
    if (shopeeSyncRetryTimer) {
        clearTimeout(shopeeSyncRetryTimer);
    }

    shopeeSyncRetryTimer = setTimeout(() => {
        shopeeSyncRetryTimer = null;
        refreshShopeeSyncStatus();
    }, Math.max(1, Number(seconds) || 15) * 1000);
}

function startShopeeSyncCountdown(seconds) {
    stopShopeeSyncCountdown();
    if (shopeeSyncRetryTimer) {
        clearTimeout(shopeeSyncRetryTimer);
        shopeeSyncRetryTimer = null;
    }
    shopeeSyncCountdownSeconds = Number.isFinite(Number(seconds)) ? Math.max(0, Math.floor(Number(seconds))) : null;
    renderShopeeSyncStatus();

    if (!Number.isFinite(shopeeSyncCountdownSeconds)) return;

    shopeeSyncCountdownTimer = setInterval(() => {
        shopeeSyncCountdownSeconds = Math.max(0, shopeeSyncCountdownSeconds - 1);
        renderShopeeSyncStatus();

        if (shopeeSyncCountdownSeconds <= 0) {
            stopShopeeSyncCountdown();
            refreshShopeeSyncStatus();
        }
    }, 1000);
}

function setShopeeSyncStatus(statusText = '', countdownSeconds = null) {
    shopeeSyncBaseStatusText = statusText || '';
    if (countdownSeconds === null) {
        shopeeSyncCountdownSeconds = null;
        stopShopeeSyncCountdown();
        renderShopeeSyncStatus();
        return;
    }

    startShopeeSyncCountdown(countdownSeconds);
}

function refreshShopeeSyncStatus() {
    if (shopeeSyncRefreshInFlight) return;
    shopeeSyncRefreshInFlight = true;
    fetch(routeBase + '/shopee-sync-status')
        .then(response => response.json())
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Unable to load Shopee sync status.');
            if (data.is_stale) {
                setShopeeSyncStatus('Shopee auto sync: syncing latest prices...', null);
                return fetch(routeBase + '/shopee-sync-auto', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                }).then(response => response.json())
                    .then(syncData => {
                        const status = normalizeShopeeSyncStatusPayload({
                            ...data,
                            ...syncData,
                            webhook: syncData.webhook || data.webhook,
                            fallback: syncData.fallback || (syncData.status?.mode === 'fallback' ? syncData.status : data.fallback),
                        }, syncData.status || data.status);

                        if (syncData.locked) {
                            setShopeeSyncStatus('Shopee auto sync: already running in the background. Checking again in 15s...', null);
                            scheduleShopeeSyncRetry(15);
                            return;
                        }

                        const syncCountdownSeconds = resolveShopeeCountdownSeconds(syncData, status.fallback || status.latest);
                        setShopeeSyncStatus(formatShopeeSyncStatus(status, syncData.message || 'Shopee auto sync finished.'), syncCountdownSeconds);
                        if (syncData.ran) {
                            loadDashboardCounts();
                            loadSystem();
                            loadOnline();
                            loadConverted();
                        }
                    });
            }

            const status = normalizeShopeeSyncStatusPayload(data);
            const countdownSeconds = resolveShopeeCountdownSeconds(data, status.fallback || status.latest);
            setShopeeSyncStatus(formatShopeeSyncStatus(status), countdownSeconds);
            return null;
        })
        .catch(error => {
            setShopeeSyncStatus('Shopee auto sync: ' + (error.message || 'status unavailable'), null);
        })
        .finally(() => {
            shopeeSyncRefreshInFlight = false;
        });
}

function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(el => {
        el.className = 'tab-btn px-4 py-2 rounded-lg bg-white border border-slate-200 text-slate-600 text-[10px] font-black uppercase tracking-widest';
    });
    document.getElementById('tab-' + tab).className = 'tab-btn px-4 py-2 rounded-lg bg-maroon-900 text-white text-[10px] font-black uppercase tracking-widest';
    document.getElementById('tab-' + tab + '-content').classList.remove('hidden');
    if (tab === 'system') loadSystem();
    if (tab === 'online') loadOnline();
    if (tab === 'converted') loadConverted();
}

let systemSearchTimeout, onlineSearchTimeout, convertedSearchTimeout;
let shopeeSyncCountdownTimer = null;
let shopeeSyncRetryTimer = null;
let shopeeSyncCountdownSeconds = null;
let shopeeSyncBaseStatusText = 'Shopee auto sync: checking...';
let shopeeSyncRefreshInFlight = false;
document.addEventListener('DOMContentLoaded', function () {
    loadDashboardCounts();
    loadSystem();
    refreshShopeeSyncStatus();
    bindColumnFilters('system', systemState, loadSystem);
    bindColumnFilters('online', onlineState, loadOnline);
    bindColumnFilters('converted', convertedState, loadConverted);
    document.getElementById('system-search').addEventListener('input', function () {
        clearTimeout(systemSearchTimeout);
        systemSearchTimeout = setTimeout(() => { systemState.search = this.value; systemState.page = 1; loadSystem(); }, 300);
    });
    document.getElementById('online-search').addEventListener('input', function () {
        clearTimeout(onlineSearchTimeout);
        onlineSearchTimeout = setTimeout(() => {
            onlineState.search = this.value;
            onlineState.page = 1;
            onlineState.error = '';
            onlineState.cursors = [];
            onlineState.cursors[1] = '';
            loadOnline();
        }, 300);
    });
    document.getElementById('converted-search').addEventListener('input', function () {
        clearTimeout(convertedSearchTimeout);
        convertedSearchTimeout = setTimeout(() => { convertedState.search = this.value; convertedState.page = 1; loadConverted(); }, 300);
    });
    lucide && lucide.createIcons();
});

// ---- System Product ----
function loadSystem() {
    systemState.loading = true;
    renderSystem();
    const params = new URLSearchParams({ page: systemState.page, per_page: PER_PAGE });
    if (systemState.search) params.set('search', systemState.search);
    appendColumnFilters(params, systemState.filters);
    fetch(routeBase + '/fetch-system?' + params.toString())
        .then(r => r.json()).then(res => {
            systemState.data = res.products || [];
            systemState.total = res.total || 0;
            systemState.lastPage = res.last_page || 1;
            systemState.loading = false;
            renderSystem();
        }).catch(() => { systemState.loading = false; renderSystem(); });
}
function renderSystem() {
    const tbody = document.getElementById('system-tbody');
    const pag = document.getElementById('system-pagination');
    const info = document.getElementById('system-info');
    if (systemState.loading) {
        tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-slate-400 text-xs font-bold">Loading...</td></tr>';
        pag.classList.add('hidden'); return;
    }
    if (!systemState.data.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-slate-400 text-xs font-bold">No system products found</td></tr>';
        pag.classList.add('hidden'); return;
    }
    tbody.innerHTML = systemState.data.map(p => `
        <tr class="hover:bg-slate-50/50">
            <td class="px-4 py-4 font-bold text-slate-800">${esc(p.product_code)}</td>
            <td class="px-4 py-4 text-slate-600">${esc(p.part_number || '-')}</td>
            <td class="px-4 py-4 text-slate-600">${esc(p.description || '-')}</td>
            <td class="px-4 py-4 text-slate-600">${esc(p.application || '-')}</td>
            <td class="px-4 py-4 text-slate-600">${p.price_online ? '₱' + parseFloat(p.price_online).toFixed(2) : '-'}</td>
                <td class="px-4 py-4">
                    <div class="flex items-center gap-1.5">
                        <button onclick='openConvertModal("system", ${JSON.stringify(p).replace(/'/g, "&#39;")})' class="px-3 py-1.5 rounded-lg bg-maroon-900 text-white text-[9px] font-black uppercase tracking-widest hover:bg-maroon-800">Convert</button>
                        <button class="px-3 py-1.5 rounded-lg bg-rose-600 text-white text-[9px] font-black uppercase tracking-widest hover:bg-rose-700">Delete</button>
                    </div>
                </td>
            </tr>
        `).join('');
    renderPagination('system', systemState);
}
function systemPage(dir) {
    if (dir === 'prev' && systemState.page > 1) { systemState.page--; loadSystem(); }
    else if (dir === 'next' && systemState.page < systemState.lastPage) { systemState.page++; loadSystem(); }
}

// ---- Online Product ----
function loadOnline() {
    onlineState.loading = true;
    onlineState.error = '';
    renderOnline();
    const params = new URLSearchParams({ page: onlineState.page, per_page: PER_PAGE });
    if (onlineState.search) params.set('search', onlineState.search);
    const offset = onlineState.cursors[onlineState.page] || '';
    if (offset) params.set('offset', offset);
    appendColumnFilters(params, onlineState.filters);
    fetch(routeBase + '/fetch-online?' + params.toString())
        .then(r => r.json()).then(res => {
            if (!res.success) {
                onlineState.data = [];
                onlineState.total = 0;
                onlineState.lastPage = 1;
                onlineState.error = res.message || res.error || 'Unable to load online products.';
                onlineState.loading = false;
                renderOnline();
                return;
            }
            onlineState.data = res.products || [];
            onlineState.total = res.total || 0;
            onlineState.lastPage = res.last_page || 1;
            onlineState.source = res.source || 'database';
            if (res.next_offset) {
                onlineState.cursors[onlineState.page + 1] = res.next_offset;
            }
            onlineState.loading = false;
            renderOnline();
        }).catch(() => {
            onlineState.error = 'Unable to load online products.';
            onlineState.loading = false;
            renderOnline();
        });
}
function renderOnline() {
    const tbody = document.getElementById('online-tbody');
    const pag = document.getElementById('online-pagination');
    const info = document.getElementById('online-info');
    if (onlineState.loading) {
        tbody.innerHTML = '<tr><td colspan="4" class="px-4 py-8 text-center text-slate-400 text-xs font-bold">Loading...</td></tr>';
        pag.classList.add('hidden'); return;
    }
    if (onlineState.error) {
        tbody.innerHTML = '<tr><td colspan="4" class="px-4 py-8 text-center text-rose-500 text-xs font-bold">' + esc(onlineState.error) + '</td></tr>';
        pag.classList.add('hidden'); return;
    }
    if (!onlineState.data.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="px-4 py-8 text-center text-slate-400 text-xs font-bold">No online products found</td></tr>';
        pag.classList.add('hidden'); return;
    }
    tbody.innerHTML = onlineState.data.map(p => {
        const data = JSON.stringify(p).replace(/'/g, "&#39;");
        return `
        <tr class="hover:bg-slate-50/50">
            <td class="px-4 py-4 font-bold text-slate-800">${esc(p.name || '-')}</td>
            <td class="px-4 py-4 text-slate-600">${esc(p.category || '-')}</td>
            <td class="px-4 py-4 text-slate-600">${p.price ? '₱' + parseFloat(p.price).toFixed(2) : '-'}</td>
                <td class="px-4 py-4">
                    <div class="flex items-center gap-1.5">
                        <button onclick='openConvertModal("online", ${data})' class="px-3 py-1.5 rounded-lg bg-maroon-900 text-white text-[9px] font-black uppercase tracking-widest hover:bg-maroon-800">Convert</button>
                        <button class="px-3 py-1.5 rounded-lg bg-rose-600 text-white text-[9px] font-black uppercase tracking-widest hover:bg-rose-700">Delete</button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
    renderPagination('online', onlineState);
}
function onlinePage(dir) {
    if (dir === 'prev' && onlineState.page > 1) { onlineState.page--; loadOnline(); }
    else if (dir === 'next' && onlineState.page < onlineState.lastPage) { onlineState.page++; loadOnline(); }
}

// ---- Converted Product ----
function loadConverted() {
    convertedState.loading = true;
    renderConverted();
    const params = new URLSearchParams({ page: convertedState.page, per_page: PER_PAGE });
    if (convertedState.search) params.set('search', convertedState.search);
    appendColumnFilters(params, convertedState.filters);
    fetch(routeBase + '/converted-products?' + params.toString())
        .then(r => r.json()).then(res => {
            convertedState.data = res.converted || [];
            convertedState.total = res.total || 0;
            convertedState.lastPage = res.last_page || 1;
            convertedState.loading = false;
            renderConverted();
        }).catch(() => { convertedState.loading = false; renderConverted(); });
}
function renderConverted() {
    const tbody = document.getElementById('converted-tbody');
    const pag = document.getElementById('converted-pagination');
    const info = document.getElementById('converted-info');
    if (convertedState.loading) {
        tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-slate-400 text-xs font-bold">Loading...</td></tr>';
        pag.classList.add('hidden'); return;
    }
    if (!convertedState.data.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-slate-400 text-xs font-bold">No converted products found</td></tr>';
        pag.classList.add('hidden'); return;
    }
    tbody.innerHTML = convertedState.data.map(c => {
        return `
        <tr class="hover:bg-slate-50/50">
            <td class="px-4 py-4 font-bold text-slate-800">${esc(c.system.name || '-')}</td>
            <td class="px-4 py-4 text-slate-600">${esc(c.system.part_number || '-')}</td>
            <td class="px-4 py-4 text-slate-600">${formatMoney(c.system.price)}</td>
            <td class="px-4 py-4 text-center text-slate-500 text-sm font-black">-&gt;</td>
            <td class="px-4 py-4 font-bold text-slate-800">${esc(c.online.name || '-')}</td>
            <td class="px-4 py-4 text-slate-600">${esc(c.online.category || '-')}</td>
            <td class="px-4 py-4 text-slate-600">${formatMoney(c.online.price)}</td>
        </tr>
    `;
    }).join('');
    renderPagination('converted', convertedState);
}
function convertedPage(dir) {
    if (dir === 'prev' && convertedState.page > 1) { convertedState.page--; loadConverted(); }
    else if (dir === 'next' && convertedState.page < convertedState.lastPage) { convertedState.page++; loadConverted(); }
}

// ---- Shared pagination renderer ----
function renderPagination(prefix, state) {
    const pag = document.getElementById(prefix + '-pagination');
    const info = document.getElementById(prefix + '-info');
    const pagesEl = document.getElementById(prefix + '-pages');
    const from = (state.page - 1) * PER_PAGE + 1;
    const to = Math.min(state.page * PER_PAGE, state.total);
    info.textContent = 'Showing ' + from + ' to ' + to + ' of ' + state.total + ' entries';
    pag.classList.remove('hidden');
    const btnBase = 'shrink-0 px-3 py-2 rounded-lg text-[10px] font-black border ';
    const disabledClass = 'bg-white border-slate-200 text-slate-300';
    const enabledClass = 'bg-white border-slate-200 text-slate-600 hover:bg-slate-100';
    const firstBtn = document.getElementById(prefix + '-first');
    const lastBtn = document.getElementById(prefix + '-last');
    if (firstBtn) firstBtn.className = btnBase + (state.page <= 1 ? disabledClass : enabledClass);
    if (lastBtn) lastBtn.className = btnBase + (state.page >= state.lastPage ? disabledClass : enabledClass);
    document.getElementById(prefix + '-prev').className = btnBase + (state.page <= 1 ? disabledClass : enabledClass);
    document.getElementById(prefix + '-next').className = btnBase + (state.page >= state.lastPage ? disabledClass : enabledClass);
    let html = '';
    for (let i = 1; i <= state.lastPage; i++) {
        const active = i === state.page;
        html += '<button onclick="' + prefix + 'Page(' + i + ')" ' + (active ? 'data-active-page="true" ' : '') + 'class="shrink-0 px-3 py-2 rounded-lg text-[10px] font-black border ' + (active ? 'bg-maroon-900 border-maroon-900 text-white' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-100') + '">' + i + '</button>';
    }
    pagesEl.innerHTML = html;
    requestAnimationFrame(() => {
        const activeButton = pagesEl.querySelector('[data-active-page="true"]');
        if (activeButton) activeButton.scrollIntoView({ block: 'nearest', inline: 'center' });
    });
}
['system', 'online', 'converted'].forEach(p => {
    window[p + 'Page'] = function(n) {
        const state = p === 'system' ? systemState : p === 'online' ? onlineState : convertedState;
        if (typeof n === 'number') { state.page = n; }
        else if (n === 'first' && state.page > 1) { state.page = 1; }
        else if (n === 'prev' && state.page > 1) { state.page--; }
        else if (n === 'next' && state.page < state.lastPage) { state.page++; }
        else if (n === 'last' && state.page < state.lastPage) { state.page = state.lastPage; }
        else return;
        const loadFn = p === 'system' ? loadSystem : p === 'online' ? loadOnline : loadConverted;
        loadFn();
    };
});

// ---- Convert Modal ----
let convertSource = null; // { type: 'system'|'online', product: {} }
let selectedPartner = null; // the product selected from search modal

function openConvertModal(type, product) {
    convertSource = { type, product };
    selectedPartner = null;
    const leftTitle = document.getElementById('convert-left-title');
    const rightTitle = document.getElementById('convert-right-title');
    const leftFields = document.getElementById('convert-left-fields');
    const rightFields = document.getElementById('convert-right-fields');
    document.getElementById('convert-search-input').value = '';

    if (type === 'system') {
        leftTitle.textContent = 'System Product';
        rightTitle.textContent = 'Select Online Product';
        leftFields.innerHTML = `
            <div><label class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-1">Product Code</label><input type="text" readonly value="${esc(product.product_code)}" class="w-full border border-slate-200 bg-slate-50 rounded-lg px-3 py-2.5 text-xs font-bold text-slate-700 outline-none"></div>
            <div><label class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-1">Part No#</label><input type="text" readonly value="${esc(product.part_number || '-')}" class="w-full border border-slate-200 bg-slate-50 rounded-lg px-3 py-2.5 text-xs font-bold text-slate-700 outline-none"></div>
            <div><label class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-1">Description</label><input type="text" readonly value="${esc(product.description || '-')}" class="w-full border border-slate-200 bg-slate-50 rounded-lg px-3 py-2.5 text-xs font-bold text-slate-700 outline-none"></div>
            <div><label class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-1">Application</label><input type="text" readonly value="${esc(product.application || '-')}" class="w-full border border-slate-200 bg-slate-50 rounded-lg px-3 py-2.5 text-xs font-bold text-slate-700 outline-none"></div>
        `;
        rightFields.innerHTML = '<p class="text-xs text-slate-400 italic">Click "Search & Select" to choose an online product</p>';
    } else {
        leftTitle.textContent = 'Online Product';
        rightTitle.textContent = 'Select System Product';
        leftFields.innerHTML = `
            <div><label class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-1">Product Name</label><input type="text" readonly value="${esc(product.name || '-')}" class="w-full border border-slate-200 bg-slate-50 rounded-lg px-3 py-2.5 text-xs font-bold text-slate-700 outline-none"></div>
            <div><label class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-1">Category</label><input type="text" readonly value="${esc(product.category || '-')}" class="w-full border border-slate-200 bg-slate-50 rounded-lg px-3 py-2.5 text-xs font-bold text-slate-700 outline-none"></div>
            <div><label class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-1">Price</label><input type="text" readonly value="${product.price ? '₱' + parseFloat(product.price).toFixed(2) : '-'}" class="w-full border border-slate-200 bg-slate-50 rounded-lg px-3 py-2.5 text-xs font-bold text-slate-700 outline-none"></div>
        `;
        rightFields.innerHTML = '<p class="text-xs text-slate-400 italic">Click "Search & Select" to choose a system product</p>';
    }
    toggleModal('convert-modal', true);
}
function closeConvertModal() { toggleModal('convert-modal', false); }

function confirmConvert() {
    if (!selectedPartner) { alert('Please select a product first.'); return; }
    const productId = convertSource.type === 'system' ? convertSource.product.id : selectedPartner.id;
    const onlineProduct = convertSource.type === 'system' ? selectedPartner : convertSource.product;
    const onlineProductId = onlineProduct.id;
    if (!productId || !onlineProductId) {
        alert('Please select a valid system product and online product.');
        return;
    }
    const endpoint = routeBase + '/confirm-convert';
    fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
        body: JSON.stringify({ product_id: productId, online_product_id: onlineProductId, online_product: onlineProduct }),
    }).then(r => r.json()).then(res => {
        if (res.success) {
            closeConvertModal();
            loadDashboardCounts();
            loadSystem();
            loadOnline();
            loadConverted();
            alert(res.message || 'Products linked successfully.');
        } else {
            alert('Conversion failed: ' + (res.message || 'Unknown error'));
        }
    }).catch(() => alert('Conversion request failed'));
}

// ---- Search Modal ----
let searchModalTimeout;
function openSearchModal() {
    const title = document.getElementById('search-modal-title');
    const th1 = document.getElementById('search-th-1');
    const th2 = document.getElementById('search-th-2');
    if (convertSource.type === 'system') {
        title.textContent = 'Search Online Products';
        th1.textContent = 'Product Name';
        th2.textContent = 'Category';
    } else {
        title.textContent = 'Search System Products';
        th1.textContent = 'Part No#';
        th2.textContent = 'Description';
    }
    document.getElementById('search-modal-input').value = '';
    document.getElementById('search-modal-tbody').innerHTML = '<tr><td colspan="3" class="px-4 py-8 text-center text-slate-400 text-xs font-bold">Loading...</td></tr>';
    document.getElementById('search-pagination').classList.add('hidden');
    searchState = { data: [], page: 1, lastPage: 1, total: 0, search: '', loading: false, cursors: [] };
    searchState.cursors[1] = '';
    toggleModal('search-modal', true);
    document.getElementById('search-modal-input').focus();
    searchModalProducts('', 1);
}
function closeSearchModal() { toggleModal('search-modal', false); }

function searchModalProducts(query, page) {
    const isNewSearch = query !== searchState.search;
    searchState.search = query || '';
    searchState.page = page || 1;
    if (isNewSearch) { searchState.cursors = ['']; searchState.cursors[1] = ''; }
    searchState.loading = true;

    const tbody = document.getElementById('search-modal-tbody');
    tbody.innerHTML = '<tr><td colspan="3" class="px-4 py-8 text-center text-slate-400 text-xs font-bold">Searching...</td></tr>';
    document.getElementById('search-pagination').classList.add('hidden');

    if (convertSource.type === 'system') {
        const params = new URLSearchParams({ page: searchState.page, per_page: SEARCH_PER_PAGE });
        if (searchState.search) params.set('search', searchState.search);
        const offset = searchState.cursors[searchState.page] || '';
        if (offset) params.set('offset', offset);
        const url = routeBase + '/search-shopee?' + params.toString();
        fetch(url).then(r => r.json()).then(res => {
            if (!res.success) {
                const message = res.message || res.error || 'Shopee search is not available';
                tbody.innerHTML = '<tr><td colspan="3" class="px-4 py-8 text-center text-rose-500 text-xs font-bold">' + esc(message) + '</td></tr>';
                searchState.loading = false;
                return;
            }
            searchState.data = res.products || [];
            searchState.total = res.total || 0;
            searchState.lastPage = res.last_page || 1;
            if (res.next_offset) {
                searchState.cursors[searchState.page + 1] = res.next_offset;
            }
            searchState.loading = false;
            renderSearchResults();
        }).catch(() => {
            tbody.innerHTML = '<tr><td colspan="3" class="px-4 py-8 text-center text-rose-500 text-xs font-bold">Unable to connect to Shopee</td></tr>';
            searchState.loading = false;
        });
    } else {
        const params = new URLSearchParams({ page: searchState.page, per_page: SEARCH_PER_PAGE });
        if (searchState.search) params.set('search', searchState.search);
        const url = routeBase + '/fetch-system?' + params.toString();
        fetch(url).then(r => r.json()).then(res => {
            searchState.data = res.products || [];
            searchState.total = res.total || 0;
            searchState.lastPage = res.last_page || 1;
            searchState.loading = false;
            renderSearchResults();
        }).catch(() => {
            tbody.innerHTML = '<tr><td colspan="3" class="px-4 py-8 text-center text-rose-500 text-xs font-bold">Error loading data</td></tr>';
            searchState.loading = false;
        });
    }
}

function renderSearchResults() {
    const tbody = document.getElementById('search-modal-tbody');
    const pag = document.getElementById('search-pagination');
    const items = searchState.data;

    if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="3" class="px-4 py-8 text-center text-slate-400 text-xs font-bold">No results found</td></tr>';
        pag.classList.add('hidden');
        return;
    }

    tbody.innerHTML = items.map(item => {
        const data = JSON.stringify(item).replace(/'/g, "&#39;");
        if (convertSource.type === 'system') {
            const img = item.image_url ? `<img src="${esc(item.image_url)}" class="w-8 h-8 object-cover rounded mr-2 inline-block">` : '';
            return `<tr class="hover:bg-slate-50 cursor-pointer">
                <td class="px-4 py-3 font-bold text-slate-800">${img}${esc(item.name || '-')}</td>
                <td class="px-4 py-3 text-slate-600">${esc(item.category_id ? 'Category #' + item.category_id : item.category || '-')}</td>
                <td class="px-4 py-3 text-center"><button onclick='selectProduct(${data})' class="px-3 py-1.5 rounded-lg bg-maroon-900 text-white text-[9px] font-black uppercase tracking-widest hover:bg-maroon-800">Select</button></td>
            </tr>`;
        } else {
            return `<tr class="hover:bg-slate-50 cursor-pointer">
                <td class="px-4 py-3 font-bold text-slate-800">${esc(item.part_number || item.product_code || '-')}</td>
                <td class="px-4 py-3 text-slate-600">${esc(item.description || '-')}</td>
                <td class="px-4 py-3 text-center"><button onclick='selectProduct(${data})' class="px-3 py-1.5 rounded-lg bg-maroon-900 text-white text-[9px] font-black uppercase tracking-widest hover:bg-maroon-800">Select</button></td>
            </tr>`;
        }
    }).join('');

    renderSearchPagination();
}

function renderSearchPagination() {
    const pag = document.getElementById('search-pagination');
    const info = document.getElementById('search-info');
    const pagesEl = document.getElementById('search-pages');
    const from = (searchState.page - 1) * SEARCH_PER_PAGE + 1;
    const to = Math.min(searchState.page * SEARCH_PER_PAGE, searchState.total);
    info.textContent = 'Showing ' + from + ' to ' + to + ' of ' + searchState.total + ' entries';
    pag.classList.remove('hidden');
    const btnBase = 'shrink-0 px-3 py-2 rounded-lg text-[10px] font-black border ';
    document.getElementById('search-prev').className = btnBase + (searchState.page <= 1 ? 'bg-white border-slate-200 text-slate-300' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-100');
    document.getElementById('search-next').className = btnBase + (searchState.page >= searchState.lastPage ? 'bg-white border-slate-200 text-slate-300' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-100');
    const c = searchState.page, lp = searchState.lastPage;
    let pages = [];
    if (lp <= 10) {
        for (let i = 1; i <= lp; i++) pages.push(i);
    } else {
        pages.push(1);
        if (c > 4) pages.push('...');
        let start = Math.max(2, c - 2), end = Math.min(lp - 1, c + 2);
        if (c <= 4) end = Math.min(6, lp - 1);
        if (c >= lp - 3) start = Math.max(lp - 5, 2);
        for (let i = start; i <= end; i++) pages.push(i);
        if (c < lp - 3) pages.push('...');
        pages.push(lp);
    }
    let html = '';
    for (const p of pages) {
        if (p === '...') {
            html += '<span class="shrink-0 px-2 text-[10px] font-bold text-slate-400">...</span>';
        } else {
            const active = p === searchState.page;
            html += '<button onclick="searchPage(' + p + ')" class="shrink-0 px-3 py-2 rounded-lg text-[10px] font-black border ' + (active ? 'bg-maroon-900 border-maroon-900 text-white' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-100') + '">' + p + '</button>';
        }
    }
    pagesEl.innerHTML = html;
    pagesEl.scrollLeft = pagesEl.scrollWidth;
}

function searchPage(n) {
    if (typeof n === 'number') { searchState.page = n; }
    else if (n === 'prev' && searchState.page > 1) { searchState.page--; }
    else if (n === 'next' && searchState.page < searchState.lastPage) { searchState.page++; }
    else return;
    searchModalProducts(searchState.search, searchState.page);
}

function selectProduct(item) {
    selectedPartner = item;
    closeSearchModal();
    const fields = document.getElementById('convert-right-fields');
    if (convertSource.type === 'system') {
        fields.innerHTML = `
            <div><label class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-1">Product Name</label><input type="text" readonly value="${esc(item.name || '-')}" class="w-full border border-slate-200 bg-slate-50 rounded-lg px-3 py-2.5 text-xs font-bold text-slate-700 outline-none"></div>
            <div><label class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-1">Category</label><input type="text" readonly value="${esc(item.category || '-')}" class="w-full border border-slate-200 bg-slate-50 rounded-lg px-3 py-2.5 text-xs font-bold text-slate-700 outline-none"></div>
            <div><label class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-1">Price</label><input type="text" readonly value="${item.price ? '₱' + parseFloat(item.price).toFixed(2) : '-'}" class="w-full border border-slate-200 bg-slate-50 rounded-lg px-3 py-2.5 text-xs font-bold text-slate-700 outline-none"></div>
        `;
    } else {
        fields.innerHTML = `
            <div><label class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-1">Product Code</label><input type="text" readonly value="${esc(item.product_code)}" class="w-full border border-slate-200 bg-slate-50 rounded-lg px-3 py-2.5 text-xs font-bold text-slate-700 outline-none"></div>
            <div><label class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-1">Part No#</label><input type="text" readonly value="${esc(item.part_number || '-')}" class="w-full border border-slate-200 bg-slate-50 rounded-lg px-3 py-2.5 text-xs font-bold text-slate-700 outline-none"></div>
            <div><label class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-1">Description</label><input type="text" readonly value="${esc(item.description || '-')}" class="w-full border border-slate-200 bg-slate-50 rounded-lg px-3 py-2.5 text-xs font-bold text-slate-700 outline-none"></div>
        `;
    }
}

function toggleModal(id, show) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.toggle('hidden', !show);
    el.classList.toggle('flex', show);
}

// Wire up search modal input
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('search-modal-input');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(searchModalTimeout);
            searchModalTimeout = setTimeout(() => searchModalProducts(this.value, 1), 300);
        });
    }
});

function esc(str) {
    if (!str) return '-';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
</script>

@endSection
@include('partials.admin.admin_sidebar_navbar')
