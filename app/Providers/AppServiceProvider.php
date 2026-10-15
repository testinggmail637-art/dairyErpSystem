<?php

namespace App\Providers;

use App\Support\AuditReason;
use App\Support\CurrentFarm;
use App\Support\Menu;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
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
        $this->app->scoped(AuditReason::class);
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Paginator::useBootstrapFive();

        // Standard columns of every business table (spec Section 4.1): `$table->farmScoped(); … $table->userStamps();`
        Blueprint::macro('farmScoped', function () {
            /** @var Blueprint $this */
            return $this->foreignId('farm_id')->constrained()->restrictOnDelete();
        });

        Blueprint::macro('userStamps', function () {
            /** @var Blueprint $this */
            $this->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $this->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
        });

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
