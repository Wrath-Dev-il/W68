<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Forwarder extends Model
{
    protected $connection = 'masterlist';

    protected $fillable = [
        'code',
        'name',
    ];

    public function contact(): HasOne
    {
        return $this->hasOne(ForwarderContact::class);
    }

    public function address(): HasOne
    {
        return $this->hasOne(ForwarderAddress::class);
    }
}
