/**
 * Purchase Return Client Logic
 */

document.addEventListener('DOMContentLoaded', () => {
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // Set automatic current date for the Add Modal
    const dateInput = document.getElementById('new-return-date');
    if (dateInput) {
        const today = new Date().toISOString().split('T')[0];
        dateInput.value = today;
    }

    // Load dashboard cards
    loadReturnDashboard();

    // Load return history table
    loadReturnHistory();
});

window.pendingDeleteReturnId = null;

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
        
        const anyModalVisible = document.querySelector('.fixed.inset-0:not(.hidden)');
        if (!anyModalVisible) {
            document.body.style.overflow = 'auto';
        }
    }
};

/**
 * Multi-step Modal Navigation
 */
window.goToStep = function(step) {
    // Hide all step contents
    document.querySelectorAll('.return-step-content').forEach(el => el.classList.add('hidden'));
    
    // Show target step
    const targetStep = document.getElementById(`return-step-${step}`);
    if (targetStep) {
        targetStep.classList.remove('hidden');
        updateStepIndicators(step);
        
        if (step === 3) {
            populateReviewData();
        }
    }
};

/**
 * Update Step Indicators
 */
function updateStepIndicators(currentStep) {
    const steps = [1, 2, 3];
    const widths = ['0%', '50%', '100%'];

    steps.forEach(step => {
        const indicator = document.getElementById(`step-indicator-${step}`);
        if (!indicator) return;

        indicator.classList.remove('active', 'completed', 'pending');
        
        if (step < currentStep) {
            indicator.classList.add('completed');
            indicator.innerHTML = '<i data-lucide="check" class="w-4 h-4 text-white"></i>';
        } else if (step === currentStep) {
            indicator.classList.add('active');
            indicator.innerText = step;
        } else {
            indicator.classList.add('pending');
            indicator.innerText = step;
        }
    });

    const progressLine = document.getElementById('step-progress-line');
    if (progressLine) {
        progressLine.style.width = widths[currentStep - 1];
    }

    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

/**
 * Load Return History Table
 */
async function loadReturnHistory() {
    const tbody = document.getElementById('main-return-tbody');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="7" class="p-4 text-center text-slate-400">Loading history...</td></tr>';

    try {
        const response = await fetch(window.returnRoutes?.historyUrl || '/admin/purchase/purchase-return/history');
        const result = await response.json();

        if (result.success && result.history.length > 0) {
            tbody.innerHTML = result.history.map(ret => `
                <tr class="hover:bg-slate-50 transition-colors group">
                    <td class="p-4 px-6 font-mono text-xs text-maroon font-bold">${ret.invoice_no}</td>
                    <td class="p-4 px-6 text-slate-600 whitespace-nowrap">${ret.date || '---'}</td>
                    <td class="p-4 px-6 text-slate-700 font-bold">${ret.supplier_name}</td>
                    <td class="p-4 px-6 text-slate-600">${ret.contact_no}</td>
                    <td class="p-4 px-6 text-slate-600">${ret.contact_person}</td>
                    <td class="p-4 px-6 text-slate-500">${ret.billing_address}</td>
                    <td class="p-4 px-6 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <button onclick="viewReturnDetail({id: ${ret.id}})" class="p-2 bg-slate-100 text-slate-500 hover:bg-maroon hover:text-white rounded-lg transition-all shadow-sm">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </button>
                            <button onclick="printReturn(${ret.id})" class="p-2 bg-amber-50 text-amber-600 hover:bg-amber-600 hover:text-white rounded-lg transition-all shadow-sm">
                                <i data-lucide="printer" class="w-4 h-4"></i>
                            </button>
                            <button onclick="editReturn(${ret.id})" class="p-2 bg-sky-50 text-sky-600 hover:bg-sky-600 hover:text-white rounded-lg transition-all shadow-sm">
                                <i data-lucide="pencil" class="w-4 h-4"></i>
                            </button>
                            <button onclick="handleDeleteReturn(${ret.id})" class="p-2 bg-red-50 text-red-500 hover:bg-red-600 hover:text-white rounded-lg transition-all shadow-sm">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
            if (window.lucide) lucide.createIcons();
        } else {
            tbody.innerHTML = '<tr><td colspan="7" class="p-4 text-center text-slate-300 italic">No return history found.</td></tr>';
        }
    } catch (error) {
        console.error('Error loading return history:', error);
        tbody.innerHTML = '<tr><td colspan="7" class="p-4 text-center text-red-400 italic">Error loading history.</td></tr>';
    }
}

window.handleDeleteReturn = async function(id) {
    window.pendingDeleteReturnId = id;
    toggleModal('delete-return-confirm-modal', true);
};

window.confirmDeletePurchaseReturn = async function() {
    const id = window.pendingDeleteReturnId;
    if (!id) {
        toggleModal('delete-return-confirm-modal', false);
        return;
    }

    try {
        const url = (window.returnRoutes?.deleteUrl || '/admin/purchase/purchase-return/delete/:id').replace(':id', id);
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': window.csrfToken || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });

        const responseText = await response.text();
        let result = {};
        try {
            result = responseText ? JSON.parse(responseText) : {};
        } catch (parseError) {
            throw new Error(`Delete request failed (${response.status}). ${responseText || 'Invalid server response.'}`);
        }

        if (!response.ok || !result.success) {
            throw new Error(result.message || `Failed to delete purchase return (${response.status})`);
        }

        window.pendingDeleteReturnId = null;
        toggleModal('delete-return-confirm-modal', false);
        await refreshPurchaseReturnData();
        showActionSuccessModal(result.message || 'Purchase Return archived successfully');
    } catch (error) {
        console.error('Error deleting purchase return:', error);
        alert(error.message || 'Failed to delete purchase return');
    }
};

// Backward-compatible alias for any cached Purchase Return markup.
window.confirmDeleteReturn = window.confirmDeletePurchaseReturn;

/**
 * Load Dashboard Cards
 */
async function loadReturnDashboard() {
    try {
        const response = await fetch(window.returnRoutes?.dashboardUrl || '/admin/purchase/purchase-return/dashboard');
        const result = await response.json();

        if (result.success) {
            document.getElementById('total-returns-count').textContent = result.total_returns.toLocaleString();
            document.getElementById('total-supplier-count').textContent = result.total_suppliers.toLocaleString();
        }
    } catch (error) {
        console.error('Error loading dashboard:', error);
    }
}

/**
 * Load Invoices for Invoice Search Modal
 */
window.loadPurchaseInvoices = async function() {
    const tbody = document.getElementById('invoice-search-tbody');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="5" class="p-4 text-center text-slate-400">Loading invoices...</td></tr>';
    toggleModal('invoice-search-modal', true);

    try {
        const response = await fetch(window.returnRoutes?.invoicesUrl || '/admin/purchase/purchase-return/invoices');
        const result = await response.json();

        if (result.success && result.invoices.length > 0) {
            tbody.innerHTML = result.invoices.map(inv => `
                <tr onclick="selectInvoice({po_id: ${inv.po_id}, supplier_id: ${inv.supplier_id}, invoiceNo: '${inv.invoice_no}', customerName: '${inv.customer_name}', contactNo: '${inv.contact_no}', contactPerson: '${inv.contact_person}', billingAddress: '${inv.billing_address}'})" class="hover:bg-slate-50 cursor-pointer transition-colors">
                    <td class="p-3 px-4 font-bold text-maroon">${inv.invoice_no}</td>
                    <td class="p-3 px-4 font-bold">${inv.customer_name}</td>
                    <td class="p-3 px-4">${inv.contact_no}</td>
                    <td class="p-3 px-4">${inv.contact_person}</td>
                    <td class="p-3 px-4 text-slate-500">${inv.billing_address}</td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="5" class="p-4 text-center text-slate-300 italic">No eligible invoices found.</td></tr>';
        }
    } catch (error) {
        console.error('Error loading invoices:', error);
        tbody.innerHTML = '<tr><td colspan="5" class="p-4 text-center text-red-400 italic">Error loading invoices.</td></tr>';
    }
};

/**
 * Table Filtering
 */
window.filterTable = function(tbodyId, colIndex, value) {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return;
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

/**
 * Invoice Selection
 */
window.selectInvoice = function(invoiceData) {
    const invInput = document.getElementById('new-invoice-no');
    const supplierInput = document.getElementById('new-supplier-name');
    
    if (invInput) invInput.value = invoiceData.invoiceNo;
    if (supplierInput) supplierInput.value = invoiceData.customerName;

    // Store selected PO ID and Supplier ID
    window.selectedPoId = invoiceData.po_id;
    window.selectedSupplierId = invoiceData.supplier_id;

    // Load PO items into return items table
    loadPoItems(invoiceData.po_id);

    toggleModal('invoice-search-modal', false);
};

/**
 * Load PO Items for Selected Invoice
 */
function purchaseReturnItemRowHtml(item, editing = false) {
    const discountPercent = parseFloat(item.discount_percent ?? item.discount ?? 0) || 0;
    const totalQty = Math.max(0, [item.total_quantity, item.received_quantity, item.actual_quantity, item.quantity]
        .map(value => parseInt(value ?? 0, 10) || 0)
        .find(value => value > 0) || 0);
    const qty = Math.min(totalQty || Number.MAX_SAFE_INTEGER, Math.max(0, parseInt(item.quantity ?? totalQty, 10) || 0));
    const outRaw = editing ? parseInt(item.out_quantity ?? 0, 10) : null;
    const outQty = Math.min(qty, Math.max(0, Number.isFinite(outRaw) ? outRaw : 0));
    const outValue = editing ? String(outQty) : '';
    const junkQty = Math.max(0, qty - outQty);
    const unitPrice = parseFloat(item.unit_price || 0) || 0;
    const subtotal = (qty * unitPrice) * (1 - discountPercent / 100);

    return `
        <tr class="hover:bg-slate-50 transition-colors" data-product-id="${item.product_id || ''}" data-total-qty="${totalQty}">
            <td class="p-4 px-6"><input type="checkbox" checked class="item-checkbox rounded border-slate-300 text-maroon focus:ring-maroon"></td>
            <td class="p-4 px-6 font-bold text-maroon item-code">${item.product_code || ''}</td>
            <td class="p-4 px-6 text-slate-700 item-description">${item.original_description || item.description || ''}</td>
            <td class="p-4 px-6 text-center">
                <div class="inline-flex items-center justify-center gap-1 whitespace-nowrap">
                    <input type="number" value="${qty}" min="0" max="${totalQty}" oninput="updateReturnItemSubtotal(this)" class="item-qty w-16 px-2 py-1.5 border border-slate-200 rounded-lg text-center focus:ring-2 focus:ring-maroon/20 focus:border-maroon outline-none transition-all">
                    <span class="item-total-qty text-xs font-bold text-slate-400">/${totalQty}</span>
                </div>
            </td>
            <td class="p-4 px-6 text-center">
                <input type="number" value="${outValue}" min="0" max="${qty}" placeholder="0" oninput="updateReturnOut(this)" class="item-out w-16 px-2 py-1.5 border border-slate-200 rounded-lg text-center focus:ring-2 focus:ring-maroon/20 focus:border-maroon outline-none transition-all">
            </td>
            <td class="p-4 px-6 text-center font-bold text-amber-600 item-junk">${junkQty}</td>
            <td class="p-4 px-6 text-center uppercase text-slate-500 item-oum">${item.oum || item.unit || ''}</td>
            <td class="p-4 px-6 text-right item-price" data-price="${unitPrice}">₱ ${unitPrice.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
            <td class="p-4 px-6 text-center item-disc" data-disc="${discountPercent}">${discountPercent.toFixed(2)}%</td>
            <td class="p-4 px-6 text-right font-bold text-maroon item-subtotal">₱ ${subtotal.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
        </tr>`;
}

async function loadPoItems(poId) {
    const tbody = document.getElementById('return-items-tbody');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="10" class="p-4 text-center text-slate-400">Loading items...</td></tr>';

    try {
        const url = (window.returnRoutes?.poItemsUrl || `/admin/purchase/purchase-return/po-items/${poId}`).replace(':poId', poId);
        const response = await fetch(url);
        const result = await response.json();

        if (result.success && result.items.length > 0) {
            const discPct = parseFloat(result.additional_discount_percent || 0);
            const discInput = document.getElementById('return-additional-discount-input');
            if (discInput) discInput.value = discPct;

            tbody.innerHTML = result.items.map(item => purchaseReturnItemRowHtml(item, false)).join('');
            updateReturnTotals();
        } else {
            tbody.innerHTML = '<tr><td colspan="10" class="p-4 text-center text-slate-300 italic">No items found for this invoice.</td></tr>';
        }
    } catch (error) {
        console.error('Error loading PO items:', error);
        tbody.innerHTML = '<tr><td colspan="10" class="p-4 text-center text-red-400 italic">Error loading items.</td></tr>';
    }
}

/**
 * Keep Return QTY / OUT / JUNK consistent and recalculate money.
 */
window.updateReturnItemSubtotal = function(input) {
    const row = input.closest('tr');
    if (!row) return;

    const qtyInput = row.querySelector('.item-qty');
    const outInput = row.querySelector('.item-out');
    const junkEl = row.querySelector('.item-junk');
    const totalQty = Math.max(0, parseInt(row.dataset.totalQty || qtyInput?.max || '0', 10) || 0);

    let qty = Math.max(0, parseInt(qtyInput?.value || '0', 10) || 0);
    if (totalQty > 0 && qty > totalQty) {
        qty = totalQty;
        qtyInput.value = String(qty);
    }

    let outQty = Math.max(0, parseInt(outInput?.value || '0', 10) || 0);
    if (outQty > qty) {
        outQty = qty;
        if (outInput) outInput.value = String(outQty);
    }
    if (outInput) outInput.max = String(qty);
    if (junkEl) junkEl.textContent = String(Math.max(0, qty - outQty));

    const priceEl = row.querySelector('.item-price');
    const price = parseFloat(priceEl?.dataset.price || priceEl?.innerText.replace(/[₱,]/g, '') || '0') || 0;
    const discEl = row.querySelector('.item-disc');
    const disc = parseFloat(discEl?.dataset.disc || discEl?.innerText.replace(/[%]/g, '') || '0') || 0;
    const subtotal = (qty * price) * (1 - (disc / 100));
    const subtotalEl = row.querySelector('.item-subtotal');
    if (subtotalEl) subtotalEl.innerText = `₱ ${subtotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

    updateReturnTotals();
};

window.updateReturnOut = function(input) {
    const row = input.closest('tr');
    const qtyInput = row?.querySelector('.item-qty');
    if (qtyInput) window.updateReturnItemSubtotal(qtyInput);
};

/**
 * Calculate and display return totals
 */
function updateReturnTotals() {
    const tbody = document.getElementById('return-items-tbody');
    if (!tbody) return;
    
    let total = 0;
    const rows = tbody.querySelectorAll('tr');
    
    rows.forEach(row => {
        const subtotalEl = row.querySelector('.item-subtotal');
        if (subtotalEl) {
            const subtotal = parseFloat(subtotalEl.innerText.replace(/[₱,]/g, '')) || 0;
            total += subtotal;
        }
    });
    
    const discInput = document.getElementById('return-additional-discount-input');
    const additionalDiscPercent = parseFloat(discInput?.value || 0);
    const additionalDiscAmount = total * (additionalDiscPercent / 100);
    const grandTotal = total - additionalDiscAmount;
    
    // Update footer displays
    const totalEl = document.getElementById('return-total');
    const grandTotalEl = document.getElementById('return-grand-total');
    const discAmountEl = document.getElementById('return-additional-discount-amount');
    
    if (totalEl) totalEl.innerText = `₱ ${total.toLocaleString(undefined, {minimumFractionDigits: 2})}`;
    if (discAmountEl) discAmountEl.innerText = `% (₱ ${additionalDiscAmount.toLocaleString(undefined, {minimumFractionDigits: 2})})`;
    if (grandTotalEl) grandTotalEl.innerText = `₱ ${grandTotal.toLocaleString(undefined, {minimumFractionDigits: 2})}`;
}

/**
 * Populate Review Data
 */
function populateReviewData() {
    document.getElementById('rev-return-no').innerText = document.getElementById('new-return-no').value;
    document.getElementById('rev-return-date').innerText = document.getElementById('new-return-date').value;
    document.getElementById('rev-invoice-no').innerText = document.getElementById('new-invoice-no').value;
    document.getElementById('rev-supplier-name').innerText = document.getElementById('new-supplier-name').value;
    document.getElementById('rev-remarks').innerText = document.getElementById('new-remarks').value || '---';

    const reviewTbody = document.getElementById('review-items-tbody');
    const sourceTbody = document.getElementById('return-items-tbody');
    if (reviewTbody && sourceTbody) {
        reviewTbody.innerHTML = '';
        const selectedCheckboxes = sourceTbody.querySelectorAll('.item-checkbox:checked');
        let total = 0;

        selectedCheckboxes.forEach(cb => {
            const row = cb.closest('tr');
            const code = row.querySelector('.item-code')?.innerText || '';
            const desc = row.querySelector('.item-description')?.innerText || '';
            const qty = parseInt(row.querySelector('.item-qty')?.value || '0', 10) || 0;
            const totalQty = parseInt(row.dataset.totalQty || '0', 10) || 0;
            const outQty = parseInt(row.querySelector('.item-out')?.value || '0', 10) || 0;
            const junkQty = Math.max(0, qty - outQty);
            const unit = row.querySelector('.item-oum')?.innerText || '';
            const price = row.querySelector('.item-price')?.innerText || '₱ 0.00';
            const disc = row.querySelector('.item-disc')?.innerText || '0.00%';
            const subtotal = row.querySelector('.item-subtotal')?.innerText || '₱ 0.00';

            const newRow = document.createElement('tr');
            newRow.className = 'border-b border-slate-100';
            newRow.innerHTML = `
                <td class="p-3 text-slate-700">${code}</td>
                <td class="p-3 text-slate-600">${desc}</td>
                <td class="p-3 text-center">${qty}/${totalQty}</td>
                <td class="p-3 text-center">${outQty}</td>
                <td class="p-3 text-center text-amber-600">${junkQty}</td>
                <td class="p-3 text-center uppercase">${unit}</td>
                <td class="p-3 text-right">${price}</td>
                <td class="p-3 text-center">${disc}</td>
                <td class="p-3 text-right font-bold text-maroon">${subtotal}</td>`;
            reviewTbody.appendChild(newRow);
            total += parseFloat(subtotal.replace(/[₱,]/g, '')) || 0;
        });

        const discInput = document.getElementById('return-additional-discount-input');
        const additionalDiscPercent = parseFloat(discInput?.value || 0);
        const additionalDiscAmount = total * (additionalDiscPercent / 100);
        const grandTotal = total - additionalDiscAmount;

        const revSubtotal = document.getElementById('rev-subtotal');
        const revAdditionalDisc = document.getElementById('rev-additional-discount');
        const revGrandTotal = document.getElementById('rev-grand-total');

        if (revSubtotal) revSubtotal.innerText = `₱ ${total.toLocaleString(undefined, {minimumFractionDigits: 2})}`;
        if (revAdditionalDisc) revAdditionalDisc.innerText = `${additionalDiscPercent.toFixed(2)}% (₱ ${additionalDiscAmount.toLocaleString(undefined, {minimumFractionDigits: 2})})`;
        if (revGrandTotal) revGrandTotal.innerText = `₱ ${grandTotal.toLocaleString(undefined, {minimumFractionDigits: 2})}`;
    }
}

/**
 * Step 1: Trigger Confirmation Modal
 */
window.submitReturn = function() {
    const isEditing = !!window.editingReturnId;
    const titleEl = document.getElementById('confirm-modal-title');
    const msgEl = document.getElementById('confirm-modal-message');
    if (titleEl) titleEl.textContent = isEditing ? 'Confirm Edit' : 'Confirm Return';
    if (msgEl) msgEl.textContent = isEditing
        ? `Are you sure you want to update this purchase return (${window.editingReturnNumber || ''})? This will update the records in the system.`
        : 'Are you sure you want to finalize this purchase return? This action will record the transaction in the system.';
    toggleModal('confirm-return-modal', true);
};

/**
 * Step 2: Finalize and Show Success
 */
window.finalizeReturn = async function() {
    const returnDate = document.getElementById('new-return-date').value;
    const slipNo = document.getElementById('new-slip-no')?.value.trim() || '';
    const remarks = document.getElementById('new-remarks').value;
    const isEditing = !!window.editingReturnId;

    if (!isEditing) {
        const poId = window.selectedPoId;
        const supplierId = window.selectedSupplierId;
        if (!poId || !supplierId) {
            alert('Please select an invoice first.');
            return;
        }
    }

    // Collect checked items
    const items = [];
    let itemValidationError = '';
    const checkboxes = document.querySelectorAll('#return-items-tbody .item-checkbox:checked');
    checkboxes.forEach(cb => {
        const row = cb.closest('tr');
        const productId = parseInt(row.dataset.productId || '0', 10) || null;
        const productCode = row.querySelector('.item-code')?.innerText.trim() || '';
        const description = row.querySelector('.item-description')?.innerText.trim() || '';
        const qty = parseInt(row.querySelector('.item-qty')?.value || '0', 10) || 0;
        const outQty = parseInt(row.querySelector('.item-out')?.value || '0', 10) || 0;
        const totalQty = parseInt(row.dataset.totalQty || '0', 10) || 0;
        const priceText = row.querySelector('.item-price')?.innerText || '';
        const price = parseFloat(priceText.replace(/[₱,]/g, '')) || 0;
        const discText = row.querySelector('.item-disc')?.innerText || '';
        const disc = parseFloat(discText.replace(/[%]/g, '')) || 0;
        const subtotalText = row.querySelector('.item-subtotal')?.innerText || '';
        const subtotal = parseFloat(subtotalText.replace(/[₱,]/g, '')) || 0;
        const oumText = row.querySelector('.item-oum')?.innerText.trim() || '';

        if (qty < 0 || (totalQty > 0 && qty > totalQty) || outQty < 0 || outQty > qty) {
            itemValidationError = `Invalid QTY/OUT for ${productCode}. OUT must be between 0 and Return QTY, and Return QTY cannot exceed ${totalQty}.`;
            return;
        }

        items.push({
            product_id: productId,
            product_code: productCode,
            description: description,
            quantity: qty,
            out_quantity: outQty,
            unit: oumText,
            unit_price: price,
            discount: disc,
            subtotal: subtotal,
        });
    });

    if (itemValidationError) {
        alert(itemValidationError);
        return;
    }

    if (items.length === 0) {
        alert('Please select at least one item to return.');
        return;
    }

    const totalSubtotal = items.reduce((sum, item) => sum + item.subtotal, 0);
    const discInput = document.getElementById('return-additional-discount-input');
    const additionalDiscPercent = parseFloat(discInput?.value || 0);
    const additionalDiscAmount = totalSubtotal * (additionalDiscPercent / 100);

    try {
        const endpoint = isEditing
            ? (window.returnRoutes?.updateUrl || `/admin/purchase/purchase-return/update/${window.editingReturnId}`).replace(':id', window.editingReturnId)
            : (window.returnRoutes?.processUrl || '/admin/purchase/purchase-return/process');

        const payload = {
            return_date: returnDate,
            slip_no: slipNo,
            remarks: remarks,
            additional_discount_percent: additionalDiscPercent,
            additional_discount_amount: additionalDiscAmount,
            items: items,
        };

        if (!isEditing) {
            payload.po_id = window.selectedPoId;
            payload.supplier_id = window.selectedSupplierId;
        }

        const response = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.csrfToken || '' },
            body: JSON.stringify(payload),
        });
        const result = await response.json();

        if (result.success) {
            toggleModal('confirm-return-modal', false);
            toggleModal('add-return-modal', false);
            resetPurchaseReturnForm();
            await refreshPurchaseReturnData();
            showActionSuccessModal(result.message || (isEditing ? 'Purchase Return updated successfully' : 'Purchase Return processed successfully'));
        } else {
            alert('Error: ' + (result.message || 'Failed to process return.'));
        }
    } catch (error) {
        console.error('Error processing return:', error);
        alert('An error occurred while processing the return.');
    }
};

async function refreshPurchaseReturnData() {
    await Promise.all([
        loadReturnHistory(),
        loadReturnDashboard(),
    ]);
}

function showActionSuccessModal(message) {
    const messageNode = document.getElementById('success-modal-message');
    if (messageNode) {
        messageNode.textContent = message;
    }
    toggleModal('success-modal', true);
}

function resetPurchaseReturnForm() {
    const formFields = [
        'new-invoice-no',
        'new-supplier-name',
        'new-slip-no',
        'new-remarks',
    ];

    formFields.forEach(id => {
        const field = document.getElementById(id);
        if (field) field.value = '';
    });

    const returnNoField = document.getElementById('new-return-no');
    if (returnNoField) returnNoField.value = '00000001';

    const dateInput = document.getElementById('new-return-date');
    if (dateInput) {
        dateInput.value = new Date().toISOString().split('T')[0];
    }

    const returnItemsTbody = document.getElementById('return-items-tbody');
    if (returnItemsTbody) {
        returnItemsTbody.innerHTML = '<tr><td colspan="8" class="p-4 text-center text-slate-300 italic">No items selected yet.</td></tr>';
    }

    const reviewItemsTbody = document.getElementById('review-items-tbody');
    if (reviewItemsTbody) {
        reviewItemsTbody.innerHTML = '';
    }

    // Reset additional discount and totals
    const discInput = document.getElementById('return-additional-discount-input');
    if (discInput) discInput.value = '0';
    const discAmountEl = document.getElementById('return-additional-discount-amount');
    if (discAmountEl) discAmountEl.innerText = '% (₱ 0.00)';
    const totalEl = document.getElementById('return-total');
    if (totalEl) totalEl.innerText = '₱ 0.00';
    const grandTotalEl = document.getElementById('return-grand-total');
    if (grandTotalEl) grandTotalEl.innerText = '₱ 0.00';
    const revSubtotal = document.getElementById('rev-subtotal');
    if (revSubtotal) revSubtotal.innerText = '₱ 0.00';
    const revDisc = document.getElementById('rev-additional-discount');
    if (revDisc) revDisc.innerText = '0.00% (₱ 0.00)';
    const revGrand = document.getElementById('rev-grand-total');
    if (revGrand) revGrand.innerText = '₱ 0.00';

    window.selectedPoId = null;
    window.selectedSupplierId = null;
    window.editingReturnId = null;
    window.editingReturnNumber = null;
    window.additionalDiscountPercent = 0;

    const titleEl = document.getElementById('add-return-modal-title');
    const subtitleEl = document.getElementById('add-return-modal-subtitle');
    if (titleEl) titleEl.textContent = 'Purchase Return Processing';
    if (subtitleEl) subtitleEl.textContent = 'Follow the steps to record return';

    if (typeof window.goToStep === 'function') {
        window.goToStep(1);
    }
}

/**
 * View Detail Modal
 */
/**
 * Print Return Slip
 */
/**
 * Edit Return - pre-fill modal with existing data
 */
window.editReturn = async function(id) {
    try {
        const url = (window.returnRoutes?.detailUrl || `/admin/purchase/purchase-return/detail/${id}`).replace(':id', id);
        const response = await fetch(url);
        const result = await response.json();

        if (result.success) {
            const ret = result.return;

            window.editingReturnId = id;
            window.editingReturnNumber = ret.return_number;

            const titleEl = document.getElementById('add-return-modal-title');
            const subtitleEl = document.getElementById('add-return-modal-subtitle');
            if (titleEl) titleEl.textContent = 'Edit Purchase Return';
            if (subtitleEl) subtitleEl.textContent = `Editing: ${ret.return_number}`;

            document.getElementById('new-return-no').value = ret.return_number;
            const slipNoInput = document.getElementById('new-slip-no');
            if (slipNoInput) slipNoInput.value = ret.slip_no || '';
            document.getElementById('new-return-date').value = ret.date || '';
            document.getElementById('new-invoice-no').value = ret.invoice_no || '';
            document.getElementById('new-supplier-name').value = ret.supplier_name || '';
            document.getElementById('new-remarks').value = ret.remarks && ret.remarks !== '---' ? ret.remarks : '';

            window.additionalDiscountPercent = parseFloat(ret.additional_discount_percent || 0);
            window.additionalDiscountAmount = parseFloat(ret.additional_discount_amount || 0);
            const discInput = document.getElementById('return-additional-discount-input');
            if (discInput) discInput.value = window.additionalDiscountPercent;

            const tbody = document.getElementById('return-items-tbody');
            if (ret.items && ret.items.length > 0) {
                tbody.innerHTML = ret.items.map(item => purchaseReturnItemRowHtml(item, true)).join('');
                updateReturnTotals();
            }

            toggleModal('add-return-modal', true);
            goToStep(1);
        } else {
            alert('Failed to load return details for editing.');
        }
    } catch (error) {
        console.error('Error loading return for edit:', error);
        alert('An error occurred while trying to edit.');
    }
};

window.printReturn = async function(id) {
    try {
        const url = (window.returnRoutes?.detailUrl || `/admin/purchase/purchase-return/detail/${id}`).replace(':id', id);
        const response = await fetch(url);
        const result = await response.json();

        if (result.success) {
            const ret = result.return;
            const container = document.getElementById('print-slips-container');
            const template = document.getElementById('single-print-slip');
            if (!container || !template) return;

            container.innerHTML = '';
            const clone = template.content.cloneNode(true);

            clone.querySelector('.p-invoice-no').textContent = ret.invoice_no || '---';
            clone.querySelector('.p-return-no').textContent = ret.return_number || '---';
            clone.querySelector('.p-supplier').textContent = ret.supplier_name || '---';
            clone.querySelector('.p-date').textContent = ret.date || '---';

            const tbody = clone.querySelector('.p-items-tbody');
            if (ret.items && ret.items.length > 0) {
                const itemsSubtotal = ret.items.reduce((sum, item) => sum + parseFloat(item.subtotal || 0), 0);
                const additionalDiscPercent = parseFloat(ret.additional_discount_percent || 0);
                const additionalDiscAmount = itemsSubtotal * (additionalDiscPercent / 100);
                const grandTotal = itemsSubtotal - additionalDiscAmount;

                tbody.innerHTML = ret.items.map(item => {
                    const disc = parseFloat(item.discount || 0);
                    return `<tr>
                        <td class="c">${item.quantity}</td>
                        <td class="c">${item.oum || ''}</td>
                        <td>${item.product_code}</td>
                        <td>${item.description || ''}</td>
                        <td class="r">${parseFloat(item.unit_price).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                        <td class="c">${disc.toFixed(2)}%</td>
                        <td class="r">${parseFloat(item.subtotal).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                    </tr>`;
                }).join('');

                clone.querySelector('.p-subtotal').textContent = `₱ ${itemsSubtotal.toLocaleString(undefined, {minimumFractionDigits: 2})}`;
                clone.querySelector('.p-additional-discount').textContent = `${additionalDiscPercent.toFixed(2)}% (₱ ${additionalDiscAmount.toLocaleString(undefined, {minimumFractionDigits: 2})})`;
                clone.querySelector('.p-grand-total').textContent = `₱ ${grandTotal.toLocaleString(undefined, {minimumFractionDigits: 2})}`;
            } else {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center">No items.</td></tr>';
                clone.querySelector('.p-subtotal').textContent = '₱ 0.00';
                clone.querySelector('.p-additional-discount').textContent = '0.00% (₱ 0.00)';
                clone.querySelector('.p-grand-total').textContent = '₱ 0.00';
            }

            container.appendChild(clone);

            setTimeout(() => {
                window.print();
            }, 500);
        } else {
            alert('Failed to load return details for printing.');
        }
    } catch (error) {
        console.error('Error printing return:', error);
        alert('An error occurred while trying to print.');
    }
};

window.viewReturnDetail = async function(data) {
    const tbody = document.getElementById('view-items-tbody');
    if (!tbody) return;

    toggleModal('view-return-modal', true);
    tbody.innerHTML = '<tr><td colspan="9" class="p-4 text-center text-slate-400">Loading...</td></tr>';

    try {
        const url = (window.returnRoutes?.detailUrl || `/admin/purchase/purchase-return/detail/${data.id}`).replace(':id', data.id);
        const response = await fetch(url);
        const result = await response.json();

        if (result.success) {
            const ret = result.return;
            document.getElementById('view-transaction-label').textContent = `Transaction: ${ret.return_number}`;
            document.getElementById('view-return-no').textContent = ret.return_number;
            const viewSlipNo = document.getElementById('view-slip-no');
            if (viewSlipNo) viewSlipNo.textContent = ret.slip_no || '---';
            document.getElementById('view-return-date').textContent = ret.date;
            document.getElementById('view-invoice-no').textContent = ret.invoice_no;
            document.getElementById('view-supplier-name').textContent = ret.supplier_name;
            document.getElementById('view-remarks').textContent = ret.remarks;

            if (ret.items.length > 0) {
                const itemsSubtotal = ret.items.reduce((sum, item) => sum + parseFloat(item.subtotal || 0), 0);
                const additionalDiscPercent = parseFloat(ret.additional_discount_percent || 0);
                const additionalDiscAmount = itemsSubtotal * (additionalDiscPercent / 100);
                const totalAmount = itemsSubtotal - additionalDiscAmount;

                tbody.innerHTML = ret.items.map(item => {
                    const disc = parseFloat(item.discount || 0);
                    return `
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="p-4 px-6 font-bold text-maroon">${item.product_code}</td>
                        <td class="p-4 px-6 text-slate-700">${item.description || ''}</td>
                        <td class="p-4 px-6 text-center font-bold whitespace-nowrap">${item.quantity}/${item.total_quantity ?? item.quantity}</td>
                        <td class="p-4 px-6 text-center font-bold text-maroon">${item.out_quantity ?? 0}</td>
                        <td class="p-4 px-6 text-center font-bold text-amber-600">${item.junk_quantity ?? Math.max(0, item.quantity - (item.out_quantity || 0))}</td>
                        <td class="p-4 px-6 text-center uppercase text-slate-500">${item.oum}</td>
                        <td class="p-4 px-6 text-right">₱ ${parseFloat(item.unit_price).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                        <td class="p-4 px-6 text-center text-slate-400">${disc.toFixed(2)}%</td>
                        <td class="p-4 px-6 text-right font-bold text-maroon">₱ ${parseFloat(item.subtotal).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                    </tr>
                `}).join('');

                document.getElementById('view-items-count').textContent = `${ret.items.length} items`;
                document.getElementById('view-subtotal-amount').textContent = `₱ ${itemsSubtotal.toLocaleString(undefined, {minimumFractionDigits: 2})}`;
                const addlDiscEl = document.getElementById('view-additional-discount');
                if (addlDiscEl) addlDiscEl.textContent = `${additionalDiscPercent.toFixed(2)}% (₱ ${additionalDiscAmount.toLocaleString(undefined, {minimumFractionDigits: 2})})`;
                document.getElementById('view-total-amount').textContent = `₱ ${totalAmount.toLocaleString(undefined, {minimumFractionDigits: 2})}`;
            } else {
                tbody.innerHTML = '<tr><td colspan="9" class="p-4 text-center text-slate-300 italic">No items found.</td></tr>';
                document.getElementById('view-items-count').textContent = '0 items';
                document.getElementById('view-subtotal-amount').textContent = '₱ 0.00';
                const addlDiscEl = document.getElementById('view-additional-discount');
                if (addlDiscEl) addlDiscEl.textContent = '0.00% (₱ 0.00)';
                document.getElementById('view-total-amount').textContent = '₱ 0.00';
            }

            if (window.lucide) lucide.createIcons();
        }
    } catch (error) {
        console.error('Error loading return detail:', error);
        tbody.innerHTML = '<tr><td colspan="9" class="p-4 text-center text-red-400 italic">Error loading details.</td></tr>';
    }
};
