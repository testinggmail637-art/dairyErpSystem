<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Milk of each own animal per shift (spec Section 6, Phase 2). No soft delete; milk type comes from the animal. */
    public function up(): void
    {
        Schema::create('milk_production_records', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->date('production_date');
            $table->string('shift', 10);                       // MilkShift
            $table->foreignId('animal_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_litre', 10, 2);          // 0 … 60
            $table->decimal('fat', 5, 2)->nullable();
            $table->decimal('snf', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->uuid('client_uuid')->nullable()->unique(); // idempotent single entry (spec 4.7)
            $table->userStamps();
            $table->timestamps();

            $table->unique(['animal_id', 'production_date', 'shift']);          // one entry per animal and shift (spec)
            $table->index(['farm_id', 'production_date', 'shift', 'animal_id'], 'milk_records_farm_date_index'); // day grid, entries list order, daily totals
            $table->index(['animal_id', 'shift', 'production_date', 'quantity_litre'], 'milk_records_animal_shift_index'); // hints, drop alerts, charts
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milk_production_records');
    }
};
