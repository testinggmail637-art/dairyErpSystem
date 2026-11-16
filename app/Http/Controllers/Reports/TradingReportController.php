<?php

namespace App\Http\Controllers\Reports;

use App\Exports\ReportTableExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\TradingReportRequest;
use App\Services\AnimalPnlService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Reports → Animal trading (spec Phase 6). The same ReportTable feeds the screen, Excel and PDF. */
class TradingReportController extends Controller
{
    public const REPORTS = ['summary'];

    public function __construct(private readonly AnimalPnlService $pnl) {}

    public function show(TradingReportRequest $request, string $report): View|Response|BinaryFileResponse
    {
        $f = $request->validated();
        $table = $this->pnl->tradingSummary($f['from'], $f['to']);

        return match ($f['format'] ?? null) {
            'excel' => Excel::download(new ReportTableExport($table), $table->fileName().'.xlsx'),
            'pdf' => Pdf::loadView('pdf.report', ['table' => $table])->setPaper('a4', 'landscape')->download($table->fileName().'.pdf'),
            default => $this->listView($request, 'reports.show', [
                'table' => $table,
                'routeName' => 'reports.trading',
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
            'summary' => ['title' => __('Animal trading summary'), 'description' => __('Resale animals: purchase cost, days held, costs, sale and estimated profit.'), 'icon' => 'bi-arrow-left-right'],
        ];
    }
}
