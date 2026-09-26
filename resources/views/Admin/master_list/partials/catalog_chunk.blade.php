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
                            $pic = $product->Product_Picture ?? null;
                            if (!empty($pic)) {
                                try {
                                    if (is_array($pic)) {
                                        $firstImg = count($pic) > 0 ? $pic[0] : null;
                                    } elseif (is_string($pic)) {
                                        $trimmed = trim($pic);
                                        if (str_starts_with($trimmed, 'data:image/') || preg_match('#^https?://#i', $trimmed)) {
                                            $firstImg = $trimmed;
                                        } elseif (str_starts_with($trimmed, '/') || preg_match('/\.(png|jpg|jpeg|webp|gif|bmp|svg)(\?.*)?$/i', $trimmed)) {
                                            $firstImg = url('/' . ltrim($trimmed, '/'));
                                        } elseif ($trimmed !== '' && ($trimmed[0] === '[' || $trimmed[0] === '{')) {
                                            $decoded = json_decode($trimmed, true);
                                            if (is_array($decoded) && count($decoded) > 0) {
                                                $first = $decoded[0];
                                                if (is_string($first)) {
                                                    if (str_starts_with($first, 'data:image/') || preg_match('#^https?://#i', $first)) {
                                                        $firstImg = $first;
                                                    } elseif (preg_match('/\.(png|jpg|jpeg|webp|gif|bmp|svg)(\?.*)?$/i', $first)) {
                                                        $firstImg = url('/' . ltrim($first, '/'));
                                                    }
                                                }
                                                unset($first, $decoded);
                                            }
                                        } elseif (strlen($pic) >= 8 && substr($pic, 0, 8) === "\x89PNG\r\n\x1a\n") {
                                            $firstImg = 'data:image/png;base64,' . base64_encode($pic);
                                        } elseif (strlen($pic) >= 3 && substr($pic, 0, 3) === "\xFF\xD8\xFF") {
                                            $firstImg = 'data:image/jpeg;base64,' . base64_encode($pic);
                                        }
                                        unset($trimmed);
                                    }
                                } catch(\Throwable $e) { $firstImg = null; }
                            }
                            unset($pic);
                        @endphp
                        @if($firstImg)
                            <img src="{!! $firstImg !!}" class="max-w-full max-h-full object-contain">
                        @else
                            <div class="text-slate-200 flex flex-col items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                                <span class="text-[7px] font-bold uppercase mt-1">No Image</span>
                            </div>
                        @endif
                        @php unset($firstImg); @endphp
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
            @php
                $products = null;
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            @endphp
        </div>

        <!-- Footer -->
        <div class="mt-4 pt-4 border-t-2 border-slate-100 text-center">
            <p class="text-[9px] text-slate-400 font-black uppercase tracking-[0.5em]">W68 AUTO PARTS • ENTERPRISE INVENTORY SYSTEM</p>
        </div>
    </div>
</div>