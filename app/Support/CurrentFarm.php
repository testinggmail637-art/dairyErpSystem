<?php

namespace App\Support;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The farm the current request works in (spec Section 4.2).
 *
 * Resolution order: a farm set explicitly with set() (console commands, jobs, tests) → the session's
 * `current_farm_id` if the logged-in user is linked to it → the user's first active farm.
 * Returns null when nothing resolves (guest, no linked farm, or a session farm the user may not use);
 * EnsureFarmSelected turns that into a 403 on app routes.
 */
class CurrentFarm
{
    public const SESSION_KEY = 'current_farm_id';

    private ?Farm $farm = null;

    private bool $resolved = false;

    /** The request the farm was resolved or set for; a new request (long-running worker, tests) resolves again. */
    private ?int $requestId = null;

    public function farm(): ?Farm
    {
        if (! $this->resolved || $this->requestId !== spl_object_id(request())) {
            $this->farm = $this->resolve();
            $this->resolved = true;
            $this->requestId = spl_object_id(request());
        }

        return $this->farm;
    }

    public function id(): ?int
    {
        return $this->farm()?->id;
    }

    public function check(): bool
    {
        return $this->farm() !== null;
    }

    /** Switch to $farm for the rest of this request (and the session, when there is one). */
    public function set(Farm $farm): void
    {
        $this->farm = $farm;
        $this->resolved = true;
        $this->requestId = spl_object_id(request());

        if (app()->bound('session') && request()->hasSession()) {
            request()->session()->put(self::SESSION_KEY, $farm->id);
        }
    }

    /** Forget the resolved farm so the next call resolves again. */
    public function forget(): void
    {
        $this->farm = null;
        $this->resolved = false;
    }

    /**
     * Active farms the user may work in, by name.
     *
     * @return Collection<int, Farm>
     */
    public function availableFor(User $user): Collection
    {
        return $user->farms()->active()->orderBy('name')->get();
    }

    private function resolve(): ?Farm
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        $farms = $this->availableFor($user);
        $sessionFarmId = request()->hasSession() ? request()->session()->get(self::SESSION_KEY) : null;

        if ($sessionFarmId !== null) {
            return $farms->firstWhere('id', (int) $sessionFarmId);
        }

        return $farms->first();
    }
}
