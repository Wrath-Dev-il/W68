<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsignmentInvoice extends Model
{
    protected $connection = 'sales';

    protected $fillable = [
        'sales_note_id',
        'invoice_number',
        'customer_id',
        'customer_name',
        'total_amount',
        'status',
        'waybill_no',
        'waybill_date',
        'remarks',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ConsignmentInvoiceItem::class, 'consignment_invoice_id');
    }

    public function salesNote(): BelongsTo
    {
        return $this->belongsTo(SalesNote::class, 'sales_note_id');
    }
}
