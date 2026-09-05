<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Online Report Print</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { width: 100%; margin: 0; padding: 0; }
        body { font-family: 'Arial Rounded MT Bold', Arial, sans-serif; font-size: 13px; color: #000; font-weight: bold; line-height: 1.2; }
        #printable-content { width: 100%; }
        .company-name { text-align: center; font-size: 16px; font-weight: bold; font-family: 'Arial Black', Arial, sans-serif; margin-bottom: 0px; }
        .report-title { text-align: center; font-size: 14px; font-weight: bold; font-family: 'Arial Black', Arial, sans-serif; margin-bottom: 5px; text-decoration: underline; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        .info-table td { padding: 0px 4px; font-size: 13px; vertical-align: top; }
        .info-table .label { font-weight: bold; }
        .items-table { width: 100% !important; border-collapse: collapse; margin-bottom: 3px; }
        .items-table th { border: 1px solid #000 !important; padding: 2px 2px !important; font-size: 13px; font-weight: bold; font-family: 'Arial Black', Arial, sans-serif; text-align: center; text-transform: uppercase; background: #fff; }
        .items-table td { border: 1px solid #000 !important; padding: 1px 2px !important; font-size: 13px; font-weight: bold; word-wrap: break-word; }
        .items-table td.c { text-align: center; }
        .items-table td.r { text-align: right; }
        .totals { text-align: right; margin-top: 2px; font-size: 13px; font-weight: bold; }
        .totals .line { border-top: 1px solid #000; width: 200px; margin-left: auto; margin-top: 2px; margin-bottom: 2px; }
        .item-block { margin-bottom: 4px; }
        .item-inner { display: flex; width: 100%; page-break-inside: avoid; gap: 4px; }
        .item-inner .side { flex: 1; padding: 3px; box-sizing: border-box; border: 1px solid #000; display: flex; flex-direction: column; }
        .item-inner .side:last-child { border-left: 2px solid #000; }
        .detail-row { font-size: 11px; margin-bottom: 0px; }
        .detail-row .lbl { font-weight: bold; }
        .sales-table { width: 100%; border-collapse: collapse; margin-top: 2px; }
        .sales-table th { border: 1px solid #000; padding: 1px 2px; font-size: 11px; font-weight: bold; font-family: 'Arial Black', Arial, sans-serif; text-align: center; background: #fff; }
        .sales-table td { border: 1px solid #000; padding: 1px 2px; font-size: 11px; font-weight: bold; text-align: center; }
        .costing-inline { margin-top: 2px; font-size: 11px; font-weight: 600; white-space: normal; line-height: 1.2; }
        .row-label { text-align: center; font-weight: bold; background: #f0f0f0; }
        .note-section { page-break-inside: auto; }
        @page { 
            size: 8.5in 11in portrait; 
            margin-top: 0.5cm;
            margin-bottom: 5.3in;
            margin-left: 1cm;
            margin-right: 1cm;
        }
        .stock-summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-top: 6px;
            font-size: 11px;
            padding: 1px 2px;
        }
        .stock-summary span {
            white-space: nowrap;
        }
        @media print {
            body { margin: 0; padding: 0; background: #fff; display: block !important; }
            #printable-content { display: block !important; }
            .no-print { display: none !important; }
            .note-section { page-break-inside: auto; }
            .item-block { page-break-inside: avoid; break-inside: avoid; }
        }
    </style>
</head>
<body>
    <?php echo $__env->make('partials.global.w68-loader', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<div id="printable-content">
<?php
$years = [];
if (!empty($dateRanges)) {
    foreach ($dateRanges as $dr) {
        if (is_array($dr) || is_object($dr)) {
            $dr = (array)$dr;
            if (($dr['type'] ?? '') === 'annual' && !empty($dr['value'])) {
                $years[] = $dr['value'];
            } elseif (($dr['type'] ?? '') === 'monthly' && !empty($dr['value'])) {
                $parts = explode('-', $dr['value']);
                if (count($parts) === 2) $years[] = $parts[0];
            } elseif (($dr['type'] ?? '') === 'specific' && !empty($dr['value'])) {
                $parts = explode('|', $dr['value']);
                if (count($parts) === 2) {
                    $y1 = date('Y', strtotime($parts[0]));
                    $y2 = date('Y', strtotime($parts[1]));
                    if ($y1) $years[] = $y1;
                    if ($y2) $years[] = $y2;
                }
            }
        }
    }
}
$years = array_unique($years);
sort($years);
$years = array_slice($years, 0, 10);
$sortedYears = array_reverse(array_values($years));
$colCount = count($sortedYears) > 0 ? count($sortedYears) : 1;
?>

<?php if(empty($notes)): ?>
<div style="text-align:center;padding:40px;font-size:16px;color:#666;">
    <p>No sales notes found for this report.</p>
    <p style="font-size:12px;margin-top:8px;">The report may have been generated with invalid note IDs. Please generate a new report.</p>
</div>
<?php else: ?>
<?php $__currentLoopData = $notes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $noteIdx => $note): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<div class="note-section"<?php if(!$loop->first): ?> style="page-break-before:always;"<?php endif; ?>>
<div class="company-name">W68 AUTOPARTS &amp; SERVICE CENTER</div>
<div class="report-title"><?php echo e($note['customer_name']); ?></div>

<table class="info-table">
    <tr>
        <td style="width:50%;" class="label">INVOICE NO.: <span style="color:red"><?php echo e($note['invoice_no'] ?: '________________________'); ?></span></td>
        <td style="width:50%;text-align:right;" class="label">SN NO.: <?php echo e($note['sales_number']); ?></td>
    </tr>
    <tr>
        <td class="label"><?php echo e($note['customer_name']); ?></td>
        <td style="text-align:right;" class="label">DATE: <?php echo e($reportDate); ?></td>
    </tr>
    <tr>
        <td class="label">ADDRESS: <?php echo e($note['customer_address']); ?></td>
        <td style="text-align:right;" class="label">SALESMAN: <?php echo e($note['salesman'] ?: '---'); ?></td>
    </tr>
</table>

<?php
$grandTotalAmount = 0;
foreach ($note['items'] as &$item) {
    $price = isset($prices[(string)$item['product_id']]) ? (float)$prices[(string)$item['product_id']] : (float)($item['price_online'] ?? 0);
    $item['_sub'] = $price;
    $item['_total'] = $item['quantity'] * $price;
    $grandTotalAmount += $item['_total'];
}
unset($item);
?>

<table class="items-table">
    <thead>
        <tr>
            <th style="width:6%">QTY</th>
            <th style="width:6%">UNIT</th>
            <th style="width:14%">PRODUCT CODE</th>
            <th style="width:38%">DESCRIPTION / APPLICATION</th>
            <th style="width:9%">SUB AMOUNT</th>
            <th style="width:9%">TOTAL AMOUNT</th>
        </tr>
    </thead>
    <tbody>
        <?php $__currentLoopData = $note['items']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
            <td class="c"><?php echo e($item['quantity'] + ($item['additional_qty'] ?? 0)); ?></td>
            <td class="c"><?php echo e($item['oum']); ?></td>
            <td><?php echo e($item['product_code']); ?></td>
            <td><?php echo e($item['description']); ?></td>
            <td class="r"><?php echo e(number_format($item['_sub'], 2)); ?></td>
            <td class="r"><?php echo e(number_format($item['_total'], 2)); ?></td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
</table>

<div class="totals">
    <div class="line"></div>
    <div>INVOICE AMOUNT: <span style="font-size:14px;"><?php echo e(number_format($grandTotalAmount, 2)); ?></span></div>
    <div style="margin-top:2px;">NET TOTAL: <span style="font-size:14px;"><?php echo e(number_format($grandTotalAmount, 2)); ?></span></div>
</div>

<?php
    $_supplierName = function($pid, $itemSupplierId = null) use ($productSupplierIds, $supplierNames) {
        $sid = $itemSupplierId ?: ($productSupplierIds[(int)$pid] ?? null);
        return $sid ? ($supplierNames[(int)$sid] ?? 'N/A') : 'N/A';
    };
    $_unitVal = function($pid, $fallback) use ($productUnits) {
        $u = $productUnits[(int)$pid] ?? '';
        return $u ?: ($fallback ?? '');
    };
    $_costInline = function($item, $prefix = '') {
        $costFound = !empty($item[$prefix . 'cost_found']);
        if (!$costFound) {
            return '<div class="costing-inline">COSTING: N/A</div>';
        }

        $unitPrice = number_format((float) ($item[$prefix . 'cost'] ?? 0), 2);
        $date = !empty($item[$prefix . 'cost_date'])
            ? strtoupper(\Carbon\Carbon::parse($item[$prefix . 'cost_date'])->format('F d, Y'))
            : 'N/A';
        $supplierName = strtoupper((string) ($item[$prefix . 'cost_supplier_name'] ?? 'N/A'));

        return '<div class="costing-inline">COSTING: '
            . e($unitPrice)
            . ' ('
            . e($date)
            . ') '
            . e($supplierName)
            . '</div>';
    };
    $_stockUnit = function($val, $unit) {
        return number_format((float)$val) . ($unit ? ' ' . $unit : '');
    };
?>
<?php $__currentLoopData = $note['paired']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pair): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php
    $leftItem = $note['items'][$pair['left_idx']];
    $hasCp = $pair['has_cp'];
    $cp = $hasCp ? ($leftItem['counter_part'] ?? null) : null;
    $rightItem = (!$hasCp && $pair['right_idx'] !== null) ? $note['items'][$pair['right_idx']] : null;

    $_lPid = (int)($leftItem['product_id'] ?? 0);
    $_lCode = $leftItem['product_code'] ?? '';
    $_lPn = $leftItem['part_number'] ?: ($_lCode ?: ($leftItem['description'] ?: 'N/A'));
    $_lPrice = ($leftItem['selling_price'] ?? 0) > 0 ? number_format($leftItem['selling_price'], 2)
        : (($leftItem['price_online'] ?? 0) > 0 ? number_format($leftItem['price_online'], 2)
        : (($leftItem['unit_price'] ?? 0) > 0 ? number_format($leftItem['unit_price'], 2) : '0.00'));
    $_lUnit = $_unitVal($_lPid, $leftItem['oum'] ?? '');
    $_lComp = $localBalanceStocks[$_lPid] ?? 0;
    $_lOnline = $onlineBalanceStocks[$_lPid] ?? null;
?>
<div class="item-block">
<div class="item-inner">
<div class="side">
    <div class="detail-row"><span class="lbl">PART NUMBER:</span> <span style="color:red"><?php echo e($_lPn); ?></span></div>
    <?php echo $_costInline($leftItem); ?>

    <div class="detail-row"><span class="lbl">PRICE LIST:</span> <span style="color:red"><?php echo e($_lPrice); ?></span></div>

    <table class="sales-table">
        <thead>
            <tr>
                <th>YEAR</th>
                <th>TOTAL</th>
                <th>LOCAL</th>
                <th>ONLINE</th>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $sortedYears; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $yr): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
            $loc = $salesByProduct[$_lPid . '-' . $yr . '-local'] ?? 0;
            $onl = $salesByProduct[$_lPid . '-' . $yr . '-online'] ?? 0;
            ?>
            <tr>
                <td class="row-label"><?php echo e($yr); ?></td>
                <td style="font-weight:bold;"><?php echo e($loc + $onl); ?></td>
                <td><?php echo e($loc); ?></td>
                <td><?php echo e($onl); ?></td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
    <div class="stock-summary">
        <span>COMPT. STOCK: <strong><?php echo e($_stockUnit($_lComp, $_lUnit)); ?></strong></span>
        <span>STOCK ONLINE: <strong><?php echo e($_lOnline !== null ? $_stockUnit($_lOnline, $_lUnit) : 'N/A'); ?></strong></span>
    </div>
</div>
<div class="side">
    <?php if($hasCp && $cp): ?>
    <?php
        $_cPid = (int)($cp['product_id'] ?? 0);
        $_cCode = $cp['product_code'] ?? '';
        $_cPn = $cp['part_number'] ?: ($_cCode ?: ($cp['description'] ?: '--'));
        $_cPrice = ($leftItem['cp_selling_price'] ?? 0) > 0 ? number_format($leftItem['cp_selling_price'], 2) : '0.00';
        $_cUnit = $_unitVal($_cPid, $cp['oum'] ?? '');
        $_cComp = $_cPid > 0 ? ($localBalanceStocks[$_cPid] ?? 0) : 0;
        $_cOnline = $_cPid > 0 ? ($onlineBalanceStocks[$_cPid] ?? null) : null;
    ?>
    <div class="detail-row"><span class="lbl">PART NUMBER:</span> <span style="color:red"><?php echo e($_cPn); ?></span></div>
    <?php echo $_costInline($leftItem, 'cp_'); ?>

    <div class="detail-row"><span class="lbl">PRICE LIST:</span> <span style="color:red"><?php echo e($_cPrice); ?></span></div>

    <table class="sales-table">
        <thead>
            <tr>
                <th>YEAR</th>
                <th>TOTAL</th>
                <th>LOCAL</th>
                <th>ONLINE</th>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $sortedYears; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $yr): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
            $cLoc = $_cPid > 0 ? ($salesByProduct[$_cPid . '-' . $yr . '-local'] ?? 0) : 0;
            $cOnl = $_cPid > 0 ? ($salesByProduct[$_cPid . '-' . $yr . '-online'] ?? 0) : 0;
            ?>
            <tr>
                <td class="row-label"><?php echo e($yr); ?></td>
                <td style="font-weight:bold;"><?php echo e($cLoc + $cOnl); ?></td>
                <td><?php echo e($cLoc); ?></td>
                <td><?php echo e($cOnl); ?></td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
    <div class="stock-summary">
        <span>COMPT. STOCK: <strong><?php echo e($_cPid > 0 ? $_stockUnit($_cComp, $_cUnit) : '---'); ?></strong></span>
        <span>STOCK ONLINE: <strong><?php echo e($_cPid > 0 && $_cOnline !== null ? $_stockUnit($_cOnline, $_cUnit) : 'N/A'); ?></strong></span>
    </div>
    <?php elseif($rightItem): ?>
    <?php
        $_rPid = (int)($rightItem['product_id'] ?? 0);
        $_rCode = $rightItem['product_code'] ?? '';
        $_rPn = $rightItem['part_number'] ?: ($_rCode ?: ($rightItem['description'] ?: 'N/A'));
        $_rPrice = ($rightItem['selling_price'] ?? 0) > 0 ? number_format($rightItem['selling_price'], 2)
            : (($rightItem['price_online'] ?? 0) > 0 ? number_format($rightItem['price_online'], 2)
            : (($rightItem['unit_price'] ?? 0) > 0 ? number_format($rightItem['unit_price'], 2) : '0.00'));
        $_rUnit = $_unitVal($_rPid, $rightItem['oum'] ?? '');
        $_rComp = $localBalanceStocks[$_rPid] ?? 0;
        $_rOnline = $onlineBalanceStocks[$_rPid] ?? null;
    ?>
    <div class="detail-row"><span class="lbl">PART NUMBER:</span> <span style="color:red"><?php echo e($_rPn); ?></span></div>
    <?php echo $_costInline($rightItem); ?>

    <div class="detail-row"><span class="lbl">PRICE LIST:</span> <span style="color:red"><?php echo e($_rPrice); ?></span></div>

    <table class="sales-table">
        <thead>
            <tr>
                <th>YEAR</th>
                <th>TOTAL</th>
                <th>LOCAL</th>
                <th>ONLINE</th>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $sortedYears; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $yr): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
            $rLoc = $salesByProduct[$_rPid . '-' . $yr . '-local'] ?? 0;
            $rOnl = $salesByProduct[$_rPid . '-' . $yr . '-online'] ?? 0;
            ?>
            <tr>
                <td class="row-label"><?php echo e($yr); ?></td>
                <td style="font-weight:bold;"><?php echo e($rLoc + $rOnl); ?></td>
                <td><?php echo e($rLoc); ?></td>
                <td><?php echo e($rOnl); ?></td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
    <div class="stock-summary">
        <span>COMPT. STOCK: <strong><?php echo e($_stockUnit($_rComp, $_rUnit)); ?></strong></span>
        <span>STOCK ONLINE: <strong><?php echo e($_rOnline !== null ? $_stockUnit($_rOnline, $_rUnit) : 'N/A'); ?></strong></span>
    </div>
    <?php else: ?>
    <div style="height:100%;min-height:100px;"></div>
    <?php endif; ?>
</div>
</div>
</div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<?php endif; ?>
</div>

<div class="no-print" style="position:fixed;bottom:0;left:0;right:0;background:#fff;border-top:2px solid #800000;padding:10px 20px;display:flex;justify-content:center;gap:12px;z-index:999;box-shadow:0 -4px 20px rgba(0,0,0,0.08);">
    <button onclick="window.history.back()" style="padding:10px 24px;background:#fff;border:2px solid #e2e8f0;border-radius:10px;font-size:12px;font-weight:bold;color:#475569;cursor:pointer;display:flex;align-items:center;gap:6px;"><span>&larr;</span> Go Back</button>
    <button onclick="window.print()" style="padding:10px 24px;background:#800000;border:none;border-radius:10px;font-size:12px;font-weight:bold;color:#fff;cursor:pointer;box-shadow:0 4px 12px rgba(128,0,0,0.3);display:flex;align-items:center;gap:6px;">Print Report</button>
    <button onclick="saveAsPDF()" id="pdf-btn" style="padding:10px 24px;background:#1e40af;border:none;border-radius:10px;font-size:12px;font-weight:bold;color:#fff;cursor:pointer;box-shadow:0 4px 12px rgba(30,64,175,0.3);display:flex;align-items:center;gap:6px;">Save as PDF</button>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer" class="no-print"></script>
<script>
window.onload = function() { window.print(); };
function saveAsPDF() {
    const btn = document.getElementById('pdf-btn');
    btn.textContent = 'Generating PDF...';
    btn.disabled = true;
    const element = document.getElementById('printable-content');
    const opt = {
        margin: [3, 3, 3, 3],
        filename: 'Online-Report-<?php echo e($note["sales_number"] ?? "report"); ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, logging: false, allowTaint: true, backgroundColor: '#ffffff', foreignObjectRendering: false, windowHeight: element.scrollHeight },
        jsPDF: { unit: 'in', format: [5.5, 8.5], orientation: 'portrait' }
    };
    html2pdf().set(opt).from(element).save();
    setTimeout(() => { btn.innerHTML = 'Save as PDF'; btn.disabled = false; }, 1500);
}
</script>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\hatdog\resources\views\Regular_User\sales\online-report-print.blade.php ENDPATH**/ ?>