<?php

use App\Enums\RoleName;
use App\Models\Farm;
use App\Models\User;
use App\Models\Vendor;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
});

function vendorPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Sharma Feeds',
        'phone' => '+91 98765 43210',
        'address' => 'Mandi Road, Mathura',
        'gstin' => '09abcde1234f1z5',
        'opening_balance' => '12,500.50',
        'notes' => 'Cattle feed supplier',
        'is_active' => '1',
    ], $overrides);
}

it('creates a vendor with normalised phone, GSTIN and opening balance', function () {
    $this->actingAs($this->accountant)->post(route('vendors.store'), vendorPayload())
        ->assertRedirect(route('vendors.index'))
        ->assertSessionHas('success');

    $vendor = Vendor::firstOrFail();

    expect($vendor->farm_id)->toBe($this->farm->id)
        ->and($vendor->phone)->toBe('9876543210')
        ->and($vendor->gstin)->toBe('09ABCDE1234F1Z5')
        ->and($vendor->opening_balance)->toBe('12500.50')
        ->and($vendor->created_by)->toBe($this->accountant->id);
});

it('accepts a negative opening balance (advance paid) and defaults it to zero', function () {
    $this->actingAs($this->accountant)->post(route('vendors.store'), vendorPayload(['name' => 'A', 'opening_balance' => '-2000']));
    $this->actingAs($this->accountant)->post(route('vendors.store'), vendorPayload(['name' => 'B', 'opening_balance' => '', 'phone' => '']));

    expect(Vendor::where('name', 'A')->value('opening_balance'))->toBe('-2000.00')
        ->and(Vendor::where('name', 'B')->value('opening_balance'))->toBe('0.00');
});

it('validates name uniqueness, phone, GSTIN and amount', function () {
    Vendor::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Sharma Feeds']);
    Vendor::factory()->create(['farm_id' => Farm::factory()->create()->id, 'name' => 'Gupta Medical']);

    $this->actingAs($this->accountant)
        ->post(route('vendors.store'), vendorPayload(['phone' => '12345', 'gstin' => 'BAD', 'opening_balance' => '10.555']))
        ->assertSessionHasErrors(['name', 'phone', 'gstin', 'opening_balance']);

    // the same name in another farm is fine
    $this->actingAs($this->accountant)->post(route('vendors.store'), vendorPayload(['name' => 'Gupta Medical']))
        ->assertSessionDoesntHaveErrors();
});

it('lists, searches and filters vendors of the current farm only, paginated', function () {
    Vendor::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Sharma Feeds', 'phone' => '9876543210']);
    Vendor::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Old Diesel Pump', 'is_active' => false]);
    Vendor::factory()->create(['farm_id' => Farm::factory()->create()->id, 'name' => 'Foreign Vendor']);

    $this->actingAs($this->accountant)->get(route('vendors.index'))
        ->assertOk()->assertSee('Sharma Feeds')->assertSee('Old Diesel Pump')->assertDontSee('Foreign Vendor');

    $this->actingAs($this->accountant)->get(route('vendors.index', ['search' => '43210']))
        ->assertSee('Sharma Feeds')->assertDontSee('Old Diesel Pump');

    $this->actingAs($this->accountant)->get(route('vendors.index', ['status' => 'inactive']))
        ->assertSee('Old Diesel Pump')->assertDontSee('Sharma Feeds');

    Vendor::factory()->count(30)->create(['farm_id' => $this->farm->id]);
    $this->actingAs($this->accountant)->withHeaders(['X-Ajax-List' => '1'])->get(route('vendors.index'))
        ->assertHeader('X-Ajax-List-Fragment', '1')
        ->assertSee('page=2', false);
});

it('updates and soft-deletes a vendor', function () {
    $vendor = Vendor::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Old Name']);

    $this->actingAs($this->accountant)->get(route('vendors.edit', $vendor))->assertOk()->assertSee('value="Old Name"', false);

    $this->actingAs($this->accountant)
        ->put(route('vendors.update', $vendor), vendorPayload(['name' => 'New Name', 'is_active' => '0']))
        ->assertRedirect(route('vendors.index'));

    expect($vendor->fresh()->name)->toBe('New Name')->and($vendor->fresh()->is_active)->toBeFalse();

    $this->actingAs($this->accountant)->delete(route('vendors.destroy', $vendor))->assertRedirect(route('vendors.index'));
    expect(Vendor::count())->toBe(0)->and(Vendor::withTrashed()->count())->toBe(1);

    // a deleted vendor's name can be used again
    $this->actingAs($this->accountant)->post(route('vendors.store'), vendorPayload(['name' => 'New Name']))
        ->assertSessionDoesntHaveErrors();
});

it('serves Select2 search results: active vendors of this farm, 20 per page', function () {
    Vendor::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Sharma Feeds', 'phone' => '9876543210']);
    Vendor::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Sharma Old', 'is_active' => false]);
    Vendor::factory()->create(['farm_id' => Farm::factory()->create()->id, 'name' => 'Sharma Foreign']);
    Vendor::factory()->count(25)->sequence(fn ($s) => ['name' => 'Bulk Vendor '.str_pad($s->index, 2, '0', STR_PAD_LEFT)])
        ->create(['farm_id' => $this->farm->id]);

    $this->actingAs($this->accountant)->getJson(route('vendors.search', ['q' => 'sharma']))
        ->assertOk()
        ->assertJsonPath('results.0.text', 'Sharma Feeds · 9876543210')
        ->assertJsonCount(1, 'results')
        ->assertJsonPath('pagination.more', false);

    $this->actingAs($this->accountant)->getJson(route('vendors.search', ['q' => 'bulk']))
        ->assertJsonCount(20, 'results')->assertJsonPath('pagination.more', true);
    $this->actingAs($this->accountant)->getJson(route('vendors.search', ['q' => 'bulk', 'page' => 2]))
        ->assertJsonCount(5, 'results')->assertJsonPath('pagination.more', false);
});

it('enforces vendors.view / vendors.manage and farm isolation', function () {
    $herdStaff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $vendor = Vendor::factory()->create(['farm_id' => $this->farm->id]);
    $foreign = Vendor::factory()->create(['farm_id' => Farm::factory()->create()->id]);

    $this->actingAs($herdStaff)->get(route('vendors.index'))->assertForbidden();
    $this->actingAs($herdStaff)->post(route('vendors.store'), vendorPayload())->assertForbidden();
    $this->actingAs($herdStaff)->delete(route('vendors.destroy', $vendor))->assertForbidden();

    $this->actingAs($this->accountant)->get(route('vendors.edit', $foreign))->assertNotFound();
});
