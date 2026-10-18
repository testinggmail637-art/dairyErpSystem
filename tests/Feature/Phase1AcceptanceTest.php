<?php

/*
| Phase 1 acceptance criteria (spec Section 8), end to end over HTTP, in the order the spec lists them,
| on the production seed data (default farm, categories, sections, payment modes).
| Detailed behaviour is covered by the per-feature tests in tests/Feature/Expenses and tests/Feature/Reports.
*/

use App\Enums\RoleName;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\PaymentMode;
use App\Models\Section;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ExpenseReportService;
use App\Support\Money;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo('2026-10-18 10:00');
    $this->seed(DatabaseSeeder::class);

    $this->farm = Farm::where('code', 'MDF')->firstOrFail();
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
    $this->herdStaff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();

    $category = fn (string $name, ?int $parent = null) => ExpenseCategory::allFarms()
        ->where('farm_id', $this->farm->id)->where('name', $name)->where('parent_id', $parent)->firstOrFail();
    $this->feed = $category('Feed');
    $this->cattleFeed = $category('Cattle Feed', $this->feed->id);
    $this->health = $category('Health');
    $this->medicine = $category('Medicine', $this->health->id);
    $this->herd = Section::allFarms()->where('farm_id', $this->farm->id)->where('code', 'HERD')->firstOrFail();
    $this->cash = PaymentMode::allFarms()->where('farm_id', $this->farm->id)->where('code', 'CASH')->firstOrFail();
});

function acceptanceExpense(array $overrides = []): array
{
    $t = test();

    return array_merge([
        'expense_date' => now()->toDateString(), 'amount' => '1500', 'category_id' => $t->feed->id,
        'subcategory_id' => $t->cattleFeed->id, 'section_id' => $t->herd->id, 'is_credit' => '0',
        'payment_mode_id' => $t->cash->id, 'paid_to_text' => 'Local shop',
    ], $overrides);
}

it('1. an expense saved by staff appears immediately in the list and the reports — no status or approval anywhere', function () {
    $this->actingAs($this->herdStaff)->post(route('expenses.store'), acceptanceExpense(['amount' => '1234.50']))
        ->assertSessionHasNoErrors()->assertRedirect();

    $this->flushSession();
    $this->actingAs($this->accountant)->get(route('expenses.index'))->assertOk()
        ->assertSee('₹1,234.50')->assertSee('Local shop')
        ->assertDontSee('Approve')->assertDontSee('Pending');
    $this->get(route('reports.expenses', ['monthly', 'month' => '2026-10']))->assertOk()->assertSee('1,234.50');
    $this->get(route('reports.expenses', ['daily', 'from' => '2026-10-18', 'to' => '2026-10-18']))->assertOk()->assertSee('1,234.50');

    foreach (['status', 'approved_by', 'approved_at', 'approval_status'] as $column) {
        expect(Schema::hasColumn('expenses', $column))->toBeFalse();
    }
});

it('2. credit expense needs a vendor and raises the balance; a payment lowers it; a paid expense does not touch it (₹10,000 − ₹4,000 = ₹6,000)', function () {
    $this->actingAs($this->accountant);
    $vendor = Vendor::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Sharma Feeds', 'opening_balance' => '0.00']);

    $this->post(route('expenses.store'), acceptanceExpense(['is_credit' => '1', 'vendor_id' => null, 'payment_mode_id' => null]))
        ->assertSessionHasErrors(['vendor_id' => 'A credit (unpaid) expense needs a vendor.']);

    $this->post(route('expenses.store'), acceptanceExpense(['amount' => '10000', 'is_credit' => '1', 'vendor_id' => $vendor->id, 'paid_to_text' => null]))
        ->assertSessionHasNoErrors();
    $this->post(route('vendor-payments.store'), [
        'vendor_id' => $vendor->id, 'payment_date' => '2026-10-18', 'amount' => '4000', 'payment_mode_id' => $this->cash->id,
    ])->assertSessionHasNoErrors();
    $this->post(route('expenses.store'), acceptanceExpense(['amount' => '2500', 'vendor_id' => $vendor->id])) // paid now
        ->assertSessionHasNoErrors();

    expect(Vendor::withBalance()->find($vendor->id)->balance())->toBe('6000.00');
    $this->get(route('vendors.show', $vendor))->assertOk()->assertSee('₹6,000.00');
    $this->get(route('reports.expenses', 'vendors'))->assertOk()->assertSee('Sharma Feeds')->assertSee('6,000.00');
});

it('3. the subcategory must belong to the category (server-side)', function () {
    $this->actingAs($this->accountant)
        ->post(route('expenses.store'), acceptanceExpense(['subcategory_id' => $this->medicine->id]))
        ->assertSessionHasErrors(['subcategory_id' => 'This subcategory does not belong to the chosen category.']);

    expect(Expense::count())->toBe(0);
});

it('4. staff without expenses.edit can edit their own entry on the same day only', function () {
    $this->actingAs($this->herdStaff)->post(route('expenses.store'), acceptanceExpense())->assertSessionHasNoErrors();
    $mine = Expense::sole();
    $others = Expense::factory()->create(['farm_id' => $this->farm->id, 'created_by' => $this->accountant->id, 'expense_date' => '2026-10-18']);

    $this->put(route('expenses.update', $mine), acceptanceExpense(['amount' => '1600', 'reason' => 'Typo in amount']))->assertSessionHasNoErrors();
    expect($mine->fresh()->amount)->toBe('1600.00');
    $this->put(route('expenses.update', $others), acceptanceExpense(['reason' => 'Not mine']))->assertForbidden();

    $this->travelTo('2026-10-19 09:00');
    $this->put(route('expenses.update', $mine), acceptanceExpense(['amount' => '1700', 'reason' => 'Next day']))->assertForbidden();
    $this->delete(route('expenses.destroy', $mine), ['reason' => 'Next day'])->assertForbidden();
    expect($mine->fresh()->amount)->toBe('1600.00');
});

it('5. import: 3 valid + 2 invalid rows → preview shows 2 errors; import inserts exactly 3; the error file has 2 rows', function () {
    $this->actingAs($this->accountant);
    $row = fn (array $o = []) => importRow(array_merge(['section' => 'Own Farm/Herd', 'date' => '15-10-2026'], $o));

    $batch = uploadImport(importXlsx([
        $row(),
        $row(['amount' => '0']), // → error
        $row(['category' => 'health', 'subcategory' => 'MEDICINE', 'amount' => '350']),
        $row(['subcategory' => 'Medicine']), // not a subcategory of Feed → error
        $row(['amount' => '20', 'paid_to' => 'Tea stall']),
    ]));
    expect($batch->only(['total_rows', 'valid_rows', 'error_rows']))->toBe(['total_rows' => 5, 'valid_rows' => 3, 'error_rows' => 2]);

    $this->get(route('expense-imports.show', [$batch, 'show' => 'errors']))->assertOk()
        ->assertSee('The amount must be more than zero.')
        ->assertSee('is not an active subcategory of Feed', false);

    $this->post(route('expense-imports.import', $batch))->assertRedirect();
    expect(Expense::where('import_batch_id', $batch->id)->count())->toBe(3)->and(Expense::count())->toBe(3);

    $response = $this->get(route('expense-imports.errors', $batch))->assertOk();
    $path = tempnam(sys_get_temp_dir(), 'err').'.xlsx';
    file_put_contents($path, $response->streamedContent());
    $rows = IOFactory::load($path)->getSheetByName('Expenses')->toArray();

    expect($rows)->toHaveCount(3) // header + 2 error rows
        ->and(end($rows[0]))->toBe('errors');
});

it('6. the monthly report totals equal the sum of the expenses', function () {
    $this->actingAs($this->accountant);
    foreach ([['2026-10-01', '1250.50'], ['2026-10-09', '99.99'], ['2026-10-18', '10000']] as [$date, $amount]) {
        $this->post(route('expenses.store'), acceptanceExpense(['expense_date' => $date, 'amount' => $amount]))->assertSessionHasNoErrors();
    }
    $this->post(route('expenses.store'), acceptanceExpense(['category_id' => $this->health->id, 'subcategory_id' => $this->medicine->id, 'amount' => '650.01']));
    Expense::factory()->create(['farm_id' => $this->farm->id, 'expense_date' => '2026-09-30', 'amount' => '777.00']); // other month

    $sum = Money::sum(Expense::whereBetween('expense_date', ['2026-10-01', '2026-10-31'])->pluck('amount'));
    $table = app(ExpenseReportService::class)->monthly('2026-10');

    expect($sum)->toBe('12000.50')
        ->and(collect($table->totals)->last())->toBe($sum);
    $this->get(route('reports.expenses', ['monthly', 'month' => '2026-10']))->assertOk()->assertSee('12,000.50');
});
