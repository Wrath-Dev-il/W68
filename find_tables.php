<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tables = \Illuminate\Support\Facades\DB::connection('sales')->select('SHOW TABLES');
echo "=== All tables in sales database ===\n";
foreach ($tables as $t) {
    $name = reset($t);
    echo "  $name\n";
}

// Check what tables exist for this key product
echo "\n=== Checking invoice references for product 4148 ===\n";
$entries = \Illuminate\Support\Facades\DB::connection('ledger')
    ->select("SELECT id, transaction_number, reference_number, remarks FROM product_ledgers WHERE product_id = 4148 AND YEAR(date) = 2026 ORDER BY date, id");
foreach ($entries as $e) {
    echo "id={$e->id} trans#={$e->transaction_number} ref={$e->reference_number} remarks={$e->remarks}\n";
}

// Check if there's a way to link 65510 to a sales note
echo "\n=== Checking sales_notes for 65510 patterns ===\n";
$sn = \App\Models\SalesNote::on('sales')
    ->where('sales_number', 'LIKE', '%65510%')
    ->orWhere('id', 65510)
    ->orWhere('sales_number', 'LIKE', '%RET%')
    ->limit(5)
    ->pluck('sales_number');
echo "Notes matching patterns: " . json_encode($sn) . "\n";

// Check the invoice table - is it maybe sales_orders?
echo "\n=== Looking for CHGINVC creation =====\n";
// The entries with 65510, 65567, 65607 are CHGINVC
// Maybe they're online sales order numbers
$so = \Illuminate\Support\Facades\DB::connection('sales')
    ->table('sales_orders')
    ->where('id', 65510)
    ->orWhere('order_number', '65510')
    ->first();
if ($so) {
    echo "SalesOrder: id={$so->id} order_number={$so->order_number}\n";
    $note = \App\Models\SalesNote::on('sales')->find($so->sales_note_id);
    echo "  -> SalesNote: " . ($note ? $note->sales_number : 'none') . "\n";
} else {
    echo "No SalesOrder for 65510\n";
}
