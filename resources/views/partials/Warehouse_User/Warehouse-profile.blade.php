@php
    $sidebarAvatarUrl = $sidebarAvatarUrl ?? '';
    $sidebarFullName = $sidebarFullName ?? 'Warehouse User';
    $sidebarRoleLabel = $sidebarRoleLabel ?? 'Warehouse User';
@endphp

@push('styles')
<style>
    .profile-card {
        background: linear-gradient(135deg, #3D0C11 0%, #5a131c 100%);
    }
    .stat-card {
        transition: all 0.2s ease;
    }
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
</style>
@endpush

@section('profile_content')

<div class="p-6 bg-gray-50 min-h-screen">

    <div class="max-w-5xl mx-auto space-y-6">

        <!-- Profile Header Card -->
        <div class="profile-card rounded-2xl shadow-xl p-8 text-white">
            <div class="flex flex-col md:flex-row items-center md:items-start gap-6">
                <div class="relative">
                    <div class="w-28 h-28 rounded-full border-4 border-goldlining-400 overflow-hidden bg-maroon-800 flex items-center justify-center shadow-lg">
                        @if($profilePictureUrl)
                            <img src="{{ $profilePictureUrl }}" alt="Profile" class="w-full h-full object-cover">
                        @else
                            <i data-lucide="user" class="w-12 h-12 text-goldlining-400"></i>
                        @endif
                    </div>
                    <label for="profile-pic-input" class="absolute bottom-0 right-0 bg-goldlining-500 text-maroon-950 rounded-full p-2 cursor-pointer shadow-lg hover:bg-goldlining-400 transition-colors">
                        <i data-lucide="camera" class="w-4 h-4"></i>
                    </label>
                    <input type="file" id="profile-pic-input" accept="image/*" class="hidden">
                </div>
                <div class="text-center md:text-left flex-1">
                    <h1 class="text-2xl font-extrabold">{{ $profileUser->User_First_Name ?? '' }} {{ $profileUser->User_Middle_Name ?? '' }} {{ $profileUser->User_Last_Name ?? '' }}</h1>
                    <p class="text-goldlining-400 font-semibold text-sm mt-1">{{ $accountTypeLabel }}</p>
                    <p class="text-gray-300 text-xs mt-2">User ID: {{ $profileUser->User_ID ?? 'N/A' }}</p>
                </div>
            </div>
        </div>

        <!-- Edit Profile Form -->
        <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
            <h2 class="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2">
                <i data-lucide="edit" class="w-5 h-5 text-maroon-700"></i>
                Edit Profile Information
            </h2>
            <form id="profile-form" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">First Name</label>
                    <input type="text" name="User_First_Name" value="{{ $profileUser->User_First_Name ?? '' }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-maroon-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Middle Name</label>
                    <input type="text" name="User_Middle_Name" value="{{ $profileUser->User_Middle_Name ?? '' }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-maroon-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Last Name</label>
                    <input type="text" name="User_Last_Name" value="{{ $profileUser->User_Last_Name ?? '' }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-maroon-500 focus:border-transparent">
                </div>
                <div class="md:col-span-3 flex justify-end">
                    <button type="submit" class="bg-maroon-900 text-white text-sm font-bold px-6 py-2.5 rounded-xl hover:bg-maroon-950 transition-colors shadow-md flex items-center gap-2">
                        <i data-lucide="save" class="w-4 h-4"></i>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>

        <!-- Login Statistics -->
        <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
            <h2 class="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2">
                <i data-lucide="activity" class="w-5 h-5 text-maroon-700"></i>
                Login Activity
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="stat-card bg-gradient-to-br from-maroon-50 to-maroon-100 rounded-xl p-5 border border-maroon-200">
                    <p class="text-xs text-maroon-700 font-semibold">Total Logins</p>
                    <p class="text-2xl font-extrabold text-maroon-900 mt-1">{{ $totalLoginCount }}</p>
                </div>
                <div class="stat-card bg-gradient-to-br from-amber-50 to-amber-100 rounded-xl p-5 border border-amber-200">
                    <p class="text-xs text-amber-700 font-semibold">This Week</p>
                    <p class="text-2xl font-extrabold text-amber-900 mt-1">{{ $weeklyLoginCount }}</p>
                </div>
                <div class="stat-card bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-xl p-5 border border-emerald-200">
                    <p class="text-xs text-emerald-700 font-semibold">Last Login</p>
                    <p class="text-sm font-bold text-emerald-900 mt-1">{{ $latestLoginAt }}</p>
                </div>
            </div>
        </div>

    </div>

</div>

@endsection

@push('scripts')
<script>
document.getElementById('profile-pic-input')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('profile_picture', file);
    formData.append('_token', '{{ csrf_token() }}');
    fetch('{{ route("warehouse.profile.picture.update") }}', {
        method: 'POST',
        body: formData,
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Upload failed');
        }
    })
    .catch(() => alert('Upload failed'));
});

document.getElementById('profile-form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('_token', '{{ csrf_token() }}');
    fetch('{{ route("warehouse.profile.update") }}', {
        method: 'POST',
        body: formData,
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Update failed');
        }
    })
    .catch(() => alert('Update failed'));
});
</script>
@endpush

@include('partials.Warehouse_User.WareHouse-sidebar_navbar')
