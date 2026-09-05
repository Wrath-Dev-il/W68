document.addEventListener('DOMContentLoaded', () => {
    if (typeof lucide !== 'undefined') lucide.createIcons();
    loadWaybillDashboard();
    loadPendingWaybills();
    initForwarderSuggestions();
});

function initForwarderSuggestions() {
    const input = document.getElementById('wb-forwarder');
    const container = document.getElementById('forwarder-suggestions');
    if (!input || !container) return;

    input.addEventListener('input', () => {
        const query = input.value.trim();
        if (query.length < 1) {
            container.classList.add('hidden');
            return;
        }

        fetch(`${window.waybillRoutes.forwarderSuggestions}?query=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(r => {
                if (r.success && r.forwarders.length > 0) {
                    container.innerHTML = r.forwarders.map(f => `
                        <div class="suggestion-item p-3 hover:bg-maroon/5 cursor-pointer text-xs font-medium text-slate-700 transition-colors border-b border-slate-50 last:border-0" onclick="selectForwarder('${f.name.replace(/'/g, "\\'")}')">
                            ${f.name}
                        </div>
                    `).join('');
                    container.classList.remove('hidden');
                } else {
                    container.classList.add('hidden');
                }
            })
            .catch(() => container.classList.add('hidden'));
    });

    document.addEventListener('click', (e) => {
        if (!input.contains(e.target) && !container.contains(e.target)) {
            container.classList.add('hidden');
        }
    });
}

window.selectForwarder = function(name) {
    const input = document.getElementById('wb-forwarder');
    const container = document.getElementById('forwarder-suggestions');
    if (input) input.value = name;
    if (container) container.classList.add('hidden');
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
        const wbModalIds = ['wb-waybill-modal','wb-so-select-modal','wb-confirm-modal','wb-success-modal','wb-edit-modal','edit-so-select-modal','wb-edit-confirm-modal','wb-edit-success-modal','wb-view-items-modal','wb-history-view-modal','wb-invoice-products-modal','wb-delete-confirm-modal','wb-delete-success-modal'];
        const anyVisible = wbModalIds.some(id => {
            const el = document.getElementById(id);
            return el && !el.classList.contains('hidden');
        });
        if (!anyVisible) document.body.style.overflow = 'auto';
    }
};

let currentStep = 1;
window._pendingWaybills = [];
window._selectedOrders = [];

window.loadWaybillDashboard = async function() {
    // Wait for routes to be available
    if (!window.waybillRoutes || !window.waybillRoutes.stats) {
        setTimeout(loadWaybillDashboard, 100);
        return;
    }

    try {
        const res = await fetch(window.waybillRoutes.stats);
        const r = await res.json();
        if (r.success) {
            document.getElementById('wb-card-total').textContent = (r.total_no_waybill || 0).toLocaleString();
            document.getElementById('wb-card-new').textContent = (r.new_waybill || 0).toLocaleString();
            document.getElementById('wb-card-overdue').textContent = (r.overdue || 0).toLocaleString();
        }
    } catch (e) { console.error('Error loading dashboard stats:', e); }
};

// Pagination and Search State
let pendingState = { page: 1, customer: '', invoice: '' };
let historyState = { page: 1, waybill: '', customer: '', invoice: '', search: '' };
let pendingRequestSequence = 0;

window.loadPendingWaybills = async function() {
    const requestSeq = ++pendingRequestSequence;
    const tbody = document.getElementById('wb-main-tbody');
    if (!tbody) return;
    
    // Wait for routes to be available if they aren't yet
    if (!window.waybillRoutes || !window.waybillRoutes.pending) {
        console.warn('Waybill routes not yet defined. Retrying in 100ms...');
        setTimeout(loadPendingWaybills, 100);
        return;
    }

    tbody.innerHTML = '<tr><td colspan="3" class="p-6 text-center text-slate-300 italic">Loading orders...</td></tr>';
    try {
        const url = new URL(window.waybillRoutes.pending, window.location.origin);
        url.searchParams.append('page', pendingState.page);
        if (pendingState.customer) url.searchParams.append('customer', pendingState.customer);
        if (pendingState.invoice) url.searchParams.append('invoice', pendingState.invoice);
        if (pendingState.search) url.searchParams.set('search', pendingState.search);

        const res = await fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const contentType = res.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            const text = await res.text();
            throw new Error(`Pending Waybill API returned ${res.status} instead of JSON${text ? ': ' + text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 160) : ''}`);
        }
        const r = await res.json();
        if (!res.ok) throw new Error(r.message || r.error || `Pending Waybill API error: ${res.status}`);
        if (requestSeq !== pendingRequestSequence) return;
        
        if (r.success && r.orders && r.orders.length > 0) {
            window._pendingWaybills = r.orders;
            tbody.innerHTML = r.orders.map(o => {
                const customerName = (o.customer_name || '---').replace(/'/g, "\\'").replace(/"/g, "&quot;");
                const address = (o.address || '---').replace(/'/g, "\\'").replace(/"/g, "&quot;");
                const contact = (o.contact_number || '---').replace(/'/g, "\\'").replace(/"/g, "&quot;");
                const orderCount = o.order_count || 1;
                
                return `
                    <tr class="border-b border-slate-100 hover:bg-maroon/5 transition-all cursor-pointer">
                        <td class="p-4 px-6 text-slate-700 font-medium">${o.customer_name || '---'}</td>
                        <td class="p-4 px-6"><span class="px-3 py-1 bg-amber-50 text-amber-700 font-bold rounded-full text-[10px]">${orderCount} Invoice${orderCount > 1 ? 's' : ''}</span></td>
                        <td class="p-4 px-6 text-center">
                            <button onclick='openWaybillModal("${o.customer_id}", "${customerName}", ${JSON.stringify(o.order_ids).replace(/"/g, "&quot;")}, ${JSON.stringify(o.order_numbers).replace(/"/g, "&quot;")}, ${JSON.stringify(o.order_amounts).replace(/"/g, "&quot;")}, ${o.total_amount}, ${JSON.stringify(o.invoice_numbers).replace(/"/g, "&quot;")}, "${address}", "${contact}")' class="px-4 py-2 bg-maroon text-white text-[9px] font-bold rounded-xl hover:bg-maroon-800 transition-all shadow-lg flex items-center space-x-1 mx-auto">
                                <i data-lucide="truck" class="w-3 h-3 text-gold"></i>
                                <span>Make Waybill</span>
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');
            
            updatePaginationUI('pending', r.page, r.last_page, r.total);
            if (typeof lucide !== 'undefined') lucide.createIcons();
        } else {
            tbody.innerHTML = '<tr><td colspan="3" class="p-6 text-center text-slate-300 italic">No pending sales orders found.</td></tr>';
            updatePaginationUI('pending', 1, 1, 0);
        }
    } catch (e) {
        console.error('Error loading pending waybills:', e);
        tbody.innerHTML = `<tr><td colspan="3" class="p-6 text-center text-red-400 italic">Error loading data: ${e.message}</td></tr>`;
    }
};

window.searchPendingWaybills = function(col, val) {
    if (col === 0) pendingState.customer = val;
    if (col === 1) pendingState.invoice = val;
    pendingState.page = 1;
    clearTimeout(window._pendingSearchTimer);
    window._pendingSearchTimer = setTimeout(loadPendingWaybills, 500);
};

// Sales Order modal search, sorting, and draft selection state
let _soSearchTerm = '';
let _soSort = { key: 'date', direction: 'desc' };
window._soDraftSelections = new Map();

function escapeSOHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatSODate(value) {
    if (!value) return '---';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return String(value);

    return parsed.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: '2-digit'
    });
}

function manilaDateKey(value = new Date()) {
    const parsed = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(parsed.getTime())) return '';

    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    }).formatToParts(parsed);
    const values = Object.fromEntries(parts.map(part => [part.type, part.value]));
    return `${values.year}-${values.month}-${values.day}`;
}

function soRecordDateKey(value) {
    const raw = String(value ?? '').trim();
    const direct = raw.match(/^(\d{4}-\d{2}-\d{2})/);
    if (direct) return direct[1];
    return manilaDateKey(raw);
}

function getSOModalRecords() {
    const filteredGroups = window._currentCustomerName
        ? window._pendingWaybills.filter(group => group.customer_name === window._currentCustomerName)
        : window._pendingWaybills;

    const records = [];

    filteredGroups.forEach(group => {
        (group.order_ids || []).forEach((id, index) => {
            const orderNumber = group.order_numbers?.[index] || '';
            const invoiceNumber = group.invoice_numbers?.[index] || orderNumber || '---';
            const totalAmount = Number.parseFloat(group.order_amounts?.[index] ?? 0) || 0;
            const orderDate = group.order_dates?.[index] || '';

            const duplicateCount = Number.parseInt(group.invoice_duplicate_counts?.[index] ?? 0, 10) || 0;

            records.push({
                id: String(id),
                invoice_number: String(invoiceNumber),
                order_number: String(orderNumber),
                total_amount: totalAmount,
                order_date: orderDate,
                customer_id: group.customer_id,
                customer_name: group.customer_name || '---',
                address: group.address || '---',
                contact_number: group.contact_number || '---',
                duplicate_count: duplicateCount,
                is_duplicate: duplicateCount > 1
            });
        });
    });

    return records;
}

function compareSORecords(left, right) {
    const direction = _soSort.direction === 'asc' ? 1 : -1;
    let result = 0;

    switch (_soSort.key) {
        case 'customer':
            result = left.customer_name.localeCompare(right.customer_name, undefined, { numeric: true, sensitivity: 'base' });
            break;
        case 'amount':
            result = left.total_amount - right.total_amount;
            break;
        case 'date':
            result = (Date.parse(left.order_date) || 0) - (Date.parse(right.order_date) || 0);
            break;
        case 'invoice':
        default:
            result = left.invoice_number.localeCompare(right.invoice_number, undefined, { numeric: true, sensitivity: 'base' });
            break;
    }

    if (result === 0) {
        result = left.id.localeCompare(right.id, undefined, { numeric: true });
    }

    return result * direction;
}

function updateSOSortIndicators() {
    ['invoice', 'customer', 'amount', 'date'].forEach(key => {
        const indicator = document.getElementById(`wb-so-sort-${key}`);
        if (!indicator) return;

        const isActive = _soSort.key === key;
        indicator.textContent = isActive ? (_soSort.direction === 'asc' ? '↑' : '↓') : '↕';
        indicator.classList.toggle('text-maroon', isActive);
        indicator.classList.toggle('text-slate-300', !isActive);
    });
}

function selectionFromSOCheckbox(checkbox) {
    return {
        id: String(checkbox.value),
        invoice_number: checkbox.dataset.invoice || '---',
        order_number: checkbox.dataset.order || checkbox.dataset.invoice || '---',
        total_amount: Number.parseFloat(checkbox.dataset.amount || 0) || 0,
        order_date: checkbox.dataset.date || '',
        customer_name: checkbox.dataset.customer || '---',
        address: checkbox.dataset.address || '---',
        contact_number: checkbox.dataset.contact || '---'
    };
}

function updateSODraftSelection(checkbox) {
    const key = String(checkbox.value);
    const row = checkbox.closest('tr');

    if (checkbox.checked) {
        window._soDraftSelections.set(key, selectionFromSOCheckbox(checkbox));
        row?.classList.add('bg-amber-50');
    } else {
        window._soDraftSelections.delete(key);
        row?.classList.remove('bg-amber-50');
    }
}

function updateSOSelectionSummary() {
    const selected = Array.from(window._soDraftSelections.values());
    const countElement = document.getElementById('wb-so-selected-count');
    const totalElement = document.getElementById('wb-so-selected-total');
    const total = selected.reduce((sum, order) => sum + (Number.parseFloat(order.total_amount) || 0), 0);

    if (countElement) countElement.textContent = selected.length.toLocaleString();
    if (totalElement) {
        totalElement.textContent = '₱ ' + total.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
}

function syncSOSelectAllState() {
    const selectAll = document.getElementById('wb-so-select-all');
    if (!selectAll) return;

    const visibleCheckboxes = Array.from(document.querySelectorAll('#wb-so-tbody .so-checkbox'));
    const checkedCount = visibleCheckboxes.filter(checkbox => checkbox.checked).length;

    selectAll.disabled = visibleCheckboxes.length === 0;
    selectAll.checked = visibleCheckboxes.length > 0 && checkedCount === visibleCheckboxes.length;
    selectAll.indeterminate = checkedCount > 0 && checkedCount < visibleCheckboxes.length;
}

window.searchSOModal = function() {
    const input = document.getElementById('wb-so-search');
    const clearButton = document.getElementById('wb-so-search-clear');
    if (!input) return;

    _soSearchTerm = input.value.trim().toLowerCase();
    clearButton?.classList.toggle('hidden', !_soSearchTerm);

    clearTimeout(window._soSearchTimer);
    window._soSearchTimer = setTimeout(refreshSOModalContent, 120);
};

window.clearSOSearch = function() {
    const input = document.getElementById('wb-so-search');
    const clearButton = document.getElementById('wb-so-search-clear');

    if (input) input.value = '';
    clearButton?.classList.add('hidden');
    _soSearchTerm = '';
    refreshSOModalContent();
    input?.focus();
};

window.sortSOModal = function(key) {
    if (!['invoice', 'customer', 'amount', 'date'].includes(key)) return;

    if (_soSort.key === key) {
        _soSort.direction = _soSort.direction === 'asc' ? 'desc' : 'asc';
    } else {
        _soSort.key = key;
        _soSort.direction = ['amount', 'date'].includes(key) ? 'desc' : 'asc';
    }

    refreshSOModalContent();
};

window.toggleAllSO = function(masterCheckbox) {
    document.querySelectorAll('#wb-so-tbody .so-checkbox').forEach(checkbox => {
        checkbox.checked = masterCheckbox.checked;
        updateSODraftSelection(checkbox);
    });

    updateSOSelectionSummary();
    syncSOSelectAllState();
};

window.selectInvoicesByDate = function() {
    const dateInput = document.getElementById('wb-so-specific-date');
    const selectedDate = dateInput?.value || manilaDateKey();

    if (dateInput && !dateInput.value) {
        dateInput.value = selectedDate;
    }

    const matchingRecords = getSOModalRecords().filter(
        record => soRecordDateKey(record.order_date) === selectedDate
    );

    if (matchingRecords.length === 0) {
        window._soDraftSelections = new Map();
        updateSOSelectionSummary();
        refreshSOModalContent();

        const formattedDate = new Intl.DateTimeFormat('en-PH', {
            year: 'numeric',
            month: 'short',
            day: '2-digit',
            timeZone: 'Asia/Manila'
        }).format(new Date(`${selectedDate}T00:00:00+08:00`));

        alert(`No invoices dated ${formattedDate} were found for this customer.`);
        return;
    }

    // Replace the draft selection with invoices from the chosen specific date.
    window._soDraftSelections = new Map(
        matchingRecords.map(record => [record.id, { ...record }])
    );

    const input = document.getElementById('wb-so-search');
    const clearButton = document.getElementById('wb-so-search-clear');
    _soSearchTerm = '';
    if (input) input.value = '';
    clearButton?.classList.add('hidden');

    refreshSOModalContent();
};

function refreshSOModalContent() {
    const tbody = document.getElementById('wb-so-tbody');
    const noResults = document.getElementById('wb-so-no-results');
    if (!tbody) return;

    const records = getSOModalRecords()
        .filter(record => {
            if (!_soSearchTerm) return true;

            const displayedDate = formatSODate(record.order_date);
            const searchableText = [
                record.invoice_number,
                record.order_number,
                record.customer_name,
                record.order_date,
                displayedDate,
                record.total_amount,
                record.total_amount.toFixed(2)
            ].join(' ').toLowerCase();

            return searchableText.includes(_soSearchTerm);
        })
        .sort(compareSORecords);

    if (records.length === 0) {
        tbody.innerHTML = '';
        noResults?.classList.remove('hidden');
    } else {
        tbody.innerHTML = records.map(record => {
            const isSelected = window._soDraftSelections.has(record.id);
            const invoice = escapeSOHtml(record.invoice_number);
            const order = escapeSOHtml(record.order_number);
            const customer = escapeSOHtml(record.customer_name);
            const address = escapeSOHtml(record.address);
            const contact = escapeSOHtml(record.contact_number);
            const date = escapeSOHtml(formatSODate(record.order_date));
            const rawDate = escapeSOHtml(record.order_date);
            const amount = record.total_amount.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            const duplicateBadge = record.is_duplicate
                ? `<span class="ml-2 inline-flex items-center rounded-md px-2 py-0.5 text-[8px] font-black uppercase tracking-widest text-white" style="background-color:#7f1d1d;">Duplicate ×${record.duplicate_count}</span>`
                : '';
            const duplicateStyle = record.is_duplicate ? 'style="background-color:#4b0b12;"' : '';
            const duplicateText = record.is_duplicate ? 'text-white' : 'text-slate-700';
            const duplicateInvoiceText = record.is_duplicate ? 'text-amber-200' : 'text-maroon';

            return `
                <tr data-duplicate="${record.is_duplicate ? '1' : '0'}" ${duplicateStyle} class="transition-colors ${record.is_duplicate ? 'text-white' : 'hover:bg-slate-50'} ${isSelected && !record.is_duplicate ? 'bg-amber-50' : ''}">
                    <td class="p-3 text-center">
                        <input
                            type="checkbox"
                            value="${escapeSOHtml(record.id)}"
                            data-invoice="${invoice}"
                            data-order="${order}"
                            data-amount="${record.total_amount}"
                            data-date="${rawDate}"
                            data-customer="${customer}"
                            data-address="${address}"
                            data-contact="${contact}"
                            class="accent-maroon cursor-pointer so-checkbox"
                            ${isSelected ? 'checked' : ''}
                            onchange="selectSOrder(this)"
                        >
                    </td>
                    <td class="p-3 font-mono ${duplicateInvoiceText} font-bold">${invoice}${duplicateBadge}</td>
                    <td class="p-3 ${duplicateText}">${customer}</td>
                    <td class="p-3 text-right font-bold ${record.is_duplicate ? 'text-amber-200' : 'text-maroon'}">₱ ${amount}</td>
                    <td class="p-3 ${record.is_duplicate ? 'text-white' : 'text-slate-500'} whitespace-nowrap">${date}</td>
                    <td class="p-3 text-center">
                        <button
                            type="button"
                            data-id="${escapeSOHtml(record.id)}"
                            data-invoice="${invoice}"
                            onclick="openViewItemsModal(this.dataset.id, this.dataset.invoice)"
                            class="px-3 py-1 ${record.is_duplicate ? 'bg-white/10 text-white hover:bg-white/20' : 'bg-sky-50 text-sky-600 hover:bg-sky-100'} text-[9px] font-bold rounded-lg transition-all flex items-center space-x-1 mx-auto"
                        >
                            <i data-lucide="eye" class="w-3 h-3"></i>
                            <span>View</span>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
        noResults?.classList.add('hidden');
    }

    updateSOSortIndicators();
    updateSOSelectionSummary();
    syncSOSelectAllState();
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

window.changePendingPage = function(dir) {
    pendingState.page += dir;
    loadPendingWaybills();
};

window.goToStep = function(step) {
    document.querySelectorAll('.wb-step-content').forEach(el => el.classList.add('hidden'));
    const target = document.getElementById('wb-step-' + step);
    if (target) target.classList.remove('hidden');

    [1, 2, 3].forEach(s => {
        const ind = document.getElementById('wbstep-indicator-' + s);
        const lbl = document.getElementById('wbstep-label-' + s);
        if (!ind) return;
        ind.classList.remove('active', 'completed', 'pending');
        lbl?.classList.remove('text-maroon', 'text-slate-400');
        if (s < step) { ind.classList.add('completed'); ind.innerHTML = '<i data-lucide="check" class="w-4 h-4 text-white"></i>'; }
        else if (s === step) { ind.classList.add('active'); ind.innerText = s; lbl?.classList.add('text-maroon'); lbl?.classList.remove('text-slate-400'); }
        else { ind.classList.add('pending'); ind.innerText = s; }
    });
    document.getElementById('wbstep-progress-1').style.width = step > 1 ? '100%' : '0%';
    document.getElementById('wbstep-progress-2').style.width = step > 2 ? '100%' : '0%';

    const btnBack = document.getElementById('wb-btn-back');
    const btnNext = document.getElementById('wb-btn-next');
    const nextText = document.getElementById('wb-next-text');
    const nextIcon = document.querySelector('#wb-btn-next i');

    if (step === 1) {
        btnBack.classList.add('hidden');
        btnNext.onclick = () => goToStep(2);
        nextText.innerText = 'Next Step';
        if (nextIcon) nextIcon.setAttribute('data-lucide', 'chevron-right');
    } else if (step === 2) {
        btnBack.classList.remove('hidden');
        btnNext.onclick = () => goToStep(3);
        nextText.innerText = 'Next Step';
        if (nextIcon) nextIcon.setAttribute('data-lucide', 'chevron-right');
    } else {
        btnBack.classList.remove('hidden');
        btnNext.onclick = () => toggleModal('wb-confirm-modal', true);
        nextText.innerText = 'Finalize';
        if (nextIcon) nextIcon.setAttribute('data-lucide', 'check-circle');
    }
    currentStep = step;
    if (typeof lucide !== 'undefined') lucide.createIcons();
};

window.openWaybillModal = function(customerId, customer, orderIds, orderNumbers, orderAmounts, totalAmount, invoiceNumbers, address, contactNumber) {
    window._currentOrderId = customerId;
    window._currentCustomerName = customer;
    document.getElementById('wb-display-no').textContent = String(Date.now()).slice(-8);
    document.getElementById('wb-display-date').textContent = new Date().toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });
    document.getElementById('wb-display-customer').textContent = customer || '---';
    document.getElementById('wb-display-so').textContent = Array.isArray(invoiceNumbers) ? invoiceNumbers.filter(Boolean).join(', ') : (invoiceNumbers || '---');
    
    if (Array.isArray(orderIds) && orderIds.length > 0) {
        window._selectedOrders = orderIds.map((id, index) => ({
            id: id,
            order_number: Array.isArray(orderNumbers) ? orderNumbers[index] : orderNumbers,
            invoice_number: Array.isArray(invoiceNumbers) ? invoiceNumbers[index] : invoiceNumbers,
            customer_id: customerId,
            customer_name: customer,
            total_amount: Array.isArray(orderAmounts) ? (parseFloat(orderAmounts[index]) || 0) : (parseFloat(orderAmounts) || 0),
            address: address || '---',
            contact_number: contactNumber || '---'
        }));
        window._invoiceNumbers = Array.isArray(invoiceNumbers) ? invoiceNumbers : [invoiceNumbers];
        renderSelectedOrders();
    } else {
        window._selectedOrders = [];
        window._invoiceNumbers = [];
        document.getElementById('wb-selected-so-tbody').innerHTML = '<tr><td colspan="4" class="p-4 text-center text-slate-300 italic">No orders selected.</td></tr>';
        document.getElementById('wb-total-value').textContent = '₱ 0.00';
    }
    
    document.getElementById('wb-pct-declare').value = 100;
    document.getElementById('wb-declared-value').value = '0.00';
    document.getElementById('wb-cargo-tbody').innerHTML = `
        <tr>
            <td class="p-2 px-3 text-center"><input type="number" value="1" min="1" class="wb-cargo-qty w-full px-2 py-1.5 border border-slate-200 rounded-lg text-center font-bold outline-none focus:ring-2 focus:ring-maroon/20" style="min-width: 7.25rem;"></td>
            <td class="p-2 px-3 text-center"><input type="text" value="PCS" class="wb-cargo-unit w-full px-2 py-1.5 border border-slate-200 rounded-lg text-center uppercase outline-none focus:ring-2 focus:ring-maroon/20"></td>
            <td class="p-2 px-3"><input type="text" placeholder="Enter cargo description..." class="wb-cargo-desc w-full px-2 py-1.5 border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-maroon/20"></td>
            <td class="p-2 px-3 text-center"><button onclick="this.closest('tr').remove()" class="p-1.5 bg-red-50 text-red-400 hover:bg-red-100 hover:text-red-600 rounded-lg transition-all"><i data-lucide="x" class="w-3.5 h-3.5"></i></button></td>
        </tr>`;
    toggleModal('wb-waybill-modal', true);
    goToStep(1);
};

window.openSOModal = function() {
    const input = document.getElementById('wb-so-search');
    const clearButton = document.getElementById('wb-so-search-clear');
    const dateInput = document.getElementById('wb-so-specific-date');

    if (dateInput) {
        dateInput.value = manilaDateKey();
    }

    _soSearchTerm = '';
    _soSort = { key: 'date', direction: 'desc' };
    if (input) input.value = '';
    clearButton?.classList.add('hidden');

    window._soDraftSelections = new Map(
        (window._selectedOrders || []).map(order => [String(order.id), { ...order, id: String(order.id) }])
    );

    refreshSOModalContent();
    toggleModal('wb-so-select-modal', true);
    setTimeout(() => input?.focus(), 50);
    if (typeof lucide !== 'undefined') lucide.createIcons();
};

window.selectSOrder = function(checkbox) {
    updateSODraftSelection(checkbox);
    updateSOSelectionSummary();
    syncSOSelectAllState();
};

window.confirmSelectedSO = function() {
    window._selectedOrders = Array.from(window._soDraftSelections.values());
    renderSelectedOrders();
    toggleModal('wb-so-select-modal', false);
};

function renderSelectedOrders() {
    const tbody = document.getElementById('wb-selected-so-tbody');
    if (window._selectedOrders.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="p-4 text-center text-slate-300 italic">No orders selected.</td></tr>';
        document.getElementById('wb-total-value').textContent = '₱ 0.00';
        return;
    }
    let total = 0;
    tbody.innerHTML = window._selectedOrders.map(o => {
        total += parseFloat(o.total_amount);
        return `
            <tr class="border-b border-slate-100">
                <td class="p-3 text-xs text-slate-700 font-bold">${o.invoice_number || o.order_number}</td>
                <td class="p-3 text-xs text-slate-700">${o.customer_name}</td>
                <td class="p-3 text-xs text-right text-maroon font-bold">₱ ${parseFloat(o.total_amount).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                <td class="p-3 text-xs text-center"><button type="button" data-id="${escapeSOHtml(o.id)}" data-invoice="${escapeSOHtml(o.invoice_number || o.order_number)}" onclick="openViewItemsModal(this.dataset.id, this.dataset.invoice)" class="px-3 py-1 bg-sky-50 text-sky-600 hover:bg-sky-100 text-[9px] font-bold rounded-lg transition-all"><i data-lucide="eye" class="w-3 h-3 inline"></i> View</button></td>
            </tr>
        `;
    }).join('');
    document.getElementById('wb-total-value').textContent = '₱ ' + total.toLocaleString('en-US', {minimumFractionDigits: 2});
    calcDeclared();
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

window.openViewItemsModal = async function(orderId, orderNumber) {
    document.getElementById('wb-view-items-title').textContent = 'View Items - ' + orderNumber;
    const tbody = document.getElementById('wb-view-items-tbody');
    tbody.innerHTML = '<tr><td colspan="8" class="p-4 text-center text-slate-300 italic">Loading items...</td></tr>';
    toggleModal('wb-view-items-modal', true);
    
    try {
        const res = await fetch(window.waybillRoutes.soItems.replace(':id', encodeURIComponent(orderId)), {
            headers: { 'Accept': 'application/json' }
        });
        const r = await res.json();
        if (!res.ok || !r.success) {
            throw new Error(r.message || `Unable to load items (${res.status}).`);
        }
        if (Array.isArray(r.items) && r.items.length > 0) {
            tbody.innerHTML = r.items.map(item => `
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="p-3 text-slate-700 font-mono font-bold">${item.product_code}</td>
                    <td class="p-3 text-slate-700">${item.part_number || '---'}</td>
                    <td class="p-3 text-slate-700">${item.description}</td>
                    <td class="p-3 text-slate-700">${item.application || '---'}</td>
                    <td class="p-3 text-slate-700">${item.oum}</td>
                    <td class="p-3 text-right text-slate-700">₱ ${parseFloat(item.unit_price).toFixed(2)}</td>
                    <td class="p-3 text-right text-slate-700 font-bold">${item.quantity}</td>
                    <td class="p-3 text-right font-bold text-maroon">₱ ${parseFloat(item.subtotal).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="8" class="p-4 text-center text-slate-300 italic">No items found.</td></tr>';
        }
    } catch (e) {
        console.error('Error loading SO items:', e);
        tbody.innerHTML = '<tr><td colspan="8" class="p-4 text-center text-red-400 italic">Error loading items.</td></tr>';
    }
    if (typeof lucide !== 'undefined') lucide.createIcons();
};

window.calcDeclared = function() {
    const pct = parseFloat(document.getElementById('wb-pct-declare')?.value) || 0;
    const totalText = document.getElementById('wb-total-value')?.textContent?.replace(/[₱,]/g, '') || '0';
    const total = parseFloat(totalText) || 0;
    const declared = total * (pct / 100);
    document.getElementById('wb-declared-value').value = declared.toFixed(2);
};

window.addCargoRow = function() {
    const tbody = document.getElementById('wb-cargo-tbody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td class="p-2 px-3 text-center"><input type="number" value="1" min="1" class="wb-cargo-qty w-full px-2 py-1.5 border border-slate-200 rounded-lg text-center font-bold outline-none focus:ring-2 focus:ring-maroon/20" style="min-width: 7.25rem;"></td>
        <td class="p-2 px-3 text-center"><input type="text" value="PCS" class="wb-cargo-unit w-full px-2 py-1.5 border border-slate-200 rounded-lg text-center uppercase outline-none focus:ring-2 focus:ring-maroon/20"></td>
        <td class="p-2 px-3"><input type="text" placeholder="Enter cargo description..." class="wb-cargo-desc w-full px-2 py-1.5 border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-maroon/20"></td>
        <td class="p-2 px-3 text-center"><button onclick="this.closest('tr').remove()" class="p-1.5 bg-red-50 text-red-400 hover:bg-red-100 hover:text-red-600 rounded-lg transition-all"><i data-lucide="x" class="w-3.5 h-3.5"></i></button></td>`;
    tbody.appendChild(tr);
    if (typeof lucide !== 'undefined') lucide.createIcons();
};

window.finalizeWaybill = async function() {
    const waybillSequence = document.getElementById('wb-display-no').textContent;
    const waybillNoInput = document.getElementById('wb-waybill-no').value;
    const forwarder = document.getElementById('wb-forwarder').value;
    const orderIds = window._selectedOrders.map(o => o.id);
    const totalValue = parseFloat(document.getElementById('wb-total-value').textContent.replace(/[₱,]/g, '')) || 0;
    const declaredValue = parseFloat(document.getElementById('wb-declared-value').value) || 0;

    const cargoItems = [];
    document.querySelectorAll('#wb-cargo-tbody tr').forEach(tr => {
        const qty = parseInt(tr.querySelector('.wb-cargo-qty').value) || 0;
        const unit = tr.querySelector('.wb-cargo-unit').value || '';
        const desc = tr.querySelector('.wb-cargo-desc').value || '';
        if (qty > 0 && desc) {
            cargoItems.push({ qty, unit, desc });
        }
    });

    if (orderIds.length === 0) { alert('No orders selected.'); return; }
    if (!forwarder) { alert('Please enter forwarder information.'); goToStep(1); return; }

    try {
        const res = await fetch(window.waybillRoutes.finalize, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.csrfToken },
            body: JSON.stringify({ 
                order_ids: orderIds, 
                waybill_no: waybillNoInput,
                forwarder: forwarder,
                total_value: totalValue,
                declared_value: declaredValue,
                cargo_items: cargoItems
            })
        });
        const r = await res.json();
        if (r.success) {
            toggleModal('wb-confirm-modal', false);
            toggleModal('wb-waybill-modal', false);
            
            // Print the finalized/saved waybill through the exact same data + layout
            // used by the History "Print Selected" button. This guarantees that
            // customer address/contact come from the saved Masterlist customer record.
            if (r.waybill_id) {
                try {
                    await printWaybillIds([r.waybill_id]);
                } catch (printError) {
                    console.error('Finalized waybill print failed:', printError);
                    alert('Waybill was finalized, but the print data could not be loaded. You can print it from Waybill History.');
                }
            }

            const msgEl = document.querySelector('#wb-success-modal p');
            if (msgEl && r.waybill_sequence) msgEl.textContent = 'Waybill ' + r.waybill_sequence + ' has been created successfully.';

            // Keep the success confirmation after the print flow.
            setTimeout(() => {
                toggleModal('wb-success-modal', true);
            }, 500);

            loadWaybillDashboard();
            loadPendingWaybills();
        } else {
            alert('Error: ' + (r.message || 'Failed to finalize waybill.'));
        }
    } catch (e) {
        console.error('Error finalizing waybill:', e);
        alert('An error occurred. Check console.');
    }
};

window.switchMainTab = function(tab) {
    document.querySelectorAll('.main-tab-view').forEach(v => v.classList.add('hidden'));
    document.getElementById('view-' + tab).classList.remove('hidden');
    
    // UI Toggle
    const btnPending = document.getElementById('tab-btn-pending');
    const btnHistory = document.getElementById('tab-btn-history');
    const actions = document.getElementById('history-actions');
    const activeClass = 'px-6 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all duration-200 bg-maroon text-white shadow-md';
    const inactiveClass = 'px-6 py-2.5 rounded-xl text-xs font-bold uppercase tracking-widest transition-all duration-200 text-slate-500 hover:bg-slate-50';

    if (tab === 'pending') {
        btnPending.className = activeClass;
        btnHistory.className = inactiveClass;
        actions.classList.add('hidden');
        loadPendingWaybills();
    } else {
        btnHistory.className = activeClass;
        btnPending.className = inactiveClass;
        actions.classList.remove('hidden');
        loadWaybillHistory();
    }
    if (typeof lucide !== 'undefined') lucide.createIcons();
};

window.loadWaybillHistory = async function() {
    const tbody = document.getElementById('wb-history-tbody');
    tbody.innerHTML = '<tr><td colspan="7" class="p-8 text-center text-slate-300 italic">Loading history...</td></tr>';
    
    try {
        const url = new URL(window.waybillRoutes.history, window.location.origin);
        url.searchParams.append('page', historyState.page);
        if (historyState.search) {
            url.searchParams.append('search', historyState.search);
        } else {
            if (historyState.waybill) url.searchParams.append('waybill', historyState.waybill);
            if (historyState.customer) url.searchParams.append('customer', historyState.customer);
            if (historyState.invoice) url.searchParams.append('invoice', historyState.invoice);
        }

        const res = await fetch(url);
        const r = await res.json();
        if (r.success && r.history.length > 0) {
            tbody.innerHTML = r.history.map(wb => `
                <tr class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                    <td class="p-4 px-6"><input type="checkbox" value="${wb.id}" class="wb-history-checkbox accent-maroon cursor-pointer"></td>
                    <td class="p-4 px-6 font-mono font-bold text-maroon text-xs">${wb.waybill_sequence}</td>
                    <td class="p-4 px-6 text-slate-500 font-mono text-xs">${wb.display_waybill_no || wb.waybill_no || wb.waybill_sequence || 'N/A'}</td>
                    <td class="p-4 px-6 text-slate-700 font-medium">${wb.customer_name}</td>
                    <td class="p-4 px-6">
                        <span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded text-[9px] font-bold">${wb.invoice_count} Invoice${wb.invoice_count > 1 ? 's' : ''}</span>
                        <p class="text-[9px] text-slate-400 mt-0.5 truncate max-w-[150px]">${wb.invoice_numbers}</p>
                    </td>
                    <td class="p-4 px-6 text-slate-500 text-xs">${new Date(wb.waybill_date).toLocaleDateString()}</td>
                    <td class="p-4 px-6 text-center">
                        <div class="flex items-center justify-center space-x-2">
                            <button onclick="openHistoryViewModal('${wb.id}')" class="px-3 py-1.5 bg-sky-50 text-sky-600 hover:bg-sky-100 text-[9px] font-bold rounded-lg transition-all flex items-center space-x-1 shadow-sm">
                                <i data-lucide="eye" class="w-3 h-3"></i>
                                <span>View</span>
                            </button>
                            <button onclick="openEditWaybillModal('${wb.id}')" class="px-3 py-1.5 bg-amber-50 text-amber-600 hover:bg-amber-100 text-[9px] font-bold rounded-lg transition-all flex items-center space-x-1 shadow-sm">
                                <i data-lucide="edit-3" class="w-3 h-3"></i>
                                <span>Edit</span>
                            </button>
                            <button onclick="openDeleteWaybillModal('${wb.id}')" class="px-3 py-1.5 bg-red-50 text-red-600 hover:bg-red-100 text-[9px] font-bold rounded-lg transition-all flex items-center space-x-1 shadow-sm">
                                <i data-lucide="trash-2" class="w-3 h-3"></i>
                                <span>Delete</span>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
            
            updatePaginationUI('history', r.page, r.last_page, r.total);
            if (typeof lucide !== 'undefined') lucide.createIcons();
        } else {
            tbody.innerHTML = '<tr><td colspan="7" class="p-8 text-center text-slate-300 italic">No waybill history found.</td></tr>';
            updatePaginationUI('history', 1, 1, 0);
        }
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="7" class="p-8 text-center text-red-400">Error loading history.</td></tr>';
    }
};

// ============== DELETE WAYBILL FLOW ==============
window._deleteWaybillId = null;

window.openDeleteWaybillModal = async function(id) {
    window._deleteWaybillId = id;
    try {
        const res = await fetch(window.waybillRoutes.editData.replace(':id', id));
        const data = await res.json();
        if (!data.success) throw new Error(data.message);

        const wb = data.waybill;
        const firstInvoice = data.attached_invoices && data.attached_invoices.length > 0
            ? data.attached_invoices[0].invoice_no || '---'
            : '---';
        const customerName = data.customer ? data.customer.name : '---';

        document.getElementById('wb-delete-wb-sequence').textContent = wb.waybill_sequence || '---';
        document.getElementById('wb-delete-wb-number').textContent = wb.display_waybill_no || wb.waybill_no || wb.waybill_sequence || 'N/A';
        document.getElementById('wb-delete-invoice').textContent = firstInvoice;
        document.getElementById('wb-delete-customer').textContent = customerName;

        toggleModal('wb-delete-confirm-modal', true);
    } catch (e) {
        alert('Failed to load waybill details: ' + e.message);
    }
};

window.confirmDeleteWaybill = async function() {
    const id = window._deleteWaybillId;
    if (!id) return;

    const btn = document.getElementById('wb-confirm-delete-btn');
    const btnText = document.getElementById('wb-delete-btn-text');
    const spinner = document.getElementById('wb-delete-spinner');
    if (btn) btn.disabled = true;
    if (btnText) btnText.classList.add('hidden');
    if (spinner) spinner.classList.remove('hidden');

    try {
        const url = window.waybillRoutes.destroy.replace(':id', id);
        const res = await fetch(url, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': window.csrfToken,
                'Accept': 'application/json'
            }
        });
        const data = await res.json();
        if (data.success) {
            toggleModal('wb-delete-confirm-modal', false);
            toggleModal('wb-delete-success-modal', true);
        } else {
            alert(data.message || 'Failed to delete waybill.');
        }
    } catch (e) {
        alert('Error deleting waybill: ' + e.message);
    } finally {
        if (btn) btn.disabled = false;
        if (btnText) btnText.classList.remove('hidden');
        if (spinner) spinner.classList.add('hidden');
    }
};

window.closeDeleteSuccess = function() {
    toggleModal('wb-delete-success-modal', false);
    location.reload();
};

window.searchWaybillHistory = function(col, val) {
    if (col === 1) historyState.waybill = val;
    if (col === 2) historyState.waybill = val;
    if (col === 3) historyState.customer = val;
    if (col === 4) historyState.invoice = val;
    historyState.page = 1;
    clearTimeout(window._historySearchTimer);
    window._historySearchTimer = setTimeout(loadWaybillHistory, 500);
};

window.searchWaybillHistoryMain = function(val) {
    const input = document.getElementById('wb-history-search');
    const clearBtn = document.getElementById('wb-history-search-clear');
    if (!input) return;
    historyState.search = input.value.trim();
    if (clearBtn) clearBtn.classList.toggle('hidden', !historyState.search);
    historyState.page = 1;
    clearTimeout(window._historySearchTimer);
    window._historySearchTimer = setTimeout(loadWaybillHistory, 300);
};

window.clearHistorySearch = function() {
    const input = document.getElementById('wb-history-search');
    const clearBtn = document.getElementById('wb-history-search-clear');
    if (input) input.value = '';
    if (clearBtn) clearBtn.classList.add('hidden');
    historyState.search = '';
    historyState.page = 1;
    loadWaybillHistory();
};

window.changeHistoryPage = function(dir) {
    historyState.page += dir;
    loadWaybillHistory();
};

function updatePaginationUI(type, page, lastPage, total) {
    const info = document.getElementById(`${type}-page-info`);
    const prevBtn = document.getElementById(`${type}-prev-btn`);
    const nextBtn = document.getElementById(`${type}-next-btn`);
    if (!info || !prevBtn || !nextBtn) return;
    
    const label = type === 'pending' ? 'Invoices' : 'Waybills';
    info.textContent = `Showing Page ${page} of ${lastPage} (${total} Total ${label})`;
    
    prevBtn.disabled = page <= 1;
    nextBtn.disabled = page >= lastPage;
}

window.toggleSelectAllWaybills = function(el) {
    document.querySelectorAll('.wb-history-checkbox').forEach(cb => cb.checked = el.checked);
};

window.openHistoryViewModal = async function(id) {
    try {
        const res = await fetch(window.waybillRoutes.historyDetails.replace(':id', id));
        const r = await res.json();
        if (r.success) {
            // Customer Info
            document.getElementById('view-wb-customer-name').textContent = r.customer ? r.customer.name : '---';
            document.getElementById('view-wb-customer-address').textContent = r.customer ? r.customer.address : '---';
            document.getElementById('view-wb-customer-tin').textContent = r.customer ? r.customer.tin : '---';
            document.getElementById('view-wb-customer-phone').textContent = r.customer ? r.customer.phone : '---';
            
            // Waybill Info
            document.getElementById('view-wb-sequence').textContent = r.waybill.waybill_sequence || '---';
            document.getElementById('view-wb-no').textContent = r.waybill.waybill_no || r.waybill.waybill_sequence || 'N/A';
            document.getElementById('view-wb-forwarder').textContent = r.waybill.forwarder;
            document.getElementById('view-wb-total-invoices').textContent = r.stats.total_invoices;
            document.getElementById('view-wb-total-items').textContent = r.stats.total_items;
            document.getElementById('view-wb-total-cargo').textContent = r.stats.total_cargo;
            
            // Invoices Table
            const invTbody = document.getElementById('view-wb-invoices-tbody');
            invTbody.innerHTML = r.invoices.map(inv => `
                <tr>
                    <td class="p-3 font-mono font-bold text-maroon">${inv.invoice_no}</td>
                    <td class="p-3">${inv.item_count} Items</td>
                    <td class="p-3 text-center">
                        <button onclick="openInvoiceProductsModal('${inv.id}', '${inv.invoice_no}')" class="px-2 py-1 bg-maroon/5 text-maroon text-[9px] font-bold rounded hover:bg-maroon/10 transition-all">View Products</button>
                    </td>
                </tr>
            `).join('');
            
            // Cargo Table
            const cargoTbody = document.getElementById('view-wb-cargo-tbody');
            cargoTbody.innerHTML = r.cargo.map(c => `
                <tr>
                    <td class="p-3 text-center font-bold">${c.quantity}</td>
                    <td class="p-3 text-center uppercase text-[10px]">${c.unit}</td>
                    <td class="p-3">${c.description}</td>
                </tr>
            `).join('');
            
            toggleModal('wb-history-view-modal', true);
            switchViewModalTab('customer');
            switchViewModalRightTab('invoices');
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    } catch (e) { alert('Error loading details'); }
};

window.printSingleHistory = async function(id) {
    try {
        await printWaybillIds([String(id)]);
    } catch (e) {
        console.error('Single waybill print review failed:', e);
        alert('Error loading waybill print review: ' + e.message);
    }
};

window.switchViewModalTab = function(tab) {
    document.getElementById('view-modal-tab-customer').classList.toggle('hidden', tab !== 'customer');
    document.getElementById('view-modal-tab-waybill').classList.toggle('hidden', tab !== 'waybill');
    
    const btnCust = document.getElementById('view-tab-btn-customer');
    const btnWb = document.getElementById('view-tab-btn-waybill');
    
    if (tab === 'customer') {
        btnCust.className = 'flex-1 py-3 text-[10px] font-bold uppercase tracking-widest text-maroon border-b-2 border-maroon bg-white';
        btnWb.className = 'flex-1 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400 border-b-2 border-transparent hover:text-maroon';
    } else {
        btnWb.className = 'flex-1 py-3 text-[10px] font-bold uppercase tracking-widest text-maroon border-b-2 border-maroon bg-white';
        btnCust.className = 'flex-1 py-3 text-[10px] font-bold uppercase tracking-widest text-slate-400 border-b-2 border-transparent hover:text-maroon';
    }
};

window.switchViewModalRightTab = function(tab) {
    document.getElementById('view-modal-right-tab-invoices').classList.toggle('hidden', tab !== 'invoices');
    document.getElementById('view-modal-right-tab-cargo').classList.toggle('hidden', tab !== 'cargo');
    
    const btnInv = document.getElementById('view-right-tab-btn-invoices');
    const btnCargo = document.getElementById('view-right-tab-btn-cargo');
    
    if (tab === 'invoices') {
        btnInv.className = 'py-4 px-6 text-[10px] font-bold uppercase tracking-widest text-maroon border-b-2 border-maroon';
        btnCargo.className = 'py-4 px-6 text-[10px] font-bold uppercase tracking-widest text-slate-400 border-b-2 border-transparent hover:text-maroon';
    } else {
        btnCargo.className = 'py-4 px-6 text-[10px] font-bold uppercase tracking-widest text-maroon border-b-2 border-maroon';
        btnInv.className = 'py-4 px-6 text-[10px] font-bold uppercase tracking-widest text-slate-400 border-b-2 border-transparent hover:text-maroon';
    }
};

window.openInvoiceProductsModal = async function(id, invoiceNo) {
    document.getElementById('invoice-products-title').textContent = 'Products for ' + invoiceNo;
    const tbody = document.getElementById('invoice-products-tbody');
    tbody.innerHTML = '<tr><td colspan="4" class="p-4 text-center italic text-slate-300">Loading...</td></tr>';
    toggleModal('wb-invoice-products-modal', true);
    
    try {
        const res = await fetch(window.waybillRoutes.soItems.replace(':id', id));
        const r = await res.json();
        if (r.success) {
            tbody.innerHTML = r.items.map(p => `
                <tr>
                    <td class="p-3 font-mono font-bold">${p.product_code}</td>
                    <td class="p-3">${p.description}</td>
                    <td class="p-3 text-right font-bold">${p.quantity}</td>
                    <td class="p-3">${p.oum}</td>
                </tr>
            `).join('');
        }
    } catch (e) { tbody.innerHTML = '<tr><td colspan="4" class="p-4 text-center text-red-400">Error loading products.</td></tr>'; }
};

async function fetchWaybillPrintData(ids) {
    const res = await fetch(window.waybillRoutes.batchPrint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.csrfToken },
        body: JSON.stringify({ ids })
    });
    const r = await res.json();
    if (!res.ok || !r.success) {
        throw new Error(r.message || `Unable to load waybill print data (${res.status}).`);
    }
    return Array.isArray(r.data) ? r.data : [];
}

function escapeWaybillPrintHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function ensureWaybillPrintReviewModal() {
    let modal = document.getElementById('wb-print-review-modal');
    if (modal) return modal;

    modal = document.createElement('div');
    modal.id = 'wb-print-review-modal';
    modal.className = 'fixed inset-0 z-[1200] hidden bg-slate-950/90 backdrop-blur-sm p-4 md:p-6';
    modal.innerHTML = `
        <div class="mx-auto flex h-full max-w-[1500px] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b-4 border-gold bg-maroon px-5 py-4 text-white">
                <div>
                    <h3 class="text-sm font-black uppercase tracking-widest">Waybill Print Review</h3>
                    <p class="mt-1 text-[10px] font-bold uppercase tracking-wider text-white/60">Click any highlighted print value to edit it before printing.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="printWaybillReview()" class="rounded-xl bg-white px-5 py-2.5 text-xs font-black uppercase tracking-widest text-maroon shadow hover:bg-gold">Print</button>
                    <button type="button" onclick="closeWaybillPrintReview()" class="rounded-xl bg-white/10 px-4 py-2.5 text-xs font-black uppercase tracking-widest text-white hover:bg-white/20">Close</button>
                </div>
            </div>
            <div class="flex-1 overflow-hidden bg-slate-100 p-3 md:p-4">
                <iframe id="wb-print-review-iframe" class="h-full w-full rounded-xl bg-white shadow-inner" frameborder="0"></iframe>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    return modal;
}

window.closeWaybillPrintReview = function() {
    document.getElementById('wb-print-review-modal')?.classList.add('hidden');
};

window.printWaybillReview = function() {
    const iframe = document.getElementById('wb-print-review-iframe');
    if (!iframe?.contentWindow) return;
    iframe.contentWindow.focus();
    iframe.contentWindow.print();
};

function renderWaybillPrintPages(data) {
    if (!Array.isArray(data) || data.length === 0) {
        throw new Error('No waybill print data found.');
    }

    const modal = ensureWaybillPrintReviewModal();
    const iframe = modal.querySelector('#wb-print-review-iframe');
    const doc = iframe.contentWindow.document;

    let html = `
        <html>
        <head>
            <title>Print Waybills</title>
            <script src="https://cdn.tailwindcss.com"><\/script>
            <style>
                @import url('https://fonts.googleapis.com/css2?family=PT+Serif:wght@400;700&display=swap');
                html, body { margin: 0; padding: 0; background: #e2e8f0; font-family: 'PT Serif', serif; }
                body { padding: 18px 0; }
                .print-page {
                    width: 210mm;
                    min-height: 297mm;
                    margin: 0 auto 18px auto;
                    background: white;
                    padding: 2rem;
                    box-sizing: border-box;
                    page-break-after: always;
                    position: relative;
                    box-shadow: 0 8px 24px rgba(15, 23, 42, .12);
                }
                .print-page:last-child { page-break-after: auto; }
                .waybill-line {
                    border-bottom: 1px solid #cbd5e1;
                    flex: 1 1 0%;
                    min-width: 0;
                    min-height: 1.25rem;
                    height: auto;
                    line-height: 1.25rem;
                    white-space: normal;
                    overflow-wrap: anywhere;
                    word-break: break-word;
                    padding: 0 2px 2px 2px;
                }
                .waybill-editable {
                    cursor: text;
                    border-radius: 2px;
                    outline: 1px dashed transparent;
                    transition: background-color .15s ease, outline-color .15s ease;
                }
                .waybill-editable:hover,
                .waybill-editable:focus {
                    background: #fef9c3;
                    outline-color: #d97706;
                }
                .waybill-editable:focus { outline-width: 2px; }
                .customer-name-line { font-weight: 700; }
                @media print {
                    html, body { background: white; padding: 0; }
                    .print-page { margin: 0; border: none; box-shadow: none; }
                    .waybill-editable, .waybill-editable:hover, .waybill-editable:focus {
                        background: transparent !important;
                        outline: none !important;
                    }
                    @page { size: A4; margin: 0; }
                }
            </style>
        </head>
        <body>
    `;

    data.forEach((wb) => {
        const invoices = wb.invoices ? wb.invoices.split(', ') : [];
        const customerName = escapeWaybillPrintHtml(wb.customer?.name || '---');
        const customerAddress = escapeWaybillPrintHtml(wb.customer?.address || '---');
        const customerPhone = escapeWaybillPrintHtml(wb.customer?.phone || '---');
        const forwarder = escapeWaybillPrintHtml(wb.forwarder || '---');
        const printedWaybillNo = escapeWaybillPrintHtml(
            window.formatPrintedWaybillNumber
                ? window.formatPrintedWaybillNumber(wb.waybill_sequence, wb.waybill_no)
                : (wb.display_waybill_no || wb.waybill_no || wb.waybill_sequence || 'N/A')
        );
        const printedDate = escapeWaybillPrintHtml(new Date().toLocaleDateString('en-PH', {year:'numeric', month:'long', day:'numeric'}));
        const total = Number.parseFloat(wb.total) || 0;
        const totalText = escapeWaybillPrintHtml('₱ ' + total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        const cargo = Array.isArray(wb.cargo) ? wb.cargo : [];

        html += `
            <div class="print-page text-slate-900 text-[13px]">
                <div class="flex justify-between items-start mb-8">
                    <div class="space-y-1 w-1/2 min-w-0">
                        <div class="flex items-start"><span class="w-20 font-bold shrink-0">to:</span><span contenteditable="true" spellcheck="false" class="waybill-line waybill-editable customer-name-line">${customerName}</span></div>
                        <div class="flex items-start"><span class="w-20 font-bold shrink-0">address:</span><span contenteditable="true" spellcheck="false" class="waybill-line waybill-editable">${customerAddress}</span></div>
                        <div class="flex items-start"><span class="w-20 font-bold shrink-0">Tell No:</span><span contenteditable="true" spellcheck="false" class="waybill-line waybill-editable">${customerPhone}</span></div>
                        <div class="flex items-start"><span class="w-20 font-bold shrink-0">Date:</span><span contenteditable="true" spellcheck="false" class="waybill-line waybill-editable">${printedDate}</span></div>
                    </div>
                    <div class="space-y-1 w-1/2 min-w-0 ml-8">
                        <div class="flex items-start"><span class="w-24 font-bold shrink-0">Shipper:</span><span contenteditable="true" spellcheck="false" class="waybill-line waybill-editable">W68 AUTOPARTS &amp; SERVICE CENTER</span></div>
                        <div class="flex items-start"><span class="w-24 font-bold shrink-0">Forwarder:</span><span contenteditable="true" spellcheck="false" class="waybill-line waybill-editable">${forwarder}</span></div>
                        <div class="flex items-start"><span class="w-24 font-bold shrink-0">WAYBILL NO.</span><span contenteditable="true" spellcheck="false" class="waybill-line waybill-editable font-bold">${printedWaybillNo}</span></div>
                        <div class="flex items-start"><span class="w-24 font-bold shrink-0">Total:</span><span contenteditable="true" spellcheck="false" class="waybill-line waybill-editable">${totalText}</span></div>
                    </div>
                </div>

                <table class="w-full border-collapse mb-8 border border-slate-800">
                    <thead>
                        <tr class="bg-slate-100">
                            <th class="py-2 px-3 text-left w-20 border border-slate-800">QTY</th>
                            <th class="py-2 px-3 text-left w-24 border border-slate-800">UNIT</th>
                            <th class="py-2 px-3 text-left border border-slate-800">CARGO DESCRIPTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${cargo.map(c => `
                            <tr>
                                <td contenteditable="true" spellcheck="false" class="waybill-editable py-2 px-3 border border-slate-800">${escapeWaybillPrintHtml(c.quantity)}</td>
                                <td contenteditable="true" spellcheck="false" class="waybill-editable py-2 px-3 border border-slate-800">${escapeWaybillPrintHtml(c.unit)}</td>
                                <td contenteditable="true" spellcheck="false" class="waybill-editable py-2 px-3 border border-slate-800">${escapeWaybillPrintHtml(c.description)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>

                <div class="mt-auto pt-12">
                    <div class="flex flex-wrap items-center">
                        <div class="border border-slate-800 px-3 py-1 font-bold bg-slate-100 min-h-8 flex items-center">INVOICE NO.</div>
                        ${invoices.map(num => `<div contenteditable="true" spellcheck="false" class="waybill-editable border border-slate-800 border-l-0 px-3 py-1 min-h-8 flex items-center break-words">${escapeWaybillPrintHtml(num)}</div>`).join('')}
                    </div>
                    <div class="flex mt-10 ml-[65%] items-center">
                        <span class="font-bold mr-2 whitespace-nowrap">Received By:</span>
                        <div contenteditable="true" spellcheck="false" class="waybill-editable border-b border-slate-800 flex-1 min-h-8"></div>
                    </div>
                </div>
            </div>
        `;
    });

    html += `</body></html>`;
    doc.open();
    doc.write(html);
    doc.close();

    modal.classList.remove('hidden');
    if (typeof lucide !== 'undefined') lucide.createIcons();
    return Promise.resolve();
}

async function printWaybillIds(ids) {
    const data = await fetchWaybillPrintData(ids);
    await renderWaybillPrintPages(data);
}

window.batchPrintWaybills = async function() {
    const checked = Array.from(document.querySelectorAll('.wb-history-checkbox:checked')).map(cb => cb.value);
    if (checked.length === 0) { alert('Please select at least one waybill.'); return; }

    try {
        await printWaybillIds(checked);
    } catch (e) {
        console.error('Waybill print error:', e);
        alert('Error during batch print: ' + e.message);
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
        row.style.display = text.toLowerCase().includes(filter) ? '' : 'none';
    });
};

window.formatPrintedWaybillNumber = function(sequence, externalNo) {
    const seq = String(sequence || '').trim();
    const ext = String(externalNo || '').trim();
    if (seq && ext) return seq + '/' + ext;
    return seq || ext || 'N/A';
};

window.printReceipt = function(waybillNo, forwarder, total, cargoItems) {
    const order = window._selectedOrders[0] || {};
    
    document.getElementById('print-customer-name').textContent = order.customer_name || '---';
    document.getElementById('print-customer-address').textContent = order.address || '---';
    document.getElementById('print-customer-tell').textContent = order.contact_number || '---';
    document.getElementById('print-date').textContent = new Date().toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });
    
    document.getElementById('print-shipper').textContent = 'W68 AUTOPARTS & SERVICE CENTER';
    document.getElementById('print-forwarder').textContent = forwarder || '---';
    document.getElementById('print-waybill-no').textContent = waybillNo || '---';
    document.getElementById('print-total').textContent = '₱ ' + parseFloat(total).toLocaleString('en-US', {minimumFractionDigits: 2});
    
    const invoiceCells = document.getElementById('print-invoice-cells');
    const invoiceNumbers = window._selectedOrders.map(o => o.invoice_number || o.order_number).filter(Boolean);
    
    let html = `<div class="border border-slate-800 px-3 py-1 font-bold bg-slate-100 h-8 flex items-center">INVOICE NO.</div>`;
    invoiceNumbers.forEach(num => {
        html += `<div class="border border-slate-800 border-l-0 px-3 py-1 h-8 flex items-center">${num}</div>`;
    });
    invoiceCells.innerHTML = html;
    
    const tbody = document.getElementById('print-cargo-items');
    tbody.innerHTML = cargoItems.map(item => `
        <tr class="border-b border-slate-800">
            <td class="py-2 px-3 border border-slate-800">${item.qty}</td>
            <td class="py-2 px-3 border border-slate-800">${item.unit}</td>
            <td class="py-2 px-3 border border-slate-800">${item.desc}</td>
        </tr>
    `).join('');
    
    setTimeout(() => {
        window.print();
    }, 500);
};

// ============== EDIT WAYBILL (3-STEP) ==============
window._editWaybillId = null;
window._editCargoData = [];
window._editAttachedInvoices = [];
window._editAvailableInvoices = [];
window._editSOSearchTerm = '';
window.editCurrentStep = 1;

window.openEditWaybillModal = async function(id) {
    window._editWaybillId = id;
    try {
        const res = await fetch(window.waybillRoutes.editData.replace(':id', id));
        const data = await res.json();
        if (!data.success) throw new Error(data.message);

        const wb = data.waybill;
        document.getElementById('edit-waybill-number').textContent = wb.waybill_sequence || '---';
        document.getElementById('edit-display-no').textContent = wb.waybill_sequence || '---';
        document.getElementById('edit-waybill-no-input').value = wb.waybill_no || '';
        document.getElementById('edit-display-date').textContent = wb.waybill_date || '---';
        document.getElementById('edit-display-customer').textContent = data.customer?.name || '---';
        document.getElementById('edit-display-contact').textContent = data.customer?.phone || '---';
        document.getElementById('edit-waybill-date').value = wb.waybill_date || '';
        document.getElementById('edit-forwarder').value = wb.forwarder || '';
        document.getElementById('edit-total-value').value = wb.total_value || 0;
        document.getElementById('edit-declared-value').value = wb.declared_value || 0;

        window._editAttachedInvoices = (data.attached_invoices || []).map(inv => ({ ...inv }));
        window._editAvailableInvoices = (data.available_invoices || []);

        window._editCargoData = (data.cargo || []).map(c => ({
            qty: c.quantity || 1,
            unit: c.unit || 'PCS',
            desc: c.description || ''
        }));

        editGoToStep(1);
        toggleModal('wb-edit-modal', true);
    } catch (e) {
        alert('Failed to load waybill details: ' + e.message);
    }
};

window.editCloseModal = function() {
    toggleModal('wb-edit-modal', false);
};

window.editGoToStep = function(step) {
    editCurrentStep = step;
    document.querySelectorAll('.edit-step-content').forEach(el => el.classList.add('hidden'));
    const target = document.getElementById('edit-step-' + step);
    if (target) target.classList.remove('hidden');

    if (step === 2) editRenderAttachedInvoices();
    if (step === 3) editRenderCargoRows();

    for (let i = 1; i <= 3; i++) {
        const indicator = document.getElementById('edit-step-indicator-' + i);
        const label = document.getElementById('edit-step-label-' + i);
        const progress = document.getElementById('edit-step-progress-' + i);
        if (!indicator) continue;
        indicator.className = 'step-indicator ' + (i < step ? 'completed' : i === step ? 'active' : 'pending');
        if (label) label.className = 'text-[10px] font-bold uppercase tracking-widest ' + (i <= step ? 'text-amber-700' : 'text-slate-400');
        if (progress) progress.style.width = (i < step ? '100%' : '0%');
    }

    const backBtn = document.getElementById('edit-btn-back');
    if (backBtn) backBtn.classList.toggle('hidden', step === 1);

    const nextBtn = document.getElementById('edit-btn-next');
    const nextText = document.getElementById('edit-next-text');
    if (nextBtn && nextText) {
        if (step === 3) {
            nextText.textContent = 'Save Changes';
            nextBtn.onclick = function() { editConfirm(); };
        } else {
            nextText.textContent = 'Next Step';
            nextBtn.onclick = function() { editGoToStep(step + 1); };
        }
    }
};

window.editRenderAttachedInvoices = function() {
    const tbody = document.getElementById('edit-attached-invoices-tbody');
    if (!tbody) return;

    if (!window._editAttachedInvoices.length) {
        tbody.innerHTML = '<tr><td colspan="3" class="p-4 text-center text-slate-300 italic">No invoices attached.</td></tr>';
    } else {
        tbody.innerHTML = window._editAttachedInvoices.map(inv => `
            <tr>
                <td class="p-4 font-medium text-slate-700">${inv.invoice_no || '---'}</td>
                <td class="p-4 text-right font-bold text-slate-800">₱ ${parseFloat(inv.total_amount || 0).toLocaleString('en-PH', {minimumFractionDigits:2})}</td>
                <td class="p-4 text-center">
                    <button onclick="editRemoveInvoice(${inv.id})" class="p-1.5 bg-red-50 text-red-400 hover:bg-red-100 hover:text-red-600 rounded-lg transition-all" title="Remove Invoice">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    }

    const total = window._editAttachedInvoices.reduce((sum, inv) => sum + (parseFloat(inv.total_amount) || 0), 0);
    const display = document.getElementById('edit-total-value-display');
    if (display) display.textContent = '₱ ' + total.toLocaleString('en-PH', {minimumFractionDigits:2});

    if (typeof lucide !== 'undefined') lucide.createIcons();
};

window.editRemoveInvoice = function(id) {
    const idx = window._editAttachedInvoices.findIndex(inv => inv.id === id);
    if (idx === -1) return;
    const removed = window._editAttachedInvoices.splice(idx, 1)[0];
    if (removed && !window._editAvailableInvoices.find(inv => inv.id === removed.id)) {
        window._editAvailableInvoices.push(removed);
    }
    editRenderAttachedInvoices();
};

window.editOpenSOModal = function() {
    _editSOSearchTerm = '';
    const searchInput = document.getElementById('edit-so-search');
    const searchClear = document.getElementById('edit-so-search-clear');
    if (searchInput) searchInput.value = '';
    if (searchClear) searchClear.classList.add('hidden');
    editRenderAvailableInvoices();
    toggleModal('edit-so-select-modal', true);
    if (typeof lucide !== 'undefined') lucide.createIcons();
};

function editRenderAvailableInvoices() {
    const tbody = document.getElementById('edit-so-tbody');
    const noResults = document.getElementById('edit-so-no-results');
    if (!tbody) return;

    const attachedIds = window._editAttachedInvoices.map(inv => inv.id);
    let available = window._editAvailableInvoices.filter(inv => !attachedIds.includes(inv.id));

    if (_editSOSearchTerm) {
        const term = _editSOSearchTerm.toLowerCase();
        available = available.filter(inv =>
            (inv.invoice_no || '').toLowerCase().includes(term) ||
            (inv.customer_name || '').toLowerCase().includes(term) ||
            (inv.order_number || '').toLowerCase().includes(term) ||
            (String(inv.id) || '').includes(term)
        );
    }

    if (!available.length) {
        tbody.innerHTML = '';
        if (noResults) noResults.classList.remove('hidden');
    } else {
        tbody.innerHTML = available.map(inv => `
            <tr>
                <td class="p-3 text-center"><input type="checkbox" value="${inv.id}" data-invoice="${(inv.invoice_no || '').replace(/"/g, '&quot;')}" data-amount="${inv.total_amount || 0}" onchange="editSelectSO(this)" class="accent-amber-600 cursor-pointer"></td>
                <td class="p-3 font-medium text-slate-700">${inv.invoice_no || '---'}</td>
                <td class="p-3 text-right font-bold text-slate-800">₱ ${parseFloat(inv.total_amount || 0).toLocaleString('en-PH', {minimumFractionDigits:2})}</td>
            </tr>
        `).join('');
        if (noResults) noResults.classList.add('hidden');
    }
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

window.searchEditSOModal = function() {
    const input = document.getElementById('edit-so-search');
    const clearBtn = document.getElementById('edit-so-search-clear');
    if (!input) return;
    _editSOSearchTerm = input.value.trim();
    if (clearBtn) clearBtn.classList.toggle('hidden', !_editSOSearchTerm);
    clearTimeout(window._editSOSearchTimer);
    window._editSOSearchTimer = setTimeout(() => {
        editRenderAvailableInvoices();
    }, 300);
};

window.clearEditSOSearch = function() {
    const input = document.getElementById('edit-so-search');
    const clearBtn = document.getElementById('edit-so-search-clear');
    if (input) input.value = '';
    if (clearBtn) clearBtn.classList.add('hidden');
    _editSOSearchTerm = '';
    editRenderAvailableInvoices();
};

window.editSelectSO = function(cb) {
    const row = cb.closest('tr');
    if (cb.checked) {
        if (row) row.classList.add('bg-amber-50');
    } else {
        if (row) row.classList.remove('bg-amber-50');
    }
};

window.editConfirmSelectedSO = function() {
    const checked = document.querySelectorAll('#edit-so-tbody input[type=checkbox]:checked');
    checked.forEach(cb => {
        const id = parseInt(cb.value);
        if (!window._editAttachedInvoices.find(inv => inv.id === id)) {
            window._editAttachedInvoices.push({
                id: id,
                invoice_no: cb.dataset.invoice || '---',
                total_amount: parseFloat(cb.dataset.amount) || 0
            });
        }
    });
    toggleModal('edit-so-select-modal', false);
    editRenderAttachedInvoices();
};

window.editCalcDeclared = function() {
    const total = parseFloat(document.getElementById('edit-total-value').value) || 0;
    const pct = parseFloat(document.getElementById('edit-pct-declare').value) || 0;
    document.getElementById('edit-declared-value').value = (total * pct / 100).toFixed(2);
};

window.editRenderCargoRows = function() {
    const tbody = document.getElementById('edit-cargo-tbody');
    if (!tbody) return;
    tbody.innerHTML = window._editCargoData.map((item, i) => `
        <tr>
            <td class="p-2 px-3 text-center"><input type="number" value="${item.qty}" min="1" onchange="editUpdateCargo(${i}, 'qty', this.value)" class="edit-cargo-qty w-full px-2 py-1.5 border border-slate-200 rounded-lg text-center font-bold outline-none focus:ring-2 focus:ring-amber-300/30" style="min-width: 7.25rem;"></td>
            <td class="p-2 px-3 text-center"><input type="text" value="${item.unit}" onchange="editUpdateCargo(${i}, 'unit', this.value)" class="w-full px-2 py-1.5 border border-slate-200 rounded-lg text-center uppercase outline-none focus:ring-2 focus:ring-amber-300/30"></td>
            <td class="p-2 px-3"><input type="text" value="${item.desc}" placeholder="Enter cargo description..." onchange="editUpdateCargo(${i}, 'desc', this.value)" class="w-full px-2 py-1.5 border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-amber-300/30"></td>
            <td class="p-2 px-3 text-center"><button onclick="editRemoveCargo(${i})" class="p-1.5 bg-red-50 text-red-400 hover:bg-red-100 hover:text-red-600 rounded-lg transition-all"><i data-lucide="x" class="w-3.5 h-3.5"></i></button></td>
        </tr>
    `).join('');
    if (typeof lucide !== 'undefined') lucide.createIcons();
};

window.editUpdateCargo = function(idx, field, value) {
    if (window._editCargoData[idx]) {
        window._editCargoData[idx][field] = field === 'qty' ? parseInt(value) || 0 : value;
    }
};

window.editRemoveCargo = function(idx) {
    window._editCargoData.splice(idx, 1);
    editRenderCargoRows();
};

window.editAddCargoRow = function() {
    window._editCargoData.push({ qty: 1, unit: 'PCS', desc: '' });
    editRenderCargoRows();
};

window.editConfirm = function() {
    toggleModal('wb-edit-modal', false);
    toggleModal('wb-edit-confirm-modal', true);
};

window.saveEditWaybill = async function() {
    toggleModal('wb-edit-confirm-modal', false);
    const id = window._editWaybillId;
    if (!id) return;

    const payload = {
        forwarder: document.getElementById('edit-forwarder').value,
        waybill_date: document.getElementById('edit-waybill-date').value,
        waybill_no: document.getElementById('edit-waybill-no-input').value,
        total_value: parseFloat(document.getElementById('edit-total-value').value) || 0,
        declared_value: parseFloat(document.getElementById('edit-declared-value').value) || 0,
        cargo_items: window._editCargoData,
        invoice_ids: window._editAttachedInvoices.map(inv => inv.id)
    };

    try {
        const url = window.waybillRoutes.update.replace(':id', id);
        const res = await fetch(url, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            toggleModal('wb-edit-success-modal', true);
        } else {
            alert(data.message || 'Failed to update waybill.');
        }
    } catch (e) {
        alert('Error updating waybill: ' + e.message);
    }
};

window.closeEditSuccess = function() {
    toggleModal('wb-edit-success-modal', false);
    location.reload();
};
