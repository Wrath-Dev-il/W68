<?php
/**
 * final_sim.php — Three-configuration final simulation.
 * Runs PHP paginator with configs A/B/C, then measures actual
 * rendered height of each generated page using text-derived heights.
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Http\Kernel::class)->handle(Illuminate\Http\Request::capture());

// ── Geometry & font metrics ─────────────────────────────────────────────────
const MM_PER_PX   = 25.4 / 96;
const PX_PER_MM   = 96  / 25.4;
const PAPER_W_PX  = (215.9 - 10 - 10) * (96 / 25.4); // 740.4px
const COL_APP_PX  = PAPER_W_PX * 0.35;                // 259.1px
const COL_APP_IN  = COL_APP_PX - 12;                   // 247.1px (minus 6px×2 pad)
const CHAR_W_14   = 7.8;   // px per char, 14px bold Arial
const CHAR_W_11   = 6.1;   // px per char, 11px bold Arial
const CPL_APP     = 31;    // chars per line in app column  (floor(247.1/7.8))
const CPL_POS     = 40;    // chars per line in pos sub-div (floor(247.1/6.1))
const LH_ROW      = 14 * 1.2;   // 16.8px
const LH_POS_DIV  = 11 * 1.2;   // 13.2px
const PAD_ROW     = 8;    // 4px top + 4px bottom
const BORDER_ROW  = 2;    // 2px (collapsed shared border — conservative)
const LH_CAT      = 18 * 1.2;   // 21.6px
const PAD_CAT     = 12;   // 6px top + 6px bottom
const BORDER_CAT  = 2;
const PAPER_H     = 257.4; // mm: Letter 279.4 - 8 top - 10 bottom - 4 Chromium

// ── Measured header / footer heights ────────────────────────────────────────
// Header: flex(20px+18px stacked) + 3px pad-bottom + 2px border + 4px margin = 47px = 12.44mm
// Footer: 14.4px text + 2px pad-top + 1px border + 4px margin-top = 21.4px = 5.66mm
const HDR_MM = 12.44;
const FTR_MM =  5.66;

// ── Actual row height from product fields ────────────────────────────────────
function realRowMm(\App\Models\Product $p): float {
    $app      = trim($p->application ?: $p->Application ?: '---');
    $pos      = trim($p->position    ?: $p->Position    ?: '');
    $appLines = max(1, (int) ceil(mb_strlen($app) / CPL_APP));
    $appHpx   = $appLines * LH_ROW;
    $posPx    = 0;
    if ($pos !== '') {
        $posLines = max(1, (int) ceil((mb_strlen($pos) + 5) / CPL_POS)); // +5 for "POS: "
        $posPx    = 2 + 1 + 1 + $posLines * LH_POS_DIV; // margin+border+padding+text
    }
    return ($appHpx + $posPx + PAD_ROW + BORDER_ROW) * MM_PER_PX;
}

function realCatMm(string $text): float {
    $chars = mb_strlen($text);
    $lines = max(1, (int) ceil($chars / 75)); // cat header ~75 chars/line (wide col)
    return ($lines * LH_CAT + PAD_CAT + BORDER_CAT) * MM_PER_PX;
}

// ── Fetch + sort ─────────────────────────────────────────────────────────────
$products = \App\Models\Product::where('is_selected_for_report', true)
    ->orderBy('description','asc')->orderBy('category','asc')->orderBy('application','asc')
    ->get()->sort(function($a,$b){
        $d = strcmp(trim(strtoupper($a->description??'')), trim(strtoupper($b->description??'')));
        if ($d) return $d;
        $c = strcmp(trim(strtoupper($a->category??'')),    trim(strtoupper($b->category??'')));
        if ($c) return $c;
        return strcmp(trim(strtoupper($a->application??'')), trim(strtoupper($b->application??'')));
    })->values();

$N = $products->count();

// ── Paginator (mirrors Blade exactly) ────────────────────────────────────────
function paginate($products, int $rowH, int $posRowH,
                  int $availH=257, int $hdrH=13, int $ftrH=6, int $descH=10): array {
    $netAvail = $availH - $hdrH - $ftrH;
    $pages = []; $cur = []; $h = 0; $last = null;
    foreach ($products as $p) {
        $desc  = trim(strtoupper($p->description ?: 'NO DESCRIPTION'));
        $isPos = !empty($p->position) || !empty($p->Position);
        $rh    = $isPos ? $posRowH : $rowH;
        $isNew = ($desc !== $last);
        $need  = $rh + ($isNew ? $descH : 0);
        if ($h + $need > $netAvail && !empty($cur)) {
            $pages[] = $cur; $cur = []; $h = 0; $isNew = true; $need = $rh + $descH;
        }
        if ($isNew) { $h += $descH; $last = $desc; }
        $cur[] = ['p' => $p, 'isNew' => $isNew, 'isPos' => $isPos, 'rh' => $rh];
        $h += $rh;
    }
    if (!empty($cur)) $pages[] = $cur;
    return $pages;
}

// ── Measure actual page height from real text ────────────────────────────────
function measurePage(array $page): array {
    $cats = 0; $rows = 0; $catH = 0.0; $rowH = 0.0;
    $catNames = []; $rowDetails = [];
    foreach ($page as $item) {
        $rows++;
        $rMm = realRowMm($item['p']);
        $rowH += $rMm;
        if ($item['isNew']) {
            $cats++;
            $cMm = realCatMm(trim(strtoupper($item['p']->description ?? '')));
            $catH += $cMm;
            $catNames[] = trim(strtoupper($item['p']->description ?? ''));
        }
        $rowDetails[] = ['mm' => $rMm, 'isPos' => $item['isPos'],
                         'app' => substr($item['p']->application ?? '', 0, 30),
                         'pos' => substr($item['p']->position    ?? '', 0, 20)];
    }
    $total = HDR_MM + $catH + $rowH + FTR_MM;
    return [
        'rows'     => $rows,
        'cats'     => $cats,
        'catH'     => $catH,
        'rowH'     => $rowH,
        'total'    => $total,
        'blank'    => max(0, PAPER_H - $total),
        'overflow' => max(0, $total - PAPER_H),
        'catNames' => $catNames,
        'rowDetails' => $rowDetails,
    ];
}

// ── Run all configs ──────────────────────────────────────────────────────────
$configs = [
    'A' => ['rowH' => 8, 'posRowH' => 16],
    'B' => ['rowH' => 8, 'posRowH' => 18],
    'C' => ['rowH' => 8, 'posRowH' => 20],
];

header('Content-Type: text/plain; charset=utf-8');

echo "╔══════════════════════════════════════════════════════════════════════════╗\n";
echo "║         FINAL PAGINATION SIMULATION — THREE CONFIGURATIONS              ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════╝\n\n";
printf("Dataset: %d products  |  Paper: %.1fmm  |  Fixed: availH=257 hdrH=13 ftrH=6 descH=10\n\n", $N, PAPER_H);

$results = [];
foreach ($configs as $label => $cfg) {
    $pages    = paginate($products, $cfg['rowH'], $cfg['posRowH']);
    $measured = array_map('measurePage', $pages);

    $totals   = array_column($measured, 'total');
    $blanks   = array_column($measured, 'blank');
    $oflows   = array_column($measured, 'overflow');
    $rowCounts= array_column($measured, 'rows');

    $results[$label] = compact('pages','measured','cfg','totals','blanks','oflows','rowCounts');

    $netAvail = 257 - 13 - 6;
    echo "══════════════════════════════════════════════════════════════════════════\n";
    printf("CONFIG %s  ▸  \$rowH=%d  \$posRowH=%d  (\$netAvail=%dmm)\n",
        $label, $cfg['rowH'], $cfg['posRowH'], $netAvail);
    echo "══════════════════════════════════════════════════════════════════════════\n\n";

    printf("  Total pages generated : %d\n", count($pages));
    printf("  Rows per page         : min=%d  max=%d  avg=%.1f\n",
        min($rowCounts), max($rowCounts), array_sum($rowCounts)/count($rowCounts));
    printf("  Max actual page height: %.2fmm  (paper=%.1fmm)\n", max($totals), PAPER_H);
    printf("  Max overflow          : %.2fmm\n", max($oflows));
    printf("  Max blank at bottom   : %.2fmm\n", max($blanks));
    printf("  Pages overflowing     : %d\n", count(array_filter($oflows, fn($o)=>$o>0)));
    printf("  Pages with >20mm blank: %d\n", count(array_filter($blanks, fn($b)=>$b>20)));
    echo "\n";

    printf("  %-5s %-6s %-5s %-13s %-13s %-13s  %s\n",
        'Page','Rows','Cats','ActualH','Blank','Status','Categories (first 3)');
    printf("  %s\n", str_repeat('─', 95));
    foreach ($measured as $pi => $pg) {
        $status = $pg['overflow'] > 0
            ? '◄◄ OVERFLOW +'.round($pg['overflow'],1).'mm'
            : ($pg['blank'] > 30 ? '◄ large blank' : ($pg['blank'] > 15 ? '◄ blank' : '✓ good'));
        $cats3 = implode(', ', array_map(fn($s)=>substr($s,0,15), array_slice($pg['catNames'],0,3)));
        if (count($pg['catNames'])>3) $cats3.='...';
        printf("  %-5d %-6d %-5d %-13s %-13s %-13s  %s\n",
            $pi+1, $pg['rows'], $pg['cats'],
            round($pg['total'],1).'mm',
            round($pg['blank'],1).'mm',
            $status, $cats3);
    }
    echo "\n";
}

// ── Side-by-side comparison ──────────────────────────────────────────────────
echo "══════════════════════════════════════════════════════════════════════════\n";
echo "SIDE-BY-SIDE COMPARISON\n";
echo "══════════════════════════════════════════════════════════════════════════\n\n";
printf("  %-35s  %10s  %10s  %10s\n", 'Metric', 'Config A', 'Config B', 'Config C');
printf("  %-35s  %10s  %10s  %10s\n", '', '(posRowH=16)', '(posRowH=18)', '(posRowH=20)');
printf("  %s\n", str_repeat('─', 70));

$r = $results;
$metrics = [
    ['Total pages',            fn($l)=>count($r[$l]['pages'])],
    ['Max actual height (mm)', fn($l)=>round(max($r[$l]['totals']),2)],
    ['Max overflow (mm)',      fn($l)=>round(max($r[$l]['oflows']),2)],
    ['Max blank (mm)',         fn($l)=>round(max($r[$l]['blanks']),2)],
    ['Avg blank (mm)',         fn($l)=>round(array_sum($r[$l]['blanks'])/count($r[$l]['blanks']),2)],
    ['Pages overflowing',      fn($l)=>count(array_filter($r[$l]['oflows'],fn($o)=>$o>0))],
    ['Pages >20mm blank',      fn($l)=>count(array_filter($r[$l]['blanks'],fn($b)=>$b>20))],
    ['Min rows per page',      fn($l)=>min($r[$l]['rowCounts'])],
    ['Max rows per page',      fn($l)=>max($r[$l]['rowCounts'])],
    ['Avg rows per page',      fn($l)=>round(array_sum($r[$l]['rowCounts'])/count($r[$l]['rowCounts']),1)],
    ['Chrome will split?',     fn($l)=>max($r[$l]['oflows'])>0?'YES – splits':'NO – safe'],
];

foreach ($metrics as [$name, $fn]) {
    printf("  %-35s  %10s  %10s  %10s\n", $name, $fn('A'), $fn('B'), $fn('C'));
}

// ── Tallest rows per config — what makes them tall ───────────────────────────
echo "\n══ TALLEST PAGE PER CONFIG — ROW BREAKDOWN ═══════════════════════════════\n\n";
foreach ($results as $label => $res) {
    $maxIdx  = array_search(max($res['totals']), $res['totals']);
    $pg      = $res['measured'][$maxIdx];
    printf("Config %s — Tallest page is page %d (%.1fmm, %s):\n",
        $label, $maxIdx+1, $pg['total'],
        $pg['overflow']>0 ? 'OVERFLOWS by '.round($pg['overflow'],1).'mm' : 'fits');
    // Show top 5 tallest rows on that page
    $rows = $pg['rowDetails'];
    usort($rows, fn($a,$b)=>$b['mm']<=>$a['mm']);
    foreach (array_slice($rows,0,5) as $i=>$row) {
        printf("  Row %-2d: %.2fmm  %s  app='%s'  pos='%s'\n",
            $i+1, $row['mm'], $row['isPos']?'[+pos]':'[norm]',
            $row['app'], $row['pos']);
    }
    echo "\n";
}

// ── Recommendation ────────────────────────────────────────────────────────────
echo "══ RECOMMENDATION ════════════════════════════════════════════════════════\n\n";

// Find first config with zero overflow
$rec = null;
foreach ($results as $label => $res) {
    if (max($res['oflows']) == 0 && $rec === null) {
        $rec = $label;
    }
}

if ($rec) {
    $cfg = $results[$rec]['cfg'];
    printf("  ✓ Recommended: Config %s  (\$rowH=%d  \$posRowH=%d)\n\n", $rec, $cfg['rowH'], $cfg['posRowH']);
    printf("  This is the SMALLEST posRowH value that produces:\n");
    printf("  - Zero overflow on every page\n");
    printf("  - No Chrome page splitting\n");
    printf("  - Minimum total page count\n\n");
    printf("  Changes required (lines 364-370 of Product_PriceList.blade.php):\n");
    printf("    \$availHeight    = 257;  // unchanged\n");
    printf("    \$companyHeaderH = 13;   // unchanged\n");
    printf("    \$footerH        = 6;    // unchanged\n");
    printf("    \$descH          = 10;   // unchanged\n");
    printf("    \$rowH           = %d;    // was 7\n", $cfg['rowH']);
    printf("    \$posRowH        = %d;   // was 12\n", $cfg['posRowH']);
    printf("    \$netAvail = 257 - 13 - 6; // = 238mm  (unchanged)\n\n");
    echo "  Preserved without change:\n";
    echo "    ✓ W68 AUTO PARTS watermark (body background-image)\n";
    echo "    ✓ W68 AUTO PARTS — PRICE LIST PAGE X OF Y footer\n";
    echo "    ✓ All HTML, CSS, branding, design\n";
    echo "    ✓ Pagination algorithm logic\n";
} else {
    echo "  ✗ None of the tested configs guarantee zero overflow.\n";
    echo "    Recommend Config C (posRowH=20) as the safest option and re-verify.\n";
}
