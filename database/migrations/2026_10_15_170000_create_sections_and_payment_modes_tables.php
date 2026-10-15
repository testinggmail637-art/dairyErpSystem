<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Business units used to tag expenses and reports (HERD, COLLECTION, DELIVERY, TRADING, OFFICE).
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->string('name', 100);
            $table->string('code', 20);
            $table->boolean('is_active')->default(true);
            $table->userStamps();
            $table->timestamps();

            $table->unique(['farm_id', 'code']);
        });

        Schema::create('payment_modes', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->string('name', 100);
            $table->string('code', 20);
            $table->boolean('is_active')->default(true);
            $table->userStamps();
            $table->timestamps();

            $table->unique(['farm_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_modes');
        Schema::dropIfExists('sections');
    }
};
