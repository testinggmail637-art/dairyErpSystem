<?php

namespace App\Http\Controllers\Reports;

use App\Exports\ReportTableExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StaffReportRequest;
use App\Services\StaffReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Reports → Staff: attendance register and salary register (spec 7.6 #11). */
class StaffReportController extends Controller
{
    public const REPORTS = ['attendance', 'salary'];

    public function __construct(private readonly StaffReportService $reports) {}

    public function show(StaffReportRequest $request, string $report): View|Response|BinaryFileResponse
    {
        $f = $request->validated();
        $table = $report === 'attendance' ? $this->reports->attendance($f['month']) : $this->reports->salary($f['from'], $f['to']);

        return match ($f['format'] ?? null) {
            'excel' => Excel::download(new ReportTableExport($table), $table->fileName().'.xlsx'),
            'pdf' => Pdf::loadView('pdf.report', ['table' => $table])->setPaper('a4', 'landscape')->download($table->fileName().'.pdf'),
            default => $this->listView($request, 'reports.show', [
                'table' => $table,
                'routeName' => 'reports.staff',
                'report' => $report,
                'filters' => Arr::only($f, $report === 'attendance' ? ['month'] : ['from', 'to']),
                'filterType' => $report === 'attendance' ? 'month' : 'range',
            ]),
        };
    }

    /** @return array<string, array{title: string, description: string, icon: string}> */
    public static function catalogue(bool $withSalary): array
    {
        return ['attendance' => ['title' => __('Attendance register'), 'description' => __('Month grid of each staff member\'s days with totals and paid days.'), 'icon' => 'bi-calendar-check']]
            + ($withSalary ? ['salary' => ['title' => __('Salary register'), 'description' => __('Finalized salary slips by month: earned, bonus, deductions, net and payment.'), 'icon' => 'bi-wallet2']] : []);
    }
}
