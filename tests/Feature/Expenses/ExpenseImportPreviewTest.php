<?php

use App\Enums\ImportBatchStatus;
use App\Enums\RoleName;
use App\Imports\ExpenseImportColumns;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseImportBatch;
use App\Models\ExpenseImportRow;
use App\Models\Farm;
use App\Models\PaymentMode;
use App\Models\Section;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    Storage::fake('local');

    $this->farm = Farm::factory()->create();
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();

    $this->feed = ExpenseCategory::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Feed']);
    $this->cattleFeed = ExpenseCategory::factory()->childOf($this->feed)->create(['name' => 'Cattle Feed']);
    $this->health = ExpenseCategory::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Health']);
    ExpenseCategory::factory()->childOf($this->health)->create(['name' => 'Medicine']);
    $this->section = Section::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Own Herd']);
    $this->cash = PaymentMode::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Cash']);
    $this->vendor = Vendor::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Sharma Feeds']);
    Vendor::factory()->create(['farm_id' => Farm::factory()->create()->id, 'name' => 'Foreign Vendor']);
});

/** A row in template column order: date, amount, category, subcategory, section, payment_mode, vendor, paid_to, credit, quantity, unit, note. */
function importRow(array $overrides = []): array
{
    return array_values(array_merge([
        'date' => '15-10-2026', 'amount' => '1250.50', 'category' => 'Feed', 'subcategory' => 'Cattle Feed',
        'section' => 'Own Herd', 'payment_mode' => 'Cash', 'vendor' => '', 'paid_to' => 'Ramesh',
        'credit' => 'no', 'quantity' => '', 'unit' => '', 'note' => '',
    ], $overrides));
}

function importXlsx(array $rows, ?array $headers = null): UploadedFile
{
    $book = new Spreadsheet;
    $book->getActiveSheet()->setTitle('Expenses')->fromArray([$headers ?? array_values(ExpenseImportColumns::COLUMNS), ...$rows], null, 'A1', true);
    $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    (new Xlsx($book))->save($path);

    return new UploadedFile($path, 'expenses.xlsx', null, null, true);
}

function importCsv(array $rows, ?array $headers = null): UploadedFile
{
    $lines = array_map(fn ($r) => implode(',', array_map(fn ($c) => '"'.str_replace('"', '""', (string) $c).'"', $r)),
        [$headers ?? array_values(ExpenseImportColumns::COLUMNS), ...$rows]);

    return UploadedFile::fake()->createWithContent('expenses.csv', implode("\n", $lines));
}

function uploadImport(UploadedFile $file): ExpenseImportBatch
{
    test()->post(route('expense-imports.store'), ['file' => $file])->assertSessionHasNoErrors()->assertRedirect();

    return ExpenseImportBatch::query()->latest('id')->firstOrFail();
}

it('uploads 3 valid + 2 invalid rows and the preview shows the 2 errors (Phase 1 acceptance)', function () {
    $this->travelTo('2026-10-18');
    $this->actingAs($this->accountant);

    $batch = uploadImport(importXlsx([
        importRow(),
        importRow(['amount' => '-5']),
        importRow(['date' => '10-10-2026', 'category' => 'Health', 'subcategory' => 'Medicine', 'amount' => '300']),
        importRow(['category' => 'Unknown']),
        importRow(['date' => '11-10-2026', 'credit' => 'yes', 'vendor' => 'Sharma Feeds', 'payment_mode' => '', 'quantity' => '5', 'unit' => 'bag']),
    ]));

    expect($batch->only(['total_rows', 'valid_rows', 'error_rows', 'imported_rows']))
        ->toBe(['total_rows' => 5, 'valid_rows' => 3, 'error_rows' => 2, 'imported_rows' => 0])
        ->and($batch->status)->toBe(ImportBatchStatus::Previewed)
        ->and(Expense::count())->toBe(0) // nothing saved yet
        ->and($batch->rows()->where('is_valid', false)->pluck('row_number')->all())->toBe([3, 5]);

    Storage::disk('local')->assertExists($batch->stored_path);
    expect($batch->stored_path)->toStartWith("imports/expenses/{$this->farm->id}/");

    $this->get(route('expense-imports.show', $batch))
        ->assertOk()
        ->assertSee('The amount must be more than zero.')
        ->assertSee('&quot;Unknown&quot; is not an active main category of this farm.', false)
        ->assertSee('table-danger');

    // filter: only the error rows (AJAX fragment)
    $this->get(route('expense-imports.show', [$batch, 'show' => 'errors']), ['X-Ajax-List' => '1'])
        ->assertOk()->assertHeader('X-Ajax-List-Fragment', '1')
        ->assertSee('Unknown')->assertDontSee('Medicine');
});

it('matches names case-insensitively and trimmed, reads Excel serial dates and stores what was read', function () {
    $this->travelTo('2026-10-18');
    $this->actingAs($this->accountant);

    $batch = uploadImport(importXlsx([
        importRow([
            'date' => Date::PHPToExcel(new DateTime('2026-10-01')), // numeric date cell
            'amount' => 1250.5, 'category' => '  feed ', 'subcategory' => 'CATTLE  feed', 'section' => 'own herd',
            'payment_mode' => 'cash', 'vendor' => 'sharma feeds', 'credit' => 'YES', 'unit' => 'Bag', 'quantity' => 2,
        ]),
    ], ['Date', 'Amount', 'Category', 'Subcategory', 'Section', 'Payment Mode', 'Vendor', 'Paid To', 'Credit', 'Quantity', 'Unit', 'Note']));

    $row = $batch->rows()->sole();
    expect($row->is_valid)->toBeTrue()
        ->and($row->errors)->toBeNull()
        ->and($row->payload['date'])->toBe('01-10-2026')
        ->and($row->payload['amount'])->toBe('1250.5')
        ->and($row->payload['quantity'])->toBe('2');
});

it('reads CSV saved by Excel: BOM, ; separator and Windows-1252 text', function () {
    $this->travelTo('2026-10-18');
    $this->actingAs($this->accountant);

    $csv = "\xEF\xBB\xBFDate;Amount;Category;Section;Payment_mode;Paid_to\r\n15/10/2026;\"1,250.50\";Feed;Own Herd;Cash;Ramesh\r\n;;;;;\r\n";
    $row = uploadImport(UploadedFile::fake()->createWithContent('excel.csv', $csv))->rows()->sole();
    expect($row->is_valid)->toBeTrue()->and($row->payload['amount'])->toBe('1,250.50');

    $latin = mb_convert_encoding("date,amount,category,section,payment_mode,note\n15-10-2026,10,Feed,Own Herd,Cash,Café bill\n", 'Windows-1252', 'UTF-8');
    $row = uploadImport(UploadedFile::fake()->createWithContent('latin.csv', $latin))->rows()->sole();
    expect($row->payload['note'])->toBe('Café bill');
});

it('checks every row with the same rules as the expense form', function (array $overrides, string $column, string $message) {
    $this->travelTo('2026-10-18');
    $this->actingAs($this->accountant);

    $row = uploadImport(importCsv([importRow($overrides)]))->rows()->sole();

    expect($row->is_valid)->toBeFalse()
        ->and($row->errorsFor($column))->toContain($message);
})->with([
    'missing date' => [['date' => ''], 'date', 'The date is required.'],
    'bad date' => [['date' => '2026/10/15'], 'date', 'Write the date as DD-MM-YYYY, e.g. 15-10-2026.'],
    'impossible date' => [['date' => '31-02-2026'], 'date', 'Write the date as DD-MM-YYYY, e.g. 15-10-2026.'],
    'future date' => [['date' => '19-10-2026'], 'date', 'The expense date cannot be in the future.'],
    'zero amount' => [['amount' => '0'], 'amount', 'The amount must be more than zero.'],
    'three decimals' => [['amount' => '10.555'], 'amount', 'Enter the amount in rupees with up to 2 decimals.'],
    'text amount' => [['amount' => 'abc'], 'amount', 'Enter the amount in rupees with up to 2 decimals.'],
    'subcategory of another category' => [['subcategory' => 'Medicine'], 'subcategory', '"Medicine" is not an active subcategory of Feed.'],
    'unknown section' => [['section' => 'Nowhere'], 'section', '"Nowhere" is not an active section of this farm.'],
    'credit without vendor' => [['credit' => 'yes', 'payment_mode' => ''], 'vendor', 'A credit (unpaid) expense needs a vendor.'],
    'other farm vendor' => [['vendor' => 'Foreign Vendor'], 'vendor', '"Foreign Vendor" is not an active vendor of this farm. Add the vendor first.'],
    'paid without payment mode' => [['payment_mode' => ''], 'payment_mode', 'Choose how it was paid, or mark the expense as credit.'],
    'bad credit value' => [['credit' => 'maybe'], 'credit', 'Write yes or no.'],
    'quantity without unit' => [['quantity' => '5'], 'unit', 'Choose the unit for the quantity.'],
    'bad unit' => [['quantity' => '5', 'unit' => 'tonne'], 'unit', 'Use one of: kg, litre, piece, bag, dose, hour, day, trip, other.'],
]);

it('refuses inactive categories and vendors', function () {
    $this->travelTo('2026-10-18');
    $this->actingAs($this->accountant);
    $this->feed->update(['is_active' => false]);
    $this->vendor->update(['is_active' => false]);

    $row = uploadImport(importCsv([importRow(['vendor' => 'Sharma Feeds'])]))->rows()->sole();

    expect(array_keys($row->errors))->toBe(['category', 'vendor']);
});

it('warns about possible duplicates without blocking them', function () {
    $this->travelTo('2026-10-18');
    $this->actingAs($this->accountant);
    Expense::factory()->create([
        'farm_id' => $this->farm->id, 'expense_date' => '2026-10-15', 'amount' => '1250.50', 'category_id' => $this->feed->id,
        'section_id' => $this->section->id, 'payment_mode_id' => $this->cash->id, 'paid_to_text' => 'Ramesh',
    ]);

    $batch = uploadImport(importCsv([
        importRow(['paid_to' => 'RAMESH']),          // same as the saved expense
        importRow(['date' => '14-10-2026', 'amount' => '99']),
        importRow(['date' => '14-10-2026', 'amount' => '99.00']), // same as the row above
        importRow(['paid_to' => 'Suresh']),          // different paid to → no warning
    ]));

    $warnings = $batch->rows()->orderBy('row_number')->pluck('warnings', 'row_number')->all();

    expect($batch->valid_rows)->toBe(4)
        ->and($batch->warning_rows)->toBe(2)
        ->and($warnings[2][0])->toContain('already exists')
        ->and($warnings[3])->toBeNull()
        ->and($warnings[4])->toBe(['Possible duplicate of row 3 in this file.'])
        ->and($warnings[5])->toBeNull();

    $this->get(route('expense-imports.show', [$batch, 'show' => 'warnings']))->assertOk()->assertSee('Possible duplicate of row 3');
});

it('rejects files over 5,000 rows, without the required columns, empty or of the wrong type', function () {
    $this->actingAs($this->accountant);

    $this->post(route('expense-imports.store'), ['file' => importCsv(array_fill(0, 5001, importRow()))])
        ->assertSessionHasErrors(['file' => 'The file has more than 5,000 rows. Split it into smaller files.']);

    $this->post(route('expense-imports.store'), ['file' => importCsv([['15-10-2026', '10']], ['date', 'amount'])])
        ->assertSessionHasErrors(['file' => 'The header row is missing: category, section. Use the downloaded template.']);

    $this->post(route('expense-imports.store'), ['file' => importCsv([])])
        ->assertSessionHasErrors(['file' => 'The file has no expense rows below the header.']);

    $this->post(route('expense-imports.store'), ['file' => UploadedFile::fake()->create('expenses.pdf', 10)])
        ->assertSessionHasErrors(['file' => 'Upload an xlsx, xls or csv file.']);

    $this->post(route('expense-imports.store'), ['file' => UploadedFile::fake()->create('expenses.xlsx', 6000)])
        ->assertSessionHasErrors(['file' => 'The file may not be larger than 5 MB.']);

    expect(ExpenseImportBatch::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('imports'))->toBe([]); // rejected files are not kept
});

it('accepts exactly 5,000 rows', function () {
    $this->travelTo('2026-10-18');
    $this->actingAs($this->accountant);

    $batch = uploadImport(importCsv(array_fill(0, 5000, importRow())));

    expect($batch->total_rows)->toBe(5000)
        ->and(ExpenseImportRow::where('import_batch_id', $batch->id)->count())->toBe(5000)
        ->and($batch->warning_rows)->toBe(4999); // all the same row
});

it('cancels a previewed upload', function () {
    $this->travelTo('2026-10-18');
    $this->actingAs($this->accountant);
    $batch = uploadImport(importCsv([importRow()]));

    $this->post(route('expense-imports.cancel', $batch))->assertRedirect(route('expense-imports.index'));

    expect($batch->fresh()->status)->toBe(ImportBatchStatus::Cancelled);
    Storage::disk('local')->assertMissing($batch->stored_path);

    $this->post(route('expense-imports.cancel', $batch))->assertSessionHas('error');
});

it('needs expenses.import and keeps batches inside their farm', function () {
    $this->travelTo('2026-10-18');
    $this->actingAs($this->accountant);
    $batch = uploadImport(importCsv([importRow()]));

    $herdStaff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $this->flushSession();
    $this->actingAs($herdStaff)->post(route('expense-imports.store'), ['file' => importCsv([importRow()])])->assertForbidden();
    $this->actingAs($herdStaff)->get(route('expense-imports.show', $batch))->assertForbidden();

    $otherFarm = Farm::factory()->create();
    $otherAccountant = User::factory()->forFarm($otherFarm)->withRole(RoleName::Accountant)->create();
    $this->flushSession();
    $this->actingAs($otherAccountant)->get(route('expense-imports.show', $batch))->assertNotFound();
    $this->actingAs($otherAccountant)->post(route('expense-imports.cancel', $batch))->assertNotFound();
});
