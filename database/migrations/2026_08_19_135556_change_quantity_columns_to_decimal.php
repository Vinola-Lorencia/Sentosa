<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite (used in tests) has dynamic column typing and already stores
        // decimal values fine in an "integer" column — nothing to alter there.
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE products ALTER COLUMN stock TYPE decimal(10,2) USING stock::decimal(10,2)');
        DB::statement('ALTER TABLE purchase_details ALTER COLUMN quantity TYPE decimal(10,2) USING quantity::decimal(10,2)');
        DB::statement('ALTER TABLE purchase_order_details ALTER COLUMN quantity TYPE decimal(10,2) USING quantity::decimal(10,2)');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE products ALTER COLUMN stock TYPE integer USING stock::integer');
        DB::statement('ALTER TABLE purchase_details ALTER COLUMN quantity TYPE integer USING quantity::integer');
        DB::statement('ALTER TABLE purchase_order_details ALTER COLUMN quantity TYPE integer USING quantity::integer');
    }
};
