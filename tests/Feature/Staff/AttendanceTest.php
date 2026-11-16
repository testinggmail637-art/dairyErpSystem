<?php

use App\Enums\AttendanceStatus;
use App\Enums\RoleName;
use App\Models\Attendance;
use App\Models\Farm;
use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->raju = StaffMember::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Raju Pawar', 'joining_date' => '2026-01-01']);
    $this->sunil = StaffMember::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Sunil More', 'joining_date' => '2026-01-01']);
    $this->actingAs($this->owner);
});

it('shows staff employed on the date: joined by then, not left before; inactive without a leaving date hidden', function () {
    StaffMember::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Not Joined Yet', 'joining_date' => '2026-11-10']);
    StaffMember::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Already Left', 'joining_date' => '2026-01-01', 'leaving_date' => '2026-10-31', 'is_active' => false]);
    StaffMember::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Switched Off', 'joining_date' => '2026-01-01', 'is_active' => false]);
    StaffMember::factory()->create(['name' => 'Other Farm']);

    $this->get(route('attendance.index', ['date' => '2026-11-05']))->assertOk()
        ->assertSee('Raju Pawar')->assertSee('Sunil More')
        ->assertDontSee('Not Joined Yet')->assertDontSee('Already Left')->assertDontSee('Switched Off')->assertDontSee('Other Farm');
    $this->get(route('attendance.index', ['date' => '2026-10-15']))->assertSee('Already Left'); // past days before leaving stay editable
});

it('saves the whole day, updates on re-save and removes rows set back to not marked', function () {
    $this->post(route('attendance.store'), ['date' => '2026-11-05', 'attendance' => [
        $this->raju->id => ['status' => 'present', 'note' => ''],
        $this->sunil->id => ['status' => 'half_day', 'note' => 'Left at noon'],
    ]])->assertRedirect(route('attendance.index', ['date' => '2026-11-05']))->assertSessionHas('success');

    expect(Attendance::count())->toBe(2)
        ->and(Attendance::where('staff_member_id', $this->sunil->id)->sole())->status->toBe(AttendanceStatus::HalfDay)->note->toBe('Left at noon')
        ->farm_id->toBe($this->farm->id);

    $this->post(route('attendance.store'), ['date' => '2026-11-05', 'attendance' => [
        $this->raju->id => ['status' => 'absent'],
        $this->sunil->id => ['status' => ''],
    ]]);
    expect(Attendance::count())->toBe(1)->and(Attendance::sole()->status)->toBe(AttendanceStatus::Absent);

    $this->get(route('attendance.index', ['date' => '2026-11-05']))->assertSee('value="absent" data-status="absent" checked', false);
});

it('rejects future dates, unknown statuses and staff of other farms', function () {
    $other = StaffMember::factory()->create();

    $this->post(route('attendance.store'), ['date' => today()->addDay()->toDateString(), 'attendance' => [$this->raju->id => ['status' => 'present']]])
        ->assertSessionHasErrors('date');
    $this->post(route('attendance.store'), ['date' => '2026-11-05', 'attendance' => [$this->raju->id => ['status' => 'sick']]])
        ->assertSessionHasErrors('attendance.'.$this->raju->id.'.status');
    $this->post(route('attendance.store'), ['date' => '2026-11-05', 'attendance' => [$other->id => ['status' => 'present']]])->assertSessionHasNoErrors();

    expect(Attendance::withoutGlobalScopes()->count())->toBe(0);
});

it('needs attendance.manage; flat query count on the bulk screen', function () {
    $accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create(); // staff.view, no attendance.manage
    $this->actingAs($accountant)->get(route('attendance.index'))->assertForbidden();
    $this->post(route('attendance.store'), ['date' => '2026-11-05', 'attendance' => []])->assertForbidden();

    $this->actingAs($this->owner);
    $count = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('attendance.index', ['date' => '2026-11-05']))->assertOk();

        return count(DB::getQueryLog());
    };
    $count(); // warm the permission cache
    $few = $count();
    StaffMember::factory()->count(15)->create(['farm_id' => $this->farm->id, 'joining_date' => '2026-01-01'])
        ->each(fn ($s) => Attendance::create(['staff_member_id' => $s->id, 'attendance_date' => '2026-11-05', 'status' => 'present']));
    expect($count())->toBe($few);
});
