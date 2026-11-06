<?php

use App\Enums\RoleName;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\CustomerAdjustment;
use App\Models\Delivery;
use App\Models\Farm;
use App\Models\Product;
use App\Models\User;
use App\Services\BillingService;
use App\Services\CustomerBalanceService;
use App\Support\CurrentFarm;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->farm = Farm::factory()->create();
    app(CurrentFarm::class)->set($this->farm);
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
    $this->customer = Customer::factory()->create(['farm_id' => $this->farm->id, 'opening_balance' => '500.00']);
    $this->travelTo('2026-11-05 10:00:00');
});

it('adds signed adjustments that count in the balance and on the next bill', function () {
    $this->actingAs($this->accountant);
    $this->post(route('customers.adjustments.store', $this->customer), ['adjustment_date' => '2026-10-20', 'amount' => '-200', 'reason' => 'Diwali discount'])
        ->assertSessionHasNoErrors();
    $this->post(route('customers.adjustments.store', $this->customer), ['adjustment_date' => '2026-09-10', 'amount' => '50', 'reason' => 'Bottle charge'])
        ->assertSessionHasNoErrors();

    expect(CustomerAdjustment::orderBy('id')->pluck('amount')->all())->toBe(['-200.00', '50.00'])
        ->and(app(CustomerBalanceService::class)->balanceAsOf($this->customer, '2026-10-31'))->toBe('350.00');

    $product = Product::factory()->create(['farm_id' => $this->farm->id]);
    Delivery::factory()->create(['customer_id' => $this->customer->id, 'product_id' => $product->id, 'delivery_date' => '2026-10-10',
        'status' => 'delivered', 'delivered_qty' => '60', 'rate' => '60', 'amount' => '3600.00']);
    $bill = app(BillingService::class)->finalize(app(BillingService::class)->generate($this->customer, '2026-10-01', '2026-10-31', '2026-10-31'));

    // previous = 500 + 50 (Sept) ; this bill 3,600 ; adjustments in period −200 → 3,950
    expect($bill)->previous_balance->toBe('550.00')->adjustments_in_period->toBe('-200.00')->closing_balance->toBe('3950.00');

    $this->get(route('customers.show', $this->customer))->assertOk()->assertSee('Diwali discount');
});

it('validates adjustments', function (array $input, string $field) {
    $this->actingAs($this->accountant)->post(route('customers.adjustments.store', $this->customer),
        array_merge(['adjustment_date' => '2026-11-01', 'amount' => '100', 'reason' => 'Extra'], $input))->assertSessionHasErrors($field);
})->with([
    'zero' => [['amount' => '0.00'], 'amount'],
    'no reason' => [['reason' => ' '], 'reason'],
    'future' => [['adjustment_date' => '2026-12-01'], 'adjustment_date'],
]);

it('lets only the creator remove it on the same day, with a reason', function () {
    $this->actingAs($this->accountant)->post(route('customers.adjustments.store', $this->customer),
        ['adjustment_date' => '2026-11-05', 'amount' => '100', 'reason' => 'Extra litre']);
    $adj = CustomerAdjustment::sole();

    $other = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
    $this->actingAs($other)->delete(route('customers.adjustments.destroy', [$this->customer, $adj]), ['reason' => 'Wrong'])->assertForbidden();

    $this->actingAs($this->accountant)->delete(route('customers.adjustments.destroy', [$this->customer, $adj]))->assertSessionHasErrors('reason');
    $this->delete(route('customers.adjustments.destroy', [$this->customer, $adj]), ['reason' => 'Entered twice'])->assertSessionHasNoErrors();
    expect(CustomerAdjustment::count())->toBe(0)
        ->and(Activity::where('subject_type', $adj->getMorphClass())->where('event', 'deleted')->sole()->reason())->toBe('Entered twice');
});

it('needs customer_adjustments.create', function () {
    $herd = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();

    $this->actingAs($herd)->post(route('customers.adjustments.store', $this->customer),
        ['adjustment_date' => '2026-11-01', 'amount' => '100', 'reason' => 'Extra'])->assertForbidden();
});
