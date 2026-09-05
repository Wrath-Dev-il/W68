<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $connection = 'purchase';

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'product_code',
        'unit',
        'description',
        'quantity',
        'actual_quantity',
        'received_quantity',
        'unit_price',
        'subtotal',
        'actual_subtotal',
        'discount_percent',
        'discount_amount',
    ];

    /**
     * Get the purchase order that owns the item.
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * Get the product details from the masterlist.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
