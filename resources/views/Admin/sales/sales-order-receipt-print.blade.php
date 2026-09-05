<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            color: #000;
            padding: 0 10px;
            font-weight: bold;
            line-height: 1.3;
        }
        @media print {
            @page { margin-top: 5.5cm; margin-bottom: 5cm; margin-left: 1cm; margin-right: 1cm; }
            body { padding: 0; }
            .no-print { display: none !important; }
        }
        .receipt { width: 100%; }
        .header-info { width: 100%; margin-bottom: 15px; }
        .header-info table { width: 100%; border-collapse: collapse; }
        .header-info td { vertical-align: top; padding: 0; font-size: 16px; font-weight: bold; }
        .header-info .label { font-weight: 400 !important; font-size: 16px; color: #000 !important; }
        .header-info .header-value { font-size: 15px; }
        .items-table { width: 100%; table-layout: fixed; border-collapse: collapse; margin-bottom: 8px; }
        .items-table td { border: 1px solid transparent; padding: 2px 3px; text-align: left; font-size: 14px; }
        .items-table td.c { text-align: center; white-space: nowrap; }
        .items-table td.r { text-align: right; }
        .qty-col { width: 7%; }
        .unit-col { width: 9%; }
        .code-col { width: 18%; padding-right: 6px; word-break: break-word; }
        .desc-col { width: 34%; padding-left: 2px; word-break: break-word; }
        .price-col { width: 12%; }
        .disc-col { width: 8%; }
        .total-col { width: 12%; }
        .sep-dash { border-top: 1px dashed #000; margin: 5px 0; }
        .summary { width: 100%; table-layout: fixed; margin-top: 3px; border-collapse: collapse; }
        .summary td { padding: 1px 4px; font-size: 14px; vertical-align: top; }
        .summary .lb { width: 65%; }
        .summary .vl { width: 35%; text-align: right; }
        .summary .bd { font-weight: bold; }
        .rush-text { font-family: 'Arial Black', Arial, sans-serif; font-size: 25px; font-weight: bold; margin: 5px 0; text-align: center; }
    </style>
</head>
<body>
    @include('partials.global.w68-loader')

    <div class="receipt">
        @php
            $rawPrintDate = trim((string) ($date ?? ''));
            $displayPrintDate = $rawPrintDate;
            if ($rawPrintDate !== '') {
                try {
                    $displayPrintDate = \Carbon\Carbon::parse($rawPrintDate)->format('d-m-Y');
                } catch (\Throwable $dateFormatError) {
                    $displayPrintDate = $rawPrintDate;
                }
            }
        @endphp
        <div class="header-info">
            <table>
                <tr>
                    <td style="width:60%">
                        <span class="label">CUSTOMER:</span> <span class="header-value">{{ $customerName }}</span><br>
                        <span class="label">ADDRESS:</span> <span class="header-value">{{ $customerAddress }}</span>
                    </td>
                    <td style="width:40%;text-align:right">
                        @if($printType === 'invoice')
                            <span class="label">DATE:</span> <span class="header-value">{{ $displayPrintDate }}</span><br>
                            <span class="label">TERMS:</span> <span class="header-value">{{ $terms ?? '' }}</span><br>
                            <span class="label">TIN:</span> {{ $customerTin }}
                        @else
                            <span class="label">SN NO.:</span> <span class="header-value">{{ $salesNumber }}</span><br>
                            <span class="label">DATE:</span> <span class="header-value">{{ $displayPrintDate }}</span><br>
                            <span class="label">TERMS:</span> <span class="header-value">{{ $terms ?? '' }}</span><br>
                            <span class="label">SALESMAN:</span> <span class="header-value">{{ $salesMan ?? '' }}</span>
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        <table class="items-table">
            <tbody>
                @foreach($items as $item)
                @php
                    // sales_order_items.description is the authoritative printable text.
                    // Migrated/processed sales notes already contain the application and
                    // position in this field, so appending them again duplicates the print.
                    $printDescription = trim((string) ($item['description'] ?? ''));
                    $formatPrintQty = static function ($value): string {
                        $number = (float) $value;
                        return floor($number) == $number
                            ? number_format($number, 0, '.', '')
                            : rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
                    };
                    $printQty = (float) ($item['print_quantity'] ?? $item['quantity'] ?? 0);
                    $additionalQty = (float) ($item['additional_qty'] ?? 0);
                    $printQtyLabel = $formatPrintQty($printQty)
                        . ($additionalQty > 0 ? '+(' . $formatPrintQty($additionalQty) . ')' : '');
                @endphp
                <tr>
                    <td class="qty-col c">{{ $printQtyLabel }}</td>
                    <td class="unit-col c">{{ $item['oum'] }}</td>
                    <td class="code-col">{{ !empty($item['price_code']) ? $item['price_code'] : $item['product_code'] }}</td>
                    <td class="desc-col">{{ $printDescription }}</td>
                    <td class="price-col r">{{ number_format($item['unit_price'], 2) }}</td>
                    <td class="disc-col c">
                        @if(!empty($item['discount']) && (float)$item['discount'] > 0)
                            {{ $item['discount'] }}%
                        @endif
                    </td>
                    <td class="total-col r">{{ number_format($item['subtotal'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="sep-dash"></div>

        <table class="summary">
            <tr><td class="lb bd">TOTAL (QTY {{ $totalQty }}):</td><td class="vl bd">{{ number_format($grossTotal, 2) }}</td></tr>
        </table>

        <div class="sep-dash"></div>

        @if(!empty($rushText))
        <div class="rush-text">{{ $rushText }}</div>
        @endif

        <table class="summary">
            @if($totalAddlDiscount > 0)
            <tr><td class="lb">Additional Discount ({{ number_format($addlDiscountRate, 2) }}%):</td><td class="vl">{{ number_format($totalAddlDiscount, 2) }}</td></tr>
            @endif
            @if($printType === 'invoice')
            <tr><td class="lb bd">NET OF VAT:</td><td class="vl bd">{{ number_format($grandTotal, 2) }}</td></tr>
            <tr><td class="lb">VAT({{ number_format($vatRate, 2) }}%):</td><td class="vl">{{ number_format($vatAmount, 2) }}</td></tr>
            <tr><td class="lb bd">TOTAL AMOUNT DUE:</td><td class="vl bd">{{ number_format($netAfterAddl, 2) }}</td></tr>
            @else
            <tr><td class="lb bd">INVOICE AMOUNT:</td><td class="vl bd">{{ number_format($netAfterAddl, 2) }}</td></tr>
            <tr><td class="lb bd">NET AMOUNT:</td><td class="vl bd">{{ number_format($grandTotal, 2) }}</td></tr>
            @endif
        </table>
    </div>

    <div class="no-print" style="position:fixed;bottom:0;left:0;right:0;background:#fff;border-top:2px solid #800000;padding:8px 20px;display:flex;align-items:center;gap:10px;z-index:999;font-family:Arial,sans-serif;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:4px;font-size:10px;font-weight:bold;">
            <span>Margins (cm):</span>
            <label>T<input type="number" id="margin-top" value="5.5" step="0.1" min="0" max="10" style="width:40px;padding:2px 4px;border:1px solid #ccc;border-radius:4px;font-size:10px;"></label>
            <label>B<input type="number" id="margin-bottom" value="5" step="0.1" min="0" max="10" style="width:40px;padding:2px 4px;border:1px solid #ccc;border-radius:4px;font-size:10px;"></label>
            <label>L<input type="number" id="margin-left" value="1" step="0.1" min="0" max="10" style="width:40px;padding:2px 4px;border:1px solid #ccc;border-radius:4px;font-size:10px;"></label>
            <label>R<input type="number" id="margin-right" value="1" step="0.1" min="0" max="10" style="width:40px;padding:2px 4px;border:1px solid #ccc;border-radius:4px;font-size:10px;"></label>
        </div>
        <button onclick="window.print()" style="padding:8px 20px;background:#800000;border:none;border-radius:8px;font-size:11px;font-weight:bold;color:#fff;cursor:pointer;">Print Receipt</button>
        <button id="export-pdf-btn" onclick="exportPdf()" style="padding:8px 20px;background:#1e40af;border:none;border-radius:8px;font-size:11px;font-weight:bold;color:#fff;cursor:pointer;">Export PDF</button>
        <button onclick="window.close()" style="padding:8px 20px;background:#fff;border:2px solid #e2e8f0;border-radius:8px;font-size:11px;font-weight:bold;color:#475569;cursor:pointer;">Close</button>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        function getMargins() {
            return {
                top: parseFloat(document.getElementById('margin-top').value) || 0,
                bottom: parseFloat(document.getElementById('margin-bottom').value) || 0,
                left: parseFloat(document.getElementById('margin-left').value) || 0,
                right: parseFloat(document.getElementById('margin-right').value) || 0,
            };
        }
        function updatePageMargins() {
            const m = getMargins();
            const style = document.getElementById('page-margin-style') || (function(){
                const s = document.createElement('style');
                s.id = 'page-margin-style';
                document.head.appendChild(s);
                return s;
            })();
            style.textContent = '@media print { @page { margin-top: ' + m.top + 'cm; margin-bottom: ' + m.bottom + 'cm; margin-left: ' + m.left + 'cm; margin-right: ' + m.right + 'cm; } }';
        }
        document.querySelectorAll('#margin-top,#margin-bottom,#margin-left,#margin-right').forEach(el => {
            el.addEventListener('input', updatePageMargins);
        });
        window.onload = function() { updatePageMargins(); setTimeout(() => { window.print(); }, 300); };

        function exportPdf() {
            const btn = document.getElementById('export-pdf-btn');
            btn.textContent = 'Generating PDF...';
            btn.disabled = true;
            const m = getMargins();
            const el = document.querySelector('.receipt');
            const opt = {
                margin: [m.top * 10, m.left * 10, m.bottom * 10, m.right * 10],
                filename: 'receipt.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
            };
            html2pdf().set(opt).from(el).save().then(function() {
                btn.textContent = 'Export PDF';
                btn.disabled = false;
            });
        }
    </script>
</body>
</html>
