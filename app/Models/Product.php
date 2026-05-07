<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'product_name',
        'brand',
        'default_buy_price',
        'default_buy_discount',
        'sale_price',
        'sale_unit',
        'dus_to_unit',
        'stock'
    ];
}
