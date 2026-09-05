/**
 * Supplier Master List Client Logic - Server-Side Pagination
 */

let suppliers = [];
let supplierToDelete = null;
let currentView = 'table';
let currentTab = 'all';
let currentActiveViewTab = 'purchase';
let supplierFilters = {
    status: 'all',
    type: 'all'
};

let currentPage = 1;
let perPage = 50;
let pagination = null;
let searchValues = {};
let generalSearch = '';
let currentViewSupplierId = null;
let purchasedItemsState = {
    page: 1,
    pagination: null,
    filters: {},
    sort: null,
    direction: 'desc',
    loaded: false,
    loading: false,
    abortController: null,
    requestToken: 0
};
let unregisteredPurchaseOrdersState = {
    page: 1,
    pagination: null,
    filters: {},
    sort: null,
    direction: 'desc',
    loaded: false,
    loading: false,
    abortController: null,
    requestToken: 0
};

let osmMap = null;
let osmMarker = null;
let editOsmMap = null;
let editOsmMarker = null;

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

window.addEventListener("DOMContentLoaded", () => {
    const style = document.createElement('style');
    style.textContent = `
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.95) translateY(10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .modal-animate-in { animation: modalFadeIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards !important; }
    `;
    document.head.appendChild(style);

    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    perPage = 50;
    fetchSuppliers(1);

    setupLifecycleListeners();
    initMapAndAutocomplete();
    setupImageDragAndDrop();

    const debouncedSearch = debounce(() => {
        collectSearchValues();
        currentPage = 1;
        fetchSuppliers(1);
    }, 300);

    document.querySelectorAll('#supplier-table-container .column-search-input').forEach(input => {
        input.addEventListener('input', debouncedSearch);
    });

    const debouncedPurchasedItemsSearch = debounce(() => {
        collectPurchasedItemFilters();
        purchasedItemsState.page = 1;
        fetchPurchasedItems(1);
    }, 280);

    document.querySelectorAll('.purchased-items-filter').forEach(input => {
        input.addEventListener('input', debouncedPurchasedItemsSearch);
    });

    const debouncedUnregisteredPurchaseOrderSearch = debounce(() => {
        collectUnregisteredPurchaseOrderFilters();
        unregisteredPurchaseOrdersState.page = 1;
        fetchUnregisteredPurchaseOrders(1);
    }, 280);

    document.querySelectorAll('.unregistered-po-filter').forEach(input => {
        input.addEventListener('input', debouncedUnregisteredPurchaseOrderSearch);
    });

    const debouncedCardsSearch = debounce(() => {
        const input = document.getElementById('cards-search-input');
        generalSearch = input ? input.value.trim() : '';
        currentPage = 1;
        fetchSuppliers(1);
    }, 300);

    const cardsInput = document.getElementById('cards-search-input');
    if (cardsInput) {
        cardsInput.addEventListener('input', debouncedCardsSearch);
    }
});

function collectSearchValues() {
    searchValues = {};
    const tableContainer = document.getElementById('supplier-table-container');
    const searchInputs = tableContainer.querySelectorAll('thead .column-search-input');
    searchInputs.forEach(input => {
        const col = input.getAttribute('data-col');
        const val = input.value.trim();
        if (col && val) {
            searchValues[col] = val;
        }
    });
}

function collectPurchasedItemFilters() {
    purchasedItemsState.filters = {};
    document.querySelectorAll('.purchased-items-filter').forEach(input => {
        const field = input.getAttribute('data-purchased-items-filter');
        const value = input.value.trim();
        if (field && value) {
            purchasedItemsState.filters[field] = value;
        }
    });
}

function resetPurchasedItemsState(supplierId) {
    if (purchasedItemsState.abortController) {
        purchasedItemsState.abortController.abort();
    }

    currentViewSupplierId = supplierId;
    purchasedItemsState = {
        page: 1,
        pagination: null,
        filters: {},
        sort: null,
        direction: 'desc',
        loaded: false,
        loading: false,
        abortController: null,
        requestToken: purchasedItemsState.requestToken + 1
    };

    document.querySelectorAll('.purchased-items-filter').forEach(input => {
        input.value = '';
    });

    renderPurchasedItemsSummary();
    renderPurchasedItemsPagination(null);
    setPurchasedItemsTableState('loading');
}

function formatQuantity(value) {
    const number = Number(value || 0);
    if (!Number.isFinite(number)) return '0';
    return number.toLocaleString('en-US', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 4
    });
}

function formatMoney(value) {
    const number = Number(value || 0);
    if (!Number.isFinite(number)) return '0.00';
    return number.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function formatPercent(value) {
    return `${formatMoney(value)}%`;
}

function renderPurchasedItemsSummary(summary = {}) {
    const setText = (id, value) => {
        const element = document.getElementById(id);
        if (element) element.textContent = value;
    };

    setText('purchased-summary-transactions', formatQuantity(summary.purchase_transactions));
    setText('purchased-summary-rows', formatQuantity(summary.item_rows));
    setText('purchased-summary-ordered', formatQuantity(summary.total_ordered_quantity));
    setText('purchased-summary-received', formatQuantity(summary.total_received_quantity));
    setText('purchased-summary-amount', formatMoney(summary.total_purchase_amount));
}

function setPurchasedItemsTableState(state) {
    const tbody = document.getElementById('purchase-history-tbody');
    if (!tbody) return;

    const messages = {
        loading: 'Loading purchased items...',
        empty: 'No purchased item records found for this supplier.',
        error: 'Unable to load the supplier&rsquo;s purchased items.'
    };

    const retry = state === 'error'
        ? '<button type="button" onclick="refreshPurchasedItems()" class="mt-3 px-4 py-2 bg-maroon text-white text-[10px] font-bold rounded-lg">Retry</button>'
        : '';

    tbody.innerHTML = `
        <tr>
            <td colspan="13" class="py-12 text-center text-slate-300 italic">
                ${messages[state] || messages.empty}
                ${retry}
            </td>
        </tr>
    `;
}

function renderPurchasedItemsRows(items) {
    const tbody = document.getElementById('purchase-history-tbody');
    if (!tbody) return;

    if (!Array.isArray(items) || items.length === 0) {
        setPurchasedItemsTableState('empty');
        return;
    }

    tbody.innerHTML = items.map(item => `
        <tr class="hover:bg-slate-50/70">
            <td class="py-3 px-4 whitespace-nowrap">${escapeHtml(item.transaction_date)}</td>
            <td class="py-3 px-4 font-semibold text-slate-700 whitespace-nowrap">${escapeHtml(item.transaction_code)}</td>
            <td class="py-3 px-4 whitespace-nowrap">${escapeHtml(item.reference_no)}</td>
            <td class="py-3 px-4 font-mono text-[11px] text-slate-700 whitespace-nowrap">${escapeHtml(item.product_code)}</td>
            <td class="py-3 px-4 min-w-[260px]">${escapeHtml(item.description)}</td>
            <td class="py-3 px-4 whitespace-nowrap">${escapeHtml(item.unit)}</td>
            <td class="py-3 px-4 text-right whitespace-nowrap">${formatQuantity(item.quantity)}</td>
            <td class="py-3 px-4 text-right whitespace-nowrap">${formatQuantity(item.actual_quantity)}</td>
            <td class="py-3 px-4 text-right whitespace-nowrap">${formatMoney(item.unit_price)}</td>
            <td class="py-3 px-4 text-right whitespace-nowrap">${formatPercent(item.discount_percent)}</td>
            <td class="py-3 px-4 text-right whitespace-nowrap">${formatMoney(item.discount_amount)}</td>
            <td class="py-3 px-4 text-right font-bold text-maroon whitespace-nowrap">${formatMoney(item.subtotal)}</td>
            <td class="py-3 px-4 min-w-[220px]">${escapeHtml(item.transaction_title)}</td>
        </tr>
    `).join('');
}

function renderPurchasedItemsPagination(paginationData) {
    const container = document.getElementById('purchased-items-pagination');
    const summary = document.getElementById('view-table-summary');

    if (!container) return;
    purchasedItemsState.pagination = paginationData;

    if (!paginationData || paginationData.total === 0) {
        container.innerHTML = '';
        if (summary) summary.textContent = 'Total Records: 0';
        return;
    }

    const current = paginationData.current_page;
    const last = paginationData.last_page;
    const total = paginationData.total;
    const from = paginationData.from || 0;
    const to = paginationData.to || 0;

    if (summary) {
        summary.textContent = `Total Records: ${total}`;
    }

    container.innerHTML = `
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="text-xs text-slate-500 font-medium">
                Page <span class="font-bold text-slate-700">${current}</span> of <span class="font-bold text-slate-700">${last}</span>
                <span class="text-slate-300 mx-2">|</span>
                Showing <span class="font-bold text-slate-700">${from}</span> to <span class="font-bold text-slate-700">${to}</span> of <span class="font-bold text-slate-700">${total}</span> records
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="goToPurchasedItemsPage(${current - 1})" class="px-3 py-1.5 text-xs font-bold rounded-lg ${current <= 1 ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600 hover:bg-slate-100'}" ${current <= 1 ? 'disabled' : ''}>Previous</button>
                <button type="button" onclick="goToPurchasedItemsPage(${current + 1})" class="px-3 py-1.5 text-xs font-bold rounded-lg ${current >= last ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600 hover:bg-slate-100'}" ${current >= last ? 'disabled' : ''}>Next</button>
            </div>
        </div>
    `;
}

async function fetchPurchasedItems(page = 1) {
    if (!currentViewSupplierId) return;

    if (purchasedItemsState.abortController) {
        purchasedItemsState.abortController.abort();
    }

    const supplierId = currentViewSupplierId;
    const requestToken = purchasedItemsState.requestToken + 1;
    purchasedItemsState.requestToken = requestToken;
    purchasedItemsState.loading = true;
    purchasedItemsState.page = page;
    purchasedItemsState.abortController = new AbortController();

    setPurchasedItemsTableState('loading');

    const params = new URLSearchParams();
    params.set('page', page);
    Object.entries(purchasedItemsState.filters).forEach(([key, value]) => {
        params.set(`filters[${key}]`, value);
    });
    if (purchasedItemsState.sort) {
        params.set('sort', purchasedItemsState.sort);
        params.set('direction', purchasedItemsState.direction);
    }

    const url = `${window.supplierRoutes.fetchLedgerItems.replace(':id', supplierId)}?${params.toString()}`;

    try {
        const response = await fetch(url, {
            headers: { 'Accept': 'application/json' },
            signal: purchasedItemsState.abortController.signal
        });
        const result = await response.json();

        if (currentViewSupplierId !== supplierId || purchasedItemsState.requestToken !== requestToken) {
            return;
        }

        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Unable to load purchased items.');
        }

        purchasedItemsState.loaded = true;
        renderPurchasedItemsSummary(result.summary);
        renderPurchasedItemsRows(result.items);
        renderPurchasedItemsPagination(result.pagination);
    } catch (error) {
        if (error.name === 'AbortError') return;
        console.error('Unable to load purchased items:', error);

        if (currentViewSupplierId === supplierId && purchasedItemsState.requestToken === requestToken) {
            renderPurchasedItemsSummary();
            renderPurchasedItemsPagination(null);
            setPurchasedItemsTableState('error');
        }
    } finally {
        if (purchasedItemsState.requestToken === requestToken) {
            purchasedItemsState.loading = false;
            purchasedItemsState.abortController = null;
        }
    }
}

window.refreshPurchasedItems = function() {
    fetchPurchasedItems(purchasedItemsState.page || 1);
};

window.clearPurchasedItemFilters = function() {
    document.querySelectorAll('.purchased-items-filter').forEach(input => {
        input.value = '';
    });
    purchasedItemsState.filters = {};
    purchasedItemsState.sort = null;
    purchasedItemsState.direction = 'desc';
    purchasedItemsState.page = 1;
    fetchPurchasedItems(1);
};

window.sortPurchasedItems = function(field) {
    if (purchasedItemsState.sort === field) {
        purchasedItemsState.direction = purchasedItemsState.direction === 'asc' ? 'desc' : 'asc';
    } else {
        purchasedItemsState.sort = field;
        purchasedItemsState.direction = 'asc';
    }
    purchasedItemsState.page = 1;
    fetchPurchasedItems(1);
};

window.goToPurchasedItemsPage = function(page) {
    const lastPage = purchasedItemsState.pagination ? purchasedItemsState.pagination.last_page : 1;
    if (page < 1 || page > lastPage) return;
    fetchPurchasedItems(page);
};

function collectUnregisteredPurchaseOrderFilters() {
    unregisteredPurchaseOrdersState.filters = {};
    document.querySelectorAll('.unregistered-po-filter').forEach(input => {
        const field = input.getAttribute('data-unregistered-po-filter');
        const value = input.value.trim();
        if (field && value) {
            unregisteredPurchaseOrdersState.filters[field] = value;
        }
    });
}

function resetUnregisteredPurchaseOrdersState() {
    if (unregisteredPurchaseOrdersState.abortController) {
        unregisteredPurchaseOrdersState.abortController.abort();
    }

    unregisteredPurchaseOrdersState = {
        page: 1,
        pagination: null,
        filters: {},
        sort: null,
        direction: 'desc',
        loaded: false,
        loading: false,
        abortController: null,
        requestToken: unregisteredPurchaseOrdersState.requestToken + 1
    };

    document.querySelectorAll('.unregistered-po-filter').forEach(input => {
        input.value = '';
    });

    renderUnregisteredPurchaseOrdersSummary();
    renderUnregisteredPurchaseOrdersPagination(null);
    setUnregisteredPurchaseOrdersTableState('loading');
}

function renderUnregisteredPurchaseOrdersSummary(summary = {}) {
    const setText = (id, value) => {
        const element = document.getElementById(id);
        if (element) element.textContent = value;
    };

    setText('unregistered-summary-count', formatQuantity(summary.unregistered_count));
    setText('unregistered-summary-amount', formatMoney(summary.unregistered_amount));
    setText('unregistered-summary-missing', formatQuantity(summary.missing_invoice_number_count));
    setText('unregistered-summary-latest', summary.latest_purchase_order_date || 'N/A');
}

function setUnregisteredPurchaseOrdersTableState(state, missingCount = 0) {
    const tbody = document.getElementById('ledger-tbody');
    if (!tbody) return;

    const messages = {
        loading: 'Loading unregistered purchase invoices...',
        empty: 'All eligible Purchase Orders for this supplier are already registered in Process Payment.',
        error: 'Unable to load the supplier&rsquo;s unregistered Purchase Orders.'
    };

    const missingNote = state === 'empty' && Number(missingCount || 0) > 0
        ? `<div class="mt-2 text-[11px] text-amber-600 font-semibold">Some Purchase Orders cannot be matched because they do not have a Supplier Invoice No.</div>`
        : '';
    const retry = state === 'error'
        ? '<button type="button" onclick="refreshUnregisteredPurchaseOrders()" class="mt-3 px-4 py-2 bg-maroon text-white text-[10px] font-bold rounded-lg">Retry</button>'
        : '';

    tbody.innerHTML = `
        <tr>
            <td colspan="9" class="py-12 text-center text-slate-300 italic">
                ${messages[state] || messages.empty}
                ${missingNote}
                ${retry}
            </td>
        </tr>
    `;
}

function renderUnregisteredPurchaseOrdersRows(items, missingCount = 0) {
    const tbody = document.getElementById('ledger-tbody');
    if (!tbody) return;

    if (!Array.isArray(items) || items.length === 0) {
        setUnregisteredPurchaseOrdersTableState('empty', missingCount);
        return;
    }

    tbody.innerHTML = items.map(item => `
        <tr class="hover:bg-slate-50/70">
            <td class="py-3 px-4 font-semibold text-slate-700 whitespace-nowrap">${escapeHtml(item.purchase_order_number)}</td>
            <td class="py-3 px-4 font-mono text-[11px] text-maroon whitespace-nowrap">${escapeHtml(item.supplier_invoice_number || 'N/A')}</td>
            <td class="py-3 px-4 whitespace-nowrap">${escapeHtml(item.date || 'N/A')}</td>
            <td class="py-3 px-4 whitespace-nowrap">${escapeHtml(item.reference_no || 'N/A')}</td>
            <td class="py-3 px-4 whitespace-nowrap">${escapeHtml(item.status || 'N/A')}</td>
            <td class="py-3 px-4 text-right whitespace-nowrap">${formatMoney(item.total_amount)}</td>
            <td class="py-3 px-4 text-right font-bold text-maroon whitespace-nowrap">${formatMoney(item.remaining_balance)}</td>
            <td class="py-3 px-4 whitespace-nowrap">${escapeHtml(item.created_at || 'N/A')}</td>
            <td class="py-3 px-4 text-center">
                <button type="button" onclick="viewUnregisteredPurchaseOrder(${Number(item.id) || 0})" class="px-3 py-1.5 bg-white border border-slate-200 text-slate-600 hover:text-maroon hover:border-maroon/30 text-[10px] font-bold rounded-lg transition-all">
                    View Purchase Order
                </button>
            </td>
        </tr>
    `).join('');
}

function renderUnregisteredPurchaseOrdersPagination(paginationData) {
    const container = document.getElementById('unregistered-po-pagination');
    const summary = document.getElementById('unregistered-table-summary');

    if (!container) return;
    unregisteredPurchaseOrdersState.pagination = paginationData;

    if (!paginationData || paginationData.total === 0) {
        container.innerHTML = '';
        if (summary) summary.textContent = 'Total Records: 0';
        return;
    }

    const current = paginationData.current_page;
    const last = paginationData.last_page;
    const total = paginationData.total;
    const from = paginationData.from || 0;
    const to = paginationData.to || 0;

    if (summary) {
        summary.textContent = `Total Records: ${total}`;
    }

    container.innerHTML = `
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="text-xs text-slate-500 font-medium">
                Page <span class="font-bold text-slate-700">${current}</span> of <span class="font-bold text-slate-700">${last}</span>
                <span class="text-slate-300 mx-2">|</span>
                Showing <span class="font-bold text-slate-700">${from}</span> to <span class="font-bold text-slate-700">${to}</span> of <span class="font-bold text-slate-700">${total}</span> records
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="goToUnregisteredPurchaseOrdersPage(${current - 1})" class="px-3 py-1.5 text-xs font-bold rounded-lg ${current <= 1 ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600 hover:bg-slate-100'}" ${current <= 1 ? 'disabled' : ''}>Previous</button>
                <button type="button" onclick="goToUnregisteredPurchaseOrdersPage(${current + 1})" class="px-3 py-1.5 text-xs font-bold rounded-lg ${current >= last ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600 hover:bg-slate-100'}" ${current >= last ? 'disabled' : ''}>Next</button>
            </div>
        </div>
    `;
}

async function fetchUnregisteredPurchaseOrders(page = 1) {
    if (!currentViewSupplierId) return;

    if (unregisteredPurchaseOrdersState.abortController) {
        unregisteredPurchaseOrdersState.abortController.abort();
    }

    const supplierId = currentViewSupplierId;
    const requestToken = unregisteredPurchaseOrdersState.requestToken + 1;
    unregisteredPurchaseOrdersState.requestToken = requestToken;
    unregisteredPurchaseOrdersState.loading = true;
    unregisteredPurchaseOrdersState.page = page;
    unregisteredPurchaseOrdersState.abortController = new AbortController();

    setUnregisteredPurchaseOrdersTableState('loading');

    const params = new URLSearchParams();
    params.set('page', page);
    Object.entries(unregisteredPurchaseOrdersState.filters).forEach(([key, value]) => {
        params.set(`filters[${key}]`, value);
    });
    if (unregisteredPurchaseOrdersState.sort) {
        params.set('sort', unregisteredPurchaseOrdersState.sort);
        params.set('direction', unregisteredPurchaseOrdersState.direction);
    }

    const url = `${window.supplierRoutes.fetchUnregisteredPurchaseOrders.replace(':id', supplierId)}?${params.toString()}`;

    try {
        const response = await fetch(url, {
            headers: { 'Accept': 'application/json' },
            signal: unregisteredPurchaseOrdersState.abortController.signal
        });
        const result = await response.json();

        if (currentViewSupplierId !== supplierId || unregisteredPurchaseOrdersState.requestToken !== requestToken) {
            return;
        }

        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Unable to load unregistered Purchase Orders.');
        }

        unregisteredPurchaseOrdersState.loaded = true;
        renderUnregisteredPurchaseOrdersSummary(result.summary);
        renderUnregisteredPurchaseOrdersRows(result.items, result.summary?.missing_invoice_number_count || 0);
        renderUnregisteredPurchaseOrdersPagination(result.pagination);
    } catch (error) {
        if (error.name === 'AbortError') return;
        console.error('Unable to load unregistered purchase orders:', error);

        if (currentViewSupplierId === supplierId && unregisteredPurchaseOrdersState.requestToken === requestToken) {
            renderUnregisteredPurchaseOrdersSummary();
            renderUnregisteredPurchaseOrdersPagination(null);
            setUnregisteredPurchaseOrdersTableState('error');
        }
    } finally {
        if (unregisteredPurchaseOrdersState.requestToken === requestToken) {
            unregisteredPurchaseOrdersState.loading = false;
            unregisteredPurchaseOrdersState.abortController = null;
        }
    }
}

window.refreshUnregisteredPurchaseOrders = function() {
    fetchUnregisteredPurchaseOrders(unregisteredPurchaseOrdersState.page || 1);
};

window.clearUnregisteredPurchaseOrderFilters = function() {
    document.querySelectorAll('.unregistered-po-filter').forEach(input => {
        input.value = '';
    });
    unregisteredPurchaseOrdersState.filters = {};
    unregisteredPurchaseOrdersState.sort = null;
    unregisteredPurchaseOrdersState.direction = 'desc';
    unregisteredPurchaseOrdersState.page = 1;
    fetchUnregisteredPurchaseOrders(1);
};

window.sortUnregisteredPurchaseOrders = function(field) {
    if (unregisteredPurchaseOrdersState.sort === field) {
        unregisteredPurchaseOrdersState.direction = unregisteredPurchaseOrdersState.direction === 'asc' ? 'desc' : 'asc';
    } else {
        unregisteredPurchaseOrdersState.sort = field;
        unregisteredPurchaseOrdersState.direction = 'asc';
    }
    unregisteredPurchaseOrdersState.page = 1;
    fetchUnregisteredPurchaseOrders(1);
};

window.goToUnregisteredPurchaseOrdersPage = function(page) {
    const lastPage = unregisteredPurchaseOrdersState.pagination ? unregisteredPurchaseOrdersState.pagination.last_page : 1;
    if (page < 1 || page > lastPage) return;
    fetchUnregisteredPurchaseOrders(page);
};

window.viewUnregisteredPurchaseOrder = function(id) {
    if (!id || !window.supplierRoutes?.purchaseOrderDetail) return;
    const url = window.supplierRoutes.purchaseOrderDetail.replace(':id', id);
    window.open(url, '_blank', 'noopener');
};

async function fetchSuppliers(page) {
    try {
        if (page !== undefined) currentPage = page;

        const params = new URLSearchParams();
        params.set('page', currentPage);
        params.set('perPage', perPage);

        Object.keys(searchValues).forEach(key => {
            params.set(`search[${key}]`, searchValues[key]);
        });

        if (generalSearch) {
            params.set('search[general]', generalSearch);
        }

        if (supplierFilters.status && supplierFilters.status !== 'all') {
            params.set('filter[status]', supplierFilters.status);
        }
        if (supplierFilters.type && supplierFilters.type !== 'all') {
            params.set('filter[type]', supplierFilters.type);
        }

        const query = params.toString();
        const response = await fetch(`${window.supplierRoutes.fetchData}${query ? `?${query}` : ''}`, {
            headers: { 'Accept': 'application/json' }
        });
        const result = await response.json();

        if (result.suppliers) {
            suppliers = result.suppliers;
            pagination = result.pagination;
            renderView();
            updateStats(result.stats);
            renderPagination();
        }
    } catch (error) {
        console.error('Error fetching suppliers:', error);
    }
}

function updateStats(stats) {
    if (stats) {
        document.getElementById('stat-total-suppliers').textContent = stats.total;
        document.getElementById('stat-active-suppliers').textContent = stats.active;
    } else {
        document.getElementById('stat-total-suppliers').textContent = pagination ? pagination.total : suppliers.length;
        document.getElementById('stat-active-suppliers').textContent = suppliers.filter(s => s.status === 'Active').length;
    }
}

function renderPagination() {
    const tablePag = document.getElementById('supplier-table-pagination');
    const cardPag = document.getElementById('supplier-card-pagination');
    if (!pagination || pagination.total === 0) {
        if (tablePag) tablePag.innerHTML = '<div class="flex items-center justify-center py-4 text-xs text-slate-400 italic">No results found</div>';
        if (cardPag) cardPag.innerHTML = '<div class="flex items-center justify-center py-4 text-xs text-slate-400 italic">No results found</div>';
        return;
    }

    const html = buildPaginationHtml();
    if (tablePag) tablePag.innerHTML = html;
    if (cardPag) cardPag.innerHTML = html;
}

function buildPaginationHtml() {
    if (!pagination) return '';

    const { current_page, last_page, per_page, total, from, to } = pagination;
    if (total === 0) return '';

    const maxVisiblePages = 5;
    let pagesHtml = '';

    pagesHtml += `<button onclick="goToPage(${current_page - 1})" class="px-3 py-1.5 text-xs font-bold rounded-lg transition-all ${current_page <= 1 ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600 hover:bg-slate-100'}" ${current_page <= 1 ? 'disabled' : ''}>Previous</button>`;

    let startPage = Math.max(1, current_page - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(last_page, startPage + maxVisiblePages - 1);
    if (endPage - startPage < maxVisiblePages - 1) {
        startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }

    if (startPage > 1) {
        pagesHtml += `<button onclick="goToPage(1)" class="px-3 py-1.5 text-xs font-bold rounded-lg text-slate-600 hover:bg-slate-100">1</button>`;
        if (startPage > 2) pagesHtml += `<span class="px-2 text-slate-400 text-xs">...</span>`;
    }

    for (let i = startPage; i <= endPage; i++) {
        pagesHtml += `<button onclick="goToPage(${i})" class="px-3 py-1.5 text-xs font-bold rounded-lg transition-all ${i === current_page ? 'bg-maroon text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'}">${i}</button>`;
    }

    if (endPage < last_page) {
        if (endPage < last_page - 1) pagesHtml += `<span class="px-2 text-slate-400 text-xs">...</span>`;
        pagesHtml += `<button onclick="goToPage(${last_page})" class="px-3 py-1.5 text-xs font-bold rounded-lg text-slate-600 hover:bg-slate-100">${last_page}</button>`;
    }

    pagesHtml += `<button onclick="goToPage(${current_page + 1})" class="px-3 py-1.5 text-xs font-bold rounded-lg transition-all ${current_page >= last_page ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600 hover:bg-slate-100'}" ${current_page >= last_page ? 'disabled' : ''}>Next</button>`;

    return `
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="text-xs text-slate-500 font-medium">
                Page <span class="font-bold text-slate-700">${current_page}</span> of <span class="font-bold text-slate-700">${last_page}</span>
                <span class="text-slate-300 mx-2">|</span>
                Showing <span class="font-bold text-slate-700">${from}</span> to <span class="font-bold text-slate-700">${to}</span> of <span class="font-bold text-slate-700">${total}</span> entries
            </div>
            <div class="flex items-center space-x-1">
                ${pagesHtml}
            </div>
        </div>
    `;
}

window.goToPage = function(page) {
    if (page < 1 || page > (pagination ? pagination.last_page : 1)) return;
    fetchSuppliers(page);
};

window.applySupplierFilters = function() {
    const statusSelect = document.getElementById('supplier-filter-status');
    const typeSelect = document.getElementById('supplier-filter-type');
    supplierFilters = {
        status: statusSelect ? statusSelect.value : 'all',
        type: typeSelect ? typeSelect.value : 'all'
    };
    currentPage = 1;
    fetchSuppliers(1);
    toggleModal('filter-modal', false);
};

window.resetSupplierFilters = function() {
    supplierFilters = { status: 'all', type: 'all' };
    const statusSelect = document.getElementById('supplier-filter-status');
    const typeSelect = document.getElementById('supplier-filter-type');
    if (statusSelect) statusSelect.value = 'all';
    if (typeSelect) typeSelect.value = 'all';
    currentPage = 1;
    fetchSuppliers(1);
    toggleModal('filter-modal', false);
};

window.toggleView = function(view) {
    currentView = view;
    perPage = (view === 'table') ? 50 : 12;
    currentPage = 1;

    const tableBtn = document.getElementById('view-table-btn');
    const cardBtn = document.getElementById('view-card-btn');

    if (view === 'table') {
        tableBtn.classList.add('bg-maroon', 'text-white');
        tableBtn.classList.remove('bg-white', 'text-slate-600');
        cardBtn.classList.add('bg-white', 'text-slate-600');
        cardBtn.classList.remove('bg-maroon', 'text-white');
    } else {
        cardBtn.classList.add('bg-maroon', 'text-white');
        cardBtn.classList.remove('bg-white', 'text-slate-600');
        tableBtn.classList.add('bg-white', 'text-slate-600');
        tableBtn.classList.remove('bg-maroon', 'text-white');
    }

    fetchSuppliers(1);
};

function renderView() {
    const tableContainer = document.getElementById('supplier-table-container');
    const cardContainer = document.getElementById('supplier-card-container');

    if (currentView === 'table') {
        tableContainer.classList.remove('hidden');
        cardContainer.classList.add('hidden');
        document.getElementById('supplier-table-pagination').classList.remove('hidden');
        document.getElementById('supplier-card-pagination').classList.add('hidden');
        renderTable();
    } else {
        tableContainer.classList.add('hidden');
        cardContainer.classList.remove('hidden');
        document.getElementById('supplier-table-pagination').classList.add('hidden');
        document.getElementById('supplier-card-pagination').classList.remove('hidden');
        renderCards();
    }

    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

function initMapAndAutocomplete() {
    const mapContainer = document.getElementById('osm-map-container');
    if (mapContainer) {
        osmMap = L.map('osm-map-container').setView([14.5995, 120.9842], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(osmMap);

        osmMarker = L.marker([14.5995, 120.9842], { draggable: true }).addTo(osmMap);

        osmMarker.on('dragend', async function(e) {
            const latlng = e.target.getLatLng();
            try {
                const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latlng.lat}&lon=${latlng.lng}`);
                const data = await response.json();
                if (data.display_name) {
                    document.getElementById('supplier-address-input').value = data.display_name;
                }
            } catch (err) {
                console.error('Reverse geocoding failed:', err);
            }
        });
    }

    const editMapContainer = document.getElementById('edit-osm-map-container');
    if (editMapContainer) {
        editOsmMap = L.map('edit-osm-map-container').setView([14.5995, 120.9842], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(editOsmMap);

        editOsmMarker = L.marker([14.5995, 120.9842], { draggable: true }).addTo(editOsmMap);

        editOsmMarker.on('dragend', async function(e) {
            const latlng = e.target.getLatLng();
            try {
                const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latlng.lat}&lon=${latlng.lng}`);
                const data = await response.json();
                if (data.display_name) {
                    document.getElementById('edit-supplier-address').value = data.display_name;
                }
            } catch (err) {
                console.error('Reverse geocoding failed:', err);
            }
        });
    }

    setupOsmAutocomplete('supplier-address-input', 'supplier-country-select', true, 'add');
    setupOsmAutocomplete('edit-supplier-address', 'edit-supplier-country-select', true, 'edit');
}

function setupOsmAutocomplete(inputId, countrySelectId, updateOsmMap, mode) {
    const addressInput = document.getElementById(inputId);
    const countrySelect = document.getElementById(countrySelectId);
    if (!addressInput) return;

    let debounceTimer;
    const suggestionContainer = document.createElement('div');
    suggestionContainer.className = 'osm-suggestions hidden';
    addressInput.parentNode.appendChild(suggestionContainer);

    addressInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const query = this.value;
        const countryCode = countrySelect ? countrySelect.value.toLowerCase() : 'ph';

        if (query.length < 3) {
            suggestionContainer.classList.add('hidden');
            return;
        }

        debounceTimer = setTimeout(async () => {
            try {
                const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&countrycodes=${countryCode}&limit=5&addressdetails=1`);
                const data = await response.json();

                if (data.length > 0) {
                    suggestionContainer.innerHTML = data.map(item => `
                        <div class="osm-suggestion-item"
                             data-lat="${item.lat}"
                             data-lon="${item.lon}"
                             data-name="${item.display_name}">
                            ${item.display_name}
                        </div>
                    `).join('');
                    suggestionContainer.classList.remove('hidden');

                    suggestionContainer.querySelectorAll('.osm-suggestion-item').forEach(item => {
                        item.addEventListener('click', function() {
                            const lat = this.getAttribute('data-lat');
                            const lon = this.getAttribute('data-lon');
                            const name = this.getAttribute('data-name');

                            addressInput.value = name;
                            suggestionContainer.classList.add('hidden');

                            if (updateOsmMap) {
                                if (mode === 'add' && osmMap && osmMarker) {
                                    const newLatLng = new L.LatLng(lat, lon);
                                    osmMap.setView(newLatLng, 16);
                                    osmMarker.setLatLng(newLatLng);
                                } else if (mode === 'edit' && editOsmMap && editOsmMarker) {
                                    const newLatLng = new L.LatLng(lat, lon);
                                    editOsmMap.setView(newLatLng, 16);
                                    editOsmMarker.setLatLng(newLatLng);
                                }
                            }
                        });
                    });
                } else {
                    suggestionContainer.classList.add('hidden');
                }
            } catch (err) {
                console.error('Nominatim search failed:', err);
            }
        }, 500);
    });

    document.addEventListener('click', function(e) {
        if (!addressInput.contains(e.target) && !suggestionContainer.contains(e.target)) {
            suggestionContainer.classList.add('hidden');
        }
    });
}

function setupLifecycleListeners() {
    bindLifecycleCalculator(
        document.getElementById('supplier-start-date'),
        document.getElementById('supplier-end-date'),
        document.getElementById('supplier-lifecycle')
    );

    bindLifecycleCalculator(
        document.getElementById('edit-supplier-start-date'),
        document.getElementById('edit-supplier-end-date'),
        document.getElementById('edit-supplier-lifecycle')
    );
}

function bindLifecycleCalculator(startInput, endInput, lifecycleDisplay) {
    if (!startInput || !endInput || !lifecycleDisplay) return;

    const calculate = () => updateLifecycleFromBackend(startInput.value, endInput.value, lifecycleDisplay);
    startInput.addEventListener('change', calculate);
    endInput.addEventListener('change', calculate);
}

async function updateLifecycleFromBackend(startDate, endDate, lifecycleDisplay) {
    if (!lifecycleDisplay) return;

    if (!startDate || !endDate) {
        lifecycleDisplay.value = '';
        return;
    }

    lifecycleDisplay.value = 'Calculating...';

    const params = new URLSearchParams({
        start_date: startDate,
        end_date: endDate
    });

    try {
        const response = await fetch(`${window.supplierRoutes.fetchLifecycle}?${params.toString()}`, {
            headers: { 'Accept': 'application/json' }
        });
        const result = await response.json();
        lifecycleDisplay.value = result.lifecycle || (response.ok ? 'N/A' : 'Invalid date range');
    } catch (error) {
        console.error('Error calculating supplier lifecycle:', error);
        lifecycleDisplay.value = 'N/A';
    }
}

function setupImageDragAndDrop() {
    ['profile-drop-zone', 'edit-profile-drop-zone'].forEach(id => {
        const zone = document.getElementById(id);
        if (!zone) return;

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            zone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
            }, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            zone.addEventListener(eventName, () => zone.classList.add('border-maroon', 'bg-maroon/5'), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            zone.addEventListener(eventName, () => zone.classList.remove('border-maroon', 'bg-maroon/5'), false);
        });

        zone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            const previewId = id.includes('edit') ? 'edit-profile-preview' : 'profile-preview';
            handleImageUpload({ target: { files: files } }, previewId);
        }, false);
    });
}

window.handleImageUpload = async function(event, previewId) {
    const file = event.target.files[0];
    if (!file) return;

    const baseId = previewId.replace('-preview', '');
    const dropZoneId = baseId + '-drop-zone';
    const previewContentId = previewId + '-content';

    const dropZone = document.getElementById(dropZoneId);
    const previewContent = document.getElementById(previewContentId);

    try {
        const base64 = await compressSupplierImage(file);

        if (dropZone) {
            dropZone.style.backgroundImage = `url(${base64})`;
            dropZone.style.backgroundSize = 'cover';
            dropZone.style.backgroundPosition = 'center';
            if (previewContent) previewContent.classList.add('opacity-0');
        }

        const hiddenInputId = baseId.includes('edit') ? 'edit-supplier-picture-base64' : 'add-supplier-picture-base64';
        const hiddenInput = document.getElementById(hiddenInputId);
        if (hiddenInput) hiddenInput.value = base64;

    } catch (error) {
        console.error('Image compression failed:', error);
        alert('Failed to process image. Please try a different one.');
    }
};

async function compressSupplierImage(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onload = (event) => {
            const img = new Image();
            img.src = event.target.result;
            img.onload = () => {
                const canvas = document.createElement('canvas');
                let width = img.width;
                let height = img.height;
                const MAX_SIZE = 800;

                if (width > height) {
                    if (width > MAX_SIZE) {
                        height *= MAX_SIZE / width;
                        width = MAX_SIZE;
                    }
                } else {
                    if (height > MAX_SIZE) {
                        width *= MAX_SIZE / height;
                        height = MAX_SIZE;
                    }
                }

                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);
                resolve(canvas.toDataURL('image/jpeg', 0.7));
            };
            img.onerror = reject;
        };
        reader.onerror = reject;
    });
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));
}

function renderFloatingInfoCell({
    value,
    label,
    icon = 'info',
    cellClass = 'py-4 px-4 text-slate-600 align-middle',
    textClass = 'text-slate-600'
}) {
    const displayValue = value === null || value === undefined || value === '' ? 'N/A' : value;
    const safeValue = escapeHtml(displayValue);
    const safeLabel = escapeHtml(label);

    return `
        <td class="${cellClass}">
            <div class="w-full break-words whitespace-normal line-clamp-2 leading-tight overflow-hidden text-xs relative group/tooltip" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; max-height: 2.5rem;" onmousemove="updateTooltipPos(event)">
                <span class="${textClass}">${safeValue}</span>
                <div class="fixed invisible group-hover/tooltip:visible opacity-0 group-hover/tooltip:opacity-100 transition-opacity duration-200 w-72 p-4 bg-slate-900 text-white text-[12px] rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.3)] z-[9999] pointer-events-none break-words whitespace-normal leading-relaxed border border-slate-700/50 backdrop-blur-md custom-tooltip-box">
                    <div class="flex items-center gap-2 mb-2 pb-2 border-b border-white/10">
                        <i data-lucide="${icon}" class="w-3.5 h-3.5 text-gold"></i>
                        <span class="font-black text-gold uppercase tracking-[0.2em] text-[10px]">${safeLabel}</span>
                    </div>
                    ${safeValue}
                </div>
            </div>
        </td>
    `;
}

function renderTable() {
    const tbody = document.getElementById('supplier-tbody');
    if (!tbody) return;

    if (suppliers.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="10" class="py-20 text-center text-slate-400 italic">
                    <div class="flex flex-col items-center">
                        <i data-lucide="search-x" class="w-10 h-10 mb-2 opacity-20"></i>
                        No suppliers match your search criteria
                    </div>
                </td>
            </tr>
        `;
        if (typeof lucide !== 'undefined') lucide.createIcons();
        return;
    }

    tbody.innerHTML = suppliers.map(s => {
        const isNew = new Date(s.created_at) > new Date(new Date().setMonth(new Date().getMonth() - 3));
        const profilePic = s.Supplier_Image || s.supplier_picture;
        return `
            <tr onclick="openViewModal(${s.id})" class="hover:bg-slate-50 transition-colors border-b border-slate-100 cursor-pointer group">
                <td class="py-4 px-4">
                    <div class="flex flex-col">
                        <span class="font-bold text-slate-800">${s.supplier_code}</span>
                        <span class="text-[10px] px-2 py-0.5 rounded-full w-fit ${isNew ? 'tag-newly' : 'tag-old'}">
                            ${isNew ? 'Newly' : 'Old'}
                        </span>
                    </div>
                </td>
                <td class="py-4 px-4 text-center">
                    <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center overflow-hidden border border-slate-200 mx-auto">
                        ${profilePic ? `<img src="${profilePic}" class="w-full h-full object-cover">` : `<i data-lucide="image" class="w-5 h-5 text-slate-400"></i>`}
                    </div>
                </td>
                ${renderFloatingInfoCell({
                    value: s.name,
                    label: 'Supplier Name',
                    icon: 'info',
                    cellClass: 'py-4 px-4 text-slate-700 align-middle',
                    textClass: 'font-semibold text-slate-700'
                })}
                ${renderFloatingInfoCell({
                    value: s.contact_number,
                    label: 'Contact Number',
                    icon: 'phone',
                    cellClass: 'py-4 px-4 text-slate-600 align-middle'
                })}
                ${renderFloatingInfoCell({
                    value: s.contact_person,
                    label: 'Contact Person',
                    icon: 'user'
                })}
                ${renderFloatingInfoCell({
                    value: s.tin,
                    label: 'TIN',
                    icon: 'hash',
                    cellClass: 'py-4 px-4 text-slate-600 font-mono align-middle',
                    textClass: 'font-mono text-slate-600'
                })}
                ${renderFloatingInfoCell({
                    value: s.address,
                    label: 'Full Address',
                    icon: 'map-pin'
                })}
                <td class="py-4 px-4">
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider ${s.status === 'Active' ? 'tag-active' : 'tag-inactive'}">
                        ${s.status}
                    </span>
                </td>
                <td class="py-4 px-4">
                    <div class="flex flex-col text-[10px] text-slate-500">
                        <span class="font-bold text-slate-700">${s.lifecycle || 'N/A'}</span>
                        <span class="flex items-center gap-1" title="Date Range"><i data-lucide="calendar-range" class="w-3 h-3 text-slate-400"></i> ${s.start_date || '...'} - ${s.end_date || '...'}</span>
                    </div>
                </td>
                <td class="py-4 px-4 text-center" onclick="event.stopPropagation()">
                    <div class="flex items-center justify-center space-x-2">
                        <button onclick="openViewModal(${s.id})" class="p-2 hover:bg-slate-100 text-slate-600 rounded-lg transition-colors" title="View Details">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                        </button>
                        <button onclick="openEditModal(${s.id})" class="p-2 hover:bg-blue-50 text-blue-600 rounded-lg transition-colors" title="Edit">
                            <i data-lucide="edit-3" class="w-4 h-4"></i>
                        </button>
                        <button onclick="openDeleteModal(${s.id})" class="p-2 hover:bg-red-50 text-red-600 rounded-lg transition-colors" title="Delete">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function renderCards() {
    const cardGrid = document.getElementById('supplier-card-grid');
    if (!cardGrid) return;

    if (suppliers.length === 0) {
        cardGrid.innerHTML = `
            <div class="col-span-full py-20 text-center bg-white rounded-3xl border border-dashed border-slate-200">
                <i data-lucide="search-x" class="w-12 h-12 text-slate-300 mx-auto mb-4"></i>
                <p class="text-slate-400 font-bold uppercase tracking-widest text-xs">No suppliers match your search criteria</p>
            </div>
        `;
        if (typeof lucide !== 'undefined') lucide.createIcons();
        return;
    }

    cardGrid.innerHTML = suppliers.map(s => {
        const isNew = new Date(s.created_at) > new Date(new Date().setMonth(new Date().getMonth() - 3));
        const profilePic = s.Supplier_Image || s.supplier_picture;
        return `
            <div class="supplier-card bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex flex-col space-y-4">
                <div class="flex justify-between items-start">
                    <div class="w-16 h-16 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center overflow-hidden">
                        ${profilePic ? `<img src="${profilePic}" class="w-full h-full object-cover">` : `<i data-lucide="truck" class="w-8 h-8 text-slate-300"></i>`}
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider ${s.status === 'Active' ? 'tag-active' : 'tag-inactive'}">
                            ${s.status}
                        </span>
                        <span class="text-[10px] px-2 py-0.5 rounded-full ${isNew ? 'tag-newly' : 'tag-old'}">
                            ${isNew ? 'Newly' : 'Old'}
                        </span>
                    </div>
                </div>

                <div>
                    <h4 class="font-extrabold text-slate-800 truncate">${s.name}</h4>
                    <p class="text-xs text-slate-400 font-bold">${s.supplier_code}</p>
                </div>

                <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-50">
                    <div class="flex flex-col">
                        <span class="text-[10px] text-slate-400 uppercase font-bold">Contact</span>
                        <span class="text-xs text-slate-700 truncate">${s.contact_person || 'N/A'}</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[10px] text-slate-400 uppercase font-bold">Phone</span>
                        <span class="text-xs text-slate-700">${s.contact_number || 'N/A'}</span>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-3">
                    <div class="text-[10px] text-slate-500">
                        <span class="block">Lifecycle:</span>
                        <span class="font-bold text-slate-700">${s.lifecycle || 'N/A'}</span>
                    </div>
                    <div class="flex gap-1">
                        <button onclick="openViewModal(${s.id})" class="p-2 hover:bg-slate-100 text-slate-600 rounded-lg transition-colors">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                        </button>
                        <button onclick="openEditModal(${s.id})" class="p-2 hover:bg-blue-50 text-blue-600 rounded-lg transition-colors">
                            <i data-lucide="edit-3" class="w-4 h-4"></i>
                        </button>
                        <button onclick="openDeleteModal(${s.id})" class="p-2 hover:bg-red-50 text-red-600 rounded-lg transition-colors">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

window.toggleModal = function(modalId, open) {
    const modal = document.getElementById(modalId);
    if (!modal) return;

    if (open) {
        modal.classList.remove("hidden");

        if (modalId === 'add-supplier-modal' && osmMap) {
            setTimeout(() => osmMap.invalidateSize(), 250);
        }
        if (modalId === 'edit-supplier-modal' && editOsmMap) {
            setTimeout(() => editOsmMap.invalidateSize(), 250);
        }
    } else {
        modal.classList.add("hidden");

        if (modalId === 'view-supplier-modal') {
            if (purchasedItemsState.abortController) {
                purchasedItemsState.abortController.abort();
            }
            if (unregisteredPurchaseOrdersState.abortController) {
                unregisteredPurchaseOrdersState.abortController.abort();
            }
            currentViewSupplierId = null;
        }
    }
};

window.confirmAddSupplier = async function() {
    const btn = event.currentTarget;
    if (btn.disabled) return;

    const form = document.getElementById('add-supplier-form');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin mr-2"></i> Registering...';
    if (typeof lucide !== 'undefined') lucide.createIcons();

    try {
        const response = await fetch(window.supplierRoutes.create, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();
        if (result.success) {
            toggleModal('confirm-add-modal', false);
            toggleModal('add-supplier-modal', false);
            toggleModal('success-add-modal', true);
            form.reset();

            const dropZone = document.getElementById('profile-drop-zone');
            const previewContent = document.getElementById('profile-preview-content');
            if (dropZone) dropZone.style.backgroundImage = 'none';
            if (previewContent) previewContent.classList.remove('opacity-0');
            document.getElementById('add-supplier-picture-base64').value = '';

            currentPage = 1;
            fetchSuppliers(1);
        } else {
            alert(result.message || 'Failed to register supplier');
        }
    } catch (error) {
        console.error('Error adding supplier:', error);
        alert('An error occurred. Please check the console.');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Yes, Register';
    }
};

window.confirmUpdateSupplier = async function() {
    const btn = event.currentTarget;
    if (btn.disabled) return;

    const id = document.getElementById('edit-supplier-id') ? document.getElementById('edit-supplier-id').value : null;
    if (!id) {
        console.error("No ID found for update");
        return;
    }

    const form = document.getElementById('edit-supplier-form');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin mr-2"></i> Updating...';
    if (typeof lucide !== 'undefined') lucide.createIcons();

    try {
        const response = await fetch(window.supplierRoutes.update.replace(':id', id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();
        if (result.success) {
            toggleModal('confirm-edit-modal', false);
            toggleModal('edit-supplier-modal', false);
            toggleModal('success-edit-modal', true);
            fetchSuppliers(currentPage);
        } else {
            alert(result.message || 'Failed to update supplier');
        }
    } catch (error) {
        console.error('Error updating supplier:', error);
        alert('An error occurred. Please check the console.');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Yes, Update';
    }
};

window.openViewModal = function(id) {
    const supplier = suppliers.find(s => s.id === id);
    if (!supplier) return;
    const supplierId = Number(supplier.id);

    document.querySelectorAll('#view-supplier-name').forEach(el => el.textContent = supplier.name);
    document.querySelectorAll('#view-supplier-code').forEach(el => el.textContent = supplier.supplier_code);
    document.querySelectorAll('#view-supplier-contact').forEach(el => el.textContent = supplier.contact_number || 'N/A');
    document.querySelectorAll('#view-supplier-person').forEach(el => el.textContent = supplier.contact_person || 'N/A');
    document.querySelectorAll('#view-supplier-tin').forEach(el => el.textContent = supplier.tin || 'N/A');
    document.querySelectorAll('#view-supplier-address').forEach(el => el.textContent = supplier.address || 'N/A');

    const statusTag = document.getElementById('view-supplier-status-tag');
    if (statusTag) {
        statusTag.textContent = supplier.status;
        statusTag.className = `px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider ${supplier.status === 'Active' ? 'tag-active' : 'tag-inactive'}`;
    }

    document.getElementById('view-supplier-lifecycle').textContent = supplier.lifecycle || 'N/A';

    const picContainer = document.getElementById('view-supplier-pic');
    const profilePic = supplier.Supplier_Image || supplier.supplier_picture;
    if (profilePic) {
        picContainer.innerHTML = `<img src="${profilePic}" class="w-full h-full object-cover">`;
    } else {
        picContainer.innerHTML = `<i data-lucide="truck" class="w-16 h-16 text-slate-200"></i>`;
    }

    resetPurchasedItemsState(supplierId);
    resetUnregisteredPurchaseOrdersState();
    switchViewTab('purchase');
    toggleModal('view-supplier-modal', true);

    if (typeof lucide !== 'undefined') lucide.createIcons();
};

window.switchViewTab = function(tab) {
    currentActiveViewTab = tab;

    const purchaseBtn = document.getElementById('tab-btn-purchase');
    const ledgerBtn = document.getElementById('tab-btn-ledger');
    const purchaseContainer = document.getElementById('view-container-purchase');
    const ledgerContainer = document.getElementById('view-container-ledger');
    const purchasedSummary = document.getElementById('purchased-items-summary');
    const purchasedControls = document.getElementById('purchased-items-controls');

    if (tab === 'purchase') {
        purchaseBtn.classList.add('border-maroon', 'text-maroon');
        purchaseBtn.classList.remove('border-transparent', 'text-slate-400');
        ledgerBtn.classList.add('border-transparent', 'text-slate-400');
        ledgerBtn.classList.remove('border-maroon', 'text-maroon');

        purchaseContainer.classList.remove('hidden');
        ledgerContainer.classList.add('hidden');
        if (purchasedSummary) purchasedSummary.classList.remove('hidden');
        if (purchasedControls) purchasedControls.classList.remove('hidden');

        if (currentViewSupplierId && !purchasedItemsState.loaded && !purchasedItemsState.loading) {
            fetchPurchasedItems(purchasedItemsState.page || 1);
        }
    } else {
        ledgerBtn.classList.add('border-maroon', 'text-maroon');
        ledgerBtn.classList.remove('border-transparent', 'text-slate-400');
        purchaseBtn.classList.add('border-transparent', 'text-slate-400');
        purchaseBtn.classList.remove('border-maroon', 'text-maroon');

        ledgerContainer.classList.remove('hidden');
        purchaseContainer.classList.add('hidden');
        if (purchasedSummary) purchasedSummary.classList.add('hidden');
        if (purchasedControls) purchasedControls.classList.add('hidden');

        if (currentViewSupplierId && !unregisteredPurchaseOrdersState.loaded && !unregisteredPurchaseOrdersState.loading) {
            fetchUnregisteredPurchaseOrders(unregisteredPurchaseOrdersState.page || 1);
        }
    }
};

window.openEditModal = function(id) {
    const supplier = suppliers.find(s => s.id === id);
    if (!supplier) return;

    if (document.getElementById('edit-supplier-id')) {
        document.getElementById('edit-supplier-id').value = supplier.id;
    }
    document.getElementById('edit-supplier-code').value = supplier.supplier_code;
    document.getElementById('edit-supplier-name').value = supplier.name;
    document.getElementById('edit-supplier-contact').value = supplier.contact_number || '';
    document.getElementById('edit-supplier-person').value = supplier.contact_person || '';
    document.getElementById('edit-supplier-tin').value = supplier.tin || '';
    document.getElementById('edit-supplier-address').value = supplier.address || '';
    document.getElementById('edit-supplier-terms').value = supplier.payment_terms || '';
    document.getElementById('edit-supplier-start-date').value = supplier.start_date || '';
    document.getElementById('edit-supplier-end-date').value = supplier.end_date || '';
    document.getElementById('edit-supplier-lifecycle').value = supplier.lifecycle || 'N/A';
    document.getElementById('edit-supplier-telefax').value = supplier.telefax || '';

    const profilePic = supplier.Supplier_Image || supplier.supplier_picture;
    const editDropZone = document.getElementById('edit-profile-drop-zone');
    const editPreviewContent = document.getElementById('edit-profile-preview-content');
    const editHiddenInput = document.getElementById('edit-supplier-picture-base64');

    if (profilePic) {
        if (editDropZone) {
            editDropZone.style.backgroundImage = `url(${profilePic})`;
            editDropZone.style.backgroundSize = 'cover';
            editDropZone.style.backgroundPosition = 'center';
            if (editPreviewContent) editPreviewContent.classList.add('opacity-0');
        }
        if (editHiddenInput) editHiddenInput.value = profilePic;
    } else {
        if (editDropZone) {
            editDropZone.style.backgroundImage = 'none';
            if (editPreviewContent) editPreviewContent.classList.remove('opacity-0');
        }
        if (editHiddenInput) editHiddenInput.value = '';
    }

    if (editOsmMap && editOsmMarker) {
        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(supplier.address || supplier.name)}&limit=1`)
            .then(res => res.json())
            .then(data => {
                if (data && data[0]) {
                    const newLatLng = new L.LatLng(data[0].lat, data[0].lon);
                    editOsmMap.setView(newLatLng, 16);
                    editOsmMarker.setLatLng(newLatLng);
                }
            })
            .catch(err => console.error("Map positioning failed:", err));

        setTimeout(() => editOsmMap.invalidateSize(), 200);
    }

    toggleModal('edit-supplier-modal', true);
};

window.openDeleteModal = function(id) {
    supplierToDelete = id;
    toggleModal('confirm-delete-modal', true);
};

window.confirmDeleteSupplier = async function() {
    if (!supplierToDelete) return;

    try {
        const response = await fetch(window.supplierRoutes.delete.replace(':id', supplierToDelete), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
            }
        });

        const result = await response.json();
        if (result.success) {
            toggleModal('confirm-delete-modal', false);
            toggleModal('success-delete-modal', true);
            fetchSuppliers(currentPage);
        } else {
            alert(result.message || 'Failed to delete supplier');
        }
    } catch (error) {
        console.error('Error deleting supplier:', error);
        alert('An error occurred. Please check the console.');
    } finally {
        supplierToDelete = null;
    }
};

window.onCardsSearch = function() {
    const input = document.getElementById('cards-search-input');
    generalSearch = input ? input.value.trim() : '';
    currentPage = 1;
    fetchSuppliers(1);
};

window.updateTooltipPos = function(e) {
    const tooltips = document.querySelectorAll('.custom-tooltip-box');
    const x = e.clientX + 20;
    const y = e.clientY + 20;

    tooltips.forEach(tooltip => {
        tooltip.style.left = x + 'px';
        tooltip.style.top = y + 'px';
    });
};
