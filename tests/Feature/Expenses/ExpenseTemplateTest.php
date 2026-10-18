<?php

use App\Enums\RoleName;
use App\Imports\ExpenseImportColumns;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\PaymentMode;
use App\Models\Section;
use App\Models\User;
use App\Models\Vendor;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();

    $feed = ExpenseCategory::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Feed', 'sort_order' => 1]);
    ExpenseCategory::factory()->childOf($feed)->create(['name' => 'Cattle Feed']);
    ExpenseCategory::factory()->childOf($feed)->create(['name' => 'Old Sub', 'is_active' => false]);
    ExpenseCategory::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Health', 'sort_order' => 2]);
    ExpenseCategory::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Closed Category', 'is_active' => false]);
    Section::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Own Herd']);
    PaymentMode::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Cash']);
    Vendor::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Sharma Feeds']);
    Vendor::factory()->create(['farm_id' => Farm::factory()->create()->id, 'name' => 'Foreign Vendor']);
});

function downloadTemplate(User $user): Spreadsheet
{
    $response = test()->actingAs($user)->get(route('expense-imports.template'))->assertOk()->assertDownload('expenses_template.xlsx');

    return IOFactory::load($response->baseResponse->getFile()->getPathname());
}

it('downloads expenses_template.xlsx with the spec columns on the Expenses sheet', function () {
    $book = downloadTemplate($this->accountant);

    expect($book->getSheetNames())->toBe(['Expenses', 'Lists']);

    $expenses = $book->getSheetByName('Expenses');
    expect($expenses->rangeToArray('A1:L1')[0])->toBe([
        'date (DD-MM-YYYY)', 'amount', 'category', 'subcategory', 'section', 'payment_mode',
        'vendor', 'paid_to', 'credit (yes/no)', 'quantity', 'unit', 'note',
    ])->and(array_filter($expenses->rangeToArray('A2:L2')[0]))->toBe([]) // header only (row 2 holds just the dropdowns)
        ->and($expenses->getComment('A1')->getText()->getPlainText())->toContain('DD-MM-YYYY');

    // dropdowns read from the Lists sheet
    $validation = $expenses->getCell(ExpenseImportColumns::letter('category').'2')->getDataValidation();
    expect($validation->getType())->toBe('list')->and($validation->getFormula1())->toBe("'Lists'!\$A\$2:\$A\$3");
});

it('lists only this farm\'s active categories, subcategories, sections, payment modes and vendors', function () {
    $lists = downloadTemplate($this->accountant)->getSheetByName('Lists')->toArray();
    $column = fn (int $i) => array_values(array_filter(array_column(array_slice($lists, 1), $i)));

    expect($lists[0])->toBe(['Categories', 'Subcategories (category › subcategory)', 'Sections', 'Payment modes', 'Vendors', 'Units', 'Credit'])
        ->and($column(0))->toBe(['Feed', 'Health'])
        ->and($column(1))->toBe(['Feed › Cattle Feed'])
        ->and($column(2))->toBe(['Own Herd'])
        ->and($column(3))->toBe(['Cash'])
        ->and($column(4))->toBe(['Sharma Feeds'])
        ->and($column(5))->toBe(['kg', 'litre', 'piece', 'bag', 'dose', 'hour', 'day', 'trip', 'other'])
        ->and($column(6))->toBe(['no', 'yes']);
});

it('shows the import page with the template link and column help, for expenses.import only', function () {
    $this->actingAs($this->accountant)->get(route('expense-imports.index'))
        ->assertOk()
        ->assertSee(route('expense-imports.template'), false)
        ->assertSee('credit (yes/no)');

    $this->actingAs($this->accountant)->get(route('profile.edit'))->assertSee(route('expense-imports.index'), false); // menu

    $herdStaff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $this->actingAs($herdStaff)->get(route('expense-imports.index'))->assertForbidden();
    $this->actingAs($herdStaff)->get(route('expense-imports.template'))->assertForbidden();
});
