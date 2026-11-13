<?php

use App\Enums\RoleName;
use App\Models\Animal;
use App\Models\AnimalVaccination;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\PaymentMode;
use App\Models\User;
use App\Models\VaccineType;
use App\Support\CurrentFarm;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->farm = Farm::where('code', 'MDF')->firstOrFail();
    app(CurrentFarm::class)->set($this->farm);
    $this->travelTo('2026-11-13 10:00');
    $this->staff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $this->actingAs($this->staff);
    $this->animals = collect(['C-1', 'C-2', 'C-3'])->map(fn ($tag) => Animal::factory()->create(['farm_id' => $this->farm->id, 'tag_no' => $tag]));
    $this->fmd = VaccineType::where('name', 'FMD')->sole();
    $this->cash = PaymentMode::where('code', 'CASH')->value('id');
});

function vaccinate(array $overrides = []): TestResponse
{
    return test()->post(route('health.vaccinations.store'), array_merge([
        'vaccine_type_id' => test()->fmd->id, 'given_on' => '2026-11-10', 'animal_ids' => test()->animals->pluck('id')->all(),
        'cost' => '50', 'record_as_expense' => '1', 'payment_mode_id' => test()->cash,
    ], $overrides));
}

it('seeds the six spec vaccines for every farm with no interval', function () {
    expect(VaccineType::orderBy('name')->pluck('name')->all())->toBe(['BQ', 'Brucellosis', 'Deworming', 'FMD', 'HS', 'Theileriosis'])
        ->and(VaccineType::whereNotNull('interval_days')->count())->toBe(0);
});

it('gives a vaccine to many animals at once: one row and one expense each, next due from the interval', function () {
    $this->fmd->update(['interval_days' => 180]);

    vaccinate()->assertSessionHasNoErrors()->assertSessionHas('success', '3 vaccinations saved.');

    expect(AnimalVaccination::count())->toBe(3)
        ->and(AnimalVaccination::pluck('next_due_on')->map->toDateString()->unique()->all())->toBe(['2027-05-09'])
        ->and(Expense::count())->toBe(3)
        ->and(Expense::pluck('animal_id')->sort()->values()->all())->toBe($this->animals->pluck('id')->sort()->values()->all())
        ->and(Expense::first()->subcategory->name)->toBe('Vaccination')
        ->and(Expense::sum('amount'))->toEqual('150.00');

    // own next date wins; no interval and no date → none; no cost → no expense
    vaccinate(['animal_ids' => [$this->animals[0]->id], 'next_due_on' => '2027-01-01', 'cost' => '0'])->assertSessionHasNoErrors();
    $hs = VaccineType::where('name', 'HS')->sole();
    vaccinate(['animal_ids' => [$this->animals[0]->id], 'vaccine_type_id' => $hs->id, 'record_as_expense' => '0'])->assertSessionHasNoErrors();
    expect(AnimalVaccination::where('next_due_on', '2027-01-01')->count())->toBe(1)
        ->and(AnimalVaccination::where('vaccine_type_id', $hs->id)->sole()->next_due_on)->toBeNull()
        ->and(Expense::count())->toBe(3);

    vaccinate(['animal_ids' => []])->assertSessionHasErrors('animal_ids');
    vaccinate(['payment_mode_id' => ''])->assertSessionHasErrors('payment_mode_id');
});

it('edits one vaccination with a reason (expense follows) and deletes it with its expense', function () {
    vaccinate(['animal_ids' => [$this->animals[0]->id]])->assertSessionHasNoErrors();
    $row = AnimalVaccination::sole();

    $data = ['vaccine_type_id' => $this->fmd->id, 'given_on' => '2026-11-10', 'animal_ids' => [$this->animals[0]->id],
        'cost' => '70', 'record_as_expense' => '1', 'payment_mode_id' => $this->cash];
    $this->put(route('health.vaccinations.update', $row), $data)->assertSessionHasErrors('reason');
    $this->put(route('health.vaccinations.update', $row), $data + ['reason' => 'Vet charged more'])->assertSessionHasNoErrors();
    expect(Expense::sole()->amount)->toBe('70.00');

    $this->put(route('health.vaccinations.update', $row), ['animal_ids' => $this->animals->pluck('id')->all(), 'reason' => 'x x x'] + $data)
        ->assertSessionHasErrors('animal_ids'); // one animal when editing

    $this->delete(route('health.vaccinations.destroy', $row), ['reason' => 'Wrong animal'])->assertSessionHasNoErrors();
    expect(AnimalVaccination::count())->toBe(0)->and(Expense::count())->toBe(0);
});

it('lists due and overdue doses: only the latest dose of each animal and vaccine', function () {
    $give = fn (Animal $a, string $on, string $due) => AnimalVaccination::create(['animal_id' => $a->id, 'vaccine_type_id' => $this->fmd->id,
        'given_on' => $on, 'next_due_on' => $due, 'cost' => '0']);
    $give($this->animals[0], '2026-01-01', '2026-07-01'); // replaced by the next dose
    $give($this->animals[0], '2026-07-01', '2027-01-01');
    $give($this->animals[1], '2026-05-01', '2026-11-01'); // overdue
    $give($this->animals[2], '2026-06-01', '2026-12-01'); // due within 30 days

    $this->get(route('health.vaccinations.index', ['due' => 'overdue']))->assertOk()->assertSee('C-2')->assertDontSee('C-1')->assertDontSee('C-3');
    $this->get(route('health.vaccinations.index', ['due' => '30']))->assertOk()->assertSee('C-2')->assertSee('C-3')->assertDontSee('C-1');
    $this->get(route('health.vaccinations.index', ['from' => '2026-01-01']))->assertOk()->assertSee('C-1');
});

it('manages vaccines: add, set the interval, not delete one in use, inactive ones are not offered', function () {
    $this->post(route('health.vaccine-types.store'), ['name' => 'Rabies', 'interval_days' => '365'])->assertSessionHasNoErrors();
    $this->post(route('health.vaccine-types.store'), ['name' => 'rabies'])->assertSessionHasErrors('name');
    $this->get(route('health.vaccine-types.index'))->assertOk()->assertSee('Rabies')->assertSee('365 days');

    vaccinate(['animal_ids' => [$this->animals[0]->id], 'cost' => '0'])->assertSessionHasNoErrors();
    $this->delete(route('health.vaccine-types.destroy', $this->fmd))->assertSessionHas('error');
    $this->delete(route('health.vaccine-types.destroy', VaccineType::where('name', 'Rabies')->sole()))->assertSessionHas('success');

    $this->put(route('health.vaccine-types.update', $this->fmd), ['name' => 'FMD', 'interval_days' => '180', 'is_active' => '0'])->assertSessionHasNoErrors();
    vaccinate(['cost' => '0'])->assertSessionHasErrors('vaccine_type_id');
    expect($this->fmd->fresh())->interval_days->toBe(180)->is_active->toBeFalse();
});

it('is for health.view to see and health.manage to change', function () {
    $accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
    $this->actingAs($accountant)->get(route('health.vaccinations.index'))->assertForbidden();
    vaccinate()->assertForbidden();

    $this->actingAs($this->staff)->get(route('health.vaccinations.create', ['animal_id' => $this->animals[0]->id]))->assertOk()->assertSee('C-1');
    $this->get(route('health.vaccinations.edit', AnimalVaccination::create(['animal_id' => $this->animals[0]->id, 'vaccine_type_id' => $this->fmd->id,
        'given_on' => '2026-11-01', 'cost' => '0'])))->assertOk();
});
