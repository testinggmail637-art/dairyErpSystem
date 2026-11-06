<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->string('loan_no', 30);                              // LOAN/2026-27/0001
            $table->string('loan_type', 30);                            // LoanType
            $table->string('lender_name', 150);
            $table->string('lender_phone', 15)->nullable();
            $table->string('account_no', 50)->nullable();
            $table->decimal('principal_amount', 12, 2);                 // overdraft: sanctioned limit
            $table->decimal('interest_rate', 5, 2);                     // % per year
            $table->string('repayment_method', 30)->nullable();         // RepaymentMethod; null for overdraft
            $table->unsignedSmallInteger('tenure_months')->nullable();
            $table->date('start_date');
            $table->date('first_due_date')->nullable();
            $table->decimal('emi_amount', 12, 2)->nullable();           // snapshot from LoanService
            $table->decimal('processing_fee', 12, 2)->default(0);
            $table->date('od_renewal_date')->nullable();
            $table->string('status', 30)->default('active');            // LoanStatus
            $table->date('closed_on')->nullable();
            $table->text('notes')->nullable();
            $table->userStamps();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['farm_id', 'loan_no']);
            $table->index(['farm_id', 'status']);
            $table->index(['farm_id', 'loan_type']);
        });

        Schema::create('loan_installments', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('installment_no');
            $table->date('due_date');
            $table->decimal('opening_principal', 12, 2);
            $table->decimal('emi_amount', 12, 2);
            $table->decimal('principal_part', 12, 2);
            $table->decimal('interest_part', 12, 2);
            $table->decimal('closing_principal', 12, 2);
            $table->timestamps();

            $table->unique(['loan_id', 'installment_no']);
            $table->index(['farm_id', 'due_date']);
        });

        Schema::create('loan_transactions', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->date('txn_date');
            $table->string('type', 30);                                 // LoanTxnType
            $table->decimal('amount', 12, 2);
            $table->decimal('principal_part', 12, 2)->default(0);       // snapshot of the split
            $table->decimal('interest_part', 12, 2)->default(0);
            $table->foreignId('payment_mode_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('reference_no', 50)->nullable();
            $table->foreignId('expense_id')->nullable()->constrained()->restrictOnDelete(); // interest / charge expense
            $table->uuid('client_uuid')->nullable();
            $table->text('note')->nullable();
            $table->userStamps();
            $table->timestamps();

            $table->unique(['farm_id', 'client_uuid']);
            $table->index(['farm_id', 'loan_id', 'txn_date']);
            $table->index(['farm_id', 'txn_date']);
        });

        Schema::create('loan_revisions', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->date('effective_date');
            $table->decimal('old_rate', 5, 2);
            $table->decimal('new_rate', 5, 2);
            $table->decimal('old_emi', 12, 2)->nullable();
            $table->decimal('new_emi', 12, 2)->nullable();
            $table->string('reason', 500);
            $table->userStamps();
            $table->timestamps();

            $table->index(['loan_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_revisions');
        Schema::dropIfExists('loan_transactions');
        Schema::dropIfExists('loan_installments');
        Schema::dropIfExists('loans');
    }
};
