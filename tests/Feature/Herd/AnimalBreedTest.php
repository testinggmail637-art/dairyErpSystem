<?php

use App\Enums\AnimalType;
use App\Enums\RoleName;
use App\Models\AnimalBreed;
use App\Models\Farm;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->herdStaff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
});

it('seeds the 9 cow and 7 buffalo breeds of the spec for every farm, once', function () {
    $this->seed(DatabaseSeeder::class);
    $farm = Farm::where('code', 'MDF')->firstOrFail();
    $names = fn (string $type) => AnimalBreed::allFarms()->where('farm_id', $farm->id)->where('animal_type', $type)->orderBy('id')->pluck('name')->all();

    expect($names('cow'))->toBe(['HF', 'Jersey', 'Sahiwal', 'Gir', 'Tharparkar', 'Red Sindhi', 'Crossbred HF', 'Crossbred Jersey', 'Desi'])
        ->and($names('buffalo'))->toBe(['Murrah', 'Nili-Ravi', 'Jaffarabadi', 'Mehsana', 'Bhadawari', 'Surti', 'Desi']);

    AnimalBreed::allFarms()->where('farm_id', $farm->id)->where('name', 'Gir')->update(['is_active' => false]);
    AnimalBreed::seedDefaultsFor($farm); // again: nothing new, an inactive default stays inactive
    expect(AnimalBreed::allFarms()->where('farm_id', $farm->id)->count())->toBe(16)
        ->and(AnimalBreed::allFarms()->where('farm_id', $farm->id)->where('name', 'Gir')->value('is_active'))->toBeFalse();
});

it('lists breeds with type / status / search filters over AJAX', function () {
    AnimalBreed::seedDefaultsFor($this->farm);
    AnimalBreed::allFarms()->where('farm_id', $this->farm->id)->where('name', 'Surti')->update(['is_active' => false]);
    AnimalBreed::factory()->create(['farm_id' => Farm::factory()->create()->id, 'name' => 'Foreign Breed']);

    $this->actingAs($this->owner)->get(route('animal-breeds.index'))->assertOk()
        ->assertSee('Sahiwal')->assertSee('Murrah')->assertDontSee('Foreign Breed')
        ->assertSee(route('animal-breeds.create'), false);

    $this->get(route('animal-breeds.index', ['type' => 'buffalo']), ['X-Ajax-List' => '1'])
        ->assertOk()->assertHeader('X-Ajax-List-Fragment', '1')
        ->assertSee('Murrah')->assertDontSee('Sahiwal');

    $this->get(route('animal-breeds.index', ['status' => 'inactive']))->assertSee('Surti')->assertDontSee('Murrah');
    $this->get(route('animal-breeds.index', ['search' => 'cross']))->assertSee('Crossbred HF')->assertDontSee('Gir');
});

it('adds and edits a breed; the name is unique per farm and type, ignoring capitals', function () {
    $this->actingAs($this->owner);
    AnimalBreed::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Sahiwal']);

    $this->post(route('animal-breeds.store'), ['animal_type' => 'cow', 'name' => '  sahiwal ', 'is_active' => '1'])
        ->assertSessionHasErrors(['name' => 'This breed already exists for this animal type.']);

    // same name for the other type, or in another farm, is fine
    $this->post(route('animal-breeds.store'), ['animal_type' => 'buffalo', 'name' => 'Sahiwal', 'is_active' => '1'])
        ->assertRedirect(route('animal-breeds.index', ['type' => 'buffalo']))->assertSessionHas('success');
    $this->post(route('animal-breeds.store'), ['animal_type' => 'cow', 'name' => 'Kankrej   Cross', 'is_active' => '1'])->assertSessionHasNoErrors();

    $kankrej = AnimalBreed::where('name', 'Kankrej Cross')->sole();
    expect($kankrej->animal_type)->toBe(AnimalType::Cow)->and($kankrej->created_by)->toBe($this->owner->id);

    $this->put(route('animal-breeds.update', $kankrej), ['animal_type' => 'cow', 'name' => 'Kankrej', 'is_active' => '0'])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($kankrej->fresh()->only(['name', 'is_active']))->toBe(['name' => 'Kankrej', 'is_active' => false]);

    $this->post(route('animal-breeds.store'), ['animal_type' => 'goat', 'name' => 'X'])->assertSessionHasErrors('animal_type');
    $this->post(route('animal-breeds.store'), ['animal_type' => 'cow', 'name' => str_repeat('a', 61)])->assertSessionHasErrors('name');
});

it('lets herd staff view breeds but not change them, and hides other farms\' breeds', function () {
    $breed = AnimalBreed::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Gir']);

    $this->actingAs($this->herdStaff)->get(route('animal-breeds.index'))->assertOk()
        ->assertSee('Gir')->assertDontSee(route('animal-breeds.create'), false)->assertDontSee(route('animal-breeds.edit', $breed), false);
    $this->actingAs($this->herdStaff)->get(route('animal-breeds.create'))->assertForbidden();
    $this->actingAs($this->herdStaff)->post(route('animal-breeds.store'), ['animal_type' => 'cow', 'name' => 'New'])->assertForbidden();
    $this->actingAs($this->herdStaff)->put(route('animal-breeds.update', $breed), ['animal_type' => 'cow', 'name' => 'X'])->assertForbidden();

    $this->actingAs($this->herdStaff)->get(route('dashboard'))->assertSee(route('animal-breeds.index'), false); // Herd menu

    $accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create(); // no animals.view
    $this->flushSession();
    $this->actingAs($accountant)->get(route('animal-breeds.index'))->assertForbidden();

    $other = User::factory()->forFarm(Farm::factory()->create())->withRole(RoleName::Owner)->create();
    $this->flushSession();
    $this->actingAs($other)->get(route('animal-breeds.edit', $breed))->assertNotFound();
    $this->actingAs($other)->put(route('animal-breeds.update', $breed), ['animal_type' => 'cow', 'name' => 'Hijack', 'is_active' => '1'])->assertNotFound();
    expect($breed->fresh()->name)->toBe('Gir');
});
