<?php

use App\Enums\MilkSource;
use App\Enums\MilkType;
use App\Enums\QualityStatus;
use App\Enums\RoleName;
use App\Models\Activity;
use App\Models\Farm;
use App\Models\Farmer;
use App\Models\MilkCollection;
use App\Models\RateChart;
use App\Models\User;
use App\Services\CollectionService;
use App\Services\MilkStockService;
use App\Support\CurrentFarm;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->travelTo('2026-11-09 07:30');
    $this->farm = Farm::factory()->create();
    app(CurrentFarm::class)->set($this->farm);
    $this->operator = User::factory()->forFarm($this->farm)->withRole(RoleName::CollectionOperator)->create();
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->chart = RateChart::factory()->create(['farm_id' => $this->farm->id]); // cow fixed ₹40
    $this->farmer = Farmer::factory()->create(['farm_id' => $this->farm->id, 'code' => 'F-007']);

    $this->actingAs($this->operator);
    [$this->entry] = app(CollectionService::class)->create(['collection_date' => '2026-11-09', 'shift' => 'morning',
        'farmer_id' => $this->farmer->id, 'milk_type' => 'cow', 'quantity_litre' => '20']);
    $this->stock = fn () => app(MilkStockService::class)->balance(MilkSource::Collection, MilkType::Cow);
});

function changeEntry(MilkCollection $entry, array $overrides = []): TestResponse
{
    return test()->put(route('collections.update', $entry), array_merge([
        'collection_date' => '2026-11-09', 'shift' => 'morning', 'milk_type' => 'cow', 'quantity_litre' => '12', 'reason' => 'Typed 20, was 12',
    ], $overrides));
}

it('lets the operator change their own entry the same day, with a reason; stock and amount follow', function () {
    changeEntry($this->entry, ['reason' => ''])->assertSessionHasErrors('reason');
    changeEntry($this->entry)->assertSessionHasNoErrors()->assertRedirect();

    expect($this->entry->fresh())->quantity_litre->toBe('12.00')->amount->toBe('480.00')
        ->and(($this->stock)())->toBe('12.00')
        ->and(Activity::where('subject_type', $this->entry->getMorphClass())->where('event', 'updated')->sole()->reason())->toBe('Typed 20, was 12');

    changeEntry($this->entry, ['quality_status' => 'rejected', 'rejection_reason' => 'Sour'])->assertSessionHasNoErrors();
    expect($this->entry->fresh())->amount->toBe('0.00')->quality_status->toBe(QualityStatus::Rejected)->and(($this->stock)())->toBe('0.00');
});

it('prices an edited entry from the chart in force on its date', function () {
    $this->chart->update(['effective_to' => '2026-11-09']);
    RateChart::factory()->create(['farm_id' => $this->farm->id, 'name' => 'New', 'fixed_rate' => '42.00', 'effective_from' => '2026-11-10']);
    $this->travelTo('2026-11-10 07:30');
    $this->actingAs($this->owner);

    changeEntry($this->entry, ['quantity_litre' => '20'])->assertSessionHasNoErrors();
    expect($this->entry->fresh()->amount)->toBe('800.00'); // still 9 Nov → old chart

    changeEntry($this->entry, ['collection_date' => '2026-11-10', 'quantity_litre' => '20'])->assertSessionHasNoErrors();
    expect($this->entry->fresh()->amount)->toBe('840.00');
});

it('stops operators on another day, refuses a duplicate shift, deletes with a reason and removes the stock', function () {
    $other = app(CollectionService::class)->create(['collection_date' => '2026-11-09', 'shift' => 'evening',
        'farmer_id' => $this->farmer->id, 'milk_type' => 'cow', 'quantity_litre' => '5'])[0];
    changeEntry($other, ['shift' => 'morning'])->assertSessionHas('error');

    $this->travelTo('2026-11-10 08:00');
    $this->get(route('collections.edit', $this->entry))->assertForbidden();
    changeEntry($this->entry)->assertForbidden();
    $this->delete(route('collections.destroy', $this->entry), ['reason' => 'Wrong'])->assertForbidden();

    $this->actingAs($this->owner)->delete(route('collections.destroy', $this->entry))->assertSessionHasErrors('reason');
    $this->delete(route('collections.destroy', $this->entry), ['reason' => 'Farmer did not come'])->assertSessionHasNoErrors();
    expect(MilkCollection::find($this->entry->id))->toBeNull()->and(($this->stock)())->toBe('5.00');
});

it('locks an entry that is in a farmer payment statement, for everyone (spec acceptance)', function () {
    DB::table('milk_collections')->where('id', $this->entry->id)->update(['farmer_payment_id' => 1]);
    $locked = $this->entry->fresh();

    $this->actingAs($this->owner);
    $this->get(route('collections.edit', $locked))->assertForbidden();
    changeEntry($locked)->assertForbidden();
    $this->delete(route('collections.destroy', $locked), ['reason' => 'x x x'])->assertForbidden();
    expect(fn () => app(CollectionService::class)->delete($locked))->toThrow(DomainException::class);

    $this->get(route('collections.index'))->assertOk()->assertSee('bi-lock-fill', false);
});

it('lists collections with filters and totals of the whole set', function () {
    $bala = Farmer::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Bala More']);
    app(CollectionService::class)->create(['collection_date' => '2026-11-08', 'shift' => 'evening', 'farmer_id' => $bala->id,
        'milk_type' => 'cow', 'quantity_litre' => '8', 'quality_status' => 'rejected', 'rejection_reason' => 'Water']);

    $this->get(route('collections.list'))->assertOk()->assertSee('Bala More')
        ->assertViewHas('totals', fn ($t) => (int) $t->cnt === 2 && (string) $t->litres === '20.00' && (string) $t->rejected === '8.00' && (string) $t->amount === '800.00');
    $this->get(route('collections.list', ['status' => 'rejected']))->assertSee('Bala More')->assertDontSee('F-007');
    $this->get(route('collections.list', ['farmer_id' => $this->farmer->id]))->assertDontSee('Bala More');
    $this->get(route('collections.list', ['from' => '2026-11-09']))->assertDontSee('Bala More');
});
