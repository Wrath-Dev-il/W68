(function () {
    const perPage = 50;
    const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
    const qtyFormat = new Intl.NumberFormat('en-PH', { maximumFractionDigits: 4 });
    const today = new Date().toISOString().slice(0, 10);
    const $ = (id) => document.getElementById(id);
    const clean = (value) => String(value ?? '').toLowerCase();
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    let rows = [];
    let filters = {};
    let page = 1;
    let selectedSupplier = null;
    let selectedVoucher = null;
    let selectedParticular = null;
    let nextVoucherNo = 'ECV-0001';
    let cachedSuppliers = null;
    let itemSequence = 0;

    function setModal(id, show) {
        const modal = $(id);
        if (!modal) return;
        modal.classList.toggle('hidden', !show);
        modal.classList.toggle('flex', show);
        if (show && window.lucide) window.lucide.createIcons();
    }

    function showConfirm(title, message) {
        return new Promise((resolve) => {
            $('ecv-confirm-title').textContent = title;
            $('ecv-confirm-message').textContent = message;
            setModal('ecv-confirm-modal', true);
            const yes = $('ecv-confirm-yes');
            const no = $('ecv-confirm-cancel');
            const cleanup = () => { yes.removeEventListener('click', onYes); no.removeEventListener('click', onNo); setModal('ecv-confirm-modal', false); };
            const onYes = () => { cleanup(); resolve(true); };
            const onNo = () => { cleanup(); resolve(false); };
            yes.addEventListener('click', onYes);
            no.addEventListener('click', onNo);
        });
    }

    function showSuccess(title, message) {
        return new Promise((resolve) => {
            $('ecv-success-title').textContent = title;
            $('ecv-success-message').textContent = message;
            setModal('ecv-success-modal', true);
            const ok = $('ecv-success-ok');
            const done = () => { ok.removeEventListener('click', done); setModal('ecv-success-modal', false); resolve(); };
            ok.addEventListener('click', done);
        });
    }

    function filteredRows() {
        return rows.filter((row) => Object.entries(filters).every(([key, value]) => {
            if (!value) return true;
            const lookup = key === 'totalAmount' ? peso.format(row.totalAmount || 0) : row[key];
            return clean(lookup).includes(clean(value));
        }));
    }

    function renderStats(stats) {
        if (!stats) return;
        $('ecv-stat-total').textContent = stats.total ?? 0;
        $('ecv-stat-monthly').textContent = stats.monthly ?? 0;
        $('ecv-stat-yearly').textContent = stats.yearly ?? 0;
        $('ecv-stat-one-time').textContent = stats.oneTime ?? 0;
    }

    function renderPagination(total) {
        const pages = Math.max(1, Math.ceil(total / perPage));
        page = Math.min(page, pages);
        const container = $('ecv-pagination');
        if (!container) return;
        const buttons = [];
        buttons.push(`<button class="ecv-page-btn rounded-lg border border-slate-200 bg-white px-3 py-2 text-[10px] font-black uppercase tracking-widest text-slate-600" data-page="${Math.max(1, page - 1)}" ${page === 1 ? 'disabled' : ''}>Previous</button>`);
        for (let p = 1; p <= pages; p++) {
            if (pages > 12 && Math.abs(p - page) > 2 && p !== 1 && p !== pages) { if (p === 2 || p === pages - 1) buttons.push('<span class="px-1 text-slate-300">...</span>'); continue; }
            buttons.push(`<button class="ecv-page-btn rounded-lg border px-3 py-2 text-[10px] font-black ${p === page ? 'bg-maroon-900 border-maroon-900 text-white' : 'bg-white border-slate-200 text-slate-600'}" data-page="${p}">${p}</button>`);
        }
        buttons.push(`<button class="ecv-page-btn rounded-lg border border-slate-200 bg-white px-3 py-2 text-[10px] font-black uppercase tracking-widest text-slate-600" data-page="${Math.min(pages, page + 1)}" ${page === pages ? 'disabled' : ''}>Next</button>`);
        container.innerHTML = buttons.join('');
        container.querySelectorAll('button[data-page]').forEach((button) => button.addEventListener('click', () => { page = Number(button.dataset.page); renderTable(); }));
    }

    function renderTable() {
        const data = filteredRows();
        const pages = Math.max(1, Math.ceil(data.length / perPage));
        if (page > pages) page = pages;
        const start = (page - 1) * perPage;
        const visible = data.slice(start, start + perPage);
        $('ecv-table-body').innerHTML = visible.map((row) => `
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-4"><span class="ecv-tag">${escapeHtml(row.voucherNo)}</span></td>
                <td class="px-4 py-4 font-black text-slate-800">${escapeHtml(row.name)}</td>
                <td class="px-4 py-4 text-right font-black text-slate-800">${peso.format(Number(row.totalAmount || 0))}</td>
                <td class="px-4 py-4 font-bold text-slate-600">${escapeHtml(row.remarks || '---')}</td>
                <td class="px-4 py-4 text-center"><button type="button" class="ecv-view-btn inline-flex items-center gap-2 rounded-lg bg-sky-600 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-white hover:bg-sky-700" data-voucher-id="${row.id}"><i data-lucide="eye" class="h-3.5 w-3.5"></i><span>View</span></button></td>
            </tr>`).join('') || '<tr><td colspan="5" class="px-4 py-12 text-center text-sm font-bold text-slate-400">No expense vouchers found.</td></tr>';
        $('ecv-page-summary').textContent = `Showing ${data.length ? start + 1 : 0}-${Math.min(start + perPage, data.length)} of ${data.length}`;
        renderPagination(data.length);
        if (window.lucide) window.lucide.createIcons();
    }

    function renderSuppliers(list) {
        const term = clean($('ecv-supplier-search')?.value);
        const visible = list.filter((supplier) => clean(`${supplier.supplier_code} ${supplier.name}`).includes(term));
        $('ecv-supplier-body').innerHTML = visible.map((supplier) => `<tr class="cursor-pointer hover:bg-slate-50" data-supplier-id="${supplier.id}" data-supplier-code="${escapeHtml(supplier.supplier_code)}" data-supplier-name="${escapeHtml(supplier.name)}"><td class="px-4 py-3 font-black text-maroon-800">${escapeHtml(supplier.supplier_code)}</td><td class="px-4 py-3 font-bold text-slate-700">${escapeHtml(supplier.name)}</td></tr>`).join('') || '<tr><td colspan="2" class="px-4 py-8 text-center text-xs font-bold text-slate-400">No suppliers found.</td></tr>';
    }

    function particularGroupHtml(values = {}) {
        const index = ++itemSequence;
        const method = values.payment_method || 'Cash';
        const type = values.expense_type || 'Monthly';
        return `<div class="ecv-particular-group rounded-2xl border border-slate-200 bg-slate-50/50 p-5" data-item-index="${index}">
            <div class="mb-4 flex items-center justify-between gap-3"><span class="text-[10px] font-black uppercase tracking-widest text-maroon-800">Particular Group</span><button type="button" class="ecv-remove-particular rounded-lg border border-red-200 bg-white px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-red-600 hover:bg-red-50">Remove</button></div>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                <div class="space-y-1"><label class="ecv-label">Invoice No</label><input class="ecv-field ecv-item-invoice-no" type="text" value="${escapeHtml(values.invoice_no || '')}" placeholder="Invoice number"></div>
                <div class="space-y-1 md:col-span-1 xl:col-span-3"><label class="ecv-label">Particulars</label><input class="ecv-field ecv-item-particulars" type="text" value="${escapeHtml(values.particulars || '')}" placeholder="Expense particulars" required></div>
                <div class="space-y-1"><label class="ecv-label">Unit</label><input class="ecv-field ecv-item-unit" type="text" value="${escapeHtml(values.unit || '')}" placeholder="PC, SET, LOT..."></div>
                <div class="space-y-1"><label class="ecv-label">Unit Price</label><input class="ecv-field ecv-item-unit-price" type="number" min="0" step="0.01" value="${values.unit_price ?? ''}"></div>
                <div class="space-y-1"><label class="ecv-label">Qty</label><input class="ecv-field ecv-item-qty" type="number" min="0" step="0.0001" value="${values.qty ?? ''}"></div>
                <div class="space-y-1"><label class="ecv-label">Billing Date</label><input class="ecv-field ecv-item-billing-date" type="date" value="${escapeHtml(values.billing_date || today)}"></div>
                <div class="space-y-1"><label class="ecv-label">Expense Type</label><select class="ecv-field ecv-item-expense-type"><option value="Monthly" ${type === 'Monthly' ? 'selected' : ''}>Monthly</option><option value="Yearly" ${type === 'Yearly' ? 'selected' : ''}>Yearly</option><option value="One time" ${type === 'One time' ? 'selected' : ''}>One time</option></select></div>
                <div class="space-y-1"><label class="ecv-label">Amount</label><input class="ecv-field ecv-item-amount" type="number" min="0" step="0.01" value="${Number(values.amount || 0)}" required></div>
                <div class="space-y-1"><label class="ecv-label">Paid Amount</label><input class="ecv-field ecv-item-paid-amount" type="number" min="0" step="0.01" value="${Number(values.paid_amount || 0)}" required></div>
                <div class="space-y-1"><label class="ecv-label">Payment Method</label><select class="ecv-field ecv-item-payment-method"><option value="Gcash" ${method === 'Gcash' ? 'selected' : ''}>Gcash</option><option value="Cash" ${method === 'Cash' ? 'selected' : ''}>Cash</option><option value="Bank" ${method === 'Bank' ? 'selected' : ''}>Bank</option></select></div>
                <div class="ecv-item-bank-fields ${method === 'Bank' ? '' : 'hidden'} md:col-span-2 xl:col-span-4 grid grid-cols-1 md:grid-cols-3 gap-4 rounded-xl border border-slate-200 bg-white p-4"><div class="space-y-1"><label class="ecv-label">Account No.</label><input class="ecv-field ecv-item-account-no" type="text" value="${escapeHtml(values.account_no || '')}"></div><div class="space-y-1"><label class="ecv-label">Check Date</label><input class="ecv-field ecv-item-check-date" type="date" value="${escapeHtml(values.check_date || today)}"></div><div class="space-y-1"><label class="ecv-label">Check No.</label><input class="ecv-field ecv-item-check-no" type="text" value="${escapeHtml(values.check_no || '')}"></div></div>
            </div></div>`;
    }

    function attachParticularContainerEvents(container) {
        if (!container || container.dataset.bound === '1') return;
        container.dataset.bound = '1';
        container.addEventListener('click', (event) => { const remove = event.target.closest('.ecv-remove-particular'); if (!remove) return; const groups = container.querySelectorAll('.ecv-particular-group'); if (groups.length > 1) remove.closest('.ecv-particular-group')?.remove(); });
        container.addEventListener('change', (event) => {
            const group = event.target.closest('.ecv-particular-group');
            if (event.target.classList.contains('ecv-item-payment-method')) group?.querySelector('.ecv-item-bank-fields')?.classList.toggle('hidden', event.target.value !== 'Bank');
            if (event.target.classList.contains('ecv-item-expense-type')) { const date = group?.querySelector('.ecv-item-billing-date'); if (date) { date.disabled = event.target.value === 'One time'; if (date.disabled) date.value = ''; } }
        });
    }

    function addParticular(containerId, values = {}) { const container = $(containerId); container.insertAdjacentHTML('beforeend', particularGroupHtml(values)); attachParticularContainerEvents(container); if (window.lucide) window.lucide.createIcons(); }

    function collectParticulars(containerId) {
        const items = Array.from($(containerId).querySelectorAll('.ecv-particular-group')).map((group) => {
            const type = group.querySelector('.ecv-item-expense-type').value;
            const method = group.querySelector('.ecv-item-payment-method').value;
            const unitPrice = group.querySelector('.ecv-item-unit-price').value;
            const qty = group.querySelector('.ecv-item-qty').value;
            return {
                invoice_no: group.querySelector('.ecv-item-invoice-no').value.trim() || null,
                particulars: group.querySelector('.ecv-item-particulars').value.trim(),
                unit: group.querySelector('.ecv-item-unit').value.trim() || null,
                unit_price: unitPrice === '' ? null : Number(unitPrice),
                qty: qty === '' ? null : Number(qty),
                billing_date: type === 'One time' ? null : (group.querySelector('.ecv-item-billing-date').value || null),
                expense_type: type,
                amount: Number(group.querySelector('.ecv-item-amount').value || 0),
                paid_amount: Number(group.querySelector('.ecv-item-paid-amount').value || 0),
                payment_method: method,
                account_no: method === 'Bank' ? (group.querySelector('.ecv-item-account-no').value.trim() || null) : null,
                check_date: method === 'Bank' ? (group.querySelector('.ecv-item-check-date').value || null) : null,
                check_no: method === 'Bank' ? (group.querySelector('.ecv-item-check-no').value.trim() || null) : null,
            };
        });
        if (!items.length) throw new Error('Add at least one particular.');
        if (items.some((item) => !item.particulars)) throw new Error('Particulars is required for every group.');
        return items;
    }

    async function loadData() {
        const res = await fetch('/hatdog/public/admin/accounting/expense-cheque-voucher/data', { headers: { Accept: 'application/json' } });
        const json = await res.json();
        if (!res.ok || !json.success) throw new Error(json.message || 'Failed to load data');
        rows = json.vouchers || []; nextVoucherNo = json.nextVoucherNo || 'ECV-0001'; renderStats(json.stats); renderTable();
    }

    async function loadSuppliers() {
        if (cachedSuppliers) return renderSuppliers(cachedSuppliers);
        const res = await fetch('/hatdog/public/admin/accounting/expense-cheque-voucher/suppliers', { headers: { Accept: 'application/json' } });
        const json = await res.json(); if (!res.ok || !json.success) throw new Error(json.message || 'Failed to load suppliers'); cachedSuppliers = json.suppliers || []; renderSuppliers(cachedSuppliers);
    }

    function openAddModal() {
        $('ecv-add-form').reset(); selectedSupplier = null; $('ecv-voucher-preview').textContent = nextVoucherNo; $('ecv-supplier-name').textContent = 'Search supplier'; $('ecv-supplier-code').value = ''; $('ecv-remarks').value = ''; $('ecv-add-particulars-container').innerHTML = ''; addParticular('ecv-add-particulars-container'); setModal('ecv-add-modal', true);
    }

    async function submitVoucherItems(payload) {
        const res = await fetch('/hatdog/public/admin/accounting/expense-cheque-voucher/store', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' }, body: JSON.stringify(payload) });
        const json = await res.json(); if (!res.ok || !json.success) throw new Error(json.message || (json.errors ? Object.values(json.errors).flat().join('\n') : 'Save failed.')); return json;
    }

    async function saveExpense(event) {
        event.preventDefault(); if (!selectedSupplier) return alert('Please select a supplier.');
        let items; try { items = collectParticulars('ecv-add-particulars-container'); } catch (e) { return alert(e.message); }
        if (!(await showConfirm('Save Expense', `Save ${items.length} particulars group${items.length === 1 ? '' : 's'} under ${$('ecv-voucher-preview').textContent.trim()}?`))) return;
        try { await submitVoucherItems({ voucher_no: $('ecv-voucher-preview').textContent.trim(), supplier_id: selectedSupplier.id, supplier_code: selectedSupplier.code, supplier_name: selectedSupplier.name, ref: $('ecv-remarks').value.trim() || null, items }); page = 1; setModal('ecv-add-modal', false); await loadData(); await showSuccess('Expense Saved', 'The expense voucher and its particulars were saved successfully.'); } catch (e) { alert(e.message || 'Unable to save expense voucher.'); }
    }

    function renderViewVoucher(voucher) {
        selectedVoucher = voucher;
        $('ecv-view-heading').textContent = `${voucher.voucherNo} • ${voucher.name}`; $('ecv-view-voucher-no').textContent = voucher.voucherNo || '---'; $('ecv-view-name').textContent = voucher.name || '---'; $('ecv-view-remarks').textContent = voucher.remarks || '---';
        const details = Array.isArray(voucher.items) ? voucher.items : [];
        $('ecv-view-items-body').innerHTML = details.map((item) => `<tr>
            <td class="px-4 py-4 font-black text-slate-700">${escapeHtml(item.invoiceNo || '---')}</td><td class="px-4 py-4 font-bold text-slate-700">${escapeHtml(item.particulars || '---')}</td><td class="px-4 py-4 font-bold text-slate-600">${escapeHtml(item.unit || '---')}</td><td class="px-4 py-4 text-right font-black text-slate-700">${item.unitPrice == null ? '---' : peso.format(Number(item.unitPrice))}</td><td class="px-4 py-4 text-right font-black text-slate-700">${item.qty == null ? '---' : qtyFormat.format(Number(item.qty))}</td><td class="px-4 py-4 font-bold text-slate-600">${escapeHtml(item.billingDate || '---')}</td><td class="px-4 py-4 font-bold text-slate-600">${escapeHtml(item.expenseType || '---')}</td><td class="px-4 py-4 text-right font-black text-slate-800">${peso.format(Number(item.amount || 0))}</td><td class="px-4 py-4 text-right font-black text-emerald-600">${peso.format(Number(item.paidAmount || 0))}</td><td class="px-4 py-4 font-bold text-slate-600">${escapeHtml(item.paymentMethod || '---')}</td><td class="px-4 py-4 text-center"><div class="inline-flex items-center justify-center gap-2"><button type="button" class="ecv-edit-particular-btn inline-flex items-center gap-1 rounded-lg bg-amber-500 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-white hover:bg-amber-600 disabled:opacity-50" data-particular-id="${item.id ?? ''}" ${item.id == null ? 'disabled' : ''}><i data-lucide="pencil" class="h-3.5 w-3.5"></i><span>Edit</span></button><button type="button" class="ecv-delete-particular-btn inline-flex items-center gap-1 rounded-lg bg-red-600 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-white hover:bg-red-700 disabled:opacity-50" data-particular-id="${item.id ?? ''}" ${item.id == null ? 'disabled' : ''}><i data-lucide="trash-2" class="h-3.5 w-3.5"></i><span>Delete</span></button></div></td>
        </tr>`).join('') || '<tr><td colspan="11" class="px-4 py-10 text-center text-xs font-bold text-slate-400">No particulars found.</td></tr>';
        if (window.lucide) window.lucide.createIcons(); setModal('ecv-view-modal', true);
    }

    function openAddMoreModal() {
        if (!selectedVoucher) return; $('ecv-add-more-heading').textContent = `${selectedVoucher.voucherNo} • ${selectedVoucher.name}`; $('ecv-add-more-container').innerHTML = ''; addParticular('ecv-add-more-container', { expense_type: selectedVoucher.items?.[0]?.expenseType || 'Monthly', payment_method: selectedVoucher.items?.[0]?.paymentMethod || 'Cash' }); setModal('ecv-view-modal', false); setModal('ecv-add-more-modal', true);
    }

    async function saveMoreParticulars(event) {
        event.preventDefault(); if (!selectedVoucher) return;
        let items; try { items = collectParticulars('ecv-add-more-container'); } catch (e) { return alert(e.message); }
        if (!(await showConfirm('Add More Particulars', `Add ${items.length} new group${items.length === 1 ? '' : 's'} to ${selectedVoucher.voucherNo}?`))) return;
        const voucherId = selectedVoucher.id;
        try { await submitVoucherItems({ voucher_no: selectedVoucher.voucherNo, supplier_id: selectedVoucher.supplierId, supplier_code: selectedVoucher.supplierCode, supplier_name: selectedVoucher.name, ref: selectedVoucher.remarks || null, items }); setModal('ecv-add-more-modal', false); await loadData(); selectedVoucher = rows.find((row) => row.id === voucherId) || null; await showSuccess('Particulars Added', 'The new particulars groups were saved.'); if (selectedVoucher) renderViewVoucher(selectedVoucher); } catch (e) { alert(e.message || 'Unable to add particulars.'); }
    }

    function openEditParticular(itemId) {
        if (!selectedVoucher) return;
        const item = selectedVoucher.items?.find((detail) => Number(detail.id) === Number(itemId)); if (!item) return;
        selectedParticular = item;
        $('ecv-edit-particular-id').value = item.id; $('ecv-edit-particular-heading').textContent = `${selectedVoucher.voucherNo} • ${selectedVoucher.name}`; $('ecv-edit-invoice-no').value = item.invoiceNo || ''; $('ecv-edit-particulars').value = item.particulars || ''; $('ecv-edit-unit').value = item.unit || ''; $('ecv-edit-unit-price').value = item.unitPrice ?? ''; $('ecv-edit-qty').value = item.qty ?? ''; $('ecv-edit-billing-date').value = item.billingDate || ''; $('ecv-edit-expense-type').value = item.expenseType || 'Monthly'; $('ecv-edit-amount').value = Number(item.amount || 0); $('ecv-edit-paid-amount').value = Number(item.paidAmount || 0); $('ecv-edit-payment-method').value = item.paymentMethod || 'Cash'; $('ecv-edit-account-no').value = item.accountNo || ''; $('ecv-edit-check-date').value = item.checkDate || ''; $('ecv-edit-check-no').value = item.checkNo || '';
        updateEditConditionalFields(); setModal('ecv-view-modal', false); setModal('ecv-edit-particular-modal', true);
    }

    function updateEditConditionalFields() {
        const method = $('ecv-edit-payment-method').value; $('ecv-edit-bank-fields').classList.toggle('hidden', method !== 'Bank'); const oneTime = $('ecv-edit-expense-type').value === 'One time'; $('ecv-edit-billing-date').disabled = oneTime; if (oneTime) $('ecv-edit-billing-date').value = '';
    }

    async function deleteParticular(particularId) {
        if (!selectedVoucher) return;
        const id = Number(particularId);
        const item = selectedVoucher.items.find((entry) => Number(entry.id) === id);
        if (!item) return alert('Particular group not found.');

        const label = item.invoiceNo || item.particulars || `Particular #${id}`;
        if (!(await showConfirm('Delete Particular Group', `Delete "${label}" from ${selectedVoucher.voucherNo}? This cannot be undone.`))) return;

        const voucherId = selectedVoucher.id;
        try {
            const res = await fetch(`/hatdog/public/admin/accounting/expense-cheque-voucher/particulars/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
            });
            const json = await res.json();
            if (!res.ok || !json.success) throw new Error(json.message || 'Delete failed.');

            await loadData();
            selectedVoucher = rows.find((row) => row.id === voucherId) || null;
            selectedParticular = null;
            await showSuccess('Particular Deleted', 'The particular group was deleted and the voucher totals were recalculated.');
            if (selectedVoucher) renderViewVoucher(selectedVoucher);
        } catch (e) {
            alert(e.message || 'Unable to delete particular group.');
        }
    }

    async function updateParticular(event) {
        event.preventDefault(); if (!selectedVoucher || !selectedParticular) return;
        const id = Number($('ecv-edit-particular-id').value); const method = $('ecv-edit-payment-method').value; const unitPrice = $('ecv-edit-unit-price').value; const qty = $('ecv-edit-qty').value;
        const payload = { invoice_no: $('ecv-edit-invoice-no').value.trim() || null, particulars: $('ecv-edit-particulars').value.trim(), unit: $('ecv-edit-unit').value.trim() || null, unit_price: unitPrice === '' ? null : Number(unitPrice), qty: qty === '' ? null : Number(qty), billing_date: $('ecv-edit-expense-type').value === 'One time' ? null : ($('ecv-edit-billing-date').value || null), expense_type: $('ecv-edit-expense-type').value, amount: Number($('ecv-edit-amount').value || 0), paid_amount: Number($('ecv-edit-paid-amount').value || 0), payment_method: method, account_no: method === 'Bank' ? ($('ecv-edit-account-no').value.trim() || null) : null, check_date: method === 'Bank' ? ($('ecv-edit-check-date').value || null) : null, check_no: method === 'Bank' ? ($('ecv-edit-check-no').value.trim() || null) : null };
        if (!payload.particulars) return alert('Particulars is required.');
        if (!(await showConfirm('Update Particular Group', 'Save the changes to this particular group?'))) return;
        const voucherId = selectedVoucher.id;
        try {
            const res = await fetch(`/hatdog/public/admin/accounting/expense-cheque-voucher/particulars/${id}`, { method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' }, body: JSON.stringify(payload) });
            const json = await res.json(); if (!res.ok || !json.success) throw new Error(json.message || (json.errors ? Object.values(json.errors).flat().join('\n') : 'Update failed.'));
            setModal('ecv-edit-particular-modal', false); await loadData(); selectedVoucher = rows.find((row) => row.id === voucherId) || null; selectedParticular = null; await showSuccess('Particular Updated', 'The particular group was updated successfully.'); if (selectedVoucher) renderViewVoucher(selectedVoucher);
        } catch (e) { alert(e.message || 'Unable to update particular group.'); }
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (!$('page-expense-cheque-voucher')) return;
        if ($('page-expense-cheque-voucher')?.dataset.ecvVersion === '2') return;
        $('ecv-open-add-btn')?.addEventListener('click', openAddModal); $('ecv-add-form')?.addEventListener('submit', saveExpense); $('ecv-add-particular-btn')?.addEventListener('click', () => addParticular('ecv-add-particulars-container')); $('ecv-add-more-row-btn')?.addEventListener('click', () => addParticular('ecv-add-more-container')); $('ecv-add-more-form')?.addEventListener('submit', saveMoreParticulars); $('ecv-view-add-more-btn')?.addEventListener('click', openAddMoreModal); $('ecv-edit-particular-form')?.addEventListener('submit', updateParticular); $('ecv-edit-payment-method')?.addEventListener('change', updateEditConditionalFields); $('ecv-edit-expense-type')?.addEventListener('change', updateEditConditionalFields);
        $('ecv-open-supplier-btn')?.addEventListener('click', () => { loadSuppliers().catch((e) => alert(e.message)); setModal('ecv-supplier-modal', true); setTimeout(() => $('ecv-supplier-search')?.focus(), 0); });
        $('ecv-supplier-search')?.addEventListener('input', () => cachedSuppliers && renderSuppliers(cachedSuppliers)); $('ecv-supplier-body')?.addEventListener('click', (event) => { const row = event.target.closest('tr[data-supplier-code]'); if (!row) return; selectedSupplier = { id: Number(row.dataset.supplierId), code: row.dataset.supplierCode, name: row.dataset.supplierName }; $('ecv-supplier-name').textContent = selectedSupplier.name; $('ecv-supplier-code').value = selectedSupplier.code; setModal('ecv-supplier-modal', false); });
        document.querySelectorAll('[data-ecv-close]').forEach((item) => item.addEventListener('click', () => { setModal(`ecv-${item.dataset.ecvClose}-modal`, false); if (item.dataset.ecvClose === 'edit-particular' && selectedVoucher) renderViewVoucher(selectedVoucher); }));
        document.querySelectorAll('[data-ecv-filter]').forEach((input) => input.addEventListener('input', () => { filters[input.dataset.ecvFilter] = input.value; page = 1; renderTable(); }));
        $('ecv-table-body')?.addEventListener('click', (event) => { const view = event.target.closest('.ecv-view-btn'); if (!view) return; const voucher = rows.find((row) => row.id === Number(view.dataset.voucherId)); if (voucher) renderViewVoucher(voucher); });
        $('ecv-view-items-body')?.addEventListener('click', (event) => { const edit = event.target.closest('.ecv-edit-particular-btn'); if (edit && !edit.disabled) return openEditParticular(edit.dataset.particularId); const remove = event.target.closest('.ecv-delete-particular-btn'); if (remove && !remove.disabled) deleteParticular(remove.dataset.particularId); });
        attachParticularContainerEvents($('ecv-add-particulars-container')); attachParticularContainerEvents($('ecv-add-more-container'));
        loadData().catch((e) => console.error('ECV loadData error:', e));
    });
})();

(function () {
    'use strict';

    const perPage = 50;
    const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
    const qtyFormat = new Intl.NumberFormat('en-PH', { maximumFractionDigits: 4 });
    const $ = (id) => document.getElementById(id);
    const clean = (value) => String(value ?? '').toLowerCase();
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const pageMarker = '/accounting/expense-cheque-voucher';
    const path = window.location.pathname;
    const markerIndex = path.indexOf(pageMarker);
    const pageBase = markerIndex >= 0 ? path.slice(0, markerIndex + pageMarker.length) : pageMarker;
    const apiBase = `${pageBase}/v2`;
    const suppliersUrl = `${pageBase}/suppliers`;
    const today = new Date().toISOString().slice(0, 10);

    let rows = [];
    let filters = {};
    let page = 1;
    let nextVoucherNo = 'ECV-0001';
    let selectedSupplier = null;
    let selectedVoucher = null;
    let cachedSuppliers = null;
    let formMode = 'add';
    let rowSequence = 0;

    function setModal(id, show) {
        const modal = $(id);
        if (!modal) return;
        modal.classList.toggle('hidden', !show);
        modal.classList.toggle('flex', show);
        if (show && window.lucide) window.lucide.createIcons();
    }

    function showConfirm(title, message) {
        return new Promise((resolve) => {
            $('ecv2-confirm-title').textContent = title;
            $('ecv2-confirm-message').textContent = message;
            setModal('ecv2-confirm-modal', true);
            const yes = $('ecv2-confirm-yes');
            const no = $('ecv2-confirm-cancel');
            const cleanup = () => {
                yes.removeEventListener('click', onYes);
                no.removeEventListener('click', onNo);
                setModal('ecv2-confirm-modal', false);
            };
            const onYes = () => { cleanup(); resolve(true); };
            const onNo = () => { cleanup(); resolve(false); };
            yes.addEventListener('click', onYes);
            no.addEventListener('click', onNo);
        });
    }

    function showSuccess(title, message) {
        return new Promise((resolve) => {
            $('ecv2-success-title').textContent = title;
            $('ecv2-success-message').textContent = message;
            setModal('ecv2-success-modal', true);
            const ok = $('ecv2-success-ok');
            const done = () => {
                ok.removeEventListener('click', done);
                setModal('ecv2-success-modal', false);
                resolve();
            };
            ok.addEventListener('click', done);
        });
    }

    async function readJsonResponse(response) {
        const text = await response.text();
        let json = null;
        try { json = text ? JSON.parse(text) : {}; } catch (_) {}
        if (!json) {
            const short = text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 240);
            throw new Error(short || `Request failed with HTTP ${response.status}.`);
        }
        if (!response.ok || json.success === false) {
            const validation = json.errors ? Object.values(json.errors).flat().join('\n') : '';
            throw new Error(json.message || validation || `Request failed with HTTP ${response.status}.`);
        }
        return json;
    }

    async function request(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers: {
                Accept: 'application/json',
                ...(options.body ? { 'Content-Type': 'application/json' } : {}),
                ...(options.method && options.method !== 'GET' ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                ...(options.headers || {}),
            },
        });
        return readJsonResponse(response);
    }

    function filteredRows() {
        return rows.filter((row) => Object.entries(filters).every(([key, value]) => {
            if (!value) return true;
            return clean(row[key]).includes(clean(value));
        }));
    }

    function renderPagination(total) {
        const pages = Math.max(1, Math.ceil(total / perPage));
        page = Math.min(page, pages);
        const container = $('ecv2-pagination');
        const parts = [];
        parts.push(`<button class="ecv2-page-btn rounded-lg border border-slate-200 bg-white px-3 py-2 text-[10px] font-black uppercase tracking-widest text-slate-600" data-page="${Math.max(1, page - 1)}" ${page === 1 ? 'disabled' : ''}>Previous</button>`);
        for (let p = 1; p <= pages; p++) {
            if (pages > 12 && Math.abs(p - page) > 2 && p !== 1 && p !== pages) {
                if (p === 2 || p === pages - 1) parts.push('<span class="px-1 text-slate-300">...</span>');
                continue;
            }
            parts.push(`<button class="ecv2-page-btn rounded-lg border px-3 py-2 text-[10px] font-black ${p === page ? 'bg-maroon-900 border-maroon-900 text-white' : 'bg-white border-slate-200 text-slate-600'}" data-page="${p}">${p}</button>`);
        }
        parts.push(`<button class="ecv2-page-btn rounded-lg border border-slate-200 bg-white px-3 py-2 text-[10px] font-black uppercase tracking-widest text-slate-600" data-page="${Math.min(pages, page + 1)}" ${page === pages ? 'disabled' : ''}>Next</button>`);
        container.innerHTML = parts.join('');
        container.querySelectorAll('button[data-page]').forEach((button) => button.addEventListener('click', () => {
            page = Number(button.dataset.page);
            renderTable();
        }));
    }

    function multiline(values, formatter = (v) => v) {
        if (!Array.isArray(values) || !values.length) return '<span class="text-slate-400">---</span>';
        return values.map((value) => `<div class="whitespace-nowrap">${escapeHtml(formatter(value))}</div>`).join('');
    }

    function renderTable() {
        const data = filteredRows();
        const pages = Math.max(1, Math.ceil(data.length / perPage));
        if (page > pages) page = pages;
        const start = (page - 1) * perPage;
        const visible = data.slice(start, start + perPage);
        $('ecv2-table-body').innerHTML = visible.map((row) => `
            <tr class="align-top hover:bg-slate-50">
                <td class="px-4 py-4"><span class="ecv-tag">${escapeHtml(row.voucherNo)}</span></td>
                <td class="px-4 py-4 font-black text-slate-800">${escapeHtml(row.supplierName || '---')}</td>
                <td class="px-4 py-4 font-black text-slate-800">${escapeHtml(row.particular || '---')}</td>
                <td class="px-4 py-4 font-bold text-slate-600">${escapeHtml(row.remarks || '---')}</td>
                <td class="px-4 py-4 font-bold text-slate-600">${multiline((row.bankDetails || []).map((item) => item.checkDate || '---'))}</td>
                <td class="px-4 py-4 font-bold text-slate-600">${multiline((row.bankDetails || []).map((item) => item.bankName || '---'))}</td>
                <td class="px-4 py-4 font-bold text-slate-600">${multiline((row.bankDetails || []).map((item) => item.checkNo || '---'))}</td>
                <td class="px-4 py-4 text-right font-black text-slate-800">${multiline((row.bankDetails || []).map((item) => Number(item.creditAmount || 0)), (value) => peso.format(Number(value || 0)))}</td>
                <td class="px-4 py-4 text-center">
                    <div class="inline-flex flex-wrap items-center justify-center gap-2">
                        <button type="button" class="ecv2-view-btn inline-flex h-9 w-9 items-center justify-center rounded-lg bg-sky-600 text-white shadow-sm hover:bg-sky-700" data-id="${row.id}" title="View" aria-label="View voucher ${escapeHtml(row.voucherNo)}"><i data-lucide="eye" class="h-4 w-4"></i></button>
                        <button type="button" class="ecv2-edit-btn inline-flex h-9 w-9 items-center justify-center rounded-lg bg-amber-500 text-white shadow-sm hover:bg-amber-600" data-id="${row.id}" title="Edit" aria-label="Edit voucher ${escapeHtml(row.voucherNo)}"><i data-lucide="pencil" class="h-4 w-4"></i></button>
                        <button type="button" class="ecv2-delete-btn inline-flex h-9 w-9 items-center justify-center rounded-lg bg-red-600 text-white shadow-sm hover:bg-red-700" data-id="${row.id}" title="Delete" aria-label="Delete voucher ${escapeHtml(row.voucherNo)}"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                        <button type="button" class="ecv2-print-btn inline-flex h-9 w-9 items-center justify-center rounded-lg bg-slate-700 text-white shadow-sm hover:bg-slate-800" data-id="${row.id}" title="Print" aria-label="Print voucher ${escapeHtml(row.voucherNo)}"><i data-lucide="printer" class="h-4 w-4"></i></button>
                    </div>
                </td>
            </tr>`).join('') || '<tr><td colspan="9" class="px-4 py-12 text-center text-sm font-bold text-slate-400">No expense vouchers found.</td></tr>';
        $('ecv2-page-summary').textContent = `Showing ${data.length ? start + 1 : 0}-${Math.min(start + perPage, data.length)} of ${data.length}`;
        renderPagination(data.length);
        if (window.lucide) window.lucide.createIcons();
    }

    async function loadData() {
        const json = await request(`${apiBase}/data`, { method: 'GET' });
        rows = json.vouchers || [];
        nextVoucherNo = json.nextVoucherNo || 'ECV-0001';
        renderTable();
    }

    function renderSuppliers(list) {
        const term = clean($('ecv2-supplier-search')?.value);
        const visible = list.filter((supplier) => clean(`${supplier.supplier_code} ${supplier.name}`).includes(term));
        $('ecv2-supplier-body').innerHTML = visible.map((supplier) => `
            <tr class="cursor-pointer hover:bg-slate-50" data-supplier-id="${supplier.id}" data-supplier-code="${escapeHtml(supplier.supplier_code)}" data-supplier-name="${escapeHtml(supplier.name)}">
                <td class="px-4 py-3 font-black text-maroon-800">${escapeHtml(supplier.supplier_code)}</td>
                <td class="px-4 py-3 font-bold text-slate-700">${escapeHtml(supplier.name)}</td>
            </tr>`).join('') || '<tr><td colspan="2" class="px-4 py-8 text-center text-xs font-bold text-slate-400">No suppliers found.</td></tr>';
    }

    async function loadSuppliers() {
        if (cachedSuppliers) return renderSuppliers(cachedSuppliers);
        const json = await request(suppliersUrl, { method: 'GET' });
        cachedSuppliers = json.suppliers || [];
        renderSuppliers(cachedSuppliers);
    }

    function expenseItemHtml(values = {}) {
        const id = ++rowSequence;
        return `<div class="ecv2-expense-row rounded-xl border border-slate-200 bg-white p-4" data-row="${id}">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-3 items-end">
                <div class="space-y-1 xl:col-span-2"><label class="ecv-label">Particular Item</label><input class="ecv-field ecv2-item-particular" type="text" value="${escapeHtml(values.particularItem || '')}" required></div>
                <div class="space-y-1"><label class="ecv-label">Unit</label><input class="ecv-field ecv2-item-unit" type="text" value="${escapeHtml(values.unit || '')}" placeholder="PC, LOT, SET..."></div>
                <div class="space-y-1"><label class="ecv-label">QTY</label><input class="ecv-field ecv2-item-qty" type="number" min="0.0001" step="0.0001" value="${values.qty ?? 1}" required></div>
                <div class="space-y-1"><label class="ecv-label">Unit Price</label><input class="ecv-field ecv2-item-unit-price" type="number" min="0" step="0.01" value="${values.unitPrice ?? 0}" required></div>
            </div>
            <div class="mt-3 flex items-center justify-between gap-3"><span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Line Amount: <strong class="ecv2-line-amount text-slate-800">${peso.format(Number(values.amount ?? ((Number(values.qty ?? 1)) * Number(values.unitPrice ?? 0))))}</strong></span><button type="button" class="ecv2-remove-expense rounded-lg border border-red-200 bg-white px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-red-600 hover:bg-red-50">Remove</button></div>
        </div>`;
    }

    function bankDetailHtml(values = {}) {
        const id = ++rowSequence;
        return `<div class="ecv2-bank-row rounded-xl border border-slate-200 bg-white p-4" data-row="${id}">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3 items-end">
                <div class="space-y-1"><label class="ecv-label">Bank Name</label><input class="ecv-field ecv2-bank-name" type="text" value="${escapeHtml(values.bankName || '')}" placeholder="Bank name"></div>
                <div class="space-y-1"><label class="ecv-label">Check No.</label><input class="ecv-field ecv2-check-no" type="text" value="${escapeHtml(values.checkNo || '')}" placeholder="Check number"></div>
                <div class="space-y-1"><label class="ecv-label">Check Date</label><input class="ecv-field ecv2-check-date" type="date" value="${escapeHtml(values.checkDate || '')}"></div>
                <div class="space-y-1"><label class="ecv-label">Credit Amount</label><input class="ecv-field ecv2-credit-amount" type="number" step="0.01" value="${values.creditAmount ?? 0}"></div>
            </div>
            <div class="mt-3 flex justify-end"><button type="button" class="ecv2-remove-bank rounded-lg border border-red-200 bg-white px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-red-600 hover:bg-red-50">Remove</button></div>
        </div>`;
    }

    function addExpenseItem(values = {}) {
        $('ecv2-expense-items').insertAdjacentHTML('beforeend', expenseItemHtml(values));
        recalculateTotal();
    }

    function addBankDetail(values = {}) {
        $('ecv2-bank-details').insertAdjacentHTML('beforeend', bankDetailHtml(values));
        recalculateTotal();
    }

    function roundMoney(value) {
        return Math.round((Number(value || 0) + Number.EPSILON) * 100) / 100;
    }

    function updateFormHeaderTotals(expenseTotal, bankTotal) {
        const remaining = roundMoney(expenseTotal - bankTotal);
        if ($('ecv2-form-expense-total')) $('ecv2-form-expense-total').textContent = peso.format(expenseTotal);
        if ($('ecv2-form-bank-total')) $('ecv2-form-bank-total').textContent = peso.format(bankTotal);
        if ($('ecv2-form-remaining')) $('ecv2-form-remaining').textContent = peso.format(remaining);
        return remaining;
    }

    function calculateBankTotal() {
        let bankTotal = 0;
        $('ecv2-bank-details')?.querySelectorAll('.ecv2-bank-row').forEach((row) => {
            bankTotal += Number(row.querySelector('.ecv2-credit-amount')?.value || 0);
        });
        return roundMoney(bankTotal);
    }

    function recalculateTotal() {
        let total = 0;
        $('ecv2-expense-items').querySelectorAll('.ecv2-expense-row').forEach((row) => {
            const qty = Number(row.querySelector('.ecv2-item-qty').value || 0);
            const unitPrice = Number(row.querySelector('.ecv2-item-unit-price').value || 0);
            const amount = roundMoney(qty * unitPrice);
            total += amount;
            row.querySelector('.ecv2-line-amount').textContent = peso.format(amount);
        });
        total = roundMoney(total);
        const bankTotal = calculateBankTotal();
        $('ecv2-total').value = peso.format(total);
        updateFormHeaderTotals(total, bankTotal);
        return total;
    }

    function resetForm() {
        $('ecv2-form').reset();
        $('ecv2-edit-id').value = '';
        $('ecv2-expense-items').innerHTML = '';
        $('ecv2-bank-details').innerHTML = '';
        selectedSupplier = null;
        $('ecv2-supplier-name').textContent = 'Search supplier';
        $('ecv2-date').value = today;
        addExpenseItem({ qty: 1, unitPrice: 0 });
        addBankDetail({ checkDate: today, creditAmount: 0 });
        recalculateTotal();
    }

    function openAddModal() {
        formMode = 'add';
        selectedVoucher = null;
        resetForm();
        $('ecv2-voucher-no').textContent = nextVoucherNo;
        $('ecv2-form-title').textContent = 'Add Expense Voucher';
        $('ecv2-form-subtitle').textContent = 'Create one voucher with expense items and bank details.';
        $('ecv2-save-btn').textContent = 'Save Voucher';
        setModal('ecv2-form-modal', true);
    }

    function openEditModal(voucher) {
        formMode = 'edit';
        selectedVoucher = voucher;
        resetForm();
        $('ecv2-edit-id').value = voucher.id;
        $('ecv2-voucher-no').textContent = voucher.voucherNo;
        selectedSupplier = { id: voucher.supplierId, code: voucher.supplierCode, name: voucher.supplierName };
        $('ecv2-supplier-name').textContent = voucher.supplierName || 'Search supplier';
        $('ecv2-particular').value = voucher.particular || '';
        $('ecv2-ref').value = voucher.remarks || '';
        $('ecv2-date').value = voucher.date || today;
        $('ecv2-expense-items').innerHTML = '';
        (voucher.expenseItems || []).forEach(addExpenseItem);
        if (!(voucher.expenseItems || []).length) addExpenseItem({ qty: 1, unitPrice: Number(voucher.totalAmount || 0) });
        $('ecv2-bank-details').innerHTML = '';
        (voucher.bankDetails || []).forEach(addBankDetail);
        recalculateTotal();
        $('ecv2-form-title').textContent = `Edit ${voucher.voucherNo}`;
        $('ecv2-form-subtitle').textContent = 'Edit the voucher header, expense items, and bank details together.';
        $('ecv2-save-btn').textContent = 'Save Edit';
        setModal('ecv2-form-modal', true);
    }

    function collectFormPayload() {
        if (!selectedSupplier) throw new Error('Please select a supplier.');
        const particular = $('ecv2-particular').value.trim();
        const date = $('ecv2-date').value;
        if (!particular) throw new Error('Particular is required.');
        if (!date) throw new Error('Date is required.');

        const expenseItems = Array.from($('ecv2-expense-items').querySelectorAll('.ecv2-expense-row')).map((row) => ({
            particular_item: row.querySelector('.ecv2-item-particular').value.trim(),
            unit: row.querySelector('.ecv2-item-unit').value.trim() || null,
            qty: Number(row.querySelector('.ecv2-item-qty').value || 0),
            unit_price: Number(row.querySelector('.ecv2-item-unit-price').value || 0),
        }));
        if (!expenseItems.length) throw new Error('Add at least one expense item.');
        if (expenseItems.some((item) => !item.particular_item)) throw new Error('Particular Item is required for every expense item.');
        if (expenseItems.some((item) => !(item.qty > 0))) throw new Error('QTY must be greater than zero for every expense item.');
        if (expenseItems.some((item) => item.unit_price < 0)) throw new Error('Unit Price cannot be negative.');

        const bankDetails = Array.from($('ecv2-bank-details').querySelectorAll('.ecv2-bank-row')).map((row) => ({
            bank_name: row.querySelector('.ecv2-bank-name').value.trim(),
            check_no: row.querySelector('.ecv2-check-no').value.trim() || null,
            check_date: row.querySelector('.ecv2-check-date').value || null,
            credit_amount: Number(row.querySelector('.ecv2-credit-amount').value || 0),
        })).filter((row) => row.bank_name || row.check_no || row.check_date || row.credit_amount !== 0);

        return {
            voucher_no: $('ecv2-voucher-no').textContent.trim(),
            supplier_id: selectedSupplier.id,
            supplier_code: selectedSupplier.code,
            supplier_name: selectedSupplier.name,
            particular,
            ref: $('ecv2-ref').value.trim() || null,
            date,
            expense_items: expenseItems,
            bank_details: bankDetails,
        };
    }

    async function saveForm(event) {
        event.preventDefault();
        let payload;
        try { payload = collectFormPayload(); } catch (error) { alert(error.message); return; }
        const action = formMode === 'edit' ? `save changes to ${payload.voucher_no}` : `create ${payload.voucher_no}`;
        if (!(await showConfirm(formMode === 'edit' ? 'Save Voucher Edit' : 'Save Expense Voucher', `Are you sure you want to ${action}?`))) return;
        const endpoint = formMode === 'edit' ? `${apiBase}/update/${$('ecv2-edit-id').value}` : `${apiBase}/store`;
        const method = formMode === 'edit' ? 'PUT' : 'POST';
        try {
            await request(endpoint, { method, body: JSON.stringify(payload) });
            setModal('ecv2-form-modal', false);
            page = 1;
            await loadData();
            await showSuccess(formMode === 'edit' ? 'Voucher Updated' : 'Voucher Saved', formMode === 'edit' ? 'The expense voucher was updated successfully.' : 'The expense voucher was saved successfully.');
        } catch (error) {
            alert(error.message || 'Unable to save expense voucher.');
        }
    }

    function openView(voucher) {
        selectedVoucher = voucher;
        $('ecv2-view-heading').textContent = `${voucher.voucherNo} • ${voucher.supplierName || ''}`;
        $('ecv2-view-voucher-no').textContent = voucher.voucherNo || '---';
        $('ecv2-view-supplier').textContent = voucher.supplierName || '---';
        $('ecv2-view-particular').textContent = voucher.particular || '---';
        $('ecv2-view-ref').textContent = voucher.remarks || '---';
        $('ecv2-view-date').textContent = voucher.date || '---';
        const expenseTotal = roundMoney((voucher.expenseItems || []).reduce((sum, item) => sum + Number(item.amount || 0), 0));
        const bankTotal = roundMoney((voucher.bankDetails || []).reduce((sum, item) => sum + Number(item.creditAmount || 0), 0));
        $('ecv2-view-total').textContent = peso.format(expenseTotal || Number(voucher.totalAmount || 0));
        if ($('ecv2-view-expense-total')) $('ecv2-view-expense-total').textContent = peso.format(expenseTotal || Number(voucher.totalAmount || 0));
        if ($('ecv2-view-bank-total')) $('ecv2-view-bank-total').textContent = peso.format(bankTotal);
        if ($('ecv2-view-remaining')) $('ecv2-view-remaining').textContent = peso.format(roundMoney((expenseTotal || Number(voucher.totalAmount || 0)) - bankTotal));
        $('ecv2-view-items-body').innerHTML = (voucher.expenseItems || []).map((item) => `
            <tr><td class="px-4 py-4 font-black text-slate-800">${escapeHtml(item.particularItem || '---')}</td><td class="px-4 py-4 font-bold text-slate-600">${escapeHtml(item.unit || '---')}</td><td class="px-4 py-4 text-right font-black text-slate-700">${qtyFormat.format(Number(item.qty || 0))}</td><td class="px-4 py-4 text-right font-black text-slate-800">${peso.format(Number(item.amount || 0))}</td></tr>`).join('') || '<tr><td colspan="4" class="px-4 py-8 text-center text-xs font-bold text-slate-400">No expense items.</td></tr>';
        $('ecv2-view-bank-body').innerHTML = (voucher.bankDetails || []).map((item) => `
            <tr><td class="px-4 py-4 font-black text-slate-800">${escapeHtml(item.bankName || '---')}</td><td class="px-4 py-4 font-bold text-slate-600">${escapeHtml(item.checkNo || '---')}</td><td class="px-4 py-4 font-bold text-slate-600">${escapeHtml(item.checkDate || '---')}</td><td class="px-4 py-4 text-right font-black text-slate-800">${peso.format(Number(item.creditAmount || 0))}</td></tr>`).join('') || '<tr><td colspan="4" class="px-4 py-8 text-center text-xs font-bold text-slate-400">No bank details.</td></tr>';
        setModal('ecv2-view-modal', true);
    }

    function printVoucher(voucher) {
        const popup = window.open('', '_blank', 'width=1000,height=800');
        if (!popup) return alert('Please allow pop-ups to print this voucher.');

        const expenseItems = voucher.expenseItems || [];
        const bankDetails = voucher.bankDetails || [];
        const itemRows = expenseItems.map((item) => `<tr><td class="num">${qtyFormat.format(Number(item.qty || 0))}</td><td>${escapeHtml(item.unit || '')}</td><td>${escapeHtml(item.particularItem || '')}</td><td class="num">${peso.format(Number(item.unitPrice || 0))}</td><td class="num">${peso.format(Number(item.amount || 0))}</td></tr>`).join('');
        const blankItemRows = Array.from({ length: Math.max(0, 5 - expenseItems.length) }, () => '<tr class="blank-row"><td>&nbsp;</td><td></td><td></td><td></td><td></td></tr>').join('');
        const bankRows = bankDetails.map((item) => `<tr><td></td><td>${escapeHtml(item.bankName || '')}</td><td>${escapeHtml(item.checkNo || '')}</td><td>${escapeHtml(item.checkDate || '')}</td><td class="num">${peso.format(Number(item.creditAmount || 0))}</td></tr>`).join('');
        const blankBankRow = bankRows ? '' : '<tr class="blank-row"><td>&nbsp;</td><td></td><td></td><td></td><td></td></tr>';
        const netCheckTotal = Math.round((bankDetails.reduce((sum, item) => sum + Number(item.creditAmount || 0), 0) + Number.EPSILON) * 100) / 100;
        const printTotal = bankDetails.length ? netCheckTotal : Number(voucher.totalAmount || 0);

        popup.document.write(`<!doctype html><html><head><title>${escapeHtml(voucher.voucherNo)} - Check Voucher</title><style>
            @page{size:auto;margin:10mm}
            *{box-sizing:border-box}
            body{font-family:Arial,Helvetica,sans-serif;color:#000;margin:0;padding:0;font-size:12px}
            .sheet{width:100%;max-width:900px;margin:0 auto}
            .company{text-align:center;font-weight:800;font-size:20px;letter-spacing:.2px;margin-top:2px}
            .sub{text-align:center;font-size:11px;line-height:1.45}
            .title{text-align:center;font-size:18px;font-weight:800;margin:22px 0 18px}
            .meta{display:grid;grid-template-columns:1fr 1fr;column-gap:42px;row-gap:10px;margin-bottom:18px}
            .meta-line{display:grid;grid-template-columns:auto 1fr;gap:7px;align-items:end;min-height:22px}
            .meta-label{font-weight:800;white-space:nowrap}
            .meta-value{border-bottom:1px solid #000;min-height:19px;padding:0 4px 2px}
            table{width:100%;border-collapse:collapse;margin:0 0 17px}
            th,td{border:1px solid #000;padding:7px 6px;height:30px;vertical-align:middle}
            th{text-align:center;font-weight:800;font-size:11px}
            .items th:nth-child(1){width:10%}.items th:nth-child(2){width:12%}.items th:nth-child(3){width:38%}.items th:nth-child(4){width:20%}.items th:nth-child(5){width:20%}
            .banks th:nth-child(1){width:20%}.banks th:nth-child(2){width:22%}.banks th:nth-child(3){width:19%}.banks th:nth-child(4){width:19%}.banks th:nth-child(5){width:20%}
            .num{text-align:right;white-space:nowrap}
            .blank-row td{height:31px}
            .total-row{display:flex;justify-content:flex-end;align-items:center;gap:14px;font-size:14px;font-weight:800;margin:2px 0 42px}
            .total-value{min-width:180px;text-align:right;border-bottom:1px solid #000;padding:0 4px 3px}
            .received{font-weight:800;margin-top:10px}
            .received-line{display:inline-block;width:270px;border-bottom:1px solid #000;margin-left:8px;transform:translateY(-2px)}
            @media print{body{padding:0}.sheet{max-width:none}}
        </style></head><body><div class="sheet">
            <div class="company">W68 AUTO PARTS &amp; SERVICE CENTER</div>
            <div class="sub">48 Timothy St. Multinational Village Parañaque City</div>
            <div class="sub">Tel. Nos. 8553-9092 / 8829-0480 Mobile No. 0917-3239-605 Mobile/Viber. 0949-8818-468</div>
            <div class="title">Check Voucher</div>
            <div class="meta">
                <div class="meta-line"><span class="meta-label">PAYEE:</span><span class="meta-value">${escapeHtml(voucher.supplierName || '')}</span></div>
                <div class="meta-line"><span class="meta-label">DATE:</span><span class="meta-value">${escapeHtml(voucher.date || '')}</span></div>
                <div class="meta-line"><span class="meta-label">PARTICULARS:</span><span class="meta-value">${escapeHtml(voucher.particular || '')}</span></div>
                <div class="meta-line"><span class="meta-label">VOUCHER No.:</span><span class="meta-value">${escapeHtml(voucher.voucherNo || '')}</span></div>
            </div>
            <table class="items"><thead><tr><th>QTY</th><th>UNIT</th><th>DESCRIPTION</th><th>UNIT PRICE</th><th>UNIT TOTAL</th></tr></thead><tbody>${itemRows}${blankItemRows}</tbody></table>
            <table class="banks"><thead><tr><th>ACCOUNT NO</th><th>BANK NAME</th><th>CHECK NO.</th><th>CHECK DATE</th><th>CHECK AMOUNT</th></tr></thead><tbody>${bankRows}${blankBankRow}</tbody></table>
            <div class="total-row"><span>TOTAL:</span><span class="total-value">${peso.format(printTotal)}</span></div>
            <div class="received">RECEIVED BY:<span class="received-line"></span></div>
        </div></body></html>`);
        popup.document.close();
        popup.focus();
        setTimeout(() => popup.print(), 150);
    }

    async function deleteVoucher(voucher) {
        if (!(await showConfirm('Delete Expense Voucher', `Delete ${voucher.voucherNo}? This will also delete its Expense Items and Bank Details.`))) return;
        try {
            await request(`${apiBase}/delete/${voucher.id}`, { method: 'DELETE' });
            await loadData();
            await showSuccess('Voucher Deleted', `${voucher.voucherNo} was deleted successfully.`);
        } catch (error) {
            alert(error.message || 'Unable to delete expense voucher.');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const currentPage = document.getElementById('page-expense-cheque-voucher');
        if (!currentPage || currentPage.dataset.ecvVersion !== '2') return;
        $('ecv2-open-add-btn')?.addEventListener('click', openAddModal);
        $('ecv2-form')?.addEventListener('submit', saveForm);
        $('ecv2-add-expense-item-btn')?.addEventListener('click', () => addExpenseItem({ qty: 1, unitPrice: 0 }));
        $('ecv2-add-bank-btn')?.addEventListener('click', () => addBankDetail({ checkDate: today, creditAmount: 0 }));
        $('ecv2-open-supplier-btn')?.addEventListener('click', () => {
            loadSuppliers().catch((error) => alert(error.message));
            setModal('ecv2-supplier-modal', true);
            setTimeout(() => $('ecv2-supplier-search')?.focus(), 0);
        });
        $('ecv2-supplier-search')?.addEventListener('input', () => cachedSuppliers && renderSuppliers(cachedSuppliers));
        $('ecv2-supplier-body')?.addEventListener('click', (event) => {
            const row = event.target.closest('tr[data-supplier-id]');
            if (!row) return;
            selectedSupplier = { id: Number(row.dataset.supplierId), code: row.dataset.supplierCode, name: row.dataset.supplierName };
            $('ecv2-supplier-name').textContent = selectedSupplier.name;
            setModal('ecv2-supplier-modal', false);
        });
        $('ecv2-expense-items')?.addEventListener('input', (event) => {
            if (event.target.matches('.ecv2-item-qty, .ecv2-item-unit-price')) recalculateTotal();
        });
        $('ecv2-expense-items')?.addEventListener('click', (event) => {
            const button = event.target.closest('.ecv2-remove-expense');
            if (!button) return;
            const rowsNow = $('ecv2-expense-items').querySelectorAll('.ecv2-expense-row');
            if (rowsNow.length <= 1) return alert('At least one Expense Item is required.');
            button.closest('.ecv2-expense-row')?.remove();
            recalculateTotal();
        });
        $('ecv2-bank-details')?.addEventListener('input', (event) => {
            if (event.target.matches('.ecv2-credit-amount')) recalculateTotal();
        });
        $('ecv2-bank-details')?.addEventListener('click', (event) => {
            const button = event.target.closest('.ecv2-remove-bank');
            if (!button) return;
            button.closest('.ecv2-bank-row')?.remove();
            recalculateTotal();
        });
        document.querySelectorAll('[data-ecv2-close]').forEach((button) => button.addEventListener('click', () => setModal(`ecv2-${button.dataset.ecv2Close}-modal`, false)));
        document.querySelectorAll('[data-ecv2-filter]').forEach((input) => input.addEventListener('input', () => {
            filters[input.dataset.ecv2Filter] = input.value;
            page = 1;
            renderTable();
        }));
        $('ecv2-table-body')?.addEventListener('click', (event) => {
            const button = event.target.closest('button[data-id]');
            if (!button) return;
            const voucher = rows.find((row) => Number(row.id) === Number(button.dataset.id));
            if (!voucher) return;
            if (button.classList.contains('ecv2-print-btn')) return printVoucher(voucher);
            if (button.classList.contains('ecv2-view-btn')) return openView(voucher);
            if (button.classList.contains('ecv2-edit-btn')) return openEditModal(voucher);
            if (button.classList.contains('ecv2-delete-btn')) return deleteVoucher(voucher);
        });
        loadData().catch((error) => {
            console.error('ECV v2 load error:', error);
            alert(error.message || 'Unable to load Expense Cheque Vouchers.');
        });
    });
})();
