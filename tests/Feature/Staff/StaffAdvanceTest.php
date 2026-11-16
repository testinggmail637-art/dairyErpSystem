<?php

use App\Enums\RoleName;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\PaymentMode;
use App\Models\StaffAdvance;
use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->mode = PaymentMode::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Cash']);
    $this->raju = StaffMember::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Raju Pawar']);
    $this->actingAs($this->owner);
});

function advancePayload(array $overrides = []): array
{
    return array_merge(['staff_member_id' => test()->raju->id, 'advance_date' => '2026-11-10', 'amount' => '2,000',
        'payment_mode_id' => test()->mode->id, 'note' => 'Festival'], $overrides);
}

it('records an advance (not an expense); a re-submit with the same uuid is not saved twice', function () {
    $uuid = (string) Str::uuid();

    $this->post(route('staff-advances.store'), advancePayload(['client_uuid' => $uuid]))
        ->assertRedirect(route('staff-advances.index', ['staff_member_id' => $this->raju->id]))->assertSessionHas('success', 'Advance of ₹2,000.00 saved.');
    $this->post(route('staff-advances.store'), advancePayload(['client_uuid' => $uuid]))->assertSessionHas('success', 'This advance was already recorded.');

    expect(StaffAdvance::sole())->farm_id->toBe($this->farm->id)->amount->toBe('2000.00')->note->toBe('Festival')
        ->and(Expense::count())->toBe(0);
});

it('validates staff (active, this farm), amount, date and mode', function () {
    $left = StaffMember::factory()->create(['farm_id' => $this->farm->id, 'is_active' => false]);
    $other = StaffMember::factory()->create();

    $this->post(route('staff-advances.store'), advancePayload(['staff_member_id' => $left->id, 'amount' => '0',
        'advance_date' => today()->addDay()->toDateString(), 'payment_mode_id' => '']))
        ->assertSessionHasErrors(['staff_member_id', 'amount', 'advance_date', 'payment_mode_id']);
    $this->post(route('staff-advances.store'), advancePayload(['staff_member_id' => $other->id]))->assertSessionHasErrors('staff_member_id');
    expect(StaffAdvance::count())->toBe(0);
});

it('lists with staff / date filters, totals and outstanding; deletes with a reason; needs salary.manage', function () {
    $this->post(route('staff-advances.store'), advancePayload());
    $this->post(route('staff-advances.store'), advancePayload(['amount' => '500', 'advance_date' => '2026-11-12']));
    $sunil = StaffMember::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Sunil More']);
    $this->post(route('staff-advances.store'), advancePayload(['staff_member_id' => $sunil->id, 'amount' => '1000']));

    $this->get(route('staff-advances.index', ['staff_member_id' => $this->raju->id]))->assertOk()
        ->assertSee('₹2,500.00')->assertSee('Still to recover from salary');
    $this->get(route('staff-advances.index', ['from' => '2026-11-11']))->assertSee('₹500.00')->assertDontSee('₹3,500.00');

    $advance = StaffAdvance::where('amount', '500')->sole();
    $this->delete(route('staff-advances.destroy', $advance))->assertSessionHasErrors('reason');
    $this->delete(route('staff-advances.destroy', $advance), ['reason' => 'Entered twice'])->assertSessionHas('success');
    expect(StaffAdvance::count())->toBe(2);

    $herd = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $this->actingAs($herd)->get(route('staff-advances.index'))->assertForbidden();
    $this->post(route('staff-advances.store'), advancePayload())->assertForbidden();

    $this->actingAs($this->owner);
    $count = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('staff-advances.index'))->assertOk();

        return count(DB::getQueryLog());
    };
    $count();
    $few = $count();
    foreach (range(1, 8) as $n) {
        $this->post(route('staff-advances.store'), advancePayload(['amount' => (string) $n]));
    }
    expect($count())->toBe($few);
});
