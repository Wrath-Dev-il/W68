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
            @page { size: A4 portrait; margin-top: 1cm; margin-bottom: 1cm; margin-left: 1cm; margin-right: 1cm; }
            body { padding: 0; }
            .no-print { display: none !important; }
        }
        .receipt { width: 100%; }

        /* W68_SALES_ORDER_ONLY_PRINT_LAYOUT_20260929 */

        body {
            font-weight: 400 !important;
        }

        .sales-order-topline {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin: 0;
        }

        .sales-order-topline td {
            padding: 0;
            vertical-align: middle;
            font-weight: 400;
        }

        .sales-order-topline .top-left {
            width: 30%;
        }

        .sales-order-topline .erw-heading {
            width: 40%;
            text-align: center;
            font-size: 15px;
            line-height: 1.2;
            font-weight: 700 !important;
            color: #000;
            white-space: nowrap;
        }

        .sales-order-topline .invoice-heading {
            width: 30%;
            text-align: right;
            font-size: 12px;
            line-height: 1.2;
            font-weight: 700 !important;
            color: #d00000 !important;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .sales-order-topline .invoice-heading .invoice-label {
            font-size: 12px;
            font-weight: 700 !important;
            color: #d00000 !important;
        }

        .sales-order-topline .invoice-heading .invoice-value {
            font-size: 14px;
            font-weight: 700 !important;
            color: #d00000 !important;
        }

        .sales-order-customer-info {
            width: 100%;
            margin-bottom: 0;
        }

        .sales-order-customer-info table {
            width: 100%;
            border-collapse: collapse;
        }

        .sales-order-customer-info td {
            vertical-align: top;
            padding: 0;
            font-size: 12px !important;
            line-height: 1.35;
            font-weight: 400 !important;
        }

        .sales-order-customer-info .label {
            font-size: 12px !important;
            font-weight: 400 !important;
        }

        .sales-order-customer-info .header-value {
            font-size: 11px !important;
            font-weight: 400 !important;
        }

        .sales-order-customer-info .customer-value {
            font-size: 11.5px !important;
            font-weight: 700 !important;
        }

        .sales-order-header-separator {
            margin: 5px 0 6px 0;
        }

        /* Item/body values must not be bold. */
        .items-table,
        .items-table tbody,
        .items-table tr,
        .items-table td {
            font-size: 12px !important;
            font-weight: 400 !important;
        }

        .summary,
        .summary td,
        .summary .lb,
        .summary .vl,
        .summary .bd {
            font-size: 12px !important;
            font-weight: 400 !important;
        }

        .rush-text {
            font-weight: 400 !important;
        }

        .sales-order-footer {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-top: 3px;
        }

        .sales-order-footer td {
            padding: 1px 4px;
            font-size: 12px;
            line-height: 1.3;
            vertical-align: top;
            font-weight: 400 !important;
        }

        .sales-order-footer .signoff-cell {
            width: 55%;
            text-align: left;
            white-space: nowrap;
        }

        .sales-order-footer .financial-label {
            width: 25%;
            text-align: left;
            white-space: nowrap;
        }

        .sales-order-footer .financial-value {
            width: 20%;
            text-align: right;
            white-space: nowrap;
        }
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
            /* W68_SALES_PRINT_INVOICE_REF_STYLE_20260921 */
        .invoice-reference,
        .order-reference {
            text-align: left !important;
            white-space: nowrap;
            padding-left: 0 !important;
            font-variant-numeric: tabular-nums;
        }

        .invoice-reference-row .print-invoice-reference {
            width: 45%;
            text-align: left;
            white-space: nowrap;
            padding-left: 4px;
            font-variant-numeric: tabular-nums;
        }

        .invoice-reference-row .print-financial-label {
            width: 20%;
            text-align: left;
            white-space: nowrap;
        }

        .invoice-reference-row .print-financial-value {
            width: 35%;
            text-align: right;
        }
    
        /* W68_SALES_ORDER_TOTALS_UNDER_UNIT_PRICE_20260929 */
        .sales-order-footer .signoff-cell,
        .sales-order-footer .financial-label,
        .sales-order-footer .financial-value {
            width: auto !important;
        }

        /* W68_SALES_ORDER_BODY_HEADING_20260929 */
        .items-table thead th {
            font-size: 12px !important;
            font-weight: 400 !important;
            line-height: 1.2;
            padding: 1px 2px 3px 2px;
            vertical-align: bottom;
            border-bottom: 1px dashed #000;
            white-space: nowrap;
        }

        .items-table thead .qty-col,
        .items-table thead .unit-col,
        .items-table thead .disc-col {
            text-align: center;
        }

        .items-table thead .code-col,
        .items-table thead .desc-col {
            text-align: left;
        }

        .items-table thead .price-col,
        .items-table thead .total-col {
            text-align: right;
        }

        /* W68_SALES_ORDER_CENTERED_HEADING_SEPARATOR_20260929 */
        .items-table thead th {
            font-size: 12px !important;
            font-weight: 400 !important;
            text-align: center !important;
            vertical-align: middle !important;
            white-space: nowrap;
            border-bottom: 0 !important;
        }

        .items-table thead .qty-col,
        .items-table thead .unit-col,
        .items-table thead .code-col,
        .items-table thead .desc-col,
        .items-table thead .price-col,
        .items-table thead .disc-col,
        .items-table thead .total-col {
            text-align: center !important;
        }

        .items-table thead .sales-order-heading-bottom th {
            padding: 0 !important;
            margin: 0 !important;
            height: 0 !important;
            line-height: 0 !important;
            font-size: 0 !important;
            border-top: 1px dashed #000 !important;
        }

        /* W68_SALES_ORDER_DYNAMIC_DISCOUNT_WIDTHS_20260929 */
        .items-table.no-discount .qty-col   { width: 8.333333% !important; }
        .items-table.no-discount .unit-col  { width: 10.333333% !important; }
        .items-table.no-discount .code-col  { width: 19.333333% !important; }
        .items-table.no-discount .desc-col  { width: 35.333333% !important; }
        .items-table.no-discount .price-col { width: 13.333333% !important; }
        .items-table.no-discount .total-col { width: 13.333333% !important; }

        /* W68_SALES_ORDER_NO_DISCOUNT_CENTER_VALUES_20260929 */
        .items-table.no-discount tbody td.code-col,
        .items-table.no-discount tbody td.price-col,
        .items-table.no-discount tbody td.total-col {
            text-align: center !important;
        }

        /* W68_SALES_ORDER_CENTER_BODY_COLUMNS_ALWAYS_20260929 */
        .items-table tbody td.code-col,
        .items-table tbody td.price-col,
        .items-table tbody td.total-col {
            text-align: center !important;
        }

        /* W68_SALES_ORDER_TERMS_20260929 */
        .sales-order-customer-info .terms-value {
            font-size: 11.5px !important;
            font-weight: 400 !important;
        }

        /* W68_SALES_ORDER_META_SIGNOFF_20260929 */

        .sales-order-meta {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .sales-order-meta td {
            padding: 0 !important;
            vertical-align: top;
            line-height: 1.35;
        }

        .sales-order-meta .meta-label {
            width: 44%;
            text-align: right !important;
            font-size: 12px !important;
            font-weight: 400 !important;
            white-space: nowrap;
        }

        .sales-order-meta .meta-value {
            width: 56%;
            text-align: left !important;
            padding-left: 7px !important;
            font-size: 11px !important;
            font-weight: 400 !important;
            white-space: nowrap;
        }

        .sales-order-meta .terms-value {
            font-size: 11.5px !important;
        }

        .signoff-editable {
            display: inline-block;
            min-width: 165px;
            min-height: 15px;
            margin-left: 5px;
            padding: 0 3px;
            border-bottom: 1px dotted #777;
            outline: none;
            font-size: 12px;
            font-weight: 400 !important;
            cursor: text;
        }

        .signoff-editable:focus {
            background: #fff8dc;
            border-bottom-color: #800000;
        }

        @media print {
            .signoff-editable {
                border-bottom: 0 !important;
                background: transparent !important;
                outline: none !important;
            }
        }

        
        /* W68_SALES_ORDER_TIGHT_HEADER_ALIGNMENT_20260929 */

        .sales-order-topline .invoice-heading {
            overflow: visible !important;
            text-align: left !important;
            white-space: nowrap;
        }

        /* NO.66015 */
        .sales-order-topline .invoice-align-row {
            display: inline-flex !important;
            align-items: baseline;
            justify-content: flex-start;
            gap: 0 !important;
            width: auto !important;
            margin-left: 0 !important;
        }

        .sales-order-topline .invoice-align-row .invoice-label {
            display: inline !important;
            text-align: left !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
            margin: 0 !important;
            font-size: 12px !important;
            font-weight: 700 !important;
            color: #d00000 !important;
            white-space: nowrap;
        }

        .sales-order-topline .invoice-align-row .invoice-value {
            display: inline !important;
            text-align: left !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
            margin: 0 !important;
            font-size: 14px !important;
            font-weight: 700 !important;
            color: #d00000 !important;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        /* SN NO.: value / DATE: value / TIN: value / TERMS: value / SALESMAN: value */
        .sales-order-meta {
            width: 100% !important;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .sales-order-meta td {
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            vertical-align: baseline !important;
            line-height: 1.35 !important;
        }

        .sales-order-meta .meta-label {
            width: 34% !important;
            text-align: left !important;
            padding-left: 0 !important;
            padding-right: 2px !important;
            font-size: 12px !important;
            font-weight: 400 !important;
            white-space: nowrap;
        }

        .sales-order-meta .meta-value {
            width: 66% !important;
            text-align: left !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
            font-size: 11px !important;
            font-weight: 400 !important;
            white-space: nowrap;
        }

        .sales-order-meta .terms-value {
            font-size: 11.5px !important;
        }

        /* keep footer alignment */
        .sales-order-footer .signoff-cell {
            text-align: left !important;
            white-space: nowrap;
        }

        .sales-order-footer .signoff-line {
            display: grid !important;
            grid-template-columns: 100px minmax(0, 1fr);
            align-items: baseline;
            width: 100%;
        }

        .sales-order-footer .signoff-label {
            display: block;
            padding: 0 !important;
            margin: 0 !important;
            text-align: left !important;
            font-size: 12px !important;
            font-weight: 400 !important;
            white-space: nowrap;
        }

        .sales-order-footer .signoff-editable,
        .sales-order-footer .signoff-value {
            display: block !important;
            margin: 0 !important;
            padding: 0 0 0 5px !important;
            min-width: 0 !important;
            text-align: left !important;
            font-size: 12px !important;
            font-weight: 400 !important;
        }

        .sales-order-footer .signoff-editable {
            min-height: 15px;
            border-bottom: 1px dotted #777;
            outline: none;
        }

        @media print {
            .sales-order-footer .signoff-editable {
                border-bottom: 0 !important;
                background: transparent !important;
                outline: none !important;
            }
        }

        /* W68_SALES_ORDER_MATCH_WORD_REFERENCE_20260929 */

        /*
         * FINAL HEADER POSITION FROM PRINT SALES.DOCX
         *
         *                         NO.66015
         * CUSTOMER: ...          SN NO:   SN-0030005
         * ADDRESS: ...           DATE:    29-09-2026
         *                        TIN:     103-310-823
         *                        TERMS:   150
         *                        SALESMAN:YUNJIN
         */

        .sales-order-customer-info {
            margin-bottom: 15px !important;
        }

        /*
         * Reference document uses approximately:
         * 70% left/customer area
         * 30% right metadata area
         */
        .sales-order-customer-info > table > tbody > tr > td:first-child,
        .sales-order-customer-info > table > tr > td:first-child {
            width: 70% !important;
        }

        .sales-order-customer-info > table > tbody > tr > td:last-child,
        .sales-order-customer-info > table > tr > td:last-child {
            width: 30% !important;
            padding-left: 0 !important;
            text-align: left !important;
        }

        /*
         * Undo any previous movement of the metadata block.
         */
        .sales-order-customer-info .sales-order-meta {
            position: static !important;
            left: auto !important;
            right: auto !important;
            top: auto !important;

            width: max-content !important;
            min-width: 0 !important;
            margin: 0 !important;

            table-layout: auto !important;
            border-collapse: collapse !important;
        }

        .sales-order-customer-info .sales-order-meta td {
            line-height: 1.35 !important;
            vertical-align: baseline !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
        }

        /*
         * Fixed short label column.
         * This removes the giant gap from the previous 34%/66% layout.
         */
        .sales-order-customer-info .sales-order-meta .meta-label {
            width: 80px !important;
            min-width: 80px !important;
            max-width: 80px !important;

            text-align: left !important;

            padding-left: 0 !important;
            padding-right: 3px !important;

            margin: 0 !important;

            font-size: 12px !important;
            font-weight: 400 !important;

            white-space: nowrap !important;
        }

        .sales-order-customer-info .sales-order-meta .meta-value {
            width: auto !important;
            min-width: 0 !important;

            text-align: left !important;

            padding-left: 0 !important;
            padding-right: 0 !important;

            margin: 0 !important;

            font-size: 11px !important;
            font-weight: 400 !important;

            white-space: nowrap !important;
        }

        .sales-order-customer-info .sales-order-meta .terms-value {
            font-size: 11.5px !important;
        }

        /*
         * NO.66015
         *
         * NO. starts at the same approximate horizontal point
         * where metadata VALUES begin below it.
         */
        .sales-order-topline .invoice-heading {
            overflow: visible !important;
            text-align: left !important;
        }

        .sales-order-topline .invoice-align-row {
            display: flex !important;
            align-items: baseline !important;
            justify-content: flex-start !important;

            width: max-content !important;

            margin-left: 80px !important;
            margin-right: 0 !important;

            gap: 0 !important;
        }

        .sales-order-topline .invoice-align-row .invoice-label {
            display: inline !important;

            margin: 0 !important;
            padding: 0 !important;

            font-size: 12px !important;
            font-weight: 700 !important;
            color: #d00000 !important;

            white-space: nowrap !important;
        }

        .sales-order-topline .invoice-align-row .invoice-value {
            display: inline !important;

            margin: 0 !important;
            padding: 0 !important;

            font-size: 14px !important;
            font-weight: 700 !important;
            color: #d00000 !important;

            white-space: nowrap !important;
            font-variant-numeric: tabular-nums;
        }
</style>
</head>
<body>
    @include('partials.global.w68-loader')

    <div class="receipt">
        @php
            $rawPrintDate = trim((string) ($date ?? ''));
            $displayPrintDate = $rawPrintDate;

            // W68_SALES_PRINT_INVOICE_REF_20260921
            // Invoice number sent by Sales-Order.js for both new and history prints.
            $printInvoiceNumber = trim((string) request()->input('invoice_number', ''));
            if ($rawPrintDate !== '') {
                try {
                    $displayPrintDate = \Carbon\Carbon::parse($rawPrintDate)->format('d-m-Y');
                } catch (\Throwable $dateFormatError) {
                    $displayPrintDate = $rawPrintDate;

            // W68_SALES_PRINT_INVOICE_REF_20260921
            // Invoice number sent by Sales-Order.js for both new and history prints.
            $printInvoiceNumber = trim((string) request()->input('invoice_number', ''));
                }
            }
        @endphp
                @php
            // W68_SALES_ORDER_DYNAMIC_DISCOUNT_COLUMN_20260929
            // Hide LESS only when every printed item's discount is zero/blank.
            $showDiscountColumn = false;

            foreach ($items as $discountColumnItem) {
                if (abs((float) ($discountColumnItem['discount'] ?? 0)) > 0.000001) {
                    $showDiscountColumn = true;
                    break;
                }
            }
        @endphp
        @php
            // W68_SALES_ORDER_SIGNOFF_FROM_SALES_NOTE_20260929
            $w68SignoffNote = DB::connection('sales')
                ->table('sales_notes')
                ->where('sales_number', $salesNumber)
                ->first();

            $preparedBy = trim((string) ($w68SignoffNote->prepared_by ?? ''));
            $packedBy = trim((string) ($w68SignoffNote->packed_by ?? ''));
            $checkedBy = trim((string) ($w68SignoffNote->checked_by ?? ''));
        @endphp
<table class="sales-order-topline">
            <tr>
                <td class="top-left"></td>
                <td class="erw-heading">ERW</td>
                <td class="invoice-heading">
                    <div class="invoice-align-row">
                        <span class="invoice-label">NO.</span>
                        <span class="invoice-value">{{ $printInvoiceNumber }}</span>
                    </div>
                </td>
            </tr>
        </table>

        <div class="sep-dash sales-order-header-separator"></div>

        <div class="header-info sales-order-customer-info">
            <table>
                <tr>
                    <td style="width:60%">
                        <span class="label">CUSTOMER:</span>
                        <span class="header-value customer-value">{{ $customerName }}</span><br>

                        <span class="label">ADDRESS:</span>
                        <span class="header-value">{{ $customerAddress }}</span>
                    </td>

                                        <td style="width:40%; padding-left:0;">
                        <table class="sales-order-meta">
                            <tr>
                                <td class="meta-label">SN NO.:</td>
                                <td class="meta-value">{{ $salesNumber }}</td>
                            </tr>
                            <tr>
                                <td class="meta-label">DATE:</td>
                                <td class="meta-value">{{ $displayPrintDate }}</td>
                            </tr>
                            <tr>
                                <td class="meta-label">TIN:</td>
                                <td class="meta-value">{{ $customerTin }}</td>
                            </tr>
                            <tr>
                                <td class="meta-label">TERMS:</td>
                                <td class="meta-value terms-value">{{ $terms ?? '' }}</td>
                            </tr>
                            <tr>
                                <td class="meta-label">SALESMAN:</td>
                                <td class="meta-value">{{ $salesMan ?? '' }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        <div class="sep-dash sales-order-header-separator"></div>

                <table class="items-table {{ $showDiscountColumn ? '' : 'no-discount' }}">
            <thead>
                <tr class="sales-order-items-heading">
                    <th class="qty-col">QTY</th>
                    <th class="unit-col">UNIT</th>
                    <th class="code-col">PRODUCT CODE</th>
                    <th class="desc-col">ITEM</th>
                    <th class="price-col">UNIT PRICE</th>
                                        @if($showDiscountColumn)
                    <th class="disc-col">LESS</th>
                    @endif
                    <th class="total-col">TOTAL</th>
                </tr>
                <tr class="sales-order-heading-bottom">
                    <th colspan="{{ $showDiscountColumn ? 7 : 6 }}"></th>
                </tr>
            </thead>
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
                    @if($showDiscountColumn)                    <td class="disc-col c">
                        @if(!empty($item['discount']) && (float)$item['discount'] > 0)
                            {{ $item['discount'] }}%
                        @endif
                    </td>
                    @endif
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
            <tr>
                <td class="lb">
                    Additional Discount ({{ number_format($addlDiscountRate, 2) }}%):
                </td>
                <td class="vl">
                    {{ number_format($totalAddlDiscount, 2) }}
                </td>
            </tr>
        </table>
        @endif

                        <table class="sales-order-footer">
            <colgroup>
                @if($showDiscountColumn)
                    <col style="width:7%">
                    <col style="width:9%">
                    <col style="width:18%">
                    <col style="width:34%">
                    <col style="width:12%">
                    <col style="width:8%">
                    <col style="width:12%">
                @else
                    <col style="width:8.333333%">
                    <col style="width:10.333333%">
                    <col style="width:19.333333%">
                    <col style="width:35.333333%">
                    <col style="width:13.333333%">
                    <col style="width:13.333333%">
                @endif
            </colgroup>

            <tr>
                <td colspan="4" class="signoff-cell">
                    <span class="signoff-line">
                        <span class="signoff-label">PREPARED BY:</span>
                        <span class="signoff-editable" contenteditable="true" data-field="prepared_by">{{ $preparedBy }}</span>
                    </span>
                </td>
                <td class="financial-label">INVOICE AMOUNT:</td>

                @if($showDiscountColumn)
                    <td></td>
                @endif

                <td class="financial-value">{{ number_format($netAfterAddl, 2) }}</td>
            </tr>

            <tr>
                <td colspan="4" class="signoff-cell">
                    <span class="signoff-line">
                        <span class="signoff-label">PACKED BY:</span>
                        <span class="signoff-editable" contenteditable="true" data-field="packed_by">{{ $packedBy }}</span>
                    </span>
                </td>
                <td class="financial-label">NET AMOUNT:</td>

                @if($showDiscountColumn)
                    <td></td>
                @endif

                <td class="financial-value">{{ number_format($grandTotal, 2) }}</td>
            </tr>

            <tr>
                <td colspan="4" class="signoff-cell">
                    <span class="signoff-line">
                        <span class="signoff-label">CHECKED BY:</span>
                        <span class="signoff-editable" contenteditable="true" data-field="checked_by">{{ $checkedBy }}</span>
                    </span>
                </td>
                <td></td>

                @if($showDiscountColumn)
                    <td></td>
                @endif

                <td></td>
            </tr>

            <tr>
                <td colspan="4" class="signoff-cell">
                    <span class="signoff-line">
                        <span class="signoff-label">RECEIVED BY:</span>
                        <span class="signoff-value">&nbsp;</span>
                    </span>
                </td>
                <td></td>

                @if($showDiscountColumn)
                    <td></td>
                @endif

                <td></td>
            </tr>
        </table>
    </div>

    <div class="no-print" style="position:fixed;bottom:0;left:0;right:0;background:#fff;border-top:2px solid #800000;padding:8px 20px;display:flex;align-items:center;gap:10px;z-index:999;font-family:Arial,sans-serif;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:4px;font-size:10px;font-weight:bold;">
            <span>Margins (cm):</span>
            <label>T<input type="number" id="margin-top" value="1" step="0.1" min="0" max="10" style="width:40px;padding:2px 4px;border:1px solid #ccc;border-radius:4px;font-size:10px;"></label>
            <label>B<input type="number" id="margin-bottom" value="1" step="0.1" min="0" max="10" style="width:40px;padding:2px 4px;border:1px solid #ccc;border-radius:4px;font-size:10px;"></label>
            <label>L<input type="number" id="margin-left" value="1" step="0.1" min="0" max="10" style="width:40px;padding:2px 4px;border:1px solid #ccc;border-radius:4px;font-size:10px;"></label>
            <label>R<input type="number" id="margin-right" value="1" step="0.1" min="0" max="10" style="width:40px;padding:2px 4px;border:1px solid #ccc;border-radius:4px;font-size:10px;"></label>
        </div>
                <!-- W68_SALES_ORDER_SIGNOFF_SAVE_BUTTON_20260929 -->
        <button id="save-signoff-btn"
                onclick="saveSalesOrderSignoff(false)"
                style="padding:8px 16px;background:#15803d;border:none;border-radius:8px;font-size:11px;font-weight:bold;color:#fff;cursor:pointer;">
            Save Prepared/Packed/Checked
        </button>

        <span id="signoff-save-status"
              style="font-size:10px;font-weight:bold;color:#15803d;"></span>
<button onclick="saveSalesOrderSignoff(true).catch(function(){}).finally(function(){ window.print(); })" style="padding:8px 20px;background:#800000;border:none;border-radius:8px;font-size:11px;font-weight:bold;color:#fff;cursor:pointer;">Print Receipt</button>
        <button id="export-pdf-btn" onclick="saveSalesOrderSignoff(true).catch(function(){}).finally(function(){ exportPdf(); })" style="padding:8px 20px;background:#1e40af;border:none;border-radius:8px;font-size:11px;font-weight:bold;color:#fff;cursor:pointer;">Export PDF</button>
        <button onclick="window.close()" style="padding:8px 20px;background:#fff;border:2px solid #e2e8f0;border-radius:8px;font-size:11px;font-weight:bold;color:#475569;cursor:pointer;">Close</button>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        // W68_SALES_ORDER_SIGNOFF_EDITOR_20260929
        const w68SignoffSaveUrl = @json(url('/w68/sales-order/print-signoff'));
        const w68SignoffCsrf = @json(csrf_token());
        const w68SignoffSalesNumber = @json($salesNumber);

        function w68SignoffValue(field) {
            const el = document.querySelector(
                '.signoff-editable[data-field="' + field + '"]'
            );

            return el ? el.textContent.trim() : '';
        }

        async function saveSalesOrderSignoff(silent = false) {
            const status = document.getElementById('signoff-save-status');

            if (!silent && status) {
                status.textContent = 'Saving...';
            }

            try {
                const response = await fetch(w68SignoffSaveUrl, {
                    method: 'POST',
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': w68SignoffCsrf,
                    },
                    body: JSON.stringify({
                        sales_number: w68SignoffSalesNumber,
                        prepared_by: w68SignoffValue('prepared_by'),
                        packed_by: w68SignoffValue('packed_by'),
                        checked_by: w68SignoffValue('checked_by'),
                    }),
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(
                        data.message || 'Unable to save signoff.'
                    );
                }

                if (status) {
                    status.style.color = '#15803d';
                    status.textContent = 'Saved';
                }

                return true;
            }
            catch (error) {
                if (status) {
                    status.style.color = '#b91c1c';
                    status.textContent = error.message || 'Save failed';
                }

                if (!silent) {
                    alert(error.message || 'Unable to save signoff.');
                }

                throw error;
            }
        }

        document.querySelectorAll('.signoff-editable').forEach(function (el) {
            el.addEventListener('blur', function () {
                saveSalesOrderSignoff(true).catch(function () {});
            });
        });
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
        window.onload = function() { updatePageMargins(); };

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
