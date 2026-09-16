@extends('partials.Warehouse_User.WareHouse-sidebar_navbar')

@section('sales_note_content')
<div class="space-y-6 animate-fade-in text-slate-800">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-amber-600">
                <i data-lucide="shopping-cart" class="w-4 h-4"></i>
                <span>Online Portal Order</span>
            </div>
            <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-maroon">{{ $note->sales_number }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $portalOrder->order_code ?? 'Online Order' }} · {{ $note->customer_name }}</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold uppercase text-emerald-700">{{ $note->status }}</span>
            <button type="button" onclick="history.back()" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 shadow-sm transition hover:border-maroon-200 hover:text-maroon">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                <span>Back</span>
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Customer</p>
            <p class="mt-1 text-sm font-extrabold text-slate-800">{{ $note->customer_name }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Order Date</p>
            <p class="mt-1 text-sm font-extrabold text-slate-800">{{ $note->order_date }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Prepared By</p>
            <p class="mt-1 text-sm font-extrabold text-slate-800">{{ $note->prepared_by ?: '---' }}</p>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
            <p class="text-[10px] font-bold uppercase tracking-wider text-amber-600">Net Total</p>
            <p class="mt-1 text-lg font-extrabold text-maroon">PHP {{ number_format((float) $note->net_total, 2) }}</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <div>
                <h2 class="text-sm font-extrabold text-slate-800">Sales Note Items</h2>
                <p class="text-[11px] text-slate-400">{{ $note->items->count() }} item(s)</p>
            </div>
            <span class="text-xs font-bold text-maroon">{{ $portalOrder->order_code ?? '' }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-xs">
                <thead class="bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Product Code</th>
                        <th class="px-5 py-3">Description</th>
                        <th class="px-5 py-3 text-center">Qty</th>
                        <th class="px-5 py-3 text-center">Unit</th>
                        <th class="px-5 py-3 text-right">Unit Price</th>
                        <th class="px-5 py-3 text-center">Discount</th>
                        <th class="px-5 py-3 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($note->items as $item)
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-3 font-bold text-maroon">{{ $item->product_code ?: '---' }}</td>
                            <td class="px-5 py-3 text-slate-700">{{ $item->description ?: '---' }}</td>
                            <td class="px-5 py-3 text-center font-bold">{{ number_format((float) $item->quantity, 0) }}</td>
                            <td class="px-5 py-3 text-center uppercase text-slate-500">{{ $item->oum ?: '---' }}</td>
                            <td class="px-5 py-3 text-right">PHP {{ number_format((float) $item->unit_price, 2) }}</td>
                            <td class="px-5 py-3 text-center">{{ number_format((float) $item->discount, 2) }}%</td>
                            <td class="px-5 py-3 text-right font-bold text-maroon">PHP {{ number_format((float) $item->subtotal, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-8 text-center text-slate-400">No Sales Note items found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="grid grid-cols-1 gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 text-sm md:grid-cols-3">
            <div><span class="text-slate-400">Gross:</span> <strong>PHP {{ number_format((float) $note->gross_total, 2) }}</strong></div>
            <div><span class="text-slate-400">Discount:</span> <strong>PHP {{ number_format((float) $note->total_discount, 2) }}</strong></div>
            <div class="md:text-right"><span class="text-slate-400">Net:</span> <strong class="text-maroon">PHP {{ number_format((float) $note->net_total, 2) }}</strong></div>
        </div>
    </div>

    @if($note->remarks)
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Remarks</p>
            <p class="mt-2 text-sm text-slate-700">{{ $note->remarks }}</p>
        </div>
    @endif
</div>
@endsection
