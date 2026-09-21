(() => {
    const root = document.getElementById('unserved-report-root');
    if (!root) return;

    const routes = {
        customers: root.dataset.customersUrl,
        salesmen: root.dataset.salesmenUrl,
        data: root.dataset.dataUrl,
        productHistory: root.dataset.productHistoryUrl,
        print: root.dataset.printUrl,
    };

    const customerInput = document.getElementById('unserved-customer');
    const salesmanInput = document.getElementById('unserved-salesman');
    const stockFilter = document.getElementById('unserved-stock-filter');
    const rushFilter = document.getElementById('unserved-rush-filter');
    const statusFilter = document.getElementById('unserved-status-filter');
    const dateType = document.getElementById('unserved-date-type');
    const dateFields = document.getElementById('unserved-date-fields');
    const printBtn = document.getElementById('unserved-print-btn');
    const tbody = document.getElementById('unserved-table-body');
    const loadingLabel = document.getElementById('unserved-loading-label');
    const allUnservedCount = document.getElementById('unserved-all-count');
    const totalNotesCount = document.getElementById('unserved-total-notes-count');
    const openNotesCount = document.getElementById('unserved-open-notes-count');
    const partialNotesCount = document.getElementById('unserved-partial-notes-count');

    const withoutStockCount = document.getElementById('unserved-without-stock-count');
    const withStockCount = document.getElementById('unserved-with-stock-count');
    const withStockOpenCount = document.getElementById('unserved-with-stock-open-count');
    const withStockPartialCount = document.getElementById('unserved-with-stock-partial-count');
    const servableCount = document.getElementById('unserved-servable-count');

    const allUnservedCard = document.getElementById('unserved-all-card');
    const withoutStockCard = document.getElementById('unserved-without-stock-card');
    const withStockCard = document.getElementById('unserved-with-stock-card');
    const servableCard = document.getElementById('unserved-servable-card');
    const previewTitle = document.getElementById('unserved-preview-title');
    const toast = document.getElementById('unserved-toast');
    const historyModal = document.getElementById('unserved-history-modal');
    const historyClose = document.getElementById('unserved-history-close');
    const historyTitle = document.getElementById('unserved-history-title');
    const historyProductLabel = document.getElementById('unserved-history-product-label');
    const historyLoading = document.getElementById('unserved-history-loading');
    const historyContent = document.getElementById('unserved-history-content');
    const historyPurchaseBody = document.getElementById('unserved-history-purchase-body');
    const historySalesBody = document.getElementById('unserved-history-sales-body');

    const now = new Date();
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const d = String(now.getDate()).padStart(2, '0');
    const today = `${y}-${m}-${d}`;
    const currentMonth = `${y}-${m}`;
    let previewTimer = null;
    let previewAbort = null;
    let latestRows = [];
    let activeSummaryMode = null;
    // W68_UNSERVED_ALL_USERS_PARTIAL_FIX_20260918
    // W68_UNSERVED_OPEN_PARTIAL_STATUS_FIX_V2_20260921
    // W68_UNSERVED_STOCK_STATUS_FILTER_V2_20260921

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
    const fmtQty = value => Number(value || 0).toLocaleString(undefined, {maximumFractionDigits: 2});
    const fmtMoney = value => Number(value || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});

    function showToast(message) {
        const span = toast.querySelector('span');
        span.textContent = message;
        toast.classList.remove('hidden');
        if (window.lucide) window.lucide.createIcons();
        setTimeout(() => toast.classList.add('hidden'), 3500);
    }

    function renderDateFields() {
        const type = dateType.value;
        const quarter = Math.floor(now.getMonth() / 3) + 1;
        const half = now.getMonth() < 6 ? 'H1' : 'H2';
        let html = '';

        if (type === 'annual') {
            html = `<div class="unserved-date-field"><label>Year</label><input data-period-field="year" type="number" min="2000" max="2100" value="${y}"></div>`;
        } else if (type === 'monthly') {
            html = `<div class="unserved-date-field"><label>Month</label><input data-period-field="month" type="month" value="${currentMonth}"></div>`;
        } else if (type === 'quarterly') {
            html = `<div class="unserved-date-field"><label>Year</label><input data-period-field="year" type="number" min="2000" max="2100" value="${y}"></div>
                    <div class="unserved-date-field"><label>Quarter</label><select data-period-field="quarter"><option value="Q1" ${quarter===1?'selected':''}>1st Quarter</option><option value="Q2" ${quarter===2?'selected':''}>2nd Quarter</option><option value="Q3" ${quarter===3?'selected':''}>3rd Quarter</option><option value="Q4" ${quarter===4?'selected':''}>4th Quarter</option></select></div>`;
        } else if (type === 'half-year') {
            html = `<div class="unserved-date-field"><label>Year</label><input data-period-field="year" type="number" min="2000" max="2100" value="${y}"></div>
                    <div class="unserved-date-field"><label>Half Year</label><select data-period-field="half"><option value="H1" ${half==='H1'?'selected':''}>First Half (Jan - Jun)</option><option value="H2" ${half==='H2'?'selected':''}>Second Half (Jul - Dec)</option></select></div>`;
        } else if (type === 'as-of') {
            html = `<div class="unserved-date-field"><label>As Of</label><input data-period-field="as_of" type="date" value="${today}"></div>`;
        } else if (type === 'from-to') {
            html = `<div class="unserved-date-field"><label>From</label><input data-period-field="date_from" type="date" value="${y}-${m}-01"></div>
                    <div class="unserved-date-field"><label>To</label><input data-period-field="date_to" type="date" value="${today}"></div>`;
        }

        dateFields.innerHTML = html;
        dateFields.querySelectorAll('input,select').forEach(el => el.addEventListener('change', schedulePreview));
        schedulePreview();
    }

    function collectParams() {
        const params = new URLSearchParams();
        params.set('customer', customerInput.value.trim());
        params.set('salesman', salesmanInput.value.trim());
        params.set('stock_filter', stockFilter.value || 'all');
        params.set('rush_filter', rushFilter.value || 'all');
        params.set('status_filter', statusFilter?.value || 'all');
        params.set('date_type', dateType.value);
        dateFields.querySelectorAll('[data-period-field]').forEach(field => params.set(field.dataset.periodField, field.value));
        return params;
    }

    function searchableValue(row, key) {
        const raw = row?.[key] ?? '';
        if (key === 'so_no') return `${raw} ${row?.status ?? ''}`.toLowerCase();
        if (['on_hand', 'served', 'unserved'].includes(key)) return `${raw} ${fmtQty(raw)}`.toLowerCase();
        if (['unit_price', 'total_amount'].includes(key)) return `${raw} ${fmtMoney(raw)}`.toLowerCase();
        return String(raw).toLowerCase();
    }

    function summaryModeRows() {
        if (activeSummaryMode === 'without-stock') {
            return latestRows.filter(
                row => Math.abs(Number(row.on_hand || 0)) < 0.000001
            );
        }

        if (activeSummaryMode === 'with-stock') {
            return latestRows.filter(
                row => Number(row.on_hand || 0) > 0
            );
        }

        if (activeSummaryMode === 'servable') {
            return latestRows.filter(row =>
                Math.abs(
                    Number(row.on_hand || 0)
                    - Number(row.unserved || 0)
                ) < 0.000001
            );
        }

        return latestRows;
    }

    function visibleRows() {
        const baseRows = summaryModeRows();

        const searches = Array.from(
            document.querySelectorAll('[data-column-search]')
        )
            .map(input => ({
                key: input.dataset.columnSearch,
                value: input.value.trim().toLowerCase()
            }))
            .filter(entry => entry.value !== '');

        if (!searches.length) {
            return baseRows;
        }

        return baseRows.filter(row =>
            searches.every(entry =>
                searchableValue(row, entry.key).includes(entry.value)
            )
        );
    }

    function renderRows() {
        const rows = visibleRows();
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="11" class="unserved-empty">No unserved items found for the selected filters/searches.</td></tr>';
        } else {
            tbody.innerHTML = rows.map(row => {
                const status = String(row.status || '').trim();
                const normalizedStatus = status.toLowerCase();
                const statusClass = normalizedStatus === 'open' ? 'is-open' : 'is-partial';
                const statusTag = status
                    ? `<span class="unserved-status-tag ${statusClass}">${escapeHtml(status)}</span>`
                    : '';

                return `<tr class="${row.is_rush ? 'unserved-rush-row' : ''} unserved-history-row" data-product-id="${Number(row.product_id || 0)}" tabindex="0" title="Click to view latest product history">
                    <td>
                        <div class="unserved-so-cell">
                            <span class="unserved-so-number">${escapeHtml(row.so_no)}</span>
                            ${statusTag}
                        </div>
                    </td>
                    <td>${escapeHtml(row.customer)}</td>
                    <td>${escapeHtml(row.order_date)}</td>
                    <td>${escapeHtml(row.product_code)}</td>
                    <td>${escapeHtml(row.part_number)}</td>
                    <td>${escapeHtml(row.description)}</td>
                    <td class="num">${fmtQty(row.on_hand)}</td>
                    <td class="num">${fmtQty(row.served)}</td>
                    <td class="num unserved-value">${fmtQty(row.unserved)}</td>
                    <td class="num">${fmtMoney(row.unit_price)}</td>
                    <td class="num">${fmtMoney(row.total_amount)}</td>
                </tr>`;
            }).join('');
        }
        loadingLabel.textContent = rows.length === latestRows.length
            ? `${rows.length.toLocaleString()} line${rows.length === 1 ? '' : 's'}`
            : `${rows.length.toLocaleString()} of ${latestRows.length.toLocaleString()} lines`;
    }

    async function loadPreview() {
        if (previewAbort) previewAbort.abort();
        previewAbort = new AbortController();
        loadingLabel.textContent = 'Loading…';
        tbody.innerHTML = '<tr><td colspan="11" class="unserved-empty">Loading Open / Partial unserved details…</td></tr>';

        try {
            const response = await fetch(`${routes.data}?${collectParams().toString()}`, {
                headers: {'Accept':'application/json'},
                signal: previewAbort.signal,
            });
            const payload = await response.json().catch(() => null);
            if (!response.ok || !payload?.success) throw new Error(payload?.message || `Unable to load report (${response.status}).`);

            allUnservedCount.textContent =
                Number(payload.summary?.all_unserved_items || 0).toLocaleString();

            totalNotesCount.textContent =
                Number(payload.summary?.all_unserved_total_notes || 0).toLocaleString();

            openNotesCount.textContent =
                Number(payload.summary?.all_unserved_open_notes || 0).toLocaleString();

            partialNotesCount.textContent =
                Number(payload.summary?.all_unserved_partial_notes || 0).toLocaleString();

            withoutStockCount.textContent =
                Number(payload.summary?.unserved_without_stock_lines || 0).toLocaleString();

            withStockCount.textContent =
                Number(payload.summary?.unserved_with_stock_lines || 0).toLocaleString();

            withStockOpenCount.textContent =
                Number(payload.summary?.unserved_with_stock_open_lines || 0).toLocaleString();

            withStockPartialCount.textContent =
                Number(payload.summary?.unserved_with_stock_partial_lines || 0).toLocaleString();

            servableCount.textContent =
                Number(payload.summary?.servable_item_lines || 0).toLocaleString();

            latestRows = Array.isArray(payload.rows) ? payload.rows : [];
            renderRows();
        } catch (error) {
            if (error.name === 'AbortError') return;
            latestRows = [];
            loadingLabel.textContent = 'Error';
            tbody.innerHTML = `<tr><td colspan="11" class="unserved-empty">${escapeHtml(error.message)}</td></tr>`;
            showToast(error.message);
        }
    }

    function closeProductHistory() {
        if (!historyModal) return;
        historyModal.classList.add('hidden');
        historyModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('unserved-modal-open');
    }

    function historyEmpty(colspan, message) {
        return `<tr><td colspan="${colspan}" class="unserved-history-empty">${escapeHtml(message)}</td></tr>`;
    }

    function renderPurchaseHistory(rows) {
        if (!historyPurchaseBody) return;
        if (!Array.isArray(rows) || !rows.length) {
            historyPurchaseBody.innerHTML = historyEmpty(6, 'No purchase history found for this product.');
            return;
        }

        historyPurchaseBody.innerHTML = rows.map(row => `<tr>
            <td>${escapeHtml(row.date)}</td>
            <td>${escapeHtml(row.po_no || '-')}</td>
            <td>${escapeHtml(row.supplier_invoice || '-')}</td>
            <td>${escapeHtml(row.supplier_name || '-')}</td>
            <td class="num">${fmtQty(row.qty)}</td>
            <td class="num">${fmtMoney(row.unit_cost)}</td>
        </tr>`).join('');
    }

    function renderSalesHistory(rows) {
        if (!historySalesBody) return;
        if (!Array.isArray(rows) || !rows.length) {
            historySalesBody.innerHTML = historyEmpty(5, 'No qualifying OUT history found for this product.');
            return;
        }

        historySalesBody.innerHTML = rows.map(row => `<tr>
            <td>${escapeHtml(row.date)}</td>
            <td>${escapeHtml(row.sales_invoice || '-')}</td>
            <td>${escapeHtml(row.customer_name || '-')}</td>
            <td class="num">${fmtQty(row.qty)}</td>
            <td class="num">${fmtMoney(row.unit_price)}</td>
        </tr>`).join('');
    }

    async function openProductHistory(productId) {
        const id = Number(productId || 0);
        if (!id || !routes.productHistory || !historyModal) return;

        historyModal.classList.remove('hidden');
        historyModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('unserved-modal-open');
        historyTitle.textContent = 'Product History';
        historyProductLabel.textContent = 'Loading product...';
        historyLoading.classList.remove('hidden');
        historyContent.classList.add('is-loading');
        historyPurchaseBody.innerHTML = historyEmpty(6, 'Loading latest purchase history...');
        historySalesBody.innerHTML = historyEmpty(5, 'Loading latest sales history...');

        try {
            const response = await fetch(`${routes.productHistory}?product_id=${encodeURIComponent(id)}`, {
                headers: {'Accept':'application/json'},
            });
            const payload = await response.json().catch(() => null);
            if (!response.ok || !payload?.success) {
                throw new Error(payload?.message || `Unable to load product history (${response.status}).`);
            }

            const product = payload.product || {};
            historyTitle.textContent = product.product_code || 'Product History';
            historyProductLabel.textContent = [product.part_number, product.description].filter(Boolean).join(' / ') || '-';
            renderPurchaseHistory(payload.purchase_history);
            renderSalesHistory(payload.sales_history);
        } catch (error) {
            historyPurchaseBody.innerHTML = historyEmpty(6, error.message);
            historySalesBody.innerHTML = historyEmpty(5, error.message);
            showToast(error.message);
        } finally {
            historyLoading.classList.add('hidden');
            historyContent.classList.remove('is-loading');
            if (window.lucide) window.lucide.createIcons();
        }
    }

    function schedulePreview() {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(loadPreview, 350);
    }

    function updatePreviewTitle() {
        if (!previewTitle) return;

        const titles = {
            all: 'All Unserved Items',
            'without-stock': 'Unserved Items Without Stocks',
            'with-stock': 'Unserved Items With Stocks',
            servable: 'Servable Items',
        };

        previewTitle.textContent =
            titles[activeSummaryMode] || 'Unserved Details';

        [
            allUnservedCard,
            withoutStockCard,
            withStockCard,
            servableCard
        ].forEach(card => card?.classList.remove('is-active'));

        const activeCard = {
            all: allUnservedCard,
            'without-stock': withoutStockCard,
            'with-stock': withStockCard,
            servable: servableCard,
        }[activeSummaryMode];

        activeCard?.classList.add('is-active');
    }

    function showSummaryMode(mode) {
        activeSummaryMode = mode;

        if (statusFilter) {
            statusFilter.value = 'all';
        }

        if (mode === 'with-stock') {
            stockFilter.value = 'with';
        } else if (mode === 'without-stock') {
            stockFilter.value = 'without';
        } else {
            stockFilter.value = 'all';
        }

        updatePreviewTitle();
        schedulePreview();
    }

    function bindSummaryCard(card, mode) {
        if (!card) return;

        card.addEventListener(
            'click',
            () => showSummaryMode(mode)
        );

        card.addEventListener('keydown', event => {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            event.preventDefault();
            showSummaryMode(mode);
        });
    }

    function setupCombobox(container, input, endpoint) {
        const menu = container.querySelector('.unserved-combo-menu');
        const toggle = container.querySelector('.unserved-combo-toggle');
        let timer = null;
        let requestController = null;

        async function loadOptions(forceOpen = true) {
            if (requestController) requestController.abort();
            requestController = new AbortController();
            const q = input.value.trim();
            menu.classList.remove('hidden');
            menu.innerHTML = '<div class="unserved-combo-empty">Searching…</div>';
            try {
                const response = await fetch(`${endpoint}?q=${encodeURIComponent(q)}`, {headers:{'Accept':'application/json'}, signal:requestController.signal});
                const payload = await response.json();
                const items = Array.isArray(payload.items) ? payload.items : [];
                menu.innerHTML = items.length
                    ? `<button type="button" class="unserved-combo-option" data-value="">All</button>` + items.map(item => `<button type="button" class="unserved-combo-option" data-value="${escapeHtml(item.value)}">${escapeHtml(item.label)}</button>`).join('')
                    : '<div class="unserved-combo-empty">No matches found.</div>';
                if (forceOpen) menu.classList.remove('hidden');
            } catch (error) {
                if (error.name !== 'AbortError') menu.innerHTML = '<div class="unserved-combo-empty">Unable to load options.</div>';
            }
        }

        input.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => { loadOptions(); schedulePreview(); }, 250);
        });
        input.addEventListener('focus', () => loadOptions());
        input.addEventListener('change', schedulePreview);
        toggle.addEventListener('click', () => menu.classList.contains('hidden') ? loadOptions() : menu.classList.add('hidden'));
        menu.addEventListener('click', event => {
            const option = event.target.closest('.unserved-combo-option');
            if (!option) return;
            input.value = option.dataset.value || '';
            menu.classList.add('hidden');
            schedulePreview();
        });
        document.addEventListener('click', event => {
            if (!container.contains(event.target)) menu.classList.add('hidden');
        });
    }

    setupCombobox(root.querySelector('[data-combobox="customer"]'), customerInput, routes.customers);
    setupCombobox(root.querySelector('[data-combobox="salesman"]'), salesmanInput, routes.salesmen);

    dateType.addEventListener('change', renderDateFields);

    stockFilter.addEventListener('change', () => {
        activeSummaryMode = null;
        updatePreviewTitle();
        schedulePreview();
    });

    rushFilter.addEventListener('change', schedulePreview);

    statusFilter?.addEventListener('change', () => {
        activeSummaryMode = null;
        updatePreviewTitle();
        schedulePreview();
    });

    document.querySelectorAll('[data-column-search]').forEach(
        input => input.addEventListener('input', renderRows)
    );

    bindSummaryCard(allUnservedCard, 'all');
    bindSummaryCard(withoutStockCard, 'without-stock');
    bindSummaryCard(withStockCard, 'with-stock');
    bindSummaryCard(servableCard, 'servable');

    printBtn.addEventListener('click', () => {
        const url = `${routes.print}?${collectParams().toString()}`;
        window.open(url, '_blank', 'noopener');
    });


    tbody.addEventListener('click', event => {
        const row = event.target.closest('tr[data-product-id]');
        if (!row) return;
        openProductHistory(row.dataset.productId);
    });
    tbody.addEventListener('keydown', event => {
        if (event.key !== 'Enter' && event.key !== ' ') return;
        const row = event.target.closest('tr[data-product-id]');
        if (!row) return;
        event.preventDefault();
        openProductHistory(row.dataset.productId);
    });
    historyClose?.addEventListener('click', closeProductHistory);
    historyModal?.querySelector('[data-history-close]')?.addEventListener('click', closeProductHistory);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && historyModal && !historyModal.classList.contains('hidden')) {
            closeProductHistory();
        }
    });
    renderDateFields();
    if (window.lucide) window.lucide.createIcons();
})();
