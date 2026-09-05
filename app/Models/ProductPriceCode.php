<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPriceCode extends Model
{
    protected $connection = 'masterlist';

    protected $table = 'product_price_codes';

    protected $fillable = [
        'product_id',
        'price_code',
        'selling_price',
        'sort_order',
    ];
}
