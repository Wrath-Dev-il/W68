 (function () {
     const root = document.getElementById('archive-page');
     if (!root) return;
 
     const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
     const dataUrl = root.dataset.dataUrl || '';
     const restoreUrl = root.dataset.restoreUrl || '';
 
     const els = {
         activeFilter: document.getElementById('archive-active-filter'),
         clearFilters: document.getElementById('archive-clear-filters'),
         refresh: document.getElementById('archive-refresh'),
         cards: Array.from(document.querySelectorAll('.archive-card')),
         tbody: document.getElementById('archive-tbody'),
         paginationLabel: document.getElementById('archive-pagination-label'),
         prev: document.getElementById('archive-prev'),
         next: document.getElementById('archive-next'),
         pageLabel: document.getElementById('archive-page-label'),
         lastPageLabel: document.getElementById('archive-last-page-label'),
         idColLabel: document.getElementById('archive-id-col-label'),
         searchId: document.getElementById('search-id'),
         searchName: document.getElementById('search-name'),
         searchDeleted: document.getElementById('search-deleted'),
         searchRemaining: document.getElementById('search-remaining'),
         confirmModal: document.getElementById('archive-confirm-restore-modal'),
         confirmText: document.getElementById('archive-confirm-text'),
         confirmYes: document.getElementById('archive-confirm-yes'),
         confirmNo: document.getElementById('archive-confirm-no'),
         successModal: document.getElementById('archive-success-modal'),
         successMessage: document.getElementById('archive-success-message'),
         successDone: document.getElementById('archive-success-done'),
         idInfoBtn: document.getElementById('archive-id-info-btn'),
         idGuideModal: document.getElementById('archive-id-guide-modal'),
         idGuideClose: document.getElementById('archive-id-guide-close'),
         idGuideOk: document.getElementById('archive-id-guide-ok'),
     };
 
     const state = {
         category: 'all',
         categoryLabel: 'All',
         page: 1,
         perPage: 50,
         lastPage: 1,
         total: 0,
         from: 0,
         to: 0,
         search: {
             id: '',
             name: '',
             deleted: '',
             remaining: '',
         },
         pendingRestore: null,
         isLoading: false,
        countdownTimer: null,
        expiryReloadQueued: false,
     };
 
     function safeStr(v) {
         if (v === null || v === undefined) return '';
         return String(v);
     }
 
     function setModal(modalEl, show) {
         if (!modalEl) return;
         if (show) {
             modalEl.classList.remove('hidden');
             modalEl.classList.add('flex');
             document.body.style.overflow = 'hidden';
         } else {
             modalEl.classList.add('hidden');
             modalEl.classList.remove('flex');
             document.body.style.overflow = '';
         }
         if (window.lucide?.createIcons) window.lucide.createIcons();
     }
 
     function debounce(fn, delayMs) {
         let t = null;
         return function (...args) {
             if (t) window.clearTimeout(t);
             t = window.setTimeout(() => fn.apply(this, args), delayMs);
         };
     }

    function formatCountdown(expiresAt) {
        if (!expiresAt) return '---';

        const targetTime = new Date(expiresAt.replace(' ', 'T')).getTime();
        if (Number.isNaN(targetTime)) return '---';

        let diff = targetTime - Date.now();
        if (diff <= 0) return 'Expired';

        const totalSeconds = Math.floor(diff / 1000);
        const days = Math.floor(totalSeconds / 86400);
        const hours = Math.floor((totalSeconds % 86400) / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = totalSeconds % 60;

        if (days > 0) return `${days}d ${hours}h ${minutes}m ${seconds}s`;
        if (hours > 0) return `${hours}h ${minutes}m ${seconds}s`;
        if (minutes > 0) return `${minutes}m ${seconds}s`;
        return `${seconds}s`;
    }

    function stopCountdownTimer() {
        if (state.countdownTimer) {
            window.clearInterval(state.countdownTimer);
            state.countdownTimer = null;
        }
    }

    function refreshCountdownCells() {
        if (!els.tbody) return;

        const countdownEls = els.tbody.querySelectorAll('[data-expires-at]');
        let hitExpired = false;
        countdownEls.forEach((node) => {
            const value = formatCountdown(node.dataset.expiresAt || '');
            node.textContent = value;
            if (value === 'Expired') {
                hitExpired = true;
            }
        });

        return hitExpired;
    }

    function startCountdownTimer() {
        stopCountdownTimer();
        const hasExpired = refreshCountdownCells();

        if (!els.tbody || !els.tbody.querySelector('[data-expires-at]')) return;
        if (hasExpired && !state.expiryReloadQueued) {
            state.expiryReloadQueued = true;
            loadData();
            return;
        }

        state.countdownTimer = window.setInterval(() => {
            const hitExpired = refreshCountdownCells();
            if (hitExpired && !state.isLoading && !state.expiryReloadQueued) {
                state.expiryReloadQueued = true;
                stopCountdownTimer();
                loadData();
            }
        }, 1000);
    }
 
     function setLoading(show) {
         state.isLoading = show;
         if (!els.tbody) return;
         if (show) {
             els.tbody.innerHTML = `<tr><td colspan="5" class="px-4 py-10 text-center text-slate-400 text-xs font-semibold">Loading...</td></tr>`;
         }
     }
 
     function setActiveFilterLabel() {
         if (!els.activeFilter) return;
         els.activeFilter.textContent = `Showing: ${state.categoryLabel}`;
     }
 
     function getIdLabelForCategory(category) {
         switch (category) {
             case 'product':
             case 'inventory':
                 return 'Product Code';
             case 'supplier':
                 return 'Supplier Code';
             case 'customer':
                 return 'Customer Code';
             case 'forwarder':
                 return 'Forwarder Code';
             case 'purchase_note':
                 return 'Purchase Number';
             case 'purchase_return':
                 return 'Purchase Return Number';
             case 'sales_note':
                 return 'Sales Note Number';
             case 'sales_return':
                 return 'Sales Return Number';
             case 'payments':
                 return 'Payment Number';
             case 'pay_check_v':
                 return 'ID';
             case 'exp_check_v':
                 return 'ID';
             case 'waybill':
                 return 'Waybill Number';
             default:
                 return 'ID';
         }
     }
 
     function getIdPlaceholderForCategory(category) {
         const label = getIdLabelForCategory(category);
         if (category === 'all') return 'Search ID / Code';
         return `Search ${label}`;
     }
 
     function updateIdColumnUi() {
         const label = (state.category === 'all') ? 'ID' : getIdLabelForCategory(state.category);
         if (els.idColLabel) els.idColLabel.textContent = label;
         if (els.searchId) els.searchId.placeholder = getIdPlaceholderForCategory(state.category);
     }
 
     function setCardsActive() {
         els.cards.forEach((btn) => {
             const isActive = (btn.dataset.category || '') === state.category;
             btn.classList.toggle('is-active', isActive);
             if (isActive) {
                 btn.classList.add('ring-2', 'ring-goldlining-500/60');
             } else {
                 btn.classList.remove('ring-2', 'ring-goldlining-500/60');
             }
         });
     }
 
     function getDisplayId(it) {
         const moduleKey = safeStr(it.module || it.category || '').trim();
         const normalized = moduleKey || safeStr(state.category || '').trim();
 
         const explicit = safeStr(it.display_id || it.displayId || it.code || '');
         if (explicit) return explicit;
 
         if (normalized === 'product' || normalized === 'inventory') {
             return safeStr(it.product_code || it.productCode || it.code || it.id || '');
         }
         if (normalized === 'supplier') {
             return safeStr(it.supplier_code || it.supplierCode || it.code || it.id || '');
         }
         if (normalized === 'customer') {
             return safeStr(it.customer_code || it.customerCode || it.code || it.id || '');
         }
         if (normalized === 'forwarder') {
             return safeStr(it.forwarder_code || it.forwarderCode || it.code || it.id || '');
         }
         if (normalized === 'purchase_note') {
             return safeStr(it.purchase_number || it.purchaseNumber || it.purchase_no || it.purchaseNo || it.code || it.id || '');
         }
         if (normalized === 'purchase_return') {
             return safeStr(it.purchase_return_number || it.purchaseReturnNumber || it.purchase_return_no || it.purchaseReturnNo || it.code || it.id || '');
         }
         if (normalized === 'sales_note') {
             return safeStr(it.sales_note_number || it.salesNoteNumber || it.sales_number || it.salesNumber || it.code || it.id || '');
         }
         if (normalized === 'sales_return') {
             return safeStr(it.sales_return_number || it.salesReturnNumber || it.sales_return_no || it.salesReturnNo || it.code || it.id || '');
         }
         if (normalized === 'waybill') {
             return safeStr(it.waybill_number || it.waybillNumber || it.waybill_no || it.waybillNo || it.code || it.id || '');
         }
         if (normalized === 'payments') {
             return safeStr(it.payment_number || it.paymentNumber || it.payment_no || it.paymentNo || it.code || it.id || '');
         }
 
         return safeStr(it.id || '');
     }
 
     function updatePaginationUi() {
         if (els.pageLabel) els.pageLabel.textContent = safeStr(state.page);
         if (els.lastPageLabel) els.lastPageLabel.textContent = safeStr(state.lastPage);
 
         if (els.prev) els.prev.disabled = state.page <= 1 || state.isLoading;
         if (els.next) els.next.disabled = state.page >= state.lastPage || state.isLoading;
 
         if (els.paginationLabel) {
             els.paginationLabel.textContent = `Showing ${state.from} to ${state.to} of ${state.total}`;
         }
     }
 
     function buildQueryParams() {
         const params = new URLSearchParams();
         params.set('page', safeStr(state.page));
         params.set('perPage', safeStr(state.perPage));
         params.set('category', safeStr(state.category));
         params.set('search[id]', safeStr(state.search.id));
         params.set('search[name]', safeStr(state.search.name));
         params.set('search[deleted]', safeStr(state.search.deleted));
         params.set('search[remaining]', safeStr(state.search.remaining));
         return params.toString();
     }
 
     function renderRows(items) {
         if (!els.tbody) return;
         if (!Array.isArray(items) || items.length === 0) {
            stopCountdownTimer();
             els.tbody.innerHTML = `<tr><td colspan="5" class="px-4 py-10 text-center text-slate-400 text-xs font-semibold">No archived data found</td></tr>`;
             return;
         }
 
         const rowsHtml = items.map((it) => {
             const moduleKey = safeStr(it.module || it.category || '');
             const idValue = getDisplayId(it);
             const name = safeStr(it.name || it.data_name || '');
             const deletedAt = safeStr(it.deleted_at || it.date_deleted || '');
            const expiresAt = safeStr(it.expires_at || '');
            const remaining = formatCountdown(expiresAt) || safeStr(it.remaining || it.remaining_days || '');
 
             const moduleBadge = moduleKey
                 ? `<span class="inline-flex items-center px-2 py-1 rounded-lg bg-slate-100 text-slate-600 text-[9px] font-black uppercase tracking-widest">${moduleKey.replace(/_/g, ' ')}</span>`
                 : '';
 
             return `
                 <tr class="hover:bg-slate-50/60">
                     <td class="px-4 py-3 align-top">
                         <div class="flex flex-col gap-1">
                             <div class="text-xs font-black text-slate-800">${idValue}</div>
                             <div>${moduleBadge}</div>
                         </div>
                     </td>
                     <td class="px-4 py-3 align-top">
                         <div class="text-xs font-semibold text-slate-700">${name}</div>
                     </td>
                     <td class="px-4 py-3 align-top">
                         <div class="text-xs font-semibold text-slate-600">${deletedAt || '—'}</div>
                     </td>
                     <td class="px-4 py-3 align-top">
                        <div class="text-xs font-black text-slate-700" data-expires-at="${expiresAt.replace(/"/g, '&quot;')}">${remaining || '—'}</div>
                     </td>
                     <td class="px-4 py-3 text-center align-top">
                         <button type="button"
                             class="archive-restore-btn px-4 py-2 rounded-xl bg-maroon-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-maroon-800"
                             data-module="${moduleKey}"
                             data-id="${safeStr(it.id || '')}"
                             data-name="${name.replace(/"/g, '&quot;')}">
                             Restore
                         </button>
                     </td>
                 </tr>
             `;
         }).join('');
 
         els.tbody.innerHTML = rowsHtml;
        startCountdownTimer();
         if (window.lucide?.createIcons) window.lucide.createIcons();
     }
 
     async function loadData() {
         if (!dataUrl) return;
 
        state.expiryReloadQueued = false;
         setLoading(true);
         updatePaginationUi();
 
         try {
             const query = buildQueryParams();
             const res = await fetch(`${dataUrl}?${query}`, {
                 headers: {
                     'Accept': 'application/json',
                 },
             });
             const json = await res.json();
 
             if (!json || json.success !== true) {
                 renderRows([]);
                 state.total = 0;
                 state.from = 0;
                 state.to = 0;
                 state.lastPage = 1;
                 updatePaginationUi();
                 return;
             }
 
             const items = json.items || [];
             state.page = Number(json.page || state.page || 1) || 1;
             state.lastPage = Number(json.last_page || 1) || 1;
             state.total = Number(json.total || 0) || 0;
             state.from = Number(json.from || 0) || 0;
             state.to = Number(json.to || 0) || 0;
 
             renderRows(items);
             updatePaginationUi();
         } catch (e) {
             renderRows([]);
             state.total = 0;
             state.from = 0;
             state.to = 0;
             state.lastPage = 1;
             updatePaginationUi();
         } finally {
             state.isLoading = false;
             updatePaginationUi();
         }
     }
 
      function openConfirmRestore(payload) {
          state.pendingRestore = payload;
          if (els.confirmText) {
              const label = payload?.name ? `"${payload.name}"` : 'this item';
              let msg = `Are you sure you want to restore ${label}?`;
              if (payload?.module === 'purchase_note') {
                  msg += ' This will also restore all related Purchase Orders, Order Items, Purchase Returns, and Return Items.';
              }
              if (payload?.module === 'sales_note') {
                  msg += ' This will also restore all related Sales Orders, Order Items, Sales Returns, and Return Items.';
              }
              els.confirmText.textContent = msg;
          }
          setModal(els.confirmModal, true);
      }
 
     function closeConfirmRestore() {
         setModal(els.confirmModal, false);
         state.pendingRestore = null;
     }
 
     async function submitRestore() {
         const payload = state.pendingRestore;
         if (!payload || !restoreUrl) return;
 
         try {
             const res = await fetch(restoreUrl, {
                 method: 'POST',
                 headers: {
                     'Accept': 'application/json',
                     'Content-Type': 'application/json',
                     'X-CSRF-TOKEN': csrfToken,
                 },
                 body: JSON.stringify({
                     module: payload.module || null,
                     id: payload.id || null,
                 }),
             });
             const json = await res.json();
             closeConfirmRestore();
 
              if (json && json.success) {
                  if (els.successMessage) {
                      const msg = safeStr(json.message || 'The item was restored successfully.');
                      els.successMessage.innerHTML = msg.replace(/\n/g, '<br>');
                  }
                  setModal(els.successModal, true);
                  await loadData();
              } else {
                  if (els.successMessage) {
                      const msg = safeStr(json?.message || 'Restore failed.');
                      els.successMessage.innerHTML = msg.replace(/\n/g, '<br>');
                  }
                  setModal(els.successModal, true);
              }
         } catch (e) {
             closeConfirmRestore();
              if (els.successMessage) els.successMessage.innerHTML = 'Restore failed.';
             setModal(els.successModal, true);
         }
     }
 
     function closeSuccessModal() {
         setModal(els.successModal, false);
     }
 
     function openIdGuide() {
         setModal(els.idGuideModal, true);
     }
 
     function closeIdGuide() {
         setModal(els.idGuideModal, false);
     }
 
     function resetFilters() {
         state.category = 'all';
         state.categoryLabel = 'All';
         state.page = 1;
         state.search = { id: '', name: '', deleted: '', remaining: '' };
 
         if (els.searchId) els.searchId.value = '';
         if (els.searchName) els.searchName.value = '';
         if (els.searchDeleted) els.searchDeleted.value = '';
         if (els.searchRemaining) els.searchRemaining.value = '';
 
         setCardsActive();
         setActiveFilterLabel();
         updateIdColumnUi();
         loadData();
     }
 
     function wireEvents() {
         els.cards.forEach((btn) => {
             btn.addEventListener('click', () => {
                 const key = btn.dataset.category || 'all';
                 const label = btn.querySelector('h3')?.textContent?.trim() || key;
                 state.category = key;
                 state.categoryLabel = label;
                 state.page = 1;
                 setCardsActive();
                 setActiveFilterLabel();
                 updateIdColumnUi();
                 loadData();
             });
         });
 
         if (els.refresh) {
             els.refresh.addEventListener('click', () => loadData());
         }
 
         if (els.clearFilters) {
             els.clearFilters.addEventListener('click', () => resetFilters());
         }
 
         if (els.prev) {
             els.prev.addEventListener('click', () => {
                 if (state.page <= 1) return;
                 state.page -= 1;
                 loadData();
             });
         }
 
         if (els.next) {
             els.next.addEventListener('click', () => {
                 if (state.page >= state.lastPage) return;
                 state.page += 1;
                 loadData();
             });
         }
 
         const onSearchInput = debounce(() => {
             state.page = 1;
             state.search.id = safeStr(els.searchId?.value || '').trim();
             state.search.name = safeStr(els.searchName?.value || '').trim();
             state.search.deleted = safeStr(els.searchDeleted?.value || '').trim();
             state.search.remaining = safeStr(els.searchRemaining?.value || '').trim();
             loadData();
         }, 250);
 
         [els.searchId, els.searchName, els.searchDeleted, els.searchRemaining].forEach((inp) => {
             if (!inp) return;
             inp.addEventListener('input', onSearchInput);
         });
 
         if (els.tbody) {
             els.tbody.addEventListener('click', (e) => {
                 const btn = e.target?.closest?.('.archive-restore-btn');
                 if (!btn) return;
                 openConfirmRestore({
                     module: btn.dataset.module || null,
                     id: btn.dataset.id || null,
                     name: btn.dataset.name || null,
                 });
             });
         }
 
         if (els.confirmNo) els.confirmNo.addEventListener('click', () => closeConfirmRestore());
         if (els.confirmYes) els.confirmYes.addEventListener('click', () => submitRestore());
 
         if (els.successDone) els.successDone.addEventListener('click', () => closeSuccessModal());
 
         if (els.idInfoBtn) els.idInfoBtn.addEventListener('click', () => openIdGuide());
         if (els.idGuideClose) els.idGuideClose.addEventListener('click', () => closeIdGuide());
         if (els.idGuideOk) els.idGuideOk.addEventListener('click', () => closeIdGuide());
     }
 
     function init() {
         setCardsActive();
         setActiveFilterLabel();
         updateIdColumnUi();
         wireEvents();
         loadData();
         if (window.lucide?.createIcons) window.lucide.createIcons();
     }
 
     init();
 })();
