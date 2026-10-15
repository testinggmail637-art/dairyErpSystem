<?php

namespace App\Providers;

use App\Models\Farm;
use App\Models\User;
use App\Services\AppearanceService;
use App\Support\AuditReason;
use App\Support\CurrentFarm;
use App\Support\Menu;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\Backup\Tasks\Backup\DbDumperFactory;
use Spatie\DbDumper\Databases\MariaDb;
use Spatie\DbDumper\Databases\MySql;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One instance per request (scoped, so queue workers start fresh for each job).
        $this->app->scoped(CurrentFarm::class);
        $this->app->scoped(AuditReason::class);

        // Telescope: local only, registered by hand (it is in composer.json "dont-discover"; dev dependency).
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Paginator::useBootstrapFive();

        // Backups on MariaDB: older MariaDB (e.g. XAMPP's 10.4) ships only `mysqldump`, not `mariadb-dump`.
        DbDumperFactory::extend('mariadb', function () {
            $path = rtrim((string) config('database.connections.mariadb.dump.dump_binary_path'), '/\\');
            $hasMariaDbDump = $path !== '' && (is_file("{$path}/mariadb-dump") || is_file("{$path}/mariadb-dump.exe"));

            return $path === '' || $hasMariaDbDump ? new MariaDb : new MySql;
        });

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

        // /pulse (System Monitor): only users with monitoring.view — the owner by default (spec 4.10).
        Gate::define('viewPulse', fn (User $user) => $user->can('monitoring.view'));

        // Route-level limit on the login form per IP (per-account limit is in LoginRequest).
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));

        // Appearance (spec 4.11) for the layouts, the login page and PDFs. Guests (login page) see the first active farm's branding.
        View::composer(['layouts.app', 'layouts.guest', 'auth.login', 'account.profile', 'pdf.*', 'settings.appearance.*'], function ($view) {
            $service = app(AppearanceService::class);
            $farm = app(CurrentFarm::class)->farm() ?? Farm::query()->active()->orderBy('id')->first();
            $appearance = $service->get($farm); // cached per farm

            $view->with([
                'appearance' => $appearance,
                'appearanceCss' => $service->styleBlock($appearance),
                'themeMode' => $service->themeFor(auth()->user(), $appearance),
            ]);
        });

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
