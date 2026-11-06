<?php

use App\Enums\DeliveryStatus;
use App\Enums\MilkMovementType;
use App\Enums\MilkShift;
use App\Enums\MilkSource;
use App\Enums\MilkType;
use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\Farm;
use App\Models\MilkStockMovement;
use App\Models\PaymentMode;
use App\Models\Product;
use App\Models\User;
use App\Services\MilkStockService;
use App\Support\CurrentFarm;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    app(CurrentFarm::class)->set($this->farm);
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->boy = User::factory()->forFarm($this->farm)->withRole(RoleName::DeliveryBoy)->create();
    $this->myRoute = DeliveryRoute::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Ward 4', 'delivery_user_id' => $this->boy->id]);
    $this->otherRoute = DeliveryRoute::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Ward 9']);
    $this->product = Product::factory()->create(['farm_id' => $this->farm->id, 'milk_type' => 'cow']);
    $this->mode = PaymentMode::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Cash']);

    app(MilkStockService::class)->recordIn(MilkSource::OwnFarm, MilkType::Cow, today()->toDateString(), MilkShift::Morning, MilkMovementType::Production, '50');
});

function sheetRow(DeliveryRoute $route, array $attrs = [], array $customer = []): Delivery
{
    $c = Customer::factory()->create(array_merge(['farm_id' => test()->farm->id, 'route_id' => $route->id], $customer));

    return Delivery::factory()->create(array_merge([
        'customer_id' => $c->id, 'product_id' => test()->product->id, 'route_id' => $route->id,
        'delivery_date' => today()->toDateString(), 'shift' => 'morning', 'planned_qty' => '2.00', 'rate' => '60.00',
    ], $attrs));
}

it('shows the delivery boy only his routes, in route order', function () {
    sheetRow($this->myRoute, [], ['name' => 'Second Stop', 'route_sequence' => 2]);
    sheetRow($this->myRoute, [], ['name' => 'First Stop', 'route_sequence' => 1]);
    sheetRow($this->otherRoute, [], ['name' => 'Not Mine']);

    $this->actingAs($this->boy)->get(route('deliveries.sheet', ['date' => today()->toDateString(), 'shift' => 'morning']))
        ->assertOk()->assertSeeInOrder(['First Stop', 'Second Stop'])->assertDontSee('Not Mine')->assertDontSee('Ward 9');

    // asking for another route falls back to his own
    $this->get(route('deliveries.sheet', ['shift' => 'morning', 'route_id' => $this->otherRoute->id]))->assertDontSee('Not Mine');
});

it('marks delivered with extra litres, syncs stock on change, and skip removes the movement', function () {
    $row = sheetRow($this->myRoute);
    $this->actingAs($this->boy);

    $this->post(route('deliveries.deliver', $row), ['delivered_qty' => '2.5'])->assertSessionHasNoErrors();
    $row->refresh();
    expect($row)->status->toBe(DeliveryStatus::Delivered)->delivered_qty->toBe('2.50')->amount->toBe('150.00')
        ->delivered_by->toBe($this->boy->id)->delivered_at->not->toBeNull();
    $movement = fn () => MilkStockMovement::where('reference_type', $row->getMorphClass())->where('reference_id', $row->id)->first();
    expect($movement())->movement_type->toBe(MilkMovementType::Delivery)->quantity_out->toBe('2.50');

    $this->post(route('deliveries.deliver', $row), ['delivered_qty' => '1'])->assertSessionHasNoErrors();
    expect($row->fresh()->amount)->toBe('60.00')->and($movement()->quantity_out)->toBe('1.00');

    $this->post(route('deliveries.skip', $row), ['skip_reason' => 'House locked'])->assertSessionHasNoErrors();
    expect($row->fresh())->status->toBe(DeliveryStatus::Skipped)->delivered_qty->toBe('0.00')->amount->toBe('0.00')
        ->skip_reason->toBe('House locked')
        ->and($movement())->toBeNull();
});

it('refuses delivery beyond the stock and keeps the row pending', function () {
    $row = sheetRow($this->myRoute);

    $this->actingAs($this->boy)->post(route('deliveries.deliver', $row), ['delivered_qty' => '80'])->assertSessionHasErrors('quantity');
    expect($row->fresh()->status)->toBe(DeliveryStatus::Pending)
        ->and(MilkStockMovement::where('movement_type', 'delivery')->count())->toBe(0);
});

it('blocks other routes (404), billed rows and future rows', function () {
    $other = sheetRow($this->otherRoute);
    $billed = sheetRow($this->myRoute, ['bill_id' => 99]);
    $future = sheetRow($this->myRoute, ['delivery_date' => today()->addDay()->toDateString()]);
    $this->actingAs($this->boy);

    $this->post(route('deliveries.deliver', $other), ['delivered_qty' => '2'])->assertNotFound();
    $this->post(route('deliveries.collect', $other), ['amount' => '100', 'payment_mode_id' => $this->mode->id])->assertForbidden();
    $this->post(route('deliveries.deliver', $billed), ['delivered_qty' => '2'])->assertForbidden();
    $this->post(route('deliveries.skip', $future), ['skip_reason' => 'Away'])->assertForbidden();

    // a manager may work on any route
    $this->actingAs($this->owner)->post(route('deliveries.deliver', $other), ['delivered_qty' => '2'])->assertSessionHasNoErrors();
    expect($other->fresh()->status)->toBe(DeliveryStatus::Delivered);
});

it('collects a payment once per client_uuid and shows it on the row', function () {
    $row = sheetRow($this->myRoute, [], ['name' => 'Sharma Ji']);
    $uuid = '3f2b8c1e-8a7d-4c1e-9b2a-1d2e3f4a5b6c';
    $this->actingAs($this->boy);

    $payload = ['amount' => '₹ 1,200', 'payment_mode_id' => $this->mode->id, 'reference_no' => 'UPI123', 'client_uuid' => $uuid];
    $this->post(route('deliveries.collect', $row), $payload)->assertSessionHasNoErrors();
    $this->post(route('deliveries.collect', $row), $payload)->assertSessionHasNoErrors();

    $payment = CustomerPayment::sole();
    expect($payment)->amount->toBe('1200.00')->customer_id->toBe($row->customer_id)->collected_by->toBe($this->boy->id)
        ->payment_date->toDateString()->toBe(today()->toDateString())->farm_id->toBe($this->farm->id);

    $this->get(route('deliveries.sheet', ['shift' => 'morning']))->assertSee('Paid today ₹1,200.00');

    $this->post(route('deliveries.collect', $row), ['amount' => '0', 'payment_mode_id' => $this->mode->id])->assertSessionHasErrors('amount');
});

it('keeps the sheet at a constant number of queries', function () {
    $count = function () {
        $this->actingAs($this->boy)->get(route('deliveries.sheet', ['shift' => 'morning']))->assertOk();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('deliveries.sheet', ['shift' => 'morning']))->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    foreach (range(1, 3) as $i) {
        sheetRow($this->myRoute);
    }
    $few = $count();
    foreach (range(1, 30) as $i) {
        sheetRow($this->myRoute);
    }
    expect($count())->toBe($few);
});
