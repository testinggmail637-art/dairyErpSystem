<?php

use App\Enums\AnimalSource;
use App\Enums\AnimalStatus;
use App\Enums\OwnershipType;
use App\Enums\RoleName;
use App\Models\Animal;
use App\Models\AnimalBreed;
use App\Models\AnimalPurchase;
use App\Models\Farm;
use App\Models\MilkProductionRecord;
use App\Models\Party;
use App\Models\User;
use App\Services\AnimalPurchaseService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create(); // animal_trading.view only
    $this->seller = Party::factory()->create(['farm_id' => $this->farm->id, 'party_type' => 'seller', 'name' => 'Ramesh Yadav', 'village' => 'Wadgaon']);
});

function animalRow(array $overrides = []): array
{
    return array_merge(['tag_no' => 'C-101', 'animal_type' => 'cow', 'breed_id' => '', 'gender' => 'female', 'age_months' => '36',
        'lifecycle_stage' => 'milking', 'ownership_type' => 'own_herd', 'price' => '30,000'], $overrides);
}

function purchasePayload(int $partyId, array $overrides = []): array
{
    return array_merge(['party_id' => $partyId, 'purchase_date' => '2026-11-10', 'transport_cost' => '1000', 'commission' => '',
        'other_cost' => '', 'animals' => [animalRow(), animalRow(['tag_no' => 'C-102', 'price' => '20000', 'ownership_type' => 'resale'])]], $overrides);
}

it('allocates costs by price; the last item absorbs the rounding', function () {
    expect(AnimalPurchaseService::allocate('1000.00', ['30000.00', '20000.00']))->toBe(['600.00', '400.00'])
        ->and(AnimalPurchaseService::allocate('100.00', ['1.00', '1.00', '1.00']))->toBe(['33.33', '33.33', '33.34'])
        ->and(AnimalPurchaseService::allocate('0.00', ['500.00']))->toBe(['0.00']);
});

it('saves a purchase: creates the animals (source purchased) and items with allocated cost — spec acceptance', function () {
    $breed = AnimalBreed::factory()->create(['farm_id' => $this->farm->id, 'animal_type' => 'cow', 'name' => 'HF']);
    $payload = purchasePayload($this->seller->id);
    $payload['animals'][0]['breed_id'] = $breed->id;
    $payload['animals'][] = ['tag_no' => '', 'price' => '']; // an empty row is ignored

    $this->actingAs($this->owner)->post(route('animal-purchases.store'), $payload)->assertSessionHasNoErrors();

    $purchase = AnimalPurchase::sole();
    expect($purchase)->farm_id->toBe($this->farm->id)->total_animal_price->toBe('50000.00')->total_amount->toBe('51000.00')
        ->and($purchase->purchase_no)->not->toBeEmpty();

    $items = $purchase->items()->with('animal')->orderBy('id')->get();
    expect($items->pluck('allocated_cost')->all())->toBe(['600.00', '400.00'])
        ->and($items->pluck('total_cost')->all())->toBe(['30600.00', '20400.00']);

    $cow = $items[0]->animal;
    expect($cow)->source->toBe(AnimalSource::Purchased)->status->toBe(AnimalStatus::Active)->breed_id->toBe($breed->id)
        ->date_of_birth->toDateString()->toBe('2023-11-10')->dob_is_estimated->toBeTrue()
        ->and($items[1]->animal->ownership_type)->toBe(OwnershipType::Resale)
        ->and($cow->statusHistories()->value('changed_on')->toDateString())->toBe('2026-11-10');

    $this->get(route('animal-purchases.show', $purchase))->assertOk()->assertSee('C-101')->assertSee('₹30,600.00')->assertSee('₹51,000.00');
});

it('validates rows and saves nothing when one row is wrong', function () {
    Animal::factory()->create(['farm_id' => $this->farm->id, 'tag_no' => 'C-101']);
    $buyer = Party::factory()->create(['farm_id' => $this->farm->id, 'party_type' => 'buyer']);
    $buffaloBreed = AnimalBreed::factory()->create(['farm_id' => $this->farm->id, 'animal_type' => 'buffalo']);

    $this->actingAs($this->owner)->post(route('animal-purchases.store'), purchasePayload($buyer->id, ['animals' => [
        animalRow(),                                                     // tag already used
        animalRow(['tag_no' => 'X1', 'breed_id' => $buffaloBreed->id]),  // breed of another type
        animalRow(['tag_no' => 'X2', 'gender' => 'male']),               // a male cannot be milking
        animalRow(['tag_no' => 'x2', 'price' => '0']),                   // duplicate tag, no price
    ]]))->assertSessionHasErrors(['party_id', 'animals.0.tag_no', 'animals.1.breed_id', 'animals.2.lifecycle_stage', 'animals.3.tag_no', 'animals.3.price']);

    $this->post(route('animal-purchases.store'), purchasePayload($this->seller->id, ['animals' => []]))->assertSessionHasErrors('animals');

    expect(AnimalPurchase::count())->toBe(0)->and(Animal::count())->toBe(1);
});

it('deletes a purchase with a reason only while its animals have no other records', function () {
    $this->actingAs($this->owner)->post(route('animal-purchases.store'), purchasePayload($this->seller->id));
    $purchase = AnimalPurchase::sole();
    $cow = Animal::where('tag_no', 'C-101')->sole();

    $this->delete(route('animal-purchases.destroy', $purchase))->assertSessionHasErrors('reason');

    MilkProductionRecord::factory()->create(['farm_id' => $this->farm->id, 'animal_id' => $cow->id]);
    $this->delete(route('animal-purchases.destroy', $purchase), ['reason' => 'Entered twice'])->assertSessionHas('error');
    expect(AnimalPurchase::count())->toBe(1);

    MilkProductionRecord::query()->delete();
    $this->delete(route('animal-purchases.destroy', $purchase), ['reason' => 'Entered twice'])->assertRedirect(route('animal-purchases.index'));
    expect(AnimalPurchase::count())->toBe(0)->and(Animal::count())->toBe(0)->and(Animal::withTrashed()->count())->toBe(2);
});

it('blocks deleting a purchased animal or its seller on their own', function () {
    $this->actingAs($this->owner)->post(route('animal-purchases.store'), purchasePayload($this->seller->id));

    expect(Animal::where('tag_no', 'C-101')->sole()->transactionCounts())->toHaveKey('purchases');

    $this->delete(route('parties.destroy', $this->seller))->assertSessionHas('error');
    expect($this->seller->fresh()->trashed())->toBeFalse();
});

it('lists purchases with filters and a flat query count; view-only users cannot add', function () {
    $this->actingAs($this->owner)->post(route('animal-purchases.store'), purchasePayload($this->seller->id));

    $this->actingAs($this->accountant)->get(route('animal-purchases.index'))->assertOk()->assertSee('Ramesh Yadav')->assertDontSee('New purchase');
    $this->get(route('animal-purchases.index', ['search' => 'nobody']))->assertDontSee('Ramesh Yadav');
    $this->get(route('animal-purchases.index', ['from' => '2026-11-11']))->assertDontSee('Ramesh Yadav');
    $this->get(route('animal-purchases.create'))->assertForbidden();
    $this->post(route('animal-purchases.store'), purchasePayload($this->seller->id))->assertForbidden();
    $this->delete(route('animal-purchases.destroy', AnimalPurchase::sole()), ['reason' => 'x x x'])->assertForbidden();
    $this->getJson(route('parties.search', ['type' => 'seller', 'q' => 'rame']))->assertOk()->assertJsonPath('results.0.text', 'Ramesh Yadav · Wadgaon');

    $count = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('animal-purchases.index'))->assertOk();

        return count(DB::getQueryLog());
    };
    $few = $count();
    $this->actingAs($this->owner);
    foreach (range(1, 5) as $n) {
        $this->post(route('animal-purchases.store'), purchasePayload($this->seller->id, ['animals' => [animalRow(['tag_no' => "T{$n}"])]]));
    }
    $this->actingAs($this->accountant);
    expect(AnimalPurchase::count())->toBe(6)->and($count())->toBe($few);
});

it('shows the purchase form with its widgets', function () {
    AnimalBreed::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Gir']);

    $this->actingAs($this->owner)->get(route('animal-purchases.create'))->assertOk()
        ->assertSee('data-add-row', false)->assertSee('animals[__I__][tag_no]', false)->assertSee('Gir');
});
