<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Receivable extends Model
{
    protected $primaryKey = 'receivable_id';

    protected $fillable = [
        'sale_id',
        'customer_id',
        'due_date',
        'amount',
        'paid_amount',
        'remaining_amount',
        'status',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class, 'sale_id', 'sale_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function payments()
    {
        return $this->hasMany(ReceivablePayment::class, 'receivable_id', 'receivable_id');
    }
}