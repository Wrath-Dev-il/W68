<?php

/**
 * Script: fix_product_48674_26040_n_ledger.php
 *
 * Task:
 * 1. For product code 48674-26040-N in Product Ledger:
 *    - 2023 & 2024 OUT transactions:
 *      * OUM set to 'SET'
 *      * Quantity OUT divided by 4 (4 = 1; e.g. 8 becomes 2)
 *    - Invoices with 'SH' -> Customer set to 'SHOPEE ONLINE BUYERS'
 *    - Invoices with 'LZ' -> Customer set to 'LAZADA ONLINE BUYERS'
 *    - Running balance_stock recalculated across all rows for this product
 *    - products.on_hand synchronized with final balance_stock
 *
 * Usage:
 *   php scripts/fix_product_48674_26040_n_ledger.php --dry-run
 *   php scripts/fix_product_48674_26040_n_ledger.php --run
 *   php scripts/fix_product_48674_26040_n_ledger.php --run --all-sh-lz
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\ProductStockSyncService;

// Parse CLI options
$options = getopt('', [
    'dry-run',
    'run',
    'execute',
    'product-code::',
    'product-id::',
    'all-sh-lz',
]);

$dryRun = isset($options['dry-run']) || (!isset($options['run']) && !isset($options['execute']));
$targetProductCode = trim((string) ($options['product-code'] ?? '48674-26040-N'));
$forcedProductId = isset($options['product-id']) ? (int) $options['product-id'] : null;
$updateAllShLz = isset($options['all-sh-lz']);

echo "====================================================================\n";
echo "  W68 PRODUCT LEDGER ADJUSTMENT & RECALCULATION TOOL\n";
echo "====================================================================\n";
echo "Mode: " . ($dryRun ? "PREVIEW / DRY-RUN (no changes committed)" : "LIVE EXECUTION (changes will be saved)") . "\n";
echo "Target Product Code: {$targetProductCode}\n";
if ($forcedProductId) {
    echo "Forced Product ID: {$forcedProductId}\n";
}
echo "--------------------------------------------------------------------\n";

// 1. Resolve Product ID
$product = null;
$productId = $forcedProductId;

if (!$productId) {
    // Try masterlist connection
    try {
        $product = DB::connection('masterlist')->table('products')
            ->where(function ($q) use ($targetProductCode) {
                $q->whereRaw('LOWER(TRIM(product_code)) = ?', [strtolower($targetProductCode)])
                  ->orWhereRaw('LOWER(TRIM(part_number)) = ?', [strtolower($targetProductCode)])
                  ->orWhereRaw('LOWER(TRIM(product_code2)) = ?', [strtolower($targetProductCode)])
                  ->orWhereRaw('LOWER(TRIM(pricelist_code)) = ?', [strtolower($targetProductCode)]);
            })
            ->first();
    } catch (\Throwable $e) {
        echo "Note: Querying masterlist connection error: " . $e->getMessage() . "\n";
    }

    // Fallback to default connection if not found
    if (!$product) {
        try {
            $product = DB::table('products')
                ->where(function ($q) use ($targetProductCode) {
                    $q->whereRaw('LOWER(TRIM(product_code)) = ?', [strtolower($targetProductCode)])
                      ->orWhereRaw('LOWER(TRIM(part_number)) = ?', [strtolower($targetProductCode)]);
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
$onHandBefore = $product->on_hand ?? 'N/A';
echo "Found Product:\n";
echo "  ID:        {$productId}\n";
echo "  Code:      {$productCodeFound}\n";
echo "  Part#:     {$partNumberFound}\n";
echo "  On Hand:   {$onHandBefore}\n";
echo "--------------------------------------------------------------------\n";

// 2. Resolve Customer IDs for Shopee and Lazada
$shopeeCustomer = null;
$lazadaCustomer = null;

try {
    $shopeeCustomer = DB::connection('masterlist')->table('customers')
        ->where(function ($q) {
            $q->whereRaw('LOWER(TRIM(name)) = ?', ['shopee online buyers'])
              ->orWhereRaw('LOWER(TRIM(name)) LIKE ?', ['%shopee online%']);
        })
        ->first();

    $lazadaCustomer = DB::connection('masterlist')->table('customers')
        ->where(function ($q) {
            $q->whereRaw('LOWER(TRIM(name)) = ?', ['lazada online buyers'])
              ->orWhereRaw('LOWER(TRIM(name)) LIKE ?', ['%lazada online%']);
        })
        ->first();
} catch (\Throwable $e) {
    echo "Note: Querying customers error: " . $e->getMessage() . "\n";
}

$shopeeCustomerId = $shopeeCustomer ? (int) $shopeeCustomer->id : null;
$shopeeCustomerName = 'SHOPEE ONLINE BUYERS';

$lazadaCustomerId = $lazadaCustomer ? (int) $lazadaCustomer->id : null;
$lazadaCustomerName = 'LAZADA ONLINE BUYERS';

echo "Customer Mappings:\n";
echo "  Shopee: " . ($shopeeCustomerId ? "ID {$shopeeCustomerId} | {$shopeeCustomer->name}" : "ID None | {$shopeeCustomerName}") . "\n";
echo "  Lazada: " . ($lazadaCustomerId ? "ID {$lazadaCustomerId} | {$lazadaCustomer->name}" : "ID None | {$lazadaCustomerName}") . "\n";
echo "--------------------------------------------------------------------\n";

// 3. Fetch Ledger rows for this product
$rows = DB::connection('ledger')->table('product_ledgers')
    ->where('product_id', $productId)
    ->orderBy('date', 'asc')
    ->orderBy('id', 'asc')
    ->get();

$totalRows = $rows->count();
echo "Total Ledger entries for Product #{$productId}: {$totalRows}\n\n";

if ($totalRows === 0) {
    echo "No ledger entries found for product #{$productId}.\n";
    exit(0);
}

$qtyOutAdjustedCount = 0;
$shopeeUpdatedCount = 0;
$lazadaUpdatedCount = 0;
$balanceUpdatedCount = 0;

$runningBalance = 0;
$updates = [];

printf("%-6s | %-10s | %-15s | %-12s | %-10s | %-22s | %-12s\n",
    "ID", "Date", "Invoice/Ref#", "Trans#", "Qty Out", "Customer Name", "Balance");
echo str_repeat("-", 100) . "\n";

foreach ($rows as $index => $row) {
    $rowId = (int) $row->id;
    $date = (string) $row->date;
    $year = (int) date('Y', strtotime($date));
    $refNum = trim((string) ($row->reference_number ?? ''));
    $transNum = trim((string) ($row->transaction_number ?? ''));
    $currentName = trim((string) ($row->entity_name ?? ''));
    $currentOum = trim((string) ($row->oum ?? ''));
    $currentQtyIn = (float) ($row->quantity_in ?? 0);
    $currentQtyOut = (float) ($row->quantity_out ?? 0);
    $currentBalance = (float) ($row->balance_stock ?? 0);
    $remarks = (string) ($row->remarks ?? '');

    $newQtyOut = $currentQtyOut;
    $newOum = $currentOum;
    $newName = $currentName;
    $newCustomerId = $row->customer_id ?? null;
    $newRemarks = $remarks;

    $isOut = ($currentQtyOut > 0) || (strtoupper(trim((string) $row->transaction_type)) === 'OUT');
    $is2023or2024 = ($year === 2023 || $year === 2024);

    $isModified = false;

    // A. 2023 & 2024 OUT Adjustment: 4 = 1 and OUM = 'SET'
    if ($is2023or2024 && $isOut) {
        $alreadyAdjusted = str_contains($remarks, '[4=1 SET]');

        if (!$alreadyAdjusted && $currentQtyOut > 0) {
            $newQtyOut = round($currentQtyOut / 4, 4);
            // If whole integer, cast to int
            if (floor($newQtyOut) == $newQtyOut) {
                $newQtyOut = (int) $newQtyOut;
            }
            $newOum = 'SET';
            $newRemarks = trim($remarks . " [4=1 SET: qty_out {$currentQtyOut} -> {$newQtyOut}]");
            $qtyOutAdjustedCount++;
            $isModified = true;
        } elseif (!$alreadyAdjusted && $currentOum !== 'SET') {
            $newOum = 'SET';
            $isModified = true;
        }
    }

    // B. Invoice check for SH and LZ
    // Exclude plain cash words like 'CASH' or 'GCASH'
    $isCashWord = preg_match('/\b(cash|gcash)\b/i', $refNum) || preg_match('/\b(cash|gcash)\b/i', $transNum);

    $hasSh = !$isCashWord && (
        stripos($refNum, 'SH') !== false ||
        stripos($transNum, 'SH') !== false ||
        stripos($remarks, 'shopee') !== false
    );

    $hasLz = (
        stripos($refNum, 'LZ') !== false ||
        stripos($transNum, 'LZ') !== false ||
        stripos($remarks, 'lazada') !== false
    );

    if ($hasSh) {
        if ($newName !== $shopeeCustomerName) {
            $newName = $shopeeCustomerName;
            $newCustomerId = $shopeeCustomerId ?? $row->customer_id;
            $shopeeUpdatedCount++;
            $isModified = true;
        }
    } elseif ($hasLz) {
        if ($newName !== $lazadaCustomerName) {
            $newName = $lazadaCustomerName;
            $newCustomerId = $lazadaCustomerId ?? $row->customer_id;
            $lazadaUpdatedCount++;
            $isModified = true;
        }
    }

    // C. Running balance recalculation
    $runningBalance = $runningBalance + $currentQtyIn - $newQtyOut;
    $newBalanceStock = (int) round($runningBalance);

    if ((int) round($currentBalance) !== $newBalanceStock) {
        $balanceUpdatedCount++;
        $isModified = true;
    }

    // Log display for modified or key rows
    if ($isModified || $is2023or2024 || $hasSh || $hasLz) {
        $qtyDisplay = ($currentQtyOut != $newQtyOut) ? "{$currentQtyOut} -> {$newQtyOut}" : "{$currentQtyOut}";
        $nameDisplay = ($currentName !== $newName) ? substr($currentName, 0, 8) . "->" . substr($newName, 0, 10) : substr($newName ?: '-', 0, 20);
        $balDisplay = ($currentBalance != $newBalanceStock) ? "{$currentBalance} -> {$newBalanceStock}" : "{$newBalanceStock}";

        printf("%-6d | %-10s | %-15s | %-12s | %-10s | %-22s | %-12s\n",
            $rowId,
            $date,
            substr($refNum ?: '-', 0, 15),
            substr($transNum ?: '-', 0, 12),
            $qtyDisplay,
            $nameDisplay,
            $balDisplay
        );
    }

    $updates[] = [
        'id' => $rowId,
        'quantity_out' => $newQtyOut,
        'oum' => $newOum,
        'entity_name' => $newName,
        'customer_id' => $newCustomerId,
        'balance_stock' => $newBalanceStock,
        'remarks' => $newRemarks,
        'is_modified' => $isModified,
    ];
}

echo str_repeat("-", 100) . "\n";
echo "Summary of planned changes for product #{$productId}:\n";
echo "  2023-2024 OUT transactions adjusted (OUM=SET, Qty/4): {$qtyOutAdjustedCount}\n";
echo "  SH Invoices updated to 'SHOPEE ONLINE BUYERS':        {$shopeeUpdatedCount}\n";
echo "  LZ Invoices updated to 'LAZADA ONLINE BUYERS':        {$lazadaUpdatedCount}\n";
echo "  Ledger Balance Stock recalculations:                  {$balanceUpdatedCount}\n";
echo "  Final Stock Balance:                                  {$runningBalance}\n";

// Optional: Global SH / LZ update across all products if requested
$globalShCount = 0;
$globalLzCount = 0;
if ($updateAllShLz) {
    echo "\n--------------------------------------------------------------------\n";
    echo "Global Ledger Online Customers Update (--all-sh-lz active):\n";

    $globalShRows = DB::connection('ledger')->table('product_ledgers')
        ->where(function ($q) {
            $q->where('reference_number', 'LIKE', '%SH%')
              ->orWhere('transaction_number', 'LIKE', '%SH%');
        })
        ->where('reference_number', 'NOT LIKE', '%CASH%')
        ->where('transaction_number', 'NOT LIKE', '%CASH%')
        ->where(function ($q) use ($shopeeCustomerName) {
            $q->whereNull('entity_name')
              ->orWhere('entity_name', '!=', $shopeeCustomerName);
        })
        ->count();

    $globalLzRows = DB::connection('ledger')->table('product_ledgers')
        ->where(function ($q) {
            $q->where('reference_number', 'LIKE', '%LZ%')
              ->orWhere('transaction_number', 'LIKE', '%LZ%');
        })
        ->where(function ($q) use ($lazadaCustomerName) {
            $q->whereNull('entity_name')
              ->orWhere('entity_name', '!=', $lazadaCustomerName);
        })
        ->count();

    echo "  Total other SH invoices to update: {$globalShRows}\n";
    echo "  Total other LZ invoices to update: {$globalLzRows}\n";
    $globalShCount = $globalShRows;
    $globalLzCount = $globalLzRows;
}

// 4. Commit Changes if Live Execution
if ($dryRun) {
    echo "\n====================================================================\n";
    echo "[DRY RUN COMPLETE] No records were altered.\n";
    echo "To apply these changes permanently, execute:\n";
    echo "  php scripts/fix_product_48674_26040_n_ledger.php --run\n";
    echo "====================================================================\n";
    exit(0);
}

echo "\nWriting updates to database...\n";

DB::connection('ledger')->transaction(function () use ($updates, $updateAllShLz, $shopeeCustomerName, $shopeeCustomerId, $lazadaCustomerName, $lazadaCustomerId) {
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
                'entity_name' => $u['entity_name'],
                'customer_id' => $u['customer_id'],
                'balance_stock' => $u['balance_stock'],
                'remarks' => $u['remarks'],
                'updated_at' => $now,
            ]);

        $updatedCount++;
    }

    echo "  Updated {$updatedCount} product_ledgers rows for target product.\n";

    if ($updateAllShLz) {
        $shGlobalUpdated = DB::connection('ledger')->table('product_ledgers')
            ->where(function ($q) {
                $q->where('reference_number', 'LIKE', '%SH%')
                  ->orWhere('transaction_number', 'LIKE', '%SH%');
            })
            ->where('reference_number', 'NOT LIKE', '%CASH%')
            ->where('transaction_number', 'NOT LIKE', '%CASH%')
            ->where(function ($q) use ($shopeeCustomerName) {
                $q->whereNull('entity_name')
                  ->orWhere('entity_name', '!=', $shopeeCustomerName);
            })
            ->update([
                'entity_name' => $shopeeCustomerName,
                'customer_id' => $shopeeCustomerId,
                'updated_at' => $now,
            ]);

        $lzGlobalUpdated = DB::connection('ledger')->table('product_ledgers')
            ->where(function ($q) {
                $q->where('reference_number', 'LIKE', '%LZ%')
                  ->orWhere('transaction_number', 'LIKE', '%LZ%');
            })
            ->where(function ($q) use ($lazadaCustomerName) {
                $q->whereNull('entity_name')
                  ->orWhere('entity_name', '!=', $lazadaCustomerName);
            })
            ->update([
                'entity_name' => $lazadaCustomerName,
                'customer_id' => $lazadaCustomerId,
                'updated_at' => $now,
            ]);

        echo "  Updated {$shGlobalUpdated} global SH rows and {$lzGlobalUpdated} global LZ rows.\n";
    }
});

// 5. Synchronize products.on_hand
$finalOnHand = (int) round($runningBalance);
try {
    DB::connection('masterlist')->table('products')
        ->where('id', $productId)
        ->update([
            'on_hand' => $finalOnHand,
            'updated_at' => now(),
        ]);
    echo "Successfully synced masterlist products.on_hand = {$finalOnHand} for Product #{$productId}.\n";

    if (class_exists(ProductStockSyncService::class) && method_exists(ProductStockSyncService::class, 'clearProductMasterStatsCache')) {
        ProductStockSyncService::clearProductMasterStatsCache();
    }
} catch (\Throwable $e) {
    echo "Warning: Could not update products.on_hand on masterlist connection: " . $e->getMessage() . "\n";
}

echo "\n====================================================================\n";
echo "SUCCESS! Ledger adjustment and stock recalculation completed.\n";
echo "====================================================================\n";
