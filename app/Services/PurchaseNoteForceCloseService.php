<?php

namespace App\Services;

use App\Models\PurchaseNote;
use App\Models\PurchaseNoteItem;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseNoteForceCloseService
{
    private array $closableStatuses = ['partial'];

    /**
     * Backward-compatible preview endpoint. Force close no longer uses passcodes.
     */
    public function generatePasscode(int $purchaseNoteId, $user): array
    {
        $note = PurchaseNote::findOrFail($purchaseNoteId);
        $this->assertClosable($note->status);

        return [
            'passcode' => null,
            'expires_at' => null,
            'passcode_required' => false,
            'summary' => $this->buildSummary($purchaseNoteId),
        ];
    }

    public function buildSummary(int $purchaseNoteId): array
    {
        $note = PurchaseNote::findOrFail($purchaseNoteId);
        $items = PurchaseNoteItem::where('purchase_note_id', $purchaseNoteId)
            ->orderBy('id')
            ->get();

        return $this->buildSummaryFromItems($note, $items);
    }

    public function forceClose(int $purchaseNoteId, $user, Request $request): array
    {
        $userIdentifier = $this->userIdentifier($user);

        return DB::connection('purchase')->transaction(function () use ($purchaseNoteId, $user, $request, $userIdentifier) {
            $note = PurchaseNote::where('id', $purchaseNoteId)
                ->lockForUpdate()
                ->firstOrFail();

            $previousStatus = (string) $note->status;
            $this->assertClosable($previousStatus);

            $items = PurchaseNoteItem::where('purchase_note_id', $purchaseNoteId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $summary = $this->buildSummaryFromItems($note, $items);
            $reference = sprintf(
                'FC-PN-%s-%d-%s',
                now('Asia/Manila')->format('YmdHis'),
                $purchaseNoteId,
                strtoupper(Str::random(6))
            );

            $deletedItemCount = 0;
            $deletedQuantity = 0.0;

            foreach ($items as $item) {
                // purchase_note_items.quantity is the current unreceived remainder.
                // Example: ordered 10, already received/invoiced 6 => quantity is 4.
                // Force close deletes only that remaining 4. Existing purchase_orders
                // and purchase_order_items are never updated or deleted here.
                $remainingQuantity = max(0, (float) ($item->quantity ?? 0));
                if ($remainingQuantity <= 0) {
                    continue;
                }

                $deletedItemCount++;
                $deletedQuantity += $remainingQuantity;
                $item->delete();
            }

            $actor = function_exists('hatdogAuditActor')
                ? hatdogAuditActor($user)
                : ['name' => $userIdentifier, 'identifier' => $userIdentifier];

            // The current core4_purchase schema has no force-close passcode/log
            // tables and no force-close metadata columns. Update only real columns.
            $note->update([
                'status' => 'Closed',
                'total_amount' => 0,
                'total_amount_converted' => 0,
            ]);

            $summary['deleted_remaining_item_count'] = $deletedItemCount;
            $summary['deleted_remaining_quantity'] = (float) $deletedQuantity;
            $summary['cancelled_remaining_quantity'] = (float) $deletedQuantity;

            if (function_exists('hatdogWriteAuditTrail')) {
                hatdogWriteAuditTrail([
                    'module' => 'purchase_order',
                    'action' => 'Force Closed',
                    'source_connection' => 'purchase',
                    'source_table' => 'purchase_notes',
                    'source_id' => $note->id,
                    'display_id' => (string) $note->purchase_note_number,
                    'record_name' => 'Purchase Note ' . $note->purchase_note_number,
                    'user_name' => $actor['name'] ?? $userIdentifier,
                    'user_identifier' => $actor['identifier'] ?? $userIdentifier,
                    'audit_data' => [
                        'force_close_reference' => $reference,
                        'previous_status' => $previousStatus,
                        'new_status' => 'Closed',
                        'deleted_remaining_item_count' => $deletedItemCount,
                        'deleted_remaining_quantity' => (float) $deletedQuantity,
                        'items_before_force_close' => $summary['items'],
                        'ip_address' => $request->ip(),
                        'user_agent' => (string) $request->userAgent(),
                    ],
                ]);
            }

            return [
                'purchase_note_number' => $note->purchase_note_number,
                'force_close_reference' => $reference,
                'deleted_remaining_item_count' => $deletedItemCount,
                'deleted_remaining_quantity' => (float) $deletedQuantity,
                'summary' => $summary,
            ];
        });
    }

    private function buildSummaryFromItems(PurchaseNote $note, Collection $items): array
    {
        $rows = $items->map(function (PurchaseNoteItem $item) {
            $remainingQuantity = max(0, (float) ($item->quantity ?? 0));

            return [
                'id' => (int) $item->id,
                'product_id' => (int) ($item->product_id ?? 0),
                'product_code' => (string) ($item->product_code ?? ''),
                'description' => (string) ($item->description ?? ''),
                'unit' => (string) ($item->unit ?? ''),
                'remaining_quantity' => $remainingQuantity,
                'action' => $remainingQuantity > 0 ? 'delete' : 'keep',
                'snapshot' => $item->toArray(),
            ];
        })->values();

        $remainingRows = $rows->where('remaining_quantity', '>', 0)->values();
        $keptRows = $rows->where('remaining_quantity', '<=', 0)->values();
        $remainingQuantity = (float) $remainingRows->sum('remaining_quantity');

        return [
            'note' => [
                'id' => (int) $note->id,
                'purchase_note_number' => (string) $note->purchase_note_number,
                'status' => (string) $note->status,
            ],
            'processed_item_count' => 0,
            'remaining_item_count' => $remainingRows->count(),
            'processed_quantity' => 0.0,
            'remaining_quantity' => $remainingQuantity,
            'fully_processed_items' => $keptRows->all(),
            'partially_processed_items' => [],
            'unprocessed_items' => $remainingRows->all(),
            'cancelled_remaining_quantity' => $remainingQuantity,
            'items' => $rows->all(),
        ];
    }

    private function assertClosable($status): void
    {
        if (!in_array(strtolower((string) $status), $this->closableStatuses, true)) {
            throw new \RuntimeException('Only Partial Purchase Notes can be force closed.');
        }
    }

    private function userIdentifier($user): string
    {
        if (is_array($user)) {
            $user = (object) $user;
        }

        return (string) (
            $user->id
            ?? $user->user_id
            ?? $user->User_ID
            ?? $user->email
            ?? $user->username
            ?? $user->name
            ?? 'unknown'
        );
    }
}
