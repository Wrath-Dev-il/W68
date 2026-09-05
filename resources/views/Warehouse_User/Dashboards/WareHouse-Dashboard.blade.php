@php
    $sidebarAvatarUrl = $sidebarAvatarUrl ?? '';
    $sidebarFullName = $sidebarFullName ?? 'Warehouse User';
    $sidebarRoleLabel = $sidebarRoleLabel ?? 'Warehouse User';
@endphp

@push('styles')
<style>
    .dashboard-main {
        min-height: 100vh;
    }
    .icon-bg {
        background: rgba(255, 255, 255, 0.1);
    }
    .action-btn {
        transition: all 0.2s ease;
    }
    .action-btn:hover {
        background: #EAB308 !important;
        color: #3D0C11 !important;
        border-color: #EAB308 !important;
    }
</style>
@endpush

@section('warehouse_dashboard_content')

<div class="dashboard-main p-6 bg-gray-50">

    <!-- Page Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-extrabold text-maroon-900">Warehouse Dashboard</h1>
            <p class="text-sm text-gray-500 mt-1">Manage inventory, junk items, and product conversions.</p>
        </div>
    </div>

    <!-- Three-Card Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        <!-- Card 1: Inventory Adjustment -->
        <a href="{{ route('admin.inv-adjust') }}" class="group block bg-gradient-to-br from-maroon-900 to-maroon-950 rounded-2xl shadow-xl p-6 hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 border border-maroon-800">
            <div class="flex flex-col items-center text-center space-y-4">
                <div class="icon-bg p-5 rounded-2xl">
                    <i data-lucide="sliders-horizontal" class="w-12 h-12 text-goldlining-400"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white">Inventory Adjustment</h3>
                    <p class="text-gray-300 text-sm mt-1">Adjust stock levels, correct discrepancies, manage inventory counts.</p>
                </div>
                <div class="action-btn inline-flex items-center space-x-2 bg-goldlining-500 text-maroon-950 font-bold text-sm px-5 py-2.5 rounded-xl group-hover:bg-white transition-colors">
                    <span>Go To</span>
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </div>
            </div>
        </a>

        <!-- Card 2: Sale's Junk -->
        <a href="{{ route('admin.sales-junk') }}" class="group block bg-gradient-to-br from-maroon-900 to-maroon-950 rounded-2xl shadow-xl p-6 hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 border border-maroon-800">
            <div class="flex flex-col items-center text-center space-y-4">
                <div class="icon-bg p-5 rounded-2xl">
                    <i data-lucide="archive" class="w-12 h-12 text-goldlining-400"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white">Sale's Junk</h3>
                    <p class="text-gray-300 text-sm mt-1">View and restore returned items marked as junk from sales orders.</p>
                </div>
                <div class="action-btn inline-flex items-center space-x-2 bg-goldlining-500 text-maroon-950 font-bold text-sm px-5 py-2.5 rounded-xl group-hover:bg-white transition-colors">
                    <span>Go To</span>
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </div>
            </div>
        </a>

        <!-- Card 3: Convert Products -->
        <a href="#" class="group block bg-gradient-to-br from-maroon-900 to-maroon-950 rounded-2xl shadow-xl p-6 hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 border border-maroon-800">
            <div class="flex flex-col items-center text-center space-y-4">
                <div class="icon-bg p-5 rounded-2xl">
                    <i data-lucide="shuffle" class="w-12 h-12 text-goldlining-400"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white">Convert Products</h3>
                    <p class="text-gray-300 text-sm mt-1">Transform products between categories or batch-convert item types.</p>
                </div>
                <div class="action-btn inline-flex items-center space-x-2 bg-goldlining-500 text-maroon-950 font-bold text-sm px-5 py-2.5 rounded-xl group-hover:bg-white transition-colors">
                    <span>Go To</span>
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </div>
            </div>
        </a>

    </div>

</div>

@endsection

@include('partials.Warehouse_User.WareHouse-sidebar_navbar')
