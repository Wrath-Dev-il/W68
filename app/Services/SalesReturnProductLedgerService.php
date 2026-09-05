<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SalesReturnProductLedgerService
{
    public const SOURCE_TYPE = 'sales_return';
    public const GOOD_REMARKS = 'Sales Return Good Items';
    public const JUNK_REMARKS = 'JUNK';

    /**
     * Rebuild all product-ledger rows owned by one Sales Return.
     *
     * Sales Return quantity is split as:
     *   GOOD = good_qty -> IN stock
     *   JUNK = quantity - good_qty -> informational only, no stock effect
     *
     * Examples:
     *   Return QTY 4 / Good 3 => IN 3 + JUNK 1
     *   Return QTY 4 / Good 0 => JUNK 4 only; balance_stock does not change
     *   Return QTY 4 / Good 4 => IN 4 only
     *
     * Existing later balances are shifted only by the IN difference so edits
     * affect the latest balance_stock without rebuilding unrelated history.
     *
     * @return int[] affected product ids
     */
    public function replaceForReturn(int $salesReturnId): array
    {
        $this->assertSchemaReady();

        $header = DB::connection('sales')
            ->table('sales_returns')
            ->where('id', $salesReturnId)
            ->first();

        if (!$header) {
            throw new \RuntimeException('Sales Return not found.');
        }

        $items = DB::connection('sales')
            ->table('sales_return_items')
            ->where('sales_return_id', $salesReturnId)
            ->orderBy('id')
            ->get();

        $productIds = $items
            ->pluck('product_id')
            ->map(fn ($value) => (int) $value)
            ->filter(fn ($value) => $value > 0)
            ->unique()
            ->values();

        $products = $productIds->isEmpty()
            ? collect()
            : DB::connection('masterlist')
                ->table('products')
                ->whereIn('id', $productIds->all())
                ->select('id', 'cost', 'unit')
                ->get()
                ->keyBy(fn ($product) => (int) $product->id);

        return DB::connection('ledger')->transaction(function () use ($header, $items, $products, $salesReturnId) {
            $affectedProductIds = [];

            // Remove the old Sales Return stock effect first. An old IN is
            // subtracted from every later balance; old JUNK has no stock effect.
            $existingRows = $this->ownedRowsQuery($header, $salesReturnId)
                ->orderBy('date')
                ->orderBy('id')
                ->get(['id', 'product_id', 'date', 'quantity_in', 'quantity_out', 'created_at']);

            foreach ($existingRows as $row) {
                $productId = (int) ($row->product_id ?? 0);
                if ($productId <= 0) {
                    continue;
                }

                $affectedProductIds[$productId] = true;

                // Reverse the exact stock effect of the old row.
                $reverseDelta = max(0, (int) ($row->quantity_out ?? 0))
                    - max(0, (int) ($row->quantity_in ?? 0));

                if ($reverseDelta !== 0) {
                    $this->shiftBalancesAfter(
                        $productId,
                        (string) $row->date,
                        $row->created_at ?? null,
                        (int) $row->id,
                        $reverseDelta
                    );
                }
            }

            if ($existingRows->isNotEmpty()) {
                DB::connection('ledger')
                    ->table('product_ledgers')
                    ->whereIn('id', $existingRows->pluck('id')->all())
                    ->delete();
            }

            try {
                $returnDate = Carbon::parse($header->created_at)->toDateString();
            } catch (\Throwable $dateError) {
                $returnDate = now()->toDateString();
            }

            try {
                $ledgerCreatedAt = Carbon::parse($header->created_at ?: now());
            } catch (\Throwable $createdAtError) {
                $ledgerCreatedAt = now();
            }
            $customerName = trim((string) ($header->customer_name ?? ''));
            $customerId = !empty($header->customer_id) ? (int) $header->customer_id : null;
            $invoiceNo = trim((string) ($header->invoice_no ?? ''));
            $returnNo = trim((string) ($header->return_number ?? ''));

            foreach ($items as $item) {
                $productId = (int) ($item->product_id ?? 0);
                if ($productId <= 0) {
                    continue;
                }

                $returnQty = max(0, (int) ($item->quantity ?? 0));
                $goodQty = max(0, (int) ($item->good_qty ?? 0));
                $goodQty = min($goodQty, $returnQty);
                $junkQty = max(0, $returnQty - $goodQty);

                if ($returnQty <= 0 && $goodQty <= 0 && $junkQty <= 0) {
                    continue;
                }

                $affectedProductIds[$productId] = true;
                $product = $products->get($productId);
                $oum = trim((string) ($product->unit ?? '')) ?: 'PCS';
                $unitPrice = (float) ($item->unit_price ?? 0);
                $cost = (float) ($product->cost ?? 0);
                $itemRemarks = trim((string) ($item->remarks ?? ''));

                if ($goodQty > 0) {
                    $balanceBeforeIn = $this->balanceImmediatelyBeforeNewRow($productId, $returnDate, $ledgerCreatedAt);
                    $balanceAfterIn = $balanceBeforeIn + $goodQty;

                    $inLedgerId = DB::connection('ledger')->table('product_ledgers')->insertGetId([
                        'product_id' => $productId,
                        'supplier_id' => null,
                        'customer_id' => $customerId,
                        'source_type' => self::SOURCE_TYPE,
                        'source_id' => $salesReturnId,
                        'source_item_id' => (int) $item->id,
                        'processed_actual_qty' => 0,
                        'transaction_type' => 'IN',
                        'date' => $returnDate,
                        'transaction_number' => $returnNo,
                        'reference_number' => $invoiceNo,
                        'entity_name' => $customerName,
                        'quantity_in' => $goodQty,
                        'quantity_out' => 0,
                        'junk' => 0,
                        'balance_stock' => $balanceAfterIn,
                        'oum' => $oum,
                        'price' => $unitPrice,
                        'cost' => $cost,
                        'remarks' => trim(self::GOOD_REMARKS . ($itemRemarks !== '' ? ' - ' . $itemRemarks : '')),
                        'idempotency_key' => $this->idempotencyKey($salesReturnId, $item->id, 'in'),
                        'created_at' => $ledgerCreatedAt,
                        'updated_at' => now(),
                    ]);

                    // The IN row adds usable stock, so every row after its exact
                    // ledger position must also increase by the same quantity.
                    $this->shiftBalancesAfter(
                        $productId,
                        $returnDate,
                        $ledgerCreatedAt,
                        (int) $inLedgerId,
                        $goodQty
                    );
                }

                if ($junkQty > 0) {
                    // JUNK is a separate historical ledger row only. It copies
                    // the current balance and never changes later balances.
                    $junkBalance = $this->balanceImmediatelyBeforeNewRow($productId, $returnDate, $ledgerCreatedAt);

                    DB::connection('ledger')->table('product_ledgers')->insert([
                        'product_id' => $productId,
                        'supplier_id' => null,
                        'customer_id' => $customerId,
                        'source_type' => self::SOURCE_TYPE,
                        'source_id' => $salesReturnId,
                        'source_item_id' => (int) $item->id,
                        'processed_actual_qty' => 0,
                        'transaction_type' => 'JUNK',
                        'date' => $returnDate,
                        'transaction_number' => $returnNo,
                        'reference_number' => $invoiceNo,
                        'entity_name' => $customerName,
                        'quantity_in' => 0,
                        'quantity_out' => 0,
                        'junk' => $junkQty,
                        'balance_stock' => $junkBalance,
                        'oum' => $oum,
                        'price' => $unitPrice,
                        'cost' => $cost,
                        'remarks' => self::JUNK_REMARKS,
                        'idempotency_key' => $this->idempotencyKey($salesReturnId, $item->id, 'junk'),
                        'created_at' => $ledgerCreatedAt,
                        'updated_at' => now(),
                    ]);
                }
            }

            return array_values(array_map('intval', array_keys($affectedProductIds)));
        });
    }

    /**
     * Delete all Sales Return product-ledger rows and reverse only their stock
     * effect. JUNK rows are removed without changing balance_stock.
     *
     * @return int[] affected product ids
     */
    public function deleteForReturn(int $salesReturnId): array
    {
        $this->assertSchemaReady();

        $header = DB::connection('sales')
            ->table('sales_returns')
            ->where('id', $salesReturnId)
            ->first();

        if (!$header) {
            return [];
        }

        return DB::connection('ledger')->transaction(function () use ($header, $salesReturnId) {
            $rows = $this->ownedRowsQuery($header, $salesReturnId)
                ->orderBy('date')
                ->orderBy('id')
                ->get(['id', 'product_id', 'date', 'quantity_in', 'quantity_out', 'created_at']);

            if ($rows->isEmpty()) {
                return [];
            }

            $affectedProductIds = [];

            foreach ($rows as $row) {
                $productId = (int) ($row->product_id ?? 0);
                if ($productId <= 0) {
                    continue;
                }

                $affectedProductIds[$productId] = true;
                $reverseDelta = max(0, (int) ($row->quantity_out ?? 0))
                    - max(0, (int) ($row->quantity_in ?? 0));

                if ($reverseDelta !== 0) {
                    $this->shiftBalancesAfter(
                        $productId,
                        (string) $row->date,
                        $row->created_at ?? null,
                        (int) $row->id,
                        $reverseDelta
                    );
                }
            }

            DB::connection('ledger')
                ->table('product_ledgers')
                ->whereIn('id', $rows->pluck('id')->all())
                ->delete();

            return array_values(array_map('intval', array_keys($affectedProductIds)));
        });
    }

    private function ownedRowsQuery(object $header, int $salesReturnId)
    {
        $returnNo = trim((string) ($header->return_number ?? ''));

        return DB::connection('ledger')
            ->table('product_ledgers')
            ->where(function ($query) use ($salesReturnId, $returnNo) {
                $query->where(function ($owned) use ($salesReturnId) {
                    $owned->where('source_type', self::SOURCE_TYPE)
                        ->where('source_id', $salesReturnId);
                });

                if ($returnNo !== '') {
                    $query->orWhere(function ($legacy) use ($returnNo) {
                        $legacy->where('transaction_number', $returnNo)
                            ->where(function ($kind) {
                                $kind->where('remarks', 'like', self::GOOD_REMARKS . '%')
                                    ->orWhere(function ($junk) {
                                        $junk->where('transaction_type', 'JUNK')
                                            ->where('remarks', self::JUNK_REMARKS);
                                    });
                            });
                    });
                }
            });
    }

    /**
     * Return the balance immediately before the Sales Return's chronological
     * position. Legacy rows can have old IDs even when they occur later on the
     * same date, so created_at must be considered before the ID fallback.
     */
    private function balanceImmediatelyBeforeNewRow(int $productId, string $date, $createdAt): int
    {
        if ($productId <= 0 || $date === '') {
            return 0;
        }

        try {
            $createdAtValue = Carbon::parse($createdAt ?: ($date . ' 23:59:59'));
        } catch (\Throwable $createdAtError) {
            $createdAtValue = Carbon::parse($date . ' 23:59:59');
        }

        $row = DB::connection('ledger')
            ->table('product_ledgers')
            ->where('product_id', $productId)
            ->where(function ($query) use ($date, $createdAtValue) {
                $query->where('date', '<', $date)
                    ->orWhere(function ($sameDate) use ($date, $createdAtValue) {
                        $sameDate->where('date', $date)
                            ->where(function ($time) use ($createdAtValue) {
                                // Imported rows without created_at are treated
                                // as earlier rows for that date. Rows with a
                                // timestamp are included only up to the return.
                                $time->whereNull('created_at')
                                    ->orWhere('created_at', '<=', $createdAtValue);
                            });
                    });
            })
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first(['balance_stock']);

        return (int) ($row->balance_stock ?? 0);
    }

    /**
     * Shift only rows that are chronologically after one exact Sales Return
     * ledger row. JUNK never calls this method, so it never changes stock.
     *
     * Using created_at before the ID fallback is important for the legacy
     * CHGRR/other imported rows: a newly inserted return always has a newer ID,
     * but an older same-day transaction must still stay before it if its
     * timestamp says so.
     */
    private function shiftBalancesAfter(int $productId, string $date, $createdAt, int $rowId, int $delta): void
    {
        if ($productId <= 0 || $date === '' || $rowId <= 0 || $delta === 0) {
            return;
        }

        try {
            $createdAtValue = Carbon::parse($createdAt ?: ($date . ' 23:59:59'));
        } catch (\Throwable $createdAtError) {
            $createdAtValue = Carbon::parse($date . ' 23:59:59');
        }

        DB::connection('ledger')
            ->table('product_ledgers')
            ->where('product_id', $productId)
            ->where(function ($query) use ($date, $createdAtValue, $rowId) {
                $query->where('date', '>', $date)
                    ->orWhere(function ($sameDate) use ($date, $createdAtValue, $rowId) {
                        $sameDate->where('date', $date)
                            ->where(function ($after) use ($createdAtValue, $rowId) {
                                $after->where('created_at', '>', $createdAtValue)
                                    ->orWhere(function ($sameMoment) use ($createdAtValue, $rowId) {
                                        $sameMoment->where('created_at', $createdAtValue)
                                            ->where('id', '>', $rowId);
                                    });
                            });
                    });
            })
            ->update([
                'balance_stock' => DB::raw('balance_stock + (' . (int) $delta . ')'),
                'updated_at' => now(),
            ]);
    }

    private function idempotencyKey(int $salesReturnId, $itemId, string $kind): string
    {
        return sprintf(
            'sales_return:%d:item:%d:%s',
            $salesReturnId,
            (int) $itemId,
            $kind
        );
    }

    private function assertSchemaReady(): void
    {
        if (!Schema::connection('sales')->hasColumn('sales_return_items', 'good_qty')) {
            throw new \RuntimeException('sales_return_items.good_qty is missing. Run the Sales Return migration first.');
        }

        if (!Schema::connection('ledger')->hasColumn('product_ledgers', 'junk')) {
            throw new \RuntimeException('product_ledgers.junk is missing. Run the JUNK column SQL migration first.');
        }
    }
}
