<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 12, 2);
            $table->foreignId('payment_mode_id')->constrained()->restrictOnDelete();
            $table->string('reference_no', 50)->nullable();
            $table->foreignId('collected_by')->constrained('users')->restrictOnDelete();
            $table->uuid('client_uuid')->nullable()->unique(); // idempotent form submit (spec 4.7)
            $table->string('note', 500)->nullable();
            $table->userStamps();
            $table->timestamps();

            $table->index(['farm_id', 'payment_date']);
            $table->index(['customer_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_payments');
    }
};
