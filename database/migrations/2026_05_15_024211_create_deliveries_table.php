<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id('delivery_id');
            $table->foreignId('sale_id')->constrained('sales', 'sale_id')->onDelete('cascade');
            $table->date('delivery_date');
            $table->enum('type', ['Ambil Sendiri', 'Dikirim']);
            $table->enum('status', ['Pending', 'Dikirim'])->default('Pending');
            $table->string('recipient_name');
            $table->string('recipient_phone')->nullable();
            $table->text('address')->nullable();
            $table->string('expedition')->nullable();
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};