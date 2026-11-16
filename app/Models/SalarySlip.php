<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One staff member's salary in a run (spec Phase 6). Audit-logged; locked once the run is finalized. */
class SalarySlip extends Model
{
    use Auditable, BelongsToFarm, TracksUser;

    /** @var list<string> */
    protected $fillable = [
        'farm_id', 'salary_run_id', 'staff_member_id', 'days_in_month', 'present_days', 'half_days', 'leave_days', 'holiday_days',
        'absent_days', 'payable_days', 'base_salary', 'earned_salary', 'bonus', 'advance_deduction', 'other_deduction', 'net_salary',
        'note', 'paid_on', 'payment_mode_id', 'expense_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payable_days' => 'decimal:2',
            'base_salary' => 'decimal:2',
            'earned_salary' => 'decimal:2',
            'bonus' => 'decimal:2',
            'advance_deduction' => 'decimal:2',
            'other_deduction' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'paid_on' => 'date',
        ];
    }

    /** @return BelongsTo<SalaryRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(SalaryRun::class, 'salary_run_id');
    }

    /** @return BelongsTo<StaffMember, $this> */
    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    /** @return BelongsTo<PaymentMode, $this> */
    public function paymentMode(): BelongsTo
    {
        return $this->belongsTo(PaymentMode::class);
    }

    /** @return BelongsTo<Expense, $this> */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }
}
