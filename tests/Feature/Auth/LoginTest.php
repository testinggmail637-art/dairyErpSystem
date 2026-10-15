<?php

use App\Models\Farm;
use App\Models\User;
use App\Support\CurrentFarm;
use Illuminate\Support\Facades\RateLimiter;

it('shows the login page to guests', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee(__('Email or phone'));
});

it('redirects guests away from the app', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->get('/')->assertRedirect(route('login'));
});

it('logs in with email', function () {
    $user = User::factory()->forFarm()->create(['email' => 'ram@example.com']);

    $this->post(route('login.store'), ['login' => 'Ram@Example.com ', 'password' => 'password'])
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('logs in with phone in any common format', function (string $typed) {
    $user = User::factory()->forFarm()->create(['phone' => '9876543210']);

    $this->post(route('login.store'), ['login' => $typed, 'password' => 'password'])
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
})->with(['9876543210', '+91 98765 43210', '09876543210', '98765-43210']);

it('rejects a wrong password', function () {
    User::factory()->forFarm()->create(['email' => 'ram@example.com']);

    $this->from(route('login'))
        ->post(route('login.store'), ['login' => 'ram@example.com', 'password' => 'wrong'])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('login');

    $this->assertGuest();
});

it('blocks inactive users even with the right password', function () {
    User::factory()->inactive()->create(['email' => 'ram@example.com']);

    $this->post(route('login.store'), ['login' => 'ram@example.com', 'password' => 'password'])
        ->assertSessionHasErrors(['login' => __('Your account is inactive. Please contact the owner.')]);

    $this->assertGuest();
});

it('does not touch last_login_at when login fails or the user is inactive', function () {
    $active = User::factory()->forFarm()->create(['email' => 'ram@example.com']);
    $inactive = User::factory()->forFarm()->inactive()->create(['email' => 'sita@example.com']);

    $this->post(route('login.store'), ['login' => 'ram@example.com', 'password' => 'wrong']);
    $this->post(route('login.store'), ['login' => 'sita@example.com', 'password' => 'password']);

    expect($active->fresh()->last_login_at)->toBeNull()
        ->and($inactive->fresh()->last_login_at)->toBeNull();
});

it('remembers the login when asked', function () {
    User::factory()->forFarm()->create(['email' => 'ram@example.com']);

    $response = $this->post(route('login.store'), ['login' => 'ram@example.com', 'password' => 'password', 'remember' => '1']);

    $response->assertCookie(auth()->guard('web')->getRecallerName());
});

it('signs out a user who is deactivated while logged in', function () {
    $user = User::factory()->forFarm()->create();

    $this->actingAs($user)->get(route('profile.edit'))->assertOk();

    $user->update(['is_active' => false]);

    $this->actingAs($user->fresh())->get(route('profile.edit'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['login' => __('Your account is inactive. Please contact the owner.')]);

    $this->assertGuest();
});

it('answers 401 to AJAX requests from a deactivated user', function () {
    $user = User::factory()->forFarm()->inactive()->create();

    $this->actingAs($user)->getJson(route('profile.edit'))->assertUnauthorized();
});

it('locks the account after too many failed attempts', function () {
    $user = User::factory()->forFarm()->create(['email' => 'ram@example.com']);

    foreach (range(1, 5) as $i) {
        $this->post(route('login.store'), ['login' => 'ram@example.com', 'password' => 'wrong']);
    }

    // Even the right password is refused while locked out.
    $this->post(route('login.store'), ['login' => 'ram@example.com', 'password' => 'password'])
        ->assertSessionHasErrors('login');

    $this->assertGuest();
    RateLimiter::clear('ram@example.com|127.0.0.1');
});

it('does not carry a previous user\'s farm into a new login', function () {
    $user = User::factory()->forFarm()->create(['email' => 'ram@example.com']);
    $foreignFarm = Farm::factory()->create();

    $this->withSession([CurrentFarm::SESSION_KEY => $foreignFarm->id])
        ->post(route('login.store'), ['login' => 'ram@example.com', 'password' => 'password'])
        ->assertRedirect(route('home'))
        ->assertSessionMissing(CurrentFarm::SESSION_KEY);

    $this->get(route('profile.edit'))->assertOk();
    expect(session(CurrentFarm::SESSION_KEY))->toBe($user->farms->first()->id);
});

it('logs out', function () {
    $this->actingAs(User::factory()->forFarm()->create())
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('has no public registration page', function () {
    $this->get('/register')->assertNotFound();
});
