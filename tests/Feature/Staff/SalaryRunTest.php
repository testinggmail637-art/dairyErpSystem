<?php

use App\Enums\RoleName;
use App\Enums\SalaryRunStatus;
use App\Models\Attendance;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\PaymentMode;
use App\Models\SalaryRun;
use App\Models\SalarySlip;
use App\Models\Section;
use App\Models\StaffAdvance;
use App\Models\StaffMember;
use App\Models\User;
use App\Services\StaffAdvanceService;
use App\Support\CurrentFarm;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    app(CurrentFarm::class)->set($this->farm);
    ExpenseCategory::seedDefaultsFor($this->farm); // Labour › Salary
    Section::seedDefaultsFor($this->farm);         // OFFICE
    PaymentMode::seedDefaultsFor($this->farm);     // CASH
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->cash = PaymentMode::query()->where('code', 'CASH')->firstOrFail();
    $this->raju = StaffMember::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Raju Pawar', 'monthly_salary' => '15000', 'joining_date' => '2025-01-01']);
    $this->actingAs($this->owner);
});

/** Marks days of November 2026: [day => status]. */
function markNovember(StaffMember $staff, array $days): void
{
    foreach ($days as $day => $status) {
        Attendance::create(['farm_id' => $staff->farm_id, 'staff_member_id' => $staff->id, 'attendance_date' => sprintf('2026-11-%02d', $day), 'status' => $status]);
    }
}

function novemberRun(): SalaryRun
{
    test()->post(route('salary-runs.store'), ['month' => '2026-11'])->assertSessionHasNoErrors();

    return SalaryRun::sole();
}

it('30-day month, 26 present + 4 absent, base 15,000 → earned 13,000; finalize creates a 13,000 expense — spec acceptance', function () {
    markNovember($this->raju, array_fill_keys(range(1, 26), 'present') + array_fill_keys(range(27, 30), 'absent'));

    $run = novemberRun();
    $slip = $run->slips()->sole();
    expect($run)->status->toBe(SalaryRunStatus::Draft)->run_no->not->toBeEmpty()
        ->and($slip)->days_in_month->toBe(30)->present_days->toBe(26)->absent_days->toBe(4)->payable_days->toBe('26.00')
        ->base_salary->toBe('15000.00')->earned_salary->toBe('13000.00')->net_salary->toBe('13000.00');

    $this->post(route('salary-runs.finalize', $run))->assertSessionHas('success');

    $expense = Expense::sole();
    expect($run->fresh()->status)->toBe(SalaryRunStatus::Finalized)
        ->and($expense)->amount->toBe('13000.00')->paid_to_text->toBe('Raju Pawar')->source_id->toBe($slip->id)
        ->and($expense->category->name)->toBe('Labour')->and($expense->subcategory?->name)->toBe('Salary')
        ->and($expense->section->code)->toBe('OFFICE')
        ->and($slip->fresh()->expense_id)->toBe($expense->id)
        ->and($expense->isAutoCreated())->toBeTrue();
});

it('counts half days as ½, holidays as paid and leave as unpaid unless the farm setting says so', function () {
    markNovember($this->raju, array_fill_keys(range(1, 20), 'present') + [21 => 'half_day', 22 => 'half_day', 23 => 'holiday', 24 => 'leave', 25 => 'leave']);

    $slip = novemberRun()->slips()->sole();
    expect($slip)->payable_days->toBe('22.00')->leave_days->toBe(2)->earned_salary->toBe('11000.00'); // 20 + 1 + 1

    $this->farm->update(['settings' => ['salary_paid_leave' => true]]);
    $this->post(route('salary-runs.recalculate', $slip->run))->assertSessionHas('success');
    expect($slip->fresh())->payable_days->toBe('24.00')->earned_salary->toBe('12000.00');
});

it('recovers the outstanding advance by default; edits bonus / deductions with a reason; finalize clears the advance', function () {
    markNovember($this->raju, array_fill_keys(range(1, 30), 'present'));
    StaffAdvance::create(['farm_id' => $this->farm->id, 'staff_member_id' => $this->raju->id, 'advance_date' => '2026-11-05', 'amount' => '2000', 'payment_mode_id' => $this->cash->id]);

    $run = novemberRun();
    $slip = $run->slips()->sole();
    expect($slip)->earned_salary->toBe('15000.00')->advance_deduction->toBe('2000.00')->net_salary->toBe('13000.00');

    $this->put(route('salary-slips.update', $slip), ['bonus' => '1000', 'advance_deduction' => '1500', 'other_deduction' => '200'])->assertSessionHasErrors('reason');
    $this->put(route('salary-slips.update', $slip), ['bonus' => '1000', 'advance_deduction' => '2500', 'other_deduction' => '0', 'reason' => 'Try'])
        ->assertSessionHas('error'); // more than the advance outstanding
    $this->put(route('salary-slips.update', $slip), ['bonus' => '1,000', 'advance_deduction' => '1500', 'other_deduction' => '200', 'note' => 'Diwali', 'reason' => 'Diwali bonus'])
        ->assertRedirect(route('salary-runs.show', $run));
    expect($slip->fresh())->bonus->toBe('1000.00')->advance_deduction->toBe('1500.00')->net_salary->toBe('14300.00')->note->toBe('Diwali');

    expect(app(StaffAdvanceService::class)->outstanding([$this->raju->id])[$this->raju->id])->toBe('2000.00'); // draft does not count
    $this->post(route('salary-runs.finalize', $run));
    expect(app(StaffAdvanceService::class)->outstanding([$this->raju->id])[$this->raju->id])->toBe('500.00')
        ->and(Expense::sole()->amount)->toBe('16000.00'); // earned + bonus

    // the recovered advance can no longer be deleted
    $this->delete(route('staff-advances.destroy', StaffAdvance::sole()), ['reason' => 'Mistake'])->assertSessionHas('error');
    expect(StaffAdvance::count())->toBe(1);
});

it('locks a finalized run: no slip edit, no recalculate, no delete, and the month attendance cannot change; one run per month', function () {
    markNovember($this->raju, [1 => 'present']);
    $run = novemberRun();
    $this->post(route('salary-runs.store'), ['month' => '2026-11'])->assertSessionHas('error');
    $this->post(route('salary-runs.store'), ['month' => today()->addMonth()->format('Y-m')])->assertSessionHasErrors('month');

    $this->post(route('salary-runs.finalize', $run));
    $slip = $run->slips()->sole();

    $this->put(route('salary-slips.update', $slip), ['bonus' => '1', 'advance_deduction' => '0', 'other_deduction' => '0', 'reason' => 'Late'])->assertSessionHas('error');
    $this->get(route('salary-slips.edit', $slip))->assertRedirect(route('salary-runs.show', $run));
    $this->post(route('salary-runs.recalculate', $run))->assertSessionHas('error');
    $this->delete(route('salary-runs.destroy', $run))->assertSessionHas('error');
    $this->post(route('attendance.store'), ['date' => '2026-11-01', 'attendance' => [$this->raju->id => ['status' => 'absent']]])->assertSessionHas('error');
    $this->get(route('attendance.index', ['date' => '2026-11-01']))->assertSee('attendance can no longer change');
    expect(Attendance::sole()->status->value)->toBe('present')->and(SalaryRun::count())->toBe(1);

    // the salary expense is changed only through the run
    $this->get(route('expenses.edit', Expense::sole()))->assertForbidden();
});

it('includes only staff employed in the month; a draft run can be deleted', function () {
    StaffMember::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Joins December', 'joining_date' => '2026-12-01']);
    StaffMember::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Left In October', 'joining_date' => '2025-01-01', 'leaving_date' => '2026-10-31', 'is_active' => false]);
    StaffMember::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Left Mid November', 'joining_date' => '2025-01-01', 'leaving_date' => '2026-11-15', 'is_active' => false]);

    $run = novemberRun();
    expect($run->slips()->with('staffMember')->get()->pluck('staffMember.name')->sort()->values()->all())->toBe(['Left Mid November', 'Raju Pawar']);

    $this->delete(route('salary-runs.destroy', $run))->assertRedirect(route('salary-runs.index'));
    expect(SalaryRun::count())->toBe(0)->and(SalarySlip::count())->toBe(0);
});

it('marks a finalized slip paid (expense takes the mode); slip PDF; needs salary.manage; flat query count', function () {
    markNovember($this->raju, array_fill_keys(range(1, 30), 'present'));
    $run = novemberRun();
    $slip = $run->slips()->sole();
    $upi = PaymentMode::factory()->create(['farm_id' => $this->farm->id, 'name' => 'UPI']);

    $this->post(route('salary-slips.pay', $slip), ['paid_on' => '2026-11-16', 'payment_mode_id' => $upi->id])->assertSessionHas('error'); // draft
    $this->post(route('salary-runs.finalize', $run));
    $this->post(route('salary-slips.pay', $slip), ['paid_on' => '2026-11-16', 'payment_mode_id' => $upi->id])->assertSessionHas('success');
    expect($slip->fresh())->paid_on->toDateString()->toBe('2026-11-16')->payment_mode_id->toBe($upi->id)
        ->and(Expense::sole()->payment_mode_id)->toBe($upi->id);

    $this->get(route('salary-slips.pdf', $slip))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->get(route('salary-runs.show', $run))->assertOk()->assertSee('Raju Pawar')->assertSee('₹15,000.00');
    $this->get(route('salary-runs.index'))->assertOk()->assertSee('November 2026');

    $herd = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $this->actingAs($herd)->get(route('salary-runs.index'))->assertForbidden();
    $this->post(route('salary-runs.store'), ['month' => '2026-10'])->assertForbidden();

    $this->actingAs($this->owner);
    $count = function () use ($run) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('salary-runs.show', $run))->assertOk();

        return count(DB::getQueryLog());
    };
    $count();
    $few = $count();
    StaffMember::factory()->count(8)->create(['farm_id' => $this->farm->id])
        ->each(fn ($s) => SalarySlip::create(['farm_id' => $this->farm->id, 'salary_run_id' => $run->id, 'staff_member_id' => $s->id, 'days_in_month' => 30, 'payable_days' => '30',
            'base_salary' => '10000', 'earned_salary' => '10000', 'net_salary' => '10000', 'payment_mode_id' => $upi->id]));
    expect($count())->toBe($few);
});
