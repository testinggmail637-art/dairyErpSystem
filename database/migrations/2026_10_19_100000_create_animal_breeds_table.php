<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Cow and buffalo breeds per farm (spec Section 6, Phase 2). No delete: breeds are deactivated. */
    public function up(): void
    {
        Schema::create('animal_breeds', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->string('animal_type', 10);   // AnimalType
            $table->string('name', 60);
            $table->boolean('is_active')->default(true);
            $table->userStamps();
            $table->timestamps();

            $table->unique(['farm_id', 'animal_type', 'name']);
            $table->index(['farm_id', 'is_active', 'animal_type', 'name'], 'animal_breeds_farm_list_index'); // dropdowns + list
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animal_breeds');
    }
};
