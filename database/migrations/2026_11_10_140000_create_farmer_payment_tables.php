<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farmer_payment_cycles', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->string('cycle_no', 30);                       // FPAY/2026-27/0001
            $table->date('from_date');
            $table->date('to_date');
            $table->string('status', 20);                         // CycleStatus
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->userStamps();
            $table->timestamps();

            $table->unique(['farm_id', 'cycle_no']);
            $table->index(['farm_id', 'from_date', 'to_date']);
        });

        Schema::create('farmer_payments', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->foreignId('farmer_payment_cycle_id')->constrained()->restrictOnDelete();
            $table->foreignId('farmer_id')->constrained()->restrictOnDelete();
            $table->decimal('total_qty', 10, 2);
            $table->decimal('avg_fat', 5, 2)->nullable();
            $table->decimal('avg_snf', 5, 2)->nullable();
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('bonus', 12, 2)->default(0);
            $table->decimal('advance_deduction', 12, 2)->default(0);
            $table->decimal('other_deduction', 12, 2)->default(0);
            $table->string('deduction_note', 255)->nullable();
            $table->decimal('net_payable', 12, 2);
            $table->string('status', 20);                         // FarmerPaymentStatus
            $table->date('paid_on')->nullable();
            $table->foreignId('payment_mode_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('reference_no', 50)->nullable();
            $table->userStamps();
            $table->timestamps();

            $table->unique(['farmer_payment_cycle_id', 'farmer_id']);
            $table->index(['farm_id', 'farmer_id']);
            $table->index(['farm_id', 'status']);
        });

        Schema::table('milk_collections', function (Blueprint $table) {
            $table->foreign('farmer_payment_id')->references('id')->on('farmer_payments')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('milk_collections', fn (Blueprint $table) => $table->dropForeign(['farmer_payment_id']));
        Schema::dropIfExists('farmer_payments');
        Schema::dropIfExists('farmer_payment_cycles');
    }
};
