@php
    $user = session('user');
    $accountType = is_array($user) ? ($user['account_type'] ?? null) : ($user->account_type ?? null);
@endphp

@section('sales_note_content')
<style>
    .price-input { width: 140px; padding: 8px 12px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 14px; text-align: right; outline: none; transition: all 0.2s; }
    .price-input:focus { border-color: #800000; box-shadow: 0 0 0 3px rgba(128,0,0,0.1); }
    .price-input.saved { border-color: #10b981; background: #f0fdf4; }
</style>

<div class="max-w-7xl mx-auto py-8 px-6 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-maroon tracking-tight">Online Price Editor</h1>
            <p class="text-sm text-slate-500">Input online prices for selected items</p>
        </div>
        <button onclick="submitPrices()" class="px-6 py-3 bg-maroon text-white text-xs font-bold rounded-xl shadow-lg hover:bg-maroon-800 transition-all uppercase tracking-widest flex items-center space-x-2">
            <i data-lucide="check-circle" class="w-4 h-4"></i>
            <span>Submit Prices</span>
        </button>
    </div>

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 bg-slate-50/50">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-widest">{{ count($items) }} item(s) to update</span>
        </div>
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="p-3 px-5 text-[10px] font-bold text-slate-500 uppercase tracking-widest">SN #</th>
                        <th class="p-3 px-5 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Item Code</th>
                        <th class="p-3 px-5 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Description</th>
                        <th class="p-3 px-5 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Qty</th>
                        <th class="p-3 px-5 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Unit</th>
                        <th class="p-3 px-5 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-right">Online Price</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @foreach($items as $item)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="p-3 px-5 font-mono text-xs text-maroon font-bold">{{ $item['sales_number'] }}</td>
                        <td class="p-3 px-5 text-slate-700">{{ $item['product_code'] }}</td>
                        <td class="p-3 px-5 text-slate-600">{{ $item['description'] }}</td>
                        <td class="p-3 px-5 text-center text-slate-600">{{ $item['quantity'] }}</td>
                        <td class="p-3 px-5 text-center text-slate-600">{{ $item['oum'] }}</td>
                        <td class="p-3 px-5 text-right">
                            <input type="number" step="0.01" min="0"
                                   data-product-id="{{ $item['product_id'] }}"
                                   value="{{ $item['price_online'] ? number_format($item['price_online'], 2, '.', '') : '' }}"
                                   placeholder="0.00"
                                   class="price-input"
                                   oninput="markUnsaved(this)"
                                   onchange="markUnsaved(this)">
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <input type="hidden" id="note-ids" value="{{ $noteIds }}">
    <input type="hidden" id="report-type" value="{{ $reportType }}">
    <input type="hidden" id="range-type" value="{{ $rangeType ?? 'annual' }}">
    <input type="hidden" id="from-date" value="{{ $fromDate ?? date('Y') }}">
    <input type="hidden" id="to-date" value="{{ $toDate ?? date('Y') + 1 }}">

    <form id="redirect-form" method="GET" action="{{ route('regular.sales-note.report-redirect') }}" style="display:none">
        <input type="hidden" name="ids" id="redirect-ids">
        <input type="hidden" name="type" id="redirect-type">
        <input type="hidden" name="range_type" id="redirect-range-type">
        <input type="hidden" name="from_date" id="redirect-from-date">
        <input type="hidden" name="to_date" id="redirect-to-date">
        <input type="hidden" name="online_prices" value="1">
    </form>
</div>

<script>
window.submitPrices = async function() {
    const inputs = document.querySelectorAll('.price-input');
    const prices = {};
    inputs.forEach(inp => {
        const pid = inp.dataset.productId;
        const val = parseFloat(inp.value);
        if (!isNaN(val) && val >= 0) prices[pid] = val;
    });
    if (Object.keys(prices).length === 0) { alert('Please enter at least one online price.'); return; }
    const btn = document.querySelector('button[onclick="submitPrices()"]');
    btn.disabled = true; btn.innerHTML = '<span>Saving...</span>';
    try {
        const res = await fetch('{{ route("regular.sales-note.online-prices.save") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            body: JSON.stringify({ prices }),
        });
        const data = await res.json();
        if (data.success) {
            inputs.forEach(inp => inp.classList.add('saved'));
            document.getElementById('redirect-ids').value = document.getElementById('note-ids').value;
            document.getElementById('redirect-type').value = document.getElementById('report-type').value;
            document.getElementById('redirect-range-type').value = document.getElementById('range-type').value;
            document.getElementById('redirect-from-date').value = document.getElementById('from-date').value;
            document.getElementById('redirect-to-date').value = document.getElementById('to-date').value;
            document.getElementById('redirect-form').submit();
        } else {
            alert(data.message || 'Failed to save prices.');
            btn.disabled = false; btn.innerHTML = '<i data-lucide="check-circle" class="w-4 h-4"></i><span>Submit Prices</span>';
        }
    } catch (e) {
        console.error(e); alert('An unexpected error occurred.');
        btn.disabled = false; btn.innerHTML = '<i data-lucide="check-circle" class="w-4 h-4"></i><span>Submit Prices</span>';
    }
};
window.markUnsaved = function(el) { el.classList.remove('saved'); };
document.addEventListener('DOMContentLoaded', function() { if (typeof lucide !== 'undefined') lucide.createIcons(); });
</script>
@endsection
@include('partials.user_account.user_sidebar_navbar')


