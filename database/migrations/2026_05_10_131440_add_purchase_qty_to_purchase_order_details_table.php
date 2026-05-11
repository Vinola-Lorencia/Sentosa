<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_details', function (Blueprint $table) {

            $table->integer('purchase_qty')
                  ->default(0)
                  ->after('product_id');

        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_details', function (Blueprint $table) {

            $table->dropColumn('purchase_qty');

        });
    }
};