<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_charts', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->string('name', 100);
            $table->string('milk_type', 20);                     // MilkType
            $table->string('method', 20);                        // RateMethod
            $table->decimal('fixed_rate', 10, 2)->nullable();    // ₹ per litre (fixed)
            $table->decimal('fat_rate', 10, 2)->nullable();      // ₹ per 1 fat point per litre
            $table->decimal('snf_rate', 10, 2)->nullable();      // ₹ per 1 SNF point per litre
            $table->decimal('min_fat', 5, 2)->nullable();
            $table->decimal('min_snf', 5, 2)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->userStamps();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['farm_id', 'milk_type', 'is_default', 'effective_from'], 'rate_charts_default_lookup');
        });

        Schema::create('rate_chart_slabs', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->foreignId('rate_chart_id')->constrained()->cascadeOnDelete();
            $table->decimal('fat_from', 5, 2);
            $table->decimal('fat_to', 5, 2);
            $table->decimal('snf_from', 5, 2)->nullable();
            $table->decimal('snf_to', 5, 2)->nullable();
            $table->decimal('rate_per_litre', 10, 2);
            $table->timestamps();

            $table->index(['rate_chart_id', 'fat_from']);
        });

        Schema::table('farmers', function (Blueprint $table) {
            $table->foreign('rate_chart_id')->references('id')->on('rate_charts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('farmers', fn (Blueprint $table) => $table->dropForeign(['rate_chart_id']));
        Schema::dropIfExists('rate_chart_slabs');
        Schema::dropIfExists('rate_charts');
    }
};
