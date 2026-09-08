document.addEventListener('DOMContentLoaded', () => {
    if (typeof lucide !== 'undefined') lucide.createIcons();
    initPayments();
});

const money = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2
});

let currentStep = 1;
let maxSteps = 4;

function normalizeInvoiceCandidates(value) {
    const raw = String(value || '').trim();
    if (!raw) return [];
    const normalize = value => String(value || '').trim().replace(/\s+/g, '').toUpperCase();
    const keys = [normalize(raw)];
    raw.split(/[,\s/]+/).forEach(token => {
        const key = normalize(token);
        if (key) keys.push(key);
    });
    (raw.match(/SN-\d+/gi) || []).forEach(token => keys.push(normalize(token)));
    return [...new Set(keys.filter(Boolean))];
}

function returnMatchesInvoice(ret, invoice) {
    const returnKeys = new Set(normalizeInvoiceCandidates(ret?.invoice_no));
    if (!returnKeys.size) return false;
    return normalizeInvoiceCandidates(invoice?.invoice_no).some(key => returnKeys.has(key));
}

let currentGroupPrintReturns = [];

function manualReturnRowHtml(ret = null, scope = 'payment') {
    const returnOrderId = String(ret?.return_order_id ?? ret?.sales_return_id ?? ret?.id ?? '');
    const walletRaw = ret?.wallet_adjustment ?? ret?.return_amount ?? ret?.total_amount ?? '';
    const walletAdjustment = walletRaw === null || walletRaw === undefined ? '' : String(walletRaw);
    const returnNo = String(ret?.return_number ?? '');

    return `
        <tr class="manual-return-row" data-return-scope="${escapeAttr(scope)}">
            <td class="p-3 min-w-[170px]">
                <input type="text" value="${escapeAttr(returnOrderId)}" class="manual-return-id w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black text-maroon outline-none focus:border-maroon" placeholder="Return Order ID">
            </td>
            <td class="p-3 min-w-[180px]">
                <input type="text" value="${escapeAttr(walletAdjustment)}" class="manual-return-amount-input w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-right text-xs font-black text-maroon outline-none focus:border-maroon" placeholder="Text or number">
            </td>
            <td class="p-3 min-w-[170px]">
                <input type="text" value="${escapeAttr(returnNo)}" class="manual-return-number-input w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black text-maroon outline-none focus:border-maroon" placeholder="Return No.">
            </td>
            <td class="p-3 text-center whitespace-nowrap">
                <button type="button" onclick="window.removeManualReturnRow(this, '${escapeAttr(scope)}')" class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-slate-200 text-slate-400 hover:text-red-600 hover:border-red-200" title="Remove Row"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
            </td>
            <td class="p-3 text-center">
                <button type="button" onclick="window.printManualReturnFromRow(this)" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-maroon text-white hover:bg-maroon-800" title="Print Manual Return"><i data-lucide="printer" class="w-3.5 h-3.5"></i></button>
            </td>
        </tr>`;
}

function returnTbodyForScope(scope) {
    return document.getElementById(scope === 'group' ? 'group-print-returns-tbody' : 'sudden-returns-tbody');
}

function renderManualReturns(scope = 'payment', initialReturns = null) {
    const tbody = returnTbodyForScope(scope);
    if (!tbody) return;
    const returns = Array.isArray(initialReturns) ? initialReturns : [];
    tbody.innerHTML = returns.length
        ? returns.map(ret => manualReturnRowHtml(ret, scope)).join('')
        : manualReturnRowHtml(null, scope);
    if (scope === 'group') {
        window.filterManualReturnsTable('group', document.getElementById('group-print-returns-search')?.value || '');
    }
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

window.addManualReturnRow = function(scope = 'payment') {
    const tbody = returnTbodyForScope(scope);
    if (!tbody) return;
    tbody.insertAdjacentHTML('beforeend', manualReturnRowHtml(null, scope));
    if (scope === 'group') {
        window.filterManualReturnsTable('group', document.getElementById('group-print-returns-search')?.value || '');
    }
    if (typeof lucide !== 'undefined') lucide.createIcons();
};

window.removeManualReturnRow = function(button, scope = 'payment') {
    const row = button?.closest('.manual-return-row');
    const tbody = returnTbodyForScope(scope);
    if (!row || !tbody) return;
    const rows = tbody.querySelectorAll('.manual-return-row');
    if (rows.length <= 1) {
        row.querySelector('.manual-return-id').value = '';
        row.querySelector('.manual-return-amount-input').value = '';
        row.querySelector('.manual-return-number-input').value = '';
    } else {
        row.remove();
    }
};

function collectManualReturns(scope = 'payment') {
    const tbody = returnTbodyForScope(scope);
    if (!tbody) return [];

    return Array.from(tbody.querySelectorAll('.manual-return-row')).map(row => {
        const returnOrderId = String(row.querySelector('.manual-return-id')?.value || '').trim();
        const amountText = String(row.querySelector('.manual-return-amount-input')?.value || '').trim();
        const returnNo = String(row.querySelector('.manual-return-number-input')?.value || '').trim();

        if (!returnOrderId && !amountText && !returnNo) return null;

        return {
            return_order_id: returnOrderId,
            wallet_adjustment: amountText === '' ? null : amountText,
            return_number: returnNo
        };
    }).filter(Boolean);
}

function walletAdjustmentNumericValue(value) {
    const raw = String(value ?? '').trim();
    if (!raw) return null;
    const normalized = raw.replace(/,/g, '');
    if (!/^[-+]?(?:\d+(?:\.\d*)?|\.\d+)$/.test(normalized)) return null;
    const parsed = Number(normalized);
    return Number.isFinite(parsed) ? parsed : null;
}

function formatWalletAdjustmentForDisplay(value) {
    const raw = String(value ?? '').trim();
    if (!raw) return '';
    const numeric = walletAdjustmentNumericValue(raw);
    return numeric === null ? raw : money.format(numeric);
}

function serverSafeManualReturns(returns = []) {
    return returns.map(ret => ({
        ...ret,
        wallet_adjustment: walletAdjustmentNumericValue(ret?.wallet_adjustment)
    }));
}

window.filterManualReturnsTable = function(scope = 'group', value = '') {
    const tbody = returnTbodyForScope(scope);
    if (!tbody) return;
    const query = String(value || '').trim().toLowerCase();
    Array.from(tbody.querySelectorAll('.manual-return-row')).forEach(row => {
        const haystack = Array.from(row.querySelectorAll('input, select, textarea'))
            .map(el => String(el.value || ''))
            .join(' ')
            .toLowerCase();
        row.style.display = !query || haystack.includes(query) ? '' : 'none';
    });
};

window.printManualReturnFromRow = function(button) {
    const row = button?.closest('.manual-return-row');
    if (!row) return;
    const returnOrderId = String(row.querySelector('.manual-return-id')?.value || '').trim();
    const amountText = String(row.querySelector('.manual-return-amount-input')?.value || '').trim();
    const returnNo = String(row.querySelector('.manual-return-number-input')?.value || '').trim();

    if (!returnOrderId && !amountText && !returnNo) {
        showNotification('Enter at least one manual Return value to print.', 'error');
        return;
    }

    const popup = window.open('', '_blank', 'width=760,height=620');
    if (!popup) {
        showNotification('Allow pop-ups to print the Return row.', 'error');
        return;
    }
    const amount = formatWalletAdjustmentForDisplay(amountText);
    popup.document.write(`<!doctype html><html><head><title>Return</title><style>
        body{font-family:Arial,sans-serif;color:#111;padding:32px}h1{font-size:18px;text-align:center;margin:0}h2{font-size:14px;text-align:center;margin:6px 0 28px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #111;padding:10px;font-size:12px}th{text-align:left;background:#f5f5f5}.right{text-align:right}@media print{body{padding:0}}
    </style></head><body><h1>W68 AUTO PARTS &amp; SERVICE CENTER</h1><h2>RETURN</h2><table><thead><tr><th>RETURN ORDER ID</th><th class="right">WALLET &amp; ADJUSTMENT</th><th>RETURN NO.</th></tr></thead><tbody><tr><td>${escapeHtml(returnOrderId || '')}</td><td class="right">${escapeHtml(amount)}</td><td>${escapeHtml(returnNo || '')}</td></tr></tbody></table><script>window.onload=()=>{window.print();};<\/script></body></html>`);
    popup.document.close();
};

function renderSuddenReturns() {
    // Manual print-only fields: never auto-load saved returns or Sales Return data.
    renderManualReturns('payment', []);
}

let currentPayment = {
    customer: null,
    banks: [],
    invoices: [],
    checks: [],
    returns: [],
    paymentNo: '',
    termsDays: null,
    mode: 'create',
    paymentId: null
};
let invoiceSelectionOrder = new Map();
let invoiceSelectionSequence = 0;

function invoiceSelectionKeyFromRow(row) {
    if (!row) return '';
    return `${row.dataset.sourceType || ''}:${row.dataset.sourceId || ''}:${row.dataset.invoiceNo || ''}`;
}

function recordInvoiceSelection(check) {
    const row = check?.closest('tr');
    const key = invoiceSelectionKeyFromRow(row);
    if (!key) return;
    if (check.checked) {
        if (!invoiceSelectionOrder.has(key)) {
            invoiceSelectionOrder.set(key, ++invoiceSelectionSequence);
        }
    } else {
        invoiceSelectionOrder.delete(key);
    }
}

function selectedInvoiceRowsInClickOrder() {
    return Array.from(document.querySelectorAll('#modal-invoices-tbody .invoice-check:checked'))
        .map(cb => cb.closest('tr'))
        .filter(Boolean)
        .sort((a, b) => {
            const ak = invoiceSelectionOrder.get(invoiceSelectionKeyFromRow(a)) ?? Number.MAX_SAFE_INTEGER;
            const bk = invoiceSelectionOrder.get(invoiceSelectionKeyFromRow(b)) ?? Number.MAX_SAFE_INTEGER;
            return ak - bk;
        });
}
let paymentsPage = 1;
let paymentsLastPage = 1;
let paymentsHistoryPage = 1;
let paymentsHistoryLastPage = 1;
let paymentsHistorySearch = '';
let currentPaymentsTab = 'active';
let historyHasLoaded = false;
let currentHistoryViewStep = 1;
let currentHistoryPayment = null;
let historyDetailRequestSeq = 0;
let historyLedgerAbortController = null;
let pendingDeletePaymentId = null;
let pendingDeletePaymentNo = '';
let payorsHistoryPage = 1;
let payorsHistoryLastPage = 1;
let payorsHistorySearch = '';
let payorsHistoryHasLoaded = false;
let groupsPage = 1;
let groupsLastPage = 1;
let groupsSearch = '';
let groupsActivityFilter = 'active';
let groupsHasLoaded = false;
let existingGroupOptions = [];
let currentPayorHistoryCustomerId = null;
let currentPayorHistoryCustomerName = '';
let currentPayorHistoryPage = 1;
let currentPayorHistoryLastPage = 1;
let currentPayorHistoryFilters = {};
let payorHistoryFilterTimer = null;
let payorHistoryRequestSeq = 0;
let payorHistoryAbortController = null;
let currentGroupId = null;
let currentGroupItems = [];
let currentGroupPrintMeta = { online_percent: 0, online_payment: 0 };
let currentProcessedGroupReceipts = [];
let currentGroupDraggedItemId = 0;

function isRegularPaymentsContext() {
    return String(routes().groupDetail || '').includes('/regular/');
}

function processGroupOrderStorageKey(groupId) {
    return `hatdog:regular:payments:group-order:${Number(groupId || 0)}`;
}

function applySavedProcessGroupOrder(items, groupId) {
    const rows = Array.isArray(items) ? [...items] : [];
    if (!isRegularPaymentsContext() || !groupId || rows.length < 2) return rows;

    try {
        const saved = JSON.parse(localStorage.getItem(processGroupOrderStorageKey(groupId)) || '[]');
        if (!Array.isArray(saved) || !saved.length) return rows;

        const byId = new Map(rows.map(item => [Number(item.id || 0), item]));
        const used = new Set();
        const ordered = [];
        saved.forEach(rawId => {
            const id = Number(rawId || 0);
            if (id > 0 && byId.has(id) && !used.has(id)) {
                ordered.push(byId.get(id));
                used.add(id);
            }
        });
        rows.forEach(item => {
            const id = Number(item.id || 0);
            if (!used.has(id)) ordered.push(item);
        });
        return ordered;
    } catch (_) {
        return rows;
    }
}

function saveCurrentProcessGroupOrder() {
    if (!isRegularPaymentsContext() || !currentGroupId || !currentGroupItems.length) return;
    try {
        localStorage.setItem(
            processGroupOrderStorageKey(currentGroupId),
            JSON.stringify(currentGroupItems.map(item => Number(item.id || 0)).filter(id => id > 0))
        );
    } catch (_) {
        // Reordering still works for the open modal even when browser storage is unavailable.
    }
}

function clearProcessGroupDropStyles() {
    document.querySelectorAll('#process-group-tbody tr[data-item-id]').forEach(row => {
        row.style.boxShadow = '';
        row.style.opacity = '';
    });
}

function syncProcessGroupOrderFromDom() {
    const rows = Array.from(document.querySelectorAll('#process-group-tbody tr[data-item-id]'));
    if (!rows.length) return;

    const byId = new Map(currentGroupItems.map(item => [Number(item.id || 0), item]));
    const ordered = rows
        .map(row => byId.get(Number(row.dataset.itemId || 0)))
        .filter(Boolean);
    const used = new Set(ordered.map(item => Number(item.id || 0)));
    currentGroupItems.forEach(item => {
        if (!used.has(Number(item.id || 0))) ordered.push(item);
    });
    currentGroupItems = ordered;

    rows.forEach((row, index) => {
        row.dataset.index = String(index);
        const checkbox = row.querySelector('.process-group-check');
        if (checkbox) checkbox.dataset.index = String(index);
    });

    saveCurrentProcessGroupOrder();
    updateProcessGroupSummary();
}

window.beginProcessGroupDrag = function(event, itemId) {
    if (!isRegularPaymentsContext()) return;
    currentGroupDraggedItemId = Number(itemId || 0);
    if (!currentGroupDraggedItemId) {
        event.preventDefault();
        return;
    }
    const row = event.currentTarget?.closest('tr[data-item-id]');
    if (row) row.style.opacity = '0.45';
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(currentGroupDraggedItemId));
    }
};

window.overProcessGroupDrag = function(event, targetItemId, targetRow) {
    if (!isRegularPaymentsContext() || !currentGroupDraggedItemId) return;
    if (Number(targetItemId || 0) === currentGroupDraggedItemId) return;
    event.preventDefault();
    if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';

    document.querySelectorAll('#process-group-tbody tr[data-item-id]').forEach(row => {
        if (row !== targetRow) row.style.boxShadow = '';
    });
    if (!targetRow) return;
    const rect = targetRow.getBoundingClientRect();
    const after = event.clientY > rect.top + (rect.height / 2);
    targetRow.style.boxShadow = after
        ? 'inset 0 -3px 0 #7c3aed'
        : 'inset 0 3px 0 #7c3aed';
};

window.dropProcessGroupDrag = function(event, targetItemId, targetRow) {
    if (!isRegularPaymentsContext() || !currentGroupDraggedItemId || !targetRow) return;
    event.preventDefault();

    const draggedId = Number(currentGroupDraggedItemId || 0);
    const targetId = Number(targetItemId || 0);
    if (!draggedId || !targetId || draggedId === targetId) {
        clearProcessGroupDropStyles();
        currentGroupDraggedItemId = 0;
        return;
    }

    const tbody = document.getElementById('process-group-tbody');
    const draggedRow = tbody?.querySelector(`tr[data-item-id="${draggedId}"]`);
    if (!draggedRow) {
        clearProcessGroupDropStyles();
        currentGroupDraggedItemId = 0;
        return;
    }

    const rect = targetRow.getBoundingClientRect();
    const insertAfter = event.clientY > rect.top + (rect.height / 2);
    if (insertAfter) targetRow.after(draggedRow);
    else targetRow.before(draggedRow);

    clearProcessGroupDropStyles();
    currentGroupDraggedItemId = 0;
    syncProcessGroupOrderFromDom();
};

window.endProcessGroupDrag = function() {
    clearProcessGroupDropStyles();
    currentGroupDraggedItemId = 0;
};
let payorHistorySelectedIds = new Set();
let payorsHistoryTimer = null;
let groupsFilterTimer = null;

function routes() {
    return window.paymentsRoutes || {
        data: '/admin/payments/data',
        customer: '/admin/payments/customer',
        customerReturns: '/admin/payments/customer/:customerId/returns',
        returnLookup: '/admin/payments/return/:returnId',
        process: '/admin/payments/process',
        historyData: '/admin/payments/history/data',
        historyDetail: '/admin/payments/history/:paymentId',
        historyUpdate: '/admin/payments/history/:paymentId',
        historyDelete: '/admin/payments/history/:paymentId',
        historyPrint: '/admin/payments/history/print',
        payorHistory: '/admin/payments/history/payor/:customerId',
        payors: '/admin/payments/history/payors',
        historyItems: '/admin/payments/history/items/:sourceType/:sourceId',
        historyLedger: '/admin/payments/history/:paymentId/ledger',
        groupsData: '/admin/payments/groups/data',
        groupsStore: '/admin/payments/groups/store',
        groupDetail: '/admin/payments/groups/:groupId',
        groupPrint: '/admin/payments/groups/:groupId/print',
        groupProcess: '/admin/payments/groups/:groupId/process',
        groupsItems: '/admin/payments/groups/:groupId/items',
        groupItemUpdate: '/admin/payments/groups/:groupId/items/:itemId',
        groupItemDelete: '/admin/payments/groups/:groupId/items/:itemId',
        groupDelete: '/admin/payments/groups/:groupId'
    };
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function initPayments() {
    initPaymentsTabs();
    loadPaymentsTable();
    bindInvoiceHeaderCheckbox();
    bindPayorHistoryFilters();
    bindProcessGroupSelectAll();
    const combo = document.getElementById('payment-existing-group-combobox');
    if (combo && combo.dataset.outsideBound !== '1') {
        combo.dataset.outsideBound = '1';
        document.addEventListener('click', event => {
            if (!combo.contains(event.target)) {
                document.getElementById('payment-existing-group-options')?.classList.add('hidden');
            }
        });
    }
}

function initPaymentsTabs() {
    showPaymentsTab('active');
}

function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value || '---';
}

async function fetchPaymentsJson(url, fallbackMessage = 'Unable to load payments.') {
    const request = async () => {
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            cache: 'no-store'
        });

        const text = await response.text();

        if (!text || !text.trim()) {
            const error = new Error(
                `${fallbackMessage} Server returned an empty response${response.status ? ` (HTTP ${response.status})` : ''}.`
            );
            error.emptyResponse = true;
            error.status = response.status;
            throw error;
        }

        let data;
        try {
            data = JSON.parse(text);
        } catch (_) {
            const error = new Error(
                `${fallbackMessage} Server returned an invalid response${response.status ? ` (HTTP ${response.status})` : ''}.`
            );
            error.invalidJson = true;
            error.status = response.status;
            throw error;
        }

        if (!response.ok || data.success === false) {
            throw new Error(
                data.message ||
                `${fallbackMessage}${response.status ? ` (HTTP ${response.status})` : ''}`
            );
        }

        return data;
    };

    try {
        return await request();
    } catch (error) {
        // HostForge may occasionally terminate an application response before
        // Laravel can write its JSON body. Retry that empty/invalid response once
        // instead of immediately crashing on Response.json().
        if (!error?.emptyResponse && !error?.invalidJson) {
            throw error;
        }

        await new Promise(resolve => setTimeout(resolve, 500));
        return await request();
    }
}

window.loadPaymentsTable = async function() {
    const tbody = document.getElementById('payments-tbody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="6" class="py-12 text-center text-slate-400 font-bold">Loading closed P.O records...</td></tr>`;

    try {
        const url = new URL(routes().data, window.location.origin);
        url.searchParams.set('page', paymentsPage);

        const data = await fetchPaymentsJson(
            url.toString(),
            'Unable to load payments.'
        );

        if (!data.payors.length) {
            tbody.innerHTML = `<tr><td colspan="6" class="py-12 text-center text-slate-400 font-bold">No closed P.O records with unpaid balances.</td></tr>`;
            updateAgingStats(data.aging_stats);
            updatePaymentsPagination(data);
            return;
        }

        tbody.innerHTML = data.payors.map(row => {
            const poList = row.po_numbers?.length ? row.po_numbers.slice(0, 3).join(', ') : '---';
            const morePo = row.po_numbers?.length > 3 ? ` +${row.po_numbers.length - 3}` : '';

            return `
                <tr class="${row.has_overdue ? 'payor-row-overdue animate-overdue-blink' : 'hover:bg-slate-50/50'} transition-colors group">
                    <td class="py-5 px-6 text-slate-700 font-bold">
                        <div class="flex flex-col">
                            <span>${escapeHtml(row.name)}</span>
                            ${row.has_overdue ? `<span class="mt-1 inline-flex w-fit rounded-full bg-red-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wide text-red-700">${Number(row.overdue_invoice_count || 0)} overdue invoice${Number(row.overdue_invoice_count || 0) === 1 ? '' : 's'}</span>` : ''}
                        </div>
                    </td>
                    <td class="py-5 px-6">
                        <div class="text-center">
                            <div class="text-slate-600 font-black">${escapeHtml(poList)}${morePo}</div>
                            <div class="text-[9px] text-slate-400 font-bold mt-1">${row.invoice_count} closed P.O / invoice${row.invoice_count === 1 ? '' : 's'}</div>
                        </div>
                    </td>
                    <td class="py-5 px-6 text-center">
                        ${renderStatusCount(row.partial_count, row.partial_new_count, row.partial_latest_date, 'bg-amber-tag', 'text-amber-600')}
                    </td>
                    <td class="py-5 px-6 text-center">
                        ${renderStatusCount(row.paid_count, row.paid_new_count, row.paid_latest_date, 'bg-emerald-tag', 'text-emerald-600')}
                    </td>
                    <td class="py-5 px-6 text-center">
                        <span class="px-3 py-1 ${row.has_overdue ? 'bg-red-100 text-red-700' : 'bg-slate-100'} rounded-full text-[10px]">${money.format(row.total_due)}</span>
                        <div class="text-[9px] ${row.has_overdue ? 'text-red-500' : 'text-slate-400'} font-bold mt-1">${row.has_overdue ? `Exceeded by ${Number(row.oldest_overdue_by_days || 0)} day(s)` : (row.latest_date || '---')}</div>
                    </td>
                    <td class="py-5 px-6 text-center">
                        <button onclick="openProceedModal(${row.customer_id})" class="px-4 py-2 bg-maroon text-white text-[10px] font-bold rounded-xl hover:bg-maroon-800 transition-all shadow-md shadow-maroon/10">
                            PROCEED
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        updateAgingStats(data.aging_stats);
        updatePaymentsPagination(data);
        window.filterTableByColumns('payments-tbody', 'payments-active-filter-row');

        // After rendering, check if redirected from a notification
        setTimeout(highlightPayorFromNotification, 100);
    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="6" class="py-12 text-center text-red-500 font-bold">${escapeHtml(error.message)}</td></tr>`;
    }
};

window.loadPaymentsHistoryTable = async function() {
    const tbody = document.getElementById('payments-history-tbody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="4" class="py-12 text-center text-slate-400 font-bold">Loading payment history...</td></tr>`;

    try {
        const url = new URL(routes().payors, window.location.origin);
        url.searchParams.set('page', paymentsHistoryPage);
        if (paymentsHistorySearch) url.searchParams.set('search', paymentsHistorySearch);
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        if (!res.ok || !data.success) throw new Error(data.message || 'Unable to load payment history.');

        const rows = data.rows || [];
        tbody.innerHTML = rows.length ? rows.map(row => `
            <tr class="hover:bg-slate-50/50 transition-colors">
                <td class="py-4 px-5 font-black text-slate-800">${escapeHtml(row.customer_name || '---')}</td>
                <td class="py-4 px-5 text-center font-black text-maroon">${Number(row.paid_invoices || 0)}</td>
                <td class="py-4 px-5 text-center font-bold text-slate-500">${escapeHtml(row.latest_paid_date || '---')}</td>
                <td class="py-4 px-5 text-center">
                    <button onclick="window.openPayorHistoryModal(${Number(row.customer_id || 0)}, '${escapeAttr(row.customer_name || '')}')" class="payments-history-action" style="background:#00FFFF;color:#4A0E0E" title="View Payment History"><i data-lucide="eye" class="w-3.5 h-3.5"></i><span>View</span></button>
                </td>
            </tr>
        `).join('') : `<tr><td colspan="4" class="py-12 text-center text-slate-400 font-bold">No payment history found.</td></tr>`;

        updatePaymentsHistoryPagination(data);
        if (typeof lucide !== 'undefined') lucide.createIcons();
    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="4" class="py-12 text-center text-red-500 font-bold">${escapeHtml(error.message)}</td></tr>`;
    }
};

function renderStatusCount(count, newCount, latestDate, tagClass, textClass) {
    return `
        <div class="flex items-center justify-center space-x-3">
            <span class="${textClass} font-black text-lg">${Number(count || 0)}</span>
            ${Number(newCount || 0) > 0 ? `
                <div class="status-tag-container">
                    <span class="tag-count ${tagClass}">+${Number(newCount || 0)}</span>
                    <span class="tag-date">(${escapeHtml(latestDate || '')})</span>
                </div>
            ` : ''}
        </div>
    `;
}

function updatePaymentsPagination(data = {}) {
    paymentsPage = Number(data.page || paymentsPage || 1);
    paymentsLastPage = Number(data.last_page || 1);

    const total = Number(data.total || 0);
    const perPage = Number(data.per_page || 50);
    const from = total ? ((paymentsPage - 1) * perPage) + 1 : 0;
    const to = total ? Math.min(paymentsPage * perPage, total) : 0;

    setText('payments-page-info', `Showing ${from}-${to} of ${total} payors`);
    const prev = document.getElementById('payments-prev-btn');
    const next = document.getElementById('payments-next-btn');
    if (prev) prev.disabled = paymentsPage <= 1;
    if (next) next.disabled = paymentsPage >= paymentsLastPage;
}

window.showPaymentsTab = function(tab) {
    currentPaymentsTab = ['history', 'groups'].includes(tab) ? tab : 'active';

    const activeView = document.getElementById('payments-active-view');
    const historyView = document.getElementById('payments-history-view');
    const groupsView = document.getElementById('payments-groups-view');
    const activeTab = document.getElementById('payments-tab-active');
    const historyTab = document.getElementById('payments-tab-history');
    const groupsTab = document.getElementById('payments-tab-groups');

    if (activeView) activeView.classList.toggle('hidden', currentPaymentsTab !== 'active');
    if (historyView) historyView.classList.toggle('hidden', currentPaymentsTab !== 'history');
    if (groupsView) groupsView.classList.toggle('hidden', currentPaymentsTab !== 'groups');
    if (activeTab) activeTab.classList.toggle('active', currentPaymentsTab === 'active');
    if (historyTab) historyTab.classList.toggle('active', currentPaymentsTab === 'history');
    if (groupsTab) groupsTab.classList.toggle('active', currentPaymentsTab === 'groups');

    if (currentPaymentsTab === 'groups' && !groupsHasLoaded) {
        groupsPage = 1;
        loadGroupsTable();
        groupsHasLoaded = true;
    }

    if (currentPaymentsTab === 'history' && document.getElementById('payments-history-tbody') && !historyHasLoaded) {
        paymentsHistoryPage = 1;
        loadPaymentsHistoryTable();
        historyHasLoaded = true;
    }

    if (typeof lucide !== 'undefined') lucide.createIcons();
};

function showNotification(message, type = 'success') {
    const colors = {
        success: { bg: '#1e7e34', icon: 'check-circle' },
        error: { bg: '#b91c1c', icon: 'alert-circle' }
    };
    const cfg = colors[type] || colors.success;
    let container = document.getElementById('payments-notification-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'payments-notification-container';
        container.style.cssText = 'position:fixed;top:1.25rem;right:1.25rem;z-index:9999;display:flex;flex-direction:column;gap:0.6rem;';
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.style.cssText = `display:flex;align-items:center;gap:0.6rem;background:${cfg.bg};color:#fff;font-weight:600;font-size:0.85rem;padding:0.7rem 1rem;border-radius:0.5rem;box-shadow:0 6px 18px rgba(0,0,0,0.18);max-width:22rem;animation:fadeIn 0.2s ease;`;
    toast.innerHTML = `<i data-lucide="${cfg.icon}" class="w-4 h-4 shrink-0"></i><span>${escapeHtml(message)}</span>`;
    container.appendChild(toast);
    if (typeof lucide !== 'undefined') lucide.createIcons();
    setTimeout(() => {
        toast.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(1rem)';
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

async function loadPayorsHistoryTable() {
    const tbody = document.getElementById('payments-payor-history-tbody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="4" class="py-12 text-center text-slate-400 font-bold">Loading payment history...</td></tr>`;

    try {
        const params = new URLSearchParams();
        if (payorsHistorySearch) params.set('search', payorsHistorySearch);
        params.set('page', payorsHistoryPage);

        const response = await fetch(`${routes().payors}?${params.toString()}`, { headers: { 'Accept': 'application/json' } });
        const data = await response.json();
        if (!data.success) throw new Error(data.message || 'Failed to load payment history.');

        const rows = data.rows || [];
        tbody.innerHTML = rows.length ? rows.map(row => `
            <tr class="hover:bg-slate-50 border-b border-slate-100">
                <td class="p-4">
                    <span class="font-bold text-slate-800">${escapeHtml(row.customer_name || '---')}</span>
                    <div class="text-xs text-slate-400 mt-0.5">${escapeHtml(row.customer_identifier || '')}</div>
                </td>
                <td class="p-4 text-center font-bold text-slate-700">${Number(row.paid_invoices || 0)}</td>
                <td class="p-4 text-center text-slate-500">${escapeHtml(row.latest_paid_date || '---')}</td>
                <td class="p-4 text-center">
                    <button onclick="window.openPayorHistoryModal(${Number(row.customer_id)}, '${escapeAttr(row.customer_name || '')}')"
                            class="payments-history-action">View</button>
                </td>
            </tr>
        `).join('') : `<tr><td colspan="4" class="py-12 text-center text-slate-400">No payment history found.</td></tr>`;

        payorsHistoryPage = Number(data.page || payorsHistoryPage || 1);
        payorsHistoryLastPage = Number(data.last_page || 1);
        updatePayorsHistoryPagination(data);
        window.filterTableByColumns('payments-payor-history-tbody', 'payments-payor-history-filter-row');
        if (typeof lucide !== 'undefined') lucide.createIcons();
    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="4" class="py-12 text-center text-red-500 font-bold">${escapeHtml(error.message)}</td></tr>`;
    }
}

function updatePayorsHistoryPagination(data = {}) {
    const total = Number(data.total || 0);
    const perPage = Number(data.per_page || 50);
    const from = total ? ((payorsHistoryPage - 1) * perPage) + 1 : 0;
    const to = total ? Math.min(payorsHistoryPage * perPage, total) : 0;

    setText('payments-payor-history-page-info', `Showing ${from}-${to} of ${total} payors`);
    const prev = document.getElementById('payments-payor-history-prev-btn');
    const next = document.getElementById('payments-payor-history-next-btn');
    if (prev) prev.disabled = payorsHistoryPage <= 1;
    if (next) next.disabled = payorsHistoryPage >= payorsHistoryLastPage;
}

window.changePayorsHistoryPage = function(direction) {
    const nextPage = payorsHistoryPage + direction;
    if (nextPage < 1 || nextPage > payorsHistoryLastPage) return;
    payorsHistoryPage = nextPage;
    loadPayorsHistoryTable();
};

window.filterPayorsHistoryTable = function() {
    const input = document.getElementById('payments-payor-history-search');
    if (!input) return;
    clearTimeout(payorsHistoryTimer);
    payorsHistoryTimer = setTimeout(() => {
        payorsHistorySearch = input.value.trim();
        payorsHistoryPage = 1;
        loadPayorsHistoryTable();
    }, 300);
};

window.openPayorHistoryModal = function(customerId, customerName) {
    currentPayorHistoryCustomerId = customerId;
    currentPayorHistoryCustomerName = customerName || '';
    currentPayorHistoryPage = 1;
    currentPayorHistoryLastPage = 1;
    currentPayorHistoryFilters = {};
    document.querySelectorAll('#payor-history-search-row .payor-history-filter-input').forEach(input => {
        input.value = '';
    });
    setText('payor-history-customer-name', currentPayorHistoryCustomerName);
    toggleModal('payor-history-modal', true);
    loadPayorHistoryInvoices();
};

window.changePayorHistoryPage = function(direction) {
    const nextPage = currentPayorHistoryPage + direction;
    if (nextPage < 1 || nextPage > currentPayorHistoryLastPage) return;
    currentPayorHistoryPage = nextPage;
    loadPayorHistoryInvoices();
};

async function loadPayorHistoryInvoices() {
    const tbody = document.getElementById('payor-history-invoices-tbody');
    if (!tbody || !currentPayorHistoryCustomerId) return;

    const requestSeq = ++payorHistoryRequestSeq;
    if (payorHistoryAbortController) payorHistoryAbortController.abort();
    payorHistoryAbortController = new AbortController();

    tbody.innerHTML = `<tr><td colspan="5" class="py-10 text-center text-slate-400 font-bold">Loading Payment Nos...</td></tr>`;

    try {
        const url = new URL(routes().payorHistory.replace(':customerId', currentPayorHistoryCustomerId), window.location.origin);
        url.searchParams.set('page', currentPayorHistoryPage);
        url.searchParams.set('per_page', '50');
        Object.entries(currentPayorHistoryFilters).forEach(([key, value]) => {
            if (key !== 'action' && String(value || '').trim() !== '') url.searchParams.set(key, String(value).trim());
        });

        const response = await fetch(url.toString(), {
            headers: { 'Accept': 'application/json' },
            signal: payorHistoryAbortController.signal
        });
        const data = await response.json();
        if (requestSeq !== payorHistoryRequestSeq) return;
        if (!response.ok || !data.success) throw new Error(data.message || 'Failed to load Payment Nos.');

        const rows = data.rows || data.data || [];
        tbody.innerHTML = rows.length ? rows.map(row => {
            const invoiceNumbers = Array.isArray(row.invoice_numbers) ? row.invoice_numbers.filter(Boolean) : [];
            const visible = invoiceNumbers.slice(0, 5);
            const moreCount = Math.max(invoiceNumbers.length - visible.length, 0);
            const preview = visible.length
                ? visible.map(no => `<span class="inline-flex rounded-md bg-slate-100 px-2 py-1 text-[9px] font-black text-slate-600">${escapeHtml(no)}</span>`).join('')
                : '<span class="text-slate-400">---</span>';
            const hoverList = `<div class="pointer-events-none invisible absolute left-1/2 top-full z-[920] mt-2 w-96 -translate-x-1/2 rounded-xl border border-slate-200 bg-white p-3 opacity-0 shadow-2xl transition-all group-hover/invoices:visible group-hover/invoices:opacity-100">
                <p class="mb-2 text-[9px] font-black uppercase tracking-widest text-maroon">Invoices in ${escapeHtml(row.payment_no || 'Payment')}</p>
                <div class="max-h-60 overflow-y-auto custom-scrollbar text-[10px] font-bold text-slate-600">${(invoiceNumbers.length ? invoiceNumbers : ['---']).map((no, i) => `<div class="border-b border-slate-50 py-1.5 last:border-0"><span class="mr-2 text-slate-300">${i + 1}.</span>${escapeHtml(no)}</div>`).join('')}</div>
            </div>`;
            return `
            <tr class="hover:bg-slate-50 border-b border-slate-100" data-payment-id="${Number(row.id || row.process_payment_id || 0)}">
                <td class="p-4 font-black text-maroon">${escapeHtml(row.payment_no || '---')}</td>
                <td class="p-4">
                    <div class="group/invoices relative inline-flex max-w-full flex-wrap items-center gap-1.5 cursor-help">
                        ${preview}${moreCount ? `<span class="text-[9px] font-black text-maroon">+${moreCount} more</span>` : ''}${hoverList}
                    </div>
                    <div class="mt-1 text-[9px] font-bold text-slate-400">${Number(row.invoice_count || invoiceNumbers.length)} invoice${Number(row.invoice_count || invoiceNumbers.length) === 1 ? '' : 's'}</div>
                </td>
                <td class="p-4 text-center font-bold text-slate-500">${escapeHtml(row.payment_date || '---')}</td>
                <td class="p-4 text-right font-black text-emerald-700">${money.format(Number(row.total_paid || 0))}</td>
                <td class="p-4"><div class="flex flex-wrap items-center justify-center gap-1.5">
                    <button onclick="window.openPaymentsHistoryView(${Number(row.id || 0)})" class="payments-history-action" style="background:#00FFFF;color:#4A0E0E;width:32px;height:32px;padding:0;justify-content:center;gap:0" title="View payment" aria-label="View payment"><i data-lucide="eye" class="w-4 h-4"></i></button>
                    <button onclick="window.printWholePaymentHistory(${Number(row.id || 0)})" class="payments-history-action text-white" style="background:#0F766E;width:32px;height:32px;padding:0;justify-content:center;gap:0" title="Print whole payment" aria-label="Print whole payment"><i data-lucide="printer" class="w-4 h-4"></i></button>
                    <button onclick="window.openEditPaymentModal(${Number(row.id || 0)})" class="payments-history-action" style="background:#EAB308;color:#4A0E0E;width:32px;height:32px;padding:0;justify-content:center;gap:0" title="Edit payment" aria-label="Edit payment"><i data-lucide="pencil" class="w-4 h-4"></i></button>
                    <button onclick="window.confirmDeletePaymentHistory(${Number(row.id || 0)}, '${escapeAttr(row.payment_no || '')}')" class="payments-history-action text-white" style="background:#4A0E0E;width:32px;height:32px;padding:0;justify-content:center;gap:0" title="Delete payment" aria-label="Delete payment"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                </div></td>
            </tr>`;
        }).join('') : `<tr><td colspan="5" class="py-10 text-center text-slate-400">No Payment Nos. found for this Payor.</td></tr>`;

        const actionQuery = String(currentPayorHistoryFilters.action || '').trim().toLowerCase();
        if (actionQuery) {
            Array.from(tbody.querySelectorAll('tr')).forEach(row => {
                row.style.display = searchableCellText(row.children[4]).includes(actionQuery) ? '' : 'none';
            });
        }

        const pagination = data.pagination || {};
        currentPayorHistoryPage = Number(pagination.current_page || data.page || currentPayorHistoryPage || 1);
        currentPayorHistoryLastPage = Number(pagination.last_page || data.last_page || 1);
        updatePayorHistoryPagination(data);
        if (typeof lucide !== 'undefined') lucide.createIcons();
    } catch (error) {
        if (error.name === 'AbortError') return;
        tbody.innerHTML = `<tr><td colspan="5" class="py-10 text-center text-red-500 font-bold">${escapeHtml(error.message)}</td></tr>`;
    }
}

function updatePayorHistoryPagination(data = {}) {
    const pagination = data.pagination || {};
    const total = Number(pagination.total || data.total || 0);
    const perPage = Number(pagination.per_page || data.per_page || 50);
    const from = Number(pagination.from ?? (total ? ((currentPayorHistoryPage - 1) * perPage) + 1 : 0));
    const to = Number(pagination.to ?? (total ? Math.min(currentPayorHistoryPage * perPage, total) : 0));

    setText('payor-history-page-info', `Showing ${from}-${to} of ${total} payments`);
    setText('payor-history-page-count', `Page ${currentPayorHistoryPage} of ${currentPayorHistoryLastPage}`);
    const prev = document.getElementById('payor-history-prev-btn');
    const next = document.getElementById('payor-history-next-btn');
    if (prev) prev.disabled = currentPayorHistoryPage <= 1;
    if (next) next.disabled = currentPayorHistoryPage >= currentPayorHistoryLastPage;
}

window.togglePayorHistorySelectAll = function(cb) {
    document.querySelectorAll('#payor-history-invoices-tbody .payor-history-check').forEach(rowCb => {
        const id = Number(rowCb.dataset.id || 0);
        rowCb.checked = cb.checked;
        if (cb.checked) { payorHistorySelectedIds.add(id); } else { payorHistorySelectedIds.delete(id); }
    });
    updatePayorHistorySelectionUi();
};

window.togglePayorHistoryRow = function(cb) {
    const id = Number(cb.dataset.id || 0);
    if (cb.checked) { payorHistorySelectedIds.add(id); } else { payorHistorySelectedIds.delete(id); }
    updatePayorHistorySelectionUi();
};

function updatePayorHistorySelectionUi() {
    const visibleCbs = Array.from(document.querySelectorAll('#payor-history-invoices-tbody .payor-history-check'));
    const selectAll = document.getElementById('payor-history-select-all');
    if (selectAll) {
        const checkedVisible = visibleCbs.filter(cb => cb.checked).length;
        selectAll.checked = visibleCbs.length > 0 && checkedVisible === visibleCbs.length;
        selectAll.indeterminate = checkedVisible > 0 && checkedVisible < visibleCbs.length;
    }
    const printBtn = document.getElementById('payor-history-print-selected-btn');
    if (printBtn) printBtn.disabled = payorHistorySelectedIds.size === 0;
}

window.printSelectedPayorHistory = async function() {
    if (payorHistorySelectedIds.size === 0) {
        showNotification('Select at least one item to print.', 'error');
        return;
    }
    const printBtn = document.getElementById('payor-history-print-selected-btn');
    if (printBtn) { printBtn.disabled = true; printBtn.textContent = 'Printing...'; }
    try {
        const response = await fetch(routes().historyPrint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify({ invoice_ids: Array.from(payorHistorySelectedIds) })
        });
        const data = await response.json();
        if (!data.success) throw new Error(data.message || 'Failed to load receipts.');
        const receipts = data.receipts || [];
        if (!receipts.length) throw new Error('No printable receipt data was returned.');
        receipts.forEach(receipt => { openPrintablePaymentLayout(paymentDetailToPrintable(receipt)); });
    } catch (error) {
        showNotification(error.message || 'Failed to print selected invoices.', 'error');
    } finally {
        if (printBtn) { printBtn.disabled = payorHistorySelectedIds.size === 0; printBtn.textContent = 'Print Selected'; }
    }
};

function bindPayorHistoryFilters() {
    document.addEventListener('input', event => {
        const input = event.target;
        if (!input?.classList?.contains('payor-history-filter-input')) return;

        clearTimeout(payorHistoryFilterTimer);
        payorHistoryFilterTimer = setTimeout(() => {
            currentPayorHistoryFilters = {};
            document.querySelectorAll('#payor-history-search-row .payor-history-filter-input').forEach(filterInput => {
                const key = filterInput.dataset.filter;
                const value = filterInput.value.trim();
                if (key && value !== '') {
                    currentPayorHistoryFilters[key] = value;
                }
            });
            currentPayorHistoryPage = 1;
            loadPayorHistoryInvoices();
        }, 300);
    });
}

window.openHistoryItemsModal = function(sourceType, sourceId, invoiceNo) {
    setText('history-items-invoice-label', invoiceNo || 'Invoice Items');
    document.getElementById('history-items-tbody').innerHTML = `<tr><td colspan="7" class="py-10 text-center text-slate-400 font-bold">Loading items...</td></tr>`;
    document.getElementById('history-items-tfoot').innerHTML = '';
    toggleModal('payor-history-items-modal', true);
    loadHistoryItems(sourceType, sourceId, invoiceNo || '');
};

async function loadHistoryItems(sourceType, sourceId, invoiceNo) {
    const tbody = document.getElementById('history-items-tbody');
    if (!tbody) return;

    try {
        const url = routes().historyItems
            .replace(':sourceType', encodeURIComponent(sourceType))
            .replace(':sourceId', sourceId) + `?invoice_no=${encodeURIComponent(invoiceNo || '')}`;
        const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const data = await response.json();
        if (!data.success) throw new Error(data.message || 'Failed to load invoice items.');

        const items = data.items || [];
        let totalQty = 0;
        let totalAmount = 0;

        tbody.innerHTML = items.length ? items.map(item => {
            const qty = Number(item.qty || 0);
            const total = Number(item.total || 0);
            totalQty += qty;
            totalAmount += total;
            return `
                <tr class="hover:bg-slate-50 border-b border-slate-100">
                    <td class="p-4 font-semibold text-slate-700">${escapeHtml(item.invoice_no || '---')}</td>
                    <td class="p-4 text-right text-slate-600">${money.format(Number(item.invoice_amount || 0))}</td>
                    <td class="p-4 text-slate-600">${escapeHtml(item.product_code || '---')}</td>
                    <td class="p-4 text-slate-600">${escapeHtml(item.description || '---')}</td>
                    <td class="p-4 text-slate-600">${escapeHtml(item.unit || '---')}</td>
                    <td class="p-4 text-center text-slate-600">${qty}</td>
                    <td class="p-4 text-right font-semibold text-maroon">${money.format(total)}</td>
                </tr>
            `;
        }).join('') : `<tr><td colspan="7" class="py-10 text-center text-slate-400">No item records found for this invoice.</td></tr>`;

        const tfoot = document.getElementById('history-items-tfoot');
        if (tfoot) {
            tfoot.innerHTML = items.length ? `
                <tr class="bg-slate-50 font-bold text-slate-800">
                    <td colspan="5" class="p-4 text-right">TOTAL</td>
                    <td class="p-4 text-center">${totalQty}</td>
                    <td class="p-4 text-right text-maroon">${money.format(totalAmount)}</td>
                </tr>
            ` : '';
        }
    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="7" class="py-10 text-center text-red-500 font-bold">${escapeHtml(error.message)}</td></tr>`;
    }
}

async function loadGroupsTable() {
    const tbody = document.getElementById('payments-groups-tbody');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="6" class="py-12 text-center text-slate-400 font-bold">Loading invoice groups...</td></tr>`;

    try {
        const params = new URLSearchParams();
        if (groupsSearch) params.set('search', groupsSearch);
        params.set('activity', groupsActivityFilter);
        params.set('page', groupsPage);

        const response = await fetch(`${routes().groupsData}?${params.toString()}`, { headers: { 'Accept': 'application/json' } });
        const data = await response.json();
        if (!data.success) throw new Error(data.message || 'Failed to load invoice groups.');

        const rows = data.rows || [];
        const statusMeta = {
            active: { label: 'Active', cls: 'bg-emerald-100 text-emerald-700' },
            partially_processed: { label: 'Partially Processed', cls: 'bg-amber-100 text-amber-700' },
            processed: { label: 'Processed', cls: 'bg-slate-200 text-slate-600' }
        };

        tbody.innerHTML = rows.length ? rows.map(row => {
            const meta = statusMeta[row.status] || { label: String(row.status || 'Active'), cls: 'bg-slate-100 text-slate-600' };
            const activityMeta = row.activity_status === 'active'
                ? { label: 'Active', cls: 'bg-emerald-50 text-emerald-700 border-emerald-200' }
                : { label: 'Not Active', cls: 'bg-slate-50 text-slate-500 border-slate-200' };
            const lastAdded = row.last_invoice_added_at ? String(row.last_invoice_added_at).slice(0, 10) : '---';
            return `
                <tr class="hover:bg-slate-50 border-b border-slate-100">
                    <td class="p-4">
                        <span class="font-bold text-slate-800">${escapeHtml(row.title || '---')}</span>
                        <div class="flex flex-wrap items-center gap-2 mt-1">
                            <span class="text-xs text-slate-400">Created by ${escapeHtml(row.created_by || 'system')}</span>
                            <span class="px-2 py-0.5 rounded-full border text-[9px] font-black uppercase tracking-wider ${activityMeta.cls}">${activityMeta.label}</span>
                            <span class="text-[9px] text-slate-400">Last invoice: ${escapeHtml(lastAdded)}</span>
                        </div>
                    </td>
                    <td class="p-4 text-center font-bold text-slate-700">${Number(row.total_invoices || 0)}</td>
                    <td class="p-4 text-right font-bold text-maroon">${money.format(Number(row.total_amount || 0))}</td>
                    <td class="p-4 text-center text-slate-500">${escapeHtml(row.grouped_date || '---')}</td>
                    <td class="p-4 text-center"><span class="px-2.5 py-1 rounded-full text-xs font-bold ${meta.cls}">${meta.label}</span></td>
                    <td class="p-4 text-center">
                        <div class="inline-flex items-center justify-center gap-2 flex-wrap">
                            <button onclick="window.openProcessGroupModal(${Number(row.id)})"
                                    class="payments-history-action"><i data-lucide="eye" class="w-3.5 h-3.5 inline-block mr-1"></i>View Group</button>
                            <button onclick="window.deletePaymentGroup(${Number(row.id)}, '${escapeAttr(row.title || '')}')"
                                    class="payments-history-action !text-red-600 hover:!bg-red-50"><i data-lucide="trash-2" class="w-3.5 h-3.5 inline-block mr-1"></i>Delete</button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('') : `<tr><td colspan="6" class="py-12 text-center text-slate-400">No invoice groups found.</td></tr>`;

        groupsPage = Number(data.page || groupsPage || 1);
        groupsLastPage = Number(data.last_page || 1);
        updateGroupsPagination(data);
        window.filterTableByColumns('payments-groups-tbody', 'payments-groups-filter-row');
        if (typeof lucide !== 'undefined') lucide.createIcons();
    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="6" class="py-12 text-center text-red-500 font-bold">${escapeHtml(error.message)}</td></tr>`;
    }
}

function updateGroupsPagination(data = {}) {
    const total = Number(data.total || 0);
    const perPage = Number(data.per_page || 50);
    const from = total ? ((groupsPage - 1) * perPage) + 1 : 0;
    const to = total ? Math.min(groupsPage * perPage, total) : 0;

    setText('payments-groups-page-info', `Showing ${from}-${to} of ${total} groups`);
    const prev = document.getElementById('payments-groups-prev-btn');
    const next = document.getElementById('payments-groups-next-btn');
    if (prev) prev.disabled = groupsPage <= 1;
    if (next) next.disabled = groupsPage >= groupsLastPage;
}

window.changeGroupsPage = function(direction) {
    const nextPage = groupsPage + direction;
    if (nextPage < 1 || nextPage > groupsLastPage) return;
    groupsPage = nextPage;
    loadGroupsTable();
};

window.filterGroupsTable = function() {
    const input = document.getElementById('payments-groups-search');
    if (!input) return;
    clearTimeout(groupsFilterTimer);
    groupsFilterTimer = setTimeout(() => {
        groupsSearch = input.value.trim();
        groupsPage = 1;
        loadGroupsTable();
    }, 300);
};

window.changeGroupsActivityFilter = function(value) {
    const allowed = ['active', 'not_active', 'all'];
    groupsActivityFilter = allowed.includes(value) ? value : 'active';
    groupsPage = 1;
    loadGroupsTable();
};

window.deletePaymentGroup = async function(groupId, groupTitle = '') {
    const id = Number(groupId || 0);
    if (!id) return;

    const label = groupTitle ? `"${groupTitle}"` : `Group #${id}`;
    const ok = window.confirm(`Delete ${label}?\n\nAll invoices inside this group will become UNPAID again and return to the Process Payment modal. This also removes the group's payment settlements.`);
    if (!ok) return;

    try {
        const template = routes().groupDelete || routes().groupDetail;
        const url = template.replace(':groupId', String(id));
        const response = await fetch(url, {
            method: 'DELETE',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() }
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'Failed to delete invoice group.');

        if (Number(currentGroupId || 0) === id) {
            currentGroupId = null;
            currentGroupItems = [];
            toggleModal('payment-process-group-modal', false);
        }

        existingGroupOptions = existingGroupOptions.filter(group => Number(group.id || 0) !== id);
        showNotification(`${data.deleted_group_title || groupTitle || 'Group'} deleted. ${Number(data.unpaid_invoices || 0)} invoice(s) are unpaid again.`, 'success');

        // Refresh every Payments surface affected by settlement removal.
        groupsHasLoaded = true;
        loadGroupsTable();
        paymentsPage = 1;
        loadPaymentsTable();
        historyHasLoaded = false;
        payorsHistoryHasLoaded = false;
        if (currentPaymentsTab === 'history') {
            historyHasLoaded = true;
            loadPaymentsHistoryTable();
        }
        if (currentPayorHistoryCustomerId && !document.getElementById('payor-history-modal')?.classList.contains('hidden')) {
            loadPayorHistoryInvoices();
        }
    } catch (error) {
        showNotification(error.message || 'Failed to delete invoice group.', 'error');
    }
};

window.openPaymentGroupModal = function() {
    const checkedRows = Array.from(document.querySelectorAll('#modal-invoices-tbody .invoice-check:checked'));
    const items = selectedInvoiceRowsInClickOrder().filter(tr => tr && tr.dataset.sourceType && tr.dataset.sourceId && tr.dataset.invoiceNo);

    if (!items.length) {
        showNotification('Select at least one invoice to group.', 'error');
        return;
    }

    const title = document.getElementById('payment-group-title');
    const error = document.getElementById('payment-group-error');
    const count = document.getElementById('payment-group-count');
    if (title) title.value = '';
    if (error) error.classList.add('hidden');
    if (count) count.textContent = `${items.length} invoice${items.length > 1 ? 's' : ''} selected`;

    const groupSelect = document.getElementById('payment-existing-group-select');
    const groupSearch = document.getElementById('payment-existing-group-search');
    if (groupSelect) groupSelect.value = '';
    if (groupSearch) groupSearch.value = '';
    if (typeof loadExistingGroupOptions === 'function') loadExistingGroupOptions();
    if (typeof updateGroupButton === 'function') updateGroupButton();

    toggleModal('payment-group-modal', true);
};

window.submitPaymentGroup = async function() {
    const titleEl = document.getElementById('payment-group-title');
    const errorEl = document.getElementById('payment-group-error');
    const createBtn = document.getElementById('payment-group-create-btn');
    const groupSelect = document.getElementById('payment-existing-group-select');

    const title = (titleEl?.value || '').trim();
    const selectedGroupId = (groupSelect && groupSelect.value) ? String(groupSelect.value) : '';

    if (!title && !selectedGroupId) {
        if (errorEl) {
            errorEl.textContent = 'Enter a new group title or select an existing group.';
            errorEl.classList.remove('hidden');
        }
        return;
    }
    if (title && selectedGroupId) {
        if (errorEl) {
            errorEl.textContent = 'Use only one option: enter a new group title or select an existing group.';
            errorEl.classList.remove('hidden');
        }
        return;
    }

    const checkedRows = Array.from(document.querySelectorAll('#modal-invoices-tbody .invoice-check:checked'));
    const items = selectedInvoiceRowsInClickOrder().filter(tr => tr && tr.dataset.sourceType && tr.dataset.sourceId && tr.dataset.invoiceNo)
        .map(tr => ({
            source_type: tr.dataset.sourceType,
            source_id: Number(tr.dataset.sourceId),
            invoice_no: tr.dataset.invoiceNo,
            remarks: tr.querySelector('.remarks-input')?.value?.trim() || ''
        }));

    if (!items.length) {
        showNotification('Select at least one invoice to group.', 'error');
        return;
    }

    const isAppend = !!selectedGroupId;
    if (createBtn) {
        createBtn.disabled = true;
        createBtn.textContent = isAppend ? 'Adding...' : 'Creating...';
    }

    try {
        let response;
        if (isAppend) {
            response = await fetch(routes().groupsItems.replace(':groupId', selectedGroupId), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify({ items })
            });
        } else {
            response = await fetch(routes().groupsStore, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify({ title, items })
            });
        }
        const data = await response.json();

        if (!data.success) {
            showNotification(data.message || 'Failed to process group request.', 'error');
            if (errorEl) {
                errorEl.textContent = data.message || 'Failed to process group request.';
                errorEl.classList.remove('hidden');
            }
            return;
        }

        toggleModal('payment-group-modal', false);
        if (isAppend) {
            showNotification(`Group updated: ${data.added} new invoice(s), ${data.skipped} existing invoice(s) rechecked, ${Number(data.payments_created || 0)} payment record(s) created.`, 'success');
        } else {
            showNotification(`Invoice group created and paid successfully${(data.payment_nos || []).length ? ': ' + data.payment_nos.join(', ') : '.'}`, 'success');
        }
        groupsHasLoaded = false;
        groupsPage = 1;
        if (currentPaymentsTab === 'groups') {
            groupsHasLoaded = true;
            loadGroupsTable();
        }
        checkedRows.forEach(cb => { cb.checked = false; });
        invoiceSelectionOrder.clear();
        invoiceSelectionSequence = 0;
        updateSelectedCount();
        paymentsPage = 1;
        loadPaymentsTable();
        historyHasLoaded = false;
        payorsHistoryHasLoaded = false;
        if (typeof lucide !== 'undefined') lucide.createIcons();
    } catch (error) {
        showNotification(error.message || 'Failed to process group request.', 'error');
    } finally {
        if (createBtn) {
            createBtn.disabled = false;
            updateGroupButton();
        }
    }
};

function existingGroupLabel(group = {}) {
    return `${group.title || 'Untitled Group'} — ${group.total_invoices ?? 0} invoice${Number(group.total_invoices ?? 0) !== 1 ? 's' : ''} — ${money.format(Number(group.total_amount ?? 0))} — ${group.grouped_date || '---'} — ${group.status || '---'}`;
}

function renderExistingGroupOptions(filter = '') {
    const panel = document.getElementById('payment-existing-group-options');
    if (!panel) return;
    const query = String(filter || '').trim().toLowerCase();
    const rows = existingGroupOptions.filter(group => {
        const haystack = `${existingGroupLabel(group)} ${group.id ?? ''}`.toLowerCase();
        return !query || haystack.includes(query);
    });

    panel.innerHTML = rows.length ? rows.map(group => `
        <button type="button" onclick="window.selectExistingGroup(${Number(group.id)})" class="w-full px-3 py-2.5 text-left hover:bg-maroon/5 border-b border-slate-100 last:border-b-0">
            <span class="block text-[10px] font-black text-slate-700">${escapeHtml(group.title || 'Untitled Group')}</span>
            <span class="mt-0.5 block text-[9px] font-semibold text-slate-400">${escapeHtml(`${group.total_invoices ?? 0} invoice${Number(group.total_invoices ?? 0) !== 1 ? 's' : ''} • ${money.format(Number(group.total_amount ?? 0))} • ${group.grouped_date || '---'} • ${group.status || '---'}`)}</span>
        </button>`).join('') : '<div class="px-3 py-3 text-[10px] font-bold text-slate-400">No matching groups.</div>';
}

window.openExistingGroupDropdown = function() {
    const panel = document.getElementById('payment-existing-group-options');
    if (!panel) return;
    renderExistingGroupOptions(document.getElementById('payment-existing-group-search')?.value || '');
    panel.classList.remove('hidden');
};

window.toggleExistingGroupDropdown = function() {
    const panel = document.getElementById('payment-existing-group-options');
    if (!panel) return;
    if (panel.classList.contains('hidden')) window.openExistingGroupDropdown();
    else panel.classList.add('hidden');
};

window.filterExistingGroupOptions = function(value) {
    const groupSelect = document.getElementById('payment-existing-group-select');
    if (groupSelect) groupSelect.value = '';
    renderExistingGroupOptions(value);
    document.getElementById('payment-existing-group-options')?.classList.remove('hidden');
    window.updateGroupButton();
};

window.selectExistingGroup = function(groupId) {
    const group = existingGroupOptions.find(row => Number(row.id) === Number(groupId));
    if (!group) return;
    const groupSelect = document.getElementById('payment-existing-group-select');
    const searchInput = document.getElementById('payment-existing-group-search');
    if (groupSelect) groupSelect.value = String(group.id);
    if (searchInput) searchInput.value = existingGroupLabel(group);
    document.getElementById('payment-existing-group-options')?.classList.add('hidden');
    window.updateGroupButton();
};

window.loadExistingGroupOptions = async function() {
    const groupSelect = document.getElementById('payment-existing-group-select');
    const panel = document.getElementById('payment-existing-group-options');
    if (!groupSelect || !panel) return;

    panel.innerHTML = '<div class="px-3 py-3 text-[10px] font-bold text-slate-400">Loading all groups...</div>';
    try {
        const allGroups = [];
        let page = 1;
        let lastPage = 1;
        do {
            const response = await fetch(`${routes().groupsData}?search=&activity=all&page=${page}`, {
                method: 'GET',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() }
            });
            const data = await response.json();
            if (!data.success) throw new Error(data.message || 'Failed to load groups.');
            allGroups.push(...(data.rows || data.groups || data.data || []));
            lastPage = Math.max(1, Number(data.last_page || 1));
            page += 1;
        } while (page <= lastPage);

        existingGroupOptions = allGroups;
        groupSelect.innerHTML = '<option value="">Select an existing group</option>' + existingGroupOptions.map(group =>
            `<option value="${Number(group.id)}">${escapeHtml(existingGroupLabel(group))}</option>`
        ).join('');
        renderExistingGroupOptions(document.getElementById('payment-existing-group-search')?.value || '');
    } catch (error) {
        existingGroupOptions = [];
        groupSelect.innerHTML = '<option value="">Select an existing group</option>';
        panel.innerHTML = `<div class="px-3 py-3 text-[10px] font-bold text-red-500">${escapeHtml(error.message || 'Unable to load groups.')}</div>`;
    }
};

window.updateGroupButton = function() {
    const titleEl = document.getElementById('payment-group-title');
    const groupSelect = document.getElementById('payment-existing-group-select');
    const createBtn = document.getElementById('payment-group-create-btn');
    if (!createBtn) return;
    const title = (titleEl?.value || '').trim();
    const selected = !!(groupSelect && groupSelect.value);
    if (title && !selected) {
        createBtn.textContent = 'Create Group & Pay';
    } else if (!title && selected) {
        createBtn.textContent = 'Add to Group & Pay';
    } else {
        createBtn.textContent = 'Save Group';
    }
};

window.openProcessGroupModal = async function(groupId) {
    currentGroupId = groupId;
    currentGroupItems = [];
    const titleEl = document.getElementById('process-group-title');
    const subtitleEl = document.getElementById('process-group-subtitle');
    const tbody = document.getElementById('process-group-tbody');
    const printBtn = document.getElementById('process-group-print-selected-btn');
    if (printBtn) printBtn.disabled = true;
    if (titleEl) titleEl.textContent = 'Loading group...';
    if (subtitleEl) subtitleEl.textContent = 'Paid invoice group';
    if (tbody) tbody.innerHTML = `<tr><td colspan="9" class="py-12 text-center text-slate-400 font-bold">Loading paid group details...</td></tr>`;
    toggleModal('payment-process-group-modal', true);

    try {
        const response = await fetch(routes().groupDetail.replace(':groupId', groupId), { headers: { 'Accept': 'application/json' } });
        const data = await response.json();
        if (!data.success) throw new Error(data.message || 'Failed to load group details.');
        const group = data.group || {};
        currentGroupItems = applySavedProcessGroupOrder(data.items || [], groupId);
        saveCurrentProcessGroupOrder();
        currentGroupPrintMeta = {
            online_percent: Number(group.online_percent || 0),
            online_payment: Number(group.online_payment || 0)
        };
        currentGroupPrintReturns = Array.isArray(data.returns) ? data.returns : [];
        if (titleEl) titleEl.textContent = group.title || `Group #${groupId}`;
        if (subtitleEl) {
            const dragHint = isRegularPaymentsContext() ? ' · drag invoice numbers to rearrange' : '';
            subtitleEl.textContent = `Grouped on ${group.grouped_date || '---'} · ${String(group.status || 'processed').replaceAll('_', ' ')}${dragHint}`;
        }
        renderProcessGroupRows(currentGroupItems);
    } catch (error) {
        if (tbody) tbody.innerHTML = `<tr><td colspan="9" class="py-12 text-center text-red-500 font-bold">${escapeHtml(error.message)}</td></tr>`;
        showNotification(error.message, 'error');
    }
};

function renderProcessGroupRows(items) {
    const tbody = document.getElementById('process-group-tbody');
    if (!tbody) return;
    const dragEnabled = isRegularPaymentsContext();
    tbody.innerHTML = items.length ? items.map((item, index) => `
        <tr class="hover:bg-slate-50 border-b border-slate-100 ${dragEnabled ? 'transition-shadow' : ''}" data-index="${index}" data-item-id="${Number(item.id || 0)}" ${dragEnabled ? `ondragover="window.overProcessGroupDrag(event, ${Number(item.id || 0)}, this)" ondrop="window.dropProcessGroupDrag(event, ${Number(item.id || 0)}, this)"` : ''}>
            <td class="p-4 text-center"><input type="checkbox" class="process-group-check accent-[#800000]" data-index="${index}" checked></td>
            <td class="p-4 font-black text-maroon">${dragEnabled ? `
                <div draggable="true" ondragstart="window.beginProcessGroupDrag(event, ${Number(item.id || 0)})" ondragend="window.endProcessGroupDrag()" class="inline-flex items-center gap-2 cursor-grab active:cursor-grabbing select-none" title="Hold and drag this invoice to change its placement">
                    <i data-lucide="grip-vertical" class="w-3.5 h-3.5 text-violet-600 shrink-0"></i>
                    <span>${escapeHtml(item.invoice_no || '---')}</span>
                </div>` : escapeHtml(item.invoice_no || '---')}</td>
            <td class="p-4 text-slate-500 whitespace-nowrap">${escapeHtml(item.invoice_date || item.date || '---')}</td>
            <td class="p-4 text-right font-bold text-slate-700">${money.format(Number(item.invoice_amount || 0))}</td>
            <td class="p-4 text-right font-bold text-amber-600">${money.format(Number(item.adjustment || 0))}</td>
            <td class="p-3 text-right">
                <input type="number" min="0" step="0.01"
                    class="process-group-paid-input w-28 px-2 py-1.5 border rounded-lg outline-none text-right font-black ${item.can_edit_paid_amount !== false ? 'bg-white border-slate-200 text-emerald-700 focus:border-maroon' : 'bg-slate-100 border-slate-200 text-slate-400 cursor-not-allowed'}"
                    value="${escapeAttr(Number(item.paid_amount || 0).toFixed(2))}"
                    data-saved-value="${escapeAttr(Number(item.paid_amount || 0).toFixed(2))}"
                    data-can-edit-paid="${item.can_edit_paid_amount !== false ? '1' : '0'}"
                    ${item.can_edit_paid_amount !== false ? '' : 'disabled title="No linked Process Payment record exists for this grouped invoice. Remarks can still be edited."'}
                    oninput="window.updateProcessGroupPaidDraft(this)"
                    onblur="window.autoSaveProcessGroupItem(this)"
                    onmouseleave="window.autoSaveProcessGroupItem(this)">
            </td>
            <td class="p-4 font-bold text-slate-600">${escapeHtml(item.payment_no || '---')}</td>
            <td class="p-3">
                <div class="flex items-center gap-2 min-w-[220px]">
                    <input type="text" class="process-group-remarks-input flex-1 min-w-0 px-2 py-1.5 bg-white border border-slate-200 rounded-lg outline-none text-slate-600 focus:border-maroon" value="${escapeAttr(item.remarks || '')}" data-saved-value="${escapeAttr(item.remarks || '')}" placeholder="Add remarks..." onblur="window.autoSaveProcessGroupItem(this)" onmouseleave="window.autoSaveProcessGroupItem(this)">
                    <span class="process-group-save-state text-[8px] font-black uppercase tracking-wider text-slate-300 whitespace-nowrap">Saved</span>
                </div>
            </td>
            <td class="p-3 text-center">
                <button type="button" onclick="window.deleteProcessGroupInvoice(${Number(item.id || 0)}, '${escapeAttr(item.invoice_no || '')}')" class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-maroon text-gold shadow-sm hover:bg-maroon-800" title="Delete invoice from group and make it unpaid"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
            </td>
        </tr>
    `).join('') : `<tr><td colspan="9" class="py-12 text-center text-slate-400">No invoices in this group.</td></tr>`;
    updateProcessGroupSummary();
    const selectAll = document.getElementById('process-group-select-all');
    if (selectAll) selectAll.checked = items.length > 0;
    window.filterProcessGroupTable();
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

window.deleteProcessGroupInvoice = async function(itemId, invoiceNo) {
    if (!currentGroupId || !itemId) return;
    const ok = window.confirm(`Delete invoice ${invoiceNo || ''} from this Group?\n\nIts Process Payment Invoice row will be deleted and the invoice will become unpaid so it can appear in Process Payment again.`);
    if (!ok) return;
    try {
        const template = routes().groupItemDelete;
        if (!template) throw new Error('Group invoice delete route is not configured.');
        const url = template.replace(':groupId', String(currentGroupId)).replace(':itemId', String(itemId));
        const res = await fetch(url, { method: 'DELETE', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() } });
        const data = await res.json();
        if (!res.ok || !data.success) throw new Error(data.message || 'Failed to delete Group invoice.');
        showNotification(`Invoice ${invoiceNo || ''} was removed and is unpaid again.`, 'success');
        await window.openProcessGroupModal(currentGroupId);
        groupsPage = 1;
        loadGroupsTable();
        paymentsPage = 1;
        loadPaymentsTable();
        paymentsHistoryPage = 1;
        loadPaymentsHistoryTable();
        if (currentPayorHistoryCustomerId && !document.getElementById('payor-history-modal')?.classList.contains('hidden')) loadPayorHistoryInvoices();
    } catch (error) {
        showNotification(error.message || 'Failed to delete Group invoice.', 'error');
    }
};

window.updateProcessGroupPaidDraft = function(input) {
    const row = input?.closest('tr[data-index]');
    if (!row) return;
    const index = Number(row.dataset.index || 0);
    const amount = Math.max(0, Number(input.value || 0));
    if (currentGroupItems[index]) currentGroupItems[index].paid_amount = amount;
    updateProcessGroupSummary();
};

window.autoSaveProcessGroupItem = async function(input) {
    const row = input?.closest('tr[data-item-id]');
    if (!row || !currentGroupId) return;

    const itemId = Number(row.dataset.itemId || 0);
    const index = Number(row.dataset.index || 0);
    const paidInput = row.querySelector('.process-group-paid-input');
    const remarksInput = row.querySelector('.process-group-remarks-input');
    const stateEl = row.querySelector('.process-group-save-state');
    if (!itemId || !paidInput || !remarksInput) return;

    const paidAmount = Math.max(0, Number(paidInput.value || 0));
    const remarks = remarksInput.value.trim();
    const savedPaid = Number(paidInput.dataset.savedValue || 0);
    const savedRemarks = remarksInput.dataset.savedValue || '';
    const canEditPaid = paidInput.dataset.canEditPaid !== '0' && !paidInput.disabled;
    const paidChanged = canEditPaid && Math.abs(paidAmount - savedPaid) >= 0.005;
    const remarksChanged = remarks !== savedRemarks;

    if (!paidChanged && !remarksChanged) return;
    if (row.dataset.saving === '1') {
        row.dataset.savePending = '1';
        return;
    }

    row.dataset.saving = '1';
    row.dataset.savePending = '0';
    if (stateEl) {
        stateEl.textContent = 'Saving...';
        stateEl.className = 'process-group-save-state text-[8px] font-black uppercase tracking-wider text-amber-500 whitespace-nowrap';
    }

    try {
        const routeTemplate = routes().groupItemUpdate;
        if (!routeTemplate) throw new Error('Group item update route is not configured.');
        const url = routeTemplate
            .replace(':groupId', String(currentGroupId))
            .replace(':itemId', String(itemId));
        const payload = {};
        if (paidChanged) payload.paid_amount = paidAmount;
        if (remarksChanged) payload.remarks = remarks;

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken()
            },
            body: JSON.stringify(payload)
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'Failed to save group invoice.');

        const saved = data.item || {};
        const normalizedPaid = Number(saved.paid_amount ?? paidAmount);
        const normalizedRemarks = String(saved.remarks ?? remarks);
        const currentPaidDraft = Math.max(0, Number(paidInput.value || 0));
        const currentRemarksDraft = remarksInput.value.trim();

        if (paidChanged) {
            paidInput.dataset.savedValue = normalizedPaid.toFixed(2);
            if (Math.abs(currentPaidDraft - paidAmount) < 0.005) paidInput.value = normalizedPaid.toFixed(2);
        }
        if (remarksChanged) {
            remarksInput.dataset.savedValue = normalizedRemarks;
            if (currentRemarksDraft === remarks) remarksInput.value = normalizedRemarks;
        }

        if (currentGroupItems[index]) {
            currentGroupItems[index].paid_amount = Math.abs(currentPaidDraft - paidAmount) < 0.005 ? normalizedPaid : currentPaidDraft;
            currentGroupItems[index].remarks = currentRemarksDraft === remarks ? normalizedRemarks : currentRemarksDraft;
        }
        updateProcessGroupSummary();

        const subtitleEl = document.getElementById('process-group-subtitle');
        if (subtitleEl && data.group_status) {
            const groupLabel = subtitleEl.textContent.split(' · ')[0] || 'Grouped';
            subtitleEl.textContent = `${groupLabel} · ${String(data.group_status).replaceAll('_', ' ')}`;
        }

        if (stateEl) {
            stateEl.textContent = 'Saved';
            stateEl.className = 'process-group-save-state text-[8px] font-black uppercase tracking-wider text-emerald-600 whitespace-nowrap';
        }
    } catch (error) {
        if (stateEl) {
            stateEl.textContent = 'Retry';
            stateEl.className = 'process-group-save-state text-[8px] font-black uppercase tracking-wider text-red-500 whitespace-nowrap';
        }
        showNotification(error.message || 'Failed to save group invoice.', 'error');
    } finally {
        row.dataset.saving = '0';
        if (row.dataset.savePending === '1') {
            row.dataset.savePending = '0';
            window.autoSaveProcessGroupItem(remarksInput);
        }
    }
};

window.updateProcessGroupSummary = function() {
    const checked = Array.from(document.querySelectorAll('#process-group-tbody .process-group-check:checked'));
    const totalInvoices = checked.reduce((sum, cb) => sum + Number(currentGroupItems[Number(cb.dataset.index || 0)]?.invoice_amount || 0), 0);
    const totalPaid = checked.reduce((sum, cb) => sum + Number(currentGroupItems[Number(cb.dataset.index || 0)]?.paid_amount || 0), 0);
    setText('process-group-selected-count', `${checked.length} of ${currentGroupItems.length} invoice${currentGroupItems.length !== 1 ? 's' : ''} selected`);
    const totalEl = document.getElementById('process-group-total');
    if (totalEl) totalEl.textContent = money.format(totalInvoices);
    const paidEl = document.getElementById('process-group-paid-total');
    if (paidEl) paidEl.textContent = money.format(totalPaid);
    const printBtn = document.getElementById('process-group-print-selected-btn');
    if (printBtn) printBtn.disabled = checked.length === 0;
};

function bindProcessGroupSelectAll() {
    const selectAll = document.getElementById('process-group-select-all');
    if (!selectAll) return;
    selectAll.addEventListener('change', () => {
        document.querySelectorAll('#process-group-tbody .process-group-check').forEach(cb => cb.checked = selectAll.checked);
        updateProcessGroupSummary();
    });
    document.addEventListener('change', event => {
        if (event.target.classList?.contains('process-group-check')) updateProcessGroupSummary();
    });
}

window.filterProcessGroupTable = function() {
    window.filterTableByColumns('process-group-tbody', 'process-group-filter-row');
};

function selectedGroupItemIds() {
    return Array.from(document.querySelectorAll('#process-group-tbody .process-group-check:checked'))
        .map(cb => Number(currentGroupItems[Number(cb.dataset.index || 0)]?.id || 0))
        .filter(id => id > 0);
}

window.printSelectedGroupReceipt = function() {
    const itemIds = selectedGroupItemIds();
    if (!itemIds.length) {
        showNotification('Select at least one paid invoice to print.', 'error');
        return;
    }
    const selectedSet = new Set(itemIds);
    const retotal = currentGroupItems.filter(item => selectedSet.has(Number(item.id)))
        .reduce((sum, item) => sum + Number(item.invoice_amount || 0), 0);
    const percentInput = document.getElementById('group-print-online-percent');
    const paymentInput = document.getElementById('group-print-online-payment');
    const retotalInput = document.getElementById('group-print-retotal');
    if (percentInput) percentInput.value = Number(currentGroupPrintMeta.online_percent || 0).toFixed(2);
    if (paymentInput) paymentInput.value = Number(currentGroupPrintMeta.online_payment || 0).toFixed(2);
    if (retotalInput) retotalInput.value = retotal.toFixed(2);
    renderManualReturns('group', currentGroupPrintReturns);
    setText('group-print-selected-count', `${itemIds.length} selected invoice${itemIds.length !== 1 ? 's' : ''}`);
    toggleModal('group-print-settings-modal', true);
};

window.submitGroupPrintSettings = async function() {
    const itemIds = selectedGroupItemIds();
    if (!itemIds.length) {
        showNotification('Select at least one paid invoice to print.', 'error');
        toggleModal('group-print-settings-modal', false);
        return;
    }
    const onlinePercent = Math.max(0, Math.min(100, Number(document.getElementById('group-print-online-percent')?.value || 0)));
    const onlinePayment = Number(document.getElementById('group-print-online-payment')?.value || 0);
    const manualReturns = collectManualReturns('group');
    const submitBtn = document.getElementById('group-print-submit-btn');
    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Preparing...'; }
    try {
        const response = await fetch(routes().groupPrint.replace(':groupId', currentGroupId), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify({
                item_ids: itemIds,
                online_percent: onlinePercent,
                online_payment: onlinePayment,
                // The current DB column is numeric. Numeric wallet values keep saving
                // normally; free-form text remains available for this print without
                // forcing a database/schema change.
                returns: serverSafeManualReturns(manualReturns)
            })
        });
        const data = await response.json();
        if (!data.success) throw new Error(data.message || 'Failed to prepare Collection Invoice.');
        const receipts = data.receipts || [];
        if (!receipts.length) throw new Error('No printable Collection Invoice was returned.');
        currentGroupPrintMeta = {
            online_percent: onlinePercent,
            online_payment: onlinePayment
        };
        // Keep exactly what the user typed for the immediate Collection Invoice print.
        currentGroupPrintReturns = manualReturns;
        toggleModal('group-print-settings-modal', false);

        // A Group can contain invoices posted through several Process Payment rows,
        // especially when new invoices are appended later. Printing each payment row
        // separately leaves the first Collection Invoice stuck on its old Re-Total.
        // Merge every selected invoice into one current Group print and recalculate the
        // amount from the selected invoices at print time.
        const combinedInvoices = receipts
            .flatMap(receipt => Array.isArray(receipt.invoices) ? receipt.invoices : [])
            .sort((a, b) => Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0));
        const selectedRetotal = combinedInvoices.reduce((sum, invoice) => sum + Number(invoice.invoice_amount || 0), 0);
        const primaryReceipt = receipts[0] || {};

        openPrintablePaymentLayout({
            customer: primaryReceipt.customer || {},
            collectionNo: primaryReceipt.collection_no || '---',
            paymentDate: primaryReceipt.payment_date || '---',
            invoices: combinedInvoices,
            returns: currentGroupPrintReturns,
            checks: [],
            isGroupPayment: true,
            groupOnlinePercent: onlinePercent,
            groupOnlinePayment: onlinePayment,
            groupRetotalAmount: selectedRetotal
        });
    } catch (error) {
        showNotification(error.message || 'Failed to print selected invoices.', 'error');
    } finally {
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Print'; }
    }
};

function splitLegacyGroupOnlinePayment(invoice, onlinePercent) {
    const percent = Math.max(0, Math.min(100, Number(onlinePercent || 0)));
    const rawAdjustment = Math.max(0, Number(invoice.adjustment || 0));
    const paidAmount = Math.max(0, Number(invoice.paid_amount || 0));

    if (percent <= 0 || rawAdjustment <= 0) {
        return { adjustment: rawAdjustment, online_payment: 0 };
    }

    let onlinePayment = 0;
    if (percent >= 100) {
        // Legacy 100% group processing stored the whole online payment inside
        // Adjustment and left Paid at zero. Keep it out of the Adjustment column.
        onlinePayment = rawAdjustment;
    } else {
        // Legacy group processing used:
        // paid_after = paid_before * (1 - percent)
        // online_payment = paid_before * percent
        // therefore online_payment = paid_after * percent / (100 - percent).
        onlinePayment = Math.round((paidAmount * percent / (100 - percent)) * 100) / 100;
        onlinePayment = Math.min(onlinePayment, rawAdjustment);
    }

    return {
        adjustment: Math.max(Math.round((rawAdjustment - onlinePayment) * 100) / 100, 0),
        online_payment: Math.max(Math.round(onlinePayment * 100) / 100, 0)
    };
}

function paymentDetailToPrintable(detail) {
    const newGroupFlow = !!detail.group_discount?.is_new_group_flow || !!detail.payment?.payment_invoice_group_id;
    const legacyPercent = Math.max(0, Math.min(100, Number(detail.group_discount?.discount_percent || 0)));
    const legacyEmbedded = !newGroupFlow && legacyPercent > 0;
    return {
        customer: detail.customer || {},
        collectionNo: detail.payment?.payment_no || '---',
        paymentDate: detail.payment?.payment_date || '---',
        invoices: (detail.invoices || []).map(inv => {
            const normalized = { ...inv, date: inv.date || '', invoice_amount: Number(inv.invoice_amount || inv.amount || 0), due_amount: Number(inv.due_amount || inv.due || 0), paid_amount: Number(inv.paid_amount || 0), adjustment: Number(inv.adjustment || 0) };
            if (legacyEmbedded) {
                const split = splitLegacyGroupOnlinePayment(normalized, legacyPercent);
                normalized.adjustment = split.adjustment;
                normalized.online_payment = split.online_payment;
            }
            return normalized;
        }),
        checks: detail.checks || [],
        returns: detail.returns || [],
        isGroupPayment: newGroupFlow,
        groupOnlinePercent: newGroupFlow ? Number(detail.payment?.online_percent ?? detail.group_discount?.online_percent ?? 0) : legacyPercent,
        groupOnlinePayment: newGroupFlow ? Number(detail.payment?.online_payment ?? detail.group_discount?.online_payment ?? 0) : Number(detail.group_discount?.discount_amount || 0),
        groupRetotalAmount: newGroupFlow ? Number(detail.payment?.retotal_amount ?? detail.group_discount?.retotal_amount ?? 0) : null,
        groupDiscountPercent: legacyPercent,
        groupDiscountEmbeddedInAdjustments: legacyEmbedded
    };
}

function renderProcessedGroupReview(details = []) {
    const panel = document.getElementById('process-group-review-panel');
    const content = document.getElementById('process-group-review-content');
    const printBtn = document.getElementById('process-group-print-btn');
    const processBtn = document.getElementById('process-group-process-btn');
    const tableWrap = document.getElementById('process-group-table-wrap');
    if (!panel || !content) return;

    tableWrap?.classList.add('hidden');
    panel.classList.remove('hidden');
    processBtn?.classList.add('hidden');
    printBtn?.classList.toggle('hidden', details.length === 0);

    if (!details.length) {
        content.innerHTML = '<div class="md:col-span-2 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-xs font-bold text-amber-700">Payment was posted, but the saved receipt detail could not be loaded. Please use Payment History to print the receipt.</div>';
        return;
    }

    content.innerHTML = details.map(detail => {
        const printable = paymentDetailToPrintable(detail);
        const totalPaid = printable.invoices.reduce((sum, inv) => sum + Number(inv.paid_amount || 0), 0);
        const totalDue = printable.invoices.reduce((sum, inv) => sum + Number(inv.due_amount || 0), 0);
        const totalAdjustment = printable.invoices.reduce((sum, inv) => sum + Number(inv.adjustment || 0), 0);
        const totalReturns = printable.returns.reduce((sum, ret) => sum + Number(ret.return_amount || 0), 0);
        return `
            <div class="md:col-span-2 overflow-hidden rounded-2xl border border-maroon/10 bg-white shadow-sm">
                <div class="bg-maroon p-6 text-white">
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.25em] text-gold">Collection Review</p>
                            <h5 class="mt-2 text-2xl font-black tracking-tight">${escapeHtml(printable.customer.name || detail.payment?.customer_name || '---')}</h5>
                            <p class="mt-2 max-w-2xl text-xs font-semibold text-white/60">${escapeHtml(printable.customer.address || 'No address on file')}</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/10 px-5 py-4 text-right">
                            <p class="text-[9px] font-black uppercase tracking-widest text-white/50">Collection No.</p>
                            <p class="mt-1 text-lg font-black text-gold">${escapeHtml(printable.collectionNo)}</p>
                            <p class="mt-2 text-[10px] font-bold text-white/60">${escapeHtml(printable.paymentDate)}</p>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-5 divide-y md:divide-y-0 md:divide-x divide-slate-100 bg-slate-50">
                    <div class="p-4"><p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Invoices</p><p class="mt-1 text-xl font-black text-slate-800">${printable.invoices.length}</p></div>
                    <div class="p-4"><p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Total Returns</p><p class="mt-1 text-xl font-black text-slate-800">${money.format(totalReturns)}</p></div>
                    <div class="p-4"><p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Amount Due</p><p class="mt-1 text-xl font-black text-amber-600">${money.format(totalDue)}</p></div>
                    <div class="p-4"><p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Adjustment</p><p class="mt-1 text-xl font-black text-slate-800">${money.format(totalAdjustment)}</p></div>
                    <div class="p-4"><p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Amount Paid</p><p class="mt-1 text-xl font-black text-maroon">${money.format(totalPaid)}</p></div>
                </div>
            </div>
            <div class="md:col-span-2 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <h5 class="text-[10px] font-black text-maroon uppercase tracking-widest">Transaction Details</h5>
                    <span class="rounded-full bg-maroon/5 px-3 py-1 text-[9px] font-black uppercase tracking-widest text-maroon">${printable.invoices.length} Posted</span>
                </div>
                <div class="mt-4 overflow-x-auto rounded-xl border border-slate-100">
                    <table class="w-full min-w-[760px] text-left text-[10px]">
                        <thead class="bg-slate-50 text-slate-400 uppercase tracking-widest">
                            <tr>
                                <th class="p-3">Invoice No.</th>
                                <th class="p-3">Date</th>
                                <th class="p-3 text-right">Invc. Amount</th>
                                <th class="p-3 text-right">Amt Due</th>
                                <th class="p-3 text-right">Adjustment</th>
                                <th class="p-3 text-right">Amt. Paid</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            ${printable.invoices.map(inv => `
                                <tr>
                                    <td class="p-3 font-black text-maroon">${escapeHtml(inv.invoice_no || '---')}</td>
                                    <td class="p-3 font-bold text-slate-500">${escapeHtml(inv.date || '---')}</td>
                                    <td class="p-3 text-right font-bold">${money.format(inv.invoice_amount)}</td>
                                    <td class="p-3 text-right font-bold text-amber-600">${money.format(inv.due_amount)}</td>
                                    <td class="p-3 text-right font-bold">${money.format(inv.adjustment)}</td>
                                    <td class="p-3 text-right font-black text-maroon">${money.format(inv.paid_amount)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }).join('');
}

window.printProcessedGroupReceipt = function() {
    if (!currentProcessedGroupReceipts.length) {
        showNotification('No processed payment receipt is available yet.', 'error');
        return;
    }

    currentProcessedGroupReceipts.forEach(detail => {
        openPrintablePaymentLayout(paymentDetailToPrintable(detail));
    });
};

window.changePaymentsPage = function(direction) {
    const nextPage = paymentsPage + direction;
    if (nextPage < 1 || nextPage > paymentsLastPage) return;
    paymentsPage = nextPage;
    loadPaymentsTable();
};

function updatePaymentsHistoryPagination(data = {}) {
    paymentsHistoryPage = Number(data.page || paymentsHistoryPage || 1);
    paymentsHistoryLastPage = Number(data.last_page || 1);

    const total = Number(data.total || 0);
    const perPage = Number(data.per_page || 50);
    const from = total ? ((paymentsHistoryPage - 1) * perPage) + 1 : 0;
    const to = total ? Math.min(paymentsHistoryPage * perPage, total) : 0;

    setText('payments-history-page-info', `Showing ${from}-${to} of ${total} payors`);
    const prev = document.getElementById('payments-history-prev-btn');
    const next = document.getElementById('payments-history-next-btn');
    if (prev) prev.disabled = paymentsHistoryPage <= 1;
    if (next) next.disabled = paymentsHistoryPage >= paymentsHistoryLastPage;
}

window.changePaymentsHistoryPage = function(direction) {
    const nextPage = paymentsHistoryPage + direction;
    if (nextPage < 1 || nextPage > paymentsHistoryLastPage) return;
    paymentsHistoryPage = nextPage;
    loadPaymentsHistoryTable();
};

function updateAgingStats(stats = {}) {
    setText('stat-30-days', `${Number(stats?.['30'] || 0)} Payors`);
    setText('stat-60-days', `${Number(stats?.['60'] || 0)} Payors`);
    setText('stat-90-days', `${Number(stats?.['90'] || 0)} Payors`);
    setText('stat-120-days', `${Number(stats?.['120'] || 0)} Payors`);
    setText('stat-150-days', `${Number(stats?.['150'] || 0)} Payors`);
}

function searchableCellText(cell) {
    if (!cell) return '';
    const values = Array.from(cell.querySelectorAll('input, select, textarea'))
        .map(el => el.value || '')
        .join(' ');
    return `${cell.innerText || cell.textContent || ''} ${values}`.toLowerCase().replace(/[₱$,]/g, '');
}

window.filterTableByColumns = function(tbodyId, filterRowId) {
    const tbody = document.getElementById(tbodyId);
    const filterRow = document.getElementById(filterRowId);
    if (!tbody || !filterRow) return;

    const filters = Array.from(filterRow.querySelectorAll('input[data-col]'))
        .map(input => ({ col: Number(input.dataset.col), value: input.value.trim().toLowerCase().replace(/[₱$,]/g, '') }))
        .filter(filter => filter.value !== '');

    Array.from(tbody.querySelectorAll('tr')).forEach(row => {
        const cells = row.children;
        const matches = filters.every(filter => searchableCellText(cells[filter.col]).includes(filter.value));
        row.style.display = matches ? '' : 'none';
    });
};

window.filterPaymentsTable = function() {
    window.filterTableByColumns('payments-tbody', 'payments-active-filter-row');
};

window.filterPaymentsHistoryTable = function() {
    const input = document.getElementById('payments-history-payor-search');
    paymentsHistorySearch = input ? input.value.trim() : '';
    paymentsHistoryPage = 1;
    clearTimeout(window.__paymentsHistoryFilterTimer);
    window.__paymentsHistoryFilterTimer = setTimeout(() => loadPaymentsHistoryTable(), 220);
};

window.toggleModal = function(id, show) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.toggle('hidden', !show);
    if (show) {
        document.body.style.overflow = 'hidden';
        if (id === 'proceed-modal') resetProceedModal();
    } else {
        document.body.style.overflow = 'auto';
    }
};

window.resetProceedModal = function() {
    currentStep = 1;
    maxSteps = 4;
    const srIndicator = document.querySelector('.sudden-returns-step-indicator');
    if (srIndicator) srIndicator.classList.remove('hidden');
    clearInvoiceFilters();
    window.switchProcessLeftTab('current', false);
    showStep(1);
};

window.openProceedModal = async function(customerId) {
    setProcessPaymentMode('create');
    toggleModal('proceed-modal', true);
    setText('p-payor-name', 'Loading...');
    setText('p-contact', '---');
    setText('p-address', '---');
    setText('p-payment-no', '---');
    setText('p-date', new Date().toISOString().split('T')[0]);
    setText('p-total-payment', money.format(0));
    renderCheckRows([]);

    const tbody = document.getElementById('modal-invoices-tbody');
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="10" class="p-8 text-center text-slate-400 font-bold">Loading invoices...</td></tr>`;
    }

    try {
        const url = routes().customer.replace(':customerId', customerId);
        const res = await fetch(url);
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Unable to load customer payment data.');

        currentPayment = {
            customer: data.customer,
            banks: data.banks || [],
            invoices: data.invoices || [],
            checks: [],
            returns: [],
            paymentNo: data.payment_no,
            termsDays: data.terms_days === null || data.terms_days === undefined || data.terms_days === ''
                ? null
                : Number(data.terms_days),
            mode: 'create',
            paymentId: null
        };

        setText('p-payor-name', data.customer.name);
        setText('p-contact', data.customer.contact_number || data.customer.contact_person || '---');
        setText('p-address', data.customer.address || '---');
        setText('p-payment-no', data.payment_no);
        setText('p-date', new Date().toISOString().split('T')[0]);

        renderInvoiceRows(currentPayment.invoices);
        renderCheckRows([]);
        fillBankDetails();
        calculateStep1Total();

        currentPayment.returns = [];
        maxSteps = 4;
        const srIndicator = document.querySelector('.sudden-returns-step-indicator');
        if (srIndicator) srIndicator.classList.remove('hidden');
        showStep(1);
    } catch (error) {
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="10" class="p-8 text-center text-red-500 font-bold">${escapeHtml(error.message)}</td></tr>`;
        }
    }
};

window.openEditPaymentModal = async function(paymentId) {
    setProcessPaymentMode('edit');
    if (!document.getElementById('payor-history-modal')?.classList.contains('hidden')) {
        toggleModal('payor-history-modal', false);
    }
    toggleModal('proceed-modal', true);
    setText('p-payor-name', 'Loading...');
    setText('p-contact', '---');
    setText('p-address', '---');
    setText('p-payment-no', '---');
    setText('p-date', '---');
    setText('p-total-payment', money.format(0));
    renderCheckRows([]);

    const tbody = document.getElementById('modal-invoices-tbody');
    if (tbody) tbody.innerHTML = `<tr><td colspan="10" class="p-8 text-center text-slate-400 font-bold">Loading saved payment and available invoices...</td></tr>`;

    try {
        const detail = await fetchPaymentHistoryDetail(paymentId);
        const customerId = Number(detail.customer?.id || detail.payment?.customer_id || 0);
        let available = [];
        if (customerId) {
            const openRes = await fetch(routes().customer.replace(':customerId', customerId), { headers: { 'Accept': 'application/json' } });
            const openData = await openRes.json();
            if (openRes.ok && openData.success) available = openData.invoices || [];
        }

        const saved = (detail.invoices || []).map(inv => ({
            ...inv,
            amount: Number(inv.invoice_amount ?? inv.amount ?? 0),
            due: Number(inv.due_amount ?? inv.due ?? 0),
            invoice_date: inv.invoice_date || inv.date_created || inv.date || '',
            is_selected: true
        }));
        const keyOf = inv => `${inv.source_type || ''}:${Number(inv.source_id || 0)}:${String(inv.invoice_no || '').replace(/\s+/g, '').toUpperCase()}`;
        const merged = new Map(saved.map(inv => [keyOf(inv), inv]));
        available.forEach(inv => {
            const key = keyOf(inv);
            if (!merged.has(key)) merged.set(key, { ...inv, paid_amount: 0, is_selected: false });
        });

        currentPayment = {
            customer: detail.customer,
            banks: detail.banks || [],
            invoices: Array.from(merged.values()),
            checks: detail.checks || [],
            returns: detail.returns || [],
            paymentNo: detail.payment?.payment_no || '---',
            termsDays: detail.terms_days === null || detail.terms_days === undefined || detail.terms_days === '' ? null : Number(detail.terms_days),
            mode: 'edit',
            paymentId
        };

        setText('p-payor-name', detail.customer?.name || detail.payment?.customer_name || '---');
        setText('p-contact', detail.customer?.contact_number || detail.customer?.contact_person || '---');
        setText('p-address', detail.customer?.address || '---');
        setText('p-payment-no', detail.payment?.payment_no || '---');
        setText('p-date', detail.payment?.payment_date || '---');

        renderInvoiceRows(currentPayment.invoices);
        renderCheckRows(currentPayment.checks);
        fillBankDetails();
        calculateStep1Total();
        window.switchProcessLeftTab('current', false);

        currentPayment.returns = [];
        maxSteps = 4;
        document.querySelector('.sudden-returns-step-indicator')?.classList.remove('hidden');
        showStep(1);
    } catch (error) {
        if (tbody) tbody.innerHTML = `<tr><td colspan="10" class="p-8 text-center text-red-500 font-bold">${escapeHtml(error.message)}</td></tr>`;
    }
};

function renderInvoiceRows(invoices) {
    const tbody = document.getElementById('modal-invoices-tbody');
    if (!tbody) return;
    invoiceSelectionOrder = new Map();
    invoiceSelectionSequence = 0;

    if (!invoices.length) {
        tbody.innerHTML = `<tr><td colspan="10" class="p-8 text-center text-slate-400 font-bold">No unpaid invoices found for this customer.</td></tr>`;
        return;
    }

    tbody.innerHTML = invoices.map(inv => {
        const returned = Number(inv.returned_amount || 0);
        const returnedQty = Number(inv.returned_qty || 0);
        const adjValue = Number(inv.adjustment || 0) > 0 ? Number(inv.adjustment) : (returned > 0 ? returned : 0);
        const sourceBadge = inv.source_type === 'online_report'
            ? '<span style="background:#f3e8ff;color:#7c3aed;border:1px solid #d8b4fe;" class="inline-block text-[10px] font-bold uppercase rounded-full px-1.5 py-0.5 mb-0.5">ONLINE</span>'
            : '<span style="background:#f1f5f9;color:#64748b;border:1px solid #cbd5e1;" class="inline-block text-[10px] font-bold uppercase rounded-full px-1.5 py-0.5 mb-0.5">LOCAL</span>';
        return `
        <tr class="${invoiceRowClass(inv)}"
            data-source-type="${escapeAttr(inv.source_type)}"
            data-source-id="${escapeAttr(inv.source_id)}"
            data-invoice-no="${escapeAttr(inv.invoice_no)}"
            data-po-no="${escapeAttr(inv.po_no || '')}"
            data-date="${escapeAttr(inv.invoice_date || inv.date || '')}"
            data-amount="${Number(inv.amount) || 0}"
            data-due="${Number(inv.due) || 0}"
            data-returned-amount="${returned}"
            data-returned-qty="${returnedQty}"
            data-terms-days="${escapeAttr(inv.terms_days ?? '')}"
            data-age-days="${escapeAttr(inv.age_days ?? '')}"
            data-overdue="${inv.has_exceeded_terms ? '1' : '0'}"
            data-overdue-days="${escapeAttr(inv.overdue_days ?? 0)}">
            <td class="p-4 text-center"><input type="checkbox" class="accent-maroon invoice-check" ${inv.paid_amount > 0 || inv.is_selected ? 'checked' : ''}></td>
            <td class="p-4 font-bold ${inv.has_exceeded_terms ? 'text-red-700' : 'text-slate-500'}">${escapeHtml(inv.po_no || '---')}</td>
            <td class="p-4 font-bold ${inv.has_exceeded_terms ? 'text-red-700' : 'text-maroon'}">
                <div class="flex flex-col">
                    ${sourceBadge}
                    <span>${escapeHtml(inv.invoice_no || '---')}</span>
                    ${renderOverdueMeta(inv)}
                </div>
            </td>
            <td class="p-4 font-bold text-slate-500 whitespace-nowrap">${escapeHtml(inv.invoice_date || inv.date || '---')}</td>
            <td class="p-4 font-bold">${money.format(Number(inv.amount) || 0)}</td>
            <td class="p-4 font-bold ${inv.has_exceeded_terms ? 'text-red-600' : 'text-amber-600'}"><span class="due-amount">${money.format(Number(inv.due) || 0)}</span></td>
            <td class="p-4">
                <div class="flex items-center gap-1">
                    <span class="font-bold text-slate-600">${returnedQty}</span>
                </div>
            </td>
            <td class="p-4"><input type="number" class="adj-input w-20 px-2 py-1 bg-slate-50 border border-slate-100 rounded outline-none text-center" value="${escapeAttr(adjValue.toFixed(2))}"></td>
            <td class="p-4"><input type="number" oninput="calculateStep1Total()" class="w-24 px-2 py-1 bg-white border border-slate-200 rounded outline-none text-center font-bold paid-input" placeholder="0.00" value="${inv.paid_amount > 0 ? escapeAttr(Number(inv.paid_amount).toFixed(2)) : ''}"></td>
            <td class="p-4"><input type="text" class="remarks-input w-full px-2 py-1 bg-slate-50 border border-slate-100 rounded outline-none" placeholder="..." value="${escapeAttr(inv.remarks || '')}"></td>
        </tr>`;
    }).join('');

    document.querySelectorAll('#modal-invoices-tbody .invoice-check').forEach(check => {
        if (check.checked) recordInvoiceSelection(check);
        check.addEventListener('change', () => {
            const row = check.closest('tr');
            if (check.checked && currentPayment.mode === 'edit') {
                const paidInput = row?.querySelector('.paid-input');
                if (paidInput && Number(paidInput.value || 0) <= 0) {
                    paidInput.value = Math.max(0, Number(row?.dataset.due || 0)).toFixed(2);
                }
            }
            recordInvoiceSelection(check);
            updateSelectedCount();
            calculateStep1Total();
            if (check.checked && row) window.showSelectedInvoiceTab(row);
        });
    });
    document.querySelectorAll('#modal-invoices-tbody .paid-input, #modal-invoices-tbody .remarks-input').forEach(input => {
        input.addEventListener('input', renderSelectedInvoiceTrail);
    });
    renderSelectedInvoiceTrail();
    updateSelectedCount();
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function bindInvoiceHeaderCheckbox() {
    document.addEventListener('change', event => {
        if (event.target?.id !== 'select-all-invoices') return;

        document.querySelectorAll('#modal-invoices-tbody .invoice-check').forEach(check => {
            if (check.closest('tr').style.display !== 'none') {
                check.checked = event.target.checked;
                recordInvoiceSelection(check);
            }
        });
        updateSelectedCount();
        calculateStep1Total();
        const firstSelected = document.querySelector('#modal-invoices-tbody .invoice-check:checked')?.closest('tr');
        if (firstSelected) window.showSelectedInvoiceTab(firstSelected);
    });
}

function updateSelectedCount() {
    const totalSelected = document.querySelectorAll('#modal-invoices-tbody .invoice-check:checked').length;
    setText('p-selected-count', String(totalSelected));

    const allChecks = document.querySelectorAll('#modal-invoices-tbody .invoice-check');
    const visibleChecks = Array.from(allChecks).filter(c => c.closest('tr').style.display !== 'none');
    const visibleSelected = visibleChecks.filter(c => c.checked).length;

    const header = document.getElementById('select-all-invoices');
    if (header) {
        header.checked = visibleChecks.length > 0 && visibleSelected === visibleChecks.length;
        header.indeterminate = visibleSelected > 0 && visibleSelected < visibleChecks.length;
    }

    calculateStep1Total();
}

let invoiceFilterTimer;

function applyInvoiceFilters() {
    const filters = {};
    document.querySelectorAll('#proceed-modal thead .invoice-filter-input[data-col]').forEach(input => {
        const val = input.value.trim().toLowerCase();
        if (val) filters[input.dataset.col] = val;
    });

    const hasFilters = Object.keys(filters).length > 0;
    const clearBtn = document.getElementById('btn-clear-invoice-filters');
    if (clearBtn) clearBtn.classList.toggle('hidden', !hasFilters);

    document.querySelectorAll('#modal-invoices-tbody tr').forEach(row => {
        if (!hasFilters) {
            row.style.display = '';
            return;
        }
        const cells = row.querySelectorAll('td');
        for (const [col, filterVal] of Object.entries(filters)) {
            const cell = cells[parseInt(col)];
            if (!cell) continue;
            const inputValues = Array.from(cell.querySelectorAll('input, select, textarea'))
                .map(el => el.value || '')
                .join(' ');
            const text = `${cell.textContent} ${inputValues}`.toLowerCase().replace(/[₱$,]/g, '');
            if (!text.includes(filterVal)) {
                row.style.display = 'none';
                return;
            }
        }
        row.style.display = '';
    });

    updateSelectedCount();
}

window.clearInvoiceFilters = function() {
    document.querySelectorAll('#proceed-modal thead .invoice-filter-input[data-col]').forEach(input => input.value = '');
    applyInvoiceFilters();
};

document.addEventListener('input', function(e) {
    if (e.target.classList.contains('invoice-filter-input') && e.target.dataset.col !== undefined) {
        clearTimeout(invoiceFilterTimer);
        invoiceFilterTimer = setTimeout(applyInvoiceFilters, 200);
    }
});

function fillBankDetails() {
    const bank = currentPayment.banks[0] || {};
    document.querySelectorAll('.check-row').forEach(row => {
        if (!row.dataset.bankAccountId) {
            row.dataset.bankAccountId = bank.id || '';
        }
        const bankInput = row.querySelector('.bank-name-input');
        const accountInput = row.querySelector('.account-no-input');
        if (bankInput && !bankInput.value) bankInput.value = bank.bank_name || '';
        if (accountInput && !accountInput.value) accountInput.value = bank.account_number || '';
    });
}

window.markAllSelectedAsPaid = function() {
    document.querySelectorAll('#modal-invoices-tbody tr').forEach(row => {
        const checkbox = row.querySelector('.invoice-check');
        if (checkbox?.checked) {
            const paidInput = row.querySelector('.paid-input');
            if (paidInput) paidInput.value = Number(row.dataset.due || 0).toFixed(2);
        }
    });
    calculateStep1Total();
};

window.calculateStep1Total = function() {
    let total = 0;
    document.querySelectorAll('#modal-invoices-tbody tr').forEach(row => {
        const checked = row.querySelector('.invoice-check')?.checked;
        if (!checked) return;
        total += parseFloat(row.querySelector('.paid-input')?.value || 0) || 0;
    });
    setText('p-total-payment', money.format(total));
    renderSelectedInvoiceTrail();
};

window.changeStep = function(dir) {
    const nextStep = currentStep + dir;
    if (nextStep < 1 || nextStep > maxSteps) return;

    if (nextStep === 2 && selectedInvoicePayload().length === 0) {
        showNotification('Please select at least one invoice and enter a paid amount.', 'error');
        return;
    }

    currentStep = nextStep;
    showStep(currentStep);
};

function showStep(step) {
    document.querySelectorAll('.step-content').forEach(s => s.classList.add('hidden'));

    const isReviewStep = (maxSteps === 3 && step === 3) || (maxSteps === 4 && step === 4);
    const contentId = isReviewStep ? 'step-4' : 'step-' + step;
    const stepEl = document.getElementById(contentId);
    if (stepEl) stepEl.classList.remove('hidden');

    const indSteps = maxSteps === 3
        ? [1, 2, 4]
        : [1, 2, 3, 4];

    for (let i = 1; i <= 4; i++) {
        const indicator = document.querySelector('.payment-step-indicator-' + i);
        if (!indicator) continue;
        const visibleIdx = indSteps.indexOf(i);
        if (visibleIdx === -1) {
            indicator.closest('.payment-stepper-item')?.classList.add('hidden');
            continue;
        }
        indicator.closest('.payment-stepper-item')?.classList.remove('hidden');
        const effectivePos = visibleIdx + 1;
        indicator.classList.remove('active', 'pending', 'completed');
        indicator.classList.add(effectivePos < step ? 'completed' : (effectivePos === step ? 'active' : 'pending'));
    }

    const stepLine = document.querySelector('.payment-step-line');
    if (stepLine) stepLine.style.width = (((step - 1) / (maxSteps - 1)) * 100) + '%';

    document.getElementById('btn-back')?.classList.toggle('hidden', step === 1);
    document.getElementById('btn-next')?.classList.toggle('hidden', step === maxSteps);
    document.getElementById('btn-finalize')?.classList.toggle('hidden', step !== maxSteps);
    document.getElementById('btn-mark-paid')?.classList.toggle('hidden', step !== 1);
    document.getElementById('btn-print-invoice')?.classList.toggle('hidden', step !== maxSteps);

    if (step === 2) {
        fillBankDetails();
        calculateCheckTotal();
    }
    if (isReviewStep) populateReview();
    if (step === 3 && maxSteps === 4) renderSuddenReturns();
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

window.removeCheckInput = function(btn) {
    const row = btn.closest('.check-row');
    if (!row) return;
    const container = document.getElementById('check-inputs-container');
    const rows = container?.querySelectorAll('.check-row');
    if (!rows || rows.length <= 1) {
        row.querySelectorAll('input').forEach(input => {
            if (input.classList.contains('bank-name-input') || input.classList.contains('account-no-input')) return;
            input.value = '';
        });
        calculateCheckTotal();
        return;
    }
    row.remove();
    calculateCheckTotal();
};

window.addCheckInput = function() {
    const container = document.getElementById('check-inputs-container');
    const firstRow = container?.querySelector('.check-row');
    if (!container || !firstRow) return;

    const newRow = firstRow.cloneNode(true);
    newRow.querySelectorAll('input').forEach(input => {
        if (input.classList.contains('bank-name-input') || input.classList.contains('account-no-input')) return;
        input.value = '';
    });
    container.appendChild(newRow);
    fillBankDetails();
};

function renderCheckRows(checks = []) {
    const container = document.getElementById('check-inputs-container');
    if (!container) return;

    const rows = checks.length ? checks : [{}];
    container.innerHTML = rows.map(check => buildCheckRowMarkup(check)).join('');
    calculateCheckTotal();
}

function buildCheckRowMarkup(check = {}) {
    return `
        <div class="grid grid-cols-5 gap-4 p-5 bg-slate-50 rounded-2xl border border-slate-100 relative check-row" data-bank-account-id="${escapeAttr(check.customer_bank_account_id ?? '')}">
            <button onclick="window.removeCheckInput(this)" class="absolute -top-2 -right-2 w-6 h-6 bg-white border border-slate-200 rounded-full flex items-center justify-center text-slate-400 hover:text-red-600 hover:border-red-300 transition-colors shadow-sm" title="Remove this payment"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
            <div class="space-y-1.5">
                <label class="text-[10px] font-bold text-slate-400 uppercase">Bank Name</label>
                <input type="text" class="bank-name-input w-full px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs outline-none focus:border-maroon" placeholder="Bank name" value="${escapeAttr(check.bank_name || '')}">
            </div>
            <div class="space-y-1.5">
                <label class="text-[10px] font-bold text-slate-400 uppercase">Account No</label>
                <input type="text" class="account-no-input w-full px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs outline-none focus:border-maroon" placeholder="Account no." value="${escapeAttr(check.account_number || '')}">
            </div>
            <div class="space-y-1.5">
                <label class="text-[10px] font-bold text-slate-400 uppercase">Check No</label>
                <input type="text" class="w-full px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs outline-none focus:border-maroon" placeholder="Enter check #" value="${escapeAttr(check.check_no || '')}">
            </div>
            <div class="space-y-1.5">
                <label class="text-[10px] font-bold text-slate-400 uppercase">Check Date</label>
                <input type="date" class="w-full px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs outline-none focus:border-maroon" value="${escapeAttr(check.check_date || '')}">
            </div>
            <div class="space-y-1.5">
                <label class="text-[10px] font-bold text-slate-400 uppercase">Credit Amount</label>
                <input type="number" oninput="window.calculateCheckTotal()" class="w-full px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs outline-none focus:border-maroon check-amount" placeholder="0.00" value="${escapeAttr(check.credit_amount ?? '')}">
            </div>
        </div>
    `;
}

window.switchProcessLeftTab = function(tab = 'current', shouldScroll = false) {
    const selected = tab === 'selected';
    document.getElementById('process-left-current-panel')?.classList.toggle('hidden', selected);
    document.getElementById('process-left-selected-panel')?.classList.toggle('hidden', !selected);
    const currentBtn = document.getElementById('process-left-tab-current');
    const selectedBtn = document.getElementById('process-left-tab-selected');
    if (currentBtn) currentBtn.className = selected ? 'flex-1 rounded-lg px-3 py-2 text-[9px] font-black uppercase tracking-widest text-slate-400' : 'flex-1 rounded-lg bg-white px-3 py-2 text-[9px] font-black uppercase tracking-widest text-maroon shadow-sm';
    if (selectedBtn) selectedBtn.className = selected ? 'flex-1 rounded-lg bg-white px-3 py-2 text-[9px] font-black uppercase tracking-widest text-maroon shadow-sm' : 'flex-1 rounded-lg px-3 py-2 text-[9px] font-black uppercase tracking-widest text-slate-400';
    if (selected && shouldScroll) document.getElementById('process-left-tabs')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
};

function renderSelectedInvoiceTrail() {
    const tbody = document.getElementById('selected-invoice-trail-tbody');
    if (!tbody) return;
    const rows = selectedInvoiceRowsInClickOrder();
    setText('selected-invoice-trail-count', `${rows.length} selected`);
    tbody.innerHTML = rows.length ? rows.map(row => {
        const invoiceNo = row.dataset.invoiceNo || '---';
        const paid = Number(row.querySelector('.paid-input')?.value || 0);
        const remarks = String(row.querySelector('.remarks-input')?.value || '').trim();
        return `<tr class="hover:bg-slate-50"><td class="p-3 font-black text-maroon break-all">${escapeHtml(invoiceNo)}</td><td class="p-3 text-right font-black text-emerald-700 whitespace-nowrap">${money.format(paid)}</td><td class="p-3 font-bold text-slate-600 break-words">${escapeHtml(remarks || '---')}</td></tr>`;
    }).join('') : `<tr><td colspan="3" class="p-5 text-center font-bold text-slate-400">No selected invoices yet.</td></tr>`;
}

window.showSelectedInvoiceTab = function(row) {
    renderSelectedInvoiceTrail();
    window.switchProcessLeftTab('selected', true);
};

window.calculateCheckTotal = function() {
    const total = Array.from(document.querySelectorAll('#check-inputs-container .check-amount'))
        .reduce((sum, input) => sum + (Number(input.value || 0) || 0), 0);
    const paymentTarget = selectedInvoicePayload()
        .reduce((sum, invoice) => sum + Number(invoice.paid_amount || 0), 0);
    const remaining = Math.round((paymentTarget - total + Number.EPSILON) * 100) / 100;

    const totalEl = document.getElementById('payment-details-computed-total');
    if (totalEl) totalEl.textContent = money.format(total);

    const remainingEl = document.getElementById('payment-details-remaining');
    if (remainingEl) {
        remainingEl.textContent = money.format(remaining);
        remainingEl.classList.remove('text-amber-600', 'text-emerald-600', 'text-red-600');
        if (remaining > 0.004) remainingEl.classList.add('text-amber-600');
        else if (remaining < -0.004) remainingEl.classList.add('text-red-600');
        else remainingEl.classList.add('text-emerald-600');
    }

    return total;
};

function selectedInvoicePayload() {
    return Array.from(document.querySelectorAll('#modal-invoices-tbody tr')).map(row => {
        const checked = row.querySelector('.invoice-check')?.checked;
        const paidAmount = parseFloat(row.querySelector('.paid-input')?.value || 0);
        const dueAmount = Number(row.dataset.due || 0);
        if (!checked || paidAmount < 0 || (paidAmount <= 0 && dueAmount > 0)) return null;

        return {
            source_type: row.dataset.sourceType,
            source_id: Number(row.dataset.sourceId),
            po_no: row.dataset.poNo || '',
            invoice_no: row.dataset.invoiceNo,
            date: row.dataset.date || '',
            invoice_amount: Number(row.dataset.amount || 0),
            due_amount: Number(row.dataset.due || 0),
            returned_amount: Number(row.dataset.returnedAmount || 0),
            terms_days: row.dataset.termsDays === '' ? null : Number(row.dataset.termsDays),
            age_days: row.dataset.ageDays === '' ? null : Number(row.dataset.ageDays),
            has_exceeded_terms: row.dataset.overdue === '1',
            overdue_days: Number(row.dataset.overdueDays || 0),
            adjustment: Number(row.querySelector('.adj-input')?.value || 0),
            paid_amount: paidAmount,
            remarks: row.querySelector('.remarks-input')?.value || ''
        };
    }).filter(Boolean);
}

function checkPayload() {
    return Array.from(document.querySelectorAll('.check-row')).map(row => ({
        customer_bank_account_id: row.dataset.bankAccountId ? Number(row.dataset.bankAccountId) : null,
        bank_name: row.querySelector('.bank-name-input')?.value || '',
        account_number: row.querySelector('.account-no-input')?.value || '',
        check_no: row.querySelector('input[placeholder="Enter check #"]')?.value || '',
        check_date: row.querySelector('input[type="date"]')?.value || null,
        credit_amount: Number(row.querySelector('.check-amount')?.value || 0)
    }));
}

function populateReview() {
    const container = document.getElementById('review-content');
    if (!container) return;

    const invoices = selectedInvoicePayload();
    const checks = checkPayload().filter(check => check.bank_name || check.account_number || check.check_no || check.credit_amount);
    const total = invoices.reduce((sum, inv) => sum + inv.paid_amount, 0);
    const totalDue = invoices.reduce((sum, inv) => sum + inv.due_amount, 0);
    const totalAdjustment = invoices.reduce((sum, inv) => sum + inv.adjustment, 0);
    const totalReturns = invoices.reduce((sum, inv) => sum + (inv.returned_amount || 0), 0);
    const customer = currentPayment.customer || {};

    const manualReturns = collectManualReturns('payment');
    container.innerHTML = `
        <div class="md:col-span-2 overflow-hidden rounded-2xl border border-maroon/10 bg-white shadow-sm">
            <div class="bg-maroon p-6 text-white">
                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.25em] text-gold">Collection Review</p>
                        <h5 class="mt-2 text-2xl font-black tracking-tight">${escapeHtml(customer.name || '---')}</h5>
                        <p class="mt-2 max-w-2xl text-xs font-semibold text-white/60">${escapeHtml(customer.address || 'No address on file')}</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/10 px-5 py-4 text-right">
                        <p class="text-[9px] font-black uppercase tracking-widest text-white/50">Collection No.</p>
                        <p class="mt-1 text-lg font-black text-gold">${escapeHtml(currentPayment.paymentNo || '---')}</p>
                        <p class="mt-2 text-[10px] font-bold text-white/60">${escapeHtml(document.getElementById('p-date')?.textContent || '')}</p>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-5 divide-y md:divide-y-0 md:divide-x divide-slate-100 bg-slate-50">
                <div class="p-4">
                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Invoices</p>
                    <p class="mt-1 text-xl font-black text-slate-800">${invoices.length}</p>
                </div>
                <div class="p-4">
                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Total Returns</p>
                    <p class="mt-1 text-xl font-black text-slate-800">${money.format(totalReturns)}</p>
                </div>
                <div class="p-4">
                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Amount Due</p>
                    <p class="mt-1 text-xl font-black text-amber-600">${money.format(totalDue)}</p>
                </div>
                <div class="p-4">
                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Adjustment</p>
                    <p class="mt-1 text-xl font-black text-slate-800">${money.format(totalAdjustment)}</p>
                </div>
                <div class="p-4">
                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Amount Paid</p>
                    <p class="mt-1 text-xl font-black text-maroon">${money.format(total)}</p>
                </div>
            </div>
        </div>

        <div class="md:col-span-2 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <h5 class="text-[10px] font-black text-maroon uppercase tracking-widest">Transaction Details</h5>
                <span class="rounded-full bg-maroon/5 px-3 py-1 text-[9px] font-black uppercase tracking-widest text-maroon">${invoices.length} Selected</span>
            </div>
            <div class="mt-4 overflow-x-auto rounded-xl border border-slate-100">
                <table class="w-full min-w-[760px] text-left text-[10px]">
                    <thead class="bg-slate-50 text-slate-400 uppercase tracking-widest">
                        <tr>
                            <th class="p-3">Invoice No.</th>
                            <th class="p-3">Date</th>
                            <th class="p-3 text-right">Invc. Amount</th>
                            <th class="p-3 text-right">Amt Due</th>
                            <th class="p-3 text-right">Adjustment</th>
                            <th class="p-3 text-right">Amt. Paid</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        ${invoices.map(inv => `
                            <tr>
                                <td class="p-3 font-black text-maroon">${escapeHtml(inv.invoice_no)}</td>
                                <td class="p-3 font-bold text-slate-500">${escapeHtml(inv.date || '---')}</td>
                                <td class="p-3 text-right font-bold">${money.format(inv.invoice_amount)}</td>
                                <td class="p-3 text-right font-bold text-amber-600">${money.format(inv.due_amount)}</td>
                                <td class="p-3 text-right font-bold">${money.format(inv.adjustment)}</td>
                                <td class="p-3 text-right font-black text-maroon">${money.format(inv.paid_amount)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>

        <div class="md:col-span-2 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <h5 class="text-[10px] font-black text-maroon uppercase tracking-widest">Bank Info</h5>
                <span class="rounded-full bg-gold/20 px-3 py-1 text-[9px] font-black uppercase tracking-widest text-maroon">${checks.length} Check Row${checks.length === 1 ? '' : 's'}</span>
            </div>
            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                ${checks.length ? checks.map(check => `
                    <div class="rounded-xl bg-slate-50 border border-slate-100 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Bank Name</p>
                                <p class="mt-1 text-sm font-black text-maroon">${escapeHtml(check.bank_name || '---')}</p>
                            </div>
                            <div class="h-9 w-9 rounded-lg bg-maroon text-gold flex items-center justify-center">
                                <i data-lucide="landmark" class="w-4 h-4"></i>
                            </div>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-[10px] font-bold text-slate-500">
                            <div><span class="block text-slate-400 uppercase">Account</span>${escapeHtml(check.account_number || '---')}</div>
                            <div><span class="block text-slate-400 uppercase">Check No</span>${escapeHtml(check.check_no || '---')}</div>
                            <div><span class="block text-slate-400 uppercase">Check Date</span>${escapeHtml(check.check_date || '---')}</div>
                            <div><span class="block text-slate-400 uppercase">Amount</span><strong class="text-maroon">${money.format(check.credit_amount || 0)}</strong></div>
                        </div>
                    </div>
                `).join('') : '<p class="text-[10px] italic text-slate-400">No check details entered.</p>'}
            </div>
        </div>

        ${manualReturns.length ? `
        <div class="md:col-span-2 rounded-2xl border border-amber-200 bg-amber-50/50 p-5 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <h5 class="text-[10px] font-black text-amber-700 uppercase tracking-widest">Returns</h5>
                <span class="rounded-full bg-amber-100 px-3 py-1 text-[9px] font-black uppercase tracking-widest text-amber-700">${manualReturns.length} Entered</span>
            </div>
            <div class="mt-4 overflow-x-auto rounded-xl border border-amber-100 bg-white">
                <table class="w-full text-[10px]"><thead class="bg-amber-50 text-amber-700 uppercase tracking-widest"><tr><th class="p-3 text-left">Return Order ID</th><th class="p-3 text-right">Wallet & Adjustment</th><th class="p-3 text-left">Return No.</th></tr></thead><tbody>${manualReturns.map(ret => `<tr><td class="p-3 font-black">${escapeHtml(ret.return_order_id)}</td><td class="p-3 text-right font-black">${escapeHtml(formatWalletAdjustmentForDisplay(ret.wallet_adjustment))}</td><td class="p-3 font-bold">${escapeHtml(ret.return_number || '---')}</td></tr>`).join('')}</tbody></table>
            </div>
            <p class="mt-3 text-[10px] font-semibold text-amber-700">Manual Return fields are print-only. They are not saved, looked up, linked, or used in the payment calculation.</p>
        </div>
        ` : ''}
    `;
};

window.printPaymentInvoice = function() {
    const invoices = selectedInvoicePayload();
    const checks = checkPayload().filter(check => check.bank_name || check.account_number || check.check_no || check.credit_amount);
    if (!invoices.length) {
        showNotification('No selected invoices to print.', 'error');
        return;
    }

    const manualReturns = collectManualReturns('payment');

    openPrintablePaymentLayout({
        customer: currentPayment.customer || {},
        collectionNo: currentPayment.paymentNo || '---',
        paymentDate: document.getElementById('p-date')?.textContent || new Date().toISOString().split('T')[0],
        invoices,
        checks,
        returns: manualReturns
    });
};

function formatPrintPercent(value) {
    const numeric = Math.max(0, Math.min(100, Number(value || 0)));
    return numeric.toFixed(2).replace(/\.00$/, '').replace(/(\.\d)0$/, '$1');
}

function openPrintablePaymentLayout({
    customer = {}, collectionNo = '---', paymentDate = '', invoices = [], checks = [], returns = [],
    isGroupPayment = false, groupOnlinePercent = 0, groupOnlinePayment = 0, groupRetotalAmount = null,
    groupDiscountPercent = 0, groupDiscountAmount = 0, groupDiscountEmbeddedInAdjustments = false
}) {
    const legacyPercent = Math.max(0, Math.min(100, Number(groupDiscountPercent || 0)));
    const printableInvoices = invoices.map(inv => {
        const normalized = {
            ...inv,
            invoice_amount: Number(inv.invoice_amount || inv.amount || 0),
            paid_amount: Number(inv.paid_amount || 0),
            adjustment: Number(inv.adjustment || 0),
            online_payment: Number(inv.online_payment || 0)
        };
        if (groupDiscountEmbeddedInAdjustments && normalized.online_payment <= 0 && legacyPercent > 0) {
            const split = splitLegacyGroupOnlinePayment(normalized, legacyPercent);
            normalized.adjustment = split.adjustment;
            normalized.online_payment = split.online_payment;
        }
        return normalized;
    });

    const totalPaid = printableInvoices.reduce((sum, inv) => sum + Number(inv.paid_amount || 0), 0);
    const totalAdjustment = printableInvoices.reduce((sum, inv) => sum + Number(inv.adjustment || 0), 0);
    const totalInvoice = printableInvoices.reduce((sum, inv) => sum + Number(inv.invoice_amount || 0), 0);
    const totalChecks = checks.reduce((sum, check) => sum + Number(check.credit_amount || 0), 0);
    const normalizedPercent = isGroupPayment ? Math.max(0, Math.min(100, Number(groupOnlinePercent || 0))) : legacyPercent;
    const grossOnlinePayment = isGroupPayment ? Number(groupOnlinePayment || 0) : Number(groupDiscountAmount || 0);
    const onlinePercentValue = isGroupPayment
        ? Math.round((grossOnlinePayment * normalizedPercent / 100 + Number.EPSILON) * 100) / 100
        : 0;
    // Online Payment is a manual print value. Do not deduct Online Percent from it.
    // If the user enters 2000, the Collection Invoice must print exactly 2000.
    const normalizedOnlinePayment = isGroupPayment
        ? Math.round((grossOnlinePayment + Number.EPSILON) * 100) / 100
        : grossOnlinePayment;

    // Re-Total is always the TOTAL INVOICE AMOUNT of the selected invoices,
    // never the paid amount and never the Online Payment after any calculation.
    const normalizedRetotal = totalInvoice;
    const invoiceBlanks = Math.max(5 - printableInvoices.length, 0);
    const returnBlanks = Math.max(4 - returns.length, 0);
    const bankBlanks = Math.max(2 - checks.length, 0);
    const blankCells = count => `<tr class="blank-row">${'<td>&nbsp;</td>'.repeat(count)}</tr>`;

    const secondarySection = isGroupPayment ? `
        <div class="section-block"><div class="section-title">RETURNS</div><table class="detail-table"><thead><tr><th>RETURN ORDER ID</th><th class="right">WALLET &amp; ADJUSTMENT</th><th>RETURN NO.</th></tr></thead><tbody>
        ${returns.map(r => {
            const wallet = formatWalletAdjustmentForDisplay(r.wallet_adjustment);
            return `<tr><td>${escapeHtml(r.return_order_id || '')}</td><td class="right">${escapeHtml(wallet)}</td><td>${escapeHtml(r.return_number || '')}</td></tr>`;
        }).join('')}
        ${Array.from({ length: returnBlanks }, () => blankCells(3)).join('')}</tbody></table></div>` : `
        <div class="section-block"><div class="section-title">BANK INFO</div><table class="detail-table"><thead><tr><th>ACCOUNT NO</th><th>BANK NAME</th><th>CHECK NO.</th><th>CHECK DATE</th><th class="right">CHECK AMOUNT</th></tr></thead><tbody>
        ${checks.map(check => `<tr><td>${escapeHtml(check.account_number || '---')}</td><td>${escapeHtml(check.bank_name || '---')}</td><td>${escapeHtml(check.check_no || '---')}</td><td>${escapeHtml(check.check_date || '---')}</td><td class="right">${money.format(Number(check.credit_amount || 0))}</td></tr>`).join('')}
        ${Array.from({ length: bankBlanks }, () => blankCells(5)).join('')}<tr><td colspan="4" class="right"><strong>TOTAL</strong></td><td class="right"><strong>${money.format(totalChecks)}</strong></td></tr></tbody></table></div>
        ${returns.length ? `<div class="section-block"><div class="section-title">RETURNS</div><table class="detail-table"><thead><tr><th>RETURN ORDER ID</th><th class="right">WALLET &amp; ADJUSTMENT</th><th>RETURN NO.</th></tr></thead><tbody>${returns.map(r => `<tr><td>${escapeHtml(r.return_order_id || r.sales_return_id || '---')}</td><td class="right">${escapeHtml(formatWalletAdjustmentForDisplay(r.wallet_adjustment ?? r.return_amount ?? ''))}</td><td>${escapeHtml(r.return_number || '---')}</td></tr>`).join('')}</tbody></table></div>` : ''}`;

    const summary = isGroupPayment
        ? `<div class="summary"><div><span>SUB TOTAL:</span><strong>${money.format(totalPaid)}</strong></div><div><span>ONLINE PERCENT:</span><strong>(${formatPrintPercent(normalizedPercent)}%) ${money.format(onlinePercentValue)}</strong></div><div><span>ONLINE PAYMENT:</span><strong>${money.format(normalizedOnlinePayment)}</strong></div><div class="retotal"><span>RE-TOTAL:</span><strong>${money.format(normalizedRetotal)}</strong></div></div>`
        : `<div class="summary"><div class="retotal"><span>TOTAL:</span><strong>${money.format(totalPaid)}</strong></div></div>`;

    // Put the company title, sub-title, customer meta, and transaction column labels
    // inside THEAD. Chrome repeats THEAD on every printed page, which is much more
    // reliable than a fixed-position header and keeps Letter pages aligned.
    const printable = `<!doctype html><html><head><meta charset="utf-8"><title>Collection Invoice ${escapeHtml(collectionNo)}</title><style>
@page{size:Letter portrait;margin:.38in .35in .42in}
*{box-sizing:border-box}
html,body{margin:0;padding:0;background:#fff;color:#111}
body{font-family:Arial,sans-serif;font-size:9px;line-height:1.2}
.transaction-table,.detail-table{width:100%;border-collapse:collapse;table-layout:fixed}
.transaction-table thead,.detail-table thead{display:table-header-group}
.transaction-table tr,.detail-table tr{break-inside:avoid;page-break-inside:avoid}
th,td{border:1px solid #111;padding:4px 5px;height:20px;vertical-align:middle}
th{font-size:8.5px;font-weight:800;text-align:left}
.right{text-align:right}
.doc-head-cell{border:0!important;padding:0 0 8px!important;background:#fff!important}
.company{text-align:center;font-size:16px;font-weight:800;letter-spacing:.2px}
.subtitle{text-align:center;font-size:11px;font-weight:700;margin:2px 0 10px}
.meta{display:grid;grid-template-columns:1fr 1fr;gap:4px 24px;text-align:left;font-size:8.5px}
.meta-row{display:grid;grid-template-columns:72px 1fr;gap:6px;min-height:16px;align-items:end}
.meta-label{font-weight:800}
.meta-value{border-bottom:1px solid #111;padding:0 3px 2px;font-weight:700;min-width:0;overflow-wrap:anywhere}
.transaction-section-title{border:0!important;padding:5px 0 4px!important;font-size:9px;font-weight:900;background:#fff!important;text-align:left}
.column-head th{background:#fff;font-size:8px;padding-top:4px;padding-bottom:4px}
.section-block{margin-top:10px;break-inside:avoid-page}
.section-title{font-size:9px;font-weight:900;margin:0 0 4px}
.summary{width:260px;margin:10px 0 0 auto;font-size:9px;break-inside:avoid}
.summary>div{display:grid;grid-template-columns:1fr 108px;gap:10px;padding:3px 0}
.summary strong{text-align:right}
.summary .retotal{border-top:1px solid #111;margin-top:2px;padding-top:6px;font-size:10px}
.blank-row td{height:18px}
@media print{
  html,body{width:auto;height:auto}
  body{-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .transaction-table thead,.detail-table thead{display:table-header-group}
  .section-block,.summary{page-break-inside:avoid}
}
</style></head><body>
<table class="transaction-table">
<thead>
<tr><th colspan="5" class="doc-head-cell"><div class="company">W68 AUTO PARTS &amp; SERVICE CENTER</div><div class="subtitle">Collection Invoice</div><div class="meta"><div class="meta-row"><span class="meta-label">Customer:</span><span class="meta-value">${escapeHtml(customer.name || '---')}</span></div><div class="meta-row"><span class="meta-label">Collection No.:</span><span class="meta-value">${escapeHtml(collectionNo)}</span></div><div class="meta-row"><span class="meta-label">Address:</span><span class="meta-value">${escapeHtml(customer.address || '---')}</span></div><div class="meta-row"><span class="meta-label">DATE:</span><span class="meta-value">${escapeHtml(paymentDate || '---')}</span></div></div></th></tr>
<tr><th colspan="5" class="transaction-section-title">TRANSACTION DETAILS</th></tr>
<tr class="column-head"><th>INVOICE NO.</th><th>DATE</th><th class="right">INVC. AMOUNT</th><th class="right">ADJUSTMENT</th><th class="right">AMT. PAID</th></tr>
</thead>
<tbody>
${printableInvoices.map(inv => `<tr><td>${escapeHtml(inv.invoice_no || '---')}</td><td>${escapeHtml(inv.date || '---')}</td><td class="right">${money.format(inv.invoice_amount)}</td><td class="right">${money.format(inv.adjustment)}</td><td class="right">${money.format(inv.paid_amount)}</td></tr>`).join('')}
${Array.from({ length: invoiceBlanks }, () => blankCells(5)).join('')}
<tr><td colspan="2" class="right"><strong>TOTAL</strong></td><td class="right"><strong>${money.format(totalInvoice)}</strong></td><td class="right"><strong>${money.format(totalAdjustment)}</strong></td><td class="right"><strong>${money.format(totalPaid)}</strong></td></tr>
</tbody></table>
${secondarySection}${summary}
</body></html>`;

    const printWindow = window.open('', '_blank', 'width=900,height=700');
    if (!printWindow) {
        showNotification('Please allow popups to print the invoice.', 'error');
        return;
    }
    printWindow.document.open();
    printWindow.document.write(printable);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => printWindow.print(), 300);
}

async function fetchPaymentHistoryDetail(paymentId) {
    const url = routes().historyDetail.replace(':paymentId', paymentId);
    const res = await fetch(url);
    const data = await res.json();
    if (!data.success) {
        throw new Error(data.message || 'Unable to load payment history detail.');
    }
    return data;
}

window.finalizePayment = async function() {
    const invoices = selectedInvoicePayload();
    if (!invoices.length) {
        showNotification('No selected invoices with paid amounts.', 'error');
        return;
    }

    const total = invoices.reduce((sum, invoice) => sum + invoice.paid_amount, 0);
    const overdueInvoices = invoices.filter(invoice => invoice.has_exceeded_terms);
    const overdueWarning = overdueInvoices.length
        ? ` Warning: ${overdueInvoices.length} selected invoice${overdueInvoices.length === 1 ? ' is' : 's are'} already exceeded the customer terms.`
        : '';
    const actionWord = currentPayment.mode === 'edit' ? 'update' : 'finalize';
    const actionLabel = currentPayment.mode === 'edit' ? 'Update Payment' : 'Confirm Payment';
    const actionSubtitle = currentPayment.mode === 'edit'
        ? 'Review the changes before updating the saved payment.'
        : 'Review before posting to accounting.';
    const confirmButtonLabel = currentPayment.mode === 'edit' ? 'Update' : 'Confirm';

    setText('payment-confirm-title', actionLabel);
    setText('payment-confirm-subtitle', actionSubtitle);
    setText('payment-confirm-submit-btn', confirmButtonLabel);
    setText('payment-confirm-message', `${actionWord.charAt(0).toUpperCase() + actionWord.slice(1)} ${money.format(total)} for ${currentPayment.customer?.name || 'this customer'}? This will ${currentPayment.mode === 'edit' ? 'update the saved payment record' : 'post the payment to accounting'}.${overdueWarning}`);
    setOverdueWarning(overdueInvoices);
    openPaymentConfirmModal();
};

window.openPaymentConfirmModal = function() {
    const modal = document.getElementById('payment-confirm-modal');
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    if (typeof lucide !== 'undefined') lucide.createIcons();
};

window.closePaymentConfirmModal = function() {
    const modal = document.getElementById('payment-confirm-modal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
};

window.closePaymentSuccessModal = function() {
    const modal = document.getElementById('payment-success-modal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
};

function openPaymentSuccessModal(message, title = 'Payment Posted') {
    setText('payment-success-title', title);
    setText('payment-success-message', message);
    const modal = document.getElementById('payment-success-modal');
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function setProcessPaymentMode(mode) {
    const currentMode = mode === 'edit' ? 'edit' : 'create';
    currentPayment.mode = currentMode;

    setText('process-payment-title', currentMode === 'edit' ? 'Edit Payment' : 'Process Payment');
    setText('process-payment-subtitle', currentMode === 'edit'
        ? 'Update an existing posted payment record.'
        : 'Create a new posted payment record.');
    setText('btn-finalize', currentMode === 'edit' ? 'Update Payment' : 'Finalize Payment');
    setText('payment-confirm-title', currentMode === 'edit' ? 'Confirm Update' : 'Confirm Payment');
    setText('payment-confirm-subtitle', currentMode === 'edit'
        ? 'Review the changes before updating the saved payment.'
        : 'Review before posting to accounting.');
    setText('payment-confirm-submit-btn', currentMode === 'edit' ? 'Update' : 'Confirm');
}

function invoiceRowClass(inv) {
    return inv.has_exceeded_terms
        ? 'invoice-row-overdue animate-overdue-blink transition-colors'
        : 'hover:bg-slate-50 transition-colors';
}

function renderOverdueMeta(inv) {
    if (!inv.has_exceeded_terms) {
        return '';
    }

    const termsDays = Number.isFinite(Number(inv.terms_days)) ? Number(inv.terms_days) : null;
    const ageDays = Number.isFinite(Number(inv.age_days)) ? Number(inv.age_days) : null;
    const overdueDays = Number.isFinite(Number(inv.overdue_days)) ? Number(inv.overdue_days) : 0;
    const termsLabel = termsDays === null ? 'No terms set' : `${termsDays} day${termsDays === 1 ? '' : 's'} terms`;
    const ageLabel = ageDays === null ? '' : `, ${ageDays} day${ageDays === 1 ? '' : 's'} old`;

    return `<span class="mt-1 inline-flex items-center gap-1 rounded-md bg-red-50 border border-red-200 px-2 py-0.5 text-[10px] font-bold text-red-700"><svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>Exceeded ${overdueDays}d | ${escapeHtml(termsLabel)}${escapeHtml(ageLabel)}</span>`;
}

function setOverdueWarning(overdueInvoices) {
    const warningBox = document.getElementById('payment-overdue-warning');
    const warningText = document.getElementById('payment-overdue-warning-text');
    if (!warningBox || !warningText) return;

    if (!overdueInvoices.length) {
        warningBox.classList.add('hidden');
        warningText.textContent = '';
        return;
    }

    const labels = overdueInvoices
        .slice(0, 3)
        .map(invoice => `${invoice.invoice_no} (+${Number(invoice.overdue_days || 0)} day${Number(invoice.overdue_days || 0) === 1 ? '' : 's'})`);
    const extra = overdueInvoices.length > 3 ? ` and ${overdueInvoices.length - 3} more` : '';
    warningText.textContent = `This payment includes invoice(s) that already exceeded the terms: ${labels.join(', ')}${extra}.`;
    warningBox.classList.remove('hidden');
}

window.submitFinalizePayment = async function() {
    const invoices = selectedInvoicePayload();
    if (!invoices.length) {
        closePaymentConfirmModal();
        showNotification('No selected invoices with paid amounts.', 'error');
        return;
    }

    const btn = document.getElementById('btn-finalize');
    const oldText = btn?.textContent;
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Processing...';
    }

    try {
        const isEdit = currentPayment.mode === 'edit';
        const targetUrl = isEdit
            ? routes().historyUpdate.replace(':paymentId', currentPayment.paymentId)
            : routes().process;
        const res = await fetch(targetUrl, {
            method: isEdit ? 'PUT' : 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken()
            },
            body: JSON.stringify({
                customer_id: currentPayment.customer?.id,
                customer_name: currentPayment.customer?.name,
                payment_no: currentPayment.paymentNo,
                payment_date: document.getElementById('p-date')?.textContent || new Date().toISOString().split('T')[0],
                invoices,
                checks: checkPayload()
            })
        });

        const data = await res.json();
        if (!data.success) throw new Error(data.message || `Unable to ${isEdit ? 'update' : 'process'} payment.`);

        closePaymentConfirmModal();
        toggleModal('proceed-modal', false);
        openPaymentSuccessModal(
            isEdit
                ? 'The payment record was updated in core4_accounting successfully.'
                : 'The payment was saved to core4_accounting successfully.',
            isEdit ? 'Payment Updated' : 'Payment Posted'
        );
        paymentsPage = 1;
        loadPaymentsTable();
        paymentsHistoryPage = 1;
        historyHasLoaded = true;
        loadPaymentsHistoryTable();
        if (currentPayorHistoryCustomerId && !document.getElementById('payor-history-modal')?.classList.contains('hidden')) loadPayorHistoryInvoices();
    } catch (error) {
        closePaymentConfirmModal();
        showNotification(error.message || 'Failed to post payment.', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = oldText || 'Finalize Payment';
        }
    }
};

function renderPaymentHistoryLedgerRows(ledger = []) {
    const ledgerBody = document.getElementById('payments-history-view-ledger-tbody');
    if (!ledgerBody) return;
    ledgerBody.innerHTML = ledger.length ? ledger.map(row => {
        const isPayment = String(row.transaction || '').toUpperCase() === 'PAID';
        const creditText = isPayment
            ? money.format(Number(row.credit || 0))
            : (Number(row.credit || 0) ? money.format(Number(row.credit || 0)) : '—');
        return `<tr class="${isPayment ? 'bg-emerald-50/60' : ''}"><td class="p-3 font-black text-maroon">${escapeHtml(row.invoice_no || '---')}</td><td class="p-3 font-black text-maroon">${escapeHtml(row.product_code || '---')}</td><td class="p-3 font-bold text-slate-600">${escapeHtml(row.part_number || '---')}</td><td class="p-3 font-black">${escapeHtml(row.transaction || '---')}</td><td class="p-3 font-bold">${escapeHtml(row.transaction_no || '---')}</td><td class="p-3 text-right font-bold">${Number(row.debit || 0) ? money.format(Number(row.debit || 0)) : '—'}</td><td class="p-3 text-right font-black text-emerald-700">${creditText}</td><td class="p-3 text-slate-500">${escapeHtml(row.remarks || '')}</td><td class="p-3 font-bold text-slate-500 whitespace-nowrap">${escapeHtml(row.date || '---')}</td></tr>`;
    }).join('') : `<tr><td colspan="9" class="p-8 text-center text-slate-400 font-bold">No ledger movements found.</td></tr>`;
}

function renderPaymentsHistoryLoading(paymentId) {
    setText('history-view-payment-no', `Loading Payment #${paymentId}...`);
    setText('history-view-payment-date', '---');
    const totalEl = document.getElementById('history-view-total-paid');
    if (totalEl) totalEl.textContent = 'Loading...';

    const invoiceBody = document.getElementById('payments-history-view-invoices-tbody');
    if (invoiceBody) invoiceBody.innerHTML = `<tr><td colspan="7" class="p-8 text-center text-slate-400 font-bold">Loading invoices...</td></tr>`;
    const bankBody = document.getElementById('payments-history-view-bank-tbody');
    if (bankBody) bankBody.innerHTML = `<tr><td colspan="6" class="p-8 text-center text-slate-400 font-bold">Loading bank details...</td></tr>`;
    const ledgerBody = document.getElementById('payments-history-view-ledger-tbody');
    if (ledgerBody) ledgerBody.innerHTML = `<tr><td colspan="9" class="p-8 text-center text-slate-400 font-bold">Ledger loads after the modal opens...</td></tr>`;
    document.getElementById('history-detail-tab-items')?.classList.add('hidden');
}

async function loadPaymentHistoryLedger(paymentId, { silent = false } = {}) {
    const id = Number(paymentId || 0);
    if (!id || !currentHistoryPayment || Number(currentHistoryPayment.payment?.id || 0) !== id) return;
    if (currentHistoryPayment.__ledgerLoaded || currentHistoryPayment.__ledgerLoading) return;

    currentHistoryPayment.__ledgerLoading = true;
    const ledgerBody = document.getElementById('payments-history-view-ledger-tbody');
    if (!silent && ledgerBody) ledgerBody.innerHTML = `<tr><td colspan="9" class="p-8 text-center text-slate-400 font-bold">Loading ledger movements...</td></tr>`;

    if (historyLedgerAbortController) historyLedgerAbortController.abort();
    historyLedgerAbortController = new AbortController();

    try {
        const url = (routes().historyLedger || '/admin/payments/history/:paymentId/ledger').replace(':paymentId', String(id));
        const response = await fetch(url, {
            headers: { 'Accept': 'application/json' },
            signal: historyLedgerAbortController.signal
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'Unable to load payment ledger.');
        if (!currentHistoryPayment || Number(currentHistoryPayment.payment?.id || 0) !== id) return;
        currentHistoryPayment.ledger = data.ledger || [];
        currentHistoryPayment.__ledgerLoaded = true;
        renderPaymentHistoryLedgerRows(currentHistoryPayment.ledger);
    } catch (error) {
        if (error.name === 'AbortError') return;
        if (ledgerBody) ledgerBody.innerHTML = `<tr><td colspan="9" class="p-8 text-center text-red-500 font-bold">${escapeHtml(error.message || 'Failed to load ledger.')}</td></tr>`;
    } finally {
        if (currentHistoryPayment && Number(currentHistoryPayment.payment?.id || 0) === id) {
            currentHistoryPayment.__ledgerLoading = false;
        }
    }
}

window.openPaymentsHistoryView = async function(paymentId) {
    const id = Number(paymentId || 0);
    const modal = document.getElementById('payments-history-view-modal');
    if (!modal) {
        showNotification('Payment History detail modal is not available on this user view.', 'error');
        return;
    }

    const requestSeq = ++historyDetailRequestSeq;
    if (historyLedgerAbortController) historyLedgerAbortController.abort();
    currentHistoryPayment = {
        payment: { id },
        invoices: [],
        checks: [],
        ledger: [],
        __itemsLoaded: false,
        __ledgerLoaded: false,
        __ledgerLoading: false
    };

    // Open first, fetch second: the modal now appears immediately instead of waiting
    // for invoice/product-ledger calculations to finish on the server.
    renderPaymentsHistoryLoading(id);
    window.switchPaymentHistoryDetailTab('invoices');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
    if (typeof lucide !== 'undefined') lucide.createIcons();

    try {
        const detail = await fetchPaymentHistoryDetail(id);
        if (requestSeq !== historyDetailRequestSeq) return;
        currentHistoryPayment = {
            ...detail,
            __itemsLoaded: false,
            __ledgerLoaded: false,
            __ledgerLoading: false
        };
        renderPaymentsHistoryView(currentHistoryPayment);
        window.switchPaymentHistoryDetailTab('invoices');

        // Warm the Ledger tab only after the fast modal content has rendered.
        const prefetch = () => loadPaymentHistoryLedger(id, { silent: true });
        if ('requestIdleCallback' in window) {
            window.requestIdleCallback(prefetch, { timeout: 700 });
        } else {
            setTimeout(prefetch, 80);
        }
    } catch (error) {
        if (requestSeq !== historyDetailRequestSeq) return;
        const invoiceBody = document.getElementById('payments-history-view-invoices-tbody');
        if (invoiceBody) invoiceBody.innerHTML = `<tr><td colspan="7" class="p-8 text-center text-red-500 font-bold">${escapeHtml(error.message || 'Failed to load payment details.')}</td></tr>`;
        showNotification(error.message || 'Failed to load payment details.', 'error');
    }
};

window.closePaymentsHistoryViewModal = function() {
    historyDetailRequestSeq++;
    if (historyLedgerAbortController) historyLedgerAbortController.abort();
    const modal = document.getElementById('payments-history-view-modal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = 'auto';
};

window.switchPaymentHistoryDetailTab = function(tab = 'invoices') {
    ['invoices', 'bank', 'ledger', 'items'].forEach(name => {
        document.getElementById(`history-detail-panel-${name}`)?.classList.toggle('hidden', name !== tab);
        const btn = document.getElementById(`history-detail-tab-${name}`);
        if (btn) {
            const active = name === tab;
            btn.className = `rounded-xl px-4 py-2 text-[9px] font-black uppercase tracking-widest transition ${active ? 'bg-maroon text-gold shadow-sm' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'} ${name === 'items' && !currentHistoryPayment?.__itemsLoaded ? 'hidden' : ''}`;
        }
    });

    if (tab === 'ledger') {
        loadPaymentHistoryLedger(Number(currentHistoryPayment?.payment?.id || 0));
    }
};

window.filterHistoryDetailTable = function(tbodyId, filterRowId) {
    window.filterTableByColumns(tbodyId, filterRowId);
};

function renderPaymentsHistoryView(detail) {
    const payment = detail.payment || {};
    const invoices = detail.invoices || [];
    const checks = detail.checks || [];
    currentHistoryPayment.__itemsLoaded = false;

    setText('history-view-payment-no', payment.payment_no || '---');
    setText('history-view-payment-date', payment.payment_date || '---');
    const totalEl = document.getElementById('history-view-total-paid'); if (totalEl) totalEl.textContent = money.format(Number(payment.total_paid || 0));

    const invoiceBody = document.getElementById('payments-history-view-invoices-tbody');
    if (invoiceBody) {
        invoiceBody.innerHTML = invoices.length ? invoices.map(inv => `
            <tr class="cursor-pointer hover:bg-maroon/5" onclick="window.openPaymentHistoryInvoiceItems('${escapeAttr(inv.source_type || '')}', ${Number(inv.source_id || 0)}, '${escapeAttr(inv.invoice_no || '')}')">
                <td class="p-3 font-black text-maroon">${escapeHtml(inv.invoice_no || '---')}</td>
                <td class="p-3 text-right font-bold">${money.format(Number(inv.invoice_amount || 0))}</td>
                <td class="p-3 font-bold text-slate-600">${escapeHtml(inv.return_number || '---')}</td>
                <td class="p-3 text-right font-bold text-amber-600">${money.format(Number(inv.return_amount || 0))}</td>
                <td class="p-3 text-right font-black text-emerald-700">${money.format(Number(inv.paid_amount || 0))}${Number(inv.paid_amount || 0) <= 0.005 && Number(inv.return_amount || 0) >= (Number(inv.invoice_amount || 0) - 0.005) ? '<div class="mt-1 text-[8px] font-black uppercase tracking-wider text-amber-600">Settled by Return</div>' : ''}</td>
                <td class="p-3 font-bold text-slate-500 whitespace-nowrap">${escapeHtml(inv.date_created || inv.invoice_date || inv.date || '---')}</td>
                <td class="p-3 font-bold text-slate-500 whitespace-nowrap">${escapeHtml(inv.payment_date || payment.payment_date || '---')}</td>
            </tr>`).join('') : `<tr><td colspan="7" class="p-8 text-center text-slate-400 font-bold">No saved invoice rows found.</td></tr>`;
    }

    const bankBody = document.getElementById('payments-history-view-bank-tbody');
    if (bankBody) {
        const total = checks.reduce((sum, c) => sum + Number(c.credit_amount || 0), 0);
        bankBody.innerHTML = (checks.length ? checks.map(c => `
            <tr><td class="p-3 font-black text-maroon">${escapeHtml(c.bank_name || '---')}</td><td class="p-3 font-bold">${escapeHtml(c.account_number || '---')}</td><td class="p-3 font-bold">${escapeHtml(c.check_no || '---')}</td><td class="p-3 font-bold whitespace-nowrap">${escapeHtml(c.check_date || '---')}</td><td class="p-3 text-right font-black text-emerald-700">${money.format(Number(c.credit_amount || 0))}</td><td class="p-3 text-right font-black text-maroon">${money.format(total)}</td></tr>`).join('') : `<tr><td colspan="6" class="p-8 text-center text-slate-400 font-bold">No bank/check details found.</td></tr>`);
    }

    renderPaymentHistoryLedgerRows(detail.ledger || []);
    if (!(detail.ledger || []).length) {
        const ledgerBody = document.getElementById('payments-history-view-ledger-tbody');
        if (ledgerBody) ledgerBody.innerHTML = `<tr><td colspan="9" class="p-8 text-center text-slate-400 font-bold">Ledger is loading in the background...</td></tr>`;
    }

    document.getElementById('history-detail-tab-items')?.classList.add('hidden');
}

window.openPaymentHistoryInvoiceItems = async function(sourceType, sourceId, invoiceNo) {
    const tbody = document.getElementById('payments-history-view-items-tbody');
    if (!tbody) return;
    tbody.innerHTML = `<tr><td colspan="9" class="p-8 text-center text-slate-400 font-bold">Loading item details...</td></tr>`;
    try {
        let url = routes().historyItems.replace(':sourceType', encodeURIComponent(sourceType)).replace(':sourceId', String(sourceId));
        const u = new URL(url, window.location.origin);
        if (invoiceNo) u.searchParams.set('invoice_no', invoiceNo);
        const res = await fetch(u, { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        if (!res.ok || !data.success) throw new Error(data.message || 'Unable to load invoice item details.');
        const items = data.items || [];
        tbody.innerHTML = items.length ? items.map(item => {
            const hasReturn = Number(item.returned_qty || 0) > 0 || Number(item.returned_amount || 0) > 0;
            return `<tr class="${hasReturn ? 'bg-[#4A0E0E] text-yellow-300' : ''}"><td class="p-3 font-black">${escapeHtml(item.product_code || '---')}</td><td class="p-3 font-bold">${escapeHtml(item.part_number || '---')}</td><td class="p-3 text-right font-bold">${Number(item.qty || 0)}</td><td class="p-3 text-right font-bold">${money.format(Number(item.unit_price || 0))}</td><td class="p-3 text-right font-bold">${Number(item.discount || 0)}%</td><td class="p-3 text-right font-bold">${Number(item.additional_discount || 0)}%</td><td class="p-3 text-right font-black">${Number(item.returned_qty || 0)}</td><td class="p-3 text-right font-bold">${money.format(Number(item.total_amount || 0))}</td><td class="p-3 text-right font-black">${money.format(Number(item.final_amount || 0))}</td></tr>`;
        }).join('') : `<tr><td colspan="9" class="p-8 text-center text-slate-400 font-bold">No item details found.</td></tr>`;
        currentHistoryPayment.__itemsLoaded = true;
        const itemTab = document.getElementById('history-detail-tab-items');
        if (itemTab) itemTab.classList.remove('hidden');
        setText('history-view-items-title', `Item Details · ${invoiceNo || ''}`);
        window.switchPaymentHistoryDetailTab('items');
    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="9" class="p-8 text-center text-red-500 font-bold">${escapeHtml(error.message)}</td></tr>`;
    }
};

window.printPaymentHistoryReceipt = async function(paymentId) {
    try {
        const detail = await fetchPaymentHistoryDetail(paymentId);
        const savedReturns = detail.returns || [];
        const printable = paymentDetailToPrintable({ ...detail, returns: savedReturns });
        openPrintablePaymentLayout(printable);
    } catch (error) {
        showNotification(error.message || 'Failed to load payment details.', 'error');
    }
};

function openWholePaymentHistoryLayout(detail, printWindow = null) {
    const payment = detail?.payment || {};
    const printable = paymentDetailToPrintable(detail || {});
    const customer = printable.customer || detail?.customer || {};
    const invoices = Array.isArray(printable.invoices) ? printable.invoices : [];
    const checks = Array.isArray(printable.checks) ? printable.checks : [];

    const totalAmount = Number(payment.total_paid ?? invoices.reduce((sum, inv) => sum + Number(inv.paid_amount || 0), 0));

    const transactionRows = invoices.length ? invoices.map(inv => {
        const rowDate = inv.date || inv.date_created || inv.invoice_date || printable.paymentDate || '---';
        return `<tr>
            <td>${escapeHtml(rowDate || '---')}</td>
            <td class="strong">${escapeHtml(inv.invoice_no || '---')}</td>
            <td class="right">${money.format(Number(inv.invoice_amount || 0))}</td>
            <td>${escapeHtml(inv.return_number || '---')}</td>
            <td class="right">${money.format(Number(inv.adjustment || 0))}</td>
            <td class="right">${money.format(Number(inv.due_amount || inv.due || 0))}</td>
            <td class="right strong">${money.format(Number(inv.paid_amount || 0))}</td>
        </tr>`;
    }).join('') : '<tr><td colspan="7" class="empty">No invoice rows found.</td></tr>';

    const paymentRows = checks.length ? checks.map(check => `<tr>
        <td>${escapeHtml(check.account_number || '---')}</td>
        <td>${escapeHtml(check.bank_name || '---')}</td>
        <td>${escapeHtml(check.check_no || '---')}</td>
        <td>${escapeHtml(check.check_date || '---')}</td>
        <td class="right strong">${money.format(Number(check.credit_amount || 0))}</td>
    </tr>`).join('') : '<tr><td colspan="5" class="empty">No bank/check details found.</td></tr>';

    const html = `<!doctype html><html><head><meta charset="utf-8"><title>Whole Payment ${escapeHtml(printable.collectionNo || payment.payment_no || '')}</title><style>
@page{size:Letter portrait;margin:.38in}
*{box-sizing:border-box}
html,body{margin:0;padding:0;background:#fff;color:#111}
body{font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.28}
.page{width:100%}
.doc-title{text-align:center;font-size:14px;font-weight:900;letter-spacing:1px;text-transform:uppercase;margin:0 0 12px}
.top{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(250px,.75fr);gap:26px;border-bottom:2px solid #111;padding-bottom:11px;margin-bottom:14px;align-items:start;font-size:14px}
.meta-row{display:grid;grid-template-columns:140px 1fr;gap:10px;padding:2px 0;align-items:start}
.label{font-weight:900;text-transform:uppercase;white-space:nowrap}
.value{font-weight:700;border-bottom:1px solid #777;min-height:18px;padding:0 2px 2px;overflow-wrap:anywhere}
.customer-box{border:1.5px solid #111;padding:10px 11px;min-height:58px;font-size:14px}
.customer-label{font-size:14px;font-weight:900;letter-spacing:.8px;text-transform:uppercase;margin-bottom:7px}
.customer-value{font-size:14px;font-weight:900;line-height:1.35;overflow-wrap:anywhere}
.section-title{font-size:13px;font-weight:900;letter-spacing:.7px;text-transform:uppercase;margin:13px 0 5px}
table{width:100%;border-collapse:collapse;table-layout:fixed}
thead{display:table-header-group}
tr{break-inside:avoid;page-break-inside:avoid}
th,td{border:1px solid #111;padding:4.5px 5px;vertical-align:middle;overflow-wrap:anywhere}
th{font-size:13px;font-weight:900;text-transform:uppercase;background:#fff;line-height:1.2}
.transactions th:nth-child(1){width:11%}.transactions th:nth-child(2){width:17%}.transactions th:nth-child(3){width:14%}.transactions th:nth-child(4){width:16%}.transactions th:nth-child(5){width:13%}.transactions th:nth-child(6){width:14%}.transactions th:nth-child(7){width:15%}
.payment-details th:nth-child(1){width:22%}.payment-details th:nth-child(2){width:24%}.payment-details th:nth-child(3){width:18%}.payment-details th:nth-child(4){width:18%}.payment-details th:nth-child(5){width:18%}
.right{text-align:right}.strong{font-weight:900}.empty{text-align:center;color:#666;font-style:italic;padding:12px}
.total{margin:14px 0 0 auto;width:360px;border:2px solid #111;display:grid;grid-template-columns:1fr 155px;align-items:center;font-size:13px;font-weight:900}
.total.paid-summary{margin-top:10px;margin-bottom:12px}
.total span{padding:8px 10px}.total .amount{text-align:right;border-left:2px solid #111;font-size:13px}
@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact}.customer-box,.total{break-inside:avoid;page-break-inside:avoid}}
</style></head><body><div class="page">
<div class="doc-title">Payment History</div>
<div class="top">
    <div>
        <div class="meta-row"><span class="label">Address:</span><span class="value">${escapeHtml(customer.address || '---')}</span></div>
        <div class="meta-row"><span class="label">Collection No.:</span><span class="value">${escapeHtml(printable.collectionNo || payment.payment_no || '---')}</span></div>
        <div class="meta-row"><span class="label">Date:</span><span class="value">${escapeHtml(printable.paymentDate || payment.payment_date || '---')}</span></div>
    </div>
    <div class="customer-box">
        <div class="customer-label">Customer:</div>
        <div class="customer-value">${escapeHtml(customer.name || payment.customer_name || '---')}</div>
    </div>
</div>

<div class="section-title">Invoice Details</div>
<table class="transactions"><thead><tr><th>Date</th><th>Invoice No.</th><th class="right">Inv. Amount</th><th>Slip (Return No.)</th><th class="right">Adjustment</th><th class="right">Amount Due</th><th class="right">Amount Paid</th></tr></thead><tbody>${transactionRows}</tbody></table>

<div class="total paid-summary"><span>Total Paid Amount</span><span class="amount">${money.format(totalAmount)}</span></div>

<div class="section-title">Payment Details</div>
<table class="payment-details"><thead><tr><th>Account No.</th><th>Bank Name</th><th>Check No.</th><th>Check Date</th><th class="right">Check Amount</th></tr></thead><tbody>${paymentRows}</tbody></table>

<div class="total"><span>Total Amount</span><span class="amount">${money.format(totalAmount)}</span></div>
</div></body></html>`;

    const target = printWindow || window.open('', '_blank', 'width=900,height=700');
    if (!target) {
        showNotification('Please allow popups to print the whole payment.', 'error');
        return;
    }
    target.document.open();
    target.document.write(html);
    target.document.close();
    target.focus();
    setTimeout(() => target.print(), 300);
}

window.printWholePaymentHistory = async function(paymentId) {
    // Open immediately from the click so browsers do not block the print window
    // while the full Payment No. detail is being loaded.
    const printWindow = window.open('', '_blank', 'width=900,height=700');
    if (!printWindow) {
        showNotification('Please allow popups to print the whole payment.', 'error');
        return;
    }
    printWindow.document.write('<!doctype html><html><body style="font-family:Arial,sans-serif;padding:28px;font-size:12px">Loading whole payment...</body></html>');
    printWindow.document.close();

    try {
        const detail = await fetchPaymentHistoryDetail(paymentId);
        openWholePaymentHistoryLayout(detail, printWindow);
    } catch (error) {
        printWindow.close();
        showNotification(error.message || 'Failed to load the whole payment.', 'error');
    }
};

window.confirmDeletePaymentHistory = async function(paymentId, paymentNo) {
    const label = paymentNo || 'this payment';
    const ok = window.confirm(`Delete ${label}?\n\nAll invoices under this Payment No. will become unpaid again and return to Process Payment.`);
    if (!ok) return;
    pendingDeletePaymentId = paymentId;
    pendingDeletePaymentNo = paymentNo || '';
    await window.submitDeletePaymentHistory();
};

window.closePaymentDeleteConfirmModal = function() {
    const modal = document.getElementById('payment-delete-confirm-modal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
};

window.closePaymentDeleteSuccessModal = function() {
    const modal = document.getElementById('payment-delete-success-modal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
};

window.submitDeletePaymentHistory = async function() {
    if (!pendingDeletePaymentId) return;

    try {
        const res = await fetch(routes().historyDelete.replace(':paymentId', pendingDeletePaymentId), {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken()
            }
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Unable to delete payment.');

        closePaymentDeleteConfirmModal();
        setText('payment-delete-success-message', `${pendingDeletePaymentNo || 'The selected payment'} was deleted successfully.`);
        const successModal = document.getElementById('payment-delete-success-modal');
        successModal?.classList.remove('hidden');
        successModal?.classList.add('flex');

        paymentsPage = 1;
        loadPaymentsTable();
        paymentsHistoryPage = 1;
        historyHasLoaded = true;
        loadPaymentsHistoryTable();
        if (currentPayorHistoryCustomerId && !document.getElementById('payor-history-modal')?.classList.contains('hidden')) loadPayorHistoryInvoices();

        pendingDeletePaymentId = null;
        pendingDeletePaymentNo = '';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    } catch (error) {
        showNotification(error.message || 'Failed to delete payment.', 'error');
    }
};

async function fetchStoredPaymentReturn(returnId) {
    const id = Number(returnId || 0);
    if (!id) throw new Error('Return record not found.');
    const response = await fetch(routes().returnLookup.replace(':returnId', id), { headers: { 'Accept': 'application/json' } });
    const data = await response.json();
    if (!response.ok || !data.success) throw new Error(data.message || 'Return record not found.');
    return data.return || {};
}

window.openReturnDetailsModal = async function(returnId) {
    const id = Number(returnId || 0);
    if (!id) return;
    try {
        const ret = await fetchStoredPaymentReturn(id);
        renderReturnDetailsView([ret]);
        const modal = document.getElementById('payment-return-details-modal');
        if (!modal) return;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    } catch (error) {
        showNotification(error.message || 'Return record not found.', 'error');
    }
};

function printReturnRecord(ret) {
    const returnId = Number(ret?.return_order_id || ret?.id || ret?.sales_return_id || 0);
    if (!returnId) return;
    const items = ret.items || [];
    const total = Number(ret.wallet_adjustment ?? ret.total_amount ?? ret.return_amount ?? 0);
    const html = `<!doctype html><html><head><meta charset="utf-8"><title>Return ${escapeHtml(ret.return_number || '')}</title><style>body{font-family:Arial,sans-serif;padding:24px;color:#111}.c{text-align:center;font-weight:800;font-size:19px}.s{text-align:center;font-weight:700;margin:4px 0 20px}table{width:100%;border-collapse:collapse;font-size:11px}th,td{border:1px solid #111;padding:7px}.r{text-align:right}.meta{margin:16px 0;line-height:1.8}@media print{@page{margin:8mm}body{padding:8px}}</style></head><body><div class="c">W68 AUTO PARTS &amp; SERVICE CENTER</div><div class="s">Sales Return</div><div class="meta"><strong>RETURN ORDER ID:</strong> ${escapeHtml(returnId)}<br><strong>RETURN NO.:</strong> ${escapeHtml(ret.return_number || '---')}<br><strong>DATE:</strong> ${escapeHtml(ret.return_date || '---')}<br><strong>INVOICE NO.:</strong> ${escapeHtml(ret.invoice_no || '---')}</div><table><thead><tr><th>PRODUCT CODE</th><th>APPLICATION</th><th>DESCRIPTION</th><th>QTY</th><th class="r">AMOUNT</th></tr></thead><tbody>${items.map(item => `<tr><td>${escapeHtml(item.product_code || '---')}</td><td>${escapeHtml(item.application || '---')}</td><td>${escapeHtml(item.description || '---')}</td><td>${escapeHtml(item.quantity ?? '')}</td><td class="r">${money.format(Number(item.amount || 0))}</td></tr>`).join('') || '<tr><td colspan="5">No item details.</td></tr>'}<tr><td colspan="4" class="r"><strong>WALLET &amp; ADJUSTMENT</strong></td><td class="r"><strong>${money.format(total)}</strong></td></tr></tbody></table></body></html>`;
    const win = window.open('', '_blank', 'width=850,height=650');
    if (!win) { showNotification('Please allow popups to print the return.', 'error'); return; }
    win.document.open(); win.document.write(html); win.document.close(); win.focus(); setTimeout(() => win.print(), 250);
}

window.printPaymentReturn = async function(returnId) {
    const id = Number(returnId || 0);
    if (!id) return;
    try {
        const ret = await fetchStoredPaymentReturn(id);
        printReturnRecord(ret);
    } catch (error) {
        showNotification(error.message || 'Return record not found.', 'error');
    }
};

window.closeReturnDetailsModal = function() {
    const modal = document.getElementById('payment-return-details-modal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = 'auto';
};

function renderReturnDetailsView(returns) {
    const container = document.getElementById('return-details-content');
    if (!container) return;

    container.innerHTML = returns.map(ret => `
        <div class="border border-slate-100 rounded-2xl overflow-hidden mb-4">
            <div class="grid grid-cols-1 lg:grid-cols-[30%_70%]">
                <div class="bg-slate-50 p-5 border-r border-slate-100">
                    <h5 class="text-[10px] font-black text-maroon uppercase tracking-widest mb-4">Return Info</h5>
                    <div class="space-y-3">
                        <div>
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Return No.</span>
                            <p class="text-xs font-extrabold text-slate-800 mt-1">${escapeHtml(ret.return_number)}</p>
                        </div>
                        <div>
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Return Date</span>
                            <p class="text-xs font-extrabold text-slate-800 mt-1">${escapeHtml(ret.return_date)}</p>
                        </div>
                        <div>
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Remarks</span>
                            <p class="text-xs font-extrabold text-slate-800 mt-1">${escapeHtml(ret.remarks || '---')}</p>
                        </div>
                    </div>
                </div>
                <div class="p-5 overflow-x-auto">
                    <table class="w-full text-left return-items-table">
                        <thead class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">
                            <tr>
                                <th class="pb-2 pr-2">Product Code<br><input type="text" onkeyup="window.filterReturnItemsTable(this, 0)" class="col-search-input" placeholder="Search"></th>
                                <th class="pb-2 pr-2">Application<br><input type="text" onkeyup="window.filterReturnItemsTable(this, 1)" class="col-search-input" placeholder="Search"></th>
                                <th class="pb-2 pr-2">Description<br><input type="text" onkeyup="window.filterReturnItemsTable(this, 2)" class="col-search-input" placeholder="Search"></th>
                                <th class="pb-2 pr-2">Qty<br><input type="text" onkeyup="window.filterReturnItemsTable(this, 3)" class="col-search-input" placeholder="Search"></th>
                                <th class="pb-2">Amount<br><input type="text" onkeyup="window.filterReturnItemsTable(this, 4)" class="col-search-input" placeholder="Search"></th>
                            </tr>
                        </thead>
                        <tbody class="text-[10px] divide-y divide-slate-50 return-items-tbody">
                            ${(ret.items || []).map(item => `
                                <tr>
                                    <td class="py-2 pr-2 font-bold text-slate-600">${escapeHtml(item.product_code || '---')}</td>
                                    <td class="py-2 pr-2 font-semibold text-slate-500">${escapeHtml(item.application || '---')}</td>
                                    <td class="py-2 pr-2 text-slate-600">${escapeHtml(item.description || '---')}</td>
                                    <td class="py-2 pr-2 font-bold text-slate-600">${item.quantity}</td>
                                    <td class="py-2 font-bold text-maroon">${money.format(item.amount)}</td>
                                </tr>
                            `).join('') || '<tr><td colspan="5" class="py-4 text-center text-slate-400 font-bold">No items found.</td></tr>'}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    `).join('');

    if (typeof lucide !== 'undefined') lucide.createIcons();
}

window.filterReturnItemsTable = function(input, colIndex) {
    const val = (input.value || '').toLowerCase();
    const table = input.closest('table');
    if (!table) return;
    const tbody = table.querySelector('.return-items-tbody') || table.querySelector('tbody');
    if (!tbody) return;
    tbody.querySelectorAll('tr').forEach(row => {
        const cell = row.children[colIndex];
        if (!cell) return;
        const text = cell.textContent.toLowerCase();
        row.style.display = text.includes(val) ? '' : 'none';
    });
};

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));
}

function escapeAttr(value) {
    return escapeHtml(value).replace(/`/g, '&#096;');
}

// Scroll to customer row from notification click
function highlightPayorFromNotification() {
    const highlightCustomerName = sessionStorage.getItem('highlightCustomerName');
    const highlightType = sessionStorage.getItem('highlightType');
    if (highlightType !== 'invoice') return;

    sessionStorage.removeItem('highlightCustomerName');
    sessionStorage.removeItem('highlightType');

    const tbody = document.getElementById('payments-tbody');
    if (!tbody || !highlightCustomerName) {
        return;
    }

    const name = highlightCustomerName.trim().toLowerCase().replace(/\s+/g, ' ');
    const rows = tbody.querySelectorAll('tr');
    let row = null;
    for (const r of rows) {
        const nameCell = r.querySelector('td:first-child span:first-child');
        if (nameCell) {
            const cellText = nameCell.textContent.trim().toLowerCase().replace(/\s+/g, ' ');
            if (cellText === name) {
                row = r;
                break;
            }
        }
    }

    if (row) {
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        row.style.borderLeft = '4px solid #dc2626';
        row.style.boxShadow = '0 0 12px rgba(220, 38, 38, 0.3)';
        setTimeout(() => {
            row.style.borderLeft = '';
            row.style.boxShadow = '';
        }, 8000);
    }
}
