<?php

use App\Enums\ImportBatchStatus;
use App\Enums\RoleName;
use App\Models\Activity;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\PaymentMode;
use App\Models\Section;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo('2026-10-18 11:00');

    $this->farm = Farm::factory()->create();
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();

    $this->feed = ExpenseCategory::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Feed']);
    $this->cattleFeed = ExpenseCategory::factory()->childOf($this->feed)->create(['name' => 'Cattle Feed']);
    $this->section = Section::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Own Herd']);
    $this->cash = PaymentMode::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Cash']);
    $this->vendor = Vendor::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Sharma Feeds']);

    $this->actingAs($this->accountant);
});

function fiveRowBatch()
{
    return uploadImport(importCsv([
        importRow(),
        importRow(['amount' => '-5']),
        importRow(['date' => '10-10-2026', 'amount' => '300', 'subcategory' => '', 'note' => 'Bran']),
        importRow(['category' => 'Unknown']),
        importRow(['date' => '11-10-2026', 'credit' => 'yes', 'vendor' => 'Sharma Feeds', 'payment_mode' => '', 'quantity' => '5', 'unit' => 'bag', 'paid_to' => '']),
    ]));
}

it('imports exactly the 3 valid rows, tagged with the batch, and the error file has the 2 others (Phase 1 acceptance)', function () {
    $batch = fiveRowBatch();

    $this->post(route('expense-imports.import', $batch))
        ->assertRedirect(route('expense-imports.show', $batch))
        ->assertSessionHas('warning', '3 expenses imported. 2 rows with errors were skipped — download the error rows, fix them and upload again.');

    $batch->refresh();
    expect($batch->status)->toBe(ImportBatchStatus::Imported)
        ->and($batch->imported_rows)->toBe(3)
        ->and($batch->imported_at)->not->toBeNull()
        ->and(Expense::count())->toBe(3)
        ->and(Expense::where('import_batch_id', $batch->id)->count())->toBe(3);

    $credit = Expense::where('is_credit', true)->sole();
    expect($credit->only(['vendor_id', 'payment_mode_id', 'quantity', 'created_by', 'farm_id']))->toBe([
        'vendor_id' => $this->vendor->id, 'payment_mode_id' => null, 'quantity' => '5.00',
        'created_by' => $this->accountant->id, 'farm_id' => $this->farm->id,
    ])->and($credit->expense_date->toDateString())->toBe('2026-10-11')
        ->and($credit->unit->value)->toBe('bag');

    $paid = Expense::where('is_credit', false)->where('amount', '1250.50')->sole();
    expect($paid->only(['category_id', 'subcategory_id', 'section_id', 'payment_mode_id', 'paid_to_text', 'is_credit']))->toBe([
        'category_id' => $this->feed->id, 'subcategory_id' => $this->cattleFeed->id, 'section_id' => $this->section->id,
        'payment_mode_id' => $this->cash->id, 'paid_to_text' => 'Ramesh', 'is_credit' => false,
    ]);

    // every imported row points at its expense; the vendor now owes the credit amount
    expect($batch->rows()->whereNotNull('expense_id')->orderBy('row_number')->pluck('row_number')->all())->toBe([2, 4, 6])
        ->and($batch->rows()->where('row_number', 2)->value('expense_id'))->toBe($paid->id)
        ->and(Money::compare(Vendor::withBalance()->find($this->vendor->id)->balance(), Money::add($this->vendor->opening_balance, '1250.50')))->toBe(0);

    // error rows file: same columns + errors, only the 2 invalid rows
    $response = $this->get(route('expense-imports.errors', $batch))->assertOk()->assertDownload('expenses_errors.xlsx');
    $sheet = IOFactory::load(downloadedFile($response))->getSheetByName('Expenses');
    $rows = $sheet->toArray(null, false, false);

    expect($rows)->toHaveCount(3)
        ->and($rows[0][12])->toBe('errors')
        ->and((string) $rows[1][1])->toBe('-5') // numbers are written as numbers
        ->and($rows[1][12])->toBe('amount: The amount must be more than zero.')
        ->and($rows[2][2])->toBe('Unknown')
        ->and($rows[2][12])->toContain('category: "Unknown" is not an active main category');
    expect($batch->fresh()->error_file_path)->toEndWith('-errors.xlsx');
});

it('lets the user fix the error file and upload it again', function () {
    $batch = fiveRowBatch();
    $this->post(route('expense-imports.import', $batch));

    $response = $this->get(route('expense-imports.errors', $batch));
    $path = downloadedFile($response);
    $book = IOFactory::load($path);
    $book->getSheetByName('Expenses')->setCellValue('B2', '50')->setCellValue('C3', 'feed');
    $fixed = tempnam(sys_get_temp_dir(), 'fix').'.xlsx';
    IOFactory::createWriter($book, 'Xlsx')->save($fixed);

    $second = uploadImport(new UploadedFile($fixed, 'expenses_errors.xlsx', null, null, true));
    expect($second->only(['total_rows', 'valid_rows', 'error_rows']))->toBe(['total_rows' => 2, 'valid_rows' => 2, 'error_rows' => 0]);

    $this->post(route('expense-imports.import', $second));
    expect(Expense::count())->toBe(5);
});

it('imports everything in one go when there are no errors', function () {
    $batch = uploadImport(importCsv([importRow(), importRow(['amount' => '20'])]));

    $this->post(route('expense-imports.import', $batch))->assertSessionHas('success', '2 expenses imported.');
    $this->get(route('expense-imports.errors', $batch))->assertRedirect()->assertSessionHas('info');
    $this->get(route('expense-imports.show', $batch))->assertOk()
        ->assertSee('2 expenses were imported on')
        ->assertSee(route('expenses.show', Expense::first()), false)
        ->assertDontSee(route('expense-imports.import', $batch), false);
});

it('never imports a batch twice, nor a cancelled one', function () {
    $batch = uploadImport(importCsv([importRow()]));

    $this->post(route('expense-imports.import', $batch));
    $this->post(route('expense-imports.import', $batch))->assertSessionHas('error', 'This upload was already imported or cancelled.');
    expect(Expense::count())->toBe(1);

    $cancelled = uploadImport(importCsv([importRow(['amount' => '7'])]));
    $this->post(route('expense-imports.cancel', $cancelled));
    $this->post(route('expense-imports.import', $cancelled))->assertSessionHas('error');
    expect(Expense::count())->toBe(1);
});

it('checks rows again at import: a category deactivated after the preview turns its rows into errors', function () {
    $health = ExpenseCategory::factory()->create(['farm_id' => $this->farm->id, 'name' => 'Health']);
    $batch = uploadImport(importCsv([importRow(), importRow(['category' => 'Health', 'subcategory' => ''])]));
    expect($batch->valid_rows)->toBe(2);

    $health->update(['is_active' => false]);
    $this->post(route('expense-imports.import', $batch));

    $batch->refresh();
    expect(Expense::count())->toBe(1)
        ->and($batch->only(['imported_rows', 'valid_rows', 'error_rows']))->toBe(['imported_rows' => 1, 'valid_rows' => 1, 'error_rows' => 1])
        ->and($batch->rows()->where('row_number', 3)->first()->errorsFor('category'))->not->toBeEmpty();
});

it('imports 5,000 rows in one transaction with bulk inserts', function () {
    $batch = uploadImport(importCsv(array_map(fn ($i) => importRow(['amount' => (string) (100 + $i)]), range(1, 5000))));

    DB::enableQueryLog();
    $this->post(route('expense-imports.import', $batch))->assertSessionHas('success');
    $inserts = collect(DB::getQueryLog())->filter(fn ($q) => str_starts_with($q['query'], 'insert into `expenses`'))->count();

    expect(Expense::count())->toBe(5000)
        ->and($inserts)->toBe(10) // 500 per query
        ->and(Expense::where('import_batch_id', $batch->id)->sum('amount'))->toEqual('13002500.00') // Σ(101…5100)
        ->and($batch->rows()->whereNull('expense_id')->count())->toBe(0);
});

it('audit-logs the import on the batch', function () {
    $batch = uploadImport(importCsv([importRow()]));
    $this->post(route('expense-imports.import', $batch));

    $log = Activity::query()->where('subject_type', $batch->getMorphClass())->where('event', 'updated')->sole();
    expect($log->causer_id)->toBe($this->accountant->id)
        ->and($log->farm_id)->toBe($this->farm->id)
        ->and($log->properties['attributes']['status'])->toBe('imported')
        ->and($log->properties['attributes']['imported_rows'])->toBe(1);
});

it('needs expenses.import and stays inside the farm', function () {
    $batch = uploadImport(importCsv([importRow(['amount' => 'x'])]));

    $herdStaff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $this->flushSession();
    $this->actingAs($herdStaff)->post(route('expense-imports.import', $batch))->assertForbidden();
    $this->actingAs($herdStaff)->get(route('expense-imports.errors', $batch))->assertForbidden();

    $other = User::factory()->forFarm(Farm::factory()->create())->withRole(RoleName::Accountant)->create();
    $this->flushSession();
    $this->actingAs($other)->post(route('expense-imports.import', $batch))->assertNotFound();
    $this->actingAs($other)->get(route('expense-imports.errors', $batch))->assertNotFound();
});

/** Save a streamed download to a temp .xlsx and return its path. */
function downloadedFile($response): string
{
    $path = tempnam(sys_get_temp_dir(), 'dl').'.xlsx';
    file_put_contents($path, $response->streamedContent());

    return $path;
}
