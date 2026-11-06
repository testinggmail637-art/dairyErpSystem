<?php

/*
| Phase 3 acceptance criteria (spec Section 8), end to end over HTTP, in the order the spec lists them, on the
| production seed data (default farm, products, payment modes). Detailed behaviour is covered by the per-feature
| tests in tests/Feature/Sales and tests/Feature/Reports.
*/

use App\Enums\BillStatus;
use App\Enums\DeliveryStatus;
use App\Enums\MilkMovementType;
use App\Enums\MilkShift;
use App\Enums\MilkSource;
use App\Enums\MilkType;
use App\Enums\RoleName;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\Farm;
use App\Models\MilkStockMovement;
use App\Models\PaymentAllocation;
use App\Models\PaymentMode;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\MilkStockService;
use App\Support\CurrentFarm;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo('2026-11-05 09:00');
    $this->seed(DatabaseSeeder::class);

    $this->farm = Farm::where('code', 'MDF')->firstOrFail();
    app(CurrentFarm::class)->set($this->farm);
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->boy = User::factory()->forFarm($this->farm)->withRole(RoleName::DeliveryBoy)->create();
    $this->route = DeliveryRoute::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Ward 4', 'delivery_user_id' => $this->boy->id]);
    $this->cow = Product::where('code', 'COW')->firstOrFail(); // seeded ₹60
    $this->mode = PaymentMode::query()->active()->firstOrFail();
    app(MilkStockService::class)->recordIn(MilkSource::OwnFarm, MilkType::Cow, '2026-10-01', MilkShift::Morning, MilkMovementType::AdjustmentIn, '500', reason: 'Opening stock');
});

function acceptanceCustomer(array $attrs = []): Customer
{
    test()->actingAs(test()->owner)->post(route('customers.store'), array_merge([
        'name' => 'Sharma Ji', 'customer_type' => 'b2c', 'route_id' => test()->route->id, 'route_sequence' => 1, 'opening_balance' => '0',
    ], $attrs))->assertSessionHasNoErrors();

    return Customer::latest('id')->firstOrFail();
}

it('1. skips paused dates and generates idempotently', function () {
    $customer = acceptanceCustomer();
    $this->post(route('subscriptions.store'), ['customer_id' => $customer->id, 'product_id' => $this->cow->id, 'shift' => 'morning',
        'quantity' => '2', 'stock_source' => 'own_farm', 'start_date' => '2026-10-01'])->assertSessionHasNoErrors();
    $sub = Subscription::sole();
    $this->post(route('subscriptions.pauses.store', $sub), ['from_date' => '2026-10-10', 'to_date' => '2026-10-11'])->assertSessionHasNoErrors();

    foreach (['2026-10-09', '2026-10-10', '2026-10-11', '2026-10-12', '2026-10-12'] as $date) {
        $this->post(route('deliveries.generate'), ['date' => $date])->assertSessionHasNoErrors();
    }

    expect(Delivery::orderBy('delivery_date')->pluck('delivery_date')->map->toDateString()->all())->toBe(['2026-10-09', '2026-10-12']);
});

it('2. delivered creates stock out, extra litres work, skip removes it', function () {
    $customer = acceptanceCustomer();
    $row = Delivery::factory()->create(['customer_id' => $customer->id, 'product_id' => $this->cow->id, 'route_id' => $this->route->id,
        'delivery_date' => '2026-11-05', 'planned_qty' => '2.00', 'rate' => '60.00']);
    $out = fn () => MilkStockMovement::where('reference_id', $row->id)->where('movement_type', 'delivery')->value('quantity_out');

    $this->actingAs($this->boy)->post(route('deliveries.deliver', $row), ['delivered_qty' => '3'])->assertSessionHasNoErrors();
    expect($out())->toBe('3.00')->and($row->fresh()->amount)->toBe('180.00');

    $this->post(route('deliveries.skip', $row), ['skip_reason' => 'Locked'])->assertSessionHasNoErrors();
    expect($out())->toBeNull()->and($row->fresh()->status)->toBe(DeliveryStatus::Skipped);
});

it('3–5. one delivery in one bill, no overlaps, 500 + 3,600 − 1,000 = 3,100, finalized bill locked, cancel releases', function () {
    $customer = acceptanceCustomer(['opening_balance' => '500']);
    foreach (range(1, 30) as $day) {
        Delivery::factory()->create(['customer_id' => $customer->id, 'product_id' => $this->cow->id, 'delivery_date' => sprintf('2026-10-%02d', $day),
            'status' => 'delivered', 'delivered_qty' => '2.00', 'rate' => '60.00', 'amount' => '120.00']);
    }
    $this->post(route('customer-payments.store'), ['customer_id' => $customer->id, 'payment_date' => '2026-10-15', 'amount' => '1000',
        'payment_mode_id' => $this->mode->id])->assertSessionHasNoErrors();

    $period = ['scope' => 'one', 'customer_id' => $customer->id, 'period_from' => '2026-10-01', 'period_to' => '2026-10-31', 'bill_date' => '2026-10-31'];
    $this->post(route('bills.store'), $period)->assertSessionHasNoErrors();
    $this->post(route('bills.store'), ['period_from' => '2026-10-20'] + $period)->assertSessionHas('error'); // overlap
    $bill = Bill::sole();

    $this->post(route('bills.finalize', $bill))->assertSessionHasNoErrors();
    expect($bill->fresh())->total->toBe('3600.00')->previous_balance->toBe('500.00')->closing_balance->toBe('3100.00');

    $this->delete(route('bills.destroy', $bill))->assertSessionHas('error');
    $delivery = Delivery::first();
    $this->put(route('deliveries.update', $delivery), ['status' => 'delivered', 'delivered_qty' => '5', 'rate' => '60', 'reason' => 'Try'])->assertForbidden();

    $this->post(route('bills.cancel', $bill), ['reason' => 'Wrong period'])->assertSessionHasNoErrors();
    expect($bill->fresh()->status)->toBe(BillStatus::Cancelled)->and(Delivery::whereNotNull('bill_id')->count())->toBe(0);
});

it('6. ₹5,000 is allocated ₹3,000 to the older bill and ₹2,000 to the newer one', function () {
    $customer = acceptanceCustomer();
    $older = Bill::factory()->create(['customer_id' => $customer->id, 'status' => 'finalized', 'total' => '3000.00', 'bill_date' => '2026-09-30', 'bill_no' => 'B1']);
    $newer = Bill::factory()->create(['customer_id' => $customer->id, 'status' => 'finalized', 'total' => '4000.00', 'bill_date' => '2026-10-31', 'bill_no' => 'B2']);

    $this->post(route('customer-payments.store'), ['customer_id' => $customer->id, 'payment_date' => '2026-11-05', 'amount' => '5000',
        'payment_mode_id' => $this->mode->id])->assertSessionHasNoErrors();

    expect(PaymentAllocation::orderBy('id')->get(['bill_id', 'amount'])->map(fn ($a) => [$a->bill_id, $a->amount])->all())
        ->toBe([[$older->id, '3000.00'], [$newer->id, '2000.00']]);
});

it('7. the delivery boy sees only his route and cannot open other customers by URL', function () {
    $mine = acceptanceCustomer(['name' => 'On My Route']);
    $other = acceptanceCustomer(['name' => 'Other Route', 'route_id' => DeliveryRoute::factory()->create(['farm_id' => $this->farm->id])->id]);
    foreach ([$mine, $other] as $c) {
        Delivery::factory()->create(['customer_id' => $c->id, 'product_id' => $this->cow->id, 'route_id' => $c->route_id, 'delivery_date' => '2026-11-05']);
    }

    $this->flushSession(); // drop the owner's "customer added" flash before switching user

    $this->actingAs($this->boy)->get(route('deliveries.sheet', ['date' => '2026-11-05', 'shift' => 'morning']))
        ->assertOk()->assertSee('On My Route')->assertDontSee('Other Route');
    $this->get(route('customers.show', $other))->assertForbidden();
    $this->post(route('deliveries.deliver', Delivery::where('customer_id', $other->id)->sole()), ['delivered_qty' => '2'])->assertNotFound();
});
