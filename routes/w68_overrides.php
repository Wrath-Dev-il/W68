<?php

use App\Http\Controllers\ShopeeAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| W68 HostForge route overrides
|--------------------------------------------------------------------------
|
| W68_ONLINE_PRODUCT_DIRECT_FETCH_20261007
| W68_ONLINE_PRODUCT_ZERO_UNCONVERTED_FAST_PATH_20261007
| W68_ONLINE_PRODUCT_REMOVE_ROUTE_FALLBACK_20261007
| W68_SHOPEE_REAUTHORIZATION_20261007
|
| The Online Product tab must not boot a nested Laravel request or resolve and
| execute another named route. On HostForge those paths produced 503/500
| responses. This handler now performs the read directly for Admin, Regular,
| and Special users.
|
*/

// Admin-only Shopee authorization management. The callback is intentionally
// under /admin and the controller verifies the active W68 Admin session.
Route::get('/admin/shopee/authorization', [ShopeeAuthController::class, 'status'])
    ->name('w68.shopee.authorization');
Route::get('/admin/shopee/authorization/status', [ShopeeAuthController::class, 'statusJson'])
    ->name('w68.shopee.status');
Route::get('/admin/shopee/authorize', [ShopeeAuthController::class, 'redirectToShopee'])
    ->name('w68.shopee.authorize');
Route::get('/admin/shopee/callback', [ShopeeAuthController::class, 'handleCallback'])
    ->name('w68.shopee.callback');
Route::post('/admin/shopee/refresh', [ShopeeAuthController::class, 'manualRefresh'])
    ->name('w68.shopee.refresh');


/*
|--------------------------------------------------------------------------
| Overdue invoice navbar notification (Admin + Regular)
|--------------------------------------------------------------------------
|
| W68_OVERDUE_INVOICE_PESO_NOTIFICATION_20261010
| Uses sales_orders.terms per Sales Order / invoice. A Sales Order generated
| from the current Proceed flow contains one invoice, so its own terms are the
| authoritative terms for that invoice.
|
*/
$w68OverdueInvoiceNotifications = function (Request $request) {
    try {
        $user = session('user');
        if (is_array($user)) {
            $user = (object) $user;
        }

        if (!$user || !in_array((int) ($user->account_type ?? 0), [1, 2, 3], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden',
                'overdue_invoices' => [],
                'count' => 0,
            ], 403);
        }

        $sales = DB::connection('sales');
        $schema = $sales->getSchemaBuilder();

        if (!$schema->hasTable('sales_orders') || !$schema->hasColumn('sales_orders', 'terms')) {
            return response()->json([
                'success' => true,
                'overdue_invoices' => [],
                'count' => 0,
            ]);
        }

        $dateColumn = $schema->hasColumn('sales_orders', 'order_date')
            ? 'order_date'
            : ($schema->hasColumn('sales_orders', 'date_issue') ? 'date_issue' : 'created_at');

        $select = [
            'id',
            'order_number',
            'customer_id',
            'customer_name',
            'invoice_numbers',
            'terms',
            'total_amount',
            'status',
            DB::raw($dateColumn . ' as invoice_date'),
        ];

        $orders = $sales->table('sales_orders')
            ->whereNotNull('invoice_numbers')
            ->whereRaw("TRIM(COALESCE(invoice_numbers, '')) <> ''")
            ->whereNotNull('terms')
            ->where('terms', '>=', 0)
            ->where('total_amount', '>', 0)
            ->whereRaw("UPPER(TRIM(COALESCE(status, ''))) NOT IN ('CANCELLED', 'CANCELED', 'VOID')")
            ->select($select)
            ->orderByDesc('id')
            ->limit(5000)
            ->get();

        if ($orders->isEmpty()) {
            return response()->json([
                'success' => true,
                'overdue_invoices' => [],
                'count' => 0,
            ]);
        }

        $orderIds = $orders->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        $paidByOrder = collect();
        try {
            $paidByOrder = DB::connection('accounting')
                ->table('process_payment_invoices as ppi')
                ->join('process_payments as pp', 'pp.id', '=', 'ppi.process_payment_id')
                ->where('ppi.source_type', 'sales_order')
                ->whereIn('ppi.source_id', $orderIds)
                ->whereRaw("UPPER(TRIM(COALESCE(pp.status, 'POSTED'))) NOT IN ('CANCELLED', 'CANCELED', 'VOID')")
                ->groupBy('ppi.source_id')
                ->selectRaw('ppi.source_id, SUM(COALESCE(ppi.paid_amount, 0)) as paid_total')
                ->pluck('paid_total', 'source_id');
        } catch (\Throwable $paymentError) {
            Log::warning('Overdue invoice notification could not read payment totals.', [
                'message' => $paymentError->getMessage(),
            ]);
        }

        // Sales returns reduce the collectible balance. This keeps the peso
        // notification aligned with Payments so a fully returned invoice is not
        // reported as overdue simply because it has no cash payment.
        $returnByInvoice = collect();
        try {
            if ($schema->hasTable('sales_returns') && $schema->hasTable('sales_return_items')) {
                $invoiceValues = $orders
                    ->pluck('invoice_numbers')
                    ->flatMap(function ($raw) {
                        $text = trim((string) $raw);
                        $decoded = json_decode($text, true);
                        if (is_array($decoded)) {
                            return $decoded;
                        }
                        return preg_split('/[,\n\r]+/', $text) ?: [];
                    })
                    ->map(fn ($value) => trim((string) $value))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if (!empty($invoiceValues)) {
                    $returnRows = $sales->table('sales_returns as sr')
                        ->join('sales_return_items as sri', 'sri.sales_return_id', '=', 'sr.id')
                        ->whereIn('sr.invoice_no', $invoiceValues)
                        ->whereRaw("UPPER(TRIM(COALESCE(sr.status, ''))) NOT IN ('CANCELLED', 'CANCELED', 'VOID')")
                        ->selectRaw('sr.invoice_no, SUM(COALESCE(sri.return_amount, sri.subtotal, 0)) as return_total')
                        ->groupBy('sr.invoice_no')
                        ->get();

                    foreach ($returnRows as $returnRow) {
                        $key = strtoupper(preg_replace('/\\s+/', '', trim((string) $returnRow->invoice_no)));
                        if ($key !== '') {
                            $returnByInvoice[$key] = (float) ($returnRow->return_total ?? 0);
                        }
                    }
                }
            }
        } catch (\Throwable $returnError) {
            Log::warning('Overdue invoice notification could not read sales returns.', [
                'message' => $returnError->getMessage(),
            ]);
        }

        $today = now('Asia/Manila')->startOfDay();
        $notifications = [];

        foreach ($orders as $order) {
            $invoiceDateRaw = trim((string) ($order->invoice_date ?? ''));
            if ($invoiceDateRaw === '') {
                continue;
            }

            try {
                $invoiceDate = \Illuminate\Support\Carbon::parse($invoiceDateRaw, 'Asia/Manila')->startOfDay();
            } catch (\Throwable $dateError) {
                continue;
            }

            $terms = max(0, (int) ($order->terms ?? 0));
            $dueDate = $invoiceDate->copy()->addDays($terms);

            if (!$today->greaterThan($dueDate)) {
                continue;
            }

            $paid = (float) ($paidByOrder[(int) $order->id] ?? 0);
            $invoiceAmount = max(0, (float) ($order->total_amount ?? 0));

            $invoiceLabel = trim((string) ($order->invoice_numbers ?? ''));
            $decoded = json_decode($invoiceLabel, true);
            $invoiceParts = is_array($decoded)
                ? collect($decoded)->map(fn ($value) => trim((string) $value))->filter()->values()
                : collect(preg_split('/[,\n\r]+/', $invoiceLabel) ?: [])
                    ->map(fn ($value) => trim((string) $value))
                    ->filter()
                    ->values();

            $returned = (float) $invoiceParts->sum(function ($invoiceNo) use ($returnByInvoice) {
                $key = strtoupper(preg_replace('/\\s+/', '', (string) $invoiceNo));
                return (float) ($returnByInvoice[$key] ?? 0);
            });

            $balance = max(0, round($invoiceAmount - $returned - $paid, 2));

            if ($balance <= 0.004) {
                continue;
            }

            if ($invoiceParts->isNotEmpty()) {
                $invoiceLabel = $invoiceParts->implode(', ');
            }
            if ($invoiceLabel === '') {
                $invoiceLabel = '---';
            }

            $notifications[] = [
                'sales_order_id' => (int) $order->id,
                'order_number' => (string) ($order->order_number ?? ''),
                'invoice_no' => $invoiceLabel,
                'customer_id' => (int) ($order->customer_id ?? 0),
                'customer_name' => (string) ($order->customer_name ?? '---'),
                'invoice_date' => $invoiceDate->format('Y-m-d'),
                'terms_days' => $terms,
                'due_date' => $dueDate->format('Y-m-d'),
                'overdue_days' => $dueDate->diffInDays($today),
                'invoice_amount' => round($invoiceAmount, 2),
                'returned_amount' => round($returned, 2),
                'paid_amount' => round($paid, 2),
                'balance_due' => $balance,
            ];
        }

        usort($notifications, function ($a, $b) {
            $days = ((int) ($b['overdue_days'] ?? 0)) <=> ((int) ($a['overdue_days'] ?? 0));
            if ($days !== 0) {
                return $days;
            }

            return strcmp((string) ($a['due_date'] ?? ''), (string) ($b['due_date'] ?? ''));
        });

        return response()->json([
            'success' => true,
            'overdue_invoices' => $notifications,
            'count' => count($notifications),
            'generated_at' => now('Asia/Manila')->toDateTimeString(),
        ]);
    } catch (\Throwable $e) {
        Log::error('Overdue invoice notification failed.', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Unable to load overdue invoices.',
            'overdue_invoices' => [],
            'count' => 0,
        ], 500);
    }
};

Route::get('/admin/notifications/overdue-invoices', $w68OverdueInvoiceNotifications)
    ->name('w68.admin.overdue-invoices');
Route::get('/regular/notifications/overdue-invoices', $w68OverdueInvoiceNotifications)
    ->name('w68.regular.overdue-invoices');
Route::get('/special/notifications/overdue-invoices', $w68OverdueInvoiceNotifications)
    ->name('w68.special.overdue-invoices');

$w68DirectOnlineProductFetch = function (Request $request) {
    try {
        $user = session('user');
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
                'message' => 'Unauthorized',
            ], 403);
        }

        if (is_array($user)) {
            $user = (object) $user;
        }

        if (!in_array((int) ($user->account_type ?? 0), [1, 2, 3], true)) {
            return response()->json([
                'success' => false,
                'error' => 'Forbidden',
                'message' => 'Forbidden',
            ], 403);
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(50, (int) $request->query('per_page', 20)));

        $convertedQuery = DB::connection('masterlist')
            ->table('online_products')
            ->where(function ($query) {
                $query->whereNotNull('product_id')
                    ->orWhere('is_converted', 1);
            });

        $convertedCount = (int) (clone $convertedQuery)->count();
        $convertedCodes = (clone $convertedQuery)
            ->whereNotNull('product_code')
            ->pluck('product_code')
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->filter()
            ->flip();

        $service = app(\App\Services\ShopeeService::class);
        if (!$service->isReady()) {
            return response()->json([
                'success' => false,
                'error' => 'Shopee API is not configured.',
                'message' => 'Shopee API is not configured.',
                'products' => [],
                'total' => 0,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => 1,
            ]);
        }

        // First request only one item so we can obtain Shopee total_count with
        // the smallest possible payload. If all Shopee items are already
        // converted, stop here and do not perform the normal 20-item fetch.
        $probe = $service->searchProducts(1, 1);
        if (($probe['success'] ?? false) !== true) {
            $message = (string) ($probe['error'] ?? 'Unable to reach Shopee.');
            Log::warning('W68 Online Product Shopee probe failed.', ['error' => $message]);

            return response()->json([
                'success' => false,
                'error' => $message,
                'message' => $message,
                'products' => [],
                'total' => 0,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => 1,
                'reauthorization_required' => (bool) ($probe['reauthorization_required'] ?? false),
                'reauthorize_url' => $probe['reauthorize_url'] ?? null,
                'w68_direct_fetch' => true,
            ]);
        }

        $catalogTotal = max(0, (int) ($probe['total'] ?? 0));
        if ($catalogTotal <= $convertedCount) {
            return response()->json([
                'success' => true,
                'source' => 'shopee',
                'products' => [],
                'total' => 0,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => 1,
                'next_offset' => '',
                'catalog_total' => $catalogTotal,
                'converted_total' => $convertedCount,
                'w68_fast_path' => true,
                'w68_direct_fetch' => true,
            ]);
        }

        // There are still possible unconverted items. Fetch the requested
        // Shopee page directly; never delegate to the Admin named route.
        $result = $service->searchProducts($page, $perPage);
        if (($result['success'] ?? false) !== true) {
            $message = (string) ($result['error'] ?? 'Unable to load Shopee products.');
            Log::warning('W68 Online Product direct fetch failed.', ['error' => $message]);

            return response()->json([
                'success' => false,
                'error' => $message,
                'message' => $message,
                'products' => [],
                'total' => max(0, $catalogTotal - $convertedCount),
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => max(1, (int) ceil(max(0, $catalogTotal - $convertedCount) / $perPage)),
                'reauthorization_required' => (bool) ($result['reauthorization_required'] ?? false),
                'reauthorize_url' => $result['reauthorize_url'] ?? null,
                'w68_direct_fetch' => true,
            ]);
        }

        $products = collect($result['products'] ?? [])
            ->map(function ($product) {
                if (!is_array($product)) {
                    return $product;
                }

                $itemId = trim((string) ($product['id'] ?? ''));
                if (!isset($product['product_code']) && $itemId !== '') {
                    $product['product_code'] = 'SHOPEE-' . $itemId;
                }
                if (!isset($product['category']) && isset($product['category_id'])) {
                    $product['category'] = 'Category #' . $product['category_id'];
                }
                $product['source'] = $product['source'] ?? 'shopee';

                return $product;
            })
            ->filter(function ($product) use ($convertedCodes) {
                if (!is_array($product)) {
                    return false;
                }

                $itemId = trim((string) ($product['id'] ?? ''));
                $code = strtoupper(trim((string) ($product['product_code'] ?? ($itemId !== '' ? 'SHOPEE-' . $itemId : ''))));

                return $code === '' || !$convertedCodes->has($code);
            })
            ->values()
            ->all();

        $remainingTotal = max(0, $catalogTotal - $convertedCount);

        return response()->json([
            'success' => true,
            'source' => 'shopee',
            'products' => $products,
            'total' => $remainingTotal,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($remainingTotal / $perPage)),
            'catalog_total' => $catalogTotal,
            'converted_total' => $convertedCount,
            'w68_direct_fetch' => true,
        ]);
    } catch (\Throwable $e) {
        Log::error('W68 Online Product direct fetch exception.', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        // Return JSON with HTTP 200 so the browser receives the actual error
        // payload instead of HostForge replacing it with a generic 500 page.
        return response()->json([
            'success' => false,
            'error' => 'Online Product fetch failed: ' . $e->getMessage(),
            'message' => 'Online Product fetch failed: ' . $e->getMessage(),
            'products' => [],
            'total' => 0,
            'per_page' => max(1, min(50, (int) $request->query('per_page', 20))),
            'current_page' => max(1, (int) $request->query('page', 1)),
            'last_page' => 1,
            'w68_direct_fetch' => true,
        ]);
    }
};

Route::get('/admin/masterlist/online-product-config/fetch-online', $w68DirectOnlineProductFetch);
Route::get('/special/master-list/online-product-config/fetch-online', $w68DirectOnlineProductFetch);
Route::get('/regular/master-list/online-product-config/fetch-online', $w68DirectOnlineProductFetch);


/*
|--------------------------------------------------------------------------
| Online Invoice Edit: ONLINE note/customer + invoice date (all sales users)
|--------------------------------------------------------------------------
|
| W68_ONLINE_INVOICE_EDIT_NOTE_DATE_20261010
| The selected Sales Note must belong to an online portal order or one of the
| dedicated online-buyer customers. The Online Report snapshot remains the
| invoice item source of truth; changing the note changes customer ownership.
| Reconciliation then updates Sales Orders and Product Ledger rows.
|
*/
$w68OnlineInvoiceUser = function () {
    $user = session('user');
    if (is_array($user)) $user = (object) $user;
    if (!$user || !in_array((int) ($user->account_type ?? 0), [1, 2, 3], true)) {
        return null;
    }
    return $user;
};

$w68OnlineNoteQuery = function () {
    $onlineCustomerNames = [
        'LAZADA ONLINE BUYERS',
        'SHOPEE ONLINE BUYERS',
        'SHOPPE ONLINE',
        'SHOPPEE ONLINE',
        'TIKTOK SHOP',
        'TIKTOK ONLINE BUYERS',
    ];

    return DB::connection('sales')
        ->table('sales_notes as sn')
        ->where('sn.sales_number', 'NOT LIKE', 'PURRTN%')
        ->where(function ($query) use ($onlineCustomerNames) {
            $query->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('w68_portal_orders as po')
                    ->whereColumn('po.sales_note_id', 'sn.id');
            })->orWhereIn(DB::raw('UPPER(TRIM(COALESCE(sn.customer_name, "")))'), $onlineCustomerNames);
        });
};

$w68OnlineInvoiceEdit = function ($id) use ($w68OnlineInvoiceUser, $w68OnlineNoteQuery) {
    if (!$w68OnlineInvoiceUser()) {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    try {
        $report = \App\Models\OnlineReport::on('sales')->find((int) $id);
        if (!$report) {
            return response()->json(['success' => false, 'message' => 'Online invoice not found.'], 404);
        }

        $decode = static function ($value): array {
            if (is_array($value)) return $value;
            if (is_object($value)) return (array) $value;
            if (!is_string($value) || trim($value) === '') return [];
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        };

        $noteIds = collect(explode(',', (string) $report->sales_note_ids))
            ->map(fn ($value) => (int) trim((string) $value))
            ->filter(fn ($value) => $value > 0)
            ->values();

        $notesById = DB::connection('sales')
            ->table('sales_notes')
            ->whereIn('id', $noteIds->all())
            ->get(['id', 'sales_number', 'customer_id', 'customer_name', 'order_date', 'net_total', 'status'])
            ->keyBy(fn ($row) => (int) $row->id);

        $invoiceNumbers = $decode($report->invoice_numbers);
        $addresses = $decode($report->addresses);
        $dateRanges = $decode($report->date_ranges);
        $prices = $decode($report->prices);
        $counterParts = $decode($report->counter_parts);
        $notesData = $decode($report->notes_data);

        $productIds = collect($notesData)
            ->flatMap(function ($noteData) {
                $items = $noteData['items'] ?? [];
                if (is_string($items)) $items = json_decode($items, true) ?: [];
                return collect(is_array($items) ? $items : [])->pluck('product_id');
            })
            ->map(fn ($value) => (int) $value)
            ->filter(fn ($value) => $value > 0)
            ->unique()
            ->values();

        $productsById = DB::connection('masterlist')
            ->table('products')
            ->whereIn('id', $productIds->all())
            ->get(['id', 'price_online', 'selling_price', 'unit'])
            ->keyBy(fn ($row) => (int) $row->id);

        $notesResponse = [];
        foreach ($noteIds as $index => $noteId) {
            $note = $notesById->get((int) $noteId);
            if (!$note) continue;

            $noteData = is_array($notesData[$index] ?? null) ? $notesData[$index] : [];
            $items = $noteData['items'] ?? [];
            if (is_string($items)) $items = json_decode($items, true) ?: [];
            if (!is_array($items)) $items = [];

            $items = collect($items)->values()->map(function ($item) use ($prices, $productsById, $index) {
                $item = is_array($item) ? $item : (array) $item;
                $productId = (int) ($item['product_id'] ?? 0);
                $product = $productsById->get($productId);
                $resolved = null;

                if ($productId > 0 && array_key_exists((string) $productId, $prices)) {
                    $resolved = (float) $prices[(string) $productId];
                }
                if ($resolved === null && !empty($item['id'])) {
                    $key = $index . '-' . $item['id'];
                    if (array_key_exists($key, $prices)) $resolved = (float) $prices[$key];
                }
                if ($resolved === null && $product && (float) ($product->price_online ?? 0) > 0) {
                    $resolved = (float) $product->price_online;
                }
                if ($resolved === null && (float) ($item['unit_price'] ?? 0) > 0) {
                    $resolved = (float) $item['unit_price'];
                }
                if ($resolved === null && $product && (float) ($product->selling_price ?? 0) > 0) {
                    $resolved = (float) $product->selling_price;
                }

                $item['resolved_unit_price'] = (float) ($resolved ?? 0);
                if ($product && trim((string) ($product->unit ?? '')) !== '') {
                    $item['oum'] = trim((string) $product->unit);
                }
                return $item;
            })->all();

            $notesResponse[] = [
                'id' => (int) $note->id,
                'sales_number' => (string) $note->sales_number,
                'customer_id' => (int) ($note->customer_id ?? 0),
                'customer_name' => (string) ($note->customer_name ?? ''),
                'order_date' => $note->order_date,
                'net_total' => (float) ($note->net_total ?? 0),
                'invoice_no' => (string) ($invoiceNumbers[(string) $index] ?? $invoiceNumbers[$index] ?? ''),
                'address' => (string) ($addresses[(string) $index] ?? $addresses[$index] ?? ''),
                'items' => $items,
            ];
        }

        $availableOnlineNotes = $w68OnlineNoteQuery()
            ->orderByDesc('sn.order_date')
            ->orderByDesc('sn.id')
            ->limit(5000)
            ->get([
                'sn.id',
                'sn.sales_number',
                'sn.customer_id',
                'sn.customer_name',
                'sn.order_date',
                'sn.net_total',
                'sn.status',
            ])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'sales_number' => (string) $row->sales_number,
                'customer_id' => (int) ($row->customer_id ?? 0),
                'customer_name' => (string) ($row->customer_name ?? ''),
                'order_date' => $row->order_date,
                'net_total' => (float) ($row->net_total ?? 0),
                'status' => (string) ($row->status ?? ''),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'report' => [
                'id' => (int) $report->id,
                'sales_note_ids' => (string) $report->sales_note_ids,
                'created_by' => $report->created_by,
                'status' => $report->status,
                'created_at' => $report->created_at,
                'invoice_date' => $report->created_at ? date('Y-m-d', strtotime((string) $report->created_at)) : now('Asia/Manila')->format('Y-m-d'),
            ],
            'notes' => $notesResponse,
            'available_online_notes' => $availableOnlineNotes,
            'date_ranges' => $dateRanges,
            'prices' => $prices,
            'counter_parts' => $counterParts,
        ]);
    } catch (\Throwable $e) {
        Log::error('Online Invoice edit data failed.', [
            'report_id' => (int) $id,
            'message' => $e->getMessage(),
        ]);
        return response()->json(['success' => false, 'message' => 'Unable to load the Online Invoice for editing.'], 500);
    }
};

$w68OnlineInvoiceUpdate = function (Request $request, $id) use ($w68OnlineInvoiceUser, $w68OnlineNoteQuery) {
    if (!$w68OnlineInvoiceUser()) {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    try {
        $report = \App\Models\OnlineReport::on('sales')->find((int) $id);
        if (!$report) {
            return response()->json(['success' => false, 'message' => 'Online invoice not found.'], 404);
        }

        $data = $request->input('data', []);
        $noteIds = collect($data['note_ids'] ?? [])
            ->map(fn ($value) => (int) $value)
            ->filter(fn ($value) => $value > 0)
            ->values();

        $currentCount = collect(explode(',', (string) $report->sales_note_ids))
            ->map(fn ($value) => (int) trim((string) $value))
            ->filter(fn ($value) => $value > 0)
            ->count();

        if ($noteIds->isEmpty() || $noteIds->count() !== $currentCount) {
            return response()->json([
                'success' => false,
                'message' => 'Every Online Invoice row must have one ONLINE Sales Note selected.',
            ], 422);
        }
        if ($noteIds->unique()->count() !== $noteIds->count()) {
            return response()->json([
                'success' => false,
                'message' => 'The same ONLINE Sales Note cannot be selected more than once in one Online Invoice.',
            ], 422);
        }

        $eligibleNotes = $w68OnlineNoteQuery()
            ->whereIn('sn.id', $noteIds->all())
            ->get([
                'sn.id',
                'sn.sales_number',
                'sn.customer_id',
                'sn.customer_name',
                'sn.order_date',
                'sn.net_total',
            ])
            ->keyBy(fn ($row) => (int) $row->id);

        if ($eligibleNotes->count() !== $noteIds->count()) {
            return response()->json([
                'success' => false,
                'message' => 'Only ONLINE Sales Notes can be assigned to an Online Invoice.',
            ], 422);
        }

        $invoiceDate = trim((string) ($data['invoice_date'] ?? ''));
        $dateObject = \DateTime::createFromFormat('!Y-m-d', $invoiceDate);
        if (!$dateObject || $dateObject->format('Y-m-d') !== $invoiceDate) {
            return response()->json(['success' => false, 'message' => 'Please select a valid Online Invoice date.'], 422);
        }

        $oldTimestamp = \Illuminate\Support\Carbon::parse($report->created_at ?: now('Asia/Manila'), 'Asia/Manila');
        $newTimestamp = \Illuminate\Support\Carbon::createFromFormat('Y-m-d H:i:s', $invoiceDate . ' ' . $oldTimestamp->format('H:i:s'), 'Asia/Manila');

        $notesData = $data['notes'] ?? [];
        if (!is_array($notesData)) $notesData = [];

        foreach ($noteIds as $index => $noteId) {
            $note = $eligibleNotes->get((int) $noteId);
            $row = is_array($notesData[$index] ?? null) ? $notesData[$index] : [];
            $row['id'] = (int) $note->id;
            $row['sales_number'] = (string) $note->sales_number;
            $row['customer_id'] = (int) ($note->customer_id ?? 0);
            $row['customer_name'] = (string) ($note->customer_name ?? '');
            $row['order_date'] = $note->order_date;
            $notesData[$index] = $row;
        }

        $report->sales_note_ids = $noteIds->implode(',');
        $report->date_ranges = $data['date_ranges'] ?? [];
        $report->prices = $data['prices'] ?? [];
        $report->counter_parts = $data['counter_parts'] ?? [];
        $report->invoice_numbers = $data['invoice_numbers'] ?? [];
        $report->addresses = $data['addresses'] ?? [];
        $report->notes_data = $notesData;
        $report->created_at = $newTimestamp->format('Y-m-d H:i:s');
        $report->save();

        $sync = \App\Services\OnlineReportProductLedgerSyncService::reconcileEditedReport((int) $report->id);

        Log::info('Online Invoice note/customer/date edit synchronized.', [
            'report_id' => (int) $report->id,
            'note_ids' => $noteIds->all(),
            'invoice_date' => $invoiceDate,
            'ledger_sync' => $sync,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Online Invoice note, customer and date updated and synchronized successfully.',
            'invoice_date' => $invoiceDate,
            'ledger_sync' => $sync,
        ]);
    } catch (\Throwable $e) {
        Log::error('Online Invoice note/customer/date update failed.', [
            'report_id' => (int) $id,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Unable to update the Online Invoice: ' . $e->getMessage(),
        ], 500);
    }
};

Route::get('/admin/sales/sales-order/report/online-edit/{id}', $w68OnlineInvoiceEdit)
    ->name('admin.sales-order.online-edit');
Route::put('/admin/sales/sales-order/report/online-update/{id}', $w68OnlineInvoiceUpdate)
    ->name('admin.sales-order.online-update');
Route::post('/admin/sales/sales-order/report/online-update/{id}', $w68OnlineInvoiceUpdate);

Route::get('/regular/sales/sales-order/online-edit/{id}', $w68OnlineInvoiceEdit)
    ->name('regular.sales-order.online-edit');
Route::put('/regular/sales/sales-order/online-update/{id}', $w68OnlineInvoiceUpdate)
    ->name('regular.sales-order.online-update');
Route::post('/regular/sales/sales-order/online-update/{id}', $w68OnlineInvoiceUpdate);

Route::get('/special/sales/sales-order/online-edit/{id}', $w68OnlineInvoiceEdit)
    ->name('special.sales-order.online-edit');
Route::put('/special/sales/sales-order/online-update/{id}', $w68OnlineInvoiceUpdate)
    ->name('special.sales-order.online-update');
Route::post('/special/sales/sales-order/online-update/{id}', $w68OnlineInvoiceUpdate);


/*
|--------------------------------------------------------------------------
| Product Master Gemini AI Search - Admin + Regular
|--------------------------------------------------------------------------
|
| W68_PRODUCT_MASTER_GEMINI_PART_SEARCH_20261010
| Uses the same GEMINI_API_KEY already configured for W68 Chat. Google Search
| grounding identifies the exact automotive part number and returns a short
| part description plus all verified vehicle applications.
|
*/
$w68ProductPartAiSearch = function (Request $request) {
    $user = session('user');
    if (is_array($user)) $user = (object) $user;

    if (!$user || !in_array((int) ($user->account_type ?? 0), [1, 2, 3], true)) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized.',
        ], 403);
    }

    $validated = $request->validate([
        'part_number' => 'required|string|max:120',
    ]);

    $partNumber = trim((string) $validated['part_number']);
    $cacheKey = 'w68_product_part_ai_v4_' . sha1(mb_strtoupper($partNumber));

    try {
        $result = \Illuminate\Support\Facades\Cache::get($cacheKey);

        if (!is_array($result) || !($result['success'] ?? false)) {
            $result = app(\App\Services\ProductPartAiService::class)->identify($partNumber);

            if (($result['success'] ?? false) === true) {
                \Illuminate\Support\Facades\Cache::put($cacheKey, $result, now()->addDays(14));
            }
        }

        $status = ($result['success'] ?? false) ? 200 : 422;
        return response()->json($result, $status);
    } catch (\Throwable $e) {
        Log::error('Product Master Gemini part-number search failed.', [
            'part_number' => $partNumber,
            'message' => $e->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'found' => false,
            'part_number' => $partNumber,
            'description' => '',
            'applications' => [],
            'confidence' => 'low',
            'message' => 'AI Search could not complete this part-number lookup.',
        ], 500);
    }
};

Route::post('/admin/masterlist/product/ai-part-search', $w68ProductPartAiSearch)
    ->name('admin.prod-master.ai-part-search');

Route::post('/regular/master-list/product-master/ai-part-search', $w68ProductPartAiSearch)
    ->name('regular.prod-master.ai-part-search');

Route::post('/special/master-list/product-master/ai-part-search', $w68ProductPartAiSearch)
    ->name('special.prod-master.ai-part-search');
