<?php

use App\Enums\AnimalStatus;
use App\Enums\RoleName;
use App\Models\Animal;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\PaymentMode;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->travelTo('2026-10-19 10:00');
    $this->farm = Farm::factory()->create();
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create(); // no animals.view
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();

    $this->health = ExpenseCategory::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Health']);
    $this->section = Section::factory()->create(['farm_id' => $this->farm->id]);
    $this->cash = PaymentMode::factory()->create(['farm_id' => $this->farm->id]);
    $this->cow = Animal::factory()->create(['farm_id' => $this->farm->id, 'tag_no' => 'C-101', 'name' => 'Gauri']);
});

function vetBill(array $overrides = []): array
{
    $t = test();

    return array_merge([
        'expense_date' => '2026-10-19', 'amount' => '850', 'category_id' => $t->health->id, 'section_id' => $t->section->id,
        'is_credit' => '0', 'payment_mode_id' => $t->cash->id, 'paid_to_text' => 'Dr. Verma', 'animal_id' => $t->cow->id,
    ], $overrides);
}

it('saves an expense for one animal and shows it on the expense and on the animal', function () {
    $this->actingAs($this->accountant)->post(route('expenses.store'), vetBill())->assertSessionHasNoErrors();

    $expense = Expense::sole();
    expect($expense->animal_id)->toBe($this->cow->id);

    $this->get(route('expenses.show', $expense))->assertOk()->assertSee('C-101 · Gauri');
    $this->get(route('expenses.index'))->assertOk()->assertSee('C-101 · Gauri');

    $this->flushSession();
    $this->actingAs($this->owner)->get(route('animals.show', $this->cow))->assertOk()
        ->assertSee('₹850.00')
        ->assertSee(route('expenses.index', ['animal_id' => $this->cow->id, 'all' => 1]));
});

it('keeps the animal optional and refuses animals of another farm or deleted ones', function () {
    $this->actingAs($this->accountant);
    $this->post(route('expenses.store'), vetBill(['animal_id' => '']))->assertSessionHasNoErrors();
    expect(Expense::sole()->animal_id)->toBeNull();

    $foreign = Animal::factory()->create(['farm_id' => Farm::factory()->create()->id]);
    $deleted = Animal::factory()->create(['farm_id' => $this->farm->id]);
    $deleted->delete();

    $this->post(route('expenses.store'), vetBill(['animal_id' => $foreign->id]))->assertSessionHasErrors(['animal_id' => 'Choose an animal of this farm.']);
    $this->post(route('expenses.store'), vetBill(['animal_id' => $deleted->id]))->assertSessionHasErrors('animal_id');

    // a sold / deceased animal can still get a bill
    $this->cow->update(['status' => AnimalStatus::Deceased]);
    $this->post(route('expenses.store'), vetBill())->assertSessionHasNoErrors();
});

it('filters the expense list and export by animal', function () {
    $other = Animal::factory()->create(['farm_id' => $this->farm->id, 'tag_no' => 'C-202']);
    Expense::factory()->create(['farm_id' => $this->farm->id, 'animal_id' => $this->cow->id, 'amount' => '111.00', 'expense_date' => '2026-10-10']);
    Expense::factory()->create(['farm_id' => $this->farm->id, 'animal_id' => $other->id, 'amount' => '222.00', 'expense_date' => '2026-10-10']);

    $this->actingAs($this->accountant)->get(route('expenses.index', ['animal_id' => $this->cow->id, 'all' => 1]))->assertOk()
        ->assertSee('₹111.00')->assertDontSee('₹222.00')
        ->assertSee('<option value="'.$this->cow->id.'" selected>C-101 · Gauri</option>', false);
});

it('lets expense users search animals without animals.view, but not users without either right', function () {
    $this->actingAs($this->accountant)->getJson(route('animals.search', ['q' => 'C-1']))->assertOk()
        ->assertJsonPath('results.0.text', 'C-101 · Gauri');
    $this->actingAs($this->accountant)->get(route('animals.index'))->assertForbidden(); // still no animal screens

    $delivery = User::factory()->forFarm($this->farm)->withRole(RoleName::DeliveryBoy)->create();
    $this->flushSession();
    $this->actingAs($delivery)->getJson(route('animals.search', ['q' => 'C-1']))->assertForbidden();
});

it('blocks deleting an animal that has expenses, also at database level', function () {
    Expense::factory()->create(['farm_id' => $this->farm->id, 'animal_id' => $this->cow->id]);

    $this->actingAs($this->owner)->delete(route('animals.destroy', $this->cow), ['reason' => 'Mistake'])->assertSessionHas('error');
    expect(fn () => DB::table('animals')->where('id', $this->cow->id)->delete())->toThrow(QueryException::class);
});
