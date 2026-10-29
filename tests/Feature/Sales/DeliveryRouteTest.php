<?php

use App\Enums\RoleName;
use App\Models\DeliveryRoute;
use App\Models\Farm;
use App\Models\User;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
    $this->delivery = User::factory()->forFarm($this->farm)->withRole(RoleName::DeliveryBoy)->create(['name' => 'Ramesh Delivery']);
});

it('lists routes in order with status / search filters over AJAX, only of the current farm', function () {
    DeliveryRoute::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Station Road', 'sort_order' => 2, 'delivery_user_id' => $this->delivery->id]);
    DeliveryRoute::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Old City', 'sort_order' => 1]);
    DeliveryRoute::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Closed Lane', 'is_active' => false]);
    DeliveryRoute::factory()->create(['farm_id' => Farm::factory()->create()->id, 'name' => 'Foreign Route']);

    $this->actingAs($this->owner)->get(route('delivery-routes.index'))->assertOk()
        ->assertSeeInOrder(['Closed Lane', 'Old City', 'Station Road'])->assertSee('Ramesh Delivery')
        ->assertDontSee('Foreign Route')->assertSee(route('delivery-routes.create'), false);

    $this->get(route('delivery-routes.index', ['status' => 'active', 'search' => 'road']), ['X-Ajax-List' => '1'])
        ->assertOk()->assertHeader('X-Ajax-List-Fragment', '1')
        ->assertSee('Station Road')->assertDontSee('Old City')->assertDontSee('Closed Lane');
});

it('adds and edits a route with a delivery person of this farm', function () {
    $this->actingAs($this->owner)->get(route('delivery-routes.create'))->assertOk()->assertSee('Ramesh Delivery');

    $this->post(route('delivery-routes.store'), [
        'name' => '  Station   Road ', 'delivery_user_id' => $this->delivery->id, 'sort_order' => '3', 'is_active' => '1',
    ])->assertRedirect(route('delivery-routes.index'))->assertSessionHasNoErrors();

    $route = DeliveryRoute::sole();
    expect($route->name)->toBe('Station Road')
        ->and($route->delivery_user_id)->toBe($this->delivery->id)
        ->and($route->sort_order)->toBe(3)
        ->and($route->farm_id)->toBe($this->farm->id)
        ->and($route->created_by)->toBe($this->owner->id);

    $this->put(route('delivery-routes.update', $route), ['name' => 'Station Road East', 'delivery_user_id' => '', 'sort_order' => '', 'is_active' => '0'])
        ->assertRedirect(route('delivery-routes.index'));

    expect($route->fresh())->name->toBe('Station Road East')->delivery_user_id->toBeNull()->sort_order->toBe(0)->is_active->toBeFalse();
});

it('rejects a duplicate name in the farm but allows it in another farm', function () {
    DeliveryRoute::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Old City']);
    DeliveryRoute::factory()->create(['farm_id' => Farm::factory()->create()->id, 'name' => 'Station Road']);

    $this->actingAs($this->owner)->post(route('delivery-routes.store'), ['name' => 'old city'])->assertSessionHasErrors('name');
    $this->post(route('delivery-routes.store'), ['name' => 'Station Road'])->assertSessionHasNoErrors();
});

it('accepts only active users of this farm who can update deliveries as delivery person', function () {
    $otherFarmBoy = User::factory()->forFarm(Farm::factory()->create())->withRole(RoleName::DeliveryBoy)->create();
    $inactiveBoy = User::factory()->forFarm($this->farm)->withRole(RoleName::DeliveryBoy)->create(['is_active' => false]);

    foreach ([$otherFarmBoy, $inactiveBoy, $this->accountant] as $user) {
        $this->actingAs($this->owner)->post(route('delivery-routes.store'), ['name' => 'R '.$user->id, 'delivery_user_id' => $user->id])
            ->assertSessionHasErrors('delivery_user_id');
    }
    expect(DeliveryRoute::count())->toBe(0);
});

it('lets accountants view but not change routes, and keeps others out', function () {
    $route = DeliveryRoute::factory()->create(['farm_id' => $this->farm->id]);
    $herdStaff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();

    $this->actingAs($this->accountant)->get(route('delivery-routes.index'))->assertOk()->assertDontSee(route('delivery-routes.create'), false);
    $this->get(route('delivery-routes.create'))->assertForbidden();
    $this->put(route('delivery-routes.update', $route), ['name' => 'X'])->assertForbidden();

    $this->actingAs($herdStaff)->get(route('delivery-routes.index'))->assertForbidden();
    $this->actingAs($this->delivery)->get(route('delivery-routes.index'))->assertForbidden();
});

it('returns 404 for a route of another farm', function () {
    $foreign = DeliveryRoute::factory()->create(['farm_id' => Farm::factory()->create()->id]);

    $this->actingAs($this->owner)->get(route('delivery-routes.edit', $foreign))->assertNotFound();
});
