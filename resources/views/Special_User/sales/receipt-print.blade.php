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
            @if($printType === 'invoice')
            @page { margin-top: 4.7cm; margin-bottom: 5cm; margin-left: 0.6cm; margin-right: 0.8cm; }
            @else
            @page { margin-top: 4.9cm; margin-bottom: 5cm; margin-left: 0.6cm; margin-right: 0.8cm; }
            @endif
            body { padding: 0; }
            .no-print { display: none !important; }
        }
        .receipt { width: 100%; }
        /* Both Sales Order and Sales Invoice print text are intentionally unbold. */
        .receipt, .receipt * { font-weight: normal !important; }
        .header-info {
            position: relative;
            width: 100%;
            min-height: 1.8cm;
            margin-bottom: 15px;
            font-size: 16px;
            font-weight: normal;
            line-height: 0.6cm;
        }
        .header-left { max-width: 8.5cm; }
        .header-right-content {
            position: absolute;
            top: 0;
            width: 3cm;
            text-align: left;
            white-space: nowrap;
            line-height: 0.6cm;
            font-variant-numeric: tabular-nums;
        }
        /* Sales Order header labels/values: explicit print-safe geometry. */
        body.print-order .header-label {
            font-weight: normal;
            font-size: 16px;
            white-space: nowrap;
        }
        body.print-order .header-value {
            font-size: 14px;
            font-weight: normal;
            white-space: nowrap;
        }
        /* Visible gap between CUSTOMER/ADDRESS labels and their values. */
        body.print-order .header-left {
            width: calc(68% - 3.2cm);
            max-width: none;
        }
        body.print-order .header-left .header-row {
            display: grid;
            grid-template-columns: 2.25cm minmax(0, 1fr);
            column-gap: 0.4cm;
            align-items: start;
            min-height: 0.6cm;
            line-height: 0.6cm;
        }
        body.print-order .header-left .header-value {
            min-width: 0;
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: normal;
            line-height: 0.6cm;
            padding-right: 0.1cm;
        }
        /*
         * Anchor right-side values to the UNIT PRICE column, not to paper-right cm offsets.
         * Table columns before UNIT PRICE: QTY 7% + UNIT 9% + CODE 18% + DESCRIPTION 34% = 68%.
         * Therefore every right-side value starts exactly where .price-col starts.
         */
        body.print-order .header-right-content {
            left: calc(68% - 2.8cm);
            right: auto;
            width: calc(32% + 2.8cm);
        }
        body.print-order .header-right-content .header-row {
            position: relative;
            display: block;
            height: 0.6cm;
            line-height: 0.6cm;
        }
        body.print-order .header-right-content .header-label {
            position: absolute;
            left: 0;
            width: 2.4cm;
            text-align: right;
        }
        body.print-order .header-right-content .header-value {
            position: absolute;
            left: 2.8cm; /* 0.4cm gap after label; value still starts at 68% = UNIT PRICE */
        }
        body.print-order .header-right-content .header-value:empty::after {
            content: "\00a0";
        }
        /* Header positioning is independent from the 0.6cm body margins. */
        body.print-order .header-left,
        body.print-invoice .header-left { padding-left: 1.7cm; } /* physical left = 2.3cm */

        /*
         * Sales Order vertical alignment:
         * page origin is 4.9cm; left header is pushed down 0.6cm.
         * CUSTOMER stays at 5.5cm, ADDRESS at 6.1cm.
         * Right rows start at 4.9cm, so TERMS (3rd row) is also 6.1cm = ADDRESS.
         * The taller header keeps the item table at the same physical Y position as before.
         */
        body.print-order .header-left { padding-top: 0.6cm; }
        body.print-order .header-info { min-height: 2.4cm; }
        body.print-order .header-right-content { top: 0; }

        /*
         * Sales Invoice uses the same UNIT PRICE anchor: DATE / TERMS / TIN start at 68%.
         * Vertical positioning is unchanged, so TERMS keeps its current ADDRESS alignment.
         */
        body.print-invoice .header-right-content {
            left: 68%;
            right: auto;
            width: 32%;
            top: 0;
        }
        /* Sales Invoice header values are 1px smaller than the 16px header base. */
        body.print-invoice .header-left,
        body.print-invoice .header-right-content {
            font-size: 15px;
            font-weight: normal;
        }
        body.print-invoice .header-right-content > div {
            min-height: 0.6cm;
            line-height: 0.6cm;
        }
        body.print-invoice .header-right-content > div:empty::after {
            content: "\00a0";
        }
        /*
         * Sales Invoice approved vertical geometry:
         * CUSTOMER / DATE = 4.7cm
         * ADDRESS / TERMS = 5.3cm
         * TIN = 5.9cm
         * 2.7cm reserve preserves the existing body position / header separation.
         */
        body.print-invoice .header-info {
            min-height: 2.7cm;
            margin-bottom: 0.4cm;
        }

        /*
         * Header typography adjustment:
         * - Customer value remains at its current size and is the only bold header value.
         * - Address / Sales Order Sn no. / Date / Terms / Salesman values are 1.5px smaller.
         * - Sales Invoice Date / Terms values are also 1.5px smaller.
         * - Labels and all other print text remain unbold.
         */
        body.print-order .header-left .customer-line .header-value {
            font-size: 13px;
            font-weight: bold !important;
        }
        body.print-order .header-left .address-line .header-value {
            font-size: 12px;
            font-weight: normal !important;
        }
        body.print-order .header-right-content .header-value {
            font-size: 12.5px;
            font-weight: normal !important;
        }

        body.print-invoice .header-left .customer-line {
            font-size: 13px;
            font-weight: bold !important;
        }
        body.print-invoice .header-left .address-line {
            font-size: 12px;
            font-weight: normal !important;
        }
        body.print-invoice .header-right-content > div:nth-child(1),
        body.print-invoice .header-right-content > div:nth-child(2) {
            font-size: 12.5px;
            font-weight: normal !important;
        }

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
        /* Sales Invoice financial rows: labels begin exactly at UNIT PRICE (68%); amounts stay in TOTAL (last 12%). */
        body.print-invoice .invoice-financial-summary .invoice-spacer { width: 68%; }
        body.print-invoice .invoice-financial-summary .invoice-label { width: 20%; text-align: left; white-space: nowrap; padding-left: 0; }
        body.print-invoice .invoice-financial-summary .invoice-value { width: 12%; text-align: right; padding-right: 3px; }
        /* Sales Order financial rows: labels begin exactly at UNIT PRICE (68%); amounts stay in TOTAL (last 12%). */
        body.print-order .order-financial-summary .order-spacer { width: 68%; }
        body.print-order .order-financial-summary .order-label { width: 20%; text-align: left; white-space: nowrap; padding-left: 0; }
        body.print-order .order-financial-summary .order-value { width: 12%; text-align: right; padding-right: 3px; }
        .rush-text { font-family: 'Arial Black', Arial, sans-serif; font-size: 25px; font-weight: bold; margin: 5px 0; text-align: center; }

        /* Print-only editor. Nothing typed here is submitted or saved to the database. */
        .receipt[contenteditable="true"] {
            outline: 2px dashed #2563eb;
            outline-offset: 6px;
            cursor: text;
        }
        .receipt[contenteditable="true"]:focus { outline-color: #1d4ed8; }
        .edit-status {
            display: none;
            padding: 6px 10px;
            border-radius: 7px;
            background: #dbeafe;
            color: #1e3a8a;
            font-size: 11px;
            font-weight: bold;
        }
        body.edit-mode .edit-status { display: inline-block; }
        @media print {
            .receipt, .receipt[contenteditable="true"] { outline: none !important; }
        }
    </style>
</head>
<body class="print-{{ $printType === 'invoice' ? 'invoice' : 'order' }}">
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
            <div class="header-left">
                @if($printType === 'invoice')
                    <div class="customer-line">{{ $customerName }}</div>
                    <div class="address-line">{{ $customerAddress }}</div>
                @else
                    <div class="customer-line header-row"><span class="header-label">Customer:</span><span class="header-value">{{ $customerName }}</span></div>
                    <div class="address-line header-row"><span class="header-label">Address:</span><span class="header-value">{{ $customerAddress }}</span></div>
                @endif
            </div>
            <div class="header-right-content">
                @if($printType === 'invoice')
                    <div>{{ $displayPrintDate }}</div>
                    <div>{{ $terms ?? '' }}</div>
                    <div>{{ $customerTin }}</div>
                @else
                    <div class="header-row"><span class="header-label">Sn no.:</span><span class="header-value">{{ $salesNumber }}</span></div>
                    <div class="header-row"><span class="header-label">Date:</span><span class="header-value">{{ $displayPrintDate }}</span></div>
                    <div class="header-row"><span class="header-label">Terms:</span><span class="header-value">{{ $terms ?? '' }}</span></div>
                    <div class="header-row"><span class="header-label">Salesman:</span><span class="header-value">{{ $salesMan ?? '' }}</span></div>
                @endif
            </div>
        </div>

        <table class="items-table">
            <tbody>
                @foreach($items as $item)
                @php
                    // Show description, application, and position exactly once.
                    // Migrated items may already contain application/position inside
                    // description, while newly processed items keep them separately.
                    $normalizePrintableText = static function ($value): string {
                        $value = strtoupper(trim((string) $value));
                        $value = preg_replace('/[^A-Z0-9]+/u', ' ', $value) ?? '';
                        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
                    };

                    $alreadyIncluded = static function ($existing, $candidate) use ($normalizePrintableText): bool {
                        $existingNormalized = $normalizePrintableText($existing);
                        $candidateNormalized = $normalizePrintableText($candidate);

                        if ($candidateNormalized === '') {
                            return true;
                        }
                        if ($existingNormalized === '') {
                            return false;
                        }
                        if (str_contains($existingNormalized, $candidateNormalized)) {
                            return true;
                        }

                        $existingTokens = array_values(array_unique(array_filter(explode(' ', $existingNormalized))));
                        $candidateTokens = array_values(array_unique(array_filter(explode(' ', $candidateNormalized))));
                        if (empty($candidateTokens)) {
                            return true;
                        }

                        $matchedTokens = count(array_intersect($candidateTokens, $existingTokens));
                        $requiredMatches = count($candidateTokens) <= 2
                            ? count($candidateTokens)
                            : (int) ceil(count($candidateTokens) * 0.70);

                        return $matchedTokens >= $requiredMatches;
                    };

                    $details = [];
                    $printDescription = trim((string) ($item['description'] ?? ''));
                    if ($printDescription !== '') {
                        $details[] = $printDescription;
                    }

                    foreach (['application', 'position'] as $detailKey) {
                        $detailValue = trim((string) ($item[$detailKey] ?? ''));
                        $existingText = implode(' ', $details);
                        if ($detailValue !== '' && !$alreadyIncluded($existingText, $detailValue)) {
                            $details[] = $detailValue;
                        }
                    }
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
                    <td class="qty-col c" style="white-space:nowrap">{{ $printQtyLabel }}</td>
                    <td class="unit-col c">{{ $item['oum'] }}</td>
                    <td class="code-col">{{ !empty($item['price_code']) ? $item['price_code'] : $item['product_code'] }}</td>
                    <td class="desc-col">{{ implode(' ', $details) }}</td>
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

        @if($totalAddlDiscount > 0)
        <table class="summary">
            <tr><td class="lb">Additional Discount ({{ number_format($addlDiscountRate, 2) }}%):</td><td class="vl">{{ number_format($totalAddlDiscount, 2) }}</td></tr>
        </table>
        @endif

        @if($printType === 'invoice')
        <table class="summary invoice-financial-summary">
            <tr><td class="invoice-spacer"></td><td class="invoice-label bd">NET OF VAT:</td><td class="invoice-value bd">{{ number_format($grandTotal, 2) }}</td></tr>
            <tr><td class="invoice-spacer"></td><td class="invoice-label">VAT({{ number_format($vatRate, 2) }}%):</td><td class="invoice-value">{{ number_format($vatAmount, 2) }}</td></tr>
            <tr><td class="invoice-spacer"></td><td class="invoice-label bd">TOTAL AMOUNT DUE:</td><td class="invoice-value bd">{{ number_format($netAfterAddl, 2) }}</td></tr>
        </table>
        @else
        <table class="summary order-financial-summary">
            <tr><td class="order-spacer"></td><td class="order-label bd">INVOICE AMOUNT:</td><td class="order-value bd">{{ number_format($netAfterAddl, 2) }}</td></tr>
            <tr><td class="order-spacer"></td><td class="order-label bd">NET AMOUNT:</td><td class="order-value bd">{{ number_format($grandTotal, 2) }}</td></tr>
        </table>
        @endif
    </div>

    <div class="no-print" style="position:fixed;bottom:0;left:0;right:0;background:#fff;border-top:2px solid #800000;padding:10px 20px;display:flex;justify-content:center;align-items:center;gap:12px;z-index:999;flex-wrap:wrap;font-family:Arial,sans-serif;">
        <button id="edit-print-btn" type="button" onclick="toggleEditMode()" style="padding:10px 24px;background:#0f766e;border:none;border-radius:10px;font-size:12px;font-weight:bold;color:#fff;cursor:pointer;">Edit Print</button>
        <button id="reset-print-btn" type="button" onclick="resetPrint()" style="padding:10px 24px;background:#fff7ed;border:1px solid #fdba74;border-radius:10px;font-size:12px;font-weight:bold;color:#9a3412;cursor:pointer;">Reset</button>
        <span class="edit-status">EDIT MODE ON — click any text on the invoice and type. Changes are print-only.</span>
        <button type="button" onclick="printReceipt()" style="padding:10px 24px;background:#800000;border:none;border-radius:10px;font-size:12px;font-weight:bold;color:#fff;cursor:pointer;">Print Receipt</button>
        <button id="export-pdf-btn" type="button" onclick="exportPdf()" style="padding:10px 24px;background:#1e40af;border:none;border-radius:10px;font-size:12px;font-weight:bold;color:#fff;cursor:pointer;">Export PDF</button>
        <button type="button" onclick="window.close()" style="padding:10px 24px;background:#fff;border:2px solid #e2e8f0;border-radius:10px;font-size:12px;font-weight:bold;color:#475569;cursor:pointer;">Close</button>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>

        let originalReceiptHtml = '';
        let editMode = false;

        function setEditMode(enabled) {
            const receipt = document.querySelector('.receipt');
            const btn = document.getElementById('edit-print-btn');
            editMode = !!enabled;

            receipt.setAttribute('contenteditable', editMode ? 'true' : 'false');
            receipt.setAttribute('spellcheck', 'false');
            document.body.classList.toggle('edit-mode', editMode);

            if (btn) {
                btn.textContent = editMode ? 'Finish Editing' : 'Edit Print';
                btn.style.background = editMode ? '#b45309' : '#0f766e';
            }

            if (editMode) {
                receipt.focus();
            } else if (document.activeElement === receipt) {
                receipt.blur();
            }
        }

        function toggleEditMode() {
            setEditMode(!editMode);
        }

        function resetPrint() {
            const receipt = document.querySelector('.receipt');
            receipt.innerHTML = originalReceiptHtml;
            setEditMode(false);
        }

        function printReceipt() {
            const wasEditing = editMode;
            setEditMode(false);
            window.print();
            if (wasEditing) {
                setEditMode(true);
            }
        }

        function insertPlainText(event) {
            if (!editMode) return;
            event.preventDefault();
            const text = (event.clipboardData || window.clipboardData).getData('text/plain');
            const selection = window.getSelection();
            if (!selection || selection.rangeCount === 0) return;
            selection.deleteFromDocument();
            const range = selection.getRangeAt(0);
            const node = document.createTextNode(text);
            range.insertNode(node);
            range.setStartAfter(node);
            range.collapse(true);
            selection.removeAllRanges();
            selection.addRange(range);
        }

        window.onload = function() {
            const receipt = document.querySelector('.receipt');
            originalReceiptHtml = receipt.innerHTML;
            receipt.addEventListener('paste', insertPlainText);
            setEditMode(false);
        };

        function exportPdf() {
            const btn = document.getElementById('export-pdf-btn');
            const wasEditing = editMode;
            setEditMode(false);
            btn.textContent = 'Generating PDF...';
            btn.disabled = true;
            const el = document.querySelector('.receipt');
            const opt = {
                margin: [{{ $printType === 'invoice' ? 47 : 49 }}, 6, 50, {{ $printType === 'invoice' ? 8 : 8 }}],
                filename: 'receipt.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
            };
            html2pdf().set(opt).from(el).save().then(function() {
                btn.textContent = 'Export PDF';
                btn.disabled = false;
                if (wasEditing) setEditMode(true);
            }).catch(function() {
                btn.textContent = 'Export PDF';
                btn.disabled = false;
                if (wasEditing) setEditMode(true);
            });
        }
    </script>
</body>
</html>
