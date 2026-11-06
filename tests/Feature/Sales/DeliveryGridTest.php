<?php

use App\Enums\DeliveryStatus;
use App\Enums\MilkMovementType;
use App\Enums\MilkShift;
use App\Enums\MilkSource;
use App\Enums\MilkType;
use App\Enums\RoleName;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\Farm;
use App\Models\MilkStockMovement;
use App\Models\Product;
use App\Models\User;
use App\Services\MilkStockService;
use App\Support\CurrentFarm;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    app(CurrentFarm::class)->set($this->farm);
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->route = DeliveryRoute::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Ward 4']);
    $this->product = Product::factory()->create(['farm_id' => $this->farm->id, 'milk_type' => 'cow']);
    $this->stock = app(MilkStockService::class);
    $this->stock->recordIn(MilkSource::OwnFarm, MilkType::Cow, today()->toDateString(), MilkShift::Morning, MilkMovementType::Production, '10');
});

function gridRow(array $attrs = [], array $customer = []): Delivery
{
    $c = Customer::factory()->create(array_merge(['farm_id' => test()->farm->id, 'route_id' => test()->route->id], $customer));

    return Delivery::factory()->create(array_merge([
        'customer_id' => $c->id, 'product_id' => test()->product->id, 'route_id' => test()->route->id,
        'delivery_date' => today()->toDateString(), 'planned_qty' => '2.00', 'rate' => '60.00',
    ], $attrs));
}

it('lists and filters the day\'s deliveries with totals', function () {
    gridRow([], ['name' => 'Asha Morning']);
    gridRow(['shift' => 'evening', 'status' => 'skipped'], ['name' => 'Bala Evening']);
    gridRow(['delivery_date' => today()->subDay()->toDateString()], ['name' => 'Yesterday Guy']);

    $this->actingAs($this->owner)->get(route('deliveries.grid'))->assertOk()
        ->assertSee('Asha Morning')->assertSee('Bala Evening')->assertDontSee('Yesterday Guy')->assertSee('4.00 L');
    $this->get(route('deliveries.grid', ['shift' => 'evening']))->assertSee('Bala Evening')->assertDontSee('Asha Morning');
    $this->get(route('deliveries.grid', ['status' => 'pending']))->assertSee('Asha Morning')->assertDontSee('Bala Evening');
    $this->get(route('deliveries.grid', ['search' => 'asha']))->assertSee('Asha Morning')->assertDontSee('Bala Evening');
});

it('bulk marks only pending, unbilled, not-future rows delivered with planned litres', function () {
    $a = gridRow();
    $b = gridRow(['planned_qty' => '3.00']);
    $skipped = gridRow(['status' => 'skipped']);
    $billed = gridRow(['bill_id' => 7]);
    $future = gridRow(['delivery_date' => today()->addDay()->toDateString()]);

    $this->actingAs($this->owner)->post(route('deliveries.bulk-deliver'), ['ids' => [$a->id, $b->id, $skipped->id, $billed->id, $future->id]])
        ->assertSessionHas('success', '2 deliveries marked delivered.');

    expect($a->fresh())->status->toBe(DeliveryStatus::Delivered)->delivered_qty->toBe('2.00')->amount->toBe('120.00')
        ->and($b->fresh()->amount)->toBe('180.00')
        ->and($skipped->fresh()->status)->toBe(DeliveryStatus::Skipped)
        ->and($billed->fresh()->status)->toBe(DeliveryStatus::Pending)
        ->and($future->fresh()->status)->toBe(DeliveryStatus::Pending)
        ->and(MilkStockMovement::where('movement_type', 'delivery')->sum('quantity_out'))->toEqual(5);
});

it('rolls the whole batch back when stock runs short', function () {
    $a = gridRow(['planned_qty' => '6.00']);
    $b = gridRow(['planned_qty' => '6.00']);

    $this->actingAs($this->owner)->post(route('deliveries.bulk-deliver'), ['ids' => [$a->id, $b->id]])->assertSessionHasErrors('quantity');

    expect(Delivery::where('status', 'delivered')->count())->toBe(0)
        ->and(MilkStockMovement::where('movement_type', 'delivery')->count())->toBe(0);
});

it('edits a row with a reason: rate and litres recalculate the amount and the stock', function () {
    $row = gridRow();
    $this->actingAs($this->owner);

    $payload = ['status' => 'delivered', 'delivered_qty' => '3', 'rate' => '55', 'notes' => ''];
    $this->put(route('deliveries.update', $row), $payload)->assertSessionHasErrors('reason');
    $this->put(route('deliveries.update', $row), $payload + ['reason' => 'Took extra'])->assertSessionHasNoErrors();

    expect($row->fresh())->status->toBe(DeliveryStatus::Delivered)->delivered_qty->toBe('3.00')->rate->toBe('55.00')->amount->toBe('165.00')
        ->and(MilkStockMovement::where('reference_id', $row->id)->value('quantity_out'))->toBe('3.00')
        ->and(Activity::where('subject_type', $row->getMorphClass())->where('event', 'updated')->latest('id')->first()->reason())->toBe('Took extra');

    $this->put(route('deliveries.update', $row), ['status' => 'pending', 'rate' => '55', 'reason' => 'Undo'])->assertSessionHasNoErrors();
    expect($row->fresh())->status->toBe(DeliveryStatus::Pending)->amount->toBe('0.00')->delivered_by->toBeNull()
        ->and(MilkStockMovement::where('reference_id', $row->id)->exists())->toBeFalse();
});

it('locks billed rows and keeps the grid for managers only', function () {
    $billed = gridRow(['bill_id' => 7]);

    $this->actingAs($this->owner)->get(route('deliveries.edit', $billed))->assertForbidden();
    $this->put(route('deliveries.update', $billed), ['status' => 'delivered', 'delivered_qty' => '2', 'rate' => '60', 'reason' => 'x x'])->assertForbidden();

    $boy = User::factory()->forFarm($this->farm)->withRole(RoleName::DeliveryBoy)->create();
    $this->actingAs($boy)->get(route('deliveries.grid'))->assertForbidden();
    $this->post(route('deliveries.bulk-deliver'), ['ids' => [$billed->id]])->assertForbidden();
});

it('keeps the grid at a constant number of queries', function () {
    $count = function () {
        $this->actingAs($this->owner)->get(route('deliveries.grid'))->assertOk();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('deliveries.grid'))->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    foreach (range(1, 3) as $i) {
        gridRow(['status' => 'delivered', 'delivered_by' => $this->owner->id]);
    }
    $few = $count();
    foreach (range(1, 30) as $i) {
        gridRow();
    }
    expect($count())->toBe($few);
});
