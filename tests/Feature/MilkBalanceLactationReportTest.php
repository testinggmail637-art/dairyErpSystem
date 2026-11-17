<?php

use App\Enums\MilkSource;
use App\Enums\MilkType;
use App\Enums\RoleName;
use App\Models\Animal;
use App\Models\Calving;
use App\Models\Farm;
use App\Models\MilkProductionRecord;
use App\Models\MilkStockMovement;
use App\Models\User;
use App\Services\MilkReportService;
use App\Services\MilkStockService;
use App\Support\CurrentFarm;

/*
| Phase 7 · Task 7.3: lactation-wise milk production and milk balance / reconciliation (spec 7.6 #1, #3).
| Daily, monthly, animal-wise and the collection reports were built in Phases 2 and 4.
*/

beforeEach(function () {
    $this->travelTo('2026-11-17 10:00');
    $this->farm = Farm::factory()->create();
    app(CurrentFarm::class)->set($this->farm);
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
});

it('splits each animal\'s milk by the calving that started its lactation', function () {
    $cow = Animal::factory()->create(['farm_id' => $this->farm->id, 'tag_no' => 'C-1', 'name' => null, 'lactation_number' => 3]);
    Calving::create(['mother_id' => $cow->id, 'calving_date' => '2026-06-01', 'outcome' => 'alive']);
    Calving::create(['mother_id' => $cow->id, 'calving_date' => '2026-10-01', 'outcome' => 'stillborn']);
    $milk = fn (string $date, string $shift, string $litres) => MilkProductionRecord::factory()->for($cow)
        ->create(['production_date' => $date, 'shift' => $shift, 'quantity_litre' => $litres]);
    $milk('2026-05-20', 'morning', '10.00');  // before both calvings → lactation 1
    $milk('2026-06-05', 'morning', '8.00');
    $milk('2026-06-05', 'evening', '4.00');   // peak day 12
    $milk('2026-06-06', 'morning', '10.00');
    $milk('2026-10-03', 'morning', '7.00');
    $milk('2026-04-30', 'morning', '99.00');  // outside the period

    $table = app(MilkReportService::class)->lactationWise('2026-05-01', '2026-11-17');

    expect($table->rows)->toBe([
        ['C-1', 'Cow', 1, null, 1, '10.00', '10.00', '10.00', null],
        ['C-1', 'Cow', 2, '2026-06-01', 2, '22.00', '11.00', '12.00', 6],
        ['C-1', 'Cow', 3, '2026-10-01', 1, '7.00', '7.00', '7.00', 3],
    ])->and($table->totals[5])->toBe('39.00');

    $this->actingAs($this->owner)->get(route('reports.milk', ['report' => 'lactations', 'from' => '2026-05-01', 'to' => '2026-11-17']))
        ->assertOk()->assertSee('Lactation-wise milk production')->assertSee('01-06-2026');
    $this->get(route('reports.milk', ['report' => 'lactations', 'format' => 'excel']))->assertOk();
});

it('balances each stock from the ledger and flags the days it closed below zero', function () {
    $move = fn (string $date, string $source, string $type, string $movement, string $in, string $out) => MilkStockMovement::create([
        'farm_id' => $this->farm->id, 'movement_date' => $date, 'stock_source' => $source, 'milk_type' => $type,
        'movement_type' => $movement, 'quantity_in' => $in, 'quantity_out' => $out, 'reason' => 'test',
    ]);
    // own farm cow: opening 100 − 30 = 70; period +50 −5 −200 (closes −85 on 05-11) +100 → 15
    $move('2026-10-30', 'own_farm', 'cow', 'production', '100', '0');
    $move('2026-10-31', 'own_farm', 'cow', 'delivery', '0', '30');
    $move('2026-11-02', 'own_farm', 'cow', 'production', '50', '0');
    $move('2026-11-03', 'own_farm', 'cow', 'wastage', '0', '5');
    $move('2026-11-05', 'own_farm', 'cow', 'delivery', '0', '200');
    $move('2026-11-06', 'own_farm', 'cow', 'adjustment_in', '100', '0');
    // collection buffalo: +40 −10 on one day → 30
    $move('2026-11-10', 'collection', 'buffalo', 'collection', '40', '0');
    $move('2026-11-10', 'collection', 'buffalo', 'home_use', '0', '10');
    $move('2026-11-18', 'collection', 'buffalo', 'collection', '500', '0'); // after the period

    $table = app(MilkReportService::class)->balance('2026-11-01', '2026-11-17');

    // Stock, type, opening, production, collection, adj in, delivery, wastage, home use, calf feeding, adj out, closing, lowest, days < 0
    expect($table->rows)->toBe([
        ['Own Farm', 'Cow', '70.00', '50.00', '0.00', '100.00', '200.00', '5.00', '0.00', '0.00', '0.00', '15.00', '-85.00', 1],
        ['Collection Center', 'Buffalo', '0.00', '0.00', '40.00', '0.00', '0.00', '0.00', '10.00', '0.00', '0.00', '30.00', '30.00', 0],
    ])->and($table->strongRows)->toBe([0])
        ->and($table->totals[11])->toBe('45.00')
        ->and(collect($table->notes)->implode(' '))->toContain('05-11-2026');

    // closing reconciles with the stock service balance
    $stock = app(MilkStockService::class);
    expect($stock->balance(MilkSource::OwnFarm, MilkType::Cow, '2026-11-17'))->toBe('15.00');

    $this->actingAs($this->owner)->get(route('reports.milk', ['report' => 'balance', 'from' => '2026-11-01', 'to' => '2026-11-17']))
        ->assertOk()->assertSee('Milk balance')->assertSee('Days below zero');
    $this->get(route('reports.milk', ['report' => 'balance', 'format' => 'pdf']))->assertOk();
});
