<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'product_name',
        'sale_price',
        'sale_unit',
        'dus_to_unit',
        'stock',
    ];
}
