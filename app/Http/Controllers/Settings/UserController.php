<?php

namespace App\Http\Controllers\Settings;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use App\Support\CurrentFarm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Settings → Users: users linked to the current farm. No hard delete (deactivate instead).
 */
class UserController extends Controller
{
    public function __construct(
        private readonly UserService $users,
        private readonly CurrentFarm $currentFarm,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $role = (string) $request->query('role');
        $status = (string) $request->query('status');

        $users = User::query()
            ->whereHas('farms', fn ($q) => $q->whereKey($this->currentFarm->id()))
            ->with('roles')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', '%'.(User::normalizePhone($search) ?: $search).'%')))
            ->when($role !== '', fn ($q) => $q->role($role))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('settings.users.index', [
            'users' => $users,
            'roles' => $this->roleOptions(),
            'filters' => compact('search', 'role', 'status'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('settings.users.form', $this->formData($request, new User(['is_active' => true])));
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = $this->users->create($request->validated());

        return redirect()->route('users.index')->with('success', __('User :name created.', ['name' => $user->name]));
    }

    public function edit(Request $request, User $user): View
    {
        $this->ensureInCurrentFarm($user);

        return view('settings.users.form', $this->formData($request, $user->load('roles', 'farms')));
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->ensureInCurrentFarm($user);

        $this->users->update($user, $request->validated(), $request->editorFarmIds());

        return redirect()->route('users.index')->with('success', __('User :name updated.', ['name' => $user->name]));
    }

    private function ensureInCurrentFarm(User $user): void
    {
        abort_unless($user->farms()->whereKey($this->currentFarm->id())->exists(), 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Request $request, User $user): array
    {
        $editorIsOwner = $request->user()->hasRole(RoleName::Owner->value);

        return [
            'user' => $user,
            // Only an owner may hand out the owner role.
            'roles' => collect($this->roleOptions())
                ->reject(fn ($label, $name) => $name === RoleName::Owner->value && ! $editorIsOwner && ! $user->hasRole($name))
                ->all(),
            'farms' => $this->currentFarm->availableFor($request->user()),
            'selectedRoles' => old('roles', $user->exists ? $user->roles->pluck('name')->all() : []),
            'selectedFarms' => array_map('intval', old('farms', $user->exists
                ? $user->farms->pluck('id')->all()
                : [$this->currentFarm->id()])),
        ];
    }

    /** @return array<string, string> role name => label */
    private function roleOptions(): array
    {
        return Role::query()->orderBy('id')->pluck('name')
            ->mapWithKeys(fn (string $name) => [$name => role_label($name)])
            ->all();
    }
}
