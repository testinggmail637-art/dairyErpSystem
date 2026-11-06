<?php

use App\Enums\RoleName;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * One-time data step for installs that already exist (spec Phase 3A): the role seeder gives new permissions only to
 * the owner and to roles it creates, and categories are seeded only for new farms. Fresh installs get the same from
 * the seeders, so this is a no-op there (nothing to update yet).
 */
return new class extends Migration
{
    private const PERMISSIONS = ['loans.view', 'loans.manage', 'loan_payments.create', 'loan_payments.edit'];

    public function up(): void
    {
        if (! DB::table('roles')->exists()) {
            return; // fresh install: RolesAndPermissionsSeeder and FarmMasterDataSeeder do it
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach ([RoleName::Owner, RoleName::Admin, RoleName::Accountant] as $roleName) {
            Role::query()->where('name', $roleName->value)->where('guard_name', 'web')->first()?->givePermissionTo(self::PERMISSIONS);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Farm::query()->withTrashed()->each(fn (Farm $farm) => ExpenseCategory::seedDefaultsFor($farm));
    }

    public function down(): void
    {
        // Permissions and categories stay; they are harmless without the loans module.
    }
};
