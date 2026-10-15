<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('renders the app layout for a logged-in user', function () {
    $user = User::factory()->create(['name' => 'Ramesh Kumar']);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('id="appSidebar"', false)
        ->assertSee('Ramesh Kumar')
        ->assertSee(route('profile.edit'), false)
        ->assertSee(route('logout'), false)
        ->assertSee(asset('vendor/jquery/jquery.min.js'), false);
});

it('shows only menu items the user is allowed to see', function () {
    $user = User::factory()->create();

    // No permissions: Dashboard is hidden.
    $this->actingAs($user)->get(route('profile.edit'))
        ->assertOk()
        ->assertDontSee('<span>Dashboard</span>', false)
        ->assertSee(__('No modules available for your role yet.'));

    Gate::define('dashboard.view', fn () => true);

    $this->actingAs($user)->get(route('profile.edit'))
        ->assertSee('<span>Dashboard</span>', false);
});

it('hides menu items whose module is not built yet even when permitted', function () {
    Gate::define('expenses.view', fn () => true);

    $this->actingAs(User::factory()->create())->get(route('profile.edit'))
        ->assertDontSee('>Expenses<', false);
});

it('marks the current page as active', function () {
    Gate::define('dashboard.view', fn () => true);

    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertSee('aria-current="page"', false);
});

it('shows flash messages', function () {
    $this->actingAs(User::factory()->create())
        ->withSession(['success' => 'Expense saved.', 'warning' => 'Stock is low.'])
        ->get(route('dashboard'))
        ->assertSee('Expense saved.')
        ->assertSee('Stock is low.')
        ->assertSee('alert-success', false)
        ->assertSee('alert-warning', false);
});
