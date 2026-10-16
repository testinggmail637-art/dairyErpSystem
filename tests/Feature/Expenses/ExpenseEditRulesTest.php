<?php

/*
| Spec 4.5 — edit rules for staff, and the audit reason on edit/delete (spec 4.4), for expenses.
| Herd staff have only expenses.create; the accountant has expenses.view/edit/delete.
*/

use App\Enums\RoleName;
use App\Models\Activity;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\PaymentMode;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    $this->herdStaff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create(['name' => 'Ramu Herd']);
    $this->otherStaff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();

    $this->category = ExpenseCategory::factory()->create(['farm_id' => $this->farm->id]);
    $this->section = Section::factory()->create(['farm_id' => $this->farm->id]);
    $this->mode = PaymentMode::factory()->create(['farm_id' => $this->farm->id]);
});

/** An expense entered by $user at $when. */
function entryBy(User $user, ?Carbon $when = null, string $payee = 'Payee'): Expense
{
    $t = test();
    Carbon::setTestNow($when ?? now());
    test()->actingAs($user);
    $expense = Expense::factory()->create([
        'farm_id' => $t->farm->id, 'category_id' => $t->category->id, 'section_id' => $t->section->id,
        'payment_mode_id' => $t->mode->id, 'paid_to_text' => $payee, 'expense_date' => ($when ?? now())->toDateString(),
    ]);
    Carbon::setTestNow();

    return $expense;
}

function editPayload(Expense $e, array $overrides = []): array
{
    return array_merge([
        'expense_date' => $e->expense_date->toDateString(), 'amount' => '999', 'category_id' => $e->category_id,
        'section_id' => $e->section_id, 'is_credit' => '0', 'payment_mode_id' => $e->payment_mode_id,
        'paid_to_text' => $e->paid_to_text, 'reason' => 'Typed the wrong amount',
    ], $overrides);
}

it('lets create-only staff edit and delete their OWN entry on the SAME day', function () {
    $mine = entryBy($this->herdStaff);

    $this->actingAs($this->herdStaff)->get(route('expenses.edit', $mine))->assertOk();
    $this->actingAs($this->herdStaff)->put(route('expenses.update', $mine), editPayload($mine))->assertSessionDoesntHaveErrors();
    expect($mine->fresh()->amount)->toBe('999.00');

    $this->actingAs($this->herdStaff)->delete(route('expenses.destroy', $mine), ['reason' => 'Duplicate'])->assertRedirect();
    expect(Expense::whereKey($mine->id)->exists())->toBeFalse();
});

it('refuses create-only staff on someone else\'s entry and on their own entry from an earlier day', function () {
    $others = entryBy($this->otherStaff);
    $yesterday = entryBy($this->herdStaff, now()->subDay());

    foreach ([$others, $yesterday] as $expense) {
        $this->actingAs($this->herdStaff)->get(route('expenses.edit', $expense))->assertForbidden();
        $this->actingAs($this->herdStaff)->put(route('expenses.update', $expense), editPayload($expense))->assertForbidden();
        $this->actingAs($this->herdStaff)->delete(route('expenses.destroy', $expense), ['reason' => 'x x x'])->assertForbidden();
    }

    expect(Expense::count())->toBe(2);
});

it('allows own entries on any day when the farm turns the same-day rule off', function () {
    $settings = $this->farm->settings ?? [];
    $settings['staff_same_day_edit_only'] = false;
    $this->farm->update(['settings' => $settings]);

    $lastWeek = entryBy($this->herdStaff, now()->subWeek());

    $this->actingAs($this->herdStaff)->put(route('expenses.update', $lastWeek), editPayload($lastWeek))->assertSessionDoesntHaveErrors();
    expect($lastWeek->fresh()->amount)->toBe('999.00');
});

it('lets users with expenses.edit / expenses.delete change any (unlocked) entry, any day', function () {
    $old = entryBy($this->herdStaff, now()->subMonth());

    $this->actingAs($this->accountant)->put(route('expenses.update', $old), editPayload($old))->assertSessionDoesntHaveErrors();
    $this->actingAs($this->accountant)->delete(route('expenses.destroy', $old), ['reason' => 'Wrong farm'])->assertRedirect();

    expect(Expense::count())->toBe(0);
});

it('shows create-only staff only their own changeable entries in the list', function () {
    entryBy($this->herdStaff, null, 'My entry today');
    entryBy($this->herdStaff, now()->subDays(2), 'My old entry');
    entryBy($this->otherStaff, null, 'Somebody else');

    $this->actingAs($this->herdStaff)->get(route('expenses.index'))
        ->assertOk()
        ->assertSee(__('My expense entries'))
        ->assertSee('My entry today')
        ->assertDontSee('My old entry')
        ->assertDontSee('Somebody else')
        ->assertDontSee(route('expenses.export'), false);

    $this->actingAs($this->herdStaff)->get(route('profile.edit'))->assertSee(route('expenses.index'), false); // menu item
});

it('requires a reason to edit or delete, and stores it in the audit log', function () {
    $expense = entryBy($this->herdStaff);

    $this->actingAs($this->accountant)->put(route('expenses.update', $expense), editPayload($expense, ['reason' => '']))
        ->assertSessionHasErrors('reason');
    $this->actingAs($this->accountant)->delete(route('expenses.destroy', $expense))
        ->assertSessionHasErrors('reason');

    $this->actingAs($this->accountant)->put(route('expenses.update', $expense), editPayload($expense));
    $this->actingAs($this->accountant)->delete(route('expenses.destroy', $expense), ['reason' => 'Paid by the owner personally']);

    $logs = Activity::where('subject_type', (new Expense)->getMorphClass())->where('subject_id', $expense->id)->get();
    expect($logs->firstWhere('event', 'updated')->reason())->toBe('Typed the wrong amount')
        ->and($logs->firstWhere('event', 'deleted')->reason())->toBe('Paid by the owner personally');
});

it('shows Edit only where allowed and explains the rule otherwise', function () {
    $yesterday = entryBy($this->herdStaff, now()->subDay());

    $this->actingAs($this->accountant)->get(route('expenses.show', $yesterday))->assertSee(route('expenses.edit', $yesterday), false);

    // the herd staff cannot even open someone's old entry (it is not theirs to change any more)
    $this->actingAs($this->herdStaff)->get(route('expenses.show', $yesterday))->assertForbidden();

    $viewer = User::factory()->forFarm($this->farm)->create();
    $viewer->givePermissionTo('expenses.view');
    $this->actingAs($viewer)->get(route('expenses.show', $yesterday))
        ->assertOk()
        ->assertDontSee(route('expenses.edit', $yesterday), false)
        ->assertSee(__('You can only change your own entries, on the day you made them.'));
});
