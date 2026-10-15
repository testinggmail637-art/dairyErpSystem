<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * All permissions (spec 5.1) and the default roles (spec 5.2). Safe to run again:
 * - new permissions are created; the owner always gets every permission;
 * - other roles get their defaults only when the role is created, so the owner's edits in the Roles screen survive.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (RoleName::cases() as $roleName) {
            $role = Role::query()->where('name', $roleName->value)->where('guard_name', 'web')->first();

            if ($role === null) {
                Role::create(['name' => $roleName->value, 'guard_name' => 'web'])
                    ->syncPermissions($roleName->defaultPermissions());
            } elseif ($roleName === RoleName::Owner) {
                $role->syncPermissions(Permissions::all());
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
