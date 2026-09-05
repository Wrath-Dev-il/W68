<?php

namespace App\Http\Controllers;

use App\Models\PurchaseNote;
use App\Models\PurchaseNoteItem;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\PartialPurchaseNoteConversion;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\ViberListItem;
use App\Services\PurchaseNoteStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PurchaseNoteController extends Controller
{
    /**
     * Preserve the real core4_masterlist.products primary key in Purchase Note
     * requests. The current Special User page can still have an older browser
     * script open that sends product_code but omits product_id. This method does
     * not generate a new ID; it retrieves the existing master-list primary key.
     */
    private function hydrateMissingItemProductIds(Request $request): void
    {
        $items = $request->input('items');

        if (!is_array($items)) {
            return;
        }

        $changed = false;

        foreach ($items as $index => &$item) {
            if (!is_array($item)) {
                continue;
            }

            $candidateId = $item['product_id'] ?? $item['productId'] ?? null;
            if (is_numeric($candidateId) && (int) $candidateId > 0) {
                $item['product_id'] = (int) $candidateId;
                continue;
            }

            $productCode = trim((string) ($item['product_code'] ?? $item['code'] ?? ''));
            if ($productCode === '') {
                continue;
            }

            $matches = Product::on('masterlist')
                ->whereRaw('TRIM(product_code) = ?', [$productCode])
                ->limit(2)
                ->get(['id', 'product_code']);

            if ($matches->count() === 1) {
                $item['product_id'] = (int) $matches->first()->id;
                $changed = true;

                Log::warning('Purchase Note request arrived without product_id; existing master-list ID restored.', [
                    'item_index' => $index,
                    'product_code' => $productCode,
                    'product_id' => $item['product_id'],
                    'request_path' => $request->path(),
                ]);
            } elseif ($matches->count() > 1) {
                Log::error('Purchase Note product ID could not be restored because product_code is not unique.', [
                    'item_index' => $index,
                    'product_code' => $productCode,
                    'matching_ids' => $matches->pluck('id')->all(),
                    'request_path' => $request->path(),
                ]);
            }
        }
        unset($item);

        if ($changed) {
            $request->merge(['items' => $items]);
        }
    }
    /**
     * Keep the Viber/Purchase Entry source quantity aligned when the user edits
     * the Purchase Note quantity.  Viber rows stay linked to the Purchase Note,
     * so their original order_qty can otherwise become stale (for example Viber
     * 20, Purchase Note edited to 50).  Do not create/delete rows here; only
     * distribute the current Purchase Note quantity across the already-linked
     * source rows.
     */
    private function syncLinkedViberQuantitiesFromPurchaseNote(PurchaseNote $purchaseNote): void
    {
        $noteItems = PurchaseNoteItem::where('purchase_note_id', $purchaseNote->id)
            ->withoutForceCancelled()
            ->orderBy('id')
            ->get(['product_id', 'product_code', 'quantity']);

        if ($noteItems->isEmpty()) {
            return;
        }

        $groups = $noteItems->groupBy(function ($item) {
            $productId = (int) ($item->product_id ?? 0);
            if ($productId > 0) {
                return 'id:' . $productId;
            }

            return 'code:' . strtoupper(trim((string) ($item->product_code ?? '')));
        });

        foreach ($groups as $groupKey => $group) {
            $first = $group->first();
            $targetQty = max(0, (float) $group->sum('quantity'));

            $viberQuery = ViberListItem::where('purchase_note_id', $purchaseNote->id);
            $productId = (int) ($first->product_id ?? 0);
            if ($productId > 0) {
                $viberQuery->where('product_id', $productId);
            } else {
                $productCode = trim((string) ($first->product_code ?? ''));
                if ($productCode === '') {
                    continue;
                }
                $viberQuery->whereRaw('TRIM(item_code) = ?', [$productCode]);
            }

            $viberRows = $viberQuery->orderBy('id')->lockForUpdate()->get();
            if ($viberRows->isEmpty()) {
                continue;
            }

            $remaining = $targetQty;
            $lastIndex = $viberRows->count() - 1;

            foreach ($viberRows as $index => $viberRow) {
                if ($index === $lastIndex) {
                    $assigned = $remaining;
                } else {
                    $current = max(0, (float) ($viberRow->order_qty ?? 0));
                    $assigned = min($current, $remaining);
                }

                $viberRow->order_qty = max(0, $assigned);
                $viberRow->save();
                $remaining = max(0, $remaining - $assigned);
            }
        }
    }

    private function resolveAuditActor(): array
    {
        $user = session('user');

        if (function_exists('hatdogAuditActor') && $user) {
            return hatdogAuditActor($user);
        }

        if (is_array($user)) {
            $user = (object) $user;
        }

        return [
            'name' => trim((string) ($user->name ?? $user->username ?? $user->User_ID ?? 'Admin')) ?: 'Admin',
            'identifier' => trim((string) ($user->User_ID ?? $user->username ?? $user->id ?? '')) ?: null,
        ];
    }

    private function buildPurchaseNoteAuditSnapshot(PurchaseNote $purchaseNote): array
    {
        $purchaseNote->loadMissing(['items', 'supplier']);

        return [
            'purchase_note' => $purchaseNote->toArray(),
            'supplier' => $purchaseNote->supplier?->toArray(),
            'items' => $purchaseNote->items->map(fn ($item) => $item->toArray())->values()->all(),
        ];
    }

    private function writePurchaseNoteAudit(string $action, PurchaseNote $purchaseNote, array $auditData): void
    {
        if (!function_exists('hatdogWriteAuditTrail')) {
            return;
        }

        $purchaseNote->loadMissing('supplier');
        $actor = $this->resolveAuditActor();
        $supplierName = trim((string) optional($purchaseNote->supplier)->name);
        $displayId = (string) ($purchaseNote->purchase_note_number ?? $purchaseNote->id);
        $dataName = $supplierName !== '' ? $supplierName : ('Purchase Note ' . $displayId);

        hatdogWriteAuditTrail([
            'module' => 'purchase_note',
            'action' => $action,
            'source_connection' => 'purchase',
            'source_table' => 'purchase_notes',
            'source_id' => $purchaseNote->id,
            'display_id' => $displayId,
            'record_name' => $dataName,
            'user_name' => $actor['name'],
            'user_identifier' => $actor['identifier'],
            'audit_data' => $auditData,
        ]);
    }

    public function index(Request $request)
    {
        // Handle password verification (AJAX fetch to same page)
        if ($request->has('verify_password')) {
            $password = $request->input('password');
            $user = session('user');
            if (!$user) return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            $login = \App\Models\Login::find(($user->login_ID ?? $user->User_ID ?? 0));
            if (!$login) return response()->json(['success' => false, 'message' => 'User not found'], 404);
            $login->makeVisible('Password');
            $passwordMatches = $password === $login->Password || \Illuminate\Support\Facades\Hash::check($password, $login->Password);
            if ($passwordMatches) return response()->json(['success' => true]);
            return response()->json(['success' => false, 'message' => 'Incorrect password. Please try again.']);
        }
        $user = session('user');
        if (is_array($user)) { $user = (object) $user; }
        $accountType = (int) ($user->account_type ?? 0);
        $view = match($accountType) {
            2 => 'Regular_User.Purchase.Purchase-Note',
            3 => 'Special_User.Purchase.Purchase-Note',
            default => 'Admin.Purchase.Purchase-Note',
        };
        $viberData = session('viber_prepare_note');
        return view($view, compact('viberData'));
    }

    public function getNextPurchaseNoteNumber()
    {
        return response()->json([
            'success' => true,
            'next_number' => PurchaseNote::nextNumber(),
        ]);
    }

    public function searchSuppliers(Request $request)
    {
        try {
            $page = max(1, (int) $request->get('page', 1));
            $perPage = max(1, min(100, (int) $request->get('perPage', 50)));
            $globalSearch = trim((string) $request->get('q', ''));
            $codeSearch = trim((string) $request->get('code', ''));
            $nameSearch = trim((string) $request->get('name', ''));
            $contactSearch = trim((string) $request->get('contact', ''));
            $contactPersonSearch = trim((string) $request->get('contactPerson', ''));
            $addressSearch = trim((string) $request->get('address', ''));

            $query = Supplier::query()->select([
                'id',
                'name',
                'supplier_code',
                'contact_number',
                'contact_person',
                'address',
            ]);

            if ($globalSearch !== '') {
                $query->where(function ($q) use ($globalSearch) {
                    $q->where('name', 'LIKE', "%{$globalSearch}%")
                        ->orWhere('supplier_code', 'LIKE', "%{$globalSearch}%")
                        ->orWhere('contact_number', 'LIKE', "%{$globalSearch}%")
                        ->orWhere('contact_person', 'LIKE', "%{$globalSearch}%")
                        ->orWhere('address', 'LIKE', "%{$globalSearch}%");
                });
            }

            if ($codeSearch !== '') {
                $query->where('supplier_code', 'LIKE', "%{$codeSearch}%");
            }
            if ($nameSearch !== '') {
                $query->where('name', 'LIKE', "%{$nameSearch}%");
            }
            if ($contactSearch !== '') {
                $query->where('contact_number', 'LIKE', "%{$contactSearch}%");
            }
            if ($contactPersonSearch !== '') {
                $query->where('contact_person', 'LIKE', "%{$contactPersonSearch}%");
            }
            if ($addressSearch !== '') {
                $query->where('address', 'LIKE', "%{$addressSearch}%");
            }

            $suppliers = $query
                ->orderBy('supplier_code', 'asc')
                ->paginate($perPage, ['*'], 'page', $page);

            $data = collect($suppliers->items())->map(function ($supplier) {
                return [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'supplier_code' => $supplier->supplier_code,
                    'contact_number' => $supplier->contact_number,
                    'contact_person' => $supplier->contact_person,
                    'address' => $supplier->address,
                ];
            })->values()->all();

            return response()->json([
                'success' => true,
                'data' => $data,
                'current_page' => $suppliers->currentPage(),
                'last_page' => $suppliers->lastPage(),
                'total' => $suppliers->total(),
                'per_page' => $suppliers->perPage(),
                'from' => $suppliers->firstItem() ?? 0,
                'to' => $suppliers->lastItem() ?? 0,
            ]);
        } catch (\Throwable $e) {
            Log::error('Purchase Note searchSuppliers Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load suppliers. Please try again.',
            ], 500);
        }
    }

    public function searchProducts(Request $request)
    {
        try {
            $query = Product::query()->select([
                'id',
                'product_code',
                'part_number',
                'category',
                'description',
                'Position',
                'unit',
                'cost',
                'selling_price',
                'on_hand',
                'status',
            ]);

            if ($request->filled('q')) {
                $search = $request->get('q');
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'LIKE', "%{$search}%")
                        ->orWhere('product_code', 'LIKE', "%{$search}%")
                        ->orWhere('part_number', 'LIKE', "%{$search}%");
                });
            }

            if ($request->filled('code')) {
                $query->where('product_code', 'LIKE', '%' . $request->get('code') . '%');
            }
            if ($request->filled('partNo')) {
                $query->where('part_number', 'LIKE', '%' . $request->get('partNo') . '%');
            }
            if ($request->filled('desc')) {
                $query->where('description', 'LIKE', '%' . $request->get('desc') . '%');
            }
            if ($request->filled('cat')) {
                $query->where('category', 'LIKE', '%' . $request->get('cat') . '%');
            }
            if ($request->filled('unit')) {
                $query->where('Position', 'LIKE', '%' . $request->get('unit') . '%');
            }

            $products = $query->orderBy('product_code', 'asc')->paginate(50);

            $data = collect($products->items())->map(function ($product) {
                return [
                    // Keep both names because the Purchase Note UI has legacy paths
                    // that use productId/product_id while the search table used id.
                    // Both values are the same core4_masterlist.products primary key.
                    'id' => (int) $product->id,
                    'product_id' => (int) $product->id,
                    'product_code' => $product->product_code,
                    'part_number' => $product->part_number,
                    'description' => $product->description,
                    'category' => $product->category,
                    'unit' => $product->unit ?? '',
                    'cost' => (float) ($product->cost ?? 0),
                    'selling_price' => (float) ($product->selling_price ?? 0),
                    'on_hand' => (int) ($product->on_hand ?? 0),
                    'status' => $product->status,
                    'last_purchase_date' => null,
                ];
            })->values()->all();

            return response()->json([
                'success' => true,
                'data' => $data,
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
                'per_page' => $products->perPage(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Purchase Note searchProducts Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load products. Please try again.',
            ], 500);
        }
    }

    public function fetchPurchaseNotes(Request $request)
    {
        try {
            app(PurchaseNoteStatusService::class)->closeZeroRemainingNotes();
        } catch (\Throwable $statusRepairError) {
            Log::warning('Purchase Note status repair skipped: ' . $statusRepairError->getMessage());
        }

        $perPage = $request->get('perPage', 50);
        $page = $request->get('page', 1);
        $tab = $request->get('tab', 'active');
        $search = $request->get('search', []);
        $fromDate = trim((string) $request->get('from_date', ''));
        $toDate = trim((string) $request->get('to_date', ''));
        $minAmount = trim((string) $request->get('min_amount', ''));
        $maxAmount = trim((string) $request->get('max_amount', ''));

        $query = PurchaseNote::with(['supplier']);

        // Tab filtering
        if ($tab === 'active') {
            $query->whereIn('status', ['Open', 'Partial', 'Surplus']);
        } elseif ($tab === 'closed') {
            $query->where('status', 'Closed');
        }

        // Column search
        if (!empty($search)) {
            if (!empty($search['poNumber'])) {
                $query->where('purchase_note_number', 'LIKE', "%{$search['poNumber']}%");
            }
            if (!empty($search['supplierName'])) {
                $supplierName = trim((string) $search['supplierName']);
                $supplierIds = Supplier::query()
                    ->where('name', 'LIKE', "%{$supplierName}%")
                    ->pluck('id');

                $query->whereIn('supplier_id', $supplierIds->isNotEmpty() ? $supplierIds : [-1]);
            }
            if (!empty($search['date'])) {
                $query->where('date', 'LIKE', "%{$search['date']}%");
            }
            if (!empty($search['totalAmount'])) {
                $query->where('total_amount', 'LIKE', "%{$search['totalAmount']}%");
            }
            if (!empty($search['status'])) {
                $query->where('status', 'LIKE', "%{$search['status']}%");
            }
            if (!empty($search['reference'])) {
                $query->where('reference_number', 'LIKE', "%{$search['reference']}%");
            }
        }

        if ($fromDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
            $query->whereDate('date', '>=', $fromDate);
        }

        if ($toDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
            $query->whereDate('date', '<=', $toDate);
        }

        if ($minAmount !== '' && is_numeric($minAmount)) {
            $query->where('total_amount', '>=', (float) $minAmount);
        }

        if ($maxAmount !== '' && is_numeric($maxAmount)) {
            $query->where('total_amount', '<=', (float) $maxAmount);
        }

        $query->orderBy('created_at', 'desc');

        // Repair only the Partial notes that are about to appear on this page.
        // This keeps the request bounded while correcting legacy rows where every
        // latest PO snapshot is fully received but a duplicate PN row stayed positive.
        try {
            $repairCandidateIds = (clone $query)
                ->where('status', 'Partial')
                ->forPage($page, $perPage)
                ->pluck('id');

            $statusService = app(PurchaseNoteStatusService::class);
            foreach ($repairCandidateIds as $repairCandidateId) {
                $statusService->repairCompletedPartialNote((int) $repairCandidateId);
            }
        } catch (\Throwable $statusRepairError) {
            Log::warning('Purchase Note completed-status repair skipped: ' . $statusRepairError->getMessage());
        }

        // Run the page query after repair so newly Closed notes immediately move
        // out of the Active tab and into the Closed tab.
        $notes = $query->paginate($perPage, ['*'], 'page', $page);

        // Stats calculation (always full count, not filtered)
        $stats = [
            'total_transactions' => PurchaseNote::count(),
            'open_notes' => PurchaseNote::whereIn('status', ['Open', 'Partial', 'Surplus'])->count(),
            'total_amount' => PurchaseNote::sum('total_amount'),
        ];

        return response()->json([
            'success' => true,
            'notes' => collect($notes->items())->map(function ($note) {
                // If it is closed, we might want to get the actual amount processed from PO history
                $displayAmount = $note->total_amount;
                if (strtoupper($note->status) === 'CLOSED') {
                    // Fetch total processed amount from core4_purchase.purchase_orders
                    $processedAmount = \App\Models\PurchaseOrder::where('reference_number', $note->purchase_note_number)->sum('actual_total_amount');
                    if ($processedAmount > 0) {
                        $displayAmount = $processedAmount;
                    }
                }

                return [
                    'id' => $note->id,
                    'pn_number' => $note->purchase_note_number,
                    'supplier_name' => $note->supplier ? $note->supplier->name : 'N/A',
                    'date' => $note->date,
                    'total_amount' => $displayAmount,
                    'status' => $note->status,
                    'reference' => $note->reference_number,
                    'currency' => $note->currency ?? 'PHP',
                ];
            }),
            'pagination' => [
                'current_page' => $notes->currentPage(),
                'last_page' => $notes->lastPage(),
                'per_page' => $notes->perPage(),
                'total' => $notes->total(),
                'from' => $notes->firstItem(),
                'to' => $notes->lastItem(),
            ],
            'stats' => $stats
        ]);
    }

    public function getNoteDetails(Request $request, $id)
    {
        try {
            $note = PurchaseNote::with(['supplier'])->findOrFail($id);

            if (strcasecmp((string) $note->status, 'Partial') === 0) {
                app(PurchaseNoteStatusService::class)->repairCompletedPartialNote($note);
                $note->refresh()->load('supplier');
            }

            $forEdit = $request->boolean('for_edit');

            // A Purchase Note line with QTY = 0 is still a real line and must remain
            // visible in P.O ITEMS DETAIL. The only zero-quantity rows we hide are
            // rows that were explicitly transferred out to another Purchase Note.
            $transferredOutItemIds = [];
            try {
                $transferredOutItemIds = PartialPurchaseNoteConversion::on('ledger')
                    ->where('source_purchase_note_id', $note->id)
                    ->where('status', 'active')
                    ->whereNotNull('source_purchase_note_item_id')
                    ->pluck('source_purchase_note_item_id')
                    ->map(fn ($itemId) => (int) $itemId)
                    ->all();
            } catch (\Throwable $e) {
                // Keep the detail modal usable on installations where the transfer
                // tracking table is not available yet.
                $transferredOutItemIds = [];
            }

            $visibleNoteItemsQuery = function () use ($note, $transferredOutItemIds) {
                $query = \App\Models\PurchaseNoteItem::where('purchase_note_id', $note->id)
                    ->withoutForceCancelled()
                    // Purchase Note item arrangement is the original insert order.
                    // Always keep it stable whenever the note is opened again for Edit.
                    ->orderBy('id', 'asc');

                if (!empty($transferredOutItemIds)) {
                    $query->whereNotIn('id', $transferredOutItemIds);
                }

                return $query;
            };

            // Fetch processed items if any (from purchase_order_items via reference_number)
            $processedItems = \App\Models\PurchaseOrderItem::with(['purchaseOrder', 'product'])->whereHas('purchaseOrder', function($q) use ($note) {
                $q->where('reference_number', $note->purchase_note_number);
            })
                ->orderBy('id', 'asc')
                ->get();

            // For non-edit views, keep processed PO rows only for Purchase Note
            // products that still belong to this note. QTY = 0 remains valid; transfer
            // tracking (not quantity) decides whether a line was moved away.
            if (!$forEdit && $processedItems->isNotEmpty() && strtoupper($note->status) !== 'CLOSED') {
                $visibleProductIds = $visibleNoteItemsQuery()
                    ->pluck('product_id')
                    ->filter()
                    ->map(fn ($productId) => (int) $productId)
                    ->unique()
                    ->all();

                if (!empty($visibleProductIds)) {
                    $processedItems = $processedItems->filter(function($pi) use ($visibleProductIds) {
                        return in_array((int) $pi->product_id, $visibleProductIds, true);
                    })->values();
                } else {
                    $processedItems = collect();
                }
            }

            $totalAmount = $note->total_amount;

            // When for_edit=1, merge remaining PN items with processed data from PO items
            if ($forEdit) {
                $remainingItems = $visibleNoteItemsQuery()->get();

                if ($processedItems->count() > 0) {
                    $processedByProductId = $processedItems->keyBy('product_id');
                    $processedByProductCode = $processedItems->keyBy(function($pi) {
                        return $pi->product_code;
                    });

                    $items = $remainingItems->map(function($pnItem) use ($processedByProductId, $processedByProductCode, $note) {
                        $processedItem = $processedByProductId->get($pnItem->product_id)
                            ?? $processedByProductCode->get($pnItem->product_code);
                        $isProcessed = $processedItem !== null;

                        return [
                            'product_id' => $pnItem->product_id,
                            'product_code' => $pnItem->product_code,
                            'part_number' => $pnItem->part_number ?? '---',
                            'description' => $pnItem->description,
                            'quantity' => $pnItem->quantity,
                            'original_quantity' => $isProcessed ? (float) $processedItem->quantity : (float) $pnItem->quantity,
                            'actual_quantity' => $isProcessed ? $processedItem->actual_quantity : null,
                            'unit_price' => $pnItem->unit_price,
                            'total_price' => $pnItem->total_price,
                            'actual_total_price' => $isProcessed ? $processedItem->actual_subtotal : null,
                            'unit' => $pnItem->unit,
                            'processed' => $isProcessed,
                            'purchase_order_id' => $isProcessed ? $processedItem->purchase_order_id : null,
                            'po_number' => $isProcessed ? $processedItem->purchaseOrder?->po_number : $note->purchase_note_number,
                        ];
                    });

                    // Deduplicate by product_id: sum quantities, keep last unit_price
                    $items = $items->groupBy('product_id')->map(function ($group) {
                        if ($group->count() === 1) return $group->first();
                        $first = $group->first();
                        $last = $group->last();
                        $sumQty = $group->sum('quantity');
                        $first['quantity'] = $sumQty;
                        $first['unit_price'] = $last['unit_price'];
                        $first['total_price'] = $sumQty * $last['unit_price'];
                        $first['actual_quantity'] = $group->first(function ($i) { return $i['actual_quantity'] !== null; })['actual_quantity'] ?? null;
                        return $first;
                    })->values();

                    $totalAmount = $items->sum('total_price');
                } else {
                    $items = $remainingItems->map(function($item) use ($note) {
                        return [
                            'product_id' => $item->product_id,
                            'product_code' => $item->product_code,
                            'part_number' => $item->part_number ?? '---',
                            'description' => $item->description,
                            'quantity' => $item->quantity,
                            'unit_price' => $item->unit_price,
                            'total_price' => $item->total_price,
                            'unit' => $item->unit,
                            'processed' => false,
                            'purchase_order_id' => null,
                            'po_number' => $note->purchase_note_number,
                        ];
                    });

                    // Deduplicate by product_id: sum quantities, keep last unit_price
                    $items = $items->groupBy('product_id')->map(function ($group) {
                        if ($group->count() === 1) return $group->first();
                        $first = $group->first();
                        $last = $group->last();
                        $sumQty = $group->sum('quantity');
                        $first['quantity'] = $sumQty;
                        $first['unit_price'] = $last['unit_price'];
                        $first['total_price'] = $sumQty * $last['unit_price'];
                        return $first;
                    })->values();

                    $totalAmount = $items->sum('total_price');
                }
            }
            // If the note is PARTIAL and NOT for edit, show processed items
            elseif (strtoupper($note->status) === 'PARTIAL' && !$forEdit) {
                if ($processedItems->count() > 0) {
                    $items = $processedItems->map(function($pi) {
                        return [
                            'product_id' => $pi->product_id,
                            'product_code' => $pi->product_code,
                            'part_number' => $pi->product?->part_number ?? '',
                            'description' => trim((string) ($pi->description ?? '')) !== '' ? $pi->description : ($pi->product?->description ?? ''),
                            'quantity' => $pi->actual_quantity, 
                            'unit_price' => $pi->unit_price,
                            'total_price' => $pi->actual_subtotal,
                            'unit' => $pi->unit ?? null,
                            'processed' => true,
                            'purchase_order_id' => $pi->purchase_order_id,
                            'po_number' => $pi->purchaseOrder?->po_number,
                        ];
                    });
                    $totalAmount = $processedItems->sum('actual_subtotal');
                } else {
                    // Fallback to purchase_note_items if no processed items
                    // Exclude transferred items (quantity reduced to 0)
                    $items = $visibleNoteItemsQuery()->get()
                        ->map(function($item) use ($note) {
                            return [
                                'product_id' => $item->product_id,
                                'product_code' => $item->product_code,
                                'part_number' => $item->part_number ?? '---',
                                'description' => $item->description,
                                'quantity' => $item->quantity,
                                'unit_price' => $item->unit_price,
                                'total_price' => $item->total_price,
                                'unit' => $item->unit,
                                'processed' => false,
                                'purchase_order_id' => null,
                                'po_number' => $note->purchase_note_number,
                            ];
                        });
                    $totalAmount = $items->sum('total_price');
                }
            }
            // If the note is CLOSED and NOT for edit, show processed items for view
            elseif (!$forEdit && strtoupper($note->status) === 'CLOSED') {
                if ($processedItems->count() > 0) {
                    $items = $processedItems->map(function($pi) {
                        return [
                            'product_id' => $pi->product_id,
                            'product_code' => $pi->product_code,
                            'part_number' => $pi->product?->part_number ?? '',
                            'description' => trim((string) ($pi->description ?? '')) !== '' ? $pi->description : ($pi->product?->description ?? ''),
                            'quantity' => $pi->actual_quantity, 
                            'unit_price' => $pi->unit_price,
                            'total_price' => $pi->actual_subtotal,
                            'unit' => $pi->unit ?? null,
                            'processed' => true,
                            'purchase_order_id' => $pi->purchase_order_id,
                            'po_number' => $pi->purchaseOrder?->po_number,
                        ];
                    });
                    $totalAmount = $processedItems->sum('actual_subtotal');
                } else {
                    // Fallback to purchase_note_items if no processed items
                    // Exclude transferred items (quantity reduced to 0)
                    $items = $visibleNoteItemsQuery()->get()
                        ->map(function($item) use ($note) {
                            return [
                                'product_id' => $item->product_id,
                                'product_code' => $item->product_code,
                                'part_number' => $item->part_number ?? '---',
                                'description' => $item->description,
                                'quantity' => $item->quantity,
                                'unit_price' => $item->unit_price,
                                'total_price' => $item->total_price,
                                'unit' => $item->unit,
                                'processed' => false,
                                'purchase_order_id' => null,
                                'po_number' => $note->purchase_note_number,
                            ];
                        });
                    $totalAmount = $items->sum('total_price');
                }
            }
            // Default: Use items from the purchase_note_items table
            else {
                // Exclude transferred items (quantity reduced to 0)
                $items = $visibleNoteItemsQuery()->get()
                    ->map(function($item) use ($note) {
                        return [
                            'product_id' => $item->product_id,
                            'product_code' => $item->product_code,
                            'part_number' => $item->part_number ?? '---',
                            'description' => $item->description,
                            'quantity' => $item->quantity,
                            'unit_price' => $item->unit_price,
                            'total_price' => $item->total_price,
                            'unit' => $item->unit,
                            'processed' => false,
                            'purchase_order_id' => null,
                            'po_number' => $note->purchase_note_number,
                        ];
                    });
                $totalAmount = $items->isNotEmpty() ? $items->sum('total_price') : $note->total_amount;
            }


            // Partial/Closed notes normally display processed PO rows. If a zero-QTY
            // product was added to the Purchase Note after processing, it has no PO row
            // yet and would otherwise disappear. Append those legitimate zero-QTY PN
            // lines so P.O ITEMS DETAIL always reflects the Purchase Note itself.
            $detailItems = collect($items ?? [])->values();
            $representedProductIds = $detailItems
                ->pluck('product_id')
                ->filter(fn ($productId) => $productId !== null && $productId !== '')
                ->map(fn ($productId) => (int) $productId)
                ->unique()
                ->all();
            $representedProductCodes = $detailItems
                ->pluck('product_code')
                ->filter(fn ($code) => trim((string) $code) !== '')
                ->map(fn ($code) => strtoupper(trim((string) $code)))
                ->unique()
                ->all();

            $missingZeroQtyItems = $visibleNoteItemsQuery()
                ->where('quantity', 0)
                ->get()
                ->filter(function ($item) use ($representedProductIds, $representedProductCodes) {
                    $productId = (int) ($item->product_id ?? 0);
                    $productCode = strtoupper(trim((string) ($item->product_code ?? '')));

                    if ($productId > 0 && in_array($productId, $representedProductIds, true)) {
                        return false;
                    }

                    return $productCode === '' || !in_array($productCode, $representedProductCodes, true);
                })
                ->map(function ($item) use ($note) {
                    return [
                        'product_id' => $item->product_id,
                        'product_code' => $item->product_code,
                        'part_number' => $item->part_number ?? '---',
                        'description' => $item->description,
                        'quantity' => 0,
                        'unit_price' => $item->unit_price,
                        'total_price' => 0,
                        'unit' => $item->unit,
                        'processed' => false,
                        'purchase_order_id' => null,
                        'po_number' => $note->purchase_note_number,
                    ];
                });

            if ($missingZeroQtyItems->isNotEmpty()) {
                $items = $detailItems->concat($missingZeroQtyItems)->values();
            } else {
                $items = $detailItems;
            }

            // Get transfer history for this note
            $transferHistory = [];
            $transferredCount = 0;
            try {
                $conversions = PartialPurchaseNoteConversion::on('ledger')
                    ->where('source_purchase_note_id', $note->id)
                    ->where('status', 'active')
                    ->get();
                $transferredCount = $conversions->count();
                if ($conversions->isNotEmpty()) {
                    $targetNoteIds = $conversions->pluck('new_purchase_note_id')->unique();
                    $targetNotes = PurchaseNote::whereIn('id', $targetNoteIds)->pluck('purchase_note_number', 'id');
                    $transferHistory = [
                        'transferred_items_count' => $transferredCount,
                        'target_notes' => $conversions->groupBy('new_purchase_note_id')->map(function($group, $targetId) use ($targetNotes) {
                            return [
                                'target_purchase_note_id' => (int) $targetId,
                                'target_purchase_note_number' => $targetNotes[$targetId] ?? 'N/A',
                                'items_count' => $group->count(),
                            ];
                        })->values()->toArray(),
                    ];
                }
            } catch (\Exception $e) {
                // Table may not exist yet
            }

            return response()->json([
                'success' => true,
                'note' => [
                    'id' => $note->id,
                    'purchase_note_number' => $note->purchase_note_number,
                    'date' => $note->date,
                    'status' => $note->status,
                    'total_amount' => $totalAmount,
                    'remarks' => $note->remarks,
                    'reference_number' => $note->reference_number,
                    'supplier' => $note->supplier,
                    'items' => $items,
                    'transferred_items_count' => $transferredCount,
                    'transfer_history' => $transferHistory,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch note details: ' . $e->getMessage()
            ], 422);
        }
    }

    public function store(Request $request)
    {
        $this->hydrateMissingItemProductIds($request);

        $request->validate([
            'supplier_code' => 'required|string',
            'date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|min:1|exists:masterlist.products,id',
            'items.*.quantity' => 'required|integer|min:0',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.unit_price' => 'required|numeric|min:0',
            
            // Currency converter persistence (save the sign/user input only)
            'items.*.currency' => 'nullable|string|max:10',
            'items.*.conversion_rate' => 'nullable|numeric',
            'items.*.unit_price_converted' => 'nullable|numeric',
            'items.*.total_price_converted' => 'nullable|numeric',

            // Transferred forgotten partial items
            'transferred_items' => 'nullable|array',
            'transferred_items.*.source_purchase_note_id' => 'required|integer|exists:purchase.purchase_notes,id',
            'transferred_items.*.source_purchase_note_item_id' => 'required|integer|exists:purchase.purchase_note_items,id',
            'transferred_items.*.product_id' => 'required|integer|exists:masterlist.products,id',
            'transferred_items.*.quantity' => 'required|integer|min:1',
            'transferred_items.*.unit_price' => 'required|numeric|min:0',
            'transferred_items.*.total_price' => 'required|numeric|min:0',
            'viber_item_ids' => 'nullable|array',
            'viber_item_ids.*' => 'integer|exists:purchase.viber_list_items,id',
        ]);

        try {
            return DB::connection('purchase')->transaction(function () use ($request) {
                // Lookup supplier by code
                $supplier = \App\Models\Supplier::where('supplier_code', $request->supplier_code)->first();
                if (!$supplier) {
                    throw new \Exception("Supplier with code '{$request->supplier_code}' not found.");
                }

                $poNumber = PurchaseNote::nextNumber();

                $purchaseNote = PurchaseNote::create([
                    'purchase_note_number' => $poNumber,
                    'supplier_id' => $supplier->id,
                    'date' => $request->date,
                    'reference_number' => $request->reference_number,
                    'remarks' => $request->remarks,
                    'total_amount' => 0,
                    'status' => 'Open',
                ]);

                $totalAmount = 0;
                $firstCurrency = null;
                $firstRate = null;
                $totalConverted = 0;
                $newItemIdsByIndex = [];

                foreach ($request->items as $idx => $itemData) {
                    $product = Product::on('masterlist')->find($itemData['product_id']);

                    $totalPrice = $itemData['quantity'] * $itemData['unit_price'];
                    $currency = $itemData['currency'] ?? 'PHP';
                    $rate = $itemData['conversion_rate'] ?? null;
                    $unitConverted = $itemData['unit_price_converted'] ?? null;
                    $itemTotalConverted = $itemData['total_price_converted'] ?? null;

                    if ($firstCurrency === null) {
                        $firstCurrency = $currency;
                        $firstRate = $rate;
                    }

                    $newItem = PurchaseNoteItem::create([
                        'purchase_note_id' => $purchaseNote->id,
                        'product_id' => $product->id,
                        'product_code' => $product->product_code,
                        'part_number' => $product->part_number,
                        'description' => $product->description,
                        'currency' => $currency,
                        'conversion_rate' => $rate,
                        'unit_price_converted' => $unitConverted,
                        'total_price_converted' => $itemTotalConverted,
                        'quantity' => $itemData['quantity'],
                        'unit' => (($unit = trim((string) ($itemData['unit'] ?? ''))) !== '') ? $unit : null,
                        'unit_price' => $itemData['unit_price'],
                        'total_price' => $totalPrice,
                    ]);

                    $newItemIdsByIndex[$idx] = $newItem->id;

                    $totalAmount += $totalPrice;
                    if ($itemTotalConverted) $totalConverted += (float)$itemTotalConverted;
                }

                $updateData = ['total_amount' => $totalAmount];
                if ($firstCurrency && $firstCurrency !== 'PHP') {
                    $updateData['currency'] = $firstCurrency;
                    $updateData['conversion_rate'] = $firstRate;
                    $updateData['total_amount_converted'] = $totalConverted ?: $totalAmount;
                }
                $purchaseNote->update($updateData);
                $purchaseNote = $purchaseNote->fresh(['items', 'supplier']);

                // Process transferred items: create conversion records and update source items
                $sourceNoteIdsToCheck = [];
                if ($request->has('transferred_items')) {
                    foreach ($request->transferred_items as $tItem) {
                        $sourceItem = PurchaseNoteItem::findOrFail($tItem['source_purchase_note_item_id']);
                        $sourceNote = $sourceItem->purchaseNote;

                        if ($sourceNote->supplier_id !== $supplier->id) {
                            throw new \Exception('Supplier mismatch on transferred item.');
                        }

                        $convertedAmount = $tItem['quantity'] * $tItem['unit_price'];
                        $originalSourceNoteId = $sourceItem->purchase_note_id;
                        $isFullTransfer = $tItem['quantity'] >= $sourceItem->quantity;

                        if ($isFullTransfer) {
                            // Full transfer: move ownership to destination note
                            $duplicateItemId = $newItemIdsByIndex[$tItem['item_index']] ?? null;

                            $sourceItem->update([
                                'purchase_note_id' => $purchaseNote->id,
                                'total_price' => $tItem['quantity'] * $sourceItem->unit_price,
                            ]);

                            if ($duplicateItemId && $duplicateItemId != $sourceItem->id) {
                                PurchaseNoteItem::where('id', $duplicateItemId)->delete();
                            }

                            $newItemId = $sourceItem->id;
                        } else {
                            // Partial transfer: reduce source item quantity, keep the newly created item
                            $newSourceQty = $sourceItem->quantity - $tItem['quantity'];
                            $sourceItem->update([
                                'quantity' => $newSourceQty,
                                'total_price' => $newSourceQty * $sourceItem->unit_price,
                            ]);

                            $newItemId = $newItemIdsByIndex[$tItem['item_index']] ?? 0;
                        }

                        PartialPurchaseNoteConversion::on('ledger')->create([
                            'source_purchase_note_id' => $tItem['source_purchase_note_id'],
                            'source_purchase_note_item_id' => $tItem['source_purchase_note_item_id'],
                            'new_purchase_note_id' => $purchaseNote->id,
                            'new_purchase_note_item_id' => $newItemId,
                            'product_ledger_id' => null,
                            'supplier_id' => $supplier->id,
                            'transferred_quantity' => $tItem['quantity'],
                            'transferred_amount' => $convertedAmount,
                            'status' => 'active',
                        ]);

                        $sourceNoteIdsToCheck[$originalSourceNoteId] = true;
                    }

                    // Recalculate source note totals and close if no remaining owned items.
                    // Use PO data to calculate true remaining qty (PN item qty may be stale).
                    // Also subtract already-transferred qty from effective remaining.
                    foreach (array_keys($sourceNoteIdsToCheck) as $sourceNoteId) {
                        $sourceNoteForCheck = PurchaseNote::find($sourceNoteId);
                        if (!$sourceNoteForCheck) continue;

                        $checkPoItems = \App\Models\PurchaseOrderItem::whereHas('purchaseOrder', function ($q) use ($sourceNoteForCheck) {
                            $q->where('reference_number', $sourceNoteForCheck->purchase_note_number);
                        })->get();

                        // Load active transfers from this source note to subtract from remaining
                        $sourceConversions = PartialPurchaseNoteConversion::on('ledger')
                            ->where('source_purchase_note_id', $sourceNoteId)
                            ->where('status', 'active')
                            ->get()
                            ->groupBy('source_purchase_note_item_id');

                        $allSourceItems = PurchaseNoteItem::where('purchase_note_id', $sourceNoteId)
                            ->withoutForceCancelled()
                            ->get();
                        $hasEffectiveRemaining = false;
                        $newTotal = 0;

                        foreach ($allSourceItems as $item) {
                            $checkRelated = $checkPoItems->where('product_id', $item->product_id);
                            $checkOrdered = $checkRelated->sum('quantity');
                            $checkReceived = $checkRelated->sum('actual_quantity');

                            if ($checkOrdered > 0) {
                                $effectiveRemaining = max(0, (int) ($checkOrdered - $checkReceived));
                            } else {
                                // Try product_code fallback
                                $normalizedCode = preg_replace('/\s+/', ' ', trim($item->product_code ?? ''));
                                $matchedByCode = $checkPoItems->first(function ($pi) use ($normalizedCode) {
                                    return preg_replace('/\s+/', ' ', trim($pi->product_code ?? '')) === $normalizedCode;
                                });
                                if ($matchedByCode) {
                                    $effectiveRemaining = max(0, (int) ($matchedByCode->quantity - $matchedByCode->actual_quantity));
                                } else {
                                    // No PO match — trust PN item quantity
                                    $effectiveRemaining = max(0, (int) $item->quantity);
                                }
                            }

                            // Subtract already-transferred quantity for this item
                            $transferredQty = 0;
                            if (isset($sourceConversions[$item->id])) {
                                $transferredQty = (int) $sourceConversions[$item->id]->sum('transferred_quantity');
                            }
                            $effectiveRemaining = max(0, $effectiveRemaining - $transferredQty);

                            if ($effectiveRemaining > 0) {
                                $hasEffectiveRemaining = true;
                                $newTotal += $effectiveRemaining * (float) $item->unit_price;
                            }
                        }

                        $updateData = ['total_amount' => max(0, $newTotal)];
                        if (!$hasEffectiveRemaining) {
                            $updateData['status'] = 'Closed';
                        }
                        PurchaseNote::where('id', $sourceNoteId)->update($updateData);
                    }
                }

                $this->writePurchaseNoteAudit('Created', $purchaseNote, [
                    'after' => $this->buildPurchaseNoteAuditSnapshot($purchaseNote),
                ]);

                // Mark Viber list items as shipped if this came from Viber
                $viberItemIds = $request->input('viber_item_ids');
                if (empty($viberItemIds)) {
                    $viberPrepare = session('viber_prepare_note');
                    if ($viberPrepare && !empty($viberPrepare['viber_item_ids'])) {
                        $viberItemIds = $viberPrepare['viber_item_ids'];
                    }
                }
                if (!empty($viberItemIds)) {
                    ViberListItem::whereIn('id', $viberItemIds)
                        ->update([
                            'purchase_note_id' => $purchaseNote->id,
                            'shipped_at' => now(),
                        ]);
                    session()->forget('viber_prepare_note');
                }

                // Update product costs in masterlist for all finalized items
                $purchaseNote->load('items');
                foreach ($purchaseNote->items as $pni) {
                    try {
                        DB::connection('masterlist')
                            ->table('products')
                            ->where('id', $pni->product_id)
                            ->update([
                                'cost' => (float) $pni->unit_price,
                                'updated_at' => now(),
                            ]);
                    } catch (\Exception $costErr) {
                        Log::warning('PN store: Failed to update cost for product ID ' . $pni->product_id . ': ' . $costErr->getMessage());
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Purchase Note created successfully!',
                    'pn_number' => $poNumber
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Purchase Note Store Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create Purchase Note: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $this->hydrateMissingItemProductIds($request);

        $request->validate([
            'supplier_code' => 'required|string',
            'date' => 'required|date',
            'linked_purchase_order_id' => 'nullable|integer|exists:purchase.purchase_orders,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|min:1|exists:masterlist.products,id',
            // Zero quantity is valid for a Purchase Note placeholder item.
            // Negative quantities remain invalid.
            'items.*.quantity' => 'required|integer|min:0',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.currency' => 'nullable|string|max:10',
            'items.*.conversion_rate' => 'nullable|numeric',
            'items.*.unit_price_converted' => 'nullable|numeric',
            'items.*.total_price_converted' => 'nullable|numeric',
        ]);

        try {
            return DB::connection('purchase')->transaction(function () use ($request, $id) {
                $purchaseNote = PurchaseNote::with(['items', 'supplier'])->lockForUpdate()->findOrFail($id);

                if (strcasecmp((string) $purchaseNote->status, 'Partial') === 0) {
                    app(PurchaseNoteStatusService::class)->repairCompletedPartialNote($purchaseNote);
                    $purchaseNote = $purchaseNote->fresh(['items', 'supplier']);
                }

                $before = $this->buildPurchaseNoteAuditSnapshot($purchaseNote);
                $statusBeforeUpdate = strtoupper(trim((string) $purchaseNote->status));
                $isClosed = $statusBeforeUpdate === 'CLOSED';
                $isPartial = $statusBeforeUpdate === 'PARTIAL';
                $linkedPoId = (int) $request->input('linked_purchase_order_id', 0);
                $isInvoiceScopedEdit = $linkedPoId > 0;

                // A selected invoice means the browser submitted only that invoice's
                // items, not the complete Purchase Note. Never use that filtered list
                // to replace the whole Purchase Note or other invoice arrangements.
                $shouldSyncLinkedPoSnapshot = $isInvoiceScopedEdit || ($isClosed && $linkedPoId === 0);

                $supplier = Supplier::where('supplier_code', $request->supplier_code)->first();
                if (!$supplier) {
                    throw new \RuntimeException("Supplier with code '{$request->supplier_code}' not found.");
                }

                $purchaseNote->update([
                    'supplier_id' => $supplier->id,
                    'date' => $request->date,
                    'reference_number' => $request->reference_number,
                    'remarks' => $request->remarks,
                ]);

                // Product ID is the authoritative identity. Keep browser order while
                // merging accidental duplicate rows for the same product.
                $deduped = [];
                foreach ($request->items as $itemData) {
                    $productId = (int) $itemData['product_id'];
                    if (isset($deduped[$productId])) {
                        $deduped[$productId]['quantity'] += (int) $itemData['quantity'];
                        $deduped[$productId]['unit'] = $itemData['unit'] ?? $deduped[$productId]['unit'] ?? null;
                        $deduped[$productId]['unit_price'] = (float) $itemData['unit_price'];
                        $deduped[$productId]['currency'] = $itemData['currency'] ?? $deduped[$productId]['currency'] ?? 'PHP';
                        $deduped[$productId]['conversion_rate'] = $itemData['conversion_rate'] ?? $deduped[$productId]['conversion_rate'] ?? null;
                        $deduped[$productId]['unit_price_converted'] = $itemData['unit_price_converted'] ?? $deduped[$productId]['unit_price_converted'] ?? null;
                        $deduped[$productId]['total_price_converted'] = $itemData['total_price_converted'] ?? $deduped[$productId]['total_price_converted'] ?? null;
                    } else {
                        $deduped[$productId] = $itemData;
                    }
                }

                $productIds = array_keys($deduped);
                $products = Product::on('masterlist')
                    ->whereIn('id', $productIds)
                    ->get()
                    ->keyBy(fn ($product) => (int) $product->id);

                $resolvedItems = [];
                foreach ($deduped as $productId => $itemData) {
                    $product = $products->get((int) $productId);
                    if (!$product) {
                        throw new \RuntimeException("Product ID {$productId} was not found in the master list.");
                    }

                    $quantity = (int) $itemData['quantity'];
                    $unitPrice = (float) $itemData['unit_price'];
                    $unit = trim((string) ($itemData['unit'] ?? ''));

                    $resolvedItems[(int) $productId] = [
                        'product_id' => (int) $product->id,
                        'product_code' => $product->product_code,
                        'part_number' => $product->part_number,
                        'description' => $product->description,
                        'quantity' => $quantity,
                        'unit' => $unit !== '' ? $unit : null,
                        'unit_price' => $unitPrice,
                        'total_price' => $quantity * $unitPrice,
                        'currency' => $itemData['currency'] ?? 'PHP',
                        'conversion_rate' => $itemData['conversion_rate'] ?? null,
                        'unit_price_converted' => $itemData['unit_price_converted'] ?? null,
                        'total_price_converted' => $itemData['total_price_converted'] ?? null,
                    ];
                }

                if ($isInvoiceScopedEdit) {
                    // IMPORTANT: selectedItems contains only the chosen invoice.
                    // Preserve every existing Purchase Note row (and therefore every
                    // other invoice grouping). Existing PN quantities are current
                    // remaining quantities, so an invoice quantity must never overwrite
                    // them. Only add a PN row for a genuinely new product and refresh
                    // price/unit metadata for products that already exist on the note.
                    $existingNoteItems = PurchaseNoteItem::where('purchase_note_id', $purchaseNote->id)
                        ->withoutForceCancelled()
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get()
                        ->groupBy(fn ($item) => (int) $item->product_id);

                    foreach ($resolvedItems as $productId => $itemData) {
                        $existingGroup = $existingNoteItems->get((int) $productId, collect());

                        if ($existingGroup->isEmpty()) {
                            PurchaseNoteItem::create([
                                'purchase_note_id' => $purchaseNote->id,
                                'product_id' => $itemData['product_id'],
                                'product_code' => $itemData['product_code'],
                                'part_number' => $itemData['part_number'],
                                'description' => $itemData['description'],
                                'currency' => $itemData['currency'],
                                'conversion_rate' => $itemData['conversion_rate'],
                                'unit_price_converted' => $itemData['unit_price_converted'],
                                'total_price_converted' => $itemData['total_price_converted'],
                                'quantity' => $itemData['quantity'],
                                'unit' => $itemData['unit'],
                                'unit_price' => $itemData['unit_price'],
                                'total_price' => $itemData['total_price'],
                            ]);
                            continue;
                        }

                        foreach ($existingGroup as $existingNoteItem) {
                            $currentRemaining = (int) $existingNoteItem->quantity;
                            $existingNoteItem->update([
                                'product_code' => $itemData['product_code'],
                                'part_number' => $itemData['part_number'],
                                'description' => $itemData['description'],
                                'currency' => $itemData['currency'],
                                'conversion_rate' => $itemData['conversion_rate'],
                                'unit_price_converted' => $itemData['unit_price_converted'],
                                'total_price_converted' => $itemData['total_price_converted'],
                                'unit' => $itemData['unit'],
                                'unit_price' => $itemData['unit_price'],
                                'total_price' => $currentRemaining * $itemData['unit_price'],
                            ]);
                        }
                    }
                } else {
                    // Whole-note edit (no real invoice selected): retain the original
                    // behavior of replacing the complete PN snapshot because the
                    // submitted list really is the whole Purchase Note in this mode.
                    $purchaseNote->items()->delete();

                    foreach ($resolvedItems as $itemData) {
                        PurchaseNoteItem::create([
                            'purchase_note_id' => $purchaseNote->id,
                            'product_id' => $itemData['product_id'],
                            'product_code' => $itemData['product_code'],
                            'part_number' => $itemData['part_number'],
                            'description' => $itemData['description'],
                            'currency' => $itemData['currency'],
                            'conversion_rate' => $itemData['conversion_rate'],
                            'unit_price_converted' => $itemData['unit_price_converted'],
                            'total_price_converted' => $itemData['total_price_converted'],
                            'quantity' => $itemData['quantity'],
                            'unit' => $itemData['unit'],
                            'unit_price' => $itemData['unit_price'],
                            'total_price' => $itemData['total_price'],
                        ]);
                    }
                }

                // Recalculate the Purchase Note header from ALL surviving PN rows,
                // not only the currently selected invoice.
                $activeNoteItems = PurchaseNoteItem::where('purchase_note_id', $purchaseNote->id)
                    ->withoutForceCancelled()
                    ->orderBy('id')
                    ->get();

                // Whole-note quantity edits must flow back to the linked Viber
                // source rows.  Invoice-scoped edits intentionally represent only
                // one PO snapshot, so they are excluded from this source sync.
                if (!$isInvoiceScopedEdit && !$isClosed) {
                    $this->syncLinkedViberQuantitiesFromPurchaseNote($purchaseNote);
                }

                $totalAmount = round((float) $activeNoteItems->sum('total_price'), 2);
                $firstItem = $activeNoteItems->first();
                $updateData = ['total_amount' => $totalAmount];

                if ($firstItem && !empty($firstItem->currency) && $firstItem->currency !== 'PHP') {
                    $updateData['currency'] = $firstItem->currency;
                    $updateData['conversion_rate'] = $firstItem->conversion_rate;
                    $convertedTotal = (float) $activeNoteItems
                        ->filter(fn ($item) => $item->total_price_converted !== null)
                        ->sum('total_price_converted');
                    $updateData['total_amount_converted'] = $convertedTotal ?: $totalAmount;
                }

                $purchaseNote->update($updateData);
                $purchaseNote = $purchaseNote->fresh(['items', 'supplier']);

                $addedItems = [];
                $deletedItems = [];
                $updatedItems = [];
                $latestPo = null;

                // Only the selected Purchase Order is synchronized. resolvedItems is
                // the selected invoice snapshot supplied by the browser; never use
                // purchaseNote->items here because that collection also contains
                // products arranged under other invoices.
                if ($shouldSyncLinkedPoSnapshot) {
                    $linkValues = collect([
                        $purchaseNote->purchase_note_number,
                        $before['purchase_note']['purchase_note_number'] ?? null,
                        $purchaseNote->reference_number,
                    ])->filter()->map(fn ($value) => trim((string) $value))->unique()->values();

                    if ($linkedPoId > 0) {
                        $latestPo = PurchaseOrder::with('items')->lockForUpdate()->findOrFail($linkedPoId);
                        if (!$linkValues->contains(trim((string) $latestPo->reference_number))) {
                            throw new \RuntimeException('The selected Purchase Order is not linked to this Purchase Note.');
                        }
                    } else {
                        $latestPo = PurchaseOrder::with('items')
                            ->whereIn('reference_number', $linkValues->all())
                            ->orderByDesc('id')
                            ->lockForUpdate()
                            ->first();
                    }

                    if ($latestPo) {
                        $oldPoItems = $latestPo->items->keyBy(fn ($item) => (int) $item->product_id);
                        $newInvoiceItems = collect($resolvedItems)->keyBy(fn ($item) => (int) $item['product_id']);

                        foreach ($oldPoItems as $productId => $oldItem) {
                            if ($newInvoiceItems->has((int) $productId)) {
                                continue;
                            }

                            $deletedItems[] = [
                                'id' => (int) $oldItem->id,
                                'product_id' => (int) $oldItem->product_id,
                                'product_code' => $oldItem->product_code,
                                'unit' => $oldItem->unit,
                                'description' => $oldItem->description,
                                'quantity' => (float) $oldItem->quantity,
                                'actual_quantity' => (float) $oldItem->actual_quantity,
                                'unit_price' => (float) $oldItem->unit_price,
                                'discount_percent' => (float) $oldItem->discount_percent,
                            ];

                            $oldItem->delete();
                        }

                        foreach ($newInvoiceItems as $productId => $invoiceItem) {
                            $existing = $oldPoItems->get((int) $productId);
                            $newQuantity = (int) $invoiceItem['quantity'];
                            $newUnitPrice = (float) $invoiceItem['unit_price'];
                            $newUnit = trim((string) ($invoiceItem['unit'] ?? ''));

                            if ($existing) {
                                $beforeItem = $existing->toArray();
                                $quantityChanged = (float) $existing->quantity !== (float) $newQuantity;
                                $actualQuantity = $quantityChanged
                                    ? $newQuantity
                                    : (float) $existing->actual_quantity;
                                $discountPercent = (float) $existing->discount_percent;
                                $discountAmount = $newQuantity * $newUnitPrice * ($discountPercent / 100);
                                $subtotal = ($newQuantity * $newUnitPrice) - $discountAmount;
                                $actualDiscount = $actualQuantity * $newUnitPrice * ($discountPercent / 100);
                                $actualSubtotal = ($actualQuantity * $newUnitPrice) - $actualDiscount;

                                $existing->update([
                                    'product_code' => $invoiceItem['product_code'],
                                    'description' => $invoiceItem['description'],
                                    'quantity' => $newQuantity,
                                    'actual_quantity' => $actualQuantity,
                                    'unit_price' => $newUnitPrice,
                                    'unit' => $newUnit !== '' ? $newUnit : null,
                                    'discount_amount' => $discountAmount,
                                    'subtotal' => $subtotal,
                                    'actual_subtotal' => $actualSubtotal,
                                ]);

                                $existing->refresh();
                                $changedFields = [];
                                foreach (['unit', 'quantity', 'actual_quantity', 'unit_price'] as $field) {
                                    $beforeValue = $beforeItem[$field] ?? null;
                                    $afterValue = $existing->{$field};
                                    if ((string) $beforeValue !== (string) $afterValue) {
                                        $changedFields[$field] = [
                                            'before' => $beforeValue,
                                            'after' => $afterValue,
                                        ];
                                    }
                                }

                                if (!empty($changedFields)) {
                                    $updatedItems[] = [
                                        'id' => (int) $existing->id,
                                        'product_id' => (int) $existing->product_id,
                                        'product_code' => $existing->product_code,
                                        'unit' => $existing->unit,
                                        'description' => $existing->description,
                                        'quantity' => (float) $existing->quantity,
                                        'actual_quantity' => (float) $existing->actual_quantity,
                                        'unit_price' => (float) $existing->unit_price,
                                        'discount_percent' => (float) $existing->discount_percent,
                                        'changes' => $changedFields,
                                    ];
                                }
                            } else {
                                $subtotal = $newQuantity * $newUnitPrice;
                                $createdItem = PurchaseOrderItem::create([
                                    'purchase_order_id' => $latestPo->id,
                                    'product_id' => $invoiceItem['product_id'],
                                    'product_code' => $invoiceItem['product_code'],
                                    'description' => $invoiceItem['description'],
                                    'quantity' => $newQuantity,
                                    'actual_quantity' => $newQuantity,
                                    'received_quantity' => 0,
                                    'unit_price' => $newUnitPrice,
                                    'discount_percent' => 0,
                                    'discount_amount' => 0,
                                    'subtotal' => $subtotal,
                                    'actual_subtotal' => $subtotal,
                                    'unit' => $newUnit !== '' ? $newUnit : null,
                                ]);

                                $addedItems[] = [
                                    'id' => (int) $createdItem->id,
                                    'product_id' => (int) $createdItem->product_id,
                                    'product_code' => $createdItem->product_code,
                                    'unit' => $createdItem->unit,
                                    'description' => $createdItem->description,
                                    'quantity' => (float) $createdItem->quantity,
                                    'actual_quantity' => (float) $createdItem->actual_quantity,
                                    'unit_price' => (float) $createdItem->unit_price,
                                    'discount_percent' => 0,
                                ];
                            }
                        }

                        $latestPo->refresh();
                        $poItems = $latestPo->items()->get();
                        $orderedBeforeAdditional = (float) $poItems->sum('subtotal');
                        $actualBeforeAdditional = (float) $poItems->sum('actual_subtotal');
                        $additionalPercent = (float) $latestPo->additional_discount_percent;
                        $additionalAmount = round($orderedBeforeAdditional * ($additionalPercent / 100), 2);
                        $actualAdditionalAmount = round($actualBeforeAdditional * ($additionalPercent / 100), 2);

                        $latestPo->update([
                            'total_amount' => round($orderedBeforeAdditional - $additionalAmount, 2),
                            'actual_total_amount' => round($actualBeforeAdditional - $actualAdditionalAmount, 2),
                            'additional_discount_amount' => $additionalAmount,
                        ]);
                    }
                }

                $this->writePurchaseNoteAudit('Updated', $purchaseNote, [
                    'before' => $before,
                    'after' => $this->buildPurchaseNoteAuditSnapshot($purchaseNote),
                    'purchase_order_sync' => [
                        'purchase_order_id' => $latestPo?->id,
                        'invoice_scoped_edit' => $isInvoiceScopedEdit,
                        'added_items' => $addedItems,
                        'deleted_items' => $deletedItems,
                        'updated_items' => $updatedItems,
                        'ledger_finalization_deferred' => true,
                    ],
                ]);

                $response = [
                    'success' => true,
                    'message' => 'Purchase Note updated successfully!',
                    'has_changes' => !empty($deletedItems) || !empty($addedItems) || !empty($updatedItems),
                    'po_id' => $latestPo?->id,
                    'deleted_items' => $deletedItems,
                    'added_items' => $addedItems,
                    'updated_items' => $updatedItems,
                    'ledger_finalization_deferred' => $shouldSyncLinkedPoSnapshot && $latestPo !== null,
                ];

                if (!$isClosed) {
                    $statusResult = app(PurchaseNoteStatusService::class)
                        ->syncFromRemainingItems($purchaseNote->fresh(['items']));

                    $response['status'] = $statusResult['status'];
                    $response['remaining_quantity'] = $statusResult['remaining_quantity'];
                }

                return response()->json($response);
            });
        } catch (\Throwable $e) {
            Log::error('Purchase Note Update Error: ' . $e->getMessage(), [
                'purchase_note_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update Purchase Note: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $user = session('user');
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
            if (is_array($user)) {
                $user = (object) $user;
            }

            if (!Schema::connection('ledger')->hasTable('archived_records')) {
                throw new \Exception('Archive table is missing. Please run the archive migration first.');
            }

            DB::connection('purchase')->beginTransaction();
            DB::connection('ledger')->beginTransaction();

            $purchaseNote = PurchaseNote::with(['items', 'supplier'])->lockForUpdate()->findOrFail($id);
            $items = $purchaseNote->items->map(fn ($item) => $item->toArray())->values()->all();
            $supplierName = (string) optional($purchaseNote->supplier)->name;
            $auditSnapshot = $this->buildPurchaseNoteAuditSnapshot($purchaseNote);

            // Fetch related records BEFORE cascade delete
            $pnNumber = $purchaseNote->purchase_note_number;
            $relatedPOs = PurchaseOrder::where('po_number', $pnNumber)->get();
            $relatedPOItems = collect();
            $relatedReturns = collect();
            $relatedReturnItems = collect();
            if ($relatedPOs->isNotEmpty()) {
                $poIds = $relatedPOs->pluck('id');
                $relatedPOItems = PurchaseOrderItem::whereIn('purchase_order_id', $poIds)->get();
                $relatedReturns = PurchaseReturn::whereIn('po_id', $poIds)->get();
                if ($relatedReturns->isNotEmpty()) {
                    $returnIds = $relatedReturns->pluck('id');
                    $relatedReturnItems = PurchaseReturnItem::whereIn('purchase_return_id', $returnIds)->get();
                }
            }

            $archivedData = [
                'purchase_note' => $purchaseNote->toArray(),
                'items' => $items,
                'purchase_orders' => $relatedPOs->toArray(),
                'purchase_order_items' => $relatedPOItems->toArray(),
                'purchase_returns' => $relatedReturns->toArray(),
                'purchase_return_items' => $relatedReturnItems->toArray(),
            ];

            $deletedBy = $user->name ?? $user->username ?? $user->User_ID ?? 'Admin';
            $displayId = (string) ($purchaseNote->purchase_note_number ?? $purchaseNote->id);
            $dataName = trim($supplierName !== '' ? $supplierName : ('Purchase Note ' . $displayId));

            $existingArchive = DB::connection('ledger')
                ->table('archived_records')
                ->where('module', 'purchase_note')
                ->whereNull('restored_at')
                ->where('source_connection', 'purchase')
                ->where('source_table', 'purchase_notes')
                ->where('source_id', $purchaseNote->id)
                ->first();

            if ($existingArchive) {
                throw new \Exception('This purchase note is already archived.');
            }

            DB::connection('ledger')->table('archived_records')->insert([
                'module' => 'purchase_note',
                'source_connection' => 'purchase',
                'source_table' => 'purchase_notes',
                'source_id' => $purchaseNote->id,
                'display_id' => $displayId,
                'data_name' => $dataName,
                'archived_data' => json_encode($archivedData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'deleted_by' => (string) $deletedBy,
                'deleted_at' => now(),
                'expires_at' => now()->addDays(30),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->writePurchaseNoteAudit('Deleted', $purchaseNote, [
                'before' => $auditSnapshot,
            ]);

            $deletedCounts = [
                'purchase_notes' => 1,
                'purchase_note_items' => count($items),
                'purchase_orders' => $relatedPOs->count(),
                'purchase_order_items' => $relatedPOItems->count(),
                'purchase_returns' => $relatedReturns->count(),
                'purchase_return_items' => $relatedReturnItems->count(),
            ];

            // FK-safe order: return items → returns → PO items → POs → note items → note
            if ($relatedReturnItems->isNotEmpty()) {
                PurchaseReturnItem::whereIn('id', $relatedReturnItems->pluck('id'))->delete();
            }
            if ($relatedReturns->isNotEmpty()) {
                PurchaseReturn::whereIn('id', $relatedReturns->pluck('id'))->delete();
            }
            if ($relatedPOItems->isNotEmpty()) {
                PurchaseOrderItem::whereIn('id', $relatedPOItems->pluck('id'))->delete();
            }
            if ($relatedPOs->isNotEmpty()) {
                PurchaseOrder::whereIn('id', $relatedPOs->pluck('id'))->delete();
            }

            $purchaseNote->items()->delete();
            $purchaseNote->delete();

            DB::connection('ledger')->commit();
            DB::connection('purchase')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Purchase Note and all related Purchase Orders, Order Items, Purchase Returns, and Return Items archived and deleted successfully.',
                'deleted' => $deletedCounts,
            ]);
        } catch (\Exception $e) {
            if (DB::connection('ledger')->transactionLevel() > 0) DB::connection('ledger')->rollBack();
            if (DB::connection('purchase')->transactionLevel() > 0) DB::connection('purchase')->rollBack();
            Log::error('Purchase Note Delete Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete Purchase Note: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Product IDs already on Open/Partial notes for a supplier (for Add Purchase Note validation).
     */
    public function getSupplierOpenPartialItemConflicts(Request $request)
    {
        $supplierCode = $request->get('supplier_code');
        if (empty($supplierCode)) {
            return response()->json([
                'success' => true,
                'conflicts' => [],
            ]);
        }

        $supplier = Supplier::where('supplier_code', $supplierCode)->first();
        if (!$supplier) {
            return response()->json([
                'success' => true,
                'conflicts' => [],
            ]);
        }

        $notesQuery = PurchaseNote::with(['items' => function ($query) {
                $query->withoutForceCancelled();
            }])
            ->where('supplier_id', $supplier->id)
            ->whereIn('status', ['Open', 'Partial']);

        if ($request->filled('exclude_note_id')) {
            $notesQuery->where('id', '!=', $request->get('exclude_note_id'));
        }

        $notes = $notesQuery->get();

        $conflicts = [];
        foreach ($notes as $note) {
            foreach ($note->items as $item) {
                $productId = (int) $item->product_id;
                if (!isset($conflicts[$productId])) {
                    $conflicts[$productId] = [];
                }

                $alreadyListed = collect($conflicts[$productId])->contains('note_id', $note->id);
                if (!$alreadyListed) {
                    $conflicts[$productId][] = [
                        'note_id' => $note->id,
                        'pn_number' => $note->purchase_note_number,
                        'status' => $note->status,
                        'product_code' => $item->product_code,
                    ];
                }
            }
        }

        return response()->json([
            'success' => true,
            'conflicts' => $conflicts,
        ]);
    }

    public function fetchOpenPurchaseNotes()
    {
        $openNotes = PurchaseNote::whereIn('status', ['Open', 'Partial'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'notes' => $openNotes->map(function ($note) {
                // Manually fetch supplier from masterlist connection
                $supplier = null;
                try {
                    $supplier = Supplier::on('masterlist')->find($note->supplier_id);
                } catch (\Exception $e) {
                    Log::warning('Failed to fetch supplier: ' . $e->getMessage());
                }
                
                return [
                    'id' => $note->id,
                    'supplier_code' => $supplier ? $supplier->supplier_code : 'N/A',
                    'supplier_name' => $supplier ? $supplier->name : 'N/A',
                    'trans_no' => $note->purchase_note_number,
                    'date' => $note->date,
                    'status' => $note->status,
                    'total_amount' => $note->total_amount,
                    'reference' => $note->reference_number
                ];
            })
        ]);
    }

    /**
     * Keep Purchase Note print rows in the exact order the items were saved on
     * the Purchase Note. Closed/Partial notes can print Purchase Order snapshots,
     * whose row IDs reflect processing order rather than the user's PN arrangement.
     */
    private function orderPrintItemsByPurchaseNoteSequence($items, $purchaseNoteItems)
    {
        $purchaseNoteItems = collect($purchaseNoteItems)->values();
        $items = collect($items)->values();

        if ($items->count() < 2 || $purchaseNoteItems->isEmpty()) {
            return $items;
        }

        $orderByProductId = [];
        $orderByProductCode = [];

        foreach ($purchaseNoteItems as $index => $purchaseNoteItem) {
            $productId = (int) ($purchaseNoteItem->product_id ?? 0);
            $productCode = strtoupper(preg_replace('/\s+/', ' ', trim((string) ($purchaseNoteItem->product_code ?? ''))));

            if ($productId > 0 && !array_key_exists($productId, $orderByProductId)) {
                $orderByProductId[$productId] = $index;
            }
            if ($productCode !== '' && !array_key_exists($productCode, $orderByProductCode)) {
                $orderByProductCode[$productCode] = $index;
            }
        }

        return $items
            ->map(function ($item, $loadedIndex) use ($orderByProductId, $orderByProductCode) {
                $productId = (int) ($item->product_id ?? 0);
                $productCode = strtoupper(preg_replace('/\s+/', ' ', trim((string) ($item->product_code ?? ''))));
                $rank = PHP_INT_MAX;

                if ($productId > 0 && array_key_exists($productId, $orderByProductId)) {
                    $rank = $orderByProductId[$productId];
                } elseif ($productCode !== '' && array_key_exists($productCode, $orderByProductCode)) {
                    $rank = $orderByProductCode[$productCode];
                }

                return [
                    'item' => $item,
                    'rank' => $rank,
                    'loaded_index' => $loadedIndex,
                ];
            })
            ->sort(function ($a, $b) {
                if ($a['rank'] !== $b['rank']) {
                    return $a['rank'] <=> $b['rank'];
                }

                return $a['loaded_index'] <=> $b['loaded_index'];
            })
            ->pluck('item')
            ->values();
    }

    public function print($id)
    {
        try {
            // purchase_note_items.id is the authoritative insertion sequence.
            $note = PurchaseNote::with([
                'supplier',
                'items' => fn ($query) => $query->orderBy('id', 'asc'),
            ])->findOrFail($id);
            $purchaseNotePrintSequence = $note->items->values();
            
            // Get items based on status
            // Include all items, marking transferred ones (quantity = 0) 
            $items = $note->items->map(function($item) {
                $item->is_transferred = ($item->quantity <= 0);
                return $item;
            });
            $totalAmount = $note->total_amount;
            
            // If the note is CLOSED, get the actual processed items
            if (strtoupper($note->status) === 'CLOSED') {
                $processedItems = \App\Models\PurchaseOrderItem::with('product')->whereHas('purchaseOrder', function($q) use ($note) {
                    $q->where('reference_number', $note->purchase_note_number);
                })->orderBy('id', 'asc')->get();

                if ($processedItems->count() > 0) {
                    $items = $processedItems->map(function($pi) {
                        return (object)[
                            'product_id' => $pi->product_id,
                            'product_code' => $pi->product_code,
                            'part_number' => $pi->product?->part_number ?? '',
                            'description' => trim((string) ($pi->description ?? '')) !== '' ? $pi->description : ($pi->product?->description ?? ''),
                            'quantity' => $pi->actual_quantity,
                            'unit_price' => $pi->unit_price,
                            'total_price' => $pi->actual_subtotal,
                            'is_transferred' => false,
                        ];
                    });
                    $items = $this->orderPrintItemsByPurchaseNoteSequence($items, $purchaseNotePrintSequence);
                    $totalAmount = $processedItems->sum('actual_subtotal');
                } else {
                    // Fallback: use purchase_note_items, mark transferred (quantity = 0)
                    $items = $note->items->map(function($item) {
                        $item->is_transferred = ($item->quantity <= 0);
                        return $item;
                    });
                    $totalAmount = $items->sum('total_price');
                }
            }
            // If the note is PARTIAL, get items with discrepancies
            elseif (strtoupper($note->status) === 'PARTIAL') {
                $processedItems = \App\Models\PurchaseOrderItem::with('product')->whereHas('purchaseOrder', function($q) use ($note) {
                    $q->where('reference_number', $note->purchase_note_number);
                })->orderBy('id', 'asc')->get();

                if ($processedItems->count() > 0) {
                    $discrepancyItems = $processedItems->filter(function($pi) {
                        return $pi->quantity != $pi->actual_quantity;
                    });

                    if ($discrepancyItems->count() > 0) {
                        $items = $discrepancyItems->map(function($pi) {
                            return (object)[
                                'product_id' => $pi->product_id,
                                'product_code' => $pi->product_code,
                                'part_number' => $pi->product?->part_number ?? '',
                                'description' => trim((string) ($pi->description ?? '')) !== '' ? $pi->description : ($pi->product?->description ?? ''),
                                'quantity' => $pi->actual_quantity,
                                'unit_price' => $pi->unit_price,
                                'total_price' => $pi->actual_subtotal,
                            ];
                        });
                        $items = $this->orderPrintItemsByPurchaseNoteSequence($items, $purchaseNotePrintSequence);
                        $totalAmount = $discrepancyItems->sum('actual_subtotal');
                    }
                }
            }
            
            // Get incoming and outgoing transfer info
            $outgoingTransferRows = [];
            try {
                // OUTGOING transfers (items transferred FROM this note TO another)
                $outgoingConversions = PartialPurchaseNoteConversion::on('ledger')
                    ->where('source_purchase_note_id', $note->id)
                    ->where('status', 'active')
                    ->get();
                if ($outgoingConversions->isNotEmpty()) {
                    $targetNoteIds = $outgoingConversions->pluck('new_purchase_note_id')->unique();
                    $targetNotes = PurchaseNote::whereIn('id', $targetNoteIds)->pluck('purchase_note_number', 'id');
                    $existingItemIds = $note->items->pluck('id')->toArray();
                    foreach ($outgoingConversions as $conv) {
                        if (!in_array($conv->source_purchase_note_item_id, $existingItemIds)) {
                            $targetNoteNumber = $targetNotes[$conv->new_purchase_note_id] ?? 'N/A';
                            $outgoingTransferRows[] = (object)[
                                'product_code' => '',
                                'part_number' => '',
                                'description' => "Transferred to Note #{$targetNoteNumber}",
                                'quantity' => 0,
                                'unit_price' => $conv->transferred_quantity > 0 ? round($conv->transferred_amount / $conv->transferred_quantity, 2) : 0,
                                'total_price' => (float) $conv->transferred_amount,
                                'unit' => '',
                                'is_transferred' => true,
                                'is_reconstructed' => true,
                            ];
                        }
                    }
                }

                // INCOMING transfers (items transferred INTO this note FROM another)
                $incomingConversions = PartialPurchaseNoteConversion::on('ledger')
                    ->where('new_purchase_note_id', $note->id)
                    ->where('status', 'active')
                    ->get();
                $transferRemarks = '';
                if ($incomingConversions->isNotEmpty()) {
                    $sourceNoteIds = $incomingConversions->pluck('source_purchase_note_id')->unique();
                    $sourceNotes = PurchaseNote::whereIn('id', $sourceNoteIds)->pluck('purchase_note_number', 'id');
                    $remarksParts = [];
                    foreach ($incomingConversions as $conv) {
                        $sourceNoteNumber = $sourceNotes[$conv->source_purchase_note_id] ?? 'N/A';
                        $sourceItem = \App\Models\PurchaseNoteItem::find($conv->source_purchase_note_item_id);
                        $productCode = $sourceItem ? trim($sourceItem->product_code) : '';
                        $remarksParts[] = "{$productCode} (Transferred from {$sourceNoteNumber})";
                    }
                    $transferRemarks = implode('; ', $remarksParts);
                }
            } catch (\Exception $e) {
                // Table may not exist yet
            }

            // Append reconstructed outgoing rows (transferred-out items deleted from source)
            if (!empty($outgoingTransferRows)) {
                $items = collect($items)->values();
                foreach ($outgoingTransferRows as $tRow) {
                    $items->push($tRow);
                }
            }

            return view('Admin.Purchase.Purchase-Note-Print', [
                'note' => $note,
                'items' => $items,
                'totalAmount' => $totalAmount,
                'transferRemarks' => $transferRemarks ?? '',
            ]);
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to generate print view: ' . $e->getMessage());
        }
}

    public function getForgottenPartialItems($supplierCode)
    {
        try {
            $supplier = Supplier::where('supplier_code', $supplierCode)->first();
            if (!$supplier) {
                return response()->json(['success' => false, 'message' => 'Supplier not found'], 404);
            }

            // Find items already transferred from this supplier
            $alreadyTransferredItemIds = PartialPurchaseNoteConversion::where('supplier_id', $supplier->id)
                ->where('status', 'active')
                ->pluck('source_purchase_note_item_id')
                ->toArray();

            // Find Partial PNs for this supplier
            $partialNotes = PurchaseNote::with(['items' => function ($query) {
                    $query->withoutForceCancelled();
                }])
                ->where('supplier_id', $supplier->id)
                ->where('status', 'Partial')
                ->orderBy('date', 'desc')
                ->get();

            $result = collect();

            foreach ($partialNotes as $note) {
                $validItems = collect();

                foreach ($note->items as $item) {
                    // Skip already transferred items.
                    if (!empty($alreadyTransferredItemIds) && in_array($item->id, $alreadyTransferredItemIds)) {
                        continue;
                    }

                    // purchase_note_items.quantity is already the authoritative
                    // unreceived balance. Historical Purchase Order quantities are
                    // transaction snapshots and summing them double-counts partial
                    // receipts (for example 10 ordered, then 5 + 5 received).
                    $remainingQty = max(0, (int) $item->quantity);

                    if ($remainingQty <= 0) continue;

                    $validItems->push([
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_code' => $item->product_code,
                        'part_number' => $item->part_number,
                        'description' => $item->description,
                        'unit_price' => (float) $item->unit_price,
                        'remaining_quantity' => $remainingQty,
                        'remaining_amount' => (float) ($remainingQty * $item->unit_price),
                    ]);
                }

                if ($validItems->isNotEmpty()) {
                    $result->push([
                        'id' => $note->id,
                        'purchase_note_number' => $note->purchase_note_number,
                        'date' => $note->date,
                        'status' => $note->status,
                        'items' => $validItems,
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'partial_notes' => $result,
                'has_items' => $result->count() > 0,
            ]);
        } catch (\Exception $e) {
            Log::error('getForgottenPartialItems Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch forgotten partial items: ' . $e->getMessage()
            ], 500);
        }
    }

    public function verifyPassword(Request $request)
    {
        $user = session('user');
        if (!$user) return response()->json(['success' => false, 'message' => 'Unauthorized', 'debug' => 'No session user'], 403);
        
        // Convert to object if it's an array
        if (is_array($user)) {
            $user = (object) $user;
        }
        
        $password = $request->query('password') ?? $request->input('password');
        if (!$password) return response()->json(['success' => false, 'message' => 'Password is required'], 422);
        
        // Debug: Log what we're looking for
        $loginId = $user->login_ID ?? $user->User_ID ?? 0;
        \Log::info('Password verification attempt', [
            'user_object' => $user,
            'login_id' => $loginId,
        ]);
        
        $login = \App\Models\Login::find($loginId);
        if (!$login) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'debug' => [
                    'login_id_used' => $loginId,
                    'user_keys' => is_object($user) ? array_keys((array)$user) : 'not_object'
                ]
            ], 404);
        }
        
        $login->makeVisible('Password');
        $passwordMatches = $password === $login->Password || Hash::check($password, $login->Password);
        
        if ($passwordMatches) {
            return response()->json(['success' => true]);
        }
        
        return response()->json(['success' => false, 'message' => 'Incorrect password']);
    }
}
