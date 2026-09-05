<?php

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SupplierComprehensiveProfileService
{
    public function getPurchasedItems(int $supplierId, Request $request): array
    {
        $supplier = $this->visibleSupplier($supplierId);

        $allowedFilters = [
            'transaction_date' => 'sl.date',
            'transaction_code' => 'sl.transaction_code',
            'reference_no' => 'sl.reference_no',
            'product_code' => 'sli.product_code',
            'description' => 'sli.description',
            'unit' => 'sli.unit',
            'quantity' => 'sli.quantity',
            'actual_quantity' => 'sli.actual_quantity',
            'unit_price' => 'sli.unit_price',
            'discount_percent' => 'sli.discount_percent',
            'discount_amount' => 'sli.discount_amount',
            'subtotal' => 'sli.subtotal',
            'transaction_title' => 'sl.title',
        ];

        $filters = $this->filters($request);
        $buildBaseQuery = function () use ($supplierId, $allowedFilters, $filters) {
            $query = DB::connection('ledger')
                ->table('supplier_ledger_items as sli')
                ->join('supplier_ledgers as sl', 'sl.id', '=', 'sli.supplier_ledger_id')
                ->where('sl.supplier_id', $supplierId)
                ->where('sl.module_type', 'Purchase Order');

            foreach ($allowedFilters as $field => $column) {
                $value = $filters[$field] ?? null;
                if ($value === null || trim((string) $value) === '') {
                    continue;
                }

                $query->where($column, 'LIKE', '%' . $this->escapeLike($value) . '%');
            }

            return $query;
        };

        $summary = $buildBaseQuery()
            ->selectRaw('
                COUNT(DISTINCT sli.supplier_ledger_id) as purchase_transactions,
                COUNT(sli.id) as item_rows,
                COALESCE(SUM(sli.quantity), 0) as total_ordered_quantity,
                COALESCE(SUM(sli.actual_quantity), 0) as total_received_quantity,
                COALESCE(SUM(sli.subtotal), 0) as total_purchase_amount
            ')
            ->first();

        $query = $buildBaseQuery()->select([
            'sli.id',
            'sli.supplier_ledger_id',
            'sli.product_id',
            'sli.product_code',
            'sli.unit',
            'sli.description',
            'sli.quantity',
            'sli.actual_quantity',
            'sli.unit_price',
            'sli.discount_percent',
            'sli.discount_amount',
            'sli.subtotal',
            'sli.created_at',
            'sli.updated_at',
            'sl.date as transaction_date',
            'sl.transaction_code',
            'sl.reference_no',
            'sl.title as transaction_title',
            'sl.credit_amount as ledger_credit_amount',
            'sl.created_at as ledger_created_at',
        ]);

        $allowedSorts = $allowedFilters;
        $sort = (string) $request->query('sort', '');
        $direction = $this->sortDirection($request);

        if (array_key_exists($sort, $allowedSorts)) {
            $query->orderBy($allowedSorts[$sort], $direction)->orderBy('sli.id', 'desc');
        } else {
            $query->orderBy('sl.date', 'desc')
                ->orderBy('sl.created_at', 'desc')
                ->orderBy('sli.id', 'desc');
        }

        $paginator = $query->paginate(25, ['*'], 'page', $this->page($request));

        return [
            'success' => true,
            'supplier' => [
                'id' => (int) $supplier->id,
                'name' => (string) ($supplier->name ?? ''),
            ],
            'summary' => [
                'purchase_transactions' => (int) ($summary->purchase_transactions ?? 0),
                'item_rows' => (int) ($summary->item_rows ?? 0),
                'total_ordered_quantity' => (float) ($summary->total_ordered_quantity ?? 0),
                'total_received_quantity' => (float) ($summary->total_received_quantity ?? 0),
                'total_purchase_amount' => (float) ($summary->total_purchase_amount ?? 0),
            ],
            'items' => collect($paginator->items())->map(fn ($item) => [
                'id' => (int) $item->id,
                'supplier_ledger_id' => (int) $item->supplier_ledger_id,
                'transaction_date' => $item->transaction_date ? (string) $item->transaction_date : '',
                'transaction_code' => (string) ($item->transaction_code ?? ''),
                'reference_no' => (string) ($item->reference_no ?? ''),
                'transaction_title' => (string) ($item->transaction_title ?? ''),
                'product_id' => $item->product_id !== null ? (int) $item->product_id : null,
                'product_code' => (string) ($item->product_code ?? ''),
                'unit' => (string) ($item->unit ?? ''),
                'description' => (string) ($item->description ?? ''),
                'quantity' => (float) ($item->quantity ?? 0),
                'actual_quantity' => (float) ($item->actual_quantity ?? 0),
                'unit_price' => (float) ($item->unit_price ?? 0),
                'discount_percent' => (float) ($item->discount_percent ?? 0),
                'discount_amount' => (float) ($item->discount_amount ?? 0),
                'subtotal' => (float) ($item->subtotal ?? 0),
                'created_at' => $item->created_at ? (string) $item->created_at : '',
                'updated_at' => $item->updated_at ? (string) $item->updated_at : '',
            ])->values(),
            'pagination' => $this->pagination($paginator),
        ];
    }

    public function getUnregisteredPurchaseOrders(int $supplierId, Request $request): array
    {
        $supplier = $this->visibleSupplier($supplierId);
        $filters = $this->filters($request);

        // Accounting data is collected on the accounting connection first.
        // HostForge runs accounting and purchase on separate MariaDB services,
        // so none of the purchase SQL below references accounting tables.
        $paidByPo = collect();
        $suddenReturnByPo = collect();
        $registeredKeys = [];
        $usedReturnKeys = [];

        if (Schema::connection('accounting')->hasTable('payable_cheque_voucher_invoices')) {
            $paidByPo = DB::connection('accounting')->table('payable_cheque_voucher_invoices')
                ->whereNotNull('purchase_order_id')
                ->select('purchase_order_id', DB::raw('SUM(amount_paid + COALESCE(amount_due * discount_1 / 100, 0) + COALESCE(amount_due * discount_2 / 100, 0)) AS paid_total'))
                ->groupBy('purchase_order_id')
                ->pluck('paid_total', 'purchase_order_id');

            DB::connection('accounting')->table('payable_cheque_voucher_invoices')
                ->whereNotNull('purchase_order_id')
                ->whereNotNull('return_number')
                ->where('return_number', '!=', '')
                ->get(['purchase_order_id', 'return_number'])
                ->each(function ($row) use (&$usedReturnKeys) {
                    foreach (preg_split('/\s*,\s*/', (string) $row->return_number, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $number) {
                        $usedReturnKeys[(int) $row->purchase_order_id . '|' . $this->normalizeKey($number)] = true;
                    }
                });
        }

        if (Schema::connection('accounting')->hasTable('payable_cheque_voucher_sudden_returns')
            && Schema::connection('accounting')->hasTable('payable_cheque_voucher_invoices')) {
            $suddenReturnByPo = DB::connection('accounting')->table('payable_cheque_voucher_sudden_returns as sr')
                ->join('payable_cheque_voucher_invoices as inv', 'sr.payable_cheque_voucher_invoice_id', '=', 'inv.id')
                ->whereNotNull('inv.purchase_order_id')
                ->select('inv.purchase_order_id', DB::raw('COALESCE(SUM(sr.return_amount), 0) AS sudden_return_total'))
                ->groupBy('inv.purchase_order_id')
                ->pluck('sudden_return_total', 'purchase_order_id');
        }

        if (Schema::connection('accounting')->hasTable('process_payment_invoices')) {
            DB::connection('accounting')->table('process_payment_invoices')
                ->where('source_type', 'purchase_order')
                ->get(['source_id', 'invoice_no'])
                ->each(function ($row) use (&$registeredKeys) {
                    $registeredKeys[(int) $row->source_id . '|' . $this->normalizeKey($row->invoice_no)] = true;
                });
        }

        $orders = DB::connection('purchase')->table('purchase_orders as po')
            ->where('po.supplier_id', $supplierId)
            ->whereNotIn('po.status', ['Cancelled', 'Void'])
            ->get([
                'po.id', 'po.po_number', 'po.supplier_id', 'po.supplier_invoice_number',
                'po.date', 'po.reference_number', 'po.status', 'po.total_amount',
                'po.actual_total_amount', 'po.created_at',
            ]);

        $orderIds = $orders->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $itemTotals = collect();
        $returnsByPo = collect();

        if ($orderIds !== []) {
            $itemTotals = DB::connection('purchase')->table('purchase_order_items')
                ->whereIn('purchase_order_id', $orderIds)
                ->select('purchase_order_id', DB::raw('COALESCE(SUM(COALESCE(NULLIF(actual_subtotal, 0), subtotal, 0)), 0) AS item_total'))
                ->groupBy('purchase_order_id')
                ->pluck('item_total', 'purchase_order_id');

            $returnsByPo = DB::connection('purchase')->table('purchase_returns')
                ->whereIn('po_id', $orderIds)
                ->orderBy('id')
                ->get(['id', 'po_id', 'return_number', 'total_amount'])
                ->groupBy(fn ($row) => (int) $row->po_id);
        }

        $eligible = $orders->map(function ($order) use ($paidByPo, $suddenReturnByPo, $itemTotals, $returnsByPo, $registeredKeys, $usedReturnKeys) {
            $actual = (float) ($order->actual_total_amount ?? 0);
            $total = (float) ($order->total_amount ?? 0);
            $amount = $actual != 0.0 ? $actual : ($total != 0.0 ? $total : (float) ($itemTotals->get($order->id) ?? 0));

            $returns = $returnsByPo->get((int) $order->id, collect());
            $returnAmount = (float) $returns->sum(fn ($row) => (float) ($row->total_amount ?? 0));
            $unusedReturns = $returns->filter(function ($row) use ($order, $usedReturnKeys) {
                $key = (int) $order->id . '|' . $this->normalizeKey($row->return_number ?? '');
                return !isset($usedReturnKeys[$key]);
            });

            $paid = (float) ($paidByPo->get($order->id) ?? 0);
            $sudden = (float) ($suddenReturnByPo->get($order->id) ?? 0);
            $remaining = max($amount - $paid - $sudden - $returnAmount, 0);
            $invoiceNo = trim((string) ($order->supplier_invoice_number ?? ''));
            $registered = $invoiceNo !== '' && isset($registeredKeys[(int) $order->id . '|' . $this->normalizeKey($invoiceNo)]);

            return (object) [
                'id' => (int) $order->id,
                'purchase_order_number' => (string) ($order->po_number ?? ''),
                'supplier_id' => (int) $order->supplier_id,
                'supplier_invoice_number' => $invoiceNo,
                'date' => (string) ($order->date ?? ''),
                'reference_no' => (string) ($order->reference_number ?? ''),
                'status' => (string) ($order->status ?? ''),
                'total_amount' => $amount,
                'remaining_balance' => $remaining,
                'created_at' => $order->created_at,
                'has_invoice' => $invoiceNo !== '',
                'registered' => $registered,
                'unused_return_count' => $unusedReturns->count(),
                'eligible' => $amount > 0 && ($remaining > 0 || $unusedReturns->isNotEmpty()),
            ];
        })->filter(fn ($row) => $row->eligible)->values();

        $fieldMap = [
            'purchase_order_number' => 'purchase_order_number',
            'supplier_invoice_number' => 'supplier_invoice_number',
            'date' => 'date',
            'reference_no' => 'reference_no',
            'status' => 'status',
            'total_amount' => 'total_amount',
            'remaining_balance' => 'remaining_balance',
        ];

        foreach ($fieldMap as $filterName => $property) {
            $value = trim((string) ($filters[$filterName] ?? ''));
            if ($value === '') {
                continue;
            }
            $needle = mb_strtolower($value);
            $eligible = $eligible->filter(function ($row) use ($property, $needle) {
                return str_contains(mb_strtolower((string) ($row->{$property} ?? '')), $needle);
            })->values();
        }

        $missingInvoiceCount = $eligible->filter(fn ($row) => !$row->has_invoice)->count();
        $registeredCount = $eligible->filter(fn ($row) => $row->has_invoice && $row->registered)->count();
        $unregistered = $eligible->filter(fn ($row) => $row->has_invoice && !$row->registered)->values();

        $sort = (string) $request->query('sort', '');
        $direction = $this->sortDirection($request);
        $sortMap = $fieldMap + ['created_at' => 'created_at'];

        if (isset($sortMap[$sort])) {
            $property = $sortMap[$sort];
            $unregistered = $unregistered->sort(function ($a, $b) use ($property, $direction) {
                $av = $a->{$property} ?? '';
                $bv = $b->{$property} ?? '';
                $cmp = is_numeric($av) && is_numeric($bv)
                    ? ((float) $av <=> (float) $bv)
                    : strcmp((string) $av, (string) $bv);
                if ($cmp === 0) {
                    $cmp = (int) $a->id <=> (int) $b->id;
                }
                return $direction === 'asc' ? $cmp : -$cmp;
            })->values();
        } else {
            $unregistered = $unregistered->sort(function ($a, $b) {
                $cmp = strcmp((string) $b->date, (string) $a->date);
                if ($cmp !== 0) return $cmp;
                $cmp = strcmp((string) ($b->created_at ?? ''), (string) ($a->created_at ?? ''));
                return $cmp !== 0 ? $cmp : ((int) $b->id <=> (int) $a->id);
            })->values();
        }

        $totalRows = $unregistered->count();
        $page = $this->page($request);
        $perPage = 25;
        $lastPage = max(1, (int) ceil($totalRows / $perPage));
        $pageItems = $unregistered->slice(($page - 1) * $perPage, $perPage)->values();

        return [
            'success' => true,
            'supplier' => [
                'id' => (int) $supplier->id,
                'name' => (string) ($supplier->name ?? ''),
            ],
            'summary' => [
                'unregistered_count' => $totalRows,
                'unregistered_amount' => (float) $unregistered->sum('remaining_balance'),
                'missing_invoice_number_count' => $missingInvoiceCount,
                'latest_purchase_order_date' => (string) ($unregistered->max('date') ?? ''),
                'already_registered_count' => $registeredCount,
            ],
            'items' => $pageItems->map(fn ($item) => [
                'id' => (int) $item->id,
                'purchase_order_number' => (string) ($item->purchase_order_number ?? ''),
                'supplier_id' => (int) $item->supplier_id,
                'supplier_invoice_number' => (string) ($item->supplier_invoice_number ?? ''),
                'date' => (string) ($item->date ?? ''),
                'reference_no' => (string) ($item->reference_no ?? ''),
                'status' => (string) ($item->status ?? ''),
                'total_amount' => (float) ($item->total_amount ?? 0),
                'remaining_balance' => (float) ($item->remaining_balance ?? 0),
                'created_at' => $item->created_at ? (string) $item->created_at : '',
            ])->values(),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $totalRows,
                'last_page' => $lastPage,
                'from' => $totalRows === 0 ? 0 : (($page - 1) * $perPage) + 1,
                'to' => $totalRows === 0 ? 0 : min($page * $perPage, $totalRows),
            ],
        ];
    }

    private function visibleSupplier(int $supplierId): Supplier
    {
        if ($supplierId < 1) {
            throw new NotFoundHttpException('Supplier not found.');
        }

        $query = Supplier::query()->where('id', $supplierId);

        if (Schema::connection('masterlist')->hasColumn('suppliers', 'record_type')) {
            $query->where(function ($query) {
                $query->whereNull('record_type')
                    ->orWhere('record_type', 'supplier');
            });
        }

        $supplier = $query->first(['id', 'name']);
        if (!$supplier) {
            throw new NotFoundHttpException('Supplier not found.');
        }

        return $supplier;
    }

    private function filters(Request $request): array
    {
        $filters = $request->query('filters', []);

        return is_array($filters) ? $filters : [];
    }

    private function sortDirection(Request $request): string
    {
        $direction = strtolower((string) $request->query('direction', 'desc'));

        return in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';
    }

    private function page(Request $request): int
    {
        return max(1, (int) $request->query('page', 1));
    }

    private function escapeLike($value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim((string) $value));
    }

    private function normalizeKey($value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    private function pagination($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem() ?? 0,
            'to' => $paginator->lastItem() ?? 0,
        ];
    }
}
