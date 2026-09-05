const pageSize = 50;
const statusMap = { entry: 'Entry', to_shipped: 'To Shipped', arrived: 'Arrived', not_arrived: 'Not Arrived', partial: 'Partial' };
const statusKeys = ['entry', 'to_shipped', 'arrived', 'not_arrived'];

const CURRENCY_SYMBOLS = { PHP: '₱', TWD: 'NT$', USD: '$' };

function hasUsableRoute(route) {
    return typeof route === 'string' && route.trim() !== '' && route.trim() !== '#';
}

function formatCurrencyValue(value, currencyCode = 'PHP') {
    const symbol = CURRENCY_SYMBOLS[currencyCode] || '₱';
    const amount = Number(value || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
    return `${symbol} ${amount}`;
}

function isRollbackItem(item) {
    return item?.is_rollback === true || Number(item?.is_rollback || 0) === 1 || String(item?.is_rollback || '').toLowerCase() === 'true';
}

const VIBER_READ_ONLY = window.viberReadOnly === true;

function supplierVisualSeed(supplier) {
    const raw = String(supplier?.supplier_id || supplier?.supplier_code || supplier?.supplier_name || supplier?.id || 'supplier');
    let hash = 0;
    for (let i = 0; i < raw.length; i++) hash = ((hash << 5) - hash + raw.charCodeAt(i)) | 0;
    return Math.abs(hash);
}

function getSupplierIconStyle(supplier) {
    // Stable varied backgrounds: visually random, but consistent for each supplier.
    const hue = supplierVisualSeed(supplier) % 360;
    return `background-color:hsl(${hue} 68% 42%);color:#fff;`;
}

function getSharedSupplierTabStyle(supplier, isActive = false) {
    // Active supplier always wins: yellow background + dark maroon text.
    if (isActive) {
        return 'background-color:#fde047;color:#4a0712;border-color:#eab308;';
    }

    // Supplier tabs are dark maroon by default. Dark blue is click-driven only:
    // after the user clicks a shared item, suppliers containing that same item are highlighted.
    const highlighted = state.sharedSupplierHighlightIds instanceof Set
        && state.sharedSupplierHighlightIds.has(Number(supplier?.id));

    return highlighted
        ? 'background-color:#172554;color:#fde047;border-color:#1e3a8a;'
        : 'background-color:#4a0712;color:#fde047;border-color:#7f1d1d;';
}

function getSupplierCountStyle(supplier, isActive = false) {
    if (isActive) {
        return 'color:#4a0712;background:rgba(74,7,18,.14);';
    }

    return 'color:#fde047;background:rgba(253,224,71,.14);';
}

function ensureViberColorStyles() {
    if (document.getElementById('viber-shared-item-color-rules')) return;
    const style = document.createElement('style');
    style.id = 'viber-shared-item-color-rules';
    style.textContent = `
        /* Shared item: dark blue + yellow, but only when JS assigns this class. */
        .viber-row-shared > td { background:#172554 !important; color:#fde047 !important; }
        .viber-row-shared > td * { color:#fde047 !important; }
        .viber-row-shared input, .viber-row-shared textarea { background:#172554 !important; color:#fde047 !important; border-color:rgba(253,224,71,.35) !important; }

        /* Rollback has the highest Entry-row visual priority. */
        .viber-row-rollback > td { background:#fde047 !important; color:#4a0712 !important; }
        .viber-row-rollback > td * { color:#4a0712 !important; }
        .viber-row-rollback input, .viber-row-rollback textarea { background:#fde047 !important; color:#4a0712 !important; border-color:#4a0712 !important; }

        /* Inline auto-save feedback: no manual Save button is needed. */
        .inline-edit-input.viber-autosave-pending { border-color:#f59e0b !important; background:#fffbeb !important; }
        .inline-edit-input.viber-autosave-saved { border-color:#10b981 !important; background:#ecfdf5 !important; }
        .inline-edit-input.viber-autosave-error { border-color:#ef4444 !important; background:#fef2f2 !important; }

        /* Purchase Entry transfer selection: violet background + yellow text. */
        .viber-row-transfer-selected > td { background:#6d28d9 !important; color:#fde047 !important; }
        .viber-row-transfer-selected > td * { color:#fde047 !important; }
        .viber-row-transfer-selected input, .viber-row-transfer-selected textarea {
            background:#6d28d9 !important;
            color:#fde047 !important;
            border-color:rgba(253,224,71,.55) !important;
        }
    `;
    document.head.appendChild(style);
}

ensureViberColorStyles();

function autoResizeViberRemarks(textarea) {
    if (!textarea) return;
    textarea.style.height = 'auto';
    textarea.style.height = `${Math.max(28, textarea.scrollHeight)}px`;
}

function resizeViberRemarkTextareas(scope = document) {
    if (!scope || typeof scope.querySelectorAll !== 'function') return;
    scope.querySelectorAll('textarea[data-viber-autoresize="remarks"]').forEach(autoResizeViberRemarks);
}

window.autoResizeViberRemarks = autoResizeViberRemarks;

function escapePrintValue(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    }[char]));
}

function formatPrintCurrencyValue(value, currencyCode = 'PHP') {
    const symbols = { PHP: '\u20b1', TWD: 'NT$', USD: '$' };
    const symbol = symbols[currencyCode] || symbols.PHP;
    const amount = Number(value || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
    return `${symbol} ${amount}`;
}

const state = {
    activeDashboard: 'entry',
    activeViberListId: null,
    viberLists: { entry: [], to_shipped: [], arrived: [], not_arrived: [], partial: [] },
    allViberLists: [],
    searchQueries: {},
    columnFilters: {},
    pages: {},
    isLoaded: { entry: false, to_shipped: false, arrived: false, not_arrived: false, partial: false },
    sharedSupplierHighlightIds: new Set(),
    selectedSharedItemKey: null,
    selectedEntryItemIds: new Set()
};

function getStatus() { return statusMap[state.activeDashboard]; }

function getCurrentLists() { return state.viberLists[state.activeDashboard] || []; }

function getActiveList() {
    return getCurrentLists().find(l => l.id === state.activeViberListId) || null;
}

function getSearch() { return state.searchQueries[state.activeViberListId] || ''; }
function setSearch(val) { state.searchQueries[state.activeViberListId] = val; }
function getColFilter(col) {
    if (!state.columnFilters[state.activeViberListId]) state.columnFilters[state.activeViberListId] = {};
    return state.columnFilters[state.activeViberListId][col] || '';
}
function setColFilter(col, val) {
    if (!state.columnFilters[state.activeViberListId]) state.columnFilters[state.activeViberListId] = {};
    state.columnFilters[state.activeViberListId][col] = val;
}
function getPage() { return state.pages[state.activeViberListId] || 1; }
function setPage(p) { state.pages[state.activeViberListId] = p; }

function getFilteredEntryItems(items) {
    let data = Array.isArray(items) ? [...items] : [];
    const search = getSearch();
    if (search) {
        const q = search.toLowerCase();
        data = data.filter(e =>
            (e.item_code && e.item_code.toLowerCase().includes(q)) ||
            (e.part_no && e.part_no.toLowerCase().includes(q)) ||
            (e.description && e.description.toLowerCase().includes(q)) ||
            (e.remarks && e.remarks.toLowerCase().includes(q))
        );
    }

    const colFilters = state.columnFilters[state.activeViberListId] || {};
    Object.keys(colFilters).forEach(key => {
        const val = (colFilters[key] || '').trim().toLowerCase();
        if (val) {
            data = data.filter(e => {
                const fieldValue = key === 'unit' ? (e.unit || e.oum_unit || '') : (e[key] || '');
                return String(fieldValue).toLowerCase().includes(val);
            });
        }
    });

    return data;
}

// ── Fetch Viber Lists ──
async function fetchViberLists(status) {
    try {
        const resp = await fetch(window.viberRoutes.list + '?status=' + encodeURIComponent(status));
        const json = await resp.json();
        if (json.success) {
            state.viberLists[state.activeDashboard] = json.data;
            state.isLoaded[state.activeDashboard] = true;
            // Keep active tab if it still exists
            const activeStillExists = json.data.some(l => l.id === state.activeViberListId);
            if (!activeStillExists) state.activeViberListId = json.data.length > 0 ? json.data[0].id : null;
            renderAll();
        }
    } catch (e) {
        console.error('Failed to fetch Viber lists:', e);
    }
}

async function fetchAllViberLists() {
    try {
        const resp = await fetch(window.viberRoutes.list + '?status=all');
        const json = await resp.json();
        if (json.success) {
            state.allViberLists = json.data;
            renderSuppliersTable();
        }
    } catch (e) {
        console.error('Failed to fetch all Viber lists:', e);
    }
}

// ── Dashboard Counts (backend-based) ──
async function fetchDashboardCounts() {
    try {
        const resp = await fetch(window.viberRoutes.counts);
        const json = await resp.json();
        if (json.success) {
            statusKeys.forEach(key => {
                const el = document.querySelector(`.viber-dashboard-card[data-tab="${key}"] .viber-card-count`);
                if (el) el.textContent = json.data[key] ?? 0;
            });
        }
    } catch (e) {
        console.error('Failed to fetch dashboard counts:', e);
    }
}

// ── Fetch Items ──
async function fetchItems(viberListId, shipped) {
    try {
        let url = window.viberRoutes.items.replace(':viberListId', viberListId);
        if (shipped !== undefined) {
            url += (url.includes('?') ? '&' : '?') + 'shipped=' + shipped;
        }
        const resp = await fetch(url);
        const json = await resp.json();
        if (json.success) {
            return json.data;
        }
        return [];
    } catch (e) {
        console.error('Failed to fetch items:', e);
        return [];
    }
}


function sameViberItem(item, productId, itemCode) {
    const rowProductId = String(productId || '').trim();
    const candidateProductId = String(item?.product_id || '').trim();
    if (rowProductId && candidateProductId && rowProductId === candidateProductId) return true;

    const rowCode = String(itemCode || '').trim().toLowerCase();
    const candidateCode = String(item?.item_code || '').trim().toLowerCase();
    return !!rowCode && !!candidateCode && rowCode === candidateCode;
}

window.highlightSharedItemSuppliers = async function(row) {
    if (!row) return;

    const productId = String(row.dataset.productId || '').trim();
    const itemCode = String(row.dataset.itemCode || '').trim();
    if (!productId && !itemCode) return;

    const itemKey = productId ? `product:${productId}` : `code:${itemCode.toLowerCase()}`;
    const lists = getCurrentLists();
    const matchingSupplierIds = new Set();

    await Promise.all(lists.map(async supplier => {
        let supplierItems = supplier._cachedItems;
        if (!Array.isArray(supplierItems)) {
            supplierItems = await fetchItems(supplier.id);
            supplier._cachedItems = supplierItems;
        }

        if (supplierItems.some(item => sameViberItem(item, productId, itemCode))) {
            matchingSupplierIds.add(Number(supplier.id));
        }
    }));

    state.selectedSharedItemKey = itemKey;
    state.sharedSupplierHighlightIds = matchingSupplierIds;
    renderSupplierTabs();
};

// ── Dashboard Card Switching ──
window.switchViberTab = function(tab) {
    if (state.activeDashboard === tab) return;
    state.activeDashboard = tab;
    state.activeViberListId = null;
    state.sharedSupplierHighlightIds = new Set();
    state.selectedSharedItemKey = null;
    state.selectedEntryItemIds = new Set();
    resetToShippedState();
    document.querySelectorAll('.viber-dashboard-card').forEach(c => {
        const isActive = c.dataset.tab === tab;
        if (isActive) {
            c.className = `viber-dashboard-card flex flex-col items-center justify-center p-4 rounded-xl border-2 cursor-pointer transition-all bg-yellow-400 border-yellow-500 ring-2 ring-yellow-400/50 shadow-lg shadow-yellow-400/10`;
            const icon = c.querySelector('.viber-card-icon');
            const count = c.querySelector('.viber-card-count');
            const label = c.querySelector('.viber-card-label');
            if (icon) icon.className = `viber-card-icon w-5 h-5 mb-1 text-maroon`;
            if (count) count.className = `viber-card-count text-2xl font-extrabold text-maroon`;
            if (label) label.className = `viber-card-label text-xs font-bold uppercase tracking-wider mt-0.5 text-maroon-800`;
        } else {
            c.className = `viber-dashboard-card flex flex-col items-center justify-center p-4 rounded-xl border-2 cursor-pointer transition-all bg-maroon-950 border-maroon-800/50`;
            const icon = c.querySelector('.viber-card-icon');
            const count = c.querySelector('.viber-card-count');
            const label = c.querySelector('.viber-card-label');
            if (icon) icon.className = `viber-card-icon w-5 h-5 mb-1 text-yellow-400`;
            if (count) count.className = `viber-card-count text-2xl font-extrabold text-yellow-400`;
            if (label) label.className = `viber-card-label text-xs font-bold uppercase tracking-wider mt-0.5 text-white/80`;
        }
    });
    if (tab === 'arrived') {
        fetchArrivedInvoicedData();
        return;
    }
    if (tab === 'not_arrived') {
        fetchNotArrivedData();
        return;
    }
    if (!state.isLoaded[tab]) {
        fetchViberLists(getStatus());
        fetchAllViberLists();
    } else {
        // Auto-select first supplier when switching to Entry
        if (tab === 'entry') {
            const lists = state.viberLists['entry'] || [];
            const stillExists = lists.some(l => l.id === state.activeViberListId);
            if (!stillExists && lists.length > 0) {
                state.activeViberListId = lists[0].id;
            }
        }
        renderAll();
    }
};

// ── To Shipped Mode State ──
let toShippedMode = 'suppliers'; // 'suppliers' | 'items'
let toShippedSelectedListId = null;
let toShippedSelectedItemIds = new Set();
let toShippedItemsCache = [];
let toShippedReadyItems = [];
let toShippedShippedItems = [];
let toShippedTab = 'ready'; // 'ready' | 'shipped'
let toShippedCurrency = 'PHP';
let toShippedColumnFilters = {};

function resetToShippedState() {
    toShippedMode = 'suppliers';
    toShippedSelectedListId = null;
    toShippedSelectedItemIds = new Set();
    toShippedItemsCache = [];
    toShippedReadyItems = [];
    toShippedShippedItems = [];
    toShippedTab = 'ready';
    toShippedCurrency = 'PHP';
    toShippedColumnFilters = {};
}

function renderToShippedView() {
    const area = document.getElementById('viber-to-shipped-area');
    if (!area) return;
    if (state.activeDashboard === 'entry' || state.activeDashboard === 'arrived' || state.activeDashboard === 'not_arrived') return;
    area.classList.remove('hidden');
    if (toShippedMode === 'suppliers') {
        document.getElementById('viber-suppliers-area').classList.remove('hidden');
        document.getElementById('viber-to-shipped-items-area').classList.add('hidden');
        renderSuppliersTable();
    } else {
        document.getElementById('viber-suppliers-area').classList.add('hidden');
        document.getElementById('viber-to-shipped-items-area').classList.remove('hidden');
        renderToShippedItems();
    }
}

function renderSuppliersTable() {
    const tbody = document.getElementById('viber-suppliers-tbody');
    if (!tbody) return;
    const lists = state.allViberLists;
    if (lists.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="py-8 text-center text-sm text-slate-400">No suppliers found.</td></tr>';
        return;
    }
    let html = '';
    lists.forEach(l => {
        const arrived = l.arrived_count || 0;
        const partial = l.partial_count || 0;
        const total = l.item_count || 0;
        html += `<tr class="hover:bg-slate-50/50 transition-colors">
            <td class="py-3 px-4 font-medium text-slate-700">${l.supplier_code || '—'}</td>
            <td class="py-3 px-4 text-slate-600">${l.supplier_name || '—'}</td>
            <td class="py-3 px-4 text-center font-semibold text-slate-700">${total}</td>
            <td class="py-3 px-4 text-center text-emerald-600 font-medium">${arrived}</td>
            <td class="py-3 px-4 text-center text-amber-600 font-medium">${partial}</td>
            <td class="py-3 px-4 text-center">
                <div class="inline-flex items-center gap-1.5">
                    <button onclick="selectSupplierItems(${l.id})" class="px-3 py-1.5 bg-maroon text-white text-[10px] font-bold rounded-lg hover:bg-maroon-800 transition-all shadow-sm">Select</button>
                    ${hasUsableRoute(window.viberRoutes.shippedPrintItems) ? `<button onclick="event.stopPropagation();printToShippedSupplierItems(${l.id})" class="px-3 py-1.5 bg-white border border-maroon text-maroon text-[10px] font-bold rounded-lg hover:bg-maroon-50 transition-all">Print</button>` : ''}
                </div>
            </td>
        </tr>`;
    });
    tbody.innerHTML = html;
}

window.selectSupplierItems = async function(viberListId) {
    const list = state.allViberLists.find(l => l.id === viberListId);
    if (!list) return;
    if (state.activeDashboard === 'entry') {
        state.activeViberListId = viberListId;
        setPage(1);
        renderAll();
        return;
    }
    toShippedMode = 'items';
    toShippedSelectedListId = viberListId;
    toShippedSelectedItemIds = new Set();
    toShippedTab = 'ready';
    toShippedCurrency = 'PHP';
    toShippedColumnFilters = {};
    document.querySelectorAll('.viber-ready-col-filter').forEach(input => { input.value = ''; });
    const currencySelect = document.getElementById('viber-currency-select');
    if (currencySelect) currencySelect.value = 'PHP';
    // Fetch ready items (purchase_note_id IS NULL) and shipped items separately via backend
    toShippedReadyItems = await fetchItems(viberListId, 0);
    toShippedShippedItems = await fetchItems(viberListId, 1);
    toShippedItemsCache = [...toShippedReadyItems, ...toShippedShippedItems];
    renderToShippedView();
    updateAddToNoteBtn();
};

function renderToShippedItems() {
    const tbody = document.getElementById('viber-to-shipped-items-tbody');
    if (!tbody) return;
    const list = state.allViberLists.find(l => l.id === toShippedSelectedListId);
    const heading = document.querySelector('#viber-to-shipped-items-area h3');
    if (heading && list) {
        heading.textContent = `Selected Items — ${list.supplier_code} (${list.supplier_name})`;
    }

    // Update tab button styles
    const readyBtn = document.getElementById('viber-ready-tab-btn');
    const shippedBtn = document.getElementById('viber-shipped-tab-btn');
    const addBtnArea = document.getElementById('viber-ready-btn-area');
    if (readyBtn) {
        readyBtn.className = `px-4 py-1.5 text-xs font-bold rounded-lg transition-all ${toShippedTab === 'ready' ? 'bg-maroon text-white shadow-sm' : 'text-slate-500 hover:text-slate-700'}`;
    }
    if (shippedBtn) {
        shippedBtn.className = `px-4 py-1.5 text-xs font-bold rounded-lg transition-all ${toShippedTab === 'shipped' ? 'bg-maroon text-white shadow-sm' : 'text-slate-500 hover:text-slate-700'}`;
    }
    if (addBtnArea) {
        addBtnArea.classList.toggle('hidden', toShippedTab !== 'ready');
    }
    const readyFilterRow = document.getElementById('viber-ready-column-filters');
    if (readyFilterRow) {
        readyFilterRow.classList.toggle('hidden', toShippedTab !== 'ready');
    }

    let items = toShippedTab === 'ready' ? [...toShippedReadyItems] : [...toShippedShippedItems];
    if (toShippedTab === 'ready') {
        Object.entries(toShippedColumnFilters).forEach(([column, query]) => {
            const q = String(query || '').trim().toLowerCase();
            if (!q) return;
            items = items.filter(item => {
                const value = column === 'unit'
                    ? (item.unit || item.oum_unit || '')
                    : (item[column] ?? '');
                return String(value).toLowerCase().includes(q);
            });
        });
    }

    if (items.length === 0) {
        const hasFilters = toShippedTab === 'ready' && Object.values(toShippedColumnFilters).some(value => String(value || '').trim() !== '');
        const msg = toShippedTab === 'ready'
            ? (hasFilters ? 'No ready-to-ship items match your search.' : 'No items ready to ship.')
            : 'No shipped items yet.';
        tbody.innerHTML = `<tr><td colspan="9" class="py-8 text-center text-sm text-slate-400">${msg}</td></tr>`;
        return;
    }
    let html = '';
    items.forEach(item => {
        const isClickable = toShippedTab === 'ready';
        const isSelected = toShippedSelectedItemIds.has(item.id);
        const rollbackClass = isRollbackItem(item) ? 'viber-row-rollback' : '';
        const sharedClass = (!isRollbackItem(item) && !isSelected && item.is_shared_item) ? 'viber-row-shared' : '';
        const rowClass = isClickable ? 'cursor-pointer' : '';
        const clickHandler = isClickable ? `toggleToShippedItem(${item.id})` : '';
        const bgClass = isClickable && isSelected ? 'bg-yellow-100 hover:bg-yellow-200' : (isClickable ? 'hover:bg-slate-50' : '');
        const textClass = isClickable && isSelected ? 'text-maroon' : 'text-slate-600';
        html += `<tr onclick="${clickHandler}" class="transition-colors ${rowClass} ${bgClass} ${rollbackClass} ${sharedClass}">
            <td class="py-3 px-4"><span class="font-medium ${isClickable && isSelected ? 'text-maroon' : 'text-slate-700'}">${item.item_code || '—'}</span></td>
            <td class="py-3 px-4 ${textClass}">${item.part_no || '—'}</td>
            <td class="py-3 px-4 ${textClass}">${item.description || '—'}</td>
            <td class="py-3 px-4 ${textClass}">${item.application || '—'}</td>
            <td class="py-3 px-4 text-right ${textClass}">${item.last_cost != null ? formatCurrencyValue(item.last_cost, toShippedTab === 'ready' ? toShippedCurrency : item.currency_code) : '—'}</td>
            <td class="py-3 px-4 text-right ${isClickable && isSelected ? 'text-maroon' : 'text-slate-600'}">${item.new_cost != null ? formatCurrencyValue(item.new_cost, toShippedTab === 'ready' ? toShippedCurrency : item.currency_code) : '—'}</td>
            <td class="py-3 px-4 text-center ${isClickable && isSelected ? 'text-maroon font-semibold' : 'text-slate-600'}">${item.order_qty != null ? parseFloat(item.order_qty) : '—'}</td>
            <td class="py-3 px-4 ${textClass}">${item.oum_unit || '—'}</td>
            <td class="py-3 px-4 ${textClass}">
                ${toShippedTab === 'ready'
                    ? `<input type="date" value="${item.ordered_date || ''}" data-field="ordered_date" data-item-id="${item.id}" onclick="event.stopPropagation()" oninput="event.stopPropagation()" onchange="event.stopPropagation(); onInlineFieldChange(this, ${item.id}, true)" class="inline-edit-input w-32 px-2 py-1.5 text-xs text-slate-700 bg-white border border-slate-200 hover:border-slate-300 focus:border-maroon/40 focus:ring-1 focus:ring-maroon/20 rounded-lg outline-none transition-all">`
                    : (item.ordered_date || '—')}
            </td>
        </tr>`;
    });
    tbody.innerHTML = html;
}

window.filterToShippedColumn = function(input, column) {
    toShippedColumnFilters[column] = input.value;
    renderToShippedItems();
};

window.toggleToShippedItem = function(itemId) {
    if (toShippedSelectedItemIds.has(itemId)) {
        toShippedSelectedItemIds.delete(itemId);
    } else {
        toShippedSelectedItemIds.add(itemId);
    }
    renderToShippedItems();
    updateAddToNoteBtn();
};

function updateAddToNoteBtn() {
    const btn = document.getElementById('add-to-note-btn');
    if (!btn) return;
    const count = toShippedSelectedItemIds.size;
    btn.disabled = count === 0;
    btn.className = `px-4 py-2 text-xs font-bold rounded-xl transition-all ${count === 0 ? 'bg-maroon text-white opacity-50 cursor-not-allowed' : 'bg-maroon text-white hover:bg-maroon-800 shadow-sm cursor-pointer'}`;
    btn.textContent = count === 0 ? 'Add to Note' : `Add to Note (${count})`;
}

window.backToSuppliers = function() {
    toShippedMode = 'suppliers';
    toShippedSelectedListId = null;
    toShippedSelectedItemIds = new Set();
    toShippedItemsCache = [];
    toShippedReadyItems = [];
    toShippedShippedItems = [];
    toShippedTab = 'ready';
    toShippedColumnFilters = {};
    document.querySelectorAll('.viber-ready-col-filter').forEach(input => { input.value = ''; });
    renderToShippedView();
};

window.switchToShippedTab = function(tab) {
    if (toShippedTab === tab) return;
    toShippedTab = tab;
    renderToShippedItems();
};

window.onCurrencyChange = function(select) {
    toShippedCurrency = select.value;
    renderToShippedItems();
};

window.addToNote = async function() {
    const btn = document.getElementById('add-to-note-btn');
    if (!btn || btn.disabled) return;
    if (!hasUsableRoute(window.viberRoutes.prepareNote)) {
        showToast('error', 'Preparing purchase notes is not available for this account.');
        return;
    }
    if (!toShippedSelectedListId || toShippedSelectedItemIds.size === 0) return;
    btn.disabled = true;
    btn.textContent = 'Preparing...';
    try {
        const resp = await fetch(window.viberRoutes.prepareNote, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                viber_list_id: toShippedSelectedListId,
                selected_item_ids: Array.from(toShippedSelectedItemIds),
                currency: toShippedCurrency,
            })
        });
        const json = await resp.json();
        if (json.success) {
            window.location.href = json.redirect_url || (window.viberRoutes.purchaseNote + '?from_viber=1');
        } else {
            alert(json.message || 'Failed to prepare note data.');
            btn.disabled = false;
            updateAddToNoteBtn();
        }
    } catch (e) {
        console.error('addToNote error:', e);
        alert('An error occurred. Please try again.');
        btn.disabled = false;
        updateAddToNoteBtn();
    }
};

// ── Delete State ──
let supplierToDeleteId = null;
let itemToDeleteId = null;
let isDeleting = false;

// ── Supplier Tabs ──
function renderSupplierTabs() {
    const container = document.getElementById('supplier-tabs-container');
    const wrapper = document.getElementById('supplier-tabs-wrapper');
    if (!container) return;

    // Hide supplier tabs on non-Entry dashboards (suppliers shown in table)
    if (state.activeDashboard !== 'entry') {
        wrapper.style.display = 'none';
        return;
    }

    const lists = getCurrentLists();

    if (lists.length === 0) {
        wrapper.style.display = 'none';
        return;
    }
    wrapper.style.display = 'flex';

    let html = '';
    lists.forEach(l => {
        const isActive = l.id === state.activeViberListId;
        html += `<div class="supplier-tab-wrapper inline-flex items-center gap-0 ${isActive ? '' : 'group'}">
            <button onclick="window.selectSupplierTab(${l.id})" style="${getSharedSupplierTabStyle(l, isActive)}" class="supplier-tab inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-l-lg border transition-all ${isActive ? 'bg-yellow-400 text-maroon border-yellow-500 shadow-sm' : 'bg-white text-slate-600 border-slate-200 hover:border-maroon/30 hover:text-maroon'}">
                <span style="${getSupplierIconStyle(l)}" class="w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0">
                    <i data-lucide="building-2" class="w-3 h-3 text-white"></i>
                </span>
                <span class="truncate max-w-[100px]">${l.supplier_code}</span>
                <span class="opacity-40 mx-0.5">—</span>
                <span class="truncate max-w-[140px]">${l.supplier_name}</span>
                <span style="${getSupplierCountStyle(l, isActive)}" class="ml-1 px-1.5 py-0.5 rounded-full text-[9px] font-bold ${isActive ? 'bg-maroon/20 text-maroon' : 'bg-slate-100 text-slate-500'}">${l.item_count}</span>
            </button>
            <button type="button" onclick="event.stopPropagation(); openDeleteSupplierModal(${l.id})" class="inline-flex items-center justify-center self-stretch px-2 rounded-r-lg border border-l-0 transition-all ${isActive ? 'bg-yellow-400 text-maroon border-yellow-500 hover:bg-red-50 hover:text-red-600' : 'bg-white text-slate-400 border-slate-200 hover:bg-red-50 hover:text-red-600 hover:border-red-200'}" title="Delete supplier tab">
                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
            </button>
        </div>`;
    });
    container.innerHTML = html;
    if (typeof lucide !== 'undefined') setTimeout(() => lucide.createIcons(), 0);
}

window.selectSupplierTab = async function(viberListId) {
    if (state.activeViberListId === viberListId) return;
    state.activeViberListId = viberListId;
    state.selectedEntryItemIds = new Set();
    setPage(1);
    renderAll();
};

// ── Main View ──
function renderMainView() {
    const toShippedArea = document.getElementById('viber-to-shipped-area');
    const mainCard = document.getElementById('viber-main-table-card');
    const emptyNoSupplier = document.getElementById('viber-empty-no-supplier');
    const emptyNoSelection = document.getElementById('viber-empty-no-selection');
    const emptyNoItems = document.getElementById('viber-empty-no-items');
    const tableArea = document.getElementById('viber-table-area');
    [emptyNoSupplier, emptyNoSelection, emptyNoItems, tableArea].forEach(el => el.classList.add('hidden'));
    if (toShippedArea) toShippedArea.classList.add('hidden');
    if (mainCard) mainCard.classList.remove('hidden');

    const lists = getCurrentLists();

    if (state.activeDashboard !== 'entry') {
        if (state.activeDashboard === 'arrived' || state.activeDashboard === 'not_arrived') {
            if (mainCard) mainCard.classList.add('hidden');
            if (toShippedArea) toShippedArea.classList.add('hidden');
            return;
        }
        // Non-Entry dashboards: hide main card, show suppliers area
        if (mainCard) mainCard.classList.add('hidden');
        toShippedArea.classList.remove('hidden');
        return;
    }

    if (lists.length === 0) { emptyNoSupplier.classList.remove('hidden'); return; }
    if (!state.activeViberListId) { emptyNoSelection.classList.remove('hidden'); return; }
    const active = getActiveList();
    if (active && active.item_count === 0) { emptyNoItems.classList.remove('hidden'); return; }
    tableArea.classList.remove('hidden');
}

// ── Table Rendering ──
async function renderTable() {
    const tbody = document.getElementById('viber-table-body');
    const active = getActiveList();
    if (!tbody || !active) return;

    const items = await fetchItems(active.id);
    // Cache items on the list object for filter/search
    active._cachedItems = items;
    if (!(state.selectedEntryItemIds instanceof Set)) state.selectedEntryItemIds = new Set();
    const transferableEntryIds = new Set(
        items.filter(item => item.purchase_note_id == null).map(item => Number(item.id))
    );
    let transferSelectionChanged = false;
    Array.from(state.selectedEntryItemIds).forEach(id => {
        if (!transferableEntryIds.has(Number(id))) {
            state.selectedEntryItemIds.delete(id);
            transferSelectionChanged = true;
        }
    });
    if (transferSelectionChanged) updateTransferButtonState();

    // Auto-detect duplicates for persistent blink highlighting
    const keyCount = new Map();
    items.forEach(item => {
        const key = String(item.product_id || item.item_code || '');
        if (key) keyCount.set(key, (keyCount.get(key) || 0) + 1);
    });
    _duplicateHighlightKeys = new Set();
    keyCount.forEach((count, key) => {
        if (count > 1) _duplicateHighlightKeys.add(key);
    });

    let data = getFilteredEntryItems(items);

    const totalPages = Math.ceil(data.length / pageSize) || 1;
    let currentPage = getPage();
    if (currentPage > totalPages) { currentPage = totalPages; setPage(currentPage); }
    const start = (currentPage - 1) * pageSize;
    const pageData = data.slice(start, start + pageSize);

    if (pageData.length === 0) {
        const emptyNoItems = document.getElementById('viber-empty-no-items');
        const tableArea = document.getElementById('viber-table-area');
        if (data.length === 0 && items.length === 0) {
            tableArea.classList.add('hidden');
            emptyNoItems.classList.remove('hidden');
        } else {
        tbody.innerHTML = `<tr><td colspan="11" class="py-16 text-center text-slate-400">
            <i data-lucide="package-open" class="w-10 h-10 mx-auto mb-3 opacity-50"></i>
            <p class="text-sm font-medium">No items match your search.</p>
            <p class="text-xs mt-1">Try adjusting your search or filters.</p>
        </td></tr>`;
            if (typeof lucide !== 'undefined') setTimeout(() => lucide.createIcons(), 0);
        }
        updatePagination(0, 0, 0);
        return;
    }

    let html = '';
    pageData.forEach(e => {
        const dupKey = e.product_id || e.item_code || '';
        const isDup = _duplicateHighlightKeys.has(String(dupKey));
        const hasPn = e.purchase_note_id != null;
        const isRollback = isRollbackItem(e);
        let rowClass = '';
        // Preserve existing semantic colors first. Shared blue is only the fallback.
        if (isRollback) rowClass = 'viber-row-rollback';
        else if (isDup && hasPn) rowClass = 'viber-row-duplicate-has-pn';
        else if (isDup) rowClass = 'viber-row-duplicate';
        else if (hasPn) rowClass = 'viber-row-has-pn';
        else if (e.is_shared_item) rowClass = 'viber-row-shared';
        const canTransferEntryItem = !hasPn;
        const isTransferSelected = canTransferEntryItem && state.selectedEntryItemIds instanceof Set && state.selectedEntryItemIds.has(Number(e.id));
        const rowClick = canTransferEntryItem
            ? `onclick="window.selectViberEntryItem(this, ${e.id}, ${e.is_shared_item ? 'true' : 'false'})"`
            : (e.is_shared_item ? 'onclick="window.highlightSharedItemSuppliers(this)"' : '');
        const rowCursor = (canTransferEntryItem || e.is_shared_item) ? 'cursor-pointer' : '';
        html += `<tr ${rowClick} data-product-id="${e.product_id || ''}" data-item-code="${escapePrintValue(e.item_code || '')}" data-item-id="${e.id}" class="hover:bg-slate-50 transition-colors border-b border-slate-100 ${rowCursor} ${rowClass} ${isTransferSelected ? 'viber-row-transfer-selected' : ''}">
            <td class="py-3 px-4 text-xs font-mono text-maroon font-semibold">${e.item_code || ''}</td>
            <td class="py-3 px-4 text-xs text-slate-700">${e.part_no || ''}</td>
            <td class="py-3 px-4 text-xs text-slate-700 max-w-[200px] truncate" title="${e.description || ''}">${e.description || ''}</td>
            <td class="py-3 px-4 text-xs text-slate-600">${e.application || ''}</td>
            <td class="py-3 px-4 text-xs text-right text-slate-600">${formatCurrencyValue(e.last_cost, e.currency_code)}</td>
            <td class="py-3 px-4 text-xs text-right font-semibold text-slate-800">
                <input type="number" step="0.01" min="0" value="${parseFloat(e.new_cost || 0).toFixed(2)}" data-field="new_cost" data-item-id="${e.id}" onclick="event.stopPropagation()" oninput="onInlineFieldChange(this, ${e.id})" class="inline-edit-input w-20 px-1.5 py-1 text-xs text-right border border-transparent hover:border-slate-200 focus:border-maroon/40 focus:ring-1 focus:ring-maroon/20 rounded-lg outline-none transition-all">
            </td>
            <td class="py-3 px-4 text-xs text-center font-semibold">
                <input type="number" min="0" value="${e.order_qty || 0}" data-field="order_qty" data-item-id="${e.id}" onclick="event.stopPropagation()" oninput="onInlineFieldChange(this, ${e.id})" class="inline-edit-input w-14 px-1.5 py-1 text-xs text-center border border-transparent hover:border-slate-200 focus:border-maroon/40 focus:ring-1 focus:ring-maroon/20 rounded-lg outline-none transition-all">
            </td>
            <td class="py-3 px-4 text-xs text-slate-600">
                <input type="text" maxlength="50" value="${escapePrintValue(e.unit || e.oum_unit || '')}" data-field="unit" data-item-id="${e.id}" onclick="event.stopPropagation()" oninput="onInlineFieldChange(this, ${e.id})" class="inline-edit-input w-20 px-1.5 py-1 text-xs border border-transparent hover:border-slate-200 focus:border-maroon/40 focus:ring-1 focus:ring-maroon/20 rounded-lg outline-none transition-all" placeholder="—">
            </td>
            <td class="py-3 px-4 text-xs text-slate-600">
                <input type="date" value="${e.ordered_date || ''}" data-field="ordered_date" data-item-id="${e.id}" onclick="event.stopPropagation()" onchange="onInlineFieldChange(this, ${e.id}, true)" class="inline-edit-input w-32 px-1.5 py-1 text-xs border border-transparent hover:border-slate-200 focus:border-maroon/40 focus:ring-1 focus:ring-maroon/20 rounded-lg outline-none transition-all">
            </td>
            <td class="py-3 px-4 text-xs text-slate-500 min-w-[180px] align-top">
                <textarea rows="1" data-viber-autoresize="remarks" data-field="remarks" data-item-id="${e.id}" onclick="event.stopPropagation()" oninput="autoResizeViberRemarks(this); onInlineFieldChange(this, ${e.id})" class="inline-edit-input w-full min-w-[160px] px-1.5 py-1 text-xs leading-5 border border-transparent hover:border-slate-200 focus:border-maroon/40 focus:ring-1 focus:ring-maroon/20 rounded-lg outline-none transition-all resize-none overflow-hidden whitespace-pre-wrap break-words" placeholder="—">${escapePrintValue(e.remarks || '')}</textarea>
            </td>
            <td class="py-3 px-4 text-center whitespace-nowrap">
                <button type="button" onclick="event.stopPropagation(); openDeleteItemModal(${e.id})" class="inline-flex items-center justify-center p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-all" title="Delete item">
                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                </button>
            </td>
        </tr>`;
    });
    tbody.innerHTML = html;
    resizeViberRemarkTextareas(tbody);
    if (typeof lucide !== 'undefined') setTimeout(() => lucide.createIcons(), 0);
    updatePagination(start + 1, start + pageData.length, data.length);
}

function updatePagination(from, to, total) {
    const info = document.getElementById('table-range-info');
    const indicator = document.getElementById('table-page-indicator');
    const prevBtn = document.getElementById('table-prev-btn');
    const nextBtn = document.getElementById('table-next-btn');
    const currentPage = getPage();
    const totalPages = Math.ceil(total / pageSize) || 1;
    if (info) info.textContent = total > 0 ? `Showing ${from}-${to} of ${total}` : 'Showing 0-0 of 0';
    if (indicator) indicator.textContent = `Page ${currentPage} of ${totalPages}`;
    if (prevBtn) {
        prevBtn.disabled = currentPage <= 1;
        prevBtn.className = `px-3 py-1.5 text-xs font-bold rounded-lg border transition-all ${currentPage <= 1 ? 'border-slate-100 text-slate-300 cursor-not-allowed' : 'border-slate-200 text-slate-600 hover:bg-slate-100 cursor-pointer'}`;
    }
    if (nextBtn) {
        nextBtn.disabled = currentPage >= totalPages;
        nextBtn.className = `px-3 py-1.5 text-xs font-bold rounded-lg border transition-all ${currentPage >= totalPages ? 'border-slate-100 text-slate-300 cursor-not-allowed' : 'border-slate-200 text-slate-600 hover:bg-slate-100 cursor-pointer'}`;
    }
}

window.changePage = function(delta) {
    const active = getActiveList();
    if (!active) return;
    let cp = getPage();
    const totalPages = Math.ceil((active._cachedItems || []).length / pageSize) || 1;
    const np = cp + delta;
    if (np < 1 || np > totalPages) return;
    setPage(np);
    renderTable();
};

// ── Global Search ──
window.handleViberSearch = function(input) {
    setSearch(input.value);
    setPage(1);
    renderTable();
};

// ── Column Filter ──
window.filterViberColumn = function(input, column) {
    setColFilter(column, input.value);
    setPage(1);
    renderTable();
};

// ── Purchase Entry Item Transfer ──
function getSelectedEntryItemIds() {
    if (!(state.selectedEntryItemIds instanceof Set)) {
        state.selectedEntryItemIds = new Set();
    }
    return Array.from(state.selectedEntryItemIds)
        .map(id => Number(id))
        .filter(id => Number.isInteger(id) && id > 0);
}

function getTransferRouteTemplate() {
    const configured = window.viberRoutes?.transferItem;
    if (typeof configured === 'string' && configured.trim() !== '' && configured.trim() !== '#') {
        return configured;
    }

    const currentPath = String(window.location.pathname || '').replace(/\/+$/, '');
    if (/\/purchase\/viber-list$/i.test(currentPath)) {
        return `${currentPath}/items/:id/transfer`;
    }
    return '';
}

function ensureTransferUi() {
    if (VIBER_READ_ONLY) return;

    let transferBtn = document.getElementById('viber-transfer-item-btn');
    const addItemsBtn = document.getElementById('viber-add-items-btn');
    if (!transferBtn && addItemsBtn?.parentElement) {
        addItemsBtn.insertAdjacentHTML('afterend', `
            <button type="button" onclick="openTransferItemModal()" id="viber-transfer-item-btn" disabled class="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-100 text-slate-400 border border-slate-200 text-xs font-bold rounded-xl cursor-not-allowed">
                <i data-lucide="arrow-right-left" class="w-4 h-4"></i>
                <span>Transfer</span>
            </button>
        `);
        transferBtn = document.getElementById('viber-transfer-item-btn');
    }

    if (!document.getElementById('transfer-item-modal')) {
        document.body.insertAdjacentHTML('beforeend', `
            <div id="transfer-item-modal" class="fixed inset-0 hidden" style="z-index:1100;">
                <div class="viber-modal-overlay fixed inset-0" onclick="closeTransferItemModal()"></div>
                <div class="fixed inset-0 flex items-center justify-center p-4">
                    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                            <div>
                                <h3 class="text-base font-bold text-slate-800">Transfer Purchase Entry Items</h3>
                                <p id="transfer-item-description" class="text-xs text-slate-500 mt-1">Move the selected item(s) to another existing supplier.</p>
                            </div>
                            <button type="button" onclick="closeTransferItemModal()" class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-all">×</button>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">From</label>
                                <div id="transfer-from-supplier" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-sm font-semibold text-slate-700">—</div>
                            </div>
                            <div>
                                <label for="transfer-to-supplier" class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">To</label>
                                <select id="transfer-to-supplier" onchange="updateTransferConfirmButton()" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon">
                                    <option value="">Select supplier...</option>
                                </select>
                                <p id="transfer-no-supplier-message" class="hidden mt-2 text-xs font-medium text-amber-600">Add another supplier to Purchase Entry first before transferring these items.</p>
                            </div>
                        </div>
                        <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex justify-end gap-3">
                            <button type="button" onclick="closeTransferItemModal()" class="px-5 py-2 bg-white border border-slate-200 text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all">Cancel</button>
                            <button type="button" id="confirm-transfer-item-btn" onclick="confirmTransferItem()" disabled class="px-5 py-2 bg-slate-200 text-slate-400 text-xs font-bold rounded-xl cursor-not-allowed transition-all">Confirm</button>
                        </div>
                    </div>
                </div>
            </div>
        `);
    }

    if (!document.getElementById('transfer-success-modal')) {
        document.body.insertAdjacentHTML('beforeend', `
            <div id="transfer-success-modal" class="fixed inset-0 hidden" style="z-index:1150;">
                <div class="viber-modal-overlay fixed inset-0"></div>
                <div class="fixed inset-0 flex items-center justify-center p-4">
                    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 text-center">
                        <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-emerald-50 flex items-center justify-center">
                            <i data-lucide="check-circle" class="w-7 h-7 text-emerald-500"></i>
                        </div>
                        <h3 class="text-base font-bold text-slate-800">Transfer Successful</h3>
                        <p class="text-sm text-slate-500 mt-2" id="transfer-success-message">The selected item(s) were transferred successfully.</p>
                        <div class="flex items-center justify-center gap-3 mt-6">
                            <button type="button" onclick="window.location.reload()" class="px-5 py-2 bg-emerald-600 text-white text-xs font-bold rounded-xl hover:bg-emerald-700 transition-all shadow-sm">Reload Page</button>
                        </div>
                    </div>
                </div>
            </div>
        `);
    }

    if (typeof lucide !== 'undefined') setTimeout(() => lucide.createIcons(), 0);
}

function updateTransferButtonState() {
    ensureTransferUi();
    const btn = document.getElementById('viber-transfer-item-btn');
    if (!btn) return;
    const active = getActiveList();
    const canShow = state.activeDashboard === 'entry' && !!active && !VIBER_READ_ONLY;
    btn.classList.toggle('hidden', !canShow);
    if (!canShow) return;

    const selectedCount = getSelectedEntryItemIds().length;
    const hasSelection = selectedCount > 0;
    btn.disabled = !hasSelection;
    btn.className = hasSelection
        ? 'inline-flex items-center gap-1.5 px-4 py-2 bg-maroon text-yellow-400 border border-maroon text-xs font-bold rounded-xl hover:bg-maroon-800 transition-all shadow-sm cursor-pointer'
        : 'inline-flex items-center gap-1.5 px-4 py-2 bg-slate-100 text-slate-400 border border-slate-200 text-xs font-bold rounded-xl cursor-not-allowed';
    const label = btn.querySelector('span');
    if (label) label.textContent = hasSelection ? `Transfer (${selectedCount})` : 'Transfer';
}

window.selectViberEntryItem = async function(row, itemId, isSharedItem = false) {
    if (VIBER_READ_ONLY || state.activeDashboard !== 'entry') return;

    const normalizedId = Number(itemId || 0);
    if (!normalizedId) return;
    if (!(state.selectedEntryItemIds instanceof Set)) state.selectedEntryItemIds = new Set();

    if (state.selectedEntryItemIds.has(normalizedId)) {
        state.selectedEntryItemIds.delete(normalizedId);
    } else {
        state.selectedEntryItemIds.add(normalizedId);
    }

    document.querySelectorAll('#viber-table-body tr[data-item-id]').forEach(tr => {
        tr.classList.toggle('viber-row-transfer-selected', state.selectedEntryItemIds.has(Number(tr.dataset.itemId)));
    });
    updateTransferButtonState();

    if (state.selectedEntryItemIds.has(normalizedId) && isSharedItem) {
        await window.highlightSharedItemSuppliers(row);
    }
};

window.openTransferItemModal = function() {
    ensureTransferUi();
    const selectedIds = getSelectedEntryItemIds();
    if (VIBER_READ_ONLY || state.activeDashboard !== 'entry' || selectedIds.length === 0) return;

    const active = getActiveList();
    if (!active) return;
    const selectedIdSet = new Set(selectedIds);
    const selected = (active._cachedItems || []).filter(item => selectedIdSet.has(Number(item.id)) && item.purchase_note_id == null);
    if (selected.length === 0) {
        state.selectedEntryItemIds = new Set();
        updateTransferButtonState();
        return;
    }

    // Keep only still-transferable rows from the active supplier.
    state.selectedEntryItemIds = new Set(selected.map(item => Number(item.id)));

    const modal = document.getElementById('transfer-item-modal');
    const fromEl = document.getElementById('transfer-from-supplier');
    const descEl = document.getElementById('transfer-item-description');
    const select = document.getElementById('transfer-to-supplier');
    const noSupplier = document.getElementById('transfer-no-supplier-message');
    if (!modal || !select) return;

    if (fromEl) fromEl.textContent = `${active.supplier_code || ''} - ${active.supplier_name || ''}`.replace(/^\s*-\s*|\s*-\s*$/g, '');
    if (descEl) {
        if (selected.length === 1) {
            const item = selected[0];
            descEl.textContent = `${item.item_code || ''}${item.description ? ' — ' + item.description : ''}`;
        } else {
            const preview = selected.slice(0, 4).map(item => item.item_code || '').filter(Boolean).join(', ');
            const more = selected.length > 4 ? ` +${selected.length - 4} more` : '';
            descEl.textContent = `${selected.length} items selected${preview ? ': ' + preview + more : ''}`;
        }
    }

    const targets = getCurrentLists().filter(list => Number(list.id) !== Number(active.id));
    select.innerHTML = '<option value="">Select supplier...</option>' + targets.map(list =>
        `<option value="${list.id}">${escapePrintValue(list.supplier_code || '')} - ${escapePrintValue(list.supplier_name || '')}</option>`
    ).join('');
    select.value = '';
    if (noSupplier) noSupplier.classList.toggle('hidden', targets.length > 0);

    modal.classList.remove('hidden');
    window.updateTransferConfirmButton();
    if (typeof lucide !== 'undefined') setTimeout(() => lucide.createIcons(), 0);
};

window.closeTransferItemModal = function() {
    document.getElementById('transfer-item-modal')?.classList.add('hidden');
};

window.updateTransferConfirmButton = function() {
    const select = document.getElementById('transfer-to-supplier');
    const btn = document.getElementById('confirm-transfer-item-btn');
    if (!btn) return;
    const enabled = !!select?.value && getSelectedEntryItemIds().length > 0;
    btn.disabled = !enabled;
    btn.className = enabled
        ? 'px-5 py-2 bg-maroon text-yellow-400 text-xs font-bold rounded-xl hover:bg-maroon-800 transition-all shadow-sm cursor-pointer'
        : 'px-5 py-2 bg-slate-200 text-slate-400 text-xs font-bold rounded-xl cursor-not-allowed transition-all';
};

window.confirmTransferItem = async function() {
    const itemIds = getSelectedEntryItemIds();
    const targetId = Number(document.getElementById('transfer-to-supplier')?.value || 0);
    const btn = document.getElementById('confirm-transfer-item-btn');
    if (itemIds.length === 0 || !targetId || !btn || btn.disabled) return;

    const routeTemplate = getTransferRouteTemplate();
    if (!routeTemplate) {
        alert('Transfer route is not available.');
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Transferring...';

    try {
        const url = routeTemplate.replace(':id', itemIds[0]);
        const resp = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': window.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({
                target_viber_list_id: targetId,
                item_ids: itemIds,
            }),
        });
        const json = await resp.json();
        if (!resp.ok || !json.success) {
            alert(json.message || 'Failed to transfer selected items.');
            btn.textContent = 'Confirm';
            window.updateTransferConfirmButton();
            return;
        }

        window.closeTransferItemModal();
        state.selectedEntryItemIds = new Set();
        updateTransferButtonState();
        const successMessage = document.getElementById('transfer-success-message');
        if (successMessage) {
            const data = json.data || {};
            const count = Number(data.item_count || itemIds.length || 0);
            successMessage.textContent = `${count} item${count === 1 ? '' : 's'} ${count === 1 ? 'was' : 'were'} transferred from ${data.from_supplier || 'the current supplier'} to ${data.to_supplier || 'the selected supplier'}.`;
        }
        document.getElementById('transfer-success-modal')?.classList.remove('hidden');
        if (typeof lucide !== 'undefined') setTimeout(() => lucide.createIcons(), 0);
    } catch (error) {
        console.error('confirmTransferItem error:', error);
        alert('Failed to transfer selected items. Please try again.');
        btn.textContent = 'Confirm';
        window.updateTransferConfirmButton();
    }
};

// ── Supplier Modal ──
window.printToShippedSupplierItems = async function(viberListId) {
    if (state.activeDashboard !== 'to_shipped') return;
    if (!hasUsableRoute(window.viberRoutes.shippedPrintItems)) {
        showToast('error', 'To Shipped print is not available for this account.');
        return;
    }
    const list = state.allViberLists.find(l => l.id === viberListId);
    if (!list) return;

    const printWindow = window.open('', '_blank', 'width=1200,height=800');
    if (!printWindow) {
        alert('Please allow pop-ups to print.');
        return;
    }

    printWindow.document.write('<!doctype html><html><head><title>Preparing Print</title></head><body><p>Loading...</p></body></html>');
    printWindow.document.close();

    try {
        const resp = await fetch(window.viberRoutes.shippedPrintItems.replace(':viberListId', viberListId));
        const json = await resp.json();

        if (!json.success || !json.data) {
            printWindow.document.write('<p>Failed to load items.</p>');
            printWindow.document.close();
            return;
        }

        const { supplier, items } = json.data;
        const printedAt = new Date().toLocaleString();

        const rowsHtml = items.length
            ? items.map(item => `
                <tr>
                    <td>${escapePrintValue(item.item_code)}</td>
                    <td>${escapePrintValue(item.part_no)}</td>
                    <td>${escapePrintValue(item.description)}</td>
                    <td>${escapePrintValue(item.application)}</td>
                    <td class="numeric">${escapePrintValue(formatPrintCurrencyValue(item.last_cost, item.currency_code))}</td>
                    <td class="numeric">${escapePrintValue(formatPrintCurrencyValue(item.new_cost, item.currency_code))}</td>
                    <td class="center">${escapePrintValue(item.order_qty != null ? parseFloat(item.order_qty) : '')}</td>
                    <td>${escapePrintValue(item.oum_unit || '')}</td>
                    <td>${escapePrintValue(item.ordered_date)}</td>
                </tr>
            `).join('')
            : '<tr><td colspan="9" class="center">No shipped items found for this supplier.</td></tr>';

        const printHtml = `<!doctype html>
<html>
<head>
    <title>TO SHIPPED ITEMS</title>
    <style>
        @page { margin: 12mm; }
        * {
            color: #000 !important;
            font-size: 12px !important;
            font-weight: 700 !important;
            box-sizing: border-box;
        }
        body {
            margin: 0;
            background: #fff;
            font-family: Arial, sans-serif;
        }
        h1 {
            margin: 0 0 10px;
            text-align: center;
        }
        .meta {
            margin-bottom: 10px;
            line-height: 1.5;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #000 !important;
            color: #000 !important;
            font-size: 12px !important;
            font-weight: 700 !important;
            padding: 4px;
            vertical-align: top;
            text-align: left;
            word-break: break-word;
            overflow-wrap: anywhere;
        }
        th {
            background: #fff !important;
        }
        .numeric {
            text-align: right;
            white-space: nowrap;
        }
        .center {
            text-align: center;
        }
        .print-table th:nth-child(1),
        .print-table td:nth-child(1) { width: 14%; }
        .print-table th:nth-child(2),
        .print-table td:nth-child(2) { width: 16%; }
        .print-table th:nth-child(3),
        .print-table td:nth-child(3) { width: 16%; }
        .print-table th:nth-child(4),
        .print-table td:nth-child(4) { width: 15%; }
        .print-table th:nth-child(5),
        .print-table td:nth-child(5),
        .print-table th:nth-child(6),
        .print-table td:nth-child(6) { width: 9%; }
        .print-table th:nth-child(7),
        .print-table td:nth-child(7) { width: 6%; }
        .print-table th:nth-child(8),
        .print-table td:nth-child(8) {
            width: 15%;
            white-space: nowrap;
            word-break: normal;
            overflow-wrap: normal;
        }
        .no-print {
            display: none !important;
        }
        @media print {
            * {
                color: #000 !important;
                font-size: 12px !important;
                font-weight: 700 !important;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            th, td {
                border: 1px solid #000 !important;
                color: #000 !important;
                font-size: 12px !important;
                font-weight: 700 !important;
                padding: 4px;
                word-break: break-word;
                overflow-wrap: anywhere;
            }
            .print-table th:nth-child(8),
            .print-table td:nth-child(8) {
                white-space: nowrap;
                word-break: normal;
                overflow-wrap: normal;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <h1>TO SHIPPED ITEMS</h1>
    <div class="meta">
        <div>Supplier Code: ${escapePrintValue(supplier.supplier_code)}</div>
        <div>Supplier Name: ${escapePrintValue(supplier.supplier_name)}</div>
        <div>Date Printed: ${escapePrintValue(printedAt)}</div>
    </div>
    <table class="print-table">
        <thead>
            <tr>
                <th>Item Code</th>
                <th>Part No</th>
                <th>Description</th>
                <th>Application</th>
                <th>Last Cost</th>
                <th>New Cost</th>
                <th>Order QTY</th>
                <th>Unit</th>
                <th>Ordered Date</th>
            </tr>
        </thead>
        <tbody>${rowsHtml}</tbody>
    </table>
</body>
</html>`;

        printWindow.document.open();
        printWindow.document.write(printHtml);
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => printWindow.print(), 250);
    } catch (e) {
        console.error('printToShippedSupplierItems error:', e);
        printWindow.document.write('<p>Error loading items.</p>');
        printWindow.document.close();
    }
};

window.printEntrySupplierItems = async function() {
    if (state.activeDashboard !== 'entry') return;

    const active = getActiveList();
    if (!active) {
        alert('Please select a supplier tab first.');
        return;
    }

    const printWindow = window.open('', '_blank', 'width=1200,height=800');
    if (!printWindow) {
        alert('Please allow pop-ups to print the selected supplier items.');
        return;
    }

    printWindow.document.write('<!doctype html><html><head><title>Preparing Print</title></head><body><p>Preparing print...</p></body></html>');
    printWindow.document.close();

    let items = Array.isArray(active._cachedItems) ? active._cachedItems : null;
    if (!items) {
        items = await fetchItems(active.id);
        active._cachedItems = items;
    }

    const filteredItems = getFilteredEntryItems(items).filter(item => !item.purchase_note_id);
    const printedAt = new Date().toLocaleString();
    const rowsHtml = filteredItems.length
        ? filteredItems.map(item => `
            <tr>
                <td class="item-code-wide">${escapePrintValue(item.item_code)}</td>
                <td>${escapePrintValue(item.part_no)}</td>
                <td class="description-wide">${escapePrintValue(item.description)}</td>
                <td>${escapePrintValue(item.application)}</td>
                <td class="numeric">${escapePrintValue(formatPrintCurrencyValue(item.last_cost, item.currency_code))}</td>
                <td class="numeric">${escapePrintValue(formatPrintCurrencyValue(item.new_cost, item.currency_code))}</td>
                <td class="center">${escapePrintValue(item.order_qty != null ? parseFloat(item.order_qty) : '')}</td>
                <td>${escapePrintValue(item.oum_unit || '')}</td>
                <td class="date-wide">${escapePrintValue(item.ordered_date)}</td>
            </tr>
        `).join('')
        : '<tr><td colspan="9" class="center">No items to print.</td></tr>';

    const printHtml = `<!doctype html>
<html>
<head>
    <title>VIBER LIST ENTRY ITEMS</title>
    <style>
        @page { margin: 12mm; }
        * {
            color: #000 !important;
            font-size: 12px !important;
            font-weight: 700 !important;
            box-sizing: border-box;
        }
        body {
            margin: 0;
            background: #fff;
            font-family: Arial, sans-serif;
        }
        h1 {
            margin: 0 0 10px;
            text-align: center;
        }
        .meta {
            margin-bottom: 10px;
            line-height: 1.5;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #000 !important;
            color: #000 !important;
            font-size: 12px !important;
            font-weight: 700 !important;
            padding: 4px;
            vertical-align: top;
            text-align: left;
        }
        th {
            background: #fff !important;
        }
        .numeric {
            text-align: right;
            white-space: nowrap;
        }
        .center {
            text-align: center;
        }
        .item-code-wide {
            width: 16%;
        }
        .description-wide {
            width: 16%;
        }
        .date-wide {
            width: 10%;
            white-space: nowrap;
        }
        .no-print {
            display: none !important;
        }
        @media print {
            * {
                color: #000 !important;
                font-size: 12px !important;
                font-weight: 700 !important;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            th, td {
                border: 1px solid #000 !important;
                color: #000 !important;
                font-size: 12px !important;
                font-weight: 700 !important;
                padding: 4px;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <h1>VIBER LIST ENTRY ITEMS</h1>
    <div class="meta">
        <div>Supplier Code: ${escapePrintValue(active.supplier_code)}</div>
        <div>Supplier Name: ${escapePrintValue(active.supplier_name)}</div>
        <div>Date Printed: ${escapePrintValue(printedAt)}</div>
    </div>
    <table>
        <thead>
            <tr>
                <th class="item-code-wide">Item Code</th>
                <th>Part No</th>
                <th class="description-wide">Description</th>
                <th>Application</th>
                <th>Last Cost</th>
                <th>New Cost</th>
                <th>Order QTY</th>
                <th>Unit</th>
                <th class="date-wide">Ordered Date</th>
            </tr>
        </thead>
        <tbody>${rowsHtml}</tbody>
    </table>
</body>
</html>`;

    printWindow.document.open();
    printWindow.document.write(printHtml);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => printWindow.print(), 250);
};

let supplierSearchQuery = '';
let pendingSelectedSupplier = null;
let supplierPage = 1;
let supplierTotalPages = 1;

window.openSelectSupplier = function() {
    supplierSearchQuery = '';
    pendingSelectedSupplier = null;
    supplierPage = 1;
    document.getElementById('supplier-search-input').value = '';
    renderSupplierList();
    document.getElementById('select-supplier-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
};

window.closeSelectSupplier = function() {
    document.getElementById('select-supplier-modal').classList.add('hidden');
    document.body.style.overflow = '';
};

window.filterSuppliers = function(input) {
    supplierSearchQuery = input.value;
    supplierPage = 1;
    renderSupplierList();
};

async function renderSupplierList() {
    const tbody = document.getElementById('supplier-table-body');
    try {
        const resp = await fetch(window.viberRoutes.suppliers + '?q=' + encodeURIComponent(supplierSearchQuery) + '&page=' + supplierPage + '&perPage=50');
        const json = await resp.json();
        if (!json.success) { tbody.innerHTML = '<tr><td colspan="5" class="py-10 text-center text-slate-400 text-sm">Failed to load suppliers.</td></tr>'; return; }
        supplierTotalPages = json.last_page || 1;
        if (json.data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="py-10 text-center text-slate-400 text-sm">No suppliers found</td></tr>`;
            return;
        }
        let html = '';
        json.data.forEach(s => {
            const isSelected = pendingSelectedSupplier && pendingSelectedSupplier.id === s.id;
            html += `<tr onclick="selectSupplier(${s.id})" class="hover:bg-maroon-50 cursor-pointer transition-colors border-b border-slate-100 ${isSelected ? 'bg-maroon-50 ring-2 ring-maroon/20' : ''}">
                <td class="py-3 px-4 text-xs font-mono font-semibold text-maroon">${s.supplier_code || ''}</td>
                <td class="py-3 px-4 text-xs text-slate-700 font-medium">${s.name || ''}</td>
                <td class="py-3 px-4 text-xs text-slate-600">${s.contact_number || ''}</td>
                <td class="py-3 px-4 text-xs text-slate-600">${s.contact_person || ''}</td>
                <td class="py-3 px-4 text-xs text-slate-500 max-w-[200px] truncate" title="${s.address || ''}">${s.address || ''}</td>
            </tr>`;
        });
        tbody.innerHTML = html;
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="5" class="py-10 text-center text-slate-400 text-sm">Error loading suppliers.</td></tr>';
    }
}

window.selectSupplier = function(id) {
    pendingSelectedSupplier = { id };
    renderSupplierList();
};

window.confirmSelectSupplier = async function() {
    if (!pendingSelectedSupplier) { alert('Please select a supplier first.'); return; }
    try {
        const resp = await fetch(window.viberRoutes.storeSupplier, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
            body: JSON.stringify({ supplier_id: pendingSelectedSupplier.id, status: getStatus() })
        });
        const json = await resp.json();
        if (json.success) {
            closeSelectSupplier();
            await fetchViberLists(getStatus());
            await fetchAllViberLists();
            await fetchDashboardCounts();
        } else {
            alert(json.message || 'Failed to add supplier.');
        }
    } catch (e) {
        alert('Error adding supplier.');
    }
};

// ── Add Items Modal ──
let modalSelectedItems = [];
let modalStep = 1;
let modalSearchQuery = '';
let modalColumnFilters = { item_code: '', part_number: '', description: '', application: '', brand: '' };
let modalCurrentPage = 1;
let modalTotalPages = 1;
let modalProductsCache = [];
window.openAddItems = function() {
    const active = getActiveList();
    if (!active) { alert('Please select a supplier tab first.'); return; }
    modalSelectedItems = [];
    modalStep = 1;
    modalSearchQuery = '';
    modalColumnFilters = { item_code: '', part_number: '', description: '', application: '', brand: '' };
    modalCurrentPage = 1;
    modalProductsCache = [];
    document.getElementById('modal-search-input').value = '';
    document.querySelectorAll('.modal-col-filter').forEach(el => el.value = '');
    document.getElementById('add-items-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    renderModalStep1();
};

window.closeAddItems = function() {
    document.getElementById('add-items-modal').classList.add('hidden');
    document.body.style.overflow = '';
};

async function renderModalStep1() {
    document.getElementById('modal-step-1').classList.remove('hidden');
    document.getElementById('modal-step-2').classList.add('hidden');
    document.getElementById('modal-step-title').textContent = 'Step 1: Search & Select Items';

    const tbody = document.getElementById('modal-items-tbody');
    try {
        const params = new URLSearchParams({
            q: modalSearchQuery,
            page: modalCurrentPage,
            perPage: 50,
            item_code_q: modalColumnFilters.item_code,
            part_number_q: modalColumnFilters.part_number,
            description_q: modalColumnFilters.description,
            application_q: modalColumnFilters.application,
            brand_q: modalColumnFilters.brand,
        });
        const resp = await fetch(window.viberRoutes.products + '?' + params.toString());
        const json = await resp.json();
        if (!json.success) { tbody.innerHTML = '<tr><td colspan="7" class="py-10 text-center text-slate-400 text-sm">Failed to load products.</td></tr>'; return; }
        modalTotalPages = json.last_page || 1;
        modalProductsCache = json.data || [];

        if (json.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="py-10 text-center text-slate-400 text-sm">No items found</td></tr>';
        } else {
            let html = '';
            json.data.forEach(i => {
                const checked = modalSelectedItems.some(m => m.product_id === i.id) ? 'checked' : '';
                html += `<tr class="hover:bg-slate-50 transition-colors border-b border-slate-100">
                    <td class="py-3 px-4"><input type="checkbox" ${checked} onchange="toggleModalItem(${i.id})" class="rounded border-slate-300 text-maroon focus:ring-maroon w-4 h-4"></td>
                    <td class="py-3 px-4 text-xs font-mono font-semibold text-maroon">${i.product_code || ''}</td>
                    <td class="py-3 px-4 text-xs text-slate-700">${i.part_number || ''}</td>
                    <td class="py-3 px-4 text-xs text-slate-700">${i.description || ''}</td>
                    <td class="py-3 px-4 text-xs text-slate-600">${i.application || ''}</td>
                    <td class="py-3 px-4 text-xs text-slate-600">${i.brand || ''}</td>
                    <td class="py-3 px-4 text-xs text-slate-600">${i.unit || i.oum_unit || '—'}</td>
                </tr>`;
            });
            tbody.innerHTML = html;
        }

        const info = document.getElementById('modal-items-info');
        const prevBtn = document.getElementById('modal-prev-btn');
        const nextBtn = document.getElementById('modal-next-btn');
        if (info) info.textContent = `Page ${modalCurrentPage} of ${modalTotalPages} (${json.total || 0} items)`;
        if (prevBtn) {
            prevBtn.disabled = modalCurrentPage <= 1;
            prevBtn.className = `px-3 py-1.5 text-xs font-bold rounded-lg border transition-all ${modalCurrentPage <= 1 ? 'border-slate-100 text-slate-300 cursor-not-allowed' : 'border-slate-200 text-slate-600 hover:bg-slate-100 cursor-pointer'}`;
        }
        if (nextBtn) {
            nextBtn.disabled = modalCurrentPage >= modalTotalPages;
            nextBtn.className = `px-3 py-1.5 text-xs font-bold rounded-lg border transition-all ${modalCurrentPage >= modalTotalPages ? 'border-slate-100 text-slate-300 cursor-not-allowed' : 'border-slate-200 text-slate-600 hover:bg-slate-100 cursor-pointer'}`;
        }
        document.getElementById('modal-selected-count').textContent = `${modalSelectedItems.length} item(s) selected`;
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="7" class="py-10 text-center text-slate-400 text-sm">Error loading products.</td></tr>';
    }
}

window.toggleModalItem = function(productId) {
    const idx = modalSelectedItems.findIndex(m => m.product_id === productId);
    if (idx >= 0) {
        modalSelectedItems.splice(idx, 1);
        renderModalStep1();
        return;
    }
    const prod = modalProductsCache.find(p => p.id === productId);
    if (prod) {
        modalSelectedItems.push({
            product_id: prod.id,
            item_code: prod.product_code,
            part_no: prod.part_number,
            description: prod.description,
            application: prod.application,
            brand: prod.brand || '',
            unit: prod.unit || prod.oum_unit || '',
            oum_unit: prod.oum_unit || '',
            last_cost: prod.last_cost,
            new_cost: prod.last_cost,
            order_qty: 1,
            ordered_date: new Date().toISOString().split('T')[0],
            remarks: ''
        });
    }
    renderModalStep1();
};

window.filterModalItems = function(input) {
    modalSearchQuery = input.value;
    modalColumnFilters = { item_code: '', part_number: '', description: '', application: '', brand: '' };
    document.querySelectorAll('.modal-col-filter').forEach(el => el.value = '');
    modalCurrentPage = 1;
    renderModalStep1();
};

window.filterModalColumn = function(col, input) {
    modalColumnFilters[col] = input.value;
    modalCurrentPage = 1;
    renderModalStep1();
};

window.modalChangePage = function(delta) {
    const np = modalCurrentPage + delta;
    if (np < 1 || np > modalTotalPages) return;
    modalCurrentPage = np;
    renderModalStep1();
};

window.goToModalStep2 = function() {
    if (modalSelectedItems.length === 0) { alert('Please select at least one item.'); return; }
    modalStep = 2;
    document.getElementById('modal-step-1').classList.add('hidden');
    document.getElementById('modal-step-2').classList.remove('hidden');
    document.getElementById('modal-step-title').textContent = 'Step 2: Set Costs & Quantities';
    renderModalStep2();
};

window.goToModalStep1 = function() {
    modalStep = 1;
    renderModalStep1();
};

function renderModalStep2() {
    const tbody = document.getElementById('modal-confirm-tbody');
    let html = '';
    modalSelectedItems.forEach((item, idx) => {
        html += `<tr class="border-b border-slate-100">
            <td class="py-3 px-4 text-xs font-mono font-semibold text-maroon">${item.item_code || ''}</td>
            <td class="py-3 px-4 text-xs text-slate-700">${item.part_no || ''}</td>
            <td class="py-3 px-4 text-xs text-slate-700">${item.description || ''}</td>
            <td class="py-3 px-4 text-xs text-slate-600">${item.application || ''}</td>
            <td class="py-3 px-4 text-xs text-slate-600">${item.brand || ''}</td>
            <td class="py-3 px-4 text-xs text-right text-slate-600">₱${parseFloat(item.last_cost || 0).toLocaleString(undefined, {minimumFractionDigits:2})}</td>
            <td class="py-3 px-4"><input type="number" step="0.01" value="${item.new_cost}" onchange="updateModalItemField(${idx}, 'new_cost', this.value)" class="w-24 px-2 py-1.5 text-xs border border-slate-200 rounded-lg text-right focus:ring-2 focus:ring-maroon/20 outline-none"></td>
            <td class="py-3 px-4"><input type="number" value="${item.order_qty}" onchange="updateModalItemField(${idx}, 'order_qty', this.value)" class="w-16 px-2 py-1.5 text-xs border border-slate-200 rounded-lg text-center focus:ring-2 focus:ring-maroon/20 outline-none"></td>
            <td class="py-3 px-4 text-xs text-slate-600">${item.unit || item.oum_unit || '—'}</td>
            <td class="py-3 px-4"><input type="date" value="${item.ordered_date}" onchange="updateModalItemField(${idx}, 'ordered_date', this.value)" class="w-28 px-2 py-1.5 text-xs border border-slate-200 rounded-lg focus:ring-2 focus:ring-maroon/20 outline-none"></td>
            <td class="py-3 px-4 align-top"><textarea data-viber-autoresize="remarks" oninput="autoResizeViberRemarks(this); updateModalItemField(${idx}, 'remarks', this.value)" rows="1" class="w-40 min-w-[160px] px-2 py-1.5 text-xs leading-5 border border-slate-200 rounded-lg resize-none overflow-hidden whitespace-pre-wrap break-words focus:ring-2 focus:ring-maroon/20 outline-none">${escapePrintValue(item.remarks || '')}</textarea></td>
        </tr>`;
    });
    tbody.innerHTML = html;
    resizeViberRemarkTextareas(tbody);
}

window.updateModalItemField = function(idx, field, value) {
    if (modalSelectedItems[idx]) {
        modalSelectedItems[idx][field] = field === 'new_cost' || field === 'order_qty' ? parseFloat(value) || 0 : value;
    }
};

// ── Duplicate Warning State ──
let _pendingDuplicateKeys = new Set();
let _duplicateHighlightKeys = new Set();

window.saveModalItems = async function() {
    const active = getActiveList();
    if (!active) { alert('No active supplier tab.'); closeAddItems(); return; }

    // Ensure existing items are loaded for duplicate check
    if (!active._cachedItems) {
        active._cachedItems = await fetchItems(active.id);
    }

    // Check for duplicates against existing items in the supplier
    const existingKeys = new Set();
    active._cachedItems.forEach(item => {
        const key = item.product_id || item.item_code;
        if (key) existingKeys.add(String(key));
    });

    const duplicates = modalSelectedItems.filter(item => {
        const key = item.product_id || item.item_code;
        return key && existingKeys.has(String(key));
    });

    if (duplicates.length > 0) {
        _pendingDuplicateKeys = new Set(
            duplicates.map(d => d.product_id || d.item_code).filter(Boolean).map(String)
        );
        document.getElementById('duplicate-warning-modal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        return;
    }

    await doSaveItems();
};

window.confirmDuplicateContinue = async function() {
    document.getElementById('duplicate-warning-modal').classList.add('hidden');
    document.body.style.overflow = '';
    await doSaveItems();
};

window.cancelDuplicateContinue = function() {
    document.getElementById('duplicate-warning-modal').classList.add('hidden');
    document.body.style.overflow = '';
    _pendingDuplicateKeys = new Set();
};

async function doSaveItems() {
    const active = getActiveList();
    if (!active) { alert('No active supplier tab.'); closeAddItems(); return; }
    try {
        const resp = await fetch(window.viberRoutes.storeItems, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
            body: JSON.stringify({ viber_list_id: active.id, items: modalSelectedItems })
        });
        const json = await resp.json();
        if (json.success) {
            closeAddItems();
            active.item_count = (active.item_count || 0) + json.saved;
            // If duplicates were warned, set highlight keys and schedule cleanup
            if (_pendingDuplicateKeys.size > 0) {
                _duplicateHighlightKeys = new Set(_pendingDuplicateKeys);
                _pendingDuplicateKeys = new Set();
            } else {
                _duplicateHighlightKeys.clear();
            }
            renderAll();
            fetchDashboardCounts();
        } else {
            alert(json.message || 'Failed to save items.');
        }
    } catch (e) {
        alert('Error saving items.');
    }
}

// ── Action Buttons ──
function updateActionButtons() {
    const addSupplierBtn = document.getElementById('viber-add-supplier-action-btn');
    const addItemsBtn = document.getElementById('viber-add-items-btn');
    const printBtn = document.getElementById('viber-print-entry-btn');
    const transferBtn = document.getElementById('viber-transfer-item-btn');

    // Non-Entry dashboards: hide action buttons and search bar
    if (state.activeDashboard !== 'entry') {
        if (addSupplierBtn) addSupplierBtn.classList.add('hidden');
        if (addItemsBtn) addItemsBtn.classList.add('hidden');
        if (printBtn) printBtn.classList.add('hidden');
        if (transferBtn) transferBtn.classList.add('hidden');
        const searchParent = document.getElementById('viber-search-input')?.parentElement;
        if (searchParent) searchParent.style.display = 'none';
        return;
    }
    // Show search bar for Entry
    const searchParent = document.getElementById('viber-search-input')?.parentElement;
    if (searchParent) searchParent.style.display = '';

    const active = getActiveList();
    const lists = getCurrentLists();

    if (!active) {
        if (addSupplierBtn) {
            addSupplierBtn.classList.remove('hidden');
            addSupplierBtn.className = 'inline-flex items-center gap-1.5 px-4 py-2 bg-maroon text-white text-xs font-bold rounded-xl hover:bg-maroon-800 transition-all shadow-sm';
        }
        if (addItemsBtn) addItemsBtn.classList.add('hidden');
        if (printBtn) printBtn.classList.add('hidden');
        if (transferBtn) transferBtn.classList.add('hidden');
    } else {
        if (addSupplierBtn) addSupplierBtn.classList.add('hidden');
        if (addItemsBtn) {
            addItemsBtn.classList.remove('hidden');
            addItemsBtn.className = 'inline-flex items-center gap-1.5 px-4 py-2 bg-white text-maroon border border-maroon/30 text-xs font-bold rounded-xl hover:bg-maroon/5 transition-all shadow-sm cursor-pointer';
            addItemsBtn.disabled = false;
        }
        if (printBtn) {
            printBtn.classList.remove('hidden');
            printBtn.className = 'inline-flex items-center gap-1.5 px-4 py-2 bg-white text-slate-700 border border-slate-200 text-xs font-bold rounded-xl hover:bg-slate-50 hover:text-maroon transition-all shadow-sm cursor-pointer';
            printBtn.disabled = false;
        }
        if (transferBtn) {
            transferBtn.classList.remove('hidden');
            updateTransferButtonState();
        }
    }
}

// ── Render All ──
function renderAll() {
    renderSupplierTabs();
    renderMainView();
    updateActionButtons();
    updateTransferButtonState();
    const searchInput = document.getElementById('viber-search-input');
    if (searchInput) searchInput.value = getSearch();
    renderToShippedView();
    const arrivedArea = document.getElementById('viber-arrived-invoiced-area');
    const notArrivedArea = document.getElementById('viber-not-arrived-area');
    if (state.activeDashboard === 'entry') {
        if (arrivedArea) arrivedArea.classList.add('hidden');
        if (notArrivedArea) notArrivedArea.classList.add('hidden');
        renderTable();
    } else if (state.activeDashboard === 'arrived') {
        if (notArrivedArea) notArrivedArea.classList.add('hidden');
        renderArrivedInvoicedView();
    } else if (state.activeDashboard === 'not_arrived') {
        if (arrivedArea) arrivedArea.classList.add('hidden');
        renderNotArrivedView();
    } else {
        if (arrivedArea) arrivedArea.classList.add('hidden');
        if (notArrivedArea) notArrivedArea.classList.add('hidden');
    }
}

// ── Delete Supplier Modal ──
window.openDeleteSupplierModal = function(viberListId) {
    if (isDeleting) return;
    if (!hasUsableRoute(window.viberRoutes.destroySupplier)) {
        showToast('error', 'Supplier deletion is not available for this account.');
        return;
    }
    supplierToDeleteId = Number(viberListId);
    const modal = document.getElementById('delete-supplier-modal');
    if (modal) modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
};

window.closeDeleteSupplierModal = function() {
    supplierToDeleteId = null;
    const modal = document.getElementById('delete-supplier-modal');
    if (modal) modal.classList.add('hidden');
    document.body.style.overflow = '';
};

window.confirmDeleteSupplier = async function() {
    if (isDeleting || !supplierToDeleteId) return;

    const deletingSupplierId = supplierToDeleteId;
    const btn = document.getElementById('confirm-delete-supplier-btn');
    isDeleting = true;
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Deleting...';
    }

    try {
        const url = window.viberRoutes.destroySupplier.replace(':id', deletingSupplierId);
        const resp = await fetch(url, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        });
        const json = await resp.json();
        if (!resp.ok || !json.success) throw new Error(json.message || 'Failed to delete supplier tab.');

        window.closeDeleteSupplierModal();
        state.activeViberListId = null;
        delete state.searchQueries[deletingSupplierId];
        delete state.columnFilters[deletingSupplierId];
        delete state.pages[deletingSupplierId];

        await Promise.all([fetchViberLists(getStatus()), fetchAllViberLists(), fetchDashboardCounts()]);
        showToast('success', json.message || 'Supplier tab deleted successfully.');
    } catch (error) {
        showToast('error', error.message || 'Error deleting supplier tab.');
    } finally {
        isDeleting = false;
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Confirm Delete';
        }
    }
};

// ── Delete Item Modal ──
window.openDeleteItemModal = function(itemId) {
    if (isDeleting) return;
    if (!hasUsableRoute(window.viberRoutes.destroyItem)) {
        showToast('error', 'Item deletion is not available for this account.');
        return;
    }
    itemToDeleteId = Number(itemId);
    const modal = document.getElementById('delete-item-modal');
    if (modal) modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
};

window.closeDeleteItemModal = function() {
    itemToDeleteId = null;
    const modal = document.getElementById('delete-item-modal');
    if (modal) modal.classList.add('hidden');
    document.body.style.overflow = '';
};

window.confirmDeleteItem = async function() {
    if (isDeleting || !itemToDeleteId) return;

    const deletingItemId = itemToDeleteId;
    const btn = document.getElementById('confirm-delete-item-btn');
    isDeleting = true;
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Deleting...';
    }

    try {
        const url = window.viberRoutes.destroyItem.replace(':id', deletingItemId);
        const resp = await fetch(url, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        });
        const json = await resp.json();
        if (!resp.ok || !json.success) throw new Error(json.message || 'Failed to delete item.');

        window.closeDeleteItemModal();
        await Promise.all([fetchViberLists(getStatus()), fetchAllViberLists(), fetchDashboardCounts()]);
        showToast('success', json.message || 'Item deleted successfully.');
    } catch (error) {
        showToast('error', error.message || 'Error deleting item.');
    } finally {
        isDeleting = false;
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Confirm Delete';
        }
    }
};

// ── Inline Auto-Save State ──
let _inlineSaving = {};
let _inlineSaveTimers = {};
let _inlineSaveQueued = {};
let _inlineSaveRows = {};
let _inlineSavedTimers = {};

function setInlineAutoSaveState(row, stateName) {
    if (!row) return;
    const inputs = row.querySelectorAll('.inline-edit-input[data-item-id]');
    inputs.forEach(input => {
        input.classList.remove('viber-autosave-pending', 'viber-autosave-saved', 'viber-autosave-error');
        if (stateName === 'pending' || stateName === 'saving') input.classList.add('viber-autosave-pending');
        if (stateName === 'saved') input.classList.add('viber-autosave-saved');
        if (stateName === 'error') input.classList.add('viber-autosave-error');
    });
}

function updateViberItemCaches(itemId, serverData, payload) {
    const apply = item => {
        if (!item || Number(item.id) !== Number(itemId)) return;
        if (serverData && typeof serverData === 'object') Object.assign(item, serverData);
        else Object.assign(item, payload);
    };

    const active = getActiveList();
    if (active && Array.isArray(active._cachedItems)) {
        active._cachedItems.forEach(apply);
    }
    toShippedReadyItems.forEach(apply);
    toShippedShippedItems.forEach(apply);
    toShippedItemsCache.forEach(apply);
}

window.onInlineFieldChange = function(input, itemId, immediate = false) {
    const row = input.closest('tr');
    if (row) _inlineSaveRows[itemId] = row;

    setInlineAutoSaveState(row, 'pending');

    if (_inlineSaveTimers[itemId]) clearTimeout(_inlineSaveTimers[itemId]);
    const delay = immediate || input.type === 'date' || input.tagName === 'SELECT' ? 80 : 550;
    _inlineSaveTimers[itemId] = setTimeout(() => window.saveInlineItem(itemId), delay);
};

window.saveInlineItem = async function(itemId) {
    const updateRoute = hasUsableRoute(window.viberRoutes.updateItem)
        ? window.viberRoutes.updateItem
        : `${window.location.origin}/hatdog/public/regular/purchase/viber-list/items/:id`;
    if (!hasUsableRoute(updateRoute)) {
        showToast('error', 'Inline item editing is not available for this account.');
        return;
    }

    if (_inlineSaving[itemId]) {
        _inlineSaveQueued[itemId] = true;
        return;
    }

    const row = _inlineSaveRows[itemId]
        || document.querySelector(`[data-item-id="${itemId}"][data-field]`)?.closest('tr');
    if (!row) return;

    const inputs = row.querySelectorAll(`[data-item-id="${itemId}"][data-field]`);
    const payload = {};
    inputs.forEach(inp => {
        const field = inp.dataset.field;
        if (field === 'new_cost') {
            payload.new_cost = parseFloat(inp.value) || 0;
        } else if (field === 'order_qty') {
            payload.order_qty = parseFloat(inp.value) || 0;
        } else if (field === 'unit') {
            payload.unit = inp.value.trim();
        } else if (field === 'ordered_date') {
            payload.ordered_date = inp.value || null;
        } else if (field === 'remarks') {
            payload.remarks = inp.value;
        }
    });

    if (Object.keys(payload).length === 0) return;

    _inlineSaving[itemId] = true;
    setInlineAutoSaveState(row, 'saving');

    try {
        const url = updateRoute.replace(':id', itemId);
        const resp = await fetch(url, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify(payload)
        });
        const json = await resp.json();
        if (!resp.ok || !json.success) {
            throw new Error(json.message || 'Failed to auto-save item.');
        }

        updateViberItemCaches(itemId, json.data || null, payload);
        setInlineAutoSaveState(row, 'saved');
        if (_inlineSavedTimers[itemId]) clearTimeout(_inlineSavedTimers[itemId]);
        _inlineSavedTimers[itemId] = setTimeout(() => {
            setInlineAutoSaveState(row, 'idle');
        }, 900);
    } catch (e) {
        console.error('Auto-save item error:', e);
        setInlineAutoSaveState(row, 'error');
        showToast('error', e.message || 'Unable to auto-save the item.');
    } finally {
        _inlineSaving[itemId] = false;
        if (_inlineSaveQueued[itemId]) {
            _inlineSaveQueued[itemId] = false;
            if (_inlineSaveTimers[itemId]) clearTimeout(_inlineSaveTimers[itemId]);
            _inlineSaveTimers[itemId] = setTimeout(() => window.saveInlineItem(itemId), 100);
        }
    }
};

// ── Arrived Invoiced Items State ──
let arrivedData = null;
let arrivedActiveListId = null;
let arrivedSearch = '';
let arrivedColFilters = {};
let arrivedPage = 1;
let arrivedSuppliers = [];
let arrivedItemsMap = {};
let arrivedCachedFiltered = [];

async function fetchArrivedInvoicedData() {
    try {
        const resp = await fetch(window.viberRoutes.invoicedItems);
        const json = await resp.json();
        if (json.success) {
            arrivedData = json.data;
            arrivedSuppliers = json.data.suppliers || [];
            arrivedItemsMap = json.data.items || {};
            state.isLoaded['arrived'] = true;
            if (arrivedSuppliers.length > 0) {
                arrivedActiveListId = arrivedSuppliers[0].id;
            }
            renderAll();
        }
    } catch (e) {
        console.error('Failed to fetch arrived invoiced items:', e);
    }
}

function renderArrivedInvoicedView() {
    const area = document.getElementById('viber-arrived-invoiced-area');
    if (!area) return;
    area.classList.remove('hidden');
    renderArrivedSupplierTabs();
    renderArrivedTable();
}

function renderArrivedSupplierTabs() {
    const wrapper = document.getElementById('arrived-supplier-tabs-wrapper');
    const container = document.getElementById('arrived-supplier-tabs-container');
    if (!wrapper || !container) return;

    if (arrivedSuppliers.length === 0) {
        wrapper.style.display = 'none';
        return;
    }
    wrapper.style.display = 'flex';

    let html = '';
    arrivedSuppliers.forEach(s => {
        const isActive = s.id === arrivedActiveListId;
        html += `<div class="supplier-tab-wrapper inline-flex items-center gap-0 ${isActive ? '' : 'group'}">
            <button onclick="selectArrivedSupplierTab(${s.id})" style="${getSharedSupplierTabStyle(s, isActive)}" class="supplier-tab inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-l-lg border transition-all bg-white text-slate-600 border-slate-200 hover:border-maroon/30 hover:text-maroon ${isActive ? 'bg-yellow-400 text-maroon border-yellow-500 shadow-sm' : ''}">
                <span style="${getSupplierIconStyle(s)}" class="w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0">
                    <i data-lucide="building-2" class="w-3 h-3 text-white"></i>
                </span>
                <span class="truncate max-w-[100px]">${s.supplier_code}</span>
                <span class="opacity-40 mx-0.5">—</span>
                <span class="truncate max-w-[140px]">${s.supplier_name}</span>
                <span style="${getSupplierCountStyle(s, isActive)}" class="ml-1 px-1.5 py-0.5 rounded-full text-[9px] font-bold ${isActive ? 'bg-maroon/20 text-maroon' : 'bg-slate-100 text-slate-500'}">${s.item_count}</span>
            </button>
        </div>`;
    });
    container.innerHTML = html;
    if (typeof lucide !== 'undefined') setTimeout(() => lucide.createIcons(), 0);
}

window.selectArrivedSupplierTab = function(viberListId) {
    if (arrivedActiveListId === viberListId) return;
    arrivedActiveListId = viberListId;
    arrivedPage = 1;
    arrivedSearch = '';
    arrivedColFilters = {};
    document.getElementById('arrived-table-area')?.classList.add('hidden');
    renderArrivedTable();
};

function renderArrivedTable() {
    const emptyNoSuppliers = document.getElementById('arrived-empty-no-suppliers');
    const emptyNoSelection = document.getElementById('arrived-empty-no-selection');
    const emptyNoItems = document.getElementById('arrived-empty-no-items');
    const tableArea = document.getElementById('arrived-table-area');
    const tbody = document.getElementById('arrived-table-body');
    [emptyNoSuppliers, emptyNoSelection, emptyNoItems, tableArea].forEach(el => el?.classList.add('hidden'));

    if (arrivedSuppliers.length === 0) {
        emptyNoSuppliers.classList.remove('hidden');
        return;
    }

    if (!arrivedActiveListId) {
        emptyNoSelection.classList.remove('hidden');
        return;
    }

    const items = arrivedItemsMap[arrivedActiveListId] || [];
    if (items.length === 0) {
        emptyNoItems.classList.remove('hidden');
        return;
    }

    let filtered = [...items];
    if (arrivedSearch) {
        const q = arrivedSearch.toLowerCase();
        filtered = filtered.filter(e =>
            (e.item_code && e.item_code.toLowerCase().includes(q)) ||
            (e.part_no && e.part_no.toLowerCase().includes(q)) ||
            (e.description && e.description.toLowerCase().includes(q)) ||
            (e.po_number && e.po_number.toLowerCase().includes(q))
        );
    }

    Object.keys(arrivedColFilters).forEach(key => {
        const val = (arrivedColFilters[key] || '').trim().toLowerCase();
        if (val) {
            filtered = filtered.filter(e => {
                let fieldVal;
                if (key === 'po_date') fieldVal = e.po_date || '';
                else if (key === 'po_number') fieldVal = e.po_number || '';
                else if (key === 'actual_qty') fieldVal = String(e.actual_qty ?? '');
                else fieldVal = e[key] || '';
                return String(fieldVal).toLowerCase().includes(val);
            });
        }
    });

    arrivedCachedFiltered = filtered;

    const totalPages = Math.ceil(filtered.length / pageSize) || 1;
    let cp = arrivedPage;
    if (cp > totalPages) { cp = totalPages; arrivedPage = cp; }
    const start = (cp - 1) * pageSize;
    const pageData = filtered.slice(start, start + pageSize);

    tableArea.classList.remove('hidden');

    if (pageData.length === 0) {
        tbody.innerHTML = `<tr><td colspan="11" class="py-16 text-center text-slate-400">
            <i data-lucide="package-open" class="w-10 h-10 mx-auto mb-3 opacity-50"></i>
            <p class="text-sm font-medium">No items match your search.</p>
            <p class="text-xs mt-1">Try adjusting your search or filters.</p>
        </td></tr>`;
        if (typeof lucide !== 'undefined') setTimeout(() => lucide.createIcons(), 0);
        updateArrivedPagination(0, 0, 0);
        return;
    }

    let html = '';
    pageData.forEach(e => {
        const isPartial = e.is_partial_arrived;
        const rowClass = isPartial
            ? 'bg-yellow-100 hover:bg-yellow-200/70 transition-colors border-b border-slate-100'
            : 'hover:bg-emerald-50/50 transition-colors border-b border-slate-100';
        const textClass = isPartial ? 'text-amber-900' : '';
        html += `<tr class="${rowClass}">
            <td class="py-3 px-4 text-xs font-mono text-emerald-800 font-semibold ${textClass}">${e.item_code || '—'}</td>
            <td class="py-3 px-4 text-xs ${textClass}">${e.part_no || '—'}</td>
            <td class="py-3 px-4 text-xs ${textClass} max-w-[200px] truncate" title="${e.description || ''}">${e.description || '—'}</td>
            <td class="py-3 px-4 text-xs ${textClass}">${e.application || '—'}</td>
            <td class="py-3 px-4 text-xs text-right ${textClass}">${formatCurrencyValue(e.last_cost, e.currency_code)}</td>
            <td class="py-3 px-4 text-xs text-right font-semibold ${textClass}">${formatCurrencyValue(e.new_cost, e.currency_code)}</td>
            <td class="py-3 px-4 text-xs text-center font-semibold ${textClass}">${e.order_qty != null ? parseFloat(e.order_qty) : '—'}</td>
            <td class="py-3 px-4 text-xs ${textClass}">${e.oum_unit || '—'}</td>
            <td class="py-3 px-4 text-xs text-center font-semibold ${textClass}">${e.actual_qty != null ? parseFloat(e.actual_qty) : '—'}</td>
            <td class="py-3 px-4 text-xs ${textClass}">${e.ordered_date || '—'}</td>
            <td class="py-3 px-4 text-xs font-mono ${textClass}">${e.po_number || '—'}</td>
            <td class="py-3 px-4 text-xs ${textClass}">${e.po_date || '—'}</td>
        </tr>`;
    });
    tbody.innerHTML = html;
    if (typeof lucide !== 'undefined') setTimeout(() => lucide.createIcons(), 0);
    updateArrivedPagination(start + 1, start + pageData.length, filtered.length);
}

function updateArrivedPagination(from, to, total) {
    const info = document.getElementById('arrived-range-info');
    const indicator = document.getElementById('arrived-page-indicator');
    const prevBtn = document.getElementById('arrived-prev-btn');
    const nextBtn = document.getElementById('arrived-next-btn');
    const totalPages = Math.ceil(total / pageSize) || 1;
    if (info) info.textContent = total > 0 ? `Showing ${from}-${to} of ${total}` : 'Showing 0-0 of 0';
    if (indicator) indicator.textContent = `Page ${arrivedPage} of ${totalPages}`;
    if (prevBtn) {
        prevBtn.disabled = arrivedPage <= 1;
        prevBtn.className = `px-3 py-1.5 text-xs font-bold rounded-lg border transition-all ${arrivedPage <= 1 ? 'border-slate-100 text-slate-300 cursor-not-allowed' : 'border-slate-200 text-slate-600 hover:bg-slate-100 cursor-pointer'}`;
    }
    if (nextBtn) {
        nextBtn.disabled = arrivedPage >= totalPages;
        nextBtn.className = `px-3 py-1.5 text-xs font-bold rounded-lg border transition-all ${arrivedPage >= totalPages ? 'border-slate-100 text-slate-300 cursor-not-allowed' : 'border-slate-200 text-slate-600 hover:bg-slate-100 cursor-pointer'}`;
    }
}

window.changeArrivedPage = function(delta) {
    const totalPages = Math.ceil(arrivedCachedFiltered.length / pageSize) || 1;
    const np = arrivedPage + delta;
    if (np < 1 || np > totalPages) return;
    arrivedPage = np;
    renderArrivedTable();
};

window.filterArrivedColumn = function(input, column) {
    arrivedColFilters[column] = input.value;
    arrivedPage = 1;
    renderArrivedTable();
};

// ── Not Arrived Items State ──
let notArrivedData = null;
let notArrivedActiveListId = null;
let notArrivedSearch = '';
let notArrivedColFilters = {};
let notArrivedPage = 1;
let notArrivedSuppliers = [];
let notArrivedItemsMap = {};
let notArrivedCachedFiltered = [];

async function fetchNotArrivedData() {
    try {
        const resp = await fetch(window.viberRoutes.notArrivedItems);
        const json = await resp.json();
        if (json.success) {
            notArrivedData = json.data;
            notArrivedSuppliers = json.data.suppliers || [];
            notArrivedItemsMap = json.data.items || {};
            state.isLoaded['not_arrived'] = true;
            const activeStillExists = notArrivedSuppliers.some(s => Number(s.id) === Number(notArrivedActiveListId));
            notArrivedActiveListId = activeStillExists
                ? notArrivedActiveListId
                : (notArrivedSuppliers.length > 0 ? notArrivedSuppliers[0].id : null);
            renderAll();
            fetchDashboardCounts();
        }
    } catch (e) {
        console.error('Failed to fetch not-arrived items:', e);
    }
}

function renderNotArrivedView() {
    const area = document.getElementById('viber-not-arrived-area');
    if (!area) return;
    area.classList.remove('hidden');
    renderNotArrivedSupplierTabs();
    renderNotArrivedTable();
}

function renderNotArrivedSupplierTabs() {
    const wrapper = document.getElementById('not-arrived-supplier-tabs-wrapper');
    const container = document.getElementById('not-arrived-supplier-tabs-container');
    if (!wrapper || !container) return;

    if (notArrivedSuppliers.length === 0) {
        wrapper.style.display = 'none';
        return;
    }
    wrapper.style.display = 'flex';

    let html = '';
    notArrivedSuppliers.forEach(s => {
        const isActive = s.id === notArrivedActiveListId;
        html += `<div class="supplier-tab-wrapper inline-flex items-center gap-0 ${isActive ? '' : 'group'}">
            <button onclick="selectNotArrivedSupplierTab(${s.id})" style="${getSharedSupplierTabStyle(s, isActive)}" class="supplier-tab inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-l-lg border transition-all bg-white text-slate-600 border-slate-200 hover:border-maroon/30 hover:text-maroon ${isActive ? 'bg-yellow-400 text-maroon border-yellow-500 shadow-sm' : ''}">
                <span style="${getSupplierIconStyle(s)}" class="w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0">
                    <i data-lucide="building-2" class="w-3 h-3 text-white"></i>
                </span>
                <span class="truncate max-w-[100px]">${s.supplier_code}</span>
                <span class="opacity-40 mx-0.5">—</span>
                <span class="truncate max-w-[140px]">${s.supplier_name}</span>
                <span style="${getSupplierCountStyle(s, isActive)}" class="ml-1 px-1.5 py-0.5 rounded-full text-[9px] font-bold ${isActive ? 'bg-maroon/20 text-maroon' : 'bg-slate-100 text-slate-500'}">${s.item_count}</span>
            </button>
        </div>`;
    });
    container.innerHTML = html;
    if (typeof lucide !== 'undefined') setTimeout(() => lucide.createIcons(), 0);
}

window.selectNotArrivedSupplierTab = function(viberListId) {
    if (notArrivedActiveListId === viberListId) return;
    notArrivedActiveListId = viberListId;
    notArrivedPage = 1;
    notArrivedSearch = '';
    notArrivedColFilters = {};
    document.getElementById('not-arrived-table-area')?.classList.add('hidden');
    renderNotArrivedTable();
};

function renderNotArrivedTable() {
    const card = document.getElementById('viber-not-arrived-card');
    const emptyNoSuppliers = document.getElementById('not-arrived-empty-no-suppliers');
    const emptyNoSelection = document.getElementById('not-arrived-empty-no-selection');
    const emptyNoItems = document.getElementById('not-arrived-empty-no-items');
    const tableArea = document.getElementById('not-arrived-table-area');
    const tbody = document.getElementById('not-arrived-table-body');
    [emptyNoSuppliers, emptyNoSelection, emptyNoItems, tableArea].forEach(el => el?.classList.add('hidden'));

    const rollbackBtn = document.getElementById('rollback-not-arrived-btn');

    if (notArrivedSuppliers.length === 0) {
        emptyNoSuppliers.classList.remove('hidden');
        if (rollbackBtn) rollbackBtn.classList.add('hidden');
        return;
    }

    if (!notArrivedActiveListId) {
        emptyNoSelection.classList.remove('hidden');
        if (rollbackBtn) rollbackBtn.classList.add('hidden');
        return;
    }

    const items = notArrivedItemsMap[notArrivedActiveListId] || [];
    if (items.length === 0) {
        emptyNoItems.classList.remove('hidden');
        if (rollbackBtn) rollbackBtn.classList.add('hidden');
        return;
    }

    let filtered = [...items];
    if (notArrivedSearch) {
        const q = notArrivedSearch.toLowerCase();
        filtered = filtered.filter(e =>
            (e.item_code && e.item_code.toLowerCase().includes(q)) ||
            (e.part_no && e.part_no.toLowerCase().includes(q)) ||
            (e.description && e.description.toLowerCase().includes(q)) ||
            (e.po_number && e.po_number.toLowerCase().includes(q))
        );
    }

    Object.keys(notArrivedColFilters).forEach(key => {
        const val = (notArrivedColFilters[key] || '').trim().toLowerCase();
        if (val) {
            filtered = filtered.filter(e => {
                let fieldVal;
                if (key === 'po_date') fieldVal = e.po_date || '';
                else if (key === 'po_number') fieldVal = e.po_number || '';
                else if (key === 'actual_qty') fieldVal = String(e.actual_qty ?? '');
                else fieldVal = e[key] || '';
                return String(fieldVal).toLowerCase().includes(val);
            });
        }
    });

    notArrivedCachedFiltered = filtered;

    const totalPages = Math.ceil(filtered.length / pageSize) || 1;
    let cp = notArrivedPage;
    if (cp > totalPages) { cp = totalPages; notArrivedPage = cp; }
    const start = (cp - 1) * pageSize;
    const pageData = filtered.slice(start, start + pageSize);

    tableArea.classList.remove('hidden');

    if (rollbackBtn && items.length > 0) {
        rollbackBtn.classList.remove('hidden');
        if (typeof lucide !== 'undefined') setTimeout(() => lucide.createIcons(), 0);
    } else if (rollbackBtn) {
        rollbackBtn.classList.add('hidden');
    }

    if (pageData.length === 0) {
        tbody.innerHTML = `<tr><td colspan="12" class="py-16 text-center text-slate-400">
            <i data-lucide="package-open" class="w-10 h-10 mx-auto mb-3 opacity-50"></i>
            <p class="text-sm font-medium">No items match your search.</p>
            <p class="text-xs mt-1">Try adjusting your search or filters.</p>
        </td></tr>`;
        if (typeof lucide !== 'undefined') setTimeout(() => lucide.createIcons(), 0);
        updateNotArrivedPagination(0, 0, 0);
        return;
    }

    let html = '';
    pageData.forEach(e => {
        const isUnchecked = e.is_unchecked;
        const rowClass = isUnchecked
            ? 'bg-yellow-100 hover:bg-yellow-200/70 transition-colors border-b border-slate-100'
            : 'hover:bg-amber-50/50 transition-colors border-b border-slate-100';
        const textClass = isUnchecked ? 'text-amber-900 font-semibold' : '';
        html += `<tr class="${rowClass}">
            <td class="py-3 px-4 text-xs font-mono text-amber-800 font-semibold ${textClass}">${e.item_code || '—'}</td>
            <td class="py-3 px-4 text-xs ${textClass}">${e.part_no || '—'}</td>
            <td class="py-3 px-4 text-xs ${textClass} max-w-[200px] truncate" title="${e.description || ''}">${e.description || '—'}</td>
            <td class="py-3 px-4 text-xs ${textClass}">${e.application || '—'}</td>
            <td class="py-3 px-4 text-xs text-right ${textClass}">${formatCurrencyValue(e.last_cost, e.currency_code)}</td>
            <td class="py-3 px-4 text-xs text-right font-semibold ${textClass}">${formatCurrencyValue(e.new_cost, e.currency_code)}</td>
            <td class="py-3 px-4 text-xs text-center font-semibold ${textClass}">${e.order_qty != null ? parseFloat(e.order_qty) : '—'}</td>
            <td class="py-3 px-4 text-xs ${textClass}">${e.oum_unit || '—'}</td>
            <td class="py-3 px-4 text-xs ${textClass}">${e.ordered_date || '—'}</td>
            <td class="py-3 px-4 text-xs font-mono ${textClass}">${e.po_number || '—'}</td>
            <td class="py-3 px-4 text-xs ${textClass}">${e.po_date || '—'}</td>
            <td class="py-3 px-4 text-xs text-center font-semibold ${textClass}">${e.actual_qty != null ? e.actual_qty : '—'}</td>
        </tr>`;
    });
    tbody.innerHTML = html;
    if (typeof lucide !== 'undefined') setTimeout(() => lucide.createIcons(), 0);
    updateNotArrivedPagination(start + 1, start + pageData.length, filtered.length);
}

function updateNotArrivedPagination(from, to, total) {
    const info = document.getElementById('not-arrived-range-info');
    const indicator = document.getElementById('not-arrived-page-indicator');
    const prevBtn = document.getElementById('not-arrived-prev-btn');
    const nextBtn = document.getElementById('not-arrived-next-btn');
    const totalPages = Math.ceil(total / pageSize) || 1;
    if (info) info.textContent = total > 0 ? `Showing ${from}-${to} of ${total}` : 'Showing 0-0 of 0';
    if (indicator) indicator.textContent = `Page ${notArrivedPage} of ${totalPages}`;
    if (prevBtn) {
        prevBtn.disabled = notArrivedPage <= 1;
        prevBtn.className = `px-3 py-1.5 text-xs font-bold rounded-lg border transition-all ${notArrivedPage <= 1 ? 'border-slate-100 text-slate-300 cursor-not-allowed' : 'border-slate-200 text-slate-600 hover:bg-slate-100 cursor-pointer'}`;
    }
    if (nextBtn) {
        nextBtn.disabled = notArrivedPage >= totalPages;
        nextBtn.className = `px-3 py-1.5 text-xs font-bold rounded-lg border transition-all ${notArrivedPage >= totalPages ? 'border-slate-100 text-slate-300 cursor-not-allowed' : 'border-slate-200 text-slate-600 hover:bg-slate-100 cursor-pointer'}`;
    }
}

window.changeNotArrivedPage = function(delta) {
    const totalPages = Math.ceil(notArrivedCachedFiltered.length / pageSize) || 1;
    const np = notArrivedPage + delta;
    if (np < 1 || np > totalPages) return;
    notArrivedPage = np;
    renderNotArrivedTable();
};

window.filterNotArrivedColumn = function(input, column) {
    notArrivedColFilters[column] = input.value;
    notArrivedPage = 1;
    renderNotArrivedTable();
};

// ── Apply Initial Active Card Style ──
function applyInitialCardStyle() {
    const tab = state.activeDashboard;
    document.querySelectorAll('.viber-dashboard-card').forEach(c => {
        const isActive = c.dataset.tab === tab;
        if (isActive) {
            c.className = `viber-dashboard-card flex flex-col items-center justify-center p-4 rounded-xl border-2 cursor-pointer transition-all bg-yellow-400 border-yellow-500 ring-2 ring-yellow-400/50 shadow-lg shadow-yellow-400/10`;
            const icon = c.querySelector('.viber-card-icon');
            const count = c.querySelector('.viber-card-count');
            const label = c.querySelector('.viber-card-label');
            if (icon) icon.className = `viber-card-icon w-5 h-5 mb-1 text-maroon`;
            if (count) count.className = `viber-card-count text-2xl font-extrabold text-maroon`;
            if (label) label.className = `viber-card-label text-xs font-bold uppercase tracking-wider mt-0.5 text-maroon-800`;
        }
    });
}

// ── Toast Notification ──
function showToast(type, message) {
    const existing = document.querySelector('.viber-toast');
    if (existing) existing.remove();
    const toast = document.createElement('div');
    toast.className = `viber-toast fixed top-4 right-4 z-[200] px-5 py-3 rounded-xl shadow-2xl text-sm font-bold text-white transition-all duration-300 ${type === 'success' ? 'bg-emerald-600' : 'bg-red-600'}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 4000);
}

// ── Rollback Modal ──
let isRollingBack = false;

window.openRollbackModal = function() {
    if (isRollingBack) return;
    if (!hasUsableRoute(window.viberRoutes.notArrivedRollback)) {
        showToast('error', 'Rollback is not available for this account.');
        return;
    }

    const items = notArrivedItemsMap[notArrivedActiveListId] || [];
    if (!notArrivedActiveListId || items.length === 0) {
        showToast('error', 'There are no Not Arrived items to rollback.');
        return;
    }

    const modal = document.getElementById('rollback-modal');
    if (modal) modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
};

window.closeRollbackModal = function() {
    if (isRollingBack) return;
    const modal = document.getElementById('rollback-modal');
    if (modal) modal.classList.add('hidden');
    document.body.style.overflow = '';
};

window.closeRollbackSuccessModal = function() {
    const modal = document.getElementById('rollback-success-modal');
    if (modal) modal.classList.add('hidden');
    document.body.style.overflow = '';
};

window.confirmRollback = async function() {
    if (isRollingBack || !notArrivedActiveListId) return;

    const viberListId = Number(notArrivedActiveListId);
    const btn = document.getElementById('confirm-rollback-btn');
    isRollingBack = true;
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Rolling Back...';
    }

    try {
        const resp = await fetch(window.viberRoutes.notArrivedRollback, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify({ viber_list_id: viberListId })
        });
        const json = await resp.json();
        if (!resp.ok || !json.success) throw new Error(json.message || 'Rollback failed.');

        const rollbackModal = document.getElementById('rollback-modal');
        if (rollbackModal) rollbackModal.classList.add('hidden');

        const count = Number(json.rolled_back_count || (json.rolled_back_items || []).length || 0);
        const successMessage = document.getElementById('rollback-success-message');
        if (successMessage) {
            successMessage.textContent = count > 0
                ? `${count} item${count === 1 ? '' : 's'} returned to Ready to Shipped Items.`
                : (json.message || 'Remaining quantities were returned to Ready to Shipped Items.');
        }
        const successModal = document.getElementById('rollback-success-modal');
        if (successModal) successModal.classList.remove('hidden');

        state.isLoaded.entry = false;
        state.isLoaded.to_shipped = false;
        (state.viberLists.entry || []).forEach(list => {
            if (Number(list.id) === viberListId) delete list._cachedItems;
        });

        await fetchNotArrivedData();
        await fetchAllViberLists();
        await fetchDashboardCounts();
        showToast('success', json.message || 'Rollback completed successfully.');
    } catch (error) {
        showToast('error', error.message || 'Rollback failed.');
    } finally {
        isRollingBack = false;
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Confirm Rollback';
        }
    }
};

// ── Init ──
document.addEventListener('DOMContentLoaded', function() {
    ensureTransferUi();
    updateTransferButtonState();
    applyInitialCardStyle();
    fetchViberLists(getStatus());
    fetchAllViberLists();
    fetchDashboardCounts();
});
