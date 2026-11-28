<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ProductPurchaseCostService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CostReportController extends Controller
{
    private const LOOKUP_LIMIT = 25;
    private const LOOKUP_TTL_SECONDS = 120;
    private const SEARCH_TTL_SECONDS = 60;

    public function productCodes(Request $request): JsonResponse
    {
        return $this->lookupDistinctColumn($request, 'product_code');
    }

    public function partNumbers(Request $request): JsonResponse
    {
        return $this->lookupDistinctColumn($request, 'part_number');
    }

    public function descriptions(Request $request): JsonResponse
    {
        return $this->lookupDistinctColumn($request, 'description');
    }

    public function applications(Request $request): JsonResponse
    {
        return $this->lookupDistinctColumn($request, 'application');
    }

    public function categories(Request $request): JsonResponse
    {
        return $this->lookupFromSmallDistinctList($request, 'category');
    }

    public function pricelistCodes(Request $request): JsonResponse
    {
        try {
            if (!$this->hasMasterlistColumn('pricelist_code')) {
                return response()->json([]);
            }

            return $this->lookupFromSmallDistinctList($request, 'pricelist_code');
        } catch (Throwable $e) {
            Log::error('Cost Report pricelist-code lookup failed.', [
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return response()->json([]);
        }
    }

    public function data(Request $request, ProductPurchaseCostService $purchaseCostService): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $this->prepareHeavyRequest();

        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(500, (int) $request->query('per_page', 50)));
        $all = (string) $request->query('all', '0') === '1';
        $skipStats = $request->boolean('skip_stats');

        $normalizeMultiFilter = static function ($value): array {
            return collect(is_array($value) ? $value : [$value])
                ->map(fn ($item) => trim((string) $item))
                ->filter(fn ($item) => $item !== '')
                ->unique()
                ->values()
                ->all();
        };

        $productCodes = $normalizeMultiFilter($request->query('product_code', []));
        $partNumbers = $normalizeMultiFilter($request->query('part_number', []));
        $description = trim((string) $request->query('description', ''));
        $application = trim((string) $request->query('application', ''));
        $category = trim((string) $request->query('category', ''));
        $pricelistCode = trim((string) $request->query('pricelist_code', ''));
        $supplier = trim((string) $request->query('supplier', ''));

        $year1 = (int) $request->query('year1', date('Y'));
        $year2 = (int) $request->query('year2', date('Y') - 1);
        $year3 = (int) $request->query('year3', date('Y') - 2);
        $years = array_values(array_unique([$year1, $year2, $year3]));

        $unitColumn = $this->resolveMasterlistUnitColumn();
        $selectColumns = $this->resolveProductSelectColumns($unitColumn);

        $query = Product::on('masterlist')->select($selectColumns);

        if ($productCodes !== []) {
            $query->whereIn('product_code', $productCodes);
        }
        if ($partNumbers !== []) {
            $query->whereIn('part_number', $partNumbers);
        }
        if ($description !== '') {
            $query->where('description', 'LIKE', '%' . $this->escapeLike($description) . '%');
        }
        if ($application !== '') {
            $query->where('application', 'LIKE', '%' . $this->escapeLike($application) . '%');
        }
        if ($category !== '') {
            $query->where('category', $category);
        }
        if ($pricelistCode !== '' && $this->hasMasterlistColumn('pricelist_code')) {
            $query->where('pricelist_code', 'LIKE', '%' . $this->escapeLike($pricelistCode) . '%');
        }
        if ($supplier !== '' && $this->hasMasterlistColumn('supplier')) {
            $query->where('supplier', 'LIKE', '%' . $this->escapeLike($supplier) . '%');
        }

        $totalItems = 0;
        $totalCost = 0.0;
        $avgCost = 0.0;
        $highestCost = 0.0;

        if (!$skipStats) {
            $statsRow = (clone $query)
                ->toBase()
                ->selectRaw('COUNT(*) as total_items, COALESCE(SUM(cost), 0) as total_cost, COALESCE(MAX(cost), 0) as highest_cost')
                ->first();

            $totalItems = (int) ($statsRow->total_items ?? 0);
            $totalCost = (float) ($statsRow->total_cost ?? 0);
            $highestCost = (float) ($statsRow->highest_cost ?? 0);
            $avgCost = $totalItems > 0 ? ($totalCost / $totalItems) : 0.0;
        }

        // Deterministic PK ordering keeps multi-batch chunked printing non-overlapping
        // without adding a filesort on unindexed columns.
        $orderedQuery = (clone $query)->orderBy('id');
        $knownTotal = ($skipStats && $request->filled('known_total'))
            ? max(0, (int) $request->query('known_total'))
            : null;
        $afterId = ($skipStats && $request->filled('after_id'))
            ? max(0, (int) $request->query('after_id'))
            : null;

        if ($all) {
            $paginated = null;
            $productCollection = $orderedQuery->get();
        } elseif ($knownTotal !== null) {
            $paginated = null;
            if ($afterId !== null) {
                $productCollection = (clone $orderedQuery)
                    ->where('id', '>', $afterId)
                    ->limit($perPage)
                    ->get();
            } else {
                $productCollection = (clone $orderedQuery)
                    ->forPage($page, $perPage)
                    ->get();
            }
        } else {
            $paginated = $orderedQuery->paginate($perPage, $selectColumns, 'page', $page);
            $productCollection = collect($paginated->items());
        }

        if ($skipStats) {
            $totalItems = $paginated
                ? (int) $paginated->total()
                : ($knownTotal ?? $productCollection->count());
        }

        $nextAfterId = $productCollection->isNotEmpty()
            ? (int) $productCollection->last()->id
            : null;

        $pageProductIds = $productCollection
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $pageProductCodes = $productCollection
            ->pluck('product_code')
            ->map(fn ($code) => trim((string) $code))
            ->filter(fn ($code) => $code !== '')
            ->unique()
            ->values()
            ->all();

        $latestBalances = [];
        $latestPrices = [];
        $salesByYear = [];
        $salesOumByProduct = [];
        $purchaseCostLookup = ['by_product_id' => [], 'by_product_code' => []];

        if ($pageProductIds !== []) {
            [$latestBalances, $latestPrices] = $this->fetchLatestLedgerSnapshots($pageProductIds);

            $outColumn = $this->hasLedgerColumn('out') ? 'out' : 'quantity_out';
            $salesByYear = $this->fetchSalesByYear($pageProductIds, $years, $outColumn);
            $salesOumByProduct = $this->fetchSalesOumByProduct($pageProductIds, $outColumn);

            $purchaseCostLookup = $purchaseCostService->getLatestPurchaseCosts(
                $pageProductIds,
                $pageProductCodes
            );
        }

        $items = $productCollection->map(function ($p) use (
            $latestBalances,
            $latestPrices,
            $salesByYear,
            $year1,
            $year2,
            $year3,
            $purchaseCostService,
            $purchaseCostLookup,
            $salesOumByProduct
        ) {
            $pid = (int) $p->id;
            $costInfo = $purchaseCostLookup['by_product_id'][$pid] ?? null;
            if (!$costInfo) {
                $costInfo = $purchaseCostLookup['by_product_code'][
                    $purchaseCostService->normalizeProductCode($p->product_code ?? '')
                ] ?? null;
            }

            $costDate = '';
            if (!empty($costInfo['purchase_date'])) {
                try {
                    $costDate = Carbon::parse($costInfo['purchase_date'])->format('Y-m-d');
                } catch (Throwable $e) {
                    $costDate = (string) $costInfo['purchase_date'];
                }
            }

            $unit = '';
            foreach (['unit', 'Unit', 'UNIT', 'unit_measure', 'uom', 'UOM'] as $key) {
                if (isset($p->$key) && trim((string) $p->$key) !== '') {
                    $unit = trim((string) $p->$key);
                    break;
                }
            }

            $salesOum = trim((string) ($salesOumByProduct[$pid] ?? ''));
            if ($salesOum === '' && $unit !== '') {
                $salesOum = $unit;
            }

            return [
                'product_code' => $p->product_code ?? '',
                'part_number' => $p->part_number ?? '',
                'description' => $p->description ?? '',
                'application' => $p->application ?? '',
                'category' => $p->category ?? '',
                'unit' => $unit,
                'oum' => $salesOum,
                'supplier' => $costInfo['supplier_name'] ?? '',
                'last_cost' => (float) ($p->cost ?? 0),
                'cost_supplier' => $costInfo['supplier_name'] ?? '',
                'supplier_cost' => (float) ($costInfo['unit_price'] ?? 0),
                'cost_date' => $costDate,
                'local_price' => (float) ($p->selling_price ?? 0),
                'online_price' => (float) ($p->price_online ?? 0),
                'amount' => (float) ($latestPrices[$pid] ?? 0),
                'amount_date' => $costDate,
                'on_hand' => (int) ($latestBalances[$pid] ?? 0),
                'sales_1' => (int) ($salesByYear[$pid][$year1] ?? 0),
                'sales_2' => (int) ($salesByYear[$pid][$year2] ?? 0),
                'sales_3' => (int) ($salesByYear[$pid][$year3] ?? 0),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'items' => $items,
            'pagination' => $all ? null : [
                'current_page' => $paginated ? $paginated->currentPage() : $page,
                'last_page' => $paginated ? $paginated->lastPage() : max(1, (int) ceil($totalItems / $perPage)),
                'per_page' => $paginated ? $paginated->perPage() : $perPage,
                'total' => $paginated ? $paginated->total() : $totalItems,
                'next_after_id' => $nextAfterId,
            ],
            'stats' => [
                'total_items' => $totalItems,
                'total_cost' => $totalCost,
                'avg_cost' => $avgCost,
                'highest_cost' => $highestCost,
            ],
        ]);
    }

    private function lookupDistinctColumn(Request $request, string $column): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = trim((string) ($request->query('query', $request->input('query', ''))));

        if ($query === '') {
            $values = Cache::remember("w68:cr:lookup:{$column}:default", self::LOOKUP_TTL_SECONDS, function () use ($column) {
                return DB::connection('masterlist')
                    ->table('products')
                    ->whereNotNull($column)
                    ->where($column, '!=', '')
                    ->distinct()
                    ->orderBy($column)
                    ->limit(self::LOOKUP_LIMIT)
                    ->pluck($column)
                    ->values()
                    ->all();
            });

            return response()->json($values);
        }

        $cacheKey = 'w68:cr:lookup:' . $column . ':' . sha1(mb_strtolower($query));
        $values = Cache::remember($cacheKey, self::SEARCH_TTL_SECONDS, function () use ($column, $query) {
            return DB::connection('masterlist')
                ->table('products')
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->where($column, 'like', '%' . $this->escapeLike($query) . '%')
                ->distinct()
                ->orderBy($column)
                ->limit(self::LOOKUP_LIMIT)
                ->pluck($column)
                ->values()
                ->all();
        });

        return response()->json($values);
    }

    /**
     * Low-cardinality columns (category, pricelist_code) have <= 1,000 distinct values,
     * so caching the sorted list and filtering in PHP avoids scanning 40k+ rows on every keystroke.
     */
    private function lookupFromSmallDistinctList(Request $request, string $column): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = trim((string) ($request->query('query', $request->input('query', ''))));

        $allValues = Cache::remember("w68:cr:distinct_all:{$column}", self::LOOKUP_TTL_SECONDS, function () use ($column) {
            return DB::connection('masterlist')
                ->table('products')
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->distinct()
                ->orderBy($column)
                ->pluck($column)
                ->values()
                ->all();
        });

        if ($query !== '') {
            $allValues = array_values(array_filter(
                $allValues,
                static fn ($val) => mb_stripos((string) $val, $query) !== false
            ));
        }

        return response()->json(array_slice($allValues, 0, self::LOOKUP_LIMIT));
    }

    private function fetchLatestLedgerSnapshots(array $productIds): array
    {
        $maxIds = DB::connection('ledger')
            ->table('product_ledgers')
            ->whereIn('product_id', $productIds)
            ->selectRaw('MAX(id) as max_id')
            ->groupBy('product_id')
            ->pluck('max_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($maxIds === []) {
            return [[], []];
        }

        $rows = DB::connection('ledger')
            ->table('product_ledgers')
            ->whereIn('id', $maxIds)
            ->get(['product_id', 'balance_stock', 'price']);

        $balances = [];
        $prices = [];
        foreach ($rows as $row) {
            $pid = (int) $row->product_id;
            $balances[$pid] = (int) ($row->balance_stock ?? 0);
            $prices[$pid] = (float) ($row->price ?? 0);
        }

        return [$balances, $prices];
    }

    private function fetchSalesByYear(array $productIds, array $years, string $outColumn): array
    {
        if ($productIds === [] || $years === []) {
            return [];
        }

        $dateRanges = $this->buildYearDateRanges($years);
        $salesByYear = [];

        $outQuery = DB::connection('ledger')
            ->table('product_ledgers')
            ->whereIn('product_id', $productIds)
            ->whereRaw("UPPER(TRIM(COALESCE(transaction_type,''))) = 'OUT'")
            ->whereRaw('COALESCE(' . $outColumn . ', 0) > 0');

        $this->applyDateRangesFilter($outQuery, $dateRanges);
        $this->applySalesRemarkFilter($outQuery);

        $outRows = $outQuery
            ->selectRaw('product_id, YEAR(date) as sale_year, SUM(' . $outColumn . ') as total_out')
            ->groupBy('product_id')
            ->groupByRaw('YEAR(date)')
            ->get();

        foreach ($outRows as $row) {
            $pid = (int) ($row->product_id ?? 0);
            $yr = (int) ($row->sale_year ?? 0);
            if ($pid > 0 && $yr > 0) {
                $salesByYear[$pid][$yr] = (float) ($row->total_out ?? 0);
            }
        }

        $returnQuery = DB::connection('ledger')
            ->table('product_ledgers')
            ->whereIn('product_id', $productIds)
            ->whereRaw("UPPER(TRIM(COALESCE(transaction_type,''))) = 'IN'")
            ->whereRaw('COALESCE(quantity_in, 0) > 0')
            ->where(function ($q) {
                $q->whereRaw("LOWER(COALESCE(remarks,'')) LIKE '%salrtn%'")
                    ->orWhereRaw("LOWER(TRIM(COALESCE(remarks,''))) LIKE 'sales return%'")
                    ->orWhereRaw("LOWER(TRIM(COALESCE(remarks,''))) LIKE 'sales order edit - item removed%'");
            });

        $this->applyDateRangesFilter($returnQuery, $dateRanges);

        $returnRows = $returnQuery
            ->selectRaw('product_id, YEAR(date) as return_year, SUM(quantity_in) as return_qty')
            ->groupBy('product_id')
            ->groupByRaw('YEAR(date)')
            ->get();

        foreach ($returnRows as $row) {
            $pid = (int) ($row->product_id ?? 0);
            $yr = (int) ($row->return_year ?? 0);
            $qty = (float) ($row->return_qty ?? 0);
            if ($pid > 0 && $yr > 0 && $qty > 0) {
                $salesByYear[$pid][$yr] = ($salesByYear[$pid][$yr] ?? 0) - $qty;
            }
        }

        return $salesByYear;
    }

    private function fetchSalesOumByProduct(array $productIds, string $outColumn): array
    {
        $oumColumn = null;
        foreach (['oum', 'Oum', 'OUM'] as $candidate) {
            if ($this->hasLedgerColumn($candidate)) {
                $oumColumn = $candidate;
                break;
            }
        }

        if ($oumColumn === null) {
            return [];
        }

        $oumQuery = DB::connection('ledger')
            ->table('product_ledgers')
            ->whereIn('product_id', $productIds)
            ->whereRaw("TRIM(COALESCE({$oumColumn},'')) <> ''")
            ->whereRaw("UPPER(TRIM(COALESCE(transaction_type,''))) = 'OUT'")
            ->whereRaw('COALESCE(' . $outColumn . ', 0) > 0');

        $this->applySalesRemarkFilter($oumQuery);

        $rows = $oumQuery
            ->selectRaw("product_id, {$oumColumn} as oum, SUM(COALESCE({$outColumn}, 0)) as qty_weight")
            ->groupBy('product_id')
            ->groupBy($oumColumn)
            ->orderBy('qty_weight', 'desc')
            ->get(['product_id', 'oum', 'qty_weight']);

        $result = [];
        foreach ($rows as $row) {
            $pid = (int) ($row->product_id ?? 0);
            if ($pid > 0 && !isset($result[$pid])) {
                $result[$pid] = trim((string) ($row->oum ?? ''));
            }
        }

        return $result;
    }

    /**
     * Approved Cost Report sales remarks filter (W68_COST_REPORT_STRICT_SALES_REMARKS_20260926).
     */
    private function applySalesRemarkFilter($query): void
    {
        $query->where(function ($salesRemarkQuery) {
            $salesRemarkQuery
                ->whereRaw("LOWER(COALESCE(remarks,'')) LIKE '%chginvc%'")
                ->orWhereRaw("LOWER(COALESCE(remarks,'')) LIKE '%online report generation%'")
                ->orWhereRaw("LOWER(COALESCE(remarks,'')) LIKE '%new_system_sales%'")
                ->orWhereRaw("LOWER(COALESCE(remarks,'')) LIKE '%sales order processing%'")
                ->orWhereRaw("LOWER(TRIM(COALESCE(remarks,''))) = 'so'")
                ->orWhere(function ($salesOrderRemarkQuery) {
                    $salesOrderRemarkQuery
                        ->whereRaw("LOWER(TRIM(COALESCE(remarks,''))) LIKE 'sales order%'")
                        ->whereRaw("LOWER(TRIM(COALESCE(remarks,''))) NOT LIKE 'sales order edit - item removed%'");
                });
        });
    }

    /**
     * Merge consecutive selected years into sargable YYYY-01-01..YYYY-12-31 ranges
     * so MariaDB can use idx_product_ledgers_product_date_id on (product_id, date).
     */
    private function buildYearDateRanges(array $years): array
    {
        $sorted = array_values(array_unique(array_map('intval', $years)));
        sort($sorted);

        $ranges = [];
        foreach ($sorted as $year) {
            if ($year <= 0) {
                continue;
            }
            $lastIdx = count($ranges) - 1;
            if ($lastIdx >= 0 && $ranges[$lastIdx][1] === $year - 1) {
                $ranges[$lastIdx][1] = $year;
            } else {
                $ranges[] = [$year, $year];
            }
        }

        return array_map(
            static fn (array $pair) => [sprintf('%04d-01-01', $pair[0]), sprintf('%04d-12-31', $pair[1])],
            $ranges
        );
    }

    private function applyDateRangesFilter($query, array $dateRanges): void
    {
        if ($dateRanges === []) {
            return;
        }

        if (count($dateRanges) === 1) {
            $query->whereBetween('date', $dateRanges[0]);
            return;
        }

        $query->where(function ($q) use ($dateRanges) {
            foreach ($dateRanges as $range) {
                $q->orWhereBetween('date', $range);
            }
        });
    }

    private function resolveMasterlistUnitColumn(): ?string
    {
        foreach (['unit', 'Unit', 'UNIT'] as $candidate) {
            if ($this->hasMasterlistColumn($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function resolveProductSelectColumns(?string $unitColumn): array
    {
        $columns = ['id'];
        if ($unitColumn !== null) {
            $columns[] = $unitColumn;
        }

        // Explicitly exclude Product_Picture (LONGBLOB) and unused metadata columns.
        $candidates = [
            'product_code',
            'part_number',
            'category',
            'description',
            'application',
            'selling_price',
            'cost',
            'price_online',
        ];

        foreach ($candidates as $column) {
            if ($this->hasMasterlistColumn($column)) {
                $columns[] = $column;
            }
        }

        return array_values(array_unique($columns));
    }

    private function hasMasterlistColumn(string $column): bool
    {
        if (function_exists('hatdogSchemaHasColumnCached')) {
            return hatdogSchemaHasColumnCached('masterlist', 'products', $column);
        }

        return Cache::rememberForever(
            'hatdog_schema_has_column:masterlist:products:' . $column,
            static fn () => Schema::connection('masterlist')->hasColumn('products', $column)
        );
    }

    private function hasLedgerColumn(string $column): bool
    {
        if (function_exists('hatdogSchemaHasColumnCached')) {
            return hatdogSchemaHasColumnCached('ledger', 'product_ledgers', $column);
        }

        return Cache::rememberForever(
            'hatdog_schema_has_column:ledger:product_ledgers:' . $column,
            static fn () => Schema::connection('ledger')->hasColumn('product_ledgers', $column)
        );
    }

    private function isAuthorized(Request $request): bool
    {
        $user = session('user');
        if (!$user) {
            return false;
        }

        if (is_array($user)) {
            $user = (object) $user;
        }

        $allowedTypes = $request->route()?->defaults['allowed_types'] ?? null;
        if (is_array($allowedTypes) && $allowedTypes !== []) {
            return in_array((int) ($user->account_type ?? 0), $allowedTypes, true);
        }

        return true;
    }

    private function prepareHeavyRequest(): void
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(180);

        foreach (['mysql', 'system', 'masterlist', 'ledger', 'accounting', 'purchase', 'sales'] as $connection) {
            try {
                DB::connection($connection)->disableQueryLog();
            } catch (Throwable $ignored) {
            }
        }
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
