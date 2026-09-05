<?php

namespace App\Services;

use App\Models\ProductLedger;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SupplierLedger;
use App\Models\SupplierLedgerItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PurchaseOrderLedgerReconciliationService
{
    /**
     * Make the saved Purchase Order snapshot authoritative for inventory and
     * supplier-ledger movements. This method is intentionally idempotent: a
     * second save updates the same source-linked rows instead of adding stock
     * or ledger duplicates again.
     */
    public function reconcile(PurchaseOrder $purchaseOrder, array $legacyTransactionNumbers = []): array
    {
        $po = $purchaseOrder->fresh(['items', 'supplier']);
        if (!$po) {
            throw new \RuntimeException('Purchase Order could not be reloaded for ledger reconciliation.');
        }

        $items = $po->items()->orderBy('id')->get();
        $legacyTransactionNumbers = collect(array_merge(
            [(string) $po->po_number, (string) $po->supplier_invoice_number],
            $legacyTransactionNumbers
        ))
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();

        $candidateLedgers = $this->candidateProductLedgers($po, $items, $legacyTransactionNumbers);
        $unmatchedLedgerIds = $candidateLedgers->pluck('id')->map(fn ($id) => (int) $id)->flip();
        $affectedProductIds = $candidateLedgers->pluck('product_id')
            ->merge($items->pluck('product_id'))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $createdLedgers = 0;
        $updatedLedgers = 0;

        foreach ($items as $item) {
            $ledger = $this->matchProductLedger(
                $candidateLedgers,
                $unmatchedLedgerIds,
                $po,
                $item,
                $legacyTransactionNumbers
            );
            $quantity = (int) round((float) $item->actual_quantity);
            $unit = $this->normalizeUnit($item->unit);
            $supplierInvoiceNumber = trim((string) $po->supplier_invoice_number);
            $ledgerTransactionNumber = $supplierInvoiceNumber !== ''
                ? $supplierInvoiceNumber
                : (string) $po->po_number;
            $ledgerReferenceNumber = $supplierInvoiceNumber !== ''
                ? $supplierInvoiceNumber
                : $po->reference_number;
            $payload = [
                'product_id' => (int) $item->product_id,
                'supplier_id' => (int) $po->supplier_id,
                'customer_id' => null,
                'source_type' => 'purchase_order',
                'source_id' => (int) $po->id,
                'source_item_id' => (int) $item->id,
                'processed_actual_qty' => (float) $item->actual_quantity,
                'transaction_type' => 'IN',
                'date' => $po->date ?? now()->toDateString(),
                'transaction_number' => $ledgerTransactionNumber,
                'reference_number' => $ledgerReferenceNumber,
                'entity_name' => $po->supplier?->name ?? '',
                'quantity_in' => $quantity,
                'quantity_out' => 0,
                'oum' => $unit,
                'price' => 0,
                'cost' => (float) $item->unit_price,
                'remarks' => 'Purchase Order Processing / reconciled from saved PO item',
                'idempotency_key' => $this->idempotencyKey($po, $item),
            ];

            if ($ledger) {
                $ledger->fill($payload);
                $ledger->save();
                $unmatchedLedgerIds->forget((int) $ledger->id);
                $updatedLedgers++;
            } else {
                // balance_stock is recalculated for the full product history below.
                $payload['balance_stock'] = 0;
                ProductLedger::create($payload);
                $createdLedgers++;
            }
        }

        // Permanent physical deletion. This deliberately bypasses Eloquent
        // model deletion hooks and any future SoftDeletes configuration.
        $deletedLedgers = $this->hardDeleteProductLedgers($unmatchedLedgerIds->keys()->all());

        foreach ($affectedProductIds as $productId) {
            $this->recalculateProductBalance((int) $productId);
        }

        $supplierResult = $this->rebuildSupplierLedger($po, $items);
        $this->syncProductMaster($items, $affectedProductIds);

        return [
            'product_ledgers_created' => $createdLedgers,
            'product_ledgers_updated' => $updatedLedgers,
            'product_ledgers_deleted' => (int) $deletedLedgers,
            'supplier_ledger_id' => $supplierResult['supplier_ledger_id'],
            'supplier_ledger_items' => $supplierResult['item_count'],
            'supplier_ledger_items_created' => $supplierResult['items_created'],
            'supplier_ledger_items_updated' => $supplierResult['items_updated'],
            'supplier_ledger_items_deleted' => $supplierResult['items_deleted'],
            'affected_product_ids' => $affectedProductIds->all(),
        ];
    }

    private function candidateProductLedgers(
        PurchaseOrder $po,
        Collection $items,
        array $legacyTransactionNumbers = []
    ): Collection
    {
        $idempotencyKeys = $items
            ->map(fn (PurchaseOrderItem $item) => $this->idempotencyKey($po, $item))
            ->filter()
            ->values()
            ->all();

        return ProductLedger::query()
            ->where(function ($query) use ($po, $idempotencyKeys, $legacyTransactionNumbers) {
                $query->where(function ($sourceQuery) use ($po) {
                    $sourceQuery->whereIn('source_type', ['purchase_order', 'purchase-order'])
                        ->where('source_id', $po->id);
                });

                if (!empty($idempotencyKeys)) {
                    $query->orWhereIn('idempotency_key', $idempotencyKeys);
                }

                // Legacy rows created by the original PO processing route had no
                // source_id/source_item_id. Their transaction number was usually the
                // supplier invoice (not the PO number), so preserve both the current
                // and pre-edit invoice identifiers when adopting those rows.
                if (!empty($legacyTransactionNumbers)) {
                    $query->orWhere(function ($legacyQuery) use ($po, $legacyTransactionNumbers) {
                        $legacyQuery->where('transaction_type', 'IN')
                            ->where('supplier_id', $po->supplier_id)
                            ->where(function ($identityQuery) use ($legacyTransactionNumbers) {
                                $identityQuery->whereIn('transaction_number', $legacyTransactionNumbers)
                                    ->orWhereIn('reference_number', $legacyTransactionNumbers);
                            })
                            ->where('remarks', 'like', '%Purchase Order%');
                    });
                }
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    private function matchProductLedger(
        Collection $candidateLedgers,
        Collection $unmatchedLedgerIds,
        PurchaseOrder $po,
        PurchaseOrderItem $item,
        array $legacyTransactionNumbers = []
    ): ?ProductLedger {
        $available = $candidateLedgers->filter(
            fn ($ledger) => $unmatchedLedgerIds->has((int) $ledger->id)
        );

        $idempotencyKey = $this->idempotencyKey($po, $item);
        $ledger = $available->first(
            fn ($row) => (string) ($row->idempotency_key ?? '') === $idempotencyKey
        );

        if ($ledger) {
            return $ledger;
        }

        $ledger = $available->first(function ($row) use ($item) {
            return in_array(strtolower(trim((string) ($row->source_type ?? ''))), ['purchase_order', 'purchase-order'], true)
                && (int) ($row->source_item_id ?? 0) === (int) $item->id;
        });

        if ($ledger) {
            return $ledger;
        }

        // Product fallback is only for legacy, not-yet-linked rows. If a row
        // already points to a different PO item, that old movement represents a
        // deleted line and must be hard-deleted instead of being recycled for a
        // newly added line of the same product.
        $ledger = $available->first(function ($row) use ($po, $item) {
            return (int) ($row->source_id ?? 0) === (int) $po->id
                && (int) ($row->source_item_id ?? 0) === 0
                && (int) ($row->product_id ?? 0) === (int) $item->product_id;
        });

        if ($ledger) {
            return $ledger;
        }

        return $available->first(function ($row) use ($po, $item, $legacyTransactionNumbers) {
            $transactionNumber = trim((string) ($row->transaction_number ?? ''));
            $referenceNumber = trim((string) ($row->reference_number ?? ''));
            $matchesLegacyIdentity = in_array($transactionNumber, $legacyTransactionNumbers, true)
                || in_array($referenceNumber, $legacyTransactionNumbers, true);

            return (int) ($row->source_item_id ?? 0) === 0
                && $matchesLegacyIdentity
                && (int) ($row->supplier_id ?? 0) === (int) $po->supplier_id
                && (int) ($row->product_id ?? 0) === (int) $item->product_id;
        });
    }

    private function recalculateProductBalance(int $productId): void
    {
        if ($productId <= 0) {
            return;
        }

        $runningBalance = 0;
        ProductLedger::where('product_id', $productId)
            ->orderBy('date')
            ->orderBy('id')
            ->get(['id', 'quantity_in', 'quantity_out', 'balance_stock'])
            ->each(function ($row) use (&$runningBalance) {
                $runningBalance += (int) $row->quantity_in - (int) $row->quantity_out;
                if ((int) $row->balance_stock !== $runningBalance) {
                    ProductLedger::whereKey($row->id)->update(['balance_stock' => $runningBalance]);
                }
            });
    }

    private function rebuildSupplierLedger(PurchaseOrder $po, Collection $items): array
    {
        $supplierLedger = SupplierLedger::where('source_purchase_order_id', $po->id)
            ->lockForUpdate()
            ->first();

        if (!$supplierLedger) {
            $supplierLedger = SupplierLedger::where('transaction_code', $po->po_number)
                ->where('supplier_id', $po->supplier_id)
                ->where('module_type', 'Purchase Order')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();
        }

        $parentTotal = $this->money($po->actual_total_amount);
        $parentPayload = [
            'source_purchase_order_id' => (int) $po->id,
            'supplier_id' => (int) $po->supplier_id,
            'date' => $po->date ?? now()->toDateString(),
            'transaction_code' => (string) $po->po_number,
            'module_type' => 'Purchase Order',
            'title' => 'Purchase Order Processing - ' . ($po->status ?: 'Updated'),
            'credit_amount' => $parentTotal,
            'debit_amount' => 0,
            'reference_no' => $po->reference_number,
        ];

        if ($supplierLedger) {
            $supplierLedger->fill($parentPayload);
            $supplierLedger->save();
        } else {
            $supplierLedger = SupplierLedger::create($parentPayload);
        }

        // Include both rows already under this supplier ledger and rows globally
        // linked to the current Purchase Order item IDs. The source item column
        // is globally unique, so a stale row may still belong to an older parent
        // ledger. Reuse/move that row instead of attempting a duplicate insert.
        $sourceItemIds = $items->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $existingRows = SupplierLedgerItem::query()
            ->where(function ($query) use ($supplierLedger, $sourceItemIds) {
                $query->where('supplier_ledger_id', $supplierLedger->id);

                if (!empty($sourceItemIds)) {
                    $query->orWhereIn('source_purchase_order_item_id', $sourceItemIds);
                }
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->unique('id')
            ->values();

        $unmatchedIds = $existingRows->pluck('id')->map(fn ($id) => (int) $id)->flip();

        if ($items->isEmpty()) {
            $deleted = $this->hardDeleteSupplierLedgerItems($unmatchedIds->keys()->all());

            return [
                'supplier_ledger_id' => (int) $supplierLedger->id,
                'item_count' => 0,
                'items_created' => 0,
                'items_updated' => 0,
                'items_deleted' => (int) $deleted,
            ];
        }

        $baseTotal = $this->money($items->sum(fn ($item) => (float) $item->actual_subtotal));
        $remainingParent = $parentTotal;
        $remainingBase = $baseTotal;
        $itemValues = $items->values();
        $lastAllocatableIndex = $itemValues
            ->filter(fn ($item) => $this->money($item->actual_subtotal) > 0)
            ->keys()
            ->last();

        $created = 0;
        $updated = 0;
        $allocatedTotal = 0.0;

        foreach ($itemValues as $index => $item) {
            $actualQuantity = (float) $item->actual_quantity;
            $unitPrice = (float) $item->unit_price;
            $gross = $this->money($actualQuantity * $unitPrice);
            $lineBase = $this->money($item->actual_subtotal);

            if ($parentTotal <= 0 || $baseTotal <= 0 || $lineBase <= 0) {
                $allocatedSubtotal = 0.0;
            } elseif ($index === $lastAllocatableIndex || $remainingBase <= 0) {
                $allocatedSubtotal = $this->money($remainingParent);
            } else {
                $allocatedSubtotal = $this->money($parentTotal * ($lineBase / $baseTotal));
                $remainingParent = $this->money($remainingParent - $allocatedSubtotal);
                $remainingBase = $this->money($remainingBase - $lineBase);
            }

            $discountAmount = $this->money(max(0, $gross - $allocatedSubtotal));
            $discountPercent = $gross > 0
                ? $this->money(($discountAmount / $gross) * 100)
                : 0.0;

            $payload = [
                'source_purchase_order_item_id' => (int) $item->id,
                'supplier_ledger_id' => (int) $supplierLedger->id,
                'product_id' => (int) $item->product_id,
                'product_code' => $item->product_code,
                'unit' => $this->normalizeUnit($item->unit),
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'actual_quantity' => $actualQuantity,
                'unit_price' => $unitPrice,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
                'subtotal' => $allocatedSubtotal,
            ];

            $available = $existingRows->filter(
                fn ($row) => $unmatchedIds->has((int) $row->id)
            );
            $supplierItem = $available->first(
                fn ($row) => (int) ($row->source_purchase_order_item_id ?? 0) === (int) $item->id
            );

            if (!$supplierItem) {
                // Adopt only legacy rows that have never been linked to a PO
                // item. A row linked to a different (now deleted) PO item must
                // remain unmatched so it is physically deleted below.
                $supplierItem = $available->first(
                    fn ($row) => (int) ($row->source_purchase_order_item_id ?? 0) === 0
                        && (int) ($row->product_id ?? 0) === (int) $item->product_id
                );
            }

            if (!$supplierItem) {
                $normalizedCode = strtolower(trim((string) $item->product_code));
                $supplierItem = $available->first(
                    fn ($row) => (int) ($row->source_purchase_order_item_id ?? 0) === 0
                        && strtolower(trim((string) ($row->product_code ?? ''))) === $normalizedCode
                );
            }

            if ($supplierItem) {
                $supplierItem->fill($payload);
                $supplierItem->save();
                $unmatchedIds->forget((int) $supplierItem->id);
                $updated++;
            } else {
                SupplierLedgerItem::create($payload);
                $created++;
            }

            $allocatedTotal = $this->money($allocatedTotal + $allocatedSubtotal);
        }

        // Permanent physical deletion; removed PO items are not archived or
        // recoverable. Re-adding the product creates a new ledger-item row.
        $deleted = $this->hardDeleteSupplierLedgerItems($unmatchedIds->keys()->all());

        if (abs($allocatedTotal - $parentTotal) > 0.01) {
            throw new \RuntimeException('Supplier ledger item total does not match the Purchase Order actual total.');
        }

        return [
            'supplier_ledger_id' => (int) $supplierLedger->id,
            'item_count' => $items->count(),
            'items_created' => $created,
            'items_updated' => $updated,
            'items_deleted' => (int) $deleted,
        ];
    }

    /**
     * Physically delete Product Ledger rows with SQL DELETE.
     *
     * The user explicitly requires mistakes to be corrected by re-adding the
     * item, not by restoring an archived or soft-deleted ledger movement.
     */
    private function hardDeleteProductLedgers(array $ids): int
    {
        $ids = collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            return 0;
        }

        return DB::connection('ledger')
            ->table('product_ledgers')
            ->whereIn('id', $ids)
            ->delete();
    }

    /**
     * Physically delete Supplier Ledger Item rows with SQL DELETE.
     */
    private function hardDeleteSupplierLedgerItems(array $ids): int
    {
        $ids = collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            return 0;
        }

        return DB::connection('ledger')
            ->table('supplier_ledger_items')
            ->whereIn('id', $ids)
            ->delete();
    }

    private function syncProductMaster(Collection $items, Collection $affectedProductIds): void
    {
        $latestCosts = [];
        foreach ($items as $item) {
            $productId = (int) $item->product_id;
            if ($productId > 0) {
                $latestCosts[$productId] = (float) $item->unit_price;
            }
        }

        foreach ($affectedProductIds as $productId) {
            $productId = (int) $productId;
            if ($productId <= 0) {
                continue;
            }

            $latestBalance = ProductLedger::where('product_id', $productId)
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->value('balance_stock');

            $update = [
                'on_hand' => (int) ($latestBalance ?? 0),
                'updated_at' => now(),
            ];

            if (array_key_exists($productId, $latestCosts)) {
                $update['cost'] = $latestCosts[$productId];
            }

            DB::connection('masterlist')
                ->table('products')
                ->where('id', $productId)
                ->update($update);
        }

        ProductStockSyncService::clearProductMasterStatsCache();
    }

    private function normalizeUnit(?string $unit): ?string
    {
        $unit = trim((string) $unit);
        return $unit !== '' ? $unit : null;
    }

    private function idempotencyKey(PurchaseOrder $po, PurchaseOrderItem $item): string
    {
        return 'purchase_order:' . $po->id . ':item:' . $item->id;
    }

    private function money($value): float
    {
        return round((float) $value, 2);
    }
}
