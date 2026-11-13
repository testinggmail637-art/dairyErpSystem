<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatments', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->foreignId('animal_id')->constrained()->restrictOnDelete();
            $table->date('treatment_date');
            $table->string('diagnosis', 255);
            $table->string('medicine', 255)->nullable();
            $table->string('dose', 100)->nullable();
            $table->string('vet_name', 150)->nullable();
            $table->decimal('cost', 12, 2)->default(0);
            $table->date('milk_withdrawal_until')->nullable();
            $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete(); // "Also record as expense" (spec Phase 5)
            $table->string('notes', 1000)->nullable();
            $table->userStamps();
            $table->timestamps();

            $table->index(['farm_id', 'treatment_date']);
            $table->index(['farm_id', 'animal_id', 'treatment_date']);
            $table->index(['farm_id', 'milk_withdrawal_until']); // animals under withdrawal (milk entry warning, 5.8)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatments');
    }
};
