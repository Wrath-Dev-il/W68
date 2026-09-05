(() => {
    'use strict';

    const AUDIT_ENDPOINT = '/admin/system-security/audit-trail/data';
    const PRODUCT_MODULE = 'product';
    const PRODUCT_ACTIONS = new Set(['Created', 'Updated', 'Deleted']);
    const originalFetch = window.fetch ? window.fetch.bind(window) : null;

    let currentModule = null;
    let latestProductPayload = null;
    let renderTimers = [];
    let auditTable = null;
    let headerBackup = null;
    let filterBackup = null;

    const productItems = new Map();

    const FIELD_LABELS = {
        shopee_item_id: 'Shopee Item ID',
        product_code: 'Product Code',
        pricelist_code: 'Pricelist Code',
        product_picture: 'Product Picture',
        images: 'Images',
        part_number: 'Part Number',
        category: 'Category',
        specification: 'Specification',
        description: 'Description',
        unit: 'Unit',
        date_added: 'Date Added',
        application: 'Application',
        position: 'Position',
        on_hand: 'On Hand',
        re_order_level: 'Re-order Level',
        actual_qty: 'Actual Qty',
        status: 'Status',
        is_selected_for_report: 'Selected for Report',
        selling_price: 'Selling Price',
        price_online: 'Online Price',
        cost: 'Cost',
        price_codes: 'Price Codes'
    };

    const TECHNICAL_KEYS = new Set(['id', 'created_at', 'updated_at']);

    function safeJsonClone(value) {
        if (value === undefined) return null;
        try {
            return JSON.parse(JSON.stringify(value));
        } catch (_) {
            return value;
        }
    }

    function stableString(value) {
        if (value === null || value === undefined || value === '') return '';
        if (typeof value !== 'object') return String(value);
        if (Array.isArray(value)) {
            return JSON.stringify(value.map(stableComparable));
        }
        const sorted = {};
        Object.keys(value).sort().forEach((key) => {
            sorted[key] = stableComparable(value[key]);
        });
        return JSON.stringify(sorted);
    }

    function stableComparable(value) {
        if (Array.isArray(value)) return value.map(stableComparable);
        if (value && typeof value === 'object') {
            const out = {};
            Object.keys(value).sort().forEach((key) => {
                if (!['id', 'created_at', 'updated_at', 'product_id'].includes(String(key).toLowerCase())) {
                    out[key] = stableComparable(value[key]);
                }
            });
            return out;
        }
        return value;
    }

    function normalizeKey(key) {
        let normalized = String(key || '')
            .trim()
            .replace(/([a-z0-9])([A-Z])/g, '$1_$2')
            .replace(/[\s-]+/g, '_')
            .toLowerCase();

        if (normalized === 'reorder_level') normalized = 're_order_level';
        if (normalized === 'productpicture') normalized = 'product_picture';
        return normalized;
    }

    function normalizeRecord(record) {
        const out = {};
        if (!record || typeof record !== 'object') return out;

        Object.entries(record).forEach(([rawKey, value]) => {
            const key = normalizeKey(rawKey);
            if (!key || TECHNICAL_KEYS.has(key)) return;

            // Prefer a populated value if the same logical field appears with different casing.
            if (!(key in out) || (out[key] == null && value != null)) {
                out[key] = safeJsonClone(value);
            }
        });

        return out;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatMoneyLike(value) {
        const number = Number(value);
        if (!Number.isFinite(number)) return null;
        return new Intl.NumberFormat('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(number);
    }

    function formatValue(key, value) {
        if (value === null || value === undefined || value === '') return '—';

        if (key === 'is_selected_for_report') {
            const truthy = value === true || value === 1 || value === '1' || String(value).toLowerCase() === 'true';
            return truthy ? 'Yes' : 'No';
        }

        if (key === 'selling_price' || key === 'price_online' || key === 'cost') {
            const formatted = formatMoneyLike(value);
            return formatted !== null ? `₱${formatted}` : String(value);
        }

        if (key === 'price_codes' && Array.isArray(value)) {
            if (!value.length) return '—';
            return value.map((row) => {
                const code = row?.price_code ?? row?.code ?? '—';
                const priceRaw = row?.selling_price ?? row?.price ?? null;
                const price = priceRaw == null ? '' : formatMoneyLike(priceRaw);
                return price ? `${code} = ₱${price}` : String(code);
            }).join('\n');
        }

        if ((key === 'product_picture' || key === 'images') && typeof value === 'string') {
            const trimmed = value.trim();
            if (trimmed === '[]' || trimmed === '{}') return 'None';
            if (trimmed.length > 240) return '[Image data stored]';
        }

        if (Array.isArray(value)) {
            if (!value.length) return '—';
            return value.map((entry) => typeof entry === 'object' ? JSON.stringify(stableComparable(entry)) : String(entry)).join('\n');
        }

        if (typeof value === 'object') {
            return JSON.stringify(stableComparable(value), null, 2);
        }

        return String(value);
    }

    function fieldLabel(key) {
        if (FIELD_LABELS[key]) return FIELD_LABELS[key];
        return key.split('_').map((part) => part ? part.charAt(0).toUpperCase() + part.slice(1) : '').join(' ');
    }

    function findAuditTable() {
        const tables = Array.from(document.querySelectorAll('table'));
        if (!tables.length) return null;

        const scored = tables.map((table) => {
            const headerText = Array.from(table.querySelectorAll('thead th'))
                .map((th) => (th.textContent || '').trim().toLowerCase())
                .join(' | ');
            let score = 0;
            if (/action/.test(headerText)) score += 4;
            if (/user|username/.test(headerText)) score += 4;
            if (/date|time/.test(headerText)) score += 3;
            if (/module/.test(headerText)) score += 2;
            if (/name/.test(headerText)) score += 1;
            return { table, score };
        }).sort((a, b) => b.score - a.score);

        return scored[0]?.score > 0 ? scored[0].table : tables[0];
    }

    function backupTableChrome(table) {
        if (!table?.tHead?.rows?.length || headerBackup) return;
        const firstRow = table.tHead.rows[0];
        const cells = Array.from(firstRow.cells);
        headerBackup = cells.map((cell) => cell.innerHTML);

        const filterRow = table.tHead.rows.length > 1 ? table.tHead.rows[1] : null;
        if (filterRow) {
            filterBackup = Array.from(filterRow.cells).map((cell) => ({
                display: cell.style.display,
                visibility: cell.style.visibility,
                opacity: cell.style.opacity,
                inputs: Array.from(cell.querySelectorAll('input,select,textarea')).map((input) => ({
                    disabled: input.disabled,
                    display: input.style.display,
                    visibility: input.style.visibility
                }))
            }));
        }
    }

    function applyProductHeader(table) {
        if (!table?.tHead?.rows?.length) return;
        backupTableChrome(table);

        const firstRow = table.tHead.rows[0];
        const labels = ['Product Code', 'User Name', 'Time & Date', 'Action', 'View'];
        const cells = Array.from(firstRow.cells);
        cells.slice(0, labels.length).forEach((cell, index) => {
            cell.textContent = labels[index];
        });

        // Preserve the existing first four filter controls/listeners. The fifth generic Module
        // filter is hidden only while Product Masterlist is active because View is not searchable.
        const filterRow = table.tHead.rows.length > 1 ? table.tHead.rows[1] : null;
        if (filterRow && filterRow.cells.length >= 5) {
            const fifthCell = filterRow.cells[4];
            Array.from(fifthCell.querySelectorAll('input,select,textarea')).forEach((input) => {
                input.disabled = true;
                input.style.visibility = 'hidden';
            });
            fifthCell.style.visibility = 'hidden';
        }
    }

    function restoreGenericHeader() {
        if (!auditTable || !headerBackup || !auditTable.tHead?.rows?.length) return;
        const firstRow = auditTable.tHead.rows[0];
        Array.from(firstRow.cells).forEach((cell, index) => {
            if (headerBackup[index] !== undefined) cell.innerHTML = headerBackup[index];
        });

        const filterRow = auditTable.tHead.rows.length > 1 ? auditTable.tHead.rows[1] : null;
        if (filterRow && filterBackup) {
            Array.from(filterRow.cells).forEach((cell, cellIndex) => {
                const backup = filterBackup[cellIndex];
                if (!backup) return;
                cell.style.display = backup.display;
                cell.style.visibility = backup.visibility;
                cell.style.opacity = backup.opacity;
                Array.from(cell.querySelectorAll('input,select,textarea')).forEach((input, inputIndex) => {
                    const inputBackup = backup.inputs[inputIndex];
                    if (!inputBackup) return;
                    input.disabled = inputBackup.disabled;
                    input.style.display = inputBackup.display;
                    input.style.visibility = inputBackup.visibility;
                });
            });
        }
    }

    function actionBadge(action) {
        const normalized = String(action || '').toLowerCase();
        const styles = normalized === 'created'
            ? 'background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;'
            : normalized === 'deleted'
                ? 'background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;'
                : 'background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;';
        return `<span style="${styles}display:inline-flex;align-items:center;padding:4px 10px;border-radius:9999px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;">${escapeHtml(action || '—')}</span>`;
    }

    function renderProductRows(payload) {
        auditTable = findAuditTable();
        if (!auditTable) return;
        applyProductHeader(auditTable);

        const tbody = auditTable.tBodies?.[0] || auditTable.querySelector('tbody');
        if (!tbody) return;

        const items = Array.isArray(payload?.items) ? payload.items.filter((item) => PRODUCT_ACTIONS.has(item.action)) : [];
        productItems.clear();
        items.forEach((item) => productItems.set(String(item.id), item));

        if (!items.length) {
            tbody.innerHTML = `<tr><td colspan="5" style="padding:28px 12px;text-align:center;color:#64748b;font-size:13px;font-weight:600;">No Product Masterlist create, update, or delete audit records found.</td></tr>`;
            return;
        }

        tbody.innerHTML = items.map((item) => {
            const code = item.product_code || item.display_id || item.name || '—';
            return `
                <tr>
                    <td style="padding:12px 14px;font-weight:800;color:#0f172a;vertical-align:middle;">${escapeHtml(code)}</td>
                    <td style="padding:12px 14px;color:#334155;vertical-align:middle;">${escapeHtml(item.username || '—')}</td>
                    <td style="padding:12px 14px;color:#475569;vertical-align:middle;white-space:nowrap;">${escapeHtml(item.datetime || '—')}</td>
                    <td style="padding:12px 14px;vertical-align:middle;">${actionBadge(item.action)}</td>
                    <td style="padding:12px 14px;vertical-align:middle;">
                        <button type="button" data-product-audit-view="${escapeHtml(item.id)}" style="border:0;border-radius:8px;background:#7f1d1d;color:white;padding:7px 12px;font-size:11px;font-weight:800;cursor:pointer;text-transform:uppercase;letter-spacing:.04em;">View</button>
                    </td>
                </tr>`;
        }).join('');
    }

    function buildDetailRows(item) {
        const data = item?.audit_data && typeof item.audit_data === 'object' ? item.audit_data : {};
        const before = normalizeRecord(data.before || {});
        const after = normalizeRecord(data.after || {});
        const action = String(item?.action || '');

        if (action === 'Updated') {
            const keys = Array.from(new Set([...Object.keys(before), ...Object.keys(after)]));
            return keys
                .filter((key) => stableString(before[key]) !== stableString(after[key]))
                .map((key) => ({ key, before: before[key], after: after[key] }));
        }

        const source = action === 'Deleted' ? before : after;
        return Object.keys(source).map((key) => ({
            key,
            before: action === 'Deleted' ? source[key] : undefined,
            after: action === 'Created' ? source[key] : undefined
        }));
    }

    function ensureModal() {
        let modal = document.getElementById('product-audit-detail-modal');
        if (modal) return modal;

        modal = document.createElement('div');
        modal.id = 'product-audit-detail-modal';
        modal.style.cssText = 'display:none;position:fixed;inset:0;z-index:99999;background:rgba(15,23,42,.72);padding:24px;overflow:auto;';
        modal.innerHTML = `
            <div style="max-width:1100px;margin:20px auto;background:white;border-radius:16px;box-shadow:0 25px 70px rgba(0,0,0,.28);overflow:hidden;">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:20px 22px;border-bottom:1px solid #e2e8f0;background:#f8fafc;">
                    <div>
                        <div style="font-size:11px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;color:#7f1d1d;">Product Masterlist Audit Detail</div>
                        <div id="product-audit-modal-title" style="margin-top:5px;font-size:20px;font-weight:900;color:#0f172a;">Product</div>
                        <div id="product-audit-modal-meta" style="margin-top:6px;font-size:12px;font-weight:600;color:#64748b;"></div>
                    </div>
                    <button type="button" data-product-audit-close style="border:0;background:#e2e8f0;color:#334155;width:34px;height:34px;border-radius:9px;font-size:20px;line-height:1;cursor:pointer;">×</button>
                </div>
                <div style="padding:20px 22px;">
                    <div id="product-audit-summary" style="margin-bottom:14px;padding:12px 14px;border-radius:10px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;font-size:12px;font-weight:700;"></div>
                    <div style="overflow:auto;border:1px solid #e2e8f0;border-radius:12px;">
                        <table style="width:100%;border-collapse:collapse;min-width:720px;">
                            <thead id="product-audit-detail-head"></thead>
                            <tbody id="product-audit-detail-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>`;
        document.body.appendChild(modal);
        return modal;
    }

    function renderDetailModal(item) {
        const modal = ensureModal();
        const title = modal.querySelector('#product-audit-modal-title');
        const meta = modal.querySelector('#product-audit-modal-meta');
        const summary = modal.querySelector('#product-audit-summary');
        const head = modal.querySelector('#product-audit-detail-head');
        const body = modal.querySelector('#product-audit-detail-body');

        const code = item.product_code || item.display_id || item.name || '—';
        title.textContent = code;
        meta.textContent = `User: ${item.username || '—'}  •  Action: ${item.action || '—'}  •  Time & Date: ${item.datetime || '—'}`;

        const rows = buildDetailRows(item);
        const action = String(item.action || '');

        if (action === 'Updated') {
            summary.textContent = rows.length
                ? `${rows.length} field${rows.length === 1 ? '' : 's'} changed. Only fields whose values actually changed are shown below.`
                : 'No value differences were found in the stored before/after snapshots.';
            head.innerHTML = '<tr style="background:#f8fafc;"><th style="text-align:left;padding:11px 12px;border-bottom:1px solid #e2e8f0;font-size:11px;text-transform:uppercase;color:#475569;">Field</th><th style="text-align:left;padding:11px 12px;border-bottom:1px solid #e2e8f0;font-size:11px;text-transform:uppercase;color:#475569;">Before</th><th style="text-align:left;padding:11px 12px;border-bottom:1px solid #e2e8f0;font-size:11px;text-transform:uppercase;color:#475569;">After</th></tr>';
            body.innerHTML = rows.length ? rows.map((row) => `
                <tr>
                    <td style="padding:11px 12px;border-bottom:1px solid #f1f5f9;font-weight:800;color:#334155;vertical-align:top;width:22%;">${escapeHtml(fieldLabel(row.key))}</td>
                    <td style="padding:11px 12px;border-bottom:1px solid #f1f5f9;color:#991b1b;white-space:pre-wrap;word-break:break-word;vertical-align:top;width:39%;">${escapeHtml(formatValue(row.key, row.before))}</td>
                    <td style="padding:11px 12px;border-bottom:1px solid #f1f5f9;color:#047857;white-space:pre-wrap;word-break:break-word;vertical-align:top;width:39%;">${escapeHtml(formatValue(row.key, row.after))}</td>
                </tr>`).join('') : '<tr><td colspan="3" style="padding:22px;text-align:center;color:#64748b;">No changed fields found.</td></tr>';
        } else {
            const valueLabel = action === 'Deleted' ? 'Value Before Deletion' : 'Value Entered';
            summary.textContent = action === 'Deleted'
                ? 'These are the Product Masterlist values stored immediately before the product was deleted.'
                : 'These are the Product Masterlist values stored when the product was created.';
            head.innerHTML = `<tr style="background:#f8fafc;"><th style="text-align:left;padding:11px 12px;border-bottom:1px solid #e2e8f0;font-size:11px;text-transform:uppercase;color:#475569;">Field</th><th style="text-align:left;padding:11px 12px;border-bottom:1px solid #e2e8f0;font-size:11px;text-transform:uppercase;color:#475569;">${escapeHtml(valueLabel)}</th></tr>`;
            body.innerHTML = rows.length ? rows.map((row) => {
                const value = action === 'Deleted' ? row.before : row.after;
                return `
                    <tr>
                        <td style="padding:11px 12px;border-bottom:1px solid #f1f5f9;font-weight:800;color:#334155;vertical-align:top;width:30%;">${escapeHtml(fieldLabel(row.key))}</td>
                        <td style="padding:11px 12px;border-bottom:1px solid #f1f5f9;color:#0f172a;white-space:pre-wrap;word-break:break-word;vertical-align:top;">${escapeHtml(formatValue(row.key, value))}</td>
                    </tr>`;
            }).join('') : '<tr><td colspan="2" style="padding:22px;text-align:center;color:#64748b;">No stored product snapshot found for this audit record.</td></tr>';
        }

        modal.style.display = 'block';
        document.documentElement.style.overflow = 'hidden';
    }

    function closeModal() {
        const modal = document.getElementById('product-audit-detail-modal');
        if (modal) modal.style.display = 'none';
        document.documentElement.style.overflow = '';
    }

    function requestUrlFromFetch(input) {
        if (typeof input === 'string') return input;
        if (input instanceof URL) return input.toString();
        if (input && typeof input.url === 'string') return input.url;
        return '';
    }

    function isAuditDataUrl(url) {
        try {
            const parsed = new URL(url, window.location.href);
            return parsed.pathname.endsWith(AUDIT_ENDPOINT) || parsed.pathname.includes(AUDIT_ENDPOINT);
        } catch (_) {
            return String(url || '').includes(AUDIT_ENDPOINT);
        }
    }

    function moduleFromUrl(url) {
        try {
            const parsed = new URL(url, window.location.href);
            return String(parsed.searchParams.get('module') || 'all').trim().toLowerCase();
        } catch (_) {
            return 'all';
        }
    }

    function clearRenderTimers() {
        renderTimers.forEach((timer) => window.clearTimeout(timer));
        renderTimers = [];
    }

    function scheduleProductRender(payload) {
        clearRenderTimers();
        [0, 60, 180].forEach((delay) => {
            renderTimers.push(window.setTimeout(() => {
                if (currentModule === PRODUCT_MODULE) renderProductRows(payload);
            }, delay));
        });
    }

    if (originalFetch) {
        window.fetch = async function (...args) {
            const response = await originalFetch(...args);
            const url = requestUrlFromFetch(args[0]);

            if (isAuditDataUrl(url)) {
                const module = moduleFromUrl(url);
                currentModule = module;

                try {
                    const clone = response.clone();
                    clone.json().then((payload) => {
                        if (module === PRODUCT_MODULE) {
                            latestProductPayload = payload;
                            scheduleProductRender(payload);
                        } else {
                            latestProductPayload = null;
                            clearRenderTimers();
                            window.setTimeout(restoreGenericHeader, 0);
                            window.setTimeout(restoreGenericHeader, 80);
                        }
                    }).catch(() => {});
                } catch (_) {
                    // Keep the existing audit page behavior even if cloning/parsing fails.
                }
            }

            return response;
        };
    }

    document.addEventListener('click', (event) => {
        const viewButton = event.target.closest('[data-product-audit-view]');
        if (viewButton) {
            const item = productItems.get(String(viewButton.getAttribute('data-product-audit-view')));
            if (item) renderDetailModal(item);
            return;
        }

        if (event.target.closest('[data-product-audit-close]')) {
            closeModal();
            return;
        }

        const modal = document.getElementById('product-audit-detail-modal');
        if (modal && event.target === modal) {
            closeModal();
            return;
        }

        // Fallback for pages where the existing audit script made its first request before this
        // enhancer loaded. Clicking the existing Product Masterlist dashboard card will trigger
        // the normal page behavior and, if needed, one read-only Product request for this view.
        const moduleTarget = event.target.closest('[data-module], [data-audit-module], button, a, [role=\"button\"], .cursor-pointer');
        if (moduleTarget) {
            const explicitModule = String(
                moduleTarget.getAttribute('data-module')
                || moduleTarget.getAttribute('data-audit-module')
                || ''
            ).trim().toLowerCase();
            const text = String(moduleTarget.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
            const isProductCard = explicitModule === PRODUCT_MODULE || /product\s*masterlist|product\s*master\s*list/.test(text);

            if (isProductCard) {
                currentModule = PRODUCT_MODULE;
                window.setTimeout(() => {
                    if (latestProductPayload || !originalFetch) return;
                    originalFetch(`${AUDIT_ENDPOINT}?module=product&page=1&perPage=50`, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin'
                    })
                        .then((response) => response.ok ? response.json() : null)
                        .then((payload) => {
                            if (!payload || currentModule !== PRODUCT_MODULE) return;
                            latestProductPayload = payload;
                            scheduleProductRender(payload);
                        })
                        .catch(() => {});
                }, 120);
            }
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeModal();
    });
})();
