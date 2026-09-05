@push('styles')
    <link rel="stylesheet" href="{{ asset('css/supplier_master_list.css') }}?v={{ time() }}">
    <!-- Leaflet CSS for OpenStreetMap -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <style>
        .osm-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            margin-top: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            max-height: 200px;
            overflow-y: auto;
        }
        .osm-suggestion-item {
            padding: 0.75rem 1rem;
            cursor: pointer;
            font-size: 0.75rem;
            transition: all 0.2s;
            border-bottom: 1px solid #f1f5f9;
        }
        .osm-suggestion-item:last-child {
            border-bottom: none;
        }
        .osm-suggestion-item:hover {
            background-color: #f8fafc;
            color: #800000;
        }
    </style>
@endpush

@section('supplier_master_content')
<script>
    // Global function to fix malformed URLs for Supplier module
    (function() {
        const originalFetch = window.fetch;
        window.fetch = function(input, init) {
            if (typeof input === 'string') {
                // If the URL starts with /admin/masterlist/supplier and we are in /hatdog/public
                if (input.startsWith('/admin/masterlist/supplier') && window.location.pathname.includes('/hatdog/public')) {
                    input = '/hatdog/public' + input;
                }
            }
            return originalFetch(input, init);
        };
    })();
</script>
<div id="page-supplier-master-root" class="space-y-8 animate-fade-in">

    <!-- SUB-HEADER INTERACTIVE PANEL -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
        <div>
            <h2 class="text-lg font-bold text-slate-800">Supplier Master List Administration</h2>
            <p class="text-xs text-slate-400">Manage your supplier relationships, lifecycle tracking, and procurement logistics.</p>
        </div>
        
        <!-- HEADER QUICK ACTIONS -->
        <div class="flex items-center space-x-3">
            <!-- View Switcher -->
            <div class="flex bg-slate-100 p-1 rounded-xl border border-slate-200">
                <button id="view-table-btn" onclick="toggleView('table')" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all bg-maroon text-white shadow-sm">
                    <i data-lucide="list" class="w-4 h-4 inline-block mr-1"></i> Table
                </button>
                <button id="view-card-btn" onclick="toggleView('card')" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all bg-white text-slate-600">
                    <i data-lucide="layout-grid" class="w-4 h-4 inline-block mr-1"></i> Cards
                </button>
            </div>

            <!-- Filter Button -->
            <button onclick="toggleModal('filter-modal', true)" class="px-4 py-2.5 bg-white border border-slate-200 hover:border-maroon-200 hover:bg-slate-50 text-slate-700 hover:text-maroon text-xs font-bold rounded-xl shadow-sm flex items-center space-x-2 transition-all">
                <i data-lucide="sliders-horizontal" class="w-4 h-4 text-slate-500"></i>
                <span>Filter</span>
            </button>
            
            <!-- Add Supplier Button -->
            <button onclick="toggleModal('add-supplier-modal', true)" class="px-4 py-2.5 bg-maroon hover:bg-maroon-hover text-white text-xs font-bold rounded-xl shadow-lg flex items-center space-x-2 transition-all hover:shadow-xl">
                <i data-lucide="plus-circle" class="w-4 h-4 text-gold"></i>
                <span>Add Supplier</span>
            </button>
        </div>
    </div>

    <!-- DASHBOARD STATS CARDS -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Stat Card 1: TOTAL SUPPLIERS -->
        <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between transition-all duration-200 hover:shadow-md hover:border-slate-200">
            <div class="space-y-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Suppliers</span>
                <h3 id="stat-total-suppliers" class="text-2xl font-extrabold text-slate-800 tracking-tight">0</h3>
            </div>
            <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-maroon-gradient shadow-lg">
                <i data-lucide="truck" class="w-5 h-5 text-gold"></i>
            </div>
        </div>

        <!-- Stat Card 2: ACTIVE SUPPLIERS -->
        <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between transition-all duration-200 hover:shadow-md hover:border-slate-200">
            <div class="space-y-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Active Suppliers</span>
                <h3 id="stat-active-suppliers" class="text-2xl font-extrabold text-emerald-600 tracking-tight">0</h3>
            </div>
            <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-maroon-gradient shadow-lg">
                <i data-lucide="user-check" class="w-5 h-5 text-gold"></i>
            </div>
        </div>
    </div>

    <!-- TABLE VIEW -->
    <div id="supplier-table-container" class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/20 text-slate-400 text-[10px] font-bold uppercase tracking-wider">
                        <th class="py-4 px-4">Code</th>
                        <th class="py-4 px-4">Profile</th>
                        <th class="py-4 px-4">Supplier Name</th>
                        <th class="py-4 px-4">Contact No#</th>
                        <th class="py-4 px-4">Contact Person</th>
                        <th class="py-4 px-4">Tin</th>
                        <th class="py-4 px-4">Address</th>
                        <th class="py-4 px-4">Status</th>
                        <th class="py-4 px-4">Lifecycle</th>
                        <th class="py-4 px-4 text-center">Action</th>
                    </tr>
                    <!-- Column Search Row -->
                    <tr class="border-b border-slate-100 bg-slate-50/10">
                        <th class="p-2 px-4"><input type="text" data-col="supplier_code" placeholder="Search Code..." class="column-search-input text-[10px]"></th>
                        <th class="p-2 px-4"></th>
                        <th class="p-2 px-4"><input type="text" data-col="name" placeholder="Search Name..." class="column-search-input text-[10px]"></th>
                        <th class="p-2 px-4"><input type="text" data-col="contact_number" placeholder="Search No..." class="column-search-input text-[10px]"></th>
                        <th class="p-2 px-4"><input type="text" data-col="contact_person" placeholder="Search Person..." class="column-search-input text-[10px]"></th>
                        <th class="p-2 px-4"><input type="text" data-col="tin" placeholder="Search Tin..." class="column-search-input text-[10px]"></th>
                        <th class="p-2 px-4"><input type="text" data-col="address" placeholder="Search Address..." class="column-search-input text-[10px]"></th>
                        <th class="p-2 px-4"></th>
                        <th class="p-2 px-4"></th>
                        <th class="p-2 px-4"></th>
                    </tr>
                </thead>
                <tbody id="supplier-tbody" class="divide-y divide-slate-100 text-sm text-slate-600">
                    <!-- JS Rendered -->
                </tbody>
            </table>
        </div>
    </div>
    <div id="supplier-table-pagination" class="p-4 border-t border-slate-100"></div>

    <!-- CARD VIEW -->
    <div id="supplier-card-container" class="hidden space-y-4">
        <!-- Cards Search Bar -->
        <div class="flex items-center justify-end">
            <div class="relative w-full max-w-md">
                <input type="text" id="cards-search-input" onkeyup="onCardsSearch()" class="w-full px-5 py-3 pl-12 bg-white border border-slate-200 rounded-2xl text-sm shadow-sm outline-none focus:border-maroon focus:ring-4 focus:ring-maroon/5 transition-all" placeholder="Search suppliers by name, code, or contact person...">
                <i data-lucide="search" class="w-5 h-5 absolute left-4 top-3 text-slate-400"></i>
            </div>
        </div>
        <div id="supplier-card-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <!-- JS Rendered -->
        </div>
    </div>
    <div id="supplier-card-pagination" class="p-4"></div>

    <!-- ==================== MODALS ==================== -->

    <!-- 1. ADD SUPPLIER MODAL -->
    <div id="add-supplier-modal" class="fixed inset-0 z-[900] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div onclick="toggleModal('add-supplier-modal', false)" class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-2xl shadow-2xl max-w-2xl w-full overflow-hidden border border-goldlining-500/20">
                <div class="bg-maroon-gradient px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="plus-circle" class="w-5 h-5 text-gold"></i>
                        <h3 class="text-sm font-bold text-white uppercase tracking-wider">Add New Supplier</h3>
                    </div>
                    <button onclick="toggleModal('add-supplier-modal', false)" class="text-slate-300 hover:text-white transition-colors">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <form id="add-supplier-form" class="p-6 space-y-6 max-h-[75vh] overflow-y-auto custom-scrollbar">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Profile Image -->
                        <div class="md:col-span-2 flex flex-col items-center">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Supplier Profile</label>
                            <div id="profile-drop-zone" class="w-full h-48 rounded-2xl bg-slate-50 border-2 border-dashed border-slate-200 flex flex-col items-center justify-center overflow-hidden relative group cursor-pointer hover:bg-slate-100/50 hover:border-maroon/30 transition-all">
                                <div id="profile-preview-content" class="flex flex-col items-center justify-center space-y-2 pointer-events-none">
                                    <i data-lucide="image-plus" class="w-10 h-10 text-slate-300 group-hover:text-maroon/40 transition-colors"></i>
                                    <div class="flex flex-col items-center">
                                        <span class="text-xs text-slate-500 font-bold uppercase tracking-widest">Select or Drag Image</span>
                                        <span class="text-[10px] text-slate-400 font-medium mt-1">PNG, JPG or WEBP (Max. 5MB)</span>
                                    </div>
                                </div>
                                <input type="file" name="supplier_picture_file" id="supplier-picture-input" onchange="handleImageUpload(event, 'profile-preview')" class="absolute inset-0 opacity-0 cursor-pointer">
                                <input type="hidden" name="Supplier_Image" id="add-supplier-picture-base64">
                            </div>
                        </div>

                        <!-- Fields -->
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Supplier Code</label>
                            <input type="text" name="supplier_code" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon transition-all" placeholder="e.g. SUP-001" required>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Supplier Name</label>
                            <input type="text" name="name" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon transition-all" placeholder="Full Company Name" required>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Contact No#</label>
                            <input type="text" name="contact_number" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon transition-all" placeholder="Phone or Mobile">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Contact Person</label>
                            <input type="text" name="contact_person" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon transition-all" placeholder="Name of representative">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">TIN</label>
                            <input type="text" name="tin" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon transition-all" placeholder="Tax Identification Number">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Telefax</label>
                            <input type="text" name="telefax" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon transition-all" placeholder="Fax Number">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Address (Google Maps Integrated)</label>
                            <div class="flex gap-2">
                                <div class="w-1/3">
                                    <select id="supplier-country-select" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon transition-all">
                                        <option value="PH" selected>Philippines</option>
                                        <option value="TW">Taiwan</option>
                                        <option value="CN">China</option>
                                        <option value="JP">Japan</option>
                                        <option value="US">USA</option>
                                        <option value="TH">Thailand</option>
                                        <option value="MY">Malaysia</option>
                                        <option value="SG">Singapore</option>
                                    </select>
                                </div>
                                <div class="flex-1 relative">
                                    <input type="text" name="address" id="supplier-address-input" class="w-full px-4 py-2 pl-10 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon transition-all" placeholder="Search street or location...">
                                    <i data-lucide="map-pin" class="w-4 h-4 absolute left-3 top-2.5 text-slate-400"></i>
                                </div>
                            </div>
                            <div id="osm-map-container" class="mt-2 h-48 bg-slate-100 rounded-xl border border-slate-200 z-10 overflow-hidden">
                                <!-- Leaflet Map -->
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Terms (Days)</label>
                            <input type="number" name="payment_terms" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon transition-all" placeholder="e.g. 30">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Lifecycle (Automated)</label>
                            <input type="text" id="supplier-lifecycle" readonly class="w-full px-4 py-2 bg-slate-100 border border-slate-200 rounded-xl text-sm text-slate-500 font-bold outline-none cursor-not-allowed" placeholder="Calculated from dates">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Start Date</label>
                            <input type="date" name="start_date" id="supplier-start-date" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">End Date</label>
                            <input type="date" name="end_date" id="supplier-end-date" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon transition-all">
                        </div>
                    </div>
                </form>

                <div class="bg-slate-50 px-6 py-4 flex items-center justify-end space-x-3 border-t border-slate-100">
                    <button type="button" onclick="toggleModal('add-supplier-modal', false)" class="px-6 py-2.5 bg-white border border-slate-200 text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-100 transition-all">Cancel</button>
                    <button type="button" onclick="toggleModal('confirm-add-modal', true)" class="px-8 py-2.5 bg-maroon text-white text-xs font-bold rounded-xl shadow-lg hover:shadow-xl transition-all">Confirm To Add</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. EDIT SUPPLIER MODAL -->
    <div id="edit-supplier-modal" class="fixed inset-0 z-[900] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div onclick="toggleModal('edit-supplier-modal', false)" class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-2xl shadow-2xl max-w-2xl w-full overflow-hidden border border-goldlining-500/20">
                <div class="bg-maroon-gradient px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="edit-3" class="w-5 h-5 text-gold"></i>
                        <h3 class="text-sm font-bold text-white uppercase tracking-wider">Edit Supplier Information</h3>
                    </div>
                    <button onclick="toggleModal('edit-supplier-modal', false)" class="text-slate-300 hover:text-white transition-colors">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <form id="edit-supplier-form" class="p-6 space-y-6 max-h-[75vh] overflow-y-auto custom-scrollbar">
                    @csrf
                    <input type="hidden" id="edit-supplier-id" name="id">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Profile Image -->
                        <div class="md:col-span-2 flex flex-col items-center">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Change Supplier Profile</label>
                            <div id="edit-profile-drop-zone" class="w-full h-48 rounded-2xl bg-slate-50 border-2 border-dashed border-slate-200 flex flex-col items-center justify-center overflow-hidden relative group cursor-pointer hover:bg-slate-100/50 hover:border-maroon/30 transition-all">
                                <div id="edit-profile-preview-content" class="flex flex-col items-center justify-center space-y-2 pointer-events-none">
                                    <i data-lucide="image-plus" class="w-10 h-10 text-slate-300 group-hover:text-maroon/40 transition-colors"></i>
                                    <div class="flex flex-col items-center">
                                        <span class="text-xs text-slate-500 font-bold uppercase tracking-widest">Select or Drag Image</span>
                                        <span class="text-[10px] text-slate-400 font-medium mt-1">PNG, JPG or WEBP (Max. 5MB)</span>
                                    </div>
                                </div>
                                <input type="file" name="edit_supplier_picture_file" id="edit-supplier-picture-input" onchange="handleImageUpload(event, 'edit-profile-preview')" class="absolute inset-0 opacity-0 cursor-pointer">
                                <input type="hidden" name="Supplier_Image" id="edit-supplier-picture-base64">
                            </div>
                        </div>

                        <!-- Fields -->
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Supplier Code</label>
                            <input type="text" name="supplier_code" id="edit-supplier-code" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Supplier Name</label>
                            <input type="text" name="name" id="edit-supplier-name" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Contact No#</label>
                            <input type="text" name="contact_number" id="edit-supplier-contact" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Contact Person</label>
                            <input type="text" name="contact_person" id="edit-supplier-person" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">TIN</label>
                            <input type="text" name="tin" id="edit-supplier-tin" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Telefax</label>
                            <input type="text" name="telefax" id="edit-supplier-telefax" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon transition-all">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Address (Integrated Map Search)</label>
                            <div class="flex gap-2">
                                <div class="w-1/3">
                                    <select id="edit-supplier-country-select" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon transition-all">
                                        <option value="PH" selected>Philippines</option>
                                        <option value="TW">Taiwan</option>
                                        <option value="CN">China</option>
                                        <option value="JP">Japan</option>
                                        <option value="US">USA</option>
                                        <option value="TH">Thailand</option>
                                        <option value="MY">Malaysia</option>
                                        <option value="SG">Singapore</option>
                                    </select>
                                </div>
                                <div class="flex-1 relative">
                                    <input type="text" name="address" id="edit-supplier-address" class="w-full px-4 py-2 pl-10 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon transition-all" placeholder="Search street or location...">
                                    <i data-lucide="map-pin" class="w-4 h-4 absolute left-3 top-2.5 text-slate-400"></i>
                                </div>
                            </div>
                            <div id="edit-osm-map-container" class="mt-2 h-48 bg-slate-100 rounded-xl border border-slate-200 z-10 overflow-hidden">
                                <!-- Leaflet Map -->
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Terms (Days)</label>
                            <input type="number" name="payment_terms" id="edit-supplier-terms" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Lifecycle (Automated)</label>
                            <input type="text" id="edit-supplier-lifecycle" readonly class="w-full px-4 py-2 bg-slate-100 border border-slate-200 rounded-xl text-sm text-slate-500 font-bold outline-none cursor-not-allowed">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Start Date</label>
                            <input type="date" name="start_date" id="edit-supplier-start-date" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">End Date</label>
                            <input type="date" name="end_date" id="edit-supplier-end-date" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-maroon transition-all">
                        </div>
                    </div>
                </form>

                <div class="bg-slate-50 px-6 py-4 flex items-center justify-end space-x-3 border-t border-slate-100">
                    <button type="button" onclick="toggleModal('edit-supplier-modal', false)" class="px-6 py-2.5 bg-white border border-slate-200 text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-100 transition-all">Cancel</button>
                    <button type="button" onclick="toggleModal('confirm-edit-modal', true)" class="px-8 py-2.5 bg-maroon text-white text-xs font-bold rounded-xl shadow-lg hover:shadow-xl transition-all">Confirm Edit</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. VIEW SUPPLIER MODAL (30/70 LAYOUT) -->
    <div id="view-supplier-modal" class="fixed inset-0 z-[900] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div onclick="toggleModal('view-supplier-modal', false)" class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-3xl shadow-2xl max-w-[95vw] w-full h-[90vh] overflow-hidden flex flex-col border border-goldlining-500/20">
                <!-- Modal Header -->
                <div class="bg-maroon-gradient px-8 py-4 flex items-center justify-between flex-shrink-0">
                    <div class="flex items-center space-x-3">
                        <i data-lucide="eye" class="w-6 h-6 text-gold"></i>
                        <h3 class="text-lg font-bold text-white uppercase tracking-widest">Supplier Comprehensive Profile</h3>
                    </div>
                    <button onclick="toggleModal('view-supplier-modal', false)" class="text-slate-300 hover:text-white transition-colors p-2 hover:bg-white/10 rounded-full">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>

                <div class="flex flex-1 overflow-hidden">
                    <!-- LEFT SIDE (30%): SUPPLIER DETAILS -->
                    <div class="w-[30%] bg-slate-50/50 border-r border-slate-100 p-8 overflow-y-auto custom-scrollbar flex flex-col space-y-8">
                        <div class="flex flex-col items-center space-y-4">
                            <div id="view-supplier-pic" class="w-40 h-40 rounded-3xl bg-white shadow-xl border-4 border-white overflow-hidden flex items-center justify-center">
                                <i data-lucide="truck" class="w-16 h-16 text-slate-200"></i>
                            </div>
                            <div class="text-center">
                                <h4 id="view-supplier-name" class="text-xl font-extrabold text-slate-800">Supplier Name</h4>
                                <p id="view-supplier-code" class="text-sm font-bold text-maroon tracking-wider">SUP-000</p>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 space-y-4">
                                <h5 class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest border-b border-slate-50 pb-2">Primary Information</h5>
                                
                                <div class="flex flex-col">
                                    <span class="text-[10px] text-slate-400 font-bold uppercase">Contact No#</span>
                                    <span id="view-supplier-contact" class="text-sm text-slate-700 font-semibold">0000-000-0000</span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-[10px] text-slate-400 font-bold uppercase">Contact Person</span>
                                    <span id="view-supplier-person" class="text-sm text-slate-700 font-semibold">Representative Name</span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-[10px] text-slate-400 font-bold uppercase">TIN</span>
                                    <span id="view-supplier-tin" class="text-sm text-slate-700 font-mono font-bold">000-000-000-000</span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-[10px] text-slate-400 font-bold uppercase">Address</span>
                                    <span id="view-supplier-address" class="text-sm text-slate-700 font-semibold leading-relaxed">Complete Office Address</span>
                                </div>
                            </div>

                            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 space-y-4">
                                <h5 class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest border-b border-slate-50 pb-2">Status & Lifecycle</h5>
                                
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] text-slate-400 font-bold uppercase">Current Status</span>
                                    <span id="view-supplier-status-tag" class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider">Status</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] text-slate-400 font-bold uppercase">Active Lifecycle</span>
                                    <span id="view-supplier-lifecycle" class="text-sm text-slate-800 font-extrabold">0y 0m 0d</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT SIDE (70%): TABS & TABLES -->
                    <div class="w-[70%] bg-white flex flex-col overflow-hidden">
                        <!-- Tab Headers -->
                        <div class="flex items-center border-b border-slate-100 bg-slate-50/30 px-6">
                            <button onclick="switchViewTab('purchase')" id="tab-btn-purchase" class="px-8 py-5 text-xs font-bold uppercase tracking-widest border-b-2 border-maroon text-maroon transition-all">
                                <i data-lucide="shopping-cart" class="w-4 h-4 inline-block mr-2"></i> Purchased Items
                            </button>
                            <button onclick="switchViewTab('ledger')" id="tab-btn-ledger" class="px-8 py-5 text-xs font-bold uppercase tracking-widest border-b-2 border-transparent text-slate-400 hover:text-slate-600 transition-all">
                                <i data-lucide="book-open" class="w-4 h-4 inline-block mr-2"></i> Ledger
                            </button>
                        </div>

                        <!-- Tab Content -->
                        <div class="flex-1 overflow-hidden flex flex-col p-6 space-y-4">
                            <!-- Purchased Items Summary -->
                            <div id="purchased-items-summary" class="grid grid-cols-2 xl:grid-cols-5 gap-3">
                                <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Purchase Transactions</span>
                                    <strong id="purchased-summary-transactions" class="text-sm text-slate-800">0</strong>
                                </div>
                                <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Purchased Item Rows</span>
                                    <strong id="purchased-summary-rows" class="text-sm text-slate-800">0</strong>
                                </div>
                                <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Ordered Qty</span>
                                    <strong id="purchased-summary-ordered" class="text-sm text-slate-800">0</strong>
                                </div>
                                <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Received Qty</span>
                                    <strong id="purchased-summary-received" class="text-sm text-slate-800">0</strong>
                                </div>
                                <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Purchase Amount</span>
                                    <strong id="purchased-summary-amount" class="text-sm text-maroon">0.00</strong>
                                </div>
                            </div>

                            <div id="purchased-items-controls" class="flex items-center justify-between gap-3">
                                <span id="view-table-summary" class="text-[10px] text-slate-400 font-bold uppercase">Total Records: 0</span>
                                <div class="flex items-center gap-2">
                                    <button type="button" onclick="refreshPurchasedItems()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-slate-200 text-slate-600 hover:text-maroon hover:border-maroon/30 text-[10px] font-bold rounded-lg transition-all">
                                        <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> Refresh
                                    </button>
                                    <button type="button" onclick="clearPurchasedItemFilters()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-100 text-slate-700 hover:bg-slate-200 text-[10px] font-bold rounded-lg transition-all">
                                        <i data-lucide="filter-x" class="w-3.5 h-3.5"></i> Clear Filters
                                    </button>
                                </div>
                            </div>

                            <!-- TABLE 1: PURCHASED ITEMS -->
                            <div id="view-container-purchase" class="flex-1 overflow-hidden border border-slate-100 rounded-2xl shadow-sm flex flex-col">
                                <div class="overflow-x-auto flex-1 custom-scrollbar">
                                    <table class="w-full text-left border-collapse min-w-[1500px]">
                                        <thead class="sticky top-0 z-10 bg-slate-50 text-slate-400 text-[10px] font-bold uppercase tracking-wider">
                                            <tr class="border-b border-slate-200">
                                                <th class="py-3 px-4"><button type="button" onclick="sortPurchasedItems('transaction_date')" class="font-bold uppercase">Date</button></th>
                                                <th class="py-3 px-4"><button type="button" onclick="sortPurchasedItems('transaction_code')" class="font-bold uppercase">Transaction Code</button></th>
                                                <th class="py-3 px-4"><button type="button" onclick="sortPurchasedItems('reference_no')" class="font-bold uppercase">Reference No.</button></th>
                                                <th class="py-3 px-4"><button type="button" onclick="sortPurchasedItems('product_code')" class="font-bold uppercase">Product Code</button></th>
                                                <th class="py-3 px-4"><button type="button" onclick="sortPurchasedItems('description')" class="font-bold uppercase">Description</button></th>
                                                <th class="py-3 px-4"><button type="button" onclick="sortPurchasedItems('unit')" class="font-bold uppercase">Unit</button></th>
                                                <th class="py-3 px-4 text-right"><button type="button" onclick="sortPurchasedItems('quantity')" class="font-bold uppercase">Ordered Qty</button></th>
                                                <th class="py-3 px-4 text-right"><button type="button" onclick="sortPurchasedItems('actual_quantity')" class="font-bold uppercase">Received Qty</button></th>
                                                <th class="py-3 px-4 text-right"><button type="button" onclick="sortPurchasedItems('unit_price')" class="font-bold uppercase">Unit Price</button></th>
                                                <th class="py-3 px-4 text-right"><button type="button" onclick="sortPurchasedItems('discount_percent')" class="font-bold uppercase">Discount %</button></th>
                                                <th class="py-3 px-4 text-right"><button type="button" onclick="sortPurchasedItems('discount_amount')" class="font-bold uppercase">Discount Amount</button></th>
                                                <th class="py-3 px-4 text-right"><button type="button" onclick="sortPurchasedItems('subtotal')" class="font-bold uppercase">Subtotal</button></th>
                                                <th class="py-3 px-4"><button type="button" onclick="sortPurchasedItems('transaction_title')" class="font-bold uppercase">Status/Transaction Title</button></th>
                                            </tr>
                                            <!-- Column Head Search -->
                                            <tr class="bg-white border-b border-slate-100">
                                                <th class="p-1.5 px-4"><input type="text" data-purchased-items-filter="transaction_date" placeholder="Date..." class="purchased-items-filter column-search-input py-1 text-[9px]"></th>
                                                <th class="p-1.5 px-4"><input type="text" data-purchased-items-filter="transaction_code" placeholder="Code..." class="purchased-items-filter column-search-input py-1 text-[9px]"></th>
                                                <th class="p-1.5 px-4"><input type="text" data-purchased-items-filter="reference_no" placeholder="Ref..." class="purchased-items-filter column-search-input py-1 text-[9px]"></th>
                                                <th class="p-1.5 px-4"><input type="text" data-purchased-items-filter="product_code" placeholder="Product..." class="purchased-items-filter column-search-input py-1 text-[9px]"></th>
                                                <th class="p-1.5 px-4"><input type="text" data-purchased-items-filter="description" placeholder="Description..." class="purchased-items-filter column-search-input py-1 text-[9px]"></th>
                                                <th class="p-1.5 px-4"><input type="text" data-purchased-items-filter="unit" placeholder="Unit..." class="purchased-items-filter column-search-input py-1 text-[9px]"></th>
                                                <th class="p-1.5 px-4"><input type="text" data-purchased-items-filter="quantity" placeholder="Qty..." class="purchased-items-filter column-search-input py-1 text-[9px]"></th>
                                                <th class="p-1.5 px-4"><input type="text" data-purchased-items-filter="actual_quantity" placeholder="Received..." class="purchased-items-filter column-search-input py-1 text-[9px]"></th>
                                                <th class="p-1.5 px-4"><input type="text" data-purchased-items-filter="unit_price" placeholder="Price..." class="purchased-items-filter column-search-input py-1 text-[9px]"></th>
                                                <th class="p-1.5 px-4"><input type="text" data-purchased-items-filter="discount_percent" placeholder="Disc..." class="purchased-items-filter column-search-input py-1 text-[9px]"></th>
                                                <th class="p-1.5 px-4"><input type="text" data-purchased-items-filter="discount_amount" placeholder="Amount..." class="purchased-items-filter column-search-input py-1 text-[9px]"></th>
                                                <th class="p-1.5 px-4"><input type="text" data-purchased-items-filter="subtotal" placeholder="Subtotal..." class="purchased-items-filter column-search-input py-1 text-[9px]"></th>
                                                <th class="p-1.5 px-4"><input type="text" data-purchased-items-filter="transaction_title" placeholder="Title..." class="purchased-items-filter column-search-input py-1 text-[9px]"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="purchase-history-tbody" class="divide-y divide-slate-50 text-xs text-slate-600">
                                            <!-- JS Rendered -->
                                            <tr class="hover:bg-slate-50/50">
                                                <td colspan="13" class="py-12 text-center text-slate-300 italic">No purchased item records found for this supplier.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div id="purchased-items-pagination" class="border-t border-slate-100 p-3 bg-white"></div>
                            </div>

                            <!-- TABLE 2: LEDGER -->
                            <div id="view-container-ledger" class="hidden flex-1 overflow-hidden flex flex-col gap-4">
                                <div id="unregistered-po-summary" class="grid grid-cols-2 xl:grid-cols-4 gap-3">
                                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Unregistered Purchase Orders</span>
                                        <strong id="unregistered-summary-count" class="text-sm text-slate-800">0</strong>
                                    </div>
                                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Unregistered Amount</span>
                                        <strong id="unregistered-summary-amount" class="text-sm text-maroon">0.00</strong>
                                    </div>
                                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Missing Supplier Invoice No.</span>
                                        <strong id="unregistered-summary-missing" class="text-sm text-slate-800">0</strong>
                                    </div>
                                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Latest Purchase Order Date</span>
                                        <strong id="unregistered-summary-latest" class="text-sm text-slate-800">N/A</strong>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between gap-3">
                                    <span id="unregistered-table-summary" class="text-[10px] text-slate-400 font-bold uppercase">Total Records: 0</span>
                                    <div class="flex items-center gap-2">
                                        <button type="button" onclick="refreshUnregisteredPurchaseOrders()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-slate-200 text-slate-600 hover:text-maroon hover:border-maroon/30 text-[10px] font-bold rounded-lg transition-all">
                                            <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> Refresh
                                        </button>
                                        <button type="button" onclick="clearUnregisteredPurchaseOrderFilters()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-100 text-slate-700 hover:bg-slate-200 text-[10px] font-bold rounded-lg transition-all">
                                            <i data-lucide="filter-x" class="w-3.5 h-3.5"></i> Clear Filters
                                        </button>
                                    </div>
                                </div>

                                <div class="overflow-hidden border border-slate-100 rounded-2xl shadow-sm flex flex-col flex-1">
                                    <div class="overflow-x-auto flex-1 custom-scrollbar">
                                    <table class="w-full text-left border-collapse min-w-[1150px]">
                                        <thead class="sticky top-0 z-10 bg-slate-50 text-slate-400 text-[10px] font-bold uppercase tracking-wider">
                                            <tr class="border-b border-slate-200">
                                                <th class="py-3 px-4"><button type="button" onclick="sortUnregisteredPurchaseOrders('purchase_order_number')" class="font-bold uppercase">Purchase Order No.</button></th>
                                                <th class="py-3 px-4"><button type="button" onclick="sortUnregisteredPurchaseOrders('supplier_invoice_number')" class="font-bold uppercase">Supplier Invoice No.</button></th>
                                                <th class="py-3 px-4"><button type="button" onclick="sortUnregisteredPurchaseOrders('date')" class="font-bold uppercase">Purchase Order Date</button></th>
                                                <th class="py-3 px-4"><button type="button" onclick="sortUnregisteredPurchaseOrders('reference_no')" class="font-bold uppercase">Reference No.</button></th>
                                                <th class="py-3 px-4"><button type="button" onclick="sortUnregisteredPurchaseOrders('status')" class="font-bold uppercase">Status</button></th>
                                                <th class="py-3 px-4 text-right"><button type="button" onclick="sortUnregisteredPurchaseOrders('total_amount')" class="font-bold uppercase">Total Amount</button></th>
                                                <th class="py-3 px-4 text-right"><button type="button" onclick="sortUnregisteredPurchaseOrders('remaining_balance')" class="font-bold uppercase">Remaining Balance</button></th>
                                                <th class="py-3 px-4"><button type="button" onclick="sortUnregisteredPurchaseOrders('created_at')" class="font-bold uppercase">Created At</button></th>
                                                <th class="py-3 px-4 text-center">Action</th>
                                            </tr>
                                            <!-- Column Head Search -->
                                            <tr class="bg-white border-b border-slate-100">
                                                <th class="p-1.5 px-4"><input type="text" data-unregistered-po-filter="purchase_order_number" placeholder="PO..." class="unregistered-po-filter column-search-input py-1 text-[9px]"></th>
                                                <th class="p-1.5 px-4"><input type="text" data-unregistered-po-filter="supplier_invoice_number" placeholder="Invoice..." class="unregistered-po-filter column-search-input py-1 text-[9px]"></th>
                                                <th class="p-1.5 px-4"><input type="text" data-unregistered-po-filter="date" placeholder="Date..." class="unregistered-po-filter column-search-input py-1 text-[9px]"></th>
                                                <th class="p-1.5 px-4"><input type="text" data-unregistered-po-filter="reference_no" placeholder="Ref..." class="unregistered-po-filter column-search-input py-1 text-[9px]"></th>
                                                <th class="p-1.5 px-4"><input type="text" data-unregistered-po-filter="status" placeholder="Status..." class="unregistered-po-filter column-search-input py-1 text-[9px]"></th>
                                                <th class="p-1.5 px-4"><input type="text" data-unregistered-po-filter="total_amount" placeholder="Amount..." class="unregistered-po-filter column-search-input py-1 text-[9px]"></th>
                                                <th class="p-1.5 px-4"><input type="text" data-unregistered-po-filter="remaining_balance" placeholder="Balance..." class="unregistered-po-filter column-search-input py-1 text-[9px]"></th>
                                                <th class="p-1.5 px-4"></th>
                                                <th class="p-1.5 px-4"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="ledger-tbody" class="divide-y divide-slate-50 text-xs text-slate-600">
                                            <!-- JS Rendered -->
                                            <tr class="hover:bg-slate-50/50">
                                                <td colspan="9" class="py-12 text-center text-slate-300 italic">Loading unregistered purchase invoices...</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    </div>
                                    <div id="unregistered-po-pagination" class="border-t border-slate-100 p-3 bg-white"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. FILTER MODAL -->
    <div id="filter-modal" class="fixed inset-0 z-[900] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div onclick="toggleModal('filter-modal', false)" class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-2xl p-6 max-w-sm w-full shadow-2xl">
                <h3 class="text-sm font-bold text-slate-800 uppercase mb-4 tracking-wider flex items-center gap-2">
                    <i data-lucide="sliders-horizontal" class="w-4 h-4 text-maroon"></i> Filter Records
                </h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Filter by Status</label>
                        <select id="supplier-filter-status" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none">
                            <option value="all">All Statuses</option>
                            <option value="Active">Active Only</option>
                            <option value="Inactive">Inactive Only</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Filter by Type</label>
                        <select id="supplier-filter-type" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none">
                            <option value="all">All Suppliers</option>
                            <option value="new">Newly Registered</option>
                            <option value="old">Existing/Old</option>
                        </select>
                    </div>
                </div>
                <div class="mt-6 flex gap-3">
                    <button onclick="resetSupplierFilters()" class="flex-1 py-2 bg-slate-100 text-slate-700 text-xs font-bold rounded-xl">Reset</button>
                    <button onclick="applySupplierFilters()" class="flex-1 py-2 bg-maroon text-white text-xs font-bold rounded-xl shadow-md">Apply</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== FEEDBACK MODALS ==================== -->

    <!-- ADD CONFIRMATION -->
    <div id="confirm-add-modal" class="fixed inset-0 z-[950] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl">
                <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4 text-amber-600"><i data-lucide="help-circle" class="w-8 h-8"></i></div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Register Supplier?</h3>
                <p class="text-sm text-slate-500 mb-6">Are you sure you want to add this supplier to the master list?</p>
                <div class="flex gap-3 justify-center">
                    <button type="button" onclick="toggleModal('confirm-add-modal', false)" class="px-6 py-2 bg-slate-100 text-slate-700 text-xs font-bold rounded-xl">No</button>
                    <button type="button" onclick="confirmAddSupplier()" class="px-8 py-2 bg-maroon text-white text-xs font-bold rounded-xl shadow-md">Yes, Register</button>
                </div>
            </div>
        </div>
    </div>

    <!-- EDIT CONFIRMATION -->
    <div id="confirm-edit-modal" class="fixed inset-0 z-[950] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4 text-blue-600"><i data-lucide="help-circle" class="w-8 h-8"></i></div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Update Information?</h3>
                <p class="text-sm text-slate-500 mb-6">Confirm changes to the supplier's security and contact credentials?</p>
                <div class="flex gap-3 justify-center">
                    <button type="button" onclick="toggleModal('confirm-edit-modal', false)" class="px-6 py-2 bg-slate-100 text-slate-700 text-xs font-bold rounded-xl">No</button>
                    <button type="button" onclick="confirmUpdateSupplier()" class="px-8 py-2 bg-blue-600 text-white text-xs font-bold rounded-xl shadow-md">Yes, Update</button>
                </div>
            </div>
        </div>
    </div>

    <!-- DELETE CONFIRMATION -->
    <div id="confirm-delete-modal" class="fixed inset-0 z-[950] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl border-2 border-red-500/10">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4 text-red-600"><i data-lucide="trash-2" class="w-8 h-8"></i></div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Terminate Relationship?</h3>
                <p class="text-sm text-slate-500 mb-6">This will permanently remove the supplier from the enterprise ledger. Proceed?</p>
                <div class="flex gap-3 justify-center">
                    <button type="button" onclick="toggleModal('confirm-delete-modal', false)" class="px-6 py-2 bg-slate-100 text-slate-700 text-xs font-bold rounded-xl">No, Keep</button>
                    <button type="button" onclick="confirmDeleteSupplier()" class="px-8 py-2 bg-red-600 text-white text-xs font-bold rounded-xl shadow-md">Yes, Delete</button>
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
                <h3 class="text-lg font-bold text-slate-800 mb-2">Registration Successful</h3>
                <p class="text-sm text-slate-500 mb-6">Supplier has been successfully added to the master list database.</p>
                <button type="button" onclick="toggleModal('success-add-modal', false)" class="px-10 py-2 bg-maroon text-white text-xs font-bold rounded-xl shadow-md">Confirm</button>
            </div>
        </div>
    </div>

    <div id="success-edit-modal" class="fixed inset-0 z-[1000] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4 text-blue-600"><i data-lucide="check-circle" class="w-8 h-8"></i></div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Record Updated</h3>
                <p class="text-sm text-slate-500 mb-6">Supplier details have been successfully recalibrated in the system.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== FEEDBACK MODALS ==================== -->

    <!-- ADD CONFIRMATION -->
    <div id="confirm-add-modal" class="fixed inset-0 z-[950] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl">
                <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4 text-amber-600"><i data-lucide="help-circle" class="w-8 h-8"></i></div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Register Supplier?</h3>
                <p class="text-sm text-slate-500 mb-6">Are you sure you want to add this supplier to the master list?</p>
                <div class="flex gap-3 justify-center">
                    <button type="button" onclick="toggleModal('confirm-add-modal', false)" class="px-6 py-2 bg-slate-100 text-slate-700 text-xs font-bold rounded-xl">No</button>
                    <button type="button" onclick="confirmAddSupplier()" class="px-8 py-2 bg-maroon text-white text-xs font-bold rounded-xl shadow-md">Yes, Register</button>
                </div>
            </div>
        </div>
    </div>

    <!-- EDIT CONFIRMATION -->
    <div id="confirm-edit-modal" class="fixed inset-0 z-[950] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4 text-blue-600"><i data-lucide="help-circle" class="w-8 h-8"></i></div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Update Information?</h3>
                <p class="text-sm text-slate-500 mb-6">Confirm changes to the supplier's security and contact credentials?</p>
                <div class="flex gap-3 justify-center">
                    <button type="button" onclick="toggleModal('confirm-edit-modal', false)" class="px-6 py-2 bg-slate-100 text-slate-700 text-xs font-bold rounded-xl">No</button>
                    <button type="button" onclick="confirmUpdateSupplier()" class="px-8 py-2 bg-blue-600 text-white text-xs font-bold rounded-xl shadow-md">Yes, Update</button>
                </div>
            </div>
        </div>
    </div>

    <!-- DELETE CONFIRMATION -->
    <div id="confirm-delete-modal" class="fixed inset-0 z-[950] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl border-2 border-red-500/10">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4 text-red-600"><i data-lucide="trash-2" class="w-8 h-8"></i></div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Terminate Relationship?</h3>
                <p class="text-sm text-slate-500 mb-6">This will permanently remove the supplier from the enterprise ledger. Proceed?</p>
                <div class="flex gap-3 justify-center">
                    <button type="button" onclick="toggleModal('confirm-delete-modal', false)" class="px-6 py-2 bg-slate-100 text-slate-700 text-xs font-bold rounded-xl">No, Keep</button>
                    <button type="button" onclick="confirmDeleteSupplier()" class="px-8 py-2 bg-red-600 text-white text-xs font-bold rounded-xl shadow-md">Yes, Delete</button>
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
                <h3 class="text-lg font-bold text-slate-800 mb-2">Registration Successful</h3>
                <p class="text-sm text-slate-500 mb-6">Supplier has been successfully added to the master list database.</p>
                <button type="button" onclick="toggleModal('success-add-modal', false)" class="px-10 py-2 bg-maroon text-white text-xs font-bold rounded-xl shadow-md">Confirm</button>
            </div>
        </div>
    </div>

    <div id="success-edit-modal" class="fixed inset-0 z-[1000] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4 text-blue-600"><i data-lucide="check-circle" class="w-8 h-8"></i></div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Record Updated</h3>
                <p class="text-sm text-slate-500 mb-6">Supplier details have been successfully recalibrated in the system.</p>
                <button type="button" onclick="toggleModal('success-edit-modal', false)" class="px-10 py-2 bg-maroon text-white text-xs font-bold rounded-xl shadow-md">Confirm</button>
            </div>
        </div>
    </div>

    <div id="success-delete-modal" class="fixed inset-0 z-[1000] overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-maroon-900/60 backdrop-blur-sm"></div>
            <div class="modal-animate-in relative bg-white rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4 text-red-600"><i data-lucide="check-circle" class="w-8 h-8"></i></div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Entry Purged</h3>
                <p class="text-sm text-slate-500 mb-6">The supplier has been successfully removed from the active database.</p>
                <button type="button" onclick="toggleModal('success-delete-modal', false)" class="px-10 py-2 bg-maroon text-white text-xs font-bold rounded-xl shadow-md">Confirm</button>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
    <script>
        window.supplierRoutes = {
            fetchData: '{{ route("admin.supplier-master.data") }}',
            fetchLifecycle: '{{ route("admin.supplier-master.lifecycle") }}',
            create: '{{ route("admin.supplier-master.create") }}',
            update: '{{ route("admin.supplier-master.update", ["id" => ":id"]) }}',
            delete: '{{ route("admin.supplier-master.delete", ["id" => ":id"]) }}',
            fetchLedgerItems: '{{ route("admin.masterlist.supplier.ledger-items", ["supplierId" => ":id"]) }}',
            fetchUnregisteredPurchaseOrders: '{{ route("admin.masterlist.supplier.unregistered-purchase-orders", ["supplierId" => ":id"]) }}',
            fetchHistory: '{{ route("admin.supplier-master.history", ["id" => ":id"]) }}',
            fetchLedger: '{{ route("admin.supplier-master.ledger", ["id" => ":id"]) }}',
            purchaseOrderDetail: '{{ route("admin.purchase-order.detail", ["poId" => ":id"]) }}',
            purchaseOrderPage: '{{ route("admin.purchase-order") }}',
        };
    </script>
    <!-- Leaflet JS for OpenStreetMap -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="{{ asset('js/supplier_master_list.js') }}?v={{ time() }}"></script>
@endpush

@include('partials.admin.admin_sidebar_navbar')
