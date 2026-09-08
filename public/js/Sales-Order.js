document.addEventListener('DOMContentLoaded', () => {
    if (typeof lucide !== 'undefined') { lucide.createIcons(); }

    // Auto-open proceed modal from Sales Note edit redirect (Remaining Items / Open note)
    const proceedData = sessionStorage.getItem('proceed_note_data');
    if (proceedData) {
        sessionStorage.removeItem('proceed_note_data');
        try {
            const data = JSON.parse(proceedData);
            if (data.noteId) {
                setTimeout(() => {
                    // Switch to active tab
                    const activeBtn = document.querySelector('[data-tab="all"], .sales-order-tab');
                    if (activeBtn && typeof window.switchSalesOrderTab === 'function') {
                        window.switchSalesOrderTab('all');
                    }
                    window.openProceedModal(data.noteId);
                }, 500);
            }
        } catch (e) {
            console.error('Failed to parse proceed_note_data:', e);
        }
    }
});

window.toggleModal = function(modalId, show) {
    const modal = document.getElementById(modalId);
    if (!modal) {
        console.error('Modal not found:', modalId);
        return;
    }
    if (show) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    } else {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        const anyVisible = document.querySelector('.fixed.inset-0:not(.hidden)');
        if (!anyVisible) document.body.style.overflow = 'auto';
    }
};

async function fetchJsonOrThrow(url, options = {}, label = 'Request') {
    const headers = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(options.headers || {}),
    };
    const response = await fetch(url, { ...options, headers });
    const contentType = response.headers.get('content-type') || '';

    if (!response.ok) {
        const body = await response.text();
        throw new Error(label + ' failed (' + response.status + '): ' + body.slice(0, 200));
    }

    if (!contentType.includes('application/json')) {
        const body = await response.text();
        throw new Error(label + ' expected JSON but received ' + contentType + ': ' + body.slice(0, 200));
    }

    return response.json();
}

window.switchSalesOrderTab = function(tab) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.sales-order-tab').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + tab)?.classList.add('active');
    document.getElementById('tab-content-' + tab)?.classList.remove('hidden');
    if (tab === 'all' && typeof loadAllOrders === 'function') {
        loadAllOrders(window._allOrdersStatus || 'All');
    }
};

window.filterTable = function(tbodyId, colIndex, value) {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return;
    const filter = value.toLowerCase();
    Array.from(tbody.getElementsByTagName('tr')).forEach(row => {
        const cell = row.getElementsByTagName('td')[colIndex];
        if (!cell) return;
        const text = cell.textContent || '';
        const input = cell.querySelector('input');
        const val = input ? input.value : text;
        row.style.display = val.toLowerCase().includes(filter) ? '' : 'none';
    });
};

window.addInvoiceInput = function(value = '') {
    const container = document.getElementById('invoice-inputs-container');
    const row = document.createElement('div');
    row.className = 'invoice-row flex items-center space-x-3';
    row.innerHTML = `
        <div class="flex-1">
            <p class="text-[9px] text-slate-400 font-bold mb-1 ml-1 uppercase">Invoice No.</p>
            <input type="text" class="invoice-input w-full px-3 py-2.5 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-maroon/20 outline-none transition-all" placeholder="Enter invoice number..." value="${String(value || '').replace(/"/g, '&quot;')}">
        </div>
        <button onclick="this.closest('.invoice-row').remove()" class="mt-5 p-2 bg-red-50 text-red-400 hover:bg-red-100 hover:text-red-600 rounded-lg transition-all">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    `;
    container.appendChild(row);
    if (typeof lucide !== 'undefined') lucide.createIcons();
};

window.loadSalesOrderDashboard = async function() {
    try {
        const res = await fetch(window.salesOrderRoutes?.dashboardUrl || '/admin/sales/sales-order/dashboard');
        const r = await res.json();
        if (r.success) {
            document.getElementById('card-open-notes').textContent = r.open_notes.toLocaleString();
            document.getElementById('card-partial-po').textContent = r.partial_po.toLocaleString();
            document.getElementById('card-closed-po').textContent = r.closed_po.toLocaleString();
            const totalEl = document.getElementById('card-total-so');
            if (totalEl && r.total_so !== undefined) totalEl.textContent = r.total_so.toLocaleString();
        }
    } catch (e) { console.error('Error loading dashboard:', e); }
};

window.filterActiveByStatus = function(status) {
    // Switch to All Orders tab
    const tabBtn = document.getElementById('tab-all');
    if (tabBtn && !tabBtn.classList.contains('active')) {
        switchSalesOrderTab('all');
    }
    loadAllOrders(status);
};

// ===== ALL ORDERS TAB (server-paginated) =====
window._allOrdersPage = 1;
window._allOrdersPageSize = 50;
window._allOrdersStatus = 'All';
window._allOrdersSearch = '';
window._allOrdersTotalPages = 0;
let _allOrdersSearchFields = {};
let _allOrdersSearchDebounce = null;

window.loadAllOrders = async function(status) {
    if (status) window._allOrdersStatus = status;
    window._allOrdersPage = 1;
    window._allOrdersSearch = '';
    _allOrdersSearchFields = {};
    document.querySelectorAll('#tab-content-all .column-search-input').forEach(el => el.value = '');
    const badge = document.getElementById('all-orders-filter-badge');
    if (badge) badge.textContent = 'Showing: ' + (status || 'All');
    await fetchAllOrdersPage();
};

async function fetchAllOrdersPage() {
    const tbody = document.getElementById('all-orders-tbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="7" class="p-4 text-center text-slate-300 italic">Loading orders...</td></tr>';
    try {
        const params = new URLSearchParams();
        params.set('page', window._allOrdersPage);
        params.set('perPage', window._allOrdersPageSize);
        params.set('status', window._allOrdersStatus);
        if (window._allOrdersSearch) params.set('search', window._allOrdersSearch);
        const url = (window.salesOrderRoutes?.allOrdersDataUrl || '/admin/sales/sales-order/all-orders-data') + '?' + params.toString();
        const res = await fetch(url);
        const r = await res.json();
        if (r.success) {
            window._allOrdersTotalPages = r.last_page || 1;
            renderAllOrdersPage(r.notes || [], r.total || 0);
        } else {
            tbody.innerHTML = '<tr><td colspan="7" class="p-4 text-center text-slate-300 italic">No orders found.</td></tr>';
            setPagination('all-orders', 0);
        }
    } catch (e) {
        console.error('Error loading all orders:', e);
        tbody.innerHTML = '<tr><td colspan="7" class="p-4 text-center text-red-400 italic">Error loading orders.</td></tr>';
    }
}

window.searchAllOrdersField = function(colIndex, value) {
    _allOrdersSearchFields[colIndex] = value;
    clearTimeout(_allOrdersSearchDebounce);
    _allOrdersSearchDebounce = setTimeout(() => {
        const parts = Object.values(_allOrdersSearchFields).filter(v => v !== '');
        window._allOrdersSearch = parts.join(' ');
        window._allOrdersPage = 1;
        fetchAllOrdersPage();
    }, 300);
};

function renderAllOrdersPage(notes, total) {
    const tbody = document.getElementById('all-orders-tbody');
    if (!tbody) return;
    if (notes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="p-4 text-center text-slate-300 italic">No orders found.</td></tr>';
    } else {
        tbody.innerHTML = notes.map(n => {
            const status = (n.status || '').toLowerCase();
            const isOpen = status === 'open' || status === 'partial';
            const isClosed = status === 'closed';
            const sourceType = (n.source_type || '').toLowerCase();
            const hasOnlineReport = isClosed && sourceType === 'online_report' && n.online_report_id;
            
            // Build action buttons
            let buttons = '';
            if (isOpen) {
                buttons = `<button onclick="openProceedModal(${n.id})" class="px-4 py-1.5 bg-maroon text-white text-[10px] font-bold rounded-lg hover:bg-maroon-800 transition-all shadow-md flex items-center space-x-1 mx-auto">
                             <i data-lucide="arrow-right" class="w-3 h-3 text-gold"></i>
                             <span>Proceed</span>
                            </button>`;
            } else if (hasOnlineReport) {
                buttons = `<button onclick="printOnlineReport(${n.online_report_id})" class="inline-flex items-center justify-center min-w-[86px] px-3 py-1.5 bg-blue-600 text-white text-[10px] font-bold rounded-lg hover:bg-blue-700 transition-all shadow-md space-x-1 mx-auto">
                             <i data-lucide="printer" class="w-3 h-3"></i>
                             <span>Print</span>
                            </button>`;
            } else if (isClosed) {
                buttons = `<button onclick="openHistoryPrintModal(${n.id})" class="inline-flex items-center justify-center min-w-[86px] px-3 py-1.5 bg-blue-600 text-white text-[10px] font-bold rounded-lg hover:bg-blue-700 transition-all shadow-md space-x-1 mx-auto">
                             <i data-lucide="printer" class="w-3 h-3"></i>
                             <span>Print</span>
                            </button>`;
            } else {
                buttons = `<span class="inline-flex items-center justify-center min-w-[86px] px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest bg-slate-100 text-slate-400 border border-slate-200">Complete</span>`;
            }

            return `
            <tr class="hover:bg-slate-50 transition-colors group table-row-animate">
                <td class="p-4 px-6 font-mono text-xs text-maroon font-bold">${n.sales_number || ''}</td>
                <td class="p-4 px-6 text-slate-700 font-bold">${n.customer_name || ''}</td>
                <td class="p-4 px-6 text-slate-600">${n.order_date || ''}</td>
                <td class="p-4 px-6 text-slate-600">${n.salesman || ''}</td>
                <td class="p-4 px-6 font-bold text-maroon text-right">₱ ${parseFloat(n.net_total || 0).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                <td class="p-4 px-6 text-center">${getSalesOrderStatusBadge(n.status)}</td>
                <td class="p-4 px-6 text-center">${buttons}</td>
            </tr>`;
        }).join('');
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
    setPagination('all-orders', window._allOrdersTotalPages);
}

window.prevAllOrdersPage = function() {
    if (window._allOrdersPage > 1) { window._allOrdersPage--; fetchAllOrdersPage(); }
};

window.nextAllOrdersPage = function() {
    if (window._allOrdersPage < window._allOrdersTotalPages) { window._allOrdersPage++; fetchAllOrdersPage(); }
};

window.changeAllOrdersPerPage = function(val) {
    window._allOrdersPageSize = parseInt(val);
    window._allOrdersPage = 1;
    fetchAllOrdersPage();
};

window._activePageSearch = '';
let _activeSearchDebounce = null;

window.loadActiveNotes = async function(page) {
    const tbody = document.getElementById('active-notes-tbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="7" class="p-4 text-center text-slate-300 italic">Loading active notes...</td></tr>';
    try {
        if (page !== undefined) window._activePage = page;
        else window._activePage = window._activePage || 1;
        const params = new URLSearchParams();
        params.set('page', window._activePage);
        params.set('perPage', 50);
        if (window._activePageSearch) params.set('search', window._activePageSearch);
        const url = (window.salesOrderRoutes?.activeUrl || '/admin/sales/sales-order/active') + '?' + params.toString();
        const res = await fetch(url);
        const r = await res.json();
        if (r.success) {
            window._activeTotalPages = r.last_page || 1;
            window._activeTotal = r.total || 0;
            renderActivePage(r.data || []);
        } else {
            tbody.innerHTML = '<tr><td colspan="7" class="p-4 text-center text-slate-300 italic">No active notes found.</td></tr>';
            setPagination('active', 0);
        }
    } catch (e) {
        console.error('Error loading active notes:', e);
        tbody.innerHTML = '<tr><td colspan="7" class="p-4 text-center text-red-400 italic">Error loading active notes.</td></tr>';
    }
};

window.searchActiveField = function(colIndex, value) {
    clearTimeout(_activeSearchDebounce);
    _activeSearchDebounce = setTimeout(() => {
        window._activePageSearch = value;
        window.loadActiveNotes(1);
    }, 300);
};

function getSalesOrderStatusBadge(status) {
    const normalized = String(status || 'Open').trim().toLowerCase();
    const styles = {
        open: 'bg-blue-50 text-blue-700 border border-blue-200',
        partial: 'bg-amber-50 text-amber-700 border border-amber-200',
        closed: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
        surplus: 'bg-violet-50 text-violet-700 border border-violet-200',
    };
    const label = normalized ? normalized.charAt(0).toUpperCase() + normalized.slice(1) : 'Open';
    return `<span class="inline-flex items-center justify-center min-w-[86px] px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest ${styles[normalized] || 'bg-slate-100 text-slate-600 border border-slate-200'}">${label}</span>`;
}

function renderActivePage(items) {
    const tbody = document.getElementById('active-notes-tbody');
    const itemsArray = items || [];
    const tp = window._activeTotalPages || 1;
    const overdueRushNotes = window.overdueRushNotes || [];
    if (itemsArray.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="p-4 text-center text-slate-300 italic">No matching notes.</td></tr>';
    } else {
        tbody.innerHTML = itemsArray.map(n => `
            <tr class="hover:bg-slate-50 transition-colors group table-row-animate${n.has_price_change ? ' price-changed' : ''}${overdueRushNotes.includes(n.sales_number) ? ' rush-overdue-row' : ''}"
                data-sales-number="${n.sales_number}">
                <td class="p-4 px-6"><input type="checkbox" value="${n.id}" class="note-checkbox rounded border-slate-300 text-maroon focus:ring-maroon"></td>
                <td class="p-4 px-6 font-mono text-xs text-maroon font-bold">${n.sales_number}</td>
                <td class="p-4 px-6 text-slate-700 font-bold">${n.customer_name}</td>
                <td class="p-4 px-6 text-slate-600">${n.order_date}</td>
                <td class="p-4 px-6 text-center">${getSalesOrderStatusBadge(n.status)}</td>
                <td class="p-4 px-6 font-bold text-maroon text-right">₱ ${parseFloat(n.net_total).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                <td class="p-4 px-6 text-center">
                    <button onclick="openProceedModal(${n.id})" class="px-4 py-1.5 bg-maroon text-white text-[10px] font-bold rounded-lg hover:bg-maroon-800 transition-all shadow-md flex items-center space-x-1 mx-auto">
                        <i data-lucide="arrow-right" class="w-3 h-3 text-gold"></i>
                        <span>Proceed</span>
                    </button>
                </td>
            </tr>
        `).join('');
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
    setPagination('active', tp);
    checkAndHighlightRushNote();
}

function setPagination(type, totalPages) {
    const varMap = { 'all-orders': 'allOrders' };
    const varName = varMap[type] || type;
    const page = window['_' + varName + 'Page'] || 1;
    document.getElementById(type + '-page-info').textContent = 'Page ' + page + ' of ' + totalPages;
    document.getElementById(type + '-prev-btn').disabled = page <= 1 || totalPages <= 1;
    document.getElementById(type + '-next-btn').disabled = page >= totalPages || totalPages <= 1;
}

window.prevActivePage = function() {
    if (window._activePage > 1) { window._activePage--; loadActiveNotes(); }
};

window.nextActivePage = function() {
    if (window._activePage < (window._activeTotalPages || 1)) { window._activePage++; loadActiveNotes(); }
};

// ===== HISTORY TAB (server-paginated) =====
window._historyPage = 1;
window._historyPageSize = 50;
window._historySearch = '';
window._historyTotalPages = 0;
let _historySearchFields = {};
let _historySearchDebounce = null;

window.loadSalesOrderHistory = async function() {
    window._historyPage = 1;
    window._historySearch = '';
    _historySearchFields = {};
    document.querySelectorAll('#tab-content-history .column-search-input').forEach(el => el.value = '');
    await fetchHistoryPage();
};

async function fetchHistoryPage() {
    const tbody = document.getElementById('history-orders-tbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="9" class="p-4 text-center text-slate-300 italic">Loading history...</td></tr>';
    try {
        const params = new URLSearchParams();
        params.set('page', window._historyPage);
        params.set('perPage', window._historyPageSize);
        if (window._historySearch) params.set('search', window._historySearch);
        const url = (window.salesOrderRoutes?.historyDataUrl || '/admin/sales/sales-order/history-data') + '?' + params.toString();
        const res = await fetch(url);
        const r = await res.json();
        if (r.success) {
            window._historyTotalPages = r.last_page || 1;
            renderHistoryPage(r.orders || [], r.total || 0);
        } else {
            tbody.innerHTML = '<tr><td colspan="9" class="p-4 text-center text-slate-300 italic">No history found.</td></tr>';
            setPagination('history', 0);
        }
    } catch (e) {
        console.error('Error loading history:', e);
        tbody.innerHTML = '<tr><td colspan="9" class="p-4 text-center text-red-400 italic">Error loading history.</td></tr>';
    }
}

window.searchHistoryField = function(colIndex, value) {
    _historySearchFields[colIndex] = value;
    clearTimeout(_historySearchDebounce);
    _historySearchDebounce = setTimeout(() => {
        const parts = Object.values(_historySearchFields).filter(v => v !== '');
        window._historySearch = parts.join(' ');
        window._historyPage = 1;
        fetchHistoryPage();
    }, 300);
};

function renderHistoryPage(orders, total) {
    const tbody = document.getElementById('history-orders-tbody');
    if (!tbody) return;
    if (orders.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="p-4 text-center text-slate-300 italic">No matching records.</td></tr>';
    } else {
        tbody.innerHTML = orders.map(o => `
            <tr class="hover:bg-slate-50 transition-colors group table-row-animate">
                <td class="p-4 px-6"><input type="checkbox" value="${o.id}" class="note-checkbox rounded border-slate-300 text-maroon focus:ring-maroon"></td>
                <td class="p-4 px-6 text-slate-600">${o.date_issue}</td>
                <td class="p-4 px-6 font-mono text-xs text-maroon font-bold">${o.invoice_no}</td>
                <td class="p-4 px-6 text-slate-600">${o.waybill_no || '---'}</td>
                <td class="p-4 px-6 text-slate-600">${o.waybill_date || '---'}</td>
                <td class="p-4 px-6 text-slate-700 font-bold">${o.customer_name}</td>
                <td class="p-4 px-6 font-bold text-maroon text-right">₱ ${parseFloat(o.total_amount).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                <td class="p-4 px-6 text-slate-500 italic text-xs">${o.remarks || '---'}</td>
                <td class="p-4 px-6 text-center">
                    <div class="flex items-center justify-center space-x-1">
                        <button onclick="editSalesOrder(${o.id})" class="p-2 bg-slate-100 text-sky-600 hover:bg-sky-500 hover:text-white rounded-lg transition-all shadow-sm" title="Edit">
                            <i data-lucide="pencil" class="w-4 h-4"></i>
                        </button>
                        <button onclick="viewSalesOrderDetail(${o.id})" class="p-2 bg-slate-100 text-slate-500 hover:bg-maroon hover:text-white rounded-lg transition-all shadow-sm" title="View Details">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                        </button>
                        <button onclick="openHistoryPrintModal(${o.id})" class="p-2 bg-slate-100 text-slate-500 hover:bg-amber-500 hover:text-white rounded-lg transition-all shadow-sm" title="Print Receipt">
                            <i data-lucide="printer" class="w-4 h-4"></i>
                        </button>
                        <button onclick="showDeleteSoConfirmation(${o.id}, '${(o.invoice_no || '').replace(/'/g, "\\'")}', '${(o.customer_name || '').replace(/'/g, "\\'")}', '${(o.date_issue || '')}', '${(o.sales_number || '').replace(/'/g, "\\'")}')" class="p-2 bg-slate-100 text-red-500 hover:bg-red-500 hover:text-white rounded-lg transition-all shadow-sm" title="Delete">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
    setPagination('history', window._historyTotalPages);
}

window.prevHistoryPage = function() {
    if (window._historyPage > 1) { window._historyPage--; fetchHistoryPage(); }
};

window.nextHistoryPage = function() {
    if (window._historyPage < window._historyTotalPages) { window._historyPage++; fetchHistoryPage(); }
};

window.changeHistoryPerPage = function(val) {
    window._historyPageSize = parseInt(val);
    window._historyPage = 1;
    fetchHistoryPage();
};

window.updateProceedStep2Totals = function() {
    let grossTotal = 0;
    let netTotal = 0;
    const addlDisc = Math.max(0, Math.min(100, parseFloat(document.getElementById('proceed-addl-discount')?.value) || 0));

    document.querySelectorAll('#proceed-items-tbody tr').forEach(row => {
        const checkbox = row.querySelector('.p-item-checkbox');
        if (!checkbox || !checkbox.checked) return;

        const actualQty = parseFloat(row.querySelector('.p-item-actual-qty')?.value) || 0;
        const unitPrice = parseFloat(row.querySelector('.p-item-price')?.value) || 0;
        const itemDisc = Math.max(0, Math.min(100, parseFloat(row.querySelector('.p-item-disc')?.value) || 0));
        const itemGross = actualQty * unitPrice;
        const afterItemDiscount = itemGross * (1 - itemDisc / 100);
        const afterAdditionalDiscount = afterItemDiscount * (1 - addlDisc / 100);

        grossTotal += itemGross;
        netTotal += afterAdditionalDiscount;
    });

    const grossEl = document.getElementById('proceed-step2-gross-total');
    const netEl = document.getElementById('proceed-step2-net-total');
    if (grossEl) grossEl.textContent = '₱ ' + grossTotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (netEl) netEl.textContent = '₱ ' + netTotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

function getLocalSalesOrderDateValue() {
    const now = new Date();
    const pad = (value) => String(value).padStart(2, '0');
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

function normalizeSalesOrderPrintDate(value) {
    const raw = String(value || '').trim();
    const pad = (number) => String(number).padStart(2, '0');

    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
        return raw;
    }

    if (raw) {
        const parsed = new Date(raw);
        if (!Number.isNaN(parsed.getTime())) {
            return `${parsed.getFullYear()}-${pad(parsed.getMonth() + 1)}-${pad(parsed.getDate())}`;
        }
    }

    return '';
}

window.openProceedModal = async function(noteId) {
    console.log('openProceedModal called with noteId:', noteId);
    console.log('salesOrderRoutes:', window.salesOrderRoutes);
    toggleModal('proceed-order-modal', true);
    try {
        proceedGoToStep(1);
    } catch (e) {
        console.error('Error in proceedGoToStep(1):', e);
    }

    try {
        const url = (window.salesOrderRoutes?.detailUrl || '/admin/sales/sales-order/detail/:id').replace(':id', noteId) + '?for_proceed=1';
        console.log('Fetching detail URL:', url);
        const res = await fetch(url);
        console.log('Response status:', res.status);
        const r = await res.json();
        console.log('Response data:', r);
        if (r.success) {
            const note = r.order;
            document.getElementById('p-sn-no').textContent = note.sales_number;
            document.getElementById('p-customer-name').textContent = note.customer_name;
            document.getElementById('p-issue-date').textContent = note.order_date;
            document.getElementById('p-grand-total').textContent = '₱ ' + parseFloat(note.gross_total).toLocaleString(undefined, {minimumFractionDigits: 2});
            document.getElementById('p-net-total').textContent = '₱ ' + parseFloat(note.net_total).toLocaleString(undefined, {minimumFractionDigits: 2});
            document.getElementById('p-note-type').textContent = note.so_type || "Sale's Order";
            document.getElementById('p-note-status').textContent = note.status;
            const rushBadge = document.getElementById('p-rush-badge');
            if (note.is_rush) { rushBadge.classList.remove('hidden'); rushBadge.textContent = 'Rush'; }
            else { rushBadge.classList.add('hidden'); }
            window._proceedNoteId = note.id;
            window._proceedNoteData = note;
            if (typeof window.syncSalesForceCloseButton === 'function') window.syncSalesForceCloseButton();
            
            // Single invoice number input (not multiple)
            const invoiceInput = document.getElementById('proceed-invoice-number');
            if (invoiceInput) {
                invoiceInput.value = ''; // Clear for new invoice
            }
            const orderDateInput = document.getElementById('proceed-order-date');
            if (orderDateInput) {
                // A new Sales Order starts with today's date, not the Sales Note date.
                orderDateInput.value = getLocalSalesOrderDateValue();
            }
            window._processedSalesOrderDate = '';
            window._processedSalesOrderId = null;
            
            const ptbody = document.getElementById('proceed-items-tbody');
            if (note.items && note.items.length > 0) {
                ptbody.innerHTML = note.items.map((item, idx) => `
                    <tr data-product-id="${item.product_id || 0}" data-application="${(item.application||'').replace(/"/g,'&quot;')}">
                        <td class="p-2 px-3 text-center"><input type="checkbox" class="p-item-checkbox accent-maroon cursor-pointer" checked data-idx="${idx}"></td>
                        <td class="p-2 px-3"><input type="text" value="${(item.product_code||'').replace(/"/g,'&quot;')}" class="p-item-code text-xs w-full px-2 py-1 border border-slate-200 rounded-lg focus:ring-2 focus:ring-maroon/20 outline-none font-bold text-maroon" data-idx="${idx}" data-original-code="${(item.product_code||'').replace(/"/g,'&quot;')}" style="min-width:150px;width:150px;white-space:normal;word-break:break-word;overflow-wrap:anywhere;"></td>
                        <td class="p-2 px-3"><select class="p-item-price-code price-code-select text-xs w-full px-2 py-1 border border-slate-200 rounded-lg focus:ring-2 focus:ring-maroon/20 outline-none text-slate-500" data-idx="${idx}" data-product-id="${item.product_id || 0}" style="min-width:150px;width:150px;white-space:normal;word-break:break-word;overflow-wrap:anywhere;"><option value="${(item.price_code||'').replace(/"/g,'&quot;')}">${(item.price_code||'N/A').replace(/"/g,'&quot;')}</option></select></td>
                        <td class="p-2 px-3"><input type="text" value="${(item.description||'').replace(/"/g,'&quot;')}" class="p-item-desc text-xs w-full px-2 py-1 border border-slate-200 rounded-lg focus:ring-2 focus:ring-maroon/20 outline-none text-slate-700" data-idx="${idx}"></td>
                        <td class="p-2 px-3"><input type="number" value="${item.quantity}" min="1" class="p-item-qty text-xs w-16 px-2 py-1 border border-slate-200 rounded-lg focus:ring-2 focus:ring-maroon/20 outline-none text-center font-bold" data-idx="${idx}"></td>
                        <td class="p-2 px-3"><input type="number" value="${item.quantity}" min="1" class="p-item-actual-qty text-xs w-16 px-2 py-1 border border-amber-200 rounded-lg focus:ring-2 focus:ring-amber-300 outline-none text-center font-bold bg-amber-50" data-idx="${idx}"></td>
                        <td class="p-2 px-3"><input type="number" value="${item.additional_qty || 0}" min="0" class="p-item-add-qty text-xs w-16 px-2 py-1 border border-slate-200 rounded-lg focus:ring-2 focus:ring-maroon/20 outline-none text-center" data-idx="${idx}"></td>
                        <td class="p-2 px-3"><input type="text" value="${(item.oum||'').replace(/"/g,'&quot;')}" class="p-item-oum text-xs w-16 px-2 py-1 border border-slate-200 rounded-lg focus:ring-2 focus:ring-maroon/20 outline-none text-center uppercase text-slate-500" data-idx="${idx}"></td>
                        <td class="p-2 px-3"><div class="flex items-center gap-1"><input type="number" value="${item.unit_price}" step="0.01" min="0" class="p-item-price text-xs w-full px-2 py-1 border border-slate-200 rounded-lg focus:ring-2 focus:ring-maroon/20 outline-none text-right" data-idx="${idx}" data-product-id="${item.product_id || 0}" />${item.has_price_change ? '<span class="p-item-price-refresh needs-update" onclick="updatePriceFromDB(this)" data-product-id="' + (item.product_id || 0) + '" data-idx="' + idx + '" title="Selling price changed - click to update">↻</span>' : ''}</div></td>
                        <td class="p-2 px-3"><input type="number" value="${item.discount}" step="0.01" min="0" max="100" class="p-item-disc text-xs w-14 px-2 py-1 border border-slate-200 rounded-lg focus:ring-2 focus:ring-maroon/20 outline-none text-center" data-idx="${idx}"></td>
                        <td class="p-2 px-3"><input type="text" value="${parseFloat(item.subtotal).toFixed(2)}" class="p-item-subtotal text-xs w-full px-2 py-1 border border-slate-200 rounded-lg focus:ring-2 focus:ring-maroon/20 outline-none text-right font-bold text-maroon" data-idx="${idx}" readonly></td>
                        <td class="p-2 px-3"><input type="text" class="p-item-particulars text-xs w-full px-2 py-1 border border-slate-200 rounded-lg focus:ring-2 focus:ring-maroon/20 outline-none" placeholder="Notes..." value="${(item.particulars||'').replace(/"/g,'&quot;')}" data-idx="${idx}"></td>
                    </tr>
                `).join('');

                var recalcItemSubtotal = window._recalcItemSubtotal = function(row) {
                    normalizeProceedQtyFromActual(row);
                    const checked = row.querySelector('.p-item-checkbox')?.checked || false;
                    const actualQty = parseFloat(row.querySelector('.p-item-actual-qty').value) || 0;
                    const price = parseFloat(row.querySelector('.p-item-price').value) || 0;
                    const disc = parseFloat(row.querySelector('.p-item-disc').value) || 0;
                    const addlDisc = parseFloat(document.getElementById('proceed-addl-discount')?.value) || 0;
                    const subtotal = actualQty * price * (1 - disc / 100) * (checked ? (1 - addlDisc / 100) : 1);
                    row.querySelector('.p-item-subtotal').value = subtotal.toFixed(2);
                    if (typeof window.updateProceedStep2Totals === 'function') window.updateProceedStep2Totals();
                }
                ptbody.querySelectorAll('.p-item-qty, .p-item-actual-qty, .p-item-price, .p-item-disc, .p-item-checkbox').forEach(el => {
                    el.addEventListener('input', function() {
                        recalcItemSubtotal(this.closest('tr'));
                    });
                    if (el.classList.contains('p-item-checkbox')) {
                        el.addEventListener('change', function() {
                            recalcItemSubtotal(this.closest('tr'));
                        });
                    }
                });
                document.getElementById('select-all-items')?.addEventListener('change', function() {
                    ptbody.querySelectorAll('.p-item-checkbox').forEach(cb => cb.checked = this.checked);
                    ptbody.querySelectorAll('.p-item-checkbox').forEach(cb => recalcItemSubtotal(cb.closest('tr')));
                });
                document.getElementById('proceed-addl-discount')?.addEventListener('input', function() {
                    ptbody.querySelectorAll('.p-item-actual-qty').forEach(el => recalcItemSubtotal(el.closest('tr')));
                    if (typeof window.updateProceedStep2Totals === 'function') window.updateProceedStep2Totals();
                });
                if (typeof window.updateProceedStep2Totals === 'function') window.updateProceedStep2Totals();
            } else {
                ptbody.innerHTML = '<tr><td colspan="12" class="p-4 text-center text-slate-300 italic">No items found.</td></tr>';
                if (typeof window.updateProceedStep2Totals === 'function') window.updateProceedStep2Totals();
            }
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    } catch (e) { console.error('Error loading note detail:', e); }
};

window.updatePriceFromDB = async function(el) {
    const productId = el.getAttribute('data-product-id');
    if (!productId || productId === '0') { return; }
    const row = el.closest('tr');
    const input = row.querySelector('.p-item-price');
    try {
        el.textContent = '⟳';
        const priceUrl = (window.salesOrderRoutes?.productPriceUrl || '/admin/sales/sales-order/product-price/:id').replace(':id', productId);
        const res = await fetch(priceUrl);
        const r = await res.json();
        if (r.success && r.selling_price > 0) {
            input.value = r.selling_price;
            input.classList.add('updated');
            el.classList.remove('needs-update');
            if (window._recalcItemSubtotal) window._recalcItemSubtotal(row);
            else {
                const handler = row.querySelector('.p-item-actual-qty');
                if (handler) handler.dispatchEvent(new Event('input'));
            }
        }
    } catch (e) { console.error('Error fetching product price:', e); }
    finally { el.textContent = '↻'; }
};

// ---- Pricing Code Dropdown ----
window.populatePriceCodeSelect = async function(selectEl) {
    if (!selectEl || selectEl.dataset.loaded === '1') return;
    const productId = selectEl.dataset.productId;
    if (!productId || productId === '0') return;
    
    selectEl.dataset.loaded = '1';
    const currentVal = selectEl.value;
    selectEl.innerHTML = '<option value="" disabled>Loading...</option>';
    
    try {
        const url = (window.salesOrderRoutes?.priceCodesUrl || '/admin/sales/sales-order/price-codes/:productId').replace(':productId', productId);
        const res = await fetch(url);
        const r = await res.json();
        if (r.success && r.price_codes && r.price_codes.length > 0) {
            let html = '<option value="">N/A</option>';
            let matchFound = false;
            r.price_codes.forEach(pc => {
                const selected = (pc.price_code === currentVal) ? ' selected' : '';
                if (pc.price_code === currentVal) matchFound = true;
                html += `<option value="${pc.price_code.replace(/"/g, '&quot;')}" data-selling-price="${pc.selling_price}"${selected}>${pc.price_code.replace(/"/g, '&quot;')} (₱${parseFloat(pc.selling_price).toLocaleString(undefined, {minimumFractionDigits: 2})})</option>`;
            });
            if (!matchFound && currentVal) {
                html = `<option value="${currentVal.replace(/"/g, '&quot;')}" selected>${currentVal.replace(/"/g, '&quot;')}</option>` + html;
            }
            selectEl.innerHTML = html;
        } else {
            selectEl.innerHTML = '<option value="">N/A</option>';
        }
    } catch (e) {
        console.error('Error loading price codes:', e);
        selectEl.innerHTML = '<option value="">N/A</option>';
    }
};

window.handlePriceCodeChange = function(selectEl, isEdit) {
    if (!selectEl) return;
    const selectedOption = selectEl.options[selectEl.selectedIndex];
    const sellingPrice = selectedOption ? parseFloat(selectedOption.dataset.sellingPrice) : 0;
    const row = selectEl.closest('tr');
    if (!row) return;
    
    // Always update the row dataset so any code reading data-price-code gets live value
    row.dataset.priceCode = selectEl.value || '';
    row.setAttribute('data-price-code', selectEl.value || '');
    
    const priceInput = isEdit 
        ? row.querySelector('.e-item-price')
        : row.querySelector('.p-item-price');
    
    if (priceInput && sellingPrice > 0) {
        priceInput.value = sellingPrice;
        priceInput.dispatchEvent(new Event('input'));
    } else if (priceInput) {
        priceInput.dispatchEvent(new Event('input'));
    }
};
// ---- End Pricing Code Dropdown ----

function normalizeProceedQtyFromActual(row) {
    const qtyInput = row.querySelector('.p-item-qty');
    const actualQtyInput = row.querySelector('.p-item-actual-qty');
    const qty = parseInt(qtyInput?.value) || 0;
    const actualQty = parseInt(actualQtyInput?.value) || 0;
    if (actualQty > qty) {
        qtyInput.value = actualQty;
    }
}

window.viewSalesOrderDetail = async function(orderId) {
    toggleModal('view-sales-order-modal', true);
    try {
        const url = (window.salesOrderRoutes?.detailUrl || '/admin/sales/sales-order/detail/:id').replace(':id', orderId);
        const res = await fetch(url);
        const r = await res.json();
        if (r.success) {
            const order = r.order;
            document.getElementById('view-so-sn-no').textContent = order.sales_number || '---';
            document.getElementById('view-so-invoice-no').textContent = order.invoice_number || '---';
            document.getElementById('view-so-customer').textContent = order.customer_name || '---';
            document.getElementById('view-so-date').textContent = order.order_date || '---';
            document.getElementById('view-so-total').textContent = '₱ ' + parseFloat(order.total_amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2});
            document.getElementById('view-so-status').innerHTML = getSalesOrderStatusBadge(order.status);
            const tbody = document.getElementById('view-so-items-tbody');
            if (order.items && order.items.length > 0) {
                tbody.innerHTML = order.items.map(item => `
                    <tr class="border-b border-slate-100">
                        <td class="p-3 px-4 text-xs font-mono text-maroon font-bold" style="min-width:150px;width:150px;white-space:normal;word-break:break-word;overflow-wrap:anywhere;">${(item.product_code || '').replace(/"/g, '&quot;')}</td>
                        <td class="p-3 px-4 text-xs text-slate-500" style="min-width:150px;width:150px;white-space:normal;word-break:break-word;overflow-wrap:anywhere;">${(item.price_code || 'N/A').replace(/"/g, '&quot;')}</td>
                        <td class="p-3 px-4 text-xs text-slate-700">${(item.description || '').replace(/"/g, '&quot;')}</td>
                        <td class="p-3 px-4 text-xs text-center font-bold">${parseFloat(item.actual_qty ?? item.quantity ?? 0).toLocaleString()}</td>
                        <td class="p-3 px-4 text-xs text-slate-500 uppercase">${(item.oum || '').replace(/"/g, '&quot;')}</td>
                        <td class="p-3 px-4 text-xs text-right">₱ ${parseFloat(item.unit_price || 0).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                        <td class="p-3 px-4 text-xs text-center">${parseFloat(item.discount || 0).toFixed(2)}%</td>
                        <td class="p-3 px-4 text-xs text-right font-bold text-maroon">₱ ${parseFloat(item.subtotal || 0).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="8" class="p-4 text-center text-slate-300 italic">No items found.</td></tr>';
            }
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    } catch (e) {
        console.error('Error loading order detail:', e);
        document.getElementById('view-so-items-tbody').innerHTML = '<tr><td colspan="8" class="p-4 text-center text-red-400 italic">Error loading details.</td></tr>';
    }
};

window.proceedGoToStep = async function(step) {
    // Check stock before going to step 3
    if (step === 3) {
        const stockOk = await checkSalesOrderStock();
        if (!stockOk) {
            // Stock check failed - COMPLETELY BLOCK step 3
            console.log('Stock check failed - blocking step 3');
            return false;
        }
    }
    
    console.log('Proceeding to step:', step);
    document.querySelectorAll('.proceed-step-content').forEach(el => el.classList.add('hidden'));
    const target = document.getElementById('proceed-step-' + step);
    if (target) target.classList.remove('hidden');
    [1, 2, 3].forEach(s => {
        const ind = document.getElementById('pstep-indicator-' + s);
        const lbl = document.getElementById('pstep-label-' + s);
        if (!ind) return;
        ind.classList.remove('active', 'completed', 'pending');
        lbl?.classList.remove('text-maroon', 'text-slate-400');
        if (s < step) { ind.classList.add('completed'); ind.innerHTML = '<i data-lucide="check" class="w-4 h-4 text-white"></i>'; }
        else if (s === step) { ind.classList.add('active'); ind.innerText = s; lbl?.classList.add('text-maroon'); lbl?.classList.remove('text-slate-400'); }
        else { ind.classList.add('pending'); ind.innerText = s; }
    });
    document.getElementById('pstep-progress-1').style.width = step > 1 ? '100%' : '0%';
    document.getElementById('pstep-progress-2').style.width = step > 2 ? '100%' : '0%';
    if (step === 2 && typeof window.updateProceedStep2Totals === 'function') window.updateProceedStep2Totals();
    if (step === 3) populateProceedReview();
    if (typeof lucide !== 'undefined') lucide.createIcons();
    return true;
};

function populateProceedReview() {
    const note = window._proceedNoteData;
    if (!note) return;
    document.getElementById('rev-p-sn-no').textContent = note.sales_number;
    document.getElementById('rev-p-customer').textContent = note.customer_name;
    const selectedOrderDate = document.getElementById('proceed-order-date')?.value || getLocalSalesOrderDateValue();
    document.getElementById('rev-p-date').textContent = selectedOrderDate;
    document.getElementById('rev-p-grand').textContent = '₱ ' + parseFloat(note.gross_total).toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById('rev-p-net').textContent = '₱ ' + parseFloat(note.net_total).toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById('rev-p-type').textContent = note.so_type || "Sale's Order";
    document.getElementById('rev-p-status').textContent = note.status;
    
    // Single invoice number
    const invoiceNumber = document.getElementById('proceed-invoice-number')?.value?.trim() || '';
    document.getElementById('rev-p-invoices').textContent = invoiceNumber || '---';
    
    const rushEl = document.getElementById('rev-p-rush');
    if (note.is_rush) { rushEl.classList.remove('hidden'); rushEl.textContent = 'Rush'; }
    else { rushEl.classList.add('hidden'); }
    const rtbody = document.getElementById('review-proceed-tbody');
    const sourceRows = document.querySelectorAll('#proceed-items-tbody tr');
    let gross = 0, totalDiscAmt = 0, addlDiscAmt = 0;
    const addlDisc = parseFloat(document.getElementById('proceed-addl-discount')?.value) || 0;
    if (sourceRows.length > 0 && sourceRows[0].cells.length > 1) {
        // Filter to show only CHECKED items
        rtbody.innerHTML = Array.from(sourceRows)
            .filter(row => {
                const checkbox = row.querySelector('.p-item-checkbox');
                return checkbox && checkbox.checked; // Only include checked items
            })
            .map(row => {
                normalizeProceedQtyFromActual(row);
                const cells = row.cells;
                if (cells.length < 11) return '';
                const checked = true; // Already filtered for checked items
                    const code = row.querySelector('.p-item-code')?.value || '';
                const desc = row.querySelector('.p-item-desc')?.value || '';
                const qty = parseInt(row.querySelector('.p-item-qty')?.value) || 0;
                const actualQty = parseInt(row.querySelector('.p-item-actual-qty')?.value) || 0;
                const addQty = parseInt(row.querySelector('.p-item-add-qty')?.value) || 0;
                const unit = row.querySelector('.p-item-oum')?.value || '';
                const price = parseFloat(row.querySelector('.p-item-price')?.value) || 0;
                const disc = parseFloat(row.querySelector('.p-item-disc')?.value) || 0;
                const sub = parseFloat(row.querySelector('.p-item-subtotal')?.value) || 0;
                const particulars = row.querySelector('.p-item-particulars')?.value || '';
                const priceCode = row.getAttribute('data-price-code') || '';
                const itemGross = price * actualQty;
                const itemDiscAmt = itemGross * (disc / 100);
                const itemBeforeAddl = itemGross - itemDiscAmt;
                const itemAddlDisc = itemBeforeAddl * (addlDisc / 100);
                gross += itemGross;
                totalDiscAmt += itemDiscAmt;
                addlDiscAmt += itemAddlDisc;
                const badgeClass = actualQty !== qty ? 'text-amber-600 font-bold' : 'text-slate-700';
                return `<tr><td class="p-4 text-center">✓</td><td class="p-4 font-bold text-maroon" style="min-width:150px;width:150px;white-space:normal;word-break:break-word;overflow-wrap:anywhere;">${code}</td><td class="p-4 text-slate-500" style="min-width:150px;width:150px;white-space:normal;word-break:break-word;overflow-wrap:anywhere;">${priceCode || 'N/A'}</td><td class="p-4 text-slate-700">${desc}</td><td class="p-4 text-center">${qty}</td><td class="p-4 text-center ${badgeClass}">${actualQty}</td><td class="p-4 text-center text-amber-600 font-bold">+${addQty}</td><td class="p-4 text-center uppercase text-slate-500">${unit}</td><td class="p-4 text-right">₱ ${price.toLocaleString(undefined, {minimumFractionDigits: 2})}</td><td class="p-4 text-center text-slate-400">${disc}%</td><td class="p-4 text-right font-bold text-maroon">₱ ${sub.toLocaleString(undefined, {minimumFractionDigits: 2})}</td><td class="p-4 text-slate-500 italic text-[10px]">${particulars || '---'}</td></tr>`;
            }).join('');
        if (addlDisc > 0) {
            const addlRow = document.createElement('tr');
            addlRow.innerHTML = `<td colspan="10" class="p-4 text-right text-amber-600 font-bold text-xs">Additional Discount (${addlDisc}%):</td><td class="p-4 text-right font-bold text-amber-600 text-xs">-₱ ${addlDiscAmt.toLocaleString(undefined, {minimumFractionDigits: 2})}</td><td></td>`;
            rtbody.appendChild(addlRow);
        }
    }
    const netAfterAll = gross - totalDiscAmt - addlDiscAmt;
    document.getElementById('rev-p-gross').textContent = '₱ ' + gross.toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById('rev-p-discount').textContent = '₱ ' + totalDiscAmt.toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById('rev-p-net-total').textContent = '₱ ' + netAfterAll.toLocaleString(undefined, {minimumFractionDigits: 2});
}

window.finalizeSalesOrder = async function() {
    if (window._proceedingOrder) {
        console.log('[SALES ORDER] Double-submit prevented');
        return;
    }
    window._proceedingOrder = true;

    const confirmBtn = document.querySelector('#confirm-order-modal .bg-maroon');
    if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Processing...';
    }

    try {
        const invoiceNumber = document.getElementById('proceed-invoice-number')?.value?.trim() || '';
        if (!invoiceNumber) {
            alert('Please enter an invoice number.');
            window._proceedingOrder = false;
            if (confirmBtn) { confirmBtn.disabled = false; confirmBtn.textContent = 'Yes, Confirm'; }
            return;
        }
        const orderDate = document.getElementById('proceed-order-date')?.value || '';
        if (!orderDate) {
            alert('Please select the Sales Order date.');
            window._proceedingOrder = false;
            if (confirmBtn) { confirmBtn.disabled = false; confirmBtn.textContent = 'Yes, Confirm'; }
            return;
        }
        
        const addlDisc = parseFloat(document.getElementById('proceed-addl-discount')?.value) || 0;
        const items = Array.from(document.querySelectorAll('#proceed-items-tbody tr'))
            .filter(row => {
                const checkbox = row.querySelector('.p-item-checkbox');
                return checkbox && checkbox.checked;
            })
            .map(row => {
                normalizeProceedQtyFromActual(row);
                let pid = row.getAttribute('data-product-id');
                if (pid === 'undefined' || !pid) pid = 0;
                
                const codeInput = row.querySelector('.p-item-code');
                return {
                    product_id: pid,
                    product_code: codeInput?.dataset?.originalCode || codeInput?.value || '',
                    description: row.querySelector('.p-item-desc')?.value || '',
                    price_code: row.querySelector('.p-item-price-code')?.value || '',
                    quantity: parseInt(row.querySelector('.p-item-qty')?.value) || 0,
                    actual_qty: parseInt(row.querySelector('.p-item-actual-qty')?.value) || 0,
                    additional_qty: parseInt(row.querySelector('.p-item-add-qty')?.value) || 0,
                    oum: (row.querySelector('.p-item-oum')?.value || '').trim(),
                    unit_price: parseFloat(row.querySelector('.p-item-price')?.value) || 0,
                    discount: parseFloat(row.querySelector('.p-item-disc')?.value) || 0,
                    additional_discount: addlDisc,
                    subtotal: parseFloat(row.querySelector('.p-item-subtotal')?.value) || 0,
                    particulars: row.querySelector('.p-item-particulars')?.value || '',
                };
            });
        if (items.length === 0) { alert('No items selected to process.'); return; }

        const res = await fetch(window.salesOrderRoutes?.proceedUrl || '/admin/sales/sales-order/proceed', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.csrfToken || '' },
            body: JSON.stringify({ 
                sales_note_id: window._proceedNoteId, 
                invoice_number: invoiceNumber,
                order_date: orderDate,
                items: items 
            }),
        });
        const r = await res.json();
        if (!res.ok) {
            const errors = r.errors ? Object.values(r.errors).flat().join('\n') : '';
            alert('Error: ' + (r.message || 'Request failed') + (errors ? '\n\nDetails:\n' + errors : ''));
            return;
        }
        if (r.success) {
            toggleModal('confirm-order-modal', false);
            toggleModal('proceed-order-modal', false);
            window._printSource = 'proceed';
            window._printInvoiceNumber = invoiceNumber;
            window._processedSalesOrderId = r.sales_order_id || null;
            window._processedSalesOrderDate = r.order_date || orderDate;
            toggleModal('print-receipt-modal', true);
            loadActiveNotes();
            loadSalesOrderDashboard();
            if (typeof reloadAllSalesOrderTabs === 'function') reloadAllSalesOrderTabs();
        } else { alert('Error: ' + (r.message || 'Failed to process order.')); }
    } catch (e) { console.error('Error processing order:', e); alert('An error occurred: ' + e.message); }
    finally {
        window._proceedingOrder = false;
        if (confirmBtn) { confirmBtn.disabled = false; confirmBtn.textContent = 'Yes, Confirm'; }
    }
};

window._printSource = 'proceed';

window.openHistoryPrintModal = function(orderId) {
    window._printSource = 'history';
    window._historyPrintOrderId = orderId;
    toggleModal('print-receipt-modal', true);
};

window.submitPrintReceipt = async function() {
    const printType = document.querySelector('input[name="receipt-print-type"]:checked')?.value || 'order';

    let note, addlDisc, items, addlDiscRate, totalAddlDiscAmt, grossTotal, netTotal, date;
    let printSalesOrderId = null;
    let printInvoiceNumber = '';

    if (window._printSource === 'history') {
        try {
            const url = (window.salesOrderRoutes?.detailUrl || '/admin/sales/sales-order/detail/:id').replace(':id', window._historyPrintOrderId);
            const res = await fetch(url);
            const r = await res.json();
            if (!r.success) { alert('Failed to load order data'); return; }
            note = r.order;
            // History detail returns the exact sales_orders.id. Send it to print so
            // the server can re-read sales_orders.created_at as the authoritative date.
            printSalesOrderId = note.id || window._historyPrintOrderId || null;
            printInvoiceNumber = note.invoice_number || '';
            
            // Read additional discount rate from stored items
            addlDiscRate = (note.items && note.items.length > 0) ? parseFloat(note.items[0].additional_discount) || 0 : 0;
            
            items = (note.items || []).map(item => {
                const storedSubtotal = parseFloat(item.subtotal) || 0;
                const rate = parseFloat(item.additional_discount) || 0;
                // Reverse-calculate the "before additional discount" subtotal
                const subtotalBeforeAddl = rate > 0 ? Math.round(storedSubtotal / (1 - rate / 100) * 100) / 100 : storedSubtotal;
                return {
                    product_code: item.product_code || '',
                    price_code: item.price_code || '',
                    description: item.description || '',
                    application: item.application || '',
                    quantity: item.actual_qty ?? item.quantity ?? 0,
                    actual_qty: item.actual_qty ?? item.quantity ?? 0,
                    additional_qty: item.additional_qty || 0,
                    oum: (item.oum || '').trim(),
                    unit_price: parseFloat(item.unit_price) || 0,
                    discount: parseFloat(item.discount) || 0,
                    additional_discount: rate,
                    subtotal: subtotalBeforeAddl,
                };
            });
            grossTotal = items.reduce((sum, i) => sum + i.subtotal, 0);
            totalAddlDiscAmt = items.reduce((sum, i) => sum + i.subtotal * (i.additional_discount / 100), 0);
            netTotal = grossTotal - totalAddlDiscAmt;
            date = normalizeSalesOrderPrintDate(note.order_date || note.date_issue || note.created_at || '');
        } catch (e) {
            console.error('Error loading history order:', e);
            alert('Error loading order data.');
            return;
        }
    } else {
        note = window._proceedNoteData;
        if (!note) { alert('No order data available.'); return; }

        // This is the new sales_orders.id returned by the proceed endpoint, NOT the Sales Note id.
        printSalesOrderId = window._processedSalesOrderId || null;
        printInvoiceNumber = window._printInvoiceNumber || '';

        addlDisc = parseFloat(document.getElementById('proceed-addl-discount')?.value) || 0;
        // Filter to show only CHECKED items for print
        items = Array.from(document.querySelectorAll('#proceed-items-tbody tr'))
            .filter(row => {
                if (row.cells.length < 2) return false;
                const checkbox = row.querySelector('.p-item-checkbox');
                return checkbox && checkbox.checked; // Only checked items
            })
            .map(row => {
                const actualQty = parseInt(row.querySelector('.p-item-actual-qty')?.value) || 0;
                const addQty = parseInt(row.querySelector('.p-item-add-qty')?.value) || 0;
                const price = parseFloat(row.querySelector('.p-item-price')?.value) || 0;
                const disc = parseFloat(row.querySelector('.p-item-disc')?.value) || 0;
                const printSubtotal = actualQty * price * (1 - disc / 100);
                const pCodeInput = row.querySelector('.p-item-code');
                return {
                    product_code: pCodeInput?.dataset?.originalCode || pCodeInput?.value || '',
                    price_code: row.querySelector('.p-item-price-code')?.value || '',
                    description: row.querySelector('.p-item-desc')?.value || '',
                    application: row.dataset.application || '',
                    quantity: actualQty,
                    actual_qty: actualQty,
                    additional_qty: addQty,
                    oum: (row.querySelector('.p-item-oum')?.value || '').trim(),
                    unit_price: price,
                    discount: disc,
                    additional_discount: 0,
                    subtotal: printSubtotal,
                };
            });

        grossTotal = items.reduce((sum, i) => sum + i.subtotal, 0);

        addlDiscRate = parseFloat(document.getElementById('proceed-addl-discount')?.value) || 0;
        totalAddlDiscAmt = 0;
        // Calculate additional discount only for checked items
        items.forEach(item => {
            const itemBeforeAddl = item.subtotal;
            totalAddlDiscAmt += itemBeforeAddl * (addlDiscRate / 100);
        });
        netTotal = grossTotal - totalAddlDiscAmt;
        date = normalizeSalesOrderPrintDate(window._processedSalesOrderDate
            || document.getElementById('proceed-order-date')?.value
            || document.getElementById('rev-p-date')?.textContent
            || '');
    }

    toggleModal('print-receipt-modal', false);

    try {
        const resp = await fetch(window.salesOrderRoutes.receiptPrintUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.csrfToken,
                'Accept': 'text/html',
            },
            body: JSON.stringify({
                print_type: printType,
                sales_order_id: printSalesOrderId || '',
                invoice_number: printInvoiceNumber || '',
                customer_id: note.customer_id || '',
                customer_name: note.customer_name || '',
                date: normalizeSalesOrderPrintDate(date),
                sales_number: note.sales_number || '',
                gross_total: grossTotal.toString(),
                net_total: netTotal.toString(),
                items: JSON.stringify(items),
                rush_text: note.is_rush ? 'RUSH' : '',
                total_addl_discount: totalAddlDiscAmt.toString(),
                addl_discount_rate: addlDiscRate.toString(),
                vat_rate: '12',
                vat_amount: printType === 'invoice' ? (netTotal / 1.12 * 0.12).toFixed(2) : '0',
                grand_total: printType === 'invoice' ? (netTotal / 1.12).toFixed(2) : netTotal.toFixed(2),
            }),
        });
        const html = await resp.text();
        const blob = new Blob([html], { type: 'text/html' });
        const blobUrl = URL.createObjectURL(blob);
        const win = window.open(blobUrl, 'receipt_print');
        if (!win) { alert('Please allow popups for this site.'); return; }
        win.focus();
    } catch (e) {
        console.error('Receipt print error:', e);
        alert('Error loading receipt. Check console for details.');
    }
};

window._deleteSoInProgress = false;

window.showDeleteSoConfirmation = function(id, invoiceNo, customerName, dateIssue, salesNumber) {
    if (window._deleteSoInProgress) return;

    document.getElementById('delete-so-invoice').textContent = invoiceNo || salesNumber || '---';
    document.getElementById('delete-so-customer').textContent = customerName || '---';
    document.getElementById('delete-so-date').textContent = dateIssue || '---';

    const deleteBtn = document.getElementById('confirm-delete-so-btn');
    const newBtn = deleteBtn.cloneNode(true);
    deleteBtn.parentNode.replaceChild(newBtn, deleteBtn);
    document.getElementById('confirm-delete-so-btn').addEventListener('click', async function() {
        if (window._deleteSoInProgress) return;
        window._deleteSoInProgress = true;
        this.disabled = true;
        this.innerHTML = '<span>Deleting...</span>';
        try {
            const deleteUrl = (window.salesOrderRoutes?.historyDeleteUrl || '/special/sales/sales-order/history-destroy/').replace(':id', id);
            const resp = await fetch(deleteUrl, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': window.csrfToken || '',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                }
            });
            const data = await resp.json();
            if (data.success) {
                toggleModal('confirm-delete-so-modal', false);
                fetchHistoryPage();
                const notice = document.createElement('div');
                notice.className = 'fixed top-4 right-4 z-[9999] bg-emerald-600 text-white px-6 py-3 rounded-xl shadow-2xl text-sm font-bold animate-fade-in';
                notice.textContent = 'Sales order deleted successfully.';
                document.body.appendChild(notice);
                setTimeout(function() { notice.remove(); }, 3000);
            } else {
                alert(data.message || 'Unable to delete the Sales Order.');
            }
        } catch (e) {
            console.error('Delete sales order error:', e);
            alert('Unable to delete the Sales Order.');
        } finally {
            window._deleteSoInProgress = false;
            this.disabled = false;
            this.innerHTML = '<i data-lucide="trash-2" class="w-4 h-4"></i><span>Delete</span>';
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    });
    toggleModal('confirm-delete-so-modal', true);
};

window.viewSalesOrderDetail = async function(id) {
    const tbody = document.getElementById('view-order-tbody');
    const movementTbody = document.getElementById('view-order-movement-tbody');
    if (!tbody) return;

    toggleModal('view-order-modal', true);
    switchSalesOrderDetailTab('items', document.getElementById('view-order-items-tab'));
    document.querySelectorAll('#view-order-movement-panel .movement-column-search').forEach(input => input.value = '');

    tbody.innerHTML = '<tr><td colspan="11" class="p-4 text-center text-slate-400">Loading...</td></tr>';
    if (movementTbody) {
        movementTbody.innerHTML = '<tr><td colspan="6" class="p-5 text-center text-slate-400">Loading movement...</td></tr>';
    }

    try {
        const url = (window.salesOrderRoutes?.detailUrl || '/admin/sales/sales-order/detail/:id').replace(':id', id);
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const r = await res.json();

        if (!res.ok || !r.success) {
            throw new Error(r.message || `Unable to load Sales Order detail (HTTP ${res.status}).`);
        }

        const order = r.order || {};
        const invoiceNo = order.invoice_no || order.invoice_number || order.invoice_numbers || '---';
        window._currentViewSalesNumber = order.sales_number;

        document.getElementById('view-order-label').textContent = 'Transaction: ' + (invoiceNo !== '---' ? invoiceNo : (order.sales_number || '---'));
        document.getElementById('view-h-date').textContent = order.date_issue || order.order_date || '---';
        document.getElementById('view-h-invoice').textContent = invoiceNo;
        document.getElementById('view-h-waybill').textContent = order.waybill_no || '---';
        document.getElementById('view-h-waybill-date').textContent = order.waybill_date || '---';
        document.getElementById('view-h-name').textContent = order.customer_name || '---';
        document.getElementById('view-h-total').textContent = '₱ ' + parseFloat(order.total_amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2});
        document.getElementById('view-h-remarks').textContent = order.remarks || '---';
        document.getElementById('view-i-sn').textContent = order.sales_note_number || order.sales_number || '---';
        document.getElementById('view-i-customer').textContent = order.customer_name || '---';
        document.getElementById('view-i-date').textContent = order.order_date || order.date_issue || '---';
        document.getElementById('view-i-invoices').textContent = invoiceNo;
        document.getElementById('view-i-type').textContent = order.so_type || "Sale's Order";
        document.getElementById('view-i-status').textContent = order.status || '---';

        if (Array.isArray(order.items) && order.items.length > 0) {
            tbody.innerHTML = order.items.map(item => `
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="p-4 font-bold text-maroon" style="min-width:150px;width:150px;white-space:normal;word-break:break-word;overflow-wrap:anywhere;">${escapeHtml(item.product_code || '')}</td>
                    <td class="p-4 text-slate-500" style="min-width:150px;width:150px;white-space:normal;word-break:break-word;overflow-wrap:anywhere;">${escapeHtml(item.price_code || 'N/A')}</td>
                    <td class="p-4 text-slate-700">${escapeHtml(item.description || '')}</td>
                    <td class="p-4 text-center font-bold">${Number(item.quantity || 0).toLocaleString()}</td>
                    <td class="p-4 text-center ${Number(item.actual_qty ?? item.quantity ?? 0) !== Number(item.quantity || 0) ? 'text-amber-600 font-bold' : 'text-slate-700'}">${Number(item.actual_qty ?? item.quantity ?? 0).toLocaleString()}</td>
                    <td class="p-4 text-center text-amber-600 font-bold">${Number(item.additional_qty || 0).toLocaleString()}</td>
                    <td class="p-4 text-center uppercase text-slate-500">${escapeHtml(item.oum || '')}</td>
                    <td class="p-4 text-right">₱ ${parseFloat(item.unit_price || 0).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                    <td class="p-4 text-center text-slate-400">${parseFloat(item.discount || 0).toLocaleString()}%</td>
                    <td class="p-4 text-right font-bold text-maroon">₱ ${parseFloat(item.subtotal || 0).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                    <td class="p-4 text-slate-500 italic text-[10px]">${escapeHtml(item.particulars || '---')}</td>
                </tr>
            `).join('');
            document.getElementById('view-order-count').textContent = order.items.length + (order.items.length === 1 ? ' item' : ' items');
            document.getElementById('view-order-total').textContent = '₱ ' + parseFloat(order.total_amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2});
        } else {
            tbody.innerHTML = '<tr><td colspan="11" class="p-4 text-center text-slate-300 italic">No items found.</td></tr>';
            document.getElementById('view-order-count').textContent = '0 items';
            document.getElementById('view-order-total').textContent = '₱ 0.00';
        }

        const movement = Array.isArray(order.movement) ? order.movement : [];
        if (movementTbody) {
            if (movement.length > 0) {
                movementTbody.innerHTML = movement.map(row => {
                    const debit = Number(row.debit || 0);
                    const credit = Number(row.credit || 0);
                    return `
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="p-3 font-bold text-maroon uppercase">${escapeHtml(row.transaction || '')}</td>
                            <td class="p-3 font-mono font-bold text-slate-700">${escapeHtml(row.transaction_no || '')}</td>
                            <td class="p-3 text-right font-bold text-slate-700">${debit > 0 ? '₱ ' + debit.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '—'}</td>
                            <td class="p-3 text-right font-bold text-emerald-700">${credit > 0 ? '₱ ' + credit.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '—'}</td>
                            <td class="p-3 text-slate-600">${escapeHtml(row.remarks || '')}</td>
                            <td class="p-3 whitespace-nowrap text-slate-600">${escapeHtml(row.date || '')}</td>
                        </tr>`;
                }).join('');
            } else {
                movementTbody.innerHTML = '<tr><td colspan="6" class="p-5 text-center text-slate-300 italic">No ledger movement found.</td></tr>';
            }
        }

        const movementCount = document.getElementById('view-order-movement-count');
        if (movementCount) movementCount.textContent = movement.length + (movement.length === 1 ? ' transaction' : ' transactions');

        switchViewSubTab('view-details', document.querySelector('.view-sub-tab'));
        if (typeof lucide !== 'undefined') lucide.createIcons();
    } catch (e) {
        console.error('Error loading order detail:', e);
        tbody.innerHTML = '<tr><td colspan="11" class="p-4 text-center text-red-400 italic">Error loading details.</td></tr>';
        if (movementTbody) movementTbody.innerHTML = '<tr><td colspan="6" class="p-5 text-center text-red-400 italic">Error loading ledger.</td></tr>';
    }
};

window.switchSalesOrderDetailTab = function(tab, btn) {
    const itemsPanel = document.getElementById('view-order-items-panel');
    const movementPanel = document.getElementById('view-order-movement-panel');
    if (!itemsPanel || !movementPanel) return;

    const showMovement = tab === 'movement';
    itemsPanel.classList.toggle('hidden', showMovement);
    movementPanel.classList.toggle('hidden', !showMovement);

    document.querySelectorAll('.sales-order-detail-tab').forEach(el => {
        el.classList.remove('active', 'bg-white', 'text-maroon', 'shadow-sm');
        el.classList.add('text-slate-500');
    });
    if (btn) {
        btn.classList.add('active', 'bg-white', 'text-maroon', 'shadow-sm');
        btn.classList.remove('text-slate-500');
    }
};

window.filterSalesOrderMovement = function() {
    const tbody = document.getElementById('view-order-movement-tbody');
    if (!tbody) return;

    const filters = {};
    document.querySelectorAll('#view-order-movement-panel .movement-column-search').forEach(input => {
        filters[Number(input.dataset.movementCol)] = String(input.value || '').trim().toLowerCase();
    });

    Array.from(tbody.querySelectorAll('tr')).forEach(row => {
        const cells = Array.from(row.querySelectorAll('td'));
        if (cells.length < 6) return;
        const matches = Object.entries(filters).every(([index, filter]) => {
            if (!filter) return true;
            return (cells[Number(index)]?.textContent || '').toLowerCase().includes(filter);
        });
        row.style.display = matches ? '' : 'none';
    });
};

window.switchViewSubTab = function(tabId, btn) {
    document.querySelectorAll('.view-sub-content').forEach(el => el.classList.add('hidden'));
    document.getElementById('view-tab-' + tabId)?.classList.remove('hidden');
    document.querySelectorAll('.view-sub-tab').forEach(el => {
        el.classList.remove('active', 'bg-white', 'text-maroon', 'shadow-sm');
        el.classList.add('text-slate-500');
    });
    if (btn) { btn.classList.add('active', 'bg-white', 'text-maroon', 'shadow-sm'); btn.classList.remove('text-slate-500'); }
};

window.resetFilters = function() {
    document.getElementById('filter-date-from').value = '';
    document.getElementById('filter-date-to').value = '';
    document.getElementById('filter-status').value = '';
    toggleModal('filter-modal', false);
};

window.applyFilters = function() { toggleModal('filter-modal', false); };

// ===== ONLINE REPORT GENERATION MODAL =====
let _onlineReportNotes = [];
let _dateRangeRowCount = 0;
window._onlineReportCounterParts = {};
let _counterPartProducts = [];
let _counterPartFiltered = [];
let _counterPartPage = 1;
let _counterPartPageSize = 25;
let _counterPartTotalPages = 1;
let _counterPartSearchTimer = null;
let _counterPartRequestSeq = 0;
let _counterPartContext = null;

// ---- Online Invoice Edit / Delete ----

window._editingReportId = null;

window.resetOnlineReportModal = function() {
    window._editingReportId = null;
    window._onlineReportIds = [];
    window._onlineReportCounterParts = {};
    window._onlineReportAddedItems = {};

    document.querySelector('#online-report-modal h3').textContent = 'Online Report Generation';
    document.getElementById('online-report-subtitle').textContent = 'Step 1: Basic Info';
    document.getElementById('or-step-1-content').classList.remove('hidden');
    document.getElementById('or-step-2-content').classList.add('hidden');
    document.getElementById('or-footer-1').style.display = 'flex';
    document.getElementById('or-footer-2').style.display = 'none';
    document.getElementById('or-step-1-indicator').className = 'w-8 h-8 rounded-full bg-gold text-maroon font-bold flex items-center justify-center text-sm shadow';
    document.getElementById('or-step-2-indicator').className = 'w-8 h-8 rounded-full bg-white/20 text-white/60 font-bold flex items-center justify-center text-sm';
    document.getElementById('or-step-1-label').className = 'text-xs font-bold text-white';
    document.getElementById('or-step-2-label').className = 'text-xs font-bold text-white/60';
    document.getElementById('or-footer-1').querySelector('button:last-child').innerHTML = '<span>Next</span><i data-lucide="arrow-right" class="w-4 h-4"></i>';
    document.getElementById('or-footer-1').querySelector('button:last-child').onclick = goToOnlineReportStep2;
    document.getElementById('or-footer-2').querySelector('button:last-child').innerHTML = '<i data-lucide="file-check" class="w-4 h-4"></i><span>Generate Receipt</span>';
    document.getElementById('or-footer-2').querySelector('button:last-child').onclick = generateOnlineReceipt;
    document.getElementById('date-range-container').innerHTML = '';
    document.getElementById('or-notes-tbody').innerHTML = '<tr><td colspan="3" class="p-6 text-center text-slate-300 italic">No sales notes loaded.</td></tr>';
    document.getElementById('or-items-container').innerHTML = '';
    document.getElementById('or-items-loading').classList.add('hidden');
    _dateRangeRowCount = 0;
    const onlineTotal = document.getElementById('or-total-amount');
    if (onlineTotal) onlineTotal.textContent = '₱ 0.00';
    if (typeof lucide !== 'undefined') lucide.createIcons();
};

window.openOnlineReportEdit = async function(reportId) {
    try {
        const editUrl = (window.salesOrderRoutes?.onlineInvoiceEditUrl || '/special/sales/sales-order/online-edit/').replace(':id', reportId);
        const resp = await fetch(editUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await resp.json();
        if (!data.success) { alert(data.message || 'Failed to load online invoice.'); return; }

        // Reset and set edit mode
        window.resetOnlineReportModal();
        window._editingReportId = reportId;

        document.querySelector('#online-report-modal h3').textContent = 'Edit Online Invoice';

        // Populate notes info
        const notes = data.notes || [];
        window._onlineReportIds = notes.map(n => n.id);
        window._onlineReportCounterParts = (data.counter_parts && typeof data.counter_parts === 'object')
            ? Object.assign({}, data.counter_parts)
            : {};
        window._onlineReportAddedItems = {};
        window._onlineReportEditData = data;

        // Populate Step 1: invoice numbers, addresses, date ranges
        const tbody = document.getElementById('or-notes-tbody');
        if (notes.length > 0) {
            tbody.innerHTML = notes.map((note, idx) => `
                <tr class="or-note-row" data-idx="${idx}">
                    <td class="p-3 px-4 font-bold text-slate-800">${note.sales_number || '---'}</td>
                    <td class="p-3 px-4"><input type="text" class="or-invoice-input w-full px-2 py-1.5 border border-slate-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon" value="${(note.invoice_no || '').replace(/"/g, '&quot;')}"></td>
                    <td class="p-3 px-4"><input type="text" class="or-address-input w-full px-2 py-1.5 border border-slate-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon" value="${(note.address || '').replace(/"/g, '&quot;')}"></td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="3" class="p-6 text-center text-slate-300 italic">No sales notes found.</td></tr>';
        }

        // Populate date ranges
        const dateRanges = data.date_ranges || [];
        document.getElementById('date-range-container').innerHTML = '';
        _dateRangeRowCount = 0;
        if (dateRanges.length > 0) {
            dateRanges.forEach(function(dr) {
                addDateRangeRow();
                const rows = document.querySelectorAll('#date-range-container > div');
                const lastRow = rows[rows.length - 1];
                if (lastRow) {
                    const typeSelect = lastRow.querySelector('.dr-type');
                    if (typeSelect) typeSelect.value = dr.type || 'annual';
                    onDateRangeTypeChange(lastRow.id);
                    if (dr.type === 'annual') {
                        const valInput = lastRow.querySelector('.dr-val');
                        if (valInput) valInput.value = dr.value || '';
                    } else if (dr.type === 'monthly') {
                        const parts = (dr.value || '').split('-');
                        const monthInput = lastRow.querySelector('.dr-month');
                        const yearInput = lastRow.querySelector('.dr-year');
                        if (monthInput) monthInput.value = parts[1] || '';
                        if (yearInput) yearInput.value = parts[0] || '';
                    } else if (dr.type === 'specific') {
                        const rangeParts = (dr.value || '').split('|');
                        const fromInput = lastRow.querySelector('.dr-from');
                        const toInput = lastRow.querySelector('.dr-to');
                        if (fromInput) fromInput.value = rangeParts[0] || '';
                        if (toInput) toInput.value = rangeParts[1] || '';
                    }
                }
            });
        } else {
            addDefaultOnlineReportDateRanges();
        }

        // Build item map for Step 2
        const prices = data.prices || {};
        window._onlineReportEditPrices = prices;

        // Load notes into _onlineReportNotes for the renderer
        _onlineReportNotes = notes.map(function(note, idx) {
            const netTotal = parseFloat(notes[idx]?.net_total || 0);
            return {
                id: note.id,
                sales_number: note.sales_number,
                customer_name: note.customer_name,
                net_total: netTotal,
                items: [],
            };
        });

        // Populate items for each note
        notes.forEach(function(note, noteIdx) {
            const items = note.items || [];
            if (_onlineReportNotes[noteIdx]) {
                _onlineReportNotes[noteIdx].items = items;
                _onlineReportNotes[noteIdx].net_total = net_total_from_items(items, prices);
            }
        });

        // Override Step 2's button for update (pre-wire even though we stay on Step 1)
        document.getElementById('or-footer-2').querySelector('button:last-child').innerHTML = '<i data-lucide="save" class="w-4 h-4"></i><span>Update</span>';
        document.getElementById('or-footer-2').querySelector('button:last-child').onclick = updateOnlineReport;
        // Also override Step 1's Next button to go to Step 2 when clicked (default behavior is fine)

        toggleModal('online-report-modal', true);
        if (typeof lucide !== 'undefined') lucide.createIcons();
    } catch (e) {
        console.error('Edit online invoice error:', e);
        alert('Unable to load the Online Invoice for editing.');
    }
};

function net_total_from_items(items, prices) {
    let total = 0;
    (items || []).forEach(function(item) {
        const qty = parseInt(item.quantity || 0);
        const pid = item.product_id;
        let price = 0;
        if (pid && prices && prices[String(pid)]) {
            price = parseFloat(prices[String(pid)]);
        } else {
            price = parseFloat(item.resolved_unit_price ?? item._resolved_price ?? item.price_online ?? item.unit_price ?? 0);
        }
        total += qty * price;
    });
    return total;
}

window.showDeleteOiConfirmation = function(id, invoiceDisplay, customers, createdAt, totalItems) {
    if (window._deleteOiInProgress) return;
    document.getElementById('delete-oi-invoice').textContent = invoiceDisplay || '---';
    document.getElementById('delete-oi-customer').textContent = customers || '---';
    document.getElementById('delete-oi-date').textContent = createdAt || '---';
    document.getElementById('delete-oi-items').textContent = String(totalItems) + ' item(s)';

    const deleteBtn = document.getElementById('confirm-delete-oi-btn');
    const newBtn = deleteBtn.cloneNode(true);
    deleteBtn.parentNode.replaceChild(newBtn, deleteBtn);
    document.getElementById('confirm-delete-oi-btn').addEventListener('click', async function() {
        if (window._deleteOiInProgress) return;
        window._deleteOiInProgress = true;
        this.disabled = true;
        this.innerHTML = '<span>Deleting...</span>';
        try {
            const deleteUrl = (window.salesOrderRoutes?.onlineInvoiceDeleteUrl || '/special/sales/sales-order/online-destroy/').replace(':id', id);
            const resp = await fetch(deleteUrl, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': window.csrfToken || '',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                }
            });
            const data = await resp.json();
            if (data.success) {
                toggleModal('confirm-delete-oi-modal', false);
                fetchOiPage();
                // Show success toast-like notification
                const notice = document.createElement('div');
                notice.className = 'fixed top-4 right-4 z-[9999] bg-emerald-600 text-white px-6 py-3 rounded-xl shadow-2xl text-sm font-bold animate-fade-in';
                notice.textContent = 'Online Invoice deleted successfully.';
                document.body.appendChild(notice);
                setTimeout(function() { notice.remove(); }, 3000);
            } else {
                alert(data.message || 'Unable to delete the Online Invoice.');
            }
        } catch (e) {
            console.error('Delete online invoice error:', e);
            alert('Unable to delete the Online Invoice.');
        } finally {
            window._deleteOiInProgress = false;
            this.disabled = false;
            this.innerHTML = '<i data-lucide="trash-2" class="w-4 h-4"></i><span>Delete</span>';
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    });
    toggleModal('confirm-delete-oi-modal', true);
};

window.updateOnlineReport = async function() {
    if (window._updateOiInProgress) return;
    window._updateOiInProgress = true;

    try {
        const reportId = window._editingReportId;
        if (!reportId) { alert('No report selected for update.'); return; }

        // Collect data same as generateOnlineReceipt
        const prices = {};
        document.querySelectorAll('.or-price-input').forEach(function(input) {
            const pid = input.getAttribute('data-product-id');
            const val = parseFloat(input.value);
            const noteIdx = input.getAttribute('data-note-idx');
            const itemIdx = input.getAttribute('data-item-idx');
            if (pid && pid !== '0' && !isNaN(val)) {
                prices[pid] = val;
                const key = noteIdx + '-' + itemIdx;
                const cp = window._onlineReportCounterParts[key];
                if (cp && cp.product_id) {
                    prices[cp.product_id] = val;
                }
            }
        });

        // Save prices first (same as create)
        if (Object.keys(prices).length > 0) {
            try {
                const saveResp = await fetch(window.salesOrderRoutes.onlinePricesUrl.replace('/online-prices', '/online-prices/save'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ prices: prices })
                });
                const saveData = await saveResp.json();
                if (!saveData.success) {
                    console.error('Save prices failed:', saveData.message);
                }
            } catch (e) {
                console.error('Save prices error:', e);
            }
        }

        // Collect date ranges
        const dateRanges = [];
        document.querySelectorAll('#date-range-container > div').forEach(function(row) {
            const type = row.querySelector('.dr-type')?.value || 'annual';
            let value = '';
            if (type === 'annual') value = row.querySelector('.dr-val')?.value || '';
            else if (type === 'monthly') {
                const m = row.querySelector('.dr-month')?.value || '';
                const y = row.querySelector('.dr-year')?.value || '';
                value = m && y ? y + '-' + m : '';
            } else if (type === 'specific') {
                const f = row.querySelector('.dr-from')?.value || '';
                const t = row.querySelector('.dr-to')?.value || '';
                value = f && t ? f + '|' + t : '';
            }
            if (value) dateRanges.push({ type: type, value: value });
        });

        // Collect invoice numbers and addresses
        const invoiceNumbers = {};
        const addresses = {};
        document.querySelectorAll('#or-notes-tbody .or-note-row').forEach(function(row) {
            const idx = row.getAttribute('data-idx');
            const invInput = row.querySelector('.or-invoice-input');
            const addrInput = row.querySelector('.or-address-input');
            if (invInput && invInput.value.trim()) invoiceNumbers[idx] = invInput.value.trim();
            if (addrInput && addrInput.value.trim()) addresses[idx] = addrInput.value.trim();
        });

        // Collect items qty and unit
        const itemsQty = {};
        const itemsUnit = {};
        document.querySelectorAll('.or-qty-input').forEach(function(input) {
            const noteIdx = input.getAttribute('data-or-note-idx');
            const itemIdx = input.getAttribute('data-or-item-idx');
            if (noteIdx !== null && itemIdx !== null) {
                itemsQty[noteIdx + '-' + itemIdx] = parseInt(input.value) || 1;
            }
        });
        document.querySelectorAll('.or-unit-input').forEach(function(input) {
            const noteIdx = input.getAttribute('data-or-note-idx');
            const itemIdx = input.getAttribute('data-or-item-idx');
            if (noteIdx !== null && itemIdx !== null) {
                itemsUnit[noteIdx + '-' + itemIdx] = input.value || '';
            }
        });

        // Build notes array with merged items (same as create)
        const notes = (_onlineReportNotes || []).map(function(note, noteIdx) {
            const addedItems = (window._onlineReportAddedItems && window._onlineReportAddedItems[noteIdx]) || [];
            const origItems = (note.items || []).map(function(item, origIdx) {
                const qtyKey = noteIdx + '-' + origIdx;
                const qty = itemsQty[qtyKey] || item.quantity || 0;
                const unit = itemsUnit[qtyKey] || item.oum || '';
                return Object.assign({}, item, { quantity: qty, oum: unit });
            });
            const merged = origItems.concat(addedItems.map(function(item, addIdx) {
                const flatIdx = origItems.length + addIdx;
                const qtyKey = noteIdx + '-' + flatIdx;
                const qty = itemsQty[qtyKey] || item.quantity || 1;
                const unit = itemsUnit[qtyKey] || item.oum || '';
                return Object.assign({}, item, { quantity: qty, oum: unit, is_added: true });
            }));
            return Object.assign({}, note, { items: merged });
        });

        const payload = {
            data: {
                date_ranges: dateRanges,
                prices: prices,
                invoice_numbers: invoiceNumbers,
                addresses: addresses,
                counter_parts: Object.assign({}, window._onlineReportCounterParts || {}),
                notes: notes,
                note_ids: notes.map(function(n) { return n.id; }),
                added_items: window._onlineReportAddedItems || {},
                items_qty: itemsQty,
                items_unit: itemsUnit,
            }
        };

        const updateUrl = (window.salesOrderRoutes?.onlineInvoiceUpdateUrl || '/special/sales/sales-order/online-update/').replace(':id', reportId);

        toggleModal('online-report-modal', false);

        const resp = await fetch(updateUrl, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await resp.json();
        if (data.success) {
            const notice = document.createElement('div');
            notice.className = 'fixed top-4 right-4 z-[9999] bg-emerald-600 text-white px-6 py-3 rounded-xl shadow-2xl text-sm font-bold animate-fade-in';
            notice.textContent = 'Online Invoice updated successfully.';
            document.body.appendChild(notice);
            setTimeout(function() { notice.remove(); }, 3000);
            fetchOiPage();
        } else {
            alert(data.message || 'Unable to update the Online Invoice.');
            toggleModal('online-report-modal', true);
        }
    } catch (e) {
        console.error('Update online invoice error:', e);
        alert('Unable to update the Online Invoice.');
        toggleModal('online-report-modal', true);
    } finally {
        window._updateOiInProgress = false;
    }
};

window.openOnlineReport = function() {
    const checked = document.querySelectorAll('.note-checkbox:checked');
    if (checked.length === 0) { alert('Please select at least one sales note.'); return; }
    const ids = Array.from(checked).map(cb => parseInt(cb.value));
    window._onlineReportIds = ids;
    window.resetOnlineReportModal();
    document.getElementById('online-report-subtitle').textContent = 'Step 1: Basic Info';
    document.getElementById('or-step-1-content').classList.remove('hidden');
    document.getElementById('or-step-2-content').classList.add('hidden');
    document.getElementById('or-footer-1').style.display = 'flex';
    document.getElementById('or-footer-2').style.display = 'none';
    document.getElementById('or-step-1-indicator').className = 'w-8 h-8 rounded-full bg-gold text-maroon font-bold flex items-center justify-center text-sm shadow';
    document.getElementById('or-step-2-indicator').className = 'w-8 h-8 rounded-full bg-white/20 text-white/60 font-bold flex items-center justify-center text-sm';
    document.getElementById('or-step-1-label').className = 'text-xs font-bold text-white';
    document.getElementById('or-step-2-label').className = 'text-xs font-bold text-white/60';
    
    document.getElementById('date-range-container').innerHTML = '';
    _dateRangeRowCount = 0;
    addDefaultOnlineReportDateRanges();
    
    window._onlineReportCounterParts = {};
    window._onlineReportAddedItems = {};
    window._onlineReportEditData = null;
    
    document.getElementById('or-notes-tbody').innerHTML = '<tr><td colspan="5" class="p-6 text-center text-slate-300 italic">Loading sales notes...</td></tr>';
    toggleModal('online-report-modal', true);
    fetch(window.salesOrderRoutes.reportUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.csrfToken, 'Accept': 'application/json' },
        body: JSON.stringify({ ids: ids })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            _onlineReportNotes = data.notes || [];
            renderOnlineReportNotesTable();
        } else {
            document.getElementById('or-notes-tbody').innerHTML = '<tr><td colspan="5" class="p-6 text-center text-red-400 italic">' + (data.message || 'Failed to load.') + '</td></tr>';
        }
    })
    .catch(err => {
        console.error('Online report fetch error:', err);
        document.getElementById('or-notes-tbody').innerHTML = '<tr><td colspan="5" class="p-6 text-center text-red-400 italic">Failed to load sales notes.</td></tr>';
    });
    if (typeof lucide !== 'undefined') lucide.createIcons();
};

// ===== COUNTER PART MODAL =====
window.openCounterPartModal = function(noteIdx, itemIdx) {
    _counterPartContext = { noteIdx, itemIdx };
    toggleModal('counter-part-modal', true);
    document.getElementById('cp-tbody').innerHTML = '<tr><td colspan="5" class="p-6 text-center text-slate-300 italic">Loading items...</td></tr>';
    _counterPartPage = 1;
    document.querySelectorAll('.cp-search-input').forEach(inp => inp.value = '');
    loadCounterPartProducts(1);
    if (typeof lucide !== 'undefined') lucide.createIcons();
};

function buildCounterPartSearchParams(page) {
    const params = new URLSearchParams();
    const searchKeyMap = ['code', 'part_number', 'description', 'application', 'category'];
    params.set('page', String(page || 1));
    params.set('per_page', String(_counterPartPageSize || 25));

    document.querySelectorAll('.cp-search-input').forEach(inp => {
        const col = parseInt(inp.getAttribute('data-cp-col'), 10);
        const key = searchKeyMap[col];
        const value = inp.value.trim();
        if (key && value) {
            params.set('search[' + key + ']', value);
        }
    });

    return params;
}

window.loadCounterPartProducts = async function(page = 1) {
    const tbody = document.getElementById('cp-tbody');
    const requestSeq = ++_counterPartRequestSeq;
    _counterPartPage = page;
    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="5" class="p-6 text-center text-slate-300 italic">Loading items...</td></tr>';
    }

    try {
        const url = window.salesOrderRoutes?.counterPartProductsUrl
            || window.salesOrderRoutes?.counterPartProducts
            || window.salesOrderRoutes?.productsUrl;
        if (!url) {
            throw new Error('Counter-part products URL is not configured.');
        }
        const result = await fetchJsonOrThrow(
            url + '?' + buildCounterPartSearchParams(page).toString(),
            {},
            'Counter-part products request'
        );

        if (requestSeq !== _counterPartRequestSeq) return;

        if (result.success) {
            const pagination = result.pagination || {};
            _counterPartProducts = Array.isArray(result.products) ? result.products : [];
            _counterPartFiltered = [..._counterPartProducts];
            _counterPartPage = pagination.current_page || page || 1;
            _counterPartTotalPages = pagination.last_page || 1;
            renderCounterPartPage();
        } else {
            _counterPartProducts = [];
            _counterPartFiltered = [];
            document.getElementById('cp-tbody').innerHTML = '<tr><td colspan="5" class="p-6 text-center text-slate-300 italic">No items found.</td></tr>';
            document.getElementById('cp-page-info').textContent = 'Page 1 of 1';
            document.getElementById('cp-prev-btn').disabled = true;
            document.getElementById('cp-next-btn').disabled = true;
        }
    } catch (e) {
        if (requestSeq !== _counterPartRequestSeq) return;
        console.error('Error loading counter part products:', e);
        document.getElementById('cp-tbody').innerHTML = '<tr><td colspan="5" class="p-6 text-center text-red-400 italic">Error loading items.</td></tr>';
    }
};

function renderCounterPartPage() {
    const tbody = document.getElementById('cp-tbody');
    const data = _counterPartFiltered || [];
    const page = _counterPartPage || 1;
    const tp = _counterPartTotalPages || 1;
    const items = data;
    if (items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="p-4 text-center text-slate-300 italic">No items found.</td></tr>';
    } else {
        tbody.innerHTML = items.map(p => `
            <tr onclick="selectCounterPartItem(${p.id})" class="hover:bg-maroon/5 cursor-pointer transition-colors">
                <td class="p-3 px-4 font-bold text-slate-800">${p.product_code}</td>
                <td class="p-3 px-4 text-slate-600">${p.part_number || 'N/A'}</td>
                <td class="p-3 px-4 text-slate-600 max-w-xs truncate">${p.description || '---'}</td>
                <td class="p-3 px-4 text-slate-600">${p.application || 'N/A'}</td>
                <td class="p-3 px-4 text-slate-600">${p.category || 'N/A'}</td>
            </tr>
        `).join('');
    }
    document.getElementById('cp-page-info').textContent = 'Page ' + page + ' of ' + tp;
    document.getElementById('cp-prev-btn').disabled = page <= 1 || tp <= 1;
    document.getElementById('cp-next-btn').disabled = page >= tp || tp <= 1;
}

window.filterCounterPartTable = function() {
    clearTimeout(_counterPartSearchTimer);
    _counterPartSearchTimer = setTimeout(() => {
        loadCounterPartProducts(1);
    }, 350);
};

window.prevCounterPartPage = function() {
    if (_counterPartPage > 1) {
        loadCounterPartProducts(_counterPartPage - 1);
    }
};

window.nextCounterPartPage = function() {
    const tp = _counterPartTotalPages || 1;
    if (_counterPartPage < tp) {
        loadCounterPartProducts(_counterPartPage + 1);
    }
};

window.selectCounterPartItem = function(productId) {
    const product = _counterPartProducts.find(p => p.id === productId);
    if (!product || !_counterPartContext) return;
    const key = _counterPartContext.noteIdx + '-' + _counterPartContext.itemIdx;
    window._onlineReportCounterParts[key] = {
        product_id: product.id,
        product_code: product.product_code,
        part_number: product.part_number,
        description: product.description,
        application: product.application,
        category: product.category,
    };
    toggleModal('counter-part-modal', false);
    _counterPartContext = null;
    renderOnlineReportItems();
};

// ---- OR Item List Modal (for notes with 0 items) ----
window._onlineReportAddedItems = window._onlineReportAddedItems || {};

let _orItemPage = 1;
const _orItemPageSize = 50;
let _orItemTotalPages = 1;
let _orItemProducts = [];
let _orItemFiltered = [];
let _orCurrentNoteIdx = -1;

function normalizeRemainingQty(item) {
    const directRemaining = item.remaining_qty ?? item.remaining;
    if (directRemaining !== undefined && directRemaining !== null && directRemaining !== '') {
        return Math.max(0, Number(directRemaining) || 0);
    }

    const orderedQty = Number(item.qty ?? item.quantity ?? 0);
    const actualQty = Number(item.actual_qty ?? item.served_qty ?? 0);
    if (orderedQty || actualQty) {
        return Math.max(orderedQty - actualQty, 0);
    }

    return Math.max(0, Number(item.on_hand ?? item.balance_stock ?? 0) || 0);
}

window.openORItemListModal = function(noteIdx) {
    _orCurrentNoteIdx = noteIdx;
    _orItemPage = 1;
    _orItemProducts = [];
    _orItemFiltered = [];
    document.getElementById('or-item-list-tbody').innerHTML = '<tr><td colspan="8" class="p-6 text-center text-slate-300 italic">Loading items...</td></tr>';
    toggleModal('or-item-list-modal', true);
    loadORProducts(1);
};

window.closeORItemListModal = function() {
    toggleModal('or-item-list-modal', false);
    _orCurrentNoteIdx = -1;
};

window.loadORProducts = async function(page) {
    const tbody = document.getElementById('or-item-list-tbody');
    try {
        const url = window.salesOrderRoutes.productsUrl || '/admin/sales/sales-note/products';
        const params = new URLSearchParams();
        params.set('page', String(page));
        params.set('perPage', String(_orItemPageSize));

        const searchKeyMap = ['', 'code', 'description', 'part_number', 'description', 'application', 'position', 'specification'];
        const inputs = document.querySelectorAll('#or-item-list-modal .or-item-search');
        inputs.forEach(inp => {
            const col = parseInt(inp.getAttribute('data-or-col')) || 0;
            const val = inp.value.trim();
            if (val && searchKeyMap[col]) {
                params.set('search[' + searchKeyMap[col] + ']', val);
            }
        });

        const result = await fetchJsonOrThrow(
            url + '?' + params.toString(),
            {},
            'Online report item list request'
        );

        if (result.success) {
            const pagination = result.pagination || {};
            _orItemProducts = (Array.isArray(result.products) ? result.products : []).map(item => ({
                ...item,
                remaining_qty: normalizeRemainingQty(item),
            }));
            _orItemFiltered = [..._orItemProducts];
            _orItemPage = pagination.current_page || page || 1;
            _orItemTotalPages = pagination.last_page || 1;
            renderORItemPage();
        } else {
            tbody.innerHTML = '<tr><td colspan="8" class="p-6 text-center text-slate-300 italic">No products found.</td></tr>';
        }
    } catch (e) {
        console.error(e);
        tbody.innerHTML = '<tr><td colspan="8" class="p-6 text-center text-red-400 italic">Error loading products.</td></tr>';
    }
};

function renderORItemPage() {
    const tbody = document.getElementById('or-item-list-tbody');
    const data = _orItemFiltered || [];
    const pageItems = data;

    if (pageItems.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="p-6 text-center text-slate-300 italic">No matching items.</td></tr>';
    } else {
        tbody.innerHTML = pageItems.map(p => {
            const checked = (_orItemCheckedIds && _orItemCheckedIds.has(p.id)) ? 'checked' : '';
            const remainingQty = normalizeRemainingQty(p);
            return '<tr class="hover:bg-slate-50 transition-colors cursor-pointer" onclick="selectORItem(' + p.id + ')" data-pid="' + p.id + '">' +
                '<td class="p-3 px-4"><input type="checkbox" class="or-item-cb rounded border-slate-300 text-maroon focus:ring-maroon" value="' + p.id + '" ' + checked + ' onclick="event.stopPropagation(); toggleORItemCheckbox(' + p.id + ', this.checked)"></td>' +
                '<td class="p-3 px-4 font-bold text-maroon">' + (p.product_code || '') + '</td>' +
                '<td class="p-3 px-4 font-bold text-slate-800">' + (p.description || '') + '</td>' +
                '<td class="p-3 px-4 text-slate-600">' + (p.part_number || '') + '</td>' +
                '<td class="p-3 px-4 text-slate-600">' + (p.description || '') + '</td>' +
                '<td class="p-3 px-4 text-slate-600">' + (p.application || '') + '</td>' +
                '<td class="p-3 px-4 text-slate-600">' + (p.position || '') + '</td>' +
                '<td class="p-3 px-4 text-slate-600">' + (p.specification || '') + '</td>' +
                '<td class="p-3 px-4 text-center"><input type="number" class="or-item-qty w-16 px-2 py-1 border-2 border-emerald-300 bg-emerald-50 rounded-lg text-center font-bold text-emerald-800 focus:ring-2 focus:ring-emerald-400 outline-none" value="' + remainingQty + '" min="0" onclick="event.stopPropagation()"></td>' +
            '</tr>';
        }).join('');
    }

    const tp = _orItemTotalPages || 1;
    document.getElementById('or-item-page-info').textContent = 'Page ' + _orItemPage + ' of ' + tp;
    document.getElementById('or-item-prev-btn').disabled = _orItemPage <= 1;
    document.getElementById('or-item-next-btn').disabled = _orItemPage >= tp || tp <= 1;
    updateORItemSelectedCount();
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

let _orItemCheckedIds = new Set();

window.toggleORSelectAll = function(checked) {
    document.querySelectorAll('#or-item-list-tbody .or-item-cb').forEach(cb => {
        cb.checked = checked;
        const pid = parseInt(cb.value);
        if (checked) _orItemCheckedIds.add(pid);
        else _orItemCheckedIds.delete(pid);
    });
    updateORItemSelectedCount();
};

window.toggleORItemCheckbox = function(pid, checked) {
    if (checked) _orItemCheckedIds.add(pid);
    else _orItemCheckedIds.delete(pid);
    updateORItemSelectedCount();
};

function updateORItemSelectedCount() {
    document.getElementById('or-item-selected-count').textContent = _orItemCheckedIds.size + ' selected';
}

window.filterORItemTable = function() {
    _orItemPage = 1;
    loadORProducts(1);
};

window.prevORItemPage = function() {
    if (_orItemPage > 1) {
        loadORProducts(_orItemPage - 1);
    }
};

window.nextORItemPage = function() {
    const tp = _orItemTotalPages || 1;
    if (_orItemPage < tp) {
        loadORProducts(_orItemPage + 1);
    }
};

window.selectORItem = function(productId) {
    const product = _orItemProducts.find(p => p.id === productId);
    if (!product || _orCurrentNoteIdx < 0) return;

    const row = document.querySelector('#or-item-list-tbody tr[data-pid="' + productId + '"]');
    const qtyInput = row ? row.querySelector('.or-item-qty') : null;
    const qty = qtyInput ? parseInt(qtyInput.value) || 1 : 1;

    const stockIssues = checkORItemStock([product]);
    if (stockIssues.length > 0) {
        showORStockWarning(stockIssues);
        setTimeout(function() {
            if (confirm('Add this item anyway?')) {
                addItemToOnlineReport(_orCurrentNoteIdx, product, qty);
                _orItemCheckedIds.clear();
                closeORItemListModal();
                renderOnlineReportItems();
            }
        }, 100);
        return;
    }

    addItemToOnlineReport(_orCurrentNoteIdx, product, qty);
    _orItemCheckedIds.clear();
    closeORItemListModal();
    renderOnlineReportItems();
};

window.addORSelectedItems = function() {
    if (_orCurrentNoteIdx < 0) return;
    const selected = _orItemFiltered.filter(p => _orItemCheckedIds.has(p.id));
    if (selected.length === 0) { alert('Please select at least one item.'); return; }

    // Check stock first
    const stockIssues = checkORItemStock(selected);
    if (stockIssues.length > 0) {
        showORStockWarning(stockIssues);
        return;
    }

    selected.forEach(p => {
        const row = document.querySelector('#or-item-list-tbody tr[data-pid="' + p.id + '"]');
        const qtyInput = row ? row.querySelector('.or-item-qty') : null;
        const qty = qtyInput ? parseInt(qtyInput.value) || 1 : 1;
        addItemToOnlineReport(_orCurrentNoteIdx, p, qty);
    });
    _orItemCheckedIds.clear();
    closeORItemListModal();
    renderOnlineReportItems();
};

function addItemToOnlineReport(noteIdx, product, qty) {
    if (!window._onlineReportAddedItems) window._onlineReportAddedItems = {};
    if (!window._onlineReportAddedItems[noteIdx]) window._onlineReportAddedItems[noteIdx] = [];

    const note = _onlineReportNotes[noteIdx] || {};
    const originalItems = getOnlineReportOriginalItems(note);
    const existsInOriginal = originalItems.some(i => String(i.product_id) === String(product.id));
    const existsInAdded = window._onlineReportAddedItems[noteIdx].some(i => String(i.product_id) === String(product.id));
    if (existsInOriginal || existsInAdded) {
        return;
    }

    const onlinePrice = parseFloat(
        product.price_online ?? product.online_price ?? product.price ?? product.selling_price ?? 0
    ) || 0;

    window._onlineReportAddedItems[noteIdx].push({
        product_id: product.id,
        product_code: product.product_code || '',
        description: product.description || '',
        part_number: product.part_number || '',
        application: product.application || '',
        position: product.position || '',
        specification: product.specification || '',
        quantity: qty || 1,
        oum: product.oum || '',
        unit_price: onlinePrice,
        price_online: onlinePrice,
        resolved_unit_price: onlinePrice,
    });
}

function shiftCounterPartKeysAfterAddedRemoval(noteIdx, removedFlatIdx) {
    const current = window._onlineReportCounterParts || {};
    const next = {};
    Object.keys(current).forEach(key => {
        const parts = key.split('-');
        if (String(parts[0]) !== String(noteIdx)) {
            next[key] = current[key];
            return;
        }

        const itemIdx = parseInt(parts[1], 10);
        if (Number.isNaN(itemIdx) || itemIdx < removedFlatIdx) {
            next[key] = current[key];
            return;
        }

        if (itemIdx > removedFlatIdx) {
            next[noteIdx + '-' + (itemIdx - 1)] = current[key];
        }
    });
    window._onlineReportCounterParts = next;
}

window.removeSelectedOnlineProduct = function(noteIdx, productId) {
    if (!window._onlineReportAddedItems || !window._onlineReportAddedItems[noteIdx]) return;

    syncOnlineQty();
    syncOnlinePrices();

    const addedItems = window._onlineReportAddedItems[noteIdx];
    const addedIdx = addedItems.findIndex(item => String(item.product_id) === String(productId));
    if (addedIdx < 0) return;

    const note = _onlineReportNotes[noteIdx] || {};
    const originalItems = getOnlineReportOriginalItems(note);
    const removedFlatIdx = originalItems.length + addedIdx;

    addedItems.splice(addedIdx, 1);
    shiftCounterPartKeysAfterAddedRemoval(noteIdx, removedFlatIdx);
    renderOnlineReportItems();
};

window.removeExistingOnlineReportProduct = function(noteIdx, itemIdx) {
    if (!window._editingReportId) return;

    syncOnlineQty();
    syncOnlinePrices();

    const note = _onlineReportNotes[noteIdx];
    if (!note) return;

    const originalItems = getOnlineReportOriginalItems(note);
    if (itemIdx < 0 || itemIdx >= originalItems.length) return;

    originalItems.splice(itemIdx, 1);
    shiftCounterPartKeysAfterAddedRemoval(noteIdx, itemIdx);
    renderOnlineReportItems();
};

function checkORItemStock(products) {
    const issues = [];
    products.forEach(p => {
        const onHand = parseInt(p.on_hand || p.balance_stock || 0);
        if (onHand < 1) {
            issues.push({
                product_id: p.id,
                product_code: p.product_code,
                description: p.description,
                requested_qty: 1,
                available_stock: onHand,
                reason: onHand <= 0 ? 'Out of stock' : 'Not enough stock',
            });
        }
    });
    return issues;
}

function showORStockWarning(issues) {
    // Simple alert for now
    const msg = issues.map(i =>
        '• ' + (i.product_code || 'ID-' + i.product_id) + ' - ' + i.description +
        ' (Requested: ' + i.requested_qty + ', Available: ' + i.available_stock + ') - ' + i.reason
    ).join('\n');
    alert('Cannot proceed with the following low stock/out of stock items:\n\n' + msg);
}

function renderOnlineReportNotesTable() {
    const tbody = document.getElementById('or-notes-tbody');
    if (!_onlineReportNotes || _onlineReportNotes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="p-6 text-center text-slate-300 italic">No sales notes found.</td></tr>';
        return;
    }
    tbody.innerHTML = _onlineReportNotes.map((note, idx) => {
        return '<tr class="or-note-row" data-idx="' + idx + '">' +
            '<td class="p-3 px-4 font-bold text-slate-800">' + (note.sales_number || '---') + '</td>' +
            '<td class="p-3 px-4"><input type="text" class="or-invoice-input w-full px-2 py-1.5 border border-slate-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon" placeholder="Invoice #"></td>' +
            '<td class="p-3 px-4"><input type="text" class="or-address-input w-full px-2 py-1.5 border border-slate-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon" placeholder="Address"></td>' +
        '</tr>';
    }).join('');
}

window.onPlatformCheck = function(cb) {
    const row = cb.closest('tr');
    const idx = cb.getAttribute('data-idx');
    if (cb.checked) {
        row.querySelectorAll('.or-platform-cb').forEach(other => {
            if (other !== cb) other.checked = false;
        });
    }
    row.classList.remove('bg-orange-100', 'bg-blue-100', 'bg-yellow-100');
    if (cb.checked) {
        const p = cb.getAttribute('data-platform');
        if (p === 'shopee') row.classList.add('bg-orange-100');
        else if (p === 'lazada') row.classList.add('bg-blue-100');
        else if (p === 'tiktok') row.classList.add('bg-yellow-100');
    }
};

document.addEventListener('change', function(e) {
    const globalCb = e.target.closest('.platform-global-cb');
    if (globalCb) {
        const platform = globalCb.getAttribute('data-platform');
        const checked = globalCb.checked;
        document.querySelectorAll('.or-platform-cb[data-platform="' + platform + '"]').forEach(cb => {
            cb.checked = checked;
            if (checked) {
                const row = cb.closest('tr');
                row.querySelectorAll('.or-platform-cb').forEach(other => {
                    if (other !== cb) other.checked = false;
                });
            }
            row.classList.remove('bg-orange-100', 'bg-blue-100', 'bg-yellow-100');
            if (cb.checked) {
                const p = cb.getAttribute('data-platform');
                if (p === 'shopee') row.classList.add('bg-orange-100');
                else if (p === 'lazada') row.classList.add('bg-blue-100');
                else if (p === 'tiktok') row.classList.add('bg-yellow-100');
            }
        });
    }
});

window.addDateRangeRow = function(defaultYear = null) {
    const container = document.getElementById('date-range-container');
    const rowId = 'dr-row-' + (_dateRangeRowCount++);
    const row = document.createElement('div');
    row.id = rowId;
    row.className = 'flex items-center space-x-3 p-4 bg-slate-50 rounded-xl border border-slate-200';
    row.innerHTML = 
        '<select onchange="onDateRangeTypeChange(\'' + rowId + '\')" class="dr-type px-3 py-2.5 border border-slate-200 rounded-xl text-xs font-bold outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon bg-white">' +
            '<option value="annual">Annual</option>' +
            '<option value="monthly">Monthly</option>' +
            '<option value="specific">Specific Date</option>' +
        '</select>' +
        '<div class="dr-inputs flex items-center space-x-2 flex-1"></div>' +
        '<button onclick="this.closest(\'div\').remove()" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all">' +
            '<i data-lucide="x" class="w-4 h-4"></i>' +
        '</button>';
    container.appendChild(row);
    onDateRangeTypeChange(rowId);
    if (defaultYear !== null) {
        const yearSelect = row.querySelector('.dr-val');
        if (yearSelect) yearSelect.value = String(defaultYear);
    }
    if (typeof lucide !== 'undefined') lucide.createIcons();
};

window.addDefaultOnlineReportDateRanges = function() {
    const container = document.getElementById('date-range-container');
    if (!container) return;

    container.innerHTML = '';
    _dateRangeRowCount = 0;

    const currentYear = new Date().getFullYear();
    for (let year = currentYear - 3; year <= currentYear; year++) {
        addDateRangeRow(year);
    }
};

window.onDateRangeTypeChange = function(rowId) {
    const row = document.getElementById(rowId);
    if (!row) return;
    const type = row.querySelector('.dr-type').value;
    const inputsDiv = row.querySelector('.dr-inputs');
    inputsDiv.innerHTML = '';
    const years = [];
    for (let y = 2015; y <= 3000; y++) years.push(y);
    const yearOpts = years.map(y => '<option value="' + y + '">' + y + '</option>').join('');
    
    if (type === 'annual') {
        inputsDiv.innerHTML = '<select class="dr-val w-full px-3 py-2.5 border border-slate-200 rounded-xl text-xs font-bold outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon bg-white">' + yearOpts + '</select>';
    } else if (type === 'monthly') {
        inputsDiv.innerHTML = 
            '<select class="dr-month px-3 py-2.5 border border-slate-200 rounded-xl text-xs outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon bg-white">' +
                '<option value="">Month</option>' +
                '<option value="1">Jan</option><option value="2">Feb</option><option value="3">Mar</option><option value="4">Apr</option><option value="5">May</option><option value="6">Jun</option>' +
                '<option value="7">Jul</option><option value="8">Aug</option><option value="9">Sep</option><option value="10">Oct</option><option value="11">Nov</option><option value="12">Dec</option>' +
            '</select>' +
            '<select class="dr-year w-full px-3 py-2.5 border border-slate-200 rounded-xl text-xs font-bold outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon bg-white">' + yearOpts + '</select>';
    } else if (type === 'specific') {
        inputsDiv.innerHTML = 
            '<input type="date" class="dr-from flex-1 px-3 py-2.5 border border-slate-200 rounded-xl text-xs outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon">' +
            '<span class="text-xs font-bold text-slate-400">to</span>' +
            '<input type="date" class="dr-to flex-1 px-3 py-2.5 border border-slate-200 rounded-xl text-xs outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon">';
    }
};

window.goToOnlineReportStep2 = function() {
    document.getElementById('or-step-1-content').classList.add('hidden');
    document.getElementById('or-step-2-content').classList.remove('hidden');
    document.getElementById('or-footer-1').style.display = 'none';
    document.getElementById('or-footer-2').style.display = 'flex';
    document.getElementById('online-report-subtitle').textContent = 'Step 2: Selected Items';
    document.getElementById('or-step-1-indicator').className = 'w-8 h-8 rounded-full bg-white/20 text-white/60 font-bold flex items-center justify-center text-sm';
    document.getElementById('or-step-2-indicator').className = 'w-8 h-8 rounded-full bg-gold text-maroon font-bold flex items-center justify-center text-sm shadow';
    document.getElementById('or-step-1-label').className = 'text-xs font-bold text-white/60';
    document.getElementById('or-step-2-label').className = 'text-xs font-bold text-white';
    
    renderOnlineReportItems();
};

function getOnlineReportOriginalItems(note) {
    if (!note) return [];
    if (window._editingReportId) {
        return Array.isArray(note.items) ? note.items : [];
    }
    return Number(note.net_total || 0) === 0 ? [] : (Array.isArray(note.items) ? note.items : []);
}

function syncOnlinePrices() {
    document.querySelectorAll('.or-price-input').forEach(input => {
        const noteIdx = parseInt(input.getAttribute('data-note-idx'));
        const itemIdx = parseInt(input.getAttribute('data-item-idx'));
        if (isNaN(noteIdx) || isNaN(itemIdx)) return;

        const note = _onlineReportNotes[noteIdx];
        if (!note) return;

        const price = parseFloat(input.value) || 0;
        const originalItems = getOnlineReportOriginalItems(note);
        let target = null;

        if (itemIdx < originalItems.length) {
            target = originalItems[itemIdx];
        } else {
            const addedIdx = itemIdx - originalItems.length;
            const addedItems = (window._onlineReportAddedItems && window._onlineReportAddedItems[noteIdx]) || [];
            target = addedItems[addedIdx] || null;
        }

        if (target) {
            target.price_online = price;
            target.resolved_unit_price = price;
            target.unit_price = price;
        }
    });
}

function syncOnlineQty() {
    document.querySelectorAll('.or-qty-input').forEach(input => {
        const noteIdx = parseInt(input.getAttribute('data-or-note-idx'));
        const itemIdx = parseInt(input.getAttribute('data-or-item-idx'));
        if (isNaN(noteIdx) || isNaN(itemIdx)) return;

        const note = _onlineReportNotes[noteIdx];
        if (!note) return;

        const qty = parseInt(input.value) || 1;
        const originalItems = getOnlineReportOriginalItems(note);

        if (itemIdx < originalItems.length) {
            originalItems[itemIdx].quantity = qty;
            return;
        }

        const addedIdx = itemIdx - originalItems.length;
        const addedItems = (window._onlineReportAddedItems && window._onlineReportAddedItems[noteIdx]) || [];
        if (addedIdx >= 0 && addedItems[addedIdx]) {
            addedItems[addedIdx].quantity = qty;
        }
    });
}
window.updateOnlineReportTotalAmount = function() {
    let total = 0;
    document.querySelectorAll('#or-items-container .or-qty-input').forEach(qtyInput => {
        const row = qtyInput.closest('tr');
        const priceInput = row?.querySelector('.or-price-input');
        if (!priceInput) return;

        const qty = parseFloat(qtyInput.value) || 0;
        const price = parseFloat(priceInput.value);
        total += qty * (Number.isFinite(price) ? price : 0);
    });

    const totalEl = document.getElementById('or-total-amount');
    if (totalEl) {
        totalEl.textContent = '₱ ' + total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
};

function renderOnlineReportItems() {
    syncOnlineQty();
    syncOnlinePrices(); // Save current input values before re-rendering
    const container = document.getElementById('or-items-container');
    document.getElementById('or-items-loading').classList.add('hidden');
    container.innerHTML = '';
    
    _onlineReportNotes.forEach((note, noteIdx) => {
        // Notes with net_total == 0 had 0 original items (created via Online toggle); ignore DB items
        const items = getOnlineReportOriginalItems(note);
        const addedItems = (window._onlineReportAddedItems && window._onlineReportAddedItems[noteIdx]) || [];
        const hasItems = items.length > 0 || addedItems.length > 0;
        
        const card = document.createElement('div');
        card.className = 'bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden';
        
        // Header
        let cardHtml = '<div class="px-6 py-4 border-b border-slate-100 bg-maroon/5 flex justify-between items-center">' +
            '<div><span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Sales Note</span><span class="ml-2 text-sm font-extrabold text-slate-800">' + (note.sales_number || '---') + '</span></div>' +
        '</div>';
        
        if (!hasItems) {
            // Show search bar for empty notes
            cardHtml += '<div class="p-8 text-center space-y-4">' +
                '<p class="text-xs text-slate-400 italic">This sales note has no items.</p>' +
                '<button onclick="openORItemListModal(' + noteIdx + ')" class="px-6 py-3 bg-maroon text-white rounded-2xl text-xs font-bold hover:bg-maroon-800 transition-all shadow-lg shadow-maroon/20 flex items-center space-x-2 mx-auto">' +
                    '<i data-lucide="search" class="w-4 h-4"></i>' +
                    '<span>Search & Add Items</span>' +
                '</button>' +
            '</div>';
        } else {
            cardHtml += '<div class="overflow-x-auto">' +
                '<table class="w-full text-left">' +
                    '<thead class="bg-slate-50">' +
                        '<tr>' +
                            '<th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Item Code</th>' +
                            '<th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Description</th>' +
                            '<th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">QTY</th>' +
                            '<th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">UNIT</th>' +
                            '<th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Price Online</th>' +
                            '<th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Counter Part</th>' +
                            '<th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Action</th>' +
                        '</tr>' +
                    '</thead>' +
                    '<tbody class="divide-y divide-slate-100 text-sm">' +
                        // Original note items
                        items.map((item, itemIdx) => {
                            const qty = item.quantity || 0;
                            const oum = item.oum || '';
                            const priceOnline = item.resolved_unit_price ?? item._resolved_price ?? item.price_online ?? item.unit_price ?? 0;
                            const productId = item.product_id || 0;
                            const cpKey = noteIdx + '-' + itemIdx;
                            const cpData = window._onlineReportCounterParts[cpKey];
                            const cpHtml = cpData
                                ? '<div class="flex items-center space-x-1">' +
                                    '<span class="text-[9px] font-bold text-maroon truncate max-w-[80px]" title="' + (cpData.product_code || '') + '">' + (cpData.product_code || '') + '</span>' +
                                    '<button onclick="openCounterPartModal(' + noteIdx + ',' + itemIdx + ')" class="p-1 text-slate-400 hover:text-maroon hover:bg-maroon/5 rounded transition-all" title="Edit Counter Part"><i data-lucide="pencil" class="w-3 h-3"></i></button>' +
                                  '</div>'
                                : '<button onclick="openCounterPartModal(' + noteIdx + ',' + itemIdx + ')" class="px-2 py-1 bg-slate-100 hover:bg-maroon hover:text-white text-slate-500 text-[9px] font-bold rounded-lg transition-all">+ Add</button>';
                            return '<tr>' +
                                '<td class="p-3 px-4 font-bold text-slate-800">' + (item.product_code || '---') + '</td>' +
                                '<td class="p-3 px-4 text-slate-600 max-w-xs truncate">' + (item.description || item.product_code || '---') + '</td>' +
                                '<td class="p-3 px-4 text-center"><input type="number" class="or-qty-input w-20 px-3 py-1.5 border border-slate-200 rounded-lg text-center font-bold text-slate-800 focus:ring-2 focus:ring-maroon/20 outline-none" data-or-item-idx="' + itemIdx + '" data-or-note-idx="' + noteIdx + '" value="' + qty + '" min="1"></td>' +
                                '<td class="p-3 px-4 text-center"><input type="text" class="or-unit-input w-20 px-3 py-1.5 border border-slate-200 rounded-lg text-center text-slate-600 focus:ring-2 focus:ring-maroon/20 outline-none" data-or-item-idx="' + itemIdx + '" data-or-note-idx="' + noteIdx + '" value="' + oum + '"></td>' +
                                '<td class="p-3 px-4 text-center"><input type="number" step="0.01" class="or-price-input w-28 px-3 py-2 border border-slate-200 rounded-xl text-xs font-bold text-right outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon" data-product-id="' + productId + '" data-note-idx="' + noteIdx + '" data-item-idx="' + itemIdx + '" value="' + parseFloat(priceOnline).toFixed(2) + '"></td>' +
                                '<td class="p-3 px-4 text-center">' + cpHtml + '</td>' +
                                '<td class="p-3 px-4 text-center">' +
                                    (window._editingReportId
                                        ? '<button type="button" onclick="removeExistingOnlineReportProduct(' + noteIdx + ', ' + itemIdx + ')" class="p-1.5 bg-red-50 text-red-500 hover:bg-red-100 hover:text-red-700 rounded-lg transition-all" title="Delete item from this Online Invoice"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>'
                                        : '<span class="text-slate-300">---</span>') +
                                '</td>' +
                            '</tr>';
                        }).join('') +
                        // Added items (from item list modal) - index offset by original items length
                        addedItems.map((item, addedIdx) => {
                            const flatIdx = items.length + addedIdx;
                            const qty = item.quantity || 1;
                            const oum = item.oum || '';
                            const priceOnline = item.resolved_unit_price ?? item._resolved_price ?? item.price_online ?? item.unit_price ?? 0;
                            const productId = item.product_id || 0;
                            const cpKey = noteIdx + '-' + flatIdx;
                            const cpData = window._onlineReportCounterParts[cpKey];
                            const cpHtml = cpData
                                ? '<div class="flex items-center space-x-1">' +
                                    '<span class="text-[9px] font-bold text-maroon truncate max-w-[80px]" title="' + (cpData.product_code || '') + '">' + (cpData.product_code || '') + '</span>' +
                                    '<button onclick="openCounterPartModal(' + noteIdx + ',' + flatIdx + ')" class="p-1 text-slate-400 hover:text-maroon hover:bg-maroon/5 rounded transition-all" title="Edit Counter Part"><i data-lucide="pencil" class="w-3 h-3"></i></button>' +
                                  '</div>'
                                : '<button onclick="openCounterPartModal(' + noteIdx + ',' + flatIdx + ')" class="px-2 py-1 bg-slate-100 hover:bg-maroon hover:text-white text-slate-500 text-[9px] font-bold rounded-lg transition-all">+ Add</button>';
                            return '<tr class="bg-emerald-50/30">' +
                                '<td class="p-3 px-4 font-bold text-maroon">' + item.product_code + '</td>' +
                                '<td class="p-3 px-4 text-slate-600 max-w-xs truncate">' + item.description + '</td>' +
                                '<td class="p-3 px-4 text-center"><input type="number" class="or-qty-input w-20 px-3 py-1.5 border border-slate-200 rounded-lg text-center font-bold text-slate-800 focus:ring-2 focus:ring-maroon/20 outline-none" data-or-item-idx="' + flatIdx + '" data-or-note-idx="' + noteIdx + '" value="' + qty + '" min="1"></td>' +
                                '<td class="p-3 px-4 text-center"><input type="text" class="or-unit-input w-20 px-3 py-1.5 border border-slate-200 rounded-lg text-center text-slate-600 focus:ring-2 focus:ring-maroon/20 outline-none" data-or-item-idx="' + flatIdx + '" data-or-note-idx="' + noteIdx + '" value="' + oum + '"></td>' +
                                '<td class="p-3 px-4 text-center"><input type="number" step="0.01" class="or-price-input w-28 px-3 py-2 border border-slate-200 rounded-xl text-xs font-bold text-right outline-none focus:ring-2 focus:ring-maroon/20 focus:border-maroon" data-product-id="' + productId + '" data-note-idx="' + noteIdx + '" data-item-idx="' + flatIdx + '" value="' + parseFloat(priceOnline).toFixed(2) + '"></td>' +
                                '<td class="p-3 px-4 text-center">' + cpHtml + '</td>' +
                                '<td class="p-3 px-4 text-center"><button type="button" onclick="removeSelectedOnlineProduct(' + noteIdx + ', \'' + String(productId).replace(/'/g, "\\'") + '\')" class="p-1.5 bg-red-50 text-red-500 hover:bg-red-100 hover:text-red-700 rounded-lg transition-all" title="Remove selected product"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button></td>' +
                            '</tr>';
                        }).join('') +
                    '</tbody>' +
                '</table>' +
            '</div>';
            
            // If note had 0 original items but has added items, show "Add More" button
            if (window._editingReportId || items.length === 0) {
                cardHtml += '<div class="p-3 border-t border-slate-100 bg-slate-50/50 text-center">' +
                    '<button onclick="openORItemListModal(' + noteIdx + ')" class="px-4 py-2 bg-maroon/10 text-maroon rounded-xl text-[10px] font-bold hover:bg-maroon hover:text-white transition-all flex items-center space-x-1 mx-auto">' +
                        '<i data-lucide="plus" class="w-3 h-3"></i>' +
                        '<span>Add More Items</span>' +
                    '</button>' +
                '</div>';
            }
        }
        
        card.innerHTML = cardHtml;
        container.appendChild(card);
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
    // Sync QTY edits back to state on every input change
    document.querySelectorAll('.or-qty-input').forEach(input => {
        input.addEventListener('input', function() {
            syncOnlineQty();
            if (typeof window.updateOnlineReportTotalAmount === 'function') window.updateOnlineReportTotalAmount();
        });
    });
    document.querySelectorAll('.or-price-input').forEach(input => {
        input.addEventListener('input', function() {
            syncOnlinePrices();
            if (typeof window.updateOnlineReportTotalAmount === 'function') window.updateOnlineReportTotalAmount();
        });
    });
    if (typeof window.updateOnlineReportTotalAmount === 'function') window.updateOnlineReportTotalAmount();
}

function getPlatformForNote(noteIdx) {
    const row = document.querySelector('#or-notes-tbody .or-note-row[data-idx="' + noteIdx + '"]');
    if (row) {
        const checked = row.querySelector('.or-platform-cb:checked');
        if (checked) return checked.getAttribute('data-platform');
    }
    return 'shopee';
}

window.goToOnlineReportStep1 = function() {
    document.getElementById('or-step-1-content').classList.remove('hidden');
    document.getElementById('or-step-2-content').classList.add('hidden');
    document.getElementById('or-footer-1').style.display = 'flex';
    document.getElementById('or-footer-2').style.display = 'none';
    document.getElementById('online-report-subtitle').textContent = 'Step 1: Basic Info';
    document.getElementById('or-step-1-indicator').className = 'w-8 h-8 rounded-full bg-gold text-maroon font-bold flex items-center justify-center text-sm shadow';
    document.getElementById('or-step-2-indicator').className = 'w-8 h-8 rounded-full bg-white/20 text-white/60 font-bold flex items-center justify-center text-sm';
    document.getElementById('or-step-1-label').className = 'text-xs font-bold text-white';
    document.getElementById('or-step-2-label').className = 'text-xs font-bold text-white/60';
    if (typeof lucide !== 'undefined') lucide.createIcons();
};

window.generateOnlineReceipt = async function() {
    if (window._onlineReportGenerating) {
        return;
    }
    window._onlineReportGenerating = true;

    const loadingOverlay = document.getElementById('or-loading-overlay');
    // The database synchronization overlay below is the authoritative loader.
    if (loadingOverlay) loadingOverlay.classList.add('hidden');
    const prices = {};
    document.querySelectorAll('.or-price-input').forEach(input => {
        const pid = input.getAttribute('data-product-id');
        const val = parseFloat(input.value);
        const noteIdx = input.getAttribute('data-note-idx');
        const itemIdx = input.getAttribute('data-item-idx');
        
        if (pid && pid !== '0' && !isNaN(val)) {
            prices[pid] = val;
            
            // Also include the counter part's product_id if it exists
            const cpKey = noteIdx + '-' + itemIdx;
            const cpData = window._onlineReportCounterParts[cpKey];
            if (cpData && cpData.product_id) {
                prices[cpData.product_id] = val;
            }
        }
    });
    
    if (Object.keys(prices).length > 0) {
        console.log('Attempting to save prices:', prices);
        try {
            const response = await fetch(window.salesOrderRoutes.onlinePricesUrl.replace('/online-prices', '/online-prices/save'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ prices: prices })
            });
            const result = await response.json();
            console.log('Save prices response:', result);
            if (!result.success) {
                console.error('Save prices failed:', result.message);
                alert('Warning: Failed to save some online prices. ' + (result.message || ''));
            } else {
                console.log('Successfully updated ' + result.updated_count + ' items');
            }
        } catch (err) {
            console.error('Save prices error:', err);
            alert('Error: Could not save online prices. Please check your connection.');
        }
    }
    
    const dateRanges = [];
    document.querySelectorAll('#date-range-container > div').forEach(row => {
        const type = row.querySelector('.dr-type')?.value || 'annual';
        let value = '';
        if (type === 'annual') value = row.querySelector('.dr-val')?.value || '';
        else if (type === 'monthly') {
            const m = row.querySelector('.dr-month')?.value || '';
            const y = row.querySelector('.dr-year')?.value || '';
            value = m && y ? y + '-' + m : '';
        }
        else if (type === 'specific') {
            const f = row.querySelector('.dr-from')?.value || '';
            const t = row.querySelector('.dr-to')?.value || '';
            value = f && t ? f + '|' + t : '';
        }
        if (value) dateRanges.push({ type: type, value: value });
    });
    
    const invoiceNumbers = {};
    document.querySelectorAll('#or-notes-tbody .or-note-row').forEach(row => {
        const idx = row.getAttribute('data-idx');
        const invoiceInput = row.querySelector('.or-invoice-input');
        if (invoiceInput && invoiceInput.value.trim()) {
            invoiceNumbers[idx] = invoiceInput.value.trim();
        }
    });
    
    const addresses = {};
    document.querySelectorAll('#or-notes-tbody .or-note-row').forEach(row => {
        const idx = row.getAttribute('data-idx');
        const addrInput = row.querySelector('.or-address-input');
        if (addrInput && addrInput.value.trim()) {
            addresses[idx] = addrInput.value.trim();
        }
    });
    
    // Collect qty and unit from DOM for added items
    const itemsQty = {};
    const itemsUnit = {};
    document.querySelectorAll('.or-qty-input').forEach(inp => {
        const noteIdx = inp.getAttribute('data-or-note-idx');
        const itemIdx = inp.getAttribute('data-or-item-idx');
        const key = noteIdx + '-' + itemIdx;
        itemsQty[key] = parseInt(inp.value) || 1;
    });
    document.querySelectorAll('.or-unit-input').forEach(inp => {
        const noteIdx = inp.getAttribute('data-or-note-idx');
        const itemIdx = inp.getAttribute('data-or-item-idx');
        const key = noteIdx + '-' + itemIdx;
        itemsUnit[key] = inp.value || '';
    });

    // Merge added items into notes data for the report/print view
    // Apply user-edited qty and unit from DOM to ALL items
    const mergedNotes = _onlineReportNotes.map((note, noteIdx) => {
        const added = (window._onlineReportAddedItems && window._onlineReportAddedItems[noteIdx]) || [];
        const origItems = getOnlineReportOriginalItems(note).map((item, idx) => {
            const key = noteIdx + '-' + idx;
            const qty = itemsQty[key] || item.quantity || 0;
            const oum = itemsUnit[key] || item.oum || '';
            return { ...item, quantity: qty, oum: oum };
        });
        if (added.length === 0) return { ...note, items: origItems };
        const mergedItems = origItems.concat(added.map((item, addIdx) => {
            const flatIdx = origItems.length + addIdx;
            const key = noteIdx + '-' + flatIdx;
            const qty = itemsQty[key] || item.quantity || 1;
            const oum = itemsUnit[key] || item.oum || '';
            return {
                product_id: item.product_id,
                product_code: item.product_code,
                description: item.description,
                quantity: qty,
                oum: oum,
                unit_price: item.unit_price || 0,
                subtotal: qty * (item.unit_price || 0),
                is_added: true,
            };
        }));
        return { ...note, items: mergedItems };
    });

    const receiptData = {
        date_ranges: dateRanges,
        prices: prices,
        invoice_numbers: invoiceNumbers,
        addresses: addresses,
        counter_parts: window._onlineReportCounterParts || {},
        notes: mergedNotes,
        note_ids: _onlineReportNotes.map(n => n.id),
        added_items: window._onlineReportAddedItems || {},
        items_qty: itemsQty,
        items_unit: itemsUnit,
    };
    
    toggleModal('online-report-modal', false);
    resetOnlineReportSyncProgress();
    showProductLedgerCheckingOverlay('Synchronizing Online Report across all required database tables...');
    markOnlineReportSyncInProgress();
    
    try {
        const res = await fetch(window.salesOrderRoutes.onlineGenerateUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ data: receiptData })
        });
        const responseText = await res.text();
        let json = {};
        try {
            json = responseText ? JSON.parse(responseText) : {};
        } catch (parseError) {
            throw new Error(`Online Report synchronization returned an invalid server response (HTTP ${res.status}).`);
        }

        if (json.table_progress) applyOnlineReportTableProgress(json.table_progress);

        if (res.ok && json.success) {
            if (loadingOverlay) loadingOverlay.classList.add('hidden');
            const reportId = json.online_report_id || json.report_id;
            const fallbackPrintUrl = (window.salesOrderRoutes?.onlinePrintUrl || '/special/sales/sales-order/online-print/:id').replace(':id', reportId);
            updateProductLedgerCheckingOverlay(
                json.reused_existing
                    ? 'Existing Online Report found. All required database tables were verified without duplicate inserts.'
                    : 'All required database tables were synchronized and verified successfully.'
            );
            showExistingSuccessNotification(json.message || 'Online Report synchronized successfully.');
            await wait(750);
            hideProductLedgerCheckingOverlay();
            await navigateToOnlinePrintWithW68(fallbackPrintUrl);
        } else {
            if (loadingOverlay) loadingOverlay.classList.add('hidden');
            const message = json.message || `Failed to synchronize Online Report (HTTP ${res.status}).`;
            updateProductLedgerCheckingOverlay('Synchronization failed. Review the table statuses below.');
            failOnlineReportSyncProgress(message, json.table_progress);
            showExistingErrorNotification(message);
            const failedReportId = json.online_report_id || json.report_id;
            if (failedReportId) {
                const retryPrintUrl = (window.salesOrderRoutes?.onlinePrintUrl || '/special/sales/sales-order/online-print/:id').replace(':id', failedReportId);
                showSyncAndPrintButton(failedReportId, retryPrintUrl, message);
            }
        }
    } catch (err) {
        if (loadingOverlay) loadingOverlay.classList.add('hidden');
        console.error('Generate online report error:', err);
        updateProductLedgerCheckingOverlay('Synchronization failed before all required tables were verified.');
        failOnlineReportSyncProgress(err.message || 'An error occurred while generating the Online Report.');
        showExistingErrorNotification(err.message || 'An error occurred while generating the Online Report.');
    } finally {
        window._onlineReportGenerating = false;
    }
};

// ===== ONLINE INVOICES TAB (server-paginated) =====
window._oiPage = 1;
window._oiPageSize = 50;
window._oiSearch = '';
window._oiTotalPages = 0;
let _oiSearchFields = {};
let _oiSearchDebounce = null;
let _oiAbortController = null;
let _oiRequestSeq = 0;

window.loadOnlineInvoices = async function() {
    window._oiPage = 1;
    window._oiSearch = '';
    _oiSearchFields = {};
    document.querySelectorAll('#tab-content-online-invoices .column-search-input').forEach(el => el.value = '');
    await fetchOiPage();
};

async function fetchOiPage() {
    const tbody = document.getElementById('online-invoices-tbody');
    if (!tbody) return;
    const requestSeq = ++_oiRequestSeq;
    if (_oiAbortController) {
        _oiAbortController.abort();
    }
    _oiAbortController = new AbortController();

    tbody.innerHTML = '<tr><td colspan="8" class="p-4 text-center text-slate-300 italic">Loading online invoices...</td></tr>';
    try {
        const params = new URLSearchParams();
        params.set('page', window._oiPage);
        params.set('perPage', window._oiPageSize);
        if (window._oiSearch) params.set('search', window._oiSearch);
        const fieldKeys = {
            0: 'date',
            1: 'invoice',
            2: 'sales_note',
            3: 'customer',
            6: 'creator',
        };
        Object.entries(_oiSearchFields).forEach(([colIndex, value]) => {
            const key = fieldKeys[colIndex];
            const term = String(value || '').trim();
            if (key && term) {
                params.set('search_fields[' + key + ']', term);
            }
        });
        const url = (window.salesOrderRoutes?.onlineInvoicesDataUrl || '/admin/sales/sales-order/online-invoices-data') + '?' + params.toString();
        const res = await fetch(url, {
            signal: _oiAbortController.signal,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const r = await res.json();
        if (requestSeq !== _oiRequestSeq) return;
        if (r.success) {
            window._oiTotalPages = r.last_page || 1;
            renderOiPage(r.reports || [], r.total || 0);
        } else {
            tbody.innerHTML = '<tr><td colspan="8" class="p-4 text-center text-slate-300 italic">No online invoices found.</td></tr>';
            setOiPagination(1, 0);
        }
    } catch (e) {
        if (e.name === 'AbortError') return;
        if (requestSeq !== _oiRequestSeq) return;
        console.error('Error loading online invoices:', e);
        tbody.innerHTML = '<tr><td colspan="8" class="p-4 text-center text-red-400 italic">Error loading online invoices.</td></tr>';
    }
}

window.fetchOiPage = fetchOiPage;

window.searchOiField = function(colIndex, value) {
    _oiSearchFields[colIndex] = String(value || '').trim();
    clearTimeout(_oiSearchDebounce);
    _oiSearchDebounce = setTimeout(() => {
        const parts = Object.values(_oiSearchFields).map(v => String(v || '').trim()).filter(v => v !== '');
        const minSearchLength = Number(window.salesOrderRoutes?.onlineInvoiceMinSearchLength ?? 0);
        window._oiSearch = parts.join(' ');
        window._oiPage = 1;
        if (window._oiSearch.length > 0 && window._oiSearch.length < minSearchLength) {
            if (_oiAbortController) {
                _oiAbortController.abort();
            }
            const tbody = document.getElementById('online-invoices-tbody');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="8" class="p-4 text-center text-slate-300 italic">Type at least ' + minSearchLength + ' characters to search online invoices.</td></tr>';
            }
            setOiPagination(1, 1);
            return;
        }
        fetchOiPage();
    }, 500);
};

function setOiPagination(page, totalPages) {
    document.getElementById('oi-page-info').textContent = 'Page ' + page + ' of ' + totalPages;
    document.getElementById('oi-prev-btn').disabled = page <= 1 || totalPages <= 1;
    document.getElementById('oi-next-btn').disabled = page >= totalPages || totalPages <= 1;
}

function renderOiPage(reports, total) {
    const tbody = document.getElementById('online-invoices-tbody');
    if (!tbody) return;
    if (reports.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="p-4 text-center text-slate-300 italic">No matching records.</td></tr>';
    } else {
        tbody.innerHTML = reports.map(o => `
            <tr class="hover:bg-slate-50 transition-colors group table-row-animate">
                <td class="p-4 px-6 text-slate-600">${o.created_at}</td>
                <td class="p-4 px-6"><span class="px-3 py-1 bg-maroon/10 text-maroon text-[10px] font-bold rounded-lg">${o.invoice_display}</span></td>
                <td class="p-4 px-6 font-mono text-xs text-slate-800 font-bold">${o.sales_notes}</td>
                <td class="p-4 px-6 text-slate-700">${o.customers}</td>
                <td class="p-4 px-6 text-center font-bold text-slate-800">${o.total_items}</td>
                <td class="p-4 px-6 font-bold text-maroon text-right">₱ ${parseFloat(o.total_amount).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                <td class="p-4 px-6 text-slate-500 text-xs">${o.created_by}</td>
                <td class="p-4 px-6 text-center">
                    <div class="flex items-center justify-center space-x-1">
                        <button onclick="openOnlineReportEdit(${o.id})" class="p-2 bg-slate-100 text-sky-600 hover:bg-sky-500 hover:text-white rounded-lg transition-all shadow-sm" title="Edit">
                            <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                        </button>
                        <button onclick="showDeleteOiConfirmation(${o.id}, '${String(o.invoice_display).replace(/'/g, "\\'").replace(/"/g, "&quot;")}', '${String(o.customers).replace(/'/g, "\\'").replace(/"/g, "&quot;")}', '${String(o.created_at).replace(/'/g, "\\'")}', ${o.total_items})" class="p-2 bg-slate-100 text-red-500 hover:bg-red-500 hover:text-white rounded-lg transition-all shadow-sm" title="Delete">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                        </button>
                        <button onclick="printOnlineReport(${o.id})" class="p-2 bg-maroon/10 text-maroon rounded-lg hover:bg-maroon hover:text-white transition-all shadow-sm" title="Print">
                            <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
    setOiPagination(window._oiPage, window._oiTotalPages);
}

window.prevOiPage = function() {
    if (window._oiPage > 1) { window._oiPage--; fetchOiPage(); }
};

window.nextOiPage = function() {
    if (window._oiPage < window._oiTotalPages) { window._oiPage++; fetchOiPage(); }
};

window.changeOiPerPage = function(val) {
    window._oiPageSize = parseInt(val);
    window._oiPage = 1;
    fetchOiPage();
};



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


// Proceed modal price code dropdown listeners (delegated)
document.getElementById('proceed-items-tbody')?.addEventListener('focusin', function(e) {
    const sel = e.target.closest('.p-item-price-code');
    if (sel) populatePriceCodeSelect(sel);
});
document.getElementById('proceed-items-tbody')?.addEventListener('change', function(e) {
    const sel = e.target.closest('.p-item-price-code');
    if (sel) handlePriceCodeChange(sel, false);
});

// ========== STOCK CHECKING FUNCTIONS ==========

async function checkSalesOrderStock() {
    console.log('=== checkSalesOrderStock called ===');
    const tbody = document.getElementById('proceed-items-tbody');
    if (!tbody) {
        console.log('tbody not found');
        return true;
    }

    const rows = Array.from(tbody.querySelectorAll('tr[data-product-id]'));
    console.log('Found rows:', rows.length);
    if (rows.length === 0) return true;

    const items = [];
    for (const row of rows) {
        const checkbox = row.querySelector('.p-item-checkbox');
        if (!checkbox || !checkbox.checked) continue;
        normalizeProceedQtyFromActual(row);

        const productId = parseInt(row.getAttribute('data-product-id')) || 0;
        const productCode = row.querySelector('.p-item-code')?.value || '';
        const description = row.querySelector('.p-item-desc')?.value || '';
        const actualQty = parseInt(row.querySelector('.p-item-actual-qty')?.value) || 0;
        const additionalQty = parseInt(row.querySelector('.p-item-add-qty')?.value) || 0;

        items.push({
            product_id: productId,
            product_code: productCode,
            description: description,
            actual_qty: actualQty,
            additional_qty: additionalQty,
        });
    }

    console.log('Items to check:', items.length, items);
    if (items.length === 0) return true;

    const url = window.salesOrderRoutes?.stockCheckUrl || '/admin/sales/sales-order/check-stock';
    console.log('Stock check URL:', url);
    
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.csrfToken || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ items }),
        });

        console.log('Response status:', res.status);
        
        let data;
        try {
            data = await res.json();
            console.log('Response data:', data);
        } catch (e) {
            console.error('JSON parse error:', e);
            alert('Server error (HTTP ' + res.status + '). The response was not valid JSON. Check server logs for details.');
            return false;
        }

        if (!res.ok) {
            console.error('Response not OK:', data);
            const errors = data?.errors ? Object.values(data.errors).flat().join('\n') : '';
            alert('Error: ' + (data?.message || 'Stock check failed') + (errors ? '\n\nDetails:\n' + errors : ''));
            return false;
        }

        if (!data?.success) {
            console.error('Success false:', data);
            alert('Error: ' + (data?.message || 'Stock check failed'));
            return false;
        }

        // Check if stock is OK (data.ok === true means no issues)
        if (data.ok === true) {
            console.log('Stock check passed - all items have sufficient stock');
            return true;
        }

        // If ok is false, there are stock issues
        const issues = Array.isArray(data.issues) ? data.issues : [];
        console.log('Stock issues found:', issues.length, issues);
        
        if (issues.length > 0) {
            console.log('Calling showStockWarningModal with issues:', issues);
            showStockWarningModal(issues);
            return false;
        }
        
        // Fallback: if no issues but ok is false, something went wrong
        console.warn('Stock check returned ok=false but no issues array');
        return false;
    } catch (e) {
        console.error('Fetch error:', e);
        alert('An error occurred while checking stock: ' + (e?.message || e));
        return false;
    }
}

function renderStockIssuesModal(issues) {
    const tbody = document.getElementById('stock-warning-tbody');
    const countEl = document.getElementById('stock-warning-count');
    if (!tbody) return;

    if (countEl) {
        countEl.textContent = issues.length.toString();
    }

    tbody.innerHTML = issues.map((i) => {
        const req = Number(i.requested_qty ?? 0);
        const avail = Number(i.available_stock ?? 0);
        const reason = i.reason || (avail <= 0 ? 'Out of stock' : 'Not enough stock');
        return `
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="p-3 font-bold text-maroon">${escapeHtml(i.product_code || '')}</td>
                <td class="p-3 text-slate-700">${escapeHtml(i.description || '')}</td>
                <td class="p-3 text-center font-bold text-slate-700">${req}</td>
                <td class="p-3 text-center font-bold ${avail <= 0 ? 'text-red-600' : 'text-amber-600'}">${avail}</td>
                <td class="p-3 text-center text-[10px] font-bold uppercase tracking-widest ${avail <= 0 ? 'text-red-600' : 'text-amber-600'}">${escapeHtml(reason)}</td>
            </tr>
        `;
    }).join('');
}

function showStockWarningModal(issues) {
    console.log('=== showStockWarningModal called with', issues.length, 'issues ===');
    renderStockIssuesModal(issues);
    console.log('About to call toggleModal for stock-warning-modal');
    
    // Make sure the modal exists
    const modal = document.getElementById('stock-warning-modal');
    if (!modal) {
        console.error('ERROR: stock-warning-modal element not found in DOM!');
        alert('Stock warning modal not found. Cannot display stock issues.');
        return;
    }
    console.log('Modal element found:', modal);
    
    toggleModal('stock-warning-modal', true);
    console.log('toggleModal called');
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

async function checkEditSalesOrderStock() {
    console.log('=== [STOCK CHECK] checkEditSalesOrderStock STARTED ===');
    const tbody = document.getElementById('edit-so-items-tbody');
    if (!tbody) {
        console.log('[STOCK CHECK] ERROR: edit-so-items-tbody not found');
        return true;
    }

    const rows = Array.from(tbody.querySelectorAll('tr[data-product-id]'));
    console.log('[STOCK CHECK] Found', rows.length, 'rows');
    if (rows.length === 0) return true;

    const items = [];
    for (const row of rows) {
        const checkbox = row.querySelector('.e-item-checkbox');
        if (!checkbox || !checkbox.checked) continue;

        const productId = parseInt(row.getAttribute('data-product-id')) || 0;
        const productCode = row.querySelector('.e-item-code')?.value || '';
        const description = row.querySelector('.e-item-desc')?.value || '';
        const actualQty = parseFloat(row.querySelector('.e-item-actual-qty')?.value) || 0;
        const additionalQty = parseFloat(row.querySelector('.e-item-add-qty')?.value) || 0;
        const oldQty = parseFloat(row.getAttribute('data-old-qty')) || 0;
        const hasLedger = row.getAttribute('data-has-ledger') === '1';

        console.log(`[STOCK CHECK] Row data: ${productCode}`);
        console.log(`  - productId: ${productId}`);
        console.log(`  - actualQty: ${actualQty}`);
        console.log(`  - oldQty: ${oldQty} (from data-old-qty attribute)`);
        console.log(`  - hasLedger: ${hasLedger} (from data-has-ledger attribute)`);
        console.log(`  - NEW item: ${!hasLedger}`);

        if (actualQty <= 0) continue;

        items.push({
            product_id: productId,
            product_code: productCode,
            description: description,
            actual_qty: actualQty,
            additional_qty: additionalQty,
            old_qty: oldQty,
        });
    }

    console.log('[STOCK CHECK] Items to check:', items.length);
    console.log('[STOCK CHECK] Items array:', JSON.stringify(items, null, 2));
    
    if (items.length === 0) {
        console.log('[STOCK CHECK] No items to check, returning true');
        return true;
    }

    // Get order number from modal
    const orderNumber = document.getElementById('edit-so-order-no')?.textContent || '';
    console.log('[STOCK CHECK] Order Number:', orderNumber);

    const url = window.salesOrderRoutes?.editStockCheckUrl || '/admin/sales/sales-order/edit-check-stock';
    console.log('[STOCK CHECK] API URL:', url);
    console.log('[STOCK CHECK] Note ID:', window._editNoteId);
    
    const payload = { 
        items,
        note_id: window._editNoteId,
        order_number: orderNumber
    };
    
    console.log('[STOCK CHECK] Sending payload:', JSON.stringify(payload, null, 2));
    
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.csrfToken || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        });

        console.log('[STOCK CHECK] Response status:', res.status);

        if (res.status === 404) {
            console.error('[STOCK CHECK] ERROR: Route not found! Run: php artisan route:clear');
            alert('Stock check endpoint not found (404). Please run "php artisan route:clear" and refresh the page.');
            return false;
        }

        let data;
        try { 
            data = await res.json();
            console.log('[STOCK CHECK] Response data:', JSON.stringify(data, null, 2));
        } catch (e) {
            console.error('[STOCK CHECK] Failed to parse JSON response:', e);
            alert('Server error (HTTP ' + res.status + '). The response was not valid JSON. Check server logs for details.');
            return false;
        }

        if (!res.ok) {
            const errors = data?.errors ? Object.values(data.errors).flat().join('\n') : '';
            console.error('[STOCK CHECK] Request failed:', data);
            alert('Error: ' + (data?.message || 'Stock check failed') + (errors ? '\n\nDetails:\n' + errors : ''));
            return false;
        }

        if (!data?.success) {
            console.error('[STOCK CHECK] Response success=false:', data);
            alert('Error: ' + (data?.message || 'Stock check failed'));
            return false;
        }

        console.log('[STOCK CHECK] data.ok =', data.ok);
        console.log('[STOCK CHECK] data.issues =', data.issues);

        if (data.ok === true) {
            console.log('[STOCK CHECK] ✓ Stock check PASSED, returning true');
            return true;
        }

        const issues = Array.isArray(data.issues) ? data.issues : [];
        console.log('[STOCK CHECK] ✗ Stock issues found:', issues.length);
        console.log('[STOCK CHECK] Issues array:', JSON.stringify(issues, null, 2));
        
        if (issues.length > 0) {
            console.log('[STOCK CHECK] Calling showStockWarningModal with', issues.length, 'issues');
            showStockWarningModal(issues);
            return false;
        }
        
        console.log('[STOCK CHECK] No issues but ok=false, returning false');
        return false;
    } catch (e) {
        console.error('[STOCK CHECK] Exception:', e);
        alert('An error occurred while checking stock: ' + (e?.message || e));
        return false;
    }
}

// ========== INVOICE SELECTION FOR EDIT ==========

window.showInvoiceSelectionForEdit = async function(noteId) {
    toggleModal('select-invoice-edit-modal', true);
    try {
        // Fetch note and its sales orders
        const noteInvoicesUrl = (window.salesOrderRoutes?.noteInvoicesUrl || `/admin/sales/sales-note/${noteId}/invoices`).replace(':noteId', noteId);
        const noteRes = await fetch(noteInvoicesUrl);
        const noteData = await noteRes.json();
        
        if (!noteData.success) {
            alert('Error loading sales note: ' + (noteData.message || 'Unknown error'));
            toggleModal('select-invoice-edit-modal', false);
            return;
        }
        
        // Display note info
        document.getElementById('edit-note-order-no').textContent = noteData.note.order_number || '---';
        document.getElementById('edit-note-customer').textContent = noteData.note.customer_name || '---';
        
        // Display invoice selection grid
        const grid = document.getElementById('invoice-selection-grid');
        if (noteData.invoices && noteData.invoices.length > 0) {
            grid.innerHTML = noteData.invoices.map(invoice => `
                <div onclick="selectInvoiceToEdit(${invoice.id})" 
                     class="p-6 border-2 border-slate-200 rounded-2xl hover:border-maroon hover:bg-maroon/5 cursor-pointer transition-all group">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1">
                            <h4 class="text-sm font-bold text-maroon group-hover:text-maroon-800 mb-1">${escapeHtml(invoice.invoice_numbers || 'N/A')}</h4>
                            <p class="text-[10px] text-slate-400 uppercase tracking-wider font-bold">Status: <span class="text-emerald-600">${escapeHtml(invoice.status || 'N/A')}</span></p>
                        </div>
                        <div class="p-2 bg-slate-100 rounded-lg group-hover:bg-maroon group-hover:text-white transition-all">
                            <i data-lucide="chevron-right" class="w-4 h-4"></i>
                        </div>
                    </div>
                    <div class="text-xs text-slate-600 space-y-1">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Items:</span>
                            <span class="font-bold">${invoice.items_count || 0}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Total:</span>
                            <span class="font-bold text-maroon">₱${parseFloat(invoice.total_amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                        </div>
                    </div>
                </div>
            `).join('');
            
            if (typeof lucide !== 'undefined') lucide.createIcons();
        } else {
            // If no invoices and note is Open, open Proceed modal instead
            const noteStatus = (noteData.note?.status || '').toLowerCase();
            if (noteStatus === 'open') {
                toggleModal('select-invoice-edit-modal', false);
                openProceedModal(noteId);
                return;
            }
            grid.innerHTML = '<div class="col-span-2 p-8 text-center text-slate-300 italic">No invoices found for this sales note.</div>';
        }
    } catch (e) {
        console.error('Error loading invoices:', e);
        alert('Error loading invoices for editing.');
        toggleModal('select-invoice-edit-modal', false);
    }
};

window.selectInvoiceToEdit = function(salesOrderId) {
    toggleModal('select-invoice-edit-modal', false);
    setTimeout(() => editSalesOrder(salesOrderId), 300);
};

// ========== EDIT SALES ORDER ==========

window.editSalesOrder = async function(salesOrderId) {
    // Get tracking params from URL (added/deleted items from sales-note edit)
    const urlParams2 = new URLSearchParams(window.location.search);
    const addedItemsStr = urlParams2.get('addedItems') || '';
    const deletedItemsStr = urlParams2.get('deletedItems') || '';
    let addedChangeItems = [];
    let deletedChangeItems = [];
    let updatedChangeItems = [];

    const storedChanges = sessionStorage.getItem('so_edit_changes');
    if (storedChanges) {
        try {
            const parsed = JSON.parse(storedChanges);
            if (parseInt(parsed.salesOrderId || 0, 10) === parseInt(salesOrderId || 0, 10)) {
                addedChangeItems = Array.isArray(parsed.addedItems) ? parsed.addedItems : [];
                deletedChangeItems = Array.isArray(parsed.deletedItems) ? parsed.deletedItems : [];
                updatedChangeItems = Array.isArray(parsed.updatedItems) ? parsed.updatedItems : [];
            }
        } catch (e) {
            console.error('Unable to parse Sales Note → Sales Order changes:', e);
        }
    }

    const addedItems = new Set([
        ...addedItemsStr.split(',').map(s => s.trim()).filter(Boolean),
        ...addedChangeItems.map(item => String(item.product_code || '').trim()).filter(Boolean),
    ]);
    const deletedItems = new Set([
        ...deletedItemsStr.split(',').map(s => s.trim()).filter(Boolean),
        ...deletedChangeItems.map(item => String(item.product_code || '').trim()).filter(Boolean),
    ]);
    const updatedItems = new Set(updatedChangeItems.map(item => String(item.product_code || '').trim()).filter(Boolean));
    window._editAddedItems = addedItems;
    window._editDeletedItems = deletedItems;
    window._editUpdatedItems = updatedItems;
    window._editDeletedChangeItems = deletedChangeItems;

    // Show blink legend if there are tracking items
    const legend = document.getElementById('edit-so-blink-legend');
    if (legend) {
        legend.classList.toggle('hidden', addedItems.size === 0 && deletedItems.size === 0);
    }

    toggleModal('edit-sales-order-modal', true);
    try {
        const url = (window.salesOrderRoutes?.editDetailUrl || '/admin/sales/sales-order/edit-detail/:salesOrderId').replace(':salesOrderId', salesOrderId);
        const res = await fetch(url);
        const r = await res.json();
        if (r.success) {
            const so = r.sales_order;

            // The Sales Note update already removed deleted lines from the
            // persisted Sales Order snapshot. Reconstruct those rows only for
            // review, exactly like the Purchase Order deferred-delete flow.
            const deletedChangeRows = Array.isArray(window._editDeletedChangeItems) ? window._editDeletedChangeItems : [];
            deletedChangeRows.forEach(deletedItem => {
                const code = String(deletedItem.product_code || '').trim();
                const pid = parseInt(deletedItem.product_id || 0, 10);
                const alreadyPresent = (so.items || []).some(item =>
                    (pid > 0 && parseInt(item.product_id || 0, 10) === pid) ||
                    (code && String(item.product_code || '').trim() === code)
                );
                if (!alreadyPresent) {
                    so.items.push({
                        ...deletedItem,
                        _deletedFromSalesNote: true,
                        has_ledger_entry: true,
                    });
                }
            });

            window._editNoteId = so.sales_note_id;
            window._editSalesOrderId = so.id;
            
            // Store original items for change detection
            window._originalEditItems = so.items.map(item => ({
                id: item.id,
                product_id: item.product_id,
                quantity: item.quantity,
                actual_qty: item.actual_qty,
                additional_qty: item.additional_qty || 0,
                unit_price: item.unit_price,
                discount: item.discount,
                particulars: item.particulars || ''
            }));

            document.getElementById('edit-so-order-no').textContent = so.order_number || '---';
            document.getElementById('edit-so-customer').textContent = so.customer_name || '---';
            document.getElementById('edit-so-date').textContent = so.order_date || '---';
            document.getElementById('edit-so-status').textContent = so.status || '---';
            const editOrderDateInput = document.getElementById('edit-so-order-date');
            if (editOrderDateInput) editOrderDateInput.value = so.order_date || '';

            // Invoice numbers
            const invField = document.getElementById('edit-so-invoices');
            if (so.invoice_numbers) {
                invField.value = so.invoice_numbers;
            } else {
                invField.value = '';
            }

            // Waybill
            document.getElementById('edit-so-waybill-no').value = so.waybill_no || '';
            document.getElementById('edit-so-waybill-date').value = so.waybill_date || '';

            // Remarks
            document.getElementById('edit-so-remarks').value = so.remarks || '';

            // Additional Discount - read from first item (same % applies to all)
            const firstItemAddlDisc = (so.items && so.items.length > 0) ? parseFloat(so.items[0].additional_discount) || 0 : 0;
            const addlDiscInput = document.getElementById('edit-so-addl-discount');
            if (addlDiscInput) addlDiscInput.value = firstItemAddlDisc;

            // Items
            const tbody = document.getElementById('edit-so-items-tbody');
            if (so.items && so.items.length > 0) {
                const addedItemsSet = window._editAddedItems || new Set();
                const deletedItemsSet = window._editDeletedItems || new Set();
                const updatedItemsSet = window._editUpdatedItems || new Set();
                tbody.innerHTML = so.items.map((item, idx) => {
                    const code = item.product_code || '';
                    const isDeletedFromSalesNote = Boolean(item._deletedFromSalesNote) || deletedItemsSet.has(code);
                    // Change-review priority mirrors Purchase Order:
                    // MAROON/YELLOW = deleted in Sales Note, excluded until Final Save
                    // YELLOW = newly added in Sales Note
                    // BLUE = existing line changed in Sales Note
                    // GREEN = no Product Ledger OUT entry yet
                    let blinkClass = '';
                    if (isDeletedFromSalesNote) {
                        blinkClass = ' sales-deleted-from-note-row';
                    } else if (addedItemsSet.has(code)) {
                        blinkClass = ' blink-item-yellow';
                    } else if (updatedItemsSet.has(code)) {
                        blinkClass = ' sales-updated-from-note-row';
                    } else if (!item.has_ledger_entry) {
                        blinkClass = ' blink-item-green';
                    }
                    const deleteFlag = isDeletedFromSalesNote ? '1' : '0';
                    const checkboxState = isDeletedFromSalesNote ? 'disabled' : 'checked';
                    return `
                    <tr class="${blinkClass}" data-product-id="${item.product_id || 0}" data-item-id="${item.id || 0}" data-old-qty="${item.old_qty_ledger || 0}" data-has-ledger="${item.has_ledger_entry ? '1' : '0'}" data-deleted-from-sales-note="${deleteFlag}">
                        <td class="p-2 px-3"><input type="checkbox" class="e-item-checkbox accent-maroon cursor-pointer" ${checkboxState} data-idx="${idx}"></td>
                        <td class="p-2 px-3"><input type="text" value="${escapeHtml(item.product_code || '')}" class="e-item-code text-xs w-full px-2 py-1 border border-slate-200 rounded-lg focus:ring-2 focus:ring-maroon/20 outline-none font-bold text-maroon" data-idx="${idx}" data-original-code="${escapeHtml(item.product_code || '')}" readonly style="min-width:150px;width:150px;white-space:normal;word-break:break-word;overflow-wrap:anywhere;"></td>
                        <td class="p-2 px-3"><select class="e-item-price-code price-code-select text-xs w-full px-2 py-1 border border-slate-200 rounded-lg focus:ring-2 focus:ring-maroon/20 outline-none text-slate-500" data-idx="${idx}" data-product-id="${item.product_id || 0}" style="min-width:150px;width:150px;white-space:normal;word-break:break-word;overflow-wrap:anywhere;"><option value="${escapeHtml(item.price_code || '')}">${escapeHtml(item.price_code || 'N/A')}</option></select></td>
                        <td class="p-2 px-3"><input type="text" value="${escapeHtml(item.description || '')}" class="e-item-desc text-xs w-full px-2 py-1 border border-slate-200 rounded-lg focus:ring-2 focus:ring-maroon/20 outline-none text-slate-700" data-idx="${idx}" readonly>${isDeletedFromSalesNote ? '<div class="mt-1 text-[10px] font-bold text-yellow-300">Deleted in Sales Note — Confirm Save to hard-delete its Product Ledger movement.</div>' : ''}</td>
                        <td class="p-2 px-3"><input type="number" value="${item.quantity}" min="0" class="e-item-qty text-xs w-16 px-2 py-1 border border-slate-200 rounded-lg focus:ring-2 focus:ring-maroon/20 outline-none text-center font-bold bg-white" data-idx="${idx}"></td>
                        <td class="p-2 px-3"><input type="number" value="${item.actual_qty}" min="0" class="e-item-actual-qty text-xs w-16 px-2 py-1 border border-amber-200 rounded-lg focus:ring-2 focus:ring-amber-300 outline-none text-center font-bold bg-amber-50" data-idx="${idx}"></td>
                        <td class="p-2 px-3"><input type="number" value="${item.additional_qty || 0}" min="0" class="e-item-add-qty text-xs w-16 px-2 py-1 border border-slate-200 rounded-lg focus:ring-2 focus:ring-maroon/20 outline-none text-center" data-idx="${idx}"></td>
                        <td class="p-2 px-3"><input type="text" value="${escapeHtml(item.oum || '')}" class="e-item-oum text-xs w-16 px-2 py-1 border border-slate-200 rounded-lg focus:ring-2 focus:ring-maroon/20 outline-none text-center uppercase text-slate-500" data-idx="${idx}"></td>
                        <td class="p-2 px-3"><input type="number" value="${item.unit_price}" step="0.01" min="0" class="e-item-price text-xs w-20 px-2 py-1 border border-slate-200 rounded-lg focus:ring-2 focus:ring-maroon/20 outline-none text-right" data-idx="${idx}"></td>
                        <td class="p-2 px-3"><input type="number" value="${item.discount}" step="0.01" min="0" max="100" class="e-item-disc text-xs w-14 px-2 py-1 border border-slate-200 rounded-lg focus:ring-2 focus:ring-maroon/20 outline-none text-center" data-idx="${idx}"></td>
                        <td class="p-2 px-3"><input type="text" value="${parseFloat(item.subtotal || 0).toFixed(2)}" class="e-item-subtotal text-xs w-full px-2 py-1 border border-slate-200 rounded-lg focus:ring-2 focus:ring-maroon/20 outline-none text-right font-bold text-maroon" data-idx="${idx}" readonly></td>
                        <td class="p-2 px-3"><input type="text" value="${escapeHtml(item.particulars || '')}" class="e-item-particulars text-xs w-full px-2 py-1 border border-slate-200 rounded-lg focus:ring-2 focus:ring-maroon/20 outline-none" placeholder="Notes..." data-idx="${idx}"></td>
                    </tr>
                `}).join('');

                // Attach recalc listeners
                tbody.querySelectorAll('.e-item-qty, .e-item-actual-qty, .e-item-price, .e-item-disc, .e-item-checkbox').forEach(el => {
                    el.addEventListener('input', function() {
                        editRecalcItemSubtotal(this.closest('tr'));
                    });
                    el.addEventListener('change', function() {
                        editRecalcItemSubtotal(this.closest('tr'));
                    });
                });
                // Select-all checkbox
                document.getElementById('select-all-edit-items')?.addEventListener('change', function() {
                    tbody.querySelectorAll('.e-item-checkbox').forEach(cb => {
                        if (!cb.disabled) cb.checked = this.checked;
                    });
                    tbody.querySelectorAll('.e-item-checkbox').forEach(cb => editRecalcItemSubtotal(cb.closest('tr')));
                });
                // Additional discount input
                document.getElementById('edit-so-addl-discount')?.addEventListener('input', function() {
                    tbody.querySelectorAll('.e-item-actual-qty').forEach(el => editRecalcItemSubtotal(el.closest('tr')));
                });
                // Price code dropdown: lazy load on focus, update price on change
                tbody.querySelectorAll('.e-item-price-code').forEach(el => {
                    el.addEventListener('focusin', function() { populatePriceCodeSelect(this); });
                    el.addEventListener('change', function() { handlePriceCodeChange(this, true); });
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="12" class="p-4 text-center text-slate-300 italic">No items found.</td></tr>';
            }
            if (storedChanges && deletedChangeItems.length + addedChangeItems.length + updatedChangeItems.length > 0) {
                sessionStorage.removeItem('so_edit_changes');
            }
            updateEditTotal();
            if (typeof lucide !== 'undefined') lucide.createIcons();
        } else {
            alert('Error loading sales order: ' + (r.message || 'Unknown error'));
            toggleModal('edit-sales-order-modal', false);
        }
    } catch (e) {
        console.error('Error loading edit detail:', e);
        alert('Error loading sales order for editing.');
        toggleModal('edit-sales-order-modal', false);
    }
};

function editRecalcItemSubtotal(row) {
    if (!row) return;
    const checked = row.querySelector('.e-item-checkbox')?.checked || false;
    const actualQty = parseFloat(row.querySelector('.e-item-actual-qty').value) || 0;
    const qtyEl = row.querySelector('.e-item-qty');
    const qty = parseFloat(qtyEl?.value) || 0;
    if (actualQty > qty) {
        qtyEl.value = actualQty;
    }
    const price = parseFloat(row.querySelector('.e-item-price').value) || 0;
    const disc = parseFloat(row.querySelector('.e-item-disc').value) || 0;
    const addlDisc = parseFloat(document.getElementById('edit-so-addl-discount')?.value) || 0;
    const subtotal = actualQty * price * (1 - disc / 100) * (checked ? (1 - addlDisc / 100) : 1);
    row.querySelector('.e-item-subtotal').value = subtotal.toFixed(2);
    updateEditTotal();
}

function updateEditTotal() {
    const tbody = document.getElementById('edit-so-items-tbody');
    if (!tbody) return;
    let total = 0;
    tbody.querySelectorAll('tr').forEach(row => {
        if (row.dataset.deletedFromSalesNote === '1') return;
        const checkbox = row.querySelector('.e-item-checkbox');
        if (checkbox && !checkbox.checked) return;
        total += parseFloat(row.querySelector('.e-item-subtotal')?.value) || 0;
    });
    const el = document.getElementById('edit-so-total-amount');
    if (el) el.textContent = '₱ ' + total.toLocaleString(undefined, {minimumFractionDigits: 2});
}

window.closeEditSalesOrder = function() {
    toggleModal('edit-sales-order-modal', false);
};

window.confirmEditSalesOrder = async function() {
    console.log('=== [EDIT SO] confirmEditSalesOrder STARTED - ' + new Date().toISOString() + ' ===');
    
    const stockOk = await checkEditSalesOrderStock();
    console.log('[EDIT SO] Stock check returned:', stockOk);
    if (!stockOk) {
        console.log('[EDIT SO] BLOCKING - Stock check failed');
        return;
    }
    console.log('[EDIT SO] PROCEEDING - Stock check passed');
    document.getElementById('confirm-edit-text').textContent = 'Are you sure you want to update this Sales Order? This will reverse the existing ledger entries and create new ones.';
    document.getElementById('confirm-edit-btn').onclick = finalizeEditSalesOrder;
    toggleModal('confirm-edit-order-modal', true);
};

window.finalizeEditSalesOrder = async function() {
    const invoices = document.getElementById('edit-so-invoices').value.split(',').map(v => v.trim()).filter(v => v);
    const orderDate = document.getElementById('edit-so-order-date')?.value || '';
    if (!orderDate) {
        alert('Please select the Sales Order date.');
        return;
    }
    const waybillNo = document.getElementById('edit-so-waybill-no').value.trim();
    const waybillDate = document.getElementById('edit-so-waybill-date').value;
    const remarks = document.getElementById('edit-so-remarks').value.trim();

    const addlDisc = parseFloat(document.getElementById('edit-so-addl-discount')?.value) || 0;
    const items = Array.from(document.querySelectorAll('#edit-so-items-tbody tr'))
        .filter(row => {
            if (row.dataset.deletedFromSalesNote === '1') return false;
            const checkbox = row.querySelector('.e-item-checkbox');
            return checkbox && checkbox.checked;
        })
        .map(row => {
            const codeInput = row.querySelector('.e-item-code');
            return {
                sales_order_item_id: parseInt(row.getAttribute('data-item-id') || '0', 10) || null,
                product_id: row.getAttribute('data-product-id') || 0,
                product_code: codeInput?.dataset?.originalCode || codeInput?.value || '',
                description: row.querySelector('.e-item-desc')?.value || '',
                price_code: row.querySelector('.e-item-price-code')?.value || '',
                quantity: parseInt(row.querySelector('.e-item-qty')?.value) || 0,
                actual_qty: parseFloat(row.querySelector('.e-item-actual-qty')?.value) || 0,
                additional_qty: parseFloat(row.querySelector('.e-item-add-qty')?.value) || 0,
                oum: (row.querySelector('.e-item-oum')?.value || '').trim(),
                unit_price: parseFloat(row.querySelector('.e-item-price')?.value) || 0,
                discount: parseFloat(row.querySelector('.e-item-disc')?.value) || 0,
                additional_discount: addlDisc,
                subtotal: parseFloat(row.querySelector('.e-item-subtotal')?.value) || 0,
                particulars: row.querySelector('.e-item-particulars')?.value || '',
            };
        });

    try {
        const url = (window.salesOrderRoutes?.updateUrl || '/admin/sales/sales-order/update/:salesOrderId').replace(':salesOrderId', window._editSalesOrderId);
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.csrfToken || '' },
            body: JSON.stringify({
                invoices: invoices,
                order_date: orderDate,
                waybill_no: waybillNo,
                waybill_date: waybillDate,
                remarks: remarks,
                items: items,
            }),
        });
        
        console.log('Edit SO response status:', res.status);
        const r = await res.json();
        console.log('Edit SO response data:', r);
        
        if (!res.ok) {
            const errors = r.errors ? Object.values(r.errors).flat().join('\n') : '';
            alert('Error: ' + (r.message || 'Request failed') + (errors ? '\n\nDetails:\n' + errors : ''));
            return;
        }
        
        if (r.success) {
            console.log('Edit SO success - closing modals');
            // Close both modals
            toggleModal('confirm-edit-order-modal', false);
            toggleModal('edit-sales-order-modal', false);
            
            // Show success modal
            document.getElementById('success-edit-text').textContent = 'The Sales Order has been successfully updated.';
            toggleModal('success-edit-order-modal', true);
            
            // Reload history + dashboard
            loadSalesOrderHistory();
            loadSalesOrderDashboard();
        } else {
            console.error('Edit SO failed:', r.message);
            alert('Error: ' + (r.message || 'Failed to update sales order.'));
        }
    } catch (e) {
        console.error('Error updating sales order:', e);
        alert('An error occurred: ' + e.message);
    }
};


// Function to check and highlight invoice/sales note from notification
function checkAndHighlightSalesOrder() {
    const highlightSalesNumber = sessionStorage.getItem('highlightSalesNumber');
    const highlightType = sessionStorage.getItem('highlightType');
    
    if (highlightSalesNumber) {
        // Switch to "All Orders" tab first
        const tabBtn = document.getElementById('tab-all');
        if (tabBtn && !tabBtn.classList.contains('active')) {
            switchSalesOrderTab('all');
        }
        
        // Wait for table to load, then find and highlight the row
        setTimeout(() => {
            const allRows = document.querySelectorAll('#all-orders-tbody tr');
            allRows.forEach(row => {
                const salesNumCell = row.querySelector('td:first-child');
                if (salesNumCell && salesNumCell.textContent.trim() === highlightSalesNumber) {
                    // Scroll to the row
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    
                    // Add blinking red effect for invoices, or just red for rush
                    if (highlightType === 'invoice') {
                        // Add blinking red class
                        row.classList.add('invoice-overdue-blink');
                        row.style.border = '3px solid #ea580c';
                        
                        // Remove blink after 10 seconds but keep subtle highlight
                        setTimeout(() => {
                            row.classList.remove('invoice-overdue-blink');
                            row.style.border = '';
                        }, 10000);
                    } else {
                        // Rush order - just static red highlight
                        row.style.border = '3px solid #dc2626';
                        row.style.boxShadow = '0 0 20px rgba(220, 38, 38, 0.5)';
                        row.style.backgroundColor = '#fef2f2';
                        
                        setTimeout(() => {
                            row.style.border = '';
                            row.style.boxShadow = '';
                            row.style.backgroundColor = '';
                        }, 5000);
                    }
                }
            });
        }, 1000);
        
        // Clear the session storage
        sessionStorage.removeItem('highlightSalesNumber');
        sessionStorage.removeItem('highlightType');
    }
}

// Add CSS for blinking animation
if (!document.getElementById('invoice-blink-styles')) {
    const style = document.createElement('style');
    style.id = 'invoice-blink-styles';
    style.textContent = `
        @keyframes blinkRedInvoice {
            0%, 100% { background-color: #fef2f2; }
            50% { background-color: #fca5a5; }
        }
        
        .invoice-overdue-blink {
            animation: blinkRedInvoice 1.5s ease-in-out infinite;
        }
        
        .invoice-overdue-blink td {
            color: #991b1b !important;
            font-weight: 600;
        }
    `;
    document.head.appendChild(style);
}

// Highlight rush note from notification click on Active Notes tab
function checkAndHighlightRushNote() {
    const highlightNote = sessionStorage.getItem('highlightRushNote');

    if (highlightNote) {
        const targetRow = document.querySelector(
            '#active-notes-tbody tr[data-sales-number="' + highlightNote + '"]'
        );

        if (targetRow) {
            targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
            targetRow.style.border = '3px solid #dc2626';
            targetRow.style.boxShadow = '0 0 20px rgba(220, 38, 38, 0.5)';

            setTimeout(() => {
                targetRow.style.border = '';
                targetRow.style.boxShadow = '';
            }, 5000);

            sessionStorage.removeItem('highlightRushNote');
        }
    }
}

// Re-fetch Active Notes when overdue rush notes data arrives asynchronously
window.addEventListener('rushNotificationsLoaded', function () {
    if (document.getElementById('active-notes-tbody') && typeof loadActiveNotes === 'function') {
        loadActiveNotes();
    }
});

// Call the highlight function when all orders are loaded
const originalLoadAllOrders = window.loadAllOrders;
window.loadAllOrders = async function(status) {
    await originalLoadAllOrders(status);
    checkAndHighlightSalesOrder();
};

// Also check on page load
document.addEventListener('DOMContentLoaded', function() {
    // Small delay to ensure table is rendered
    setTimeout(checkAndHighlightSalesOrder, 500);
});

window.reloadAllSalesOrderTabs = function() {
    loadSalesOrderDashboard();
    loadActiveNotes();
    loadSalesOrderHistory();
    loadOnlineInvoices();
    if (typeof loadAllOrders === 'function') {
        loadAllOrders(window._allOrdersStatus || 'All');
    }
};

// ===== ONLINE REPORT DATABASE SYNCHRONIZATION FLOW =====
const ONLINE_REPORT_SYNC_TABLES = [
    { key: 'online_reports', label: 'core4_sales_order.online_reports' },
    { key: 'sales_orders', label: 'core4_sales_order.sales_orders' },
    { key: 'sales_order_items', label: 'core4_sales_order.sales_order_items' },
    { key: 'product_ledgers', label: 'core4_ledger.product_ledgers' },
];

function ensureProductLedgerCheckingOverlay() {
    let overlay = document.getElementById('productLedgerCheckingOverlay');

    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'productLedgerCheckingOverlay';
        overlay.className = 'fixed inset-0 z-[10000] hidden items-center justify-center bg-black/65 backdrop-blur-sm p-4';
        overlay.setAttribute('aria-hidden', 'true');
        overlay.innerHTML = `
            <div class="w-full max-w-2xl overflow-hidden rounded-3xl bg-white shadow-2xl">
                <div class="bg-maroon px-6 py-5 text-white border-b-4 border-gold">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-extrabold tracking-tight">Synchronizing Online Report</h3>
                            <p id="productLedgerCheckingMessage" class="mt-1 text-xs text-white/70">Checking required database tables...</p>
                        </div>
                        <div class="h-10 w-10 rounded-full border-4 border-white/25 border-t-gold animate-spin"></div>
                    </div>
                </div>
                <div class="p-6 space-y-4">
                    <div id="onlineReportSyncRows" class="space-y-3"></div>
                    <div id="productLedgerSyncRetryPanel" class="hidden rounded-2xl border border-red-200 bg-red-50 p-4">
                        <p id="onlineReportSyncError" class="text-xs font-bold text-red-700">Synchronization failed.</p>
                        <div class="mt-3 flex justify-end gap-2">
                            <button type="button" onclick="hideProductLedgerCheckingOverlay()" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-600">Close</button>
                            <button id="productLedgerSyncPrintButton" type="button" class="px-4 py-2 rounded-xl bg-maroon text-white text-xs font-bold">Retry Sync & Print</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
    }

    const rows = overlay.querySelector('#onlineReportSyncRows');
    if (rows && !rows.dataset.ready) {
        rows.innerHTML = ONLINE_REPORT_SYNC_TABLES.map(table => `
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4" data-sync-table="${table.key}">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-[10px] font-extrabold uppercase tracking-widest text-slate-400">Database Table</div>
                        <div class="truncate text-xs font-bold text-slate-800" title="${table.label}">${table.label}</div>
                    </div>
                    <span data-sync-status class="shrink-0 rounded-full bg-slate-200 px-3 py-1 text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Waiting</span>
                </div>
                <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-slate-200">
                    <div data-sync-bar class="h-full w-0 rounded-full bg-maroon transition-all duration-500"></div>
                </div>
                <div class="mt-2 flex items-center justify-between gap-3 text-[10px] font-bold text-slate-400">
                    <span data-sync-detail>Waiting to verify...</span>
                    <span data-sync-percent>0%</span>
                </div>
            </div>
        `).join('');
        rows.dataset.ready = '1';
    }

    return overlay;
}

function setOnlineReportSyncTableState(key, state, detail, percent) {
    const overlay = ensureProductLedgerCheckingOverlay();
    const row = overlay.querySelector(`[data-sync-table="${key}"]`);
    if (!row) return;
    const status = row.querySelector('[data-sync-status]');
    const bar = row.querySelector('[data-sync-bar]');
    const detailEl = row.querySelector('[data-sync-detail]');
    const percentEl = row.querySelector('[data-sync-percent]');
    const safePercent = Math.max(0, Math.min(100, Number(percent) || 0));

    if (status) {
        status.className = 'shrink-0 rounded-full px-3 py-1 text-[10px] font-extrabold uppercase tracking-wider ' + (
            state === 'complete' ? 'bg-emerald-100 text-emerald-700' :
            state === 'failed' ? 'bg-red-100 text-red-700' :
            state === 'syncing' ? 'bg-amber-100 text-amber-700' :
            'bg-slate-200 text-slate-500'
        );
        status.textContent = state === 'complete' ? 'Verified' : state === 'failed' ? 'Failed' : state === 'syncing' ? 'Syncing' : 'Waiting';
    }
    if (bar) {
        bar.style.width = `${safePercent}%`;
        bar.className = 'h-full rounded-full transition-all duration-500 ' + (
            state === 'complete' ? 'bg-emerald-500' : state === 'failed' ? 'bg-red-500' : 'bg-maroon'
        ) + (state === 'syncing' ? ' animate-pulse' : '');
    }
    if (detailEl) detailEl.textContent = detail || '';
    if (percentEl) percentEl.textContent = `${Math.round(safePercent)}%`;
}

function resetOnlineReportSyncProgress() {
    ensureProductLedgerCheckingOverlay();
    ONLINE_REPORT_SYNC_TABLES.forEach(table => {
        setOnlineReportSyncTableState(table.key, 'waiting', 'Waiting to verify...', 0);
    });
    const retry = document.getElementById('productLedgerSyncRetryPanel');
    if (retry) retry.classList.add('hidden');
}

function markOnlineReportSyncInProgress() {
    const messages = {
        online_reports: 'Creating or reusing Online Report...',
        sales_orders: 'Creating or verifying Sales Orders...',
        sales_order_items: 'Creating or verifying Sales Order Items...',
        product_ledgers: 'Creating or repairing Product Ledger movements...',
    };
    ONLINE_REPORT_SYNC_TABLES.forEach((table, index) => {
        setOnlineReportSyncTableState(table.key, 'syncing', messages[table.key], 25 + (index * 10));
    });
}

function applyOnlineReportTableProgress(progress) {
    const rows = progress || {};
    ONLINE_REPORT_SYNC_TABLES.forEach(table => {
        const item = rows[table.key] || {};
        const complete = item.status === 'complete' || Number(item.percent) >= 100;
        const expected = Number(item.expected ?? 0);
        const actual = Number(item.actual ?? 0);
        const detail = expected > 0
            ? `${actual} of ${expected} required row${expected === 1 ? '' : 's'} verified`
            : 'No rows required';
        setOnlineReportSyncTableState(
            table.key,
            complete ? 'complete' : 'failed',
            detail,
            Number(item.percent ?? (complete ? 100 : 0))
        );
    });
}

function failOnlineReportSyncProgress(message, progress) {
    if (progress) {
        applyOnlineReportTableProgress(progress);
    } else {
        ONLINE_REPORT_SYNC_TABLES.forEach(table => {
            setOnlineReportSyncTableState(table.key, 'failed', 'Synchronization did not complete.', 0);
        });
    }
    const retryPanel = document.getElementById('productLedgerSyncRetryPanel');
    const errorEl = document.getElementById('onlineReportSyncError');
    if (errorEl) errorEl.textContent = message || 'Synchronization failed.';
    retryPanel?.classList.remove('hidden');
}

function showProductLedgerCheckingOverlay(message) {
    const overlay = ensureProductLedgerCheckingOverlay();
    const messageElement = document.getElementById('productLedgerCheckingMessage');
    if (messageElement) messageElement.textContent = message || 'Checking required database tables...';
    overlay.classList.remove('hidden');
    overlay.classList.add('flex');
    overlay.setAttribute('aria-hidden', 'false');
    document.body.classList.add('overflow-hidden');
}

function updateProductLedgerCheckingOverlay(message) {
    const messageElement = document.getElementById('productLedgerCheckingMessage');
    if (messageElement) messageElement.textContent = message;
}

function hideProductLedgerCheckingOverlay() {
    const overlay = document.getElementById('productLedgerCheckingOverlay');
    overlay?.classList.add('hidden');
    overlay?.classList.remove('flex');
    overlay?.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('overflow-hidden');
}

function showSalesOrderNotification(message, type) {
    const notice = document.createElement('div');
    notice.className = 'fixed top-4 right-4 z-[10002] px-6 py-3 rounded-xl shadow-2xl text-sm font-bold animate-fade-in text-white '
        + (type === 'error' ? 'bg-red-600' : 'bg-emerald-600');
    notice.textContent = message;
    document.body.appendChild(notice);
    setTimeout(function() { notice.remove(); }, 5000);
}

function showExistingSuccessNotification(message) {
    showSalesOrderNotification(message, 'success');
}

function showExistingErrorNotification(message) {
    showSalesOrderNotification(message, 'error');
}

function wait(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

/**
 * Show the global W68 animation BEFORE starting the expensive Online Print
 * navigation. The short paint wait is intentional: without it, some browsers
 * keep showing the Sales Order page while Laravel is still building the print
 * response and the loader is never visibly painted.
 */
async function navigateToOnlinePrintWithW68(printUrl) {
    if (window.W68Loader && typeof window.W68Loader.show === 'function') {
        // Mark this as a navigation loader, not a permanent/manual loader.
        // The global loader automatically clears the 'navigation' reason when
        // Chrome restores Sales Order from the back/forward cache.
        window.W68Loader.show('navigation');
        // Give Chrome one real rendering opportunity before navigation starts.
        await wait(50);
    }
    window.location.href = printUrl;
}

function showSyncAndPrintButton(reportId, printUrl, message) {
    const panel = document.getElementById('productLedgerSyncRetryPanel');
    const btn = document.getElementById('productLedgerSyncPrintButton');
    const errorEl = document.getElementById('onlineReportSyncError');

    if (errorEl && message) errorEl.textContent = message;
    panel?.classList.remove('hidden');

    if (btn) {
        btn.disabled = false;
        btn.textContent = 'Retry Sync & Print';
        btn.onclick = async function() {
            btn.disabled = true;
            btn.textContent = 'Synchronizing...';
            await verifyLedgerBeforePrint(reportId, printUrl);
            btn.disabled = false;
            btn.textContent = 'Retry Sync & Print';
        };
    }
}

function hideSyncAndPrintButton() {
    document.getElementById('productLedgerSyncRetryPanel')?.classList.add('hidden');
}

async function verifyLedgerBeforePrint(reportId, printUrl) {
    const accountPrefix = window.location.pathname.match(/\/(admin|regular|special)\//)?.[1] || 'admin';
    const fallbackVerificationUrl = `${window.location.origin}${window.location.pathname.split(`/${accountPrefix}/`)[0]}/${accountPrefix}/sales/sales-order/online-report/:reportId/verify-product-ledger`;
    const verificationUrl = (window.salesOrderRoutes?.onlineVerifyLedgerUrl || fallbackVerificationUrl)
        .replace(':reportId', encodeURIComponent(String(reportId)));

    hideSyncAndPrintButton();
    resetOnlineReportSyncProgress();
    showProductLedgerCheckingOverlay('Verifying Online Report across all required database tables...');
    markOnlineReportSyncInProgress();

    try {
        const response = await fetch(verificationUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || window.csrfToken || '',
            },
            body: JSON.stringify({}),
        });

        const responseText = await response.text();
        let result = null;
        try {
            result = responseText ? JSON.parse(responseText) : {};
        } catch (parseError) {
            const routeMessage = response.status === 404
                ? 'Online Report synchronization route was not found. Run php artisan optimize:clear and confirm the verify-product-ledger route is installed.'
                : `Online Report synchronization returned an invalid server response (HTTP ${response.status}).`;
            throw new Error(routeMessage);
        }

        if (result?.table_progress) applyOnlineReportTableProgress(result.table_progress);

        if (!response.ok || !result.success || !result.can_print) {
            throw new Error(result.message || `Online Report synchronization failed (HTTP ${response.status}).`);
        }

        updateProductLedgerCheckingOverlay('All required database tables are synchronized and verified.');
        showExistingSuccessNotification(result.message || 'Online Report synchronization completed successfully.');
        await wait(650);
        hideProductLedgerCheckingOverlay();
        await navigateToOnlinePrintWithW68(printUrl);
    } catch (error) {
        updateProductLedgerCheckingOverlay('Synchronization stopped because one or more required tables failed verification.');
        failOnlineReportSyncProgress(error.message);
        showExistingErrorNotification(error.message || 'Online Report synchronization failed.');
        showSyncAndPrintButton(reportId, printUrl, error.message);
        console.error('Verify Online Report sync error:', error);
    }
}

window.showProductLedgerCheckingOverlay = showProductLedgerCheckingOverlay;
window.updateProductLedgerCheckingOverlay = updateProductLedgerCheckingOverlay;
window.hideProductLedgerCheckingOverlay = hideProductLedgerCheckingOverlay;
window.showOnlineReportLoader = showProductLedgerCheckingOverlay;
window.hideOnlineReportLoader = hideProductLedgerCheckingOverlay;
window.showSyncAndPrintButton = showSyncAndPrintButton;
window.verifyLedgerBeforePrint = verifyLedgerBeforePrint;
window.applyOnlineReportTableProgress = applyOnlineReportTableProgress;
window.printOnlineReport = function(reportId) {
    const printUrl = (window.salesOrderRoutes?.onlinePrintUrl || '/special/sales/sales-order/online-print/:id').replace(':id', reportId);
    return verifyLedgerBeforePrint(reportId, printUrl);
};

function resetProductLedgerCheckingOverlay() {
    const overlay = document.getElementById('productLedgerCheckingOverlay');
    if (overlay) {
        overlay.classList.add('hidden');
        overlay.classList.remove('flex');
        overlay.setAttribute('aria-hidden', 'true');
    }
    document.body.classList.remove('overflow-hidden');
    hideSyncAndPrintButton();
}

document.addEventListener('DOMContentLoaded', resetProductLedgerCheckingOverlay);
window.addEventListener('pageshow', function() {
    resetProductLedgerCheckingOverlay();

    // Older cached Sales Order pages may still contain the previous 'manual'
    // W68 loader reason from Online Print navigation. Clear only that stale
    // reason when the page becomes visible again so Go Back cannot leave the
    // application covered by an endless loader.
    if (window.W68Loader && typeof window.W68Loader.hide === 'function') {
        window.W68Loader.hide('manual');
        window.W68Loader.hide('navigation');
    }
});

/* ADDITIVE FEATURE: Force close Partial Sales Notes from Sales Order Processing Step 1. */
(function () {
    function isSalesForceCloseAllowedStatus(status) {
        return String(status || '').trim().toLowerCase() === 'partial';
    }

    function syncSalesForceCloseButton() {
        const button = document.getElementById('sales-force-close-note-btn');
        if (!button) return;

        const note = window._proceedNoteData;
        const stepOne = document.getElementById('proceed-step-1');
        const isStepOne = !!stepOne && !stepOne.classList.contains('hidden');
        const allowed = isStepOne && note && isSalesForceCloseAllowedStatus(note.status);
        button.classList.toggle('hidden', !allowed);
    }

    window.syncSalesForceCloseButton = syncSalesForceCloseButton;

    window.openSalesForceCloseModal = function () {
        const note = window._proceedNoteData;
        if (!note || !window._proceedNoteId) {
            alert('Sales Note data is not loaded.');
            return;
        }
        if (!isSalesForceCloseAllowedStatus(note.status)) {
            alert('Only Partial Sales Notes can be force closed.');
            return;
        }

        const stepOne = document.getElementById('proceed-step-1');
        if (!stepOne || stepOne.classList.contains('hidden')) {
            alert('Force Close Note is available in Step 1 only.');
            return;
        }

        const remainingItems = Array.isArray(note.items) ? note.items : [];
        const remainingQty = remainingItems.reduce((sum, item) => {
            const qty = Number(item.remaining_qty ?? item.quantity ?? 0);
            return sum + (Number.isFinite(qty) ? Math.max(0, qty) : 0);
        }, 0);

        const noteNumber = document.getElementById('sales-fc-note-number');
        const customer = document.getElementById('sales-fc-customer');
        const itemCount = document.getElementById('sales-fc-item-count');
        const qty = document.getElementById('sales-fc-qty');

        if (noteNumber) noteNumber.textContent = note.sales_number || '---';
        if (customer) customer.textContent = note.customer_name || '---';
        if (itemCount) itemCount.textContent = remainingItems.length.toLocaleString();
        if (qty) qty.textContent = remainingQty.toLocaleString();

        toggleModal('force-close-sales-note-modal', true);
        if (typeof lucide !== 'undefined') lucide.createIcons();
    };

    window.closeSalesForceCloseModal = function () {
        toggleModal('force-close-sales-note-modal', false);
    };

    window.confirmSalesForceCloseNote = async function () {
        if (window._salesForceClosing) return;

        const note = window._proceedNoteData;
        const noteId = window._proceedNoteId;
        if (!note || !noteId || !isSalesForceCloseAllowedStatus(note.status)) {
            alert('Only Partial Sales Notes can be force closed.');
            window.closeSalesForceCloseModal();
            return;
        }

        const button = document.getElementById('sales-fc-confirm-btn');
        const originalHtml = button ? button.innerHTML : '';
        window._salesForceClosing = true;
        if (button) {
            button.disabled = true;
            button.innerHTML = '<span>Closing...</span>';
        }

        try {
            const template = window.salesOrderRoutes?.forceCloseNoteUrl || '/admin/sales/sales-order/force-close-note/:salesNoteId';
            const url = template.replace(':salesNoteId', encodeURIComponent(noteId));
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': window.csrfToken || '',
                },
                body: JSON.stringify({}),
            });
            const result = await res.json();
            if (!res.ok || !result.success) {
                throw new Error(result.message || 'Failed to force close Sales Note.');
            }

            note.status = 'Closed';
            window.closeSalesForceCloseModal();
            toggleModal('proceed-order-modal', false);
            syncSalesForceCloseButton();

            if (typeof loadActiveNotes === 'function') loadActiveNotes();
            if (typeof loadSalesOrderDashboard === 'function') loadSalesOrderDashboard();
            if (typeof reloadAllSalesOrderTabs === 'function') reloadAllSalesOrderTabs();

            const removedQty = Number(result.removed_quantity || 0).toLocaleString();
            alert(`Sales Note ${note.sales_number || ''} was force closed. ${removedQty} remaining quantity was removed. Existing invoiced Sales Order items were preserved.`);
        } catch (error) {
            console.error('Sales Note force close failed:', error);
            alert(error.message || 'Failed to force close Sales Note.');
        } finally {
            window._salesForceClosing = false;
            if (button) {
                button.disabled = false;
                button.innerHTML = originalHtml;
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        const stepOne = document.getElementById('proceed-step-1');
        const proceedModal = document.getElementById('proceed-order-modal');
        const status = document.getElementById('p-note-status');

        const observer = new MutationObserver(syncSalesForceCloseButton);
        if (stepOne) observer.observe(stepOne, { attributes: true, attributeFilter: ['class'] });
        if (proceedModal) observer.observe(proceedModal, { attributes: true, attributeFilter: ['class'] });
        if (status) observer.observe(status, { childList: true, characterData: true, subtree: true });
        syncSalesForceCloseButton();
    });
})();
