<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsignmentInvoiceItem extends Model
{
    protected $connection = 'sales';

    protected $fillable = [
        'consignment_invoice_id',
        'product_id',
        'product_code',
        'description',
        'quantity',
        'additional_qty',
        'oum',
        'unit_price',
        'discount',
        'additional_discount',
        'subtotal',
        'particulars',
    ];

    public function consignmentInvoice(): BelongsTo
    {
        return $this->belongsTo(ConsignmentInvoice::class, 'consignment_invoice_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
