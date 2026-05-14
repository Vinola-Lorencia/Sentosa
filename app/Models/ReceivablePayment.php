<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceivablePayment extends Model
{
    protected $primaryKey = 'payment_id';

    protected $fillable = [
        'receivable_id',
        'amount',
        'payment_date',
        'notes',
    ];

    public function receivable()
    {
        return $this->belongsTo(Receivable::class, 'receivable_id', 'receivable_id');
    }
}