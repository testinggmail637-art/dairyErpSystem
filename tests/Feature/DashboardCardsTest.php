<?php

use App\Enums\RoleName;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\CustomerAdjustment;
use App\Models\CustomerPayment;
use App\Models\Delivery;
use App\Models\Farm;
use App\Models\Farmer;
use App\Models\FarmerAdvance;
use App\Models\FarmerPayment;
use App\Models\FarmerPaymentCycle;
use App\Models\FeedItem;
use App\Models\MilkCollection;
use App\Models\Party;
use App\Models\PaymentMode;
use App\Models\User;
use App\Services\DashboardService;
use App\Support\CurrentFarm;

/*
| Phase 7 · Task 7.1: the remaining dashboard cards (spec 7.5) — collection, deliveries, sales, receivables, unbilled,
| farmer payable, party balances, reminders. Each total is checked against its source rows.
*/

beforeEach(function () {
    $this->travelTo('2026-11-17 10:00');
    $this->farm = Farm::factory()->create();
    app(CurrentFarm::class)->set($this->farm);
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->mode = PaymentMode::factory()->create(['farm_id' => $this->farm->id]);
});

it('totals collection, deliveries, sales, receivables, unbilled, farmer payable and party balances from the source tables', function () {
    $f = ['farm_id' => $this->farm->id];
    // collection today: accepted counts, rejected and yesterday do not
    MilkCollection::factory()->create($f + ['quantity_litre' => '10', 'amount' => '400']);
    MilkCollection::factory()->create($f + ['quantity_litre' => '5', 'amount' => '0', 'quality_status' => 'rejected']);
    MilkCollection::factory()->create($f + ['collection_date' => '2026-11-16', 'quantity_litre' => '8', 'amount' => '320']);

    // deliveries: today 4 L ₹240 (unbilled), earlier this month ₹600 (billed), last month ₹100 (unbilled), pending ignored
    $customer = Customer::factory()->create($f + ['opening_balance' => '1000']);
    $bill = Bill::factory()->create(['customer_id' => $customer->id, 'status' => 'finalized', 'total' => '600']);
    $d = fn (array $a) => Delivery::factory()->create($a + ['customer_id' => $customer->id, 'status' => 'delivered']);
    $d(['delivery_date' => '2026-11-17', 'delivered_qty' => '4', 'amount' => '240']);
    $d(['delivery_date' => '2026-11-05', 'delivered_qty' => '10', 'amount' => '600', 'bill_id' => $bill->id]);
    $d(['delivery_date' => '2026-10-30', 'delivered_qty' => '2', 'amount' => '100']);
    $d(['delivery_date' => '2026-11-17', 'delivered_qty' => '0', 'amount' => '0', 'status' => 'pending']);
    Bill::factory()->create(['customer_id' => $customer->id, 'status' => 'draft', 'total' => '999']); // draft not counted
    CustomerAdjustment::create(['customer_id' => $customer->id, 'adjustment_date' => '2026-11-10', 'amount' => '-50', 'reason' => 'Discount']);
    CustomerPayment::create(['customer_id' => $customer->id, 'payment_date' => '2026-11-12', 'amount' => '300', 'payment_mode_id' => $this->mode->id, 'collected_by' => $this->owner->id]);

    // farmers: opening 500 + finalized (2,000 + 100 bonus − 50 other) − advances 300 − paid net 1,200; draft cycle ignored
    $farmer = Farmer::factory()->create($f + ['opening_balance' => '500']);
    $final = FarmerPaymentCycle::create(['cycle_no' => 'FP-1', 'from_date' => '2026-11-01', 'to_date' => '2026-11-10', 'status' => 'finalized']);
    $draft = FarmerPaymentCycle::create(['cycle_no' => 'FP-2', 'from_date' => '2026-11-11', 'to_date' => '2026-11-16', 'status' => 'draft']);
    $pay = fn ($cycle, $gross, $status, $net) => FarmerPayment::create(['farmer_payment_cycle_id' => $cycle->id, 'farmer_id' => $farmer->id,
        'total_qty' => '50', 'gross_amount' => $gross, 'bonus' => '100', 'other_deduction' => '50', 'net_payable' => $net, 'status' => $status]);
    $pay($final, '2000', 'paid', '1200');
    $pay($draft, '9999', 'pending', '9999');
    FarmerAdvance::create(['farmer_id' => $farmer->id, 'advance_date' => '2026-11-02', 'amount' => '300', 'payment_mode_id' => $this->mode->id]);

    Party::factory()->create($f + ['opening_balance' => '5000']);
    Party::factory()->create($f + ['opening_balance' => '-2000']);
    Party::factory()->create(['opening_balance' => '777']); // other farm

    $data = app(DashboardService::class)->data();
    expect($data['collection'])->toBe(['litres' => '10.00', 'amount' => '400.00'])
        ->and($data['deliveries'])->toBe(['today_litres' => '4.00', 'today_amount' => '240.00', 'month_sales' => '840.00', 'unbilled' => '340.00'])
        ->and($data['receivables'])->toBe('1250.00')      // 1,000 + 600 − 50 − 300
        ->and($data['farmer_payable'])->toBe('1050.00')   // 500 + 2,050 − 300 − 1,200
        ->and($data['parties'])->toBe(['receivable' => '5000.00', 'payable' => '2000.00']);

    $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()
        ->assertSee('Collection today')->assertSee('Delivered today')->assertSee('Sales this month')
        ->assertSee('₹1,250.00')->assertSee('₹1,050.00')->assertSee('Party balances');
});

it('hides money cards without dashboard.financial and each card without its module permission', function () {
    $operator = User::factory()->forFarm($this->farm)->withRole(RoleName::CollectionOperator)->create();
    $this->actingAs($operator)->get(route('dashboard'))->assertOk()
        ->assertSee('Collection today')->assertDontSee('Customer receivables')->assertDontSee('Farmer payable')
        ->assertDontSee('Party balances')->assertDontSee('Sales this month');
});

it('lists the reminders the user may see and clears the cache when a collection is saved', function () {
    $feed = FeedItem::create(['name' => 'Cattle feed', 'unit' => 'kg', 'reorder_level' => '100', 'is_active' => true]);
    $this->actingAs($this->owner)->get(route('dashboard'))->assertSee('Needs attention')->assertSee('Cattle feed');

    expect(app(DashboardService::class)->data()['collection']['litres'])->toBe('0.00');
    MilkCollection::factory()->create(['farm_id' => $this->farm->id, 'quantity_litre' => '7']);
    expect(app(DashboardService::class)->data()['collection']['litres'])->toBe('7.00');

    $operator = User::factory()->forFarm($this->farm)->withRole(RoleName::CollectionOperator)->create(); // no feed.view
    $this->actingAs($operator)->get(route('dashboard'))->assertDontSee('Cattle feed');
});
