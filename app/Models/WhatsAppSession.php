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
        'customer_name',
        'address',
        'last_activity',
    ];

    protected $casts = [
        'cart'          => 'array',
        'messages'      => 'array',
        'last_activity' => 'datetime',
    ];
}
