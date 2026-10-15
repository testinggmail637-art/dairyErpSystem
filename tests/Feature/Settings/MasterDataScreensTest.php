<?php

use App\Enums\RoleName;
use App\Models\Farm;
use App\Models\PaymentMode;
use App\Models\Section;
use App\Models\User;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    Section::seedDefaultsFor($this->farm);
    PaymentMode::seedDefaultsFor($this->farm);
    $this->admin = User::factory()->forFarm($this->farm)->withRole(RoleName::Admin)->create();
});

it('lists, adds and edits payment modes', function () {
    $this->actingAs($this->admin)->get(route('payment-modes.index'))
        ->assertOk()->assertSee('Bank Transfer')->assertSee('CHEQUE');

    $this->actingAs($this->admin)
        ->post(route('payment-modes.store'), ['name' => 'Paytm Wallet', 'code' => 'paytm', 'is_active' => '1'])
        ->assertRedirect(route('payment-modes.index'));

    $mode = PaymentMode::where('code', 'PAYTM')->firstOrFail();
    expect($mode->farm_id)->toBe($this->farm->id)->and($mode->created_by)->toBe($this->admin->id);

    $this->actingAs($this->admin)
        ->put(route('payment-modes.update', $mode), ['name' => 'Paytm', 'code' => 'PAYTM', 'is_active' => '0'])
        ->assertRedirect(route('payment-modes.index'));

    expect($mode->fresh()->name)->toBe('Paytm')->and($mode->fresh()->is_active)->toBeFalse();
});

it('adds and edits sections with search', function () {
    $this->actingAs($this->admin)
        ->post(route('sections.store'), ['name' => 'Biogas Plant', 'code' => 'BIOGAS', 'is_active' => '1'])
        ->assertRedirect(route('sections.index'));

    $this->actingAs($this->admin)->get(route('sections.index', ['search' => 'bio']))
        ->assertSee('Biogas Plant')->assertDontSee('Animal Trading');

    $section = Section::where('code', 'HERD')->firstOrFail();
    $this->actingAs($this->admin)->get(route('sections.edit', $section))->assertOk()->assertSee('Own Farm/Herd');
});

it('rejects a duplicate or badly formed code within the farm', function () {
    $this->actingAs($this->admin)
        ->post(route('sections.store'), ['name' => 'Herd 2', 'code' => 'herd'])
        ->assertSessionHasErrors('code');

    $this->actingAs($this->admin)
        ->post(route('payment-modes.store'), ['name' => 'Weird', 'code' => 'A B-C'])
        ->assertSessionHasErrors('code');
});

it('hides other farms\' rows (404) and forbids users without settings.manage', function () {
    $otherFarm = Farm::factory()->create();
    PaymentMode::seedDefaultsFor($otherFarm);
    $foreign = PaymentMode::allFarms()->where('farm_id', $otherFarm->id)->first();

    $this->actingAs($this->admin)->get(route('payment-modes.edit', $foreign))->assertNotFound();

    $staff = User::factory()->forFarm($this->farm)->withRole(RoleName::CollectionOperator)->create();
    $this->actingAs($staff)->get(route('payment-modes.index'))->assertForbidden();
    $this->actingAs($staff)->post(route('sections.store'), ['name' => 'X', 'code' => 'X'])->assertForbidden();
});
