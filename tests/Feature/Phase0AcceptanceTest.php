<?php

/*
| Phase 0 acceptance criteria (spec Section 8), end to end, in the order the spec lists them.
| Detailed behaviour is covered by the per-feature tests; this file proves the whole flow works together.
*/

use App\Enums\RoleName;
use App\Models\Activity;
use App\Models\Farm;
use App\Models\User;
use App\Services\AppearanceService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders\SlowRequests;
use Tests\Fixtures\FarmScopedThing;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->farm = Farm::where('code', 'MDF')->firstOrFail();
    $this->owner = User::where('email', config('dairy.owner.email'))->firstOrFail();
});

it('1. owner logs in, creates a herd_staff user who sees only permitted menus and gets 403 elsewhere', function () {
    $this->post(route('login.store'), ['login' => config('dairy.owner.email'), 'password' => config('dairy.owner.password')])
        ->assertRedirect(route('home'));

    $this->post(route('users.store'), [
        'name' => 'Ramu Herd', 'phone' => '9811111111', 'password' => 'herd12345', 'password_confirmation' => 'herd12345',
        'is_active' => '1', 'roles' => ['herd_staff'], 'farms' => [$this->farm->id],
    ])->assertRedirect(route('users.index'));

    $this->post(route('logout'));
    $this->post(route('login.store'), ['login' => '9811111111', 'password' => 'herd12345'])->assertRedirect(route('home'));

    $page = $this->get(route('dashboard'))->assertOk();
    $page->assertSee('<span>Dashboard</span>', false)
        ->assertDontSee(route('users.index'), false)
        ->assertDontSee(route('roles.index'), false)
        ->assertDontSee(route('audit-logs.index'), false)
        ->assertDontSee('System Monitor');

    foreach (['users.index', 'roles.index', 'settings.farm.edit', 'settings.appearance.edit', 'audit-logs.index', 'sections.index'] as $route) {
        $this->get(route($route))->assertForbidden();
    }
});

it('2. data of another farm is invisible', function () {
    FarmScopedThing::createTable();
    Route::middleware(['web', 'auth', 'active', 'farm'])->get('_t/things/{thing}', fn (FarmScopedThing $thing) => $thing->name);

    $otherFarm = Farm::factory()->create();
    $mine = FarmScopedThing::create(['name' => 'Ours', 'farm_id' => $this->farm->id]);
    $theirs = FarmScopedThing::create(['name' => 'Theirs', 'farm_id' => $otherFarm->id]);

    $staff = User::factory()->forFarm($this->farm)->withRole(RoleName::Admin)->create();

    $this->actingAs($staff)->get("_t/things/{$mine->id}")->assertOk();
    $this->actingAs($staff)->get("_t/things/{$theirs->id}")->assertNotFound();
    $this->actingAs($staff)->withSession(['current_farm_id' => $otherFarm->id])->get(route('profile.edit'))->assertForbidden();
});

it('3. farm settings save', function () {
    $this->actingAs($this->owner)->put(route('settings.farm.update'), [
        'name' => 'Gokul Dairy', 'address' => 'Rampur',
        'settings' => array_merge(Farm::DEFAULT_SETTINGS, ['bill_due_days' => 10, 'allow_negative_milk_stock' => '0', 'staff_same_day_edit_only' => '1']),
    ])->assertSessionHas('success');

    expect($this->farm->fresh()->setting('bill_due_days'))->toBe(10);
});

it('4. the audit log shows user edits', function () {
    $staff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create(['name' => 'Old Name']);

    $this->actingAs($this->owner)->put(route('users.update', $staff), [
        'name' => 'New Name', 'email' => $staff->email, 'phone' => $staff->phone, 'is_active' => '1',
        'roles' => ['herd_staff'], 'farms' => [$this->farm->id], 'reason' => 'Spelling corrected',
    ])->assertRedirect(route('users.index'));

    expect(Activity::where('subject_id', $staff->id)->where('description', 'updated')->exists())->toBeTrue();

    $this->actingAs($this->owner)->get(route('audit-logs.index'))
        ->assertSee('Old Name')
        ->assertSee('New Name')
        ->assertSee('Spelling corrected');
});

it('5. appearance changes the whole panel, login page and PDF header; reset restores the defaults', function () {
    Storage::fake('public');

    $payload = collect(AppearanceService::DEFAULTS)
        ->except(['logo_path', 'logo_dark_path', 'logo_mobile_path', 'logo_mobile_dark_path', 'favicon_path', 'login_background_path', 'login_logo_path'])
        ->map(fn ($v) => $v ?? '')
        ->merge(['primary_color' => '#7A1F5C', 'sidebar_bg' => '#202040', 'logo' => UploadedFile::fake()->image('logo.png', 500, 120)])
        ->all();

    $this->actingAs($this->owner)->put(route('settings.appearance.update'), $payload)->assertSessionHas('success');
    $logo = app(AppearanceService::class)->stored($this->farm->fresh())['logo_path'];

    $this->actingAs($this->owner)->get(route('users.index'))
        ->assertSee('--bs-primary: #7A1F5C;', false)
        ->assertSee('--sidebar-bg: #202040;', false)
        ->assertSee(Storage::disk('public')->url($logo), false);

    $pdfHtml = view('pdf.appearance-sample', ['farm' => $this->farm->fresh()])->render();
    expect($pdfHtml)->toContain('border-bottom: 3px solid #7A1F5C')->toContain('data:image/png;base64,');

    auth()->logout();
    $this->get(route('login'))->assertSee('--bs-primary: #7A1F5C;', false)->assertSee(Storage::disk('public')->url($logo), false);

    $this->actingAs($this->owner)->post(route('settings.appearance.reset'));
    $this->actingAs($this->owner)->get(route('users.index'))
        ->assertSee('--bs-primary: #2D7A46;', false)
        ->assertSee('--sidebar-bg: #FCFDFA;', false);
});

it('6. /pulse opens for the owner and returns 403 for admin and staff', function () {
    $admin = User::factory()->forFarm($this->farm)->withRole(RoleName::Admin)->create();
    $staff = User::factory()->forFarm($this->farm)->withRole(RoleName::CollectionOperator)->create();

    $this->actingAs($this->owner)->get('/pulse')->assertOk();
    $this->actingAs($admin)->get('/pulse')->assertForbidden();
    $this->actingAs($staff)->get('/pulse')->assertForbidden();
});

it('7. a request slower than the threshold appears in Pulse slow requests', function () {
    // Pulse is switched off in tests; switch it on here and use a 0 ms threshold so any request counts as slow.
    config(['pulse.enabled' => true, 'pulse.recorders.'.SlowRequests::class.'.threshold' => 0]);
    Pulse::register([SlowRequests::class => config('pulse.recorders.'.SlowRequests::class)]);
    Pulse::startRecording();

    $this->actingAs($this->owner)->get(route('profile.edit'))->assertOk();
    Pulse::ingest();

    // The dashboard's Slow Requests card reads exactly these entries (cards load lazily, so the HTML is not asserted).
    expect(DB::table('pulse_entries')->where('type', 'slow_request')->where('key', 'like', '%/profile%')->exists())->toBeTrue();
});
