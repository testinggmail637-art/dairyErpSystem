<?php

namespace App\Http\Middleware;

use App\Support\CurrentFarm;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs on every app route (alias `farm`): makes sure a current farm is resolved and that the user is
 * linked to it; otherwise 403. Registered before SubstituteBindings so route model binding is farm-scoped.
 */
class EnsureFarmSelected
{
    public function __construct(private readonly CurrentFarm $currentFarm) {}

    public function handle(Request $request, Closure $next): Response
    {
        $farm = $this->currentFarm->farm();

        if ($farm === null) {
            // Drop a stale/forbidden farm id so the next request falls back to the user's first farm.
            $request->session()->forget(CurrentFarm::SESSION_KEY);
            $this->currentFarm->forget();

            abort(403, __('Your account is not linked to this farm. Please contact the owner.'));
        }

        $request->session()->put(CurrentFarm::SESSION_KEY, $farm->id);

        return $next($request);
    }
}
