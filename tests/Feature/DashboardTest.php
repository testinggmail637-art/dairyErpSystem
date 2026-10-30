<?php

use App\Enums\MilkMovementType;
use App\Enums\MilkShift;
use App\Enums\MilkSource;
use App\Enums\MilkType;
use App\Enums\RoleName;
use App\Models\Animal;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\MilkProductionRecord;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Services\DashboardService;
use App\Services\MilkStockService;
use App\Support\CurrentFarm;

beforeEach(function () {
    $this->travelTo('2026-10-28 10:00');
    $this->farm = Farm::factory()->create();
    app(CurrentFarm::class)->set($this->farm);
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->herdStaff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
});

it('totals milk, stock, herd, expenses and vendor payable from the source tables', function () {
    $cow = Animal::factory()->create(['farm_id' => $this->farm->id]);
    $buffalo = Animal::factory()->buffalo()->create(['farm_id' => $this->farm->id]);
    MilkProductionRecord::factory()->for($cow)->create(['production_date' => '2026-10-28', 'shift' => 'morning', 'quantity_litre' => '10.50']);
    MilkProductionRecord::factory()->for($buffalo)->create(['production_date' => '2026-10-28', 'shift' => 'morning', 'quantity_litre' => '6.25']);
    MilkProductionRecord::factory()->for($cow)->create(['production_date' => '2026-10-27', 'shift' => 'morning', 'quantity_litre' => '10.00']);
    MilkProductionRecord::factory()->for($cow)->create(['production_date' => '2026-09-30', 'shift' => 'morning', 'quantity_litre' => '9.00']);

    $stock = app(MilkStockService::class);
    $stock->recordIn(MilkSource::OwnFarm, MilkType::Cow, '2026-10-28', MilkShift::Morning, MilkMovementType::Production, '20.00');
    $stock->recordOut(MilkSource::OwnFarm, MilkType::Cow, '2026-10-28', MilkShift::Morning, MilkMovementType::Wastage, '1.50', reason: 'Spilt');

    Expense::factory()->create(['farm_id' => $this->farm->id, 'expense_date' => '2026-10-28', 'amount' => '1200.00']);
    Expense::factory()->create(['farm_id' => $this->farm->id, 'expense_date' => '2026-10-02', 'amount' => '800.00']);
    Expense::factory()->create(['farm_id' => $this->farm->id, 'expense_date' => '2026-09-15', 'amount' => '500.00']);
    $vendor = Vendor::factory()->create(['farm_id' => $this->farm->id, 'opening_balance' => '1000.00']);
    Expense::factory()->credit($vendor)->create(['expense_date' => '2026-10-10', 'amount' => '3000.00']);
    VendorPayment::factory()->create(['farm_id' => $this->farm->id, 'vendor_id' => $vendor->id, 'amount' => '2500.00']);

    $data = app(DashboardService::class)->data();

    expect($data['production']['today'])->toMatchArray(['cow' => '10.50', 'buffalo' => '6.25', 'total' => '16.75'])
        ->and($data['production']['yesterday_total'])->toBe('10.00')
        ->and($data['production']['change_pct'])->toBe('67.5')
        ->and($data['production']['month_total'])->toBe('26.75')
        ->and($data['production']['chart']['labels'])->toHaveCount(DashboardService::CHART_DAYS)
        ->and(end($data['production']['chart']['series']['cow']))->toBe('10.00')
        ->and($data['stock']['total'])->toBe('18.50')
        ->and($data['stock']['wastage_today'])->toBe('1.50')
        ->and($data['herd']['active'])->toBe(2)
        ->and($data['expenses']['today'])->toBe('1200.00')
        ->and($data['expenses']['month'])->toBe('5000.00')
        ->and($data['expenses']['last_month'])->toBe('500.00')
        ->and($data['finance']['expenses'])->toHaveCount(DashboardService::CHART_MONTHS)
        ->and($data['production']['chart'])->toMatchArray(['total' => '19.00', 'average' => '0.63']) // 27 Oct + 30 Sep, today excluded
        ->and($data['finance']['total_expenses'])->toBe('5500.00')
        ->and($data['vendor_payable'])->toBe('1500.00');
});

it('caches the numbers and clears them when a source record is written', function () {
    $cow = Animal::factory()->create(['farm_id' => $this->farm->id]);
    $service = app(DashboardService::class);
    expect($service->data()['production']['today']['total'])->toBe('0.00');

    MilkProductionRecord::factory()->for($cow)->create(['production_date' => '2026-10-28', 'shift' => 'morning', 'quantity_litre' => '8.00']);

    expect($service->data()['production']['today']['total'])->toBe('8.00');
});

it('shows charts to the owner and hides financial cards from herd staff', function () {
    $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()
        ->assertSee('chart-production')->assertSee('chart-finance')->assertSee(__('Vendor payable'));

    $this->actingAs($this->herdStaff)->get(route('dashboard'))->assertOk()
        ->assertSee('chart-production')->assertDontSee('chart-finance')->assertDontSee(__('Vendor payable'));
});

it('estimates income from own milk × the active default rate and sets it against expenses per month', function () {
    $cow = Animal::factory()->create(['farm_id' => $this->farm->id]);
    $buffalo = Animal::factory()->buffalo()->create(['farm_id' => $this->farm->id]);
    Product::factory()->create(['farm_id' => $this->farm->id, 'milk_type' => MilkType::Cow, 'default_rate' => '60.00']);
    Product::factory()->buffalo()->create(['farm_id' => $this->farm->id, 'default_rate' => '70.00']);
    Product::factory()->create(['farm_id' => $this->farm->id, 'milk_type' => MilkType::Cow, 'default_rate' => '99.00', 'is_active' => false]);
    MilkProductionRecord::factory()->for($cow)->create(['production_date' => '2026-10-28', 'shift' => 'morning', 'quantity_litre' => '20.50']);
    MilkProductionRecord::factory()->for($buffalo)->create(['production_date' => '2026-10-28', 'shift' => 'morning', 'quantity_litre' => '6.25']);
    MilkProductionRecord::factory()->for($cow)->create(['production_date' => '2026-09-30', 'shift' => 'morning', 'quantity_litre' => '9.00']);
    Expense::factory()->create(['farm_id' => $this->farm->id, 'expense_date' => '2026-10-02', 'amount' => '5000.00']);
    Expense::factory()->create(['farm_id' => $this->farm->id, 'expense_date' => '2026-09-15', 'amount' => '500.00']);

    $finance = app(DashboardService::class)->data()['finance'];

    // Oct: 20.50 × 60 + 6.25 × 70 = 1,667.50 · Sep: 9 × 60 = 540
    expect(array_slice($finance['labels'], -2))->toBe(['Sep 26', 'Oct 26'])
        ->and(array_slice($finance['income'], -2))->toBe(['540.00', '1667.50'])
        ->and(array_slice($finance['expenses'], -2))->toBe(['500.00', '5000.00'])
        ->and(array_slice($finance['profit'], -2))->toBe(['40.00', '-3332.50'])
        ->and($finance)->toMatchArray([
            'total_income' => '2207.50', 'total_expenses' => '5500.00', 'total_profit' => '-3292.50',
            'rates' => ['cow' => '60.00', 'buffalo' => '70.00'],
        ]);
});

it('groups income and expenses by day, Monday weeks and April–March financial years', function () {
    $cow = Animal::factory()->create(['farm_id' => $this->farm->id]);
    Product::factory()->create(['farm_id' => $this->farm->id, 'milk_type' => MilkType::Cow, 'default_rate' => '60.00']);
    MilkProductionRecord::factory()->for($cow)->create(['production_date' => '2026-10-27', 'shift' => 'morning', 'quantity_litre' => '10.00']); // Tuesday
    MilkProductionRecord::factory()->for($cow)->create(['production_date' => '2026-10-25', 'shift' => 'morning', 'quantity_litre' => '5.00']);  // Sunday, week before
    MilkProductionRecord::factory()->for($cow)->create(['production_date' => '2026-03-31', 'shift' => 'morning', 'quantity_litre' => '2.00']);  // FY 2025-26
    Expense::factory()->create(['farm_id' => $this->farm->id, 'expense_date' => '2026-10-26', 'amount' => '100.00']); // Monday
    Expense::factory()->create(['farm_id' => $this->farm->id, 'expense_date' => '2026-04-01', 'amount' => '50.00']);  // FY 2026-27

    $service = app(DashboardService::class);

    $day = $service->finance('day');
    expect($day['labels'])->toHaveCount(30)->and(end($day['labels']))->toBe('28 Oct')
        ->and(array_slice($day['income'], -4))->toBe(['300.00', '0.00', '600.00', '0.00'])
        ->and(array_slice($day['expenses'], -3))->toBe(['100.00', '0.00', '0.00']);

    $week = $service->finance('week');
    expect($week['labels'])->toHaveCount(12)->and(array_slice($week['labels'], -2))->toBe(['19 Oct', '26 Oct'])
        ->and(array_slice($week['income'], -2))->toBe(['300.00', '600.00'])
        ->and(end($week['expenses']))->toBe('100.00');

    $year = $service->finance('year');
    expect($year['labels'])->toBe(['FY 2022-23', 'FY 2023-24', 'FY 2024-25', 'FY 2025-26', 'FY 2026-27'])
        ->and(array_slice($year['income'], -2))->toBe(['120.00', '900.00'])
        ->and(array_slice($year['expenses'], -2))->toBe(['0.00', '150.00'])
        ->and($year['display']['total_profit'])->toBe('₹870.00');
});

it('serves a period over JSON only to users who see the money cards', function () {
    $this->actingAs($this->owner)->getJson(route('dashboard.finance', ['period' => 'week']))
        ->assertOk()->assertJsonPath('period', 'week')->assertJsonCount(12, 'labels');
    $this->getJson(route('dashboard.finance', ['period' => 'hour']))->assertUnprocessable();

    $this->actingAs($this->herdStaff)->getJson(route('dashboard.finance', ['period' => 'month']))->assertForbidden();
});
