/**
 * Forwarder Master List Client Logic
 */

let forwarders = [];
let ledgerData = [];
let filteredForwarders = [];
let filteredLedger = [];
let forwarderFormMode = 'create';
let editingForwarderId = null;
let currentPage = 1;
let totalPages = 1;
let paginationData = {};

const FORWARDER_ENDPOINTS = (() => {
    const r = window.forwarderRoutes || {
        data: '/admin/masterlist/forwarder/data',
        create: '/admin/masterlist/forwarder/create',
        update: '/admin/masterlist/forwarder/update/:id',
        delete: '/admin/masterlist/forwarder/delete/:id'
    };
    return {
        data: r.data,
        create: r.create,
        update: (id) => r.update.replace(':id', id),
        delete: (id) => r.delete.replace(':id', id)
    };
})();

let searchTimeout;

function debounce(func, delay = 300) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(func, delay);
}

window.addEventListener("DOMContentLoaded", () => {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    initializeMockLedger();
    
    // Setup search input listeners with debounce
    const searchInputs = document.querySelectorAll('[id^="col-search-"]');
    searchInputs.forEach(input => {
        input.addEventListener('keyup', () => {
            debounce(() => filterForwarderTable(), 300);
        });
    });
    
    fetchForwarders();
});

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function safeString(value) {
    return String(value ?? '');
}

function escapeHtml(value) {
    return safeString(value).replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));
}

function displayValue(value) {
    const text = safeString(value).trim();
    return text ? escapeHtml(text) : 'N/A';
}

async function fetchForwarders(page = 1) {
    try {
        currentPage = page;
        
        // Build search parameters
        const searchParams = new URLSearchParams();
        searchParams.append('page', page);
        searchParams.append('perPage', 50);
        
        // Get search values
        const code = document.getElementById('col-search-code')?.value.trim() || '';
        const name = document.getElementById('col-search-name')?.value.trim() || '';
        const address = document.getElementById('col-search-address')?.value.trim() || '';
        const contactNo = document.getElementById('col-search-contact')?.value.trim() || '';
        const contactPerson = document.getElementById('col-search-person')?.value.trim() || '';
        
        if (code) searchParams.append('search[code]', code);
        if (name) searchParams.append('search[name]', name);
        if (address) searchParams.append('search[address]', address);
        if (contactNo) searchParams.append('search[contactNo]', contactNo);
        if (contactPerson) searchParams.append('search[contactPerson]', contactPerson);

        const response = await fetch(`${FORWARDER_ENDPOINTS.data}?${searchParams}`, {
            headers: {
                'Accept': 'application/json'
            }
        });
        const result = await response.json();

        forwarders = result.forwarders || [];
        paginationData = result.pagination || {};
        totalPages = paginationData.last_page || 1;
        
        renderForwarderTable();
        updatePaginationControls();
        updateStats(result.stats);
    } catch (error) {
        console.error('Error fetching forwarders:', error);
        forwarders = [];
        filteredForwarders = [];
        renderForwarderTable();
        updateStats();
    }
}

function initializeMockLedger() {
    ledgerData = [
        { date: '2024-05-01', code: 'TR-101', module: 'Logistics', name: 'Shipment A', title: 'Ocean Freight', debit: 5000, credit: 0 },
        { date: '2024-05-10', code: 'PY-202', module: 'Finance', name: 'Payment A', title: 'Wire Transfer', debit: 0, credit: 5000 },
        { date: '2024-05-15', code: 'TR-105', module: 'Logistics', name: 'Shipment B', title: 'Air Freight', debit: 12000, credit: 0 },
        { date: '2024-05-20', code: 'TR-108', module: 'Logistics', name: 'Shipment C', title: 'Ground Transport', debit: 3500, credit: 0 }
    ];
    filteredLedger = [...ledgerData];
}

function updateStats(stats = null) {
    document.getElementById('stat-total-forward').textContent = stats ? stats.total : forwarders.length;
    document.getElementById('stat-monthly-forward').textContent = stats ? stats.monthly : countForwardersByDate('month');
    document.getElementById('stat-annual-forward').textContent = stats ? stats.annual : countForwardersByDate('year');
}

function countForwardersByDate(scope) {
    const now = new Date();

    return forwarders.filter((forwarder) => {
        if (!forwarder.createdAt) return false;

        const createdAt = new Date(forwarder.createdAt);
        if (Number.isNaN(createdAt.getTime())) return false;

        const sameYear = createdAt.getFullYear() === now.getFullYear();
        if (scope === 'year') return sameYear;

        return sameYear && createdAt.getMonth() === now.getMonth();
    }).length;
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

function getForwarderForm() {
    return document.getElementById('add-forwarder-form');
}

function setForwarderModalMode() {
    const title = document.getElementById('forwarder-modal-title');
    const submitText = document.getElementById('forwarder-submit-text');
    const submitIcon = document.getElementById('forwarder-submit-icon');

    if (title) title.textContent = forwarderFormMode === 'edit' ? 'Edit Forwarder' : 'Add Forwarder';
    if (submitText) submitText.textContent = forwarderFormMode === 'edit' ? 'Save Changes' : 'Save Forwarder';
    if (submitIcon) submitIcon.setAttribute('data-lucide', forwarderFormMode === 'edit' ? 'save' : 'plus-circle');

    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function resetForwarderFormState() {
    forwarderFormMode = 'create';
    editingForwarderId = null;
    getForwarderForm()?.reset();
    setForwarderModalMode();
}

window.openAddForwarderModal = function() {
    resetForwarderFormState();
    toggleModal('add-forwarder-modal', true);
}

function renderForwarderTable() {
    const tbody = document.getElementById('forwarder-tbody');
    if (!tbody) return;

    if (!forwarders.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="py-16 text-center text-slate-400 italic">
                    <div class="flex flex-col items-center">
                        <i data-lucide="search-x" class="w-10 h-10 mb-2 opacity-20"></i>
                        No forwarders found
                    </div>
                </td>
            </tr>
        `;
        if (typeof lucide !== 'undefined') lucide.createIcons();
        return;
    }

    tbody.innerHTML = forwarders.map((fw) => {
        const id = Number(fw.id);

        return `
            <tr onclick="openViewModal(${id})" class="hover:bg-slate-50 transition-colors cursor-pointer">
                <td class="py-4 px-6 font-bold text-slate-800">${displayValue(fw.code)}</td>
                <td class="py-4 px-6 font-semibold">${displayValue(fw.name)}</td>
                <td class="py-4 px-6 max-w-[200px] truncate">${displayValue(fw.address)}</td>
                <td class="py-4 px-6">${displayValue(fw.contactNo)}</td>
                <td class="py-4 px-6">${displayValue(fw.contactPerson)}</td>
                <td class="py-4 px-6 text-center" onclick="event.stopPropagation()">
                    <div class="flex items-center justify-center space-x-2">
                        <button type="button" onclick="event.stopPropagation(); openViewModal(${id})" class="p-2 hover:bg-slate-100 text-slate-600 rounded-lg" title="View">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                        </button>
                        <button type="button" onclick="event.stopPropagation(); openEditModal(${id})" class="p-2 hover:bg-blue-50 text-blue-600 rounded-lg" title="Edit">
                            <i data-lucide="edit-3" class="w-4 h-4"></i>
                        </button>
                        <button type="button" onclick="event.stopPropagation(); openDeleteModal(${id})" class="p-2 hover:bg-red-50 text-red-600 rounded-lg" title="Delete">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function updatePaginationControls() {
    const container = document.getElementById('fw-pagination-container');
    if (!container) return;

    // Update pagination info
    document.getElementById('fw-page-from').textContent = paginationData.from || 0;
    document.getElementById('fw-page-to').textContent = paginationData.to || 0;
    document.getElementById('fw-page-total').textContent = paginationData.total || 0;

    if (totalPages <= 1) {
        container.innerHTML = '';
        return;
    }

    let html = '';

    // Previous button
    if (currentPage > 1) {
        html += `<button onclick="goToPage(${currentPage - 1})" class="px-3 py-2 border border-slate-200 hover:bg-slate-100 rounded-lg text-[10px] font-bold text-slate-600">← Previous</button>`;
    }

    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
        if (i === currentPage) {
            html += `<button class="px-3 py-2 bg-maroon text-white rounded-lg text-[10px] font-bold">${i}</button>`;
        } else if (i <= 3 || i >= totalPages - 2 || Math.abs(i - currentPage) <= 1) {
            html += `<button onclick="goToPage(${i})" class="px-3 py-2 border border-slate-200 hover:bg-slate-100 rounded-lg text-[10px] font-bold text-slate-600">${i}</button>`;
        } else if (i === 4 || i === totalPages - 3) {
            html += `<span class="px-2 py-2 text-slate-400">...</span>`;
        }
    }

    // Next button
    if (currentPage < totalPages) {
        html += `<button onclick="goToPage(${currentPage + 1})" class="px-3 py-2 border border-slate-200 hover:bg-slate-100 rounded-lg text-[10px] font-bold text-slate-600">Next →</button>`;
    }

    container.innerHTML = html;
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

window.goToPage = function(page) {
    if (page >= 1 && page <= totalPages) {
        fetchForwarders(page);
    }
}

function applyForwarderFilters(render = true) {
    // Search is now handled server-side, just call fetchForwarders from page 1
    fetchForwarders(1);
}

window.filterForwarderTable = function() {
    applyForwarderFilters();
}

window.saveForwarder = function(event) {
    event.preventDefault();

    const isEdit = forwarderFormMode === 'edit';
    const title = isEdit ? 'Edit Forwarder' : 'Add Forwarder';
    const message = isEdit ? 'Save changes to this forwarder?' : 'Register this new logistics partner?';

    showConfirmModal(title, message, async () => {
        const form = getForwarderForm();
        const formData = new FormData(form);
        const endpoint = isEdit ? FORWARDER_ENDPOINTS.update(editingForwarderId) : FORWARDER_ENDPOINTS.create;

        if (isEdit && !editingForwarderId) {
            alert('No forwarder selected for editing.');
            return;
        }

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: JSON.stringify(Object.fromEntries(formData.entries()))
            });
            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message || (isEdit ? 'Failed to update forwarder' : 'Failed to register forwarder'));
            }

            await fetchForwarders(currentPage);
            toggleModal('add-forwarder-modal', false);
            resetForwarderFormState();
            showSuccessModal('Success', isEdit ? 'Forwarder updated successfully!' : 'Forwarder registered successfully!');
        } catch (error) {
            console.error('Error saving forwarder:', error);
            showErrorModal('Error', error.message);
        }
    });
};

window.openViewModal = function(id) {
    const fw = forwarders.find(x => Number(x.id) === Number(id));
    if (!fw) return;

    document.getElementById('view-fw-name').textContent = safeString(fw.name) || 'N/A';
    document.getElementById('view-fw-code').textContent = safeString(fw.code) || 'N/A';
    document.getElementById('view-fw-address').textContent = safeString(fw.address) || 'N/A';
    document.getElementById('view-fw-contact').textContent = safeString(fw.contactNo) || 'N/A';
    document.getElementById('view-fw-person').textContent = safeString(fw.contactPerson) || 'N/A';

    filteredLedger = [...ledgerData];
    renderLedgerTable();
    toggleModal('view-forwarder-modal', true);
}

window.openEditModal = function(id) {
    const fw = forwarders.find(x => Number(x.id) === Number(id));
    if (!fw) return;

    const form = getForwarderForm();
    if (!form) return;

    forwarderFormMode = 'edit';
    editingForwarderId = fw.id;
    setForwarderModalMode();

    form.elements.code.value = fw.code || '';
    form.elements.name.value = fw.name || '';
    form.elements.contactNo.value = fw.contactNo || '';
    form.elements.contactPerson.value = fw.contactPerson || '';
    form.elements.address.value = fw.address || '';

    toggleModal('add-forwarder-modal', true);
}

window.openDeleteModal = function(id) {
    showConfirmModal('Delete Forwarder', 'This action cannot be undone. Proceed?', async () => {
        try {
            const response = await fetch(FORWARDER_ENDPOINTS.delete(id), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                }
            });
            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message || 'Failed to delete forwarder');
            }

            // If current page becomes empty after deletion, go to previous page
            const newPage = forwarders.length <= 1 && currentPage > 1 ? currentPage - 1 : currentPage;
            await fetchForwarders(newPage);
            showSuccessModal('Deleted', 'Forwarder removed successfully.');
        } catch (error) {
            console.error('Error deleting forwarder:', error);
            alert(error.message || 'Failed to delete forwarder');
        }
    });
}

function renderLedgerTable() {
    const tbody = document.getElementById('ledger-tbody');
    if (!tbody) return;

    tbody.innerHTML = filteredLedger.map(l => `
        <tr>
            <td class="py-3 px-4">${displayValue(l.date)}</td>
            <td class="py-3 px-4 font-bold">${displayValue(l.code)}</td>
            <td class="py-3 px-4">${displayValue(l.module)}</td>
            <td class="py-3 px-4">${displayValue(l.name)}</td>
            <td class="py-3 px-4">${displayValue(l.title)}</td>
            <td class="py-3 px-4 text-right text-red-600 font-bold">${l.debit > 0 ? l.debit.toLocaleString(undefined, {minimumFractionDigits: 2}) : '-'}</td>
            <td class="py-3 px-4 text-right text-emerald-600 font-bold">${l.credit > 0 ? l.credit.toLocaleString(undefined, {minimumFractionDigits: 2}) : '-'}</td>
        </tr>
    `).join('');

    const totalDebit = filteredLedger.reduce((s, x) => s + x.debit, 0);
    const totalCredit = filteredLedger.reduce((s, x) => s + x.credit, 0);

    document.getElementById('ledger-total-count').textContent = filteredLedger.length;
    document.getElementById('ledger-total-debit').textContent = totalDebit.toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById('ledger-total-credit').textContent = totalCredit.toLocaleString(undefined, {minimumFractionDigits: 2});
}

window.filterLedgerTable = function() {
    const inputs = document.querySelectorAll('.ledger-search');
    const filters = Array.from(inputs).map(i => i.value.toLowerCase());

    filteredLedger = ledgerData.filter(l => (
        safeString(l.date).toLowerCase().includes(filters[0]) &&
        safeString(l.code).toLowerCase().includes(filters[1]) &&
        safeString(l.module).toLowerCase().includes(filters[2]) &&
        safeString(l.name).toLowerCase().includes(filters[3]) &&
        safeString(l.title).toLowerCase().includes(filters[4]) &&
        safeString(l.debit).includes(filters[5]) &&
        safeString(l.credit).includes(filters[6])
    ));
    renderLedgerTable();
}

window.filterLedgerByDate = function() {
    const from = document.getElementById('ledger-from').value;
    const to = document.getElementById('ledger-to').value;

    if (!from || !to) return;

    filteredLedger = ledgerData.filter(l => {
        const date = new Date(l.date);
        return date >= new Date(from) && date <= new Date(to);
    });
    renderLedgerTable();
}

// Global Feedback Modals
window.showConfirmModal = function(title, message, callback) {
    document.getElementById('confirm-title').textContent = title;
    document.getElementById('confirm-message').textContent = message;
    const actionBtn = document.getElementById('confirm-action-btn');
    actionBtn.onclick = async () => {
        actionBtn.disabled = true;
        try {
            await callback();
            closeConfirmModal();
        } finally {
            actionBtn.disabled = false;
        }
    };
    toggleModal('confirm-modal', true);
}

window.closeConfirmModal = function() {
    toggleModal('confirm-modal', false);
}

window.showSuccessModal = function(title, message) {
    document.getElementById('success-title').textContent = title;
    document.getElementById('success-message').textContent = message;
    toggleModal('success-modal', true);
}
