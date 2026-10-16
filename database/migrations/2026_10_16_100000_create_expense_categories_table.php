<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Two levels: parent_id null = category, set = subcategory (spec Section 6, Phase 1).
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->foreignId('parent_id')->nullable()->constrained('expense_categories')->restrictOnDelete();
            $table->string('name', 100);
            $table->string('code', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->userStamps();
            $table->timestamps();

            // NULL parent_id values never clash in a unique index, so top-level names are also checked in validation.
            $table->unique(['farm_id', 'parent_id', 'name']);
            $table->index(['farm_id', 'parent_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
