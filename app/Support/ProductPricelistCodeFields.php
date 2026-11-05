<?php

namespace App\Support;

use Illuminate\Http\Request;

class ProductPricelistCodeFields
{
    public const ALL = ['pricelist_code', 'pricelist_code2'];

    /**
     * Resolve the pricelist columns the user wants to search.
     */
    public static function selected(Request $request): array
    {
        $raw = $request->input('pricelist_code_fields');

        if ($raw === null) {
            $raw = $request->query('pricelist_code_fields');
        }

        if (is_string($raw)) {
            $raw = [$raw];
        }

        if (!is_array($raw)) {
            return self::ALL;
        }

        $fields = array_values(array_intersect(self::ALL, $raw));

        return $fields ?: self::ALL;
    }

    /**
     * Search a term across one or both pricelist columns.
     */
    public static function like($query, string $term, array $fields)
    {
        $fields = $fields ?: self::ALL;
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);

        return $query->where(function ($nested) use ($fields, $escaped) {
            foreach ($fields as $field) {
                $nested->orWhere($field, 'like', '%'.$escaped.'%');
            }
        });
    }

    /**
     * Match a full pricelist code across one or both pricelist columns.
     */
    public static function exact($query, string $code, array $fields)
    {
        $fields = $fields ?: self::ALL;
        $code = trim($code);

        return $query->where(function ($nested) use ($fields, $code) {
            foreach ($fields as $field) {
                $nested->orWhereRaw("TRIM({$field}) = ?", [$code]);
            }
        });
    }
}
