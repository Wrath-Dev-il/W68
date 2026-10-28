<?php

use App\Http\Controllers\ShopeeAuthController;
use App\Http\Controllers\CustomerPaymentHistoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Warehouse direct logout
|--------------------------------------------------------------------------
|
| W68_WAREHOUSE_DIRECT_LOGOUT_20261026
| Keep Warehouse logout independent from activity logging and absolute URL
| generation. HostForge sits behind a proxy, so the response always redirects
| to the relative /login path after destroying the session and expiring the
| session cookie.
|
*/
Route::match(['GET', 'POST'], '/warehouse/logout-now', function (Request $request) {
    $session = $request->session();
    $sessionId = (string) $session->getId();
    $cookieName = (string) config('session.cookie', 'laravel_session');
    $cookiePath = (string) config('session.path', '/');
    $configuredDomain = config('session.domain');
    $host = (string) $request->getHost();

    /*
     * W68_WAREHOUSE_HARD_SESSION_LOGOUT_20261026
     * HostForge can expose multiple Laravel apps on sibling subdomains. If an
     * old parent-domain session cookie survives, a normal invalidate() can look
     * like a successful logout and then immediately authenticate again.
     *
     * Delete the backing database-session row first, then invalidate Laravel's
     * session and expire the cookie at every domain scope W68 may have used.
     */
    try {
        if (
            $sessionId !== ''
            && (string) config('session.driver') === 'database'
        ) {
            $sessionConnection = config('session.connection') ?: config('database.default');
            $sessionTable = (string) config('session.table', 'sessions');

            DB::connection($sessionConnection)
                ->table($sessionTable)
                ->where('id', $sessionId)
                ->delete();
        }
    } catch (\Throwable $e) {
        Log::warning('Warehouse hard logout session-row delete failed.', [
            'session_id' => $sessionId,
            'message' => $e->getMessage(),
        ]);
    }

    $session->forget('user');
    $session->flush();
    $session->invalidate();

    /*
     * StartSession runs after the controller response and normally writes a
     * fresh cookie even after invalidate(). The session Store already copied
     * the cookie name from config before this route executes, so changing only
     * config('session.cookie') is not enough. Rename the live Store itself.
     *
     * Keep $cookieName above unchanged so the real authenticated cookie is
     * explicitly expired below, while middleware can only persist the
     * throwaway __w68_logout_discard cookie.
     */
    if (method_exists($session, 'setName')) {
        $session->setName('__w68_logout_discard');
    }
    config(['session.cookie' => '__w68_logout_discard']);

    $response = redirect('/login?logged_out=1', 303)
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        ->header('Pragma', 'no-cache')
        ->header('Clear-Site-Data', '"cache", "cookies", "storage"');

    if ($cookieName !== '') {
        $domains = array_values(array_unique(array_filter([
            null,
            $configuredDomain,
            $host,
            '.' . $host,
            'hostforgeplatforms.com',
            '.hostforgeplatforms.com',
        ], static fn ($value) => $value === null || $value !== '')));

        foreach ($domains as $domain) {
            $response->withCookie(
                \Symfony\Component\HttpFoundation\Cookie::create(
                    $cookieName,
                    '',
                    1,
                    $cookiePath,
                    $domain,
                    true,
                    true,
                    false,
                    \Symfony\Component\HttpFoundation\Cookie::SAMESITE_LAX
                )
            );
        }
    }

    return $response;
})->name('warehouse.logout-now');

/*
|--------------------------------------------------------------------------
| Logged-out login landing guard
|--------------------------------------------------------------------------
|
| W68_LOGGED_OUT_LOGIN_GUARD_20261026
| After Warehouse hard logout, render the login page directly instead of
| allowing the legacy GET /login handler to redirect an old duplicate session
| cookie back to /dashboard.
|
*/
Route::get('/login', function (Request $request) {
    if ($request->boolean('logged_out')) {
        $request->session()->forget('user');
        return response()
            ->view('login')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    if ($request->session()->has('user')) {
        return redirect()->route('dashboard');
    }

    return view('login');
})->name('login');

/*
|--------------------------------------------------------------------------
| Unified logout route
|--------------------------------------------------------------------------
|
| W68_UNIFIED_LOGOUT_ROUTE_20261026
| The legacy routes register both GET and POST /logout under the same route
| name. Warehouse uses a GET navigation while other areas may use POST/AJAX.
| Handle both methods in one final override, clear the entire session, rotate
| the session ID/CSRF token, and return an appropriate response.
|
*/
Route::match(['GET', 'POST'], '/logout', function (Request $request) {
    $user = session('user');

    try {
        if ($user) {
            $userId = is_array($user)
                ? ($user['login_ID'] ?? null)
                : ($user->login_ID ?? null);
            $accountType = is_array($user)
                ? ($user['account_type'] ?? null)
                : ($user->account_type ?? null);

            if (
                $userId
                && \Illuminate\Support\Facades\Schema::hasTable('login_activity_logs')
                && \Illuminate\Support\Facades\Schema::hasColumn('login_activity_logs', 'logged_out_at')
            ) {
                DB::table('login_activity_logs')
                    ->where('login_id', $userId)
                    ->where('account_type', $accountType)
                    ->whereNull('logged_out_at')
                    ->orderBy('logged_in_at', 'desc')
                    ->limit(1)
                    ->update(['logged_out_at' => now()]);
            }
        }
    } catch (\Throwable $e) {
        Log::warning('Logout activity update failed.', [
            'message' => $e->getMessage(),
        ]);
    }

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    if ($request->expectsJson()) {
        return response()->json([
            'success' => true,
            'redirect' => route('login'),
        ]);
    }

    return redirect()->route('login');
})->name('logout');

/*
|--------------------------------------------------------------------------
| Account type 6 dashboard redirect fix
|--------------------------------------------------------------------------
|
| W68_ACCOUNT_TYPE_6_DASHBOARD_REDIRECT_20261026
| The normal GET /login route sends any authenticated session to the generic
| dashboard route. The legacy generic dashboard omitted account_type 6, which
| sent that same authenticated session back to /login and created:
| /login -> /dashboard -> /login -> ...
|
| Route overrides are loaded after routes/web.php, so this replaces only the
| generic dashboard handler while preserving the role-specific dashboards.
|
*/
Route::get('/dashboard', function () {
    $user = session('user');

    if (!$user) {
        return redirect()->route('login');
    }

    if (is_array($user)) {
        $user = (object) $user;
    }

    return match ((int) ($user->account_type ?? 0)) {
        1 => redirect()->route('admin.dashboard'),
        2 => redirect()->route('regular.dashboard'),
        3 => redirect()->route('special.dashboard'),
        4 => redirect()->route('developer.control-panel'),
        6 => redirect()->route('warehouse.prod-master'),
        default => (function () {
            session()->forget('user');
            return redirect('/login')->with('error', 'This account type is not allowed in the internal W68 system.');
        })(),
    };
})->name('dashboard');

/*
|--------------------------------------------------------------------------
| Warehouse Product Master - view only
|--------------------------------------------------------------------------
|
| W68_WAREHOUSE_PRODUCT_MASTER_READ_ONLY_20261026
| Account type 6 may view/search Product Master records only. Warehouse
| Inventory Adjustment, Junk and Item Convert access is disabled below.
|
*/
Route::get('/warehouse/masterlist/product', function () {
    $user = session('user');

    if (!$user) {
        return redirect()->route('login');
    }

    if (is_array($user)) {
        $user = (object) $user;
    }

    if ((int) ($user->account_type ?? 0) !== 6) {
        abort(403);
    }

    $profileUser = null;
    try {
        $profileUser = \App\Models\Login::query()
            ->where('login_ID', $user->login_ID ?? 0)
            ->first();
    } catch (\Throwable $ignored) {
    }

    $sidebarFullName = trim(collect([
        trim((string) ($profileUser->User_First_Name ?? $user->User_First_Name ?? '')),
        trim((string) ($profileUser->User_Middle_Name ?? $user->User_Middle_Name ?? '')),
        trim((string) ($profileUser->User_Last_Name ?? $user->User_Last_Name ?? '')),
    ])->filter()->implode(' '));

    if ($sidebarFullName === '') {
        $sidebarFullName = (string) ($profileUser->User_ID ?? $user->User_ID ?? 'Warehouse User');
    }

    $sidebarAvatarUrl = '';
    try {
        if (
            $profileUser
            && \Illuminate\Support\Facades\Schema::hasColumn('logins', 'profile_picture')
            && !empty($profileUser->profile_picture)
        ) {
            $mime = \Illuminate\Support\Facades\Schema::hasColumn('logins', 'profile_picture_mime')
                ? ((string) ($profileUser->profile_picture_mime ?: 'image/png'))
                : 'image/png';
            $sidebarAvatarUrl = 'data:' . $mime . ';base64,' . base64_encode($profileUser->profile_picture);
        }
    } catch (\Throwable $ignored) {
    }

    return view('Warehouse_User.master_list.Product_Master-list', [
        'user' => $user,
        'sidebarAvatarUrl' => $sidebarAvatarUrl,
        'sidebarFullName' => $sidebarFullName,
        'sidebarRoleLabel' => 'Warehouse User',
    ]);
})->name('warehouse.prod-master');

Route::get('/warehouse/masterlist/product/data', [
    \App\Http\Controllers\RegularProductMasterController::class,
    'data',
])->name('warehouse.prod-master.data');

// Product detail remains read-only. Return transaction rows through the
// existing historical ledger endpoint without exposing any write operation.
Route::get('/warehouse/masterlist/product/ledger/{id}', function ($id) {
    $user = session('user');
    if (is_array($user)) {
        $user = (object) $user;
    }

    if (!$user || (int) ($user->account_type ?? 0) !== 6) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    try {
        $ledger = \App\Models\ProductLedger::query()
            ->where('product_id', $id)
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'ledger' => $ledger,
            'total_in' => $ledger->sum('quantity_in'),
            'total_out' => $ledger->sum('quantity_out'),
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => 'Unable to load product history.',
        ], 422);
    }
})->whereNumber('id')->name('warehouse.prod-master.ledger');

Route::get('/warehouse/masterlist/product/active-notes/{id}', function ($id) {
    $user = session('user');
    if (is_array($user)) {
        $user = (object) $user;
    }

    if (!$user || (int) ($user->account_type ?? 0) !== 6) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    return response()->json([
        'success' => true,
        'rows' => [],
    ]);
})->whereNumber('id')->name('warehouse.prod-master.active-notes');

// Warehouse starts in Product Master now.
Route::get('/warehouse/dashboard', function () {
    $user = session('user');
    if (is_array($user)) {
        $user = (object) $user;
    }

    if (!$user) {
        return redirect()->route('login');
    }

    if ((int) ($user->account_type ?? 0) !== 6) {
        abort(403);
    }

    return redirect()->route('warehouse.prod-master');
})->name('warehouse.dashboard');

// Old Warehouse inventory pages are no longer part of the Warehouse role.
$warehouseReadOnlyRedirect = fn () => redirect()->route('warehouse.prod-master');
$warehouseReadOnlyDenied = fn () => response()->json([
    'success' => false,
    'error' => 'Warehouse access is view-only. Inventory management is disabled.',
], 403);

Route::get('/warehouse/inventory/adjustment', $warehouseReadOnlyRedirect);
Route::match(['GET', 'POST'], '/warehouse/inventory/adjustment/data', $warehouseReadOnlyDenied);
Route::match(['GET', 'POST'], '/warehouse/inventory/adjustment/save', $warehouseReadOnlyDenied);

Route::get('/warehouse/inventory/sales-junk', $warehouseReadOnlyRedirect);
Route::match(['GET', 'POST'], '/warehouse/inventory/sales-junk/data', $warehouseReadOnlyDenied);
Route::match(['GET', 'POST'], '/warehouse/inventory/sales-junk/restore', $warehouseReadOnlyDenied);

Route::get('/warehouse/inventory/item-convert', $warehouseReadOnlyRedirect);
Route::get('/warehouse/inventory/item-convert/data', $warehouseReadOnlyDenied);
Route::post('/warehouse/inventory/item-convert/save', $warehouseReadOnlyDenied);
Route::get('/warehouse/inventory/item-convert/targets', $warehouseReadOnlyDenied);

/*
|--------------------------------------------------------------------------
| Admin USM Warehouse account type
|--------------------------------------------------------------------------
|
| W68_USM_WAREHOUSE_ACCOUNT_TYPE_6_20261026
| Warehouse is account_type = 6. Account type 5 remains reserved for
| Pricelist/customer portal accounts.
|
*/
Route::post('/admin/usm/create', function (Request $request) {
    $user = session('user');

    if (is_array($user)) {
        $user = (object) $user;
    }

    if (!$user || (int) ($user->account_type ?? 0) !== 1) {
        return response()->json(['error' => 'Unauthorized Access'], 403);
    }

    try {
        $validated = $request->validate([
            'User_ID' => 'required|unique:logins,User_ID',
            'User_First_Name' => 'required|string|max:100',
            'User_Middle_Name' => 'nullable|string|max:100',
            'User_Last_Name' => 'required|string|max:100',
            'Password' => 'required|string|min:4',
            'account_type' => 'required|in:admin,employee,employee_with_pricelist,worker,warehouse',
            'Gender' => 'required|string|in:Male,Female,Other',
        ]);

        $accountTypeMap = [
            'admin' => 1,
            'employee_with_pricelist' => 2,
            'employee' => 2,
            'worker' => 3,
            'warehouse' => 6,
        ];

        $newUser = \App\Models\Login::create([
            'User_ID' => $validated['User_ID'],
            'User_First_Name' => $validated['User_First_Name'],
            'User_Middle_Name' => $validated['User_Middle_Name'] ?? '',
            'User_Last_Name' => $validated['User_Last_Name'],
            'Password' => $validated['Password'],
            'account_type' => $accountTypeMap[$validated['account_type']],
            'Gender' => $validated['Gender'],
        ]);

        return response()->json([
            'success' => true,
            'user' => [
                'login_ID' => $newUser->login_ID,
                'User_ID' => $newUser->User_ID,
                'User_First_Name' => $newUser->User_First_Name,
                'User_Middle_Name' => $newUser->User_Middle_Name,
                'User_Last_Name' => $newUser->User_Last_Name,
                'Password' => $validated['Password'],
                'account_type' => (int) $newUser->account_type,
                'Gender' => $newUser->Gender,
                'created_at' => optional($newUser->created_at)->toIso8601String(),
                'updated_at' => optional($newUser->updated_at)->toIso8601String(),
            ],
        ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'error' => $e->validator->errors()->first(),
        ], 422);
    } catch (\Throwable $e) {
        Log::error('USM warehouse-capable account creation failed.', [
            'message' => $e->getMessage(),
        ]);

        return response()->json([
            'error' => 'Database error: ' . $e->getMessage(),
        ], 500);
    }
})->name('admin.usm.create');

/*
|--------------------------------------------------------------------------
| Sales Report SOA direct role bridges
|--------------------------------------------------------------------------
|
| W68_SALES_REPORT_SOA_DIRECT_BRIDGE_20261026
| Special/Regular Sales Report must not proxy the Admin SOA route through
| app()->handle(). Nested Laravel requests can fail on HostForge and surface as
| HTTP 500. Resolve the already-registered Admin SOA action and invoke it
| directly in-process while preserving the current request/session.
|
*/
$w68InvokeAdminSalesReportSoa = function (Request $request, array $allowedTypes) {
    $user = session('user');

    if (is_array($user)) {
        $user = (object) $user;
    }

    if (!$user || !in_array((int) ($user->account_type ?? 0), $allowedTypes, true)) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized.',
        ], 403);
    }

    /*
     * W68_SALES_REPORT_SOA_MEMORY_GUARD_20261026
     *
     * The legacy Admin SOA generator can legitimately process a large amount
     * of invoice/payment data. HostForge PHP is capped at 128M by default and
     * production logs show the request fatalling at that limit. Raise memory
     * only for this report request, turn off query logs on the data
     * connections, and collect old cycles before invoking the legacy action.
     */
    @ini_set('memory_limit', '512M');

    foreach (['mysql', 'system', 'masterlist', 'sales', 'ledger', 'accounting', 'purchase'] as $connection) {
        try {
            DB::connection($connection)->disableQueryLog();
        } catch (\Throwable $ignored) {
        }
    }

    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }

    try {
        $adminRoute = Route::getRoutes()->getByName('admin.sales-report.print-soa');

        if (!$adminRoute) {
            Log::error('Sales Report SOA bridge target route is missing.', [
                'requested_path' => $request->path(),
                'account_type' => (int) ($user->account_type ?? 0),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'The SOA print handler is not registered on the server.',
            ], 503);
        }

        $uses = $adminRoute->getAction('uses');

        if (!$uses) {
            Log::error('Sales Report SOA bridge target has no callable action.', [
                'route_name' => 'admin.sales-report.print-soa',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'The SOA print handler is unavailable.',
            ], 503);
        }

        return app()->call($uses, [
            'request' => $request,
        ]);
    } catch (\Throwable $e) {
        Log::error('Sales Report SOA direct bridge failed.', [
            'requested_path' => $request->path(),
            'account_type' => (int) ($user->account_type ?? 0),
            'exception' => $e::class,
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'SOA print failed: ' . $e->getMessage(),
        ], 422);
    }
};

Route::post('/special/reports/sales-report/print-soa', function (Request $request) use ($w68InvokeAdminSalesReportSoa) {
    return $w68InvokeAdminSalesReportSoa($request, [3]);
})->name('special.sales-report.print-soa');

Route::post('/regular/reports/sales-report/print-soa', function (Request $request) use ($w68InvokeAdminSalesReportSoa) {
    return $w68InvokeAdminSalesReportSoa($request, [2]);
})->name('regular.sales-report.print-soa');

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
| system.customer_portal_accounts. Expose that linked account in Customer Master
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
                !\Illuminate\Support\Facades\Schema::connection('system')->hasTable('customer_portal_accounts')
                || !\Illuminate\Support\Facades\Schema::connection('system')->hasTable('logins')
            ) {
                return collect();
            }

            return DB::connection('system')
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
            && \Illuminate\Support\Facades\Schema::connection('system')->hasTable('customer_portal_accounts')
            && \Illuminate\Support\Facades\Schema::connection('system')->hasTable('logins')
        ) {
            $portalAccounts = DB::connection('system')
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

        DB::connection('system')->beginTransaction();
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

        if (\Illuminate\Support\Facades\Schema::connection('system')->hasTable('customer_portal_accounts')) {
            $portalLink = DB::connection('system')
                ->table('customer_portal_accounts')
                ->where('customer_id', $customerId)
                ->lockForUpdate()
                ->first();

            if ($portalLink) {
                $portalLogin = DB::connection('system')
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
                && \Illuminate\Support\Facades\Schema::connection('system')->hasTable('sessions')
                && \Illuminate\Support\Facades\Schema::connection('system')->hasColumn('sessions', 'user_id')
            ) {
                DB::connection('system')
                    ->table('sessions')
                    ->where('user_id', $loginId)
                    ->delete();
            }

            if (
                $portalLogin
                && !empty($portalLogin->Email)
                && \Illuminate\Support\Facades\Schema::connection('system')->hasTable('password_reset_tokens')
                && \Illuminate\Support\Facades\Schema::connection('system')->hasColumn('password_reset_tokens', 'email')
            ) {
                DB::connection('system')
                    ->table('password_reset_tokens')
                    ->where('email', (string) $portalLogin->Email)
                    ->delete();
            }

            DB::connection('system')
                ->table('customer_portal_accounts')
                ->where('id', (int) $portalLink->id)
                ->delete();

            if ($portalLogin && $loginId > 0) {
                DB::connection('system')
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
            && DB::connection('system')
                ->table('customer_portal_accounts')
                ->where('customer_id', $customerId)
                ->exists()
        ) {
            throw new \RuntimeException('Pricelist account-link deletion verification failed.');
        }

        if (
            $deletedLoginId
            && DB::connection('system')
                ->table('logins')
                ->where('login_ID', $deletedLoginId)
                ->where('account_type', 5)
                ->exists()
        ) {
            throw new \RuntimeException('Pricelist login deletion verification failed.');
        }

        DB::connection('ledger')->commit();
        $ledgerStarted = false;

        DB::connection('system')->commit();
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
        if ($systemStarted && DB::connection('system')->transactionLevel() > 0) {
            DB::connection('system')->rollBack();
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
                \Illuminate\Support\Facades\Schema::connection('system')->hasTable('logins')
                && \Illuminate\Support\Facades\Schema::connection('system')->hasTable('customer_portal_accounts')
            ) {
                $loginIds = DB::connection('system')
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
                    $matchingCustomerIds = DB::connection('system')
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
            && \Illuminate\Support\Facades\Schema::connection('system')->hasTable('customer_portal_accounts')
            && \Illuminate\Support\Facades\Schema::connection('system')->hasTable('logins')
        ) {
            $links = DB::connection('system')
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
                : DB::connection('system')
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


/*
|--------------------------------------------------------------------------
| Inventory Adjustment safety + chronological recalculation
|--------------------------------------------------------------------------
|
| W68_INVENTORY_ADJUSTMENT_NEGATIVE_GUARD_RECALC_20261021
|
| Shared Admin endpoint. Regular and Special Inventory Adjustment routes proxy
| to these named Admin routes, so all three roles receive the same protection.
|
*/
if (!function_exists('hatdogInventoryAdjustmentNegativeResponse')) {
    function hatdogInventoryAdjustmentNegativeResponse(
        int $currentOnHand,
        int $requestedQty,
        int $minimumSafeQty,
        int $projectedMinimumBalance,
        string $context = 'create',
        ?int $recordedAdjustedQty = null
    ) {
        $baseQty = $recordedAdjustedQty ?? $currentOnHand;
        $maximumSafeDeduction = max(0, $baseQty - $minimumSafeQty);

        $instruction = $context === 'edit'
            ? 'Re-enter an Adjusted Quantity of at least ' . $minimumSafeQty
                . '. If you want the largest safe reduction for this adjustment, use '
                . $minimumSafeQty . '.'
            : 'Re-enter the Actual Physical Quantity as 0 or higher. '
                . 'The maximum safe deduction from the current stock is '
                . max(0, $currentOnHand) . '.';

        return response()->json([
            'success' => false,
            'code' => 'NEGATIVE_INVENTORY',
            'message' => 'This adjustment was not saved because it would make inventory negative.',
            'warning_context' => $context,
            'current_on_hand' => $currentOnHand,
            'recorded_adjusted_qty' => $recordedAdjustedQty,
            'requested_adjusted_qty' => $requestedQty,
            'minimum_safe_adjusted_qty' => $minimumSafeQty,
            'recommended_adjusted_qty' => $minimumSafeQty,
            'maximum_safe_deduction' => $maximumSafeDeduction,
            'projected_minimum_balance' => $projectedMinimumBalance,
            'instruction' => $instruction,
        ], 422);
    }
}

Route::post('/admin/inventory/adjustment/adjust', function (\Illuminate\Http\Request $request) {
    $user = session('user');
    if (!$user) {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    $validated = $request->validate([
        'product_id' => 'required|exists:masterlist.products,id',
        'actual_qty' => 'required|integer',
        'unit' => 'required|string|max:255',
    ]);

    $ledgerDb = DB::connection('ledger');
    $masterDb = DB::connection('masterlist');
    $ledgerDb->beginTransaction();
    $masterDb->beginTransaction();

    try {
        $product = $masterDb->table('products')
            ->where('id', (int) $validated['product_id'])
            ->lockForUpdate()
            ->first();

        if (!$product) {
            throw new \RuntimeException('Product not found.');
        }

        $latestLedger = $ledgerDb->table('product_ledgers')
            ->where('product_id', (int) $product->id)
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $currentBalance = $latestLedger
            ? (int) ($latestLedger->balance_stock ?? 0)
            : (int) ($product->on_hand ?? 0);

        $targetBalance = (int) $validated['actual_qty'];

        if ($targetBalance < 0) {
            $ledgerDb->rollBack();
            $masterDb->rollBack();

            return hatdogInventoryAdjustmentNegativeResponse(
                (int) ($product->on_hand ?? $currentBalance),
                $targetBalance,
                0,
                $targetBalance,
                'create'
            );
        }

        $unit = trim((string) $validated['unit']);
        $movement = $targetBalance - $currentBalance;
        $transactionType = $movement >= 0 ? 'IN' : 'OUT';
        $quantityIn = $movement > 0 ? $movement : 0;
        $quantityOut = $movement < 0 ? abs($movement) : 0;
        $actor = hatdogAuditActor($user);
        $now = now();

        $ledgerId = $ledgerDb->table('product_ledgers')->insertGetId([
            'product_id' => (int) $product->id,
            'transaction_type' => $transactionType,
            'date' => $now->toDateString(),
            'transaction_number' => 'ADJ-' . (string) ($product->product_code ?? $product->id) . '-' . $now->format('YmdHis'),
            'reference_number' => 'INVENTORY-ADJUSTMENT',
            'entity_name' => $actor['name'],
            'quantity_in' => $quantityIn,
            'quantity_out' => $quantityOut,
            'balance_stock' => $targetBalance,
            'oum' => $unit,
            'price' => (float) ($product->selling_price ?? 0),
            'cost' => (float) ($product->cost ?? 0),
            'remarks' => 'Inventory adjustment via admin panel',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $beforeOnHand = (int) ($product->on_hand ?? 0);
        $beforeActualQty = (int) ($product->actual_qty ?? 0);
        $beforeUnit = trim((string) ($product->unit ?? ''));

        $masterDb->table('products')->where('id', (int) $product->id)->update([
            'on_hand' => $targetBalance,
            'actual_qty' => $targetBalance,
            'unit' => $unit,
            'updated_at' => $now,
        ]);

        $recordName = trim((string) (
            ($product->description ?? '') !== ''
                ? $product->description
                : ($product->part_number ?? $product->product_code ?? 'Inventory Item')
        ));

        hatdogWriteAuditTrail([
            'module' => 'inventory',
            'action' => 'Adjusted',
            'source_connection' => 'ledger',
            'source_table' => 'product_ledgers',
            'source_id' => (int) $ledgerId,
            'display_id' => (string) ($product->product_code ?? $product->id),
            'record_name' => $recordName,
            'user_name' => $actor['name'],
            'user_identifier' => $actor['identifier'],
            'audit_data' => [
                'before' => [
                    'on_hand' => $beforeOnHand,
                    'actual_qty' => $beforeActualQty,
                    'unit' => $beforeUnit,
                ],
                'after' => [
                    'on_hand' => $targetBalance,
                    'actual_qty' => $targetBalance,
                    'unit' => $unit,
                    'quantity_in' => $quantityIn,
                    'quantity_out' => $quantityOut,
                ],
            ],
        ]);

        $ledgerDb->commit();
        $masterDb->commit();

        \App\Services\ProductStockSyncService::clearProductMasterStatsCache();

        return response()->json([
            'success' => true,
            'message' => 'Inventory adjusted successfully. On-hand stock is now ' . $targetBalance . '.',
            'on_hand' => $targetBalance,
            'adjustment_id' => (int) $ledgerId,
        ]);
    } catch (\Throwable $e) {
        if ($ledgerDb->transactionLevel() > 0) $ledgerDb->rollBack();
        if ($masterDb->transactionLevel() > 0) $masterDb->rollBack();

        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 422);
    }
})->name('admin.inv-adjust.save');

Route::patch('/admin/inventory/adjustment/adjusted/{id}', function (\Illuminate\Http\Request $request, $id) {
    $user = session('user');
    if (!$user) {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    $validated = $request->validate([
        'adjusted_qty' => 'required|integer',
        'unit' => 'required|string|max:255',
    ]);

    $ledgerDb = DB::connection('ledger');
    $masterDb = DB::connection('masterlist');
    $ledgerDb->beginTransaction();
    $masterDb->beginTransaction();

    try {
        $row = $ledgerDb->table('product_ledgers')
            ->where('id', (int) $id)
            ->where('reference_number', 'INVENTORY-ADJUSTMENT')
            ->lockForUpdate()
            ->first();

        if (!$row) {
            $ledgerDb->rollBack();
            $masterDb->rollBack();
            return response()->json(['success' => false, 'message' => 'Adjustment record not found.'], 404);
        }

        $product = $masterDb->table('products')
            ->where('id', (int) $row->product_id)
            ->lockForUpdate()
            ->first();

        if (!$product) {
            $ledgerDb->rollBack();
            $masterDb->rollBack();
            return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
        }

        $rows = $ledgerDb->table('product_ledgers')
            ->where('product_id', (int) $row->product_id)
            ->orderBy('date')
            ->orderByRaw('COALESCE(created_at, "1970-01-01 00:00:00") ASC')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $targetIndex = null;
        foreach ($rows as $index => $ledgerRow) {
            if ((int) $ledgerRow->id === (int) $row->id) {
                $targetIndex = $index;
                break;
            }
        }

        if ($targetIndex === null) {
            throw new \RuntimeException('Unable to locate the adjustment in the Product Ledger sequence.');
        }

        $oldTargetBalance = (int) ($row->balance_stock ?? 0);
        $newTargetBalance = (int) $validated['adjusted_qty'];
        $unit = trim((string) $validated['unit']);

        // Preserve the exact stock immediately before this adjustment.
        $balanceBeforeTarget = $oldTargetBalance
            - (int) ($row->quantity_in ?? 0)
            + (int) ($row->quantity_out ?? 0);

        // A later Inventory Adjustment is an absolute physical-stock anchor.
        // Only movements before the next adjustment depend on this edited target.
        $cumulativeAfterTarget = 0;
        $minimumCumulative = 0;
        $nextAdjustmentIndex = null;

        for ($i = $targetIndex + 1, $count = $rows->count(); $i < $count; $i++) {
            $laterRow = $rows[$i];

            if (strtoupper(trim((string) ($laterRow->reference_number ?? ''))) === 'INVENTORY-ADJUSTMENT') {
                $nextAdjustmentIndex = $i;
                break;
            }

            $cumulativeAfterTarget += (int) ($laterRow->quantity_in ?? 0)
                - (int) ($laterRow->quantity_out ?? 0);
            $minimumCumulative = min($minimumCumulative, $cumulativeAfterTarget);
        }

        $minimumSafeTarget = max(0, -$minimumCumulative);
        $projectedMinimumBalance = $newTargetBalance + $minimumCumulative;

        if ($newTargetBalance < $minimumSafeTarget) {
            $ledgerDb->rollBack();
            $masterDb->rollBack();

            return hatdogInventoryAdjustmentNegativeResponse(
                (int) ($product->on_hand ?? 0),
                $newTargetBalance,
                $minimumSafeTarget,
                $projectedMinimumBalance,
                'edit',
                $oldTargetBalance
            );
        }

        $targetMovement = $newTargetBalance - $balanceBeforeTarget;
        $targetType = $targetMovement >= 0 ? 'IN' : 'OUT';
        $targetIn = $targetMovement > 0 ? $targetMovement : 0;
        $targetOut = $targetMovement < 0 ? abs($targetMovement) : 0;

        $ledgerDb->table('product_ledgers')->where('id', (int) $row->id)->update([
            'transaction_type' => $targetType,
            'quantity_in' => $targetIn,
            'quantity_out' => $targetOut,
            'balance_stock' => $newTargetBalance,
            'oum' => $unit,
            'updated_at' => now(),
        ]);

        $runningBalance = $newTargetBalance;

        // Recalculate every dependent row until the next physical adjustment.
        $endIndex = $nextAdjustmentIndex ?? $rows->count();

        for ($i = $targetIndex + 1; $i < $endIndex; $i++) {
            $laterRow = $rows[$i];
            $runningBalance += (int) ($laterRow->quantity_in ?? 0)
                - (int) ($laterRow->quantity_out ?? 0);

            if ($runningBalance < 0) {
                throw new \RuntimeException(
                    'Adjustment recalculation stopped because ledger row #'
                    . (int) $laterRow->id . ' would become negative.'
                );
            }

            if ((int) ($laterRow->balance_stock ?? 0) !== $runningBalance) {
                $ledgerDb->table('product_ledgers')->where('id', (int) $laterRow->id)->update([
                    'balance_stock' => $runningBalance,
                    'updated_at' => now(),
                ]);
            }
        }

        // The next Inventory Adjustment remains the same absolute stock count,
        // but its IN/OUT movement must be rebuilt from the newly calculated
        // stock immediately before it.
        if ($nextAdjustmentIndex !== null) {
            $anchorRow = $rows[$nextAdjustmentIndex];
            $anchorBalance = (int) ($anchorRow->balance_stock ?? 0);

            if ($anchorBalance < 0) {
                throw new \RuntimeException('A later inventory adjustment already contains a negative stock balance.');
            }

            $anchorMovement = $anchorBalance - $runningBalance;
            $anchorType = $anchorMovement >= 0 ? 'IN' : 'OUT';
            $anchorIn = $anchorMovement > 0 ? $anchorMovement : 0;
            $anchorOut = $anchorMovement < 0 ? abs($anchorMovement) : 0;

            $ledgerDb->table('product_ledgers')->where('id', (int) $anchorRow->id)->update([
                'transaction_type' => $anchorType,
                'quantity_in' => $anchorIn,
                'quantity_out' => $anchorOut,
                'updated_at' => now(),
            ]);
        }

        // Read the true current stock after recalculation. If there is a later
        // adjustment anchor, the later chain is intentionally unchanged.
        $latestLedger = $ledgerDb->table('product_ledgers')
            ->where('product_id', (int) $row->product_id)
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first(['balance_stock']);

        $latestAdjustment = $ledgerDb->table('product_ledgers')
            ->where('product_id', (int) $row->product_id)
            ->where('reference_number', 'INVENTORY-ADJUSTMENT')
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first(['balance_stock', 'oum']);

        $currentOnHand = (int) ($latestLedger->balance_stock ?? 0);
        $latestActualQty = $latestAdjustment
            ? (int) ($latestAdjustment->balance_stock ?? $currentOnHand)
            : $currentOnHand;
        $latestUnit = $latestAdjustment && trim((string) ($latestAdjustment->oum ?? '')) !== ''
            ? trim((string) $latestAdjustment->oum)
            : trim((string) ($product->unit ?? ''));

        if ($currentOnHand < 0) {
            throw new \RuntimeException('Adjustment update was cancelled because the resulting current on-hand would be negative.');
        }

        $masterDb->table('products')->where('id', (int) $row->product_id)->update([
            'on_hand' => $currentOnHand,
            'actual_qty' => $latestActualQty,
            'unit' => $latestUnit,
            'updated_at' => now(),
        ]);

        $actor = hatdogAuditActor($user);
        hatdogWriteAuditTrail([
            'module' => 'inventory',
            'action' => 'Updated',
            'source_connection' => 'ledger',
            'source_table' => 'product_ledgers',
            'source_id' => (int) $row->id,
            'display_id' => (string) ($product->product_code ?? $row->id),
            'record_name' => (string) ($product->part_number ?? $product->product_code ?? 'Inventory Adjustment'),
            'user_name' => $actor['name'],
            'user_identifier' => $actor['identifier'],
            'audit_data' => [
                'before' => [
                    'transaction_type' => (string) ($row->transaction_type ?? ''),
                    'quantity_in' => (int) ($row->quantity_in ?? 0),
                    'quantity_out' => (int) ($row->quantity_out ?? 0),
                    'adjusted_qty' => $oldTargetBalance,
                    'unit' => (string) ($row->oum ?? ''),
                    'product_on_hand' => (int) ($product->on_hand ?? 0),
                ],
                'after' => [
                    'transaction_type' => $targetType,
                    'quantity_in' => $targetIn,
                    'quantity_out' => $targetOut,
                    'adjusted_qty' => $newTargetBalance,
                    'unit' => $unit,
                    'product_on_hand' => $currentOnHand,
                ],
            ],
        ]);

        $ledgerDb->commit();
        $masterDb->commit();

        \App\Services\ProductStockSyncService::clearProductMasterStatsCache();

        return response()->json([
            'success' => true,
            'message' => 'Adjustment updated and on-hand stock recalculated successfully.',
            'on_hand' => $currentOnHand,
            'adjusted_qty' => $newTargetBalance,
        ]);
    } catch (\Throwable $e) {
        if ($ledgerDb->transactionLevel() > 0) $ledgerDb->rollBack();
        if ($masterDb->transactionLevel() > 0) $masterDb->rollBack();

        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 422);
    }
})->name('admin.inv-adjust.adjusted.update');


/*
|--------------------------------------------------------------------------
| Developer Control Panel - real SQL database downloads
|--------------------------------------------------------------------------
| W68_DEVELOPER_REAL_SQL_ZIP_BACKUP_20261022
|
| Replaces the older CSV-in-ZIP download endpoints. Every download is a ZIP
| archive containing genuine MariaDB/MySQL .sql.gz dump(s). CSV is never used.
|
*/

Route::get('/developer/data-ups/download/{type}/{filename?}', function (string $type, $filename = null) {
    $user = session('user');
    $accountType = is_array($user)
        ? ($user['account_type'] ?? null)
        : ($user->account_type ?? null);

    if (!$user || (int) $accountType !== 4) {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    $connectionMap = \App\Services\DeveloperDatabaseBackupService::connectionMap();

    if (!isset($connectionMap[$type])) {
        return response()->json(['success' => false, 'message' => 'Invalid database type'], 400);
    }

    $dumpPath = null;
    $zipPath = null;
    $zip = null;

    try {
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $timestamp = now('Asia/Manila')->format('Y-m-d_H-i-s');
        $connection = $connectionMap[$type];

        $dumpPath = app(\App\Services\DeveloperDatabaseBackupService::class)
            ->exportSqlGz($connection, $type);

        $tmp = rtrim(sys_get_temp_dir(), '\\/');
        $zipPath = $tmp . DIRECTORY_SEPARATOR
            . 'w68_' . preg_replace('/[^a-z0-9_-]+/i', '_', $type)
            . '_' . bin2hex(random_bytes(8)) . '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create the database backup ZIP.');
        }

        $databaseName = (string) config("database.connections.{$connection}.database", $type);
        $safeDatabaseName = preg_replace('/[^a-z0-9_.-]+/i', '_', $databaseName) ?: $type;
        $entryName = "{$safeDatabaseName}_{$timestamp}.sql.gz";

        if (!$zip->addFile($dumpPath, $entryName)) {
            throw new \RuntimeException('Unable to add the SQL dump to the backup ZIP.');
        }

        $zip->close();
        $zip = null;

        @unlink($dumpPath);
        $dumpPath = null;

        $downloadName = "w68_{$type}_database_{$timestamp}.zip";

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        return response()->download($zipPath, $downloadName, [
            'Content-Type' => 'application/zip',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ])->deleteFileAfterSend(true);
    } catch (\Throwable $e) {
        if ($zip instanceof \ZipArchive) {
            try {
                $zip->close();
            } catch (\Throwable $ignored) {
            }
        }

        if ($dumpPath) {
            @unlink($dumpPath);
        }

        if ($zipPath) {
            @unlink($zipPath);
        }

        \Illuminate\Support\Facades\Log::error('Developer SQL database ZIP download failed.', [
            'type' => $type,
            'message' => $e->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Unable to create SQL database backup ZIP: ' . $e->getMessage(),
        ], 500);
    }
})->where('filename', '[^/]+')->name('developer.data-ups.download');

Route::get('/developer/data-ups/download-all/{filename?}', function ($filename = null) {
    $user = session('user');
    $accountType = is_array($user)
        ? ($user['account_type'] ?? null)
        : ($user->account_type ?? null);

    if (!$user || (int) $accountType !== 4) {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    $timestamp = now('Asia/Manila')->format('Y-m-d_H-i-s');
    $tmp = rtrim(sys_get_temp_dir(), '\\/');
    $zipPath = $tmp . DIRECTORY_SEPARATOR . 'w68_databases_' . bin2hex(random_bytes(8)) . '.zip';
    $zip = new \ZipArchive();

    if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
        return response()->json([
            'success' => false,
            'message' => 'Unable to create the database backup ZIP.',
        ], 500);
    }

    $dumpPaths = [];

    try {
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $backupService = app(\App\Services\DeveloperDatabaseBackupService::class);

        foreach (\App\Services\DeveloperDatabaseBackupService::connectionMap() as $group => $connection) {
            $dumpPath = $backupService->exportSqlGz($connection, $group);
            $dumpPaths[] = $dumpPath;

            $databaseName = (string) config("database.connections.{$connection}.database", $group);
            $safeDatabaseName = preg_replace('/[^a-z0-9_.-]+/i', '_', $databaseName) ?: $group;

            if (!$zip->addFile(
                $dumpPath,
                "{$safeDatabaseName}_{$timestamp}.sql.gz"
            )) {
                throw new \RuntimeException("Unable to add {$group} SQL dump to the backup ZIP.");
            }
        }

        $zip->close();

        foreach ($dumpPaths as $dumpPath) {
            @unlink($dumpPath);
        }

        $downloadName = "w68_all_databases_{$timestamp}.zip";

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        return response()->download($zipPath, $downloadName, [
            'Content-Type' => 'application/zip',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ])->deleteFileAfterSend(true);
    } catch (\Throwable $e) {
        try {
            $zip->close();
        } catch (\Throwable $ignored) {
        }

        foreach ($dumpPaths as $dumpPath) {
            @unlink($dumpPath);
        }

        @unlink($zipPath);

        \Illuminate\Support\Facades\Log::error('Developer full SQL database download failed.', [
            'message' => $e->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Unable to create full SQL database backup: ' . $e->getMessage(),
        ], 500);
    }
})->where('filename', '[^/]+')->name('developer.data-ups.download-all');


/*
|--------------------------------------------------------------------------
| Purchase Order transaction-date synchronization
|--------------------------------------------------------------------------
|
| W68_PURCHASE_ORDER_TRANSACTION_DATE_SYNC_20261028
| Processing and Edit Purchase Order share this wrapper for Admin, Regular,
| and Special users. It delegates the existing business operation unchanged,
| then synchronizes only the transaction-date columns on the saved PO and its
| linked ledger rows. Amounts, quantities, balances, and created_at timestamps
| are intentionally left untouched.
|
*/
$w68SyncPurchaseOrderTransactionDate = function (int $purchaseOrderId, string $transactionDate): array {
    $purchaseDb = DB::connection('purchase');
    $ledgerDb = DB::connection('ledger');
    $ledgerSchema = \Illuminate\Support\Facades\Schema::connection('ledger');

    $purchaseOrder = $purchaseDb->table('purchase_orders')->where('id', $purchaseOrderId)->first();
    if (!$purchaseOrder) {
        throw new \RuntimeException('Saved Purchase Order #' . $purchaseOrderId . ' could not be found.');
    }

    // Save only the transaction-date field on the Purchase Order header.
    $purchaseDb->table('purchase_orders')
        ->where('id', $purchaseOrderId)
        ->update(['date' => $transactionDate]);

    $poNumber = trim((string) ($purchaseOrder->po_number ?? ''));
    $invoiceNumber = trim((string) ($purchaseOrder->supplier_invoice_number ?? ''));
    $supplierId = (int) ($purchaseOrder->supplier_id ?? 0);
    $legacyNumbers = array_values(array_unique(array_filter(
        [$poNumber, $invoiceNumber],
        fn ($value) => $value !== ''
    )));

    $productLedgerRowsUpdated = 0;
    if (
        $ledgerSchema->hasTable('product_ledgers')
        && $ledgerSchema->hasColumn('product_ledgers', 'date')
    ) {
        $hasSourceIdentity = $ledgerSchema->hasColumn('product_ledgers', 'source_type')
            && $ledgerSchema->hasColumn('product_ledgers', 'source_id');
        $hasLegacyIdentity = $supplierId > 0
            && $legacyNumbers !== []
            && $ledgerSchema->hasColumn('product_ledgers', 'supplier_id')
            && $ledgerSchema->hasColumn('product_ledgers', 'transaction_type')
            && $ledgerSchema->hasColumn('product_ledgers', 'transaction_number')
            && $ledgerSchema->hasColumn('product_ledgers', 'remarks');

        if ($hasSourceIdentity || $hasLegacyIdentity) {
            $query = $ledgerDb->table('product_ledgers')->where(function ($outer) use (
                $purchaseOrderId,
                $supplierId,
                $legacyNumbers,
                $hasSourceIdentity,
                $hasLegacyIdentity,
                $ledgerSchema
            ) {
                $hasCondition = false;

                if ($hasSourceIdentity) {
                    $outer->where(function ($source) use ($purchaseOrderId) {
                        $source->whereIn('source_type', ['purchase_order', 'purchase-order'])
                            ->where('source_id', $purchaseOrderId);
                    });
                    $hasCondition = true;
                }

                if ($hasLegacyIdentity) {
                    $method = $hasCondition ? 'orWhere' : 'where';
                    $outer->{$method}(function ($legacy) use ($supplierId, $legacyNumbers, $ledgerSchema) {
                        $legacy->where('supplier_id', $supplierId)
                            ->where('transaction_type', 'IN')
                            ->where('remarks', 'like', '%Purchase Order%')
                            ->where(function ($identity) use ($legacyNumbers, $ledgerSchema) {
                                $identity->whereIn('transaction_number', $legacyNumbers);
                                if ($ledgerSchema->hasColumn('product_ledgers', 'reference_number')) {
                                    $identity->orWhereIn('reference_number', $legacyNumbers);
                                }
                            });
                    });
                }
            });

            $productLedgerRowsUpdated = $query->update(['date' => $transactionDate]);
        }
    }

    $supplierLedgerIds = [];
    $supplierLedgerRowsUpdated = 0;
    if (
        $ledgerSchema->hasTable('supplier_ledgers')
        && $ledgerSchema->hasColumn('supplier_ledgers', 'date')
    ) {
        $hasSourceIdentity = $ledgerSchema->hasColumn('supplier_ledgers', 'source_purchase_order_id');
        $hasLegacyIdentity = $supplierId > 0
            && $poNumber !== ''
            && $ledgerSchema->hasColumn('supplier_ledgers', 'transaction_code')
            && $ledgerSchema->hasColumn('supplier_ledgers', 'supplier_id')
            && $ledgerSchema->hasColumn('supplier_ledgers', 'module_type');

        if ($hasSourceIdentity || $hasLegacyIdentity) {
            $query = $ledgerDb->table('supplier_ledgers')->where(function ($outer) use (
                $purchaseOrderId,
                $poNumber,
                $supplierId,
                $hasSourceIdentity,
                $hasLegacyIdentity
            ) {
                $hasCondition = false;

                if ($hasSourceIdentity) {
                    $outer->where('source_purchase_order_id', $purchaseOrderId);
                    $hasCondition = true;
                }

                if ($hasLegacyIdentity) {
                    $method = $hasCondition ? 'orWhere' : 'where';
                    $outer->{$method}(function ($legacy) use ($poNumber, $supplierId) {
                        $legacy->where('transaction_code', $poNumber)
                            ->where('supplier_id', $supplierId)
                            ->where('module_type', 'Purchase Order');
                    });
                }
            });

            $supplierLedgerIds = $query->pluck('id')->map(fn ($id) => (int) $id)->all();
            $supplierLedgerRowsUpdated = $query->update(['date' => $transactionDate]);
        }
    }

    $supplierLedgerItemRowsUpdated = 0;
    if (
        $ledgerSchema->hasTable('supplier_ledger_items')
        && $ledgerSchema->hasColumn('supplier_ledger_items', 'date')
    ) {
        $purchaseOrderItemIds = [];
        if (
            $purchaseDb->getSchemaBuilder()->hasTable('purchase_order_items')
            && $purchaseDb->getSchemaBuilder()->hasColumn('purchase_order_items', 'purchase_order_id')
        ) {
            $purchaseOrderItemIds = $purchaseDb->table('purchase_order_items')
                ->where('purchase_order_id', $purchaseOrderId)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $hasParentLink = $ledgerSchema->hasColumn('supplier_ledger_items', 'supplier_ledger_id')
            && $supplierLedgerIds !== [];
        $hasItemLink = $ledgerSchema->hasColumn('supplier_ledger_items', 'source_purchase_order_item_id')
            && $purchaseOrderItemIds !== [];

        if ($hasParentLink || $hasItemLink) {
            $query = $ledgerDb->table('supplier_ledger_items')->where(function ($outer) use (
                $supplierLedgerIds,
                $purchaseOrderItemIds,
                $hasParentLink,
                $hasItemLink
            ) {
                $hasCondition = false;

                if ($hasParentLink) {
                    $outer->whereIn('supplier_ledger_id', $supplierLedgerIds);
                    $hasCondition = true;
                }

                if ($hasItemLink) {
                    $method = $hasCondition ? 'orWhereIn' : 'whereIn';
                    $outer->{$method}('source_purchase_order_item_id', $purchaseOrderItemIds);
                }
            });

            $supplierLedgerItemRowsUpdated = $query->update(['date' => $transactionDate]);
        }
    }

    return [
        'purchase_order_id' => $purchaseOrderId,
        'transaction_date' => $transactionDate,
        'product_ledger_rows_updated' => $productLedgerRowsUpdated,
        'supplier_ledger_rows_updated' => $supplierLedgerRowsUpdated,
        'supplier_ledger_item_rows_updated' => $supplierLedgerItemRowsUpdated,
    ];
};

$w68WrapPurchaseOrderDateRoute = function (string $routeName, string $operation) use ($w68SyncPurchaseOrderTransactionDate): void {
    $originalRoute = Route::getRoutes()->getByName($routeName);
    if (!$originalRoute) {
        Log::warning('Purchase Order transaction-date wrapper could not find route.', [
            'route_name' => $routeName,
            'operation' => $operation,
        ]);
        return;
    }

    $originalAction = $originalRoute->getAction('uses');
    if (!is_callable($originalAction)) {
        Log::warning('Purchase Order transaction-date wrapper skipped a non-callable route action.', [
            'route_name' => $routeName,
            'operation' => $operation,
        ]);
        return;
    }

    $methods = array_values(array_diff($originalRoute->methods(), ['HEAD']));
    $uri = $originalRoute->uri();
    $middleware = array_values(array_diff($originalRoute->gatherMiddleware(), ['web']));

    Route::match($methods, $uri, function (Request $request) use (
        $originalAction,
        $operation,
        $routeName,
        $w68SyncPurchaseOrderTransactionDate
    ) {
        $transactionDate = trim((string) $request->input('date', ''));
        if (
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $transactionDate)
            || !checkdate(
                (int) substr($transactionDate, 5, 2),
                (int) substr($transactionDate, 8, 2),
                (int) substr($transactionDate, 0, 4)
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'A valid Transaction Date is required (YYYY-MM-DD).',
            ], 422);
        }

        // Run the original W68 operation unchanged first.
        $response = app()->call($originalAction, ['request' => $request]);

        if (!is_object($response) || !method_exists($response, 'getStatusCode')) {
            return $response;
        }

        $status = (int) $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            return $response;
        }

        $responseData = [];
        if (method_exists($response, 'getData')) {
            try {
                $responseData = $response->getData(true);
            } catch (\Throwable $ignored) {
                $responseData = [];
            }
        } elseif (method_exists($response, 'getContent')) {
            $decoded = json_decode((string) $response->getContent(), true);
            $responseData = is_array($decoded) ? $decoded : [];
        }

        if (is_array($responseData) && array_key_exists('success', $responseData) && $responseData['success'] !== true) {
            return $response;
        }

        // For edits, trust the submitted PO ID first. Processing may only
        // return the newly created ID, so fall back to its response payload.
        $purchaseOrderId = (int) $request->input('po_id', 0);
        if ($purchaseOrderId <= 0) {
            $purchaseOrderId = (int) (
                $responseData['po_id']
                ?? $responseData['purchase_order_id']
                ?? 0
            );
        }
        if ($purchaseOrderId <= 0 && $operation === 'process') {
            $purchaseOrderId = (int) ($responseData['id'] ?? 0);
        }

        if ($purchaseOrderId <= 0 && $operation === 'process') {
            $poNumber = trim((string) $request->input('po_number', ''));
            if ($poNumber !== '') {
                $purchaseOrderId = (int) DB::connection('purchase')
                    ->table('purchase_orders')
                    ->where('po_number', $poNumber)
                    ->value('id');
            }
        }

        if ($purchaseOrderId <= 0) {
            Log::error('Purchase Order saved but its transaction date could not be synchronized because the PO ID was not found.', [
                'route_name' => $routeName,
                'operation' => $operation,
                'po_number' => $request->input('po_number'),
            ]);
            return $response;
        }

        try {
            $syncResult = $w68SyncPurchaseOrderTransactionDate($purchaseOrderId, $transactionDate);
            Log::info('Purchase Order transaction date synchronized across linked ledger rows.', $syncResult);
        } catch (\Throwable $e) {
            Log::error('Purchase Order transaction-date synchronization failed after the original save.', [
                'route_name' => $routeName,
                'operation' => $operation,
                'purchase_order_id' => $purchaseOrderId,
                'transaction_date' => $transactionDate,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'po_id' => $purchaseOrderId,
                'message' => 'The Purchase Order was saved, but its Transaction Date could not be synchronized to all ledger tables. Do not submit it again; check the application log.',
            ], 500);
        }

        return $response;
    })->middleware($middleware)->name($routeName);
};

foreach ([
    ['admin.purchase-order.process', 'process'],
    ['admin.purchase-order.update', 'update'],
    ['regular.purchase-order.process', 'process'],
    ['regular.purchase-order.update', 'update'],
    ['special.purchase-order.process', 'process'],
    ['special.purchase-order.update', 'update'],
] as [$routeName, $operation]) {
    $w68WrapPurchaseOrderDateRoute($routeName, $operation);
}
