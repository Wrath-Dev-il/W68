<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connections = ['sales', 'ledger', 'masterlist'];

foreach ($connections as $connection) {
    DB::connection($connection)->beginTransaction();
}

function regressionAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function regressionRequest(string $method, string $uri, array $payload = []): Request
{
    global $app;

    $request = Request::create(
        $uri,
        $method,
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        $payload === [] ? null : json_encode($payload)
    );

    $session = $app->make('session')->driver();
    $session->start();
    $session->put('user', [
        'login_ID' => 0,
        'User_ID' => 'codex-admin-regression',
        'account_type' => 1,
    ]);
    $request->setLaravelSession($session);
    $app->instance('request', $request);

    return $request;
}

try {
    $products = DB::connection('masterlist')
        ->table('products')
        ->whereNotNull('product_code')
        ->orderBy('id')
        ->limit(3)
        ->get(['id', 'product_code']);

    regressionAssert($products->count() === 3, 'The fixture needs three existing products.');

    $customer = DB::connection('masterlist')->table('customers')->orderBy('id')->first(['id', 'name']);
    regressionAssert($customer !== null, 'The fixture needs an existing customer.');

    [$invoiceProduct, $remainingProduct, $newProduct] = $products->all();
    $salesNumber = 'TEST-PARTIAL-' . str_replace('.', '', uniqid('', true));
    $now = now();

    $noteId = DB::connection('sales')->table('sales_notes')->insertGetId([
        'sales_number' => $salesNumber,
        'so_type' => 'Sales Order',
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'order_date' => $now->toDateString(),
        'gross_total' => 600,
        'total_discount' => 0,
        'net_total' => 600,
        'status' => 'Partial',
        'remarks' => '',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $invoiceNoteItemId = DB::connection('sales')->table('sales_note_items')->insertGetId([
        'sales_note_id' => $noteId,
        'product_id' => $invoiceProduct->id,
        'product_code' => $invoiceProduct->product_code,
        'description' => 'Invoice item',
        'quantity' => 50,
        'additional_qty' => 0,
        'oum' => 'PCS',
        'unit_price' => 10,
        'discount' => 0,
        'bonus' => 0,
        'subtotal' => 500,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $remainingNoteItemId = DB::connection('sales')->table('sales_note_items')->insertGetId([
        'sales_note_id' => $noteId,
        'product_id' => $remainingProduct->id,
        'product_code' => $remainingProduct->product_code,
        'description' => 'Uninvoiced remaining item',
        'quantity' => 10,
        'additional_qty' => 0,
        'oum' => 'PCS',
        'unit_price' => 10,
        'discount' => 0,
        'bonus' => 0,
        'subtotal' => 100,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $salesOrderId = DB::connection('sales')->table('sales_orders')->insertGetId([
        'sales_note_id' => $noteId,
        'order_number' => $salesNumber,
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'invoice_numbers' => 'TEST-INV-001',
        'total_amount' => 250,
        'status' => 'Confirmed',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::connection('sales')->table('sales_order_items')->insert([
        'sales_order_id' => $salesOrderId,
        'product_id' => $invoiceProduct->id,
        'product_code' => $invoiceProduct->product_code,
        'description' => 'Invoice item',
        // Reproduce the historical corruption where quantity was overwritten
        // with the partial transaction's actual quantity.
        'quantity' => 25,
        'actual_qty' => 25,
        'additional_qty' => 0,
        'oum' => 'PCS',
        'unit_price' => 10,
        'discount' => 0,
        'additional_discount' => 0,
        'subtotal' => 250,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $invoiceItemsRequest = regressionRequest(
        'GET',
        "/admin/sales/sales-note/invoice/{$salesOrderId}/items"
    );
    $invoiceItemsRoute = Route::getRoutes()->getByName('admin.sales-note.invoice.items');
    $invoiceItemsResponse = ($invoiceItemsRoute->getAction()['uses'])($salesOrderId);
    $invoiceItemsData = $invoiceItemsResponse->getData(true);
    $mappedInvoiceItem = collect($invoiceItemsData['items'] ?? [])->firstWhere(
        'product_id',
        $invoiceProduct->id
    );

    regressionAssert(
        (int) ($mappedInvoiceItem['sales_note_item_id'] ?? 0) === $invoiceNoteItemId,
        'Invoice item loading did not map the Sales Order item to its Sales Note item ID.'
    );
    regressionAssert(
        (int) ($mappedInvoiceItem['quantity'] ?? 0) === 50,
        'Partial Invoice Step 3 mapped QTY from sales_order_items.actual_qty instead of the original Sales Note quantity.'
    );
    regressionAssert(
        (int) ($mappedInvoiceItem['actual_qty'] ?? 0) === 25,
        'Partial Invoice Step 3 did not keep actual_qty separate.'
    );
    regressionAssert(
        (int) ($mappedInvoiceItem['remaining_qty'] ?? -1) === 25,
        'Partial Invoice Step 3 did not calculate remaining_qty = qty - actual_qty.'
    );

    $payload = [
        'sales_order_id' => $salesOrderId,
        'so_type' => 'Sales Order',
        'order_date' => $now->toDateString(),
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'salesman' => null,
        'prepared_by' => null,
        'checked_by' => null,
        'packed_by' => null,
        'is_rush' => false,
        'gross_total' => 550,
        'total_discount' => 0,
        'net_total' => 550,
        'remarks' => '',
        'ignore_stock' => true,
        'items' => [
            [
                'sales_note_item_id' => $mappedInvoiceItem['sales_note_item_id'],
                'product_id' => $invoiceProduct->id,
                'product_code' => $invoiceProduct->product_code,
                'description' => 'Invoice item',
                'quantity' => 50,
                'additional_qty' => 0,
                'oum' => 'PCS',
                'unit_price' => 10,
                'discount' => 0,
                'subtotal' => 500,
                '_changed' => false,
            ],
            [
                'sales_note_item_id' => null,
                'product_id' => $newProduct->id,
                'product_code' => $newProduct->product_code,
                'description' => 'New partial invoice item',
                'quantity' => 5,
                'additional_qty' => 0,
                'oum' => 'PCS',
                'unit_price' => 10,
                'discount' => 0,
                'subtotal' => 50,
                '_changed' => true,
            ],
        ],
    ];

    $updateRequest = regressionRequest(
        'POST',
        "/admin/sales/sales-note/update/{$noteId}",
        $payload
    );
    $updateRoute = Route::getRoutes()->getByName('admin.sales-note.update');
    $updateResponse = ($updateRoute->getAction()['uses'])($updateRequest, $noteId);
    $updateData = $updateResponse->getData(true);

    regressionAssert(
        ($updateData['success'] ?? false) === true,
        'Sales Note update route did not succeed: ' . json_encode($updateData)
    );
    regressionAssert(
        DB::connection('sales')->table('sales_note_items')->where('id', $remainingNoteItemId)->exists(),
        'Editing one partial invoice deleted an unrelated remaining Sales Note item.'
    );
    regressionAssert(
        DB::connection('sales')->table('sales_order_items')
            ->where('sales_order_id', $salesOrderId)
            ->where('product_id', $newProduct->id)
            ->count() === 1,
        'The newly added Partial / Invoice item was not synchronized exactly once to sales_order_items.'
    );
    regressionAssert(
        (int) DB::connection('sales')->table('sales_order_items')
            ->where('sales_order_id', $salesOrderId)
            ->where('product_id', $invoiceProduct->id)
            ->value('actual_qty') === 25,
        'The existing actual_qty value changed during Sales Note invoice synchronization.'
    );
    regressionAssert(
        DB::connection('sales')->table('sales_notes')->where('id', $noteId)->value('status') === 'Partial',
        'The partial Sales Note status changed unexpectedly.'
    );

    $detailRequest = regressionRequest(
        'GET',
        "/admin/sales/sales-order/edit-detail/{$salesOrderId}"
    );
    $detailRoute = Route::getRoutes()->getByName('admin.sales-order.edit-detail');
    $detailResponse = ($detailRoute->getAction()['uses'])($salesOrderId);
    $detailData = $detailResponse->getData(true);
    $detailCodes = collect($detailData['sales_order']['items'] ?? [])->pluck('product_code');

    regressionAssert(
        $detailCodes->contains($newProduct->product_code),
        'Edit Sales Order detail response does not include the newly added item.'
    );

    $blade = file_get_contents(resource_path('views/Admin/sales/sales-order.blade.php'));
    $salesNoteBlade = file_get_contents(resource_path('views/Admin/sales/sales-note.blade.php'));
    $css = file_get_contents(public_path('css/Sales-Order.css'));
    $salesOrderJs = file_get_contents(public_path('js/Sales-Order.js'));

    regressionAssert(
        str_contains($salesNoteBlade, 'item-actual-qty')
            && str_contains($salesNoteBlade, 'item-remaining-qty'),
        'Edit Sales Note Step 3 does not render qty, actual_qty, and remaining_qty as separate fields.'
    );
    regressionAssert(
        str_contains($salesOrderJs, 'qtyInput.value = actualQty'),
        'Frontend processing should overwrite qty when actual_qty is greater than the original qty.'
    );
    regressionAssert(
        str_contains($salesOrderJs, 'quantity: actualQty'),
        'Submitted/print payload should use actualQty as quantity when actual_qty is greater.'
    );

    $invalidUpdateRequest = regressionRequest(
        'POST',
        "/admin/sales/sales-order/update/{$salesOrderId}",
        [
            'invoices' => ['TEST-INV-001'],
            'items' => [[
                'sales_order_item_id' => DB::connection('sales')
                    ->table('sales_order_items')
                    ->where('sales_order_id', $salesOrderId)
                    ->where('product_id', $invoiceProduct->id)
                    ->value('id'),
                'product_id' => $invoiceProduct->id,
                'product_code' => $invoiceProduct->product_code,
                'description' => 'Invoice item',
                'quantity' => 1,
                'actual_qty' => 12,
                'additional_qty' => 0,
                'oum' => 'PCS',
                'unit_price' => 10,
                'discount' => 0,
                'additional_discount' => 0,
                'subtotal' => 120,
                'particulars' => '',
            ]],
        ]
    );
    $salesOrderUpdateRoute = Route::getRoutes()->getByName('admin.sales-order.update');
    $invalidUpdateResponse = ($salesOrderUpdateRoute->getAction()['uses'])(
        $invalidUpdateRequest,
        $salesOrderId
    );
    $invalidUpdateData = $invalidUpdateResponse->getData(true);

    regressionAssert(
        ($invalidUpdateData['success'] ?? true) === false,
        'Edit Sales Order accepted actual_qty greater than qty instead of returning validation.'
    );
    regressionAssert(
        str_contains(strtolower((string) ($invalidUpdateData['message'] ?? '')), 'actual'),
        'Edit Sales Order validation does not explain the actual_qty problem.'
    );

    $printItems = [[
        'product_code' => $invoiceProduct->product_code,
        'description' => 'Invoice item',
        'quantity' => 50,
        'actual_qty' => 25,
        'remaining_qty' => 25,
        'additional_qty' => 0,
        'oum' => 'PCS',
        'unit_price' => 10,
        'discount' => 0,
        'subtotal' => 250,
    ]];
    $receiptPrintRoute = Route::getRoutes()->getByName('admin.sales-order.receipt-print');

    $orderPrintRequest = regressionRequest('POST', '/admin/sales/sales-order/receipt-print', [
        'print_type' => 'order',
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'date' => $now->toDateString(),
        'sales_number' => $salesNumber,
        'items' => json_encode($printItems),
        'gross_total' => 500,
        'net_total' => 500,
    ]);
    $orderPrintView = ($receiptPrintRoute->getAction()['uses'])($orderPrintRequest);
    $orderPrintHtml = $orderPrintView->render();

    regressionAssert(
        preg_match('/<td[^>]*class="c"[^>]*>\s*50\s*<\/td>/', $orderPrintHtml) === 1,
        'Sales Order print does not display the order quantity for order print.'
    );
    regressionAssert(
        str_contains($orderPrintHtml, 'TOTAL (QTY 50)'),
        'Sales Order print total quantity does not use the original/order qty.'
    );

    $invoicePrintRequest = regressionRequest('POST', '/admin/sales/sales-order/receipt-print', [
        'print_type' => 'invoice',
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'date' => $now->toDateString(),
        'sales_number' => $salesNumber,
        'items' => json_encode($printItems),
        'gross_total' => 250,
        'net_total' => 250,
    ]);
    $invoicePrintView = ($receiptPrintRoute->getAction()['uses'])($invoicePrintRequest);
    $invoicePrintHtml = $invoicePrintView->render();

    regressionAssert(
        preg_match('/<td[^>]*class="c"[^>]*>\s*25\s*<\/td>/', $invoicePrintHtml) === 1,
        'Sales Invoice print does not display actual_qty as its served quantity.'
    );
    regressionAssert(
        str_contains($invoicePrintHtml, 'TOTAL (QTY 25)'),
        'Sales Invoice print total quantity does not use actual_qty.'
    );

    regressionAssert(
        str_contains($blade, 'id="proceed-items-scroll"'),
        'Step 2 has no dedicated item-table viewport.'
    );
    regressionAssert(
        preg_match('/#proceed-items-scroll\s*\{[^}]*height:\s*clamp\(/s', $css) === 1,
        'Step 2 item-table viewport does not reserve a responsive five-row height.'
    );
    regressionAssert(
        preg_match('/#proceed-items-scroll\s*\{[^}]*overflow-y:\s*auto/s', $css) === 1,
        'Step 2 item-table viewport is not independently scrollable.'
    );

    echo "PASS: qty/actual_qty/remaining_qty mapping, edit validation, print, and prior regressions.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "FAIL: {$e->getMessage()}\n");
    $exitCode = 1;
} finally {
    foreach (array_reverse($connections) as $connection) {
        if (DB::connection($connection)->transactionLevel() > 0) {
            DB::connection($connection)->rollBack();
        }
    }
}

exit($exitCode ?? 0);
