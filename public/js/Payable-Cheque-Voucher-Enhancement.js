/**
 * Payable Cheque Voucher Enhancement Script
 * Features:
 * 1. Sudden Return Insert - Save draft state when modal is closed
 * 2. Edit Button & Modal - Edit existing vouchers
 * 3. Backend Integration - CRUD operations
 */

(function() {
    'use strict';

    // ==================== CONFIGURATION ====================
    const AUTOSAVE_INTERVAL = 5000; // Auto-save every 5 seconds
    let autosaveTimer = null;
    let currentDraftState = null;
    let currentSupplierId = null;
    let isEditMode = false;
    let editingVoucherId = null;

    // ==================== HELPER FUNCTIONS ====================
    function showNotification(message, type = 'success') {
        const colors = {
            success: 'bg-emerald-500',
            error: 'bg-red-500',
            info: 'bg-blue-500',
            warning: 'bg-amber-500'
        };

        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg z-[999] transition-opacity duration-300`;
        notification.textContent = message;
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    function getCurrentVoucherState() {
        // Capture the current state of the voucher form
        const state = {
            currentStep: window.pcvCurrentStep || 1,
            supplierId: currentSupplierId,
            refNo: document.getElementById('pcv-ref-no')?.value || '',
            particulars: document.getElementById('pcv-particulars')?.value || '',
            selectedInvoices: [],
            selectedSuddenReturns: [],
            paymentMethod: document.querySelector('input[name="pcv-payment-method"]:checked')?.value || 'Gcash',
            paymentDetails: {
                accountNo: document.getElementById('pcv-account-no')?.value || '',
                bankName: document.getElementById('pcv-bank-name')?.value || '',
                checkNo: document.getElementById('pcv-check-no')?.value || '',
                checkDate: document.getElementById('pcv-check-date')?.value || '',
                paymentDate: document.getElementById('pcv-payment-date')?.value || '',
                gcashReferenceNo: document.getElementById('pcv-gcash-reference-no')?.value || '',
                cashCredit: document.getElementById('pcv-cash-credit')?.value || '',
                bankCredit: document.getElementById('pcv-bank-credit')?.value || ''
            },
            payments: window.pcvGetPaymentDetails ? window.pcvGetPaymentDetails() : [],
            showCheckSummary: !!document.getElementById('pcv-show-check-summary')?.checked,
            timestamp: new Date().toISOString()
        };

        // Capture selected invoices
        const invoiceCheckboxes = document.querySelectorAll('#pcv-invoice-tbody input[type="checkbox"]:checked');
        invoiceCheckboxes.forEach(checkbox => {
            const row = checkbox.closest('tr');
            if (row) {
                const invoiceData = {
                    purchase_no: row.dataset.purchaseNo,
                    invoice_no: row.dataset.invoiceNo,
                    amount_paid: row.querySelector('[data-field="amountPaid"]')?.value || '0',
                    remarks: row.querySelector('[data-field="remarks"]')?.value || '',
                    discount1: row.querySelector('[data-field="discount1"]')?.value || '0'
                };
                state.selectedInvoices.push(invoiceData);
            }
        });

        // Capture selected sudden returns
        const returnCheckboxes = document.querySelectorAll('#pcv-sudden-returns-tbody input[type="checkbox"]:checked');
        returnCheckboxes.forEach(checkbox => {
            const row = checkbox.closest('tr');
            if (row) {
                const returnData = {
                    return_no: row.dataset.returnNo,
                    amount: row.dataset.amount,
                    remarks: row.querySelector('[data-field="returnRemarks"]')?.value || ''
                };
                state.selectedSuddenReturns.push(returnData);
            }
        });

        return state;
    }

    async function saveDraftState(state) {
        if (!state.supplierId) {
            console.warn('No supplier ID, cannot save draft');
            return;
        }

        try {
            const response = await fetch(window.pcvRoutes.draftSave, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({
                    supplier_id: state.supplierId,
                    draft_state: state
                })
            });

            const data = await response.json();
            if (data.success) {
                console.log('Draft saved successfully');
                return true;
            } else {
                console.error('Failed to save draft:', data.message);
                return false;
            }
        } catch (error) {
            console.error('Error saving draft:', error);
            return false;
        }
    }

    async function loadDraftState(supplierId) {
        try {
            const response = await fetch(`${window.pcvRoutes.draftGet}?supplier_id=${supplierId}`);
            const data = await response.json();

            if (data.success && data.has_draft) {
                return data.draft_state;
            }
            return null;
        } catch (error) {
            console.error('Error loading draft:', error);
            return null;
        }
    }

    async function clearDraftState(supplierId) {
        try {
            const response = await fetch(window.pcvRoutes.draftClear, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({
                    supplier_id: supplierId
                })
            });

            const data = await response.json();
            return data.success;
        } catch (error) {
            console.error('Error clearing draft:', error);
            return false;
        }
    }

    function restoreDraftState(state) {
        if (!state) return;

        // Restore form fields
        if (state.refNo) {
            const refNoField = document.getElementById('pcv-ref-no');
            if (refNoField) refNoField.value = state.refNo;
        }

        if (state.particulars) {
            const particularsField = document.getElementById('pcv-particulars');
            if (particularsField) particularsField.value = state.particulars;
        }

        // Restore payment method
        if (state.paymentMethod) {
            const paymentMethodRadio = document.querySelector(`input[name="pcv-payment-method"][value="${state.paymentMethod}"]`);
            if (paymentMethodRadio) paymentMethodRadio.checked = true;
        }

        // Restore all payment rows when available.
        if (Array.isArray(state.payments) && state.payments.length && window.pcvSetPaymentDetails) {
            window.pcvSetPaymentDetails(state.payments);
        }

        const checkSummaryToggle = document.getElementById('pcv-show-check-summary');
        if (checkSummaryToggle) checkSummaryToggle.checked = !!state.showCheckSummary;

        // Backward-compatible restore for old drafts.
        if (state.paymentDetails && !(Array.isArray(state.payments) && state.payments.length)) {
            const pd = state.paymentDetails;
            if (pd.accountNo) document.getElementById('pcv-account-no').value = pd.accountNo;
            if (pd.bankName) document.getElementById('pcv-bank-name').value = pd.bankName;
            if (pd.checkNo) document.getElementById('pcv-check-no').value = pd.checkNo;
            if (pd.checkDate) document.getElementById('pcv-check-date').value = pd.checkDate;
            if (pd.paymentDate) document.getElementById('pcv-payment-date').value = pd.paymentDate;
            if (pd.gcashReferenceNo && document.getElementById('pcv-gcash-reference-no')) document.getElementById('pcv-gcash-reference-no').value = pd.gcashReferenceNo;
            if (pd.cashCredit) document.getElementById('pcv-cash-credit').value = pd.cashCredit;
            if (pd.bankCredit) document.getElementById('pcv-bank-credit').value = pd.bankCredit;
        }

        showNotification('Previous draft restored', 'info');
    }

    // ==================== AUTO-SAVE FUNCTIONALITY ====================
    function startAutosave() {
        stopAutosave(); // Clear any existing timer

        autosaveTimer = setInterval(() => {
            const state = getCurrentVoucherState();
            if (state.supplierId && !isEditMode) {
                saveDraftState(state);
            }
        }, AUTOSAVE_INTERVAL);
    }

    function stopAutosave() {
        if (autosaveTimer) {
            clearInterval(autosaveTimer);
            autosaveTimer = null;
        }
    }

    // ==================== MODAL CLOSE INTERCEPTION ====================
    const originalPcvCloseModal = window.pcvCloseModal;
    window.pcvCloseModal = async function() {
        stopAutosave();

        // Save draft before closing if not in edit mode
        if (currentSupplierId && !isEditMode) {
            const state = getCurrentVoucherState();
            await saveDraftState(state);
            showNotification('Progress saved as draft', 'success');
        }

        // Call original close function
        if (originalPcvCloseModal) {
            originalPcvCloseModal();
        }

        // Reset state
        currentSupplierId = null;
        isEditMode = false;
        editingVoucherId = null;
        window.editingVoucherId = null;
        if (window.pcvEditPrecheckedReturns) window.pcvEditPrecheckedReturns = null;
    };

    // ==================== PROCEED BUTTON INTERCEPTION ====================
    const originalProceedFunction = window.pcvProceedToVoucher;
    window.pcvProceedToVoucher = async function(supplierId) {
        currentSupplierId = supplierId;
        isEditMode = false;
        editingVoucherId = null;
        window.editingVoucherId = null;

        // Call original function
        if (originalProceedFunction) {
            await originalProceedFunction(supplierId);
        }

        // Load draft state after modal opens
        setTimeout(async () => {
            const draftState = await loadDraftState(supplierId);
            if (draftState) {
                const restore = confirm('You have unsaved progress for this supplier. Do you want to restore it?');
                if (restore) {
                    restoreDraftState(draftState);
                } else {
                    await clearDraftState(supplierId);
                }
            }

            // Start autosave
            startAutosave();
        }, 500);
    };

    // ==================== FINALIZE VOUCHER INTERCEPTION ====================
    const originalFinalizeVoucher = window.pcvFinalizeVoucher;
    window.pcvFinalizeVoucher = async function() {
        stopAutosave();

        // Clear draft after successful finalization
        if (currentSupplierId && !isEditMode) {
            await clearDraftState(currentSupplierId);
        }

        // Call original function
        if (originalFinalizeVoucher) {
            originalFinalizeVoucher();
        }
    };

    // ==================== EDIT BUTTON FUNCTIONALITY ====================
    
    window.pcvOpenEditModal = async function(voucherId) {
        try {
            const requestedVoucherId = Number(voucherId || 0);
            if (!requestedVoucherId) {
                throw new Error('Invalid voucher ID for editing.');
            }

            // Do not enter edit mode until the server confirms the voucher record.
            const response = await fetch(`${window.pcvRoutes.show}/${requestedVoucherId}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Failed to load voucher');
            }

            const voucher = data.voucher;
            const voucherFields = data.voucher_fields || {};
            const resolvedVoucherId = Number(voucher?.id || 0);
            if (!resolvedVoucherId) {
                throw new Error('The server returned an invalid voucher record.');
            }

            // Use the authoritative voucher ID returned by the accounting backend.
            editingVoucherId = resolvedVoucherId;
            window.editingVoucherId = resolvedVoucherId;
            isEditMode = true;
            currentSupplierId = Number(voucher.supplier_id || 0) || null;

            // Load supplier data with voucher_id to include existing invoices
            const supplierRes = await fetch(`${window.pcvRoutes.supplier}/${voucher.supplier_id}?voucher_id=${resolvedVoucherId}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            const supplierData = await supplierRes.json();
            if (!supplierData.success) {
                throw new Error(supplierData.message || 'Failed to load supplier data');
            }

            // Build currentSupplier with pre-checked invoices
            const supplierInvoices = (supplierData.invoices || []).map((inv) => {
                const existingInv = (voucher.invoices || []).find(
                    vi => String(vi.purchase_order_id) === String(inv.sourceId)
                );
                const isSelected = !!existingInv;
                const existingPaid = existingInv ? Number(existingInv.amount_paid || 0) : 0;
                return {
                    ...inv,
                    id: inv.id || 'po-' + inv.sourceId,
                    selected: isSelected,
                    userPaidAmount: existingPaid || inv.amountPaid || 0,
                    amountPaid: existingPaid || inv.amountPaid || 0,
                    discount1: existingInv ? Number(existingInv.discount_1 || inv.discount1 || 0) : (inv.discount1 || 0),
                    discount2: existingInv ? Number(existingInv.discount_2 || inv.discount2 || 0) : (inv.discount2 || 0),
                    returnAmount: existingInv ? Number(existingInv.return_amount ?? inv.returnAmount ?? 0) : Number(inv.returnAmount || 0),
                    totalReturns: existingInv ? Number(existingInv.total_returns ?? inv.totalReturns ?? 0) : Number(inv.totalReturns || 0),
                    rsDetails: existingInv ? (existingInv.rs_details || inv.rsDetails || '') : (inv.rsDetails || ''),
                    returnNumber: existingInv ? (existingInv.return_number || inv.returnNumber || '') : (inv.returnNumber || ''),
                };
            });

            // Pre-checked sudden returns (to be used by pcvCallFetchSuddenReturns)
            // Support both camelCase (Laravel JSON) and snake_case keys.
            // Split DB rows must be grouped by return_number with summed total.
            if (!voucher.sudden_returns && !voucher.suddenReturns) {
                console.warn('[PCV] sudden_returns not found in voucher. Keys:', Object.keys(voucher));
            }
            const rawReturns = (voucher.sudden_returns || voucher.suddenReturns || []);
            const groupedMap = {};
            rawReturns.forEach(sr => {
                const key = sr.return_number || 'unknown';
                if (!groupedMap[key]) {
                    groupedMap[key] = {
                        po_id: sr.purchase_order_id,
                        return_number: sr.return_number,
                        total_amount: 0,
                        remarks: sr.remarks || '',
                        slip_no: sr.slip_no || '',
                        date: sr.return_date || sr.date || null,
                        items: Array.isArray(sr.items) ? sr.items : [],
                    };
                }
                groupedMap[key].total_amount += Number(sr.return_amount || 0);
                if (!groupedMap[key].slip_no && sr.slip_no) groupedMap[key].slip_no = sr.slip_no;
                if ((!groupedMap[key].items || !groupedMap[key].items.length) && Array.isArray(sr.items)) groupedMap[key].items = sr.items;
                if (!groupedMap[key].date && (sr.return_date || sr.date)) groupedMap[key].date = sr.return_date || sr.date;
            });
            const precheckedReturns = Object.values(groupedMap);

            // Set internal state via blade's exposed function
            if (window.pcvSetEditState) {
                window.pcvSetEditState({
                    currentSupplier: {
                        id: voucher.supplier_id,
                        supplier: voucher.supplier_name,
                        contact: supplierData.supplier.contact || '',
                        contactPerson: supplierData.supplier.contactPerson || '',
                        address: supplierData.supplier.address || '',
                        totalAmount: supplierData.supplier.totalAmount || 0,
                        invoices: supplierInvoices
                    },
                    selectedSuddenReturns: precheckedReturns,
                    additionalDiscount: voucherFields.additional_discount || 0,
                });
            }

            // Store prechecked returns for the fetch function to use
            window.pcvEditPrecheckedReturns = precheckedReturns;

            // Populate modal header
            document.getElementById('pcv-modal-supplier-title').textContent = voucher.supplier_name;
            document.getElementById('pcv-voucher-no').textContent = voucher.voucher_no;
            document.getElementById('pcv-voucher-date').textContent = voucher.voucher_date ? voucher.voucher_date.slice(0, 10) : new Date().toISOString().slice(0, 10);
            document.getElementById('pcv-supplier-name').textContent = voucher.supplier_name;
            document.getElementById('pcv-supplier-contact').textContent = supplierData.supplier.contact || '---';
            document.getElementById('pcv-supplier-person').textContent = supplierData.supplier.contactPerson || '---';
            document.getElementById('pcv-supplier-address').textContent = supplierData.supplier.address || '---';

            // Set reference fields
            document.getElementById('pcv-ref-no').value = voucher.reference_no || '';
            document.getElementById('pcv-particulars').value = voucher.particulars || '';
            const addlDiscPct = Number(voucherFields.additional_discount || 0);

            // Restore every saved payment detail (including negative credit rows).
            if (window.pcvSetPaymentDetails) {
                window.pcvSetPaymentDetails(voucher.payments || []);
            } else {
                const paymentMethodRadio = document.querySelector(`input[name="pcv-payment-method"][value="${voucher.payment_method}"]`);
                if (paymentMethodRadio) paymentMethodRadio.checked = true;
                const payment = (voucher.payments || [])[0] || {};
                const accEl = document.getElementById('pcv-account-no');
                const bankEl = document.getElementById('pcv-bank-name');
                const chkNoEl = document.getElementById('pcv-check-no');
                const chkDateEl = document.getElementById('pcv-check-date');
                const payDateEl = document.getElementById('pcv-payment-date');
                const gcashRefEl = document.getElementById('pcv-gcash-reference-no');
                const bankCreditEl = document.getElementById('pcv-bank-credit');
                const cashCreditEl = document.getElementById('pcv-cash-credit');
                if (accEl) accEl.value = payment.account_no || '';
                if (bankEl) bankEl.value = payment.bank_name || '';
                if (chkNoEl) chkNoEl.value = payment.check_no || '';
                if (chkDateEl) chkDateEl.value = payment.check_date || '';
                if (payDateEl) payDateEl.value = payment.payment_date || new Date().toISOString().slice(0, 10);
                if (gcashRefEl) gcashRefEl.value = payment.reference_no || '';
                const savedCredit = Number(payment.credit_amount || 0);
                if (bankCreditEl) bankCreditEl.value = savedCredit.toFixed(2);
                if (cashCreditEl) cashCreditEl.value = savedCredit.toFixed(2);
            }

            const checkSummaryToggle = document.getElementById('pcv-show-check-summary');
            if (checkSummaryToggle) checkSummaryToggle.checked = !!(voucher.show_check_summary ?? voucherFields.show_check_summary ?? false);

            // Set additional discount
            const addlDiscInput = document.getElementById('pcv-additional-discount');
            if (addlDiscInput) addlDiscInput.value = addlDiscPct;

            // Update payment fields visibility
            if (typeof updatePaymentFields === 'function') {
                updatePaymentFields();
            } else {
                // Manual toggle
                const isBank = ['Cheque', 'Bank Transfer'].includes(voucher.payment_method);
                const bf = document.getElementById('pcv-bank-fields');
                const cf = document.getElementById('pcv-cash-fields');
                if (bf) bf.classList.toggle('hidden', !isBank);
                if (cf) cf.classList.toggle('hidden', isBank);
            }

            // Open the modal
            const proceedModal = document.getElementById('pcv-proceed-modal');
            if (proceedModal) {
                proceedModal.classList.remove('hidden');
                proceedModal.classList.add('flex');
            }

            // Refresh UI with pre-checked data
            if (window.pcvRefreshUI) {
                window.pcvRefreshUI();
            }

            // Fetch sudden returns with voucher_id for pre-checked state
            if (window.pcvCallFetchSuddenReturns && voucher.supplier_id) {
                window.pcvCallFetchSuddenReturns(voucher.supplier_id);
            }

            if (window.lucide) {
                lucide.createIcons();
            }

            showNotification('Edit mode: modify and proceed to save changes', 'info');

        } catch (error) {
            // A partially initialized edit must never remain saveable.
            editingVoucherId = null;
            window.editingVoucherId = null;
            isEditMode = false;
            currentSupplierId = null;
            const proceedModal = document.getElementById('pcv-proceed-modal');
            if (proceedModal) {
                proceedModal.classList.add('hidden');
                proceedModal.classList.remove('flex');
            }
            console.error('Error opening edit modal:', error);
            showNotification(error.message || 'Failed to load voucher for editing', 'error');
        }
    };

    window.pcvCloseEditModal = function() {
        // Close the Proceed modal and reset edit state
        const proceedModal = document.getElementById('pcv-proceed-modal');
        if (proceedModal) {
            proceedModal.classList.add('hidden');
            proceedModal.classList.remove('flex');
        }
        editingVoucherId = null;
        window.editingVoucherId = null;
        isEditMode = false;
        currentSupplierId = null;
        window.pcv_selectedSR = null;
        if (window.pcvEditPrecheckedReturns) window.pcvEditPrecheckedReturns = null;
    };

    window.pcvSubmitEditedVoucher = async function() {
        try {
            // Close confirm modal
            const confirmModal = document.getElementById('pcv-confirm-modal');
            if (confirmModal) {
                confirmModal.classList.add('hidden');
                confirmModal.classList.remove('flex');
            }

            // Get selected invoices
            let invoices = [];
            if (window.pcvSelectedVoucherInvoices) {
                invoices = window.pcvSelectedVoucherInvoices();
            } else {
                // Fallback: read from DOM
                const checkedRows = document.querySelectorAll('#pcv-invoice-tbody input[type="checkbox"]:checked');
                checkedRows.forEach(cb => {
                    const row = cb.closest('tr');
                    if (row) {
                        const amtPaid = row.querySelector('[data-amount-paid]')?.value || '0';
                        invoices.push({ amountPaid: Number(amtPaid) });
                    }
                });
            }

            if (!invoices.length) {
                showNotification('Please select at least one invoice with an amount paid.', 'error');
                return;
            }

            const editVoucherId = Number(window.editingVoucherId || editingVoucherId || 0);
            if (!editVoucherId) {
                showNotification('No voucher is being edited.', 'error');
                return;
            }

            // Build payload using the blade's voucherPayload function
            let payload = null;
            if (window.pcvVoucherPayload) {
                payload = window.pcvVoucherPayload(invoices);
            }

            if (!payload) {
                showNotification('Failed to build voucher payload.', 'error');
                return;
            }

            // Send update request
            const response = await fetch(`${window.pcvRoutes.updateNew}/${editVoucherId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (result.success) {
                // Close all modals
                const proceedModal = document.getElementById('pcv-proceed-modal');
                if (proceedModal) {
                    proceedModal.classList.add('hidden');
                    proceedModal.classList.remove('flex');
                }

                // Reset edit state
                editingVoucherId = null;
                window.editingVoucherId = null;
                isEditMode = false;
                currentSupplierId = null;
                window.pcv_selectedSR = null;
                if (window.pcvEditPrecheckedReturns) window.pcvEditPrecheckedReturns = null;

                // Show success notification
                showNotification('Voucher updated successfully', 'success');

                // Refresh page data
                if (window.pcvRefreshHistoryData) {
                    setTimeout(() => window.pcvRefreshHistoryData(), 1000);
                }
                // Reload page to refresh all data
                setTimeout(() => window.location.reload(), 1500);
            } else {
                throw new Error(result.message || 'Failed to update voucher');
            }

        } catch (error) {
            console.error('Error saving edited voucher:', error);
            showNotification(error.message || 'Failed to save changes', 'error');
        }
    };

    // ==================== ADD EDIT BUTTONS TO HISTORY TABLE ====================
    function addEditButtonsToHistory() {
        // This function should be called after the history table is rendered
        const historyTbody = document.getElementById('pcv-history-tbody');
        if (!historyTbody) return;

        // Observer to watch for table updates
        const observer = new MutationObserver(() => {
            const actionCells = historyTbody.querySelectorAll('td:last-child');
            actionCells.forEach(cell => {
                if (cell.querySelector('.pcv-edit-btn')) return; // Already has edit button

                const viewBtn = cell.querySelector('button');
                if (viewBtn) {
                    const voucherId = viewBtn.getAttribute('onclick')?.match(/\d+/)?.[0];
                    if (voucherId) {
                        const editBtn = document.createElement('button');
                        editBtn.className = 'pcv-edit-btn ml-2 px-4 py-2 rounded-lg bg-amber-600 text-white text-[9px] font-black uppercase tracking-widest hover:bg-amber-700 transition-colors';
                        editBtn.innerHTML = '<i data-lucide="edit-3" class="w-3 h-3 inline mr-1"></i> Edit';
                        editBtn.setAttribute('onclick', `window.pcvOpenEditModal(${voucherId})`);
                        cell.appendChild(editBtn);

                        // Re-initialize lucide icons
                        if (window.lucide) {
                            lucide.createIcons();
                        }
                    }
                }
            });
        });

        observer.observe(historyTbody, { childList: true, subtree: true });
    }

    // ==================== INITIALIZATION ====================
    document.addEventListener('DOMContentLoaded', () => {
        console.log('Payable Cheque Voucher Enhancement loaded');

        // Add edit buttons to history table
        addEditButtonsToHistory();

        // Listen for history tab activation
        const historyTab = document.getElementById('pcv-tab-history');
        if (historyTab) {
            historyTab.addEventListener('click', () => {
                setTimeout(() => addEditButtonsToHistory(), 500);
            });
        }
    });

    // Cleanup on page unload
    window.addEventListener('beforeunload', () => {
        stopAutosave();
    });

})();
