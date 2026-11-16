<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Farm workers paid a monthly salary (spec Phase 6); optionally linked to a login user.
        Schema::create('staff_members', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->string('phone', 15)->nullable();
            $table->string('designation', 100);
            $table->decimal('monthly_salary', 12, 2);
            $table->date('joining_date');
            $table->date('leaving_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->userStamps();
            $table->timestamps();

            $table->unique(['farm_id', 'user_id']);
            $table->index(['farm_id', 'is_active', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_members');
    }
};
