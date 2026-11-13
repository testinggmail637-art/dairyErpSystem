<?php

/*
| Phase 5 acceptance criteria (spec Section 8), end to end over HTTP, in the order the spec lists them, on the
| production seed data (default farm, payment modes, expense categories, vaccines). Detailed behaviour is covered
| by the per-feature tests in tests/Feature/Herd.
*/

use App\Enums\LifecycleStage;
use App\Enums\RoleName;
use App\Models\Animal;
use App\Models\BreedingEvent;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\FeedItem;
use App\Models\PaymentMode;
use App\Models\Treatment;
use App\Models\User;
use App\Models\VaccineType;
use App\Models\Vendor;
use App\Services\FeedService;
use App\Support\CurrentFarm;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->travelTo('2027-01-01 10:00');
    $this->seed(DatabaseSeeder::class);
    $this->farm = Farm::where('code', 'MDF')->firstOrFail();
    app(CurrentFarm::class)->set($this->farm);
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->actingAs($this->owner);
    $this->cow = Animal::factory()->create(['farm_id' => $this->farm->id, 'tag_no' => 'C-7', 'lactation_number' => 2]);
    $this->cash = PaymentMode::where('code', 'CASH')->value('id');
});

it('1. treatment ₹800 "record as expense" makes exactly one expense; editing to ₹900 updates it', function () {
    $data = ['animal_id' => $this->cow->id, 'treatment_date' => '2027-01-01', 'diagnosis' => 'Mastitis', 'cost' => '800',
        'record_as_expense' => '1', 'payment_mode_id' => $this->cash];
    $this->post(route('health.treatments.store'), $data)->assertSessionHasNoErrors();
    $this->put(route('health.treatments.update', Treatment::sole()), ['cost' => '900', 'reason' => 'Final vet bill'] + $data)
        ->assertSessionHasNoErrors();

    expect(Expense::count())->toBe(1)
        ->and(Expense::sole()->amount)->toBe('900.00')
        ->and(Treatment::sole()->expense_id)->toBe(Expense::sole()->id);
});

it('2. AI on a cow on 01-01-2027 expects calving on 08-10-2027 (280 days)', function () {
    $this->post(route('breeding.store'), ['animal_id' => $this->cow->id, 'event_type' => 'ai', 'event_date' => '2027-01-01',
        'cost' => '0', 'record_as_expense' => '0'])->assertSessionHasNoErrors();

    expect(BreedingEvent::sole()->expected_calving_date->toDateString())->toBe('2027-10-08');
});

it('3. calving creates the calf with mother_id and increments lactation_number', function () {
    $this->travelTo('2027-10-08 10:00');
    $this->post(route('breeding.calvings.store'), ['mother_id' => $this->cow->id, 'calving_date' => '2027-10-08',
        'outcome' => 'alive', 'calf_gender' => 'female', 'calf_tag_no' => 'C-7-1'])->assertSessionHasNoErrors();

    $calf = Animal::where('tag_no', 'C-7-1')->sole();
    expect($calf->mother_id)->toBe($this->cow->id)
        ->and($calf->lifecycle_stage)->toBe(LifecycleStage::Calf)
        ->and($this->cow->fresh()->lactation_number)->toBe(3);
});

it('4. feed: opening 500 kg, consume 80 → 420; purchase creates one expense, consumption none', function () {
    $this->post(route('feed.items.store'), ['name' => 'Cattle feed', 'unit' => 'kg', 'reorder_level' => '0'])->assertSessionHasNoErrors();
    $item = FeedItem::sole();
    $entry = fn (string $type, array $data) => $this->post(route('feed.transactions.store'),
        ['type' => $type, 'feed_item_id' => $item->id, 'transaction_date' => '2027-01-01'] + $data)->assertSessionHasNoErrors();

    $entry('opening', ['quantity' => '500']);
    $entry('consumption', ['quantity' => '80']);
    expect(app(FeedService::class)->stock()[$item->id])->toBe('420.00')->and(Expense::count())->toBe(0);

    $vendor = Vendor::factory()->create(['farm_id' => $this->farm->id]);
    $entry('purchase', ['quantity' => '200', 'amount' => '5600', 'vendor_id' => $vendor->id, 'is_credit' => '1',
        'expense_subcategory_id' => ExpenseCategory::where('name', 'Cattle Feed')->value('id')]);
    expect(Expense::count())->toBe(1)->and(Expense::sole()->amount)->toBe('5600.00');
});

it('5. reminders:daily notifies due vaccinations and low feed stock', function () {
    $this->post(route('health.vaccinations.store'), ['vaccine_type_id' => VaccineType::where('name', 'FMD')->value('id'),
        'given_on' => '2026-07-01', 'next_due_on' => '2027-01-03', 'animal_ids' => [$this->cow->id], 'cost' => '0',
        'record_as_expense' => '0'])->assertSessionHasNoErrors();
    $this->post(route('feed.items.store'), ['name' => 'Bhusa', 'unit' => 'kg', 'reorder_level' => '100'])->assertSessionHasNoErrors();
    $this->post(route('feed.transactions.store'), ['type' => 'opening', 'feed_item_id' => FeedItem::sole()->id,
        'transaction_date' => '2027-01-01', 'quantity' => '60'])->assertSessionHasNoErrors();

    $this->artisan('reminders:daily')->assertSuccessful();

    expect($this->owner->notifications()->pluck('data')->pluck('message')->sort()->values()->all())->toBe([
        '1 feed items at or below reorder level: Bhusa 60.00 kg.',
        '1 vaccinations due by 08-01-2027 (0 overdue): C-7 FMD.',
    ]);
});
