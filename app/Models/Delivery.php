<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $primaryKey = 'delivery_id';

    protected $fillable = [
        'sale_id',
        'delivery_date',
        'type',
        'status',
        'recipient_name',
        'recipient_phone',
        'address',
        'expedition',
        'shipping_cost',
        'colly',
        'notes',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class, 'sale_id', 'sale_id');
    }
}