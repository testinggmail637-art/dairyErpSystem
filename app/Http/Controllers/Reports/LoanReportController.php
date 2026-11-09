<?php

namespace App\Http\Controllers\Reports;

use App\Exports\ReportTableExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\LoanReportRequest;
use App\Models\Loan;
use App\Services\LoanReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Reports → Loans (spec 7.4A). The same ReportTable feeds the screen, Excel and PDF. */
class LoanReportController extends Controller
{
    public function __construct(private readonly LoanReportService $reports) {}

    public function show(LoanReportRequest $request, string $report): View|Response|BinaryFileResponse
    {
        $f = $request->validated();

        $table = match ($report) {
            'summary' => $this->reports->summary($f['date']),
            'due' => $this->reports->due($f['from'], $f['to']),
            'interest' => $this->reports->interest($f['from'], $f['to']),
            'statement' => $this->reports->statement($f['loan_id'] ? Loan::find($f['loan_id']) : null, $f['from'], $f['to']),
            'monthly' => $this->reports->monthly($f['from'], $f['to']),
        };

        return match ($f['format'] ?? null) {
            'excel' => Excel::download(new ReportTableExport($table), $table->fileName().'.xlsx'),
            'pdf' => Pdf::loadView('pdf.report', ['table' => $table])
                ->setPaper('a4', count($table->columns) > 6 ? 'landscape' : 'portrait')
                ->download($table->fileName().'.pdf'),
            default => $this->listView($request, 'reports.show', [
                'table' => $table,
                'routeName' => 'reports.loans',
                'report' => $report,
                'filters' => Arr::only($f, match ($report) {
                    'summary' => ['date'], 'statement' => ['from', 'to', 'loan_id'], default => ['from', 'to']
                }),
                'filterType' => $report === 'summary' ? 'date' : 'range',
                'loanOptions' => $report === 'statement'
                    ? Loan::query()->orderBy('lender_name')->get(['id', 'lender_name', 'loan_no'])->mapWithKeys(fn ($l) => [$l->id => $l->lender_name.' · '.$l->loan_no])
                    : null,
                'futureDates' => $report === 'due',
            ]),
        };
    }

    /** @return array<string, array{title: string, description: string, icon: string}> */
    public static function catalogue(): array
    {
        return [
            'summary' => ['title' => __('Loan summary'), 'description' => __('Every loan, OD and card as on a date: paid, outstanding / used.'), 'icon' => 'bi-bank'],
            'due' => ['title' => __('Instalments due'), 'description' => __('EMIs due in a period with paid / overdue status.'), 'icon' => 'bi-calendar-event'],
            'interest' => ['title' => __('Interest & principal paid'), 'description' => __('Per loan and loan type for a period or financial year (income tax).'), 'icon' => 'bi-percent'],
            'statement' => ['title' => __('Loan statement'), 'description' => __('All entries of one loan with the balance after each.'), 'icon' => 'bi-journal-text'],
            'monthly' => ['title' => __('Monthly loan payments'), 'description' => __('Interest, charges and principal by month and loan type.'), 'icon' => 'bi-bar-chart'],
        ];
    }
}
