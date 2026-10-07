<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopeeCredential extends Model
{
    protected $connection = 'mysql';

    protected $table = 'shopee_credentials';

    protected $fillable = [
        'shop_id',
        'access_token',
        'refresh_token',
        'access_token_expires_at',
        'refresh_token_expires_at',
        'authorization_expires_at',
        'authorized_at',
        'last_refreshed_at',
        'reauthorization_required',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'access_token_expires_at' => 'datetime',
            'refresh_token_expires_at' => 'datetime',
            'authorization_expires_at' => 'datetime',
            'authorized_at' => 'datetime',
            'last_refreshed_at' => 'datetime',
            'reauthorization_required' => 'boolean',
        ];
    }
}
