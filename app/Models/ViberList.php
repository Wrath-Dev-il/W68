<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ViberList extends Model
{
    protected $connection = 'purchase';

    protected $fillable = [
        'status',
        'supplier_id',
        'supplier_code',
        'supplier_name',
        'contact_no',
        'contact_person',
        'billing_address',
        'remarks',
        'created_by',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ViberListItem::class, 'viber_list_id');
    }
}
