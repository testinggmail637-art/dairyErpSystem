<?php

use App\Enums\RoleName;
use App\Models\Farm;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create(['name' => 'Owner']);
});

function userPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ramu Kaka',
        'email' => '',
        'phone' => '+91 98765 11111',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'is_active' => '1',
        'roles' => ['herd_staff'],
        'farms' => [test()->farm->id],
        'reason' => 'Details corrected',
    ], $overrides);
}

it('creates a herd staff user who then sees only permitted menus and gets 403 elsewhere', function () {
    $this->actingAs($this->owner)
        ->post(route('users.store'), userPayload())
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

    $staff = User::where('phone', '9876511111')->firstOrFail();

    expect($staff->hasRole('herd_staff'))->toBeTrue()
        ->and($staff->farms->pluck('id')->all())->toBe([$this->farm->id]);

    // Staff logs in with the phone number.
    auth()->logout();
    $this->post(route('login.store'), ['login' => '9876511111', 'password' => 'secret123'])->assertRedirect(route('home'));

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('<span>Dashboard</span>', false)
        ->assertDontSee(route('users.index'), false)
        ->assertDontSee(route('settings.farm.edit'), false);

    $this->get(route('users.index'))->assertForbidden();
    $this->get(route('roles.index'))->assertForbidden();
    $this->get(route('settings.farm.edit'))->assertForbidden();
    $this->get(route('sections.index'))->assertForbidden();
});

it('lists the users of the current farm with search and filters', function () {
    User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create(['name' => 'Asha Accountant']);
    User::factory()->forFarm($this->farm)->inactive()->create(['name' => 'Old Worker']);
    User::factory()->forFarm()->create(['name' => 'Other Farm Person']);

    $this->actingAs($this->owner)->get(route('users.index'))
        ->assertOk()
        ->assertSee('Asha Accountant')
        ->assertSee('Old Worker')
        ->assertDontSee('Other Farm Person');

    $this->actingAs($this->owner)->get(route('users.index', ['role' => 'accountant']))
        ->assertSee('Asha Accountant')->assertDontSee('Old Worker');

    $this->actingAs($this->owner)->get(route('users.index', ['status' => 'inactive', 'search' => 'old']))
        ->assertSee('Old Worker')->assertDontSee('Asha Accountant');
});

it('requires an email or a phone, unique values and a confirmed password', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs($this->owner)
        ->post(route('users.store'), userPayload(['phone' => '', 'email' => '']))
        ->assertSessionHasErrors(['email', 'phone']);

    $this->actingAs($this->owner)
        ->post(route('users.store'), userPayload(['email' => 'Taken@Example.com', 'password_confirmation' => 'nope']))
        ->assertSessionHasErrors(['email', 'password']);

    $this->actingAs($this->owner)
        ->post(route('users.store'), userPayload(['phone' => '12345']))
        ->assertSessionHasErrors('phone');
});

it('updates a user, keeps the password when left blank and resets it when given', function () {
    $user = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create(['phone' => '9000000001']);
    $oldHash = $user->password;

    $this->actingAs($this->owner)
        ->put(route('users.update', $user), userPayload([
            'name' => 'New Name', 'phone' => '9000000001', 'password' => '', 'password_confirmation' => '',
            'roles' => ['collection_operator'],
        ]))
        ->assertRedirect(route('users.index'));

    $user->refresh();
    expect($user->name)->toBe('New Name')
        ->and($user->password)->toBe($oldHash)
        ->and($user->getRoleNames()->all())->toBe(['collection_operator']);

    config(['session.driver' => 'database']); // phpunit.xml uses the array driver
    DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    $this->actingAs($this->owner)
        ->put(route('users.update', $user), userPayload(['phone' => '9000000001', 'password' => 'newpass99', 'password_confirmation' => 'newpass99']));

    expect(password_verify('newpass99', $user->fresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();
});

it('cannot deactivate itself and must keep one active owner', function () {
    $this->actingAs($this->owner)
        ->put(route('users.update', $this->owner), userPayload([
            'name' => 'Owner', 'phone' => '', 'email' => $this->owner->email, 'roles' => ['owner'], 'is_active' => '0',
            'password' => '', 'password_confirmation' => '',
        ]))
        ->assertSessionHasErrors(['is_active', 'roles']);

    $this->actingAs($this->owner)
        ->put(route('users.update', $this->owner), userPayload([
            'name' => 'Owner', 'phone' => '', 'email' => $this->owner->email, 'roles' => ['admin'],
            'password' => '', 'password_confirmation' => '',
        ]))
        ->assertSessionHasErrors(['roles' => __('At least one active owner must remain.')]);

    expect($this->owner->fresh()->hasRole('owner'))->toBeTrue();
});

it('stops an admin from creating owners or editing an owner', function () {
    $admin = User::factory()->forFarm($this->farm)->withRole(RoleName::Admin)->create();

    $this->actingAs($admin)->get(route('users.create'))
        ->assertOk()
        ->assertDontSee('id="role_owner"', false);

    $this->actingAs($admin)
        ->post(route('users.store'), userPayload(['roles' => ['owner']]))
        ->assertSessionHasErrors(['roles' => __('Only an owner can give the owner role.')]);

    $this->actingAs($admin)
        ->put(route('users.update', $this->owner), userPayload(['roles' => ['owner']]))
        ->assertForbidden();
});

it('only links farms the editor belongs to and keeps the others', function () {
    $hiddenFarm = Farm::factory()->create();
    $admin = User::factory()->forFarm($this->farm)->withRole(RoleName::Admin)->create();
    $user = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create(['phone' => '9000000002']);
    $user->farms()->attach($hiddenFarm);

    $this->actingAs($admin)
        ->post(route('users.store'), userPayload(['farms' => [$hiddenFarm->id]]))
        ->assertSessionHasErrors('farms.0');

    $this->actingAs($admin)
        ->put(route('users.update', $user), userPayload(['phone' => '9000000002', 'password' => '', 'password_confirmation' => '']))
        ->assertRedirect(route('users.index'));

    expect($user->farms()->pluck('farms.id')->sort()->values()->all())
        ->toBe(collect([$this->farm->id, $hiddenFarm->id])->sort()->values()->all());
});

it('returns 404 for a user of another farm', function () {
    $stranger = User::factory()->forFarm()->create();

    $this->actingAs($this->owner)->get(route('users.edit', $stranger))->assertNotFound();
});

it('stops an admin from giving a role with permissions the admin does not have', function () {
    $admin = User::factory()->forFarm($this->farm)->withRole(RoleName::Admin)->create();
    $strong = Role::create(['name' => 'farm_manager', 'guard_name' => 'web']);
    $strong->givePermissionTo(['farms.manage', 'dashboard.view']);
    $weak = Role::create(['name' => 'helper', 'guard_name' => 'web']);
    $weak->givePermissionTo(['dashboard.view']);

    $this->actingAs($admin)->post(route('users.store'), userPayload(['roles' => ['farm_manager']]))->assertSessionHasErrors('roles');
    $this->actingAs($admin)->put(route('users.update', $admin), userPayload(['name' => $admin->name, 'phone' => $admin->phone, 'roles' => ['admin', 'farm_manager']]))
        ->assertSessionHasErrors('roles');
    expect($admin->fresh()->hasRole('farm_manager'))->toBeFalse();

    $this->actingAs($admin)->post(route('users.store'), userPayload(['roles' => ['helper']]))->assertSessionHasNoErrors();
    $this->actingAs($this->owner)->post(route('users.store'), userPayload(['phone' => '9876522222', 'roles' => ['farm_manager']]))->assertSessionHasNoErrors();
});
