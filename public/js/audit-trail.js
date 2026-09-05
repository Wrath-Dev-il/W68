 (function () {
     const root = document.getElementById('audit-trail-page');
     if (!root) return;
 
     const dataUrl = root.dataset.dataUrl || '';
    let purgeAtRaw = root.dataset.purgeAt || '';
 
     const els = {
         activeFilter: document.getElementById('audit-active-filter'),
         clearFilters: document.getElementById('audit-clear-filters'),
         refresh: document.getElementById('audit-refresh'),
         cards: Array.from(document.querySelectorAll('.audit-card')),
         tbody: document.getElementById('audit-tbody'),
         paginationLabel: document.getElementById('audit-pagination-label'),
         prev: document.getElementById('audit-prev'),
         next: document.getElementById('audit-next'),
         pageLabel: document.getElementById('audit-page-label'),
         lastPageLabel: document.getElementById('audit-last-page-label'),
         searchName: document.getElementById('audit-search-name'),
         searchUsername: document.getElementById('audit-search-username'),
         searchDatetime: document.getElementById('audit-search-datetime'),
         searchAction: document.getElementById('audit-search-action'),
         searchModule: document.getElementById('audit-search-module'),
        purgeStatus: document.getElementById('audit-purge-status'),
        purgeCountdown: document.getElementById('audit-purge-countdown'),
     };
 
     const state = {
         module: 'all',
         moduleLabel: 'All',
         page: 1,
         perPage: 50,
         lastPage: 1,
         total: 0,
         from: 0,
         to: 0,
         search: {
             name: '',
             username: '',
             datetime: '',
             action: '',
             module: '',
         },
         isLoading: false,
     };

    let purgeCountdownTimer = null;
    let purgeRefreshTimer = null;
    const MAX_TIMEOUT_MS = 2147483647;
    const PURGE_REFRESH_STEP_MS = 60 * 60 * 1000;
 
     function safeStr(v) {
         if (v === null || v === undefined) return '';
         return String(v);
     }
 
     function debounce(fn, delayMs) {
         let t = null;
         return function (...args) {
             if (t) window.clearTimeout(t);
             t = window.setTimeout(() => fn.apply(this, args), delayMs);
         };
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
         els.activeFilter.textContent = `Showing: ${state.moduleLabel}`;
     }
 
     function setCardsActive() {
         els.cards.forEach((btn) => {
             const isActive = (btn.dataset.module || '') === state.module;
             btn.classList.toggle('is-active', isActive);
             if (isActive) {
                 btn.classList.add('ring-2', 'ring-goldlining-500/60');
             } else {
                 btn.classList.remove('ring-2', 'ring-goldlining-500/60');
             }
         });
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
         params.set('module', safeStr(state.module));
         params.set('search[name]', safeStr(state.search.name));
         params.set('search[username]', safeStr(state.search.username));
         params.set('search[datetime]', safeStr(state.search.datetime));
         params.set('search[action]', safeStr(state.search.action));
         params.set('search[module]', safeStr(state.search.module));
         return params.toString();
     }
 
     function renderRows(items) {
         if (!els.tbody) return;
         if (!Array.isArray(items) || items.length === 0) {
             els.tbody.innerHTML = `<tr><td colspan="5" class="px-4 py-10 text-center text-slate-400 text-xs font-semibold">No audit logs found</td></tr>`;
             return;
         }
 
         const rowsHtml = items.map((it) => {
             const name = safeStr(it.name || it.record_name || '');
             const username = safeStr(it.username || it.user_name || it.user || '');
             const datetime = safeStr(it.datetime || it.time_date || it.created_at || '');
             const action = safeStr(it.action || '');
             const moduleKey = safeStr(it.module || it.module_key || '');
 
             const badge = moduleKey
                 ? `<span class="inline-flex items-center px-2 py-1 rounded-lg bg-slate-100 text-slate-600 text-[9px] font-black uppercase tracking-widest">${moduleKey.replace(/_/g, ' ')}</span>`
                 : '—';
 
             return `
                 <tr class="hover:bg-slate-50/60">
                     <td class="px-4 py-3 align-top">
                         <div class="text-xs font-semibold text-slate-700">${name || '—'}</div>
                     </td>
                     <td class="px-4 py-3 align-top">
                         <div class="text-xs font-semibold text-slate-600">${username || '—'}</div>
                     </td>
                     <td class="px-4 py-3 align-top">
                         <div class="text-xs font-semibold text-slate-600">${datetime || '—'}</div>
                     </td>
                     <td class="px-4 py-3 align-top">
                         <div class="text-xs font-black text-slate-700">${action || '—'}</div>
                     </td>
                     <td class="px-4 py-3 align-top">
                         ${badge}
                     </td>
                 </tr>
             `;
         }).join('');
 
         els.tbody.innerHTML = rowsHtml;
         if (window.lucide?.createIcons) window.lucide.createIcons();
     }

    function formatRemaining(ms) {
        const totalSeconds = Math.max(0, Math.floor(ms / 1000));
        const hours = Math.floor(totalSeconds / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = totalSeconds % 60;
        return [hours, minutes, seconds].map((v) => String(v).padStart(2, '0')).join(':');
    }

    function setPurgeCountdownText(text) {
        if (els.purgeCountdown) els.purgeCountdown.textContent = text;
    }

    function setPurgeStatusText(text) {
        if (els.purgeStatus) els.purgeStatus.textContent = text;
    }

    function renderPurgeCountdown() {
        if (!purgeAtRaw) {
            setPurgeStatusText('No deletion schedule set.');
            setPurgeCountdownText('--:--:--');
            return;
        }

        const purgeAt = new Date(purgeAtRaw);
        if (Number.isNaN(purgeAt.getTime())) {
            setPurgeStatusText('Invalid deletion schedule.');
            setPurgeCountdownText('--:--:--');
            return;
        }

        const remainingMs = purgeAt.getTime() - Date.now();
        if (remainingMs <= 0) {
            setPurgeStatusText('Deletion time reached. Waiting for purge confirmation...');
            setPurgeCountdownText('00:00:00');
            return;
        }

        setPurgeStatusText('Audit Trail will auto delete all logs every end of month at 10:00 PM Manila time.');
        setPurgeCountdownText(formatRemaining(remainingMs));
    }

    function startPurgeCountdown() {
        renderPurgeCountdown();
        if (purgeCountdownTimer) window.clearInterval(purgeCountdownTimer);
        purgeCountdownTimer = window.setInterval(renderPurgeCountdown, 1000);
    }

    function applyAutoPurgeState(autoPurge) {
        if (!autoPurge || typeof autoPurge !== 'object') return;
        if (autoPurge.due_at) {
            syncPurgeTarget(autoPurge.due_at);
        }

        if (autoPurge.purged) {
            setPurgeStatusText(`Month-end auto delete completed. Deleted ${safeStr(autoPurge.deleted_count || 0)} audit logs. Countdown reset for next month.`);
            return;
        }

        if (autoPurge.already_purged) {
            setPurgeStatusText('This month already finished its auto delete. Countdown now points to next month end at 10:00 PM Manila time.');
            return;
        }

        renderPurgeCountdown();
    }

    function scheduleAutoPurgeRefresh() {
        if (!purgeAtRaw) return;
        if (purgeRefreshTimer) {
            window.clearTimeout(purgeRefreshTimer);
            purgeRefreshTimer = null;
        }

        const purgeAt = new Date(purgeAtRaw);
        if (Number.isNaN(purgeAt.getTime())) return;

        const delayMs = purgeAt.getTime() - Date.now();
        if (delayMs <= 0) {
            state.page = 1;
            loadData();
            return;
        }

        const safeStepMs = Math.min(delayMs + 500, PURGE_REFRESH_STEP_MS, MAX_TIMEOUT_MS);
        purgeRefreshTimer = window.setTimeout(() => {
            const remainingMs = purgeAt.getTime() - Date.now();
            if (remainingMs <= 0) {
                state.page = 1;
                loadData();
                return;
            }

            scheduleAutoPurgeRefresh();
        }, safeStepMs);
    }

    function syncPurgeTarget(raw) {
        if (!raw) return;
        purgeAtRaw = safeStr(raw);
        renderPurgeCountdown();
        scheduleAutoPurgeRefresh();
    }
 
     async function loadData() {
         if (!dataUrl) return;
 
         setLoading(true);
         updatePaginationUi();
 
         try {
             const query = buildQueryParams();
             const res = await fetch(`${dataUrl}?${query}`, {
                 headers: { 'Accept': 'application/json' },
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
            applyAutoPurgeState(json.auto_purge || null);
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
 
     function resetFilters() {
         state.module = 'all';
         state.moduleLabel = 'All';
         state.page = 1;
         state.search = { name: '', username: '', datetime: '', action: '', module: '' };
 
         if (els.searchName) els.searchName.value = '';
         if (els.searchUsername) els.searchUsername.value = '';
         if (els.searchDatetime) els.searchDatetime.value = '';
         if (els.searchAction) els.searchAction.value = '';
         if (els.searchModule) els.searchModule.value = '';
 
         setCardsActive();
         setActiveFilterLabel();
         loadData();
     }
 
     function wireEvents() {
         els.cards.forEach((btn) => {
             btn.addEventListener('click', () => {
                 const key = btn.dataset.module || 'all';
                 const label = btn.querySelector('h3')?.textContent?.trim() || key;
                 state.module = key;
                 state.moduleLabel = label;
                 state.page = 1;
                 setCardsActive();
                 setActiveFilterLabel();
                 loadData();
             });
         });
 
         if (els.refresh) els.refresh.addEventListener('click', () => loadData());
         if (els.clearFilters) els.clearFilters.addEventListener('click', () => resetFilters());
 
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
             state.search.name = safeStr(els.searchName?.value || '').trim();
             state.search.username = safeStr(els.searchUsername?.value || '').trim();
             state.search.datetime = safeStr(els.searchDatetime?.value || '').trim();
             state.search.action = safeStr(els.searchAction?.value || '').trim();
             state.search.module = safeStr(els.searchModule?.value || '').trim();
             loadData();
         }, 250);
 
         [els.searchName, els.searchUsername, els.searchDatetime, els.searchAction, els.searchModule].forEach((inp) => {
             if (!inp) return;
             inp.addEventListener('input', onSearchInput);
         });
     }
 
     function init() {
         setCardsActive();
         setActiveFilterLabel();
         wireEvents();
        startPurgeCountdown();
        scheduleAutoPurgeRefresh();
         loadData();
         if (window.lucide?.createIcons) window.lucide.createIcons();
     }
 
     init();
 })();
