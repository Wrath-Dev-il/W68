<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrder extends Model
{
    protected $connection = 'purchase';

    protected $fillable = [
        'receiving_number',
        'po_number',
        'supplier_invoice_number',
        'supplier_id',
        'date',
        'expected_delivery_date',
        'reference_number',
        'total_amount',
        'actual_total_amount',
        'status',
        'remarks',
        'currency',
        'conversion_rate',
        'additional_discount_percent',
        'additional_discount_amount',
    ];

    /**
     * Get the items for the purchase order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * Get the supplier for the purchase order.
     * Connects to the masterlist database.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
