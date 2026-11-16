<?php

/*
| Phase 6 acceptance criteria (spec Section 8), end to end over HTTP, in the order the spec lists them, on the
| production seed data (default farm, payment modes, expense categories, sections). Detailed behaviour is covered
| by the per-feature tests in tests/Feature/Trading and tests/Feature/Staff.
*/

use App\Enums\AnimalStatus;
use App\Enums\RoleName;
use App\Models\Animal;
use App\Models\AnimalPurchase;
use App\Models\Attendance;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\FeedItem;
use App\Models\FeedTransaction;
use App\Models\MilkProductionRecord;
use App\Models\Party;
use App\Models\PaymentMode;
use App\Models\SalaryRun;
use App\Models\StaffMember;
use App\Models\User;
use App\Services\AnimalPnlService;
use App\Support\CurrentFarm;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->travelTo('2027-01-15 10:00');
    $this->seed(DatabaseSeeder::class);
    $this->farm = Farm::where('code', 'MDF')->firstOrFail();
    app(CurrentFarm::class)->set($this->farm);
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->actingAs($this->owner);
    $this->party = Party::factory()->create(['farm_id' => $this->farm->id, 'party_type' => 'both', 'name' => 'Ramesh Yadav']);
});

function buyRow(string $tag, string $price, string $stage = 'heifer', string $ownership = 'resale'): array
{
    return ['tag_no' => $tag, 'animal_type' => 'cow', 'gender' => 'female', 'lifecycle_stage' => $stage, 'ownership_type' => $ownership, 'price' => $price];
}

it('1. purchase of 2 animals priced 30,000 and 20,000 with 1,000 transport → allocated 600 / 400', function () {
    $this->post(route('animal-purchases.store'), ['party_id' => $this->party->id, 'purchase_date' => '2027-01-10',
        'transport_cost' => '1,000', 'commission' => '', 'other_cost' => '',
        'animals' => [buyRow('A-30', '30,000'), buyRow('A-20', '20000')]])->assertSessionHasNoErrors();

    $items = AnimalPurchase::sole()->items()->orderBy('id')->get();
    expect($items->pluck('allocated_cost')->all())->toBe(['600.00', '400.00'])
        ->and($items->pluck('total_cost')->all())->toBe(['30600.00', '20400.00']);
});

it('2. heifer: 35,000 + 12,000 feed + 2,000 health + 1,000 transport = 50,000; sale 65,000 → estimated profit 15,000', function () {
    $this->post(route('animal-purchases.store'), ['party_id' => $this->party->id, 'purchase_date' => '2026-08-01',
        'transport_cost' => '1000', 'animals' => [buyRow('H-1', '35000')]])->assertSessionHasNoErrors();
    $heifer = Animal::where('tag_no', 'H-1')->sole();

    // feed: 600 kg eaten × ₹20 average purchase cost
    $item = FeedItem::create(['name' => 'Cattle feed', 'unit' => 'kg', 'reorder_level' => '0', 'is_active' => true]);
    FeedTransaction::create(['feed_item_id' => $item->id, 'transaction_date' => '2026-08-01', 'type' => 'purchase', 'quantity' => '1000', 'rate' => '20', 'amount' => '20000']);
    FeedTransaction::create(['feed_item_id' => $item->id, 'transaction_date' => '2026-10-01', 'type' => 'consumption', 'quantity' => '600', 'animal_id' => $heifer->id]);
    // health: a ₹2,000 treatment recorded as an expense
    $this->post(route('health.treatments.store'), ['animal_id' => $heifer->id, 'treatment_date' => '2026-09-10', 'diagnosis' => 'Fever',
        'cost' => '2000', 'record_as_expense' => '1', 'payment_mode_id' => PaymentMode::where('code', 'CASH')->value('id')])
        ->assertSessionHasNoErrors();

    $this->post(route('animal-sales.store'), ['party_id' => $this->party->id, 'sale_date' => '2027-01-10', 'selling_costs' => '0',
        'animals' => [['animal_id' => $heifer->id, 'price' => '65000']]])->assertSessionHasNoErrors();

    expect(app(AnimalPnlService::class)->forAnimal($heifer->fresh()))
        ->total_cost->toBe('50000.00')->income->toBe('65000.00')->profit->toBe('15000.00');
    $this->get(route('reports.trading', ['summary', 'from' => '2026-04-01', 'to' => '2027-01-15']))->assertOk()
        ->assertSee('H-1')->assertSee('₹50,000.00')->assertSee('₹15,000.00');
});

it('3. a sold animal disappears from milk entry and keeps its full history', function () {
    $cow = Animal::factory()->create(['farm_id' => $this->farm->id, 'tag_no' => 'C-9', 'lifecycle_stage' => 'milking', 'gender' => 'female']);
    MilkProductionRecord::factory()->create(['farm_id' => $this->farm->id, 'animal_id' => $cow->id, 'production_date' => '2027-01-05']);
    $this->getJson(route('animals.search', ['milking' => 1, 'q' => 'C-9']))->assertJsonCount(1, 'results');

    $this->post(route('animal-sales.store'), ['party_id' => $this->party->id, 'sale_date' => '2027-01-10', 'selling_costs' => '0',
        'animals' => [['animal_id' => $cow->id, 'price' => '60000']]])->assertSessionHasNoErrors();

    $this->getJson(route('animals.search', ['milking' => 1, 'q' => 'C-9']))->assertJsonCount(0, 'results');
    expect($cow->fresh()->status)->toBe(AnimalStatus::Sold)
        ->and(MilkProductionRecord::where('animal_id', $cow->id)->count())->toBe(1)
        ->and($cow->statusHistories()->where('new_value', 'sold')->exists())->toBeTrue();
    $this->get(route('animals.show', $cow))->assertOk()->assertSee('C-9');
});

it('4. salary: 30-day month, 26 present + 4 absent, base 15,000 → earned 13,000; finalize creates expense 13,000', function () {
    $raju = StaffMember::create(['name' => 'Raju Pawar', 'designation' => 'Milker', 'monthly_salary' => '15000', 'joining_date' => '2026-01-01', 'is_active' => true]);
    foreach (range(1, 30) as $day) {
        Attendance::create(['staff_member_id' => $raju->id, 'attendance_date' => sprintf('2026-11-%02d', $day), 'status' => $day <= 26 ? 'present' : 'absent']);
    }

    $this->post(route('salary-runs.store'), ['month' => '2026-11'])->assertSessionHasNoErrors();
    $run = SalaryRun::sole();
    expect($run->slips()->sole()->earned_salary)->toBe('13000.00');

    $this->post(route('salary-runs.finalize', $run))->assertSessionHas('success');
    expect(Expense::sole())->amount->toBe('13000.00')->paid_to_text->toBe('Raju Pawar');
});
