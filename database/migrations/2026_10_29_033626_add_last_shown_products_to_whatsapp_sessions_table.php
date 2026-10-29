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
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            // IDs (max 5) of the products the bot last showed in a chat reply,
            // so a follow-up like "pesan 2 lusin" knows which product is meant.
            $table->json('last_shown_products')->nullable()->after('pending_clarification');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->dropColumn('last_shown_products');
        });
    }
};
