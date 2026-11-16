<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Monthly salary run with one slip per staff member (spec Phase 6). Finalized runs are locked.
        Schema::create('salary_runs', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->string('run_no', 30);
            $table->date('month');                                   // first day of the month
            $table->string('status', 10)->default('draft');          // SalaryRunStatus
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->userStamps();
            $table->timestamps();

            $table->unique(['farm_id', 'run_no']);
            $table->unique(['farm_id', 'month']);                    // one run per month
        });

        Schema::create('salary_slips', function (Blueprint $table) {
            $table->id();
            $table->farmScoped();
            $table->foreignId('salary_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_member_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('days_in_month');
            // attendance counts of the month (snapshot)
            $table->unsignedTinyInteger('present_days')->default(0);
            $table->unsignedTinyInteger('half_days')->default(0);
            $table->unsignedTinyInteger('leave_days')->default(0);
            $table->unsignedTinyInteger('holiday_days')->default(0);
            $table->unsignedTinyInteger('absent_days')->default(0);
            $table->decimal('payable_days', 5, 2);
            $table->decimal('base_salary', 12, 2);                   // monthly salary snapshot
            $table->decimal('earned_salary', 12, 2);                 // base × payable / days in month
            $table->decimal('bonus', 12, 2)->default(0);
            $table->decimal('advance_deduction', 12, 2)->default(0);
            $table->decimal('other_deduction', 12, 2)->default(0);
            $table->decimal('net_salary', 12, 2);                    // earned + bonus − deductions
            $table->string('note', 255)->nullable();
            $table->date('paid_on')->nullable();
            $table->foreignId('payment_mode_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete();
            $table->userStamps();
            $table->timestamps();

            $table->unique(['salary_run_id', 'staff_member_id']);
            $table->index(['staff_member_id', 'salary_run_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_slips');
        Schema::dropIfExists('salary_runs');
    }
};
