<?php

use App\Enums\MilkType;
use App\Enums\RoleName;
use App\Models\Activity;
use App\Models\Farm;
use App\Models\Farmer;
use App\Models\User;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->operator = User::factory()->forFarm($this->farm)->withRole(RoleName::CollectionOperator)->create();
});

function farmerPayload(array $overrides = []): array
{
    return array_merge([
        'name' => '  Ramesh   Patil ', 'father_name' => 'Ganpat', 'phone' => '+91 98765 43210', 'village' => 'Wadgaon',
        'default_milk_type' => 'buffalo', 'bank_account_name' => 'Ramesh G Patil', 'bank_account_no' => '1234 5678 9012',
        'ifsc' => 'sbin0001234', 'upi_id' => 'Ramesh@UPI', 'opening_balance' => '₹ 1,500', 'is_active' => '1',
    ], $overrides);
}

it('adds farmers with auto codes F-001, F-002 per farm, never reusing a deleted code', function () {
    $this->actingAs($this->owner)->post(route('farmers.store'), farmerPayload())->assertSessionHasNoErrors();

    $first = Farmer::sole();
    expect($first)->code->toBe('F-001')->name->toBe('Ramesh Patil')->phone->toBe('9876543210')
        ->default_milk_type->toBe(MilkType::Buffalo)->bank_account_no->toBe('123456789012')->ifsc->toBe('SBIN0001234')
        ->upi_id->toBe('ramesh@upi')->opening_balance->toBe('1500.00')->farm_id->toBe($this->farm->id);

    $first->delete();
    $this->post(route('farmers.store'), farmerPayload(['name' => 'Sunita', 'bank_account_no' => '', 'ifsc' => '']))->assertSessionHasNoErrors();
    expect(Farmer::where('name', 'Sunita')->value('code'))->toBe('F-002');

    Farmer::factory()->create(['farm_id' => Farm::factory()->create()->id, 'code' => 'F-099']);
    $this->post(route('farmers.store'), farmerPayload(['name' => 'Third']))->assertSessionHasNoErrors();
    expect(Farmer::where('name', 'Third')->value('code'))->toBe('F-003');
});

it('validates the farmer form', function (array $input, string $field) {
    $this->actingAs($this->owner)->post(route('farmers.store'), farmerPayload($input))->assertSessionHasErrors($field);
    expect(Farmer::count())->toBe(0);
})->with([
    'name missing' => [['name' => ' '], 'name'],
    'bad milk type' => [['default_milk_type' => 'goat'], 'default_milk_type'],
    'short phone' => [['phone' => '98765'], 'phone'],
    'short account' => [['bank_account_no' => '1234'], 'bank_account_no'],
    'account without ifsc' => [['ifsc' => ''], 'ifsc'],
    'bad ifsc' => [['ifsc' => 'SBIN1234'], 'ifsc'],
    'bad upi' => [['upi_id' => 'ramesh'], 'upi_id'],
]);

it('edits with a reason in the audit log and deletes softly with a reason', function () {
    $farmer = Farmer::factory()->create(['farm_id' => $this->farm->id]);

    $this->actingAs($this->owner)->put(route('farmers.update', $farmer), farmerPayload())->assertSessionHasErrors('reason');
    $this->put(route('farmers.update', $farmer), farmerPayload(['reason' => 'New bank account']))->assertRedirect(route('farmers.show', $farmer));
    expect($farmer->fresh()->ifsc)->toBe('SBIN0001234')
        ->and(Activity::where('subject_type', $farmer->getMorphClass())->where('event', 'updated')->sole()->reason())->toBe('New bank account');

    $this->delete(route('farmers.destroy', $farmer))->assertSessionHasErrors('reason');
    $this->delete(route('farmers.destroy', $farmer), ['reason' => 'Stopped selling'])->assertRedirect(route('farmers.index'));
    expect($farmer->fresh()->trashed())->toBeTrue();
});

it('lists, filters and searches farmers of this farm (code 7 finds F-007)', function () {
    Farmer::factory()->create(['farm_id' => $this->farm->id, 'code' => 'F-007', 'name' => 'Asha Pawar', 'village' => 'Wadgaon']);
    Farmer::factory()->create(['farm_id' => $this->farm->id, 'code' => 'F-008', 'name' => 'Bala More', 'default_milk_type' => 'buffalo', 'is_active' => false]);
    Farmer::factory()->create(['farm_id' => Farm::factory()->create()->id, 'name' => 'Foreign Farmer']);

    $this->actingAs($this->operator)->get(route('farmers.index'))->assertOk()
        ->assertSee('Asha Pawar')->assertSee('Bala More')->assertDontSee('Foreign Farmer')->assertDontSee(route('farmers.create'), false);
    $this->get(route('farmers.index', ['milk_type' => 'buffalo']))->assertSee('Bala More')->assertDontSee('Asha Pawar');
    $this->get(route('farmers.index', ['status' => 'active']))->assertSee('Asha Pawar')->assertDontSee('Bala More');
    $this->get(route('farmers.index', ['search' => '7']))->assertSee('Asha Pawar')->assertDontSee('Bala More');

    $this->getJson(route('farmers.search', ['q' => 'wad']))->assertOk()
        ->assertJsonPath('results.0.text', 'F-007 · Asha Pawar · Wadgaon')->assertJsonPath('results.0.milk_type', 'cow')
        ->assertJsonCount(1, 'results'); // inactive Bala not offered
});

it('lets only farmers.manage change farmers; others get 403; another farm\'s farmer is 404', function () {
    $farmer = Farmer::factory()->create(['farm_id' => $this->farm->id]);
    $foreign = Farmer::factory()->create(['farm_id' => Farm::factory()->create()->id]);

    $this->actingAs($this->operator)->get(route('farmers.show', $farmer))->assertOk();
    $this->get(route('farmers.create'))->assertForbidden();
    $this->post(route('farmers.store'), farmerPayload())->assertForbidden();
    $this->get(route('farmers.edit', $farmer))->assertForbidden();

    $deliveryBoy = User::factory()->forFarm($this->farm)->withRole(RoleName::DeliveryBoy)->create();
    $this->actingAs($deliveryBoy)->get(route('farmers.index'))->assertForbidden();

    $this->actingAs($this->owner)->get(route('farmers.show', $foreign))->assertNotFound();
});
