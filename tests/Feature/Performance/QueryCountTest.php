<?php

/*
| Performance rules (CLAUDE.md): list pages must not run more queries as the data grows (no N+1).
| Each test counts the queries for a small and a large data set and expects the same number.
*/

use App\Enums\RoleName;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\User;
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

it('loads the user\'s farms once per page', function () {
    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->actingAs($this->owner)->get(route('profile.edit'))->assertOk();

    $farmQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'inner join `farm_user`'));

    expect($farmQueries)->toHaveCount(1);
});
