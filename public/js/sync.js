 (function () {
     const root = document.getElementById('sync-page');
     if (!root) return;
 
     const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
     const configUrl = root.dataset.configUrl || '';
     const runUrl = root.dataset.runUrl || '';
 
     const els = {
         dashboards: document.getElementById('sync-dashboards'),
         tbody: document.getElementById('sync-tbody'),
         activeDashboard: document.getElementById('sync-active-dashboard'),
         refresh: document.getElementById('sync-refresh'),
         runAll: document.getElementById('sync-run-all'),
         modeManual: document.getElementById('sync-mode-manual'),
         modeAuto: document.getElementById('sync-mode-auto'),
     };
 
     const state = {
         dashboards: [],
         activeKey: null,
         mode: 'manual',
         isRunning: false,
     };
 
     function safeStr(v) {
         if (v === null || v === undefined) return '';
         return String(v);
     }
 
     function storageKey(dashboardKey, connection, table) {
         return `sync:last:${dashboardKey}:${connection}:${table}`;
     }
 
     function formatTs(ts) {
         if (!ts) return '—';
         const d = new Date(ts);
         if (Number.isNaN(d.getTime())) return safeStr(ts);
         const yyyy = d.getFullYear();
         const mm = String(d.getMonth() + 1).padStart(2, '0');
         const dd = String(d.getDate()).padStart(2, '0');
         const hh = String(d.getHours()).padStart(2, '0');
         const mi = String(d.getMinutes()).padStart(2, '0');
         const ss = String(d.getSeconds()).padStart(2, '0');
         return `${yyyy}-${mm}-${dd} ${hh}:${mi}:${ss}`;
     }
 
     function setMode(mode) {
         state.mode = mode === 'auto' ? 'auto' : 'manual';
         if (els.modeManual) els.modeManual.classList.toggle('is-active', state.mode === 'manual');
         if (els.modeAuto) els.modeAuto.classList.toggle('is-active', state.mode === 'auto');
         renderTable();
     }
 
     function setActiveDashboard(key) {
         state.activeKey = key;
         renderDashboards();
         renderTable();
     }
 
     function getActiveDashboard() {
         return state.dashboards.find((d) => d.key === state.activeKey) || null;
     }
 
     function renderDashboards() {
         if (!els.dashboards) return;
 
         if (!Array.isArray(state.dashboards) || state.dashboards.length === 0) {
             els.dashboards.innerHTML = `
                 <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
                     <div class="text-xs font-semibold text-slate-400">No dashboards</div>
                     <div class="w-12 h-12 rounded-xl bg-maroon-900 flex items-center justify-center shadow-inner">
                         <i data-lucide="circle-off" class="w-6 h-6 text-goldlining-400"></i>
                     </div>
                 </div>
             `;
             if (window.lucide?.createIcons) window.lucide.createIcons();
             return;
         }
 
         els.dashboards.innerHTML = state.dashboards.map((d) => {
             const isActive = d.key === state.activeKey;
             return `
                 <button type="button"
                     class="sync-dashboard-card bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between group hover:border-slate-200 transition-all hover:shadow-md hover:scale-[1.01] duration-200 overflow-hidden text-left ${isActive ? 'is-active ring-2 ring-goldlining-500/60' : ''}"
                     data-key="${safeStr(d.key)}">
                     <div class="space-y-0.5 min-w-0">
                         <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Dashboard</span>
                         <h3 class="text-sm font-extrabold text-slate-800 tracking-tight truncate">${safeStr(d.label)}</h3>
                         <p class="text-[10px] text-slate-400 font-semibold">Click to view tables</p>
                     </div>
                     <div class="w-12 h-12 rounded-xl bg-maroon-900 flex items-center justify-center shadow-inner group-hover:scale-105 transition-transform duration-300 flex-shrink-0 ml-3">
                         <i data-lucide="${safeStr(d.icon || 'grid-3x3')}" class="w-6 h-6 text-goldlining-400"></i>
                     </div>
                 </button>
             `;
         }).join('');
 
         els.dashboards.querySelectorAll('.sync-dashboard-card').forEach((btn) => {
             btn.addEventListener('click', () => {
                 const key = btn.getAttribute('data-key');
                 if (!key) return;
                 setActiveDashboard(key);
             });
         });
 
         if (window.lucide?.createIcons) window.lucide.createIcons();
     }
 
     function renderTable() {
         if (!els.tbody) return;
 
         const dash = getActiveDashboard();
         if (!dash) {
             if (els.activeDashboard) els.activeDashboard.textContent = 'Select a dashboard';
             if (els.runAll) els.runAll.disabled = true;
             els.tbody.innerHTML = `<tr><td colspan="5" class="px-4 py-10 text-center text-slate-400 text-xs font-semibold">Select a dashboard to view tables</td></tr>`;
             return;
         }
 
         if (els.activeDashboard) els.activeDashboard.textContent = `${safeStr(dash.label)} (${safeStr(state.mode).toUpperCase()} MODE)`;
         if (els.runAll) els.runAll.disabled = state.mode !== 'manual' || state.isRunning;
 
         const tables = Array.isArray(dash.tables) ? dash.tables : [];
         if (tables.length === 0) {
             els.tbody.innerHTML = `<tr><td colspan="5" class="px-4 py-10 text-center text-slate-400 text-xs font-semibold">No tables configured</td></tr>`;
             return;
         }
 
         els.tbody.innerHTML = tables.map((t, idx) => {
             const connection = safeStr(t.connection);
             const table = safeStr(t.table);
             const label = safeStr(t.label);
             const last = localStorage.getItem(storageKey(dash.key, connection, table));
             const lastText = last ? formatTs(last) : '—';
             const disabled = state.mode !== 'manual' || state.isRunning;
             const actionText = disabled ? (state.mode !== 'manual' ? 'Auto' : 'Running') : 'Sync Now';
 
             return `
                 <tr class="hover:bg-slate-50/60">
                     <td class="px-4 py-3 align-top">
                         <div class="text-[10px] font-black uppercase tracking-widest text-slate-600">${connection}</div>
                     </td>
                     <td class="px-4 py-3 align-top">
                         <div class="text-xs font-black text-slate-800">${table}</div>
                         <div class="mt-1 text-[10px] font-semibold text-slate-400">#${idx + 1}</div>
                     </td>
                     <td class="px-4 py-3 align-top">
                         <div class="text-xs font-semibold text-slate-700">${label || '—'}</div>
                     </td>
                     <td class="px-4 py-3 align-top">
                         <div class="text-xs font-semibold text-slate-600">${lastText}</div>
                     </td>
                     <td class="px-4 py-3 text-center align-top">
                         <button type="button"
                             class="sync-run-btn px-4 py-2 rounded-xl bg-maroon-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-maroon-800 disabled:opacity-50 disabled:cursor-not-allowed"
                             data-dashboard="${safeStr(dash.key)}"
                             data-connection="${connection}"
                             data-table="${table}"
                             ${disabled ? 'disabled' : ''}>
                             ${actionText}
                         </button>
                     </td>
                 </tr>
             `;
         }).join('');
 
         els.tbody.querySelectorAll('.sync-run-btn').forEach((btn) => {
             btn.addEventListener('click', async () => {
                 const dashboardKey = btn.getAttribute('data-dashboard') || '';
                 const connection = btn.getAttribute('data-connection') || '';
                 const table = btn.getAttribute('data-table') || '';
                 if (!dashboardKey || !table) return;
                 await runSyncOne(dashboardKey, connection, table);
             });
         });
 
         if (window.lucide?.createIcons) window.lucide.createIcons();
     }
 
     async function runSyncOne(dashboardKey, connection, table) {
         if (!runUrl || state.mode !== 'manual') return;
         if (state.isRunning) return;
 
         state.isRunning = true;
         renderTable();
 
         try {
             const res = await fetch(runUrl, {
                 method: 'POST',
                 headers: {
                     'Accept': 'application/json',
                     'Content-Type': 'application/json',
                     'X-CSRF-TOKEN': csrfToken,
                 },
                 body: JSON.stringify({
                     mode: state.mode,
                     dashboardKey,
                     connection,
                     table,
                 }),
             });
             const json = await res.json();
             if (json && json.success) {
                 localStorage.setItem(storageKey(dashboardKey, connection, table), new Date().toISOString());
             }
         } catch (e) {
         } finally {
             state.isRunning = false;
             renderTable();
         }
     }
 
     async function runSyncAll() {
         const dash = getActiveDashboard();
         if (!dash) return;
         if (state.mode !== 'manual') return;
 
         const tables = Array.isArray(dash.tables) ? dash.tables : [];
         for (const t of tables) {
             await runSyncOne(dash.key, safeStr(t.connection), safeStr(t.table));
         }
     }
 
     async function loadConfig() {
         if (!configUrl) return;
         try {
             const res = await fetch(configUrl, { headers: { 'Accept': 'application/json' } });
             const json = await res.json();
             if (json && json.success && Array.isArray(json.dashboards)) {
                 state.dashboards = json.dashboards;
                 state.activeKey = state.dashboards[0]?.key || null;
                 renderDashboards();
                 renderTable();
                 return;
             }
         } catch (e) {
         }
 
         state.dashboards = [];
         state.activeKey = null;
         renderDashboards();
         renderTable();
     }
 
     function wireEvents() {
         if (els.modeManual) els.modeManual.addEventListener('click', () => setMode('manual'));
         if (els.modeAuto) els.modeAuto.addEventListener('click', () => setMode('auto'));
 
         if (els.refresh) els.refresh.addEventListener('click', () => loadConfig());
         if (els.runAll) els.runAll.addEventListener('click', () => runSyncAll());
     }
 
     function init() {
         wireEvents();
         loadConfig();
         if (window.lucide?.createIcons) window.lucide.createIcons();
     }
 
     init();
 })();
