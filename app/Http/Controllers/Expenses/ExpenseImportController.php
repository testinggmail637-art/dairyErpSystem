<?php

namespace App\Http\Controllers\Expenses;

use App\Exports\ExpenseTemplate\ExpenseTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\ExpenseImportColumns;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Expenses → Import (spec 7.2). Task 1.7: the template; upload/preview/import/history follow in Tasks 1.8–1.10.
 */
class ExpenseImportController extends Controller
{
    public function index(): View
    {
        return view('expenses.imports.index', [
            'columns' => ExpenseImportColumns::COLUMNS,
            'help' => ExpenseImportColumns::help(),
        ]);
    }

    /** expenses_template.xlsx with this farm's current lists (categories, sections, payment modes, vendors). */
    public function template(): BinaryFileResponse
    {
        return Excel::download(new ExpenseTemplateExport, 'expenses_template.xlsx');
    }
}
