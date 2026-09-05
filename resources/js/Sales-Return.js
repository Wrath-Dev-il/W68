document.addEventListener('DOMContentLoaded', () => {
    if (typeof lucide !== 'undefined') lucide.createIcons();
    initSalesReturn();
});

function initSalesReturn() {
    console.log('Sales Return UI Initialized');
    loadSalesReturns();
    loadSalesReturnDashboard();
}

window.loadSalesReturnDashboard = async function() {
    try {
        const res = await fetch(window.salesReturnRoutes.dashboard);
        const r = await res.json();
        if (r.success) {
            const totalEl = document.getElementById('dash-total-returns');
            const newEl = document.getElementById('dash-new-requests');
            const avgEl = document.getElementById('dash-return-average');
            
            if (totalEl) totalEl.textContent = r.total_returns;
            if (newEl) newEl.textContent = r.new_requests;
            if (avgEl) avgEl.textContent = r.return_average;
        }
    } catch (e) {
        console.error('Error loading dashboard stats:', e);
    }
};

// Main Table State
let returnState = { page: 1, invoice: '', customer: '' };

window.loadSalesReturns = async function() {
    const tbody = document.getElementById('sales-return-tbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="6" class="p-12 text-center text-slate-300 italic">Loading sales returns...</td></tr>';
    
    try {
        const url = new URL(window.salesReturnRoutes.history, window.location.origin);
        url.searchParams.append('page', returnState.page);
        if (returnState.invoice) url.searchParams.append('invoice', returnState.invoice);
        if (returnState.customer) url.searchParams.append('customer', returnState.customer);

        const res = await fetch(url);
        const r = await res.json();
        
        if (r.success && r.returns && r.returns.length > 0) {
            tbody.innerHTML = r.returns.map(ret => `
                <tr class="hover:bg-maroon/[0.02] transition-colors group">
                    <td class="p-5 text-center"><input type="checkbox" value="${ret.id}" class="accent-maroon cursor-pointer return-checkbox"></td>
                    <td class="p-5 font-mono font-bold text-maroon">${ret.invoice_no}</td>
                    <td class="p-5 text-slate-700 font-medium">${ret.customer_name}</td>
                    <td class="p-5 text-slate-500">${new Date(ret.created_at).toLocaleDateString()}</td>
                    <td class="p-5 text-center">
                        <span class="px-3 py-1 bg-slate-100 text-slate-600 rounded-full font-bold text-[10px]">Return</span>
                    </td>
                    <td class="p-5 text-center">
                        <div class="flex items-center justify-center space-x-2">
                            <button onclick="editReturn('${ret.id}')" class="px-3 py-2 bg-maroon/10 text-maroon rounded-xl font-bold text-[10px] hover:bg-maroon/20 transition-all flex items-center space-x-1 group-hover:scale-105">
                                <i data-lucide="edit" class="w-3.5 h-3.5"></i>
                                <span>EDIT</span>
                            </button>
                            <button onclick="viewReturnDetails('${ret.id}')" class="px-3 py-2 bg-sky-50 text-sky-600 rounded-xl font-bold text-[10px] hover:bg-sky-100 transition-all flex items-center space-x-1 group-hover:scale-105">
                                <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                <span>VIEW</span>
                            </button>
                            <button onclick="printReturn('${ret.id}')" class="px-3 py-2 bg-amber-50 text-amber-600 rounded-xl font-bold text-[10px] hover:bg-amber-100 transition-all flex items-center space-x-1 group-hover:scale-105">
                                <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                                <span>PRINT</span>
                            </button>
                            <button onclick="confirmDeleteReturn('${ret.id}')" class="px-3 py-2 bg-red-50 text-red-600 rounded-xl font-bold text-[10px] hover:bg-red-100 transition-all flex items-center space-x-1 group-hover:scale-105">
                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                <span>DELETE</span>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
            
            updateReturnPaginationUI(r.page, r.last_page, r.total);
            if (typeof lucide !== 'undefined') lucide.createIcons();

            // Add select all listener
            const selectAll = document.getElementById('select-all-returns');
            if (selectAll) {
                selectAll.onchange = (e) => {
                    document.querySelectorAll('.return-checkbox').forEach(cb => cb.checked = e.target.checked);
                };
            }
        } else {
            tbody.innerHTML = '<tr><td colspan="6" class="p-12 text-center text-slate-300 italic">No sales returns found.</td></tr>';
            updateReturnPaginationUI(1, 1, 0);
        }
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="6" class="p-12 text-center text-red-400">Error loading data.</td></tr>';
    }
};

window.printReturn = async function(id) {
    try {
        const url = window.salesReturnRoutes.historyDetail.replace(':id', id);
        const res = await fetch(url);
        const r = await res.json();

        if (r.success && r.header) {
            const container = document.getElementById('print-slips-container');
            const template = document.getElementById('single-slip-template');
            if (!container || !template) return;

            container.innerHTML = ''; // Clear existing
            const slip = createPrintSlip(template, r.header, r.items);
            container.appendChild(slip);

            // Small delay to ensure DOM is updated before print
            setTimeout(() => {
                window.print();
            }, 500);
        } else {
            alert('Failed to load return details for printing.');
        }
    } catch (e) {
        console.error('Error printing return:', e);
        alert('An error occurred while trying to print.');
    }
};

window.printSelectedReturns = async function() {
    const checked = document.querySelectorAll('.return-checkbox:checked');
    if (checked.length === 0) {
        alert('Please select at least one return record to print.');
        return;
    }

    const container = document.getElementById('print-slips-container');
    const template = document.getElementById('single-slip-template');
    if (!container || !template) return;

    container.innerHTML = ''; // Clear existing
    const ids = Array.from(checked).map(cb => cb.value);

    // Show loading state or similar if needed
    for (const id of ids) {
        try {
            const url = window.salesReturnRoutes.historyDetail.replace(':id', id);
            const res = await fetch(url);
            const r = await res.json();

            if (r.success && r.header) {
                const slip = createPrintSlip(template, r.header, r.items);
                container.appendChild(slip);
            }
        } catch (e) {
            console.error(`Error fetching return ${id}:`, e);
        }
    }

    if (container.children.length > 0) {
        setTimeout(() => {
            window.print();
        }, 500);
    } else {
        alert('No data available to print.');
    }
};

function createPrintSlip(template, header, items) {
    const clone = template.content.cloneNode(true);
    
    // Populate text content
    clone.querySelector('.p-customer').textContent = ': ' + (header.customer_name || '---');
    clone.querySelector('.p-address').textContent = ': ' + (header.address || '---');
    clone.querySelector('.p-return-no').textContent = ': ' + (header.return_number || '---');
    clone.querySelector('.p-date').textContent = ': ' + new Date(header.created_at).toLocaleDateString();
    clone.querySelector('.p-invoice-no').textContent = ': ' + (header.invoice_no || '---');
    
    // Collect unique remarks from items
    const itemRemarks = (items || []).map(i => i.remarks).filter(r => r && r.trim() !== '' && r !== '---');
    const uniqueRemarks = [...new Set(itemRemarks)];
    clone.querySelector('.p-reason').textContent = uniqueRemarks.length > 0 ? uniqueRemarks.join('; ') : (header.remarks || '---');
    
    const cleanDesc = (d) => (d || '').replace(/\s*Converted from Partial\s*/gi, '');

    const itemsTbody = clone.querySelector('.p-items-tbody');
    itemsTbody.innerHTML = (items || []).map(item => {
        const qty = parseInt(item.quantity || item.qty || 0);
        const unitPrice = parseFloat(item.unit_price || 0);
        const discPct = parseFloat(item.discount || 0);
        const grossReturn = qty * unitPrice;
        const subtotal = grossReturn * (1 - discPct / 100);
        return `
        <tr>
            <td class="text-center font-bold">${qty}</td>
            <td>${item.oum || ''}</td>
            <td>
                <div class="font-bold">${cleanDesc(item.description)}</div>
                <div class="text-[10px] text-slate-500">${item.product_code} | ${item.part_number || '---'}</div>
                <div class="text-[10px] text-slate-600 mt-1">
                    <span class="font-bold uppercase">App:</span> ${item.application || '---'} | 
                    <span class="font-bold uppercase">Pos:</span> ${item.position || '---'}
                </div>
            </td>
            <td class="text-right">₱ ${unitPrice.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
            <td class="text-center">${discPct.toFixed(2)}%</td>
            <td class="text-right font-bold">₱ ${subtotal.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
        </tr>`;
    }).join('');

    // Calculate per-item subtotals (gross - discount) for the print formula
    let calcSubtotal = 0;
    (items || []).forEach(item => {
        const q = parseInt(item.quantity || item.qty || 0);
        const up = parseFloat(item.unit_price || 0);
        const dp = parseFloat(item.discount || 0);
        calcSubtotal += q * up * (1 - dp / 100);
    });

    const addlDiscPct = parseFloat(header.additional_discount_percent || 0);
    const addlDiscAmt = calcSubtotal * addlDiscPct / 100;
    const totalAmount = calcSubtotal - addlDiscAmt;

    clone.querySelector('.p-subtotal').textContent = '₱ ' + calcSubtotal.toLocaleString('en-US', {minimumFractionDigits: 2});
    clone.querySelector('.p-addl-disc-amt').textContent = '- ₱ ' + addlDiscAmt.toLocaleString('en-US', {minimumFractionDigits: 2});
    clone.querySelector('.p-addl-disc-pct').textContent = addlDiscPct.toFixed(2);
    if (addlDiscPct <= 0) {
        const row = clone.querySelector('.p-addl-disc-row');
        if (row) row.style.display = 'none';
    }
    clone.querySelector('.p-total-amount').textContent = '₱ ' + totalAmount.toLocaleString('en-US', {minimumFractionDigits: 2});
    clone.querySelector('.p-report-total').textContent = '₱ ' + totalAmount.toLocaleString('en-US', {minimumFractionDigits: 2});

    return clone;
}

window.searchSalesReturn = function(col, val) {
    if (col === 0) returnState.invoice = val;
    if (col === 1) returnState.customer = val;
    returnState.page = 1;
    clearTimeout(window._returnSearchTimer);
    window._returnSearchTimer = setTimeout(loadSalesReturns, 500);
};

window.changeReturnPage = function(dir) {
    returnState.page += dir;
    loadSalesReturns();
};

function updateReturnPaginationUI(page, lastPage, total) {
    const info = document.getElementById('return-page-info');
    const prevBtn = document.getElementById('return-prev-btn');
    const nextBtn = document.getElementById('return-next-btn');
    if (!info || !prevBtn || !nextBtn) return;
    
    info.textContent = `Showing Page ${page} of ${lastPage} (${total} Total Returns)`;
    prevBtn.disabled = page <= 1;
    nextBtn.disabled = page >= lastPage;
}

window.viewReturnDetails = async function(id) {
    toggleModal('view-return-modal', true);
    const tbody = document.getElementById('v-return-items-tbody');
    tbody.innerHTML = '<tr><td colspan="6" class="p-8 text-center text-slate-300 italic">Loading details...</td></tr>';

    try {
        const url = window.salesReturnRoutes.historyDetail.replace(':id', id);
        const res = await fetch(url);
        const r = await res.json();

        if (r.success && r.header) {
            const ret = r.header;
            document.getElementById('v-return-no').textContent = ret.return_number || '---';
            document.getElementById('v-invoice-no').textContent = ret.invoice_no || '---';
            document.getElementById('v-customer-name').textContent = ret.customer_name || '---';
            document.getElementById('v-return-date').textContent = new Date(ret.created_at).toLocaleString();
            document.getElementById('v-general-remarks').textContent = ret.remarks || 'No general remarks.';
            document.getElementById('v-total-items').textContent = ret.total_items || 0;
            document.getElementById('v-subtotal').textContent = '₱ ' + parseFloat(ret.subtotal || 0).toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('v-total-amount').textContent = '₱ ' + parseFloat(ret.total_amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2});

            tbody.innerHTML = (r.items || []).map(item => `
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="p-4 font-mono font-bold text-maroon">${item.product_code}</td>
                    <td class="p-4">
                        <div class="font-medium text-slate-700">${(item.description || '').replace(/\s*Converted from Partial\s*/gi, '')}</div>
                        <div class="text-[10px] text-slate-400">${item.part_number || '---'} | ${item.application || '---'} | ${item.position || '---'}</div>
                    </td>
                    <td class="p-4 text-center font-bold">${item.qty || item.quantity}</td>
                    <td class="p-4 text-slate-500 italic">${item.remarks || '---'}</td>
                    <td class="p-4 text-right font-bold text-slate-700">₱ ${parseFloat(item.subtotal || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                    <td class="p-4 text-right font-bold text-maroon">₱ ${parseFloat(item.return_amount > 0 ? item.return_amount : (item.subtotal || 0)).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="6" class="p-8 text-center text-red-400 italic">Failed to load return details.</td></tr>';
        }
    } catch (e) {
        console.error('Error loading return details:', e);
        tbody.innerHTML = '<tr><td colspan="6" class="p-8 text-center text-red-400 italic">Error loading data.</td></tr>';
    }
};

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
        const srModalIds = ['add-return-modal','invoice-search-modal','return-success-modal','view-return-modal','edit-return-modal','edit-return-confirm-modal','edit-return-success-modal','delete-return-confirm-modal','delete-return-success-modal'];
        const anyVisible = srModalIds.some(function(id) {
            var el = document.getElementById(id);
            return el && !el.classList.contains('hidden') && el.classList.contains('flex');
        });
        if (!anyVisible) document.body.style.overflow = 'auto';
    }
};

window.currentReturnStep = 1;
window._selectedInvoices = [];
window._invoiceItems = {};
window._invoiceSearchPage = 1;
window._invoiceSearchQuery = '';
window._invoiceSearchTotal = 0;
window._invoiceSearchLastPage = 1;
window._invoiceSearchAbort = null;
window._invoiceSearchDebounceTimer = null;
window._invoiceSearchSelected = new Map();

function invoiceSearchKey(inv) {
    const sourceType = inv.source_type || 'local';
    const sourceId = inv.source_id || inv.id || '';
    const invoiceNo = inv.invoice_no || inv.no || '';
    return [sourceType, sourceId, inv.id || '', invoiceNo].join(':').toUpperCase();
}

function invoiceUiKey(inv) {
    if (inv && inv.ui_key) return inv.ui_key;
    const raw = invoiceSearchKey(inv || {});
    let hash = 2166136261;
    for (let i = 0; i < raw.length; i++) {
        hash ^= raw.charCodeAt(i);
        hash = Math.imul(hash, 16777619);
    }
    return 'sr-' + (hash >>> 0).toString(36);
}

window.openInvoiceSearchModal = async function() {
    window._selectedInvoices = [];
    window._invoiceItems = {};
    window._invoiceSearchSelected = new Map();
    window._invoiceSearchPage = 1;
    window._invoiceSearchQuery = '';
    window.currentReturnStep = 1;
    goToReturnStep(1);

    const searchInput = document.getElementById('invoice-search-query-input');
    if (searchInput) searchInput.value = '';

    const tbody = document.getElementById('invoice-search-tbody');
    if (tbody) tbody.innerHTML = '<tr><td colspan="5" class="p-6 text-center text-slate-300 italic">Loading invoices...</td></tr>';
    renderInvoicePagination();
    toggleModal('invoice-search-modal', true);

    await loadInvoiceSearchPage(1);
};

window.loadInvoiceSearchPage = async function(page) {
    if (window._invoiceSearchAbort) window._invoiceSearchAbort.abort();
    const controller = new AbortController();
    window._invoiceSearchAbort = controller;

    const tbody = document.getElementById('invoice-search-tbody');
    if (tbody) tbody.innerHTML = '<tr><td colspan="5" class="p-6 text-center text-slate-300 italic">Loading invoices...</td></tr>';

    const params = new URLSearchParams();
    params.set('page', page);
    params.set('per_page', 50);
    if (window._invoiceSearchQuery) params.set('search', window._invoiceSearchQuery);

    try {
        const res = await fetch(window.salesReturnRoutes.invoices + '?' + params.toString(), {
            signal: controller.signal,
        });
        const r = await res.json();
        if (controller.signal.aborted) return;
        window._invoiceSearchAbort = null;

        if (!r.success) throw new Error(r.message || 'Failed to load invoices');

        const invoices = (r.data || r.invoices || []);
        window._invoiceSearchPage = (r.pagination && r.pagination.current_page) || page;
        window._invoiceSearchTotal = (r.pagination && r.pagination.total) || r.total || 0;
        window._invoiceSearchLastPage = (r.pagination && r.pagination.last_page) || r.last_page || 1;

        if (!tbody) return;
        tbody.innerHTML = '';

        if (invoices.length === 0) {
            const msg = window._invoiceSearchQuery ? 'No matching invoices found.' : 'No available invoices found.';
            tbody.innerHTML = '<tr><td colspan="5" class="p-6 text-center text-slate-300 italic">' + msg + '</td></tr>';
        } else {
            invoices.forEach(function(inv) {
                const key = invoiceSearchKey(inv);
                const uiKey = invoiceUiKey(inv);
                const wasSelected = window._invoiceSearchSelected.has(key);
                const row = document.createElement('tr');
                row.className = 'hover:bg-slate-50 transition-colors' + (wasSelected ? ' bg-amber-50' : '');
                row.innerHTML = `
                    <td class="p-4 text-center"><input type="checkbox" value="${inv.id}" data-no="${inv.no}" data-customer-id="${inv.customer_id}" data-customer-name="${inv.customer || '---'}" data-source-type="${inv.source_type || 'local'}" data-source-id="${inv.source_id || ''}" data-invoice-no="${inv.no}" data-ui-key="${uiKey}" class="accent-maroon cursor-pointer invoice-checkbox" ${wasSelected ? 'checked' : ''} onchange="toggleInvoiceSelection(this)"></td>
                    <td class="p-4 font-mono font-bold text-maroon">${inv.no} ${inv.source_type === 'online_report' ? '<span class="ml-2 px-2 py-0.5 rounded-full bg-maroon text-white text-[8px] font-bold uppercase tracking-wider">Online</span>' : '<span class="ml-2 px-2 py-0.5 rounded-full bg-slate-200 text-slate-600 text-[8px] font-bold uppercase tracking-wider">Local</span>'}</td>
                    <td class="p-4 text-slate-700 font-medium">${inv.customer || '---'}</td>
                    <td class="p-4 text-right text-maroon font-bold">₱ ${parseFloat(inv.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                    <td class="p-4 text-slate-500 text-[10px]">${inv.created_at ? new Date(inv.created_at).toLocaleDateString() : '---'}</td>
                `;
                tbody.appendChild(row);
            });
        }
        renderInvoicePagination();
    } catch (e) {
        if (e.name === 'AbortError') return;
        window._invoiceSearchAbort = null;
        console.error('Error loading invoices:', e);
        if (tbody) tbody.innerHTML = '<tr><td colspan="5" class="p-6 text-center text-red-400 italic">Error loading data.</td></tr>';
    }
};

function renderInvoicePagination() {
    const prevBtn = document.getElementById('invoice-prev-page');
    const nextBtn = document.getElementById('invoice-next-page');
    const indicator = document.getElementById('invoice-page-indicator');
    const info = document.getElementById('invoice-pagination-info');

    if (indicator) {
        indicator.textContent = window._invoiceSearchTotal > 0 ? 'Page ' + window._invoiceSearchPage + ' of ' + window._invoiceSearchLastPage : '';
    }
    if (info) {
        if (window._invoiceSearchTotal > 0) {
            const from = (window._invoiceSearchPage - 1) * 50 + 1;
            const to = Math.min(window._invoiceSearchPage * 50, window._invoiceSearchTotal);
            info.textContent = 'Showing ' + from + '–' + to + ' of ' + window._invoiceSearchTotal + ' invoices';
        } else {
            info.textContent = '';
        }
    }
    if (prevBtn) prevBtn.disabled = window._invoiceSearchPage <= 1;
    if (nextBtn) nextBtn.disabled = window._invoiceSearchPage >= window._invoiceSearchLastPage;
}

window.invoicePrevPage = function() {
    if (window._invoiceSearchPage <= 1) return;
    loadInvoiceSearchPage(window._invoiceSearchPage - 1);
};

window.invoiceNextPage = function() {
    if (window._invoiceSearchPage >= window._invoiceSearchLastPage) return;
    loadInvoiceSearchPage(window._invoiceSearchPage + 1);
};

window.handleInvoiceSearchInput = function() {
    const input = document.getElementById('invoice-search-query-input');
    if (!input) return;
    clearTimeout(window._invoiceSearchDebounceTimer);
    window._invoiceSearchDebounceTimer = setTimeout(function() {
        window._invoiceSearchQuery = input.value.trim();
        window._invoiceSearchPage = 1;
        loadInvoiceSearchPage(1);
    }, 300);
};

window.toggleInvoiceSelection = function(checkbox) {
    const row = checkbox.closest('tr');
    const key = (checkbox.dataset.sourceType || 'local') + ':' + checkbox.value;
    if (checkbox.checked) {
        if (row) row.classList.add('bg-amber-50');
        window._invoiceSearchSelected.set(key, {
            id: checkbox.value,
            no: checkbox.dataset.no,
            customer_id: checkbox.dataset.customerId,
            customer_name: checkbox.dataset.customerName,
            source_type: checkbox.dataset.sourceType || 'local',
            source_id: checkbox.dataset.sourceId || '',
            invoice_no: checkbox.dataset.invoiceNo || checkbox.dataset.no || '',
            ui_key: checkbox.dataset.uiKey || ''
        });
    } else {
        if (row) row.classList.remove('bg-amber-50');
        window._invoiceSearchSelected.delete(key);
    }
};

window.confirmInvoiceSelection = async function() {
    const selected = Array.from(window._invoiceSearchSelected.values());

    if (selected.length === 0) {
        alert('Please select at least one invoice.');
        return;
    }

    window._selectedInvoices = selected;

    // Keep item loading responsive without flooding MariaDB with many simultaneous scans.
    // A small worker pool is faster and safer on the local XAMPP/MariaDB setup.
    window._invoiceItems = {};
    const queue = selected.slice();

    const loadInvoiceItems = async function(inv) {
        const uiKey = invoiceUiKey(inv);
        try {
            let url = (window.salesReturnRoutes.items || '/admin/sales/sales-return/items/:id').replace(':id', inv.id);
            const params = new URLSearchParams();
            params.set('invoice_no', inv.invoice_no || inv.no || '');

            if (inv.source_type === 'online_report') {
                params.set('type', 'online_report');
                params.set('report_id', inv.source_id || '');
            } else if (inv.source_id) {
                // source_id is the exact sales_orders.id for Local invoices.
                // This avoids another lookup by sales_note_id on the backend.
                params.set('sales_order_id', inv.source_id);
            }

            url += (url.includes('?') ? '&' : '?') + params.toString();

            const res = await fetch(url);
            const r = await res.json();
            if (!res.ok || !r.success) {
                throw new Error(r.message || ('HTTP ' + res.status));
            }
            window._invoiceItems[uiKey] = r.items || [];
        } catch (e) {
            console.error('Error loading items for', inv.no, e);
            window._invoiceItems[uiKey] = [];
        }
    };

    const workerCount = Math.min(3, queue.length);
    const workers = Array.from({ length: workerCount }, async () => {
        while (queue.length > 0) {
            const inv = queue.shift();
            if (!inv) break;
            await loadInvoiceItems(inv);
        }
    });

    await Promise.all(workers);

    renderInvoiceTabs();
    renderSelectedInvoiceTags();
    toggleModal('invoice-search-modal', false);
};

function renderSelectedInvoiceTags() {
    const container = document.getElementById('selected-invoice-tags');
    const empty = document.getElementById('step-1-empty');
    if (!container) return;
    container.innerHTML = '';
    if (window._selectedInvoices.length === 0) {
        if (empty) empty.classList.remove('hidden');
        return;
    }
    if (empty) empty.classList.add('hidden');
    window._selectedInvoices.forEach(inv => {
        const tag = document.createElement('span');
        tag.className = 'px-3 py-1.5 bg-maroon/5 text-maroon text-[10px] font-bold rounded-full border border-maroon/10';
        tag.innerText = inv.no;
        container.appendChild(tag);
    });
}

function renderInvoiceTabs() {
    const tabContainer = document.getElementById('invoice-tabs-container');
    const contentContainer = document.getElementById('invoice-items-content');
    if (!tabContainer || !contentContainer) return;
    tabContainer.innerHTML = '';
    contentContainer.innerHTML = '';

    window._selectedInvoices.forEach((inv, idx) => {
        const uiKey = invoiceUiKey(inv);
        const items = window._invoiceItems[uiKey] || [];
        const tabBtn = document.createElement('button');
        tabBtn.className = `px-5 py-2.5 text-[10px] font-bold uppercase tracking-widest border-b-2 transition-all ${idx === 0 ? 'border-maroon text-maroon bg-maroon/5' : 'border-transparent text-slate-400 hover:text-slate-600'}`;
        tabBtn.innerText = inv.no;
        tabBtn.onclick = () => switchInvoiceTab(uiKey, tabBtn);
        tabContainer.appendChild(tabBtn);

        const defaultAddlDisc = items.reduce((max, item) => Math.max(max, parseFloat(item.additional_discount) || 0), 0);

        const content = document.createElement('div');
        content.id = `items-content-${uiKey}`;
        content.className = idx === 0 ? '' : 'hidden';
        content.innerHTML = `
            <table class="w-full">
                <thead>
                    <tr class="text-[9px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                        <th class="p-4 text-left">Product Code</th>
                        <th class="p-4 text-left">Description</th>
                        <th class="p-4 text-center">Return QTY</th>
                        <th class="p-4 text-center">Good Items</th>
                        <th class="p-4 text-center">Disc %</th>
                        <th class="p-4 text-left">Remarks</th>
                        <th class="p-4 text-right">Subtotal</th>
                        <th class="p-4 text-right">Return Amount</th>
                    </tr>
                </thead>
                <tbody class="text-xs divide-y divide-slate-50">
                    ${renderInvoiceItems(uiKey, items)}
                </tbody>
            </table>
            <div class="p-4 bg-slate-50 border-t border-slate-100 space-y-3">
                <div class="flex items-center space-x-4">
                    <div class="flex-1">
                        <p class="text-[9px] font-bold text-slate-400 uppercase mb-2">General Return Remarks for ${inv.no}</p>
                        <textarea id="general-remarks-${uiKey}" placeholder="Enter general reason for return..." class="w-full px-4 py-2 text-xs bg-white border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-maroon/10 h-20 resize-none"></textarea>
                    </div>
                    <div class="w-48">
                        <p class="text-[9px] font-bold text-slate-400 uppercase mb-2">Addl Disc (%)</p>
                        <input type="number" id="addl-disc-${uiKey}" value="${defaultAddlDisc}" min="0" max="100" step="0.01" oninput="recalcInvoiceAddlDisc('${uiKey}', this.value)" class="w-full px-3 py-2 text-xs bg-white border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-maroon/10 font-bold text-maroon text-center">
                    </div>
                </div>
            </div>
        `;
        contentContainer.appendChild(content);
    });

    if (tabContainer.children.length === 0) {
        tabContainer.innerHTML = '<p class="text-[10px] text-slate-400 italic">No invoices selected.</p>';
    }
}

function renderInvoiceItems(invNo, items) {
    if (!items || items.length === 0) {
        return `<tr><td colspan="8" class="p-8 text-center text-[10px] text-slate-400 italic">No items found for this invoice.</td></tr>`;
    }
    const cleanDesc = (d) => (d || '').replace(/\s*Converted from Partial\s*/gi, '');
    return items.map(item => {
        const unitPrice = parseFloat(item.unit_price) || 0;
        const qty = parseInt(item.quantity) || 0;
        const remaining = (item.remaining !== undefined && item.remaining !== null) ? (parseInt(item.remaining) || 0) : qty;
        const prevReturned = parseInt(item.previously_returned) || 0;
        const subtotal = parseFloat(item.subtotal) || 0;
        const partNumber = item.part_number || '';
        const application = item.application || '';
        const productId = item.product_id || '';
        const addlDisc = parseFloat(item.additional_discount) || 0;
        const disc = parseFloat(item.discount) || 0;
        return `
            <tr data-part-number="${partNumber}" data-application="${application}" data-product-id="${productId}" data-additional-discount="${addlDisc}">
                <td class="p-4 font-mono font-bold">${item.product_code || '---'}</td>
                <td class="p-4 text-slate-600">${cleanDesc(item.description)}</td>
                <td class="p-4">
                    <div class="flex items-center justify-center space-x-2">
                        <input type="number" value="" placeholder="0" min="0" max="${remaining}" oninput="updateReturnQty(this, ${remaining}, ${unitPrice})" class="w-16 px-2 py-1 border border-slate-200 rounded-lg text-center font-bold focus:ring-2 focus:ring-maroon/20 outline-none return-qty-input">
                        <span class="text-[10px] text-slate-400">/ ${remaining} remaining${prevReturned > 0 ? ` <span class="text-red-400 font-bold">(-${prevReturned} returned)</span>` : ''}</span>
                    </div>
                </td>
                <td class="p-4">
                    <input type="number" value="" placeholder="0" min="0" max="0" oninput="updateGoodItemsQty(this)" class="w-20 px-2 py-1 border border-slate-200 rounded-lg text-center font-bold text-emerald-700 focus:ring-2 focus:ring-emerald-500/20 outline-none good-items-input">
                </td>
                <td class="p-4">
                    <input type="number" value="${disc || ''}" placeholder="0" min="0" max="100" step="0.01" oninput="updateReturnQty(this.closest('tr').querySelector('.return-qty-input'), ${remaining}, ${unitPrice})" class="w-16 px-2 py-1 border border-slate-200 rounded-lg text-center font-bold focus:ring-2 focus:ring-maroon/20 outline-none item-disc-input">
                </td>
                <td class="p-4">
                    <input type="text" placeholder="Add remarks..." class="w-full px-3 py-1.5 bg-slate-50 border border-slate-100 rounded-lg text-[10px] outline-none focus:ring-2 focus:ring-maroon/10 return-item-remarks">
                </td>
                <td class="p-4 text-right font-bold text-slate-700 item-gross-subtotal">₱ 0.00</td>
                <td class="p-4 text-right font-bold text-maroon return-amount-cell" data-unit-price="${unitPrice}">₱ 0.00</td>
            </tr>
        `;
    }).join('');
}

window.switchInvoiceTab = function(invNo, tabBtn) {
    document.querySelectorAll('#invoice-tabs-container button').forEach(btn => {
        btn.classList.remove('border-maroon', 'text-maroon', 'bg-maroon/5');
        btn.classList.add('border-transparent', 'text-slate-400');
    });
    tabBtn.classList.add('border-maroon', 'text-maroon', 'bg-maroon/5');
    tabBtn.classList.remove('border-transparent', 'text-slate-400');

    document.querySelectorAll('#invoice-items-content > div').forEach(div => div.classList.add('hidden'));
    document.getElementById(`items-content-${invNo}`).classList.remove('hidden');
};

window.getInvoiceAddlDiscRate = function(row) {
    const contentDiv = row.closest('[id^="items-content-"]');
    if (!contentDiv) return 0;
    const invId = contentDiv.id.replace('items-content-', '');
    const input = document.getElementById(`addl-disc-${invId}`);
    return parseFloat(input?.value) || 0;
};

window.calcItemAmounts = function(row) {
    const qtyInput = row.querySelector('.return-qty-input');
    const qty = parseInt(qtyInput?.value) || 0;
    const unitPrice = parseFloat(row.querySelector('.return-amount-cell')?.dataset?.unitPrice || 0);
    const discRate = parseFloat(row.querySelector('.item-disc-input')?.value) || 0;
    const addlDiscRate = window.getInvoiceAddlDiscRate(row);

    const grossReturn = qty * unitPrice;
    const afterItemDisc = grossReturn * (1 - discRate / 100);
    const afterAddlDisc = afterItemDisc * (1 - addlDiscRate / 100);

    const grossCell = row.querySelector('.item-gross-subtotal');
    if (grossCell) grossCell.innerText = '₱ ' + grossReturn.toLocaleString(undefined, {minimumFractionDigits: 2});

    const returnCell = row.querySelector('.return-amount-cell');
    if (returnCell) returnCell.innerText = '₱ ' + afterAddlDisc.toLocaleString(undefined, {minimumFractionDigits: 2});
};

window.updateReturnQty = function(input, max, unitPrice) {
    let val = parseInt(input.value) || 0;
    if (val > max) { val = max; input.value = max; }
    if (val < 0) { val = 0; input.value = 0; }
    
    const row = input.closest('tr');
    window.calcItemAmounts(row);
    
    // Update Good Items max value
    const goodInput = row.querySelector('.good-items-input');
    if (goodInput) {
        goodInput.max = val;
        const goodQty = parseInt(goodInput.value) || 0;
        if (goodQty > val) goodInput.value = val || '';
    }
};

window.recalcInvoiceAddlDisc = function(invId, rate) {
    const contentDiv = document.getElementById(`items-content-${invId}`);
    if (!contentDiv) return;
    const rows = contentDiv.querySelectorAll('tr');
    rows.forEach(row => {
        window.calcItemAmounts(row);
    });
};

window.updateGoodItemsQty = function(input) {
    const row = input.closest('tr');
    const returnQty = parseInt(row?.querySelector('.return-qty-input')?.value) || 0;
    let val = parseInt(input.value) || 0;
    if (val > returnQty) {
        val = returnQty;
        input.value = returnQty || '';
    }
    if (val < 0) input.value = '';
};

function populateReturnReview() {
    const tabContainer = document.getElementById('review-tabs-container');
    const contentContainer = document.getElementById('review-items-content');
    if (!tabContainer || !contentContainer) return;
    tabContainer.innerHTML = '';
    contentContainer.innerHTML = '';

    let totalItems = 0;
    let totalAmount = 0;

    window._selectedInvoices.forEach((inv, idx) => {
        const uiKey = invoiceUiKey(inv);
        const itemsContent = document.getElementById(`items-content-${uiKey}`);
        const inputs = itemsContent ? itemsContent.querySelectorAll('.return-qty-input') : [];
        const rows = Array.from(inputs).map(inp => {
            const row = inp.closest('tr');
            const cells = row.querySelectorAll('td');
            const productId = row.getAttribute('data-product-id') || '';
            const productCode = cells[0]?.innerText || '---';
            const partNumber = row.getAttribute('data-part-number') || '';
            const description = cells[1]?.innerText || '---';
            const application = row.getAttribute('data-application') || '';
            const qty = parseInt(inp.value) || 0;
            const goodItems = parseInt(row.querySelector('.good-items-input')?.value) || 0;
            const remarks = row.querySelector('.return-item-remarks')?.value || '';
            
            // Get the return amount that was already calculated in Step 2
            const returnAmountCell = row.querySelector('.return-amount-cell');
            const returnAmountText = returnAmountCell?.innerText || '₱ 0.00';
            const amount = parseFloat(returnAmountText.replace(/[₱,\s]/g, '')) || 0;
            
            return { productId, productCode, partNumber, description, application, qty, goodItems, amount, remarks };
        }).filter(r => r.qty > 0);

        totalItems += rows.reduce((s, r) => s + r.qty, 0);
        totalAmount += rows.reduce((s, r) => s + r.amount, 0);

        const genRemarks = document.getElementById(`general-remarks-${uiKey}`)?.value || '';

        // Tab button
        const tabBtn = document.createElement('button');
        tabBtn.className = `px-5 py-2.5 text-[10px] font-bold uppercase tracking-widest border-b-2 transition-all ${idx === 0 ? 'border-maroon text-maroon bg-maroon/5' : 'border-transparent text-slate-400 hover:text-slate-600'}`;
        tabBtn.innerText = inv.no;
        tabBtn.onclick = () => switchReviewTab(uiKey, tabBtn);
        tabContainer.appendChild(tabBtn);

        // Tab content
        const content = document.createElement('div');
        content.id = `review-content-${uiKey}`;
        content.className = idx === 0 ? '' : 'hidden';
        content.innerHTML = `
            <table class="w-full">
                <thead>
                    <tr class="text-[9px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                        <th class="p-4 text-left">Product Code</th>
                        <th class="p-4 text-left">Part Number</th>
                        <th class="p-4 text-left">Description</th>
                        <th class="p-4 text-left">Application</th>
                        <th class="p-4 text-center">Deducted QTY</th>
                        <th class="p-4 text-center">Good Items</th>
                        <th class="p-4 text-left">Remarks</th>
                        <th class="p-4 text-right">Deducted Amount</th>
                    </tr>
                </thead>
                <tbody class="text-xs divide-y divide-slate-50">
                    ${rows.length ? rows.map(r => `
                        <tr>
                            <td class="p-4 font-mono font-bold">${r.productCode}</td>
                            <td class="p-4 text-slate-500 font-mono">${r.partNumber}</td>
                            <td class="p-4 text-slate-600">${r.description}</td>
                            <td class="p-4 text-slate-500 italic">${r.application}</td>
                            <td class="p-4 text-center font-bold">${r.qty}</td>
                            <td class="p-4 text-center font-bold text-emerald-700">${r.goodItems || 0}</td>
                            <td class="p-4 text-slate-500 italic">${r.remarks || '---'}</td>
                            <td class="p-4 text-right font-bold text-maroon">₱ ${r.amount.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                        </tr>
                    `).join('') : `<tr><td colspan="8" class="p-8 text-center text-[10px] text-slate-400 italic">No items with quantity selected for this invoice.</td></tr>`}
                </tbody>
            </table>
            ${genRemarks ? `
            <div class="p-4 bg-slate-50 border-t border-slate-100">
                <p class="text-[9px] font-bold text-slate-400 uppercase mb-1">General Remarks</p>
                <p class="text-xs text-slate-600 italic">"${genRemarks}"</p>
            </div>
            ` : ''}
        `;
        contentContainer.appendChild(content);
    });

    document.getElementById('review-total-items').innerText = totalItems + ' Items';
    document.getElementById('review-total-amount').innerText = '₱ ' + totalAmount.toLocaleString('en-US', {minimumFractionDigits: 2});

    if (tabContainer.children.length === 0) {
        tabContainer.innerHTML = '<p class="text-[10px] text-slate-400 italic p-6">No invoices selected.</p>';
    }
}

window.switchReviewTab = function(invNo, tabBtn) {
    document.querySelectorAll('#review-tabs-container button').forEach(btn => {
        btn.classList.remove('border-maroon', 'text-maroon', 'bg-maroon/5');
        btn.classList.add('border-transparent', 'text-slate-400');
    });
    tabBtn.classList.add('border-maroon', 'text-maroon', 'bg-maroon/5');
    tabBtn.classList.remove('border-transparent', 'text-slate-400');

    document.querySelectorAll('#review-items-content > div').forEach(div => div.classList.add('hidden'));
    const target = document.getElementById(`review-content-${invNo}`);
    if (target) target.classList.remove('hidden');
};

window.finalizeReturn = async function() {
    const returns = [];
    
    window._selectedInvoices.forEach(inv => {
        const uiKey = invoiceUiKey(inv);
        const itemsContent = document.getElementById(`items-content-${uiKey}`);
        if (!itemsContent) return;

        const addlDiscInput = document.getElementById(`addl-disc-${uiKey}`);
        const additionalDiscount = parseFloat(addlDiscInput?.value) || 0;

        const inputs = itemsContent.querySelectorAll('.return-qty-input');
        const items = Array.from(inputs).map(inp => {
            const row = inp.closest('tr');
            if (!row) return null;

            const cells = row.querySelectorAll('td');
            const qty = parseInt(inp.value) || 0;
            const returnAmountCell = row.querySelector('.return-amount-cell');
            const unitPrice = parseFloat(returnAmountCell?.dataset?.unitPrice || 0);
            const goodItems = parseInt(row.querySelector('.good-items-input')?.value) || 0;
            const itemRemarks = row.querySelector('.return-item-remarks')?.value || '';
            const discRate = parseFloat(row.querySelector('.item-disc-input')?.value) || 0;
            const grossReturn = qty * unitPrice;
            const afterItemDisc = grossReturn * (1 - discRate / 100);
            const afterAddlDisc = afterItemDisc * (1 - additionalDiscount / 100);
            
            return {
                product_id: row.getAttribute('data-product-id'),
                product_code: cells[0]?.innerText?.trim() || '',
                part_number: row.getAttribute('data-part-number') || '',
                description: cells[1]?.innerText?.trim() || '',
                application: row.getAttribute('data-application') || '',
                qty: qty,
                good_items: Math.min(goodItems, qty),
                remarks: itemRemarks.trim(),
                unit_price: unitPrice,
                discount: discRate,
                amount: afterAddlDisc,
                gross_subtotal: grossReturn,
                additional_discount: additionalDiscount
            };
        }).filter(item => item !== null && item.qty > 0);

        if (items.length > 0) {
            const genRemarks = document.getElementById(`general-remarks-${uiKey}`)?.value || '';
            const totalGross = items.reduce((sum, item) => sum + item.gross_subtotal, 0);
            const totalAfterItemDisc = items.reduce((sum, item) => sum + (item.gross_subtotal * (1 - item.discount / 100)), 0);
            const totalAfterAddlDisc = items.reduce((sum, item) => sum + item.amount, 0);
            returns.push({
                invoice_no: inv.no,
                customer_id: inv.customer_id,
                customer_name: inv.customer_name,
                total_items: items.reduce((sum, item) => sum + item.qty, 0),
                total_amount: totalAfterAddlDisc,
                subtotal: totalAfterItemDisc,
                gross_subtotal: totalGross,
                additional_discount: additionalDiscount,
                remarks: genRemarks.trim(),
                items: items
            });
        }
    });

    if (returns.length === 0) {
        alert('Please enter return quantity for at least one item.');
        return;
    }

    console.log('Finalizing returns:', returns);

    const btn = document.getElementById('r-btn-finalize');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i><span>Processing...</span>';
    if (typeof lucide !== 'undefined') lucide.createIcons();

    try {
        const res = await fetch(window.salesReturnRoutes.finalize, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
            },
            body: JSON.stringify({ returns })
        });
        const r = await res.json();
        if (r.success) {
            toggleModal('add-return-modal', false);
            toggleModal('return-success-modal', true);
            if (window.loadSalesReturns) window.loadSalesReturns();
            if (window.loadSalesReturnDashboard) window.loadSalesReturnDashboard();
        } else {
            alert('Error: ' + (r.message || 'Unknown error occurred'));
        }
    } catch (e) {
        console.error('Finalize error:', e);
        alert('An unexpected error occurred while processing the return.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
};

// ===== EDIT RETURN FUNCTIONS =====
window._editReturnId = null;

window.editReturn = async function(id) {
    window._editReturnId = id;
    toggleModal('edit-return-modal', true);
    const tbody = document.getElementById('edit-return-items-tbody');
    tbody.innerHTML = '<tr><td colspan="7" class="p-8 text-center text-slate-300 italic">Loading items...</td></tr>';

    try {
        const url = window.salesReturnRoutes.editData.replace(':id', id);
        const res = await fetch(url);
        const r = await res.json();

        if (r.success) {
            const header = r.header;
            document.getElementById('edit-return-no').textContent = header.return_number || '---';
            document.getElementById('edit-invoice-no').textContent = header.invoice_no || '---';
            document.getElementById('edit-customer-name').textContent = header.customer_name || '---';
            document.getElementById('edit-general-remarks').value = header.remarks || '';

            renderEditItems(r.invoice_items || [], r.existing_items || []);
        } else {
            tbody.innerHTML = '<tr><td colspan="7" class="p-8 text-center text-red-400 italic">Failed to load return data.</td></tr>';
        }
    } catch (e) {
        console.error('Error loading edit data:', e);
        tbody.innerHTML = '<tr><td colspan="7" class="p-8 text-center text-red-400 italic">Error loading data.</td></tr>';
    }
};

function renderEditItems(invoiceItems, existingItems) {
    const tbody = document.getElementById('edit-return-items-tbody');
    if (!tbody) return;

    if (!invoiceItems || invoiceItems.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="p-8 text-center text-[10px] text-slate-400 italic">No items found for this invoice.</td></tr>';
        return;
    }

    const existingMap = {};
    existingItems.forEach(item => {
        const pid = item.product_id || item.product_code;
        existingMap[pid] = item;
    });

    tbody.innerHTML = invoiceItems.map(item => {
        const existing = existingMap[item.product_id] || existingMap[item.product_code];
        const isExisting = !!existing;
        const unitPrice = parseFloat(item.unit_price) || 0;
        const maxQty = parseInt(item.quantity) || 0;
        const existingQty = existing ? parseInt(existing.quantity || existing.qty || 0) : 0;
        const existingGoodQty = existing ? parseInt(existing.good_qty || existing.good_items || 0) : 0;
        const existingRemarks = existing ? (existing.remarks || '') : '';
        const originalDisc = parseFloat(item.discount) || 0;
        const existingDisc = existing ? parseFloat(existing.discount ?? originalDisc) || 0 : originalDisc;
        const initialAmount = existing
            ? parseFloat(existing.return_amount ?? (existingQty * unitPrice * (1 - existingDisc / 100))) || 0
            : 0;

        const blinkClass = isExisting ? ' blink-red' : '';
        const blinkRowBg = isExisting ? ' bg-red-50/30' : '';

        return `
            <tr class="hover:bg-slate-50 transition-colors${blinkRowBg}" data-product-id="${item.product_id}" data-product-code="${item.product_code || ''}">
                <td class="p-4 font-mono font-bold text-maroon${blinkClass}">${item.product_code || '---'}</td>
                <td class="p-4 text-slate-600${blinkClass}">${(item.description || '').replace(/\s*Converted from Partial\s*/gi, '')}</td>
                <td class="p-4 text-center${blinkClass}">
                    <input type="number" value="${existingQty || ''}" placeholder="0" min="0" max="${maxQty}" oninput="updateEditReturnQty(this, ${maxQty}, ${unitPrice})" class="w-16 px-2 py-1 border border-slate-200 rounded-lg text-center font-bold focus:ring-2 focus:ring-maroon/20 outline-none edit-return-qty-input">
                    <span class="text-[10px] text-slate-400">/ ${maxQty}</span>
                </td>
                <td class="p-4 text-center${blinkClass}">
                    <input type="number" value="${existingGoodQty || ''}" placeholder="0" min="0" max="${existingQty || 0}" oninput="updateEditGoodItemsQty(this)" class="w-20 px-2 py-1 border border-slate-200 rounded-lg text-center font-bold text-emerald-700 focus:ring-2 focus:ring-emerald-500/20 outline-none edit-good-items-input">
                </td>
                <td class="p-4 text-center${blinkClass}">
                    <input type="number" value="${existingDisc || ''}" placeholder="0" min="0" max="100" step="0.01" oninput="updateEditReturnQty(this.closest('tr').querySelector('.edit-return-qty-input'), ${maxQty}, ${unitPrice})" class="w-16 px-2 py-1 border border-slate-200 rounded-lg text-center font-bold focus:ring-2 focus:ring-maroon/20 outline-none edit-item-disc-input">
                </td>
                <td class="p-4${blinkClass}">
                    <input type="text" value="${existingRemarks}" placeholder="Add remarks..." class="w-full px-3 py-1.5 bg-slate-50 border border-slate-100 rounded-lg text-[10px] outline-none focus:ring-2 focus:ring-maroon/10 edit-return-item-remarks">
                </td>
                <td class="p-4 text-right font-bold text-maroon edit-return-amount-cell" data-unit-price="${unitPrice}">₱ ${initialAmount.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
            </tr>
        `;
    }).join('');

    if (typeof lucide !== 'undefined') lucide.createIcons();
}

window.updateEditReturnQty = function(input, max, unitPrice) {
    let val = parseInt(input.value) || 0;
    if (val > max) { val = max; input.value = max; }
    if (val < 0) { val = 0; input.value = 0; }

    const row = input.closest('tr');
    const discRate = parseFloat(row.querySelector('.edit-item-disc-input')?.value) || 0;
    const amountCell = row.querySelector('.edit-return-amount-cell');
    if (amountCell) {
        const amount = (val * unitPrice) * (1 - discRate / 100);
        amountCell.innerText = `₱ ${amount.toLocaleString(undefined, {minimumFractionDigits: 2})}`;
    }
    const goodInput = row.querySelector('.edit-good-items-input');
    if (goodInput) {
        goodInput.max = val;
        const goodQty = parseInt(goodInput.value) || 0;
        if (goodQty > val) goodInput.value = val || '';
    }
};

window.updateEditGoodItemsQty = function(input) {
    const row = input.closest('tr');
    const returnQty = parseInt(row?.querySelector('.edit-return-qty-input')?.value) || 0;
    let val = parseInt(input.value) || 0;
    if (val > returnQty) {
        val = returnQty;
        input.value = returnQty || '';
    }
    if (val < 0) input.value = '';
};

window.confirmEditReturn = function() {
    toggleModal('edit-return-confirm-modal', true);
};

window.submitEditReturn = async function() {
    const tbody = document.getElementById('edit-return-items-tbody');
    const rows = tbody.querySelectorAll('tr');
    const items = [];
    let hasAnyValue = false;

    rows.forEach(row => {
        const productId = row.getAttribute('data-product-id');
        if (!productId) return;

        const cells = row.querySelectorAll('td');
        const qtyInput = row.querySelector('.edit-return-qty-input');
        const goodInput = row.querySelector('.edit-good-items-input');
        const remarksInput = row.querySelector('.edit-return-item-remarks');
        const amountCell = row.querySelector('.edit-return-amount-cell');

        const qty = parseInt(qtyInput?.value) || 0;
        const goodItems = parseInt(goodInput?.value) || 0;
        const unitPrice = parseFloat(amountCell?.dataset?.unitPrice || 0);
        const discount = parseFloat(row.querySelector('.edit-item-disc-input')?.value) || 0;
        const grossSubtotal = qty * unitPrice;
        const amount = grossSubtotal * (1 - discount / 100);

        if (qty > 0) hasAnyValue = true;

        items.push({
            product_id: productId,
            product_code: cells[0]?.innerText?.trim() || '',
            description: cells[1]?.innerText?.trim() || '',
            qty: qty,
            good_items: Math.min(goodItems, qty),
            remarks: remarksInput?.value?.trim() || '',
            unit_price: unitPrice,
            discount: discount,
            amount: amount,
            gross_subtotal: grossSubtotal,
            original_subtotal: grossSubtotal
        });
    });

    const remarks = document.getElementById('edit-general-remarks')?.value || '';

    toggleModal('edit-return-confirm-modal', false);

    if (!hasAnyValue) {
        alert('Please enter return quantity for at least one item.');
        return;
    }

    const saveBtn = document.querySelector('#edit-return-modal .bg-maroon:last-child');
    const originalText = saveBtn ? saveBtn.innerHTML : '';
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i><span>Processing...</span>';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    try {
        const url = window.salesReturnRoutes.update.replace(':id', window._editReturnId);
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || window.csrfToken
            },
            body: JSON.stringify({ items, remarks })
        });
        const r = await res.json();
        if (r.success) {
            toggleModal('edit-return-modal', false);
            toggleModal('edit-return-success-modal', true);
            if (window.loadSalesReturns) window.loadSalesReturns();
            if (window.loadSalesReturnDashboard) window.loadSalesReturnDashboard();
        } else {
            alert('Error: ' + (r.message || 'Unknown error occurred'));
        }
    } catch (e) {
        console.error('Update error:', e);
        alert('An unexpected error occurred while updating the return.');
    } finally {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalText;
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    }
};

window.goToReturnStep = function(step) {
    if (step === 2 && window._selectedInvoices.length === 0) {
        alert('Please select at least one invoice first.');
        return;
    }
    
    if (step === 3) {
        populateReturnReview();
    }

    window.currentReturnStep = step;

    // Step indicators
    document.querySelectorAll('.step-indicator').forEach(el => {
        const s = parseInt(el.innerText);
        if (isNaN(s)) return;
        
        if (s < step) {
            el.className = 'w-8 h-8 rounded-full bg-maroon text-gold font-bold flex items-center justify-center text-sm shadow-lg step-indicator';
            el.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i>';
        } else if (s === step) {
            el.className = 'w-8 h-8 rounded-full bg-white text-maroon font-bold flex items-center justify-center text-sm shadow-lg border-2 border-maroon step-indicator';
            el.innerHTML = s;
        } else {
            el.className = 'w-8 h-8 rounded-full bg-white/20 text-white/60 font-bold flex items-center justify-center text-sm step-indicator';
            el.innerHTML = s;
        }
    });

    // Step content visibility
    document.getElementById('return-step-1').classList.toggle('hidden', step !== 1);
    document.getElementById('return-step-2').classList.toggle('hidden', step !== 2);
    document.getElementById('return-step-3').classList.toggle('hidden', step !== 3);

    // Footer buttons
    document.getElementById('r-btn-back').classList.toggle('hidden', step === 1);
    document.getElementById('r-btn-next').classList.toggle('hidden', step === 3);
    document.getElementById('r-btn-finalize').classList.toggle('hidden', step !== 3);

    if (typeof lucide !== 'undefined') lucide.createIcons();
};

window._deleteReturnId = null;

window.confirmDeleteReturn = function(id) {
    window._deleteReturnId = id;
    toggleModal('delete-return-confirm-modal', true);
};

window.submitDeleteReturn = async function() {
    const id = window._deleteReturnId;
    if (!id) return;

    const btn = document.getElementById('confirm-delete-return-btn');
    const originalText = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i><span>Deleting...</span>';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    try {
        const url = window.salesReturnRoutes.delete.replace(':id', id);
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || window.csrfToken
            }
        });
        const r = await res.json();
        if (r.success) {
            toggleModal('delete-return-confirm-modal', false);
            toggleModal('delete-return-success-modal', true);
            if (window.loadSalesReturns) window.loadSalesReturns();
            if (window.loadSalesReturnDashboard) window.loadSalesReturnDashboard();
        } else {
            alert('Error: ' + (r.message || 'Unknown error occurred'));
        }
    } catch (e) {
        console.error('Delete error:', e);
        alert('An unexpected error occurred while deleting the return.');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
        window._deleteReturnId = null;
    }
};
