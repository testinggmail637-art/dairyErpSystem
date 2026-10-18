<?php

namespace App\Http\Controllers\Expenses;

use App\Exports\ExpenseTemplate\ExpenseTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expenses\ExpenseImportPreviewRequest;
use App\Http\Requests\Expenses\ExpenseImportUploadRequest;
use App\Http\Requests\Expenses\RevertExpenseImportRequest;
use App\Imports\ExpenseImportColumns;
use App\Models\ExpenseImportBatch;
use App\Services\ExpenseImportService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Expenses → Import (spec 7.2): template (1.7), upload + preview (1.8), import + error rows (1.9), history + revert (1.10).
 */
class ExpenseImportController extends Controller
{
    public function __construct(private readonly ExpenseImportService $imports) {}

    /** Template + upload, and the import history (25 per page, AJAX paging). */
    public function index(Request $request): View|Response
    {
        $batches = ExpenseImportBatch::query()
            ->with(['creator:id,name', 'reverter:id,name'])
            ->latest()
            ->orderByDesc('id')
            ->paginate(25);

        return $this->listView($request, 'expenses.imports.index', [
            'columns' => ExpenseImportColumns::COLUMNS,
            'help' => ExpenseImportColumns::help(),
            'batches' => $batches,
            'canRevert' => $request->user()->can('expenses.delete'),
        ]);
    }

    /** expenses_template.xlsx with this farm's current lists (categories, sections, payment modes, vendors). */
    public function template(): BinaryFileResponse
    {
        return Excel::download(new ExpenseTemplateExport, 'expenses_template.xlsx');
    }

    /** Upload → every row checked → preview. Nothing is saved as an expense yet. */
    public function store(ExpenseImportUploadRequest $request): RedirectResponse
    {
        $batch = $this->imports->upload($request->file('file'));

        return redirect()->route('expense-imports.show', $batch)->with(
            $batch->error_rows ? 'warning' : 'success',
            __(':valid of :total rows are ready to import; :errors have errors.', [
                'valid' => $batch->valid_rows, 'total' => $batch->total_rows, 'errors' => $batch->error_rows,
            ]),
        );
    }

    /** Preview: counts and the rows (100 per page) with errors highlighted per cell. */
    public function show(ExpenseImportPreviewRequest $request, ExpenseImportBatch $batch): View|Response
    {
        $show = $request->show();

        $rows = $batch->rows()
            ->select(['id', 'row_number', 'payload', 'errors', 'warnings', 'is_valid', 'expense_id'])
            ->when($show === 'errors', fn ($q) => $q->where('is_valid', false))
            ->when($show === 'valid', fn ($q) => $q->where('is_valid', true))
            ->when($show === 'warnings', fn ($q) => $q->whereNotNull('warnings'))
            ->orderBy('row_number')
            ->paginate(100)
            ->appends(array_filter(['show' => $show]));

        return $this->listView($request, 'expenses.imports.show', [
            'batch' => $batch->load(['creator:id,name', 'reverter:id,name']),
            'rows' => $rows,
            'show' => $show,
            'columns' => ExpenseImportColumns::COLUMNS,
        ]);
    }

    /** "Import valid rows": one transaction; rows with errors are skipped. */
    public function import(ExpenseImportBatch $batch): RedirectResponse
    {
        try {
            $count = $this->imports->import($batch);
        } catch (DomainException $e) {
            return redirect()->route('expense-imports.show', $batch)->with('error', $e->getMessage());
        }

        $batch->refresh();
        $skipped = $batch->total_rows - $count;

        return redirect()->route('expense-imports.show', $batch)->with(
            $skipped ? 'warning' : 'success',
            trans_choice(':count expense imported.|:count expenses imported.', $count)
            .($skipped ? ' '.trans_choice(':count row with errors was skipped — download the error rows, fix them and upload again.|:count rows with errors were skipped — download the error rows, fix them and upload again.', $skipped) : ''),
        );
    }

    /** "Download error rows": the invalid rows with an `errors` column, ready to fix and upload again. */
    public function errors(ExpenseImportBatch $batch): StreamedResponse|RedirectResponse
    {
        if ($batch->error_rows === 0) {
            return redirect()->route('expense-imports.show', $batch)->with('info', __('This upload has no rows with errors.'));
        }

        $path = $this->imports->errorFile($batch);
        $name = pathinfo($batch->original_filename, PATHINFO_FILENAME).'_errors.xlsx';

        return Storage::disk(ExpenseImportService::DISK)->download($path, $name);
    }

    /** Undo an import: soft-deletes its expenses if none was edited (reason required). */
    public function revert(RevertExpenseImportRequest $request, ExpenseImportBatch $batch): RedirectResponse
    {
        try {
            $count = $this->imports->revert($batch);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('expense-imports.show', $batch)
            ->with('success', trans_choice('Upload reverted: :count expense deleted.|Upload reverted: :count expenses deleted.', $count));
    }

    public function cancel(ExpenseImportBatch $batch): RedirectResponse
    {
        try {
            $this->imports->cancel($batch);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('expense-imports.index')->with('success', __('The upload was cancelled. Nothing was imported.'));
    }
}
