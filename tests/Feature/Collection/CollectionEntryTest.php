<?php

use App\Enums\MilkSource;
use App\Enums\MilkType;
use App\Enums\QualityStatus;
use App\Enums\RateMethod;
use App\Enums\RoleName;
use App\Models\Farm;
use App\Models\Farmer;
use App\Models\MilkCollection;
use App\Models\RateChart;
use App\Models\User;
use App\Services\MilkStockService;
use App\Support\CurrentFarm;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->travelTo('2026-11-09 07:30');
    $this->farm = Farm::factory()->create();
    app(CurrentFarm::class)->set($this->farm);
    $this->operator = User::factory()->forFarm($this->farm)->withRole(RoleName::CollectionOperator)->create();
    $this->actingAs($this->operator);
    $this->chart = RateChart::factory()->create(['farm_id' => $this->farm->id]); // cow, fixed ₹40, default
    $this->farmer = Farmer::factory()->create(['farm_id' => $this->farm->id, 'code' => 'F-007', 'name' => 'Asha Pawar']);
});

function collect_(array $overrides = []): TestResponse
{
    return test()->postJson(route('collections.store'), array_merge([
        'collection_date' => '2026-11-09', 'shift' => 'morning', 'farmer_id' => test()->farmer->id, 'milk_type' => 'cow',
        'quantity_litre' => '20', 'client_uuid' => (string) Str::uuid(),
    ], $overrides));
}

it('saves 20 L at fixed ₹40 = ₹800 and adds it to the cow collection stock (spec acceptance)', function () {
    collect_()->assertCreated()->assertJsonPath('created', true);

    $row = MilkCollection::sole();
    expect($row)->rate_per_litre->toBe('40.00')->amount->toBe('800.00')->quality_status->toBe(QualityStatus::Accepted)
        ->rate_chart_id->toBe($this->chart->id)
        ->and(app(MilkStockService::class)->balance(MilkSource::Collection, MilkType::Cow))->toBe('20.00')
        ->and(app(MilkStockService::class)->balance(MilkSource::OwnFarm, MilkType::Cow))->toBe('0.00');

    // changing the chart later does not change the saved amount
    $this->chart->update(['fixed_rate' => '45.00']);
    expect($row->fresh()->amount)->toBe('800.00');
});

it('keeps rejected milk at ₹0 and out of stock; a reason is required', function () {
    collect_(['quality_status' => 'rejected'])->assertUnprocessable()->assertJsonValidationErrors('rejection_reason');
    collect_(['quality_status' => 'rejected', 'rejection_reason' => 'Sour'])->assertCreated();

    expect(MilkCollection::sole())->amount->toBe('0.00')->rate_per_litre->toBe('0.00')->rejection_reason->toBe('Sour')
        ->and(app(MilkStockService::class)->balance(MilkSource::Collection, MilkType::Cow))->toBe('0.00');
});

it('asks before saving below-standard milk: accept at the rate or reject', function () {
    $this->chart->update(['method' => RateMethod::FatRate, 'fixed_rate' => null, 'fat_rate' => '9.00', 'min_fat' => '3.5']);

    collect_(['fat' => '3.0'])->assertUnprocessable()->assertJsonPath('below_standard', true)->assertJsonPath('rate', '27.00');
    expect(MilkCollection::count())->toBe(0);

    collect_(['fat' => '3.0', 'accept_below' => '1'])->assertCreated();
    expect(MilkCollection::sole()->amount)->toBe('540.00'); // 20 × 3.0 × 9

    collect_(['fat' => '3.0', 'milk_type' => 'buffalo', 'quality_status' => 'rejected', 'rejection_reason' => 'Below standard fat / SNF'])
        ->assertCreated(); // rejected needs no chart
    collect_(['fat' => ''])->assertStatus(422); // a fat chart needs the fat reading — but this farmer's cow milk is already in
});

it('takes one entry per farmer, shift and milk; a repeated submit returns the first; no chart is a clear error', function () {
    $uuid = (string) Str::uuid();
    collect_(['client_uuid' => $uuid])->assertCreated();
    collect_(['client_uuid' => $uuid])->assertOk()->assertJsonPath('created', false);
    collect_()->assertUnprocessable()->assertJsonPath('message', 'F-007 Asha Pawar already has cow milk in the 09-11-2026 morning shift. Change that entry instead.');
    collect_(['shift' => 'evening'])->assertCreated();
    collect_(['milk_type' => 'buffalo'])->assertUnprocessable()->assertJsonPath('message', 'There is no rate chart for buffalo milk on 09-11-2026. Add one in Rate Charts.');

    expect(MilkCollection::count())->toBe(2);
    collect_(['collection_date' => '2026-11-10'])->assertJsonValidationErrors('collection_date');
    collect_(['quantity_litre' => '501'])->assertJsonValidationErrors('quantity_litre');
});

it('shows the shift with totals (fat / SNF averaged by litres) and a live quote', function () {
    $other = Farmer::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Bala More']);
    collect_(['quantity_litre' => '10', 'fat' => '4.0', 'snf' => '8.0'])->assertCreated();
    collect_(['farmer_id' => $other->id, 'quantity_litre' => '30', 'fat' => '5.0', 'snf' => '9.0'])->assertCreated();

    $this->get(route('collections.index'))->assertOk() // 07:30 → morning shift
        ->assertSee('Asha Pawar')->assertSee('Bala More')
        ->assertViewHas('totals', fn ($t) => $t['litres'] === '40.00' && $t['fat'] === '4.75' && $t['snf'] === '8.75' && $t['amount'] === '1600.00');
    $this->get(route('collections.index', ['shift' => 'evening']))->assertOk()->assertDontSee('Bala More');

    $this->getJson(route('collections.quote', ['farmer_id' => $this->farmer->id, 'milk_type' => 'cow', 'collection_date' => '2026-11-09', 'quantity_litre' => '12.5']))
        ->assertOk()->assertJson(['rate' => '40.00', 'amount' => '500.00', 'below_standard' => false]);
    $this->getJson(route('farmers.search', ['q' => '7']))->assertJsonPath('results.0.id', $this->farmer->id);
});

it('lets collections.view see but not save; delivery staff get 403; a farmer with milk cannot be deleted', function () {
    collect_()->assertCreated();

    $accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
    $this->actingAs($accountant)->get(route('collections.index'))->assertOk()->assertDontSee('id="collection-form"', false);
    collect_(['shift' => 'evening'])->assertForbidden();

    $deliveryBoy = User::factory()->forFarm($this->farm)->withRole(RoleName::DeliveryBoy)->create();
    $this->actingAs($deliveryBoy)->get(route('collections.index'))->assertForbidden();

    $owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->actingAs($owner)->delete(route('farmers.destroy', $this->farmer), ['reason' => 'Left'])->assertSessionHas('error');
    expect($this->farmer->fresh()->trashed())->toBeFalse();
});
