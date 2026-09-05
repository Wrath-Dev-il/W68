<?php $__env->startPush('styles'); ?>
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

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #e2e8f0;
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #cbd5e1;
        }

        .stat-card-hero {
            border-radius: 1.25rem;
            background: radial-gradient(circle at 85% 5%, rgba(255, 215, 0, 0.32), transparent 32%), linear-gradient(145deg, #800000 0%, #4b0710 72%);
            color: #ffffff;
            box-shadow: 0 18px 35px rgba(128, 0, 0, 0.22);
            position: relative;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.25s ease;
        }

        .stat-card-hero::after {
            content: "";
            position: absolute;
            inset: auto -20% -45% 18%;
            height: 8rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            transform: rotate(-12deg);
        }

        .stat-card-hero:hover {
            transform: scale(1.03);
            box-shadow: 0 22px 40px rgba(128, 0, 0, 0.3);
        }

        .stat-card {
            cursor: pointer;
            transition: all 0.25s ease;
        }

        .stat-card:hover {
            transform: scale(1.03);
        }

        .stat-card.active-card {
            border-color: #800000 !important;
            box-shadow: 0 0 0 2px rgba(128, 0, 0, 0.15);
        }

        .icon-wrap {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 215, 0, 0.15);
            color: #FFD700;
            flex-shrink: 0;
        }

        .convert-side {
            min-height: 280px;
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
            background: rgba(128, 0, 0, 0.55);
            backdrop-filter: blur(4px);
        }

        .tab-btn {
            padding: 0.625rem 1.25rem;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-radius: 0.75rem;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .tab-btn-inactive {
            background: #f1f5f9;
            color: #94a3b8;
        }

        .tab-btn-inactive:hover {
            background: #e2e8f0;
            color: #64748b;
        }

        .tab-btn-active {
            background: #800000;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.25);
        }

        .tab-content {
            display: none;
        }

        .tab-content-active {
            display: block;
        }
    </style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('item_convert_content'); ?>

<div id="page-item-convert" class="space-y-6 animate-fade-in p-6">

    <!-- HEADER -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-bold text-slate-800 tracking-tight">Convert Products</h2>
            <p class="text-xs text-slate-400 font-medium">Convert items from one product type to another.</p>
        </div>
    </div>

    <!-- SUMMARY CARDS (clickable) -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <div onclick="switchTab('converted')" class="stat-card-hero p-6" id="card-converted">
            <div class="relative z-10">
                <span class="text-[10px] font-bold uppercase tracking-widest text-gold/80 block mb-2">Converted Items</span>
                <h3 id="stat-converted" class="text-3xl font-extrabold tracking-tight">0</h3>
                <p class="text-[10px] text-white/60 font-medium mt-1">successfully converted</p>
            </div>
        </div>
        <div onclick="switchTab('rejected')" class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between group stat-card" id="card-rejected">
            <div class="space-y-0.5 min-w-0">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Rejected Convert</span>
                <h3 id="stat-rejected" class="text-2xl font-extrabold text-slate-800 tracking-tight truncate">0</h3>
                <div class="flex items-center gap-1.5 mt-1">
                    <span class="text-red-500 font-bold text-[10px] flex items-center gap-0.5">
                        <i data-lucide="x-circle" class="w-3 h-3"></i>
                        failed
                    </span>
                </div>
            </div>
            <div class="icon-wrap group-hover:scale-105 transition-transform duration-300">
                <i data-lucide="ban" class="w-5 h-5"></i>
            </div>
        </div>
        <div onclick="switchTab('convertible')" class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between group stat-card active-card" id="card-convertible">
            <div class="space-y-0.5 min-w-0">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Total Items</span>
                <h3 id="stat-total" class="text-2xl font-extrabold text-slate-800 tracking-tight truncate">0</h3>
                <div class="flex items-center gap-1.5 mt-1">
                    <span class="text-amber-500 font-bold text-[10px] flex items-center gap-0.5">
                        <i data-lucide="package" class="w-3 h-3"></i>
                        available
                    </span>
                </div>
            </div>
            <div class="icon-wrap group-hover:scale-105 transition-transform duration-300">
                <i data-lucide="shuffle" class="w-5 h-5"></i>
            </div>
        </div>
    </div>

    <!-- TAB NAVIGATION -->
    <div class="flex items-center space-x-2">
        <button onclick="switchTab('convertible')" id="tab-btn-convertible" class="tab-btn tab-btn-active">Convertible Items</button>
        <button onclick="switchTab('converted')" id="tab-btn-converted" class="tab-btn tab-btn-inactive">Converted Items</button>
        <button onclick="switchTab('rejected')" id="tab-btn-rejected" class="tab-btn tab-btn-inactive">Rejected Items</button>
    </div>

    <!-- TAB: CONVERTIBLE ITEMS -->
    <div id="tab-convertible" class="tab-content tab-content-active">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-50 bg-slate-50/50 flex items-center justify-between">
                <h3 class="text-xs font-bold text-slate-700 uppercase tracking-widest">Convertible Items List</h3>
                <span class="text-[10px] text-slate-400 font-medium">Items eligible for product conversion</span>
            </div>
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-400 text-[10px] font-bold uppercase tracking-widest">
                            <th class="py-5 px-6">Product Code</th>
                            <th class="py-5 px-6">Part No#</th>
                            <th class="py-5 px-6">Application</th>
                            <th class="py-5 px-6">Description</th>
                            <th class="py-5 px-6">Position</th>
                            <th class="py-5 px-6 text-center">Action</th>
                        </tr>
                        <tr class="bg-white border-b border-slate-100">
                            <th class="p-2 px-6"><input type="text" id="search-code" onkeyup="searchConvertible()" class="col-search-input" placeholder="Search Code..."></th>
                            <th class="p-2 px-6"><input type="text" id="search-part" onkeyup="searchConvertible()" class="col-search-input" placeholder="Search Part No..."></th>
                            <th class="p-2 px-6"><input type="text" id="search-app" onkeyup="searchConvertible()" class="col-search-input" placeholder="Search App..."></th>
                            <th class="p-2 px-6"><input type="text" id="search-desc" onkeyup="searchConvertible()" class="col-search-input" placeholder="Search Desc..."></th>
                            <th class="p-2 px-6"><input type="text" id="search-pos" onkeyup="searchConvertible()" class="col-search-input" placeholder="Search Pos..."></th>
                            <th class="p-2 px-6"></th>
                        </tr>
                    </thead>
                    <tbody id="convertible-tbody" class="divide-y divide-slate-100 text-xs text-slate-600 font-medium">
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-slate-100 bg-slate-50/30 flex items-center justify-between flex-shrink-0">
                <span id="convertible-page-info" class="text-xs text-slate-500 font-medium">Page 1 of 1</span>
                <div class="flex items-center space-x-2">
                    <button id="convertible-prev-page" onclick="changePage('convertible', -1)" class="px-3 py-1.5 text-xs border border-slate-200 rounded-lg hover:bg-slate-100 disabled:opacity-30 transition-all" disabled>Prev</button>
                    <button id="convertible-next-page" onclick="changePage('convertible', 1)" class="px-3 py-1.5 text-xs border border-slate-200 rounded-lg hover:bg-slate-100 transition-all">Next</button>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB: CONVERTED ITEMS -->
    <div id="tab-converted" class="tab-content">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-50 bg-slate-50/50 flex items-center justify-between">
                <h3 class="text-xs font-bold text-slate-700 uppercase tracking-widest">Converted Items List</h3>
                <span class="text-[10px] text-slate-400 font-medium">Items that have been successfully converted</span>
            </div>
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-400 text-[10px] font-bold uppercase tracking-widest">
                            <th class="py-5 px-6">Product Code</th>
                            <th class="py-5 px-6">Part No#</th>
                            <th class="py-5 px-6">Application</th>
                            <th class="py-5 px-6">Description</th>
                            <th class="py-5 px-6">Position</th>
                            <th class="py-5 px-6 text-center">Converted Qty</th>
                            <th class="py-5 px-6 text-center">Date</th>
                        </tr>
                        <tr class="bg-white border-b border-slate-100">
                            <th class="p-2 px-6"><input type="text" onkeyup="window.filterConvertTable('converted', 0, this.value)" class="col-search-input" placeholder="Search Code..."></th>
                            <th class="p-2 px-6"><input type="text" onkeyup="window.filterConvertTable('converted', 1, this.value)" class="col-search-input" placeholder="Search Part No..."></th>
                            <th class="p-2 px-6"><input type="text" onkeyup="window.filterConvertTable('converted', 2, this.value)" class="col-search-input" placeholder="Search App..."></th>
                            <th class="p-2 px-6"><input type="text" onkeyup="window.filterConvertTable('converted', 3, this.value)" class="col-search-input" placeholder="Search Desc..."></th>
                            <th class="p-2 px-6"><input type="text" onkeyup="window.filterConvertTable('converted', 4, this.value)" class="col-search-input" placeholder="Search Pos..."></th>
                            <th class="p-2 px-6"><input type="text" onkeyup="window.filterConvertTable('converted', 5, this.value)" class="col-search-input" placeholder="Search Qty..."></th>
                            <th class="p-2 px-6"><input type="text" onkeyup="window.filterConvertTable('converted', 6, this.value)" class="col-search-input" placeholder="Search Date..."></th>
                        </tr>
                    </thead>
                    <tbody id="converted-tbody" class="divide-y divide-slate-100 text-xs text-slate-600 font-medium">
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB: REJECTED ITEMS -->
    <div id="tab-rejected" class="tab-content">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-50 bg-slate-50/50 flex items-center justify-between">
                <h3 class="text-xs font-bold text-slate-700 uppercase tracking-widest">Rejected Items List</h3>
                <span class="text-[10px] text-slate-400 font-medium">Items that failed conversion</span>
            </div>
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-400 text-[10px] font-bold uppercase tracking-widest">
                            <th class="py-5 px-6">Product Code</th>
                            <th class="py-5 px-6">Part No#</th>
                            <th class="py-5 px-6">Application</th>
                            <th class="py-5 px-6">Description</th>
                            <th class="py-5 px-6">Position</th>
                            <th class="py-5 px-6 text-center">Qty</th>
                            <th class="py-5 px-6">Reason</th>
                        </tr>
                        <tr class="bg-white border-b border-slate-100">
                            <th class="p-2 px-6"><input type="text" onkeyup="window.filterConvertTable('rejected', 0, this.value)" class="col-search-input" placeholder="Search Code..."></th>
                            <th class="p-2 px-6"><input type="text" onkeyup="window.filterConvertTable('rejected', 1, this.value)" class="col-search-input" placeholder="Search Part No..."></th>
                            <th class="p-2 px-6"><input type="text" onkeyup="window.filterConvertTable('rejected', 2, this.value)" class="col-search-input" placeholder="Search App..."></th>
                            <th class="p-2 px-6"><input type="text" onkeyup="window.filterConvertTable('rejected', 3, this.value)" class="col-search-input" placeholder="Search Desc..."></th>
                            <th class="p-2 px-6"><input type="text" onkeyup="window.filterConvertTable('rejected', 4, this.value)" class="col-search-input" placeholder="Search Pos..."></th>
                            <th class="p-2 px-6"><input type="text" onkeyup="window.filterConvertTable('rejected', 5, this.value)" class="col-search-input" placeholder="Search Qty..."></th>
                            <th class="p-2 px-6"><input type="text" onkeyup="window.filterConvertTable('rejected', 6, this.value)" class="col-search-input" placeholder="Search Reason..."></th>
                        </tr>
                    </thead>
                    <tbody id="rejected-tbody" class="divide-y divide-slate-100 text-xs text-slate-600 font-medium">
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- CONVERT ITEMS MODAL -->
<div id="convert-modal" class="fixed inset-0 z-50 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div onclick="toggleModal('convert-modal', false)" class="fixed inset-0 modal-overlay"></div>
        <div class="animate-slide-up relative bg-white rounded-3xl shadow-2xl w-full max-w-5xl overflow-hidden flex flex-col max-h-[90vh]">
            <div class="bg-gradient-to-r from-maroon-900 to-maroon-950 px-8 py-5 flex items-center justify-between">
                <div class="flex items-center space-x-3 text-white">
                    <i data-lucide="shuffle" class="w-6 h-6 text-gold"></i>
                    <h3 class="text-sm font-bold uppercase tracking-widest">Convert Items</h3>
                </div>
                <button onclick="toggleModal('convert-modal', false)" class="text-white/70 hover:text-white p-2 hover:bg-white/10 rounded-full transition-all">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div class="flex-1 p-8 overflow-y-auto">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <!-- LEFT SIDE - Source Item -->
                    <div class="convert-side bg-slate-50 rounded-2xl p-6 border border-slate-100">
                        <div class="flex items-center space-x-2 mb-5 pb-3 border-b border-slate-200">
                            <div class="w-8 h-8 rounded-lg bg-maroon flex items-center justify-center">
                                <i data-lucide="package" class="w-4 h-4 text-gold"></i>
                            </div>
                            <span class="text-xs font-bold text-slate-600 uppercase tracking-widest">Source Item</span>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <p class="field-label">Product Code</p>
                                <p id="conv-source-code" class="field-value">---</p>
                            </div>
                            <div>
                                <p class="field-label">Part No#</p>
                                <p id="conv-source-part" class="field-value">---</p>
                            </div>
                            <div>
                                <p class="field-label">Application</p>
                                <p id="conv-source-app" class="field-value">---</p>
                            </div>
                            <div>
                                <p class="field-label">Description</p>
                                <p id="conv-source-desc" class="field-value">---</p>
                            </div>
                            <div>
                                <p class="field-label">Position</p>
                                <p id="conv-source-pos" class="field-value">---</p>
                            </div>
                            <div>
                                <p class="field-label">Available Qty</p>
                                <p id="conv-source-qty" class="field-value font-black text-lg">0</p>
                            </div>
                            <div>
                                <p class="field-label">Convertable Qty</p>
                                <input type="number" id="conv-convertable-qty" min="1" oninput="updateConvertedQtyDisplay()" class="w-full px-4 py-3 bg-white border-2 border-maroon/20 rounded-xl text-lg font-black text-slate-800 outline-none focus:border-maroon focus:ring-4 focus:ring-maroon/5 transition-all" placeholder="Enter qty" value="1">
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT SIDE - Target Item -->
                    <div class="convert-side bg-slate-50 rounded-2xl p-6 border border-slate-100">
                        <div class="flex items-center space-x-2 mb-5 pb-3 border-b border-slate-200">
                            <div class="w-8 h-8 rounded-lg bg-maroon flex items-center justify-center">
                                <i data-lucide="refresh-cw" class="w-4 h-4 text-gold"></i>
                            </div>
                            <span class="text-xs font-bold text-slate-600 uppercase tracking-widest">Target Item</span>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <p class="field-label">Search Target</p>
                                <div onclick="openTargetModal()" class="w-full px-4 py-3 bg-white border-2 border-dashed border-maroon/30 rounded-xl text-sm text-slate-400 cursor-pointer hover:border-maroon hover:bg-maroon/5 transition-all flex items-center space-x-2">
                                    <i data-lucide="search" class="w-4 h-4 text-maroon"></i>
                                    <span id="target-search-label">Click to search and select a target product...</span>
                                </div>
                            </div>
                            <div>
                                <p class="field-label">Product Code</p>
                                <p id="conv-target-code" class="field-value">---</p>
                            </div>
                            <div>
                                <p class="field-label">Part No#</p>
                                <p id="conv-target-part" class="field-value">---</p>
                            </div>
                            <div>
                                <p class="field-label">Application</p>
                                <p id="conv-target-app" class="field-value">---</p>
                            </div>
                            <div>
                                <p class="field-label">Description</p>
                                <p id="conv-target-desc" class="field-value">---</p>
                            </div>
                            <div>
                                <p class="field-label">Position</p>
                                <p id="conv-target-pos" class="field-value">---</p>
                            </div>
                            <div>
                                <p class="field-label">Available Qty</p>
                                <p id="conv-target-avail" class="field-value">0</p>
                            </div>
                            <div>
                                <p class="field-label">Converted Qty</p>
                                <p id="conv-target-converted" class="field-value font-black text-lg text-emerald-600">0</p>
                            </div>
                            <div class="pt-2 flex gap-3">
                                <button onclick="openConfirmModal()" class="flex-1 py-3.5 bg-maroon text-white text-[10px] font-bold rounded-xl shadow-lg hover:shadow-maroon/20 transition-all uppercase tracking-widest flex items-center justify-center space-x-2">
                                    <i data-lucide="shuffle" class="w-4 h-4"></i>
                                    <span>Request Convert</span>
                                </button>
                                <button onclick="toggleModal('convert-modal', false)" class="flex-1 py-3.5 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-xl hover:bg-slate-200 transition-all uppercase tracking-widest">Cancel</button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- REQUEST CONFIRM MODAL -->
<div id="confirm-modal" class="fixed inset-0 z-[100] overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 modal-overlay"></div>
        <div class="animate-slide-up relative bg-white rounded-3xl shadow-2xl max-w-sm w-full p-8 text-center space-y-6">
            <div class="w-20 h-20 rounded-full bg-amber-50 mx-auto flex items-center justify-center shadow-inner">
                <i data-lucide="alert-triangle" class="w-10 h-10 text-amber-500"></i>
            </div>
            <div>
                <h3 class="text-lg font-black text-slate-800 tracking-tight">Confirm Conversion?</h3>
                <p class="text-xs text-slate-400 font-medium leading-relaxed mt-2">This will directly convert the source item quantity to the target product. This action cannot be undone.</p>
            </div>
            <div class="flex items-center gap-3 pt-2">
                <button onclick="toggleModal('confirm-modal', false)" class="flex-1 py-3.5 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-xl hover:bg-slate-200 transition-all uppercase tracking-widest">No, Cancel</button>
                <button id="confirm-yes-btn" class="flex-1 py-3.5 bg-maroon text-white text-[10px] font-bold rounded-xl shadow-lg hover:shadow-maroon/20 transition-all uppercase tracking-widest">Yes, Convert</button>
            </div>
        </div>
    </div>
</div>

<!-- STOCK WARNING MODAL (shown when convert qty exceeds available stock) -->
<div id="stock-warning-modal" class="fixed inset-0 z-[105] overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 modal-overlay"></div>
        <div class="animate-slide-up relative bg-white rounded-3xl shadow-2xl max-w-sm w-full p-8 text-center space-y-6">
            <div class="w-20 h-20 rounded-full bg-amber-50 mx-auto flex items-center justify-center shadow-inner">
                <i data-lucide="alert-triangle" class="w-10 h-10 text-amber-500"></i>
            </div>
            <div>
                <h3 class="text-lg font-black text-slate-800 tracking-tight">Low Stock Warning</h3>
                <p id="stock-warning-text" class="text-xs text-slate-400 font-medium leading-relaxed mt-2">The convert quantity exceeds the available stock.</p>
            </div>
            <div class="flex items-center gap-3 pt-2">
                <button onclick="toggleModal('stock-warning-modal', false)" class="flex-1 py-3.5 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-xl hover:bg-slate-200 transition-all uppercase tracking-widest">Cancel</button>
                <button onclick="proceedAfterWarning()" class="flex-1 py-3.5 bg-amber-500 text-white text-[10px] font-bold rounded-xl shadow-lg hover:shadow-amber-500/20 transition-all uppercase tracking-widest">Proceed Anyway</button>
            </div>
        </div>
    </div>
</div>

<!-- SUCCESS MODAL -->
<div id="success-modal" class="fixed inset-0 z-[110] overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div onclick="toggleModal('success-modal', false)" class="fixed inset-0 bg-emerald-900/20 backdrop-blur-sm"></div>
        <div class="animate-slide-up relative bg-white rounded-3xl shadow-2xl max-w-sm w-full p-10 text-center space-y-6 border-b-8 border-emerald-500">
            <div class="w-24 h-24 rounded-full bg-emerald-50 mx-auto flex items-center justify-center shadow-inner animate-pulse">
                <i data-lucide="check-circle" class="w-12 h-12 text-emerald-500"></i>
            </div>
            <div>
                <h3 id="success-title" class="text-xl font-black text-slate-800 tracking-tight">Conversion Submitted!</h3>
                <p id="success-desc" class="text-xs text-slate-400 font-medium leading-relaxed mt-2">The conversion request has been submitted for admin review.</p>
            </div>
            <button onclick="toggleModal('success-modal', false)" class="w-full py-4 bg-emerald-500 text-white text-[10px] font-bold rounded-xl shadow-lg shadow-emerald-500/20 hover:bg-emerald-600 transition-all uppercase tracking-widest">Done</button>
        </div>
    </div>
</div>

<!-- SELECT TARGET MODAL -->
<div id="target-modal" class="fixed inset-0 z-[150] overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div onclick="toggleModal('target-modal', false)" class="fixed inset-0 modal-overlay"></div>
        <div class="animate-slide-up relative bg-white rounded-3xl shadow-2xl w-full max-w-5xl max-h-[90vh] overflow-hidden flex flex-col">
            <div class="bg-gradient-to-r from-maroon-900 to-maroon-950 px-8 py-5 flex items-center justify-between">
                <div class="flex items-center space-x-3 text-white">
                    <i data-lucide="search" class="w-6 h-6 text-gold"></i>
                    <h3 class="text-sm font-bold uppercase tracking-widest">Select Target Item</h3>
                </div>
                <button onclick="toggleModal('target-modal', false)" class="text-white/70 hover:text-white p-2 hover:bg-white/10 rounded-full transition-all">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div class="flex-1 p-6 overflow-y-auto">
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto custom-scrollbar">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 text-slate-400 text-[10px] font-bold uppercase tracking-widest">
                                    <th class="py-4 px-5">Product Code</th>
                                    <th class="py-4 px-5">Part No#</th>
                                    <th class="py-4 px-5">Description</th>
                                    <th class="py-4 px-5">Application</th>
                                    <th class="py-4 px-5">Position</th>
                                </tr>
                                <tr class="bg-white border-b border-slate-100">
                                    <th class="p-1.5 px-5"><input type="text" id="target-srch-code" onkeyup="targetSearch()" class="col-search-input" placeholder="Search Code..."></th>
                                    <th class="p-1.5 px-5"><input type="text" id="target-srch-part" onkeyup="targetSearch()" class="col-search-input" placeholder="Search Part No..."></th>
                                    <th class="p-1.5 px-5"><input type="text" id="target-srch-desc" onkeyup="targetSearch()" class="col-search-input" placeholder="Search Desc..."></th>
                                    <th class="p-1.5 px-5"><input type="text" id="target-srch-app" onkeyup="targetSearch()" class="col-search-input" placeholder="Search App..."></th>
                                    <th class="p-1.5 px-5"><input type="text" id="target-srch-pos" onkeyup="targetSearch()" class="col-search-input" placeholder="Search Pos..."></th>
                                </tr>
                            </thead>
                            <tbody id="target-tbody" class="divide-y divide-slate-100 text-xs text-slate-600 font-medium">
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 border-t border-slate-100 bg-slate-50/30 flex items-center justify-between">
                        <span id="target-page-info" class="text-xs text-slate-500 font-medium">Page 1 of 1</span>
                        <div class="flex items-center space-x-2">
                            <button id="target-prev-page" onclick="targetChangePage(-1)" class="px-3 py-1.5 text-xs border border-slate-200 rounded-lg hover:bg-slate-100 disabled:opacity-30 transition-all" disabled>Prev</button>
                            <button id="target-next-page" onclick="targetChangePage(1)" class="px-3 py-1.5 text-xs border border-slate-200 rounded-lg hover:bg-slate-100 transition-all">Next</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script>
        var allData = { convertible: [], converted: [], rejected: [] };
        var selectedItem = null;
        var currentTab = 'convertible';
        var currentPage = 1;
        var lastPage = 1;

        document.addEventListener('DOMContentLoaded', function () {
            loadAllData();
        });

        function loadAllData() {
            var tbody = document.getElementById('convertible-tbody');
            tbody.innerHTML = '<tr><td colspan="6" class="py-10 text-center text-slate-400 text-xs font-medium">Loading items...</td></tr>';

            var params = '?page=' + currentPage;
            var s = buildSearchParams();
            if (s) params += '&' + s;

            fetch('<?php echo e(route("special.item-convert.data")); ?>' + params)
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.success) {
                        tbody.innerHTML = '<tr><td colspan="6" class="py-10 text-center text-slate-400 text-xs font-medium">Failed to load items.</td></tr>';
                        return;
                    }
                    allData.convertible = res.items || [];
                    allData.converted = res.converted || [];
                    allData.rejected = res.rejected || [];

                    if (res.pagination) {
                        currentPage = res.pagination.current_page;
                        lastPage = res.pagination.last_page;
                        updatePagination();
                    }

                    renderTable('convertible', allData.convertible);
                    renderTable('converted', allData.converted);
                    renderTable('rejected', allData.rejected);
                    updateConvertStats(res.stats);
                })
                .catch(function (e) {
                    console.error('Failed to load convert items:', e);
                    tbody.innerHTML = '<tr><td colspan="6" class="py-10 text-center text-slate-400 text-xs font-medium">Error loading items.</td></tr>';
                });
        }

        function changePage(tab, dir) {
            var newPage = currentPage + dir;
            if (newPage < 1 || newPage > lastPage) return;
            currentPage = newPage;
            loadAllData();
        }

        function updatePagination() {
            var info = document.getElementById('convertible-page-info');
            if (info) info.textContent = 'Page ' + currentPage + ' of ' + lastPage;

            var prev = document.getElementById('convertible-prev-page');
            var next = document.getElementById('convertible-next-page');
            if (prev) prev.disabled = currentPage <= 1;
            if (next) next.disabled = currentPage >= lastPage;
        }

        function buildSearchParams() {
            var fields = { code: 'search-code', part: 'search-part', app: 'search-app', desc: 'search-desc', pos: 'search-pos' };
            var parts = [];
            for (var key in fields) {
                var el = document.getElementById(fields[key]);
                if (el && el.value.trim()) {
                    parts.push(encodeURIComponent('search[' + key + ']') + '=' + encodeURIComponent(el.value.trim()));
                }
            }
            return parts.join('&');
        }

        function searchConvertible() {
            currentPage = 1;
            loadAllData();
        }

        function renderTable(tab, items) {
            var tbodyId = tab + '-tbody';
            var tbody = document.getElementById(tbodyId);
            tbody.innerHTML = '';
            if (!items || items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="py-10 text-center text-slate-400 text-xs font-medium">No ' + tab + ' items found.</td></tr>';
                return;
            }
            items.forEach(function (item, idx) {
                var tr = document.createElement('tr');
                tr.className = 'hover:bg-slate-50/50 transition-colors group';

                if (tab === 'convertible') {
                    tr.innerHTML =
                        '<td class="py-5 px-6 text-slate-700 font-bold">' + escapeHtml(item.product_code) + '</td>' +
                        '<td class="py-5 px-6 text-slate-500">' + escapeHtml(item.part_number) + '</td>' +
                        '<td class="py-5 px-6"><span class="px-2.5 py-1 bg-slate-100 rounded-md text-[10px] font-semibold">' + escapeHtml(item.application || 'N/A') + '</span></td>' +
                        '<td class="py-5 px-6 text-slate-500 max-w-[200px] truncate">' + escapeHtml(item.description) + '</td>' +
                        '<td class="py-5 px-6 text-slate-500">' + escapeHtml(item.position || 'N/A') + '</td>' +
                        '<td class="py-5 px-6 text-center"><button onclick="openConvertModal(' + idx + ')" class="px-4 py-2 bg-maroon text-white text-[10px] font-bold rounded-xl hover:bg-maroon/90 transition-all shadow-md flex items-center space-x-1.5 mx-auto"><i data-lucide="shuffle" class="w-3.5 h-3.5"></i><span>Convert</span></button></td>';
                } else if (tab === 'converted') {
                    tr.innerHTML =
                        '<td class="py-5 px-6 text-slate-700 font-bold">' + escapeHtml(item.product_code) + '</td>' +
                        '<td class="py-5 px-6 text-slate-500">' + escapeHtml(item.part_number) + '</td>' +
                        '<td class="py-5 px-6"><span class="px-2.5 py-1 bg-slate-100 rounded-md text-[10px] font-semibold">' + escapeHtml(item.application || 'N/A') + '</span></td>' +
                        '<td class="py-5 px-6 text-slate-500 max-w-[200px] truncate">' + escapeHtml(item.description) + '</td>' +
                        '<td class="py-5 px-6 text-slate-500">' + escapeHtml(item.position || 'N/A') + '</td>' +
                        '<td class="py-5 px-6 text-center font-bold text-emerald-600">' + (item.converted_qty || 0) + '</td>' +
                        '<td class="py-5 px-6 text-center text-slate-400 text-[10px]">' + escapeHtml(item.converted_at || '---') + '</td>';
                } else if (tab === 'rejected') {
                    tr.innerHTML =
                        '<td class="py-5 px-6 text-slate-700 font-bold">' + escapeHtml(item.product_code) + '</td>' +
                        '<td class="py-5 px-6 text-slate-500">' + escapeHtml(item.part_number) + '</td>' +
                        '<td class="py-5 px-6"><span class="px-2.5 py-1 bg-slate-100 rounded-md text-[10px] font-semibold">' + escapeHtml(item.application || 'N/A') + '</span></td>' +
                        '<td class="py-5 px-6 text-slate-500 max-w-[200px] truncate">' + escapeHtml(item.description) + '</td>' +
                        '<td class="py-5 px-6 text-slate-500">' + escapeHtml(item.position || 'N/A') + '</td>' +
                        '<td class="py-5 px-6 text-center font-bold text-red-500">' + (item.qty || 0) + '</td>' +
                        '<td class="py-5 px-6 text-slate-400 text-[10px]">' + escapeHtml(item.reason || 'N/A') + '</td>';
                }
                tbody.appendChild(tr);
            });
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }

        function switchTab(tab) {
            currentTab = tab;

            document.querySelectorAll('.tab-content').forEach(function (el) {
                el.classList.remove('tab-content-active');
            });
            document.getElementById('tab-' + tab).classList.add('tab-content-active');

            document.querySelectorAll('.tab-btn').forEach(function (btn) {
                btn.classList.remove('tab-btn-active');
                btn.classList.add('tab-btn-inactive');
            });
            document.getElementById('tab-btn-' + tab).classList.remove('tab-btn-inactive');
            document.getElementById('tab-btn-' + tab).classList.add('tab-btn-active');

            document.querySelectorAll('.stat-card, .stat-card-hero').forEach(function (el) {
                el.classList.remove('active-card');
            });
            var cardEl = document.getElementById('card-' + tab);
            if (cardEl) cardEl.classList.add('active-card');
        }

        function updateConvertStats(stats) {
            document.getElementById('stat-converted').textContent = stats.converted || 0;
            document.getElementById('stat-rejected').textContent = stats.rejected || 0;
            document.getElementById('stat-total').textContent = stats.total || 0;
        }

        function openConvertModal(idx) {
            selectedItem = allData.convertible[idx];
            if (!selectedItem) return;

            document.getElementById('conv-source-code').textContent = selectedItem.product_code || '---';
            document.getElementById('conv-source-part').textContent = selectedItem.part_number || '---';
            document.getElementById('conv-source-app').textContent = selectedItem.application || '---';
            document.getElementById('conv-source-desc').textContent = selectedItem.description || '---';
            document.getElementById('conv-source-pos').textContent = selectedItem.position || '---';
            document.getElementById('conv-source-qty').textContent = selectedItem.available_qty || 0;
            document.getElementById('conv-convertable-qty').value = 1;
            document.getElementById('conv-convertable-qty').max = '';

            document.getElementById('conv-target-code').textContent = selectedItem.target_code || '---';
            document.getElementById('conv-target-part').textContent = selectedItem.target_part || '---';
            document.getElementById('conv-target-app').textContent = selectedItem.target_app || '---';
            document.getElementById('conv-target-desc').textContent = selectedItem.target_desc || '---';
            document.getElementById('conv-target-pos').textContent = selectedItem.target_pos || '---';
            document.getElementById('conv-target-avail').textContent = selectedItem.target_avail || 0;
            updateConvertedQtyDisplay();

            toggleModal('convert-modal', true);
        }

        function updateConvertedQtyDisplay() {
            var qty = parseInt(document.getElementById('conv-convertable-qty').value) || 0;
            document.getElementById('conv-target-converted').textContent = qty;
        }

        function openConfirmModal() {
            if (!selectedTarget) { alert('Please select a target product first.'); return; }
            var qty = parseInt(document.getElementById('conv-convertable-qty').value) || 0;
            if (qty < 1) {
                alert('Please enter a valid quantity.');
                return;
            }
            var availQty = parseInt(selectedItem.available_qty || 0);
            if (qty > availQty) {
                document.getElementById('stock-warning-text').textContent = 'You are trying to convert ' + qty + ' units, but the available stock is only ' + availQty + '. Do you want to proceed anyway?';
                toggleModal('convert-modal', false);
                toggleModal('stock-warning-modal', true);
                return;
            }
            proceedToConfirm();
        }

        function proceedAfterWarning() {
            toggleModal('stock-warning-modal', false);
            proceedToConfirm();
        }

        function proceedToConfirm() {
            var qty = parseInt(document.getElementById('conv-convertable-qty').value) || 0;
            document.getElementById('conv-target-converted').textContent = qty;
            toggleModal('convert-modal', false);
            toggleModal('confirm-modal', true);
        }

        document.getElementById('confirm-yes-btn')?.addEventListener('click', function () {
            var qty = parseInt(document.getElementById('conv-convertable-qty').value) || 0;
            toggleModal('confirm-modal', false);

            fetch('<?php echo e(route("special.item-convert.save")); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>' },
                body: JSON.stringify({ product_id: selectedItem.id, target_product_id: selectedTarget ? selectedTarget.id : null, quantity: qty })
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    if (res.auto_accepted) {
                        document.getElementById('success-title').textContent = 'Conversion Auto-Approved!';
                        document.getElementById('success-desc').textContent = 'The conversion has been automatically approved. Inventory updated.';
                    }
                    toggleModal('success-modal', true);
                    loadAllData();
                } else {
                    alert(res.error || 'Conversion failed.');
                }
            })
            .catch(function () { alert('Conversion request failed.'); });
        });

        function toggleModal(id, show) {
            var el = document.getElementById(id);
            if (el) {
                if (show) el.classList.remove('hidden');
                else el.classList.add('hidden');
            }
        }

        window.filterConvertTable = function (tab, colIndex, value) {
            var tbody = document.getElementById(tab + '-tbody');
            if (!tbody) return;
            var rows = tbody.querySelectorAll('tr');
            var val = value.toLowerCase();
            rows.forEach(function (row) {
                var cols = row.querySelectorAll('td');
                if (cols[colIndex]) {
                    var text = cols[colIndex].textContent.toLowerCase();
                    row.style.display = text.includes(val) ? '' : 'none';
                }
            });
        };

        function escapeHtml(str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        /* ─── TARGET SELECTION MODAL ─── */
        var targetPage = 1;
        var targetLastPage = 1;
        var targetData = [];
        var selectedTarget = null;

        function openTargetModal() {
            targetPage = 1;
            targetLastPage = 1;
            targetData = [];
            selectedTarget = null;
            document.getElementById('target-tbody').innerHTML = '<tr><td colspan="5" class="py-10 text-center text-slate-400 text-xs font-medium">Loading...</td></tr>';
            toggleModal('target-modal', true);
            loadTargets();
        }

        function loadTargets() {
            var tbody = document.getElementById('target-tbody');
            var params = '?page=' + targetPage;
            var s = buildTargetSearchParams();
            if (s) params += '&' + s;

            fetch('<?php echo e(route("special.item-convert.targets")); ?>' + params)
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.success) {
                        tbody.innerHTML = '<tr><td colspan="5" class="py-10 text-center text-slate-400 text-xs font-medium">Failed to load.</td></tr>';
                        return;
                    }
                    targetData = res.items || [];
                    if (res.pagination) {
                        targetPage = res.pagination.current_page;
                        targetLastPage = res.pagination.last_page;
                        updateTargetPagination();
                    }
                    renderTargetTable(targetData);
                })
                .catch(function () {
                    tbody.innerHTML = '<tr><td colspan="5" class="py-10 text-center text-slate-400 text-xs font-medium">Error loading targets.</td></tr>';
                });
        }

        function renderTargetTable(items) {
            var tbody = document.getElementById('target-tbody');
            tbody.innerHTML = '';
            if (!items || items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="py-10 text-center text-slate-400 text-xs font-medium">No items found.</td></tr>';
                return;
            }
            items.forEach(function (item, idx) {
                var tr = document.createElement('tr');
                tr.className = 'hover:bg-maroon/5 transition-colors cursor-pointer border-b border-slate-50';
                tr.onclick = function () { selectTarget(idx); };
                tr.innerHTML =
                    '<td class="py-4 px-5 text-slate-700 font-bold">' + escapeHtml(item.product_code) + '</td>' +
                    '<td class="py-4 px-5 text-slate-500">' + escapeHtml(item.part_number) + '</td>' +
                    '<td class="py-4 px-5 text-slate-500 max-w-[200px] truncate">' + escapeHtml(item.description) + '</td>' +
                    '<td class="py-4 px-5"><span class="px-2 py-0.5 bg-slate-100 rounded text-[10px] font-semibold">' + escapeHtml(item.application || 'N/A') + '</span></td>' +
                    '<td class="py-4 px-5 text-slate-500">' + escapeHtml(item.position || 'N/A') + '</td>';
                tbody.appendChild(tr);
            });
        }

        function selectTarget(idx) {
            selectedTarget = targetData[idx];
            if (!selectedTarget) return;

            document.getElementById('conv-target-code').textContent = selectedTarget.product_code || '---';
            document.getElementById('conv-target-part').textContent = selectedTarget.part_number || '---';
            document.getElementById('conv-target-app').textContent = selectedTarget.application || '---';
            document.getElementById('conv-target-desc').textContent = selectedTarget.description || '---';
            document.getElementById('conv-target-pos').textContent = selectedTarget.position || '---';
            document.getElementById('conv-target-avail').textContent = selectedTarget.available_qty || 0;
            document.getElementById('target-search-label').textContent = selectedTarget.product_code + ' - ' + (selectedTarget.description || '');

            toggleModal('target-modal', false);
        }

        function targetSearch() {
            targetPage = 1;
            loadTargets();
        }

        function buildTargetSearchParams() {
            var fields = { code: 'target-srch-code', part: 'target-srch-part', desc: 'target-srch-desc', app: 'target-srch-app', pos: 'target-srch-pos' };
            var parts = [];
            for (var key in fields) {
                var el = document.getElementById(fields[key]);
                if (el && el.value.trim()) {
                    parts.push(encodeURIComponent('search[' + key + ']') + '=' + encodeURIComponent(el.value.trim()));
                }
            }
            return parts.join('&');
        }

        function targetChangePage(dir) {
            var np = targetPage + dir;
            if (np < 1 || np > targetLastPage) return;
            targetPage = np;
            loadTargets();
        }

        function updateTargetPagination() {
            var info = document.getElementById('target-page-info');
            if (info) info.textContent = 'Page ' + targetPage + ' of ' + targetLastPage;
            var prev = document.getElementById('target-prev-page');
            var next = document.getElementById('target-next-page');
            if (prev) prev.disabled = targetPage <= 1;
            if (next) next.disabled = targetPage >= targetLastPage;
        }
    </script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('partials.special_user.special_sidebar_navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\hatdog\resources\views\Special_User\Inventory\item-convert.blade.php ENDPATH**/ ?>