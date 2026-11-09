<?php

namespace App\Http\Controllers\Loans;

use App\Http\Controllers\Controller;
use App\Http\Requests\Loans\CloseLoanRequest;
use App\Http\Requests\Loans\PrepaymentRequest;
use App\Http\Requests\Loans\RateChangeRequest;
use App\Models\Loan;
use App\Models\PaymentMode;
use App\Services\LoanPaymentService;
use App\Services\LoanRescheduleService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Prepayment, interest rate change, close and re-open of a loan (spec 7.4A). */
class LoanActionController extends Controller
{
    public function __construct(private readonly LoanRescheduleService $reschedule) {}

    public function prepaymentForm(Loan $loan, LoanPaymentService $payments): View
    {
        return view('loans.prepayment', [
            'loan' => $loan,
            'state' => $payments->state($loan),
            'paymentModes' => PaymentMode::query()->active()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function prepay(PrepaymentRequest $request, Loan $loan): RedirectResponse
    {
        return $this->attempt($loan, fn () => $this->reschedule->prepay($loan, $request->validated()),
            __('Prepayment saved; the remaining instalments were re-planned.'));
    }

    public function rateForm(Loan $loan, LoanPaymentService $payments): View
    {
        return view('loans.rate-change', ['loan' => $loan, 'state' => $payments->state($loan)]);
    }

    public function changeRate(RateChangeRequest $request, Loan $loan): RedirectResponse
    {
        return $this->attempt($loan, fn () => $this->reschedule->changeRate($loan, $request->validated()),
            __('Interest rate changed; the unpaid instalments were re-planned.'));
    }

    public function close(CloseLoanRequest $request, Loan $loan): RedirectResponse
    {
        return $this->attempt($loan, fn () => $this->reschedule->close($loan, today()->toDateString()), __('Loan closed.'));
    }

    public function reopen(CloseLoanRequest $request, Loan $loan): RedirectResponse
    {
        return $this->attempt($loan, fn () => $this->reschedule->reopen($loan), __('Loan re-opened.'));
    }

    private function attempt(Loan $loan, callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('loans.show', $loan)->with('success', $message);
    }
}
