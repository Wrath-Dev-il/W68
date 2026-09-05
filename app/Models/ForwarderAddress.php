<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForwarderAddress extends Model
{
    protected $connection = 'masterlist';

    protected $fillable = [
        'forwarder_id',
        'address',
    ];

    public function forwarder(): BelongsTo
    {
        return $this->belongsTo(Forwarder::class);
    }
}
