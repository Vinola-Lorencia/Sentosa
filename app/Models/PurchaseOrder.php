<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $primaryKey = 'po_id';

    public $timestamps = false; 

    protected $fillable = [
        'supplier_id',
        'po_date',
        'status',
        'notes'
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'id');
    }

    public function details()
    {
        return $this->hasMany(PurchaseOrderDetail::class, 'po_id');
    }
}
