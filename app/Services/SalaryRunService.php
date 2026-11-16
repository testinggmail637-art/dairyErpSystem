<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\DocumentType;
use App\Enums\SalaryRunStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentMode;
use App\Models\SalaryRun;
use App\Models\SalarySlip;
use App\Models\Section;
use App\Models\StaffMember;
use App\Support\CurrentFarm;
use App\Support\Money;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Salary runs (spec Phase 6). One run per month: created as a draft with a slip per staff member employed that month,
 * computed from attendance — payable days = present + holiday + ½ × half day (+ leave when the farm setting
 * `salary_paid_leave` is on), earned = base × payable days ÷ days in month. Bonus and deductions are edited on the
 * draft; finalizing locks the run and its month's attendance and books one expense per slip (Labour › Salary,
 * section OFFICE, amount = earned + bonus). Advances recovered through finalized slips reduce the advance outstanding.
 */
class SalaryRunService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly StaffAdvanceService $advances,
        private readonly CurrentFarm $currentFarm,
    ) {}

    public function create(string $month): SalaryRun
    {
        $start = Carbon::parse($month.'-01')->startOfMonth();

        if (SalaryRun::query()->whereDate('month', $start)->exists()) {
            throw new DomainException(__('A salary run for :month already exists.', ['month' => $start->translatedFormat('F Y')]));
        }

        return DB::transaction(function () use ($start) {
            $run = SalaryRun::create([
                'run_no' => $this->numbers->next(DocumentType::Salary, $start->copy()->endOfMonth()),
                'month' => $start->toDateString(),
                'status' => SalaryRunStatus::Draft,
            ]);
            $this->fillSlips($run, true);

            return $run;
        });
    }

    /** Re-reads attendance and salaries of a draft (after attendance changes); adds newly employed staff. */
    public function recalculate(SalaryRun $run): void
    {
        $this->assertDraft($run);
        DB::transaction(fn () => $this->fillSlips($run, false));
    }

    /** @param  array{bonus: string, advance_deduction: string, other_deduction: string, note: ?string}  $data */
    public function updateSlip(SalarySlip $slip, array $data): SalarySlip
    {
        $this->assertDraft($slip->run);

        $outstanding = $this->advances->outstanding([$slip->staff_member_id])[$slip->staff_member_id];
        if (Money::compare($data['advance_deduction'], $outstanding) > 0) {
            throw new DomainException(__('Advance deduction cannot be more than the advance still to recover (:amount).', ['amount' => inr($outstanding)]));
        }

        $net = Money::sub(Money::add($slip->earned_salary, $data['bonus']), Money::add($data['advance_deduction'], $data['other_deduction']));
        if (Money::isNegative($net)) {
            throw new DomainException(__('Deductions cannot be more than the earned salary plus bonus.'));
        }

        DB::transaction(fn () => $slip->update($data + ['net_salary' => $net]));

        return $slip;
    }

    public function finalize(SalaryRun $run): void
    {
        DB::transaction(function () use ($run) {
            $run = SalaryRun::query()->lockForUpdate()->findOrFail($run->id);
            $this->assertDraft($run);

            $slips = $run->slips()->with('staffMember:id,name,designation')->get();
            $outstanding = $this->advances->outstanding($slips->pluck('staff_member_id')->all());

            $labour = ExpenseCategory::query()->whereNull('parent_id')->where('name', 'Labour')->firstOrFail();
            $salaryId = $labour->children()->where('name', 'Salary')->value('id');
            $sectionId = Section::query()->where('code', 'OFFICE')->value('id') ?? Section::query()->orderBy('id')->value('id');
            $cashId = PaymentMode::query()->where('code', 'CASH')->value('id') ?? PaymentMode::query()->orderBy('id')->value('id');
            $date = $run->month->copy()->endOfMonth()->min(today())->toDateString();

            foreach ($slips as $slip) {
                if (Money::compare($slip->advance_deduction, $outstanding[$slip->staff_member_id] ?? '0') > 0) {
                    throw new DomainException(__(':name: advance deduction is more than the advance still to recover.', ['name' => $slip->staffMember->name]));
                }

                $amount = Money::add($slip->earned_salary, $slip->bonus);
                if (Money::isZero($amount)) {
                    continue;
                }

                $expense = (new Expense)->forceFill(['source_type' => $slip->getMorphClass(), 'source_id' => $slip->id]);
                $expense->fill([
                    'expense_date' => $date,
                    'amount' => $amount,
                    'category_id' => $labour->id,
                    'subcategory_id' => $salaryId,
                    'section_id' => $sectionId,
                    'is_credit' => false,
                    'payment_mode_id' => $slip->payment_mode_id ?? $cashId,
                    'paid_to_text' => $slip->staffMember->name,
                    'note' => __('Salary :month (:run)', ['month' => $run->label(), 'run' => $run->run_no]),
                ])->save();
                $slip->update(['expense_id' => $expense->id]);
            }

            $run->update(['status' => SalaryRunStatus::Finalized, 'finalized_at' => now(), 'finalized_by' => auth()->id()]);
        });
    }

    /** Records that a finalized slip was paid; the slip's expense takes the payment mode. */
    public function pay(SalarySlip $slip, string $paidOn, int $paymentModeId): void
    {
        if (! $slip->run->isFinalized()) {
            throw new DomainException(__('Finalize the salary run before marking salaries paid.'));
        }

        DB::transaction(function () use ($slip, $paidOn, $paymentModeId) {
            $slip->update(['paid_on' => $paidOn, 'payment_mode_id' => $paymentModeId]);
            $slip->expense?->update(['payment_mode_id' => $paymentModeId]);
        });
    }

    public function delete(SalaryRun $run): void
    {
        $this->assertDraft($run);
        DB::transaction(fn () => $run->delete());
    }

    /** True when the month of $date has a finalized salary run (its attendance can no longer change). */
    public function isMonthLocked(string $date): bool
    {
        return SalaryRun::query()->whereDate('month', Carbon::parse($date)->startOfMonth())
            ->where('status', SalaryRunStatus::Finalized->value)->exists();
    }

    private function assertDraft(SalaryRun $run): void
    {
        if ($run->isFinalized()) {
            throw new DomainException(__('This salary run is finalized and can no longer be changed.'));
        }
    }

    /** Creates / refreshes the slip of every staff member employed in the run's month. */
    private function fillSlips(SalaryRun $run, bool $fresh): void
    {
        $start = $run->month->copy()->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $days = $start->daysInMonth;
        $paidLeave = (bool) $this->currentFarm->farm()?->setting('salary_paid_leave', false);

        $staff = StaffMember::query()
            ->where('joining_date', '<=', $end->toDateString())
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('is_active', true)->whereNull('leaving_date'))
                ->orWhere('leaving_date', '>=', $start->toDateString()))
            ->orderBy('name')->get(['id', 'monthly_salary']);

        $counts = $this->attendanceCounts($staff->pluck('id')->all(), $start->toDateString(), $end->toDateString());
        $existing = $fresh ? collect() : $run->slips()->get()->keyBy('staff_member_id');
        $outstanding = $this->advances->outstanding($staff->pluck('id')->all());

        foreach ($staff as $member) {
            $c = $counts[$member->id] ?? [];
            $n = fn (AttendanceStatus $s) => (int) ($c[$s->value] ?? 0);
            $payable = bcadd((string) ($n(AttendanceStatus::Present) + $n(AttendanceStatus::Holiday) + ($paidLeave ? $n(AttendanceStatus::Leave) : 0)),
                bcmul((string) $n(AttendanceStatus::HalfDay), '0.5', 2), 2);
            $earned = Money::round2(bcdiv(bcmul(Money::normalize($member->monthly_salary), $payable, 6), (string) $days, 6));

            $slip = $existing->get($member->id);
            $bonus = $slip?->bonus ?? '0.00';
            $other = $slip?->other_deduction ?? '0.00';
            // advance: by default recover all that is outstanding, never more than what is left to pay
            $room = Money::sub(Money::add($earned, $bonus), $other);
            $wanted = $slip ? $slip->advance_deduction : $outstanding[$member->id];
            $advance = Money::isNegative($room) ? '0.00' : $this->min($wanted, $outstanding[$member->id], $room);

            $fields = [
                'days_in_month' => $days,
                'present_days' => $n(AttendanceStatus::Present), 'half_days' => $n(AttendanceStatus::HalfDay),
                'leave_days' => $n(AttendanceStatus::Leave), 'holiday_days' => $n(AttendanceStatus::Holiday), 'absent_days' => $n(AttendanceStatus::Absent),
                'payable_days' => $payable,
                'base_salary' => Money::round2($member->monthly_salary),
                'earned_salary' => $earned,
                'bonus' => $bonus, 'other_deduction' => $other, 'advance_deduction' => $advance,
                'net_salary' => Money::sub($room, $advance),
            ];

            $slip ? $slip->update($fields) : $run->slips()->create($fields + ['staff_member_id' => $member->id]);
        }
    }

    /**
     * @param  list<int>  $staffIds
     * @return array<int, array<string, int>> staff id => status => days
     */
    private function attendanceCounts(array $staffIds, string $from, string $to): array
    {
        $out = [];
        DB::table('attendances')->whereIn('staff_member_id', $staffIds)->whereBetween('attendance_date', [$from, $to])
            ->groupBy('staff_member_id', 'status')->get(['staff_member_id', 'status', DB::raw('COUNT(*) AS days')])
            ->each(function ($r) use (&$out) {
                $out[$r->staff_member_id][$r->status] = (int) $r->days;
            });

        return $out;
    }

    private function min(string ...$values): string
    {
        return Collection::make($values)->reduce(fn ($carry, $v) => $carry === null || Money::compare($v, $carry) < 0 ? Money::round2($v) : $carry);
    }
}
