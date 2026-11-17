<?php

namespace App\Http\Controllers\Reports;

use App\Exports\ReportTableExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\BalanceReportRequest;
use App\Models\User;
use App\Services\BalanceReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Reports → Receivables & payables (spec 7.6 #5). The same ReportTable feeds the screen, Excel and PDF. */
class BalanceReportController extends Controller
{
    public function __construct(private readonly BalanceReportService $reports) {}

    public function show(BalanceReportRequest $request, string $report): View|Response|BinaryFileResponse
    {
        $f = $request->validated();

        $table = match ($report) {
            'receivables' => $this->reports->receivablesAging($f['date']),
            'farmers' => $this->reports->farmerPayables($f['date']),
            'parties' => $this->reports->partyBalances($f['date']),
        };

        return match ($f['format'] ?? null) {
            'excel' => Excel::download(new ReportTableExport($table), $table->fileName().'.xlsx'),
            'pdf' => Pdf::loadView('pdf.report', ['table' => $table])
                ->setPaper('a4', count($table->columns) > 6 ? 'landscape' : 'portrait')
                ->download($table->fileName().'.pdf'),
            default => $this->listView($request, 'reports.show', [
                'table' => $table,
                'routeName' => 'reports.balances',
                'report' => $report,
                'filters' => Arr::only($f, ['date']),
                'filterType' => 'date',
            ]),
        };
    }

    /**
     * The reports this user may open. Vendor payables stay under Expenses (Phase 1).
     *
     * @return array<string, array{title: string, description: string, icon: string}>
     */
    public static function catalogue(User $user): array
    {
        return array_filter([
            'receivables' => ['title' => __('Receivables aging'), 'description' => __('What each customer owes, split into 0–30, 31–60, 61–90 and 90+ days.'), 'icon' => 'bi-hourglass-split'],
            'farmers' => ['title' => __('Farmer payables'), 'description' => __('What we owe each farmer: finalized milk statements less advances and payments.'), 'icon' => 'bi-person-check'],
            'parties' => ['title' => __('Party balances'), 'description' => __('Animal trading parties: what they owe us and what we owe them.'), 'icon' => 'bi-people'],
        ], fn ($r, $key) => $user->can(BalanceReportService::PERMISSIONS[$key]), ARRAY_FILTER_USE_BOTH);
    }
}
