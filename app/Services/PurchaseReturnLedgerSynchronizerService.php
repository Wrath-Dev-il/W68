<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PurchaseReturnLedgerSynchronizerService
{
    public const SOURCE_TYPE = 'purchase_return';
    public const OUT_MODE = 'OUT';
    public const JUNK_MODE = 'JUNK';
    public const OUT_REMARKS = 'PURRTN';
    public const JUNK_REMARKS = 'JUNK';

    public function paginateMissing(string $dateFrom, string $dateTo, int $page = 1, int $perPage = 50): array
    {
        $this->assertSchemaReady();

        $page = max(1, $page);
        $perPage = max(10, min(100, $perPage));

        // HostForge keeps purchase, masterlist and ledger on separate MariaDB
        // services. Build the result in Laravel instead of issuing one SQL
        // statement that tries to join all three servers.
        $missing = $this->missingItems($dateFrom, $dateTo);
        $total = $missing->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $rows = $missing->slice(($page - 1) * $perPage, $perPage)->values();

        return [
            'success' => true,
            'data' => $rows->map(fn ($row) => [
                'purchase_return_item_id' => (int) $row->purchase_return_item_id,
                'purchase_return_id' => (int) $row->purchase_return_id,
                'product_id' => (int) $row->product_id,
                'return_number' => (string) $row->return_number,
                'slip_no' => (string) ($row->slip_no ?? ''),
                'supplier_name' => (string) ($row->supplier_name ?? ''),
                'product_code' => (string) ($row->product_code ?? ''),
                'part_number' => (string) ($row->part_number ?? ''),
                'quantity' => (int) ($row->quantity ?? 0),
                'out_quantity' => (int) ($row->out_quantity ?? 0),
                'oum' => (string) ($row->oum ?? ''),
                'unit_price' => (float) ($row->unit_price ?? 0),
                'return_date' => (string) $row->return_date,
                'return_date_display' => $this->displayDate((string) $row->return_date),
                'status' => (string) ($row->status ?? ''),
            ])->all(),
            'pagination' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    public function syncItem(int $purchaseReturnItemId, string $mode): array
    {
        $this->assertSchemaReady();
        $mode = $this->normalizeMode($mode);

        $result = $this->syncItemInternal($purchaseReturnItemId, $mode);
        if (!empty($result['product_id'])) {
            ProductStockSyncService::syncProduct((int) $result['product_id']);
        }

        return $result;
    }

    public function syncRange(string $dateFrom, string $dateTo, string $mode): array
    {
        $this->assertSchemaReady();
        $mode = $this->normalizeMode($mode);

        $synced = 0;
        $alreadySynced = 0;
        $affectedProducts = [];
        $ledgerIds = [];

        // Resolve the missing set once using separate purchase + ledger reads.
        // syncItemInternal() is still idempotent, so a concurrent sync remains safe.
        $ids = $this->missingItems($dateFrom, $dateTo)
            ->pluck('purchase_return_item_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        foreach ($ids as $itemId) {
            $result = $this->syncItemInternal((int) $itemId, $mode);
            if (!empty($result['synced'])) {
                $synced++;
            } else {
                $alreadySynced++;
            }

            if (!empty($result['product_id'])) {
                $affectedProducts[(int) $result['product_id']] = true;
            }
            if (!empty($result['ledger_id'])) {
                $ledgerIds[] = (int) $result['ledger_id'];
            }
        }

        $productIds = array_values(array_map('intval', array_keys($affectedProducts)));
        if ($productIds !== []) {
            ProductStockSyncService::syncProducts($productIds, 500);
        }

        return [
            'success' => true,
            'synced' => $synced,
            'already_synced' => $alreadySynced,
            'affected_products' => count($productIds),
            'ledger_ids' => $ledgerIds,
            'mode' => $mode,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    private function syncItemInternal(int $purchaseReturnItemId, string $mode): array
    {
        $source = $this->sourceItem($purchaseReturnItemId);
        if (!$source) {
            throw new \RuntimeException('Purchase Return item not found.');
        }

        $quantity = max(0, (int) ($source->quantity ?? 0));
        if ($quantity <= 0) {
            throw new \RuntimeException('Purchase Return quantity must be greater than zero before it can be synchronized.');
        }

        $returnDate = trim((string) ($source->return_date ?? ''));
        if ($returnDate === '') {
            throw new \RuntimeException('Purchase Return date is missing.');
        }

        $result = DB::connection('ledger')->transaction(function () use ($source, $quantity, $returnDate, $mode) {
            $existing = $this->existingLedgerRow($source);
            if ($existing) {
                return [
                    'success' => true,
                    'synced' => false,
                    'already_synced' => true,
                    'ledger_id' => (int) $existing->id,
                    'product_id' => (int) $source->product_id,
                    'return_number' => (string) $source->return_number,
                    'return_date' => $returnDate,
                    'mode' => $mode,
                ];
            }

            $productId = (int) $source->product_id;
            $balanceBefore = $this->balanceImmediatelyBeforeNewRow($productId, $returnDate);
            $isOut = $mode === self::OUT_MODE;
            $balanceAfter = $isOut ? $balanceBefore - $quantity : $balanceBefore;
            $createdAt = $this->ledgerCreatedAt($returnDate, $source->return_created_at ?? null);

            $ledgerId = DB::connection('ledger')->table('product_ledgers')->insertGetId([
                'product_id' => $productId,
                'supplier_id' => (int) ($source->supplier_id ?? 0) ?: null,
                'customer_id' => null,
                'source_type' => self::SOURCE_TYPE,
                'source_id' => (int) $source->purchase_return_id,
                'source_item_id' => (int) $source->purchase_return_item_id,
                'processed_actual_qty' => 0,
                'transaction_type' => $mode,
                // CRITICAL: preserve the source Purchase Return date exactly.
                // Example: 01-08-2026 stays 2026-08-01 in the DB date column
                // and displays again as 01-08-2026 in the Control Panel.
                'date' => $returnDate,
                'transaction_number' => (string) $source->return_number,
                'reference_number' => trim((string) ($source->supplier_invoice_number ?? '')) ?: null,
                'entity_name' => trim((string) ($source->supplier_name ?? '')) ?: 'N/A',
                'quantity_in' => 0,
                'quantity_out' => $isOut ? $quantity : 0,
                'junk' => $isOut ? 0 : $quantity,
                'balance_stock' => $balanceAfter,
                'oum' => (string) ($source->oum ?? ''),
                'remarks' => $isOut ? self::OUT_REMARKS : self::JUNK_REMARKS,
                'idempotency_key' => sprintf(
                    'purchase_return:%d:item:%d:%s',
                    (int) $source->purchase_return_id,
                    (int) $source->purchase_return_item_id,
                    strtolower($mode)
                ),
                // Keep the timestamp date on the Purchase Return date too so
                // a historical sync does not look like a transaction created today.
                'created_at' => $createdAt,
                'updated_at' => now('Asia/Manila'),
            ]);

            if ($isOut) {
                // OUT physically removes stock. Only rows after this historical
                // position are shifted. JUNK intentionally has no stock effect.
                $this->shiftBalancesAfter($productId, $returnDate, (int) $ledgerId, -$quantity);
            }

            return [
                'success' => true,
                'synced' => true,
                'already_synced' => false,
                'ledger_id' => (int) $ledgerId,
                'product_id' => $productId,
                'return_number' => (string) $source->return_number,
                'return_date' => $returnDate,
                'mode' => $mode,
                'quantity' => $quantity,
                'balance_stock' => $balanceAfter,
            ];
        });

        // Keep the Purchase Return's OUT classification consistent with the
        // developer's historical sync choice so a future Purchase Return edit
        // does not silently reinterpret the same item.
        if (!empty($result['synced'])) {
            try {
                DB::connection('purchase')->table('purchase_return_items')
                    ->where('id', (int) $source->purchase_return_item_id)
                    ->update([
                        'out_quantity' => $mode === self::OUT_MODE ? $quantity : 0,
                        'updated_at' => now('Asia/Manila'),
                    ]);
            } catch (\Throwable $e) {
                Log::warning('Purchase Return synchronizer could not update out_quantity classification.', [
                    'purchase_return_item_id' => (int) $source->purchase_return_item_id,
                    'mode' => $mode,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /**
     * Return missing Purchase Return items without any cross-server SQL.
     */
    private function missingItems(string $dateFrom, string $dateTo)
    {
        $candidates = DB::connection('purchase')
            ->table('purchase_return_items as pri')
            ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'pr.po_id')
            ->where('pri.quantity', '>', 0)
            ->whereBetween('pr.date', [$dateFrom, $dateTo])
            ->orderBy('pr.date')
            ->orderBy('pr.id')
            ->orderBy('pri.id')
            ->get([
                'pri.id as purchase_return_item_id',
                'pri.purchase_return_id',
                'pri.product_id',
                'pri.product_code',
                'pri.quantity',
                'pri.out_quantity',
                'pri.oum',
                'pri.unit_price',
                'pr.return_number',
                'pr.slip_no',
                'pr.supplier_id',
                'po.supplier_invoice_number',
                'pr.date as return_date',
                'pr.created_at as return_created_at',
                'pr.status',
            ]);

        if ($candidates->isEmpty()) {
            return collect();
        }

        $missing = collect();
        foreach ($candidates->chunk(500) as $chunk) {
            $matchedItemIds = $this->matchedLedgerItemIds($chunk);
            foreach ($chunk as $row) {
                if (!isset($matchedItemIds[(int) $row->purchase_return_item_id])) {
                    $missing->push($row);
                }
            }
        }

        return $this->enrichMasterlistRows($missing);
    }

    /**
     * Find which purchase-return source rows already have an OUT/JUNK ledger row.
     * All SQL in this method runs only on the ledger connection.
     *
     * @return array<int,bool>
     */
    private function matchedLedgerItemIds($rows): array
    {
        $rows = collect($rows)->values();
        if ($rows->isEmpty()) {
            return [];
        }

        $productIds = $rows->pluck('product_id')->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        $sourceIds = $rows->pluck('purchase_return_id')->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        $itemIds = $rows->pluck('purchase_return_item_id')->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        $returnNumbers = $rows->pluck('return_number')->map(fn ($v) => trim((string) $v))->filter()->unique()->values()->all();
        $idempotencyKeys = $rows->flatMap(function ($row) {
            $returnId = (int) $row->purchase_return_id;
            $itemId = (int) $row->purchase_return_item_id;
            return [
                "purchase_return:{$returnId}:item:{$itemId}:out",
                "purchase_return:{$returnId}:item:{$itemId}:junk",
            ];
        })->values()->all();

        if ($productIds === []) {
            return [];
        }

        $ledgerRows = DB::connection('ledger')
            ->table('product_ledgers')
            ->whereIn('product_id', $productIds)
            ->where(function ($query) use ($sourceIds, $itemIds, $idempotencyKeys, $returnNumbers) {
                if ($sourceIds !== [] && $itemIds !== []) {
                    $query->where(function ($sourceLinked) use ($sourceIds, $itemIds) {
                        $sourceLinked->where('source_type', self::SOURCE_TYPE)
                            ->whereIn('source_id', $sourceIds)
                            ->whereIn('source_item_id', $itemIds);
                    });
                } else {
                    $query->whereRaw('1 = 0');
                }

                if ($idempotencyKeys !== []) {
                    $query->orWhereIn('idempotency_key', $idempotencyKeys);
                }

                if ($returnNumbers !== []) {
                    $query->orWhere(function ($legacy) use ($returnNumbers) {
                        $legacy->where(function ($numberMatch) use ($returnNumbers) {
                            $numberMatch->whereIn('transaction_number', $returnNumbers)
                                ->orWhereIn('reference_number', $returnNumbers);
                        })->where(function ($purchaseReturnRemark) {
                            $purchaseReturnRemark
                                ->whereRaw("UPPER(TRIM(COALESCE(remarks, ''))) IN ('PURRTN', 'JUNK')")
                                ->orWhereRaw("UPPER(COALESCE(remarks, '')) LIKE '%TYPE=PURRTN%'");
                        });
                    });
                }
            })
            ->get([
                'product_id', 'source_type', 'source_id', 'source_item_id',
                'idempotency_key', 'transaction_number', 'reference_number', 'remarks',
            ])
            ->groupBy(fn ($row) => (int) $row->product_id);

        $matched = [];
        foreach ($rows as $source) {
            $productLedgerRows = $ledgerRows->get((int) $source->product_id, collect());
            $outKey = sprintf('purchase_return:%d:item:%d:out', (int) $source->purchase_return_id, (int) $source->purchase_return_item_id);
            $junkKey = sprintf('purchase_return:%d:item:%d:junk', (int) $source->purchase_return_id, (int) $source->purchase_return_item_id);
            $sourceNumber = $this->normalizeText($source->return_number ?? '');

            $exists = $productLedgerRows->contains(function ($ledger) use ($source, $outKey, $junkKey, $sourceNumber) {
                if ((string) ($ledger->source_type ?? '') === self::SOURCE_TYPE
                    && (int) ($ledger->source_id ?? 0) === (int) $source->purchase_return_id
                    && (int) ($ledger->source_item_id ?? 0) === (int) $source->purchase_return_item_id) {
                    return true;
                }

                if (in_array((string) ($ledger->idempotency_key ?? ''), [$outKey, $junkKey], true)) {
                    return true;
                }

                $remarks = strtoupper(trim((string) ($ledger->remarks ?? '')));
                $isPurchaseReturnRemark = in_array($remarks, [self::OUT_REMARKS, self::JUNK_REMARKS], true)
                    || str_contains($remarks, 'TYPE=PURRTN');
                if (!$isPurchaseReturnRemark) {
                    return false;
                }

                return $sourceNumber !== '' && in_array($sourceNumber, [
                    $this->normalizeText($ledger->transaction_number ?? ''),
                    $this->normalizeText($ledger->reference_number ?? ''),
                ], true);
            });

            if ($exists) {
                $matched[(int) $source->purchase_return_item_id] = true;
            }
        }

        return $matched;
    }

    private function enrichMasterlistRows($rows)
    {
        $rows = collect($rows)->values();
        if ($rows->isEmpty()) {
            return $rows;
        }

        $suppliers = DB::connection('masterlist')->table('suppliers')
            ->whereIn('id', $rows->pluck('supplier_id')->map(fn ($id) => (int) $id)->filter()->unique()->values()->all())
            ->pluck('name', 'id');

        $products = DB::connection('masterlist')->table('products')
            ->whereIn('id', $rows->pluck('product_id')->map(fn ($id) => (int) $id)->filter()->unique()->values()->all())
            ->get(['id', 'part_number'])
            ->keyBy('id');

        return $rows->map(function ($row) use ($suppliers, $products) {
            $row->supplier_name = (string) ($suppliers->get((int) $row->supplier_id) ?? '');
            $row->part_number = (string) ($products->get((int) $row->product_id)->part_number ?? '');
            return $row;
        })->values();
    }

    private function sourceItem(int $purchaseReturnItemId): ?object
    {
        $row = DB::connection('purchase')
            ->table('purchase_return_items as pri')
            ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'pr.po_id')
            ->where('pri.id', $purchaseReturnItemId)
            ->first([
                'pri.id as purchase_return_item_id',
                'pri.purchase_return_id',
                'pri.product_id',
                'pri.product_code',
                'pri.quantity',
                'pri.out_quantity',
                'pri.oum',
                'pri.unit_price',
                'pr.return_number',
                'pr.slip_no',
                'pr.supplier_id',
                'po.supplier_invoice_number',
                'pr.date as return_date',
                'pr.created_at as return_created_at',
                'pr.status',
            ]);

        if (!$row) {
            return null;
        }

        $supplier = DB::connection('masterlist')->table('suppliers')
            ->where('id', (int) $row->supplier_id)
            ->first(['name']);
        $product = DB::connection('masterlist')->table('products')
            ->where('id', (int) $row->product_id)
            ->first(['part_number']);

        $row->supplier_name = (string) ($supplier->name ?? '');
        $row->part_number = (string) ($product->part_number ?? '');

        return $row;
    }

    private function existingLedgerRow(object $source): ?object
    {
        return DB::connection('ledger')
            ->table('product_ledgers')
            ->where('product_id', (int) $source->product_id)
            ->where(function ($query) use ($source) {
                $query->where(function ($sourceLinked) use ($source) {
                    $sourceLinked->where('source_type', self::SOURCE_TYPE)
                        ->where('source_id', (int) $source->purchase_return_id)
                        ->where('source_item_id', (int) $source->purchase_return_item_id);
                })->orWhereIn('idempotency_key', [
                    sprintf('purchase_return:%d:item:%d:out', (int) $source->purchase_return_id, (int) $source->purchase_return_item_id),
                    sprintf('purchase_return:%d:item:%d:junk', (int) $source->purchase_return_id, (int) $source->purchase_return_item_id),
                ])->orWhere(function ($legacy) use ($source) {
                    $legacy->where(function ($numberMatch) use ($source) {
                        $numberMatch->where('transaction_number', (string) $source->return_number)
                            ->orWhere('reference_number', (string) $source->return_number);
                    })->where(function ($purchaseReturnRemark) {
                        $purchaseReturnRemark
                            ->whereRaw("UPPER(TRIM(COALESCE(remarks, ''))) IN ('PURRTN', 'JUNK')")
                            ->orWhereRaw("UPPER(COALESCE(remarks, '')) LIKE '%TYPE=PURRTN%'");
                    });
                });
            })
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first(['id', 'product_id', 'date', 'transaction_type', 'quantity_out', 'junk', 'balance_stock']);
    }

    private function balanceImmediatelyBeforeNewRow(int $productId, string $date): int
    {
        $row = DB::connection('ledger')
            ->table('product_ledgers')
            ->where('product_id', $productId)
            ->where('date', '<=', $date)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first(['balance_stock']);

        return (int) ($row->balance_stock ?? 0);
    }

    private function shiftBalancesAfter(int $productId, string $date, int $rowId, int $delta): void
    {
        if ($delta === 0) {
            return;
        }

        DB::connection('ledger')
            ->table('product_ledgers')
            ->where('product_id', $productId)
            ->where(function ($query) use ($date, $rowId) {
                $query->where('date', '>', $date)
                    ->orWhere(function ($sameDate) use ($date, $rowId) {
                        $sameDate->where('date', $date)->where('id', '>', $rowId);
                    });
            })
            ->update([
                'balance_stock' => DB::raw('balance_stock + (' . (int) $delta . ')'),
                'updated_at' => now('Asia/Manila'),
            ]);
    }

    private function ledgerCreatedAt(string $returnDate, $sourceCreatedAt): string
    {
        $time = '00:00:00';
        if ($sourceCreatedAt) {
            try {
                $time = Carbon::parse($sourceCreatedAt)->format('H:i:s');
            } catch (\Throwable $e) {
                $time = '00:00:00';
            }
        }

        return $returnDate . ' ' . $time;
    }

    private function displayDate(string $date): string
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $date)->format('d-m-Y');
        } catch (\Throwable $e) {
            return $date;
        }
    }

    private function normalizeMode(string $mode): string
    {
        $mode = strtoupper(trim($mode));
        if (!in_array($mode, [self::OUT_MODE, self::JUNK_MODE], true)) {
            throw new \InvalidArgumentException('Purchase Return sync mode must be OUT or JUNK.');
        }

        return $mode;
    }

    private function assertSchemaReady(): void
    {
        if (!Schema::connection('purchase')->hasTable('purchase_returns')
            || !Schema::connection('purchase')->hasTable('purchase_return_items')) {
            throw new \RuntimeException('Purchase Return tables are missing from core4_purchase.');
        }

        if (!Schema::connection('purchase')->hasColumn('purchase_return_items', 'out_quantity')) {
            throw new \RuntimeException('purchase_return_items.out_quantity is missing. Run the Purchase Return OUT/JUNK migration first.');
        }

        foreach (['junk', 'source_type', 'source_id', 'source_item_id', 'idempotency_key'] as $column) {
            if (!Schema::connection('ledger')->hasColumn('product_ledgers', $column)) {
                throw new \RuntimeException("product_ledgers.{$column} is missing. Run the latest ledger migration first.");
            }
        }
    }

    private function normalizeText($value): string
    {
        return mb_strtolower(trim((string) $value));
    }
}
