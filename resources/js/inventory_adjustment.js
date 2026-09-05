/**
 * Inventory Adjustment Client Logic
 */

let items = [];
let historyData = [];
let filteredItems = [];
let filteredHistory = [];
let currentFilterStatus = 'All';
let selectedItem = null;
let currentPage = 1;
let searchFilters = { code: '', name: '', desc: '', app: '', qty: '', diff: '' };
let adjustedRows = [];
let adjustedPage = 1;
let adjustedLoaded = false;
let adjustedSearchFilters = { code: '', qty: '', unit: '' };
let selectedAdjustmentForDelete = null;

window.addEventListener("DOMContentLoaded", () => {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    fetchProducts(1);
    setupColumnSearchListeners();
    setupAdjustedSearchListeners();
});

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

const debouncedFetch = debounce(() => fetchProducts(1), 300);

const debouncedAdjustedFetch = debounce(() => fetchAdjusted(1), 300);

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function showSuccess(title, message) {
    const titleEl = document.getElementById('success-modal-title');
    const messageEl = document.getElementById('success-modal-message');
    if (titleEl) titleEl.textContent = title || 'Success!';
    if (messageEl) messageEl.textContent = message || 'The action was completed successfully.';
    toggleModal('success-modal', true);
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function setupAdjustedSearchListeners() {
    ['code', 'qty', 'unit'].forEach(key => {
        const input = document.getElementById(`adjusted-search-${key}`);
        if (!input) return;
        input.addEventListener('input', () => {
            adjustedSearchFilters[key] = input.value.trim();
            debouncedAdjustedFetch();
        });
    });
}

window.switchAdjustmentTab = function(tab) {
    const adjustPanel = document.getElementById('adjust-tab-panel');
    const adjustedPanel = document.getElementById('adjusted-tab-panel');
    const adjustBtn = document.getElementById('tab-adjust-btn');
    const adjustedBtn = document.getElementById('tab-adjusted-btn');

    const showAdjusted = tab === 'adjusted';
    adjustPanel?.classList.toggle('hidden', showAdjusted);
    adjustedPanel?.classList.toggle('hidden', !showAdjusted);

    if (adjustBtn) {
        adjustBtn.classList.toggle('bg-maroon', !showAdjusted);
        adjustBtn.classList.toggle('text-white', !showAdjusted);
        adjustBtn.classList.toggle('text-slate-500', showAdjusted);
    }
    if (adjustedBtn) {
        adjustedBtn.classList.toggle('bg-maroon', showAdjusted);
        adjustedBtn.classList.toggle('text-white', showAdjusted);
        adjustedBtn.classList.toggle('text-slate-500', !showAdjusted);
    }

    if (showAdjusted && !adjustedLoaded) {
        fetchAdjusted(1);
    }
};

window.fetchAdjusted = function(page = 1) {
    adjustedPage = page;
    const tbody = document.getElementById('adjusted-tbody');
    if (!tbody || !window.adjustmentRoutes?.adjusted) return;

    tbody.innerHTML = '<tr><td colspan="5" class="py-12 text-center text-slate-400 italic"><div class="flex items-center justify-center gap-2"><i data-lucide="loader-2" class="w-4 h-4 animate-spin text-maroon"></i><span>Loading adjustments...</span></div></td></tr>';
    if (typeof lucide !== 'undefined') lucide.createIcons();

    const params = new URLSearchParams({ page });
    if (adjustedSearchFilters.code) params.append('search[code]', adjustedSearchFilters.code);
    if (adjustedSearchFilters.qty) params.append('search[qty]', adjustedSearchFilters.qty);
    if (adjustedSearchFilters.unit) params.append('search[unit]', adjustedSearchFilters.unit);

    fetch(`${window.adjustmentRoutes.adjusted}?${params.toString()}`, {
        headers: { 'Accept': 'application/json' }
    })
        .then(async res => {
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Failed to load adjustment records.');
            return data;
        })
        .then(r => {
            if (!r.success) throw new Error(r.message || 'Failed to load adjustment records.');
            adjustedRows = r.adjustments || [];
            adjustedLoaded = true;
            renderAdjustedTable();
            renderAdjustedPagination(r.pagination);
        })
        .catch(err => {
            console.error(err);
            tbody.innerHTML = `<tr><td colspan="5" class="py-12 text-center text-red-500 italic">${escapeHtml(err.message || 'Error loading adjustments.')}</td></tr>`;
        });
};

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function renderAdjustedTable() {
    const tbody = document.getElementById('adjusted-tbody');
    if (!tbody) return;

    if (!adjustedRows.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="py-12 text-center text-slate-400 italic">No inventory adjustments found.</td></tr>';
        return;
    }

    tbody.innerHTML = adjustedRows.map(row => `
        <tr class="hover:bg-slate-50 transition-colors">
            <td class="py-4 px-6 font-black text-slate-800">${escapeHtml(row.product_code || '---')}</td>
            <td class="py-4 px-6 text-center font-black text-maroon">${escapeHtml(row.adjusted_qty)}</td>
            <td class="py-4 px-6 font-bold uppercase text-slate-600">${escapeHtml(row.unit || '---')}</td>
            <td class="py-4 px-3 text-center">
                <button onclick="openEditAdjustment(${Number(row.id)})" class="px-4 py-2 bg-maroon/5 text-maroon text-[10px] font-bold rounded-lg hover:bg-maroon hover:text-white transition-all">Edit</button>
            </td>
            <td class="py-4 px-3 text-center">
                <button onclick="openDeleteAdjustment(${Number(row.id)})" class="px-4 py-2 bg-red-50 text-red-600 text-[10px] font-bold rounded-lg hover:bg-red-600 hover:text-white transition-all">Delete</button>
            </td>
        </tr>
    `).join('');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function renderAdjustedPagination(pagination) {
    const container = document.getElementById('adjusted-pagination-container');
    const info = document.getElementById('adjusted-pagination-info');
    if (!container) return;

    if (!pagination || pagination.total === 0) {
        container.innerHTML = '';
        if (info) info.textContent = 'Showing 0 to 0 of 0 entries';
        return;
    }

    const { current_page, last_page, total, from, to } = pagination;
    if (info) info.textContent = `Showing ${from} to ${to} of ${total} entries`;

    let html = `
        <button onclick="fetchAdjusted(${current_page - 1})" ${current_page === 1 ? 'disabled' : ''}
            class="p-2 rounded-xl border border-slate-200 text-slate-400 hover:text-maroon hover:border-maroon disabled:opacity-50 disabled:cursor-not-allowed transition-all">
            <i data-lucide="chevron-left" class="w-4 h-4"></i>
        </button>`;

    for (let i = 1; i <= last_page; i++) {
        if (i === 1 || i === last_page || (i >= current_page - 2 && i <= current_page + 2)) {
            html += `<button onclick="fetchAdjusted(${i})" class="px-4 py-2 rounded-xl text-xs font-bold transition-all ${i === current_page ? 'bg-maroon text-white shadow-md shadow-maroon/20' : 'text-slate-500 hover:bg-slate-100'}">${i}</button>`;
        } else if (i === current_page - 3 || i === current_page + 3) {
            html += '<span class="text-slate-300 px-1">...</span>';
        }
    }

    html += `
        <button onclick="fetchAdjusted(${current_page + 1})" ${current_page === last_page ? 'disabled' : ''}
            class="p-2 rounded-xl border border-slate-200 text-slate-400 hover:text-maroon hover:border-maroon disabled:opacity-50 disabled:cursor-not-allowed transition-all">
            <i data-lucide="chevron-right" class="w-4 h-4"></i>
        </button>`;

    container.innerHTML = html;
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

window.openEditAdjustment = function(id) {
    const row = adjustedRows.find(x => Number(x.id) === Number(id));
    if (!row) return;

    document.getElementById('edit-adjustment-id').value = row.id;
    document.getElementById('edit-adjustment-code').value = row.product_code || '---';
    document.getElementById('edit-adjustment-part').value = row.part_number || '---';
    document.getElementById('edit-adjustment-qty').value = row.adjusted_qty ?? 0;
    document.getElementById('edit-adjustment-unit').value = row.unit || '';
    toggleModal('edit-adjustment-modal', true);
};

window.saveEditedAdjustment = function() {
    const id = Number(document.getElementById('edit-adjustment-id')?.value || 0);
    const qtyValue = document.getElementById('edit-adjustment-qty')?.value ?? '';
    const unit = document.getElementById('edit-adjustment-unit')?.value.trim() || '';
    const qty = Number.parseInt(qtyValue, 10);

    if (!id || qtyValue === '' || Number.isNaN(qty) || qty < 0) {
        alert('Please enter a valid quantity.');
        return;
    }
    if (!unit) {
        alert('Please enter a unit.');
        return;
    }

    fetch(window.adjustmentRoutes.adjustedUpdate.replace(':id', id), {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken()
        },
        body: JSON.stringify({ adjusted_qty: qty, unit })
    })
        .then(async res => {
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) throw new Error(data.message || 'Failed to update adjustment.');
            return data;
        })
        .then(r => {
            toggleModal('edit-adjustment-modal', false);
            showSuccess('Adjustment Updated!', r.message || 'The adjustment was updated successfully.');
            fetchAdjusted(adjustedPage);
            fetchProducts(currentPage);
        })
        .catch(err => {
            console.error(err);
            alert(err.message || 'Error updating adjustment.');
        });
};

window.openDeleteAdjustment = function(id) {
    const row = adjustedRows.find(x => Number(x.id) === Number(id));
    if (!row) return;
    selectedAdjustmentForDelete = row;
    const text = document.getElementById('delete-adjustment-text');
    if (text) text.textContent = `Delete adjustment for ${row.product_code || 'this product'}?`;
    toggleModal('delete-adjustment-modal', true);
};

window.confirmDeleteAdjustment = function() {
    const row = selectedAdjustmentForDelete;
    if (!row) return;

    fetch(window.adjustmentRoutes.adjustedDelete.replace(':id', row.id), {
        method: 'DELETE',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken()
        }
    })
        .then(async res => {
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) throw new Error(data.message || 'Failed to delete adjustment.');
            return data;
        })
        .then(r => {
            selectedAdjustmentForDelete = null;
            toggleModal('delete-adjustment-modal', false);
            showSuccess('Adjustment Deleted!', r.message || 'The adjustment was deleted successfully.');
            fetchAdjusted(adjustedPage);
            fetchProducts(currentPage);
        })
        .catch(err => {
            console.error(err);
            alert(err.message || 'Error deleting adjustment.');
        });
};

function setupColumnSearchListeners() {
    document.querySelectorAll('.col-search-input').forEach((input, index) => {
        input.addEventListener('input', () => {
            const inputs = document.querySelectorAll('.col-search-input');
            searchFilters.code = inputs[0]?.value.trim() || '';
            searchFilters.name = inputs[1]?.value.trim() || '';
            searchFilters.desc = inputs[2]?.value.trim() || '';
            searchFilters.app  = inputs[3]?.value.trim() || '';
            searchFilters.qty  = inputs[4]?.value.trim() || '';
            searchFilters.diff = inputs[5]?.value.trim() || '';
            debouncedFetch();
        });
    });
}

window.fetchProducts = function(page = 1) {
    currentPage = page;
    const tbody = document.getElementById('adjustment-tbody');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="7" class="py-12 text-center text-slate-400 italic"><div class="flex items-center justify-center space-x-2"><i data-lucide="loader-2" class="w-4 h-4 animate-spin text-maroon"></i><span>Loading products...</span></div></td></tr>';
    if (typeof lucide !== 'undefined') lucide.createIcons();

    const params = new URLSearchParams({
        page: page,
        status: currentFilterStatus
    });

    if (searchFilters.code) params.append('search[code]', searchFilters.code);
    if (searchFilters.name) params.append('search[name]', searchFilters.name);
    if (searchFilters.desc) params.append('search[desc]', searchFilters.desc);
    if (searchFilters.app)  params.append('search[app]', searchFilters.app);
    if (searchFilters.qty)  params.append('search[qty]', searchFilters.qty);

    fetch(`${window.adjustmentRoutes.fetch}?${params.toString()}`)
        .then(res => res.json())
        .then(r => {
            if (r.success) {
                items = r.products || [];
                filteredItems = [...items];
                renderAdjustmentTable();
                renderPagination(r.pagination);
                updateStats(r.stats);
            } else {
                tbody.innerHTML = '<tr><td colspan="7" class="py-12 text-center text-red-400 italic">Failed to load data.</td></tr>';
            }
        })
        .catch(err => {
            console.error(err);
            tbody.innerHTML = '<tr><td colspan="7" class="py-12 text-center text-red-400 italic">Error loading data.</td></tr>';
        });
}

function updateStats(stats) {
    if (!stats) return;
    document.getElementById('stat-total-items').textContent = stats.total || 0;
    document.getElementById('stat-new-items').textContent = stats.new || 0;
    document.getElementById('stat-old-items').textContent = stats.old || 0;
}

window.toggleModal = function(id, open = true) {
    const modal = document.getElementById(id);
    if (!modal) return;
    if (open) {
        modal.classList.remove('hidden');
    } else {
        modal.classList.add('hidden');
    }
}

function renderAdjustmentTable() {
    const tbody = document.getElementById('adjustment-tbody');
    if (!tbody) return;

    if (!filteredItems.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="py-12 text-center text-slate-400 italic">No products found.</td></tr>';
        return;
    }

    tbody.innerHTML = filteredItems.map(item => `
        <tr class="hover:bg-slate-50 transition-colors">
            <td class="py-4 px-6">
                <div class="flex items-center gap-3">
                    <span class="font-bold text-slate-800">${item.code}</span>
                    <span class="px-2 py-0.5 rounded-full text-[8px] font-black uppercase ${item.status === 'New' ? 'tag-new' : 'tag-old'}">
                        ${item.status}
                    </span>
                </div>
            </td>
            <td class="py-4 px-6 font-bold text-slate-700">${item.name}</td>
            <td class="py-4 px-6 text-slate-400 max-w-[200px] truncate">${item.desc}</td>
            <td class="py-4 px-6 text-slate-500">${item.app}</td>
            <td class="py-4 px-6 text-center font-black text-slate-800">${item.actualQty}</td>
            <td class="py-4 px-6 text-center font-black ${item.diff > 0 ? 'text-emerald-600' : item.diff < 0 ? 'text-maroon' : 'text-slate-300'}">
                ${item.diff > 0 ? '+' : ''}${item.diff}
            </td>
            <td class="py-4 px-6">
                <div class="flex items-center justify-center gap-2">
                    <button onclick="openAdjustModal(${item.id})" class="px-3 py-1.5 bg-maroon/5 text-maroon text-[10px] font-bold rounded-lg hover:bg-maroon hover:text-white transition-all">Adjust</button>
                    <button onclick="openHistoryModal(${item.id})" class="p-2 hover:bg-slate-100 text-slate-400 rounded-lg transition-all"><i data-lucide="history" class="w-4 h-4"></i></button>
                </div>
            </td>
        </tr>
    `).join('');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function renderPagination(pagination) {
    const container = document.getElementById('pagination-container');
    const info = document.getElementById('pagination-info');
    if (!container) return;

    if (!pagination || pagination.total === 0) {
        container.innerHTML = '';
        if (info) info.textContent = 'Showing 0 to 0 of 0 entries';
        return;
    }

    const { current_page, last_page, per_page, total, from, to } = pagination;
    if (info) {
        info.textContent = `Showing ${from} to ${to} of ${total} entries`;
    }

    let html = '';

    html += `
        <button onclick="fetchProducts(${current_page - 1})"
            ${current_page === 1 ? 'disabled' : ''}
            class="p-2 rounded-xl border border-slate-200 text-slate-400 hover:text-maroon hover:border-maroon disabled:opacity-50 disabled:cursor-not-allowed transition-all">
            <i data-lucide="chevron-left" class="w-4 h-4"></i>
        </button>
    `;

    for (let i = 1; i <= last_page; i++) {
        if (i === 1 || i === last_page || (i >= current_page - 2 && i <= current_page + 2)) {
            html += `
                <button onclick="fetchProducts(${i})"
                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all ${i === current_page ? 'bg-maroon text-white shadow-md shadow-maroon/20' : 'text-slate-500 hover:bg-slate-100 border border-transparent'}">
                    ${i}
                </button>
            `;
        } else if (i === current_page - 3 || i === current_page + 3) {
            html += `<span class="text-slate-300 px-1">...</span>`;
        }
    }

    html += `
        <button onclick="fetchProducts(${current_page + 1})"
            ${current_page === last_page ? 'disabled' : ''}
            class="p-2 rounded-xl border border-slate-200 text-slate-400 hover:text-maroon hover:border-maroon disabled:opacity-50 disabled:cursor-not-allowed transition-all">
            <i data-lucide="chevron-right" class="w-4 h-4"></i>
        </button>
    `;

    container.innerHTML = html;
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

window.filterAdjTable = function() {
    const inputs = document.querySelectorAll('.col-search-input');
    searchFilters.code = inputs[0]?.value.trim() || '';
    searchFilters.name = inputs[1]?.value.trim() || '';
    searchFilters.desc = inputs[2]?.value.trim() || '';
    searchFilters.app  = inputs[3]?.value.trim() || '';
    searchFilters.qty  = inputs[4]?.value.trim() || '';
    searchFilters.diff = inputs[5]?.value.trim() || '';
    debouncedFetch();
}

window.openAdjustModal = function(id) {
    selectedItem = items.find(x => x.id === id);
    if (!selectedItem) return;

    document.getElementById('adj-item-name').textContent = selectedItem.desc;
    document.getElementById('adj-item-code').textContent = selectedItem.code;
    document.getElementById('adj-actual-qty').textContent = selectedItem.actualQty;
    document.getElementById('adj-diff-qty').textContent = '0';
    document.getElementById('adj-total-projected').textContent = selectedItem.actualQty;
    document.getElementById('adj-input-qty').value = '';
    document.getElementById('adj-input-unit').value = selectedItem.unit || '';

    toggleModal('adjust-modal', true);
}

window.calculateAdjTotal = function() {
    const input = document.getElementById('adj-input-qty').value;
    if (input === '') {
        document.getElementById('adj-diff-qty').textContent = '0';
        document.getElementById('adj-diff-qty').className = 'w-full px-4 py-3 bg-slate-50 border border-slate-100 rounded-xl text-lg font-black shadow-inner text-slate-400';
        document.getElementById('adj-total-projected').textContent = selectedItem.actualQty;
        return;
    }

    const newActual = parseInt(input) || 0;
    const onHand = selectedItem.onHand;
    const diff = newActual - onHand;

    document.getElementById('adj-diff-qty').textContent = (diff > 0 ? '+' : '') + diff;
    document.getElementById('adj-diff-qty').className = `w-full px-4 py-3 bg-slate-50 border border-slate-100 rounded-xl text-lg font-black shadow-inner ${diff > 0 ? 'text-emerald-600' : diff < 0 ? 'text-maroon' : 'text-slate-400'}`;
    document.getElementById('adj-total-projected').textContent = newActual;
}

window.confirmAdjustment = function() {
    const inputVal = document.getElementById('adj-input-qty').value;
    if (inputVal === '') {
        alert('Please enter a valid actual quantity.');
        return;
    }
    const newActual = parseInt(inputVal);
    if (isNaN(newActual) || newActual < 0) {
        alert('Please enter a valid actual quantity.');
        return;
    }
    const unit = document.getElementById('adj-input-unit')?.value.trim() || '';
    if (!unit) {
        alert('Please enter a unit.');
        return;
    }

    const yesBtn = document.getElementById('confirm-yes-btn');
    yesBtn.onclick = () => {
        fetch(window.adjustmentRoutes.adjust, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken()
            },
            body: JSON.stringify({
                product_id: selectedItem.id,
                actual_qty: newActual,
                unit: unit
            })
        })
        .then(res => res.json())
        .then(r => {
            if (r.success) {
                toggleModal('confirm-modal', false);
                toggleModal('adjust-modal', false);
                showSuccess('Adjustment Complete!', r.message || 'The inventory levels have been synchronized successfully.');
                fetchProducts(currentPage);
                if (adjustedLoaded) fetchAdjusted(1);
            } else {
                alert(r.message || 'Failed to save adjustment.');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error saving adjustment.');
        });
    };
    toggleModal('confirm-modal', true);
}

window.openHistoryModal = function(id) {
    const item = items.find(x => x.id === id);
    if (!item) return;
    document.getElementById('history-item-title').textContent = (item.desc || '') + ' (' + (item.code || '') + ')';
    
    const tbody = document.getElementById('history-tbody');
    tbody.innerHTML = '<tr><td colspan="12" class="py-6 text-center text-slate-400 italic">Loading history...</td></tr>';
    toggleModal('history-modal', true);

    fetch(window.adjustmentRoutes.ledger.replace(':id', id))
        .then(res => res.json())
        .then(r => {
            if (r.success) {
                historyData = r.ledger || [];
                filteredHistory = [...historyData];
                renderHistoryTable();
            } else {
                tbody.innerHTML = '<tr><td colspan="12" class="py-6 text-center text-red-400 italic">Failed to load history.</td></tr>';
            }
        })
        .catch(err => {
            console.error(err);
            tbody.innerHTML = '<tr><td colspan="12" class="py-6 text-center text-red-400 italic">Error loading history.</td></tr>';
        });
}

function renderHistoryTable() {
    const tbody = document.getElementById('history-tbody');
    if (!tbody) return;

    if (!filteredHistory.length) {
        tbody.innerHTML = '<tr><td colspan="12" class="py-6 text-center text-slate-400 italic">No ledger history available.</td></tr>';
        return;
    }

    tbody.innerHTML = filteredHistory.map(h => `
        <tr>
            <td class="py-3 px-4 font-bold text-slate-400 uppercase tracking-tighter">${h.transaction_type}</td>
            <td class="py-3 px-4">${h.date}</td>
            <td class="py-3 px-4 font-black text-slate-800">${h.transaction_number || '---'}</td>
            <td class="py-3 px-4 text-slate-400 font-mono">${h.reference_number || '---'}</td>
            <td class="py-3 px-4 font-bold">${h.entity_name || '---'}</td>
            <td class="py-3 px-4 text-emerald-600 font-black">${h.quantity_in || '-'}</td>
            <td class="py-3 px-4 text-maroon font-black">${h.quantity_out || '-'}</td>
            <td class="py-3 px-4 font-black bg-slate-50/50 text-slate-800">${h.balance_stock}</td>
            <td class="py-3 px-4 uppercase">${h.oum || 'PCS'}</td>
            <td class="py-3 px-4 text-right font-bold">${h.price ? parseFloat(h.price).toFixed(2) : '0.00'}</td>
            <td class="py-3 px-4 text-right font-bold text-slate-400">${h.cost ? parseFloat(h.cost).toFixed(2) : '0.00'}</td>
            <td class="py-3 px-4 italic text-slate-400">${h.remarks || ''}</td>
        </tr>
    `).join('');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

window.filterHistoryTable = function() {
    const inputs = document.querySelectorAll('.hist-search');
    const filters = Array.from(inputs).map(i => i.value.toLowerCase());

    filteredHistory = historyData.filter(h => {
        const type = (h.transaction_type || '').toLowerCase();
        const date = (h.date || '').toLowerCase();
        const trans = (h.transaction_number || '').toLowerCase();
        const ref = (h.reference_number || '').toLowerCase();
        const name = (h.entity_name || '').toLowerCase();
        const uom = (h.oum || '').toLowerCase();
        const remarks = (h.remarks || '').toLowerCase();
        
        return type.includes(filters[0]) &&
               date.includes(filters[1]) &&
               trans.includes(filters[2]) &&
               ref.includes(filters[3]) &&
               name.includes(filters[4]) &&
               (h.quantity_in || 0).toString().includes(filters[5]) &&
               (h.quantity_out || 0).toString().includes(filters[6]) &&
               (h.balance_stock || 0).toString().includes(filters[7]) &&
               uom.includes(filters[8]) &&
               (h.price || 0).toString().includes(filters[9]) &&
               (h.cost || 0).toString().includes(filters[10]) &&
               remarks.includes(filters[11]);
    });
    renderHistoryTable();
}

window.setFilterStatus = function(status) {
    currentFilterStatus = status;
    const btns = document.querySelectorAll('.filter-status-btn');
    btns.forEach(btn => {
        if (btn.textContent.includes(status) || (status === 'All' && btn.textContent === 'All')) {
            btn.classList.add('bg-maroon', 'text-white');
            btn.classList.remove('bg-slate-50', 'text-slate-500');
        } else {
            btn.classList.remove('bg-maroon', 'text-white');
            btn.classList.add('bg-slate-50', 'text-slate-500');
        }
    });
}

window.applyFilters = function() {
    fetchProducts(1);
    toggleModal('filter-modal', false);
}

window.resetFilters = function() {
    currentFilterStatus = 'All';
    setFilterStatus('All');
    document.getElementById('filter-date-from').value = '';
    document.getElementById('filter-date-to').value = '';
    
    const inputs = document.querySelectorAll('.col-search-input');
    inputs.forEach(i => i.value = '');
    searchFilters = { code: '', name: '', desc: '', app: '', qty: '', diff: '' };

    fetchProducts(1);
    toggleModal('filter-modal', false);
}
