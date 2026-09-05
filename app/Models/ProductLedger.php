<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductLedger extends Model
{
    protected $connection = 'ledger';

    protected $fillable = [
        'product_id',
        'supplier_id',
        'customer_id',
        'source_type',
        'source_id',
        'source_item_id',
        'processed_actual_qty',
        'transaction_type',
        'date',
        'transaction_number',
        'reference_number',
        'entity_name',
        'quantity_in',
        'quantity_out',
        'junk',
        'balance_stock',
        'oum',
        'price',
        'cost',
        'remarks',
        'idempotency_key',
    ];

    /**
     * Purchase Order receipts must populate acquisition cost only.
     *
     * This guard protects every user type and every ProductLedger::create()
     * call. Even if an older route accidentally sends price or price_online,
     * those attributes are removed before the INSERT statement is generated.
     */
    protected static function booted(): void
    {
        static::creating(function (ProductLedger $ledger): void {
            if (!$ledger->isPurchaseOrderMovement()) {
                return;
            }

            $attributes = $ledger->getAttributes();

            unset(
                $attributes['price'],
                $attributes['price_online']
            );

            $ledger->setRawAttributes($attributes);
        });
    }

    private function isPurchaseOrderMovement(): bool
    {
        $sourceType = strtolower(trim((string) ($this->source_type ?? '')));
        $remarks = strtolower(trim((string) ($this->remarks ?? '')));

        return in_array($sourceType, ['purchase_order', 'purchase-order'], true)
            || str_contains($remarks, 'purchase order processing')
            || str_contains($remarks, 'purchase order update')
            || str_contains($remarks, 'purchase order /');
    }

    /**
     * Get the product for this ledger entry.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Get the supplier for this ledger entry.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
