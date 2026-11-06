<?php

use App\Enums\B2bCategory;
use App\Enums\DeliveryStatus;
use App\Enums\MilkMovementType;
use App\Enums\MilkShift;
use App\Enums\MilkSource;
use App\Enums\MilkType;
use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\CustomerRate;
use App\Models\Delivery;
use App\Models\Farm;
use App\Models\MilkStockMovement;
use App\Models\Product;
use App\Models\User;
use App\Services\MilkStockService;
use App\Support\CurrentFarm;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    app(CurrentFarm::class)->set($this->farm);
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->plant = Customer::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Amul Plant', 'customer_type' => 'b2b', 'b2b_category' => B2bCategory::DairyPlant]);
    $this->product = Product::factory()->create(['farm_id' => $this->farm->id, 'milk_type' => 'buffalo', 'default_rate' => '70.00']);
    app(MilkStockService::class)->recordIn(MilkSource::Collection, MilkType::Buffalo, today()->toDateString(), MilkShift::Morning, MilkMovementType::Collection, '500');
});

function dispatchPayload(array $overrides = []): array
{
    return array_merge([
        'customer_id' => test()->plant->id, 'product_id' => test()->product->id, 'delivery_date' => today()->toDateString(),
        'shift' => 'morning', 'stock_source' => 'collection', 'delivered_qty' => '300', 'rate' => '',
        'fat' => '6.5', 'snf' => '9.0', 'buyer_measured_qty' => '', 'buyer_fat' => '', 'buyer_snf' => '',
        'client_uuid' => '7d1f0c2a-3b4c-4d5e-8f90-a1b2c3d4e5f6',
    ], $overrides);
}

it('dispatches a delivered row without subscription, at the customer rate, taking stock out', function () {
    CustomerRate::factory()->create(['customer_id' => $this->plant->id, 'product_id' => $this->product->id, 'rate' => '48.00', 'effective_from' => '2026-01-01']);

    $this->actingAs($this->owner)->post(route('deliveries.dispatch.store'), dispatchPayload())->assertSessionHasNoErrors();

    $d = Delivery::sole();
    expect($d)->status->toBe(DeliveryStatus::Delivered)->subscription_id->toBeNull()->planned_qty->toBe('0.00')
        ->delivered_qty->toBe('300.00')->rate->toBe('48.00')->amount->toBe('14400.00')
        ->fat->toBe('6.50')->snf->toBe('9.00')->buyer_measured_qty->toBeNull()
        ->stock_source->toBe(MilkSource::Collection)->delivered_by->toBe($this->owner->id)
        ->and(MilkStockMovement::where('reference_id', $d->id)->where('movement_type', 'delivery')->value('quantity_out'))->toBe('300.00');

    // the same form submitted again returns the first record
    $this->post(route('deliveries.dispatch.store'), dispatchPayload())->assertSessionHas('success', 'This dispatch was already saved.');
    expect(Delivery::count())->toBe(1);
});

it('uses a typed rate, and rejects a second row for the same customer, product, date and shift', function () {
    $this->actingAs($this->owner)->post(route('deliveries.dispatch.store'), dispatchPayload(['rate' => '50']))->assertSessionHasNoErrors();
    expect(Delivery::sole()->amount)->toBe('15000.00');

    $this->post(route('deliveries.dispatch.store'), dispatchPayload(['client_uuid' => '11111111-2222-4333-8444-555555555555']))
        ->assertSessionHasErrors('customer_id');
    $this->post(route('deliveries.dispatch.store'), dispatchPayload(['client_uuid' => '11111111-2222-4333-8444-555555555556', 'shift' => 'evening', 'delivered_qty' => '100']))
        ->assertSessionHasNoErrors();
});

it('validates dispatch input and stock', function (array $input, string $field) {
    $this->actingAs($this->owner)->post(route('deliveries.dispatch.store'), dispatchPayload($input))->assertSessionHasErrors($field);
    expect(Delivery::count())->toBe(0);
})->with([
    'future date' => [['delivery_date' => '2099-01-01'], 'delivery_date'],
    'zero litres' => [['delivered_qty' => '0'], 'delivered_qty'],
    'fat too high' => [['fat' => '25'], 'fat'],
    'more than stock' => [['delivered_qty' => '900'], 'quantity'],
]);

it('adds the buyer readings later from the row edit', function () {
    $this->actingAs($this->owner)->post(route('deliveries.dispatch.store'), dispatchPayload());
    $d = Delivery::sole();

    $this->put(route('deliveries.update', $d), [
        'status' => 'delivered', 'delivered_qty' => '300', 'rate' => $d->rate, 'fat' => '6.5', 'snf' => '9',
        'buyer_measured_qty' => '296.5', 'buyer_fat' => '6.3', 'buyer_snf' => '8.9', 'reason' => 'Plant slip received',
    ])->assertSessionHasNoErrors();

    expect($d->fresh())->buyer_measured_qty->toBe('296.50')->buyer_fat->toBe('6.30')->buyer_snf->toBe('8.90')->fat->toBe('6.50');
});

it('is for managers only', function () {
    $boy = User::factory()->forFarm($this->farm)->withRole(RoleName::DeliveryBoy)->create();

    $this->actingAs($boy)->get(route('deliveries.dispatch.create'))->assertForbidden();
    $this->post(route('deliveries.dispatch.store'), dispatchPayload())->assertForbidden();
    $this->actingAs($this->owner)->get(route('deliveries.dispatch.create'))->assertOk()->assertSee('Readings');
});
