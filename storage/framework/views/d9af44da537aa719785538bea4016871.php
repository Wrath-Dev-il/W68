<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unserved Details</title>
    <link rel="stylesheet" href="<?php echo e(asset('css/unserved-report.css')); ?>?v=<?php echo e(@filemtime(public_path('css/unserved-report.css')) ?: time()); ?>">
</head>
<body class="unserved-print-body">
    <?php echo $__env->make('partials.global.w68-loader', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <main class="unserved-print-sheet">
        <header class="unserved-print-header unserved-print-main-header">
            <h1>W68 AUTOPARTS AND SERVICE CENTER</h1>
            <h2>UNSERVED</h2>
            <p>GENERATED: <?php echo e(strtoupper($generatedAt)); ?> &nbsp; | &nbsp; DATE: <?php echo e(strtoupper($periodLabel)); ?></p>
            <?php if($salesman): ?>
                <p class="unserved-print-filters">SALES MAN: <?php echo e(strtoupper($salesman)); ?></p>
            <?php endif; ?>
        </header>

        <?php $__empty_1 = true; $__currentLoopData = $customerGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <section class="unserved-print-customer-section">
                <div class="unserved-print-customer-details">
                    <div><strong>CUSTOMER:</strong> <?php echo e(strtoupper($group['customer']['name'] ?? '—')); ?></div>
                    <div><strong>TIN:</strong> <?php echo e(strtoupper($group['customer']['tin'] ?? '—')); ?></div>
                    <div class="terms"><strong>TERMS:</strong> <?php echo e(strtoupper($group['customer']['terms'] ?? '—')); ?></div>
                </div>

                <table class="unserved-print-table">
                    <thead>
                        <tr>
                            <th>S.O. NO.</th>
                            <th>PRODUCT CODE</th>
                            <th>PART NO.</th>
                            <th>DESCRIPTION</th>
                            <th>ON HAND</th>
                            <th>SERVED</th>
                            <th>UNSERVED</th>
                            <th>UNIT PRICE</th>
                            <th>TOTAL AMOUNT</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $group['rows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr>
                                <td><?php echo e($row['so_no']); ?></td>
                                <td><?php echo e($row['product_code']); ?></td>
                                <td><?php echo e($row['part_number']); ?></td>
                                <td><?php echo e($row['description']); ?></td>
                                <td class="num"><?php echo e(rtrim(rtrim(number_format((float) $row['on_hand'], 2, '.', ','), '0'), '.')); ?></td>
                                <td class="num"><?php echo e(rtrim(rtrim(number_format((float) $row['served'], 2, '.', ','), '0'), '.')); ?></td>
                                <td class="num strong"><?php echo e(rtrim(rtrim(number_format((float) $row['unserved'], 2, '.', ','), '0'), '.')); ?></td>
                                <td class="num"><?php echo e(number_format((float) $row['unit_price'], 2)); ?></td>
                                <td class="num strong"><?php echo e(number_format((float) $row['total_amount'], 2)); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6" class="customer-total-label">CUSTOMER TOTAL</td>
                            <td class="num strong"><?php echo e(rtrim(rtrim(number_format((float) ($group['summary']['total_unserved'] ?? 0), 2, '.', ','), '0'), '.')); ?></td>
                            <td></td>
                            <td class="num strong"><?php echo e(number_format((float) ($group['summary']['total_amount'] ?? 0), 2)); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </section>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <section class="unserved-print-customer-section">
                <div class="unserved-print-customer-details">
                    <div><strong>CUSTOMER:</strong> <?php echo e(strtoupper($customerDetails['name'] ?? 'ALL CUSTOMERS')); ?></div>
                    <div><strong>TIN:</strong> <?php echo e(strtoupper($customerDetails['tin'] ?? '—')); ?></div>
                    <div class="terms"><strong>TERMS:</strong> <?php echo e(strtoupper($customerDetails['terms'] ?? '—')); ?></div>
                </div>
                <table class="unserved-print-table">
                    <thead>
                        <tr>
                            <th>S.O. NO.</th>
                            <th>PRODUCT CODE</th>
                            <th>PART NO.</th>
                            <th>DESCRIPTION</th>
                            <th>ON HAND</th>
                            <th>SERVED</th>
                            <th>UNSERVED</th>
                            <th>UNIT PRICE</th>
                            <th>TOTAL AMOUNT</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="9" class="empty">No unserved items found for the selected filters.</td></tr>
                    </tbody>
                </table>
            </section>
        <?php endif; ?>

        <footer class="unserved-print-footer">
            <span>Open / Partial Notes: <?php echo e(number_format($summary['open_partial_notes'])); ?></span>
            <span>Lines: <?php echo e(number_format($summary['line_items'])); ?></span>
            <span>Total Unserved: <?php echo e(rtrim(rtrim(number_format((float) $summary['total_unserved'], 2, '.', ','), '0'), '.')); ?></span>
            <span>Total Amount: <?php echo e(number_format((float) ($summary['total_amount'] ?? 0), 2)); ?></span>
        </footer>
    </main>

    <script src="<?php echo e(asset('js/unserved-report-print.js')); ?>?v=<?php echo e(@filemtime(public_path('js/unserved-report-print.js')) ?: time()); ?>"></script>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\hatdog\resources\views\Special_User\sales\unserved-report-print.blade.php ENDPATH**/ ?>