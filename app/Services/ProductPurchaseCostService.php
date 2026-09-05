<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ProductPurchaseCostService
{
    public function normalizeProductCode($productCode): string
    {
        return strtolower(trim((string) $productCode));
    }

    /**
     * Return the latest Purchase Order cost for every requested product.
     * Missing legacy ledger tables are treated as "cost not found" instead of
     * crashing the Sales Order page.
     *
     * @return array{by_product_id: array, by_product_code: array}
     */
    public function getLatestPurchaseCosts(array $productIds = [], array $productCodes = []): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds), fn ($id) => $id > 0)));
        $productCodes = array_values(array_unique(array_filter(array_map(fn ($code) => trim((string) $code), $productCodes), fn ($code) => $code !== '')));

        $result = ['by_product_id' => [], 'by_product_code' => []];
        if ($productIds === [] && $productCodes === []) {
            return $result;
        }

        try {
            if (!Schema::connection('ledger')->hasTable('supplier_ledger_items') ||
                !Schema::connection('ledger')->hasTable('supplier_ledgers')) {
                return $result;
            }

            $query = DB::connection('ledger')
                ->table('supplier_ledger_items as sli')
                ->join('supplier_ledgers as sl', 'sl.id', '=', 'sli.supplier_ledger_id')
                ->whereRaw('TRIM(LOWER(sl.module_type)) = ?', ['purchase order'])
                ->whereNotNull('sli.unit_price')
                ->where('sli.unit_price', '>=', 0)
                ->where(function ($q) use ($productIds, $productCodes) {
                    if ($productIds !== []) {
                        $q->whereIn('sli.product_id', $productIds);
                    }
                    if ($productCodes !== []) {
                        $method = $productIds !== [] ? 'orWhereIn' : 'whereIn';
                        $q->{$method}(DB::raw('TRIM(sli.product_code)'), $productCodes);
                    }
                })
                ->orderByDesc('sl.date')
                ->orderByDesc('sl.created_at')
                ->orderByDesc('sl.id')
                ->orderByDesc('sli.id')
                ->get([
                    'sli.id as supplier_ledger_item_id',
                    'sli.supplier_ledger_id',
                    'sli.product_id',
                    'sli.product_code',
                    'sli.description',
                    'sli.quantity',
                    'sli.actual_quantity',
                    'sli.unit_price',
                    'sli.created_at as supplier_ledger_item_created_at',
                    'sl.supplier_id',
                    'sl.date as purchase_date',
                    'sl.transaction_code',
                    'sl.module_type',
                    'sl.created_at as supplier_ledger_created_at',
                ]);

            $supplierIds = $query->pluck('supplier_id')->filter()->unique()->map(fn ($id) => (int) $id)->all();
            $supplierNames = [];
            if ($supplierIds !== [] && Schema::connection('masterlist')->hasTable('suppliers')) {
                $supplierNameColumn = Schema::connection('masterlist')->hasColumn('suppliers', 'name')
                    ? 'name'
                    : (Schema::connection('masterlist')->hasColumn('suppliers', 'supplier_name') ? 'supplier_name' : null);

                if ($supplierNameColumn !== null) {
                    $supplierNames = DB::connection('masterlist')->table('suppliers')
                        ->whereIn('id', $supplierIds)
                        ->pluck($supplierNameColumn, 'id')
                        ->mapWithKeys(fn ($name, $id) => [(int) $id => (string) $name])
                        ->all();
                }
            }

            foreach ($query as $row) {
                $productId = (int) ($row->product_id ?? 0);
                $codeKey = $this->normalizeProductCode($row->product_code ?? '');
                $supplierId = (int) ($row->supplier_id ?? 0);
                $cost = [
                    'product_id' => $productId ?: null,
                    'product_code' => trim((string) ($row->product_code ?? '')),
                    'unit_price' => (float) ($row->unit_price ?? 0),
                    'purchase_date' => $row->purchase_date ?? null,
                    'supplier_ledger_item_created_at' => $row->supplier_ledger_item_created_at ?? null,
                    'supplier_id' => $supplierId ?: null,
                    'supplier_name' => $supplierNames[$supplierId] ?? 'N/A',
                    'supplier_ledger_item_id' => (int) $row->supplier_ledger_item_id,
                    'supplier_ledger_id' => (int) $row->supplier_ledger_id,
                    'transaction_code' => $row->transaction_code ?? null,
                    'actual_quantity' => isset($row->actual_quantity) ? (float) $row->actual_quantity : null,
                    'quantity' => isset($row->quantity) ? (float) $row->quantity : null,
                    'description' => $row->description ?? null,
                ];

                if ($productId > 0 && !isset($result['by_product_id'][$productId])) {
                    $result['by_product_id'][$productId] = $cost;
                }
                if ($codeKey !== '' && !isset($result['by_product_code'][$codeKey])) {
                    $result['by_product_code'][$codeKey] = $cost;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Product purchase-cost lookup failed; Sales Order will continue without cost data.', [
                'message' => $e->getMessage(),
            ]);
        }

        return $result;
    }
}
