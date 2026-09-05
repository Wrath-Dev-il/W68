<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaybillCargo extends Model
{
    protected $connection = 'sales';

    protected $table = 'waybill_cargo';

    protected $fillable = [
        'waybill_id',
        'quantity',
        'unit',
        'description',
    ];
}
