/* W68_CUSTOMER_PORTAL_CHANGE_PASSWORD_TEMP_20261007 */
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
        const rawPassword = String(account?.password ?? '');
        const isHashed = Boolean(account?.is_hashed) || (
            rawPassword.startsWith('$2y$') ||
            rawPassword.startsWith('$2a$') ||
            rawPassword.startsWith('$2b$') ||
            rawPassword.startsWith('$argon2') ||
            (rawPassword.startsWith('$') && rawPassword.length >= 30)
        );

        let passwordTypeBadge = '';
        let passwordHint = '';
        if (!rawPassword) {
            passwordTypeBadge = '<span class="text-[8px] font-bold uppercase tracking-widest text-slate-400">None</span>';
        } else if (isHashed) {
            passwordTypeBadge = '<span class="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[8px] font-black uppercase tracking-widest text-amber-700">Hashed Account</span>';
            passwordHint = '<p class="mt-1 text-[9px] font-semibold leading-relaxed text-amber-700">This account was registered with password hashing in the database.</p>';
        } else {
            passwordTypeBadge = '<span class="rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-[8px] font-black uppercase tracking-widest text-blue-700">Plain Text</span>';
        }

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
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <i data-lucide="key" class="h-3.5 w-3.5 text-slate-500"></i>
                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-500">Customer Password</p>
                    </div>
                    ${passwordTypeBadge}
                </div>
                ${passwordHint}

                <div class="mt-2 flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-2.5">
                    <span id="portal-account-password-view" class="font-mono text-xs font-bold text-slate-700 break-all select-all">${rawPassword ? '••••••••' : 'No password set'}</span>
                    <div class="flex items-center gap-1.5 shrink-0 ml-2">
                        <button id="portal-account-toggle-view" type="button" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[9px] font-bold text-slate-600 hover:bg-slate-100 flex items-center gap-1 ${!rawPassword ? 'opacity-50 cursor-not-allowed' : ''}">
                            <i data-lucide="eye" class="h-3 w-3"></i>
                            <span id="portal-account-toggle-label">Show</span>
                        </button>
                        <button id="portal-account-copy-view" type="button" class="rounded-lg bg-slate-800 px-2.5 py-1 text-[9px] font-bold text-white hover:bg-slate-700 flex items-center gap-1 ${!rawPassword ? 'opacity-50 cursor-not-allowed' : ''}">
                            <i data-lucide="copy" class="h-3 w-3"></i>
                            <span>Copy</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- W68_CUSTOMER_PORTAL_CHANGE_PASSWORD_TEMP_20261007 -->
            <div class="mt-3 rounded-xl border border-dashed border-amber-300 bg-amber-50/70 p-3">
                <div class="flex items-center justify-between">
                    <p class="text-[9px] font-black uppercase tracking-widest text-amber-800">Temporary: Set Plain Text Password</p>
                    <span class="rounded bg-amber-200/80 px-1.5 py-0.5 text-[7px] font-black uppercase tracking-wider text-amber-900">Plain Text</span>
                </div>
                <p class="mt-1 text-[8px] font-medium leading-relaxed text-amber-700">Enter a new password below. It will be saved directly in plain text (no hash).</p>
                <div class="mt-2 flex gap-2">
                    <input id="portal-account-temp-password" type="text" placeholder="Enter new plain text password" class="min-w-0 flex-1 rounded-xl border border-amber-300 bg-white px-3 py-2 text-xs font-mono font-bold text-slate-800 outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-200">
                    <button id="portal-account-temp-save-btn" type="button" class="shrink-0 rounded-xl bg-amber-600 px-3.5 py-2 text-[9px] font-black uppercase tracking-widest text-white shadow-sm hover:bg-amber-700 disabled:opacity-50">
                        Update
                    </button>
                </div>
                <p id="portal-account-temp-error" class="mt-1.5 hidden text-[8px] font-bold text-red-600"></p>
            </div>

            <p id="portal-account-action-error" class="mt-2 hidden rounded-lg bg-red-50 px-3 py-2 text-[9px] font-bold text-red-600"></p>

            <button id="portal-account-delete" type="button" class="mt-3 w-full rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-[9px] font-black uppercase tracking-widest text-red-600 hover:bg-red-100">
                Delete Portal Account
            </button>
            <p class="mt-1.5 text-[8px] font-semibold leading-relaxed text-slate-400">Deleting the portal account removes only the registered Pricelist login/link. The Customer Master record and authorization/QR are kept so the customer can re-register.</p>`;

        const toggleBtn = document.getElementById('portal-account-toggle-view');
        const copyBtn = document.getElementById('portal-account-copy-view');
        const remove = document.getElementById('portal-account-delete');
        const passView = document.getElementById('portal-account-password-view');
        const toggleLabel = document.getElementById('portal-account-toggle-label');
        const tempPassInput = document.getElementById('portal-account-temp-password');
        const tempSaveBtn = document.getElementById('portal-account-temp-save-btn');
        const tempError = document.getElementById('portal-account-temp-error');

        let isShowing = false;

        toggleBtn?.addEventListener('click', () => {
            if (!rawPassword || !passView) return;
            isShowing = !isShowing;
            passView.textContent = isShowing ? rawPassword : '••••••••';
            if (toggleLabel) toggleLabel.textContent = isShowing ? 'Hide' : 'Show';
            const icon = toggleBtn.querySelector('i');
            if (icon) {
                icon.setAttribute('data-lucide', isShowing ? 'eye-off' : 'eye');
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
        });

        copyBtn?.addEventListener('click', async () => {
            if (!rawPassword) {
                alert('No password available to copy.');
                return;
            }
            try {
                await navigator.clipboard.writeText(rawPassword);
                if (typeof showSuccessModal === 'function') {
                    showSuccessModal('Copied!', 'Customer password copied to clipboard.');
                } else {
                    alert('Customer password copied to clipboard.');
                }
            } catch (_) {
                if (passView) {
                    const range = document.createRange();
                    range.selectNodeContents(passView);
                    const sel = window.getSelection();
                    sel.removeAllRanges();
                    sel.addRange(range);
                }
                alert('Password selected. Press Ctrl+C to copy.');
            }
        });

        tempSaveBtn?.addEventListener('click', async () => {
            const newPassword = String(tempPassInput?.value ?? '').trim();
            if (!newPassword) {
                if (tempError) {
                    tempError.textContent = 'Please enter a new password first.';
                    tempError.classList.remove('hidden');
                }
                tempPassInput?.focus();
                return;
            }
            if (tempError) tempError.classList.add('hidden');
            tempSaveBtn.disabled = true;
            tempSaveBtn.textContent = 'Saving...';

            try {
                const endpoints = endpointsFor(customerId());
                if (!endpoints) throw new Error('Missing endpoints.');

                const response = await fetch(endpoints.reset, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken()
                    },
                    body: JSON.stringify({
                        action: 'reset_password',
                        password: newPassword
                    })
                });
                const body = await readJson(response);
                if (typeof showSuccessModal === 'function') {
                    showSuccessModal('Password Updated', body.message || 'Password updated as plain text.');
                } else {
                    alert(body.message || 'Password updated as plain text.');
                }
                await loadAccount();
                try {
                    if (typeof currentPortalLoadId !== 'undefined') {
                        currentPortalLoadId = null;
                    }
                    if (typeof loadPortalAccess === 'function') {
                        await loadPortalAccess(customerId());
                    }
                } catch (_) {}
            } catch (err) {
                if (tempError) {
                    tempError.textContent = err.message || 'Failed to update password.';
                    tempError.classList.remove('hidden');
                } else {
                    alert(err.message || 'Failed to update password.');
                }
            } finally {
                tempSaveBtn.disabled = false;
                tempSaveBtn.textContent = 'Update';
            }
        });

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
                    if (typeof currentPortalLoadId !== 'undefined') {
                        currentPortalLoadId = null;
                    }
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
