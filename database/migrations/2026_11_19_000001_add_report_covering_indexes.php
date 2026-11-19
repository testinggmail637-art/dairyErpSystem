<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Phase 7.9: month-wise income / milk-flow totals on the dashboard and P&L reports read only these columns,
// so they run as index-only range scans instead of reading every row of the year.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->index(['farm_id', 'status', 'delivery_date', 'amount', 'delivered_qty'], 'deliveries_farm_status_date_totals_index');
        });
        Schema::table('milk_collections', function (Blueprint $table) {
            $table->index(['farm_id', 'quality_status', 'collection_date', 'quantity_litre', 'amount'], 'milk_collections_farm_quality_date_totals_index');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', fn (Blueprint $table) => $table->dropIndex('deliveries_farm_status_date_totals_index'));
        Schema::table('milk_collections', fn (Blueprint $table) => $table->dropIndex('milk_collections_farm_quality_date_totals_index'));
    }
};
