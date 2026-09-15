<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 10, 2);
            $table->string('unit');
            $table->timestamps();

            // Mirrors the old session cart's key strategy ("product_{id}_
            // {unit}") — one row per user+product+unit combination, so the
            // same product in two different units stays as two separate
            // lines (see UnitPriceConversionTest for why: price now varies
            // per chosen unit, so there's no single unit two such lines
            // could be merged into).
            $table->unique(['user_id', 'product_id', 'unit']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
