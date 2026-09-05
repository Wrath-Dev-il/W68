<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

$pn = \App\Models\PurchaseNote::where('status', 'Open')->latest('id')->first();
if (!$pn) { echo "NO OPEN PN\n"; exit; }

$items = \App\Models\PurchaseNoteItem::where('purchase_note_id', $pn->id)->get();
if ($items->isEmpty()) { echo "PN has no items\n"; exit; }

$supplier = \App\Models\Supplier::find($pn->supplier_id);
if (!$supplier) { echo "Supplier {$pn->supplier_id} not found\n"; exit; }

$payloadItems = [];
$total = 0;
foreach ($items as $it) {
    $subtotal = (float)$it->quantity * (float)$it->unit_price;
    $total += $subtotal;
    $payloadItems[] = [
        'product_id' => (int)$it->product_id,
        'product_code' => (string)$it->product_code,
        'description' => (string)$it->description,
        'quantity' => (float)$it->quantity,
        'actual_quantity' => (float)$it->quantity,
        'unit_price' => (float)$it->unit_price,
        'unit' => (string)($it->unit ?? ''),
        'discount_percent' => 0,
        'discount_amount' => 0,
        'subtotal' => $subtotal,
        'actual_subtotal' => $subtotal,
    ];
}

$payload = [
    'po_number' => '9999997',
    'supplier_invoice_number' => '',
    'trans_no' => $pn->purchase_note_number,
    'additional_discount_percent' => 0,
    'additional_discount_amount' => 0,
    'supplier_code' => (string)$supplier->supplier_code,
    'date' => date('Y-m-d'),
    'total_amount' => $total,
    'actual_total_amount' => $total,
    'currency' => 'PHP',
    'conversion_rate' => 1,
    'remarks' => '',
    'items' => $payloadItems,
];

echo "PN: {$pn->purchase_note_number} supplier={$supplier->supplier_code} items=".count($payloadItems)." total={$total}\n";

$json = json_encode($payload);
$req = Request::create('/admin/purchase/purchase-order/process', 'POST', [], [], [], [], $json);
$req->headers->set('Content-Type', 'application/json');

$session = new \Illuminate\Session\Store('repro', new \Illuminate\Session\FileSessionHandler(new \Illuminate\Filesystem\Filesystem(), storage_path('framework/sessions'), 120));
$session->start();
$session->put('user', ['id' => 1, 'name' => 'Repro User', 'account_type' => 2, 'email' => 'repro@local']);
$req->setLaravelSession($session);

$conns = ['purchase', 'ledger', 'masterlist', 'mysql'];
foreach ($conns as $c) { try { DB::connection($c)->beginTransaction(); } catch (\Throwable $e) { echo "TX fail $c: {$e->getMessage()}\n"; } }

try {
    $response = app()->handle($req);
    echo "STATUS: ".$response->getStatusCode()."\n";
    echo "BODY: ".$response->getContent()."\n";
} catch (\Throwable $e) {
    echo "EXCEPTION: ".get_class($e).": ".$e->getMessage()."\n";
    if ($e instanceof \Illuminate\Validation\ValidationException) {
        echo "ERRORS: ".json_encode($e->errors())."\n";
    }
} finally {
    foreach (array_reverse($conns) as $c) { try { DB::connection($c)->rollBack(); } catch (\Throwable $e) {} }
    echo "ROLLED BACK\n";
}
