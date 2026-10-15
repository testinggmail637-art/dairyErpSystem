<?php

namespace App\Providers;

use App\Support\CurrentFarm;
use App\Support\Menu;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One instance per request (scoped, so queue workers start fresh for each job).
        $this->app->scoped(CurrentFarm::class);
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Paginator::useBootstrapFive();

        // Route-level limit on the login form per IP (per-account limit is in LoginRequest).
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));

        View::composer('layouts.partials.sidebar', function ($view) {
            $view->with('menu', Menu::for(auth()->user()));
        });

        View::composer('layouts.partials.topbar', function ($view) {
            $user = auth()->user();

            $currentFarm = app(CurrentFarm::class);

            $view->with([
                'currentFarm' => $currentFarm->farm(),
                'userFarms' => $user ? $currentFarm->availableFor($user) : collect(),
                'unreadCount' => $user?->unreadNotifications()->count() ?? 0,
                'recentNotifications' => $user?->notifications()->limit(5)->get() ?? collect(),
            ]);
        });
    }
}
