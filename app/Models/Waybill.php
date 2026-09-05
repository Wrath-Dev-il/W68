<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Waybill extends Model
{
    protected $connection = 'sales';

    protected $table = 'waybills';

    protected $fillable = [
        'waybill_sequence',
        'waybill_no',
        'waybill_date',
        'forwarder',
        'total_value',
        'declared_value',
        'remarks',
    ];
}
