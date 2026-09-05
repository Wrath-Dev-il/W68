@push('styles')
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');

        #page-sales-junk {
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

        .restore-btn {
            padding: 0.5rem 1.25rem;
            background-color: #800000;
            color: #FFD700;
            font-size: 9px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            border-radius: 0.75rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.15);
        }

        .restore-btn:hover {
            background-color: #6b0000;
            transform: scale(1.03);
            box-shadow: 0 6px 20px rgba(128, 0, 0, 0.25);
        }

        .restore-btn:active {
            transform: scale(0.97);
        }

        .junk-icon-wrap {
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
    </style>
@endpush

@section('sales_junk_content')
<div id="page-sales-junk" class="space-y-6 animate-fade-in p-6">

    <!-- HEADER -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-bold text-slate-800 tracking-tight">Junk Items</h2>
            <p class="text-xs text-slate-400 font-medium">Manage and restore discarded inventory items.</p>
        </div>
    </div>

    <!-- SUMMARY CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="stat-card-hero p-6">
            <div class="relative z-10">
                <span class="text-[10px] font-bold uppercase tracking-widest text-gold/80 block mb-2">Total Junk Items</span>
                <h3 id="total-junk-count" class="text-3xl font-extrabold tracking-tight">0</h3>
                <p class="text-[10px] text-white/60 font-medium mt-1">items in junk</p>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between group hover:border-slate-200 transition-all hover:shadow-md hover:scale-[1.02] duration-300">
            <div class="space-y-0.5 min-w-0">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">By Category</span>
                <h3 id="junk-by-category" class="text-2xl font-extrabold text-slate-800 tracking-tight truncate">0</h3>
                <div class="flex items-center gap-1.5 mt-1">
                    <span class="text-emerald-500 font-bold text-[10px] flex items-center gap-0.5">
                        <i data-lucide="layers" class="w-3 h-3"></i>
                        categories
                    </span>
                </div>
            </div>
            <div class="junk-icon-wrap group-hover:scale-105 transition-transform duration-300">
                <i data-lucide="folder" class="w-5 h-5"></i>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between group hover:border-slate-200 transition-all hover:shadow-md hover:scale-[1.02] duration-300">
            <div class="space-y-0.5 min-w-0">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Total QTY</span>
                <h3 id="junk-total-qty" class="text-2xl font-extrabold text-slate-800 tracking-tight truncate">0</h3>
                <div class="flex items-center gap-1.5 mt-1">
                    <span class="text-amber-500 font-bold text-[10px] flex items-center gap-0.5">
                        <i data-lucide="package" class="w-3 h-3"></i>
                        units
                    </span>
                </div>
            </div>
            <div class="junk-icon-wrap group-hover:scale-105 transition-transform duration-300">
                <i data-lucide="archive" class="w-5 h-5"></i>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between group hover:border-slate-200 transition-all hover:shadow-md hover:scale-[1.02] duration-300">
            <div class="space-y-0.5 min-w-0">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Restored Today</span>
                <h3 id="junk-restored-today" class="text-2xl font-extrabold text-slate-800 tracking-tight truncate">0</h3>
                <div class="flex items-center gap-1.5 mt-1">
                    <span class="text-blue-500 font-bold text-[10px] flex items-center gap-0.5">
                        <i data-lucide="rotate-ccw" class="w-3 h-3"></i>
                        items restored
                    </span>
                </div>
            </div>
            <div class="junk-icon-wrap group-hover:scale-105 transition-transform duration-300">
                <i data-lucide="refresh-ccw" class="w-5 h-5"></i>
            </div>
        </div>
    </div>

    <!-- MAIN TABLE -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-50 bg-slate-50/50 flex items-center justify-between">
            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-widest">Junk Items List</h3>
            <div class="flex items-center space-x-2">
                <span class="text-[10px] text-slate-400 font-medium">Items marked as junk awaiting disposition</span>
            </div>
        </div>
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-slate-400 text-[10px] font-bold uppercase tracking-widest">
                        <th class="py-5 px-6">Product Code</th>
                        <th class="py-5 px-6">Part No#</th>
                        <th class="py-5 px-6">Application</th>
                        <th class="py-5 px-6">Description</th>
                        <th class="py-5 px-6 text-center">QTY</th>
                        <th class="py-5 px-6 text-center">Action</th>
                    </tr>
                    <tr class="bg-white border-b border-slate-100">
                        <th class="p-2 px-6"><input type="text" onkeyup="window.filterJunkTable(0, this.value)" class="col-search-input" placeholder="Search Code..."></th>
                        <th class="p-2 px-6"><input type="text" onkeyup="window.filterJunkTable(1, this.value)" class="col-search-input" placeholder="Search Part No..."></th>
                        <th class="p-2 px-6"><input type="text" onkeyup="window.filterJunkTable(2, this.value)" class="col-search-input" placeholder="Search Application..."></th>
                        <th class="p-2 px-6"><input type="text" onkeyup="window.filterJunkTable(3, this.value)" class="col-search-input" placeholder="Search Description..."></th>
                        <th class="p-2 px-6"><input type="text" onkeyup="window.filterJunkTable(4, this.value)" class="col-search-input text-center" placeholder="Search QTY..."></th>
                        <th class="p-2 px-6"></th>
                    </tr>
                </thead>
                <tbody id="junk-tbody" class="divide-y divide-slate-100 text-xs text-slate-600 font-medium">
                    <!-- SAMPLE ROW -->
                    <tr class="hover:bg-slate-50/50 transition-colors group">
                        <td class="py-5 px-6 text-slate-700 font-bold">JC-001</td>
                        <td class="py-5 px-6 text-slate-500">PN-98423-A</td>
                        <td class="py-5 px-6">
                            <span class="px-2.5 py-1 bg-slate-100 rounded-md text-[10px] font-semibold">Sedan</span>
                        </td>
                        <td class="py-5 px-6 text-slate-500 max-w-[200px] truncate">Brake Pad Set Ceramic Premium</td>
                        <td class="py-5 px-6 text-center">
                            <span class="font-black text-lg text-amber-600">5</span>
                        </td>
                        <td class="py-5 px-6 text-center">
                            <button onclick="window.restoreJunkItem('JC-001')" class="restore-btn">
                                <i data-lucide="rotate-ccw" class="w-3 h-3 inline-block mr-1"></i>
                                Restored
                            </button>
                        </td>
                    </tr>
                    <tr class="hover:bg-slate-50/50 transition-colors group">
                        <td class="py-5 px-6 text-slate-700 font-bold">JC-045</td>
                        <td class="py-5 px-6 text-slate-500">FL-7712-B</td>
                        <td class="py-5 px-6">
                            <span class="px-2.5 py-1 bg-slate-100 rounded-md text-[10px] font-semibold">SUV</span>
                        </td>
                        <td class="py-5 px-6 text-slate-500 max-w-[200px] truncate">Oil Filter High Flow Synthetic Media</td>
                        <td class="py-5 px-6 text-center">
                            <span class="font-black text-lg text-amber-600">12</span>
                        </td>
                        <td class="py-5 px-6 text-center">
                            <button onclick="window.restoreJunkItem('JC-045')" class="restore-btn">
                                <i data-lucide="rotate-ccw" class="w-3 h-3 inline-block mr-1"></i>
                                Restored
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- RESTORE CONFIRMATION MODAL -->
<div id="restore-modal" class="fixed inset-0 z-[650] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"></div>
    <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl overflow-hidden modal-animate-in">
        <div class="p-8 text-center">
            <div class="mx-auto w-16 h-16 rounded-2xl bg-maroon flex items-center justify-center">
                <i data-lucide="rotate-ccw" class="w-9 h-9 text-gold"></i>
            </div>
            <h3 class="mt-5 text-lg font-black text-slate-800 uppercase tracking-widest">Restore Item</h3>
            <p class="mt-2 text-sm font-semibold text-slate-500">Are you sure you want to restore this item back to inventory?</p>
            <div class="mt-6 flex items-center justify-center gap-3">
                <button onclick="window.closeRestoreModal()" class="px-6 py-3 rounded-xl bg-slate-100 text-slate-600 text-[10px] font-black uppercase tracking-widest hover:bg-slate-200 transition-all">Cancel</button>
                <button onclick="window.confirmRestore()" class="px-6 py-3 rounded-xl bg-maroon text-gold text-[10px] font-black uppercase tracking-widest hover:bg-maroon-800 transition-all shadow-md shadow-maroon/20">Confirm Restore</button>
            </div>
        </div>
    </div>
</div>

<!-- RESTORE SUCCESS MODAL -->
<div id="restore-success-modal" class="fixed inset-0 z-[650] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"></div>
    <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl overflow-hidden modal-animate-in">
        <div class="p-8 text-center">
            <div class="mx-auto w-16 h-16 rounded-2xl bg-emerald-50 text-emerald-500 flex items-center justify-center">
                <i data-lucide="check-circle-2" class="w-9 h-9"></i>
            </div>
            <h3 class="mt-5 text-lg font-black text-slate-800 uppercase tracking-widest">Item Restored</h3>
            <p id="restore-success-message" class="mt-2 text-sm font-semibold text-slate-500">The item has been restored to inventory.</p>
            <button onclick="window.closeRestoreSuccessModal()" class="mt-6 px-8 py-3 rounded-xl bg-maroon text-white text-[10px] font-black uppercase tracking-widest hover:bg-maroon-800">Done</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script>
        window.salesJunkRoutes = {
            data: '{{ route("admin.sales-junk.data") }}',
            restore: '{{ route("admin.sales-junk.restore") }}'
        };

        document.addEventListener('DOMContentLoaded', function () {
            loadJunkItems();
        });

        function loadJunkItems() {
            fetch(window.salesJunkRoutes.data)
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.success) return;
                    renderJunkTable(res.junk_items);
                    updateJunkStats(res.stats);
                })
                .catch(function (e) { console.error('Failed to load junk items:', e); });
        }

        function renderJunkTable(items) {
            var tbody = document.getElementById('junk-tbody');
            tbody.innerHTML = '';
            if (!items || items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="py-10 text-center text-slate-400 text-xs font-medium">No junk items found.</td></tr>';
                return;
            }
            items.forEach(function (item) {
                var tr = document.createElement('tr');
                tr.className = 'hover:bg-slate-50/50 transition-colors group';
                tr.innerHTML =
                    '<td class="py-5 px-6 text-slate-700 font-bold">' + escapeHtml(item.product_code) + '</td>' +
                    '<td class="py-5 px-6 text-slate-500">' + escapeHtml(item.part_number) + '</td>' +
                    '<td class="py-5 px-6"><span class="px-2.5 py-1 bg-slate-100 rounded-md text-[10px] font-semibold">' + escapeHtml(item.application || 'N/A') + '</span></td>' +
                    '<td class="py-5 px-6 text-slate-500 max-w-[200px] truncate">' + escapeHtml(item.description) + '</td>' +
                    '<td class="py-5 px-6 text-center"><span class="font-black text-lg text-amber-600">' + item.junk_qty + '</span></td>' +
                    '<td class="py-5 px-6 text-center">' +
                        '<button onclick="window.restoreJunkItem(' + item.product_id + ', ' + item.junk_qty + ')" class="restore-btn">' +
                            '<i data-lucide="rotate-ccw" class="w-3 h-3 inline-block mr-1"></i> Restored' +
                        '</button>' +
                    '</td>';
                tbody.appendChild(tr);
            });
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }

        function updateJunkStats(stats) {
            document.getElementById('total-junk-count').textContent = stats.total_items || 0;
            document.getElementById('junk-by-category').textContent = stats.total_categories || 0;
            document.getElementById('junk-total-qty').textContent = stats.total_qty || 0;
            document.getElementById('junk-restored-today').textContent = stats.restored_today || 0;
        }

        function escapeHtml(str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        window.filterJunkTable = function(colIndex, value) {
            const tbody = document.getElementById('junk-tbody');
            const rows = tbody.querySelectorAll('tr');
            const val = value.toLowerCase();
            rows.forEach(row => {
                const cols = row.querySelectorAll('td');
                if (cols[colIndex]) {
                    const text = cols[colIndex].textContent.toLowerCase();
                    row.style.display = text.includes(val) ? '' : 'none';
                }
            });
        };

        var pendingRestoreProductId = null;
        var pendingRestoreQty = null;

        window.restoreJunkItem = function(productId, junkQty) {
            pendingRestoreProductId = productId;
            pendingRestoreQty = junkQty;
            document.getElementById('restore-modal').classList.remove('hidden');
            document.getElementById('restore-modal').classList.add('flex');
        };

        window.closeRestoreModal = function() {
            document.getElementById('restore-modal').classList.add('hidden');
            document.getElementById('restore-modal').classList.remove('flex');
            pendingRestoreProductId = null;
            pendingRestoreQty = null;
        };

        window.confirmRestore = function() {
            var pid = pendingRestoreProductId;
            var qty = pendingRestoreQty;
            if (!pid || !qty) return;
            closeRestoreModal();
            fetch(window.salesJunkRoutes.restore, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ product_id: pid, junk_qty: qty })
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    document.getElementById('restore-success-message').textContent = 'Item restored to inventory. New balance: ' + res.new_balance;
                    document.getElementById('restore-success-modal').classList.remove('hidden');
                    document.getElementById('restore-success-modal').classList.add('flex');
                    loadJunkItems();
                } else {
                    alert('Restore failed: ' + (res.message || 'Unknown error'));
                }
            })
            .catch(function (e) {
                alert('Restore failed: ' + e.message);
            });
        };

        window.closeRestoreSuccessModal = function() {
            document.getElementById('restore-success-modal').classList.add('hidden');
            document.getElementById('restore-success-modal').classList.remove('flex');
        };
    </script>
@endpush

@include('partials.admin.admin_sidebar_navbar')
