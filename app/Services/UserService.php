<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates and updates users from Settings → Users (spec 5.3). Users are never hard-deleted; they are deactivated.
 */
class UserService
{
    /**
     * @param  array{name: string, email: ?string, phone: ?string, password: string, is_active: bool, roles: list<string>, farms: list<int>}  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'is_active' => $data['is_active'],
            ]);

            $user->syncRoles($data['roles']);
            $user->farms()->sync($data['farms']);

            $this->logAccessChange($user, [], [], false);

            return $user;
        });
    }

    /**
     * Farms the editor cannot see are kept as they are; only the editor's own farms are added/removed.
     *
     * @param  array{name: string, email: ?string, phone: ?string, password?: ?string, is_active: bool, roles: list<string>, farms: list<int>}  $data
     * @param  list<int>  $editorFarmIds
     */
    public function update(User $user, array $data, array $editorFarmIds): User
    {
        return DB::transaction(function () use ($user, $data, $editorFarmIds) {
            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'is_active' => $data['is_active'],
            ]);

            $passwordChanged = filled($data['password'] ?? null);

            if ($passwordChanged) {
                $user->password = $data['password'];
                $user->setRememberToken(Str::random(60));
            }

            $oldRoles = $user->getRoleNames()->sort()->values()->all();
            $oldFarms = $user->farms()->pluck('farms.id')->sort()->values()->all();

            $user->save();

            $user->syncRoles($data['roles']);

            $hiddenFarmIds = $user->farms()->pluck('farms.id')->diff($editorFarmIds);
            $user->farms()->sync($hiddenFarmIds->merge($data['farms'])->unique()->values()->all());

            $this->logAccessChange($user, $oldRoles, $oldFarms, $passwordChanged);

            // A reset password or a deactivation signs the user out everywhere.
            if ($passwordChanged || ! $user->is_active) {
                $this->endSessions($user);
            }

            return $user;
        });
    }

    /** Active owners other than $except — at least one active owner must always remain (spec 5.2). */
    public function otherActiveOwnersExist(User $except): bool
    {
        return User::role(RoleName::Owner->value)
            ->active()
            ->whereKeyNot($except->getKey())
            ->exists();
    }

    /**
     * Roles and farms live in pivot tables, so the model's own audit entry misses them: log old → new here.
     * A password reset is recorded as an event only — the password itself is never logged.
     *
     * @param  list<string>  $oldRoles
     * @param  list<int>  $oldFarms
     */
    private function logAccessChange(User $user, array $oldRoles, array $oldFarms, bool $passwordChanged): void
    {
        $newRoles = $user->roles()->pluck('name')->sort()->values()->all();
        $newFarms = $user->farms()->pluck('farms.id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $oldFarms = array_map('intval', $oldFarms);

        $old = $new = [];

        if ($oldRoles !== $newRoles) {
            [$old['roles'], $new['roles']] = [$oldRoles, $newRoles];
        }

        if ($oldFarms !== $newFarms) {
            [$old['farms'], $new['farms']] = [$oldFarms, $newFarms];
        }

        if ($passwordChanged) {
            [$old['password'], $new['password']] = ['••••••', __('reset')];
        }

        if ($new !== []) {
            activity()
                ->performedOn($user)
                ->event('updated')
                ->withProperties(['old' => $old, 'attributes' => $new])
                ->log('access');
        }
    }

    private function endSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->getKey())->delete();
        }
    }
}
