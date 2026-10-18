<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Money paid to vendors; reduces what we owe (spec Section 6, Phase 1). */
    public function up(): void
    {
        Schema::create('vendor_payments', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 12, 2);
            $table->foreignId('payment_mode_id')->constrained()->restrictOnDelete();
            $table->string('reference_no', 50)->nullable();
            $table->text('note')->nullable();
            $table->uuid('client_uuid')->nullable()->unique(); // idempotent form submit (spec 4.7)
            $table->userStamps();
            $table->timestamps();

            // Ledger / balance: payments of one vendor by date, summed without reading rows.
            $table->index(['farm_id', 'vendor_id', 'payment_date', 'amount'], 'vendor_payments_farm_vendor_index');
            $table->index(['farm_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_payments');
    }
};
