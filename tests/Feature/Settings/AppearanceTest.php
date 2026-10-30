<?php

use App\Enums\RoleName;
use App\Models\Farm;
use App\Models\User;
use App\Services\AppearanceService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->farm = Farm::factory()->create(['name' => 'Gokul Dairy', 'address' => 'Rampur, Mathura']);
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
});

function appearancePayload(array $overrides = []): array
{
    return array_merge(collect(AppearanceService::DEFAULTS)
        ->except(['logo_path', 'logo_dark_path', 'logo_mobile_path', 'favicon_path', 'login_background_path'])
        ->map(fn ($v) => $v ?? '')
        ->all(), $overrides);
}

it('opens only for users with appearance.manage', function () {
    $admin = User::factory()->forFarm($this->farm)->withRole(RoleName::Admin)->create();
    $accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();

    $this->actingAs($this->owner)->get(route('settings.appearance.edit'))->assertOk()->assertSee('Green Dairy');
    $this->actingAs($admin)->get(route('settings.appearance.edit'))->assertOk();
    $this->actingAs($accountant)->get(route('settings.appearance.edit'))->assertForbidden();
    $this->actingAs($accountant)->put(route('settings.appearance.update'), appearancePayload())->assertForbidden();
});

it('rejects invalid colours and out-of-range sizes', function () {
    $this->actingAs($this->owner)
        ->put(route('settings.appearance.update'), appearancePayload([
            'primary_color' => 'green', 'sidebar_bg' => '#12345', 'base_font_size' => 20, 'border_radius' => 30,
            'font_family' => 'Comic Sans', 'theme_mode' => 'neon',
        ]))
        ->assertSessionHasErrors(['primary_color', 'sidebar_bg', 'base_font_size', 'border_radius', 'font_family', 'theme_mode']);
});

it('applies saved colours as CSS variables across the panel and the login page', function () {
    $this->actingAs($this->owner)
        ->put(route('settings.appearance.update'), appearancePayload([
            'app_name' => 'Gokul ERP', 'primary_color' => '#112233', 'sidebar_bg' => '#330000',
            'font_family' => 'Poppins', 'border_radius' => 10, 'login_welcome_text' => 'Namaste!',
        ]))
        ->assertRedirect(route('settings.appearance.edit'))
        ->assertSessionHas('success');

    $this->actingAs($this->owner)->get(route('profile.edit'))
        ->assertSee('--bs-primary: #112233;', false)
        ->assertSee('--bs-primary-rgb: 17, 34, 51;', false)
        ->assertSee('--sidebar-bg: #330000;', false)
        ->assertSee("--bs-body-font-family: 'Poppins'", false)
        ->assertSee('--bs-border-radius: 10px;', false)
        ->assertSee('<title>My profile · Gokul ERP</title>', false)
        ->assertSee('<span>Gokul ERP</span>', false);

    auth()->logout();

    $this->get(route('login'))
        ->assertSee('--bs-primary: #112233;', false)
        ->assertSee('Gokul ERP')
        ->assertSee('Namaste!');
});

it('stores, resizes and shows the uploaded logo, then removes it', function () {
    $this->actingAs($this->owner)->put(route('settings.appearance.update'), appearancePayload([
        'logo' => UploadedFile::fake()->image('logo.png', 1200, 300),
        'favicon' => UploadedFile::fake()->image('icon.png', 300, 200),
    ]))->assertSessionDoesntHaveErrors();

    $stored = app(AppearanceService::class)->stored($this->farm->fresh());
    expect($stored['logo_path'])->toStartWith("branding/{$this->farm->id}/logo-");
    Storage::disk('public')->assertExists($stored['logo_path']);

    [$width] = getimagesize(Storage::disk('public')->path($stored['logo_path']));
    [$fw, $fh] = getimagesize(Storage::disk('public')->path($stored['favicon_path']));
    expect($width)->toBe(400)->and([$fw, $fh])->toBe([64, 64]);

    $this->actingAs($this->owner)->get(route('profile.edit'))
        ->assertSee(Storage::disk('public')->url($stored['logo_path']), false)
        ->assertSee('rel="icon" href="'.Storage::disk('public')->url($stored['favicon_path']), false);

    $this->actingAs($this->owner)->put(route('settings.appearance.update'), appearancePayload(['remove' => ['logo']]));

    expect(app(AppearanceService::class)->stored($this->farm->fresh())['logo_path'])->toBeNull();
    Storage::disk('public')->assertMissing($stored['logo_path']);
});

it('sanitizes SVG logos', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10" onload="alert(1)">'
        .'<script>alert(2)</script><rect width="10" height="10" fill="#1F6F5C"/>'
        .'<a href="javascript:alert(3)"><circle r="2"/></a><foreignObject><p>x</p></foreignObject></svg>';

    $this->actingAs($this->owner)->put(route('settings.appearance.update'), appearancePayload([
        'logo' => UploadedFile::fake()->createWithContent('logo.svg', $svg),
    ]))->assertSessionDoesntHaveErrors();

    $path = app(AppearanceService::class)->stored($this->farm->fresh())['logo_path'];
    $saved = Storage::disk('public')->get($path);

    expect($path)->toEndWith('.svg')
        ->and($saved)->toContain('<rect')
        ->not->toContain('script')
        ->not->toContain('onload')
        ->not->toContain('javascript')
        ->not->toContain('foreignObject');
});

it('resets to the defaults and deletes uploaded files', function () {
    $this->actingAs($this->owner)->put(route('settings.appearance.update'), appearancePayload([
        'primary_color' => '#112233', 'logo' => UploadedFile::fake()->image('logo.png', 200, 50),
    ]));
    $logo = app(AppearanceService::class)->stored($this->farm->fresh())['logo_path'];

    $this->actingAs($this->owner)->post(route('settings.appearance.reset'))
        ->assertRedirect(route('settings.appearance.edit'));

    expect($this->farm->fresh()->setting('appearance'))->toBeNull();
    Storage::disk('public')->assertMissing($logo);

    $this->actingAs($this->owner)->get(route('profile.edit'))
        ->assertSee('--bs-primary: #2D7A46;', false)
        ->assertDontSee('#112233', false);
});

it('warns (without blocking) when colours are hard to read', function () {
    $this->actingAs($this->owner)
        ->put(route('settings.appearance.update'), appearancePayload(['sidebar_bg' => '#FFFFFF', 'sidebar_text' => '#EEEEEE']))
        ->assertSessionHas('success')
        ->assertSessionHas('warning', fn ($w) => str_contains($w, 'Sidebar text on sidebar background'));
});

it('lets each user override the theme from the profile', function () {
    $this->actingAs($this->owner)->put(route('settings.appearance.update'), appearancePayload(['theme_mode' => 'dark']));
    $this->actingAs($this->owner)->get(route('profile.edit'))->assertSee('data-bs-theme="dark"', false);

    $this->actingAs($this->owner)->put(route('profile.update'), ['name' => $this->owner->name, 'theme_mode' => 'light'])
        ->assertSessionHas('success');

    expect($this->owner->fresh()->preferences['theme_mode'])->toBe('light');
    $this->actingAs($this->owner->fresh())->get(route('profile.edit'))->assertSee('data-bs-theme="light"', false);

    $this->actingAs($this->owner)->put(route('profile.update'), ['name' => $this->owner->name, 'theme_mode' => 'auto']);
    $this->actingAs($this->owner->fresh())->get(route('profile.edit'))->assertSee('prefers-color-scheme: dark', false);

    $this->actingAs($this->owner)->put(route('profile.update'), ['name' => $this->owner->name, 'theme_mode' => 'pink'])
        ->assertSessionHasErrors('theme_mode');
});

it('brands the PDF header with the print settings and accent colour', function () {
    $this->actingAs($this->owner)->put(route('settings.appearance.update'), appearancePayload([
        'print_footer_text' => 'Bank: SBI 1234', 'pdf_accent_color' => '#AA5500',
    ]));

    $this->actingAs($this->owner);
    $html = view('pdf.appearance-sample', ['farm' => $this->farm->fresh()])->render();

    expect($html)->toContain('Gokul Dairy')            // default header = farm name + address
        ->toContain('Rampur, Mathura')
        ->toContain('Bank: SBI 1234')
        ->toContain('background: #AA5500')
        ->toContain('₹4,685.00');

    $this->get(route('settings.appearance.sample-pdf'))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('keeps appearance per farm and rebuilds the cache when the farm changes', function () {
    $other = Farm::factory()->create();
    $service = app(AppearanceService::class);

    $this->actingAs($this->owner)->put(route('settings.appearance.update'), appearancePayload(['primary_color' => '#112233']));

    expect($service->get($this->farm->fresh())['primary_color'])->toBe('#112233')
        ->and($service->get($other)['primary_color'])->toBe('#2D7A46');

    $this->farm->update(['name' => 'Renamed Dairy']);
    expect($service->get($this->farm->fresh())['print_header_text'])->toStartWith('Renamed Dairy');
});

it('prints the card background and keeps a light sidebar readable in the dark theme', function () {
    $this->actingAs($this->owner)->put(route('settings.appearance.update'), appearancePayload([
        'card_bg' => '#FAFFF0', 'sidebar_bg' => '#FFFFFF', 'sidebar_text' => '#1F3A2B',
    ]))->assertSessionHasNoErrors();

    $css = app(AppearanceService::class)->styleBlock(app(AppearanceService::class)->get($this->farm->fresh()));

    expect($css)->toContain('--app-card-bg: #FAFFF0;')
        ->and(str($css)->after('[data-bs-theme="dark"]')->toString())->toContain('--sidebar-bg: var(--bs-tertiary-bg);');
});

it('offers presets that each set every colour and a theme, and save as valid settings', function () {
    foreach (AppearanceService::PRESETS as $key => $preset) {
        expect(array_keys($preset))->toContain(...AppearanceService::COLOR_KEYS)
            ->and($preset['theme_mode'])->toBeIn(AppearanceService::THEMES);

        $this->actingAs($this->owner)
            ->put(route('settings.appearance.update'), appearancePayload(collect($preset)->except(['label', 'description'])->all()))
            ->assertSessionHasNoErrors();
    }

    $this->actingAs($this->owner)->get(route('settings.appearance.edit'))
        ->assertSee('Cream &amp; Butter', false)->assertSee('Forest Night')->assertSee('preset-card');
});

it('hides the app name when a logo exists and shows the mobile logo in the phone top bar', function () {
    $this->actingAs($this->owner)->get(route('profile.edit'))
        ->assertSee('<span>'.e(AppearanceService::DEFAULTS['app_name']).'</span>', false);

    $this->actingAs($this->owner)->put(route('settings.appearance.update'), appearancePayload([
        'app_name' => 'Shree Milk ERP',
        'logo' => UploadedFile::fake()->image('logo.png', 400, 100),
        'logo_mobile' => UploadedFile::fake()->image('mobile.png', 100, 100),
    ]))->assertSessionDoesntHaveErrors();

    $stored = app(AppearanceService::class)->stored($this->farm->fresh());
    expect($stored['logo_mobile_path'])->toStartWith("branding/{$this->farm->id}/logo_mobile-");

    $this->actingAs($this->owner)->get(route('profile.edit'))
        ->assertDontSee('<span>Shree Milk ERP</span>', false)
        ->assertSee('src="'.Storage::disk('public')->url($stored['logo_mobile_path']).'" alt="Shree Milk ERP" class="app-topbar-logo"', false);
});
