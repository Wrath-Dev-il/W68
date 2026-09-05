/**
 * Purchase Note Client Logic
 */

window.__PURCHASE_NOTE_PRODUCT_ID_FIX__ = '2026-08-05-v4';
console.info('[PN FIX] Product ID restoration v4 loaded');

let selectedItems = [];
let forgottenPartialData = null;
let transferredPartialItems = [];
let wizardShowPartialNotesStep = false;
let currentTab = 'active';
let searchFilters = {};
let purchaseNoteFilters = { from_date: '', to_date: '', min_amount: '', max_amount: '' };
let currentPage = 1;
let itemSearchState = { page: 1, q: '', code: '', partNo: '', desc: '', cat: '' };
let purchaseNoteItemColumnFilters = { itemCode: '', partNo: '', description: '', unit: '', qty: '', actualQty: '', cost: '', subTotal: '', action: '' };
let itemSearchSelection = new Map();
let supplierSearchState = { page: 1, q: '', code: '', name: '', contact: '', contactPerson: '', address: '' };
let supplierItemConflicts = {};
let pendingTableHighlightNoteId = null;
let editingNoteId = null;
let editingNoteStatus = null;
let pendingConflictNote = null;
let originalPNItems = [];
const PN_SYNC_MIN_VISIBLE_MS = 3000;
window.selectedInvoicePoNumber = null;
window.selectedInvoiceOriginalItems = [];

function normalizePurchaseNoteProductId(value) {
    const parsedId = Number.parseInt(value, 10);
    return Number.isInteger(parsedId) && parsedId > 0 ? parsedId : null;
}

function getPurchaseNoteProductId(item) {
    return normalizePurchaseNoteProductId(
        item?.productId ?? item?.product_id ?? item?.masterlist_product_id ?? null
    );
}

function validatePurchaseNoteProductIds(items, context = 'Purchase Note') {
    const invalidItems = (items || []).filter(item => getPurchaseNoteProductId(item) === null);

    if (invalidItems.length === 0) {
        return true;
    }

    const invalidCodes = invalidItems
        .map(item => String(item?.code ?? item?.product_code ?? 'Unknown item').trim() || 'Unknown item')
        .join(', ');

    console.error(`[PN] ${context}: item product ID was lost before submission.`, invalidItems);
    alert(`Cannot save because the product ID could not be restored for: ${invalidCodes}. Please refresh the page and try again.`);

    return false;
}

function normalizePurchaseNoteProductCode(value) {
    return String(value ?? '')
        .trim()
        .replace(/\s+/g, ' ')
        .toUpperCase();
}

/**
 * Keep Edit Purchase Note items in the exact arrangement of the original
 * Purchase Note even when Step 2 reloads items from a Purchase Order.
 *
 * Example: TEST, TEST 2 must stay TEST, TEST 2 after reopening/editing.
 */
function keepOriginalPurchaseNoteItemOrder(items) {
    const list = Array.isArray(items) ? items.slice() : [];

    if (!editingNoteId || !Array.isArray(originalPNItems) || originalPNItems.length === 0 || list.length < 2) {
        return list;
    }

    const orderByProductId = new Map();
    const orderByProductCode = new Map();

    originalPNItems.forEach((item, index) => {
        const productId = getPurchaseNoteProductId(item);
        const productCode = normalizePurchaseNoteProductCode(item?.code ?? item?.product_code ?? '');

        if (productId !== null && !orderByProductId.has(productId)) {
            orderByProductId.set(productId, index);
        }
        if (productCode !== '' && !orderByProductCode.has(productCode)) {
            orderByProductCode.set(productCode, index);
        }
    });

    return list
        .map((item, loadedIndex) => {
            const productId = getPurchaseNoteProductId(item);
            const productCode = normalizePurchaseNoteProductCode(item?.code ?? item?.product_code ?? '');

            let originalIndex = Number.MAX_SAFE_INTEGER;
            if (productId !== null && orderByProductId.has(productId)) {
                originalIndex = orderByProductId.get(productId);
            } else if (productCode !== '' && orderByProductCode.has(productCode)) {
                originalIndex = orderByProductCode.get(productCode);
            }

            return { item, loadedIndex, originalIndex };
        })
        .sort((a, b) => {
            if (a.originalIndex !== b.originalIndex) {
                return a.originalIndex - b.originalIndex;
            }
            return a.loadedIndex - b.loadedIndex;
        })
        .map(entry => entry.item);
}

const purchaseNoteProductIdByCode = new Map();
const purchaseNoteProductLookupPromises = new Map();

function setPurchaseNoteItemProductId(item, productId) {
    const normalizedId = normalizePurchaseNoteProductId(productId);
    if (!item || normalizedId === null) return null;

    item.productId = normalizedId;
    item.product_id = normalizedId;

    const codeKey = normalizePurchaseNoteProductCode(item.code ?? item.product_code);
    if (codeKey) purchaseNoteProductIdByCode.set(codeKey, normalizedId);

    return normalizedId;
}

function rememberPurchaseNoteProductId(item) {
    if (!item) return null;

    const productId = getPurchaseNoteProductId(item);
    if (productId === null) return null;

    return setPurchaseNoteItemProductId(item, productId);
}

function seedPurchaseNoteProductIdIndex(items) {
    (items || []).forEach(rememberPurchaseNoteProductId);
}

async function fetchPurchaseNoteProductIdByCode(productCode) {
    const codeKey = normalizePurchaseNoteProductCode(productCode);
    if (!codeKey) return null;

    const cachedId = normalizePurchaseNoteProductId(purchaseNoteProductIdByCode.get(codeKey));
    if (cachedId !== null) return cachedId;

    if (purchaseNoteProductLookupPromises.has(codeKey)) {
        return purchaseNoteProductLookupPromises.get(codeKey);
    }

    const lookupPromise = (async () => {
        const searchUrl = window.purchaseRoutes?.searchProducts;
        if (!searchUrl) return null;

        try {
            const params = new URLSearchParams({ code: String(productCode ?? '').trim() });
            const response = await fetch(`${searchUrl}?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });
            const result = await response.json().catch(() => ({}));

            if (!response.ok || !result.success || !Array.isArray(result.data)) {
                return null;
            }

            const exactProduct = result.data.find(product =>
                normalizePurchaseNoteProductCode(product?.product_code) === codeKey
            );
            const productId = normalizePurchaseNoteProductId(
                exactProduct?.product_id ?? exactProduct?.id ?? null
            );

            if (productId !== null) {
                purchaseNoteProductIdByCode.set(codeKey, productId);
            }

            return productId;
        } catch (error) {
            console.error(`[PN FIX] Failed to restore product ID for ${productCode}.`, error);
            return null;
        } finally {
            purchaseNoteProductLookupPromises.delete(codeKey);
        }
    })();

    purchaseNoteProductLookupPromises.set(codeKey, lookupPromise);
    return lookupPromise;
}

async function restorePurchaseNoteProductIds(items, context = 'Purchase Note') {
    const targetItems = (items || []).filter(Boolean);

    // Build an ID index from every trusted data source already loaded by the page.
    [
        selectedItems,
        originalPNItems,
        window.remainingPNItems,
        window.selectedInvoiceOriginalItems,
        transferredPartialItems,
    ].forEach(seedPurchaseNoteProductIdIndex);
    seedPurchaseNoteProductIdIndex(targetItems);

    // First restore from another loaded item with the same normalized product code.
    targetItems.forEach(item => {
        if (getPurchaseNoteProductId(item) !== null) return;

        const codeKey = normalizePurchaseNoteProductCode(item.code ?? item.product_code);
        const indexedId = normalizePurchaseNoteProductId(purchaseNoteProductIdByCode.get(codeKey));
        if (indexedId !== null) setPurchaseNoteItemProductId(item, indexedId);
    });

    // For any item still missing its ID, ask the existing master-list search API.
    const unresolvedByCode = new Map();
    targetItems.forEach(item => {
        if (getPurchaseNoteProductId(item) !== null) return;

        const productCode = item.code ?? item.product_code ?? '';
        const codeKey = normalizePurchaseNoteProductCode(productCode);
        if (!codeKey) return;

        if (!unresolvedByCode.has(codeKey)) {
            unresolvedByCode.set(codeKey, { productCode, items: [] });
        }
        unresolvedByCode.get(codeKey).items.push(item);
    });

    await Promise.all(Array.from(unresolvedByCode.values()).map(async entry => {
        const productId = await fetchPurchaseNoteProductIdByCode(entry.productCode);
        if (productId === null) return;
        entry.items.forEach(item => setPurchaseNoteItemProductId(item, productId));
    }));

    const invalidItems = targetItems.filter(item => getPurchaseNoteProductId(item) === null);
    if (invalidItems.length === 0) {
        return true;
    }

    console.error(`[PN FIX] ${context}: unable to restore product IDs.`, invalidItems.map(item => ({
        product_id: item?.product_id ?? null,
        productId: item?.productId ?? null,
        product_code: item?.product_code ?? item?.code ?? null,
        purchase_order_id: item?.purchase_order_id ?? null,
        po_item_id: item?.po_item_id ?? null,
    })));

    return validatePurchaseNoteProductIds(targetItems, context);
}

function purchaseNoteItemIdentity(item) {
    return {
        productId: getPurchaseNoteProductId(item) ?? '',
        code: item?.code ?? item?.product_code ?? '',
        purchaseOrderId: item?.purchase_order_id ?? '',
    };
}

function selectedItemAlreadyExists(candidate) {
    const pending = purchaseNoteItemIdentity(candidate);
    return selectedItems.some(item => {
        const current = purchaseNoteItemIdentity(item);
        return String(current.productId) === String(pending.productId)
            && String(current.code) === String(pending.code)
            && String(current.purchaseOrderId || '') === String(pending.purchaseOrderId || '');
    });
}

function currentSelectedInvoiceId() {
    return window.selectedInvoiceId !== null && window.selectedInvoiceId !== undefined
        ? window.selectedInvoiceId
        : null;
}

function purchaseOrderIndexUrl() {
    return window.purchaseRoutes?.purchaseOrderIndex || '/hatdog/public/admin/purchase/purchase-order';
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

document.addEventListener('DOMContentLoaded', () => {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    const dateInput = document.getElementById('new-trans-date');
    if (dateInput) {
        const today = new Date().toISOString().split('T')[0];
        dateInput.value = today;
    }

    setupColumnSearchListeners();
    setupPurchaseNoteFilterInputs();
    fetchPurchaseNotesTable(1);

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('from_viber') === '1' && window.viberPrepareNote) {
        setTimeout(() => {
            loadViberPrepareData(window.viberPrepareNote);
        }, 300);
    }
});

function loadViberPrepareData(data) {
    const supplierCode = data.supplier_code || '';
    const supplierName = data.supplier_name || '';
    const items = data.items || [];

    // Open modal first to reset all state, THEN set supplier (modal clears it)
    openAddNoteModal();

    if (supplierCode) {
        const supplierTrigger = document.getElementById('supplier-search-input-trigger');
        const supplierIdInput = document.getElementById('selected-supplier-id');
        if (supplierTrigger) supplierTrigger.value = `${supplierCode} — ${supplierName}`;
        if (supplierIdInput) supplierIdInput.value = supplierCode;
        refreshSupplierItemConflicts(supplierCode);
        if (!editingNoteId) {
            checkForgottenPartialItems(supplierCode);
        }
    }

    items.forEach(item => {
        const qty = parseFloat(item.order_qty) || 1;
        const cost = parseFloat(item.new_cost) || 0;
        selectedItems.push({
            productId: item.product_id,
            product_id: item.product_id,
            code: item.item_code || '',
            partNo: item.part_no || '',
            desc: item.description || '',
            unit: item.oum_unit || item.unit || '',
            qty: qty,
            cost: cost,
            subTotal: qty * cost,
            currency: item.currency_code || 'PHP',
            conversion_rate: null,
            unit_price_converted: null,
            total_price_converted: null,
            po_number: null,
            purchase_order_id: null,
        });
    });

    goToStep(3);
    renderPOItemsTable();
    updatePOTotals();
}

function setupColumnSearchListeners() {
    const debouncedFetch = debounce(() => fetchPurchaseNotesTable(1), 300);
    document.querySelectorAll('#page-purchase-note-root .column-search-input').forEach(input => {
        input.addEventListener('input', (e) => {
            const col = e.target.getAttribute('data-col');
            searchFilters[col] = e.target.value.trim();
            debouncedFetch();
        });
    });
}

function getPurchaseNoteFilterInputs() {
    const modal = document.getElementById('filter-modal');
    if (!modal) {
        return {};
    }

    const dateInputs = modal.querySelectorAll('input[type="date"]');
    const amountInputs = modal.querySelectorAll('input[type="number"]');

    const fromDate = document.getElementById('pn-filter-from-date') || dateInputs[0] || null;
    const toDate = document.getElementById('pn-filter-to-date') || dateInputs[1] || null;
    const minAmount = document.getElementById('pn-filter-min-amount') || amountInputs[0] || null;
    const maxAmount = document.getElementById('pn-filter-max-amount') || amountInputs[1] || null;

    if (fromDate && !fromDate.id) fromDate.id = 'pn-filter-from-date';
    if (toDate && !toDate.id) toDate.id = 'pn-filter-to-date';
    if (minAmount && !minAmount.id) minAmount.id = 'pn-filter-min-amount';
    if (maxAmount && !maxAmount.id) maxAmount.id = 'pn-filter-max-amount';

    return { fromDate, toDate, minAmount, maxAmount };
}

function setupPurchaseNoteFilterInputs() {
    const { minAmount, maxAmount } = getPurchaseNoteFilterInputs();
    [minAmount, maxAmount].forEach(input => {
        if (!input) return;
        input.min = '0';
        input.step = '0.01';
        input.inputMode = 'decimal';
    });
}

function showPurchaseNoteFilterError(message) {
    const errorEl = document.getElementById('pn-filter-error');
    if (!errorEl) {
        alert(message);
        return;
    }

    errorEl.textContent = message;
    errorEl.classList.remove('hidden');
}

function clearPurchaseNoteFilterError() {
    const errorEl = document.getElementById('pn-filter-error');
    if (!errorEl) return;
    errorEl.textContent = '';
    errorEl.classList.add('hidden');
}

function syncPurchaseNoteFilterInputs() {
    const { fromDate, toDate, minAmount, maxAmount } = getPurchaseNoteFilterInputs();
    if (fromDate) fromDate.value = purchaseNoteFilters.from_date || '';
    if (toDate) toDate.value = purchaseNoteFilters.to_date || '';
    if (minAmount) minAmount.value = purchaseNoteFilters.min_amount || '';
    if (maxAmount) maxAmount.value = purchaseNoteFilters.max_amount || '';
}

window.openPurchaseNoteFilters = function() {
    clearPurchaseNoteFilterError();
    setupPurchaseNoteFilterInputs();
    syncPurchaseNoteFilterInputs();
    toggleModal('filter-modal', true);
};

window.applyPurchaseNoteFilters = function() {
    clearPurchaseNoteFilterError();
    const { fromDate, toDate, minAmount, maxAmount } = getPurchaseNoteFilterInputs();
    const filters = {
        from_date: (fromDate?.value || '').trim(),
        to_date: (toDate?.value || '').trim(),
        min_amount: (minAmount?.value || '').trim(),
        max_amount: (maxAmount?.value || '').trim(),
    };

    if (filters.from_date && filters.to_date && filters.from_date > filters.to_date) {
        showPurchaseNoteFilterError('From date cannot be later than To date.');
        return;
    }

    const min = filters.min_amount === '' ? null : Number(filters.min_amount);
    const max = filters.max_amount === '' ? null : Number(filters.max_amount);

    if ((min !== null && !Number.isFinite(min)) || (max !== null && !Number.isFinite(max))) {
        showPurchaseNoteFilterError('Amount filters must be valid numbers.');
        return;
    }

    if ((min !== null && min < 0) || (max !== null && max < 0)) {
        showPurchaseNoteFilterError('Amount filters cannot be negative.');
        return;
    }

    if (min !== null && max !== null && min > max) {
        showPurchaseNoteFilterError('Minimum amount cannot be greater than maximum amount.');
        return;
    }

    purchaseNoteFilters = filters;
    currentPage = 1;
    toggleModal('filter-modal', false);
    fetchPurchaseNotesTable(1);
};

window.resetPurchaseNoteFilters = function() {
    purchaseNoteFilters = { from_date: '', to_date: '', min_amount: '', max_amount: '' };
    syncPurchaseNoteFilterInputs();
    clearPurchaseNoteFilterError();
    currentPage = 1;
    toggleModal('filter-modal', false);
    fetchPurchaseNotesTable(1);
};

window.switchMainTab = function(tab) {
    currentTab = tab;
    searchFilters = {};
    currentPage = 1;

    const activeBtn = document.getElementById('tab-active');
    const closedBtn = document.getElementById('tab-closed');

    document.querySelectorAll('.column-search-input').forEach(input => input.value = '');

    if (tab === 'active') {
        activeBtn.className = 'px-6 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all duration-200 bg-maroon text-white shadow-md';
        closedBtn.className = 'px-6 py-2.5 rounded-xl text-xs font-bold uppercase tracking-widest transition-all duration-200 text-slate-500 hover:bg-slate-50';
    } else {
        closedBtn.className = 'px-6 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all duration-200 bg-maroon text-white shadow-md';
        activeBtn.className = 'px-6 py-2.5 rounded-xl text-xs font-bold uppercase tracking-widest transition-all duration-200 text-slate-500 hover:bg-slate-50';
    }

    fetchPurchaseNotesTable(1);
};

window.fetchPurchaseNotesTable = function(page, onComplete) {
    currentPage = page;
    const tbody = document.getElementById('purchase-note-tbody');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="7" class="p-12 text-center text-slate-400 italic"><div class="flex items-center justify-center space-x-2"><i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i><span>Loading purchase notes...</span></div></td></tr>';
    if (typeof lucide !== 'undefined') lucide.createIcons();

    const params = new URLSearchParams({ page: page, perPage: 50, tab: currentTab });
    Object.entries(searchFilters).forEach(([col, val]) => {
        if (val) params.append(`search[${col}]`, val);
    });
    Object.entries(purchaseNoteFilters).forEach(([key, val]) => {
        if (val) params.append(key, val);
    });

    fetch(`${window.purchaseRoutes.fetch}?${params.toString()}`)
        .then(res => res.json())
        .then(r => {
            if (r.success) {
                renderPurchaseNotesTable(r.notes);
                renderPagination(r.pagination);
                updateStats(r.stats);
                if (typeof onComplete === 'function') onComplete();
            } else {
                tbody.innerHTML = '<tr><td colspan="7" class="p-12 text-center text-red-400 italic">Failed to load data.</td></tr>';
            }
        })
        .catch(() => {
            tbody.innerHTML = '<tr><td colspan="7" class="p-12 text-center text-red-400 italic">Error loading data.</td></tr>';
        });
};

function renderPurchaseNotesTable(notes) {
    const tbody = document.getElementById('purchase-note-tbody');
    if (!tbody) return;

    if (!notes || notes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="p-12 text-center text-slate-400 italic">No purchase notes found.</td></tr>';
        return;
    }

    const statusColors = {
        'Open': 'bg-amber-50 text-amber-600',
        'Partial': 'bg-blue-50 text-blue-600',
        'Surplus': 'bg-purple-50 text-purple-600',
        'Closed': 'bg-emerald-50 text-emerald-600'
    };

    tbody.innerHTML = notes.map(n => {
        const sym = { 'PHP': '₱', 'USD': '$', 'TWD': 'NT$' };
        const currency = n.currency || 'PHP';
        const symbol = sym[currency] || '₱';
        return `
        <tr class="hover:bg-slate-50/50 transition-colors" data-note-id="${n.id}" data-pn-number="${n.pn_number}">
            <td class="p-4 px-6 font-mono font-bold text-maroon">${n.pn_number}</td>
            <td class="p-4 px-6 text-slate-700 font-medium">${n.supplier_name}</td>
            <td class="p-4 px-6 text-slate-500">${n.date}</td>
            <td class="p-4 px-6 text-maroon font-bold">${symbol} ${parseFloat(n.total_amount).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
            <td class="p-4 px-6 text-center">
                <span class="px-3 py-1 rounded-full text-[10px] font-bold ${statusColors[n.status] || 'bg-slate-100 text-slate-600'}">${n.status}</span>
            </td>
            <td class="p-4 px-6 text-slate-500 font-mono text-xs">${n.reference || '---'}</td>
            <td class="p-4 px-6 text-center">
                <div class="flex items-center justify-center space-x-2">
                    <button onclick="handleView('${n.id}')" class="p-2 hover:bg-sky-50 text-sky-600 rounded-lg transition-colors" title="View">
                        <i data-lucide="eye" class="w-4 h-4"></i>
                    </button>
                    <button onclick="handlePrint('${n.id}')" class="p-2 hover:bg-purple-50 text-purple-600 rounded-lg transition-colors" title="Print">
                        <i data-lucide="printer" class="w-4 h-4"></i>
                    </button>
                    <button onclick="handleEdit('${n.id}')" class="p-2 hover:bg-blue-50 text-blue-600 rounded-lg transition-colors" title="Edit">
                        <i data-lucide="edit-3" class="w-4 h-4"></i>
                    </button>
                    <button onclick="handleDelete('${n.id}')" class="p-2 hover:bg-red-50 text-red-600 rounded-lg transition-colors" title="Delete">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                </div>
            </td>
        </tr>`;
    }).join('');

    if (typeof lucide !== 'undefined') lucide.createIcons();

    if (pendingTableHighlightNoteId) {
        highlightPurchaseNoteRow(pendingTableHighlightNoteId);
        pendingTableHighlightNoteId = null;
    }
}

function renderPagination(pagination) {
    const container = document.getElementById('pn-pagination-container');
    const infoFrom = document.getElementById('pn-page-info-from');
    const infoTo = document.getElementById('pn-page-info-to');
    const infoTotal = document.getElementById('pn-page-info-total');
    if (!container) return;

    if (infoFrom) infoFrom.innerText = pagination.from || 0;
    if (infoTo) infoTo.innerText = pagination.to || 0;
    if (infoTotal) infoTotal.innerText = pagination.total || 0;

    let html = '';

    html += `
        <button onclick="fetchPurchaseNotesTable(${pagination.current_page - 1})"
            ${pagination.current_page === 1 ? 'disabled' : ''}
            class="p-2 rounded-xl border border-slate-200 text-slate-400 hover:text-maroon hover:border-maroon disabled:opacity-50 disabled:cursor-not-allowed transition-all">
            <i data-lucide="chevron-left" class="w-4 h-4"></i>
        </button>
    `;

    for (let i = 1; i <= pagination.last_page; i++) {
        if (i === 1 || i === pagination.last_page || (i >= pagination.current_page - 2 && i <= pagination.current_page + 2)) {
            html += `
                <button onclick="fetchPurchaseNotesTable(${i})"
                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all ${i === pagination.current_page ? 'bg-maroon text-white shadow-md shadow-maroon/20' : 'text-slate-500 hover:bg-slate-100 border border-transparent'}">
                    ${i}
                </button>
            `;
        } else if (i === pagination.current_page - 3 || i === pagination.current_page + 3) {
            html += `<span class="text-slate-300 px-1">...</span>`;
        }
    }

    html += `
        <button onclick="fetchPurchaseNotesTable(${pagination.current_page + 1})"
            ${pagination.current_page === pagination.last_page ? 'disabled' : ''}
            class="p-2 rounded-xl border border-slate-200 text-slate-400 hover:text-maroon hover:border-maroon disabled:opacity-50 disabled:cursor-not-allowed transition-all">
            <i data-lucide="chevron-right" class="w-4 h-4"></i>
        </button>
    `;

    container.innerHTML = html;
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function updateStats(stats) {
    if (!stats) return;
    const totalEl = document.getElementById('stat-total-transactions');
    const openEl = document.getElementById('stat-open-notes');
    const amountEl = document.getElementById('stat-total-amount');
    if (totalEl) totalEl.innerText = stats.total_transactions || 0;
    if (openEl) openEl.innerText = stats.open_notes || 0;
    if (amountEl) amountEl.innerText = '₱ ' + parseFloat(stats.total_amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2});
}

/**
 * Toggle Modal Visibility
 */
window.toggleModal = function(modalId, show) {
    const modal = document.getElementById(modalId);
    if (!modal) return;

    if (show) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    } else {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        
        // Only restore scroll if no other modals are visible
        const anyModalVisible = document.querySelector('.fixed.inset-0:not(.hidden)');
        if (!anyModalVisible) {
            document.body.style.overflow = 'auto';
        }
    }
};

window.closePurchaseNoteWizard = function() {
    const date = document.getElementById('new-trans-date')?.value;
    const remarks = document.getElementById('new-remarks')?.value;
    const supplierTrigger = document.getElementById('supplier-search-input-trigger')?.value;
    const hasItems = selectedItems && selectedItems.length > 0;

    if ((date || remarks || supplierTrigger || hasItems) && !window._closingConfirmed) {
        confirmAction(
            'Discard Changes?',
            'You have unsaved data. Are you sure you want to discard all changes?',
            () => {
                window._closingConfirmed = true;
                closePurchaseNoteWizard();
            }
        );
        return;
    }

    toggleModal('purchase-note-wizard-modal', false);
    editingNoteId = null;
    editingNoteStatus = null;
    window._closingConfirmed = false;
    if (typeof window.clearInvoiceFilter === 'function') {
        window.clearInvoiceFilter();
    } else {
        window.selectedInvoiceId = null;
        window.selectedInvoicePoNumber = null;
    }
};

/**
 * Multi-step wizard navigation (single modal)
 */
window.goToStep = function(step) {
    // In add mode with partial notes step shown, step 2 is Linked Partial Notes
    if (step === 2 && !editingNoteId && wizardShowPartialNotesStep) {
        const supplierCode = document.getElementById('selected-supplier-id')?.value;
        if (!supplierCode) {
            alert('Please select a supplier first.');
            step = 1;
        }
    }

    if (step === 2 && editingNoteId) {
        const supplierCode = document.getElementById('selected-supplier-id')?.value;
        if (!supplierCode) {
            alert('Please select a supplier first.');
            step = 1;
        } else {
            loadPNInvoices();
        }
    }

    if (step === 3) {
        const supplierCode = document.getElementById('selected-supplier-id')?.value;
        if (!supplierCode) {
            alert('Please select a supplier first.');
            step = 1;
            return;
        }
        if (!editingNoteId) {
            // Add mode: skip invoice selection, go straight to items
        } else if (window.selectedInvoiceId === null || window.selectedInvoiceId === undefined) {
            const hasInvoices = document.querySelectorAll('input[name="pn_selected_invoice"]').length > 0;
            if (hasInvoices) {
                alert('Please select an invoice first.');
                return;
            }
        }
        refreshSupplierItemConflicts(supplierCode);
        renderPOItemsTable();
    }

    if (step === 4) {
        if (!selectedItems || selectedItems.length === 0) {
            alert('Please add at least one item.');
            return;
        }
        populateReviewData();
    }

    document.querySelectorAll('.pn-wizard-step-content').forEach(el => el.classList.add('hidden'));
    const target = document.getElementById(`pn-wizard-step-${step}`);
    if (target) target.classList.remove('hidden');

    updatePNStepIndicators(step);

    if (!document.getElementById('purchase-note-wizard-modal')?.classList.contains('hidden')) {
        toggleModal('purchase-note-wizard-modal', true);
    }
};

function updatePNStepIndicators(currentStep) {
    const isAdd = !editingNoteId;
    const showPartial = isAdd && wizardShowPartialNotesStep;
    const displayMap = isAdd ? (showPartial ? { 1: 1, 2: 2, 3: 3, 4: 4 } : { 1: 1, 3: 2, 4: 3 }) : {};

    for (let i = 1; i <= 4; i++) {
        const indicator = document.getElementById(`pn-step-indicator-${i}`);
        const label = document.getElementById(`pn-step-label-${i}`);
        if (!indicator) continue;

        // In Add mode without partial notes, skip step 2 (Invoices)
        if (isAdd && i === 2 && !showPartial) continue;

        indicator.classList.remove('active', 'completed', 'pending');

        if (i < currentStep) {
            indicator.classList.add('completed');
            indicator.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i>';
            if (label) {
                label.classList.remove('text-white/40');
                label.classList.add('text-white/70');
            }
        } else if (i === currentStep) {
            indicator.classList.add('active');
            indicator.innerText = isAdd ? displayMap[i] : i;
            if (label) {
                label.classList.remove('text-white/40');
                label.classList.add('text-white/70');
            }
        } else {
            indicator.classList.add('pending');
            indicator.innerText = isAdd ? displayMap[i] : i;
            if (label) {
                label.classList.remove('text-white/70');
                label.classList.add('text-white/40');
            }
        }
    }

    if (typeof lucide !== 'undefined') lucide.createIcons();
}

/**
 * Load invoices (POs) linked to the current purchase note into Step 2
 */
async function loadPNInvoices() {
    const noteNumber = document.getElementById('new-po-number')?.value;
    const tbody = document.getElementById('pn-invoices-tbody');
    if (!noteNumber || !tbody) return;

    tbody.innerHTML = '<tr><td colspan="7" class="p-8 text-center text-slate-400 italic"><i data-lucide="loader-2" class="w-4 h-4 inline animate-spin"></i> Loading invoices...</td></tr>';
    if (window.lucide) window.lucide.createIcons();

    try {
        const baseUrl = window.purchaseRoutes?.invoicesByNoteUrl || '/hatdog/public/admin/purchase/purchase-order/by-note/:noteNumber';
        const url = baseUrl.replace(':noteNumber', encodeURIComponent(noteNumber));
        const res = await fetch(url);
        const result = await res.json();

        if (!result.success) throw new Error(result.message || 'Failed to load invoices');

        // Store remaining items for virtual Open entry
        window.remainingPNItems = keepOriginalPurchaseNoteItemOrder(
            (result.remaining_items || []).map(item => ({
                productId: item.product_id,
                product_id: item.product_id,
                code: item.product_code,
                partNo: item.part_number || '',
                desc: item.description || '',
                unit: item.unit || 'PCS',
                qty: parseFloat(item.quantity) || 0,
                cost: parseFloat(item.unit_price) || 0,
                subTotal: (parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0),
                currency: 'PHP',
                processed: false,
                purchase_order_id: 0,
                po_number: document.getElementById('new-po-number')?.value || '',
                po_item_id: null,
            }))
        );

        renderPNInvoices(result.invoices || []);
    } catch (e) {
        console.error('Error loading invoices:', e);
        tbody.innerHTML = '<tr><td colspan="7" class="p-8 text-center text-red-400 italic">Failed to load invoices.</td></tr>';
    }
}

function renderPNInvoices(invoices) {
    const tbody = document.getElementById('pn-invoices-tbody');
    if (!tbody) return;

    if (!invoices.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="p-8 text-center text-slate-400 italic">No invoices found for this purchase note.</td></tr>';
        return;
    }

    const statusColors = {
        'Pending': 'bg-amber-50 text-amber-600',
        'Partial': 'bg-blue-50 text-blue-600',
        'Closed': 'bg-emerald-50 text-emerald-600',
        'Open': 'bg-slate-50 text-slate-600',
        'Surplus': 'bg-purple-50 text-purple-600'
    };

    tbody.innerHTML = invoices.map(inv => {
        const invoiceId = Number.parseInt(inv.id, 10);
        const safeInvoiceId = Number.isFinite(invoiceId) ? invoiceId : 0;
        const selectedInvoiceId = Number.parseInt(window.selectedInvoiceId, 10);
        const isChecked = selectedInvoiceId === safeInvoiceId ? 'checked' : '';
        const poNumber = escapeHtml(inv.po_number || '');
        const status = escapeHtml(inv.status || '');
        return `
        <tr class="hover:bg-slate-50 transition-colors cursor-pointer"
            data-po-id="${safeInvoiceId}"
            data-po-number="${poNumber}"
            data-po-status="${status}"
            onclick="selectInvoiceAndProceed(this)">
            <td class="p-3 px-4 text-center">
                <input type="radio" name="pn_selected_invoice" value="${safeInvoiceId}" ${isChecked} onchange="selectInvoiceForFilteringFromControl(this)" class="w-4 h-4 rounded-full border-slate-300 text-maroon focus:ring-maroon" onclick="event.stopPropagation()">
            </td>
            <td class="p-3 px-4 font-mono font-bold text-maroon">${poNumber || '---'}</td>
            <td class="p-3 px-4 text-slate-600 font-mono text-[11px]">${inv.receiving_number || '---'}</td>
            <td class="p-3 px-4 text-slate-700">${inv.supplier_invoice_number || '---'}</td>
            <td class="p-3 px-4 text-slate-500">${inv.date || '---'}</td>
            <td class="p-3 px-4 text-center">
                <span class="px-3 py-1 rounded-full text-[10px] font-bold ${statusColors[inv.status] || 'bg-slate-100 text-slate-600'}">${status || '---'}</span>
            </td>
            <td class="p-3 px-4 text-center">
                <span class="selection-badge inline-flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-bold rounded-lg ${isChecked ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-slate-100 text-slate-400 border border-slate-200'}">
                    <i data-lucide="${isChecked ? 'check-circle' : 'circle'}" class="w-3 h-3"></i>
                    <span>${isChecked ? 'Selected' : 'Select'}</span>
                </span>
            </td>
        </tr>
    `;
    }).join('');

    if (window.lucide) window.lucide.createIcons();
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));
}

function selectInvoiceFromElement(element) {
    const row = element?.closest?.('tr') || element;
    const poId = Number.parseInt(row?.dataset?.poId, 10);
    const safePoId = Number.isFinite(poId) ? poId : 0;
    const poNumber = row?.dataset?.poNumber || '';
    const poStatus = row?.dataset?.poStatus || '';
    const radio = row?.querySelector?.('input[name="pn_selected_invoice"]');
    if (radio) radio.checked = true;
    return window.selectInvoiceForFiltering(safePoId, poNumber, poStatus);
}

window.selectInvoiceAndProceed = function(source, poNumber = '', poStatus = '') {
    if (source && typeof source === 'object') {
        return selectInvoiceFromElement(source);
    }

    const poId = Number.parseInt(source, 10);
    const safePoId = Number.isFinite(poId) ? poId : 0;
    const radio = document.querySelector(`input[name="pn_selected_invoice"][value="${safePoId}"]`);
    if (radio) {
        return selectInvoiceFromElement(radio);
    }

    return window.selectInvoiceForFiltering(safePoId, poNumber, poStatus);
};

window.selectInvoiceForFilteringFromControl = function(control) {
    return selectInvoiceFromElement(control);
};

window.selectInvoiceForFiltering = async function(poId, poNumber, poStatus) {
    console.log(`[PN] selectInvoiceForFiltering called: poId=${poId}, poNumber=${poNumber}, status=${poStatus}`);
    console.log(`[PN] Setting selectedInvoiceId to: ${poId} (type: ${typeof poId})`);
    window.selectedInvoiceId = poId;
    window.selectedInvoicePoNumber = poNumber;
    window.selectedInvoiceStatus = poStatus;

    // Update filter banners
    ['', '-step3', '-step4'].forEach(suffix => {
        const status = document.getElementById(`pn-invoice-filter-status${suffix}`);
        const clearBtn = document.getElementById(`pn-clear-invoice-filter-btn${suffix}`);
        if (status) {
            status.innerText = `Editing Invoice: P.O #${poNumber}`;
            status.classList.remove('text-slate-500');
            status.classList.add('text-maroon', 'font-bold');
        }
        if (clearBtn) {
            clearBtn.classList.remove('hidden');
        }
    });

    // For virtual Open entry (poId=0), use remaining PN items only
    if (parseInt(poId, 10) === 0) {
        window.selectedPOData = null;
        if (window.remainingPNItems && window.remainingPNItems.length > 0) {
            selectedItems = window.remainingPNItems.map(i => ({ ...i }));
        } else {
            selectedItems = originalPNItems.map(i => ({ ...i, purchase_order_id: 0, po_number: poNumber, po_item_id: null }));
        }
        console.log(`[PN] Virtual Open entry selected, using ${selectedItems.length} remaining items`);
    } else {
        // Fetch PO detail items
        try {
            const baseUrl = window.purchaseRoutes?.invoiceDetailUrl || '/hatdog/public/admin/purchase/purchase-order/detail/:poId';
            const url = baseUrl.replace(':poId', encodeURIComponent(poId));
            const res = await fetch(url);
            const result = await res.json();

            if (!result.success) throw new Error(result.message || 'Failed to load invoice items');

            window.selectedPOData = result.po || null;

            // Actual invoice rows must show their saved Purchase Order items.
            // Only the virtual Open row (poId = 0) uses remaining Purchase Note items.
            selectedItems = keepOriginalPurchaseNoteItemOrder(
                (result.items || []).map(item => ({
                    productId: normalizePurchaseNoteProductId(item.product_id),
                    product_id: normalizePurchaseNoteProductId(item.product_id),
                    code: item.product_code,
                    partNo: item.part_number || '',
                    desc: item.description || '',
                    unit: item.unit || 'PCS',
                    qty: parseFloat(item.quantity) || 0,
                    actualQty: item.actual_quantity !== null ? parseFloat(item.actual_quantity) : null,
                    cost: parseFloat(item.unit_price) || 0,
                    subTotal: parseFloat(item.subtotal) || 0,
                    actualSubTotal: item.actual_subtotal !== null ? parseFloat(item.actual_subtotal) : null,
                    currency: (result.po?.currency) || 'PHP',
                    processed: false,
                    purchase_order_id: poId,
                    po_number: poNumber,
                    po_item_id: item.id || null,
                }))
            );

            // Fallback: if PO has no items, keep the modal usable with original PN items.
            if (selectedItems.length === 0 && originalPNItems.length > 0) {
                selectedItems = originalPNItems.map(i => ({ ...i, purchase_order_id: poId, po_number: poNumber, po_item_id: null }));
            }
            window.selectedInvoiceOriginalItems = selectedItems.map(item => ({ ...item }));
        } catch (e) {
            console.error('Failed to load PO items:', e);
            window.selectedInvoiceId = null;
            window.selectedInvoicePoNumber = null;
            document.querySelectorAll('input[name="pn_selected_invoice"]').forEach(r => { r.checked = false; });
            if (editingNoteId && originalPNItems.length > 0) {
                selectedItems = originalPNItems.map(item => ({ ...item }));
            }
            clearInvoiceFilter();
            alert('Failed to load invoice items. Please try again.');
            return;
        }
    }

    // Update selection indicator badges
    document.querySelectorAll('#pn-invoices-tbody tr').forEach(tr => {
        const radio = tr.querySelector('input[name="pn_selected_invoice"]');
        const badge = tr.querySelector('.selection-badge');
        if (!radio || !badge) return;
        const isSelected = radio.checked;
        badge.className = `inline-flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-bold rounded-lg ${isSelected ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-slate-100 text-slate-400 border border-slate-200'}`;
        badge.innerHTML = `<i data-lucide="${isSelected ? 'check-circle' : 'circle'}" class="w-3 h-3"></i><span>${isSelected ? 'Selected' : 'Select'}</span>`;
    });
    if (typeof lucide !== 'undefined') lucide.createIcons();

    renderPOItemsTable();
    updatePOTotals();
};

window.clearInvoiceFilter = function() {
    window.selectedInvoiceId = null;
    window.selectedInvoicePoNumber = null;
    window.selectedInvoiceStatus = null;
    window.selectedPOData = null;
    window.remainingPNItems = null;
    window.selectedInvoiceOriginalItems = [];
    sessionStorage.removeItem('po_edit_changes');
    document.querySelectorAll('input[name="pn_selected_invoice"]').forEach(radio => {
        radio.checked = false;
    });

    // Restore original PN items when in edit mode
    if (editingNoteId && originalPNItems.length > 0) {
        selectedItems = originalPNItems.map(item => ({ ...item }));
    }

    // Update filter banners
    ['', '-step3', '-step4'].forEach(suffix => {
        const status = document.getElementById(`pn-invoice-filter-status${suffix}`);
        const clearBtn = document.getElementById(`pn-clear-invoice-filter-btn${suffix}`);
        if (status) {
            status.innerText = 'All Invoices (No Filter)';
            status.classList.remove('text-maroon', 'font-bold');
            status.classList.add('text-slate-500');
        }
        if (clearBtn) {
            clearBtn.classList.add('hidden');
        }
    });

    // Update selection badges
    document.querySelectorAll('.selection-badge').forEach(badge => {
        badge.className = 'selection-badge inline-flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-bold rounded-lg bg-slate-100 text-slate-400 border border-slate-200';
        badge.innerHTML = '<i data-lucide="circle" class="w-3 h-3"></i><span>Select</span>';
    });
    if (typeof lucide !== 'undefined') lucide.createIcons();

    // Hide fallback warning banner
    const fbBanner = document.getElementById('pn-po-empty-fallback');
    if (fbBanner) fbBanner.classList.add('hidden');

    renderPOItemsTable();
    updatePOTotals();
};

/**
 * Supplier Selection Logic
 */
window.selectSupplier = function(code, name) {
    const triggerInput = document.getElementById('supplier-search-input-trigger');
    const idInput = document.getElementById('selected-supplier-id');
    
    if (triggerInput) triggerInput.value = `${code} - ${name}`;
    if (idInput) idInput.value = code;

    refreshSupplierItemConflicts(code);
    toggleModal('supplier-search-modal', false);

    // In add mode, check for forgotten partial items
    if (!editingNoteId) {
        checkForgottenPartialItems(code);
    }
};

async function refreshSupplierItemConflicts(supplierCode) {
    supplierItemConflicts = {};
    const code = supplierCode || document.getElementById('selected-supplier-id')?.value;
    if (!code || !window.purchaseRoutes?.supplierItemConflicts) return;

    try {
        let url = `${window.purchaseRoutes.supplierItemConflicts}?supplier_code=${encodeURIComponent(code)}`;
        if (editingNoteId) {
            url += `&exclude_note_id=${encodeURIComponent(editingNoteId)}`;
        }
        const res = await fetch(url);
        const data = await res.json();
        if (data.success && data.conflicts) {
            supplierItemConflicts = data.conflicts;
        }
    } catch (e) {
        console.error('Error loading supplier item conflicts:', e);
    }
}

/**
 * Check for forgotten partial items for a supplier
 */
window.checkForgottenPartialItems = async function(supplierCode) {
    const url = (window.purchaseRoutes?.forgottenPartialItems || '').replace(':supplierCode', encodeURIComponent(supplierCode));
    if (!url) return;
    try {
        const res = await fetch(url);
        const data = await res.json();
        if (data.success && data.has_items) {
            forgottenPartialData = data.partial_notes;
            wizardShowPartialNotesStep = true;
            renderForgottenPartialNotes(data.partial_notes);
            const step2Wrapper = document.getElementById('pn-step-wrapper-2');
            const step2Sep1 = document.getElementById('pn-step-sep-1');
            const step2Sep2 = document.getElementById('pn-step-sep-2');
            const step2Content = document.getElementById('pn-wizard-step-2');
            const step2Indicator = document.getElementById('pn-step-indicator-2');
            const step2Label = document.getElementById('pn-step-label-2');
            const linkedSection = document.getElementById('pn-linked-partial-notes-section');
            const invoicesSection = document.getElementById('pn-invoices-section');
            if (step2Wrapper) step2Wrapper.classList.remove('hidden');
            if (step2Sep1) step2Sep1.classList.remove('hidden');
            if (step2Sep2) step2Sep2.classList.remove('hidden');
            if (step2Indicator) step2Indicator.classList.remove('hidden');
            if (step2Label) {
                step2Label.classList.remove('text-white/40');
                step2Label.classList.add('text-white/70');
                step2Label.textContent = 'Linked Partial Notes';
            }
            if (linkedSection) linkedSection.classList.remove('hidden');
            if (invoicesSection) invoicesSection.classList.add('hidden');
            const step1NextBtn = document.getElementById('pn-step1-next-btn');
            if (step1NextBtn) {
                step1NextBtn.onclick = function() { goToStep(2); };
            }
            const step3BackBtn = document.getElementById('pn-step3-back-btn');
            const step3BackText = document.getElementById('pn-step3-back-text');
            if (step3BackBtn) step3BackBtn.onclick = function() { goToStep(2); };
            if (step3BackText) step3BackText.textContent = 'Back to Linked Partial Notes';
            const step3Label = document.getElementById('pn-step-label-3');
            const step4Label = document.getElementById('pn-step-label-4');
            if (step3Label) step3Label.textContent = 'Items';
            if (step4Label) step4Label.textContent = 'Review';
        } else {
            wizardShowPartialNotesStep = false;
            forgottenPartialData = null;
            const step2Wrapper = document.getElementById('pn-step-wrapper-2');
            const step2Sep1 = document.getElementById('pn-step-sep-1');
            const step2Sep2 = document.getElementById('pn-step-sep-2');
            if (step2Wrapper) step2Wrapper.classList.add('hidden');
            if (step2Sep1) step2Sep1.classList.add('hidden');
            if (step2Sep2) step2Sep2.classList.add('hidden');
            const linkedSection = document.getElementById('pn-linked-partial-notes-section');
            if (linkedSection) linkedSection.classList.add('hidden');
            const invoicesSection = document.getElementById('pn-invoices-section');
            if (invoicesSection) invoicesSection.classList.remove('hidden');
            const step1NextBtn = document.getElementById('pn-step1-next-btn');
            if (step1NextBtn) {
                step1NextBtn.onclick = function() { goToStep(3); };
            }
            const step3BackBtn = document.getElementById('pn-step3-back-btn');
            const step3BackText = document.getElementById('pn-step3-back-text');
            if (step3BackBtn) step3BackBtn.onclick = function() { goToStep(1); };
            if (step3BackText) step3BackText.textContent = 'Back to Basic Info';
        }
    } catch (e) {
        console.error('Error checking forgotten partial items:', e);
        wizardShowPartialNotesStep = false;
        forgottenPartialData = null;
    }
};

window.renderForgottenPartialNotes = function(partialNotes) {
    const container = document.getElementById('pn-partial-notes-container');
    if (!container) return;
    if (!partialNotes || partialNotes.length === 0) {
        container.innerHTML = '<div class="text-center text-slate-400 italic text-xs py-8">No forgotten partial items found.</div>';
        return;
    }
    let html = '';
    partialNotes.forEach(note => {
        html += `
            <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="bg-slate-50 px-4 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-3 text-xs">
                        <span class="font-bold text-maroon font-mono">${note.purchase_note_number}</span>
                        <span class="text-slate-400">|</span>
                        <span class="text-slate-500">${note.date || '---'}</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">${note.status || 'Partial'}</span>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="p-2 px-3 text-[10px] font-bold text-slate-500 uppercase w-10">
                                    <input type="checkbox" onchange="toggleSelectAllPartialItems(this, ${note.id})" class="w-3.5 h-3.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                </th>
                                <th class="p-2 px-3 text-[10px] font-bold text-slate-500 uppercase">Item Code</th>
                                <th class="p-2 px-3 text-[10px] font-bold text-slate-500 uppercase">Description</th>
                                <th class="p-2 px-3 text-[10px] font-bold text-slate-500 uppercase text-center">Remaining QTY</th>
                                <th class="p-2 px-3 text-[10px] font-bold text-slate-500 uppercase text-right">Unit Price</th>
                                <th class="p-2 px-3 text-[10px] font-bold text-slate-500 uppercase text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            ${note.items.map(item => `
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="p-2 px-3 text-center">
                                        <input type="checkbox" class="partial-item-checkbox w-3.5 h-3.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                            data-note-id="${note.id}"
                                            data-item-id="${item.id}"
                                            data-product-id="${item.product_id}"
                                            data-product-code="${item.product_code}"
                                            data-description="${item.description}"
                                            data-unit-price="${item.unit_price}"
                                            data-remaining-qty="${item.remaining_quantity}"
                                            onchange="onPartialItemCheckChange()">
                                    </td>
                                    <td class="p-2 px-3 font-mono text-slate-700">${item.product_code}</td>
                                    <td class="p-2 px-3 text-slate-600">${item.description || ''}</td>
                                    <td class="p-2 px-3 text-center font-bold text-slate-700">${item.remaining_quantity}</td>
                                    <td class="p-2 px-3 text-right text-slate-700">${parseFloat(item.unit_price).toFixed(2)}</td>
                                    <td class="p-2 px-3 text-right text-slate-700 font-medium">${parseFloat(item.remaining_amount).toFixed(2)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    });
    container.innerHTML = html;
    if (typeof lucide !== 'undefined') lucide.createIcons();
};

window.toggleSelectAllPartialItems = function(checkbox, noteId) {
    const checkboxes = document.querySelectorAll(`#pn-partial-notes-container .partial-item-checkbox[data-note-id="${noteId}"]`);
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
};

window.onPartialItemCheckChange = function() {
    document.querySelectorAll('#pn-partial-notes-container .bg-white.rounded-xl').forEach(noteDiv => {
        const allCheckbox = noteDiv.querySelector('thead input[type="checkbox"]');
        const itemCheckboxes = noteDiv.querySelectorAll('.partial-item-checkbox');
        if (allCheckbox && itemCheckboxes.length > 0) {
            allCheckbox.checked = Array.from(itemCheckboxes).every(cb => cb.checked);
        }
    });
};

window.transferSelectedPartialItems = function() {
    const checkboxes = document.querySelectorAll('.partial-item-checkbox:checked');
    if (checkboxes.length === 0) {
        alert('Please select at least one item to transfer.');
        return;
    }
    const alreadySelectedCodes = new Set(transferredPartialItems.map(i => i.product_code));
    let addedCount = 0;
    checkboxes.forEach(cb => {
        const productCode = cb.getAttribute('data-product-code');
        if (alreadySelectedCodes.has(productCode)) return;
        const item = {
            source_purchase_note_id: parseInt(cb.getAttribute('data-note-id')),
            source_purchase_note_item_id: parseInt(cb.getAttribute('data-item-id')),
            product_id: parseInt(cb.getAttribute('data-product-id')),
            product_code: productCode,
            description: cb.getAttribute('data-description'),
            unit_price: parseFloat(cb.getAttribute('data-unit-price')),
            remaining_quantity: parseInt(cb.getAttribute('data-remaining-qty')),
        };
        transferredPartialItems.push(item);
        alreadySelectedCodes.add(productCode);
        const existing = selectedItems.find(si => si.code === productCode);
        if (!existing) {
            selectedItems.push({
                productId: item.product_id,
                product_id: item.product_id,
                code: item.product_code,
                partNo: '',
                desc: item.description,
                unit: 'PCS',
                qty: item.remaining_quantity,
                cost: item.unit_price,
                subTotal: item.remaining_quantity * item.unit_price,
            currency: item.currency_code || 'PHP',
                _transferredFrom: {
                    source_purchase_note_id: item.source_purchase_note_id,
                    source_purchase_note_item_id: item.source_purchase_note_item_id,
                },
            });
        }
        addedCount++;
        cb.checked = false;
    });
    if (addedCount === 0) {
        alert('Selected items have already been transferred.');
        return;
    }
    renderPOItemsTable();
    updatePOTotals();
    checkboxes.forEach(cb => {
        const code = cb.getAttribute('data-product-code');
        if (alreadySelectedCodes.has(code)) {
            cb.disabled = true;
            cb.closest('tr')?.classList.add('opacity-50');
        }
    });
    alert(`${addedCount} item(s) transferred successfully. Click "Next" to review them in the Items step.`);
};

function getItemConflictNotes(productId) {
    const key = String(productId);
    const notes = supplierItemConflicts[key] || supplierItemConflicts[productId];
    return Array.isArray(notes) ? notes : null;
}

window.showItemExistsModal = function(productCode, description, conflictNotes, onContinue) {
    const modal = document.getElementById('item-exists-modal');
    const messageEl = document.getElementById('item-exists-message');
    const listEl = document.getElementById('item-exists-notes-list');
    const goBtn = document.getElementById('item-exists-go-btn');
    const continueBtn = document.getElementById('item-exists-continue-btn');
    const closeBtn = document.getElementById('item-exists-close-btn');
    if (!modal || !messageEl || !listEl) return;

    pendingConflictNote = conflictNotes && conflictNotes.length ? conflictNotes[0] : null;

    const desc = description || productCode;
    messageEl.textContent = `The selected item "${desc}" (${productCode}) already exists on a different Open or Partial purchase note for this supplier.`;

    listEl.innerHTML = conflictNotes.map(note => `
        <div class="p-3 bg-red-50 rounded-xl border border-red-100">
            <p class="text-xs font-bold text-maroon font-mono">PN #${note.pn_number}</p>
            <p class="text-[10px] text-slate-500 uppercase mt-0.5">${note.status}</p>
        </div>
    `).join('');

    if (goBtn) {
        goBtn.disabled = !pendingConflictNote;
        goBtn.classList.toggle('opacity-50', !pendingConflictNote);
    }

    if (continueBtn) {
        continueBtn.disabled = false;
        continueBtn.onclick = null;
        if (typeof onContinue === 'function') {
            continueBtn.classList.remove('hidden');
            continueBtn.onclick = function() {
                continueBtn.disabled = true;
                continueBtn.onclick = null;
                toggleModal('item-exists-modal', false);
                onContinue();
            };
        } else {
            continueBtn.classList.add('hidden');
            continueBtn.onclick = null;
        }
    }

    if (closeBtn) {
        if (typeof onContinue === 'function') {
            closeBtn.classList.add('hidden');
        } else {
            closeBtn.classList.remove('hidden');
        }
    }

    toggleModal('item-exists-modal', true);
    if (typeof lucide !== 'undefined') lucide.createIcons();
};

window.goToNoteFromExistsModal = function() {
    if (!pendingConflictNote) return;
    goToExistingPurchaseNote(pendingConflictNote.note_id, pendingConflictNote.pn_number);
};

window.goToExistingPurchaseNote = function(noteId, pnNumber) {
    toggleModal('item-exists-modal', false);
    toggleModal('item-search-modal', false);
    closePurchaseNoteWizard();

    pendingTableHighlightNoteId = noteId;
    searchFilters.poNumber = pnNumber;

    if (currentTab !== 'active') {
        currentTab = 'active';
        const activeBtn = document.getElementById('tab-active');
        const closedBtn = document.getElementById('tab-closed');
        if (activeBtn) {
            activeBtn.className = 'px-6 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all duration-200 bg-maroon text-white shadow-md';
        }
        if (closedBtn) {
            closedBtn.className = 'px-6 py-2.5 rounded-xl text-xs font-bold uppercase tracking-widest transition-all duration-200 text-slate-500 hover:bg-slate-50';
        }
    }

    const poInput = document.querySelector('#page-purchase-note-root .column-search-input[data-col="poNumber"]');
    if (poInput) poInput.value = pnNumber;

    const tableSection = document.getElementById('purchase-note-tbody')?.closest('.bg-white');
    if (tableSection) {
        tableSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    fetchPurchaseNotesTable(1);
};

function highlightPurchaseNoteRow(noteId) {
    const row = document.querySelector(`#purchase-note-tbody tr[data-note-id="${noteId}"]`);
    if (!row) return;

    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
    row.classList.remove('pn-row-highlight-blink');
    void row.offsetWidth;
    row.classList.add('pn-row-highlight-blink');

    setTimeout(() => row.classList.remove('pn-row-highlight-blink'), 5000);
}

async function canAddItemToNewNote(productId, productCode, description) {
    const supplierCode = document.getElementById('selected-supplier-id')?.value;
    if (!supplierCode) {
        alert('Please select a supplier first.');
        return false;
    }

    await refreshSupplierItemConflicts(supplierCode);
    const conflictNotes = getItemConflictNotes(productId);
    if (conflictNotes && conflictNotes.length > 0) {
        showItemExistsModal(productCode, description, conflictNotes, function() {
            const pending = window._pendingPOItem;
            if (!pending) return;
            if (selectedItemAlreadyExists(pending)) {
                window._pendingPOItem = null;
                return;
            }
            const pendingProductId = getPurchaseNoteProductId(pending);
            if (pendingProductId === null) {
                console.error('[PN] Pending item lost its product ID.', pending);
                alert('The selected item lost its product ID. Please add it again.');
                window._pendingPOItem = null;
                return;
            }
            const newItem = {
                productId: pendingProductId,
                product_id: pendingProductId,
                code: pending.code,
                partNo: pending.partNo,
                desc: pending.desc,
                unit: pending.unit,
                qty: 1,
                cost: pending.cost,
                subTotal: pending.cost,
                currency: 'PHP',
                conversion_rate: null,
                unit_price_converted: null,
                total_price_converted: null,
                po_number: window.selectedInvoicePoNumber || null,
                purchase_order_id: window.selectedInvoiceId !== null && window.selectedInvoiceId !== undefined ? window.selectedInvoiceId : null,
            };
            selectedItems.push(newItem);
            renderPOItemsTable();
            updatePOTotals();
            toggleModal('item-search-modal', false);
            window._pendingPOItem = null;
        });
        return false;
    }
    return true;
}

let _supplierSearchTimer = null;

window.openSupplierSearchModal = function() {
    supplierSearchState = { page: 1, q: '', code: '', name: '', contact: '', contactPerson: '', address: '' };
    const searchInput = document.getElementById('supplier-table-search');
    if (searchInput) searchInput.value = '';
    document.querySelectorAll('#supplier-search-modal .column-search-input').forEach(input => input.value = '');
    toggleModal('supplier-search-modal', true);
    fetchSuppliers(1);
};

async function fetchSuppliers(page) {
    if (page !== undefined) supplierSearchState.page = page;
    try {
        const params = new URLSearchParams();
        Object.entries(supplierSearchState).forEach(([key, value]) => {
            if (value !== undefined && value !== null && String(value).trim() !== '') {
                params.set(key, value);
            }
        });
        params.set('page', String(supplierSearchState.page || 1));
        params.set('perPage', '50');

        const url = `${(window.purchaseRoutes?.searchSuppliers || '/admin/purchase/search-suppliers')}?${params.toString()}`;
        const res = await fetch(url);
        const result = await res.json();
        if (!res.ok || !result.success) {
            throw new Error(result.message || 'Failed to load suppliers.');
        }
        renderSupplierResults(result);
        renderSupplierPagination(result);
    } catch (e) {
        console.error('Error fetching suppliers:', e);
        renderSupplierResults({ data: [] });
        renderSupplierPagination({ total: 0, current_page: 1, last_page: 1, from: 0, to: 0 });
    }
}

function renderSupplierResults(result) {
    const tbody = document.getElementById('supplier-search-tbody');
    if (!tbody) return;
    const suppliers = Array.isArray(result?.data) ? result.data : [];
    if (!suppliers.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="p-6 text-center text-slate-400 italic">No suppliers found.</td></tr>';
        return;
    }
    tbody.innerHTML = suppliers.map(s => `
        <tr onclick="selectSupplier('${s.supplier_code}', '${s.name.replace(/'/g, "\\'")}')" class="hover:bg-slate-50 cursor-pointer transition-colors">
            <td class="p-3 px-4 font-bold text-maroon">${s.supplier_code}</td>
            <td class="p-3 px-4">${s.name}</td>
            <td class="p-3 px-4">${s.contact_number || '---'}</td>
            <td class="p-3 px-4">${s.contact_person || '---'}</td>
            <td class="p-3 px-4">${s.address || '---'}</td>
        </tr>
    `).join('');
}

function renderSupplierPagination(result) {
    const container = document.getElementById('supplier-pagination-container');
    const fromEl = document.getElementById('supplier-pagination-from');
    const toEl = document.getElementById('supplier-pagination-to');
    const totalEl = document.getElementById('supplier-pagination-total');
    const currentPage = Number(result?.current_page || 1) || 1;
    const lastPage = Number(result?.last_page || 1) || 1;

    if (fromEl) fromEl.innerText = result?.from || 0;
    if (toEl) toEl.innerText = result?.to || 0;
    if (totalEl) totalEl.innerText = result?.total || 0;
    if (!container) return;

    let html = '';
    html += `<button onclick="fetchSuppliers(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''} class="p-2 rounded-xl border border-slate-200 text-slate-400 hover:text-maroon hover:border-maroon disabled:opacity-50 disabled:cursor-not-allowed transition-all"><i data-lucide="chevron-left" class="w-4 h-4"></i></button>`;

    for (let i = 1; i <= lastPage; i++) {
        if (i === 1 || i === lastPage || (i >= currentPage - 2 && i <= currentPage + 2)) {
            html += `<button onclick="fetchSuppliers(${i})" class="px-4 py-2 rounded-xl text-xs font-bold transition-all ${i === currentPage ? 'bg-maroon text-white shadow-md shadow-maroon/20' : 'text-slate-500 hover:bg-slate-100 border border-transparent'}">${i}</button>`;
        } else if (i === currentPage - 3 || i === currentPage + 3) {
            html += '<span class="text-slate-300 px-1">...</span>';
        }
    }

    html += `<button onclick="fetchSuppliers(${currentPage + 1})" ${currentPage === lastPage ? 'disabled' : ''} class="p-2 rounded-xl border border-slate-200 text-slate-400 hover:text-maroon hover:border-maroon disabled:opacity-50 disabled:cursor-not-allowed transition-all"><i data-lucide="chevron-right" class="w-4 h-4"></i></button>`;
    container.innerHTML = html;
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

window.filterSupplierTable = function() {
    const input = document.getElementById('supplier-table-search');
    const q = input ? input.value : '';
    if (_supplierSearchTimer) clearTimeout(_supplierSearchTimer);
    _supplierSearchTimer = setTimeout(() => {
        supplierSearchState.q = q;
        supplierSearchState.page = 1;
        fetchSuppliers(1);
    }, 300);
};

/**
 * Item Selection Logic
 */
window.addItemToPO = async function(productId, code, partNo, desc, unit, cost) {
    const resolvedProductId = normalizePurchaseNoteProductId(productId);
    if (resolvedProductId === null) {
        console.error('[PN] Refusing to add an item without a valid masterlist product ID.', {
            productId,
            code,
            partNo,
            desc,
        });
        alert(`Unable to add ${String(code || 'this item').trim()}: its product ID was not returned by the server. Refresh the page and try again.`);
        return;
    }

    const purchaseOrderId = currentSelectedInvoiceId();
    const candidate = { productId: resolvedProductId, code, purchase_order_id: purchaseOrderId };
    if (selectedItemAlreadyExists(candidate)) {
        alert('Item already added to the list.');
        return;
    }

    window._pendingPOItem = { productId: resolvedProductId, product_id: resolvedProductId, code, partNo, desc, unit, cost, purchase_order_id: purchaseOrderId };

    const allowed = await canAddItemToNewNote(resolvedProductId, code, desc);
    if (!allowed) return;

    if (selectedItemAlreadyExists(window._pendingPOItem)) {
        window._pendingPOItem = null;
        return;
    }

    const newItem = {
        productId: resolvedProductId,
        product_id: resolvedProductId,
        code: code,
        partNo: partNo,
        desc: desc,
        unit: unit,
        qty: 1,
        cost: cost,
        subTotal: cost,
        currency: 'PHP',
        conversion_rate: null,
        unit_price_converted: null,
        total_price_converted: null,
        po_number: window.selectedInvoicePoNumber || null,
        purchase_order_id: purchaseOrderId,
    };
    console.log(`[PN] addItemToPO: newItem code=${code}, product_id=${newItem.productId}, purchase_order_id=${newItem.purchase_order_id}, selectedInvoiceId=${window.selectedInvoiceId}`);

    selectedItems.push(newItem);
    renderPOItemsTable();
    updatePOTotals();
    toggleModal('item-search-modal', false);
    window._pendingPOItem = null;
};

/**
 * Bulk Selection Logic
 */
window.toggleSelectAllItems = function(isChecked) {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    checkboxes.forEach(cb => {
        if (cb.closest('tr').style.display !== 'none') {
            cb.checked = isChecked;
            const data = JSON.parse(cb.getAttribute('data-item'));
            const productId = getPurchaseNoteProductId(data);
            if (productId === null) return;
            data.productId = productId;
            data.product_id = productId;
            if (isChecked) {
                itemSearchSelection.set(productId, data);
            } else {
                itemSearchSelection.delete(productId);
            }
        }
    });
};

window.addSelectedItems = async function() {
    if (itemSearchSelection.size === 0) {
        alert('Please select at least one item.');
        return;
    }

    const supplierCode = document.getElementById('selected-supplier-id')?.value;
    if (!supplierCode) {
        alert('Please select a supplier first.');
        return;
    }
    await refreshSupplierItemConflicts(supplierCode);

    let addedCount = 0;
    const purchaseOrderId = currentSelectedInvoiceId();

    for (const itemData of itemSearchSelection.values()) {
        const productId = getPurchaseNoteProductId(itemData);
        if (productId === null) {
            console.error('[PN] Skipping selected item without product ID.', itemData);
            continue;
        }
        const candidate = {
            productId: productId,
            code: itemData.code,
            purchase_order_id: purchaseOrderId,
        };
        if (selectedItemAlreadyExists(candidate)) continue;

        selectedItems.push({
            productId: productId,
            product_id: productId,
            code: itemData.code,
            partNo: itemData.partNo,
            desc: itemData.desc,
            unit: itemData.unit,
            qty: 1,
            cost: itemData.cost,
            subTotal: itemData.cost,
            currency: 'PHP',
            conversion_rate: null,
            unit_price_converted: null,
            total_price_converted: null,
            po_number: window.selectedInvoicePoNumber || null,
            purchase_order_id: purchaseOrderId,
        });
        addedCount++;
    }

    if (addedCount > 0) {
        renderPOItemsTable();
        updatePOTotals();
        const selectAll = document.getElementById('select-all-items');
        if (selectAll) selectAll.checked = false;
        toggleModal('item-search-modal', false);
        itemSearchSelection.clear();
        showSuccess(`${addedCount} item(s) added to the list.`);
    } else {
        alert('Selected items are already in the list.');
    }
};

/**
 * Per-Column Filtering Logic
 */
window.filterSupplierColumn = function(col, value) {
    if (_supplierSearchTimer) clearTimeout(_supplierSearchTimer);
    _supplierSearchTimer = setTimeout(() => {
        supplierSearchState[col] = value;
        supplierSearchState.page = 1;
        fetchSuppliers(1);
    }, 300);
};

window.filterItemTableByCol = function(colIndex, value) {
    const tbody = document.getElementById('item-search-tbody');
    const rows = tbody.getElementsByTagName('tr');
    const filter = value.toLowerCase();

    for (let i = 0; i < rows.length; i++) {
        const cell = rows[i].getElementsByTagName('td')[colIndex];
        if (cell) {
            const text = cell.textContent.toLowerCase();
            rows[i].style.display = text.includes(filter) ? "" : "none";
        }
    }
};

function normalizePurchaseNoteItemSearchValue(value) {
    return String(value ?? '').trim().toLowerCase();
}

function purchaseNoteItemSearchText(item, column, hasActualQty) {
    const value = (() => {
        switch (column) {
            case 'itemCode':
                return item?.code ?? '';
            case 'partNo':
                return item?.partNo ?? '';
            case 'description':
                return item?.desc ?? '';
            case 'unit':
                return item?.unit ?? '';
            case 'qty':
                return item?.qty ?? '';
            case 'actualQty':
                return hasActualQty ? (item?.actualQty ?? '') : '';
            case 'cost':
                return `${item?.currency ?? 'PHP'} ${item?.cost ?? ''}`;
            case 'subTotal':
                return hasActualQty && item?.actualSubTotal !== null && item?.actualSubTotal !== undefined
                    ? item.actualSubTotal
                    : (item?.subTotal ?? '');
            case 'action':
                return item?.processed ? 'processed protected delete remove' : 'delete remove';
            default:
                return '';
        }
    })();

    return normalizePurchaseNoteItemSearchValue(value);
}

function purchaseNoteItemMatchesColumnFilters(item, hasActualQty) {
    return Object.entries(purchaseNoteItemColumnFilters).every(([column, rawFilter]) => {
        const filter = normalizePurchaseNoteItemSearchValue(rawFilter);
        if (!filter) return true;
        return purchaseNoteItemSearchText(item, column, hasActualQty).includes(filter);
    });
}

window.filterPurchaseNoteItems = function(column, value) {
    if (!Object.prototype.hasOwnProperty.call(purchaseNoteItemColumnFilters, column)) return;
    purchaseNoteItemColumnFilters[column] = value ?? '';
    renderPOItemsTable();
};

window.resetPurchaseNoteItemColumnFilters = function() {
    Object.keys(purchaseNoteItemColumnFilters).forEach(column => {
        purchaseNoteItemColumnFilters[column] = '';
    });
    document.querySelectorAll('[data-pn-item-search]').forEach(input => {
        input.value = '';
    });
};

window.renderPOItemsTable = function() {
    const tbody = document.getElementById('po-items-tbody');
    if (!tbody) return;

    // Filter items based on active invoice filter first. Column searches are visual-only
    // and never remove/change selectedItems or the values that will be saved.
    let invoiceItems = selectedItems;
    if (window.selectedInvoiceId !== null && window.selectedInvoiceId !== undefined) {
        const filterIdInt = parseInt(window.selectedInvoiceId, 10);
        invoiceItems = selectedItems.filter(item => {
            const itemIdInt = parseInt(item.purchase_order_id, 10);
            return !isNaN(itemIdInt) && !isNaN(filterIdInt) && itemIdInt === filterIdInt;
        });
    }

    // ACTUAL QTY belongs to Edit mode/processed data. Keep both header rows aligned.
    const hasActualQty = invoiceItems.some(item => item.actualQty !== null && item.actualQty !== undefined);
    const actualQtyHeader = document.getElementById('actual-qty-header');
    const actualQtySearchHeader = document.getElementById('actual-qty-search-header');
    [actualQtyHeader, actualQtySearchHeader].forEach(header => {
        if (!header) return;
        header.classList.toggle('hidden', !hasActualQty);
    });

    const columnCount = hasActualQty ? 10 : 9;

    if (!selectedItems.length) {
        tbody.innerHTML = `<tr><td colspan="${columnCount}" class="p-8 text-center text-slate-400 italic">No items yet. Use Add Items to add products.</td></tr>`;
        return;
    }

    if (!invoiceItems.length) {
        tbody.innerHTML = `<tr><td colspan="${columnCount}" class="p-8 text-center text-slate-400 italic">No items found for this invoice filter.</td></tr>`;
        return;
    }

    const itemsToRender = invoiceItems.filter(item => purchaseNoteItemMatchesColumnFilters(item, hasActualQty));
    if (!itemsToRender.length) {
        tbody.innerHTML = `<tr><td colspan="${columnCount}" class="p-8 text-center text-slate-400 italic">No Purchase Note item matches the column search.</td></tr>`;
        return;
    }

    tbody.innerHTML = '';
    itemsToRender.forEach((item) => {
        const index = selectedItems.indexOf(item);
        if (!item.currency) item.currency = 'PHP';
        
        const currencySymbols = {
            'PHP': '₱',
            'TWD': 'NT$',
            'USD': '$'
        };

        const row = document.createElement('tr');
        row.className = "hover:bg-slate-50/50 transition-colors";
        
        // Build the additional actual quantity column if needed
        let actualQtyCell = '';
        if (hasActualQty) {
            actualQtyCell = `
                <td class="p-3 px-4 text-center">
                    <span class="font-semibold text-blue-600">${item.actualQty !== null ? item.actualQty : '---'}</span>
                </td>
            `;
        }
        
        row.innerHTML = `
            <td class="p-3 px-4 font-bold text-slate-700">${item.code}</td>
            <td class="p-3 px-4 text-slate-600">${item.partNo}</td>
            <td class="p-3 px-4 text-slate-600">${item.desc}</td>
            <td class="p-3 px-4">
                <input
                    type="text"
                    value="${escapeHtml(item.unit || '')}"
                    maxlength="50"
                    oninput="updateItemUnit(${index}, this.value)"
                    class="w-full min-w-[80px] px-2 py-1 border border-slate-200 rounded text-center uppercase focus:ring-1 focus:ring-maroon focus:border-maroon outline-none bg-white"
                    placeholder="UNIT"
                    autocomplete="off"
                >
            </td>
            <td class="p-3 px-4">
                <input type="number" value="${item.qty}" min="${editingNoteId ? 1 : 0}" oninput="updateItemQty(${index}, this.value)" class="w-full px-2 py-1 border border-slate-200 rounded text-center focus:ring-1 focus:ring-maroon focus:border-maroon outline-none bg-white">
            </td>
            ${actualQtyCell}
            <td class="p-3 px-4">
                <div class="flex items-center space-x-1">
                    <select onchange="updateItemCurrency(${index}, this.value)" class="px-1 py-1 border border-slate-200 rounded text-[10px] focus:ring-1 focus:ring-maroon outline-none bg-slate-50">
                        <option value="PHP" ${item.currency === 'PHP' ? 'selected' : ''}>PHP</option>
                        <option value="TWD" ${item.currency === 'TWD' ? 'selected' : ''}>TWD</option>
                        <option value="USD" ${item.currency === 'USD' ? 'selected' : ''}>USD</option>
                    </select>
                    <div class="flex items-center flex-1 border border-slate-200 rounded bg-white px-2 focus-within:ring-1 focus-within:ring-maroon min-w-[120px]">
                        <span class="text-[10px] text-slate-400 font-bold mr-1 pointer-events-none">${currencySymbols[item.currency]}</span>
                        <input type="number" value="${item.cost}" min="0" step="0.01" oninput="updateItemCost(${index}, this.value)" class="w-full py-1 text-right outline-none text-xs bg-white" onclick="this.select()">
                    </div>
                </div>
            </td>
            <td class="p-3 px-4 text-right font-bold text-maroon" id="subtotal-${index}">${currencySymbols[item.currency]} ${(hasActualQty && item.actualSubTotal ? item.actualSubTotal : item.subTotal).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
            <td class="p-3 px-4 text-center">
                ${item.processed
                    ? `<button onclick="confirmDeleteProcessedItem(${index})" class="p-1.5 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all" title="Item already processed — requires password to delete">
                        <i data-lucide="shield-off" class="w-4 h-4"></i>
                       </button>`
                    : `<button onclick="removeItemFromPO(${index})" class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all" title="Delete item">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                       </button>`
                }
            </td>
        `;
        tbody.appendChild(row);
    });

    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
};

window.updateItemCurrency = function(index, currency) {
    selectedItems[index].currency = currency;
    renderPOItemsTable();
    updatePOTotals();
};

window.updateItemUnit = function(index, unit) {
    if (!selectedItems[index]) return;

    // Keep the user's editable unit in the selected item object so it survives
    // filtering, Step navigation, Add/Edit saves, and Purchase Order redirects.
    selectedItems[index].unit = String(unit ?? '').slice(0, 50);
};

window.updateItemQty = function(index, qty) {
    const rawQty = String(qty ?? '').trim();
    selectedItems[index].qty = rawQty === '' ? '' : Number(rawQty);
    const numericQty = Number.isFinite(selectedItems[index].qty) ? selectedItems[index].qty : 0;
    selectedItems[index].subTotal = numericQty * selectedItems[index].cost;
    
    // Update only the subtotal cell and grand total to avoid losing focus
    const subtotalEl = document.getElementById(`subtotal-${index}`);
    if (subtotalEl) {
        const symbol = { 'PHP': '₱', 'TWD': 'NT$', 'USD': '$' }[selectedItems[index].currency || 'PHP'];
        subtotalEl.innerText = `${symbol} ${selectedItems[index].subTotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
    }
    updatePOTotals();
};

window.updateItemCost = function(index, cost) {
    selectedItems[index].cost = parseFloat(cost) || 0;
    selectedItems[index].subTotal = selectedItems[index].qty * selectedItems[index].cost;
    
    // Update only the subtotal cell and grand total to avoid losing focus
    const subtotalEl = document.getElementById(`subtotal-${index}`);
    if (subtotalEl) {
        const symbol = { 'PHP': '₱', 'TWD': 'NT$', 'USD': '$' }[selectedItems[index].currency || 'PHP'];
        subtotalEl.innerText = `${symbol} ${selectedItems[index].subTotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
    }
    updatePOTotals();
};

window.removeItemFromPO = function(index) {
    selectedItems.splice(index, 1);
    renderPOItemsTable();
    updatePOTotals();
};

window.confirmDeleteProcessedItem = function(index) {
    document.getElementById('password-delete-index').value = index;
    document.getElementById('password-error-msg').classList.add('hidden');
    const container = document.getElementById('password-input-container');
    if (container) {
        container.innerHTML = '<input type="password" id="password-input" name="password" placeholder="Enter your password" autocomplete="new-password" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all text-slate-800">';
    }
    toggleModal('password-modal', true);
    setTimeout(() => document.getElementById('password-input')?.focus(), 200);
};

window.verifyPasswordDeleteV2 = async function() {
    const password = document.getElementById('password-input')?.value;
    if (!password) return;

    const btn = document.querySelector('#password-modal .bg-red-500');
    if (btn) btn.disabled = true;

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const formData = new FormData();
        formData.append('_token', csrfToken);
        formData.append('verify_password', '1');
        formData.append('password', password);
        formData.append('delete_index', document.getElementById('password-delete-index').value);

        // Use checkPasswordUrl which bypasses CSRF and routing issues
        const verifyUrl = window.purchaseRoutes?.checkPasswordUrl || '/check-password';
        
        console.log('[DEBUG] Attempting password verification...');
        console.log('[DEBUG] URL:', verifyUrl);
        console.log('[DEBUG] CSRF Token:', csrfToken);
        
        const res = await fetch(verifyUrl, {
            method: 'POST',
            body: formData
        });

        console.log('[DEBUG] Response status:', res.status);
        console.log('[DEBUG] Response OK:', res.ok);
        
        const result = await res.json();
        console.log('[DEBUG] Response data:', result);

        if (result.success) {
            const index = parseInt(document.getElementById('password-delete-index').value);
            toggleModal('password-modal', false);
            document.getElementById('password-input-container').innerHTML = '';
            removeItemFromPO(index);
        } else {
            document.getElementById('password-error-msg').textContent = result.message || 'Incorrect password. Please try again.';
            document.getElementById('password-error-msg').classList.remove('hidden');
            const input = document.getElementById('password-input');
            if (input) { input.value = ''; input.focus(); }
        }
    } catch (e) {
        console.error('Password verification error:', e);
        document.getElementById('password-error-msg').textContent = 'Error verifying password. Please try again.';
        document.getElementById('password-error-msg').classList.remove('hidden');
    } finally {
        if (btn) btn.disabled = false;
    }
};

window.updatePOTotals = function() {
    const cleanPo = (val) => {
        if (!val) return '';
        const num = parseInt(val, 10);
        return isNaN(num) ? String(val).trim() : num;
    };

    let itemsToSum = selectedItems;
    if (window.selectedInvoiceId) {
        const filterIdInt = parseInt(window.selectedInvoiceId, 10);
        itemsToSum = selectedItems.filter(item => {
            if (item.purchase_order_id === null || item.purchase_order_id === undefined) return true;
            return parseInt(item.purchase_order_id, 10) === filterIdInt;
        });
    }
    const total = itemsToSum.reduce((sum, item) => sum + parseFloat(item.subTotal || 0), 0);
    const firstItem = itemsToSum[0];
    const currency = firstItem ? (firstItem.currency || 'PHP') : 'PHP';
    
    const currencySymbols = {
        'PHP': '₱',
        'TWD': 'NT$',
        'USD': '$'
    };
    const symbol = currencySymbols[currency];

    const displayTotal = `${symbol} ${total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
    
    const grandTotalEl = document.getElementById('po-items-grand-total');
    const basicInfoTotalEl = document.getElementById('new-total-amount');
    
    if (grandTotalEl) grandTotalEl.innerText = displayTotal;
    if (basicInfoTotalEl) basicInfoTotalEl.value = total.toFixed(2);
};

window.filterItemTable = function() {
    const input = document.getElementById('item-table-search');
    if (!input) return;
    itemSearchState.q = input.value;
    itemSearchState.page = 1;
    fetchItemResults();
};

window.filterItemColumn = function(col, value) {
    itemSearchState[col] = value;
    itemSearchState.page = 1;
    fetchItemResults();
};

window.fetchItemResults = function(page) {
    if (page !== undefined) itemSearchState.page = page;

    const params = {};
    Object.entries(itemSearchState).forEach(([k, v]) => { if (v) params[k] = v; });

    const tbody = document.getElementById('item-search-tbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="8" class="p-8 text-center text-slate-400 italic"><div class="flex items-center justify-center space-x-2"><i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i><span>Loading items...</span></div></td></tr>';
    if (typeof lucide !== 'undefined') lucide.createIcons();

    const qs = new URLSearchParams(params).toString();
    const url = window.purchaseRoutes?.searchProducts;
    if (!url) {
        tbody.innerHTML = '<tr><td colspan="8" class="p-8 text-center text-red-400 italic">Product search route is not configured.</td></tr>';
        return;
    }

    fetch(`${url}?${qs}`, { headers: { Accept: 'application/json' } })
        .then(async (res) => {
            const r = await res.json().catch(() => ({}));
            if (!res.ok || !r.success) {
                const msg = r.message || `Server error (${res.status})`;
                tbody.innerHTML = `<tr><td colspan="8" class="p-8 text-center text-red-400 italic">${msg}</td></tr>`;
                return;
            }
            renderItemResults(r);
            renderItemPagination(r);
        })
        .catch((err) => {
            console.error('search-products failed:', err);
            tbody.innerHTML = '<tr><td colspan="8" class="p-8 text-center text-red-400 italic">Error loading items. Please refresh and try again.</td></tr>';
        });
};

function renderItemResults(r) {
    const tbody = document.getElementById('item-search-tbody');
    if (!tbody) return;
    const items = r.data || [];
    if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="p-8 text-center text-slate-400 italic">No items found.</td></tr>';
        return;
    }
    const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    tbody.innerHTML = items.map(item => {
        const productId = normalizePurchaseNoteProductId(item?.product_id ?? item?.id ?? null);
        const checked = productId !== null && itemSearchSelection.has(productId) ? ' checked' : '';
        const itemData = JSON.stringify({
            productId: productId,
            product_id: productId,
            code: item.product_code,
            partNo: item.part_number || '',
            desc: item.description || '',
            unit: item.unit || '',
            cost: item.cost || 0,
        }).replace(/'/g, '&#39;');

        const missingId = productId === null;
        return `<tr class="hover:bg-slate-50 transition-colors ${missingId ? 'opacity-60' : ''}">
            <td class="p-3 px-4"><input type="checkbox" class="item-checkbox w-4 h-4 rounded border-slate-300 text-maroon focus:ring-maroon" data-item='${itemData}'${checked} ${missingId ? 'disabled' : ''}></td>
            <td class="p-3 px-4 font-bold text-maroon">${esc(item.product_code)}</td>
            <td class="p-3 px-4 text-slate-700">${esc(item.part_number || '---')}</td>
            <td class="p-3 px-4 text-slate-700">${esc(item.description || '')}</td>
            <td class="p-3 px-4 text-slate-500">${esc(item.unit || '')}</td>
            <td class="p-3 px-4 text-slate-500">${esc(item.category || '')}</td>
            <td class="p-3 px-4 text-slate-400">${esc(item.last_purchase_date || '---')}</td>
            <td class="p-3 px-4 text-center">
                <button type="button" class="pn-add-item-btn px-3 py-1 bg-maroon text-white text-[10px] font-bold rounded-lg hover:bg-maroon-800 transition-all disabled:opacity-50 disabled:cursor-not-allowed" data-item='${itemData}' ${missingId ? 'disabled title="Missing product ID"' : ''}>+ Add</button>
            </td>
        </tr>`;
    }).join('');

    tbody.querySelectorAll('.item-checkbox').forEach(cb => {
        cb.addEventListener('change', () => {
            const data = JSON.parse(cb.getAttribute('data-item'));
            const productId = getPurchaseNoteProductId(data);
            if (productId === null) return;
            data.productId = productId;
            data.product_id = productId;
            if (cb.checked) {
                itemSearchSelection.set(productId, data);
            } else {
                itemSearchSelection.delete(productId);
            }
        });
    });

    tbody.querySelectorAll('.pn-add-item-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const data = JSON.parse(btn.getAttribute('data-item'));
            addItemToPO(data.productId, data.code, data.partNo, data.desc, data.unit, data.cost);
        });
    });

    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function renderItemPagination(r) {
    const c = document.getElementById('item-pagination-container');
    if (!c) return;
    const fromEl = document.getElementById('item-pagination-from');
    const toEl = document.getElementById('item-pagination-to');
    const totalEl = document.getElementById('item-pagination-total');
    if (fromEl) fromEl.innerText = r.total > 0 ? (r.current_page - 1) * r.per_page + 1 : 0;
    if (toEl) toEl.innerText = Math.min(r.current_page * r.per_page, r.total);
    if (totalEl) totalEl.innerText = r.total;
    let h = '';
    h += `<button onclick="fetchItemResults(${r.current_page - 1})" ${r.current_page === 1 ? 'disabled' : ''} class="p-2 rounded-xl border border-slate-200 text-slate-400 hover:text-maroon hover:border-maroon disabled:opacity-50 disabled:cursor-not-allowed transition-all"><i data-lucide="chevron-left" class="w-4 h-4"></i></button>`;
    for (let i = 1; i <= r.last_page; i++) {
        if (i === 1 || i === r.last_page || (i >= r.current_page - 2 && i <= r.current_page + 2))
            h += `<button onclick="fetchItemResults(${i})" class="px-4 py-2 rounded-xl text-xs font-bold transition-all ${i === r.current_page ? 'bg-maroon text-white shadow-md shadow-maroon/20' : 'text-slate-500 hover:bg-slate-100 border border-transparent'}">${i}</button>`;
        else if (i === r.current_page - 3 || i === r.current_page + 3) h += '<span class="text-slate-300 px-1">...</span>';
    }
    h += `<button onclick="fetchItemResults(${r.current_page + 1})" ${r.current_page === r.last_page ? 'disabled' : ''} class="p-2 rounded-xl border border-slate-200 text-slate-400 hover:text-maroon hover:border-maroon disabled:opacity-50 disabled:cursor-not-allowed transition-all"><i data-lucide="chevron-right" class="w-4 h-4"></i></button>`;
    c.innerHTML = h;
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

window.openItemSearchModal = function() {
    itemSearchSelection.clear();
    itemSearchState = { page: 1, q: '', code: '', partNo: '', desc: '', cat: '' };
    const globalInput = document.getElementById('item-table-search');
    if (globalInput) globalInput.value = '';
    document.querySelectorAll('#item-search-modal .column-search-input').forEach(inp => inp.value = '');
    toggleModal('item-search-modal', true);
    fetchItemResults(1);
};

/**
 * Review Data Population
 */
function populateReviewData() {
    // Basic Info
    document.getElementById('review-trans-date').innerText = document.getElementById('new-trans-date').value;
    document.getElementById('review-po-number').innerText = document.getElementById('new-po-number').value;
    document.getElementById('review-supplier-name').innerText = document.getElementById('supplier-search-input-trigger').value;
    document.getElementById('review-remarks').innerText = document.getElementById('new-remarks').value || 'N/A';
    document.getElementById('review-po-status').innerText = document.getElementById('new-po-status').value;

    // Items
    const tbody = document.getElementById('review-items-tbody');
    tbody.innerHTML = '';

    const cleanPo = (val) => {
        if (!val) return '';
        const num = parseInt(val, 10);
        return isNaN(num) ? String(val).trim() : num;
    };

    let itemsToRender = selectedItems;
    if (window.selectedInvoiceId) {
        const filterIdInt = parseInt(window.selectedInvoiceId, 10);
        itemsToRender = selectedItems.filter(item => {
            if (item.purchase_order_id === null || item.purchase_order_id === undefined) return true;
            return parseInt(item.purchase_order_id, 10) === filterIdInt;
        });
    }

    itemsToRender.forEach(item => {
        const currencySymbols = {
            'PHP': '₱',
            'TWD': 'NT$',
            'USD': '$'
        };
        const symbol = currencySymbols[item.currency || 'PHP'];

        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="p-3 px-4 font-bold text-slate-700">${item.code}</td>
            <td class="p-3 px-4">${item.partNo}</td>
            <td class="p-3 px-4">${item.desc}</td>
            <td class="p-3 px-4 uppercase text-slate-500">${item.unit}</td>
            <td class="p-3 px-4 text-center font-bold">${item.qty}</td>
            <td class="p-3 px-4 text-right">${symbol} ${item.cost.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
            <td class="p-3 px-4 text-right font-bold text-maroon">${symbol} ${item.subTotal.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
        `;
        tbody.appendChild(row);
    });

    const total = itemsToRender.reduce((sum, item) => sum + item.subTotal, 0);
    const firstItem = itemsToRender[0];
    const currency = firstItem ? (firstItem.currency || 'PHP') : 'PHP';
    const currencySymbols = {
        'PHP': '₱',
        'TWD': 'NT$',
        'USD': '$'
    };
    const symbol = currencySymbols[currency];
    document.getElementById('review-grand-total').innerText = `${symbol} ${total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
}

/**
 * Confirmation Dialog
 */
window.confirmAction = function(title, message, onConfirm) {
    const modal = document.getElementById('confirmation-modal');
    const titleEl = document.getElementById('confirm-title');
    const msgEl = document.getElementById('confirm-message');
    const confirmBtn = document.getElementById('confirm-action-btn');

    if (!modal || !titleEl || !msgEl || !confirmBtn) return;

    titleEl.textContent = title;
    msgEl.textContent = message;

    // Remove old listeners
    const newConfirmBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

    newConfirmBtn.addEventListener('click', () => {
        onConfirm();
        toggleModal('confirmation-modal', false);
    });

    toggleModal('confirmation-modal', true);
};

/**
 * Show Success Message
 */
window.showSuccess = function(message) {
    const modal = document.getElementById('success-modal');
    const msgEl = document.getElementById('success-message');
    if (!modal || !msgEl) return;

    msgEl.textContent = message;
    toggleModal('success-modal', true);

    // Auto hide after 3 seconds
    setTimeout(() => {
        toggleModal('success-modal', false);
    }, 3000);
};

function waitForPurchaseNoteSync(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

function setPNSyncIcon(mode) {
    const spinner = document.getElementById('pn-sync-spinner');
    const successIcon = document.getElementById('pn-sync-success-icon');
    const errorIcon = document.getElementById('pn-sync-error-icon');

    spinner?.classList.toggle('hidden', mode !== 'loading');
    spinner?.classList.toggle('flex', mode === 'loading');
    successIcon?.classList.toggle('hidden', mode !== 'success');
    successIcon?.classList.toggle('flex', mode === 'success');
    errorIcon?.classList.toggle('hidden', mode !== 'error');
    errorIcon?.classList.toggle('flex', mode === 'error');
}

function setPNSyncStep(step, state) {
    const row = document.querySelector(`[data-pn-sync-step="${step}"]`);
    if (!row) return;

    const label = row.querySelector('.pn-sync-step-status');
    const states = {
        pending: ['Pending', 'border-slate-100', 'bg-slate-50', 'text-slate-400'],
        loading: ['Syncing', 'border-amber-200', 'bg-amber-50', 'text-amber-600'],
        success: ['Done', 'border-green-200', 'bg-green-50', 'text-green-600'],
        error: ['Needs Review', 'border-red-200', 'bg-red-50', 'text-red-600']
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

function setAllPNSyncSteps(state) {
    document.querySelectorAll('[data-pn-sync-step]').forEach(row => {
        setPNSyncStep(row.dataset.pnSyncStep, state);
    });
}

function showPNSyncModal(mode, message) {
    const modal = document.getElementById('pn-sync-modal');
    if (!modal) return;

    const title = document.getElementById('pn-sync-title');
    const messageEl = document.getElementById('pn-sync-message');
    const closeBtn = document.getElementById('pn-sync-close-btn');

    if (title) {
        title.textContent = mode === 'error'
            ? 'Sync Needs Review'
            : mode === 'success'
                ? 'Purchase Note Synced'
                : 'Syncing Purchase Note';
    }

    if (messageEl) {
        messageEl.textContent = message;
    }

    closeBtn?.classList.toggle('hidden', mode === 'loading');
    setPNSyncIcon(mode);
    toggleModal('pn-sync-modal', true);

    if (window.lucide) window.lucide.createIcons();
}

function showPNSyncLoading(message = 'Saving the Purchase Note and item details.') {
    setAllPNSyncSteps('loading');
    showPNSyncModal('loading', message);
}

function showPNSyncSuccess(message = 'Purchase Note data synced successfully.') {
    setAllPNSyncSteps('success');
    showPNSyncModal('success', message);
}

function showPNSyncFailure(message) {
    setAllPNSyncSteps('pending');
    setPNSyncStep('purchase_notes', 'error');
    showPNSyncModal('error', message || 'Purchase Note sync failed. Please review and try again.');
}

async function finishPNSyncSuccess(message) {
    if (!document.getElementById('pn-sync-modal')) return;

    showPNSyncSuccess(message);
    await waitForPurchaseNoteSync(500);
    toggleModal('pn-sync-modal', false);
}

async function runTimedPNSync(requestCallback) {
    if (!document.getElementById('pn-sync-modal')) {
        return requestCallback();
    }

    const startedAt = Date.now();
    const result = await requestCallback();
    const remainingMs = PN_SYNC_MIN_VISIBLE_MS - (Date.now() - startedAt);
    if (remainingMs > 0) {
        await waitForPurchaseNoteSync(remainingMs);
    }

    return result;
}

/**
 * Open Add Purchase Note Modal
 */
async function loadNextPoNumber() {
    const poInput = document.getElementById('new-po-number');
    if (!poInput || !window.purchaseRoutes?.nextNumber) return;

    try {
        const res = await fetch(window.purchaseRoutes.nextNumber, {
            headers: { Accept: 'application/json' },
        });
        const data = await res.json();
        if (data.success && data.next_number) {
            poInput.value = data.next_number;
        }
    } catch (e) {
        console.error('Failed to load next P.O number:', e);
    }
}

function setWizardMode(mode) {
    const title = document.getElementById('pn-wizard-title');
    const subtitle = document.getElementById('pn-wizard-subtitle');
    const finalizeText = document.getElementById('finalize-btn-text');
    const poInput = document.getElementById('new-po-number');
    const step2Content = document.getElementById('pn-wizard-step-2');
    const step1NextBtn = document.getElementById('pn-step1-next-btn');
    const step2Indicator = document.getElementById('pn-step-indicator-2');
    const step2Label = document.getElementById('pn-step-label-2');

    const step3BackBtn = document.getElementById('pn-step3-back-btn');
    const step3BackText = document.getElementById('pn-step3-back-text');
    const step2Wrapper = document.getElementById('pn-step-wrapper-2');
    const step2Sep1 = document.getElementById('pn-step-sep-1');
    const step2Sep2 = document.getElementById('pn-step-sep-2');

    if (mode === 'edit') {
        if (title) title.textContent = 'Edit Purchase Note';
        if (subtitle) subtitle.textContent = 'Update basic info, items, and quantities';
        if (finalizeText) finalizeText.textContent = 'Save Changes';
        if (step2Wrapper) step2Wrapper.classList.remove('hidden');
        if (step2Sep1) step2Sep1.classList.remove('hidden');
        if (step2Sep2) step2Sep2.classList.remove('hidden');
        if (step2Content) step2Content.classList.remove('hidden');
        if (step1NextBtn) step1NextBtn.onclick = function() { goToStep(2); };
        if (step2Indicator) step2Indicator.classList.remove('hidden');
        if (step2Label) step2Label.classList.remove('hidden');
        if (step3BackBtn) step3BackBtn.onclick = function() { goToStep(2); };
        if (step3BackText) step3BackText.textContent = 'Back to Invoices';
    } else {
        editingNoteId = null;
        if (title) title.textContent = 'Add Purchase Note';
        if (subtitle) subtitle.textContent = 'Follow the steps to complete your purchase note';
        if (finalizeText) finalizeText.textContent = 'Submit Purchase Note';
        if (poInput) poInput.setAttribute('readonly', 'readonly');
        loadNextPoNumber();
        if (step2Wrapper) step2Wrapper.classList.add('hidden');
        if (step2Sep1) step2Sep1.classList.add('hidden');
        if (step2Sep2) step2Sep2.classList.add('hidden');
        if (step2Content) step2Content.classList.add('hidden');
        if (step1NextBtn) step1NextBtn.onclick = function() { goToStep(3); };
        if (step2Indicator) step2Indicator.classList.add('hidden');
        if (step2Label) step2Label.classList.add('hidden');
        if (step3BackBtn) step3BackBtn.onclick = function() { goToStep(1); };
        if (step3BackText) step3BackText.textContent = 'Back to Basic Info';
    }
}

window.openAddNoteModal = function() {
    supplierItemConflicts = {};
    if (typeof window.resetPurchaseNoteItemColumnFilters === 'function') {
        window.resetPurchaseNoteItemColumnFilters();
    }
    selectedItems = [];
    originalPNItems = [];
    editingNoteId = null;
    forgottenPartialData = null;
    transferredPartialItems = [];
    wizardShowPartialNotesStep = false;
    if (typeof window.clearInvoiceFilter === 'function') {
        window.clearInvoiceFilter();
    } else {
        window.selectedInvoicePoNumber = null;
    }
    renderPOItemsTable();
    updatePOTotals();

    const dateInput = document.getElementById('new-trans-date');
    if (dateInput) dateInput.value = new Date().toISOString().split('T')[0];
    const remarksEl = document.getElementById('new-remarks');
    if (remarksEl) remarksEl.value = '';
    const supplierTrigger = document.getElementById('supplier-search-input-trigger');
    if (supplierTrigger) supplierTrigger.value = '';
    const supplierId = document.getElementById('selected-supplier-id');
    if (supplierId) supplierId.value = '';

    setWizardMode('add');
    goToStep(1);
    toggleModal('purchase-note-wizard-modal', true);
};

function purchaseNoteQuantityValue(item) {
    return item.qty ?? item.quantity;
}

function normalizePurchaseNoteQuantity(item) {
    const rawQuantity = purchaseNoteQuantityValue(item);
    if (rawQuantity === '' || rawQuantity === null || rawQuantity === undefined) {
        return null;
    }

    const quantity = Number(rawQuantity);
    return Number.isFinite(quantity) ? quantity : NaN;
}

function validatePurchaseNoteQuantities(items, allowZeroQuantity = true) {
    for (let index = 0; index < items.length; index += 1) {
        const quantity = normalizePurchaseNoteQuantity(items[index]);
        if (quantity === null) {
            alert(`Please enter a quantity for item #${index + 1}.`);
            return false;
        }
        if (!Number.isFinite(quantity)) {
            alert(`Please enter a valid quantity for item #${index + 1}.`);
            return false;
        }
        if (quantity < 0) {
            alert(`Quantity cannot be negative for item #${index + 1}.`);
            return false;
        }
        if (!allowZeroQuantity && quantity === 0) {
            alert(`Quantity must be greater than zero for item #${index + 1}.`);
            return false;
        }
    }

    return true;
}

/**
 * Final submit (Add or Edit)
 */
window.handleFinalizePurchaseNote = async function(event) {
    if (event) event.preventDefault();

    if (window._pnSubmitting) return;

    if (!selectedItems || selectedItems.length === 0) { alert('Please add at least one item.'); return; }

    const isEdit = !!editingNoteId;
    // Purchase Note quantity may legitimately be 0 for both create and edit.
    // Only negative / missing / non-numeric quantities are invalid.
    if (!validatePurchaseNoteQuantities(selectedItems, true)) return;
    const isPOEdit = isEdit && window.selectedInvoiceId !== null && window.selectedInvoiceId !== undefined;

    const productIdSources = [...selectedItems];
    const productIdsReady = await restorePurchaseNoteProductIds(
        productIdSources,
        isPOEdit ? 'Purchase Note invoice update' : (isEdit ? 'Purchase Note update' : 'Purchase Note creation')
    );
    if (!productIdsReady) return;

    // PO Update flow: save PN first, then redirect based on PO status
    if (isPOEdit) {
        const poId = parseInt(window.selectedInvoiceId, 10);
        const isOpenEntry = poId === 0;
        const invoicePoNumber = window.selectedInvoicePoNumber || '';
        const poStatus = (window.selectedInvoiceStatus || '').toLowerCase();
        const isPartial = poStatus === 'partial' || poStatus === 'closed';

        const supplierCode = document.getElementById('selected-supplier-id')?.value;
        const date = document.getElementById('new-trans-date')?.value;
        const remarks = document.getElementById('new-remarks')?.value;
        const pnNumber = document.getElementById('new-po-number')?.value;

        if (!supplierCode) { alert('Please select a supplier.'); return; }
        if (!date) { alert('Please enter a transaction date.'); return; }
        if (!pnNumber) { alert('Purchase Note number is required.'); return; }

        const pnItems = selectedItems.map(item => ({
            product_id: getPurchaseNoteProductId(item),
            product_code: item.code || item.product_code,
            unit: String(item.unit ?? '').trim(),
            quantity: normalizePurchaseNoteQuantity(item),
            unit_price: item.cost || item.unit_price || item.unitCost || 0,
            currency: item.currency || 'PHP',
            conversion_rate: item.conversion_rate ?? null,
            unit_price_converted: item.unit_price_converted ?? null,
            total_price_converted: item.total_price_converted ?? null,
        }));

        if (!validatePurchaseNoteProductIds(pnItems, 'Purchase Note invoice update')) {
            return;
        }

        // Safety deduplication: if the same product_id appears multiple times,
        // sum quantities and keep the last unit_price to prevent DB duplicates
        const dedupMap = new Map();
        pnItems.forEach(item => {
            const pid = item.product_id;
            if (dedupMap.has(pid)) {
                const existing = dedupMap.get(pid);
                existing.quantity += item.quantity;
                existing.unit = item.unit;
                existing.unit_price = item.unit_price;
                existing.unit_price_converted = item.unit_price_converted ?? existing.unit_price_converted;
                existing.total_price_converted = item.total_price_converted ?? existing.total_price_converted;
            } else {
                dedupMap.set(pid, { ...item });
            }
        });
        pnItems.length = 0;
        pnItems.push(...dedupMap.values());

        const label = isPartial ? 'Edit Invoice Items' : 'Process Remaining Items';
        const msg = isPartial
            ? `P.O #${invoicePoNumber} — Save PN changes and redirect to edit this invoice's items?`
            : `P.O #${invoicePoNumber} — Save PN changes and redirect to process remaining items?`;

        confirmAction(label, msg, async () => {
            if (window._pnSubmitting) return;
            window._pnSubmitting = true;
            try {
                const url = window.purchaseRoutes.update.replace(':id', editingNoteId);
                console.info('[PN FIX] Submitting invoice-update items', pnItems.map(item => ({
                    product_id: item.product_id,
                    product_code: item.product_code,
                    quantity: item.quantity,
                })));
                showPNSyncLoading('Saving Purchase Note changes before opening Purchase Order.');
                const response = await runTimedPNSync(() => fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({
                        supplier_code: supplierCode,
                        date: date,
                        reference_number: pnNumber,
                        remarks: remarks,
                        linked_purchase_order_id: poId > 0 ? poId : null,
                        items: pnItems,
                    })
                }));
                const result = await response.json();
                if (!result.success) throw new Error(result.message || 'Failed to update purchase note');

                // The backend is authoritative because it has already reshaped
                // the linked Purchase Order snapshot by product_id.
                const deletedItems = Array.isArray(result.deleted_items) ? result.deleted_items : [];
                const addedItems = Array.isArray(result.added_items) ? result.added_items : [];
                const updatedItems = Array.isArray(result.updated_items) ? result.updated_items : [];
                const redirectPoId = parseInt(result.po_id || poId, 10);
                console.log(`[PN] PO sync: poId=${redirectPoId}, added=${addedItems.length}, deleted=${deletedItems.length}, updated=${updatedItems.length}`);

                const currentEditingNoteId = editingNoteId;

                window._closingConfirmed = true;
                closePurchaseNoteWizard();

                sessionStorage.setItem('po_edit_changes', JSON.stringify({
                    poId: redirectPoId,
                    addedItems: addedItems,
                    deletedItems: deletedItems,
                    updatedItems: updatedItems,
                }));

                if (!isPartial) {
                    sessionStorage.setItem('proceed_note_data', JSON.stringify({
                        poId: currentEditingNoteId,
                        po_number: pnNumber,
                        supplierCode: supplierCode,
                        name: document.getElementById('supplier-search-input-trigger')?.value || '',
                        transNo: pnNumber,
                        status: editingNoteStatus || 'Open',
                    }));
                }
                selectedItems = [];
                renderPOItemsTable();
                updatePOTotals();
                fetchPurchaseNotesTable(1);
                await finishPNSyncSuccess('Purchase Note changes synced successfully.');

                const orderIndexUrl = purchaseOrderIndexUrl();
                window.location.href = isPartial
                    ? `${orderIndexUrl}?open_edit_po=${encodeURIComponent(redirectPoId)}`
                    : orderIndexUrl;
            } catch (error) {
                console.error('Failed to save purchase note:', error);
                showPNSyncFailure(error.message || 'Failed to save purchase note before redirect');
                alert(error.message || 'Failed to save purchase note before redirect');
            } finally {
                window._pnSubmitting = false;
            }
        });
        return;
    }

    // Original PN save flow (Add or Edit without invoice selection)
    const supplierCode = document.getElementById('selected-supplier-id')?.value;
    const date = document.getElementById('new-trans-date')?.value;
    const remarks = document.getElementById('new-remarks')?.value;
    const poNumber = document.getElementById('new-po-number')?.value;

    if (!supplierCode) { alert('Please select a supplier.'); return; }
    if (!date) { alert('Please enter a transaction date.'); return; }

    const items = selectedItems.map(item => ({
        product_id: getPurchaseNoteProductId(item),
        product_code: item.code || item.product_code,
        unit: String(item.unit ?? '').trim(),
        quantity: normalizePurchaseNoteQuantity(item),
        unit_price: item.cost || item.unit_price || item.unitCost || 0,
        currency: item.currency || 'PHP',
        conversion_rate: item.conversion_rate ?? null,
        unit_price_converted: item.unit_price_converted ?? null,
        total_price_converted: item.total_price_converted ?? null,
        _transferredFrom: item._transferredFrom || null,
    }));

    if (!validatePurchaseNoteProductIds(items, isEdit ? 'Purchase Note update' : 'Purchase Note creation')) {
        return;
    }

    // Build transferred_items array for server-side processing
    const transferredItems = [];
    items.forEach((item, idx) => {
        if (item._transferredFrom) {
            const matchedPartial = transferredPartialItems.find(
                t => t.source_purchase_note_item_id === item._transferredFrom.source_purchase_note_item_id
            );
            if (matchedPartial) {
                transferredItems.push({
                    item_index: idx,
                    source_purchase_note_id: matchedPartial.source_purchase_note_id,
                    source_purchase_note_item_id: matchedPartial.source_purchase_note_item_id,
                    product_id: matchedPartial.product_id,
                    quantity: item.quantity,
                    unit_price: item.unit_price,
                    total_price: item.quantity * item.unit_price,
                });
            }
        }
    });

    const hasTransfers = transferredItems.length > 0;
    const confirmTitle = isEdit ? 'Update Purchase Note' : (hasTransfers ? 'Add Purchase Note (with transfers)' : 'Add Purchase Note');
    const confirmMsg = isEdit
        ? 'Are you sure you want to save changes to this purchase note?'
        : hasTransfers
            ? `Are you sure you want to create this purchase note? ${transferredItems.length} item(s) will be transferred from an existing partial purchase note.`
            : 'Are you sure you want to add this purchase note?';

    confirmAction(confirmTitle, confirmMsg, async () => {
        if (window._pnSubmitting) return;
        window._pnSubmitting = true;
        try {
            const url = isEdit
                ? window.purchaseRoutes.update.replace(':id', editingNoteId)
                : window.purchaseRoutes.store;
            const method = isEdit ? 'POST' : 'POST';

            showPNSyncLoading(isEdit ? 'Updating the Purchase Note and item details.' : 'Creating the Purchase Note and item details.');
            const response = await runTimedPNSync(() => fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({
                    supplier_code: supplierCode,
                    date: date,
                    reference_number: poNumber,
                    remarks: remarks,
                    items: items,
                    transferred_items: transferredItems,
                    viber_item_ids: window.viberPrepareNote?.viber_item_ids || undefined,
                })
            }));

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message || (isEdit ? 'Failed to update purchase note' : 'Failed to create purchase note'));
            }

            const currentEditingNoteId = editingNoteId;

            window._closingConfirmed = true;
            closePurchaseNoteWizard();
            selectedItems = [];
            renderPOItemsTable();
            updatePOTotals();

            if (isEdit) {
                // Redirect to Purchase Order page for processing
                sessionStorage.setItem('proceed_note_data', JSON.stringify({
                    poId: currentEditingNoteId,
                    po_number: poNumber,
                    supplierCode: supplierCode,
                    name: document.getElementById('supplier-search-input-trigger')?.value || '',
                    transNo: poNumber,
                    status: editingNoteStatus || 'Open',
                }));
                await finishPNSyncSuccess('Purchase Note synced successfully.');
                window.location.href = purchaseOrderIndexUrl();
            } else {
                await finishPNSyncSuccess('Purchase Note synced successfully.');
                showSuccess('Purchase note #' + (result.pn_number || '') + ' created successfully!');
                fetchPurchaseNotesTable(1);
                loadNextPoNumber();
            }
        } catch (error) {
            console.error('Error saving purchase note:', error);
            showPNSyncFailure(error.message || 'Failed to save purchase note');
            alert(error.message || 'Failed to save purchase note');
        } finally {
            window._pnSubmitting = false;
        }
    });
};

window.handleAddPurchaseNote = handleFinalizePurchaseNote;

/**
 * Handle Edit — opens same wizard with loaded data
 */
function showEditLoadingState() {
    document.querySelectorAll('.pn-wizard-step-content').forEach(function(el) {
        el.classList.add('hidden');
    });
    var loadingEl = document.getElementById('pn-wizard-loading');
    if (loadingEl) loadingEl.classList.remove('hidden');
}

function hideEditLoadingState() {
    var loadingEl = document.getElementById('pn-wizard-loading');
    if (loadingEl) loadingEl.classList.add('hidden');
}

window.handleEdit = async function(id) {
    editingNoteId = id;
    if (typeof window.resetPurchaseNoteItemColumnFilters === 'function') {
        window.resetPurchaseNoteItemColumnFilters();
    }
    originalPNItems = [];
    window.remainingPNItems = null;
    if (typeof window.clearInvoiceFilter === 'function') {
        window.clearInvoiceFilter();
    } else {
        window.selectedInvoicePoNumber = null;
    }

    // Show modal IMMEDIATELY with loading state
    toggleModal('purchase-note-wizard-modal', true);
    showEditLoadingState();
    if (typeof requestAnimationFrame === 'function') {
        await new Promise(resolve => requestAnimationFrame(resolve));
    }

    const detailsUrl = window.purchaseRoutes.details.replace(':id', id) + '?for_edit=1';

    try {
        const res = await fetch(detailsUrl, { headers: { Accept: 'application/json' } });
        const data = await res.json();
        if (!data.success || !data.note) {
            throw new Error(data.message || 'Failed to load purchase note');
        }

        const note = data.note;
        editingNoteStatus = note.status || 'Open';
        const supplier = note.supplier;

        document.getElementById('new-trans-date').value = note.date || '';
        document.getElementById('new-po-number').value = note.purchase_note_number || '';
        document.getElementById('new-po-number').setAttribute('readonly', 'readonly');
        document.getElementById('new-remarks').value = note.remarks || '';
        document.getElementById('new-po-status').value = note.status || 'Open';

        if (supplier) {
            document.getElementById('selected-supplier-id').value = supplier.supplier_code || '';
            document.getElementById('supplier-search-input-trigger').value =
                `${supplier.supplier_code || ''} - ${supplier.name || ''}`;
        }

        selectedItems = (note.items || []).map(item => ({
            productId: item.product_id,
            product_id: item.product_id,
            code: item.product_code,
            partNo: item.part_number || '',
            desc: item.description || '',
            unit: item.unit || '',
            qty: item.quantity,
            actualQty: item.actual_quantity !== undefined ? item.actual_quantity : null,
            cost: parseFloat(item.unit_price) || 0,
            subTotal: parseFloat(item.total_price) || (item.quantity * item.unit_price),
            actualSubTotal: item.actual_total_price !== undefined ? parseFloat(item.actual_total_price) : null,
            currency: item.currency || 'PHP',
            processed: item.processed === true,
            purchase_order_id: item.purchase_order_id || null,
            po_number: item.po_number || null,
        }));

        // Store original PN items for restoration when clearing invoice filter
        originalPNItems = selectedItems.map(item => ({ ...item }));

        renderPOItemsTable();
        updatePOTotals();

        // Reset Add-mode state so Linked Partial Notes don't leak into Edit modal
        wizardShowPartialNotesStep = false;
        forgottenPartialData = null;
        transferredPartialItems = [];
        const editLinkedSection = document.getElementById('pn-linked-partial-notes-section');
        const editInvoicesSection = document.getElementById('pn-invoices-section');
        if (editLinkedSection) editLinkedSection.classList.add('hidden');
        if (editInvoicesSection) editInvoicesSection.classList.remove('hidden');
        const editStep2Label = document.getElementById('pn-step-label-2');
        if (editStep2Label) editStep2Label.textContent = 'Invoices';

        // Hide loading, show wizard steps
        hideEditLoadingState();
        setWizardMode('edit');
        goToStep(1);

        refreshSupplierItemConflicts(supplier?.supplier_code);
    } catch (e) {
        console.error(e);
        hideEditLoadingState();
        alert(e.message || 'Could not load purchase note for editing');
        editingNoteId = null;
    }
};

/**
 * Handle Delete
 */
window.handleDelete = function(id) {
    confirmAction('Delete Record', 'Deleting this Purchase Note will also archive all related Purchase Orders, Purchase Order Items, Purchase Returns, and Return Items. These can be restored later from the Archived page. Continue?', async () => {
        try {
            const url = window.purchaseRoutes.delete.replace(':id', id);
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'Failed to delete purchase note');
            }

            showSuccess(data.message || 'Record deleted successfully!');
            fetchPurchaseNotesTable(currentPage);
        } catch (error) {
            console.error('Error deleting purchase note:', error);
            alert(error.message || 'Failed to delete purchase note');
        }
    });
};

/**
 * Handle View
 */
window.handleView = function(id) {
    console.log('Viewing', id);
    
    // Show loading state
    const modal = document.getElementById('view-purchase-note-modal');
    const tbody = document.getElementById('view-items-tbody');
    const grandTotal = document.getElementById('view-items-grand-total');
    
    // Show modal first
    toggleModal('view-purchase-note-modal', true);
    
    // Set loading state
    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="7" class="p-12 text-center text-slate-400"><div class="flex items-center justify-center space-x-2"><i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i><span>Loading items...</span></div></td></tr>';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
    
    // Fetch data
    const detailsUrl = window.purchaseRoutes.details.replace(':id', id);
    
    fetch(detailsUrl)
        .then(res => res.json())
        .then(response => {
            console.log('[DEBUG] Purchase Note Details Response:', response);
            
            if (response.success && response.note) {
                const note = response.note;
                console.log('[DEBUG] Note Items:', note.items);
                console.log('[DEBUG] Items Length:', note.items ? note.items.length : 0);
                
                // Update modal title
                const titleElement = modal.querySelector('.bg-maroon p');
                if (titleElement) {
                    titleElement.textContent = `Transaction: ${note.purchase_note_number}`;
                }
                
                // Update supplier info
                if (note.supplier) {
                    document.getElementById('view-supplier-code').textContent = note.supplier.supplier_code || 'N/A';
                    document.getElementById('view-supplier-name').textContent = note.supplier.name || 'N/A';
                    document.getElementById('view-supplier-contact').textContent = note.supplier.contact_number || 'N/A';
                    document.getElementById('view-supplier-person').textContent = note.supplier.contact_person || 'N/A';
                    document.getElementById('view-supplier-address').textContent = note.supplier.address || 'N/A';
                }
                
                // Show transfer history banner if items were transferred from this note
                const transferBanner = document.getElementById('view-transfer-history-banner');
                if (transferBanner) {
                    if (note.transferred_items_count > 0 && note.transfer_history && note.transfer_history.target_notes) {
                        const targets = note.transfer_history.target_notes.map(t =>
                            `#${t.target_purchase_note_number}`
                        ).join(', ');
                        transferBanner.innerHTML =
                            `<div class="flex items-center gap-2 text-amber-800">
                                <i data-lucide="info" class="w-4 h-4 shrink-0"></i>
                                <span><strong>${note.transferred_items_count}</strong> item(s) transferred to Purchase Note ${targets}</span>
                            </div>`;
                        transferBanner.classList.remove('hidden');
                        transferBanner.classList.add('flex');
                    } else {
                        transferBanner.classList.add('hidden');
                        transferBanner.classList.remove('flex');
                    }
                }
                
                // Update items table
                if (note.items && note.items.length > 0) {
                    tbody.innerHTML = note.items.map(item => `
                        <tr class="hover:bg-gold/5 transition-colors">
                            <td class="p-3 px-4 font-mono text-maroon font-bold">${item.product_code || 'N/A'}</td>
                            <td class="p-3 px-4 font-mono">${item.part_number || '---'}</td>
                            <td class="p-3 px-4">${item.description || 'N/A'}</td>
                            <td class="p-3 px-4 text-center font-bold">${item.quantity || 0}</td>
                            <td class="p-3 px-4 text-center">${item.unit || '-'}</td>
                            <td class="p-3 px-4 text-right font-mono">₱ ${parseFloat(item.unit_price || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                            <td class="p-3 px-4 text-right font-mono font-bold text-maroon">₱ ${parseFloat(item.total_price || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                        </tr>
                    `).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="7" class="p-12 text-center text-slate-400 italic">No active items remaining on this note.</td></tr>';
                }
                
                // Update grand total
                if (grandTotal) {
                    grandTotal.textContent = '₱ ' + parseFloat(note.total_amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2});
                }
                
                // Re-initialize lucide icons
                if (typeof lucide !== 'undefined') lucide.createIcons();
                
                // Setup column search for view modal
                setupViewModalColumnSearch(note.items);
            } else {
                tbody.innerHTML = '<tr><td colspan="7" class="p-12 text-center text-red-400 italic">Failed to load note details.</td></tr>';
            }
        })
        .catch(error => {
            console.error('Error fetching note details:', error);
            tbody.innerHTML = '<tr><td colspan="7" class="p-12 text-center text-red-400 italic">Error loading data.</td></tr>';
        });
};

/**
 * Handle Print
 */
window.handlePrint = function(id) {
    console.log('Printing', id);
    // Open print page in new window using the proper route
    const printUrl = window.purchaseRoutes.print.replace(':id', id);
    window.open(printUrl, '_blank');
};

/**
 * Setup column search for view modal
 */
function setupViewModalColumnSearch(items) {
    const searchInputs = document.querySelectorAll('#view-purchase-note-modal .column-search-input');
    let allItems = items || [];
    
    searchInputs.forEach(input => {
        input.addEventListener('input', () => {
            filterViewModalItems();
        });
    });
    
    function filterViewModalItems() {
        const filters = {};
        searchInputs.forEach(input => {
            const col = input.getAttribute('data-col');
            const val = input.value.trim().toLowerCase();
            if (val) filters[col] = val;
        });
        
        const tbody = document.getElementById('view-items-tbody');
        const grandTotal = document.getElementById('view-items-grand-total');
        
        let filteredItems = allItems;
        
        // Apply filters
        if (Object.keys(filters).length > 0) {
            filteredItems = allItems.filter(item => {
                return Object.entries(filters).every(([col, val]) => {
                    let itemValue = '';
                    switch(col) {
                        case 'itemCode':
                            itemValue = (item.product_code || '').toLowerCase();
                            break;
                        case 'partNo':
                            itemValue = (item.part_number || '').toLowerCase();
                            break;
                        case 'desc':
                            itemValue = (item.description || '').toLowerCase();
                            break;
                        case 'qty':
                            itemValue = (item.quantity || '').toString();
                            break;
                        case 'unit':
                            itemValue = (item.unit || '').toLowerCase();
                            break;
                        case 'cost':
                            itemValue = (item.unit_price || '').toString();
                            break;
                    }
                    return itemValue.includes(val);
                });
            });
        }
        
        // Render filtered items
        if (filteredItems.length > 0) {
            tbody.innerHTML = filteredItems.map(item => `
                <tr class="hover:bg-gold/5 transition-colors">
                    <td class="font-mono text-maroon font-bold">${item.product_code || 'N/A'}</td>
                    <td class="font-mono">${item.part_number || '---'}</td>
                    <td>${item.description || 'N/A'}</td>
                    <td class="text-center font-bold">${item.quantity || 0}</td>
                    <td class="text-center">${item.unit || '-'}</td>
                    <td class="text-right font-mono">₱ ${parseFloat(item.unit_price || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                    <td class="text-right font-mono font-bold text-maroon">₱ ${parseFloat(item.total_price || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="7" class="p-12 text-center text-slate-400 italic">No items match the search criteria.</td></tr>';
        }
        
        // Update total
        const total = filteredItems.reduce((sum, item) => sum + parseFloat(item.total_price || 0), 0);
        if (grandTotal) {
            grandTotal.textContent = '₱ ' + total.toLocaleString('en-US', {minimumFractionDigits: 2});
        }
        
        // Re-initialize lucide icons
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
}

document.addEventListener('keydown', function(e) {
    if (!e.ctrlKey || !['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(e.key)) return;
    const modals = document.querySelectorAll('.fixed.inset-0:not(.hidden)');
    if (modals.length === 0) return;
    e.preventDefault();
    const modal = modals[modals.length - 1];
    const inputs = Array.from(modal.querySelectorAll('input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled])'));
    if (inputs.length === 0) return;
    const active = document.activeElement;
    const idx = inputs.indexOf(active);
    if (e.key === 'ArrowDown' || e.key === 'ArrowRight') {
        inputs[(idx + 1) % inputs.length].focus();
    } else {
        inputs[(idx - 1 + inputs.length) % inputs.length].focus();
    }
});
