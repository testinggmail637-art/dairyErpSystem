<?php

use App\Enums\RoleName;
use App\Models\AnimalPayment;
use App\Models\AnimalPurchase;
use App\Models\AnimalSale;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\CustomerAdjustment;
use App\Models\CustomerPayment;
use App\Models\Farm;
use App\Models\Farmer;
use App\Models\FarmerAdvance;
use App\Models\FarmerPayment;
use App\Models\FarmerPaymentCycle;
use App\Models\Party;
use App\Models\PaymentMode;
use App\Models\User;
use App\Services\BalanceReportService;
use App\Services\FarmerPaymentService;
use App\Services\PartyLedgerService;
use App\Support\CurrentFarm;

/*
| Phase 7 · Task 7.4: receivables aging, farmer payables and party balances as of a date (spec 7.6 #5).
| Sales reports (Phase 3) and vendor payables (Phase 1) already exist.
*/

beforeEach(function () {
    $this->travelTo('2026-11-17 10:00');
    $this->farm = Farm::factory()->create();
    app(CurrentFarm::class)->set($this->farm);
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->mode = PaymentMode::factory()->create(['farm_id' => $this->farm->id]);
    $this->service = app(BalanceReportService::class);
});

it('ages each customer balance FIFO by bill date, with the opening balance in 90+ and advances shown negative', function () {
    $f = ['farm_id' => $this->farm->id];
    $a = Customer::factory()->create($f + ['code' => 'C-1', 'name' => 'Asha', 'opening_balance' => '500']);
    $bill = fn (string $date, string $total, string $status = 'finalized') => Bill::factory()->create(['customer_id' => $a->id, 'bill_date' => $date, 'status' => $status, 'total' => $total]);
    $bill('2026-07-01', '1000');                // 139 days
    $bill('2026-09-30', '800');                 // 48 days
    $bill('2026-11-10', '600');                 // 7 days
    $bill('2026-11-12', '999', 'draft');        // not counted
    $bill('2026-11-20', '999');                 // after the date
    CustomerAdjustment::create(['customer_id' => $a->id, 'adjustment_date' => '2026-10-01', 'amount' => '100', 'reason' => 'Extra']);
    CustomerAdjustment::create(['customer_id' => $a->id, 'adjustment_date' => '2026-10-02', 'amount' => '-50', 'reason' => 'Discount']);
    $pay = fn (Customer $c, string $date, string $amount) => CustomerPayment::create(['customer_id' => $c->id, 'payment_date' => $date,
        'amount' => $amount, 'payment_mode_id' => $this->mode->id, 'collected_by' => $this->owner->id]);
    $pay($a, '2026-11-01', '500');
    $pay($a, '2026-11-20', '999');              // after the date
    Customer::factory()->create($f + ['code' => 'C-2', 'name' => 'Bhanu', 'opening_balance' => '300']);
    $c = Customer::factory()->create($f + ['code' => 'C-3', 'name' => 'Chetan', 'opening_balance' => '0']);
    $pay($c, '2026-11-02', '200');
    Customer::factory()->create($f + ['code' => 'C-4', 'opening_balance' => '0']); // no balance → not listed

    $table = $this->service->receivablesAging('2026-11-17');

    // A: 500 + 2,400 + 100 − 50 − 500 = 2,450 → newest first: 600 (0–30), 100 + 800 (31–60), 950 of the July bill (90+)
    expect($table->rows)->toBe([
        ['C-1 · Asha', '2450.00', '600.00', '900.00', '0.00', '950.00'],
        ['C-2 · Bhanu', '300.00', '0.00', '0.00', '0.00', '300.00'],
        ['C-3 · Chetan', '-200.00', '0.00', '0.00', '0.00', '0.00'],
    ])->and($table->totals)->toBe(['Total (3)', '2550.00', '600.00', '900.00', '0.00', '1250.00']);

    $this->actingAs($this->owner)->get(route('reports.balances', ['report' => 'receivables']))
        ->assertOk()->assertSee('Receivables aging')->assertSee('₹2,450.00');
    $this->get(route('reports.balances', ['report' => 'receivables', 'format' => 'excel']))->assertOk();
});

it('lists what we owe each farmer as of a date, matching the farmer balance', function () {
    $farmer = Farmer::factory()->create(['farm_id' => $this->farm->id, 'code' => 'F-1', 'name' => 'Ramesh', 'opening_balance' => '500']);
    $final = FarmerPaymentCycle::create(['cycle_no' => 'FP-1', 'from_date' => '2026-11-01', 'to_date' => '2026-11-10', 'status' => 'finalized']);
    $draft = FarmerPaymentCycle::create(['cycle_no' => 'FP-2', 'from_date' => '2026-11-11', 'to_date' => '2026-11-16', 'status' => 'draft']);
    $stmt = fn ($cycle, $gross, $status, $net, $paidOn = null) => FarmerPayment::create(['farmer_payment_cycle_id' => $cycle->id, 'farmer_id' => $farmer->id,
        'total_qty' => '50', 'gross_amount' => $gross, 'bonus' => '100', 'other_deduction' => '50', 'net_payable' => $net, 'status' => $status, 'paid_on' => $paidOn]);
    $stmt($final, '2000', 'paid', '1200', '2026-11-12');
    $stmt($draft, '9999', 'pending', '9999');
    FarmerAdvance::create(['farmer_id' => $farmer->id, 'advance_date' => '2026-11-02', 'amount' => '300', 'payment_mode_id' => $this->mode->id]);
    Farmer::factory()->create(['farm_id' => $this->farm->id, 'opening_balance' => '0']); // nothing → not listed

    expect($this->service->farmerPayables('2026-11-17')->rows)
        ->toBe([['F-1 · Ramesh', '500.00', '2050.00', '300.00', '1200.00', '1050.00']])
        ->and(app(FarmerPaymentService::class)->balance($farmer))->toBe('1050.00')
        // before the payment day the statement is still unpaid
        ->and($this->service->farmerPayables('2026-11-11')->rows[0][5])->toBe('2250.00');
});

it('splits party balances into they owe us / we owe them, matching the party ledger', function () {
    $f = ['farm_id' => $this->farm->id];
    $buyer = Party::factory()->create($f + ['name' => 'Buyer', 'opening_balance' => '5000']);
    $seller = Party::factory()->create($f + ['name' => 'Seller', 'opening_balance' => '0']);
    $sale = fn (string $no, string $status) => AnimalSale::create(['sale_no' => $no, 'party_id' => $buyer->id, 'sale_date' => '2026-11-10',
        'total_price' => '10000', 'selling_costs' => '500', 'net_amount' => '9500', 'status' => $status]);
    $sale('S-1', 'completed');
    $sale('S-2', 'cancelled');
    AnimalPurchase::create(['purchase_no' => 'P-1', 'party_id' => $seller->id, 'purchase_date' => '2026-11-08', 'total_animal_price' => '20000', 'total_amount' => '20000']);
    $pay = fn (Party $p, string $direction, string $amount) => AnimalPayment::create(['party_id' => $p->id, 'direction' => $direction,
        'payment_date' => '2026-11-12', 'amount' => $amount, 'payment_mode_id' => $this->mode->id]);
    $pay($buyer, 'received', '3000');
    $pay($seller, 'paid', '15000');

    $table = $this->service->partyBalances('2026-11-17');

    expect($table->rows)->toBe([
        ['Buyer', '5000.00', '9500.00', '0.00', '3000.00', '0.00', '11500.00', '0.00'],
        ['Seller', '0.00', '0.00', '20000.00', '0.00', '15000.00', '0.00', '5000.00'],
    ])->and(app(PartyLedgerService::class)->balances(Party::query()->orderBy('id')->get()))
        ->toBe([$buyer->id => '11500.00', $seller->id => '-5000.00']);
});

it('needs the module permission for each report and shows only those on the reports page', function () {
    $user = User::factory()->forFarm($this->farm)->create();
    $user->givePermissionTo('reports.view', 'customers.view');

    $this->actingAs($user)->get(route('reports.balances', ['report' => 'receivables']))->assertOk();
    $this->get(route('reports.balances', ['report' => 'farmers']))->assertForbidden();
    $this->get(route('reports.balances', ['report' => 'parties']))->assertForbidden();
    $this->get(route('reports.index'))->assertOk()->assertSee('Receivables aging')->assertDontSee('Farmer payables');
});
