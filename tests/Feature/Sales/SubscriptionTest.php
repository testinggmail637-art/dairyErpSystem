<?php

use App\Enums\MilkShift;
use App\Enums\MilkSource;
use App\Enums\RoleName;
use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\Farm;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\SubscriptionPause;
use App\Models\User;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->customer = Customer::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Sharma Ji', 'code' => 'C-0001']);
    $this->product = Product::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Cow Milk']);
});

function subscriptionPayload(array $overrides = []): array
{
    return array_merge([
        'customer_id' => test()->customer->id, 'product_id' => test()->product->id, 'shift' => 'morning',
        'quantity' => '2', 'stock_source' => 'own_farm', 'start_date' => '2026-11-01', 'end_date' => '', 'notes' => '',
    ], $overrides);
}

it('adds a subscription and edits it, stopping it', function () {
    $this->actingAs($this->owner)->post(route('subscriptions.store'), subscriptionPayload(['quantity' => '1.5']))
        ->assertSessionHasNoErrors();

    $sub = Subscription::sole();
    expect($sub)->quantity->toBe('1.50')->shift->toBe(MilkShift::Morning)->stock_source->toBe(MilkSource::OwnFarm)
        ->status->toBe(SubscriptionStatus::Active)->end_date->toBeNull()->farm_id->toBe($this->farm->id);

    $this->put(route('subscriptions.update', $sub), subscriptionPayload(['quantity' => '3', 'status' => 'stopped', 'end_date' => '2026-11-30']))
        ->assertRedirect(route('subscriptions.edit', $sub));
    expect($sub->fresh())->quantity->toBe('3.00')->status->toBe(SubscriptionStatus::Stopped)
        ->end_date->toDateString()->toBe('2026-11-30');
});

it('validates the subscription form', function (array $input, string $field) {
    $this->actingAs($this->owner)->post(route('subscriptions.store'), subscriptionPayload($input))->assertSessionHasErrors($field);
})->with([
    'zero quantity' => [['quantity' => '0'], 'quantity'],
    'bad shift' => [['shift' => 'night'], 'shift'],
    'bad source' => [['stock_source' => 'market'], 'stock_source'],
    'end before start' => [['end_date' => '2026-10-01'], 'end_date'],
    'unknown customer' => [['customer_id' => 999999], 'customer_id'],
]);

it('rejects a customer or product of another farm', function () {
    $other = Farm::factory()->create();

    $this->actingAs($this->owner)
        ->post(route('subscriptions.store'), subscriptionPayload(['customer_id' => Customer::factory()->create(['farm_id' => $other->id])->id]))
        ->assertSessionHasErrors('customer_id');
    $this->post(route('subscriptions.store'), subscriptionPayload(['product_id' => Product::factory()->create(['farm_id' => $other->id])->id]))
        ->assertSessionHasErrors('product_id');
});

it('rejects an overlapping active subscription for the same customer, product and shift', function () {
    Subscription::factory()->create([
        'customer_id' => $this->customer->id, 'product_id' => $this->product->id, 'start_date' => '2026-11-01', 'end_date' => '2026-11-30',
    ]);
    $this->actingAs($this->owner);

    $this->post(route('subscriptions.store'), subscriptionPayload(['start_date' => '2026-11-20']))->assertSessionHasErrors('start_date');
    $this->post(route('subscriptions.store'), subscriptionPayload(['start_date' => '2026-10-01', 'end_date' => '2026-11-01']))->assertSessionHasErrors('start_date');

    // Other shift, or a period after it ends, is fine.
    $this->post(route('subscriptions.store'), subscriptionPayload(['shift' => 'evening']))->assertSessionHasNoErrors();
    $this->post(route('subscriptions.store'), subscriptionPayload(['start_date' => '2026-12-01']))->assertSessionHasNoErrors();
    expect(Subscription::count())->toBe(3);
});

it('adds pauses, rejects overlapping ones, removes them, and deletes the subscription with its pauses', function () {
    $sub = Subscription::factory()->create(['customer_id' => $this->customer->id, 'product_id' => $this->product->id]);
    $this->actingAs($this->owner);

    $this->post(route('subscriptions.pauses.store', $sub), ['from_date' => '2026-11-10', 'to_date' => '2026-11-12', 'reason' => 'Out of town'])
        ->assertSessionHasNoErrors();
    $this->post(route('subscriptions.pauses.store', $sub), ['from_date' => '2026-11-12', 'to_date' => '2026-11-15'])->assertSessionHasErrors('from_date');
    $this->post(route('subscriptions.pauses.store', $sub), ['from_date' => '2026-11-20', 'to_date' => '2026-11-19'])->assertSessionHasErrors('to_date');

    $pause = SubscriptionPause::sole();
    expect($pause)->reason->toBe('Out of town')->farm_id->toBe($this->farm->id);
    $this->get(route('subscriptions.edit', $sub))->assertOk()->assertSee('Out of town');

    $otherSub = Subscription::factory()->create(['customer_id' => $this->customer->id, 'product_id' => $this->product->id, 'shift' => 'evening']);
    $this->delete(route('subscriptions.pauses.destroy', [$otherSub, $pause]))->assertNotFound();
    $this->delete(route('subscriptions.pauses.destroy', [$sub, $pause]))->assertRedirect(route('subscriptions.edit', $sub));
    expect(SubscriptionPause::count())->toBe(0);

    $sub->pauses()->create(['from_date' => '2026-12-01', 'to_date' => '2026-12-02']);
    $this->delete(route('subscriptions.destroy', $sub))->assertRedirect(route('subscriptions.index'));
    expect(Subscription::find($sub->id))->toBeNull()->and(SubscriptionPause::count())->toBe(0);
});

it('lists and filters subscriptions, showing paused today', function () {
    $paused = Subscription::factory()->create(['customer_id' => $this->customer->id, 'product_id' => $this->product->id]);
    $paused->pauses()->create(['farm_id' => $this->farm->id, 'from_date' => now()->subDay()->toDateString(), 'to_date' => now()->addDay()->toDateString()]);
    $evening = Customer::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Evening Verma']);
    Subscription::factory()->create(['customer_id' => $evening->id, 'product_id' => $this->product->id, 'shift' => 'evening']);
    Subscription::factory()->create(['customer_id' => Customer::factory()->create(['farm_id' => Farm::factory()->create()->id, 'name' => 'Foreign Guy'])->id]);

    $this->actingAs($this->owner)->get(route('subscriptions.index'))->assertOk()
        ->assertSee('Sharma Ji')->assertSee('Evening Verma')->assertSee('Paused today')->assertDontSee('Foreign Guy');
    $this->get(route('subscriptions.index', ['shift' => 'evening']))->assertSee('Evening Verma')->assertDontSee('Sharma Ji');
    $this->get(route('subscriptions.index', ['search' => 'sharma']))->assertSee('Sharma Ji')->assertDontSee('Evening Verma');
    $this->get(route('subscriptions.index', ['customer_id' => $evening->id]))->assertSee('Evening Verma')->assertDontSee('Sharma Ji');
});

it('needs subscriptions.manage; another farm\'s subscription is 404', function () {
    $accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
    $foreign = Subscription::factory()->create(['customer_id' => Customer::factory()->create(['farm_id' => Farm::factory()->create()->id])->id]);

    $this->actingAs($accountant)->get(route('subscriptions.index'))->assertForbidden();
    $this->post(route('subscriptions.store'), subscriptionPayload())->assertForbidden();
    $this->actingAs($this->owner)->get(route('subscriptions.edit', $foreign))->assertNotFound();
});

it('searches active customers of the farm for the customer box', function () {
    Customer::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Sharma Inactive', 'is_active' => false]);
    Customer::factory()->create(['farm_id' => Farm::factory()->create()->id, 'name' => 'Sharma Foreign']);

    $this->actingAs($this->owner)->getJson(route('customers.search', ['q' => 'sharma']))->assertOk()
        ->assertJsonCount(1, 'results')
        ->assertJsonPath('results.0.id', $this->customer->id)
        ->assertJsonPath('results.0.text', 'C-0001 · Sharma Ji · '.$this->customer->phone);
});
