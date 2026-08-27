<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'product_name',
        'brand',
        'image',
        'image_path',
        'default_buy_price',
        'default_buy_discount',
        'sale_price',
        'sale_unit',
        'units_per_sale_unit',
        'purchase_unit',
        'dus_to_unit',
        'stock',
    ];

    public function purchaseOrderDetails()
    {
        return $this->hasMany(PurchaseOrderDetail::class, 'product_id', 'id');
    }
}