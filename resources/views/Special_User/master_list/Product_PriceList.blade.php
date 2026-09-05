<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Price List Content - W68 AUTO PARTS</title>
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
        @page {
            size: letter;
            margin: 0.8cm;
        }
        /* Watermark - extends beyond content area to cover margins */
        .print-watermark {
            position: fixed;
            top: -2cm;
            left: -2cm;
            width: calc(100vw + 4cm);
            height: calc(100vh + 4cm);
            pointer-events: none;
            z-index: 1;
            display: none;
            background-image: url("data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPScxODAnIGhlaWdodD0nMTIwJz48dGV4dCB4PScyJyB5PSc3MCcgZmlsbD0nIzQ3NTU2OScgZm9udC1mYW1pbHk9J3NhbnMtc2VyaWYnIGZvbnQtd2VpZ2h0PSc5MDAnIGZvbnQtc2l6ZT0nMTInIHRyYW5zZm9ybT0ncm90YXRlKC0yNSA5MCA2MCknIG9wYWNpdHk9JzAuMTInPlc2OCBBVVRPIFBBUlRTPC90ZXh0Pjwvc3ZnPg==");
            background-repeat: repeat;
            background-size: 180px 120px;
        }
        
        @media print {
            body {
                margin: 0 !important;
                padding: 0 !important;
                box-sizing: border-box !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                background: none !important;
                background-color: white !important;
            }
            .print-watermark {
                display: block !important;
            }
            .no-print,
            #report-container,
            #pdf-worker-container,
            #pdf-progress-modal {
                display: none !important;
            }
            
            #print-report {
                display: block !important;
            }
            
            /* ── Single continuous table — browser handles pagination ── */
            #print-report .print-table {
                width: 100%;
                table-layout: fixed !important;
                border-collapse: collapse;
                border: none;
            }
            #print-report tbody td {
                border: 2px solid black;
                padding: 4px 6px !important;
                font-size: 14px !important;
                font-weight: 700;
                color: black !important;
                line-height: 1.2 !important;
            }
            #print-report tbody .desc-header {
                font-size: 18px !important;
                padding: 6px 10px !important;
                font-weight: 900;
                text-transform: uppercase;
                color: black !important;
            }
            #print-report tbody .price-col {
                font-size: 14px !important;
                font-weight: 900;
                color: #1e3a8a !important;
                text-align: right;
                white-space: nowrap;
            }
            .print-page-item {
                page-break-after: always;
                width: 100%;
                box-sizing: border-box;
            }
            .print-page-item:last-child {
                page-break-after: auto;
            }
        }
        
        .desc-header {
            font-size: 18px !important;
            padding: 10px 12px !important;
        }
        
        .report-page {
            width: 215.9mm;
            min-height: 279.4mm;
            padding: 12mm 10mm;
            margin: 10mm auto;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            page-break-after: auto;
        }

        /* Print-Only Report (hidden on screen) */
        #print-report {
            display: none;
        }

        .print-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            border-bottom: 4px solid #94a3b8;
            padding-bottom: 3px;
        }
        .print-header h1 {
            font-size: 20px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: -0.05em;
            line-height: 1;
            color: #1e293b;
        }
        .print-header .subtitle {
            font-size: 8px;
            color: #64748b;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3em;
        }
        .print-header .date {
            font-size: 10px;
            color: black;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        .print-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 2px solid black;
            padding-top: 2mm;
        }
        .print-footer p {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .page-counter::after {
            content: counter(page);
        }

        /* Optimized Watermark Overlay */
        .watermark-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 100;
            opacity: 0.12;
            overflow: hidden;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='200' height='150'><text x='0' y='80' fill='%23475569' font-family='sans-serif' font-weight='900' font-size='14' transform='rotate(-25 100 75)'>W68 AUTO PARTS</text></svg>");
            background-repeat: repeat;
        }

        .report-content {
            position: relative;
            z-index: 10;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: auto;
            border: 2px solid black; /* External border */
        }

        thead {
            display: table-header-group;
        }

        th {
            background-color: #f8fafc;
            color: #1e293b;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.05em;
            padding: 10px 8px;
            border: 1.5px solid #e2e8f0;
            text-align: left;
        }

        td {
            padding: 8px;
            border: 2px solid black; /* Increased visibility */
            font-size: 14px; /* Increased font size */
            color: #334155;
            line-height: 1.4;
        }

        tr:nth-child(even) {
            background-color: rgba(248, 250, 252, 0.4);
        }

        .price-col {
            font-weight: 900;
            color: #1e3a8a; /* Dark Blue */
            text-align: right;
            white-space: nowrap;
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
            PRINT LIST
        </button>
    </div>

    <div id="report-container">
    @php
        // Sort products by description, category, and then application
        $sortedProducts = $products->sort(function($a, $b) {
            $descDiff = strcmp(trim(strtoupper($a->description)), trim(strtoupper($b->description)));
            if ($descDiff !== 0) return $descDiff;
            
            $catDiff = strcmp(trim(strtoupper($a->category)), trim(strtoupper($b->category)));
            if ($catDiff !== 0) return $catDiff;

            return strcmp(trim(strtoupper($a->application)), trim(strtoupper($b->application)));
        });

        // Helper for dynamic footer description list
        $groupedProducts = $sortedProducts->groupBy(function($item) {
            return trim(strtoupper($item->description ?: 'NO DESCRIPTION'));
        });

        $previewProducts = $sortedProducts->take(180);

        $previewItems = $previewProducts->values();
        $previewTotalItems = $previewItems->count();
        $previewPerPage = (int) max(12, ceil($previewTotalItems / 5));
        $previewChunks = $previewItems->chunk($previewPerPage);
        $previewTotalPages = $previewChunks->count();

        $printItemsPerPage = 20;
        $printChunks = $sortedProducts->chunk($printItemsPerPage);
        $printTotalPages = $printChunks->count();
    @endphp

    @if($totalCount > 180)
        <div class="no-print bg-amber-50 border-l-4 border-amber-400 p-4 mb-6 rounded-r-xl shadow-sm mx-auto max-w-[215.9mm]">
            <div class="flex items-center">
                <i data-lucide="alert-circle" class="w-5 h-5 text-amber-500 mr-3"></i>
                <div>
                    <p class="text-sm text-amber-800 font-bold uppercase tracking-tight">Large Dataset Detected</p>
                    <p class="text-xs text-amber-700">Showing first 180 items for preview. Use the <b>PRINT LIST</b> button to generate the full report for all {{ number_format($totalCount) }} items.</p>
                </div>
            </div>
        </div>
    @endif

    @foreach($previewChunks as $pageIdx => $pageProducts)
    <div class="report-page catalog-page-item" data-page="{{ $pageIdx + 1 }}" style="min-height: auto; height: auto;">
        <!-- Tiled Watermark Background Overlay -->
        <div class="watermark-container"></div>

        <div class="report-content">
            <!-- Header -->
            <div style="display:flex;justify-content:space-between;align-items:flex-end;border-bottom:2px solid black;padding-bottom:3px;margin-bottom:24px;">
                <div>
                    <div style="font-family:'Arial Black',Arial,sans-serif;font-size:18px;font-weight:900;text-transform:uppercase;line-height:1;color:black;letter-spacing:-0.02em;">W68 AUTOPARTS &amp; SERVICE CENTER</div>
                    <div style="font-family:'Arial Black',Arial,sans-serif;font-size:18px;font-weight:900;text-transform:uppercase;line-height:1;color:black;letter-spacing:0.05em;">PRICE LIST</div>
                </div>
                <div style="font-family:'Arial Black',Arial,sans-serif;font-size:18px;font-weight:900;text-transform:uppercase;color:black;line-height:1;">{{ strtoupper(date('M d, Y')) }}</div>
            </div>

            <!-- Price List Table -->
            <table class="mb-4">
                <tbody>
                    @php $lastDesc = null; @endphp
                    @foreach($pageProducts as $product)
                        @php $currentDesc = trim(strtoupper($product->description ?: 'NO DESCRIPTION')); @endphp
                        
                        @if($currentDesc !== $lastDesc)
                            <!-- Group Description Header Row -->
                            <tr>
                                <td colspan="5" class="desc-header bg-slate-100 text-black font-black uppercase py-2 px-3 border-[2px] border-black">
                                    {{ $currentDesc }}
                                </td>
                            </tr>
                            @php $lastDesc = $currentDesc; @endphp
                        @endif

                        <!-- Details Row -->
                        <tr>
                            <td class="font-bold text-slate-800">{{ $product->product_code }}</td>
                            <td class="font-mono text-black font-bold">{{ $product->part_number ?: '---' }}</td>
                            <td>
                                <div class="font-bold text-slate-800">{{ $product->application ?: $product->Application ?: '---' }}</div>
                                @php $pos = $product->position ?: $product->Position; @endphp
                                @if($pos)
                                    <div class="text-[9px] text-slate-500 font-bold uppercase mt-1 border-t border-slate-100 pt-0.5">
                                        <span class="text-[7px] text-slate-400 mr-1">POS:</span>{{ $pos }}
                                    </div>
                                @endif
                            </td>
                            <td class="uppercase text-black font-bold">{{ $product->category }}</td>
                            <td class="price-col">₱{{ number_format($product->selling_price, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Footer -->
            @php $pageDescs = $pageProducts->pluck('description')->filter()->unique()->values(); @endphp
            <div class="mt-auto pt-4 border-t-2 border-slate-300 flex justify-between items-center">
                <p class="text-[12px] text-black font-bold uppercase tracking-widest">
                    W68 AUTO PARTS - PRICE LIST
                    @if($pageDescs->isNotEmpty())
                    <br><span class="text-[10px] text-slate-600 font-bold">[{{ $pageDescs->implode(' | ') }}]</span>
                    @endif
                </p>
                <p class="text-[12px] text-black font-bold uppercase">PAGE {{ $pageIdx + 1 }} OF {{ $previewTotalPages }}</p>
            </div>
        </div>
    </div>
    @endforeach

    <!-- Full-page watermark overlay for print -->
    <div class="print-watermark" aria-hidden="true"></div>

    <!-- Print-Only: Per-page chunks for consistent layout -->
    <div id="print-report">
        @foreach($printChunks as $pageIdx => $pageProducts)
        @php $itemsArr = $pageProducts->values(); $lastDesc = null; @endphp
        <div class="print-page-item">
            <div style="display:flex;justify-content:space-between;align-items:flex-end;border-bottom:2px solid black;padding-bottom:3px;margin-bottom:12px;">
                <div>
                    <div style="font-family:'Arial Black',Arial,sans-serif;font-size:18px;font-weight:900;text-transform:uppercase;line-height:1;color:black;letter-spacing:-0.02em;">W68 AUTOPARTS &amp; SERVICE CENTER</div>
                    <div style="font-family:'Arial Black',Arial,sans-serif;font-size:18px;font-weight:900;text-transform:uppercase;line-height:1;color:black;letter-spacing:0.05em;">PRICE LIST</div>
                </div>
                <div style="font-family:'Arial Black',Arial,sans-serif;font-size:18px;font-weight:900;text-transform:uppercase;color:black;line-height:1;">{{ strtoupper(date('M d, Y')) }}</div>
            </div>
            <table class="print-table">
                <colgroup>
                    <col style="width:15%">
                    <col style="width:20%">
                    <col style="width:35%">
                    <col style="width:15%">
                    <col style="width:15%">
                </colgroup>
                <tbody>
                    @foreach($itemsArr as $product)
                    @php $desc = trim(strtoupper($product->description ?: 'NO DESCRIPTION')); @endphp
                    @if($desc !== $lastDesc)
                    <tr>
                        <td colspan="5" class="desc-header" style="background:#f1f5f9;border:2px solid black;text-align:left;padding:6px 10px;font-size:18px;font-weight:900;color:black;text-transform:uppercase;">
                            {{ $desc }}
                        </td>
                    </tr>
                    @php $lastDesc = $desc; @endphp
                    @endif
                    <tr>
                        <td style="border:2px solid black;width:15%;">{{ $product->product_code }}</td>
                        <td style="border:2px solid black;width:20%;font-family:monospace;">{{ $product->part_number ?: '---' }}</td>
                        <td style="border:2px solid black;width:35%;">
                            <div>{{ $product->application ?: $product->Application ?: '---' }}</div>
                            @php $pos = $product->position ?: $product->Position; @endphp
                            @if($pos)
                            <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:black;margin-top:2px;border-top:1px solid #ccc;padding-top:1px;">
                                <span style="font-size:9px;margin-right:2px;">POS:</span>{{ $pos }}
                            </div>
                            @endif
                        </td>
                        <td style="border:2px solid black;width:15%;text-transform:uppercase;">{{ $product->category }}</td>
                        <td style="border:2px solid black;width:15%;" class="price-col">₱{{ number_format($product->selling_price, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @php $printDescs = $itemsArr->pluck('description')->filter()->unique()->values(); @endphp
            <div style="border-top:1px solid black;display:flex;justify-content:space-between;align-items:center;padding-top:2mm;margin-top:8px;">
                <span style="font-size:12px;font-weight:700;text-transform:uppercase;color:black;">W68 AUTO PARTS &mdash; PRICE LIST @if($printDescs->isNotEmpty())<br><span style="font-size:10px;">[{{ $printDescs->implode(' | ') }}]</span>@endif</span>
                <span style="font-size:12px;font-weight:700;text-transform:uppercase;color:black;white-space:nowrap;">PAGE {{ $pageIdx + 1 }} OF {{ $printTotalPages }}</span>
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

