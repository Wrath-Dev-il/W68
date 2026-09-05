<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderItem extends Model
{
    protected $connection = 'sales';

    protected $fillable = [
        'sales_order_id',
        'product_id',
        'price_code',
        'product_code',
        'description',
        'quantity',
        'actual_qty',
        'additional_qty',
        'oum',
        'unit_price',
        'discount',
        'additional_discount',
        'subtotal',
        'particulars',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
