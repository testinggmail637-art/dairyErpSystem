<?php

use App\Enums\LoanStatus;
use App\Enums\RoleName;
use App\Models\Farm;
use App\Models\LoanInstallment;
use App\Models\LoanRevision;
use App\Models\LoanTransaction;
use App\Models\PaymentMode;
use App\Models\User;
use App\Services\LoanPaymentService;
use App\Services\LoanService;
use App\Support\CurrentFarm;
use App\Support\Money;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->farm = Farm::where('code', 'MDF')->firstOrFail();
    app(CurrentFarm::class)->set($this->farm);
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->actingAs($this->accountant);
    $this->travelTo('2026-04-15 10:00');
    $this->bank = PaymentMode::where('code', 'BANK')->value('id');
    $this->loan = app(LoanService::class)->create([
        'loan_type' => 'personal_loan', 'lender_name' => 'HDFC Bank', 'principal_amount' => '100000', 'interest_rate' => '12',
        'repayment_method' => 'emi_reducing', 'installment_frequency' => 'monthly', 'tenure_months' => 12,
        'start_date' => '2025-12-31', 'first_due_date' => '2026-01-31', 'processing_fee' => '0',
    ]);
    foreach (['2026-01-31', '2026-02-28', '2026-03-31'] as $date) {
        $this->post(route('loans.payments.store', $this->loan), ['kind' => 'payment', 'txn_date' => $date, 'amount' => '8885', 'payment_mode_id' => $this->bank]);
    }
    $this->state = fn () => app(LoanPaymentService::class)->state($this->loan->fresh());
});

function prepay(array $overrides = []): TestResponse
{
    return test()->post(route('loans.prepayment.store', test()->loan), array_merge(
        ['txn_date' => '2026-04-15', 'amount' => '20000', 'mode' => 'reduce_emi', 'payment_mode_id' => test()->bank], $overrides));
}

it('prepays ₹20,000 after 3 EMIs (reduce EMI): outstanding −20,000, unpaid re-planned, paid unchanged, revision written', function () {
    $before = ($this->state)();
    $paidRows = LoanInstallment::where('loan_id', $this->loan->id)->where('installment_no', '<=', 3)->get(['installment_no', 'emi_amount', 'principal_part'])->toArray();

    prepay()->assertSessionHasNoErrors();

    $after = ($this->state)();
    $rows = $after['rows']->where('installment_no', '>', 3);
    expect(Money::sub($before['outstanding'], $after['outstanding']))->toBe('20000.00')
        ->and($rows)->toHaveCount(9)
        ->and(Money::sum($rows->pluck('principal_part')))->toBe($after['outstanding'])
        ->and(Money::compare($rows->first()->emi_amount, '8885'))->toBe(-1)
        ->and($this->loan->fresh()->emi_amount)->toBe($rows->first()->emi_amount)
        ->and(LoanInstallment::where('loan_id', $this->loan->id)->where('installment_no', '<=', 3)->get(['installment_no', 'emi_amount', 'principal_part'])->toArray())->toBe($paidRows)
        ->and(LoanRevision::sole())->kind->toBe('prepayment')->old_emi->toBe('8885.00')
        ->and($after['principal_paid'])->toBe(Money::add($before['principal_paid'], '20000'));
});

it('keeps the EMI and finishes earlier with "reduce tenure"', function () {
    prepay(['mode' => 'reduce_tenure'])->assertSessionHasNoErrors();

    $rows = ($this->state)()['rows']->where('installment_no', '>', 3);
    expect($rows->count())->toBeLessThan(9)->and($rows->first()->emi_amount)->toBe('8885.00')
        ->and($this->loan->fresh()->emi_amount)->toBe('8885.00');
});

it('deleting the latest prepayment puts the old schedule back; a prepayment cannot be edited', function () {
    $before = LoanInstallment::where('loan_id', $this->loan->id)->orderBy('installment_no')->get(['installment_no', 'due_date', 'emi_amount', 'principal_part'])->toArray();
    prepay()->assertSessionHasNoErrors();
    $txn = LoanTransaction::latest('id')->first();

    $this->put(route('loan-transactions.update', $txn), ['kind' => 'payment', 'txn_date' => '2026-04-15', 'amount' => '1000',
        'payment_mode_id' => $this->bank, 'reason' => 'Change'])->assertSessionHas('error');

    $this->delete(route('loan-transactions.destroy', $txn), ['reason' => 'Cheque bounced'])->assertSessionHasNoErrors();
    expect(LoanInstallment::where('loan_id', $this->loan->id)->orderBy('installment_no')->get(['installment_no', 'due_date', 'emi_amount', 'principal_part'])->toArray())->toBe($before)
        ->and($this->loan->fresh()->emi_amount)->toBe('8885.00')->and(LoanRevision::count())->toBe(0);
});

it('refuses a prepayment while the current instalment is part paid, or above the outstanding', function () {
    $this->post(route('loans.payments.store', $this->loan), ['kind' => 'payment', 'txn_date' => '2026-04-15', 'amount' => '500', 'payment_mode_id' => $this->bank]);
    prepay()->assertSessionHas('error');

    $this->delete(route('loan-transactions.destroy', LoanTransaction::latest('id')->first()), ['reason' => 'Wrong']);
    prepay(['amount' => '999999'])->assertSessionHas('error');
    expect(LoanTransaction::where('type', 'prepayment')->count())->toBe(0);
});

it('changes the rate: new EMI, or the same EMI with a different number of instalments', function () {
    $this->post(route('loans.rate.update', $this->loan), ['effective_date' => '2026-04-01', 'new_rate' => '10', 'mode' => 'reduce_emi'])
        ->assertSessionHasErrors('reason');
    $this->post(route('loans.rate.update', $this->loan), ['effective_date' => '2026-04-01', 'new_rate' => '10', 'mode' => 'reduce_emi', 'reason' => 'Repo cut'])
        ->assertSessionHasNoErrors();

    $loan = $this->loan->fresh();
    $rows = ($this->state)()['rows']->where('installment_no', '>', 3);
    expect($loan->interest_rate)->toBe('10.00')->and($rows)->toHaveCount(9)->and(Money::compare($loan->emi_amount, '8885'))->toBe(-1)
        ->and(LoanRevision::sole())->kind->toBe('rate_change')->old_rate->toBe('12.00')->new_rate->toBe('10.00');

    $this->post(route('loans.rate.update', $this->loan), ['effective_date' => '2026-04-01', 'new_rate' => '14', 'mode' => 'reduce_tenure', 'reason' => 'Repo up'])
        ->assertSessionHasNoErrors();
    expect(($this->state)()['rows']->where('installment_no', '>', 3)->first()->emi_amount)->toBe($loan->emi_amount);

    $this->post(route('loans.rate.update', $this->loan), ['effective_date' => '2027-06-01', 'new_rate' => '9', 'mode' => 'reduce_emi', 'reason' => 'x x'])
        ->assertSessionHas('error'); // nothing due after that date
});

it('closes only a fully repaid loan; closed loans take nothing; only the owner re-opens', function () {
    $this->post(route('loans.close', $this->loan), ['reason' => 'Done'])->assertSessionHas('error');

    prepay(['amount' => ($this->state)()['outstanding']])->assertSessionHasNoErrors();
    expect(($this->state)()['outstanding'])->toBe('0.00');

    $this->post(route('loans.close', $this->loan), ['reason' => 'Fully repaid'])->assertSessionHasNoErrors();
    expect($this->loan->fresh())->status->toBe(LoanStatus::Closed)->closed_on->toDateString()->toBe('2026-04-15');

    $this->post(route('loans.payments.store', $this->loan), ['kind' => 'charge', 'txn_date' => '2026-04-15', 'amount' => '100', 'payment_mode_id' => $this->bank])
        ->assertSessionHas('error');
    $this->get(route('loans.edit', $this->loan))->assertRedirect(route('loans.show', $this->loan));

    $this->post(route('loans.reopen', $this->loan), ['reason' => 'Try'])->assertForbidden();
    $this->actingAs($this->owner)->post(route('loans.reopen', $this->loan), ['reason' => 'Closed by mistake'])->assertSessionHasNoErrors();
    expect($this->loan->fresh())->status->toBe(LoanStatus::Active)->closed_on->toBeNull();
});
