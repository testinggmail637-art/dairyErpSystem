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
        ->and($data['expenses']['chart']['values'])->toHaveCount(DashboardService::CHART_MONTHS)
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
        ->assertSee('chart-production')->assertSee('chart-expenses')->assertSee(__('Vendor payable'));

    $this->actingAs($this->herdStaff)->get(route('dashboard'))->assertOk()
        ->assertSee('chart-production')->assertDontSee('chart-expenses')->assertDontSee(__('Vendor payable'));
});
