<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calvings', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->foreignId('mother_id')->constrained('animals')->restrictOnDelete();
            $table->date('calving_date');
            $table->string('outcome', 20);                        // CalvingOutcome
            $table->string('calf_gender', 10)->nullable();        // AnimalGender
            $table->foreignId('calf_id')->nullable()->constrained('animals')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->userStamps();
            $table->timestamps();

            $table->unique(['mother_id', 'calving_date']);        // one calving a day (twins: add the 2nd calf as an animal)
            $table->index(['farm_id', 'calving_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calvings');
    }
};
