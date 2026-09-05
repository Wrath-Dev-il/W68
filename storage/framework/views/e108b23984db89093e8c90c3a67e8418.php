<?php $__env->startPush('styles'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/product_master_list.css')); ?>?v=<?php echo e(time()); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('prod_master_content'); ?>
<script>
    window.prodRoutes = {
        fetch: '<?php echo e(route("regular.prod-master.data")); ?>',
        create: '<?php echo e(route("regular.prod-master.create")); ?>',
        update: '<?php echo e(route("regular.prod-master.update", ["id" => ":id"])); ?>',
        delete: '<?php echo e(route("regular.prod-master.delete", ["id" => ":id"])); ?>',
       'ledger': '<?php echo e(route("regular.prod-master.ledger", ["id" => ":id"])); ?>',
        'select': '<?php echo e(route("regular.prod-master.select")); ?>',
        'reorderLevel': '<?php echo e(route("regular.prod-master.reorder-level")); ?>',
        'catalog': '<?php echo e(route("regular.prod-master.catalog")); ?>',
        'priceList': '<?php echo e(route("regular.prod-master.price-list")); ?>',
        'selectedFilters': '<?php echo e(route("regular.prod-master.selected-filters")); ?>',
        'activeNotes': '<?php echo e(route("regular.prod-master.active-notes", ["id" => ":id"])); ?>',
        'priceCodesUpdate': '<?php echo e(route("regular.prod-master.price-codes.update", ["id" => ":id"])); ?>'
    };
    window.productMasterViewEditor = true;
</script>
<!-- Dashboard Content for Product Master List -->
<div id="page-prod-master-root" class="space-y-8 animate-fade-in">

    <!-- SUB-HEADER INTERACTIVE PANEL -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
        <div>
            <h2 class="text-lg font-bold text-slate-800">Product Master List Administration</h2>
            <p class="text-xs text-slate-400">Manage your comprehensive product inventory, pricing, and stock levels.</p>
        </div>
        
        <!-- HEADER QUICK ACTIONS -->
        <div class="flex items-center space-x-3">
            <!-- Bulk Delete Button -->
            <button id="bulk-delete-btn" onclick="handleBulkDelete()" class="px-4 py-2.5 bg-white border border-slate-200 hover:border-red-200 hover:bg-red-50 text-slate-700 hover:text-red-600 text-xs font-bold rounded-xl shadow-sm flex items-center space-x-2 transition-all hidden">
                <i data-lucide="trash-2" class="w-4 h-4 text-red-500"></i>
                <span>Bulk Delete (<span id="selected-count">0</span>)</span>
            </button>

            <!-- Generate Reports Button -->
            <button onclick="toggleModal('generate-report-modal', true)" class="px-4 py-2.5 bg-white border border-slate-200 hover:border-maroon-200 hover:bg-slate-50 text-slate-700 hover:text-maroon text-xs font-bold rounded-xl shadow-sm flex items-center space-x-2 transition-all">
                <i data-lucide="file-text" class="w-4 h-4 text-slate-500"></i>
                <span>Generate Reports</span>
            </button>

            <!-- Reorder Level Button -->
            <button onclick="toggleModal('reorder-level-modal', true)" class="px-4 py-2.5 bg-white border border-slate-200 hover:border-amber-200 hover:bg-slate-50 text-slate-700 hover:text-amber-600 text-xs font-bold rounded-xl shadow-sm flex items-center space-x-2 transition-all">
                <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-500"></i>
                <span>Reorder Level</span>
            </button>

            <!-- Search Specific Button -->
            <button onclick="toggleModal('search-specific-modal', true)" class="px-4 py-2.5 bg-white border border-slate-200 hover:border-blue-200 hover:bg-slate-50 text-slate-700 hover:text-blue-600 text-xs font-bold rounded-xl shadow-sm flex items-center space-x-2 transition-all">
                <i data-lucide="search" class="w-4 h-4 text-blue-500"></i>
                <span>Search Specific</span>
            </button>
            
            <!-- Filter Button -->
            <button onclick="toggleModal('filter-modal', true)" class="px-4 py-2.5 bg-white border border-slate-200 hover:border-maroon-200 hover:bg-slate-50 text-slate-700 hover:text-maroon text-xs font-bold rounded-xl shadow-sm flex items-center space-x-2 transition-all">
                <i data-lucide="sliders-horizontal" class="w-4 h-4 text-slate-500"></i>
                <span>Filter</span>
            </button>
            
            <!-- Add Product Button -->
            <button onclick="toggleModal('add-product-modal', true)" class="px-4 py-2.5 bg-maroon hover:bg-maroon-hover text-white text-xs font-bold rounded-xl shadow-lg flex items-center space-x-2 transition-all hover:shadow-xl">
                <i data-lucide="package-plus" class="w-4 h-4 text-gold"></i>
                <span>Add Product</span>
            </button>
        </div>
    </div>

    <!-- 3 SYSTEM DASHBOARD STATS CARDS -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        
        <!-- Stat Card 1: TOTAL PRODUCTS -->
        <div onclick="switchTab('all')" class="cursor-pointer bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between transition-all duration-200 hover:shadow-md hover:border-slate-200 hover-card-trigger">
            <div class="space-y-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Products</span>
                <h3 id="card-count-total" class="text-2xl font-extrabold text-slate-800 tracking-tight"><?php echo e($total_products ?? 0); ?></h3>
            </div>
            <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-maroon-gradient shadow-lg flex-shrink-0 ml-4 border border-goldlining-500/20">
                <i data-lucide="package" class="w-5 h-5 text-gold"></i>
            </div>
        </div>

        <!-- Stat Card 2: NEWLY ADDED ITEMS -->
        <div onclick="switchTab('newly')" class="cursor-pointer bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between transition-all duration-200 hover:shadow-md hover:border-slate-200 hover-card-trigger">
            <div class="space-y-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Newly Added Items</span>
                <h3 id="card-count-newly" class="text-2xl font-extrabold text-slate-800 tracking-tight"><?php echo e($newly_added ?? 0); ?></h3>
            </div>
            <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-maroon-gradient shadow-lg flex-shrink-0 ml-4 border border-goldlining-500/20">
                <i data-lucide="sparkles" class="w-5 h-5 text-gold"></i>
            </div>
        </div>

        <!-- Stat Card 3: LOW STOCK ITEMS -->
        <div onclick="switchTab('low')" class="cursor-pointer bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between transition-all duration-200 hover:shadow-md hover:border-slate-200 hover-card-trigger">
            <div class="space-y-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Low Stock Items</span>
                <h3 id="card-count-low" class="text-2xl font-extrabold text-slate-800 tracking-tight text-red-600"><?php echo e($low_stock ?? 0); ?></h3>
            </div>
            <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-maroon-gradient shadow-lg flex-shrink-0 ml-4 border border-goldlining-500/20">
                <i data-lucide="alert-triangle" class="w-5 h-5 text-gold"></i>
            </div>
        </div>

    </div>

    <!-- MAIN INTERACTIVE DATATABLES WORKSPACE -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden flex flex-col justify-between">
        
        <!-- Table Control Tabs Panel -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between border-b border-slate-100 bg-slate-50/50">
            <!-- Navigation Tabs -->
            <div class="flex flex-wrap overflow-hidden">
                <button data-tab="all" class="prod-tab-btn tab-active px-5 py-3 text-sm font-bold flex items-center space-x-2 transition-all duration-200 border-r border-slate-100">
                    <i data-lucide="layers" class="w-4 h-4"></i>
                    <span>All Products</span>
                </button>
                
                <button data-tab="newly" class="prod-tab-btn tab-inactive px-5 py-3 text-sm font-semibold flex items-center space-x-2 transition-all duration-200 border-r border-slate-100">
                    <i data-lucide="sparkle" class="w-4 h-4"></i>
                    <span>Newly Added</span>
                </button>
                
                <button data-tab="low" class="prod-tab-btn tab-inactive px-5 py-3 text-sm font-semibold flex items-center space-x-2 transition-all duration-200">
                    <i data-lucide="trending-down" class="w-4 h-4"></i>
                    <span>Low Stock</span>
                </button>
            </div>
            
            <div class="p-3 pr-5 text-right">
                <span class="text-[10px] text-slate-400 font-semibold tracking-wider uppercase">Enterprise Inventory Ledger</span>
            </div>
        </div>

        <div class="overflow-x-auto custom-scrollbar pb-10">
            <table class="w-full text-left border-collapse table-fixed min-w-[1320px]">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/20 text-slate-400 text-[10px] font-bold uppercase tracking-wider">
                        <th class="py-2.5 px-4 text-center w-[50px]"><input type="checkbox" id="select-all-checkbox" class="rounded border-slate-300" onclick="handleSelectAll(this)"></th>
                        <th class="py-2.5 px-4 w-[80px]">Picture</th>
                        <th class="py-2.5 px-4 w-[120px] sortable-header" data-sort-key="productCode" onclick="window.handleSortClick('productCode')" style="cursor:pointer;user-select:none;">Product Code <span class="sort-indicator">&#8597;</span></th>
                        <th class="py-2.5 px-4 w-[150px] sortable-header" data-sort-key="position" onclick="window.handleSortClick('position')" style="cursor:pointer;user-select:none;">Position <span class="sort-indicator">&#8597;</span></th>
                        <th class="py-2.5 px-4 w-[120px] sortable-header" data-sort-key="partNumber" onclick="window.handleSortClick('partNumber')" style="cursor:pointer;user-select:none;">Part No# <span class="sort-indicator">&#8597;</span></th>
                        <th class="py-2.5 px-4 w-[250px] sortable-header" data-sort-key="description" onclick="window.handleSortClick('description')" style="cursor:pointer;user-select:none;">Description <span class="sort-indicator">&#8597;</span></th>
                        <th class="py-2.5 px-4 w-[200px] sortable-header" data-sort-key="application" onclick="window.handleSortClick('application')" style="cursor:pointer;user-select:none;">Application <span class="sort-indicator">&#8597;</span></th>
                        <th class="py-2.5 px-4 w-[160px] sortable-header" data-sort-key="specification" onclick="window.handleSortClick('specification')" style="cursor:pointer;user-select:none;">Specification <span class="sort-indicator">&#8597;</span></th>
                        <th class="py-2.5 px-4 w-[120px] sortable-header" data-sort-key="brand" onclick="window.handleSortClick('brand')" style="cursor:pointer;user-select:none;">Brand <span class="sort-indicator">&#8597;</span></th>
                        <th class="py-2.5 px-4 w-[100px] sortable-header" data-sort-key="onHand" onclick="window.handleSortClick('onHand')" style="cursor:pointer;user-select:none;">QTY (On Hand) <span class="sort-indicator">&#8597;</span></th>
                        <th class="py-2.5 px-4 w-[105px] sortable-header" data-sort-key="restockLevel" onclick="window.handleSortClick('restockLevel')" style="cursor:pointer;user-select:none;">Restock LVL <span class="sort-indicator">&#8597;</span></th>
                        <th class="py-2.5 px-4 w-[120px] sortable-header" data-sort-key="sellingPrice" onclick="window.handleSortClick('sellingPrice')" style="cursor:pointer;user-select:none;">Selling Price <span class="sort-indicator">&#8597;</span></th>
                        <th class="py-2.5 px-4 w-[120px] sortable-header" data-sort-key="priceOnline" onclick="window.handleSortClick('priceOnline')" style="cursor:pointer;user-select:none;">Online Price <span class="sort-indicator">&#8597;</span></th>
                        <th class="py-2.5 px-4 text-center w-[120px]">Action</th>
                    </tr>
                    <!-- Column Specific Search Input Row -->
                    <tr class="border-b border-slate-100 bg-slate-50/10">
                        <th class="px-4"></th>
                        <th class="px-4"></th>
                        <th class="p-1.5 px-4"><input type="text" data-col="productCode" name="product_code" placeholder="Code..." class="column-search-input py-1 text-[10px] w-full"></th>
                        <th class="p-1.5 px-4"><input type="text" data-col="position" name="position" placeholder="Pos..." class="column-search-input py-1 text-[10px] w-full"></th>
                        <th class="p-1.5 px-4"><input type="text" data-col="partNumber" name="part_number" placeholder="Part No..." class="column-search-input py-1 text-[10px] w-full"></th>
                        <th class="p-1.5 px-4"><input type="text" data-col="description" name="description" placeholder="Desc..." class="column-search-input py-1 text-[10px] w-full"></th>
                        <th class="p-1.5 px-4"><input type="text" data-col="application" name="application" placeholder="App..." class="column-search-input py-1 text-[10px] w-full"></th>
                        <th class="p-1.5 px-4"><input type="text" data-col="specification" name="specification" placeholder="Spec..." class="column-search-input py-1 text-[10px] w-full"></th>
                        <th class="p-1.5 px-4"><input type="text" data-col="category" name="category" placeholder="Brand..." class="column-search-input py-1 text-[10px] w-full"></th>
                        <th class="p-1.5 px-4"><input type="text" data-col="onHand" name="on_hand" placeholder="QTY..." class="column-search-input py-1 text-[10px] w-full"></th>
                        <th class="p-1.5 px-4"><input type="text" data-col="restockLevel" name="restock_level" placeholder="Restock..." class="column-search-input py-1 text-[10px] w-full"></th>
                        <th class="p-1.5 px-4"><input type="text" data-col="sellingPrice" name="selling_price" placeholder="Price..." class="column-search-input py-1 text-[10px] w-full"></th>
                        <th class="p-1.5 px-4"><input type="text" data-col="priceOnline" name="price_online" placeholder="Online..." class="column-search-input py-1 text-[10px] w-full"></th>
                        <th class="px-4"></th>
                    </tr>
                </thead>
                <tbody id="product-tbody" class="divide-y divide-slate-100 text-sm">
                    <!-- Data will be loaded via AJAX -->
                    <tr>
                        <td colspan="14" class="py-12 text-center text-slate-400 italic">Loading products...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- FOOTER PAGINATION & SUMMARY -->
        <div class="p-5 border-t border-slate-100 bg-slate-50/30 flex items-center justify-between flex-shrink-0">
            <span id="pagination-info" class="text-xs text-slate-500 font-medium">Showing 0 to 0 of 0 entries</span>
            <div id="pagination-container" class="flex items-center space-x-2">
                <!-- Buttons rendered in JS -->
            </div>
        </div>

    </div>

    <!-- ==================== MODALS SECTION ==================== -->

    <!-- 1. ADD PRODUCT MODAL -->
    <div id="add-product-modal" class="fixed inset-0 z-[900] overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div onclick="toggleModal('add-product-modal', false)" class="fixed inset-0 bg-maroon-900/60 transition-opacity backdrop-blur-sm" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="modal-animate-in inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full border-2 border-goldlining-500/20">
                
                <div class="bg-maroon-gradient px-6 py-4 flex items-center justify-between border-b border-goldlining-500">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="package-plus" class="w-5 h-5 text-gold"></i>
                        <h3 class="text-sm font-bold text-white uppercase tracking-wider">Register New Product</h3>
                    </div>
                    <button onclick="toggleModal('add-product-modal', false)" class="text-slate-300 hover:text-white transition-colors">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <form id="add-product-form" class="max-h-[80vh] overflow-y-auto custom-scrollbar relative">
                    <!-- Absolute Tags (Top Right) -->
                    <div class="absolute top-8 right-8 flex items-center gap-2 z-10">
                        <span class="px-3 py-1.5 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-full border border-slate-200 flex items-center gap-1.5 shadow-sm">
                            <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-400"></i>
                            <?php echo e(date('M d, Y')); ?>

                            <input type="hidden" name="date_added" value="<?php echo e(date('Y-m-d')); ?>">
                        </span>
                        <span class="px-3 py-1.5 bg-emerald-50 text-emerald-600 text-[10px] font-bold rounded-full border border-emerald-100 flex items-center gap-1.5 shadow-sm">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            NEWLY ADDED
                            <input type="hidden" name="status" id="add-product-status" value="Newly">
                        </span>
                    </div>

                    <div class="bg-white px-8 py-8 space-y-8">
                        
                        <!-- Top Row: Picture Section -->
                        <div class="space-y-4">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest">Product Pictures (Max 7)</label>
                            
                            <div class="flex gap-4">
                                <!-- Upload Trigger -->
                                <label for="prod-image-input" class="cursor-pointer flex flex-col items-center justify-center w-32 h-48 border-2 border-dashed border-slate-200 rounded-2xl hover:bg-slate-50 hover:border-maroon/30 transition-all group shrink-0">
                                    <div class="w-10 h-10 bg-slate-50 rounded-full flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                                        <i data-lucide="upload-cloud" class="w-5 h-5 text-slate-400 group-hover:text-maroon"></i>
                                    </div>
                                    <span class="text-[10px] text-slate-400 font-bold group-hover:text-maroon">CLICK TO UPLOAD</span>
                                    <input type="file" id="prod-image-input" multiple class="hidden" accept="image/*">
                                </label>

                                <!-- Multi-Image Grid/Carousel Preview -->
                                <div id="image-preview-grid" class="flex-grow grid grid-cols-4 gap-3 h-48 overflow-y-auto p-2 border border-slate-100 rounded-2xl bg-slate-50 custom-scrollbar">
                                    <!-- Dynamic Previews -->
                                    <div class="col-span-4 flex flex-col items-center justify-center h-full text-slate-300">
                                        <i data-lucide="image" class="w-10 h-10 mb-2"></i>
                                        <span class="text-[10px] font-bold uppercase tracking-widest">Selected Images (0/7)</span>
                                    </div>
                                </div>
                            </div>
                            <p class="text-[10px] text-slate-400 font-medium italic">You can upload up to 7 images. Click any image to view enlarged carousel.</p>
                        </div>

                        <!-- Main Form Fields -->
                        <div class="space-y-6">
                            <!-- Row 1: Code & Part Number -->
                            <div class="grid grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Product Code <span class="text-red-500">*</span></label>
                                    <input type="text" name="product_code" required placeholder="e.g. PRD-0001" class="block w-full px-4 py-3 border border-slate-200 rounded-xl bg-white text-sm focus:border-maroon focus:ring-4 focus:ring-maroon/5 outline-none transition-all">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Part Number <span class="text-[9px] font-semibold text-slate-400 normal-case tracking-normal ml-1">(Optional)</span></label>
                                    <input type="text" name="part_number" placeholder="e.g. PN-9920" class="block w-full px-4 py-3 border border-slate-200 rounded-xl bg-white text-sm focus:border-maroon focus:ring-4 focus:ring-maroon/5 outline-none transition-all">
                                </div>
                            </div>

                            <!-- Pricelist Code -->
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Pricelist Code <span class="text-[9px] font-semibold text-slate-400 normal-case tracking-normal ml-1">(Optional)</span></label>
                                <input type="text" name="pricelist_code" id="add-pricelist-code" maxlength="255" placeholder="Optional pricelist code" class="block w-full px-4 py-3 border border-slate-200 rounded-xl bg-white text-slate-800 focus:border-maroon focus:ring-4 focus:ring-maroon/5 text-sm outline-none transition-all">
                            </div>

                            <!-- Row 2: Description -->
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Description</label>
                                <textarea name="description" placeholder="Enter detailed product description..." class="block w-full px-4 py-3 border border-slate-200 rounded-xl bg-white text-sm focus:border-maroon focus:ring-4 focus:ring-maroon/5 outline-none transition-all resize-none" rows="3"></textarea>
                            </div>

                            <!-- Row 3: Specification -->
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Specification</label>
                                <textarea name="specification" placeholder="e.g. 12V, LH, 1.5L" class="block w-full px-4 py-3 border border-slate-200 rounded-xl bg-white text-sm focus:border-maroon focus:ring-4 focus:ring-maroon/5 outline-none transition-all resize-none" rows="3"></textarea>
                            </div>

                            <!-- Row 4: Application (Multi-Entry) -->
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest">Application</label>
                                    <button type="button" onclick="window.addApplicationEntry('add')" class="flex items-center gap-1 px-2.5 py-1 bg-maroon/10 text-maroon text-[10px] font-bold rounded-lg hover:bg-maroon/20 transition-colors uppercase tracking-wider">
                                        <i data-lucide="plus" class="w-3 h-3"></i> Add More
                                    </button>
                                </div>
                                <input type="hidden" name="application" id="add-application-hidden">
                                <div id="add-application-entries" class="space-y-3">
                                    <!-- Entry 1 (default) -->
                                    <div class="app-entry relative border border-slate-200 rounded-xl p-3 bg-slate-50/50">
                                        <div class="grid grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Car Brand <span class="text-red-500">*</span></label>
                                                <input type="text" data-field="car_brand" placeholder="e.g. Toyota" required class="block w-full px-3 py-2 border border-slate-200 rounded-lg bg-white text-sm focus:border-maroon focus:ring-2 focus:ring-maroon/5 outline-none transition-all">
                                            </div>
                                            <div>
                                                <label class="block text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Car Model <span class="text-red-500">*</span></label>
                                                <input type="text" data-field="car_model" placeholder="e.g. Mirage" required class="block w-full px-3 py-2 border border-slate-200 rounded-lg bg-white text-sm focus:border-maroon focus:ring-2 focus:ring-maroon/5 outline-none transition-all">
                                            </div>
                                            <div>
                                                <label class="block text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Year (From - To) <span class="text-red-500">*</span></label>
                                                <div class="flex items-center gap-1.5">
                                                    <input type="text" data-field="year_from" placeholder="2020" maxlength="4" required class="block w-full px-3 py-2 border border-slate-200 rounded-lg bg-white text-sm text-center focus:border-maroon focus:ring-2 focus:ring-maroon/5 outline-none transition-all">
                                                    <span class="text-slate-400 text-xs font-bold shrink-0">to</span>
                                                    <input type="text" data-field="year_to" placeholder="2026" maxlength="4" required class="block w-full px-3 py-2 border border-slate-200 rounded-lg bg-white text-sm text-center focus:border-maroon focus:ring-2 focus:ring-maroon/5 outline-none transition-all">
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Engine <span class="text-red-500">*</span></label>
                                                <input type="text" data-field="engine" placeholder="e.g. 1KD" required class="block w-full px-3 py-2 border border-slate-200 rounded-lg bg-white text-sm focus:border-maroon focus:ring-2 focus:ring-maroon/5 outline-none transition-all">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Row 4b: Price Code (Multi-Entry) -->
                            <div class="mt-6">
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest">Price Code</label>
                                    <button type="button" onclick="window.addPriceCodeEntry('add')" class="flex items-center gap-1 px-2.5 py-1 bg-maroon/10 text-maroon text-[10px] font-bold rounded-lg hover:bg-maroon/20 transition-colors uppercase tracking-wider">
                                        <i data-lucide="plus" class="w-3 h-3"></i> Add Price Code
                                    </button>
                                </div>
                                <input type="hidden" name="price_codes" id="add-price-codes-hidden">
                                <div id="add-price-codes-entries" class="space-y-3">
                                    <!-- Default entry -->
                                    <div class="pc-entry relative border border-slate-200 rounded-xl p-3 bg-slate-50/50">
                                        <div class="flex items-center gap-3">
                                            <div class="flex-[2]">
                                                <input type="text" data-field="price_code" placeholder="e.g. ABC-001" class="block w-full px-3 py-2 border border-slate-200 rounded-lg bg-white text-sm focus:border-maroon focus:ring-2 focus:ring-maroon/5 outline-none transition-all">
                                            </div>
                                            <div class="flex-1">
                                                <div class="relative">
                                                    <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-slate-400 font-bold text-xs">₱</span>
                                                    <input type="number" data-field="selling_price" step="0.01" min="0" placeholder="0.00" class="block w-full pl-6 pr-2.5 py-2 border border-slate-200 rounded-lg bg-white text-sm font-bold text-slate-700 focus:border-maroon focus:ring-2 focus:ring-maroon/5 outline-none transition-all">
                                                </div>
                                            </div>
                                            <button type="button" onclick="window.removePriceCodeEntry(this)" class="shrink-0 px-2.5 py-1.5 bg-red-50 text-red-500 text-[9px] font-bold rounded-lg hover:bg-red-100 transition-colors uppercase tracking-wider flex items-center gap-1">
                                                <i data-lucide="x" class="w-3 h-3"></i> Remove
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Row 4: Position -->
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Position</label>
                                <input type="text" name="position" placeholder="e.g. Front, Rear, Left, Right" class="block w-full px-4 py-3 border border-slate-200 rounded-xl bg-white text-sm focus:border-maroon focus:ring-4 focus:ring-maroon/5 outline-none transition-all">
                            </div>

                            <!-- Row 5: Unit -->
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Unit</label>
                                <input type="text" name="unit" placeholder="e.g. PCS, SET, PC" class="block w-full px-4 py-3 border border-slate-200 rounded-xl bg-white text-sm focus:border-maroon focus:ring-4 focus:ring-maroon/5 outline-none transition-all">
                            </div>

                            <!-- Row 5b: Restock Level -->
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Restock LVL</label>
                                <input type="number" name="Re_order_level" min="0" step="1" value="0" placeholder="0" class="block w-full px-4 py-3 border border-slate-200 rounded-xl bg-white text-sm focus:border-maroon focus:ring-4 focus:ring-maroon/5 outline-none transition-all">
                            </div>

                            <!-- Brand, Selling Price & Online Price -->
                            <div class="grid grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Brand <span class="text-red-500">*</span></label>
                                    <input type="text" name="category" required placeholder="e.g. Engine Parts" class="block w-full px-4 py-3 border border-slate-200 rounded-xl bg-white text-sm focus:border-maroon focus:ring-4 focus:ring-maroon/5 outline-none transition-all">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Selling Price <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400 font-bold text-sm">₱</span>
                                        <input type="number" name="selling_price" step="0.01" required placeholder="0.00" class="block w-full pl-9 pr-4 py-3 border border-slate-200 rounded-xl bg-white text-sm font-bold text-slate-700 focus:border-maroon focus:ring-4 focus:ring-maroon/5 outline-none transition-all">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Online Price</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400 font-bold text-sm">₱</span>
                                        <input type="number" name="price_online" step="0.01" placeholder="0.00" class="block w-full pl-9 pr-4 py-3 border border-slate-200 rounded-xl bg-white text-sm font-bold text-slate-700 focus:border-maroon focus:ring-4 focus:ring-maroon/5 outline-none transition-all">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Hidden fields for defaults -->
                        <input type="hidden" name="on_hand" value="0">
                        <input type="hidden" name="cost" value="0">
                        <input type="hidden" name="Product_Picture" id="main_product_picture_hidden">
                        <input type="hidden" name="images" id="multi_images_json_hidden">

                    </div>

                    <div class="bg-slate-50 px-8 py-5 flex items-center justify-end space-x-3 border-t border-slate-100">
                        <button type="button" onclick="toggleModal('add-product-modal', false)" class="px-6 py-2.5 text-xs font-bold text-slate-400 hover:text-slate-600 transition-all uppercase tracking-widest">
                            Cancel
                        </button>
                        <button type="button" onclick="if(document.getElementById('add-product-form').reportValidity()) toggleModal('confirm-add-modal', true)" class="px-10 py-2.5 bg-maroon hover:bg-maroon-hover text-white text-xs font-bold rounded-xl shadow-lg shadow-maroon/20 transition-all uppercase tracking-widest">
                            Save Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 2. VIEW PRODUCT MODAL -->
    <div id="view-product-modal" class="fixed inset-0 z-[900] overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div onclick="closeProductViewModal()" class="fixed inset-0 bg-maroon-900/60 transition-opacity backdrop-blur-sm" aria-hidden="true"></div>

            <div class="modal-animate-in relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all w-full border-2 border-goldlining-500/20 flex flex-col" style="width:96vw;max-width:1850px;max-height:94vh;">
                <!-- Modal Header -->
                <div class="bg-maroon-gradient px-6 py-3 flex items-center justify-between border-b border-goldlining-500 flex-shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center space-x-2">
                            <i data-lucide="package-search" class="w-5 h-5 text-gold"></i>
                            <h3 class="text-xs font-bold text-white uppercase tracking-wider">Product Ledger View</h3>
                        </div>
                        <span id="view-edit-mode-badge" class="invisible px-2.5 py-1 rounded-full bg-gold/20 border border-gold/40 text-gold text-[9px] font-black uppercase tracking-widest">Edit Mode</span>
                        <div class="relative w-[142px] h-10 flex-shrink-0">
                            <button id="view-modal-edit-btn" type="button" onclick="activateProductViewEditMode()" class="absolute inset-0 w-full h-full px-4 bg-gold hover:bg-yellow-300 text-maroon-900 text-[10px] font-black rounded-lg shadow-md transition-all flex items-center justify-center gap-1.5 uppercase tracking-wider">
                                <i data-lucide="square-pen" class="w-3.5 h-3.5"></i>
                                <span>Edit Product</span>
                            </button>
                            <button id="view-save-edit-btn" type="button" onclick="saveProductViewChanges()" disabled class="hidden absolute inset-0 w-full h-full px-4 bg-gold hover:bg-yellow-300 text-maroon-900 text-[10px] font-black rounded-lg shadow-md transition-all uppercase tracking-wider disabled:opacity-40 disabled:cursor-not-allowed">
                                <span class="inline-flex items-center justify-center gap-1.5"><i data-lucide="save" class="w-3.5 h-3.5"></i> Save Changes</span>
                            </button>
                        </div>
                        <span id="view-edit-actions-separator" class="hidden text-white/50 text-sm font-bold">|</span>
                        <button id="view-cancel-edit-btn" type="button" onclick="cancelProductViewEditMode()" class="hidden px-4 py-2 bg-white/10 border border-white/25 hover:bg-white/20 text-white text-[10px] font-bold rounded-lg transition-all uppercase tracking-wider">
                            Cancel Edit
                        </button>
                    </div>
                    <div class="flex items-center gap-3">
                        <button onclick="closeProductViewModal()" class="p-2 text-slate-300 hover:text-white hover:bg-white/10 rounded-lg transition-colors" title="Close">
                            <i data-lucide="x" class="w-5 h-5"></i>
                        </button>
                    </div>
                </div>

                <div class="flex flex-col md:flex-row overflow-hidden flex-grow">
                    <!-- LEFT SIDE: Product Info / Inline Editor -->
                    <div class="w-full md:w-[30%] bg-slate-50/50 border-r border-slate-100 p-4 space-y-4 overflow-y-auto custom-scrollbar">
                        <!-- Product Picture -->
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Product Picture</span>
                                <span id="view-image-counter" class="text-[9px] font-bold text-slate-400">0 / 0</span>
                            </div>
                            <div class="relative max-w-[210px] mx-auto">
                                <div id="view-prod-img-container" class="image-zoom-container w-full aspect-square rounded-xl bg-white border border-slate-200 shadow-sm overflow-hidden flex items-center justify-center group" onclick="handleViewProductPictureClick(event)" onmousemove="handleImageZoom(event)" onmouseleave="resetImageZoom()">
                                    <img id="view-prod-img" src="data:," alt="Product" class="w-full h-full object-cover transition-transform">
                                    <div id="view-prod-no-img" class="hidden flex flex-col items-center text-slate-300">
                                        <i data-lucide="image" class="w-10 h-10 mb-2"></i>
                                        <span class="text-[10px] font-medium">No Image</span>
                                    </div>
                                    <div id="view-picture-edit-overlay" class="hidden absolute inset-0 bg-maroon/70 items-center justify-center text-white text-center p-4 cursor-pointer">
                                        <div>
                                            <i data-lucide="images" class="w-7 h-7 mx-auto mb-2 text-gold"></i>
                                            <span class="text-[10px] font-black uppercase tracking-widest">Manage Product Pictures</span>
                                        </div>
                                    </div>
                                </div>
                                <button id="view-image-prev-btn" type="button" onclick="event.stopPropagation(); slideViewProductImage(-1)" class="absolute left-2 top-1/2 -translate-y-1/2 w-8 h-8 bg-black/45 hover:bg-black/70 text-white rounded-full flex items-center justify-center transition-all hidden" title="Previous picture">
                                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                                </button>
                                <button id="view-image-next-btn" type="button" onclick="event.stopPropagation(); slideViewProductImage(1)" class="absolute right-2 top-1/2 -translate-y-1/2 w-8 h-8 bg-black/45 hover:bg-black/70 text-white rounded-full flex items-center justify-center transition-all hidden" title="Next picture">
                                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                                </button>
                            </div>
                            <button id="view-full-gallery-btn" type="button" onclick="openGalleryFromView()" class="w-full mt-2 py-2 border border-slate-200 rounded-lg text-[9px] font-bold text-slate-500 hover:bg-white hover:border-maroon/20 hover:text-maroon transition-colors flex items-center justify-center space-x-1.5">
                                <i data-lucide="gallery-horizontal-end" class="w-3.5 h-3.5"></i>
                                <span>Slide / Full Gallery</span>
                            </button>
                            <p id="view-picture-edit-hint" class="hidden mt-2 text-center text-[9px] font-bold text-maroon uppercase tracking-wide">Click the picture to add, delete, or choose the catalog cover.</p>
                        </div>

                        <!-- Editable Product Fields -->
                        <div id="view-product-edit-form" class="grid grid-cols-1 gap-y-3">
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Product Code</label>
                                    <input id="view-prod-code" data-view-field="product_code" type="text" readonly class="view-product-input w-full px-2.5 py-2 rounded-lg border border-transparent bg-transparent text-xs font-bold text-slate-800 outline-none">
                                </div>
                                <div>
                                    <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Part Number</label>
                                    <input id="view-prod-part" data-view-field="part_number" type="text" readonly class="view-product-input w-full px-2.5 py-2 rounded-lg border border-transparent bg-transparent text-xs font-semibold text-slate-700 outline-none">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Pricelist Code</label>
                                    <input id="view-pricelist-code" data-view-field="pricelist_code" type="text" readonly class="view-product-input w-full px-2.5 py-2 rounded-lg border border-transparent bg-transparent text-xs font-semibold text-slate-700 outline-none">
                                </div>
                                <div>
                                    <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Brand <span class="text-red-500">*</span></label>
                                    <input id="view-prod-cat" data-view-field="category" type="text" required readonly class="view-product-input w-full px-2.5 py-2 rounded-lg border border-transparent bg-transparent text-xs font-semibold text-slate-700 outline-none">
                                </div>
                            </div>
                            <div>
                                <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Description</label>
                                <textarea id="view-prod-desc" data-view-field="description" rows="2" readonly class="view-product-input w-full px-2.5 py-2 rounded-lg border border-transparent bg-transparent text-[11px] text-slate-600 outline-none resize-y"></textarea>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block">Date Added</span>
                                    <p id="view-prod-date" class="px-2.5 py-2 text-xs text-slate-700 font-medium"></p>
                                </div>
                                <div>
                                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block">Status</span>
                                    <span id="view-prod-status-badge" class="mt-1 inline-flex px-2 py-1 rounded-full text-[9px] font-bold"></span>
                                </div>
                            </div>
                            <div>
                                <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Application</label>
                                <textarea id="view-prod-app" data-view-field="application" rows="2" readonly class="view-product-input w-full px-2.5 py-2 rounded-lg border border-transparent bg-transparent text-[11px] text-slate-700 outline-none resize-y"></textarea>
                            </div>
                            <div>
                                <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Specification</label>
                                <textarea id="view-prod-spec" data-view-field="specification" rows="2" readonly class="view-product-input w-full px-2.5 py-2 rounded-lg border border-transparent bg-transparent text-[11px] text-slate-700 outline-none resize-y"></textarea>
                            </div>

                            <div id="view-price-code-section" class="rounded-xl border border-slate-200 bg-white p-3 transition-all">
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Price Code / Selling Price</span>
                                    <button id="view-price-code-manage-btn" type="button" onclick="openViewPriceCodeSetup()" class="hidden px-3 py-1.5 bg-maroon text-white text-[9px] font-black rounded-lg hover:bg-maroon-hover transition-all uppercase tracking-wider">
                                        Manage Pricing
                                    </button>
                                </div>
                                <div id="view-prod-price-codes" class="flex flex-wrap gap-1.5">
                                    <span class="text-[10px] text-slate-400 italic">Loading...</span>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Position</label>
                                    <input id="view-prod-pos" data-view-field="position" type="text" readonly class="view-product-input w-full px-2.5 py-2 rounded-lg border border-transparent bg-transparent text-xs font-semibold text-slate-700 outline-none">
                                </div>
                                <div>
                                    <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Unit</label>
                                    <input id="view-prod-unit" data-view-field="unit" type="text" readonly class="view-product-input w-full px-2.5 py-2 rounded-lg border border-transparent bg-transparent text-xs font-semibold text-slate-700 outline-none">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Onhand Qty</label>
                                    <input id="view-prod-hand" type="number" disabled class="w-full px-2.5 py-2 rounded-lg border border-slate-200 bg-slate-100 text-xs font-black text-maroon cursor-not-allowed">
                                </div>
                                <div>
                                    <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Restock LVL</label>
                                    <input id="view-prod-restock" data-view-field="Re_order_level" type="number" min="0" readonly class="view-product-input w-full px-2.5 py-2 rounded-lg border border-transparent bg-transparent text-xs font-black text-amber-700 outline-none">
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-2 pt-2 border-t border-slate-200">
                                <div>
                                    <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Selling Price</label>
                                    <input id="view-prod-price" data-view-field="selling_price" type="number" min="0" step="0.01" readonly class="view-product-input w-full px-2.5 py-2 rounded-lg border border-transparent bg-transparent text-xs font-black text-slate-800 outline-none">
                                </div>
                                <div>
                                    <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Online Price</label>
                                    <input id="view-prod-online-price" data-view-field="price_online" type="number" min="0" step="0.01" readonly class="view-product-input w-full px-2.5 py-2 rounded-lg border border-transparent bg-transparent text-xs font-black text-emerald-600 outline-none">
                                </div>
                                <div>
                                    <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Cost</label>
                                    <input id="view-prod-cost" type="number" disabled class="w-full px-2.5 py-2 rounded-lg border border-slate-200 bg-slate-100 text-xs font-bold text-slate-500 cursor-not-allowed">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT SIDE: Transaction History + Active SN/PN -->
                    <div class="w-full md:w-[70%] bg-white flex flex-col overflow-hidden">
                        <div class="px-5 py-2 border-b border-slate-100 flex items-center justify-between bg-slate-50/40 flex-shrink-0">
                            <div class="flex items-center gap-2">
                                <button id="product-history-tab-btn" type="button" onclick="switchProductDetailTab('history')" class="product-detail-tab px-4 py-2 rounded-lg bg-maroon text-white text-[12px] font-black uppercase tracking-wider flex items-center gap-2">
                                    <i data-lucide="history" class="w-3.5 h-3.5"></i>
                                    Transaction History
                                </button>
                                <button id="product-active-notes-tab-btn" type="button" onclick="switchProductDetailTab('active-notes')" class="product-detail-tab px-4 py-2 rounded-lg bg-white border border-slate-200 text-slate-600 text-[11px] font-black uppercase tracking-wider flex items-center gap-2 hover:border-maroon/30 hover:text-maroon transition-all">
                                    <i data-lucide="notebook-tabs" class="w-3.5 h-3.5"></i>
                                    Active SN/PN
                                    <span id="active-notes-count" class="px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-500 text-[8px]">0</span>
                                </button>
                            </div>
                        </div>

                        <!-- Transaction History Panel -->
                        <div id="product-history-panel" class="flex flex-col flex-grow overflow-hidden">
                            <div class="flex-grow overflow-auto custom-scrollbar">
                                <table class="w-full text-left border-collapse" style="min-width:1400px;">
                                    <thead class="sticky top-0 z-10">
                                        <tr class="bg-slate-50 text-[11px] font-bold text-slate-500 uppercase tracking-wider border-b border-slate-100">
                                            <th class="py-2.5 px-3">Type</th>
                                            <th class="py-2.5 px-3">Date</th>
                                            <th class="py-2.5 px-3">Trans#</th>
                                            <th class="py-2.5 px-3">Invoice No.</th>
                                            <th class="py-2.5 px-3">Name</th>
                                            <th class="py-2.5 px-3 text-center bg-emerald-50/50 text-emerald-700">In</th>
                                            <th class="py-2.5 px-3 text-center bg-red-50/50 text-red-700">Out</th>
                                            <th class="py-2.5 px-3 text-center" style="color:#8B4513; background-color:rgba(139,69,19,0.06);">Junk</th>
                                            <th class="py-2.5 px-3 text-center font-bold text-slate-800 border-x border-slate-100">Stock</th>
                                            <th class="py-2.5 px-3">OUM</th>
                                            <th class="py-2.5 px-3">Price</th>
                                            <th class="py-2.5 px-3">Online Price</th>
                                            <th class="py-2.5 px-3">Cost</th>
                                            <th class="py-2.5 px-3">Remarks</th>
                                        </tr>
                                        <tr class="bg-slate-100/80 border-b border-slate-200">
                                            <th class="p-1 px-3"><input type="text" data-ledger-col="type" placeholder="Search..." class="ledger-search-input w-full h-7 px-2 py-1 text-[10px] font-semibold text-slate-700 bg-white border border-slate-300 rounded-md shadow-sm placeholder:text-slate-400 focus:border-maroon focus:ring-1 focus:ring-maroon/20 outline-none"></th>
                                            <th class="p-1 px-3"><input type="text" data-ledger-col="date" placeholder="Search..." class="ledger-search-input w-full h-7 px-2 py-1 text-[10px] font-semibold text-slate-700 bg-white border border-slate-300 rounded-md shadow-sm placeholder:text-slate-400 focus:border-maroon focus:ring-1 focus:ring-maroon/20 outline-none"></th>
                                            <th class="p-1 px-3"><input type="text" data-ledger-col="transNum" placeholder="Search..." class="ledger-search-input w-full h-7 px-2 py-1 text-[10px] font-semibold text-slate-700 bg-white border border-slate-300 rounded-md shadow-sm placeholder:text-slate-400 focus:border-maroon focus:ring-1 focus:ring-maroon/20 outline-none"></th>
                                            <th class="p-1 px-3"><input type="text" data-ledger-col="refNum" placeholder="Invoice..." class="ledger-search-input w-full h-7 px-2 py-1 text-[10px] font-semibold text-slate-700 bg-white border border-slate-300 rounded-md shadow-sm placeholder:text-slate-400 focus:border-maroon focus:ring-1 focus:ring-maroon/20 outline-none"></th>
                                            <th class="p-1 px-3"><input type="text" data-ledger-col="name" placeholder="Search..." class="ledger-search-input w-full h-7 px-2 py-1 text-[10px] font-semibold text-slate-700 bg-white border border-slate-300 rounded-md shadow-sm placeholder:text-slate-400 focus:border-maroon focus:ring-1 focus:ring-maroon/20 outline-none"></th>
                                            <th class="p-1 px-3"><input type="text" data-ledger-col="in" placeholder="In" class="ledger-search-input w-full h-7 px-2 py-1 text-[10px] font-semibold text-slate-700 bg-white border border-slate-300 rounded-md shadow-sm placeholder:text-slate-400 focus:border-maroon focus:ring-1 focus:ring-maroon/20 outline-none text-center"></th>
                                            <th class="p-1 px-3"><input type="text" data-ledger-col="out" placeholder="Out" class="ledger-search-input w-full h-7 px-2 py-1 text-[10px] font-semibold text-slate-700 bg-white border border-slate-300 rounded-md shadow-sm placeholder:text-slate-400 focus:border-maroon focus:ring-1 focus:ring-maroon/20 outline-none text-center"></th>
                                            <th class="p-1 px-3"><input type="text" data-ledger-col="junk" placeholder="Junk" class="ledger-search-input w-full h-7 px-2 py-1 text-[10px] font-semibold bg-white border border-slate-300 rounded-md shadow-sm placeholder:text-slate-400 focus:border-maroon focus:ring-1 focus:ring-maroon/20 outline-none text-center" style="color:#8B4513;"></th>
                                            <th class="p-1 px-3"><input type="text" data-ledger-col="stock" placeholder="Stock" class="ledger-search-input w-full h-7 px-2 py-1 text-[10px] font-semibold text-slate-700 bg-white border border-slate-300 rounded-md shadow-sm placeholder:text-slate-400 focus:border-maroon focus:ring-1 focus:ring-maroon/20 outline-none text-center"></th>
                                            <th class="p-1 px-3"><input type="text" data-ledger-col="oum" placeholder="OUM" class="ledger-search-input w-full h-7 px-2 py-1 text-[10px] font-semibold text-slate-700 bg-white border border-slate-300 rounded-md shadow-sm placeholder:text-slate-400 focus:border-maroon focus:ring-1 focus:ring-maroon/20 outline-none"></th>
                                            <th class="p-1 px-3"><input type="text" data-ledger-col="sell" placeholder="Price" class="ledger-search-input w-full h-7 px-2 py-1 text-[10px] font-semibold text-slate-700 bg-white border border-slate-300 rounded-md shadow-sm placeholder:text-slate-400 focus:border-maroon focus:ring-1 focus:ring-maroon/20 outline-none"></th>
                                            <th class="p-1 px-3"><input type="text" data-ledger-col="online" placeholder="Online" class="ledger-search-input w-full h-7 px-2 py-1 text-[10px] font-semibold text-slate-700 bg-white border border-slate-300 rounded-md shadow-sm placeholder:text-slate-400 focus:border-maroon focus:ring-1 focus:ring-maroon/20 outline-none"></th>
                                            <th class="p-1 px-3"><input type="text" data-ledger-col="cost" placeholder="Cost" class="ledger-search-input w-full h-7 px-2 py-1 text-[10px] font-semibold text-slate-700 bg-white border border-slate-300 rounded-md shadow-sm placeholder:text-slate-400 focus:border-maroon focus:ring-1 focus:ring-maroon/20 outline-none"></th>
                                            <th class="p-1 px-3"><input type="text" data-ledger-col="remarks" placeholder="Remarks" class="ledger-search-input w-full h-7 px-2 py-1 text-[10px] font-semibold text-slate-700 bg-white border border-slate-300 rounded-md shadow-sm placeholder:text-slate-400 focus:border-maroon focus:ring-1 focus:ring-maroon/20 outline-none"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="ledger-tbody" class="divide-y divide-slate-50 text-[12px]"></tbody>
                                </table>
                                <div id="ledger-empty-state" class="hidden flex flex-col items-center justify-center py-16 text-slate-300">
                                    <i data-lucide="database-zap" class="w-10 h-10 mb-2 opacity-20"></i>
                                    <p class="text-xs font-medium">No history found.</p>
                                </div>
                            </div>
                            <div class="px-5 py-2.5 border-t border-slate-100 bg-slate-50/30 flex items-center justify-between flex-shrink-0">
                                <p class="text-[10px] text-slate-400 italic">Complete transaction history since registration.</p>
                                <div class="flex items-center space-x-3">
                                    <span class="text-[10px] font-bold text-slate-500 uppercase">In: <span id="ledger-total-in" class="text-emerald-700">0</span></span>
                                    <span class="text-[10px] font-bold text-slate-500 uppercase">Out: <span id="ledger-total-out" class="text-red-700">0</span></span>
                                </div>
                            </div>
                        </div>

                        <!-- Active SN/PN Panel -->
                        <div id="product-active-notes-panel" class="hidden flex flex-col flex-grow overflow-hidden">
                            <div class="px-5 py-2 border-b border-slate-100 bg-amber-50/40">
                                <p class="text-[9px] text-slate-500"><strong>Active</strong> = Purchase Notes in Open / Partial / Surplus and Sales Notes in Open / Partial.</p>
                            </div>
                            <div class="flex-grow overflow-auto custom-scrollbar">
                                <table class="w-full text-left border-collapse" style="min-width:980px;">
                                    <thead class="sticky top-0 z-10">
                                        <tr class="bg-slate-50 text-[9px] font-bold text-slate-500 uppercase tracking-wider border-b border-slate-100">
                                            <th class="py-2.5 px-3">S.N / PN</th>
                                            <th class="py-2.5 px-3">Date</th>
                                            <th class="py-2.5 px-3 text-center bg-emerald-50/50 text-emerald-700">In (Purchase Note)</th>
                                            <th class="py-2.5 px-3 text-center bg-red-50/50 text-red-700">Out (Sales Note)</th>
                                            <th class="py-2.5 px-3">Name</th>
                                            <th class="py-2.5 px-3">Price (Sales Note)</th>
                                            <th class="py-2.5 px-3">Cost (Purchase Note)</th>
                                        </tr>
                                        <tr class="bg-slate-100/80 border-b border-slate-200">
                                            <th class="p-1 px-3"><input type="text" data-active-note-col="number" placeholder="Search SN/PN..." class="active-note-search-input w-full h-7 px-2 py-1 text-[9px] font-semibold text-slate-700 bg-white border border-slate-300 rounded-md shadow-sm placeholder:text-slate-400 focus:border-maroon focus:ring-1 focus:ring-maroon/20 outline-none"></th>
                                            <th class="p-1 px-3"><input type="text" data-active-note-col="date" placeholder="Search date..." class="active-note-search-input w-full h-7 px-2 py-1 text-[9px] font-semibold text-slate-700 bg-white border border-slate-300 rounded-md shadow-sm placeholder:text-slate-400 focus:border-maroon focus:ring-1 focus:ring-maroon/20 outline-none"></th>
                                            <th class="p-1 px-3"><input type="text" data-active-note-col="in" placeholder="Search in..." class="active-note-search-input w-full h-7 px-2 py-1 text-[9px] font-semibold text-slate-700 bg-white border border-slate-300 rounded-md shadow-sm placeholder:text-slate-400 focus:border-maroon focus:ring-1 focus:ring-maroon/20 outline-none text-center"></th>
                                            <th class="p-1 px-3"><input type="text" data-active-note-col="out" placeholder="Search out..." class="active-note-search-input w-full h-7 px-2 py-1 text-[9px] font-semibold text-slate-700 bg-white border border-slate-300 rounded-md shadow-sm placeholder:text-slate-400 focus:border-maroon focus:ring-1 focus:ring-maroon/20 outline-none text-center"></th>
                                            <th class="p-1 px-3"><input type="text" data-active-note-col="name" placeholder="Search name..." class="active-note-search-input w-full h-7 px-2 py-1 text-[9px] font-semibold text-slate-700 bg-white border border-slate-300 rounded-md shadow-sm placeholder:text-slate-400 focus:border-maroon focus:ring-1 focus:ring-maroon/20 outline-none"></th>
                                            <th class="p-1 px-3"><input type="text" data-active-note-col="price" placeholder="Search price..." class="active-note-search-input w-full h-7 px-2 py-1 text-[9px] font-semibold text-slate-700 bg-white border border-slate-300 rounded-md shadow-sm placeholder:text-slate-400 focus:border-maroon focus:ring-1 focus:ring-maroon/20 outline-none"></th>
                                            <th class="p-1 px-3"><input type="text" data-active-note-col="cost" placeholder="Search cost..." class="active-note-search-input w-full h-7 px-2 py-1 text-[9px] font-semibold text-slate-700 bg-white border border-slate-300 rounded-md shadow-sm placeholder:text-slate-400 focus:border-maroon focus:ring-1 focus:ring-maroon/20 outline-none"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="active-notes-tbody" class="divide-y divide-slate-50 text-[10px]"></tbody>
                                </table>
                                <div id="active-notes-empty-state" class="hidden flex-col items-center justify-center py-16 text-slate-300 text-center">
                                    <i data-lucide="notebook" class="w-10 h-10 mb-2 opacity-20 mx-auto"></i>
                                    <p class="text-xs font-medium">No active Sales Note or Purchase Note for this product.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="bg-slate-50 px-6 py-3 flex items-center justify-between border-t border-slate-100 flex-shrink-0">
                    <p id="view-edit-dirty-message" class="hidden text-[10px] font-bold text-amber-700">Unsaved product changes detected.</p>
                    <div class="ml-auto flex items-center gap-2">
                        <button onclick="closeProductViewModal()" class="px-6 py-2 bg-slate-800 hover:bg-slate-900 text-white text-[10px] font-bold rounded-lg shadow-md transition-all">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. EDIT PRODUCT MODAL -->
    <div id="edit-product-modal" class="fixed inset-0 z-[900] overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div onclick="toggleModal('edit-product-modal', false)" class="fixed inset-0 bg-maroon-900/60 transition-opacity backdrop-blur-sm" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="modal-animate-in inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full border-2 border-goldlining-500/20">
                
                <div class="bg-maroon-gradient px-6 py-4 flex items-center justify-between border-b border-goldlining-500">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="edit" class="w-5 h-5 text-gold"></i>
                        <h3 class="text-sm font-bold text-white uppercase tracking-wider">Edit Product Ledger</h3>
                    </div>
                    <button onclick="toggleModal('edit-product-modal', false)" class="text-slate-300 hover:text-white transition-colors">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <form id="edit-product-form" class="max-h-[80vh] overflow-y-auto custom-scrollbar relative">
                    <input type="hidden" name="id" id="edit-product-id">
                    
                    <!-- Absolute Tags (Top Right) -->
                    <div class="absolute top-8 right-8 flex items-center gap-2 z-10">
                        <span class="px-3 py-1.5 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-full border border-slate-200 flex items-center gap-1.5 shadow-sm">
                            <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-400"></i>
                            <span id="edit-date-display"></span>
                            <input type="hidden" name="date_added" id="edit-date-added">
                        </span>
                        <span id="edit-status-tag" class="px-3 py-1.5 text-[10px] font-bold rounded-full border flex items-center gap-1.5 shadow-sm">
                            <span class="w-1.5 h-1.5 rounded-full animate-pulse" id="edit-status-pulse"></span>
                            <span id="edit-status-text"></span>
                            <input type="hidden" name="status" id="edit-status">
                        </span>
                    </div>

                    <div class="bg-white px-8 py-8 space-y-8">
                        
                        <!-- Top Row: Picture Section -->
                        <div class="space-y-4">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest">Product Pictures (Max 7)</label>
                            
                            <div class="flex gap-4">
                                <!-- Upload Trigger -->
                                <label for="edit-prod-image-input" class="cursor-pointer flex flex-col items-center justify-center w-32 h-48 border-2 border-dashed border-slate-200 rounded-2xl hover:bg-slate-50 hover:border-maroon/30 transition-all group shrink-0">
                                    <div class="w-10 h-10 bg-slate-50 rounded-full flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                                        <i data-lucide="upload-cloud" class="w-5 h-5 text-slate-400 group-hover:text-maroon"></i>
                                    </div>
                                    <span class="text-[10px] text-slate-400 font-bold group-hover:text-maroon">CHANGE PICTURES</span>
                                    <input type="file" id="edit-prod-image-input" multiple class="hidden" accept="image/*">
                                </label>

                                <!-- Multi-Image Grid/Carousel Preview -->
                                <div id="edit-image-preview-container" class="flex-grow grid grid-cols-4 gap-3 h-48 overflow-y-auto p-2 border border-slate-100 rounded-2xl bg-slate-50 custom-scrollbar">
                                    <!-- Dynamic Previews -->
                                </div>
                            </div>
                            <p class="text-[10px] text-slate-400 font-medium italic">Update images as needed. Maximum 7 images allowed.</p>
                        </div>

                        <!-- Main Form Fields -->
                        <div class="space-y-6">
                            <!-- Row 1: Code & Part Number -->
                            <div class="grid grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Product Code <span class="text-red-500">*</span></label>
                                    <input type="text" name="product_code" id="edit-product-code" required placeholder="e.g. PRD-0001" class="block w-full px-4 py-3 border border-slate-200 rounded-xl bg-white text-sm focus:border-maroon focus:ring-4 focus:ring-maroon/5 outline-none transition-all">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Part Number <span class="text-[9px] font-semibold text-slate-400 normal-case tracking-normal ml-1">(Optional)</span></label>
                                    <input type="text" name="part_number" id="edit-part-number" placeholder="e.g. PN-9920" class="block w-full px-4 py-3 border border-slate-200 rounded-xl bg-white text-sm focus:border-maroon focus:ring-4 focus:ring-maroon/5 outline-none transition-all">
                                </div>
                            </div>

                            <!-- Pricelist Code -->
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Pricelist Code <span class="text-[9px] font-semibold text-slate-400 normal-case tracking-normal ml-1">(Optional)</span></label>
                                <input type="text" name="pricelist_code" id="edit-pricelist-code" maxlength="255" placeholder="Optional pricelist code" class="block w-full px-4 py-3 border border-slate-200 rounded-xl bg-white text-slate-800 focus:border-maroon focus:ring-4 focus:ring-maroon/5 text-sm outline-none transition-all">
                            </div>

                            <!-- Row 2: Description -->
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Description</label>
                                <textarea name="description" id="edit-description" placeholder="Enter detailed product description..." class="block w-full px-4 py-3 border border-slate-200 rounded-xl bg-white text-sm focus:border-maroon focus:ring-4 focus:ring-maroon/5 outline-none transition-all resize-none" rows="3"></textarea>
                            </div>

                            <!-- Row 3: Specification -->
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Specification</label>
                                <textarea name="specification" id="edit-specification" placeholder="e.g. 12V, LH, 1.5L" class="block w-full px-4 py-3 border border-slate-200 rounded-xl bg-white text-sm focus:border-maroon focus:ring-4 focus:ring-maroon/5 outline-none transition-all resize-none" rows="3"></textarea>
                            </div>

                            <!-- Row 4: Application (Multi-Entry) -->
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest">Application</label>
                                    <button type="button" onclick="window.addApplicationEntry('edit')" class="flex items-center gap-1 px-2.5 py-1 bg-maroon/10 text-maroon text-[10px] font-bold rounded-lg hover:bg-maroon/20 transition-colors uppercase tracking-wider">
                                        <i data-lucide="plus" class="w-3 h-3"></i> Add More
                                    </button>
                                </div>
                                <input type="hidden" name="application" id="edit-application-hidden">
                                <div id="edit-application-entries" class="space-y-3">
                                    <!-- Entries will be populated by JS when editing -->
                                </div>
                            </div>

                            <!-- Row 4b: Price Code (Multi-Entry) -->
                            <div class="mt-6">
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest">Price Code</label>
                                    <button type="button" onclick="window.addPriceCodeEntry('edit')" class="flex items-center gap-1 px-2.5 py-1 bg-maroon/10 text-maroon text-[10px] font-bold rounded-lg hover:bg-maroon/20 transition-colors uppercase tracking-wider">
                                        <i data-lucide="plus" class="w-3 h-3"></i> Add Price Code
                                    </button>
                                </div>
                                <input type="hidden" name="price_codes" id="edit-price-codes-hidden">
                                <div id="edit-price-codes-entries" class="space-y-3">
                                    <!-- Entries will be populated by JS when editing -->
                                </div>
                            </div>

                            <!-- Row 4: Position -->
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Position</label>
                                <input type="text" name="position" id="edit-position" placeholder="e.g. Front, Rear, Left, Right" class="block w-full px-4 py-3 border border-slate-200 rounded-xl bg-white text-sm focus:border-maroon focus:ring-4 focus:ring-maroon/5 outline-none transition-all">
                            </div>

                            <!-- Row 5: Unit -->
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Unit</label>
                                <input type="text" name="unit" id="edit-unit" placeholder="e.g. PCS, SET, PC" class="block w-full px-4 py-3 border border-slate-200 rounded-xl bg-white text-sm focus:border-maroon focus:ring-4 focus:ring-maroon/5 outline-none transition-all">
                            </div>

                            <!-- Brand, Selling Price & Online Price -->
                            <div class="grid grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Brand <span class="text-red-500">*</span></label>
                                    <input type="text" name="category" id="edit-category" required placeholder="e.g. Engine Parts" class="block w-full px-4 py-3 border border-slate-200 rounded-xl bg-white text-sm focus:border-maroon focus:ring-4 focus:ring-maroon/5 outline-none transition-all">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Selling Price <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400 font-bold text-sm">₱</span>
                                        <input type="number" name="selling_price" id="edit-selling-price" step="0.01" required placeholder="0.00" class="block w-full pl-9 pr-4 py-3 border border-slate-200 rounded-xl bg-white text-sm font-bold text-slate-700 focus:border-maroon focus:ring-4 focus:ring-maroon/5 outline-none transition-all">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Online Price</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400 font-bold text-sm">₱</span>
                                        <input type="number" name="price_online" id="edit-price-online" step="0.01" placeholder="0.00" class="block w-full pl-9 pr-4 py-3 border border-slate-200 rounded-xl bg-white text-sm font-bold text-slate-700 focus:border-maroon focus:ring-4 focus:ring-maroon/5 outline-none transition-all">
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">On Hand (QTY) <span class="text-red-500">*</span></label>
                                    <input type="number" name="on_hand" id="edit-on-hand" required placeholder="0" class="block w-full px-4 py-3 border border-slate-200 rounded-xl bg-white text-sm focus:border-maroon focus:ring-4 focus:ring-maroon/5 outline-none transition-all">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Restock LVL</label>
                                    <input type="number" name="Re_order_level" id="edit-restock-level" min="0" step="1" placeholder="0" class="block w-full px-4 py-3 border border-slate-200 rounded-xl bg-white text-sm focus:border-maroon focus:ring-4 focus:ring-maroon/5 outline-none transition-all">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Cost Price</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400 font-bold text-sm">₱</span>
                                        <input type="number" name="cost" id="edit-cost" step="0.01" placeholder="0.00" class="block w-full pl-9 pr-4 py-3 border border-slate-200 rounded-xl bg-white text-sm font-bold text-slate-700 focus:border-maroon focus:ring-4 focus:ring-maroon/5 outline-none transition-all">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-50 px-8 py-4 flex items-center justify-end space-x-3 border-t border-slate-100">
                        <button type="button" onclick="toggleModal('edit-product-modal', false)" class="px-6 py-2.5 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 text-[10px] font-bold rounded-xl transition-all tracking-widest uppercase">
                            Cancel
                        </button>
                        <button type="button" onclick="if(document.getElementById('edit-product-form').reportValidity()) toggleModal('confirm-update-modal', true)" class="px-8 py-2.5 bg-maroon hover:bg-maroon-hover text-white text-[10px] font-bold rounded-xl shadow-md transition-all tracking-widest uppercase">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 4. SEARCH SPECIFIC MODAL -->
    <div id="search-specific-modal" class="fixed inset-0 z-[900] overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div onclick="toggleModal('search-specific-modal', false)" class="fixed inset-0 bg-maroon-900/60 transition-opacity backdrop-blur-sm" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="modal-animate-in inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full border-2 border-goldlining-500/20">
                <div class="bg-maroon-gradient px-6 py-4 flex items-center justify-between border-b border-goldlining-500">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="search" class="w-5 h-5 text-gold"></i>
                        <h3 class="text-sm font-bold text-white uppercase tracking-wider">Search Parameters</h3>
                    </div>
                    <button onclick="toggleModal('search-specific-modal', false)" class="text-slate-300 hover:text-white transition-colors">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <input type="text" id="search-product-code" placeholder="Search by Product Code..." class="block w-full px-3 py-2 border border-slate-200 rounded-xl text-sm outline-none">
                    <input type="text" id="search-part-number" placeholder="Search by Part Number..." class="block w-full px-3 py-2 border border-slate-200 rounded-xl text-sm outline-none">
                    <input type="text" id="search-category" placeholder="Search by Category..." class="block w-full px-3 py-2 border border-slate-200 rounded-xl text-sm outline-none">
                    <input type="date" id="search-date" class="block w-full px-3 py-2 border border-slate-200 rounded-xl text-sm outline-none">
                </div>
                <div class="bg-slate-50 px-6 py-4 flex justify-center border-t border-slate-100">
                    <button onclick="applySpecificSearch()" class="px-8 py-2 bg-maroon text-white text-xs font-bold rounded-xl shadow-md">Search</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. SEARCH SPECIFIC MODAL -->
    <!-- ... (Assuming this exists or just adding the gallery modal) ... -->
    
    <!-- PRODUCT IMAGE GALLERY MODAL -->
    <div id="product-gallery-modal" class="fixed inset-0 z-[900] overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4 text-center sm:p-0">
            <div onclick="toggleModal('product-gallery-modal', false)" class="fixed inset-0 bg-black/90 transition-opacity backdrop-blur-md" aria-hidden="true"></div>
            
            <div class="relative inline-block align-middle bg-transparent overflow-hidden transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                <!-- Close Button -->
                <button onclick="toggleModal('product-gallery-modal', false)" class="absolute top-4 right-4 z-[910] text-white/70 hover:text-white transition-colors p-2 bg-white/10 rounded-full hover:bg-white/20">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>

                <!-- Gallery Container -->
                <div class="relative group">
                    <div id="gallery-image-container" class="flex items-center justify-center min-h-[50vh] md:min-h-[70vh]">
                        <!-- Main Image -->
                        <img id="gallery-main-image" src="data:," alt="Product Gallery" class="max-w-[90vw] max-h-[85vh] w-auto h-auto object-contain rounded-lg shadow-2xl">
                    </div>

                    <!-- Navigation Arrows -->
                    <button id="gallery-prev-btn" onclick="prevGalleryImage()" class="absolute left-4 top-1/2 -translate-y-1/2 p-3 bg-white/10 hover:bg-white/20 text-white rounded-full transition-all opacity-0 group-hover:opacity-100 disabled:opacity-0">
                        <i data-lucide="chevron-left" class="w-8 h-8"></i>
                    </button>
                    <button id="gallery-next-btn" onclick="nextGalleryImage()" class="absolute right-4 top-1/2 -translate-y-1/2 p-3 bg-white/10 hover:bg-white/20 text-white rounded-full transition-all opacity-0 group-hover:opacity-100 disabled:opacity-0">
                        <i data-lucide="chevron-right" class="w-8 h-8"></i>
                    </button>
                </div>

                <!-- Zoom Control Bar -->
                <div class="mt-3 flex items-center justify-center gap-4 px-4">
                    <button id="gallery-zoom-out-btn" onclick="galleryZoomOut()" class="p-1.5 bg-white/10 hover:bg-white/20 text-white rounded-full transition-colors" title="Zoom Out">
                        <i data-lucide="zoom-out" class="w-4 h-4"></i>
                    </button>
                    <div class="flex items-center gap-3 text-white/80 text-xs">
                        <span class="text-white/50 text-[10px] uppercase tracking-wider font-bold">Size</span>
                        <input type="range" id="gallery-zoom-slider" min="50" max="300" value="100" step="10" oninput="galleryZoomChange(this.value)" class="w-32 h-1.5 bg-white/20 rounded-full appearance-none cursor-pointer accent-white" style="height:6px;">
                        <span id="gallery-zoom-label" class="text-white/70 font-bold text-xs w-8 text-right">100%</span>
                    </div>
                    <button id="gallery-zoom-in-btn" onclick="galleryZoomIn()" class="p-1.5 bg-white/10 hover:bg-white/20 text-white rounded-full transition-colors" title="Zoom In">
                        <i data-lucide="zoom-in" class="w-4 h-4"></i>
                    </button>
                    <button onclick="galleryZoomReset()" class="p-1.5 bg-white/10 hover:bg-white/20 text-white/60 hover:text-white rounded-full transition-colors" title="Reset Zoom">
                        <i data-lucide="maximize-2" class="w-4 h-4"></i>
                    </button>
                </div>

                <!-- Thumbnails/Indicators -->
                <div id="gallery-thumbnails" class="mt-3 flex justify-center gap-3 overflow-x-auto py-2 px-4 custom-scrollbar">
                    <!-- Thumbnails go here -->
                </div>
            </div>
        </div>
    </div>

    <!-- PRODUCT VIEW: PRICING CODE SET-UP MODAL -->
    <div id="view-price-code-modal" class="fixed inset-0 z-[1150] overflow-y-auto hidden" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div onclick="closeViewPriceCodeSetup()" class="fixed inset-0 bg-maroon-900/70 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl overflow-hidden border border-slate-100">
                <div class="bg-maroon-gradient px-6 py-4 flex items-center justify-between border-b border-goldlining-500">
                    <div>
                        <h3 class="text-sm font-black text-white uppercase tracking-wider">Pricing Code Set-Up</h3>
                        <p class="text-[10px] text-white/70 mt-0.5">Add or delete pricing codes and their equivalent selling price for this product.</p>
                    </div>
                    <button type="button" onclick="closeViewPriceCodeSetup()" class="text-white/70 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
                </div>
                <div class="p-6 max-h-[65vh] overflow-y-auto custom-scrollbar">
                    <div id="view-price-code-entries" class="space-y-3"></div>
                    <button type="button" onclick="addViewPriceCodeEntry()" class="mt-4 w-full py-2.5 border-2 border-dashed border-slate-200 hover:border-maroon/30 hover:bg-maroon/5 text-slate-500 hover:text-maroon text-[10px] font-black rounded-xl uppercase tracking-wider transition-all flex items-center justify-center gap-2">
                        <i data-lucide="plus" class="w-4 h-4"></i> Add More
                    </button>
                </div>
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-2">
                    <button type="button" onclick="closeViewPriceCodeSetup()" class="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 text-[10px] font-bold rounded-xl">Cancel</button>
                    <button id="view-price-code-save-btn" type="button" onclick="saveViewPriceCodes()" class="px-6 py-2.5 bg-maroon hover:bg-maroon-hover text-white text-[10px] font-black rounded-xl shadow-md uppercase tracking-wider">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- PRODUCT VIEW: PRICING SUCCESS -->
    <div id="view-price-code-success-modal" class="fixed inset-0 z-[1200] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl">
                <div class="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4 text-emerald-600"><i data-lucide="badge-check" class="w-8 h-8"></i></div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Pricing Saved</h3>
                <p class="text-sm text-slate-500 mb-6">The product pricing codes were updated successfully.</p>
                <button type="button" onclick="confirmViewPriceCodeSuccess()" class="px-8 py-2.5 bg-maroon text-white text-xs font-bold rounded-xl shadow-md">OK</button>
            </div>
        </div>
    </div>

    <!-- PRODUCT VIEW: PICTURE MANAGER -->
    <div id="view-picture-manager-modal" class="fixed inset-0 z-[1150] overflow-y-auto hidden" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div onclick="cancelViewPictureManager()" class="fixed inset-0 bg-maroon-900/70 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl overflow-hidden border border-slate-100">
                <div class="bg-maroon-gradient px-6 py-4 flex items-center justify-between border-b border-goldlining-500">
                    <div>
                        <h3 class="text-sm font-black text-white uppercase tracking-wider">Product Picture Manager</h3>
                        <p class="text-[10px] text-white/70 mt-0.5">Maximum 7 pictures. Check the picture that should appear as the catalog cover.</p>
                    </div>
                    <button type="button" onclick="cancelViewPictureManager()" class="text-white/70 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
                </div>
                <div class="p-6">
                    <label for="view-edit-image-input" class="cursor-pointer w-full min-h-[100px] border-2 border-dashed border-slate-200 rounded-xl hover:border-maroon/30 hover:bg-maroon/5 transition-all flex flex-col items-center justify-center text-slate-400 mb-5">
                        <i data-lucide="image-plus" class="w-6 h-6 mb-2 text-maroon"></i>
                        <span class="text-[10px] font-black uppercase tracking-wider">Add Product Picture</span>
                        <span class="text-[9px] mt-1">JPG / PNG / WEBP</span>
                        <input type="file" id="view-edit-image-input" multiple class="hidden" accept="image/*">
                    </label>
                    <div id="view-edit-image-grid" class="flex flex-wrap gap-3 min-h-[90px]"></div>
                </div>
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-2">
                    <button type="button" onclick="cancelViewPictureManager()" class="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 text-[10px] font-bold rounded-xl">Cancel</button>
                    <button type="button" onclick="applyViewPictureManager()" class="px-6 py-2.5 bg-maroon hover:bg-maroon-hover text-white text-[10px] font-black rounded-xl shadow-md uppercase tracking-wider">Done</button>
                </div>
            </div>
        </div>
    </div>

    <!-- PRODUCT VIEW: SAVE SUCCESS -->
    <div id="view-edit-success-modal" class="fixed inset-0 z-[1200] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl">
                <div class="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4 text-emerald-600"><i data-lucide="check-circle-2" class="w-8 h-8"></i></div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Changes Saved</h3>
                <p id="view-edit-success-text" class="text-sm text-slate-500 mb-6">The product changes were saved successfully.</p>
                <button type="button" onclick="closeViewEditSuccess()" class="px-8 py-2.5 bg-maroon text-white text-xs font-bold rounded-xl shadow-md">OK</button>
            </div>
        </div>
    </div>

    <!-- 5. FILTER MODAL -->
    <div id="filter-modal" class="fixed inset-0 z-[900] overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div onclick="toggleModal('filter-modal', false)" class="fixed inset-0 bg-maroon-900/60 transition-opacity backdrop-blur-sm" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="modal-animate-in inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full border-2 border-goldlining-500/20">
                <div class="bg-maroon-gradient px-6 py-4 flex items-center justify-between border-b border-goldlining-500">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="sliders-horizontal" class="w-5 h-5 text-gold"></i>
                        <h3 class="text-sm font-bold text-white uppercase tracking-wider">Filter Ledger</h3>
                    </div>
                    <button onclick="toggleModal('filter-modal', false)" class="text-slate-300 hover:text-white transition-colors">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Category</label>
                        <input type="text" id="filter-category" placeholder="Filter by category..." class="block w-full px-3 py-2 border border-slate-200 rounded-xl text-sm outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Status</label>
                        <select id="filter-status" class="block w-full px-3 py-2 border border-slate-200 rounded-xl text-sm outline-none">
                            <option value="all">All Statuses</option>
                            <option value="Newly">Newly Added</option>
                            <option value="Old">Old Items</option>
                        </select>
                    </div>
                </div>
                <div class="bg-slate-50 px-6 py-4 flex justify-between items-center border-t border-slate-100">
                    <button onclick="resetFilters()" class="text-xs text-maroon font-bold underline">Reset All</button>
                    <button onclick="applyFilters()" class="px-6 py-2 bg-maroon text-white text-xs font-bold rounded-xl shadow-md">Apply Filters</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 6. REORDER LEVEL MODAL -->
    <div id="reorder-level-modal" class="fixed inset-0 z-[900] overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div onclick="toggleModal('reorder-level-modal', false)" class="fixed inset-0 bg-maroon-900/60 transition-opacity backdrop-blur-sm" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="modal-animate-in inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full border-2 border-goldlining-500/20">
                <div class="bg-maroon-gradient px-6 py-4 flex items-center justify-between border-b border-goldlining-500">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="alert-triangle" class="w-5 h-5 text-gold"></i>
                        <h3 class="text-sm font-bold text-white uppercase tracking-wider">Configure Reorder Levels</h3>
                    </div>
                    <button onclick="toggleModal('reorder-level-modal', false)" class="text-slate-300 hover:text-white transition-colors">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Low Stock Threshold Count</label>
                        <input id="reorder-threshold-input" type="number" min="0" placeholder="Enter threshold e.g. 20" class="block w-full px-3 py-2 border border-slate-200 rounded-xl text-sm outline-none">
                        <p class="text-[10px] text-slate-400 mt-1 italic">Products with QTY below this value will be flagged as Low Stock.</p>
                    </div>
                </div>
                <div class="bg-slate-50 px-6 py-4 flex justify-center border-t border-slate-100">
                    <button onclick="openConfirmReorderModal()" class="px-8 py-2 bg-maroon text-white text-xs font-bold rounded-xl shadow-md">Save Threshold</button>
                </div>
            </div>
        </div>
    </div>

    <div id="confirm-reorder-modal" class="fixed inset-0 z-[950] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl">
                <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4 text-amber-600"><i data-lucide="help-circle" class="w-8 h-8"></i></div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Confirm Reorder Level</h3>
                <p class="text-sm text-slate-500 mb-6">Apply this threshold to all currently checked products?</p>
                <div class="flex gap-3 justify-center">
                    <button onclick="toggleModal('confirm-reorder-modal', false)" class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-bold rounded-xl">Cancel</button>
                    <button id="btn-confirm-reorder" class="px-6 py-2 bg-maroon text-white text-xs font-bold rounded-xl shadow-md">Yes, Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- CONFIRM CHECKBOX ACTION -->
    <div id="confirm-checkbox-modal" class="fixed inset-0 z-[950] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div onclick="toggleModal('confirm-checkbox-modal', false); window.cancelCheckboxAction()" class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl">
                <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4 text-amber-600"><i data-lucide="help-circle" class="w-8 h-8"></i></div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Confirm Action</h3>
                <p id="confirm-checkbox-msg" class="text-sm text-slate-500 mb-6">Are you sure you want to perform this action?</p>
                <div class="flex gap-3 justify-center">
                    <button onclick="toggleModal('confirm-checkbox-modal', false); window.cancelCheckboxAction()" class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-bold rounded-xl">Cancel</button>
                    <button onclick="window.confirmCheckboxAction()" class="px-6 py-2 bg-maroon text-white text-xs font-bold rounded-xl shadow-md">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== UTILITY FEEDBACK MODALS ==================== -->
    
    <!-- CONFIRMATION MODALS -->
    <div id="confirm-add-modal" class="fixed inset-0 z-[950] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl">
                <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4 text-amber-600"><i data-lucide="help-circle" class="w-8 h-8"></i></div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Confirm Action</h3>
                <p class="text-sm text-slate-500 mb-6">Are you sure you want to proceed with adding this product to the master list?</p>
                <div class="flex gap-3 justify-center">
                    <button onclick="toggleModal('confirm-add-modal', false)" class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-bold rounded-xl">No</button>
                    <button onclick="confirmAddProduct()" class="px-6 py-2 bg-maroon text-white text-xs font-bold rounded-xl shadow-md">Yes, Add</button>
                </div>
            </div>
        </div>
    </div>

    <!-- BULK DELETE CONFIRMATION -->
    <div id="confirm-bulk-delete-modal" class="fixed inset-0 z-[950] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl border-2 border-red-500/10">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4 text-red-600"><i data-lucide="trash-2" class="w-8 h-8"></i></div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Bulk Delete Products?</h3>
                <p class="text-sm text-slate-500 mb-6">You are about to delete <span id="bulk-selected-count-msg" class="font-bold">0</span> products. This action is irreversible. Are you sure?</p>
                <div class="flex gap-3 justify-center">
                    <button onclick="toggleModal('confirm-bulk-delete-modal', false)" class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-bold rounded-xl">Cancel</button>
                    <button onclick="confirmBulkDelete()" class="px-6 py-2 bg-red-600 text-white text-xs font-bold rounded-xl shadow-md">Yes, Delete All</button>
                </div>
            </div>
        </div>
    </div>

    <!-- SUCCESS MODALS -->
    <div id="success-add-modal" class="fixed inset-0 z-[1000] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl">
                <div class="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4 text-emerald-600"><i data-lucide="check-circle" class="w-8 h-8"></i></div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Success!</h3>
                <p class="text-sm text-slate-500 mb-6">Product has been successfully added to the database.</p>
                <button onclick="toggleModal('success-add-modal', false)" class="px-8 py-2 bg-maroon text-white text-xs font-bold rounded-xl shadow-md">Confirm</button>
            </div>
        </div>
    </div>

    <div id="success-bulk-delete-modal" class="fixed inset-0 z-[1000] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4 text-red-600"><i data-lucide="check-circle" class="w-8 h-8"></i></div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Bulk Deletion Complete</h3>
                <p class="text-sm text-slate-500 mb-6">Selected products have been successfully removed from the database.</p>
                <button onclick="toggleModal('success-bulk-delete-modal', false)" class="px-8 py-2 bg-maroon text-white text-xs font-bold rounded-xl shadow-md">Confirm</button>
            </div>
        </div>
    </div>

    <div id="success-edit-modal" class="fixed inset-0 z-[1000] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4 text-blue-600"><i data-lucide="check-circle" class="w-8 h-8"></i></div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Changes Saved</h3>
                <p id="success-edit-text" class="text-sm text-slate-500 mb-6">The product ledger has been updated successfully.</p>
                <button onclick="toggleModal('success-edit-modal', false); toggleModal('edit-product-modal', false)" class="px-8 py-2 bg-maroon text-white text-xs font-bold rounded-xl shadow-md">Confirm</button>
            </div>
        </div>
    </div>

    <div id="success-delete-modal" class="fixed inset-0 z-[1000] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4 text-red-600"><i data-lucide="user-x" class="w-8 h-8"></i></div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Purged</h3>
                <p class="text-sm text-slate-500 mb-6">The item has been successfully removed from the master list.</p>
                <button onclick="toggleModal('success-delete-modal', false)" class="px-8 py-2 bg-maroon text-white text-xs font-bold rounded-xl shadow-md">Confirm</button>
            </div>
        </div>
    </div>

    <div id="confirm-delete-modal" class="fixed inset-0 z-[1050] overflow-y-auto hidden" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4 text-center">
            <div onclick="toggleModal('confirm-delete-modal', false)" class="fixed inset-0 bg-maroon-900/60 transition-opacity backdrop-blur-sm"></div>
            <div class="modal-animate-in relative inline-block align-middle bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-sm w-full p-8 text-center border border-slate-100">
                <div class="w-20 h-20 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i data-lucide="trash-2" class="w-10 h-10 text-red-500"></i>
                </div>
                <h3 class="text-xl font-black text-slate-800 mb-2">Delete Product?</h3>
                <p class="text-sm text-slate-500 mb-8">This action cannot be undone. Are you sure you want to remove this item from the masterlist?</p>
                <div class="flex gap-3">
                    <button onclick="toggleModal('confirm-delete-modal', false)" class="flex-1 px-6 py-3 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded-2xl transition-all uppercase tracking-widest">Cancel</button>
                    <button id="btn-confirm-delete" class="flex-1 px-6 py-3 bg-red-500 hover:bg-red-600 text-white text-xs font-bold rounded-2xl shadow-lg shadow-red-200 transition-all uppercase tracking-widest">Yes, Delete</button>
                </div>
            </div>
        </div>
    </div>

    <div id="confirm-update-modal" class="fixed inset-0 z-[1050] overflow-y-auto hidden" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4 text-center">
            <div onclick="toggleModal('confirm-update-modal', false)" class="fixed inset-0 bg-maroon-900/60 transition-opacity backdrop-blur-sm"></div>
            <div class="modal-animate-in relative inline-block align-middle bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-sm w-full p-8 text-center border border-slate-100">
                <div class="w-20 h-20 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i data-lucide="help-circle" class="w-10 h-10 text-blue-500"></i>
                </div>
                <h3 class="text-xl font-black text-slate-800 mb-2">Save Changes?</h3>
                <p class="text-sm text-slate-500 mb-8">Are you sure you want to update this product's information?</p>
                <div class="flex gap-3">
                    <button onclick="toggleModal('confirm-update-modal', false)" class="flex-1 px-6 py-3 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded-2xl transition-all uppercase tracking-widest">No</button>
                    <button id="btn-confirm-update" class="flex-1 px-6 py-3 bg-maroon hover:bg-maroon-800 text-white text-xs font-bold rounded-2xl shadow-lg shadow-maroon/20 transition-all uppercase tracking-widest">Yes, Update</button>
                </div>
            </div>
        </div>
    </div>

    <div id="confirm-discard-modal" class="fixed inset-0 z-[1050] overflow-y-auto hidden" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4 text-center">
            <div onclick="toggleModal('confirm-discard-modal', false)" class="fixed inset-0 bg-maroon-900/60 transition-opacity backdrop-blur-sm"></div>
            <div class="modal-animate-in relative inline-block align-middle bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-sm w-full p-8 text-center border border-slate-100">
                <div class="w-20 h-20 bg-amber-50 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i data-lucide="alert-triangle" class="w-10 h-10 text-amber-500"></i>
                </div>
                <h3 class="text-xl font-black text-slate-800 mb-2">Discard Changes?</h3>
                <p class="text-sm text-slate-500 mb-8">You have unsaved changes. Are you sure you want to close?</p>
                <div class="flex gap-3">
                    <button onclick="toggleModal('confirm-discard-modal', false)" class="flex-1 px-6 py-3 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded-2xl transition-all uppercase tracking-widest">Cancel</button>
                    <button id="btn-confirm-discard" class="flex-1 px-6 py-3 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-2xl shadow-lg shadow-amber-600/20 transition-all uppercase tracking-widest">Yes, Discard</button>
                </div>
            </div>
        </div>
    </div>

    <div id="success-reorder-modal" class="fixed inset-0 z-[1000] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl">
                <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4 text-amber-600"><i data-lucide="check-circle" class="w-8 h-8"></i></div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Threshold Updated</h3>
                <p id="success-reorder-text" class="text-sm text-slate-500 mb-6">The system reorder levels have been recalibrated successfully.</p>
                <button onclick="toggleModal('success-reorder-modal', false); toggleModal('reorder-level-modal', false); fetchProducts(1);" class="px-8 py-2 bg-maroon text-white text-xs font-bold rounded-xl shadow-md">Confirm</button>
            </div>
        </div>
    </div>

    <!-- 6. GENERATE REPORT MODAL -->
    <div id="generate-report-modal" class="fixed inset-0 z-[1050] overflow-y-auto hidden" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4 text-center">
            <div onclick="toggleModal('generate-report-modal', false)" class="fixed inset-0 bg-maroon-900/60 transition-opacity backdrop-blur-sm"></div>
            <div class="modal-animate-in relative inline-block align-middle bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-md w-full p-8 border border-slate-100">
                <div class="w-20 h-20 bg-maroon-50 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i data-lucide="printer" class="w-10 h-10 text-maroon"></i>
                </div>
                <h3 class="text-2xl font-black text-slate-800 mb-2 text-center">Generate Reports</h3>
                <p class="text-sm text-slate-500 mb-8 text-center">Select the type of document you want to generate for the selected items.</p>
                
                <div class="space-y-4 mb-8">
                    <!-- Option 1: Price List Catalog -->
                    <label class="relative flex items-center p-4 bg-slate-50 rounded-2xl border-2 border-transparent hover:border-maroon/20 cursor-pointer transition-all group">
                        <input type="radio" name="report_type" value="catalog" class="hidden peer" checked>
                        <div class="w-5 h-5 border-2 border-slate-300 rounded-full flex items-center justify-center peer-checked:border-maroon peer-checked:bg-maroon transition-all">
                            <div class="w-2 h-2 bg-white rounded-full"></div>
                        </div>
                        <div class="ml-4">
                            <span class="block text-sm font-bold text-slate-700 uppercase tracking-wider">Price List Catalog</span>
                            <span class="block text-[10px] text-slate-400 font-medium">Generate a visual printable catalog with product pictures.</span>
                        </div>
                    </label>

                    <!-- Option 2: Price List Content -->
                    <label class="relative flex items-center p-4 bg-slate-50 rounded-2xl border-2 border-transparent hover:border-maroon/20 cursor-pointer transition-all group">
                        <input type="radio" name="report_type" value="content" class="hidden peer">
                        <div class="w-5 h-5 border-2 border-slate-300 rounded-full flex items-center justify-center peer-checked:border-maroon peer-checked:bg-maroon transition-all">
                            <div class="w-2 h-2 bg-white rounded-full"></div>
                        </div>
                        <div class="ml-4">
                            <span class="block text-sm font-bold text-slate-700 uppercase tracking-wider">Price List Content</span>
                            <span class="block text-[10px] text-slate-400 font-medium">Generate a simple tabular list of items and prices.</span>
                        </div>
                    </label>
                </div>

                <div class="flex gap-3">
                    <button onclick="toggleModal('generate-report-modal', false)" class="flex-1 px-6 py-3 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded-2xl transition-all uppercase tracking-widest">Cancel</button>
                    <button onclick="window.handleGenerateReport()" class="flex-1 px-6 py-3 bg-maroon hover:bg-maroon-hover text-white text-xs font-bold rounded-2xl shadow-lg shadow-maroon/20 transition-all uppercase tracking-widest">Generate</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 6a. REPORT FILTER MODAL -->
    <div id="report-filter-modal" class="fixed inset-0 z-[1060] overflow-y-auto hidden" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4 text-center">
            <div onclick="toggleModal('report-filter-modal', false)" class="fixed inset-0 bg-maroon-900/60 transition-opacity backdrop-blur-sm"></div>
            <div class="modal-animate-in relative inline-block align-middle bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-md w-full p-8 border border-slate-100">
                <div class="w-16 h-16 bg-maroon-50 rounded-full flex items-center justify-center mx-auto mb-5">
                    <i data-lucide="filter" class="w-8 h-8 text-maroon"></i>
                </div>
                <h3 class="text-xl font-black text-slate-800 mb-1 text-center">Filter Report</h3>
                <p class="text-xs text-slate-500 mb-6 text-center">Narrow down the selected products before generating.</p>
                
                <div class="space-y-4 mb-6">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Description</label>
                        <div class="report-filter-combobox">
                            <input type="text" id="report-filter-description" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all" placeholder="All Descriptions" autocomplete="off">
                            <div id="report-filter-description-dropdown" class="report-filter-dropdown hidden"></div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Brand</label>
                        <div class="report-filter-combobox">
                            <input type="text" id="report-filter-brand" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all" placeholder="All Brands" autocomplete="off">
                            <div id="report-filter-brand-dropdown" class="report-filter-dropdown hidden"></div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Application</label>
                        <div class="report-filter-combobox">
                            <input type="text" id="report-filter-application" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all" placeholder="All Applications" autocomplete="off">
                            <div id="report-filter-application-dropdown" class="report-filter-dropdown hidden"></div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Year</label>
                        <div class="report-filter-combobox">
                            <input type="text" id="report-filter-year" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-maroon-900/10 focus:border-maroon-900 transition-all" placeholder="All Years" autocomplete="off">
                            <div id="report-filter-year-dropdown" class="report-filter-dropdown hidden"></div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button onclick="toggleModal('report-filter-modal', false); toggleModal('generate-report-modal', true)" class="flex-1 px-6 py-3 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded-2xl transition-all uppercase tracking-widest">Cancel</button>
                    <button onclick="window.clearReportFilters()" class="flex-1 px-6 py-3 bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-bold rounded-2xl transition-all uppercase tracking-widest">Clear Filters</button>
                    <button onclick="window.applyReportFilters()" class="flex-1 px-6 py-3 bg-maroon hover:bg-maroon-hover text-white text-xs font-bold rounded-2xl shadow-lg shadow-maroon/20 transition-all uppercase tracking-widest">Apply &amp; Generate</button>
                </div>
            </div>
        </div>
    </div>

</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script src="<?php echo e(asset('js/product_master_list.js')); ?>?v=<?php echo e(time()); ?>"></script>
<?php $__env->stopPush(); ?>


<?php echo $__env->make('partials.user_account.user_sidebar_navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\hatdog\resources\views/Regular_User/master_list/Product_Master-list.blade.php ENDPATH**/ ?>