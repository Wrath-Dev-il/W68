/**
 * Purchase Note item arrangement — FRONTEND ONLY.
 *
 * No database column, no migration, no model/controller schema changes.
 *
 * Applies to the shared Add/Edit Purchase Note wizard used by
 * Admin, Regular, and Special users.
 *
 * The existing Purchase-Note.js already submits selectedItems in array order.
 * This file only changes that array order.
 */
(function () {
    'use strict';

    let activeArrangementIndex = null;
    let renderHookInstalled = false;
    let filteredWarningLocked = false;

    function items() {
        try {
            return Array.isArray(selectedItems) ? selectedItems : [];
        } catch (_) {
            return [];
        }
    }

    function isInvoiceFilterActive() {
        return window.selectedInvoiceId !== null
            && window.selectedInvoiceId !== undefined;
    }

    function selectedIndexFromRow(row) {
        if (!row) return -1;

        const explicit = Number.parseInt(row.dataset.pnSelectedIndex, 10);
        if (Number.isInteger(explicit)) return explicit;

        const input = row.querySelector(
            '[oninput*="updateItemUnit("], [oninput*="updateItemQty("], [oninput*="updateItemCost("]'
        );
        const handler = input?.getAttribute('oninput') || '';
        const match = handler.match(/updateItem(?:Unit|Qty|Cost)\((\d+),/);

        return match ? Number.parseInt(match[1], 10) : -1;
    }

    function warnFilteredArrangement() {
        if (filteredWarningLocked) return;
        filteredWarningLocked = true;
        window.setTimeout(() => { filteredWarningLocked = false; }, 1200);

        alert(
            'Arrangement changes apply to the whole Purchase Note. ' +
            'Clear the invoice filter first before changing item arrangement.'
        );
    }

    function rerender() {
        if (typeof window.renderPOItemsTable === 'function') {
            window.renderPOItemsTable();
        }
        if (typeof window.updatePOTotals === 'function') {
            window.updatePOTotals();
        }
    }

    function moveToPosition(sourceIndex, finalPosition) {
        const list = items();

        if (!Number.isInteger(sourceIndex) || sourceIndex < 0 || sourceIndex >= list.length) {
            return;
        }

        const position = Math.max(
            1,
            Math.min(list.length, Number.parseInt(finalPosition, 10) || 1)
        );

        const [moved] = list.splice(sourceIndex, 1);
        list.splice(position - 1, 0, moved);

        rerender();
    }

    function clearDropState() {
        document.querySelectorAll('#po-items-tbody > tr').forEach(row => {
            row.classList.remove(
                'pn-arrangement-drop-before',
                'pn-arrangement-drop-after',
                'opacity-50'
            );
            delete row.dataset.pnDropPosition;
        });
    }

    function enhanceRenderedRows() {
        const tbody = document.getElementById('po-items-tbody');
        if (!tbody) return;

        const list = items();
        const rows = Array.from(tbody.querySelectorAll(':scope > tr'));

        rows.forEach(row => {
            // Empty/search-result row.
            if (row.children.length === 1 && row.children[0].hasAttribute('colspan')) {
                const cell = row.children[0];
                const current = Number.parseInt(cell.getAttribute('colspan'), 10);

                // Existing Purchase-Note.js knows only the original column count.
                // Add one because Arrangement is a UI-only extra column.
                if (
                    Number.isInteger(current)
                    && cell.dataset.pnArrangementColspan !== '1'
                ) {
                    cell.setAttribute('colspan', String(current + 1));
                    cell.dataset.pnArrangementColspan = '1';
                }
                return;
            }

            if (row.querySelector('[data-pn-arrangement-cell="1"]')) return;

            const index = selectedIndexFromRow(row);
            if (index < 0 || index >= list.length) return;

            row.dataset.pnSelectedIndex = String(index);

            const number = index + 1;
            const cell = document.createElement('td');
            cell.dataset.pnArrangementCell = '1';
            cell.className =
                'p-3 px-3 text-center whitespace-nowrap bg-slate-50/70';

            cell.innerHTML = `
                <div class="inline-flex items-center gap-1.5">
                    <span
                        draggable="true"
                        data-pn-arrangement-drag="${index}"
                        title="Hold and drag to change arrangement"
                        class="inline-flex w-7 h-7 items-center justify-center rounded-lg border border-transparent text-slate-400 hover:border-slate-200 hover:bg-white hover:text-maroon cursor-grab active:cursor-grabbing select-none"
                    >
                        <span class="text-base leading-none font-black">⋮⋮</span>
                    </span>

                    <button
                        type="button"
                        onclick="openPurchaseNoteArrangementModal(${index})"
                        title="Click to move this item before or after another number"
                        class="inline-flex min-w-9 h-8 items-center justify-center rounded-lg bg-maroon px-2 text-xs font-black text-gold shadow-sm transition-all hover:bg-maroon-800"
                    >${number}</button>
                </div>
            `;

            row.insertBefore(cell, row.firstChild);

            const grip = cell.querySelector('[data-pn-arrangement-drag]');

            grip?.addEventListener('dragstart', event => {
                if (isInvoiceFilterActive()) {
                    event.preventDefault();
                    warnFilteredArrangement();
                    return;
                }

                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData(
                    'application/x-pn-arrangement-index',
                    String(index)
                );
                event.dataTransfer.setData('text/plain', String(index));
                row.classList.add('opacity-50');
            });

            grip?.addEventListener('dragend', clearDropState);

            row.addEventListener('dragover', event => {
                if (isInvoiceFilterActive()) return;

                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';

                const targetIndex = selectedIndexFromRow(row);
                if (targetIndex < 0) return;

                const rect = row.getBoundingClientRect();
                const before = event.clientY < rect.top + rect.height / 2;

                row.classList.toggle('pn-arrangement-drop-before', before);
                row.classList.toggle('pn-arrangement-drop-after', !before);

                row.dataset.pnDropPosition = String(
                    before ? targetIndex + 1 : targetIndex + 2
                );
            });

            row.addEventListener('dragleave', event => {
                if (row.contains(event.relatedTarget)) return;
                row.classList.remove(
                    'pn-arrangement-drop-before',
                    'pn-arrangement-drop-after'
                );
            });

            row.addEventListener('drop', event => {
                if (isInvoiceFilterActive()) {
                    warnFilteredArrangement();
                    return;
                }

                event.preventDefault();

                const rawSource =
                    event.dataTransfer.getData(
                        'application/x-pn-arrangement-index'
                    ) || event.dataTransfer.getData('text/plain');

                const sourceIndex = Number.parseInt(rawSource, 10);
                const position = Number.parseInt(
                    row.dataset.pnDropPosition,
                    10
                );

                clearDropState();

                if (
                    !Number.isInteger(sourceIndex)
                    || !Number.isInteger(position)
                ) {
                    return;
                }

                moveToPosition(sourceIndex, position);
            });
        });
    }

    function installRenderHook() {
        if (
            renderHookInstalled
            || typeof window.renderPOItemsTable !== 'function'
        ) {
            return false;
        }

        renderHookInstalled = true;

        const originalRender = window.renderPOItemsTable;

        window.renderPOItemsTable = function (...args) {
            const result = originalRender.apply(this, args);
            enhanceRenderedRows();
            return result;
        };

        enhanceRenderedRows();
        return true;
    }

    function showArrangementModal(index) {
        const list = items();
        const item = list[index];
        if (!item) return;

        if (isInvoiceFilterActive()) {
            warnFilteredArrangement();
            return;
        }

        activeArrangementIndex = index;

        const current = document.getElementById('pn-arrangement-current');
        const product = document.getElementById(
            'pn-arrangement-product-code'
        );
        const before = document.getElementById('pn-arrangement-before');
        const after = document.getElementById('pn-arrangement-after');
        const error = document.getElementById('pn-arrangement-error');
        const modal = document.getElementById('pn-arrangement-modal');

        if (current) current.textContent = `#${index + 1}`;
        if (product) {
            product.textContent = String(
                item.code || item.product_code || 'N/A'
            );
        }

        if (before) {
            before.value = '';
            before.max = String(list.length);
        }

        if (after) {
            after.value = '';
            after.max = String(list.length);
        }

        if (error) {
            error.textContent = '';
            error.classList.add('hidden');
        }

        modal?.classList.remove('hidden');
        window.setTimeout(() => before?.focus(), 100);
    }

    window.openPurchaseNoteArrangementModal = showArrangementModal;

    window.closePurchaseNoteArrangementModal = function () {
        document
            .getElementById('pn-arrangement-modal')
            ?.classList.add('hidden');

        activeArrangementIndex = null;

        const error = document.getElementById('pn-arrangement-error');
        if (error) {
            error.textContent = '';
            error.classList.add('hidden');
        }
    };

    window.applyPurchaseNoteArrangement = function () {
        const list = items();
        const index = Number.parseInt(activeArrangementIndex, 10);

        const beforeRaw = String(
            document.getElementById('pn-arrangement-before')?.value || ''
        ).trim();

        const afterRaw = String(
            document.getElementById('pn-arrangement-after')?.value || ''
        ).trim();

        const error = document.getElementById('pn-arrangement-error');

        const fail = message => {
            if (error) {
                error.textContent = message;
                error.classList.remove('hidden');
            } else {
                alert(message);
            }
        };

        if (
            !Number.isInteger(index)
            || index < 0
            || index >= list.length
        ) {
            fail(
                'The selected item could not be found. Reopen the arrangement modal.'
            );
            return;
        }

        if ((beforeRaw && afterRaw) || (!beforeRaw && !afterRaw)) {
            fail('Fill only one field: Add Before OR Add After.');
            return;
        }

        const reference = Number.parseInt(
            beforeRaw || afterRaw,
            10
        );

        if (
            !Number.isInteger(reference)
            || reference < 1
            || reference > list.length
        ) {
            fail(`Enter an item number from 1 to ${list.length}.`);
            return;
        }

        /*
         * Requested numbering behavior:
         *
         * Current #20 + Before #5 -> #4
         * Current #20 + After  #5 -> #6
         */
        const targetPosition = beforeRaw
            ? Math.max(1, reference - 1)
            : Math.min(list.length, reference + 1);

        moveToPosition(index, targetPosition);
        window.closePurchaseNoteArrangementModal();

        if (typeof window.showSuccess === 'function') {
            window.showSuccess(
                `Item moved to arrangement #${targetPosition}.`
            );
        }
    };

    document.addEventListener('keydown', event => {
        const modal = document.getElementById('pn-arrangement-modal');
        if (!modal || modal.classList.contains('hidden')) return;

        if (event.key === 'Escape') {
            event.preventDefault();
            window.closePurchaseNoteArrangementModal();
            return;
        }

        if (
            event.key === 'Enter'
            && (
                event.target?.id === 'pn-arrangement-before'
                || event.target?.id === 'pn-arrangement-after'
            )
        ) {
            event.preventDefault();
            window.applyPurchaseNoteArrangement();
        }
    });

    function boot() {
        if (installRenderHook()) return;

        let tries = 0;
        const timer = window.setInterval(() => {
            tries += 1;

            if (installRenderHook() || tries >= 100) {
                window.clearInterval(timer);
            }
        }, 50);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, {
            once: true,
        });
    } else {
        boot();
    }

    window.addEventListener('pageshow', () => {
        window.setTimeout(enhanceRenderedRows, 0);
    });
})();
