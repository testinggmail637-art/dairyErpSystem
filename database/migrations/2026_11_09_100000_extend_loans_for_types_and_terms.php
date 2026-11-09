<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 3A redesign (spec §6, DECISIONS #152–#154): more loan terms, revolving / card fields, loan links on expenses. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->string('installment_frequency', 30)->default('monthly')->after('repayment_method'); // InstallmentFrequency
            $table->unsignedSmallInteger('moratorium_months')->default(0)->after('tenure_months');
            $table->string('moratorium_interest', 30)->nullable()->after('moratorium_months');         // MoratoriumInterest
            $table->decimal('broken_period_interest', 12, 2)->default(0)->after('first_due_date');
            $table->string('interest_debit_cycle', 30)->nullable()->after('od_renewal_date');          // revolving: InstallmentFrequency
            $table->unsignedTinyInteger('statement_day')->nullable()->after('interest_debit_cycle');   // credit card
            $table->unsignedTinyInteger('due_day')->nullable()->after('statement_day');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('loan_id')->nullable()->after('source_id')->constrained()->restrictOnDelete();        // loan cost (interest, fees)
            $table->foreignId('paid_by_card_id')->nullable()->after('loan_id')->constrained('loans')->restrictOnDelete(); // bought with this card
            $table->index(['farm_id', 'loan_id', 'expense_date']);
            $table->index(['farm_id', 'paid_by_card_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['loan_id']);
            $table->dropForeign(['paid_by_card_id']);
            $table->dropIndex(['farm_id', 'loan_id', 'expense_date']);
            $table->dropIndex(['farm_id', 'paid_by_card_id', 'expense_date']);
            $table->dropColumn(['loan_id', 'paid_by_card_id']);
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['installment_frequency', 'moratorium_months', 'moratorium_interest', 'broken_period_interest',
                'interest_debit_cycle', 'statement_day', 'due_day']);
        });
    }
};
