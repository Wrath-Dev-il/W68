<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "===========================================\n";
echo "CREATE TEST PURCHASE RETURN FOR PO 49\n";
echo "===========================================\n\n";

// Get PO 49 details
$po = DB::connection('purchase')->table('purchase_orders')->where('id', 49)->first();

if (!$po) {
    echo "❌ PO 49 not found\n";
    exit(1);
}

echo "PO 49 Details:\n";
echo "  PO Number: {$po->po_number}\n";
echo "  Supplier ID: {$po->supplier_id}\n";
echo "  Invoice: {$po->supplier_invoice_number}\n";
echo "  Total: {$po->total_amount}\n\n";

// Check if return already exists for PO 49
$existingReturn = DB::connection('purchase')->table('purchase_returns')
    ->where('po_id', 49)
    ->first();

if ($existingReturn) {
    echo "✅ Purchase return already exists for PO 49:\n";
    echo "  Return ID: {$existingReturn->id}\n";
    echo "  Return Number: {$existingReturn->return_number}\n";
    echo "  Amount: {$existingReturn->total_amount}\n";
    echo "  Date: {$existingReturn->date}\n";
} else {
    echo "Creating new purchase return for PO 49...\n";
    
    try {
        $returnId = DB::connection('purchase')->table('purchase_returns')->insertGetId([
            'po_id' => 49,
            'supplier_id' => $po->supplier_id,
            'return_number' => 'TEST-SR-PO49-001',
            'date' => now()->format('Y-m-d'),
            'total_amount' => 1000.00,
            'remarks' => 'Test sudden return for PO 49 verification',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        echo "✅ Purchase return created successfully!\n";
        echo "  Return ID: {$returnId}\n";
        echo "  Return Number: TEST-SR-PO49-001\n";
        echo "  Amount: 1000.00\n";
        echo "  PO ID: 49\n";
    } catch (\Exception $e) {
        echo "❌ Failed to create purchase return: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\n";
echo "===========================================\n";
echo "NOW TEST THE FIX:\n";
echo "===========================================\n";
echo "1. Go to: http://localhost/hatdog/public/admin/accounting/payable-cheque-voucher\n";
echo "2. Select supplier 622 (CELHERSON)\n";
echo "3. Select invoice from PO 49 (INV-002)\n";
echo "4. Go to Step 2.5 - Sudden Returns\n";
echo "5. You should ONLY see TEST-SR-PO49-001 (not RET-0000001 from PO 48)\n";
echo "6. Select it and complete the voucher\n";
echo "7. Run verify_sudden_returns_fix_complete.php to check database\n";
