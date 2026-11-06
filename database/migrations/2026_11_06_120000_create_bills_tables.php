<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->string('bill_no', 30)->nullable();               // assigned on finalize
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->date('bill_date');
            $table->date('due_date');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('previous_balance', 12, 2)->default(0);    // snapshots, set on finalize
            $table->decimal('payments_in_period', 12, 2)->default(0);
            $table->decimal('adjustments_in_period', 12, 2)->default(0);
            $table->decimal('closing_balance', 12, 2)->default(0);
            $table->string('status', 30)->default('draft');            // BillStatus
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('cancel_reason', 500)->nullable();
            $table->string('pdf_path', 255)->nullable();
            $table->string('notes', 500)->nullable();
            $table->userStamps();
            $table->timestamps();

            $table->unique(['farm_id', 'bill_no']);
            $table->index(['farm_id', 'bill_date']);
            $table->index(['farm_id', 'status', 'bill_date']);
            $table->index(['customer_id', 'period_from']);
        });

        Schema::create('bill_items', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->foreignId('bill_id')->constrained()->restrictOnDelete();
            $table->foreignId('delivery_id')->nullable()->unique()->constrained()->restrictOnDelete(); // null after cancel
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->date('item_date');
            $table->string('shift', 30)->nullable();                   // snapshot for the day-wise bill table
            $table->string('description', 150);
            $table->decimal('quantity', 10, 2);
            $table->decimal('rate', 10, 2);
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->index(['bill_id', 'item_date']);
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->foreign('bill_id')->references('id')->on('bills')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropForeign(['bill_id']);
        });
        Schema::dropIfExists('bill_items');
        Schema::dropIfExists('bills');
    }
};
