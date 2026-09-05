<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForwarderContact extends Model
{
    protected $connection = 'masterlist';

    protected $fillable = [
        'forwarder_id',
        'contact_number',
        'contact_person',
    ];

    public function forwarder(): BelongsTo
    {
        return $this->belongsTo(Forwarder::class);
    }
}
