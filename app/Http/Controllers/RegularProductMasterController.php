<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductPriceCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class RegularProductMasterController extends Controller
{
    /**
     * Production-safe Product Master data endpoint for Regular users.
     *
     * IMPORTANT: masterlist and ledger are separate MariaDB services on
     * HostForge. Never use a raw SQL cross-database subquery here.
     * products.on_hand is the synchronized inventory value used by the Admin
     * Product Master as well.
     */
    public function data(Request $request): JsonResponse
    {
        $user = session('user');

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (is_array($user)) {
            $user = (object) $user;
        }

        if ((int) ($user->account_type ?? 0) !== 2) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $tab = (string) $request->query('tab', 'all');
        $perPage = max(10, min(200, (int) $request->query('perPage', 50)));
        $search = (array) $request->query('search', []);
        $filter = (array) $request->query('filter', []);
        $sortBy = (string) $request->query('sort_by', '');
        $sortDirection = strtolower((string) $request->query('sort_direction', 'asc')) === 'desc'
            ? 'desc'
            : 'asc';

        $query = Product::query()->select('products.*');

        if ($tab === 'newly') {
            $query->where('status', 'Newly');
        } elseif ($tab === 'low') {
            $query->whereNotNull('Re_order_level')
                ->whereColumn('on_hand', '<=', 'Re_order_level');
        }

        $searchMap = [
            'productCode' => 'product_code',
            'partNumber' => 'part_number',
            'description' => 'description',
            'application' => 'application',
            'specification' => 'specification',
            'position' => 'position',
            'category' => 'category',
            'onHand' => 'on_hand',
            'restockLevel' => 'Re_order_level',
            'sellingPrice' => 'selling_price',
            'priceOnline' => 'price_online',
            'dateAdded' => 'date_added',
        ];

        $escapeLike = static fn ($value) => str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            (string) $value
        );

        foreach ($searchMap as $requestColumn => $databaseColumn) {
            if (!isset($search[$requestColumn]) || trim((string) $search[$requestColumn]) === '') {
                continue;
            }

            $term = $escapeLike($search[$requestColumn]);

            if ($requestColumn === 'onHand') {
                $query->whereRaw('CAST(on_hand AS CHAR) LIKE ?', ['%' . $term . '%']);
            } elseif ($requestColumn === 'restockLevel') {
                $query->whereRaw('CAST(Re_order_level AS CHAR) LIKE ?', ['%' . $term . '%']);
            } elseif ($requestColumn === 'priceOnline') {
                $query->whereRaw('CAST(price_online AS CHAR) LIKE ?', ['%' . $term . '%']);
            } else {
                $query->where($databaseColumn, 'LIKE', '%' . $term . '%');
            }
        }

        if (!empty($filter['category']) && $filter['category'] !== 'all') {
            $query->where('category', (string) $filter['category']);
        }

        if (!empty($filter['application']) && $filter['application'] !== 'all') {
            $query->where('application', 'LIKE', '%' . $escapeLike($filter['application']) . '%');
        }

        if (!empty($filter['status']) && $filter['status'] !== 'all') {
            $query->where('status', (string) $filter['status']);
        }

        $sortableColumns = [
            'productCode' => 'product_code',
            'description' => 'description',
            'application' => 'application',
            'brand' => 'category',
            'partNumber' => 'part_number',
            'position' => 'position',
            'specification' => 'specification',
            'unit' => 'unit',
            'priceOnline' => 'price_online',
            'sellingPrice' => 'selling_price',
            'onHand' => 'on_hand',
            'restockLevel' => 'Re_order_level',
            'dateAdded' => 'date_added',
        ];

        if ($sortBy !== '' && isset($sortableColumns[$sortBy])) {
            $query->orderBy($sortableColumns[$sortBy], $sortDirection);
        } else {
            $query->orderByDesc('created_at')->orderByDesc('id');
        }

        $pagination = $query->paginate($perPage);
        $productIds = collect($pagination->items())
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $priceCodeMap = collect();

        if ($productIds->isNotEmpty()) {
            try {
                $priceCodeMap = ProductPriceCode::query()
                    ->whereIn('product_id', $productIds->all())
                    ->orderBy('sort_order')
                    ->get()
                    ->groupBy('product_id')
                    ->map(fn ($rows) => $rows->map(fn ($row) => [
                        'price_code' => $row->price_code,
                        'selling_price' => (float) $row->selling_price,
                    ])->values()->all());
            } catch (Throwable $e) {
                $priceCodeMap = collect();
            }
        }

        $pagination->getCollection()->transform(function ($product) use ($priceCodeMap) {
            $product->on_hand = (int) ($product->on_hand ?? 0);
            $product->restock_level = $product->Re_order_level;
            $product->price_codes = $priceCodeMap->get($product->id, []);

            foreach ($product->getAttributes() as $field => $value) {
                if ($field === 'Product_Picture' || $field === 'images') {
                    continue;
                }

                if (is_string($value)) {
                    $product->{$field} = iconv('UTF-8', 'UTF-8//IGNORE', $value);
                }
            }

            $product->Product_Picture = $this->normalizeProductPicture(
                $product->Product_Picture ?? null
            );

            return $product;
        });

        // HostForge-safe counters: this query stays completely inside the
        // masterlist connection. No masterlist -> ledger SQL join is attempted.
        $stats = DB::connection('masterlist')->selectOne(<<<'SQL'
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN p.status = 'Newly' THEN 1 ELSE 0 END) AS newly,
                SUM(
                    CASE
                        WHEN p.Re_order_level IS NOT NULL
                         AND COALESCE(p.on_hand, 0) <= p.Re_order_level
                        THEN 1 ELSE 0
                    END
                ) AS low
            FROM products p
        SQL);

        return response()->json([
            'pagination' => $pagination,
            'stats' => [
                'total' => (int) ($stats->total ?? 0),
                'newly' => (int) ($stats->newly ?? 0),
                'low' => (int) ($stats->low ?? 0),
            ],
        ]);
    }

    private function normalizeProductPicture(mixed $picture): mixed
    {
        if ($picture === null || $picture === '') {
            return null;
        }

        if (is_array($picture)) {
            return $picture;
        }

        if (!is_string($picture)) {
            return null;
        }

        if (str_starts_with($picture, 'data:image/')) {
            return $picture;
        }

        if (preg_match('#^https?://#i', $picture)) {
            return $picture;
        }

        // PNG
        if (strlen($picture) >= 8 && substr($picture, 0, 8) === "\x89PNG\r\n\x1a\n") {
            return 'data:image/png;base64,' . base64_encode($picture);
        }

        // JPEG
        if (strlen($picture) >= 3 && substr($picture, 0, 3) === "\xFF\xD8\xFF") {
            return 'data:image/jpeg;base64,' . base64_encode($picture);
        }

        // GIF
        if (str_starts_with($picture, 'GIF87a') || str_starts_with($picture, 'GIF89a')) {
            return 'data:image/gif;base64,' . base64_encode($picture);
        }

        $trimmed = trim($picture);

        if ($trimmed !== '' && ($trimmed[0] === '[' || $trimmed[0] === '{')) {
            $decoded = json_decode($trimmed, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded ?: null;
            }
        }

        if (preg_match('/\.(png|jpg|jpeg|webp|gif|bmp|svg)(\?.*)?$/i', $trimmed)) {
            return url('/' . ltrim($trimmed, '/'));
        }

        return null;
    }
}
