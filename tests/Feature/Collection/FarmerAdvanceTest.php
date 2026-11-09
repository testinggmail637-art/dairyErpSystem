<?php

use App\Enums\RoleName;
use App\Models\Activity;
use App\Models\Farm;
use App\Models\Farmer;
use App\Models\FarmerAdvance;
use App\Models\PaymentMode;
use App\Models\User;
use App\Services\FarmerAdvanceService;
use App\Support\CurrentFarm;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->travelTo('2026-11-09 10:00');
    $this->farm = Farm::factory()->create();
    app(CurrentFarm::class)->set($this->farm);
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
    $this->actingAs($this->accountant);
    $this->cash = PaymentMode::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Cash']);
    $this->farmer = Farmer::factory()->create(['farm_id' => $this->farm->id, 'code' => 'F-007', 'name' => 'Asha Pawar']);
});

function advance(array $overrides = []): TestResponse
{
    return test()->post(route('farmer-advances.store'), array_merge([
        'farmer_id' => test()->farmer->id, 'advance_date' => '2026-11-05', 'amount' => '₹ 1,500', 'payment_mode_id' => test()->cash->id,
        'note' => 'Cattle feed', 'client_uuid' => (string) Str::uuid(),
    ], $overrides));
}

it('gives advances once per submit and shows the outstanding advance on the farmer', function () {
    $uuid = (string) Str::uuid();
    advance(['client_uuid' => $uuid])->assertSessionHasNoErrors()->assertSessionHas('success');
    advance(['client_uuid' => $uuid])->assertSessionHas('success', 'This advance was already saved.');
    advance(['amount' => '500', 'advance_date' => '2026-11-08'])->assertSessionHasNoErrors();

    expect(FarmerAdvance::count())->toBe(2)
        ->and(FarmerAdvance::where('amount', '1500.00')->sole()->payment_mode_id)->toBe($this->cash->id)
        ->and(app(FarmerAdvanceService::class)->outstanding($this->farmer->id))->toBe('2000.00');

    $this->get(route('farmers.show', $this->farmer))->assertOk()->assertSee('Outstanding')->assertSee('₹2,000.00')->assertSee('Cattle feed');
});

it('validates the advance', function (array $input, string $field) {
    advance($input)->assertSessionHasErrors($field);
    expect(FarmerAdvance::count())->toBe(0);
})->with([
    'no farmer' => [['farmer_id' => ''], 'farmer_id'],
    'future date' => [['advance_date' => '2026-11-10'], 'advance_date'],
    'zero' => [['amount' => '0'], 'amount'],
    'no mode' => [['payment_mode_id' => ''], 'payment_mode_id'],
]);

it('lists advances of a period with the total, filters by farmer, deletes with a reason', function () {
    $other = Farmer::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Bala More']);
    advance()->assertSessionHasNoErrors();
    advance(['farmer_id' => $other->id, 'amount' => '700'])->assertSessionHasNoErrors();
    advance(['advance_date' => '2026-10-20', 'amount' => '300'])->assertSessionHasNoErrors(); // last month

    $this->get(route('farmer-advances.index'))->assertOk()->assertSee('Bala More')
        ->assertViewHas('total', '2200.00')->assertViewHas('count', 2);
    $this->get(route('farmer-advances.index', ['farmer_id' => $other->id]))->assertViewHas('total', '700.00')->assertDontSee('Asha Pawar</a>', false);

    $first = FarmerAdvance::where('amount', '1500.00')->sole();
    $this->delete(route('farmer-advances.destroy', $first))->assertSessionHasErrors('reason');
    $this->delete(route('farmer-advances.destroy', $first), ['reason' => 'Given twice'])->assertSessionHasNoErrors();
    expect(FarmerAdvance::find($first->id))->toBeNull()
        ->and(Activity::where('subject_type', $first->getMorphClass())->where('event', 'deleted')->sole()->reason())->toBe('Given twice');
});

it('is only for farmer_advances.manage; another farm\'s farmer is refused', function () {
    advance(['farmer_id' => Farmer::factory()->create(['farm_id' => Farm::factory()->create()->id])->id])->assertSessionHasErrors('farmer_id');

    $operator = User::factory()->forFarm($this->farm)->withRole(RoleName::CollectionOperator)->create();
    $this->actingAs($operator)->get(route('farmer-advances.index'))->assertForbidden();
    advance()->assertForbidden();
    $this->get(route('farmers.show', $this->farmer))->assertOk()->assertDontSee('Give advance')->assertDontSee('Save advance');
});
