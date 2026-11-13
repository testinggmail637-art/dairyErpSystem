<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('breeding_events', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->foreignId('animal_id')->constrained()->restrictOnDelete();
            $table->string('event_type', 20);                      // BreedingEventType
            $table->date('event_date');
            $table->string('sire_info', 150)->nullable();          // bull / semen straw
            $table->string('technician', 150)->nullable();
            $table->string('result', 10)->nullable();              // PregnancyResult, pregnancy_check only
            $table->date('expected_calving_date')->nullable();
            $table->decimal('cost', 12, 2)->default(0);
            $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->userStamps();
            $table->timestamps();

            $table->index(['farm_id', 'event_date']);
            $table->index(['farm_id', 'expected_calving_date']);
            $table->index(['farm_id', 'animal_id', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('breeding_events');
    }
};
