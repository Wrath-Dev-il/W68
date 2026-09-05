<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Note - <?php echo e($note->purchase_note_number); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            font-size: 11px;
        }

        .print-container {
            max-width: 8.5in;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }

        .header h1 {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .header p {
            font-size: 10px;
            margin: 2px 0;
        }

        .header .title {
            font-size: 16px;
            font-weight: bold;
            margin-top: 10px;
            text-decoration: underline;
        }

        .info-section {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }

        .info-row {
            display: table-row;
        }

        .info-label {
            display: table-cell;
            font-weight: bold;
            width: 150px;
            padding: 4px 0;
        }

        .info-value {
            display: table-cell;
            padding: 4px 0;
            border-bottom: 1px solid #000;
        }

        .separator {
            border-top: 1px solid #000;
            margin: 10px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        table th,
        table td {
            border: 1px solid #000;
            padding: 6px 8px;
            text-align: left;
        }

        table th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
            font-size: 10px;
        }

        table td {
            font-size: 10px;
        }

        table td.text-right {
            text-align: right;
        }

        table td.text-center {
            text-align: center;
        }

        .total-section {
            margin-top: 20px;
            text-align: right;
            font-size: 12px;
            font-weight: bold;
        }

        .remarks-section {
            margin-top: 30px;
            margin-bottom: 30px;
        }

        .remarks-label {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .remarks-content {
            border: 1px solid #000;
            padding: 10px;
            min-height: 60px;
        }

        .signatures {
            display: table;
            width: 100%;
            margin-top: 50px;
        }

        .signature-cell {
            display: table-cell;
            text-align: center;
            width: 33.33%;
            padding: 10px;
        }

        .signature-line {
            border-top: 1px solid #000;
            margin-bottom: 5px;
            padding-top: 40px;
        }

        .signature-label {
            font-size: 10px;
            font-weight: bold;
        }

        @media print {
            body {
                padding: 0;
            }
            
            .no-print {
                display: none;
            }

            @page {
                margin: 0.5in;
            }
        }

        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background-color: #7c2d12;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
        }

        .print-button:hover {
            background-color: #991b1b;
        }
    </style>
</head>
<body>
    <?php echo $__env->make('partials.global.w68-loader', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <button class="print-button no-print" onclick="window.print()">🖨️ Print</button>

    <div class="print-container">
        <!-- Header -->
        <div class="header">
            <h1>W68 AUTOPARTS & SERVICE CENTER</h1>
            <p>48 TIMOTHY ST. MULTINATIONAL VILLAGE PARAÑAQUE CITY</p>
            <p>TEL. NOS. 8553-9092 / 8829-0480 FAX NO. 8846-3985</p>
            <p>MOBILE NO. 09338137652 MOBILE/VIBER 09173239605</p>
            <div class="title">PURCHASE NOTE</div>
        </div>

        <!-- Supplier and PO Information -->
        <div class="info-section">
            <div class="info-row">
                <div class="info-label">Supplier Name:</div>
                <div class="info-value"><?php echo e($note->supplier->name ?? 'N/A'); ?></div>
                <div class="info-label" style="width: 100px; padding-left: 20px;">PO No.</div>
                <div class="info-value" style="width: 200px;"><?php echo e($note->purchase_note_number); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Address:</div>
                <div class="info-value"><?php echo e($note->supplier->address ?? 'N/A'); ?></div>
                <div class="info-label" style="width: 100px; padding-left: 20px;">PO Date:</div>
                <div class="info-value" style="width: 200px;"><?php echo e(\Carbon\Carbon::parse($note->date)->format('M d, Y')); ?></div>
            </div>
        </div>

        <!-- Items Table -->
        <?php if($items && (is_object($items) && $items->count() > 0) || (is_array($items) && count($items) > 0)): ?>
        <table>
            <thead>
                <tr>
                    <th style="width: 8%;">QTY</th>
                    <th style="width: 10%;">UNIT</th>
                    <th style="width: 20%;">PART NO.</th>
                    <th style="width: 32%;">DESCRIPTION</th>
                    <th style="width: 15%;">UNIT PRICE</th>
                    <th style="width: 15%;">SUBTOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if(!empty($item->is_transferred) && !empty($item->description) && str_starts_with($item->description, 'Transferred to Note')) continue; ?>
                <tr <?php if(!empty($item->is_transferred)): ?> style="color: #999; text-decoration: line-through;" <?php endif; ?>>
                    <td class="text-center"><?php echo e(number_format($item->quantity, 0)); ?></td>
                    <td class="text-center"><?php echo e($item->unit ?? '-'); ?></td>
                    <td><?php echo e($item->part_number ?? $item->product_code); ?></td>
                    <td><?php echo e($item->description); ?><?php if(!empty($item->is_transferred)): ?> <span style="color: #d97706; font-weight: bold; font-style: italic;">(Transferred)</span><?php endif; ?></td>
                    <td class="text-right"><?php echo e(number_format($item->unit_price, 2)); ?></td>
                    <td class="text-right"><?php echo e(number_format($item->total_price, 2)); ?></td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>

        <div class="separator"></div>

        <!-- Total (active items only) -->
        <div class="total-section">
            TOTAL: ₱ <?php echo e(number_format($items->where('is_transferred', '!=', true)->sum('total_price'), 2)); ?>

        </div>
        <?php elseif(empty($transferRemarks)): ?>
        <p style="text-align: center; font-style: italic; color: #888; padding: 20px;">No items found on this note.</p>
        <?php endif; ?>

        <!-- Remarks -->
        <div class="remarks-section">
            <div class="remarks-label">REMARKS:</div>
            <div class="remarks-content">
                <?php echo e($note->remarks ?? ''); ?>

                <?php if(!empty($transferRemarks)): ?>
                <br><br><strong>Transferred items:</strong><br><?php echo e($transferRemarks); ?>

                <?php endif; ?>
            </div>
        </div>

        <!-- Signatures -->
        <div class="signatures">
            <div class="signature-cell">
                <div class="signature-line"></div>
                <div class="signature-label">Prepared By</div>
            </div>
            <div class="signature-cell">
                <div class="signature-line"></div>
                <div class="signature-label">CHECKED BY</div>
            </div>
            <div class="signature-cell">
                <div class="signature-line"></div>
                <div class="signature-label">APPROVED BY</div>
            </div>
        </div>
    </div>

    <script>
        // Auto-print when page loads (optional - you can remove this if you don't want auto-print)
        // window.onload = function() {
        //     window.print();
        // };
    </script>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\hatdog\resources\views/Admin/Purchase/Purchase-Note-Print.blade.php ENDPATH**/ ?>