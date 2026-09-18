<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CustomerAutoSoaService
{
    private const SYSTEM_CONNECTION = 'mysql';
    private const CONFIG_TABLE = 'customer_soa_auto_configs';
    private const LOG_TABLE = 'customer_soa_auto_send_logs';
    private const NOTIFICATION_TABLE = 'customer_portal_notifications';
    private const CONFIG_KEY = 'global';

    public function parseTermsDays(?string $terms): ?int
    {
        $terms = trim((string) $terms);
        if ($terms === '') {
            return null;
        }

        if (!preg_match('/(\d+)/', $terms, $matches)) {
            return null;
        }

        $days = (int) ($matches[1] ?? 0);
        return $days > 0 ? $days : null;
    }

    public function linkedPortalAccount(int $customerId): ?array
    {
        if (!Schema::connection(self::SYSTEM_CONNECTION)->hasTable('customer_portal_accounts')) {
            return null;
        }

        $link = DB::connection(self::SYSTEM_CONNECTION)
            ->table('customer_portal_accounts')
            ->where('customer_id', $customerId)
            ->first(['login_id', 'customer_id', 'linked_at']);

        if (!$link || (int) ($link->login_id ?? 0) <= 0) {
            return null;
        }

        $login = DB::connection(self::SYSTEM_CONNECTION)
            ->table('logins')
            ->where('login_ID', (int) $link->login_id)
            ->first(['login_ID', 'User_ID', 'Email']);

        if (!$login) {
            return null;
        }

        $email = trim((string) ($login->Email ?? ''));

        return [
            'login_id' => (int) $login->login_ID,
            'username' => (string) ($login->User_ID ?? ''),
            'email' => $email,
            'email_valid' => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
            'linked_at' => $link->linked_at ?? null,
        ];
    }

    public function configurationPayload(): array
    {
        $config = $this->hasStorage()
            ? DB::connection(self::SYSTEM_CONNECTION)
                ->table(self::CONFIG_TABLE)
                ->where('config_key', self::CONFIG_KEY)
                ->first()
            : null;

        $leadValue = $config && $config->lead_value !== null ? (int) $config->lead_value : null;
        $leadUnit = $config && in_array((string) $config->lead_unit, ['minutes', 'days', 'months'], true)
            ? (string) $config->lead_unit
            : null;

        $stats = $this->linkedCustomerStats();

        return [
            'scope' => [
                'type' => 'global',
                'label' => 'All linked Pricelist customer accounts',
                ...$stats,
            ],
            'configuration' => [
                'enabled' => (bool) ($config->enabled ?? false),
                'lead_value' => $leadValue,
                'lead_unit' => $leadUnit,
                'last_sent_at' => $config->last_sent_at ?? null,
                'last_error' => (string) ($config->last_error ?? ''),
                'updated_at' => $config->updated_at ?? null,
            ],
            'mail' => [
                'mailer' => (string) config('mail.default', 'log'),
                'ready' => $this->mailReady(),
            ],
            'example' => $this->globalExampleText($leadValue, $leadUnit),
            'storage_ready' => $this->hasStorage(),
        ];
    }

    public function saveConfiguration(
        bool $enabled,
        ?int $leadValue,
        ?string $leadUnit,
        string $actor
    ): array {
        $this->assertStorage();

        if ($enabled) {
            if (!$leadValue || $leadValue < 1) {
                throw new RuntimeException('Enter the SOA lead time first.');
            }
            if (!in_array((string) $leadUnit, ['minutes', 'days', 'months'], true)) {
                throw new RuntimeException('Select Minutes, Days, or Months.');
            }
        }

        $now = now('Asia/Manila');
        $payload = [
            'config_key' => self::CONFIG_KEY,
            'enabled' => $enabled ? 1 : 0,
            'lead_value' => $enabled ? max(1, (int) $leadValue) : null,
            'lead_unit' => $enabled ? (string) $leadUnit : null,
            'last_error' => null,
            'updated_by' => $actor,
            'updated_at' => $now,
        ];

        $connection = DB::connection(self::SYSTEM_CONNECTION);
        $existing = $connection->table(self::CONFIG_TABLE)
            ->where('config_key', self::CONFIG_KEY)
            ->first(['id']);

        if ($existing) {
            $connection->table(self::CONFIG_TABLE)
                ->where('config_key', self::CONFIG_KEY)
                ->update($payload);
        } else {
            $connection->table(self::CONFIG_TABLE)->insert([
                ...$payload,
                'created_at' => $now,
            ]);
        }

        return $this->configurationPayload();
    }

    public function runDueReminders(): array
    {
        $this->assertStorage();

        $summary = [
            'checked' => 0,
            'sent_customers' => 0,
            'sent_invoices' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $config = DB::connection(self::SYSTEM_CONNECTION)
            ->table(self::CONFIG_TABLE)
            ->where('config_key', self::CONFIG_KEY)
            ->where('enabled', 1)
            ->first();

        if (!$config) {
            return $summary;
        }

        if ($config->lead_value === null || !in_array((string) $config->lead_unit, ['minutes', 'days', 'months'], true)) {
            throw new RuntimeException('Global SOA(AUTO) is enabled but its lead time is not configured.');
        }

        if (!$this->mailReady()) {
            throw new RuntimeException('Automatic SOA email is disabled because MAIL_MAILER is set to log/array or has no usable mail configuration.');
        }

        if (!Schema::connection(self::SYSTEM_CONNECTION)->hasTable('customer_portal_accounts')) {
            throw new RuntimeException('customer_portal_accounts table is missing.');
        }

        $customerIds = DB::connection(self::SYSTEM_CONNECTION)
            ->table('customer_portal_accounts')
            ->whereNotNull('customer_id')
            ->whereNotNull('login_id')
            ->orderBy('customer_id')
            ->pluck('customer_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $anySent = false;

        foreach ($customerIds as $customerId) {
            $summary['checked']++;

            try {
                $result = $this->processCustomerConfig((int) $customerId, $config);

                if (!empty($result['sent'])) {
                    $anySent = true;
                    $summary['sent_customers']++;
                    $summary['sent_invoices'] += (int) ($result['eligible_invoice_count'] ?? 0);
                } else {
                    $summary['skipped']++;
                }
            } catch (Throwable $exception) {
                $summary['errors'][] = 'Customer #' . (int) $customerId . ': ' . $exception->getMessage();
                Log::error('SOA AUTO customer failed', [
                    'customer_id' => (int) $customerId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        DB::connection(self::SYSTEM_CONNECTION)
            ->table(self::CONFIG_TABLE)
            ->where('config_key', self::CONFIG_KEY)
            ->update([
                'last_sent_at' => $anySent ? now('Asia/Manila') : ($config->last_sent_at ?? null),
                'last_error' => empty($summary['errors'])
                    ? null
                    : Str::limit(implode(' | ', array_slice($summary['errors'], 0, 10)), 2000),
                'updated_at' => now('Asia/Manila'),
            ]);

        return $summary;
    }

    private function processCustomerConfig(int $customerId, object $config): array
    {
        if ($customerId <= 0) {
            return ['sent' => false, 'reason' => 'invalid_customer'];
        }

        $customer = DB::connection('masterlist')
            ->table('customers')
            ->where('id', $customerId)
            ->first(['id', 'name', 'address', 'terms']);

        if (!$customer) {
            return ['sent' => false, 'reason' => 'customer_missing'];
        }

        $termDays = $this->parseTermsDays((string) ($customer->terms ?? ''));
        if (!$termDays) {
            return ['sent' => false, 'reason' => 'missing_terms'];
        }

        $account = $this->linkedPortalAccount($customerId);
        if (!$account || empty($account['email_valid'])) {
            return ['sent' => false, 'reason' => 'invalid_linked_email'];
        }

        $leadValue = max(1, (int) $config->lead_value);
        $leadUnit = (string) $config->lead_unit;

        $now = Carbon::now('Asia/Manila');
        $statement = $this->buildStatement($customerId, $now);

        if (empty($statement['transactions'])) {
            return ['sent' => false, 'reason' => 'no_unpaid_invoices'];
        }

        $alreadySent = DB::connection(self::SYSTEM_CONNECTION)
            ->table(self::LOG_TABLE)
            ->where('customer_id', $customerId)
            ->where('status', 'sent')
            ->pluck('invoice_key')
            ->map(fn ($value) => (string) $value)
            ->flip();

        $eligible = [];
        foreach ($statement['transactions'] as $transaction) {
            $invoiceKey = (string) ($transaction['invoice_key'] ?? '');
            if ($invoiceKey === '' || $alreadySent->has($invoiceKey)) {
                continue;
            }

            $invoiceAt = Carbon::parse((string) $transaction['invoice_at'], 'Asia/Manila');
            $dueAt = $invoiceAt->copy()->addDays($termDays);
            $sendAt = $this->subtractLead($dueAt->copy(), $leadValue, $leadUnit);

            if ($now->gte($sendAt) && $now->lte($dueAt)) {
                $transaction['due_at'] = $dueAt->toDateTimeString();
                $transaction['send_at'] = $sendAt->toDateTimeString();
                $eligible[] = $transaction;
            }
        }

        if ($eligible === []) {
            return ['sent' => false, 'reason' => 'not_due'];
        }

        $pages = $this->buildPrintPages($statement['customer'], $statement['transactions']);
        $dateRangeLabel = 'As Of ' . $now->format('d-M-y');
        $securityId = Str::uuid()->toString();

        $pdfBytes = Pdf::loadView('Admin.Reports.prints.sales-report-print', [
            'pages' => $pages,
            'dateRangeLabel' => $dateRangeLabel,
            'timestamp' => $now->format('m-d-Y h:i A'),
            'printedBy' => 'SOA AUTO',
            'securityId' => $securityId,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('defaultMediaType', 'print')
            ->output();

        $recipient = (string) $account['email'];
        $customerName = (string) $statement['customer']['name'];
        $totalBalance = (float) $statement['total_balance'];
        $subject = 'W68 Statement of Account - Payment Reminder - ' . $customerName;
        $fileName = 'W68-SOA-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $customerName) . '-' . $now->format('Ymd') . '.pdf';

        Mail::send('emails.customer-soa-reminder', [
            'customerName' => $customerName,
            'terms' => (string) ($statement['customer']['terms'] ?? ''),
            'totalBalance' => $totalBalance,
            'invoiceCount' => count($statement['transactions']),
            'eligibleInvoiceCount' => count($eligible),
            'generatedAt' => $now,
        ], function ($message) use ($recipient, $subject, $pdfBytes, $fileName) {
            $message->to($recipient)
                ->subject($subject)
                ->attachData($pdfBytes, $fileName, ['mime' => 'application/pdf']);
        });

        $batchKey = (string) Str::uuid();
        $system = DB::connection(self::SYSTEM_CONNECTION);
        $system->transaction(function () use (
            $eligible,
            $customerId,
            $account,
            $batchKey,
            $now,
            $totalBalance,
            $recipient
        ) {
            foreach ($eligible as $transaction) {
                DB::connection(self::SYSTEM_CONNECTION)->table(self::LOG_TABLE)->insertOrIgnore([
                    'customer_id' => $customerId,
                    'login_id' => (int) $account['login_id'],
                    'email' => $recipient,
                    'invoice_key' => (string) $transaction['invoice_key'],
                    'invoice_no' => (string) $transaction['invoice_no'],
                    'invoice_date' => substr((string) $transaction['invoice_at'], 0, 19),
                    'due_at' => (string) $transaction['due_at'],
                    'send_at' => (string) $transaction['send_at'],
                    'balance' => (float) $transaction['balance'],
                    'batch_key' => $batchKey,
                    'status' => 'sent',
                    'error_message' => null,
                    'sent_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::connection(self::SYSTEM_CONNECTION)->table(self::NOTIFICATION_TABLE)->insertOrIgnore([
                'login_id' => (int) $account['login_id'],
                'customer_id' => $customerId,
                'event_type' => 'SOA_AUTO_SENT',
                'event_key' => 'SOA_AUTO_SENT:' . $batchKey,
                'title' => 'Statement of Account Sent',
                'message' => 'Your W68 Statement of Account was sent to ' . $recipient . ' as a payment reminder. Outstanding balance: PHP ' . number_format($totalBalance, 2) . '.',
                'event_at' => $now,
                'is_read' => 0,
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        return [
            'sent' => true,
            'customer' => $customerName,
            'email' => $recipient,
            'eligible_invoice_count' => count($eligible),
            'statement_invoice_count' => count($statement['transactions']),
            'total_balance' => $totalBalance,
        ];
    }

    /**
     * Build the same finalized/unpaid invoice concept used by Sales Report SOA.
     * Local invoices use finalized Sales Orders; online invoices use the existing
     * hatdogBuildOnlineInvoices() helper and Accounting Payments source identity.
     */
    private function buildStatement(int $customerId, Carbon $asOf): array
    {
        $customer = DB::connection('masterlist')
            ->table('customers')
            ->where('id', $customerId)
            ->first(['id', 'name', 'address', 'terms']);

        if (!$customer) {
            throw new RuntimeException('Customer not found.');
        }

        $normalize = static fn ($value) => strtoupper(preg_replace('/\s+/', '', trim((string) $value)));
        $invoiceDateSql = 'COALESCE(so.created_at, sn.order_date)';

        $localInvoices = DB::connection('sales')->table('sales_orders as so')
            ->leftJoin('sales_notes as sn', 'sn.id', '=', 'so.sales_note_id')
            ->where('so.customer_id', $customerId)
            ->where('so.order_number', 'NOT LIKE', 'ONL-%')
            ->where(function ($query) {
                $query->whereIn('so.status', ['Closed', 'Confirmed'])
                    ->orWhere('sn.status', 'Closed');
            })
            ->whereNotNull('so.total_amount')
            ->where('so.total_amount', '>', 0)
            ->whereRaw("{$invoiceDateSql} <= ?", [$asOf->toDateTimeString()])
            ->select(
                DB::raw("'sales_order' as source_type"),
                'so.id as source_id',
                'so.customer_id',
                'so.customer_name',
                'so.order_number as po_no',
                DB::raw("COALESCE(NULLIF(so.invoice_numbers, ''), so.order_number) as invoice_no"),
                'so.total_amount as amount',
                'sn.sales_number as sales_note_no',
                'sn.net_total as sales_note_amount',
                DB::raw("{$invoiceDateSql} as invoice_date"),
                DB::raw("'' as row_remarks")
            )
            ->get();

        $onlineInvoices = collect();
        if (function_exists('hatdogBuildOnlineInvoices')) {
            $rawOnline = collect(hatdogBuildOnlineInvoices($customerId));

            $reportIds = $rawOnline->pluck('source_id')
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all();

            $reportMap = collect();
            foreach (array_chunk($reportIds, 1000) as $chunk) {
                foreach (DB::connection('sales')->table('online_reports')
                    ->whereIn('id', $chunk)
                    ->get(['id', 'addresses', 'notes_data', 'created_at']) as $row) {
                    $reportMap->put((int) $row->id, $row);
                }
            }

            $orderMap = [];
            if ($reportIds !== []) {
                $orders = DB::connection('sales')->table('sales_orders')
                    ->where('order_number', 'LIKE', 'ONL-%')
                    ->whereIn('status', ['Confirmed', 'Closed'])
                    ->where('customer_id', $customerId)
                    ->get(['id', 'order_number', 'invoice_numbers', 'created_at', 'updated_at']);

                foreach ($orders as $order) {
                    if (!preg_match('/^ONL-(\d+)-/', (string) $order->order_number, $match)) {
                        continue;
                    }
                    $reportId = (int) $match[1];
                    if (!in_array($reportId, $reportIds, true)) {
                        continue;
                    }
                    $values = json_decode((string) ($order->invoice_numbers ?? ''), true);
                    if (!is_array($values)) {
                        $values = array_map('trim', explode(',', (string) ($order->invoice_numbers ?? '')));
                    }
                    foreach ($values as $value) {
                        $key = $normalize($value);
                        if ($key !== '') {
                            $orderMap[$reportId . ':' . $key] = $order;
                        }
                    }
                }
            }

            foreach ($rawOnline as $doc) {
                $reportId = (int) ($doc->source_id ?? 0);
                $invoiceNo = trim((string) ($doc->invoice_no ?? ''));
                $amount = (float) ($doc->amount ?? 0);
                if ($reportId <= 0 || $invoiceNo === '' || $amount <= 0) {
                    continue;
                }

                $report = $reportMap->get($reportId);
                $invoiceIndex = (int) ($doc->invoice_index ?? 0);
                $order = $orderMap[$reportId . ':' . $normalize($invoiceNo)] ?? null;

                $notesData = $report ? json_decode((string) ($report->notes_data ?? '[]'), true) : [];
                if (!is_array($notesData)) {
                    $notesData = [];
                }
                $noteData = $notesData[(string) $invoiceIndex] ?? $notesData[$invoiceIndex] ?? null;
                $historicalDate = is_array($noteData) ? ($noteData['order_date'] ?? null) : null;
                $invoiceDate = $order->created_at ?? ($historicalDate ?: ($doc->created_at ?? ($report->created_at ?? null)));
                if (!$invoiceDate || Carbon::parse($invoiceDate, 'Asia/Manila')->gt($asOf)) {
                    continue;
                }

                $addresses = $report ? json_decode((string) ($report->addresses ?? '[]'), true) : [];
                if (!is_array($addresses)) {
                    $addresses = [];
                }
                $address = trim((string) ($addresses[(string) $invoiceIndex] ?? $addresses[$invoiceIndex] ?? ''));

                $onlineInvoices->push((object) [
                    'source_type' => 'online_report',
                    'source_id' => $reportId,
                    'customer_id' => $customerId,
                    'customer_name' => (string) ($doc->customer_name ?? $customer->name),
                    'po_no' => $invoiceNo,
                    'invoice_no' => $invoiceNo,
                    'amount' => $amount,
                    'sales_note_no' => null,
                    'sales_note_amount' => null,
                    'invoice_date' => $invoiceDate,
                    'row_remarks' => $address,
                ]);
            }
        }

        $documents = $localInvoices->concat($onlineInvoices)->values();
        if ($documents->isEmpty()) {
            return [
                'customer' => (array) $customer,
                'transactions' => [],
                'total_balance' => 0.0,
            ];
        }

        $localSourceIds = $documents->where('source_type', 'sales_order')
            ->pluck('source_id')->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        $onlineSourceIds = $documents->where('source_type', 'online_report')
            ->pluck('source_id')->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();

        $settlementRows = collect();
        foreach (array_chunk($localSourceIds, 1000) as $chunk) {
            $settlementRows = $settlementRows->concat(
                DB::connection('accounting')->table('process_payment_invoices as ppi')
                    ->leftJoin('process_payments as pp', 'pp.id', '=', 'ppi.process_payment_id')
                    ->where('ppi.source_type', 'sales_order')
                    ->whereIn('ppi.source_id', $chunk)
                    ->where(function ($q) {
                        $q->whereNull('pp.status')
                            ->orWhereRaw("UPPER(TRIM(COALESCE(pp.status, 'POSTED'))) = 'POSTED'");
                    })
                    ->get(['ppi.source_type', 'ppi.source_id', 'ppi.invoice_no', 'ppi.paid_amount', 'ppi.adjustment'])
            );
        }
        foreach (array_chunk($onlineSourceIds, 1000) as $chunk) {
            $settlementRows = $settlementRows->concat(
                DB::connection('accounting')->table('process_payment_invoices as ppi')
                    ->leftJoin('process_payments as pp', 'pp.id', '=', 'ppi.process_payment_id')
                    ->where('ppi.source_type', 'online_report')
                    ->whereIn('ppi.source_id', $chunk)
                    ->where(function ($q) {
                        $q->whereNull('pp.status')
                            ->orWhereRaw("UPPER(TRIM(COALESCE(pp.status, 'POSTED'))) = 'POSTED'");
                    })
                    ->get(['ppi.source_type', 'ppi.source_id', 'ppi.invoice_no', 'ppi.paid_amount', 'ppi.adjustment'])
            );
        }

        $settlementMap = [];
        foreach ($settlementRows as $row) {
            $sourceType = (string) ($row->source_type ?? '');
            $sourceId = (int) ($row->source_id ?? 0);
            if ($sourceType === '' || $sourceId <= 0) {
                continue;
            }

            $key = $sourceType === 'online_report'
                ? $sourceType . ':' . $sourceId . ':' . $normalize($row->invoice_no ?? '')
                : $sourceType . ':' . $sourceId;

            $settlementMap[$key] ??= ['paid' => 0.0, 'adjustment' => 0.0];
            $settlementMap[$key]['paid'] += (float) ($row->paid_amount ?? 0);
            $settlementMap[$key]['adjustment'] += (float) ($row->adjustment ?? 0);
        }

        $returns = DB::connection('sales')->table('sales_returns as sr')
            ->join('sales_return_items as sri', 'sri.sales_return_id', '=', 'sr.id')
            ->where('sr.customer_id', $customerId)
            ->whereNotNull('sr.invoice_no')
            ->whereRaw("UPPER(TRIM(COALESCE(sr.status, ''))) NOT IN ('CANCELLED', 'VOID')")
            ->select('sr.invoice_no', DB::raw('SUM(COALESCE(sri.return_amount, sri.subtotal, 0)) as return_total'))
            ->groupBy('sr.invoice_no')
            ->get();

        $returnMap = [];
        foreach ($returns as $row) {
            $rawInvoice = trim((string) ($row->invoice_no ?? ''));
            $returnTotal = (float) ($row->return_total ?? 0);
            if ($rawInvoice === '' || $returnTotal <= 0) {
                continue;
            }

            $keys = [$normalize($rawInvoice)];
            foreach (preg_split('/[,\s\/]+/', $rawInvoice) ?: [] as $token) {
                if (trim((string) $token) !== '') {
                    $keys[] = $normalize($token);
                }
            }
            if (preg_match_all('/SN-\d+/i', $rawInvoice, $matches)) {
                foreach ($matches[0] as $salesNoteNo) {
                    $keys[] = $normalize($salesNoteNo);
                }
            }

            foreach (array_values(array_unique(array_filter($keys))) as $key) {
                $returnMap[$key] = (float) ($returnMap[$key] ?? 0) + $returnTotal;
            }
        }

        $transactions = [];
        $totalBalance = 0.0;

        foreach ($documents as $doc) {
            $sourceType = (string) ($doc->source_type ?? 'sales_order');
            $sourceId = (int) ($doc->source_id ?? 0);
            $invoiceNo = trim((string) ($doc->invoice_no ?? ''));
            $amount = round((float) ($doc->amount ?? 0), 2);
            if ($sourceId <= 0 || $invoiceNo === '' || $amount <= 0) {
                continue;
            }

            $settlementKey = $sourceType === 'online_report'
                ? $sourceType . ':' . $sourceId . ':' . $normalize($invoiceNo)
                : $sourceType . ':' . $sourceId;
            $settlement = $settlementMap[$settlementKey] ?? ['paid' => 0.0, 'adjustment' => 0.0];
            $paid = (float) ($settlement['paid'] ?? 0);
            $recordedAdjustment = (float) ($settlement['adjustment'] ?? 0);

            $invoiceKeys = collect(explode(',', $invoiceNo))
                ->map(fn ($value) => $normalize($value))
                ->filter()
                ->merge([$normalize($doc->po_no ?? ''), $normalize($doc->sales_note_no ?? '')])
                ->filter()
                ->unique()
                ->values();

            $returned = (float) $invoiceKeys->sum(fn ($key) => (float) ($returnMap[$key] ?? 0));
            $salesNoteAmount = (float) ($doc->sales_note_amount ?? 0);
            $effectiveAdjustment = max($returned, $recordedAdjustment);
            $autoSettledByReturn = $returned > 0 && (
                $returned >= $amount
                || ($salesNoteAmount > 0 && $returned >= $salesNoteAmount)
                || $paid >= ($amount / 2)
            );

            $balance = $autoSettledByReturn
                ? 0.0
                : max($amount - $paid - $effectiveAdjustment, 0.0);
            $balance = round($balance, 2);

            if ($balance <= 0.005) {
                continue;
            }

            $credit = round(max($amount - $balance, 0.0), 2);
            $invoiceAt = Carbon::parse($doc->invoice_date, 'Asia/Manila');
            $invoiceKey = $sourceType === 'online_report'
                ? $sourceType . ':' . $sourceId . ':' . $normalize($invoiceNo)
                : $sourceType . ':' . $sourceId;

            $transactions[] = [
                'date' => $invoiceAt->toDateString(),
                'invoice_at' => $invoiceAt->toDateTimeString(),
                'invoice_key' => $invoiceKey,
                'invoice_no' => $invoiceNo,
                'debits' => $amount,
                'credits' => $credit,
                'balance' => $balance,
                'remarks' => trim((string) ($doc->row_remarks ?? '')),
                'sort_key' => $invoiceAt->format('Y-m-d H:i:s') . '|' . $invoiceNo,
            ];
            $totalBalance += $balance;
        }

        usort($transactions, fn ($a, $b) => strcmp($a['sort_key'], $b['sort_key']));
        foreach ($transactions as &$transaction) {
            unset($transaction['sort_key']);
        }
        unset($transaction);

        return [
            'customer' => [
                'id' => (int) $customer->id,
                'name' => (string) $customer->name,
                'address' => (string) ($customer->address ?? ''),
                'terms' => (string) ($customer->terms ?? ''),
            ],
            'transactions' => $transactions,
            'total_balance' => round($totalBalance, 2),
        ];
    }

    private function buildPrintPages(array $customer, array $transactions): array
    {
        $chunks = array_chunk($transactions, 35);
        $pages = [];
        $count = count($chunks);
        $totalBalance = round((float) collect($transactions)->sum('balance'), 2);

        foreach ($chunks as $index => $chunk) {
            $rows = array_map(function (array $row): array {
                return [
                    'date' => $row['date'],
                    'invoice_no' => $row['invoice_no'],
                    'debits' => (float) $row['debits'],
                    'credits' => (float) $row['credits'],
                    'balance' => (float) $row['balance'],
                    'remarks' => (string) $row['remarks'],
                ];
            }, $chunk);

            $pages[] = [
                'customer' => $customer,
                'rows' => $rows,
                'show_customer_box' => $index === 0,
                'show_payment_summary' => $index === $count - 1,
                'total_balance' => $index === $count - 1 ? $totalBalance : 0,
                'page_number' => $index + 1,
                'total_pages' => $count,
            ];
        }

        return $pages;
    }

    private function subtractLead(Carbon $dueAt, int $value, string $unit): Carbon
    {
        return match ($unit) {
            'minutes' => $dueAt->subMinutes($value),
            'months' => $dueAt->subMonthsNoOverflow($value),
            default => $dueAt->subDays($value),
        };
    }

    private function globalExampleText(?int $leadValue, ?string $leadUnit): string
    {
        if (!$leadValue || !in_array((string) $leadUnit, ['minutes', 'days', 'months'], true)) {
            return 'Enable SOA(AUTO) and enter a lead time. Example: 14 Days applies to every linked customer using that customer\'s own Terms.';
        }

        if ($leadUnit === 'days') {
            $sampleTerms = 130;
            $sendDay = $sampleTerms - $leadValue;
            return $sendDay >= 0
                ? "Example: a customer with 130-day Terms and {$leadValue} day(s) before due receives the SOA at invoice age {$sendDay} day(s)."
                : "Example: the lead time is longer than 130 days, so a 130-day customer becomes eligible as soon as the finalized unpaid invoice is seen.";
        }

        return "The same {$leadValue} {$leadUnit} lead time applies to every linked customer, calculated backward from each customer's own due date.";
    }

    private function linkedCustomerStats(): array
    {
        $schema = Schema::connection(self::SYSTEM_CONNECTION);
        if (!$schema->hasTable('customer_portal_accounts') || !$schema->hasTable('logins')) {
            return [
                'linked_customer_count' => 0,
                'valid_email_count' => 0,
                'numeric_terms_count' => 0,
            ];
        }

        $links = DB::connection(self::SYSTEM_CONNECTION)
            ->table('customer_portal_accounts as cpa')
            ->leftJoin('logins as l', 'l.login_ID', '=', 'cpa.login_id')
            ->whereNotNull('cpa.customer_id')
            ->get(['cpa.customer_id', 'l.Email']);

        $byCustomer = [];
        foreach ($links as $link) {
            $customerId = (int) ($link->customer_id ?? 0);
            if ($customerId <= 0) continue;
            $email = trim((string) ($link->Email ?? ''));
            $byCustomer[$customerId] = $email;
        }

        $customerIds = array_keys($byCustomer);
        $termsByCustomer = empty($customerIds)
            ? collect()
            : DB::connection('masterlist')->table('customers')
                ->whereIn('id', $customerIds)
                ->pluck('terms', 'id');

        $validEmail = 0;
        $numericTerms = 0;
        foreach ($byCustomer as $customerId => $email) {
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                $validEmail++;
            }
            if ($this->parseTermsDays((string) ($termsByCustomer[$customerId] ?? ''))) {
                $numericTerms++;
            }
        }

        return [
            'linked_customer_count' => count($byCustomer),
            'valid_email_count' => $validEmail,
            'numeric_terms_count' => $numericTerms,
        ];
    }

    private function mailReady(): bool
    {
        $mailer = strtolower(trim((string) config('mail.default', 'log')));
        if (in_array($mailer, ['', 'log', 'array'], true)) {
            return false;
        }

        if ($mailer === 'smtp') {
            return trim((string) config('mail.mailers.smtp.host', '')) !== ''
                && trim((string) config('mail.from.address', '')) !== ''
                && trim((string) config('mail.mailers.smtp.username', '')) !== '';
        }

        return trim((string) config('mail.from.address', '')) !== '';
    }

    private function hasStorage(): bool
    {
        $schema = Schema::connection(self::SYSTEM_CONNECTION);
        return $schema->hasTable(self::CONFIG_TABLE)
            && $schema->hasColumn(self::CONFIG_TABLE, 'config_key')
            && $schema->hasTable(self::LOG_TABLE)
            && $schema->hasTable(self::NOTIFICATION_TABLE);
    }

    private function assertStorage(): void
    {
        if (!$this->hasStorage()) {
            throw new RuntimeException('SOA(AUTO) tables are missing. Run php artisan migrate --force first.');
        }
    }


}
