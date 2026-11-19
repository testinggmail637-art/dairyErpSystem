<?php

use App\Models\Farm;
use App\Support\Website;

beforeEach(function () {
    Farm::factory()->create();
});

it('serves the website in Hindi at / and English at /en', function () {
    $this->get('/')->assertOk()
        ->assertSee('<html lang="hi"', false)
        ->assertSee(__('website.nav.products', [], 'hi'))
        ->assertSee('href="'.url('/en').'"', false);

    $this->get('/en')->assertOk()
        ->assertSee('<html lang="en"', false)
        ->assertSee('Home delivery')
        ->assertSee('hreflang="hi" href="'.url('/').'"', false);
});

it('links every page to the same page in the other language', function () {
    foreach (Website::PAGES as $page) {
        $this->get("/{$page}")->assertOk()->assertSee('href="'.url("/en/{$page}").'"', false);
        $this->get("/en/{$page}")->assertOk()->assertSee('href="'.url("/{$page}").'"', false);
    }
});

it('renders the theme the visitor chose and follows the device otherwise', function () {
    $this->get('/')->assertSee('data-theme-pref="auto"', false)->assertSee('prefers-color-scheme: dark', false);

    $this->withUnencryptedCookie(Website::THEME_COOKIE, 'dark')->get('/')
        ->assertSee('data-theme="dark"', false)
        ->assertDontSee('prefers-color-scheme: dark', false);
});

it('outputs the brand colours as CSS variables, not hard-coded', function () {
    $this->get('/')->assertSee('--w-primary: #093F1E', false)->assertSee('--w-accent: #FD8D00', false);

    $farm = Farm::first();
    $farm->update(['settings' => array_merge($farm->settings ?? [], ['website' => ['colors' => ['light' => ['accent' => '#AA5500']]]])]);

    $this->get('/')->assertSee('--w-accent: #AA5500', false);
});

it('sends security headers and keeps admin links off the public site', function () {
    $this->get('/')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertDontSee(route('login'), false)
        ->assertDontSee('/admin', false);
});

it('moves the ERP under /admin', function () {
    expect(route('login'))->toEndWith('/admin/login')
        ->and(route('home'))->toEndWith('/admin');
    $this->get('/admin')->assertRedirect(route('login'));
});
