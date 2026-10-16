<?php

use App\Enums\RoleName;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\User;
use App\Support\CurrentFarm;
use Database\Seeders\FarmMasterDataSeeder;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
});

function category(Farm $farm, string $name, ?ExpenseCategory $parent = null): ExpenseCategory
{
    return ExpenseCategory::factory()->create(['farm_id' => $farm->id, 'parent_id' => $parent?->id, 'name' => $name]);
}

it('seeds the 9 default categories with their 25 subcategories, once per farm', function () {
    $this->seed(FarmMasterDataSeeder::class);
    $this->seed(FarmMasterDataSeeder::class);

    app(CurrentFarm::class)->set($this->farm);

    expect(ExpenseCategory::topLevel()->ordered()->pluck('name')->all())->toBe(array_keys(ExpenseCategory::DEFAULTS))
        ->and(ExpenseCategory::whereNotNull('parent_id')->count())->toBe(25)
        ->and(ExpenseCategory::where('name', 'Dry Fodder (Bhusa)')->first()->parent->name)->toBe('Feed');
});

it('shows the tree with categories and their subcategories', function () {
    $feed = category($this->farm, 'Feed');
    category($this->farm, 'Cattle Feed', $feed);
    category($this->farm, 'Silage', $feed);
    ExpenseCategory::factory()->create(['farm_id' => Farm::factory()->create()->id, 'name' => 'Other Farm Category']);

    $this->actingAs($this->accountant)->get(route('expense-categories.index'))
        ->assertOk()
        ->assertSeeInOrder(['Feed', 'Cattle Feed', 'Silage'])
        ->assertDontSee('Other Farm Category')
        ->assertSee(route('expense-categories.create', ['parent' => $feed->id]), false);
});

it('creates a category and a subcategory', function () {
    $this->actingAs($this->accountant)
        ->post(route('expense-categories.store'), ['name' => 'Biogas', 'code' => 'bio', 'is_active' => '1', 'sort_order' => 5])
        ->assertRedirect(route('expense-categories.index'));

    $biogas = ExpenseCategory::where('name', 'Biogas')->firstOrFail();
    expect($biogas->farm_id)->toBe($this->farm->id)->and($biogas->code)->toBe('BIO')->and($biogas->isTopLevel())->toBeTrue();

    $this->actingAs($this->accountant)
        ->post(route('expense-categories.store'), ['name' => 'Slurry Pump', 'parent_id' => $biogas->id, 'is_active' => '1'])
        ->assertRedirect(route('expense-categories.index'));

    expect(ExpenseCategory::where('name', 'Slurry Pump')->first()->parent_id)->toBe($biogas->id);
});

it('allows only two levels and keeps names unique under the same parent', function () {
    $feed = category($this->farm, 'Feed');
    $cattleFeed = category($this->farm, 'Cattle Feed', $feed);
    $health = category($this->farm, 'Health');
    $foreign = category(Farm::factory()->create(), 'Foreign');

    // no third level, no parent from another farm
    $this->actingAs($this->accountant)
        ->post(route('expense-categories.store'), ['name' => 'Too deep', 'parent_id' => $cattleFeed->id, 'is_active' => '1'])
        ->assertSessionHasErrors('parent_id');
    $this->actingAs($this->accountant)
        ->post(route('expense-categories.store'), ['name' => 'Wrong farm', 'parent_id' => $foreign->id, 'is_active' => '1'])
        ->assertSessionHasErrors('parent_id');

    // duplicates: same top-level name, same name under the same parent
    $this->actingAs($this->accountant)->post(route('expense-categories.store'), ['name' => 'feed', 'is_active' => '1'])
        ->assertSessionHasErrors('name');
    $this->actingAs($this->accountant)->post(route('expense-categories.store'), ['name' => 'Cattle Feed', 'parent_id' => $feed->id, 'is_active' => '1'])
        ->assertSessionHasErrors('name');

    // same name under a different parent is fine
    $this->actingAs($this->accountant)->post(route('expense-categories.store'), ['name' => 'Cattle Feed', 'parent_id' => $health->id, 'is_active' => '1'])
        ->assertSessionDoesntHaveErrors();

    // a category with subcategories cannot become a subcategory; nothing can be its own parent
    $this->actingAs($this->accountant)->put(route('expense-categories.update', $feed), ['name' => 'Feed', 'parent_id' => $health->id, 'is_active' => '1'])
        ->assertSessionHasErrors('parent_id');
    $this->actingAs($this->accountant)->put(route('expense-categories.update', $health), ['name' => 'Health', 'parent_id' => $health->id, 'is_active' => '1'])
        ->assertSessionHasErrors('parent_id');
});

it('edits and deactivates a category', function () {
    $feed = category($this->farm, 'Feed');

    $this->actingAs($this->accountant)->get(route('expense-categories.edit', $feed))->assertOk()->assertSee('value="Feed"', false);

    $this->actingAs($this->accountant)
        ->put(route('expense-categories.update', $feed), ['name' => 'Animal Feed', 'is_active' => '0', 'sort_order' => 3])
        ->assertRedirect(route('expense-categories.index'));

    $feed->refresh();
    expect($feed->name)->toBe('Animal Feed')->and($feed->is_active)->toBeFalse()->and($feed->sort_order)->toBe(3);
});

it('deletes only categories without subcategories', function () {
    $feed = category($this->farm, 'Feed');
    $silage = category($this->farm, 'Silage', $feed);

    $this->actingAs($this->accountant)->delete(route('expense-categories.destroy', $feed))->assertSessionHas('error');
    expect(ExpenseCategory::whereKey($feed->id)->exists())->toBeTrue();

    $this->actingAs($this->accountant)->delete(route('expense-categories.destroy', $silage))->assertSessionHas('success');
    $this->actingAs($this->accountant)->delete(route('expense-categories.destroy', $feed))->assertSessionHas('success');
    expect(ExpenseCategory::count())->toBe(0);
});

it('lets viewers see the tree but only editors change it, and hides other farms (404)', function () {
    $herdStaff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create(); // expenses.create only
    $feed = category($this->farm, 'Feed');
    $foreign = category(Farm::factory()->create(), 'Foreign');

    $this->actingAs($herdStaff)->get(route('expense-categories.index'))->assertForbidden();
    $this->actingAs($herdStaff)->post(route('expense-categories.store'), ['name' => 'X', 'is_active' => '1'])->assertForbidden();
    $this->actingAs($herdStaff)->delete(route('expense-categories.destroy', $feed))->assertForbidden();

    $this->actingAs($this->accountant)->get(route('expense-categories.edit', $foreign))->assertNotFound();
});

it('shows Categories in the Expenses menu for users with expenses.view', function () {
    $this->actingAs($this->accountant)->get(route('profile.edit'))
        ->assertSee(route('expense-categories.index'), false);
});
