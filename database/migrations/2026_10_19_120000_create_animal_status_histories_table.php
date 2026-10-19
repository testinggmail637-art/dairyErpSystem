<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Every change of an animal's status, stage or ownership (spec Section 6, Phase 2), written by AnimalObserver. */
    public function up(): void
    {
        Schema::create('animal_status_histories', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->foreignId('animal_id')->constrained()->restrictOnDelete();
            $table->string('field', 20);                 // status | lifecycle_stage | ownership_type
            $table->string('old_value', 20)->nullable();
            $table->string('new_value', 20);
            $table->date('changed_on');
            $table->string('reason', 500)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['animal_id', 'changed_on']);
            $table->index(['farm_id', 'changed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animal_status_histories');
    }
};
