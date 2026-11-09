<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farmers', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->string('code', 20);                          // auto F-001 per farm
            $table->string('name', 150);
            $table->string('father_name', 150)->nullable();
            $table->string('phone', 15)->nullable();
            $table->string('village', 100)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('default_milk_type', 20);             // MilkType
            $table->unsignedBigInteger('rate_chart_id')->nullable(); // own rate chart; FK added with rate_charts (Task 4.2)
            $table->string('bank_account_name', 150)->nullable();
            $table->string('bank_account_no', 20)->nullable();
            $table->string('ifsc', 11)->nullable();
            $table->string('upi_id', 100)->nullable();
            $table->decimal('opening_balance', 12, 2)->default(0); // + = we owe the farmer
            $table->boolean('is_active')->default(true);
            $table->string('notes', 1000)->nullable();
            $table->userStamps();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['farm_id', 'code']);
            $table->index(['farm_id', 'name']);
            $table->index(['farm_id', 'phone']);
            $table->index(['farm_id', 'village']);
            $table->index('rate_chart_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farmers');
    }
};
