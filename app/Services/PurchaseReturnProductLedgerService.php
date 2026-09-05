<?php

namespace App\Services;

use App\Models\PurchaseReturn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurchaseReturnProductLedgerService
{
    public const SOURCE_TYPE = 'purchase_return';
    public const OUT_REMARKS = 'PURRTN';
    public const JUNK_REMARKS = 'JUNK';

    /**
     * Rebuild every product-ledger row owned by a Purchase Return.
     *
     * Return quantity is split as:
     *   OUT  = stock physically leaving usable inventory
     *   JUNK = return quantity - OUT (recorded only; no stock effect)
     *
     * Examples:
     *   Return QTY 5 / OUT 3 => OUT 3 + JUNK 2
     *   Return QTY 4 / OUT 0 => JUNK 4 only; balance_stock does not change
     *   Return QTY 5 / OUT 5 => OUT 5 only
     *
     * Existing later balances are shifted only by the OUT difference. This
     * preserves historical imported/corrected balances instead of rebuilding
     * unrelated ledger history from quantity_in/quantity_out.
     *
     * @return int[] affected product ids
     */
    public function replaceForReturn(PurchaseReturn $purchaseReturn): array
    {
        $this->assertSchemaReady();

        return DB::connection('ledger')->transaction(function () use ($purchaseReturn) {
            $affectedProductIds = [];

            // First remove the old Purchase Return effect. Every old OUT is
            // added back to the balances after that exact row; JUNK has zero
            // stock effect and therefore requires no balance shift.
            $existingRows = $this->ownedRowsQuery($purchaseReturn)
                ->orderBy('date')
                ->orderBy('id')
                ->get(['id', 'product_id', 'date', 'quantity_out']);

            foreach ($existingRows as $row) {
                $productId = (int) ($row->product_id ?? 0);
                if ($productId <= 0) {
                    continue;
                }

                $affectedProductIds[$productId] = true;
                $oldOut = max(0, (int) ($row->quantity_out ?? 0));
                if ($oldOut > 0) {
                    $this->shiftBalancesAfter(
                        $productId,
                        (string) $row->date,
                        (int) $row->id,
                        $oldOut
                    );
                }
            }

            if ($existingRows->isNotEmpty()) {
                DB::connection('ledger')
                    ->table('product_ledgers')
                    ->whereIn('id', $existingRows->pluck('id')->all())
                    ->delete();
            }

            $purchaseReturn->loadMissing(['items', 'supplier', 'purchaseOrder']);
            $supplierName = trim((string) ($purchaseReturn->supplier?->name ?? '')) ?: 'N/A';
            $supplierInvoiceNumber = trim((string) ($purchaseReturn->purchaseOrder?->supplier_invoice_number ?? ''));
            $ledgerReferenceNumber = $supplierInvoiceNumber !== '' ? $supplierInvoiceNumber : null;
            $returnDate = (string) $purchaseReturn->date;
            $ledgerCreatedAt = $purchaseReturn->created_at ?? now();

            foreach ($purchaseReturn->items as $item) {
                $productId = (int) ($item->product_id ?? 0);
                if ($productId <= 0) {
                    continue;
                }

                $returnQty = max(0, (int) ($item->quantity ?? 0));
                $outQty = max(0, (int) ($item->out_quantity ?? 0));
                if ($outQty > $returnQty) {
                    throw new \InvalidArgumentException(
                        "OUT quantity for {$item->product_code} cannot be greater than Return QTY."
                    );
                }

                $junkQty = max(0, $returnQty - $outQty);
                $affectedProductIds[$productId] = true;

                if ($outQty > 0) {
                    $balanceBeforeOut = $this->balanceImmediatelyBeforeNewRow($productId, $returnDate);
                    $balanceAfterOut = $balanceBeforeOut - $outQty;

                    $outLedgerId = DB::connection('ledger')->table('product_ledgers')->insertGetId([
                        'product_id' => $productId,
                        'supplier_id' => (int) ($purchaseReturn->supplier_id ?? 0) ?: null,
                        'customer_id' => null,
                        'source_type' => self::SOURCE_TYPE,
                        'source_id' => (int) $purchaseReturn->id,
                        'source_item_id' => (int) $item->id,
                        'processed_actual_qty' => 0,
                        'transaction_type' => 'OUT',
                        'date' => $returnDate,
                        'transaction_number' => (string) $purchaseReturn->return_number,
                        'reference_number' => $ledgerReferenceNumber,
                        'entity_name' => $supplierName,
                        'quantity_in' => 0,
                        'quantity_out' => $outQty,
                        'junk' => 0,
                        'balance_stock' => $balanceAfterOut,
                        'oum' => (string) ($item->oum ?? ''),
                        'remarks' => self::OUT_REMARKS,
                        'idempotency_key' => $this->idempotencyKey($purchaseReturn, $item->id, 'out'),
                        'created_at' => $ledgerCreatedAt,
                        'updated_at' => now(),
                    ]);

                    // The new OUT happens after all pre-existing rows on the
                    // same date (new row has the newest id). Shift only rows
                    // that are chronologically after this exact row.
                    $this->shiftBalancesAfter($productId, $returnDate, (int) $outLedgerId, -$outQty);
                }

                if ($junkQty > 0) {
                    // JUNK is informational only. Its balance is copied from
                    // the ledger state immediately before the new JUNK row and
                    // NO later balances are changed.
                    $junkBalance = $this->balanceImmediatelyBeforeNewRow($productId, $returnDate);

                    DB::connection('ledger')->table('product_ledgers')->insert([
                        'product_id' => $productId,
                        'supplier_id' => (int) ($purchaseReturn->supplier_id ?? 0) ?: null,
                        'customer_id' => null,
                        'source_type' => self::SOURCE_TYPE,
                        'source_id' => (int) $purchaseReturn->id,
                        'source_item_id' => (int) $item->id,
                        'processed_actual_qty' => 0,
                        'transaction_type' => 'JUNK',
                        'date' => $returnDate,
                        'transaction_number' => (string) $purchaseReturn->return_number,
                        'reference_number' => $ledgerReferenceNumber,
                        'entity_name' => $supplierName,
                        'quantity_in' => 0,
                        'quantity_out' => 0,
                        'junk' => $junkQty,
                        'balance_stock' => $junkBalance,
                        'oum' => (string) ($item->oum ?? ''),
                        'remarks' => self::JUNK_REMARKS,
                        'idempotency_key' => $this->idempotencyKey($purchaseReturn, $item->id, 'junk'),
                        'created_at' => $ledgerCreatedAt,
                        'updated_at' => now(),
                    ]);
                }
            }

            return array_values(array_map('intval', array_keys($affectedProductIds)));
        });
    }

    /**
     * Delete Purchase Return product-ledger rows and reverse only their OUT
     * effect on all later balances. JUNK never changes stock.
     *
     * @return int[] affected product ids
     */
    public function deleteForReturn(PurchaseReturn $purchaseReturn): array
    {
        $this->assertSchemaReady();

        return DB::connection('ledger')->transaction(function () use ($purchaseReturn) {
            $rows = $this->ownedRowsQuery($purchaseReturn)
                ->orderBy('date')
                ->orderBy('id')
                ->get(['id', 'product_id', 'date', 'quantity_out']);

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
                $outQty = max(0, (int) ($row->quantity_out ?? 0));
                if ($outQty > 0) {
                    $this->shiftBalancesAfter(
                        $productId,
                        (string) $row->date,
                        (int) $row->id,
                        $outQty
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

    /**
     * Locate rows created by the new source linkage, while also cleaning up
     * older Purchase Return ledger rows generated by the previous archive
     * restore implementation.
     */
    private function ownedRowsQuery(PurchaseReturn $purchaseReturn)
    {
        return DB::connection('ledger')
            ->table('product_ledgers')
            ->where(function ($query) use ($purchaseReturn) {
                $query->where(function ($owned) use ($purchaseReturn) {
                    $owned->where('source_type', self::SOURCE_TYPE)
                        ->where('source_id', (int) $purchaseReturn->id);
                })->orWhere(function ($legacy) use ($purchaseReturn) {
                    $legacy->where('transaction_number', (string) $purchaseReturn->return_number)
                        ->whereIn('remarks', [
                            self::OUT_REMARKS,
                            self::JUNK_REMARKS,
                            'Purchase Return Processing',
                        ]);

                    if (!empty($purchaseReturn->supplier_id)) {
                        $legacy->where(function ($supplier) use ($purchaseReturn) {
                            $supplier->whereNull('supplier_id')
                                ->orWhere('supplier_id', (int) $purchaseReturn->supplier_id);
                        });
                    }
                });
            });
    }

    /**
     * Return the balance of the latest row that will be before a newly inserted
     * row on $date. New ledger rows get a larger id, so all existing rows on
     * that date are considered earlier.
     */
    private function balanceImmediatelyBeforeNewRow(int $productId, string $date): int
    {
        if ($productId <= 0 || $date === '') {
            return 0;
        }

        $row = DB::connection('ledger')
            ->table('product_ledgers')
            ->where('product_id', $productId)
            ->where('date', '<=', $date)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first(['balance_stock']);

        return (int) ($row->balance_stock ?? 0);
    }

    /**
     * Shift balance_stock only for rows after one exact ledger position.
     * This is intentionally delta-based so legacy/imported/corrected balance
     * history is preserved while Purchase Return OUT changes propagate to the
     * latest balance.
     */
    private function shiftBalancesAfter(int $productId, string $date, int $rowId, int $delta): void
    {
        if ($productId <= 0 || $date === '' || $rowId <= 0 || $delta === 0) {
            return;
        }

        DB::connection('ledger')
            ->table('product_ledgers')
            ->where('product_id', $productId)
            ->where(function ($query) use ($date, $rowId) {
                $query->where('date', '>', $date)
                    ->orWhere(function ($sameDate) use ($date, $rowId) {
                        $sameDate->where('date', $date)
                            ->where('id', '>', $rowId);
                    });
            })
            ->update([
                'balance_stock' => DB::raw('balance_stock + (' . (int) $delta . ')'),
                'updated_at' => now(),
            ]);
    }

    private function idempotencyKey(PurchaseReturn $purchaseReturn, $itemId, string $kind): string
    {
        return sprintf(
            'purchase_return:%d:item:%d:%s',
            (int) $purchaseReturn->id,
            (int) $itemId,
            $kind
        );
    }

    private function assertSchemaReady(): void
    {
        if (!Schema::connection('purchase')->hasColumn('purchase_return_items', 'out_quantity')) {
            throw new \RuntimeException('Purchase Return OUT column is missing. Run the latest database migration first.');
        }

        if (!Schema::connection('ledger')->hasColumn('product_ledgers', 'junk')) {
            throw new \RuntimeException('product_ledgers.junk is missing. Run the latest database migration first.');
        }
    }
}
