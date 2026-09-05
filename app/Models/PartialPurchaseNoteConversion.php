<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartialPurchaseNoteConversion extends Model
{
    protected $connection = 'ledger';

    protected $fillable = [
        'source_purchase_note_id',
        'source_purchase_note_item_id',
        'new_purchase_note_id',
        'new_purchase_note_item_id',
        'product_ledger_id',
        'supplier_id',
        'transferred_quantity',
        'transferred_amount',
        'status',
    ];

    public function sourcePurchaseNote(): BelongsTo
    {
        return $this->belongsTo(PurchaseNote::class, 'source_purchase_note_id');
    }

    public function newPurchaseNote(): BelongsTo
    {
        return $this->belongsTo(PurchaseNote::class, 'new_purchase_note_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
