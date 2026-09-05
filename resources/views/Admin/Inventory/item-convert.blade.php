@push('styles')
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');

        #page-item-convert {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .text-maroon { color: #800000; }
        .bg-maroon { background-color: #800000; }
        .border-maroon { border-color: #800000; }
        .text-gold { color: #FFD700; }
        .bg-gold { background-color: #FFD700; }

        .animate-fade-in {
            animation: fadeIn 0.4s ease-out forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animate-slide-up {
            animation: slideUp 0.35s ease-out forwards;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px) scale(0.96); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .col-search-input {
            padding: 0.375rem 0.75rem;
            background-color: #f8fafc;
            border: 1px solid #f1f5f9;
            border-radius: 0.5rem;
            outline: none;
            transition: all 0.2s;
            font-size: 10px;
            width: 100%;
        }

        .col-search-input:focus {
            box-shadow: 0 0 0 2px rgba(128, 0, 0, 0.1);
            border-color: rgba(128, 0, 0, 0.2);
        }

        .custom-scrollbar::-webkit-scrollbar {
            height: 6px;
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #e2e8f0;
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #cbd5e1;
        }

        .stat-card {
            transition: all 0.25s ease;
            cursor: pointer;
        }

        .stat-card:hover {
            transform: scale(1.02);
            box-shadow: 0 12px 25px rgba(128, 0, 0, 0.12);
        }

        .stat-card.active-card {
            border-color: #800000 !important;
            box-shadow: 0 0 0 2px rgba(128, 0, 0, 0.15);
        }

        .review-side {
            min-height: 260px;
        }

        .field-label {
            font-size: 10px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.25rem;
        }

        .field-value {
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
            padding: 0.5rem 0.75rem;
            background: #f8fafc;
            border-radius: 0.5rem;
            border: 1px solid #f1f5f9;
        }

        .modal-overlay {
            background: transparent;
        }
    </style>
@endpush

@section('item_convert_content')

<div id="page-item-convert" class="space-y-6 animate-fade-in p-6">

    <!-- HEADER -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-bold text-slate-800 tracking-tight">Review Convert Requests</h2>
            <p class="text-xs text-slate-400 font-medium">Admin panel — review and approve/reject warehouse conversion requests.</p>
        </div>
        <div class="flex items-center space-x-3">
            <div id="auto-accept-badge" class="hidden px-3 py-1.5 bg-emerald-50 border border-emerald-200 rounded-lg text-[10px] font-bold text-emerald-600 flex items-center space-x-1.5">
                <i data-lucide="bot" class="w-3.5 h-3.5"></i>
                <span>Auto</span>
            </div>
            <button onclick="openModal('auto-accept-modal')" class="px-3 py-2 bg-maroon text-gold text-[10px] font-bold rounded-xl hover:bg-maroon/90 transition-all shadow-md flex items-center space-x-1.5">
                <i data-lucide="settings" class="w-3.5 h-3.5"></i>
                <span>Auto Accept</span>
            </button>
        </div>
    </div>

    <!-- AUTO ACCEPT SUMMARY MODAL -->
    <div id="auto-accept-summary-modal" class="hidden fixed inset-0 z-[200] flex items-center justify-center p-4">
        <div class="modal-overlay absolute inset-0" onclick="closeSummaryModal()"></div>
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-lg animate-slide-up overflow-hidden">
            <div class="bg-emerald-500 px-6 py-5 flex items-center space-x-3">
                <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center">
                    <i data-lucide="bot" class="w-5 h-5 text-white"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-white">Auto-Accept Triggered</h3>
                    <p class="text-[10px] text-white/80">Pending requests have been confirmed automatically.</p>
                </div>
                <button onclick="closeSummaryModal()" class="ml-auto text-white/60 hover:text-white">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto custom-scrollbar max-h-64">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-slate-400 text-[10px] font-bold uppercase tracking-widest border-b border-slate-100">
                                <th class="pb-3 pr-3">Source Code</th>
                                <th class="pb-3 pr-3">Target Code</th>
                                <th class="pb-3 pr-3">Qty</th>
                                <th class="pb-3">Requestor</th>
                            </tr>
                        </thead>
                        <tbody id="summary-items-tbody" class="divide-y divide-slate-50 text-xs text-slate-600 font-medium">
                        </tbody>
                    </table>
                </div>
                <div id="summary-empty" class="hidden text-center py-6 text-slate-400 text-xs font-medium">No pending requests to auto-confirm.</div>
                <button onclick="closeSummaryModal()" class="mt-4 w-full py-3 bg-emerald-500 text-white text-xs font-bold rounded-xl hover:bg-emerald-600 transition-all">OK</button>
            </div>
        </div>
    </div>

    <!-- AUTO ACCEPT CONFIG MODAL -->
    <div id="auto-accept-modal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="modal-overlay absolute inset-0" onclick="closeModal('auto-accept-modal')"></div>
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md animate-slide-up p-6">
            <button onclick="closeModal('auto-accept-modal')" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
            <div class="flex items-center space-x-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-maroon/10 flex items-center justify-center">
                    <i data-lucide="bot" class="w-5 h-5 text-maroon"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-800">Auto-Accept Configuration</h3>
                    <p class="text-[10px] text-slate-400 font-medium">Philippines Time (Asia/Manila)</p>
                </div>
                <div class="ml-auto text-right">
                    <p class="text-[10px] text-slate-400 font-medium">Current PH Time</p>
                    <p id="auto-current-ph-time" class="text-xs font-bold text-maroon">--:-- --</p>
                </div>
            </div>

            <div class="space-y-5">
                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-100">
                    <div>
                        <p class="text-xs font-bold text-slate-700">Auto-Accept</p>
                        <p class="text-[10px] text-slate-400">Confirm pending requests automatically</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="auto-accept-toggle" class="sr-only peer">
                        <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500"></div>
                    </label>
                </div>

                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-100">
                    <div>
                        <p class="text-xs font-bold text-slate-700">Schedule Active</p>
                        <p class="text-[10px] text-slate-400">Let the schedule trigger auto-accept</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="auto-schedule-active" class="sr-only peer">
                        <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500"></div>
                    </label>
                </div>

                <!-- Mode Selector -->
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Trigger Mode</p>
                    <div class="flex bg-slate-100 rounded-xl p-1">
                        <button id="mode-daily" onclick="setAutoAcceptMode('daily')" class="flex-1 py-2 text-[10px] font-bold rounded-lg transition-all bg-white text-slate-800 shadow-sm">Daily Schedule</button>
                        <button id="mode-inactivity" onclick="setAutoAcceptMode('inactivity')" class="flex-1 py-2 text-[10px] font-bold rounded-lg transition-all text-slate-400">Inactivity Period</button>
                    </div>
                </div>

                <!-- Daily Schedule Controls -->
                <div id="auto-controls-daily">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Schedule Time (PH)</p>
                    <div class="flex items-center space-x-3">
                        <input type="time" id="auto-accept-time" value="18:00" class="flex-1 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-700 outline-none focus:border-maroon focus:ring-2 focus:ring-maroon/10">
                        <span class="text-[10px] text-slate-400 font-medium">Daily</span>
                    </div>
                </div>

                <!-- Inactivity Period Controls -->
                <div id="auto-controls-inactivity" class="hidden">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Auto-accept after no admin logout for</p>
                    <div class="flex items-center space-x-3">
                        <input type="number" id="auto-interval-value" min="1" value="1" class="w-24 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-700 outline-none focus:border-maroon focus:ring-2 focus:ring-maroon/10">
                        <select id="auto-interval-unit" class="flex-1 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-700 outline-none focus:border-maroon focus:ring-2 focus:ring-maroon/10">
                            <option value="minutes">Minutes</option>
                            <option value="hours">Hours</option>
                            <option value="days">Days</option>
                            <option value="weeks">Weeks</option>
                            <option value="months">Months</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-100">
                    <div>
                        <p class="text-xs font-bold text-slate-700">Exclude Weekends</p>
                        <p class="text-[10px] text-slate-400">Skip auto-accept on Saturday & Sunday</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="auto-exclude-weekends" class="sr-only peer" checked>
                        <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500"></div>
                    </label>
                </div>

                <div id="auto-accept-last-run" class="hidden text-center">
                    <p class="text-[10px] text-slate-400"><span class="font-medium">Last auto-accept run:</span> <span id="auto-last-run-text" class="text-slate-600"></span></p>
                </div>

                <button onclick="saveAutoAccept()" class="w-full py-3 bg-maroon text-gold text-xs font-bold rounded-xl hover:bg-maroon/90 transition-all shadow-md flex items-center justify-center space-x-2">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>Save Configuration</span>
                </button>

                <div id="auto-accept-modal-status" class="text-center text-[10px] text-emerald-600 font-medium hidden">
                    <i data-lucide="check-circle" class="w-3 h-3 inline-block"></i>
                    <span id="auto-accept-modal-status-text">Settings saved</span>
                </div>
            </div>
        </div>
    </div>

    <!-- SUMMARY CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="stat-card bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between group active-card" id="card-pending">
            <div class="space-y-0.5 min-w-0">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Conversion Request</span>
                <h3 id="stat-pending" class="text-2xl font-extrabold text-slate-800 tracking-tight truncate">0</h3>
                <div class="flex items-center gap-1.5 mt-1">
                    <span class="text-amber-500 font-bold text-[10px] flex items-center gap-0.5">
                        <i data-lucide="clock" class="w-3 h-3"></i>
                        awaiting review
                    </span>
                </div>
            </div>
            <div class="w-11 h-11 rounded-xl bg-maroon flex items-center justify-center shadow-inner group-hover:scale-105 transition-transform duration-300 flex-shrink-0">
                <i data-lucide="clipboard-list" class="w-5 h-5 text-gold"></i>
            </div>
        </div>
        <div class="stat-card bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between group" id="card-converted">
            <div class="space-y-0.5 min-w-0">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Converted Items</span>
                <h3 id="stat-converted" class="text-2xl font-extrabold text-slate-800 tracking-tight truncate">0</h3>
                <div class="flex items-center gap-1.5 mt-1">
                    <span class="text-emerald-500 font-bold text-[10px] flex items-center gap-0.5">
                        <i data-lucide="check-circle" class="w-3 h-3"></i>
                        approved
                    </span>
                </div>
            </div>
            <div class="w-11 h-11 rounded-xl bg-maroon flex items-center justify-center shadow-inner group-hover:scale-105 transition-transform duration-300 flex-shrink-0">
                <i data-lucide="check-check" class="w-5 h-5 text-gold"></i>
            </div>
        </div>
        <div class="stat-card bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between group" id="card-rejected">
            <div class="space-y-0.5 min-w-0">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Rejected Items</span>
                <h3 id="stat-rejected" class="text-2xl font-extrabold text-slate-800 tracking-tight truncate">0</h3>
                <div class="flex items-center gap-1.5 mt-1">
                    <span class="text-red-500 font-bold text-[10px] flex items-center gap-0.5">
                        <i data-lucide="x-circle" class="w-3 h-3"></i>
                        rejected
                    </span>
                </div>
            </div>
            <div class="w-11 h-11 rounded-xl bg-maroon flex items-center justify-center shadow-inner group-hover:scale-105 transition-transform duration-300 flex-shrink-0">
                <i data-lucide="ban" class="w-5 h-5 text-gold"></i>
            </div>
        </div>
        <div class="stat-card bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between group" id="card-total">
            <div class="space-y-0.5 min-w-0">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Total Items</span>
                <h3 id="stat-total" class="text-2xl font-extrabold text-slate-800 tracking-tight truncate">0</h3>
                <div class="flex items-center gap-1.5 mt-1">
                    <span class="text-blue-500 font-bold text-[10px] flex items-center gap-0.5">
                        <i data-lucide="package" class="w-3 h-3"></i>
                        all requests
                    </span>
                </div>
            </div>
            <div class="w-11 h-11 rounded-xl bg-maroon flex items-center justify-center shadow-inner group-hover:scale-105 transition-transform duration-300 flex-shrink-0">
                <i data-lucide="shuffle" class="w-5 h-5 text-gold"></i>
            </div>
        </div>
    </div>

    <!-- REQUESTS TABLE -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-50 bg-slate-50/50 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <button onclick="switchStatus('pending')" id="tab-pending" class="text-xs font-bold uppercase tracking-widest px-4 py-2 rounded-xl transition-all bg-maroon text-gold shadow-sm">Pending</button>
                <button onclick="switchStatus('converted')" id="tab-converted" class="text-xs font-bold uppercase tracking-widest px-4 py-2 rounded-xl transition-all text-slate-400 hover:text-slate-600">Converted</button>
                <button onclick="switchStatus('rejected')" id="tab-rejected" class="text-xs font-bold uppercase tracking-widest px-4 py-2 rounded-xl transition-all text-slate-400 hover:text-slate-600">Rejected</button>
            </div>
            <span id="table-subtitle" class="text-[10px] text-slate-400 font-medium">Review each request below</span>
        </div>
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead id="table-thead">
                    <tr id="header-row" class="bg-slate-50 text-slate-400 text-[10px] font-bold uppercase tracking-widest">
                        <th class="py-5 px-6">Product Code</th>
                        <th class="py-5 px-6">Converted Qty</th>
                        <th class="py-5 px-6">Requestor</th>
                        <th class="py-5 px-6">Date</th>
                        <th class="py-5 px-6 text-center">Action</th>
                    </tr>
                    <tr id="search-row" class="bg-white border-b border-slate-100">
                        <th class="p-2 px-6"><input type="text" id="search-code" onkeyup="searchRequests()" class="col-search-input" placeholder="Search Code..."></th>
                        <th id="search-target-th" class="p-2 px-6 hidden"><input type="text" id="search-target" onkeyup="searchRequests()" class="col-search-input" placeholder="Search Target..."></th>
                        <th class="p-2 px-6"></th>
                        <th class="p-2 px-6"><input type="text" id="search-requestor" onkeyup="searchRequests()" class="col-search-input" placeholder="Search Requestor..."></th>
                        <th class="p-2 px-6"></th>
                        <th id="search-action-th" class="p-2 px-6"></th>
                    </tr>
                </thead>
                <tbody id="requests-tbody" class="divide-y divide-slate-100 text-xs text-slate-600 font-medium">
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100 bg-slate-50/30 flex items-center justify-between flex-shrink-0">
            <span id="page-info" class="text-xs text-slate-500 font-medium">Page 1 of 1</span>
            <div class="flex items-center space-x-2">
                <button id="prev-page" onclick="changePage(-1)" class="px-3 py-1.5 text-xs border border-slate-200 rounded-lg hover:bg-slate-100 disabled:opacity-30 transition-all" disabled>Prev</button>
                <button id="next-page" onclick="changePage(1)" class="px-3 py-1.5 text-xs border border-slate-200 rounded-lg hover:bg-slate-100 transition-all">Next</button>
            </div>
        </div>
    </div>

</div>

<!-- REVIEW MODAL -->
<div id="review-modal" class="fixed inset-0 z-50 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div onclick="toggleModal('review-modal', false)" class="fixed inset-0 modal-overlay"></div>
        <div class="animate-slide-up relative bg-white rounded-3xl shadow-2xl w-full max-w-5xl overflow-hidden flex flex-col max-h-[90vh]">
            <div class="bg-gradient-to-r from-maroon-900 to-maroon-950 px-8 py-5 flex items-center justify-between">
                <div class="flex items-center space-x-3 text-white">
                    <i data-lucide="shuffle" class="w-6 h-6 text-gold"></i>
                    <h3 class="text-sm font-bold uppercase tracking-widest">Review Conversion Request</h3>
                </div>
                <button onclick="toggleModal('review-modal', false)" class="text-white/70 hover:text-white p-2 hover:bg-white/10 rounded-full transition-all">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div class="flex-1 p-8 overflow-y-auto">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <!-- LEFT SIDE - FROM (Source) -->
                    <div class="review-side bg-slate-50 rounded-2xl p-6 border border-slate-100">
                        <div class="flex items-center space-x-2 mb-5 pb-3 border-b border-slate-200">
                            <div class="w-8 h-8 rounded-lg bg-maroon flex items-center justify-center">
                                <i data-lucide="package" class="w-4 h-4 text-gold"></i>
                            </div>
                            <span class="text-xs font-bold text-slate-600 uppercase tracking-widest">From</span>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <p class="field-label">Product Code</p>
                                <p id="rev-source-code" class="field-value">---</p>
                            </div>
                            <div>
                                <p class="field-label">Part No#</p>
                                <p id="rev-source-part" class="field-value">---</p>
                            </div>
                            <div>
                                <p class="field-label">Description</p>
                                <p id="rev-source-desc" class="field-value">---</p>
                            </div>
                            <div>
                                <p class="field-label">Application#</p>
                                <p id="rev-source-app" class="field-value">---</p>
                            </div>
                            <div>
                                <p class="field-label">Position</p>
                                <p id="rev-source-pos" class="field-value">---</p>
                            </div>
                            <div>
                                <p class="field-label">Available Qty</p>
                                <p id="rev-source-avail" class="field-value">0</p>
                            </div>
                            <div>
                                <p class="field-label">Converted Qty</p>
                                <p id="rev-source-converted" class="field-value font-black text-lg text-amber-600">0</p>
                            </div>
                            <div>
                                <p class="field-label">New Qty (Total Plus Deduction)</p>
                                <p id="rev-source-new" class="field-value font-black text-lg text-red-600">0</p>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT SIDE - TO (Target) -->
                    <div class="review-side bg-slate-50 rounded-2xl p-6 border border-slate-100">
                        <div class="flex items-center space-x-2 mb-5 pb-3 border-b border-slate-200">
                            <div class="w-8 h-8 rounded-lg bg-maroon flex items-center justify-center">
                                <i data-lucide="refresh-cw" class="w-4 h-4 text-gold"></i>
                            </div>
                            <span class="text-xs font-bold text-slate-600 uppercase tracking-widest">To</span>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <p class="field-label">Product Code</p>
                                <p id="rev-target-code" class="field-value">---</p>
                            </div>
                            <div>
                                <p class="field-label">Part No#</p>
                                <p id="rev-target-part" class="field-value">---</p>
                            </div>
                            <div>
                                <p class="field-label">Description</p>
                                <p id="rev-target-desc" class="field-value">---</p>
                            </div>
                            <div>
                                <p class="field-label">Application#</p>
                                <p id="rev-target-app" class="field-value">---</p>
                            </div>
                            <div>
                                <p class="field-label">Position</p>
                                <p id="rev-target-pos" class="field-value">---</p>
                            </div>
                            <div>
                                <p class="field-label">Available Qty</p>
                                <p id="rev-target-avail" class="field-value">0</p>
                            </div>
                            <div>
                                <p class="field-label">Converted Qty</p>
                                <p id="rev-target-converted" class="field-value font-black text-lg text-amber-600">0</p>
                            </div>
                            <div>
                                <p class="field-label">New Qty (Total Plus Addition)</p>
                                <p id="rev-target-new" class="field-value font-black text-lg text-emerald-600">0</p>
                            </div>
                        </div>
                        <div class="pt-4 flex gap-3">
                            <button onclick="openDecisionModal('confirm')" class="flex-1 py-3.5 bg-emerald-500 text-white text-[10px] font-bold rounded-xl shadow-lg hover:shadow-emerald-500/20 transition-all uppercase tracking-widest flex items-center justify-center space-x-2">
                                <i data-lucide="check" class="w-4 h-4"></i>
                                <span>Confirm</span>
                            </button>
                            <button onclick="openDecisionModal('reject')" class="flex-1 py-3.5 bg-red-500 text-white text-[10px] font-bold rounded-xl shadow-lg hover:shadow-red-500/20 transition-all uppercase tracking-widest flex items-center justify-center space-x-2">
                                <i data-lucide="x" class="w-4 h-4"></i>
                                <span>Reject</span>
                            </button>
                            <button onclick="toggleModal('review-modal', false)" class="flex-1 py-3.5 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-xl hover:bg-slate-200 transition-all uppercase tracking-widest">Cancel</button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- DECISION CONFIRM MODAL (shared for Confirm & Reject) -->
<div id="decision-modal" class="fixed inset-0 z-[100] overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 modal-overlay"></div>
        <div class="animate-slide-up relative bg-white rounded-3xl shadow-2xl max-w-sm w-full p-8 text-center space-y-6">
            <div id="decision-icon" class="w-20 h-20 rounded-full bg-amber-50 mx-auto flex items-center justify-center shadow-inner">
                <i data-lucide="alert-triangle" class="w-10 h-10 text-amber-500"></i>
            </div>
            <div>
                <h3 id="decision-title" class="text-lg font-black text-slate-800 tracking-tight">Confirm Decision?</h3>
                <p id="decision-desc" class="text-xs text-slate-400 font-medium leading-relaxed mt-2">This action cannot be undone.</p>
            </div>
            <div class="flex items-center gap-3 pt-2">
                <button onclick="toggleModal('decision-modal', false)" class="flex-1 py-3.5 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-xl hover:bg-slate-200 transition-all uppercase tracking-widest">No, Cancel</button>
                <button id="decision-yes-btn" class="flex-1 py-3.5 text-white text-[10px] font-bold rounded-xl shadow-lg transition-all uppercase tracking-widest">Yes</button>
            </div>
        </div>
    </div>
</div>

<!-- SUCCESS MODAL (shared for Confirm & Reject) -->
<div id="success-modal" class="fixed inset-0 z-[110] overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div onclick="toggleModal('success-modal', false)" class="fixed inset-0 bg-emerald-900/20 backdrop-blur-sm"></div>
        <div id="success-modal-box" class="animate-slide-up relative bg-white rounded-3xl shadow-2xl max-w-sm w-full p-10 text-center space-y-6 border-b-8 border-emerald-500">
            <div id="success-icon-wrap" class="w-24 h-24 rounded-full bg-emerald-50 mx-auto flex items-center justify-center shadow-inner animate-pulse">
                <i id="success-icon" data-lucide="check-circle" class="w-12 h-12 text-emerald-500"></i>
            </div>
            <div>
                <h3 id="success-title" class="text-xl font-black text-slate-800 tracking-tight">Done!</h3>
                <p id="success-desc" class="text-xs text-slate-400 font-medium leading-relaxed mt-2">The request has been processed.</p>
            </div>
            <button onclick="toggleModal('success-modal', false)" class="w-full py-4 text-white text-[10px] font-bold rounded-xl shadow-lg transition-all uppercase tracking-widest" id="success-btn">Confirm & Close</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
    <script>
        var requests = [];
        var selectedRequest = null;
        var currentPage = 1;
        var lastPage = 1;
        var decisionAction = '';
        var currentStatus = 'pending';

        document.addEventListener('DOMContentLoaded', function () {
            loadRequests();
            loadAutoAcceptConfig();
        });

        function loadRequests() {
            var tbody = document.getElementById('requests-tbody');
            tbody.innerHTML = '<tr><td colspan="6" class="py-10 text-center text-slate-400 text-xs font-medium">Loading requests...</td></tr>';

            var params = '?page=' + currentPage + '&status=' + currentStatus;
            var s = buildSearchParams();
            if (s) params += '&' + s;

            fetch('{{ route("admin.item-convert.data") }}' + params)
                .then(function (r) { return r.text().then(function (t) { return JSON.parse(t); }); })
                .then(function (res) {
                    if (!res.success) {
                        tbody.innerHTML = '<tr><td colspan="6" class="py-10 text-center text-slate-400 text-xs font-medium">Failed to load.</td></tr>';
                        return;
                    }
                    requests = res.items || [];
                    if (res.pagination) {
                        currentPage = res.pagination.current_page;
                        lastPage = res.pagination.last_page;
                        updatePagination();
                    }
                    renderTable(requests);
                    updateStats(res.stats);

                    // Only show auto-accept summary when viewing pending
                    if (currentStatus === 'pending' && res.auto_accepted) {
                        showAutoAcceptSummary(res.auto_accepted_items || []);
                    }
                })
                .catch(function () {
                    tbody.innerHTML = '<tr><td colspan="6" class="py-10 text-center text-slate-400 text-xs font-medium">Error loading requests.</td></tr>';
                });
        }

        function renderTable(items) {
            var tbody = document.getElementById('requests-tbody');
            tbody.innerHTML = '';
            if (!items || items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="py-10 text-center text-slate-400 text-xs font-medium">No ' + currentStatus + ' requests found.</td></tr>';
                return;
            }
            // Update header columns based on status
            var headerRow = document.getElementById('header-row');
            var searchRow = document.getElementById('search-row');

            if (currentStatus === 'converted') {
                headerRow.innerHTML =
                    '<th class="py-5 px-6">Source Code</th>' +
                    '<th class="py-5 px-6">Target Code</th>' +
                    '<th class="py-5 px-6">Qty</th>' +
                    '<th class="py-5 px-6">Requestor</th>' +
                    '<th class="py-5 px-6">Date</th>';
                searchRow.innerHTML =
                    '<th class="p-2 px-6"><input type="text" id="search-code" onkeyup="searchRequests()" class="col-search-input" placeholder="Search Source..."></th>' +
                    '<th class="p-2 px-6"><input type="text" id="search-target" onkeyup="searchRequests()" class="col-search-input" placeholder="Search Target..."></th>' +
                    '<th class="p-2 px-6"></th>' +
                    '<th class="p-2 px-6"><input type="text" id="search-requestor" onkeyup="searchRequests()" class="col-search-input" placeholder="Search Requestor..."></th>' +
                    '<th class="p-2 px-6"></th>';
                document.getElementById('table-subtitle').textContent = 'View all converted requests';
            } else if (currentStatus === 'rejected') {
                headerRow.innerHTML =
                    '<th class="py-5 px-6">Source Code</th>' +
                    '<th class="py-5 px-6">Target Code</th>' +
                    '<th class="py-5 px-6">Qty</th>' +
                    '<th class="py-5 px-6">Requestor</th>' +
                    '<th class="py-5 px-6">Date</th>';
                searchRow.innerHTML =
                    '<th class="p-2 px-6"><input type="text" id="search-code" onkeyup="searchRequests()" class="col-search-input" placeholder="Search Source..."></th>' +
                    '<th class="p-2 px-6"><input type="text" id="search-target" onkeyup="searchRequests()" class="col-search-input" placeholder="Search Target..."></th>' +
                    '<th class="p-2 px-6"></th>' +
                    '<th class="p-2 px-6"><input type="text" id="search-requestor" onkeyup="searchRequests()" class="col-search-input" placeholder="Search Requestor..."></th>' +
                    '<th class="p-2 px-6"></th>';
                document.getElementById('table-subtitle').textContent = 'View all rejected requests';
            } else {
                headerRow.innerHTML =
                    '<th class="py-5 px-6">Product Code</th>' +
                    '<th class="py-5 px-6">Converted Qty</th>' +
                    '<th class="py-5 px-6">Requestor</th>' +
                    '<th class="py-5 px-6">Date</th>' +
                    '<th class="py-5 px-6 text-center">Action</th>';
                searchRow.innerHTML =
                    '<th class="p-2 px-6"><input type="text" id="search-code" onkeyup="searchRequests()" class="col-search-input" placeholder="Search Code..."></th>' +
                    '<th class="p-2 px-6"></th>' +
                    '<th class="p-2 px-6"><input type="text" id="search-requestor" onkeyup="searchRequests()" class="col-search-input" placeholder="Search Requestor..."></th>' +
                    '<th class="p-2 px-6"></th>' +
                    '<th class="p-2 px-6"></th>';
                document.getElementById('table-subtitle').textContent = 'Review each request below';
            }

            items.forEach(function (item, idx) {
                var tr = document.createElement('tr');
                tr.className = 'hover:bg-slate-50/50 transition-colors group';
                if (currentStatus === 'converted') {
                    tr.innerHTML =
                        '<td class="py-5 px-6 text-slate-700 font-bold">' + escapeHtml(item.source_code || '---') + '</td>' +
                        '<td class="py-5 px-6 text-slate-700 font-bold">' + escapeHtml(item.target_code || '---') + '</td>' +
                        '<td class="py-5 px-6"><span class="font-black text-emerald-600">' + (item.converted_qty || 0) + '</span></td>' +
                        '<td class="py-5 px-6 text-slate-500">' + escapeHtml(item.requestor) + '</td>' +
                        '<td class="py-5 px-6 text-slate-400 text-[10px]">' + escapeHtml(item.date || '---') + '</td>';
                } else if (currentStatus === 'rejected') {
                    tr.innerHTML =
                        '<td class="py-5 px-6 text-slate-700 font-bold">' + escapeHtml(item.source_code || '---') + '</td>' +
                        '<td class="py-5 px-6 text-slate-700 font-bold">' + escapeHtml(item.target_code || '---') + '</td>' +
                        '<td class="py-5 px-6"><span class="font-black text-red-500">' + (item.converted_qty || 0) + '</span></td>' +
                        '<td class="py-5 px-6 text-slate-500">' + escapeHtml(item.requestor) + '</td>' +
                        '<td class="py-5 px-6 text-slate-400 text-[10px]">' + escapeHtml(item.date || '---') + '</td>';
                } else {
                    tr.innerHTML =
                        '<td class="py-5 px-6 text-slate-700 font-bold">' + escapeHtml(item.product_code) + '</td>' +
                        '<td class="py-5 px-6"><span class="font-black text-amber-600">' + (item.converted_qty || 0) + '</span></td>' +
                        '<td class="py-5 px-6 text-slate-500">' + escapeHtml(item.requestor) + '</td>' +
                        '<td class="py-5 px-6 text-slate-400 text-[10px]">' + escapeHtml(item.date || '---') + '</td>' +
                        '<td class="py-5 px-6 text-center"><button onclick="openReviewModal(' + idx + ')" class="px-4 py-2 bg-maroon text-white text-[10px] font-bold rounded-xl hover:bg-maroon/90 transition-all shadow-md flex items-center space-x-1.5 mx-auto"><i data-lucide="eye" class="w-3.5 h-3.5"></i><span>Confirm</span></button></td>';
                }
                tbody.appendChild(tr);
            });
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }

        function openReviewModal(idx) {
            selectedRequest = requests[idx];
            if (!selectedRequest) return;

            // Source (from)
            document.getElementById('rev-source-code').textContent = selectedRequest.source_code || '---';
            document.getElementById('rev-source-part').textContent = selectedRequest.source_part || '---';
            document.getElementById('rev-source-desc').textContent = selectedRequest.source_desc || '---';
            document.getElementById('rev-source-app').textContent = selectedRequest.source_app || '---';
            document.getElementById('rev-source-pos').textContent = selectedRequest.source_pos || '---';
            var sourceAvail = parseInt(selectedRequest.source_avail || 0);
            var convQty = parseInt(selectedRequest.converted_qty || 0);
            document.getElementById('rev-source-avail').textContent = sourceAvail;
            document.getElementById('rev-source-converted').textContent = convQty;
            document.getElementById('rev-source-new').textContent = Math.max(0, sourceAvail - convQty);

            // Target (to)
            document.getElementById('rev-target-code').textContent = selectedRequest.target_code || '---';
            document.getElementById('rev-target-part').textContent = selectedRequest.target_part || '---';
            document.getElementById('rev-target-desc').textContent = selectedRequest.target_desc || '---';
            document.getElementById('rev-target-app').textContent = selectedRequest.target_app || '---';
            document.getElementById('rev-target-pos').textContent = selectedRequest.target_pos || '---';
            var targetAvail = parseInt(selectedRequest.target_avail || 0);
            document.getElementById('rev-target-avail').textContent = targetAvail;
            document.getElementById('rev-target-converted').textContent = convQty;
            document.getElementById('rev-target-new').textContent = targetAvail + convQty;

            toggleModal('review-modal', true);
        }

        function openDecisionModal(action) {
            decisionAction = action;
            toggleModal('review-modal', false);

            var title = document.getElementById('decision-title');
            var desc = document.getElementById('decision-desc');
            var yesBtn = document.getElementById('decision-yes-btn');
            var iconWrap = document.getElementById('decision-icon');

            if (action === 'confirm') {
                title.textContent = 'Confirm Conversion?';
                desc.textContent = 'This will permanently convert the source qty to the target product. Inventory will be updated accordingly.';
                yesBtn.className = 'flex-1 py-3.5 bg-emerald-500 text-white text-[10px] font-bold rounded-xl shadow-lg hover:shadow-emerald-500/20 transition-all uppercase tracking-widest';
                yesBtn.textContent = 'Yes, Confirm';
                iconWrap.innerHTML = '<i data-lucide="check-circle" class="w-10 h-10 text-emerald-500"></i>';
            } else {
                title.textContent = 'Reject Request?';
                desc.textContent = 'This will reject the conversion request. The requestor will be notified.';
                yesBtn.className = 'flex-1 py-3.5 bg-red-500 text-white text-[10px] font-bold rounded-xl shadow-lg hover:shadow-red-500/20 transition-all uppercase tracking-widest';
                yesBtn.textContent = 'Yes, Reject';
                iconWrap.innerHTML = '<i data-lucide="x-circle" class="w-10 h-10 text-red-500"></i>';
            }
            if (typeof lucide !== 'undefined') lucide.createIcons();
            toggleModal('decision-modal', true);
        }

        document.getElementById('decision-yes-btn')?.addEventListener('click', function () {
            toggleModal('decision-modal', false);
            fetch('{{ route("admin.item-convert.review") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ request_id: selectedRequest ? selectedRequest.id : null, action: decisionAction })
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    showSuccessModal(decisionAction);
                    loadRequests();
                } else {
                    alert(res.error || 'Action failed.');
                }
            })
            .catch(function () { alert('Request failed.'); });
        });

        function showSuccessModal(action) {
            var box = document.getElementById('success-modal-box');
            var iconWrap = document.getElementById('success-icon-wrap');
            var icon = document.getElementById('success-icon');
            var title = document.getElementById('success-title');
            var desc = document.getElementById('success-desc');
            var btn = document.getElementById('success-btn');

            if (action === 'confirm') {
                box.className = 'animate-slide-up relative bg-white rounded-3xl shadow-2xl max-w-sm w-full p-10 text-center space-y-6 border-b-8 border-emerald-500';
                iconWrap.className = 'w-24 h-24 rounded-full bg-emerald-50 mx-auto flex items-center justify-center shadow-inner animate-pulse';
                icon.className = 'w-12 h-12 text-emerald-500';
                icon.setAttribute('data-lucide', 'check-circle');
                title.textContent = 'Conversion Confirmed!';
                desc.textContent = 'The conversion request has been approved and inventory has been updated.';
                btn.className = 'w-full py-4 bg-emerald-500 text-white text-[10px] font-bold rounded-xl shadow-lg shadow-emerald-500/20 hover:bg-emerald-600 transition-all uppercase tracking-widest';
                btn.textContent = 'Confirm & Close';
            } else {
                box.className = 'animate-slide-up relative bg-white rounded-3xl shadow-2xl max-w-sm w-full p-10 text-center space-y-6 border-b-8 border-red-500';
                iconWrap.className = 'w-24 h-24 rounded-full bg-red-50 mx-auto flex items-center justify-center shadow-inner animate-pulse';
                icon.className = 'w-12 h-12 text-red-500';
                icon.setAttribute('data-lucide', 'x-circle');
                title.textContent = 'Request Rejected!';
                desc.textContent = 'The conversion request has been rejected. The requestor will be notified.';
                btn.className = 'w-full py-4 bg-red-500 text-white text-[10px] font-bold rounded-xl shadow-lg shadow-red-500/20 hover:bg-red-600 transition-all uppercase tracking-widest';
                btn.textContent = 'Close';
            }
            if (typeof lucide !== 'undefined') lucide.createIcons();
            toggleModal('success-modal', true);
        }

        function changePage(dir) {
            var np = currentPage + dir;
            if (np < 1 || np > lastPage) return;
            currentPage = np;
            loadRequests();
        }

        function updatePagination() {
            var info = document.getElementById('page-info');
            if (info) info.textContent = 'Page ' + currentPage + ' of ' + lastPage;
            var prev = document.getElementById('prev-page');
            var next = document.getElementById('next-page');
            if (prev) prev.disabled = currentPage <= 1;
            if (next) next.disabled = currentPage >= lastPage;
        }

        function buildSearchParams() {
            var fields = { code: 'search-code', requestor: 'search-requestor' };
            if (currentStatus === 'converted' || currentStatus === 'rejected') fields.target = 'search-target';
            var parts = [];
            for (var key in fields) {
                var el = document.getElementById(fields[key]);
                if (el && el.value.trim()) {
                    parts.push(encodeURIComponent('search[' + key + ']') + '=' + encodeURIComponent(el.value.trim()));
                }
            }
            return parts.join('&');
        }

        function switchStatus(status) {
            currentStatus = status;
            currentPage = 1;
            // Update tab styling
            var tabP = document.getElementById('tab-pending');
            var tabC = document.getElementById('tab-converted');
            var tabR = document.getElementById('tab-rejected');
            tabP.className = 'text-xs font-bold uppercase tracking-widest px-4 py-2 rounded-xl transition-all text-slate-400 hover:text-slate-600';
            tabC.className = 'text-xs font-bold uppercase tracking-widest px-4 py-2 rounded-xl transition-all text-slate-400 hover:text-slate-600';
            tabR.className = 'text-xs font-bold uppercase tracking-widest px-4 py-2 rounded-xl transition-all text-slate-400 hover:text-slate-600';
            if (status === 'pending') tabP.className = 'text-xs font-bold uppercase tracking-widest px-4 py-2 rounded-xl transition-all bg-maroon text-gold shadow-sm';
            else if (status === 'converted') tabC.className = 'text-xs font-bold uppercase tracking-widest px-4 py-2 rounded-xl transition-all bg-maroon text-gold shadow-sm';
            else tabR.className = 'text-xs font-bold uppercase tracking-widest px-4 py-2 rounded-xl transition-all bg-maroon text-gold shadow-sm';
            loadRequests();
        }

        function searchRequests() {
            currentPage = 1;
            loadRequests();
        }

        function updateStats(stats) {
            document.getElementById('stat-pending').textContent = stats.total_pending || 0;
            document.getElementById('stat-converted').textContent = stats.converted || 0;
            document.getElementById('stat-rejected').textContent = stats.rejected || 0;
            document.getElementById('stat-total').textContent = stats.total || 0;
        }

        function toggleModal(id, show) {
            var el = document.getElementById(id);
            if (el) { if (show) el.classList.remove('hidden'); else el.classList.add('hidden'); }
        }

        function escapeHtml(str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        /* ─── AUTO ACCEPT ─── */
        var autoAcceptMode = 'daily';

        function openModal(id) {
            var el = document.getElementById(id);
            if (el) el.classList.remove('hidden');
            if (id === 'auto-accept-modal') loadAutoAcceptConfig();
        }

        function closeModal(id) {
            var el = document.getElementById(id);
            if (el) el.classList.add('hidden');
        }

        function closeSummaryModal() {
            document.getElementById('auto-accept-summary-modal').classList.add('hidden');
        }

        function setAutoAcceptMode(mode) {
            autoAcceptMode = mode;
            var dailyBtn = document.getElementById('mode-daily');
            var inactBtn = document.getElementById('mode-inactivity');
            var dailyControls = document.getElementById('auto-controls-daily');
            var inactControls = document.getElementById('auto-controls-inactivity');

            if (mode === 'daily') {
                dailyBtn.className = 'flex-1 py-2 text-[10px] font-bold rounded-lg transition-all bg-white text-slate-800 shadow-sm';
                inactBtn.className = 'flex-1 py-2 text-[10px] font-bold rounded-lg transition-all text-slate-400';
                dailyControls.classList.remove('hidden');
                inactControls.classList.add('hidden');
            } else {
                inactBtn.className = 'flex-1 py-2 text-[10px] font-bold rounded-lg transition-all bg-white text-slate-800 shadow-sm';
                dailyBtn.className = 'flex-1 py-2 text-[10px] font-bold rounded-lg transition-all text-slate-400';
                inactControls.classList.remove('hidden');
                dailyControls.classList.add('hidden');
            }
        }

        function loadAutoAcceptConfig() {
            fetch('{{ route("admin.item-convert.auto-accept") }}')
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.success || !res.settings) return;
                    var s = res.settings;
                    document.getElementById('auto-accept-toggle').checked = s.enabled;
                    document.getElementById('auto-schedule-active').checked = s.schedule_active === true || s.schedule_active === '1';
                    document.getElementById('auto-accept-time').value = s.time || '18:00';
                    document.getElementById('auto-exclude-weekends').checked = s.exclude_weekends !== false;
                    document.getElementById('auto-current-ph-time').textContent = s.current_ph_time || '--:-- --';
                    document.getElementById('auto-interval-value').value = s.interval_value || 1;
                    document.getElementById('auto-interval-unit').value = s.interval_unit || 'days';

                    var mode = s.mode || 'daily';
                    setAutoAcceptMode(mode);

                    var badge = document.getElementById('auto-accept-badge');
                    if (s.enabled) badge.classList.remove('hidden');
                    else badge.classList.add('hidden');

                    var lastRun = document.getElementById('auto-accept-last-run');
                    if (s.last_auto_accept_run) {
                        lastRun.classList.remove('hidden');
                        var d = new Date(s.last_auto_accept_run);
                        document.getElementById('auto-last-run-text').textContent = d.toLocaleString('en-PH', { timeZone: 'Asia/Manila' });
                    } else {
                        lastRun.classList.add('hidden');
                    }
                })
                .catch(function () {});
        }

        function saveAutoAccept() {
            var enabled = document.getElementById('auto-accept-toggle').checked;
            var scheduleActive = document.getElementById('auto-schedule-active').checked;
            var time = document.getElementById('auto-accept-time').value || '18:00';
            var intervalValue = parseInt(document.getElementById('auto-interval-value').value) || 1;
            var intervalUnit = document.getElementById('auto-interval-unit').value;
            var excludeWeekends = document.getElementById('auto-exclude-weekends').checked;

            var badge = document.getElementById('auto-accept-badge');
            if (enabled) badge.classList.remove('hidden');
            else badge.classList.add('hidden');

            fetch('{{ route("admin.item-convert.auto-accept.save") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({
                    enabled: enabled,
                    schedule_active: scheduleActive,
                    mode: autoAcceptMode,
                    time: time,
                    interval_value: intervalValue,
                    interval_unit: intervalUnit,
                    exclude_weekends: excludeWeekends
                })
            })
            .then(function (r) { return r.text().then(function (t) { return JSON.parse(t); }); })
            .then(function (res) {
                if (res.success && res.confirmed_count > 0) {
                    showAutoAcceptSummary(res.confirmed_items || []);
                    closeModal('auto-accept-modal');
                    setTimeout(function () { location.reload(); }, 2000);
                } else {
                    var status = document.getElementById('auto-accept-modal-status');
                    var text = document.getElementById('auto-accept-modal-status-text');
                    status.classList.remove('hidden');
                    text.textContent = res.success ? 'Settings saved' : 'Save failed: ' + (res.error || 'unknown');
                    setTimeout(function () { status.classList.add('hidden'); location.reload(); }, 2000);
                }
            })
            .catch(function (err) {
                console.error('Auto-accept save error:', err);
                document.getElementById('auto-accept-modal-status').classList.remove('hidden');
                document.getElementById('auto-accept-modal-status-text').textContent = 'Network error: ' + err.message;
                setTimeout(function () { document.getElementById('auto-accept-modal-status').classList.add('hidden'); }, 2000);
            });
        }

        function showAutoAcceptSummary(items) {
            var tbody = document.getElementById('summary-items-tbody');
            tbody.innerHTML = '';
            if (!items || items.length === 0) {
                document.getElementById('summary-empty').classList.remove('hidden');
            } else {
                document.getElementById('summary-empty').classList.add('hidden');
                items.forEach(function (item) {
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td class="py-3 pr-3 text-slate-700 font-bold">' + escapeHtml(item.source_code || '---') + '</td>' +
                        '<td class="py-3 pr-3 text-slate-700 font-bold">' + escapeHtml(item.target_code || '---') + '</td>' +
                        '<td class="py-3 pr-3 text-emerald-600 font-bold">' + (item.qty || 0) + '</td>' +
                        '<td class="py-3 text-slate-500">' + escapeHtml(item.requestor || 'Unknown') + '</td>';
                    tbody.appendChild(tr);
                });
            }
            document.getElementById('auto-accept-summary-modal').classList.remove('hidden');
        }
    </script>
@endpush

@include('partials.admin.admin_sidebar_navbar')
