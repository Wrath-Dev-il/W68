@php
    $resolvedPrefix = $routePrefix ?? 'admin';
    $resolvedDataRoute = $dataRoute ?? ($resolvedPrefix . '.cost-report.data');
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/sales-report.css') }}?v={{ time() }}">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');

        #page-cost-report {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .text-maroon { color: #800000; }
        .bg-maroon { background-color: #800000; }
        .border-maroon { border-color: #800000; }
        .text-gold { color: #FFD700; }
        .bg-gold { background-color: #FFD700; }

        .animate-fade-in {
            animation: fadeIn 0.4s ease-out forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .custom-scrollbar::-webkit-scrollbar {
            height: 6px;
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #e2e8f0;
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #cbd5e1;
        }

        .cr-table { font-size: 13px; }
        .cr-table th, .cr-table td { font-size: 13px; padding: 8px 10px; }
        .cr-print-header { text-align: center; font-size: 18px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 16px; }
        .cr-print-meta { text-align: center; font-size: 13px; font-weight: 700; margin-bottom: 16px; display: flex; gap: 20px; justify-content: center; }

        @media print {
            #cr-print-area { display: block !important; }
        }

        .radio-card {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            cursor: default;
            user-select: none;
            background: #800000;
            color: #FFD700;
            border: 2px solid #800000;
        }
        .radio-card input[type="radio"] {
            appearance: none;
            -webkit-appearance: none;
            width: 18px;
            height: 18px;
            border: 2px solid #FFD700;
            border-radius: 50%;
            position: relative;
            cursor: default;
            flex-shrink: 0;
        }
        .radio-card input[type="radio"]:checked::after {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 10px;
            height: 10px;
            background: #FFD700;
            border-radius: 50%;
        }
        .radio-card input[type="radio"]:checked {
            border-color: #FFD700;
        }

        #page-cost-report .sales-report-dropdown {
            max-height: 220px;
            overflow-y: auto;
        }
        #page-cost-report .sales-report-option {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #page-cost-report .sales-report-option.cr-multi-option {
            white-space: normal;
            gap: 8px;
        }
        #page-cost-report .cr-multi-check {
            width: 16px;
            height: 16px;
            accent-color: #800000;
            flex: 0 0 auto;
            pointer-events: none;
        }
        #page-cost-report .cr-selected-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 8px;
        }
        #page-cost-report .cr-selected-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            max-width: 100%;
            border: 1px solid #e2e8f0;
            border-radius: 9999px;
            background: #f8fafc;
            padding: 4px 8px;
            color: #475569;
            font-size: 10px;
            font-weight: 800;
            line-height: 1.2;
        }
        #page-cost-report .cr-selected-chip span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        #page-cost-report .cr-selected-chip:hover {
            border-color: #800000;
            color: #800000;
        }
    </style>
@endpush

@section('cost_report_content')
<div id="page-cost-report" class="rep-page space-y-6 animate-fade-in">
    <div class="flex items-center gap-4 bg-white border border-slate-200 rounded-xl p-5 shadow-sm no-print">
        <label class="radio-card">
            <input type="radio" name="report_type" value="cost_report" checked>
            C.S.I
        </label>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm no-print">
        <h3 class="text-xs font-black uppercase tracking-widest text-slate-700 mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
            <i data-lucide="filter" class="w-4 h-4 text-maroon-700"></i>
            filter by
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
            <div class="space-y-2 relative">
                <label for="cr-product-code" class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Product Code</label>
                <div class="sales-report-searchable" data-endpoint="{{ route($resolvedPrefix . '.cost-report.product-codes') }}" data-type="text" data-input-id="cr-product-code" data-multi-select="true">
                    <div class="relative">
                        <input id="cr-product-code" type="text" autocomplete="off" placeholder="Search and check multiple Product Codes..." class="sales-report-input w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-10 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="package-search" class="w-4 h-4"></i>
                        </div>
                        <button type="button" class="sales-report-toggle absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-maroon-700 transition-colors">
                            <i data-lucide="chevron-down" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <div class="sales-report-dropdown absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl z-50 overflow-hidden divide-y divide-slate-100 hidden"></div>
                </div>
            </div>
            <div class="space-y-2 relative">
                <label for="cr-part-number" class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Part Number</label>
                <div class="sales-report-searchable" data-endpoint="{{ route($resolvedPrefix . '.cost-report.part-numbers') }}" data-type="text" data-input-id="cr-part-number" data-multi-select="true">
                    <div class="relative">
                        <input id="cr-part-number" type="text" autocomplete="off" placeholder="Search and check multiple Part Numbers..." class="sales-report-input w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-10 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="hash" class="w-4 h-4"></i>
                        </div>
                        <button type="button" class="sales-report-toggle absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-maroon-700 transition-colors">
                            <i data-lucide="chevron-down" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <div class="sales-report-dropdown absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl z-50 overflow-hidden divide-y divide-slate-100 hidden"></div>
                </div>
            </div>
            <div class="space-y-2 relative">
                <label for="cr-description" class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Description</label>
                <div class="sales-report-searchable" data-endpoint="{{ route($resolvedPrefix . '.cost-report.descriptions') }}" data-type="text" data-input-id="cr-description">
                    <div class="relative">
                        <input id="cr-description" type="text" autocomplete="off" placeholder="Type to search or click ▼ to browse..." class="sales-report-input w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-10 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="search" class="w-4 h-4"></i>
                        </div>
                        <button type="button" class="sales-report-toggle absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-maroon-700 transition-colors">
                            <i data-lucide="chevron-down" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <div class="sales-report-dropdown absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl z-50 overflow-hidden divide-y divide-slate-100 hidden"></div>
                </div>
            </div>
            <div class="space-y-2 relative">
                <label for="cr-application" class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Application</label>
                <div class="sales-report-searchable" data-endpoint="{{ route($resolvedPrefix . '.cost-report.applications') }}" data-type="text" data-input-id="cr-application">
                    <div class="relative">
                        <input id="cr-application" type="text" autocomplete="off" placeholder="Type to search or click ▼ to browse..." class="sales-report-input w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-10 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="search" class="w-4 h-4"></i>
                        </div>
                        <button type="button" class="sales-report-toggle absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-maroon-700 transition-colors">
                            <i data-lucide="chevron-down" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <div class="sales-report-dropdown absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl z-50 overflow-hidden divide-y divide-slate-100 hidden"></div>
                </div>
            </div>
            <div class="space-y-2 relative">
                <label for="cr-category" class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Category</label>
                <div class="sales-report-searchable" data-endpoint="{{ route($resolvedPrefix . '.cost-report.categories') }}" data-type="text" data-input-id="cr-category">
                    <div class="relative">
                        <input id="cr-category" type="text" autocomplete="off" placeholder="Type to search or click ▼ to browse..." class="sales-report-input w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-10 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="tag" class="w-4 h-4"></i>
                        </div>
                        <button type="button" class="sales-report-toggle absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-maroon-700 transition-colors">
                            <i data-lucide="chevron-down" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <div class="sales-report-dropdown absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl z-50 overflow-hidden divide-y divide-slate-100 hidden"></div>
                </div>
            </div>
            <div class="space-y-2 relative">
                <label for="cr-pricelist-code" class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Pricelist Code</label>
                <div class="sales-report-searchable" data-endpoint="{{ route($resolvedPrefix . '.cost-report.pricelist-codes') }}" data-type="text" data-input-id="cr-pricelist-code">
                    <div class="relative">
                        <input id="cr-pricelist-code" type="text" autocomplete="off" placeholder="Type to search or click ▼ to browse..." class="sales-report-input w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-10 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="list-filter" class="w-4 h-4"></i>
                        </div>
                        <button type="button" class="sales-report-toggle absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-maroon-700 transition-colors">
                            <i data-lucide="chevron-down" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <div class="sales-report-dropdown absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl z-50 overflow-hidden divide-y divide-slate-100 hidden"></div>
                </div>
            </div>
        </div>

        <h3 class="text-xs font-black uppercase tracking-widest text-slate-700 mt-6 mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
            <i data-lucide="calendar" class="w-4 h-4 text-maroon-700"></i>
            Sales Date Range (3 dropdown annual date up to 3000)
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="space-y-2">
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Date</label>
                <select id="cr-year1" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all">
                </select>
            </div>
            <div class="space-y-2">
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Date</label>
                <select id="cr-year2" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all">
                </select>
            </div>
            <div class="space-y-2">
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Date</label>
                <select id="cr-year3" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all">
                </select>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-3 mt-6 pt-4 border-t border-slate-100">
            <button type="button" id="cr-print-btn" class="inline-flex items-center justify-center gap-2 rounded-xl bg-maroon-900 px-6 py-3 text-[10px] font-black uppercase tracking-widest text-white shadow-md hover:bg-maroon-800 transition-all">
                <i data-lucide="printer" class="h-4 w-4 text-goldlining-400"></i>
                <span>Print</span>
            </button>
        </div>
    </div>

    <div id="cr-print-area" style="display:none;">
        <div class="cr-print-header">C.S.I  REPORT</div>
        <div class="cr-print-meta">
            <span id="cr-print-generated-label"></span>
        </div>
        <div id="cr-print-container">
            <table class="cr-table w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-slate-700 font-bold uppercase">
                        <th class="border border-slate-300 px-3 py-2" rowspan="1" style="width:14%">Item Code</th>
                        <th class="border border-slate-300 px-3 py-2" rowspan="1" style="width:12%">Part No#</th>
                        <th class="border border-slate-300 px-3 py-2" rowspan="1" style="width:22%">Application</th>
                        <th class="border border-slate-300 px-3 py-2 text-left" rowspan="1" style="width:12%">PRICE</th>
                        <th class="border border-slate-300 px-3 py-2 text-left" rowspan="1" style="width:14.5%"><div>DATE</div><div>SUPPLIER</div><div>COST</div></th>
                        <th class="border border-slate-300 px-3 py-2 text-center" style="width:5.5%">INV</th>
                        <th class="border border-slate-300 px-3 py-2 text-center" id="cr-th-year1" style="width:4.5%">'21</th>
                        <th class="border border-slate-300 px-3 py-2 text-center" id="cr-th-year2" style="width:4.5%">'22</th>
                        <th class="border border-slate-300 px-3 py-2 text-center" id="cr-th-year3" style="width:4.5%">'23</th>
                    </tr>
                </thead>
                <tbody id="cr-report-tbody" class="text-slate-600">
                    <tr><td colspan="9" class="py-10 text-center text-slate-400">Click Generate to load cost, sales, inventory report data.</td></tr>
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between mt-4 px-1">
            <span id="cr-report-info" class="text-sm text-slate-500 font-medium"></span>
            <div class="flex items-center space-x-2 no-print">
                <button id="cr-prev-page" onclick="crChangePage(-1)" class="px-3 py-1.5 text-xs border border-slate-200 rounded-lg hover:bg-slate-100 disabled:opacity-30 transition-all" disabled>Prev</button>
                <span id="cr-page-info" class="text-xs text-slate-500 font-medium">Page 1 of 1</span>
                <button id="cr-next-page" onclick="crChangePage(1)" class="px-3 py-1.5 text-xs border border-slate-200 rounded-lg hover:bg-slate-100 transition-all">Next</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script>
        (function () {
            var CR_DATA_URL = @json(route($resolvedDataRoute));
            var CR_COL_WIDTHS = ['11.5%', '10.5%', '39%', '8.5%', '12.5%', '4.5%', '4.5%', '4.5%', '4.5%'];
            var CR_PRINT_CHUNK_SIZE = 150;

            var crData = [];
            var crPage = 1;
            var crPerPage = 50;
            var crLastPage = 1;
            var crTotalItems = 0;
            var crLastGeneratedSignature = '';

            function crEscapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            /**
             * Normalize Application text so hard "Enter" newlines, <br> tags, tabs,
             * and repeated spacing flow continuously across the full column width.
             */
            function crFormatApplicationText(value) {
                var raw = String(value ?? '');
                var normalized = raw
                    .replace(/<\s*br\s*\/?>/gi, ' ')
                    .replace(/[\r\n\t]+/g, ' ')
                    .replace(/\s{2,}/g, ' ')
                    .trim();
                if (!normalized) return '---';

                return crEscapeHtml(normalized).replace(/([,/])(?=[A-Za-z0-9(])/g, '$1<wbr>');
            }

            function crBuildColgroupHtml() {
                var cols = '<colgroup>';
                for (var i = 0; i < CR_COL_WIDTHS.length; i++) {
                    cols += '<col style="width:' + CR_COL_WIDTHS[i] + '">';
                }
                return cols + '</colgroup>';
            }

            function crGetMultiSelectValues(inputId) {
                var input = document.getElementById(inputId);
                if (!input) return [];

                var wrapper = input.closest('.sales-report-searchable');
                if (wrapper?.dataset.multiSelect === 'true' && wrapper._crSelectedValues) {
                    var selected = Array.from(wrapper._crSelectedValues);
                    if (selected.length) return selected;
                }

                var typed = String(input.value || '').trim();
                return typed ? [typed] : [];
            }

            function crAppendProductFilters(params) {
                crGetMultiSelectValues('cr-product-code').forEach(function (value) {
                    params.append('product_code[]', value);
                });
                crGetMultiSelectValues('cr-part-number').forEach(function (value) {
                    params.append('part_number[]', value);
                });
            }

            function crBuildFilterParams(extraParams) {
                var params = new URLSearchParams();
                if (extraParams) {
                    Object.keys(extraParams).forEach(function (key) {
                        if (extraParams[key] !== undefined && extraParams[key] !== null) {
                            params.set(key, String(extraParams[key]));
                        }
                    });
                }
                params.set('year1', crGetYear1());
                params.set('year2', crGetYear2());
                params.set('year3', crGetYear3());

                crAppendProductFilters(params);

                var desc = document.getElementById('cr-description')?.value || '';
                if (desc) params.set('description', desc);
                var app = document.getElementById('cr-application')?.value || '';
                if (app) params.set('application', app);
                var cat = document.getElementById('cr-category')?.value || '';
                if (cat) params.set('category', cat);
                var pricelistCode = document.getElementById('cr-pricelist-code')?.value || '';
                if (pricelistCode) params.set('pricelist_code', pricelistCode);

                return params;
            }

            function populateYearSelects() {
                var selects = ['cr-year1', 'cr-year2', 'cr-year3'];
                var currentYear = new Date().getFullYear();
                var defaults = [currentYear, currentYear - 1, currentYear - 2];
                selects.forEach(function (id, idx) {
                    var sel = document.getElementById(id);
                    if (!sel) return;
                    sel.innerHTML = '';
                    for (var y = 2000; y <= 3000; y++) {
                        var opt = document.createElement('option');
                        opt.value = y;
                        opt.textContent = y;
                        if (y === defaults[idx]) opt.selected = true;
                        sel.appendChild(opt);
                    }
                });
            }

            function crGetYear1() { return parseInt(document.getElementById('cr-year1')?.value) || new Date().getFullYear(); }
            function crGetYear2() { return parseInt(document.getElementById('cr-year2')?.value) || new Date().getFullYear() - 1; }
            function crGetYear3() { return parseInt(document.getElementById('cr-year3')?.value) || new Date().getFullYear() - 2; }

            function crFormatCurrency(v) {
                var num = Number(v || 0);
                return '&#8369;' + num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function crDisplaySupplierName(value) {
                var full = String(value || '').trim();
                if (!full) return '---';

                var primary = full.split('/')[0].trim();
                primary = primary
                    .replace(/\s+(INCORPORATED|INC\.?|CORPORATION|CORP\.?|COMPANY|CO\.?|LIMITED|LTD\.?)$/i, '')
                    .replace(/\s+/g, ' ')
                    .trim();

                if (!primary) primary = full;
                return primary.length > 14 ? primary.slice(0, 14).trim() : primary;
            }

            function crCurrentFilterSignature() {
                return JSON.stringify({
                    product_code: crGetMultiSelectValues('cr-product-code').slice().sort(),
                    part_number: crGetMultiSelectValues('cr-part-number').slice().sort(),
                    description: document.getElementById('cr-description')?.value || '',
                    application: document.getElementById('cr-application')?.value || '',
                    category: document.getElementById('cr-category')?.value || '',
                    pricelist_code: document.getElementById('cr-pricelist-code')?.value || '',
                    year1: crGetYear1(),
                    year2: crGetYear2(),
                    year3: crGetYear3()
                });
            }

            function crUpdateGeneratedTimestamp() {
                var y1s = String(crGetYear1()).slice(-2);
                var y2s = String(crGetYear2()).slice(-2);
                var y3s = String(crGetYear3()).slice(-2);
                var th1 = document.getElementById('cr-th-year1');
                var th2 = document.getElementById('cr-th-year2');
                var th3 = document.getElementById('cr-th-year3');
                if (th1) th1.textContent = "'" + y1s;
                if (th2) th2.textContent = "'" + y2s;
                if (th3) th3.textContent = "'" + y3s;

                var crNow = new Date();
                var crGenLabel = 'Generated: ' + crNow.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) + ' ' + crNow.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                var genEl = document.getElementById('cr-print-generated-label');
                if (genEl) genEl.textContent = crGenLabel;
            }

            function crGroupItemsByDescription(items, sorted) {
                var groups = {};
                for (var i = 0; i < items.length; i++) {
                    var desc = items[i].description || '---';
                    if (!groups[desc]) groups[desc] = [];
                    groups[desc].push(items[i]);
                }
                var descKeys = Object.keys(groups);
                if (sorted) {
                    descKeys.sort(function (a, b) {
                        return a.localeCompare(b, undefined, { sensitivity: 'base' });
                    });
                }
                return descKeys.map(function (descName) {
                    var groupItems = groups[descName];
                    if (sorted) {
                        groupItems = groupItems.slice().sort(function (a, b) {
                            return (a.product_code || '').localeCompare(b.product_code || '', undefined, { sensitivity: 'base' });
                        });
                    }
                    return { description: descName, items: groupItems };
                });
            }

            function crRenderSalesCell(salesVal, oumHtml, isPrint) {
                var val = salesVal || 0;
                var oumBlock = (salesVal && Number(salesVal) !== 0 && oumHtml)
                    ? '<div style="font-size:' + (isPrint ? '9.5px' : '9px') + ';font-weight:600;margin-top:1px;' + (isPrint ? '' : 'color:#4a5568;') + '">' + oumHtml + '</div>'
                    : '';
                return '<div>' + val + '</div>' + oumBlock;
            }

            function crFetchJson(url) {
                return fetch(url).then(function (response) {
                    return response.text().then(function (text) {
                        var parsed;
                        try {
                            parsed = JSON.parse(text);
                        } catch (e) {
                            throw new Error('Invalid JSON response (HTTP ' + response.status + '): ' + text.substring(0, 100));
                        }
                        if (!response.ok) {
                            throw new Error(parsed?.error || parsed?.message || ('Request failed with HTTP ' + response.status));
                        }
                        return parsed;
                    });
                });
            }

            function crGenerate() {
                var tbody = document.getElementById('cr-report-tbody');
                tbody.innerHTML = '<tr><td colspan="9" class="py-10 text-center text-slate-400 text-sm font-medium">Loading...</td></tr>';

                var params = crBuildFilterParams({
                    page: crPage,
                    per_page: crPerPage
                });

                crUpdateGeneratedTimestamp();

                crFetchJson(CR_DATA_URL + '?' + params.toString())
                    .then(function (res) {
                        if (!res.success) {
                            tbody.innerHTML = '<tr><td colspan="9" class="py-10 text-center text-slate-400 text-sm">' + crEscapeHtml(res.error || 'Failed to load.') + '</td></tr>';
                            return;
                        }
                        crData = res.items || [];
                        crTotalItems = Number(res.stats?.total_items ?? res.pagination?.total ?? crData.length) || 0;
                        crLastGeneratedSignature = crCurrentFilterSignature();
                        if (res.pagination) {
                            crPage = res.pagination.current_page;
                            crLastPage = res.pagination.last_page;
                            crUpdatePagination();
                        }
                        crRenderTable(crData);
                        var info = document.getElementById('cr-report-info');
                        if (info) info.textContent = 'Items: ' + crTotalItems;
                    })
                    .catch(function () {
                        tbody.innerHTML = '<tr><td colspan="9" class="py-10 text-center text-slate-400 text-sm">Error loading data.</td></tr>';
                    });
            }

            function crRenderTable(items) {
                var tbody = document.getElementById('cr-report-tbody');
                if (!items || items.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="9" class="py-10 text-center text-slate-400 text-sm font-medium">No cost data found.</td></tr>';
                    return;
                }
                var grouped = crGroupItemsByDescription(items, false);
                var y1s = String(crGetYear1()).slice(-2);
                var y2s = String(crGetYear2()).slice(-2);
                var y3s = String(crGetYear3()).slice(-2);
                var html = '';
                for (var g = 0; g < grouped.length; g++) {
                    var descName = grouped[g].description;
                    var groupItems = grouped[g].items;
                    html += '<tr class="cr-desc-head">' +
                        '<td colspan="9" class="border px-3 py-2" style="border-color:#ddd;color:#cc0000;font-size:14px;font-weight:900;font-family:\'Gill Sans Ultra Bold\',\'Gill Sans MT\',sans-serif">DESCRIPTION: ' + crEscapeHtml(descName) + '</td>' +
                        '</tr>' +
                        '<tr class="cr-sub-head bg-slate-100 text-slate-700 font-bold uppercase" style="font-size:11px">' +
                        '<td class="border border-slate-300 px-2 py-1.5" style="width:' + CR_COL_WIDTHS[0] + '">Item Code</td>' +
                        '<td class="border border-slate-300 px-2 py-1.5" style="width:' + CR_COL_WIDTHS[1] + '">Part No#</td>' +
                        '<td class="border border-slate-300 px-2 py-1.5" style="width:' + CR_COL_WIDTHS[2] + '">Application</td>' +
                        '<td class="border border-slate-300 px-2 py-1.5 text-left" style="width:' + CR_COL_WIDTHS[3] + '">PRICE</td>' +
                        '<td class="border border-slate-300 px-2 py-1.5 text-left" style="width:' + CR_COL_WIDTHS[4] + '">Date/Supplier/Cost</td>' +
                        '<td class="border border-slate-300 px-2 py-1.5 text-center" style="width:' + CR_COL_WIDTHS[5] + '">INV</td>' +
                        '<td class="border border-slate-300 px-2 py-1.5 text-center" style="width:' + CR_COL_WIDTHS[6] + '">' + y1s + '</td>' +
                        '<td class="border border-slate-300 px-2 py-1.5 text-center" style="width:' + CR_COL_WIDTHS[7] + '">' + y2s + '</td>' +
                        '<td class="border border-slate-300 px-2 py-1.5 text-center" style="width:' + CR_COL_WIDTHS[8] + '">' + y3s + '</td>' +
                        '</tr>';
                    for (var j = 0; j < groupItems.length; j++) {
                        var item = groupItems[j];
                        var unit = crEscapeHtml(item.unit || '---');
                        var oumHtml = crEscapeHtml(item.oum || '');
                        html += '<tr class="hover:bg-slate-50/50 transition-colors">' +
                            '<td class="border border-slate-200 px-2 py-2 font-bold text-slate-700" style="white-space:normal;word-break:normal;overflow-wrap:break-word;vertical-align:top">' + crEscapeHtml(item.product_code || '---') + '</td>' +
                            '<td class="border border-slate-200 px-2 py-2 text-slate-600" style="white-space:normal;word-break:normal;overflow-wrap:break-word;vertical-align:top">' + crEscapeHtml(item.part_number || '---') + '</td>' +
                            '<td class="border border-slate-200 px-2 py-2 text-slate-600" style="font-size:11px;line-height:1.25;white-space:normal;word-break:normal;overflow-wrap:break-word;vertical-align:top">' + crFormatApplicationText(item.application) + '</td>' +
                            '<td class="border border-slate-200 px-2 py-2 text-left font-semibold text-slate-700" style="font-size:10px;line-height:1.25;white-space:normal;vertical-align:top">' +
                                '<div>' + crFormatCurrency(item.local_price) + '</div>' +
                                '<div style="color:#008000;font-weight:800">' + crFormatCurrency(item.online_price) + '</div>' +
                            '</td>' +
                            '<td class="border border-slate-200 px-2 py-2 text-left text-slate-500" style="font-size:10px;line-height:1.25;white-space:normal;overflow-wrap:break-word;vertical-align:top">' +
                                '<div>' + crEscapeHtml(item.cost_date || '---') + '</div>' +
                                '<div title="' + crEscapeHtml(item.cost_supplier || '') + '" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + crEscapeHtml(crDisplaySupplierName(item.cost_supplier)) + '</div>' +
                                '<div style="font-weight:800;color:#334155">COST: ' + crFormatCurrency(item.supplier_cost) + '</div>' +
                            '</td>' +
                            '<td class="border border-slate-200 px-2 py-2 text-center font-bold text-slate-700" style="line-height:1.25;white-space:normal;vertical-align:top">' +
                                '<div>' + (item.on_hand || 0) + '</div>' +
                                '<div style="font-size:10px;color:#4a5568;font-weight:600;margin-top:1px">' + unit + '</div>' +
                            '</td>' +
                            '<td class="border border-slate-200 px-2 py-2 text-center font-semibold text-slate-600" style="line-height:1.25;white-space:normal;vertical-align:top">' + crRenderSalesCell(item.sales_1, oumHtml, false) + '</td>' +
                            '<td class="border border-slate-200 px-2 py-2 text-center font-semibold text-slate-600" style="line-height:1.25;white-space:normal;vertical-align:top">' + crRenderSalesCell(item.sales_2, oumHtml, false) + '</td>' +
                            '<td class="border border-slate-200 px-2 py-2 text-center font-semibold text-slate-600" style="line-height:1.25;white-space:normal;vertical-align:top">' + crRenderSalesCell(item.sales_3, oumHtml, false) + '</td>' +
                            '</tr>';
                    }
                }
                tbody.innerHTML = html;
            }

            function crUpdatePagination() {
                document.getElementById('cr-page-info').textContent = 'Page ' + crPage + ' of ' + crLastPage;
                document.getElementById('cr-prev-page').disabled = crPage <= 1;
                document.getElementById('cr-next-page').disabled = crPage >= crLastPage;
            }

            function crChangePage(dir) {
                var np = crPage + dir;
                if (np < 1 || np > crLastPage) return;
                crPage = np;
                crGenerate();
            }

            function crRenderPrintGroupHtml(group, y1s, y2s, y3s) {
                var descName = group.description;
                var groupItems = group.items;
                var chunk = '<div class="cr-desc-head">DESCRIPTION: ' + crEscapeHtml(descName) + '</div>' +
                    '<table>' +
                    crBuildColgroupHtml() +
                    '<thead>' +
                    '<tr>' +
                    '<th>ITEM CODE</th>' +
                    '<th>PART NO#</th>' +
                    '<th>APPLICATION</th>' +
                    '<th style="text-align:left">PRICE</th>' +
                    '<th style="text-align:left">DATE/SUPPLIER</th>' +
                    '<th>INV</th>' +
                    '<th>' + y1s + '</th>' +
                    '<th>' + y2s + '</th>' +
                    '<th>' + y3s + '</th>' +
                    '</tr>' +
                    '</thead><tbody>';

                for (var j = 0; j < groupItems.length; j++) {
                    var item = groupItems[j];
                    var unit = crEscapeHtml(item.unit || '---');
                    var oumHtml = crEscapeHtml(item.oum || '---');
                    chunk += '<tr>' +
                        '<td class="cr-code-cell">' + crEscapeHtml(item.product_code || '---') + '</td>' +
                        '<td class="cr-code-cell">' + crEscapeHtml(item.part_number || '---') + '</td>' +
                        '<td class="cr-app-cell">' + crFormatApplicationText(item.application) + '</td>' +
                        '<td class="cr-price-cell">' +
                            '<div>' + crFormatCurrency(item.local_price) + '</div>' +
                            '<div style="color:#008000;font-weight:900">' + crFormatCurrency(item.online_price) + '</div>' +
                        '</td>' +
                        '<td class="cr-sup-cell">' +
                            '<div>' + crEscapeHtml(item.cost_date || '---') + '</div>' +
                            '<div title="' + crEscapeHtml(item.cost_supplier || '') + '" class="cr-sup-name">' + crEscapeHtml(crDisplaySupplierName(item.cost_supplier)) + '</div>' +
                            '<div style="font-weight:900">COST: ' + crFormatCurrency(item.supplier_cost) + '</div>' +
                        '</td>' +
                        '<td class="cr-num-cell">' +
                            '<div>' + (item.on_hand || 0) + '</div>' +
                            '<div class="cr-unit-lbl">' + unit + '</div>' +
                        '</td>' +
                        '<td class="cr-num-cell">' + crRenderSalesCell(item.sales_1, oumHtml, true) + '</td>' +
                        '<td class="cr-num-cell">' + crRenderSalesCell(item.sales_2, oumHtml, true) + '</td>' +
                        '<td class="cr-num-cell">' + crRenderSalesCell(item.sales_3, oumHtml, true) + '</td>' +
                        '</tr>';
                }
                return chunk + '</tbody></table>';
            }

            function crRenderPrintHtml(items) {
                if (!items || items.length === 0) return '<p style="text-align:center;color:#999">No data.</p>';
                var grouped = crGroupItemsByDescription(items, true);
                var y1s = String(crGetYear1()).slice(-2);
                var y2s = String(crGetYear2()).slice(-2);
                var y3s = String(crGetYear3()).slice(-2);
                var parts = [];
                for (var g = 0; g < grouped.length; g++) {
                    parts.push(crRenderPrintGroupHtml(grouped[g], y1s, y2s, y3s));
                }
                return parts.join('');
            }

            function crRenderPrintHtmlAsync(items, startPct, endPct, onProgress) {
                if (!items || items.length === 0) {
                    return Promise.resolve('<p style="text-align:center;color:#999">No data.</p>');
                }

                var grouped = crGroupItemsByDescription(items, true);
                var totalCount = items.length;
                var y1s = String(crGetYear1()).slice(-2);
                var y2s = String(crGetYear2()).slice(-2);
                var y3s = String(crGetYear3()).slice(-2);
                var parts = [];
                var processed = 0;
                var groupIndex = 0;

                return new Promise(function (resolve) {
                    function processBatch() {
                        var batchItems = 0;
                        while (groupIndex < grouped.length && (batchItems === 0 || batchItems + grouped[groupIndex].items.length <= 120)) {
                            var grp = grouped[groupIndex++];
                            parts.push(crRenderPrintGroupHtml(grp, y1s, y2s, y3s));
                            processed += grp.items.length;
                            batchItems += grp.items.length;
                        }

                        if (onProgress) {
                            var pct = startPct + Math.round((processed / totalCount) * (endPct - startPct));
                            onProgress(pct, processed, totalCount);
                        }

                        if (groupIndex < grouped.length) {
                            setTimeout(processBatch, 0);
                        } else {
                            resolve(parts.join(''));
                        }
                    }

                    processBatch();
                });
            }

            function crRestorePrintBtn() {
                var pBtn = document.getElementById('cr-print-btn');
                if (pBtn) {
                    pBtn.disabled = false;
                    pBtn.classList.remove('opacity-60', 'cursor-not-allowed');
                }
            }

            function crBuildPrintDocumentHtml(printHtml, genLabel) {
                return '<!DOCTYPE html><html><head><title>C.S.I REPORT</title><style>' +
                    '@page { size: portrait; margin: 1cm 0.4cm; }' +
                    'body { font-family: "Courier New", monospace; margin: 0; padding: 0; color: #000; }' +
                    'table { width: 100%; border-collapse: collapse; table-layout: fixed; }' +
                    'thead { display: table-header-group; }' +
                    'th, td { border: 1px solid #000; padding: 2px; box-sizing: border-box; }' +
                    'tr { page-break-inside: avoid; break-inside: avoid; }' +
                    'th { font-size: 10px; font-weight: 900; background: #dde3ec; text-align: center; vertical-align: middle; line-height: 1.15; }' +
                    'td { font-size: 11px; font-weight: 700; vertical-align: top; }' +
                    '.cr-code-cell { font-size: 10.5px; white-space: normal; word-break: normal; overflow-wrap: break-word; }' +
                    '.cr-app-cell { font-family: Arial, "Helvetica Neue", Helvetica, sans-serif; font-size: 10px; font-weight: 700; line-height: 1.25; text-align: left; padding: 2px 3px; white-space: normal; word-break: normal; overflow-wrap: break-word; }' +
                    '.cr-price-cell { font-size: 9.5px; line-height: 1.25; text-align: left; white-space: normal; overflow-wrap: break-word; }' +
                    '.cr-sup-cell { font-size: 9.5px; line-height: 1.25; text-align: left; white-space: normal; overflow-wrap: break-word; }' +
                    '.cr-sup-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }' +
                    '.cr-num-cell { font-size: 11px; line-height: 1.2; text-align: center; padding: 2px 1px; }' +
                    '.cr-unit-lbl { font-size: 9.5px; font-weight: 600; margin-top: 1px; }' +
                    '.cr-desc-head { color: #cc0000; font-size: 14px; font-weight: 900; font-family: "Gill Sans Ultra Bold","Gill Sans MT",sans-serif; margin: 8px 0 2px 0; page-break-after: avoid; break-after: avoid; }' +
                    '.hdr { text-align: center; font-size: 16px; font-weight: 900; margin: 0 0 6px 0; }' +
                    '.meta { text-align: center; font-size: 12px; font-weight: 700; margin: 0 0 8px 0; }' +
                    '</style></head><body>' +
                    '<div class="hdr">C.S.I  REPORT</div>' +
                    '<div class="meta"><span>' + crEscapeHtml(genLabel) + '</span></div>' +
                    printHtml +
                    '</body></html>';
            }

            function crPrintViaIframe(docHtml) {
                var existing = document.getElementById('cr-print-fallback-frame');
                if (existing) existing.remove();

                var iframe = document.createElement('iframe');
                iframe.id = 'cr-print-fallback-frame';
                iframe.style.position = 'fixed';
                iframe.style.right = '0';
                iframe.style.bottom = '0';
                iframe.style.width = '0';
                iframe.style.height = '0';
                iframe.style.border = '0';
                document.body.appendChild(iframe);

                var doc = iframe.contentWindow.document;
                doc.open();
                doc.write(docHtml);
                doc.close();

                setTimeout(function () {
                    if (window.W68Loader) window.W68Loader.hideProgress();
                    crRestorePrintBtn();
                    try {
                        iframe.contentWindow.focus();
                        iframe.contentWindow.print();
                    } catch (e) {
                        console.error('Fallback print trigger error:', e);
                        alert('Pop-up blocked! Please allow pop-ups for this site and try again.');
                    }
                    setTimeout(function () {
                        if (iframe.parentNode) iframe.parentNode.removeChild(iframe);
                    }, 60000);
                }, 250);
            }

            function crDoPrint(itemsOrHtml, alreadyRendered) {
                var printHtml = alreadyRendered
                    ? String(itemsOrHtml || '')
                    : (itemsOrHtml ? crRenderPrintHtml(itemsOrHtml) : document.getElementById('cr-print-container').innerHTML);
                var genLabel = (document.getElementById('cr-print-generated-label') || {}).textContent || '';
                if (!genLabel) {
                    var crNow = new Date();
                    genLabel = 'Generated: ' + crNow.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) + ' ' + crNow.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                }

                if (window.W68Loader) window.W68Loader.setProgress(97, 'Writing print layout...');
                var docHtml = crBuildPrintDocumentHtml(printHtml, genLabel);

                var win = window.open('', '_blank');
                if (!win) {
                    if (window.W68Loader) window.W68Loader.setProgress(100, 'Opening print dialog...');
                    crPrintViaIframe(docHtml);
                    return;
                }

                win.document.open();
                win.document.write(docHtml);
                win.document.close();
                win.focus();

                if (window.W68Loader) window.W68Loader.setProgress(100, 'Opening print dialog...');

                setTimeout(function () {
                    if (window.W68Loader) window.W68Loader.hideProgress();
                    crRestorePrintBtn();
                    try {
                        win.print();
                    } catch (e) {
                        console.error('Print trigger error:', e);
                    }
                }, 250);
            }

            async function crPrintAll() {
                var printBtn = document.getElementById('cr-print-btn');
                if (printBtn) {
                    printBtn.disabled = true;
                    printBtn.classList.add('opacity-60', 'cursor-not-allowed');
                }

                crUpdateGeneratedTimestamp();

                if (window.W68Loader) {
                    window.W68Loader.showProgress(0, 'Connecting to C.S.I Report server...');
                }

                var tbody = document.getElementById('cr-report-tbody');

                try {
                    var allItems = [];
                    var totalItems = 0;

                    var currentSignature = crCurrentFilterSignature();
                    if (
                        crLastGeneratedSignature === currentSignature
                        && crTotalItems === crData.length
                        && crTotalItems > 0
                    ) {
                        allItems = crData.slice();
                        totalItems = allItems.length;
                        if (window.W68Loader) {
                            window.W68Loader.setProgress(80, 'Loaded ' + totalItems.toLocaleString() + ' / ' + totalItems.toLocaleString() + ' items...');
                        }
                    } else {
                        tbody.innerHTML = '<tr><td colspan="9" class="py-10 text-center text-slate-400 text-sm font-medium">Loading all data for print...</td></tr>';

                        var firstParams = crBuildFilterParams({
                            page: 1,
                            per_page: CR_PRINT_CHUNK_SIZE,
                            skip_stats: 1
                        });

                        var firstRes = await crFetchJson(CR_DATA_URL + '?' + firstParams.toString());
                        if (!firstRes.success || !Array.isArray(firstRes.items)) {
                            throw new Error(firstRes.error || 'Failed to load C.S.I report data.');
                        }

                        allItems = firstRes.items.slice();
                        totalItems = Number(firstRes.pagination?.total ?? firstRes.stats?.total_items ?? allItems.length) || allItems.length;
                        var lastPage = Math.max(1, Number(firstRes.pagination?.last_page ?? 1) || 1);
                        var nextAfterId = firstRes.pagination?.next_after_id ?? null;

                        if (totalItems === 0 || allItems.length === 0) {
                            if (window.W68Loader) window.W68Loader.hideProgress();
                            crRestorePrintBtn();
                            tbody.innerHTML = '<tr><td colspan="9" class="py-10 text-center text-slate-400 text-sm font-medium">No cost data found.</td></tr>';
                            alert('No C.S.I report items found for the selected filters.');
                            return;
                        }

                        var firstPct = Math.min(80, Math.round((Math.min(allItems.length, totalItems) / totalItems) * 80));
                        if (window.W68Loader) {
                            window.W68Loader.setProgress(
                                firstPct,
                                'Loaded ' + Math.min(allItems.length, totalItems).toLocaleString() + ' / ' + totalItems.toLocaleString() + ' items (Batch 1 of ' + lastPage + ')...'
                            );
                        }

                        for (var p = 2; p <= lastPage; p++) {
                            var batchQuery = {
                                page: p,
                                per_page: CR_PRINT_CHUNK_SIZE,
                                skip_stats: 1,
                                known_total: totalItems
                            };
                            if (nextAfterId) {
                                batchQuery.after_id = nextAfterId;
                            }
                            var pageParams = crBuildFilterParams(batchQuery);

                            var pageRes = await crFetchJson(CR_DATA_URL + '?' + pageParams.toString());
                            if (!pageRes.success || !Array.isArray(pageRes.items)) {
                                throw new Error(pageRes.error || ('Failed loading batch ' + p + ' of ' + lastPage + '.'));
                            }

                            if (pageRes.items.length === 0) {
                                break;
                            }

                            if (pageRes.pagination && pageRes.pagination.next_after_id) {
                                nextAfterId = pageRes.pagination.next_after_id;
                            } else {
                                nextAfterId = null;
                            }

                            for (var k = 0; k < pageRes.items.length; k++) {
                                allItems.push(pageRes.items[k]);
                            }

                            var loadedNow = Math.min(allItems.length, totalItems);
                            var pct = Math.min(80, Math.round((loadedNow / totalItems) * 80));
                            if (window.W68Loader) {
                                window.W68Loader.setProgress(
                                    pct,
                                    'Loaded ' + loadedNow.toLocaleString() + ' / ' + totalItems.toLocaleString() + ' items (Batch ' + p + ' of ' + lastPage + ')...'
                                );
                            }
                        }
                    }

                    var info = document.getElementById('cr-report-info');
                    if (info) info.textContent = 'Items: ' + totalItems;

                    crRenderTable(allItems.length > 300 ? allItems.slice(0, crPerPage) : allItems);

                    if (window.W68Loader) {
                        window.W68Loader.setProgress(81, 'Grouping ' + allItems.length.toLocaleString() + ' items for print...');
                    }

                    var printHtml = await crRenderPrintHtmlAsync(allItems, 81, 96, function (pct, doneCount, itemTotal) {
                        if (window.W68Loader) {
                            window.W68Loader.setProgress(
                                pct,
                                'Formatting printable rows (' + doneCount.toLocaleString() + ' / ' + itemTotal.toLocaleString() + ')...'
                            );
                        }
                    });

                    crDoPrint(printHtml, true);
                } catch (err) {
                    if (window.W68Loader) window.W68Loader.hideProgress();
                    crRestorePrintBtn();
                    console.error('Cost report print error:', err);
                    tbody.innerHTML = '<tr><td colspan="9" class="py-10 text-center text-slate-400 text-sm">' + crEscapeHtml(err?.message || 'Error loading data for print.') + '</td></tr>';
                }
            }

            function initCrDropdowns() {
                document.querySelectorAll('#page-cost-report .sales-report-searchable').forEach(function (wrapper) {
                    var input = wrapper.querySelector('.sales-report-input');
                    var toggle = wrapper.querySelector('.sales-report-toggle');
                    var dropdown = wrapper.querySelector('.sales-report-dropdown');
                    var endpoint = wrapper.dataset.endpoint;
                    var valueType = wrapper.dataset.type || 'text';
                    var defaultLabel = wrapper.dataset.defaultLabel || '';
                    var isMultiSelect = wrapper.dataset.multiSelect === 'true';

                    if (!input || !toggle || !dropdown || !endpoint) return;

                    var activeIndex = -1;
                    var currentItems = [];
                    var debounceTimer = null;
                    var activeController = null;
                    var optionCache = new Map();
                    var selectedValues = new Set();
                    wrapper._crSelectedValues = selectedValues;

                    var selectedList = null;
                    if (isMultiSelect) {
                        selectedList = document.createElement('div');
                        selectedList.className = 'cr-selected-list';
                        selectedList.setAttribute('aria-live', 'polite');
                        wrapper.appendChild(selectedList);
                    }

                    function updateSelectedList() {
                        if (!isMultiSelect || !selectedList) return;

                        var values = Array.from(selectedValues);
                        selectedList.innerHTML = values.map(function (value) {
                            return '<button type="button" class="cr-selected-chip" data-selected-value="' + crEscapeHtml(value) + '" title="Remove ' + crEscapeHtml(value) + '">' +
                                '<span>' + crEscapeHtml(value) + '</span><strong aria-hidden="true">×</strong>' +
                                '</button>';
                        }).join('');

                        selectedList.querySelectorAll('.cr-selected-chip').forEach(function (chip) {
                            chip.addEventListener('click', function () {
                                selectedValues.delete(chip.dataset.selectedValue || '');
                                updateSelectedList();
                                renderOptions(currentItems);
                            });
                        });
                    }

                    function renderOptions(items) {
                        currentItems = items;
                        activeIndex = -1;
                        var normalizedItems = [];
                        if (defaultLabel) {
                            normalizedItems.push({ label: defaultLabel, value: '', meta: 'Default' });
                        }
                        items.forEach(function (item) {
                            if (valueType === 'object') {
                                normalizedItems.push({
                                    label: item.name || '---',
                                    value: item.name || '',
                                    meta: 'ID ' + (item.id ?? 'N/A')
                                });
                            } else {
                                normalizedItems.push({
                                    label: item || '---',
                                    value: item || '',
                                    meta: 'Option'
                                });
                            }
                        });
                        if (!normalizedItems.length) {
                            dropdown.innerHTML = '<button type="button" class="sales-report-option"><span>No results found</span><span class="sales-report-option-meta">Empty</span></button>';
                            dropdown.classList.remove('hidden');
                            return;
                        }
                        dropdown.innerHTML = normalizedItems.map(function (item, index) {
                            var value = String(item.value || '');
                            var checked = isMultiSelect && selectedValues.has(value);
                            var optionClass = 'sales-report-option' + (isMultiSelect ? ' cr-multi-option' : '') + (checked ? ' is-selected' : '');
                            var labelHtml = isMultiSelect
                                ? '<span class="flex min-w-0 flex-1 items-center gap-2"><input class="cr-multi-check" type="checkbox" tabindex="-1" ' + (checked ? 'checked' : '') + '><span class="truncate">' + crEscapeHtml(item.label) + '</span></span>'
                                : '<span>' + crEscapeHtml(item.label) + '</span>';
                            var meta = checked ? 'Selected' : item.meta;

                            if (isMultiSelect) {
                                return '<div role="option" tabindex="-1" aria-selected="' + (checked ? 'true' : 'false') + '" class="' + optionClass + '" data-index="' + index + '" data-value="' + crEscapeHtml(value) + '">' +
                                    labelHtml +
                                    '<span class="sales-report-option-meta">' + crEscapeHtml(meta) + '</span>' +
                                    '</div>';
                            }

                            return '<button type="button" class="' + optionClass + '" data-index="' + index + '" data-value="' + crEscapeHtml(value) + '">' +
                                labelHtml +
                                '<span class="sales-report-option-meta">' + crEscapeHtml(meta) + '</span>' +
                                '</button>';
                        }).join('');
                        dropdown.classList.remove('hidden');
                        dropdown.querySelectorAll('.sales-report-option').forEach(function (option) {
                            option.addEventListener('click', function () {
                                var value = option.dataset.value || '';

                                if (isMultiSelect) {
                                    if (!value) return;
                                    if (selectedValues.has(value)) {
                                        selectedValues.delete(value);
                                    } else {
                                        selectedValues.add(value);
                                    }
                                    input.value = '';
                                    updateSelectedList();
                                    renderOptions(currentItems);
                                    input.focus();
                                    return;
                                }

                                input.value = value;
                                closeDropdown();
                            });
                        });
                    }

                    function fetchOptions(query) {
                        var trimmed = String(query || '').trim();
                        var cacheKey = trimmed.toLowerCase();

                        if (optionCache.has(cacheKey)) {
                            renderOptions(optionCache.get(cacheKey));
                            return;
                        }

                        if (activeController) {
                            activeController.abort();
                            activeController = null;
                        }

                        try {
                            var url = new URL(endpoint, window.location.origin);
                            if (trimmed !== '') {
                                url.searchParams.set('query', trimmed);
                            }

                            activeController = typeof AbortController !== 'undefined' ? new AbortController() : null;
                            var fetchOpts = activeController ? { signal: activeController.signal } : {};

                            fetch(url.toString(), fetchOpts)
                                .then(function (r) { return r.json(); })
                                .then(function (data) {
                                    var list = Array.isArray(data) ? data : [];
                                    if (optionCache.size > 80) optionCache.clear();
                                    optionCache.set(cacheKey, list);
                                    renderOptions(list);
                                })
                                .catch(function (err) {
                                    if (err && err.name === 'AbortError') return;
                                    dropdown.innerHTML = '<button type="button" class="sales-report-option"><span>Failed to load</span><span class="sales-report-option-meta">Error</span></button>';
                                    dropdown.classList.remove('hidden');
                                });
                        } catch (e) {
                            dropdown.innerHTML = '<button type="button" class="sales-report-option"><span>Failed to load</span><span class="sales-report-option-meta">Error</span></button>';
                            dropdown.classList.remove('hidden');
                        }
                    }

                    function closeDropdown() {
                        dropdown.classList.add('hidden');
                        activeIndex = -1;
                    }

                    input.addEventListener('focus', function () { fetchOptions(input.value); });
                    input.addEventListener('input', function () {
                        clearTimeout(debounceTimer);
                        debounceTimer = setTimeout(function () { fetchOptions(input.value); }, 220);
                    });
                    input.addEventListener('keydown', function (event) {
                        var options = Array.from(dropdown.querySelectorAll('.sales-report-option'));
                        if (!options.length || dropdown.classList.contains('hidden')) return;
                        if (event.key === 'ArrowDown') {
                            event.preventDefault();
                            activeIndex = (activeIndex + 1) % options.length;
                            options.forEach(function (o, i) { o.classList.toggle('is-active', i === activeIndex); });
                        }
                        if (event.key === 'ArrowUp') {
                            event.preventDefault();
                            activeIndex = (activeIndex - 1 + options.length) % options.length;
                            options.forEach(function (o, i) { o.classList.toggle('is-active', i === activeIndex); });
                        }
                        if (event.key === 'Enter' && activeIndex >= 0) {
                            event.preventDefault();
                            options[activeIndex].click();
                        }
                        if (event.key === 'Escape') {
                            closeDropdown();
                        }
                    });
                    toggle.addEventListener('click', function (event) {
                        event.preventDefault();
                        event.stopPropagation();
                        if (dropdown.classList.contains('hidden')) {
                            fetchOptions(input.value);
                        } else {
                            closeDropdown();
                        }
                    });
                    document.addEventListener('click', function (event) {
                        if (!wrapper.contains(event.target)) {
                            closeDropdown();
                        }
                    });
                });
            }

            window.crChangePage = crChangePage;

            document.addEventListener('DOMContentLoaded', function () {
                populateYearSelects();
                initCrDropdowns();
                document.getElementById('cr-print-btn')?.addEventListener('click', crPrintAll);
                if (window.lucide?.createIcons) window.lucide.createIcons();
            });
        })();
    </script>
@endpush
