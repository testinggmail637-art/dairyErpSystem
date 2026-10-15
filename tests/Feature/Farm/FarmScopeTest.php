<?php

use App\Models\Farm;
use App\Models\User;
use App\Support\CurrentFarm;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\FarmScopedThing;

beforeEach(function () {
    FarmScopedThing::createTable();

    Route::middleware(['web', 'auth', 'farm'])
        ->get('_test/things/{thing}', fn (FarmScopedThing $thing) => $thing->name);
});

it('shows only the current farm rows and fills farm_id on create', function () {
    [$farmA, $farmB] = Farm::factory()->count(2)->create();
    $current = app(CurrentFarm::class);

    $current->set($farmB);
    FarmScopedThing::create(['name' => 'B thing']);

    $current->set($farmA);
    $thing = FarmScopedThing::create(['name' => 'A thing']);

    expect($thing->farm_id)->toBe($farmA->id)
        ->and(FarmScopedThing::pluck('name')->all())->toBe(['A thing'])
        ->and(FarmScopedThing::allFarms()->count())->toBe(2);
});

it('keeps an explicitly given farm_id', function () {
    [$farmA, $farmB] = Farm::factory()->count(2)->create();
    app(CurrentFarm::class)->set($farmA);

    $thing = FarmScopedThing::create(['name' => 'X', 'farm_id' => $farmB->id]);

    expect($thing->farm_id)->toBe($farmB->id);
});

it('refuses to create a farm row without a current farm', function () {
    FarmScopedThing::create(['name' => 'Orphan']);
})->throws(LogicException::class, 'No current farm');

it('returns 404 for a record of another farm through route model binding', function () {
    $farmA = Farm::factory()->create();
    $farmB = Farm::factory()->create();
    $user = User::factory()->forFarm($farmA)->create();

    $own = FarmScopedThing::create(['name' => 'Mine', 'farm_id' => $farmA->id]);
    $other = FarmScopedThing::create(['name' => 'Theirs', 'farm_id' => $farmB->id]);

    $this->actingAs($user)->get("_test/things/{$own->id}")->assertOk()->assertSee('Mine');
    $this->actingAs($user)->get("_test/things/{$other->id}")->assertNotFound();
});

it('fills created_by and updated_by from the logged-in user', function () {
    $farm = Farm::factory()->create();
    $creator = User::factory()->forFarm($farm)->create();
    $editor = User::factory()->forFarm($farm)->create();
    app(CurrentFarm::class)->set($farm);

    $this->actingAs($creator);
    $thing = FarmScopedThing::create(['name' => 'Tracked']);

    expect($thing->created_by)->toBe($creator->id)->and($thing->updated_by)->toBe($creator->id);

    $this->actingAs($editor);
    $thing->update(['name' => 'Edited']);
    $thing->refresh()->load('creator');

    expect($thing->created_by)->toBe($creator->id)
        ->and($thing->updated_by)->toBe($editor->id)
        ->and($thing->creator->is($creator))->toBeTrue();
});
