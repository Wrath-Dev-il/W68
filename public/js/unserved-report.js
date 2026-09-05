(() => {
    const root = document.getElementById('unserved-report-root');
    if (!root) return;

    const routes = {
        customers: root.dataset.customersUrl,
        salesmen: root.dataset.salesmenUrl,
        data: root.dataset.dataUrl,
        print: root.dataset.printUrl,
    };

    const customerInput = document.getElementById('unserved-customer');
    const salesmanInput = document.getElementById('unserved-salesman');
    const dateType = document.getElementById('unserved-date-type');
    const dateFields = document.getElementById('unserved-date-fields');
    const printBtn = document.getElementById('unserved-print-btn');
    const tbody = document.getElementById('unserved-table-body');
    const loadingLabel = document.getElementById('unserved-loading-label');
    const noteCount = document.getElementById('unserved-note-count');
    const lineCount = document.getElementById('unserved-line-count');
    const qtyCount = document.getElementById('unserved-qty-count');
    const periodLabel = document.getElementById('unserved-period-label');
    const toast = document.getElementById('unserved-toast');

    const now = new Date();
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const d = String(now.getDate()).padStart(2, '0');
    const today = `${y}-${m}-${d}`;
    const currentMonth = `${y}-${m}`;
    let previewTimer = null;
    let previewAbort = null;

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
        params.set('date_type', dateType.value);
        dateFields.querySelectorAll('[data-period-field]').forEach(field => params.set(field.dataset.periodField, field.value));
        return params;
    }

    async function loadPreview() {
        if (previewAbort) previewAbort.abort();
        previewAbort = new AbortController();
        loadingLabel.textContent = 'Loading…';
        tbody.innerHTML = '<tr><td colspan="9" class="unserved-empty">Loading unserved details…</td></tr>';

        try {
            const response = await fetch(`${routes.data}?${collectParams().toString()}`, {
                headers: {'Accept':'application/json'},
                signal: previewAbort.signal,
            });
            const payload = await response.json().catch(() => null);
            if (!response.ok || !payload?.success) throw new Error(payload?.message || `Unable to load report (${response.status}).`);

            noteCount.textContent = Number(payload.summary?.open_partial_notes || 0).toLocaleString();
            lineCount.textContent = Number(payload.summary?.line_items || 0).toLocaleString();
            qtyCount.textContent = fmtQty(payload.summary?.total_unserved || 0);
            periodLabel.textContent = payload.period_label || '—';

            const rows = Array.isArray(payload.rows) ? payload.rows : [];
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="9" class="unserved-empty">No unserved items found for the selected filters.</td></tr>';
            } else {
                tbody.innerHTML = rows.map(row => `<tr>
                    <td>${escapeHtml(row.so_no)}</td>
                    <td>${escapeHtml(row.product_code)}</td>
                    <td>${escapeHtml(row.part_number)}</td>
                    <td>${escapeHtml(row.description)}</td>
                    <td class="num">${fmtQty(row.on_hand)}</td>
                    <td class="num">${fmtQty(row.served)}</td>
                    <td class="num unserved-value">${fmtQty(row.unserved)}</td>
                    <td class="num">${fmtMoney(row.unit_price)}</td>
                    <td class="num">${fmtMoney(row.total_amount)}</td>
                </tr>`).join('');
            }
            loadingLabel.textContent = `${rows.length.toLocaleString()} line${rows.length === 1 ? '' : 's'}`;
        } catch (error) {
            if (error.name === 'AbortError') return;
            loadingLabel.textContent = 'Error';
            tbody.innerHTML = `<tr><td colspan="9" class="unserved-empty">${escapeHtml(error.message)}</td></tr>`;
            showToast(error.message);
        }
    }

    function schedulePreview() {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(loadPreview, 350);
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
    printBtn.addEventListener('click', () => {
        const url = `${routes.print}?${collectParams().toString()}`;
        window.open(url, '_blank', 'noopener');
    });

    renderDateFields();
    if (window.lucide) window.lucide.createIcons();
})();
