<?php

use App\Enums\MilkType;
use App\Enums\RoleName;
use App\Models\Animal;
use App\Models\AnimalPurchase;
use App\Models\AnimalSale;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\FeedItem;
use App\Models\FeedTransaction;
use App\Models\MilkCollection;
use App\Models\MilkProductionRecord;
use App\Models\Party;
use App\Models\Product;
use App\Models\Section;
use App\Models\User;
use App\Services\ExpenseReportService;
use App\Services\ProfitReportService;
use App\Support\CurrentFarm;

/*
| Phase 7 · Task 7.5: expense category × month and section-wise matrices, cost per litre, profit per animal,
| collection center margin (spec 7.6 #6–#9). Each figure is checked against hand-worked source rows.
*/

beforeEach(function () {
    $this->travelTo('2026-11-17 10:00');
    $this->farm = Farm::factory()->create();
    app(CurrentFarm::class)->set($this->farm);
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->f = ['farm_id' => $this->farm->id];
    $this->herd = Section::factory()->create($this->f + ['code' => 'HERD', 'name' => 'Own Farm/Herd']);
    $this->center = Section::factory()->create($this->f + ['code' => 'COLLECTION', 'name' => 'Collection Center']);
    $this->expense = fn (Section $s, string $date, string $amount, array $extra = []) => Expense::factory()
        ->create($this->f + ['section_id' => $s->id, 'expense_date' => $date, 'amount' => $amount] + $extra);
});

it('lays expenses out as category × month and section × month with totals and share', function () {
    $feed = ExpenseCategory::factory()->create($this->f + ['name' => 'Feed']);
    $labour = ExpenseCategory::factory()->create($this->f + ['name' => 'Labour']);
    ($this->expense)($this->herd, '2026-09-05', '300.00', ['category_id' => $feed->id]);
    ($this->expense)($this->herd, '2026-10-05', '200.00', ['category_id' => $feed->id]);
    ($this->expense)($this->center, '2026-10-20', '500.00', ['category_id' => $labour->id]);
    ($this->expense)($this->center, '2026-08-31', '999.00', ['category_id' => $labour->id]); // before the period

    $service = app(ExpenseReportService::class);
    $byCategory = $service->categoryByMonth('2026-09-01', '2026-10-31');
    expect(array_column($byCategory->columns, 'label'))->toBe(['Category', 'Sep 26', 'Oct 26', 'Total', 'Share'])
        ->and($byCategory->rows)->toBe([['Feed', '300.00', '200.00', '500.00', '50.0'], ['Labour', '0.00', '500.00', '500.00', '50.0']])
        ->and($byCategory->totals)->toBe(['Total', '300.00', '700.00', '1000.00', '100.0']);

    expect($service->sectionByMonth('2026-09-01', '2026-10-31')->rows)
        ->toBe([['Collection Center', '0.00', '500.00', '500.00', '50.0'], ['Own Farm/Herd', '300.00', '200.00', '500.00', '50.0']]);

    // at most 24 month columns
    $this->actingAs($this->owner)->get(route('reports.expenses', ['report' => 'sections', 'from' => '2024-01-01', 'to' => '2026-11-17']))
        ->assertSessionHasErrors('from');
    $this->get(route('reports.expenses', ['report' => 'category-months']))->assertOk()->assertSee('Expenses by category and month');
});

it('works out cost per litre and each animal\'s estimated contribution with a shared herd overhead', function () {
    $a = Animal::factory()->create($this->f + ['tag_no' => 'A-1', 'name' => null]);
    $b = Animal::factory()->create($this->f + ['tag_no' => 'B-1', 'name' => null]);
    $milk = fn (Animal $x, string $date, string $l) => MilkProductionRecord::factory()->for($x)->create(['production_date' => $date, 'shift' => 'morning', 'quantity_litre' => $l]);
    $milk($a, '2026-11-01', '10.00');
    $milk($a, '2026-11-02', '10.00');
    $milk($a, '2026-11-03', '10.00');   // A: 3 days, 30 L
    $milk($b, '2026-11-01', '10.00');   // B: 1 day, 10 L
    // realized own-milk rate in November: 600 / 10 L = ₹60
    $product = Product::factory()->create($this->f + ['milk_type' => MilkType::Cow]);
    $customer = Customer::factory()->create($this->f);
    Delivery::factory()->create(['customer_id' => $customer->id, 'product_id' => $product->id, 'delivery_date' => '2026-11-05',
        'status' => 'delivered', 'delivered_qty' => '10', 'amount' => '600']);
    // feed: 100 kg bought for ₹2,000 (₹20/kg, a HERD expense), A ate 10 kg = ₹200
    $item = FeedItem::create($this->f + ['name' => 'Cattle feed', 'unit' => 'kg']);
    FeedTransaction::create($this->f + ['feed_item_id' => $item->id, 'transaction_date' => '2026-11-01', 'type' => 'purchase', 'quantity' => '100', 'amount' => '2000']);
    FeedTransaction::create($this->f + ['feed_item_id' => $item->id, 'transaction_date' => '2026-11-02', 'type' => 'consumption', 'quantity' => '10', 'animal_id' => $a->id]);
    ($this->expense)($this->herd, '2026-11-01', '2000.00');                          // the feed purchase
    ($this->expense)($this->herd, '2026-11-03', '200.00', ['animal_id' => $b->id]); // treatment of B
    ($this->expense)($this->herd, '2026-11-04', '200.00');                          // other herd cost
    ($this->expense)($this->center, '2026-11-04', '777.00');                        // not herd

    $service = app(ProfitReportService::class);

    // November: herd 2,400 ÷ 40 L = 60
    expect($service->costPerLitre('2026-10-01', '2026-11-17')->rows)->toBe([['Oct 26', '0.00', '0.00', null], ['Nov 26', '2400.00', '40.00', '60.00']]);

    // overhead = 2,200 (herd without animal) − 200 feed charged = 2,000 → A 3/4 = 1,500, B 1/4 = 500
    $table = $service->profitPerAnimal('2026-11-01', '2026-11-17');
    expect($table->rows)->toBe([
        ['A-1', 'Cow', 3, '30.00', '1800.00', '200.00', '0.00', '1500.00', '100.00'],
        ['B-1', 'Cow', 1, '10.00', '600.00', '0.00', '200.00', '500.00', '-100.00'],
    ])->and($table->totals)->toBe(['Total (2)', '', 4, '40.00', '2400.00', '200.00', '200.00', '2000.00', '0.00']);

    $this->actingAs($this->owner)->get(route('reports.profit', ['report' => 'animals', 'from' => '2026-11-01']))
        ->assertOk()->assertSee('Profit per animal (estimate)')->assertSee('₹1,500.00');
    $this->get(route('reports.profit', ['report' => 'cost-per-litre', 'format' => 'pdf']))->assertOk();
});

it('works out the collection center margin per month', function () {
    MilkCollection::factory()->create($this->f + ['collection_date' => '2026-11-03', 'quantity_litre' => '100', 'amount' => '4000']);
    MilkCollection::factory()->create($this->f + ['collection_date' => '2026-11-03', 'quantity_litre' => '20', 'amount' => '0', 'quality_status' => 'rejected']);
    $customer = Customer::factory()->create($this->f);
    $sell = fn (string $source, string $qty, string $amount) => Delivery::factory()->create(['customer_id' => $customer->id,
        'delivery_date' => '2026-11-04', 'status' => 'delivered', 'stock_source' => $source, 'delivered_qty' => $qty, 'amount' => $amount]);
    $sell('collection', '90', '4950');
    $sell('own_farm', '10', '600');                        // own milk is not the center's
    ($this->expense)($this->center, '2026-11-05', '300.00');
    ($this->expense)($this->herd, '2026-11-05', '999.00');  // not the center's

    expect(app(ProfitReportService::class)->collectionMargin('2026-11-01', '2026-11-17')->rows)
        ->toBe([['Nov 26', '100.00', '90.00', '4950.00', '4000.00', '300.00', '650.00']]);
});

it('shows profitability only with the money rights', function () {
    $user = User::factory()->forFarm($this->farm)->create();
    $user->givePermissionTo('reports.view', 'expenses.view');

    $this->actingAs($user)->get(route('reports.profit', ['report' => 'collection-margin']))->assertForbidden();
    $this->get(route('reports.index'))->assertOk()->assertDontSee('Collection center margin');

    $user->givePermissionTo('dashboard.financial');
    $this->get(route('reports.profit', ['report' => 'collection-margin']))->assertOk();
});

it('sums a management P&L from bills, unbilled milk, animal trades, farmer milk and expenses by category', function () {
    $customer = Customer::factory()->create($this->f);
    $bill = fn (string $status, string $total, string $date = '2026-11-01') => Bill::factory()->create(['customer_id' => $customer->id, 'status' => $status, 'total' => $total, 'bill_date' => $date]);
    $final = $bill('finalized', '1000');
    $draft = $bill('draft', '777');
    $bill('finalized', '999', '2026-10-31');                      // before the period
    $deliver = fn (string $amount, ?int $billId) => Delivery::factory()->create(['customer_id' => $customer->id, 'delivery_date' => '2026-11-03',
        'status' => 'delivered', 'delivered_qty' => '1', 'amount' => $amount, 'bill_id' => $billId]);
    $deliver('1000', $final->id);                                  // inside the finalized bill → counted once, as the bill
    $deliver('300', $draft->id);                                   // draft bill → not yet billed
    $deliver('200', null);                                         // not billed
    $party = Party::factory()->create($this->f);
    AnimalSale::create(['sale_no' => 'S-1', 'party_id' => $party->id, 'sale_date' => '2026-11-05', 'total_price' => '5000', 'selling_costs' => '500', 'net_amount' => '4500', 'status' => 'completed']);
    AnimalSale::create(['sale_no' => 'S-2', 'party_id' => $party->id, 'sale_date' => '2026-11-05', 'total_price' => '9000', 'net_amount' => '9000', 'status' => 'cancelled']);
    AnimalPurchase::create(['purchase_no' => 'P-1', 'party_id' => $party->id, 'purchase_date' => '2026-11-06', 'total_animal_price' => '2000', 'total_amount' => '2000']);
    MilkCollection::factory()->create($this->f + ['collection_date' => '2026-11-04', 'amount' => '1500']);
    MilkCollection::factory()->create($this->f + ['collection_date' => '2026-11-04', 'amount' => '0', 'quality_status' => 'rejected']);
    $feed = ExpenseCategory::factory()->create($this->f + ['name' => 'Feed']);
    ($this->expense)($this->herd, '2026-11-07', '700.00', ['category_id' => $feed->id]);

    $table = app(ProfitReportService::class)->profitAndLoss('2026-11-01', '2026-11-17');

    expect($table->rows)->toBe([
        ['Income', null, null],
        ['Milk sales — finalized bills', '1000.00', '16.7'],
        ['Milk sales — delivered, not yet billed', '500.00', '8.3'],
        ['Animal sales (net of selling costs)', '4500.00', '75.0'],
        ['Total income', '6000.00', '100.0'],
        ['Costs', null, null],
        ['Milk bought from farmers', '1500.00', '25.0'],
        ['Expenses — Feed', '700.00', '11.7'],
        ['Animal purchases', '2000.00', '33.3'],
        ['Total costs', '4200.00', '70.0'],
        ['Net profit', '1800.00', '30.0'],
    ])->and($table->strongRows)->toBe([0, 4, 5, 9, 10]);

    $this->actingAs($this->owner)->get(route('reports.profit', ['report' => 'pnl', 'from' => '2026-11-01']))
        ->assertOk()->assertSee('Management summary, not statutory accounts.')->assertSee('₹1,800.00');
    $this->get(route('reports.profit', ['report' => 'pnl', 'format' => 'excel']))->assertOk();
});
