<?php

use App\Enums\MilkType;
use App\Enums\RateMethod;
use App\Enums\RoleName;
use App\Models\Farm;
use App\Models\Farmer;
use App\Models\RateChart;
use App\Models\User;
use App\Services\RateChartService;
use App\Support\CurrentFarm;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    app(CurrentFarm::class)->set($this->farm);
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->actingAs($this->owner);
    $this->rates = app(RateChartService::class);
});

function chartPayload(array $overrides = []): array
{
    return array_merge(['name' => 'Cow fixed', 'milk_type' => 'cow', 'method' => 'fixed', 'fixed_rate' => '40',
        'effective_from' => '2026-04-01', 'is_default' => '1', 'is_active' => '1'], $overrides);
}

function chart(array $attributes = []): RateChart
{
    return RateChart::factory()->create(['farm_id' => test()->farm->id] + $attributes);
}

it('prices 20 L at fixed ₹40 = ₹800, by fat, by fat + SNF, and flags below standard (spec acceptance)', function () {
    $fixed = chart();
    $this->getJson(route('rate-charts.preview', [$fixed, 'qty' => '20']))->assertOk()
        ->assertJson(['rate' => '40.00', 'amount' => '800.00', 'below_standard' => false]);

    $fat = chart(['method' => RateMethod::FatRate, 'fixed_rate' => null, 'fat_rate' => '8.50', 'is_default' => false]);
    expect($this->rates->calculate($fat, '4.5', null))->toBe(['rate' => '38.25', 'below_standard' => false]);

    $fatSnf = chart(['method' => RateMethod::FatSnfRate, 'fixed_rate' => null, 'fat_rate' => '6.15', 'snf_rate' => '2.35',
        'min_fat' => '3.5', 'min_snf' => '8.5', 'is_default' => false]);
    // 4.1 × 6.15 + 8.7 × 2.35 = 25.215 + 20.445 = 45.66 (one rounding at the end)
    expect($this->rates->calculate($fatSnf, '4.1', '8.7'))->toBe(['rate' => '45.66', 'below_standard' => false])
        ->and($this->rates->calculate($fatSnf, '3.2', '8.7')['below_standard'])->toBeTrue()
        ->and($this->rates->calculate($fatSnf, '4.0', '8.0')['below_standard'])->toBeTrue();

    $this->getJson(route('rate-charts.preview', [$fatSnf, 'fat' => '4.1']))->assertStatus(422)->assertJsonPath('error', 'Enter the SNF % — this rate chart needs it.');
});

it('saves a slab chart, uses the first matching row and refuses overlapping rows', function () {
    $slabs = [
        ['fat_from' => '3.0', 'fat_to' => '3.5', 'rate_per_litre' => '36'],
        ['fat_from' => '3.5', 'fat_to' => '4.0', 'rate_per_litre' => '40'],   // touching 3.5 is fine
        ['fat_from' => '4.0', 'fat_to' => '6.0', 'snf_from' => '8.0', 'snf_to' => '8.4', 'rate_per_litre' => '44'],
        ['fat_from' => '4.0', 'fat_to' => '6.0', 'snf_from' => '8.4', 'snf_to' => '9.5', 'rate_per_litre' => '47'],
        ['fat_from' => '', 'fat_to' => '', 'rate_per_litre' => ''], // empty row is ignored
    ];
    $this->post(route('rate-charts.store'), chartPayload(['name' => 'Buffalo slab', 'milk_type' => 'buffalo', 'method' => 'slab', 'fixed_rate' => '', 'slabs' => $slabs]))
        ->assertSessionHasNoErrors();

    $chart = RateChart::with('slabs')->sole();
    expect($chart->slabs)->toHaveCount(4)->and($chart->fixed_rate)->toBeNull()
        ->and($this->rates->calculate($chart, '3.5', null)['rate'])->toBe('36.00') // first row wins on the shared end
        ->and($this->rates->calculate($chart, '3.8', null)['rate'])->toBe('40.00')
        ->and($this->rates->calculate($chart, '4.5', '8.6')['rate'])->toBe('47.00');
    expect(fn () => $this->rates->calculate($chart, '6.5', '8.6'))->toThrow(DomainException::class);
    expect(fn () => $this->rates->calculate($chart, '4.5', null))->toThrow(DomainException::class); // SNF needed for those rows

    $overlap = [['fat_from' => '3.0', 'fat_to' => '4.0', 'rate_per_litre' => '36'], ['fat_from' => '3.8', 'fat_to' => '4.5', 'rate_per_litre' => '40']];
    $this->post(route('rate-charts.store'), chartPayload(['name' => 'Bad', 'method' => 'slab', 'is_default' => '0', 'slabs' => $overlap]))
        ->assertSessionHasErrors('slabs');
    $this->post(route('rate-charts.store'), chartPayload(['name' => 'Bad', 'method' => 'slab', 'is_default' => '0', 'slabs' => [['fat_from' => '4', 'fat_to' => '3', 'rate_per_litre' => '30']]]))
        ->assertSessionHasErrors('slabs.0.fat_to');
    $this->post(route('rate-charts.store'), chartPayload(['name' => 'Bad', 'method' => 'fat_snf_rate', 'is_default' => '0', 'fat_rate' => '6']))
        ->assertSessionHasErrors('snf_rate');
});

it('allows one default chart per milk on a date and resolves farmer chart, else the default in force', function () {
    $this->post(route('rate-charts.store'), chartPayload())->assertSessionHasNoErrors();
    $this->post(route('rate-charts.store'), chartPayload(['name' => 'Cow 2027', 'fixed_rate' => '42', 'effective_from' => '2027-04-01']))
        ->assertSessionHasErrors('is_default');

    $old = RateChart::sole();
    $this->put(route('rate-charts.update', $old), chartPayload(['effective_to' => '2027-03-31', 'reason' => 'New rate from April']))->assertSessionHasNoErrors();
    $this->post(route('rate-charts.store'), chartPayload(['name' => 'Cow 2027', 'fixed_rate' => '42', 'effective_from' => '2027-04-01']))
        ->assertSessionHasNoErrors();
    $this->post(route('rate-charts.store'), chartPayload(['name' => 'Buffalo', 'milk_type' => 'buffalo', 'fixed_rate' => '55']))->assertSessionHasNoErrors();
    $special = chart(['name' => 'Ramesh special', 'fixed_rate' => '45', 'is_default' => false]);

    $farmer = Farmer::factory()->create(['farm_id' => $this->farm->id]);
    expect($this->rates->resolve($farmer, MilkType::Cow, '2027-03-31')->name)->toBe('Cow fixed')
        ->and($this->rates->resolve($farmer, MilkType::Cow, '2027-04-01')->name)->toBe('Cow 2027')
        ->and($this->rates->resolve($farmer, MilkType::Buffalo, '2026-11-09')->name)->toBe('Buffalo')
        ->and($this->rates->resolve(null, MilkType::Cow, '2026-01-01'))->toBeNull();

    $farmer->update(['rate_chart_id' => $special->id]);
    expect($this->rates->resolve($farmer, MilkType::Cow, '2026-11-09')->name)->toBe('Ramesh special')
        ->and($this->rates->resolve($farmer, MilkType::Buffalo, '2026-11-09')->name)->toBe('Buffalo'); // own chart is cow only
});

it('sets a farmer\'s own chart of the same milk; a chart in use cannot be deleted; edits need a reason', function () {
    $cow = chart(['name' => 'Cow special', 'is_default' => false]);
    $buffalo = chart(['name' => 'Buffalo', 'milk_type' => MilkType::Buffalo]);
    $farmer = Farmer::factory()->create(['farm_id' => $this->farm->id]);
    $payload = ['name' => $farmer->name, 'default_milk_type' => 'cow', 'opening_balance' => '0', 'reason' => 'Special rate'];

    $this->put(route('farmers.update', $farmer), $payload + ['rate_chart_id' => $buffalo->id])->assertSessionHasErrors('rate_chart_id');
    $this->put(route('farmers.update', $farmer), $payload + ['rate_chart_id' => $cow->id])->assertSessionHasNoErrors();
    $this->get(route('farmers.show', $farmer))->assertSee('Cow special');

    $this->put(route('rate-charts.update', $cow), chartPayload(['name' => 'Cow special', 'is_default' => '0']))->assertSessionHasErrors('reason');
    $this->delete(route('rate-charts.destroy', $cow), ['reason' => 'Old'])->assertSessionHas('error');
    $this->delete(route('rate-charts.destroy', $buffalo), ['reason' => 'Old'])->assertRedirect(route('rate-charts.index'));
    expect($buffalo->fresh()->trashed())->toBeTrue();

    $this->get(route('rate-charts.index'))->assertOk()->assertSee('Cow special');
    $this->get(route('rate-charts.show', $cow))->assertOk()->assertSee($farmer->code);
    $this->get(route('rate-charts.create', ['copy' => $cow->id]))->assertOk()->assertSee('Cow special (copy)');
});

it('is only for rate_charts.manage; another farm\'s chart is 404', function () {
    $foreign = RateChart::factory()->create(['farm_id' => Farm::factory()->create()->id]);
    $this->get(route('rate-charts.show', $foreign))->assertNotFound();

    $operator = User::factory()->forFarm($this->farm)->withRole(RoleName::CollectionOperator)->create();
    $this->actingAs($operator)->get(route('rate-charts.index'))->assertForbidden();
    $this->post(route('rate-charts.store'), chartPayload())->assertForbidden();
});
