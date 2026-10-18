<?php

namespace App\Services;

use App\Enums\ExpenseUnit;
use App\Enums\ImportBatchStatus;
use App\Exports\ExpenseImportErrorsExport;
use App\Imports\ExpenseImportColumns;
use App\Imports\ExpenseImportReader;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseImportBatch;
use App\Models\ExpenseImportRow;
use App\Models\PaymentMode;
use App\Models\Section;
use App\Models\Vendor;
use App\Support\CurrentFarm;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Excel import of expenses, steps 2–3 of spec 7.2: upload → batch + rows, each row checked with the same rules
 * as the expense form (ExpenseRequest), names matched case-insensitively. Possible duplicates are warnings only.
 * Lookups are loaded once per file into memory, so 5,000 rows cost a handful of queries, not 25,000.
 */
class ExpenseImportService
{
    public const DISK = 'local';

    /** @var array<string, mixed>|null */
    private ?array $lookups = null;

    public function __construct(
        private readonly CurrentFarm $currentFarm,
        private readonly ExpenseImportReader $reader,
    ) {}

    /** Store the file, read and check every row, save the batch for the preview. */
    public function upload(UploadedFile $file): ExpenseImportBatch
    {
        $farmId = $this->currentFarm->id();
        $extension = strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs("imports/expenses/{$farmId}", Str::uuid().'.'.$extension, self::DISK);

        try {
            $rows = $this->reader->read(Storage::disk(self::DISK)->path($path));
            $checked = $this->check($rows);

            return DB::transaction(function () use ($file, $path, $farmId, $checked) {
                $batch = ExpenseImportBatch::create([
                    'original_filename' => Str::limit($file->getClientOriginalName(), 250, ''),
                    'stored_path' => $path,
                    'total_rows' => count($checked),
                    'valid_rows' => count(array_filter($checked, fn ($r) => $r['errors'] === [])),
                    'error_rows' => count(array_filter($checked, fn ($r) => $r['errors'] !== [])),
                    'warning_rows' => count(array_filter($checked, fn ($r) => $r['warnings'] !== [])),
                    'status' => ImportBatchStatus::Previewed,
                ]);

                $now = now();
                foreach (array_chunk($checked, 500, true) as $chunk) {
                    $insert = [];
                    foreach ($chunk as $rowNumber => $row) {
                        $insert[] = [
                            'farm_id' => $farmId,
                            'import_batch_id' => $batch->id,
                            'row_number' => $rowNumber,
                            'payload' => json_encode($row['values'], JSON_UNESCAPED_UNICODE),
                            'errors' => $row['errors'] === [] ? null : json_encode($row['errors'], JSON_UNESCAPED_UNICODE),
                            'warnings' => $row['warnings'] === [] ? null : json_encode($row['warnings'], JSON_UNESCAPED_UNICODE),
                            'is_valid' => $row['errors'] === [],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    ExpenseImportRow::insert($insert);
                }

                return $batch;
            });
        } catch (Throwable $e) {
            Storage::disk(self::DISK)->delete($path);

            throw $e;
        }
    }

    /**
     * "Import valid rows" (spec 7.2 step 4): all valid rows become expenses in one transaction, tagged with the batch.
     * Each row is checked again first — a category or vendor may have been deactivated since the preview; such a
     * row turns into an error row instead of being imported. Bulk inserts (500 per query) keep 5,000 rows fast;
     * the batch itself is audit-logged (status → imported, imported_rows) rather than 5,000 single entries.
     *
     * @return int the number of expenses created
     */
    public function import(ExpenseImportBatch $batch): int
    {
        return DB::transaction(function () use ($batch) {
            // lock: a double click or a second tab must not import the same rows twice
            $locked = ExpenseImportBatch::query()->lockForUpdate()->findOrFail($batch->getKey());
            if (! $locked->isPreviewed()) {
                throw new \DomainException(__('This upload was already imported or cancelled.'));
            }

            $ready = [];
            $nowInvalid = [];
            $batch->rows()->where('is_valid', true)->select(['id', 'payload'])->lazyById(500)
                ->each(function (ExpenseImportRow $row) use (&$ready, &$nowInvalid) {
                    [$data, $errors] = $this->checkRow($row->payload + array_fill_keys(array_keys(ExpenseImportColumns::COLUMNS), ''));
                    $errors === [] ? $ready[$row->id] = $data : $nowInvalid[$row->id] = $errors;
                });

            $now = now();
            $stamps = [
                'farm_id' => $locked->farm_id,
                'import_batch_id' => $locked->id,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            foreach (array_chunk($ready, 500) as $chunk) {
                Expense::insert(array_map(fn (array $data) => $data + $stamps, $chunk));
            }

            // ids grow in insert order, which is row order → link every import row to its expense
            $expenseIds = Expense::query()->where('import_batch_id', $locked->id)->orderBy('id')->pluck('id')->all();
            foreach (array_chunk(array_combine(array_keys($ready), $expenseIds), 500, true) as $chunk) {
                $case = implode(' ', array_map(fn ($rowId, $expenseId) => 'WHEN '.(int) $rowId.' THEN '.(int) $expenseId, array_keys($chunk), $chunk));
                ExpenseImportRow::query()->whereKey(array_keys($chunk))->update(['expense_id' => DB::raw("CASE id {$case} END")]);
            }

            foreach ($nowInvalid as $rowId => $errors) {
                ExpenseImportRow::query()->whereKey($rowId)->update(['is_valid' => false, 'errors' => json_encode($errors, JSON_UNESCAPED_UNICODE)]);
            }

            $locked->update([
                'status' => ImportBatchStatus::Imported,
                'imported_rows' => count($ready),
                'imported_at' => $now,
                'valid_rows' => count($ready),
                'error_rows' => $locked->error_rows + count($nowInvalid),
            ]);

            return count($ready);
        });
    }

    /**
     * Undo an import (spec 7.2 step 5): soft-deletes the batch's expenses — allowed only while none of them was
     * edited after the import. Expenses already deleted one by one are left as they are. The reason (from the
     * request) lands in the batch's audit entry.
     *
     * @return int the number of expenses deleted
     */
    public function revert(ExpenseImportBatch $batch): int
    {
        return DB::transaction(function () use ($batch) {
            $locked = ExpenseImportBatch::query()->lockForUpdate()->findOrFail($batch->getKey());
            if (! $locked->isImported()) {
                throw new \DomainException(__('Only an imported upload can be reverted.'));
            }
            if (! $locked->canBeReverted()) {
                throw new \DomainException(__('An upload can be reverted only within :days days of the import (until :date). Delete the expenses one by one instead.', [
                    'days' => config('dairy.imports.revert_days'), 'date' => fmt_datetime($locked->revertDeadline()),
                ]));
            }

            $expenses = Expense::query()->where('import_batch_id', $locked->id);

            $edited = (clone $expenses)->whereColumn('updated_at', '>', 'created_at')->count();
            if ($edited > 0) {
                throw new \DomainException(trans_choice(
                    ':count expense from this upload was edited after the import, so the upload cannot be reverted. Delete the expenses one by one instead.|:count expenses from this upload were edited after the import, so the upload cannot be reverted. Delete the expenses one by one instead.',
                    $edited,
                ));
            }

            $now = now();
            $deleted = $expenses->update(['deleted_at' => $now, 'updated_at' => $now, 'updated_by' => auth()->id()]);

            $locked->update(['status' => ImportBatchStatus::Reverted, 'reverted_at' => $now, 'reverted_by' => auth()->id()]);

            return $deleted;
        });
    }

    /** Excel of the invalid rows with an `errors` column (spec 7.2 step 4); rebuilt on each download, so it is never stale. */
    public function errorFile(ExpenseImportBatch $batch): string
    {
        $path = preg_replace('/\.\w+$/', '', $batch->stored_path).'-errors.xlsx';
        Excel::store(new ExpenseImportErrorsExport($batch), $path, self::DISK);
        $batch->update(['error_file_path' => $path]);

        return $path;
    }

    /** A previewed batch can be dropped; nothing was saved as an expense yet. */
    public function cancel(ExpenseImportBatch $batch): void
    {
        if (! $batch->isPreviewed()) {
            throw new \DomainException(__('Only a batch that is not imported yet can be cancelled.'));
        }

        $batch->update(['status' => ImportBatchStatus::Cancelled]);
        Storage::disk(self::DISK)->delete($batch->stored_path);
    }

    /**
     * Check rows read from a sheet.
     *
     * @param  array<int, array<string, string>>  $rows  row number => column key => text
     * @return array<int, array{values: array<string, string>, data: array<string, mixed>|null, errors: array<string, list<string>>, warnings: list<string>}>
     */
    public function check(array $rows): array
    {
        $checked = [];
        foreach ($rows as $rowNumber => $values) {
            [$data, $errors] = $this->checkRow($values);
            $checked[$rowNumber] = ['values' => $values, 'data' => $errors === [] ? $data : null, 'errors' => $errors, 'warnings' => []];
        }

        return $this->addDuplicateWarnings($checked);
    }

    /**
     * One row → expense attributes (ids resolved) and errors by column key.
     *
     * @param  array<string, string>  $v
     * @return array{0: array<string, mixed>, 1: array<string, list<string>>}
     */
    public function checkRow(array $v): array
    {
        $lookups = $this->lookups();
        $errors = [];
        $error = function (string $key, string $message) use (&$errors) {
            $errors[$key][] = $message;
        };
        $find = fn (array $map, string $name) => $map[self::key($name)] ?? null;

        // date: DD-MM-YYYY text (or an Excel date, already turned into that by the reader)
        $date = $this->parseDate($v['date']);
        if ($v['date'] === '') {
            $error('date', __('The date is required.'));
        } elseif ($date === null) {
            $error('date', __('Write the date as DD-MM-YYYY, e.g. 15-10-2026.'));
        } elseif ($date > today()->toDateString()) {
            $error('date', __('The expense date cannot be in the future.'));
        }

        // lookups by name
        $categoryId = $v['category'] === '' ? null : $find($lookups['categories'], $v['category']);
        if ($v['category'] === '') {
            $error('category', __('The category is required.'));
        } elseif ($categoryId === null) {
            $error('category', __('":name" is not an active main category of this farm.', ['name' => $v['category']]));
        }

        $subcategoryId = null;
        if ($v['subcategory'] !== '' && $categoryId !== null) {
            $subcategoryId = $find($lookups['subcategories'][$categoryId] ?? [], $v['subcategory']);
            if ($subcategoryId === null) {
                $error('subcategory', __('":name" is not an active subcategory of :category.', ['name' => $v['subcategory'], 'category' => $v['category']]));
            }
        }

        $sectionId = $v['section'] === '' ? null : $find($lookups['sections'], $v['section']);
        if ($v['section'] === '') {
            $error('section', __('The section is required.'));
        } elseif ($sectionId === null) {
            $error('section', __('":name" is not an active section of this farm.', ['name' => $v['section']]));
        }

        $credit = match (self::key($v['credit'])) {
            '', 'no', 'n', 'false', '0' => false,
            'yes', 'y', 'true', '1' => true,
            default => null,
        };
        if ($credit === null) {
            $error('credit', __('Write yes or no.'));
        }

        $vendorId = $v['vendor'] === '' ? null : $find($lookups['vendors'], $v['vendor']);
        if ($v['vendor'] !== '' && $vendorId === null) {
            $error('vendor', __('":name" is not an active vendor of this farm. Add the vendor first.', ['name' => $v['vendor']]));
        } elseif ($v['vendor'] === '' && $credit === true) {
            $error('vendor', __('A credit (unpaid) expense needs a vendor.'));
        }

        $paymentModeId = null;
        if ($credit === false) { // credit rows carry no payment mode, like the form
            $paymentModeId = $v['payment_mode'] === '' ? null : $find($lookups['payment_modes'], $v['payment_mode']);
            if ($v['payment_mode'] === '') {
                $error('payment_mode', __('Choose how it was paid, or mark the expense as credit.'));
            } elseif ($paymentModeId === null) {
                $error('payment_mode', __('":name" is not an active payment mode of this farm.', ['name' => $v['payment_mode']]));
            }
        }

        $unit = $v['unit'] === '' ? null : ExpenseUnit::tryFrom(self::key($v['unit']));
        if ($v['unit'] !== '' && $unit === null) {
            $error('unit', __('Use one of: :units.', ['units' => implode(', ', array_column(ExpenseUnit::cases(), 'value'))]));
        }

        // number / text rules, the same as ExpenseRequest
        $clean = fn (string $s) => ($s = str_replace([',', ' ', '₹'], '', $s)) === '' ? null : $s;
        $data = [
            'amount' => $clean($v['amount']),
            'quantity' => $clean($v['quantity']),
            'paid_to_text' => $v['paid_to'] ?: null,
            'note' => $v['note'] ?: null,
        ];
        // Plain checks instead of Validator::make per row (2 s → ~0.1 s for 5,000 rows); same limits as ExpenseRequest.
        if ($data['amount'] === null) {
            $error('amount', __('The amount is required.'));
        } elseif ($message = $this->decimalError($data['amount'], '9999999999.99')) {
            $error('amount', match ($message) {
                'decimal' => __('Enter the amount in rupees with up to 2 decimals.'),
                'gt' => __('The amount must be more than zero.'),
                default => __('The amount is too large.'),
            });
        }
        if ($data['quantity'] !== null && ($message = $this->decimalError($data['quantity'], '99999999.99'))) {
            $error('quantity', match ($message) {
                'decimal' => __('The quantity must be a number with up to 2 decimals.'),
                'gt' => __('The quantity must be more than zero.'),
                default => __('The quantity is too large.'),
            });
        }
        if (mb_strlen((string) $data['paid_to_text']) > 150) {
            $error('paid_to', __('Paid to may not be longer than 150 characters.'));
        }
        if (mb_strlen((string) $data['note']) > 2000) {
            $error('note', __('The note may not be longer than 2000 characters.'));
        }

        if ($data['quantity'] !== null && $unit === null && $v['unit'] === '') {
            $error('unit', __('Choose the unit for the quantity.'));
        }

        return [[
            'expense_date' => $date,
            'amount' => isset($errors['amount']) ? null : Money::round2($data['amount']),
            'category_id' => $categoryId,
            'subcategory_id' => $subcategoryId,
            'section_id' => $sectionId,
            'vendor_id' => $vendorId,
            'paid_to_text' => $data['paid_to_text'],
            'is_credit' => (bool) $credit,
            'payment_mode_id' => $paymentModeId,
            'quantity' => isset($errors['quantity']) || $data['quantity'] === null ? null : Money::round2($data['quantity']),
            'unit' => $unit?->value,
            'note' => $data['note'],
        ], $errors];
    }

    /**
     * Possible duplicate (spec 7.2): same date + amount + category + vendor/paid_to as an existing expense,
     * or as an earlier row of the same file. One query over the file's dates.
     *
     * @param  array<int, array<string, mixed>>  $checked
     * @return array<int, array<string, mixed>>
     */
    private function addDuplicateWarnings(array $checked): array
    {
        $valid = array_filter($checked, fn ($r) => $r['data'] !== null);
        if ($valid === []) {
            return $checked;
        }

        $dates = array_values(array_unique(array_map(fn ($r) => $r['data']['expense_date'], $valid)));
        $existing = [];
        foreach (array_chunk($dates, 500) as $chunk) {
            Expense::query()
                ->whereIn('expense_date', $chunk)
                ->whereIn('category_id', array_unique(array_map(fn ($r) => $r['data']['category_id'], $valid)))
                ->toBase()
                ->get(['expense_date', 'amount', 'category_id', 'vendor_id', 'paid_to_text'])
                ->each(function ($e) use (&$existing) {
                    $existing[self::duplicateKey(substr((string) $e->expense_date, 0, 10), $e->amount, $e->category_id, $e->vendor_id, $e->paid_to_text)] = true;
                });
        }

        $seen = [];
        foreach ($valid as $rowNumber => $row) {
            $d = $row['data'];
            $key = self::duplicateKey($d['expense_date'], $d['amount'], $d['category_id'], $d['vendor_id'], $d['paid_to_text']);

            if (isset($existing[$key])) {
                $checked[$rowNumber]['warnings'][] = __('Possible duplicate: an expense with the same date, amount, category and vendor / paid to already exists.');
            }
            if (isset($seen[$key])) {
                $checked[$rowNumber]['warnings'][] = __('Possible duplicate of row :row in this file.', ['row' => $seen[$key]]);
            } else {
                $seen[$key] = $rowNumber;
            }
        }

        return $checked;
    }

    private static function duplicateKey(string $date, mixed $amount, mixed $categoryId, mixed $vendorId, ?string $paidTo): string
    {
        return implode('|', [$date, Money::round2($amount), (int) $categoryId, $vendorId ? 'v'.$vendorId : 't'.self::key((string) $paidTo)]);
    }

    /** Laravel's decimal:0,2 + gt:0 + max:… on a text number: null when fine, else 'decimal' | 'gt' | 'max'. */
    private function decimalError(string $value, string $max): ?string
    {
        if (! preg_match('/^[+-]?(\d+(\.\d{0,2})?|\.\d{1,2})$/', $value)) {
            return 'decimal';
        }

        $value = Money::normalize(preg_replace('/^([+-]?)\./', '${1}0.', $value));

        return match (true) {
            Money::compare($value, '0') <= 0 => 'gt',
            Money::compare($value, $max) > 0 => 'max',
            default => null,
        };
    }

    /** DD-MM-YYYY (also D-M-YYYY, with / or . separators) → Y-m-d, or null. */
    private function parseDate(string $text): ?string
    {
        if (! preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/', $text, $m)) {
            return null;
        }

        return checkdate((int) $m[2], (int) $m[1], (int) $m[3])
            ? Carbon::createFromDate((int) $m[3], (int) $m[2], (int) $m[1])->toDateString()
            : null;
    }

    /** Case-insensitive, trimmed, single-spaced name used for matching. */
    private static function key(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name) ?? ''));
    }

    /**
     * Active lookups of the current farm, name key => id (loaded once).
     *
     * @return array<string, mixed>
     */
    private function lookups(): array
    {
        if ($this->lookups !== null) {
            return $this->lookups;
        }

        $byName = fn ($rows) => $rows->mapWithKeys(fn ($r) => [self::key($r->name) => $r->id])->all();
        $categories = ExpenseCategory::query()->where('is_active', true)->get(['id', 'name', 'parent_id']);
        $parents = $categories->whereNull('parent_id');

        return $this->lookups = [
            'categories' => $byName($parents),
            'subcategories' => $categories->whereNotNull('parent_id')
                ->filter(fn ($c) => $parents->contains('id', $c->parent_id))
                ->groupBy('parent_id')->map($byName)->all(),
            'sections' => $byName(Section::query()->where('is_active', true)->get(['id', 'name'])),
            'payment_modes' => $byName(PaymentMode::query()->where('is_active', true)->get(['id', 'name'])),
            'vendors' => $byName(Vendor::query()->where('is_active', true)->get(['id', 'name'])),
        ];
    }
}
