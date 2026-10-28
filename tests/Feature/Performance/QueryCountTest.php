<?php

/*
| Performance rules (CLAUDE.md): list pages must not run more queries as the data grows (no N+1).
| Each test counts the queries for a small and a large data set and expects the same number.
*/

use App\Enums\RoleName;
use App\Models\Animal;
use App\Models\AnimalBreed;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\MilkProductionRecord;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
});

function queriesFor(string $url, User $user): int
{
    test()->actingAs($user)->get($url)->assertOk(); // warm-up (permission cache, appearance cache)

    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->actingAs($user)->get($url)->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('keeps the users list at a constant number of queries', function () {
    User::factory()->count(3)->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $few = queriesFor(route('users.index'), $this->owner);

    User::factory()->count(40)->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
    $many = queriesFor(route('users.index'), $this->owner);

    expect($many)->toBe($few)->toBeLessThanOrEqual(10);
});

it('keeps the audit log at a constant number of queries', function () {
    $staff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $staff->update(['name' => 'Edit 1']);
    $few = queriesFor(route('audit-logs.index'), $this->owner);

    foreach (range(2, 40) as $i) {
        User::factory()->forFarm($this->farm)->create()->update(['name' => "Edit {$i}"]);
    }
    $many = queriesFor(route('audit-logs.index'), $this->owner);

    // Can only drop (fewer record types on the newest page), never grow with the number of entries.
    expect($many)->toBeLessThanOrEqual($few)->toBeLessThanOrEqual(12);
});

it('keeps the expense category tree at a constant number of queries', function () {
    $make = function (int $count) {
        foreach (range(1, $count) as $i) {
            $parent = ExpenseCategory::factory()->create(['farm_id' => $this->farm->id]);
            ExpenseCategory::factory()->count(3)->childOf($parent)->create();
        }
    };

    $make(2);
    $few = queriesFor(route('expense-categories.index'), $this->owner);
    $make(15);
    $many = queriesFor(route('expense-categories.index'), $this->owner);

    expect($many)->toBe($few);
});

it('keeps the vendor list at a constant number of queries', function () {
    Vendor::factory()->count(3)->create(['farm_id' => $this->farm->id]);
    $few = queriesFor(route('vendors.index'), $this->owner);

    Vendor::factory()->count(40)->create(['farm_id' => $this->farm->id]);
    $many = queriesFor(route('vendors.index'), $this->owner);

    expect($many)->toBe($few);
});

it('keeps the expense list at a constant number of queries', function () {
    // Both sets contain every kind of row (paid, credit with vendor, with subcategory, with creator),
    // so the same relations are loaded; only the number of rows differs.
    $vendor = Vendor::factory()->create(['farm_id' => $this->farm->id]);
    $parent = ExpenseCategory::factory()->create(['farm_id' => $this->farm->id]);
    $sub = ExpenseCategory::factory()->childOf($parent)->create();
    $make = function (int $n) use ($vendor, $parent, $sub) {
        $this->actingAs($this->owner);
        Expense::factory()->count($n)->create(['farm_id' => $this->farm->id, 'category_id' => $parent->id, 'subcategory_id' => $sub->id]);
        Expense::factory()->count($n)->credit($vendor)->create();
    };

    $make(2);
    $few = queriesFor(route('expenses.index', ['all' => 1]), $this->owner);
    $make(20);
    $many = queriesFor(route('expenses.index', ['all' => 1]), $this->owner);

    expect($many)->toBe($few);
});

it('keeps the vendor list with balances and the ledger at a constant number of queries', function () {
    $vendor = Vendor::factory()->create(['farm_id' => $this->farm->id]);
    $add = function (int $n) use ($vendor) {
        Vendor::factory()->count($n)->create(['farm_id' => $this->farm->id]);
        Expense::factory()->count($n)->credit($vendor)->create();
        VendorPayment::factory()->count($n)->create(['vendor_id' => $vendor->id, 'farm_id' => $this->farm->id]);
    };

    $add(2);
    $fewList = queriesFor(route('vendors.index'), $this->owner);
    $fewLedger = queriesFor(route('vendors.show', $vendor), $this->owner);
    $add(25);

    expect(queriesFor(route('vendors.index'), $this->owner))->toBe($fewList)
        ->and(queriesFor(route('vendors.show', $vendor), $this->owner))->toBe($fewLedger);
});

it('loads the user\'s farms once per page', function () {
    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->actingAs($this->owner)->get(route('profile.edit'))->assertOk();

    $farmQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'inner join `farm_user`'));

    expect($farmQueries)->toHaveCount(1);
});

it('keeps the breeds list at a constant number of queries', function () {
    AnimalBreed::seedDefaultsFor($this->farm);
    $few = queriesFor(route('animal-breeds.index'), $this->owner);

    AnimalBreed::factory()->count(40)->create(['farm_id' => $this->farm->id]);
    $many = queriesFor(route('animal-breeds.index'), $this->owner);

    expect($many)->toBe($few)->toBeLessThanOrEqual(8);
});

it('keeps the animals list and profile at a constant number of queries', function () {
    $breed = AnimalBreed::factory()->create(['farm_id' => $this->farm->id]);
    Animal::factory()->count(3)->create(['farm_id' => $this->farm->id, 'breed_id' => $breed->id]);
    $few = queriesFor(route('animals.index'), $this->owner);

    Animal::factory()->count(40)->create(['farm_id' => $this->farm->id, 'breed_id' => AnimalBreed::factory()->create(['farm_id' => $this->farm->id])->id]);
    $many = queriesFor(route('animals.index'), $this->owner);

    expect($many)->toBe($few)->toBeLessThanOrEqual(12);
});

it('keeps the milk entry grid and entries list at a constant number of queries', function () {
    $this->actingAs($this->owner); // entries get created_by in both rounds, like real data
    $make = function (int $count) {
        Animal::factory()->count($count)->create(['farm_id' => $this->farm->id])->each(function (Animal $animal) {
            foreach (range(1, 4) as $d) {
                MilkProductionRecord::factory()->for($animal)->create(['production_date' => today()->subDays($d)->toDateString()]);
            }
        });
    };

    $make(2);
    $fewGrid = queriesFor(route('milk-production.index'), $this->owner);
    $fewList = queriesFor(route('milk-production.entries', ['from' => today()->subDays(10)->toDateString()]), $this->owner);

    $make(25);
    expect(queriesFor(route('milk-production.index'), $this->owner))->toBe($fewGrid)->toBeLessThanOrEqual(12)
        ->and(queriesFor(route('milk-production.entries', ['from' => today()->subDays(10)->toDateString()]), $this->owner))->toBe($fewList);
});
