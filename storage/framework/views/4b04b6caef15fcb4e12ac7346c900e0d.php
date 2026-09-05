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
            <?php if($printType === 'invoice'): ?>
            @page { margin-top: 4.7cm; margin-bottom: 5cm; margin-left: 0.6cm; margin-right: 0.8cm; }
            <?php else: ?>
            @page { margin-top: 4.9cm; margin-bottom: 5cm; margin-left: 0.6cm; margin-right: 0.8cm; }
            <?php endif; ?>
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
    </style>
</head>
<body class="print-<?php echo e($printType === 'invoice' ? 'invoice' : 'order'); ?>">
    <?php echo $__env->make('partials.global.w68-loader', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <div class="receipt">
        <?php
            $rawPrintDate = trim((string) ($date ?? ''));
            $displayPrintDate = $rawPrintDate;
            if ($rawPrintDate !== '') {
                try {
                    $displayPrintDate = \Carbon\Carbon::parse($rawPrintDate)->format('d-m-Y');
                } catch (\Throwable $dateFormatError) {
                    $displayPrintDate = $rawPrintDate;
                }
            }
        ?>
        <div class="header-info">
            <div class="header-left">
                <?php if($printType === 'invoice'): ?>
                    <div class="customer-line"><?php echo e($customerName); ?></div>
                    <div class="address-line"><?php echo e($customerAddress); ?></div>
                <?php else: ?>
                    <div class="customer-line header-row"><span class="header-label">Customer:</span><span class="header-value"><?php echo e($customerName); ?></span></div>
                    <div class="address-line header-row"><span class="header-label">Address:</span><span class="header-value"><?php echo e($customerAddress); ?></span></div>
                <?php endif; ?>
            </div>
            <div class="header-right-content">
                <?php if($printType === 'invoice'): ?>
                    <div><?php echo e($displayPrintDate); ?></div>
                    <div><?php echo e($terms ?? ''); ?></div>
                    <div><?php echo e($customerTin); ?></div>
                <?php else: ?>
                    <div class="header-row"><span class="header-label">Sn no.:</span><span class="header-value"><?php echo e($salesNumber); ?></span></div>
                    <div class="header-row"><span class="header-label">Date:</span><span class="header-value"><?php echo e($displayPrintDate); ?></span></div>
                    <div class="header-row"><span class="header-label">Terms:</span><span class="header-value"><?php echo e($terms ?? ''); ?></span></div>
                    <div class="header-row"><span class="header-label">Salesman:</span><span class="header-value"><?php echo e($salesMan ?? ''); ?></span></div>
                <?php endif; ?>
            </div>
        </div>

        <table class="items-table">
            <tbody>
                <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
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
                ?>
                <tr>
                    <td class="qty-col c" style="white-space:nowrap"><?php echo e($printQtyLabel); ?></td>
                    <td class="unit-col c"><?php echo e($item['oum']); ?></td>
                    <td class="code-col"><?php echo e(!empty($item['price_code']) ? $item['price_code'] : $item['product_code']); ?></td>
                    <td class="desc-col"><?php echo e($printDescription); ?></td>
                    <td class="price-col r"><?php echo e(number_format($item['unit_price'], 2)); ?></td>
                    <td class="disc-col c">
                        <?php if(!empty($item['discount']) && (float)$item['discount'] > 0): ?>
                            <?php echo e($item['discount']); ?>%
                        <?php endif; ?>
                    </td>
                    <td class="total-col r"><?php echo e(number_format($item['subtotal'], 2)); ?></td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>

        <div class="sep-dash"></div>

        <table class="summary">
            <tr><td class="lb bd">TOTAL (QTY <?php echo e($totalQty); ?>):</td><td class="vl bd"><?php echo e(number_format($grossTotal, 2)); ?></td></tr>
        </table>

        <div class="sep-dash"></div>

        <?php if(!empty($rushText)): ?>
        <div class="rush-text"><?php echo e($rushText); ?></div>
        <?php endif; ?>

        <?php if($totalAddlDiscount > 0): ?>
        <table class="summary">
            <tr><td class="lb">Additional Discount (<?php echo e(number_format($addlDiscountRate, 2)); ?>%):</td><td class="vl"><?php echo e(number_format($totalAddlDiscount, 2)); ?></td></tr>
        </table>
        <?php endif; ?>

        <?php if($printType === 'invoice'): ?>
        <table class="summary invoice-financial-summary">
            <tr><td class="invoice-spacer"></td><td class="invoice-label bd">NET OF VAT:</td><td class="invoice-value bd"><?php echo e(number_format($grandTotal, 2)); ?></td></tr>
            <tr><td class="invoice-spacer"></td><td class="invoice-label">VAT(<?php echo e(number_format($vatRate, 2)); ?>%):</td><td class="invoice-value"><?php echo e(number_format($vatAmount, 2)); ?></td></tr>
            <tr><td class="invoice-spacer"></td><td class="invoice-label bd">TOTAL AMOUNT DUE:</td><td class="invoice-value bd"><?php echo e(number_format($netAfterAddl, 2)); ?></td></tr>
        </table>
        <?php else: ?>
        <table class="summary order-financial-summary">
            <tr><td class="order-spacer"></td><td class="order-label bd">INVOICE AMOUNT:</td><td class="order-value bd"><?php echo e(number_format($netAfterAddl, 2)); ?></td></tr>
            <tr><td class="order-spacer"></td><td class="order-label bd">NET AMOUNT:</td><td class="order-value bd"><?php echo e(number_format($grandTotal, 2)); ?></td></tr>
        </table>
        <?php endif; ?>
    </div>

    <div class="no-print" style="position:fixed;bottom:0;left:0;right:0;background:#fff;border-top:2px solid #800000;padding:8px 20px;display:flex;align-items:center;gap:10px;z-index:999;font-family:Arial,sans-serif;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:4px;font-size:10px;font-weight:bold;">
            <span>Margins (cm):</span>
            <label>T<input type="number" id="margin-top" value="<?php echo e($printType === 'invoice' ? '4.7' : '4.9'); ?>" step="0.1" min="0" max="10" style="width:40px;padding:2px 4px;border:1px solid #ccc;border-radius:4px;font-size:10px;"></label>
            <label>B<input type="number" id="margin-bottom" value="5" step="0.1" min="0" max="10" style="width:40px;padding:2px 4px;border:1px solid #ccc;border-radius:4px;font-size:10px;"></label>
            <label>L<input type="number" id="margin-left" value="0.6" step="0.1" min="0" max="10" style="width:40px;padding:2px 4px;border:1px solid #ccc;border-radius:4px;font-size:10px;"></label>
            <label>R<input type="number" id="margin-right" value="<?php echo e($printType === 'invoice' ? '0.8' : '0.8'); ?>" step="0.1" min="0" max="10" style="width:40px;padding:2px 4px;border:1px solid #ccc;border-radius:4px;font-size:10px;"></label>
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
<?php /**PATH C:\xampp\htdocs\hatdog\resources\views\Regular_User\sales\receipt-print.blade.php ENDPATH**/ ?>