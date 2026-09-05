<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesNoteConvertedItem extends Model
{
    protected $connection = 'sales';

    protected $fillable = [
        'source_sales_note_id',
        'source_sales_note_item_id',
        'target_sales_note_id',
        'sales_order_id',
        'sales_order_item_id',
        'product_id',
        'converted_qty',
        'converted_amount',
    ];

    public function sourceSalesNote(): BelongsTo
    {
        return $this->belongsTo(SalesNote::class, 'source_sales_note_id');
    }

    public function targetSalesNote(): BelongsTo
    {
        return $this->belongsTo(SalesNote::class, 'target_sales_note_id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function salesOrderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class, 'sales_order_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
