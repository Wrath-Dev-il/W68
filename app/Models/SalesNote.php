<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesNote extends Model
{
    protected $connection = 'sales';

    protected $fillable = [
        'sales_number',
        'so_type',
        'customer_id',
        'customer_name',
        'order_date',
        'salesman',
        'prepared_by',
        'checked_by',
        'packed_by',
        'is_rush',
        'gross_total',
        'total_discount',
        'net_total',
        'status',
        'remarks',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SalesNoteItem::class, 'sales_note_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
