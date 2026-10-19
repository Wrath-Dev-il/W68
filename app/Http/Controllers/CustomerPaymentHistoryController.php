<?php

namespace App\Http\Controllers;

use App\Services\PaymentHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerPaymentHistoryController extends Controller
{
    /**
     * Customer Master -> Ledger / Payment History.
     *
     * Payment truth comes from the same core4_accounting tables used by
     * Special -> Accounting -> Payments. This endpoint is read-only and does
     * not change /admin/payments or payment processing.
     */
    public function index(int $id): JsonResponse
    {
        if (!$this->authorizedStaff()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $customer = DB::connection('masterlist')
                ->table('customers')
                ->where('id', $id)
                ->first(['id', 'name']);

            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found.',
                ], 404);
            }

            $customerName = trim((string) ($customer->name ?? ''));

            $orders = $this->customerOrders((int) $customer->id, $customerName);
            $orderIds = $orders->pluck('id')->map(fn ($value) => (int) $value)->filter()->values();

            $settlements = $this->settlementsForOrders($orderIds);
            $returnsByInvoiceKey = $this->returnsForCustomer((int) $customer->id, $customerName);

            $ledger = $orders->map(function ($order) use ($settlements, $returnsByInvoiceKey) {
                $invoiceNumbers = $this->invoiceNumbers(
                    $order->invoice_numbers ?? null,
                    $order->order_number ?? ''
                );

                $invoiceDisplay = $invoiceNumbers !== []
                    ? implode(', ', $invoiceNumbers)
                    : (string) ($order->order_number ?? '---');

                $settlement = $settlements->get((int) $order->id);
                $paid = (float) ($settlement->paid_total ?? 0);
                $recordedAdjustment = (float) ($settlement->adjustment_total ?? 0);

                $returnRows = collect();
                $keys = collect($invoiceNumbers)
                    ->push((string) ($order->order_number ?? ''))
                    ->map(fn ($value) => $this->normalizeInvoiceKey($value))
                    ->filter()
                    ->unique();

                foreach ($keys as $key) {
                    $returnRows = $returnRows->merge($returnsByInvoiceKey->get($key, collect()));
                }

                // A return can be indexed by multiple aliases (invoice / SO number).
                // De-duplicate by Sales Return ID before totaling.
                $returnRows = $returnRows
                    ->unique(fn ($row) => (int) ($row->sales_return_id ?? 0))
                    ->values();

                $returned = (float) $returnRows->sum(fn ($row) => (float) ($row->return_total ?? 0));
                $hasReturn = $returnRows->isNotEmpty();

                $amount = (float) ($order->total_amount ?? 0);
                $effectiveAdjustment = max($returned, $recordedAdjustment);
                $due = max($amount - $paid - $effectiveAdjustment, 0);

                $hasPaymentRecord = $settlement !== null
                    && (
                        $paid > 0.005
                        || $recordedAdjustment > 0.005
                        || trim((string) ($settlement->payment_numbers ?? '')) !== ''
                    );

                // Exact Customer Master status contract:
                // Returned > Paid > Not Paid.
                // A partial cash payment remains Not Paid while balance is outstanding.
                $status = $hasReturn
                    ? 'Returned'
                    : (($hasPaymentRecord && $due <= 0.005) ? 'Paid' : 'Not Paid');

                $date = $order->created_at ?: ($order->waybill_date ?: $order->updated_at);

                return [
                    'sales_order_id' => (int) $order->id,
                    'source_type' => 'sales_order',
                    'source_id' => (int) $order->id,
                    'date' => $date ? substr((string) $date, 0, 10) : '---',
                    'code' => $invoiceDisplay,
                    'invoice_no' => $invoiceDisplay,
                    'module' => 'Sales Invoice',
                    'title' => $status,
                    'payment_status' => $status,
                    'payment_numbers' => (string) ($settlement->payment_numbers ?? ''),
                    'latest_payment_date' => !empty($settlement?->latest_payment_date)
                        ? substr((string) $settlement->latest_payment_date, 0, 10)
                        : '',
                    'debit' => round($amount, 2),
                    'paid' => round($paid, 2),
                    'return_amount' => round($returned, 2),
                    'adjustment' => round($effectiveAdjustment, 2),
                    'credit' => round($paid + $effectiveAdjustment, 2),
                    'balance' => round($due, 2),
                    'order_status' => (string) ($order->status ?? ''),
                ];
            })->values()->all();

            return response()->json([
                'success' => true,
                'customer_id' => (int) $customer->id,
                'customer_name' => $customerName,
                'status_values' => ['Paid', 'Not Paid', 'Returned'],
                'payment_source' => 'core4_accounting.process_payments + process_payment_invoices',
                'return_source' => 'core4_sales_order.sales_returns + sales_return_items',
                'ledger' => $ledger,
            ]);
        } catch (\Throwable $e) {
            Log::error('Customer Master payment history backend error.', [
                'customer_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Detailed movements for one Sales Order / invoice.
     */
    public function show(int $id, int $salesOrderId, PaymentHistoryService $service): JsonResponse
    {
        if (!$this->authorizedStaff()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $customer = DB::connection('masterlist')
                ->table('customers')
                ->where('id', $id)
                ->first(['id', 'name']);

            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found.',
                ], 404);
            }

            $customerName = trim((string) ($customer->name ?? ''));

            $orderQuery = DB::connection('sales')
                ->table('sales_orders as so')
                ->where('so.id', $salesOrderId);

            $this->applyCustomerOwnership(
                $orderQuery,
                (int) $customer->id,
                $customerName
            );

            $order = $orderQuery->first([
                'so.id',
                'so.order_number',
                'so.invoice_numbers',
                'so.total_amount',
                'so.status',
                'so.created_at',
                'so.updated_at',
                'so.waybill_date',
            ]);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sales invoice not found for this customer.',
                ], 404);
            }

            $invoiceNumbers = $this->invoiceNumbers(
                $order->invoice_numbers ?? null,
                $order->order_number ?? ''
            );
            $invoiceDisplay = $invoiceNumbers !== []
                ? implode(', ', $invoiceNumbers)
                : (string) ($order->order_number ?? '---');

            $invoiceDate = $order->created_at ?: ($order->waybill_date ?: $order->updated_at);
            $primaryInvoiceNo = $invoiceNumbers[0] ?? $invoiceDisplay;
            $items = $service->invoiceItems('sales_order', (int) $order->id, $primaryInvoiceNo);
            $rows = [];

            foreach ($items as $index => $item) {
                $rows[] = [
                    'invoice_no' => $invoiceDisplay,
                    'product_code' => (string) ($item['product_code'] ?? '---'),
                    'part_number' => (string) ($item['part_number'] ?? '---'),
                    'transaction' => 'INVOICE',
                    'transaction_no' => $invoiceDisplay,
                    'debit' => (float) ($item['total_amount'] ?? 0),
                    'credit' => 0.0,
                    'remarks' => (string) ($item['description'] ?? ''),
                    'date' => $invoiceDate ? substr((string) $invoiceDate, 0, 10) : '',
                    '_sort_at' => ($invoiceDate ?: '0000-00-00') . ' 00:00:00',
                    '_sort_id' => 1000 + $index,
                ];

                $returnedAmount = (float) ($item['returned_amount'] ?? 0);
                $returnedQty = (float) ($item['returned_qty'] ?? 0);

                if ($returnedAmount > 0.005 || $returnedQty > 0) {
                    $returnDate = (string) ($item['return_date'] ?? '');
                    $rows[] = [
                        'invoice_no' => $invoiceDisplay,
                        'product_code' => (string) ($item['product_code'] ?? '---'),
                        'part_number' => (string) ($item['part_number'] ?? '---'),
                        'transaction' => 'SALES RETURN',
                        'transaction_no' => (string) (($item['return_number'] ?? '') ?: '---'),
                        'debit' => 0.0,
                        'credit' => $returnedAmount,
                        'remarks' => $returnedQty > 0
                            ? 'Returned Qty: ' . rtrim(rtrim(number_format($returnedQty, 2, '.', ''), '0'), '.')
                            : '',
                        'date' => $returnDate,
                        '_sort_at' => ($returnDate ?: '9999-12-30') . ' 12:00:00',
                        '_sort_id' => 5000 + $index,
                    ];
                }
            }

            $payments = DB::connection('accounting')
                ->table('process_payment_invoices as ppi')
                ->join('process_payments as pp', 'pp.id', '=', 'ppi.process_payment_id')
                ->where('ppi.source_type', 'sales_order')
                ->where('ppi.source_id', (int) $order->id)
                ->whereRaw("UPPER(TRIM(COALESCE(pp.status, 'POSTED'))) = 'POSTED'")
                ->select(
                    'ppi.id',
                    'ppi.invoice_no',
                    'ppi.paid_amount',
                    'ppi.adjustment',
                    'ppi.remarks',
                    'pp.id as payment_id',
                    'pp.payment_no',
                    'pp.payment_date',
                    'pp.remarks as payment_remarks'
                )
                ->orderBy('pp.payment_date')
                ->orderBy('pp.id')
                ->orderBy('ppi.id')
                ->get();

            foreach ($payments as $payment) {
                $paymentDate = $payment->payment_date
                    ? substr((string) $payment->payment_date, 0, 10)
                    : '';

                $remarks = trim((string) (
                    ($payment->remarks ?? '') !== ''
                        ? $payment->remarks
                        : ($payment->payment_remarks ?? '')
                ));

                $rows[] = [
                    'invoice_no' => (string) (
                        ($payment->invoice_no ?? '') !== ''
                            ? $payment->invoice_no
                            : $invoiceDisplay
                    ),
                    'product_code' => 'PAYMENT',
                    'part_number' => '---',
                    'transaction' => 'PAID',
                    'transaction_no' => (string) ($payment->payment_no ?? '---'),
                    'debit' => 0.0,
                    'credit' => (float) ($payment->paid_amount ?? 0),
                    'remarks' => $remarks,
                    'date' => $paymentDate,
                    '_sort_at' => ($paymentDate ?: '9999-12-31') . ' 20:00:00',
                    '_sort_id' => 100000 + (int) $payment->id,
                ];

                $adjustment = (float) ($payment->adjustment ?? 0);
                if ($adjustment > 0.005) {
                    $rows[] = [
                        'invoice_no' => (string) (
                            ($payment->invoice_no ?? '') !== ''
                                ? $payment->invoice_no
                                : $invoiceDisplay
                        ),
                        'product_code' => 'ADJUSTMENT',
                        'part_number' => '---',
                        'transaction' => 'ADJUSTMENT',
                        'transaction_no' => (string) ($payment->payment_no ?? '---'),
                        'debit' => 0.0,
                        'credit' => $adjustment,
                        'remarks' => 'Payment adjustment',
                        'date' => $paymentDate,
                        '_sort_at' => ($paymentDate ?: '9999-12-31') . ' 21:00:00',
                        '_sort_id' => 200000 + (int) $payment->id,
                    ];
                }
            }

            usort($rows, function ($a, $b) {
                $cmp = strcmp((string) ($a['_sort_at'] ?? ''), (string) ($b['_sort_at'] ?? ''));
                if ($cmp !== 0) {
                    return $cmp;
                }

                return ((int) ($a['_sort_id'] ?? 0)) <=> ((int) ($b['_sort_id'] ?? 0));
            });

            $rows = array_map(function ($row) {
                unset($row['_sort_at'], $row['_sort_id']);
                return $row;
            }, $rows);

            $paid = (float) $payments->sum('paid_amount');
            $recordedAdjustment = (float) $payments->sum('adjustment');
            $returned = collect($items)->sum(fn ($item) => (float) ($item['returned_amount'] ?? 0));
            $hasReturn = collect($items)->contains(
                fn ($item) => (float) ($item['returned_amount'] ?? 0) > 0.005
                    || (float) ($item['returned_qty'] ?? 0) > 0
            );

            $effectiveAdjustment = max($returned, $recordedAdjustment);
            $amount = (float) ($order->total_amount ?? 0);
            $due = max($amount - $paid - $effectiveAdjustment, 0);

            $hasPaymentRecord = $payments->isNotEmpty()
                && ($paid > 0.005 || $recordedAdjustment > 0.005 || $payments->pluck('payment_no')->filter()->isNotEmpty());

            $status = $hasReturn
                ? 'Returned'
                : (($hasPaymentRecord && $due <= 0.005) ? 'Paid' : 'Not Paid');

            return response()->json([
                'success' => true,
                'invoice' => [
                    'sales_order_id' => (int) $order->id,
                    'invoice_no' => $invoiceDisplay,
                    'invoice_date' => $invoiceDate ? substr((string) $invoiceDate, 0, 10) : '---',
                    'invoice_amount' => round($amount, 2),
                    'paid_amount' => round($paid, 2),
                    'return_amount' => round($returned, 2),
                    'adjustment' => round($effectiveAdjustment, 2),
                    'balance' => round($due, 2),
                    'payment_status' => $status,
                ],
                'ledger' => $rows,
            ]);
        } catch (\Throwable $e) {
            Log::error('Customer Master invoice ledger backend error.', [
                'customer_id' => $id,
                'sales_order_id' => $salesOrderId,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function authorizedStaff(): bool
    {
        $user = session('user');
        if (!$user) {
            return false;
        }

        if (is_array($user)) {
            $user = (object) $user;
        }

        return in_array((int) ($user->account_type ?? 0), [1, 2, 3], true);
    }

    private function customerOrders(int $customerId, string $customerName): Collection
    {
        $query = DB::connection('sales')
            ->table('sales_orders as so')
            ->whereRaw("UPPER(TRIM(COALESCE(so.status, ''))) NOT IN ('CANCELLED', 'CANCELED', 'VOID')");

        $this->applyCustomerOwnership($query, $customerId, $customerName);

        return $query
            ->select(
                'so.id',
                'so.order_number',
                'so.invoice_numbers',
                'so.total_amount',
                'so.status',
                'so.created_at',
                'so.updated_at',
                'so.waybill_date'
            )
            ->orderByDesc('so.created_at')
            ->orderByDesc('so.id')
            ->get();
    }

    private function settlementsForOrders(Collection $orderIds): Collection
    {
        if ($orderIds->isEmpty()) {
            return collect();
        }

        return DB::connection('accounting')
            ->table('process_payment_invoices as ppi')
            ->join('process_payments as pp', 'pp.id', '=', 'ppi.process_payment_id')
            ->where('ppi.source_type', 'sales_order')
            ->whereIn('ppi.source_id', $orderIds->all())
            ->whereRaw("UPPER(TRIM(COALESCE(pp.status, 'POSTED'))) = 'POSTED'")
            ->select(
                'ppi.source_id',
                DB::raw('COALESCE(SUM(ppi.paid_amount), 0) as paid_total'),
                DB::raw('COALESCE(SUM(ppi.adjustment), 0) as adjustment_total'),
                DB::raw('MAX(pp.payment_date) as latest_payment_date'),
                DB::raw("GROUP_CONCAT(DISTINCT pp.payment_no ORDER BY pp.payment_date DESC, pp.id DESC SEPARATOR ', ') as payment_numbers")
            )
            ->groupBy('ppi.source_id')
            ->get()
            ->keyBy(fn ($row) => (int) $row->source_id);
    }

    /**
     * @return Collection<string, Collection<int, object>>
     */
    private function returnsForCustomer(int $customerId, string $customerName): Collection
    {
        $query = DB::connection('sales')
            ->table('sales_returns as sr')
            ->join('sales_return_items as sri', 'sri.sales_return_id', '=', 'sr.id')
            ->whereNotNull('sr.invoice_no')
            ->whereRaw("UPPER(TRIM(COALESCE(sr.status, ''))) NOT IN ('CANCELLED', 'CANCELED', 'VOID')")
            ->where(function ($q) use ($customerId, $customerName) {
                $q->where('sr.customer_id', $customerId);

                if ($customerName !== '') {
                    $q->orWhereRaw(
                        "UPPER(TRIM(COALESCE(sr.customer_name, ''))) = ?",
                        [mb_strtoupper($customerName)]
                    );
                }
            })
            ->select(
                'sr.id as sales_return_id',
                'sr.invoice_no',
                'sr.status',
                DB::raw('COALESCE(SUM(COALESCE(sri.return_amount, sri.subtotal, 0)), 0) as return_total')
            )
            ->groupBy('sr.id', 'sr.invoice_no', 'sr.status')
            ->get();

        $map = collect();

        foreach ($query as $row) {
            $keys = collect([$row->invoice_no])
                ->flatMap(function ($value) {
                    $raw = trim((string) $value);
                    if ($raw === '') {
                        return [];
                    }

                    $parts = preg_split('/[\s,\/]+/', $raw) ?: [];
                    $parts[] = $raw;

                    if (preg_match_all('/SN-\d+/i', $raw, $matches) && !empty($matches[0])) {
                        $parts = array_merge($parts, $matches[0]);
                    }

                    return $parts;
                })
                ->map(fn ($value) => $this->normalizeInvoiceKey($value))
                ->filter()
                ->unique();

            foreach ($keys as $key) {
                $rows = $map->get($key, collect());
                $rows->push($row);
                $map->put($key, $rows);
            }
        }

        return $map;
    }

    private function applyCustomerOwnership($query, int $customerId, string $customerName): void
    {
        $query->where(function ($q) use ($customerId, $customerName) {
            $q->where('so.customer_id', $customerId);

            if ($customerName !== '') {
                $q->orWhereRaw(
                    "UPPER(TRIM(COALESCE(so.customer_name, ''))) = ?",
                    [mb_strtoupper(trim($customerName))]
                );
            }
        });
    }

    private function invoiceNumbers($raw, string $fallback = ''): array
    {
        $raw = trim((string) ($raw ?? ''));
        $values = [];

        if ($raw !== '') {
            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                $values = $decoded;
            } elseif (is_string($decoded) && trim($decoded) !== '') {
                $values = [$decoded];
            } else {
                $values = preg_split('/[,\r\n]+/', $raw) ?: [];
            }
        }

        $values = collect($values)
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();

        if ($values === [] && trim($fallback) !== '') {
            $values = [trim($fallback)];
        }

        return $values;
    }

    private function normalizeInvoiceKey($value): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim((string) $value)) ?? '');
    }
}
