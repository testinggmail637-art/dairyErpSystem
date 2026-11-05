<?php

use App\Enums\DeliveryStatus;
use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\CustomerRate;
use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\Farm;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\DeliverySheetService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->route = DeliveryRoute::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Ward 4']);
    $this->product = Product::factory()->create(['farm_id' => $this->farm->id, 'default_rate' => '60.00']);
    $this->customer = Customer::factory()->create(['farm_id' => $this->farm->id, 'route_id' => $this->route->id]);
});

function deliverySub(array $attrs = []): Subscription
{
    return Subscription::factory()->create(array_merge([
        'customer_id' => test()->customer->id, 'product_id' => test()->product->id, 'quantity' => '2.00',
        'start_date' => '2026-11-01', 'end_date' => null,
    ], $attrs));
}

it('creates pending rows only for active, started, not ended, not paused subscriptions of active customers', function () {
    $due = deliverySub();
    deliverySub(['shift' => 'evening', 'status' => 'stopped']);
    $paused = deliverySub(['shift' => 'evening', 'start_date' => '2026-11-01', 'product_id' => Product::factory()->create(['farm_id' => $this->farm->id])->id]);
    $paused->pauses()->create(['farm_id' => $this->farm->id, 'from_date' => '2026-11-10', 'to_date' => '2026-11-12']);
    deliverySub(['product_id' => Product::factory()->create(['farm_id' => $this->farm->id])->id, 'start_date' => '2026-11-11']);   // not started
    deliverySub(['product_id' => Product::factory()->create(['farm_id' => $this->farm->id])->id, 'end_date' => '2026-11-09']);     // ended
    deliverySub(['customer_id' => Customer::factory()->create(['farm_id' => $this->farm->id, 'is_active' => false])->id]);         // inactive customer
    $deleted = Customer::factory()->create(['farm_id' => $this->farm->id]);
    deliverySub(['customer_id' => $deleted->id]);
    $deleted->delete();

    expect(app(DeliverySheetService::class)->generate($this->farm, '2026-11-10'))->toBe(1);

    $row = Delivery::allFarms()->sole();
    expect($row)->subscription_id->toBe($due->id)->status->toBe(DeliveryStatus::Pending)
        ->planned_qty->toBe('2.00')->delivered_qty->toBe('0.00')->amount->toBe('0.00')
        ->rate->toBe('60.00')->route_id->toBe($this->route->id)->farm_id->toBe($this->farm->id)
        ->delivery_date->toDateString()->toBe('2026-11-10');

    // On the 13th the pause is over and the one starting on the 11th has begun.
    expect(app(DeliverySheetService::class)->generate($this->farm, '2026-11-13'))->toBe(3);
});

it('is idempotent and never touches existing rows', function () {
    deliverySub();
    $service = app(DeliverySheetService::class);

    expect($service->generate($this->farm, '2026-11-10'))->toBe(1);
    Delivery::allFarms()->update(['status' => 'skipped', 'planned_qty' => '5.00']);
    deliverySub(['shift' => 'evening']);

    expect($service->generate($this->farm, '2026-11-10'))->toBe(1)
        ->and(Delivery::allFarms()->count())->toBe(2)
        ->and(Delivery::allFarms()->where('shift', 'morning')->sole())->status->toBe(DeliveryStatus::Skipped)->planned_qty->toBe('5.00');
});

it('snapshots the customer rate valid on the date, else the product default', function () {
    deliverySub();
    $other = Customer::factory()->create(['farm_id' => $this->farm->id]);
    deliverySub(['customer_id' => $other->id]);
    CustomerRate::factory()->create(['customer_id' => $this->customer->id, 'product_id' => $this->product->id, 'rate' => '55.00', 'effective_from' => '2026-11-01']);
    CustomerRate::factory()->create(['customer_id' => $this->customer->id, 'product_id' => $this->product->id, 'rate' => '58.00', 'effective_from' => '2026-11-20']);

    app(DeliverySheetService::class)->generate($this->farm, '2026-11-10');

    expect(Delivery::allFarms()->where('customer_id', $this->customer->id)->value('rate'))->toBe('55.00')
        ->and(Delivery::allFarms()->where('customer_id', $other->id)->value('rate'))->toBe('60.00');

    // Changing rates later does not change the generated row.
    $this->product->update(['default_rate' => '65.00']);
    expect(Delivery::allFarms()->where('customer_id', $other->id)->value('rate'))->toBe('60.00');
});

it('uses a constant number of queries however many subscriptions there are', function () {
    $count = function (string $date) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(DeliverySheetService::class)->generate($this->farm, $date);
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };
    $make = fn (int $n) => Subscription::factory()->count($n)->create([
        'customer_id' => fn () => Customer::factory()->create(['farm_id' => $this->farm->id])->id,
        'product_id' => $this->product->id, 'start_date' => '2026-11-01',
    ]);

    $make(3);
    $few = $count('2026-11-10');
    $make(40);
    expect($count('2026-11-11'))->toBe($few);
});

it('generates from the button for managers only', function () {
    deliverySub();
    $this->actingAs($this->owner)->post(route('deliveries.generate'), ['date' => '2026-11-10'])
        ->assertRedirect(route('deliveries.index', ['date' => '2026-11-10']))->assertSessionHas('success');
    expect(Delivery::count())->toBe(1);

    $this->get(route('deliveries.index', ['date' => '2026-11-10']))->assertOk()->assertSee('Ward 4')->assertSee('Generate sheet');

    $boy = User::factory()->forFarm($this->farm)->withRole(RoleName::DeliveryBoy)->create();
    $this->actingAs($boy)->post(route('deliveries.generate'), ['date' => '2026-11-11'])->assertForbidden();
    $this->get(route('deliveries.index', ['date' => '2026-11-10']))->assertOk()->assertDontSee('Ward 4')->assertDontSee('Generate sheet');

    $this->route->update(['delivery_user_id' => $boy->id]);
    $this->get(route('deliveries.index', ['date' => '2026-11-10']))->assertSee('Ward 4');
});

it('runs from the command for a date, and on schedule once a day after the farm time', function () {
    deliverySub();
    $this->artisan('deliveries:generate', ['date' => '2026-11-10'])->assertSuccessful();
    expect(Delivery::allFarms()->count())->toBe(1);

    $this->farm->update(['settings' => ['delivery_sheet_generate_time' => '05:30']]);
    $this->travelTo(now()->setDate(2026, 11, 12)->setTime(5, 0));
    $this->artisan('deliveries:generate', ['--scheduled' => true])->assertSuccessful();
    expect(Delivery::allFarms()->whereDate('delivery_date', '2026-11-12')->count())->toBe(0);

    $this->travelTo(now()->setTime(5, 30));
    $this->artisan('deliveries:generate', ['--scheduled' => true])->assertSuccessful();
    expect(Delivery::allFarms()->whereDate('delivery_date', '2026-11-12')->count())->toBe(1);

    // Later runs the same day do nothing (even after a new subscription — the button covers that).
    deliverySub(['shift' => 'evening']);
    $this->travelTo(now()->setTime(9, 0));
    $this->artisan('deliveries:generate', ['--scheduled' => true])->assertSuccessful();
    expect(Delivery::allFarms()->whereDate('delivery_date', '2026-11-12')->count())->toBe(1);
});

it('does not delete a subscription that has deliveries', function () {
    $sub = deliverySub();
    app(DeliverySheetService::class)->generate($this->farm, '2026-11-10');

    $this->actingAs($this->owner)->delete(route('subscriptions.destroy', $sub))->assertSessionHas('error');
    expect(Subscription::find($sub->id))->not->toBeNull();
});
