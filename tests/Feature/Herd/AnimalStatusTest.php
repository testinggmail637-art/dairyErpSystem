<?php

use App\Enums\AnimalStatus;
use App\Enums\LifecycleStage;
use App\Enums\OwnershipType;
use App\Enums\RoleName;
use App\Models\Animal;
use App\Models\AnimalStatusHistory;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->travelTo('2026-10-19 10:00');

    $this->farm = Farm::factory()->create();
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create(['name' => 'Owner Ji']);
    $this->animal = Animal::factory()->create(['farm_id' => $this->farm->id, 'tag_no' => 'C-101', 'date_of_birth' => '2021-04-10']);
    $this->actingAs($this->owner);
});

function changeAnimal(array $data)
{
    return test()->post(route('animals.change', test()->animal), $data + ['changed_on' => '2026-10-18', 'reason' => 'Vet advice']);
}

it('records starting rows when an animal is added, then one row per change (status, stage, ownership)', function () {
    expect($this->animal->statusHistories()->orderBy('id')->get(['field', 'old_value', 'new_value'])->toArray())->toBe([
        ['field' => 'status', 'old_value' => null, 'new_value' => 'active'],
        ['field' => 'lifecycle_stage', 'old_value' => null, 'new_value' => 'milking'],
        ['field' => 'ownership_type', 'old_value' => null, 'new_value' => 'own_herd'],
    ]);

    changeAnimal(['field' => 'lifecycle_stage', 'value' => 'dry'])
        ->assertRedirect(route('animals.show', $this->animal))->assertSessionHas('success', 'Stage of C-101 changed.');

    $row = AnimalStatusHistory::latest('id')->first();
    expect($row->only(['field', 'old_value', 'new_value', 'reason', 'changed_by', 'farm_id']))->toBe([
        'field' => 'lifecycle_stage', 'old_value' => 'milking', 'new_value' => 'dry', 'reason' => 'Vet advice',
        'changed_by' => $this->owner->id, 'farm_id' => $this->farm->id,
    ])->and($row->changed_on->toDateString())->toBe('2026-10-18')
        ->and($this->animal->fresh()->lifecycle_stage)->toBe(LifecycleStage::Dry);

    changeAnimal(['field' => 'ownership_type', 'value' => 'resale']);
    expect($this->animal->fresh()->ownership_type)->toBe(OwnershipType::Resale)
        ->and($this->animal->statusHistories()->count())->toBe(5);
});

it('changes the status with its date, which takes the animal out of milk entry', function () {
    expect(Animal::milking()->count())->toBe(1);

    changeAnimal(['field' => 'status', 'value' => 'deceased', 'changed_on' => '2026-10-17', 'reason' => 'Died of illness']);

    $animal = $this->animal->fresh();
    expect($animal->status)->toBe(AnimalStatus::Deceased)
        ->and($animal->status_changed_on->toDateString())->toBe('2026-10-17')
        ->and(Animal::milking()->count())->toBe(0);

    $this->get(route('animals.show', $animal))->assertOk()
        ->assertSee('Died of illness')->assertSee('Owner Ji')->assertSee('17-10-2026');
    $this->get(route('animals.index'))->assertDontSee('C-101'); // list opens on active animals

    // marked by mistake → back to active
    changeAnimal(['field' => 'status', 'value' => 'active', 'reason' => 'Marked by mistake'])->assertSessionHasNoErrors();
    expect($this->animal->fresh()->status)->toBe(AnimalStatus::Active);
});

it('also records stage and ownership changes made on the edit form', function () {
    $this->put(route('animals.update', $this->animal), [
        'tag_no' => 'C-101', 'animal_type' => 'cow', 'gender' => 'female', 'source' => 'opening', 'ownership_type' => 'own_herd',
        'lifecycle_stage' => 'dry', 'reason' => 'Dry from today',
    ])->assertSessionHasNoErrors();

    $row = AnimalStatusHistory::latest('id')->first();
    expect($row->only(['field', 'old_value', 'new_value', 'reason']))->toBe([
        'field' => 'lifecycle_stage', 'old_value' => 'milking', 'new_value' => 'dry', 'reason' => 'Dry from today',
    ])->and($row->changed_on->toDateString())->toBe('2026-10-19')
        ->and($this->animal->statusHistories()->count())->toBe(4); // a plain name change would add none
});

it('validates the change', function (array $data, string $field, string $message) {
    changeAnimal($data)->assertSessionHasErrors([$field => $message]);
    expect($this->animal->statusHistories()->count())->toBe(3);
})->with([
    'same value' => [['field' => 'lifecycle_stage', 'value' => 'milking'], 'value', 'The animal already has this value.'],
    'sold by hand' => [['field' => 'status', 'value' => 'sold'], 'value', 'Choose one of the listed values.'],
    'female bull' => [['field' => 'lifecycle_stage', 'value' => 'bull'], 'value', 'This stage does not fit the animal (males are only calf or bull).'],
    'future date' => [['field' => 'status', 'value' => 'culled', 'changed_on' => '2026-10-20'], 'changed_on', 'The date cannot be in the future.'],
    'before birth' => [['field' => 'status', 'value' => 'culled', 'changed_on' => '2021-01-01'], 'changed_on', 'The date cannot be before the animal was born.'],
    'no reason' => [['field' => 'status', 'value' => 'culled', 'reason' => ''], 'reason', 'The reason field is required.'],
    'unknown field' => [['field' => 'tag_no', 'value' => 'X'], 'field', 'The selected field is invalid.'],
]);

it('refuses to make a sold animal active by hand', function () {
    $this->animal->update(['status' => AnimalStatus::Sold]);

    changeAnimal(['field' => 'status', 'value' => 'active'])
        ->assertSessionHasErrors(['value' => 'A sold animal becomes active again only by cancelling its sale.']);
});

it('reopens the action form with its errors after a failed save', function () {
    $html = $this->from(route('animals.show', $this->animal))->followingRedirects()
        ->post(route('animals.change', $this->animal), ['field' => 'status', 'value' => 'culled', 'changed_on' => '2026-10-18', 'reason' => ''])
        ->assertOk()->getContent();

    expect($html)->toMatch('/id="change-status"[^>]*data-show-on-load/')
        ->not->toMatch('/id="change-lifecycle_stage"[^>]*data-show-on-load/');
});

it('needs animals.manage and stays inside the farm', function () {
    $herdStaff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $this->flushSession();
    $this->actingAs($herdStaff)->get(route('animals.show', $this->animal))->assertOk()->assertDontSee('Change status');
    $this->actingAs($herdStaff)->post(route('animals.change', $this->animal), ['field' => 'status', 'value' => 'culled', 'changed_on' => '2026-10-18', 'reason' => 'Try'])
        ->assertForbidden();

    $other = User::factory()->forFarm(Farm::factory()->create())->withRole(RoleName::Owner)->create();
    $this->flushSession();
    $this->actingAs($other)->post(route('animals.change', $this->animal), ['field' => 'status', 'value' => 'culled', 'changed_on' => '2026-10-18', 'reason' => 'Try'])
        ->assertNotFound();

    expect($this->animal->fresh()->status)->toBe(AnimalStatus::Active);
});
