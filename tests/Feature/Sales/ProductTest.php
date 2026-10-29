<?php

use App\Enums\MilkType;
use App\Enums\RoleName;
use App\Models\Farm;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
});

function productPayload(array $overrides = []): array
{
    return array_merge(['name' => 'A2 Cow Milk', 'code' => 'a2-cow', 'milk_type' => 'cow', 'default_rate' => '₹ 80.50', 'is_active' => '1'], $overrides);
}

it('seeds Cow Milk ₹60 and Buffalo Milk ₹70 for every farm, once, keeping owner changes', function () {
    $this->seed(DatabaseSeeder::class);
    $farm = Farm::where('code', 'MDF')->firstOrFail();
    $products = fn () => Product::allFarms()->where('farm_id', $farm->id)->orderBy('id')->get();

    expect($products()->map(fn ($p) => [$p->code, $p->name, $p->milk_type, $p->default_rate, $p->unit])->all())->toBe([
        ['COW', 'Cow Milk', MilkType::Cow, '60.00', 'litre'],
        ['BUF', 'Buffalo Milk', MilkType::Buffalo, '70.00', 'litre'],
    ]);

    Product::allFarms()->where('farm_id', $farm->id)->where('code', 'COW')->update(['default_rate' => '64.00', 'is_active' => false]);
    Product::seedDefaultsFor($farm);

    expect($products())->toHaveCount(2)
        ->and($products()->first())->default_rate->toBe('64.00')->is_active->toBeFalse();
});

it('lists the farm\'s products with rate, and only of the current farm', function () {
    Product::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Cow Milk', 'default_rate' => '1250.00']);
    Product::factory()->create(['farm_id' => Farm::factory()->create()->id, 'name' => 'Foreign Milk']);

    $this->actingAs($this->owner)->get(route('products.index'))->assertOk()
        ->assertSee('Cow Milk')->assertSee('₹1,250.00')->assertDontSee('Foreign Milk')
        ->assertSee(route('products.create'), false);
});

it('adds and edits a product with a cleaned code and rate', function () {
    $this->actingAs($this->owner)->post(route('products.store'), productPayload())
        ->assertRedirect(route('products.index'))->assertSessionHasNoErrors();

    $product = Product::sole();
    expect($product)->name->toBe('A2 Cow Milk')->code->toBe('A2-COW')->default_rate->toBe('80.50')
        ->milk_type->toBe(MilkType::Cow)->unit->toBe('litre')->farm_id->toBe($this->farm->id);

    $this->put(route('products.update', $product), productPayload(['default_rate' => '82', 'is_active' => '0']))
        ->assertRedirect(route('products.index'));
    expect($product->fresh())->default_rate->toBe('82.00')->is_active->toBeFalse();
});

it('validates name, code, milk type and rate', function (array $input, string $field) {
    Product::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Cow Milk', 'code' => 'COW']);

    $this->actingAs($this->owner)->post(route('products.store'), productPayload($input))->assertSessionHasErrors($field);
})->with([
    'duplicate name' => [['name' => 'cow milk'], 'name'],
    'duplicate code' => [['code' => 'cow'], 'code'],
    'bad code' => [['code' => 'A 2'], 'code'],
    'milk type' => [['milk_type' => 'goat'], 'milk_type'],
    'zero rate' => [['default_rate' => '0'], 'default_rate'],
    '3 decimals' => [['default_rate' => '60.555'], 'default_rate'],
    'too high' => [['default_rate' => '10000'], 'default_rate'],
]);

it('allows the same name and code in another farm', function () {
    Product::factory()->create(['farm_id' => Farm::factory()->create()->id, 'name' => 'A2 Cow Milk', 'code' => 'A2-COW']);

    $this->actingAs($this->owner)->post(route('products.store'), productPayload())->assertSessionHasNoErrors();
});

it('lets accountants view but not change products, keeps others out, and 404s other farms', function () {
    $product = Product::factory()->create(['farm_id' => $this->farm->id]);
    $foreign = Product::factory()->create(['farm_id' => Farm::factory()->create()->id]);
    $herdStaff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();

    $this->actingAs($this->accountant)->get(route('products.index'))->assertOk()->assertDontSee(route('products.create'), false);
    $this->get(route('products.create'))->assertForbidden();
    $this->put(route('products.update', $product), productPayload())->assertForbidden();

    $this->actingAs($herdStaff)->get(route('products.index'))->assertForbidden();
    $this->actingAs($this->owner)->get(route('products.edit', $foreign))->assertNotFound();
});
