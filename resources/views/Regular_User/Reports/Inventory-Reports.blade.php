@extends('partials.user_account.user_sidebar_navbar')

@section('inventory_reports_content')
<div id="page-inventory-reports" class="rep-page space-y-6">
    
    <!-- Title Card -->
    <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4 bg-white border border-slate-200 rounded-xl p-5 shadow-sm no-print">
        <div>
            <p class="text-[10px] font-black uppercase tracking-widest text-maroon-700">Reports Engine</p>
            <h2 class="mt-1 text-xl font-black text-slate-900">Inventory Reports</h2>
            <p class="text-xs font-semibold text-slate-500 mt-1">Generate and print stock logs, critical reorders, and stock holdings reports.</p>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm no-print">
        <h3 class="text-xs font-black uppercase tracking-widest text-slate-700 mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
            <i data-lucide="filter" class="w-4 h-4 text-maroon-700"></i>
            Report Configuration
        </h3>
        
        <!-- Row 1: Report Type + Description + Category -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Report Type Dropdown -->
            <div class="space-y-2">
                <label for="rep-type" class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Report Type</label>
                <div class="relative">
                    <select id="rep-type" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all appearance-none cursor-pointer">
                        <option value="product_on_hand">Product On Hand</option>
                        <option value="reorder_report">Reorder Report (Low Stock)</option>
                    </select>
                    <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-slate-400">
                        <i data-lucide="chevron-down" class="w-4 h-4"></i>
                    </div>
                </div>
            </div>

            <!-- Description Search/Dropdown Input -->
            <div class="space-y-2 relative" id="rep-description-wrapper">
                <label for="rep-description" class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Product Description</label>
                <div class="relative">
                    <input type="text" id="rep-description" autocomplete="off"
                        placeholder="Type to search or click ▼ to browse..."
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-10 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <i data-lucide="search" class="w-4 h-4"></i>
                    </div>
                    <!-- Dropdown toggle button -->
                    <button type="button" id="rep-description-toggle"
                        class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-maroon-700 transition-colors">
                        <i data-lucide="chevron-down" class="w-4 h-4"></i>
                    </button>
                </div>
                <!-- Autocomplete / Dropdown List -->
                <div id="rep-description-suggestions"
                    class="absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl z-50 overflow-hidden divide-y divide-slate-100 hidden autocomplete-suggestions">
                </div>
            </div>

            <!-- Category Search/Dropdown Input -->
            <div class="space-y-2 relative" id="rep-category-wrapper">
                <label for="rep-category" class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Product Category</label>
                <div class="relative">
                    <input type="text" id="rep-category" autocomplete="off"
                        placeholder="Type to search or click ▼ to browse..."
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-10 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <i data-lucide="tag" class="w-4 h-4"></i>
                    </div>
                    <!-- Dropdown toggle button -->
                    <button type="button" id="rep-category-toggle"
                        class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-maroon-700 transition-colors">
                        <i data-lucide="chevron-down" class="w-4 h-4"></i>
                    </button>
                </div>
                <!-- Autocomplete / Dropdown List -->
                <div id="rep-category-suggestions"
                    class="absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl z-50 overflow-hidden divide-y divide-slate-100 hidden autocomplete-suggestions">
                </div>
            </div>
        </div>

        <!-- Row 2: Year Range (Annual Sales) -->
        <div class="mt-5 pt-4 border-t border-slate-100">
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-3 flex items-center gap-2">
                <i data-lucide="calendar-range" class="w-3.5 h-3.5 text-maroon-700"></i>
                Annual Sales Year Range
            </p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Year From -->
                <div class="space-y-2">
                    <label for="rep-date-from" class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Year From</label>
                    <div class="relative">
                        <input type="number" id="rep-date-from" min="2000" max="2099" placeholder="e.g. 2024"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="calendar" class="w-4 h-4"></i>
                        </div>
                    </div>
                </div>

                <!-- Year To -->
                <div class="space-y-2">
                    <label for="rep-date-to" class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Year To</label>
                    <div class="relative">
                        <input type="number" id="rep-date-to" min="2000" max="2099" placeholder="e.g. 2026"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="calendar" class="w-4 h-4"></i>
                        </div>
                    </div>
                </div>

                <!-- As Of Year -->
                <div class="space-y-2">
                    <label for="rep-date-asof" class="block text-[10px] font-black uppercase tracking-widest text-slate-400">As Of Year</label>
                    <div class="relative">
                        <input type="number" id="rep-date-asof" min="2000" max="2099" placeholder="e.g. 2026"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="calendar-check" class="w-4 h-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Buttons Panel -->
        <div class="flex flex-wrap items-center justify-end gap-3 mt-6 pt-4 border-t border-slate-100">
            <button type="button" id="rep-clear-btn"
                class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-5 py-3 text-[10px] font-black uppercase tracking-widest text-slate-500 hover:bg-slate-50 transition-all">
                <i data-lucide="x-circle" class="h-4 w-4"></i>
                <span>Clear Filters</span>
            </button>
            <button type="button" id="rep-print-btn"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-maroon-900 px-6 py-3 text-[10px] font-black uppercase tracking-widest text-white shadow-md hover:bg-maroon-800 transition-all">
                <i data-lucide="printer" class="h-4 w-4 text-goldlining-400"></i>
                <span>Print Report</span>
            </button>
        </div>
    </div>

    <!-- PRINT LAYOUT CONTAINER (Completely isolated for printer layouts) -->
    <div id="print-report-area" class="hidden"></div>
</div>
@endsection

@push('styles')
    @vite(['resources/css/inventory_reports.css'])
@endpush

@push('scripts')
    <script>
        window.inventoryReportsRoutes = {
            searchDescriptions: "{{ route('api.products.search-descriptions') }}",
            searchCategories: "{{ route('api.products.search-categories') }}",
            reportData: "{{ route('api.reports.inventory-data') }}"
        };
    </script>
    @php
        $inventoryReportsScript = file_get_contents(resource_path('js/inventory_reports.js'));
    @endphp
    <script>{!! $inventoryReportsScript !!}</script>
@endpush


