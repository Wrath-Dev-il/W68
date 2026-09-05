<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnlineReport extends Model
{
    protected $connection = 'sales';

    protected $fillable = [
        'sales_note_ids',
        'created_by',
        'date_ranges',
        'prices',
        'counter_parts',
        'invoice_numbers',
        'addresses',
        'notes_data',
        'status',
    ];

    protected $casts = [
        'date_ranges' => 'array',
        'prices' => 'array',
        'counter_parts' => 'array',
        'invoice_numbers' => 'array',
        'addresses' => 'array',
        'notes_data' => 'array',
    ];
}
