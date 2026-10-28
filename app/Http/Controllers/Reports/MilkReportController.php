<?php

namespace App\Http\Controllers\Reports;

use App\Exports\ReportTableExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\MilkReportRequest;
use App\Services\MilkReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Reports → Milk production: daily (animal × shift), monthly (day by day), animal-wise.
 * The same ReportTable feeds the screen, Excel (?format=excel) and PDF (?format=pdf).
 */
class MilkReportController extends Controller
{
    public function __construct(private readonly MilkReportService $reports) {}

    public function show(MilkReportRequest $request, string $report): View|Response|BinaryFileResponse
    {
        $f = $request->validated();
        $type = $f['milk_type'] ?: null;

        $table = match ($report) {
            'daily' => $this->reports->daily($f['date'], $type),
            'monthly' => $this->reports->monthly($f['month'], $type),
            'animals' => $this->reports->animalWise($f['from'], $f['to'], $type),
        };

        return match ($f['format'] ?? null) {
            'excel' => Excel::download(new ReportTableExport($table), $table->fileName().'.xlsx'),
            'pdf' => Pdf::loadView('pdf.report', ['table' => $table])
                ->setPaper('a4', count($table->columns) > 6 ? 'landscape' : 'portrait')
                ->download($table->fileName().'.pdf'),
            default => $this->listView($request, 'reports.show', [
                'table' => $table,
                'routeName' => 'reports.milk',
                'report' => $report,
                'filters' => $f,
                'filterType' => match ($report) {
                    'daily' => 'date',
                    'monthly' => 'month',
                    default => 'range',
                },
            ]),
        };
    }

    /** @return array<string, array{title: string, description: string, icon: string}> */
    public static function catalogue(): array
    {
        return [
            'daily' => ['title' => __('Daily milk production'), 'description' => __('Each animal\'s morning and evening milk for a day, totals by milk type.'), 'icon' => 'bi-droplet'],
            'monthly' => ['title' => __('Monthly milk production'), 'description' => __('Day by day: cow, buffalo, morning, evening and animals milked.'), 'icon' => 'bi-calendar-month'],
            'animals' => ['title' => __('Animal-wise milk production'), 'description' => __('Litres and average per day of each animal for any period.'), 'icon' => 'bi-list-ol'],
        ];
    }
}
