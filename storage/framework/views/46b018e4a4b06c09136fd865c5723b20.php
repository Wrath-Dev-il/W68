<?php $__env->startPush('styles'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/Purchase-Note.css')); ?>?v=<?php echo e(time()); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('purchase_note_content'); ?>
<script>
    console.log('[DEBUG] App URL:', '<?php echo e(config("app.url")); ?>');
    console.log('[DEBUG] Asset URL:', '<?php echo e(asset("")); ?>');
    console.log('[DEBUG] Check Password Route:', '<?php echo e(url("/check-password")); ?>');

    window.purchaseRoutes = {
        fetch: '<?php echo e(route("special.purchase.note.data")); ?>',
        details: '<?php echo e(route("special.purchase.note.details", ["id" => ":id"])); ?>',
        searchSuppliers: '<?php echo e(route("special.purchase.search-suppliers")); ?>',
        searchProducts: '<?php echo e(route("special.purchase.search-products")); ?>',
        store: '<?php echo e(route("special.purchase.note.store")); ?>',
        update: '<?php echo e(route("special.purchase.note.update", ["id" => ":id"])); ?>',
        delete: '<?php echo e(route("special.purchase.note.delete", ["id" => ":id"])); ?>',
        supplierItemConflicts: '<?php echo e(route("special.purchase.note.supplier-item-conflicts")); ?>',
        forgottenPartialItems: '<?php echo e(route("special.purchase.note.forgotten-partial-items", ["supplierCode" => ":supplierCode"])); ?>',
        nextNumber: '<?php echo e(route("special.purchase.note.next-number")); ?>',
        print: '<?php echo e(route("special.purchase.note.print", ["id" => ":id"])); ?>',
        verifyPasswordUrl: '<?php echo e(route("special.purchase.note.verify-password")); ?>',
        checkPasswordUrl: '<?php echo e(url("/check-password")); ?>',
        invoicesByNoteUrl: '<?php echo e(route("special.purchase-order.by-note", ["purchaseNoteNumber" => ":noteNumber"])); ?>',
        invoiceDetailUrl: '<?php echo e(route("special.purchase-order.detail", ["poId" => ":poId"])); ?>',
        purchaseOrderIndex: '<?php echo e(route("special.purchase-order")); ?>'
    };

    console.log('[DEBUG] Purchase Routes:', window.purchaseRoutes);

    <?php if(isset($viberData) && $viberData): ?>
        window.viberPrepareNote = <?php echo json_encode($viberData, 15, 512) ?>;
    <?php else: ?>
        window.viberPrepareNote = null;
    <?php endif; ?>

    window.verifyPasswordDeleteV2 = async function() {
        const password = document.getElementById('password-input')?.value;
        if (!password) return;

        const btn = document.querySelector('#password-modal .bg-red-500');
        if (btn) btn.disabled = true;

        try {
            const res = await fetch('/hatdog/public/check-password', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({ password })
            });

            const result = await res.json();

            if (result.success) {
                const index = parseInt(document.getElementById('password-delete-index').value);
                toggleModal('password-modal', false);
                document.getElementById('password-input-container').innerHTML = '';
                removeItemFromPO(index);
            } else {
                document.getElementById('password-error-msg').textContent = result.message || 'Incorrect password. Please try again.';
            }
        } catch (error) {
            console.error('[DEBUG] Password verification error:', error);
            document.getElementById('password-error-msg').textContent = 'An error occurred. Please try again.';
        } finally {
            if (btn) btn.disabled = false;
        }
    };
</script>
<div id="page-purchase-note-root" class="space-y-8 animate-fade-in text-slate-800">

    <!-- DASHBOARD CARDS -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex items-center space-x-4 hover-card-trigger">
            <div class="p-3 bg-maroon rounded-xl flex-shrink-0">
                <i data-lucide="shopping-cart" class="w-6 h-6 text-gold"></i>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Total Transactions</p>
                <h3 id="stat-total-transactions" class="text-2xl font-extrabold text-slate-800 tracking-tight">0</h3>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex items-center space-x-4 hover-card-trigger">
            <div class="p-3 bg-maroon rounded-xl flex-shrink-0">
                <i data-lucide="clock" class="w-6 h-6 text-gold"></i>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Open Notes</p>
                <h3 id="stat-open-notes" class="text-2xl font-extrabold text-slate-800 tracking-tight">0</h3>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm flex items-center space-x-4 hover-card-trigger">
            <div class="p-3 bg-maroon rounded-xl flex-shrink-0">
                <i data-lucide="banknote" class="w-6 h-6 text-gold"></i>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Total Amount</p>
                <h3 id="stat-total-amount" class="text-2xl font-extrabold text-slate-800 tracking-tight">₱ 0.00</h3>
            </div>
        </div>
    </div>

    <!-- MAIN ACTION BAR -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
        <div>
            <h2 class="text-lg font-bold text-slate-800">Purchase Note Management</h2>
            <p class="text-xs text-slate-400">Manage and track all purchasing transactions and notes.</p>
        </div>
        
        <div class="flex items-center space-x-3">
            <button onclick="openPurchaseNoteFilters()" class="px-4 py-2.5 bg-white border border-slate-200 hover:border-maroon-200 hover:bg-slate-50 text-slate-700 hover:text-maroon text-xs font-bold rounded-xl shadow-sm flex items-center space-x-2 transition-all">
                <i data-lucide="sliders-horizontal" class="w-4 h-4 text-maroon"></i>
                <span>Filter</span>
            </button>
            <button onclick="openAddNoteModal()" class="px-4 py-2.5 bg-maroon text-white hover:bg-maroon-800 text-xs font-bold rounded-xl shadow-md flex items-center space-x-2 transition-all">
                <i data-lucide="plus-circle" class="w-4 h-4 text-gold"></i>
                <span>Add Purchase Note</span>
            </button>
        </div>
    </div>

    <!-- TABS SECTION -->
    <div class="flex items-center space-x-1 bg-white p-1 rounded-2xl border border-slate-100 shadow-sm w-fit">
        <button id="tab-active" onclick="switchMainTab('active')" class="px-6 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all duration-200 bg-maroon text-white shadow-md">
            Active Notes
        </button>
        <button id="tab-closed" onclick="switchMainTab('closed')" class="px-6 py-2.5 rounded-xl text-xs font-bold uppercase tracking-widest transition-all duration-200 text-slate-500 hover:bg-slate-50">
            Closed Notes
        </button>
    </div>

    <!-- MAIN TABLE SECTION -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden text-slate-800">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50/80 border-b border-slate-100">
                    <tr>
                        <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-nowrap">P.O Number</th>
                        <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-nowrap">Supplier Name</th>
                        <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-nowrap text-nowrap">Date Issued</th>
                        <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-nowrap">Total Amount</th>
                        <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-nowrap text-center">Status</th>
                        <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-nowrap">Reference</th>
                        <th class="p-4 px-6 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center text-nowrap">Action</th>
                    </tr>
                    <tr class="bg-slate-50/30">
                        <th class="p-2 px-3"><input type="text" data-col="poNumber" placeholder="Search PO..." class="column-search-input text-[9px] w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-maroon/10"></th>
                        <th class="p-2 px-3"><input type="text" data-col="supplierName" placeholder="Search Supplier..." class="column-search-input text-[9px] w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-maroon/10"></th>
                        <th class="p-2 px-3"><input type="text" data-col="date" placeholder="Search Date..." class="column-search-input text-[9px] w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-maroon/10"></th>
                        <th class="p-2 px-3"><input type="text" data-col="totalAmount" placeholder="Search Amount..." class="column-search-input text-[9px] w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-maroon/10"></th>
                        <th class="p-2 px-3"><input type="text" data-col="status" placeholder="Search Status..." class="column-search-input text-[9px] w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-maroon/10"></th>
                        <th class="p-2 px-3"><input type="text" data-col="reference" placeholder="Search Ref..." class="column-search-input text-[9px] w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg outline-none focus:ring-2 focus:ring-maroon/10"></th>
                        <th class="p-2 px-3"></th>
                    </tr>
                </thead>
                <tbody id="purchase-note-tbody" class="divide-y divide-slate-100 text-sm">
                    <!-- Dynamic Data -->
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <div class="p-4 border-t border-slate-100 bg-slate-50/30 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                Showing <span id="pn-page-info-from" class="text-maroon">0</span> to <span id="pn-page-info-to" class="text-maroon">0</span> of <span id="pn-page-info-total" class="text-maroon">0</span> entries
            </div>
            <div id="pn-pagination-container" class="flex items-center space-x-1">
                <!-- Pagination buttons will be injected here -->
            </div>
        </div>
    </div>

    <!-- ==================== MODALS ==================== -->

    <?php echo $__env->make('partials.purchase.purchase_note_wizard', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <!-- 1.1 SUPPLIER SEARCH MODAL -->
    <div id="supplier-search-modal" class="fixed inset-0 z-[500] hidden flex items-center justify-center" role="dialog" aria-modal="true">
        <div onclick="toggleModal('supplier-search-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-4xl sm:w-full modal-animate-in mx-4">
            <div class="bg-maroon p-5 flex justify-between items-center text-white">
                <div class="flex items-center space-x-3">
                    <i data-lucide="truck" class="w-5 h-5 text-gold"></i>
                    <h3 class="text-sm font-bold uppercase tracking-widest">Select Supplier</h3>
                </div>
                <button onclick="toggleModal('supplier-search-modal', false)" class="text-white/70 hover:text-white transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div class="p-6">
                <div class="mb-4 relative">
                    <i data-lucide="search" class="absolute left-3 top-2.5 w-4 h-4 text-slate-400"></i>
                    <input type="text" id="supplier-table-search" oninput="filterSupplierTable()" placeholder="Search by name, code, contact, person, or address..." class="w-full pl-10 pr-4 py-2 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-maroon/20 focus:border-maroon outline-none transition-all text-slate-800">
                </div>
                <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto custom-scrollbar max-h-[400px]">
                        <table class="w-full text-left border-collapse">
                            <thead class="sticky top-0 bg-slate-50 border-b border-slate-100 text-slate-800 z-10">
                                <tr>
                                    <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Supplier Code</th>
                                    <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Name</th>
                                    <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Contact No#</th>
                                    <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Contact Person</th>
                                    <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Billing Address</th>
                                </tr>
                                <tr class="bg-slate-50/50">
                                    <th class="p-2 px-3"><input type="text" oninput="filterSupplierColumn('code', this.value)" placeholder="Search Code..." class="column-search-input"></th>
                                    <th class="p-2 px-3"><input type="text" oninput="filterSupplierColumn('name', this.value)" placeholder="Search Name..." class="column-search-input"></th>
                                    <th class="p-2 px-3"><input type="text" oninput="filterSupplierColumn('contact', this.value)" placeholder="Search Contact..." class="column-search-input"></th>
                                    <th class="p-2 px-3"><input type="text" oninput="filterSupplierColumn('contactPerson', this.value)" placeholder="Search Person..." class="column-search-input"></th>
                                    <th class="p-2 px-3"><input type="text" oninput="filterSupplierColumn('address', this.value)" placeholder="Search Address..." class="column-search-input"></th>
                                </tr>
                            </thead>
                            <tbody id="supplier-search-tbody" class="divide-y divide-slate-100 text-xs text-slate-700">
                                <!-- JS rendered -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="mt-4 flex flex-col md:flex-row justify-between items-center gap-4 bg-slate-50 p-4 rounded-xl border border-slate-100">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                        Showing <span id="supplier-pagination-from" class="text-maroon">0</span> to <span id="supplier-pagination-to" class="text-maroon">0</span> of <span id="supplier-pagination-total" class="text-maroon">0</span> suppliers
                    </div>
                    <div id="supplier-pagination-container" class="flex items-center space-x-1">
                        <!-- Pagination buttons will be injected here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2.1 ITEM SEARCH MODAL -->
    <div id="item-search-modal" class="fixed inset-0 z-[500] hidden flex items-center justify-center" role="dialog" aria-modal="true">
        <div onclick="toggleModal('item-search-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-5xl sm:w-full modal-animate-in mx-4">
            <div class="bg-maroon p-5 flex justify-between items-center text-white">
                <div class="flex items-center space-x-3">
                    <i data-lucide="package-search" class="w-5 h-5 text-gold"></i>
                    <h3 class="text-sm font-bold uppercase tracking-widest">Search Items</h3>
                </div>
                <button onclick="toggleModal('item-search-modal', false)" class="text-white/70 hover:text-white transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div class="p-6">
                <div class="mb-4 flex justify-between items-center gap-4">
                    <div class="relative flex-1">
                        <i data-lucide="search" class="absolute left-3 top-2.5 w-4 h-4 text-slate-400"></i>
                        <input type="text" id="item-table-search" oninput="filterItemTable()" placeholder="Search by description, part no, or code..." class="w-full pl-10 pr-4 py-2 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-maroon/20 focus:border-maroon outline-none transition-all text-slate-800">
                    </div>
                    <button onclick="addSelectedItems()" class="px-6 py-2 bg-maroon text-white text-xs font-bold rounded-xl hover:bg-maroon-800 transition-all flex items-center space-x-2 shadow-lg">
                        <i data-lucide="plus-square" class="w-4 h-4 text-gold"></i>
                        <span>ADD SELECTED ITEMS</span>
                    </button>
                </div>
                <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto custom-scrollbar max-h-[400px]">
                        <table class="w-full text-left border-collapse">
                            <thead class="sticky top-0 bg-slate-50 border-b border-slate-100 text-slate-800 z-10">
                                <tr>
                                    <th class="p-3 px-4 w-10">
                                        <input type="checkbox" id="select-all-items" onclick="toggleSelectAllItems(this.checked)" class="w-4 h-4 rounded border-slate-300 text-maroon focus:ring-maroon">
                                    </th>
                                    <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Item Code</th>
                                    <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Part No#</th>
                                    <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Description</th>
                                    <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Unit</th>
                                    <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Category</th>
                                    <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Last Purchase</th>
                                    <th class="p-3 px-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Action</th>
                                </tr>
                                <tr class="bg-slate-50/50">
                                    <th></th>
                                    <th class="p-2 px-3"><input type="text" oninput="filterItemColumn('code', this.value)" placeholder="Search Code..." class="column-search-input"></th>
                                    <th class="p-2 px-3"><input type="text" oninput="filterItemColumn('partNo', this.value)" placeholder="Search Part..." class="column-search-input"></th>
                                    <th class="p-2 px-3"><input type="text" oninput="filterItemColumn('desc', this.value)" placeholder="Search Desc..." class="column-search-input"></th>
                                    <th class="p-2 px-3"></th>
                                    <th class="p-2 px-3"><input type="text" oninput="filterItemColumn('cat', this.value)" placeholder="Search Cat..." class="column-search-input"></th>
                                    <th class="p-2 px-3"></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="item-search-tbody" class="divide-y divide-slate-100 text-xs text-slate-700">
                                <!-- Dynamically Added Rows -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- Pagination Controls -->
                <div class="mt-4 flex flex-col md:flex-row justify-between items-center gap-4 bg-slate-50 p-4 rounded-xl border border-slate-100">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                        Showing <span id="item-pagination-from" class="text-maroon">0</span> to <span id="item-pagination-to" class="text-maroon">0</span> of <span id="item-pagination-total" class="text-maroon">0</span> items
                    </div>
                    <div id="item-pagination-container" class="flex items-center space-x-1">
                        <!-- Pagination buttons will be injected here -->
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button onclick="toggleModal('item-search-modal', false)" class="px-8 py-2.5 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-200 transition-all uppercase tracking-widest">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. VIEW ITEMS MODAL -->
    <div id="view-purchase-note-modal" class="fixed inset-0 z-[300] hidden flex items-center justify-center" role="dialog" aria-modal="true">
        <div onclick="toggleModal('view-purchase-note-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-7xl sm:w-full modal-animate-in mx-4">
            <div class="bg-maroon p-6 flex justify-between items-center border-b-4 border-gold text-white">
                <div class="flex items-center space-x-3">
                    <div class="p-2 bg-white/15 rounded-lg">
                        <i data-lucide="file-text" class="w-5 h-5 text-gold"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold tracking-widest">P.O ITEMS DETAIL</h3>
                        <p class="text-[10px] text-white/70 uppercase tracking-widest">Transaction: #00000001</p>
                    </div>
                </div>
                <button onclick="toggleModal('view-purchase-note-modal', false)" class="text-white/70 hover:text-white transition-colors p-1">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div class="p-6 flex flex-col md:flex-row gap-6 bg-gradient-to-br from-slate-50 to-white">
                <!-- LEFT SIDE: Supplier Info (30%) -->
                <div class="w-full md:w-[30%] space-y-6">
                    <div class="bg-white p-5 rounded-2xl border-2 border-gold shadow-lg hover:shadow-xl transition-shadow">
                        <div class="flex items-center space-x-2 mb-4 pb-3 border-b-2 border-gold">
                            <div class="w-1 h-6 bg-maroon rounded"></div>
                            <h4 class="text-xs font-bold text-maroon uppercase tracking-widest">Supplier Basic Info</h4>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mb-1">Supplier Code</p>
                                <p id="view-supplier-code" class="text-sm font-bold text-maroon bg-gradient-to-r from-maroon/5 to-transparent px-2 py-1 rounded">N/A</p>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mb-1">Name</p>
                                <p id="view-supplier-name" class="text-sm font-bold text-slate-800 bg-slate-50 px-2 py-1 rounded">N/A</p>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mb-1">Contact No#</p>
                                <p id="view-supplier-contact" class="text-sm text-slate-700 bg-slate-50 px-2 py-1 rounded">N/A</p>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mb-1">Contact Person</p>
                                <p id="view-supplier-person" class="text-sm text-slate-700 bg-slate-50 px-2 py-1 rounded">N/A</p>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mb-1">Billing Address</p>
                                <p id="view-supplier-address" class="text-sm text-slate-700 leading-relaxed bg-slate-50 px-2 py-1 rounded">N/A</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT SIDE: Selected Items (70%) -->
                <div class="w-full md:w-[70%] space-y-6">
                    <div id="view-transfer-history-banner" class="hidden items-center gap-2 bg-amber-50 border border-amber-200 p-3 rounded-xl text-xs font-medium"></div>
                    <div class="bg-white rounded-xl border-2 border-gold shadow-lg overflow-hidden">
                        <div class="overflow-x-auto custom-scrollbar max-h-[400px]">
                            <table class="w-full text-left border-collapse">
                                <thead class="sticky-header bg-gradient-to-r from-maroon to-maroon-800 border-b-2 border-gold text-white">
                                    <tr>
                                        <th class="p-3 px-4 text-[10px] font-bold text-gold uppercase tracking-widest">Item Code</th>
                                        <th class="p-3 px-4 text-[10px] font-bold text-gold uppercase tracking-widest">Part No</th>
                                        <th class="p-3 px-4 text-[10px] font-bold text-gold uppercase tracking-widest">Description</th>
                                        <th class="p-3 px-4 text-[10px] font-bold text-gold uppercase tracking-widest">QTY</th>
                                        <th class="p-3 px-4 text-[10px] font-bold text-gold uppercase tracking-widest">UNIT</th>
                                        <th class="p-3 px-4 text-[10px] font-bold text-gold uppercase tracking-widest">UNIT COST</th>
                                        <th class="p-3 px-4 text-[10px] font-bold text-gold uppercase tracking-widest text-right">SUB TOTAL</th>
                                    </tr>
                                    <tr class="bg-maroon/10 border-t border-gold/30">
                                        <th class="p-2 px-3"><input type="text" data-col="itemCode" placeholder="Search..." class="column-search-input border-l-2 border-gold focus:border-gold"></th>
                                        <th class="p-2 px-3"><input type="text" data-col="partNo" placeholder="Search..." class="column-search-input border-l-2 border-gold focus:border-gold"></th>
                                        <th class="p-2 px-3"><input type="text" data-col="desc" placeholder="Search..." class="column-search-input border-l-2 border-gold focus:border-gold"></th>
                                        <th class="p-2 px-3"><input type="text" data-col="qty" placeholder="Search..." class="column-search-input border-l-2 border-gold focus:border-gold"></th>
                                        <th class="p-2 px-3"><input type="text" data-col="unit" placeholder="Search..." class="column-search-input border-l-2 border-gold focus:border-gold"></th>
                                        <th class="p-2 px-3"><input type="text" data-col="cost" placeholder="Search..." class="column-search-input border-l-2 border-gold focus:border-gold"></th>
                                        <th class="p-2 px-3"></th>
                                    </tr>
                                </thead>
                                <tbody id="view-items-tbody" class="divide-y divide-gold/20 text-xs text-slate-700 bg-white">
                                    <!-- Dynamic Data -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="flex items-center justify-between p-5 bg-gradient-to-r from-maroon to-maroon-800 rounded-2xl border-2 border-gold shadow-lg text-white">
                        <div class="flex items-center space-x-4">
                            <div class="p-3 bg-white/15 rounded-xl flex-shrink-0 border border-gold/30">
                                <i data-lucide="package-check" class="w-6 h-6 text-gold"></i>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold text-gold/90 uppercase tracking-widest mb-1 block">Total P.O Items Summary</span>
                                <h4 id="view-items-grand-total" class="text-2xl font-extrabold text-gold tracking-tight">₱ 0.00</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. FILTER MODAL -->
    <div id="filter-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center" role="dialog" aria-modal="true">
        <div onclick="toggleModal('filter-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-md sm:w-full modal-animate-in mx-4">
            <div class="bg-maroon p-5 flex justify-between items-center text-white">
                <h3 class="text-sm font-bold uppercase tracking-widest flex items-center gap-2">
                    <i data-lucide="sliders-horizontal" class="w-4 h-4 text-gold"></i>
                    Filter Records
                </h3>
                <button onclick="toggleModal('filter-modal', false)" class="text-white/70 hover:text-white transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div class="p-6 space-y-6">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Filter by Date Range</label>
                    <div class="grid grid-cols-2 gap-3 text-slate-800">
                        <div>
                            <p class="text-[9px] text-slate-400 font-bold mb-1 ml-1 uppercase">From</p>
                            <input type="date" id="pn-filter-from-date" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-maroon/20 focus:border-maroon outline-none transition-all">
                        </div>
                        <div>
                            <p class="text-[9px] text-slate-400 font-bold mb-1 ml-1 uppercase">To</p>
                            <input type="date" id="pn-filter-to-date" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-maroon/20 focus:border-maroon outline-none transition-all">
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Filter by Total Amount</label>
                    <div class="grid grid-cols-2 gap-3 text-slate-800">
                        <div>
                            <p class="text-[9px] text-slate-400 font-bold mb-1 ml-1 uppercase">Min</p>
                            <input type="number" id="pn-filter-min-amount" placeholder="₱ 0.00" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-maroon/20 focus:border-maroon outline-none transition-all">
                        </div>
                        <div>
                            <p class="text-[9px] text-slate-400 font-bold mb-1 ml-1 uppercase">Max</p>
                            <input type="number" id="pn-filter-max-amount" placeholder="₱ 0.00" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-maroon/20 focus:border-maroon outline-none transition-all">
                        </div>
                    </div>
                </div>
            </div>
            <div class="px-6 pb-3">
                <p id="pn-filter-error" class="hidden rounded-xl border border-red-100 bg-red-50 px-3 py-2 text-[11px] font-bold text-red-600"></p>
            </div>
            <div class="p-6 pt-0 flex space-x-3">
                <button onclick="resetPurchaseNoteFilters()" class="flex-1 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] font-bold rounded-xl transition-all uppercase tracking-widest">Reset</button>
                <button onclick="applyPurchaseNoteFilters()" class="flex-1 px-4 py-2.5 bg-maroon text-white hover:bg-maroon-800 text-[10px] font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg shadow-maroon/20">Apply Filters</button>
            </div>
        </div>
    </div>

    <!-- 6.5 ITEM EXISTS IN OPEN/PARTIAL NOTE MODAL -->
    <div id="item-exists-modal" class="fixed inset-0 z-[600] hidden flex items-center justify-center" role="dialog" aria-modal="true">
        <div onclick="toggleModal('item-exists-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-md sm:w-full modal-animate-in mx-4">
            <div class="bg-maroon p-5 flex items-center justify-between text-white">
                <div class="flex items-center space-x-3">
                    <div class="p-2 bg-white/10 rounded-lg">
                        <i data-lucide="alert-circle" class="w-5 h-5 text-gold"></i>
                    </div>
                    <h3 class="text-base font-bold">Item Already on Note</h3>
                </div>
                <button onclick="toggleModal('item-exists-modal', false)" class="text-white/70 hover:text-white transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <p id="item-exists-message" class="text-xs text-slate-600 leading-relaxed"></p>
                <div id="item-exists-notes-list" class="space-y-2 max-h-40 overflow-y-auto custom-scrollbar"></div>
                <div class="flex gap-3 pt-2">
                    <button type="button" id="item-exists-close-btn" onclick="toggleModal('item-exists-modal', false)" class="flex-1 px-4 py-3 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded-xl transition-all uppercase tracking-widest">Close</button>
                    <button type="button" id="item-exists-continue-btn" onclick="void 0" class="hidden flex-1 px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg shadow-blue-600/30">Continue</button>
                    <button type="button" id="item-exists-go-btn" onclick="goToNoteFromExistsModal()" class="flex-1 px-4 py-3 bg-maroon hover:bg-maroon-800 text-white text-xs font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg shadow-maroon/30 ring-2 ring-gold ring-offset-2 pn-go-to-note-highlight">
                        Go to Note
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 6b. SYNC LOADING MODAL -->
    <div id="pn-sync-modal" class="fixed inset-0 z-[690] hidden flex items-center justify-center" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-950/85 transition-opacity backdrop-blur-md z-[690]"></div>
        <div class="relative bg-white rounded-3xl p-8 shadow-2xl transform transition-all max-w-lg w-full modal-animate-in mx-4 border-t-8 border-maroon z-[691]">
            <div class="flex items-start gap-4 mb-6">
                <div id="pn-sync-spinner" class="h-14 w-14 rounded-2xl bg-maroon/10 border border-maroon/20 flex items-center justify-center shrink-0">
                    <i data-lucide="loader-2" class="h-7 w-7 text-maroon animate-spin"></i>
                </div>
                <div id="pn-sync-success-icon" class="h-14 w-14 rounded-2xl bg-green-100 border border-green-200 hidden items-center justify-center shrink-0">
                    <i data-lucide="check-circle" class="h-7 w-7 text-green-600"></i>
                </div>
                <div id="pn-sync-error-icon" class="h-14 w-14 rounded-2xl bg-red-100 border border-red-200 hidden items-center justify-center shrink-0">
                    <i data-lucide="circle-alert" class="h-7 w-7 text-red-600"></i>
                </div>
                <div>
                    <h3 id="pn-sync-title" class="text-lg font-extrabold text-slate-800 uppercase tracking-widest">Syncing Purchase Note</h3>
                    <p id="pn-sync-message" class="text-sm text-slate-500 leading-relaxed mt-1">Saving the Purchase Note and item details.</p>
                </div>
            </div>

            <div class="space-y-3 mb-7">
                <div data-pn-sync-step="purchase_notes" class="pn-sync-step flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
                    <span class="text-xs font-bold text-slate-600">Purchase Note</span>
                    <span class="pn-sync-step-status text-[10px] font-bold uppercase tracking-widest text-slate-400">Pending</span>
                </div>
                <div data-pn-sync-step="purchase_note_items" class="pn-sync-step flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
                    <span class="text-xs font-bold text-slate-600">Purchase Note Items</span>
                    <span class="pn-sync-step-status text-[10px] font-bold uppercase tracking-widest text-slate-400">Pending</span>
                </div>
                <div data-pn-sync-step="purchase_note_refresh" class="pn-sync-step flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
                    <span class="text-xs font-bold text-slate-600">Purchase Note Refresh</span>
                    <span class="pn-sync-step-status text-[10px] font-bold uppercase tracking-widest text-slate-400">Pending</span>
                </div>
            </div>

            <button id="pn-sync-close-btn" onclick="toggleModal('pn-sync-modal', false)" class="hidden w-full px-4 py-3 bg-slate-100 text-slate-600 rounded-2xl text-xs font-bold hover:bg-slate-200 transition-all uppercase tracking-widest">Close</button>
        </div>
    </div>

    <!-- 7. CONFIRMATION MODAL -->
    <div id="confirmation-modal" class="fixed inset-0 z-[600] hidden flex items-center justify-center" role="dialog" aria-modal="true">
        <div onclick="toggleModal('confirmation-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-sm sm:w-full modal-animate-in mx-4">
            <div class="p-6 text-center">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-2xl bg-maroon mb-4 shadow-lg shadow-maroon/20">
                    <i id="confirm-icon" data-lucide="alert-triangle" class="h-8 w-8 text-gold"></i>
                </div>
                <h3 id="confirm-title" class="text-lg font-bold text-slate-800 mb-2">Confirmation Required</h3>
                <p id="confirm-message" class="text-xs text-slate-500 leading-relaxed">Are you sure you want to proceed with this action? This cannot be undone.</p>
            </div>
            <div class="bg-slate-50 px-6 py-4 flex flex-col sm:flex-row-reverse gap-2">
                <button id="confirm-action-btn" class="w-full px-4 py-2 bg-maroon hover:bg-maroon-800 text-white text-xs font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg shadow-maroon/20">Confirm</button>
                <button onclick="toggleModal('confirmation-modal', false)" class="w-full px-4 py-2 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest">Cancel</button>
            </div>
        </div>
    </div>

    <!-- 8. SUCCESS MODAL -->
    <div id="success-modal" class="fixed inset-0 z-[700] hidden flex items-center justify-center" role="dialog" aria-modal="true">
        <div onclick="toggleModal('success-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-sm sm:w-full modal-animate-in mx-4">
            <div class="p-8 text-center">
                <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-2xl bg-maroon mb-6 shadow-xl shadow-maroon/20">
                    <i data-lucide="check-circle" class="h-12 w-12 text-gold"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-800 mb-2">Success!</h3>
                <p id="success-message" class="text-xs text-slate-500">Action completed successfully.</p>
            </div>
            <div class="p-6 pt-0">
                <button onclick="toggleModal('success-modal', false)" class="w-full px-4 py-3 bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg shadow-emerald-200">Dismiss</button>
            </div>
        </div>
    </div>

    <!-- 9. PASSWORD RE-ENTRY MODAL -->
    <div id="password-modal" class="fixed inset-0 z-[800] hidden flex items-center justify-center" role="dialog" aria-modal="true">
        <div onclick="toggleModal('password-modal', false)" class="fixed inset-0 bg-slate-900/60 transition-opacity backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-sm sm:w-full modal-animate-in mx-4">
            <div class="p-6 text-center">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-2xl bg-red-500 mb-4 shadow-lg shadow-red-200">
                    <i data-lucide="lock" class="h-8 w-8 text-white"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Confirm Deletion</h3>
                <p class="text-xs text-slate-500 leading-relaxed mb-4">This item has already been processed. Enter your password to delete it.</p>
                <div class="space-y-3 text-left">
                    <input type="hidden" id="password-delete-index" value="">
                    <div id="password-error-msg" class="text-xs text-red-500 hidden mb-2">Incorrect password. Please try again.</div>
                    <div id="password-input-container"></div>
                </div>
            </div>
            <div class="bg-slate-50 px-6 py-4 flex flex-col sm:flex-row-reverse gap-2">
                <button onclick="verifyPasswordDeleteV2()" class="w-full px-4 py-2 bg-red-500 hover:bg-red-600 text-white text-xs font-bold rounded-xl transition-all uppercase tracking-widest shadow-lg shadow-red-200">Confirm</button>
                <button onclick="toggleModal('password-modal', false); document.getElementById('password-input-container').innerHTML=''" class="w-full px-4 py-2 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-widest">Cancel</button>
            </div>
        </div>
    </div>


</div>

<?php $__env->startPush('scripts'); ?>
    <script src="<?php echo e(asset('js/Purchase-Note.js')); ?>?v=<?php echo e(time()); ?>"></script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>




<?php echo $__env->make('partials.special_user.special_sidebar_navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\hatdog\resources\views/Special_User/Purchase/Purchase-Note.blade.php ENDPATH**/ ?>