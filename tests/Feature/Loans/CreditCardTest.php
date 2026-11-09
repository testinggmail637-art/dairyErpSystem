<?php

use App\Enums\RoleName;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\LoanTransaction;
use App\Models\PaymentMode;
use App\Models\Section;
use App\Models\User;
use App\Services\LoanService;
use App\Services\RevolvingAccountService;
use App\Support\CurrentFarm;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->farm = Farm::where('code', 'MDF')->firstOrFail();
    app(CurrentFarm::class)->set($this->farm);
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    $this->actingAs($this->owner);
    $this->travelTo('2026-11-09 10:00');
    $this->bank = PaymentMode::where('code', 'BANK')->value('id');
    $this->cardMode = PaymentMode::where('code', 'CARD')->value('id');
    $this->card = app(LoanService::class)->create([
        'loan_type' => 'credit_card', 'lender_name' => 'HDFC', 'principal_amount' => '100000', 'interest_rate' => '42',
        'start_date' => '2026-01-01', 'processing_fee' => '0', 'statement_day' => 5, 'due_day' => 25,
    ]);
    $this->category = ExpenseCategory::query()->topLevel()->where('is_active', true)->firstOrFail();
    $this->section = Section::query()->firstOrFail();
});

function cardPurchase(string $amount, string $date = '2026-11-02', array $extra = []): array
{
    $t = test();

    return array_merge(['expense_date' => $date, 'amount' => $amount, 'category_id' => $t->category->id, 'section_id' => $t->section->id,
        'is_credit' => '0', 'payment_mode_id' => $t->cardMode, 'paid_by_card_id' => $t->card->id, 'paid_to_text' => 'Feed store'], $extra);
}

function cardEntry(string $type, string $amount, string $date, array $extra = []): void
{
    test()->post(route('loans.entries.store', test()->card), array_merge(['type' => $type, 'txn_date' => $date, 'amount' => $amount,
        'payment_mode_id' => test()->bank], $extra))->assertRedirect()->assertSessionHasNoErrors()->assertSessionMissing('error');
}

it('counts a card purchase once as an expense; the bill payment is not an expense (spec acceptance)', function () {
    $this->post(route('expenses.store'), cardPurchase('30000'))->assertSessionHasNoErrors();
    cardEntry('od_repayment', '30000', '2026-11-07');

    $accounts = app(RevolvingAccountService::class);
    expect(Expense::count())->toBe(1)
        ->and(Expense::sole()->paid_by_card_id)->toBe($this->card->id)
        ->and($accounts->utilized($this->card))->toBe('0.00')
        ->and($accounts->utilized($this->card, '2026-11-05'))->toBe('30000.00');

    $ledger = $accounts->ledger($this->card)->getCollection();
    expect($ledger->pluck('type')->all())->toBe(['od_repayment', 'card_purchase']);
    $this->get(route('loans.show', $this->card))->assertOk()->assertSee('Card purchase (expense)')->assertSee('Bill payment')
        ->assertDontSee('Check the bank');
});

it('refuses a purchase over the free limit and keeps the card only with the Card payment mode', function () {
    $this->post(route('expenses.store'), cardPurchase('90000'))->assertSessionHasNoErrors();
    $this->post(route('expenses.store'), cardPurchase('10000.01'))->assertSessionHasErrors('amount');

    $cash = PaymentMode::where('code', 'CASH')->value('id');
    $this->post(route('expenses.store'), cardPurchase('500', '2026-11-03', ['payment_mode_id' => $cash]))->assertSessionHasNoErrors();
    expect(Expense::where('amount', '500.00')->sole()->paid_by_card_id)->toBeNull();

    // editing the purchase itself does not count it twice
    $purchase = Expense::where('amount', '90000.00')->sole();
    $this->put(route('expenses.update', $purchase), cardPurchase('95000', '2026-11-02', ['reason' => 'Bill corrected']))->assertSessionHasNoErrors();
    expect(app(RevolvingAccountService::class)->utilized($this->card))->toBe('95000.00')
        ->and(app(LoanService::class)->hasTransactions($this->card))->toBeTrue();
});

it('shows the last statement, due date and the part-paid warning', function () {
    $this->post(route('expenses.store'), cardPurchase('30000'))->assertSessionHasNoErrors();
    cardEntry('od_repayment', '10000', '2026-11-07');

    $bill = app(RevolvingAccountService::class)->card($this->card);
    expect($bill['statement_date']->toDateString())->toBe('2026-11-05')
        ->and($bill['statement_amount'])->toBe('30000.00')
        ->and($bill['due_date']->toDateString())->toBe('2026-11-25')
        ->and($bill['to_pay'])->toBe('20000.00')
        ->and($bill['part_paid'])->toBeFalse();

    $this->travelTo('2026-11-26 10:00');
    $bill = app(RevolvingAccountService::class)->card($this->card);
    expect($bill['part_paid'])->toBeTrue()->and($bill['next_due']->toDateString())->toBe('2026-12-25');
    $this->get(route('loans.show', $this->card))->assertOk()->assertSee('Part paid — interest is running');

    $this->travelTo('2026-11-20 10:00');
    cardEntry('od_repayment', '20000', '2026-11-20');
    $this->travelTo('2026-11-26 10:00');
    expect(app(RevolvingAccountService::class)->card($this->card)['part_paid'])->toBeFalse();
});

it('books statement interest + GST and fees as expenses; a card takes no subvention', function () {
    $this->post(route('expenses.store'), cardPurchase('30000'))->assertSessionHasNoErrors();
    cardEntry('od_interest', '1239', '2026-11-05', ['payment_mode_id' => '', 'note' => 'Interest 1050 + GST 189']);
    cardEntry('charge', '590', '2026-11-05', ['note' => 'Late fee + GST']);

    expect(Expense::whereNotNull('loan_id')->where('loan_id', $this->card->id)->pluck('amount')->sort()->values()->all())->toBe(['590.00', '1239.00'])
        ->and(app(RevolvingAccountService::class)->utilized($this->card))->toBe('31829.00');

    $this->post(route('loans.entries.store', $this->card), ['type' => 'subvention_credit', 'txn_date' => '2026-11-06', 'amount' => '10'])
        ->assertSessionHasErrors('type');
    expect(LoanTransaction::count())->toBe(2);

    $this->get(route('loans.create', ['loan_type' => 'card_emi']))->assertOk()->assertSee('value="card_emi" selected', false);
    $this->get(route('expenses.create', ['paid_by_card_id' => $this->card->id]))->assertOk()->assertSee('Which credit card?')
        ->assertSee('data-card-mode="'.$this->cardMode.'"', false);
});
