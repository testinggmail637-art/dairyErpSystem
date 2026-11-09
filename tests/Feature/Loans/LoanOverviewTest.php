<?php

use App\Enums\RoleName;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\PaymentMode;
use App\Models\Section;
use App\Models\User;
use App\Services\LoanService;
use App\Support\CurrentFarm;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->farm = Farm::where('code', 'MDF')->firstOrFail();
    app(CurrentFarm::class)->set($this->farm);
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->staff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
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
        'start_date' => '2026-01-01', 'processing_fee' => '0', 'interest_debit_cycle' => 'monthly', 'od_renewal_date' => '2026-12-15']);
    $this->card = $loans->create(['loan_type' => 'credit_card', 'lender_name' => 'ICICI', 'principal_amount' => '100000', 'interest_rate' => '42',
        'start_date' => '2026-01-01', 'processing_fee' => '0', 'statement_day' => 5, 'due_day' => 25]);

    // 9 EMIs paid (Jan–Sep): the 10th (31-10-2026) is overdue
    foreach (['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30', '2026-05-31', '2026-06-30', '2026-07-31', '2026-08-31', '2026-09-30'] as $date) {
        $this->post(route('loans.payments.store', $this->loan), ['kind' => 'payment', 'txn_date' => $date, 'amount' => '8885',
            'payment_mode_id' => $this->bank])->assertSessionHasNoErrors();
    }
    $this->post(route('loans.entries.store', $this->od), ['type' => 'od_withdrawal', 'txn_date' => '2026-11-01', 'amount' => '50000'])
        ->assertSessionHasNoErrors();
    $this->post(route('expenses.store'), ['expense_date' => '2026-11-02', 'amount' => '30000',
        'category_id' => ExpenseCategory::query()->topLevel()->where('is_active', true)->value('id'), 'section_id' => Section::query()->value('id'),
        'is_credit' => '0', 'payment_mode_id' => PaymentMode::where('code', 'CARD')->value('id'), 'paid_by_card_id' => $this->card->id,
        'paid_to_text' => 'Feed store'])->assertSessionHasNoErrors();
});

it('shows loan cards on the dashboard only with dashboard.financial and loans.view', function () {
    $this->get(route('dashboard'))->assertOk()
        ->assertSee('Loan outstanding')->assertSee('Overdue instalments')->assertSee('₹8,885.00')
        ->assertSee('OD / card used')->assertSee('₹80,000.00')->assertSee('of ₹3,00,000.00 limit');

    $this->actingAs($this->staff)->get(route('dashboard'))->assertOk()->assertDontSee('Loan outstanding');
});

it('sends reminders: due in 3 days / today / overdue, card bill, interest debit and renewal — once a day', function () {
    $this->travelTo('2026-11-08 08:00');
    $this->artisan('loans:reminders')->assertSuccessful();
    expect($this->owner->notifications()->pluck('data')->pluck('message')->all())
        ->toBe(['HDFC Bank ('.$this->loan->loan_no.'): instalment 10 of ₹8,885.00 was due on 31-10-2026 — 8 days overdue.']);

    $this->artisan('loans:reminders')->assertSuccessful(); // same day again: nothing new
    expect($this->owner->notifications()->count())->toBe(1);

    $this->owner->notifications()->delete();
    $this->travelTo('2026-11-22 08:00');
    $this->artisan('loans:reminders')->assertSuccessful();
    expect($this->owner->notifications()->pluck('data')->pluck('message')->sort()->values()->all())->toBe([
        'HDFC Bank ('.$this->loan->loan_no.'): instalment 10 of ₹8,885.00 was due on 31-10-2026 — 22 days overdue.', // repeats weekly
        'ICICI ('.$this->card->loan_no.'): card bill of ₹30,000.00 is due on 25-11-2026 (in 3 days). Pay it in full to avoid interest.',
    ]);

    $this->owner->notifications()->delete();
    $this->travelTo('2026-11-30 08:00');
    $this->artisan('loans:reminders')->assertSuccessful();
    $sent = $this->owner->notifications()->pluck('data')->pluck('message')->sort()->values()->all();
    expect($sent)->toHaveCount(3)
        ->and($sent[0])->toBe('HDFC Bank ('.$this->loan->loan_no.'): instalment 11 of ₹8,885.00 is due today.')
        ->and($sent[1])->toStartWith('SBI ('.$this->od->loan_no.'): limit renewal is due on 15-12-2026')
        ->and($sent[2])->toStartWith('SBI ('.$this->od->loan_no.'): the bank debits interest today (about ₹390.41');
    expect($this->staff->notifications()->count())->toBe(0);
});

it('filters expenses by loan and shows principal repaid beside the expenses, not in them (owner\'s choice)', function () {
    $this->post(route('loans.entries.store', $this->od), ['type' => 'od_interest', 'txn_date' => '2026-11-05', 'amount' => '400'])
        ->assertSessionHasNoErrors();

    // 9 EMIs: interest parts are expenses, principal parts are not
    $interest = Expense::where('loan_id', $this->loan->id)->sum('amount');
    $all = ['from' => '2026-01-01', 'to' => '2026-11-09'];

    $this->get(route('expenses.index', $all + ['loan' => 'l:'.$this->loan->id]))->assertOk()
        ->assertViewHas('totals', fn ($t) => $t['count'] === 9 && $t['total'] === number_format((float) $interest, 2, '.', ''))
        ->assertViewHas('principal', fn ($p) => $p === bcsub('79965.00', number_format((float) $interest, 2, '.', ''), 2))
        ->assertSee('Loan principal repaid')->assertSee('Total cash paid out');

    $this->get(route('expenses.index', $all + ['loan' => 't:overdraft']))->assertOk()
        ->assertViewHas('totals', fn ($t) => $t['count'] === 1 && $t['total'] === '400.00')->assertViewHas('principal', '0.00');
    $this->get(route('expenses.index', $all + ['loan' => 'any']))->assertOk()->assertViewHas('totals', fn ($t) => $t['count'] === 10);
    // the card purchase is an ordinary expense, not a loan cost; the unfiltered total = every expense (incl. it)
    $this->get(route('expenses.index', $all))->assertOk()->assertViewHas('totals', fn ($t) => $t['count'] === 11);

    $this->get(route('expenses.index', ['loan' => 'x']))->assertSessionHasErrors('loan');
});

it('filters the loans list by kind', function () {
    $this->get(route('loans.index', ['type' => 'family:revolving']))->assertOk()->assertSee('SBI')->assertDontSee('ICICI')->assertDontSee('HDFC Bank');
    $this->get(route('loans.index', ['type' => 'family:credit_card']))->assertOk()->assertSee('ICICI')->assertDontSee('SBI');
});
