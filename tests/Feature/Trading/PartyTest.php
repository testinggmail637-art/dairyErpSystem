<?php

use App\Enums\PartyType;
use App\Enums\RoleName;
use App\Models\Farm;
use App\Models\Party;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create(); // animal_trading.view only
});

function partyPayload(array $overrides = []): array
{
    return array_merge(['name' => 'Ramesh Yadav', 'phone' => '+91 98765 43210', 'village' => 'Wadgaon', 'address' => 'Near temple',
        'party_type' => 'seller', 'opening_balance' => '-12,500.50', 'is_active' => '1'], $overrides);
}

it('adds and edits a party with normalised phone and a signed opening balance', function () {
    $this->actingAs($this->owner)->post(route('parties.store'), partyPayload())
        ->assertRedirect(route('parties.index'))->assertSessionHas('success', 'Party Ramesh Yadav added.');

    $party = Party::sole();
    expect($party)->farm_id->toBe($this->farm->id)->phone->toBe('9876543210')->party_type->toBe(PartyType::Seller)
        ->opening_balance->toBe('-12500.50')->created_by->toBe($this->owner->id);

    $this->put(route('parties.update', $party), partyPayload(['party_type' => 'both', 'opening_balance' => '', 'is_active' => '0']))
        ->assertSessionHasNoErrors();
    expect($party->fresh())->party_type->toBe(PartyType::Both)->opening_balance->toBe('0.00')->is_active->toBeFalse();
});

it('validates: same name allowed in another village, not twice in one; type and phone', function () {
    $this->actingAs($this->owner);
    Party::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Ramesh Yadav', 'village' => 'Wadgaon']);

    $this->post(route('parties.store'), partyPayload())->assertSessionHasErrors('name');
    $this->post(route('parties.store'), partyPayload(['village' => 'Shirur']))->assertSessionHasNoErrors();
    $this->post(route('parties.store'), partyPayload(['name' => 'X', 'party_type' => 'trader', 'phone' => '12345']))
        ->assertSessionHasErrors(['party_type', 'phone']);
});

it('lists with search / type / status filters and a flat query count', function () {
    Party::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Ramesh Yadav', 'village' => 'Wadgaon', 'party_type' => 'seller']);
    Party::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Suresh Patil', 'village' => 'Loni', 'party_type' => 'buyer', 'is_active' => false]);
    Party::factory()->create(['name' => 'Other Farm Party']); // other farm

    $this->actingAs($this->accountant)->get(route('parties.index'))->assertOk()
        ->assertSee('Ramesh Yadav')->assertSee('Suresh Patil')->assertDontSee('Other Farm Party')->assertDontSee('Add party');
    $this->get(route('parties.index', ['search' => 'loni']))->assertSee('Suresh Patil')->assertDontSee('Ramesh Yadav');
    $this->get(route('parties.index', ['type' => 'seller']))->assertSee('Ramesh Yadav')->assertDontSee('Suresh Patil');
    $this->get(route('parties.index', ['status' => 'inactive']))->assertSee('Suresh Patil')->assertDontSee('Ramesh Yadav');

    $count = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('parties.index'))->assertOk();

        return count(DB::getQueryLog());
    };
    $few = $count();
    Party::factory()->count(20)->create(['farm_id' => $this->farm->id]);
    expect($count())->toBe($few);
});

it('lets only animal_trading.manage change parties; delete is soft', function () {
    $party = Party::factory()->create(['farm_id' => $this->farm->id]);

    $this->actingAs($this->accountant);
    $this->get(route('parties.create'))->assertForbidden();
    $this->post(route('parties.store'), partyPayload())->assertForbidden();
    $this->delete(route('parties.destroy', $party))->assertForbidden();
    $herd = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $this->actingAs($herd)->get(route('parties.index'))->assertForbidden();

    $this->actingAs($this->owner)->delete(route('parties.destroy', $party))->assertRedirect(route('parties.index'));
    expect(Party::count())->toBe(0)->and(Party::withTrashed()->count())->toBe(1);
});
