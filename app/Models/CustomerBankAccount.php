<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerBankAccount extends Model
{
    protected $connection = 'masterlist';

    protected $fillable = [
        'customer_id',
        'bank_code',
        'account_number',
        'bank_name',
        'bank_contact_number',
        'bank_contact_person',
        'bank_address',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
