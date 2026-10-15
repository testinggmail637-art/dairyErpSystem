<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\RoleRequest;
use App\Services\RoleService;
use App\Support\Permissions;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Settings → Roles: list roles and edit the permission matrix (grouped by module).
 */
class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roles) {}

    public function index(): View
    {
        return view('settings.roles.index', [
            'roles' => Role::query()->withCount(['users', 'permissions'])->orderBy('id')->get(),
            'totalPermissions' => count(Permissions::all()),
        ]);
    }

    public function create(): View
    {
        return view('settings.roles.form', $this->formData(new Role));
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $role = $this->roles->create($request->validated('name'), $request->validated('permissions', []));

        return redirect()->route('roles.index')->with('success', __('Role :name created.', ['name' => role_label($role->name)]));
    }

    public function edit(Role $role): View
    {
        return view('settings.roles.form', $this->formData($role->load('permissions')));
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $this->roles->update($role, $request->validated('name'), $request->validated('permissions', []));

        return redirect()->route('roles.index')->with('success', __('Role :name updated.', ['name' => role_label($role->name)]));
    }

    public function destroy(Role $role): RedirectResponse
    {
        try {
            $this->roles->delete($role);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('roles.index')->with('success', __('Role deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Role $role): array
    {
        return [
            'role' => $role,
            'locked' => $role->exists && RoleService::isLocked($role),
            'groups' => Permissions::GROUPS,
            'selected' => old('permissions', $role->exists ? $role->permissions->pluck('name')->all() : []),
        ];
    }
}
