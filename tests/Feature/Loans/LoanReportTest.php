<?php

use App\Enums\LoanType;
use App\Enums\RoleName;
use App\Models\Farm;
use App\Models\PaymentMode;
use App\Models\User;
use App\Services\LoanReportService;
use App\Services\LoanService;
use App\Support\CurrentFarm;
use App\Support\Money;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->farm = Farm::where('code', 'MDF')->firstOrFail();
    app(CurrentFarm::class)->set($this->farm);
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->actingAs($this->owner);
    $this->travelTo('2026-11-09 10:00');
    $this->bank = PaymentMode::where('code', 'BANK')->value('id');

    $loans = app(LoanService::class);
    $this->loan = $loans->create([
        'loan_type' => 'personal_loan', 'lender_name' => 'HDFC Bank', 'principal_amount' => '100000', 'interest_rate' => '12',
        'repayment_method' => 'emi_reducing', 'installment_frequency' => 'monthly', 'tenure_months' => 12,
        'start_date' => '2025-12-31', 'first_due_date' => '2026-01-31', 'processing_fee' => '0',
    ]);
    $this->od = $loans->create(['loan_type' => 'overdraft', 'lender_name' => 'SBI', 'principal_amount' => '200000', 'interest_rate' => '9.5',
        'start_date' => '2026-01-01', 'processing_fee' => '0', 'interest_debit_cycle' => 'monthly']);

    foreach (['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30'] as $date) { // 1 in FY 2025-26 … 3 in FY 2026-27
        $this->post(route('loans.payments.store', $this->loan), ['kind' => 'payment', 'txn_date' => $date, 'amount' => '8885',
            'payment_mode_id' => $this->bank])->assertSessionHasNoErrors();
    }
    foreach ([['od_withdrawal', '2026-04-10', '50000'], ['od_interest', '2026-04-30', '390'], ['charge', '2026-05-02', '590'], ['od_repayment', '2026-05-05', '10000']] as [$type, $date, $amount]) {
        $this->post(route('loans.entries.store', $this->od), ['type' => $type, 'txn_date' => $date, 'amount' => $amount, 'payment_mode_id' => $this->bank])
            ->assertSessionHasNoErrors();
    }
    $this->reports = app(LoanReportService::class);
});

it('summary: outstanding principal and OD used as on a date', function () {
    $table = $this->reports->summary('2026-03-31');
    $byNo = collect($table->rows)->keyBy(0);

    // after 3 EMIs the outstanding = closing principal of the 3rd instalment (schedule)
    $closing = $this->loan->installments()->where('installment_no', 3)->value('closing_principal');
    expect($byNo[$this->loan->loan_no][10])->toBe($closing)
        ->and($byNo[$this->od->loan_no][10])->toBe('0.00'); // withdrawal came later

    expect(collect($this->reports->summary('2026-11-09')->rows)->keyBy(0)[$this->od->loan_no][10])->toBe('40980.00'); // 50,000 + 390 + 590 − 10,000
    $this->get(route('reports.loans', ['summary', 'date' => '2026-11-09']))->assertOk()->assertSee('₹40,980.00');
});

it('interest & principal for a financial year, subtotalled per type; monthly payments by type', function () {
    $table = $this->reports->interest('2026-04-01', '2027-03-31');
    $emi4 = $this->loan->installments()->where('installment_no', 4)->first();

    expect($table->totals[2])->toBe(Money::add($emi4->interest_part, '390.00')) // only the April EMI + the OD debit
        ->and($table->totals[5])->toBe('590.00')
        ->and($table->totals[6])->toBe($emi4->principal_part);

    $monthly = $this->reports->monthly('2026-04-01', '2026-05-31');
    expect(collect($monthly->rows)->map(fn ($r) => [$r[0], $r[1]])->all())
        ->toBe([['Apr 2026', LoanType::Overdraft->label()], ['Apr 2026', LoanType::PersonalLoan->label()], ['May 2026', LoanType::Overdraft->label()]]);
    expect($monthly->totals[5])->toBe(Money::add(Money::add('8885.00', '390.00'), '590.00'));

    $this->get(route('reports.loans', 'interest'))->assertOk()->assertSee('Interest &amp; principal paid', false);
    $this->get(route('reports.loans', ['interest', 'format' => 'excel']))->assertOk();
});

it('instalments due with status, and the loan statement with running balance', function () {
    $due = collect($this->reports->due('2026-04-01', '2026-06-30')->rows);
    expect($due->pluck(7)->all())->toBe(['Paid', 'Overdue', 'Overdue']);

    $statement = $this->reports->statement($this->od, '2026-05-01', '2026-05-31');
    expect(collect($statement->rows)->map(fn ($r) => [$r[1], $r[6]])->all())
        ->toBe([['Opening balance', '50390.00'], ['Bank charge / fee', '50980.00'], ['Repayment / bill payment', '40980.00']]);

    $this->get(route('reports.loans', ['statement', 'loan_id' => $this->loan->id, 'from' => '2026-01-01', 'to' => '2026-11-09']))
        ->assertOk()->assertSee('Opening balance')->assertSee('₹1,00,000.00');
    $this->get(route('reports.loans', ['due', 'from' => '2026-11-01', 'to' => '2026-12-31']))->assertOk();
    $this->get(route('reports.loans', ['statement', 'format' => 'pdf', 'loan_id' => $this->od->id]))->assertOk();
});

it('is only for users with reports.view and loans.view', function () {
    $this->get(route('reports.index'))->assertSee('Loan statement');
    $staff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $this->actingAs($staff)->get(route('reports.loans', 'summary'))->assertForbidden();
});
