<?php

use App\Enums\RoleName;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\CustomerAdjustment;
use App\Models\CustomerPayment;
use App\Models\Delivery;
use App\Models\Farm;
use App\Models\PaymentMode;
use App\Models\User;
use App\Services\CustomerBalanceService;
use App\Services\CustomerLedgerService;
use App\Support\CurrentFarm;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    app(CurrentFarm::class)->set($this->farm);
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
    $this->customer = Customer::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Sharma Ji', 'opening_balance' => '500.00']);
    $this->mode = PaymentMode::factory()->create(['farm_id' => $this->farm->id, 'name' => 'UPI']);
    $this->travelTo('2026-11-05 10:00:00');
});

function ledgerBill(string $total, string $date, string $status = 'finalized'): Bill
{
    return Bill::factory()->create(['customer_id' => test()->customer->id, 'status' => $status, 'total' => $total, 'subtotal' => $total,
        'bill_date' => $date, 'period_from' => substr($date, 0, 8).'01', 'period_to' => $date, 'bill_no' => $status === 'finalized' ? 'BILL/'.$date : null]);
}

function ledgerPayment(string $amount, string $date): void
{
    CustomerPayment::create(['farm_id' => test()->farm->id, 'customer_id' => test()->customer->id, 'payment_date' => $date, 'amount' => $amount,
        'payment_mode_id' => test()->mode->id, 'reference_no' => 'UTR9', 'collected_by' => test()->accountant->id]);
}

it('lists bills, adjustments and payments in date order with a running balance that matches the customer balance', function () {
    ledgerBill('3000.00', '2026-09-30');
    ledgerPayment('1000.00', '2026-10-05');
    CustomerAdjustment::create(['farm_id' => $this->farm->id, 'customer_id' => $this->customer->id, 'adjustment_date' => '2026-10-20', 'amount' => '-200.00', 'reason' => 'Diwali discount']);
    ledgerBill('3600.00', '2026-10-31');
    ledgerBill('999.00', '2026-10-15', 'cancelled'); // never in the ledger
    Delivery::factory()->create(['customer_id' => $this->customer->id, 'delivery_date' => '2026-11-02', 'status' => 'delivered', 'amount' => '120.00']);

    $ledger = app(CustomerLedgerService::class)->ledger($this->customer, null, null);

    expect($ledger['rows']->getCollection()->map(fn ($r) => [$r->type, $r->balance])->all())->toBe([
        ['bill', '3500.00'], ['payment', '2500.00'], ['adjustment', '2300.00'], ['bill', '5900.00'],
    ])->and($ledger['closing'])->toBe('5900.00')
        ->and($ledger['unbilled'])->toBe('120.00')
        ->and(app(CustomerBalanceService::class)->balanceAsOf($this->customer, '2026-11-05'))->toBe('5900.00');

    $this->actingAs($this->accountant)->get(route('customers.ledger', [$this->customer, 'all' => 1]))->assertOk()
        ->assertSeeInOrder(['Opening balance', 'BILL/2026-09-30', 'UPI · UTR9', 'Diwali discount', 'BILL/2026-10-31'])
        ->assertSee('₹5,900.00')->assertSee('₹120.00')->assertDontSee('999');
});

it('starts a period from the balance before it', function () {
    ledgerBill('3000.00', '2026-09-30');
    ledgerPayment('1000.00', '2026-10-05');

    $ledger = app(CustomerLedgerService::class)->ledger($this->customer, '2026-10-01', '2026-10-31');

    expect($ledger['opening'])->toBe('3500.00')->and($ledger['closing'])->toBe('2500.00')
        ->and($ledger['rows']->total())->toBe(1);

    // default view: this financial year
    $this->actingAs($this->accountant)->get(route('customers.ledger', $this->customer))->assertOk()->assertSee('₹2,500.00');
});

it('exports the ledger to Excel and PDF', function () {
    ledgerBill('3000.00', '2026-09-30');
    $this->actingAs($this->accountant);

    $this->get(route('customers.ledger', [$this->customer, 'all' => 1, 'format' => 'excel']))->assertOk()->assertDownload();
    $this->get(route('customers.ledger', [$this->customer, 'all' => 1, 'format' => 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');

    $table = app(CustomerLedgerService::class)->table($this->customer, null, null);
    expect($table->rows[0][1])->toBe('Opening balance')->and($table->totals[5])->toBe('3500.00');
});

it('needs customers.view and keeps queries flat', function () {
    $herd = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $this->actingAs($herd)->get(route('customers.ledger', $this->customer))->assertForbidden();

    $count = function () {
        $this->actingAs($this->accountant)->get(route('customers.ledger', [$this->customer, 'all' => 1]))->assertOk();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('customers.ledger', [$this->customer, 'all' => 1]))->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };
    ledgerBill('100.00', '2026-04-30');
    ledgerPayment('50.00', '2026-05-01');
    $few = $count();
    foreach (range(1, 20) as $i) {
        ledgerPayment('10.00', '2026-06-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT));
    }
    expect($count())->toBe($few);
});
