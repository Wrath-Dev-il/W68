<?php $__env->startPush('styles'); ?>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');
        
        #page-payments {
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

        .modal-animate-in {
            animation: modalScaleUp 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        }

        @keyframes modalScaleUp {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        .animate-overdue-blink {
            animation: overdueBlink 1s ease-in-out infinite;
        }

        @keyframes overdueBlink {
            0%, 100% {
                background-color: rgba(254, 226, 226, 0.15);
                box-shadow: inset 0 0 0 1px rgba(239, 68, 68, 0.16);
            }
            50% {
                background-color: rgba(254, 202, 202, 0.4);
                box-shadow: inset 0 0 0 1px rgba(220, 38, 38, 0.3);
            }
        }

        .invoice-row-overdue td,
        .payor-row-overdue td {
            background-color: rgba(254, 242, 242, 0.72);
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

        /* Tag Styles */
        .tag-new {
            padding: 0.125rem 0.5rem;
            background-color: #eff6ff;
            color: #2563eb;
            font-size: 8px;
            font-weight: 900;
            text-transform: uppercase;
            border-radius: 0.375rem;
            border: 1px solid #dbeafe;
            margin-left: 0.25rem;
            display: inline-block;
        }

        .tag-partial {
            padding: 0.125rem 0.5rem;
            background-color: #fffbeb;
            color: #d97706;
            font-size: 8px;
            font-weight: 900;
            text-transform: uppercase;
            border-radius: 0.375rem;
            border: 1px solid #fef3c7;
            margin-left: 0.25rem;
            display: inline-block;
        }

        .tag-paid {
            padding: 0.125rem 0.5rem;
            background-color: #ecfdf5;
            color: #059669;
            font-size: 8px;
            font-weight: 900;
            text-transform: uppercase;
            border-radius: 0.375rem;
            border: 1px solid #d1fae5;
            margin-left: 0.25rem;
            display: inline-block;
        }

        /* Multi-line Tag System */
        .status-tag-container {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            vertical-align: middle;
            margin-left: 0.5rem;
            gap: 2px;
        }

        .tag-count {
            padding: 1px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: 800;
            line-height: 1;
        }

        .tag-date {
            font-size: 8px;
            font-weight: 600;
            color: #64748b;
            line-height: 1;
            font-family: monospace;
        }

        /* Color Variations */
        .bg-blue-tag { background-color: #eff6ff; color: #2563eb; border: 1px solid #dbeafe; }
        .bg-amber-tag { background-color: #fffbeb; color: #d97706; border: 1px solid #fef3c7; }
        .bg-emerald-tag { background-color: #ecfdf5; color: #059669; border: 1px solid #d1fae5; }

        /* Step Indicator Styles */
        .step-indicator {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 10px;
            transition: all 0.3s ease;
        }

        .step-indicator.active {
            background-color: #FFD700;
            color: #800000;
            font-size: 11px;
            font-weight: 900;
            box-shadow: 0 0 12px rgba(255, 215, 0, 0.4);
        }

        .step-indicator.pending {
            background-color: #6f0f1d;
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.28);
        }

        .step-indicator.completed {
            background-color: #FFD700;
            color: #800000;
            font-weight: 900;
        }

        .payment-step-line {
            background: linear-gradient(90deg, #FFD700 0%, rgba(255, 215, 0, 0.3) 100%);
            max-width: calc(100% - 2.5rem);
        }

        .payment-stepper-line {
            z-index: 0;
            pointer-events: none;
        }

        .payment-stepper-item {
            position: relative;
            z-index: 1;
        }

        .payment-info-panel {
            background: #ffffff;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
        }

        .payment-info-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.8rem;
            border: 1px solid #f1f5f9;
            border-radius: 0.8rem;
            background: #ffffff;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
        }

        .payment-info-icon {
            width: 2rem;
            height: 2rem;
            border-radius: 0.65rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background: #fff7d6;
            color: #800000;
            border: 1px solid rgba(255, 215, 0, 0.55);
        }

        .payment-left-hero {
            position: relative;
            overflow: hidden;
            border-radius: 1.25rem;
            background:
                radial-gradient(circle at 85% 5%, rgba(255, 215, 0, 0.32), transparent 32%),
                linear-gradient(145deg, #800000 0%, #4b0710 72%);
            color: #ffffff;
            box-shadow: 0 18px 35px rgba(128, 0, 0, 0.22);
        }

        .payment-left-hero::after {
            content: "";
            position: absolute;
            inset: auto -20% -45% 18%;
            height: 8rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            transform: rotate(-12deg);
        }

        .payment-mini-stat {
            border-radius: 0.9rem;
            border: 1px solid #f1f5f9;
            background: #f8fafc;
            padding: 0.85rem;
        }

        .payment-pane-scroll {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 transparent;
            overscroll-behavior: contain;
        }

        .payment-step-one {
            min-height: 0;
            overflow: hidden;
        }

        .payment-step-one-grid {
            min-height: 0;
            overflow: hidden;
        }

        .payment-left-pane,
        .payment-right-pane {
            min-height: 0;
            overflow-y: auto;
        }

        @media (min-width: 1024px) {
            .payment-left-pane {
                height: 100%;
            }

            .payment-right-pane {
                min-width: 0;
                height: 100%;
            }
        }

        .payments-tab-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            border-radius: 0.75rem;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            color: #64748b;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            transition: all 0.2s ease;
        }

        .payments-tab-button.active {
            background: #800000;
            color: #ffffff;
            border-color: #800000;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
        }

        .payments-tab-button:not(.active):hover {
            border-color: #800000;
            color: #800000;
        }

        .payments-history-action {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.5rem 0.8rem;
            border-radius: 0.8rem;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            transition: all 0.2s ease;
        }

        .payments-history-action:hover {
            transform: translateY(-1px);
        }
    </style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('payments_content'); ?>
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h2 class="text-lg font-black text-slate-800 tracking-tight">Payments Workspace</h2>
            <p class="text-xs font-semibold text-slate-400 mt-1">Manage active collections and payment history in one place.</p>
        </div>
    </div>

    <!-- DASHBOARD CARDS (Aging) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6">
        <?php $__currentLoopData = [
            ['30', '0 Payors', 'calendar', 'text-emerald-500', 'Unpaid', 'up'],
            ['60', '0 Payors', 'clock', 'text-amber-500', 'Unpaid', 'up'],
            ['90', '0 Payors', 'alert-circle', 'text-slate-400', 'Unpaid', 'minus'],
            ['120', '0 Payors', 'alert-triangle', 'text-red-500', 'Unpaid', 'down'],
            ['150', '0 Payors', 'shield-alert', 'text-red-600', 'Unpaid', 'down']
        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between group hover:border-slate-200 transition-all hover:shadow-md hover:scale-[1.02] duration-300 overflow-hidden">
            <div class="space-y-0.5 min-w-0">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1"><?php echo e($stat[0]); ?> DAYS</span>
                <h3 id="stat-<?php echo e($stat[0]); ?>-days" class="text-2xl font-extrabold text-slate-800 tracking-tight truncate"><?php echo e($stat[1]); ?></h3>
                <div class="flex items-center gap-1.5 mt-1">
                    <span class="<?php echo e($stat[3]); ?> font-bold text-[10px] flex items-center gap-0.5 whitespace-nowrap">
                        <?php if($stat[5] === 'up'): ?>
                            <i data-lucide="trending-up" class="w-3 h-3"></i>
                        <?php elseif($stat[5] === 'down'): ?>
                            <i data-lucide="trending-down" class="w-3 h-3"></i>
                        <?php else: ?>
                            <i data-lucide="minus" class="w-3 h-3"></i>
                        <?php endif; ?>
                        <?php echo e($stat[4]); ?>

                    </span>
                    <span class="text-[10px] text-slate-400 font-medium whitespace-nowrap">payors with open invoices</span>
                </div>
            </div>
            <div class="w-12 h-12 rounded-xl bg-maroon flex items-center justify-center shadow-inner group-hover:scale-105 transition-transform duration-300 flex-shrink-0 ml-3">
                <i data-lucide="<?php echo e($stat[2]); ?>" class="w-6 h-6 text-gold"></i>
            </div>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>

    <!-- PAYMENTS TABS -->
    <div class="flex flex-wrap items-center gap-3 mt-6">
        <button class="payments-tab-button active" id="payments-tab-active" onclick="window.showPaymentsTab('active')">
            <i data-lucide="users" class="w-4 h-4"></i> Active Accounts
        </button>
        <button class="payments-tab-button" id="payments-tab-history" onclick="window.showPaymentsTab('history')">
            <i data-lucide="history" class="w-4 h-4"></i> Payment History
        </button>
        <button class="payments-tab-button" id="payments-tab-groups" onclick="window.showPaymentsTab('groups')">
            <i data-lucide="folder-open" class="w-4 h-4"></i> Grouped Invoices
        </button>
    </div>

    <!-- ACTIVE ACCOUNTS TAB -->
    <div id="payments-active-view" class="mt-6">
    <!-- MAIN TABLE (Active Accounts only) -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-50 bg-slate-50/50 flex items-center justify-between">
            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-widest">Active Accounts</h3>
            <div class="flex items-center space-x-2">
                <span class="text-[10px] text-slate-400 font-medium">Showing all payors with outstanding balances</span>
            </div>
        </div>
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-slate-400 text-[10px] font-bold uppercase tracking-widest">
                        <th class="py-5 px-6">Payor's Name</th>
                        <th class="py-5 px-6 text-center">Closed P.O</th>
                        <th class="py-5 px-6 text-center">Partial Paid</th>
                        <th class="py-5 px-6 text-center">Paid</th>
                        <th class="py-5 px-6 text-center">Total Due</th>
                        <th class="py-5 px-6 text-center">Action</th>
                    </tr>
                    <tr id="payments-active-filter-row" class="bg-white border-b border-slate-100">
                        <th class="p-2 px-6"><input type="text" data-col="0" onkeyup="window.filterPaymentsTable()" class="col-search-input text-[10px] w-full" placeholder="Search Payor..."></th>
                        <th class="p-2 px-6"><input type="text" data-col="1" onkeyup="window.filterPaymentsTable()" class="col-search-input text-[10px] w-full text-center" placeholder="Search P.O..."></th>
                        <th class="p-2 px-6"><input type="text" data-col="2" onkeyup="window.filterPaymentsTable()" class="col-search-input text-[10px] w-full text-center" placeholder="Search Partial..."></th>
                        <th class="p-2 px-6"><input type="text" data-col="3" onkeyup="window.filterPaymentsTable()" class="col-search-input text-[10px] w-full text-center" placeholder="Search Paid..."></th>
                        <th class="p-2 px-6"><input type="text" data-col="4" onkeyup="window.filterPaymentsTable()" class="col-search-input text-[10px] w-full text-center" placeholder="Search Due..."></th>
                        <th class="p-2 px-6"><input type="text" data-col="5" onkeyup="window.filterPaymentsTable()" class="col-search-input text-[10px] w-full text-center" placeholder="Search Action..."></th>
                    </tr>
                </thead>
                <tbody id="payments-tbody" class="divide-y divide-slate-100 text-xs text-slate-600 font-medium">
                    <!-- JS Rendered via loadPaymentsTable() -->
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100 bg-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <span id="payments-page-info" class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Showing 0 of 0 payors</span>
            <div class="flex items-center gap-2">
                <button id="payments-prev-btn" onclick="window.changePaymentsPage(-1)" class="px-4 py-2 rounded-lg border border-slate-200 text-[10px] font-black uppercase tracking-widest text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Previous</button>
                <button id="payments-next-btn" onclick="window.changePaymentsPage(1)" class="px-4 py-2 rounded-lg bg-maroon text-white text-[10px] font-black uppercase tracking-widest hover:bg-maroon-800 disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
            </div>
        </div>
    </div>
    </div>

    <!-- PAYMENT HISTORY TAB -->
    <div id="payments-history-view" class="hidden mt-6">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-50 bg-slate-50/50 flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-xs font-bold text-slate-700 uppercase tracking-widest">Payment History</h3>
                    <p class="text-[10px] text-slate-400 font-medium mt-1">One row per Payor. Click View to see that Payor's Payment Nos.</p>
                </div>
                <span class="rounded-full bg-maroon/5 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-maroon">Payor Summary</span>
            </div>
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full min-w-[760px] text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-400 text-[10px] font-bold uppercase tracking-widest">
                            <th class="py-4 px-5">Payor's Name</th>
                            <th class="py-4 px-5 text-center">Paid Invoices</th>
                            <th class="py-4 px-5 text-center">Latest Paid Date</th>
                            <th class="py-4 px-5 text-center">Action</th>
                        </tr>
                        <tr class="bg-white border-t border-slate-100">
                            <th class="p-2 px-5">
                                <input id="payments-history-payor-search" type="text" oninput="window.filterPaymentsHistoryTable()" class="col-search-input w-full text-[9px]" placeholder="Search Payor's Name...">
                            </th>
                            <th class="p-2 px-5"></th>
                            <th class="p-2 px-5"></th>
                            <th class="p-2 px-5"></th>
                        </tr>
                    </thead>
                    <tbody id="payments-history-tbody" class="divide-y divide-slate-100 text-xs text-slate-600 font-medium">
                        <tr><td colspan="4" class="py-12 text-center text-slate-400 font-bold">Loading payment history...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-slate-100 bg-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <span id="payments-history-page-info" class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Showing 0 of 0 payors</span>
                <div class="flex items-center gap-2">
                    <button id="payments-history-prev-btn" onclick="window.changePaymentsHistoryPage(-1)" class="px-4 py-2 rounded-lg border border-slate-200 text-[10px] font-black uppercase tracking-widest text-slate-500 hover:bg-slate-50 disabled:opacity-40">Previous</button>
                    <button id="payments-history-next-btn" onclick="window.changePaymentsHistoryPage(1)" class="px-4 py-2 rounded-lg bg-maroon text-white text-[10px] font-black uppercase tracking-widest hover:bg-maroon-800 disabled:opacity-40">Next</button>
                </div>
            </div>
        </div>
    </div>
        <div id="payments-groups-view" class="mt-6 hidden">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-50 bg-slate-50/50 flex items-center justify-between gap-3 flex-wrap">
                <h3 class="text-xs font-bold text-slate-700 uppercase tracking-widest">Grouped Invoices</h3>
                <div class="flex items-center gap-2 flex-wrap justify-end">
                    <select id="payments-groups-activity-filter" onchange="window.changeGroupsActivityFilter(this.value)" class="col-search-input text-[10px] min-w-[130px] font-black uppercase tracking-wider">
                        <option value="active" selected>Active</option>
                        <option value="not_active">Not Active</option>
                        <option value="all">All</option>
                    </select>
                    <input type="text" id="payments-groups-search" onkeyup="window.filterGroupsTable()" class="col-search-input text-[10px] w-72" placeholder="Search group title, invoice no, date...">
                </div>
            </div>
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-400 text-[10px] font-bold uppercase tracking-widest">
                            <th class="py-5 px-6">Group Title</th>
                            <th class="py-5 px-6 text-center">Total Invoices</th>
                            <th class="py-5 px-6 text-center">Total Amount</th>
                            <th class="py-5 px-6 text-center">Grouped Date</th>
                            <th class="py-5 px-6 text-center">Status</th>
                            <th class="py-5 px-6 text-center">Action</th>
                        </tr>
                        <tr id="payments-groups-filter-row" class="bg-white border-b border-slate-100">
                            <th class="p-2 px-6"><input type="text" data-col="0" onkeyup="window.filterTableByColumns('payments-groups-tbody','payments-groups-filter-row')" class="col-search-input text-[10px] w-full" placeholder="Search Group..."></th>
                            <th class="p-2 px-6"><input type="text" data-col="1" onkeyup="window.filterTableByColumns('payments-groups-tbody','payments-groups-filter-row')" class="col-search-input text-[10px] w-full" placeholder="Search Count..."></th>
                            <th class="p-2 px-6"><input type="text" data-col="2" onkeyup="window.filterTableByColumns('payments-groups-tbody','payments-groups-filter-row')" class="col-search-input text-[10px] w-full" placeholder="Search Amount..."></th>
                            <th class="p-2 px-6"><input type="text" data-col="3" onkeyup="window.filterTableByColumns('payments-groups-tbody','payments-groups-filter-row')" class="col-search-input text-[10px] w-full" placeholder="Search Date..."></th>
                            <th class="p-2 px-6"><input type="text" data-col="4" onkeyup="window.filterTableByColumns('payments-groups-tbody','payments-groups-filter-row')" class="col-search-input text-[10px] w-full" placeholder="Search Status..."></th>
                            <th class="p-2 px-6"><input type="text" data-col="5" onkeyup="window.filterTableByColumns('payments-groups-tbody','payments-groups-filter-row')" class="col-search-input text-[10px] w-full" placeholder="Search Action..."></th>
                        </tr>
                    </thead>
                    <tbody id="payments-groups-tbody" class="divide-y divide-slate-100 text-xs text-slate-600 font-medium">
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-slate-100 bg-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <span id="payments-groups-page-info" class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Showing 0 of 0 groups</span>
                <div class="flex items-center gap-2">
                    <button id="payments-groups-prev-btn" onclick="window.changeGroupsPage(-1)" class="px-4 py-2 rounded-lg border border-slate-200 text-[10px] font-black uppercase tracking-widest text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Previous</button>
                    <button id="payments-groups-next-btn" onclick="window.changeGroupsPage(1)" class="px-4 py-2 rounded-lg bg-maroon text-white text-[10px] font-black uppercase tracking-widest hover:bg-maroon-800 disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
                </div>
            </div>
        </div>
    </div>

    <!-- PROCEED MODAL (Multi-Step: Invoice -> Details -> Returns -> Review) -->
    <div id="proceed-modal" class="fixed inset-0 z-[500] hidden flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div onclick="window.toggleModal('proceed-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all w-full max-w-[96vw] h-[calc(100vh-2rem)] modal-animate-in flex flex-col">
            <!-- Modal Header -->
            <div class="bg-maroon p-6 flex flex-col space-y-6 text-white flex-shrink-0">
                <div class="flex justify-between items-center">
                    <div class="flex items-center space-x-3">
                        <div class="p-2 bg-white/10 rounded-lg">
                            <i data-lucide="credit-card" class="w-5 h-5 text-gold"></i>
                        </div>
                        <div>
                            <h3 id="process-payment-title" class="text-lg font-bold uppercase tracking-widest">Process Payment</h3>
                            <p id="process-payment-subtitle" class="text-[10px] font-semibold text-white/60 mt-1">Create a new posted payment record.</p>
                        </div>
                    </div>
                    <button onclick="window.toggleModal('proceed-modal', false)" class="text-white/70 hover:text-white transition-colors">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>

                <!-- STEP INDICATOR -->
                <div class="flex items-center justify-center max-w-md mx-auto w-full relative pb-2">
                    <div class="payment-stepper-line absolute top-4 left-5 right-5 h-0.5 bg-white/20"></div>
                    <div class="payment-step-line payment-stepper-line absolute top-4 left-5 h-0.5 bg-gold transition-all duration-500" style="width: 0%;"></div>
                    
                    <div class="relative flex justify-between w-full">
                        <div class="payment-stepper-item flex flex-col items-center space-y-2">
                            <div class="payment-step-indicator-1 step-indicator active">1</div>
                            <span class="text-[9px] font-bold uppercase tracking-widest text-white">Invoice</span>
                        </div>
                        <div class="payment-stepper-item flex flex-col items-center space-y-2">
                            <div class="payment-step-indicator-2 step-indicator pending">2</div>
                            <span class="text-[9px] font-bold uppercase tracking-widest text-white/50">Details</span>
                        </div>
                        <div class="payment-stepper-item flex flex-col items-center space-y-2 sudden-returns-step-indicator">
                            <div class="payment-step-indicator-3 step-indicator pending">3</div>
                            <span class="text-[9px] font-bold uppercase tracking-widest text-white/50">Returns</span>
                        </div>
                        <div class="payment-stepper-item flex flex-col items-center space-y-2">
                            <div class="payment-step-indicator-4 step-indicator pending">4</div>
                            <span class="text-[9px] font-bold uppercase tracking-widest text-white/50">Review</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-6 flex-1 min-h-0 flex flex-col overflow-hidden">
                    <!-- STEP 1: BASIC INFO & INVOICE SELECTION -->
                    <div id="step-1" class="step-content payment-step-one flex-1">
                        <div class="payment-step-one-grid grid grid-cols-1 lg:grid-cols-[minmax(280px,30%)_1fr] gap-6 h-full items-stretch">
                            <!-- Left Side: Basic Info (30%) -->
                            <div class="payment-left-pane payment-info-panel payment-pane-scroll custom-scrollbar rounded-2xl border border-slate-100 p-4 space-y-4">
    <div class="payment-left-hero p-5">
        <div class="relative z-10 flex items-start justify-between gap-3"><div><p class="text-[9px] font-black uppercase tracking-[0.24em] text-gold/90">Payment Desk</p><h4 class="mt-2 text-xl font-black leading-tight">Current transaction</h4></div><div class="w-11 h-11 rounded-xl bg-white/12 border border-white/15 flex items-center justify-center"><i data-lucide="wallet-cards" class="w-5 h-5 text-gold"></i></div></div>
        <div class="relative z-10 mt-6"><span class="text-[10px] font-black uppercase tracking-widest text-white/55">Total Payment</span><div id="p-total-payment" class="mt-2 text-3xl font-black tracking-tight text-gold">PHP 0.00</div></div>
    </div>
    <div id="process-left-tabs" class="flex rounded-xl bg-slate-100 p-1"><button id="process-left-tab-current" type="button" onclick="window.switchProcessLeftTab('current')" class="flex-1 rounded-lg bg-white px-3 py-2 text-[9px] font-black uppercase tracking-widest text-maroon shadow-sm">Current Transaction</button><button id="process-left-tab-selected" type="button" onclick="window.switchProcessLeftTab('selected')" class="flex-1 rounded-lg px-3 py-2 text-[9px] font-black uppercase tracking-widest text-slate-400">Selected Invoice</button></div>
    <div id="process-left-current-panel" class="space-y-3">
        <div class="payment-info-row"><div class="payment-info-icon"><i data-lucide="hash" class="w-4 h-4"></i></div><div class="min-w-0"><label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Payment No</label><div id="p-payment-no" class="text-xs font-extrabold text-slate-800 truncate">---</div></div></div>
        <div class="payment-info-row"><div class="payment-info-icon"><i data-lucide="calendar-days" class="w-4 h-4"></i></div><div class="min-w-0"><label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Payment Date</label><div id="p-date" class="text-xs font-extrabold text-slate-800 truncate">---</div></div></div>
        <div class="payment-info-row"><div class="payment-info-icon"><i data-lucide="building-2" class="w-4 h-4"></i></div><div class="min-w-0"><label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Payor's Name</label><div id="p-payor-name" class="text-xs font-extrabold text-maroon truncate">---</div></div></div>
        <div class="payment-info-row"><div class="payment-info-icon"><i data-lucide="phone" class="w-4 h-4"></i></div><div class="min-w-0"><label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Contact</label><div id="p-contact" class="text-xs font-extrabold text-slate-800 truncate">---</div></div></div>
        <div class="payment-info-row items-start"><div class="payment-info-icon"><i data-lucide="map-pin" class="w-4 h-4"></i></div><div class="min-w-0"><label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Address</label><div id="p-address" class="text-xs font-extrabold text-slate-800 leading-relaxed">---</div></div></div>
        <div class="grid grid-cols-2 gap-3"><div class="payment-mini-stat"><span class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Selected</span><div class="mt-1 flex items-center gap-2"><i data-lucide="list-checks" class="w-4 h-4 text-maroon"></i><span id="p-selected-count" class="text-sm font-black text-slate-800">0</span></div></div><div class="payment-mini-stat"><span class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Status</span><div class="mt-1 text-sm font-black text-slate-800">Open</div></div></div>
    </div>
    <div id="process-left-selected-panel" class="hidden space-y-3">
        <div class="rounded-xl border border-maroon/10 bg-maroon/5 p-4">
            <div class="flex items-center justify-between gap-3">
                <div><p class="text-[9px] font-black uppercase tracking-widest text-maroon">Selected Invoice Trail</p><p class="mt-1 text-[9px] font-semibold text-slate-400">Checked invoices appear here in selection order.</p></div>
                <span id="selected-invoice-trail-count" class="rounded-full bg-white px-2.5 py-1 text-[9px] font-black text-maroon shadow-sm">0 selected</span>
            </div>
        </div>
        <div class="max-h-[360px] overflow-auto rounded-xl border border-slate-100 custom-scrollbar">
            <table class="w-full min-w-[480px] text-left text-[9px]">
                <thead class="sticky top-0 z-10 bg-slate-50 text-slate-400 uppercase tracking-widest">
                    <tr><th class="p-3">Invoice</th><th class="p-3 text-right">Paid Amount</th><th class="p-3">Remarks</th></tr>
                </thead>
                <tbody id="selected-invoice-trail-tbody" class="divide-y divide-slate-100">
                    <tr><td colspan="3" class="p-5 text-center font-bold text-slate-400">No selected invoices yet.</td></tr>
                </tbody>
            </table>
        </div>
        <p class="text-[9px] font-semibold text-slate-400">Checking an invoice automatically opens this tab. Paid Amount and Remarks update live as you type.</p>
    </div>
</div>

                            

                            <!-- Right Side: Invoice Selection (70%) -->
                            <div class="payment-right-pane payment-pane-scroll custom-scrollbar space-y-4 pr-2">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="text-[10px] font-bold text-slate-400 uppercase">Select Invoices to Pay</h4>
                                        <p class="text-[10px] text-slate-400 font-semibold mt-1">Open invoices and payment allocation</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button onclick="clearInvoiceFilters()" class="hidden text-[9px] text-maroon font-bold hover:underline" id="btn-clear-invoice-filters">Clear Filters</button>
                                        <button onclick="window.openPaymentGroupModal()" class="payments-history-action text-white" style="background:#800000;" title="Create an invoice group from selected invoices">
                                            <i data-lucide="folder-plus" class="w-3.5 h-3.5"></i> Group
                                        </button>
                                    </div>
                                </div>
                                <div class="overflow-x-auto border border-slate-100 rounded-2xl">
                                    <table class="w-full min-w-[1350px] text-left">
                                        <thead class="bg-slate-50 text-slate-400 text-[9px] font-bold uppercase tracking-wider">
                                            <tr>
                                                <th class="p-4 text-center"><input type="checkbox" id="select-all-invoices" class="accent-maroon"></th>
                                                <th class="p-4">P.O</th>
                                                <th class="p-4">Invoice No</th>
                                                <th class="p-4">Invoice Date</th>
                                                <th class="p-4">Invoice Amount</th>
                                                <th class="p-4">Amount Due</th>
                                                <th class="p-4">Total Returns</th>
                                                <th class="p-4">Adj</th>
                                                <th class="p-4">Paid</th>
                                                <th class="p-4">Remarks</th>
                                            </tr>
                                            <tr class="bg-white border-t border-slate-100">
                                                <th class="p-1 text-center"></th>
                                                <th class="p-1"><input type="text" class="invoice-filter-input w-full px-2 py-1 text-[8px] border border-slate-200 rounded outline-none bg-slate-50" data-col="1" placeholder="Filter P.O..."></th>
                                                <th class="p-1"><input type="text" class="invoice-filter-input w-full px-2 py-1 text-[8px] border border-slate-200 rounded outline-none bg-slate-50" data-col="2" placeholder="Filter Invoice..."></th>
                                                <th class="p-1"><input type="text" class="invoice-filter-input w-full px-2 py-1 text-[8px] border border-slate-200 rounded outline-none bg-slate-50" data-col="3" placeholder="Filter Date..."></th>
                                                <th class="p-1"><input type="text" class="invoice-filter-input w-full px-2 py-1 text-[8px] border border-slate-200 rounded outline-none bg-slate-50" data-col="4" placeholder="Filter Invoice Amount..."></th>
                                                <th class="p-1"><input type="text" class="invoice-filter-input w-full px-2 py-1 text-[8px] border border-slate-200 rounded outline-none bg-slate-50" data-col="5" placeholder="Filter Amount Due..."></th>
                                                <th class="p-1"><input type="text" class="invoice-filter-input w-full px-2 py-1 text-[8px] border border-slate-200 rounded outline-none bg-slate-50" data-col="6" placeholder="Filter Returns..."></th>
                                                <th class="p-1"><input type="text" class="invoice-filter-input w-full px-2 py-1 text-[8px] border border-slate-200 rounded outline-none bg-slate-50" data-col="7" placeholder="Filter Adj..."></th>
                                                <th class="p-1"><input type="text" class="invoice-filter-input w-full px-2 py-1 text-[8px] border border-slate-200 rounded outline-none bg-slate-50" data-col="8" placeholder="Filter Paid..."></th>
                                                <th class="p-1"><input type="text" class="invoice-filter-input w-full px-2 py-1 text-[8px] border border-slate-200 rounded outline-none bg-slate-50" data-col="9" placeholder="Filter Remarks..."></th>
                                            </tr>
                                        </thead>
                                        <tbody id="modal-invoices-tbody" class="text-[10px] divide-y divide-slate-50">
                                            <!-- JS Rendered -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 2: PAYMENT DETAILS -->
                    <div id="step-2" class="step-content hidden flex-1 min-h-0 overflow-y-auto custom-scrollbar pr-2 space-y-6">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Payment Details</h4>
                                <p class="text-[9px] font-semibold text-slate-400 mt-1">Computed from all Credit Amount entries below.</p>
                            </div>
                            <div class="flex flex-wrap items-center justify-end gap-3">
                                <div class="rounded-xl border border-maroon/10 bg-maroon/5 px-4 py-2.5 text-right min-w-[170px]">
                                    <div class="text-[8px] font-black uppercase tracking-widest text-slate-400">Computed Total</div>
                                    <div id="payment-details-computed-total" class="mt-0.5 text-lg font-black text-maroon">PHP 0.00</div>
                                </div>
                                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-right min-w-[170px]">
                                    <div class="text-[8px] font-black uppercase tracking-widest text-slate-400">Remaining</div>
                                    <div id="payment-details-remaining" class="mt-0.5 text-lg font-black text-amber-600">PHP 0.00</div>
                                    <div class="mt-0.5 text-[8px] font-bold text-slate-400">Selected payment − computed total</div>
                                </div>
                                <button onclick="window.addCheckInput()" class="px-4 py-2 bg-maroon text-white text-[10px] font-bold rounded-xl hover:bg-maroon-800 transition-all flex items-center space-x-2">
                                    <i data-lucide="plus" class="w-3.5 h-3.5 text-gold"></i>
                                    <span>ADD CHECK NO.</span>
                                </button>
                            </div>
                        </div>
                        
                        <div id="check-inputs-container" class="space-y-4">
                            <div class="grid grid-cols-5 gap-4 p-5 bg-slate-50 rounded-2xl border border-slate-100 relative check-row">
                                <button onclick="window.removeCheckInput(this)" class="absolute -top-2 -right-2 w-6 h-6 bg-white border border-slate-200 rounded-full flex items-center justify-center text-slate-400 hover:text-red-600 hover:border-red-300 transition-colors shadow-sm" title="Remove this payment"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
                                <div class="space-y-1.5">
                                    <label class="text-[10px] font-bold text-slate-400 uppercase">Bank Name</label>
                                    <input type="text" class="bank-name-input w-full px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs outline-none focus:border-maroon" placeholder="Auto-fetched" readonly>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-[10px] font-bold text-slate-400 uppercase">Account No</label>
                                    <input type="text" class="account-no-input w-full px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs outline-none focus:border-maroon" placeholder="Auto-fetched" readonly>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-[10px] font-bold text-slate-400 uppercase">Check No</label>
                                    <input type="text" class="w-full px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs outline-none focus:border-maroon" placeholder="Enter check #">
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-[10px] font-bold text-slate-400 uppercase">Check Date</label>
                                    <input type="date" class="w-full px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs outline-none focus:border-maroon">
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-[10px] font-bold text-slate-400 uppercase">Credit Amount</label>
                                    <input type="number" oninput="window.calculateCheckTotal()" class="w-full px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs outline-none focus:border-maroon check-amount" placeholder="0.00">
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- STEP 3: RETURNS -->
                    <div id="step-3" class="step-content hidden flex-1 min-h-0 overflow-y-auto custom-scrollbar pr-2 space-y-6">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Returns</h4>
                                <p class="text-[10px] text-slate-400 font-semibold mt-1">Optional print-only fields. Enter Return Order ID, Wallet & Adjustment, and Return No. manually, or leave the row blank. Nothing is auto-filled or saved to the payment.</p>
                            </div>
                            <button type="button" onclick="window.addManualReturnRow('payment')" class="px-4 py-2 rounded-xl border border-maroon/20 bg-maroon/5 text-maroon text-[9px] font-black uppercase tracking-widest hover:bg-maroon/10 flex items-center gap-2"><i data-lucide="plus" class="w-3.5 h-3.5"></i>Add Return</button>
                        </div>
                        <div class="overflow-x-auto border border-slate-100 rounded-2xl">
                            <table class="w-full text-left min-w-[760px]">
                                <thead class="bg-slate-50 text-slate-400 text-[9px] font-bold uppercase tracking-wider">
                                    <tr>
                                        <th class="p-4">Return Order ID</th>
                                        <th class="p-4 text-right">Wallet &amp; Adjustment</th>
                                        <th class="p-4">Return No.</th>
                                        <th class="p-4 text-center">Action</th>
                                        <th class="p-4 text-center">Print</th>
                                    </tr>
                                </thead>
                                <tbody id="sudden-returns-tbody" class="text-[10px] divide-y divide-slate-50">
                                    <!-- Manual Return rows rendered by payments.js -->
                                </tbody>
                            </table>
                        </div>
                    </div>


                    <!-- STEP 4: REVIEW -->
                    <div id="step-4" class="step-content hidden flex-1 min-h-0 overflow-y-auto custom-scrollbar pr-2 space-y-6">
                        <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest border-b pb-2">Review Payment Summary</h4>
                        <div id="review-content" class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <!-- Populated via JS -->
                        </div>
                    </div>

                    <!-- Footer Actions -->
                    <div class="flex items-center justify-between border-t border-slate-100 bg-white pt-5 mt-5 space-x-3 flex-shrink-0">
                        <button id="btn-back" onclick="window.changeStep(-1)" class="px-8 py-3 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-xl hover:bg-slate-200 transition-all uppercase tracking-widest hidden">Back</button>
                        <div class="flex-1"></div>
                        <div class="flex items-center space-x-3">
                            <button id="btn-mark-paid" onclick="window.markAllSelectedAsPaid()" class="px-6 py-3 bg-gold text-maroon text-[10px] font-bold rounded-xl hover:bg-yellow-400 transition-all uppercase tracking-widest flex items-center space-x-2 shadow-lg shadow-gold/10">
                                <i data-lucide="check-circle" class="w-4 h-4"></i>
                                <span>Mark Selected as Paid</span>
                            </button>
                            <button id="btn-print-invoice" onclick="window.printPaymentInvoice()" class="px-6 py-3 bg-white border border-maroon/20 text-maroon text-[10px] font-bold rounded-xl hover:bg-maroon/5 transition-all uppercase tracking-widest flex items-center space-x-2 hidden">
                                <i data-lucide="printer" class="w-4 h-4"></i>
                                <span>Print Invoice</span>
                            </button>
                            <button id="btn-next" onclick="window.changeStep(1)" class="px-10 py-3 bg-maroon text-white text-[10px] font-bold rounded-xl shadow-lg hover:shadow-maroon/20 transition-all uppercase tracking-widest">Next Step</button>
                        </div>
                        <button id="btn-finalize" onclick="window.finalizePayment()" class="px-10 py-3 bg-emerald-500 text-white text-[10px] font-bold rounded-xl shadow-lg hover:shadow-emerald-500/20 transition-all uppercase tracking-widest hidden">Finalize Payment</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- RETURN DETAILS MODAL -->
    <div id="payment-return-details-modal" class="fixed inset-0 z-[550] hidden items-center justify-center p-4" role="dialog" aria-modal="true">
        <div onclick="window.closeReturnDetailsModal()" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all w-full max-w-[960px] max-h-[85vh] modal-animate-in flex flex-col">
            <div class="bg-maroon p-5 flex items-center justify-between text-white flex-shrink-0">
                <div class="flex items-center space-x-3">
                    <div class="p-2 bg-white/10 rounded-lg">
                        <i data-lucide="rotate-ccw" class="w-5 h-5 text-gold"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold uppercase tracking-widest">Return Details</h3>
                        <p class="text-[10px] font-semibold text-white/60 mt-1">Sales returns linked to this customer.</p>
                    </div>
                </div>
                <button onclick="window.closeReturnDetailsModal()" class="text-white/70 hover:text-white transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <div class="p-5 flex-1 min-h-0 overflow-y-auto custom-scrollbar" id="return-details-content">
                <div class="text-center py-12 text-slate-400 font-bold text-[10px]">Select a return to view details.</div>
            </div>
            <div class="p-4 border-t border-slate-100 flex justify-end flex-shrink-0">
                <button onclick="window.closeReturnDetailsModal()" class="px-6 py-2.5 bg-maroon text-white text-[10px] font-bold rounded-xl hover:bg-maroon-800 transition-all uppercase tracking-widest">Close</button>
            </div>
        </div>
    </div>

    <!-- FINALIZE CONFIRMATION MODAL -->
    <div id="payment-confirm-modal" class="fixed inset-0 z-[650] hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"></div>
        <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl overflow-hidden">
            <div class="bg-maroon p-5 text-white">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-xl bg-white/10 flex items-center justify-center">
                        <i data-lucide="shield-check" class="w-5 h-5 text-gold"></i>
                    </div>
                    <div>
                        <h3 id="payment-confirm-title" class="text-sm font-black uppercase tracking-widest">Confirm Payment</h3>
                        <p id="payment-confirm-subtitle" class="text-[10px] font-semibold text-white/60 mt-1">Review before posting to accounting.</p>
                    </div>
                </div>
            </div>
            <div class="p-6 space-y-4">
                <p id="payment-confirm-message" class="text-sm text-slate-600 font-semibold leading-relaxed">Are you sure you want to finalize this payment?</p>
                <div id="payment-overdue-warning" class="hidden rounded-2xl border border-red-200 bg-red-50 p-4">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 flex h-9 w-9 items-center justify-center rounded-xl bg-red-100 text-red-600">
                            <i data-lucide="triangle-alert" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <h4 class="text-[11px] font-black uppercase tracking-widest text-red-700">Exceeded Terms Warning</h4>
                            <p id="payment-overdue-warning-text" class="mt-1 text-xs font-semibold leading-relaxed text-red-600"></p>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <button onclick="window.closePaymentConfirmModal()" class="px-5 py-2.5 rounded-xl bg-slate-100 text-slate-600 text-[10px] font-black uppercase tracking-widest hover:bg-slate-200">Cancel</button>
                    <button id="payment-confirm-submit-btn" onclick="window.submitFinalizePayment()" class="px-5 py-2.5 rounded-xl bg-maroon text-white text-[10px] font-black uppercase tracking-widest hover:bg-maroon-800">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <!-- PAYMENT SUCCESS MODAL -->
    <div id="payment-success-modal" class="fixed inset-0 z-[650] hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"></div>
        <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl overflow-hidden">
            <div class="p-8 text-center">
                <div class="mx-auto w-16 h-16 rounded-2xl bg-emerald-50 text-emerald-500 flex items-center justify-center">
                    <i data-lucide="check-circle-2" class="w-9 h-9"></i>
                </div>
                <h3 id="payment-success-title" class="mt-5 text-lg font-black text-slate-800 uppercase tracking-widest">Payment Posted</h3>
                <p id="payment-success-message" class="mt-2 text-sm font-semibold text-slate-500">The payment was saved successfully.</p>
                <button onclick="window.closePaymentSuccessModal()" class="mt-6 px-8 py-3 rounded-xl bg-maroon text-white text-[10px] font-black uppercase tracking-widest hover:bg-maroon-800">Done</button>
            </div>
        </div>
    </div>
    <!-- INVOICE GROUP CREATION MODAL -->
    <div id="payment-group-modal" class="fixed inset-0 z-[700] hidden flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div onclick="window.toggleModal('payment-group-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all w-full max-w-md modal-animate-in">
            <div class="bg-maroon p-6 text-white">
                <div class="flex items-center space-x-3">
                    <div class="p-2 bg-white/10 rounded-lg">
                        <i data-lucide="folder-plus" class="w-5 h-5 text-gold"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold uppercase tracking-widest">Create Invoice Group</h3>
                        <p class="text-[10px] font-semibold text-white/60 mt-1">Group selected invoices and post them as paid immediately.</p>
                    </div>
                </div>
            </div>
            <div class="p-6 space-y-4">
                <p id="payment-group-count" class="text-[10px] font-semibold text-slate-400"></p>
                <div>
                    <label for="payment-group-title" class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block mb-2">ADD NEW GROUP</label>
                    <input type="text" id="payment-group-title" oninput="window.updateGroupButton()" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 outline-none focus:border-maroon focus:ring-2 focus:ring-maroon/20" placeholder="Group Title">
                </div>
                <div>
                    <label for="payment-existing-group-search" class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block mb-2">USE EXISTING GROUP</label>
                    <div id="payment-existing-group-combobox" class="relative">
                        <div class="relative">
                            <input type="text" id="payment-existing-group-search" autocomplete="off" onfocus="window.openExistingGroupDropdown()" oninput="window.filterExistingGroupOptions(this.value)" class="w-full px-4 py-2.5 pr-10 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 outline-none focus:border-maroon focus:ring-2 focus:ring-maroon/20 bg-white" placeholder="Search all groups...">
                            <button type="button" onclick="window.toggleExistingGroupDropdown()" class="absolute right-2 top-1/2 -translate-y-1/2 w-7 h-7 rounded-lg text-slate-400 hover:bg-slate-100 hover:text-maroon" title="Show all groups"><i data-lucide="chevron-down" class="w-4 h-4 mx-auto"></i></button>
                        </div>
                        <select id="payment-existing-group-select" class="hidden" aria-hidden="true"><option value="">Select an existing group</option></select>
                        <div id="payment-existing-group-options" class="hidden absolute left-0 right-0 z-[820] mt-1 max-h-64 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-2xl"></div>
                    </div>
                    <p class="mt-1 text-[9px] font-semibold text-slate-400">Searches and shows all groups, including active and not active groups.</p>
                </div>
                <div>
                    <p id="payment-group-error" class="hidden text-[10px] font-bold text-red-600"></p>
                </div>
            </div>
            <div class="p-5 border-t border-slate-100 bg-slate-50/50 flex items-center justify-end gap-2">
                <button onclick="window.toggleModal('payment-group-modal', false)" class="px-5 py-2.5 rounded-xl border border-slate-200 text-[10px] font-black uppercase tracking-widest text-slate-500 hover:bg-slate-100">Cancel</button>
                <button id="payment-group-create-btn" onclick="window.submitPaymentGroup()" class="px-5 py-2.5 rounded-xl bg-maroon text-white text-[10px] font-black uppercase tracking-widest hover:bg-maroon-800 disabled:opacity-50 disabled:cursor-not-allowed">Create Group &amp; Pay</button>
            </div>
        </div>
    </div>

    <!-- PAYOR PAYMENT HISTORY MODAL -->
    <!-- PAYOR PAYMENT HISTORY MODAL: grouped by Payment No. -->
    <div id="payor-history-modal" class="fixed inset-0 z-[640] hidden flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div onclick="window.toggleModal('payor-history-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all w-full max-w-[1250px] max-h-[92vh] modal-animate-in flex flex-col">
            <div class="bg-maroon p-6 text-white flex items-center justify-between shrink-0">
                <div class="flex items-center space-x-3">
                    <div class="p-2 bg-white/10 rounded-lg"><i data-lucide="receipt-text" class="w-5 h-5 text-gold"></i></div>
                    <div>
                        <h3 class="text-base font-bold uppercase tracking-widest">Payment History</h3>
                        <p id="payor-history-customer-name" class="text-[11px] font-bold text-gold mt-1">---</p>
                    </div>
                </div>
                <button onclick="window.toggleModal('payor-history-modal', false)" class="text-white/60 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="p-6 flex-1 min-h-0 flex flex-col">
                <div class="mb-3 flex items-center justify-between gap-4">
                    <p class="text-[10px] font-semibold text-slate-400">Each row is one Payment No. Invoice preview shows only the first 5; hover it to see the complete invoice list.</p>
                    <span class="rounded-full bg-maroon/5 px-3 py-1 text-[9px] font-black uppercase tracking-widest text-maroon">Grouped by Payment No.</span>
                </div>
                <div class="min-h-0 h-[62vh] max-h-[62vh] overflow-auto border border-slate-100 rounded-2xl custom-scrollbar">
                    <table class="w-full min-w-[1040px] text-left">
                        <thead class="bg-slate-50 text-slate-400 text-[9px] font-bold uppercase tracking-wider sticky top-0 z-10">
                            <tr>
                                <th class="p-4">Payment No</th>
                                <th class="p-4">Invoices</th>
                                <th class="p-4 text-center">Date</th>
                                <th class="p-4 text-right">Amount</th>
                                <th class="p-4 text-center">Action</th>
                            </tr>
                            <tr id="payor-history-search-row" class="bg-white border-t border-slate-100">
                                <th class="p-1"><input type="text" data-filter="payment_no" class="payor-history-filter-input w-full px-2 py-1.5 text-[9px] border border-slate-200 rounded-lg outline-none bg-slate-50 focus:border-maroon" placeholder="Search payment no"></th>
                                <th class="p-1"><input type="text" data-filter="invoices" class="payor-history-filter-input w-full px-2 py-1.5 text-[9px] border border-slate-200 rounded-lg outline-none bg-slate-50 focus:border-maroon" placeholder="Search invoice"></th>
                                <th class="p-1"><input type="text" data-filter="payment_date" class="payor-history-filter-input w-full px-2 py-1.5 text-[9px] border border-slate-200 rounded-lg outline-none bg-slate-50 focus:border-maroon" placeholder="Search date"></th>
                                <th class="p-1"><input type="text" data-filter="amount" class="payor-history-filter-input w-full px-2 py-1.5 text-[9px] border border-slate-200 rounded-lg outline-none bg-slate-50 focus:border-maroon" placeholder="Search amount"></th>
                                <th class="p-1"><input type="text" data-filter="action" class="payor-history-filter-input w-full px-2 py-1.5 text-[9px] border border-slate-200 rounded-lg outline-none bg-slate-50 focus:border-maroon text-center" placeholder="View / Edit / Delete"></th>
                            </tr>
                        </thead>
                        <tbody id="payor-history-invoices-tbody" class="text-[10px] divide-y divide-slate-50"></tbody>
                    </table>
                </div>
                <div class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shrink-0">
                    <span id="payor-history-page-info" class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Showing 0 of 0 payments</span>
                    <span id="payor-history-page-count" class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Page 1 of 1</span>
                    <div class="flex items-center gap-2">
                        <button id="payor-history-prev-btn" onclick="window.changePayorHistoryPage(-1)" class="px-4 py-2 rounded-lg border border-slate-200 text-[10px] font-black uppercase tracking-widest text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed">Previous</button>
                        <button id="payor-history-next-btn" onclick="window.changePayorHistoryPage(1)" class="px-4 py-2 rounded-lg bg-maroon text-white text-[10px] font-black uppercase tracking-widest hover:bg-maroon-800 disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- INVOICE ITEMS MODAL -->
    <div id="payor-history-items-modal" class="fixed inset-0 z-[750] hidden flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div onclick="window.toggleModal('payor-history-items-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all w-full max-w-[950px] modal-animate-in">
            <div class="bg-maroon p-6 text-white flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="p-2 bg-white/10 rounded-lg">
                        <i data-lucide="package-search" class="w-5 h-5 text-gold"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold uppercase tracking-widest">Invoice Items</h3>
                        <p id="history-items-invoice-label" class="text-[11px] font-bold text-gold mt-1">---</p>
                    </div>
                </div>
                <button onclick="window.toggleModal('payor-history-items-modal', false)" class="text-white/60 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto border border-slate-100 rounded-2xl">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 text-slate-400 text-[9px] font-bold uppercase tracking-wider">
                            <tr>
                                <th class="p-4">Invoice No.</th>
                                <th class="p-4 text-right">Invoice Amount</th>
                                <th class="p-4">Product Code</th>
                                <th class="p-4">Description</th>
                                <th class="p-4">Unit</th>
                                <th class="p-4 text-center">Qty</th>
                                <th class="p-4 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody id="history-items-tbody" class="text-[10px] divide-y divide-slate-50">
                        </tbody>
                        <tfoot id="history-items-tfoot" class="bg-slate-50 text-[10px] font-black text-slate-700 uppercase tracking-widest">
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- PAID GROUP VIEWER MODAL -->
    <div id="payments-history-view-modal" class="fixed inset-0 z-[680] hidden items-center justify-center p-4" role="dialog" aria-modal="true">
    <div onclick="window.closePaymentsHistoryViewModal()" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
    <div class="relative flex max-h-[92vh] w-full max-w-[1500px] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl modal-animate-in">
        <div class="bg-maroon p-5 text-white flex flex-wrap items-center gap-4"><div class="min-w-0 flex-1"><p class="text-[9px] font-black uppercase tracking-[0.24em] text-gold">Payment History</p><h3 id="history-view-payment-no" class="mt-1 text-lg font-black">---</h3></div><div class="rounded-xl bg-white/10 px-4 py-2"><div class="text-[8px] font-black uppercase text-white/50">Payment Date</div><div id="history-view-payment-date" class="text-xs font-black">---</div></div><div class="rounded-xl bg-white/10 px-4 py-2 text-right"><div class="text-[8px] font-black uppercase text-white/50">Total</div><div id="history-view-total-paid" class="text-base font-black text-gold">PHP 0.00</div></div><button onclick="window.closePaymentsHistoryViewModal()" class="text-white/60 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button></div>
        <div class="border-b border-slate-100 bg-white p-3 flex flex-wrap gap-2"><button id="history-detail-tab-invoices" onclick="window.switchPaymentHistoryDetailTab('invoices')">Invoices</button><button id="history-detail-tab-bank" onclick="window.switchPaymentHistoryDetailTab('bank')">Bank Details</button><button id="history-detail-tab-ledger" onclick="window.switchPaymentHistoryDetailTab('ledger')">Ledger</button><button id="history-detail-tab-items" onclick="window.switchPaymentHistoryDetailTab('items')" class="hidden">Item Details</button></div>
        <div class="flex-1 min-h-0 overflow-auto custom-scrollbar p-4">
            <div id="history-detail-panel-invoices"><div class="overflow-auto rounded-xl border border-slate-100"><table class="w-full min-w-[1100px] text-left text-[10px]"><thead class="sticky top-0 z-10 bg-slate-50 text-slate-400 uppercase tracking-wider"><tr><th class="p-3">Invoices</th><th class="p-3 text-right">Invoice Amount</th><th class="p-3">Return</th><th class="p-3 text-right">Return Amount</th><th class="p-3 text-right">Paid Amount</th><th class="p-3">Date Created</th><th class="p-3">Payment Date</th></tr><tr id="history-invoices-filter-row" class="bg-white border-t"><th class="p-1"><input data-col="0" oninput="window.filterHistoryDetailTable('payments-history-view-invoices-tbody','history-invoices-filter-row')" class="col-search-input w-full text-[9px]" placeholder="Search invoice..."></th><th class="p-1"><input data-col="1" oninput="window.filterHistoryDetailTable('payments-history-view-invoices-tbody','history-invoices-filter-row')" class="col-search-input w-full text-[9px]" placeholder="Search amount..."></th><th class="p-1"><input data-col="2" oninput="window.filterHistoryDetailTable('payments-history-view-invoices-tbody','history-invoices-filter-row')" class="col-search-input w-full text-[9px]" placeholder="Search return..."></th><th class="p-1"><input data-col="3" oninput="window.filterHistoryDetailTable('payments-history-view-invoices-tbody','history-invoices-filter-row')" class="col-search-input w-full text-[9px]" placeholder="Search return amt..."></th><th class="p-1"><input data-col="4" oninput="window.filterHistoryDetailTable('payments-history-view-invoices-tbody','history-invoices-filter-row')" class="col-search-input w-full text-[9px]" placeholder="Search paid..."></th><th class="p-1"><input data-col="5" oninput="window.filterHistoryDetailTable('payments-history-view-invoices-tbody','history-invoices-filter-row')" class="col-search-input w-full text-[9px]" placeholder="Search created..."></th><th class="p-1"><input data-col="6" oninput="window.filterHistoryDetailTable('payments-history-view-invoices-tbody','history-invoices-filter-row')" class="col-search-input w-full text-[9px]" placeholder="Search payment date..."></th></tr></thead><tbody id="payments-history-view-invoices-tbody" class="divide-y divide-slate-100"></tbody></table></div><p class="mt-2 text-[9px] font-semibold text-slate-400">Click an invoice row to open its Item Details.</p></div>
            <div id="history-detail-panel-bank" class="hidden"><div class="overflow-auto rounded-xl border border-slate-100"><table class="w-full min-w-[900px] text-left text-[10px]"><thead class="sticky top-0 z-10 bg-slate-50 text-slate-400 uppercase tracking-wider"><tr><th class="p-3">Bank Name</th><th class="p-3">Account No</th><th class="p-3">Check No</th><th class="p-3">Check Date</th><th class="p-3 text-right">Check Amount</th><th class="p-3 text-right">Total</th></tr><tr id="history-bank-filter-row" class="bg-white border-t"><th class="p-1"><input data-col="0" oninput="window.filterHistoryDetailTable('payments-history-view-bank-tbody','history-bank-filter-row')" class="col-search-input w-full text-[9px]" placeholder="Search..."></th><th class="p-1"><input data-col="1" oninput="window.filterHistoryDetailTable('payments-history-view-bank-tbody','history-bank-filter-row')" class="col-search-input w-full text-[9px]" placeholder="Search..."></th><th class="p-1"><input data-col="2" oninput="window.filterHistoryDetailTable('payments-history-view-bank-tbody','history-bank-filter-row')" class="col-search-input w-full text-[9px]" placeholder="Search..."></th><th class="p-1"><input data-col="3" oninput="window.filterHistoryDetailTable('payments-history-view-bank-tbody','history-bank-filter-row')" class="col-search-input w-full text-[9px]" placeholder="Search..."></th><th class="p-1"><input data-col="4" oninput="window.filterHistoryDetailTable('payments-history-view-bank-tbody','history-bank-filter-row')" class="col-search-input w-full text-[9px]" placeholder="Search..."></th><th class="p-1"><input data-col="5" oninput="window.filterHistoryDetailTable('payments-history-view-bank-tbody','history-bank-filter-row')" class="col-search-input w-full text-[9px]" placeholder="Search..."></th></tr></thead><tbody id="payments-history-view-bank-tbody" class="divide-y divide-slate-100"></tbody></table></div></div>
            <div id="history-detail-panel-ledger" class="hidden"><div class="overflow-auto rounded-xl border border-slate-100"><table class="w-full min-w-[1350px] text-left text-[10px]"><thead class="sticky top-0 z-10 bg-slate-50 text-slate-400 uppercase tracking-wider"><tr><th class="p-3">Invoice No.</th><th class="p-3">Product Code</th><th class="p-3">Part Number</th><th class="p-3">Transaction</th><th class="p-3">Transaction No.</th><th class="p-3 text-right">Debit</th><th class="p-3 text-right">Credit</th><th class="p-3">Remarks</th><th class="p-3">Date</th></tr><tr id="history-ledger-filter-row" class="bg-white border-t"><th class="p-1"><input data-col="0" oninput="window.filterHistoryDetailTable('payments-history-view-ledger-tbody','history-ledger-filter-row')" class="col-search-input w-full text-[9px]" placeholder="Search invoice..."></th><th class="p-1"><input data-col="1" oninput="window.filterHistoryDetailTable('payments-history-view-ledger-tbody','history-ledger-filter-row')" class="col-search-input w-full text-[9px]" placeholder="Search product..."></th><th class="p-1"><input data-col="2" oninput="window.filterHistoryDetailTable('payments-history-view-ledger-tbody','history-ledger-filter-row')" class="col-search-input w-full text-[9px]" placeholder="Search part..."></th><th class="p-1"><input data-col="3" oninput="window.filterHistoryDetailTable('payments-history-view-ledger-tbody','history-ledger-filter-row')" class="col-search-input w-full text-[9px]" placeholder="Search movement..."></th><th class="p-1"><input data-col="4" oninput="window.filterHistoryDetailTable('payments-history-view-ledger-tbody','history-ledger-filter-row')" class="col-search-input w-full text-[9px]" placeholder="Search transaction..."></th><th class="p-1"><input data-col="5" oninput="window.filterHistoryDetailTable('payments-history-view-ledger-tbody','history-ledger-filter-row')" class="col-search-input w-full text-[9px]" placeholder="Search debit..."></th><th class="p-1"><input data-col="6" oninput="window.filterHistoryDetailTable('payments-history-view-ledger-tbody','history-ledger-filter-row')" class="col-search-input w-full text-[9px]" placeholder="Search credit..."></th><th class="p-1"><input data-col="7" oninput="window.filterHistoryDetailTable('payments-history-view-ledger-tbody','history-ledger-filter-row')" class="col-search-input w-full text-[9px]" placeholder="Search remarks..."></th><th class="p-1"><input data-col="8" oninput="window.filterHistoryDetailTable('payments-history-view-ledger-tbody','history-ledger-filter-row')" class="col-search-input w-full text-[9px]" placeholder="Search date..."></th></tr></thead><tbody id="payments-history-view-ledger-tbody" class="divide-y divide-slate-100"></tbody></table></div></div>
            <div id="history-detail-panel-items" class="hidden"><div class="mb-3 flex items-center justify-between"><h4 id="history-view-items-title" class="text-[10px] font-black uppercase tracking-widest text-maroon">Item Details</h4><span class="rounded-full bg-[#4A0E0E] px-3 py-1 text-[9px] font-black text-yellow-300">Returned rows highlighted</span></div><div class="overflow-auto rounded-xl border border-slate-100"><table class="w-full min-w-[1200px] text-left text-[10px]"><thead class="sticky top-0 z-10 bg-slate-50 text-slate-400 uppercase tracking-wider"><tr><th class="p-3">Product Code</th><th class="p-3">Part Number</th><th class="p-3 text-right">Qty</th><th class="p-3 text-right">Unit Price</th><th class="p-3 text-right">Discount</th><th class="p-3 text-right">Additional Discount</th><th class="p-3 text-right">Returned Qty</th><th class="p-3 text-right">Total Amount</th><th class="p-3 text-right">F. Amount</th></tr><tr id="history-items-filter-row" class="bg-white border-t"><th class="p-1"><input data-col="0" oninput="window.filterHistoryDetailTable('payments-history-view-items-tbody','history-items-filter-row')" class="col-search-input w-full text-[9px]" placeholder="Search..."></th><th class="p-1"><input data-col="1" oninput="window.filterHistoryDetailTable('payments-history-view-items-tbody','history-items-filter-row')" class="col-search-input w-full text-[9px]" placeholder="Search..."></th><th class="p-1"><input data-col="2" oninput="window.filterHistoryDetailTable('payments-history-view-items-tbody','history-items-filter-row')" class="col-search-input w-full text-[9px]" placeholder="Search..."></th><th class="p-1"><input data-col="3" oninput="window.filterHistoryDetailTable('payments-history-view-items-tbody','history-items-filter-row')" class="col-search-input w-full text-[9px]" placeholder="Search..."></th><th class="p-1"><input data-col="4" oninput="window.filterHistoryDetailTable('payments-history-view-items-tbody','history-items-filter-row')" class="col-search-input w-full text-[9px]" placeholder="Search..."></th><th class="p-1"><input data-col="5" oninput="window.filterHistoryDetailTable('payments-history-view-items-tbody','history-items-filter-row')" class="col-search-input w-full text-[9px]" placeholder="Search..."></th><th class="p-1"><input data-col="6" oninput="window.filterHistoryDetailTable('payments-history-view-items-tbody','history-items-filter-row')" class="col-search-input w-full text-[9px]" placeholder="Search..."></th><th class="p-1"><input data-col="7" oninput="window.filterHistoryDetailTable('payments-history-view-items-tbody','history-items-filter-row')" class="col-search-input w-full text-[9px]" placeholder="Search..."></th><th class="p-1"><input data-col="8" oninput="window.filterHistoryDetailTable('payments-history-view-items-tbody','history-items-filter-row')" class="col-search-input w-full text-[9px]" placeholder="Search..."></th></tr></thead><tbody id="payments-history-view-items-tbody" class="divide-y divide-slate-100"></tbody></table></div></div>
        </div>
    </div>
</div>

    <div id="payment-process-group-modal" class="fixed inset-0 z-[700] hidden flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div onclick="window.toggleModal('payment-process-group-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all w-full max-w-[1180px] modal-animate-in">
            <div class="bg-maroon p-6 text-white flex flex-wrap items-center gap-3">
                <div class="flex items-center space-x-3 min-w-0 flex-1">
                    <div class="p-2 bg-white/10 rounded-lg shrink-0"><i data-lucide="folder-check" class="w-5 h-5 text-gold"></i></div>
                    <div class="min-w-0">
                        <h3 id="process-group-title" class="text-base font-bold uppercase tracking-widest truncate">Paid Group</h3>
                        <p id="process-group-subtitle" class="text-[10px] font-semibold text-white/60 mt-1 truncate">Grouped invoices are already posted as paid.</p>
                    </div>
                </div>
                <div class="ml-auto flex flex-wrap items-center justify-end gap-2 pl-4">
                    <div class="rounded-xl border border-white/10 bg-white/10 px-3 py-2 text-right">
                        <div id="process-group-selected-count" class="text-[8px] font-black uppercase tracking-widest text-white/50">0 selected</div>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-white/10 px-3 py-2 text-right min-w-[145px]">
                        <div class="text-[8px] font-black uppercase tracking-widest text-white/50">Total Invoice</div>
                        <div id="process-group-total" class="mt-0.5 text-sm font-black text-gold">PHP 0.00</div>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-white/10 px-3 py-2 text-right min-w-[145px]">
                        <div class="text-[8px] font-black uppercase tracking-widest text-white/50">Total Paid</div>
                        <div id="process-group-paid-total" class="mt-0.5 text-sm font-black text-emerald-300">PHP 0.00</div>
                    </div>
                    <button onclick="window.toggleModal('payment-process-group-modal', false)" class="ml-1 text-white/60 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
                </div>
            </div>
            <div class="p-6 space-y-4 max-h-[calc(100vh-16rem)] overflow-y-auto custom-scrollbar">
                <div id="process-group-table-wrap" class="overflow-x-auto border border-slate-100 rounded-2xl">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 text-slate-400 text-[9px] font-bold uppercase tracking-wider">
                            <tr>
                                <th class="p-4 text-center"><input type="checkbox" id="process-group-select-all" class="accent-maroon"></th>
                                <th class="p-4">Invoice No.</th>
                                <th class="p-4">Invoice Date</th>
                                <th class="p-4 text-right">Invoice Amount</th>
                                <th class="p-4 text-right">Adjustment</th>
                                <th class="p-4 text-right">Amount Paid</th>
                                <th class="p-4">Payment No.</th>
                                <th class="p-4">Remarks</th>
                                <th class="p-4 text-center">Action</th>
                            </tr>
                            <tr id="process-group-filter-row" class="bg-white border-t border-slate-100">
                                <th class="p-1"></th>
                                <th class="p-1"><input type="text" data-col="1" onkeyup="window.filterProcessGroupTable()" class="col-search-input text-[9px] w-full" placeholder="Search Invoice..."></th>
                                <th class="p-1"><input type="text" data-col="2" onkeyup="window.filterProcessGroupTable()" class="col-search-input text-[9px] w-full" placeholder="Search Date..."></th>
                                <th class="p-1"><input type="text" data-col="3" onkeyup="window.filterProcessGroupTable()" class="col-search-input text-[9px] w-full" placeholder="Search Amount..."></th>
                                <th class="p-1"><input type="text" data-col="4" onkeyup="window.filterProcessGroupTable()" class="col-search-input text-[9px] w-full" placeholder="Search Adjustment..."></th>
                                <th class="p-1"><input type="text" data-col="5" onkeyup="window.filterProcessGroupTable()" class="col-search-input text-[9px] w-full" placeholder="Search Paid..."></th>
                                <th class="p-1"><input type="text" data-col="6" onkeyup="window.filterProcessGroupTable()" class="col-search-input text-[9px] w-full" placeholder="Search Payment..."></th>
                                <th class="p-1"><input type="text" data-col="7" onkeyup="window.filterProcessGroupTable()" class="col-search-input text-[9px] w-full" placeholder="Search Remarks..."></th>
                                <th class="p-1"></th>
                            </tr>
                        </thead>
                        <tbody id="process-group-tbody" class="text-[10px] divide-y divide-slate-50"></tbody>
                    </table>
                </div>
            </div>
            <div class="p-5 border-t border-slate-100 bg-slate-50/50 flex items-center justify-end gap-2">
                <button onclick="window.toggleModal('payment-process-group-modal', false)" class="px-5 py-2.5 rounded-xl border border-slate-200 text-[10px] font-black uppercase tracking-widest text-slate-500 hover:bg-slate-100">Close</button>
                <button id="process-group-print-selected-btn" onclick="window.printSelectedGroupReceipt()" disabled class="px-5 py-2.5 rounded-xl bg-maroon text-white text-[10px] font-black uppercase tracking-widest hover:bg-maroon-800 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"><i data-lucide="printer" class="w-3.5 h-3.5"></i>Print Selected</button>
            </div>
        </div>
    </div>

    <!-- GROUP COLLECTION PRINT SETTINGS MODAL -->
    <div id="group-print-settings-modal" class="fixed inset-0 z-[760] hidden flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div onclick="window.toggleModal('group-print-settings-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all w-full max-w-4xl modal-animate-in">
            <div class="bg-maroon p-6 text-white flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold uppercase tracking-widest">Collection Invoice Print</h3>
                    <p id="group-print-selected-count" class="text-[10px] font-semibold text-white/60 mt-1">0 selected invoices</p>
                </div>
                <button onclick="window.toggleModal('group-print-settings-modal', false)" class="text-white/60 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="p-6 space-y-4">
                <label class="block">
                    <span class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">Online Percent</span>
                    <div class="relative"><input id="group-print-online-percent" type="number" min="0" max="100" step="0.01" value="0.00" class="w-full rounded-xl border border-slate-200 px-4 py-3 pr-10 text-right text-sm font-black text-maroon outline-none focus:border-maroon"><span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-black text-slate-400">%</span></div>
                </label>
                <label class="block">
                    <span class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">Online Payment</span>
                    <input id="group-print-online-payment" type="number" step="0.01" value="0.00" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-right text-sm font-black text-maroon outline-none focus:border-maroon">
                </label>
                <label class="block">
                    <span class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">Re-Total</span>
                    <input id="group-print-retotal" type="number" step="0.01" value="0.00" readonly class="w-full rounded-xl border border-maroon/20 bg-slate-50 px-4 py-3 text-right text-sm font-black text-maroon outline-none">
                    <span class="mt-1 block text-[9px] font-semibold text-slate-400">Re-Total stays equal to the selected invoice amount total.</span>
                </label>
                <div class="rounded-2xl border border-slate-100 overflow-hidden">
                    <div class="flex items-center justify-between gap-4 bg-slate-50 px-4 py-3 border-b border-slate-100">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Returns</p>
                            <p class="mt-1 text-[9px] font-semibold text-slate-400">Optional print-only fields. Enter all Return values manually or leave them blank. Nothing is auto-filled or saved to the payment.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="text" id="group-print-returns-search" oninput="window.filterManualReturnsTable('group', this.value)" class="w-52 px-3 py-2 rounded-lg border border-slate-200 bg-white text-[9px] font-bold text-slate-600 outline-none focus:border-maroon" placeholder="Search Returns...">
                            <button type="button" onclick="window.addManualReturnRow('group')" class="px-3 py-2 rounded-lg border border-maroon/20 bg-white text-maroon text-[9px] font-black uppercase tracking-widest hover:bg-maroon/5 flex items-center gap-1.5"><i data-lucide="plus" class="w-3.5 h-3.5"></i>Add Return</button>
                        </div>
                    </div>
                    <div class="max-h-[280px] overflow-auto custom-scrollbar">
                        <table class="w-full min-w-[700px] text-left">
                            <thead class="sticky top-0 z-10 bg-white text-slate-400 text-[9px] font-black uppercase tracking-wider shadow-sm">
                                <tr><th class="p-3">Return Order ID</th><th class="p-3 text-right">Wallet &amp; Adjustment</th><th class="p-3">Return No.</th><th class="p-3 text-center">Action</th><th class="p-3 text-center">Print</th></tr>
                            </thead>
                            <tbody id="group-print-returns-tbody" class="text-[10px] divide-y divide-slate-50"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="p-5 border-t border-slate-100 bg-slate-50/50 flex items-center justify-end gap-2">
                <button onclick="window.toggleModal('group-print-settings-modal', false)" class="px-5 py-2.5 rounded-xl border border-slate-200 text-[10px] font-black uppercase tracking-widest text-slate-500 hover:bg-slate-100">Cancel</button>
                <button id="group-print-submit-btn" onclick="window.submitGroupPrintSettings()" class="px-5 py-2.5 rounded-xl bg-maroon text-white text-[10px] font-black uppercase tracking-widest hover:bg-maroon-800 flex items-center gap-2"><i data-lucide="printer" class="w-3.5 h-3.5"></i>Print</button>
            </div>
        </div>
    </div>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script>
        window.paymentsRoutes = {
            data: "<?php echo e(route('special.payments.data')); ?>",
            customer: "<?php echo e(route('special.payments.customer', ['customerId' => ':customerId'])); ?>",
            customerReturns: "<?php echo e(route('special.payments.customer.returns', ['customerId' => ':customerId'])); ?>",
            returnLookup: "<?php echo e(route('special.payments.return.lookup', ['returnId' => ':returnId'])); ?>",
            process: "<?php echo e(route('special.payments.process')); ?>",
            historyData: "<?php echo e(route('special.payments.history.data')); ?>",
            payors: "<?php echo e(route('special.payments.history.payors')); ?>",
            payorHistory: "<?php echo e(route('special.payments.history.payor', ['customerId' => ':customerId'])); ?>",
            historyItems: "<?php echo e(route('special.payments.history.items', ['sourceType' => ':sourceType', 'sourceId' => ':sourceId'])); ?>",
            historyLedger: "<?php echo e(route('special.payments.history.ledger', ['paymentId' => ':paymentId'])); ?>",
            historyDetail: "<?php echo e(route('special.payments.history.detail', ['paymentId' => ':paymentId'])); ?>",
            historyUpdate: "<?php echo e(route('special.payments.history.update', ['paymentId' => ':paymentId'])); ?>",
            historyDelete: "<?php echo e(route('special.payments.history.delete', ['paymentId' => ':paymentId'])); ?>",
            historyPrint: "<?php echo e(route('special.payments.history.print')); ?>",
            groupsData: "<?php echo e(route('special.payments.groups.data')); ?>",
            groupsStore: "<?php echo e(route('special.payments.groups.store')); ?>",
            groupDelete: "<?php echo e(route('special.payments.groups.delete', ['groupId' => ':groupId'])); ?>",
            groupDetail: "<?php echo e(route('special.payments.groups.detail', ['groupId' => ':groupId'])); ?>",
            groupPrint: "<?php echo e(route('special.payments.groups.print', ['groupId' => ':groupId'])); ?>",
            groupProcess: "<?php echo e(route('special.payments.groups.process', ['groupId' => ':groupId'])); ?>",
            groupsItems: "<?php echo e(route('special.payments.groups.items', ['groupId' => ':groupId'])); ?>",

            groupItemUpdate: "<?php echo e(route('special.payments.groups.item.update', ['groupId' => ':groupId', 'itemId' => ':itemId'])); ?>",
            groupItemDelete: "<?php echo e(route('special.payments.groups.item.delete', ['groupId' => ':groupId', 'itemId' => ':itemId'])); ?>"
        };
    </script>
    <script src="<?php echo e(asset('js/payments.js')); ?>?v=<?php echo e(time()); ?>"></script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('partials.special_user.special_sidebar_navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\hatdog\resources\views/Special_User/Accounting/Payments.blade.php ENDPATH**/ ?>