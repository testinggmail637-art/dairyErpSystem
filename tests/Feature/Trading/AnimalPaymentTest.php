<?php

use App\Enums\PaymentDirection;
use App\Enums\RoleName;
use App\Models\Animal;
use App\Models\AnimalPayment;
use App\Models\AnimalPurchase;
use App\Models\AnimalSale;
use App\Models\Farm;
use App\Models\Party;
use App\Models\PaymentMode;
use App\Models\User;
use App\Services\PartyLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create(); // animal_trading.view only
    $this->mode = PaymentMode::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Cash']);
    $this->party = Party::factory()->create(['farm_id' => $this->farm->id, 'party_type' => 'both', 'name' => 'Ramesh Yadav', 'opening_balance' => '1000']);
    $this->actingAs($this->owner);
});

/** Purchase of one animal for 30,000 + 1,000 transport (total 31,000), optionally with a first payment. */
function buyFrom(Party $party, array $overrides = []): AnimalPurchase
{
    test()->post(route('animal-purchases.store'), array_merge(['party_id' => $party->id, 'purchase_date' => '2026-11-01',
        'transport_cost' => '1000', 'commission' => '', 'other_cost' => '',
        'animals' => [['tag_no' => 'P-'.Str::random(5), 'animal_type' => 'cow', 'gender' => 'female', 'lifecycle_stage' => 'milking',
            'ownership_type' => 'resale', 'price' => '30000']]], $overrides))->assertSessionHasNoErrors();

    return AnimalPurchase::latest('id')->first();
}

function sellTo(Party $party, string $price = '50000'): AnimalSale
{
    $animal = Animal::factory()->create(['farm_id' => $party->farm_id]);
    test()->post(route('animal-sales.store'), ['party_id' => $party->id, 'sale_date' => '2026-11-05', 'selling_costs' => '2000',
        'animals' => [['animal_id' => $animal->id, 'price' => $price]]])->assertSessionHasNoErrors();

    return AnimalSale::latest('id')->first();
}

function payParty(array $data): TestResponse
{
    return test()->post(route('animal-payments.store'), array_merge(['party_id' => test()->party->id, 'direction' => 'paid',
        'payment_date' => '2026-11-10', 'amount' => '1000', 'payment_mode_id' => test()->mode->id], $data));
}

it('balance = opening + sales net − purchases total − received + paid; cancelled sales do not count', function () {
    buyFrom($this->party);                       // −31,000
    $sale = sellTo($this->party);                // +48,000 (50,000 − 2,000)
    payParty(['direction' => 'paid', 'amount' => '10,000'])->assertSessionHasNoErrors();      // +10,000
    payParty(['direction' => 'received', 'amount' => '5000'])->assertSessionHasNoErrors();    // −5,000

    $ledger = app(PartyLedgerService::class);
    expect($ledger->balance($this->party))->toBe('23000.00')
        ->and($ledger->balances(collect([$this->party])))->toBe([$this->party->id => '23000.00']);

    $cancelled = sellTo($this->party, '9000');
    $this->post(route('animal-sales.cancel', $cancelled), ['reason' => 'Buyer backed out']);
    expect($ledger->balance($this->party))->toBe('23000.00');

    $this->get(route('parties.index'))->assertSee('₹23,000.00');
});

it('records installments against a purchase and shows paid / due; a re-submit is not saved twice', function () {
    $purchase = buyFrom($this->party);
    $uuid = (string) Str::uuid();

    payParty(['animal_purchase_id' => $purchase->id, 'amount' => '20000', 'client_uuid' => $uuid])
        ->assertRedirect(route('parties.ledger', $this->party));
    payParty(['animal_purchase_id' => $purchase->id, 'amount' => '20000', 'client_uuid' => $uuid])
        ->assertSessionHas('success', 'This payment was already recorded.');
    payParty(['animal_purchase_id' => $purchase->id, 'amount' => '5000'])->assertSessionHasNoErrors();

    expect($purchase->payments()->count())->toBe(2)
        ->and(AnimalPayment::first())->farm_id->toBe($this->farm->id)->direction->toBe(PaymentDirection::Paid);
    $this->get(route('animal-purchases.show', $purchase))->assertOk()->assertSee('₹25,000.00')->assertSee('₹6,000.00'); // paid / due
});

it('links a payment only to a document of the same party that fits the direction', function () {
    $other = Party::factory()->create(['farm_id' => $this->farm->id, 'party_type' => 'both']);
    $otherPurchase = buyFrom($other);
    $sale = sellTo($this->party);

    payParty(['animal_purchase_id' => $otherPurchase->id])->assertSessionHasErrors('animal_purchase_id');
    payParty(['amount' => '0', 'payment_mode_id' => ''])->assertSessionHasErrors(['amount', 'payment_mode_id']);

    // a sale id sent with "paid" is dropped; with "received" it is linked
    payParty(['direction' => 'paid', 'animal_sale_id' => $sale->id])->assertSessionHasNoErrors();
    payParty(['direction' => 'received', 'animal_sale_id' => $sale->id])->assertSessionHasNoErrors();
    expect(AnimalPayment::whereNotNull('animal_sale_id')->count())->toBe(1);

    $this->post(route('animal-sales.cancel', $sale), ['reason' => 'Mistake'])->assertSessionHas('error'); // has payments
    payParty(['direction' => 'received', 'animal_sale_id' => $sale->id])->assertSessionHasNoErrors();
});

it('saves the optional initial payment with a purchase, never more than its total', function () {
    $purchase = buyFrom($this->party, ['initial_payment' => '10,000', 'initial_payment_mode_id' => $this->mode->id, 'initial_reference_no' => 'UPI-1']);

    expect($purchase->payments()->sole())->amount->toBe('10000.00')->direction->toBe(PaymentDirection::Paid)
        ->reference_no->toBe('UPI-1')->payment_date->toDateString()->toBe('2026-11-01');

    $this->post(route('animal-purchases.store'), ['party_id' => $this->party->id, 'purchase_date' => '2026-11-01', 'transport_cost' => '0',
        'animals' => [['tag_no' => 'Z1', 'animal_type' => 'cow', 'gender' => 'female', 'lifecycle_stage' => 'milking', 'ownership_type' => 'resale', 'price' => '100']],
        'initial_payment' => '100.01', 'initial_payment_mode_id' => $this->mode->id])->assertSessionHasErrors('initial_payment');
    $this->post(route('animal-purchases.store'), ['party_id' => $this->party->id, 'purchase_date' => '2026-11-01', 'transport_cost' => '0',
        'animals' => [['tag_no' => 'Z2', 'animal_type' => 'cow', 'gender' => 'female', 'lifecycle_stage' => 'milking', 'ownership_type' => 'resale', 'price' => '100']],
        'initial_payment' => '50'])->assertSessionHasErrors('initial_payment_mode_id');

    // a purchase with payments cannot be deleted
    $this->delete(route('animal-purchases.destroy', $purchase), ['reason' => 'Wrong'])->assertSessionHas('error');
    expect(AnimalPurchase::count())->toBe(1);
});

it('deletes a payment with a reason; view-only users see but cannot change; party with payments is kept', function () {
    payParty([])->assertSessionHasNoErrors();
    $payment = AnimalPayment::sole();

    $this->delete(route('parties.destroy', $this->party))->assertSessionHas('error');

    $this->actingAs($this->accountant);
    $this->get(route('animal-payments.index'))->assertOk()->assertSee('Ramesh Yadav')->assertDontSee('Record payment');
    $this->get(route('parties.ledger', $this->party))->assertOk()->assertSee('Opening balance')->assertDontSee('Record payment');
    $this->get(route('animal-payments.create'))->assertForbidden();
    $this->delete(route('animal-payments.destroy', $payment), ['reason' => 'x x x'])->assertForbidden();

    $this->actingAs($this->owner);
    $this->delete(route('animal-payments.destroy', $payment))->assertSessionHasErrors('reason');
    $this->delete(route('animal-payments.destroy', $payment), ['reason' => 'Entered twice'])->assertSessionHas('success');
    expect(AnimalPayment::count())->toBe(0);
});

it('ledger: running balance in date order, period opening, links; flat query counts', function () {
    $purchase = buyFrom($this->party);                                          // 01-11 −31,000
    payParty(['amount' => '31000', 'animal_purchase_id' => $purchase->id]);    // 10-11 +31,000
    $sale = sellTo($this->party);                                               // 05-11 +48,000

    $response = $this->get(route('parties.ledger', $this->party))->assertOk()
        ->assertSee($purchase->purchase_no)->assertSee($sale->sale_no)->assertSee('₹49,000.00'); // closing 1,000 − 31,000 + 48,000 + 31,000
    $rows = $response->viewData('ledger')['rows'];
    expect($rows->pluck('type')->all())->toBe(['purchase', 'sale', 'paid'])
        ->and($rows->pluck('balance')->map(fn ($b) => (string) $b)->all())->toBe(['-30000.00', '18000.00', '49000.00']);

    $period = $this->get(route('parties.ledger', [$this->party, 'from' => '2026-11-05']))->viewData('ledger');
    expect($period['opening'])->toBe('-30000.00')->and($period['closing'])->toBe('49000.00');

    $count = function (string $url) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($url)->assertOk();

        return count(DB::getQueryLog());
    };
    $ledgerFew = $count(route('parties.ledger', $this->party));
    $listFew = $count(route('animal-payments.index'));
    $partiesFew = $count(route('parties.index'));
    foreach (range(1, 5) as $n) {
        payParty(['amount' => (string) $n, 'direction' => $n % 2 ? 'paid' : 'received']);
        Party::factory()->create(['farm_id' => $this->farm->id]);
    }
    expect($count(route('parties.ledger', $this->party)))->toBe($ledgerFew)
        ->and($count(route('animal-payments.index')))->toBe($listFew)
        ->and($count(route('parties.index')))->toBe($partiesFew);
});

it('pre-fills the payment form from a purchase and lists the party documents', function () {
    $purchase = buyFrom($this->party);

    $this->get(route('animal-purchases.show', $purchase))
        ->assertSee(route('animal-payments.create', ['party_id' => $this->party->id, 'direction' => 'paid', 'animal_purchase_id' => $purchase->id]));
    $this->get(route('animal-payments.create', ['party_id' => $this->party->id, 'direction' => 'paid', 'animal_purchase_id' => $purchase->id]))
        ->assertOk()->assertSee('Ramesh Yadav')->assertSee($purchase->purchase_no)->assertSee('data-client-uuid', false);
});
