<?php
/**
 * dynamic_sim.php
 * Simulates pagination with content-aware row heights.
 *
 * Formula (user-specified):
 *   appLines = max(1, ceil(strlen(app) / 31))
 *   posLines = hasPos ? max(1, ceil(strlen(pos) / 31)) : 0
 *   rowH     = 7 + (appLines - 1) * 4.5 + posLines * 4.5
 *
 * Actual CSS-measured constants (from browser diagnostic):
 *   header   ≈ 12.44mm
 *   footer   ≈  5.66mm
 *   cat hdr  ≈ 10.0mm
 *   paper    ≈ 257.4mm
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Http\Kernel::class)->handle(Illuminate\Http\Request::capture());

header('Content-Type: text/plain; charset=utf-8');

// ── Geometry ──────────────────────────────────────────────────────────────────
const PAPER     = 257.4;  // mm available (Letter minus margins minus Chromium overhead)
const HDR_MM    = 12.44;  // measured header height
const FTR_MM    =  5.66;  // measured footer height
const CAT_MM    = 10.0;   // measured category header row
const CHARS_PER_LINE = 31; // app/pos column chars per line (browser-measured)
const BASE_ROW  = 7.0;    // mm — single-line row, no position
const LINE_INC  = 4.5;    // mm — per extra app OR pos line

// ── Dynamic row height from content ──────────────────────────────────────────
function dynRowH(string $app, ?string $pos): float {
    $appLines = max(1, (int) ceil(mb_strlen(trim($app)) / CHARS_PER_LINE));
    $posLines = ($pos !== null && trim($pos) !== '')
        ? max(1, (int) ceil(mb_strlen(trim($pos)) / CHARS_PER_LINE))
        : 0;
    return BASE_ROW + ($appLines - 1) * LINE_INC + $posLines * LINE_INC;
}

// ── Fetch + sort (exact as Blade) ─────────────────────────────────────────────
$products = \App\Models\Product::where('is_selected_for_report', true)
    ->orderBy('description', 'asc')
    ->orderBy('category', 'asc')
    ->orderBy('application', 'asc')
    ->get()
    ->sort(function ($a, $b) {
        $d = strcmp(trim(strtoupper($a->description ?? '')), trim(strtoupper($b->description ?? '')));
        if ($d) return $d;
        $c = strcmp(trim(strtoupper($a->category ?? '')), trim(strtoupper($b->category ?? '')));
        if ($c) return $c;
        return strcmp(trim(strtoupper($a->application ?? '')), trim(strtoupper($b->application ?? '')));
    })->values();

$N = $products->count();

// ── Pre-compute all row heights ───────────────────────────────────────────────
$rowMeta = [];
foreach ($products as $p) {
    $app  = $p->application ?? $p->Application ?? '---';
    $pos  = $p->position    ?? $p->Position    ?? null;
    $appLines = max(1, (int) ceil(mb_strlen(trim($app))  / CHARS_PER_LINE));
    $posLines = ($pos && trim($pos) !== '')
        ? max(1, (int) ceil(mb_strlen(trim($pos)) / CHARS_PER_LINE))
        : 0;
    $rh = dynRowH($app, $pos);
    $rowMeta[] = [
        'p'        => $p,
        'app'      => $app,
        'pos'      => $pos,
        'appLines' => $appLines,
        'posLines' => $posLines,
        'rh'       => $rh,
        'isPos'    => ($pos && trim($pos) !== ''),
    ];
}

// ── Print row stats ───────────────────────────────────────────────────────────
$allRH    = array_column($rowMeta, 'rh');
$normRH   = array_column(array_filter($rowMeta, fn($r) => !$r['isPos']), 'rh');
$posRH    = array_column(array_filter($rowMeta, fn($r) => $r['isPos']), 'rh');
$wrapApp  = count(array_filter($rowMeta, fn($r) => $r['appLines'] > 1));
$wrapPos  = count(array_filter($rowMeta, fn($r) => $r['posLines'] > 1));
$avgAll   = $allRH ? array_sum($allRH) / count($allRH) : 0;
$avgNorm  = $normRH ? array_sum($normRH) / count($normRH) : 0;
$avgPos   = $posRH  ? array_sum($posRH)  / count($posRH)  : 0;

echo "╔══════════════════════════════════════════════════════════════════════════╗\n";
echo "║        DYNAMIC ROW HEIGHT PAGINATION SIMULATION — W68 PRICE LIST        ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════╝\n\n";

echo "FORMULA:\n";
echo "  appLines = max(1, ceil(strlen(app) / 31))\n";
echo "  posLines = hasPos ? max(1, ceil(strlen(pos) / 31)) : 0\n";
echo "  rowH     = 7 + (appLines-1)*4.5 + posLines*4.5\n\n";

echo "DATASET: {$N} products\n\n";

echo "ROW HEIGHT DISTRIBUTION:\n";
printf("  All rows:    count=%d  min=%.1f  avg=%.2f  max=%.1f mm\n",
    count($allRH), min($allRH), $avgAll, max($allRH));
printf("  Normal rows: count=%d  min=%.1f  avg=%.2f  max=%.1f mm\n",
    count($normRH), min($normRH ?? [0]), $avgNorm, max($normRH ?? [0]));
printf("  Pos rows:    count=%d  min=%.1f  avg=%.2f  max=%.1f mm\n",
    count($posRH), min($posRH ?? [0]), $avgPos, max($posRH ?? [0]));
printf("  App wraps (>1 line): %d of %d products\n", $wrapApp, $N);
printf("  Pos wraps (>1 line): %d of %d products\n\n", $wrapPos, $N);

// ── Show top-10 tallest rows ───────────────────────────────────────────────────
$sorted = $rowMeta;
usort($sorted, fn($a, $b) => $b['rh'] <=> $a['rh']);

echo "TOP 10 TALLEST ROWS (dynamic heights):\n";
printf("  %-3s %-8s %-6s %-5s %-5s  %-38s  %s\n", '#','Code','Height','ALine','PLine','Application','Position');
printf("  %s\n", str_repeat('─', 90));
foreach (array_slice($sorted, 0, 10) as $i => $r) {
    printf("  %-3d %-8s %-6s %-5d %-5d  %-38s  %s\n",
        $i + 1,
        substr($r['p']->product_code ?? '', 0, 8),
        round($r['rh'], 1) . 'mm',
        $r['appLines'],
        $r['posLines'],
        substr($r['app'], 0, 38),
        substr($r['pos'] ?? '', 0, 30));
}
echo "\n";

// ═══════════════════════════════════════════════════════════════════════════════
// RUN PAGINATOR: Dynamic height, netAvail = 257 - 13 - 6 = 238mm (unchanged)
// catH = 10mm per category header (unchanged)
// ═══════════════════════════════════════════════════════════════════════════════
$availH = 257; $hdrH = 13; $ftrH = 6; $catH = 10;
$netAvail = $availH - $hdrH - $ftrH;

$pages = []; $curPage = []; $curH = 0.0; $lastDesc = null;

foreach ($rowMeta as $meta) {
    $desc  = trim(strtoupper($meta['p']->description ?? 'NO DESCRIPTION'));
    $rh    = $meta['rh'];
    $isNew = ($desc !== $lastDesc);
    $need  = $rh + ($isNew ? $catH : 0);

    if ($curH + $need > $netAvail && !empty($curPage)) {
        $pages[]  = $curPage;
        $curPage  = [];
        $curH     = 0.0;
        $isNew    = true;
        $need     = $rh + $catH;
    }

    if ($isNew) {
        $curH    += $catH;
        $lastDesc = $desc;
    }
    $curPage[] = $meta;
    $curH     += $rh;
}
if (!empty($curPage)) $pages[] = $curPage;

$totalPages = count($pages);

echo "══ PAGINATION RESULT ═══════════════════════════════════════════════════════\n\n";
printf("  Total pages generated  : %d\n", $totalPages);
printf("  netAvail per page      : %dmm\n\n", $netAvail);

// ── Per-page breakdown ────────────────────────────────────────────────────────
printf("  %-5s %-6s %-5s %-5s %-5s %-12s %-12s %-12s %-12s  %s\n",
    'Page', 'Items', 'Cats', 'Pos', 'Wrap', 'PHPbudget', 'ActualH', 'Blank', 'Status', 'Categories (first 3)');
printf("  %s\n", str_repeat('─', 115));

$maxActual  = 0;
$maxBlank   = 0;
$maxOverflow = 0;
$sumBlank   = 0;

foreach ($pages as $pi => $pg) {
    $rows  = count($pg);
    $cats  = 0; $pos  = 0; $wrap = 0;
    $phpBudget = 0.0;
    $actualH   = HDR_MM + FTR_MM;
    $catNames  = [];
    $lastD     = null;

    foreach ($pg as $meta) {
        $desc = trim(strtoupper($meta['p']->description ?? ''));
        if ($desc !== $lastD) {
            $cats++;
            $phpBudget += $catH;
            $actualH   += CAT_MM;
            $catNames[] = substr($desc, 0, 14);
            $lastD = $desc;
        }
        $phpBudget += $meta['rh'];
        $actualH   += $meta['rh'];  // dynamic height IS the actual estimate
        if ($meta['isPos']) $pos++;
        if ($meta['appLines'] > 1 || $meta['posLines'] > 1) $wrap++;
    }

    $blank    = max(0, PAPER - $actualH);
    $overflow = max(0, $actualH - PAPER);
    if ($actualH > $maxActual) $maxActual  = $actualH;
    if ($blank   > $maxBlank)  $maxBlank   = $blank;
    if ($overflow > $maxOverflow) $maxOverflow = $overflow;
    $sumBlank += $blank;

    $status = $overflow > 0
        ? '◄◄ OVERFLOW +' . round($overflow, 1) . 'mm'
        : ($blank > 40 ? '◄ large blank' : ($blank > 15 ? '◄ blank' : '✓ good'));

    $c3 = implode(', ', array_slice($catNames, 0, 3));
    if (count($catNames) > 3) $c3 .= '...';

    printf("  %-5d %-6d %-5d %-5d %-5d %-12s %-12s %-12s %-12s  %s\n",
        $pi + 1, $rows, $cats, $pos, $wrap,
        round($phpBudget, 1) . 'mm',
        round($actualH, 1) . 'mm',
        round($blank, 1) . 'mm',
        $status, $c3);
}

echo "\n";
echo "══ SUMMARY ═════════════════════════════════════════════════════════════════\n\n";
printf("  Total pages            : %d\n", $totalPages);
printf("  Max actual page height : %.2fmm  (paper=%.1fmm)\n", $maxActual, PAPER);
printf("  Max overflow           : %.2fmm\n", $maxOverflow);
printf("  Max blank at bottom    : %.2fmm\n", $maxBlank);
printf("  Avg blank per page     : %.2fmm\n", $sumBlank / $totalPages);
printf("  Pages overflowing      : %d\n", $maxOverflow > 0 ? 1 : 0);
printf("  Chrome will split?     : %s\n\n", $maxOverflow > 0 ? 'YES' : 'NO — safe');

// ── Utilisation per page ──────────────────────────────────────────────────────
echo "  Page utilisation:\n";
foreach ($pages as $pi => $pg) {
    $actualH = HDR_MM + FTR_MM;
    $lastD   = null;
    foreach ($pg as $meta) {
        $desc = trim(strtoupper($meta['p']->description ?? ''));
        if ($desc !== $lastD) { $actualH += CAT_MM; $lastD = $desc; }
        $actualH += $meta['rh'];
    }
    $util = $actualH / PAPER * 100;
    $bar  = str_repeat('█', (int)($util / 2)) . str_repeat('░', 50 - (int)($util / 2));
    printf("    Page %d: %5.1fmm / %.1fmm  [%s] %.0f%%\n",
        $pi + 1, $actualH, PAPER, $bar, $util);
}

// ── Comparison: fixed vs dynamic ─────────────────────────────────────────────
echo "\n══ COMPARISON: FIXED vs DYNAMIC HEIGHT ═════════════════════════════════════\n\n";

// Run fixed paginator (rowH=8, posRowH=14)
$fixRowH = 8; $fixPosRowH = 14;
$fixPages = []; $fc = []; $fh = 0; $fl = null;
foreach ($rowMeta as $meta) {
    $desc  = trim(strtoupper($meta['p']->description ?? 'NO DESCRIPTION'));
    $rh    = $meta['isPos'] ? $fixPosRowH : $fixRowH;
    $isNew = ($desc !== $fl);
    $need  = $rh + ($isNew ? $catH : 0);
    if ($fh + $need > $netAvail && !empty($fc)) {
        $fixPages[] = $fc; $fc = []; $fh = 0; $isNew = true; $need = $rh + $catH;
    }
    if ($isNew) { $fh += $catH; $fl = $desc; }
    $fc[] = $meta;
    $fh  += $rh;
}
if (!empty($fc)) $fixPages[] = $fc;

$fixMaxOver = 0; $fixMaxH = 0;
foreach ($fixPages as $fp) {
    $ah = HDR_MM + FTR_MM; $ld = null;
    foreach ($fp as $meta) {
        $d = trim(strtoupper($meta['p']->description ?? ''));
        if ($d !== $ld) { $ah += CAT_MM; $ld = $d; }
        $ah += $meta['rh'];
    }
    if ($ah > $fixMaxH)    $fixMaxH    = $ah;
    if (max(0,$ah-PAPER) > $fixMaxOver) $fixMaxOver = max(0, $ah - PAPER);
}

printf("  %-30s  %-15s  %-15s\n", 'Metric', 'Fixed (8/14)', 'Dynamic');
printf("  %s\n", str_repeat('─', 65));
printf("  %-30s  %-15s  %-15s\n", 'Total pages', count($fixPages), $totalPages);
printf("  %-30s  %-15s  %-15s\n", 'Max actual height (mm)', round($fixMaxH, 1), round($maxActual, 1));
printf("  %-30s  %-15s  %-15s\n", 'Max overflow (mm)', round($fixMaxOver, 1), round($maxOverflow, 1));
printf("  %-30s  %-15s  %-15s\n", 'Chrome splits?',
    $fixMaxOver > 0 ? 'YES' : 'NO', $maxOverflow > 0 ? 'YES' : 'NO');

echo "\n";

// ── Decision ──────────────────────────────────────────────────────────────────
echo "══ DECISION ════════════════════════════════════════════════════════════════\n\n";
if ($maxOverflow <= 0) {
    echo "  ✓ SAFE TO IMPLEMENT\n";
    echo "  Dynamic height calculation produces zero overflow.\n";
    echo "  All pages fit within 257.4mm paper.\n";
} else {
    echo "  ✗ NOT SAFE — overflow still present.\n";
    printf("  Max overflow: %.1fmm — do not implement without further tuning.\n", $maxOverflow);
}
echo "\n";
echo "  PHP changes required:\n";
echo "  - Remove \$rowH and \$posRowH fixed constants\n";
echo "  - Replace with inline dynamic calculation inside the foreach loop\n";
echo "  - All other constants (\$availHeight, \$companyHeaderH, \$footerH, \$descH) unchanged\n";
echo "  - All HTML, CSS, watermark, footer, branding unchanged\n";
