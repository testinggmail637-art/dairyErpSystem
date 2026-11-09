<?php

use App\Enums\LoanStatus;
use App\Enums\LoanTxnType;
use App\Enums\RoleName;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\Loan;
use App\Models\LoanTransaction;
use App\Models\PaymentMode;
use App\Models\User;
use App\Services\LoanService;
use App\Services\RevolvingAccountService;
use App\Support\CurrentFarm;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->farm = Farm::where('code', 'MDF')->firstOrFail();
    app(CurrentFarm::class)->set($this->farm);
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
    $this->actingAs($this->accountant);
    $this->travelTo('2026-11-09 10:00');
    $this->bank = PaymentMode::where('code', 'BANK')->value('id');
    $this->od = fn (string $limit = '200000', string $type = 'overdraft') => app(LoanService::class)->create([
        'loan_type' => $type, 'lender_name' => 'SBI', 'principal_amount' => $limit, 'interest_rate' => '12',
        'start_date' => '2026-01-01', 'processing_fee' => '0', 'interest_debit_cycle' => 'monthly',
    ]);
});

function entry(Loan $loan, string $type, string $amount, string $date = '2026-11-01', array $extra = []): TestResponse
{
    return test()->post(route('loans.entries.store', $loan), array_merge(['type' => $type, 'txn_date' => $date, 'amount' => $amount,
        'payment_mode_id' => test()->bank], $extra));
}

it('refuses a withdrawal over the limit and shows what is available (spec acceptance)', function () {
    $od = ($this->od)();
    entry($od, 'od_withdrawal', '100000')->assertSessionHasNoErrors();
    entry($od, 'od_withdrawal', '50000')->assertSessionHasNoErrors();
    entry($od, 'od_withdrawal', '60000')->assertSessionHas('error');

    $accounts = app(RevolvingAccountService::class);
    expect($accounts->utilized($od))->toBe('150000.00')->and(LoanTransaction::count())->toBe(2);

    $this->get(route('loans.show', $od))->assertOk()->assertSee('₹50,000.00')->assertSee('₹1,50,000.00');
    $this->get(route('loans.index'))->assertOk()->assertSee('₹50,000.00 free');
});

it('books interest debited and charges as expenses, but not withdrawals, repayments or subvention', function () {
    $kcc = ($this->od)('160000', 'kcc');
    entry($kcc, 'od_withdrawal', '100000')->assertSessionHasNoErrors();
    entry($kcc, 'od_interest', '583.33', '2026-11-05', ['payment_mode_id' => ''])->assertSessionHasNoErrors();
    entry($kcc, 'charge', '590', '2026-11-05', ['note' => 'Renewal fee'])->assertSessionHasNoErrors();
    entry($kcc, 'subvention_credit', '250', '2026-11-06', ['payment_mode_id' => ''])->assertSessionHasNoErrors();
    entry($kcc, 'od_repayment', '20000', '2026-11-07')->assertSessionHasNoErrors();

    $expenses = Expense::where('loan_id', $kcc->id)->with('subcategory')->get();
    expect($expenses->map(fn ($e) => [$e->subcategory->name, $e->amount])->sortBy(0)->values()->all())
        ->toBe([['Bank Charges', '590.00'], ['Loan Interest', '583.33']])
        ->and($expenses->firstWhere('amount', '583.33')->payment_mode_id)->toBe($this->bank)
        ->and(app(RevolvingAccountService::class)->utilized($kcc))->toBe('80923.33'); // 1,00,000 + 583.33 + 590 − 250 − 20,000

    entry($kcc, 'od_repayment', '90000', '2026-11-08')->assertSessionHas('error'); // more than used
    entry($kcc, 'od_repayment', '100', '2026-11-08', ['payment_mode_id' => ''])->assertSessionHasErrors('payment_mode_id');
});

it('estimates interest from the daily balance (₹4 lakh for 10 days + ₹7 lakh for 20 days at 12 % → ₹5,918)', function () {
    $od = ($this->od)('1000000');
    entry($od, 'od_withdrawal', '400000', '2026-09-01');
    entry($od, 'od_withdrawal', '300000', '2026-09-11');
    entry($od, 'od_interest', '5918', '2026-09-30', ['payment_mode_id' => '']);

    $e = app(RevolvingAccountService::class)->estimate($od, '2026-09-01', '2026-09-29');
    expect($e['days'])->toBe(29)->and($e['estimate'])->toBe('5687.67'); // 29 days (interest debited on the 30th)

    $full = app(RevolvingAccountService::class)->estimate($od, '2026-09-01', '2026-09-30');
    // day 30 also counts the interest debit itself in its closing balance: 7,05,918 for that day
    expect($full['bank'])->toBe('5918.00')->and($full['estimate'])->toBe('5919.75');

    $clean = ($this->od)('1000000'); // the spec example exactly: no interest debit inside the 30 days
    entry($clean, 'od_withdrawal', '400000', '2026-06-01');
    entry($clean, 'od_withdrawal', '300000', '2026-06-11');
    expect(app(RevolvingAccountService::class)->estimate($clean, '2026-06-01', '2026-06-30')['estimate'])->toBe('5917.81'); // ≈ ₹5,918

    expect(app(RevolvingAccountService::class)->lastCycle($od))->toBe(['2026-10-01', '2026-10-31']);
    $this->get(route('loans.show', [$od, 'from' => '2026-09-01', 'to' => '2026-09-29']))->assertOk()->assertSee('₹5,687.67')->assertSee('Used after');
});

it('shows a running balance and changes only the latest entry, with a reason', function () {
    $od = ($this->od)();
    entry($od, 'od_withdrawal', '50000', '2026-11-01');
    entry($od, 'od_repayment', '20000', '2026-11-03');
    [$first, $latest] = LoanTransaction::orderBy('id')->get()->all();

    $ledger = app(RevolvingAccountService::class)->ledger($od);
    expect($ledger->getCollection()->pluck('balance')->map(fn ($b) => number_format((float) $b, 2, '.', ''))->all())->toBe(['30000.00', '50000.00']);

    $this->get(route('loan-transactions.entry.edit', $first))->assertForbidden();
    $this->put(route('loan-transactions.entry.update', $latest), ['type' => 'od_repayment', 'txn_date' => '2026-11-03', 'amount' => '25000',
        'payment_mode_id' => $this->bank])->assertSessionHasErrors('reason');
    $this->put(route('loan-transactions.entry.update', $latest), ['type' => 'od_repayment', 'txn_date' => '2026-11-03', 'amount' => '25000',
        'payment_mode_id' => $this->bank, 'reason' => 'Statement says 25,000'])->assertSessionHasNoErrors();
    expect(app(RevolvingAccountService::class)->utilized($od))->toBe('25000.00');

    $this->delete(route('loan-transactions.destroy', $latest), ['reason' => 'Wrong'])->assertSessionHasNoErrors();
    expect(app(RevolvingAccountService::class)->utilized($od))->toBe('50000.00');
});

it('closes only when nothing is used; instalment entries are refused on these accounts', function () {
    $od = ($this->od)();
    entry($od, 'od_withdrawal', '10000');
    $this->post(route('loans.close', $od), ['reason' => 'Done'])->assertSessionHas('error');
    entry($od, 'od_repayment', '10000', '2026-11-02');
    $this->post(route('loans.close', $od), ['reason' => 'Limit not renewed'])->assertSessionHasNoErrors();
    expect($od->fresh()->status)->toBe(LoanStatus::Closed);
    entry($od, 'od_withdrawal', '100', '2026-11-03')->assertSessionHas('error');

    $other = ($this->od)();
    $this->post(route('loans.payments.store', $other), ['kind' => 'payment', 'txn_date' => '2026-11-01', 'amount' => '100', 'payment_mode_id' => $this->bank])
        ->assertSessionHas('error');
    expect(LoanTransaction::where('loan_id', $other->id)->exists())->toBeFalse()
        ->and(LoanTxnType::OdWithdrawal->label())->toBe('Withdrawal');
});
