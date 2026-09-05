
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>⚠ DEBUG — Price List Height Measurement</title>
<style>
/* ── Debug chrome ──────────────────────────────────────────────────────── */
body { margin: 0; padding: 0; font-family: monospace; background: #111; color: #e0e0e0; }
#debug-banner {
    background: #ff4444; color: white; font-size: 14px; font-weight: bold;
    padding: 8px 16px; text-align: center; position: sticky; top: 0; z-index: 9999;
}
#controls {
    display: flex; gap: 10px; padding: 10px 16px;
    background: #1e1e1e; border-bottom: 2px solid #444;
}
#controls button {
    padding: 8px 18px; font-size: 13px; font-weight: bold; border: none;
    border-radius: 4px; cursor: pointer;
}
#btn-copy     { background: #2196f3; color: white; }
#btn-download { background: #4caf50; color: white; }
#btn-measure  { background: #ff9800; color: white; }
#controls button:hover { opacity: 0.85; }
#status { padding: 10px 16px; font-size: 13px; color: #aaa; }

/* ── Report panel ──────────────────────────────────────────────────────── */
#report {
    padding: 16px;
    white-space: pre;
    font-size: 12px;
    line-height: 1.5;
    background: #1e1e1e;
    color: #d4d4d4;
    min-height: 200px;
    border-top: 2px solid #333;
}

/* ── Hidden print render area ─────────────────────────────────────────── */
#render-area {
    position: absolute;
    top: 0; left: -9999px;
    /* Must match the print content width exactly: Letter 215.9mm − 10mm×2 margins at 96dpi */
    width: 740px;
    background: white;
    visibility: hidden;
}

/* ── Exact @page print CSS (copied from Product_PriceList.blade.php) ──── */
#render-area .print-table {
    width: 100%;
    table-layout: fixed;
    border-collapse: collapse;
    border: 2px solid black;
}
#render-area .print-table tbody td {
    border: 2px solid black;
    padding: 4px 6px;
    font-size: 14px;
    font-weight: 700;
    color: black;
    line-height: 1.2;
    vertical-align: top;
}
#render-area .print-table .desc-header {
    font-size: 18px;
    padding: 6px 10px;
    font-weight: 900;
    text-transform: uppercase;
    color: black;
    background: #f1f5f9;
    border: 2px solid black;
    text-align: left;
}
#render-area .price-col {
    font-size: 14px;
    font-weight: 900;
    color: #1e3a8a;
    text-align: right;
    white-space: nowrap;
}
#render-area .page-hdr {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    border-bottom: 2px solid black;
    padding-bottom: 3px;
    margin-bottom: 4px;
}
#render-area .page-ftr {
    margin-top: 4px;
    border-top: 1px solid black;
    padding-top: 2px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
</style>
</head>
<body>
    <?php echo $__env->make('partials.global.w68-loader', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>



<div id="debug-banner">
    ⚠ TEMPORARY DIAGNOSTIC PAGE — W68 Price List Height Measurement ⚠
    &nbsp;|&nbsp; Remove this route and view after pagination is fixed
    &nbsp;|&nbsp; /admin/debug/pricelist-measure
</div>

<div id="controls">
    <button id="btn-measure"  onclick="runMeasurement()">▶ Run Measurement</button>
    <button id="btn-copy"     onclick="copyReport()"     disabled>📋 Copy Diagnostic Report</button>
    <button id="btn-download" onclick="downloadReport()" disabled>💾 Download .txt</button>
</div>
<div id="status">Click "Run Measurement" to render the print layout and measure all elements.</div>
<pre id="report">Waiting for measurement...</pre>



<?php
    // ── Sort (exact copy from production blade) ───────────────────────
    $sortedProducts = $products->sort(function($a, $b) {
        $descA = trim(strtoupper($a->description ?? 'NO DESCRIPTION'));
        $descB = trim(strtoupper($b->description ?? 'NO DESCRIPTION'));
        $descDiff = strcmp($descA, $descB);
        if ($descDiff !== 0) return $descDiff;
        $catA = trim(strtoupper($a->category ?? ''));
        $catB = trim(strtoupper($b->category ?? ''));
        $catDiff = strcmp($catA, $catB);
        if ($catDiff !== 0) return $catDiff;
        return strcmp(
            trim(strtoupper($a->application ?? '')),
            trim(strtoupper($b->application ?? ''))
        );
    });

    // ── Constants ────────────────────────────────────────────────────
    $availHeight    = 257;
    $companyHeaderH = 13;
    $footerH        = 6;
    $descH          = 10;
    $netAvail       = $availHeight - $companyHeaderH - $footerH;

    // ── Paginator (exact copy from production blade) ──────────────────
    $printPages       = [];
    $currentPageItems = [];
    $currentH         = 0;
    $currentDescs     = [];
    $lastDesc         = null;

    foreach ($sortedProducts as $product) {
        $desc     = trim(strtoupper($product->description ?? 'NO DESCRIPTION'));
        $isPos    = !empty($product->position) || !empty($product->Position);
        $appText  = trim($product->application ?? $product->Application ?? '');
        $posText  = trim($product->position    ?? $product->Position    ?? '');
        $appLines = max(1, (int) ceil(mb_strlen($appText) / 32));
        $posLines = ($posText !== '') ? max(1, (int) ceil(mb_strlen($posText) / 32)) : 0;
        $h        = 7.62 + ($appLines - 1) * 4.5 + $posLines * 4.5;
        $isNew    = ($desc !== $lastDesc);
        $needed   = $h + ($isNew ? $descH : 0);

        if ($currentH + $needed > $netAvail && !empty($currentPageItems)) {
            $printPages[]     = [
                'items'        => $currentPageItems,
                'descriptions' => array_values(array_unique($currentDescs)),
                'finalH'       => $currentH,
            ];
            $currentPageItems = [];
            $currentH         = 0;
            $currentDescs     = [];
            $isNew            = true;
            $needed           = $h + $descH;
        }

        if ($isNew) {
            $currentH      += $descH;
            $currentDescs[] = $desc;
            $lastDesc        = $desc;
        }

        $currentPageItems[] = [
            'product'    => $product,
            'isNewGroup' => $isNew,
            'isPos'      => $isPos,
            'phpAppLines' => $appLines,
            'phpPosLines' => $posLines,
            'phpH'        => round($h, 2),
        ];
        $currentH += $h;
    }
    if (!empty($currentPageItems)) {
        $printPages[] = [
            'items'        => $currentPageItems,
            'descriptions' => array_values(array_unique($currentDescs)),
            'finalH'       => $currentH,
        ];
    }

    $totalPrintPages = count($printPages);
?>

<div id="render-area">
    <?php $__currentLoopData = $printPages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pageIndex => $pageData): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="print-page"
         data-page="<?php echo e($pageIndex + 1); ?>"
         data-php-h="<?php echo e($pageData['finalH']); ?>"
         data-cats="<?php echo e(count($pageData['descriptions'])); ?>">

        
        <div class="page-hdr" data-role="header">
            <div>
                <div style="font-family:'Arial Black',Arial,sans-serif;font-size:20px;font-weight:900;text-transform:uppercase;line-height:1;color:black;letter-spacing:-0.02em;">W68 AUTO PARTS</div>
                <div style="font-family:'Arial Black',Arial,sans-serif;font-size:18px;font-weight:900;text-transform:uppercase;line-height:1;color:black;letter-spacing:0.05em;">PRICE LIST</div>
            </div>
            <div style="font-family:'Arial Black',Arial,sans-serif;font-size:18px;font-weight:900;text-transform:uppercase;color:black;line-height:1;"><?php echo e(strtoupper(date('M d, Y'))); ?></div>
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
            <?php $__currentLoopData = $pageData['items']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if($item['isNewGroup']): ?>
                <tr data-type="cat">
                    <th colspan="5" class="desc-header"><?php echo e(trim(strtoupper($item['product']->description ?? 'NO DESCRIPTION'))); ?></th>
                </tr>
                <?php endif; ?>
                <?php
                    $_appText = $item['product']->application ?? $item['product']->Application ?? '';
                    $_posText = $item['product']->position ?? $item['product']->Position ?? '';
                ?>
                <tr data-type="<?php echo e($item['isPos'] ? 'pos' : 'row'); ?>"
                    data-app="<?php echo e(e($_appText)); ?>"
                    data-pos="<?php echo e(e($_posText)); ?>"
                    data-code="<?php echo e(e($item['product']->product_code ?? '')); ?>"
                    data-app-len="<?php echo e(mb_strlen($_appText)); ?>"
                    data-pos-len="<?php echo e(mb_strlen($_posText)); ?>"
                    data-php-app-lines="<?php echo e($item['phpAppLines'] ?? '?'); ?>"
                    data-php-pos-lines="<?php echo e($item['phpPosLines'] ?? '?'); ?>"
                    data-php-h="<?php echo e($item['phpH'] ?? '?'); ?>">
                    <td style="border:2px solid black;width:15%;"><?php echo e($item['product']->product_code); ?></td>
                    <td style="border:2px solid black;width:20%;font-family:monospace;"><?php echo e($item['product']->part_number ?? '---'); ?></td>
                    <td style="border:2px solid black;width:35%;">
                        <div data-role="app-div"><?php echo e($item['product']->application ?? $item['product']->Application ?? '---'); ?></div>
                        <?php $pos = $item['product']->position ?? $item['product']->Position; ?>
                        <?php if($pos): ?>
                        <div data-role="pos-div" style="font-size:11px;font-weight:700;text-transform:uppercase;color:black;margin-top:2px;border-top:1px solid #ccc;padding-top:1px;">
                            <span style="font-size:9px;margin-right:2px;">POS:</span><?php echo e($pos); ?>

                        </div>
                        <?php endif; ?>
                    </td>
                    <td style="border:2px solid black;width:15%;text-transform:uppercase;"><?php echo e($item['product']->category); ?></td>
                    <td style="border:2px solid black;width:15%;" class="price-col">₱<?php echo e(number_format($item['product']->selling_price, 2)); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>

        
        <div class="page-ftr" data-role="footer">
            <span style="font-family:'Arial Black',Arial,sans-serif;font-size:12px;font-weight:900;text-transform:uppercase;color:black;">W68 AUTO PARTS &mdash; PRICE LIST</span>
            <span style="font-family:'Arial Black',Arial,sans-serif;font-size:12px;font-weight:900;text-transform:uppercase;color:black;white-space:nowrap;">PAGE <?php echo e($pageIndex + 1); ?> OF <?php echo e($totalPrintPages); ?></span>
        </div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>

<script>
// ══════════════════════════════════════════════════════════════════════
// ⚠ DIAGNOSTIC SCRIPT — TEMPORARY — REMOVE WITH THIS VIEW
// ══════════════════════════════════════════════════════════════════════

let reportText = '';

function runMeasurement() {
    document.getElementById('status').textContent = 'Rendering and measuring… please wait.';

    // Make render-area visible (still off-screen) so layout is computed
    const area = document.getElementById('render-area');
    area.style.visibility = 'visible';

    // Let browser reflow
    requestAnimationFrame(() => requestAnimationFrame(() => {
        const lines = [];
        const push = s => lines.push(s);

        // ── Calibration ─────────────────────────────────────────────
        const cal = document.createElement('div');
        cal.style.cssText = 'position:fixed;top:-9999px;width:100mm;height:1px;';
        document.body.appendChild(cal);
        const mmPx = cal.getBoundingClientRect().width / 100;
        document.body.removeChild(cal);
        const toMm = px => (px / mmPx).toFixed(2);

        push('═══════════════════════════════════════════════════════════════════════════');
        push('  W68 PRICE LIST — ACTUAL BROWSER DOM HEIGHT MEASUREMENT');
        push('═══════════════════════════════════════════════════════════════════════════');
        push('');
        push('  Calibration  : 1mm = ' + mmPx.toFixed(4) + 'px');
        push('  Render width : ' + area.getBoundingClientRect().width.toFixed(1) + 'px = ' + toMm(area.getBoundingClientRect().width) + 'mm');
        push('  Timestamp    : ' + new Date().toISOString());
        push('  PHP pages    : <?php echo e($totalPrintPages); ?>');
        push('  PHP paginator: availH=257 hdrH=13 ftrH=6 descH=10 netAvail=238 rowH=dynamic browser-calibrated');
        push('  Paper height : 261.4mm usable (Letter @page 8mm top 10mm bottom)');
        push('  Paper (Chromium): ~257.4mm (minus ~4mm Chromium internal overhead)');
        push('');

        const pages = area.querySelectorAll('.print-page');
        const PAPER_MM = 257.4;
        const allDataRows = [];

        // ── Per-page measurements ────────────────────────────────────
        pages.forEach(function(pg, pi) {
            const r = pg.getBoundingClientRect();
            const offsetH  = pg.offsetHeight;
            const scrollH  = pg.scrollHeight;
            const clientH  = pg.clientHeight;
            const rectH    = r.height;
            const phpH     = pg.dataset.phpH;
            const cats     = pg.dataset.cats;

            const hdrEl  = pg.querySelector('[data-role="header"]');
            const ftrEl  = pg.querySelector('[data-role="footer"]');
            const tblEl  = pg.querySelector('table');
            const hdrH   = hdrEl ? hdrEl.getBoundingClientRect().height : 0;
            const ftrH   = ftrEl ? ftrEl.getBoundingClientRect().height : 0;
            const tblH   = tblEl ? tblEl.getBoundingClientRect().height : 0;

            const overflow = rectH - PAPER_MM * mmPx;

            push('───────────────────────────────────────────────────────────────────────────');
            push('  PAGE ' + (pi + 1) + ' OF <?php echo e($totalPrintPages); ?>');
            push('───────────────────────────────────────────────────────────────────────────');
            push('  offsetHeight  : ' + offsetH + 'px  = ' + toMm(offsetH) + 'mm');
            push('  scrollHeight  : ' + scrollH + 'px  = ' + toMm(scrollH) + 'mm');
            push('  clientHeight  : ' + clientH + 'px  = ' + toMm(clientH) + 'mm');
            push('  getBoundingClientRect.height : ' + rectH.toFixed(1) + 'px = ' + toMm(rectH) + 'mm');
            push('  PHP height budget used (netAvail portion) : ' + phpH + 'mm');
            push('  Header height : ' + hdrH.toFixed(1) + 'px = ' + toMm(hdrH) + 'mm');
            push('  Table  height : ' + tblH.toFixed(1) + 'px = ' + toMm(tblH) + 'mm');
            push('  Footer height : ' + ftrH.toFixed(1) + 'px = ' + toMm(ftrH) + 'mm');
            push('  Header+Table+Footer : ' + toMm(hdrH + tblH + ftrH) + 'mm');
            push('  Paper usable  : ' + PAPER_MM + 'mm');
            if (overflow > 0.5) {
                push('  ⚠ OVERFLOWS PAPER by ' + toMm(overflow) + 'mm  ← Chrome WILL split this page!');
            } else {
                push('  ✓ Fits within paper (margin: ' + toMm(-overflow) + 'mm below page bottom)');
            }
            push('  Category rows : ' + cats);
            push('');

            // ── Row-by-row breakdown ─────────────────────────────────
            push('  ROW BREAKDOWN:');
            push('  ' + 'No '.padEnd(5) + 'Type'.padEnd(6) + 'H(px)'.padEnd(9) + 'H(mm)'.padEnd(9) +
                 'BrApp'.padEnd(7) + 'PHPApp'.padEnd(8) + 'BrPos'.padEnd(7) + 'PHPPos'.padEnd(8) +
                 'AppLen'.padEnd(8) + 'PosLen'.padEnd(8) + 'Wraps'.padEnd(10) + 'Product Code');
            push('  ' + '─'.repeat(110));

            const rows = pg.querySelectorAll('tr[data-type]');
            let rowNum = 0;
            rows.forEach(function(row) {
                rowNum++;
                const type    = row.dataset.type;
                const rh      = row.getBoundingClientRect().height;
                const appText = row.dataset.app || '';
                const posText = row.dataset.pos || '';
                const code    = row.dataset.code || '';

                // PHP-calculated values from data attributes
                const phpAppLines = parseInt(row.dataset.phpAppLines) || 0;
                const phpPosLines = parseInt(row.dataset.phpPosLines) || 0;
                const phpHRaw     = parseFloat(row.dataset.phpH) || 0;
                const appLen      = parseInt(row.dataset.appLen) || 0;
                const posLen      = parseInt(row.dataset.posLen) || 0;

                // Measure application div and position div heights
                let appLines = 1, posLines = 0, wraps = 'none', mismatch = '';
                if (type !== 'cat') {
                    const appDiv = row.querySelector('[data-role="app-div"]');
                    const posDiv = row.querySelector('[data-role="pos-div"]');
                    const appDivH = appDiv ? appDiv.getBoundingClientRect().height : 0;
                    const posDivH = posDiv ? posDiv.getBoundingClientRect().height : 0;
                    // Single line at 14px × 1.2 = 16.8px
                    const singleAppH = 14 * 1.2;
                    const singlePosH = 11 * 1.2;
                    appLines = appDivH > 0 ? Math.max(1, Math.round(appDivH / singleAppH)) : 1;
                    posLines = posDivH > 0 ? Math.max(0, Math.round(posDivH / singlePosH)) : 0;
                    const appWrap = appLines > 1;
                    const posWrap = posLines > 1;
                    wraps = (appWrap ? 'APP' : '') + (posWrap ? (appWrap ? '+POS' : 'POS') : '') || 'none';

                    // Compare PHP vs browser line counts
                    if (phpAppLines > 0 && appLines !== phpAppLines) mismatch += ' APP_LINE_MISMATCH';
                    if (phpPosLines > 0 && posLines !== phpPosLines) mismatch += ' POS_LINE_MISMATCH';

                    allDataRows.push({
                        page: pi + 1, num: rowNum, type, heightPx: rh,
                        heightMm: parseFloat(toMm(rh)), appLines, posLines, wraps,
                        phpAppLines, phpPosLines, phpHRaw, appLen, posLen,
                        app: appText.substring(0, 45), pos: posText.substring(0, 30), code
                    });
                }

                const flag = rh / mmPx > 15 ? ' ◄◄ TALL' : '';
                push('  ' +
                    String(rowNum).padStart(3).padEnd(5) +
                    type.padEnd(6) +
                    (rh.toFixed(1) + 'px').padEnd(9) +
                    (toMm(rh) + 'mm').padEnd(9) +
                    String(appLines).padEnd(7) +
                    String(phpAppLines).padEnd(8) +
                    String(posLines).padEnd(7) +
                    String(phpPosLines).padEnd(8) +
                    String(appLen).padEnd(8) +
                    String(posLen).padEnd(8) +
                    (wraps + (mismatch ? ' ⚠' + mismatch : '')).padEnd(10) +
                    code + flag);
            });
            push('');
        });

        // ── Global statistics ────────────────────────────────────────
        push('═══════════════════════════════════════════════════════════════════════════');
        push('  GLOBAL ROW STATISTICS (data rows only, excludes category header rows)');
        push('═══════════════════════════════════════════════════════════════════════════');
        push('');

        const posRows  = allDataRows.filter(r => r.type === 'pos');
        const normRows = allDataRows.filter(r => r.type === 'row');
        const allDataH = allDataRows.map(r => r.heightMm);

        function stats(arr, label) {
            if (!arr.length) return;
            const h = arr.map(r => r.heightMm);
            const mn = Math.min(...h).toFixed(2);
            const mx = Math.max(...h).toFixed(2);
            const av = (h.reduce((a,b)=>a+b,0)/h.length).toFixed(2);
            push('  ' + label + ':  count=' + arr.length + '  min=' + mn + 'mm  avg=' + av + 'mm  max=' + mx + 'mm');
        }
        stats(allDataRows, 'ALL data rows');
        stats(normRows,    'Normal rows (type=row)');
        stats(posRows,     'Position rows (type=pos)');

        const wrapApp  = allDataRows.filter(r => r.appLines > 1).length;
        const wrapPos  = allDataRows.filter(r => r.posLines > 1).length;

        // ── PHP vs Browser line-count comparison ──────────────────────
        const appMismatch = allDataRows.filter(r => r.phpAppLines > 0 && r.appLines !== r.phpAppLines);
        const posMismatch = allDataRows.filter(r => r.phpPosLines > 0 && r.posLines !== r.phpPosLines);
        const anyMismatch = [...new Set([...appMismatch, ...posMismatch])];
        push('');
        push('  PHP vs BROWSER LINE-COUNT COMPARISON:');
        push('    Rows where browser appLines ≠ PHP appLines : ' + appMismatch.length + '/' + allDataRows.length);
        if (appMismatch.length > 0) {
            push('    ── App line mismatches ──');
            appMismatch.forEach(function(r) {
                push('      ' + r.code + ': PHP appLines=' + r.phpAppLines + ' Browser=' + r.appLines +
                     ' appLen=' + r.appLen + ' text="' + r.app + '"');
            });
        }
        push('    Rows where browser posLines ≠ PHP posLines : ' + posMismatch.length + '/' + allDataRows.length);
        if (posMismatch.length > 0) {
            push('    ── Pos line mismatches ──');
            posMismatch.forEach(function(r) {
                push('      ' + r.code + ': PHP posLines=' + r.phpPosLines + ' Browser=' + r.posLines +
                     ' posLen=' + r.posLen + ' text="' + r.pos + '"');
            });
        }
        const appOver = allDataRows.filter(r => r.phpAppLines > 0 && r.appLines > r.phpAppLines);
        const posOver = allDataRows.filter(r => r.phpPosLines > 0 && r.posLines > r.phpPosLines);
        push('    Rows where browser appLines > PHP appLines (under-counted) : ' + appOver.length);
        push('    Rows where browser posLines > PHP posLines (under-counted) : ' + posOver.length);
        push('');

        push('  Rows where application wraps (>1 line) : ' + wrapApp);
        push('  Rows where position wraps (>1 line)    : ' + wrapPos);

        // ── Category header statistics ───────────────────────────────
        const catRows = [];
        area.querySelectorAll('tr[data-type="cat"]').forEach(function(row) {
            catRows.push(row.getBoundingClientRect().height);
        });
        if (catRows.length) {
            const mn = Math.min(...catRows).toFixed(2);
            const mx = Math.max(...catRows).toFixed(2);
            const av = (catRows.reduce((a,b)=>a+b,0)/catRows.length).toFixed(2);
            push('  Category header rows:  count=' + catRows.length +
                 '  min=' + toMm(parseFloat(mn)) + 'mm  avg=' + toMm(parseFloat(av)) + 'mm  max=' + toMm(parseFloat(mx)) + 'mm');
        }

        // ── Header / footer ──────────────────────────────────────────
        const hdrAll = Array.from(area.querySelectorAll('[data-role="header"]'))
                            .map(e => e.getBoundingClientRect().height);
        const ftrAll = Array.from(area.querySelectorAll('[data-role="footer"]'))
                            .map(e => e.getBoundingClientRect().height);
        if (hdrAll.length) {
            push('  Page header height:  min=' + toMm(Math.min(...hdrAll)) + 'mm  max=' + toMm(Math.max(...hdrAll)) + 'mm');
            push('  Page footer height:  min=' + toMm(Math.min(...ftrAll)) + 'mm  max=' + toMm(Math.max(...ftrAll)) + 'mm');
        }
        push('');

        // ── Top 25 tallest data rows ─────────────────────────────────
        push('═══════════════════════════════════════════════════════════════════════════');
        push('  TOP 25 TALLEST DATA ROWS');
        push('═══════════════════════════════════════════════════════════════════════════');
        push('');
        push('  ' + '#  '.padEnd(5) + 'Pg'.padEnd(5) + 'Type'.padEnd(6) + 'Height'.padEnd(10) +
             'ALines'.padEnd(8) + 'PLines'.padEnd(8) + 'Wraps'.padEnd(12) + 'PHP_h'.padEnd(10) +
             'Diff'.padEnd(10) + 'Application text');
        push('  ' + '─'.repeat(110));
        const sortedRows = [...allDataRows].sort((a,b) => b.heightMm - a.heightMm);
        sortedRows.slice(0, 25).forEach(function(r, i) {
            const diff = r.phpHRaw > 0 ? (r.heightMm - r.phpHRaw).toFixed(1) : '?';
            push('  ' +
                String(i+1).padEnd(5) +
                ('pg'+r.page).padEnd(5) +
                r.type.padEnd(6) +
                (r.heightMm.toFixed(1)+'mm').padEnd(10) +
                String(r.appLines).padEnd(8) +
                String(r.posLines).padEnd(8) +
                r.wraps.padEnd(12) +
                (r.phpHRaw.toFixed(1)+'mm').padEnd(10) +
                (diff+'mm').padEnd(10) +
                r.app);
        });
        push('');

        // ── Application column width ─────────────────────────────────
        push('═══════════════════════════════════════════════════════════════════════════');
        push('  APPLICATION COLUMN MEASUREMENT');
        push('═══════════════════════════════════════════════════════════════════════════');
        push('');
        const appCells = area.querySelectorAll('tr[data-type] td:nth-child(3)');
        if (appCells.length) {
            const appW = appCells[0].getBoundingClientRect().width;
            const innerW = appW - 12; // minus 6px×2 padding
            push('  Application column width : ' + appW.toFixed(1) + 'px = ' + toMm(appW) + 'mm');
            push('  Inner text width         : ' + innerW.toFixed(1) + 'px (minus 6px×2 padding)');
            push('  At 14px bold Arial, ~7.8px/char → chars/line ≈ ' + Math.floor(innerW / 7.8));
        }
        push('');

        // ── Overflow diagnosis ───────────────────────────────────────
        push('═══════════════════════════════════════════════════════════════════════════');
        push('  OVERFLOW DIAGNOSIS');
        push('═══════════════════════════════════════════════════════════════════════════');
        push('');
        let overflowCount = 0;
        pages.forEach(function(pg, pi) {
            const h = pg.getBoundingClientRect().height;
            const hMm = parseFloat(toMm(h));
            if (hMm > PAPER_MM) {
                overflowCount++;
                push('  ⚠ Page ' + (pi+1) + ': ' + hMm.toFixed(1) + 'mm  OVERFLOWS by ' + (hMm - PAPER_MM).toFixed(1) + 'mm');
                push('    → Chrome splits this into 2 physical sheets');
            } else {
                push('  ✓ Page ' + (pi+1) + ': ' + hMm.toFixed(1) + 'mm  (fits with ' + (PAPER_MM - hMm).toFixed(1) + 'mm to spare)');
            }
        });
        push('');
        push('  PHP pages: ' + <?php echo e($totalPrintPages); ?>);
        push('  Overflow pages: ' + overflowCount);
        push('  Expected Chrome physical pages: ' + (<?php echo e($totalPrintPages); ?> + overflowCount));
        push('  (Each overflow page is split into 2 physical sheets by Chrome)');
        push('');
        push('  PHP paginator constants:');
        push('    $availHeight    = 257');
        push('    $companyHeaderH = 13');
        push('    $footerH        = 6');
        push('    $descH          = 10');
        push('    $netAvail       = 238mm');
        push('    row height      = dynamic per product: 7.62 + (appLines-1)*4.5 + posLines*4.5');
        push('');
        push('═══════════════════════════════════════════════════════════════════════════');
        push('  END OF REPORT');
        push('═══════════════════════════════════════════════════════════════════════════');

        reportText = lines.join('\n');
        document.getElementById('report').textContent = reportText;
        document.getElementById('btn-copy').disabled = false;
        document.getElementById('btn-download').disabled = false;
        document.getElementById('status').textContent =
            'Measurement complete. ' + pages.length + ' pages, ' + allDataRows.length + ' data rows measured.';

        // Hide render area again
        area.style.visibility = 'hidden';
    }));
}

function copyReport() {
    if (!reportText) return;
    navigator.clipboard.writeText(reportText).then(() => {
        document.getElementById('status').textContent = '✓ Report copied to clipboard.';
    }).catch(() => {
        // Fallback
        const ta = document.createElement('textarea');
        ta.value = reportText;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        document.getElementById('status').textContent = '✓ Report copied to clipboard (fallback).';
    });
}

function downloadReport() {
    if (!reportText) return;
    const blob = new Blob([reportText], { type: 'text/plain' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = 'w68-pricelist-dom-measurement-' + new Date().toISOString().slice(0,19).replace(/:/g,'-') + '.txt';
    a.click();
    URL.revokeObjectURL(url);
    document.getElementById('status').textContent = '✓ Report downloaded.';
}
</script>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\hatdog\resources\views\Admin\master_list\Product_PriceList_Debug.blade.php ENDPATH**/ ?>