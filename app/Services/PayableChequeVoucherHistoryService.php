<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PayableChequeVoucherHistoryService
{
    public function history(Request $request): array
    {
        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, min(100, (int) $request->input('per_page', 50)));

        $invoiceSearch = trim((string) $request->input('invoice', ''));
        $filters = [
            'pcv' => trim((string) $request->input('pcv', '')),
            'supplier_name' => trim((string) $request->input('supplier_name', '')),
            'invoice_amount' => trim((string) $request->input('invoice_amount', '')),
            'return_total' => trim((string) $request->input('return_total', '')),
            'amount_paid' => trim((string) $request->input('amount_paid', '')),
            'remaining' => trim((string) $request->input('remaining', '')),
            'status' => trim((string) $request->input('status', '')),
            'date' => trim((string) $request->input('date', '')),
        ];

        $vouchersQuery = DB::connection('accounting')->table('payable_cheque_vouchers')
            ->where('is_draft', false);

        if ($invoiceSearch !== '') {
            $matchingVoucherIds = DB::connection('accounting')->table('payable_cheque_voucher_invoices')
                ->where('invoice_no', 'like', '%' . $invoiceSearch . '%')
                ->pluck('payable_cheque_voucher_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();
            $vouchersQuery->whereIn('id', $matchingVoucherIds);
        }

        $vouchers = $vouchersQuery
            ->orderByDesc('voucher_date')
            ->orderByDesc('id')
            ->get();

        if ($vouchers->isEmpty()) {
            return [
                'rows' => [], 'page' => $page, 'per_page' => $perPage,
                'total' => 0, 'last_page' => 1, 'from' => 0, 'to' => 0,
            ];
        }

        $voucherIds = $vouchers->pluck('id')->map(fn ($id) => (int) $id)->values();

        $invoiceTotals = DB::connection('accounting')->table('payable_cheque_voucher_invoices')
            ->whereIn('payable_cheque_voucher_id', $voucherIds)
            ->select(
                'payable_cheque_voucher_id',
                DB::raw('COUNT(*) as invoice_count'),
                DB::raw('COALESCE(SUM(invoice_amount), 0) as invoice_amount'),
                DB::raw('COALESCE(SUM(return_amount), 0) as return_total'),
                DB::raw('COALESCE(SUM(amount_paid), 0) as allocated_paid')
            )
            ->groupBy('payable_cheque_voucher_id')
            ->get()
            ->keyBy('payable_cheque_voucher_id');

        $paymentTotals = DB::connection('accounting')->table('payable_cheque_voucher_payments')
            ->whereIn('payable_cheque_voucher_id', $voucherIds)
            ->select(
                'payable_cheque_voucher_id',
                DB::raw('COUNT(*) as payment_count'),
                DB::raw('COALESCE(SUM(credit_amount), 0) as payment_total')
            )
            ->groupBy('payable_cheque_voucher_id')
            ->get()
            ->keyBy('payable_cheque_voucher_id');

        $rows = $vouchers->map(function ($voucher) use ($invoiceTotals, $paymentTotals) {
            $inv = $invoiceTotals->get($voucher->id);
            $pay = $paymentTotals->get($voucher->id);

            $invoiceAmount = round((float) ($inv->invoice_amount ?? 0), 2);
            $returnTotal = round((float) ($inv->return_total ?? 0), 2);
            $allocatedPaid = round((float) ($inv->allocated_paid ?? $voucher->total_paid ?? 0), 2);
            $paymentCount = (int) ($pay->payment_count ?? 0);
            $paymentTotal = round($paymentCount > 0 ? (float) $pay->payment_total : (float) ($voucher->total_paid ?? 0), 2);

            // This reconstructs the same "Computed Remaining" saved by the PCV flow:
            // invoice paid allocation + applied return, then deduct return + voucher discounts.
            $computedRemaining = max(0, $allocatedPaid
                - (float) ($voucher->additional_discount_amount ?? 0)
                - (float) ($voucher->global_discount_amount ?? 0));
            $remaining = max(0, round($computedRemaining - $paymentTotal, 2));

            if ($remaining <= 0.005) {
                $paymentStatus = 'Paid';
            } elseif ($paymentTotal > 0.005) {
                $paymentStatus = 'Partial';
            } else {
                $paymentStatus = 'Unpaid';
            }

            return [
                'id' => (int) $voucher->id,
                'pcv' => (string) $voucher->voucher_no,
                'voucher_no' => (string) $voucher->voucher_no,
                'supplier_id' => (int) $voucher->supplier_id,
                'supplier_name' => (string) $voucher->supplier_name,
                'invoice_amount' => $invoiceAmount,
                'return_total' => $returnTotal,
                'amount_paid' => $paymentTotal,
                'computed_remaining' => round($computedRemaining, 2),
                'remaining' => round($remaining, 2),
                'status' => $paymentStatus,
                'voucher_status' => (string) ($voucher->status ?? 'Posted'),
                'date' => $voucher->voucher_date ? substr((string) $voucher->voucher_date, 0, 10) : '',
                'voucher_date' => $voucher->voucher_date ? substr((string) $voucher->voucher_date, 0, 10) : '',
                'invoice_count' => (int) ($inv->invoice_count ?? 0),
            ];
        })->filter(function (array $row) use ($filters) {
            return $this->matches($row['pcv'], $filters['pcv'])
                && $this->matches($row['supplier_name'], $filters['supplier_name'])
                && $this->matchesAmount($row['invoice_amount'], $filters['invoice_amount'])
                && $this->matchesAmount($row['return_total'], $filters['return_total'])
                && $this->matchesAmount($row['amount_paid'], $filters['amount_paid'])
                && $this->matchesAmount($row['remaining'], $filters['remaining'])
                && $this->matches($row['status'], $filters['status'])
                && $this->matches($row['date'], $filters['date']);
        })->values();

        $total = $rows->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        if ($page > $lastPage) $page = $lastPage;
        $start = ($page - 1) * $perPage;
        $paged = $rows->slice($start, $perPage)->values()->all();

        return [
            'rows' => $paged,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $lastPage,
            'from' => $total ? $start + 1 : 0,
            'to' => $total ? min($start + $perPage, $total) : 0,
        ];
    }

    public function detail(int $voucherId): array
    {
        $voucher = DB::connection('accounting')->table('payable_cheque_vouchers')
            ->where('id', $voucherId)
            ->where('is_draft', false)
            ->first();
        if (!$voucher) {
            throw new \RuntimeException('Voucher not found.');
        }

        $supplier = DB::connection('masterlist')->table('suppliers')->where('id', $voucher->supplier_id)->first();
        $invoiceRows = DB::connection('accounting')->table('payable_cheque_voucher_invoices')
            ->where('payable_cheque_voucher_id', $voucherId)
            ->orderBy('id')
            ->get();

        $poIds = $invoiceRows->pluck('purchase_order_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $purchaseOrders = $poIds->isEmpty()
            ? collect()
            : DB::connection('purchase')->table('purchase_orders')
                ->whereIn('id', $poIds)
                ->get(['id', 'po_number', 'supplier_invoice_number', 'date', 'total_amount', 'actual_total_amount'])
                ->keyBy('id');

        $returnNumbers = $invoiceRows
            ->pluck('return_number')
            ->flatMap(fn ($value) => preg_split('/\s*,\s*/', (string) $value, -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        $purchaseReturnRows = ($poIds->isEmpty() || $returnNumbers->isEmpty())
            ? collect()
            : DB::connection('purchase')->table('purchase_returns')
                ->whereIn('po_id', $poIds)
                ->whereIn('return_number', $returnNumbers)
                ->get();

        $returnMetaByKey = $purchaseReturnRows->keyBy(
            fn ($row) => ((int) $row->po_id) . '|' . trim((string) $row->return_number)
        );

        $returnItemsByReturnId = $purchaseReturnRows->isEmpty()
            ? collect()
            : DB::connection('purchase')->table('purchase_return_items')
                ->whereIn('purchase_return_id', $purchaseReturnRows->pluck('id'))
                ->orderBy('id')
                ->get(['purchase_return_id', 'product_code', 'description', 'quantity'])
                ->groupBy('purchase_return_id');

        $buildReturnedItems = function (Collection $returnRows) use ($returnItemsByReturnId): string {
            $totals = [];
            foreach ($returnRows as $returnRow) {
                foreach ($returnItemsByReturnId->get($returnRow->id, collect()) as $item) {
                    $code = trim((string) ($item->product_code ?: $item->description ?: 'Item'));
                    $totals[$code] = ($totals[$code] ?? 0) + (float) ($item->quantity ?? 0);
                }
            }

            return collect($totals)
                ->map(fn ($qty, $code) => $code . (((float) $qty) != 0.0 ? ' (' . ((string) (float) $qty) . ')' : ''))
                ->values()
                ->implode(', ');
        };

        $invoices = $invoiceRows->map(function ($row) use ($purchaseOrders, $returnMetaByKey, $buildReturnedItems) {
            $po = $purchaseOrders->get((int) $row->purchase_order_id);
            $numbers = collect(preg_split('/\s*,\s*/', (string) ($row->return_number ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [])
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->unique()
                ->values();
            $matchedReturns = $numbers
                ->map(fn ($number) => $returnMetaByKey->get(((int) $row->purchase_order_id) . '|' . $number))
                ->filter()
                ->values();
            $slipNo = $matchedReturns
                ->map(fn ($ret) => property_exists($ret, 'slip_no') ? trim((string) ($ret->slip_no ?? '')) : '')
                ->filter()
                ->unique()
                ->implode(', ');
            $returnedItems = $buildReturnedItems($matchedReturns);

            return [
                'id' => (int) $row->id,
                'purchase_order_id' => $row->purchase_order_id ? (int) $row->purchase_order_id : null,
                'purchase_no' => (string) $row->purchase_no,
                'invoice_no' => (string) $row->invoice_no,
                'invoice_date' => $po?->date ? substr((string) $po->date, 0, 10) : '',
                'invoice_amount' => round((float) $row->invoice_amount, 2),
                'amount_due' => round((float) $row->amount_due, 2),
                'amount_paid' => round((float) $row->amount_paid, 2),
                'discount_1' => round((float) ($row->discount_1 ?? 0), 2),
                'discount_2' => round((float) ($row->discount_2 ?? 0), 2),
                'return_amount' => round((float) ($row->return_amount ?? 0), 2),
                'total_returns' => (int) ($row->total_returns ?? 0),
                'return_number' => (string) ($row->return_number ?? ''),
                'slip_no' => $slipNo,
                'returned_items' => $returnedItems,
                'rs_details' => (string) ($row->rs_details ?? ''),
                'remarks' => (string) ($row->remarks ?? ''),
                'payment_status' => (string) ($row->payment_status ?? ''),
            ];
        })->values()->all();

        $payments = DB::connection('accounting')->table('payable_cheque_voucher_payments')
            ->where('payable_cheque_voucher_id', $voucherId)
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'payment_method' => (string) $row->payment_method,
                'account_no' => (string) ($row->account_no ?? ''),
                'bank_name' => (string) ($row->bank_name ?? ''),
                'check_no' => (string) ($row->check_no ?? ''),
                'check_date' => $row->check_date ? substr((string) $row->check_date, 0, 10) : '',
                'payment_date' => $row->payment_date ? substr((string) $row->payment_date, 0, 10) : '',
                'reference_no' => (string) ($row->reference_no ?? ''),
                'credit_amount' => round((float) $row->credit_amount, 2),
            ])->values()->all();

        $suddenReturns = DB::connection('accounting')->table('payable_cheque_voucher_sudden_returns')
            ->where('payable_cheque_voucher_id', $voucherId)
            ->orderBy('id')
            ->get()
            ->map(function ($row) use ($returnMetaByKey, $buildReturnedItems) {
                $returnMeta = $returnMetaByKey->get(((int) ($row->purchase_order_id ?? 0)) . '|' . trim((string) $row->return_number));
                $matched = $returnMeta ? collect([$returnMeta]) : collect();

                return [
                    'id' => (int) $row->id,
                    'invoice_id' => $row->payable_cheque_voucher_invoice_id ? (int) $row->payable_cheque_voucher_invoice_id : null,
                    'po_id' => $row->purchase_order_id ? (int) $row->purchase_order_id : null,
                    'return_number' => (string) $row->return_number,
                    'slip_no' => $returnMeta && property_exists($returnMeta, 'slip_no') ? trim((string) ($returnMeta->slip_no ?? '')) : '',
                    'return_date' => $row->return_date ? substr((string) $row->return_date, 0, 10) : ($returnMeta?->date ? substr((string) $returnMeta->date, 0, 10) : ''),
                    'return_amount' => round((float) $row->return_amount, 2),
                    'returned_items' => $buildReturnedItems($matched),
                    'remarks' => (string) ($row->remarks ?? ''),
                ];
            })->values()->all();

        $invoiceAmount = collect($invoices)->sum('invoice_amount');
        $returnTotal = collect($invoices)->sum('return_amount');
        $allocatedPaid = collect($invoices)->sum('amount_paid');
        $paymentTotal = collect($payments)->sum('credit_amount');
        if (!$payments) $paymentTotal = (float) ($voucher->total_paid ?? 0);
        $computedRemaining = max(0, $allocatedPaid
            - (float) ($voucher->additional_discount_amount ?? 0)
            - (float) ($voucher->global_discount_amount ?? 0));
        $remaining = max(0, $computedRemaining - $paymentTotal);

        return [
            'voucher' => [
                'id' => (int) $voucher->id,
                'voucher_no' => (string) $voucher->voucher_no,
                'supplier_id' => (int) $voucher->supplier_id,
                'supplier_name' => (string) $voucher->supplier_name,
                'supplier_address' => (string) ($supplier->address ?? '---'),
                'voucher_date' => $voucher->voucher_date ? substr((string) $voucher->voucher_date, 0, 10) : '',
                'reference_no' => (string) ($voucher->reference_no ?? ''),
                'particulars' => (string) ($voucher->particulars ?? ''),
                'payment_method' => (string) ($voucher->payment_method ?? ''),
                'total_paid' => round((float) ($voucher->total_paid ?? 0), 2),
                'global_discount' => round((float) ($voucher->global_discount ?? 0), 2),
                'global_discount_amount' => round((float) ($voucher->global_discount_amount ?? 0), 2),
                'additional_discount' => round((float) ($voucher->additional_discount ?? 0), 2),
                'additional_discount_amount' => round((float) ($voucher->additional_discount_amount ?? 0), 2),
                'show_check_summary' => (bool) ($voucher->show_check_summary ?? false),
                'status' => (string) ($voucher->status ?? 'Posted'),
            ],
            'invoices' => $invoices,
            'payments' => $payments,
            'payment' => $payments[0] ?? null,
            'sudden_returns' => $suddenReturns,
            'totals' => [
                'invoice_amount' => round($invoiceAmount, 2),
                'return_total' => round($returnTotal, 2),
                'amount_paid' => round($paymentTotal, 2),
                'computed_remaining' => round($computedRemaining, 2),
                'remaining' => round($remaining, 2),
            ],
        ];
    }

    public function invoiceItems(int $voucherId, int $invoiceId): array
    {
        $voucher = DB::connection('accounting')->table('payable_cheque_vouchers')
            ->where('id', $voucherId)->where('is_draft', false)->first();
        if (!$voucher) throw new \RuntimeException('Voucher not found.');

        $invoice = DB::connection('accounting')->table('payable_cheque_voucher_invoices')
            ->where('id', $invoiceId)
            ->where('payable_cheque_voucher_id', $voucherId)
            ->first();
        if (!$invoice) throw new \RuntimeException('Invoice not found in this PCV.');
        if (!$invoice->purchase_order_id) return [];

        $poId = (int) $invoice->purchase_order_id;
        $poItems = DB::connection('purchase')->table('purchase_order_items')
            ->where('purchase_order_id', $poId)
            ->orderBy('id')
            ->get();

        $postedCosts = DB::connection('ledger')->table('supplier_ledgers as sl')
            ->join('supplier_ledger_items as sli', 'sli.supplier_ledger_id', '=', 'sl.id')
            ->where('sl.source_purchase_order_id', $poId)
            ->where('sl.module_type', 'Purchase Order')
            ->orderByDesc('sl.date')->orderByDesc('sl.id')->orderByDesc('sli.id')
            ->get([
                'sli.source_purchase_order_item_id', 'sli.product_id', 'sli.product_code', 'sli.unit',
                'sli.quantity', 'sli.actual_quantity', 'sli.unit_price', 'sli.subtotal',
            ]);
        $postedByItem = $postedCosts->filter(fn ($r) => $r->source_purchase_order_item_id)->unique('source_purchase_order_item_id')->keyBy('source_purchase_order_item_id');

        $returnNumbers = $this->pcvReturnNumbers($voucherId, $invoice);
        $returnsQuery = DB::connection('purchase')->table('purchase_returns')
            ->where('po_id', $poId)
            ->whereRaw("UPPER(TRIM(COALESCE(status, ''))) NOT IN ('CANCELLED','VOID')");
        if ($returnNumbers->isNotEmpty()) {
            $returnsQuery->whereIn('return_number', $returnNumbers);
        } elseif ($voucher->voucher_date) {
            $returnsQuery->whereDate('date', '<=', substr((string) $voucher->voucher_date, 0, 10));
        }
        $returnRows = $returnsQuery->get(['id', 'return_number', 'date']);

        $returnItems = $returnRows->isEmpty()
            ? collect()
            : DB::connection('purchase')->table('purchase_return_items')
                ->whereIn('purchase_return_id', $returnRows->pluck('id'))
                ->get();

        $returnByProduct = [];
        foreach ($returnItems as $item) {
            $key = $this->productKey($item->product_id ?? null, $item->product_code ?? '');
            if (!isset($returnByProduct[$key])) $returnByProduct[$key] = ['qty' => 0.0, 'amount' => 0.0];
            $returnByProduct[$key]['qty'] += (float) ($item->quantity ?? 0);
            $returnByProduct[$key]['amount'] += (float) ($item->subtotal ?? 0);
        }

        $productIds = $poItems->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $partNumbers = $productIds->isEmpty()
            ? collect()
            : DB::connection('masterlist')->table('products')->whereIn('id', $productIds)->pluck('part_number', 'id');

        return $poItems->map(function ($item) use ($postedByItem, $returnByProduct, $partNumbers) {
            $posted = $postedByItem->get($item->id);
            $productId = (int) ($item->product_id ?? 0);
            $productCode = (string) ($posted->product_code ?? $item->product_code ?? '---');
            $qty = (float) (($posted && (float) $posted->actual_quantity > 0) ? $posted->actual_quantity : (($item->actual_quantity ?? 0) > 0 ? $item->actual_quantity : ($posted->quantity ?? $item->quantity ?? 0)));
            $cost = round((float) ($posted->unit_price ?? $item->unit_price ?? 0), 2);
            $invoiceAmount = round((float) (($item->actual_subtotal ?? 0) > 0 ? $item->actual_subtotal : ($posted->subtotal ?? $item->subtotal ?? ($qty * $cost))), 2);
            $key = $this->productKey($productId, $productCode);
            $ret = $returnByProduct[$key] ?? ['qty' => 0.0, 'amount' => 0.0];
            $returnAmount = round((float) $ret['amount'], 2);

            return [
                'product_code' => $productCode,
                'part_number' => (string) ($partNumbers->get($productId) ?? '---'),
                'qty' => $qty,
                'unit' => (string) ($posted->unit ?? $item->unit ?? ''),
                'cost' => $cost,
                'return_qty' => (float) $ret['qty'],
                'return_amount' => $returnAmount,
                'invoice_amount' => $invoiceAmount,
                'total_amount' => round(max(0, $invoiceAmount - $returnAmount), 2),
            ];
        })->values()->all();
    }

    public function ledger(int $voucherId): array
    {
        $voucher = DB::connection('accounting')->table('payable_cheque_vouchers')
            ->where('id', $voucherId)->where('is_draft', false)->first();
        if (!$voucher) throw new \RuntimeException('Voucher not found.');

        $invoices = DB::connection('accounting')->table('payable_cheque_voucher_invoices')
            ->where('payable_cheque_voucher_id', $voucherId)->orderBy('id')->get();
        if ($invoices->isEmpty()) return [];

        $poIds = $invoices->pluck('purchase_order_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $purchaseOrders = $poIds->isEmpty() ? collect() : DB::connection('purchase')->table('purchase_orders')->whereIn('id', $poIds)->get()->keyBy('id');

        $purchaseLedgers = $poIds->isEmpty() ? collect() : DB::connection('ledger')->table('supplier_ledgers')
            ->where('supplier_id', $voucher->supplier_id)
            ->whereIn('source_purchase_order_id', $poIds)
            ->where('module_type', 'Purchase Order')
            ->orderBy('date')->orderBy('id')->get();
        $purchaseLedgerItems = $purchaseLedgers->isEmpty() ? collect() : DB::connection('ledger')->table('supplier_ledger_items')
            ->whereIn('supplier_ledger_id', $purchaseLedgers->pluck('id'))->orderBy('id')->get()->groupBy('supplier_ledger_id');

        $returns = $poIds->isEmpty() ? collect() : DB::connection('purchase')->table('purchase_returns')
            ->whereIn('po_id', $poIds)
            ->whereRaw("UPPER(TRIM(COALESCE(status, ''))) NOT IN ('CANCELLED','VOID')")
            ->when($voucher->voucher_date, fn ($q) => $q->whereDate('date', '<=', substr((string) $voucher->voucher_date, 0, 10)))
            ->orderBy('date')->orderBy('id')->get();

        $returnNumbers = $returns->pluck('return_number')->filter()->unique()->values();
        $returnLedgerRows = $returnNumbers->isEmpty() ? collect() : DB::connection('ledger')->table('supplier_ledgers')
            ->where('supplier_id', $voucher->supplier_id)
            ->where('module_type', 'Purchase Return')
            ->where(function ($q) use ($returnNumbers) {
                $q->whereIn('transaction_code', $returnNumbers)->orWhereIn('reference_no', $returnNumbers);
            })->get();
        $returnLedgers = collect();
        foreach ($returnLedgerRows as $returnLedger) {
            foreach ([$returnLedger->transaction_code ?? null, $returnLedger->reference_no ?? null] as $key) {
                $key = trim((string) $key);
                if ($key !== '' && !$returnLedgers->has($key)) $returnLedgers->put($key, $returnLedger);
            }
        }
        $returnLedgerItems = $returnLedgerRows->isEmpty() ? collect() : DB::connection('ledger')->table('supplier_ledger_items')
            ->whereIn('supplier_ledger_id', $returnLedgerRows->pluck('id'))->orderBy('id')->get()->groupBy('supplier_ledger_id');
        $purchaseReturnItems = $returns->isEmpty() ? collect() : DB::connection('purchase')->table('purchase_return_items')
            ->whereIn('purchase_return_id', $returns->pluck('id'))->orderBy('id')->get()->groupBy('purchase_return_id');

        $allProductIds = collect()
            ->merge($purchaseLedgerItems->flatten(1)->pluck('product_id'))
            ->merge($returnLedgerItems->flatten(1)->pluck('product_id'))
            ->merge($purchaseReturnItems->flatten(1)->pluck('product_id'))
            ->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $parts = $allProductIds->isEmpty() ? collect() : DB::connection('masterlist')->table('products')->whereIn('id', $allProductIds)->pluck('part_number', 'id');

        $payments = DB::connection('accounting')->table('payable_cheque_voucher_payments')
            ->where('payable_cheque_voucher_id', $voucherId)->orderBy('id')->get();
        $paymentDate = $payments->pluck('payment_date')->filter()->sort()->first() ?: substr((string) $voucher->voucher_date, 0, 10);
        $paymentMethods = $payments->pluck('payment_method')->filter()->unique()->implode(', ');

        $rows = [];
        foreach ($invoices as $invoice) {
            $poId = (int) ($invoice->purchase_order_id ?? 0);
            $po = $purchaseOrders->get($poId);
            $poLedgerRows = $purchaseLedgers->where('source_purchase_order_id', $poId);

            if ($poLedgerRows->isEmpty()) {
                $rows[] = $this->ledgerRow($invoice->invoice_no, '---', '---', 'SUPPLIER INVOICE', $po?->po_number ?: $invoice->purchase_no, 0, (float) $invoice->invoice_amount, 'Purchase Order', $po?->date ?: '');
            } else {
                foreach ($poLedgerRows as $ledger) {
                    $items = $purchaseLedgerItems->get($ledger->id, collect());
                    if ($items->isEmpty()) {
                        $rows[] = $this->ledgerRow($invoice->invoice_no, '---', '---', 'SUPPLIER INVOICE', $ledger->transaction_code, (float) $ledger->debit_amount, (float) $ledger->credit_amount, (string) $ledger->title, (string) $ledger->date);
                    } else {
                        foreach ($items as $item) {
                            $rows[] = $this->ledgerRow(
                                $invoice->invoice_no,
                                (string) ($item->product_code ?: '---'),
                                (string) ($parts->get((int) $item->product_id) ?? '---'),
                                'SUPPLIER INVOICE',
                                (string) $ledger->transaction_code,
                                0,
                                (float) ($item->subtotal ?? 0),
                                (string) $ledger->title,
                                (string) $ledger->date
                            );
                        }
                    }
                }
            }

            foreach ($returns->where('po_id', $poId) as $ret) {
                $ledger = $returnLedgers->get($ret->return_number);
                $items = $ledger ? $returnLedgerItems->get($ledger->id, collect()) : collect();
                if ($items->isEmpty()) $items = $purchaseReturnItems->get($ret->id, collect());
                if ($items->isEmpty()) {
                    $rows[] = $this->ledgerRow($invoice->invoice_no, '---', '---', 'PURCHASE RETURN', $ret->return_number, (float) ($ledger->debit_amount ?? $ret->total_amount ?? 0), 0, (string) ($ledger->title ?? $ret->remarks ?? ''), (string) ($ledger->date ?? $ret->date ?? ''));
                } else {
                    foreach ($items as $item) {
                        $amount = (float) ($item->subtotal ?? 0);
                        $rows[] = $this->ledgerRow(
                            $invoice->invoice_no,
                            (string) ($item->product_code ?: '---'),
                            (string) ($parts->get((int) ($item->product_id ?? 0)) ?? '---'),
                            'PURCHASE RETURN',
                            (string) $ret->return_number,
                            $amount,
                            0,
                            (string) ($ledger->title ?? $ret->remarks ?? ''),
                            (string) ($ledger->date ?? $ret->date ?? '')
                        );
                    }
                }
            }

            $rows[] = $this->ledgerRow(
                $invoice->invoice_no,
                'PAYMENT',
                '---',
                'PAID',
                (string) $voucher->voucher_no,
                round((float) ($invoice->amount_paid ?? 0), 2),
                0,
                $paymentMethods !== '' ? 'Payment via ' . $paymentMethods : 'PCV Payment',
                (string) $paymentDate
            );
        }

        usort($rows, function ($a, $b) {
            $dateCompare = strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? ''));
            if ($dateCompare !== 0) return $dateCompare;
            $order = ['SUPPLIER INVOICE' => 1, 'PURCHASE RETURN' => 2, 'PAID' => 3];
            return ($order[$a['transaction']] ?? 9) <=> ($order[$b['transaction']] ?? 9);
        });

        return $rows;
    }

    private function ledgerRow(string $invoiceNo, string $productCode, string $partNumber, string $transaction, string $transactionNo, float $debit, float $credit, string $remarks, string $date): array
    {
        return [
            'invoice_no' => $invoiceNo,
            'product_code' => $productCode,
            'part_number' => $partNumber,
            'transaction' => $transaction,
            'transaction_no' => $transactionNo,
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
            'remarks' => $remarks,
            'date' => $date ? substr($date, 0, 10) : '',
        ];
    }

    private function pcvReturnNumbers(int $voucherId, object $invoice): Collection
    {
        $numbers = DB::connection('accounting')->table('payable_cheque_voucher_sudden_returns')
            ->where('payable_cheque_voucher_id', $voucherId)
            ->where(function ($q) use ($invoice) {
                $q->where('payable_cheque_voucher_invoice_id', $invoice->id);
                if ($invoice->purchase_order_id) $q->orWhere('purchase_order_id', $invoice->purchase_order_id);
            })
            ->pluck('return_number')
            ->map(fn ($value) => trim((string) $value))
            ->filter();

        foreach (preg_split('/\s*,\s*/', (string) ($invoice->return_number ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $value) {
            $numbers->push(trim($value));
        }

        return $numbers->filter()->unique()->values();
    }

    private function productKey(mixed $productId, string $productCode): string
    {
        $id = (int) ($productId ?? 0);
        if ($id > 0) return 'id:' . $id;
        return 'code:' . strtoupper(preg_replace('/\s+/', '', trim($productCode)));
    }

    private function matches(mixed $value, string $filter): bool
    {
        if ($filter === '') return true;
        return mb_stripos((string) $value, $filter) !== false;
    }

    private function matchesAmount(float $value, string $filter): bool
    {
        if ($filter === '') return true;
        $normalized = str_replace([',', 'PHP', 'php', '₱', ' '], '', $filter);
        $raw = number_format($value, 2, '.', '');
        $formatted = number_format($value, 2, '.', ',');
        return str_contains($raw, $normalized) || str_contains($formatted, $filter);
    }
}
