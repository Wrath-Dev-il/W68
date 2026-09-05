(function () {
    const $ = (id) => document.getElementById(id);
    const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
    const num  = new Intl.NumberFormat('en-PH');

    let reportData = [];

    window.addEventListener('DOMContentLoaded', () => {
        const routes = window.inventoryReportsRoutes || {
            searchDescriptions: '/api/products/search-descriptions',
            searchCategories:   '/api/products/search-categories',
            reportData:         '/api/reports/inventory-data'
        };

        // Both fields now have search + dropdown toggle
        initAutocomplete('rep-description', 'rep-description-suggestions', routes.searchDescriptions, true);
        initAutocomplete('rep-category',    'rep-category-suggestions',    routes.searchCategories,   true);

        // Print button
        const printBtn = $('rep-print-btn');
        if (printBtn) printBtn.addEventListener('click', printReport);

        // Clear button
        const clearBtn = $('rep-clear-btn');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                ['rep-description', 'rep-category', 'rep-date-from', 'rep-date-to', 'rep-date-asof'].forEach(id => {
                    const el = $(id);
                    if (el) el.value = '';
                });
                ['rep-description-suggestions', 'rep-category-suggestions'].forEach(id => {
                    const el = $(id);
                    if (el) { el.innerHTML = ''; el.classList.add('hidden'); }
                });
                const typeEl = $('rep-type');
                if (typeEl) typeEl.selectedIndex = 0;
            });
        }
    });

    // ─── Autocomplete Widget ───────────────────────────────────────────────────
    function initAutocomplete(inputId, suggestionsId, endpointUrl, hasToggle) {
        const input         = $(inputId);
        const suggestionsBox = $(suggestionsId);
        if (!input || !suggestionsBox) return;

        let activeIndex  = -1;
        let debounceTimer;

        // Typing search
        input.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            const query = input.value.trim();
            if (query.length < 1) {
                suggestionsBox.innerHTML = '';
                suggestionsBox.classList.add('hidden');
                return;
            }
            debounceTimer = setTimeout(() => fetchSuggestions(query, false), 200);
        });

        // Dropdown toggle (browse all)
        if (hasToggle) {
            const toggleBtn = $(`${inputId}-toggle`);
            if (toggleBtn) {
                toggleBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    if (!suggestionsBox.classList.contains('hidden')) {
                        closeSuggestions();
                        return;
                    }
                    fetchSuggestions(input.value.trim(), true);
                });
            }
        }

        function fetchSuggestions(query, browseAll) {
            const url = browseAll
                ? `${endpointUrl}?query=${encodeURIComponent(query)}&browse=1`
                : `${endpointUrl}?query=${encodeURIComponent(query)}`;
            fetch(url)
                .then(r => r.json())
                .then(renderSuggestions)
                .catch(err => console.error('Error fetching suggestions:', err));
        }

        // Keyboard navigation
        input.addEventListener('keydown', (e) => {
            const items = suggestionsBox.querySelectorAll('.autocomplete-suggestion');
            if (!items.length) return;
            if (e.key === 'ArrowDown')  { e.preventDefault(); activeIndex = (activeIndex + 1) % items.length; highlightItem(items); }
            else if (e.key === 'ArrowUp')   { e.preventDefault(); activeIndex = (activeIndex - 1 + items.length) % items.length; highlightItem(items); }
            else if (e.key === 'Enter') { e.preventDefault(); if (activeIndex > -1) selectItem(items[activeIndex].textContent); }
            else if (e.key === 'Escape') { closeSuggestions(); }
        });

        function renderSuggestions(suggestions) {
            suggestionsBox.innerHTML = '';
            activeIndex = -1;
            if (!suggestions.length) {
                const div = document.createElement('div');
                div.className = 'autocomplete-suggestion text-slate-400 italic cursor-default';
                div.textContent = 'No results found';
                suggestionsBox.appendChild(div);
                suggestionsBox.classList.remove('hidden');
                return;
            }
            suggestions.forEach(item => {
                const div = document.createElement('div');
                div.className = 'autocomplete-suggestion';
                div.textContent = item;
                div.addEventListener('click', () => selectItem(item));
                suggestionsBox.appendChild(div);
            });
            suggestionsBox.classList.remove('hidden');
        }

        function highlightItem(items) {
            items.forEach((item, idx) => {
                item.classList.toggle('active', idx === activeIndex);
                if (idx === activeIndex) item.scrollIntoView({ block: 'nearest' });
            });
        }

        function selectItem(value) { input.value = value; closeSuggestions(); }

        function closeSuggestions() {
            suggestionsBox.innerHTML = '';
            suggestionsBox.classList.add('hidden');
            activeIndex = -1;
        }

        document.addEventListener('click', (e) => {
            if (e.target !== input && !suggestionsBox.contains(e.target)) closeSuggestions();
        });
    }

    // ─── Print Report ──────────────────────────────────────────────────────────
    function printReport() {
        const printBtn  = $('rep-print-btn');
        const printArea = $('print-report-area');
        if (!printBtn || !printArea) return;

        const reportTypeEl  = $('rep-type');
        const descriptionEl = $('rep-description');
        const categoryEl    = $('rep-category');
        const yearFromEl    = $('rep-date-from');
        const yearToEl      = $('rep-date-to');
        const yearAsofEl    = $('rep-date-asof');

        const reportType  = reportTypeEl  ? reportTypeEl.value         : 'product_on_hand';
        const description = descriptionEl ? descriptionEl.value.trim() : '';
        const category    = categoryEl    ? categoryEl.value.trim()    : '';
        const yearFrom    = yearFromEl    ? yearFromEl.value.trim()    : '';
        const yearTo      = yearToEl      ? yearToEl.value.trim()      : '';
        const yearAsof    = yearAsofEl    ? yearAsofEl.value.trim()    : '';

        // Disable button & show loading
        const originalContent = printBtn.innerHTML;
        printBtn.disabled = true;
        printBtn.innerHTML = `<div class="inline-block animate-spin rounded-full h-3.5 w-3.5 border-b-2 border-white mr-2 align-middle"></div> Generating...`;

        const routes = window.inventoryReportsRoutes || { reportData: '/api/reports/inventory-data' };

        const params = new URLSearchParams({ report_type: reportType, description, category, year_from: yearFrom, year_to: yearTo, year_asof: yearAsof });

        fetch(`${routes.reportData}?${params.toString()}`)
            .then(res => res.json())
            .then(data => {
                reportData = data.products || [];
                const salesYears     = data.sales_years || [];
                const reportTypeText = reportTypeEl ? reportTypeEl.options[reportTypeEl.selectedIndex].text : 'Inventory Report';
                const now            = new Date();
                const generatedDate  = now.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });

                if (reportType === 'reorder_report') {
                    buildReorderPrintLayout(printArea, reportData);
                } else {
                    buildPrintLayout(printArea, reportData, salesYears, reportTypeText, generatedDate, {
                        description, category, yearFrom, yearTo, yearAsof
                    });
                }

                // CRITICAL: un-hide so print CSS can render it
                printArea.classList.remove('hidden');

                printBtn.disabled = false;
                printBtn.innerHTML = originalContent;

                // Re-hide after print dialog closes
                const onAfterPrint = () => {
                    printArea.classList.add('hidden');
                    window.removeEventListener('afterprint', onAfterPrint);
                };
                window.addEventListener('afterprint', onAfterPrint);

                window.print();
            })
            .catch(err => {
                console.error('Error generating report:', err);
                printBtn.disabled = false;
                printBtn.innerHTML = originalContent;
                alert('Failed to generate report. Please try again.');
            });
    }

    // ─── Build Printable HTML — pure black, bold, bordered cells ──────────────
    function buildReorderPrintLayout(printArea, products) {
        const CELL_BORDER = 'border:1px solid #000000;';
        const BASE_FONT   = 'font-size:12px;font-weight:bold;color:#000000;';

        const thStyle  = (extra = '') => `style="${CELL_BORDER}padding:7px 9px;${BASE_FONT}${extra}"`;
        const tdStyle  = (extra = '') => `style="${CELL_BORDER}padding:6px 9px;${BASE_FONT}${extra}"`;

        let tableRows = '';
        products.forEach((p, idx) => {
            const onHand = parseInt(p.on_hand ?? 0) || 0;
            const reorderLevel = parseInt(p.Re_order_level ?? 0) || 0;
            const reorderQty = Math.max(0, reorderLevel - onHand);
            const rowBg = idx % 2 !== 0 ? 'background-color:#f5f5f5;' : 'background-color:#ffffff;';

            tableRows += `
                <tr style="${rowBg}">
                    <td ${tdStyle('font-family:monospace;white-space:nowrap;')}>${p.product_code || '—'}</td>
                    <td ${tdStyle('')}>${p.description || '—'}</td>
                    <td ${tdStyle('text-align:right;font-family:monospace;')}>${num.format(onHand)}</td>
                    <td ${tdStyle('text-align:right;font-family:monospace;')}>${num.format(reorderQty)}</td>
                </tr>`;
        });

        if (!products.length) {
            tableRows = `<tr><td colspan="4" ${tdStyle('text-align:center;border:1px solid #000;')}>No low stock products found.</td></tr>`;
        }

        printArea.innerHTML = `
<div style="font-family:'Arial',sans-serif;padding:20px 28px;color:#000000;">
    <div style="text-align:center;margin-bottom:14px;">
        <h1 style="font-size:20px;font-weight:900;color:#000000;margin:0;text-transform:uppercase;letter-spacing:0.06em;">
            W68 AUTOPARTS &amp; SERVICE CENTER
        </h1>
    </div>

    <table style="width:100%;border-collapse:collapse;text-align:left;border:2px solid #000000;">
        <thead>
            <tr>
                <th ${thStyle('white-space:nowrap;')}>PRODUCT CODE</th>
                <th ${thStyle('')}>DESCRIPTION</th>
                <th ${thStyle('text-align:right;white-space:nowrap;')}>ON HAND</th>
                <th ${thStyle('text-align:right;white-space:nowrap;')}>RE-ORDER QTY</th>
            </tr>
        </thead>
        <tbody>
            ${tableRows}
        </tbody>
    </table>
</div>`;
    }

    function buildPrintLayout(printArea, products, salesYears, reportTypeText, generatedDate, filters) {

        // Reusable inline styles — ALL TEXT IS BLACK AND BOLD
        const CELL_BORDER = 'border:1px solid #000000;';
        const BASE_FONT   = 'font-size:12px;font-weight:bold;color:#000000;';

        const thStyle  = (extra = '') => `style="${CELL_BORDER}padding:7px 9px;${BASE_FONT}${extra}"`;
        const tdStyle  = (extra = '') => `style="${CELL_BORDER}padding:6px 9px;${BASE_FONT}${extra}"`;

        // Sales year column headers
        const yearsHeaderCells = salesYears.map(y =>
            `<th ${thStyle('text-align:right;white-space:nowrap;background:#fff8c5;')}>${y}</th>`
        ).join('');

        // Table body rows
        let tableRows = '';
        products.forEach((p, idx) => {
            const descFull    = [p.description, p.application].filter(Boolean).join(' — ');
            const cost        = p.cost          ? peso.format(p.cost)          : '—';
            const price       = p.selling_price ? peso.format(p.selling_price) : '—';
            const priceOnline = p.price_online  ? peso.format(p.price_online)  : '—';
            const onHand      = p.on_hand  ?? 0;
            const actualQty   = p.actual_qty ?? '—';

            const yearSalesCells = salesYears.map(y => {
                const qty = (p.sales_by_year && p.sales_by_year[y]) ? num.format(p.sales_by_year[y]) : '0';
                return `<td ${tdStyle('text-align:right;font-family:monospace;')}>${qty}</td>`;
            }).join('');

            const rowBg = idx % 2 !== 0 ? 'background-color:#f5f5f5;' : 'background-color:#ffffff;';
            tableRows += `
                <tr style="${rowBg}">
                    <td ${tdStyle('font-family:monospace;white-space:nowrap;')}>${p.product_code || '—'}</td>
                    <td ${tdStyle('')}>${descFull || '—'}</td>
                    <td ${tdStyle('text-align:right;font-family:monospace;')}>${cost}</td>
                    <td ${tdStyle('text-align:right;font-family:monospace;')}>${price}</td>
                    <td ${tdStyle('text-align:right;font-family:monospace;')}>${priceOnline}</td>
                    <td ${tdStyle('text-align:right;font-family:monospace;')}>${num.format(onHand)}</td>
                    <td ${tdStyle('text-align:right;font-family:monospace;')}>${actualQty}</td>
                    ${yearSalesCells}
                </tr>`;
        });

        if (!products.length) {
            const colspan = 7 + salesYears.length;
            tableRows = `<tr><td colspan="${colspan}" ${tdStyle('text-align:center;border:1px solid #000;')}>No matching inventory data available.</td></tr>`;
        }

        // SALES group header spanning year columns only
        const salesGroupHeader = salesYears.length > 0 ? `
            <tr>
                <th colspan="7" style="border:none;padding:0;background:transparent;"></th>
                <th colspan="${salesYears.length}"
                    style="${CELL_BORDER}padding:5px 9px;text-align:center;background:#fff8c5;${BASE_FONT}letter-spacing:0.05em;">
                    SALES
                </th>
            </tr>` : '';

        // Filters summary
        const filterParts = [];
        if (filters.description) filterParts.push(`Description: ${filters.description}`);
        if (filters.category)    filterParts.push(`Category: ${filters.category}`);
        if (filters.yearFrom)    filterParts.push(`Year From: ${filters.yearFrom}`);
        if (filters.yearTo)      filterParts.push(`Year To: ${filters.yearTo}`);
        if (filters.yearAsof)    filterParts.push(`As Of Year: ${filters.yearAsof}`);
        const filterLine = filterParts.length
            ? filterParts.join('   |   ')
            : 'All Products — No filters applied';

        printArea.innerHTML = `
<div style="font-family:'Arial',sans-serif;padding:20px 28px;color:#000000;">

    <!-- Company Header -->
    <div style="text-align:center;border-bottom:3px solid #000000;padding-bottom:12px;margin-bottom:14px;">
        <h1 style="font-size:20px;font-weight:900;color:#000000;margin:0;text-transform:uppercase;letter-spacing:0.06em;">
            w68 AUTOPARTS &amp; SERVICE CENTER
        </h1>
        <p style="font-size:13px;font-weight:bold;color:#000000;margin:6px 0 2px 0;">${reportTypeText}</p>
        <p style="font-size:12px;font-weight:bold;color:#000000;margin:0;">
            DATE GENERATED: ${generatedDate}
        </p>
    </div>

    <!-- Filters Row -->
    <div style="border:1px solid #000000;border-radius:4px;padding:8px 14px;margin-bottom:14px;
                font-size:12px;font-weight:bold;color:#000000;">
        ${filterLine}
    </div>

    <!-- Main Table -->
    <table style="width:100%;border-collapse:collapse;text-align:left;border:2px solid #000000;">
        <thead>
            ${salesGroupHeader}
            <tr>
                <th ${thStyle('white-space:nowrap;')}>Item Code</th>
                <th ${thStyle('')}>Description</th>
                <th ${thStyle('text-align:right;')}>Cost</th>
                <th ${thStyle('text-align:right;')}>Price</th>
                <th ${thStyle('text-align:right;')}>Price Online</th>
                <th ${thStyle('text-align:right;')}>On Hand</th>
                <th ${thStyle('text-align:right;')}>Actual QTY</th>
                ${yearsHeaderCells}
            </tr>
        </thead>
        <tbody>
            ${tableRows}
        </tbody>
    </table>

    <!-- Footer -->
    <div style="margin-top:24px;border-top:2px solid #000000;padding-top:10px;
                display:flex;justify-content:space-between;font-size:12px;font-weight:bold;color:#000000;">
        <div>Total Items: ${products.length}</div>
        <div>w68 Inventory Management System</div>
    </div>
</div>`;
    }
})();
