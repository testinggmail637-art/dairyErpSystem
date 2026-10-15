<?php

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;

function rolePermissions(RoleName $role): array
{
    return Role::findByName($role->value)->permissions->pluck('name')->sort()->values()->all();
}

it('seeds every permission and the six default roles', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Permission::count())->toBe(count(Permissions::all()))
        ->and(Role::pluck('name')->sort()->values()->all())
        ->toBe(['accountant', 'admin', 'collection_operator', 'delivery_boy', 'herd_staff', 'owner']);
});

it('gives each role its default permissions', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(rolePermissions(RoleName::Owner))->toHaveCount(count(Permissions::all()))
        ->and(rolePermissions(RoleName::Admin))->toHaveCount(count(Permissions::all()) - 3)
        ->and(rolePermissions(RoleName::Admin))->not->toContain('farms.manage', 'roles.manage', 'monitoring.view')
        ->and(rolePermissions(RoleName::Accountant))->toContain('dashboard.financial', 'bills.finalize', 'vendor_payments.edit', 'reports.financial', 'salary.manage')
        ->and(rolePermissions(RoleName::Accountant))->not->toContain('users.manage', 'animals.manage', 'collections.create')
        ->and(rolePermissions(RoleName::HerdStaff))->toContain('milk_production.create', 'expenses.create')
        ->and(rolePermissions(RoleName::HerdStaff))->not->toContain('milk_production.edit', 'expenses.view')
        ->and(rolePermissions(RoleName::CollectionOperator))
        ->toBe(['collections.create', 'collections.view', 'dashboard.view', 'farmers.view', 'milk_stock.view'])
        ->and(rolePermissions(RoleName::DeliveryBoy))
        ->toBe(['customer_payments.create', 'deliveries.update', 'deliveries.view']);
});

it('keeps the owner\'s permission changes when seeded again, but the owner role always gets everything', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    Role::findByName('herd_staff')->revokePermissionTo('feed.manage');
    Role::findByName('owner')->revokePermissionTo('reports.view');

    $this->seed(RolesAndPermissionsSeeder::class);

    expect(rolePermissions(RoleName::HerdStaff))->not->toContain('feed.manage')
        ->and(rolePermissions(RoleName::Owner))->toContain('reports.view')
        ->and(Role::count())->toBe(6);
});

it('gives the seeded owner user the owner role', function () {
    $this->seed(DatabaseSeeder::class);

    $owner = User::where('email', config('dairy.owner.email'))->firstOrFail();

    expect($owner->hasRole('owner'))->toBeTrue()
        ->and($owner->can('monitoring.view'))->toBeTrue();
});

it('lets permitted roles open the dashboard and forbids the others', function () {
    $herd = User::factory()->forFarm()->withRole(RoleName::HerdStaff)->create();
    $delivery = User::factory()->forFarm()->withRole(RoleName::DeliveryBoy)->create();

    $this->actingAs($herd)->get(route('dashboard'))->assertOk()->assertSee('<span>Dashboard</span>', false);
    $this->actingAs($delivery)->get(route('dashboard'))->assertForbidden();
});

it('sends each user to a page they may open after login', function () {
    $herd = User::factory()->forFarm()->withRole(RoleName::HerdStaff)->create();
    $delivery = User::factory()->forFarm()->withRole(RoleName::DeliveryBoy)->create();

    $this->actingAs($herd)->get(route('home'))->assertRedirect(route('dashboard'));
    $this->flushSession(); // a different person: actingAs() skips the login controller, which clears the session farm
    // No delivery screens exist yet, so the delivery boy lands on the profile page.
    $this->actingAs($delivery)->get(route('home'))->assertRedirect(route('profile.edit'));
});

it('lists unique permission names and rejects unknown ones', function () {
    expect(Permissions::all())->toBe(array_values(array_unique(Permissions::all())))
        ->and(Permissions::expand(['bills.*']))->toBe(['bills.view', 'bills.generate', 'bills.finalize', 'bills.cancel']);

    Permissions::expand(['bills.approve']);
})->throws(InvalidArgumentException::class);
