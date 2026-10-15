<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Farm of the changed record (null for farm-independent records such as users and roles).
        Schema::table('activity_log', function (Blueprint $table) {
            $table->foreignId('farm_id')->nullable()->after('log_name')->constrained()->restrictOnDelete();
            $table->index(['farm_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['farm_id', 'created_at']);
            $table->dropConstrainedForeignId('farm_id');
        });
    }
};
