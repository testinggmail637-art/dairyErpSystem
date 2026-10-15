<?php

use App\Models\Farm;
use App\Models\User;
use App\Support\CurrentFarm;
use Database\Seeders\DatabaseSeeder;

it('blocks a user who is not linked to any farm', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertForbidden();
});

it('uses the first active farm of the user and shows its name', function () {
    $farm = Farm::factory()->create(['name' => 'Gokul Dairy']);
    $user = User::factory()->forFarm($farm)->create();
    $user->farms()->attach(Farm::factory()->inactive()->create(['name' => 'Closed Farm']));

    $this->actingAs($user)->get(route('profile.edit'))
        ->assertOk()
        ->assertSessionHas(CurrentFarm::SESSION_KEY, $farm->id)
        ->assertSee('data-testid="farm-name"', false)
        ->assertSee('Gokul Dairy')
        ->assertDontSee('data-testid="farm-switcher"', false);
});

it('returns 403 and resets the session when the session farm is not linked to the user', function () {
    $user = User::factory()->forFarm()->create();
    $otherFarm = Farm::factory()->create();

    $this->actingAs($user)->withSession([CurrentFarm::SESSION_KEY => $otherFarm->id])
        ->get(route('profile.edit'))
        ->assertForbidden()
        ->assertSessionMissing(CurrentFarm::SESSION_KEY);
});

it('shows a switcher for a user with several farms and switches farm', function () {
    $farmA = Farm::factory()->create(['name' => 'Alpha Farm']);
    $farmB = Farm::factory()->create(['name' => 'Beta Farm']);
    $user = User::factory()->forFarm($farmA)->create();
    $user->farms()->attach($farmB);

    $this->actingAs($user)->get(route('profile.edit'))
        ->assertSee('data-testid="farm-switcher"', false)
        ->assertSee('Beta Farm');

    $this->actingAs($user)->post(route('farm.switch'), ['farm_id' => $farmB->id])
        ->assertRedirect(route('home'))
        ->assertSessionHas(CurrentFarm::SESSION_KEY, $farmB->id);
});

it('refuses to switch to a farm the user is not linked to or that is inactive', function () {
    $user = User::factory()->forFarm()->create();
    $foreign = Farm::factory()->create();
    $inactive = Farm::factory()->inactive()->create();
    $user->farms()->attach($inactive);

    foreach ([$foreign, $inactive] as $farm) {
        $this->actingAs($user)->post(route('farm.switch'), ['farm_id' => $farm->id])
            ->assertSessionHasErrors('farm_id');
    }
});

it('merges stored farm settings over the defaults', function () {
    $farm = Farm::factory()->create(['settings' => ['bill_due_days' => 15]]);

    expect($farm->setting('bill_due_days'))->toBe(15)
        ->and($farm->setting('farmer_payment_cycle_days'))->toBe(10)
        ->and($farm->setting('missing', 'x'))->toBe('x');
});

it('seeds the default farm and links the owner to it', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class); // safe to run twice

    $owner = User::where('email', config('dairy.owner.email'))->firstOrFail();

    expect(Farm::where('code', 'MDF')->count())->toBe(1)
        ->and($owner->farms()->pluck('code')->all())->toBe(['MDF']);
});
