/**
 * W68 Viber -> Add to Existing Purchase Note
 * Scope: ADMIN + REGULAR only.
 * No database migration/schema changes.
 */
(function () {
    'use strict';

    const currentPath = window.location.pathname.replace(/\/+$/, '');
    const allowedPage = /\/(?:admin|regular)\/purchase\/viber-list$/i.test(currentPath);

    if (!allowedPage) {
        return;
    }

    const apiBase = currentPath;
    const modalId = 'viber-existing-note-modal';
    const buttonId = 'add-to-existing-note-btn';

    function selectedItemIds() {
        try {
            return Array.from(toShippedSelectedItemIds || []);
        } catch (_) {
            return [];
        }
    }

    function selectedListId() {
        try {
            return toShippedSelectedListId || null;
        } catch (_) {
            return null;
        }
    }

    function selectedCurrency() {
        try {
            return toShippedCurrency || 'PHP';
        } catch (_) {
            return document.getElementById('viber-currency-select')?.value || 'PHP';
        }
    }

    function isReadyTab() {
        try {
            return toShippedTab === 'ready';
        } catch (_) {
            return true;
        }
    }

    function createButton() {
        if (document.getElementById(buttonId)) {
            return document.getElementById(buttonId);
        }

        const addNewButton = document.getElementById('add-to-note-btn');
        if (!addNewButton || !addNewButton.parentElement) {
            return null;
        }

        const button = document.createElement('button');
        button.id = buttonId;
        button.type = 'button';
        button.disabled = true;
        button.className = 'px-4 py-2 bg-blue-600 text-white text-xs font-bold rounded-xl opacity-50 cursor-not-allowed transition-all';
        button.textContent = 'Add to Existing Note';
        button.addEventListener('click', openExistingNoteModal);

        addNewButton.insertAdjacentElement('afterend', button);
        return button;
    }

    function syncButton() {
        const button = createButton();
        if (!button) return;

        const count = selectedItemIds().length;
        const enabled = count > 0 && isReadyTab();

        button.disabled = !enabled;
        button.textContent = count > 0
            ? `Add to Existing Note (${count})`
            : 'Add to Existing Note';

        button.className = enabled
            ? 'px-4 py-2 bg-blue-600 text-white text-xs font-bold rounded-xl hover:bg-blue-700 shadow-sm cursor-pointer transition-all'
            : 'px-4 py-2 bg-blue-600 text-white text-xs font-bold rounded-xl opacity-50 cursor-not-allowed transition-all';
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, char => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[char]));
    }

    function formatMoney(value, currency = 'PHP') {
        const symbols = { PHP: '₱', TWD: 'NT$', USD: '$' };
        const symbol = symbols[currency] || currency;
        const amount = Number(value || 0).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        return `${symbol} ${amount}`;
    }

    function ensureModal() {
        let modal = document.getElementById(modalId);
        if (modal) return modal;

        modal = document.createElement('div');
        modal.id = modalId;
        modal.className = 'fixed inset-0 z-[1400] hidden flex items-center justify-center p-4';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');

        modal.innerHTML = `
            <div class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm" data-existing-note-close></div>

            <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
                <div class="bg-maroon px-6 py-5 text-white border-b-4 border-gold flex items-center justify-between gap-4 shrink-0">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gold">Viber To Shipped</p>
                        <h3 class="text-lg font-bold mt-1">Add Selected Items to Existing Note</h3>
                        <p class="text-[10px] text-white/70 mt-1">
                            Only Viber Purchase Notes for this supplier with Open or Partial status are shown.
                        </p>
                    </div>

                    <button type="button" data-existing-note-close class="p-2 rounded-xl bg-white/10 text-white/70 hover:text-white transition-colors">
                        <span class="text-xl leading-none">×</span>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto custom-scrollbar flex-1 min-h-0">
                    <div id="viber-existing-note-summary" class="mb-4 rounded-2xl border border-blue-100 bg-blue-50 px-4 py-3 text-xs text-blue-800"></div>

                    <div id="viber-existing-note-loading" class="py-12 text-center text-sm text-slate-400">
                        Loading available Viber Purchase Notes...
                    </div>

                    <div id="viber-existing-note-empty" class="hidden py-12 text-center">
                        <p class="font-bold text-slate-600">No available existing Viber Purchase Note.</p>
                        <p class="mt-1 text-xs text-slate-400">
                            The note must belong to this supplier, must already contain a Viber-linked item,
                            must be Open or Partial, and must use the selected currency.
                        </p>
                    </div>

                    <div id="viber-existing-note-list" class="hidden overflow-x-auto rounded-2xl border border-slate-100">
                        <table class="w-full text-left border-collapse min-w-[720px]">
                            <thead class="bg-slate-50 border-b border-slate-100">
                                <tr>
                                    <th class="p-3 px-4 text-[10px] font-bold uppercase tracking-widest text-slate-500 text-center w-16">Select</th>
                                    <th class="p-3 px-4 text-[10px] font-bold uppercase tracking-widest text-slate-500">Purchase Note</th>
                                    <th class="p-3 px-4 text-[10px] font-bold uppercase tracking-widest text-slate-500">Date</th>
                                    <th class="p-3 px-4 text-[10px] font-bold uppercase tracking-widest text-slate-500 text-center">Status</th>
                                    <th class="p-3 px-4 text-[10px] font-bold uppercase tracking-widest text-slate-500 text-center">Viber Items</th>
                                    <th class="p-3 px-4 text-[10px] font-bold uppercase tracking-widest text-slate-500 text-center">Remaining QTY</th>
                                    <th class="p-3 px-4 text-[10px] font-bold uppercase tracking-widest text-slate-500 text-right">Current Total</th>
                                </tr>
                            </thead>
                            <tbody id="viber-existing-note-tbody" class="divide-y divide-slate-100 text-xs"></tbody>
                        </table>
                    </div>

                    <div id="viber-existing-note-error" class="hidden mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-bold text-red-600"></div>
                </div>

                <div class="p-5 border-t border-slate-100 bg-slate-50/60 flex items-center justify-end gap-3 shrink-0">
                    <button type="button" data-existing-note-close class="px-5 py-2.5 text-xs font-bold text-slate-500 rounded-xl hover:bg-white transition-all">
                        Cancel
                    </button>

                    <button type="button" id="viber-existing-note-confirm-btn" disabled
                        class="px-6 py-2.5 bg-blue-600 text-white text-xs font-bold rounded-xl opacity-50 cursor-not-allowed transition-all">
                        Add to Existing Note
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        modal.querySelectorAll('[data-existing-note-close]').forEach(element => {
            element.addEventListener('click', closeExistingNoteModal);
        });

        modal.querySelector('#viber-existing-note-confirm-btn')
            ?.addEventListener('click', submitExistingNote);

        return modal;
    }

    function closeExistingNoteModal() {
        document.getElementById(modalId)?.classList.add('hidden');
    }

    function setModalError(message) {
        const error = document.getElementById('viber-existing-note-error');
        if (!error) return;

        if (!message) {
            error.textContent = '';
            error.classList.add('hidden');
            return;
        }

        error.textContent = message;
        error.classList.remove('hidden');
    }

    function syncConfirmButton() {
        const confirm = document.getElementById('viber-existing-note-confirm-btn');
        if (!confirm) return;

        const selected = document.querySelector('input[name="viber_existing_note_id"]:checked');
        const enabled = !!selected && selectedItemIds().length > 0;

        confirm.disabled = !enabled;
        confirm.className = enabled
            ? 'px-6 py-2.5 bg-blue-600 text-white text-xs font-bold rounded-xl hover:bg-blue-700 shadow-sm cursor-pointer transition-all'
            : 'px-6 py-2.5 bg-blue-600 text-white text-xs font-bold rounded-xl opacity-50 cursor-not-allowed transition-all';
    }

    async function openExistingNoteModal() {
        const ids = selectedItemIds();
        const listId = selectedListId();
        const currency = selectedCurrency();

        if (!listId || ids.length === 0) {
            return;
        }

        const modal = ensureModal();
        modal.classList.remove('hidden');

        setModalError('');

        const summary = document.getElementById('viber-existing-note-summary');
        const loading = document.getElementById('viber-existing-note-loading');
        const empty = document.getElementById('viber-existing-note-empty');
        const list = document.getElementById('viber-existing-note-list');
        const tbody = document.getElementById('viber-existing-note-tbody');

        if (summary) {
            summary.textContent = `${ids.length} selected Viber item(s) will be added directly to the remaining items of the chosen Purchase Note. Currency: ${currency}.`;
        }

        loading?.classList.remove('hidden');
        empty?.classList.add('hidden');
        list?.classList.add('hidden');
        if (tbody) tbody.innerHTML = '';
        syncConfirmButton();

        try {
            const params = new URLSearchParams({
                viber_list_id: String(listId),
                currency: currency
            });

            const response = await fetch(`${apiBase}/available-existing-notes?${params.toString()}`, {
                headers: {
                    'Accept': 'application/json'
                }
            });

            const json = await response.json().catch(() => ({}));

            if (!response.ok || !json.success) {
                throw new Error(json.message || 'Failed to load existing Purchase Notes.');
            }

            const notes = Array.isArray(json.data) ? json.data : [];

            loading?.classList.add('hidden');

            if (notes.length === 0) {
                empty?.classList.remove('hidden');
                return;
            }

            if (tbody) {
                tbody.innerHTML = notes.map((note, index) => {
                    const status = String(note.status || '').toLowerCase();
                    const statusClass = status === 'partial'
                        ? 'bg-amber-50 text-amber-700 border-amber-200'
                        : 'bg-emerald-50 text-emerald-700 border-emerald-200';

                    return `
                        <tr class="hover:bg-slate-50/60 transition-colors cursor-pointer"
                            onclick="this.querySelector('input[type=radio]').click()">
                            <td class="p-3 px-4 text-center" onclick="event.stopPropagation()">
                                <input type="radio"
                                    name="viber_existing_note_id"
                                    value="${Number(note.id)}"
                                    ${index === 0 ? 'checked' : ''}
                                    class="w-4 h-4 accent-maroon">
                            </td>
                            <td class="p-3 px-4">
                                <div class="font-black text-maroon">${escapeHtml(note.purchase_note_number || note.id)}</div>
                                <div class="text-[10px] text-slate-400 mt-0.5">Viber-linked note only</div>
                            </td>
                            <td class="p-3 px-4 text-slate-600">${escapeHtml(note.date || '—')}</td>
                            <td class="p-3 px-4 text-center">
                                <span class="inline-flex px-2.5 py-1 rounded-full border text-[10px] font-black uppercase ${statusClass}">
                                    ${escapeHtml(note.status || '—')}
                                </span>
                            </td>
                            <td class="p-3 px-4 text-center font-bold text-slate-700">${Number(note.viber_item_count || 0)}</td>
                            <td class="p-3 px-4 text-center font-bold text-blue-700">${Number(note.remaining_quantity || 0).toLocaleString()}</td>
                            <td class="p-3 px-4 text-right font-bold text-slate-700">${formatMoney(note.total_amount, note.currency || currency)}</td>
                        </tr>
                    `;
                }).join('');

                tbody.querySelectorAll('input[name="viber_existing_note_id"]').forEach(radio => {
                    radio.addEventListener('change', syncConfirmButton);
                });
            }

            list?.classList.remove('hidden');
            syncConfirmButton();
        } catch (error) {
            loading?.classList.add('hidden');
            setModalError(error.message || 'Failed to load existing Purchase Notes.');
        }
    }

    async function submitExistingNote() {
        const confirm = document.getElementById('viber-existing-note-confirm-btn');
        const selectedNote = document.querySelector('input[name="viber_existing_note_id"]:checked');
        const listId = selectedListId();
        const ids = selectedItemIds();
        const currency = selectedCurrency();

        if (!confirm || confirm.disabled || !selectedNote || !listId || ids.length === 0) {
            return;
        }

        confirm.disabled = true;
        const oldText = confirm.textContent;
        confirm.textContent = 'Adding...';
        setModalError('');

        try {
            const response = await fetch(`${apiBase}/add-to-existing-note`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    viber_list_id: Number(listId),
                    purchase_note_id: Number(selectedNote.value),
                    selected_item_ids: ids,
                    currency: currency
                })
            });

            const json = await response.json().catch(() => ({}));

            if (!response.ok || !json.success) {
                throw new Error(json.message || 'Failed to add the selected items to the existing Purchase Note.');
            }

            closeExistingNoteModal();

            const message = json.message
                || `Selected Viber items were added to Purchase Note ${json.purchase_note_number || ''}.`;

            if (typeof showToast === 'function') {
                showToast('success', message);
            } else {
                alert(message);
            }

            // Reload so Ready/Shipped, dashboard counts and remaining note data all
            // come back from the authoritative database state.
            window.setTimeout(() => window.location.reload(), 350);
        } catch (error) {
            setModalError(error.message || 'Failed to add the selected items.');
            confirm.disabled = false;
            confirm.textContent = oldText;
        }
    }

    function wrapAndSync(functionName) {
        const original = window[functionName];
        if (typeof original !== 'function' || original.__viberExistingNoteWrapped) {
            return;
        }

        const wrapped = function (...args) {
            const result = original.apply(this, args);
            Promise.resolve(result)
                .catch(() => {})
                .finally(() => window.setTimeout(syncButton, 0));
            return result;
        };

        wrapped.__viberExistingNoteWrapped = true;
        window[functionName] = wrapped;
    }

    function boot() {
        createButton();
        ensureModal();
        syncButton();

        [
            'toggleToShippedItem',
            'selectSupplierItems',
            'backToSuppliers',
            'switchToShippedTab',
            'onCurrencyChange'
        ].forEach(wrapAndSync);

        // The existing Add-to-New-Note button is already updated whenever the
        // To-Shipped selection changes. Mirror that UI state as a fallback.
        const existingAddButton = document.getElementById('add-to-note-btn');
        if (existingAddButton && typeof MutationObserver !== 'undefined') {
            const observer = new MutationObserver(syncButton);
            observer.observe(existingAddButton, {
                attributes: true,
                childList: true,
                characterData: true,
                subtree: true
            });
        }

        document.addEventListener('keydown', event => {
            const modal = document.getElementById(modalId);
            if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                closeExistingNoteModal();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();
