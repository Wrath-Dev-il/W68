<?php

use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$basePath = is_dir('/app/vendor') ? '/app' : dirname(__DIR__);
chdir($basePath);

require $basePath . '/vendor/autoload.php';

$app = require $basePath . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$apply = in_array('--apply', $argv ?? [], true);
$fromDate = '2015-01-01';
$toDate = '2026-07-25';
$paymentDate = '2026-07-25';
$historicalTimestamp = '2026-07-25 12:00:00';
$batch = 'W68-HISTORICAL-PAID-20150101-20260725';

echo "=== W68 HISTORICAL INVOICE SETTLEMENT ===\n";
echo "INVOICE_RANGE={$fromDate}..{$toDate}\n";
echo "PAYMENT_DATE={$paymentDate}\n";
echo 'MODE=' . ($apply ? 'APPLY' : 'PREVIEW') . "\n";

foreach ([
    ['sales', 'sales_orders'],
    ['sales', 'sales_returns'],
    ['sales', 'sales_return_items'],
    ['accounting', 'process_payments'],
    ['accounting', 'process_payment_invoices'],
] as [$connection, $table]) {
    DB::connection($connection)->getPdo();

    if (!Schema::connection($connection)->hasTable($table)) {
        throw new RuntimeException("Required table missing: {$connection}.{$table}");
    }
}

DB::connection('masterlist')->getPdo();

if (!function_exists('hatdogBuildPaymentOnlineInvoices')) {
    require base_path('routes/web.php');
}

if (!function_exists('hatdogBuildPaymentOnlineInvoices')) {
    throw new RuntimeException('Payments online invoice resolver is unavailable.');
}

$normalizeInvoice = static fn ($value): string =>
    strtoupper(preg_replace('/\s+/', '', trim((string) $value)) ?? '');

$buildCandidates = static function () use ($fromDate, $toDate, $normalizeInvoice): array {
    $paidRows = DB::connection('accounting')
        ->table('process_payment_invoices')
        ->select(
            'source_type',
            'source_id',
            DB::raw('SUM(paid_amount) as paid_total'),
            DB::raw('SUM(adjustment) as adjustment_total')
        )
        ->groupBy('source_type', 'source_id')
        ->get()
        ->keyBy(fn ($row) => (string) $row->source_type . ':' . (int) $row->source_id);

    $onlinePaidRows = DB::connection('accounting')
        ->table('process_payment_invoices')
        ->where('source_type', 'online_report')
        ->select(
            'source_type',
            'source_id',
            'invoice_no',
            DB::raw('SUM(paid_amount) as paid_total'),
            DB::raw('SUM(adjustment) as adjustment_total')
        )
        ->groupBy('source_type', 'source_id', 'invoice_no')
        ->get()
        ->keyBy(fn ($row) =>
            (string) $row->source_type . ':' . (int) $row->source_id . ':' . (string) $row->invoice_no
        );

    $rawReturnRows = DB::connection('sales')
        ->table('sales_returns as sr')
        ->join('sales_return_items as sri', 'sri.sales_return_id', '=', 'sr.id')
        ->whereNotNull('sr.invoice_no')
        ->whereRaw("UPPER(TRIM(COALESCE(sr.status, ''))) NOT IN ('CANCELLED','CANCELED','VOID')")
        ->select(
            'sr.invoice_no',
            'sr.status',
            DB::raw('SUM(COALESCE(sri.return_amount, sri.subtotal, 0)) as return_total')
        )
        ->groupBy('sr.invoice_no', 'sr.status')
        ->get();

    $returnMap = [];
    $onlineReturnMap = [];

    foreach ($rawReturnRows as $row) {
        $rawInvoice = trim((string) ($row->invoice_no ?? ''));
        $total = (float) ($row->return_total ?? 0);

        if ($rawInvoice === '' || $total <= 0) {
            continue;
        }

        $keys = [$normalizeInvoice($rawInvoice)];

        foreach (preg_split('/[,\s\/]+/', $rawInvoice) ?: [] as $token) {
            $token = trim((string) $token);
            if ($token !== '') {
                $keys[] = $normalizeInvoice($token);
            }
        }

        if (preg_match_all('/SN-\d+/i', $rawInvoice, $matches) && !empty($matches[0])) {
            foreach ($matches[0] as $sn) {
                $keys[] = $normalizeInvoice($sn);
            }
        }

        $keys = array_values(array_unique(array_filter($keys)));

        foreach ($keys as $key) {
            $returnMap[$key] = (float) ($returnMap[$key] ?? 0) + $total;

            if (strtoupper(trim((string) ($row->status ?? ''))) === 'COMPLETED') {
                $onlineReturnMap[$key] = (float) ($onlineReturnMap[$key] ?? 0) + $total;
            }
        }
    }

    $salesOrders = DB::connection('sales')
        ->table('sales_orders as so')
        ->leftJoin('sales_notes as sn', 'sn.id', '=', 'so.sales_note_id')
        ->where('so.order_number', 'NOT LIKE', 'ONL-%')
        ->where(function ($query) {
            $query->whereIn('so.status', ['Closed', 'Confirmed'])
                ->orWhere('sn.status', 'Closed');
        })
        ->select(
            DB::raw("'sales_order' as source_type"),
            'so.id as source_id',
            'so.order_number as po_no',
            'sn.sales_number as sales_note_no',
            'sn.net_total as sales_note_amount',
            'so.customer_id',
            'so.customer_name',
            DB::raw("COALESCE(NULLIF(so.invoice_numbers, ''), so.order_number) as invoice_no"),
            'so.total_amount as amount',
            'so.status',
            'so.created_at',
            'so.updated_at',
            'so.waybill_date'
        )
        ->get();

    $consignmentInvoices = Schema::connection('sales')->hasTable('consignment_invoices')
        ? DB::connection('sales')
            ->table('consignment_invoices')
            ->whereRaw("UPPER(TRIM(COALESCE(status, ''))) NOT IN ('CANCELLED','CANCELED','VOID')")
            ->select(
                DB::raw("'consignment_invoice' as source_type"),
                'id as source_id',
                DB::raw('invoice_number as po_no'),
                DB::raw('NULL as sales_note_no'),
                DB::raw('NULL as sales_note_amount'),
                'customer_id',
                'customer_name',
                'invoice_number as invoice_no',
                'total_amount as amount',
                'status',
                'created_at',
                'updated_at',
                DB::raw('NULL as waybill_date')
            )
            ->get()
        : collect();

    $onlineInvoices = collect(hatdogBuildPaymentOnlineInvoices(null, ''))
        ->map(function ($row) {
            $row = (object) $row;

            return (object) [
                'source_type' => 'online_report',
                'source_id' => (int) ($row->source_id ?? 0),
                'po_no' => (string) ($row->po_no ?? $row->invoice_no ?? ''),
                'sales_note_no' => (string) ($row->sales_note_no ?? ''),
                'sales_note_amount' => $row->sales_note_amount ?? null,
                'customer_id' => (int) ($row->customer_id ?? 0),
                'customer_name' => (string) ($row->customer_name ?? ''),
                'invoice_no' => (string) ($row->invoice_no ?? ''),
                'amount' => (float) ($row->amount ?? 0),
                'status' => (string) ($row->status ?? ''),
                'created_at' => $row->created_at ?? null,
                'updated_at' => $row->updated_at ?? null,
                'waybill_date' => $row->waybill_date ?? null,
            ];
        });

    $documents = $salesOrders->concat($consignmentInvoices)->concat($onlineInvoices);
    $candidates = [];

    foreach ($documents as $doc) {
        if (empty($doc->created_at)) {
            continue;
        }

        try {
            $invoiceDate = Carbon::parse($doc->created_at)->format('Y-m-d');
        } catch (Throwable) {
            continue;
        }

        if ($invoiceDate < $fromDate || $invoiceDate > $toDate) {
            continue;
        }

        $sourceType = (string) ($doc->source_type ?? '');
        $sourceId = (int) ($doc->source_id ?? 0);
        $invoiceNo = trim((string) ($doc->invoice_no ?? ''));

        if ($sourceType === '' || $sourceId <= 0 || $invoiceNo === '') {
            continue;
        }

        $isOnline = $sourceType === 'online_report';
        $paidKey = $isOnline
            ? $sourceType . ':' . $sourceId . ':' . $invoiceNo
            : $sourceType . ':' . $sourceId;

        $settlement = $isOnline ? $onlinePaidRows->get($paidKey) : $paidRows->get($paidKey);
        $paid = (float) ($settlement->paid_total ?? 0);
        $recordedAdjustment = (float) ($settlement->adjustment_total ?? 0);
        $amount = max((float) ($doc->amount ?? 0), 0);

        $invoiceKeys = collect(explode(',', (string) ($doc->invoice_no ?? '')))
            ->map(fn ($value) => $normalizeInvoice($value))
            ->filter()
            ->merge([
                $normalizeInvoice($doc->po_no ?? ''),
                $normalizeInvoice($doc->sales_note_no ?? ''),
            ])
            ->filter()
            ->unique()
            ->values();

        $activeReturnMap = $isOnline ? $onlineReturnMap : $returnMap;
        $returned = (float) $invoiceKeys->sum(
            fn ($invoiceKey) => (float) ($activeReturnMap[$invoiceKey] ?? 0)
        );

        $salesNoteAmount = (float) ($doc->sales_note_amount ?? 0);
        $effectiveAdjustment = max($returned, $recordedAdjustment);
        $autoSettledByReturn = $returned > 0 && (
            $returned >= $amount
            || ($salesNoteAmount > 0 && $returned >= $salesNoteAmount)
            || $paid >= ($amount / 2)
        );

        $due = $isOnline
            ? max($amount - $paid - $returned, 0)
            : ($autoSettledByReturn ? 0 : max($amount - $paid - $effectiveAdjustment, 0));

        $due = round($due, 2);

        if ($due <= 0.005) {
            continue;
        }

        $candidateKey = $sourceType . ':' . $sourceId
            . ($isOnline ? ':' . $normalizeInvoice($invoiceNo) : '');

        $candidates[$candidateKey] = [
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'customer_id' => (int) ($doc->customer_id ?? 0),
            'customer_name' => trim((string) ($doc->customer_name ?? '')) ?: 'Unknown Customer',
            'invoice_no' => $invoiceNo,
            'invoice_date' => $invoiceDate,
            'invoice_amount' => round($amount, 2),
            'remaining_due' => $due,
        ];
    }

    return array_values($candidates);
};

$candidates = $buildCandidates();
$totalDue = round((float) collect($candidates)->sum('remaining_due'), 2);

echo 'ELIGIBLE_UNPAID_INVOICES=' . count($candidates) . "\n";
echo 'TOTAL_TO_SETTLE=' . number_format($totalDue, 2, '.', '') . "\n";

$backupDirectory = storage_path('app/backups');
if (!is_dir($backupDirectory)) {
    mkdir($backupDirectory, 0775, true);
}

$backupFile = $backupDirectory . '/' . strtolower($batch) . '.json';

$manifest = [
    'batch' => $batch,
    'invoice_date_from' => $fromDate,
    'invoice_date_to' => $toDate,
    'payment_date' => $paymentDate,
    'candidate_count' => count($candidates),
    'candidate_total_due' => $totalDue,
    'candidates' => $candidates,
    'inserted_payment_ids' => [],
    'inserted_payment_invoice_ids' => [],
    'linked_group_item_ids' => [],
];

file_put_contents(
    $backupFile,
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
);

echo "BACKUP_MANIFEST={$backupFile}\n";

if (!$apply) {
    echo "PREVIEW_COMPLETE=YES\n";
    echo "Run again with --apply to create the settlement records.\n";
    exit(0);
}

if (
    DB::connection('accounting')
        ->table('process_payments')
        ->where('remarks', 'like', '%' . $batch . '%')
        ->exists()
) {
    echo "BATCH_ALREADY_APPLIED=YES\n";
    echo "No duplicate payments were created.\n";
    exit(0);
}

if (!$candidates) {
    echo "NOTHING_TO_SETTLE=YES\n";
    exit(0);
}

$insertedPaymentIds = [];
$insertedInvoiceIds = [];
$linkedGroupItemIds = [];
$affectedGroupIds = [];

DB::connection('accounting')->transaction(function () use (
    $candidates,
    $batch,
    $paymentDate,
    $historicalTimestamp,
    &$insertedPaymentIds,
    &$insertedInvoiceIds,
    &$linkedGroupItemIds,
    &$affectedGroupIds
) {
    $maxRow = DB::connection('accounting')
        ->table('process_payments')
        ->where('payment_no', 'like', 'PAY-%')
        ->selectRaw('MAX(CAST(SUBSTRING(payment_no, 5) AS UNSIGNED)) as max_num')
        ->first();

    $nextPaymentNumber = ((int) ($maxRow->max_num ?? 0)) + 1;
    $accountingSchema = Schema::connection('accounting');
    $groupLinksAvailable = $accountingSchema->hasTable('payment_invoice_group_items')
        && $accountingSchema->hasColumn('payment_invoice_group_items', 'process_payment_invoice_id');

    $customerGroups = collect($candidates)->groupBy(function ($invoice) {
        return (int) $invoice['customer_id'] . '|'
            . mb_strtoupper(trim((string) $invoice['customer_name']));
    });

    foreach ($customerGroups as $rows) {
        $rows = collect($rows)->values();
        $first = $rows->first();
        $customerId = (int) ($first['customer_id'] ?? 0);
        $customerName = trim((string) ($first['customer_name'] ?? 'Unknown Customer'));

        do {
            $paymentNo = 'PAY-' . str_pad((string) $nextPaymentNumber, 7, '0', STR_PAD_LEFT);
            $nextPaymentNumber++;
        } while (
            DB::connection('accounting')->table('process_payments')->where('payment_no', $paymentNo)->exists()
        );

        $totalPaid = round((float) $rows->sum('remaining_due'), 2);

        $paymentInsert = [
            'payment_no' => $paymentNo,
            'customer_id' => $customerId,
            'customer_name' => $customerName !== '' ? $customerName : 'Unknown Customer',
            'payment_date' => $paymentDate,
            'total_paid' => $totalPaid,
            'status' => 'Posted',
            'remarks' => $batch . ' | Historical invoices settled through 2026-07-25',
            'created_at' => $historicalTimestamp,
            'updated_at' => $historicalTimestamp,
        ];

        foreach ([
            'payment_invoice_group_id' => null,
            'online_percent' => 0,
            'online_payment' => 0,
            'retotal_amount' => round((float) $rows->sum('invoice_amount'), 2),
        ] as $column => $value) {
            if ($accountingSchema->hasColumn('process_payments', $column)) {
                $paymentInsert[$column] = $value;
            }
        }

        $paymentId = DB::connection('accounting')
            ->table('process_payments')
            ->insertGetId($paymentInsert);

        $insertedPaymentIds[] = (int) $paymentId;

        foreach ($rows as $invoice) {
            $paymentInvoiceId = (int) DB::connection('accounting')
                ->table('process_payment_invoices')
                ->insertGetId([
                    'process_payment_id' => $paymentId,
                    'source_type' => $invoice['source_type'],
                    'source_id' => (int) $invoice['source_id'],
                    'invoice_no' => $invoice['invoice_no'],
                    'invoice_amount' => $invoice['invoice_amount'],
                    'due_amount' => $invoice['remaining_due'],
                    'adjustment' => 0,
                    'paid_amount' => $invoice['remaining_due'],
                    'payment_status' => 'Full',
                    'remarks' => $batch . ' | Historical balance fully settled',
                    'created_at' => $historicalTimestamp,
                    'updated_at' => $historicalTimestamp,
                ]);

            $insertedInvoiceIds[] = $paymentInvoiceId;

            if (!$groupLinksAvailable) {
                continue;
            }

            $groupQuery = DB::connection('accounting')
                ->table('payment_invoice_group_items')
                ->where('source_type', $invoice['source_type'])
                ->where('invoice_id', (int) $invoice['source_id'])
                ->whereNull('process_payment_invoice_id');

            if ($invoice['source_type'] === 'online_report') {
                $groupQuery->where('invoice_no', $invoice['invoice_no']);
            }

            $groupRows = $groupQuery->get(['id', 'payment_invoice_group_id']);

            if ($groupRows->isEmpty()) {
                continue;
            }

            $groupItemIds = $groupRows->pluck('id')->map('intval')->all();

            DB::connection('accounting')
                ->table('payment_invoice_group_items')
                ->whereIn('id', $groupItemIds)
                ->update([
                    'process_payment_invoice_id' => $paymentInvoiceId,
                    'updated_at' => $historicalTimestamp,
                ]);

            foreach ($groupRows as $groupRow) {
                $linkedGroupItemIds[] = (int) $groupRow->id;
                $affectedGroupIds[] = (int) $groupRow->payment_invoice_group_id;
            }
        }

        echo "CREATED={$paymentNo} CUSTOMER={$customerId} INVOICES="
            . $rows->count() . ' PAID=' . number_format($totalPaid, 2, '.', '') . "\n";
    }
});

foreach (array_values(array_unique(array_map('intval', $affectedGroupIds))) as $groupId) {
    try {
        $detail = app(\App\Services\InvoiceGroupService::class)->detail($groupId);
        $items = collect($detail['items'] ?? []);

        if ($items->isEmpty()) {
            continue;
        }

        $fullySettled = $items->filter(
            fn ($item) => (float) ($item['due_amount'] ?? 0) <= 0.005
        )->count();

        $status = $fullySettled === $items->count()
            ? 'processed'
            : ($fullySettled > 0 ? 'partially_processed' : 'active');

        DB::connection('accounting')
            ->table('payment_invoice_groups')
            ->where('id', $groupId)
            ->update([
                'status' => $status,
                'updated_at' => $historicalTimestamp,
            ]);
    } catch (Throwable $e) {
        echo "GROUP_STATUS_WARNING={$groupId}: {$e->getMessage()}\n";
    }
}

$manifest['inserted_payment_ids'] = $insertedPaymentIds;
$manifest['inserted_payment_invoice_ids'] = $insertedInvoiceIds;
$manifest['linked_group_item_ids'] = array_values(array_unique($linkedGroupItemIds));

file_put_contents(
    $backupFile,
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
);

$remaining = $buildCandidates();

echo "PAYMENTS_CREATED=" . count($insertedPaymentIds) . "\n";
echo "INVOICE_SETTLEMENT_ROWS=" . count($insertedInvoiceIds) . "\n";
echo "REMAINING_ELIGIBLE_UNPAID=" . count($remaining) . "\n";
echo "PAYMENT_DATE_CHECK="
    . DB::connection('accounting')
        ->table('process_payments')
        ->where('remarks', 'like', '%' . $batch . '%')
        ->where('payment_date', '<>', $paymentDate)
        ->count()
    . "\n";

if ($remaining) {
    echo "WARNING=Some eligible invoices still have a balance.\n";
    foreach (array_slice($remaining, 0, 20) as $row) {
        echo $row['source_type'] . ' | ' . $row['invoice_no']
            . ' | ' . $row['invoice_date']
            . ' | DUE=' . number_format($row['remaining_due'], 2, '.', '') . "\n";
    }
    exit(2);
}

echo "SUCCESS=YES\n";
echo "All eligible outstanding invoices are settled with payment date 2026-07-25.\n";
