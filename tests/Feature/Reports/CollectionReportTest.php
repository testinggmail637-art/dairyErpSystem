<?php

use App\Enums\RoleName;
use App\Models\Farm;
use App\Models\Farmer;
use App\Models\MilkCollection;
use App\Models\User;
use App\Services\CollectionReportService;
use App\Support\CurrentFarm;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    app(CurrentFarm::class)->set($this->farm);
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
    $this->asha = Farmer::factory()->create(['farm_id' => $this->farm->id, 'code' => 'F-001', 'name' => 'Asha Pawar']);
    $this->bala = Farmer::factory()->create(['farm_id' => $this->farm->id, 'code' => 'F-002', 'name' => 'Bala More']);

    $milk = fn (Farmer $f, array $a) => MilkCollection::factory()->create(array_merge(['farm_id' => $this->farm->id, 'farmer_id' => $f->id,
        'collection_date' => '2026-10-01', 'shift' => 'morning', 'milk_type' => 'cow'], $a));
    // fat by litres: (4.0 × 10 + 5.0 × 30) ÷ 40 = 4.75; Bala has no SNF reading → SNF averaged over Asha only
    $milk($this->asha, ['quantity_litre' => '10.00', 'fat' => '4.00', 'snf' => '8.50', 'rate_per_litre' => '40.00', 'amount' => '400.00']);
    $milk($this->bala, ['quantity_litre' => '30.00', 'fat' => '5.00', 'rate_per_litre' => '45.00', 'amount' => '1350.00']);
    $milk($this->asha, ['shift' => 'evening', 'quantity_litre' => '6.00', 'fat' => '2.50', 'rate_per_litre' => '0.00', 'amount' => '0.00',
        'quality_status' => 'rejected', 'rejection_reason' => 'Sour']);
    $milk($this->asha, ['collection_date' => '2026-10-02', 'milk_type' => 'buffalo', 'quantity_litre' => '5.00', 'fat' => '7.00', 'rate_per_litre' => '60.00', 'amount' => '300.00']);
    $milk($this->bala, ['collection_date' => '2026-11-01', 'quantity_litre' => '99.00', 'amount' => '3960.00']); // outside the period
});

it('sums the daily shift summary with litre-weighted fat / SNF; rejected milk apart', function () {
    $table = app(CollectionReportService::class)->dailyShift('2026-10-01', '2026-10-31');

    expect($table->rows)->toBe([
        ['2026-10-01', 'Morning', 'Cow', 2, '40.00', '4.75', '8.50', '1750.00', '43.75', '0.00'],
        ['2026-10-01', 'Evening', 'Cow', 1, '0.00', null, null, '0.00', null, '6.00'],
        ['2026-10-02', 'Morning', 'Buffalo', 1, '5.00', '7.00', null, '300.00', '60.00', '0.00'],
    ])->and($table->totals)->toBe(['Total', '', '', '', '45.00', '5.00', '8.50', '2050.00', '45.56', '6.00']);
});

it('totals each farmer per milk type and lists rejected entries', function () {
    $reports = app(CollectionReportService::class);

    expect($reports->farmerWise('2026-10-01', '2026-10-31')->rows)->toBe([
        ['F-001', 'Asha Pawar', 'Buffalo', 1, '5.00', '7.00', null, '300.00', '60.00', '0.00'],
        ['F-001', 'Asha Pawar', 'Cow', 2, '10.00', '4.00', '8.50', '400.00', '40.00', '6.00'],
        ['F-002', 'Bala More', 'Cow', 1, '30.00', '5.00', null, '1350.00', '45.00', '0.00'],
    ]);

    $rejected = $reports->rejected('2026-10-01', '2026-10-31');
    expect($rejected->rows)->toBe([['2026-10-01', 'Evening', 'F-001', 'Asha Pawar', 'Cow', '6.00', '2.50', null, 'Sour']])
        ->and($rejected->totals[5])->toBe('6.00');
});

it('shows the collection reports with exports, for reports.view only', function () {
    $this->actingAs($this->accountant)->get(route('reports.index'))->assertOk()->assertSee('Farmer-wise collection');
    $this->get(route('reports.collection', ['farmers', 'from' => '2026-10-01', 'to' => '2026-10-31']))->assertOk()->assertSee('Bala More')->assertSee('₹2,050.00');
    $this->get(route('reports.collection', ['daily', 'from' => '2026-10-01', 'to' => '2026-10-31', 'format' => 'excel']))->assertOk()->assertDownload();
    $this->get(route('reports.collection', ['rejected', 'from' => '2026-10-01', 'to' => '2026-10-31', 'format' => 'pdf']))->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $boy = User::factory()->forFarm($this->farm)->withRole(RoleName::DeliveryBoy)->create();
    $this->actingAs($boy)->get(route('reports.collection', 'daily'))->assertForbidden();
});
