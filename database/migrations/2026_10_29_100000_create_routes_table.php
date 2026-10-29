<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->string('name', 60);
            $table->foreignId('delivery_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->userStamps();
            $table->timestamps();

            $table->unique(['farm_id', 'name']);
            $table->index(['farm_id', 'is_active', 'sort_order', 'name'], 'routes_farm_list_index'); // list + dropdowns
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routes');
    }
};
