<?php

namespace App\Http\Controllers\Reports;

use App\Exports\ReportTableExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ExpenseReportRequest;
use App\Reports\ReportTable;
use App\Services\ExpenseReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Reports → Expenses: daily, monthly (category × section), category-wise, vendor outstanding.
 * The same ReportTable feeds the screen, Excel (?format=excel) and PDF (?format=pdf).
 */
class ExpenseReportController extends Controller
{
    public function __construct(private readonly ExpenseReportService $reports) {}

    public function show(ExpenseReportRequest $request, string $report): View|Response|BinaryFileResponse
    {
        $f = $request->validated();

        $table = match ($report) {
            'daily' => $this->reports->daily($f['from'], $f['to']),
            'monthly' => $this->reports->monthly($f['month']),
            'category' => $this->reports->categoryWise($f['from'], $f['to']),
            'vendors' => $this->reports->vendorOutstanding(),
        };

        return match ($f['format'] ?? null) {
            'excel' => Excel::download(new ReportTableExport($table), $table->fileName().'.xlsx'),
            'pdf' => Pdf::loadView('pdf.report', ['table' => $table])
                ->setPaper('a4', count($table->columns) > 6 ? 'landscape' : 'portrait')
                ->download($table->fileName().'.pdf'),
            default => $this->listView($request, 'reports.show', [
                'table' => $table,
                'report' => $report,
                'filters' => $f,
                'filterType' => match ($report) {
                    'monthly' => 'month',
                    'vendors' => 'none',
                    default => 'range',
                },
            ]),
        };
    }

    /** @return array<string, array{title: string, description: string, icon: string}> */
    public static function catalogue(): array
    {
        return [
            'daily' => ['title' => __('Daily expense summary'), 'description' => __('Entries, paid, credit and total for each day.'), 'icon' => 'bi-calendar3'],
            'monthly' => ['title' => __('Monthly by category × section'), 'description' => __('One month as a matrix with row and column totals.'), 'icon' => 'bi-grid-3x3'],
            'category' => ['title' => __('Category-wise expenses'), 'description' => __('Categories and subcategories for any period, with share %.'), 'icon' => 'bi-pie-chart'],
            'vendors' => ['title' => __('Vendor outstanding'), 'description' => __('What we owe each vendor today.'), 'icon' => 'bi-shop'],
        ];
    }

    /** For views: column alignment class. */
    public static function align(ReportTable $table, int $i): string
    {
        return $table->isNumeric($i) ? 'text-end text-nowrap' : '';
    }
}
