<?php

use App\Http\Controllers\ShopeeAuthController;
use App\Http\Controllers\CustomerPaymentHistoryController;
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
| Customer Master payment status backend (Admin + Special + Regular)
|--------------------------------------------------------------------------
|
| W68_CUSTOMER_PAYMENT_HISTORY_STATUS_20261019
| These routes are loaded after routes/web.php and replace only the existing
| Customer Master payment-history endpoints. Special and Regular Customer
| Master routes already proxy to these named Admin endpoints.
|
| /admin/payments is intentionally untouched.
|
*/
Route::get(
    '/admin/masterlist/customer/payment-history/{id}',
    [CustomerPaymentHistoryController::class, 'index']
)->whereNumber('id')->name('admin.customer-master.payment-history');

Route::get(
    '/admin/masterlist/customer/payment-history/{id}/invoice/{salesOrderId}',
    [CustomerPaymentHistoryController::class, 'show']
)->whereNumber('id')->whereNumber('salesOrderId')->name('admin.customer-master.payment-history-detail');


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
    $cacheKey = 'w68_product_part_ai_v6_ph_' . sha1(mb_strtoupper($partNumber));

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


/*
|--------------------------------------------------------------------------
| Payments History bank/check columns (Admin + Regular + Special)
|--------------------------------------------------------------------------
|
| W68_PAYMENT_HISTORY_BANK_COLUMNS_20261019
| Loaded after routes/web.php so this replaces only the shared Admin History
| data endpoint. Regular and Special Payments history routes already proxy to
| admin.payments.history.data.
|
*/
$w68PaymentsHistoryDataWithBankColumns = function (Request $request) {
    $user = session('user');
    if (!$user) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    try {
        $page = max((int) $request->query('page', 1), 1);
        $perPage = 15;

        $search = trim((string) $request->query('search', ''));
        $paymentNo = trim((string) $request->query('payment_no', ''));
        $invoiceSearch = trim((string) $request->query('invoices', ''));
        $paymentDate = trim((string) $request->query('payment_date', ''));
        $amount = trim((string) $request->query('amount', ''));
        $bankName = trim((string) $request->query('bank_name', ''));
        $accountNo = trim((string) $request->query('account_no', ''));
        $checkNo = trim((string) $request->query('check_no', ''));

        $baseQuery = DB::connection('accounting')->table('process_payments');

        $applyCheckSearch = static function ($query, string $column, string $value) {
            if ($value === '') {
                return;
            }

            $query->whereExists(function ($checkSub) use ($column, $value) {
                $checkSub->select(DB::raw(1))
                    ->from('process_payment_checks as ppc_filter')
                    ->whereColumn('ppc_filter.process_payment_id', 'process_payments.id')
                    ->where('ppc_filter.' . $column, 'LIKE', '%' . $value . '%');
            });
        };

        if ($search !== '') {
            $like = "%{$search}%";
            $normalizedSearchAmount = str_replace([',', 'PHP', 'php', '₱', ' '], '', $search);

            $baseQuery->where(function ($q) use ($like, $normalizedSearchAmount) {
                $q->where('customer_name', 'LIKE', $like)
                    ->orWhere('payment_no', 'LIKE', $like)
                    ->orWhere('payment_date', 'LIKE', $like)
                    ->orWhereIn('id', function ($sub) use ($like) {
                        $sub->select('process_payment_id')
                            ->from('process_payment_invoices')
                            ->where('invoice_no', 'LIKE', $like);
                    })
                    ->orWhereExists(function ($checkSub) use ($like) {
                        $checkSub->select(DB::raw(1))
                            ->from('process_payment_checks as ppc_search_text')
                            ->whereColumn('ppc_search_text.process_payment_id', 'process_payments.id')
                            ->where(function ($checkFields) use ($like) {
                                $checkFields->where('ppc_search_text.bank_name', 'LIKE', $like)
                                    ->orWhere('ppc_search_text.account_number', 'LIKE', $like)
                                    ->orWhere('ppc_search_text.check_no', 'LIKE', $like);
                            });
                    });

                if ($normalizedSearchAmount !== '') {
                    $q->orWhereExists(function ($checkSub) use ($normalizedSearchAmount) {
                        $checkSub->select(DB::raw(1))
                            ->from('process_payment_checks as ppc_search_amount')
                            ->whereColumn('ppc_search_amount.process_payment_id', 'process_payments.id')
                            ->whereRaw(
                                'CAST(ppc_search_amount.credit_amount AS CHAR) LIKE ?',
                                ['%' . $normalizedSearchAmount . '%']
                            );
                    })->orWhere(function ($legacyAmount) use ($normalizedSearchAmount) {
                        $legacyAmount->whereNotExists(function ($checkExists) {
                            $checkExists->select(DB::raw(1))
                                ->from('process_payment_checks as ppc_existing')
                                ->whereColumn('ppc_existing.process_payment_id', 'process_payments.id');
                        })->whereRaw(
                            'CAST(total_paid AS CHAR) LIKE ?',
                            ['%' . $normalizedSearchAmount . '%']
                        );
                    });
                }
            });
        }

        if ($paymentNo !== '') {
            $baseQuery->where('payment_no', 'LIKE', '%' . $paymentNo . '%');
        }

        if ($invoiceSearch !== '') {
            $baseQuery->whereIn('id', function ($sub) use ($invoiceSearch) {
                $sub->select('process_payment_id')
                    ->from('process_payment_invoices')
                    ->where('invoice_no', 'LIKE', '%' . $invoiceSearch . '%');
            });
        }

        if ($paymentDate !== '') {
            $baseQuery->where('payment_date', 'LIKE', '%' . $paymentDate . '%');
        }

        if ($amount !== '') {
            $normalizedAmount = str_replace([',', 'PHP', 'php', '₱', ' '], '', $amount);

            $baseQuery->where(function ($amountQuery) use ($normalizedAmount) {
                $amountQuery->whereExists(function ($checkSub) use ($normalizedAmount) {
                    $checkSub->select(DB::raw(1))
                        ->from('process_payment_checks as ppc_amount')
                        ->whereColumn('ppc_amount.process_payment_id', 'process_payments.id')
                        ->whereRaw(
                            'CAST(ppc_amount.credit_amount AS CHAR) LIKE ?',
                            ['%' . $normalizedAmount . '%']
                        );
                })->orWhere(function ($legacyAmount) use ($normalizedAmount) {
                    $legacyAmount->whereNotExists(function ($checkExists) {
                        $checkExists->select(DB::raw(1))
                            ->from('process_payment_checks as ppc_existing')
                            ->whereColumn('ppc_existing.process_payment_id', 'process_payments.id');
                    })->whereRaw(
                        'CAST(total_paid AS CHAR) LIKE ?',
                        ['%' . $normalizedAmount . '%']
                    );
                });
            });
        }

        $applyCheckSearch($baseQuery, 'bank_name', $bankName);
        $applyCheckSearch($baseQuery, 'account_number', $accountNo);
        $applyCheckSearch($baseQuery, 'check_no', $checkNo);

        $total = (clone $baseQuery)->count();

        $payments = $baseQuery
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $paymentIds = $payments->pluck('id')->values();

        $invoiceGroups = $paymentIds->isEmpty()
            ? collect()
            : DB::connection('accounting')
                ->table('process_payment_invoices')
                ->whereIn('process_payment_id', $paymentIds)
                ->orderBy('id')
                ->get()
                ->groupBy('process_payment_id');

        $checkGroups = $paymentIds->isEmpty()
            ? collect()
            : DB::connection('accounting')
                ->table('process_payment_checks')
                ->whereIn('process_payment_id', $paymentIds)
                ->orderBy('id')
                ->get([
                    'id',
                    'process_payment_id',
                    'bank_name',
                    'account_number',
                    'check_no',
                    'credit_amount',
                ])
                ->groupBy('process_payment_id');

        $rows = $payments->map(function ($payment) use ($invoiceGroups, $checkGroups) {
            $invoiceRows = $invoiceGroups->get($payment->id, collect());
            $invoiceNumbers = $invoiceRows->pluck('invoice_no')->filter()->unique()->values();

            $totalPayment = round((float) ($payment->total_paid ?? 0), 2);
            $totalAmount = round(
                (float) $invoiceRows->sum(fn ($row) => (float) ($row->due_amount ?? 0)),
                2
            );

            $checks = $checkGroups->get((int) $payment->id, collect());

            $checkAmounts = $checks
                ->map(fn ($check) => round((float) ($check->credit_amount ?? 0), 2))
                ->values();

            if ($checkAmounts->isEmpty()) {
                $checkAmounts = collect([$totalPayment]);
            }

            $bankNames = $checks
                ->map(fn ($check) => trim((string) ($check->bank_name ?? '')))
                ->map(fn ($value) => $value !== '' ? $value : '---')
                ->values();

            $accountNumbers = $checks
                ->map(fn ($check) => trim((string) ($check->account_number ?? '')))
                ->map(fn ($value) => $value !== '' ? $value : '---')
                ->values();

            $checkNumbers = $checks
                ->map(fn ($check) => trim((string) ($check->check_no ?? '')))
                ->map(fn ($value) => $value !== '' ? $value : '---')
                ->values();

            if ($checks->isEmpty()) {
                $bankNames = collect(['---']);
                $accountNumbers = collect(['---']);
                $checkNumbers = collect(['---']);
            }

            $settlementStatus = abs($totalPayment - $totalAmount) <= 0.01
                ? 'Fully Paid'
                : 'Partial';

            return [
                'id' => (int) $payment->id,
                'payment_no' => (string) $payment->payment_no,
                'customer_name' => (string) $payment->customer_name,
                'invoice_paid' => $invoiceNumbers->implode(', ') ?: '---',
                'invoice_numbers' => $invoiceNumbers->all(),
                'invoice_count' => $invoiceRows->count(),
                'payment_date' => $payment->payment_date
                    ? \Carbon\Carbon::parse($payment->payment_date)->format('Y-m-d')
                    : '---',
                'total_paid' => $totalPayment,
                'check_amounts' => $checkAmounts->all(),
                'bank_names' => $bankNames->all(),
                'account_numbers' => $accountNumbers->all(),
                'check_nos' => $checkNumbers->all(),
                'total_amount' => $totalAmount,
                'payment_status' => $settlementStatus,
                'status' => (string) ($payment->status ?? 'Posted'),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'rows' => $rows,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => max((int) ceil($total / $perPage), 1),
        ]);
    } catch (\Throwable $e) {
        Log::error('Payments history bank-column data error: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 422);
    }
};

Route::get('/admin/payments/history/data', $w68PaymentsHistoryDataWithBankColumns)
    ->name('admin.payments.history.data');


/*
|--------------------------------------------------------------------------
| Product Transaction History: historical Online Price
|--------------------------------------------------------------------------
|
| W68_PRODUCT_LEDGER_HISTORICAL_ONLINE_PRICE_20261020
|
| Never copy the CURRENT masterlist.products.price_online into old Product
| Ledger rows. Online Price must come from the invoice/report transaction that
| created that movement. Local/non-online movements intentionally return null.
|
| Regular and Special Product Master ledger routes proxy to this named Admin
| endpoint, so this behavior is shared by all three roles.
|
*/
Route::get('/admin/masterlist/product/ledger/{id}', function ($id) {
    $user = session('user');
    if (!$user) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    try {
        // Confirm the product still exists, but do NOT use its current
        // price_online to decorate historical transactions.
        $product = \App\Models\Product::query()->findOrFail($id);

        $ledger = \App\Models\ProductLedger::query()
            ->where('product_id', $id)
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        if ($ledger->isEmpty()) {
            return response()->json([
                'success' => true,
                'ledger' => [],
                'total_in' => 0,
                'total_out' => 0,
            ]);
        }

        $ledgerIds = $ledger->pluck('id')->map(fn ($value) => (int) $value)->filter()->values();

        // Durable Online Report links are the strongest ownership evidence.
        $onlineLedgerIds = collect();
        try {
            if (\Illuminate\Support\Facades\Schema::connection('sales')->hasTable('online_report_product_ledger_links')) {
                $onlineLedgerIds = DB::connection('sales')
                    ->table('online_report_product_ledger_links')
                    ->whereIn('product_ledger_id', $ledgerIds)
                    ->pluck('product_ledger_id')
                    ->map(fn ($value) => (int) $value)
                    ->unique()
                    ->values();
            }
        } catch (\Throwable $ignored) {
            $onlineLedgerIds = collect();
        }
        $onlineLedgerLookup = array_fill_keys($onlineLedgerIds->all(), true);

        // Source item IDs point at the historical sales_order_items snapshot.
        // Its unit_price is frozen per invoice and does not change when Product
        // Master price_online is edited later.
        $sourceItemIds = $ledger
            ->pluck('source_item_id')
            ->map(fn ($value) => (int) $value)
            ->filter(fn ($value) => $value > 0)
            ->unique()
            ->values();

        $salesOrderItems = $sourceItemIds->isEmpty()
            ? collect()
            : DB::connection('sales')
                ->table('sales_order_items')
                ->whereIn('id', $sourceItemIds)
                ->get(['id', 'sales_order_id', 'unit_price'])
                ->keyBy(fn ($row) => (int) $row->id);

        // Some older online movements were initially tagged as sales_order.
        // Treat ONL-* Sales Orders as online invoice sources, but never classify
        // an ordinary local Sales Order as having an Online Price.
        $sourceSalesOrderIds = $ledger
            ->filter(fn ($entry) => strtolower(trim((string) ($entry->source_type ?? ''))) === 'sales_order')
            ->pluck('source_id')
            ->map(fn ($value) => (int) $value)
            ->filter(fn ($value) => $value > 0)
            ->unique()
            ->values();

        $onlineSalesOrderIds = $sourceSalesOrderIds->isEmpty()
            ? collect()
            : DB::connection('sales')
                ->table('sales_orders')
                ->whereIn('id', $sourceSalesOrderIds)
                ->where('order_number', 'LIKE', 'ONL-%')
                ->pluck('id')
                ->map(fn ($value) => (int) $value)
                ->values();

        $onlineSalesOrderLookup = array_fill_keys($onlineSalesOrderIds->all(), true);

        $ledger = $ledger->map(function ($entry) use (
            $onlineLedgerLookup,
            $salesOrderItems,
            $onlineSalesOrderLookup
        ) {
            $sourceType = strtolower(trim((string) ($entry->source_type ?? '')));
            $remarks = strtolower(trim((string) ($entry->remarks ?? '')));
            $ledgerId = (int) ($entry->id ?? 0);
            $sourceId = (int) ($entry->source_id ?? 0);
            $sourceItemId = (int) ($entry->source_item_id ?? 0);

            $isOnlineInvoiceMovement =
                $sourceType === 'online_report'
                || isset($onlineLedgerLookup[$ledgerId])
                || ($sourceType === 'sales_order' && isset($onlineSalesOrderLookup[$sourceId]))
                || str_contains($remarks, 'online report generation');

            // IMPORTANT: non-online/local invoices, purchases, returns and other
            // movements have no historical Online Price in this table.
            if (!$isOnlineInvoiceMovement) {
                $entry->price_online = null;
                return $entry;
            }

            $historicalPrice = null;

            if ($sourceItemId > 0) {
                $sourceItem = $salesOrderItems->get($sourceItemId);
                if ($sourceItem) {
                    $sourceItemPrice = (float) ($sourceItem->unit_price ?? 0);
                    if ($sourceItemPrice > 0) {
                        $historicalPrice = $sourceItemPrice;
                    }
                }
            }

            // Legacy Online Report rows can predate source_item_id links. Their
            // own ledger.price is still the frozen transaction price.
            if ($historicalPrice === null) {
                $ledgerPrice = (float) ($entry->price ?? 0);
                if ($ledgerPrice > 0) {
                    $historicalPrice = $ledgerPrice;
                }
            }

            // Null means this specific invoice/movement has no stored Online
            // Price. The UI intentionally renders nothing for it.
            $entry->price_online = $historicalPrice;

            return $entry;
        });

        return response()->json([
            'success' => true,
            'ledger' => $ledger,
            'total_in' => $ledger->sum('quantity_in'),
            'total_out' => $ledger->sum('quantity_out'),
        ]);
    } catch (\Throwable $e) {
        Log::error('Product ledger historical Online Price error: ' . $e->getMessage(), [
            'product_id' => $id,
        ]);

        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 422);
    }
})->name('admin.prod-master.ledger');


/*
|--------------------------------------------------------------------------
| Customer Master: show linked Pricelist registration
|--------------------------------------------------------------------------
|
| W68_CUSTOMER_MASTER_PRICELIST_ACCOUNT_VISIBILITY_20261020
|
| Pricelist registration does not create a duplicate customer. It creates a
| customer portal login and links it to an existing masterlist customer through
| mysql.customer_portal_accounts. Expose that linked account in Customer Master
| and allow username/email searching from the main table.
|
*/
Route::get('/admin/masterlist/customer/data', function (Request $request) {
    $user = session('user');
    if (!$user) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    $search = (array) $request->query('search', []);
    $page = max(1, (int) $request->query('page', 1));
    $perPageInput = $request->query('perPage', 50);

    $portalAccountSearchIds = static function (string $term) {
        $term = trim($term);
        if ($term === '') {
            return collect();
        }

        try {
            if (
                !\Illuminate\Support\Facades\Schema::connection('mysql')->hasTable('customer_portal_accounts')
                || !\Illuminate\Support\Facades\Schema::connection('mysql')->hasTable('logins')
            ) {
                return collect();
            }

            return DB::connection('mysql')
                ->table('customer_portal_accounts as cpa')
                ->join('logins as l', 'l.login_ID', '=', 'cpa.login_id')
                ->where(function ($q) use ($term) {
                    $q->where('l.User_ID', 'LIKE', '%' . $term . '%')
                        ->orWhere('l.Email', 'LIKE', '%' . $term . '%');
                })
                ->pluck('cpa.customer_id')
                ->map(fn ($value) => (int) $value)
                ->filter()
                ->unique()
                ->values();
        } catch (\Throwable $exception) {
            report($exception);
            return collect();
        }
    };

    $query = \App\Models\Customer::with(['type', 'bankAccount']);

    foreach (['name', 'contact_number', 'contact_person', 'address'] as $column) {
        $term = trim((string) ($search[$column] ?? ''));
        if ($term !== '') {
            $query->where($column, 'LIKE', '%' . $term . '%');
        }
    }

    $typeSearch = trim((string) ($search['type'] ?? ''));
    if ($typeSearch !== '') {
        $query->whereHas('type', function ($q) use ($typeSearch) {
            $q->where('name', 'LIKE', '%' . $typeSearch . '%');
        });
    }

    $portalSearch = trim((string) ($search['portal_account'] ?? ''));
    if ($portalSearch !== '') {
        $portalIds = $portalAccountSearchIds($portalSearch);
        if ($portalIds->isEmpty()) {
            $query->whereRaw('1 = 0');
        } else {
            $query->whereIn('id', $portalIds->all());
        }
    }

    $generalSearch = trim((string) ($search['general'] ?? ''));
    if ($generalSearch !== '') {
        $portalIds = $portalAccountSearchIds($generalSearch);

        $query->where(function ($q) use ($generalSearch, $portalIds) {
            $q->where('name', 'LIKE', '%' . $generalSearch . '%')
                ->orWhere('contact_person', 'LIKE', '%' . $generalSearch . '%')
                ->orWhere('address', 'LIKE', '%' . $generalSearch . '%');

            if ($portalIds->isNotEmpty()) {
                $q->orWhereIn('id', $portalIds->all());
            }
        });
    }

    $totalMatching = $query->count();

    $stats = [
        'total' => \App\Models\Customer::count(),
        'regular' => \App\Models\Customer::whereHas('type', fn ($q) => $q->where('name', 'Regular'))->count(),
        'b2b' => \App\Models\Customer::whereHas('type', fn ($q) => $q->where('name', 'B2B Customer'))->count(),
        'casual' => \App\Models\Customer::whereHas('type', fn ($q) => $q->where('name', 'Casual'))->count(),
    ];

    if ($perPageInput === 'all') {
        $customers = $query->orderByDesc('created_at')->orderByDesc('id')->get();
        $pagination = [
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => $totalMatching,
            'total' => $totalMatching,
            'from' => $totalMatching > 0 ? 1 : 0,
            'to' => $totalMatching,
        ];
    } else {
        $perPage = max(1, (int) $perPageInput);
        $lastPage = max(1, (int) ceil($totalMatching / $perPage));
        $page = max(1, min($page, $lastPage));
        $skip = ($page - 1) * $perPage;

        $customers = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->skip($skip)
            ->take($perPage)
            ->get();

        $pagination = [
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'total' => $totalMatching,
            'from' => $totalMatching > 0 ? $skip + 1 : 0,
            'to' => min($skip + $perPage, $totalMatching),
        ];
    }

    $customerIds = $customers
        ->pluck('id')
        ->map(fn ($value) => (int) $value)
        ->filter()
        ->values();

    $portalAuthorizations = collect();
    if (
        $customerIds->isNotEmpty()
        && \Illuminate\Support\Facades\Schema::connection('masterlist')->hasTable('customer_portal_authorizations')
    ) {
        $portalAuthorizations = DB::connection('masterlist')
            ->table('customer_portal_authorizations')
            ->whereIn('customer_id', $customerIds->all())
            ->get()
            ->keyBy(fn ($row) => (int) $row->customer_id);
    }

    $portalAccounts = collect();
    try {
        if (
            $customerIds->isNotEmpty()
            && \Illuminate\Support\Facades\Schema::connection('mysql')->hasTable('customer_portal_accounts')
            && \Illuminate\Support\Facades\Schema::connection('mysql')->hasTable('logins')
        ) {
            $portalAccounts = DB::connection('mysql')
                ->table('customer_portal_accounts as cpa')
                ->leftJoin('logins as l', 'l.login_ID', '=', 'cpa.login_id')
                ->whereIn('cpa.customer_id', $customerIds->all())
                ->select(
                    'cpa.customer_id',
                    'cpa.login_id',
                    'cpa.linked_at',
                    'l.User_ID as username',
                    'l.Email as email',
                    'l.account_type'
                )
                ->get()
                ->filter(fn ($row) => !$row->account_type || (int) $row->account_type === 5)
                ->keyBy(fn ($row) => (int) $row->customer_id);
        }
    } catch (\Throwable $exception) {
        report($exception);
        $portalAccounts = collect();
    }

    $portalBaseUrl = \App\Support\W68PricelistUrl::baseUrl($request);
    $now = now();

    $formatQrRemaining = static function (int $seconds): string {
        if ($seconds <= 0) return 'Expired';
        if ($seconds < 60) return '< 1 min';

        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($days > 0) {
            return $days . ' day' . ($days === 1 ? '' : 's')
                . ($hours > 0 ? ' ' . $hours . ' hr' : '');
        }

        if ($hours > 0) {
            return $hours . ' hr' . ($minutes > 0 ? ' ' . $minutes . ' min' : '');
        }

        return $minutes . ' min';
    };

    $portalSummaryFor = static function ($customerId) use (
        $portalAuthorizations,
        $portalBaseUrl,
        $now,
        $formatQrRemaining
    ): array {
        $authorization = $portalAuthorizations->get((int) $customerId);

        if (!$authorization) {
            return [
                'active' => false,
                'expired' => false,
                'url' => null,
                'validation_label' => 'No QR',
                'remaining_label' => 'N/A',
                'expires_at' => null,
            ];
        }

        $expiresAt = \Carbon\Carbon::parse($authorization->expires_at);
        $active = $expiresAt->isFuture();
        $url = null;
        $validationLabel = $active ? 'Active' : 'Expired';

        if ($active) {
            try {
                $token = \Illuminate\Support\Facades\Crypt::decryptString(
                    (string) $authorization->token_encrypted
                );
                $url = $portalBaseUrl . '/authorized-access/' . rawurlencode($token);
            } catch (\Throwable $exception) {
                report($exception);
                $validationLabel = 'Invalid QR';
                $active = false;
            }
        }

        $remainingSeconds = $active
            ? max(0, $now->diffInSeconds($expiresAt, false))
            : 0;

        return [
            'active' => $active,
            'expired' => !$active && $expiresAt->isPast(),
            'url' => $url,
            'validation_label' => $validationLabel,
            'remaining_label' => $active
                ? $formatQrRemaining((int) $remainingSeconds)
                : ($expiresAt->isPast() ? 'Expired' : 'N/A'),
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    };

    return response()->json([
        'customers' => $customers->map(function ($customer) use ($portalSummaryFor, $portalAccounts) {
            $account = $portalAccounts->get((int) $customer->id);

            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'contactNo' => $customer->contact_number,
                'contactPerson' => $customer->contact_person,
                'address' => $customer->address,
                'tin' => $customer->tin,
                'pricingRemarks' => $customer->pricing_remarks,
                'terms' => $customer->terms,
                'type' => $customer->type ? $customer->type->name : 'Regular',
                'portal' => $portalSummaryFor($customer->id),
                'portalAccount' => $account ? [
                    'registered' => true,
                    'login_id' => (int) $account->login_id,
                    'username' => (string) ($account->username ?? ''),
                    'email' => (string) ($account->email ?? ''),
                    'linked_at' => $account->linked_at
                        ? \Carbon\Carbon::parse($account->linked_at)->toIso8601String()
                        : null,
                ] : [
                    'registered' => false,
                    'login_id' => null,
                    'username' => '',
                    'email' => '',
                    'linked_at' => null,
                ],
                'bank' => [
                    'code' => $customer->bankAccount ? $customer->bankAccount->bank_code : '',
                    'accNo' => $customer->bankAccount ? $customer->bankAccount->account_number : '',
                    'name' => $customer->bankAccount ? $customer->bankAccount->bank_name : '',
                    'contactNo' => $customer->bankAccount ? $customer->bankAccount->bank_contact_number : '',
                    'contactPerson' => $customer->bankAccount ? $customer->bankAccount->bank_contact_person : '',
                    'address' => $customer->bankAccount ? $customer->bankAccount->bank_address : '',
                ],
            ];
        })->values(),
        'stats' => $stats,
        'pagination' => $pagination,
    ]);
})->name('admin.customer-master.data');


/*
|--------------------------------------------------------------------------
| Customer Master delete + linked Pricelist account cleanup
|--------------------------------------------------------------------------
|
| W68_CUSTOMER_MASTER_DELETE_LINKED_PRICELIST_ACCOUNT_20261020
|
| Deleting a Customer Master record must also remove the customer-facing
| Pricelist login stored in the system database. Cross-database FKs cannot
| cascade masterlist.customers -> system.customer_portal_accounts/logins, so
| the cleanup is explicit here.
|
| Regular and Special Customer Master delete routes already proxy to this
| named Admin route, therefore all three roles use this same backend.
|
| Historical Sales/Accounting records are intentionally preserved.
|
*/
Route::post('/admin/masterlist/customer/delete/{id}', function ($id) {
    $user = session('user');
    if (!$user) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    if (is_array($user)) {
        $user = (object) $user;
    }

    $customerId = (int) $id;
    if ($customerId <= 0) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid customer ID.',
        ], 422);
    }

    $masterStarted = false;
    $ledgerStarted = false;
    $systemStarted = false;

    try {
        if (!\Illuminate\Support\Facades\Schema::connection('ledger')->hasTable('archived_records')) {
            throw new \RuntimeException('Archive table is missing. Please run the archive migration first.');
        }

        DB::connection('masterlist')->beginTransaction();
        $masterStarted = true;

        DB::connection('ledger')->beginTransaction();
        $ledgerStarted = true;

        DB::connection('mysql')->beginTransaction();
        $systemStarted = true;

        $customer = \App\Models\Customer::query()
            ->lockForUpdate()
            ->findOrFail($customerId);

        $bank = \App\Models\CustomerBankAccount::query()
            ->where('customer_id', $customerId)
            ->first();

        $customerData = $customer->toArray();
        $bankData = $bank ? $bank->toArray() : null;

        $portalLink = null;
        $portalLogin = null;

        if (\Illuminate\Support\Facades\Schema::connection('mysql')->hasTable('customer_portal_accounts')) {
            $portalLink = DB::connection('mysql')
                ->table('customer_portal_accounts')
                ->where('customer_id', $customerId)
                ->lockForUpdate()
                ->first();

            if ($portalLink) {
                $portalLogin = DB::connection('mysql')
                    ->table('logins')
                    ->where('login_ID', (int) $portalLink->login_id)
                    ->lockForUpdate()
                    ->first();

                // Never let Customer Master deletion remove an Admin/Regular/
                // Special internal W68 login because of corrupted link data.
                if ($portalLogin && (int) ($portalLogin->account_type ?? 0) !== 5) {
                    throw new \RuntimeException(
                        'Delete stopped because the linked login is not a Pricelist customer account.'
                    );
                }
            }
        }

        $archivedData = [
            'customer' => $customerData,
            'bank_account' => $bankData,
            // Keep identity metadata for audit only. Password/OTP/profile BLOBs
            // are deliberately never copied into archived_records.
            'pricelist_account' => $portalLogin ? [
                'login_id' => (int) ($portalLogin->login_ID ?? 0),
                'username' => (string) ($portalLogin->User_ID ?? ''),
                'email' => (string) ($portalLogin->Email ?? ''),
                'account_type' => (int) ($portalLogin->account_type ?? 0),
            ] : null,
        ];

        $deletedBy = $user->name ?? $user->username ?? $user->User_ID ?? 'W68 User';
        $displayId = (string) $customerId;
        $dataName = trim((string) (($customer->name ?? '') !== '' ? $customer->name : 'Customer'));
        $actor = hatdogAuditActor($user);

        $existingArchive = DB::connection('ledger')
            ->table('archived_records')
            ->where('module', 'customer')
            ->whereNull('restored_at')
            ->where('source_connection', 'masterlist')
            ->where('source_table', 'customers')
            ->where('source_id', $customerId)
            ->first();

        if ($existingArchive) {
            throw new \RuntimeException('This customer is already archived.');
        }

        DB::connection('ledger')->table('archived_records')->insert([
            'module' => 'customer',
            'source_connection' => 'masterlist',
            'source_table' => 'customers',
            'source_id' => $customerId,
            'display_id' => $displayId,
            'data_name' => $dataName,
            'archived_data' => json_encode($archivedData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'deleted_by' => (string) $deletedBy,
            'deleted_at' => now(),
            'expires_at' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        hatdogWriteAuditTrail([
            'module' => 'customer',
            'action' => 'Deleted',
            'source_connection' => 'masterlist',
            'source_table' => 'customers',
            'source_id' => $customerId,
            'display_id' => $displayId,
            'record_name' => $dataName,
            'user_name' => $actor['name'],
            'user_identifier' => $actor['identifier'],
            'audit_data' => [
                'before' => $archivedData,
                'linked_pricelist_account_deleted' => (bool) $portalLogin,
            ],
        ]);

        $deletedLoginId = null;

        if ($portalLink) {
            $loginId = (int) ($portalLink->login_id ?? 0);

            if (
                $loginId > 0
                && \Illuminate\Support\Facades\Schema::connection('mysql')->hasTable('sessions')
                && \Illuminate\Support\Facades\Schema::connection('mysql')->hasColumn('sessions', 'user_id')
            ) {
                DB::connection('mysql')
                    ->table('sessions')
                    ->where('user_id', $loginId)
                    ->delete();
            }

            if (
                $portalLogin
                && !empty($portalLogin->Email)
                && \Illuminate\Support\Facades\Schema::connection('mysql')->hasTable('password_reset_tokens')
                && \Illuminate\Support\Facades\Schema::connection('mysql')->hasColumn('password_reset_tokens', 'email')
            ) {
                DB::connection('mysql')
                    ->table('password_reset_tokens')
                    ->where('email', (string) $portalLogin->Email)
                    ->delete();
            }

            DB::connection('mysql')
                ->table('customer_portal_accounts')
                ->where('id', (int) $portalLink->id)
                ->delete();

            if ($portalLogin && $loginId > 0) {
                DB::connection('mysql')
                    ->table('logins')
                    ->where('login_ID', $loginId)
                    ->where('account_type', 5)
                    ->delete();

                $deletedLoginId = $loginId;
            }
        }

        // masterlist child rows such as bank account, brand discounts and portal
        // authorization are configured with ON DELETE CASCADE.
        $customer->delete();

        // Verify the two identities are actually gone before any transaction
        // is committed.
        if (DB::connection('masterlist')->table('customers')->where('id', $customerId)->exists()) {
            throw new \RuntimeException('Customer deletion verification failed.');
        }

        if (
            $portalLink
            && DB::connection('mysql')
                ->table('customer_portal_accounts')
                ->where('customer_id', $customerId)
                ->exists()
        ) {
            throw new \RuntimeException('Pricelist account-link deletion verification failed.');
        }

        if (
            $deletedLoginId
            && DB::connection('mysql')
                ->table('logins')
                ->where('login_ID', $deletedLoginId)
                ->where('account_type', 5)
                ->exists()
        ) {
            throw new \RuntimeException('Pricelist login deletion verification failed.');
        }

        DB::connection('ledger')->commit();
        $ledgerStarted = false;

        DB::connection('mysql')->commit();
        $systemStarted = false;

        DB::connection('masterlist')->commit();
        $masterStarted = false;

        return response()->json([
            'success' => true,
            'message' => $deletedLoginId
                ? 'Customer and linked Pricelist account deleted successfully.'
                : 'Customer deleted successfully. No linked Pricelist account existed.',
            'customer_id' => $customerId,
            'pricelist_login_deleted' => (bool) $deletedLoginId,
        ]);
    } catch (\Throwable $e) {
        if ($systemStarted && DB::connection('mysql')->transactionLevel() > 0) {
            DB::connection('mysql')->rollBack();
        }
        if ($ledgerStarted && DB::connection('ledger')->transactionLevel() > 0) {
            DB::connection('ledger')->rollBack();
        }
        if ($masterStarted && DB::connection('masterlist')->transactionLevel() > 0) {
            DB::connection('masterlist')->rollBack();
        }

        Log::error('Customer delete with Pricelist account cleanup failed: ' . $e->getMessage(), [
            'customer_id' => $customerId,
        ]);

        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 422);
    }

})->whereNumber('id')->name('admin.customer-master.delete');


/*
|--------------------------------------------------------------------------
| Customer Master: show linked Pricelist registration (all W68 roles)
|--------------------------------------------------------------------------
|
| W68_CUSTOMER_MASTER_PRICELIST_ACCOUNT_SYNC_20261020
|
| Pricelist registration creates a system.logins account (account_type=5) and
| links it to the existing Masterlist customer through
| system.customer_portal_accounts. Customer Master already has a Pricelist
| Account column in its shared JS, but the original data endpoint never sent
| that account mapping. This endpoint adds it without creating duplicate
| Masterlist customer rows.
|
| Regular and Special Customer Master data routes proxy to this Admin route.
|
*/
Route::get('/admin/masterlist/customer/data', function (Request $request) {
    $user = session('user');
    if (!$user) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    $search = (array) $request->query('search', []);
    $page = max(1, (int) $request->query('page', 1));
    $perPageInput = $request->query('perPage', 50);

    $query = \App\Models\Customer::with(['type', 'bankAccount']);

    $searchableColumns = ['name', 'contact_number', 'contact_person', 'address'];
    foreach ($searchableColumns as $column) {
        $value = trim((string) ($search[$column] ?? ''));
        if ($value !== '') {
            $query->where($column, 'LIKE', '%' . $value . '%');
        }
    }

    $typeSearch = trim((string) ($search['type'] ?? ''));
    if ($typeSearch !== '') {
        $query->whereHas('type', function ($typeQuery) use ($typeSearch) {
            $typeQuery->where('name', 'LIKE', '%' . $typeSearch . '%');
        });
    }

    // W68 Customer Master "Pricelist Account" column search.
    $portalAccountSearch = trim((string) ($search['portal_account'] ?? ''));
    if ($portalAccountSearch !== '') {
        $matchingCustomerIds = collect();

        try {
            if (
                \Illuminate\Support\Facades\Schema::connection('mysql')->hasTable('logins')
                && \Illuminate\Support\Facades\Schema::connection('mysql')->hasTable('customer_portal_accounts')
            ) {
                $loginIds = DB::connection('mysql')
                    ->table('logins')
                    ->where('account_type', 5)
                    ->where(function ($loginQuery) use ($portalAccountSearch) {
                        $loginQuery
                            ->where('User_ID', 'LIKE', '%' . $portalAccountSearch . '%')
                            ->orWhere('Email', 'LIKE', '%' . $portalAccountSearch . '%');
                    })
                    ->pluck('login_ID')
                    ->map(fn ($value) => (int) $value)
                    ->filter()
                    ->values();

                if ($loginIds->isNotEmpty()) {
                    $matchingCustomerIds = DB::connection('mysql')
                        ->table('customer_portal_accounts')
                        ->whereIn('login_id', $loginIds)
                        ->pluck('customer_id')
                        ->map(fn ($value) => (int) $value)
                        ->filter()
                        ->unique()
                        ->values();
                }
            }
        } catch (\Throwable $exception) {
            report($exception);
            $matchingCustomerIds = collect();
        }

        if ($matchingCustomerIds->isEmpty()) {
            $query->whereRaw('1 = 0');
        } else {
            $query->whereIn('id', $matchingCustomerIds->all());
        }
    }

    $generalSearch = trim((string) ($search['general'] ?? ''));
    if ($generalSearch !== '') {
        $query->where(function ($generalQuery) use ($generalSearch) {
            $generalQuery
                ->where('name', 'LIKE', '%' . $generalSearch . '%')
                ->orWhere('contact_person', 'LIKE', '%' . $generalSearch . '%')
                ->orWhere('address', 'LIKE', '%' . $generalSearch . '%');
        });
    }

    $totalMatching = (clone $query)->count();

    $stats = [
        'total' => \App\Models\Customer::count(),
        'regular' => \App\Models\Customer::whereHas('type', fn ($q) => $q->where('name', 'Regular'))->count(),
        'b2b' => \App\Models\Customer::whereHas('type', fn ($q) => $q->where('name', 'B2B Customer'))->count(),
        'casual' => \App\Models\Customer::whereHas('type', fn ($q) => $q->where('name', 'Casual'))->count(),
    ];

    if ($perPageInput === 'all') {
        $customers = $query->orderByDesc('created_at')->get();
        $pagination = [
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => $totalMatching,
            'total' => $totalMatching,
            'from' => $totalMatching > 0 ? 1 : 0,
            'to' => $totalMatching,
        ];
    } else {
        $perPage = max(1, (int) $perPageInput);
        $lastPage = max(1, (int) ceil($totalMatching / $perPage));
        $page = max(1, min($page, $lastPage));
        $skip = ($page - 1) * $perPage;

        $customers = $query
            ->orderByDesc('created_at')
            ->skip($skip)
            ->take($perPage)
            ->get();

        $pagination = [
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'total' => $totalMatching,
            'from' => $totalMatching > 0 ? $skip + 1 : 0,
            'to' => min($skip + $perPage, $totalMatching),
        ];
    }

    $customerIds = $customers
        ->pluck('id')
        ->map(fn ($value) => (int) $value)
        ->filter()
        ->values();

    $portalAuthorizations = collect();
    if (
        $customerIds->isNotEmpty()
        && \Illuminate\Support\Facades\Schema::connection('masterlist')->hasTable('customer_portal_authorizations')
    ) {
        $portalAuthorizations = DB::connection('masterlist')
            ->table('customer_portal_authorizations')
            ->whereIn('customer_id', $customerIds)
            ->get()
            ->keyBy(fn ($row) => (int) $row->customer_id);
    }

    // Load linked Pricelist registrations from the shared system DB.
    $portalAccountsByCustomer = collect();
    try {
        if (
            $customerIds->isNotEmpty()
            && \Illuminate\Support\Facades\Schema::connection('mysql')->hasTable('customer_portal_accounts')
            && \Illuminate\Support\Facades\Schema::connection('mysql')->hasTable('logins')
        ) {
            $links = DB::connection('mysql')
                ->table('customer_portal_accounts')
                ->whereIn('customer_id', $customerIds)
                ->get();

            $loginIds = $links
                ->pluck('login_id')
                ->map(fn ($value) => (int) $value)
                ->filter()
                ->unique()
                ->values();

            $logins = $loginIds->isEmpty()
                ? collect()
                : DB::connection('mysql')
                    ->table('logins')
                    ->whereIn('login_ID', $loginIds)
                    ->where('account_type', 5)
                    ->get(['login_ID', 'User_ID', 'Email', 'account_type'])
                    ->keyBy(fn ($row) => (int) $row->login_ID);

            foreach ($links as $link) {
                $customerId = (int) ($link->customer_id ?? 0);
                $loginId = (int) ($link->login_id ?? 0);
                $login = $logins->get($loginId);

                if ($customerId < 1 || !$login) {
                    continue;
                }

                $portalAccountsByCustomer->put($customerId, [
                    'registered' => true,
                    'login_id' => $loginId,
                    'username' => (string) ($login->User_ID ?? ''),
                    'email' => (string) ($login->Email ?? ''),
                    'linked_at' => $link->linked_at
                        ? \Carbon\Carbon::parse($link->linked_at)->toIso8601String()
                        : null,
                ]);
            }
        }
    } catch (\Throwable $exception) {
        report($exception);
        $portalAccountsByCustomer = collect();
    }

    $portalBaseUrl = \App\Support\W68PricelistUrl::baseUrl($request);
    $now = now();

    $formatQrRemaining = static function (int $seconds): string {
        if ($seconds <= 0) return 'Expired';
        if ($seconds < 60) return '< 1 min';

        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($days > 0) {
            return $days . ' day' . ($days === 1 ? '' : 's')
                . ($hours > 0 ? ' ' . $hours . ' hr' : '');
        }

        if ($hours > 0) {
            return $hours . ' hr'
                . ($minutes > 0 ? ' ' . $minutes . ' min' : '');
        }

        return $minutes . ' min';
    };

    $portalSummaryFor = static function ($customerId) use (
        $portalAuthorizations,
        $portalBaseUrl,
        $now,
        $formatQrRemaining
    ): array {
        $authorization = $portalAuthorizations->get((int) $customerId);

        if (!$authorization) {
            return [
                'active' => false,
                'expired' => false,
                'url' => null,
                'validation_label' => 'No QR',
                'remaining_label' => 'N/A',
                'expires_at' => null,
            ];
        }

        $expiresAt = \Carbon\Carbon::parse($authorization->expires_at);
        $active = $expiresAt->isFuture();
        $url = null;
        $validationLabel = $active ? 'Active' : 'Expired';

        if ($active) {
            try {
                $token = \Illuminate\Support\Facades\Crypt::decryptString(
                    (string) $authorization->token_encrypted
                );
                $url = $portalBaseUrl . '/authorized-access/' . rawurlencode($token);
            } catch (\Throwable $exception) {
                report($exception);
                $active = false;
                $validationLabel = 'Invalid QR';
            }
        }

        $remainingSeconds = $active
            ? max(0, $now->diffInSeconds($expiresAt, false))
            : 0;

        return [
            'active' => $active,
            'expired' => !$active && $expiresAt->isPast(),
            'url' => $url,
            'validation_label' => $validationLabel,
            'remaining_label' => $active
                ? $formatQrRemaining((int) $remainingSeconds)
                : ($expiresAt->isPast() ? 'Expired' : 'N/A'),
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    };

    return response()->json([
        'customers' => $customers->map(function ($customer) use (
            $portalSummaryFor,
            $portalAccountsByCustomer
        ) {
            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'contactNo' => $customer->contact_number,
                'contactPerson' => $customer->contact_person,
                'address' => $customer->address,
                'tin' => $customer->tin,
                'pricingRemarks' => $customer->pricing_remarks,
                'terms' => $customer->terms,
                'type' => $customer->type ? $customer->type->name : 'Regular',
                'portal' => $portalSummaryFor($customer->id),
                'portalAccount' => $portalAccountsByCustomer->get(
                    (int) $customer->id,
                    [
                        'registered' => false,
                        'login_id' => null,
                        'username' => '',
                        'email' => '',
                        'linked_at' => null,
                    ]
                ),
                'bank' => [
                    'code' => $customer->bankAccount ? $customer->bankAccount->bank_code : '',
                    'accNo' => $customer->bankAccount ? $customer->bankAccount->account_number : '',
                    'name' => $customer->bankAccount ? $customer->bankAccount->bank_name : '',
                    'contactNo' => $customer->bankAccount ? $customer->bankAccount->bank_contact_number : '',
                    'contactPerson' => $customer->bankAccount ? $customer->bankAccount->bank_contact_person : '',
                    'address' => $customer->bankAccount ? $customer->bankAccount->bank_address : '',
                ],
            ];
        })->values(),
        'stats' => $stats,
        'pagination' => $pagination,
    ]);
})->name('admin.customer-master.data');
