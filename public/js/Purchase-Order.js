// Purchase Order Management Logic
let isFinalizingPO = false;
const PO_SYNC_MIN_VISIBLE_MS = 3500;
let lastPOFinalizePayload = null;
let lastPOResyncId = null;

function clearPurchaseOrderOneTimeQueryParams() {
    try {
        const url = new URL(window.location.href);
        let changed = false;
        ['open_edit_po'].forEach((key) => {
            if (url.searchParams.has(key)) {
                url.searchParams.delete(key);
                changed = true;
            }
        });
        if (changed) {
            window.history.replaceState({}, document.title, url.pathname + (url.search ? url.search : '') + url.hash);
        }
    } catch (error) {
        console.warn('Unable to clear one-time Purchase Order query parameters:', error);
    }
}

// Export functions to window for global access immediately
window.toggleModal = toggleModal;
window.openProceedModal = openProceedModal;
window.goToStep = goToStep;
window.selectSupplier = selectSupplier;
window.filterSupplierTable = filterSupplierTable;
window.filterSupplierTableByCol = filterSupplierTableByCol;
window.updateCalculations = updateCalculations;
window.confirmProceed = confirmProceed;
window.finalizePO = finalizePO;
window.retryPurchaseOrderSync = retryPurchaseOrderSync;
window.completeSuccessfulPOProcess = completeSuccessfulPOProcess;
window.openForceClosePurchaseNoteModal = openForceClosePurchaseNoteModal;
window.closeForceClosePurchaseNoteModal = closeForceClosePurchaseNoteModal;
window.toggleForceCloseConfirmButton = toggleForceCloseConfirmButton;
window.submitForceClosePurchaseNote = submitForceClosePurchaseNote;
window.switchTab = switchTab;
window.openHistoryViewModal = openHistoryViewModal;
window.applyHistoryFilters = applyHistoryFilters;
window.resetHistoryFilters = resetHistoryFilters;
window.addInvoiceInput = addInvoiceInput;
window.toggleAllPOItems = toggleAllPOItems;
// openEditHistoryModal and printHistoryPO are already assigned to window at their definition sites

document.addEventListener('DOMContentLoaded', () => {
    // Initialize Lucide icons
    if (window.lucide) {
        window.lucide.createIcons();
    }

    // Initialize state
    window.poState = {
        currentStep: 1,
        selectedItems: [],
        conversionRate: 1,
        currency: 'PHP'
    };

    // Pagination state
    window.activeSearch = {};
    window.historySearch = {};
    window.activePage = 1;
    window.historyPage = 1;
    window.activePagination = null;
    window.historyPagination = null;

    // Load dashboard cards
    loadDashboardCards();

    // Load initial data
    fetchActiveOrders();

    // Auto-open edit modal from purchase note redirect
    const urlParams = new URLSearchParams(window.location.search);
    const openEditPo = urlParams.get('open_edit_po');
    if (openEditPo) {
        // Add a small delay to let lucide icons initialize and tab render
        setTimeout(() => {
            // Switch to history tab
            const historyBtn = document.querySelector('[data-tab="history"]');
            if (historyBtn) historyBtn.click();
            // Open edit modal for the PO
            openEditHistoryModal(parseInt(openEditPo));
        }, 500);
    }

    // Auto-open proceed modal from purchase note redirect (Open PO)
    const proceedData = sessionStorage.getItem('proceed_note_data');
    if (proceedData) {
        sessionStorage.removeItem('proceed_note_data');
        try {
            const data = JSON.parse(proceedData);
            setTimeout(() => {
                const activeBtn = document.querySelector('[data-tab="active"]');
                if (activeBtn) activeBtn.click();
                openProceedModal(data);
            }, 500);
        } catch (e) {
            console.error('Failed to parse proceed_note_data:', e);
        }
    }
});

async function loadDashboardCards() {
    try {
        const response = await fetch(window.purchaseRoutes?.dashboardDataUrl || '/hatdog/public/admin/purchase/purchase-order/dashboard');
        const result = await response.json();

        if (result.success) {
            document.querySelector('#total-active-po h3').textContent = result.total_active.toLocaleString();
            document.querySelector('#total-open h3').textContent = result.total_open.toLocaleString();
            document.querySelector('#total-partial h3').textContent = result.total_partial.toLocaleString();
            document.querySelector('#total-purchase-orders h3').textContent = result.total_purchase_orders.toLocaleString();
        }
    } catch (error) {
        console.error('Error loading dashboard cards:', error);
    }
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => { clearTimeout(timeout); func(...args); };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

async function fetchActiveOrders(page) {
    if (page !== undefined) window.activePage = page;
    try {
        const params = new URLSearchParams();
        params.set('page', window.activePage);
        params.set('perPage', 50);
        for (const [col, val] of Object.entries(window.activeSearch)) {
            if (val) params.set('search[' + col + ']', val);
        }
        const url = (window.purchaseRoutes?.activeDataUrl || '/admin/purchase/purchase-order/active') + '?' + params.toString();
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const result = await res.json();
        if (!result.success) throw new Error(result.message || 'Failed');
        renderActiveOrders(result.orders || []);
        window.activePagination = result.pagination || null;
        renderPagination('active-po-pagination', window.activePagination, 'active');
    } catch (e) {
        console.error('Error fetching active orders:', e);
        renderActiveOrders([]);
        window.activePagination = null;
        renderPagination('active-po-pagination', null, 'active');
    }
}

async function fetchHistoryOrders(page) {
    if (page !== undefined) window.historyPage = page;
    window.historySearch = getHistorySearchValues();
    try {
        const params = new URLSearchParams();
        params.set('page', window.historyPage);
        params.set('perPage', 50);
        for (const [col, val] of Object.entries(window.historySearch)) {
            if (val) params.set('search[' + col + ']', val);
        }
        const url = (window.purchaseRoutes?.historyDataUrl || '/hatdog/public/admin/purchase/purchase-order/history') + '?' + params.toString();
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const result = await res.json();
        if (!result.success) throw new Error(result.message || 'Failed');
        renderHistoryOrders(result.orders || []);
        window.historyPagination = result.pagination || null;
        renderPagination('history-po-pagination', window.historyPagination, 'history');
    } catch (e) {
        console.error('Error fetching history orders:', e);
        renderHistoryOrders([]);
        window.historyPagination = null;
        renderPagination('history-po-pagination', null, 'history');
    }
}

function renderActiveOrders(orders) {
    const tbody = document.getElementById('main-purchase-order-tbody');
    if (!tbody) return;

    if (!orders.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="py-16 text-center text-slate-400 italic"><div class="flex flex-col items-center"><i data-lucide="search-x" class="w-10 h-10 mb-2 opacity-20"></i>No active purchase orders found</div></td></tr>';
        if (window.lucide) window.lucide.createIcons();
        return;
    }

    tbody.innerHTML = orders.map(o => {
        const statusClass = o.status === 'Pending' ? 'bg-blue-100 text-blue-600' : o.status === 'Partial' ? 'bg-amber-100 text-amber-600' : 'bg-purple-100 text-purple-600';
        const currency = o.currency || 'PHP';
        return `
        <tr class="table-row-animate transition-all duration-200">
            <td class="p-4 px-6 font-bold text-maroon">${o.supplierCode}</td>
            <td class="p-4 px-6 text-slate-700">${o.name}</td>
            <td class="p-4 px-6 font-mono text-xs text-slate-500">${o.transNo || '---'}</td>
            <td class="p-4 px-6 text-center text-slate-600">${o.date || '---'}</td>
            <td class="p-4 px-6 text-center font-bold ${currency === 'PHP' ? 'text-green-600' : 'text-blue-600'}">${currency}</td>
            <td class="p-4 px-6 text-center"><span class="px-3 py-1 text-[10px] font-bold rounded-full ${statusClass} uppercase tracking-wider">${o.status}</span></td>
            <td class="p-4 px-6 text-center">
                <div class="flex items-center justify-center space-x-1">
                    <button onclick='openProceedModal(${JSON.stringify({
                        supplierCode: o.supplierCode || '',
                        name: o.name || '',
                        transNo: o.transNo || '',
                        po_number: o.po_number || '',
                        poId: o.id,
                        status: o.status || '',
                        existing_invoice_numbers: Array.isArray(o.existing_invoice_numbers) ? o.existing_invoice_numbers : []
                    }).replace(/'/g, '&#39;')})' class="px-4 py-1.5 bg-maroon text-white hover:bg-maroon-800 text-[10px] font-bold rounded-lg shadow-sm transition-all flex items-center space-x-1">
                        <span>Proceed</span>
                        <i data-lucide="chevron-right" class="w-3 h-3 text-gold"></i>
                    </button>
                </div>
            </td>
        </tr>`;
    }).join('');

    if (window.lucide) window.lucide.createIcons();
}

function renderHistoryOrders(orders) {
    const tbody = document.getElementById('history-purchase-order-tbody');
    if (!tbody) return;

    const showPoNo = !!document.getElementById('col-search-h-poNo');
    const columnCount = showPoNo ? 8 : 7;

    if (!orders.length) {
        tbody.innerHTML = `<tr><td colspan="${columnCount}" class="py-16 text-center text-slate-400 italic"><div class="flex flex-col items-center"><i data-lucide="search-x" class="w-10 h-10 mb-2 opacity-20"></i>No purchase history found</div></td></tr>`;
        if (window.lucide) window.lucide.createIcons();
        return;
    }

    tbody.innerHTML = orders.map(o => {
        const remarksClass = o.remarks && o.remarks.toLowerCase().includes('full') ? 'bg-green-100 text-green-600' : 'bg-slate-100 text-slate-600';
        const poNoCell = showPoNo ? `<td class="p-4 px-6 font-mono text-xs text-slate-500">${o.poNo || '---'}</td>` : '';
        return `
        <tr class="hover:bg-slate-50 transition-all duration-200 group">
            <td class="p-4 px-6 text-slate-600">${o.date || '---'}</td>
            <td class="p-4 px-6 font-mono text-xs text-slate-500">${o.receivingNo || '---'}</td>
            ${poNoCell}
            <td class="p-4 px-6 text-slate-600">${o.supplierInvoice || '---'}</td>
            <td class="p-4 px-6 font-bold text-slate-700">${o.name}</td>
            <td class="p-4 px-6 text-right font-extrabold text-slate-800">${o.currency === 'PHP' ? '₱' : '$'} ${parseFloat(o.displayTotal || o.totalAmount || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
            <td class="p-4 px-6"><span class="px-3 py-1 text-[10px] font-bold rounded-full ${remarksClass} uppercase tracking-wider">${o.remarks || '---'}</span></td>
            <td class="p-4 px-6">
                <div class="flex items-center justify-center space-x-2">
                    <button onclick='openHistoryViewModal(${JSON.stringify(o).replace(/'/g, "&#39;")})' class="p-2 bg-sky-500 hover:bg-sky-600 text-white rounded-lg transition-all shadow-sm" title="View">
                        <i data-lucide="eye" class="w-4 h-4"></i>
                    </button>
                    <button onclick="openEditHistoryModal(${o.id})" class="p-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition-all shadow-sm" title="Edit">
                        <i data-lucide="edit" class="w-4 h-4"></i>
                    </button>
                    <button onclick="printHistoryPO(${o.id})" class="p-2 bg-maroon hover:bg-maroon/90 text-white rounded-lg transition-all shadow-sm" title="Print">
                        <i data-lucide="printer" class="w-4 h-4"></i>
                    </button>
                </div>
            </td>
        </tr>`;
    }).join('');

    if (window.lucide) window.lucide.createIcons();
}

function renderPagination(containerId, pagination, tab) {
    const container = document.getElementById(containerId);
    if (!container) return;

    const fetchFn = tab === 'active' ? 'fetchActiveOrders' : 'fetchHistoryOrders';
    const pageButton = (label, page, disabled, extraClass = '') => {
        const disabledAttr = disabled ? ' disabled aria-disabled="true"' : '';
        const onClick = disabled ? '' : ' onclick="' + fetchFn + '(' + page + ')"';
        return '<button type="button"' + onClick + disabledAttr + ' class="px-2 py-1 text-[10px] rounded-lg font-bold ' + (disabled ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600 hover:bg-slate-100') + ' ' + extraClass + '">' + label + '</button>';
    };

    if (!pagination) {
        container.innerHTML = '<div class="flex items-center justify-between flex-wrap gap-3"><div class="text-[11px] text-slate-500">Showing 0 entries</div><div class="flex items-center space-x-1">' + pageButton('Previous', 1, true) + pageButton('Next', 1, true) + '</div></div>';
        return;
    }

    const p = pagination;
    if (p.last_page <= 1) {
        const from = p.total > 0 ? (p.from || 1) : 0;
        const to = p.total > 0 ? (p.to || p.total) : 0;
        container.innerHTML = '<div class="flex items-center justify-between flex-wrap gap-3"><div class="text-[11px] text-slate-500">Page 1 of 1 (Showing ' + from + ' to ' + to + ' of ' + p.total + ' entries)</div><div class="flex items-center space-x-1">' + pageButton('Previous', 1, true) + pageButton('Next', 1, true) + '</div></div>';
        return;
    }

    let html = '<div class="flex items-center justify-between flex-wrap gap-3">';
    html += '<div class="text-[11px] text-slate-500">Page ' + p.current_page + ' of ' + p.last_page + ' (Showing ' + p.from + ' to ' + p.to + ' of ' + p.total + ' entries)</div>';
    html += '<div class="flex items-center space-x-1">';

    html += pageButton('&laquo;', 1, p.current_page === 1);
    html += pageButton('&lsaquo;', Math.max(1, p.current_page - 1), p.current_page === 1);

    const range = 2;
    let start = Math.max(1, p.current_page - range);
    let end = Math.min(p.last_page, p.current_page + range);
    if (start > 1) { html += '<button onclick="' + fetchFn + '(1)" class="px-2 py-1 text-[10px] font-bold rounded-lg text-slate-600 hover:bg-slate-100">1</button>'; if (start > 2) html += '<span class="px-1 text-slate-300">...</span>'; }
    for (let i = start; i <= end; i++) {
        html += '<button onclick="' + fetchFn + '(' + i + ')" class="px-2.5 py-1 text-[10px] font-bold rounded-lg ' + (i === p.current_page ? 'bg-maroon text-white' : 'text-slate-600 hover:bg-slate-100') + '">' + i + '</button>';
    }
    if (end < p.last_page) { if (end < p.last_page - 1) html += '<span class="px-1 text-slate-300">...</span>'; html += '<button onclick="' + fetchFn + '(' + p.last_page + ')" class="px-2 py-1 text-[10px] font-bold rounded-lg text-slate-600 hover:bg-slate-100">' + p.last_page + '</button>'; }

    html += pageButton('&rsaquo;', Math.min(p.last_page, p.current_page + 1), p.current_page === p.last_page);
    html += pageButton('&raquo;', p.last_page, p.current_page === p.last_page);

    html += '</div></div>';
    container.innerHTML = html;
}

function getActiveSearchValues() {
    const cols = ['supplierCode', 'name', 'transNo', 'date', 'currency', 'status'];
    const ids = ['col-search-supplierCode', 'col-search-name', 'col-search-transNo', 'col-search-date', 'col-search-currency', 'col-search-status'];
    const sv = {};
    ids.forEach((id, i) => {
        const val = document.getElementById(id)?.value || '';
        if (val) sv[cols[i]] = val;
    });
    return sv;
}

function getHistorySearchValues() {
    const fields = [
        ['date', 'col-search-h-date'],
        ['recNo', 'col-search-h-recNo'],
        ['poNo', 'col-search-h-poNo'],
        ['invNo', 'col-search-h-invNo'],
        ['name', 'col-search-h-name'],
        ['totalAmount', 'col-search-h-totalAmount'],
        ['remarks', 'col-search-h-remarks'],
    ];
    const values = {};
    fields.forEach(([key, id]) => {
        const input = document.getElementById(id);
        const value = input?.value?.trim() || '';
        if (value) values[key] = value;
    });
    return values;
}

const debouncedActiveSearch = debounce(() => {
    window.activeSearch = getActiveSearchValues();
    window.activePage = 1;
    fetchActiveOrders(1);
}, 300);

const debouncedHistorySearch = debounce(() => {
    window.historySearch = getHistorySearchValues();
    window.historyPage = 1;
    fetchHistoryOrders(1);
}, 300);

window.filterTable = function(tbodyId, colIndex, value) {
    // Determine which tab based on tbodyId
    if (tbodyId === 'main-purchase-order-tbody') {
        debouncedActiveSearch();
    } else if (tbodyId === 'history-purchase-order-tbody') {
        debouncedHistorySearch();
    }
};
function switchTab(tab) {
    const activeBtn = document.getElementById('tab-btn-active');
    const historyBtn = document.getElementById('tab-btn-history');
    const activeContent = document.getElementById('tab-content-active');
    const historyContent = document.getElementById('tab-content-history');

    if (tab === 'active') {
        activeBtn.classList.add('bg-maroon', 'text-white', 'shadow-md');
        activeBtn.classList.remove('text-slate-500', 'hover:bg-slate-50');
        historyBtn.classList.add('text-slate-500', 'hover:bg-slate-50');
        historyBtn.classList.remove('bg-maroon', 'text-white', 'shadow-md');
        
        activeContent.classList.remove('hidden');
        historyContent.classList.add('hidden');
        fetchActiveOrders();
    } else {
        historyBtn.classList.add('bg-maroon', 'text-white', 'shadow-md');
        historyBtn.classList.remove('text-slate-500', 'hover:bg-slate-50');
        activeBtn.classList.add('text-slate-500', 'hover:bg-slate-50');
        activeBtn.classList.remove('bg-maroon', 'text-white', 'shadow-md');
        
        historyContent.classList.remove('hidden');
        activeContent.classList.add('hidden');
        fetchHistoryOrders();
    }
}



/**
 * Purchase Order modal column searches are view-only. They never remove rows
 * from editState and never alter quantities, costs, totals, or saved values.
 */
function poModalCellSearchText(cell) {
    if (!cell) return '';
    const values = [cell.textContent || ''];
    cell.querySelectorAll('input, select, textarea').forEach(control => {
        values.push(control.value || '');
        if (control.tagName === 'SELECT') {
            values.push(control.options?.[control.selectedIndex]?.text || '');
        }
    });
    return values.join(' ').toLowerCase();
}

function ensurePurchaseOrderColumnSearch(kind) {
    const tbody = document.getElementById(kind === 'history' ? 'history-view-items-tbody' : 'edit-items-tbody');
    const table = tbody?.closest('table');
    const thead = table?.querySelector('thead');
    const headerRow = thead?.querySelector('tr');
    if (!tbody || !thead || !headerRow) return;

    const className = kind === 'history' ? 'po-history-detail-search-row' : 'po-edit-item-search-row';
    if (thead.querySelector('.' + className)) return;

    const searchRow = document.createElement('tr');
    searchRow.className = `${className} bg-white border-b border-slate-100`;
    Array.from(headerRow.children).forEach((header, index) => {
        const th = document.createElement('th');
        th.className = 'p-1';
        const input = document.createElement('input');
        input.type = 'text';
        input.dataset.poSearchKind = kind;
        input.dataset.poSearchCol = String(index);
        input.placeholder = 'Search...';
        input.className = 'w-full min-w-[72px] px-2 py-1.5 text-[9px] font-semibold text-slate-600 bg-white border border-slate-200 rounded-md outline-none focus:border-maroon focus:ring-1 focus:ring-maroon/20';
        input.addEventListener('input', () => filterPurchaseOrderModalRows(kind));
        th.appendChild(input);
        searchRow.appendChild(th);
    });
    thead.appendChild(searchRow);
}

function filterPurchaseOrderModalRows(kind) {
    const tbody = document.getElementById(kind === 'history' ? 'history-view-items-tbody' : 'edit-items-tbody');
    if (!tbody) return;
    const filters = Array.from(document.querySelectorAll(`input[data-po-search-kind="${kind}"]`))
        .map(input => ({ col: Number(input.dataset.poSearchCol), value: (input.value || '').trim().toLowerCase() }))
        .filter(filter => filter.value !== '');

    Array.from(tbody.querySelectorAll('tr')).forEach(row => {
        // Empty/loading/error rows remain visible only when there is no search.
        if (row.cells.length <= 1) {
            row.style.display = filters.length ? 'none' : '';
            return;
        }
        const matches = filters.every(filter => poModalCellSearchText(row.cells[filter.col]).includes(filter.value));
        row.style.display = matches ? '' : 'none';
    });
}

function resetPurchaseOrderColumnSearch(kind) {
    document.querySelectorAll(`input[data-po-search-kind="${kind}"]`).forEach(input => { input.value = ''; });
    filterPurchaseOrderModalRows(kind);
}

function ensureFixArrangeButton() {
    if (document.getElementById('edit-fix-arrange-btn')) return;
    const tbody = document.getElementById('edit-items-tbody');
    const step = tbody?.closest('#edit-step-2');
    const headingRow = step?.querySelector('.flex.justify-between.items-center.mb-4');
    if (!headingRow) return;

    const rightGroup = headingRow.querySelector('.text-xs.text-slate-500');
    const button = document.createElement('button');
    button.id = 'edit-fix-arrange-btn';
    button.type = 'button';
    button.onclick = window.fixPurchaseOrderArrangement;
    button.className = 'mr-3 px-4 py-2 bg-violet-600 hover:bg-violet-700 text-yellow-300 rounded-lg text-[10px] font-bold uppercase tracking-wider shadow-sm transition-all';
    button.innerHTML = '<span>Fix Arrange</span>';

    if (rightGroup) {
        const wrapper = document.createElement('div');
        wrapper.className = 'flex items-center';
        headingRow.insertBefore(wrapper, rightGroup);
        wrapper.appendChild(button);
        wrapper.appendChild(rightGroup);
    } else {
        headingRow.appendChild(button);
    }
}

window.fixPurchaseOrderArrangement = function() {
    if (!window.editState || !Array.isArray(window.editState.items)) return;

    // Capture any unsaved input values first so arranging can never reset edits.
    document.querySelectorAll('#edit-items-tbody tr[data-item-index]').forEach(row => {
        const index = Number.parseInt(row.dataset.itemIndex || '', 10);
        const item = Number.isInteger(index) ? window.editState.items[index] : null;
        if (!item) return;
        const qty = row.querySelector('.item-qty');
        const actualQty = row.querySelector('.item-actual-qty');
        const unit = row.querySelector('.item-unit');
        const unitPrice = row.querySelector('.item-unit-cost');
        const foreignPrice = row.querySelector('.item-unit-cost-f');
        const currency = row.querySelector('.item-currency-f');
        const disc = row.querySelector('.item-disc');
        if (qty) item.quantity = parseFloat(qty.value) || 0;
        if (actualQty) item.actual_quantity = parseFloat(actualQty.value) || 0;
        if (unit) item.unit = unit.value;
        if (unitPrice) item.unit_price = parseFloat(unitPrice.value) || 0;
        if (foreignPrice) item.unit_price_converted = parseFloat(foreignPrice.value) || 0;
        if (currency) item.currency = currency.value;
        if (disc) item.discount_percent = parseFloat(disc.value) || 0;
    });

    window.editState.items = window.editState.items
        .map((item, originalIndex) => ({ item, originalIndex }))
        .sort((a, b) => {
            const ao = Number(a.item?.pn_order);
            const bo = Number(b.item?.pn_order);
            const am = Number.isFinite(ao) && ao > 0;
            const bm = Number.isFinite(bo) && bo > 0;
            if (am && bm && ao !== bo) return ao - bo;
            if (am !== bm) return am ? -1 : 1;
            return a.originalIndex - b.originalIndex;
        })
        .map(({ item }) => item);

    renderEditItems(window.editState.items);
    ensurePurchaseOrderColumnSearch('edit');
    filterPurchaseOrderModalRows('edit');

    const button = document.getElementById('edit-fix-arrange-btn');
    if (button) {
        const original = button.innerHTML;
        button.innerHTML = '<span>Arranged</span>';
        setTimeout(() => { button.innerHTML = original; }, 1200);
    }
};

/**
 * Open history view modal and populate data
 */
function openHistoryViewModal(data) {
    if (!data) return;

    const currency = data.currency || 'PHP';
    const symbolsMap = { 'PHP': '₱', 'USD': '$', 'TWD': 'NT$' };
    const symbol = symbolsMap[currency] || '₱';

    // Populate Left Side (Summary)
    document.getElementById('h-view-date').innerText = data.date || data.dateIssue || '---';
    document.getElementById('h-view-receiving').innerText = data.receivingNo || '---';
    document.getElementById('h-view-po').innerText = data.poNo || '---';
    document.getElementById('h-view-invoice').innerText = data.supplierInvoice || '---';
    document.getElementById('h-view-name').innerText = data.name || '---';

    // Populate Right Side (Items Table) from API
    const tbody = document.getElementById('history-view-items-tbody');
    tbody.innerHTML = '<tr><td colspan="10" class="p-6 text-center text-slate-400 text-xs">Loading items...</td></tr>';

    // Get discount values for footer
    const additionalDiscountPercent = parseFloat(data.additional_discount_percent || 0);
    const additionalDiscountAmount = parseFloat(data.additional_discount_amount || 0);

    // Update currency labels and totals in footer
    const currencyLabelEl = document.getElementById('h-view-gt-currency-label');
    if (currencyLabelEl) currencyLabelEl.innerText = currency;
    
    const totalForDisplay = parseFloat(data.displayTotal || data.totalAmount || 0);
    const gtPhpEl = document.getElementById('h-view-grand-total-php');
    if (gtPhpEl) gtPhpEl.innerText = symbol + ' ' + totalForDisplay.toLocaleString(undefined, {minimumFractionDigits: 2});
    
    // Display additional discount in footer
    const footerDiscountEl = document.getElementById('h-view-footer-additional-discount');
    if (footerDiscountEl) {
        if (additionalDiscountPercent > 0) {
            footerDiscountEl.innerText = `${additionalDiscountPercent.toFixed(2)}% (${symbol} ${additionalDiscountAmount.toLocaleString(undefined, {minimumFractionDigits: 2})})`;
        } else {
            footerDiscountEl.innerText = '0%';
        }
    }

    toggleModal('history-view-modal', true);
    ensurePurchaseOrderColumnSearch('history');
    resetPurchaseOrderColumnSearch('history');

    // Fetch items from API
    const poId = data.id;
    if (!poId) {
        tbody.innerHTML = '<tr><td colspan="10" class="p-6 text-center text-slate-400 text-xs">No items data</td></tr>';
        return;
    }

    const baseUrl = window.purchaseRoutes?.poItemsUrl || '';
    const url = baseUrl.replace(':poId', poId);
    fetch(url)
        .then(res => res.json())
        .then(result => {
            if (!result.success || !result.items || result.items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="10" class="p-6 text-center text-slate-400 text-xs">No items found</td></tr>';
                return;
            }
            
            // Calculate total (sum of subtotals)
            let subtotalSum = 0;
            
            tbody.innerHTML = result.items.map(item => {
                const subDisplay = parseFloat(item.subtotal_display || item.subtotal || 0);
                const discountPercent = parseFloat(item.discount_percent || 0);
                const discountAmount = parseFloat(item.discount_amount || 0);
                
                // Use actual_quantity instead of quantity for history display
                const actualQty = item.actual_quantity || item.quantity || 0;
                
                // Add to sum
                subtotalSum += subDisplay;
                
                return `<tr class="divide-x divide-slate-100">
                    <td class="p-3 font-bold text-maroon">${item.product_code || '---'}</td>
                    <td class="p-3 text-slate-600">${item.part_number || '---'}</td>
                    <td class="p-3 text-slate-700">${item.description || '---'}</td>
                    <td class="p-3 text-center font-bold text-slate-700">${actualQty}</td>
                    <td class="p-3 text-center text-slate-500 uppercase">${item.unit || ''}</td>
                    <td class="p-3 text-right font-mono">${symbol} ${parseFloat(item.unit_price || 0).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                    <td class="p-3 text-right font-mono">${symbol} ${discountAmount.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                    <td class="p-3 text-center text-slate-400 font-mono">${discountPercent.toFixed(2)}%</td>
                    <td class="p-3 text-right font-bold text-slate-800">${symbol} ${subDisplay.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                    <td class="p-3 text-right font-bold text-maroon">${symbol} ${subDisplay.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                </tr>`;
            }).join('');
            ensurePurchaseOrderColumnSearch('history');
            filterPurchaseOrderModalRows('history');
            
            // Update Total (sum of actual subtotals) in footer
            const subtotalSumEl = document.getElementById('h-view-subtotal-sum');
            if (subtotalSumEl) {
                subtotalSumEl.innerText = symbol + ' ' + subtotalSum.toLocaleString(undefined, {minimumFractionDigits: 2});
            }
        })
        .catch(() => {
            tbody.innerHTML = '<tr><td colspan="10" class="p-6 text-center text-red-400 text-xs">Failed to load items</td></tr>';
        });
}

/**
 * Apply history filters
 */
function applyHistoryFilters() {
    const start = document.getElementById('history-filter-date-start').value;
    const end = document.getElementById('history-filter-date-end').value;
    const name = document.getElementById('history-filter-name').value.toLowerCase();
    
    const tbody = document.getElementById('history-purchase-order-tbody');
    const rows = tbody.getElementsByTagName('tr');

    for (let i = 0; i < rows.length; i++) {
        const dateCell = rows[i].getElementsByTagName('td')[0].innerText;
        const nameCell = rows[i].getElementsByTagName('td')[4].innerText.toLowerCase();
        
        let show = true;
        if (start && dateCell < start) show = false;
        if (end && dateCell > end) show = false;
        if (name && !nameCell.includes(name)) show = false;

        rows[i].style.display = show ? "" : "none";
    }

    toggleModal('history-filter-modal', false);
}

/**
 * Reset history filters
 */
function resetHistoryFilters() {
    document.getElementById('history-filter-date-start').value = '';
    document.getElementById('history-filter-date-end').value = '';
    document.getElementById('history-filter-name').value = '';
    
    const tbody = document.getElementById('history-purchase-order-tbody');
    const rows = tbody.getElementsByTagName('tr');
    for (let i = 0; i < rows.length; i++) {
        rows[i].style.display = "";
    }
}

/**
 * Filter supplier table based on general search input
 */
function filterSupplierTable() {
    const input = document.getElementById('supplier-general-search');
    if (!input) return;
    const filter = input.value.toLowerCase();
    const tbody = document.querySelector('#supplier-search-modal tbody');
    if (!tbody) return;
    const rows = tbody.getElementsByTagName('tr');

    for (let i = 0; i < rows.length; i++) {
        const text = rows[i].textContent.toLowerCase();
        rows[i].style.display = text.includes(filter) ? "" : "none";
    }
}

/**
 * Filter supplier table by specific column
 * @param {number} colIndex 
 * @param {string} value 
 */
function filterSupplierTableByCol(colIndex, value) {
    const tbody = document.querySelector('#supplier-search-modal tbody');
    if (!tbody) return;
    const rows = tbody.getElementsByTagName('tr');
    const filter = value.toLowerCase();

    for (let i = 0; i < rows.length; i++) {
        const cell = rows[i].getElementsByTagName('td')[colIndex];
        if (cell) {
            const text = cell.textContent.toLowerCase();
            rows[i].style.display = text.includes(filter) ? "" : "none";
        }
    }
}

/**
 * Open the proceed modal and populate with initial data
 * @param {Object} data - Initial data for the modal
 */
function openProceedModal(data) {
    if (!data) return;

    // Store purchase note ID in state
    window.poState.currentPurchaseNoteId = data.poId;
    window.poState.poNumber = data.po_number || '';

    // Populate Step 1 fields
    const supplierCode = document.getElementById('proceed-supplier-code');
    const supplierName = document.getElementById('proceed-supplier-name');
    const controlNo = document.getElementById('proceed-control-no');
    const pnNo = document.getElementById('proceed-pn-no');
    const existingInvoicePanel = document.getElementById('existing-invoice-panel');
    const existingInvoiceDisplay = document.getElementById('existing-invoice-display');

    if (supplierCode) supplierCode.value = data.supplierCode || '';
    if (supplierName) supplierName.value = data.name || '';
    if (controlNo) controlNo.value = data.transNo || '';
    if (pnNo) pnNo.value = data.po_number || '';
    if (window.poState) {
        window.poState.noteStatus = data.status || '';
        window.poState.existingInvoiceNumbers = Array.isArray(data.existing_invoice_numbers) ? data.existing_invoice_numbers : [];
    }
    if (existingInvoicePanel && existingInvoiceDisplay) {
        const invoices = Array.isArray(data.existing_invoice_numbers) ? data.existing_invoice_numbers.filter(Boolean) : [];
        existingInvoiceDisplay.innerText = invoices.length ? invoices.join(', ') : '---';
        existingInvoicePanel.classList.toggle('hidden', invoices.length === 0);
    }

    // Initialize currency state from Step 1 select
    const currencySelect = document.getElementById('proceed-currency');
    if (currencySelect && window.poState) {
        window.poState.currency = currencySelect.value;
    }

    // Reset invoice inputs
    const container = document.getElementById('invoice-inputs-container');
    if (container) {
        container.innerHTML = '<div class="relative group"><input type="text" class="supplier-invoice-input w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm font-mono focus:ring-2 focus:ring-maroon/20 focus:border-maroon transition-all text-slate-800 bg-slate-50/50" placeholder="Invoice #1"></div>';
    }

    // Reset items table
    const tbody = document.getElementById('po-items-tbody');
    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="12" class="py-8 text-center text-slate-400 italic text-xs">Loading items...</td></tr>';
    }

    // Reset totals
    const grandTotal = document.getElementById('po-items-grand-total');
    const actualGrandTotal = document.getElementById('po-items-actual-grand-total');
    const totalDeductionPHP = document.getElementById('po-items-total-deduction-php');
    const totalDeductionF = document.getElementById('po-items-total-deduction-f');
    const grandTotalF = document.getElementById('po-items-grand-total-f');
    if (grandTotal) grandTotal.innerText = '₱ 0.00';
    if (actualGrandTotal) actualGrandTotal.innerText = '₱ 0.00';
    if (totalDeductionPHP) totalDeductionPHP.innerText = '₱ 0.00';
    if (totalDeductionF) totalDeductionF.innerHTML = '<span class="foreign-symbol">$</span> 0.00';
    if (grandTotalF) grandTotalF.innerHTML = '<span class="foreign-symbol">$</span> 0.00';
    const automatedTotal = document.getElementById('proceed-total-amount');
    if (automatedTotal) automatedTotal.value = '0.00';

    // Reset to step 1
    goToStep(1);

    // Open modal
    toggleModal('proceed-modal', true);

    // Fetch items for this purchase note
    fetchPurchaseNoteItems(data.poId);
}

/**
 * Fetch purchase note items from the server
 */
async function fetchPurchaseNoteItems(purchaseNoteId) {
    try {
        const baseUrl = window.purchaseRoutes?.itemsUrl || '/hatdog/public/admin/purchase/purchase-order/items';
        const url = baseUrl + '/' + purchaseNoteId;
        const res = await fetch(url);
        const result = await res.json();

        if (!result.success) throw new Error(result.message || 'Failed to fetch items');

        // Store items in state for later submission
        window.poState.noteData = result.note;
        window.poState.items = result.items;
        window.poState.noteStatus = result.note?.status || window.poState.noteStatus || '';
        window.poState.existingInvoiceNumbers = Array.isArray(result.note?.existing_invoice_numbers) ? result.note.existing_invoice_numbers : (window.poState.existingInvoiceNumbers || []);
        updateForceCloseNoteButtonVisibility();

        const existingInvoicePanel = document.getElementById('existing-invoice-panel');
        const existingInvoiceDisplay = document.getElementById('existing-invoice-display');
        if (existingInvoicePanel && existingInvoiceDisplay) {
            const invoices = Array.isArray(window.poState.existingInvoiceNumbers) ? window.poState.existingInvoiceNumbers.filter(Boolean) : [];
            existingInvoiceDisplay.innerText = invoices.length ? invoices.join(', ') : '---';
            existingInvoicePanel.classList.toggle('hidden', invoices.length === 0);
        }

        // Pre-fill Step 1 currency and conversion rate from purchase note
        const currencySelect = document.getElementById('proceed-currency');
        const convRateInput = document.getElementById('proceed-conv-rate');
        if (result.note?.currency && currencySelect) {
            currencySelect.value = result.note.currency;
            window.poState.currency = result.note.currency;
        }
        if (result.note?.conversion_rate && convRateInput) {
            convRateInput.value = result.note.conversion_rate;
        }

        renderPurchaseNoteItems(result.items);
    } catch (e) {
        console.error('Error fetching purchase note items:', e);
        const tbody = document.getElementById('po-items-tbody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="12" class="py-8 text-center text-red-400 italic text-xs">Failed to load items: ' + e.message + '</td></tr>';
        }
    }
}

/**
 * Render items in the Step 2 table
 */
function renderPurchaseNoteItems(items) {
    const tbody = document.getElementById('po-items-tbody');
    if (!tbody) return;

    if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="12" class="py-8 text-center text-slate-400 italic text-xs">No items found</td></tr>';
        return;
    }

    tbody.innerHTML = items.map(item => `
        <tr>
            <td class="p-3 px-4 text-center">
                <input type="checkbox" class="po-item-checkbox w-4 h-4 rounded border-slate-300 text-maroon focus:ring-maroon cursor-pointer" checked onchange="updateCalculations()">
            </td>
            <td class="p-3 px-4 font-bold text-maroon">${item.product_code}</td>
            <td class="p-3 px-4 text-slate-500 font-mono">${item.part_number || '---'}</td>
            <td class="p-3 px-4 text-slate-700">${item.description}</td>
            <td class="p-3 px-4 text-center">
                <input type="number" value="${item.quantity}" oninput="updateCalculations()" class="item-qty w-20 px-2 py-1.5 border border-slate-200 rounded text-center focus:ring-1 focus:ring-maroon/20">
            </td>
            <td class="p-3 px-4 text-center">
                <input type="number" min="0" value="${item.actual_quantity ?? item.quantity}" oninput="updateCalculations()" class="item-actual-qty w-20 px-2 py-1.5 border border-maroon/30 rounded text-center focus:ring-1 focus:ring-maroon bg-maroon/5 font-bold text-maroon">
            </td>
            <td class="p-3 px-4 text-center text-slate-500">${item.unit || ''}</td>
            <td class="p-3 px-4">
                <input type="number" value="${item.unit_price}" oninput="updateCalculations()" class="item-unit-cost w-32 px-2 py-1.5 border border-slate-200 rounded focus:ring-1 focus:ring-maroon/20">
            </td>
            <td class="p-3 px-4">
                <div class="flex items-center space-x-1">
                    <select onchange="updateCalculations()" class="item-currency-f px-1 py-1.5 border border-slate-200 rounded text-[10px] focus:ring-1 focus:ring-maroon outline-none bg-slate-50">
                        <option value="PHP" ${(item.currency || 'PHP') === 'PHP' ? 'selected' : ''}>PHP</option>
                        <option value="USD" ${item.currency === 'USD' ? 'selected' : ''}>USD</option>
                        <option value="TWD" ${item.currency === 'TWD' ? 'selected' : ''}>TWD</option>
                    </select>
                    <input type="number" value="${item.unit_price_converted || ''}" oninput="updateCalculations()" class="item-unit-cost-f w-32 px-2 py-1.5 border border-slate-200 rounded focus:ring-1 focus:ring-maroon/20 bg-slate-50 font-bold text-maroon" placeholder="0.00">
                </div>
            </td>
            <td class="p-3 px-4">
                <input type="number" value="${item.discount_percent || 0}" oninput="updateCalculations()" class="item-disc w-20 px-2 py-1.5 border border-slate-200 rounded text-center focus:ring-1 focus:ring-maroon/20" placeholder="0">
            </td>
            <td class="p-3 px-4 text-right font-bold text-slate-800"><span class="subtotal-symbol">₱</span> <span class="item-subtotal">${(item.quantity * item.unit_price).toLocaleString(undefined, {minimumFractionDigits: 2})}</span></td>
            <td class="p-3 px-4 text-right font-bold text-maroon">
                <div class="actual-subtotal-container">
                    <span class="actual-subtotal-symbol">₱</span> <span class="item-actual-subtotal">${(item.quantity * item.unit_price).toLocaleString(undefined, {minimumFractionDigits: 2})}</span>
                </div>
            </td>
            <td class="p-3 px-4 text-right font-bold text-maroon"><span class="foreign-symbol">$</span> <span class="item-subtotal-f">0.00</span></td>
        </tr>
    `).join('');

    // Remove the hidden class from actual-subtotal-container
    document.querySelectorAll('.actual-subtotal-container').forEach(el => el.classList.remove('hidden'));
    document.querySelectorAll('.no-actual-change').forEach(el => el.remove());

    // Recalculate totals
    updateCalculations();
    if (window.lucide) window.lucide.createIcons();
}

/**
 * Toggle visibility of a modal
 * @param {string} modalId - The ID of the modal element
 * @param {boolean} show - Whether to show or hide the modal
 */
function toggleModal(modalId, show) {
    const modal = document.getElementById(modalId);
    if (!modal) return;

    if (show) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    } else {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
    }
}

/**
 * Navigate between steps in the Proceed modal
 * @param {number} step - The step number to navigate to
 */
function goToStep(step) {
    // Hide all steps
    document.querySelectorAll('.po-step-content').forEach(el => el.classList.add('hidden'));
    
    // Show target step
    const targetStep = document.getElementById(`po-step-${step}`);
    if (targetStep) {
        targetStep.classList.remove('hidden');
        if (window.poState) window.poState.currentStep = step;
        
        // Update step indicators
        updateStepIndicators(step);

        // If step 3, populate review data and print layout
        if (step === 3) {
            populateReviewData();
            populatePrintLayout();
            updateForceCloseNoteButtonVisibility();
        }
    }
}

function isForceCloseAllowedStatus(status) {
    return String(status || '').trim().toLowerCase() === 'partial';
}

function updateForceCloseNoteButtonVisibility() {
    const button = document.getElementById('force-close-note-btn');
    if (!button) return;

    const enabled = Boolean(window.purchaseOrderForceCloseEnabled);
    const allowed = isForceCloseAllowedStatus(window.poState?.noteStatus || window.poState?.noteData?.status);
    button.classList.toggle('hidden', !(enabled && allowed));
    button.disabled = !(enabled && allowed);
}

/**
 * Populate the print layout with current PO data
 */
function populatePrintLayout() {
    console.log('populatePrintLayout called', window.poState);
    
    if (!window.poState) {
        console.warn('No poState available');
        return;
    }
    
    const state = window.poState;
    
    // Get currency info
    const currency = state.currency || 'PHP';
    const symbols = { 'PHP': '₱', 'USD': '$', 'TWD': 'NT$' };
    const currencySymbol = symbols[currency] || '₱';
    
    // Populate header info
    const invoiceNo = document.getElementById('cr-invoice-no');
    const receivingNo = document.getElementById('cr-receiving-no');
    const supplierName = document.getElementById('cr-supplier-name');
    const dateEl = document.getElementById('cr-date');
    
    // Get invoice numbers from various sources
    let invoiceNumbers = [];
    const invoiceInputs = document.querySelectorAll('.supplier-invoice-input');
    invoiceInputs.forEach(input => {
        if (input.value.trim()) {
            invoiceNumbers.push(input.value.trim());
        }
    });
    
    // Fallback to state if no inputs found
    if (invoiceNumbers.length === 0 && state.existingInvoiceNumbers) {
        invoiceNumbers = state.existingInvoiceNumbers;
    }
    
    if (invoiceNo) {
        invoiceNo.textContent = invoiceNumbers.length > 0 ? invoiceNumbers.join(', ') : '';
        console.log('Invoice No:', invoiceNo.textContent);
    }
    
    // Get control number from input
    const controlNoInput = document.getElementById('proceed-control-no');
    if (receivingNo) {
        receivingNo.textContent = controlNoInput ? controlNoInput.value : (state.controlNo || '');
        console.log('Receiving No:', receivingNo.textContent);
    }
    
    // Get supplier name from input
    const supplierNameInput = document.getElementById('proceed-supplier-name');
    if (supplierName) {
        supplierName.textContent = supplierNameInput ? supplierNameInput.value : (state.supplierName || '');
        console.log('Supplier:', supplierName.textContent);
    }
    
    if (dateEl) {
        const today = new Date();
        dateEl.textContent = today.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        console.log('Date:', dateEl.textContent);
    }
    
    // Populate items table
    const tbody = document.getElementById('cr-items-tbody');
    if (tbody) {
        const items = state.items || [];
        console.log('Items:', items);
        
        if (items.length > 0) {
            let html = '';
            
            // Get the actual rows from Step 2 to read current discount values and checkboxes
            const step2Rows = document.querySelectorAll('#po-items-tbody tr');
            
            items.forEach((item, index) => {
                // Check if this item is checked - only show checked items in print
                const row = step2Rows[index];
                if (!row) return;
                
                const checkbox = row.querySelector('.po-item-checkbox');
                if (!checkbox || !checkbox.checked) {
                    return; // Skip unchecked items
                }
                
                // Use Actual QTY for the print layout
                const actualQtyInput = row.querySelector('.item-actual-qty');
                const qty = actualQtyInput ? parseFloat(actualQtyInput.value) || 0 : (item.actualQty || item.qty || item.quantity || 0);
                
                const unitPrice = parseFloat(item.unitCost || item.unit_price || 0);
                
                // Read discount from the actual input field in Step 2
                let discount = 0;
                const discInput = row.querySelector('.item-disc');
                discount = parseFloat(discInput?.value || 0);
                
                const subtotal = qty * unitPrice * (1 - discount / 100);
                
                html += `
                    <tr>
                        <td class="text-center">${qty}</td>
                        <td class="text-center">${item.unit || ''}</td>
                        <td>${item.part_number || item.partNo || '---'}</td>
                        <td>${item.description || ''}</td>
                        <td class="text-right">${currencySymbol} ${unitPrice.toFixed(2)}</td>
                        <td class="text-center">${discount.toFixed(2)}%</td>
                        <td class="text-right">${currencySymbol} ${subtotal.toFixed(2)}</td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
        }
    }
    
    // Populate totals
    const subtotalEl = document.getElementById('cr-subtotal');
    const additionalDiscountEl = document.getElementById('cr-additional-discount');
    const netTotal = document.getElementById('cr-net-total');
    
    const items = state.items || [];
    if (items.length > 0) {
        let totalBeforeDiscount = 0;
        let totalDiscount = 0;
        
        // Get the actual rows from Step 2 to read current discount values and checkboxes
        const step2Rows = document.querySelectorAll('#po-items-tbody tr');
        
        items.forEach((item, index) => {
            const row = step2Rows[index];
            if (!row) return;
            
            // Only include checked items in totals
            const checkbox = row.querySelector('.po-item-checkbox');
            if (!checkbox || !checkbox.checked) return;
            
            // Read qty from DOM input (same source as row rendering) for consistency
            const actualQtyInput = row.querySelector('.item-actual-qty');
            const qty = actualQtyInput ? parseFloat(actualQtyInput.value) || 0 : (item.actualQty || item.qty || item.quantity || 0);
            
            const unitPrice = parseFloat(item.unitCost || item.unit_price || 0);
            
            // Read discount from the actual input field in Step 2
            let discountPercent = 0;
            if (step2Rows[index]) {
                const discInput = step2Rows[index].querySelector('.item-disc');
                discountPercent = parseFloat(discInput?.value || 0);
            }
            
            const itemTotal = qty * unitPrice;
            const itemDiscount = itemTotal * (discountPercent / 100);
            
            totalBeforeDiscount += itemTotal;
            totalDiscount += itemDiscount;
        });
        
        const totalAfterItemDiscount = totalBeforeDiscount - totalDiscount;
        
        // Get additional discount
        const additionalDiscountPercent = state.additionalDiscountPercent || 0;
        const additionalDiscountAmount = totalAfterItemDiscount * (additionalDiscountPercent / 100);
        const finalTotal = totalAfterItemDiscount - additionalDiscountAmount;
        
        if (subtotalEl) subtotalEl.textContent = currencySymbol + ' ' + totalAfterItemDiscount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        if (additionalDiscountEl) additionalDiscountEl.textContent = additionalDiscountPercent.toFixed(2) + '%';
        if (netTotal) netTotal.textContent = currencySymbol + ' ' + finalTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        
        console.log('Totals - Currency:', currency, 'Before Discount:', totalBeforeDiscount, 'After Item Discount:', totalAfterItemDiscount, 'Additional Discount:', additionalDiscountAmount, 'Final:', finalTotal);
    }
}

/**
 * Enlarge print layout in modal with iframe
 */
window.enlargePrintLayout = function() {
    const modal = document.getElementById('enlarge-print-modal');
    const iframe = document.getElementById('print-layout-iframe');
    
    if (!modal || !iframe) return;
    
    // Get the print layout HTML
    const printContent = document.getElementById('print-layout-content');
    if (!printContent) return;
    
    // Create a complete HTML document for the iframe
    const htmlContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body {
                    margin: 0;
                    padding: 20px;
                    font-family: Arial, sans-serif;
                }
                @media print {
                    body {
                        padding: 0;
                    }
                }
            </style>
        </head>
        <body>
            ${printContent.innerHTML}
        </body>
        </html>
    `;
    
    // Write content to iframe
    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
    iframeDoc.open();
    iframeDoc.write(htmlContent);
    iframeDoc.close();
    
    // Show modal
    toggleModal('enlarge-print-modal', true);
};

/**
 * Print the charge receiving document
 */
window.printChargeReceiving = function() {
    const iframe = document.getElementById('print-layout-iframe');
    if (iframe && iframe.contentWindow) {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
    }
};

/**
 * Update the visual state of step indicators
 * @param {number} currentStep 
 */
function updateStepIndicators(currentStep) {
    for (let i = 1; i <= 3; i++) {
        const indicator = document.getElementById(`step-indicator-${i}`);
        if (!indicator) continue;

        indicator.classList.remove('active', 'completed', 'pending');
        
        if (i < currentStep) {
            indicator.classList.add('completed');
            indicator.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i>';
        } else if (i === currentStep) {
            indicator.classList.add('active');
            indicator.innerText = i;
        } else {
            indicator.classList.add('pending');
            indicator.innerText = i;
        }
    }
    if (window.lucide) window.lucide.createIcons();
}

/**
 * Handle supplier selection
 */
function selectSupplier(code, name) {
    const codeInput = document.getElementById('proceed-supplier-code');
    const nameInput = document.getElementById('proceed-supplier-name');
    if (codeInput) codeInput.value = code;
    if (nameInput) nameInput.value = name;
    toggleModal('supplier-search-modal', false);
}

/**
 * Populate Review Step with current data
 */
function addInvoiceInput() {
    const container = document.getElementById('invoice-inputs-container');
    const count = container.querySelectorAll('.supplier-invoice-input').length + 1;
    const div = document.createElement('div');
    div.className = 'relative group invoice-row';
    div.innerHTML = `
        <input type="text" class="supplier-invoice-input w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm font-mono focus:ring-2 focus:ring-maroon/20 focus:border-maroon transition-all text-slate-800 bg-slate-50/50" placeholder="Invoice #${count}">
        <button onclick="this.closest('.invoice-row').remove()" class="absolute -top-2 -right-2 p-1 bg-red-50 text-red-400 hover:bg-red-100 hover:text-red-600 rounded-full transition-all shadow-sm">
            <i data-lucide="x" class="w-3 h-3"></i>
        </button>
    `;
    container.appendChild(div);
    if (window.lucide) window.lucide.createIcons();
}

function populateReviewData() {
    // Basic Info
    const fields = {
        'review-supplier-code': 'proceed-supplier-code',
        'review-supplier-name': 'proceed-supplier-name',
        'review-control-no': 'proceed-control-no',
        'review-pn-no': 'proceed-pn-no',
        'review-currency': 'proceed-currency',
        'review-conv-rate': 'proceed-conv-rate',
        'review-total-amount': 'proceed-total-amount'
    };

    for (const [reviewId, inputId] of Object.entries(fields)) {
        const reviewEl = document.getElementById(reviewId);
        const inputEl = document.getElementById(inputId);
        if (reviewEl && inputEl) {
            reviewEl.innerText = inputEl.value || (reviewId.includes('total') ? '0.00' : '---');
        }
    }

    // Supplier invoice numbers
    const reviewInvoice = document.getElementById('review-supplier-invoice');
    const invoiceInputs = document.querySelectorAll('.supplier-invoice-input');
    const invoiceValues = [];
    invoiceInputs.forEach(inp => {
        const val = inp.value.trim();
        if (val) invoiceValues.push(val);
    });
    if (reviewInvoice) {
        reviewInvoice.innerText = invoiceValues.length ? invoiceValues.join(', ') : '---';
    }

    // Special handling for dual grand totals in review
    const phpTotalReview = document.getElementById('review-total-amount-php');
    const actualPhpTotalReview = document.getElementById('review-actual-total-amount-php');
    const fTotalReview = document.getElementById('review-total-amount-f');
    const grandTotalPHP = document.getElementById('po-items-grand-total');
    const actualGrandTotalPHP = document.getElementById('po-items-actual-grand-total');
    const grandTotalF = document.getElementById('po-items-grand-total-f');

    if (phpTotalReview && grandTotalPHP) phpTotalReview.innerText = grandTotalPHP.innerText;
    if (actualPhpTotalReview && actualGrandTotalPHP) actualPhpTotalReview.innerText = actualGrandTotalPHP.innerText;
    if (fTotalReview && grandTotalF) fTotalReview.innerHTML = grandTotalF.innerHTML;

    // Populate total deductions in review summary
    const deductionPHPReview = document.getElementById('review-total-deduction-php');
    const deductionFReview = document.getElementById('review-total-deduction-f');
    const sourceDeductionPHP = document.getElementById('po-items-total-deduction-php');
    const sourceDeductionF = document.getElementById('po-items-total-deduction-f');

    if (deductionPHPReview && sourceDeductionPHP) deductionPHPReview.innerText = sourceDeductionPHP.innerText;
    if (deductionFReview && sourceDeductionF) deductionFReview.innerHTML = sourceDeductionF.innerHTML;

    // Items Table in Review
    const reviewTbody = document.getElementById('review-items-tbody');
    const sourceTbody = document.getElementById('po-items-tbody');
    
    if (reviewTbody && sourceTbody) {
        reviewTbody.innerHTML = '';
        const rows = sourceTbody.querySelectorAll('tr');
        const currency = window.poState?.currency || 'PHP';
        const symbols = { 'PHP': '₱', 'USD': '$', 'TWD': 'NT$' };
        const reviewSymbol = symbols[currency] || '₱';
        rows.forEach(row => {
            // With checkbox, indices are shifted by 1
            const checkbox = row.querySelector('.po-item-checkbox');
            
            // Skip unchecked items in review
            if (checkbox && !checkbox.checked) {
                return;
            }
            
            const code = row.cells[1]?.innerText || ''; // Item Code is now at index 1
            const partNo = row.cells[2]?.innerText || '---'; // Part No is now at index 2
            const desc = row.cells[3]?.innerText || ''; // Description is now at index 3
            const qty = row.querySelector('.item-actual-qty')?.value || row.querySelector('.item-qty')?.value || '0';
            const subtotalPHP = row.querySelector('.item-actual-subtotal')?.innerText || row.querySelector('.item-subtotal')?.innerText || '0.00';
            const subtotalF = row.querySelector('.item-subtotal-f')?.innerText || '0.00';
            const symbol = row.querySelector('.foreign-symbol')?.innerText || '$';

            const newRow = document.createElement('tr');
            newRow.className = 'divide-x divide-slate-100';
            newRow.innerHTML = `
                <td class="p-4 text-slate-700">${code}</td>
                <td class="p-4 text-slate-600">${desc}</td>
                <td class="p-4 text-center font-bold text-slate-700">${qty}</td>
                <td class="p-4 text-right font-bold text-slate-800">${reviewSymbol} ${subtotalPHP}</td>
                <td class="p-4 text-right font-bold text-maroon">${symbol} ${subtotalF}</td>
            `;
            reviewTbody.appendChild(newRow);
        });
    }
}

/**
 * Toggle all PO item checkboxes
 */
function toggleAllPOItems(checked) {
    const checkboxes = document.querySelectorAll('.po-item-checkbox');
    checkboxes.forEach(cb => cb.checked = checked);
    updateCalculations();
}

/**
 * Trigger confirmation modal
 */
function confirmProceed() {
    if (isFinalizingPO) return;
    finalizePO();
}

function setConfirmPOButtonLoading(isLoading) {
    const confirmButton = document.getElementById('confirm-proceed-btn');
    if (!confirmButton) return;

    confirmButton.disabled = isLoading;
    confirmButton.classList.toggle('opacity-60', isLoading);
    confirmButton.classList.toggle('cursor-not-allowed', isLoading);
    confirmButton.innerHTML = isLoading ? '<span>Processing...</span>' : '<i data-lucide="check-circle" class="w-4 h-4 text-gold"></i><span>CONFIRM & FINALIZE</span>';
    if (window.lucide) window.lucide.createIcons();
}

function wait(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

function getCSRFToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

function getPOCostResyncUrl(poId) {
    const route = window.purchaseRoutes?.resyncCostsUrl || '/hatdog/public/admin/purchase/purchase-order/:poId/resync-product-costs';
    return route.replace(':poId', poId);
}

function setPOSyncIcon(mode) {
    const spinner = document.getElementById('po-sync-spinner');
    const successIcon = document.getElementById('po-sync-success-icon');
    const errorIcon = document.getElementById('po-sync-error-icon');

    spinner?.classList.toggle('hidden', mode !== 'loading');
    spinner?.classList.toggle('flex', mode === 'loading');
    successIcon?.classList.toggle('hidden', mode !== 'success');
    successIcon?.classList.toggle('flex', mode === 'success');
    errorIcon?.classList.toggle('hidden', mode !== 'error');
    errorIcon?.classList.toggle('flex', mode === 'error');
}

function setPOSyncStep(step, state) {
    const row = document.querySelector(`[data-po-sync-step="${step}"]`);
    if (!row) return;

    const label = row.querySelector('.po-sync-step-status');
    const states = {
        pending: ['Pending', 'border-slate-100', 'bg-slate-50', 'text-slate-400'],
        loading: ['Syncing', 'border-amber-200', 'bg-amber-50', 'text-amber-600'],
        success: ['Done', 'border-green-200', 'bg-green-50', 'text-green-600'],
        error: ['Needs Re-Sync', 'border-red-200', 'bg-red-50', 'text-red-600']
    };
    const [text, borderClass, bgClass, textClass] = states[state] || states.pending;

    row.classList.remove('border-slate-100', 'bg-slate-50', 'border-amber-200', 'bg-amber-50', 'border-green-200', 'bg-green-50', 'border-red-200', 'bg-red-50');
    row.classList.add(borderClass, bgClass);

    if (label) {
        label.textContent = text;
        label.classList.remove('text-slate-400', 'text-amber-600', 'text-green-600', 'text-red-600');
        label.classList.add(textClass);
    }
}

function setAllPOSyncSteps(state) {
    document.querySelectorAll('[data-po-sync-step]').forEach(row => {
        setPOSyncStep(row.dataset.poSyncStep, state);
    });
}

function showPOSyncModal(mode, message, canRetry = false) {
    const title = document.getElementById('po-sync-title');
    const messageEl = document.getElementById('po-sync-message');
    const retryBtn = document.getElementById('po-sync-retry-btn');
    const closeBtn = document.getElementById('po-sync-close-btn');

    if (title) {
        title.textContent = mode === 'error'
            ? 'Re-Sync Required'
            : mode === 'success'
                ? 'Purchase Order Synced'
                : 'Analyzing Purchase Order';
    }

    if (messageEl) {
        messageEl.textContent = message;
    }

    retryBtn?.classList.toggle('hidden', !canRetry);
    closeBtn?.classList.toggle('hidden', mode !== 'error');
    setPOSyncIcon(mode);
    toggleModal('po-sync-modal', true);

    if (window.lucide) window.lucide.createIcons();
}

function showPOSyncLoading(message = 'Please wait while the transaction is being recorded.') {
    setAllPOSyncSteps('loading');
    showPOSyncModal('loading', message, false);
}

function showPOSyncSuccess(message) {
    setAllPOSyncSteps('success');
    showPOSyncModal('success', message, false);
}

function showPOSyncFailure(message, canRetry, failedStep = 'product_costs') {
    if (failedStep === 'product_costs') {
        ['purchase_orders', 'purchase_order_items', 'product_ledgers', 'supplier_ledgers'].forEach(step => setPOSyncStep(step, 'success'));
        setPOSyncStep('product_costs', 'error');
    } else {
        setAllPOSyncSteps('pending');
        setPOSyncStep(failedStep, 'error');
    }

    showPOSyncModal('error', message, canRetry);
}

async function postPurchaseOrderJson(url, payload = {}) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCSRFToken()
        },
        body: JSON.stringify(payload)
    });

    const rawText = await response.text();
    let result = {};
    try {
        result = rawText ? JSON.parse(rawText) : {};
    } catch (error) {
        const looksLikeHtml = /^\s*</.test(rawText || '');
        const statusText = `${response.status}${response.statusText ? ` ${response.statusText}` : ''}`.trim();
        throw new Error(looksLikeHtml
            ? `Server returned an HTML error page (${statusText}). Check Laravel storage/logs/laravel.log for the exact error.`
            : (rawText || `Server returned an unreadable response (${statusText}).`));
    }

    if (!response.ok || result.success === false) {
        throw new Error(result.message || `Server rejected the request (${response.status}).`);
    }

    return result;
}

async function runTimedPOSync(requestCallback) {
    const startedAt = Date.now();
    const result = await requestCallback();
    const remainingMs = PO_SYNC_MIN_VISIBLE_MS - (Date.now() - startedAt);
    if (remainingMs > 0) {
        await wait(remainingMs);
    }
    return result;
}

async function finishSuccessfulPOProcess(message) {
    const successMessage = message || 'Purchase Order processed successfully.';

    showPOSyncSuccess(successMessage);
    await wait(700);

    // Close every modal involved in finalization before displaying the
    // confirmation modal. This prevents a higher z-index overlay from hiding it.
    toggleModal('po-sync-modal', false);
    toggleModal('proceed-modal', false);

    const successModal = document.getElementById('success-modal');
    const successMessageElement = document.getElementById('success-msg');

    if (!successModal || !successMessageElement) {
        // Safe fallback: the record is already saved, so notify the user and
        // reload instead of leaving the page in an uncertain state.
        alert(successMessage + '\n\nThe page will now reload.');
        window.location.reload();
        return;
    }

    successMessageElement.textContent = successMessage;
    toggleModal('success-modal', true);

    if (window.lucide) {
        window.lucide.createIcons();
    }

    document.getElementById('success-reload-btn')?.focus();
}

function completeSuccessfulPOProcess() {
    clearPurchaseOrderOneTimeQueryParams();
    const button = document.getElementById('success-reload-btn');
    if (button) {
        button.disabled = true;
        button.textContent = 'Reloading...';
        button.classList.add('opacity-60', 'cursor-not-allowed');
    }

    window.location.reload();
}

async function retryPurchaseOrderSync() {
    if (isFinalizingPO) return;
    isFinalizingPO = true;
    setConfirmPOButtonLoading(true);

    try {
        let result;
        if (lastPOResyncId) {
            showPOSyncLoading('Re-syncing product costs from the saved Purchase Order.');
            result = await runTimedPOSync(() => postPurchaseOrderJson(getPOCostResyncUrl(lastPOResyncId), {}));
        } else if (lastPOFinalizePayload) {
            showPOSyncLoading('Retrying the Purchase Order transaction.');
            const url = window.purchaseRoutes?.process || '/hatdog/public/admin/purchase/purchase-order/process';
            result = await runTimedPOSync(() => postPurchaseOrderJson(url, lastPOFinalizePayload));
        } else {
            throw new Error('No Purchase Order sync data is available to retry.');
        }

        if (!result.success) {
            lastPOResyncId = result.po_id || lastPOResyncId;
            showPOSyncFailure(result.message || 'Re-sync failed. Please try again.', true, lastPOResyncId ? 'product_costs' : 'purchase_orders');
            return;
        }

        lastPOResyncId = null;
        await finishSuccessfulPOProcess(result.message || 'Product costs re-synced successfully.');
    } catch (error) {
        console.error('Error re-syncing PO:', error);
        showPOSyncFailure('Error: ' + error.message, true, lastPOResyncId ? 'product_costs' : 'purchase_orders');
    } finally {
        isFinalizingPO = false;
        setConfirmPOButtonLoading(false);
    }
}

let forceClosePurchaseNoteState = null;

function getForceCloseUrl(template, purchaseNoteId) {
    if (!template) return '';
    return String(template).replace(':purchaseNoteId', encodeURIComponent(purchaseNoteId));
}

function openForceClosePurchaseNoteModal() {
    if (!window.purchaseOrderForceCloseEnabled || window._forceClosingPurchaseNote) return;

    const purchaseNoteId = window.poState?.currentPurchaseNoteId;
    const noteStatus = window.poState?.noteStatus || window.poState?.noteData?.status;
    const purchaseNoteNumber = window.poState?.poNumber
        || window.poState?.noteData?.purchase_note_number
        || document.getElementById('proceed-control-no')?.value
        || '---';

    if (!purchaseNoteId) {
        alert('Unable to identify the selected Purchase Note.');
        return;
    }

    if (!isForceCloseAllowedStatus(noteStatus)) {
        alert('Only Partial Purchase Notes can be force closed.');
        return;
    }

    forceClosePurchaseNoteState = {
        purchaseNoteId,
        purchaseNoteNumber,
    };

    const noteNumber = document.getElementById('fc-note-number');
    const acknowledgement = document.getElementById('fc-ack-checkbox');
    if (noteNumber) noteNumber.textContent = purchaseNoteNumber;
    if (acknowledgement) acknowledgement.checked = false;
    toggleForceCloseConfirmButton();

    toggleModal('force-close-confirm-modal', true);
    if (window.lucide) window.lucide.createIcons();
}

function closeForceClosePurchaseNoteModal() {
    if (window._forceClosingPurchaseNote) return;
    toggleModal('force-close-confirm-modal', false);
    forceClosePurchaseNoteState = null;
}

function toggleForceCloseConfirmButton() {
    const button = document.getElementById('fc-confirm-btn');
    const checked = Boolean(document.getElementById('fc-ack-checkbox')?.checked);
    if (button) button.disabled = !checked || Boolean(window._forceClosingPurchaseNote);
}

async function showForceCloseSuccess(purchaseNoteNumber, deletedItemCount, deletedQuantity) {
    const deletedSummary = `${deletedItemCount || 0} remaining item row(s), quantity ${Number(deletedQuantity || 0).toLocaleString()}`;

    if (window.Swal?.fire) {
        await Swal.fire({
            icon: 'success',
            title: 'Forced Close Successful',
            html: `
                Purchase Note <strong>${purchaseNoteNumber}</strong> is now Closed.<br><br>
                Removed: <strong>${deletedSummary}</strong>.<br><br>
                Already invoiced Purchase Orders and their items were preserved.
            `,
            confirmButtonText: 'OK',
            allowOutsideClick: false,
            allowEscapeKey: false
        });
    } else {
        alert(`Purchase Note ${purchaseNoteNumber} is now Closed.\n\nRemoved: ${deletedSummary}.\n\nAlready invoiced Purchase Orders and their items were preserved.`);
    }

    window.location.reload();
}

async function submitForceClosePurchaseNote() {
    if (window._forceClosingPurchaseNote || !forceClosePurchaseNoteState) return;
    if (!document.getElementById('fc-ack-checkbox')?.checked) return;

    const submitUrl = getForceCloseUrl(
        window.purchaseRoutes?.forceCloseUrl,
        forceClosePurchaseNoteState.purchaseNoteId
    );

    if (!submitUrl) {
        alert('Force-close route is not configured.');
        return;
    }

    window._forceClosingPurchaseNote = true;
    const button = document.getElementById('fc-confirm-btn');
    const originalText = button?.textContent || 'Yes, Force Close';

    try {
        if (button) {
            button.disabled = true;
            button.textContent = 'CLOSING...';
        }

        const response = await postPurchaseOrderJson(submitUrl, {});
        if (!response.success) {
            throw new Error(response.message || 'Unable to force close Purchase Note.');
        }

        const purchaseNoteNumber = response.purchase_note_number
            || forceClosePurchaseNoteState.purchaseNoteNumber
            || '---';
        const deletedItemCount = response.deleted_remaining_item_count || 0;
        const deletedQuantity = response.deleted_remaining_quantity || 0;

        toggleModal('force-close-confirm-modal', false);
        toggleModal('po-sync-modal', false);
        forceClosePurchaseNoteState = null;

        await showForceCloseSuccess(purchaseNoteNumber, deletedItemCount, deletedQuantity);
    } catch (error) {
        alert(error.message || 'Unable to force close Purchase Note.');
    } finally {
        window._forceClosingPurchaseNote = false;
        if (button) {
            button.textContent = originalText;
            button.disabled = !document.getElementById('fc-ack-checkbox')?.checked;
        }
    }
}

/**
 * Finalize the Purchase Order - POST data to backend
 */
async function finalizePO() {
    if (isFinalizingPO) return;
    isFinalizingPO = true;
    setConfirmPOButtonLoading(true);

    try {
        // Gather Step 1 data
        const supplierCode = document.getElementById('proceed-supplier-code')?.value || '';
        const supplierName = document.getElementById('proceed-supplier-name')?.value || '';
        const transNo = document.getElementById('proceed-control-no')?.value || '';
        const poNumber = document.getElementById('proceed-pn-no')?.value || '';
        const currency = document.getElementById('proceed-currency')?.value || 'PHP';

        // Gather supplier invoice numbers
        const invoiceInputs = document.querySelectorAll('.supplier-invoice-input');
        const supplierInvoiceNumbers = [];
        invoiceInputs.forEach(inp => {
            const val = inp.value.trim();
            if (val) supplierInvoiceNumbers.push(val);
        });

        // Gather items from table - ONLY CHECKED ITEMS
        const rows = document.querySelectorAll('#po-items-tbody tr');
        const items = [];
        rows.forEach(row => {
            const checkbox = row.querySelector('.po-item-checkbox');
            
            // Skip unchecked items
            if (!checkbox || !checkbox.checked) {
                return;
            }
            
            const productCode = (row.cells[1]?.innerText || '').trim();
            const description = (row.cells[3]?.innerText || '').trim();
            const qtyStr = String(row.querySelector('.item-qty')?.value || '0').replace(/,/g, '');
            const actualQtyStr = String(row.querySelector('.item-actual-qty')?.value || '0').replace(/,/g, '');
            const unitPriceStr = String(row.querySelector('.item-unit-cost')?.value || '0').replace(/,/g, '');
            const discStr = String(row.querySelector('.item-disc')?.value || '0').replace(/,/g, '');

            const qty = parseFloat(qtyStr) || 0;
            const actualQty = parseFloat(actualQtyStr) || 0;
            const unitPrice = parseFloat(unitPriceStr) || 0;
            const disc = parseFloat(discStr) || 0;

            const discountAmount = (actualQty * unitPrice) * (disc / 100);
            const subtotal = (qty * unitPrice) - ((qty * unitPrice) * (disc / 100));
            const actualSubtotal = (actualQty * unitPrice) - ((actualQty * unitPrice) * (disc / 100));
            const matchedPOItem = window.poState?.items?.find(i => i.product_code === productCode);
            const productId = matchedPOItem?.product_id || null;

            if (productCode) {
                if (qty < 0 || actualQty < 0) {
                    throw new Error(`Quantity cannot be negative for ${productCode}.`);
                }
                if (actualQty > qty) {
                    throw new Error(`Received quantity cannot exceed remaining quantity for ${productCode}. (Received: ${actualQty}, Remaining: ${qty})`);
                }

                items.push({
                    product_id: productId,
                    product_code: productCode,
                    description: description,
                    quantity: qty,
                    actual_quantity: actualQty,
                    unit_price: unitPrice,
                    unit: matchedPOItem?.unit || '',
                    discount_percent: disc,
                    discount_amount: discountAmount,
                    subtotal: subtotal,
                    actual_subtotal: actualSubtotal
                });
            }
        });

        // Check if at least one item is selected
        if (items.length === 0) {
            alert('Please select at least one item to process.');
            return;
        }

        // Always send PHP amounts to the backend regardless of display currency
        const totalAmount = window.poState?.grandTotal || parseFloat(document.getElementById('po-items-grand-total')?.innerText.replace(/[₱$,NT]/g, '').replace(/,/g, '')) || 0;
        const actualTotalAmount = window.poState?.actualGrandTotal || parseFloat(document.getElementById('po-items-actual-grand-total')?.innerText.replace(/[₱$,NT]/g, '').replace(/,/g, '')) || 0;

        // Get additional discount - calculate amount directly from actual total
        const additionalDiscountPercent = parseFloat(document.getElementById('additional-discount-percent')?.value || 0);
        
        // Calculate total before additional discount (sum of actual subtotals)
        let totalBeforeAdditionalDiscount = 0;
        items.forEach(item => {
            totalBeforeAdditionalDiscount += item.actual_subtotal;
        });
        
        const additionalDiscountAmount = totalBeforeAdditionalDiscount * (additionalDiscountPercent / 100);

        const convRate = window.poState?.conversionRate || 1;
        const payload = {
            po_number: poNumber,
            supplier_invoice_number: supplierInvoiceNumbers.join(', '),
            trans_no: transNo,
            additional_discount_percent: additionalDiscountPercent,
            additional_discount_amount: additionalDiscountAmount,
            supplier_code: supplierCode,
            date: new Date().toISOString().split('T')[0],
            total_amount: totalAmount,
            actual_total_amount: actualTotalAmount,
            currency: currency,
            conversion_rate: convRate,
            remarks: '',
            items: items
        };

        lastPOFinalizePayload = payload;
        lastPOResyncId = null;
        showPOSyncLoading('Recording the Purchase Order, ledger entries, and product costs.');

        const url = window.purchaseRoutes?.process || '/hatdog/public/admin/purchase/purchase-order/process';
        const result = await runTimedPOSync(() => postPurchaseOrderJson(url, payload));

        if (!result.success) {
            lastPOResyncId = result.po_id || null;
            showPOSyncFailure(result.message || 'Failed to process purchase order', Boolean(result.can_resync || lastPOFinalizePayload), lastPOResyncId ? 'product_costs' : 'purchase_orders');
            return;
        }

        await finishSuccessfulPOProcess(result.message || 'Purchase Order processed successfully');
    } catch (e) {
        console.error('Error finalizing PO:', e);
        showPOSyncFailure('Error: ' + e.message, true, lastPOResyncId ? 'product_costs' : 'purchase_orders');
    } finally {
        isFinalizingPO = false;
        setConfirmPOButtonLoading(false);
    }
}

/**
 * Currency Conversion Logic (Updated for manual input)
 */
window.handleCurrencyChange = function(select) {
    const currency = select.value;
    if (window.poState) window.poState.currency = currency;

    // Show/hide conversion rate input
    const rateWrapper = document.getElementById('conv-rate-wrapper');
    if (rateWrapper) {
        rateWrapper.classList.toggle('hidden', currency === 'PHP');
    }

    // Reset rate field and poState rate when switching back to PHP
    if (currency === 'PHP') {
        if (window.poState) window.poState.conversionRate = 1;
        const rateInput = document.getElementById('proceed-conv-rate');
        if (rateInput) rateInput.value = '';
    }

    // Update currency labels
    const labels = ['gt-currency-label', 'agt-currency-label', 'review-gt-currency-label', 'review-agt-currency-label', 'review-currency', 'h-view-gt-currency-label', 'h-view-agt-currency-label'];
    labels.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.innerText = currency;
    });

    // Update all foreign symbols in the modal
    const symbols = { 'PHP': '₱', 'USD': '$', 'TWD': 'NT$' };
    const selectedSymbol = symbols[currency] || '$';
    document.querySelectorAll('.foreign-symbol').forEach(el => {
        el.innerText = selectedSymbol;
    });

    // Sync all row currency selectors if they exist
    document.querySelectorAll('.item-currency-f').forEach(sel => {
        if (currency !== 'PHP') sel.value = currency;
    });

    updateCalculations();
};

window.handleConversionRateChange = function(input) {
    const rate = parseFloat(input.value) || 1;
    if (window.poState) window.poState.conversionRate = rate;
    updateCalculations();
};

function updateCalculations() {
    const rows = document.querySelectorAll('#po-items-tbody tr');
    let grandTotalPHP = 0;
    let grandTotalForeign = 0;
    let actualGrandTotalPHP = 0;
    let totalDeductionPHP = 0;
    let totalDeductionForeign = 0;

    // Currency-related variables must be declared at the top
    const currency = window.poState?.currency || 'PHP';
    const convRate = window.poState?.conversionRate || 1;
    const symbols = { 'PHP': '₱', 'USD': '$', 'TWD': 'NT$' };
    const displaySymbol = symbols[currency] || '₱';
    const sym = symbols; // Alias for backward compatibility

    rows.forEach(row => {
        const checkbox = row.querySelector('.po-item-checkbox');
        const qtyInput = row.querySelector('.item-qty');
        const actualQtyInput = row.querySelector('.item-actual-qty');
        const unitCostInput = row.querySelector('.item-unit-cost');
        const unitCostForeignInput = row.querySelector('.item-unit-cost-f');
        const rowCurrencySel = row.querySelector('.item-currency-f');
        const discInput = row.querySelector('.item-disc');
        const subtotalSpan = row.querySelector('.item-subtotal');
        const actualSubtotalSpan = row.querySelector('.item-actual-subtotal');
        const subtotalForeignSpan = row.querySelector('.item-subtotal-f');
        const rowForeignSymbol = row.querySelector('.foreign-symbol');

        if (qtyInput && unitCostInput && discInput && subtotalSpan) {
            const qty = parseFloat(qtyInput.value) || 0;
            const actualQty = parseFloat(actualQtyInput?.value) || 0;
            const discPercent = parseFloat(discInput.value) || 0;
            
            let unitCostPHP = parseFloat(unitCostInput.value) || 0;
            let unitCostForeign = parseFloat(unitCostForeignInput.value) || 0;

            if (rowCurrencySel && rowForeignSymbol) {
                rowForeignSymbol.innerText = sym[rowCurrencySel.value] || '$';
            }

            const deductionPHP = (qty * unitCostPHP) * (discPercent / 100);
            const deductionF = (qty * unitCostForeign) * (discPercent / 100);

            const subtotalPHP = (qty * unitCostPHP) - deductionPHP;
            const subtotalForeign = (qty * unitCostForeign) - deductionF;
            const actualSubtotalPHP = (actualQty * unitCostPHP) - ((actualQty * unitCostPHP) * (discPercent / 100));

            subtotalSpan.innerText = subtotalPHP.toLocaleString(undefined, {minimumFractionDigits: 2});
            if (actualSubtotalSpan) {
                actualSubtotalSpan.innerText = actualSubtotalPHP.toLocaleString(undefined, {minimumFractionDigits: 2});
            }
            if (subtotalForeignSpan) {
                subtotalForeignSpan.innerText = subtotalForeign.toLocaleString(undefined, {minimumFractionDigits: 2});
            }

            const subtotalSymbol = row.querySelector('.subtotal-symbol');
            const actualSubtotalSymbol = row.querySelector('.actual-subtotal-symbol');
            if (subtotalSymbol) subtotalSymbol.innerText = sym[currency] || '₱';
            if (actualSubtotalSymbol) actualSubtotalSymbol.innerText = sym[currency] || '₱';

            // Only add to totals if checkbox is checked
            if (checkbox && checkbox.checked) {
                totalDeductionPHP += deductionPHP;
                totalDeductionForeign += deductionF;
                grandTotalPHP += subtotalPHP;
                grandTotalForeign += subtotalForeign;
                actualGrandTotalPHP += actualSubtotalPHP;
            }
        }
    });

    const totalDeductionPHPDisplay = document.getElementById('po-items-total-deduction-php');
    const totalDeductionFDisplay = document.getElementById('po-items-total-deduction-f');
    if (totalDeductionPHPDisplay) totalDeductionPHPDisplay.innerText = '₱ ' + totalDeductionPHP.toLocaleString(undefined, {minimumFractionDigits: 2});
    if (totalDeductionFDisplay) {
        totalDeductionFDisplay.innerHTML = `<span class="foreign-symbol">${displaySymbol}</span> ` + totalDeductionForeign.toLocaleString(undefined, {minimumFractionDigits: 2});
    }

    // Apply additional discount after all item calculations
    const additionalDiscountPercent = parseFloat(document.getElementById('additional-discount-percent')?.value || 0);
    const additionalDiscountAmount = grandTotalPHP * (additionalDiscountPercent / 100);
    const additionalDiscountAmountActual = actualGrandTotalPHP * (additionalDiscountPercent / 100);
    
    // Update additional discount amount display with correct currency
    const additionalDiscountAmountDisplay = document.getElementById('additional-discount-amount');
    if (additionalDiscountAmountDisplay) {
        const displayAmt = currency === 'PHP' ? additionalDiscountAmount : additionalDiscountAmount / convRate;
        additionalDiscountAmountDisplay.innerText = displaySymbol + ' ' + displayAmt.toLocaleString(undefined, {minimumFractionDigits: 2});
    }
    
    // Apply additional discount to totals
    grandTotalPHP -= additionalDiscountAmount;
    actualGrandTotalPHP -= additionalDiscountAmountActual;
    grandTotalForeign -= (grandTotalForeign * (additionalDiscountPercent / 100));

    // Store additional discount in state
    if (window.poState) {
        window.poState.additionalDiscountPercent = additionalDiscountPercent;
        window.poState.additionalDiscountAmount = additionalDiscountAmount;
    }

    const grandTotalDisplay = document.getElementById('po-items-grand-total');
    if (grandTotalDisplay) {
        const displayAmt = currency === 'PHP' ? grandTotalPHP : grandTotalPHP / convRate;
        grandTotalDisplay.innerText = displaySymbol + ' ' + displayAmt.toLocaleString(undefined, {minimumFractionDigits: 2});
    }

    const actualGrandTotalDisplay = document.getElementById('po-items-actual-grand-total');
    if (actualGrandTotalDisplay) {
        const displayAmt = currency === 'PHP' ? actualGrandTotalPHP : actualGrandTotalPHP / convRate;
        actualGrandTotalDisplay.innerText = displaySymbol + ' ' + displayAmt.toLocaleString(undefined, {minimumFractionDigits: 2});
    }

    const grandTotalForeignDisplay = document.getElementById('po-items-grand-total-f');
    if (grandTotalForeignDisplay) {
        let foreignCurrency = window.poState?.currency || 'USD';
        
        if (foreignCurrency === 'PHP') {
            const firstRowCurrency = document.querySelector('.item-currency-f');
            foreignCurrency = firstRowCurrency ? firstRowCurrency.value : 'USD';
        }
        
        const symbol = symbols[foreignCurrency] || '$';
        grandTotalForeignDisplay.innerHTML = `<span class="foreign-symbol">${symbol}</span> ` + grandTotalForeign.toLocaleString(undefined, {minimumFractionDigits: 2});
    }

    const automatedTotalInput = document.getElementById('proceed-total-amount');
    if (automatedTotalInput) {
        automatedTotalInput.value = grandTotalPHP.toLocaleString(undefined, {minimumFractionDigits: 2});
    }

    if (window.poState) {
        window.poState.grandTotal = grandTotalPHP;
        window.poState.actualGrandTotal = actualGrandTotalPHP;
        window.poState.grandTotalForeign = grandTotalForeign;
    }
}

/**
 * Close Proceed Modal with unsaved-changes check
 */
window.checkAndCloseProceedModal = function() {
    const currency = document.getElementById('proceed-currency')?.value;
    const convRate = document.getElementById('proceed-conv-rate')?.value;
    const invoiceInputs = document.querySelectorAll('.supplier-invoice-input');
    let hasInvoice = false;
    invoiceInputs.forEach(inp => { if (inp.value.trim()) hasInvoice = true; });

    if ((currency && currency !== 'PHP') || (convRate && convRate !== '1.0000' && convRate !== '1') || hasInvoice) {
        toggleModal('discard-proceed-modal', true);
        return;
    }

    toggleModal('proceed-modal', false);
};

/**
 * Confirm discard from proceed modal
 */
window.confirmDiscardProceedModal = function() {
    toggleModal('discard-proceed-modal', false);
    toggleModal('proceed-modal', false);

    const invoiceContainer = document.getElementById('invoice-inputs-container');
    if (invoiceContainer) {
        const allInputs = invoiceContainer.querySelectorAll('.relative.group');
        allInputs.forEach((wrapper, i) => {
            if (i === 0) {
                const inp = wrapper.querySelector('.supplier-invoice-input');
                if (inp) inp.value = '';
            } else {
                wrapper.remove();
            }
        });
    }

    const currencyEl = document.getElementById('proceed-currency');
    if (currencyEl) {
        currencyEl.value = 'PHP';
        if (typeof handleCurrencyChange === 'function') handleCurrencyChange(currencyEl);
    }

    const convRateEl = document.getElementById('proceed-conv-rate');
    if (convRateEl) convRateEl.value = '';
};


/**
 * Open edit history modal for a purchase order
 */
window.openEditHistoryModal = async function(poId) {
    try {
        // Initialize edit state
        window.editState = {
            currentStep: 1,
            poId: poId,
            originalData: null,
            items: [],
            currency: 'PHP',
            conversionRate: 1,
            additionalDiscountPercent: 0
        };

        // Fetch PO data - using correct route
        const baseUrl = window.purchaseRoutes?.poItemsUrl || '/hatdog/public/admin/purchase/purchase-order/po-items/:poId';
        const url = baseUrl.replace(':poId', poId);
        const res = await fetch(url);
        const result = await res.json();

        if (!result.success) throw new Error(result.message || 'Failed to fetch PO data');

        // Store original data
        window.editState.originalData = result.note || {};
        window.editState.items = result.items || [];
        window.editState.currency = result.note?.currency || 'PHP';
        window.editState.conversionRate = result.note?.conversion_rate || 1;
        window.editState.additionalDiscountPercent = result.note?.additional_discount_percent || 0;

        // Populate Step 1 fields
        document.getElementById('edit-supplier-code').value = result.note?.supplier_code || '';
        document.getElementById('edit-supplier-name').value = result.note?.supplier_name || '';
        document.getElementById('edit-control-no').value = result.note?.receiving_no || '';
        document.getElementById('edit-pn-no').value = result.note?.po_number || '';
        document.getElementById('edit-currency').value = window.editState.currency;
        document.getElementById('edit-conv-rate').value = window.editState.conversionRate;
        document.getElementById('edit-additional-discount-percent').value = window.editState.additionalDiscountPercent;

        // Populate invoice numbers
        const invoiceNumbers = Array.isArray(result.note?.invoice_numbers) ? result.note.invoice_numbers : [];
        const container = document.getElementById('edit-invoice-inputs-container');
        if (container && invoiceNumbers.length > 0) {
            container.innerHTML = invoiceNumbers.map((inv, idx) => `
                <div class="relative group">
                    <input type="text" value="${inv}" class="supplier-invoice-input w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm font-mono focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-slate-800 bg-slate-50/50" placeholder="Invoice #${idx + 1}">
                </div>
            `).join('');
        }

        // Open modal and go to step 1
        toggleModal('edit-modal', true);
        goToEditStep(1);
        ensurePurchaseOrderColumnSearch('edit');
        resetPurchaseOrderColumnSearch('edit');
        ensureFixArrangeButton();

        // Check for changes from purchase note edit
        const stored = sessionStorage.getItem('po_edit_changes');
        console.log(`[PO] openEditHistoryModal: poId=${poId}, po_edit_changes=${stored ? 'found' : 'NOT FOUND'}`);
        let deletedCodes = [];
        let addedCodes = [];
        let updatedCodes = [];
        
        if (stored) {
            try {
                const changes = JSON.parse(stored);
                console.log(`[PO] po_edit_changes: poId=${changes.poId}, added=${(changes.addedItems||[]).length} items, comparing ${changes.poId} === ${poId}: ${parseInt(changes.poId) === parseInt(poId)}`);
                if (parseInt(changes.poId) === parseInt(poId)) {
                    deletedCodes = (changes.deletedItems || []).map(d => d.product_code);
                    addedCodes = (changes.addedItems || []).map(a => a.product_code);
                    updatedCodes = (changes.updatedItems || []).map(u => u.product_code);

                    if (changes.updatedItems && changes.updatedItems.length > 0) {
                        changes.updatedItems.forEach(updatedItem => {
                            const existing = window.editState.items.find(item =>
                                Number(item.product_id || item.productId || 0) === Number(updatedItem.product_id || 0)
                                || item.product_code === updatedItem.product_code
                            );
                            if (existing) {
                                Object.assign(existing, updatedItem, {
                                    _updatedFromPurchaseNote: true,
                                    _updatedChanges: updatedItem.changes || {},
                                });
                            }
                        });
                    }
                    
                    // Keep deleted PN items visible in the PO edit modal until Save.
                    if (changes.deletedItems && changes.deletedItems.length > 0) {
                        changes.deletedItems.forEach(deletedItem => {
                            const code = deletedItem.product_code;
                            const existing = window.editState.items.find(item => item.product_code === code);
                            if (existing) {
                                existing._deletedFromPurchaseNote = true;
                                existing._deletedNotice = 'Deleted in Purchase Note - save to delete from this invoice';
                            } else if (code) {
                                window.editState.items.push({
                                    ...deletedItem,
                                    _deletedFromPurchaseNote: true,
                                    _deletedNotice: 'Deleted in Purchase Note - save to delete from this invoice',
                                });
                            }
                        });
                    }
                    
                    // Add new items to state
                    if (changes.addedItems && changes.addedItems.length > 0) {
                        changes.addedItems.forEach(newItem => {
                            // Check if item doesn't already exist
                            if (!window.editState.items.some(item => item.product_code === newItem.product_code)) {
                                window.editState.items.push(newItem);
                            }
                        });
                    }
                }
            } catch (e) {
                console.error('Error applying purchase note changes:', e);
            }
        }

        // Safety dedup: remove any duplicate product_code entries
        const seenCodes = new Set();
        window.editState.items = window.editState.items.filter(item => {
            const code = (item.product_code || item.code || '').trim();
            if (!code || seenCodes.has(code)) return false;
            seenCodes.add(code);
            return true;
        });

        // Render items in Step 2
        renderEditItems(window.editState.items);

        // Apply blink effects AFTER rendering, using the stored codes
        if (deletedCodes.length > 0 || addedCodes.length > 0 || updatedCodes.length > 0) {
            setTimeout(() => {
                const rows = document.querySelectorAll('#edit-items-tbody tr');
                rows.forEach(row => {
                    const codeCell = row.querySelector('td:first-child');
                    if (!codeCell) return;
                    const code = codeCell.textContent.trim();

                    if (deletedCodes.includes(code)) {
                        row.classList.add('po-deleted-from-pn-row');
                    }
                    
                    // Added items should blink green
                    if (addedCodes.includes(code)) {
                        row.classList.add('blink-green');
                        row.style.transition = 'background-color 0.3s';
                    }

                    // Existing items changed in the Purchase Note are highlighted blue.
                    if (updatedCodes.includes(code)) {
                        row.classList.add('bg-blue-100');
                        row.style.boxShadow = 'inset 4px 0 0 #2563eb';
                    }
                });
                
                // Clear sessionStorage after applying
                sessionStorage.removeItem('po_edit_changes');
            }, 100);
        }

    } catch (error) {
        console.error('Error opening edit modal:', error);
        alert('Failed to load purchase order: ' + error.message);
    }
};

window.applyEditBlinkEffects = function(poId) {
    // This function is deprecated - blinking is now handled in openEditHistoryModal
};

/**
 * Print a purchase order from history
 */
window.printHistoryPO = async function(poId) {
    try {
        // Fetch PO data - using correct route
        const baseUrl = window.purchaseRoutes?.poItemsUrl || '/hatdog/public/admin/purchase/purchase-order/po-items/:poId';
        const url = baseUrl.replace(':poId', poId);
        const res = await fetch(url);
        const result = await res.json();

        if (!result.success) throw new Error(result.message || 'Failed to fetch PO data');

        // The backend already returns the PO rows in Purchase Note arrangement.
        // Do not re-sort by purchase_order_items.id because legacy POs may have
        // jumbled IDs that differ from the linked Purchase Note sequence.
        const rawItems = Array.isArray(result.items) ? result.items : [];
        const items = rawItems;
        const currency = result.note?.currency || 'PHP';
        const symbols = { 'PHP': '₱', 'USD': '$', 'TWD': 'NT$' };
        const currencySymbol = symbols[currency] || '₱';
        
        // Get invoice numbers
        const invoiceNumbers = Array.isArray(result.note?.invoice_numbers) ? result.note.invoice_numbers : [];
        const receivingNo = result.note?.receiving_no || '';
        const supplierName = result.note?.supplier_name || '';
        const additionalDiscountPercent = parseFloat(result.note?.additional_discount_percent || 0);

        // Build items HTML - only actual rows, no blank rows
        let itemsHtml = '';
        let totalBeforeDiscount = 0;
        let totalDiscount = 0;
        
        items.forEach((item) => {
            // Use actual_quantity for print
            const qty = item.actual_quantity || item.quantity || 0;
            const unitPrice = parseFloat(item.unit_price || 0);
            const discount = parseFloat(item.discount_percent || 0);
            const itemTotal = qty * unitPrice;
            const itemDiscount = itemTotal * (discount / 100);
            const subtotal = itemTotal - itemDiscount;
            
            totalBeforeDiscount += itemTotal;
            totalDiscount += itemDiscount;
            
            itemsHtml += `
                <tr>
                    <td class="text-center">${qty}</td>
                    <td class="text-center">${item.unit || ''}</td>
                    <td>${item.part_number || '---'}</td>
                    <td>${item.description || ''}</td>
                    <td class="text-right">${currencySymbol} ${unitPrice.toFixed(2)}</td>
                    <td class="text-center">${discount.toFixed(2)}%</td>
                    <td class="text-right">${currencySymbol} ${subtotal.toFixed(2)}</td>
                </tr>
            `;
        });
        
        // No blank rows - only print actual data

        // Calculate totals
        const totalAfterItemDiscount = totalBeforeDiscount - totalDiscount;
        const additionalDiscountAmount = totalAfterItemDiscount * (additionalDiscountPercent / 100);
        const finalTotal = totalAfterItemDiscount - additionalDiscountAmount;
        
        const today = new Date();
        const dateStr = today.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });

        // Create print window with full charge receiving layout
        const printWindow = window.open('', '_blank', 'width=800,height=600');
        printWindow.document.write(`
<!DOCTYPE html>
<html>
<head>
    <title>Charge Receiving - ${receivingNo}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; padding: 30px; font-size: 11px; }
        .charge-receiving-layout { max-width: 8.5in; margin: 0 auto; background: white; }
        .header { text-align: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #000; }
        .header h1 { font-size: 18px; font-weight: bold; margin: 0 0 5px 0; letter-spacing: 0.5px; }
        .header .title { font-size: 16px; font-weight: bold; margin-top: 15px; text-decoration: underline; letter-spacing: 1px; }
        .info-row { display: flex; margin-bottom: 10px; align-items: center; }
        .info-group { display: flex; align-items: center; flex: 1; }
        .info-label { font-weight: bold; white-space: nowrap; font-size: 11px; margin-right: 10px; }
        .info-value { border-bottom: 1px solid #000; padding: 2px 5px; flex: 1; min-height: 20px; font-size: 11px; }
        .separator { border-top: 2px solid #000; margin: 15px 0; }
        table.items-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        table.items-table th, table.items-table td { border: 1px solid #000; padding: 6px 8px; text-align: left; }
        table.items-table th { background-color: #f0f0f0; font-weight: bold; text-align: center; font-size: 10px; letter-spacing: 0.3px; }
        table.items-table td { font-size: 10px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .totals-section { margin-top: 15px; text-align: right; font-size: 11px; font-weight: bold; }
        .totals-section > div { margin: 8px 0; padding: 3px 0; }
        .particular-section { margin-top: 25px; margin-bottom: 30px; }
        .particular-label { font-weight: bold; margin-bottom: 8px; font-size: 11px; }
        .particular-content { border: 1px solid #000; padding: 12px; min-height: 70px; }
        .signatures { display: table; width: 100%; margin-top: 50px; }
        .signature-cell { display: table-cell; text-align: center; width: 33.33%; padding: 10px; }
        .signature-line { border-top: 1px solid #000; margin-bottom: 8px; padding-top: 40px; }
        .signature-label { font-size: 10px; font-weight: bold; letter-spacing: 0.5px; }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="charge-receiving-layout">
        <!-- Header -->
        <div class="header">
            <h1>W68 AUTO PARTS & SERVICE CENTER</h1>
            <div class="title">CHARGE RECEIVING</div>
        </div>

        <!-- Invoice and Receiving Info -->
        <div style="margin-bottom: 20px;">
            <div class="info-row">
                <div class="info-group" style="flex: 1;">
                    <div class="info-label">Invoice No.</div>
                    <div class="info-value" style="flex: 1;">${invoiceNumbers.join(', ') || ''}</div>
                </div>
                <div style="width: 30px;"></div>
                <div class="info-group" style="flex: 1;">
                    <div class="info-label">Receiving No.</div>
                    <div class="info-value" style="flex: 1;">${receivingNo}</div>
                </div>
            </div>
            
            <div class="info-row">
                <div class="info-group" style="flex: 1;">
                    <div class="info-label">Supplier:</div>
                    <div class="info-value" style="flex: 1;">${supplierName}</div>
                </div>
                <div style="width: 30px;"></div>
                <div class="info-group" style="flex: 1;">
                    <div class="info-label">Date:</div>
                    <div class="info-value" style="flex: 1;">${dateStr}</div>
                </div>
            </div>
        </div>

        <div class="separator"></div>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 8%;">QTY</th>
                    <th style="width: 10%;">UNIT</th>
                    <th style="width: 20%;">PART NO.</th>
                    <th style="width: 30%;">DESCRIPTION</th>
                    <th style="width: 12%;">UNIT PRICE</th>
                    <th style="width: 8%;">DISC%</th>
                    <th style="width: 12%;">SUBTOTAL</th>
                </tr>
            </thead>
            <tbody>
                ${itemsHtml}
            </tbody>
        </table>

        <div class="separator"></div>

        <!-- Totals -->
        <div class="totals-section">
            <div style="border-top: 1px solid #ccc; padding-top: 5px; margin-top: 5px;">SUBTOTAL: ${currencySymbol} ${totalAfterItemDiscount.toLocaleString('en-US', {minimumFractionDigits: 2})}</div>
            <div style="color: #d97706; font-weight: bold;">ADDITIONAL DISCOUNT: ${additionalDiscountPercent.toFixed(2)}%</div>
            <div style="border-top: 2px solid #000; padding-top: 5px; margin-top: 5px; font-size: 13px;">NET TOTAL: ${currencySymbol} ${finalTotal.toLocaleString('en-US', {minimumFractionDigits: 2})}</div>
        </div>

        <!-- Particular -->
        <div class="particular-section">
            <div class="particular-label">PARTICULAR:</div>
            <div class="particular-content"></div>
        </div>

        <!-- Signatures -->
        <div class="signatures">
            <div class="signature-cell">
                <div class="signature-line"></div>
                <div class="signature-label">PREPARED BY</div>
            </div>
            <div class="signature-cell">
                <div class="signature-line"></div>
                <div class="signature-label">CHECKED BY</div>
            </div>
            <div class="signature-cell">
                <div class="signature-line"></div>
                <div class="signature-label">APPROVED BY</div>
            </div>
        </div>
    </div>
    
    <script>
        window.onload = function() {
            window.print();
            // Auto-close after print (optional)
            // setTimeout(function() { window.close(); }, 100);
        };
    </script>
</body>
</html>
        `);
        printWindow.document.close();

    } catch (error) {
        console.error('Error printing PO:', error);
        alert('Failed to print purchase order: ' + error.message);
    }
};


/**
 * Legacy edit item renderer retained for reference; active renderer follows below.
 */
function renderEditItemsLegacy(items) {
    const tbody = document.getElementById('edit-items-tbody');
    if (!tbody) return;

    if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="12" class="py-8 text-center text-slate-400 italic text-xs">No items found</td></tr>';
        return;
    }

    const convRate = window.editState?.conversionRate || 1;
    const currentCurrency = window.editState?.currency || 'PHP';

    tbody.innerHTML = items.map(item => {
        // Calculate unit_price_converted if missing
        let unitPriceConverted = parseFloat(item.unit_price_converted || 0);
        if (!unitPriceConverted && item.unit_price && convRate !== 1) {
            unitPriceConverted = item.unit_price / convRate;
        }
        
        const discountPercent = parseFloat(item.discount_percent || 0);
        const itemCurrency = item.currency || currentCurrency;
        
        return `
        <tr>
            <td class="p-3 px-4 font-bold text-maroon">${item.product_code}</td>
            <td class="p-3 px-4 text-slate-500 font-mono">${item.part_number || '---'}</td>
            <td class="p-3 px-4 text-slate-700">${item.description}</td>
            <td class="p-3 px-4 text-center">
                <input type="number" value="${item.quantity}" oninput="updateEditCalculations()" class="item-qty w-20 px-2 py-1.5 border border-slate-200 rounded text-center focus:ring-1 focus:ring-blue-500/20">
            </td>
            <td class="p-3 px-4 text-center">
                <input type="number" value="${item.actual_quantity ?? item.quantity}" oninput="updateEditCalculations()" class="item-actual-qty w-20 px-2 py-1.5 border border-blue-300 rounded text-center focus:ring-1 focus:ring-blue-500 bg-blue-50 font-bold text-blue-700">
            </td>
            <td class="p-3 px-4 text-center text-slate-500">${item.unit || ''}</td>
            <td class="p-3 px-4">
                <input type="number" value="${item.unit_price}" oninput="updateEditCalculations()" class="item-unit-cost w-32 px-2 py-1.5 border border-slate-200 rounded focus:ring-1 focus:ring-blue-500/20">
            </td>
            <td class="p-3 px-4">
                <div class="flex items-center space-x-1">
                    <select onchange="updateEditCalculations()" class="item-currency-f px-1 py-1.5 border border-slate-200 rounded text-[10px] focus:ring-1 focus:ring-blue-500 outline-none bg-slate-50">
                        <option value="PHP" ${itemCurrency === 'PHP' ? 'selected' : ''}>PHP</option>
                        <option value="USD" ${itemCurrency === 'USD' ? 'selected' : ''}>USD</option>
                        <option value="TWD" ${itemCurrency === 'TWD' ? 'selected' : ''}>TWD</option>
                    </select>
                    <input type="number" value="${unitPriceConverted.toFixed(2)}" oninput="updateEditCalculations()" class="item-unit-cost-f w-32 px-2 py-1.5 border border-slate-200 rounded focus:ring-1 focus:ring-blue-500/20 bg-slate-50 font-bold text-blue-700" placeholder="0.00">
                </div>
            </td>
            <td class="p-3 px-4">
                <input type="number" value="${discountPercent}" oninput="updateEditCalculations()" class="item-disc w-20 px-2 py-1.5 border border-slate-200 rounded text-center focus:ring-1 focus:ring-blue-500/20" placeholder="0">
            </td>
            <td class="p-3 px-4 text-right font-bold text-slate-800"><span class="subtotal-symbol">₱</span> <span class="item-subtotal">${(item.quantity * item.unit_price).toLocaleString(undefined, {minimumFractionDigits: 2})}</span></td>
            <td class="p-3 px-4 text-right font-bold text-blue-700">
                <div class="actual-subtotal-container">
                    <span class="actual-subtotal-symbol">₱</span> <span class="item-actual-subtotal">${((item.actual_quantity ?? item.quantity) * item.unit_price).toLocaleString(undefined, {minimumFractionDigits: 2})}</span>
                </div>
            </td>
            <td class="p-3 px-4 text-right font-bold text-blue-700"><span class="foreign-symbol">$</span> <span class="item-subtotal-f">0.00</span></td>
        </tr>
    `;
    }).join('');

    updateEditCalculations();
    if (window.lucide) window.lucide.createIcons();
}

function renderEditItems(items) {
    const tbody = document.getElementById('edit-items-tbody');
    if (!tbody) return;

    if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="12" class="py-8 text-center text-slate-400 italic text-xs">No items found</td></tr>';
        return;
    }

    const convRate = window.editState?.conversionRate || 1;
    const currentCurrency = window.editState?.currency || 'PHP';

    tbody.innerHTML = items.map((item, itemIndex) => {
        const isDeletedFromPN = item._deletedFromPurchaseNote === true;
        const isUpdatedFromPN = item._updatedFromPurchaseNote === true;
        let unitPriceConverted = parseFloat(item.unit_price_converted || 0);
        if (!unitPriceConverted && item.unit_price && convRate !== 1) {
            unitPriceConverted = item.unit_price / convRate;
        }

        const discountPercent = parseFloat(item.discount_percent || 0);
        const itemCurrency = item.currency || currentCurrency;
        const rowClass = isDeletedFromPN ? 'po-deleted-from-pn-row' : (isUpdatedFromPN ? 'bg-blue-100' : '');
        const rowStyle = isDeletedFromPN
            ? 'background:#4b0b13;color:#facc15;'
            : (isUpdatedFromPN ? 'box-shadow:inset 4px 0 0 #2563eb;' : '');
        const mutedTextClass = isDeletedFromPN ? 'text-yellow-200' : 'text-slate-500';
        const disabledAttr = isDeletedFromPN ? 'disabled' : '';
        const inputClass = isDeletedFromPN ? 'bg-maroon/80 border-yellow-300 text-yellow-300 cursor-not-allowed opacity-80' : '';
        const descriptionHtml = isDeletedFromPN
            ? `${item.description || ''}<div class="mt-1 inline-flex items-center rounded bg-yellow-300 px-2 py-0.5 text-[9px] font-black uppercase tracking-widest text-maroon">Deleted in Purchase Note - save to delete from this invoice</div>`
            : (item.description || '');

        return `
        <tr class="${rowClass}" data-item-index="${itemIndex}" data-deleted-from-pn="${isDeletedFromPN ? '1' : '0'}" style="${rowStyle}">
            <td class="p-3 px-4 font-bold ${isDeletedFromPN ? 'text-yellow-300' : 'text-maroon'}">${item.product_code}</td>
            <td class="p-3 px-4 ${mutedTextClass} font-mono">${item.part_number || '---'}</td>
            <td class="p-3 px-4 ${isDeletedFromPN ? 'text-yellow-300' : 'text-slate-700'}">${descriptionHtml}</td>
            <td class="p-3 px-4 text-center">
                <input type="number" value="${item.quantity}" ${disabledAttr} oninput="updateEditCalculations()" class="item-qty w-20 px-2 py-1.5 border border-slate-200 rounded text-center focus:ring-1 focus:ring-blue-500/20 ${inputClass}">
            </td>
            <td class="p-3 px-4 text-center">
                <input type="number" value="${item.actual_quantity ?? item.quantity}" ${disabledAttr} oninput="updateEditCalculations()" class="item-actual-qty w-20 px-2 py-1.5 border border-blue-300 rounded text-center focus:ring-1 focus:ring-blue-500 bg-blue-50 font-bold text-blue-700 ${inputClass}">
            </td>
            <td class="p-3 px-4 text-center ${mutedTextClass}">
                <input type="text" value="${item.unit || ''}" ${disabledAttr} oninput="updateEditCalculations()" class="item-unit w-20 px-2 py-1.5 border border-slate-200 rounded text-center focus:ring-1 focus:ring-blue-500/20 ${inputClass}" maxlength="50">
            </td>
            <td class="p-3 px-4">
                <input type="number" value="${item.unit_price}" ${disabledAttr} oninput="updateEditCalculations()" class="item-unit-cost w-32 px-2 py-1.5 border border-slate-200 rounded focus:ring-1 focus:ring-blue-500/20 ${inputClass}">
            </td>
            <td class="p-3 px-4">
                <div class="flex items-center space-x-1">
                    <select onchange="updateEditCalculations()" ${disabledAttr} class="item-currency-f px-1 py-1.5 border border-slate-200 rounded text-[10px] focus:ring-1 focus:ring-blue-500 outline-none bg-slate-50 ${inputClass}">
                        <option value="PHP" ${itemCurrency === 'PHP' ? 'selected' : ''}>PHP</option>
                        <option value="USD" ${itemCurrency === 'USD' ? 'selected' : ''}>USD</option>
                        <option value="TWD" ${itemCurrency === 'TWD' ? 'selected' : ''}>TWD</option>
                    </select>
                    <input type="number" value="${unitPriceConverted.toFixed(2)}" ${disabledAttr} oninput="updateEditCalculations()" class="item-unit-cost-f w-32 px-2 py-1.5 border border-slate-200 rounded focus:ring-1 focus:ring-blue-500/20 bg-slate-50 font-bold text-blue-700 ${inputClass}" placeholder="0.00">
                </div>
            </td>
            <td class="p-3 px-4">
                <input type="number" value="${discountPercent}" ${disabledAttr} oninput="updateEditCalculations()" class="item-disc w-20 px-2 py-1.5 border border-slate-200 rounded text-center focus:ring-1 focus:ring-blue-500/20 ${inputClass}" placeholder="0">
            </td>
            <td class="p-3 px-4 text-right font-bold ${isDeletedFromPN ? 'text-yellow-300 line-through' : 'text-slate-800'}"><span class="subtotal-symbol">PHP</span> <span class="item-subtotal">${(item.quantity * item.unit_price).toLocaleString(undefined, {minimumFractionDigits: 2})}</span></td>
            <td class="p-3 px-4 text-right font-bold ${isDeletedFromPN ? 'text-yellow-300 line-through' : 'text-blue-700'}">
                <div class="actual-subtotal-container">
                    <span class="actual-subtotal-symbol">PHP</span> <span class="item-actual-subtotal">${((item.actual_quantity ?? item.quantity) * item.unit_price).toLocaleString(undefined, {minimumFractionDigits: 2})}</span>
                </div>
            </td>
            <td class="p-3 px-4 text-right font-bold ${isDeletedFromPN ? 'text-yellow-300 line-through' : 'text-blue-700'}"><span class="foreign-symbol">$</span> <span class="item-subtotal-f">0.00</span></td>
        </tr>
    `;
    }).join('');

    updateEditCalculations();
    ensurePurchaseOrderColumnSearch('edit');
    filterPurchaseOrderModalRows('edit');
    ensureFixArrangeButton();
    if (window.lucide) window.lucide.createIcons();
}

/**
 * Navigate between edit modal steps
 */
window.goToEditStep = function(step) {
    if (!window.editState) return;
    
    const totalSteps = 3;
    if (step < 1 || step > totalSteps) return;
    
    window.editState.currentStep = step;
    
    // Hide all steps
    for (let i = 1; i <= totalSteps; i++) {
        document.getElementById(`edit-step-${i}`)?.classList.add('hidden');
    }
    
    // Show current step
    document.getElementById(`edit-step-${step}`)?.classList.remove('hidden');
    
    // Update step indicators
    for (let i = 1; i <= totalSteps; i++) {
        const indicator = document.getElementById(`edit-step-indicator-${i}`);
        const circle = indicator?.querySelector('div');
        const label = indicator?.querySelector('span');
        
        if (i < step) {
            circle?.classList.remove('bg-blue-600', 'bg-slate-300');
            circle?.classList.add('bg-green-500', 'text-white');
            label?.classList.remove('text-blue-600', 'text-slate-500');
            label?.classList.add('text-green-600');
        } else if (i === step) {
            circle?.classList.remove('bg-slate-300', 'bg-green-500');
            circle?.classList.add('bg-blue-600', 'text-white');
            circle?.classList.remove('text-slate-500');
            label?.classList.remove('text-slate-500', 'text-green-600');
            label?.classList.add('text-blue-600');
        } else {
            circle?.classList.remove('bg-blue-600', 'bg-green-500');
            circle?.classList.add('bg-slate-300', 'text-slate-500');
            circle?.classList.remove('text-white');
            label?.classList.remove('text-blue-600', 'text-green-600');
            label?.classList.add('text-slate-500');
        }
    }
    
    // Update buttons
    const backBtn = document.getElementById('edit-btn-back');
    const nextBtn = document.getElementById('edit-btn-next');
    const saveBtn = document.getElementById('edit-btn-save');
    
    if (step === 1) {
        backBtn?.classList.add('hidden');
        nextBtn?.classList.remove('hidden');
        saveBtn?.classList.add('hidden');
        if (nextBtn) nextBtn.innerHTML = '<span>Next: Edit Items</span><i data-lucide="chevron-right" class="w-4 h-4"></i>';
    } else if (step === 2) {
        backBtn?.classList.remove('hidden');
        nextBtn?.classList.remove('hidden');
        saveBtn?.classList.add('hidden');
        if (nextBtn) nextBtn.innerHTML = '<span>Next: Review</span><i data-lucide="chevron-right" class="w-4 h-4"></i>';
    } else if (step === 3) {
        backBtn?.classList.remove('hidden');
        nextBtn?.classList.add('hidden');
        saveBtn?.classList.remove('hidden');
        
        // Populate print layout
        populateEditPrintLayout();
    }
    
    if (window.lucide) window.lucide.createIcons();
};

/**
 * Update calculations in edit modal
 */
window.updateEditCalculations = function() {
    const rows = document.querySelectorAll('#edit-items-tbody tr');
    let grandTotalPHP = 0;
    let grandTotalForeign = 0;
    let actualGrandTotalPHP = 0;
    let totalDeductionPHP = 0;
    let totalDeductionForeign = 0;

    const currency = window.editState?.currency || 'PHP';
    const convRate = window.editState?.conversionRate || 1;
    const symbols = { 'PHP': '₱', 'USD': '$', 'TWD': 'NT$' };
    const displaySymbol = symbols[currency] || '₱';
    const sym = symbols;

    rows.forEach(row => {
        if (row.dataset.deletedFromPn === '1') {
            return;
        }

        const qtyInput = row.querySelector('.item-qty');
        const actualQtyInput = row.querySelector('.item-actual-qty');
        const unitCostInput = row.querySelector('.item-unit-cost');
        const unitCostForeignInput = row.querySelector('.item-unit-cost-f');
        const rowCurrencySel = row.querySelector('.item-currency-f');
        const discInput = row.querySelector('.item-disc');
        const subtotalSpan = row.querySelector('.item-subtotal');
        const actualSubtotalSpan = row.querySelector('.item-actual-subtotal');
        const subtotalForeignSpan = row.querySelector('.item-subtotal-f');
        const rowForeignSymbol = row.querySelector('.foreign-symbol');

        if (qtyInput && unitCostInput && discInput && subtotalSpan) {
            const qty = parseFloat(qtyInput.value) || 0;
            const actualQty = parseFloat(actualQtyInput?.value) || 0;
            const discPercent = parseFloat(discInput.value) || 0;
            
            let unitCostPHP = parseFloat(unitCostInput.value) || 0;
            let unitCostForeign = parseFloat(unitCostForeignInput.value) || 0;

            if (rowCurrencySel && rowForeignSymbol) {
                rowForeignSymbol.innerText = sym[rowCurrencySel.value] || '$';
            }

            const deductionPHP = (qty * unitCostPHP) * (discPercent / 100);
            const deductionF = (qty * unitCostForeign) * (discPercent / 100);

            totalDeductionPHP += deductionPHP;
            totalDeductionForeign += deductionF;

            const subtotalPHP = (qty * unitCostPHP) - deductionPHP;
            const subtotalForeign = (qty * unitCostForeign) - deductionF;

            const actualSubtotalPHP = (actualQty * unitCostPHP) - ((actualQty * unitCostPHP) * (discPercent / 100));

            subtotalSpan.innerText = subtotalPHP.toLocaleString(undefined, {minimumFractionDigits: 2});
            if (actualSubtotalSpan) {
                actualSubtotalSpan.innerText = actualSubtotalPHP.toLocaleString(undefined, {minimumFractionDigits: 2});
            }
            if (subtotalForeignSpan) {
                subtotalForeignSpan.innerText = subtotalForeign.toLocaleString(undefined, {minimumFractionDigits: 2});
            }

            const subtotalSymbol = row.querySelector('.subtotal-symbol');
            const actualSubtotalSymbol = row.querySelector('.actual-subtotal-symbol');
            if (subtotalSymbol) subtotalSymbol.innerText = sym[currency] || '₱';
            if (actualSubtotalSymbol) actualSubtotalSymbol.innerText = sym[currency] || '₱';

            grandTotalPHP += subtotalPHP;
            grandTotalForeign += subtotalForeign;
            actualGrandTotalPHP += actualSubtotalPHP;
        }
    });

    const totalDeductionPHPDisplay = document.getElementById('edit-items-total-deduction-php');
    const totalDeductionFDisplay = document.getElementById('edit-items-total-deduction-f');
    if (totalDeductionPHPDisplay) totalDeductionPHPDisplay.innerText = '₱ ' + totalDeductionPHP.toLocaleString(undefined, {minimumFractionDigits: 2});
    if (totalDeductionFDisplay) {
        totalDeductionFDisplay.innerHTML = `<span class="foreign-symbol">${displaySymbol}</span> ` + totalDeductionForeign.toLocaleString(undefined, {minimumFractionDigits: 2});
    }

    // Apply additional discount
    const additionalDiscountPercent = parseFloat(document.getElementById('edit-additional-discount-percent')?.value || 0);
    const additionalDiscountAmount = grandTotalPHP * (additionalDiscountPercent / 100);
    const additionalDiscountAmountActual = actualGrandTotalPHP * (additionalDiscountPercent / 100);
    
    const additionalDiscountAmountDisplay = document.getElementById('edit-additional-discount-amount');
    if (additionalDiscountAmountDisplay) {
        const displayAmt = currency === 'PHP' ? additionalDiscountAmount : additionalDiscountAmount / convRate;
        additionalDiscountAmountDisplay.innerText = displaySymbol + ' ' + displayAmt.toLocaleString(undefined, {minimumFractionDigits: 2});
    }
    
    grandTotalPHP -= additionalDiscountAmount;
    actualGrandTotalPHP -= additionalDiscountAmountActual;
    grandTotalForeign -= (grandTotalForeign * (additionalDiscountPercent / 100));

    if (window.editState) {
        window.editState.additionalDiscountPercent = additionalDiscountPercent;
        window.editState.additionalDiscountAmount = additionalDiscountAmount;
    }

    const grandTotalDisplay = document.getElementById('edit-items-grand-total');
    if (grandTotalDisplay) {
        const displayAmt = currency === 'PHP' ? grandTotalPHP : grandTotalPHP / convRate;
        grandTotalDisplay.innerText = displaySymbol + ' ' + displayAmt.toLocaleString(undefined, {minimumFractionDigits: 2});
    }

    const actualGrandTotalDisplay = document.getElementById('edit-items-actual-grand-total');
    if (actualGrandTotalDisplay) {
        const displayAmt = currency === 'PHP' ? actualGrandTotalPHP : actualGrandTotalPHP / convRate;
        actualGrandTotalDisplay.innerText = displaySymbol + ' ' + displayAmt.toLocaleString(undefined, {minimumFractionDigits: 2});
    }

    const grandTotalForeignDisplay = document.getElementById('edit-items-grand-total-f');
    if (grandTotalForeignDisplay) {
        let foreignCurrency = window.editState?.currency || 'USD';
        if (foreignCurrency === 'PHP') {
            const firstRowCurrency = document.querySelector('.item-currency-f');
            foreignCurrency = firstRowCurrency ? firstRowCurrency.value : 'USD';
        }
        const symbol = symbols[foreignCurrency] || '$';
        grandTotalForeignDisplay.innerHTML = `<span class="foreign-symbol">${symbol}</span> ` + grandTotalForeign.toLocaleString(undefined, {minimumFractionDigits: 2});
    }
};

/**
 * Handle currency change in edit modal
 */
window.handleEditCurrencyChange = function(select) {
    const currency = select.value;
    if (window.editState) window.editState.currency = currency;
    
    document.getElementById('edit-current-currency-label').innerText = currency;
    
    const symbols = { 'PHP': '₱', 'USD': '$', 'TWD': 'NT$' };
    const selectedSymbol = symbols[currency] || '$';
    document.querySelectorAll('.foreign-symbol').forEach(el => {
        el.innerText = selectedSymbol;
    });
    
    document.querySelectorAll('.item-currency-f').forEach(sel => {
        if (currency !== 'PHP') sel.value = currency;
    });
    
    updateEditCalculations();
};

/**
 * Handle conversion rate change in edit modal
 */
window.handleEditConversionRateChange = function(input) {
    const rate = parseFloat(input.value) || 1;
    if (window.editState) window.editState.conversionRate = rate;
    updateEditCalculations();
};

/**
 * Add invoice input in edit modal
 */
window.addEditInvoiceInput = function() {
    const container = document.getElementById('edit-invoice-inputs-container');
    if (!container) return;
    
    const count = container.querySelectorAll('.supplier-invoice-input').length + 1;
    const div = document.createElement('div');
    div.className = 'relative group';
    div.innerHTML = `<input type="text" class="supplier-invoice-input w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm font-mono focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-slate-800 bg-slate-50/50" placeholder="Invoice #${count}">`;
    container.appendChild(div);
};

/**
 * Populate print layout in edit modal Step 3
 */
function populateEditPrintLayout() {
    if (!window.editState) {
        return;
    }
    
    const state = window.editState;
    const currency = state.currency || 'PHP';
    const symbols = { 'PHP': '₱', 'USD': '$', 'TWD': 'NT$' };
    const currencySymbol = symbols[currency] || '₱';
    
    // IMPORTANT: Scope all queries to the edit modal's step 3 container
    const editStep3 = document.getElementById('edit-step-3');
    if (!editStep3) {
        return;
    }
    
    // Get invoice numbers
    let invoiceNumbers = [];
    const invoiceInputs = document.querySelectorAll('#edit-invoice-inputs-container .supplier-invoice-input');
    invoiceInputs.forEach(input => {
        if (input.value.trim()) {
            invoiceNumbers.push(input.value.trim());
        }
    });
    
    const invoiceNoEl = editStep3.querySelector('#cr-invoice-no');
    if (invoiceNoEl) invoiceNoEl.textContent = invoiceNumbers.join(', ') || '---';
    
    const receivingNoEl = editStep3.querySelector('#cr-receiving-no');
    const receivingValue = document.getElementById('edit-control-no')?.value;
    if (receivingNoEl) receivingNoEl.textContent = receivingValue || '---';
    
    const supplierNameEl = editStep3.querySelector('#cr-supplier-name');
    const supplierValue = document.getElementById('edit-supplier-name')?.value;
    if (supplierNameEl) supplierNameEl.textContent = supplierValue || '---';
    
    const dateEl = editStep3.querySelector('#cr-date');
    if (dateEl) {
        const today = new Date();
        dateEl.textContent = today.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    }
    
    // Get current values from Step 2 table
    const step2Rows = document.querySelectorAll('#edit-items-tbody tr');
    const itemsData = [];
    
    step2Rows.forEach((row, idx) => {
        if (row.dataset.deletedFromPn === '1') {
            return;
        }

        const cells = row.cells;
        if (!cells || cells.length < 9) {
            return;
        }
        
        const productCode = cells[0]?.textContent.trim();
        if (!productCode || productCode === 'No items found') return;
        
        const partNumber = cells[1]?.textContent.trim();
        const description = cells[2]?.textContent.trim();
        const qtyInput = row.querySelector('.item-qty');
        const actualQtyInput = row.querySelector('.item-actual-qty');
        const unitInput = row.querySelector('.item-unit');
        const unitCostInput = row.querySelector('.item-unit-cost');
        const discInput = row.querySelector('.item-disc');
        
        const qty = parseFloat(actualQtyInput?.value || qtyInput?.value || 0);
        const unitPrice = parseFloat(unitCostInput?.value || 0);
        const discount = parseFloat(discInput?.value || 0);
        const itemIndex = Number.parseInt(row.dataset.itemIndex || '', 10);
        const stateItem = Number.isInteger(itemIndex) ? window.editState?.items?.[itemIndex] : null;
        const unit = String(unitInput?.value ?? stateItem?.unit ?? '').trim();
        
        const subtotal = qty * unitPrice * (1 - discount / 100);
        
        itemsData.push({
            qty,
            partNumber,
            description,
            unitPrice,
            discount,
            subtotal,
            unit
        });
    });
    
    // Populate items table - SCOPED to edit step 3
    const tbody = editStep3.querySelector('#cr-items-tbody');
    if (tbody && itemsData.length > 0) {
        let html = '';
        itemsData.forEach(item => {
            html += `
                <tr>
                    <td class="text-center">${item.qty}</td>
                    <td class="text-center">${item.unit}</td>
                    <td>${item.partNumber || ''}</td>
                    <td>${item.description || ''}</td>
                    <td class="text-right">${currencySymbol} ${item.unitPrice.toFixed(2)}</td>
                    <td class="text-center">${item.discount.toFixed(2)}%</td>
                    <td class="text-right">${currencySymbol} ${item.subtotal.toFixed(2)}</td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    } else if (tbody) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">No items</td></tr>';
    }
    
    // Calculate totals from current step 2 data
    let totalBeforeDiscount = 0;
    let totalDiscount = 0;
    
    itemsData.forEach(item => {
        const itemTotal = item.qty * item.unitPrice;
        const itemDiscount = itemTotal * (item.discount / 100);
        
        totalBeforeDiscount += itemTotal;
        totalDiscount += itemDiscount;
    });
    
    const totalAfterItemDiscount = totalBeforeDiscount - totalDiscount;
    
    // Get additional discount from input
    const additionalDiscountInput = document.getElementById('edit-additional-discount-percent');
    const additionalDiscountPercent = parseFloat(additionalDiscountInput?.value || 0);
    const additionalDiscountAmount = totalAfterItemDiscount * (additionalDiscountPercent / 100);
    const finalTotal = totalAfterItemDiscount - additionalDiscountAmount;
    
    const subtotalEl = editStep3.querySelector('#cr-subtotal');
    if (subtotalEl) subtotalEl.textContent = currencySymbol + ' ' + totalAfterItemDiscount.toLocaleString('en-US', {minimumFractionDigits: 2});
    
    const addlDiscEl = editStep3.querySelector('#cr-additional-discount');
    if (addlDiscEl) addlDiscEl.textContent = additionalDiscountPercent.toFixed(2) + '%';
    
    const netTotalEl = editStep3.querySelector('#cr-net-total');
    if (netTotalEl) netTotalEl.textContent = currencySymbol + ' ' + finalTotal.toLocaleString('en-US', {minimumFractionDigits: 2});
}

/**
 * Enlarge edit print layout
 */
window.enlargeEditPrintLayout = function() {
    populateEditPrintLayout();
    const modal = document.getElementById('enlarge-print-modal');
    const iframe = document.getElementById('print-layout-iframe');
    
    if (!modal || !iframe) return;
    
    const printLayout = document.querySelector('#edit-step-3 .charge-receiving-layout');
    if (!printLayout) return;
    
    const html = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>${Array.from(document.styleSheets).map(sheet => {
                try { return Array.from(sheet.cssRules).map(rule => rule.cssText).join('\n'); } catch(e) { return ''; }
            }).join('\n')}</style>
        </head>
        <body style="margin: 0; padding: 20px; background: #f8f9fa;">
            ${printLayout.outerHTML}
        </body>
        </html>
    `;
    
    iframe.srcdoc = html;
    toggleModal('enlarge-print-modal', true);
};

/**
 * Print edit charge receiving
 */
window.printEditChargeReceiving = function() {
    populateEditPrintLayout();
    window.print();
};

/**
 * Check if edit modal has unsaved changes and close
 */
window.checkAndCloseEditModal = function() {
    // Simple check - could be enhanced
    toggleModal('discard-edit-modal', true);
};

/**
 * Confirm discard edit changes
 */
window.confirmDiscardEdit = function() {
    toggleModal('discard-edit-modal', false);
    toggleModal('edit-modal', false);
    window.editState = null;
};

/**
 * Save edited PO - show confirmation
 */
window.saveEditedPO = function() {
    toggleModal('edit-confirm-modal', true);
};

/**
 * Confirm and save edited PO
 */
window.confirmSaveEdit = async function() {
    toggleModal('edit-confirm-modal', false);
    
    try {
        if (!window.editState || !window.editState.poId) {
            throw new Error('Invalid edit state');
        }
        
        // Collect invoice numbers
        const invoiceNumbers = [];
        document.querySelectorAll('#edit-invoice-inputs-container .supplier-invoice-input').forEach(inp => {
            if (inp.value.trim()) invoiceNumbers.push(inp.value.trim());
        });
        
        // Collect items data
        const items = [];
        const rows = document.querySelectorAll('#edit-items-tbody tr');
        rows.forEach((row) => {
            if (row.dataset.deletedFromPn === '1') {
                return;
            }

            const itemIndex = Number.parseInt(row.dataset.itemIndex, 10);
            const sourceItem = Number.isInteger(itemIndex)
                ? window.editState.items[itemIndex]
                : null;
            const qtyInput = row.querySelector('.item-qty');
            const actualQtyInput = row.querySelector('.item-actual-qty');
            const unitCostInput = row.querySelector('.item-unit-cost');
            const unitInput = row.querySelector('.item-unit');
            const discInput = row.querySelector('.item-disc');

            if (qtyInput && sourceItem) {
                items.push({
                    id: sourceItem.id || null,
                    product_id: sourceItem.product_id || sourceItem.productId || null,
                    product_code: sourceItem.product_code || sourceItem.code || '',
                    part_number: sourceItem.part_number || sourceItem.partNo || '',
                    description: sourceItem.description || sourceItem.desc || '',
                    quantity: parseFloat(qtyInput.value) || 0,
                    actual_quantity: parseFloat(actualQtyInput?.value) || 0,
                    unit_price: parseFloat(unitCostInput.value) || 0,
                    unit: String(unitInput?.value ?? sourceItem.unit ?? '').trim(),
                    discount_percent: parseFloat(discInput.value) || 0
                });
            }
        });
        
        // Compute additional discount amount to match backend calculation
        const addlDiscPercent = parseFloat(document.getElementById('edit-additional-discount-percent')?.value) || 0;
        let totalBeforeAddlDisc = 0;
        items.forEach(item => {
            const itemDisc = (item.discount_percent || 0) / 100;
            totalBeforeAddlDisc += item.quantity * item.unit_price * (1 - itemDisc);
        });
        const addlDiscAmount = totalBeforeAddlDisc * (addlDiscPercent / 100);

        // Prepare payload
        const payload = {
            po_id: window.editState.poId,
            receiving_no: document.getElementById('edit-control-no')?.value || '',
            currency: document.getElementById('edit-currency')?.value || 'PHP',
            conversion_rate: parseFloat(document.getElementById('edit-conv-rate')?.value) || 1,
            additional_discount_percent: addlDiscPercent,
            additional_discount_amount: addlDiscAmount,
            invoice_numbers: invoiceNumbers,
            items: items
        };
        
        // Send to backend
        const response = await fetch(window.purchaseRoutes?.updatePO || '/hatdog/public/admin/purchase/purchase-order/update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify(payload)
        });
        
        const result = await response.json();
        
        if (!result.success) throw new Error(result.message || 'Failed to update purchase order');

        // open_edit_po is a one-time deep-link. Clear it immediately after a
        // successful final save so Continue & Reload cannot reopen the edit modal.
        clearPurchaseOrderOneTimeQueryParams();
        
        // Close edit modal
        toggleModal('edit-modal', false);
        
        // Show success message
        document.getElementById('success-msg').textContent = 'Purchase Order has been successfully updated!';
        toggleModal('success-modal', true);
        
        // Refresh history table
        fetchHistoryOrders();
        
        // Clear the one-time Purchase Note change markers after ledger finalization.
        sessionStorage.removeItem('po_edit_changes');

        // Clear state
        window.editState = null;
        
    } catch (error) {
        console.error('Error saving edited PO:', error);
        alert('Failed to save changes: ' + error.message);
    }
};
