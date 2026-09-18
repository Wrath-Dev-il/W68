<!-- W68_GLOBAL_SOA_AUTO_MODAL_20260918 -->
<div id="customer-soa-auto-modal" class="fixed inset-0 z-[1250] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-950/65 backdrop-blur-sm" onclick="closeSoaAutoModal()"></div>

    <div class="modal-animate-in relative w-full max-w-2xl overflow-hidden rounded-3xl bg-white shadow-2xl">
        <div class="flex items-center justify-between bg-maroon px-6 py-5 text-white">
            <div>
                <p class="text-[9px] font-black uppercase tracking-[.3em] text-gold">Global Payment Reminder</p>
                <h3 class="mt-1 text-xl font-black">Statement of Account Configuration</h3>
            </div>
            <button type="button" onclick="closeSoaAutoModal()" class="rounded-xl p-2 text-white/70 transition hover:bg-white/10 hover:text-white" aria-label="Close SOA Auto modal">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>

        <div class="space-y-5 p-6">
            <div class="rounded-2xl border border-amber-100 bg-amber-50 p-5">
                <div class="flex items-start gap-3">
                    <i data-lucide="users" class="mt-0.5 h-5 w-5 text-amber-700"></i>
                    <div>
                        <p class="text-[9px] font-black uppercase tracking-widest text-amber-700">Applies To All Linked Customer Accounts</p>
                        <p class="mt-1 text-xs font-bold leading-relaxed text-slate-700">This is one global SOA(AUTO) setting. Every customer linked to a Pricelist login uses this same lead time, while each customer keeps their own Terms to calculate their own due date.</p>
                    </div>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Linked Accounts</p>
                    <p id="soa-auto-linked-count" class="mt-1 text-xl font-black text-slate-800">—</p>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Valid Email</p>
                    <p id="soa-auto-email-count" class="mt-1 text-xl font-black text-slate-800">—</p>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Numeric Terms</p>
                    <p id="soa-auto-terms-count" class="mt-1 text-xl font-black text-slate-800">—</p>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 p-5">
                <label class="flex cursor-pointer items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-black text-slate-800">Enable SOA(AUTO) for all linked customers</p>
                        <p class="mt-1 text-[10px] font-semibold text-slate-400">Only finalized/closed invoices that still have an outstanding balance are eligible. Each invoice is automatically reminded once.</p>
                    </div>
                    <input id="soa-auto-enabled" type="checkbox" class="h-5 w-5 accent-[#4b0000]">
                </label>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="soa-auto-lead-value" class="mb-1.5 ml-1 block text-[9px] font-black uppercase tracking-widest text-slate-400">Send Before Customer Terms</label>
                        <input id="soa-auto-lead-value" type="number" min="1" max="525600" placeholder="e.g. 14" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-black outline-none transition focus:border-maroon focus:ring-4 focus:ring-maroon/5">
                    </div>
                    <div>
                        <label for="soa-auto-lead-unit" class="mb-1.5 ml-1 block text-[9px] font-black uppercase tracking-widest text-slate-400">Type</label>
                        <select id="soa-auto-lead-unit" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-black outline-none transition focus:border-maroon focus:ring-4 focus:ring-maroon/5">
                            <option value="">Select type</option>
                            <option value="minutes">Minute(s)</option>
                            <option value="days">Day(s)</option>
                            <option value="months">Month(s)</option>
                        </select>
                    </div>
                </div>

                <div class="mt-4 rounded-xl bg-slate-50 px-4 py-3">
                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Example</p>
                    <p id="soa-auto-example" class="mt-1 text-xs font-bold leading-relaxed text-slate-600">Enable SOA(AUTO) and enter a lead time to see an example.</p>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl border border-slate-100 p-4">
                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Last Automatic SOA Sent</p>
                    <p id="soa-auto-last-sent" class="mt-1 text-xs font-black text-slate-700">Never</p>
                </div>
                <div class="rounded-2xl border border-slate-100 p-4">
                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Mail Status</p>
                    <p id="soa-auto-mail-status" class="mt-1 text-xs font-black text-slate-700">Checking...</p>
                </div>
            </div>

            <div id="soa-auto-error" class="hidden rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-bold text-red-700"></div>

            <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
                <button type="button" onclick="closeSoaAutoModal()" class="rounded-xl px-5 py-3 text-[10px] font-black uppercase tracking-widest text-slate-400 hover:bg-slate-50">Cancel</button>
                <button id="soa-auto-save-btn" type="button" onclick="saveSoaAutoConfiguration()" class="inline-flex items-center gap-2 rounded-xl bg-maroon px-7 py-3 text-[10px] font-black uppercase tracking-widest text-white shadow-lg transition hover:bg-maroon-800 disabled:cursor-not-allowed disabled:opacity-50">
                    <i data-lucide="save" class="h-4 w-4"></i>
                    <span>Save Global SOA(AUTO)</span>
                </button>
            </div>
        </div>
    </div>
</div>
