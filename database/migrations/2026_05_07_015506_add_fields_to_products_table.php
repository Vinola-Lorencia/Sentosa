<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {

            $table->string('brand')
                  ->nullable()
                  ->after('product_name');

            $table->decimal('default_buy_price', 10, 2)
                  ->default(0)
                  ->after('brand');

            $table->decimal('default_buy_discount', 10, 2)
                  ->default(0)
                  ->after('default_buy_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {

            $table->dropColumn([
                'brand',
                'default_buy_price',
                'default_buy_discount'
            ]);
        });
    }
};
