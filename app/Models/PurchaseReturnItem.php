<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReturnItem extends Model
{
    protected $connection = 'purchase';

    protected $fillable = [
        'purchase_return_id',
        'product_id',
        'product_code',
        'description',
        'quantity',
        'out_quantity',
        'oum',
        'unit_price',
        'discount',
        'subtotal',
    ];

    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
