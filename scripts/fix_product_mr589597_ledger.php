<?php

/**
 * Script: fix_product_mr589597_ledger.php
 *
 * Requirements:
 * 1. Product code: mr-589597
 * 2. Date range: 2023 - 2024 only
 * 3. Match transactions: "Online Report Generation" and "CHGINVC" OUT movements
 * 4. Condition: OUM is 'PC' or 'pc'
 * 5. Update:
 *    - Quantity OUT: 2 = 1 (all out with 2 becomes 1; ratio 2 = 1)
 *    - OUM: 'PC' / 'pc' becomes 'SET'
 * 6. Scope constraint:
 *    - DO NOT TOUCH 2025 - 2026 records!
 *    - ONLY recalculate running stock balance for 2023 - 2024!
 *    - DO NOT recalculate or overwrite latest balance stock (products.on_hand)!
 *
 * Usage:
 *   php scripts/fix_product_mr589597_ledger.php --dry-run
 *   php scripts/fix_product_mr589597_ledger.php --run
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$options = getopt('', [
    'dry-run',
    'run',
    'execute',
    'product-code::',
    'product-id::',
]);

$dryRun = isset($options['dry-run']) || (!isset($options['run']) && !isset($options['execute']));
$targetProductCode = trim((string) ($options['product-code'] ?? 'mr-589597'));
$forcedProductId = isset($options['product-id']) ? (int) $options['product-id'] : null;

echo "====================================================================\n";
echo "  W68 PRODUCT LEDGER ADJUSTMENT: {$targetProductCode} (2023-2024)\n";
echo "====================================================================\n";
echo "Mode: " . ($dryRun ? "PREVIEW / DRY-RUN (no changes committed)" : "LIVE EXECUTION (changes will be saved)") . "\n";
echo "Target Code: {$targetProductCode}\n";
echo "Rules:\n";
echo "  - Date Range: 2023-01-01 to 2024-12-31 ONLY\n";
echo "  - Movements: 'Online Report Generation' OR 'CHGINVC' (OUT)\n";
echo "  - OUM Condition: PC / pc -> SET\n";
echo "  - Out Quantity: 2 = 1 (e.g. 2 becomes 1)\n";
echo "  - 2025-2026: UNTOUCHED (0 changes)\n";
echo "  - Latest stock on_hand: UNTOUCHED (preserved as-is)\n";
echo "--------------------------------------------------------------------\n";

// 1. Resolve Product ID
$product = null;
$productId = $forcedProductId;

if (!$productId) {
    try {
        $product = DB::connection('masterlist')->table('products')
            ->where(function ($q) use ($targetProductCode) {
                $clean = strtolower($targetProductCode);
                $noDash = str_replace('-', '', $clean);
                $q->whereRaw('LOWER(TRIM(product_code)) = ?', [$clean])
                  ->orWhereRaw('LOWER(TRIM(part_number)) = ?', [$clean])
                  ->orWhereRaw('LOWER(TRIM(product_code2)) = ?', [$clean])
                  ->orWhereRaw('LOWER(TRIM(pricelist_code)) = ?', [$clean])
                  ->orWhereRaw("REPLACE(LOWER(TRIM(product_code)), '-', '') = ?", [$noDash])
                  ->orWhereRaw("REPLACE(LOWER(TRIM(part_number)), '-', '') = ?", [$noDash]);
            })
            ->first();
    } catch (\Throwable $e) {
        echo "Note: Querying masterlist connection error: " . $e->getMessage() . "\n";
    }

    if (!$product) {
        try {
            $product = DB::table('products')
                ->where(function ($q) use ($targetProductCode) {
                    $clean = strtolower($targetProductCode);
                    $q->whereRaw('LOWER(TRIM(product_code)) = ?', [$clean])
                      ->orWhereRaw('LOWER(TRIM(part_number)) = ?', [$clean]);
                })
                ->first();
        } catch (\Throwable $e) {}
    }

    if ($product) {
        $productId = (int) $product->id;
    }
}

// Check product_ledgers table directly if still not found
if (!$productId) {
    try {
        if (Schema::connection('ledger')->hasColumn('product_ledgers', 'product_code')) {
            $plRow = DB::connection('ledger')->table('product_ledgers')
                ->whereRaw('LOWER(TRIM(product_code)) = ?', [strtolower($targetProductCode)])
                ->first();
            if ($plRow) {
                $productId = (int) $plRow->product_id;
            }
        }
    } catch (\Throwable $e) {}
}

if (!$productId) {
    echo "ERROR: Could not locate product record for code '{$targetProductCode}'.\n";
    echo "Please specify product ID manually using --product-id=<ID>.\n";
    exit(1);
}

$productCodeFound = $product->product_code ?? $targetProductCode;
$partNumberFound = $product->part_number ?? 'N/A';
$onHandCurrent = $product->on_hand ?? 'N/A';
echo "Found Product:\n";
echo "  ID:        {$productId}\n";
echo "  Code:      {$productCodeFound}\n";
echo "  Part#:     {$partNumberFound}\n";
echo "  On Hand:   {$onHandCurrent} (WILL NOT BE MODIFIED)\n";
echo "--------------------------------------------------------------------\n";

// 2. Fetch baseline balance before 2023-01-01
$prevRow = DB::connection('ledger')->table('product_ledgers')
    ->where('product_id', $productId)
    ->where('date', '<', '2023-01-01')
    ->orderByDesc('date')
    ->orderByDesc('id')
    ->first();

$runningBalance = $prevRow ? (float) $prevRow->balance_stock : 0;
echo "Starting Balance before 2023: {$runningBalance}\n\n";

// 3. Fetch 2023-2024 rows
$rows = DB::connection('ledger')->table('product_ledgers')
    ->where('product_id', $productId)
    ->where('date', '>=', '2023-01-01')
    ->where('date', '<=', '2024-12-31')
    ->orderBy('date', 'asc')
    ->orderBy('id', 'asc')
    ->get();

$totalRows2023_2024 = $rows->count();
echo "Total Ledger entries in 2023-2024: {$totalRows2023_2024}\n\n";

if ($totalRows2023_2024 === 0) {
    echo "No ledger entries found in 2023-2024 for product #{$productId}.\n";
    exit(0);
}

$matchedCount = 0;
$qtyOutAdjustedCount = 0;
$oumAdjustedCount = 0;
$balanceUpdatedCount = 0;
$updates = [];

printf("%-6s | %-10s | %-15s | %-12s | %-12s | %-10s | %-12s | %-25s\n",
    "ID", "Date", "Invoice/Ref#", "Trans#", "Qty Out", "OUM", "Balance", "Remarks");
echo str_repeat("-", 115) . "\n";

foreach ($rows as $row) {
    $rowId = (int) $row->id;
    $date = (string) $row->date;
    $refNum = trim((string) ($row->reference_number ?? ''));
    $transNum = trim((string) ($row->transaction_number ?? ''));
    $currentOum = trim((string) ($row->oum ?? ''));
    $currentQtyIn = (float) ($row->quantity_in ?? 0);
    $currentQtyOut = (float) ($row->quantity_out ?? 0);
    $currentBalance = (float) ($row->balance_stock ?? 0);
    $remarks = (string) ($row->remarks ?? '');
    $sourceType = strtolower(trim((string) ($row->source_type ?? '')));
    $transType = strtoupper(trim((string) ($row->transaction_type ?? '')));

    $newQtyOut = $currentQtyOut;
    $newOum = $currentOum;
    $newRemarks = $remarks;
    $isModified = false;

    // Check if movement is Online Report Generation or CHGINVC
    $isOnlineReport = (stripos($remarks, 'Online Report Generation') !== false)
        || in_array($sourceType, ['online_report', 'online-report'], true);

    $isChginvc = ($transType === 'CHGINVC')
        || (stripos($remarks, 'CHGINVC') !== false)
        || (stripos($transNum, 'CHGINVC') !== false)
        || (stripos($refNum, 'CHGINVC') !== false);

    $isTargetType = ($isOnlineReport || $isChginvc);
    $isOut = ($currentQtyOut > 0) || ($transType === 'OUT') || ($transType === 'CHGINVC');

    $isOumPc = (strtolower($currentOum) === 'pc');

    if ($isTargetType && $isOut) {
        $matchedCount++;

        // If OUM is PC or pc
        if ($isOumPc) {
            $newOum = 'SET';
            $oumAdjustedCount++;

            // 2 = 1: if out is 2, make it 1 (divide by 2)
            if (!str_contains($remarks, '[2=1 SET]')) {
                if ($currentQtyOut > 0) {
                    $calculated = round($currentQtyOut / 2, 4);
                    $newQtyOut = max(1, (int) round($calculated));
                    $newRemarks = trim($remarks . " [2=1 SET: qty_out {$currentQtyOut} -> {$newQtyOut}]");
                    $qtyOutAdjustedCount++;
                }
            }
            $isModified = true;
        } elseif (strtolower($currentOum) === 'set' && !str_contains($remarks, '[2=1 SET]')) {
            // If already set to SET but quantity is 2
            if ($currentQtyOut == 2) {
                $newQtyOut = 1;
                $newRemarks = trim($remarks . " [2=1 SET: qty_out {$currentQtyOut} -> 1]");
                $qtyOutAdjustedCount++;
                $isModified = true;
            }
        }
    }

    // Running balance recalculation for 2023-2024
    $runningBalance = $runningBalance + $currentQtyIn - $newQtyOut;
    $newBalanceStock = (int) round($runningBalance);

    if ((int) round($currentBalance) !== $newBalanceStock) {
        $balanceUpdatedCount++;
        $isModified = true;
    }

    if ($isModified || $isTargetType) {
        $qtyDisplay = ($currentQtyOut != $newQtyOut) ? "{$currentQtyOut} -> {$newQtyOut}" : "{$currentQtyOut}";
        $oumDisplay = ($currentOum !== $newOum) ? "{$currentOum} -> {$newOum}" : "{$currentOum}";
        $balDisplay = ($currentBalance != $newBalanceStock) ? "{$currentBalance} -> {$newBalanceStock}" : "{$newBalanceStock}";

        printf("%-6d | %-10s | %-15s | %-12s | %-12s | %-10s | %-12s | %-25s\n",
            $rowId,
            $date,
            substr($refNum ?: '-', 0, 15),
            substr($transNum ?: '-', 0, 12),
            $qtyDisplay,
            $oumDisplay,
            $balDisplay,
            substr($newRemarks, 0, 25)
        );
    }

    $updates[] = [
        'id' => $rowId,
        'quantity_out' => $newQtyOut,
        'oum' => $newOum,
        'balance_stock' => $newBalanceStock,
        'remarks' => $newRemarks,
        'is_modified' => $isModified,
    ];
}

echo str_repeat("-", 115) . "\n";
echo "Summary of planned changes for product #{$productId} (2023-2024):\n";
echo "  Matched Online Report / CHGINVC OUT rows: {$matchedCount}\n";
echo "  Rows with OUM changed from PC/pc to SET:   {$oumAdjustedCount}\n";
echo "  Rows with Quantity OUT adjusted (2 = 1):  {$qtyOutAdjustedCount}\n";
echo "  2023-2024 Balance Stock recalculated:     {$balanceUpdatedCount}\n";
echo "  2025-2026 rows affected:                  0 (EXPLICITLY UNTOUCHED)\n";
echo "  Latest balance on_hand:                   {$onHandCurrent} (EXPLICITLY PRESERVED)\n";

if ($dryRun) {
    echo "\n====================================================================\n";
    echo "[DRY RUN COMPLETE] No records were modified.\n";
    echo "To apply these changes, execute:\n";
    echo "  php scripts/fix_product_mr589597_ledger.php --run\n";
    echo "====================================================================\n";
    exit(0);
}

echo "\nWriting updates to database...\n";

DB::connection('ledger')->transaction(function () use ($updates) {
    $now = now();
    $updatedCount = 0;

    foreach ($updates as $u) {
        if (!$u['is_modified']) {
            continue;
        }

        DB::connection('ledger')->table('product_ledgers')
            ->where('id', $u['id'])
            ->update([
                'quantity_out' => $u['quantity_out'],
                'oum' => $u['oum'],
                'balance_stock' => $u['balance_stock'],
                'remarks' => $u['remarks'],
                'updated_at' => $now,
            ]);

        $updatedCount++;
    }

    echo "  Updated {$updatedCount} product_ledgers rows in 2023-2024.\n";
});

echo "\n====================================================================\n";
echo "SUCCESS! 2023-2024 ledger adjustments completed.\n";
echo "2025-2026 rows and latest balance on_hand were NOT modified.\n";
echo "====================================================================\n";
