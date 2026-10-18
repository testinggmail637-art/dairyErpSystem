<?php

use App\Imports\ExpenseImportColumns;
use App\Models\ExpenseImportBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/*
| Feature tests run against the MySQL/MariaDB test database (dairy_erp_test, see .env.testing / phpunit.xml).
*/
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/*
| Expense import helpers (Phase 1 Tasks 8–10). A row in template column order:
| date, amount, category, subcategory, section, payment_mode, vendor, paid_to, credit, quantity, unit, note.
*/
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
