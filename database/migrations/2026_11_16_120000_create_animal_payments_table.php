<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Money paid to / received from animal trading parties; installments = several rows (spec Phase 6).
        Schema::create('animal_payments', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->foreignId('party_id')->constrained('parties')->restrictOnDelete();
            $table->string('direction', 10);                       // PaymentDirection
            $table->date('payment_date');
            $table->decimal('amount', 12, 2);
            $table->foreignId('payment_mode_id')->constrained()->restrictOnDelete();
            $table->foreignId('animal_purchase_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('animal_sale_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('reference_no', 50)->nullable();
            $table->string('note', 500)->nullable();
            $table->uuid('client_uuid')->nullable()->unique();     // idempotent form submit (spec 4.7)
            $table->userStamps();
            $table->timestamps();

            $table->index(['farm_id', 'payment_date']);
            $table->index(['party_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animal_payments');
    }
};
