document.addEventListener('DOMContentLoaded', () => {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    initSalesReportDateInputs();
    initSalesReportDropdowns();
    initSalesReportActions();
});

function initSalesReportDateInputs() {
    const dateTypeSelect = document.getElementById('sales-report-date-type');
    const dateInputsContainer = document.getElementById('sales-report-date-inputs');

    if (!dateTypeSelect || !dateInputsContainer) return;

    const renderDateInputs = (type) => {
        const templates = {
            monthly: {
                single: true,
                html: createDateInputCard('Month', `
                    <input id="sales-report-date-monthly" type="month" class="sales-report-input w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all" />
                `)
            },
            annual: {
                single: true,
                html: createDateInputCard('Year', `
                    <input id="sales-report-date-annual" type="number" min="2000" max="2100" step="1" class="sales-report-input w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all" placeholder="Enter year" />
                `)
            },
            'as-of': {
                single: true,
                html: createDateInputCard('As Of Date', `
                    <input id="sales-report-date-as-of" type="date" class="sales-report-input w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all" />
                `)
            },
            'from-to': {
                single: false,
                html: `
                    ${createDateInputCard('From', `<input id="sales-report-date-from" type="date" class="sales-report-input w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all" />`)}
                    ${createDateInputCard('To', `<input id="sales-report-date-to" type="date" class="sales-report-input w-full bg-slate-50 border border-slate-200 rounded-xl pl-10 pr-4 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all" />`)}
                `
            }
        };

        const selectedTemplate = templates[type] || templates.monthly;
        dateInputsContainer.classList.toggle('is-single', !!selectedTemplate.single);
        dateInputsContainer.innerHTML = selectedTemplate.html;
    };

    dateTypeSelect.addEventListener('change', () => {
        renderDateInputs(dateTypeSelect.value);
    });

    renderDateInputs(dateTypeSelect.value);

    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

function createDateInputCard(label, inputHtml) {
    return `
        <div class="sales-report-date-card">
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400">${label}</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <i data-lucide="calendar-days" class="w-4 h-4"></i>
                </div>
                ${inputHtml}
            </div>
        </div>
    `;
}

function initSalesReportDropdowns() {
    document.querySelectorAll('.sales-report-searchable').forEach((wrapper) => {
        const input = wrapper.querySelector('.sales-report-input');
        const toggle = wrapper.querySelector('.sales-report-toggle');
        const dropdown = wrapper.querySelector('.sales-report-dropdown');
        const endpoint = wrapper.dataset.endpoint;
        const valueType = wrapper.dataset.type || 'text';
        const defaultLabel = wrapper.dataset.defaultLabel || '';

        if (!input || !toggle || !dropdown || !endpoint) return;

        let activeIndex = -1;
        let currentItems = [];
        let debounceTimer = null;

        const renderOptions = (items) => {
            currentItems = items;
            activeIndex = -1;

            const normalizedItems = [];
            if (defaultLabel) {
                normalizedItems.push({ label: defaultLabel, value: defaultLabel, meta: 'Default' });
            }

            items.forEach((item) => {
                if (valueType === 'object') {
                    normalizedItems.push({
                        label: item.name || '---',
                        value: item.name || '',
                        meta: `ID ${item.id ?? 'N/A'}`
                    });
                } else {
                    normalizedItems.push({
                        label: item || '---',
                        value: item || '',
                        meta: 'Option'
                    });
                }
            });

            if (!normalizedItems.length) {
                dropdown.innerHTML = `<button type="button" class="sales-report-option"><span>No results found</span><span class="sales-report-option-meta">Empty</span></button>`;
                dropdown.classList.remove('hidden');
                return;
            }

            dropdown.innerHTML = normalizedItems.map((item, index) => `
                <button type="button" class="sales-report-option" data-index="${index}" data-value="${escapeHtml(item.value)}">
                    <span>${escapeHtml(item.label)}</span>
                    <span class="sales-report-option-meta">${escapeHtml(item.meta)}</span>
                </button>
            `).join('');

            dropdown.classList.remove('hidden');

            dropdown.querySelectorAll('.sales-report-option').forEach((option) => {
                option.addEventListener('click', () => {
                    input.value = option.dataset.value || '';
                    closeDropdown();
                });
            });
        };

        const fetchOptions = async (query = '') => {
            try {
                const url = new URL(endpoint, window.location.origin);
                if (query.trim() !== '') {
                    url.searchParams.set('query', query.trim());
                }

                const response = await fetch(url.toString());
                const data = await response.json();
                renderOptions(Array.isArray(data) ? data : []);
            } catch (error) {
                dropdown.innerHTML = `<button type="button" class="sales-report-option"><span>Failed to load</span><span class="sales-report-option-meta">Error</span></button>`;
                dropdown.classList.remove('hidden');
            }
        };

        const closeDropdown = () => {
            dropdown.classList.add('hidden');
            activeIndex = -1;
        };

        input.addEventListener('focus', () => fetchOptions(input.value));
        input.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => fetchOptions(input.value), 220);
        });

        input.addEventListener('keydown', (event) => {
            const options = Array.from(dropdown.querySelectorAll('.sales-report-option'));
            if (!options.length || dropdown.classList.contains('hidden')) return;

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                activeIndex = (activeIndex + 1) % options.length;
                highlightOption(options, activeIndex);
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                activeIndex = (activeIndex - 1 + options.length) % options.length;
                highlightOption(options, activeIndex);
            }

            if (event.key === 'Enter' && activeIndex >= 0) {
                event.preventDefault();
                options[activeIndex].click();
            }

            if (event.key === 'Escape') {
                closeDropdown();
            }
        });

        toggle.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            if (dropdown.classList.contains('hidden')) {
                fetchOptions(input.value);
            } else {
                closeDropdown();
            }
        });

        document.addEventListener('click', (event) => {
            if (!wrapper.contains(event.target)) {
                closeDropdown();
            }
        });
    });
}

function highlightOption(options, activeIndex) {
    options.forEach((option, index) => {
        option.classList.toggle('is-active', index === activeIndex);
    });
}

function initSalesReportActions() {
    const clearBtn = document.getElementById('sales-report-clear-btn');
    const generateBtn = document.getElementById('sales-report-generate-btn');

    // Select all text on focus so typing replaces "Select All" immediately
    // Restore "Select All" when left blank
    var customerInput = document.getElementById('sales-report-customer');
    if (customerInput) {
        customerInput.addEventListener('focus', function() {
            this.select();
        });
        customerInput.addEventListener('blur', function() {
            var v = (this.value || '').trim();
            if (v === '' || v.toLowerCase() === 'all') {
                this.value = 'Select All';
            }
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            document.querySelectorAll('.sales-report-input').forEach((input) => {
                input.value = '';
            });

            const customerInput = document.getElementById('sales-report-customer');
            if (customerInput) {
                customerInput.value = 'Select All';
            }

            const dateType = document.getElementById('sales-report-date-type');
            if (dateType) {
                dateType.value = 'monthly';
                dateType.dispatchEvent(new Event('change'));
            }

            const defaultRadio = document.querySelector('input[name="sales-report-type"][value="statement-of-account"]');
            if (defaultRadio) {
                defaultRadio.checked = true;
            }

            showSalesReportToast('Filters cleared successfully.');
        });
    }

    if (generateBtn) {
        generateBtn.addEventListener('click', () => printStatementOfAccount());
    }
}

async function printStatementOfAccount() {
    const endpoint = window.salesReportRoutes?.printSoa;
    if (!endpoint) {
        showSalesReportToast('SOA print endpoint is missing.');
        return;
    }

    const dateType = document.getElementById('sales-report-date-type')?.value || 'monthly';
    const payload = {
        report_type: 'statement-of-account',
        date_type: dateType,
        date: '',
        date_from: '',
        date_to: '',
        customer: normalizeSalesReportCustomer(document.getElementById('sales-report-customer')?.value),
        description: '',
        category: '',
        agent: (document.getElementById('sales-report-agent')?.value || '').trim(),
    };

    if (dateType === 'monthly') {
        payload.date = document.getElementById('sales-report-date-monthly')?.value || '';
    } else if (dateType === 'annual') {
        payload.date = (document.getElementById('sales-report-date-annual')?.value || '').trim();
    } else if (dateType === 'as-of') {
        payload.date = document.getElementById('sales-report-date-as-of')?.value || '';
    } else if (dateType === 'from-to') {
        payload.date_from = document.getElementById('sales-report-date-from')?.value || '';
        payload.date_to = document.getElementById('sales-report-date-to')?.value || '';
    }

    try {
        showSalesReportToast('Preparing SOA print...');
        const res = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'text/html, application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify(payload),
        });

        const contentType = res.headers.get('content-type') || '';
        if (contentType.includes('application/json')) {
            const data = await res.json();
            showSalesReportToast(data?.message || 'Failed to load SOA data.');
            return;
        }

        const html = await res.text();
        if (!html || html.trim().length < 50) {
            showSalesReportToast('Empty response from server.');
            return;
        }

        printHtmlInNewWindow(html);
    } catch (e) {
        showSalesReportToast('SOA print failed.');
    }
}

function printHtmlInNewWindow(html) {
    const printWindow = window.open('', '_blank', 'width=1200,height=850');
    if (!printWindow) {
        showSalesReportToast('Please allow pop-ups for this site.');
        return;
    }

    printWindow.document.open();
    printWindow.document.write(html);
    printWindow.document.close();
    printWindow.focus();
}

async function printSalesNoteUnservedDetails() {
    const endpoint = window.salesReportRoutes?.printData;
    if (!endpoint) {
        showSalesReportToast('Print endpoint is missing.');
        return;
    }

    const dateType = document.getElementById('sales-report-date-type')?.value || 'monthly';
    const payload = {
        report_type: document.querySelector('input[name="sales-report-type"]:checked')?.value || 'sales-note-unserved-details',
        date_type: dateType,
        date: '',
        date_from: '',
        date_to: '',
        customer: (document.getElementById('sales-report-customer')?.value || 'Select All').trim(),
        description: (document.getElementById('sales-report-description')?.value || '').trim(),
        category: (document.getElementById('sales-report-category')?.value || '').trim(),
        agent: (document.getElementById('sales-report-agent')?.value || '').trim(),
    };

    if (dateType === 'monthly') {
        payload.date = document.getElementById('sales-report-date-monthly')?.value || '';
    } else if (dateType === 'annual') {
        payload.date = (document.getElementById('sales-report-date-annual')?.value || '').trim();
    } else if (dateType === 'as-of') {
        payload.date = document.getElementById('sales-report-date-as-of')?.value || '';
    } else if (dateType === 'from-to') {
        payload.date_from = document.getElementById('sales-report-date-from')?.value || '';
        payload.date_to = document.getElementById('sales-report-date-to')?.value || '';
    }

    try {
        showSalesReportToast('Preparing print...');
        const res = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify(payload),
        });

        const data = await res.json();
        if (!data?.success) {
            showSalesReportToast(data?.message || 'Failed to load print data.');
            return;
        }

        const notes = Array.isArray(data.notes) ? data.notes : [];
        if (!notes.length) {
            showSalesReportToast('No PARTIAL unserved items found.');
            return;
        }

        const html = buildSalesUnservedPrintHtml(notes);
        printHtmlInNewWindow(html);
    } catch (e) {
        showSalesReportToast('Print failed.');
    }
}

function buildSalesUnservedPrintHtml(notes) {
    const peso = new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const qtyFmt = new Intl.NumberFormat(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });

    const pages = notes.map((note) => {
        const rows = (note.items || []).map((it) => {
            return `
                <tr>
                    <td class="t-right">${escapeHtml(qtyFmt.format(it.unserved_qty ?? 0))}</td>
                    <td class="t-right">${escapeHtml(qtyFmt.format(it.on_hand ?? 0))}</td>
                    <td class="t-center">${escapeHtml(it.unit || '')}</td>
                    <td class="t-mono">${escapeHtml(it.item_code || '')}</td>
                    <td>${escapeHtml(it.description || '')}</td>
                    <td class="t-right">${escapeHtml(peso.format(it.unit_price ?? 0))}</td>
                    <td class="t-right">${escapeHtml(peso.format(it.subtotal ?? 0))}</td>
                </tr>
            `;
        }).join('');

        return `
            <section class="page">
                <div class="company">W68 AUTOPARTS &amp; SERVICE CENTER</div>

                <div class="meta">
                    <div class="meta-left">
                        <div class="meta-row"><span class="label">CUSTOMER:</span> <span class="value">${escapeHtml(note.customer_name || '')}</span></div>
                        <div class="meta-row"><span class="label">ADDRESS:</span> <span class="value">${escapeHtml(note.address || '--')}</span></div>
                    </div>
                    <div class="meta-right">
                        <div class="meta-row"><span class="label">SN NO:</span> <span class="value">${escapeHtml(note.sales_number || '')}</span></div>
                        <div class="meta-row"><span class="label">DATE:</span> <span class="value">${escapeHtml(note.date || '')}</span></div>
                    </div>
                </div>

                <table class="tbl">
                    <thead>
                        <tr>
                            <th style="width: 90px;">UNSERVED QTY</th>
                            <th style="width: 90px;">ON HAND</th>
                            <th style="width: 70px;">UNIT</th>
                            <th style="width: 120px;">ITEM CODE</th>
                            <th>DESCRIPTION</th>
                            <th style="width: 110px;">UNIT PRICE</th>
                            <th style="width: 110px;">SUBTOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows || '<tr><td colspan="7" class="t-center">No items</td></tr>'}
                    </tbody>
                </table>
            </section>
        `;
    }).join('');

    return `
<!doctype html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Sales Note Unserved Details</title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; color: #000; font-size: 12px; margin: 0; padding: 18px 18px 82px; }
        .print-document { width: 100%; }
        .print-document[contenteditable="true"] { outline: 2px dashed #2563eb; outline-offset: 6px; cursor: text; }
        .print-document[contenteditable="true"]:focus { outline-color: #1d4ed8; }
        .company { text-align: center; font-weight: 900; letter-spacing: 0.04em; font-size: 16px; margin-bottom: 14px; text-transform: uppercase; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 12px; }
        .meta-row { display: grid; grid-template-columns: 90px 1fr; gap: 8px; margin: 4px 0; }
        .label { font-weight: 800; }
        .value { font-weight: 700; }
        .tbl { width: 100%; border-collapse: collapse; }
        .tbl th, .tbl td { border: 1px solid #000; padding: 6px 8px; vertical-align: top; }
        .tbl th { font-weight: 900; text-transform: uppercase; font-size: 11px; }
        .t-right { text-align: right; }
        .t-center { text-align: center; }
        .t-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .edit-status { display: none; font-size: 12px; font-weight: 700; color: #92400e; }
        body.edit-mode .edit-status { display: inline; }
        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
            .print-document, .print-document[contenteditable="true"] { outline: none !important; }
        }
    </style>
</head>
<body>
    <main id="sales-report-print-document" class="print-document">
        ${pages}
    </main>

    <div class="no-print" style="position:fixed;bottom:0;left:0;right:0;background:#fff;border-top:2px solid #800000;padding:10px 20px;display:flex;justify-content:center;align-items:center;gap:12px;z-index:9999;flex-wrap:wrap;font-family:Arial,sans-serif;">
        <button id="edit-print-btn" type="button" onclick="toggleEditMode()" style="padding:10px 24px;background:#0f766e;border:none;border-radius:10px;font-size:12px;font-weight:bold;color:#fff;cursor:pointer;">Edit Print</button>
        <button id="reset-print-btn" type="button" onclick="resetPrint()" style="padding:10px 24px;background:#fff7ed;border:1px solid #fdba74;border-radius:10px;font-size:12px;font-weight:bold;color:#9a3412;cursor:pointer;">Reset</button>
        <span class="edit-status">EDIT MODE ON — click any report text and type. Changes are print-only.</span>
        <button type="button" onclick="printReport()" style="padding:10px 24px;background:#800000;border:none;border-radius:10px;font-size:12px;font-weight:bold;color:#fff;cursor:pointer;">Print Report</button>
        <button id="export-pdf-btn" type="button" onclick="exportPdf()" style="padding:10px 24px;background:#1e40af;border:none;border-radius:10px;font-size:12px;font-weight:bold;color:#fff;cursor:pointer;">Export PDF</button>
        <button type="button" onclick="window.close()" style="padding:10px 24px;background:#fff;border:2px solid #e2e8f0;border-radius:10px;font-size:12px;font-weight:bold;color:#475569;cursor:pointer;">Close</button>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"><\/script>
    <script>
        var originalReportHtml = '';
        var reportEditMode = false;

        function setEditMode(enabled) {
            var report = document.getElementById('sales-report-print-document');
            var btn = document.getElementById('edit-print-btn');
            reportEditMode = !!enabled;
            report.setAttribute('contenteditable', reportEditMode ? 'true' : 'false');
            report.setAttribute('spellcheck', 'false');
            document.body.classList.toggle('edit-mode', reportEditMode);
            if (btn) {
                btn.textContent = reportEditMode ? 'Finish Editing' : 'Edit Print';
                btn.style.background = reportEditMode ? '#b45309' : '#0f766e';
            }
            if (reportEditMode) report.focus();
            else if (document.activeElement === report) report.blur();
        }

        function toggleEditMode() { setEditMode(!reportEditMode); }

        function resetPrint() {
            var report = document.getElementById('sales-report-print-document');
            report.innerHTML = originalReportHtml;
            setEditMode(false);
        }

        function printReport() {
            var wasEditing = reportEditMode;
            setEditMode(false);
            window.print();
            if (wasEditing) setEditMode(true);
        }

        function insertPlainText(event) {
            if (!reportEditMode) return;
            event.preventDefault();
            var text = (event.clipboardData || window.clipboardData).getData('text/plain');
            var selection = window.getSelection();
            if (!selection || selection.rangeCount === 0) return;
            selection.deleteFromDocument();
            var range = selection.getRangeAt(0);
            var node = document.createTextNode(text);
            range.insertNode(node);
            range.setStartAfter(node);
            range.collapse(true);
            selection.removeAllRanges();
            selection.addRange(range);
        }

        window.onload = function () {
            var report = document.getElementById('sales-report-print-document');
            originalReportHtml = report.innerHTML;
            report.addEventListener('paste', insertPlainText);
            setEditMode(false);
        };

        function exportPdf() {
            var btn = document.getElementById('export-pdf-btn');
            var wasEditing = reportEditMode;
            var report = document.getElementById('sales-report-print-document');
            setEditMode(false);
            btn.textContent = 'Generating PDF...';
            btn.disabled = true;
            var options = {
                margin: [12, 12, 12, 12],
                filename: 'sales-report.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                pagebreak: { mode: ['css', 'legacy'], after: '.page' }
            };
            if (typeof html2pdf !== 'function') {
                btn.textContent = 'Export PDF';
                btn.disabled = false;
                if (wasEditing) setEditMode(true);
                alert('PDF library could not be loaded. You can still use Print Report and choose Save as PDF.');
                return;
            }
            html2pdf().set(options).from(report).save().then(function () {
                btn.textContent = 'Export PDF';
                btn.disabled = false;
                if (wasEditing) setEditMode(true);
            }).catch(function () {
                btn.textContent = 'Export PDF';
                btn.disabled = false;
                if (wasEditing) setEditMode(true);
            });
        }
    <\/script>
</body>
</html>
    `;
}

function printHtmlViaIframe(html) {
    const iframe = document.createElement('iframe');
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    document.body.appendChild(iframe);

    const doc = iframe.contentWindow?.document;
    if (!doc) {
        iframe.remove();
        showSalesReportToast('Print failed.');
        return;
    }

    doc.open();
    doc.write(html);
    doc.close();

    const afterPrintCleanup = () => {
        iframe.contentWindow?.removeEventListener('afterprint', afterPrintCleanup);
        setTimeout(() => iframe.remove(), 150);
    };

    iframe.contentWindow?.addEventListener('afterprint', afterPrintCleanup);

    setTimeout(() => {
        iframe.contentWindow?.focus();
        iframe.contentWindow?.print();
    }, 300);
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || window.csrfToken || '';
}

function showSalesReportToast(message) {
    const toast = document.getElementById('sales-report-toast');
    const text = document.getElementById('sales-report-toast-text');
    if (!toast || !text) return;

    text.textContent = message;
    toast.classList.remove('hidden');

    clearTimeout(window._salesReportToastTimer);
    window._salesReportToastTimer = setTimeout(() => {
        toast.classList.add('hidden');
    }, 2600);
}

function formatLabel(value) {
    return String(value)
        .replace(/-/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

function normalizeSalesReportCustomer(value) {
    var v = (value || '').trim();
    if (v === '' || v.toLowerCase() === 'all' || v.toLowerCase() === 'select all') {
        return 'Select All';
    }
    return v;
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
