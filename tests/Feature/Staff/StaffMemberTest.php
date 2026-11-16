<?php

use App\Enums\RoleName;
use App\Models\Farm;
use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create(); // staff.view only
});

function staffPayload(array $overrides = []): array
{
    return array_merge(['name' => 'Raju Pawar', 'designation' => 'Milker', 'phone' => '98765 43210', 'monthly_salary' => '15,000',
        'joining_date' => '2026-04-01', 'leaving_date' => '', 'user_id' => '', 'is_active' => '1'], $overrides);
}

it('adds a staff member and edits the salary with a reason (audit-logged)', function () {
    $this->actingAs($this->owner)->post(route('staff.store'), staffPayload())
        ->assertRedirect(route('staff.index'))->assertSessionHas('success', 'Staff member Raju Pawar added.');

    $member = StaffMember::sole();
    expect($member)->farm_id->toBe($this->farm->id)->phone->toBe('9876543210')->monthly_salary->toBe('15000.00')
        ->joining_date->toDateString()->toBe('2026-04-01')->user_id->toBeNull()->is_active->toBeTrue();

    $this->put(route('staff.update', $member), staffPayload(['monthly_salary' => '16000']))->assertSessionHasErrors('reason');
    $this->put(route('staff.update', $member), staffPayload(['monthly_salary' => '16000', 'reason' => 'Raise from November']))->assertSessionHasNoErrors();
    expect($member->fresh()->monthly_salary)->toBe('16000.00')
        ->and(Activity::query()->where('subject_type', $member->getMorphClass())->where('subject_id', $member->id)->where('event', 'updated')->exists())->toBeTrue();
});

it('validates dates, phone and the linked login user (this farm, once)', function () {
    $farmUser = User::factory()->forFarm($this->farm)->create(['name' => 'Raju Login']);
    $otherFarmUser = User::factory()->forFarm()->create();
    $this->actingAs($this->owner);

    $this->post(route('staff.store'), staffPayload(['leaving_date' => '2026-03-01', 'phone' => '123', 'user_id' => $otherFarmUser->id, 'monthly_salary' => '-1']))
        ->assertSessionHasErrors(['leaving_date', 'phone', 'user_id', 'monthly_salary']);

    $this->post(route('staff.store'), staffPayload(['user_id' => $farmUser->id]))->assertSessionHasNoErrors();
    $this->post(route('staff.store'), staffPayload(['name' => 'Second', 'user_id' => $farmUser->id]))->assertSessionHasErrors('user_id');
    $this->get(route('staff.create'))->assertDontSee('Raju Login'); // already linked
});

it('lists active staff by default with search / status filters and a flat query count; only staff.manage changes', function () {
    StaffMember::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Raju Pawar', 'designation' => 'Milker',
        'user_id' => User::factory()->forFarm($this->farm)->create()->id]);
    StaffMember::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Sunil More', 'is_active' => false, 'leaving_date' => '2026-10-31']);
    StaffMember::factory()->create(['name' => 'Other Farm Worker']);

    $this->actingAs($this->accountant)->get(route('staff.index'))->assertOk()
        ->assertSee('Raju Pawar')->assertDontSee('Sunil More')->assertDontSee('Other Farm Worker')->assertDontSee('Add staff member');
    $this->get(route('staff.index', ['status' => 'inactive']))->assertSee('Sunil More')->assertSee('31-10-2026')->assertDontSee('Raju Pawar');
    $this->get(route('staff.index', ['status' => 'all', 'search' => 'milk']))->assertSee('Raju Pawar')->assertDontSee('Sunil More');
    $this->get(route('staff.create'))->assertForbidden();
    $this->post(route('staff.store'), staffPayload())->assertForbidden();
    $herd = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $this->actingAs($herd)->get(route('staff.index'))->assertForbidden();

    $this->actingAs($this->accountant);
    $count = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('staff.index'))->assertOk();

        return count(DB::getQueryLog());
    };
    $few = $count();
    StaffMember::factory()->count(10)->create(['farm_id' => $this->farm->id, 'user_id' => fn () => User::factory()->forFarm($this->farm)->create()->id]);
    expect($count())->toBe($few);
});
