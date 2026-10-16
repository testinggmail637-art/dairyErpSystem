<?php

use App\Enums\RoleName;
use App\Models\Activity;
use App\Models\Farm;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create(['name' => 'Malik Owner']);
});

function editUserPayload(User $user, array $overrides = []): array
{
    return array_merge([
        'name' => $user->name,
        'email' => $user->email,
        'phone' => $user->phone,
        'password' => '',
        'password_confirmation' => '',
        'is_active' => '1',
        'roles' => $user->getRoleNames()->all(),
        'farms' => [test()->farm->id],
        'reason' => 'Phone number was wrong',
    ], $overrides);
}

it('logs a user edit with old and new values, the editor and the reason', function () {
    $staff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create(['name' => 'Ramu']);

    $this->actingAs($this->owner)
        ->put(route('users.update', $staff), editUserPayload($staff, ['name' => 'Ramu Yadav', 'roles' => ['collection_operator']]))
        ->assertRedirect(route('users.index'));

    $edit = Activity::where('subject_id', $staff->id)->where('description', 'updated')->latest('id')->firstOrFail();

    expect($edit->causer->is($this->owner))->toBeTrue()
        ->and($edit->properties['old']['name'])->toBe('Ramu')
        ->and($edit->properties['attributes']['name'])->toBe('Ramu Yadav')
        ->and($edit->reason())->toBe('Phone number was wrong')
        ->and($edit->farm_id)->toBeNull();

    $access = Activity::where('subject_id', $staff->id)->where('description', 'access')->firstOrFail();

    expect($access->properties['old']['roles'])->toBe(['herd_staff'])
        ->and($access->properties['attributes']['roles'])->toBe(['collection_operator'])
        ->and($access->reason())->toBe('Phone number was wrong');
});

it('records a password reset without ever storing the password', function () {
    $staff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();

    $this->actingAs($this->owner)->put(route('users.update', $staff), editUserPayload($staff, [
        'password' => 'brandnew123', 'password_confirmation' => 'brandnew123',
    ]));

    $json = Activity::where('subject_id', $staff->id)->get()->toJson();

    expect($json)->toContain('"password"')
        ->not->toContain('brandnew123')
        ->not->toContain($staff->fresh()->password);
});

it('requires a reason to edit a user or edit/delete a role, but not to create', function () {
    $staff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $role = Role::create(['name' => 'temp_role', 'guard_name' => 'web']);

    $this->actingAs($this->owner)->put(route('users.update', $staff), editUserPayload($staff, ['reason' => 'ab']))
        ->assertSessionHasErrors('reason');
    $this->actingAs($this->owner)->put(route('roles.update', $role), ['name' => 'temp_role', 'permissions' => []])
        ->assertSessionHasErrors('reason');
    $this->actingAs($this->owner)->delete(route('roles.destroy', $role))
        ->assertSessionHasErrors('reason');

    $this->actingAs($this->owner)->post(route('roles.store'), ['name' => 'new_role', 'permissions' => ['dashboard.view']])
        ->assertSessionDoesntHaveErrors();
});

it('logs role permission changes and role deletion with the reason', function () {
    $role = Role::create(['name' => 'temp_role', 'guard_name' => 'web']);
    $role->givePermissionTo('dashboard.view');

    $this->actingAs($this->owner)->put(route('roles.update', $role), [
        'name' => 'temp_role', 'permissions' => ['dashboard.view', 'reports.view'], 'reason' => 'Needs reports',
    ]);

    $change = Activity::where('subject_id', $role->id)->where('description', 'permissions')->firstOrFail();
    expect($change->properties['old']['permissions'])->toBe(['dashboard.view'])
        ->and($change->properties['attributes']['permissions'])->toBe(['dashboard.view', 'reports.view'])
        ->and($change->reason())->toBe('Needs reports');

    $this->actingAs($this->owner)->delete(route('roles.destroy', $role), ['reason' => 'Merged into accountant']);

    $deleted = Activity::where('subject_id', $role->id)->where('event', 'deleted')->firstOrFail();
    expect($deleted->properties['old']['name'])->toBe('temp_role')
        ->and($deleted->reason())->toBe('Merged into accountant');
});

it('logs farm settings changes under that farm and shows them on the audit screen', function () {
    $this->actingAs($this->owner)->put(route('settings.farm.update'), [
        'name' => 'Gokul Dairy', 'settings' => array_merge(Farm::DEFAULT_SETTINGS, [
            'bill_due_days' => 12, 'allow_negative_milk_stock' => '0', 'staff_same_day_edit_only' => '1',
        ]),
    ])->assertSessionDoesntHaveErrors();

    $entry = Activity::where('subject_type', (new Farm)->getMorphClass())->latest('id')->firstOrFail();
    expect($entry->farm_id)->toBe($this->farm->id);

    $this->actingAs($this->owner)->get(route('audit-logs.index'))
        ->assertOk()
        ->assertSee('Malik Owner')
        ->assertSee('settings.bill due days:')
        ->assertSee('Gokul Dairy');
});

it('filters by user, record type, action and date, and hides other farms', function () {
    $staff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create(['name' => 'Ramu']);
    $this->actingAs($this->owner)->put(route('users.update', $staff), editUserPayload($staff, ['name' => 'Ramu Changed']));

    $otherFarm = Farm::factory()->create(['name' => 'Secret Farm']);
    $otherFarm->update(['name' => 'Secret Farm Renamed']); // logged under the other farm

    Activity::query()->update(['created_at' => Carbon::parse('2026-10-10 10:00', 'Asia/Kolkata')->utc()]);

    $this->actingAs($this->owner)->get(route('audit-logs.index'))
        ->assertSee('Ramu Changed')
        ->assertDontSee('Secret Farm Renamed');

    $this->actingAs($this->owner)->get(route('audit-logs.index', [
        'user' => $this->owner->id, 'model' => (new User)->getMorphClass(), 'event' => 'updated',
        'from' => '2026-10-10', 'to' => '2026-10-10',
    ]))->assertSee('Ramu Changed');

    $this->actingAs($this->owner)->get(route('audit-logs.index', ['from' => '2026-10-11']))
        ->assertDontSee('<span class="text-success">Ramu Changed</span>', false); // the name is still in the User filter list

    $this->actingAs($this->owner)->get(route('audit-logs.index', ['from' => '2026-10-12', 'to' => '2026-10-11']))
        ->assertSessionHasErrors('to');
});

it('is open to roles with audit_logs.view only', function () {
    $admin = User::factory()->forFarm($this->farm)->withRole(RoleName::Admin)->create();
    $accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();

    $this->actingAs($admin)->get(route('audit-logs.index'))->assertOk();
    $this->actingAs($accountant)->get(route('audit-logs.index'))->assertForbidden();
});
