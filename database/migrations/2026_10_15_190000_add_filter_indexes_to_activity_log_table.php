<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The audit log grows forever; every filter of Settings → Audit Log must use an index
     * (farm_id first: queries are "farm_id = ? OR farm_id IS NULL", newest id first).
     */
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->index(['farm_id', 'id'], 'activity_log_farm_id_id_index');
            $table->index(['farm_id', 'event', 'id'], 'activity_log_farm_event_index');
            $table->index(['farm_id', 'causer_id', 'id'], 'activity_log_farm_causer_index');
            $table->index(['farm_id', 'subject_type', 'id'], 'activity_log_farm_subject_index');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex('activity_log_farm_id_id_index');
            $table->dropIndex('activity_log_farm_event_index');
            $table->dropIndex('activity_log_farm_causer_index');
            $table->dropIndex('activity_log_farm_subject_index');
        });
    }
};
