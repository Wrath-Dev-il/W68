<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierLedger extends Model
{
    protected $connection = 'ledger';

    protected $fillable = [
        'source_purchase_order_id',
        'supplier_id',
        'date',
        'transaction_code',
        'module_type',
        'title',
        'credit_amount',
        'debit_amount',
        'reference_no',
    ];

    protected $casts = [
        'date' => 'date',
        'credit_amount' => 'decimal:2',
        'debit_amount' => 'decimal:2',
    ];

    /**
     * Get the supplier for this ledger entry.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /**
     * Get the item movements for this supplier ledger entry.
     */
    public function items(): HasMany
    {
        return $this->hasMany(SupplierLedgerItem::class, 'supplier_ledger_id');
    }
}
