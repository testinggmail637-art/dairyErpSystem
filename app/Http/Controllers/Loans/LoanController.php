<?php

namespace App\Http\Controllers\Loans;

use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Loans\DeleteLoanRequest;
use App\Http\Requests\Loans\LoanRequest;
use App\Models\Expense;
use App\Models\Loan;
use App\Services\LoanService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Expenses → Loans (spec Phase 3A): loans and overdrafts the business has taken. Payments, prepayment, rate change,
 * close and the full detail screens come in Tasks 3A.3–3A.6.
 */
class LoanController extends Controller
{
    public function __construct(private readonly LoanService $loans) {}

    public function index(Request $request): View|Response
    {
        $filters = [
            'search' => trim((string) $request->query('search')),
            'type' => (string) $request->query('type'),
            'status' => $request->query('status', LoanStatus::Active->value),
        ];

        $base = Loan::query()
            ->when($filters['search'] !== '', fn ($q) => $q->where(fn ($w) => $w->where('lender_name', 'like', "%{$filters['search']}%")
                ->orWhere('loan_no', 'like', "%{$filters['search']}%")->orWhere('account_no', $filters['search'])))
            ->when(LoanType::tryFrom($filters['type']), fn ($q, $t) => $q->where('loan_type', $t))
            ->when(LoanStatus::tryFrom((string) $filters['status']), fn ($q, $s) => $q->where('status', $s));

        $loans = (clone $base)
            ->withMin(['installments as next_due' => fn ($q) => $q->where('due_date', '>=', today()->toDateString())], 'due_date')
            ->orderBy('status')->latest('start_date')->latest('id')
            ->paginate(25)
            ->withQueryString();

        $totals = (clone $base)->selectRaw('SUM(CASE WHEN loan_type <> ? THEN principal_amount ELSE 0 END) AS borrowed,
            SUM(CASE WHEN loan_type = ? THEN principal_amount ELSE 0 END) AS od_limit', [LoanType::Overdraft->value, LoanType::Overdraft->value])->first();

        return $this->listView($request, 'loans.index', compact('loans', 'filters', 'totals'));
    }

    public function create(): View
    {
        return view('loans.form', ['loan' => new Loan([
            'loan_type' => LoanType::BusinessLoan, 'start_date' => today(), 'first_due_date' => today()->addMonthNoOverflow(),
            'processing_fee' => '0.00', 'interest_rate' => null,
        ]), 'locked' => false]);
    }

    public function store(LoanRequest $request): RedirectResponse
    {
        $loan = $this->loans->create($request->validated());

        return redirect()->route('loans.show', $loan)->with('success', __('Loan :no saved.', ['no' => $loan->loan_no]));
    }

    public function show(Loan $loan): View
    {
        return view('loans.show', [
            'loan' => $loan,
            'installments' => $loan->hasSchedule() ? $loan->installments()->get() : collect(),
            'feeExpenseId' => Expense::query()->where('source_type', $loan->getMorphClass())->where('source_id', $loan->id)->value('id'),
            'locked' => $this->loans->hasTransactions($loan),
        ]);
    }

    public function edit(Loan $loan): View|RedirectResponse
    {
        if ($loan->status === LoanStatus::Closed) {
            return redirect()->route('loans.show', $loan)->with('error', __('A closed loan cannot be edited. Re-open it first.'));
        }

        return view('loans.form', ['loan' => $loan, 'locked' => $this->loans->hasTransactions($loan)]);
    }

    public function update(LoanRequest $request, Loan $loan): RedirectResponse
    {
        try {
            $this->loans->update($loan, $request->validated());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('loans.show', $loan)->with('success', __('Loan updated.'));
    }

    public function destroy(DeleteLoanRequest $request, Loan $loan): RedirectResponse
    {
        try {
            $this->loans->delete($loan);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('loans.index')->with('success', __('Loan :no deleted.', ['no' => $loan->loan_no]));
    }
}
