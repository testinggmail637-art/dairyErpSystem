<?php

namespace App\Http\Controllers\Loans;

use App\Enums\LoanTxnType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Loans\RevolvingEntryRequest;
use App\Models\Loan;
use App\Models\LoanTransaction;
use App\Models\PaymentMode;
use App\Services\RevolvingAccountService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Withdrawal / repayment / interest debited / charge / subvention on an OD, CC or KCC account (spec 7.4A). */
class RevolvingEntryController extends Controller
{
    public function __construct(private readonly RevolvingAccountService $accounts) {}

    public function create(Request $request, Loan $loan): View|RedirectResponse
    {
        if (! $loan->isRevolving()) {
            return redirect()->route('loans.show', $loan);
        }

        $type = LoanTxnType::tryFrom((string) $request->query('type')) ?? LoanTxnType::OdWithdrawal;

        return view('loans.revolving-entry', $this->formData($loan, new LoanTransaction(['type' => $type, 'txn_date' => today()])));
    }

    public function store(RevolvingEntryRequest $request, Loan $loan): RedirectResponse
    {
        try {
            [$txn, $created] = $this->accounts->record($loan, $request->validated());
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('loans.show', $loan)->with('success', $created
            ? __(':type of :amount saved.', ['type' => $txn->type->label(), 'amount' => inr($txn->amount)])
            : __('This entry was already saved.'));
    }

    public function edit(LoanTransaction $loanTransaction): View
    {
        return view('loans.revolving-entry', $this->formData($loanTransaction->loan, $loanTransaction));
    }

    public function update(RevolvingEntryRequest $request, LoanTransaction $loanTransaction): RedirectResponse
    {
        try {
            $this->accounts->update($loanTransaction, $request->validated());
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('loans.show', $loanTransaction->loan_id)->with('success', __('Entry updated.'));
    }

    /** @return array<string, mixed> */
    private function formData(Loan $loan, LoanTransaction $txn): array
    {
        $current = $txn->payment_mode_id;

        return [
            'loan' => $loan,
            'txn' => $txn,
            'used' => $this->accounts->utilized($loan, null, $txn->exists ? $txn->id : null),
            'types' => collect(RevolvingAccountService::TYPES)->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all(),
            'paymentModes' => PaymentMode::query()
                ->where(fn ($q) => $q->where('is_active', true)->when($current, fn ($q) => $q->orWhere('id', $current)))
                ->orderBy('name')->pluck('name', 'id'),
        ];
    }
}
