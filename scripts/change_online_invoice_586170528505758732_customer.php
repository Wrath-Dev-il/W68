<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

$invoice = '586170528505758732';
$expectedOldCustomer = 'SHOPEE ONLINE BUYERS';
$targetCustomerName = 'TIKTOK SHOP';
$now = now('Asia/Manila');

$sales = DB::connection('sales');
$master = DB::connection('masterlist');
$ledger = DB::connection('ledger');

$normalize = static fn ($value) => mb_strtolower(trim((string) $value));

$targetCustomers = $master->table('customers')
    ->whereRaw('LOWER(TRIM(name)) = ?', [$normalize($targetCustomerName)])
    ->get(['id', 'name']);

if ($targetCustomers->count() !== 1) {
    echo "ERROR: Expected exactly one Customer Master row named {$targetCustomerName}; found {$targetCustomers->count()}.\n";
    $candidates = $master->table('customers')
        ->whereRaw('LOWER(name) LIKE ?', ['%tiktok%'])
        ->get(['id', 'name']);
    foreach ($candidates as $candidate) {
        echo "CANDIDATE: ID {$candidate->id} | {$candidate->name}\n";
    }
    exit(1);
}

$targetCustomer = $targetCustomers->first();

$reportCandidates = $sales->table('online_reports')
    ->where('invoice_numbers', 'like', '%' . $invoice . '%')
    ->get();

$matches = [];
foreach ($reportCandidates as $report) {
    $invoiceNumbers = json_decode((string) ($report->invoice_numbers ?? '[]'), true);
    if (!is_array($invoiceNumbers)) {
        $invoiceNumbers = array_map('trim', explode(',', (string) ($report->invoice_numbers ?? '')));
    }

    foreach (array_values($invoiceNumbers) as $index => $number) {
        if (trim((string) $number) === $invoice) {
            $matches[] = ['report' => $report, 'index' => $index];
        }
    }
}

if (count($matches) !== 1) {
    echo "ERROR: Expected exactly one exact Online Report match for invoice {$invoice}; found " . count($matches) . ".\n";
    exit(1);
}

$report = $matches[0]['report'];
$index = (int) $matches[0]['index'];

$noteIds = array_values(array_filter(array_map(
    static fn ($value) => (int) trim((string) $value),
    explode(',', (string) ($report->sales_note_ids ?? ''))
), static fn ($value) => $value > 0));

$noteId = (int) ($noteIds[$index] ?? 0);
if ($noteId <= 0) {
    echo "ERROR: No Sales Note ID exists at report index {$index}.\n";
    exit(1);
}

$salesNote = $sales->table('sales_notes')->where('id', $noteId)->first();
if (!$salesNote) {
    echo "ERROR: Sales Note #{$noteId} was not found.\n";
    exit(1);
}

$notesData = json_decode((string) ($report->notes_data ?? '[]'), true);
if (!is_array($notesData)) {
    echo "ERROR: Online Report notes_data is invalid JSON.\n";
    exit(1);
}

$noteData = is_array($notesData[$index] ?? null) ? $notesData[$index] : [];
$currentCustomerName = trim((string) ($noteData['customer_name'] ?? $salesNote->customer_name ?? ''));

if (
    $normalize($currentCustomerName) !== $normalize($expectedOldCustomer)
    && $normalize($currentCustomerName) !== $normalize($targetCustomer->name)
) {
    echo "ERROR: Invoice currently belongs to [{$currentCustomerName}], not [{$expectedOldCustomer}]. No changes made.\n";
    exit(1);
}

$orderNumber = 'ONL-' . (int) $report->id . '-' . $index;
$salesOrders = $sales->table('sales_orders')
    ->where('order_number', $orderNumber)
    ->get();

$backupDir = storage_path('app/backups');
File::ensureDirectoryExists($backupDir);
$backupFile = $backupDir . '/online-invoice-customer-' . $invoice . '-' . $now->format('Ymd_His') . '.json';

$currentLedgerRows = $ledger->table('product_ledgers')
    ->where('source_type', 'online_report')
    ->where('source_id', (int) $report->id)
    ->where('reference_number', $invoice)
    ->get();

$legacyLedgerRows = $ledger->table('product_ledgers')
    ->where('transaction_number', (string) ($salesNote->sales_number ?? ''))
    ->where('reference_number', $invoice)
    ->where('remarks', 'like', 'Online Report Generation%')
    ->get();

File::put($backupFile, json_encode([
    'invoice' => $invoice,
    'created_at' => $now->toDateTimeString(),
    'target_customer' => (array) $targetCustomer,
    'online_report_before' => (array) $report,
    'sales_note_before' => (array) $salesNote,
    'sales_orders_before' => $salesOrders->map(fn ($row) => (array) $row)->all(),
    'product_ledgers_before' => $currentLedgerRows
        ->concat($legacyLedgerRows)
        ->unique('id')
        ->map(fn ($row) => (array) $row)
        ->values()
        ->all(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

$notesData[$index] = array_merge($noteData, [
    'customer_id' => (int) $targetCustomer->id,
    'customer_name' => (string) $targetCustomer->name,
]);

$sales->transaction(function () use (
    $sales,
    $report,
    $index,
    $notesData,
    $noteId,
    $orderNumber,
    $invoice,
    $targetCustomer,
    $now
) {
    $sales->table('online_reports')->where('id', $report->id)->update([
        'notes_data' => json_encode($notesData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'updated_at' => $now,
    ]);

    $sales->table('sales_notes')->where('id', $noteId)->update([
        'customer_id' => (int) $targetCustomer->id,
        'customer_name' => (string) $targetCustomer->name,
        'updated_at' => $now,
    ]);

    $sales->table('sales_orders')
        ->where('order_number', $orderNumber)
        ->where('invoice_numbers', $invoice)
        ->update([
            'customer_id' => (int) $targetCustomer->id,
            'customer_name' => (string) $targetCustomer->name,
            'updated_at' => $now,
        ]);
});

$ledgerIds = $currentLedgerRows
    ->concat($legacyLedgerRows)
    ->pluck('id')
    ->map(fn ($id) => (int) $id)
    ->filter()
    ->unique()
    ->values()
    ->all();

$ledgerUpdated = 0;
if ($ledgerIds !== []) {
    $ledgerUpdated = $ledger->table('product_ledgers')
        ->whereIn('id', $ledgerIds)
        ->update([
            'customer_id' => (int) $targetCustomer->id,
            'entity_name' => (string) $targetCustomer->name,
            'updated_at' => $now,
        ]);
}

$verifyReport = $sales->table('online_reports')->where('id', $report->id)->first();
$verifyNotes = json_decode((string) ($verifyReport->notes_data ?? '[]'), true) ?: [];
$verifyNoteData = $verifyNotes[$index] ?? [];
$verifySalesNote = $sales->table('sales_notes')->where('id', $noteId)->first(['customer_id', 'customer_name']);
$verifyOrder = $sales->table('sales_orders')
    ->where('order_number', $orderNumber)
    ->where('invoice_numbers', $invoice)
    ->first(['customer_id', 'customer_name']);

echo "=== ONLINE INVOICE CUSTOMER CHANGE COMPLETE ===\n";
echo "Invoice: {$invoice}\n";
echo "Online Report: #{$report->id} | index {$index}\n";
echo "Sales Note: #{$noteId} | " . ($salesNote->sales_number ?? '') . "\n";
echo "Old customer: {$currentCustomerName}\n";
echo "New customer: {$targetCustomer->name} | ID {$targetCustomer->id}\n";
echo "Snapshot: " . ($verifyNoteData['customer_name'] ?? 'MISSING') . "\n";
echo "Sales Note: " . ($verifySalesNote->customer_name ?? 'MISSING') . "\n";
echo "Sales Order: " . ($verifyOrder->customer_name ?? 'MISSING') . "\n";
echo "Product Ledger rows updated: {$ledgerUpdated}\n";
echo "Backup: {$backupFile}\n";
