<?php

use App\Enums\MilkMovementType;
use App\Enums\MilkShift;
use App\Enums\MilkSource;
use App\Enums\MilkType;
use App\Enums\RoleName;
use App\Models\Animal;
use App\Models\Farm;
use App\Models\MilkStockMovement;
use App\Models\User;
use App\Services\MilkStockService;
use App\Support\CurrentFarm;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->travelTo('2026-10-20 10:00');
    $this->farm = Farm::factory()->create();
    app(CurrentFarm::class)->set($this->farm);
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->herdStaff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $this->delivery = User::factory()->forFarm($this->farm)->withRole(RoleName::DeliveryBoy)->create();
    $this->cow = Animal::factory()->create(['farm_id' => $this->farm->id, 'tag_no' => 'C-1', 'name' => null]);
    $this->stock = app(MilkStockService::class);
});

function usage(array $data = []): array
{
    return $data + [
        'movement_type' => 'wastage', 'movement_date' => '2026-10-20', 'shift' => '', 'stock_source' => 'own_farm',
        'milk_type' => 'cow', 'quantity' => '5', 'reason' => 'Spilled while pouring', 'client_uuid' => (string) Str::uuid(),
    ];
}

function produce(string $litres, string $date = '2026-10-20'): void
{
    test()->actingAs(test()->owner)->post(route('milk-production.bulk'), ['production_date' => $date, 'shift' => 'morning', 'qty' => [test()->cow->id => $litres]])
        ->assertSessionHasNoErrors();
}

it('shows the daily stock sheet with rows per source × milk type and the combined total', function () {
    produce('12', '2026-10-19');
    produce('10');
    $this->stock->recordOut(MilkSource::OwnFarm, MilkType::Cow, '2026-10-20', null, MilkMovementType::CalfFeeding, '2.5', reason: 'Two calves');

    $html = $this->actingAs($this->herdStaff)->get(route('milk-stock.index', ['date' => '2026-10-20']))->assertOk()
        ->assertSee('Stock sheet of 20-10-2026')
        ->assertSee('Own Farm · Cow')->assertSee('Own Farm · Buffalo')->assertSee('Collection Center · Cow')->assertSee('Collection Center · Buffalo')
        ->assertSee('Two calves')->assertSee('−2.50')
        ->assertSee(route('milk-stock.usage.create', ['date' => '2026-10-20']), false)
        ->assertDontSee(route('milk-stock.adjustments.create', ['date' => '2026-10-20']), false) // herd staff cannot adjust
        ->getContent();

    // own farm cow: opening 12, in 10, calf feeding 2.50, closing 19.50; combined row the same
    expect(preg_replace('/\s+/', ' ', strip_tags($html)))
        ->toContain('Own Farm · Cow 12.00 10.00 0.00 0.00 0.00 2.50 0.00 19.50')
        ->toContain('Combined 12.00 10.00 0.00 0.00 0.00 2.50 0.00 19.50');

    $this->actingAs($this->owner)->get(route('milk-stock.index'))->assertOk()
        ->assertSee(route('milk-stock.adjustments.create', ['date' => '2026-10-20']), false);
});

it('records wastage from the usage form and lowers the balance; idempotent on client_uuid (Phase 2 acceptance)', function () {
    produce('20');
    $data = usage();

    $this->actingAs($this->herdStaff)->post(route('milk-stock.usage.store'), $data)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('milk-stock.index', ['date' => '2026-10-20']))
        ->assertSessionHas('success', 'Wastage saved: 5.00 L cow milk, Own Farm.');
    $this->post(route('milk-stock.usage.store'), $data)->assertSessionHas('success', 'This entry was already saved.');

    $movement = MilkStockMovement::query()->where('movement_type', 'wastage')->sole();
    expect($movement->only(['quantity_out', 'reason', 'client_uuid', 'created_by', 'shift']))->toBe([
        'quantity_out' => '5.00', 'reason' => 'Spilled while pouring', 'client_uuid' => $data['client_uuid'], 'created_by' => $this->herdStaff->id, 'shift' => null,
    ])->and($this->stock->balance(MilkSource::OwnFarm, MilkType::Cow))->toBe('15.00');

    $this->post(route('milk-stock.usage.store'), usage(['movement_type' => 'home_use', 'quantity' => '1.25', 'shift' => 'evening', 'reason' => 'Tea']))->assertSessionHasNoErrors();
    expect($this->stock->balance(MilkSource::OwnFarm, MilkType::Cow))->toBe('13.75')
        ->and(MilkStockMovement::query()->where('movement_type', 'home_use')->value('shift'))->toBe(MilkShift::Evening);
});

it('blocks usage beyond the balance with a quantity error (Phase 2 acceptance)', function () {
    produce('4');

    $this->actingAs($this->herdStaff)->from(route('milk-stock.usage.create'))->post(route('milk-stock.usage.store'), usage())
        ->assertRedirect(route('milk-stock.usage.create'))
        ->assertSessionHasErrors(['quantity' => 'Not enough cow milk in Own Farm stock on 20-10-2026: 4.00 L available, 5.00 L needed.'])
        ->assertSessionHasInput('reason', 'Spilled while pouring');

    expect(MilkStockMovement::query()->where('movement_type', 'wastage')->count())->toBe(0);

    $this->farm->update(['settings' => ['allow_negative_milk_stock' => true]]);
    $this->post(route('milk-stock.usage.store'), usage())->assertSessionHasNoErrors();
    expect($this->stock->balance(MilkSource::OwnFarm, MilkType::Cow))->toBe('-1.00');
});

it('validates the usage form', function (array $data, string $field) {
    $this->actingAs($this->owner)->post(route('milk-stock.usage.store'), usage($data))->assertSessionHasErrors($field);
})->with([
    'no reason' => [['reason' => '  '], 'reason'],
    'zero litres' => [['quantity' => '0'], 'quantity'],
    'three decimals' => [['quantity' => '1.255'], 'quantity'],
    'future date' => [['movement_date' => '2026-10-21'], 'movement_date'],
    'adjustment type on usage form' => [['movement_type' => 'adjustment_in'], 'movement_type'],
    'production type' => [['movement_type' => 'production'], 'movement_type'],
    'bad source' => [['stock_source' => 'market'], 'stock_source'],
]);

it('lets only milk_stock.adjust make adjustments, in or out, with a reason', function () {
    $this->actingAs($this->herdStaff)->get(route('milk-stock.adjustments.create'))->assertForbidden();
    $this->post(route('milk-stock.adjustments.store'), usage(['movement_type' => 'adjustment_in']))->assertForbidden();

    $this->actingAs($this->owner)->get(route('milk-stock.adjustments.create'))->assertOk()
        ->assertSee('Adjustment in')->assertSee('Adjustment out')->assertDontSee('Calf feeding');
    $this->post(route('milk-stock.adjustments.store'), usage(['movement_type' => 'adjustment_in', 'stock_source' => 'collection', 'milk_type' => 'buffalo', 'quantity' => '8', 'reason' => 'Recount']))
        ->assertSessionHasNoErrors()->assertSessionHas('success', 'Adjustment in saved: 8.00 L buffalo milk, Collection Center.');
    $this->post(route('milk-stock.adjustments.store'), usage(['movement_type' => 'adjustment_out', 'stock_source' => 'collection', 'milk_type' => 'buffalo', 'quantity' => '3', 'reason' => 'Recount']))
        ->assertSessionHasNoErrors();
    $this->post(route('milk-stock.adjustments.store'), usage(['movement_type' => 'wastage']))->assertSessionHasErrors('movement_type');

    expect($this->stock->balance(MilkSource::Collection, MilkType::Buffalo))->toBe('5.00');
});

it('shows the usage form with the usage types only', function () {
    $this->actingAs($this->herdStaff)->get(route('milk-stock.usage.create', ['date' => '2026-10-19']))->assertOk()
        ->assertSee('Wastage')->assertSee('Home use')->assertSee('Calf feeding')->assertDontSee('Adjustment in')
        ->assertSee('value="2026-10-19"', false)->assertSee('data-client-uuid', false);
});

it('shows the ledger with a running balance from the balance before the period', function () {
    produce('12', '2026-10-18');
    produce('10', '2026-10-19');
    $this->stock->recordOut(MilkSource::OwnFarm, MilkType::Cow, '2026-10-19', null, MilkMovementType::Wastage, '3', reason: 'Sour milk');
    produce('9');

    $page = fn (array $q) => preg_replace('/\s+/', ' ', strip_tags($this->actingAs($this->owner)->get(route('milk-stock.ledger', $q))->assertOk()->getContent()));

    $text = $page(['from' => '2026-10-19', 'to' => '2026-10-20', 'source' => 'own_farm', 'milk_type' => 'cow']);
    expect($text)->toContain('Balance on 19-10-2026: 12.00 L')
        ->toContain('In: 19.00 L')->toContain('Out: 3.00 L')
        ->toContain('Balance on 20-10-2026 (closing): 28.00 L')
        ->toContain('19-10-2026 Morning Production Own Farm · Cow C-1 10.00 22.00')
        ->toContain('19-10-2026 Wastage Own Farm · Cow Sour milk 3.00 19.00')
        ->toContain('20-10-2026 Morning Production Own Farm · Cow C-1 9.00 28.00')
        ->not->toContain('18-10-2026 Morning');

    // movement filter hides rows but the balance column still counts everything
    $text = $page(['from' => '2026-10-19', 'to' => '2026-10-20', 'movement_type' => 'wastage']);
    expect($text)->toContain('Wastage Own Farm · Cow Sour milk 3.00 19.00')->not->toContain('C-1 10.00')
        ->toContain('In: 0.00 L')->toContain('(closing): 28.00 L');

    // AJAX fragment
    $this->get(route('milk-stock.ledger', ['source' => 'collection']), ['X-Ajax-List' => '1'])
        ->assertOk()->assertHeader('X-Ajax-List-Fragment', '1')->assertSee('No milk movements for these filters.');
});

it('needs milk_stock.view and stays inside the farm', function () {
    $this->actingAs($this->delivery)->get(route('milk-stock.index'))->assertForbidden();
    $this->actingAs($this->delivery)->get(route('milk-stock.ledger'))->assertForbidden();
    $this->actingAs($this->delivery)->post(route('milk-stock.usage.store'), usage())->assertForbidden();

    $other = Farm::factory()->create();
    MilkStockMovement::create(['farm_id' => $other->id, 'movement_date' => '2026-10-20', 'stock_source' => 'own_farm', 'milk_type' => 'cow',
        'movement_type' => 'adjustment_in', 'quantity_in' => '99', 'quantity_out' => '0', 'reason' => 'Other farm stock']);

    $this->actingAs($this->owner)->get(route('milk-stock.index'))->assertDontSee('Other farm stock')->assertDontSee('99.00');
    $this->get(route('milk-stock.ledger'))->assertDontSee('Other farm stock');
});
