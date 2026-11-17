<?php

namespace App\Http\Controllers\Reports;

use App\Exports\ReportTableExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ProfitReportRequest;
use App\Services\ProfitReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Reports → Profitability (spec 7.6 #7–#10). The same ReportTable feeds the screen, Excel and PDF. */
class ProfitReportController extends Controller
{
    public function __construct(private readonly ProfitReportService $reports) {}

    public function show(ProfitReportRequest $request, string $report): View|Response|BinaryFileResponse
    {
        $f = $request->validated();

        $table = match ($report) {
            'pnl' => $this->reports->profitAndLoss($f['from'], $f['to']),
            'cost-per-litre' => $this->reports->costPerLitre($f['from'], $f['to']),
            'animals' => $this->reports->profitPerAnimal($f['from'], $f['to']),
            'collection-margin' => $this->reports->collectionMargin($f['from'], $f['to']),
        };

        return match ($f['format'] ?? null) {
            'excel' => Excel::download(new ReportTableExport($table), $table->fileName().'.xlsx'),
            'pdf' => Pdf::loadView('pdf.report', ['table' => $table])
                ->setPaper('a4', count($table->columns) > 6 ? 'landscape' : 'portrait')
                ->download($table->fileName().'.pdf'),
            default => $this->listView($request, 'reports.show', [
                'table' => $table,
                'routeName' => 'reports.profit',
                'report' => $report,
                'filters' => Arr::only($f, ['from', 'to']),
                'filterType' => 'range',
            ]),
        };
    }

    /** @return array<string, array{title: string, description: string, icon: string}> */
    public static function catalogue(): array
    {
        return [
            'pnl' => ['title' => __('Profit & loss (management summary)'), 'description' => __('Income from bills, unbilled milk and animal sales less farmer milk, expenses and animal purchases.'), 'icon' => 'bi-journal-text'],
            'cost-per-litre' => ['title' => __('Cost per litre (own milk)'), 'description' => __('Herd expenses ÷ litres produced, month by month.'), 'icon' => 'bi-calculator'],
            'animals' => ['title' => __('Profit per animal (estimate)'), 'description' => __('Milk value less feed, the animal\'s expenses and a share of herd overhead.'), 'icon' => 'bi-graph-up-arrow'],
            'collection-margin' => ['title' => __('Collection center margin'), 'description' => __('Collection milk sold less milk bought from farmers and center expenses.'), 'icon' => 'bi-shop-window'],
        ];
    }
}
