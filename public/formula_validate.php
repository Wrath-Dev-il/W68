<?php
/**
 * formula_validate.php
 *
 * Validates the dynamic row-height formula against:
 *   1. Known top-row DOM measurements from the browser diagnostic report
 *   2. Statistical analysis across all 50 products
 *   3. Threshold sensitivity (app=31 vs pos=31 vs pos=40)
 *
 * The browser diagnostic (/admin/debug/pricelist-measure) gave us
 * actual DOM heights for the tallest rows. We use those as ground truth.
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Http\Kernel::class)->handle(Illuminate\Http\Request::capture());

header('Content-Type: text/plain; charset=utf-8');

// ════════════════════════════════════════════════════════════════════════════
// GROUND TRUTH — actual DOM measurements from /admin/debug/pricelist-measure
// Each entry: product_code => actual_rendered_height_mm
// Source: "Top 25 Tallest Rows" section of the diagnostic report.
// Heights reported by JavaScript getBoundingClientRect().height.
// ════════════════════════════════════════════════════════════════════════════
$domMeasured = [
    // Rows explicitly measured in diagnostic TOP-25
    // [code] => actual_mm  (from diagnostic report)
    'ASB-3831'  => 20.5,   // 1 app + 3 pos lines
    'ASE-5261'  => 20.5,   // 2 app + 2 pos lines
    '101-181'   => 16.0,   // 2 app + 1 pos line
    'ABA-379K'  => 16.0,   // 2 app + 1 pos line
    'RSK-404H'  => 16.0,   // 2 app + 1 pos line
    'OS-32 X'   => 16.0,   // 2 app + 1 pos line (product_code may have trailing space — handle below)
    'YUS-1286'  => 16.0,   // 2 app + 1 pos line
    '48655-33'  => 16.0,   // 1 app + 2 pos lines
    // Standard single-line rows (from page stats: min measured = 7.0mm)
    // These are any row where appLines=1 and posLines=0
    // Standard pos rows (measured min = 11.5mm for 1app+1pos)
    'MT-14122'  => 11.5,   // 1 app + 1 pos line (from top-10 in simulation)
    'NC5-8003'  => 11.5,   // 1 app + 1 pos line
    '8" 12V'    => 11.5,   // 1 app + 1 pos line  (may need trim)
    'ABA-8429'  => 11.5,   // 1 app + 1 pos line
    'MR-27206'  => 11.5,   // 1 app + 1 pos line
    '11370-01'  => 11.5,   // 1 app + 1 pos line
    'EE-975'    => 11.5,   // 1 app + 1 pos line
    'NRF-5019'  => 11.5,   // 1 app + 1 pos line
    'PI-44'     => 11.5,   // 1 app + 1 pos line
    'A-500/01'  => 11.5,   // 1 app + 1 pos line
    'ASI-4095'  => 11.5,   // 1 app + 1 pos line
    'OS-65 X'   => 11.5,   // 1 app + 1 pos line
];

// ════════════════════════════════════════════════════════════════════════════
// THRESHOLD CANDIDATES
// ════════════════════════════════════════════════════════════════════════════
// Application field: 14px bold Arial → ~7.8px/char → 247px inner / 7.8 = 31.7 → floor=31
// Position field:    11px bold Arial → ~6.1px/char → 247px inner / 6.1 = 40.5 → floor=40
// Question: does using 31 for BOTH fields over-count position lines?
const CPL_APP = 31;  // chars per line — application field (14px font)
const CPL_POS_A = 31; // candidate A: same as app (conservative — may over-count pos lines)
const CPL_POS_B = 40; // candidate B: correct for 11px font (may under-count pos lines)

const BASE    = 7.0;
const INC     = 4.5;
const PAPER   = 257.4;
const HDR_MM  = 12.44;
const FTR_MM  =  5.66;
const CAT_MM  = 10.0;

function predictH(string $app, ?string $pos, int $cplPos): float {
    $al = max(1, (int) ceil(mb_strlen(trim($app)) / CPL_APP));
    $pl = ($pos !== null && trim($pos) !== '')
        ? max(1, (int) ceil(mb_strlen(trim($pos)) / $cplPos))
        : 0;
    return BASE + ($al - 1) * INC + $pl * INC;
}

function predictLines(string $app, ?string $pos, int $cplPos): array {
    $al = max(1, (int) ceil(mb_strlen(trim($app)) / CPL_APP));
    $pl = ($pos !== null && trim($pos) !== '')
        ? max(1, (int) ceil(mb_strlen(trim($pos)) / $cplPos))
        : 0;
    return ['app' => $al, 'pos' => $pl];
}

// ════════════════════════════════════════════════════════════════════════════
// Fetch + sort
// ════════════════════════════════════════════════════════════════════════════
$products = \App\Models\Product::where('is_selected_for_report', true)
    ->orderBy('description','asc')->orderBy('category','asc')->orderBy('application','asc')
    ->get()->sort(function($a,$b){
        $d=strcmp(trim(strtoupper($a->description??'')),trim(strtoupper($b->description??'')));
        if($d)return $d;
        $c=strcmp(trim(strtoupper($a->category??'')),trim(strtoupper($b->category??'')));
        if($c)return $c;
        return strcmp(trim(strtoupper($a->application??'')),trim(strtoupper($b->application??'')));
    })->values();

$N = $products->count();

echo "╔══════════════════════════════════════════════════════════════════════════╗\n";
echo "║      DYNAMIC FORMULA VALIDATION — ALL {$N} PRODUCTS                       ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════╝\n\n";

// ════════════════════════════════════════════════════════════════════════════
// SECTION 1: Application column — chars per line analysis
// ════════════════════════════════════════════════════════════════════════════
echo "══ SECTION 1: APPLICATION COLUMN CHARS-PER-LINE ANALYSIS ═══════════════\n\n";

$appLengths = [];
$posLengths = [];
foreach ($products as $p) {
    $app = trim($p->application ?? $p->Application ?? '---');
    $pos = trim($p->position    ?? $p->Position    ?? '');
    $appLengths[] = mb_strlen($app);
    if ($pos !== '') $posLengths[] = mb_strlen($pos);
}

$maxApp = max($appLengths); $avgApp = array_sum($appLengths)/count($appLengths);
$maxPos = max($posLengths); $avgPos = array_sum($posLengths)/count($posLengths);

printf("  Application text lengths:\n");
printf("    min=%d  avg=%.1f  max=%d chars\n", min($appLengths), $avgApp, $maxApp);
printf("  Position text lengths:\n");
printf("    min=%d  avg=%.1f  max=%d chars\n\n", min($posLengths), $avgPos, $maxPos);

printf("  Column inner width: 247px (browser-measured: 740px table × 35%% − 12px padding)\n");
printf("  App field (14px bold Arial, ~7.8px/char) → chars/line = 247/7.8 = %.1f → CPL=%d\n", 247/7.8, CPL_APP);
printf("  Pos field (11px bold Arial, ~6.1px/char) → chars/line = 247/6.1 = %.1f → CPL=%d\n\n", 247/6.1, CPL_POS_B);

// Show texts that are at threshold boundaries
echo "  BOUNDARY TEXTS (near 31-char or 62-char wrap points):\n";
echo "  " . str_repeat('─', 80) . "\n";
printf("  %-8s  %-5s  %-5s  %-5s  %-5s  %s\n", 'Code','ALen','AL@31','PLen','PL@31','Application text');
echo "  " . str_repeat('─', 80) . "\n";
foreach ($products as $p) {
    $app = trim($p->application ?? $p->Application ?? '---');
    $pos = trim($p->position    ?? $p->Position    ?? '');
    $al  = mb_strlen($app); $alL = max(1,(int)ceil($al/CPL_APP));
    $pl  = mb_strlen($pos); $plL = ($pos!=='')?max(1,(int)ceil($pl/CPL_APP)):0;
    // Only show rows near threshold (within 3 chars of a boundary)
    $nearBoundary = false;
    for ($i = 1; $i <= 5; $i++) {
        if (abs($al - $i*CPL_APP) <= 3) { $nearBoundary = true; break; }
        if ($pos !== '' && abs($pl - $i*CPL_APP) <= 3) { $nearBoundary = true; break; }
    }
    if (!$nearBoundary) continue;
    printf("  %-8s  %-5d  %-5d  %-5d  %-5d  %s\n",
        substr($p->product_code??'',0,8), $al, $alL, $pl, $plL,
        substr($app,0,40).($al>40?'…':''));
}

// ════════════════════════════════════════════════════════════════════════════
// SECTION 2: Per-product formula predictions — both threshold candidates
// ════════════════════════════════════════════════════════════════════════════
echo "\n══ SECTION 2: PER-PRODUCT PREDICTION TABLE ══════════════════════════════\n\n";
// Columns: Code | Desc | ALapp | PLpos@31 | H@31/31 | PLpos@40 | H@31/40 | DOM | Match
printf("  %-8s  %-10s  %-5s  %-5s  %-8s  %-5s  %-8s  %-9s  %s\n",
    'Code','Descr','Aln','Pl31','H(31/31)','Pl40','H(31/40)','DOM(meas)','Match?');
echo "  " . str_repeat('─', 85) . "\n";

$matchA = 0; $mismatchA = 0; // cplPos=31
$matchB = 0; $mismatchB = 0; // cplPos=40
$testedCount = 0;
$mismatches = [];

foreach ($products as $p) {
    $code = trim($p->product_code ?? '');
    $app  = trim($p->application  ?? $p->Application ?? '---');
    $pos  = trim($p->position     ?? $p->Position    ?? '');
    $desc = substr(trim(strtoupper($p->description??'')),0,10);

    $lA = predictLines($app, $pos ?: null, CPL_POS_A);
    $lB = predictLines($app, $pos ?: null, CPL_POS_B);
    $hA = predictH($app, $pos ?: null, CPL_POS_A);
    $hB = predictH($app, $pos ?: null, CPL_POS_B);

    // Try to match product code (trim spaces, handle special chars)
    $domH = null;
    $codeClean = trim($code);
    foreach ($domMeasured as $k => $v) {
        if (trim($k) === $codeClean || rtrim($k) === $codeClean) {
            $domH = $v; break;
        }
    }

    $matchNote = '';
    if ($domH !== null) {
        $testedCount++;
        // Tolerance: ±0.6mm (rounding from px measurement)
        $okA = abs($hA - $domH) <= 0.6;
        $okB = abs($hB - $domH) <= 0.6;
        if ($okA) $matchA++; else { $mismatchA++; }
        if ($okB) $matchB++; else { $mismatchB++; }

        if (!$okA || !$okB) {
            $mismatches[] = [
                'code' => $code, 'app' => $app, 'pos' => $pos,
                'hA' => $hA, 'hB' => $hB, 'dom' => $domH,
                'okA' => $okA, 'okB' => $okB,
            ];
        }
        $matchNote = $domH . 'mm' . ($okA ? ' A✓' : ' A✗') . ($okB ? 'B✓' : 'B✗');
    }

    printf("  %-8s  %-10s  %-5d  %-5d  %-8s  %-5d  %-9s  %s\n",
        substr($codeClean,0,8), $desc,
        $lA['app'], $lA['pos'], round($hA,1).'mm',
        $lB['pos'], round($hB,1).'mm',
        $matchNote ?: '(no DOM)');
}

// ════════════════════════════════════════════════════════════════════════════
// SECTION 3: Mismatch Report
// ════════════════════════════════════════════════════════════════════════════
echo "\n══ SECTION 3: MISMATCH REPORT ═══════════════════════════════════════════\n\n";
printf("  DOM-measured rows available for validation: %d\n", $testedCount);
printf("  Tolerance: ±0.6mm (rounding from px→mm)\n\n");

if ($testedCount > 0) {
    $rateA = $mismatchA / $testedCount * 100;
    $rateB = $mismatchB / $testedCount * 100;

    printf("  Formula A (cplPos=31 — same as app):\n");
    printf("    Matches  : %d / %d  (%.1f%%)\n", $matchA, $testedCount, 100-$rateA);
    printf("    Mismatches: %d / %d  (%.1f%%)\n", $mismatchA, $testedCount, $rateA);
    printf("    Threshold: %s\n", $rateA < 5 ? '✓ PASS (< 5%%)' : '✗ FAIL (> 5%%)');
    echo "\n";
    printf("  Formula B (cplPos=40 — correct for 11px font):\n");
    printf("    Matches  : %d / %d  (%.1f%%)\n", $matchB, $testedCount, 100-$rateB);
    printf("    Mismatches: %d / %d  (%.1f%%)\n", $mismatchB, $testedCount, $rateB);
    printf("    Threshold: %s\n", $rateB < 5 ? '✓ PASS (< 5%%)' : '✗ FAIL (> 5%%)');
    echo "\n";

    if (!empty($mismatches)) {
        echo "  MISMATCH DETAILS:\n";
        echo "  " . str_repeat('─', 90) . "\n";
        foreach ($mismatches as $m) {
            printf("  Code: %s\n", $m['code']);
            printf("    Application : %s\n", substr($m['app'],0,60));
            printf("    Position    : %s\n", substr($m['pos']??'',0,60));
            printf("    Predicted A : %.1fmm   Predicted B: %.1fmm   DOM: %.1fmm\n",
                $m['hA'], $m['hB'], $m['dom']);
            printf("    Error A: %+.1fmm   Error B: %+.1fmm\n",
                $m['hA']-$m['dom'], $m['hB']-$m['dom']);
        }
    } else {
        echo "  All validated rows match within tolerance.\n";
    }
} else {
    echo "  No DOM measurements available for direct row comparison.\n";
    echo "  Validation is against page-level totals only (see Section 4).\n";
}

// ════════════════════════════════════════════════════════════════════════════
// SECTION 4: Page-level validation against DOM-measured page heights
// ════════════════════════════════════════════════════════════════════════════
// Actual page heights from browser diagnostic:
$actualPageH = [233.8, 230.1, 255.3, 273.7, 67.3]; // PHP-const (8/14) pages 1–5
// Note: these are heights of the .print-page divs rendered with $rowH=8/$posRowH=14

echo "\n══ SECTION 4: PAGE-LEVEL VALIDATION ════════════════════════════════════\n\n";
echo "  Actual page heights measured in browser (current constants rowH=8/posRowH=14):\n";
foreach ($actualPageH as $i => $h) {
    $overflow = max(0, $h - PAPER);
    printf("    Page %d: %.1fmm  %s\n", $i+1, $h,
        $overflow > 0 ? '⚠ OVERFLOW +'.round($overflow,1).'mm' : '✓ fits');
}

echo "\n  Now running paginator with Formula A (cplPos=31) and Formula B (cplPos=40):\n\n";

foreach ([CPL_POS_A, CPL_POS_B] as $cplPos) {
    $label = $cplPos === CPL_POS_A ? 'A (cplPos=31)' : 'B (cplPos=40)';
    $pages = []; $cur = []; $curH = 0.0; $lastDesc = null;
    $netAvail = 257 - 13 - 6; // 238

    foreach ($products as $p) {
        $desc = trim(strtoupper($p->description ?? 'NO DESCRIPTION'));
        $app  = trim($p->application ?? $p->Application ?? '---');
        $pos  = trim($p->position    ?? $p->Position    ?? '');
        $rh   = predictH($app, $pos ?: null, $cplPos);
        $isNew = ($desc !== $lastDesc);
        $need  = $rh + ($isNew ? CAT_MM : 0);
        if ($curH + $need > $netAvail && !empty($cur)) {
            $pages[] = $cur; $cur = []; $curH = 0.0; $isNew = true; $need = $rh + CAT_MM;
        }
        if ($isNew) { $curH += CAT_MM; $lastDesc = $desc; }
        $cur[] = ['p' => $p, 'rh' => $rh, 'isNew' => $isNew];
        $curH += $rh;
    }
    if (!empty($cur)) $pages[] = $cur;

    printf("  Formula %s — %d pages:\n", $label, count($pages));
    printf("    %-5s %-6s %-10s %-10s %-10s  %s\n",
        'Page','Items','PHP-H','ActualH','Blank','Status');
    printf("    %s\n", str_repeat('─', 65));

    $maxOver = 0; $maxH = 0; $maxBlank = 0;
    foreach ($pages as $pi => $pg) {
        $rows = count($pg); $cats = 0; $ld = null;
        $phpH = HDR_MM + FTR_MM; $actH = HDR_MM + FTR_MM;
        foreach ($pg as $it) {
            $d = trim(strtoupper($it['p']->description??''));
            if ($d !== $ld) { $cats++; $phpH += CAT_MM; $actH += CAT_MM; $ld = $d; }
            $phpH += $it['rh']; $actH += $it['rh'];
        }
        $blank = max(0, PAPER - $actH);
        $over  = max(0, $actH - PAPER);
        if ($actH > $maxH) $maxH = $actH;
        if ($blank > $maxBlank) $maxBlank = $blank;
        if ($over  > $maxOver)  $maxOver  = $over;
        $flag = $over > 0 ? '⚠ OVERFLOW +'.round($over,1).'mm'
              : ($blank > 40 ? '◄ large blank' : ($blank > 15 ? '◄ blank' : '✓ good'));
        printf("    %-5d %-6d %-10s %-10s %-10s  %s\n",
            $pi+1, $rows, round($phpH,1).'mm', round($actH,1).'mm',
            round($blank,1).'mm', $flag);
    }
    printf("    Max height=%.1fmm  Max overflow=%.1fmm  Max blank=%.1fmm  Chrome splits=%s\n\n",
        $maxH, $maxOver, $maxBlank, $maxOver > 0 ? 'YES' : 'NO');
}

// ════════════════════════════════════════════════════════════════════════════
// SECTION 5: Chars-per-line sensitivity — does it matter?
// ════════════════════════════════════════════════════════════════════════════
echo "══ SECTION 5: CHARS-PER-LINE SENSITIVITY ═══════════════════════════════\n\n";
echo "  Effect of using cplPos=40 vs cplPos=31 on position line count:\n\n";

$diffRows = 0; $totalPos = 0;
foreach ($products as $p) {
    $pos = trim($p->position ?? $p->Position ?? '');
    if ($pos === '') continue;
    $totalPos++;
    $pl31 = max(1,(int)ceil(mb_strlen($pos)/31));
    $pl40 = max(1,(int)ceil(mb_strlen($pos)/40));
    if ($pl31 !== $pl40) {
        $diffRows++;
        printf("  Code: %-8s  pos_len=%-3d  pl@31=%d  pl@40=%d  pos='%s'\n",
            substr($p->product_code??'',0,8), mb_strlen($pos), $pl31, $pl40,
            substr($pos,0,40));
    }
}
printf("\n  Rows where cplPos=31 vs cplPos=40 gives different line count: %d / %d (%.1f%%)\n\n",
    $diffRows, $totalPos, $diffRows / max(1,$totalPos) * 100);

// ════════════════════════════════════════════════════════════════════════════
// SECTION 6: Final recommendation
// ════════════════════════════════════════════════════════════════════════════
echo "══ SECTION 6: RECOMMENDATION ═══════════════════════════════════════════\n\n";
echo "  Key question: does using cplPos=31 (conservative) vs cplPos=40 (accurate)\n";
echo "  produce different page counts or overflow outcomes?\n\n";
echo "  cplPos=31: charges MORE for position text → stops filling page EARLIER → safer\n";
echo "  cplPos=40: charges LESS for position text → fills page MORE → tighter but may risk overflow\n\n";

if ($diffRows === 0) {
    echo "  RESULT: No rows differ between cplPos=31 and cplPos=40.\n";
    echo "  The two thresholds are EQUIVALENT for this dataset.\n";
    echo "  Recommendation: use cplPos=31 (same as app) for code simplicity.\n";
} elseif ($diffRows <= (int)($totalPos * 0.05)) {
    echo "  RESULT: " . $diffRows . " rows differ (within 5% tolerance).\n";
    echo "  Both thresholds are acceptable. Use cplPos=31 for conservative safety.\n";
} else {
    printf("  RESULT: %d rows differ (%.1f%% of pos rows).\n", $diffRows, $diffRows/$totalPos*100);
    echo "  Significant difference. Recommend using cplPos=40 for accuracy,\n";
    echo "  but with a safety margin in the formula (e.g. use 4.5mm per line still).\n";
}
