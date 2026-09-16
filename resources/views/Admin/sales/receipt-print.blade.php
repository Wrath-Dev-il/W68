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
        /* W68_SALES_INVOICE_HEADER_WIDTH_20260910
         * CUSTOMER / ADDRESS may extend to 70%.
         * Description ends at 68%, so this is only slightly longer.
         */
        body.print-invoice .header-left {
            width: 70%;
            max-width: none;
            padding-right: 0.1cm;
        }

        body.print-invoice .header-left .customer-line,
        body.print-invoice .header-left .address-line {
            max-width: 100%;
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: normal;
        }

        /* W68_SALES_INVOICE_PREPRINTED_LABEL_GAP_20260911
         * Physical Sales Invoice already has DATE / TERMS / TIN labels.
         * Leave 1.5cm blank space before their printed values.
         */
        body.print-invoice .header-right-content {
            left: calc(68% + 1.5cm);
            right: auto;
            width: calc(32% - 1.5cm);
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
            // W68_SALES_PRINT_BRAND_FIX_20260910
            // Product Master "Brand" is stored in core4_masterlist.products.category.
            // Resolve all brands once for this print to avoid one query per item.
            $printBrandById = collect();
            $printBrandByCode = collect();

            try {
                $printRows = collect($items ?? []);

                $printProductIds = $printRows
                    ->map(static fn ($row) => (int) ($row['product_id'] ?? 0))
                    ->filter(static fn ($id) => $id > 0)
                    ->unique()
                    ->values();

                $printProductCodes = $printRows
                    ->map(static fn ($row) => trim((string) ($row['product_code'] ?? '')))
                    ->filter(static fn ($code) => $code !== '')
                    ->unique()
                    ->values();

                if ($printProductIds->isNotEmpty() || $printProductCodes->isNotEmpty()) {

                    $brandQuery = \Illuminate\Support\Facades\DB::connection('masterlist')
                        ->table('products')
                        ->select([
                            'id',
                            'product_code',
                            'category',
                        ]);

                    $brandQuery->where(function ($query) use (
                        $printProductIds,
                        $printProductCodes
                    ) {
                        if ($printProductIds->isNotEmpty()) {
                            $query->whereIn(
                                'id',
                                $printProductIds->all()
                            );
                        }

                        if ($printProductCodes->isNotEmpty()) {
                            if ($printProductIds->isNotEmpty()) {
                                $query->orWhereIn(
                                    'product_code',
                                    $printProductCodes->all()
                                );
                            } else {
                                $query->whereIn(
                                    'product_code',
                                    $printProductCodes->all()
                                );
                            }
                        }
                    });

                    $brandProducts = $brandQuery->get();

                    $printBrandById = $brandProducts
                        ->mapWithKeys(static function ($product) {
                            return [
                                (int) $product->id =>
                                    trim((string) $product->category)
                            ];
                        });

                    $printBrandByCode = $brandProducts
                        ->mapWithKeys(static function ($product) {
                            return [
                                trim((string) $product->product_code) =>
                                    trim((string) $product->category)
                            ];
                        });
                }
            } catch (\Throwable $brandLookupError) {
                // Printing must still work even if an old product cannot
                // be resolved from Product Master.
                $printBrandById = collect();
                $printBrandByCode = collect();
            }

            $printDetailAlreadyIncluded = static function (
                $existing,
                $candidate
            ): bool {
                $normalize = static function ($value): string {
                    $value = strtoupper(trim((string) $value));

                    $value = preg_replace(
                        '/[^A-Z0-9]+/u',
                        ' ',
                        $value
                    ) ?? '';

                    return trim(
                        preg_replace('/\s+/u', ' ', $value) ?? ''
                    );
                };

                $existingNormalized = $normalize($existing);
                $candidateNormalized = $normalize($candidate);

                if ($candidateNormalized === '') {
                    return true;
                }

                if ($existingNormalized === '') {
                    return false;
                }

                return str_contains(
                    $existingNormalized,
                    $candidateNormalized
                );
            };

            $resolvePrintBrand = static function ($item) use (
                $printBrandById,
                $printBrandByCode
            ): string {

                // Use a brand/category already supplied with the print first.
                $brand = trim((string) (
                    $item['brand']
                    ?? $item['category']
                    ?? ''
                ));

                if ($brand !== '') {
                    return $brand;
                }

                $productId = (int) ($item['product_id'] ?? 0);

                if ($productId > 0) {
                    $brand = trim((string) (
                        $printBrandById->get($productId) ?? ''
                    ));
                }

                if ($brand !== '') {
                    return $brand;
                }

                $productCode = trim((string) (
                    $item['product_code'] ?? ''
                ));

                if ($productCode !== '') {
                    $brand = trim((string) (
                        $printBrandByCode->get($productCode) ?? ''
                    ));
                }

                return $brand;
            };

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
                    // sales_order_items.description is the authoritative printable text.
                    // Migrated/processed sales notes already contain the application and
                    // position in this field, so appending them again duplicates the print.
                    $printDescription = trim((string) ($item['description'] ?? ''));

                    // W68_STANDARD_PRINT_BRAND_ROW_20260910
                    // Add Product Master Brand to Description without duplicating it.
                    $printBrand = $resolvePrintBrand($item);
                    $printDescriptionWithBrand = $printDescription;

                    if (
                        $printBrand !== ''
                        && !$printDetailAlreadyIncluded(
                            $printDescriptionWithBrand,
                            $printBrand
                        )
                    ) {
                        $printDescriptionWithBrand = trim(
                            $printDescriptionWithBrand . ' ' . $printBrand
                        );
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
                    <td class="desc-col">{{ $printDescriptionWithBrand }}</td>
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

    <div class="no-print" style="position:fixed;bottom:0;left:0;right:0;background:#fff;border-top:2px solid #800000;padding:8px 20px;display:flex;align-items:center;gap:10px;z-index:999;font-family:Arial,sans-serif;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:4px;font-size:10px;font-weight:bold;">
            <span>Margins (cm):</span>
            <label>T<input type="number" id="margin-top" value="{{ $printType === 'invoice' ? '4.7' : '4.9' }}" step="0.1" min="0" max="10" style="width:40px;padding:2px 4px;border:1px solid #ccc;border-radius:4px;font-size:10px;"></label>
            <label>B<input type="number" id="margin-bottom" value="5" step="0.1" min="0" max="10" style="width:40px;padding:2px 4px;border:1px solid #ccc;border-radius:4px;font-size:10px;"></label>
            <label>L<input type="number" id="margin-left" value="0.6" step="0.1" min="0" max="10" style="width:40px;padding:2px 4px;border:1px solid #ccc;border-radius:4px;font-size:10px;"></label>
            <label>R<input type="number" id="margin-right" value="{{ $printType === 'invoice' ? '0.8' : '0.8' }}" step="0.1" min="0" max="10" style="width:40px;padding:2px 4px;border:1px solid #ccc;border-radius:4px;font-size:10px;"></label>
        </div>
        <button id="edit-print-btn" type="button" onclick="toggleEditMode()" style="padding:8px 20px;background:#0f766e;border:none;border-radius:8px;font-size:11px;font-weight:bold;color:#fff;cursor:pointer;">Edit Print</button>
        <button id="reset-print-btn" type="button" onclick="resetPrint()" style="padding:8px 20px;background:#fff7ed;border:1px solid #fdba74;border-radius:8px;font-size:11px;font-weight:bold;color:#9a3412;cursor:pointer;">Reset</button>
        <span class="edit-status">EDIT MODE ON — click any text on the invoice and type. Changes are print-only.</span>
        <button type="button" onclick="printReceipt()" style="padding:8px 20px;background:#800000;border:none;border-radius:8px;font-size:11px;font-weight:bold;color:#fff;cursor:pointer;">Print Receipt</button>
        <button id="export-pdf-btn" type="button" onclick="exportPdf()" style="padding:8px 20px;background:#1e40af;border:none;border-radius:8px;font-size:11px;font-weight:bold;color:#fff;cursor:pointer;">Export PDF</button>
        <button type="button" onclick="window.close()" style="padding:8px 20px;background:#fff;border:2px solid #e2e8f0;border-radius:8px;font-size:11px;font-weight:bold;color:#475569;cursor:pointer;">Close</button>
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
        window.onload = function() {
            updatePageMargins();
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
