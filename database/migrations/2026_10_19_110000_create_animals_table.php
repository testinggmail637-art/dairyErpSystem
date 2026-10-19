<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Cows, buffaloes, calves and bulls of the farm (spec Section 6, Phase 2). Soft delete only. */
    public function up(): void
    {
        Schema::create('animals', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->string('tag_no', 30);
            $table->string('name', 60)->nullable();
            $table->string('animal_type', 10);                    // AnimalType
            $table->foreignId('breed_id')->nullable()->constrained('animal_breeds')->restrictOnDelete();
            $table->string('gender', 10);                         // AnimalGender
            $table->date('date_of_birth')->nullable();
            $table->boolean('dob_is_estimated')->default(false);
            $table->string('source', 20);                         // AnimalSource
            $table->foreignId('mother_id')->nullable()->constrained('animals')->restrictOnDelete();
            $table->string('sire_info', 150)->nullable();
            $table->string('ownership_type', 20);                 // OwnershipType
            $table->string('lifecycle_stage', 20);                // LifecycleStage
            $table->string('status', 20)->default('active');      // AnimalStatus
            $table->decimal('opening_value', 12, 2)->nullable();  // source = opening only
            $table->string('color_marks', 100)->nullable();
            $table->string('photo_path')->nullable();             // public disk
            $table->unsignedTinyInteger('lactation_number')->default(0);
            $table->date('status_changed_on')->nullable();
            $table->text('notes')->nullable();
            $table->userStamps();
            $table->timestamps();
            $table->softDeletes();

            // unique per farm, soft-deleted animals included (spec)
            $table->unique(['farm_id', 'tag_no']);
            // list filters (status first: the list opens on active animals) and the milk-entry grid
            $table->index(['farm_id', 'status', 'lifecycle_stage', 'animal_type'], 'animals_farm_filters_index');
            $table->index(['farm_id', 'gender', 'lifecycle_stage', 'status'], 'animals_farm_milking_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animals');
    }
};
