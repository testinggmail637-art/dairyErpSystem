<?php

use App\Enums\AnimalStatus;
use App\Enums\LifecycleStage;
use App\Enums\RoleName;
use App\Models\Activity;
use App\Models\Animal;
use App\Models\Farm;
use App\Models\MilkProductionRecord;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function () {
    $this->travelTo('2026-10-19 08:00'); // morning
    $this->farm = Farm::factory()->create();
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->herdStaff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();

    $make = fn (string $tag, array $extra = []) => Animal::factory()->create(['farm_id' => $this->farm->id, 'tag_no' => $tag, 'name' => null] + $extra);
    $this->a = $make('C-101');
    $this->b = $make('C-102');
    $this->c = Animal::factory()->buffalo()->create(['farm_id' => $this->farm->id, 'tag_no' => 'B-201', 'name' => null]);
    $this->dry = $make('C-DRY', ['lifecycle_stage' => LifecycleStage::Dry]);
    $this->sold = $make('C-SOLD', ['status' => AnimalStatus::Sold]);
    $this->bull = Animal::factory()->male()->create(['farm_id' => $this->farm->id, 'tag_no' => 'BULL-1']);
});

function saveGrid(string $shift, array $qty, array $extra = [], string $date = '2026-10-19')
{
    return test()->post(route('milk-production.bulk'), ['production_date' => $date, 'shift' => $shift, 'qty' => $qty] + $extra);
}

it('shows only active milking females in the grid, morning before 2 pm', function () {
    $this->actingAs($this->herdStaff)->get(route('milk-production.index'))->assertOk()
        ->assertSee('C-101')->assertSee('C-102')->assertSee('B-201')
        ->assertDontSee('C-DRY')->assertDontSee('C-SOLD')->assertDontSee('BULL-1')
        ->assertSee('name="shift" value="morning"', false);

    $this->travelTo('2026-10-19 17:00');
    $this->get(route('milk-production.index'))->assertSee('name="shift" value="evening"', false);
});

it('saves 3 animals morning and evening in bulk: 8+6, 10+8, 6+5 = 43 L (Phase 2 acceptance)', function () {
    $this->actingAs($this->herdStaff);

    saveGrid('morning', [$this->a->id => '8', $this->b->id => '10', $this->c->id => '6', $this->dry->id => ''])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('milk-production.index', ['date' => '2026-10-19', 'shift' => 'morning']))
        ->assertSessionHas('success', 'Morning milk of 19-10-2026 saved: 3 new, 0 changed.');
    saveGrid('evening', [$this->a->id => '6', $this->b->id => '8', $this->c->id => '5.00'])->assertSessionHasNoErrors();

    expect(MilkProductionRecord::count())->toBe(6)
        ->and(MilkProductionRecord::sum('quantity_litre'))->toEqual('43.00')
        ->and(MilkProductionRecord::where('animal_id', $this->a->id)->where('shift', 'morning')->value('created_by'))->toBe($this->herdStaff->id);

    $this->get(route('milk-production.entries', ['from' => '2026-10-19', 'to' => '2026-10-19']))->assertOk()
        ->assertSee('Cow: <span class="fw-semibold">32.00 L</span>', false)
        ->assertSee('Buffalo: <span class="fw-semibold">11.00 L</span>', false)
        ->assertSee('Total: <span class="fw-semibold">43.00 L</span>', false);

    // the grid shows the saved litres again, ready to edit
    $this->get(route('milk-production.index', ['date' => '2026-10-19', 'shift' => 'evening']))
        ->assertSee('name="qty['.$this->b->id.']" value="8.00"', false);
});

it('rejects a duplicate animal / date / shift at database level', function () {
    MilkProductionRecord::factory()->for($this->a)->create(['production_date' => '2026-10-19', 'shift' => 'morning']);

    expect(fn () => MilkProductionRecord::factory()->for($this->a)->create(['production_date' => '2026-10-19', 'shift' => 'morning']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('leaves unchanged entries alone and asks for a reason when saved litres change', function () {
    $this->actingAs($this->owner);
    saveGrid('morning', [$this->a->id => '8', $this->b->id => '10']);

    saveGrid('morning', [$this->a->id => '8.00', $this->b->id => '10'])->assertSessionHas('success', 'Morning milk of 19-10-2026 saved: 0 new, 0 changed.');

    saveGrid('morning', [$this->a->id => '7'])
        ->assertSessionHasErrors(['reason' => 'You changed saved entries — write a reason (it goes to the audit log).']);
    expect(MilkProductionRecord::where('animal_id', $this->a->id)->value('quantity_litre'))->toBe('8.00');

    saveGrid('morning', [$this->a->id => '7', $this->c->id => '4'], ['reason' => 'Typed 8 by mistake'])
        ->assertSessionHas('success', 'Morning milk of 19-10-2026 saved: 1 new, 1 changed.');

    $record = MilkProductionRecord::where('animal_id', $this->a->id)->sole();
    expect($record->quantity_litre)->toBe('7.00');
    $log = Activity::where('subject_type', $record->getMorphClass())->where('subject_id', $record->id)->where('event', 'updated')->sole();
    expect($log->reason())->toBe('Typed 8 by mistake');
});

it('validates the grid', function (array $qty, string $errorKey, string $message) {
    $this->actingAs($this->owner);
    $qty = collect($qty)->mapWithKeys(fn ($v, $k) => [test()->{$k}->id => $v])->all();
    $errorKey = $errorKey === 'row' ? 'qty.'.array_key_first($qty) : $errorKey; // errors sit on the animal's row

    saveGrid('morning', $qty)->assertSessionHasErrors([$errorKey => $message]);
    expect(MilkProductionRecord::count())->toBe(0);
})->with([
    'over 60 L' => [['a' => '61'], 'row', 'More than 60 litres in one shift is not accepted.'],
    'negative' => [['a' => '-1'], 'row', 'Litres cannot be negative.'],
    'three decimals' => [['a' => '8.125'], 'row', 'Enter litres with up to 2 decimals.'],
    'dry animal' => [['dry' => '5'], 'row', 'This animal is not in milk entry (not an active milking female of this farm).'],
    'bull' => [['bull' => '5'], 'row', 'This animal is not in milk entry (not an active milking female of this farm).'],
    'all empty' => [['a' => ''], 'qty', 'Enter the litres of at least one animal.'],
]);

it('refuses a future date and animals of another farm', function () {
    $this->actingAs($this->owner);
    saveGrid('morning', [$this->a->id => '8'], [], '2026-10-20')->assertSessionHasErrors(['production_date' => 'Milk cannot be entered for a future date.']);

    $foreign = Animal::factory()->create(['farm_id' => Farm::factory()->create()->id]);
    saveGrid('morning', [$foreign->id => '8'])->assertSessionHasErrors("qty.{$foreign->id}");
    expect(MilkProductionRecord::allFarms()->count())->toBe(0);
});

it('keeps a saved entry editable in the grid after the animal is dried off', function () {
    $this->actingAs($this->owner);
    saveGrid('morning', [$this->a->id => '8']);
    $this->a->update(['lifecycle_stage' => LifecycleStage::Dry]);

    $this->get(route('milk-production.index', ['date' => '2026-10-19', 'shift' => 'morning']))->assertSee('C-101')->assertSee('Dry');
    saveGrid('morning', [$this->a->id => '7.5'], ['reason' => 'Correction'])->assertSessionHasNoErrors();
    expect(MilkProductionRecord::sole()->quantity_litre)->toBe('7.50');
});

it('lets herd staff change only their own entries of today (spec 4.5)', function () {
    $this->actingAs($this->owner);
    saveGrid('morning', [$this->a->id => '8']);              // owner's entry

    $this->flushSession();
    $this->actingAs($this->herdStaff);
    saveGrid('morning', [$this->b->id => '10']);            // staff's own entry
    saveGrid('morning', [$this->a->id => '9'], ['reason' => 'Try'])
        ->assertSessionHasErrors(["qty.{$this->a->id}" => 'You can only change your own entries, on the day you made them.']);
    saveGrid('morning', [$this->b->id => '11'], ['reason' => 'My typo'])->assertSessionHasNoErrors();

    $this->travelTo('2026-10-20 08:00');
    saveGrid('morning', [$this->b->id => '12'], ['reason' => 'Next day'], '2026-10-19')->assertSessionHasErrors("qty.{$this->b->id}");
    expect(MilkProductionRecord::where('animal_id', $this->b->id)->value('quantity_litre'))->toBe('11.00');
});

it('shows the last 3 entries of the shift as a hint', function () {
    foreach (['2026-10-14' => '7.00', '2026-10-16' => '8.00', '2026-10-17' => '9.00', '2026-10-18' => '9.50'] as $date => $litres) {
        MilkProductionRecord::factory()->for($this->a)->create(['production_date' => $date, 'shift' => 'morning', 'quantity_litre' => $litres]);
    }
    MilkProductionRecord::factory()->for($this->a)->create(['production_date' => '2026-10-18', 'shift' => 'evening', 'quantity_litre' => '3.33']);

    $html = $this->actingAs($this->owner)->get(route('milk-production.index', ['date' => '2026-10-19', 'shift' => 'morning']))->getContent();
    expect($html)->toContain('title="18-10-2026">9.50')->toContain('title="17-10-2026">9.00')->toContain('title="16-10-2026">8.00')
        ->not->toContain('title="14-10-2026"')->not->toContain('3.33');
});

it('adds a single entry with fat / SNF, idempotent on client_uuid', function () {
    $this->actingAs($this->herdStaff);
    $uuid = (string) Str::uuid();
    $data = ['production_date' => '2026-10-19', 'shift' => 'evening', 'animal_id' => $this->a->id, 'quantity_litre' => '7.25',
        'fat' => '4.2', 'snf' => '8.5', 'client_uuid' => $uuid];

    $this->post(route('milk-production.store'), $data)->assertSessionHasNoErrors()->assertSessionHas('success', 'Milk entry saved: 7.25 L.');
    $this->post(route('milk-production.store'), $data)->assertSessionHas('success', 'This entry was already saved.');

    expect(MilkProductionRecord::count())->toBe(1)
        ->and(MilkProductionRecord::sole()->only(['quantity_litre', 'fat', 'snf', 'client_uuid']))
        ->toBe(['quantity_litre' => '7.25', 'fat' => '4.20', 'snf' => '8.50', 'client_uuid' => $uuid]);

    $this->post(route('milk-production.store'), ['client_uuid' => (string) Str::uuid()] + $data)
        ->assertSessionHasErrors(['animal_id' => 'This animal already has an entry for this date and shift — edit that entry instead.']);
    $this->post(route('milk-production.store'), ['animal_id' => $this->dry->id, 'client_uuid' => null] + $data)
        ->assertSessionHasErrors(['animal_id' => 'Choose an active milking female of this farm.']);
});

it('edits and deletes a single entry with a reason', function () {
    $this->actingAs($this->owner);
    $record = MilkProductionRecord::factory()->for($this->a)->create(['production_date' => '2026-10-19', 'shift' => 'morning', 'quantity_litre' => '10.00']);
    $data = ['production_date' => '2026-10-19', 'shift' => 'morning', 'animal_id' => $this->a->id, 'quantity_litre' => '7'];

    $this->put(route('milk-production.update', $record), $data)->assertSessionHasErrors('reason');
    $this->put(route('milk-production.update', $record), $data + ['reason' => 'Wrong litres'])->assertSessionHasNoErrors();
    expect($record->fresh()->quantity_litre)->toBe('7.00');

    $this->delete(route('milk-production.destroy', $record))->assertSessionHasErrors('reason');
    $this->delete(route('milk-production.destroy', $record), ['reason' => 'Entered for the wrong cow'])->assertSessionHas('success');
    expect(MilkProductionRecord::count())->toBe(0);
});

it('filters the entries list over AJAX', function () {
    MilkProductionRecord::factory()->for($this->a)->create(['production_date' => '2026-10-18', 'shift' => 'morning', 'quantity_litre' => '8.00']);
    MilkProductionRecord::factory()->for($this->c)->create(['production_date' => '2026-10-19', 'shift' => 'evening', 'quantity_litre' => '6.00']);

    $ajax = fn (array $q) => $this->actingAs($this->owner)->get(route('milk-production.entries', $q), ['X-Ajax-List' => '1'])
        ->assertOk()->assertHeader('X-Ajax-List-Fragment', '1');

    $ajax(['from' => '2026-10-01'])->assertSee('C-101')->assertSee('B-201');
    $ajax(['from' => '2026-10-01', 'type' => 'buffalo'])->assertSee('B-201')->assertDontSee('C-101');
    $ajax(['from' => '2026-10-01', 'shift' => 'morning'])->assertSee('C-101')->assertDontSee('B-201');
    $ajax(['from' => '2026-10-19'])->assertDontSee('C-101');
    $ajax(['from' => '2026-10-01', 'animal_id' => $this->a->id])->assertSee('C-101')->assertDontSee('B-201');
});

it('shows the animal\'s milk history with a 7-day average on its profile', function () {
    foreach (range(1, 7) as $d) {
        $date = today()->subDays($d)->toDateString();
        MilkProductionRecord::factory()->for($this->a)->create(['production_date' => $date, 'shift' => 'morning', 'quantity_litre' => '6.00']);
        MilkProductionRecord::factory()->for($this->a)->create(['production_date' => $date, 'shift' => 'evening', 'quantity_litre' => '4.00']);
    }

    $this->actingAs($this->owner)->get(route('animals.show', $this->a))->assertOk()
        ->assertSee('Average per day (last 7 days with milk): <span class="fw-semibold">10.00 L</span>', false)
        ->assertSee('id="animal-milk-chart"', false)
        ->assertSee('vendor/chartjs/chart.umd.min.js', false);
});

it('needs milk permissions and stays inside the farm', function () {
    $record = MilkProductionRecord::factory()->for($this->a)->create();
    $accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create(); // no milk rights

    $this->actingAs($accountant)->get(route('milk-production.index'))->assertForbidden();
    $this->actingAs($accountant)->post(route('milk-production.bulk'), ['production_date' => '2026-10-19', 'shift' => 'morning', 'qty' => [$this->a->id => '5']])->assertForbidden();
    $this->actingAs($accountant)->get(route('milk-production.edit', $record))->assertForbidden();

    $other = User::factory()->forFarm(Farm::factory()->create())->withRole(RoleName::Owner)->create();
    $this->flushSession();
    $this->actingAs($other)->get(route('milk-production.edit', $record))->assertNotFound();
    $this->actingAs($other)->get(route('milk-production.index'))->assertOk()->assertDontSee('C-101');
});
