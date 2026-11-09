<?php

namespace App\Http\Controllers\Loans;

use App\Enums\LoanTxnType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Loans\DeleteLoanTransactionRequest;
use App\Http\Requests\Loans\LoanPaymentRequest;
use App\Models\Loan;
use App\Models\LoanTransaction;
use App\Models\PaymentMode;
use App\Services\LoanPaymentService;
use App\Support\Money;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Record / edit / delete a payment or bank charge on an instalment loan (spec 7.4A). */
class LoanPaymentController extends Controller
{
    public function __construct(private readonly LoanPaymentService $payments) {}

    public function create(Request $request, Loan $loan): View|RedirectResponse
    {
        if (! $loan->hasSchedule()) {
            return redirect()->route('loans.show', $loan);
        }

        $state = $this->payments->state($loan);

        return view('loans.payment', $this->formData($loan, new LoanTransaction([
            'txn_date' => today(),
            'amount' => $state['next']?->balance_due,
        ]), $state) + ['kind' => $request->query('kind') === 'charge' ? 'charge' : 'payment']);
    }

    public function store(LoanPaymentRequest $request, Loan $loan): RedirectResponse
    {
        try {
            [$txn, $created] = $this->payments->record($loan, $request->validated());
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('loans.show', $loan)->with('success', $created
            ? __(':type of :amount saved.', ['type' => $txn->type->label(), 'amount' => inr($txn->amount)])
            : __('This entry was already saved.'));
    }

    /** Suggested interest / principal for an amount (payment form, as you type). */
    public function split(Request $request, Loan $loan): JsonResponse
    {
        $amount = str_replace([',', ' ', '₹'], '', (string) $request->query('amount'));

        return response()->json(is_numeric($amount) && Money::compare($amount, '0') > 0
            ? $this->payments->suggestSplit($loan, $amount, $request->integer('except') ?: null)
            : ['interest' => '0.00', 'principal' => '0.00']);
    }

    public function edit(LoanTransaction $loanTransaction): View
    {
        $loan = $loanTransaction->loan;

        return view('loans.payment', $this->formData($loan, $loanTransaction, $this->payments->state($loan, $loanTransaction->id))
            + ['kind' => $loanTransaction->type === LoanTxnType::Charge ? 'charge' : 'payment']);
    }

    public function update(LoanPaymentRequest $request, LoanTransaction $loanTransaction): RedirectResponse
    {
        try {
            $this->payments->update($loanTransaction, $request->validated());
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('loans.show', $loanTransaction->loan_id)->with('success', __('Entry updated.'));
    }

    public function destroy(DeleteLoanTransactionRequest $request, LoanTransaction $loanTransaction): RedirectResponse
    {
        try {
            $this->payments->delete($loanTransaction);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('loans.show', $loanTransaction->loan_id)->with('success', __('Entry deleted.'));
    }

    /** @return array<string, mixed> */
    private function formData(Loan $loan, LoanTransaction $txn, array $state): array
    {
        $current = $txn->payment_mode_id;

        return [
            'loan' => $loan,
            'txn' => $txn,
            'state' => $state,
            'paymentModes' => PaymentMode::query()
                ->where(fn ($q) => $q->where('is_active', true)->when($current, fn ($q) => $q->orWhere('id', $current)))
                ->orderBy('name')->pluck('name', 'id'),
        ];
    }
}
