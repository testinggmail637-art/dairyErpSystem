<?php

use App\Enums\RoleName;
use App\Models\Animal;
use App\Models\AnimalPurchase;
use App\Models\AnimalSale;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\MilkCollection;
use App\Models\MilkProductionRecord;
use App\Models\Party;
use App\Models\User;
use App\Services\DashboardService;
use App\Support\CurrentFarm;

/*
| Phase 7 · Task 7.2: dashboard charts (spec 7.5) — last 30 days production vs collection vs sales (litres) and
| month-wise income vs costs on the Simple P&L basis. Each series is checked against its source rows.
*/

beforeEach(function () {
    $this->travelTo('2026-11-17 10:00');
    $this->farm = Farm::factory()->create();
    app(CurrentFarm::class)->set($this->farm);
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->f = ['farm_id' => $this->farm->id];
    $this->customer = Customer::factory()->create($this->f);
    $this->delivery = fn (array $a) => Delivery::factory()->create($a + ['customer_id' => $this->customer->id, 'status' => 'delivered']);
});

it('charts own production, accepted collection and delivered litres for the last 30 complete days', function () {
    $cow = Animal::factory()->create($this->f);
    $milk = fn (string $date, string $litres) => MilkProductionRecord::factory()->for($cow)->create(['production_date' => $date, 'shift' => 'morning', 'quantity_litre' => $litres]);
    $milk('2026-11-16', '10.00');
    $milk('2026-10-18', '3.00');   // first day of the chart
    $milk('2026-10-17', '99.00');  // too old
    $milk('2026-11-17', '99.00');  // today is still being entered
    MilkCollection::factory()->create($this->f + ['collection_date' => '2026-11-16', 'quantity_litre' => '8.40']);
    MilkCollection::factory()->create($this->f + ['collection_date' => '2026-11-16', 'quantity_litre' => '4.00', 'quality_status' => 'rejected']);
    ($this->delivery)(['delivery_date' => '2026-11-16', 'delivered_qty' => '6.00', 'amount' => '360']);
    ($this->delivery)(['delivery_date' => '2026-11-16', 'delivered_qty' => '0', 'status' => 'pending']);

    $flow = app(DashboardService::class)->data()['flow'];

    expect($flow['labels'])->toHaveCount(DashboardService::CHART_DAYS)
        ->and($flow['labels'][0])->toBe('18 Oct')->and(end($flow['labels']))->toBe('16 Nov')
        ->and($flow['series']['production'][0])->toBe('3.00')
        ->and(end($flow['series']['production']))->toBe('10.00')
        ->and(end($flow['series']['collection']))->toBe('8.40')
        ->and(end($flow['series']['sales']))->toBe('6.00')
        ->and($flow['totals'])->toBe(['production' => '13.00', 'collection' => '8.40', 'sales' => '6.00']);

    $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()
        ->assertSee('chart-flow')->assertSee('13.00 L')->assertSee('8.40 L');

    // each line only with its module permission; no line at all → no chart
    $herd = User::factory()->forFarm($this->farm)->create();
    $herd->givePermissionTo('dashboard.view', 'milk_production.view');
    $this->actingAs($herd)->get(route('dashboard'))->assertOk()
        ->assertSee('chart-flow')->assertSee('13.00 L')->assertDontSee('8.40 L');

    $nobody = User::factory()->forFarm($this->farm)->create();
    $nobody->givePermissionTo('dashboard.view');
    $this->actingAs($nobody)->get(route('dashboard'))->assertOk()->assertDontSee('chart-flow');
});

it('sets milk and animal sales against expenses, farmer milk and animal purchases per month', function () {
    $party = Party::factory()->create($this->f);
    // November income: delivered 840 + animal sale 9,500 (cancelled sale and pending delivery ignored)
    ($this->delivery)(['delivery_date' => '2026-11-05', 'delivered_qty' => '14', 'amount' => '840']);
    ($this->delivery)(['delivery_date' => '2026-11-06', 'amount' => '500', 'status' => 'pending']);
    ($this->delivery)(['delivery_date' => '2026-10-20', 'delivered_qty' => '2', 'amount' => '100']);
    $sale = fn (string $no, string $status) => AnimalSale::create(['sale_no' => $no, 'party_id' => $party->id, 'sale_date' => '2026-11-10',
        'total_price' => '10000', 'selling_costs' => '500', 'net_amount' => '9500', 'status' => $status]);
    $sale('S-1', 'completed');
    $sale('S-2', 'cancelled');
    // November costs: expense 1,000 + accepted farmer milk 400 + animal purchase 20,000 (rejected milk ignored)
    Expense::factory()->create($this->f + ['expense_date' => '2026-11-03', 'amount' => '1000.00']);
    MilkCollection::factory()->create($this->f + ['collection_date' => '2026-11-04', 'amount' => '400']);
    MilkCollection::factory()->create($this->f + ['collection_date' => '2026-11-04', 'amount' => '0', 'quality_status' => 'rejected']);
    AnimalPurchase::create(['purchase_no' => 'P-1', 'party_id' => $party->id, 'purchase_date' => '2026-11-08',
        'total_animal_price' => '19000', 'transport_cost' => '1000', 'total_amount' => '20000']);

    $finance = app(DashboardService::class)->data()['finance'];

    expect($finance['labels'])->toHaveCount(DashboardService::CHART_MONTHS)
        ->and(array_slice($finance['labels'], -2))->toBe(['Oct 26', 'Nov 26'])
        ->and(array_slice($finance['income'], -2))->toBe(['100.00', '10340.00'])
        ->and(array_slice($finance['expenses'], -2))->toBe(['0.00', '21400.00'])
        ->and(array_slice($finance['profit'], -2))->toBe(['100.00', '-11060.00'])
        ->and($finance['parts'])->toBe(['milk_sales' => '940.00', 'animal_sales' => '9500.00', 'expense_entries' => '1000.00',
            'farmer_milk' => '400.00', 'animal_purchases' => '20000.00'])
        ->and($finance['total_profit'])->toBe('-10960.00');

    $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()
        ->assertSee('Income vs Costs')->assertSee('₹9,500.00')->assertSee('₹20,000.00');
});
