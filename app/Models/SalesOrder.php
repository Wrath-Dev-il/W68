<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrder extends Model
{
    protected $connection = 'sales';

    protected $fillable = [
        'sales_note_id',
        'order_number',
        'customer_id',
        'customer_name',
        'invoice_numbers',
        'waybill_no',
        'waybill_date',
        'total_amount',
        'status',
        'remarks',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class, 'sales_order_id');
    }

    public function salesNote(): BelongsTo
    {
        return $this->belongsTo(SalesNote::class, 'sales_note_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
