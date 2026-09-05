<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    protected $connection = 'masterlist';

    protected $fillable = [
        'customer_type_id',
        'name',
        'contact_number',
        'contact_person',
        'address',
        'tin',
        'pricing_remarks',
        'terms',
        'notes',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(CustomerType::class, 'customer_type_id');
    }

    public function bankAccount(): HasOne
    {
        return $this->hasOne(CustomerBankAccount::class);
    }
}
