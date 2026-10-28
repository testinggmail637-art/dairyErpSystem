<?php

use App\Enums\MilkShift;
use App\Enums\RoleName;
use App\Models\Animal;
use App\Models\Farm;
use App\Models\MilkProductionRecord;
use App\Models\User;
use App\Services\MilkDropAlertService;
use App\Support\CurrentFarm;

beforeEach(function () {
    $this->travelTo('2026-10-20 18:00');
    $this->farm = Farm::factory()->create();
    app(CurrentFarm::class)->set($this->farm);
    $this->herdStaff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $this->cow = Animal::factory()->create(['farm_id' => $this->farm->id, 'tag_no' => 'C-1', 'name' => null]);
    $this->service = app(MilkDropAlertService::class);
});

/** Entries for the days before 20-10-2026: $litres[0] = 19th, [1] = 18th … */
function history(Animal $animal, array $litres, string $shift = 'morning'): void
{
    foreach ($litres as $i => $l) {
        MilkProductionRecord::factory()->for($animal)->create([
            'production_date' => now()->subDays($i + 1)->toDateString(), 'shift' => $shift, 'quantity_litre' => $l,
        ]);
    }
}

function today_milk(Animal $animal, string $litres, string $shift = 'morning'): void
{
    MilkProductionRecord::factory()->for($animal)->create(['production_date' => '2026-10-20', 'shift' => $shift, 'quantity_litre' => $litres]);
}

it('fires for avg 10 → today 7: 30 % ≥ 20 % (Phase 2 acceptance)', function () {
    history($this->cow, ['10', '10', '10', '10', '10', '10', '10']);
    today_milk($this->cow, '7');

    $alerts = $this->service->alerts('2026-10-20');

    expect($alerts)->toHaveCount(1);
    $a = $alerts->first();
    expect($a->animal->tag_no)->toBe('C-1')
        ->and($a->shift)->toBe(MilkShift::Morning)
        ->and([$a->today, $a->average, $a->entries, $a->drop])->toBe(['7.00', '10.00', 7, '30.0']);
});

it('alerts at exactly the threshold but not just below it', function (string $today, bool $fires) {
    history($this->cow, ['10', '10', '10']);
    today_milk($this->cow, $today);

    expect($this->service->alerts('2026-10-20'))->toHaveCount($fires ? 1 : 0);
})->with([
    '20 % drop' => ['8', true],
    '19 % drop' => ['8.1', false],
    'more than usual' => ['12', false],
    'nothing at all (100 %)' => ['0', true],
]);

it('needs at least 3 previous entries', function () {
    history($this->cow, ['10', '10']);
    today_milk($this->cow, '2');

    expect($this->service->alerts('2026-10-20'))->toBeEmpty();
});

it('averages only the previous 7 entries of the same shift within 30 days', function () {
    // newest 7 average 10; the 8th (older) is 100 and must not count
    history($this->cow, ['9', '11', '10', '10', '9', '11', '10', '100']);
    // evening is its own series
    history($this->cow, ['4', '4', '4'], 'evening');
    today_milk($this->cow, '7');
    today_milk($this->cow, '4', 'evening');

    $alerts = $this->service->alerts('2026-10-20');
    expect($alerts)->toHaveCount(1)->and($alerts->first()->average)->toBe('10.00')->and($alerts->first()->drop)->toBe('30.0');

    // entries older than 30 days are ignored → too little history
    $old = Animal::factory()->create(['farm_id' => $this->farm->id, 'tag_no' => 'C-OLD']);
    foreach ([31, 32, 33, 34] as $d) {
        MilkProductionRecord::factory()->for($old)->create(['production_date' => now()->subDays($d)->toDateString(), 'quantity_litre' => '10']);
    }
    today_milk($old, '1');
    expect($this->service->alerts('2026-10-20')->pluck('animal.tag_no')->all())->toBe(['C-1']);
});

it('follows the farm\'s alert percent, lists biggest drops first and filters by shift', function () {
    $b = Animal::factory()->create(['farm_id' => $this->farm->id, 'tag_no' => 'C-2', 'name' => null]);
    history($this->cow, ['10', '10', '10']);
    history($b, ['10', '10', '10'], 'evening');
    today_milk($this->cow, '8.5'); // 15 %
    today_milk($b, '5', 'evening'); // 50 %

    expect($this->service->alerts('2026-10-20')->pluck('animal.tag_no')->all())->toBe(['C-2']);

    $this->farm->update(['settings' => ['milk_drop_alert_percent' => 15]]);
    app(CurrentFarm::class)->set($this->farm->fresh());

    expect($this->service->alerts('2026-10-20')->pluck('animal.tag_no')->all())->toBe(['C-2', 'C-1'])
        ->and($this->service->alerts('2026-10-20', MilkShift::Morning)->pluck('animal.tag_no')->all())->toBe(['C-1']);
});

it('shows alerts on the alerts page and on the entry grid of that shift', function () {
    history($this->cow, ['10', '10', '10', '10']);
    today_milk($this->cow, '7');

    $this->actingAs($this->herdStaff)->get(route('milk-alerts.index'))->assertOk()
        ->assertSee('1 animal gave much less milk than usual')
        ->assertSee('C-1')->assertSee('7.00 L against an average of 10.00 L (last 4 entries): down 30.0 %')
        ->assertSee('Check health — this is a signal, not a diagnosis.')
        ->assertSee(route('animals.show', ['animal' => $this->cow->id, 'tab' => 'milk']), false);

    $this->get(route('milk-alerts.index', ['date' => '2026-10-19']))->assertSee('No milk drop alerts on 19-10-2026.');

    $this->get(route('milk-production.index', ['date' => '2026-10-20', 'shift' => 'morning']))->assertSee('data-milk-alerts', false)->assertSee('down 30.0 %');
    $this->get(route('milk-production.index', ['date' => '2026-10-20', 'shift' => 'evening']))->assertDontSee('data-milk-alerts', false);

    // menu item
    $this->get(route('milk-alerts.index'))->assertSee('Milk Alerts');
});

it('needs milk_production.view and stays inside the farm', function () {
    $delivery = User::factory()->forFarm($this->farm)->withRole(RoleName::DeliveryBoy)->create();
    $this->actingAs($delivery)->get(route('milk-alerts.index'))->assertForbidden();

    $other = Animal::factory()->create(['farm_id' => Farm::factory()->create()->id, 'tag_no' => 'X-1']);
    history($other, ['10', '10', '10']);
    today_milk($other, '1');

    expect($this->service->alerts('2026-10-20'))->toBeEmpty();
});
