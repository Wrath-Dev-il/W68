<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnlineProduct extends Model
{
    protected $connection = 'masterlist';

    protected $fillable = [
        'product_id',
        'product_code',
        'name',
        'description',
        'sku',
        'price',
        'category',
        'image_url',
        'is_converted',
    ];
}
