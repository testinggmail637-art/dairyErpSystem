<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\InstallmentFrequency;
use App\Enums\LoanFamily;
use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Enums\MoratoriumInterest;
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

    /** (Re)build the whole schedule from the loan's terms; revolving accounts and credit cards have none. */
    private function buildSchedule(Loan $loan): void
    {
        $loan->installments()->delete();

        if (! $loan->hasSchedule()) {
            $loan->update(['emi_amount' => null]);

            return;
        }

        $schedule = $this->calculator->schedule($loan->repayment_method, $loan->principal_amount, $loan->interest_rate,
            $loan->tenure_months, $loan->first_due_date, $loan->installment_frequency, $loan->moratorium_months,
            $loan->moratorium_interest, $loan->broken_period_interest);
        $now = now();

        LoanInstallment::insert(array_map(fn (array $row) => $row + [
            'farm_id' => $loan->farm_id, 'loan_id' => $loan->id, 'created_at' => $now, 'updated_at' => $now,
        ], $schedule['rows']));

        $loan->update(['emi_amount' => $schedule['emi']]);
    }

    private function syncFee(Loan $loan): void
    {
        $this->expenses->sync($loan, $loan->processing_fee, $loan->start_date->toDateString(), FinanceExpenseService::CHARGES, null,
            $loan->lender_name, __('Processing fee · :no', ['no' => $loan->loan_no]), $loan->id);
    }

    /**
     * Money terms by family: instalment loans get schedule fields (bullet: one instalment at start + tenure; broken-period
     * interest calculated when not typed); revolving accounts an interest debit cycle; cards statement / due days.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function terms(array $data): array
    {
        $family = LoanType::from($data['loan_type'])->family();
        $principal = Money::round2($data['principal_amount']);
        $rate = Money::round2($data['interest_rate']);
        $terms = [
            'loan_type' => $data['loan_type'],
            'principal_amount' => $principal,
            'interest_rate' => $rate,
            'start_date' => $data['start_date'],
            'processing_fee' => Money::round2($data['processing_fee'] ?? '0'),
            'repayment_method' => null, 'installment_frequency' => InstallmentFrequency::Monthly, 'tenure_months' => null,
            'moratorium_months' => 0, 'moratorium_interest' => null, 'first_due_date' => null, 'broken_period_interest' => '0.00',
            'interest_debit_cycle' => null, 'statement_day' => null, 'due_day' => null,
        ];

        if ($family === LoanFamily::Revolving) {
            return ['interest_debit_cycle' => $data['interest_debit_cycle'] ?? InstallmentFrequency::Monthly->value] + $terms;
        }

        if ($family === LoanFamily::CreditCard) {
            return ['statement_day' => (int) $data['statement_day'], 'due_day' => (int) $data['due_day']] + $terms;
        }

        $method = RepaymentMethod::from($data['repayment_method']);
        $months = (int) $data['tenure_months'];

        if ($method === RepaymentMethod::Bullet) {
            return ['repayment_method' => $method, 'tenure_months' => $months,
                'first_due_date' => Carbon::parse($data['start_date'])->addMonthsNoOverflow($months)->toDateString()] + $terms;
        }

        $frequency = InstallmentFrequency::from($data['installment_frequency'] ?? InstallmentFrequency::Monthly->value);
        $moratorium = (int) ($data['moratorium_months'] ?? 0);
        $moratoriumInterest = $moratorium > 0 ? MoratoriumInterest::from($data['moratorium_interest']) : null;
        $firstDue = Carbon::parse($data['first_due_date']);

        // The first instalment period starts one period before the first due date (and, when the moratorium interest
        // is capitalised, before the moratorium too); interest for any days before that is broken-period interest.
        $periodStart = $firstDue->copy()->subMonthsNoOverflow($frequency->months()
            + ($moratoriumInterest === MoratoriumInterest::Capitalised ? $moratorium : 0));
        $bpi = filled($data['broken_period_interest'] ?? null)
            ? Money::round2($data['broken_period_interest'])
            : $this->calculator->brokenPeriodInterest($principal, $rate, Carbon::parse($data['start_date']), $periodStart);

        return ['repayment_method' => $method, 'installment_frequency' => $frequency, 'tenure_months' => $months,
            'moratorium_months' => $moratorium, 'moratorium_interest' => $moratoriumInterest,
            'first_due_date' => $firstDue->toDateString(), 'broken_period_interest' => $bpi] + $terms;
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
            'od_renewal_date' => LoanType::from($loanType)->family() === LoanFamily::Revolving ? ($data['od_renewal_date'] ?? null) : null,
            'notes' => $data['notes'] ?? null,
        ];
    }
}
