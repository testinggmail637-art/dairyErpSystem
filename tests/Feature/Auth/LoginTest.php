<?php

use App\Models\User;
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

it('logs out', function () {
    $this->actingAs(User::factory()->forFarm()->create())
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('has no public registration page', function () {
    $this->get('/register')->assertNotFound();
});
