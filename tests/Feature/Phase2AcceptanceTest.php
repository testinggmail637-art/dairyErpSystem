<?php

/*
| Phase 2 acceptance criteria (spec Section 8), end to end over HTTP, in the order the spec lists them,
| on the production seed data (default farm, breeds). Detailed behaviour is covered by the per-feature tests
| in tests/Feature/Herd and tests/Feature/Reports.
*/

use App\Enums\MilkSource;
use App\Enums\MilkType;
use App\Enums\RoleName;
use App\Models\Animal;
use App\Models\AnimalBreed;
use App\Models\Farm;
use App\Models\MilkProductionRecord;
use App\Models\MilkStockMovement;
use App\Models\User;
use App\Services\MilkStockService;
use App\Support\CurrentFarm;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->travelTo('2026-10-28 08:00'); // morning shift
    $this->seed(DatabaseSeeder::class);

    $this->farm = Farm::where('code', 'MDF')->firstOrFail();
    app(CurrentFarm::class)->set($this->farm);
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->herdStaff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $this->gir = AnimalBreed::where('animal_type', 'cow')->where('name', 'Gir')->firstOrFail();
    $this->stock = app(MilkStockService::class);
});

function acceptanceAnimal(array $overrides = []): array
{
    return array_merge([
        'tag_no' => 'C-1', 'name' => '', 'animal_type' => 'cow', 'breed_id' => test()->gir->id, 'gender' => 'female',
        'date_of_birth' => '2021-04-10', 'dob_is_estimated' => '0', 'source' => 'opening', 'ownership_type' => 'own_herd',
        'lifecycle_stage' => 'milking', 'lactation_number' => '1',
    ], $overrides);
}

/** Adds three milking cows (C-1 … C-3), a dry cow and a bull through the animal form. */
function acceptanceHerd(): void
{
    $t = test();
    $t->actingAs($t->owner);

    foreach (['C-1', 'C-2', 'C-3'] as $tag) {
        $t->post(route('animals.store'), acceptanceAnimal(['tag_no' => $tag]))->assertSessionHasNoErrors();
    }
    $t->post(route('animals.store'), acceptanceAnimal(['tag_no' => 'C-DRY', 'lifecycle_stage' => 'dry']))->assertSessionHasNoErrors();
    $t->post(route('animals.store'), acceptanceAnimal(['tag_no' => 'BULL-1', 'gender' => 'male', 'lifecycle_stage' => 'bull', 'lactation_number' => '0']))
        ->assertSessionHasNoErrors();

    [$t->a, $t->b, $t->c] = ['C-1', 'C-2', 'C-3'];
    foreach (['a', 'b', 'c'] as $key) {
        $t->{$key} = Animal::where('tag_no', $t->{$key})->firstOrFail();
    }
}

function acceptanceGrid(string $shift, array $qty, array $extra = []): void
{
    test()->post(route('milk-production.bulk'), ['production_date' => '2026-10-28', 'shift' => $shift, 'qty' => $qty] + $extra)
        ->assertSessionHasNoErrors();
}

it('1. keeps tags unique per farm and shows only active milking females in the entry grid', function () {
    acceptanceHerd();

    $this->post(route('animals.store'), acceptanceAnimal(['tag_no' => ' c-1 ']))
        ->assertSessionHasErrors(['tag_no' => 'This tag number is already used in this farm (also by deleted animals).']);

    $this->actingAs($this->herdStaff)->get(route('milk-production.index'))->assertOk()
        ->assertSee('C-1')->assertSee('C-2')->assertSee('C-3')
        ->assertDontSee('C-DRY')->assertDontSee('BULL-1');
});

it('2. bulk save of 3 animals (8+6, 10+8, 6+5) → daily total 43 L; stock own_farm/cow +43', function () {
    acceptanceHerd();
    $this->actingAs($this->herdStaff);

    acceptanceGrid('morning', [$this->a->id => '8', $this->b->id => '10', $this->c->id => '6']);
    acceptanceGrid('evening', [$this->a->id => '6', $this->b->id => '8', $this->c->id => '5']);

    expect(MilkProductionRecord::where('production_date', '2026-10-28')->sum('quantity_litre'))->toEqual('43.00')
        ->and($this->stock->balance(MilkSource::OwnFarm, MilkType::Cow))->toBe('43.00');

    $this->actingAs($this->owner)->get(route('reports.milk', ['daily', 'date' => '2026-10-28']))->assertOk()
        ->assertSeeInOrder(['Cow total (3)', '43.00', 'Total (3)', '43.00']);
    $this->get(route('milk-stock.index', ['date' => '2026-10-28']))->assertOk()->assertSee('43.00');
});

it('3. editing an entry from 10 to 7 moves stock by −3 in the same transaction; deleting removes the movement', function () {
    acceptanceHerd();
    acceptanceGrid('morning', [$this->a->id => '10']);
    $record = MilkProductionRecord::sole();

    $this->put(route('milk-production.update', $record), [
        'production_date' => '2026-10-28', 'shift' => 'morning', 'animal_id' => $this->a->id, 'quantity_litre' => '7', 'reason' => 'Misread the can',
    ])->assertSessionHasNoErrors();
    expect($this->stock->balance(MilkSource::OwnFarm, MilkType::Cow))->toBe('7.00')
        ->and(MilkStockMovement::sole()->quantity_in)->toBe('7.00');

    // if the stock write fails, the entry change is rolled back too (one transaction)
    MilkStockMovement::saving(fn () => throw new RuntimeException('stock write failed'));
    $this->put(route('milk-production.update', $record), [
        'production_date' => '2026-10-28', 'shift' => 'morning', 'animal_id' => $this->a->id, 'quantity_litre' => '9', 'reason' => 'Again',
    ])->assertServerError();
    expect($record->fresh()->quantity_litre)->toBe('7.00');
    MilkStockMovement::flushEventListeners();

    $this->delete(route('milk-production.destroy', $record), ['reason' => 'Wrong cow'])->assertSessionHasNoErrors();
    expect(MilkProductionRecord::count())->toBe(0)->and(MilkStockMovement::count())->toBe(0)
        ->and($this->stock->balance())->toBe('0.00');
})->after(fn () => MilkStockMovement::flushEventListeners());

it('4. rejects a duplicate (animal, date, shift); the same client_uuid twice creates one record', function () {
    acceptanceHerd();
    $data = ['production_date' => '2026-10-28', 'shift' => 'evening', 'animal_id' => $this->a->id, 'quantity_litre' => '6.5', 'client_uuid' => (string) Str::uuid()];

    $this->post(route('milk-production.store'), $data)->assertSessionHasNoErrors();
    $this->post(route('milk-production.store'), $data)->assertSessionHas('success', 'This entry was already saved.');
    expect(MilkProductionRecord::count())->toBe(1)->and(MilkStockMovement::count())->toBe(1);

    $this->post(route('milk-production.store'), ['client_uuid' => (string) Str::uuid()] + $data)
        ->assertSessionHasErrors(['animal_id' => 'This animal already has an entry for this date and shift — edit that entry instead.']);
    expect(MilkProductionRecord::count())->toBe(1);
});

it('5. wastage of 5 L reduces the balance; going out beyond the balance is blocked while negative stock is not allowed', function () {
    acceptanceHerd();
    acceptanceGrid('morning', [$this->a->id => '8']);
    $usage = fn (string $qty) => [
        'movement_type' => 'wastage', 'movement_date' => '2026-10-28', 'stock_source' => 'own_farm', 'milk_type' => 'cow',
        'quantity' => $qty, 'reason' => 'Spilled', 'client_uuid' => (string) Str::uuid(),
    ];

    expect($this->farm->setting('allow_negative_milk_stock'))->toBeFalse();
    $this->post(route('milk-stock.usage.store'), $usage('5'))->assertSessionHasNoErrors();
    expect($this->stock->balance(MilkSource::OwnFarm, MilkType::Cow))->toBe('3.00');

    $this->post(route('milk-stock.usage.store'), $usage('3.5'))
        ->assertSessionHasErrors(['quantity' => 'Not enough cow milk in Own Farm stock on 28-10-2026: 3.00 L available, 3.50 L needed.']);
    expect($this->stock->balance(MilkSource::OwnFarm, MilkType::Cow))->toBe('3.00');
});

it('6. the drop alert fires for avg 10 → today 7 (30 % ≥ 20 %)', function () {
    acceptanceHerd();
    foreach (range(1, 7) as $d) {
        MilkProductionRecord::factory()->for($this->a)->create(['production_date' => now()->subDays($d)->toDateString(), 'shift' => 'morning', 'quantity_litre' => '10']);
    }
    acceptanceGrid('morning', [$this->a->id => '7']);

    expect($this->farm->setting('milk_drop_alert_percent'))->toBe(20);
    $this->actingAs($this->herdStaff)->get(route('milk-alerts.index'))->assertOk()
        ->assertSee('C-1')->assertSee('down 30.0 %')->assertSee('Check health — this is a signal, not a diagnosis.');
});
