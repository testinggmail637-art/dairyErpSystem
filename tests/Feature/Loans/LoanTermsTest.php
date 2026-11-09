<?php

use App\Enums\InstallmentFrequency;
use App\Enums\LoanFamily;
use App\Enums\LoanType;
use App\Enums\MoratoriumInterest;
use App\Enums\RepaymentMethod;
use App\Enums\RoleName;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\Loan;
use App\Models\User;
use App\Services\LoanScheduleCalculator;
use App\Support\CurrentFarm;
use App\Support\Money;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->farm = Farm::where('code', 'MDF')->firstOrFail();
    app(CurrentFarm::class)->set($this->farm);
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
    $this->travelTo('2026-11-09 10:00');
});

function termsPayload(array $overrides = []): array
{
    return array_merge([
        'loan_type' => 'dairy_agri_loan', 'lender_name' => 'Bank of Baroda', 'principal_amount' => '200000', 'interest_rate' => '9',
        'rate_basis' => 'year', 'repayment_method' => 'emi_reducing', 'installment_frequency' => 'quarterly',
        'tenure_years' => '2', 'tenure_extra_months' => '', 'moratorium_months' => '', 'start_date' => '2026-01-01',
        'first_due_date' => '2026-03-31', 'broken_period_interest' => '', 'processing_fee' => '', 'notes' => '',
    ], $overrides);
}

$sum = fn (iterable $rows, string $col) => Money::sum(collect($rows)->pluck($col));

it('makes quarterly, moratorium and bullet schedules (spec acceptance)', function () use ($sum) {
    $calc = app(LoanScheduleCalculator::class);

    $q = $calc->schedule(RepaymentMethod::EmiReducing, '200000', '9', 24, Carbon::parse('2026-03-31'), InstallmentFrequency::Quarterly);
    expect($q['rows'])->toHaveCount(8)->and($sum($q['rows'], 'principal_part'))->toBe('200000.00')
        ->and(array_column(array_slice($q['rows'], 0, 3), 'due_date'))->toBe(['2026-03-31', '2026-06-30', '2026-09-30']);

    $paid = $calc->schedule(RepaymentMethod::EmiReducing, '100000', '12', 18, Carbon::parse('2026-01-31'), InstallmentFrequency::Monthly, 6, MoratoriumInterest::Paid);
    expect(array_column(array_slice($paid['rows'], 0, 6), 'emi_amount'))->each->toBe('1000.00')
        ->and($paid['rows'][6])->emi_amount->toBe('8885.00')->due_date->toBe('2026-07-31')
        ->and($sum($paid['rows'], 'principal_part'))->toBe('100000.00');

    $cap = $calc->schedule(RepaymentMethod::EmiReducing, '100000', '12', 18, Carbon::parse('2026-07-31'), InstallmentFrequency::Monthly, 6, MoratoriumInterest::Capitalised);
    expect($cap['rows'])->toHaveCount(12)->and($cap['principal'])->toBe('106152.02')->and($cap['emi'])->toBe('9431.00')
        ->and($sum($cap['rows'], 'principal_part'))->toBe('106152.02');

    $bullet = $calc->schedule(RepaymentMethod::Bullet, '50000', '12', 12, Carbon::parse('2027-01-10'));
    expect($bullet['rows'])->toHaveCount(1)->and($bullet['rows'][0])->emi_amount->toBe('56000.00')->principal_part->toBe('50000.00');
});

it('saves a quarterly dairy loan with a moratorium and calculated broken-period interest', function () {
    $this->actingAs($this->accountant)->post(route('loans.store'), termsPayload([
        'tenure_years' => '3', 'moratorium_months' => '6', 'moratorium_interest' => 'paid', 'start_date' => '2026-01-01', 'first_due_date' => '2026-04-30',
        'processing_fee' => '590',
    ]))->assertSessionHasNoErrors();

    $loan = Loan::sole();
    $rows = $loan->installments()->get();
    // first quarter starts 30-01-2026 → 29 days before it: 2,00,000 × 9 % × 29 ÷ 365 = ₹1,430.14
    expect($loan)->installment_frequency->toBe(InstallmentFrequency::Quarterly)->moratorium_months->toBe(6)
        ->moratorium_interest->toBe(MoratoriumInterest::Paid)->broken_period_interest->toBe('1430.14')
        ->and($rows->first())->installment_no->toBe(0)->interest_part->toBe('1430.14')
        ->and($rows->where('installment_no', '>', 0)->take(2)->pluck('emi_amount')->all())->toBe(['4500.00', '4500.00'])
        ->and($rows->where('installment_no', '>', 0))->toHaveCount(12)
        ->and(Expense::where('source_id', $loan->id)->where('source_type', $loan->getMorphClass())->value('loan_id'))->toBe($loan->id);
});

it('uses a typed broken-period interest, "% per month" rates and bullet maturity', function () {
    $this->actingAs($this->accountant);
    $this->post(route('loans.store'), termsPayload(['broken_period_interest' => '1,200', 'installment_frequency' => 'monthly', 'first_due_date' => '2026-02-28']))
        ->assertSessionHasNoErrors();
    expect(Loan::latest('id')->first()->broken_period_interest)->toBe('1200.00');

    $this->post(route('loans.store'), termsPayload(['loan_type' => 'private_loan', 'principal_amount' => '50000', 'interest_rate' => '2',
        'rate_basis' => 'month', 'repayment_method' => 'interest_only', 'installment_frequency' => 'monthly', 'tenure_years' => '1',
        'first_due_date' => '2026-02-01']))->assertSessionHasNoErrors();
    $private = Loan::latest('id')->first();
    expect($private)->interest_rate->toBe('24.00')->emi_amount->toBe('1000.00');

    $this->post(route('loans.store'), termsPayload(['loan_type' => 'gold_loan', 'principal_amount' => '50000', 'interest_rate' => '12',
        'repayment_method' => 'bullet', 'tenure_years' => '1', 'first_due_date' => '', 'start_date' => '2026-01-10']))->assertSessionHasNoErrors();
    $gold = Loan::latest('id')->first();
    expect($gold)->first_due_date->toDateString()->toBe('2027-01-10')->emi_amount->toBe('56000.00')
        ->broken_period_interest->toBe('0.00')->and($gold->installments()->count())->toBe(1);
});

it('saves cash credit / KCC and credit cards without a schedule', function () {
    $this->actingAs($this->accountant);
    $this->post(route('loans.store'), termsPayload(['loan_type' => 'kcc', 'principal_amount' => '160000', 'interest_rate' => '7',
        'interest_debit_cycle' => 'half_yearly', 'od_renewal_date' => '2027-01-01']))->assertSessionHasNoErrors();
    $kcc = Loan::latest('id')->first();
    expect($kcc->family())->toBe(LoanFamily::Revolving)->and($kcc)->interest_debit_cycle->toBe(InstallmentFrequency::HalfYearly)
        ->repayment_method->toBeNull()->tenure_months->toBeNull()->first_due_date->toBeNull()
        ->and($kcc->installments()->count())->toBe(0);

    $this->post(route('loans.store'), termsPayload(['loan_type' => 'credit_card', 'lender_name' => 'HDFC Bank', 'principal_amount' => '100000',
        'interest_rate' => '3.5', 'rate_basis' => 'month', 'statement_day' => '15', 'due_day' => '5', 'account_no' => '4321']))->assertSessionHasNoErrors();
    $card = Loan::latest('id')->first();
    expect($card)->loan_type->toBe(LoanType::CreditCard)->interest_rate->toBe('42.00')->statement_day->toBe(15)->due_day->toBe(5)
        ->and($card->isCard())->toBeTrue()->and($card->installments()->count())->toBe(0);

    $this->get(route('loans.show', $card))->assertOk()->assertSee('Credit limit')->assertSee('Last statement')->assertSee('Next due');
    $this->get(route('loans.create'))->assertOk()->assertSee('Kisan Credit Card (KCC)')->assertSee('Credit card EMI');
});

it('validates terms per loan family', function (array $input, string $field) {
    $this->actingAs($this->accountant)->post(route('loans.store'), termsPayload($input))->assertSessionHasErrors($field);
})->with([
    'period not whole quarters' => [['tenure_years' => '', 'tenure_extra_months' => '10'], 'tenure_months'],
    'moratorium not whole quarters' => [['moratorium_months' => '4'], 'moratorium_months'],
    'moratorium without interest choice' => [['moratorium_months' => '6'], 'moratorium_interest'],
    'moratorium as long as the loan' => [['moratorium_months' => '24', 'moratorium_interest' => 'paid'], 'moratorium_months'],
    'card without due day' => [['loan_type' => 'credit_card', 'statement_day' => '15'], 'due_day'],
    'monthly rate too high' => [['interest_rate' => '9', 'rate_basis' => 'month'], 'interest_rate'],
]);
