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
        Schema::create('receivables', function (Blueprint $table) {
            $table->id('receivable_id');
            $table->bigInteger('sale_id');
            $table->foreign('sale_id')->references('sale_id')->on('sales')->onDelete('cascade');
            $table->foreignId('customer_id')->nullable()->constrained('customers');
            $table->date('due_date');
            $table->decimal('amount', 12, 2);
            $table->string('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receivables');
    }
};
