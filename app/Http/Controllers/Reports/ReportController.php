<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Reports home: the available reports, grouped by module (more modules join in later phases). */
class ReportController extends Controller
{
    public function index(): View
    {
        return view('reports.index', [
            'groups' => [
                __('Expenses') => collect(ExpenseReportController::catalogue())
                    ->map(fn ($r, $key) => $r + ['url' => route('reports.expenses', $key)])
                    ->all(),
            ],
        ]);
    }
}
