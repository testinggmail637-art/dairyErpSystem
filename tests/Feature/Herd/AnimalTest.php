<?php

use App\Enums\AnimalStatus;
use App\Enums\LifecycleStage;
use App\Enums\RoleName;
use App\Models\Activity;
use App\Models\Animal;
use App\Models\AnimalBreed;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->travelTo('2026-10-19 10:00');

    $this->farm = Farm::factory()->create();
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->herdStaff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();

    $this->sahiwal = AnimalBreed::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Sahiwal']);
    $this->murrah = AnimalBreed::factory()->buffalo()->create(['farm_id' => $this->farm->id, 'name' => 'Murrah']);
});

function animalPayload(array $overrides = []): array
{
    return array_merge([
        'tag_no' => 'C-101', 'name' => 'Gauri', 'animal_type' => 'cow', 'breed_id' => test()->sahiwal->id, 'gender' => 'female',
        'date_of_birth' => '2021-04-10', 'dob_is_estimated' => '0', 'source' => 'opening', 'ownership_type' => 'own_herd',
        'lifecycle_stage' => 'milking', 'opening_value' => '65000', 'lactation_number' => '2', 'color_marks' => 'White with brown patch',
    ], $overrides);
}

it('adds an animal with a photo (resized + thumbnail) and opens its profile', function () {
    $this->actingAs($this->owner);

    $response = $this->post(route('animals.store'), animalPayload(['photo' => UploadedFile::fake()->image('cow.jpg', 3000, 2000)]));

    $animal = Animal::sole();
    $response->assertRedirect(route('animals.show', $animal))->assertSessionHas('success', 'Animal C-101 added.');
    expect($animal->only(['tag_no', 'name', 'breed_id', 'opening_value', 'lactation_number', 'created_by']))->toBe([
        'tag_no' => 'C-101', 'name' => 'Gauri', 'breed_id' => $this->sahiwal->id, 'opening_value' => '65000.00',
        'lactation_number' => 2, 'created_by' => $this->owner->id,
    ])->and($animal->status)->toBe(AnimalStatus::Active)
        ->and($animal->photo_path)->toStartWith("animals/{$this->farm->id}/");

    Storage::disk('public')->assertExists([$animal->photo_path, str_replace('.jpg', '_thumb.jpg', $animal->photo_path)]);
    [$width] = getimagesizefromstring(Storage::disk('public')->get($animal->photo_path));
    expect($width)->toBe(1200);

    $this->get(route('animals.show', $animal))->assertOk()
        ->assertSee('C-101')->assertSee('Gauri')->assertSee('Sahiwal')->assertSee('5 y 6 m')
        ->assertSee('₹65,000.00')->assertSee('Animal added');
});

it('keeps the tag unique per farm, deleted animals included', function () {
    $this->actingAs($this->owner);
    Animal::factory()->create(['farm_id' => $this->farm->id, 'tag_no' => 'C-101']);
    $deleted = Animal::factory()->create(['farm_id' => $this->farm->id, 'tag_no' => 'C-102']);
    $deleted->delete();
    Animal::factory()->create(['farm_id' => Farm::factory()->create()->id, 'tag_no' => 'C-103']);

    $message = 'This tag number is already used in this farm (also by deleted animals).';
    $this->post(route('animals.store'), animalPayload(['tag_no' => ' c-101 ']))->assertSessionHasErrors(['tag_no' => $message]);
    $this->post(route('animals.store'), animalPayload(['tag_no' => 'C-102']))->assertSessionHasErrors(['tag_no' => $message]);
    $this->post(route('animals.store'), animalPayload(['tag_no' => 'C-103']))->assertSessionHasNoErrors(); // other farm's tag is fine
});

it('applies the animal rules: breed and mother of the same type, males only calf or bull', function (array $overrides, string $field, string $message) {
    $this->actingAs($this->owner);
    $this->buffaloMother = Animal::factory()->buffalo()->create(['farm_id' => $this->farm->id]);
    $this->bull = Animal::factory()->male()->create(['farm_id' => $this->farm->id]);
    $overrides = array_map(fn ($v) => $v instanceof Closure ? $v() : $v, $overrides);

    $this->post(route('animals.store'), animalPayload($overrides))->assertSessionHasErrors([$field => $message]);
    expect(Animal::where('tag_no', 'C-101')->exists())->toBeFalse();
})->with([
    'buffalo breed on a cow' => [['breed_id' => fn () => test()->murrah->id], 'breed_id', 'Choose an active breed of the same animal type.'],
    'buffalo mother for a cow' => [['mother_id' => fn () => test()->buffaloMother->id], 'mother_id', 'The mother must be a female animal of the same type.'],
    'male mother' => [['mother_id' => fn () => test()->bull->id], 'mother_id', 'The mother must be a female animal of the same type.'],
    'milking male' => [['gender' => 'male', 'lifecycle_stage' => 'milking'], 'lifecycle_stage', 'A male animal can only be a calf or a bull.'],
    'female bull' => [['lifecycle_stage' => 'bull'], 'lifecycle_stage', 'A female animal cannot be a bull.'],
    'future birth' => [['date_of_birth' => '2026-10-20'], 'date_of_birth', 'The date of birth cannot be in the future.'],
]);

it('refuses an inactive breed for a new animal, but an existing animal keeps its own', function () {
    $this->actingAs($this->owner);
    $animal = Animal::factory()->create(['farm_id' => $this->farm->id, 'breed_id' => $this->sahiwal->id]);
    $this->sahiwal->update(['is_active' => false]);

    $this->post(route('animals.store'), animalPayload(['tag_no' => 'C-900']))->assertSessionHasErrors('breed_id');
    $this->put(route('animals.update', $animal), animalPayload(['tag_no' => $animal->tag_no, 'reason' => 'Correcting the name']))
        ->assertSessionHasNoErrors();
});

it('stores the opening value only for animals existing at go-live and lactations only for females', function () {
    $this->actingAs($this->owner);

    $this->post(route('animals.store'), animalPayload(['tag_no' => 'C-201', 'source' => 'purchased', 'opening_value' => '50000']));
    $this->post(route('animals.store'), animalPayload(['tag_no' => 'C-202', 'gender' => 'male', 'lifecycle_stage' => 'calf', 'lactation_number' => '3']));

    expect(Animal::where('tag_no', 'C-201')->value('opening_value'))->toBeNull()
        ->and(Animal::where('tag_no', 'C-202')->value('lactation_number'))->toBe(0);
});

it('edits an animal with a reason (audit log), replaces and removes the photo', function () {
    $this->actingAs($this->owner);
    $this->post(route('animals.store'), animalPayload(['photo' => UploadedFile::fake()->image('a.jpg', 800, 600)]));
    $animal = Animal::sole();
    $firstPhoto = $animal->photo_path;

    $this->put(route('animals.update', $animal), animalPayload(['name' => 'Gauri II']))->assertSessionHasErrors('reason');

    $this->put(route('animals.update', $animal), animalPayload([
        'name' => 'Gauri II', 'lifecycle_stage' => 'dry', 'reason' => 'Dried off this week',
        'photo' => UploadedFile::fake()->image('b.png', 500, 500),
    ]))->assertRedirect(route('animals.show', $animal));

    $animal->refresh();
    expect($animal->name)->toBe('Gauri II')->and($animal->lifecycle_stage)->toBe(LifecycleStage::Dry)
        ->and($animal->photo_path)->not->toBe($firstPhoto);
    Storage::disk('public')->assertMissing($firstPhoto);

    $log = Activity::where('subject_type', $animal->getMorphClass())->where('event', 'updated')->latest('id')->first();
    expect($log->reason())->toBe('Dried off this week')
        ->and($log->properties['old']['lifecycle_stage'])->toBe('milking')
        ->and($log->properties['attributes']['lifecycle_stage'])->toBe('dry');

    $this->get(route('animals.show', $animal))->assertSee('Dried off this week');

    $secondPhoto = $animal->photo_path;
    $this->put(route('animals.update', $animal), animalPayload(['name' => 'Gauri II', 'lifecycle_stage' => 'dry', 'remove_photo' => '1', 'reason' => 'Blurry photo']));
    expect($animal->fresh()->photo_path)->toBeNull();
    Storage::disk('public')->assertMissing($secondPhoto);
});

it('lists active animals by default with filters, search, stage counts and AJAX paging', function () {
    $this->actingAs($this->owner);
    Animal::factory()->create(['farm_id' => $this->farm->id, 'tag_no' => 'C-1', 'name' => 'Lakshmi']);
    Animal::factory()->stage(LifecycleStage::Dry)->create(['farm_id' => $this->farm->id, 'tag_no' => 'C-2']);
    Animal::factory()->buffalo()->create(['farm_id' => $this->farm->id, 'tag_no' => 'B-1', 'breed_id' => $this->murrah->id]);
    Animal::factory()->status(AnimalStatus::Sold)->create(['farm_id' => $this->farm->id, 'tag_no' => 'C-SOLD']);
    Animal::factory()->create(['farm_id' => Farm::factory()->create()->id, 'tag_no' => 'X-OTHER']);

    $this->get(route('animals.index'))->assertOk()
        ->assertSee('C-1')->assertSee('B-1')->assertDontSee('C-SOLD')->assertDontSee('X-OTHER')
        ->assertSee('Milking: 1')->assertSee('Dry: 1');

    $ajax = fn (array $q) => $this->get(route('animals.index', $q), ['X-Ajax-List' => '1'])->assertOk()->assertHeader('X-Ajax-List-Fragment', '1');
    $ajax(['type' => 'buffalo'])->assertSee('B-1')->assertDontSee('C-1');
    $ajax(['stage' => 'dry'])->assertSee('C-2')->assertDontSee('B-1');
    $ajax(['status' => 'sold'])->assertSee('C-SOLD')->assertDontSee('C-1');
    $ajax(['status' => 'all'])->assertSee('C-SOLD')->assertSee('C-1');
    $ajax(['search' => 'laksh'])->assertSee('C-1')->assertDontSee('C-2');
    $ajax(['breed_id' => $this->murrah->id])->assertSee('B-1')->assertDontSee('C-1');
});

it('searches mothers for the form: females of one type, never the animal itself', function () {
    $this->actingAs($this->owner);
    $cow = Animal::factory()->create(['farm_id' => $this->farm->id, 'tag_no' => 'C-10', 'name' => 'Ganga']);
    Animal::factory()->create(['farm_id' => $this->farm->id, 'tag_no' => 'C-11', 'name' => null]);
    Animal::factory()->male()->create(['farm_id' => $this->farm->id, 'tag_no' => 'C-12']);
    Animal::factory()->buffalo()->create(['farm_id' => $this->farm->id, 'tag_no' => 'B-10']);

    $ids = fn (array $q) => collect($this->getJson(route('animals.search', $q))->assertOk()->json('results'))->pluck('text')->all();

    expect($ids(['q' => 'C-1', 'type' => 'cow', 'gender' => 'female']))->toBe(['C-10 · Ganga', 'C-11'])
        ->and($ids(['q' => 'C-1', 'type' => 'cow', 'gender' => 'female', 'except' => $cow->id]))->toBe(['C-11'])
        ->and($ids(['q' => 'ganga']))->toBe(['C-10 · Ganga']);

    $this->getJson(route('animal-breeds.by-type', 'buffalo'))->assertOk()->assertExactJson([(string) $this->murrah->id => 'Murrah']);
    $this->getJson(route('animal-breeds.by-type', 'goat'))->assertNotFound();
});

it('soft-deletes only an animal without records, with a reason', function () {
    $this->actingAs($this->owner);
    $free = Animal::factory()->create(['farm_id' => $this->farm->id]);
    $withExpense = Animal::factory()->create(['farm_id' => $this->farm->id, 'tag_no' => 'C-EXP']);
    Expense::factory()->create(['farm_id' => $this->farm->id, 'animal_id' => $withExpense->id]);
    $mother = Animal::factory()->create(['farm_id' => $this->farm->id, 'tag_no' => 'C-MOM']);
    Animal::factory()->stage(LifecycleStage::Calf)->create(['farm_id' => $this->farm->id, 'mother_id' => $mother->id]);

    $this->delete(route('animals.destroy', $free))->assertSessionHasErrors('reason');
    $this->delete(route('animals.destroy', $free), ['reason' => 'Entered twice'])->assertRedirect(route('animals.index'));
    expect(Animal::find($free->id))->toBeNull()->and(Animal::withTrashed()->find($free->id))->not->toBeNull();

    $this->delete(route('animals.destroy', $withExpense), ['reason' => 'Try'])
        ->assertSessionHas('error', 'This animal cannot be deleted because it has 1 expenses. Change its status instead (sold, deceased …).');
    $this->delete(route('animals.destroy', $mother), ['reason' => 'Try'])->assertSessionHas('error');
    expect(Animal::count())->toBe(3); // the two refused + the calf
});

it('lets herd staff view animals but not change them; other farms see 404', function () {
    $animal = Animal::factory()->create(['farm_id' => $this->farm->id]);

    $this->actingAs($this->herdStaff)->get(route('animals.index'))->assertOk()->assertDontSee(route('animals.create'), false);
    $this->actingAs($this->herdStaff)->get(route('animals.show', $animal))->assertOk()->assertDontSee(route('animals.edit', $animal), false);
    $this->actingAs($this->herdStaff)->get(route('animals.create'))->assertForbidden();
    $this->actingAs($this->herdStaff)->post(route('animals.store'), animalPayload())->assertForbidden();
    $this->actingAs($this->herdStaff)->put(route('animals.update', $animal), animalPayload(['reason' => 'x x x']))->assertForbidden();
    $this->actingAs($this->herdStaff)->delete(route('animals.destroy', $animal), ['reason' => 'x x x'])->assertForbidden();

    $other = User::factory()->forFarm(Farm::factory()->create())->withRole(RoleName::Owner)->create();
    $this->flushSession();
    $this->actingAs($other)->get(route('animals.show', $animal))->assertNotFound();
    $this->actingAs($other)->put(route('animals.update', $animal), animalPayload(['reason' => 'x x x']))->assertNotFound();
    expect(collect($this->actingAs($other)->getJson(route('animals.search', ['q' => $animal->tag_no]))->json('results')))->toBeEmpty();
});
