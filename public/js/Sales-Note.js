// Sales Note Pagination & Search State
let currentTab = 'active';
let currentPage = 1;
let totalPages = 1;
let paginationData = {};
let searchTimeout;

function debounce(func, delay = 300) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(func, delay);
}



/**
 * Keep the edit Actual Qty column in sync after rows are added, hidden, or removed.
 * Exposed globally because both the shared Sales-Note.js handlers and the
 * role-specific Blade edit handlers call it.
 */
window.syncEditActualQtyColumnVisibility = window.syncEditActualQtyColumnVisibility || function () {
    const rows = Array.from(document.querySelectorAll('#edit-selected-items-tbody tr'))
        .filter(row => row.style.display !== 'none');

    const shouldShow = rows.some(row => {
        const inputValue = row.querySelector('.item-actual-qty')?.value;
        const attributeValue = row.getAttribute('data-actual-qty');
        return (parseFloat(inputValue ?? attributeValue ?? 0) || 0) > 0;
    });

    document.querySelectorAll('#edit-sales-step-3 .edit-actual-qty-col').forEach(element => {
        element.classList.toggle('hidden', !shouldShow);
    });

    const filterTail = document.getElementById('edit-items-filter-tail');
    if (filterTail) {
        filterTail.colSpan = shouldShow ? 8 : 7;
    }
};

function todayDateString() {
    const now = new Date();
    const local = new Date(now.getTime() - now.getTimezoneOffset() * 60000);
    return local.toISOString().slice(0, 10);
}

document.addEventListener('DOMContentLoaded', () => {
    if (typeof lucide !== 'undefined') { lucide.createIcons(); }

    const dateInput = document.getElementById('new-sales-date');
    if (dateInput) {
        dateInput.value = todayDateString();
    }

    document.getElementById('select-all-step2')?.addEventListener('change', function() {
        document.querySelectorAll('#selected-items-tbody input[type="checkbox"]').forEach(cb => cb.checked = this.checked);
    });

    // Setup search input listeners with debounce
    const searchInputs = document.querySelectorAll('[id^="col-search-"]');
    searchInputs.forEach(input => {
        input.addEventListener('keyup', () => {
            debounce(() => loadSalesNotesByTab(), 300);
        });
    });
});

window.toggleModal = function(modalId, show) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    if (show) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';

        // Auto-fill Order Date if empty when opening add-sales-note-modal
        if (modalId === 'add-sales-note-modal') {
            const dateInput = modal.querySelector('#new-sales-date');
            if (dateInput && !dateInput.value) {
                dateInput.value = todayDateString();
            }
        }
    } else {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        const anyVisible = document.querySelector('.fixed.inset-0:not(.hidden)');
        if (!anyVisible) document.body.style.overflow = 'auto';
    }
};

window.goToStep = function(step) {
    const reviewStep = window._totalSteps || 3;
    const hasPartial = window._hasPartialNotes;
    
    // Map logical step to content div: in 3-step mode, step 2 = sales-step-3, step 3 = sales-step-4
    const contentStep = hasPartial ? step : (step >= 2 ? step + 1 : step);
    
    // Load partial notes when entering step 2 (if 4-step mode)
    if (step === 2 && hasPartial) {
        loadPartialNotes();
    }
    
    // Add converted items to selected items when moving from partial step to select items
    if (hasPartial && step === 3 && window._convertedItems && window._convertedItems.length > 0) {
        addConvertedItemsToSelection();
    }

    document.querySelectorAll('.sales-step-content').forEach(el => el.classList.add('hidden'));
    const target = document.getElementById(`sales-step-${contentStep}`);
    if (target) {
        target.classList.remove('hidden');
        updateStepIndicators(step);
        if (step === reviewStep) populateReviewData();
    }
};

function updateStepIndicators(currentStep) {
    const totalSteps = window._totalSteps || 3;
    const steps = totalSteps === 4 ? [1, 2, 3, 4] : [1, 2, 3];
    const widths = totalSteps === 4 ? ['0%', '33.33%', '66.66%', '100%'] : ['0%', '50%', '100%'];
    
    steps.forEach(step => {
        const el = document.getElementById(`step-indicator-${step}`);
        if (!el) return;
        el.classList.remove('active', 'completed', 'pending');
        if (step < currentStep) { 
            el.classList.add('completed'); 
            el.innerHTML = '<i data-lucide="check" class="w-4 h-4 text-white"></i>'; 
        }
        else if (step === currentStep) { 
            el.classList.add('active'); 
            el.innerText = step; 
        }
        else { 
            el.classList.add('pending'); 
            el.innerText = step; 
        }
    });
    
    const line = document.getElementById('step-progress-line');
    if (line) line.style.width = widths[currentStep - 1];
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

window.filterTable = function(tbodyId, colIndex, value) {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return;
    const filter = value.toLowerCase();
    Array.from(tbody.getElementsByTagName('tr')).forEach(row => {
        const cell = row.getElementsByTagName('td')[colIndex];
        if (cell) row.style.display = cell.textContent.toLowerCase().includes(filter) ? '' : 'none';
    });
};

window.loadCustomers = async function(page) {
    const tbody = document.getElementById('customer-list-tbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="5" class="p-4 text-center text-slate-400">Loading customers...</td></tr>';
    toggleModal('customer-search-modal', true);
    try {
        const p = parseInt(page) || 1;
        window._customerPage = p;
        window._customerPerPage = 50;

        const url = new URL(window.salesRoutes?.customersUrl || '/admin/sales/sales-note/customers', window.location.origin);
        url.searchParams.set('page', String(window._customerPage));
        url.searchParams.set('perPage', String(window._customerPerPage));
        const filters = getCustomerFiltersFromInputs();
        window._customerFilters = filters;
        Object.entries(filters).forEach(([k, v]) => {
            if (!v) return;
            url.searchParams.set(`search[${k}]`, v);
        });

        const res = await fetch(url.toString());
        const result = await res.json();
        if (result.success && result.customers.length > 0) {
            const pagination = result.pagination || {};
            window._customerPage = pagination.current_page || window._customerPage || 1;
            window._customerTotalPages = pagination.last_page || 1;
            updateCustomerPaginationControls();

            const esc = (v) => String(v ?? '').replace(/'/g, "\\'");
            tbody.innerHTML = result.customers.map(c => `
                <tr onclick="selectCustomer({id: ${c.id}, code: '${esc(c.code)}', name: '${esc(c.name)}', contact: '${esc(c.contact_number)}', person: '${esc(c.contact_person)}', address: '${esc(c.address)}'})" class="hover:bg-slate-50 cursor-pointer transition-colors">
                    <td class="p-3 px-4 font-bold text-maroon">${c.code}</td>
                    <td class="p-3 px-4 font-bold">${c.name}</td>
                    <td class="p-3 px-4">${c.contact_number}</td>
                    <td class="p-3 px-4">${c.contact_person}</td>
                    <td class="p-3 px-4 text-slate-500">${c.address}</td>
                </tr>
            `).join('');
        } else {
            window._customerTotalPages = 1;
            updateCustomerPaginationControls();
            tbody.innerHTML = '<tr><td colspan="5" class="p-4 text-center text-slate-300 italic">No customers found.</td></tr>';
        }
    } catch (e) {
        console.error(e);
        window._customerTotalPages = 1;
        updateCustomerPaginationControls();
        tbody.innerHTML = '<tr><td colspan="5" class="p-4 text-center text-red-400 italic">Error loading customers.</td></tr>';
    }
};

function getCustomerFiltersFromInputs() {
    const inputs = document.querySelectorAll('#customer-search-modal .column-search-input');
    const filters = {};
    inputs.forEach(inp => {
        const key = inp.dataset.col || '';
        if (!key) return;
        filters[key] = (inp.value || '').trim();
    });
    return filters;
}

window.filterCustomerTable = function() {
    if (window._customerFilterTimer) clearTimeout(window._customerFilterTimer);
    window._customerFilterTimer = setTimeout(() => {
        loadCustomers(1);
    }, 250);
};

function updateCustomerPaginationControls() {
    const info = document.getElementById('customer-page-info');
    const prevBtn = document.getElementById('customer-prev-btn');
    const nextBtn = document.getElementById('customer-next-btn');
    const page = window._customerPage || 1;
    const total = window._customerTotalPages || 1;

    if (info) info.textContent = `Page ${page} of ${total}`;
    if (prevBtn) prevBtn.disabled = page <= 1;
    if (nextBtn) nextBtn.disabled = page >= total;
}

window.prevCustomerPage = function() {
    const page = window._customerPage || 1;
    if (page > 1) loadCustomers(page - 1);
};

window.nextCustomerPage = function() {
    const page = window._customerPage || 1;
    const total = window._customerTotalPages || 1;
    if (page < total) loadCustomers(page + 1);
};

function getItemFiltersFromInputs() {
    const columnMap = {
        '1': 'code',
        '2': 'description',
        '3': 'part_number',
        '4': 'application',
        '5': 'oum',
        '6': 'price',
        '7': 'on_hand',
    };
    const filters = {};
    document.querySelectorAll('#item-list-modal .column-search-input[data-col]').forEach(inp => {
        const col = columnMap[inp.dataset.col] || inp.dataset.col;
        if (col) {
            filters[col] = (inp.value || '').trim();
        }
    });
    return filters;
}

function updateItemPaginationControls() {
    const info = document.getElementById('item-page-info');
    const prevBtn = document.getElementById('item-prev-btn');
    const nextBtn = document.getElementById('item-next-btn');
    const page = window._itemPage || 1;
    const total = window._itemTotalPages || 1;

    if (info) info.textContent = `Page ${page} of ${total}`;
    if (prevBtn) prevBtn.disabled = page <= 1;
    if (nextBtn) nextBtn.disabled = page >= total;
}

function renderItemRows(products) {
    const tbody = document.getElementById('item-list-tbody');
    if (!tbody) return;

    if (!products || products.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="p-4 text-center text-slate-300 italic">No matching items.</td></tr>';
        return;
    }

    tbody.innerHTML = products.map(p => {
        const checked = window._checkedProductIds && window._checkedProductIds.has(Number(p.id)) ? 'checked' : '';
        return `<tr class="hover:bg-slate-50 transition-colors">
            <td class="p-3 px-4"><input type="checkbox" value="${p.id}" class="item-checkbox rounded border-slate-300" ${checked} onchange="toggleItemCheckbox(${p.id}, this.checked)"></td>
            <td class="p-3 px-4 font-bold text-maroon">${p.product_code}</td>
            <td class="p-3 px-4 font-bold">${p.description || ''}</td>
            <td class="p-3 px-4">${p.part_number}</td>
            <td class="p-3 px-4">${p.application}</td>
            <td class="p-3 px-4 text-center uppercase">${p.oum}</td>
            <td class="p-3 px-4 text-right">₱ ${parseFloat(p.price).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
            <td class="p-3 px-4 text-center font-bold text-emerald-600">${p.on_hand}</td>
        </tr>`;
    }).join('');
}

window.loadProducts = async function(page = 1) {
    const tbody = document.getElementById('item-list-tbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="8" class="p-4 text-center text-slate-400">Loading items...</td></tr>';
    toggleModal('item-list-modal', true);
    try {
        const filters = getItemFiltersFromInputs();
        const params = new URLSearchParams();
        params.set('page', String(page));
        params.set('perPage', '50');
        Object.entries(filters).forEach(([key, value]) => {
            if (value !== '') params.set(`search[${key}]`, value);
        });

        const res = await fetch(`${window.salesRoutes?.productsUrl || '/admin/sales/sales-note/products'}?${params.toString()}`);
        const result = await res.json();

        if (result.success) {
            window._checkedProductIds = window._checkedProductIds || new Set();
            window._itemProductsById = window._itemProductsById || {};
            window._itemPageProducts = Array.isArray(result.products) ? result.products : [];
            window._itemPageProducts.forEach(product => {
                window._itemProductsById[Number(product.id)] = product;
            });
            window._itemPage = result.pagination?.current_page || page;
            window._itemTotalPages = result.pagination?.last_page || 1;

            renderItemRows(window._itemPageProducts);
            updateItemPaginationControls();
        } else {
            window._itemPageProducts = [];
            tbody.innerHTML = '<tr><td colspan="8" class="p-4 text-center text-slate-300 italic">No products found.</td></tr>';
            window._itemPage = 1;
            window._itemTotalPages = 1;
            updateItemPaginationControls();
        }
    } catch (e) {
        console.error(e);
        tbody.innerHTML = '<tr><td colspan="8" class="p-4 text-center text-red-400 italic">Error loading products.</td></tr>';
        window._itemPage = 1;
        window._itemTotalPages = 1;
        updateItemPaginationControls();
    }
};

window.toggleItemCheckbox = function(id, checked) {
    if (!window._checkedProductIds) window._checkedProductIds = new Set();
    const numericId = Number(id);
    checked ? window._checkedProductIds.add(numericId) : window._checkedProductIds.delete(numericId);
};

window.toggleAllItemCheckboxes = async function(checked) {
    if (!window._checkedProductIds) window._checkedProductIds = new Set();
    if (checked) {
        const filters = getItemFiltersFromInputs();
        const params = new URLSearchParams();
        params.set('ids_only', '1');
        Object.entries(filters).forEach(([key, value]) => {
            if (value !== '') params.set(`search[${key}]`, value);
        });
        try {
            const res = await fetch(`${window.salesRoutes?.productsUrl || '/admin/sales/sales-note/products'}?${params.toString()}`);
            const result = await res.json();
            if (result.success && Array.isArray(result.products)) {
                result.products.forEach(p => {
                    window._checkedProductIds.add(Number(p.id));
                });
            }
        } catch (e) {
            console.error('Error fetching all products for select-all:', e);
        }
    } else {
        window._checkedProductIds.clear();
    }
    document.querySelectorAll('#item-list-tbody .item-checkbox').forEach(cb => cb.checked = checked);
};

window.filterItemTable = function() {
    if (window._itemFilterTimer) clearTimeout(window._itemFilterTimer);
    window._itemFilterTimer = setTimeout(() => {
        loadProducts(1);
    }, 250);
};

window.prevItemPage = function() {
    const page = window._itemPage || 1;
    if (page > 1) loadProducts(page - 1);
};

window.nextItemPage = function() {
    const page = window._itemPage || 1;
    const total = window._itemTotalPages || 1;
    if (page < total) loadProducts(page + 1);
};

// Track whether user has chosen to proceed despite low stock
window._proceedLowStock = false;

window.addSelectedItems = function() {
    const selectedIds = new Set();

    if (window._checkedProductIds instanceof Set) {
        window._checkedProductIds.forEach(id => selectedIds.add(Number(id)));
    }

    document.querySelectorAll('#item-list-tbody .item-checkbox:checked').forEach(cb => {
        const id = Number(cb.value || cb.getAttribute('value') || 0);
        if (id > 0) selectedIds.add(id);
    });

    if (selectedIds.size === 0) { alert('Please select at least one item.'); return; }

    // Capture selected IDs BEFORE clearing - need this for proceed-low-stock flow
    const lowStockPendingItems = Array.from(window._checkedProductIds || []);
    const okIds = new Set();
    const productCache = window._itemProductsById || {};
    const issues = [];
    let addedCount = 0;
    selectedIds.forEach(id => {
        const numericId = Number(id);
        let p = productCache[numericId];
        if (!p) {
            const cb = document.querySelector(`#item-list-tbody .item-checkbox[value="${numericId}"]`);
            const row = cb?.closest('tr');
            if (row) {
                const tds = row.querySelectorAll('td');
                p = {
                    id: numericId,
                    product_code: tds[1]?.innerText?.trim() || '',
                    description: tds[2]?.innerText?.trim() || '',
                    part_number: tds[3]?.innerText?.trim() || '',
                    application: tds[4]?.innerText?.trim() || '',
                    oum: tds[5]?.innerText?.trim() || '',
                    price: (tds[6]?.innerText || '').replace(/[₱,\s]/g, '') || 0,
                    on_hand: Number(tds[7]?.innerText?.trim() || 0),
                };
                productCache[numericId] = p;
            }
        }
        if (p) {
            const available = Number(p.on_hand ?? 0);
            const code = String(p.product_code ?? '').trim() || `ID-${numericId}`;
            const desc = String(p.description ?? '').trim() || '';
            if (available < 1) {
                issues.push({
                    product_id: p.id,
                    product_code: code,
                    description: desc,
                    requested_qty: 1,
                    available_stock: available,
                    reason: 'Out of stock',
                });
                okIds.add(numericId);
                return;
            }

            window.selectItemFromList({ id: p.id, code: code, desc: desc, unit: p.oum, price: p.price, on_hand: available, price_codes: p.price_codes || [] });
            addedCount++;
        }
    });
    window._checkedProductIds = new Set();
    const selectAllEl = document.getElementById('select-all-items');
    if (selectAllEl) selectAllEl.checked = false;

    if (issues.length > 0) {
        toggleModal('item-list-modal', false);
        // Store which low-stock item IDs to re-add if user clicks Proceed
        window._lowStockPendingIds = Array.from(okIds);
        // Show stock warning modal with Proceed / Go Back buttons
        showStockWarningModal(issues, true);
        return;
    }

    toggleModal('item-list-modal', false);
};

window.proceedLowStockAdd = function() {
    window._proceedLowStock = true;
    toggleModal('stock-warning-modal', false);

    // Re-add items that have low stock (with qty 0)
    const productCache = window._itemProductsById || {};
    const selectedIds = new Set(window._lowStockPendingIds || []);
    selectedIds.forEach(id => {
        const numericId = Number(id);
        const p = productCache[numericId];
        if (p) {
            window.selectItemFromList({ id: p.id, code: p.product_code, desc: p.description, unit: p.oum, price: p.price, on_hand: p.on_hand, price_codes: p.price_codes || [] });
        }
    });

    window._lowStockPendingIds = null;

    // Update qty to 0 for all low-stock items and re-enable validation
    // Actually, items get added with default qty 1, we need to set them to 0
    document.querySelectorAll('#selected-items-tbody tr').forEach(row => {
        if (row.getAttribute('data-converted') === 'true') return;

        const pid = parseInt(row.getAttribute('data-product-id')) || 0;
        if (pid && window._lowStockProductIds && window._lowStockProductIds.has(pid)) {
            const qtyInput = row.querySelector('.item-qty');
            if (qtyInput) {
                qtyInput.value = 0;
                updateSalesItemSubtotal(qtyInput);
            }
        }
    });

    // Refresh icon
    if (typeof lucide !== 'undefined') lucide.createIcons();
};

window.goBackFromStockWarning = function() {
    window._proceedLowStock = false;
    window._lowStockPendingIds = null;
    window._lowStockProductIds = null;
    toggleModal('stock-warning-modal', false);
    // Re-open item list modal so user can adjust
    setTimeout(() => {
        toggleModal('item-list-modal', true);
    }, 200);
};

window.selectCustomer = function(customerData) {
    const isEdit = document.getElementById('edit-sales-note-modal') && !document.getElementById('edit-sales-note-modal').classList.contains('hidden');
    const nameId = isEdit ? 'edit-customer-name' : 'new-customer-name';
    const codeId = isEdit ? 'edit-customer-code' : 'new-customer-code';
    const idId = isEdit ? 'edit-customer-id' : 'new-customer-id';
    
    document.getElementById(nameId).value = customerData.name;
    document.getElementById(codeId).value = customerData.code;
    document.getElementById(idId).value = customerData.id;
    toggleModal('customer-search-modal', false);
    
    // Check for partial sales notes (only for new sales note creation, not edit)
    if (!isEdit) {
        checkCustomerPartialNotes(customerData.id);
    }
};

// ========== LINKED PARTIAL SALES NOTE CONVERSION ==========

window._hasPartialNotes = false;
window._convertedItems = []; // Store converted items until confirmation

async function checkCustomerPartialNotes(customerId) {
    try {
        const url = (window.salesRoutes?.customerPartialsUrl || '/admin/sales/sales-note/customer/:customerId/partials')
            .replace(':customerId', customerId);
        const res = await fetch(url);
        const result = await res.json();
        
        if (result.success && result.partial_notes && result.partial_notes.length > 0) {
            window._hasPartialNotes = true;
            window._partialNotes = result.partial_notes;
            // Update step system to use 4 steps
            updateStepSystem(4);
        } else {
            window._hasPartialNotes = false;
            window._partialNotes = [];
            // Use standard 3 steps
            updateStepSystem(3);
        }
    } catch (e) {
        console.error('Error checking partial notes:', e);
        window._hasPartialNotes = false;
        updateStepSystem(3);
    }
}

function updateStepSystem(totalSteps) {
    window._totalSteps = totalSteps;
    
    // Update step indicators visibility
    const step4Indicator = document.querySelector('[data-step="4"]');
    if (step4Indicator) {
        step4Indicator.style.display = totalSteps === 4 ? 'flex' : 'none';
    }
    
    // Update step labels based on total steps
    if (totalSteps === 4) {
        // 4-step mode: Customer -> Linked Partial -> Select Items -> Review
        // Select step-label spans directly (data-step is on both span and step-4 div wrapper)
        const step2Label = document.querySelector('span[data-step="2"]');
        const step3Label = document.querySelector('span[data-step="3"]');
        const step4Label = document.querySelector('span[data-step="4"]');
        if (step2Label) step2Label.textContent = 'Linked Partial';
        if (step3Label) step3Label.textContent = 'Select Items';
        if (step4Label) step4Label.textContent = 'Review';
    } else {
        // 3-step mode: Customer -> Select Items -> Review
        const step2Label = document.querySelector('span[data-step="2"]');
        const step3Label = document.querySelector('span[data-step="3"]');
        if (step2Label) step2Label.textContent = 'Select Items';
        if (step3Label) step3Label.textContent = 'Review';
    }
}

window.loadPartialNotes = function() {
    const tbody = document.getElementById('partial-notes-tbody');
    if (!tbody) return;
    
    if (!window._partialNotes || window._partialNotes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="p-4 text-center text-slate-300 italic">No partial sales notes found.</td></tr>';
        return;
    }
    
    tbody.innerHTML = window._partialNotes.map(note => `
        <tr class="hover:bg-slate-50 transition-colors cursor-pointer partial-note-row" data-note-id="${note.id}">
            <td class="p-3 px-4 font-bold text-maroon sales-no-cell">${escapeHtml(note.sales_number || '')}</td>
            <td class="p-3 px-4 text-center items-count-cell">${note.total_items || 0}</td>
            <td class="p-3 px-4 text-right font-bold text-emerald-600 amount-cell">₱${parseFloat(note.remaining_amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
            <td class="p-3 px-4 text-center">
                <button onclick="viewPartialNoteItems(${note.id}, '${escapeHtml(note.sales_number)}')" 
                        class="px-4 py-1.5 bg-blue-500 hover:bg-blue-600 text-white text-xs font-bold rounded-lg transition-all">
                    View
                </button>
            </td>
        </tr>
    `).join('');
    
    // Add click handler to rows for selection highlighting
    tbody.querySelectorAll('.partial-note-row').forEach(row => {
        row.addEventListener('click', function(e) {
            // Don't highlight if clicking the View button
            if (e.target.tagName === 'BUTTON' || e.target.closest('button')) return;
            
            // Toggle selection
            const isSelected = this.classList.contains('bg-maroon-900');
            
            // Remove selection from all rows
            tbody.querySelectorAll('.partial-note-row').forEach(r => {
                r.classList.remove('bg-maroon-900');
                r.classList.add('hover:bg-slate-50');
                // Reset all cell colors
                r.querySelector('.sales-no-cell')?.classList.remove('text-yellow-400');
                r.querySelector('.sales-no-cell')?.classList.add('text-maroon');
                r.querySelector('.items-count-cell')?.classList.remove('text-yellow-400');
                r.querySelector('.amount-cell')?.classList.remove('text-yellow-400');
                r.querySelector('.amount-cell')?.classList.add('text-emerald-600');
            });
            
            // Add selection to clicked row if it wasn't selected
            if (!isSelected) {
                this.classList.add('bg-maroon-900');
                this.classList.remove('hover:bg-slate-50');
                // Change cell colors to yellow
                this.querySelector('.sales-no-cell')?.classList.remove('text-maroon');
                this.querySelector('.sales-no-cell')?.classList.add('text-yellow-400');
                this.querySelector('.items-count-cell')?.classList.add('text-yellow-400');
                this.querySelector('.amount-cell')?.classList.remove('text-emerald-600');
                this.querySelector('.amount-cell')?.classList.add('text-yellow-400');
            }
        });
    });
};

window.viewPartialNoteItems = async function(noteId, salesNumber) {
    // Highlight the selected row first
    const noteTbody = document.getElementById('partial-notes-tbody');
    if (noteTbody) {
        // Remove selection from all rows
        noteTbody.querySelectorAll('.partial-note-row').forEach(r => {
            r.classList.remove('bg-maroon-900');
            r.classList.add('hover:bg-slate-50');
            r.querySelector('.sales-no-cell')?.classList.remove('text-yellow-400');
            r.querySelector('.sales-no-cell')?.classList.add('text-maroon');
            r.querySelector('.items-count-cell')?.classList.remove('text-yellow-400');
            r.querySelector('.amount-cell')?.classList.remove('text-yellow-400');
            r.querySelector('.amount-cell')?.classList.add('text-emerald-600');
        });
        
        // Highlight the clicked row
        const selectedRow = noteTbody.querySelector(`[data-note-id="${noteId}"]`);
        if (selectedRow) {
            selectedRow.classList.add('bg-maroon-900');
            selectedRow.classList.remove('hover:bg-slate-50');
            selectedRow.querySelector('.sales-no-cell')?.classList.remove('text-maroon');
            selectedRow.querySelector('.sales-no-cell')?.classList.add('text-yellow-400');
            selectedRow.querySelector('.items-count-cell')?.classList.add('text-yellow-400');
            selectedRow.querySelector('.amount-cell')?.classList.remove('text-emerald-600');
            selectedRow.querySelector('.amount-cell')?.classList.add('text-yellow-400');
        }
    }
    
    toggleModal('view-partial-items-modal', true);
    document.getElementById('partial-note-number').textContent = salesNumber || '';
    
    const tbody = document.getElementById('partial-items-tbody');
    tbody.innerHTML = '<tr><td colspan="6" class="p-4 text-center text-slate-400">Loading items...</td></tr>';
    
    try {
        const url = (window.salesRoutes?.remainingItemsUrl || '/admin/sales/sales-note/:noteId/remaining-items')
            .replace(':noteId', noteId);
        const res = await fetch(url);
        const result = await res.json();
        
        if (result.success && result.remaining_items && result.remaining_items.length > 0) {
            window._currentPartialItems = result.remaining_items;
            window._currentPartialNoteId = noteId;
            
            tbody.innerHTML = result.remaining_items.map((item, idx) => {
                return `
                <tr data-item-index="${idx}">
                    <td class="p-3 px-4 font-bold text-maroon">${escapeHtml(item.product_code || '')}</td>
                    <td class="p-3 px-4">${escapeHtml(item.description || '')}</td>
                    <td class="p-3 px-4 text-center font-bold text-slate-600">${item.note_qty || 0}</td>
                    <td class="p-3 px-4 text-center font-bold">${item.remaining_qty || 0}</td>
                    <td class="p-3 px-4 text-right font-bold text-emerald-600">₱${parseFloat(item.remaining_amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                    <td class="p-3 px-4 text-center">
                        <button onclick="convertPartialItem(${idx})" 
                                class="px-4 py-1.5 bg-maroon hover:bg-maroon-800 text-white text-xs font-bold rounded-lg transition-all">
                            Convert
                        </button>
                    </td>
                </tr>
            `}).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="6" class="p-4 text-center text-slate-300 italic">No remaining items found.</td></tr>';
        }
    } catch (e) {
        console.error('Error loading partial items:', e);
        tbody.innerHTML = '<tr><td colspan="6" class="p-4 text-center text-red-400 italic">Error loading items.</td></tr>';
    }
};

window.convertAllPartialItems = function() {
    if (!window._currentPartialItems || window._currentPartialItems.length === 0) {
        return;
    }

    let convertedCount = 0;
    window._currentPartialItems.forEach((item, idx) => {
        const convertedQty = getPositiveConvertedQty(item);
        if (convertedQty <= 0) return;

        // Call convertPartialItem for each - it silently skips already-converted
        const checkDup = window._convertedItems?.find(ci =>
            item.sales_order_item_id
                ? ci.sales_order_item_id === item.sales_order_item_id
                : (ci.source_note_id === window._currentPartialNoteId && ci.product_id === item.product_id)
        );
        if (checkDup) return;

        if (!window._convertedItems) window._convertedItems = [];

        window._convertedItems.push({
            source_note_id: window._currentPartialNoteId,
            sales_order_id: item.sales_order_id,
            sales_order_item_id: item.sales_order_item_id,
            product_id: item.product_id,
            product_code: item.product_code,
            description: item.description,
            quantity: convertedQty,
            unit_price: item.unit_price,
            discount: item.discount,
            oum: item.oum,
            converted_qty: convertedQty,
            converted_amount: item.remaining_amount
        });

        convertedCount++;
    });

    if (convertedCount > 0) {
        toggleModal('view-partial-items-modal', false);

        setTimeout(() => {
            const tbody = document.getElementById('partial-notes-tbody');
            if (tbody && window._currentPartialNoteId) {
                const selectedRow = tbody.querySelector(`[data-note-id="${window._currentPartialNoteId}"]`);
                if (selectedRow) {
                    selectedRow.classList.add('bg-maroon-900');
                    selectedRow.classList.remove('hover:bg-slate-50');
                    selectedRow.querySelector('.sales-no-cell')?.classList.remove('text-maroon');
                    selectedRow.querySelector('.sales-no-cell')?.classList.add('text-yellow-400');
                    selectedRow.querySelector('.items-count-cell')?.classList.add('text-yellow-400');
                    selectedRow.querySelector('.amount-cell')?.classList.remove('text-emerald-600');
                    selectedRow.querySelector('.amount-cell')?.classList.add('text-yellow-400');
                }
            }
        }, 100);
    }
};

window.convertPartialItem = function(itemIndex) {
    if (!window._currentPartialItems || !window._currentPartialItems[itemIndex]) {
        alert('Item not found.');
        return;
    }
    
    const item = window._currentPartialItems[itemIndex];
    const convertedQty = getPositiveConvertedQty(item);
    if (convertedQty <= 0) {
        alert('This item has no remaining quantity to convert.');
        return;
    }
    
    // Add to converted items array
    if (!window._convertedItems) window._convertedItems = [];
    
    // Check if already converted - silently skip
    // Use sales_order_item_id for invoice items, product_id+note_id for note-only items
    const exists = window._convertedItems.find(ci => 
        item.sales_order_item_id 
            ? ci.sales_order_item_id === item.sales_order_item_id
            : (ci.source_note_id === window._currentPartialNoteId && ci.product_id === item.product_id)
    );
    
    if (exists) {
        return;
    }
    
    window._convertedItems.push({
        source_note_id: window._currentPartialNoteId,
        sales_order_id: item.sales_order_id,
        sales_order_item_id: item.sales_order_item_id,
        product_id: item.product_id,
        product_code: item.product_code,
        description: item.description,
        quantity: convertedQty,
        unit_price: item.unit_price,
        discount: item.discount,
        oum: item.oum,
        converted_qty: convertedQty,
        converted_amount: item.remaining_amount
    });
    
    // Show success feedback (optional - you can remove this too if you want)
    const btn = event.target.closest('button');
    if (btn) {
        btn.textContent = '✓ Converted';
        btn.classList.remove('bg-maroon', 'hover:bg-maroon-800');
        btn.classList.add('bg-emerald-500', 'cursor-default');
        btn.disabled = true;
    }
    
    toggleModal('view-partial-items-modal', false);
    
    // Keep the partial note row highlighted after converting
    // Find and maintain the highlight on the source note row
    setTimeout(() => {
        const tbody = document.getElementById('partial-notes-tbody');
        if (tbody && window._currentPartialNoteId) {
            const selectedRow = tbody.querySelector(`[data-note-id="${window._currentPartialNoteId}"]`);
            if (selectedRow) {
                // Ensure it stays highlighted
                selectedRow.classList.add('bg-maroon-900');
                selectedRow.classList.remove('hover:bg-slate-50');
                // Keep cell colors yellow
                selectedRow.querySelector('.sales-no-cell')?.classList.remove('text-maroon');
                selectedRow.querySelector('.sales-no-cell')?.classList.add('text-yellow-400');
                selectedRow.querySelector('.items-count-cell')?.classList.add('text-yellow-400');
                selectedRow.querySelector('.amount-cell')?.classList.remove('text-emerald-600');
                selectedRow.querySelector('.amount-cell')?.classList.add('text-yellow-400');
            }
        }
    }, 100);
};

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function isSalesNotePriceCodeEnabled() {
    return window.salesNotePriceCodeEnabled === true || window.location.pathname.includes('/special/');
}

function normalizeSalesNotePriceCodes(codes) {
    return Array.isArray(codes) ? codes.filter(code => code && code.price_code) : [];
}

function salesNotePriceCodeValue(row) {
    if (!isSalesNotePriceCodeEnabled()) return null;
    const value = row.querySelector('.item-price-code')?.value || '';
    return value.trim() === '' ? null : value.trim();
}

window.getSalesNoteRowPriceCode = salesNotePriceCodeValue;

window.renderSalesNotePriceCodeCell = function(itemData = {}, updateFn = 'updateSalesItemSubtotal(this)') {
    if (!isSalesNotePriceCodeEnabled()) return '';

    const codes = normalizeSalesNotePriceCodes(itemData.price_codes);
    const selectedCode = String(itemData.price_code || '').trim();
    const defaultPrice = itemData.price ?? itemData.unit_price ?? itemData.selling_price ?? '';

    if (codes.length === 0) {
        return `
            <td class="p-4 px-6 text-center">
                <select class="item-price-code w-32 px-2 py-1.5 border border-slate-200 rounded-lg text-[10px] bg-slate-100 text-slate-400" disabled data-default-price="${escapeHtml(defaultPrice)}">
                    <option value=""></option>
                </select>
            </td>
        `;
    }

    const options = codes.map(code => {
        const value = String(code.price_code || '').trim();
        const price = Number(code.selling_price ?? 0);
        const label = price > 0
            ? `${value} - ₱ ${price.toLocaleString(undefined, { minimumFractionDigits: 2 })}`
            : value;
        return `<option value="${escapeHtml(value)}" data-selling-price="${escapeHtml(price)}" ${value === selectedCode ? 'selected' : ''}>${escapeHtml(label)}</option>`;
    }).join('');

    return `
        <td class="p-4 px-6 text-center">
            <select class="item-price-code w-36 px-2 py-1.5 border border-slate-200 rounded-lg text-[10px] bg-white focus:ring-2 focus:ring-maroon/20 outline-none transition-all" data-default-price="${escapeHtml(defaultPrice)}" onchange="handleSalesNotePriceCodeChange(this)">
                <option value="">Normal Price</option>
                ${options}
            </select>
        </td>
    `;
};

window.renderSalesNotePriceCodeDisplayCell = function(source) {
    if (!isSalesNotePriceCodeEnabled()) return '';
    const value = source instanceof Element
        ? salesNotePriceCodeValue(source)
        : String(source?.price_code || '').trim();
    return `<td class="p-3 text-center text-slate-600">${escapeHtml(value || '')}</td>`;
};

window.handleSalesNotePriceCodeChange = function(select) {
    const row = select.closest('tr');
    if (!row) return;

    const priceInput = row.querySelector('.item-price');
    if (priceInput) {
        const option = select.selectedOptions?.[0];
        const sellingPrice = option ? parseFloat(option.dataset.sellingPrice || '') : NaN;
        const defaultPrice = parseFloat(select.dataset.defaultPrice || '');

        if (select.value && Number.isFinite(sellingPrice)) {
            priceInput.value = sellingPrice.toFixed(2);
        } else if (Number.isFinite(defaultPrice)) {
            priceInput.value = defaultPrice;
        }
    }

    if (row.closest('#edit-selected-items-tbody') && typeof window.updateEditSalesItemSubtotal === 'function') {
        window.updateEditSalesItemSubtotal(select);
    } else if (typeof window.updateSalesItemSubtotal === 'function') {
        window.updateSalesItemSubtotal(select);
    }
};

function getPositiveConvertedQty(item) {
    const qty = Number(item?.remaining_qty ?? item?.quantity ?? item?.converted_qty ?? 0);
    return Number.isFinite(qty) && qty > 0 ? qty : 0;
}

function getConvertedItemForRow(row) {
    const convertedItems = window._convertedItems || [];
    const productId = parseInt(row.getAttribute('data-product-id')) || 0;
    const sourceNoteId = parseInt(row.getAttribute('data-source-note-id')) || 0;
    const salesOrderItemId = parseInt(row.getAttribute('data-sales-order-item-id')) || 0;
    const productCode = (row.querySelector('.product-code')?.innerText || '').trim();

    return convertedItems.find(item => {
        const itemProductId = parseInt(item.product_id) || 0;
        const itemSourceNoteId = parseInt(item.source_note_id) || 0;
        const itemSalesOrderItemId = parseInt(item.sales_order_item_id) || 0;
        const itemProductCode = (item.product_code || '').trim();

        if (salesOrderItemId && itemSalesOrderItemId === salesOrderItemId) return true;
        if (sourceNoteId && productId && itemSourceNoteId === sourceNoteId && itemProductId === productId) return true;
        return productId && itemProductId === productId && productCode && itemProductCode === productCode;
    });
}

function getSalesRowQty(row) {
    const qtyInput = row.querySelector('.item-qty');
    let qty = parseInt(qtyInput?.value) || 0;

    if (qty < 1 && row.getAttribute('data-converted') === 'true') {
        const convertedQty = getPositiveConvertedQty(getConvertedItemForRow(row));
        if (convertedQty > 0) {
            qty = convertedQty;
            if (qtyInput) {
                qtyInput.value = convertedQty;
                updateSalesItemSubtotal(qtyInput);
            }
        }
    }

    return qty;
}

window.updateSalesItemSubtotal = function(input) {
    const row = input.closest('tr');
    const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
    const price = parseFloat(row.querySelector('.item-price').value) || 0;
    const disc = parseFloat(row.querySelector('.item-disc').value) || 0;
    row.querySelector('.item-subtotal').value = (qty * price * (1 - disc / 100)).toFixed(2);

    const pid = parseInt(row.getAttribute('data-product-id')) || 0;
    if (!window._proceedLowStock) {
        const issues = getSelectedItemsStockIssues();
        if (issues.some(i => (parseInt(i.product_id) || 0) === pid)) {
            const now = Date.now();
            if (!window._lastStockModalAt || (now - window._lastStockModalAt) > 1200) {
                window._lastStockModalAt = now;
                showStockWarningModal(issues, true);
            }
        }
    }
};

function getSelectedItemsStockIssues() {
    const requestedByPid = new Map();
    const metaByPid = new Map();
    const availableByPid = new Map();

    document.querySelectorAll('#selected-items-tbody tr').forEach(row => {
        if (!window._onlineMode && !row.querySelector('input[type="checkbox"]')?.checked) return;
        const pid = parseInt(row.getAttribute('data-product-id')) || 0;
        const code = row.querySelector('.product-code')?.innerText || '';
        const desc = row.querySelector('.product-desc')?.innerText || '';
        const qty = getSalesRowQty(row);
        const bonus = parseInt(row.querySelector('.bonus-input')?.value) || 0;

        if (!pid) {
            requestedByPid.set(0, (requestedByPid.get(0) || 0) + (qty + bonus));
            metaByPid.set(0, { product_code: code, description: desc });
            availableByPid.set(0, 0);
            return;
        }

        const requested = qty + bonus;
        requestedByPid.set(pid, (requestedByPid.get(pid) || 0) + requested);
        if (!metaByPid.has(pid)) metaByPid.set(pid, { product_code: code, description: desc });
        if (!availableByPid.has(pid)) {
            const onHand = Number(row.dataset.onHand ?? row.getAttribute('data-on-hand') ?? 0);
            availableByPid.set(pid, onHand);
        }
    });

    const issues = [];
    for (const [pid, requestedQty] of requestedByPid.entries()) {
        const available = Number(availableByPid.get(pid) ?? 0);
        if (available < Number(requestedQty || 0)) {
            const meta = metaByPid.get(pid) || { product_code: '', description: '' };
            issues.push({
                product_id: pid,
                product_code: meta.product_code,
                description: meta.description,
                requested_qty: Number(requestedQty || 0),
                available_stock: available,
                reason: available <= 0 ? 'Out of stock' : 'Not enough stock',
            });
        }
    }
    return issues;
}

function validateSelectedItemsStock(showModal) {
    if (window._proceedLowStock) return true;
    
    const issues = getSelectedItemsStockIssues();
    if (issues.length === 0) return true;
    if (showModal) {
        showStockWarningModal(issues, true);
    }
    return false;
}

function addConvertedItemsToSelection() {
    if (!window._convertedItems || window._convertedItems.length === 0) return;
    
    const tbody = document.getElementById('selected-items-tbody');
    if (!tbody) return;
    
    window._convertedItems.forEach(item => {
        const convertedQty = getPositiveConvertedQty(item);
        if (convertedQty <= 0) return;

        // Check if item already exists in selected items
        const itemProductId = parseInt(item.product_id) || 0;
        const exists = Array.from(tbody.querySelectorAll('tr')).some(row =>
            (parseInt(row.getAttribute('data-product-id')) || 0) === itemProductId
        );
        
        if (exists) {
            console.log('Item already in selection:', item.product_code);
            return;
        }
        
        // Add converted item to selected items table - matching exact structure
        const row = document.createElement('tr');
        row.setAttribute('data-product-id', item.product_id);
        row.setAttribute('data-converted', 'true');
        row.setAttribute('data-source-note-id', item.source_note_id);
        row.setAttribute('data-sales-order-item-id', item.sales_order_item_id);
        
        const qtyInputFn = window._onlineMode ? 'updateOnlineItemSubtotal(this)' : 'updateSalesItemSubtotal(this)';
        
        row.innerHTML = `
            <td class="p-4 px-6 text-center"><input type="checkbox" class="item-checkbox accent-maroon cursor-pointer" checked></td>
            <td class="p-4 px-6 font-bold text-maroon product-code">${escapeHtml(item.product_code)}</td>
            <td class="p-4 px-6 text-slate-700 product-desc">
                ${escapeHtml(item.description)}
                <span class="block text-[10px] text-amber-600 font-bold mt-1">
                    <i data-lucide="link" class="w-3 h-3 inline"></i> Converted from Partial
                </span>
            </td>
            <td class="p-4 px-6 text-center"><input type="number" value="${convertedQty}" min="1" oninput="${qtyInputFn}" onchange="${qtyInputFn}" class="item-qty w-20 px-3 py-1.5 border border-slate-200 rounded-lg text-center focus:ring-2 focus:ring-maroon/20 outline-none transition-all"></td>
            <td class="p-4 px-6 text-center"><input type="text" value="${escapeHtml(item.oum || '')}" class="item-unit w-20 px-3 py-1.5 border border-slate-200 rounded-lg text-center focus:ring-2 focus:ring-maroon/20 outline-none transition-all"></td>
            ${window.renderSalesNotePriceCodeCell(item, qtyInputFn)}
            <td class="p-4 px-6 text-right"><input type="number" value="${item.unit_price}" oninput="${qtyInputFn}" onchange="${qtyInputFn}" class="item-price w-28 px-3 py-1.5 border border-slate-200 rounded-lg text-right focus:ring-2 focus:ring-maroon/20 outline-none transition-all"></td>
            <td class="p-4 px-6 text-right"><input type="number" value="${(convertedQty * item.unit_price * (1 - item.discount/100)).toFixed(2)}" class="item-subtotal w-32 px-3 py-1.5 border border-slate-200 rounded-lg text-right font-bold text-maroon bg-slate-50" readonly></td>
            <td class="p-4 px-6">
                <div class="flex flex-col space-y-1">
                    <input type="number" placeholder="Bonus" value="0" oninput="${qtyInputFn}" onchange="${qtyInputFn}" class="bonus-input w-[95px] min-w-[95px] px-2 py-1 text-right text-[10px] border border-slate-200 rounded-md">
                    <input type="number" placeholder="Disc %" value="${item.discount || 0}" oninput="${qtyInputFn}" onchange="${qtyInputFn}" class="item-disc w-[95px] min-w-[95px] px-2 py-1 text-right text-[10px] border border-slate-200 rounded-md">
                </div>
            </td>
            <td class="p-4 px-6 text-center"><button onclick="this.closest('tr').remove()" class="p-1.5 bg-red-50 text-red-500 hover:bg-red-600 hover:text-white rounded-lg transition-all" title="Remove item"><i data-lucide="x" class="w-3.5 h-3.5"></i></button></td>
        `;
        
        tbody.appendChild(row);
    });
    
    if (typeof lucide !== 'undefined') lucide.createIcons();
    // Removed alert notification
}

function populateReviewData() {
    document.getElementById('rev-sales-no').innerText = document.getElementById('new-sales-no').value;
    document.getElementById('rev-sales-date').innerText = document.getElementById('new-sales-date').value;
    document.getElementById('rev-so-type').innerText = document.getElementById('new-so-type').value;
    document.getElementById('rev-customer-name').innerText = document.getElementById('new-customer-name').value || '---';
    document.getElementById('rev-salesman').innerText = document.getElementById('new-salesman').value || '---';
    document.getElementById('rev-prepared-by').innerText = document.getElementById('new-prepared-by').value || '---';
    document.getElementById('rev-checked-by').innerText = document.getElementById('new-checked-by').value || '---';
    document.getElementById('rev-packed-by').innerText = document.getElementById('new-packed-by').value || '---';
    document.getElementById('rev-is-rush').innerText = document.getElementById('new-is-rush').checked ? 'YES (RUSH)' : 'NO';

    const reviewTbody = document.getElementById('review-items-tbody');
    const sourceTbody = document.getElementById('selected-items-tbody');
    if (reviewTbody && sourceTbody) {
        reviewTbody.innerHTML = '';
        const rows = sourceTbody.querySelectorAll('tr');
        let grossTotal = 0;
        let totalDiscount = 0;

        rows.forEach(row => {
            const code = row.cells[1].innerText;
            const desc = row.cells[2].innerText;
            const qty = getSalesRowQty(row);
            const unit = row.querySelector('.item-unit').value;
            const price = parseFloat(row.querySelector('.item-price').value) || 0;
            const discPercent = parseFloat(row.querySelector('.item-disc').value) || 0;
            const bonus = row.querySelector('.bonus-input')?.value || '0';
            const subtotal = parseFloat(row.querySelector('.item-subtotal').value) || 0;
            const rowGross = qty * price;
            const rowDiscount = rowGross * (discPercent / 100);
            grossTotal += rowGross;
            totalDiscount += rowDiscount;

            const newRow = document.createElement('tr');
            newRow.className = "border-b border-slate-100";
            newRow.innerHTML = `
                <td class="p-3 text-slate-700 font-bold">${code}</td>
                <td class="p-3 text-slate-600">${desc}</td>
                <td class="p-3 text-center">${qty}</td>
                <td class="p-3 text-center uppercase">${unit}</td>
                ${window.renderSalesNotePriceCodeDisplayCell(row)}
                <td class="p-3 text-right">₱ ${price.toLocaleString()}</td>
                <td class="p-3 text-center text-emerald-600 font-bold">${discPercent}%</td>
                <td class="p-3 text-center text-amber-600 font-bold">${bonus}</td>
                <td class="p-3 text-right font-bold text-maroon">₱ ${subtotal.toLocaleString()}</td>
            `;
            reviewTbody.appendChild(newRow);
        });

        const netTotal = grossTotal - totalDiscount;
        if (document.getElementById('rev-gross-total')) document.getElementById('rev-gross-total').innerText = `₱ ${grossTotal.toLocaleString(undefined, {minimumFractionDigits: 2})}`;
        if (document.getElementById('rev-total-discount')) document.getElementById('rev-total-discount').innerText = `₱ ${totalDiscount.toLocaleString(undefined, {minimumFractionDigits: 2})}`;
        if (document.getElementById('rev-grand-total')) document.getElementById('rev-grand-total').innerText = `₱ ${netTotal.toLocaleString(undefined, {minimumFractionDigits: 2})}`;
    }
}

window.toggleOnlineMode = function() {
    window._onlineMode = !window._onlineMode;
    console.log('Online mode:', window._onlineMode);
    const track = document.getElementById('online-toggle-track');
    const thumb = document.getElementById('online-toggle-thumb');
    if (track && thumb) {
        track.className = window._onlineMode ? 'w-9 h-5 bg-emerald-400 rounded-full transition-colors' : 'w-9 h-5 bg-slate-200 rounded-full transition-colors';
        thumb.style.transform = window._onlineMode ? 'translateX(16px)' : 'translateX(0)';
    }
};

window.submitSalesNote = function() {
    if (!window._onlineMode && !validateSelectedItemsStock(true)) return;
    toggleModal('confirm-details-modal', true);
};

window.finalizeSalesNote = async function() {
    if (window._submittingSalesNote) return;
    window._submittingSalesNote = true;

    const confirmBtn = document.querySelector('#confirm-details-modal .bg-maroon');
    if (confirmBtn) confirmBtn.disabled = true;

    try {
        const items = [];
        const invalidQtyItems = [];
        document.querySelectorAll('#selected-items-tbody tr').forEach(row => {
            if (!window._onlineMode && !row.querySelector('input[type="checkbox"]').checked) return;
            const qty = getSalesRowQty(row);
            if (qty < 0) {
                invalidQtyItems.push(row.querySelector('.product-code')?.innerText || 'selected item');
                return;
            }
            items.push({
                product_id: parseInt(row.getAttribute('data-product-id')) || 0,
                product_code: row.querySelector('.product-code').innerText,
                description: row.querySelector('.product-desc').innerText,
                quantity: qty,
                oum: row.querySelector('.item-unit').value,
                price_code: window.getSalesNoteRowPriceCode ? window.getSalesNoteRowPriceCode(row) : null,
                unit_price: parseFloat(row.querySelector('.item-price').value) || 0,
                discount: parseFloat(row.querySelector('.item-disc').value) || 0,
                additional_qty: parseInt(row.querySelector('.bonus-input').value) || 0,
                subtotal: parseFloat(row.querySelector('.item-subtotal').value) || 0,
            });
        });

        if (invalidQtyItems.length > 0) {
            alert('Please enter a quantity of at least 1 for: ' + invalidQtyItems.join(', '));
            return;
        }
        console.log('finalizeSalesNote: _onlineMode =', window._onlineMode, 'items count =', items.length, 'rows found =', document.querySelectorAll('#selected-items-tbody tr').length);
        if (!window._onlineMode && items.length === 0) { alert('Please select at least one item.'); return; }

        if (!window._proceedLowStock && !window._onlineMode) {
            if (!validateSelectedItemsStock(true)) return;

            const stockOk = await checkSalesNoteStock(items);
            if (!stockOk) return;
        }

        const res = await fetch(window.salesRoutes?.processUrl || '/admin/sales/sales-note/process', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.csrfToken || '' },
            body: JSON.stringify({
                so_type: document.getElementById('new-so-type').value,
                order_date: document.getElementById('new-sales-date').value,
                customer_id: parseInt(document.getElementById('new-customer-id').value) || 0,
                customer_name: document.getElementById('new-customer-name').value,
                salesman: document.getElementById('new-salesman').value,
                prepared_by: document.getElementById('new-prepared-by').value,
                checked_by: document.getElementById('new-checked-by').value,
                packed_by: document.getElementById('new-packed-by').value,
                is_rush: document.getElementById('new-is-rush').checked,
                gross_total: parseFloat(document.getElementById('rev-gross-total').innerText.replace(/[₱,]/g, '')) || 0,
                total_discount: parseFloat(document.getElementById('rev-total-discount').innerText.replace(/[₱,]/g, '')) || 0,
                net_total: parseFloat(document.getElementById('rev-grand-total').innerText.replace(/[₱,]/g, '')) || 0,
                remarks: '',
                items: items,
                converted_items: window._convertedItems || [], // Include converted items
                ignore_stock: window._proceedLowStock || window._onlineMode || false,
            }),
        });
        let result;
        try {
            result = await res.json();
        } catch (e) {
            alert('Server error (HTTP ' + res.status + '). The response was not valid JSON. Check server logs for details.');
            return;
        }
        if (!res.ok) {
            if (result && Array.isArray(result.stock_issues) && result.stock_issues.length > 0) {
                showStockWarningModal(result.stock_issues, true);
                return;
            }
            const errors = result.errors ? Object.values(result.errors).flat().join('\n') : '';
            alert('Error: ' + (result.message || 'Request failed') + (errors ? '\n\nDetails:\n' + errors : ''));
            return;
        }
        if (result.success) {
            toggleModal('confirm-details-modal', false);
            toggleModal('add-sales-note-modal', false);
            setTimeout(() => toggleModal('success-modal', true), 300);
        } else {
            alert('Error: ' + (result.message || 'Failed to process sales note.'));
        }
    } catch (e) {
        console.error(e);
        alert('An error occurred: ' + e.message);
    } finally {
        window._submittingSalesNote = false;
        if (confirmBtn) confirmBtn.disabled = false;
    }
};

async function checkSalesNoteStock(items) {
    const url = window.salesRoutes?.stockCheckUrl || '/admin/sales/sales-note/check-stock';
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

        let data;
        try {
            data = await res.json();
        } catch (e) {
            alert('Server error (HTTP ' + res.status + '). The response was not valid JSON. Check server logs for details.');
            return false;
        }

        if (!res.ok) {
            const errors = data?.errors ? Object.values(data.errors).flat().join('\n') : '';
            alert('Error: ' + (data?.message || 'Stock check failed') + (errors ? '\n\nDetails:\n' + errors : ''));
            return false;
        }

        if (!data?.success) {
            alert('Error: ' + (data?.message || 'Stock check failed'));
            return false;
        }

        if (data.ok) return true;
        const issues = Array.isArray(data.issues) ? data.issues : [];
        if (issues.length > 0) {
            showStockWarningModal(issues, true);
            return false;
        }
        return false;
    } catch (e) {
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

function showStockWarningModal(issues, showProceedOption = false) {
    renderStockIssuesModal(issues);
    
    // Show/hide the proceed and go-back buttons based on context
    const proceedBtn = document.getElementById('stock-warning-proceed-btn');
    const goBackBtn = document.getElementById('stock-warning-goback-btn');
    const closeBtn = document.getElementById('stock-warning-close-btn');
    
    if (showProceedOption) {
        // Store low stock product IDs for later use
        window._lowStockProductIds = new Set(issues.map(i => parseInt(i.product_id)).filter(id => id > 0));
        // IMPORTANT: Do NOT overwrite _lowStockPendingIds here if it was already set by addSelectedItems
        if (!window._lowStockPendingIds || window._lowStockPendingIds.length === 0) {
            window._lowStockPendingIds = Array.from(window._checkedProductIds || []);
        }
        
        if (proceedBtn) proceedBtn.classList.remove('hidden');
        if (goBackBtn) goBackBtn.classList.remove('hidden');
        if (closeBtn) closeBtn.classList.add('hidden');
    } else {
        if (proceedBtn) proceedBtn.classList.add('hidden');
        if (goBackBtn) goBackBtn.classList.add('hidden');
        if (closeBtn) closeBtn.classList.remove('hidden');
    }
    
    const confirmModal = document.getElementById('confirm-details-modal');
    if (confirmModal && !confirmModal.classList.contains('hidden')) {
        toggleModal('confirm-details-modal', false);
    }
    toggleModal('stock-warning-modal', true);
}

window.deleteSelectedItems = function() {
    const isEdit = document.getElementById('edit-sales-note-modal') && !document.getElementById('edit-sales-note-modal').classList.contains('hidden');
    const tbodyId = isEdit ? 'edit-selected-items-tbody' : 'selected-items-tbody';
    const tbody = document.getElementById(tbodyId);
    const checked = tbody.querySelectorAll('input[type="checkbox"]:checked');
    if (checked.length === 0) { alert('Please select at least one item to delete.'); return; }
    document.getElementById('delete-items-count').textContent = 'Are you sure you want to delete ' + checked.length + ' selected item(s)?';
    toggleModal('delete-items-modal', true);
};

window.confirmDeleteItems = function() {
    const isEdit = document.getElementById('edit-sales-note-modal') && !document.getElementById('edit-sales-note-modal').classList.contains('hidden');
    if (!isEdit) {
        const tbodyId = 'selected-items-tbody';
        const tbody = document.getElementById(tbodyId);
        const checked = tbody.querySelectorAll('input[type="checkbox"]:checked');
        checked.forEach(cb => {
            const row = cb.closest('tr');
            if (row) row.remove();
        });
        toggleModal('delete-items-modal', false);
        return;
    }
    // Edit mode: stage deletions, never delete from DB yet
    if (!window._pendingDeletedItemIds) window._pendingDeletedItemIds = new Set();
    const tbody = document.getElementById('edit-selected-items-tbody');
    const checked = tbody.querySelectorAll('input[type="checkbox"]:checked');
    checked.forEach(cb => {
        const row = cb.closest('tr');
        if (!row) return;
        const sniId = row.getAttribute('data-item-id');
        if (sniId && sniId !== '0' && sniId !== '' && sniId !== 'null') {
            window._pendingDeletedItemIds.add(parseInt(sniId, 10));
            row.style.display = 'none';
            const chk = row.querySelector('input[type="checkbox"]');
            if (chk) chk.checked = false;
        } else {
            row.remove();
        }
    });
    window.syncEditActualQtyColumnVisibility();
    toggleModal('delete-items-modal', false);
};

window.selectItemFromList = function(itemData) {
    const isEdit = document.getElementById('edit-sales-note-modal') && !document.getElementById('edit-sales-note-modal').classList.contains('hidden');
    const tbodyId = isEdit ? 'edit-selected-items-tbody' : 'selected-items-tbody';
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return;
    
    const existing = tbody.querySelector(`tr[data-product-id="${itemData.id || 0}"]`);
    if (existing) {
        alert('This item is already in the list. Adjust the QTY/Bonus instead of adding it again.');
        return;
    }
    
    const qtyInputFn = isEdit ? 'updateEditSalesItemSubtotal(this)' : 'updateSalesItemSubtotal(this)';
    const priceCodeCell = window.renderSalesNotePriceCodeCell ? window.renderSalesNotePriceCodeCell(itemData, qtyInputFn) : '';
    
    const row = document.createElement('tr');
    row.setAttribute('data-product-id', itemData.id || 0);
    row.dataset.onHand = String(Number(itemData.on_hand ?? itemData.onHand ?? 0));
    row.className = "hover:bg-slate-50 transition-colors";
    row.innerHTML = `
        <td class="p-4 px-6"><input type="checkbox" checked class="rounded border-slate-300 text-maroon focus:ring-maroon"></td>
        <td class="p-4 px-6 font-bold text-maroon product-code">${itemData.code}</td>
        <td class="p-4 px-6 text-slate-700 product-desc">${itemData.desc}</td>
        <td class="p-4 px-6 text-center"><input type="number" value="1" min="0" oninput="${qtyInputFn}" onchange="${qtyInputFn}" class="item-qty w-20 px-3 py-1.5 border border-slate-200 rounded-lg text-center focus:ring-2 focus:ring-maroon/20 outline-none transition-all"></td>
        <td class="p-4 px-6 text-center"><input type="text" value="${itemData.unit}" class="item-unit w-20 px-3 py-1.5 border border-slate-200 rounded-lg text-center focus:ring-2 focus:ring-maroon/20 outline-none transition-all"></td>
        ${priceCodeCell}
        <td class="p-4 px-6 text-right"><input type="number" value="${itemData.price}" oninput="${qtyInputFn}" onchange="${qtyInputFn}" class="item-price w-28 px-3 py-1.5 border border-slate-200 rounded-lg text-right focus:ring-2 focus:ring-maroon/20 outline-none transition-all"></td>
        <td class="p-4 px-6 text-right"><input type="number" value="${itemData.price}" class="item-subtotal w-32 px-3 py-1.5 border border-slate-200 rounded-lg text-right font-bold text-maroon bg-slate-50" readonly></td>
        <td class="p-4 px-6"><div class="flex flex-col space-y-1"><input type="number" placeholder="Bonus" oninput="${qtyInputFn}" onchange="${qtyInputFn}" class="bonus-input w-[95px] min-w-[95px] px-2 py-1 text-right text-[10px] border border-slate-200 rounded-md"><input type="number" placeholder="Disc %" oninput="${qtyInputFn}" onchange="${qtyInputFn}" class="item-disc w-[95px] min-w-[95px] px-2 py-1 text-right text-[10px] border border-slate-200 rounded-md"></div></td>
    `;
    tbody.appendChild(row);
    if (isEdit) {
        updateEditSalesItemSubtotal(row.querySelector('.item-qty'));
    } else {
        updateSalesItemSubtotal(row.querySelector('.item-qty'));
    }
};

window.applyOverallQty = function() {
    const isEdit = document.getElementById('edit-sales-note-modal') && !document.getElementById('edit-sales-note-modal').classList.contains('hidden');
    const overallQtyInputId = isEdit ? 'edit-overall-qty-input' : 'overall-qty-input';
    const tbodyId = isEdit ? 'edit-selected-items-tbody' : 'selected-items-tbody';
    
    const qty = parseInt(document.getElementById(overallQtyInputId)?.value) || 0;
    if (qty < 0) return;
    const tbody = document.getElementById(tbodyId);
    tbody.querySelectorAll('tr input[type="checkbox"]:checked').forEach(cb => {
        const qtyInput = cb.closest('tr')?.querySelector('.item-qty');
        if (qtyInput) {
            qtyInput.value = qty;
            if (isEdit) {
                updateEditSalesItemSubtotal(qtyInput);
            } else {
                updateSalesItemSubtotal(qtyInput);
            }
        }
    });
};

window.loadSalesDashboard = async function() {
    try {
        const res = await fetch(window.salesRoutes?.dashboardUrl || '/admin/sales/sales-note/dashboard');
        const r = await res.json();
        if (r.success) {
            document.getElementById('card-total-sales').textContent = r.total_sales.toLocaleString();
            document.getElementById('card-total-open').textContent = r.total_open.toLocaleString();
            document.getElementById('card-total-partial').textContent = r.total_partial.toLocaleString();
            document.getElementById('card-total-closed').textContent = r.total_closed.toLocaleString();
        }
    } catch (e) { console.error('Error loading sales dashboard:', e); }
};

window.switchSalesNoteTab = function(tab) {
    currentTab = tab;
    currentPage = 1;
    document.getElementById('sn-tab-active').className = 'px-5 py-2.5 bg-white border border-slate-200 text-slate-500 text-[10px] font-bold rounded-xl uppercase tracking-widest transition-all hover:border-maroon hover:text-maroon';
    document.getElementById('sn-tab-history').className = 'px-5 py-2.5 bg-white border border-slate-200 text-slate-500 text-[10px] font-bold rounded-xl uppercase tracking-widest transition-all hover:border-maroon hover:text-maroon';
    if (tab === 'active') {
        document.getElementById('sn-tab-active').className = 'px-5 py-2.5 bg-maroon text-white text-[10px] font-bold rounded-xl uppercase tracking-widest shadow-sm transition-all hover:bg-maroon-800';
        loadSalesActive();
    } else {
        document.getElementById('sn-tab-history').className = 'px-5 py-2.5 bg-maroon text-white text-[10px] font-bold rounded-xl uppercase tracking-widest shadow-sm transition-all hover:bg-maroon-800';
        loadSalesHistory();
    }
};

function loadSalesNotesByTab() {
    if (currentTab === 'active') {
        loadSalesActive();
    } else {
        loadSalesHistory();
    }
}

window.goToPage = function(page) {
    if (page >= 1 && page <= totalPages) {
        currentPage = page;
        loadSalesNotesByTab();
    }
};

function updatePaginationControls() {
    const container = document.getElementById('sn-pagination-container');
    if (!container) return;

    // Update pagination info
    document.getElementById('sn-page-from').textContent = paginationData.from || 0;
    document.getElementById('sn-page-to').textContent = paginationData.to || 0;
    document.getElementById('sn-page-total').textContent = paginationData.total || 0;

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

window.loadSalesActive = async function() {
    const tbody = document.getElementById('main-sales-tbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="10" class="p-4 text-center text-slate-300 italic">Loading...</td></tr>';
    try {
        // Build search parameters
        const searchParams = new URLSearchParams();
        searchParams.append('page', currentPage);
        searchParams.append('perPage', 50);
        
        // Get search values
        const dateVal = document.getElementById('col-search-date')?.value.trim() || '';
        const transVal = document.getElementById('col-search-trans')?.value.trim() || '';
        const nameVal = document.getElementById('col-search-name')?.value.trim() || '';
        const amountVal = document.getElementById('col-search-amount')?.value.trim() || '';
        const salesmanVal = document.getElementById('col-search-salesman')?.value.trim() || '';
        const statusVal = document.getElementById('col-search-status')?.value.trim() || '';
        const checkedVal = document.getElementById('col-search-checked')?.value.trim() || '';
        const remarksVal = document.getElementById('col-search-remarks')?.value.trim() || '';
        
        if (dateVal) searchParams.append('search[date]', dateVal);
        if (transVal) searchParams.append('search[sales_number]', transVal);
        if (nameVal) searchParams.append('search[customer_name]', nameVal);
        if (amountVal) searchParams.append('search[net_total]', amountVal);
        if (salesmanVal) searchParams.append('search[salesman]', salesmanVal);
        if (statusVal) searchParams.append('search[status]', statusVal);
        if (checkedVal) searchParams.append('search[checked_by]', checkedVal);
        if (remarksVal) searchParams.append('search[remarks]', remarksVal);

        const res = await fetch(`${window.salesRoutes?.activeUrl || '/admin/sales/sales-note/active'}?${searchParams}`);
        const r = await res.json();
        
        if (r.success && r.notes.length > 0) {
            paginationData = r.pagination || {};
            totalPages = paginationData.last_page || 1;
            
            // Get overdue rush notes from window (set by navbar)
            const overdueRushNotes = window.overdueRushNotes || [];
            
            tbody.innerHTML = r.notes.map(n => {
                const sc = n.status === 'Closed' ? 'bg-emerald-100 text-emerald-600' : n.status === 'Open' ? 'bg-amber-100 text-amber-600' : n.status === 'Partial' ? 'bg-blue-100 text-blue-600' : 'bg-slate-100 text-slate-600';
                
                // Check if this note is overdue rush
                const isOverdueRush = overdueRushNotes.includes(n.sales_number);
                const rowClass = isOverdueRush ? 'rush-overdue-row' : '';
                
                return `<tr class="hover:bg-slate-50 transition-colors group table-row-animate ${rowClass}" data-sales-note-id="${n.id}" data-sales-number="${n.sales_number}">
                    <td class="p-4 px-4 text-center"><input type="checkbox" value="${n.id}" class="note-checkbox w-4 h-4 rounded border-slate-300 text-maroon focus:ring-maroon cursor-pointer"></td>
                    <td class="p-4 px-6 text-slate-600">${n.order_date}</td>
                    <td class="p-4 px-6 font-mono text-xs text-maroon font-bold">${n.sales_number}</td>
                    <td class="p-4 px-6 text-slate-700 font-bold">${n.customer_name}</td>
                    <td class="p-4 px-6 font-bold text-maroon">₱ ${parseFloat(n.net_total).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                    <td class="p-4 px-6 text-center text-slate-600">${n.salesman || '---'}</td>
                    <td class="p-4 px-6 text-center"><span class="px-3 py-1 ${sc} text-[10px] font-bold rounded-full uppercase tracking-wider">${n.status}</span></td>
                    <td class="p-4 px-6 text-slate-600">${n.checked_by || '---'}</td>
                    <td class="p-4 px-6 text-slate-500 italic text-xs">${n.remarks || '---'}</td>
                    <td class="p-4 px-6 text-center">
                        <div class="flex items-center justify-center space-x-1">
                            <button onclick="viewSalesDetail(${n.id})" class="sales-note-action-btn sales-note-action-view transition-all shadow-sm" aria-label="View Sales Note"><i data-lucide="eye" class="w-4 h-4"></i></button>
                            ${window.location.pathname.includes('/admin/') || window.location.pathname.includes('/special/') || window.location.pathname.includes('/regular/') ? `<button onclick="editSalesNote(${n.id})" class="sales-note-action-btn sales-note-action-edit transition-all shadow-sm" aria-label="Edit Sales Note"><i data-lucide="edit" class="w-4 h-4"></i></button>` : ''}
                            <button onclick="openDeleteModal(${n.id}, '${n.sales_number}')" class="sales-note-action-btn sales-note-action-delete transition-all shadow-sm" aria-label="Delete Sales Note"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                        </div>
                    </td>
                </tr>`;
            }).join('');
            updatePaginationControls();
            if (typeof lucide !== 'undefined') lucide.createIcons();
            
            // Check if we need to scroll to highlighted row
            checkAndHighlightRushNote();
        } else {
            tbody.innerHTML = '<tr><td colspan="10" class="p-4 text-center text-slate-300 italic">No active sales notes found.</td></tr>';
            updatePaginationControls();
        }
    } catch (e) {
        console.error('Error loading active sales notes:', e);
        tbody.innerHTML = '<tr><td colspan="10" class="p-4 text-center text-red-400 italic">Error loading active notes.</td></tr>';
    }
};

window.loadSalesHistory = async function() {
    const tbody = document.getElementById('main-sales-tbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="10" class="p-4 text-center text-slate-300 italic">Loading...</td></tr>';
    try {
        // Build search parameters
        const searchParams = new URLSearchParams();
        searchParams.append('page', currentPage);
        searchParams.append('perPage', 50);
        
        // Get search values
        const dateVal = document.getElementById('col-search-date')?.value.trim() || '';
        const transVal = document.getElementById('col-search-trans')?.value.trim() || '';
        const nameVal = document.getElementById('col-search-name')?.value.trim() || '';
        const amountVal = document.getElementById('col-search-amount')?.value.trim() || '';
        const salesmanVal = document.getElementById('col-search-salesman')?.value.trim() || '';
        const statusVal = document.getElementById('col-search-status')?.value.trim() || '';
        const checkedVal = document.getElementById('col-search-checked')?.value.trim() || '';
        const remarksVal = document.getElementById('col-search-remarks')?.value.trim() || '';
        
        if (dateVal) searchParams.append('search[date]', dateVal);
        if (transVal) searchParams.append('search[sales_number]', transVal);
        if (nameVal) searchParams.append('search[customer_name]', nameVal);
        if (amountVal) searchParams.append('search[net_total]', amountVal);
        if (salesmanVal) searchParams.append('search[salesman]', salesmanVal);
        if (statusVal) searchParams.append('search[status]', statusVal);
        if (checkedVal) searchParams.append('search[checked_by]', checkedVal);
        if (remarksVal) searchParams.append('search[remarks]', remarksVal);

        const res = await fetch(`${window.salesRoutes?.historyUrl || '/admin/sales/sales-note/history'}?${searchParams}`);
        const r = await res.json();
        
        if (r.success && r.notes.length > 0) {
            paginationData = r.pagination || {};
            totalPages = paginationData.last_page || 1;
            
            tbody.innerHTML = r.notes.map(n => {
                const sc = n.status === 'Closed' ? 'bg-emerald-100 text-emerald-600' : n.status === 'Open' ? 'bg-amber-100 text-amber-600' : n.status === 'Partial' ? 'bg-blue-100 text-blue-600' : 'bg-slate-100 text-slate-600';
                return `<tr class="hover:bg-slate-50 transition-colors group table-row-animate" data-sales-note-id="${n.id}">
                    <td class="p-4 px-4 text-center"><input type="checkbox" value="${n.id}" class="note-checkbox w-4 h-4 rounded border-slate-300 text-maroon focus:ring-maroon cursor-pointer"></td>
                    <td class="p-4 px-6 text-slate-600">${n.order_date}</td>
                    <td class="p-4 px-6 font-mono text-xs text-maroon font-bold">${n.sales_number}</td>
                    <td class="p-4 px-6 text-slate-700 font-bold">${n.customer_name}</td>
                    <td class="p-4 px-6 font-bold text-maroon">₱ ${parseFloat(n.net_total).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                    <td class="p-4 px-6 text-center text-slate-600">${n.salesman || '---'}</td>
                    <td class="p-4 px-6 text-center"><span class="px-3 py-1 ${sc} text-[10px] font-bold rounded-full uppercase tracking-wider">${n.status}</span></td>
                    <td class="p-4 px-6 text-slate-600">${n.checked_by || '---'}</td>
                    <td class="p-4 px-6 text-slate-500 italic text-xs">${n.remarks || '---'}</td>
                    <td class="p-4 px-6 text-center">
                        <div class="flex items-center justify-center space-x-1">
                            <button onclick="viewSalesDetail(${n.id})" class="sales-note-action-btn sales-note-action-view transition-all shadow-sm" aria-label="View Sales Note"><i data-lucide="eye" class="w-4 h-4"></i></button>
                            ${window.location.pathname.includes('/admin/') || window.location.pathname.includes('/special/') || window.location.pathname.includes('/regular/') ? `<button onclick="editSalesNote(${n.id})" class="sales-note-action-btn sales-note-action-edit transition-all shadow-sm" aria-label="Edit Sales Note"><i data-lucide="edit" class="w-4 h-4"></i></button>` : ''}
                            <button onclick="openDeleteModal(${n.id}, '${n.sales_number}')" class="sales-note-action-btn sales-note-action-delete transition-all shadow-sm" aria-label="Delete Sales Note"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                        </div>
                    </td>
                </tr>`;
            }).join('');
            updatePaginationControls();
            if (typeof lucide !== 'undefined') lucide.createIcons();
        } else {
            tbody.innerHTML = '<tr><td colspan="10" class="p-4 text-center text-slate-300 italic">No sales notes found.</td></tr>';
            updatePaginationControls();
        }
    } catch (e) {
        console.error('Error loading sales history:', e);
        tbody.innerHTML = '<tr><td colspan="10" class="p-4 text-center text-red-400 italic">Error loading sales notes.</td></tr>';
    }
};

window.viewSalesDetail = async function(id) {
    const tbody = document.getElementById('view-items-tbody');
    if (!tbody) return;
    const viewColspan = isSalesNotePriceCodeEnabled() ? 10 : 9;
    toggleModal('view-sales-modal', true);
    tbody.innerHTML = `<tr><td colspan="${viewColspan}" class="p-4 text-center text-slate-400">Loading...</td></tr>`;
    try {
        const url = (window.salesRoutes?.detailUrl || '/admin/sales/sales-note/detail/:id').replace(':id', id);
        const res = await fetch(url);
        const r = await res.json();
        if (r.success) {
            const note = r.note;
            document.getElementById('view-transaction-label').textContent = 'Transaction: ' + note.sales_number;
            document.getElementById('view-sales-no').textContent = note.sales_number;
            document.getElementById('view-sales-date').textContent = note.order_date;
            document.getElementById('view-so-type').textContent = note.so_type;
            document.getElementById('view-customer-name').textContent = note.customer_name;
            document.getElementById('view-salesman').textContent = note.salesman || '---';
            document.getElementById('view-prepared-by').textContent = note.prepared_by || '---';
            document.getElementById('view-checked-by').textContent = note.checked_by || '---';
            document.getElementById('view-packed-by').textContent = note.packed_by || '---';
            document.getElementById('view-status').textContent = note.status;
            document.getElementById('view-is-rush').textContent = note.is_rush ? 'YES' : 'NO';
            document.getElementById('view-remarks').textContent = note.remarks || '---';
            if (note.items.length > 0) {
                tbody.innerHTML = note.items.map(item => `
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="p-4 px-6 font-bold text-maroon">${item.product_code}</td>
                        <td class="p-4 px-6 text-slate-700">${item.description || ''}</td>
                        <td class="p-4 px-6 text-center font-bold">${item.quantity}</td>
                        <td class="p-4 px-6 text-center font-bold text-slate-600">${item.on_hand ?? 0}</td>
                        <td class="p-4 px-6 text-center uppercase text-slate-500">${item.oum}</td>
                        ${window.renderSalesNotePriceCodeDisplayCell(item)}
                        <td class="p-4 px-6 text-right">₱ ${parseFloat(item.unit_price).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                        <td class="p-4 px-6 text-center text-slate-400">${item.discount}%</td>
                        <td class="p-4 px-6 text-center text-amber-600 font-bold">${item.bonus || 0}</td>
                        <td class="p-4 px-6 text-right font-bold text-maroon">₱ ${parseFloat(item.subtotal).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                    </tr>
                `).join('');
                document.getElementById('view-items-count').textContent = note.items.length + ' items';
                document.getElementById('view-gross-total').textContent = '₱ ' + parseFloat(note.gross_total).toLocaleString(undefined, {minimumFractionDigits: 2});
                document.getElementById('view-total-discount').textContent = '₱ ' + parseFloat(note.total_discount).toLocaleString(undefined, {minimumFractionDigits: 2});
                document.getElementById('view-grand-total').textContent = '₱ ' + parseFloat(note.net_total).toLocaleString(undefined, {minimumFractionDigits: 2});
            } else {
                tbody.innerHTML = `<tr><td colspan="${viewColspan}" class="p-4 text-center text-slate-300 italic">No items found.</td></tr>`;
                document.getElementById('view-items-count').textContent = '0 items';
                document.getElementById('view-gross-total').textContent = '₱ 0.00';
                document.getElementById('view-total-discount').textContent = '₱ 0.00';
                document.getElementById('view-grand-total').textContent = '₱ 0.00';
            }
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    } catch (e) {
        console.error('Error loading sales detail:', e);
        tbody.innerHTML = `<tr><td colspan="${viewColspan}" class="p-4 text-center text-red-400 italic">Error loading details.</td></tr>`;
    }
};

window.openDeleteModal = function(id, salesNumber) {
    document.getElementById('delete-note-id').value = id;
    const infoEl = document.getElementById('delete-note-info');
    if (infoEl) {
        infoEl.textContent = salesNumber ? 'Sales Note #: ' + salesNumber : '';
    }
    toggleModal('delete-confirm-modal', true);
};

window.confirmDeleteNote = async function() {
    const id = document.getElementById('delete-note-id').value;
    if (!id) return;

    if (window._deletingSalesNote) return;
    window._deletingSalesNote = true;

    const deleteBtn = document.querySelector('#delete-confirm-modal .bg-red-500');
    if (deleteBtn) {
        deleteBtn.textContent = 'Deleting...';
        deleteBtn.disabled = true;
    }

    toggleModal('delete-confirm-modal', false);
    try {
        const res = await fetch(window.salesRoutes.deleteUrl.replace(':id', id), {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': window.csrfToken, 'Accept': 'application/json' },
        });
        let data;
        try {
            data = await res.json();
        } catch (e) {
            alert('Server error (HTTP ' + res.status + '). The response was not valid JSON. Check server logs for details.');
            return;
        }
        if (data.success) {
            toggleModal('delete-success-modal', true);
        } else {
            alert(data.message || 'Failed to delete sales note.');
        }
    } catch (e) {
        console.error('Error deleting sales note:', e);
        alert('An unexpected error occurred.');
    } finally {
        window._deletingSalesNote = false;
        if (deleteBtn) {
            deleteBtn.textContent = 'Delete';
            deleteBtn.disabled = false;
        }
    }
};

window.toggleAllCheckboxes = function(checked) {
    document.querySelectorAll('.note-checkbox').forEach(cb => cb.checked = checked);
};

window.generateNoteReport = function() {
    const checked = document.querySelectorAll('.note-checkbox:checked');
    if (checked.length === 0) { alert('Please select at least one sales note to generate a report.'); return; }
    window._reportSelectedIds = Array.from(checked).map(cb => parseInt(cb.value));
    toggleModal('note-report-modal', true);
    document.getElementById('report-setup-step').classList.remove('hidden');
    document.getElementById('report-content-step').classList.add('hidden');
    document.getElementById('report-setup-footer').classList.remove('hidden');
    document.getElementById('report-content-footer').classList.add('hidden');
    document.getElementById('online-price-confirmed-badge').classList.add('hidden');
    document.getElementById('note-report-subtitle').textContent = 'Select report options';
    document.getElementById('report-add-online-price').checked = false;
    if (typeof lucide !== 'undefined') lucide.createIcons();
};

window.getReportRangeParams = function() {
    const outDate1 = document.getElementById('report-out-date1').value || '';
    const outDate2 = document.getElementById('report-out-date2').value || '';
    const outDate3 = document.getElementById('report-out-date3').value || '';
    return { outDate1, outDate2, outDate3 };
};

window.submitReportSetup = function() {
    const type = document.querySelector('input[name="report-type"]:checked').value;
    const addOnlinePrice = document.getElementById('report-add-online-price').checked;
    const ids = window._reportSelectedIds;
    const range = getReportRangeParams();
    if (!ids || ids.length === 0) { alert('No sales notes selected. Please close and try again.'); return; }
    if (!range.outDate1) { alert('Please enter at least Date 1'); return; }
    if (addOnlinePrice) {
        window.location.href = window.salesRoutes.onlinePricesUrl + '?ids=' + ids.join(',') + '&type=' + type + '&outDate1=' + range.outDate1 + '&outDate2=' + range.outDate2 + '&outDate3=' + range.outDate3;
    } else {
        loadReportContent(ids, type, false, range);
    }
};

window.loadReportContent = async function(ids, type, onlinePricesConfirmed, range) {
    document.getElementById('report-setup-step').classList.add('hidden');
    document.getElementById('report-setup-footer').classList.add('hidden');
    document.getElementById('report-content-step').classList.remove('hidden');
    document.getElementById('report-content-footer').classList.remove('hidden');
    document.getElementById('note-report-content').innerHTML = '<p class="text-center text-slate-400 italic">Loading report...</p>';
    document.getElementById('note-report-subtitle').textContent = type === 'note-unserved' ? 'NOTE UNSERVED' : 'NOTE';
    document.getElementById('online-price-confirmed-badge').classList.toggle('hidden', !onlinePricesConfirmed);
    window._reportType = type;
    window._reportIds = ids;
    window._reportRange = range || { outDate1: new Date().getFullYear().toString(), outDate2: (new Date().getFullYear() + 1).toString(), outDate3: '' };
    try {
        const res = await fetch(window.salesRoutes.reportUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ ids: Array.isArray(ids) ? ids : ids.split(',').map(Number) }),
        });
        const data = await res.json();
        if (data.success) { renderNoteReport(data.notes); }
        else { document.getElementById('note-report-content').innerHTML = `<p class="text-center text-red-400 italic">${data.message || 'Failed to load report.'}</p>`; }
    } catch (e) {
        console.error('Error generating report:', e);
        document.getElementById('note-report-content').innerHTML = '<p class="text-center text-red-400 italic">An unexpected error occurred.</p>';
    }
};

window.openReportAfterOnlinePrices = function(ids, type, outDate1, outDate2, outDate3) {
    window._reportSelectedIds = ids.split(',').map(Number);
    toggleModal('note-report-modal', true);
    loadReportContent(window._reportSelectedIds, type, true, { outDate1: outDate1 || '', outDate2: outDate2 || '', outDate3: outDate3 || '' });
};

window.goToReportSetup = function() {
    document.getElementById('report-setup-step').classList.remove('hidden');
    document.getElementById('report-setup-footer').classList.remove('hidden');
    document.getElementById('report-content-step').classList.add('hidden');
    document.getElementById('report-content-footer').classList.add('hidden');
    document.getElementById('note-report-subtitle').textContent = 'Select report options';
};

window.printNoteReport = function() {
    const ids = window._reportIds;
    const type = window._reportType;
    if (!ids) return;
    const range = window._reportRange || { outDate1: new Date().getFullYear().toString(), outDate2: (new Date().getFullYear() + 1).toString(), outDate3: '' };
    const paymentType = document.querySelector('input[name="report-payment-type"]:checked')?.value || 'none';
    toggleModal('note-report-modal', false);
    window.open(window.salesRoutes.printUrl + '?ids=' + (Array.isArray(ids) ? ids.join(',') : ids) +
        '&type=' + type +
        '&outDate1=' + range.outDate1 +
        '&outDate2=' + range.outDate2 +
        '&outDate3=' + range.outDate3 +
        '&payment_type=' + paymentType, '_blank');
};

window.renderNoteReport = function(notes) {
    let html = '';
    const typeLabel = window._reportType === 'note-unserved' ? 'NOTE UNSERVED' : 'NOTE';
    notes.forEach(n => {
        const itemsHtml = (n.items || []).map(item => {
            const itemCode = item.product_code || item.item_code || item.code || item.productCode || '---';
            const mainQty = Number(item.quantity || item.qty || 0);
            const additionalQty = Number(item.additional_qty || item.additional_quantity || item.add_qty || 0);
            const displayQty = additionalQty > 0 ? `${mainQty} (+${additionalQty})` : `${mainQty}`;
            return `<tr class="border-b border-slate-100">
            <td class="p-2 text-slate-700">${itemCode}</td>
            <td class="p-2 text-slate-600">${item.description || '---'}</td>
            <td class="p-2 text-center text-slate-600">${displayQty}</td>
            <td class="p-2 text-center text-slate-600">${item.oum || '---'}</td>
            <td class="p-2 text-right text-slate-600">₱ ${parseFloat(item.unit_price || 0).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
            <td class="p-2 text-right text-slate-600">${parseFloat(item.discount || 0).toLocaleString(undefined, {minimumFractionDigits: 2})}%</td>
            <td class="p-2 text-right text-maroon font-bold">₱ ${parseFloat(item.subtotal || 0).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
        </tr>`;
        }).join('');
        html += `<div class="bg-slate-50 rounded-2xl p-5 border border-slate-200">
            <div class="flex justify-between items-start mb-3">
                <div><h4 class="text-sm font-bold text-maroon">${n.sales_number}</h4><p class="text-xs text-slate-500">${n.customer_name} | ${n.order_date} | ${typeLabel}</p></div>
                <span class="px-3 py-1 text-[10px] font-bold rounded-full uppercase tracking-wider ${n.status === 'Closed' ? 'bg-emerald-100 text-emerald-600' : n.status === 'Open' ? 'bg-amber-100 text-amber-600' : n.status === 'Partial' ? 'bg-blue-100 text-blue-600' : 'bg-slate-100 text-slate-600'}">${n.status}</span>
            </div>
            <table class="w-full text-left text-xs">
                <thead><tr class="border-b border-slate-200 text-[13px] font-bold text-slate-500 uppercase tracking-widest">
                    <th class="p-2">Item Code</th><th class="p-2">Description</th><th class="p-2 text-center">Qty</th><th class="p-2 text-center">Unit</th><th class="p-2 text-right">Price</th><th class="p-2 text-right">Disc</th><th class="p-2 text-right">Total</th>
                </tr></thead>
                <tbody>${itemsHtml}</tbody>
            </table>
            <div class="text-right mt-2 text-sm font-bold text-maroon">Net Total: ₱ ${parseFloat(n.net_total).toLocaleString(undefined, {minimumFractionDigits: 2})}</div>
            ${n.remarks ? `<p class="text-xs text-slate-400 mt-1 italic">${n.remarks}</p>` : ''}
        </div>`;
    });
    document.getElementById('note-report-content').innerHTML = html;
    if (typeof lucide !== 'undefined') lucide.createIcons();
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


// Function to check and highlight rush note from notification
function checkAndHighlightRushNote() {
    const highlightNote = sessionStorage.getItem('highlightRushNote');
    if (highlightNote) {
        // Find the row with this sales number
        const targetRow = document.querySelector(`#main-sales-tbody tr[data-sales-number="${highlightNote}"]`);
        if (targetRow) {
            // Scroll to the row
            targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // Add a temporary highlight effect
            targetRow.style.border = '3px solid #dc2626';
            targetRow.style.boxShadow = '0 0 20px rgba(220, 38, 38, 0.5)';
            
            // Remove the highlight after 5 seconds
            setTimeout(() => {
                targetRow.style.border = '';
                targetRow.style.boxShadow = '';
            }, 5000);
        }
        
        // Clear the session storage
        sessionStorage.removeItem('highlightRushNote');
    }
}
