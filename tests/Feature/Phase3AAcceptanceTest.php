<?php

use App\Enums\InstallmentFrequency;
use App\Enums\LoanStatus;
use App\Enums\MoratoriumInterest;
use App\Enums\RepaymentMethod;
use App\Enums\RoleName;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\Loan;
use App\Models\LoanRevision;
use App\Models\PaymentMode;
use App\Models\Section;
use App\Models\User;
use App\Services\LoanScheduleCalculator;
use App\Services\LoanService;
use App\Services\RevolvingAccountService;
use App\Support\CurrentFarm;
use App\Support\Money;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;

/** Phase 3A acceptance (spec §8): one check per bullet, end to end through the HTTP layer where it matters. */
beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->farm = Farm::where('code', 'MDF')->firstOrFail();
    app(CurrentFarm::class)->set($this->farm);
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->actingAs($this->owner);
    $this->travelTo('2026-11-09 10:00');
    $this->bank = PaymentMode::where('code', 'BANK')->value('id');
    $this->calc = app(LoanScheduleCalculator::class);
    $this->personal = fn () => app(LoanService::class)->create([
        'loan_type' => 'personal_loan', 'lender_name' => 'HDFC Bank', 'principal_amount' => '100000', 'interest_rate' => '12',
        'repayment_method' => 'emi_reducing', 'installment_frequency' => 'monthly', 'tenure_months' => 12,
        'start_date' => '2025-12-31', 'first_due_date' => '2026-01-31', 'processing_fee' => '0',
    ]);
});

it('builds the schedules of the spec (reducing, flat, quarterly, moratorium, bullet)', function () {
    $due = Carbon::parse('2026-01-31');
    $reducing = $this->calc->schedule(RepaymentMethod::EmiReducing, '100000', '12', 12, $due);
    expect($reducing['emi'])->toBe('8885.00')->and(Money::sum(array_column($reducing['rows'], 'principal_part')))->toBe('100000.00');

    $flat = $this->calc->schedule(RepaymentMethod::EmiFlat, '120000', '10', 24, $due);
    expect($flat['emi'])->toBe('6000.00')->and(Money::sum(array_column($flat['rows'], 'interest_part')))->toBe('24000.00');

    $quarterly = $this->calc->schedule(RepaymentMethod::EmiReducing, '200000', '9', 24, $due, InstallmentFrequency::Quarterly);
    expect($quarterly['rows'])->toHaveCount(8)->and(Money::sum(array_column($quarterly['rows'], 'principal_part')))->toBe('200000.00')
        ->and(Carbon::parse($quarterly['rows'][1]['due_date'])->toDateString())->toBe('2026-04-30');

    $paid = $this->calc->schedule(RepaymentMethod::EmiReducing, '100000', '12', 18, $due, InstallmentFrequency::Monthly, 6, MoratoriumInterest::Paid);
    expect(array_column(array_slice($paid['rows'], 0, 6), 'emi_amount'))->each->toBe('1000.00')->and($paid['rows'][6]['emi_amount'])->toBe('8885.00');
    $capitalised = $this->calc->schedule(RepaymentMethod::EmiReducing, '100000', '12', 18, $due, InstallmentFrequency::Monthly, 6, MoratoriumInterest::Capitalised);
    expect(Money::compare($capitalised['emi'], '8885.00'))->toBe(1);

    $bullet = $this->calc->schedule(RepaymentMethod::Bullet, '50000', '12', 12, $due);
    expect($bullet['rows'])->toHaveCount(1)->and($bullet['rows'][0]['emi_amount'])->toBe('56000.00');
});

it('pays EMIs, books only interest as expense, shows principal beside expenses, prepays and closes', function () {
    $loan = ($this->personal)();
    foreach (['2026-01-31', '2026-02-28', '2026-03-31'] as $date) {
        $this->post(route('loans.payments.store', $loan), ['kind' => 'payment', 'txn_date' => $date, 'amount' => '8885', 'payment_mode_id' => $this->bank])
            ->assertSessionHasNoErrors();
    }
    $third = $loan->installments()->where('installment_no', 3)->first();
    $this->get(route('loans.index'))->assertSee('3 / 12')->assertSee(fmt_date($loan->installments()->where('installment_no', 4)->value('due_date')))
        ->assertSee(inr($third->closing_principal));

    $interest = (string) Expense::where('loan_id', $loan->id)->sum('amount');
    $this->get(route('expenses.index', ['from' => '2026-01-01', 'to' => '2026-03-31', 'loan' => 'l:'.$loan->id]))
        ->assertViewHas('totals', fn ($t) => $t['total'] === Money::round2($interest))
        ->assertViewHas('principal', Money::sub('26655.00', Money::round2($interest)));

    // typed bank split is kept as typed
    $this->post(route('loans.payments.store', $loan), ['kind' => 'payment', 'txn_date' => '2026-04-30', 'amount' => '8885', 'payment_mode_id' => $this->bank,
        'interest_part' => '700', 'principal_part' => '8185'])->assertSessionHasNoErrors();
    expect($loan->transactions()->latest('id')->first())->interest_part->toBe('700.00')->principal_part->toBe('8185.00');

    $before = $loan->installments()->where('installment_no', 1)->value('emi_amount');
    $this->post(route('loans.prepayment.store', $loan), ['txn_date' => '2026-05-10', 'amount' => '20000', 'mode' => 'reduce_emi', 'payment_mode_id' => $this->bank])
        ->assertSessionHasNoErrors();
    expect(LoanRevision::where('loan_id', $loan->id)->exists())->toBeTrue()
        ->and($loan->installments()->where('installment_no', 1)->value('emi_amount'))->toBe($before)
        ->and(Money::compare($loan->fresh()->emi_amount, '8885.00'))->toBe(-1);

    $this->put(route('loans.update', $loan), ['loan_type' => 'personal_loan', 'lender_name' => 'HDFC Bank', 'principal_amount' => '150000',
        'interest_rate' => '12', 'repayment_method' => 'emi_reducing', 'installment_frequency' => 'monthly', 'tenure_months' => 12,
        'start_date' => '2025-12-31', 'first_due_date' => '2026-01-31', 'processing_fee' => '0', 'reason' => 'Try']);
    expect($loan->fresh()->principal_amount)->toBe('100000.00'); // terms are locked once paid

    $loan->update(['status' => LoanStatus::Closed]);
    $this->post(route('loans.payments.store', $loan), ['kind' => 'payment', 'txn_date' => '2026-06-30', 'amount' => '100', 'payment_mode_id' => $this->bank])
        ->assertSessionHas('error');
});

it('checks the OD limit, estimates interest and keeps card purchases as one expense', function () {
    $od = app(LoanService::class)->create(['loan_type' => 'overdraft', 'lender_name' => 'SBI', 'principal_amount' => '200000', 'interest_rate' => '12',
        'start_date' => '2026-01-01', 'processing_fee' => '0', 'interest_debit_cycle' => 'monthly']);
    $entry = fn (string $amount, string $date = '2026-11-01', string $type = 'od_withdrawal') => $this->post(route('loans.entries.store', $od),
        ['type' => $type, 'txn_date' => $date, 'amount' => $amount, 'payment_mode_id' => $this->bank]);
    $entry('150000')->assertSessionMissing('error');
    $entry('60000')->assertSessionHas('error');
    $this->get(route('loans.show', $od))->assertSee('₹50,000.00');

    $big = app(LoanService::class)->create(['loan_type' => 'cash_credit', 'lender_name' => 'PNB', 'principal_amount' => '1000000', 'interest_rate' => '12',
        'start_date' => '2026-01-01', 'processing_fee' => '0', 'interest_debit_cycle' => 'monthly']);
    foreach ([['400000', '2026-06-01'], ['300000', '2026-06-11']] as [$amount, $date]) {
        $this->post(route('loans.entries.store', $big), ['type' => 'od_withdrawal', 'txn_date' => $date, 'amount' => $amount])->assertSessionMissing('error');
    }
    expect(app(RevolvingAccountService::class)->estimate($big, '2026-06-01', '2026-06-30')['estimate'])->toBe('5917.81'); // ≈ ₹5,918

    $card = app(LoanService::class)->create(['loan_type' => 'credit_card', 'lender_name' => 'ICICI', 'principal_amount' => '100000', 'interest_rate' => '42',
        'start_date' => '2026-01-01', 'processing_fee' => '0', 'statement_day' => 5, 'due_day' => 25]);
    $this->post(route('expenses.store'), ['expense_date' => '2026-11-02', 'amount' => '12000', 'section_id' => Section::query()->value('id'),
        'category_id' => ExpenseCategory::query()->topLevel()->where('is_active', true)->value('id'), 'is_credit' => '0',
        'payment_mode_id' => PaymentMode::where('code', 'CARD')->value('id'), 'paid_by_card_id' => $card->id, 'paid_to_text' => 'Feed store'])
        ->assertSessionHasNoErrors();
    $this->post(route('loans.entries.store', $card), ['type' => 'od_repayment', 'txn_date' => '2026-11-07', 'amount' => '5000', 'payment_mode_id' => $this->bank])
        ->assertSessionMissing('error');
    expect(Expense::where('paid_by_card_id', $card->id)->count())->toBe(1)
        ->and(Expense::where('loan_id', $card->id)->count())->toBe(0) // bill payment is not an expense
        ->and(app(RevolvingAccountService::class)->utilized($card))->toBe('7000.00');
});

it('hides loans from staff without loans.view and from other farms', function () {
    $loan = ($this->personal)();
    $other = Loan::factory()->overdraft()->create(['farm_id' => Farm::factory()->create()->id]);
    $staff = User::factory()->forFarm($this->farm)->withRole(RoleName::HerdStaff)->create();

    foreach ([route('loans.index'), route('loans.show', $loan), route('loans.create'), route('reports.loans', 'summary')] as $url) {
        $this->actingAs($staff)->get($url)->assertForbidden();
    }
    $this->actingAs($this->owner)->get(route('loans.show', $other->id))->assertNotFound();
});
