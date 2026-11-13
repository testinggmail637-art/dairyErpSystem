<?php

use App\Enums\LifecycleStage;
use App\Enums\RoleName;
use App\Models\Animal;
use App\Models\AnimalStatusHistory;
use App\Models\BreedingEvent;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\PaymentMode;
use App\Models\User;
use App\Services\BreedingService;
use App\Support\CurrentFarm;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->farm = Farm::where('code', 'MDF')->firstOrFail();
    app(CurrentFarm::class)->set($this->farm);
    $this->travelTo('2027-03-01 10:00');
    $this->staff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $this->actingAs($this->staff);
    $this->cow = Animal::factory()->create(['farm_id' => $this->farm->id, 'tag_no' => 'C-7']); // milking
    $this->heifer = Animal::factory()->stage(LifecycleStage::Heifer)->create(['farm_id' => $this->farm->id, 'tag_no' => 'H-1']);
    $this->breeding = app(BreedingService::class);
});

function breed(Animal $animal, string $type, string $date, array $extra = []): TestResponse
{
    return test()->post(route('breeding.store'), array_merge(['animal_id' => $animal->id, 'event_type' => $type, 'event_date' => $date,
        'cost' => '0', 'record_as_expense' => '0'], $extra));
}

it('sets expected calving from AI: cow 01-01-2027 → 08-10-2027 (280 days, spec acceptance); buffalo 310 days', function () {
    breed($this->cow, 'ai', '2027-01-01', ['sire_info' => 'HF straw 123'])->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'AI saved. Expected calving: 08-10-2027.');
    expect(BreedingEvent::sole()->expected_calving_date->toDateString())->toBe('2027-10-08');

    $buffalo = Animal::factory()->buffalo()->create(['farm_id' => $this->farm->id]);
    breed($buffalo, 'natural_service', '2027-01-01')->assertSessionHasNoErrors();
    expect(BreedingEvent::where('animal_id', $buffalo->id)->sole()->expected_calving_date->toDateString())->toBe('2027-11-07');
});

it('confirms pregnancy: heifer → pregnant heifer (history), milking cow stays milking but shows pregnant; negative / abortion undo', function () {
    breed($this->heifer, 'ai', '2027-01-10')->assertSessionHasNoErrors();
    breed($this->heifer, 'pregnancy_check', '2027-02-20')->assertSessionHasErrors('result');
    breed($this->heifer, 'pregnancy_check', '2027-02-20', ['result' => 'positive'])->assertSessionHasNoErrors();

    $check = BreedingEvent::where('event_type', 'pregnancy_check')->sole();
    expect($check->expected_calving_date->toDateString())->toBe('2027-10-17') // copied from the AI
        ->and($this->heifer->fresh()->lifecycle_stage)->toBe(LifecycleStage::PregnantHeifer)
        ->and(AnimalStatusHistory::where('animal_id', $this->heifer->id)->where('new_value', 'pregnant_heifer')->value('changed_on')->toDateString())->toBe('2027-02-20')
        ->and($this->breeding->isPregnant($this->heifer))->toBeTrue();

    breed($this->cow, 'ai', '2027-01-05')->assertSessionHasNoErrors();
    breed($this->cow, 'pregnancy_check', '2027-02-25', ['result' => 'positive'])->assertSessionHasNoErrors();
    expect($this->cow->fresh()->lifecycle_stage)->toBe(LifecycleStage::Milking)->and($this->breeding->isPregnant($this->cow))->toBeTrue();
    $this->get(route('animals.show', $this->cow))->assertOk()->assertSee('Pregnant');

    breed($this->heifer, 'abortion', '2027-02-28')->assertSessionHasNoErrors();
    expect($this->heifer->fresh()->lifecycle_stage)->toBe(LifecycleStage::Heifer)->and($this->breeding->isPregnant($this->heifer))->toBeFalse();

    breed($this->cow, 'pregnancy_check', '2027-03-01', ['result' => 'negative'])->assertSessionHasNoErrors();
    expect($this->breeding->isPregnant($this->cow))->toBeFalse();
});

it('lists expected calvings from each animal\'s latest service or positive check only', function () {
    breed($this->cow, 'ai', '2026-07-01')->assertSessionHasNoErrors();      // due 07-04-2027
    breed($this->heifer, 'ai', '2026-06-20')->assertSessionHasNoErrors();   // due 27-03-2027
    breed($this->heifer, 'pregnancy_check', '2026-08-20', ['result' => 'negative'])->assertSessionHasNoErrors(); // not pregnant after all

    $rows = $this->breeding->expectedCalvings('2027-01-01', '2027-12-31')->get();
    expect($rows->pluck('animal_id')->all())->toBe([$this->cow->id])
        ->and($rows[0]->expected_calving_date)->toBe('2027-04-07');

    $this->get(route('breeding.index'))->assertOk()->assertSee('Expected calvings')->assertSee('07-04-2027')->assertSee('C-7');
});

it('records the cost as a Health → AI / Breeding expense; edit and delete need a reason', function () {
    $cash = PaymentMode::where('code', 'CASH')->value('id');
    breed($this->cow, 'ai', '2027-02-01', ['cost' => '350', 'record_as_expense' => '1', 'payment_mode_id' => $cash, 'technician' => 'Ramu (AI tech)'])
        ->assertSessionHasNoErrors();
    $event = BreedingEvent::sole();
    expect(Expense::sole())->amount->toBe('350.00')->paid_to_text->toBe('Ramu (AI tech)')
        ->and(Expense::sole()->subcategory->name)->toBe('AI / Breeding');

    $data = ['animal_id' => $this->cow->id, 'event_type' => 'ai', 'event_date' => '2027-02-01', 'cost' => '400', 'record_as_expense' => '1', 'payment_mode_id' => $cash];
    $this->put(route('breeding.update', $event), $data)->assertSessionHasErrors('reason');
    $this->put(route('breeding.update', $event), $data + ['reason' => 'Rate was 400'])->assertSessionHasNoErrors();
    expect(Expense::sole()->amount)->toBe('400.00');

    $this->delete(route('breeding.destroy', $event), ['reason' => 'Wrong cow'])->assertSessionHasNoErrors();
    expect(BreedingEvent::count())->toBe(0)->and(Expense::count())->toBe(0);
});

it('is only for female animals and breeding.manage users', function () {
    $bull = Animal::factory()->male()->create(['farm_id' => $this->farm->id]);
    breed($bull, 'heat', '2027-02-01')->assertSessionHasErrors('animal_id');

    $accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
    $this->actingAs($accountant)->get(route('breeding.index'))->assertForbidden();
    breed($this->cow, 'heat', '2027-02-01')->assertForbidden();
});
