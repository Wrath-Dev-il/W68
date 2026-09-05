<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\ViberList;
use App\Models\ViberListItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PurchaseViberController extends Controller
{
    /**
     * Get the latest OUM (unit of measurement) from core4_ledger.product_ledgers for given product IDs.
     * Returns a map of product_id => oum value (or null if no record exists).
     */
    private function getLatestOumMap(array $productIds): array
    {
        if (empty($productIds)) return [];

        $rows = DB::connection('ledger')->table('product_ledgers')
            ->select('product_id', 'oum')
            ->whereIn('product_id', $productIds)
            ->orderBy('id', 'desc')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            // First occurrence per product_id is the latest (ordered by id desc)
            if (!array_key_exists($row->product_id, $map)) {
                $map[$row->product_id] = $row->oum ?? '';
            }
        }
        return $map;
    }

    /**
     * Return Viber list IDs containing at least one item that also exists under
     * a different supplier. Product ID is preferred; item code also covers
     * legacy/migrated rows where product_id may be missing.
     */
    /**
     * Return item keys that occur under at least two different suppliers.
     * Keys use product ID when available and item code for legacy/migrated rows.
     */
    private function getSharedViberItemKeys(): array
    {
        $rows = DB::connection('purchase')->table('viber_list_items as i')
            ->join('viber_lists as l', 'i.viber_list_id', '=', 'l.id')
            ->select('i.product_id', 'i.item_code', 'l.supplier_id', 'l.supplier_code')
            ->get();

        $supplierKeysByItem = [];

        foreach ($rows as $row) {
            $supplierKey = !empty($row->supplier_id)
                ? 'id:' . (int) $row->supplier_id
                : 'code:' . strtoupper(trim((string) ($row->supplier_code ?? '')));

            $itemKeys = [];
            if (!empty($row->product_id)) {
                $itemKeys[] = 'product:' . (int) $row->product_id;
            }
            $itemCode = strtoupper(trim((string) ($row->item_code ?? '')));
            if ($itemCode !== '') {
                $itemKeys[] = 'code:' . $itemCode;
            }

            foreach (array_unique($itemKeys) as $itemKey) {
                $supplierKeysByItem[$itemKey][$supplierKey] = true;
            }
        }

        $shared = [];
        foreach ($supplierKeysByItem as $itemKey => $supplierKeys) {
            if (count($supplierKeys) >= 2) {
                $shared[$itemKey] = true;
            }
        }

        return $shared;
    }

    private function isSharedViberItem($productId, $itemCode, array $sharedItemKeys): bool
    {
        if (!empty($productId) && isset($sharedItemKeys['product:' . (int) $productId])) {
            return true;
        }

        $normalizedCode = strtoupper(trim((string) ($itemCode ?? '')));
        return $normalizedCode !== '' && isset($sharedItemKeys['code:' . $normalizedCode]);
    }

    private function getSharedViberListIds(): array
    {
        $rows = DB::connection('purchase')->table('viber_list_items as i')
            ->join('viber_lists as l', 'i.viber_list_id', '=', 'l.id')
            ->select('i.viber_list_id', 'i.product_id', 'i.item_code', 'l.supplier_id', 'l.supplier_code')
            ->get();

        $supplierKeysByItem = [];
        $listIdsByItem = [];

        foreach ($rows as $row) {
            $supplierKey = !empty($row->supplier_id)
                ? 'id:' . (int) $row->supplier_id
                : 'code:' . strtoupper(trim((string) ($row->supplier_code ?? '')));

            $itemKeys = [];
            if (!empty($row->product_id)) {
                $itemKeys[] = 'product:' . (int) $row->product_id;
            }
            $itemCode = strtoupper(trim((string) ($row->item_code ?? '')));
            if ($itemCode !== '') {
                $itemKeys[] = 'code:' . $itemCode;
            }

            foreach (array_unique($itemKeys) as $itemKey) {
                $supplierKeysByItem[$itemKey][$supplierKey] = true;
                $listIdsByItem[$itemKey][(int) $row->viber_list_id] = true;
            }
        }

        $sharedListIds = [];
        foreach ($supplierKeysByItem as $itemKey => $supplierKeys) {
            if (count($supplierKeys) < 2) {
                continue;
            }
            foreach (array_keys($listIdsByItem[$itemKey] ?? []) as $listId) {
                $sharedListIds[(int) $listId] = true;
            }
        }

        return array_keys($sharedListIds);
    }

    private function notArrivedItemsQuery(?int $viberListId = null)
    {
        $query = DB::connection('purchase')->table('viber_list_items')
            ->join('viber_lists', 'viber_list_items.viber_list_id', '=', 'viber_lists.id')
            ->leftJoin('purchase_notes', 'viber_list_items.purchase_note_id', '=', 'purchase_notes.id')
            ->leftJoin('purchase_orders', DB::raw("REPLACE(purchase_notes.purchase_note_number, 'SN-', '')"), '=', 'purchase_orders.po_number')
            ->leftJoin('purchase_order_items', function ($join) {
                $join->on('purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
                     ->on('purchase_order_items.product_id', '=', 'viber_list_items.product_id');
            })
            ->whereNotNull('viber_list_items.purchase_note_id')
            ->whereNotNull('purchase_orders.id')
            // A force-closed Purchase Note means its unreceived remainder is cancelled.
            // Keep the Viber/PO rows intact for Arrived history, but do not expose any
            // remaining balance in Not Arrived (or its count/rollback candidates).
            ->whereRaw("LOWER(TRIM(COALESCE(purchase_notes.status, ''))) <> ?", ['closed'])
            // A Purchase Note can have several Purchase Order receiving snapshots.
            // For a product that has PO history, only the newest PO-item snapshot is
            // authoritative for its current Not Arrived balance.  Without this guard,
            // an older partial receipt can be read again and the rollback quantity can
            // be overstated.
            ->where(function ($snapshot) {
                $snapshot->where(function ($unchecked) {
                    $unchecked->whereNull('purchase_order_items.id')
                        // Treat a row as truly unchecked only when this product has
                        // never appeared on any PO snapshot for the Purchase Note.
                        // Another PO header that simply omitted the product must not
                        // create a false Not Arrived row.
                        ->whereNotExists(function ($exists) {
                            $exists->selectRaw('1')
                                ->from('purchase_order_items as poi_exists')
                                ->join('purchase_orders as po_exists', 'po_exists.id', '=', 'poi_exists.purchase_order_id')
                                ->whereColumn('poi_exists.product_id', 'viber_list_items.product_id')
                                ->whereRaw("po_exists.po_number = REPLACE(purchase_notes.purchase_note_number, 'SN-', '')");
                        });
                })->orWhereRaw("purchase_order_items.id = (
                    SELECT MAX(poi_latest.id)
                    FROM purchase_order_items poi_latest
                    INNER JOIN purchase_orders po_latest ON po_latest.id = poi_latest.purchase_order_id
                    WHERE poi_latest.product_id = viber_list_items.product_id
                      AND po_latest.po_number = REPLACE(purchase_notes.purchase_note_number, 'SN-', '')
                )");
            })
            ->where(function ($q) {
                // PO checkboxes are not stored as a boolean; unchecked rows are missing PO items under an existing PO header.
                $q->whereNull('purchase_order_items.id')
                  ->orWhereRaw('COALESCE(purchase_order_items.actual_quantity, 0) < COALESCE(purchase_order_items.quantity, 0)')
                  ->orWhereRaw('COALESCE(purchase_order_items.actual_quantity, 0) != COALESCE(purchase_order_items.quantity, 0)');
            });

        if ($viberListId !== null) {
            $query->where('viber_list_items.viber_list_id', $viberListId);
        }

        return $query;
    }

    public function index(Request $request)
    {
        $user = session('user');
        if (!$user) return redirect('/login');
        return view('Admin.Purchase.Purchase-Viber', compact('user'));
    }

    public function searchSuppliers(Request $request)
    {
        try {
            $page = max(1, (int) $request->get('page', 1));
            $perPage = max(1, min(100, (int) $request->get('perPage', 50)));
            $q = trim((string) $request->get('q', ''));

            $query = Supplier::query()->select([
                'id', 'supplier_code', 'name', 'contact_number', 'contact_person', 'address',
            ]);

            if ($q !== '') {
                $query->where(function ($w) use ($q) {
                    $w->where('supplier_code', 'LIKE', "%{$q}%")
                      ->orWhere('name', 'LIKE', "%{$q}%")
                      ->orWhere('contact_number', 'LIKE', "%{$q}%")
                      ->orWhere('contact_person', 'LIKE', "%{$q}%")
                      ->orWhere('address', 'LIKE', "%{$q}%");
                });
            }

            $suppliers = $query->orderBy('supplier_code', 'asc')->paginate($perPage, ['*'], 'page', $page);

            $data = collect($suppliers->items())->map(fn($s) => [
                'id' => $s->id,
                'supplier_code' => $s->supplier_code,
                'name' => $s->name,
                'contact_number' => $s->contact_number,
                'contact_person' => $s->contact_person,
                'address' => $s->address,
            ])->values()->all();

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
            Log::error('Viber searchSuppliers Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load suppliers.'], 500);
        }
    }

    public function searchProducts(Request $request)
    {
        try {
            $page = max(1, (int) $request->get('page', 1));
            $perPage = max(1, min(100, (int) $request->get('perPage', 50)));
            $q = trim((string) $request->get('q', ''));
            $itemCodeQ = trim((string) $request->get('item_code_q', ''));
            $partNumberQ = trim((string) $request->get('part_number_q', ''));
            $descriptionQ = trim((string) $request->get('description_q', ''));
            $applicationQ = trim((string) $request->get('application_q', ''));
            $brandQ = trim((string) $request->get('brand_q', ''));

            $query = Product::query()->select([
                'id', 'product_code', 'part_number', 'description', 'application', 'category', 'cost', 'unit',
            ]);

            if ($q !== '') {
                $query->where(function ($w) use ($q) {
                    $w->where('product_code', 'LIKE', "%{$q}%")
                      ->orWhere('part_number', 'LIKE', "%{$q}%")
                      ->orWhere('description', 'LIKE', "%{$q}%")
                      ->orWhere('application', 'LIKE', "%{$q}%");
                });
            }

            if ($itemCodeQ !== '') {
                $query->where('product_code', 'LIKE', "%{$itemCodeQ}%");
            }
            if ($partNumberQ !== '') {
                $query->where('part_number', 'LIKE', "%{$partNumberQ}%");
            }
            if ($descriptionQ !== '') {
                $query->where('description', 'LIKE', "%{$descriptionQ}%");
            }
            if ($applicationQ !== '') {
                $query->where('application', 'LIKE', "%{$applicationQ}%");
            }
            if ($brandQ !== '') {
                $query->where('category', 'LIKE', "%{$brandQ}%");
            }

            $products = $query->orderBy('product_code', 'asc')->paginate($perPage, ['*'], 'page', $page);

            $productIds = collect($products->items())->pluck('id')->toArray();
            $oumMap = $this->getLatestOumMap($productIds);

            $data = collect($products->items())->map(fn($p) => [
                'id' => $p->id,
                'product_code' => $p->product_code,
                'part_number' => $p->part_number,
                'description' => $p->description,
                'application' => $p->application ?? '',
                'brand' => $p->category ?? '',
                'unit' => $p->unit ?? '',
                'oum_unit' => $oumMap[$p->id] ?? '',
                'last_cost' => (float) ($p->cost ?? 0),
            ])->values()->all();

            return response()->json([
                'success' => true,
                'data' => $data,
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'from' => $products->firstItem() ?? 0,
                'to' => $products->lastItem() ?? 0,
            ]);
        } catch (\Throwable $e) {
            Log::error('Viber searchProducts Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load products.'], 500);
        }
    }

    public function list(Request $request)
    {
        try {
            $user = session('user');
            $status = $request->get('status', 'Entry');

            $sharedListIds = array_flip($this->getSharedViberListIds());

            $query = ViberList::orderBy('created_at', 'desc');
            if ($status !== 'all') {
                $query->where('status', $status);
            }

            $lists = $query->get()->map(fn($v) => [
                    'id' => $v->id,
                    'status' => $v->status,
                    'supplier_id' => $v->supplier_id,
                    'supplier_code' => $v->supplier_code,
                    'supplier_name' => $v->supplier_name,
                    'contact_no' => $v->contact_no ?? '',
                    'contact_person' => $v->contact_person ?? '',
                    'billing_address' => $v->billing_address ?? '',
                    // Entry tabs must count exactly what the Entry table can display.
                    // Historical rows already attached to a Purchase Note stay in the
                    // same supplier list for traceability, but they are no longer
                    // Purchase Entry rows and must not inflate the supplier badge.
                    'item_count' => strcasecmp(trim((string) $v->status), 'Entry') === 0
                        ? $v->items()->whereNull('purchase_note_id')->count()
                        : $v->items()->count(),
                    'arrived_count' => $v->items()->where('status', 'Arrived')->count(),
                    'partial_count' => $v->items()->where('status', 'Partial')->count(),
                    'created_at' => $v->created_at,
                    'has_shared_items' => isset($sharedListIds[(int) $v->id]),
                ]);

            return response()->json(['success' => true, 'data' => $lists]);
        } catch (\Throwable $e) {
            Log::error('Viber list Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load Viber lists.'], 500);
        }
    }

    public function storeSupplier(Request $request)
    {
        try {
            $user = session('user');
            $status = $request->input('status', 'Entry');
            $supplierId = $request->input('supplier_id');

            $validStatuses = ['Entry', 'To Shipped', 'Arrived', 'Not Arrived', 'Partial'];
            if (!in_array($status, $validStatuses)) {
                return response()->json(['success' => false, 'message' => 'Invalid status.'], 422);
            }

            $supplier = Supplier::find($supplierId);
            if (!$supplier) {
                return response()->json(['success' => false, 'message' => 'Supplier not found.'], 404);
            }

            $exists = ViberList::where('status', $status)
                ->where('supplier_id', $supplierId)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Supplier "' . $supplier->supplier_code . ' - ' . $supplier->name . '" is already added to ' . $status . '.',
                ], 422);
            }

            $viberList = ViberList::create([
                'status' => $status,
                'supplier_id' => $supplier->id,
                'supplier_code' => $supplier->supplier_code,
                'supplier_name' => $supplier->name,
                'contact_no' => $supplier->contact_number,
                'contact_person' => $supplier->contact_person,
                'billing_address' => $supplier->address,
                'created_by' => $user ? $user->login_id ?? $user->id ?? null : null,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $viberList->id,
                    'status' => $viberList->status,
                    'supplier_id' => $viberList->supplier_id,
                    'supplier_code' => $viberList->supplier_code,
                    'supplier_name' => $viberList->supplier_name,
                    'contact_no' => $viberList->contact_no ?? '',
                    'contact_person' => $viberList->contact_person ?? '',
                    'billing_address' => $viberList->billing_address ?? '',
                    'item_count' => 0,
                ],
                'message' => 'Supplier added successfully.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Viber storeSupplier Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to add supplier.'], 500);
        }
    }

    public function counts(Request $request)
    {
        try {
            $statuses = ['Entry', 'To Shipped', 'Arrived', 'Not Arrived', 'Partial'];
            $result = array_fill_keys(['entry', 'to_shipped', 'arrived', 'not_arrived', 'partial'], 0);

            $lists = ViberList::whereIn('status', $statuses)->get(['id', 'status']);
            $viberListIds = $lists->pluck('id');
            $statusMap = [];
            foreach ($lists as $l) {
                $statusMap[$l->id] = $l->status;
            }

            if ($viberListIds->isNotEmpty()) {
                $items = ViberListItem::whereIn('viber_list_id', $viberListIds)
                    ->selectRaw('id, viber_list_id, purchase_note_id')
                    ->get();

                // Get invoiced item IDs (linked to purchase_orders / purchase_order_items)
                $invoicedIds = DB::connection('purchase')->table('viber_list_items')
                    ->join('purchase_notes', 'viber_list_items.purchase_note_id', '=', 'purchase_notes.id')
                    ->join('purchase_orders', DB::raw("REPLACE(purchase_notes.purchase_note_number, 'SN-', '')"), '=', 'purchase_orders.po_number')
                    ->join('purchase_order_items', function ($join) {
                        $join->on('purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
                             ->on('purchase_order_items.product_id', '=', 'viber_list_items.product_id');
                    })
                    ->whereNotNull('viber_list_items.purchase_note_id')
                    ->pluck('viber_list_items.id')
                    ->toArray();

                $keyMap = [
                    'Entry' => 'entry',
                    'To Shipped' => 'to_shipped',
                    'Arrived' => 'arrived',
                    'Not Arrived' => 'not_arrived',
                    'Partial' => 'partial',
                ];

                foreach ($items as $item) {
                    $listStatus = $statusMap[$item->viber_list_id] ?? null;
                    $isInvoiced = in_array($item->id, $invoicedIds);
                    // Entry: items that are NOT yet shipped (no purchase_note_id) — can't be invoiced
                    if ($listStatus === 'Entry' && $item->purchase_note_id === null) {
                        $result['entry']++;
                    }
                    // To Shipped: items that ARE already shipped but NOT invoiced
                    if ($item->purchase_note_id !== null && !$isInvoiced) {
                        $result['to_shipped']++;
                    }
                    // Other statuses (Arrived, Not Arrived, Partial): count all items
                    if ($listStatus !== 'Entry' && $listStatus !== 'To Shipped' && isset($keyMap[$listStatus])) {
                        $result[$keyMap[$listStatus]]++;
                    }
                }

                // Override arrived count: only invoiced items
                $result['arrived'] = count($invoicedIds);

                // Keep the dashboard count aligned with the Not Arrived table and rollback candidates.
                $notArrivedCount = $this->notArrivedItemsQuery()
                    ->distinct()
                    ->count('viber_list_items.id');
                $result['not_arrived'] = $notArrivedCount;
            }

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            Log::error('Viber counts Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load counts.'], 500);
        }
    }

    public function items(Request $request, $viberListId)
    {
        try {
            $shipped = $request->get('shipped');
            $query = ViberListItem::where('viber_list_id', $viberListId);
            if ($shipped === '1') {
                $query->whereNotNull('purchase_note_id');
            } elseif ($shipped === '0') {
                $query->whereNull('purchase_note_id');
            }
            // Exclude already-invoiced items from Entry and To-Shipped views
            // Invoiced items should only appear in Arrived
            $query->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('purchase_notes')
                  ->join('purchase_orders', DB::raw("REPLACE(purchase_notes.purchase_note_number, 'SN-', '')"), '=', 'purchase_orders.po_number')
                  ->join('purchase_order_items', function ($j) {
                      $j->on('purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
                        ->on('purchase_order_items.product_id', '=', 'viber_list_items.product_id');
                  })
                  ->whereColumn('purchase_notes.id', 'viber_list_items.purchase_note_id');
            });
            $items = $query->orderBy('created_at', 'asc')->orderBy('id', 'asc')
                ->get();

            $productIds = $items->pluck('product_id')->toArray();
            $oumMap = $this->getLatestOumMap($productIds);
            $sharedItemKeys = $this->getSharedViberItemKeys();

            $data = $items->map(fn($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'item_code' => $item->item_code,
                    'part_no' => $item->part_no ?? '',
                    'description' => $item->description,
                    'application' => $item->application ?? '',
                    'brand' => $item->brand ?? '',
                    'unit' => $item->unit ?? '',
                    'oum_unit' => $oumMap[$item->product_id] ?? '',
                    'last_cost' => (float) $item->last_cost,
                    'new_cost' => (float) $item->new_cost,
                    'order_qty' => (float) $item->order_qty,
                    'ordered_date' => $item->ordered_date ?? '',
                    'remarks' => $item->remarks ?? '',
                    'purchase_note_id' => $item->purchase_note_id,
                    'shipped_at' => $item->shipped_at,
                    'currency_code' => $item->currency_code ?? 'PHP',
                    'is_rollback' => (bool) ($item->is_rollback ?? false),
                    'rollback_source_item_id' => $item->rollback_source_item_id,
                    'rollbacked_at' => $item->rollbacked_at,
                    'is_shared_item' => $this->isSharedViberItem($item->product_id, $item->item_code, $sharedItemKeys),
                ]);

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            Log::error('Viber items Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load items.'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $viberList = ViberList::find($id);
            if (!$viberList) {
                return response()->json(['success' => false, 'message' => 'Viber list not found.'], 404);
            }

            DB::connection('purchase')->transaction(function () use ($viberList) {
                ViberListItem::where('viber_list_id', $viberList->id)->delete();
                $viberList->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Supplier tab and all its items deleted successfully.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Viber destroy Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete supplier tab.'], 500);
        }
    }

    public function destroyItem($id)
    {
        try {
            $item = ViberListItem::find($id);
            if (!$item) {
                return response()->json(['success' => false, 'message' => 'Item not found.'], 404);
            }

            $viberListId = $item->viber_list_id;
            $item->delete();

            return response()->json([
                'success' => true,
                'message' => 'Item deleted successfully.',
                'viber_list_id' => $viberListId,
            ]);
        } catch (\Throwable $e) {
            Log::error('Viber destroyItem Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete item.'], 500);
        }
    }

    public function updateItem(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'new_cost' => 'nullable|numeric|min:0',
                'order_qty' => 'nullable|numeric|min:0',
                'unit' => 'nullable|string|max:50',
                'ordered_date' => 'nullable|date_format:Y-m-d',
                'remarks' => 'nullable|string|max:500',
            ]);

            $item = ViberListItem::find($id);
            if (!$item) {
                return response()->json(['success' => false, 'message' => 'Item not found.'], 404);
            }

            if (array_key_exists('new_cost', $validated)) {
                $item->new_cost = $validated['new_cost'];
            }
            if (array_key_exists('order_qty', $validated)) {
                $item->order_qty = $validated['order_qty'];
            }
            if (array_key_exists('unit', $validated)) {
                $item->unit = trim((string) ($validated['unit'] ?? ''));
            }
            if (array_key_exists('ordered_date', $validated)) {
                $item->ordered_date = $validated['ordered_date'] ?: null;
            }
            if (array_key_exists('remarks', $validated)) {
                $item->remarks = $validated['remarks'];
            }
            $item->save();

            $oumMap = $this->getLatestOumMap([$item->product_id]);

            return response()->json([
                'success' => true,
                'message' => 'Item updated successfully.',
                'data' => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'item_code' => $item->item_code,
                    'part_no' => $item->part_no ?? '',
                    'description' => $item->description,
                    'application' => $item->application ?? '',
                    'brand' => $item->brand ?? '',
                    'unit' => $item->unit ?? '',
                    'oum_unit' => $oumMap[$item->product_id] ?? '',
                    'last_cost' => (float) $item->last_cost,
                    'new_cost' => (float) $item->new_cost,
                    'order_qty' => (float) $item->order_qty,
                    'ordered_date' => $item->ordered_date ?? '',
                    'remarks' => $item->remarks ?? '',
                    'is_rollback' => (bool) ($item->is_rollback ?? false),
                    'rollback_source_item_id' => $item->rollback_source_item_id,
                    'rollbacked_at' => $item->rollbacked_at,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Viber updateItem Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update item.'], 500);
        }
    }

    public function transferItem(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'target_viber_list_id' => 'required|integer|min:1',
                'item_ids' => 'nullable|array|min:1|max:500',
                'item_ids.*' => 'integer|min:1',
            ]);

            $itemIds = collect($validated['item_ids'] ?? [$id])
                ->map(fn ($itemId) => (int) $itemId)
                ->filter(fn ($itemId) => $itemId > 0)
                ->unique()
                ->values()
                ->all();

            if (count($itemIds) === 0) {
                return response()->json(['success' => false, 'message' => 'Please select at least one item to transfer.'], 422);
            }

            $result = DB::connection('purchase')->transaction(function () use ($itemIds, $validated) {
                $items = ViberListItem::whereIn('id', $itemIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($items->count() !== count($itemIds)) {
                    return ['error' => 'One or more selected items no longer exist. Reload the page and try again.', 'status' => 404];
                }

                $linkedItem = $items->first(fn ($item) => $item->purchase_note_id !== null);
                if ($linkedItem) {
                    return [
                        'error' => 'Item ' . ($linkedItem->item_code ?: ('#' . $linkedItem->id)) . ' is already linked to a Purchase Note and cannot be transferred.',
                        'status' => 422,
                    ];
                }

                $sourceListIds = $items->pluck('viber_list_id')->map(fn ($listId) => (int) $listId)->unique()->values();
                if ($sourceListIds->count() !== 1) {
                    return ['error' => 'All selected items must come from the same Purchase Entry supplier.', 'status' => 422];
                }

                $sourceList = ViberList::where('id', $sourceListIds->first())->lockForUpdate()->first();
                if (!$sourceList) {
                    return ['error' => 'Source supplier tab not found.', 'status' => 404];
                }

                $targetListId = (int) $validated['target_viber_list_id'];
                if ($targetListId === (int) $sourceList->id) {
                    return ['error' => 'Please select a different supplier.', 'status' => 422];
                }

                $targetList = ViberList::where('id', $targetListId)->lockForUpdate()->first();
                if (!$targetList) {
                    return ['error' => 'Target supplier is no longer available on the Viber List page.', 'status' => 404];
                }

                // Transfer is limited to suppliers that already exist in Purchase Entry.
                // No supplier tab and no duplicate item row is created.
                if (strcasecmp(trim((string) $sourceList->status), 'Entry') !== 0
                    || strcasecmp(trim((string) $targetList->status), 'Entry') !== 0) {
                    return ['error' => 'Items can only be transferred between existing Purchase Entry suppliers.', 'status' => 422];
                }

                $fromSupplier = trim((string) $sourceList->supplier_code . ' - ' . (string) $sourceList->supplier_name, ' -');
                $toSupplier = trim((string) $targetList->supplier_code . ' - ' . (string) $targetList->supplier_name, ' -');

                // Move the original rows so IDs, costs, quantities, dates, remarks,
                // rollback markers and every other field stay intact.
                foreach ($items as $item) {
                    $item->viber_list_id = $targetList->id;
                    $item->save();
                }

                return [
                    'data' => [
                        'item_ids' => $items->pluck('id')->map(fn ($itemId) => (int) $itemId)->values()->all(),
                        'item_count' => $items->count(),
                        'item_codes' => $items->pluck('item_code')->filter()->values()->all(),
                        'from_viber_list_id' => (int) $sourceList->id,
                        'to_viber_list_id' => (int) $targetList->id,
                        'from_supplier' => $fromSupplier,
                        'to_supplier' => $toSupplier,
                    ],
                ];
            });

            if (isset($result['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'],
                ], $result['status'] ?? 422);
            }

            $count = (int) ($result['data']['item_count'] ?? 0);
            return response()->json([
                'success' => true,
                'message' => $count . ' item' . ($count === 1 ? '' : 's') . ' transferred successfully.',
                'data' => $result['data'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Viber transferItem Error: ' . $e->getMessage(), [
                'item_id' => $id,
                'item_ids' => $request->input('item_ids'),
                'target_viber_list_id' => $request->input('target_viber_list_id'),
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to transfer selected items.'], 500);
        }
    }

    public function storeItems(Request $request)
    {
        try {
            $viberListId = $request->input('viber_list_id');
            $items = $request->input('items', []);

            $viberList = ViberList::find($viberListId);
            if (!$viberList) {
                return response()->json(['success' => false, 'message' => 'Viber list not found.'], 404);
            }

            if (!is_array($items) || count($items) === 0) {
                return response()->json(['success' => false, 'message' => 'No items provided.'], 422);
            }

            $saved = 0;

            foreach ($items as $item) {
                $productId = $item['product_id'] ?? null;

                ViberListItem::create([
                    'viber_list_id' => $viberListId,
                    'product_id' => $productId ?? 0,
                    'item_code' => $item['item_code'] ?? $item['product_code'] ?? '',
                    'part_no' => $item['part_no'] ?? $item['part_number'] ?? '',
                    'description' => $item['description'] ?? '',
                    'application' => $item['application'] ?? '',
                    'brand' => $item['brand'] ?? '',
                    'unit' => $item['unit'] ?? '',
                    'last_cost' => $item['last_cost'] ?? 0,
                    'new_cost' => $item['new_cost'] ?? 0,
                    'order_qty' => $item['order_qty'] ?? 0,
                    'ordered_date' => $item['ordered_date'] ?? null,
                    'remarks' => $item['remarks'] ?? '',
                    'currency_code' => $item['currency_code'] ?? 'PHP',
                    'is_rollback' => false,
                    'rollback_source_item_id' => null,
                    'rollbacked_at' => null,
                ]);

                $saved++;
            }

            return response()->json([
                'success' => true,
                'message' => $saved . ' item(s) added.',
                'saved' => $saved,
            ]);
        } catch (\Throwable $e) {
            Log::error('Viber storeItems Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to save items.'], 500);
        }
    }

    public function prepareNote(Request $request)
    {
        try {
            $viberListId = $request->input('viber_list_id');
            // Preserve the exact click/selection order sent by the Ready to Ship UI.
            // A plain WHERE IN query does not guarantee that rows are returned in the
            // same order as the IDs in the request, so normalize the IDs first and
            // explicitly restore that order after fetching the records.
            $itemIds = array_values(array_unique(array_map(
                'intval',
                (array) $request->input('selected_item_ids', [])
            )));
            $currency = $request->input('currency', 'PHP');

            if (!$viberListId) {
                return response()->json(['success' => false, 'message' => 'Viber list ID is required.'], 422);
            }

            $viberList = ViberList::find($viberListId);
            if (!$viberList) {
                return response()->json(['success' => false, 'message' => 'Viber list not found.'], 404);
            }

            $selectionOrder = array_flip($itemIds);

            $items = ViberListItem::whereIn('id', $itemIds)
                ->where('viber_list_id', $viberListId)
                ->get()
                ->sortBy(fn ($item) => $selectionOrder[(int) $item->id] ?? PHP_INT_MAX)
                ->values();

            // Keep only IDs that actually belong to this supplier/list, in the same
            // order the user selected them. This is also the order stored in session
            // and later used by the Purchase Note page.
            $itemIds = $items->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

            $productIds = $items->pluck('product_id')->toArray();
            $oumMap = $this->getLatestOumMap($productIds);

            $items = $items->map(fn($item) => [
                    'viber_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'item_code' => $item->item_code,
                    'part_no' => $item->part_no ?? '',
                    'description' => $item->description,
                    'application' => $item->application ?? '',
                    'brand' => $item->brand ?? '',
                    'unit' => $item->unit ?? '',
                    'oum_unit' => $oumMap[$item->product_id] ?? '',
                    'last_cost' => (float) $item->last_cost,
                    'new_cost' => (float) $item->new_cost,
                    'order_qty' => (float) $item->order_qty,
                    'ordered_date' => $item->ordered_date ?? '',
                    'currency_code' => $currency,
                ]);

            session()->put('viber_prepare_note', [
                'viber_list_id' => (int) $viberListId,
                'supplier_code' => $viberList->supplier_code,
                'supplier_name' => $viberList->supplier_name,
                'viber_item_ids' => array_map('intval', $itemIds),
                'currency' => $currency,
                'items' => $items->toArray(),
            ]);

            ViberListItem::whereIn('id', $itemIds)
                ->where('viber_list_id', $viberListId)
                ->update(['currency_code' => $currency]);

            return response()->json([
                'success' => true,
                'message' => 'Data prepared.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Viber prepareNote Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to prepare note data.'], 500);
        }
    }

    public function invoicedItems(Request $request)
    {
        try {
            $items = DB::connection('purchase')->table('viber_list_items')
                ->join('viber_lists', 'viber_list_items.viber_list_id', '=', 'viber_lists.id')
                ->join('purchase_notes', 'viber_list_items.purchase_note_id', '=', 'purchase_notes.id')
                ->join('purchase_orders', DB::raw("REPLACE(purchase_notes.purchase_note_number, 'SN-', '')"), '=', 'purchase_orders.po_number')
                ->join('purchase_order_items', function ($join) {
                    $join->on('purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
                         ->on('purchase_order_items.product_id', '=', 'viber_list_items.product_id');
                })
                ->whereNotNull('viber_list_items.purchase_note_id')
                ->select(
                    'viber_list_items.id',
                    'viber_list_items.viber_list_id',
                    'viber_list_items.product_id',
                    'viber_list_items.item_code',
                    'viber_list_items.part_no',
                    'viber_list_items.description',
                    'viber_list_items.application',
                    'viber_list_items.unit',
                    'viber_list_items.last_cost',
                    'viber_list_items.new_cost',
                    'viber_list_items.order_qty',
                    'viber_list_items.ordered_date',
                    'viber_list_items.currency_code',
                    'viber_list_items.purchase_note_id',
                    'viber_lists.supplier_code',
                    'viber_lists.supplier_name',
                    'purchase_orders.id as po_id',
                    'purchase_orders.po_number',
                    'purchase_orders.date as po_date',
                    'purchase_orders.status as po_status',
                    'purchase_order_items.quantity as ordered_qty',
                    'purchase_order_items.actual_quantity as actual_qty'
                )
                ->orderBy('viber_lists.supplier_code')
                ->orderBy('viber_list_items.item_code')
                ->get();

            $grouped = [];
            $suppliers = [];

            // Collect all product_ids for OUM lookup
            $allProductIds = collect($items)->pluck('product_id')->toArray();
            $oumMap = $this->getLatestOumMap($allProductIds);
            $sharedListIds = array_flip($this->getSharedViberListIds());
            $sharedItemKeys = $this->getSharedViberItemKeys();

            foreach ($items as $item) {
                $item->oum_unit = $oumMap[$item->product_id] ?? '';
                $item->is_shared_item = $this->isSharedViberItem($item->product_id, $item->item_code, $sharedItemKeys);
                // Once a Purchase Order exists, its ordered quantity is the
                // authoritative quantity for Arrived.  The original Viber quantity
                // may legitimately be different because the user can change QTY in
                // Purchase Note / Purchase Order before receiving.
                $item->viber_order_qty = (float) ($item->order_qty ?? 0);
                if ($item->ordered_qty !== null) {
                    $item->order_qty = (float) $item->ordered_qty;
                }
                $item->is_partial_arrived = ($item->actual_qty !== null && $item->ordered_qty !== null && $item->actual_qty < $item->ordered_qty);
                $viberListId = $item->viber_list_id;
                if (!isset($grouped[$viberListId])) {
                    $grouped[$viberListId] = [];
                    $suppliers[] = [
                        'id' => $viberListId,
                        'supplier_code' => $item->supplier_code,
                        'supplier_name' => $item->supplier_name,
                        'item_count' => 0,
                        'has_shared_items' => isset($sharedListIds[(int) $viberListId]),
                    ];
                }
                $grouped[$viberListId][] = $item;
                // Update supplier item count
                foreach ($suppliers as &$s) {
                    if ($s['id'] === $viberListId) {
                        $s['item_count']++;
                        break;
                    }
                }
                unset($s);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'suppliers' => $suppliers,
                    'items' => $grouped,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Viber invoicedItems Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load invoiced items.', 'error' => $e->getMessage()], 500);
        }
    }

    public function notArrivedItems(Request $request)
    {
        try {
            $items = $this->notArrivedItemsQuery()
                ->select(
                    'viber_list_items.id',
                    'viber_list_items.viber_list_id',
                    'viber_list_items.product_id',
                    'viber_list_items.item_code',
                    'viber_list_items.part_no',
                    'viber_list_items.description',
                    'viber_list_items.application',
                    'viber_list_items.unit',
                    'viber_list_items.last_cost',
                    'viber_list_items.new_cost',
                    'viber_list_items.order_qty',
                    'viber_list_items.ordered_date',
                    'viber_list_items.currency_code',
                    'viber_list_items.purchase_note_id',
                    'viber_lists.supplier_code',
                    'viber_lists.supplier_name',
                    'purchase_orders.id as po_id',
                    'purchase_orders.po_number',
                    'purchase_orders.date as po_date',
                    'purchase_orders.status as po_status',
                    'purchase_order_items.id as po_item_id',
                    'purchase_order_items.quantity as ordered_qty',
                    'purchase_order_items.actual_quantity as actual_qty'
                )
                ->orderBy('viber_lists.supplier_code')
                ->orderBy('viber_list_items.item_code')
                ->get()
                ->unique('id')
                ->values();

            $grouped = [];
            $suppliers = [];

            $allProductIds = collect($items)->pluck('product_id')->toArray();
            $oumMap = $this->getLatestOumMap($allProductIds);
            $sharedListIds = array_flip($this->getSharedViberListIds());
            $sharedItemKeys = $this->getSharedViberItemKeys();

            foreach ($items as $item) {
                $item->oum_unit = $oumMap[$item->product_id] ?? '';
                $item->is_shared_item = $this->isSharedViberItem($item->product_id, $item->item_code, $sharedItemKeys);
                $item->is_unchecked = ($item->po_item_id === null);
                if ($item->is_unchecked) {
                    $item->ordered_qty = (float) ($item->order_qty ?? 0);
                    $item->actual_qty = 0;
                } else {
                    // Keep Not Arrived and Arrived consistent with the Purchase Order,
                    // not with the older Viber source quantity.
                    $item->viber_order_qty = (float) ($item->order_qty ?? 0);
                    $item->order_qty = (float) ($item->ordered_qty ?? $item->order_qty ?? 0);
                }
                $item->remaining_qty = max(0, (float) ($item->ordered_qty ?? 0) - (float) ($item->actual_qty ?? 0));
                $viberListId = $item->viber_list_id;
                if (!isset($grouped[$viberListId])) {
                    $grouped[$viberListId] = [];
                    $suppliers[] = [
                        'id' => $viberListId,
                        'supplier_code' => $item->supplier_code,
                        'supplier_name' => $item->supplier_name,
                        'item_count' => 0,
                        'has_shared_items' => isset($sharedListIds[(int) $viberListId]),
                    ];
                }
                $grouped[$viberListId][] = $item;
                foreach ($suppliers as &$s) {
                    if ($s['id'] === $viberListId) {
                        $s['item_count']++;
                        break;
                    }
                }
                unset($s);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'suppliers' => $suppliers,
                    'items' => $grouped,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Viber notArrivedItems Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load not-arrived items.', 'error' => $e->getMessage()], 500);
        }
    }

    public function shippedPrintItems($viberListId)
    {
        try {
            $viberList = ViberList::find($viberListId);
            if (!$viberList) {
                return response()->json(['success' => false, 'message' => 'Supplier not found.'], 404);
            }

            $items = ViberListItem::where('viber_list_id', $viberListId)
                ->whereNotNull('purchase_note_id')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                      ->from('purchase_notes')
                      ->join('purchase_orders', DB::raw("REPLACE(purchase_notes.purchase_note_number, 'SN-', '')"), '=', 'purchase_orders.po_number')
                      ->join('purchase_order_items', function ($j) {
                          $j->on('purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
                            ->on('purchase_order_items.product_id', '=', 'viber_list_items.product_id');
                      })
                      ->whereColumn('purchase_notes.id', 'viber_list_items.purchase_note_id');
                })
                ->orderBy('created_at', 'asc')->orderBy('id', 'asc')
                ->get();

            $productIds = $items->pluck('product_id')->toArray();
            $oumMap = $this->getLatestOumMap($productIds);

            $items = $items->map(fn($item) => [
                    'item_code' => $item->item_code,
                    'part_no' => $item->part_no ?? '',
                    'description' => $item->description,
                    'application' => $item->application ?? '',
                    'brand' => $item->brand ?? '',
                    'unit' => $item->unit ?? '',
                    'oum_unit' => $oumMap[$item->product_id] ?? '',
                    'last_cost' => (float) $item->last_cost,
                    'new_cost' => (float) $item->new_cost,
                    'order_qty' => (float) $item->order_qty,
                    'ordered_date' => $item->ordered_date ?? '',
                    'currency_code' => $item->currency_code ?? 'PHP',
                ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'supplier' => [
                        'supplier_code' => $viberList->supplier_code,
                        'supplier_name' => $viberList->supplier_name,
                    ],
                    'items' => $items,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Viber shippedPrintItems Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load items.'], 500);
        }
    }

    public function rollbackNotArrived(Request $request)
    {
        $validated = $request->validate([
            'viber_list_id' => 'required|integer|min:1',
        ]);

        $viberListId = (int) $validated['viber_list_id'];
        $viberList = ViberList::find($viberListId);

        if (!$viberList) {
            return response()->json([
                'success' => false,
                'message' => 'Viber list not found.',
            ], 404);
        }

        $purchaseConnection = DB::connection('purchase');

        try {
            $purchaseConnection->beginTransaction();

            // Lock the current rollback candidates so a double-click or concurrent
            // request cannot create duplicate returned quantities.
            $items = $this->notArrivedItemsQuery($viberListId)
                ->select(
                    'viber_list_items.id as vli_id',
                    'viber_list_items.viber_list_id',
                    'viber_list_items.product_id',
                    'viber_list_items.item_code',
                    'viber_list_items.part_no',
                    'viber_list_items.description',
                    'viber_list_items.application',
                    'viber_list_items.unit',
                    'viber_list_items.last_cost',
                    'viber_list_items.new_cost',
                    'viber_list_items.order_qty',
                    'viber_list_items.ordered_date',
                    'viber_list_items.remarks',
                    'viber_list_items.currency_code',
                    'viber_list_items.purchase_note_id',
                    'purchase_orders.id as po_id',
                    'purchase_order_items.id as poi_id',
                    'purchase_order_items.quantity as poi_quantity',
                    'purchase_order_items.actual_quantity as poi_actual_qty',
                    'purchase_order_items.unit_price as poi_unit_price',
                    'purchase_order_items.discount_percent as poi_discount_percent'
                )
                ->lockForUpdate()
                ->get()
                ->unique('vli_id')
                ->values();

            if ($items->isEmpty()) {
                $purchaseConnection->rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'No not-arrived items to rollback.',
                ], 422);
            }

            $affectedNoteIds = [];
            $affectedPoIdsByNote = [];
            $affectedPoIds = [];
            $rolledBackItems = [];
            $rolledBackCount = 0;
            $rolledBackQuantity = 0;

            foreach ($items as $item) {
                $isUnchecked = $item->poi_id === null;
                $orderQty = $isUnchecked
                    ? max(0, (float) $item->order_qty)
                    : max(0, (float) $item->poi_quantity);
                $actualQty = $isUnchecked
                    ? 0
                    : min($orderQty, max(0, (float) $item->poi_actual_qty));
                $remainingQty = max(0, $orderQty - $actualQty);

                if ($remainingQty <= 0) {
                    continue;
                }

                $noteId = (int) $item->purchase_note_id;
                if ($noteId > 0) {
                    $affectedNoteIds[$noteId] = true;
                    if ($item->po_id) {
                        $affectedPoIdsByNote[$noteId][(int) $item->po_id] = true;
                        $affectedPoIds[(int) $item->po_id] = true;
                    }
                }

                if ($isUnchecked) {
                    // The product was unchecked/removed from the PO. Return the
                    // original Viber row itself to Ready to Shipped.
                    $purchaseConnection->table('viber_list_items')
                        ->where('id', $item->vli_id)
                        ->whereNotNull('purchase_note_id')
                        ->update([
                            'purchase_note_id' => null,
                            'status' => null,
                            'shipped_at' => null,
                            'is_rollback' => true,
                            'rollback_source_item_id' => $item->vli_id,
                            'rollbacked_at' => now(),
                            'updated_at' => now(),
                        ]);
                } else {
                    // Keep the arrived portion attached to the original PO and
                    // create one new Ready to Shipped row for only the balance.
                    $normalizedActualQty = max(0, (int) round($actualQty));
                    $unitPrice = max(0, (float) ($item->poi_unit_price ?? 0));
                    $discountPercent = max(0, (float) ($item->poi_discount_percent ?? 0));
                    $discountAmount = round($normalizedActualQty * $unitPrice * ($discountPercent / 100), 2);
                    $normalizedSubtotal = round(($normalizedActualQty * $unitPrice) - $discountAmount, 2);

                    $purchaseConnection->table('purchase_order_items')
                        ->where('id', $item->poi_id)
                        ->update([
                            // Rollback cancels only the unreceived balance.  The PO
                            // history therefore becomes QTY = ACTUAL QTY (for example
                            // 20 / 10 becomes 10 / 10) while the received stock stays
                            // untouched.
                            'quantity' => $normalizedActualQty,
                            'actual_quantity' => $normalizedActualQty,
                            'discount_amount' => $discountAmount,
                            'subtotal' => $normalizedSubtotal,
                            'actual_subtotal' => $normalizedSubtotal,
                            'updated_at' => now(),
                        ]);

                    $purchaseConnection->table('viber_list_items')
                        ->where('id', $item->vli_id)
                        ->update([
                            'order_qty' => $normalizedActualQty,
                            'updated_at' => now(),
                        ]);

                    $purchaseConnection->table('viber_list_items')->insert([
                        'viber_list_id' => $item->viber_list_id,
                        'product_id' => $item->product_id,
                        'item_code' => $item->item_code,
                        'part_no' => $item->part_no ?? '',
                        'description' => $item->description,
                        'application' => $item->application ?? '',
                        'unit' => $item->unit ?? '',
                        'last_cost' => $item->last_cost,
                        'new_cost' => $item->new_cost,
                        'order_qty' => $remainingQty,
                        'ordered_date' => $item->ordered_date ?? now()->format('Y-m-d'),
                        'remarks' => $item->remarks ?? '',
                        'currency_code' => $item->currency_code ?? 'PHP',
                        'purchase_note_id' => null,
                        'status' => null,
                        'shipped_at' => null,
                        'is_rollback' => true,
                        'rollback_source_item_id' => $item->vli_id,
                        'rollbacked_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $rolledBackItems[] = $item->item_code;
                $rolledBackCount++;
                $rolledBackQuantity += $remainingQty;
            }

            if ($rolledBackCount === 0) {
                $purchaseConnection->rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'No remaining quantities were available to rollback.',
                ], 422);
            }

            // Recalculate every affected PO after trimming ordered QTY down to
            // the received Actual QTY.  This prevents a 20/10 row from becoming
            // 10/10 while its old 20-QTY subtotal is still left on the PO header.
            foreach (array_keys($affectedPoIds) as $poId) {
                $poHeader = $purchaseConnection->table('purchase_orders')
                    ->where('id', $poId)
                    ->lockForUpdate()
                    ->first();

                if (!$poHeader) {
                    continue;
                }

                $poItems = $purchaseConnection->table('purchase_order_items')
                    ->where('purchase_order_id', $poId)
                    ->get(['subtotal', 'actual_subtotal']);

                $orderedBeforeAdditional = round((float) $poItems->sum('subtotal'), 2);
                $actualBeforeAdditional = round((float) $poItems->sum('actual_subtotal'), 2);
                $additionalPercent = max(0, (float) ($poHeader->additional_discount_percent ?? 0));
                $additionalAmount = round($orderedBeforeAdditional * ($additionalPercent / 100), 2);
                $actualAdditionalAmount = round($actualBeforeAdditional * ($additionalPercent / 100), 2);

                $purchaseConnection->table('purchase_orders')
                    ->where('id', $poId)
                    ->update([
                        'total_amount' => round($orderedBeforeAdditional - $additionalAmount, 2),
                        'actual_total_amount' => round($actualBeforeAdditional - $actualAdditionalAmount, 2),
                        'additional_discount_amount' => $additionalAmount,
                        'updated_at' => now(),
                    ]);
            }

            // Close a Partial note/PO only after every Not Arrived candidate for
            // that note has been cleared by this rollback.
            foreach (array_keys($affectedNoteIds) as $noteId) {
                $remainingNotArrived = $this->notArrivedItemsQuery()
                    ->where('viber_list_items.purchase_note_id', $noteId)
                    ->distinct()
                    ->count('viber_list_items.id');

                if ($remainingNotArrived !== 0) {
                    continue;
                }

                // The rollback means the operator is cancelling the rest of
                // this Partial Purchase Note. Remove those positive remaining rows
                // from the active note so they cannot appear again as forgotten
                // partial items.  Newer installs keep an audit-friendly cancelled
                // marker; older schemas simply remove the remaining rows.
                $remainingNoteItems = $purchaseConnection->table('purchase_note_items')
                    ->where('purchase_note_id', $noteId)
                    ->where('quantity', '>', 0);

                if (Schema::connection('purchase')->hasColumn('purchase_note_items', 'is_remaining_cancelled')) {
                    $remainingRows = (clone $remainingNoteItems)->get(['id', 'quantity']);
                    foreach ($remainingRows as $remainingRow) {
                        $update = [
                            'quantity' => 0,
                            'total_price' => 0,
                            'is_remaining_cancelled' => 1,
                            'remaining_cancelled_at' => now(),
                            'updated_at' => now(),
                        ];
                        if (Schema::connection('purchase')->hasColumn('purchase_note_items', 'force_closed_remaining_qty')) {
                            $update['force_closed_remaining_qty'] = (int) $remainingRow->quantity;
                        }
                        $purchaseConnection->table('purchase_note_items')
                            ->where('id', $remainingRow->id)
                            ->update($update);
                    }
                } else {
                    $remainingNoteItems->delete();
                }

                $noteUpdate = [
                    'status' => 'Closed',
                    'total_amount' => 0,
                    'updated_at' => now(),
                ];
                if (Schema::connection('purchase')->hasColumn('purchase_notes', 'total_amount_converted')) {
                    $noteUpdate['total_amount_converted'] = 0;
                }

                $purchaseConnection->table('purchase_notes')
                    ->where('id', $noteId)
                    ->whereRaw("UPPER(TRIM(COALESCE(status, ''))) = 'PARTIAL'")
                    ->update($noteUpdate);

                $poIds = array_keys($affectedPoIdsByNote[$noteId] ?? []);
                if (!empty($poIds)) {
                    $purchaseConnection->table('purchase_orders')
                        ->whereIn('id', $poIds)
                        ->where('status', 'Partial')
                        ->update([
                            'status' => 'Closed',
                            'updated_at' => now(),
                        ]);
                }

                // Legacy fallback for orders that do not have purchase_note_id.
                $note = $purchaseConnection->table('purchase_notes')
                    ->where('id', $noteId)
                    ->first();

                if ($note) {
                    $noteNumber = (string) $note->purchase_note_number;
                    $rawNumber = ltrim(str_replace('SN-', '', $noteNumber), '0') ?: '0';

                    $purchaseConnection->table('purchase_orders')
                        ->where(function ($query) use ($noteNumber, $rawNumber) {
                            $query->where('po_number', $noteNumber)
                                ->orWhere('po_number', $rawNumber);
                        })
                        ->where('status', 'Partial')
                        ->update([
                            'status' => 'Closed',
                            'updated_at' => now(),
                        ]);
                }
            }

            $purchaseConnection->commit();

            return response()->json([
                'success' => true,
                'message' => 'Rollback completed. The unreceived quantities were returned to Purchase Entry and the completed Partial Purchase Note was closed.',
                'viber_list_id' => $viberListId,
                'rolled_back_count' => $rolledBackCount,
                'rolled_back_quantity' => $rolledBackQuantity,
                'rolled_back_items' => array_values(array_unique($rolledBackItems)),
            ]);
        } catch (\Throwable $e) {
            if ($purchaseConnection->transactionLevel() > 0) {
                $purchaseConnection->rollBack();
            }

            Log::error('Viber rollbackNotArrived Error', [
                'viber_list_id' => $viberListId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Rollback failed: ' . $e->getMessage(),
            ], 500);
        }
    }

}
