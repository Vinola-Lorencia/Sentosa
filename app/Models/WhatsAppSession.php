<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppSession extends Model
{
    protected $table = 'whatsapp_sessions';

    protected $fillable = [
        'phone_number',
        'step',
        'cart',
        'messages',
        'pending_clarification',
        'last_shown_products',
        'customer_name',
        'address',
        'payment_method',
        'last_activity',
    ];

    protected $casts = [
        'cart' => 'array',
        'messages' => 'array',
        'pending_clarification' => 'array',
        'last_shown_products' => 'array',
        'last_activity' => 'datetime',
    ];
}
