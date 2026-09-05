<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Catalog - W68 AUTO PARTS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        maroon: {
                            DEFAULT: '#4A0A15',
                            hover: '#3a0810',
                            900: '#4a0612',
                            800: '#82202f',
                        },
                        gold: {
                            DEFAULT: '#FFC72C',
                        },
                        goldlining: {
                            500: '#eab308',
                        }
                    }
                }
            }
        }
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        @media print {
            @page {
                size: letter; /* 8.5 x 11 in */
                margin: 0;
            }
            body {
                background: white !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .no-print,
            #catalog-container,
            #pdf-worker-container,
            #pdf-progress-modal {
                display: none !important;
            }
            #print-catalog {
                display: block !important;
            }
            .page-break {
                page-break-after: always;
                break-after: page;
            }
        }
        
        #print-catalog {
            display: none;
        }

        .catalog-page {
            width: 215.9mm; /* 8.5in */
            height: 279.4mm; /* 11in */
            padding: 8mm 8mm 15mm 8mm; /* Added more bottom padding */
            margin: 10mm auto;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            page-break-after: always;
        }

        @media print {
            .catalog-page {
                margin: 0;
                box-shadow: none;
                height: 279.4mm; /* Force exact Letter height */
                padding: 8mm 8mm 15mm 8mm;
            }
        }

        /* Optimized Watermark Overlay */
        .watermark-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0; /* Move behind content */
            opacity: 0.12;
            overflow: hidden;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='200' height='150'><text x='0' y='80' fill='%23475569' font-family='sans-serif' font-weight='900' font-size='14' transform='rotate(-25 100 75)'>W68 AUTO PARTS</text></svg>");
            background-repeat: repeat;
        }

        .catalog-content {
            position: relative;
            z-index: 10;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .catalog-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px; /* Slightly tighter gap */
            margin-bottom: auto;
        }

        .item-card {
            border: 3px solid black; 
            display: flex;
            height: 36mm; /* Reduced height to fit 6 rows with gaps */
            overflow: hidden;
            background: white;
            box-sizing: border-box;
            border-radius: 2px;
        }

        .item-image-container {
            width: 38%;
            height: 100%;
            border-right: 3px solid black;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 5px;
            background: white;
        }

        .item-details {
            width: 62%;
            padding: 6px 8px; /* Slightly more compact vertical padding */
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: rgba(255, 255, 255, 0.3);
        }

        .detail-row {
            margin-bottom: 2px;
            font-size: 11px;
            line-height: 1.1;
            color: black;
        }

        .detail-label {
            font-weight: 900;
            color: black;
            text-transform: uppercase;
            font-size: 9px;
            display: inline-block;
            width: 75px;
        }

        .price-text {
            color: #b91c1c;
            font-weight: 900;
            font-size: 14px;
        }

        .truncate-text {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            max-height: 2.2em; /* Ensure it doesn't push price down */
        }

        /* Progress Animations */
        #pdf-progress-bar {
            background: linear-gradient(90deg, #b91c1c 0%, #ef4444 50%, #b91c1c 100%);
            background-size: 200% 100%;
            animation: shimmer 2s infinite linear;
        }

        @keyframes shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        .progress-animation-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background-image: linear-gradient(
                -45deg, 
                rgba(255, 255, 255, 0.3) 25%, 
                transparent 25%, 
                transparent 50%, 
                rgba(255, 255, 255, 0.3) 50%, 
                rgba(255, 255, 255, 0.3) 75%, 
                transparent 75%, 
                transparent
            );
            background-size: 50px 50px;
            animation: move-stripes 1s linear infinite;
        }

        @keyframes move-stripes {
            0% { background-position: 0 0; }
            100% { background-position: 50px 50px; }
        }
    </style>
</head>
<body class="bg-slate-200 font-sans">
    @include('partials.global.w68-loader')

    
    <div class="fixed top-5 left-5 z-[250] no-print">
        <a href="{{ route('special.prod-master') }}" class="px-6 py-3 bg-gold text-maroon-900 font-black rounded-2xl shadow-2xl hover:bg-goldlining-500 transition-all flex items-center gap-3 border border-goldlining-500">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            BACK TO MASTERLIST
        </a>
    </div>

    <div class="fixed top-5 right-5 z-[110] no-print">
        <button onclick="window.print()" class="px-8 py-3 bg-maroon-900 text-gold font-black rounded-2xl shadow-2xl hover:bg-maroon-800 transition-all flex items-center gap-3 border-2 border-goldlining-500">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            PRINT CATALOG
        </button>
    </div>

    <div id="catalog-container">
    @php
        // Limit preview to first 120 items
        $previewProducts = $products->take(120);
        $previewChunks = $previewProducts->chunk(12);
        $previewTotalPages = ceil($previewProducts->count() / 12);
        
        // Full catalog for printing
        $allChunks = $products->chunk(12);
        $totalPrintPages = count($allChunks);
        $totalPages = $totalPrintPages; // Added for JS compatibility
    @endphp

    @if($totalCount > 120)
        <div class="no-print bg-amber-50 border-l-4 border-amber-400 p-4 mb-6 rounded-r-xl shadow-sm mx-auto max-w-[215.9mm]">
            <div class="flex items-center">
                <i data-lucide="alert-circle" class="w-5 h-5 text-amber-500 mr-3"></i>
                <div>
                    <p class="text-sm text-amber-800 font-bold uppercase tracking-tight">Large Catalog Detected</p>
                    <p class="text-xs text-amber-700">Showing first 120 items for preview. Use the <b>PRINT CATALOG</b> button to generate the full report for all {{ number_format($totalCount) }} items.</p>
                </div>
            </div>
        </div>
    @endif

    @foreach($previewChunks as $pageIndex => $productChunk)
        <div class="catalog-page catalog-page-item">
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
                        <p class="text-[14px] text-black font-bold uppercase tracking-widest">{{ date('M d, Y') }}</p>
                    </div>
                </div>

                <!-- Catalog Grid (12 items) -->
                <div class="catalog-grid">
                    @foreach($productChunk as $product)
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
                                    <span class="font-black text-black uppercase text-[11px] truncate-text">{{ $product->product_code }}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Part No#:</span>
                                    <span class="font-mono text-black font-bold text-[9px]">{{ $product->part_number }}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Application:</span>
                                    <span class="text-black font-bold text-[9px] truncate-text leading-tight">
                                        {{ $product->application ?: $product->Application ?: '---' }}
                                        @if($product->position ?: $product->Position)
                                            <span class="text-[8px] text-slate-500 ml-1">({{ $product->position ?: $product->Position }})</span>
                                        @endif
                                    </span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Brand:</span>
                                    <span class="text-black uppercase font-bold text-[9px]">{{ $product->category }}</span>
                                </div>
                                <div class="mt-1 pt-1 border-t border-dashed border-slate-300 flex justify-between items-center">
                                    <span class="detail-label">Price:</span>
                                    <span class="price-text">₱{{ number_format($product->selling_price, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Footer -->
                <div class="mt-auto pt-4 border-t-2 border-slate-300 flex justify-between items-center">
                    <p class="text-[12px] text-black font-bold uppercase tracking-widest">PRODUCT CATALOG PREVIEW</p>
                    <p class="text-[12px] text-black font-bold uppercase">PAGE {{ $pageIndex + 1 }} OF {{ $previewTotalPages }}</p>
                </div>
            </div>
        </div>
    @endforeach
    </div>

    <!-- Print-Only Catalog (Hidden on Screen, Visible on Print) -->
    <div id="print-catalog">
        @foreach($allChunks as $pageIndex => $productChunk)
            <div class="catalog-page {{ !$loop->last ? 'page-break' : '' }}">
                <div class="catalog-content">
                    <!-- Header -->
                    <div class="flex justify-between items-end border-b-4 border-maroon pb-2 mb-4">
                        <div>
                            <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tighter leading-none">W68 AUTO PARTS</h1>
                            <p class="text-[10px] text-slate-500 font-bold uppercase tracking-[0.3em]">Premium Parts Catalog</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[14px] text-black font-bold uppercase tracking-widest">{{ date('M d, Y') }}</p>
                        </div>
                    </div>

                    <!-- Catalog Grid (12 items) -->
                    <div class="catalog-grid">
                        @foreach($productChunk as $product)
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
                                        <span class="font-black text-black uppercase text-[11px] truncate-text">{{ $product->product_code }}</span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Part No#:</span>
                                        <span class="font-mono text-black font-bold text-[9px]">{{ $product->part_number }}</span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Application:</span>
                                        <span class="text-black font-bold text-[9px] truncate-text leading-tight">
                                            {{ $product->application ?: $product->Application ?: '---' }}
                                            @if($product->position ?: $product->Position)
                                                <span class="text-[8px] text-slate-500 ml-1">({{ $product->position ?: $product->Position }})</span>
                                            @endif
                                        </span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Brand:</span>
                                        <span class="text-black uppercase font-bold text-[9px]">{{ $product->category }}</span>
                                    </div>
                                    <div class="mt-1 pt-1 border-t border-dashed border-slate-300 flex justify-between items-center">
                                        <span class="detail-label">Price:</span>
                                        <span class="price-text">₱{{ number_format($product->selling_price, 2) }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Footer -->
                    <div class="mt-auto pt-4 border-t-2 border-slate-300 flex justify-between items-center">
                        <p class="text-[12px] text-black font-bold uppercase tracking-widest">PRODUCT CATALOG</p>
                        <p class="text-[12px] text-black font-bold uppercase">PAGE {{ $pageIndex + 1 }} OF {{ $totalPrintPages }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Background Worker Container (Hidden) -->
    <div id="pdf-worker-container" style="position: fixed; top: -10000px; left: -10000px; width: 215.9mm;"></div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>

