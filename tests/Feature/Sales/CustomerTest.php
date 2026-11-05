<?php

use App\Enums\B2bCategory;
use App\Enums\CustomerType;
use App\Enums\RoleName;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\CustomerRate;
use App\Models\DeliveryRoute;
use App\Models\Farm;
use App\Models\Product;
use App\Models\User;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
    $this->route = DeliveryRoute::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Ward 4']);
    $this->product = Product::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Cow Milk', 'default_rate' => '60.00']);
});

function customerPayload(array $overrides = []): array
{
    return array_merge([
        'name' => '  Ramesh   Patil ', 'customer_type' => 'b2c', 'phone' => '+91 98765 43210', 'address' => 'Plot 12',
        'area' => 'Shivaji Nagar', 'opening_balance' => '₹ 1,250.50', 'credit_limit' => '', 'is_active' => '1',
    ], $overrides);
}

it('adds customers with auto codes C-0001, C-0002 per farm, never reusing a deleted code', function () {
    $this->actingAs($this->owner)->post(route('customers.store'), customerPayload(['route_id' => $this->route->id, 'route_sequence' => 3]))
        ->assertSessionHasNoErrors();

    $first = Customer::sole();
    expect($first)->code->toBe('C-0001')->name->toBe('Ramesh Patil')->phone->toBe('9876543210')
        ->customer_type->toBe(CustomerType::B2c)->b2b_category->toBeNull()
        ->opening_balance->toBe('1250.50')->credit_limit->toBeNull()
        ->route_id->toBe($this->route->id)->route_sequence->toBe(3)->farm_id->toBe($this->farm->id);

    $first->delete();
    $this->post(route('customers.store'), customerPayload(['name' => 'Hotel Sai', 'customer_type' => 'b2b', 'b2b_category' => 'hotel']))
        ->assertSessionHasNoErrors();

    $second = Customer::where('name', 'Hotel Sai')->sole();
    expect($second)->code->toBe('C-0002')->b2b_category->toBe(B2bCategory::Hotel);

    // Another farm starts its own series.
    Customer::factory()->create(['farm_id' => Farm::factory()->create()->id, 'code' => 'C-0099']);
    $this->post(route('customers.store'), customerPayload(['name' => 'Third']))->assertSessionHasNoErrors();
    expect(Customer::where('name', 'Third')->value('code'))->toBe('C-0003');
});

it('validates the customer form', function (array $input, string $field) {
    $this->actingAs($this->owner)->post(route('customers.store'), customerPayload($input))->assertSessionHasErrors($field);
    expect(Customer::count())->toBe(0);
})->with([
    'name missing' => [['name' => ' '], 'name'],
    'bad type' => [['customer_type' => 'retail'], 'customer_type'],
    'b2b without category' => [['customer_type' => 'b2b', 'b2b_category' => ''], 'b2b_category'],
    'short phone' => [['phone' => '98765'], 'phone'],
    'bad gstin' => [['gstin' => '27ABC'], 'gstin'],
    'route of another farm' => [['route_id' => 999999], 'route_id'],
    'negative credit limit' => [['credit_limit' => '-5'], 'credit_limit'],
]);

it('rejects a route of another farm', function () {
    $foreign = DeliveryRoute::factory()->create(['farm_id' => Farm::factory()->create()->id]);

    $this->actingAs($this->owner)->post(route('customers.store'), customerPayload(['route_id' => $foreign->id]))
        ->assertSessionHasErrors('route_id');
});

it('edits a customer with a reason in the audit log and clears the category for B2C', function () {
    $customer = Customer::factory()->create(['farm_id' => $this->farm->id, 'customer_type' => 'b2b', 'b2b_category' => 'shop']);

    $this->actingAs($this->owner)->put(route('customers.update', $customer), customerPayload(['b2b_category' => 'shop']))
        ->assertSessionHasErrors('reason');

    $this->put(route('customers.update', $customer), customerPayload(['b2b_category' => 'shop', 'reason' => 'Now a home customer']))
        ->assertRedirect(route('customers.show', $customer));

    expect($customer->fresh())->customer_type->toBe(CustomerType::B2c)->b2b_category->toBeNull();
    $log = Activity::where('subject_type', $customer->getMorphClass())->where('event', 'updated')->sole();
    expect($log->reason())->toBe('Now a home customer');
});

it('deletes a customer softly with a reason', function () {
    $customer = Customer::factory()->create(['farm_id' => $this->farm->id]);

    $this->actingAs($this->owner)->delete(route('customers.destroy', $customer))->assertSessionHasErrors('reason');
    $this->delete(route('customers.destroy', $customer), ['reason' => 'Moved away'])->assertRedirect(route('customers.index'));

    expect($customer->fresh()->trashed())->toBeTrue();
});

it('lists and filters customers of the current farm only', function () {
    Customer::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Asha Route', 'route_id' => $this->route->id]);
    Customer::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Bala Hotel', 'customer_type' => 'b2b', 'b2b_category' => 'hotel']);
    Customer::factory()->create(['farm_id' => Farm::factory()->create()->id, 'name' => 'Foreign Person']);

    $this->actingAs($this->accountant)->get(route('customers.index'))->assertOk()
        ->assertSee('Asha Route')->assertSee('Bala Hotel')->assertDontSee('Foreign Person')
        ->assertDontSee(route('customers.create'), false);

    $this->get(route('customers.index', ['type' => 'b2b']))->assertSee('Bala Hotel')->assertDontSee('Asha Route');
    $this->get(route('customers.index', ['route_id' => $this->route->id]))->assertSee('Asha Route')->assertDontSee('Bala Hotel');
    $this->get(route('customers.index', ['search' => 'bala']))->assertSee('Bala Hotel')->assertDontSee('Asha Route');
});

it('lets only customers.manage change customers and rates; another farm\'s customer is 404', function () {
    $customer = Customer::factory()->create(['farm_id' => $this->farm->id]);
    $foreign = Customer::factory()->create(['farm_id' => Farm::factory()->create()->id]);

    $this->actingAs($this->accountant)->get(route('customers.show', $customer))->assertOk();
    $this->get(route('customers.create'))->assertForbidden();
    $this->post(route('customers.store'), customerPayload())->assertForbidden();
    $this->post(route('customers.rates.store', $customer), ['product_id' => $this->product->id, 'rate' => '65', 'effective_from' => '2026-11-01'])
        ->assertForbidden();

    $deliveryBoy = User::factory()->forFarm($this->farm)->withRole(RoleName::DeliveryBoy)->create();
    $this->actingAs($deliveryBoy)->get(route('customers.index'))->assertForbidden();

    $this->actingAs($this->owner)->get(route('customers.show', $foreign))->assertNotFound();
});

it('adds own rates per product and date, rejects a duplicate date, removes with a reason', function () {
    $customer = Customer::factory()->create(['farm_id' => $this->farm->id]);
    $this->actingAs($this->owner);

    $this->get(route('customers.show', $customer))->assertSee('₹60.00')->assertSee('default');

    $this->post(route('customers.rates.store', $customer), ['product_id' => $this->product->id, 'rate' => '62.5', 'effective_from' => now()->toDateString()])
        ->assertRedirect(route('customers.show', $customer))->assertSessionHasNoErrors();
    $this->post(route('customers.rates.store', $customer), ['product_id' => $this->product->id, 'rate' => '63', 'effective_from' => now()->toDateString()])
        ->assertSessionHasErrors('effective_from');

    $rate = CustomerRate::sole();
    expect($rate)->rate->toBe('62.50')->farm_id->toBe($this->farm->id);
    $this->get(route('customers.show', $customer))->assertSee('₹62.50');

    $other = Customer::factory()->create(['farm_id' => $this->farm->id]);
    $this->delete(route('customers.rates.destroy', [$other, $rate]), ['reason' => 'Wrong'])->assertNotFound();

    $this->delete(route('customers.rates.destroy', [$customer, $rate]), ['reason' => 'Entered by mistake'])
        ->assertRedirect(route('customers.show', $customer));
    expect(CustomerRate::count())->toBe(0)
        ->and(Activity::where('subject_type', $rate->getMorphClass())->where('event', 'deleted')->sole()->reason())->toBe('Entered by mistake');
});

it('rejects a rate for a product of another farm', function () {
    $customer = Customer::factory()->create(['farm_id' => $this->farm->id]);
    $foreign = Product::factory()->create(['farm_id' => Farm::factory()->create()->id]);

    $this->actingAs($this->owner)
        ->post(route('customers.rates.store', $customer), ['product_id' => $foreign->id, 'rate' => '65', 'effective_from' => '2026-11-01'])
        ->assertSessionHasErrors('product_id');
});
