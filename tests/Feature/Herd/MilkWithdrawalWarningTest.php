<?php

use App\Enums\RoleName;
use App\Models\Animal;
use App\Models\Farm;
use App\Models\MilkProductionRecord;
use App\Models\Treatment;
use App\Models\User;

beforeEach(function () {
    $this->travelTo('2027-03-10 08:00');
    $this->farm = Farm::factory()->create();
    $this->staff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $this->sick = Animal::factory()->create(['farm_id' => $this->farm->id, 'tag_no' => 'C-SICK', 'name' => null]);
    $this->well = Animal::factory()->create(['farm_id' => $this->farm->id, 'tag_no' => 'C-WELL', 'name' => null]);
    $treat = fn (Animal $a, string $on, ?string $until) => Treatment::factory()->create([
        'farm_id' => $this->farm->id, 'animal_id' => $a->id, 'treatment_date' => $on, 'milk_withdrawal_until' => $until,
    ]);
    $treat($this->sick, '2027-03-08', '2027-03-12');
    $treat($this->well, '2027-03-01', '2027-03-05'); // already over
});

it('shows a withdrawal badge on the grid only for animals under withdrawal on that date', function () {
    $this->actingAs($this->staff)->get(route('milk-production.index'))->assertOk()
        ->assertSee('Withdrawal till 12-03-2027')
        ->assertDontSee('Withdrawal till 05-03-2027');

    $this->get(route('milk-production.index', ['date' => '2027-03-07']))->assertDontSee('Withdrawal till'); // before treatment

    $this->travelTo('2027-03-13 08:00');
    $this->get(route('milk-production.index'))->assertDontSee('Withdrawal till');
});

it('warns on the single entry form and flags the animal in the milking search', function () {
    $this->actingAs($this->staff)
        ->get(route('milk-production.create', ['animal_id' => $this->sick->id]))->assertOk()
        ->assertSee('under milk withdrawal till 12-03-2027');
    $this->get(route('milk-production.create', ['animal_id' => $this->well->id]))->assertDontSee('under milk withdrawal');

    $record = MilkProductionRecord::factory()->create(['farm_id' => $this->farm->id, 'animal_id' => $this->sick->id, 'production_date' => '2027-03-07']);
    $this->get(route('milk-production.edit', $record))->assertDontSee('under milk withdrawal');

    $texts = collect($this->getJson(route('animals.search', ['milking' => 1, 'q' => 'C-']))->json('results'))->pluck('text', 'id');
    expect($texts[$this->sick->id])->toContain('milk withdrawal till 12-03-2027')
        ->and($texts[$this->well->id])->not->toContain('withdrawal');
});
