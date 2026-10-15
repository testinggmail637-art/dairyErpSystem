<?php

namespace App\Services;

use App\Enums\RoleName;
use DomainException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Roles & permission matrix (spec 5.3). The owner role is locked: it always has every permission and cannot be
 * renamed or deleted.
 */
class RoleService
{
    /** @param  list<string>  $permissions */
    public function create(string $name, array $permissions): Role
    {
        return DB::transaction(function () use ($name, $permissions) {
            $role = Role::create(['name' => $name, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);

            return $role;
        });
    }

    /** @param  list<string>  $permissions */
    public function update(Role $role, string $name, array $permissions): Role
    {
        $this->ensureNotLocked($role);

        return DB::transaction(function () use ($role, $name, $permissions) {
            // Seeded role names are referenced in code; only custom roles can be renamed.
            if (RoleName::tryFrom($role->name) === null) {
                $role->update(['name' => $name]);
            }

            $role->syncPermissions($permissions);

            return $role;
        });
    }

    public function delete(Role $role): void
    {
        $this->ensureNotLocked($role);

        if ($role->users()->exists()) {
            throw new DomainException(__('Remove this role from all users before deleting it.'));
        }

        $role->delete();
    }

    public static function isLocked(Role $role): bool
    {
        return $role->name === RoleName::Owner->value;
    }

    private function ensureNotLocked(Role $role): void
    {
        abort_if(self::isLocked($role), 403, __('The owner role is locked.'));
    }
}
