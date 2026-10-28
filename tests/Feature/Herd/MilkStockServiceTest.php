<?php

use App\Enums\MilkMovementType;
use App\Enums\MilkShift;
use App\Enums\MilkSource;
use App\Enums\MilkType;
use App\Enums\RoleName;
use App\Exceptions\InsufficientMilkStockException;
use App\Models\Animal;
use App\Models\Farm;
use App\Models\MilkProductionRecord;
use App\Models\MilkStockMovement;
use App\Models\User;
use App\Services\MilkStockService;
use App\Support\CurrentFarm;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->travelTo('2026-10-20 08:00');
    $this->farm = Farm::factory()->create();
    app(CurrentFarm::class)->set($this->farm);
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->stock = app(MilkStockService::class);

    $cow = fn (string $tag) => Animal::factory()->create(['farm_id' => $this->farm->id, 'tag_no' => $tag]);
    $this->a = $cow('C-1');
    $this->b = $cow('C-2');
    $this->c = $cow('C-3');
    $this->buffalo = Animal::factory()->buffalo()->create(['farm_id' => $this->farm->id, 'tag_no' => 'B-1']);
});

function ownCow(): string
{
    return app(MilkStockService::class)->balance(MilkSource::OwnFarm, MilkType::Cow);
}

it('adds bulk milk to own-farm cow stock: 8+6, 10+8, 6+5 = +43 L (Phase 2 acceptance)', function () {
    $this->actingAs($this->owner);
    $grid = fn (string $shift, array $qty) => $this->post(route('milk-production.bulk'), ['production_date' => '2026-10-20', 'shift' => $shift, 'qty' => $qty])
        ->assertSessionHasNoErrors();

    $grid('morning', [$this->a->id => '8', $this->b->id => '10', $this->c->id => '6']);
    $grid('evening', [$this->a->id => '6', $this->b->id => '8', $this->c->id => '5']);

    expect(ownCow())->toBe('43.00')
        ->and(MilkStockMovement::count())->toBe(6)
        ->and($this->stock->balance(MilkSource::OwnFarm, MilkType::Buffalo))->toBe('0.00')
        ->and($this->stock->balance(MilkSource::Collection))->toBe('0.00');

    $movement = MilkStockMovement::query()->where('reference_id', MilkProductionRecord::where('animal_id', $this->a->id)->where('shift', 'morning')->value('id'))->sole();
    expect($movement->only(['stock_source', 'milk_type', 'movement_type', 'shift', 'quantity_in', 'quantity_out', 'created_by']))->toBe([
        'stock_source' => MilkSource::OwnFarm, 'milk_type' => MilkType::Cow, 'movement_type' => MilkMovementType::Production,
        'shift' => MilkShift::Morning, 'quantity_in' => '8.00', 'quantity_out' => '0.00', 'created_by' => $this->owner->id,
    ])->and($movement->movement_date->toDateString())->toBe('2026-10-20')
        ->and($movement->reference)->toBeInstanceOf(MilkProductionRecord::class);

    // saving the same grid again moves nothing
    $grid('morning', [$this->a->id => '8.00', $this->b->id => '10', $this->c->id => '6']);
    expect(MilkStockMovement::count())->toBe(6)->and(ownCow())->toBe('43.00');
});

it('moves stock by −3 when an entry goes from 10 to 7, and removes the movement on delete (Phase 2 acceptance)', function () {
    $this->actingAs($this->owner);
    $this->post(route('milk-production.bulk'), ['production_date' => '2026-10-20', 'shift' => 'morning', 'qty' => [$this->a->id => '10']]);
    $record = MilkProductionRecord::sole();

    $this->post(route('milk-production.bulk'), ['production_date' => '2026-10-20', 'shift' => 'morning', 'qty' => [$this->a->id => '7'], 'reason' => 'Misread the can'])
        ->assertSessionHasNoErrors();
    expect(ownCow())->toBe('7.00')->and(MilkStockMovement::sole()->quantity_in)->toBe('7.00');

    $this->put(route('milk-production.update', $record), ['production_date' => '2026-10-20', 'shift' => 'morning', 'animal_id' => $this->a->id, 'quantity_litre' => '9.5', 'reason' => 'Fix'])
        ->assertSessionHasNoErrors();
    expect(ownCow())->toBe('9.50')->and(MilkStockMovement::count())->toBe(1);

    $this->delete(route('milk-production.destroy', $record), ['reason' => 'Wrong cow'])->assertSessionHasNoErrors();
    expect(MilkStockMovement::count())->toBe(0)->and(ownCow())->toBe('0.00');
});

it('follows an entry moved to another animal, date or shift, and keeps no movement for 0 L', function () {
    $this->actingAs($this->owner);
    $this->post(route('milk-production.bulk'), ['production_date' => '2026-10-20', 'shift' => 'morning', 'qty' => [$this->a->id => '10']]);
    $record = MilkProductionRecord::sole();

    $this->put(route('milk-production.update', $record), ['production_date' => '2026-10-19', 'shift' => 'evening', 'animal_id' => $this->buffalo->id, 'quantity_litre' => '10', 'reason' => 'Wrong animal'])
        ->assertSessionHasNoErrors();
    $movement = MilkStockMovement::sole();
    expect($movement->milk_type)->toBe(MilkType::Buffalo)->and($movement->shift)->toBe(MilkShift::Evening)
        ->and($movement->movement_date->toDateString())->toBe('2026-10-19')
        ->and(ownCow())->toBe('0.00')
        ->and($this->stock->balance(MilkSource::OwnFarm, MilkType::Buffalo))->toBe('10.00');

    $this->put(route('milk-production.update', $record), ['production_date' => '2026-10-19', 'shift' => 'evening', 'animal_id' => $this->buffalo->id, 'quantity_litre' => '0', 'reason' => 'Gave nothing'])
        ->assertSessionHasNoErrors();
    expect(MilkStockMovement::count())->toBe(0);

    $this->post(route('milk-production.bulk'), ['production_date' => '2026-10-20', 'shift' => 'morning', 'qty' => [$this->b->id => '0']]);
    expect(MilkProductionRecord::count())->toBe(2)->and(MilkStockMovement::count())->toBe(0);
});

it('creates one movement for a single entry sent twice with the same client_uuid', function () {
    $this->actingAs($this->owner);
    $data = ['production_date' => '2026-10-20', 'shift' => 'morning', 'animal_id' => $this->a->id, 'quantity_litre' => '7.25', 'client_uuid' => (string) Str::uuid()];

    $this->post(route('milk-production.store'), $data)->assertSessionHasNoErrors();
    $this->post(route('milk-production.store'), $data)->assertSessionHasNoErrors();

    expect(MilkStockMovement::count())->toBe(1)->and(ownCow())->toBe('7.25');
});

it('reduces the balance by 5 L of wastage and blocks going out beyond the balance (Phase 2 acceptance)', function () {
    $this->stock->recordIn(MilkSource::OwnFarm, MilkType::Cow, '2026-10-20', MilkShift::Morning, MilkMovementType::AdjustmentIn, '20', reason: 'Opening stock');

    $this->stock->recordOut(MilkSource::OwnFarm, MilkType::Cow, '2026-10-20', null, MilkMovementType::Wastage, '5', reason: 'Spilled');
    expect(ownCow())->toBe('15.00');

    expect(fn () => $this->stock->recordOut(MilkSource::OwnFarm, MilkType::Cow, '2026-10-20', null, MilkMovementType::HomeUse, '15.01', reason: 'Tea'))
        ->toThrow(InsufficientMilkStockException::class, 'Not enough cow milk in Own Farm stock on 20-10-2026: 15.00 L available, 15.01 L needed.');
    expect(fn () => $this->stock->recordOut(MilkSource::OwnFarm, MilkType::Buffalo, '2026-10-20', null, MilkMovementType::Wastage, '1', reason: 'Spilled'))
        ->toThrow(InsufficientMilkStockException::class);
    expect(MilkStockMovement::count())->toBe(2);

    $this->stock->recordOut(MilkSource::OwnFarm, MilkType::Cow, '2026-10-20', null, MilkMovementType::CalfFeeding, '15', reason: 'Calves');
    expect(ownCow())->toBe('0.00');
});

it('checks the balance up to and including the movement date, all shifts', function () {
    $this->stock->recordIn(MilkSource::OwnFarm, MilkType::Cow, '2026-10-20', MilkShift::Evening, MilkMovementType::AdjustmentIn, '10', reason: 'Found');

    // milk of the 20th does not exist yet on the 19th
    expect(fn () => $this->stock->recordOut(MilkSource::OwnFarm, MilkType::Cow, '2026-10-19', null, MilkMovementType::Wastage, '1', reason: 'Spilled'))
        ->toThrow(InsufficientMilkStockException::class);

    // the evening's milk counts for a morning movement of the same day
    $this->stock->recordOut(MilkSource::OwnFarm, MilkType::Cow, '2026-10-20', MilkShift::Morning, MilkMovementType::Wastage, '4', reason: 'Spilled');

    expect($this->stock->balance(MilkSource::OwnFarm, MilkType::Cow, '2026-10-19'))->toBe('0.00')
        ->and($this->stock->balance(MilkSource::OwnFarm, MilkType::Cow, '2026-10-20'))->toBe('6.00')
        ->and($this->stock->balance())->toBe('6.00');
});

it('allows negative stock when the farm setting says so', function () {
    $this->farm->update(['settings' => ['allow_negative_milk_stock' => true]]);

    $this->stock->recordOut(MilkSource::Collection, MilkType::Buffalo, '2026-10-20', null, MilkMovementType::AdjustmentOut, '3', reason: 'Count correction');

    expect($this->stock->balance(MilkSource::Collection, MilkType::Buffalo))->toBe('-3.00');
});

it('refuses wrong direction, zero litres and a manual movement without a reason', function () {
    $in = fn (MilkMovementType $type, string $qty = '1', ?string $reason = 'x') => $this->stock->recordIn(MilkSource::OwnFarm, MilkType::Cow, '2026-10-20', null, $type, $qty, reason: $reason);

    expect(fn () => $in(MilkMovementType::Wastage))->toThrow(InvalidArgumentException::class, 'wastage is not an incoming movement.')
        ->and(fn () => $this->stock->recordOut(MilkSource::OwnFarm, MilkType::Cow, '2026-10-20', null, MilkMovementType::Production, '1'))
        ->toThrow(InvalidArgumentException::class, 'production is not an outgoing movement.')
        ->and(fn () => $in(MilkMovementType::AdjustmentIn, '0'))->toThrow(InvalidArgumentException::class, 'quantity above zero')
        ->and(fn () => $in(MilkMovementType::AdjustmentIn, '5', '  '))->toThrow(InvalidArgumentException::class, 'adjustment_in movement needs a reason');

    expect(MilkStockMovement::count())->toBe(0);
});

it('shows the stock exception as a quantity error on the form', function () {
    Route::middleware('web')->post('/_test/milk-out', fn () => throw new InsufficientMilkStockException(MilkSource::OwnFarm, MilkType::Cow, '2026-10-20', '2', '5'));
    $message = 'Not enough cow milk in Own Farm stock on 20-10-2026: 2.00 L available, 5.00 L needed.';

    $this->from('/milk-stock')->post('/_test/milk-out', ['quantity' => '5'])
        ->assertRedirect('/milk-stock')->assertSessionHasErrors(['quantity' => $message])->assertSessionHasInput('quantity', '5');
    $this->postJson('/_test/milk-out')->assertUnprocessable()->assertJsonPath('errors.quantity.0', $message);
});

it('builds the daily summary per source × milk type with the combined total', function () {
    $s = $this->stock;
    // 19th: 30 L cow produced, 2 L wasted → opening on the 20th = 28
    $s->recordIn(MilkSource::OwnFarm, MilkType::Cow, '2026-10-19', MilkShift::Morning, MilkMovementType::AdjustmentIn, '30', reason: 'Opening');
    $s->recordOut(MilkSource::OwnFarm, MilkType::Cow, '2026-10-19', null, MilkMovementType::Wastage, '2', reason: 'Spilled');
    // 20th
    $this->actingAs($this->owner)->post(route('milk-production.bulk'), ['production_date' => '2026-10-20', 'shift' => 'morning', 'qty' => [$this->a->id => '8', $this->buffalo->id => '6.5']]);
    $s->recordOut(MilkSource::OwnFarm, MilkType::Cow, '2026-10-20', null, MilkMovementType::HomeUse, '1.5', reason: 'Tea');
    $s->recordOut(MilkSource::OwnFarm, MilkType::Cow, '2026-10-20', null, MilkMovementType::CalfFeeding, '3', reason: 'Calves');
    $s->recordIn(MilkSource::OwnFarm, MilkType::Cow, '2026-10-20', null, MilkMovementType::AdjustmentIn, '1', reason: 'Recount');
    $s->recordOut(MilkSource::OwnFarm, MilkType::Cow, '2026-10-20', null, MilkMovementType::AdjustmentOut, '0.25', reason: 'Recount');
    $s->recordIn(MilkSource::Collection, MilkType::Cow, '2026-10-20', null, MilkMovementType::AdjustmentIn, '4', reason: 'Opening');
    // 21st: not part of the 20th
    $s->recordIn(MilkSource::OwnFarm, MilkType::Cow, '2026-10-21', null, MilkMovementType::AdjustmentIn, '99', reason: 'Later');

    $summary = $s->dailySummary('2026-10-20');
    $pick = fn (array $row) => array_intersect_key($row, array_flip(['opening', 'in', 'delivered', 'wastage', 'home_use', 'calf_feeding', 'adjustment', 'closing']));

    expect(array_keys($summary['rows']))->toBe(['own_farm.cow', 'own_farm.buffalo', 'collection.cow', 'collection.buffalo'])
        ->and($summary['rows']['own_farm.cow']['source'])->toBe(MilkSource::OwnFarm)
        ->and($pick($summary['rows']['own_farm.cow']))->toBe([
            'opening' => '28.00', 'in' => '8.00', 'delivered' => '0.00', 'wastage' => '0.00', 'home_use' => '1.50',
            'calf_feeding' => '3.00', 'adjustment' => '0.75', 'closing' => '32.25',
        ])
        ->and($pick($summary['rows']['own_farm.buffalo']))->toMatchArray(['opening' => '0.00', 'in' => '6.50', 'closing' => '6.50'])
        ->and($pick($summary['rows']['collection.cow']))->toMatchArray(['adjustment' => '4.00', 'closing' => '4.00'])
        ->and($pick($summary['rows']['collection.buffalo']))->toMatchArray(['opening' => '0.00', 'closing' => '0.00'])
        ->and($summary['combined'])->toBe([
            'opening' => '28.00', 'in' => '14.50', 'delivered' => '0.00', 'wastage' => '0.00', 'home_use' => '1.50',
            'calf_feeding' => '3.00', 'adjustment' => '4.75', 'closing' => '42.75',
        ])
        ->and($summary['rows']['own_farm.cow']['closing'])->toBe($s->balance(MilkSource::OwnFarm, MilkType::Cow, '2026-10-20'));
});

it('keeps each farm\'s stock separate', function () {
    $this->stock->recordIn(MilkSource::OwnFarm, MilkType::Cow, '2026-10-20', null, MilkMovementType::AdjustmentIn, '10', reason: 'Opening');

    app(CurrentFarm::class)->set(Farm::factory()->create());

    expect(ownCow())->toBe('0.00');
    expect(fn () => $this->stock->recordOut(MilkSource::OwnFarm, MilkType::Cow, '2026-10-20', null, MilkMovementType::Wastage, '1', reason: 'x'))
        ->toThrow(InsufficientMilkStockException::class);
});
