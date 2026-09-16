const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
const importModal = document.getElementById('importModal');
const confirmModal = document.getElementById('confirmModal');
const successModal = document.getElementById('successModal');
const importForm = document.getElementById('importForm');
const importTarget = document.getElementById('importTarget');
const excelFile = document.getElementById('excelFile');
const productImportOptions = document.getElementById('productImportOptions');
const productAgeInputs = Array.from(document.querySelectorAll('input[name="product_age"]'));
const importDate = document.getElementById('importDate');
const importDateHint = document.getElementById('importDateHint');
const confirmImportText = document.getElementById('confirmImportText');
const successText = document.getElementById('successText');
const toast = document.getElementById('developerToast');
const toastText = document.getElementById('developerToastText');
const runDataUpBtn = document.getElementById('runDataUpBtn');
const latestBackup = document.getElementById('latestBackup');
const backupCount = document.getElementById('backupCount');
const developerProductCount = document.getElementById('developerProductCount');
const themeStatus = document.getElementById('themeStatus');
const themeControls = document.querySelector('[data-theme-save-url]');
const themeCountdowns = Array.from(document.querySelectorAll('[data-theme-countdown]'));
const serverThemeNowMs = Number(document.body.dataset.themeNowMs || Date.now());
const pageLoadedAtMs = Date.now();

let pendingImport = null;
let toastTimer = null;
let autoAppliedWindowKey = themeControls?.dataset.lastAutoWindow || '';
let suppressedAutoWindowKey = themeControls?.dataset.manualDefaultWindow || '';

const targetLabels = {
    'product-list': 'Product List',
    'supplier-list': 'Supplier List',
    'customer-list': 'Customer List',
    'forwarder-list': 'Forwarder List',
    'coming-soon': 'Coming Soon',
};

const themeLabels = {
    default: 'Default',
    christmas: 'Christmas',
    'holy-week': 'Holy Week',
    halloween: 'Halloween',
    'new-year': 'New Year',
    'chinese-new-year': 'Chinese New Year',
};

function showModal(modal) {
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
}

function hideModal(modal) {
    if (!modal) return;
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
}

function showToast(message) {
    if (!toast || !toastText) return;
    window.clearTimeout(toastTimer);
    toastText.textContent = message;
    toast.classList.add('is-visible');
    toastTimer = window.setTimeout(() => toast.classList.remove('is-visible'), 3200);
}

function openImportModal() {
    updateProductImportOptions();
    showModal(importModal);
}

document.getElementById('importDataBtn')?.addEventListener('click', openImportModal);
document.getElementById('openImportSecondary')?.addEventListener('click', openImportModal);

document.querySelectorAll('[data-modal-close]').forEach((button) => {
    button.addEventListener('click', () => hideModal(document.getElementById(button.dataset.modalClose)));
});

[importModal, confirmModal, successModal].forEach((modal) => {
    modal?.addEventListener('click', (event) => {
        if (event.target === modal) {
            hideModal(modal);
        }
    });
});

importForm?.addEventListener('submit', (event) => {
    event.preventDefault();

    const selectedTarget = importTarget?.value || '';
    const file = excelFile?.files?.[0] || null;
    const productAge = productAgeInputs.find((input) => input.checked)?.value || 'new';
    const selectedDate = importDate?.value || '';

    if (selectedTarget === 'coming-soon') {
        showToast('Coming Soon is not ready for imports yet.');
        return;
    }

    if (selectedTarget !== 'product-list') {
        showToast('Only Product List imports are available right now.');
        return;
    }

    if (!file) {
        showToast('Choose an Excel file first.');
        return;
    }

    const allowedExtensions = ['xlsx'];
    const extension = file.name.split('.').pop()?.toLowerCase();

    if (!allowedExtensions.includes(extension)) {
        showToast('Only XLSX files are accepted for Product List imports.');
        return;
    }

    if (selectedTarget === 'product-list' && productAge === 'old' && !selectedDate) {
        showToast('Choose a date for old Product List imports.');
        return;
    }

    pendingImport = {
        targetValue: selectedTarget,
        target: targetLabels[selectedTarget] || 'Selected List',
        fileName: file.name,
        file,
        productAge,
        importDate: selectedDate,
    };

    if (confirmImportText) {
        const productType = selectedTarget === 'product-list'
            ? ` as ${productAge === 'old' ? 'Old' : 'New'} products`
            : '';
        confirmImportText.textContent = `Import ${pendingImport.fileName} into ${pendingImport.target}${productType}?`;
    }

    hideModal(importModal);
    showModal(confirmModal);
});

document.getElementById('confirmNoBtn')?.addEventListener('click', () => {
    pendingImport = null;
    hideModal(confirmModal);
    showModal(importModal);
});

document.getElementById('confirmYesBtn')?.addEventListener('click', async (event) => {
    if (!pendingImport || !importForm?.dataset.importUrl) {
        hideModal(confirmModal);
        return;
    }

    const button = event.currentTarget;
    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Importing...';

    const formData = new FormData();
    formData.append('target', pendingImport.targetValue);
    formData.append('excel_file', pendingImport.file);
    formData.append('product_age', pendingImport.productAge);
    if (pendingImport.importDate) {
        formData.append('import_date', pendingImport.importDate);
    }

    try {
        const response = await fetch(importForm.dataset.importUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
            },
            body: formData,
        });

        const payload = await response.json();
        if (!response.ok || !payload.success) {
            throw new Error(payload.message || 'Import failed.');
        }

        hideModal(confirmModal);

        if (successText) {
            const summary = payload.summary || {};
            successText.textContent = `${payload.message} Total products: ${(summary.total_products || 0).toLocaleString()}.`;
        }

        if (developerProductCount && payload.summary?.total_products !== undefined) {
            developerProductCount.textContent = Number(payload.summary.total_products).toLocaleString();
        }

        pendingImport = null;
        importForm.reset();
        updateProductImportOptions();
        showModal(successModal);
    } catch (error) {
        showToast(error.message || 'Import failed.');
    } finally {
        button.disabled = false;
        button.textContent = originalText;
    }
});

function todayString() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function updateProductImportOptions() {
    const isProductList = importTarget?.value === 'product-list';
    if (productImportOptions) {
        productImportOptions.classList.toggle('hidden', !isProductList);
    }

    const selectedAge = productAgeInputs.find((input) => input.checked)?.value || 'new';
    const isOld = isProductList && selectedAge === 'old';

    if (importDate) {
        if (!importDate.value) {
            importDate.value = todayString();
        }

        if (!isOld) {
            importDate.value = todayString();
        }

        importDate.disabled = !isOld;
        importDate.required = isOld;
    }

    if (importDateHint) {
        importDateHint.textContent = isOld
            ? 'This date will be saved as date_added for the imported old products.'
            : 'Newly added products automatically use today\'s date.';
    }
}

importTarget?.addEventListener('change', updateProductImportOptions);
productAgeInputs.forEach((input) => input.addEventListener('change', updateProductImportOptions));
updateProductImportOptions();

runDataUpBtn?.addEventListener('click', async () => {
    const runUrl = runDataUpBtn.dataset.runUrl;
    if (!runUrl) return;

    const originalHtml = runDataUpBtn.innerHTML;
    runDataUpBtn.disabled = true;
    runDataUpBtn.innerHTML = 'Running...';

    try {
        const response = await fetch(runUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
            },
        });

        const payload = await response.json();

        if (!response.ok || !payload.success) {
            throw new Error(payload.message || 'Data-up failed.');
        }

        if (latestBackup) latestBackup.textContent = 'Updated just now';
        if (backupCount) {
            const nextCount = Number.parseInt(backupCount.textContent.replace(/\D/g, ''), 10) + 1;
            backupCount.textContent = Number.isFinite(nextCount) ? nextCount.toLocaleString() : backupCount.textContent;
        }

        showToast(payload.message || 'Data-up completed successfully.');
    } catch (error) {
        showToast(error.message || 'Data-up failed.');
    } finally {
        runDataUpBtn.disabled = false;
        runDataUpBtn.innerHTML = originalHtml;
    }
});

function triggerDownload(url, btn) {
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = 'Downloading...';

    const ts = new Date().toISOString().replace(/[:T]/g, '-').slice(0, 19);
    const isAll = btn.classList.contains('developer-download-all-btn');
    const name = isAll ? 'hatdog_full_backup_' + ts + '.zip' : 'hatdog_backup_' + ts + '.zip';
    const fullUrl = url.replace(/\/+$/, '') + '/' + name;
    window.location.href = fullUrl;

    showToast('Download started.');

    btn.disabled = false;
    btn.innerHTML = originalHtml;
}

document.querySelectorAll('.developer-download-btn, .developer-download-all-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
        const url = btn.dataset.downloadUrl;
        if (url) triggerDownload(url, btn);
    });
});

function applyTheme(theme) {
    const normalizedTheme = themeLabels[theme] ? theme : 'default';
    document.body.dataset.theme = normalizedTheme;

    document.querySelectorAll('.developer-theme-option').forEach((button) => {
        button.classList.toggle('is-active', button.dataset.themeOption === normalizedTheme);
    });

    if (themeStatus) {
        themeStatus.textContent = themeLabels[normalizedTheme] || 'Default';
    }
}

function currentThemeNowMs() {
    return serverThemeNowMs + (Date.now() - pageLoadedAtMs);
}

function formatRemaining(milliseconds) {
    const totalSeconds = Math.max(0, Math.floor(milliseconds / 1000));
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    return `${String(hours).padStart(4, '0')}h ${String(minutes).padStart(2, '0')}m ${String(seconds).padStart(2, '0')}s`;
}

function countdownMeta(element) {
    return {
        element,
        theme: element.dataset.themeCountdown,
        targetMs: Number(element.dataset.targetMs || Date.parse(element.dataset.target || '')),
        autoStartMs: Number(element.dataset.autoStartMs || Date.parse(element.dataset.autoStart || '')),
        themeEndMs: Number(element.dataset.themeEndMs || Date.parse(element.dataset.themeEnd || '')),
        priority: Number(element.dataset.priority || 99),
        windowKey: element.dataset.windowKey || '',
    };
}

function getActiveAutoTheme(nowMs) {
    return themeCountdowns
        .map(countdownMeta)
        .filter((theme) => theme.theme && Number.isFinite(theme.targetMs) && Number.isFinite(theme.autoStartMs) && Number.isFinite(theme.themeEndMs))
        .filter((theme) => nowMs >= theme.autoStartMs && nowMs <= theme.themeEndMs)
        .sort((a, b) => a.priority - b.priority)[0] || null;
}

async function saveTheme(theme, options = {}) {
    if (!themeControls?.dataset.themeSaveUrl) {
        return { success: true, theme, label: themeLabels[theme] || 'Default' };
    }

    const response = await fetch(themeControls.dataset.themeSaveUrl, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            Accept: 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            theme,
            source: options.source || 'manual',
            window_key: options.windowKey || null,
        }),
    });

    const payload = await response.json();
    if (!response.ok || !payload.success) {
        throw new Error(payload.message || 'Theme save failed.');
    }

    return payload;
}

async function maybeAutoApplyTheme(nowMs) {
    const activeAutoTheme = getActiveAutoTheme(nowMs);
    if (!activeAutoTheme || !activeAutoTheme.windowKey) {
        if (autoAppliedWindowKey && (document.body.dataset.theme || 'default') !== 'default') {
            autoAppliedWindowKey = '';
            try {
                const payload = await saveTheme('default', { source: 'auto' });
                applyTheme(payload.theme || 'default');
                showToast('Default theme auto-applied.');
            } catch (error) {
                showToast(error.message || 'Default theme auto-apply failed.');
            }
        }
        return;
    }
    if (activeAutoTheme.windowKey === autoAppliedWindowKey) return;
    if (activeAutoTheme.windowKey === suppressedAutoWindowKey) return;

    autoAppliedWindowKey = activeAutoTheme.windowKey;
    applyTheme(activeAutoTheme.theme);

    try {
        const payload = await saveTheme(activeAutoTheme.theme, {
            source: 'auto',
            windowKey: activeAutoTheme.windowKey,
        });
        applyTheme(payload.theme || activeAutoTheme.theme);
        showToast(`${payload.label || themeLabels[activeAutoTheme.theme]} theme auto-applied.`);
    } catch (error) {
        applyTheme('default');
        showToast(error.message || 'Theme auto-apply failed.');
    }
}

function updateThemeCountdowns() {
    const nowMs = currentThemeNowMs();

    themeCountdowns.forEach((element) => {
        const theme = countdownMeta(element);
        if (!Number.isFinite(theme.targetMs)) {
            element.textContent = 'Remaining Until Event: --h --m --s';
            return;
        }

        element.textContent = nowMs >= theme.targetMs
            ? 'Remaining Until Event: Now Active'
            : `Remaining Until Event: ${formatRemaining(theme.targetMs - nowMs)}`;
    });

    maybeAutoApplyTheme(nowMs);
}

document.querySelectorAll('.developer-theme-option').forEach((button) => {
    button.addEventListener('click', async () => {
        const selectedTheme = button.dataset.themeOption || 'default';
        const previousTheme = document.body.dataset.theme || 'default';
        const activeAutoTheme = getActiveAutoTheme(currentThemeNowMs());

        applyTheme(selectedTheme);

        if (selectedTheme === 'default' && activeAutoTheme?.windowKey) {
            suppressedAutoWindowKey = activeAutoTheme.windowKey;
        }

        button.disabled = true;
        try {
            const payload = await saveTheme(selectedTheme, {
                source: 'manual',
                windowKey: activeAutoTheme?.windowKey || null,
            });

            applyTheme(payload.theme || selectedTheme);
            showToast(`${payload.label || themeLabels[selectedTheme] || 'Default'} theme saved.`);
        } catch (error) {
            applyTheme(previousTheme);
            showToast(error.message || 'Theme save failed.');
        } finally {
            button.disabled = false;
        }
    });
});

applyTheme(document.body.dataset.theme || 'default');
updateThemeCountdowns();
window.setInterval(updateThemeCountdowns, 1000);

// Archive
const loadArchiveBtn = document.getElementById('loadArchiveBtn');
const archiveTbody = document.getElementById('archive-tbody');
const archiveConfirmModal = document.getElementById('archiveConfirmModal');
const archiveConfirmText = document.getElementById('archiveConfirmText');
const archiveRestoreYesBtn = document.getElementById('archiveRestoreYesBtn');
const archiveRestoreNoBtn = document.getElementById('archiveRestoreNoBtn');
const archiveSuccessModal = document.getElementById('archiveSuccessModal');
const archiveSuccessText = document.getElementById('archiveSuccessText');
const archiveSuccessDoneBtn = document.getElementById('archiveSuccessDoneBtn');

let pendingRestoreId = null;
let pendingRestoreModule = null;
let pendingRestoreRow = null;

async function loadArchives() {
    if (!archiveTbody) return;
    archiveTbody.innerHTML = '<tr><td colspan="4" class="text-center text-sm text-slate-400 py-8">Loading...</td></tr>';
    try {
        const response = await fetch(window.devArchiveRoutes.data, {
            headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
        });
        const payload = await response.json();
        if (!response.ok || !payload.success) throw new Error(payload.message || 'Failed to load archives.');
        const records = payload.items || [];
        if (records.length === 0) {
            archiveTbody.innerHTML = '<tr><td colspan="4" class="text-center text-sm text-slate-400 py-8">No archived records found.</td></tr>';
            return;
        }
        archiveTbody.innerHTML = '';
        records.forEach((rec) => {
            const tr = document.createElement('tr');
            const moduleName = rec.module || 'N/A';
            let restoreBtnHtml = '';
            if (moduleName.toLowerCase() === 'inventory') {
                restoreBtnHtml = '<span class="text-xs text-slate-400 italic">N/A</span>';
            } else {
                restoreBtnHtml = `<button type="button" class="archive-restore-btn text-xs font-bold text-emerald-600 hover:text-emerald-800 transition" data-id="${rec.id}" data-module="${moduleName}" data-name="${rec.data_name || 'Unknown'}">Restore</button>`;
            }
            tr.innerHTML = `
                <td class="px-3 py-2 text-xs font-semibold text-slate-700">${moduleName}</td>
                <td class="px-3 py-2 text-xs text-slate-600">${rec.data_name || 'Unknown'}</td>
                <td class="px-3 py-2 text-xs text-slate-500">${rec.deleted_at || ''}</td>
                <td class="px-3 py-2 text-xs">${restoreBtnHtml}</td>
            `;
            archiveTbody.appendChild(tr);
        });
    } catch (error) {
        archiveTbody.innerHTML = `<tr><td colspan="4" class="text-center text-sm text-rose-500 py-8">${error.message}</td></tr>`;
    }
}

archiveTbody?.addEventListener('click', (e) => {
    const btn = e.target.closest('.archive-restore-btn');
    if (!btn) return;
    pendingRestoreId = btn.dataset.id;
    pendingRestoreModule = btn.dataset.module;
    pendingRestoreRow = btn.closest('tr');
    if (archiveConfirmText) {
        archiveConfirmText.textContent = `Restore "${btn.dataset.name}" (${pendingRestoreModule})?`;
    }
    showModal(archiveConfirmModal);
});

archiveRestoreYesBtn?.addEventListener('click', async () => {
    if (!pendingRestoreId) return;
    const button = archiveRestoreYesBtn;
    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Restoring...';
    try {
        const response = await fetch(window.devArchiveRoutes.restore, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: pendingRestoreId, module: pendingRestoreModule }),
        });
        const payload = await response.json();
        if (!response.ok || !payload.success) throw new Error(payload.message || 'Restore failed.');
        hideModal(archiveConfirmModal);
        if (archiveSuccessText) archiveSuccessText.textContent = payload.message || 'Record restored successfully.';
        showModal(archiveSuccessModal);
        if (pendingRestoreRow) {
            pendingRestoreRow.remove();
            const remaining = archiveTbody?.querySelectorAll('tr').length || 0;
            if (remaining === 0) {
                archiveTbody.innerHTML = '<tr><td colspan="4" class="text-center text-sm text-slate-400 py-8">No archived records found.</td></tr>';
            }
        }
        showToast('Record restored successfully.');
    } catch (error) {
        showToast(error.message || 'Restore failed.');
    } finally {
        button.disabled = false;
        button.textContent = originalText;
        pendingRestoreId = null;
        pendingRestoreModule = null;
        pendingRestoreRow = null;
    }
});

archiveRestoreNoBtn?.addEventListener('click', () => {
    hideModal(archiveConfirmModal);
    pendingRestoreId = null;
    pendingRestoreModule = null;
    pendingRestoreRow = null;
});

archiveSuccessDoneBtn?.addEventListener('click', () => hideModal(archiveSuccessModal));

loadArchiveBtn?.addEventListener('click', loadArchives);

[archiveConfirmModal, archiveSuccessModal].forEach((modal) => {
    modal?.addEventListener('click', (event) => {
        if (event.target === modal) hideModal(modal);
    });
});

// W68_DEVELOPER_ONLINE_REPORT_ROUTE_FIX_20260910
// Never hard-code /hatdog/public here. Laravel supplies the correct URL for
// localhost/subfolder installs and for HostForge/domain-root deployments.
const developerOnlineReportRoutes = window.developerOnlineReportRoutes || {};

function developerOnlineReportRoute(key, fallback) {
    const value = developerOnlineReportRoutes[key];
    return (typeof value === 'string' && value.trim() !== '') ? value : fallback;
}

function developerSyncReportUrl(reportId) {
    const template = developerOnlineReportRoute(
        'syncReportTemplate',
        '/developer/sync-report/__REPORT_ID__'
    );

    return template.replace('__REPORT_ID__', encodeURIComponent(String(reportId)));
}

const orsSyncSuccessModal = document.getElementById('orsSyncSuccessModal');

async function refreshOnlineReportSyncStats() {
    try {
        const res = await fetch(developerOnlineReportRoute('stats', '/developer/online-report-ledger-sync-stats'), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        const result = await res.json();
        if (result.success) {
            document.getElementById('ors-total-reports').textContent = (result.total_reports || 0).toLocaleString();
            document.getElementById('ors-total-items').textContent = (result.total_items || 0).toLocaleString();
            document.getElementById('ors-synced-items').textContent = (result.synced_items || 0).toLocaleString();
            document.getElementById('ors-missing-items').textContent = (result.missing_items || 0).toLocaleString();
            document.getElementById('ors-last-sync').textContent = result.last_sync || '-';
        }
    } catch (err) {
        console.error('Failed to load sync stats:', err);
    }
}

async function loadUnsyncedReports() {
    try {
        const res = await fetch(developerOnlineReportRoute('unsynced', '/developer/unsynced-reports-list'), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        const result = await res.json();
        if (!result.success) return;
        const reports = result.reports || [];
        const container = document.getElementById('ors-unsynced-container');
        const list = document.getElementById('ors-unsynced-list');
        const count = document.getElementById('ors-unsynced-count');
        if (reports.length === 0) {
            container.classList.add('hidden');
            return;
        }
        container.classList.remove('hidden');
        count.textContent = reports.length;
        list.innerHTML = reports.map(r => {
            const reportId = r.report_id ?? r.id;
            const missingItems = Number.isFinite(Number(r.missing_items)) ? Number(r.missing_items) : null;
            const missingLabel = missingItems === null
                ? 'Pending sync'
                : `${missingItems} item${missingItems !== 1 ? 's' : ''} missing`;
            return `<div class="flex items-center justify-between bg-slate-50 rounded-lg px-3 py-2">
                <span class="font-medium text-slate-700">Report #${reportId}</span>
                <span class="text-red-600 font-semibold">${missingLabel}</span>
            </div>`;
        }).join('');
    } catch (err) {
        console.error('Failed to load unsynced reports:', err);
    }
}

async function syncAllUnsyncedReports() {
    const btn = document.getElementById('ors-sync-btn');
    if (btn.disabled) return;
    btn.disabled = true;
    btn.innerHTML = '<span class="animate-spin inline-block h-3.5 w-3.5 border-2 border-amber-800 border-t-transparent rounded-full"></span> Syncing...';

    const progressContainer = document.getElementById('ors-progress-container');
    const progressBar = document.getElementById('ors-progress-bar');
    const progressPercent = document.getElementById('ors-progress-percent');
    const progressLabel = document.getElementById('ors-progress-label');
    const elapsedEl = document.getElementById('ors-elapsed-time');
    const remainingEl = document.getElementById('ors-remaining-time');
    const currentStatus = document.getElementById('ors-current-status');

    try {
        const listRes = await fetch(developerOnlineReportRoute('unsynced', '/developer/unsynced-reports-list'), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        const listResult = await listRes.json();
        if (!listResult.success) { showToast('Failed to fetch unsynced reports'); btn.disabled = false; btn.innerHTML = 'Sync Unsynced'; return; }

        const reports = listResult.reports || [];
        if (reports.length === 0) { showToast('No unsynced reports'); btn.disabled = false; btn.innerHTML = 'Sync Unsynced'; return; }

        progressContainer.classList.remove('hidden');
        const startTime = Date.now();
        let totalSynced = 0;
        let totalErrors = [];

        for (let i = 0; i < reports.length; i++) {
            const report = reports[i];
            const reportId = Number(report.report_id ?? report.id);
            if (!Number.isInteger(reportId) || reportId <= 0) {
                totalErrors.push(`Skipped an unsynced report because its ID is invalid.`);
                continue;
            }
            const pct = Math.round(((i) / reports.length) * 100);
            progressBar.style.width = pct + '%';
            progressPercent.textContent = pct + '%';
            progressLabel.textContent = `Syncing report ${i + 1} of ${reports.length}`;
            currentStatus.textContent = `Report #${reportId}...`;

            const elapsed = Math.floor((Date.now() - startTime) / 1000);
            elapsedEl.textContent = `Elapsed: ${elapsed}s`;

            if (i > 0) {
                const avgPerItem = (Date.now() - startTime) / i;
                const remaining = Math.round((avgPerItem * (reports.length - i)) / 1000);
                remainingEl.textContent = `Remaining: ~${remaining}s`;
            }

            try {
                const syncRes = await fetch(developerSyncReportUrl(reportId), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const syncResult = await syncRes.json();
                if (syncResult.success) {
                    totalSynced += syncResult.items_synced || 0;
                }
                if (syncResult.errors && syncResult.errors.length > 0) {
                    totalErrors = totalErrors.concat(syncResult.errors);
                }
            } catch (e) {
                totalErrors.push(`Report #${reportId}: ${e.message}`);
            }
        }

        progressBar.style.width = '100%';
        progressPercent.textContent = '100%';
        progressLabel.textContent = 'Sync complete';
        currentStatus.textContent = '';

        const totalElapsed = Math.floor((Date.now() - startTime) / 1000);
        elapsedEl.textContent = `Elapsed: ${totalElapsed}s`;
        remainingEl.textContent = 'Remaining: 0s';

        showToast(`Sync completed: ${totalSynced} items synced across ${reports.length} reports`);
        refreshOnlineReportSyncStats();

        const successText = document.getElementById('orsSyncSuccessText');
        successText.innerHTML = `All unsynced reports synced successfully.<br><strong>${totalSynced}</strong> items processed across <strong>${reports.length}</strong> reports.`;
        showModal(orsSyncSuccessModal);
    } catch (err) {
        console.error('Sync error:', err);
        showToast('Sync request failed');
    } finally {
        btn.disabled = false;
        btn.innerHTML = ` <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M23 4v6h-6"></path>
                            <path d="M1 20v-6h6"></path>
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                        </svg>
                        Sync Unsynced`;
        loadUnsyncedReports();
    }
}

orsSyncSuccessModal?.addEventListener('click', (event) => {
    if (event.target === orsSyncSuccessModal) hideModal(orsSyncSuccessModal);
});

refreshOnlineReportSyncStats();
loadUnsyncedReports();

// Developer inventory and product-ledger tools.
const developerInventoryRoutes = window.developerInventoryRoutes || {};

const stockMismatchTableBody = document.getElementById('stockMismatchTableBody');
const stockMismatchSearch = document.getElementById('stockMismatchSearch');
const stockMismatchCount = document.getElementById('stockMismatchCount');
const stockMismatchPageInfo = document.getElementById('stockMismatchPageInfo');
const stockMismatchPrevBtn = document.getElementById('stockMismatchPrevBtn');
const stockMismatchNextBtn = document.getElementById('stockMismatchNextBtn');
const refreshStockMismatchesBtn = document.getElementById('refreshStockMismatchesBtn');
const syncAllStockBtn = document.getElementById('syncAllStockBtn');
const syncAllStockConfirmModal = document.getElementById('syncAllStockConfirmModal');
const syncAllStockConfirmBtn = document.getElementById('syncAllStockConfirmBtn');
const syncAllStockCancelBtn = document.getElementById('syncAllStockCancelBtn');
const stockSyncConfirmModal = document.getElementById('stockSyncConfirmModal');
const stockSyncSuccessModal = document.getElementById('stockSyncSuccessModal');
const stockSyncConfirmText = document.getElementById('stockSyncConfirmText');
const stockSyncSuccessText = document.getElementById('stockSyncSuccessText');
const stockSyncConfirmBtn = document.getElementById('stockSyncConfirmBtn');
const stockSyncCancelBtn = document.getElementById('stockSyncCancelBtn');

const autoSyncStatusDot = document.getElementById('autoSyncStatusDot');
const autoSyncStatusLabel = document.getElementById('autoSyncStatusLabel');
const autoSyncStatusDetails = document.getElementById('autoSyncStatusDetails');
const autoSyncMismatchCount = document.getElementById('autoSyncMismatchCount');
const autoSyncLatestActivity = document.getElementById('autoSyncLatestActivity');

const openProductSelectorBtn = document.getElementById('openProductSelectorBtn');
const productSelectorModal = document.getElementById('productSelectorModal');
const productSelectorTableBody = document.getElementById('productSelectorTableBody');
const productSelectorPageInfo = document.getElementById('productSelectorPageInfo');
const productSelectorPrevBtn = document.getElementById('productSelectorPrevBtn');
const productSelectorNextBtn = document.getElementById('productSelectorNextBtn');
const productFilterCode = document.getElementById('productFilterCode');
const productFilterPartNumber = document.getElementById('productFilterPartNumber');
const productFilterDescription = document.getElementById('productFilterDescription');
const productFilterApplication = document.getElementById('productFilterApplication');
const clearProductFiltersBtn = document.getElementById('clearProductFiltersBtn');
const ledgerProductId = document.getElementById('ledgerProductId');
const selectedProductCode = document.getElementById('selectedProductCode');
const selectedProductDetails = document.getElementById('selectedProductDetails');

const ledgerActionSelect = document.getElementById('ledgerActionSelect');
const selectedProductLedgerBalance = document.getElementById('selectedProductLedgerBalance');
const openLedgerEntryModalBtn = document.getElementById('openLedgerEntryModalBtn');
const productLedgerTableBody = document.getElementById('productLedgerTableBody');
const productLedgerPageInfo = document.getElementById('productLedgerPageInfo');
const productLedgerPrevBtn = document.getElementById('productLedgerPrevBtn');
const productLedgerNextBtn = document.getElementById('productLedgerNextBtn');
const ledgerQuantityHeading = document.getElementById('ledgerQuantityHeading');
const ledgerPriceHeading = document.getElementById('ledgerPriceHeading');
const ledgerEntryModal = document.getElementById('ledgerEntryModal');
const ledgerEntryForm = document.getElementById('ledgerEntryForm');
const ledgerEntryModalTitle = document.getElementById('ledgerEntryModalTitle');
const ledgerEntryProductText = document.getElementById('ledgerEntryProductText');
const ledgerEntryId = document.getElementById('ledgerEntryId');
const ledgerEntryIdDisplay = document.getElementById('ledgerEntryIdDisplay');
const ledgerTransactionType = document.getElementById('ledgerTransactionType');
const ledgerEntryDate = document.getElementById('ledgerEntryDate');
const ledgerEntryCreatedAt = document.getElementById('ledgerEntryCreatedAt');
const ledgerEntityName = document.getElementById('ledgerEntityName');
const ledgerTransactionNumber = document.getElementById('ledgerTransactionNumber');
const ledgerReferenceNumber = document.getElementById('ledgerReferenceNumber');
const ledgerQuantity = document.getElementById('ledgerQuantity');
const ledgerQuantityLabel = document.getElementById('ledgerQuantityLabel');
const ledgerBalanceStock = document.getElementById('ledgerBalanceStock');
const ledgerOum = document.getElementById('ledgerOum');
const ledgerPrice = document.getElementById('ledgerPrice');
const ledgerPriceLabel = document.getElementById('ledgerPriceLabel');
const saveLedgerEntryBtn = document.getElementById('saveLedgerEntryBtn');
const ledgerDeleteConfirmModal = document.getElementById('ledgerDeleteConfirmModal');
const ledgerDeleteConfirmText = document.getElementById('ledgerDeleteConfirmText');
const ledgerDeleteConfirmBtn = document.getElementById('ledgerDeleteConfirmBtn');
const ledgerDeleteCancelBtn = document.getElementById('ledgerDeleteCancelBtn');

const purchaseLedgerTableBody = document.getElementById('purchaseLedgerTableBody');
const purchaseLedgerMissingCount = document.getElementById('purchaseLedgerMissingCount');
const purchaseLedgerPageInfo = document.getElementById('purchaseLedgerPageInfo');
const purchaseLedgerPrevBtn = document.getElementById('purchaseLedgerPrevBtn');
const purchaseLedgerNextBtn = document.getElementById('purchaseLedgerNextBtn');
const refreshPurchaseLedgerBtn = document.getElementById('refreshPurchaseLedgerBtn');
const syncAllPurchaseLedgerBtn = document.getElementById('syncAllPurchaseLedgerBtn');
const syncAllPurchaseLedgerConfirmModal = document.getElementById('syncAllPurchaseLedgerConfirmModal');
const syncAllPurchaseLedgerConfirmBtn = document.getElementById('syncAllPurchaseLedgerConfirmBtn');
const syncAllPurchaseLedgerCancelBtn = document.getElementById('syncAllPurchaseLedgerCancelBtn');
const purchaseLedgerSyncSuccessModal = document.getElementById('purchaseLedgerSyncSuccessModal');
const purchaseLedgerSyncSuccessText = document.getElementById('purchaseLedgerSyncSuccessText');
const purchaseLedgerFilterSupplier = document.getElementById('purchaseLedgerFilterSupplier');
const purchaseLedgerFilterInvoice = document.getElementById('purchaseLedgerFilterInvoice');
const purchaseLedgerFilterProductCode = document.getElementById('purchaseLedgerFilterProductCode');
const purchaseLedgerFilterPartNumber = document.getElementById('purchaseLedgerFilterPartNumber');
const purchaseLedgerFilterUnit = document.getElementById('purchaseLedgerFilterUnit');
const purchaseLedgerFilterCost = document.getElementById('purchaseLedgerFilterCost');
const purchaseLedgerFilterDate = document.getElementById('purchaseLedgerFilterDate');
const purchaseReturnLedgerTableBody = document.getElementById('purchaseReturnLedgerTableBody');
const purchaseReturnLedgerMissingCount = document.getElementById('purchaseReturnLedgerMissingCount');
const purchaseReturnLedgerPageInfo = document.getElementById('purchaseReturnLedgerPageInfo');
const purchaseReturnLedgerPrevBtn = document.getElementById('purchaseReturnLedgerPrevBtn');
const purchaseReturnLedgerNextBtn = document.getElementById('purchaseReturnLedgerNextBtn');
const purchaseReturnSyncDateFrom = document.getElementById('purchaseReturnSyncDateFrom');
const purchaseReturnSyncDateTo = document.getElementById('purchaseReturnSyncDateTo');
const purchaseReturnSyncMode = document.getElementById('purchaseReturnSyncMode');
const refreshPurchaseReturnLedgerBtn = document.getElementById('refreshPurchaseReturnLedgerBtn');
const syncAllPurchaseReturnLedgerBtn = document.getElementById('syncAllPurchaseReturnLedgerBtn');
const syncAllPurchaseReturnLedgerConfirmModal = document.getElementById('syncAllPurchaseReturnLedgerConfirmModal');
const syncAllPurchaseReturnLedgerConfirmText = document.getElementById('syncAllPurchaseReturnLedgerConfirmText');
const syncAllPurchaseReturnLedgerConfirmBtn = document.getElementById('syncAllPurchaseReturnLedgerConfirmBtn');
const syncAllPurchaseReturnLedgerCancelBtn = document.getElementById('syncAllPurchaseReturnLedgerCancelBtn');
const purchaseReturnLedgerSyncSuccessModal = document.getElementById('purchaseReturnLedgerSyncSuccessModal');
const purchaseReturnLedgerSyncSuccessText = document.getElementById('purchaseReturnLedgerSyncSuccessText');

const stockMismatchState = { page: 1, lastPage: 1, pending: null };
const productSelectorState = { page: 1, lastPage: 1, products: new Map() };
const productLedgerState = {
    page: 1,
    lastPage: 1,
    selectedProduct: null,
    entries: new Map(),
    pendingDelete: null,
    latestBalance: 0,
};
const purchaseLedgerState = { page: 1, lastPage: 1, total: 0 };
const purchaseReturnLedgerState = { page: 1, lastPage: 1, total: 0 };
let stockMismatchSearchTimer = null;
let productSelectorFilterTimer = null;
let purchaseLedgerFilterTimer = null;

function escapeDeveloperHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

async function developerFetchJson(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        headers: {
            Accept: 'application/json',
            ...(options.body ? { 'Content-Type': 'application/json' } : {}),
            ...(options.method && options.method !== 'GET' ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            ...(options.headers || {}),
        },
    });

    const text = await response.text();
    let payload = {};
    try {
        payload = text ? JSON.parse(text) : {};
    } catch (error) {
        throw new Error(`Server returned HTTP ${response.status} instead of JSON.`);
    }

    if (!response.ok || payload.success === false) {
        throw new Error(payload.message || `Request failed with HTTP ${response.status}.`);
    }

    return payload;
}

function purchaseLedgerFilters() {
    return {
        supplier_name: purchaseLedgerFilterSupplier?.value?.trim() || '',
        supplier_invoice: purchaseLedgerFilterInvoice?.value?.trim() || '',
        product_code: purchaseLedgerFilterProductCode?.value?.trim() || '',
        part_number: purchaseLedgerFilterPartNumber?.value?.trim() || '',
        unit: purchaseLedgerFilterUnit?.value?.trim() || '',
        cost: purchaseLedgerFilterCost?.value?.trim() || '',
        date: purchaseLedgerFilterDate?.value?.trim() || '',
    };
}

async function loadPurchaseLedgerMismatches(page = 1) {
    if (!purchaseLedgerTableBody || !developerInventoryRoutes.purchaseLedgerMismatches) return;

    purchaseLedgerTableBody.innerHTML = '<tr><td colspan="8" class="developer-empty-cell">Loading missing Purchase Ledger rows...</td></tr>';
    const params = new URLSearchParams({ page: String(page), ...purchaseLedgerFilters() });

    try {
        const payload = await developerFetchJson(`${developerInventoryRoutes.purchaseLedgerMismatches}?${params}`);
        const rows = payload.data || [];
        const pagination = payload.pagination || {};
        purchaseLedgerState.page = Number(pagination.current_page || 1);
        purchaseLedgerState.lastPage = Number(pagination.last_page || 1);
        purchaseLedgerState.total = Number(pagination.total || 0);

        if (purchaseLedgerMissingCount) purchaseLedgerMissingCount.textContent = purchaseLedgerState.total.toLocaleString();
        if (purchaseLedgerPageInfo) purchaseLedgerPageInfo.textContent = `Page ${purchaseLedgerState.page} of ${purchaseLedgerState.lastPage} · ${purchaseLedgerState.total.toLocaleString()} missing · 50 per page`;
        if (purchaseLedgerPrevBtn) purchaseLedgerPrevBtn.disabled = purchaseLedgerState.page <= 1;
        if (purchaseLedgerNextBtn) purchaseLedgerNextBtn.disabled = purchaseLedgerState.page >= purchaseLedgerState.lastPage;

        if (rows.length === 0) {
            purchaseLedgerTableBody.innerHTML = '<tr><td colspan="8" class="developer-empty-cell">No missing Purchase Order supplier-ledger items match these filters.</td></tr>';
            return;
        }

        purchaseLedgerTableBody.innerHTML = rows.map((row) => `<tr>
            <td><button type="button" class="developer-row-action purchase-ledger-sync-btn" data-purchase-order-id="${Number(row.purchase_order_id)}" data-po-number="${escapeDeveloperHtml(row.po_number)}">Sync</button></td>
            <td class="font-bold text-slate-900">${escapeDeveloperHtml(row.supplier_name)}</td>
            <td title="PO ${escapeDeveloperHtml(row.po_number)}">${escapeDeveloperHtml(row.supplier_invoice || '—')}</td>
            <td>${escapeDeveloperHtml(row.product_code)}</td>
            <td>${escapeDeveloperHtml(row.part_number || '—')}</td>
            <td>${escapeDeveloperHtml(row.unit || '—')}</td>
            <td class="text-right font-bold">${Number(row.cost || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            <td>${escapeDeveloperHtml(row.date)}</td>
        </tr>`).join('');
    } catch (error) {
        purchaseLedgerTableBody.innerHTML = `<tr><td colspan="8" class="developer-empty-cell text-rose-600">${escapeDeveloperHtml(error.message)}</td></tr>`;
    }
}

async function syncPurchaseLedgerPurchaseOrder(button) {
    if (!button || !developerInventoryRoutes.purchaseLedgerSyncBase) return;
    const purchaseOrderId = Number(button.dataset.purchaseOrderId || 0);
    if (!Number.isInteger(purchaseOrderId) || purchaseOrderId <= 0) {
        showToast('Invalid Purchase Order ID.');
        return;
    }

    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Syncing...';

    try {
        const payload = await developerFetchJson(`${developerInventoryRoutes.purchaseLedgerSyncBase}/${purchaseOrderId}/sync`, {
            method: 'POST',
            body: JSON.stringify({}),
        });
        showToast(payload.message || `Purchase Order ${button.dataset.poNumber || purchaseOrderId} synchronized.`);
        const nextPage = purchaseLedgerState.page > 1 && purchaseLedgerState.total <= ((purchaseLedgerState.page - 1) * 50 + 1)
            ? purchaseLedgerState.page - 1
            : purchaseLedgerState.page;
        await loadPurchaseLedgerMismatches(nextPage);
    } catch (error) {
        showToast(error.message || 'Purchase Ledger synchronization failed.');
    } finally {
        button.disabled = false;
        button.textContent = originalText;
    }
}

function formatPurchaseReturnDate(value) {
    const parts = String(value || '').split('-');
    if (parts.length !== 3) return String(value || '—');
    return `${parts[2]}-${parts[1]}-${parts[0]}`;
}

function purchaseReturnSyncRange(showError = true) {
    const dateFrom = purchaseReturnSyncDateFrom?.value || '';
    const dateTo = purchaseReturnSyncDateTo?.value || '';

    if (!dateFrom || !dateTo) {
        if (showError) showToast('Choose both From Date and To Date for the Purchase Return synchronizer.');
        return null;
    }
    if (dateFrom > dateTo) {
        if (showError) showToast('From Date cannot be later than To Date.');
        return null;
    }

    return { date_from: dateFrom, date_to: dateTo };
}

async function loadPurchaseReturnLedgerMismatches(page = 1) {
    if (!purchaseReturnLedgerTableBody || !developerInventoryRoutes.purchaseReturnLedgerMismatches) return;
    const range = purchaseReturnSyncRange(false);
    if (!range) {
        purchaseReturnLedgerTableBody.innerHTML = '<tr><td colspan="8" class="developer-empty-cell">Choose both From Date and To Date to load missing Purchase Returns.</td></tr>';
        if (purchaseReturnLedgerMissingCount) purchaseReturnLedgerMissingCount.textContent = '0';
        return;
    }

    purchaseReturnLedgerTableBody.innerHTML = '<tr><td colspan="8" class="developer-empty-cell">Loading missing Purchase Returns...</td></tr>';
    const params = new URLSearchParams({ ...range, page: String(page), per_page: '50' });

    try {
        const payload = await developerFetchJson(`${developerInventoryRoutes.purchaseReturnLedgerMismatches}?${params}`);
        const rows = payload.data || [];
        const pagination = payload.pagination || {};
        purchaseReturnLedgerState.page = Number(pagination.current_page || 1);
        purchaseReturnLedgerState.lastPage = Number(pagination.last_page || 1);
        purchaseReturnLedgerState.total = Number(pagination.total || 0);

        if (purchaseReturnLedgerMissingCount) purchaseReturnLedgerMissingCount.textContent = purchaseReturnLedgerState.total.toLocaleString();
        if (purchaseReturnLedgerPageInfo) purchaseReturnLedgerPageInfo.textContent = `Page ${purchaseReturnLedgerState.page} of ${purchaseReturnLedgerState.lastPage} · ${purchaseReturnLedgerState.total.toLocaleString()} missing · 50 per page`;
        if (purchaseReturnLedgerPrevBtn) purchaseReturnLedgerPrevBtn.disabled = purchaseReturnLedgerState.page <= 1;
        if (purchaseReturnLedgerNextBtn) purchaseReturnLedgerNextBtn.disabled = purchaseReturnLedgerState.page >= purchaseReturnLedgerState.lastPage;
        if (syncAllPurchaseReturnLedgerBtn) syncAllPurchaseReturnLedgerBtn.disabled = purchaseReturnLedgerState.total <= 0;

        if (rows.length === 0) {
            purchaseReturnLedgerTableBody.innerHTML = '<tr><td colspan="8" class="developer-empty-cell">No missing Purchase Return Product Ledger movements exist in this date range.</td></tr>';
            return;
        }

        purchaseReturnLedgerTableBody.innerHTML = rows.map((row) => `<tr>
            <td><button type="button" class="developer-row-action purchase-return-ledger-sync-btn" data-purchase-return-item-id="${Number(row.purchase_return_item_id)}" data-return-number="${escapeDeveloperHtml(row.return_number)}">Sync</button></td>
            <td class="font-bold text-slate-900">${escapeDeveloperHtml(row.return_number)}</td>
            <td>${escapeDeveloperHtml(row.supplier_name || '—')}</td>
            <td>${escapeDeveloperHtml(row.product_code || '—')}</td>
            <td>${escapeDeveloperHtml(row.part_number || '—')}</td>
            <td class="text-right font-bold">${Number(row.quantity || 0).toLocaleString()}</td>
            <td>${escapeDeveloperHtml(row.oum || '—')}</td>
            <td class="font-bold">${escapeDeveloperHtml(row.return_date_display || formatPurchaseReturnDate(row.return_date))}</td>
        </tr>`).join('');
    } catch (error) {
        purchaseReturnLedgerTableBody.innerHTML = `<tr><td colspan="8" class="developer-empty-cell text-rose-600">${escapeDeveloperHtml(error.message)}</td></tr>`;
        if (syncAllPurchaseReturnLedgerBtn) syncAllPurchaseReturnLedgerBtn.disabled = true;
    }
}

async function syncPurchaseReturnLedgerItem(button) {
    if (!button || !developerInventoryRoutes.purchaseReturnLedgerSyncBase) return;
    const itemId = Number(button.dataset.purchaseReturnItemId || 0);
    const mode = String(purchaseReturnSyncMode?.value || 'OUT').toUpperCase();
    if (!Number.isInteger(itemId) || itemId <= 0) {
        showToast('Invalid Purchase Return item ID.');
        return;
    }

    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = `Sync ${mode}...`;

    try {
        const payload = await developerFetchJson(`${developerInventoryRoutes.purchaseReturnLedgerSyncBase}/${itemId}/sync`, {
            method: 'POST',
            body: JSON.stringify({ mode }),
        });
        showToast(payload.message || `${button.dataset.returnNumber || 'Purchase Return'} synchronized as ${mode}.`);
        const nextPage = purchaseReturnLedgerState.page > 1 && purchaseReturnLedgerState.total <= ((purchaseReturnLedgerState.page - 1) * 50 + 1)
            ? purchaseReturnLedgerState.page - 1
            : purchaseReturnLedgerState.page;
        await Promise.all([
            loadPurchaseReturnLedgerMismatches(nextPage),
            loadStockMismatches(stockMismatchState.page),
            loadAutoSyncStatus(),
        ]);
        if (productLedgerState.selectedProduct) await loadProductLedgerEntries(productLedgerState.page);
    } catch (error) {
        showToast(error.message || 'Purchase Return synchronization failed.');
    } finally {
        button.disabled = false;
        button.textContent = originalText;
    }
}

function formatDeveloperDateTime(value) {
    if (!value) return '—';
    const date = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString();
}

async function loadAutoSyncStatus() {
    if (!developerInventoryRoutes.autoSyncStatus) return;

    try {
        const payload = await developerFetchJson(developerInventoryRoutes.autoSyncStatus);
        autoSyncStatusDot?.classList.toggle('is-active', Boolean(payload.active));
        autoSyncStatusDot?.classList.toggle('is-error', !payload.active);
        if (autoSyncStatusLabel) autoSyncStatusLabel.textContent = payload.label || 'Auto sync status unavailable';
        if (autoSyncStatusDetails) autoSyncStatusDetails.textContent = payload.details || '';
        if (autoSyncMismatchCount) autoSyncMismatchCount.textContent = Number(payload.mismatch_count || 0).toLocaleString();
        if (autoSyncLatestActivity) autoSyncLatestActivity.textContent = formatDeveloperDateTime(payload.latest_ledger_activity);
    } catch (error) {
        autoSyncStatusDot?.classList.remove('is-active');
        autoSyncStatusDot?.classList.add('is-error');
        if (autoSyncStatusLabel) autoSyncStatusLabel.textContent = 'Unable to verify auto sync';
        if (autoSyncStatusDetails) autoSyncStatusDetails.textContent = error.message;
    }
}

async function loadStockMismatches(page = 1) {
    if (!stockMismatchTableBody || !developerInventoryRoutes.mismatches) return;

    stockMismatchTableBody.innerHTML = '<tr><td colspan="5" class="developer-empty-cell">Loading stock mismatches...</td></tr>';
    const params = new URLSearchParams({
        page: String(page),
        per_page: '25',
        search: stockMismatchSearch?.value?.trim() || '',
    });

    try {
        const payload = await developerFetchJson(`${developerInventoryRoutes.mismatches}?${params}`);
        const rows = payload.data || [];
        const pagination = payload.pagination || {};
        stockMismatchState.page = Number(pagination.current_page || 1);
        stockMismatchState.lastPage = Number(pagination.last_page || 1);

        if (stockMismatchCount) stockMismatchCount.textContent = Number(pagination.total || 0).toLocaleString();
        if (autoSyncMismatchCount) autoSyncMismatchCount.textContent = Number(pagination.total || 0).toLocaleString();
        if (stockMismatchPageInfo) stockMismatchPageInfo.textContent = `Page ${stockMismatchState.page} of ${stockMismatchState.lastPage} · ${Number(pagination.total || 0).toLocaleString()} mismatches`;
        if (stockMismatchPrevBtn) stockMismatchPrevBtn.disabled = stockMismatchState.page <= 1;
        if (stockMismatchNextBtn) stockMismatchNextBtn.disabled = stockMismatchState.page >= stockMismatchState.lastPage;

        if (rows.length === 0) {
            stockMismatchTableBody.innerHTML = '<tr><td colspan="5" class="developer-empty-cell">All product inventory values match the latest ledger balance.</td></tr>';
            return;
        }

        stockMismatchTableBody.innerHTML = rows.map((row) => {
            const difference = Number(row.difference || 0);
            const differenceText = `${difference > 0 ? '+' : ''}${difference.toLocaleString()}`;
            return `<tr>
                <td class="font-bold text-slate-900">${escapeDeveloperHtml(row.item_code)}</td>
                <td>${escapeDeveloperHtml(row.part_number)}</td>
                <td class="text-right">${Number(row.product_inventory || 0).toLocaleString()}</td>
                <td class="text-right"><strong>${Number(row.ledger_inventory || 0).toLocaleString()}</strong><div class="text-[10px] ${difference >= 0 ? 'text-emerald-600' : 'text-rose-600'}">${differenceText}</div></td>
                <td><button type="button" class="developer-row-action stock-mismatch-sync-btn" data-product-id="${Number(row.id)}" data-item-code="${escapeDeveloperHtml(row.item_code)}" data-product-inventory="${Number(row.product_inventory || 0)}" data-ledger-inventory="${Number(row.ledger_inventory || 0)}">Manual Sync</button></td>
            </tr>`;
        }).join('');
    } catch (error) {
        stockMismatchTableBody.innerHTML = `<tr><td colspan="5" class="developer-empty-cell text-rose-600">${escapeDeveloperHtml(error.message)}</td></tr>`;
    }
}

stockMismatchTableBody?.addEventListener('click', (event) => {
    const button = event.target.closest('.stock-mismatch-sync-btn');
    if (!button) return;

    stockMismatchState.pending = {
        productId: Number(button.dataset.productId),
        itemCode: button.dataset.itemCode || '',
        productInventory: Number(button.dataset.productInventory || 0),
        ledgerInventory: Number(button.dataset.ledgerInventory || 0),
    };

    if (stockSyncConfirmText) {
        stockSyncConfirmText.textContent = `Sync ${stockMismatchState.pending.itemCode} from ${stockMismatchState.pending.productInventory.toLocaleString()} to ledger balance ${stockMismatchState.pending.ledgerInventory.toLocaleString()}?`;
    }
    showModal(stockSyncConfirmModal);
});

stockSyncCancelBtn?.addEventListener('click', () => {
    stockMismatchState.pending = null;
    hideModal(stockSyncConfirmModal);
});

stockSyncConfirmBtn?.addEventListener('click', async () => {
    const pending = stockMismatchState.pending;
    if (!pending || !developerInventoryRoutes.syncMismatchBase) return;

    const originalText = stockSyncConfirmBtn.textContent;
    stockSyncConfirmBtn.disabled = true;
    stockSyncConfirmBtn.textContent = 'Syncing...';

    try {
        const payload = await developerFetchJson(`${developerInventoryRoutes.syncMismatchBase}/${pending.productId}/sync`, {
            method: 'POST',
            body: JSON.stringify({}),
        });
        hideModal(stockSyncConfirmModal);
        if (stockSyncSuccessText) stockSyncSuccessText.textContent = payload.message || `${pending.itemCode} synchronized successfully.`;
        showModal(stockSyncSuccessModal);
        await Promise.all([loadStockMismatches(stockMismatchState.page), loadAutoSyncStatus()]);
        if (productLedgerState.selectedProduct?.id === pending.productId) {
            productLedgerState.latestBalance = Number(payload.ledger_inventory || 0);
            if (selectedProductLedgerBalance) selectedProductLedgerBalance.textContent = productLedgerState.latestBalance.toLocaleString();
        }
    } catch (error) {
        showToast(error.message || 'Manual stock synchronization failed.');
    } finally {
        stockMismatchState.pending = null;
        stockSyncConfirmBtn.disabled = false;
        stockSyncConfirmBtn.textContent = originalText;
    }
});

syncAllStockBtn?.addEventListener('click', () => showModal(syncAllStockConfirmModal));
syncAllStockCancelBtn?.addEventListener('click', () => hideModal(syncAllStockConfirmModal));
syncAllStockConfirmBtn?.addEventListener('click', async () => {
    if (!developerInventoryRoutes.syncAll) return;
    const originalText = syncAllStockConfirmBtn.textContent;
    syncAllStockConfirmBtn.disabled = true;
    syncAllStockConfirmBtn.textContent = 'Syncing All...';

    try {
        const payload = await developerFetchJson(developerInventoryRoutes.syncAll, {
            method: 'POST',
            body: JSON.stringify({}),
        });
        hideModal(syncAllStockConfirmModal);
        if (stockSyncSuccessText) stockSyncSuccessText.textContent = payload.message || 'All products synchronized successfully.';
        showModal(stockSyncSuccessModal);
        await Promise.all([loadStockMismatches(1), loadAutoSyncStatus()]);
        if (productLedgerState.selectedProduct) await loadProductLedgerEntries(productLedgerState.page);
    } catch (error) {
        showToast(error.message || 'Sync All failed.');
    } finally {
        syncAllStockConfirmBtn.disabled = false;
        syncAllStockConfirmBtn.textContent = originalText;
    }
});

purchaseLedgerTableBody?.addEventListener('click', (event) => {
    const button = event.target.closest('.purchase-ledger-sync-btn');
    if (button) syncPurchaseLedgerPurchaseOrder(button);
});
syncAllPurchaseLedgerBtn?.addEventListener('click', () => {
    if (purchaseLedgerState.total <= 0) {
        showToast('Purchase Ledger is already synchronized.');
        return;
    }
    showModal(syncAllPurchaseLedgerConfirmModal);
});
syncAllPurchaseLedgerCancelBtn?.addEventListener('click', () => hideModal(syncAllPurchaseLedgerConfirmModal));
syncAllPurchaseLedgerConfirmBtn?.addEventListener('click', async () => {
    if (!developerInventoryRoutes.purchaseLedgerSyncAll) return;
    const originalText = syncAllPurchaseLedgerConfirmBtn.textContent;
    syncAllPurchaseLedgerConfirmBtn.disabled = true;
    syncAllPurchaseLedgerConfirmBtn.textContent = 'Syncing All...';
    if (syncAllPurchaseLedgerBtn) syncAllPurchaseLedgerBtn.disabled = true;

    try {
        const payload = await developerFetchJson(developerInventoryRoutes.purchaseLedgerSyncAll, {
            method: 'POST',
            body: JSON.stringify({}),
        });
        hideModal(syncAllPurchaseLedgerConfirmModal);
        if (purchaseLedgerSyncSuccessText) {
            purchaseLedgerSyncSuccessText.textContent = payload.message || 'All missing Purchase Ledger rows synchronized.';
        }
        showModal(purchaseLedgerSyncSuccessModal);
        await loadPurchaseLedgerMismatches(1);
    } catch (error) {
        showToast(error.message || 'Purchase Ledger Sync All failed.');
    } finally {
        syncAllPurchaseLedgerConfirmBtn.disabled = false;
        syncAllPurchaseLedgerConfirmBtn.textContent = originalText;
        if (syncAllPurchaseLedgerBtn) syncAllPurchaseLedgerBtn.disabled = false;
    }
});
refreshPurchaseLedgerBtn?.addEventListener('click', () => loadPurchaseLedgerMismatches(1));
purchaseLedgerPrevBtn?.addEventListener('click', () => loadPurchaseLedgerMismatches(Math.max(1, purchaseLedgerState.page - 1)));
purchaseLedgerNextBtn?.addEventListener('click', () => loadPurchaseLedgerMismatches(Math.min(purchaseLedgerState.lastPage, purchaseLedgerState.page + 1)));
[
    purchaseLedgerFilterSupplier,
    purchaseLedgerFilterInvoice,
    purchaseLedgerFilterProductCode,
    purchaseLedgerFilterPartNumber,
    purchaseLedgerFilterUnit,
    purchaseLedgerFilterCost,
    purchaseLedgerFilterDate,
].forEach((input) => {
    input?.addEventListener('input', () => {
        window.clearTimeout(purchaseLedgerFilterTimer);
        purchaseLedgerFilterTimer = window.setTimeout(() => loadPurchaseLedgerMismatches(1), 300);
    });
});

purchaseReturnLedgerTableBody?.addEventListener('click', (event) => {
    const button = event.target.closest('.purchase-return-ledger-sync-btn');
    if (button) syncPurchaseReturnLedgerItem(button);
});

refreshPurchaseReturnLedgerBtn?.addEventListener('click', () => loadPurchaseReturnLedgerMismatches(1));
purchaseReturnLedgerPrevBtn?.addEventListener('click', () => loadPurchaseReturnLedgerMismatches(Math.max(1, purchaseReturnLedgerState.page - 1)));
purchaseReturnLedgerNextBtn?.addEventListener('click', () => loadPurchaseReturnLedgerMismatches(Math.min(purchaseReturnLedgerState.lastPage, purchaseReturnLedgerState.page + 1)));
purchaseReturnSyncDateFrom?.addEventListener('change', () => loadPurchaseReturnLedgerMismatches(1));
purchaseReturnSyncDateTo?.addEventListener('change', () => loadPurchaseReturnLedgerMismatches(1));

syncAllPurchaseReturnLedgerBtn?.addEventListener('click', () => {
    const range = purchaseReturnSyncRange(true);
    if (!range) return;
    if (purchaseReturnLedgerState.total <= 0) {
        showToast('No missing Purchase Returns exist in this date range.');
        return;
    }

    const mode = String(purchaseReturnSyncMode?.value || 'OUT').toUpperCase();
    if (syncAllPurchaseReturnLedgerConfirmText) {
        syncAllPurchaseReturnLedgerConfirmText.textContent = `Synchronize all ${purchaseReturnLedgerState.total.toLocaleString()} currently missing Purchase Return item(s) from ${formatPurchaseReturnDate(range.date_from)} to ${formatPurchaseReturnDate(range.date_to)} as ${mode}? ${mode === 'OUT' ? 'OUT will deduct Return QTY from Balance Stock.' : 'JUNK will not change Balance Stock.'}`;
    }
    showModal(syncAllPurchaseReturnLedgerConfirmModal);
});

syncAllPurchaseReturnLedgerCancelBtn?.addEventListener('click', () => hideModal(syncAllPurchaseReturnLedgerConfirmModal));
syncAllPurchaseReturnLedgerConfirmBtn?.addEventListener('click', async () => {
    if (!developerInventoryRoutes.purchaseReturnLedgerSyncAll) return;
    const range = purchaseReturnSyncRange(true);
    if (!range) return;
    const mode = String(purchaseReturnSyncMode?.value || 'OUT').toUpperCase();
    const originalText = syncAllPurchaseReturnLedgerConfirmBtn.textContent;
    syncAllPurchaseReturnLedgerConfirmBtn.disabled = true;
    syncAllPurchaseReturnLedgerConfirmBtn.textContent = `Syncing as ${mode}...`;
    if (syncAllPurchaseReturnLedgerBtn) syncAllPurchaseReturnLedgerBtn.disabled = true;

    try {
        const payload = await developerFetchJson(developerInventoryRoutes.purchaseReturnLedgerSyncAll, {
            method: 'POST',
            body: JSON.stringify({ ...range, mode }),
        });
        hideModal(syncAllPurchaseReturnLedgerConfirmModal);
        if (purchaseReturnLedgerSyncSuccessText) {
            purchaseReturnLedgerSyncSuccessText.textContent = payload.message || 'Missing Purchase Returns synchronized successfully.';
        }
        showModal(purchaseReturnLedgerSyncSuccessModal);
        await Promise.all([
            loadPurchaseReturnLedgerMismatches(1),
            loadStockMismatches(1),
            loadAutoSyncStatus(),
        ]);
        if (productLedgerState.selectedProduct) await loadProductLedgerEntries(productLedgerState.page);
    } catch (error) {
        showToast(error.message || 'Purchase Return Sync All failed.');
    } finally {
        syncAllPurchaseReturnLedgerConfirmBtn.disabled = false;
        syncAllPurchaseReturnLedgerConfirmBtn.textContent = originalText;
        if (syncAllPurchaseReturnLedgerBtn) syncAllPurchaseReturnLedgerBtn.disabled = purchaseReturnLedgerState.total <= 0;
    }
});

refreshStockMismatchesBtn?.addEventListener('click', () => Promise.all([loadStockMismatches(1), loadAutoSyncStatus()]));
stockMismatchPrevBtn?.addEventListener('click', () => loadStockMismatches(Math.max(1, stockMismatchState.page - 1)));
stockMismatchNextBtn?.addEventListener('click', () => loadStockMismatches(Math.min(stockMismatchState.lastPage, stockMismatchState.page + 1)));
stockMismatchSearch?.addEventListener('input', () => {
    window.clearTimeout(stockMismatchSearchTimer);
    stockMismatchSearchTimer = window.setTimeout(() => loadStockMismatches(1), 300);
});

function productSelectorFilters() {
    return {
        product_code: productFilterCode?.value?.trim() || '',
        part_number: productFilterPartNumber?.value?.trim() || '',
        description: productFilterDescription?.value?.trim() || '',
        application: productFilterApplication?.value?.trim() || '',
    };
}

async function loadProductSelector(page = 1) {
    if (!productSelectorTableBody || !developerInventoryRoutes.products) return;
    productSelectorTableBody.innerHTML = '<tr><td colspan="5" class="developer-empty-cell">Loading products...</td></tr>';

    const params = new URLSearchParams({ page: String(page), ...productSelectorFilters() });
    try {
        const payload = await developerFetchJson(`${developerInventoryRoutes.products}?${params}`);
        const products = payload.products || [];
        const pagination = payload.pagination || {};
        productSelectorState.page = Number(pagination.current_page || 1);
        productSelectorState.lastPage = Number(pagination.last_page || 1);
        productSelectorState.products.clear();
        products.forEach((product) => productSelectorState.products.set(Number(product.id), product));

        if (productSelectorPageInfo) productSelectorPageInfo.textContent = `Page ${productSelectorState.page} of ${productSelectorState.lastPage} · ${Number(pagination.total || 0).toLocaleString()} products`;
        if (productSelectorPrevBtn) productSelectorPrevBtn.disabled = productSelectorState.page <= 1;
        if (productSelectorNextBtn) productSelectorNextBtn.disabled = productSelectorState.page >= productSelectorState.lastPage;

        if (products.length === 0) {
            productSelectorTableBody.innerHTML = '<tr><td colspan="5" class="developer-empty-cell">No products match these column filters.</td></tr>';
            return;
        }

        productSelectorTableBody.innerHTML = products.map((product) => `<tr>
            <td class="font-bold text-slate-900">${escapeDeveloperHtml(product.product_code)}</td>
            <td>${escapeDeveloperHtml(product.part_number)}</td>
            <td>${escapeDeveloperHtml(product.description)}</td>
            <td>${escapeDeveloperHtml(product.application)}</td>
            <td><button type="button" class="developer-row-action product-selector-select-btn" data-product-id="${Number(product.id)}">Select</button></td>
        </tr>`).join('');
    } catch (error) {
        productSelectorTableBody.innerHTML = `<tr><td colspan="5" class="developer-empty-cell text-rose-600">${escapeDeveloperHtml(error.message)}</td></tr>`;
    }
}

function selectLedgerProduct(product) {
    productLedgerState.selectedProduct = product;
    productLedgerState.latestBalance = Number(product.ledger_inventory || 0);
    if (ledgerProductId) ledgerProductId.value = String(product.id);
    if (selectedProductCode) selectedProductCode.textContent = product.product_code || `Product #${product.id}`;
    if (selectedProductDetails) selectedProductDetails.textContent = `${product.part_number || 'No part number'} · ${product.description || 'No description'} · ${product.application || 'No application'}`;
    if (selectedProductLedgerBalance) selectedProductLedgerBalance.textContent = productLedgerState.latestBalance.toLocaleString();
    if (openLedgerEntryModalBtn) openLedgerEntryModalBtn.disabled = false;
    hideModal(productSelectorModal);
    loadProductLedgerEntries(1);
}

openProductSelectorBtn?.addEventListener('click', () => {
    showModal(productSelectorModal);
    loadProductSelector(1);
});
productSelectorPrevBtn?.addEventListener('click', () => loadProductSelector(Math.max(1, productSelectorState.page - 1)));
productSelectorNextBtn?.addEventListener('click', () => loadProductSelector(Math.min(productSelectorState.lastPage, productSelectorState.page + 1)));
productSelectorTableBody?.addEventListener('click', (event) => {
    const button = event.target.closest('.product-selector-select-btn');
    if (!button) return;
    const product = productSelectorState.products.get(Number(button.dataset.productId));
    if (product) selectLedgerProduct(product);
});

[productFilterCode, productFilterPartNumber, productFilterDescription, productFilterApplication].forEach((input) => {
    input?.addEventListener('input', () => {
        window.clearTimeout(productSelectorFilterTimer);
        productSelectorFilterTimer = window.setTimeout(() => loadProductSelector(1), 300);
    });
});
clearProductFiltersBtn?.addEventListener('click', () => {
    [productFilterCode, productFilterPartNumber, productFilterDescription, productFilterApplication].forEach((input) => {
        if (input) input.value = '';
    });
    loadProductSelector(1);
});

function updateLedgerActionLabels() {
    const isSales = (ledgerActionSelect?.value || 'sales') === 'sales';
    if (ledgerQuantityHeading) ledgerQuantityHeading.textContent = isSales ? 'Qty Out' : 'Qty In';
    if (ledgerPriceHeading) ledgerPriceHeading.textContent = isSales ? 'Price' : 'Cost';
    if (ledgerQuantityLabel) ledgerQuantityLabel.textContent = isSales ? 'Qty Out' : 'Qty In';
    if (ledgerPriceLabel) ledgerPriceLabel.textContent = isSales ? 'Price' : 'Cost';
    if (ledgerTransactionType) ledgerTransactionType.value = isSales ? 'OUT' : 'IN';
}

async function loadProductLedgerEntries(page = 1) {
    const product = productLedgerState.selectedProduct;
    const action = ledgerActionSelect?.value || 'sales';
    updateLedgerActionLabels();

    if (!product) {
        productLedgerState.entries.clear();
        productLedgerState.page = 1;
        productLedgerState.lastPage = 1;
        if (productLedgerTableBody) productLedgerTableBody.innerHTML = '<tr><td colspan="12" class="developer-empty-cell">Select a product to load its ledger entries.</td></tr>';
        if (selectedProductLedgerBalance) selectedProductLedgerBalance.textContent = '—';
        if (openLedgerEntryModalBtn) openLedgerEntryModalBtn.disabled = true;
        return;
    }

    if (productLedgerTableBody) productLedgerTableBody.innerHTML = '<tr><td colspan="12" class="developer-empty-cell">Loading product ledger entries...</td></tr>';
    const params = new URLSearchParams({ product_id: String(product.id), action, page: String(page), per_page: '15' });

    try {
        const payload = await developerFetchJson(`${developerInventoryRoutes.ledgerEntries}?${params}`);
        const rows = payload.data || [];
        const pagination = payload.pagination || {};
        productLedgerState.page = Number(pagination.current_page || 1);
        productLedgerState.lastPage = Number(pagination.last_page || 1);
        productLedgerState.latestBalance = Number(payload.product?.latest_balance || 0);
        productLedgerState.selectedProduct.ledger_inventory = productLedgerState.latestBalance;
        productLedgerState.entries.clear();
        rows.forEach((row) => productLedgerState.entries.set(Number(row.id), row));

        if (selectedProductLedgerBalance) selectedProductLedgerBalance.textContent = productLedgerState.latestBalance.toLocaleString();
        if (productLedgerPageInfo) productLedgerPageInfo.textContent = `Page ${productLedgerState.page} of ${productLedgerState.lastPage} · ${Number(pagination.total || 0).toLocaleString()} entries`;
        if (productLedgerPrevBtn) productLedgerPrevBtn.disabled = productLedgerState.page <= 1;
        if (productLedgerNextBtn) productLedgerNextBtn.disabled = productLedgerState.page >= productLedgerState.lastPage;

        if (rows.length === 0) {
            productLedgerTableBody.innerHTML = `<tr><td colspan="12" class="developer-empty-cell">No ${action} ledger entries found for this product.</td></tr>`;
            return;
        }

        productLedgerTableBody.innerHTML = rows.map((row) => `<tr>
            <td class="font-bold text-slate-900">${Number(row.id)}</td>
            <td>${escapeDeveloperHtml(row.date)}</td>
            <td>${escapeDeveloperHtml(row.entity_name)}</td>
            <td>${escapeDeveloperHtml(row.transaction_number)}</td>
            <td>${escapeDeveloperHtml(row.reference_number)}</td>
            <td class="text-right">${Number(row.quantity || 0).toLocaleString()}</td>
            <td class="text-right font-bold">${Number(row.balance_stock || 0).toLocaleString()}</td>
            <td>${escapeDeveloperHtml(row.oum)}</td>
            <td class="text-right">${Number(row.price || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            <td><span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-bold uppercase text-slate-600">${escapeDeveloperHtml(row.channel)}</span></td>
            <td>${escapeDeveloperHtml(row.created_at || '')}</td>
            <td><div class="flex gap-1"><button type="button" class="developer-row-action ledger-edit-btn" data-ledger-id="${Number(row.id)}">Edit</button><button type="button" class="developer-row-action is-danger ledger-delete-btn" data-ledger-id="${Number(row.id)}">Delete</button></div></td>
        </tr>`).join('');
    } catch (error) {
        productLedgerTableBody.innerHTML = `<tr><td colspan="12" class="developer-empty-cell text-rose-600">${escapeDeveloperHtml(error.message)}</td></tr>`;
    }
}

function localDateTimeInputValue(date = new Date()) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

function localDateValue(date = new Date()) {
    return localDateTimeInputValue(date).slice(0, 10);
}

function openLedgerModal(entry = null) {
    const product = productLedgerState.selectedProduct;
    if (!product) {
        showToast('Select a product first.');
        return;
    }

    updateLedgerActionLabels();
    const action = ledgerActionSelect?.value || 'sales';
    ledgerEntryForm?.reset();
    ledgerEntryId.value = entry ? String(entry.id) : '';
    ledgerEntryIdDisplay.value = entry ? String(entry.id) : 'Auto Increment';
    ledgerEntryModalTitle.textContent = entry ? `Edit ${action === 'sales' ? 'Sales' : 'Purchase'} Ledger Entry` : `Add ${action === 'sales' ? 'Sales' : 'Purchase'} Ledger Entry`;
    ledgerEntryProductText.textContent = `${product.product_code} — ${product.part_number || 'No part number'}`;
    ledgerTransactionType.value = action === 'sales' ? 'OUT' : 'IN';
    ledgerEntryDate.value = entry?.date || localDateValue();
    ledgerEntryCreatedAt.value = entry?.created_at_input || localDateTimeInputValue();
    ledgerEntityName.value = entry?.entity_name || '';
    ledgerTransactionNumber.value = entry?.transaction_number || '';
    ledgerReferenceNumber.value = entry?.reference_number || '';
    ledgerQuantity.value = entry?.quantity ?? 1;
    ledgerBalanceStock.value = entry?.balance_stock ?? productLedgerState.latestBalance;
    ledgerOum.value = entry?.oum || product.unit || '';
    ledgerPrice.value = entry?.price ?? (action === 'purchase' ? Number(product.cost || 0) : 0);
    const channel = entry?.channel || 'online';
    const channelRadio = ledgerEntryForm?.querySelector(`input[name="ledger_channel"][value="${channel}"]`);
    if (channelRadio) channelRadio.checked = true;
    showModal(ledgerEntryModal);
}

openLedgerEntryModalBtn?.addEventListener('click', () => openLedgerModal());
ledgerActionSelect?.addEventListener('change', () => loadProductLedgerEntries(1));
productLedgerPrevBtn?.addEventListener('click', () => loadProductLedgerEntries(Math.max(1, productLedgerState.page - 1)));
productLedgerNextBtn?.addEventListener('click', () => loadProductLedgerEntries(Math.min(productLedgerState.lastPage, productLedgerState.page + 1)));

productLedgerTableBody?.addEventListener('click', (event) => {
    const editButton = event.target.closest('.ledger-edit-btn');
    if (editButton) {
        const entry = productLedgerState.entries.get(Number(editButton.dataset.ledgerId));
        if (entry) openLedgerModal(entry);
        return;
    }

    const deleteButton = event.target.closest('.ledger-delete-btn');
    if (!deleteButton) return;
    const entry = productLedgerState.entries.get(Number(deleteButton.dataset.ledgerId));
    if (!entry) return;
    productLedgerState.pendingDelete = entry;
    if (ledgerDeleteConfirmText) ledgerDeleteConfirmText.textContent = `Permanently delete ledger ID ${entry.id} (${entry.transaction_number})? Balance Stock will be recalculated.`;
    showModal(ledgerDeleteConfirmModal);
});

ledgerEntryForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const product = productLedgerState.selectedProduct;
    const action = ledgerActionSelect?.value || 'sales';
    const editingId = Number(ledgerEntryId?.value || 0);
    if (!product) return;

    const payload = {
        product_id: Number(product.id),
        action,
        date: ledgerEntryDate.value,
        entity_name: ledgerEntityName.value.trim(),
        transaction_number: ledgerTransactionNumber.value.trim(),
        reference_number: ledgerReferenceNumber.value.trim(),
        quantity: Number(ledgerQuantity.value),
        balance_stock: Number(ledgerBalanceStock.value),
        oum: ledgerOum.value.trim(),
        price: Number(ledgerPrice.value),
        created_at: ledgerEntryCreatedAt.value,
        channel: ledgerEntryForm.querySelector('input[name="ledger_channel"]:checked')?.value || 'online',
    };

    const originalText = saveLedgerEntryBtn.textContent;
    saveLedgerEntryBtn.disabled = true;
    saveLedgerEntryBtn.textContent = 'Saving...';

    try {
        const url = editingId ? `${developerInventoryRoutes.ledgerBase}/${editingId}` : developerInventoryRoutes.ledgerStore;
        const result = await developerFetchJson(url, {
            method: editingId ? 'PUT' : 'POST',
            body: JSON.stringify(payload),
        });
        hideModal(ledgerEntryModal);
        showToast(result.message || 'Product ledger entry saved successfully.');
        await Promise.all([loadProductLedgerEntries(productLedgerState.page), loadStockMismatches(stockMismatchState.page), loadAutoSyncStatus()]);
    } catch (error) {
        showToast(error.message || 'Unable to save product ledger entry.');
    } finally {
        saveLedgerEntryBtn.disabled = false;
        saveLedgerEntryBtn.textContent = originalText;
    }
});

ledgerDeleteCancelBtn?.addEventListener('click', () => {
    productLedgerState.pendingDelete = null;
    hideModal(ledgerDeleteConfirmModal);
});

ledgerDeleteConfirmBtn?.addEventListener('click', async () => {
    const entry = productLedgerState.pendingDelete;
    if (!entry) return;
    const originalText = ledgerDeleteConfirmBtn.textContent;
    ledgerDeleteConfirmBtn.disabled = true;
    ledgerDeleteConfirmBtn.textContent = 'Deleting...';

    try {
        const result = await developerFetchJson(`${developerInventoryRoutes.ledgerBase}/${entry.id}`, {
            method: 'DELETE',
            body: JSON.stringify({}),
        });
        hideModal(ledgerDeleteConfirmModal);
        showToast(result.message || 'Product ledger entry permanently deleted.');
        await Promise.all([loadProductLedgerEntries(Math.min(productLedgerState.page, productLedgerState.lastPage)), loadStockMismatches(stockMismatchState.page), loadAutoSyncStatus()]);
    } catch (error) {
        showToast(error.message || 'Unable to delete product ledger entry.');
    } finally {
        productLedgerState.pendingDelete = null;
        ledgerDeleteConfirmBtn.disabled = false;
        ledgerDeleteConfirmBtn.textContent = originalText;
    }
});

[stockSyncConfirmModal, stockSyncSuccessModal, syncAllStockConfirmModal, syncAllPurchaseLedgerConfirmModal, purchaseLedgerSyncSuccessModal, syncAllPurchaseReturnLedgerConfirmModal, purchaseReturnLedgerSyncSuccessModal, productSelectorModal, ledgerEntryModal, ledgerDeleteConfirmModal].forEach((modal) => {
    modal?.addEventListener('click', (event) => {
        if (event.target === modal) hideModal(modal);
    });
});

updateLedgerActionLabels();
if (purchaseReturnSyncDateFrom && !purchaseReturnSyncDateFrom.value) {
    const firstDay = new Date();
    firstDay.setDate(1);
    purchaseReturnSyncDateFrom.value = localDateValue(firstDay);
}
if (purchaseReturnSyncDateTo && !purchaseReturnSyncDateTo.value) {
    purchaseReturnSyncDateTo.value = localDateValue();
}
loadAutoSyncStatus();
loadStockMismatches(1);
loadPurchaseLedgerMismatches(1);
loadPurchaseReturnLedgerMismatches(1);
