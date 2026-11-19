<?php

use App\Enums\RoleName;
use App\Http\Controllers\Reports\StaffReportController;
use App\Http\Controllers\Reports\TradingReportController;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\PaymentMode;
use App\Models\Section;
use App\Models\User;
use App\Services\BalanceReportService;
use App\Services\CollectionReportService;
use App\Services\ExpenseReportService;
use App\Services\LoanReportService;
use App\Services\MilkReportService;
use App\Services\ProfitReportService;
use App\Services\SalesReportService;
use App\Support\CurrentFarm;

// Task 7.8: every report on the Reports page opens on screen and downloads as Excel and PDF.
$routes = [
    'reports.expenses' => ExpenseReportService::REPORTS,
    'reports.milk' => MilkReportService::REPORTS,
    'reports.sales' => SalesReportService::REPORTS,
    'reports.balances' => BalanceReportService::REPORTS,
    'reports.profit' => ProfitReportService::REPORTS,
    'reports.collection' => CollectionReportService::REPORTS,
    'reports.loans' => LoanReportService::REPORTS,
    'reports.trading' => TradingReportController::REPORTS,
    'reports.staff' => StaffReportController::REPORTS,
];

$cases = [];
foreach ($routes as $route => $reports) {
    foreach ($reports as $report) {
        $cases["{$route} {$report}"] = [$route, $report];
    }
}

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    app(CurrentFarm::class)->set($this->farm);
    ExpenseCategory::seedDefaultsFor($this->farm);
    Section::seedDefaultsFor($this->farm);
    PaymentMode::seedDefaultsFor($this->farm);
    $this->actingAs(User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create());
});

it('shows the report with Excel and PDF buttons and downloads both', function (string $route, string $report) {
    $this->get(route($route, [$report]))->assertOk()
        ->assertSee('format=excel', false)
        ->assertSee('format=pdf', false);

    $excel = $this->get(route($route, [$report, 'format' => 'excel']))->assertOk();
    expect($excel->headers->get('content-disposition'))->toContain('.xlsx');

    $pdf = $this->get(route($route, [$report, 'format' => 'pdf']))->assertOk();
    expect($pdf->headers->get('content-type'))->toBe('application/pdf')
        ->and($pdf->getContent())->toStartWith('%PDF');
})->with($cases);

it('rejects an unknown export format', function () {
    $this->get(route('reports.milk', ['daily', 'format' => 'csv']))->assertSessionHasErrors('format');
});
