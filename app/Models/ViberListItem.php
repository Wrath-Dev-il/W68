<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ViberListItem extends Model
{
    protected $connection = 'purchase';

    protected $fillable = [
        'viber_list_id',
        'product_id',
        'item_code',
        'part_no',
        'description',
        'application',
        'last_cost',
        'new_cost',
        'order_qty',
        'ordered_date',
        'remarks',
        'status',
        'purchase_note_id',
        'shipped_at',
        'currency_code',
        'is_rollback',
        'rollback_source_item_id',
        'rollbacked_at',
        'unit',
    ];
}
