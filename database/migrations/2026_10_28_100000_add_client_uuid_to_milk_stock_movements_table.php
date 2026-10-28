<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Usage and adjustment forms create movements directly, so they carry a client_uuid (spec 4.7). */
    public function up(): void
    {
        Schema::table('milk_stock_movements', function (Blueprint $table) {
            $table->uuid('client_uuid')->nullable()->unique()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('milk_stock_movements', function (Blueprint $table) {
            $table->dropUnique(['client_uuid']);
            $table->dropColumn('client_uuid');
        });
    }
};
