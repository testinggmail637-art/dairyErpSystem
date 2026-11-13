<?php

use App\Enums\LifecycleStage;
use App\Enums\RoleName;
use App\Models\Animal;
use App\Models\AnimalStatusHistory;
use App\Models\Farm;
use App\Models\User;
use App\Support\CurrentFarm;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->farm = Farm::where('code', 'MDF')->firstOrFail();
    app(CurrentFarm::class)->set($this->farm);
    $this->travelTo('2026-11-13 10:00');
    $this->staff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $this->actingAs($this->staff);
    $this->cow = Animal::factory()->create(['farm_id' => $this->farm->id, 'tag_no' => 'C-1']); // milking
    $this->oldCalf = Animal::factory()->stage(LifecycleStage::Calf)->create(['farm_id' => $this->farm->id, 'tag_no' => 'K-1', 'date_of_birth' => '2025-10-01']);
    $this->youngCalf = Animal::factory()->stage(LifecycleStage::Calf)->create(['farm_id' => $this->farm->id, 'tag_no' => 'K-2', 'date_of_birth' => '2026-03-01']);
    Animal::factory()->male()->stage(LifecycleStage::Calf)->create(['farm_id' => $this->farm->id, 'tag_no' => 'K-3', 'date_of_birth' => '2025-06-01']);
});

it('dries off a milking female with the date in the status history; only from milking', function () {
    $this->get(route('animals.show', $this->cow))->assertOk()->assertSee('Dry off');

    $this->post(route('animals.dry-off', $this->cow), ['changed_on' => '2026-11-12'])->assertSessionHas('success', 'C-1 dried off.');
    expect($this->cow->fresh()->lifecycle_stage)->toBe(LifecycleStage::Dry)
        ->and(AnimalStatusHistory::where('animal_id', $this->cow->id)->where('new_value', 'dry')->value('changed_on')->toDateString())->toBe('2026-11-12');

    $this->post(route('animals.dry-off', $this->cow))->assertSessionHas('error'); // already dry
    $this->post(route('animals.dry-off', $this->oldCalf))->assertSessionHas('error');
    $this->post(route('animals.dry-off', $this->cow), ['changed_on' => '2026-11-14'])->assertSessionHasErrors('changed_on');
});

it('suggests female calves over 12 months on the dashboard and marks them as heifer only when asked', function () {
    $this->get(route('dashboard'))->assertOk()->assertSee('1 female calf is over 12 months')->assertSee('K-1')
        ->assertDontSee('K-2')->assertDontSee('K-3');
    expect($this->oldCalf->fresh()->lifecycle_stage)->toBe(LifecycleStage::Calf); // never automatic

    $this->post(route('animals.mark-heifer', $this->oldCalf))->assertSessionHas('success', 'K-1 marked as heifer.');
    expect($this->oldCalf->fresh()->lifecycle_stage)->toBe(LifecycleStage::Heifer);
    $this->get(route('dashboard'))->assertOk()->assertDontSee('over 12 months');

    $this->post(route('animals.mark-heifer', $this->cow))->assertSessionHas('error'); // not a calf
});

it('is for breeding.manage users', function () {
    $accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
    $this->actingAs($accountant)->post(route('animals.dry-off', $this->cow))->assertForbidden();
    $this->post(route('animals.mark-heifer', $this->oldCalf))->assertForbidden();
    expect($this->cow->fresh()->lifecycle_stage)->toBe(LifecycleStage::Milking);
});
