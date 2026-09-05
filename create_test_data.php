<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== CREATING TEST DATA FOR EDIT SO VALIDATION ===\n\n";

// Find products with different stock levels
$product_with_stock = DB::connection('masterlist')->table('products')
    ->where('on_hand', '>', 10)
    ->first(['id', 'product_code', 'description', 'on_hand']);

$product_low_stock = DB::connection('masterlist')->table('products')
    ->where('on_hand', '>', 0)
    ->where('on_hand', '<=', 5)
    ->first(['id', 'product_code', 'description', 'on_hand']);

$product_no_stock = DB::connection('masterlist')->table('products')
    ->where('on_hand', '=', 0)
    ->first(['id', 'product_code', 'description', 'on_hand']);

if (!$product_with_stock || !$product_low_stock || !$product_no_stock) {
    echo "❌ Cannot find products with required stock levels\n";
    exit(1);
}

echo "Products selected:\n";
echo "  1. {$product_with_stock->product_code} - Stock: {$product_with_stock->on_hand} ✓\n";
echo "  2. {$product_low_stock->product_code} - Stock: {$product_low_stock->on_hand} ⚠️\n";
echo "  3. {$product_no_stock->product_code} - Stock: {$product_no_stock->on_hand} ❌\n\n";

// Create Sales Note
$sales_note_id = DB::connection('sales')->table('sales_notes')->insertGetId([
    'sales_number' => 'SN-TEST-' . date('YmdHis'),
    'customer_id' => 1,
    'customer_name' => 'Test Customer',
    'so_type' => 'Sales Order',
    'order_date' => date('Y-m-d'),
    'status' => 'Open',
    'salesman' => 'Test Salesman',
    'prepared_by' => 'Test User',
    'is_rush' => 0,
    'gross_total' => 300.00,
    'total_discount' => 0,
    'net_total' => 300.00,
    'created_at' => now(),
    'updated_at' => now()
]);

echo "✓ Created Sales Note ID: {$sales_note_id}\n";

// Add items to Sales Note
DB::connection('sales')->table('sales_note_items')->insert([
    [
        'sales_note_id' => $sales_note_id,
        'product_id' => $product_with_stock->id,
        'product_code' => $product_with_stock->product_code,
        'description' => $product_with_stock->description,
        'quantity' => 2,
        'additional_qty' => 0,
        'oum' => 'PCS',
        'unit_price' => 50.00,
        'discount' => 0,
        'subtotal' => 100.00,
        'created_at' => now(),
        'updated_at' => now()
    ],
    [
        'sales_note_id' => $sales_note_id,
        'product_id' => $product_low_stock->id,
        'product_code' => $product_low_stock->product_code,
        'description' => $product_low_stock->description,
        'quantity' => 1,
        'additional_qty' => 0,
        'oum' => 'PCS',
        'unit_price' => 100.00,
        'discount' => 0,
        'subtotal' => 100.00,
        'created_at' => now(),
        'updated_at' => now()
    ],
    [
        'sales_note_id' => $sales_note_id,
        'product_id' => $product_no_stock->id,
        'product_code' => $product_no_stock->product_code,
        'description' => $product_no_stock->description,
        'quantity' => 1,
        'additional_qty' => 0,
        'oum' => 'PCS',
        'unit_price' => 100.00,
        'discount' => 0,
        'subtotal' => 100.00,
        'created_at' => now(),
        'updated_at' => now()
    ]
]);

echo "✓ Added 3 items to Sales Note\n\n";

// Close the Sales Note to trigger SO creation
DB::connection('sales')->table('sales_notes')
    ->where('id', $sales_note_id)
    ->update(['status' => 'Closed', 'updated_at' => now()]);

echo "✓ Changed Sales Note status to Closed\n\n";

// Create Sales Order
$sales_order_id = DB::connection('sales')->table('sales_orders')->insertGetId([
    'sales_note_id' => $sales_note_id,
    'order_number' => 'SO-TEST-' . date('YmdHis'),
    'customer_id' => 1,
    'customer_name' => 'Test Customer',
    'invoice_numbers' => 'INV-TEST-001',
    'waybill_no' => '',
    'waybill_date' => null,
    'total_amount' => 300.00,
    'status' => 'Open',
    'remarks' => 'Test Sales Order for Edit SO validation',
    'created_at' => now(),
    'updated_at' => now()
]);

echo "✓ Created Sales Order ID: {$sales_order_id}\n";

// Add items to Sales Order
DB::connection('sales')->table('sales_order_items')->insert([
    [
        'sales_order_id' => $sales_order_id,
        'product_id' => $product_with_stock->id,
        'product_code' => $product_with_stock->product_code,
        'description' => $product_with_stock->description,
        'quantity' => 2,
        'actual_qty' => 2,
        'additional_qty' => 0,
        'oum' => 'PCS',
        'unit_price' => 50.00,
        'discount' => 0,
        'subtotal' => 100.00,
        'created_at' => now(),
        'updated_at' => now()
    ],
    [
        'sales_order_id' => $sales_order_id,
        'product_id' => $product_low_stock->id,
        'product_code' => $product_low_stock->product_code,
        'description' => $product_low_stock->description,
        'quantity' => 1,
        'actual_qty' => 1,
        'additional_qty' => 0,
        'oum' => 'PCS',
        'unit_price' => 100.00,
        'discount' => 0,
        'subtotal' => 100.00,
        'created_at' => now(),
        'updated_at' => now()
    ],
    [
        'sales_order_id' => $sales_order_id,
        'product_id' => $product_no_stock->id,
        'product_code' => $product_no_stock->product_code,
        'description' => $product_no_stock->description,
        'quantity' => 1,
        'actual_qty' => 1,
        'additional_qty' => 0,
        'oum' => 'PCS',
        'unit_price' => 100.00,
        'discount' => 0,
        'subtotal' => 100.00,
        'created_at' => now(),
        'updated_at' => now()
    ]
]);

echo "✓ Added 3 items to Sales Order\n\n";

echo "=== TEST DATA CREATED ===\n\n";
echo "Sales Note ID: {$sales_note_id}\n";
echo "Sales Order ID: {$sales_order_id}\n\n";

echo "Now you can test Edit SO validation:\n";
echo "1. Go to: http://localhost/hatdog/public/admin/sales/sales-order\n";
echo "2. Go to 'Sale's Order History' tab\n";
echo "3. Click 'Edit' on the test order\n";
echo "4. Try changing quantities:\n";
echo "   - Item 1 ({$product_with_stock->product_code}): Has stock, should allow increase\n";
echo "   - Item 2 ({$product_low_stock->product_code}): Low stock, should show warning if you exceed {$product_low_stock->on_hand}\n";
echo "   - Item 3 ({$product_no_stock->product_code}): NO STOCK, should show warning if you increase from 1\n";
echo "5. Open browser console (F12) to see validation logs\n\n";

echo "=== DONE ===\n";
