<?php

use App\Enums\RoleName;
use App\Models\Animal;
use App\Models\AnimalSale;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\FeedItem;
use App\Models\FeedTransaction;
use App\Models\MilkProductionRecord;
use App\Models\Party;
use App\Models\Product;
use App\Models\User;
use App\Services\AnimalPnlService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->party = Party::factory()->create(['farm_id' => $this->farm->id, 'party_type' => 'both', 'name' => 'Ramesh Yadav']);
    $this->actingAs($this->owner);
});

/** Buy one animal for $price with $transport, returns it. */
function buyAnimal(string $tag, string $price, string $transport = '0', string $ownership = 'resale', string $stage = 'heifer', string $date = '2026-06-01'): Animal
{
    test()->post(route('animal-purchases.store'), ['party_id' => test()->party->id, 'purchase_date' => $date, 'transport_cost' => $transport,
        'animals' => [['tag_no' => $tag, 'animal_type' => 'cow', 'gender' => 'female', 'lifecycle_stage' => $stage,
            'ownership_type' => $ownership, 'price' => $price]]])->assertSessionHasNoErrors();

    return Animal::where('tag_no', $tag)->sole();
}

function feedAnimal(Animal $animal, string $kg): void
{
    $item = FeedItem::firstOrCreate(['farm_id' => $animal->farm_id, 'name' => 'Cattle feed'], ['unit' => 'kg', 'reorder_level' => 0, 'is_active' => true]);
    if (! FeedTransaction::where('type', 'purchase')->exists()) {
        FeedTransaction::create(['feed_item_id' => $item->id, 'transaction_date' => '2026-06-01', 'type' => 'purchase', 'quantity' => '1000', 'amount' => '20000', 'rate' => '20']);
    }
    FeedTransaction::create(['feed_item_id' => $item->id, 'transaction_date' => '2026-07-01', 'type' => 'consumption', 'quantity' => $kg, 'animal_id' => $animal->id]);
}

it('heifer example: cost 35,000 + 1,000 transport + 12,000 feed + 2,000 health = 50,000; sale 65,000 → profit 15,000 — spec acceptance', function () {
    $heifer = buyAnimal('H-7', '35000', '1000');
    feedAnimal($heifer, '600');  // 600 kg × ₹20 average = 12,000
    Expense::factory()->create(['farm_id' => $this->farm->id, 'animal_id' => $heifer->id, 'amount' => '2000.00']);
    Expense::factory()->create(['farm_id' => $this->farm->id, 'animal_id' => $heifer->id, 'amount' => '500.00'])->delete(); // deleted: not counted

    $this->post(route('animal-sales.store'), ['party_id' => $this->party->id, 'sale_date' => '2026-11-01', 'selling_costs' => '0',
        'animals' => [['animal_id' => $heifer->id, 'price' => '65000']]])->assertSessionHasNoErrors();

    $pnl = app(AnimalPnlService::class)->forAnimal($heifer->fresh());
    expect($pnl)->base_cost->toBe('36000.00')->expenses->toBe('2000.00')->feed_cost->toBe('12000.00')
        ->total_cost->toBe('50000.00')->income->toBe('65000.00')->profit->toBe('15000.00')->holding_days->toBe(153);

    $this->get(route('animals.show', $heifer))->assertOk()->assertSee('Estimated profit')->assertSee('₹15,000.00');

    $this->get(route('reports.trading', 'summary'))->assertOk()
        ->assertSee('H-7')->assertSee('₹36,000.00')->assertSee('₹50,000.00')->assertSee('₹65,000.00')->assertSee('₹15,000.00');
    $this->get(route('reports.trading', ['summary', 'format' => 'excel']))->assertOk();
});

it('takes the sale share of selling costs by price; a cancelled sale is not income', function () {
    $a = buyAnimal('A-1', '30000');
    $b = buyAnimal('B-1', '10000');

    $this->post(route('animal-sales.store'), ['party_id' => $this->party->id, 'sale_date' => '2026-07-01', 'selling_costs' => '1000',
        'animals' => [['animal_id' => $a->id, 'price' => '30000'], ['animal_id' => $b->id, 'price' => '10000']]])->assertSessionHasNoErrors();

    $pnl = app(AnimalPnlService::class)->forAnimals(collect([$a->fresh(), $b->fresh()]));
    expect($pnl[$a->id]['sale']['income'])->toBe('29250.00')->and($pnl[$b->id]['sale']['income'])->toBe('9750.00')
        ->and($pnl[$a->id]['profit'])->toBe('-750.00');

    $this->post(route('animal-sales.cancel', AnimalSale::sole()), ['reason' => 'Buyer backed out']);
    expect(app(AnimalPnlService::class)->forAnimal($a->fresh()))->sale->toBeNull()->income->toBe('0.00')->profit->toBe('-30000.00');
});

it('adds milk value for own-herd animals at the month realized own-farm rate (fallback: the type average)', function () {
    $cow = buyAnimal('M-1', '50000', '0', 'own_herd', 'milking');
    $opening = Animal::factory()->create(['farm_id' => $this->farm->id, 'source' => 'opening', 'opening_value' => '40000', 'ownership_type' => 'own_herd']);

    $product = Product::factory()->create(['farm_id' => $this->farm->id, 'milk_type' => 'cow']);
    $customer = Customer::factory()->create(['farm_id' => $this->farm->id]);
    $deliver = fn ($date, $qty, $amount, $source = 'own_farm') => Delivery::factory()->create(['farm_id' => $this->farm->id, 'customer_id' => $customer->id,
        'product_id' => $product->id, 'delivery_date' => $date, 'delivered_qty' => $qty, 'amount' => $amount, 'status' => 'delivered', 'stock_source' => $source]);
    $deliver('2026-07-10', '10', '600');            // July: ₹60 / L
    $deliver('2026-07-11', '10', '640');            // July average ₹62 / L
    $deliver('2026-07-12', '10', '1000', 'collection'); // other stock: ignored

    MilkProductionRecord::factory()->create(['farm_id' => $this->farm->id, 'animal_id' => $cow->id, 'production_date' => '2026-07-05', 'shift' => 'morning', 'quantity_litre' => '10']);
    MilkProductionRecord::factory()->create(['farm_id' => $this->farm->id, 'animal_id' => $cow->id, 'production_date' => '2026-08-05', 'shift' => 'morning', 'quantity_litre' => '5']); // August: no sales → type average 62

    $pnl = app(AnimalPnlService::class)->forAnimals(collect([$cow->fresh(), $opening]));
    expect($pnl[$cow->id])->milk_litres->toBe('15.00')->milk_value->toBe('930.00')->income->toBe('930.00')->profit->toBe('-49070.00')
        ->and($pnl[$opening->id])->base_cost->toBe('40000.00')->base_label->toBe('Opening value')->holding_days->toBeNull();
});

it('trading report: only resale animals bought in the period, unsold show no profit; needs reports + trading view; flat queries', function () {
    feedAnimal(buyAnimal('R-1', '20000'), '10');
    buyAnimal('O-1', '20000', '0', 'own_herd');
    buyAnimal('OLD-1', '20000', '0', 'resale', 'heifer', '2025-01-10');

    $this->get(route('reports.trading', ['summary', 'from' => '2026-04-01', 'to' => '2026-11-16']))->assertOk()
        ->assertSee('R-1')->assertDontSee('O-1')->assertDontSee('OLD-1');
    $this->get(route('reports.index'))->assertSee('Animal trading summary');

    $herd = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $this->actingAs($herd)->get(route('reports.trading', 'summary'))->assertForbidden();

    $this->actingAs($this->owner);
    $count = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('reports.trading', 'summary'))->assertOk();

        return count(DB::getQueryLog());
    };
    $few = $count();
    foreach (range(2, 6) as $n) {
        $animal = buyAnimal("R-{$n}", '15000');
        feedAnimal($animal, '10');
    }
    expect($count())->toBe($few);
});
