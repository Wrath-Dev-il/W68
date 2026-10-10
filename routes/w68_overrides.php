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

        if (!$user || !in_array((int) ($user->account_type ?? 0), [1, 2], true)) {
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
            $balance = max(0, round($invoiceAmount - $paid, 2));

            if ($balance <= 0.004) {
                continue;
            }

            $invoiceLabel = trim((string) ($order->invoice_numbers ?? ''));
            $decoded = json_decode($invoiceLabel, true);
            if (is_array($decoded)) {
                $invoiceLabel = collect($decoded)
                    ->map(fn ($value) => trim((string) $value))
                    ->filter()
                    ->implode(', ');
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
