<?php

namespace App\Http\Controllers\Special;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class UnservedReportController extends Controller
{
    // W68_UNSERVED_ALL_USERS_PRINT_SUMMARY_FIX_20260921: shared routes + print heading/footer summary.
    // W68_UNSERVED_STOCK_STATUS_FILTER_V2_20260921: stock card counts item lines; print/live support All/Open/Partial status.
    // W68_UNSERVED_OPEN_PARTIAL_STATUS_FIX_V2_20260921: shared Open + Partial remaining-items report.
    public function index(): View
    {
        // W68_UNSERVED_ALL_USERS_PARTIAL_FIX_20260918
        $user = $this->authorizeReportUser();
        $accountType = (int) ($user->account_type ?? 0);

        $unservedLayout = match ($accountType) {
            1 => 'partials.admin.admin_sidebar_navbar',
            2 => 'partials.user_account.user_sidebar_navbar',
            3 => 'partials.special_user.special_sidebar_navbar',
            default => abort(403),
        };

        $unservedRoutePrefix = match ($accountType) {
            1 => 'admin',
            2 => 'regular',
            3 => 'special',
            default => abort(403),
        };

        return view('Special_User.sales.unserved-report', [
            'user' => $user,
            'account_type' => $accountType,
            'page_title' => 'Unserved Details',
            'unservedLayout' => $unservedLayout,
            'unservedRoutePrefix' => $unservedRoutePrefix,
        ]);
    }

    public function customers(Request $request): JsonResponse
    {
        $this->authorizeReportUser();

        $search = trim((string) $request->query('q', ''));

        $query = DB::connection('sales')
            ->table('sales_notes')
            ->select('customer_name')
            ->whereIn('status', ['Open', 'Partial'])
            ->whereNotNull('customer_name')
            ->where('customer_name', '!=', '');

        if ($search !== '') {
            $query->where('customer_name', 'like', '%' . $search . '%');
        }

        $items = $query
            ->distinct()
            ->orderBy('customer_name')
            ->limit(60)
            ->pluck('customer_name')
            ->map(fn ($name) => ['value' => (string) $name, 'label' => (string) $name])
            ->values();

        return response()->json(['success' => true, 'items' => $items]);
    }

    public function salesmen(Request $request): JsonResponse
    {
        $this->authorizeReportUser();

        $search = trim((string) $request->query('q', ''));

        $query = DB::connection('sales')
            ->table('sales_notes')
            ->select('salesman')
            ->whereIn('status', ['Open', 'Partial'])
            ->whereNotNull('salesman')
            ->where('salesman', '!=', '');

        if ($search !== '') {
            $query->where('salesman', 'like', '%' . $search . '%');
        }

        $items = $query
            ->distinct()
            ->orderBy('salesman')
            ->limit(60)
            ->pluck('salesman')
            ->map(fn ($name) => ['value' => (string) $name, 'label' => (string) $name])
            ->values();

        return response()->json(['success' => true, 'items' => $items]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorizeReportUser();

        try {
            $report = $this->buildReport($request);

            return response()->json([
                'success' => true,
                'rows' => $report['rows'],
                'period_label' => $report['period_label'],
                'generated_at' => $report['generated_at'],
                'summary' => $report['summary'],
                'customer_details' => $report['customer_details'],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Unable to load unserved details.'], 500);
        }
    }

    public function productHistory(Request $request): JsonResponse
    {
        $this->authorizeReportUser();

        $productId = (int) $request->query('product_id', 0);
        if ($productId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'A valid product is required.',
            ], 422);
        }

        $product = DB::connection('masterlist')
            ->table('products')
            ->where('id', $productId)
            ->first(['id', 'product_code', 'part_number', 'description']);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found.',
            ], 404);
        }

        $purchaseLedgerRows = DB::connection('ledger')
            ->table('supplier_ledger_items as sli')
            ->join('supplier_ledgers as sl', 'sl.id', '=', 'sli.supplier_ledger_id')
            ->where('sli.product_id', $productId)
            ->where('sl.module_type', 'Purchase Order')
            ->where(function ($query) {
                $query->where('sli.actual_quantity', '>', 0)
                    ->orWhere('sli.quantity', '>', 0);
            })
            ->orderByDesc('sl.date')
            ->orderByDesc('sl.id')
            ->orderByDesc('sli.id')
            ->limit(20)
            ->get([
                'sli.id',
                'sli.quantity',
                'sli.actual_quantity',
                'sli.unit_price',
                'sl.supplier_id',
                'sl.source_purchase_order_id',
                'sl.date',
                'sl.transaction_code',
                'sl.reference_no',
            ]);

        $supplierIds = $purchaseLedgerRows
            ->pluck('supplier_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $supplierNames = empty($supplierIds)
            ? collect()
            : DB::connection('masterlist')
                ->table('suppliers')
                ->whereIn('id', $supplierIds)
                ->pluck('name', 'id');

        $purchaseOrderIds = $purchaseLedgerRows
            ->pluck('source_purchase_order_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $supplierInvoices = empty($purchaseOrderIds)
            ? collect()
            : DB::connection('purchase')
                ->table('purchase_orders')
                ->whereIn('id', $purchaseOrderIds)
                ->get(['id', 'supplier_invoice_number'])
                ->mapWithKeys(fn ($row) => [
                    (int) $row->id => trim((string) ($row->supplier_invoice_number ?? '')),
                ]);

        $purchaseHistory = $purchaseLedgerRows
            ->map(function ($row) use ($supplierNames, $supplierInvoices) {
                $actualQty = (float) ($row->actual_quantity ?? 0);
                $orderedQty = (float) ($row->quantity ?? 0);
                $purchaseOrderId = (int) ($row->source_purchase_order_id ?? 0);
                $supplierId = (int) ($row->supplier_id ?? 0);

                $supplierInvoice = trim((string) ($supplierInvoices->get($purchaseOrderId) ?? ''));
                if ($supplierInvoice === '') {
                    $supplierInvoice = trim((string) ($row->reference_no ?? ''));
                }

                return [
                    'date' => (string) ($row->date ?? ''),
                    'po_no' => trim((string) ($row->transaction_code ?? '')),
                    'supplier_invoice' => $supplierInvoice,
                    'supplier_name' => trim((string) ($supplierNames->get($supplierId) ?? '')),
                    'qty' => $actualQty > 0 ? $actualQty : $orderedQty,
                    'unit_cost' => (float) ($row->unit_price ?? 0),
                ];
            })
            ->values();

        $salesHistory = DB::connection('ledger')
            ->table('product_ledgers')
            ->where('product_id', $productId)
            ->where('quantity_out', '>', 0)
            ->whereRaw("UPPER(COALESCE(remarks, '')) NOT LIKE ?", ['%ADJUST%'])
            ->whereRaw("UPPER(COALESCE(remarks, '')) NOT LIKE ?", ['%ADJUT%'])
            ->whereRaw("UPPER(COALESCE(remarks, '')) NOT LIKE ?", ['%PURRTN%'])
            ->whereRaw("UPPER(COALESCE(remarks, '')) NOT LIKE ?", ['%PURCHRTN%'])
            ->whereRaw("UPPER(COALESCE(remarks, '')) NOT LIKE ?", ['%PURCHASE RETURN%'])
            ->whereRaw(
                "UPPER(TRIM(COALESCE(remarks, ''))) NOT IN (?, ?, ?, ?, ?, ?)",
                ['D', 'SO', 'XPNSEDIS', 'XPENSEDIS', 'CNSMTRTN', 'INTERCHANGE']
            )
            ->orderByDesc('date')
            ->orderByRaw("COALESCE(created_at, '1970-01-01 00:00:00') DESC")
            ->orderByDesc('id')
            ->limit(20)
            ->get([
                'date',
                'transaction_number',
                'reference_number',
                'entity_name',
                'quantity_out',
                'price',
            ])
            ->map(function ($row) {
                $invoice = trim((string) ($row->reference_number ?? ''));
                if ($invoice === '') {
                    $invoice = trim((string) ($row->transaction_number ?? ''));
                }

                return [
                    'date' => (string) ($row->date ?? ''),
                    'sales_invoice' => $invoice,
                    'customer_name' => trim((string) ($row->entity_name ?? '')),
                    'qty' => (float) ($row->quantity_out ?? 0),
                    'unit_price' => (float) ($row->price ?? 0),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'product' => [
                'id' => (int) $product->id,
                'product_code' => (string) ($product->product_code ?? ''),
                'part_number' => (string) ($product->part_number ?? ''),
                'description' => (string) ($product->description ?? ''),
            ],
            'purchase_history' => $purchaseHistory,
            'sales_history' => $salesHistory,
        ]);
    }

    public function print(Request $request): View
    {
        $this->authorizeReportUser();

        $report = $this->buildReport($request);

        return view('Special_User.sales.unserved-report-print', [
            'rows' => $report['rows'],
            'periodLabel' => $report['period_label'],
            'generatedAt' => $report['generated_at'],
            'summary' => $report['summary'],
            'customer' => trim((string) $request->query('customer', '')),
            'salesman' => trim((string) $request->query('salesman', '')),
            'statusFilter' => strtolower(trim((string) $request->query('status_filter', 'all'))),
            'customerDetails' => $report['customer_details'],
            'customerGroups' => $report['customer_groups'],
        ]);
    }

    private function buildReport(Request $request): array
    {
        [$dateFrom, $dateTo, $periodLabel] = $this->resolvePeriod($request);
        $customer = trim((string) $request->input('customer', ''));
        $salesman = trim((string) $request->input('salesman', ''));

        $statusFilter = strtolower(trim((string) $request->input('status_filter', 'all')));
        if (!in_array($statusFilter, ['all', 'open', 'partial'], true)) {
            $statusFilter = 'all';
        }

        $notesQuery = DB::connection('sales')
            ->table('sales_notes')
            ->whereIn('status', ['Open', 'Partial']);

        if ($statusFilter === 'open') {
            $notesQuery->where('status', 'Open');
        } elseif ($statusFilter === 'partial') {
            $notesQuery->where('status', 'Partial');
        }


        // Unserved is a backlog/as-of report. A note created before the selected
        // period can still have remaining items during that period, so do not
        // discard carry-forward Open/Partial notes with a lower-bound filter.
        // The selected period end is the snapshot cut-off.
        if ($dateTo) {
            $notesQuery->whereDate('order_date', '<=', $dateTo);
        }
        if ($customer !== '') {
            $notesQuery->where('customer_name', 'like', '%' . $customer . '%');
        }
        if ($salesman !== '') {
            $notesQuery->where('salesman', 'like', '%' . $salesman . '%');
        }

        $notes = $notesQuery
            ->orderBy('order_date')
            ->orderBy('sales_number')
            ->get([
                'id',
                'sales_number',
                'customer_id',
                'customer_name',
                'order_date',
                'salesman',
                'status',
                'is_rush',
            ]);

        $customerDetails = $this->resolveCustomerDetails($notes, $customer);

        if ($notes->isEmpty()) {
            return $this->emptyReport($periodLabel, $customerDetails);
        }

        $noteIds = $notes->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        $noteItemsQuery = DB::connection('sales')
            ->table('sales_note_items')
            ->whereIn('sales_note_id', $noteIds);

        if (Schema::connection('sales')->hasColumn('sales_note_items', 'deleted_at')) {
            $noteItemsQuery->whereNull('deleted_at');
        }

        $noteItems = $noteItemsQuery->get([
            'id',
            'sales_note_id',
            'product_id',
            'description',
            'quantity',
            'additional_qty',
            'oum',
            'unit_price',
            'subtotal',
        ]);

        if ($noteItems->isEmpty()) {
            return $this->emptyReport($periodLabel, $customerDetails);
        }

        // Served quantity comes only from Sales Order items tied to the same
        // Sales Note + Product. Invoice numbers are intentionally not part of
        // the Unserved Details report.
        $servedRowsQuery = DB::connection('sales')
            ->table('sales_orders as so')
            ->join('sales_order_items as soi', 'soi.sales_order_id', '=', 'so.id')
            ->whereIn('so.sales_note_id', $noteIds)
            ->whereNotNull('soi.product_id');

        // Count only quantities actually served on or before the selected
        // report cut-off. This is important for migrated/carry-forward notes:
        // e.g. Sales Note 00029749 is dated July but has linked Sales Orders
        // created in August. It must appear in August while August serving is
        // deducted from its remaining quantity.
        if ($dateTo) {
            $servedRowsQuery->where(function ($query) use ($dateTo) {
                $query->whereDate('so.created_at', '<=', $dateTo)
                    ->orWhere(function ($fallback) use ($dateTo) {
                        $fallback->whereNull('so.created_at')
                            ->whereDate('so.updated_at', '<=', $dateTo);
                    });
            });
        }

        $servedRows = $servedRowsQuery->get([
            'so.sales_note_id',
            'soi.product_id',
            'soi.actual_qty',
        ]);

        $servedMap = [];
        foreach ($servedRows as $row) {
            $noteId = (int) $row->sales_note_id;
            $productId = (int) $row->product_id;
            $servedQty = max(0, (float) ($row->actual_qty ?? 0));

            $servedMap[$noteId][$productId] = ($servedMap[$noteId][$productId] ?? 0) + $servedQty;
        }

        $productIds = $noteItems
            ->pluck('product_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $products = empty($productIds)
            ? collect()
            : DB::connection('masterlist')
                ->table('products')
                ->whereIn('id', $productIds)
                ->get(['id', 'product_code', 'part_number', 'description'])
                ->keyBy('id');

        $onHandMap = $this->latestLedgerBalances($productIds);

        $itemsByNote = [];
        foreach ($noteItems as $item) {
            $noteId = (int) $item->sales_note_id;
            $productId = (int) $item->product_id;
            if (!$productId) {
                continue;
            }

            // additional_qty is separate; do not count it as ordered/actual qty.
            $qty = max(0, (float) ($item->quantity ?? 0));
            $bucketKey = (string) $productId;
            $lineUnitPrice = (float) ($item->unit_price ?? 0);
            $lineSubtotal = (float) ($item->subtotal ?? 0);

            // A number of migrated Sales Note rows have unit_price = 0 while
            // their original subtotal is preserved. Recover the original unit
            // price so migrated unserved rows do not appear as zero-value.
            if ($lineUnitPrice <= 0 && $qty > 0 && $lineSubtotal > 0) {
                $lineUnitPrice = $lineSubtotal / $qty;
            }

            if (!isset($itemsByNote[$noteId][$bucketKey])) {
                $itemsByNote[$noteId][$bucketKey] = [
                    'product_id' => $productId,
                    'requested_qty' => 0.0,
                    'description' => trim((string) ($item->description ?? '')),
                    'unit_price' => $lineUnitPrice,
                    'unit' => (string) ($item->oum ?? ''),
                ];
            }

            $itemsByNote[$noteId][$bucketKey]['requested_qty'] += $qty;
            if ($itemsByNote[$noteId][$bucketKey]['description'] === '' && !empty($item->description)) {
                $itemsByNote[$noteId][$bucketKey]['description'] = trim((string) $item->description);
            }
            if ($lineUnitPrice > 0) {
                $itemsByNote[$noteId][$bucketKey]['unit_price'] = $lineUnitPrice;
            }
        }

        $rows = [];
        foreach ($notes as $note) {
            $noteId = (int) $note->id;
            foreach ($itemsByNote[$noteId] ?? [] as $item) {
                $productId = (int) $item['product_id'];
                $requested = (float) $item['requested_qty'];
                $served = min($requested, max(0, (float) ($servedMap[$noteId][$productId] ?? 0)));
                $unserved = max(0, $requested - $served);

                if ($unserved <= 0) {
                    continue;
                }

                $product = $products->get($productId);
                $description = trim((string) $item['description']);
                if ($description === '') {
                    $description = trim((string) ($product->description ?? ''));
                }

                $unitPrice = (float) ($item['unit_price'] ?? 0);

                $rows[] = [
                    'sales_note_id' => $noteId,
                    'so_no' => (string) ($note->sales_number ?? ''),
                    'product_code' => (string) ($product->product_code ?? ''),
                    'part_number' => (string) ($product->part_number ?? ''),
                    'description' => $description,
                    'on_hand' => (float) ($onHandMap[$productId] ?? 0),
                    'served' => $served,
                    'unserved' => $unserved,
                    'unit_price' => $unitPrice,
                    'total_amount' => round($unserved * $unitPrice, 2),
                    'unit' => (string) ($item['unit'] ?? ''),
                    'customer_id' => (int) ($note->customer_id ?? 0),
                    'customer' => trim((string) ($note->customer_name ?? '')),
                    'salesman' => (string) ($note->salesman ?? ''),
                    'order_date' => (string) ($note->order_date ?? ''),
                    'status' => (string) ($note->status ?? ''),
                    'is_rush' => (bool) ($note->is_rush ?? false),
                ];
            }
        }

        usort($rows, function (array $a, array $b) {
            return [
                mb_strtolower((string) $a['customer']),
                $a['order_date'],
                $a['so_no'],
                $a['product_code'],
            ] <=> [
                mb_strtolower((string) $b['customer']),
                $b['order_date'],
                $b['so_no'],
                $b['product_code'],
            ];
        });

        $rushFilter = strtolower(trim((string) $request->input('rush_filter', 'all')));
        if (!in_array($rushFilter, ['all', 'rush', 'not-rush'], true)) {
            $rushFilter = 'all';
        }
        if ($rushFilter === 'rush') {
            $rows = array_values(array_filter($rows, fn (array $row) => !empty($row['is_rush'])));
        } elseif ($rushFilter === 'not-rush') {
            $rows = array_values(array_filter($rows, fn (array $row) => empty($row['is_rush'])));
        }

        $withStockRows = array_values(array_filter(
            $rows,
            fn (array $row) => (float) ($row['on_hand'] ?? 0) > 0
        ));
        $unservedWithStockLines = count($withStockRows);
        $unservedWithStockOpenLines = count(array_filter(
            $withStockRows,
            fn (array $row) => strcasecmp((string) ($row['status'] ?? ''), 'Open') === 0
        ));
        $unservedWithStockPartialLines = count(array_filter(
            $withStockRows,
            fn (array $row) => strcasecmp((string) ($row['status'] ?? ''), 'Partial') === 0
        ));
        $unservedWithStockQty = array_sum(array_column($withStockRows, 'unserved'));

        $stockFilter = strtolower(trim((string) $request->input('stock_filter', 'all')));
        if (!in_array($stockFilter, ['all', 'with', 'without'], true)) {
            $stockFilter = 'all';
        }
        if ($stockFilter === 'with') {
            $rows = array_values(array_filter($rows, fn (array $row) => (float) ($row['on_hand'] ?? 0) > 0));
        } elseif ($stockFilter === 'without') {
            $rows = array_values(array_filter($rows, fn (array $row) => (float) ($row['on_hand'] ?? 0) <= 0));
        }
        $totalUnserved = array_sum(array_column($rows, 'unserved'));
        $totalAmount = array_sum(array_column($rows, 'total_amount'));
        $servableItems = count(array_filter(
            $rows,
            fn (array $row) => abs(
                (float) ($row['on_hand'] ?? 0) - (float) ($row['unserved'] ?? 0)
            ) < 0.000001
        ));
        $customerGroups = $this->buildCustomerGroups($rows, $notes);

        return [
            'rows' => $rows,
            'period_label' => $periodLabel,
            'generated_at' => Carbon::now('Asia/Manila')->format('F d, Y h:i A'),
            'customer_details' => $customerDetails,
            'customer_groups' => $customerGroups,
            'summary' => [
                'partial_notes' => collect($rows)
                    ->filter(fn (array $row) => strcasecmp((string) ($row['status'] ?? ''), 'Partial') === 0)
                    ->pluck('sales_note_id')
                    ->unique()
                    ->count(),
                'open_partial_notes' => collect($rows)->pluck('sales_note_id')->unique()->count(),
                'line_items' => count($rows),
                'servable_items' => $servableItems,
                'unserved_with_stock_lines' => $unservedWithStockLines,
                'unserved_with_stock_open_lines' => $unservedWithStockOpenLines,
                'unserved_with_stock_partial_lines' => $unservedWithStockPartialLines,
                'unserved_with_stock_qty' => $unservedWithStockQty,
                'total_unserved' => $totalUnserved,
                'total_amount' => $totalAmount,
            ],
        ];
    }

    private function latestLedgerBalances(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $rows = DB::connection('ledger')
            ->table('product_ledgers as pl')
            ->whereIn('pl.product_id', $productIds)
            ->whereRaw("pl.id = (SELECT pl2.id FROM product_ledgers pl2 WHERE pl2.product_id = pl.product_id ORDER BY pl2.date DESC, COALESCE(pl2.created_at, '1970-01-01 00:00:00') DESC, pl2.id DESC LIMIT 1)")
            ->get(['pl.product_id', 'pl.balance_stock']);

        return $rows
            ->mapWithKeys(fn ($row) => [(int) $row->product_id => (float) ($row->balance_stock ?? 0)])
            ->all();
    }

    private function resolvePeriod(Request $request): array
    {
        $type = strtolower(trim((string) $request->input('date_type', 'monthly')));
        $now = Carbon::now('Asia/Manila');

        return match ($type) {
            'annual' => $this->annualPeriod((string) $request->input('year', $now->year)),
            'monthly' => $this->monthlyPeriod((string) $request->input('month', $now->format('Y-m'))),
            'quarterly' => $this->quarterPeriod(
                (string) $request->input('year', $now->year),
                (string) $request->input('quarter', 'Q' . $now->quarter)
            ),
            'half-year' => $this->halfYearPeriod(
                (string) $request->input('year', $now->year),
                (string) $request->input('half', $now->month <= 6 ? 'H1' : 'H2')
            ),
            'as-of' => $this->asOfPeriod((string) $request->input('as_of', $now->toDateString())),
            'from-to' => $this->fromToPeriod(
                (string) $request->input('date_from', $now->copy()->startOfMonth()->toDateString()),
                (string) $request->input('date_to', $now->toDateString())
            ),
            default => throw new \InvalidArgumentException('Invalid date filter type.'),
        };
    }

    private function annualPeriod(string $year): array
    {
        if (!preg_match('/^\d{4}$/', $year)) {
            throw new \InvalidArgumentException('Please select a valid year.');
        }
        $date = Carbon::create((int) $year, 1, 1, 0, 0, 0, 'Asia/Manila');
        return [$date->copy()->startOfYear()->toDateString(), $date->copy()->endOfYear()->toDateString(), $date->format('Y')];
    }

    private function monthlyPeriod(string $month): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw new \InvalidArgumentException('Please select a valid month.');
        }
        $date = Carbon::createFromFormat('Y-m', $month, 'Asia/Manila')->startOfMonth();
        return [$date->toDateString(), $date->copy()->endOfMonth()->toDateString(), $date->format('F Y')];
    }

    private function quarterPeriod(string $year, string $quarter): array
    {
        if (!preg_match('/^\d{4}$/', $year) || !preg_match('/^Q[1-4]$/', strtoupper($quarter))) {
            throw new \InvalidArgumentException('Please select a valid quarter.');
        }
        $q = (int) substr(strtoupper($quarter), 1);
        $startMonth = (($q - 1) * 3) + 1;
        $start = Carbon::create((int) $year, $startMonth, 1, 0, 0, 0, 'Asia/Manila');
        return [$start->toDateString(), $start->copy()->addMonths(2)->endOfMonth()->toDateString(), "Q{$q} {$year}"];
    }

    private function halfYearPeriod(string $year, string $half): array
    {
        $half = strtoupper($half);
        if (!preg_match('/^\d{4}$/', $year) || !in_array($half, ['H1', 'H2'], true)) {
            throw new \InvalidArgumentException('Please select a valid half year.');
        }
        $startMonth = $half === 'H1' ? 1 : 7;
        $start = Carbon::create((int) $year, $startMonth, 1, 0, 0, 0, 'Asia/Manila');
        $label = $half === 'H1' ? "First Half {$year}" : "Second Half {$year}";
        return [$start->toDateString(), $start->copy()->addMonths(5)->endOfMonth()->toDateString(), $label];
    }

    private function asOfPeriod(string $asOf): array
    {
        $date = $this->parseDate($asOf, 'Please select a valid as-of date.');
        return [null, $date->toDateString(), 'As of ' . $date->format('F d, Y')];
    }

    private function fromToPeriod(string $from, string $to): array
    {
        $start = $this->parseDate($from, 'Please select a valid start date.');
        $end = $this->parseDate($to, 'Please select a valid end date.');
        if ($start->gt($end)) {
            throw new \InvalidArgumentException('From date cannot be later than To date.');
        }
        return [$start->toDateString(), $end->toDateString(), $start->format('F d, Y') . ' to ' . $end->format('F d, Y')];
    }

    private function parseDate(string $value, string $message): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $value, 'Asia/Manila')->startOfDay();
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException($message);
        }
    }

    private function buildCustomerGroups(array $rows, Collection $notes): array
    {
        if (empty($rows)) {
            return [];
        }

        $names = collect($rows)
            ->pluck('customer')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique(fn ($name) => mb_strtolower($name))
            ->values();

        $masterCustomers = collect();

        if ($names->isNotEmpty()) {
            $masterCustomers = DB::connection('masterlist')
                ->table('customers')
                ->where(function ($query) use ($names) {
                    foreach ($names as $name) {
                        $query->orWhereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim((string) $name))]);
                    }
                })
                ->get(['id', 'name', 'tin', 'terms']);
        }

        $masterByName = $masterCustomers->keyBy(
            fn ($customer) => mb_strtolower(trim((string) ($customer->name ?? '')))
        );

        $noteCustomerIdsByName = [];
        foreach ($notes as $note) {
            $name = trim((string) ($note->customer_name ?? ''));
            if ($name === '') {
                continue;
            }

            $key = mb_strtolower($name);
            $id = (int) ($note->customer_id ?? 0);
            if ($id > 0) {
                $noteCustomerIdsByName[$key][$id] = $id;
            }
        }

        $missingIds = [];
        foreach ($names as $name) {
            $key = mb_strtolower(trim((string) $name));
            if ($masterByName->has($key)) {
                continue;
            }

            $ids = array_values($noteCustomerIdsByName[$key] ?? []);
            if (count($ids) === 1) {
                $missingIds[$ids[0]] = $ids[0];
            }
        }

        $masterById = empty($missingIds)
            ? collect()
            : DB::connection('masterlist')
                ->table('customers')
                ->whereIn('id', array_values($missingIds))
                ->get(['id', 'name', 'tin', 'terms'])
                ->keyBy('id');

        $grouped = [];
        foreach ($rows as $row) {
            $displayName = trim((string) ($row['customer'] ?? ''));
            if ($displayName === '') {
                $displayName = 'UNSPECIFIED CUSTOMER';
            }

            $key = mb_strtolower($displayName);
            if (!isset($grouped[$key])) {
                $master = $masterByName->get($key);

                if (!$master) {
                    $ids = array_values($noteCustomerIdsByName[$key] ?? []);
                    if (count($ids) === 1) {
                        $master = $masterById->get($ids[0]);
                    }
                }

                $grouped[$key] = [
                    'customer' => [
                        'name' => $master
                            ? (trim((string) ($master->name ?? '')) ?: $displayName)
                            : $displayName,
                        'tin' => $master
                            ? (trim((string) ($master->tin ?? '')) ?: '—')
                            : '—',
                        'terms' => $master
                            ? (trim((string) ($master->terms ?? '')) ?: '—')
                            : '—',
                    ],
                    'rows' => [],
                    'summary' => [
                        'line_items' => 0,
                        'total_unserved' => 0.0,
                        'total_amount' => 0.0,
                    ],
                ];
            }

            $grouped[$key]['rows'][] = $row;
            $grouped[$key]['summary']['line_items']++;
            $grouped[$key]['summary']['total_unserved'] += (float) ($row['unserved'] ?? 0);
            $grouped[$key]['summary']['total_amount'] += (float) ($row['total_amount'] ?? 0);
        }

        uasort($grouped, fn (array $a, array $b) =>
            strcasecmp((string) $a['customer']['name'], (string) $b['customer']['name'])
        );

        return array_values($grouped);
    }

    private function resolveCustomerDetails(Collection $notes, string $requestedCustomer): array
    {
        $uniqueNames = $notes
            ->pluck('customer_name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->values();

        // Customer names are the safest identity for legacy imported Sales Notes,
        // where customer_id can point to a generic/old master-list row.
        $lookupName = trim($requestedCustomer);
        if ($lookupName === '' && $uniqueNames->count() === 1) {
            $lookupName = (string) $uniqueNames->first();
        }

        $customer = null;
        if ($lookupName !== '') {
            $customer = DB::connection('masterlist')
                ->table('customers')
                ->whereRaw('LOWER(TRIM(name)) = LOWER(?)', [$lookupName])
                ->first(['id', 'name', 'tin', 'terms']);

            if (!$customer) {
                $customer = DB::connection('masterlist')
                    ->table('customers')
                    ->where('name', 'like', '%' . $lookupName . '%')
                    ->orderBy('name')
                    ->first(['id', 'name', 'tin', 'terms']);
            }
        }

        // Fall back to customer_id only when the report clearly belongs to one customer.
        if (!$customer && $uniqueNames->count() <= 1) {
            $customerIds = $notes
                ->pluck('customer_id')
                ->filter(fn ($id) => (int) $id > 0)
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($customerIds->count() === 1) {
                $customer = DB::connection('masterlist')
                    ->table('customers')
                    ->where('id', $customerIds->first())
                    ->first(['id', 'name', 'tin', 'terms']);
            }
        }

        if ($customer) {
            return [
                'name' => trim((string) ($customer->name ?? '')) ?: ($lookupName ?: '—'),
                'tin' => trim((string) ($customer->tin ?? '')) ?: '—',
                'terms' => trim((string) ($customer->terms ?? '')) ?: '—',
            ];
        }

        return [
            'name' => $uniqueNames->count() === 1
                ? (string) $uniqueNames->first()
                : ($requestedCustomer !== '' ? $requestedCustomer : 'ALL CUSTOMERS'),
            'tin' => '—',
            'terms' => '—',
        ];
    }

    private function emptyReport(string $periodLabel, ?array $customerDetails = null): array
    {
        return [
            'rows' => [],
            'period_label' => $periodLabel,
            'generated_at' => Carbon::now('Asia/Manila')->format('F d, Y h:i A'),
            'customer_details' => $customerDetails ?? [
                'name' => 'ALL CUSTOMERS',
                'tin' => '—',
                'terms' => '—',
            ],
            'customer_groups' => [],
            'summary' => [
                'partial_notes' => 0,
                'open_partial_notes' => 0,
                'line_items' => 0,
                'servable_items' => 0,
                'unserved_with_stock_lines' => 0,
                'unserved_with_stock_open_lines' => 0,
                'unserved_with_stock_partial_lines' => 0,
                'unserved_with_stock_qty' => 0,
                'total_unserved' => 0,
                'total_amount' => 0,
            ],
        ];
    }

    private function authorizeReportUser(): object
    {
        $user = session('user');
        if (!$user) {
            abort(401);
        }
        if (is_array($user)) {
            $user = (object) $user;
        }

        $accountType = (int) ($user->account_type ?? 0);
        if (!in_array($accountType, [1, 2, 3], true)) {
            abort(403);
        }

        $expectedRoutePrefix = match ($accountType) {
            1 => 'admin.unserved-report',
            2 => 'regular.unserved-report',
            3 => 'special.unserved-report',
            default => '',
        };

        $routeName = (string) (request()->route()?->getName() ?? '');
        if ($routeName !== '' && !str_starts_with($routeName, $expectedRoutePrefix)) {
            abort(403);
        }

        return $user;
    }
}
