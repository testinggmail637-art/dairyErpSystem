<?php

use App\Enums\RoleName;
use App\Models\Animal;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\PaymentMode;
use App\Models\Treatment;
use App\Models\User;
use App\Support\CurrentFarm;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->farm = Farm::where('code', 'MDF')->firstOrFail();
    app(CurrentFarm::class)->set($this->farm);
    $this->travelTo('2026-11-13 10:00');
    $this->staff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $this->actingAs($this->staff);
    $this->animal = Animal::factory()->create(['farm_id' => $this->farm->id, 'tag_no' => 'C-101', 'name' => 'Gauri']);
    $this->cash = PaymentMode::where('code', 'CASH')->value('id');
});

function treat(array $overrides = [], ?Treatment $treatment = null): TestResponse
{
    $data = array_merge(['animal_id' => test()->animal->id, 'treatment_date' => '2026-11-12', 'diagnosis' => 'Mastitis', 'medicine' => 'Ceftriaxone',
        'vet_name' => 'Dr. Kale', 'cost' => '800', 'milk_withdrawal_until' => '2026-11-16', 'record_as_expense' => '1', 'payment_mode_id' => test()->cash], $overrides);

    return $treatment
        ? test()->put(route('health.treatments.update', $treatment), $data + ['reason' => 'Vet bill came higher'])
        : test()->post(route('health.treatments.store'), $data);
}

it('records ₹800 as exactly one expense and updates it to ₹900 — no duplicate (spec acceptance)', function () {
    treat()->assertSessionHasNoErrors()->assertRedirect(route('health.treatments.index'));

    $treatment = Treatment::sole();
    $expense = Expense::sole();
    expect($treatment->expense_id)->toBe($expense->id)
        ->and($expense)->amount->toBe('800.00')->animal_id->toBe($this->animal->id)->is_credit->toBeFalse()
        ->payment_mode_id->toBe($this->cash)->paid_to_text->toBe('Dr. Kale')
        ->and($expense->category->name)->toBe('Health')
        ->and($expense->subcategory->name)->toBe('Medicine')
        ->and($expense->section->code)->toBe('HERD')
        ->and($expense->isAutoCreated())->toBeTrue();

    $this->put(route('health.treatments.update', $treatment), ['animal_id' => $this->animal->id, 'treatment_date' => '2026-11-12',
        'diagnosis' => 'Mastitis', 'cost' => '900'])->assertSessionHasErrors('reason'); // no reason, no change
    treat(['cost' => '900'], $treatment)->assertSessionHasNoErrors();
    expect(Expense::count())->toBe(1)->and(Expense::sole()->amount)->toBe('900.00')->and($treatment->fresh()->cost)->toBe('900.00');

    // the expense itself can only be changed from the treatment
    $owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->actingAs($owner)->get(route('expenses.edit', $expense))->assertForbidden();
});

it('keeps the cost as information only when unticked or ₹0, and removes the expense when unticked later', function () {
    treat(['record_as_expense' => '0', 'payment_mode_id' => ''])->assertSessionHasNoErrors();
    treat(['cost' => '0', 'diagnosis' => 'Check-up', 'payment_mode_id' => ''])->assertSessionHasNoErrors();
    expect(Treatment::count())->toBe(2)->and(Expense::count())->toBe(0);

    treat(['payment_mode_id' => ''])->assertSessionHasErrors('payment_mode_id'); // record as expense needs how it was paid

    treat()->assertSessionHasNoErrors();
    $treatment = Treatment::whereNotNull('expense_id')->sole();
    treat(['record_as_expense' => '0'], $treatment)->assertSessionHasNoErrors();
    expect($treatment->fresh()->expense_id)->toBeNull()->and(Expense::count())->toBe(0);
});

it('deletes a treatment with its expense (with a reason) and lists animals under milk withdrawal', function () {
    treat()->assertSessionHasNoErrors();
    treat(['milk_withdrawal_until' => '2026-11-12', 'diagnosis' => 'Fever', 'record_as_expense' => '0'])->assertSessionHasNoErrors();
    treat(['milk_withdrawal_until' => '2026-11-11'])->assertSessionHasErrors('milk_withdrawal_until'); // before the treatment date

    $this->get(route('health.treatments.index'))->assertOk()->assertSee('Gauri')->assertSee('₹1,600.00');
    $this->get(route('health.treatments.index', ['withdrawal' => 1]))->assertOk()->assertSee('Mastitis')->assertDontSee('Fever');

    $treatment = Treatment::where('diagnosis', 'Mastitis')->sole();
    $this->delete(route('health.treatments.destroy', $treatment))->assertSessionHasErrors('reason');
    $this->delete(route('health.treatments.destroy', $treatment), ['reason' => 'Entered twice'])->assertSessionHasNoErrors();
    expect(Treatment::count())->toBe(1)->and(Expense::count())->toBe(0);
});

it('is for health.view to see and health.manage to change', function () {
    $accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create(); // no health rights
    $this->actingAs($accountant)->get(route('health.treatments.index'))->assertForbidden();
    treat()->assertForbidden();

    $this->actingAs($this->staff)->get(route('health.treatments.create', ['animal_id' => $this->animal->id]))->assertOk()->assertSee('C-101');
});
