<?php

namespace App\Http\Controllers\Reports;

use App\Exports\ReportTableExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\SalesReportRequest;
use App\Services\CollectionReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Reports → Milk collection (spec 7.6 item 2). Same period filter as the sales reports. */
class CollectionReportController extends Controller
{
    public function __construct(private readonly CollectionReportService $reports) {}

    public function show(SalesReportRequest $request, string $report): View|Response|BinaryFileResponse
    {
        $f = $request->validated();

        $table = match ($report) {
            'daily' => $this->reports->dailyShift($f['from'], $f['to']),
            'farmers' => $this->reports->farmerWise($f['from'], $f['to']),
            'rejected' => $this->reports->rejected($f['from'], $f['to']),
        };

        return match ($f['format'] ?? null) {
            'excel' => Excel::download(new ReportTableExport($table), $table->fileName().'.xlsx'),
            'pdf' => Pdf::loadView('pdf.report', ['table' => $table])
                ->setPaper('a4', count($table->columns) > 6 ? 'landscape' : 'portrait')
                ->download($table->fileName().'.pdf'),
            default => $this->listView($request, 'reports.show', [
                'table' => $table,
                'routeName' => 'reports.collection',
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
            'daily' => ['title' => __('Daily collection by shift'), 'description' => __('Farmers, litres, average fat / SNF, amount and rejected milk per day and shift.'), 'icon' => 'bi-calendar3'],
            'farmers' => ['title' => __('Farmer-wise collection'), 'description' => __('Litres, average fat / SNF, amount and rate of each farmer.'), 'icon' => 'bi-person-lines-fill'],
            'rejected' => ['title' => __('Rejected milk'), 'description' => __('Every rejected entry with its reason.'), 'icon' => 'bi-x-octagon'],
        ];
    }
}
