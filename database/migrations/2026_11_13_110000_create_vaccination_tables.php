<?php

use App\Models\Farm;
use App\Models\VaccineType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vaccine_types', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->string('name', 100);
            $table->unsignedSmallInteger('interval_days')->nullable(); // set by the owner per local vet advice
            $table->string('notes', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->userStamps();
            $table->timestamps();

            $table->unique(['farm_id', 'name']);
        });

        Schema::create('animal_vaccinations', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->foreignId('animal_id')->constrained()->restrictOnDelete();
            $table->foreignId('vaccine_type_id')->constrained()->restrictOnDelete();
            $table->date('given_on');
            $table->date('next_due_on')->nullable();
            $table->decimal('cost', 12, 2)->default(0);
            $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->userStamps();
            $table->timestamps();

            $table->index(['farm_id', 'given_on']);
            $table->index(['farm_id', 'next_due_on']); // due / overdue (reminders, 5.7)
            $table->index(['farm_id', 'animal_id', 'vaccine_type_id', 'given_on'], 'animal_vaccinations_animal_index');
        });

        // installs that already have farms get the default vaccines now; fresh installs get them from the seeder
        Farm::query()->withTrashed()->each(fn (Farm $farm) => VaccineType::seedDefaultsFor($farm));
    }

    public function down(): void
    {
        Schema::dropIfExists('animal_vaccinations');
        Schema::dropIfExists('vaccine_types');
    }
};
