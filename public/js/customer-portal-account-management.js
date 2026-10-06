/* W68_PORTAL_ACCOUNT_MANAGEMENT_ALL_STAFF_20261006 */
(function () {
    'use strict';

    function customerId() {
        try {
            return Number(currentPortalCustomerId || 0);
        } catch (_) {
            return 0;
        }
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function endpointsFor(id) {
        try {
            return {
                status: CUSTOMER_ENDPOINTS.portalStatus(id),
                reset: CUSTOMER_ENDPOINTS.portalValidity(id),
                deleteAccount: CUSTOMER_ENDPOINTS.portalDelete(id)
            };
        } catch (_) {
            return null;
        }
    }

    async function readJson(response) {
        let body = {};
        try {
            body = await response.json();
        } catch (_) {
            body = {};
        }

        if (!response.ok || body.success === false) {
            const validation = body.errors
                ? Object.values(body.errors).flat().join(' ')
                : '';
            throw new Error(validation || body.message || `Request failed (${response.status})`);
        }

        return body;
    }

    function ensureCard() {
        const portal = document.getElementById('detail-content-portal');
        if (!portal) return null;

        let card = document.getElementById('portal-account-management-card');
        if (card) return card;

        card = document.createElement('div');
        card.id = 'portal-account-management-card';
        card.className = 'rounded-2xl border border-blue-100 bg-blue-50/60 p-4 shadow-sm';

        const header = portal.firstElementChild;
        if (header?.nextSibling) {
            portal.insertBefore(card, header.nextSibling);
        } else {
            portal.appendChild(card);
        }

        return card;
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char]));
    }

    function renderLoading() {
        const card = ensureCard();
        if (!card) return;
        card.innerHTML = `
            <div class="flex items-center gap-2 text-[10px] font-bold text-blue-600">
                <span class="inline-block h-3 w-3 animate-spin rounded-full border-2 border-blue-500 border-t-transparent"></span>
                Loading registered portal account...
            </div>`;
    }

    function renderNoAccount() {
        const card = ensureCard();
        if (!card) return;

        card.innerHTML = `
            <div class="flex items-start gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white text-blue-500 shadow-sm">
                    <i data-lucide="user-x" class="h-4 w-4"></i>
                </div>
                <div>
                    <p class="text-[9px] font-black uppercase tracking-widest text-blue-700">Registered Pricelist Account</p>
                    <p class="mt-1 text-xs font-extrabold text-slate-700">No registered portal account</p>
                    <p class="mt-1 text-[9px] font-semibold leading-relaxed text-slate-500">The customer can register after opening a valid authorization link or QR code.</p>
                </div>
            </div>`;

        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function renderAccount(account) {
        const card = ensureCard();
        if (!card) return;

        const username = escapeHtml(account?.username || `Login #${account?.login_id || ''}`);
        const email = escapeHtml(account?.email || 'No email');

        card.innerHTML = `
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-[9px] font-black uppercase tracking-widest text-blue-700">Registered Pricelist Account</p>
                    <p class="mt-2 text-xs font-extrabold text-slate-800">${username}</p>
                    <p class="mt-1 break-all text-[9px] font-semibold text-slate-500">${email}</p>
                </div>
                <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-1 text-[8px] font-black uppercase tracking-widest text-emerald-700">Registered</span>
            </div>

            <div class="mt-4 rounded-xl border border-slate-200 bg-white p-3">
                <div class="flex items-start gap-2">
                    <i data-lucide="shield-check" class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600"></i>
                    <div>
                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-500">Password</p>
                        <p class="mt-1 text-[9px] font-semibold leading-relaxed text-slate-500">Securely hashed. The customer's existing password cannot be displayed or recovered. You can set a new password below.</p>
                    </div>
                </div>

                <div class="mt-3 flex overflow-hidden rounded-xl border border-slate-200 bg-slate-50 focus-within:border-maroon">
                    <input id="portal-account-new-password" type="password" autocomplete="new-password" placeholder="Enter new password" class="min-w-0 flex-1 bg-transparent px-3 py-2.5 text-xs font-bold text-slate-700 outline-none">
                    <button id="portal-account-toggle-password" type="button" class="border-l border-slate-200 px-3 text-[9px] font-black uppercase text-slate-500 hover:bg-white">Show</button>
                </div>

                <p id="portal-account-action-error" class="mt-2 hidden rounded-lg bg-red-50 px-3 py-2 text-[9px] font-bold text-red-600"></p>

                <button id="portal-account-reset-password" type="button" class="mt-3 w-full rounded-xl bg-maroon px-3 py-2.5 text-[9px] font-black uppercase tracking-widest text-white shadow-sm disabled:cursor-not-allowed disabled:opacity-50">
                    Set New Password
                </button>
            </div>

            <button id="portal-account-delete" type="button" class="mt-3 w-full rounded-xl border border-red-200 bg-red-50 px-3 py-2.5 text-[9px] font-black uppercase tracking-widest text-red-600 hover:bg-red-100">
                Delete Portal Account
            </button>
            <p class="mt-2 text-[8px] font-semibold leading-relaxed text-slate-400">Deleting the portal account removes only the registered Pricelist login/link. The Customer Master record and authorization/QR are kept.</p>`;

        const password = document.getElementById('portal-account-new-password');
        const toggle = document.getElementById('portal-account-toggle-password');
        const reset = document.getElementById('portal-account-reset-password');
        const remove = document.getElementById('portal-account-delete');

        toggle?.addEventListener('click', () => {
            if (!password) return;
            const showing = password.type === 'text';
            password.type = showing ? 'password' : 'text';
            toggle.textContent = showing ? 'Show' : 'Hide';
        });

        reset?.addEventListener('click', resetPassword);
        remove?.addEventListener('click', deleteAccount);

        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function setError(message) {
        const box = document.getElementById('portal-account-action-error');
        if (!box) return;
        box.textContent = message || '';
        box.classList.toggle('hidden', !message);
    }

    async function loadAccount() {
        const id = customerId();
        const endpoints = endpointsFor(id);
        if (!id || !endpoints) return;

        renderLoading();

        try {
            const response = await fetch(endpoints.status, {
                headers: { 'Accept': 'application/json' }
            });
            const body = await readJson(response);
            if (customerId() !== id) return;
            if (body.linked_account) renderAccount(body.linked_account);
            else renderNoAccount();
        } catch (error) {
            const card = ensureCard();
            if (card) {
                card.innerHTML = `<p class="text-[9px] font-bold text-red-600">${escapeHtml(error.message || 'Unable to load the registered portal account.')}</p>`;
            }
        }
    }

    async function resetPassword() {
        const id = customerId();
        const endpoints = endpointsFor(id);
        const input = document.getElementById('portal-account-new-password');
        const button = document.getElementById('portal-account-reset-password');
        const password = String(input?.value ?? '');

        if (!id || !endpoints) return;
        if (password.length === 0) {
            setError('Enter the new password first.');
            input?.focus();
            return;
        }

        setError('');
        if (button) {
            button.disabled = true;
            button.textContent = 'Saving...';
        }

        try {
            const response = await fetch(endpoints.reset, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken()
                },
                body: JSON.stringify({
                    action: 'reset_password',
                    password
                })
            });
            const body = await readJson(response);
            if (input) input.value = '';
            if (typeof showSuccessModal === 'function') {
                showSuccessModal('Password Updated', body.message || 'The customer portal password was updated.');
            } else {
                alert(body.message || 'The customer portal password was updated.');
            }
            await loadAccount();
        } catch (error) {
            setError(error.message || 'Unable to reset the password.');
        } finally {
            if (button) {
                button.disabled = false;
                button.textContent = 'Set New Password';
            }
        }
    }

    function deleteAccount() {
        const id = customerId();
        const endpoints = endpointsFor(id);
        if (!id || !endpoints) return;

        const runDelete = async () => {
            const button = document.getElementById('portal-account-delete');
            if (button) button.disabled = true;
            setError('');

            try {
                const response = await fetch(endpoints.deleteAccount, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken()
                    },
                    body: JSON.stringify({ target: 'account' })
                });
                const body = await readJson(response);

                if (typeof showSuccessModal === 'function') {
                    showSuccessModal('Portal Account Deleted', body.message || 'The registered portal account was deleted.');
                } else {
                    alert(body.message || 'The registered portal account was deleted.');
                }

                await loadAccount();

                try {
                    currentPortalLoadId = null;
                    if (typeof loadPortalAccess === 'function') {
                        await loadPortalAccess(id);
                    }
                } catch (_) {
                }
            } catch (error) {
                setError(error.message || 'Unable to delete the portal account.');
                if (button) button.disabled = false;
            }
        };

        if (typeof showConfirmModal === 'function') {
            showConfirmModal(
                'Delete Portal Account?',
                'Delete this customer\'s registered Pricelist login? This will NOT delete the Customer Master record or the authorization/QR code. The customer may register a new account afterward.',
                runDelete
            );
        } else if (window.confirm('Delete this registered Pricelist account? Customer Master and QR authorization will be kept.')) {
            runDelete();
        }
    }

    function bindPortalTab() {
        const tab = document.getElementById('detail-tab-portal');
        if (!tab || tab.dataset.portalAccountManagementBound === '1') return;
        tab.dataset.portalAccountManagementBound = '1';
        tab.addEventListener('click', () => {
            window.setTimeout(loadAccount, 0);
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        ensureCard();
        bindPortalTab();
    });
})();
