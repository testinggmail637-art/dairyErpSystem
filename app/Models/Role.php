<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Spatie role with audit logging (config/permission.php points here). Permission changes are logged
 * by RoleService, because syncing permissions does not change the role row itself.
 */
class Role extends SpatieRole
{
    use Auditable;

    /**
     * Spatie's role has no $fillable; the audit log records fillable attributes only.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'guard_name'];
}
