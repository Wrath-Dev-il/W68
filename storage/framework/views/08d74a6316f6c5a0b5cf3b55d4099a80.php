<?php $__env->startPush('styles'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/sales-report.css')); ?>?v=<?php echo e(time()); ?>">
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
<?php $__env->stopPush(); ?>

<?php $__env->startSection('cost_report_content'); ?>
<div id="page-cost-report" class="rep-page space-y-6 animate-fade-in">
    <div class="flex items-center gap-4 bg-white border border-slate-200 rounded-xl p-5 shadow-sm no-print">
        <label class="radio-card">
            <input type="radio" name="report_type" value="cost_report" checked>
            Cost Report
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
                <div class="sales-report-searchable" data-endpoint="<?php echo e(url('/special/reports/cost-report/product-codes')); ?>" data-type="text" data-input-id="cr-product-code" data-multi-select="true">
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
                <div class="sales-report-searchable" data-endpoint="<?php echo e(url('/special/reports/cost-report/part-numbers')); ?>" data-type="text" data-input-id="cr-part-number" data-multi-select="true">
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
                <div class="sales-report-searchable" data-endpoint="<?php echo e(route('special.cost-report.descriptions')); ?>" data-type="text" data-input-id="cr-description">
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
                <div class="sales-report-searchable" data-endpoint="<?php echo e(route('special.cost-report.applications')); ?>" data-type="text" data-input-id="cr-application">
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
                <div class="sales-report-searchable" data-endpoint="<?php echo e(route('special.cost-report.categories')); ?>" data-type="text" data-input-id="cr-category">
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
                <div class="sales-report-searchable" data-endpoint="<?php echo e(url('/special/reports/cost-report/pricelist-codes')); ?>" data-type="text" data-input-id="cr-pricelist-code">
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
        <div class="cr-print-header">W68 AUTOPARTS &amp; SERVICE CENTER</div>
        <div class="cr-print-meta">
            <span id="cr-print-generated-label"></span>
        </div>
        <div id="cr-print-container">
            <table class="cr-table w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-slate-700 font-bold uppercase">
                        <th class="border border-slate-300 px-3 py-2" rowspan="2" style="width:14%">Item Code</th>
                        <th class="border border-slate-300 px-3 py-2" rowspan="2" style="width:12%">Part No#</th>
                        <th class="border border-slate-300 px-3 py-2" rowspan="2" style="width:28%">Application</th>
                        <th class="border border-slate-300 px-3 py-2 text-left" rowspan="2" style="width:12%">PRICE</th>
                        <th class="border border-slate-300 px-3 py-2 text-left" rowspan="2" style="width:16%"><div>DATE</div><div>SUPPLIER</div><div>COST</div></th>
                        <th class="border border-slate-300 px-3 py-2" rowspan="2" style="width:6%">INV</th>
                        <th class="border border-slate-300 px-3 py-2 text-center" colspan="3" style="width:12%">SALES</th>
                    </tr>
                    <tr class="bg-slate-50 text-slate-700 font-bold uppercase">
                        <th class="border border-slate-300 px-3 py-2" id="cr-th-year1" style="width:4%">21</th>
                        <th class="border border-slate-300 px-3 py-2" id="cr-th-year2" style="width:4%">22</th>
                        <th class="border border-slate-300 px-3 py-2" id="cr-th-year3" style="width:4%">23</th>
                    </tr>
                </thead>
                <tbody id="cr-report-tbody" class="text-slate-600">
                    <tr><td colspan="9" class="py-10 text-center text-slate-400">Click Generate to load cost report data.</td></tr>
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
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script>
        (function () {
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

            function crGetMultiSelectValues(inputId) {
                var input = document.getElementById(inputId);
                if (!input) return [];

                var wrapper = input.closest('.sales-report-searchable');
                if (wrapper?.dataset.multiSelect === 'true' && wrapper._crSelectedValues) {
                    var selected = Array.from(wrapper._crSelectedValues);
                    if (selected.length) return selected;
                }

                // Backward-compatible: if nothing is checked, an exact typed value
                // still behaves like the old single-select filter.
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

                // The supplier column is intentionally a short display label so the
                // Date and Cost lines stay fully visible. Keep only the primary alias,
                // remove common corporate suffixes, then cap the display length.
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

            function crGenerate() {
                var tbody = document.getElementById('cr-report-tbody');
                tbody.innerHTML = '<tr><td colspan="9" class="py-10 text-center text-slate-400 text-sm font-medium">Loading...</td></tr>';

                var params = new URLSearchParams();
                params.set('page', crPage);
                params.set('per_page', crPerPage);
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

                var y1s = String(crGetYear1()).slice(-2);
                var y2s = String(crGetYear2()).slice(-2);
                var y3s = String(crGetYear3()).slice(-2);
                document.getElementById('cr-th-year1').textContent = y1s;
                document.getElementById('cr-th-year2').textContent = y2s;
                document.getElementById('cr-th-year3').textContent = y3s;
                var crNow = new Date();
                var crGenLabel = 'Generated: ' + crNow.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) + ' ' + crNow.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                var genEl = document.getElementById('cr-print-generated-label');
                if (genEl) genEl.textContent = crGenLabel;

                fetch('<?php echo e(route("special.cost-report.data")); ?>?' + params.toString())
                    .then(function (r) { return r.text().then(function (t) { return JSON.parse(t); }); })
                    .then(function (res) {
                        if (!res.success) {
                            tbody.innerHTML = '<tr><td colspan="9" class="py-10 text-center text-slate-400 text-sm">' + (res.error || 'Failed to load.') + '</td></tr>';
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
                        if (res.stats) info.textContent = 'Items: ' + (res.stats.total_items || 0);
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
                var groups = {};
                for (var i = 0; i < items.length; i++) {
                    var desc = items[i].description || '---';
                    if (!groups[desc]) groups[desc] = [];
                    groups[desc].push(items[i]);
                }
                var y1s = String(crGetYear1()).slice(-2);
                var y2s = String(crGetYear2()).slice(-2);
                var y3s = String(crGetYear3()).slice(-2);
                var html = '';
                var descKeys = Object.keys(groups);
                for (var g = 0; g < descKeys.length; g++) {
                    var descName = descKeys[g];
                    var groupItems = groups[descName];
                    html += '<tr class="cr-desc-head">' +
                        '<td colspan="9" class="border px-3 py-2" style="border-color:#ddd;color:#cc0000;font-size:14px;font-weight:900;font-family:\'Gill Sans Ultra Bold\',\'Gill Sans MT\',sans-serif">DESCRIPTION: ' + descName.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</td>' +
                        '</tr>' +
                        '<tr class="cr-sub-head bg-slate-100 text-slate-700 font-bold uppercase" style="font-size:11px">' +
                        '<td class="border border-slate-300 px-3 py-1.5">Item Code</td>' +
                        '<td class="border border-slate-300 px-3 py-1.5">Part No#</td>' +
                        '<td class="border border-slate-300 px-3 py-1.5">Application</td>' +
                        '<td class="border border-slate-300 px-3 py-1.5 text-left">PRICE</td>' +
                        '<td class="border border-slate-300 px-3 py-1.5 text-left">Date/Supplier/Cost</td>' +
                        '<td class="border border-slate-300 px-3 py-1.5 text-center">ON HAND</td>' +
                        '<td class="border border-slate-300 px-3 py-1.5 text-right">' + y1s + '</td>' +
                        '<td class="border border-slate-300 px-3 py-1.5 text-right">' + y2s + '</td>' +
                        '<td class="border border-slate-300 px-3 py-1.5 text-right">' + y3s + '</td>' +
                        '</tr>';
                    for (var j = 0; j < groupItems.length; j++) {
                        var item = groupItems[j];
                        html += '<tr class="hover:bg-slate-50/50 transition-colors">' +
                            '<td class="border border-slate-200 px-3 py-2 font-bold text-slate-700" style="white-space:normal;overflow-wrap:anywhere;word-break:break-word">' + (item.product_code || '---') + '</td>' +
                            '<td class="border border-slate-200 px-3 py-2 text-slate-600" style="word-break:break-word;white-space:normal">' + (item.part_number || '---') + '</td>' +
                            '<td class="border border-slate-200 px-3 py-2 text-slate-600" style="font-size:11px;white-space:normal;overflow-wrap:anywhere">' + (item.application || '---') + '</td>' +
                            '<td class="border border-slate-200 px-3 py-2 text-left font-semibold text-slate-700" style="font-size:10px;line-height:1.25;white-space:normal">' +
                                '<div>' + crFormatCurrency(item.local_price) + '</div>' +
                                '<div style="color:#008000;font-weight:800">' + crFormatCurrency(item.online_price) + '</div>' +
                            '</td>' +
                            '<td class="border border-slate-200 px-3 py-2 text-left text-slate-500" style="font-size:10px;line-height:1.25;white-space:normal;overflow-wrap:anywhere">' +
                                '<div>' + (item.cost_date || '---') + '</div>' +
                                '<div title="' + crEscapeHtml(item.cost_supplier || '') + '" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + crEscapeHtml(crDisplaySupplierName(item.cost_supplier)) + '</div>' +
                                '<div style="font-weight:800;color:#334155">COST: ' + crFormatCurrency(item.supplier_cost) + '</div>' +
                            '</td>' +
                            '<td class="border border-slate-200 px-3 py-2 text-right font-bold text-slate-700 truncate" style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis">' + (item.on_hand || 0) + '</td>' +
                            '<td class="border border-slate-200 px-3 py-2 text-right font-semibold text-slate-600 truncate" style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis">' + (item.sales_1 || 0) + '</td>' +
                            '<td class="border border-slate-200 px-3 py-2 text-right font-semibold text-slate-600 truncate" style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis">' + (item.sales_2 || 0) + '</td>' +
                            '<td class="border border-slate-200 px-3 py-2 text-right font-semibold text-slate-600 truncate" style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis">' + (item.sales_3 || 0) + '</td>' +
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

            function crRenderPrintHtml(items) {
                if (!items || items.length === 0) return '<p style="text-align:center;color:#999">No data.</p>';
                var groups = {};
                for (var i = 0; i < items.length; i++) {
                    var desc = items[i].description || '---';
                    if (!groups[desc]) groups[desc] = [];
                    groups[desc].push(items[i]);
                }
                var y1s = String(crGetYear1()).slice(-2);
                var y2s = String(crGetYear2()).slice(-2);
                var y3s = String(crGetYear3()).slice(-2);
                var html = '';
                var descKeys = Object.keys(groups);
                for (var g = 0; g < descKeys.length; g++) {
                    var descName = descKeys[g];
                    var groupItems = groups[descName];
                    html += '<div class="cr-desc-head" style="margin-top:8px;margin-bottom:2px;page-break-after:avoid">DESCRIPTION: ' + descName.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</div>' +
                        '<table style="width:100%;border-collapse:collapse;table-layout:fixed">' +
                        '<colgroup>' +
                        '<col style="width:14%">' +
                        '<col style="width:12%">' +
                        '<col style="width:28%">' +
                        '<col style="width:12%">' +
                        '<col style="width:16%">' +
                        '<col style="width:6%">' +
                        '<col style="width:4%">' +
                        '<col style="width:4%">' +
                        '<col style="width:4%">' +
                        '</colgroup>' +
                        '<thead>' +
                        '<tr style="background:#dde3ec;font-size:10px;font-weight:700;text-align:center">' +
                        '<th rowspan="2" style="border:1px solid #000;padding:2px 1px;vertical-align:middle;text-align:center">ITEM CODE</th>' +
                        '<th rowspan="2" style="border:1px solid #000;padding:2px 1px;vertical-align:middle;text-align:center">PART NO#</th>' +
                        '<th rowspan="2" style="border:1px solid #000;padding:2px 1px;vertical-align:middle;text-align:center">APPLICATION</th>' +
                        '<th rowspan="2" style="border:1px solid #000;padding:2px 1px;vertical-align:middle;text-align:left">PRICE</th>' +
                        '<th rowspan="2" style="border:1px solid #000;padding:2px 1px;vertical-align:middle;text-align:left">DATE/SUPPLIER</th>' +
                        '<th rowspan="2" style="border:1px solid #000;padding:2px 1px;vertical-align:middle;text-align:center">INV</th>' +
                        '<th colspan="3" style="border:1px solid #000;padding:2px 1px;text-align:center">SALES</th>' +
                        '</tr>' +
                        '<tr style="background:#eef1f6;font-size:10px;font-weight:700;text-align:center">' +
                        '<th style="border:1px solid #000;padding:2px 1px;text-align:center">' + y1s + '</th>' +
                        '<th style="border:1px solid #000;padding:2px 1px;text-align:center">' + y2s + '</th>' +
                        '<th style="border:1px solid #000;padding:2px 1px;text-align:center">' + y3s + '</th>' +
                        '</tr>' +
                        '</thead><tbody>';
                    for (var j = 0; j < groupItems.length; j++) {
                        var item = groupItems[j];
                        html += '<tr>' +
                            '<td style="border:1px solid #000;padding:2px 2px;white-space:normal;overflow-wrap:anywhere;word-break:break-word;font-weight:700;vertical-align:top">' + (item.product_code || '---') + '</td>' +
                            '<td style="border:1px solid #000;padding:2px 2px;word-break:break-word;overflow-wrap:anywhere;white-space:normal;vertical-align:top">' + (item.part_number || '---') + '</td>' +
                            '<td style="border:1px solid #000;padding:2px 4px;font-size:11px;white-space:normal;word-break:break-word;overflow-wrap:anywhere;vertical-align:top">' + (item.application || '---') + '</td>' +
                            '<td style="border:1px solid #000;padding:2px 2px;text-align:left;font-size:10px;line-height:1.25;white-space:normal;vertical-align:top">' +
                                '<div>' + crFormatCurrency(item.local_price) + '</div>' +
                                '<div style="color:#008000;font-weight:900">' + crFormatCurrency(item.online_price) + '</div>' +
                            '</td>' +
                            '<td style="border:1px solid #000;padding:2px 2px;text-align:left;font-size:10px;line-height:1.25;white-space:normal;overflow-wrap:anywhere;vertical-align:top">' +
                                '<div>' + (item.cost_date || '---') + '</div>' +
                                '<div title="' + crEscapeHtml(item.cost_supplier || '') + '" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + crEscapeHtml(crDisplaySupplierName(item.cost_supplier)) + '</div>' +
                                '<div style="font-weight:900">COST: ' + crFormatCurrency(item.supplier_cost) + '</div>' +
                            '</td>' +
                            '<td style="border:1px solid #000;padding:0 1px;text-align:right;font-weight:700;overflow:hidden;white-space:nowrap;text-overflow:ellipsis">' + (item.on_hand || 0) + '</td>' +
                            '<td style="border:1px solid #000;padding:0 1px;text-align:right;overflow:hidden;white-space:nowrap;text-overflow:ellipsis">' + (item.sales_1 || 0) + '</td>' +
                            '<td style="border:1px solid #000;padding:0 1px;text-align:right;overflow:hidden;white-space:nowrap;text-overflow:ellipsis">' + (item.sales_2 || 0) + '</td>' +
                            '<td style="border:1px solid #000;padding:0 1px;text-align:right;overflow:hidden;white-space:nowrap;text-overflow:ellipsis">' + (item.sales_3 || 0) + '</td>' +
                            '</tr>';
                    }
                    html += '</tbody></table>';
                }
                return html;
            }

            function crDoPrint(items) {
                var printHtml = items ? crRenderPrintHtml(items) : document.getElementById('cr-print-container').innerHTML;
                var genLabel = (document.getElementById('cr-print-generated-label') || {}).textContent || '';
                if (!genLabel) {
                    var crNow = new Date();
                    genLabel = 'Generated: ' + crNow.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) + ' ' + crNow.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                }
                var win = window.open('', '_blank');
                if (!win) {
                    alert('Pop-up blocked! Please allow pop-ups for this site and try again.');
                    return;
                }
                win.document.write('<!DOCTYPE html><html><head><style>' +
                    'body { font-family: "Courier New", monospace; margin: 0; padding: 0; }' +
                    'table { width: 100%; border-collapse: collapse; table-layout: fixed; }' +
                    'th, td { border: 1px solid #000; padding: 1px 2px; }' +
                    'tr { page-break-inside: avoid; }' +
                    'th { font-size: 12px; font-weight: 900; background: #eee; text-align: center; }' +
                    'td { font-size: 12px; font-weight: 700; vertical-align: middle; }' +
                    '.cr-desc-head { color: #cc0000; font-size: 14px; font-weight: 900; font-family: "Gill Sans Ultra Bold","Gill Sans MT",sans-serif; margin: 8px 0 2px 0; page-break-after: avoid; }' +
                    '.hdr { text-align: center; font-size: 16px; font-weight: 900; margin: 0 0 8px 0; }' +
                    '.meta { text-align: center; font-size: 12px; font-weight: 700; margin: 0 0 8px 0; }' +
                    '@page { size: portrait; margin: 1cm 0.4cm; }' +
                    '</style></head><body>' +
                    '<div class="hdr">W68 AUTOPARTS &amp; SERVICE CENTER</div>' +
                    '<div class="meta"><span>' + genLabel + '</span></div>' +
                    printHtml +
                    '</body></html>');
                win.document.close();
                win.focus();
                setTimeout(function() { win.print(); }, 250);
            }

            function crPrintAll() {
                var tbody = document.getElementById('cr-report-tbody');

                // If Generate already loaded every row for the current filters (for example,
                // one exact Product Code), print that in-memory result immediately. This avoids
                // running the expensive supplier-cost + Sales History calculation a second time.
                var currentSignature = crCurrentFilterSignature();
                if (
                    crLastGeneratedSignature === currentSignature
                    && crTotalItems === crData.length
                    && crTotalItems > 0
                ) {
                    crDoPrint(crData);
                    return;
                }

                tbody.innerHTML = '<tr><td colspan="9" class="py-10 text-center text-slate-400 text-sm font-medium">Loading all data for print...</td></tr>';

                var params = new URLSearchParams();
                params.set('all', '1');
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

                fetch('<?php echo e(route("special.cost-report.data")); ?>?' + params.toString())
                    .then(function (r) { return r.text().then(function (t) { return JSON.parse(t); }); })
                    .then(function (res) {
                        if (!res.success || !res.items) {
                            tbody.innerHTML = '<tr><td colspan="9" class="py-10 text-center text-slate-400 text-sm">' + (res.error || 'Failed to load.') + '</td></tr>';
                            return;
                        }
                        crRenderTable(res.items);
                        var info = document.getElementById('cr-report-info');
                        if (res.stats) info.textContent = 'Items: ' + (res.stats.total_items || 0);
                        crDoPrint(res.items);
                    })
                    .catch(function () {
                        tbody.innerHTML = '<tr><td colspan="9" class="py-10 text-center text-slate-400 text-sm">Error loading data.</td></tr>';
                    });
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
                        query = query || '';
                        try {
                            var url = new URL(endpoint, window.location.origin);
                            if (query.trim() !== '') {
                                url.searchParams.set('query', query.trim());
                            }
                            fetch(url.toString())
                                .then(function (r) { return r.json(); })
                                .then(function (data) {
                                    renderOptions(Array.isArray(data) ? data : []);
                                })
                                .catch(function () {
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

            document.addEventListener('DOMContentLoaded', function () {
                populateYearSelects();
                initCrDropdowns();
                document.getElementById('cr-print-btn')?.addEventListener('click', crPrintAll);
                if (window.lucide?.createIcons) window.lucide.createIcons();
            });
        })();
    </script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('partials.special_user.special_sidebar_navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php /**PATH C:\xampp\htdocs\hatdog\resources\views\Special_User\Reports\Cost-Report.blade.php ENDPATH**/ ?>