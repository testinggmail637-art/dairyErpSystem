<?php

use App\Enums\AnimalSource;
use App\Enums\LifecycleStage;
use App\Enums\RoleName;
use App\Models\Animal;
use App\Models\AnimalBreed;
use App\Models\AnimalStatusHistory;
use App\Models\BreedingEvent;
use App\Models\Calving;
use App\Models\Farm;
use App\Models\Treatment;
use App\Models\User;
use App\Services\BreedingService;
use App\Support\CurrentFarm;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->farm = Farm::where('code', 'MDF')->firstOrFail();
    app(CurrentFarm::class)->set($this->farm);
    $this->travelTo('2027-10-10 10:00');
    $this->staff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $this->actingAs($this->staff);
    $this->gir = AnimalBreed::where('name', 'Gir')->firstOrFail();
    $this->cow = Animal::factory()->stage(LifecycleStage::Dry)->create(['farm_id' => $this->farm->id, 'tag_no' => 'C-7',
        'breed_id' => $this->gir->id, 'lactation_number' => 2]);
    $this->post(route('breeding.store'), ['animal_id' => $this->cow->id, 'event_type' => 'ai', 'event_date' => '2027-01-01',
        'sire_info' => 'Gir bull straw 55', 'cost' => '0'])->assertSessionHasNoErrors();
    $this->post(route('breeding.store'), ['animal_id' => $this->cow->id, 'event_type' => 'pregnancy_check', 'event_date' => '2027-03-01',
        'result' => 'positive', 'cost' => '0'])->assertSessionHasNoErrors();
});

function calve(array $overrides = []): TestResponse
{
    return test()->post(route('breeding.calvings.store'), array_merge(['mother_id' => test()->cow->id, 'calving_date' => '2027-10-08',
        'outcome' => 'alive', 'calf_gender' => 'female', 'calf_tag_no' => 'C-7-1'], $overrides));
}

it('creates the calf with its mother and raises the lactation number (spec acceptance)', function () {
    expect(app(BreedingService::class)->isPregnant($this->cow))->toBeTrue();

    calve()->assertSessionHasNoErrors()->assertSessionHas('success', 'Calving saved: C-7 is in lactation 3, calf C-7-1 added to the herd.');

    $calf = Animal::where('tag_no', 'C-7-1')->sole();
    $cow = $this->cow->fresh();
    expect($calf)->mother_id->toBe($cow->id)->source->toBe(AnimalSource::BornOnFarm)->lifecycle_stage->toBe(LifecycleStage::Calf)
        ->animal_type->toBe($cow->animal_type)->breed_id->toBe($this->gir->id)->sire_info->toBe('Gir bull straw 55')
        ->and($calf->date_of_birth->toDateString())->toBe('2027-10-08')
        ->and(Calving::sole()->calf_id)->toBe($calf->id)
        ->and($cow)->lactation_number->toBe(3)->lifecycle_stage->toBe(LifecycleStage::Milking)
        ->and(AnimalStatusHistory::where('animal_id', $cow->id)->where('new_value', 'milking')->count())->toBe(1)
        // the calving ends the pregnancy and the expected calving
        ->and(app(BreedingService::class)->isPregnant($cow))->toBeFalse()
        ->and(app(BreedingService::class)->expectedCalvings('2027-01-01', '2027-12-31')->count())->toBe(0);

    $this->get(route('breeding.calvings.index'))->assertOk()->assertSee('C-7-1');
});

it('starts a lactation without a calf when stillborn; checks tag, gender, mother and duplicates', function () {
    calve(['calf_tag_no' => ''])->assertSessionHasErrors('calf_tag_no');
    calve(['calf_gender' => ''])->assertSessionHasErrors('calf_gender');
    calve(['calf_tag_no' => 'C-7'])->assertSessionHasErrors('calf_tag_no'); // tag in use
    $bull = Animal::factory()->male()->create(['farm_id' => $this->farm->id]);
    calve(['mother_id' => $bull->id])->assertSessionHasErrors('mother_id');

    calve(['outcome' => 'stillborn', 'calf_tag_no' => '', 'calf_gender' => 'male'])->assertSessionHasNoErrors();
    expect(Calving::sole()->calf_id)->toBeNull()->and(Animal::count())->toBe(2)->and($this->cow->fresh()->lactation_number)->toBe(3);

    calve()->assertSessionHasErrors('mother_id'); // same mother, same date
});

it('undoes a calving: lactation back, calf removed — not when the calf already has records', function () {
    calve()->assertSessionHasNoErrors();
    $calving = Calving::sole();
    $calf = Animal::where('tag_no', 'C-7-1')->sole();

    Treatment::factory()->create(['farm_id' => $this->farm->id, 'animal_id' => $calf->id]);
    $this->delete(route('breeding.calvings.destroy', $calving), ['reason' => 'Wrong cow'])->assertSessionHas('error');

    Treatment::query()->delete();
    $this->delete(route('breeding.calvings.destroy', $calving))->assertSessionHasErrors('reason');
    $this->delete(route('breeding.calvings.destroy', $calving), ['reason' => 'Wrong cow'])->assertSessionHasNoErrors();
    expect(Calving::count())->toBe(0)->and($calf->fresh()->trashed())->toBeTrue()->and($this->cow->fresh()->lactation_number)->toBe(2);
});

it('blocks deleting an animal that has health or breeding records, and is for breeding.manage only', function () {
    $owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->actingAs($owner)->delete(route('animals.destroy', $this->cow), ['reason' => 'Test'])->assertSessionHas('error');
    expect($this->cow->fresh()->trashed())->toBeFalse()->and(BreedingEvent::count())->toBe(2);

    $accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
    $this->actingAs($accountant)->get(route('breeding.calvings.index'))->assertForbidden();
    calve()->assertForbidden();
});
