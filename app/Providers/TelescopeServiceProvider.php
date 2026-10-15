<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

/**
 * Telescope — local debugging only (spec 4.10). Registered by AppServiceProvider only when APP_ENV=local
 * and the dev package is installed; never auto-discovered, never loaded in production.
 */
class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    public function register(): void
    {
        // Even locally, keep secrets out of the stored entries.
        Telescope::hideRequestParameters(['_token', 'password', 'password_confirmation', 'current_password']);
        Telescope::hideRequestHeaders(['cookie', 'set-cookie', 'x-csrf-token', 'x-xsrf-token']);
    }

    /**
     * Outside local the provider is not registered at all; this gate is a second lock.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', fn (?User $user = null) => $this->app->environment('local'));
    }
}
