<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentHistoryService
{
    public function payors(Request $request): array
    {
        $search = trim((string) $request->input('search', ''));
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 50;

        $query = DB::connection('accounting')->table('process_payments as pp')
            ->leftJoin('process_payment_invoices as ppi', 'ppi.process_payment_id', '=', 'pp.id')
            ->select(
                'pp.customer_id',
                DB::raw('MAX(pp.customer_name) as customer_name'),
                DB::raw('COUNT(ppi.id) as paid_invoices'),
                DB::raw('MAX(pp.payment_date) as latest_paid_date')
            );

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('pp.customer_name', 'like', '%' . $search . '%')
                    ->orWhere('pp.payment_no', 'like', '%' . $search . '%')
                    ->orWhere('pp.payment_date', 'like', '%' . $search . '%')
                    ->orWhereExists(function ($sub) use ($search) {
                        $sub->select(DB::raw(1))
                            ->from('process_payment_invoices as ppi2')
                            ->whereColumn('ppi2.process_payment_id', 'pp.id')
                            ->where('ppi2.invoice_no', 'like', '%' . $search . '%');
                    });
            });
        }

        $total = (clone $query)->count(DB::raw('DISTINCT pp.customer_id'));
        $rows = $query->groupBy('pp.customer_id')
            ->orderByDesc('latest_paid_date')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn ($row) => [
                'customer_id' => $row->customer_id,
                'customer_name' => $row->customer_name ?: '---',
                'paid_invoices' => (int) $row->paid_invoices,
                'latest_paid_date' => $row->latest_paid_date ?: '---',
            ])
            ->values()
            ->all();

        return [
            'rows' => $rows,
            'page' => $page,
            'per_page' => $perPage,
            'total' => (int) $total,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function payorInvoices(Request $request, int $customerId): array
    {
        // Legacy method name retained for route compatibility. The payload is now
        // one row per Payment No. for the selected Payor.
        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, min(100, (int) $request->input('per_page', 50)));
        $filters = [
            'payment_no' => trim((string) $request->input('payment_no', '')),
            'invoices' => trim((string) $request->input('invoices', '')),
            'payment_date' => trim((string) $request->input('payment_date', '')),
            'amount' => trim((string) $request->input('amount', '')),
        ];

        $query = DB::connection('accounting')->table('process_payments as pp')
            ->where('pp.customer_id', $customerId);

        if ($filters['payment_no'] !== '') {
            $query->where('pp.payment_no', 'like', '%' . $filters['payment_no'] . '%');
        }
        if ($filters['invoices'] !== '') {
            $invoiceSearch = $filters['invoices'];
            $query->whereExists(function ($sub) use ($invoiceSearch) {
                $sub->select(DB::raw(1))
                    ->from('process_payment_invoices as ppi_filter')
                    ->whereColumn('ppi_filter.process_payment_id', 'pp.id')
                    ->where('ppi_filter.invoice_no', 'like', '%' . $invoiceSearch . '%');
            });
        }
        if ($filters['payment_date'] !== '') {
            $query->where('pp.payment_date', 'like', '%' . $filters['payment_date'] . '%');
        }
        if ($filters['amount'] !== '') {
            $amount = str_replace([',', 'PHP', 'php', '₱', ' '], '', $filters['amount']);
            $query->whereRaw('CAST(pp.total_paid AS CHAR) LIKE ?', ['%' . $amount . '%']);
        }

        $total = (clone $query)->count();
        $payments = $query
            ->select('pp.id', 'pp.payment_no', 'pp.payment_date', 'pp.total_paid', 'pp.customer_name')
            ->orderByDesc('pp.payment_date')
            ->orderByDesc('pp.id')
            ->forPage($page, $perPage)
            ->get();

        $paymentIds = $payments->pluck('id')->map(fn ($id) => (int) $id)->values();
        $invoiceGroups = $paymentIds->isEmpty()
            ? collect()
            : DB::connection('accounting')->table('process_payment_invoices')
                ->whereIn('process_payment_id', $paymentIds)
                ->orderBy('id')
                ->get(['id', 'process_payment_id', 'invoice_no', 'paid_amount', 'remarks'])
                ->groupBy('process_payment_id');

        $rows = $payments->map(function ($payment) use ($invoiceGroups) {
            $invoiceRows = $invoiceGroups->get((int) $payment->id, collect());
            $invoiceNumbers = $invoiceRows
                ->pluck('invoice_no')
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->unique()
                ->values();

            return [
                'id' => (int) $payment->id,
                'process_payment_id' => (int) $payment->id,
                'payment_no' => (string) ($payment->payment_no ?? ''),
                'payment_date' => $payment->payment_date ? substr((string) $payment->payment_date, 0, 10) : '---',
                'total_paid' => (float) ($payment->total_paid ?? 0),
                'invoice_numbers' => $invoiceNumbers->all(),
                'invoice_count' => $invoiceNumbers->count(),
                'invoice_trail' => $invoiceRows->map(fn ($invoice) => [
                    'invoice_no' => (string) ($invoice->invoice_no ?? ''),
                    'paid_amount' => (float) ($invoice->paid_amount ?? 0),
                    'remarks' => (string) ($invoice->remarks ?? ''),
                ])->values()->all(),
            ];
        })->values()->all();

        $lastPage = max(1, (int) ceil($total / $perPage));
        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to = $total > 0 ? min($page * $perPage, $total) : 0;

        return [
            'success' => true,
            'data' => $rows,
            'rows' => $rows,
            'page' => $page,
            'per_page' => $perPage,
            'total' => (int) $total,
            'last_page' => $lastPage,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => (int) $total,
                'last_page' => $lastPage,
                'from' => $from,
                'to' => $to,
            ],
        ];
    }

    public function invoiceItems(string $sourceType, int $sourceId, ?string $invoiceNo = null): array
    {
        $invoiceNo = trim((string) ($invoiceNo ?? ''));
        $sourceRows = collect();

        if ($sourceType === 'sales_order') {
            $sourceRows = DB::connection('sales')->table('sales_order_items')
                ->where('sales_order_id', $sourceId)
                ->orderBy('id')
                ->get([
                    'product_id', 'product_code', 'description', 'quantity as qty', 'oum as unit',
                    'unit_price', 'discount', 'additional_discount', 'subtotal as total_amount',
                ]);
        } elseif ($sourceType === 'consignment_invoice') {
            $sourceRows = DB::connection('sales')->table('consignment_invoice_items')
                ->where('consignment_invoice_id', $sourceId)
                ->orderBy('id')
                ->get([
                    'product_id', 'product_code', 'description', 'quantity as qty', 'oum as unit',
                    'unit_price', 'discount', 'additional_discount', 'subtotal as total_amount',
                ]);
        } elseif ($sourceType === 'online_report') {
            // Prefer the finalized ONL Sales Order. It contains the actual invoice
            // item price/discount values and is safer than stale Online Report notes.
            $onlineOrder = $this->findOnlineSalesOrder($sourceId, $invoiceNo);
            if ($onlineOrder) {
                $sourceRows = DB::connection('sales')->table('sales_order_items')
                    ->where('sales_order_id', (int) $onlineOrder->id)
                    ->orderBy('id')
                    ->get([
                        'product_id', 'product_code', 'description', 'quantity as qty', 'oum as unit',
                        'unit_price', 'discount', 'additional_discount', 'subtotal as total_amount',
                    ]);
            } else {
                $report = DB::connection('sales')->table('online_reports')
                    ->select('id', 'invoice_numbers', 'notes_data', 'counter_parts')
                    ->where('id', $sourceId)
                    ->first();

                if ($report) {
                    $invoiceNumbers = json_decode($report->invoice_numbers ?? '[]', true);
                    $notesData = json_decode($report->notes_data ?? '[]', true);
                    $counterParts = json_decode($report->counter_parts ?? '[]', true);
                    if (!is_array($invoiceNumbers)) $invoiceNumbers = [];
                    if (!is_array($notesData)) $notesData = [];
                    if (!is_array($counterParts)) $counterParts = [];

                    $entry = null;
                    $entryIndex = null;
                    $target = $this->normalizeInvoiceNo($invoiceNo);
                    foreach ($invoiceNumbers as $i => $value) {
                        if ($target !== '' && $this->normalizeInvoiceNo($value) === $target) {
                            $entry = $notesData[$i] ?? null;
                            $entryIndex = $i;
                            break;
                        }
                    }
                    if ($entry === null && count($notesData) === 1 && is_array($notesData[0])) {
                        $entry = $notesData[0];
                        $entryIndex = 0;
                    }

                    if (is_array($entry) && isset($entry['items']) && is_array($entry['items'])) {
                        $sourceRows = collect($entry['items'])->map(function ($item) use ($counterParts, $entryIndex) {
                            $item = is_array($item) ? $item : [];
                            $counterKey = ($entryIndex ?? 0) . '-' . ($item['id'] ?? '');
                            $counter = isset($counterParts[$counterKey]) && is_array($counterParts[$counterKey])
                                ? $counterParts[$counterKey]
                                : [];
                            return (object) [
                                'product_id' => (int) ($item['product_id'] ?? 0),
                                'product_code' => (string) ($item['product_code'] ?? $counter['product_code'] ?? ''),
                                'description' => (string) ($item['description'] ?? $counter['description'] ?? ''),
                                'qty' => (float) ($item['quantity'] ?? 0),
                                'unit' => (string) ($item['oum'] ?? ''),
                                'unit_price' => (float) ($item['unit_price'] ?? 0),
                                'discount' => (float) ($item['discount'] ?? 0),
                                'additional_discount' => (float) ($item['additional_discount'] ?? 0),
                                'total_amount' => (float) ($item['subtotal'] ?? 0),
                                '_counter_part_number' => (string) ($counter['part_number'] ?? ''),
                            ];
                        });
                    }
                }
            }
        }

        if ($sourceRows->isEmpty()) {
            return [];
        }

        $productIds = $sourceRows->pluck('product_id')->filter(fn ($id) => (int) $id > 0)->map('intval')->unique()->values();
        $productCodes = $sourceRows->pluck('product_code')->filter()->map(fn ($code) => trim((string) $code))->unique()->values();
        $productsById = $productIds->isEmpty()
            ? collect()
            : DB::connection('masterlist')->table('products')->whereIn('id', $productIds)->get(['id', 'product_code', 'part_number'])->keyBy('id');
        $productsByCode = $productCodes->isEmpty()
            ? collect()
            : DB::connection('masterlist')->table('products')->whereIn('product_code', $productCodes)->get(['id', 'product_code', 'part_number'])->keyBy(fn ($row) => strtoupper(trim((string) $row->product_code)));

        $returnMap = $this->returnItemsForInvoice($invoiceNo);

        return $sourceRows->map(function ($item) use ($invoiceNo, $productsById, $productsByCode, $returnMap) {
            $productId = (int) ($item->product_id ?? 0);
            $productCode = trim((string) ($item->product_code ?? ''));
            $product = $productId > 0 ? $productsById->get($productId) : null;
            if (!$product && $productCode !== '') {
                $product = $productsByCode->get(strtoupper($productCode));
            }
            $partNumber = trim((string) ($product->part_number ?? ($item->_counter_part_number ?? '')));

            $returnKey = $productId > 0 ? 'id:' . $productId : 'code:' . strtoupper(preg_replace('/\s+/', '', $productCode));
            $returned = $returnMap[$returnKey] ?? ['qty' => 0.0, 'amount' => 0.0, 'numbers' => [], 'dates' => []];
            $totalAmount = round((float) ($item->total_amount ?? 0), 2);
            $returnAmount = round((float) ($returned['amount'] ?? 0), 2);

            return [
                'invoice_no' => $invoiceNo !== '' ? $invoiceNo : '---',
                'product_id' => $productId,
                'product_code' => $productCode !== '' ? $productCode : '---',
                'part_number' => $partNumber !== '' ? $partNumber : '---',
                'description' => (string) ($item->description ?? ''),
                'unit' => (string) ($item->unit ?? ''),
                'qty' => (float) ($item->qty ?? 0),
                'unit_price' => round((float) ($item->unit_price ?? 0), 2),
                'discount' => (float) ($item->discount ?? 0),
                'additional_discount' => (float) ($item->additional_discount ?? 0),
                'returned_qty' => (float) ($returned['qty'] ?? 0),
                'returned_amount' => $returnAmount,
                'return_number' => implode(', ', array_values(array_unique($returned['numbers'] ?? []))),
                'return_date' => collect($returned['dates'] ?? [])->filter()->sort()->first() ?: '',
                'total_amount' => $totalAmount,
                'final_amount' => round(max($totalAmount - $returnAmount, 0), 2),
                // Backward-compatible keys used by older item popups.
                'total' => $totalAmount,
            ];
        })->values()->all();
    }

    public function paymentLedger(int $paymentId): array
    {
        $payment = DB::connection('accounting')->table('process_payments')->where('id', $paymentId)->first();
        if (!$payment) {
            return [];
        }

        $invoiceRows = DB::connection('accounting')->table('process_payment_invoices')
            ->where('process_payment_id', $paymentId)
            ->orderBy('id')
            ->get();

        $rows = [];
        foreach ($invoiceRows as $invoice) {
            $invoiceDate = $this->sourceInvoiceCreatedAt((string) $invoice->source_type, (int) $invoice->source_id, (string) $invoice->invoice_no);
            $items = $this->invoiceItems((string) $invoice->source_type, (int) $invoice->source_id, (string) $invoice->invoice_no);

            foreach ($items as $index => $item) {
                $rows[] = [
                    'product_code' => $item['product_code'] ?? '---',
                    'part_number' => $item['part_number'] ?? '---',
                    'invoice_no' => (string) $invoice->invoice_no,
                    'transaction' => 'INVOICE',
                    'transaction_no' => (string) $invoice->invoice_no,
                    'debit' => (float) ($item['total_amount'] ?? 0),
                    'credit' => 0.0,
                    'remarks' => '',
                    'date' => $invoiceDate,
                    '_sort_at' => ($invoiceDate ?: '0000-00-00') . ' 00:00:00',
                    '_sort_id' => ((int) $invoice->id * 10000) + $index,
                ];

                if ((float) ($item['returned_qty'] ?? 0) > 0 || (float) ($item['returned_amount'] ?? 0) > 0) {
                    $returnDate = (string) ($item['return_date'] ?? '');
                    $rows[] = [
                        'product_code' => $item['product_code'] ?? '---',
                        'part_number' => $item['part_number'] ?? '---',
                        'invoice_no' => (string) $invoice->invoice_no,
                        'transaction' => 'SALES RETURN',
                        'transaction_no' => (string) (($item['return_number'] ?? '') ?: '---'),
                        'debit' => 0.0,
                        'credit' => (float) ($item['returned_amount'] ?? 0),
                        'remarks' => '',
                        'date' => $returnDate,
                        '_sort_at' => ($returnDate ?: '9999-12-30') . ' 12:00:00',
                        '_sort_id' => ((int) $invoice->id * 10000) + 5000 + $index,
                    ];
                }
            }

            if ((float) $invoice->paid_amount > 0) {
                $paymentDate = $payment->payment_date ? substr((string) $payment->payment_date, 0, 10) : '';
                $rows[] = [
                    'product_code' => 'PAYMENT',
                    'part_number' => '---',
                    // Keep the invoice identity on the PAID movement so a user
                    // investigating one invoice can see its payment and return together.
                    'invoice_no' => (string) $invoice->invoice_no,
                    'transaction' => 'PAID',
                    'transaction_no' => (string) $payment->payment_no,
                    'debit' => 0.0,
                    'credit' => (float) $invoice->paid_amount,
                    'remarks' => (string) (($invoice->remarks ?? '') !== '' ? $invoice->remarks : ($payment->remarks ?? '')),
                    'date' => $paymentDate,
                    '_sort_at' => ($paymentDate ?: '9999-12-31') . ' 23:59:59',
                    '_sort_id' => ((int) $invoice->id * 10000) + 9999,
                ];
            }
        }

        usort($rows, function ($a, $b) {
            $cmp = strcmp((string) ($a['_sort_at'] ?? ''), (string) ($b['_sort_at'] ?? ''));
            if ($cmp !== 0) return $cmp;
            return ((int) ($a['_sort_id'] ?? 0)) <=> ((int) ($b['_sort_id'] ?? 0));
        });

        return array_map(function ($row) {
            unset($row['_sort_at'], $row['_sort_id']);
            return $row;
        }, $rows);
    }

    private function findOnlineSalesOrder(int $reportId, string $invoiceNo): ?object
    {
        $target = $this->normalizeInvoiceNo($invoiceNo);
        if ($target === '') return null;

        $orders = DB::connection('sales')->table('sales_orders')
            ->where('order_number', 'LIKE', 'ONL-' . $reportId . '-%')
            ->whereIn('status', ['Confirmed', 'Closed'])
            ->orderByDesc('id')
            ->get(['id', 'invoice_numbers', 'created_at']);

        foreach ($orders as $order) {
            $values = json_decode((string) ($order->invoice_numbers ?? ''), true);
            if (!is_array($values)) {
                $values = preg_split('/[,\n\r]+/', (string) ($order->invoice_numbers ?? '')) ?: [];
            }
            foreach ($values as $value) {
                if ($this->normalizeInvoiceNo($value) === $target) return $order;
            }
        }
        return null;
    }

    private function returnItemsForInvoice(string $invoiceNo): array
    {
        $target = $this->normalizeInvoiceNo($invoiceNo);
        if ($target === '') return [];

        $rows = DB::connection('sales')->table('sales_returns as sr')
            ->join('sales_return_items as sri', 'sri.sales_return_id', '=', 'sr.id')
            ->whereNotNull('sr.invoice_no')
            ->whereRaw("UPPER(TRIM(COALESCE(sr.status, ''))) NOT IN ('CANCELLED', 'VOID')")
            ->where('sr.invoice_no', 'like', '%' . trim($invoiceNo) . '%')
            ->select(
                'sr.invoice_no', 'sr.return_number', 'sr.created_at',
                'sri.product_id', 'sri.product_code', 'sri.quantity', 'sri.return_amount', 'sri.subtotal'
            )
            ->get();

        $map = [];
        foreach ($rows as $row) {
            if ($this->normalizeInvoiceNo($row->invoice_no) !== $target) continue;
            $key = (int) ($row->product_id ?? 0) > 0
                ? 'id:' . (int) $row->product_id
                : 'code:' . strtoupper(preg_replace('/\s+/', '', trim((string) $row->product_code)));
            if (!isset($map[$key])) {
                $map[$key] = ['qty' => 0.0, 'amount' => 0.0, 'numbers' => [], 'dates' => []];
            }
            $map[$key]['qty'] += (float) ($row->quantity ?? 0);
            $map[$key]['amount'] += (float) (($row->return_amount ?? null) ?? ($row->subtotal ?? 0));
            if (!empty($row->return_number)) $map[$key]['numbers'][] = (string) $row->return_number;
            if (!empty($row->created_at)) $map[$key]['dates'][] = substr((string) $row->created_at, 0, 10);
        }
        return $map;
    }

    private function sourceInvoiceCreatedAt(string $sourceType, int $sourceId, string $invoiceNo): string
    {
        $value = null;
        if ($sourceType === 'sales_order') {
            $value = DB::connection('sales')->table('sales_orders')->where('id', $sourceId)->value('created_at');
        } elseif ($sourceType === 'consignment_invoice') {
            $value = DB::connection('sales')->table('consignment_invoices')->where('id', $sourceId)->value('created_at');
        } elseif ($sourceType === 'online_report') {
            $order = $this->findOnlineSalesOrder($sourceId, $invoiceNo);
            $value = $order?->created_at;
            if (!$value) {
                $value = DB::connection('sales')->table('online_reports')->where('id', $sourceId)->value('created_at');
            }
        }
        return $value ? substr((string) $value, 0, 10) : '';
    }

    private function normalizeInvoiceNo(mixed $value): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim((string) $value)));
    }

    private function parseNoteIds(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $ids = json_decode($raw, true);
        if (is_array($ids)) {
            return array_values(array_filter(array_map('intval', $ids)));
        }

        return array_values(array_filter(array_map('intval', preg_split('/[,\s]+/', $raw))));
    }
}
