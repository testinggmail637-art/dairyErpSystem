<?php

use App\Enums\RoleName;
use App\Models\User;
use App\Providers\TelescopeServiceProvider;
use Laravel\Pulse\Recorders;

it('opens /pulse for the owner only (admin and staff get 403, guests go to login)', function () {
    $owner = User::factory()->forFarm()->withRole(RoleName::Owner)->create();
    $admin = User::factory()->forFarm()->withRole(RoleName::Admin)->create();
    $staff = User::factory()->forFarm()->withRole(RoleName::HerdStaff)->create();

    $this->get('/pulse')->assertRedirect(route('login'));
    $this->actingAs($owner)->get('/pulse')->assertOk();
    $this->actingAs($admin)->get('/pulse')->assertForbidden();
    $this->actingAs($staff)->get('/pulse')->assertForbidden();
});

it('shows the System Monitor link only to users with monitoring.view', function () {
    $owner = User::factory()->forFarm()->withRole(RoleName::Owner)->create();
    $admin = User::factory()->forFarm()->withRole(RoleName::Admin)->create();

    $this->actingAs($owner)->get(route('profile.edit'))->assertSee('System Monitor')->assertSee(url('pulse'), false);
    $this->actingAs($admin)->get(route('profile.edit'))->assertDontSee('System Monitor');
});

it('uses the spec thresholds, recorders and 7-day retention', function () {
    $recorders = config('pulse.recorders');

    expect($recorders[Recorders\SlowRequests::class]['threshold'])->toBe(1000)
        ->and($recorders[Recorders\SlowQueries::class]['threshold'])->toBe(500)
        ->and($recorders[Recorders\SlowJobs::class]['threshold'])->toBe(1000)
        ->and(config('pulse.storage.database.trim.keep', config('pulse.storage.trim.keep')))->toBe('7 days')
        ->and($recorders[Recorders\SlowOutgoingRequests::class]['enabled'])->toBeFalse();

    foreach ([Recorders\Exceptions::class, Recorders\Queues::class, Recorders\CacheInteractions::class,
        Recorders\UserRequests::class, Recorders\Servers::class] as $recorder) {
        expect($recorders)->toHaveKey($recorder);
    }
});

it('keeps Pulse recording, Telescope and Sentry off in tests', function () {
    expect(config('pulse.enabled'))->toBeFalsy()
        ->and(config('telescope.enabled'))->toBeFalsy()
        ->and(config('sentry.dsn'))->toBeEmpty()
        ->and(app()->getProviders(TelescopeServiceProvider::class))->toBeEmpty();

    $this->actingAs(User::factory()->forFarm()->withRole(RoleName::Owner)->create())
        ->get('/telescope')->assertNotFound();
});

it('configures Sentry without personal data and with 20% tracing', function () {
    expect(config('sentry.send_default_pii'))->toBeFalse()
        ->and(config('sentry.traces_sample_rate'))->toBe(0.2);
});
