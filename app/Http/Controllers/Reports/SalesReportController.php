<?php

namespace App\Http\Controllers\Reports;

use App\Exports\ReportTableExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\SalesReportRequest;
use App\Services\SalesReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Reports → Sales (spec 7.6 item 4). The same ReportTable feeds the screen, Excel and PDF. */
class SalesReportController extends Controller
{
    public function __construct(private readonly SalesReportService $reports) {}

    public function show(SalesReportRequest $request, string $report): View|Response|BinaryFileResponse
    {
        $f = $request->validated();

        $table = match ($report) {
            'customers' => $this->reports->customerWise($f['from'], $f['to']),
            'routes' => $this->reports->routeWise($f['from'], $f['to']),
            'products' => $this->reports->productWise($f['from'], $f['to']),
            'dairy-plants' => $this->reports->dairyPlantReconciliation($f['from'], $f['to']),
        };

        return match ($f['format'] ?? null) {
            'excel' => Excel::download(new ReportTableExport($table), $table->fileName().'.xlsx'),
            'pdf' => Pdf::loadView('pdf.report', ['table' => $table])
                ->setPaper('a4', count($table->columns) > 6 ? 'landscape' : 'portrait')
                ->download($table->fileName().'.pdf'),
            default => $this->listView($request, 'reports.show', [
                'table' => $table,
                'routeName' => 'reports.sales',
                'report' => $report,
                'filters' => $f,
                'filterType' => 'range',
            ]),
        };
    }

    /** @return array<string, array{title: string, description: string, icon: string}> */
    public static function catalogue(): array
    {
        return [
            'customers' => ['title' => __('Customer-wise sales'), 'description' => __('Litres, amount and average rate of each customer.'), 'icon' => 'bi-people'],
            'routes' => ['title' => __('Route-wise sales'), 'description' => __('Customers, morning / evening litres and amount per route.'), 'icon' => 'bi-signpost-split'],
            'products' => ['title' => __('Product-wise sales'), 'description' => __('Cow / buffalo milk from own farm and collection.'), 'icon' => 'bi-box-seam'],
            'dairy-plants' => ['title' => __('Dairy plant reconciliation'), 'description' => __('Our litres, fat and SNF against the plant\'s readings.'), 'icon' => 'bi-clipboard-check'],
        ];
    }
}
