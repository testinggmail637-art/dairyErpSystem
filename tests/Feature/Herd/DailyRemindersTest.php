<?php

use App\Enums\AnimalStatus;
use App\Enums\BillStatus;
use App\Enums\RoleName;
use App\Models\Animal;
use App\Models\AnimalVaccination;
use App\Models\Bill;
use App\Models\BreedingEvent;
use App\Models\Farm;
use App\Models\FeedItem;
use App\Models\FeedTransaction;
use App\Models\MilkProductionRecord;
use App\Models\User;
use App\Models\VaccineType;
use App\Support\CurrentFarm;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->farm = Farm::where('code', 'MDF')->firstOrFail();
    app(CurrentFarm::class)->set($this->farm);
    $this->travelTo('2026-11-13 07:00');
    $this->owner = User::role(RoleName::Owner->value)->firstOrFail();
    $this->herd = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
    $this->cow = fn (string $tag, array $attrs = []) => Animal::factory()->create(['farm_id' => $this->farm->id, 'tag_no' => $tag] + $attrs);
});

function messages(User $user): array
{
    return $user->notifications()->pluck('data')->pluck('message')->sort()->values()->all();
}

it('reminds of due vaccinations and low feed stock (spec acceptance), to users with the permission, once a day', function () {
    $fmd = VaccineType::where('name', 'FMD')->firstOrFail();
    $vaccinate = fn (Animal $a, string $given, ?string $next) => AnimalVaccination::create(
        ['animal_id' => $a->id, 'vaccine_type_id' => $fmd->id, 'given_on' => $given, 'next_due_on' => $next, 'cost' => '0']);
    $vaccinate(($this->cow)('C-1'), '2026-05-10', '2026-11-10');            // overdue
    $vaccinate(($this->cow)('C-2'), '2026-05-18', '2026-11-18');            // due in 5 days
    $vaccinate(($this->cow)('C-3'), '2026-05-30', '2026-11-30');            // beyond 7 days
    $c4 = ($this->cow)('C-4');
    $vaccinate($c4, '2026-05-01', '2026-11-01');                            // superseded by a later dose
    $vaccinate($c4, '2026-11-02', '2027-05-02');
    $vaccinate(($this->cow)('C-5', ['status' => AnimalStatus::Sold]), '2026-05-10', '2026-11-10'); // sold: ignored

    $feed = FeedItem::create(['name' => 'Cattle feed', 'unit' => 'kg', 'reorder_level' => '450', 'is_active' => true]);
    FeedItem::create(['name' => 'Mineral mix', 'unit' => 'kg', 'reorder_level' => '0', 'is_active' => true]); // no level: never low
    FeedTransaction::create(['feed_item_id' => $feed->id, 'transaction_date' => '2026-11-01', 'type' => 'opening', 'quantity' => '500']);
    FeedTransaction::create(['feed_item_id' => $feed->id, 'transaction_date' => '2026-11-12', 'type' => 'consumption', 'quantity' => '80']);

    $this->artisan('reminders:daily')->assertSuccessful();

    $expected = [
        '1 feed items at or below reorder level: Cattle feed 420.00 kg.',
        '2 vaccinations due by 20-11-2026 (1 overdue): C-1 FMD, C-2 FMD.',
    ];
    expect(messages($this->herd))->toBe($expected)
        ->and(messages($this->owner))->toBe($expected)
        ->and($this->accountant->notifications()->count())->toBe(0);
    expect($this->herd->notifications()->get()->pluck('data.url')->sort()->values()->all())
        ->toBe([route('feed.index', [], false), route('health.vaccinations.index', ['due' => '30'], false)]);

    $this->artisan('reminders:daily')->assertSuccessful(); // same day again: nothing new
    expect($this->herd->notifications()->count())->toBe(2);
});

it('reminds of expected calvings, yesterday\'s milk drops and overdue bills', function () {
    $due = ($this->cow)('C-7');
    BreedingEvent::create(['animal_id' => $due->id, 'event_type' => 'ai', 'event_date' => '2026-02-05', 'expected_calving_date' => '2026-11-12']);
    $later = ($this->cow)('C-8');
    BreedingEvent::create(['animal_id' => $later->id, 'event_type' => 'ai', 'event_date' => '2026-04-01', 'expected_calving_date' => '2027-01-06']);

    $drop = ($this->cow)('C-9');
    foreach (['2026-11-08', '2026-11-09', '2026-11-10', '2026-11-11'] as $d) {
        MilkProductionRecord::factory()->create(['animal_id' => $drop->id, 'production_date' => $d, 'quantity_litre' => '10.00']);
    }
    MilkProductionRecord::factory()->create(['animal_id' => $drop->id, 'production_date' => '2026-11-12', 'quantity_litre' => '7.00']);

    Bill::factory()->create(['farm_id' => $this->farm->id, 'status' => BillStatus::Finalized, 'total' => '1500.00', 'due_date' => '2026-11-10']);
    Bill::factory()->create(['farm_id' => $this->farm->id, 'status' => BillStatus::Finalized, 'total' => '900.00', 'due_date' => '2026-11-20']); // not due yet
    Bill::factory()->create(['farm_id' => $this->farm->id, 'status' => BillStatus::Draft, 'total' => '700.00', 'due_date' => '2026-11-01']);    // draft

    $this->artisan('reminders:daily')->assertSuccessful();

    expect(messages($this->herd))->toBe([
        '1 expected calvings: C-7 12-11-2026.',
        '1 milk drop alerts on 12-11-2026: C-9 Morning −30.0%. Check health — this is a signal, not a diagnosis.',
    ])->and(messages($this->accountant))->toBe(['1 customer bills are overdue — ₹1,500.00 unpaid.'])
        ->and(messages($this->owner))->toHaveCount(3);
});
