<?php

use App\Enums\RoleName;
use App\Models\Animal;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\FeedItem;
use App\Models\FeedTransaction;
use App\Models\PaymentMode;
use App\Models\User;
use App\Models\Vendor;
use App\Services\FeedService;
use App\Support\CurrentFarm;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->farm = Farm::where('code', 'MDF')->firstOrFail();
    app(CurrentFarm::class)->set($this->farm);
    $this->travelTo('2026-11-13 10:00');
    $this->staff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $this->actingAs($this->staff);
    $this->post(route('feed.items.store'), ['name' => 'Cattle feed', 'unit' => 'kg', 'reorder_level' => '450'])->assertSessionHasNoErrors();
    $this->item = FeedItem::sole();
    $this->cash = PaymentMode::where('code', 'CASH')->value('id');
    $this->feed = app(FeedService::class);
});

function feedEntry(string $type, array $data = []): TestResponse
{
    return test()->post(route('feed.transactions.store'), array_merge([
        'type' => $type, 'feed_item_id' => test()->item->id, 'transaction_date' => '2026-11-12', 'quantity' => '10',
    ], $data));
}

it('opening 500 kg, consume 80 → 420; purchase makes one expense, consumption none (spec acceptance)', function () {
    feedEntry('opening', ['quantity' => '500'])->assertSessionHasNoErrors();
    feedEntry('consumption', ['quantity' => '80'])->assertSessionHasNoErrors();
    expect($this->feed->stock()[$this->item->id])->toBe('420.00')->and(Expense::count())->toBe(0);

    $vendor = Vendor::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Sharma Feeds']);
    $cattleFeed = ExpenseCategory::where('name', 'Cattle Feed')->value('id');
    feedEntry('purchase', ['quantity' => '200', 'amount' => '5600', 'vendor_id' => $vendor->id, 'is_credit' => '1', 'expense_subcategory_id' => $cattleFeed])
        ->assertSessionHasNoErrors()->assertSessionHas('success', 'Purchase saved and ₹5,600.00 added to expenses (Feed).');

    $purchase = FeedTransaction::where('type', 'purchase')->sole();
    $expense = Expense::sole();
    expect($purchase)->rate->toBe('28.00')->expense_id->toBe($expense->id)
        ->and($expense)->amount->toBe('5600.00')->vendor_id->toBe($vendor->id)->is_credit->toBeTrue()->payment_mode_id->toBeNull()
        ->quantity->toBe('200.00')->subcategory_id->toBe($cattleFeed)
        ->and($expense->category->name)->toBe('Feed')->and($expense->section->code)->toBe('HERD')
        ->and($expense->isAutoCreated())->toBeTrue()
        ->and($this->feed->stock()[$this->item->id])->toBe('620.00');

    feedEntry('consumption', ['quantity' => '20', 'animal_id' => Animal::factory()->create(['farm_id' => $this->farm->id])->id])->assertSessionHasNoErrors();
    expect(Expense::count())->toBe(1); // consumption never makes an expense
});

it('blocks consumption beyond stock and changes that would leave stock below zero', function () {
    feedEntry('opening', ['quantity' => '100'])->assertSessionHasNoErrors();
    feedEntry('consumption', ['quantity' => '100.01'])->assertSessionHas('error');
    feedEntry('adjustment_out', ['quantity' => '150'])->assertSessionHas('error');
    feedEntry('consumption', ['quantity' => '60'])->assertSessionHasNoErrors();

    $opening = FeedTransaction::where('type', 'opening')->sole();
    $this->delete(route('feed.transactions.destroy', $opening), ['reason' => 'Mistake'])->assertSessionHas('error'); // 60 already used
    $this->put(route('feed.transactions.update', $opening), ['type' => 'opening', 'feed_item_id' => $this->item->id,
        'transaction_date' => '2026-11-12', 'quantity' => '50', 'reason' => 'Counted again'])->assertSessionHas('error');
    expect($this->feed->stock()[$this->item->id])->toBe('40.00');
});

it('edits a purchase (expense follows) and deletes it with its expense; checks payment rules', function () {
    feedEntry('purchase', ['amount' => '300'])->assertSessionHasErrors('payment_mode_id');
    feedEntry('purchase', ['amount' => '300', 'is_credit' => '1'])->assertSessionHasErrors('vendor_id');
    feedEntry('purchase', ['amount' => '300', 'payment_mode_id' => PaymentMode::where('code', 'CARD')->value('id')])->assertSessionHasErrors('payment_mode_id');
    feedEntry('purchase')->assertSessionHasErrors('amount');

    feedEntry('purchase', ['quantity' => '10', 'amount' => '300', 'payment_mode_id' => $this->cash])->assertSessionHasNoErrors();
    $purchase = FeedTransaction::sole();

    $data = ['type' => 'purchase', 'feed_item_id' => $this->item->id, 'transaction_date' => '2026-11-12', 'quantity' => '12', 'amount' => '360', 'payment_mode_id' => $this->cash];
    $this->put(route('feed.transactions.update', $purchase), $data)->assertSessionHasErrors('reason');
    $this->put(route('feed.transactions.update', $purchase), $data + ['reason' => 'Bill corrected'])->assertSessionHasNoErrors();
    expect(Expense::sole())->amount->toBe('360.00')->quantity->toBe('12.00')->payment_mode_id->toBe($this->cash);

    $this->delete(route('feed.transactions.destroy', $purchase), ['reason' => 'Returned'])->assertSessionHasNoErrors();
    expect(FeedTransaction::count())->toBe(0)->and(Expense::count())->toBe(0);
});

it('shows stock, the 90-day average cost and low items on the stock screen', function () {
    $this->item->update(['reorder_level' => '200']);
    feedEntry('purchase', ['transaction_date' => '2026-06-01', 'quantity' => '100', 'amount' => '1000', 'payment_mode_id' => $this->cash])->assertSessionHasNoErrors(); // too old
    feedEntry('purchase', ['quantity' => '100', 'amount' => '2500', 'payment_mode_id' => $this->cash])->assertSessionHasNoErrors();
    feedEntry('purchase', ['quantity' => '100', 'amount' => '2700', 'payment_mode_id' => $this->cash])->assertSessionHasNoErrors();

    expect($this->feed->averageCost()[$this->item->id])->toBe('26.00'); // (2,500 + 2,700) ÷ 200

    $this->get(route('feed.index'))->assertOk()->assertSee('300.00 kg')->assertSee('₹26.00')->assertSee('₹7,800.00')->assertDontSee('table-danger', false);
    feedEntry('consumption', ['quantity' => '-5'])->assertSessionHasErrors('quantity');
    feedEntry('consumption', ['quantity' => '150'])->assertSessionHasNoErrors(); // 150 left ≤ 200
    $this->get(route('feed.index'))->assertOk()->assertSee('table-danger', false)->assertSee('1 item at or below reorder level');
});

it('keeps item names unique, locks the unit once used, deletes only unused items; feed rights only', function () {
    $this->post(route('feed.items.store'), ['name' => 'cattle feed', 'unit' => 'kg'])->assertSessionHasErrors('name');
    feedEntry('opening')->assertSessionHasNoErrors();
    $this->put(route('feed.items.update', $this->item), ['name' => 'Cattle feed', 'unit' => 'bag', 'reorder_level' => '0'])->assertSessionHasErrors('unit');
    $this->delete(route('feed.items.destroy', $this->item))->assertSessionHas('error');

    $this->post(route('feed.items.store'), ['name' => 'Bhusa', 'unit' => 'bag'])->assertSessionHasNoErrors();
    $this->delete(route('feed.items.destroy', FeedItem::where('name', 'Bhusa')->sole()))->assertSessionHas('success');

    $this->getJson(route('vendors.search'))->assertOk(); // herd staff pick a vendor for credit purchases

    $delivery = User::factory()->forFarm($this->farm)->withRole(RoleName::DeliveryBoy)->create();
    $this->actingAs($delivery)->get(route('feed.index'))->assertForbidden();
    feedEntry('opening')->assertForbidden();
    $this->getJson(route('vendors.search'))->assertForbidden();
});
