<?php

use App\Enums\RoleName;
use App\Models\Attendance;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\PaymentMode;
use App\Models\SalaryRun;
use App\Models\Section;
use App\Models\StaffMember;
use App\Models\User;
use App\Support\CurrentFarm;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    app(CurrentFarm::class)->set($this->farm);
    ExpenseCategory::seedDefaultsFor($this->farm);
    Section::seedDefaultsFor($this->farm);
    PaymentMode::seedDefaultsFor($this->farm);
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->raju = StaffMember::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Raju Pawar', 'monthly_salary' => '15000', 'joining_date' => '2025-01-01']);
    foreach ([1 => 'present', 2 => 'present', 3 => 'half_day', 4 => 'holiday', 5 => 'leave', 6 => 'absent'] as $day => $status) {
        Attendance::create(['farm_id' => $this->farm->id, 'staff_member_id' => $this->raju->id, 'attendance_date' => "2026-11-0{$day}", 'status' => $status]);
    }
    $this->actingAs($this->owner);
});

it('attendance register: a code per day, the counts and paid days', function () {
    $table = $this->get(route('reports.staff', ['attendance', 'month' => '2026-11']))->assertOk()
        ->assertSee('Attendance register')->assertSee('Raju Pawar')->viewData('table');

    expect($table->columns)->toHaveCount(1 + 30 + 6)
        ->and(array_slice($table->rows[0], 1, 7))->toBe(['P', 'P', 'H', 'HO', 'L', 'A', ''])
        ->and(array_slice($table->rows[0], 31))->toBe([2, 1, 1, 1, 1, '3.5']); // P H L HO A, paid = 2 + 1 + ½

    $this->get(route('reports.staff', ['attendance', 'month' => '2026-11', 'format' => 'excel']))->assertOk();
});

it('salary register: finalized runs only, with totals; needs salary.manage', function () {
    $this->post(route('salary-runs.store'), ['month' => '2026-11'])->assertSessionHasNoErrors();
    $this->get(route('reports.staff', ['salary', 'from' => '2026-11-01', 'to' => '2026-11-30']))->assertOk()->assertSee('No finalized salary runs');

    $this->post(route('salary-runs.finalize', SalaryRun::sole()));
    // earned = 15,000 × 3.5 ÷ 30 = 1,750
    $this->get(route('reports.staff', ['salary', 'from' => '2026-11-01', 'to' => '2026-11-30']))->assertOk()
        ->assertSee('Raju Pawar')->assertSee('₹1,750.00')->assertSee('Pending');
    $this->get(route('reports.staff', ['salary', 'format' => 'pdf']))->assertOk();
    $this->get(route('reports.index'))->assertSee('Attendance register')->assertSee('Salary register');

    $viewer = User::factory()->forFarm($this->farm)->create();
    $viewer->givePermissionTo(['reports.view', 'staff.view']);
    $this->actingAs($viewer)->get(route('reports.staff', 'attendance'))->assertOk();
    $this->get(route('reports.staff', 'salary'))->assertForbidden();
    $this->get(route('reports.index'))->assertSee('Attendance register')->assertDontSee('Salary register');
});

it('keeps the attendance register query count flat as staff grow', function () {
    $count = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('reports.staff', ['attendance', 'month' => '2026-11']))->assertOk();

        return count(DB::getQueryLog());
    };
    $count();
    $few = $count();
    StaffMember::factory()->count(10)->create(['farm_id' => $this->farm->id, 'joining_date' => '2025-01-01'])
        ->each(fn ($s) => Attendance::create(['farm_id' => $this->farm->id, 'staff_member_id' => $s->id, 'attendance_date' => '2026-11-02', 'status' => 'present']));
    expect($count())->toBe($few);
});
