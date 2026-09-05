<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $connection = 'masterlist';

    protected $fillable = [
        'shopee_item_id',
        'product_code',
        'pricelist_code',
        'part_number',
        'category',
        'specification',
        'description',
        'date_added',
        'application',
        'position',
        'on_hand',
        'Re_order_level',
        'actual_qty',
        'status',
        'selling_price',
        'cost',
        'price_online',
        'Product_Picture',
        'is_selected_for_report',
        'unit',
    ];

    /**
     * Get the purchase order items for the product.
     */
    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'product_id');
    }

    /**
     * Get the ledger entries for the product.
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(ProductLedger::class, 'product_id');
    }

    /**
     * Get the price codes for the product.
     */
    public function priceCodes(): HasMany
    {
        return $this->hasMany(ProductPriceCode::class, 'product_id')->orderBy('sort_order');
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        // 'Product_Picture', // Removed so it can be sent to frontend
    ];
}
