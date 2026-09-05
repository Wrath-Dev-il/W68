<?php

namespace App\Services;

use App\Models\PurchaseNote;
use App\Models\PurchaseNoteItem;
use App\Models\PurchaseOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurchaseNoteStatusService
{
    private const QUANTITY_EPSILON = 0.00001;

    /**
     * Apply one receiving line against every matching active Purchase Note row.
     * This is intentionally collection-based because historical data may contain
     * duplicate rows for the same product. Updating only the first row leaves a
     * false remaining quantity and keeps the Purchase Note in Partial status.
     *
     * @return array{matched_item_ids: array<int>, received_quantity: float, remaining_quantity: float}
     */
    public function applyReceipt(PurchaseNote $purchaseNote, array $itemData): array
    {
        $receivedQuantity = (float) ($itemData['actual_quantity'] ?? 0);
        $displayedRemaining = (float) ($itemData['quantity'] ?? 0);
        $productCode = trim((string) ($itemData['product_code'] ?? ''));

        if ($receivedQuantity < -self::QUANTITY_EPSILON || $displayedRemaining < -self::QUANTITY_EPSILON) {
            throw new RuntimeException("Quantity cannot be negative for {$productCode}.");
        }

        if ($receivedQuantity - $displayedRemaining > self::QUANTITY_EPSILON) {
            throw new RuntimeException("Received quantity cannot exceed remaining quantity for {$productCode}.");
        }

        $matchingItems = $this->matchingRemainingItems($purchaseNote, $itemData);

        if ($matchingItems->isEmpty()) {
            throw new RuntimeException("Could not match {$productCode} to an active Purchase Note item.");
        }

        $availableQuantity = (float) $matchingItems->sum(fn (PurchaseNoteItem $item) => (float) $item->quantity);

        // A new Purchase Note may intentionally contain a zero-QTY placeholder.
        // The Proceed modal allows the operator to correct that quantity before
        // receiving it. Only bootstrap the quantity when this product has never
        // been processed for this Purchase Note; historical zero balances remain
        // protected from being reopened accidentally.
        if (
            $availableQuantity <= self::QUANTITY_EPSILON
            && $displayedRemaining > self::QUANTITY_EPSILON
        ) {
            if ($this->hasPurchaseOrderHistoryForItem($purchaseNote, $itemData)) {
                throw new RuntimeException(
                    "Cannot increase the completed Purchase Note quantity for {$productCode} from the Proceed modal."
                );
            }

            /** @var PurchaseNoteItem|null $placeholderItem */
            $placeholderItem = $matchingItems->first();
            if ($placeholderItem) {
                $bootstrappedQuantity = max(0, (int) round($displayedRemaining));
                $placeholderItem->update([
                    'quantity' => $bootstrappedQuantity,
                    'total_price' => round($bootstrappedQuantity * (float) $placeholderItem->unit_price, 2),
                ]);

                $availableQuantity = (float) $bootstrappedQuantity;
                $matchingItems = $this->matchingRemainingItems($purchaseNote, $itemData);
            }
        }

        if ($receivedQuantity - $availableQuantity > self::QUANTITY_EPSILON) {
            throw new RuntimeException(
                "Received quantity for {$productCode} exceeds the Purchase Note remaining quantity "
                . "({$receivedQuantity} received, {$availableQuantity} remaining)."
            );
        }

        $quantityToApply = $receivedQuantity;

        foreach ($matchingItems as $purchaseNoteItem) {
            $currentQuantity = (float) $purchaseNoteItem->quantity;
            $appliedQuantity = min($currentQuantity, max(0, $quantityToApply));
            $newQuantity = max(0, $currentQuantity - $appliedQuantity);

            $purchaseNoteItem->update([
                'quantity' => (int) round($newQuantity),
                'total_price' => round($newQuantity * (float) $purchaseNoteItem->unit_price, 2),
            ]);

            $quantityToApply -= $appliedQuantity;

            if ($quantityToApply <= self::QUANTITY_EPSILON) {
                break;
            }
        }

        return [
            'matched_item_ids' => $matchingItems->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'received_quantity' => $receivedQuantity,
            'remaining_quantity' => max(0, $availableQuantity - $receivedQuantity),
        ];
    }

    /**
     * Synchronize the Purchase Note header from its current active remaining rows.
     * purchase_note_items.quantity is the authoritative remaining quantity in this
     * application. Historical PO quantities must not be summed because each partial
     * receiving transaction stores the then-current remaining quantity and summing
     * those rows double-counts the original order.
     *
     * @return array{status: string, remaining_quantity: float, remaining_total: float, has_purchase_orders: bool}
     */
    public function syncFromRemainingItems(PurchaseNote|int $purchaseNote, bool $markPartialWhenRemaining = false): array
    {
        $note = $purchaseNote instanceof PurchaseNote
            ? $purchaseNote
            : PurchaseNote::query()->findOrFail($purchaseNote);

        $itemsQuery = PurchaseNoteItem::query()
            ->where('purchase_note_id', $note->id)
            ->withoutForceCancelled();

        $remainingQuantity = (float) (clone $itemsQuery)->sum('quantity');
        $remainingTotal = round((float) (clone $itemsQuery)->sum('total_price'), 2);

        $linkValues = collect([
            trim((string) $note->purchase_note_number),
            trim((string) $note->reference_number),
        ])->filter()->unique()->values();

        $hasPurchaseOrders = $linkValues->isNotEmpty()
            && PurchaseOrder::query()->whereIn('reference_number', $linkValues)->exists();

        if ($remainingQuantity <= self::QUANTITY_EPSILON) {
            // A zero-quantity Purchase Note is not automatically completed.
            // New notes are allowed to contain zero-quantity placeholder items,
            // and they must stay Open until there is actual Purchase Order history
            // proving that the note has been processed/received.
            $remainingQuantity = 0;
            $remainingTotal = 0;
            $status = $hasPurchaseOrders ? 'Closed' : 'Open';
        } elseif ($markPartialWhenRemaining || $hasPurchaseOrders || strcasecmp((string) $note->status, 'Partial') === 0) {
            $status = 'Partial';
        } else {
            $status = 'Open';
        }

        PurchaseNote::query()->whereKey($note->id)->update([
            'status' => $status,
            'total_amount' => $remainingTotal,
            'updated_at' => now(),
        ]);

        $note->setAttribute('status', $status);
        $note->setAttribute('total_amount', $remainingTotal);

        return [
            'status' => $status,
            'remaining_quantity' => $remainingQuantity,
            'remaining_total' => $remainingTotal,
            'has_purchase_orders' => $hasPurchaseOrders,
        ];
    }


    /**
     * Rebuild the current Purchase Note remainder from the latest Purchase Order
     * snapshot for each product. Each PO line stores the quantity that was still
     * open immediately before that receiving transaction, so the correct balance
     * is latest quantity minus latest actual quantity. Summing quantities from all
     * PO history rows would double-count the same original demand.
     *
     * Products that have never appeared in a Purchase Order are preserved as
     * unprocessed Purchase Note items.
     *
     * @return array{status: string, remaining_quantity: float, remaining_total: float, has_purchase_orders: bool}
     */
    public function syncFromLatestPurchaseOrderSnapshots(PurchaseNote|int $purchaseNote): array
    {
        $noteId = $purchaseNote instanceof PurchaseNote
            ? (int) $purchaseNote->id
            : (int) $purchaseNote;

        return DB::connection('purchase')->transaction(function () use ($noteId) {
            $note = PurchaseNote::query()->lockForUpdate()->findOrFail($noteId);

            $noteItems = PurchaseNoteItem::query()
                ->where('purchase_note_id', $note->id)
                ->withoutForceCancelled()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $linkValues = collect([
                trim((string) $note->purchase_note_number),
                trim((string) $note->reference_number),
            ])->filter()->unique()->values();

            if ($noteItems->isEmpty() || $linkValues->isEmpty()) {
                return $this->syncFromRemainingItems($note);
            }

            $purchaseOrders = PurchaseOrder::query()
                ->with('items')
                ->whereIn('reference_number', $linkValues)
                ->orderBy('id')
                ->get();

            if ($purchaseOrders->isEmpty()) {
                return $this->syncFromRemainingItems($note);
            }

            $latestByProductId = [];
            $latestByCode = [];

            foreach ($purchaseOrders as $purchaseOrder) {
                foreach ($purchaseOrder->items as $purchaseOrderItem) {
                    $productId = (int) ($purchaseOrderItem->product_id ?? 0);
                    if ($productId > 0) {
                        $latestByProductId[$productId] = $purchaseOrderItem;
                    }

                    $normalizedCode = $this->normalize($purchaseOrderItem->product_code ?? '');
                    if ($normalizedCode !== '') {
                        $latestByCode[$normalizedCode] = $purchaseOrderItem;
                    }
                }
            }

            $groups = $noteItems->groupBy(function (PurchaseNoteItem $item) {
                $productId = (int) ($item->product_id ?? 0);
                if ($productId > 0) {
                    return 'id:' . $productId;
                }

                return 'code:' . $this->normalize($item->product_code ?? '');
            });

            foreach ($groups as $group) {
                /** @var PurchaseNoteItem $first */
                $first = $group->first();
                $productId = (int) ($first->product_id ?? 0);
                $normalizedCode = $this->normalize($first->product_code ?? '');

                $snapshot = $productId > 0 ? ($latestByProductId[$productId] ?? null) : null;
                if (!$snapshot && $normalizedCode !== '') {
                    $snapshot = $latestByCode[$normalizedCode] ?? null;
                }

                // A newly added PN item has no PO snapshot yet. Keep its current
                // quantity so the note remains Open/Partial until it is received.
                if (!$snapshot) {
                    continue;
                }

                $remainingQuantity = max(
                    0,
                    (float) ($snapshot->quantity ?? 0) - (float) ($snapshot->actual_quantity ?? 0)
                );

                $assignedRemaining = false;
                foreach ($group as $purchaseNoteItem) {
                    $newQuantity = $assignedRemaining ? 0 : $remainingQuantity;
                    $assignedRemaining = true;

                    $purchaseNoteItem->update([
                        'quantity' => (int) round($newQuantity),
                        'total_price' => round($newQuantity * (float) $purchaseNoteItem->unit_price, 2),
                    ]);
                }
            }

            return $this->syncFromRemainingItems($note);
        });
    }

    /**
     * Repair an already-stuck Partial note created by the legacy "first matching
     * row only" receiver. The repair is deliberately conservative: it closes the
     * note only when every currently positive item has a latest PO snapshot whose
     * received quantity fully covers that snapshot, and the PN item was not edited
     * after that PO transaction.
     */
    public function repairCompletedPartialNote(PurchaseNote|int $purchaseNote): bool
    {
        $note = $purchaseNote instanceof PurchaseNote
            ? $purchaseNote
            : PurchaseNote::query()->findOrFail($purchaseNote);

        if (strcasecmp((string) $note->status, 'Partial') !== 0) {
            return false;
        }

        $positiveItems = PurchaseNoteItem::query()
            ->where('purchase_note_id', $note->id)
            ->withoutForceCancelled()
            ->where('quantity', '>', 0)
            ->orderBy('id')
            ->get();

        if ($positiveItems->isEmpty()) {
            $this->syncFromRemainingItems($note);
            return true;
        }

        $linkValues = collect([
            trim((string) $note->purchase_note_number),
            trim((string) $note->reference_number),
        ])->filter()->unique()->values();

        if ($linkValues->isEmpty()) {
            return false;
        }

        $purchaseOrders = PurchaseOrder::query()
            ->with('items')
            ->whereIn('reference_number', $linkValues)
            ->orderBy('id')
            ->get();

        if ($purchaseOrders->isEmpty()) {
            return false;
        }

        $latestByProductId = [];
        $latestByCode = [];

        foreach ($purchaseOrders as $purchaseOrder) {
            foreach ($purchaseOrder->items as $purchaseOrderItem) {
                $snapshot = [
                    'item' => $purchaseOrderItem,
                    'order' => $purchaseOrder,
                ];

                $productId = (int) ($purchaseOrderItem->product_id ?? 0);
                if ($productId > 0) {
                    $latestByProductId[$productId] = $snapshot;
                }

                $normalizedCode = $this->normalize($purchaseOrderItem->product_code ?? '');
                if ($normalizedCode !== '') {
                    $latestByCode[$normalizedCode] = $snapshot;
                }
            }
        }

        $groups = $positiveItems->groupBy(function (PurchaseNoteItem $item) {
            $productId = (int) ($item->product_id ?? 0);
            if ($productId > 0) {
                return 'id:' . $productId;
            }

            return 'code:' . $this->normalize($item->product_code ?? '');
        });

        foreach ($groups as $group) {
            /** @var PurchaseNoteItem $first */
            $first = $group->first();
            $productId = (int) ($first->product_id ?? 0);
            $normalizedCode = $this->normalize($first->product_code ?? '');

            $snapshot = $productId > 0 ? ($latestByProductId[$productId] ?? null) : null;
            if (!$snapshot && $normalizedCode !== '') {
                $snapshot = $latestByCode[$normalizedCode] ?? null;
            }

            if (!$snapshot) {
                return false;
            }

            $orderedQuantity = (float) ($snapshot['item']->quantity ?? 0);
            $receivedQuantity = (float) ($snapshot['item']->actual_quantity ?? 0);

            if ($receivedQuantity + self::QUANTITY_EPSILON < $orderedQuantity) {
                return false;
            }

            $purchaseOrderTimestamp = $snapshot['order']->updated_at
                ?? $snapshot['order']->created_at;

            if ($purchaseOrderTimestamp && $group->contains(function (PurchaseNoteItem $item) use ($purchaseOrderTimestamp) {
                return $item->updated_at && $item->updated_at->gt($purchaseOrderTimestamp);
            })) {
                return false;
            }
        }

        PurchaseNoteItem::query()
            ->whereIn('id', $positiveItems->pluck('id'))
            ->update([
                'quantity' => 0,
                'total_price' => 0,
                'updated_at' => now(),
            ]);

        $this->syncFromRemainingItems($note);

        return true;
    }

    /**
     * Close stale Partial rows whose active item quantities are already zero.
     * This repairs existing records without rewriting any positive quantity.
     */
    public function closeZeroRemainingNotes(): int
    {
        $noteIdsWithRemaining = PurchaseNoteItem::query()
            ->withoutForceCancelled()
            ->where('quantity', '>', 0)
            ->distinct()
            ->pluck('purchase_note_id');

        $candidates = PurchaseNote::query()
            ->whereIn('status', ['Open', 'Partial'])
            ->when(
                $noteIdsWithRemaining->isNotEmpty(),
                fn ($query) => $query->whereNotIn('id', $noteIdsWithRemaining)
            )
            ->get(['id', 'purchase_note_number', 'reference_number']);

        if ($candidates->isEmpty()) {
            return 0;
        }

        $noteIdsByLinkValue = [];
        foreach ($candidates as $note) {
            foreach ([
                trim((string) $note->purchase_note_number),
                trim((string) $note->reference_number),
            ] as $linkValue) {
                if ($linkValue !== '') {
                    $noteIdsByLinkValue[$linkValue][] = (int) $note->id;
                }
            }
        }

        if ($noteIdsByLinkValue === []) {
            return 0;
        }

        $processedReferences = PurchaseOrder::query()
            ->whereIn('reference_number', array_keys($noteIdsByLinkValue))
            ->pluck('reference_number')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique();

        $eligibleNoteIds = $processedReferences
            ->flatMap(fn ($reference) => $noteIdsByLinkValue[$reference] ?? [])
            ->unique()
            ->values();

        if ($eligibleNoteIds->isEmpty()) {
            // Zero quantity by itself must never close a Purchase Note. A newly
            // created zero-quantity note remains Open until Purchase Order
            // processing exists for that note.
            return 0;
        }

        return PurchaseNote::query()
            ->whereIn('id', $eligibleNoteIds)
            ->whereIn('status', ['Open', 'Partial'])
            ->update([
                'status' => 'Closed',
                'total_amount' => 0,
                'updated_at' => now(),
            ]);
    }

    /** @return Collection<int, PurchaseNoteItem> */
    private function matchingRemainingItems(PurchaseNote $purchaseNote, array $itemData): Collection
    {
        $baseQuery = PurchaseNoteItem::query()
            ->where('purchase_note_id', $purchaseNote->id)
            ->withoutForceCancelled()
            ->orderBy('id')
            ->lockForUpdate();

        $productId = (int) ($itemData['product_id'] ?? 0);
        if ($productId > 0) {
            $byProductId = (clone $baseQuery)->where('product_id', $productId)->get();
            if ($byProductId->isNotEmpty()) {
                return $byProductId;
            }
        }

        $normalizedCode = $this->normalize($itemData['product_code'] ?? '');
        $normalizedDescription = $this->normalize($itemData['description'] ?? '');

        return $baseQuery->get()->filter(function (PurchaseNoteItem $candidate) use ($normalizedCode, $normalizedDescription) {
            $candidateCode = $this->normalize($candidate->product_code ?? '');
            $candidateDescription = $this->normalize($candidate->description ?? '');

            return ($normalizedCode !== '' && $candidateCode === $normalizedCode)
                || ($normalizedDescription !== '' && $candidateDescription === $normalizedDescription);
        })->values();
    }

    private function hasPurchaseOrderHistoryForItem(PurchaseNote $purchaseNote, array $itemData): bool
    {
        $linkValues = collect([
            trim((string) $purchaseNote->purchase_note_number),
            trim((string) $purchaseNote->reference_number),
        ])->filter()->unique()->values();

        if ($linkValues->isEmpty()) {
            return false;
        }

        $productId = (int) ($itemData['product_id'] ?? 0);
        $normalizedCode = $this->normalize($itemData['product_code'] ?? '');

        return \App\Models\PurchaseOrderItem::query()
            ->whereHas('purchaseOrder', function ($query) use ($linkValues) {
                $query->whereIn('reference_number', $linkValues);
            })
            ->where(function ($query) use ($productId, $normalizedCode) {
                if ($productId > 0) {
                    $query->where('product_id', $productId);
                }

                if ($normalizedCode !== '') {
                    $method = $productId > 0 ? 'orWhereRaw' : 'whereRaw';
                    $query->{$method}('LOWER(TRIM(product_code)) = ?', [$normalizedCode]);
                }
            })
            ->exists();
    }

    private function normalize(mixed $value): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $value)) ?? '');
    }
}
