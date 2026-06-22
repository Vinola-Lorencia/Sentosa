<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number')->unique();
            $table->string('step')->default('greeting'); // tahap percakapan
            $table->json('cart')->nullable();            // isi keranjang
            $table->json('messages')->nullable();        // histori percakapan
            $table->string('customer_name')->nullable(); // nama pembeli
            $table->string('address')->nullable();       // alamat pengiriman
            $table->timestamp('last_activity')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_sessions');
    }
};