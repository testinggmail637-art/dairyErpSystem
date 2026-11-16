<?php

use App\Enums\AnimalSaleStatus;
use App\Enums\AnimalStatus;
use App\Enums\RoleName;
use App\Models\Animal;
use App\Models\AnimalSale;
use App\Models\Farm;
use App\Models\Party;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create(); // animal_trading.view only
    $this->buyer = Party::factory()->create(['farm_id' => $this->farm->id, 'party_type' => 'buyer', 'name' => 'Suresh Patil', 'village' => 'Loni']);
    $this->cow = Animal::factory()->create(['farm_id' => $this->farm->id, 'tag_no' => 'C-1', 'gender' => 'female', 'lifecycle_stage' => 'milking']);
    $this->heifer = Animal::factory()->create(['farm_id' => $this->farm->id, 'tag_no' => 'H-1', 'gender' => 'female', 'lifecycle_stage' => 'heifer']);
});

function salePayload(int $partyId, array $animals, array $overrides = []): array
{
    return array_merge(['party_id' => $partyId, 'sale_date' => '2026-11-12', 'selling_costs' => '1,500', 'notes' => '', 'animals' => $animals], $overrides);
}

it('sells active animals: marks them sold with a history row, net = price − selling costs', function () {
    $this->actingAs($this->owner)->post(route('animal-sales.store'), salePayload($this->buyer->id, [
        ['animal_id' => $this->cow->id, 'price' => '45,000'],
        ['animal_id' => $this->heifer->id, 'price' => '20000'],
        ['animal_id' => '', 'price' => ''], // empty row ignored
    ]))->assertSessionHasNoErrors();

    $sale = AnimalSale::sole();
    expect($sale)->farm_id->toBe($this->farm->id)->total_price->toBe('65000.00')->selling_costs->toBe('1500.00')
        ->net_amount->toBe('63500.00')->status->toBe(AnimalSaleStatus::Completed)
        ->and($sale->items()->pluck('price')->all())->toBe(['45000.00', '20000.00']);

    $cow = $this->cow->fresh();
    expect($cow->status)->toBe(AnimalStatus::Sold)->and($cow->status_changed_on->toDateString())->toBe('2026-11-12');
    $history = $cow->statusHistories()->where('field', 'status')->latest('id')->first();
    expect($history)->old_value->toBe('active')->new_value->toBe('sold')->reason->toContain($sale->sale_no);

    $this->get(route('animal-sales.show', $sale))->assertOk()->assertSee('C-1')->assertSee('₹63,500.00')->assertSee('Cancel sale');
});

it('sold animal disappears from milk entry and keeps its history — spec acceptance', function () {
    $this->actingAs($this->owner)->post(route('animal-sales.store'), salePayload($this->buyer->id, [['animal_id' => $this->cow->id, 'price' => '45000']]));

    $this->getJson(route('animals.search', ['milking' => 1, 'q' => 'C-1']))->assertOk()->assertJsonCount(0, 'results');
    expect(Animal::query()->milking()->whereKey($this->cow->id)->exists())->toBeFalse()
        ->and($this->cow->fresh()->trashed())->toBeFalse()
        ->and($this->cow->fresh()->transactionCounts())->toHaveKey('sales');
    $this->get(route('animals.show', $this->cow))->assertOk()->assertDontSee(route('animal-sales.create', ['animal_id' => $this->cow->id]), false);
});

it('only sells active animals of this farm to an active buyer; validates prices and costs', function () {
    $dead = Animal::factory()->create(['farm_id' => $this->farm->id, 'status' => 'deceased']);
    $seller = Party::factory()->create(['farm_id' => $this->farm->id, 'party_type' => 'seller']);

    $this->actingAs($this->owner)->post(route('animal-sales.store'), salePayload($seller->id, [
        ['animal_id' => $dead->id, 'price' => '1000'],
        ['animal_id' => $this->cow->id, 'price' => '0'],
        ['animal_id' => $this->cow->id, 'price' => '100'],
    ]))->assertSessionHasErrors(['party_id', 'animals.0.animal_id', 'animals.1.price', 'animals.2.animal_id']);

    $this->post(route('animal-sales.store'), salePayload($this->buyer->id, [['animal_id' => $this->cow->id, 'price' => '1000']], ['selling_costs' => '1000.01']))
        ->assertSessionHasErrors('selling_costs');

    expect(AnimalSale::count())->toBe(0)->and($this->cow->fresh()->status)->toBe(AnimalStatus::Active);
});

it('cancels a sale with a reason: animals active again, sale kept as cancelled; cannot cancel twice', function () {
    $this->actingAs($this->owner)->post(route('animal-sales.store'), salePayload($this->buyer->id, [['animal_id' => $this->cow->id, 'price' => '45000']]));
    $sale = AnimalSale::sole();

    $this->post(route('animal-sales.cancel', $sale))->assertSessionHasErrors('reason');
    $this->post(route('animal-sales.cancel', $sale), ['reason' => 'Buyer backed out'])->assertRedirect(route('animal-sales.show', $sale));

    expect($sale->fresh())->status->toBe(AnimalSaleStatus::Cancelled)->cancel_reason->toBe('Buyer backed out')->cancelled_by->toBe($this->owner->id)
        ->and($this->cow->fresh()->status)->toBe(AnimalStatus::Active)
        ->and($this->cow->statusHistories()->where('field', 'status')->latest('id')->value('reason'))->toBe('Buyer backed out');

    $this->post(route('animal-sales.cancel', $sale), ['reason' => 'Again'])->assertSessionHas('error');

    // the animal can be sold again
    $this->post(route('animal-sales.store'), salePayload($this->buyer->id, [['animal_id' => $this->cow->id, 'price' => '44000']]))->assertSessionHasNoErrors();
    expect(AnimalSale::count())->toBe(2)->and($this->cow->fresh()->status)->toBe(AnimalStatus::Sold);
});

it('refuses to cancel when an animal status changed after the sale', function () {
    $this->actingAs($this->owner)->post(route('animal-sales.store'), salePayload($this->buyer->id, [['animal_id' => $this->cow->id, 'price' => '45000']]));
    $this->cow->fresh()->update(['status' => 'deceased']);

    $this->post(route('animal-sales.cancel', AnimalSale::sole()), ['reason' => 'Mistake'])->assertSessionHas('error');
    expect(AnimalSale::sole()->status)->toBe(AnimalSaleStatus::Completed);
});

it('lists sales with filters and a flat query count; view-only users cannot sell or cancel; party with sales is kept', function () {
    $this->actingAs($this->owner)->post(route('animal-sales.store'), salePayload($this->buyer->id, [['animal_id' => $this->cow->id, 'price' => '45000']]));
    $sale = AnimalSale::sole();

    $this->actingAs($this->accountant)->get(route('animal-sales.index'))->assertOk()->assertSee('Suresh Patil')->assertDontSee('New sale');
    $this->get(route('animal-sales.index', ['status' => 'cancelled']))->assertDontSee('Suresh Patil');
    $this->get(route('animal-sales.index', ['to' => '2026-11-11']))->assertDontSee('Suresh Patil');
    $this->get(route('animal-sales.create'))->assertForbidden();
    $this->post(route('animal-sales.cancel', $sale), ['reason' => 'x x x'])->assertForbidden();
    $this->get(route('animal-sales.show', $sale))->assertOk()->assertDontSee('Cancel sale');

    $count = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('animal-sales.index'))->assertOk();

        return count(DB::getQueryLog());
    };
    $few = $count();
    $this->actingAs($this->owner);
    Animal::factory()->count(4)->create(['farm_id' => $this->farm->id])
        ->each(fn ($a) => $this->post(route('animal-sales.store'), salePayload($this->buyer->id, [['animal_id' => $a->id, 'price' => '2000']])));
    $this->delete(route('parties.destroy', $this->buyer))->assertSessionHas('error');

    $this->actingAs($this->accountant);
    expect(AnimalSale::count())->toBe(5)->and($count())->toBe($few);
});

it('opens the sale form with an animal pre-selected from its page', function () {
    $this->actingAs($this->owner)->get(route('animals.show', $this->cow))->assertSee(route('animal-sales.create', ['animal_id' => $this->cow->id]), false);
    $this->get(route('animal-sales.create', ['animal_id' => $this->cow->id]))->assertOk()
        ->assertSee('<option value="'.$this->cow->id.'" selected>', false)->assertSee('animals[__I__][animal_id]', false);
});
