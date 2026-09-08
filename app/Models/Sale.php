<?php

namespace App\Models;

use App\Services\UnitConverter;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $primaryKey = 'sale_id';

    protected $fillable = [
        'customer_id',
        'user_id',
        'sale_date',
        'payment_method',
        'payment_status',
        'paid_date',
        'total_amount',
        'is_new',
        'notes',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function details()
    {
        return $this->hasMany(SaleDetail::class, 'sale_id', 'sale_id');
    }

    public function receivable()
    {
        return $this->hasOne(Receivable::class, 'sale_id', 'sale_id');
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class, 'sale_id', 'sale_id');
    }

    /**
     * Total profit for this sale: sum over each line item of
     * (revenue) - (cost of goods sold, converted to the actual quantity
     * of base units/Buah sold). Requires 'details.product' to be eager
     * loaded — otherwise this N+1's per sale.
     */
    public function getLabaAttribute(): float
    {
        $laba = 0.0;

        foreach ($this->details as $detail) {
            $product = $detail->product;

            if (!$product) {
                continue;
            }

            $saleUnitFactor = UnitConverter::toBaseUnit(1, (string) $product->sale_unit, $product);

            if ($saleUnitFactor <= 0) {
                continue;
            }

            $hppPerBuah = ((float) $product->default_buy_price * (1 - (float) $product->default_buy_discount / 100))
                / $saleUnitFactor;

            $qtyBuah = UnitConverter::toBaseUnit((float) $detail->quantity, (string) $detail->unit, $product);

            $laba += (float) $detail->subtotal - ($hppPerBuah * $qtyBuah);
        }

        return $laba;
    }
}