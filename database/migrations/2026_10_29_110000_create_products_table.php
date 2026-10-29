<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->string('name', 60);
            $table->string('code', 10);
            $table->string('unit', 10)->default('litre');      // only raw milk in v1 (spec 2)
            $table->string('milk_type', 10);                   // MilkType
            $table->decimal('default_rate', 10, 2);            // ₹ per unit; customer rates override it (spec 6)
            $table->boolean('is_active')->default(true);
            $table->userStamps();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['farm_id', 'code']);
            $table->unique(['farm_id', 'name']);
            $table->index(['farm_id', 'is_active', 'milk_type'], 'products_farm_list_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
