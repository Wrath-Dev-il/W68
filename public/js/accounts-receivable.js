 (function () {
     const root = document.getElementById('page-accounts-receivable');
     if (!root) return;
 
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const printUrl = root.dataset.printUrl || '';

     const els = {
         dateType: document.getElementById('ar-date-type'),
         dateInputs: document.getElementById('ar-date-inputs'),
         printBtn: document.getElementById('ar-print-btn'),
         customerId: document.getElementById('ar-customer-id'),
         printDateType: document.getElementById('ar-print-date-type'),
         printDateValue: document.getElementById('ar-print-date-value'),
        printSections: document.getElementById('ar-print-sections'),
     };
 
     function safeStr(v) {
         if (v === null || v === undefined) return '';
         return String(v);
     }

    function escapeHtml(v) {
        return safeStr(v).replace(/[&<>"']/g, (ch) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        }[ch]));
    }
 
     function qs(sel, parent) {
         return (parent || document).querySelector(sel);
     }
 
     function qsa(sel, parent) {
         return Array.from((parent || document).querySelectorAll(sel));
     }
 
     function closeAllDropdowns() {
         qsa('.ar-dropdown').forEach((dd) => dd.classList.add('hidden'));
     }
 
     function debounce(fn, delayMs) {
         let t = null;
         return function (...args) {
             if (t) window.clearTimeout(t);
             t = window.setTimeout(() => fn.apply(this, args), delayMs);
         };
     }
 
     function renderDateInputs(type) {
         if (!els.dateInputs) return;
 
         if (type === 'annual') {
             const year = new Date().getFullYear();
             els.dateInputs.innerHTML = `
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                     <div class="space-y-2">
                         <label for="ar-annual" class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Annual</label>
                         <input id="ar-annual" type="number" min="2000" max="2100" value="${year}" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all">
                     </div>
                 </div>
             `;
             return;
         }
 
         if (type === 'monthly') {
             const yyyy = new Date().getFullYear();
             const mm = String(new Date().getMonth() + 1).padStart(2, '0');
             els.dateInputs.innerHTML = `
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                     <div class="space-y-2">
                         <label for="ar-monthly" class="block text-[10px] font-black uppercase tracking-widest text-slate-400">Monthly</label>
                         <input id="ar-monthly" type="month" value="${yyyy}-${mm}" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all">
                     </div>
                 </div>
             `;
             return;
         }
 
         if (type === 'as-of') {
             const yyyy = new Date().getFullYear();
             const mm = String(new Date().getMonth() + 1).padStart(2, '0');
             const dd = String(new Date().getDate()).padStart(2, '0');
             els.dateInputs.innerHTML = `
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                     <div class="space-y-2">
                         <label for="ar-as-of" class="block text-[10px] font-black uppercase tracking-widest text-slate-400">As of</label>
                         <input id="ar-as-of" type="date" value="${yyyy}-${mm}-${dd}" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all">
                     </div>
                 </div>
             `;
             return;
         }
 
         els.dateInputs.innerHTML = `
             <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                 <div class="space-y-2">
                     <label for="ar-from" class="block text-[10px] font-black uppercase tracking-widest text-slate-400">From</label>
                     <input id="ar-from" type="date" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all">
                 </div>
                 <div class="space-y-2">
                     <label for="ar-to" class="block text-[10px] font-black uppercase tracking-widest text-slate-400">To</label>
                     <input id="ar-to" type="date" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all">
                 </div>
             </div>
         `;
     }
 
     async function fetchOptions(endpoint, query) {
         const url = new URL(endpoint, window.location.origin);
         if (query) url.searchParams.set('query', query);
         const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
         return await res.json();
     }
 
     function renderDropdown(searchableEl, items, type) {
         const dd = qs('.ar-dropdown', searchableEl);
         if (!dd) return;
 
         if (!items || items.length === 0) {
             dd.innerHTML = `<div class="px-4 py-3 text-xs font-semibold text-slate-400">No results</div>`;
             dd.classList.remove('hidden');
             if (window.lucide?.createIcons) window.lucide.createIcons();
             return;
         }
 
         if (type === 'object') {
             dd.innerHTML = items.map((it) => {
                 const id = safeStr(it.id);
                 const name = safeStr(it.name);
                 return `
                     <div class="ar-dropdown-item" data-value="${name.replace(/"/g, '&quot;')}" data-id="${id}">
                         <div class="text-xs font-semibold text-slate-700">${name}</div>
                         <div class="ar-dropdown-code">#${id}</div>
                     </div>
                 `;
             }).join('');
         } else {
             dd.innerHTML = items.map((it) => {
                 const v = safeStr(it);
                 return `
                     <div class="ar-dropdown-item" data-value="${v.replace(/"/g, '&quot;')}">
                         <div class="text-xs font-semibold text-slate-700">${v}</div>
                         <div class="ar-dropdown-code">agent</div>
                     </div>
                 `;
             }).join('');
         }
 
         dd.classList.remove('hidden');
         if (window.lucide?.createIcons) window.lucide.createIcons();
     }
 
     function wireSearchable(searchableEl) {
         const endpoint = searchableEl.dataset.endpoint || '';
         const type = searchableEl.dataset.type || 'text';
         const inputId = searchableEl.dataset.inputId || '';
         const defaultLabel = searchableEl.dataset.defaultLabel || '';
         const input = document.getElementById(inputId);
         const toggle = qs('.ar-toggle', searchableEl);
         const dd = qs('.ar-dropdown', searchableEl);
 
         if (!input || !dd || !endpoint) return;
 
         const load = debounce(async () => {
             const q = safeStr(input.value || '').trim();
             const query = (defaultLabel && q === defaultLabel) ? '' : q;
             try {
                 const items = await fetchOptions(endpoint, query);
                 renderDropdown(searchableEl, items, type);
             } catch (e) {
                 renderDropdown(searchableEl, [], type);
             }
         }, 250);
 
         input.addEventListener('input', () => load());
         input.addEventListener('focus', () => load());
 
         if (toggle) {
             toggle.addEventListener('click', () => {
                 const isHidden = dd.classList.contains('hidden');
                 closeAllDropdowns();
                 if (isHidden) load();
             });
         }
 
         dd.addEventListener('click', (e) => {
             const row = e.target?.closest?.('.ar-dropdown-item');
             if (!row) return;
 
             const value = row.dataset.value || '';
             input.value = value || defaultLabel || '';
 
             const id = row.dataset.id || '';
             if (type === 'object' && els.customerId && inputId === 'ar-customer') {
                 els.customerId.value = id;
             }
 
             dd.classList.add('hidden');
         });
     }
 
     function getDateLabelAndValue() {
         const type = els.dateType ? els.dateType.value : 'annual';
         if (type === 'annual') {
             const v = safeStr(document.getElementById('ar-annual')?.value || '');
             return { label: 'Annual', value: v ? v : '—' };
         }
         if (type === 'monthly') {
             const v = safeStr(document.getElementById('ar-monthly')?.value || '');
             return { label: 'Monthly', value: v ? v : '—' };
         }
         if (type === 'as-of') {
             const v = safeStr(document.getElementById('ar-as-of')?.value || '');
             return { label: 'As of', value: v ? v : '—' };
         }
         const from = safeStr(document.getElementById('ar-from')?.value || '');
         const to = safeStr(document.getElementById('ar-to')?.value || '');
         return { label: 'From TO', value: `${from || '—'} to ${to || '—'}` };
     }
 
    function formatAmount(v) {
        const num = Number(v || 0);
        return num.toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function getPrintPayload() {
        const type = els.dateType ? els.dateType.value : 'annual';
        const customerName = safeStr(document.getElementById('ar-customer')?.value || 'Select All').trim() || 'Select All';
        const agentName = safeStr(document.getElementById('ar-agent')?.value || 'Select All').trim() || 'Select All';
        const payload = {
            date_type: type,
            customer_id: safeStr(els.customerId?.value || '').trim(),
            customer: customerName,
            agent: agentName,
        };

        if (type === 'annual') {
            payload.date = safeStr(document.getElementById('ar-annual')?.value || '').trim();
        } else if (type === 'monthly') {
            payload.date = safeStr(document.getElementById('ar-monthly')?.value || '').trim();
        } else if (type === 'as-of') {
            payload.date = safeStr(document.getElementById('ar-as-of')?.value || '').trim();
        } else {
            payload.date_from = safeStr(document.getElementById('ar-from')?.value || '').trim();
            payload.date_to = safeStr(document.getElementById('ar-to')?.value || '').trim();
        }

        return payload;
    }

    async function fetchPrintData() {
        if (!printUrl) throw new Error('Print route is not configured.');

        const res = await fetch(printUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(getPrintPayload()),
        });

        const json = await res.json();
        if (!res.ok || !json || json.success !== true) {
            throw new Error(json?.message || 'Failed to load accounts receivable print data.');
        }

        return json;
    }

    function renderPrintArea(data) {
        if (els.printDateType) els.printDateType.textContent = safeStr(data.date_type_label || 'Date Type');
        if (els.printDateValue) els.printDateValue.textContent = safeStr(data.date_value_label || 'Date');
        if (!els.printSections) return;

        const groups = Array.isArray(data.groups) ? data.groups : [];
        if (groups.length === 0) {
            els.printSections.innerHTML = `<div class="ar-print-empty">No accounts receivable found.</div>`;
            return;
        }

        els.printSections.innerHTML = groups.map((group) => {
            const salesman = safeStr(group.salesman || 'Unassigned');
            const customers = Array.isArray(group.customers) ? group.customers : [];
            const rows = customers.map((row) => `
                <tr>
                    <td class="ar-col-customer">${escapeHtml(row.customer_name || '')}</td>
                    <td class="ar-col-total">${formatAmount(row.total_credit || 0)}</td>
                </tr>
            `).join('');

            return `
                <section class="ar-agent-section">
                    <div class="ar-salesman-line">Salesman:<span>${escapeHtml(salesman)}</span></div>
                    <table class="ar-print-table">
                        <thead>
                            <tr>
                                <th>Customer Name</th>
                                <th>Total Credit</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                    <div class="ar-agent-total">
                        <span class="ar-agent-total-label">Agent Total</span>
                        <span>${formatAmount(group.agent_total || 0)}</span>
                    </div>
                </section>
            `;
        }).join('');
     }
 
     function init() {
         if (els.dateType) {
             renderDateInputs(els.dateType.value);
             els.dateType.addEventListener('change', () => renderDateInputs(els.dateType.value));
         }
 
         qsa('.ar-searchable').forEach((el) => wireSearchable(el));
 
         document.addEventListener('click', (e) => {
             if (e.target.closest('.ar-searchable')) return;
             closeAllDropdowns();
         });
 
         if (els.printBtn) {
            els.printBtn.addEventListener('click', async () => {
                 closeAllDropdowns();
                const originalHtml = els.printBtn.innerHTML;
                els.printBtn.disabled = true;
                els.printBtn.innerHTML = '<span>Loading...</span>';

                try {
                    const data = await fetchPrintData();
                    renderPrintArea(data);
                    window.setTimeout(() => window.print(), 80);
                } catch (e) {
                    window.alert(e.message || 'Failed to load accounts receivable data.');
                } finally {
                    els.printBtn.disabled = false;
                    els.printBtn.innerHTML = originalHtml;
                    if (window.lucide?.createIcons) window.lucide.createIcons();
                }
             });
         }
 
         if (window.lucide?.createIcons) window.lucide.createIcons();
     }
 
     init();
 })();
