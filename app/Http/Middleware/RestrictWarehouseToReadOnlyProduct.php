<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictWarehouseToReadOnlyProduct
{
    /**
     * Account type 6 (Warehouse) is intentionally limited to read-only
     * Product Master access plus its own profile/logout endpoints.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->session()->get('user');

        if (is_array($user)) {
            $user = (object) $user;
        }

        if (!$user || (int) ($user->account_type ?? 0) !== 6) {
            return $next($request);
        }

        $path = trim($request->path(), '/');
        $method = strtoupper($request->method());

        $allowedExact = [
            'dashboard',
            'warehouse/dashboard',
            'warehouse/masterlist/product',
            'warehouse/profile',
            'warehouse/profile/update',
            'warehouse/profile/picture/update',
            'warehouse/logout-now',
            'logout',
            'login',
        ];

        $allowedPrefixes = [
            'warehouse/masterlist/product/',
        ];

        $allowed = in_array($path, $allowedExact, true);

        if (!$allowed) {
            foreach ($allowedPrefixes as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    $allowed = true;
                    break;
                }
            }
        }

        if ($allowed) {
            return $next($request);
        }

        if (
            !in_array($method, ['GET', 'HEAD'], true)
            || $request->expectsJson()
            || $request->ajax()
        ) {
            return response()->json([
                'success' => false,
                'error' => 'Warehouse access is view-only. Only Product Masterlist is available.',
            ], 403);
        }

        return redirect()->route('warehouse.prod-master');
    }
}
