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

    public function configurationPayload(int $customerId): array
    {
        $customer = DB::connection('masterlist')
            ->table('customers')
            ->where('id', $customerId)
            ->first(['id', 'name', 'terms']);

        if (!$customer) {
            throw new RuntimeException('Customer not found.');
        }

        $config = $this->hasStorage()
            ? DB::connection(self::SYSTEM_CONNECTION)
                ->table(self::CONFIG_TABLE)
                ->where('customer_id', $customerId)
                ->first()
            : null;

        $leadValue = (int) ($config->lead_value ?? 14);
        $leadUnit = (string) ($config->lead_unit ?? 'days');
        $termDays = $this->parseTermsDays((string) ($customer->terms ?? ''));
        $account = $this->linkedPortalAccount($customerId);

        return [
            'customer' => [
                'id' => (int) $customer->id,
                'name' => (string) $customer->name,
                'terms' => (string) ($customer->terms ?? ''),
                'term_days' => $termDays,
            ],
            'linked_account' => $account,
            'configuration' => [
                'enabled' => (bool) ($config->enabled ?? false),
                'lead_value' => max(1, $leadValue),
                'lead_unit' => in_array($leadUnit, ['minutes', 'days', 'months'], true) ? $leadUnit : 'days',
                'last_sent_at' => $config->last_sent_at ?? null,
                'last_error' => (string) ($config->last_error ?? ''),
                'updated_at' => $config->updated_at ?? null,
            ],
            'mail' => [
                'mailer' => (string) config('mail.default', 'log'),
                'ready' => $this->mailReady(),
            ],
            'example' => $this->exampleText($termDays, max(1, $leadValue), $leadUnit),
            'storage_ready' => $this->hasStorage(),
        ];
    }

    public function saveConfiguration(
        int $customerId,
        bool $enabled,
        int $leadValue,
        string $leadUnit,
        string $actor
    ): array {
        $this->assertStorage();

        $customer = DB::connection('masterlist')
            ->table('customers')
            ->where('id', $customerId)
            ->first(['id', 'name', 'terms']);

        if (!$customer) {
            throw new RuntimeException('Customer not found.');
        }

        $termDays = $this->parseTermsDays((string) ($customer->terms ?? ''));
        if ($enabled && !$termDays) {
            throw new RuntimeException('This customer needs a numeric Terms value first, for example "Net 130 Days" or "130 Days".');
        }

        $account = $this->linkedPortalAccount($customerId);
        if ($enabled && (!$account || empty($account['email_valid']))) {
            throw new RuntimeException('This customer is not linked to a valid Pricelist login email. Generate/link the customer portal account first.');
        }

        if (!in_array($leadUnit, ['minutes', 'days', 'months'], true)) {
            throw new RuntimeException('Invalid SOA lead-time type.');
        }

        $leadValue = max(1, $leadValue);
        $now = now('Asia/Manila');

        $connection = DB::connection(self::SYSTEM_CONNECTION);
        $existing = $connection->table(self::CONFIG_TABLE)
            ->where('customer_id', $customerId)
            ->first(['id']);

        $payload = [
            'enabled' => $enabled ? 1 : 0,
            'lead_value' => $leadValue,
            'lead_unit' => $leadUnit,
            'updated_by' => $actor,
            'updated_at' => $now,
        ];

        if ($existing) {
            $connection->table(self::CONFIG_TABLE)
                ->where('customer_id', $customerId)
                ->update($payload);
        } else {
            $connection->table(self::CONFIG_TABLE)->insert([
                'customer_id' => $customerId,
                ...$payload,
                'created_at' => $now,
            ]);
        }

        return $this->configurationPayload($customerId);
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

        if (!$this->mailReady()) {
            throw new RuntimeException('Automatic SOA email is disabled because MAIL_MAILER is set to log/array or has no usable mail configuration.');
        }

        $configs = DB::connection(self::SYSTEM_CONNECTION)
            ->table(self::CONFIG_TABLE)
            ->where('enabled', 1)
            ->orderBy('customer_id')
            ->get();

        foreach ($configs as $config) {
            $summary['checked']++;

            try {
                $result = $this->processCustomerConfig($config);

                if (!empty($result['sent'])) {
                    $summary['sent_customers']++;
                    $summary['sent_invoices'] += (int) ($result['eligible_invoice_count'] ?? 0);
                } else {
                    $summary['skipped']++;
                }
            } catch (Throwable $exception) {
                $summary['errors'][] = 'Customer #' . (int) $config->customer_id . ': ' . $exception->getMessage();
                $this->recordConfigError((int) $config->customer_id, $exception->getMessage());
                Log::error('SOA AUTO customer failed', [
                    'customer_id' => (int) $config->customer_id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $summary;
    }

    private function processCustomerConfig(object $config): array
    {
        $customerId = (int) ($config->customer_id ?? 0);
        if ($customerId <= 0) {
            return ['sent' => false];
        }

        $customer = DB::connection('masterlist')
            ->table('customers')
            ->where('id', $customerId)
            ->first(['id', 'name', 'address', 'terms']);

        if (!$customer) {
            throw new RuntimeException('Customer record no longer exists.');
        }

        $termDays = $this->parseTermsDays((string) ($customer->terms ?? ''));
        if (!$termDays) {
            throw new RuntimeException('Customer Terms has no positive day value.');
        }

        $account = $this->linkedPortalAccount($customerId);
        if (!$account || empty($account['email_valid'])) {
            throw new RuntimeException('No valid linked Pricelist login email.');
        }

        $leadValue = max(1, (int) ($config->lead_value ?? 14));
        $leadUnit = (string) ($config->lead_unit ?? 'days');
        if (!in_array($leadUnit, ['minutes', 'days', 'months'], true)) {
            $leadUnit = 'days';
        }

        $now = Carbon::now('Asia/Manila');
        $statement = $this->buildStatement($customerId, $now);

        if (empty($statement['transactions'])) {
            $this->clearConfigError($customerId);
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

            // The reminder is intentionally pre-due only. If the scheduler was down
            // past the due date, it will not send a late automatic reminder.
            if ($now->gte($sendAt) && $now->lte($dueAt)) {
                $transaction['due_at'] = $dueAt->toDateTimeString();
                $transaction['send_at'] = $sendAt->toDateTimeString();
                $eligible[] = $transaction;
            }
        }

        if ($eligible === []) {
            $this->clearConfigError($customerId);
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
            $customerName,
            $recipient
        ) {
            foreach ($eligible as $transaction) {
                $system = DB::connection(self::SYSTEM_CONNECTION);
                $system->table(self::LOG_TABLE)->insertOrIgnore([
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

            $system->table(self::CONFIG_TABLE)
                ->where('customer_id', $customerId)
                ->update([
                    'last_sent_at' => $now,
                    'last_error' => null,
                    'updated_at' => $now,
                ]);

            $system->table(self::NOTIFICATION_TABLE)->insertOrIgnore([
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

    private function exampleText(?int $termDays, int $leadValue, string $leadUnit): string
    {
        if (!$termDays) {
            return 'Set a numeric customer Terms value first (for example: 130 Days).';
        }

        if ($leadUnit === 'days') {
            $sendDay = $termDays - $leadValue;
            return $sendDay >= 0
                ? "For {$termDays}-day terms and {$leadValue} days before due, SOA sends at invoice age {$sendDay} days."
                : "The lead time is longer than the {$termDays}-day terms, so the first eligible finalized invoice would send as soon as the scheduler sees it.";
        }

        return "Due date is invoice date + {$termDays} days; SOA sends {$leadValue} {$leadUnit} before that due date.";
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
            && $schema->hasTable(self::LOG_TABLE)
            && $schema->hasTable(self::NOTIFICATION_TABLE);
    }

    private function assertStorage(): void
    {
        if (!$this->hasStorage()) {
            throw new RuntimeException('SOA(AUTO) tables are missing. Run php artisan migrate --force first.');
        }
    }

    private function recordConfigError(int $customerId, string $message): void
    {
        if (!Schema::connection(self::SYSTEM_CONNECTION)->hasTable(self::CONFIG_TABLE)) {
            return;
        }

        DB::connection(self::SYSTEM_CONNECTION)
            ->table(self::CONFIG_TABLE)
            ->where('customer_id', $customerId)
            ->update([
                'last_error' => Str::limit($message, 2000),
                'updated_at' => now('Asia/Manila'),
            ]);
    }

    private function clearConfigError(int $customerId): void
    {
        if (!Schema::connection(self::SYSTEM_CONNECTION)->hasTable(self::CONFIG_TABLE)) {
            return;
        }

        DB::connection(self::SYSTEM_CONNECTION)
            ->table(self::CONFIG_TABLE)
            ->where('customer_id', $customerId)
            ->whereNotNull('last_error')
            ->update([
                'last_error' => null,
                'updated_at' => now('Asia/Manila'),
            ]);
    }
}
