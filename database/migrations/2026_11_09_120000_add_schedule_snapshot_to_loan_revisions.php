<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A revision keeps the instalments it replaced, so deleting the prepayment that caused it restores the schedule. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_revisions', function (Blueprint $table) {
            $table->foreignId('loan_transaction_id')->nullable()->after('loan_id')->constrained()->restrictOnDelete(); // prepayment
            $table->string('kind', 30)->default('rate_change')->after('loan_transaction_id');                        // rate_change | prepayment
            $table->json('schedule_before')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('loan_revisions', function (Blueprint $table) {
            $table->dropForeign(['loan_transaction_id']);
            $table->dropColumn(['loan_transaction_id', 'kind', 'schedule_before']);
        });
    }
};
