<?php

use App\Enums\LifecycleStage;
use App\Enums\RoleName;
use App\Models\Animal;
use App\Models\BreedingEvent;
use App\Models\Farm;
use App\Models\MilkProductionRecord;
use App\Models\User;
use App\Services\MilkReportService;
use App\Support\CurrentFarm;

/*
| Phase 7 · Task 7.7: milk forecast for 7 / 30 / 90 days (spec 7.6 #12).
*/

beforeEach(function () {
    $this->travelTo('2026-11-17 10:00');
    $this->farm = Farm::factory()->create();
    app(CurrentFarm::class)->set($this->farm);
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->f = ['farm_id' => $this->farm->id];
    $this->milk = fn (Animal $a, string $date, string $shift, string $l) => MilkProductionRecord::factory()->for($a)
        ->create(['production_date' => $date, 'shift' => $shift, 'quantity_litre' => $l]);
});

it('forecasts each milking animal from its last 14 days, stops at the dry-off date and skips dry animals', function () {
    $a = Animal::factory()->create($this->f + ['tag_no' => 'A-1', 'name' => null]);
    ($this->milk)($a, '2026-11-10', 'morning', '6.00');
    ($this->milk)($a, '2026-11-10', 'evening', '4.00');
    ($this->milk)($a, '2026-11-11', 'morning', '12.00');   // 22 L over 2 days → 11 L/day
    ($this->milk)($a, '2026-11-02', 'morning', '99.00');   // before the 14 days
    ($this->milk)($a, '2026-11-17', 'morning', '99.00');   // today is not complete

    $b = Animal::factory()->buffalo()->create($this->f + ['tag_no' => 'B-1', 'name' => null]);
    ($this->milk)($b, '2026-11-16', 'morning', '8.00');
    // pregnant: calving due 26-01-2027 → dry off 27-11-2026, 10 milking days left from today
    BreedingEvent::create($this->f + ['animal_id' => $b->id, 'event_type' => 'pregnancy_check', 'event_date' => '2026-09-01',
        'result' => 'positive', 'expected_calving_date' => '2027-01-26']);

    $dry = Animal::factory()->stage(LifecycleStage::Dry)->create($this->f + ['tag_no' => 'C-1']);
    ($this->milk)($dry, '2026-11-05', 'morning', '5.00');
    Animal::factory()->create($this->f + ['tag_no' => 'D-1', 'name' => null]);  // milking, no entries

    $service = app(MilkReportService::class);

    $month = $service->forecast(30);
    expect($month->rows)->toBe([
        ['A-1', 'Cow', 2, '11.00', 30, '330.00', ''],
        ['B-1', 'Buffalo', 1, '8.00', 10, '80.00', 'Dry off due 27-11-2026'],
        ['D-1', 'Cow', 0, '0.00', 30, '0.00', 'No milk entries in the last 14 days'],
    ])->and($month->totals)->toBe(['Total (3)', '', '', '19.00', '', '410.00', ''])
        ->and($month->notes[0])->toBe('Estimate — actual yield depends on health, feed, season, pregnancy.');

    expect(array_column($service->forecast(7)->rows, 5))->toBe(['77.00', '56.00', '0.00']);
    expect($service->forecast(90)->rows[1][6])->toBe('Dry off due 27-11-2026 · Calving due 26-01-2027');

    $this->actingAs($this->owner)->get(route('reports.milk', ['report' => 'forecast', 'days' => 7]))
        ->assertOk()->assertSee('Milk forecast — next 7 days')->assertSee('Estimate — actual yield depends on health, feed, season, pregnancy.');
    $this->get(route('reports.milk', ['report' => 'forecast', 'days' => 15]))->assertSessionHasErrors('days');
    $this->get(route('reports.milk', ['report' => 'forecast', 'format' => 'pdf']))->assertOk();
});
