<?php

use App\Enums\LoanTxnType;
use App\Enums\RoleName;
use App\Models\Activity;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\Loan;
use App\Models\LoanTransaction;
use App\Models\PaymentMode;
use App\Models\User;
use App\Services\LoanPaymentService;
use App\Services\LoanService;
use App\Support\CurrentFarm;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->farm = Farm::where('code', 'MDF')->firstOrFail();
    app(CurrentFarm::class)->set($this->farm);
    $this->accountant = User::factory()->forFarm($this->farm)->withRole(RoleName::Accountant)->create();
    $this->actingAs($this->accountant);
    $this->travelTo('2026-05-10 10:00');
    $this->bank = PaymentMode::where('code', 'BANK')->value('id');
    // ₹1,00,000 at 12 % reducing, 12 monthly EMIs of ₹8,885 from 31-01-2026 (spec acceptance loan)
    $this->loan = app(LoanService::class)->create([
        'loan_type' => 'personal_loan', 'lender_name' => 'HDFC Bank', 'principal_amount' => '100000', 'interest_rate' => '12',
        'repayment_method' => 'emi_reducing', 'installment_frequency' => 'monthly', 'tenure_months' => 12,
        'start_date' => '2025-12-31', 'first_due_date' => '2026-01-31', 'processing_fee' => '0',
    ]);
});

function pay(array $overrides = []): TestResponse
{
    return test()->post(route('loans.payments.store', test()->loan), array_merge(
        ['kind' => 'payment', 'txn_date' => '2026-05-10', 'amount' => '8885', 'payment_mode_id' => test()->bank], $overrides));
}

it('pays 3 EMIs: 3 / 12 paid, next due = 4th, outstanding = closing principal of the 3rd (spec acceptance)', function () {
    foreach (['2026-01-31', '2026-02-28', '2026-03-31'] as $date) {
        pay(['txn_date' => $date])->assertSessionHasNoErrors();
    }

    $state = app(LoanPaymentService::class)->state($this->loan);
    $third = $this->loan->installments()->where('installment_no', 3)->first();
    expect($state['paid_count'])->toBe(3)->and($state['total'])->toBe(12)
        ->and($state['next']->installment_no)->toBe(4)->and($state['next']->due_date->toDateString())->toBe('2026-04-30')
        ->and($state['outstanding'])->toBe($third->closing_principal)
        ->and($state['next']->status)->toBe('overdue'); // 30-04 has passed

    $first = LoanTransaction::orderBy('id')->first();
    expect($first)->type->toBe(LoanTxnType::EmiPayment)->interest_part->toBe('1000.00')->principal_part->toBe('7885.00');

    $this->get(route('loans.index'))->assertOk()->assertSee('3 / 12')->assertSee('30-04-2026');
    $this->get(route('loans.show', $this->loan))->assertOk()->assertSee('Overdue')->assertSee('3 / 12');
});

it('books only the interest as an expense (Finance → Loan Interest, linked to the loan)', function () {
    pay(['txn_date' => '2026-01-31'])->assertSessionHasNoErrors();

    $txn = LoanTransaction::sole();
    $expense = Expense::findOrFail($txn->expense_id);
    expect($expense)->amount->toBe('1000.00')->loan_id->toBe($this->loan->id)->payment_mode_id->toBe($this->bank)
        ->is_credit->toBeFalse()->and($expense->category->name)->toBe('Finance')->and($expense->subcategory->name)->toBe('Loan Interest')
        ->and(Expense::where('loan_id', $this->loan->id)->sum('amount'))->toEqual(1000); // principal ₹7,885 is not an expense
});

it('covers instalments oldest first, part-pays, carries over, and stores a typed split', function () {
    pay(['amount' => '500'])->assertSessionHasNoErrors();
    expect(LoanTransaction::sole())->type->toBe(LoanTxnType::InterestPayment)->interest_part->toBe('500.00')->principal_part->toBe('0.00');

    pay(['amount' => '10000'])->assertSessionHasNoErrors(); // finishes #1 (8,385) and starts #2
    $rows = app(LoanPaymentService::class)->state($this->loan)['rows'];
    expect($rows[0]->status)->toBe('paid')->and($rows[1]->status)->toBe('overdue')->and($rows[1]->balance_due)->toBe('7270.00');

    pay(['amount' => '7270', 'interest_part' => '930', 'principal_part' => '6340'])->assertSessionHasNoErrors();
    expect(LoanTransaction::latest('id')->first())->interest_part->toBe('930.00')->principal_part->toBe('6340.00');

    pay(['amount' => '100', 'interest_part' => '10', 'principal_part' => '20'])->assertSessionHas('error');
    pay(['amount' => '999999'])->assertSessionHas('error'); // more than everything still due → use prepayment
    expect(LoanTransaction::count())->toBe(3);
});

it('records a bank charge as a Bank Charges expense, not against instalments', function () {
    pay(['kind' => 'charge', 'amount' => '590', 'note' => 'Late payment charge'])->assertSessionHasNoErrors();

    $txn = LoanTransaction::sole();
    expect($txn)->type->toBe(LoanTxnType::Charge)->interest_part->toBe('0.00')->principal_part->toBe('0.00')
        ->and(Expense::find($txn->expense_id)->subcategory->name)->toBe('Bank Charges')
        ->and(app(LoanPaymentService::class)->state($this->loan)['paid_count'])->toBe(0);
});

it('edits and deletes only the latest entry, with a reason, keeping the expense in step', function () {
    pay(['txn_date' => '2026-01-31'])->assertSessionHasNoErrors();
    pay(['txn_date' => '2026-02-28'])->assertSessionHasNoErrors();
    [$older, $latest] = LoanTransaction::orderBy('id')->get()->all();

    $this->put(route('loan-transactions.update', $older), ['kind' => 'payment', 'txn_date' => '2026-01-31', 'amount' => '8000',
        'payment_mode_id' => $this->bank, 'reason' => 'Try'])->assertForbidden();

    $this->put(route('loan-transactions.update', $latest), ['kind' => 'payment', 'txn_date' => '2026-02-28', 'amount' => '8885',
        'interest_part' => '930', 'principal_part' => '7955', 'payment_mode_id' => $this->bank])->assertSessionHasErrors('reason');
    $this->put(route('loan-transactions.update', $latest), ['kind' => 'payment', 'txn_date' => '2026-02-28', 'amount' => '8885',
        'interest_part' => '930', 'principal_part' => '7955', 'payment_mode_id' => $this->bank, 'reason' => 'As per bank statement'])
        ->assertSessionHasNoErrors();
    expect(Expense::find($latest->fresh()->expense_id)->amount)->toBe('930.00')
        ->and(Activity::where('subject_type', $latest->getMorphClass())->where('event', 'updated')->latest('id')->first()->reason())->toBe('As per bank statement');

    $this->delete(route('loan-transactions.destroy', $latest), ['reason' => 'Bounced'])->assertSessionHasNoErrors();
    expect(LoanTransaction::count())->toBe(1)->and(Expense::where('loan_id', $this->loan->id)->count())->toBe(1);
});

it('refuses payments on a closed loan, and loan pages need loan_payments rights', function () {
    $this->loan->update(['status' => 'closed']);
    pay()->assertSessionHas('error');
    expect(LoanTransaction::count())->toBe(0);

    $herd = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();
    $this->actingAs($herd)->get(route('loans.payments.create', $this->loan))->assertForbidden();
    $this->post(route('loans.payments.store', $this->loan), ['amount' => '1'])->assertForbidden();
});

it('saves a payment once per client_uuid and suggests the split while typing', function () {
    $uuid = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
    pay(['client_uuid' => $uuid])->assertSessionHasNoErrors();
    pay(['client_uuid' => $uuid])->assertSessionHas('success', 'This entry was already saved.');
    expect(LoanTransaction::count())->toBe(1);

    $this->getJson(route('loans.payments.split', [$this->loan, 'amount' => '9000']))
        ->assertOk()->assertExactJson(['interest' => '1036.15', 'principal' => '7963.85']); // rest of #2 (921.15 + 7,963.85) + ₹115 of #3's interest
});
