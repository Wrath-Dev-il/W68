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
        target: document.getElementById('sync-target'),
        globalStatus: document.getElementById('sync-global-status'),
        refresh: document.getElementById('sync-refresh'),
        runAll: document.getElementById('sync-run-all'),
        runEverything: document.getElementById('sync-run-everything'),
        modeManual: document.getElementById('sync-mode-manual'),
        modeAuto: document.getElementById('sync-mode-auto'),
    };

    const state = {
        dashboards: [],
        activeKey: null,
        mode: 'manual',
        isRunning: false,
        sourceEnabled: false,
        targetUrl: '',
        chunkSize: 500,
        autoIntervalMs: 1800000,
        autoTimer: null,
        tableStatus: new Map(),
    };

    function safeStr(value) {
        return value === null || value === undefined ? '' : String(value);
    }

    function escapeHtml(value) {
        return safeStr(value).replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        })[char]);
    }

    function tableKey(connection, table) {
        return `${connection}:${table}`;
    }

    function storageKey(connection, table) {
        return `datasync:last:${connection}:${table}`;
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

    function setGlobalStatus(message, type = 'info') {
        if (!els.globalStatus) return;

        if (!message) {
            els.globalStatus.classList.add('hidden');
            els.globalStatus.textContent = '';
            return;
        }

        els.globalStatus.classList.remove(
            'hidden',
            'border-slate-200',
            'bg-slate-50',
            'text-slate-700',
            'border-emerald-200',
            'bg-emerald-50',
            'text-emerald-700',
            'border-red-200',
            'bg-red-50',
            'text-red-700',
            'border-amber-200',
            'bg-amber-50',
            'text-amber-700'
        );

        const classes = type === 'success'
            ? ['border-emerald-200', 'bg-emerald-50', 'text-emerald-700']
            : type === 'error'
                ? ['border-red-200', 'bg-red-50', 'text-red-700']
                : type === 'warning'
                    ? ['border-amber-200', 'bg-amber-50', 'text-amber-700']
                    : ['border-slate-200', 'bg-slate-50', 'text-slate-700'];

        els.globalStatus.classList.add(...classes);
        els.globalStatus.textContent = message;
    }

    function stopAutoTimer() {
        if (state.autoTimer) {
            clearInterval(state.autoTimer);
            state.autoTimer = null;
        }
    }

    function startAutoTimer() {
        stopAutoTimer();

        if (state.mode !== 'auto' || !state.sourceEnabled) return;

        state.autoTimer = setInterval(() => {
            if (!state.isRunning) {
                runSyncEverything(true);
            }
        }, state.autoIntervalMs);
    }

    function setMode(mode) {
        state.mode = mode === 'auto' ? 'auto' : 'manual';

        if (els.modeManual) {
            els.modeManual.classList.toggle('is-active', state.mode === 'manual');
        }

        if (els.modeAuto) {
            els.modeAuto.classList.toggle('is-active', state.mode === 'auto');
        }

        if (state.mode === 'auto') {
            setGlobalStatus(
                `Auto mode enabled. All databases will sync every ${Math.round(state.autoIntervalMs / 60000)} minutes while this page stays open.`,
                'warning'
            );
            startAutoTimer();
        } else {
            stopAutoTimer();
            setGlobalStatus('', 'info');
        }

        renderTable();
        updateButtons();
    }

    function setActiveDashboard(key) {
        state.activeKey = key;
        renderDashboards();
        renderTable();
        updateButtons();
    }

    function getActiveDashboard() {
        return state.dashboards.find((dashboard) => dashboard.key === state.activeKey) || null;
    }

    function allSyncableTables() {
        const items = [];

        state.dashboards.forEach((dashboard) => {
            (dashboard.tables || []).forEach((table) => {
                if (table.syncable !== false) {
                    items.push({
                        dashboard,
                        table,
                    });
                }
            });
        });

        return items;
    }

    function updateButtons() {
        const manual = state.mode === 'manual';
        const canRun = state.sourceEnabled && !state.isRunning;

        if (els.runAll) {
            els.runAll.disabled = !manual || !canRun || !getActiveDashboard();
        }

        if (els.runEverything) {
            els.runEverything.disabled = !manual || !canRun || allSyncableTables().length === 0;
        }

        if (els.refresh) {
            els.refresh.disabled = state.isRunning;
        }
    }

    function renderDashboards() {
        if (!els.dashboards) return;

        if (!Array.isArray(state.dashboards) || state.dashboards.length === 0) {
            els.dashboards.innerHTML = `
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
                    <div class="text-xs font-semibold text-slate-400">No databases available</div>
                    <div class="w-12 h-12 rounded-xl bg-maroon-900 flex items-center justify-center shadow-inner">
                        <i data-lucide="circle-off" class="w-6 h-6 text-goldlining-400"></i>
                    </div>
                </div>
            `;

            if (window.lucide?.createIcons) window.lucide.createIcons();
            return;
        }

        els.dashboards.innerHTML = state.dashboards.map((dashboard) => {
            const active = dashboard.key === state.activeKey;
            const syncableCount = (dashboard.tables || []).filter((table) => table.syncable !== false).length;

            return `
                <button type="button"
                    class="sync-dashboard-card bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between group hover:border-slate-200 transition-all hover:shadow-md hover:scale-[1.01] duration-200 overflow-hidden text-left ${active ? 'is-active ring-2 ring-goldlining-500/60' : ''}"
                    data-key="${escapeHtml(dashboard.key)}">
                    <div class="space-y-0.5 min-w-0">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Database</span>
                        <h3 class="text-sm font-extrabold text-slate-800 tracking-tight truncate">${escapeHtml(dashboard.label)}</h3>
                        <p class="text-[10px] text-slate-400 font-semibold">${syncableCount} syncable table${syncableCount === 1 ? '' : 's'}</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-maroon-900 flex items-center justify-center shadow-inner group-hover:scale-105 transition-transform duration-300 flex-shrink-0 ml-3">
                        <i data-lucide="${escapeHtml(dashboard.icon || 'database')}" class="w-6 h-6 text-goldlining-400"></i>
                    </div>
                </button>
            `;
        }).join('');

        els.dashboards.querySelectorAll('.sync-dashboard-card').forEach((button) => {
            button.addEventListener('click', () => {
                const key = button.getAttribute('data-key');
                if (key) setActiveDashboard(key);
            });
        });

        if (window.lucide?.createIcons) window.lucide.createIcons();
    }

    function progressHtml(table) {
        const key = tableKey(table.connection, table.table);
        const status = state.tableStatus.get(key);

        if (table.syncable === false) {
            return `
                <div class="text-[10px] font-bold text-red-500">No PRIMARY/UNIQUE key — skipped</div>
            `;
        }

        const syncKey = Array.isArray(table.sync_key) ? table.sync_key.join(', ') : '—';

        if (!status) {
            return `<div class="text-[10px] font-semibold text-slate-500">Key: ${escapeHtml(syncKey)}</div>`;
        }

        if (status.error) {
            return `
                <div class="text-[10px] font-bold text-red-600">${escapeHtml(status.error)}</div>
            `;
        }

        if (status.done) {
            return `
                <div class="text-[10px] font-bold text-emerald-600">${status.total.toLocaleString()} rows synced</div>
            `;
        }

        const total = Number(status.total || 0);
        const processed = Number(status.processed || 0);
        const pct = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 100;

        return `
            <div class="space-y-1.5">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[10px] font-bold text-slate-600">${processed.toLocaleString()} / ${total.toLocaleString()}</span>
                    <span class="text-[10px] font-black text-maroon">${pct}%</span>
                </div>
                <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-maroon-900 transition-all duration-200" style="width:${pct}%"></div>
                </div>
            </div>
        `;
    }

    function renderTable() {
        if (!els.tbody) return;

        const dashboard = getActiveDashboard();

        if (!dashboard) {
            if (els.activeDashboard) els.activeDashboard.textContent = 'Select a database';

            els.tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="px-4 py-10 text-center text-slate-400 text-xs font-semibold">
                        Select a database to view tables
                    </td>
                </tr>
            `;

            updateButtons();
            return;
        }

        if (els.activeDashboard) {
            els.activeDashboard.textContent = `${safeStr(dashboard.label)} (${state.mode.toUpperCase()} MODE)`;
        }

        const tables = Array.isArray(dashboard.tables) ? dashboard.tables : [];

        if (tables.length === 0) {
            els.tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="px-4 py-10 text-center text-slate-400 text-xs font-semibold">
                        No business tables found
                    </td>
                </tr>
            `;

            updateButtons();
            return;
        }

        els.tbody.innerHTML = tables.map((table, index) => {
            const connection = safeStr(table.connection);
            const tableName = safeStr(table.table);
            const status = state.tableStatus.get(tableKey(connection, tableName));
            const last = localStorage.getItem(storageKey(connection, tableName));
            const disabled = !state.sourceEnabled || state.mode !== 'manual' || state.isRunning || table.syncable === false;

            let actionText = 'Sync Now';

            if (!state.sourceEnabled) {
                actionText = 'Receiver Only';
            } else if (table.syncable === false) {
                actionText = 'Skipped';
            } else if (status && !status.done && !status.error) {
                actionText = 'Syncing...';
            } else if (state.mode === 'auto') {
                actionText = 'Auto';
            }

            return `
                <tr class="hover:bg-slate-50/60">
                    <td class="px-4 py-3 align-top">
                        <div class="text-[10px] font-black uppercase tracking-widest text-slate-600">${escapeHtml(connection)}</div>
                    </td>

                    <td class="px-4 py-3 align-top">
                        <div class="text-xs font-black text-slate-800">${escapeHtml(tableName)}</div>
                        <div class="mt-1 text-[10px] font-semibold text-slate-400">#${index + 1}</div>
                    </td>

                    <td class="px-4 py-3 align-top min-w-[280px]">
                        ${progressHtml(table)}
                    </td>

                    <td class="px-4 py-3 align-top">
                        <div class="text-xs font-semibold text-slate-600">${last ? formatTs(last) : '—'}</div>
                    </td>

                    <td class="px-4 py-3 text-center align-top">
                        <button type="button"
                            class="sync-run-btn px-4 py-2 rounded-xl bg-maroon-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-maroon-800 disabled:opacity-50 disabled:cursor-not-allowed"
                            data-connection="${escapeHtml(connection)}"
                            data-table="${escapeHtml(tableName)}"
                            ${disabled ? 'disabled' : ''}>
                            ${actionText}
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        els.tbody.querySelectorAll('.sync-run-btn').forEach((button) => {
            button.addEventListener('click', async () => {
                const connection = button.getAttribute('data-connection') || '';
                const table = button.getAttribute('data-table') || '';

                if (!connection || !table) return;

                await runItems([{ dashboard, table }], false);
            });
        });

        updateButtons();

        if (window.lucide?.createIcons) window.lucide.createIcons();
    }

    async function requestChunk(connection, table, offset, knownTotal) {
        const response = await fetch(runUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                connection,
                table,
                offset,
                knownTotal,
                limit: state.chunkSize,
            }),
        });

        const text = await response.text();
        let json = {};

        try {
            json = text ? JSON.parse(text) : {};
        } catch (e) {
            throw new Error(`Unreadable server response (HTTP ${response.status}).`);
        }

        if (!response.ok || json.success !== true) {
            throw new Error(json.message || `Sync request failed (HTTP ${response.status}).`);
        }

        return json.result || {};
    }

    async function runSyncOne(connection, table, options = {}) {
        if (!state.sourceEnabled) {
            throw new Error('This deployment is configured as the HostForge receiver, not the localhost source.');
        }

        const key = tableKey(connection, table);
        let offset = 0;
        let total = null;

        state.tableStatus.set(key, {
            processed: 0,
            total: 0,
            done: false,
            error: null,
        });

        renderTable();

        try {
            while (true) {
                const result = await requestChunk(connection, table, offset, total);

                total = Number(result.total ?? total ?? 0);
                offset = Number(result.next_offset ?? offset);

                state.tableStatus.set(key, {
                    processed: offset,
                    total,
                    done: Boolean(result.done),
                    error: null,
                });

                renderTable();

                if (result.done) {
                    localStorage.setItem(storageKey(connection, table), new Date().toISOString());
                    break;
                }
            }

            return true;
        } catch (error) {
            state.tableStatus.set(key, {
                processed: offset,
                total: total || 0,
                done: false,
                error: error?.message || 'Sync failed.',
            });

            renderTable();

            if (!options.silent) {
                setGlobalStatus(`${connection}.${table}: ${error?.message || 'Sync failed.'}`, 'error');
            }

            return false;
        }
    }

    async function runItems(items, automatic = false) {
        if (state.isRunning) return false;

        state.isRunning = true;
        updateButtons();
        renderTable();

        let completed = 0;
        let failed = 0;

        try {
            for (const item of items) {
                const connection = item.table.connection;
                const table = item.table.table;

                setGlobalStatus(
                    `${automatic ? 'AUTO: ' : ''}Syncing ${connection}.${table} (${completed + failed + 1}/${items.length})...`,
                    'info'
                );

                const ok = await runSyncOne(connection, table, { silent: true });

                if (ok) {
                    completed++;
                } else {
                    failed++;
                }
            }

            if (failed === 0) {
                setGlobalStatus(
                    `${automatic ? 'Auto sync' : 'Sync'} finished successfully: ${completed} table${completed === 1 ? '' : 's'}.`,
                    'success'
                );
            } else {
                setGlobalStatus(
                    `Sync finished with ${completed} successful and ${failed} failed table${failed === 1 ? '' : 's'}.`,
                    'error'
                );
            }

            return failed === 0;
        } finally {
            state.isRunning = false;
            updateButtons();
            renderTable();
        }
    }

    async function runSyncAll() {
        const dashboard = getActiveDashboard();
        if (!dashboard || state.mode !== 'manual') return;

        const items = (dashboard.tables || [])
            .filter((table) => table.syncable !== false)
            .map((table) => ({ dashboard, table }));

        await runItems(items, false);
    }

    async function runSyncEverything(automatic = false) {
        if (!automatic && state.mode !== 'manual') return;

        const items = allSyncableTables();

        if (items.length === 0) {
            setGlobalStatus('No syncable business tables were found.', 'warning');
            return;
        }

        await runItems(items, automatic);
    }

    async function loadConfig() {
        if (!configUrl) return;

        setGlobalStatus('Loading DataSync configuration...', 'info');

        try {
            const response = await fetch(configUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const json = await response.json();

            if (!response.ok || json.success !== true) {
                throw new Error(json.message || `Unable to load DataSync configuration (HTTP ${response.status}).`);
            }

            state.dashboards = Array.isArray(json.dashboards) ? json.dashboards : [];
            state.activeKey = state.dashboards[0]?.key || null;
            state.sourceEnabled = Boolean(json.source_enabled);
            state.targetUrl = safeStr(json.target_url);
            state.chunkSize = Number(json.chunk_size || 500);
            state.autoIntervalMs = Number(json.auto_interval_ms || 1800000);

            if (els.target) {
                els.target.textContent = state.sourceEnabled
                    ? `Destination: ${state.targetUrl || 'NOT CONFIGURED'}`
                    : `This environment is the HostForge DataSync receiver. Run synchronization from localhost.`;
            }

            renderDashboards();
            renderTable();

            if (state.sourceEnabled) {
                setGlobalStatus('Ready. Choose a database or click Sync Everything.', 'success');
            } else {
                setGlobalStatus(
                    'Receiver mode is active. Open this DataSync page on localhost to push data to this domain.',
                    'warning'
                );
            }

            startAutoTimer();
        } catch (error) {
            state.dashboards = [];
            state.activeKey = null;
            renderDashboards();
            renderTable();

            setGlobalStatus(error?.message || 'Unable to load DataSync configuration.', 'error');
        }
    }

    function wireEvents() {
        if (els.modeManual) {
            els.modeManual.addEventListener('click', () => setMode('manual'));
        }

        if (els.modeAuto) {
            els.modeAuto.addEventListener('click', () => setMode('auto'));
        }

        if (els.refresh) {
            els.refresh.addEventListener('click', loadConfig);
        }

        if (els.runAll) {
            els.runAll.addEventListener('click', runSyncAll);
        }

        if (els.runEverything) {
            els.runEverything.addEventListener('click', () => runSyncEverything(false));
        }

        window.addEventListener('beforeunload', stopAutoTimer);
    }

    function init() {
        wireEvents();
        loadConfig();

        if (window.lucide?.createIcons) {
            window.lucide.createIcons();
        }
    }

    init();
})();
