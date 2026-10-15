<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs on every app route (alias `active`): a user deactivated while logged in (or coming back with a
 * "remember me" cookie) is signed out on the next request and sent to the login page.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = __('Your account is inactive. Please contact the owner.');

            if ($request->expectsJson()) {
                abort(401, $message);
            }

            return redirect()->route('login')->withErrors(['login' => $message]);
        }

        return $next($request);
    }
}
