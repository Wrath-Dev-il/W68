<div class="catalog-page page-break catalog-page-item">
    <!-- Tiled Watermark Background Overlay -->
    <div class="watermark-container"></div>

    <div class="catalog-content">
        <!-- Header -->
        <div class="flex justify-between items-end border-b-4 border-maroon pb-2 mb-4">
            <div>
                <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tighter leading-none">W68 AUTO PARTS</h1>
                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-[0.3em]">Premium Parts Catalog</p>
            </div>
            <div class="text-right">
                <p class="text-[10px] font-black text-maroon uppercase">Page {{ $pageIndex + 1 }} of {{ $totalPages }}</p>
                <p class="text-[8px] text-slate-400 font-bold uppercase tracking-widest">{{ date('M d, Y') }}</p>
            </div>
        </div>

        <!-- Catalog Grid (12 items) -->
        <div class="catalog-grid">
            @foreach($products as $product)
                <div class="item-card">
                    <div class="item-image-container">
                        @php
                            $firstImg = null;
                            if ($product->Product_Picture) {
                                try {
                                    $imgs = is_string($product->Product_Picture) ? json_decode($product->Product_Picture, true) : $product->Product_Picture;
                                    $firstImg = is_array($imgs) && count($imgs) > 0 ? $imgs[0] : (is_string($imgs) ? $imgs : null);
                                } catch(\Exception $e) { $firstImg = null; }
                            }
                        @endphp
                        @if($firstImg)
                            <img src="{{ $firstImg }}" class="max-w-full max-h-full object-contain">
                        @else
                            <div class="text-slate-200 flex flex-col items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                                <span class="text-[7px] font-bold uppercase mt-1">No Image</span>
                            </div>
                        @endif
                    </div>
                    <div class="item-details">
                        <div class="detail-row">
                            <span class="detail-label">Item Name:</span>
                            <span class="font-black text-slate-800 uppercase text-[11px] truncate-text">{{ $product->product_code }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Part No#:</span>
                            <span class="font-mono text-slate-700 font-bold text-[9px]">{{ $product->part_number }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Application:</span>
                            <span class="text-slate-600 font-bold text-[9px] truncate-text">
                                {{ $product->application ?: $product->Application ?: '---' }}
                                @if($product->position ?: $product->Position)
                                    <span class="text-[7px] text-slate-400 ml-1">({{ $product->position ?: $product->Position }})</span>
                                @endif
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Brand:</span>
                            <span class="text-slate-500 uppercase font-bold text-[9px]">{{ $product->category }}</span>
                        </div>
                        <div class="mt-1 pt-1 border-t border-dashed border-slate-200 flex justify-between items-center">
                            <span class="detail-label">Price:</span>
                            <span class="price-text">₱{{ number_format($product->selling_price, 2) }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Footer -->
        <div class="mt-4 pt-4 border-t-2 border-slate-100 text-center">
            <p class="text-[9px] text-slate-400 font-black uppercase tracking-[0.5em]">W68 AUTO PARTS • ENTERPRISE INVENTORY SYSTEM</p>
        </div>
    </div>
</div>