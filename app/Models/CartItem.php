<?php

namespace App\Models;

use App\Services\UnitConverter;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'quantity',
        'unit',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Price per unit, computed live — never stored — via the same
     * UnitConverter::priceForUnit() the admin Sales form and checkout both
     * use. Reflects the product's CURRENT sale_price, so a price change
     * while an item sits in someone's cart is picked up automatically the
     * next time the cart is viewed or checked out.
     */
    public function getPriceAttribute(): float
    {
        return UnitConverter::priceForUnit(
            (float) $this->product->sale_price,
            $this->unit,
            (string) $this->product->sale_unit
        );
    }

    public function getSubtotalAttribute(): float
    {
        return round((float) $this->quantity * $this->price, 2);
    }
}
