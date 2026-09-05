/**
 * Customer Master List Client Logic
 */

let customers = [];
let ledgerData = [];
let currentView = 'table'; // 'table' or 'card'
let currentActiveViewTab = 'purchase';
let currentActiveDetailTab = 'info';
let currentPage = 1;
let perPage = 50;
let pagination = null;
let searchValues = {};
let generalSearch = '';
let currentCustomerHistoryId = null;
let currentCustomerNotesOriginal = '';
let customerNotesSaveRequest = 0;

let currentWizardStep = 1;
let customerFormMode = 'create';
let editingCustomerId = null;
const CUSTOMER_ENDPOINTS = (() => {
    const r = window.customerRoutes || {
        data: '/admin/masterlist/customer/data',
        create: '/admin/masterlist/customer/create',
        update: '/admin/masterlist/customer/update/:id',
        delete: '/admin/masterlist/customer/delete/:id'
    };
    return {
        data: r.data,
        create: r.create,
        update: (id) => r.update.replace(':id', id),
        delete: (id) => r.delete.replace(':id', id),
        purchaseHistory: (id) => (r.purchaseHistory || '/admin/masterlist/customer/purchase-history/:id').replace(':id', id),
        paymentHistory: (id) => (r.paymentHistory || '/admin/masterlist/customer/payment-history/:id').replace(':id', id),
        paymentHistoryDetail: (id, salesOrderId) => (r.paymentHistoryDetail || '/admin/masterlist/customer/payment-history/:id/invoice/:salesOrderId')
            .replace(':id', id)
            .replace(':salesOrderId', salesOrderId),
        notes: (id) => (r.notes || '/admin/masterlist/customer/notes/:id').replace(':id', id)
    };
})();

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

function formatMoney(value) {
    const number = Number(value || 0);
    return number.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function paymentStatusBadge(status) {
    const value = safeString(status || 'Unpaid').trim() || 'Unpaid';
    const key = value.toLowerCase();
    const classes = key === 'paid'
        ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
        : (key === 'partial' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-red-50 text-red-700 border-red-200');
    return `<span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[8px] font-black uppercase tracking-widest ${classes}">${escapeHtml(value)}</span>`;
}

function setInputValue(id, value) {
    const input = document.getElementById(id);
    if (input) input.value = value ?? '';
}

window.addEventListener("DOMContentLoaded", () => {
    // Inject fast animation style to ensure modals are fast
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
    fetchCustomers();
});

async function fetchCustomers(page) {
    if (page !== undefined) currentPage = page;
    try {
        const params = new URLSearchParams();
        params.set('page', currentPage);
        params.set('perPage', perPage);
        for (const [col, val] of Object.entries(searchValues)) {
            if (val) params.set('search[' + col + ']', val);
        }
        if (generalSearch) params.set('search[general]', generalSearch);

        const response = await fetch(CUSTOMER_ENDPOINTS.data + '?' + params.toString(), {
            headers: {
                'Accept': 'application/json'
            }
        });
        const result = await response.json();
        customers = result.customers || [];
        pagination = result.pagination || null;
        renderView();
        renderPagination();
        updateStats(result.stats);
    } catch (error) {
        console.error('Error fetching customers:', error);
        customers = [];
        pagination = null;
        renderView();
        renderPagination();
        updateStats();
    }
}

function initializeCustomers() {
    return fetchCustomers();
}

// Wizard Logic
window.wizardMove = function(delta) {
    const nextStep = currentWizardStep + delta;
    if (nextStep < 1 || nextStep > 3) return;

    // Validation for Step 1
    if (delta > 0 && currentWizardStep === 1) {
        if (!document.getElementById('wizard-name').value) {
            alert('Please enter a customer name.');
            return;
        }
    }

    if (nextStep === 3) {
        populateReview();
    }

    // Hide all steps
    document.getElementById(`wizard-step-${currentWizardStep}`).classList.add('hidden');
    document.getElementById(`wizard-step-${nextStep}`).classList.remove('hidden');

    // Update Progress UI
    updateWizardUI(nextStep);
    currentWizardStep = nextStep;
}

function updateWizardUI(step) {
    const title = document.getElementById('wizard-step-title');
    const bar = document.getElementById('wizard-progress-bar');
    const prevBtn = document.getElementById('wizard-prev-btn');
    const nextText = document.getElementById('next-btn-text');
    const nextIcon = document.getElementById('next-btn-icon');

    // Update Step Dots
    for (let i = 1; i <= 3; i++) {
        const dot = document.getElementById(`step-dot-${i}`);
        const span = dot.nextElementSibling;
        if (dot && span) {
            if (i <= step) {
                dot.classList.add('bg-maroon', 'text-white');
                dot.classList.remove('bg-slate-100', 'text-slate-400');
                span.classList.add('text-maroon');
                span.classList.remove('text-slate-400');
            } else {
                dot.classList.remove('bg-maroon', 'text-white');
                dot.classList.add('bg-slate-100', 'text-slate-400');
                span.classList.remove('text-maroon');
                span.classList.add('text-slate-400');
            }
        }
    }

    // Update Bar
    if (bar) bar.style.width = step === 1 ? '0%' : step === 2 ? '50%' : '100%';

    // Update Title & Buttons
    if (step === 1) {
        if (title) title.textContent = 'Step 1: Customer Information';
        if (prevBtn) prevBtn.classList.add('invisible');
        if (nextText) nextText.textContent = 'Next Step';
        if (nextIcon) nextIcon.setAttribute('data-lucide', 'chevron-right');
    } else if (step === 2) {
        if (title) title.textContent = 'Step 2: Bank Information';
        if (prevBtn) prevBtn.classList.remove('invisible');
        if (nextText) nextText.textContent = 'Review Details';
        if (nextIcon) nextIcon.setAttribute('data-lucide', 'chevron-right');
    } else {
        if (title) title.textContent = 'Step 3: Review & Confirm';
        if (prevBtn) prevBtn.classList.remove('invisible');
        if (nextText) nextText.textContent = customerFormMode === 'edit' ? 'Save Changes' : 'Finalize Registration';
        if (nextIcon) nextIcon.setAttribute('data-lucide', 'check');
    }

    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function populateReview() {
    const fields = {
        name: ['wizard-name', 'review-name'],
        type: ['wizard-type', 'review-type'],
        contactNo: ['wizard-contactNo', 'review-contact'],
        contactPerson: ['wizard-contactPerson', 'review-person'],
        address: ['wizard-address', 'review-address'],
        bankName: ['wizard-bankName', 'review-bankName'],
        bankAccNo: ['wizard-bankAccNo', 'review-bankAcc'],
        bankCode: ['wizard-bankCode', 'review-bankCode'],
        bankPerson: ['wizard-bankPerson', 'review-bankPerson'],
        terms: ['wizard-terms', 'review-terms']
    };

    Object.values(fields).forEach(([inputId, reviewId]) => {
        const input = document.getElementById(inputId);
        const review = document.getElementById(reviewId);
        if (input && review) review.textContent = input.value || '---';
    });
}

window.handleWizardSubmit = function(event) {
    event.preventDefault();
    window.handleWizardNext();
}

window.handleWizardNext = function() {
    if (currentWizardStep < 3) {
        wizardMove(1);
    } else {
        submitCustomerForm();
    }
}

async function submitCustomerForm() {
    const isEdit = customerFormMode === 'edit';
    const title = isEdit ? 'Edit Customer' : 'Add Customer';
    const message = isEdit
        ? 'Are you sure you want to save this customer information?'
        : 'Are you sure you want to register this customer?';

    showConfirmModal(title, message, async () => {
        const form = document.getElementById('add-customer-form');
        const formData = new FormData(form);
        const endpoint = isEdit ? CUSTOMER_ENDPOINTS.update(editingCustomerId) : CUSTOMER_ENDPOINTS.create;

        if (isEdit && !editingCustomerId) {
            alert('No customer selected for editing.');
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
                throw new Error(result.message || (isEdit ? 'Failed to update customer' : 'Failed to register customer'));
            }

            await fetchCustomers();
            toggleModal('add-customer-modal', false);
            resetCustomerFormState();
            showSuccessModal(isEdit ? 'Updated!' : 'Registered!', isEdit ? 'Customer details have been saved.' : 'Customer has been added to the database.');
        } catch (error) {
            console.error(isEdit ? 'Error updating customer:' : 'Error registering customer:', error);
            alert(error.message || (isEdit ? 'Failed to update customer' : 'Failed to register customer'));
        }
    });
}

function resetWizard(resetForm = true) {
    currentWizardStep = 1;
    const form = document.getElementById('add-customer-form');
    if (form && resetForm) form.reset();
    for (let i = 1; i <= 3; i++) {
        const stepDiv = document.getElementById(`wizard-step-${i}`);
        if (stepDiv) stepDiv.classList.toggle('hidden', i !== 1);
    }
    updateWizardUI(1);
}

function resetCustomerFormState() {
    customerFormMode = 'create';
    editingCustomerId = null;
    setCustomerModalMode();
    resetWizard(true);
}

function setCustomerModalMode() {
    const modalTitle = document.getElementById('customer-modal-title');
    if (modalTitle) {
        modalTitle.textContent = customerFormMode === 'edit' ? 'Edit Customer' : 'Register New Customer';
    }
    updateWizardUI(currentWizardStep);
}

window.openAddCustomerModal = function() {
    customerFormMode = 'create';
    editingCustomerId = null;
    setCustomerModalMode();
    resetWizard(true);
    toggleModal('add-customer-modal', true);
}

// Global Modals
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

window.openDeleteModal = function(id) {
    showConfirmModal('Delete Customer', 'This action cannot be undone. Are you sure?', async () => {
        try {
            const response = await fetch(CUSTOMER_ENDPOINTS.delete(id), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                }
            });
            const result = await response.json();
            if (!result.success) {
                throw new Error(result.message || 'Failed to delete customer');
            }

            await fetchCustomers();
            showSuccessModal('Deleted', 'Customer record has been removed.');
        } catch (error) {
            console.error('Error deleting customer:', error);
            alert(error.message || 'Failed to delete customer');
        }
    });
}

window.openEditModal = function(id) {
    const customer = customers.find(c => Number(c.id) === Number(id));
    if (!customer) return;

    const bank = customer.bank || {};
    customerFormMode = 'edit';
    editingCustomerId = customer.id;
    resetWizard(true);
    setCustomerModalMode();

    setInputValue('wizard-name', customer.name);
    setInputValue('wizard-type', customer.type || 'Regular');
    setInputValue('wizard-contactNo', customer.contactNo);
    setInputValue('wizard-contactPerson', customer.contactPerson);
    setInputValue('wizard-address', customer.address);
    setInputValue('wizard-tin', customer.tin);
    setInputValue('wizard-remarks', customer.pricingRemarks);
    setInputValue('wizard-terms', customer.terms);
    setInputValue('wizard-bankCode', bank.code);
    setInputValue('wizard-bankAccNo', bank.accNo);
    setInputValue('wizard-bankName', bank.name);
    setInputValue('wizard-bankContact', bank.contactNo);
    setInputValue('wizard-bankPerson', bank.contactPerson);
    setInputValue('wizard-bankAddress', bank.address);

    toggleModal('add-customer-modal', true);
}

function updateStats(stats = null) {
    document.getElementById('stat-total-customers').textContent = stats ? stats.total : customers.length;
    document.getElementById('stat-total-regular').textContent = stats ? stats.regular : customers.filter(c => c.type === 'Regular').length;
    document.getElementById('stat-total-b2b').textContent = stats ? stats.b2b : customers.filter(c => c.type === 'B2B Customer').length;
    document.getElementById('stat-total-casual').textContent = stats ? stats.casual : customers.filter(c => c.type === 'Casual').length;
}

window.toggleView = function(view) {
    currentView = view;
    const tableBtn = document.getElementById('view-table-btn');
    const cardBtn = document.getElementById('view-card-btn');
    
    if (view === 'table') {
        tableBtn.classList.add('bg-maroon', 'text-white');
        tableBtn.classList.remove('bg-white', 'text-slate-600');
        cardBtn.classList.add('bg-white', 'text-slate-600');
        cardBtn.classList.remove('bg-maroon', 'text-white');
        perPage = 50;
    } else {
        cardBtn.classList.add('bg-maroon', 'text-white');
        cardBtn.classList.remove('bg-white', 'text-slate-600');
        tableBtn.classList.add('bg-white', 'text-slate-600');
        tableBtn.classList.remove('bg-maroon', 'text-white');
        perPage = 12;
    }
    currentPage = 1;
    fetchCustomers(1);
}

function renderView() {
    const tableContainer = document.getElementById('customer-table-container');
    const cardContainer = document.getElementById('customer-card-container');
    
    if (currentView === 'table') {
        tableContainer.classList.remove('hidden');
        cardContainer.classList.add('hidden');
        renderTable();
    } else {
        tableContainer.classList.add('hidden');
        cardContainer.classList.remove('hidden');
        renderCards();
    }
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function renderTable() {
    const tbody = document.getElementById('customer-tbody');
    const data = customers;

    if (!data.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="py-16 text-center text-slate-400 italic">
                    <div class="flex flex-col items-center">
                        <i data-lucide="search-x" class="w-10 h-10 mb-2 opacity-20"></i>
                        No customers found
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = data.map(c => {
        const id = Number(c.id);
        return `
        <tr onclick="openViewModal(${id})" class="hover:bg-slate-50 transition-colors border-b border-slate-100 cursor-pointer">
            <td class="py-4 px-4 font-semibold text-slate-700">${displayValue(c.name)}</td>
            <td class="py-4 px-4 text-slate-600">${displayValue(c.contactNo)}</td>
            <td class="py-4 px-4 text-slate-600">${displayValue(c.contactPerson)}</td>
            <td class="py-4 px-4 text-slate-600 max-w-[200px] truncate">${displayValue(c.address)}</td>
            <td class="py-4 px-4">
                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase ${getTypeClass(c.type)}">
                    ${displayValue(c.type)}
                </span>
            </td>
            <td class="py-4 px-4 text-center" onclick="event.stopPropagation()">
                <div class="flex items-center justify-center space-x-2">
                    <button type="button" onclick="event.stopPropagation(); openViewModal(${id})" class="p-2 hover:bg-slate-100 text-slate-600 rounded-lg transition-colors" title="View"><i data-lucide="eye" class="w-4 h-4"></i></button>
                    <button type="button" onclick="event.stopPropagation(); openEditModal(${id})" class="p-2 hover:bg-blue-50 text-blue-600 rounded-lg transition-colors" title="Edit"><i data-lucide="edit-3" class="w-4 h-4"></i></button>
                    <button type="button" onclick="event.stopPropagation(); openDeleteModal(${id})" class="p-2 hover:bg-red-50 text-red-600 rounded-lg transition-colors" title="Delete"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                </div>
            </td>
        </tr>
    `;
    }).join('');
}

function renderCards() {
    const grid = document.getElementById('customer-card-grid');
    const data = customers;

    if (!data.length) {
        grid.innerHTML = `
            <div class="col-span-full py-16 text-center bg-white rounded-3xl border border-dashed border-slate-200">
                <i data-lucide="search-x" class="w-12 h-12 text-slate-300 mx-auto mb-4"></i>
                <p class="text-slate-400 font-bold uppercase tracking-widest text-xs">No customers found</p>
            </div>
        `;
        return;
    }
    
    grid.innerHTML = data.map(c => {
        const id = Number(c.id);
        return `
        <div onclick="openViewModal(${id})" class="customer-card bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex flex-col space-y-4 cursor-pointer">
            <div class="flex justify-between items-start">
                <div class="w-12 h-12 rounded-xl bg-maroon-gradient flex items-center justify-center">
                    <i data-lucide="user" class="w-6 h-6 text-gold"></i>
                </div>
                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase ${getTypeClass(c.type)}">
                    ${displayValue(c.type)}
                </span>
            </div>
            <div>
                <h4 class="font-extrabold text-slate-800 truncate">${displayValue(c.name)}</h4>
                <p class="text-xs text-slate-400 font-bold">${displayValue(c.contactPerson)}</p>
            </div>
            <div class="space-y-2 text-xs text-slate-600 pt-2 border-t border-slate-50">
                <div class="flex items-center gap-2"><i data-lucide="phone" class="w-3 h-3"></i> ${displayValue(c.contactNo)}</div>
                <div class="flex items-center gap-2 truncate"><i data-lucide="map-pin" class="w-3 h-3"></i> ${displayValue(c.address)}</div>
            </div>
            <div class="flex gap-2 pt-2">
                <button type="button" onclick="event.stopPropagation(); openViewModal(${id})" class="flex-1 py-2 bg-slate-50 hover:bg-slate-100 text-slate-600 rounded-xl text-[10px] font-bold transition-all">VIEW</button>
                <button type="button" onclick="event.stopPropagation(); openEditModal(${id})" class="flex-1 py-2 bg-blue-50 hover:bg-blue-100 text-blue-600 rounded-xl text-[10px] font-bold transition-all">EDIT</button>
                <button type="button" onclick="event.stopPropagation(); openDeleteModal(${id})" class="flex-1 py-2 bg-red-50 hover:bg-red-100 text-red-600 rounded-xl text-[10px] font-bold transition-all">DELETE</button>
            </div>
        </div>
    `;
    }).join('');
}

function getTypeClass(type) {
    if (type === 'Regular') return 'tag-regular';
    if (type === 'B2B Customer') return 'tag-b2b';
    return 'tag-casual';
}

window.toggleModal = function(id, open = true) {
    const modal = document.getElementById(id);
    if (!modal) return;
    if (open) {
        modal.classList.remove('hidden');
        // Apply animation to inner modal element
        const innerModal = modal.querySelector('.modal-animate-in');
        if (innerModal) {
            innerModal.style.animation = 'modalFadeIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards';
        }
        // Force animation by triggering reflow
        void modal.offsetWidth;
    } else {
        modal.classList.add('hidden');
    }
}

window.openViewModal = async function(id) {
    const c = customers.find(x => Number(x.id) === Number(id));
    if (!c) return;
    const bank = c.bank || {};
    currentCustomerHistoryId = Number(id);

    // Populate Side Info
    document.getElementById('view-cust-name').textContent = safeString(c.name) || 'N/A';
    document.getElementById('view-cust-contact').textContent = safeString(c.contactNo) || 'N/A';
    document.getElementById('view-cust-person').textContent = safeString(c.contactPerson) || 'N/A';
    document.getElementById('view-cust-address').textContent = safeString(c.address) || 'N/A';
    document.getElementById('view-cust-tin').textContent = safeString(c.tin) || 'N/A';
    document.getElementById('view-cust-remarks').textContent = safeString(c.pricingRemarks) || 'N/A';
    document.getElementById('view-cust-terms').textContent = safeString(c.terms) || 'N/A';
    document.getElementById('view-cust-type').textContent = safeString(c.type) || 'N/A';

    // Bank Info
    document.getElementById('view-bank-code').textContent = safeString(bank.code) || 'N/A';
    document.getElementById('view-bank-acc').textContent = safeString(bank.accNo) || 'N/A';
    document.getElementById('view-bank-name').textContent = safeString(bank.name) || 'N/A';
    document.getElementById('view-bank-contact').textContent = safeString(bank.contactNo) || 'N/A';
    document.getElementById('view-bank-person').textContent = safeString(bank.contactPerson) || 'N/A';
    document.getElementById('view-bank-address').textContent = safeString(bank.address) || 'N/A';

    switchDetailTab('info');
    switchViewTab('purchase');

    const notesTextarea = document.getElementById('customer-notes-textarea');
    if (notesTextarea) {
        notesTextarea.value = '';
        notesTextarea.dataset.loadedFor = '';
        currentCustomerNotesOriginal = '';
        setCustomerNotesStatus('Open Notes tab to load', 'idle');
    }

    window._purchaseHistoryData = [];
    ledgerData = [];
    const purchaseBody = document.getElementById('purchase-history-tbody');
    if (purchaseBody) purchaseBody.innerHTML = '<tr><td colspan="12" class="py-8 text-center text-slate-300 italic">Loading Sales Order purchase history...</td></tr>';
    const ledgerBody = document.getElementById('ledger-tbody');
    if (ledgerBody) ledgerBody.innerHTML = '<tr><td colspan="7" class="py-8 text-center text-slate-300 italic">Loading customer payment history...</td></tr>';

    // Open immediately; the two real database histories load in parallel.
    toggleModal('view-customer-modal', true);

    const purchasePromise = fetch(CUSTOMER_ENDPOINTS.purchaseHistory(id), { headers: { 'Accept': 'application/json' } })
        .then(async res => {
            const body = await res.json();
            if (!res.ok || !body.success) throw new Error(body.message || 'Unable to load purchase history.');
            return body.purchases || [];
        });
    const ledgerPromise = fetch(CUSTOMER_ENDPOINTS.paymentHistory(id), { headers: { 'Accept': 'application/json' } })
        .then(async res => {
            const body = await res.json();
            if (!res.ok || !body.success) throw new Error(body.message || 'Unable to load payment history.');
            return body.ledger || [];
        });

    const [purchaseResult, ledgerResult] = await Promise.allSettled([purchasePromise, ledgerPromise]);

    if (purchaseResult.status === 'fulfilled') {
        window._purchaseHistoryData = purchaseResult.value;
    } else {
        console.error('Error fetching purchase history:', purchaseResult.reason);
        window._purchaseHistoryData = [];
    }

    if (ledgerResult.status === 'fulfilled') {
        ledgerData = ledgerResult.value;
    } else {
        console.error('Error fetching customer payment history:', ledgerResult.reason);
        ledgerData = [];
    }

    renderPurchaseHistory();
    renderLedger();
}

window.switchDetailTab = function(tab) {
    currentActiveDetailTab = tab;
    const infoTab = document.getElementById('detail-tab-info');
    const bankTab = document.getElementById('detail-tab-bank');
    const infoContent = document.getElementById('detail-content-info');
    const bankContent = document.getElementById('detail-content-bank');

    if (tab === 'info') {
        infoTab.classList.add('bg-maroon', 'text-white');
        bankTab.classList.remove('bg-maroon', 'text-white');
        infoContent.classList.remove('hidden');
        bankContent.classList.add('hidden');
    } else {
        bankTab.classList.add('bg-maroon', 'text-white');
        infoTab.classList.remove('bg-maroon', 'text-white');
        bankContent.classList.remove('hidden');
        infoContent.classList.add('hidden');
    }
}

window.switchViewTab = function(tab) {
    currentActiveViewTab = tab;
    const pBtn = document.getElementById('tab-btn-purchase');
    const lBtn = document.getElementById('tab-btn-ledger');
    const nBtn = document.getElementById('tab-btn-notes');
    const pCont = document.getElementById('view-container-purchase');
    const lCont = document.getElementById('view-container-ledger');
    const nCont = document.getElementById('view-container-notes');
    const filters = document.getElementById('customer-history-filters');

    [pBtn, lBtn, nBtn].forEach(btn => {
        if (!btn) return;
        btn.classList.remove('border-maroon', 'text-maroon');
        btn.classList.add('border-transparent', 'text-slate-400');
    });
    [pCont, lCont, nCont].forEach(cont => cont?.classList.add('hidden'));

    if (tab === 'notes' && nBtn && nCont) {
        nBtn.classList.add('border-maroon', 'text-maroon');
        nBtn.classList.remove('border-transparent', 'text-slate-400');
        nCont.classList.remove('hidden');
        filters?.classList.add('hidden');

        const textarea = document.getElementById('customer-notes-textarea');
        const loadedFor = Number(textarea?.dataset.loadedFor || 0);
        if (currentCustomerHistoryId && loadedFor !== Number(currentCustomerHistoryId)) {
            loadCustomerNotes(currentCustomerHistoryId);
        }
        return;
    }

    filters?.classList.remove('hidden');
    if (tab === 'ledger') {
        lBtn?.classList.add('border-maroon', 'text-maroon');
        lBtn?.classList.remove('border-transparent', 'text-slate-400');
        lCont?.classList.remove('hidden');
    } else {
        pBtn?.classList.add('border-maroon', 'text-maroon');
        pBtn?.classList.remove('border-transparent', 'text-slate-400');
        pCont?.classList.remove('hidden');
    }
}

function setCustomerNotesStatus(message, state = 'idle') {
    const status = document.getElementById('customer-notes-status');
    if (!status) return;
    status.textContent = message;
    status.classList.remove('text-slate-400', 'text-amber-600', 'text-emerald-600', 'text-red-600');
    const cls = state === 'saving' ? 'text-amber-600' : state === 'saved' ? 'text-emerald-600' : state === 'error' ? 'text-red-600' : 'text-slate-400';
    status.classList.add(cls);
}

async function loadCustomerNotes(customerId) {
    const textarea = document.getElementById('customer-notes-textarea');
    if (!textarea || !customerId) return;

    textarea.disabled = true;
    setCustomerNotesStatus('Loading...', 'saving');
    try {
        const response = await fetch(CUSTOMER_ENDPOINTS.notes(customerId), {
            headers: { 'Accept': 'application/json' }
        });
        const body = await response.json();
        if (!response.ok || !body.success) throw new Error(body.message || 'Unable to load customer notes.');

        const notes = safeString(body.notes);
        textarea.value = notes;
        textarea.dataset.loadedFor = String(customerId);
        currentCustomerNotesOriginal = notes;
        setCustomerNotesStatus('Saved', 'saved');
    } catch (error) {
        console.error('Error loading customer notes:', error);
        textarea.dataset.loadedFor = String(customerId);
        setCustomerNotesStatus('Load failed', 'error');
    } finally {
        textarea.disabled = false;
    }
}

window.saveCustomerNotes = async function() {
    const textarea = document.getElementById('customer-notes-textarea');
    const customerId = Number(currentCustomerHistoryId || 0);
    if (!textarea || !customerId || textarea.disabled) return;

    const loadedFor = Number(textarea.dataset.loadedFor || 0);
    if (loadedFor !== customerId) return;

    const notes = textarea.value;
    if (notes === currentCustomerNotesOriginal) {
        setCustomerNotesStatus('Saved', 'saved');
        return;
    }

    const requestNo = ++customerNotesSaveRequest;
    setCustomerNotesStatus('Saving...', 'saving');
    try {
        const response = await fetch(CUSTOMER_ENDPOINTS.notes(customerId), {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            },
            body: JSON.stringify({ notes })
        });
        const body = await response.json();
        if (!response.ok || !body.success) throw new Error(body.message || 'Unable to save customer notes.');

        if (requestNo === customerNotesSaveRequest) {
            currentCustomerNotesOriginal = safeString(body.notes ?? notes);
            textarea.value = currentCustomerNotesOriginal;
            setCustomerNotesStatus('Saved automatically', 'saved');
        }
    } catch (error) {
        console.error('Error saving customer notes:', error);
        if (requestNo === customerNotesSaveRequest) setCustomerNotesStatus('Save failed', 'error');
    }
}

function renderPurchaseHistory(data) {
    const tbody = document.getElementById('purchase-history-tbody');
    if (!tbody) return;
    const items = data || window._purchaseHistoryData || [];
    if (items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="12" class="py-8 text-center text-slate-300 italic">No Sales Order purchase history found.</td></tr>';
        const totalEl = document.getElementById('purchase-total-amount');
        if (totalEl) totalEl.textContent = '0.00';
        return;
    }

    tbody.innerHTML = items.map(h => {
        const discountText = Number(h.additional_discount || 0) > 0
            ? `${Number(h.disc || 0).toFixed(2)}% + ${Number(h.additional_discount || 0).toFixed(2)}%`
            : `${Number(h.disc || 0).toFixed(2)}%`;
        return `
        <tr data-purchase-row>
            <td class="py-3 px-4 font-bold text-maroon">${escapeHtml(h.order_no || h.onhand || '---')}</td>
            <td class="py-3 px-4 whitespace-nowrap">${escapeHtml(h.date || '---')}</td>
            <td class="py-3 px-4 font-bold">${escapeHtml(h.inv || '---')}</td>
            <td class="py-3 px-4">${escapeHtml(h.code || '---')}</td>
            <td class="py-3 px-4">${escapeHtml(h.desc || '---')}</td>
            <td class="py-3 px-4 text-center">${Number(h.qty || 0)}</td>
            <td class="py-3 px-4 text-center">${Number(h.qplus || 0)}</td>
            <td class="py-3 px-4">${escapeHtml(h.unit || 'PCS')}</td>
            <td class="py-3 px-4 text-right">${formatMoney(h.price)}</td>
            <td class="py-3 px-4 text-center whitespace-nowrap">${escapeHtml(discountText)}</td>
            <td class="py-3 px-4 text-right font-bold">${formatMoney(h.total)}</td>
            <td class="py-3 px-4 text-slate-500">${escapeHtml(h.particulars || '---')}</td>
        </tr>`;
    }).join('');

    const totalEl = document.getElementById('purchase-total-amount');
    if (totalEl) totalEl.textContent = formatMoney(items.reduce((s, x) => s + Number(x.total || 0), 0));
}

function renderLedger(data = ledgerData) {
    const tbody = document.getElementById('ledger-tbody');
    if (!tbody) return;
    const rows = Array.isArray(data) ? data : [];
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="py-8 text-center text-slate-300 italic">No invoice/payment history found for this customer.</td></tr>';
    } else {
        tbody.innerHTML = rows.map(l => {
            const payments = safeString(l.payment_numbers).trim();
            const latestPayment = safeString(l.latest_payment_date).trim();
            const settlementParts = [];
            if (payments) settlementParts.push(`Payment: ${payments}`);
            if (Number(l.return_amount || 0) > 0) settlementParts.push(`Returns: ${formatMoney(l.return_amount)}`);
            if (Number(l.adjustment || 0) > Number(l.return_amount || 0) + 0.005) settlementParts.push(`Adjustment: ${formatMoney(l.adjustment)}`);
            if (latestPayment) settlementParts.push(`Last Paid: ${latestPayment}`);
            const settlementText = settlementParts.length ? settlementParts.join(' • ') : 'No payment posted';
            return `
            <tr data-ledger-row>
                <td class="py-3 px-4 whitespace-nowrap">${escapeHtml(l.date || '---')}</td>
                <td class="py-3 px-4 font-black text-maroon">${escapeHtml(l.invoice_no || l.code || '---')}</td>
                <td class="py-3 px-4">${escapeHtml(l.module || 'Sales Invoice')}</td>
                <td class="py-3 px-4">
                    <div class="flex items-center gap-2 flex-wrap">${paymentStatusBadge(l.payment_status || l.title)}
                        <button type="button" onclick="window.openCustomerInvoiceLedger(${Number(l.sales_order_id || 0)})" class="px-2 py-1 rounded-lg bg-maroon text-white text-[8px] font-black uppercase tracking-widest hover:bg-maroon-800">View Ledger</button>
                    </div>
                    <div class="mt-1 text-[9px] font-semibold text-slate-400">${escapeHtml(settlementText)}</div>
                </td>
                <td class="py-3 px-4 text-right text-red-600 font-bold">${formatMoney(l.debit)}</td>
                <td class="py-3 px-4 text-right text-emerald-600 font-bold">${formatMoney(l.credit)}</td>
                <td class="py-3 px-4 text-right font-black ${Number(l.balance || 0) > 0.005 ? 'text-red-600' : 'text-emerald-700'}">${formatMoney(l.balance)}</td>
            </tr>`;
        }).join('');
    }

    const totalDebit = rows.reduce((s, x) => s + Number(x.debit || 0), 0);
    const totalCredit = rows.reduce((s, x) => s + Number(x.credit || 0), 0);
    const totalBalance = rows.reduce((s, x) => s + Number(x.balance || 0), 0);

    const debitEl = document.getElementById('ledger-total-debit');
    const creditEl = document.getElementById('ledger-total-credit');
    const balanceEl = document.getElementById('ledger-total-balance');
    if (debitEl) debitEl.textContent = formatMoney(totalDebit);
    if (creditEl) creditEl.textContent = formatMoney(totalCredit);
    if (balanceEl) balanceEl.textContent = formatMoney(totalBalance);
}

function ensureCustomerInvoiceLedgerModal() {
    let modal = document.getElementById('customer-invoice-ledger-modal');
    if (modal) return modal;

    modal = document.createElement('div');
    modal.id = 'customer-invoice-ledger-modal';
    modal.className = 'fixed inset-0 z-[1200] hidden items-center justify-center p-4';
    modal.innerHTML = `
        <div onclick="window.closeCustomerInvoiceLedger()" class="absolute inset-0 bg-slate-950/65 backdrop-blur-sm"></div>
        <div class="relative flex max-h-[92vh] w-full max-w-[1500px] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl modal-animate-in">
            <div class="bg-maroon p-5 text-white flex flex-wrap items-center gap-4">
                <div class="min-w-0 flex-1"><p class="text-[9px] font-black uppercase tracking-[0.24em] text-gold">Customer Payment History</p><h3 id="customer-ledger-detail-invoice" class="mt-1 text-lg font-black">---</h3></div>
                <div class="rounded-xl bg-white/10 px-4 py-2"><div class="text-[8px] font-black uppercase text-white/50">Invoice Date</div><div id="customer-ledger-detail-date" class="text-xs font-black">---</div></div>
                <div class="rounded-xl bg-white/10 px-4 py-2"><div class="text-[8px] font-black uppercase text-white/50">Status</div><div id="customer-ledger-detail-status" class="text-xs font-black text-gold">---</div></div>
                <div class="rounded-xl bg-white/10 px-4 py-2 text-right"><div class="text-[8px] font-black uppercase text-white/50">Balance</div><div id="customer-ledger-detail-balance" class="text-base font-black text-gold">0.00</div></div>
                <button type="button" onclick="window.closeCustomerInvoiceLedger()" class="text-white/60 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="p-4 overflow-auto custom-scrollbar">
                <div class="mb-3 grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-3"><div class="text-[8px] font-black uppercase text-slate-400">Invoice Amount</div><div id="customer-ledger-detail-amount" class="mt-1 text-sm font-black text-slate-700">0.00</div></div>
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-3"><div class="text-[8px] font-black uppercase text-slate-400">Paid Amount</div><div id="customer-ledger-detail-paid" class="mt-1 text-sm font-black text-emerald-700">0.00</div></div>
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-3"><div class="text-[8px] font-black uppercase text-slate-400">Returns</div><div id="customer-ledger-detail-return" class="mt-1 text-sm font-black text-amber-700">0.00</div></div>
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-3"><div class="text-[8px] font-black uppercase text-slate-400">Adjustment</div><div id="customer-ledger-detail-adjustment" class="mt-1 text-sm font-black text-slate-700">0.00</div></div>
                </div>
                <div class="overflow-auto rounded-xl border border-slate-100">
                    <table class="w-full min-w-[1200px] text-left text-[10px]">
                        <thead class="sticky top-0 z-10 bg-slate-50 text-slate-400 uppercase tracking-wider"><tr><th class="p-3">Invoice No.</th><th class="p-3">Product Code</th><th class="p-3">Part Number</th><th class="p-3">Transaction</th><th class="p-3">Transaction No.</th><th class="p-3 text-right">Debit</th><th class="p-3 text-right">Credit</th><th class="p-3">Remarks</th><th class="p-3">Date</th></tr></thead>
                        <tbody id="customer-invoice-ledger-detail-tbody" class="divide-y divide-slate-100"></tbody>
                    </table>
                </div>
            </div>
        </div>`;
    document.body.appendChild(modal);
    if (typeof lucide !== 'undefined') lucide.createIcons();
    return modal;
}

window.openCustomerInvoiceLedger = async function(salesOrderId) {
    const customerId = Number(currentCustomerHistoryId || 0);
    const orderId = Number(salesOrderId || 0);
    if (!customerId || !orderId) return;

    const modal = ensureCustomerInvoiceLedgerModal();
    const tbody = document.getElementById('customer-invoice-ledger-detail-tbody');
    if (tbody) tbody.innerHTML = '<tr><td colspan="9" class="p-8 text-center text-slate-400 font-bold">Loading ledger movements...</td></tr>';
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    try {
        const response = await fetch(CUSTOMER_ENDPOINTS.paymentHistoryDetail(customerId, orderId), { headers: { 'Accept': 'application/json' } });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'Unable to load invoice ledger.');
        const inv = data.invoice || {};
        document.getElementById('customer-ledger-detail-invoice').textContent = inv.invoice_no || '---';
        document.getElementById('customer-ledger-detail-date').textContent = inv.invoice_date || '---';
        document.getElementById('customer-ledger-detail-status').textContent = inv.payment_status || '---';
        document.getElementById('customer-ledger-detail-balance').textContent = formatMoney(inv.balance);
        document.getElementById('customer-ledger-detail-amount').textContent = formatMoney(inv.invoice_amount);
        document.getElementById('customer-ledger-detail-paid').textContent = formatMoney(inv.paid_amount);
        document.getElementById('customer-ledger-detail-return').textContent = formatMoney(inv.return_amount);
        document.getElementById('customer-ledger-detail-adjustment').textContent = formatMoney(inv.adjustment);

        const rows = Array.isArray(data.ledger) ? data.ledger : [];
        if (tbody) tbody.innerHTML = rows.length ? rows.map(row => {
            const paidRow = safeString(row.transaction).toUpperCase() === 'PAID';
            return `<tr class="${paidRow ? 'bg-emerald-50/60' : ''}"><td class="p-3 font-black text-maroon">${escapeHtml(row.invoice_no || '---')}</td><td class="p-3 font-black text-maroon">${escapeHtml(row.product_code || '---')}</td><td class="p-3 font-bold text-slate-600">${escapeHtml(row.part_number || '---')}</td><td class="p-3 font-black">${escapeHtml(row.transaction || '---')}</td><td class="p-3 font-bold">${escapeHtml(row.transaction_no || '---')}</td><td class="p-3 text-right font-bold">${Number(row.debit || 0) ? formatMoney(row.debit) : '—'}</td><td class="p-3 text-right font-black text-emerald-700">${Number(row.credit || 0) ? formatMoney(row.credit) : '—'}</td><td class="p-3 text-slate-500">${escapeHtml(row.remarks || '')}</td><td class="p-3 font-bold text-slate-500 whitespace-nowrap">${escapeHtml(row.date || '---')}</td></tr>`;
        }).join('') : '<tr><td colspan="9" class="p-8 text-center text-slate-400 font-bold">No ledger movements found.</td></tr>';
    } catch (error) {
        if (tbody) tbody.innerHTML = `<tr><td colspan="9" class="p-8 text-center text-red-500 font-bold">${escapeHtml(error.message || 'Failed to load ledger.')}</td></tr>`;
    }
}

window.closeCustomerInvoiceLedger = function() {
    const modal = document.getElementById('customer-invoice-ledger-modal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

window.filterPurchaseSearch = function() {
    const filters = [
        'purchase-search-onhand', 'purchase-search-date', 'purchase-search-inv', 'purchase-search-code',
        'purchase-search-desc', 'purchase-search-qty', 'purchase-search-qplus', 'purchase-search-unit',
        'purchase-search-price', 'purchase-search-disc', 'purchase-search-total', 'purchase-search-part'
    ].map(id => safeString(document.getElementById(id)?.value).trim().toLowerCase());

    const rows = Array.from(document.querySelectorAll('#purchase-history-tbody tr[data-purchase-row]'));
    rows.forEach(row => {
        const cells = Array.from(row.children);
        const show = filters.every((filter, index) => !filter || safeString(cells[index]?.textContent).toLowerCase().includes(filter));
        row.style.display = show ? '' : 'none';
    });
}

window.filterLedgerSearch = function() {
    const filters = [
        'ledger-search-date', 'ledger-search-code', 'ledger-search-module', 'ledger-search-title',
        'ledger-search-debit', 'ledger-search-credit', 'ledger-search-balance'
    ].map(id => safeString(document.getElementById(id)?.value).trim().toLowerCase());

    const rows = Array.from(document.querySelectorAll('#ledger-tbody tr[data-ledger-row]'));
    rows.forEach(row => {
        const cells = Array.from(row.children);
        const show = filters.every((filter, index) => !filter || safeString(cells[index]?.textContent).toLowerCase().includes(filter));
        row.style.display = show ? '' : 'none';
    });
}

function renderPagination() {
    const tablePagination = document.getElementById('customer-table-pagination');
    const cardPagination = document.getElementById('customer-card-pagination');
    const target = currentView === 'table' ? tablePagination : cardPagination;
    const other = currentView === 'table' ? cardPagination : tablePagination;
    if (other) other.innerHTML = '';

    if (!target) return;
    if (!pagination || pagination.last_page <= 1) {
        target.innerHTML = pagination && pagination.total > 0
            ? '<div class="text-[11px] text-slate-400 text-center py-2">Showing all ' + pagination.total + ' entries</div>'
            : '';
        return;
    }

    const p = pagination;
    let html = '<div class="flex items-center justify-between flex-wrap gap-3">';
    html += '<div class="text-[11px] text-slate-500">Page ' + p.current_page + ' of ' + p.last_page + ' (Showing ' + p.from + ' to ' + p.to + ' of ' + p.total + ' entries)</div>';
    html += '<div class="flex items-center space-x-1">';

    html += '<button onclick="goToPage(1)" class="px-2 py-1 text-[10px] rounded-lg ' + (p.current_page === 1 ? 'text-slate-300 cursor-default' : 'text-slate-600 hover:bg-slate-100') + ' font-bold" ' + (p.current_page === 1 ? 'disabled' : '') + '>&laquo;</button>';
    html += '<button onclick="goToPage(' + (p.current_page - 1) + ')" class="px-2 py-1 text-[10px] rounded-lg ' + (p.current_page === 1 ? 'text-slate-300 cursor-default' : 'text-slate-600 hover:bg-slate-100') + ' font-bold" ' + (p.current_page === 1 ? 'disabled' : '') + '>&lsaquo;</button>';

    const range = 2;
    let start = Math.max(1, p.current_page - range);
    let end = Math.min(p.last_page, p.current_page + range);
    if (start > 1) { html += '<button onclick="goToPage(1)" class="px-2 py-1 text-[10px] font-bold rounded-lg text-slate-600 hover:bg-slate-100">1</button>'; if (start > 2) html += '<span class="px-1 text-slate-300">...</span>'; }
    for (let i = start; i <= end; i++) {
        html += '<button onclick="goToPage(' + i + ')" class="px-2.5 py-1 text-[10px] font-bold rounded-lg ' + (i === p.current_page ? 'bg-maroon text-white' : 'text-slate-600 hover:bg-slate-100') + '">' + i + '</button>';
    }
    if (end < p.last_page) { if (end < p.last_page - 1) html += '<span class="px-1 text-slate-300">...</span>'; html += '<button onclick="goToPage(' + p.last_page + ')" class="px-2 py-1 text-[10px] font-bold rounded-lg text-slate-600 hover:bg-slate-100">' + p.last_page + '</button>'; }

    html += '<button onclick="goToPage(' + (p.current_page + 1) + ')" class="px-2 py-1 text-[10px] rounded-lg ' + (p.current_page === p.last_page ? 'text-slate-300 cursor-default' : 'text-slate-600 hover:bg-slate-100') + ' font-bold" ' + (p.current_page === p.last_page ? 'disabled' : '') + '>&rsaquo;</button>';
    html += '<button onclick="goToPage(' + p.last_page + ')" class="px-2 py-1 text-[10px] rounded-lg ' + (p.current_page === p.last_page ? 'text-slate-300 cursor-default' : 'text-slate-600 hover:bg-slate-100') + ' font-bold" ' + (p.current_page === p.last_page ? 'disabled' : '') + '>&raquo;</button>';

    html += '</div></div>';
    target.innerHTML = html;
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

const debouncedFilterColumnSearch = debounce(function() {
    searchValues = {};
    const name = document.getElementById('col-search-name')?.value || '';
    const contact = document.getElementById('col-search-contact')?.value || '';
    const person = document.getElementById('col-search-person')?.value || '';
    const address = document.getElementById('col-search-address')?.value || '';
    const type = document.getElementById('col-search-type')?.value || '';
    if (name) searchValues['name'] = name;
    if (contact) searchValues['contact_number'] = contact;
    if (person) searchValues['contact_person'] = person;
    if (address) searchValues['address'] = address;
    if (type) searchValues['type'] = type;
    currentPage = 1;
    fetchCustomers(1);
}, 300);

window.filterColumnSearch = function() {
    debouncedFilterColumnSearch();
};

const debouncedFilterMainSearch = debounce(function() {
    generalSearch = document.getElementById('main-search-input')?.value || '';
    currentPage = 1;
    fetchCustomers(1);
}, 300);

window.filterMainSearch = function() {
    debouncedFilterMainSearch();
};
