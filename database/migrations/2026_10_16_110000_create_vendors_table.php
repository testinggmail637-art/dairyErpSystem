<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Suppliers we buy from on cash or credit (spec Section 6, Phase 1). Master data → soft deletes.
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->string('name', 150);
            $table->string('phone', 15)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('gstin', 15)->nullable();
            $table->decimal('opening_balance', 12, 2)->default(0); // amount we owe at the start (negative = advance paid)
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->userStamps();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['farm_id', 'name']);       // list order + name search (prefix)
            $table->index(['farm_id', 'phone']);
            $table->index(['farm_id', 'is_active', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
