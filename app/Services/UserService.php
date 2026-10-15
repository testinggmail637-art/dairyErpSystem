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

            $user->save();

            $user->syncRoles($data['roles']);

            $hiddenFarmIds = $user->farms()->pluck('farms.id')->diff($editorFarmIds);
            $user->farms()->sync($hiddenFarmIds->merge($data['farms'])->unique()->values()->all());

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

    private function endSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->getKey())->delete();
        }
    }
}
