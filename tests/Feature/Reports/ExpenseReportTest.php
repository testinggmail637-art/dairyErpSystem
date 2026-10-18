<?php

use App\Enums\RoleName;
use App\Exports\ReportTableExport;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\PaymentMode;
use App\Models\Section;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Services\ExpenseReportService;
use App\Support\CurrentFarm;
use App\Support\Money;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
    app(CurrentFarm::class)->set($this->farm);

    $this->feed = ExpenseCategory::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Feed', 'sort_order' => 1]);
    $this->cattleFeed = ExpenseCategory::factory()->childOf($this->feed)->create(['name' => 'Cattle Feed']);
    $this->health = ExpenseCategory::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Health', 'sort_order' => 2]);
    $this->herd = Section::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Herd']);
    $this->office = Section::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Office']);
    $this->cash = PaymentMode::factory()->create(['farm_id' => $this->farm->id]);
    $this->vendor = Vendor::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Sharma Feeds']);
});

function spend(string $date, string $amount, $category, $section, array $extra = []): Expense
{
    $t = test();

    return Expense::factory()->create(array_merge([
        'farm_id' => $t->farm->id, 'expense_date' => $date, 'amount' => $amount, 'category_id' => $category->id,
        'section_id' => $section->id, 'payment_mode_id' => $t->cash->id,
    ], $extra));
}

function seedOctober(): void
{
    $t = test();
    spend('2026-10-01', '1000.00', $t->feed, $t->herd, ['subcategory_id' => $t->cattleFeed->id]);
    spend('2026-10-01', '250.50', $t->health, $t->herd);
    spend('2026-10-03', '499.50', $t->feed, $t->office);
    Expense::factory()->credit($t->vendor)->create(['expense_date' => '2026-10-03', 'amount' => '10000.00', 'category_id' => $t->feed->id, 'section_id' => $t->herd->id, 'subcategory_id' => $t->cattleFeed->id]);
    spend('2026-10-05', '777.00', $t->health, $t->office)->delete();            // deleted → never counted
    spend('2026-09-30', '5000.00', $t->feed, $t->herd);                        // other month
    Expense::factory()->create(['farm_id' => Farm::factory()->create()->id, 'expense_date' => '2026-10-02', 'amount' => '999.00']); // other farm
}

it('spec acceptance: the monthly report totals equal the sum of the month\'s expenses', function () {
    seedOctober();
    $table = app(ExpenseReportService::class)->monthly('2026-10');

    $sum = Money::sum(Expense::query()->whereBetween('expense_date', ['2026-10-01', '2026-10-31'])->pluck('amount'));

    expect($sum)->toBe('11750.00')
        ->and(collect($table->totals)->last())->toBe($sum)
        ->and(array_column($table->columns, 'label'))->toBe(['Category', 'Herd', 'Office', 'Total'])
        ->and($table->rows)->toBe([
            ['Feed', '11000.00', '499.50', '11499.50'],
            ['Health', '250.50', '0.00', '250.50'],
        ])
        ->and($table->totals)->toBe(['Total', '11250.50', '499.50', '11750.00']);
});

it('summarises each day with paid, credit and total', function () {
    seedOctober();
    $table = app(ExpenseReportService::class)->daily('2026-10-01', '2026-10-31');

    expect($table->rows)->toBe([
        ['2026-10-01', 2, '1250.50', '0.00', '1250.50'],
        ['2026-10-03', 2, '499.50', '10000.00', '10499.50'],
    ])->and($table->totals)->toBe(['Total', 4, '1750.00', '10000.00', '11750.00']);
});

it('breaks categories into subcategories with their share of the total', function () {
    seedOctober();
    $table = app(ExpenseReportService::class)->categoryWise('2026-10-01', '2026-10-31');

    expect($table->rows)->toBe([
        ['Feed', '', 3, '11499.50', '97.9'],
        ['', '(no subcategory)', 1, '499.50', '4.3'],
        ['', 'Cattle Feed', 2, '11000.00', '93.6'],
        ['Health', '', 1, '250.50', '2.1'],
        ['', '(no subcategory)', 1, '250.50', '2.1'],
    ])->and($table->totals)->toBe(['Total', '', 4, '11750.00', '100.0']);
});

it('lists vendor outstanding, largest first, without zero balances', function () {
    $zero = Vendor::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Settled Vendor']);
    $small = Vendor::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Small Vendor', 'opening_balance' => '300.00']);
    Expense::factory()->credit($this->vendor)->create(['amount' => '10000.00', 'category_id' => $this->feed->id, 'section_id' => $this->herd->id]);
    VendorPayment::factory()->create(['vendor_id' => $this->vendor->id, 'farm_id' => $this->farm->id, 'amount' => '4000.00', 'payment_mode_id' => $this->cash->id]);

    $table = app(ExpenseReportService::class)->vendorOutstanding();

    expect(array_column($table->rows, 0))->toBe(['Sharma Feeds', 'Small Vendor'])
        ->and(array_column($table->rows, 5))->toBe(['6000.00', '300.00'])
        ->and(collect($table->totals)->last())->toBe('6300.00');
});

it('shows the reports on screen with filters, and downloads Excel and PDF', function () {
    seedOctober();
    Excel::fake();

    $this->actingAs($this->accountant)->get(route('reports.index'))
        ->assertOk()->assertSee(route('reports.expenses', 'monthly'), false);

    $this->actingAs($this->accountant)->get(route('reports.expenses', ['monthly', 'month' => '2026-10']))
        ->assertOk()->assertSee('October 2026')->assertSeeInOrder(['Feed', '₹11,499.50', 'Health', '₹250.50', '₹11,750.00']);

    $this->actingAs($this->accountant)->get(route('reports.expenses', ['daily', 'from' => '2026-10-01', 'to' => '2026-10-31']))
        ->assertSee('01-10-2026')->assertSee('₹10,499.50');

    $this->actingAs($this->accountant)->withHeaders(['X-Ajax-List' => '1'])
        ->get(route('reports.expenses', ['category', 'from' => '2026-10-01', 'to' => '2026-10-31']))
        ->assertHeader('X-Ajax-List-Fragment', '1')->assertSee('97.9 %');

    $this->actingAs($this->accountant)->get(route('reports.expenses', ['monthly', 'month' => '2026-10', 'format' => 'excel']))->assertOk();
    Excel::assertDownloaded('monthly-expenses-by-category-and-section-october-2026.xlsx', function (ReportTableExport $export) {
        $rows = $export->array();

        return $rows[0] === ['Monthly expenses by category and section'] && end($rows) === ['Total', '11250.50', '499.50', '11750.00'];
    });

    $this->actingAs($this->accountant)->get(route('reports.expenses', ['vendors', 'format' => 'pdf']))
        ->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('keeps text such as "Total" in a date column as text (screen, Excel, PDF)', function () {
    seedOctober();
    $table = app(ExpenseReportService::class)->daily('2026-10-01', '2026-10-31');

    expect($table->display(0, 'Total'))->toBe('Total')
        ->and($table->display(0, '2026-10-01'))->toBe('01-10-2026')
        ->and(collect((new ReportTableExport($table))->array())->last()[0])->toBe('Total');

    $this->actingAs($this->accountant)->get(route('reports.expenses', ['daily', 'from' => '2026-10-01', 'to' => '2026-10-31']))
        ->assertOk()->assertSeeInOrder(['01-10-2026', '03-10-2026', 'Total', '₹11,750.00']);
    $this->actingAs($this->accountant)->get(route('reports.expenses', ['daily', 'from' => '2026-10-01', 'to' => '2026-10-31', 'format' => 'pdf']))
        ->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('validates periods and needs reports.view', function () {
    $this->actingAs($this->accountant)->get(route('reports.expenses', ['daily', 'from' => '2026-10-10', 'to' => '2026-10-01']))
        ->assertSessionHasErrors('to');

    $herdStaff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $this->actingAs($herdStaff)->get(route('reports.index'))->assertForbidden();
    $this->actingAs($herdStaff)->get(route('reports.expenses', 'daily'))->assertForbidden();

    $this->actingAs($this->accountant)->get('/reports/expenses/unknown')->assertNotFound();
});
