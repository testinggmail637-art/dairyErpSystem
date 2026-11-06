<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_adjustments', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->date('adjustment_date');
            $table->decimal('amount', 12, 2);           // + customer owes more, − discount / correction
            $table->string('reason', 500);
            $table->userStamps();
            $table->timestamps();

            $table->index(['farm_id', 'adjustment_date']);
            $table->index(['customer_id', 'adjustment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_adjustments');
    }
};
