<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class PurchaseNoteItem extends Model
{
    protected $connection = 'purchase';

    protected $fillable = [
        'purchase_note_id',
        'product_id',
        'product_code',
        'part_number',
        'description',
        'currency',
        'conversion_rate',
        'unit_price_converted',
        'total_price_converted',
        'quantity',
        'unit',
        'unit_price',
        'total_price',
        'force_closed_remaining_qty',
        'is_remaining_cancelled',
        'remaining_cancelled_at',
    ];

    public function scopeWithoutForceCancelled(Builder $query): Builder
    {
        if (!Schema::connection($this->connection)->hasColumn($this->getTable(), 'is_remaining_cancelled')) {
            return $query;
        }

        return $query->where(function (Builder $inner) {
            $inner->whereNull('is_remaining_cancelled')
                ->orWhere('is_remaining_cancelled', 0);
        });
    }

    /**
     * Get the purchase note that owns the item.
     */
    public function purchaseNote(): BelongsTo
    {
        return $this->belongsTo(PurchaseNote::class);
    }

    /**
     * Get the product for the item.
     * Note: This connects to the masterlist database.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
