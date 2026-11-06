<?php

use App\Enums\BillPaidStatus;
use App\Enums\RoleName;
use App\Models\Activity;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\Farm;
use App\Models\PaymentAllocation;
use App\Models\PaymentMode;
use App\Models\Product;
use App\Models\User;
use App\Services\BillingService;
use App\Support\CurrentFarm;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->farm = Farm::factory()->create();
    app(CurrentFarm::class)->set($this->farm);
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
    $this->customer = Customer::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Sharma Ji']);
    $this->mode = PaymentMode::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Cash']);
});

function finalBill(string $total, string $billDate): Bill
{
    return Bill::factory()->create(['customer_id' => test()->customer->id, 'status' => 'finalized', 'total' => $total,
        'subtotal' => $total, 'bill_date' => $billDate, 'bill_no' => 'BILL/'.$billDate]);
}

function paymentPayload(array $overrides = []): array
{
    return array_merge(['customer_id' => test()->customer->id, 'payment_date' => '2026-11-05', 'amount' => '5000',
        'payment_mode_id' => test()->mode->id, 'reference_no' => '', 'note' => ''], $overrides);
}

function allocationsOf(): array
{
    return PaymentAllocation::query()->orderBy('id')->get()->map(fn ($a) => [$a->bill_id, $a->amount])->all();
}

it('allocates ₹5,000 FIFO: ₹3,000 to the older bill and ₹2,000 to the newer one (spec acceptance)', function () {
    $older = finalBill('3000.00', '2026-09-30');
    $newer = finalBill('4000.00', '2026-10-31');

    $this->actingAs($this->accountant)->post(route('customer-payments.store'), paymentPayload())->assertSessionHasNoErrors();

    expect(allocationsOf())->toBe([[$older->id, '3000.00'], [$newer->id, '2000.00']])
        ->and(Bill::withPaid()->find($older->id)->paidStatus())->toBe(BillPaidStatus::Paid)
        ->and(Bill::withPaid()->find($newer->id)->paidStatus())->toBe(BillPaidStatus::PartiallyPaid);

    $this->get(route('bills.index'))->assertSee('Part paid');
});

it('keeps the extra as advance and uses it when the next bill is finalized', function () {
    $this->actingAs($this->accountant)->post(route('customer-payments.store'), paymentPayload(['amount' => '1000']));
    expect(PaymentAllocation::count())->toBe(0);

    $product = Product::factory()->create(['farm_id' => $this->farm->id]);
    Delivery::factory()->create(['customer_id' => $this->customer->id, 'product_id' => $product->id, 'delivery_date' => '2026-10-10',
        'status' => 'delivered', 'delivered_qty' => '10', 'rate' => '60', 'amount' => '600.00']);
    $draft = app(BillingService::class)->generate($this->customer, '2026-10-01', '2026-10-31', '2026-10-31');
    app(BillingService::class)->finalize($draft);

    expect(allocationsOf())->toBe([[$draft->id, '600.00']]);
});

it('reallocates when a payment is edited or deleted (with a reason), and when a bill is cancelled', function () {
    $older = finalBill('3000.00', '2026-09-30');
    $newer = finalBill('4000.00', '2026-10-31');
    $this->actingAs($this->accountant)->post(route('customer-payments.store'), paymentPayload());
    $payment = CustomerPayment::sole();

    $this->put(route('customer-payments.update', $payment), paymentPayload(['customer_id' => null, 'amount' => '2000']))->assertSessionHasErrors('reason');
    $this->put(route('customer-payments.update', $payment), paymentPayload(['customer_id' => null, 'amount' => '2000', 'reason' => 'Typo']))
        ->assertSessionHasNoErrors();
    expect(allocationsOf())->toBe([[$older->id, '2000.00']])
        ->and(Activity::where('subject_type', $payment->getMorphClass())->where('event', 'updated')->sole()->reason())->toBe('Typo');

    app(BillingService::class)->cancel($older, 'Wrong');
    expect(allocationsOf())->toBe([[$newer->id, '2000.00']]);

    $this->delete(route('customer-payments.destroy', $payment), ['reason' => 'Bounced cheque'])->assertSessionHasNoErrors();
    expect(CustomerPayment::count())->toBe(0)->and(PaymentAllocation::count())->toBe(0);
});

it('lets a delivery person see and change only his own payments of the day, for customers on his route', function () {
    $boy = User::factory()->forFarm($this->farm)->withRole(RoleName::DeliveryBoy)->create();
    $route = DeliveryRoute::factory()->create(['farm_id' => $this->farm->id, 'delivery_user_id' => $boy->id]);
    $mine = Customer::factory()->create(['farm_id' => $this->farm->id, 'route_id' => $route->id, 'name' => 'On My Route']);
    $this->actingAs($this->accountant)->post(route('customer-payments.store'), paymentPayload(['amount' => '700']));
    $other = CustomerPayment::sole();

    $this->actingAs($boy)->post(route('customer-payments.store'), paymentPayload())->assertSessionHasErrors('customer_id');
    $this->post(route('customer-payments.store'), paymentPayload(['customer_id' => $mine->id, 'amount' => '300', 'payment_date' => today()->toDateString()]))
        ->assertSessionHasNoErrors();
    $own = CustomerPayment::where('customer_id', $mine->id)->sole();

    $this->get(route('customer-payments.index'))->assertOk()->assertSee('On My Route')->assertDontSee('Sharma Ji');
    $this->get(route('customer-payments.create'))->assertForbidden(); // no customers.view: collects from the sheet
    $this->get(route('customer-payments.edit', $own))->assertOk();
    $this->get(route('customer-payments.edit', $other))->assertForbidden();

    $this->travel(1)->days();
    $this->get(route('customer-payments.edit', $own))->assertForbidden();
});

it('validates payments and keeps the list query count flat', function () {
    $this->actingAs($this->accountant);
    $this->post(route('customer-payments.store'), paymentPayload(['amount' => '0']))->assertSessionHasErrors('amount');
    $this->post(route('customer-payments.store'), paymentPayload(['payment_date' => '2099-01-01']))->assertSessionHasErrors('payment_date');
    $this->post(route('customer-payments.store'), paymentPayload(['customer_id' => Customer::factory()->create(['farm_id' => Farm::factory()->create()->id])->id]))
        ->assertSessionHasErrors('customer_id');

    $count = function () {
        $this->get(route('customer-payments.index'))->assertOk();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('customer-payments.index'))->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };
    $make = fn (int $n) => collect(range(1, $n))->each(fn () => CustomerPayment::create(['farm_id' => $this->farm->id,
        'customer_id' => Customer::factory()->create(['farm_id' => $this->farm->id])->id, 'payment_date' => '2026-11-01',
        'amount' => '100.00', 'payment_mode_id' => $this->mode->id, 'collected_by' => $this->accountant->id]));
    $make(3);
    $few = $count();
    $make(30);
    expect($count())->toBe($few);
});
