<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Enums\RepaymentMethod;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Support\Money;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Loans (spec Phase 3A, 7.4A): create with number + schedule + processing-fee expense; edit terms only while the loan
 * has no transactions (schedule made again), afterwards only lender details / notes / OD renewal date; delete only
 * without transactions.
 */
class LoanService
{
    /** Fields that may still change after the first transaction. */
    public const ALWAYS_EDITABLE = ['lender_name', 'lender_phone', 'account_no', 'od_renewal_date', 'notes'];

    public function __construct(
        private readonly LoanScheduleCalculator $calculator,
        private readonly DocumentNumberService $numbers,
        private readonly FinanceExpenseService $expenses,
    ) {}

    /** @param  array<string, mixed>  $data */
    public function create(array $data): Loan
    {
        return DB::transaction(function () use ($data) {
            $loan = Loan::create($this->terms($data) + $this->details($data, $data['loan_type']) + [
                'loan_no' => $this->numbers->next(DocumentType::Loan, Carbon::parse($data['start_date'])),
                'status' => LoanStatus::Active,
            ]);
            $this->buildSchedule($loan);
            $this->syncFee($loan);

            return $loan;
        });
    }

    /** @param  array<string, mixed>  $data */
    public function update(Loan $loan, array $data): Loan
    {
        if ($loan->status === LoanStatus::Closed) {
            throw new DomainException(__('A closed loan cannot be edited. Re-open it first.'));
        }

        return DB::transaction(function () use ($loan, $data) {
            if ($this->hasTransactions($loan)) {
                $loan->update(collect($this->details($data, $loan->loan_type->value))->only(self::ALWAYS_EDITABLE)->all());

                return $loan;
            }

            $loan->update($this->terms($data) + $this->details($data, $data['loan_type']));
            $this->buildSchedule($loan);
            $this->syncFee($loan);

            return $loan;
        });
    }

    public function delete(Loan $loan): void
    {
        if ($this->hasTransactions($loan)) {
            throw new DomainException(__('This loan has payments or other entries, so it cannot be deleted. Close it instead.'));
        }

        DB::transaction(function () use ($loan) {
            $this->expenses->sync($loan, null, $loan->start_date->toDateString(), FinanceExpenseService::CHARGES, null, $loan->lender_name, '');
            $loan->installments()->delete();
            $loan->delete();
        });
    }

    public function hasTransactions(Loan $loan): bool
    {
        return $loan->transactions()->exists();
    }

    /** (Re)build the whole schedule from the loan's terms; overdrafts have none. */
    private function buildSchedule(Loan $loan): void
    {
        $loan->installments()->delete();

        if ($loan->isOverdraft()) {
            $loan->update(['emi_amount' => null]);

            return;
        }

        $schedule = $this->calculator->schedule($loan->repayment_method, $loan->principal_amount, $loan->interest_rate,
            $loan->tenure_months, $loan->first_due_date);
        $now = now();

        LoanInstallment::insert(array_map(fn (array $row) => $row + [
            'farm_id' => $loan->farm_id, 'loan_id' => $loan->id, 'created_at' => $now, 'updated_at' => $now,
        ], $schedule['rows']));

        $loan->update(['emi_amount' => $schedule['emi']]);
    }

    private function syncFee(Loan $loan): void
    {
        $this->expenses->sync($loan, $loan->processing_fee, $loan->start_date->toDateString(), FinanceExpenseService::CHARGES, null,
            $loan->lender_name, __('Processing fee · :no', ['no' => $loan->loan_no]));
    }

    /**
     * Money terms; schedule fields are cleared for an overdraft and od_renewal_date for other loans.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function terms(array $data): array
    {
        $od = $data['loan_type'] === LoanType::Overdraft->value;

        return [
            'loan_type' => $data['loan_type'],
            'principal_amount' => Money::round2($data['principal_amount']),
            'interest_rate' => Money::round2($data['interest_rate']),
            'repayment_method' => $od ? null : RepaymentMethod::from($data['repayment_method']),
            'tenure_months' => $od ? null : (int) $data['tenure_months'],
            'start_date' => $data['start_date'],
            'first_due_date' => $od ? null : $data['first_due_date'],
            'processing_fee' => Money::round2($data['processing_fee'] ?? '0'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function details(array $data, string $loanType): array
    {
        return [
            'lender_name' => $data['lender_name'],
            'lender_phone' => $data['lender_phone'] ?? null,
            'account_no' => $data['account_no'] ?? null,
            'od_renewal_date' => $loanType === LoanType::Overdraft->value ? ($data['od_renewal_date'] ?? null) : null,
            'notes' => $data['notes'] ?? null,
        ];
    }
}
