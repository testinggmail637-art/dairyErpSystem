<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farmer_advances', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->foreignId('farmer_id')->constrained()->restrictOnDelete();
            $table->date('advance_date');
            $table->decimal('amount', 12, 2);
            $table->foreignId('payment_mode_id')->constrained()->restrictOnDelete();
            $table->string('reference_no', 50)->nullable();
            $table->string('note', 500)->nullable();
            $table->uuid('client_uuid')->nullable()->unique(); // idempotent payment form (spec 4.7)
            $table->userStamps();
            $table->timestamps();

            $table->index(['farm_id', 'advance_date']);
            $table->index(['farm_id', 'farmer_id', 'advance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farmer_advances');
    }
};
