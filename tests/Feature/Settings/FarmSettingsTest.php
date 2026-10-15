<?php

use App\Enums\RoleName;
use App\Models\Farm;
use App\Models\User;

beforeEach(function () {
    $this->farm = Farm::factory()->create(['name' => 'Gokul Dairy', 'settings' => ['appearance' => ['primary_color' => '#112233']]]);
    $this->admin = User::factory()->forFarm($this->farm)->withRole(RoleName::Admin)->create();
});

function farmPayload(array $settings = [], array $overrides = []): array
{
    return array_merge([
        'name' => 'Gokul Dairy Farm',
        'address' => 'Village Rampur, Dist. Mathura',
        'phone' => '9876543210',
        'gstin' => '09abcde1234f1z5',
        'settings' => array_merge([
            'milk_drop_alert_percent' => '25',
            'allow_negative_milk_stock' => '0',
            'farmer_payment_cycle_days' => '15',
            'cow_gestation_days' => '283',
            'buffalo_gestation_days' => '310',
            'vaccination_reminder_days' => '7',
            'calving_reminder_days' => '15',
            'staff_same_day_edit_only' => '1',
            'bill_due_days' => '10',
            'delivery_sheet_generate_time' => '04:30',
        ], $settings),
    ], $overrides);
}

it('shows the farm settings with defaults', function () {
    $this->actingAs($this->admin)->get(route('settings.farm.edit'))
        ->assertOk()
        ->assertSee('Gokul Dairy')
        ->assertSee('name="settings[farmer_payment_cycle_days]" value="10"', false);
});

it('saves farm details and typed settings, keeping appearance', function () {
    $this->actingAs($this->admin)
        ->put(route('settings.farm.update'), farmPayload())
        ->assertRedirect(route('settings.farm.edit'))
        ->assertSessionHas('success');

    $farm = $this->farm->fresh();

    expect($farm->name)->toBe('Gokul Dairy Farm')
        ->and($farm->gstin)->toBe('09ABCDE1234F1Z5')
        ->and($farm->setting('farmer_payment_cycle_days'))->toBe(15)
        ->and($farm->setting('allow_negative_milk_stock'))->toBeFalse()
        ->and($farm->setting('staff_same_day_edit_only'))->toBeTrue()
        ->and($farm->setting('delivery_sheet_generate_time'))->toBe('04:30')
        ->and($farm->setting('appearance.primary_color'))->toBe('#112233');
});

it('validates ranges, time and GSTIN', function () {
    $this->actingAs($this->admin)
        ->put(route('settings.farm.update'), farmPayload(
            ['farmer_payment_cycle_days' => '0', 'cow_gestation_days' => '500', 'delivery_sheet_generate_time' => '25:00'],
            ['gstin' => 'ABC'],
        ))
        ->assertSessionHasErrors([
            'settings.farmer_payment_cycle_days', 'settings.cow_gestation_days', 'settings.delivery_sheet_generate_time', 'gstin',
        ]);
});

it('only edits the current farm and is forbidden without settings.manage', function () {
    $other = Farm::factory()->create(['name' => 'Other Farm']);

    $this->actingAs($this->admin)->put(route('settings.farm.update'), farmPayload());
    expect($other->fresh()->name)->toBe('Other Farm');

    $accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
    $this->actingAs($accountant)->get(route('settings.farm.edit'))->assertForbidden();
    $this->actingAs($accountant)->put(route('settings.farm.update'), farmPayload())->assertForbidden();
});
