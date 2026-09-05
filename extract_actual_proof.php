<?php
/**
 * EXTRACT ACTUAL RUNTIME PROOF FROM LOGS AND DATABASE
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=================================================================\n";
echo "ACTUAL RUNTIME PROOF - EXTRACTED FROM LOGS AND DATABASE\n";
echo "=================================================================\n\n";

// Read Laravel log file
$logPath = storage_path('logs/laravel.log');
if (!file_exists($logPath)) {
    die("Log file not found at: {$logPath}\n");
}

echo "Reading log file: {$logPath}\n\n";

$logContent = file_get_contents($logPath);

// Extract the most recent sudden returns API request
preg_match_all('/=== SUDDEN RETURNS API REQUEST ===(.*?)=== SUDDEN RETURNS API RESPONSE END ===/s', $logContent, $apiMatches);

if (!empty($apiMatches[0])) {
    $latestApiLog = end($apiMatches[0]);
    echo "=================================================================\n";
    echo "1. SUDDEN RETURNS API REQUEST (ACTUAL)\n";
    echo "=================================================================\n";
    
    // Extract URL
    if (preg_match('/Request URL: (.+)/', $latestApiLog, $urlMatch)) {
        echo "Request URL: {$urlMatch[1]}\n";
    }
    
    // Extract PO IDs
    if (preg_match('/PO IDs from request: (.+)/', $latestApiLog, $poMatch)) {
        echo "PO IDs Parameter: {$poMatch[1]}\n";
    }
    
    // Extract response
    if (preg_match('/API Response Data: (.+)/', $latestApiLog, $responseMatch)) {
        echo "\nAPI Response:\n";
        $responseData = json_decode($responseMatch[1], true);
        if ($responseData) {
            echo json_encode($responseData, JSON_PRETTY_PRINT) . "\n";
        } else {
            echo $responseMatch[1] . "\n";
        }
    }
    
    // Extract returned PO IDs
    if (preg_match('/API Response - Returned PO IDs: (.+)/', $latestApiLog, $returnedPoMatch)) {
        echo "\nReturned PO IDs: {$returnedPoMatch[1]}\n";
    }
    
    echo "\n";
}

// Extract the most recent process request
preg_match_all('/=== PROCESS VOUCHER REQUEST ===(.*?)=== SUDDEN RETURNS DEBUG END ===/s', $logContent, $processMatches);

if (!empty($processMatches[0])) {
    $latestProcessLog = end($processMatches[0]);
    echo "=================================================================\n";
    echo "2. PROCESS VOUCHER PAYLOAD (ACTUAL)\n";
    echo "=================================================================\n";
    
    // Extract invoice PO IDs
    if (preg_match('/Selected Invoice PO IDs: (.+)/', $latestProcessLog, $invoicePoMatch)) {
        echo "Selected Invoice PO IDs: {$invoicePoMatch[1]}\n";
    }
    
    // Extract sudden returns
    if (preg_match('/Sudden Returns in Payload: (.+)/', $latestProcessLog, $srPayloadMatch)) {
        echo "Sudden Returns in Payload: {$srPayloadMatch[1]}\n";
    }
    
    echo "\n=================================================================\n";
    echo "3. INVOICE ID MAP (ACTUAL)\n";
    echo "=================================================================\n";
    
    // Extract invoice ID map
    if (preg_match('/Invoice ID Map:.*?"map":\s*({[^}]+})/', $latestProcessLog, $mapMatch)) {
        echo "invoiceIdMap: {$mapMatch[1]}\n";
    }
    
    echo "\n=================================================================\n";
    echo "4. INSERT OPERATIONS (ACTUAL)\n";
    echo "=================================================================\n";
    
    // Extract all insert operations
    preg_match_all('/Processing sudden return:.*?"sr":\s*({[^}]+}).*?Found invoice ID:.*?"invoice_id":([^,}]+).*?EXACT INSERT DATA: ({.+?})\n.*?(✅|⚠️|❌)/s', $latestProcessLog, $insertMatches, PREG_SET_ORDER);
    
    if (!empty($insertMatches)) {
        foreach ($insertMatches as $i => $match) {
            echo "\nOperation " . ($i + 1) . ":\n";
            echo "  Sudden Return: {$match[1]}\n";
            echo "  Invoice ID from Map: {$match[2]}\n";
            echo "  Insert Data: " . json_encode(json_decode($match[3]), JSON_PRETTY_PRINT) . "\n";
            echo "  Status: {$match[4]}\n";
        }
    }
    
    echo "\n";
}

// Query the actual database
echo "=================================================================\n";
echo "5. DATABASE QUERY RESULTS (ACTUAL)\n";
echo "=================================================================\n";
echo "Query: SELECT id, payable_cheque_voucher_id, payable_cheque_voucher_invoice_id, purchase_order_id, return_number, return_amount FROM payable_cheque_voucher_sudden_returns ORDER BY id DESC LIMIT 5;\n\n";

$rows = DB::connection('accounting')
    ->table('payable_cheque_voucher_sudden_returns')
    ->select('id', 'payable_cheque_voucher_id', 'payable_cheque_voucher_invoice_id', 'purchase_order_id', 'return_number', 'return_amount')
    ->orderBy('id', 'desc')
    ->limit(5)
    ->get();

if ($rows->isEmpty()) {
    echo "Result: 0 rows\n";
    echo "Table is EMPTY - no sudden returns saved\n";
} else {
    echo "Result: " . count($rows) . " row(s)\n\n";
    
    // Print header
    echo str_pad('id', 5) . '| ' . 
         str_pad('voucher_id', 12) . '| ' . 
         str_pad('invoice_id', 12) . '| ' . 
         str_pad('po_id', 8) . '| ' . 
         str_pad('return_number', 20) . '| ' . 
         str_pad('return_amount', 15) . "\n";
    echo str_repeat('-', 80) . "\n";
    
    // Print rows
    foreach ($rows as $row) {
        echo str_pad($row->id, 5) . '| ' . 
             str_pad($row->payable_cheque_voucher_id, 12) . '| ' . 
             str_pad($row->payable_cheque_voucher_invoice_id, 12) . '| ' . 
             str_pad($row->purchase_order_id, 8) . '| ' . 
             str_pad($row->return_number, 20) . '| ' . 
             str_pad(number_format($row->return_amount, 2), 15) . "\n";
    }
}

echo "\n=================================================================\n";
echo "END OF ACTUAL RUNTIME PROOF\n";
echo "=================================================================\n";
