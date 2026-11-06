<?php

use App\Enums\BillStatus;
use App\Enums\RoleName;
use App\Models\Activity;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Delivery;
use App\Models\Farm;
use App\Models\PaymentMode;
use App\Models\Product;
use App\Models\User;
use App\Notifications\BillsGeneratedNotification;
use App\Support\CurrentFarm;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->farm = Farm::factory()->create();
    app(CurrentFarm::class)->set($this->farm);
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
    $this->product = Product::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Cow Milk']);
    $this->customer = Customer::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Sharma Ji', 'opening_balance' => '500.00']);
    $this->travelTo('2026-11-05 10:00:00');
});

/** 30 October mornings × 2 L × ₹60 = ₹3,600. */
function octoberDeliveries(Customer $customer, string $status = 'delivered'): void
{
    foreach (range(1, 30) as $day) {
        Delivery::factory()->create([
            'customer_id' => $customer->id, 'product_id' => test()->product->id, 'delivery_date' => sprintf('2026-10-%02d', $day),
            'shift' => 'morning', 'status' => $status, 'delivered_qty' => '2.00', 'rate' => '60.00', 'amount' => '120.00',
        ]);
    }
}

function billPayload(array $overrides = []): array
{
    return array_merge(['scope' => 'one', 'customer_id' => test()->customer->id, 'period_from' => '2026-10-01',
        'period_to' => '2026-10-31', 'bill_date' => '2026-10-31'], $overrides);
}

it('makes a draft that locks the period\'s delivered rows, then finalizes with the spec balance example', function () {
    octoberDeliveries($this->customer);
    Delivery::factory()->create(['customer_id' => $this->customer->id, 'product_id' => $this->product->id, 'delivery_date' => '2026-10-31', 'status' => 'pending']);
    CustomerPayment::create(['farm_id' => $this->farm->id, 'customer_id' => $this->customer->id, 'payment_date' => '2026-10-15', 'amount' => '1000.00',
        'payment_mode_id' => PaymentMode::factory()->create(['farm_id' => $this->farm->id])->id, 'collected_by' => $this->accountant->id]);

    $this->actingAs($this->accountant)->post(route('bills.store'), billPayload())->assertSessionHasNoErrors();

    $bill = Bill::sole();
    expect($bill)->status->toBe(BillStatus::Draft)->bill_no->toBeNull()->total->toBe('3600.00')
        ->due_date->toDateString()->toBe('2026-11-07')
        ->and($bill->items()->count())->toBe(30)
        ->and(Delivery::where('bill_id', $bill->id)->count())->toBe(30);

    $this->get(route('bills.show', $bill))->assertOk()->assertSee('₹3,100.00')->assertSee('still pending');

    $this->post(route('bills.finalize', $bill))->assertSessionHasNoErrors();
    expect($bill->fresh())->status->toBe(BillStatus::Finalized)->bill_no->toBe('BILL/2026-27/0001')
        ->previous_balance->toBe('500.00')->payments_in_period->toBe('1000.00')->closing_balance->toBe('3100.00')
        ->finalized_by->toBe($this->accountant->id)
        ->and(Storage::disk('local')->exists($bill->fresh()->pdf_path))->toBeTrue();

    $this->get(route('bills.pdf', $bill))->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('never puts a delivery in two bills and rejects overlapping periods', function () {
    octoberDeliveries($this->customer);
    $this->actingAs($this->accountant)->post(route('bills.store'), billPayload(['period_to' => '2026-10-15', 'bill_date' => '2026-10-15']));
    expect(Bill::sole()->total)->toBe('1800.00');

    $this->post(route('bills.store'), billPayload(['period_from' => '2026-10-10']))->assertSessionHas('error');
    $this->post(route('bills.store'), billPayload(['period_from' => '2026-10-16']))->assertSessionHasNoErrors();
    expect(Bill::count())->toBe(2)->and(Bill::latest('id')->first()->total)->toBe('1800.00');

    // nothing left to bill
    $this->post(route('bills.store'), billPayload(['period_from' => '2026-11-01', 'period_to' => '2026-11-04', 'bill_date' => '2026-11-04']))->assertSessionHas('error');
});

it('locks a finalized bill; cancel (with reason) releases deliveries so the period can be billed again', function () {
    octoberDeliveries($this->customer);
    $this->actingAs($this->accountant)->post(route('bills.store'), billPayload());
    $bill = Bill::sole();
    $this->post(route('bills.finalize', $bill));

    $this->delete(route('bills.destroy', $bill))->assertSessionHas('error');
    $this->post(route('bills.finalize', $bill))->assertSessionHas('error');
    expect($bill->fresh()->status)->toBe(BillStatus::Finalized);

    $this->post(route('bills.cancel', $bill))->assertSessionHasErrors('reason');
    $this->post(route('bills.cancel', $bill), ['reason' => 'Wrong rate'])->assertSessionHasNoErrors();
    expect($bill->fresh())->status->toBe(BillStatus::Cancelled)->cancel_reason->toBe('Wrong rate')
        ->and(Delivery::whereNotNull('bill_id')->count())->toBe(0)
        ->and(Activity::where('subject_type', $bill->getMorphClass())->latest('id')->first()->reason())->toBe('Wrong rate');

    $this->post(route('bills.store'), billPayload())->assertSessionHasNoErrors();
    expect(Bill::where('status', 'draft')->sole()->items()->count())->toBe(30);
});

it('deletes a draft and releases its deliveries', function () {
    octoberDeliveries($this->customer);
    $this->actingAs($this->accountant)->post(route('bills.store'), billPayload());

    $this->delete(route('bills.destroy', Bill::sole()))->assertRedirect(route('bills.index'));
    expect(Bill::count())->toBe(0)->and(Delivery::whereNotNull('bill_id')->count())->toBe(0);
});

it('bulk-generates drafts for every billable customer on the queue and notifies', function () {
    Notification::fake();
    octoberDeliveries($this->customer);
    $other = Customer::factory()->create(['farm_id' => $this->farm->id]);
    octoberDeliveries($other);
    $nothing = Customer::factory()->create(['farm_id' => $this->farm->id]);
    octoberDeliveries($nothing, 'skipped');

    $this->actingAs($this->accountant)->post(route('bills.store'), billPayload(['scope' => 'all', 'customer_id' => '']))
        ->assertRedirect(route('bills.index', ['status' => 'draft']));

    expect(Bill::pluck('customer_id')->sort()->values()->all())->toBe([$this->customer->id, $other->id])
        ->and(Bill::where('status', 'draft')->count())->toBe(2);
    Notification::assertSentTo($this->accountant, BillsGeneratedNotification::class);
});

it('checks bill permissions and farm', function () {
    $herd = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $foreign = Bill::factory()->create(['customer_id' => Customer::factory()->create(['farm_id' => Farm::factory()->create()->id])->id]);

    $this->actingAs($herd)->get(route('bills.index'))->assertForbidden();
    $this->post(route('bills.store'), billPayload())->assertForbidden();
    $this->actingAs($this->accountant)->get(route('bills.show', $foreign))->assertNotFound();
    $this->get(route('bills.index'))->assertOk();
});
