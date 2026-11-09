<?php

use App\Enums\LoanType;
use App\Enums\RepaymentMethod;
use App\Enums\RoleName;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanTransaction;
use App\Models\User;
use App\Services\LoanScheduleCalculator;
use App\Support\CurrentFarm;
use App\Support\Money;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->farm = Farm::where('code', 'MDF')->firstOrFail();
    app(CurrentFarm::class)->set($this->farm);
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
    $this->travelTo('2026-11-06 10:00');
});

function loanPayload(array $overrides = []): array
{
    return array_merge([
        'loan_type' => 'business_loan', 'lender_name' => 'State Bank of India', 'account_no' => 'SBI-778',
        'principal_amount' => '1,00,000', 'interest_rate' => '12', 'repayment_method' => 'emi_reducing',
        'tenure_years' => '1', 'tenure_extra_months' => '', 'start_date' => '2026-10-31', 'first_due_date' => '2026-11-30',
        'processing_fee' => '', 'notes' => '',
    ], $overrides);
}

it('calculates the spec examples: reducing ₹8,885, flat ₹6,000, interest-only, 0 %', function () {
    $calc = app(LoanScheduleCalculator::class);
    $sum = fn (array $s, string $col) => Money::sum(array_column($s['rows'], $col));

    $reducing = $calc->schedule(RepaymentMethod::EmiReducing, '100000', '12', 12, Carbon::parse('2026-01-31'));
    expect($reducing['emi'])->toBe('8885.00')->and($sum($reducing, 'principal_part'))->toBe('100000.00')
        ->and(abs((float) $sum($reducing, 'interest_part') - 6618.55))->toBeLessThan(1.0) // spec: ± rounding
        ->and(end($reducing['rows'])['closing_principal'])->toBe('0.00')
        ->and(array_column(array_slice($reducing['rows'], 0, 3), 'due_date'))->toBe(['2026-01-31', '2026-02-28', '2026-03-31']);

    $flat = $calc->schedule(RepaymentMethod::EmiFlat, '120000', '10', 24, Carbon::parse('2026-01-10'));
    expect($flat['emi'])->toBe('6000.00')->and($sum($flat, 'interest_part'))->toBe('24000.00')->and($sum($flat, 'principal_part'))->toBe('120000.00');

    $io = $calc->schedule(RepaymentMethod::InterestOnly, '50000', '18', 6, Carbon::parse('2026-01-10'));
    expect($io['emi'])->toBe('750.00')->and($io['rows'][0]['principal_part'])->toBe('0.00')->and(end($io['rows'])['emi_amount'])->toBe('50750.00');

    $free = $calc->schedule(RepaymentMethod::EmiReducing, '10000', '0', 3, Carbon::parse('2026-01-10'));
    expect(array_column($free['rows'], 'emi_amount'))->toBe(['3333.00', '3333.00', '3334.00']);
});

it('adds a loan with number, schedule and the processing fee as a Finance expense', function () {
    $this->actingAs($this->accountant)->post(route('loans.store'), loanPayload(['processing_fee' => '1,180']))->assertSessionHasNoErrors();

    $loan = Loan::sole();
    expect($loan)->loan_no->toBe('LOAN/2026-27/0001')->emi_amount->toBe('8885.00')->tenure_months->toBe(12)
        ->principal_amount->toBe('100000.00')->and($loan->installments()->count())->toBe(12)
        ->and(LoanInstallment::where('loan_id', $loan->id)->sum('principal_part'))->toEqual(100000);

    $fee = Expense::where('source_type', $loan->getMorphClass())->where('source_id', $loan->id)->sole();
    expect($fee)->amount->toBe('1180.00')->is_credit->toBeFalse()->paid_to_text->toBe('State Bank of India')
        ->and($fee->category->name)->toBe('Finance')->and($fee->subcategory->name)->toBe('Bank Charges')
        ->and($fee->isAutoCreated())->toBeTrue();

    $this->get(route('loans.show', $loan))->assertOk()->assertSee('₹8,885.00')->assertSee('30-11-2026');
    $this->get(route('loans.index'))->assertOk()->assertSee('LOAN/2026-27/0001')->assertSee('1 yr')->assertSee('30-11-2026');
});

it('stores an overdraft without schedule', function () {
    $this->actingAs($this->accountant)->post(route('loans.store'), loanPayload(['loan_type' => 'overdraft', 'principal_amount' => '200000',
        'repayment_method' => '', 'tenure_years' => '', 'first_due_date' => '', 'od_renewal_date' => '2027-10-31']))->assertSessionHasNoErrors();

    expect(Loan::sole())->loan_type->toBe(LoanType::Overdraft)->repayment_method->toBeNull()->tenure_months->toBeNull()
        ->emi_amount->toBeNull()->od_renewal_date->toDateString()->toBe('2027-10-31')
        ->and(LoanInstallment::count())->toBe(0);
});

it('validates loans', function (array $input, string $field) {
    $this->actingAs($this->accountant)->post(route('loans.store'), loanPayload($input))->assertSessionHasErrors($field);
    expect(Loan::count())->toBe(0);
})->with([
    'zero amount' => [['principal_amount' => '0'], 'principal_amount'],
    'rate too high' => [['interest_rate' => '120'], 'interest_rate'],
    'no tenure' => [['tenure_years' => '', 'tenure_extra_months' => ''], 'tenure_months'],
    'over 40 years' => [['tenure_years' => '41'], 'tenure_months'],
    'future start' => [['start_date' => '2026-12-01', 'first_due_date' => '2027-01-01'], 'start_date'],
    'first EMI before start' => [['first_due_date' => '2026-10-15'], 'first_due_date'],
    'no repayment method' => [['repayment_method' => ''], 'repayment_method'],
]);

it('re-makes the schedule on edit until the first transaction, then only lender details change', function () {
    $this->actingAs($this->accountant)->post(route('loans.store'), loanPayload(['processing_fee' => '500']));
    $loan = Loan::sole();

    $this->put(route('loans.update', $loan), loanPayload(['interest_rate' => '10', 'tenure_years' => '2', 'processing_fee' => '0']))->assertSessionHasErrors('reason');
    $this->put(route('loans.update', $loan), loanPayload(['interest_rate' => '10', 'tenure_years' => '2', 'processing_fee' => '0', 'reason' => 'Rate was 10']))
        ->assertSessionHasNoErrors();
    expect($loan->fresh())->interest_rate->toBe('10.00')->tenure_months->toBe(24)->emi_amount->toBe('4614.00')
        ->and($loan->installments()->count())->toBe(24)
        ->and(Expense::where('source_id', $loan->id)->where('source_type', $loan->getMorphClass())->exists())->toBeFalse();

    LoanTransaction::create(['farm_id' => $this->farm->id, 'loan_id' => $loan->id, 'txn_date' => '2026-11-06', 'type' => 'emi_payment',
        'amount' => '4614.00', 'principal_part' => '3780.67', 'interest_part' => '833.33']);

    $this->put(route('loans.update', $loan), ['principal_amount' => '5', 'interest_rate' => '1', 'lender_name' => 'SBI Main Branch',
        'account_no' => 'SBI-999', 'reason' => 'Branch moved'])->assertSessionHasNoErrors();
    expect($loan->fresh())->lender_name->toBe('SBI Main Branch')->account_no->toBe('SBI-999')
        ->principal_amount->toBe('100000.00')->interest_rate->toBe('10.00')->and($loan->installments()->count())->toBe(24);

    $this->delete(route('loans.destroy', $loan), ['reason' => 'Try'])->assertSessionHas('error');
    expect(Loan::count())->toBe(1);
});

it('deletes a loan without transactions (soft) together with its fee expense', function () {
    $this->actingAs($this->accountant)->post(route('loans.store'), loanPayload(['processing_fee' => '500']));
    $loan = Loan::sole();

    $this->delete(route('loans.destroy', $loan), ['reason' => 'Entered twice'])->assertRedirect(route('loans.index'));
    expect(Loan::count())->toBe(0)->and(Loan::withTrashed()->count())->toBe(1)
        ->and(Expense::where('source_type', $loan->getMorphClass())->count())->toBe(0);
});

it('is for loans.view / loans.manage only, per farm, with a flat query count', function () {
    $herd = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $foreign = Loan::factory()->create(['farm_id' => Farm::factory()->create()->id]);

    $this->actingAs($herd)->get(route('loans.index'))->assertForbidden();
    $this->post(route('loans.store'), loanPayload())->assertForbidden();
    $this->actingAs($this->accountant)->get(route('loans.show', $foreign))->assertNotFound();

    $count = function () {
        $this->get(route('loans.index'))->assertOk();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('loans.index'))->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };
    Loan::factory()->count(2)->create(['farm_id' => $this->farm->id]);
    $few = $count();
    Loan::factory()->count(20)->create(['farm_id' => $this->farm->id]);
    expect($count())->toBe($few);
});
