<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierLedgerItem extends Model
{
    protected $connection = 'ledger';

    protected $table = 'supplier_ledger_items';

    protected $fillable = [
        'source_purchase_order_item_id',
        'supplier_ledger_id',
        'product_id',
        'product_code',
        'unit',
        'description',
        'quantity',
        'actual_quantity',
        'unit_price',
        'discount_percent',
        'discount_amount',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'actual_quantity' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function supplierLedger(): BelongsTo
    {
        return $this->belongsTo(SupplierLedger::class, 'supplier_ledger_id');
    }
}
