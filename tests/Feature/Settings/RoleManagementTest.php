<?php

use App\Enums\RoleName;
use App\Models\User;
use App\Support\Permissions;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->owner = User::factory()->forFarm()->withRole(RoleName::Owner)->create();
});

it('lists roles with permission and user counts', function () {
    $this->actingAs($this->owner)->get(route('roles.index'))
        ->assertOk()
        ->assertSee('Herd Staff')
        ->assertSee(count(Permissions::all()).' / '.count(Permissions::all()));
});

it('creates a custom role from the permission matrix', function () {
    $this->actingAs($this->owner)
        ->post(route('roles.store'), ['name' => 'Milk_Supervisor', 'permissions' => ['milk_production.view', 'milk_stock.view']])
        ->assertRedirect(route('roles.index'));

    $role = Role::findByName('milk_supervisor');

    expect($role->permissions->pluck('name')->sort()->values()->all())->toBe(['milk_production.view', 'milk_stock.view']);
});

it('rejects bad role names and unknown permissions', function () {
    $this->actingAs($this->owner)
        ->post(route('roles.store'), ['name' => 'herd staff!', 'permissions' => ['bills.approve']])
        ->assertSessionHasErrors(['name', 'permissions.0']);

    $this->actingAs($this->owner)
        ->post(route('roles.store'), ['name' => 'admin'])
        ->assertSessionHasErrors('name');
});

it('edits the permissions of a seeded role but not its code', function () {
    $role = Role::findByName('herd_staff');

    $this->actingAs($this->owner)
        ->put(route('roles.update', $role), ['name' => 'renamed', 'permissions' => ['dashboard.view']])
        ->assertRedirect(route('roles.index'));

    $role->refresh();
    expect($role->name)->toBe('herd_staff')
        ->and($role->permissions->pluck('name')->all())->toBe(['dashboard.view']);
});

it('locks the owner role', function () {
    $owner = Role::findByName('owner');

    $this->actingAs($this->owner)->get(route('roles.edit', $owner))
        ->assertOk()
        ->assertSee(__('The owner role is locked: it always has every permission and cannot be changed.'));

    $this->actingAs($this->owner)->put(route('roles.update', $owner), ['name' => 'owner', 'permissions' => []])->assertForbidden();
    $this->actingAs($this->owner)->delete(route('roles.destroy', $owner))->assertForbidden();

    expect($owner->permissions()->count())->toBe(count(Permissions::all()));
});

it('deletes a role only when no user has it', function () {
    $empty = Role::create(['name' => 'temp_role', 'guard_name' => 'web']);
    $used = Role::findByName('herd_staff');
    User::factory()->create()->assignRole($used);

    $this->actingAs($this->owner)->delete(route('roles.destroy', $used))->assertSessionHas('error');
    $this->actingAs($this->owner)->delete(route('roles.destroy', $empty))->assertRedirect(route('roles.index'));

    expect(Role::where('name', 'temp_role')->exists())->toBeFalse()
        ->and(Role::where('name', 'herd_staff')->exists())->toBeTrue();
});

it('is forbidden for admins (no roles.manage)', function () {
    $admin = User::factory()->forFarm()->withRole(RoleName::Admin)->create();

    $this->actingAs($admin)->get(route('roles.index'))->assertForbidden();
    $this->actingAs($admin)->post(route('roles.store'), ['name' => 'x_role'])->assertForbidden();
});
