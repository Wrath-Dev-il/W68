<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesNoteItem extends Model
{
    protected $connection = 'sales';

    protected $fillable = [
        'sales_note_id',
        'product_id',
        'price_code',
        'product_code',
        'description',
        'quantity',
        'additional_qty',
        'oum',
        'unit_price',
        'discount',
        'bonus',
        'subtotal',
        'particulars',
    ];

    public function salesNote(): BelongsTo
    {
        return $this->belongsTo(SalesNote::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
