<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dashboard "unbilled deliveries" total (spec 7.5): the open rows only, not a year of billed ones.
        Schema::table('deliveries', function (Blueprint $table) {
            $table->index(['farm_id', 'bill_id', 'status', 'amount'], 'deliveries_farm_unbilled_index');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropIndex('deliveries_farm_unbilled_index');
        });
    }
};
