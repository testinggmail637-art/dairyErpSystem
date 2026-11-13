<?php

/*
| Phase 4 acceptance criteria (spec Section 8), end to end over HTTP, in the order the spec lists them, on the
| production seed data (default farm, payment modes). Detailed behaviour is covered by the per-feature tests in
| tests/Feature/Collection and tests/Feature/Reports.
*/

use App\Enums\MilkSource;
use App\Enums\MilkType;
use App\Enums\RoleName;
use App\Models\Farm;
use App\Models\Farmer;
use App\Models\FarmerAdvance;
use App\Models\FarmerPayment;
use App\Models\FarmerPaymentCycle;
use App\Models\MilkCollection;
use App\Models\PaymentMode;
use App\Models\RateChart;
use App\Models\User;
use App\Services\MilkStockService;
use App\Support\CurrentFarm;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->travelTo('2026-11-12 08:00');
    $this->seed(DatabaseSeeder::class);
    $this->farm = Farm::where('code', 'MDF')->firstOrFail();
    app(CurrentFarm::class)->set($this->farm);
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->actingAs($this->owner);

    $this->post(route('rate-charts.store'), ['name' => 'Cow fixed', 'milk_type' => 'cow', 'method' => 'fixed', 'fixed_rate' => '40',
        'effective_from' => '2026-04-01', 'is_default' => '1', 'is_active' => '1'])->assertSessionHasNoErrors();
    $this->post(route('farmers.store'), ['name' => 'Asha Pawar', 'default_milk_type' => 'cow', 'opening_balance' => '0', 'is_active' => '1'])
        ->assertSessionHasNoErrors();
    $this->farmer = Farmer::sole();
});

function p4Collect(array $overrides = []): TestResponse
{
    return test()->postJson(route('collections.store'), array_merge([
        'collection_date' => '2026-11-01', 'shift' => 'morning', 'farmer_id' => test()->farmer->id, 'milk_type' => 'cow',
        'quantity_litre' => '20', 'client_uuid' => (string) Str::uuid(),
    ], $overrides));
}

it('1. prices 20 L @ fixed ₹40 = ₹800; a fat_snf_rate chart computes; below-standard asks to accept or reject', function () {
    p4Collect()->assertCreated();
    expect(MilkCollection::sole()->amount)->toBe('800.00');

    $this->post(route('rate-charts.store'), ['name' => 'Buffalo fat+SNF', 'milk_type' => 'buffalo', 'method' => 'fat_snf_rate',
        'fat_rate' => '6.15', 'snf_rate' => '2.35', 'min_fat' => '3.5', 'min_snf' => '8.5',
        'effective_from' => '2026-04-01', 'is_default' => '1', 'is_active' => '1'])->assertSessionHasNoErrors();
    $chart = RateChart::where('method', 'fat_snf_rate')->sole();
    $this->getJson(route('rate-charts.preview', [$chart, 'fat' => '4.1', 'snf' => '8.7', 'qty' => '10']))->assertOk()
        ->assertJson(['rate' => '45.66', 'amount' => '456.60', 'below_standard' => false]);

    p4Collect(['milk_type' => 'buffalo', 'fat' => '3.2', 'snf' => '8.7'])->assertUnprocessable()->assertJsonPath('below_standard', true);
    p4Collect(['milk_type' => 'buffalo', 'fat' => '3.2', 'snf' => '8.7', 'accept_below' => '1'])->assertCreated();
    expect(MilkCollection::where('milk_type', 'buffalo')->sole()->rate_per_litre)->toBe('40.13'); // 3.2 × 6.15 + 8.7 × 2.35 = 40.125
});

it('2. keeps saved amounts when the rate chart changes', function () {
    p4Collect()->assertCreated();
    $chart = RateChart::sole();
    $this->put(route('rate-charts.update', $chart), ['name' => 'Cow fixed', 'milk_type' => 'cow', 'method' => 'fixed', 'fixed_rate' => '45',
        'effective_from' => '2026-04-01', 'is_default' => '1', 'is_active' => '1', 'reason' => 'New rate'])->assertSessionHasNoErrors();

    expect($chart->fresh()->fixed_rate)->toBe('45.00')->and(MilkCollection::sole()->amount)->toBe('800.00');
});

it('3. adds accepted milk to collection stock by milk type; rejected milk does not', function () {
    p4Collect()->assertCreated();
    p4Collect(['shift' => 'evening', 'quantity_litre' => '7', 'quality_status' => 'rejected', 'rejection_reason' => 'Sour'])->assertCreated();

    $stock = app(MilkStockService::class);
    expect($stock->balance(MilkSource::Collection, MilkType::Cow))->toBe('20.00')
        ->and($stock->balance(MilkSource::Collection, MilkType::Buffalo))->toBe('0.00')
        ->and($stock->balance(MilkSource::OwnFarm, MilkType::Cow))->toBe('0.00');
});

it('4. cycle: gross 8,000 − advance 500 + bonus 200 = 7,700; its collections cannot be edited once finalized', function () {
    foreach (range(1, 10) as $day) {
        p4Collect(['collection_date' => sprintf('2026-11-%02d', $day)])->assertCreated(); // 10 × 20 L × ₹40 = ₹8,000
    }
    $cash = PaymentMode::where('code', 'CASH')->value('id');
    $this->post(route('farmer-advances.store'), ['farmer_id' => $this->farmer->id, 'advance_date' => '2026-11-02', 'amount' => '500',
        'payment_mode_id' => $cash])->assertSessionHasNoErrors();
    expect(FarmerAdvance::sole()->amount)->toBe('500.00');

    $this->post(route('farmer-payments.store'), ['from_date' => '2026-11-01', 'to_date' => '2026-11-10'])->assertSessionHasNoErrors();
    $statement = FarmerPayment::sole();
    $this->put(route('farmer-statements.update', $statement), ['bonus' => '200', 'advance_deduction' => '500', 'other_deduction' => '0'])
        ->assertSessionHasNoErrors();
    expect($statement->fresh())->gross_amount->toBe('8000.00')->net_payable->toBe('7700.00');

    $cycle = FarmerPaymentCycle::sole();
    $this->post(route('farmer-payments.finalize', $cycle))->assertSessionHasNoErrors();

    $entry = MilkCollection::first();
    $this->put(route('collections.update', $entry), ['collection_date' => '2026-11-01', 'shift' => 'morning', 'milk_type' => 'cow',
        'quantity_litre' => '25', 'reason' => 'Late change'])->assertForbidden();
    $this->delete(route('collections.destroy', $entry), ['reason' => 'Late change'])->assertForbidden();
    expect($entry->fresh()->quantity_litre)->toBe('20.00');

    $this->get(route('farmer-statements.pdf', $statement))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->get(route('reports.collection', ['farmers', 'from' => '2026-11-01', 'to' => '2026-11-10']))->assertOk()->assertSee('₹8,000.00');
});

it('5. a collection is never in two cycles and cycles cannot overlap', function () {
    p4Collect(['collection_date' => '2026-11-05'])->assertCreated();
    $this->post(route('farmer-payments.store'), ['from_date' => '2026-11-01', 'to_date' => '2026-11-10'])->assertSessionHasNoErrors();

    $this->post(route('farmer-payments.store'), ['from_date' => '2026-11-05', 'to_date' => '2026-11-12'])->assertSessionHas('error');
    $this->post(route('farmer-payments.store'), ['from_date' => '2026-10-25', 'to_date' => '2026-11-01'])->assertSessionHas('error');
    p4Collect(['collection_date' => '2026-11-06'])->assertStatus(422); // the period of a cycle is closed for new milk

    expect(FarmerPaymentCycle::count())->toBe(1)
        ->and(MilkCollection::sole()->farmer_payment_id)->toBe(FarmerPayment::sole()->id);
});
