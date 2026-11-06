<?php

namespace Database\Factories;

use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Enums\RepaymentMethod;
use App\Models\Farm;
use App\Models\Loan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    protected $model = Loan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'loan_no' => fake()->unique()->numerify('LOAN/2026-27/####'),
            'loan_type' => LoanType::BusinessLoan,
            'lender_name' => 'State Bank of India',
            'principal_amount' => '100000.00',
            'interest_rate' => '12.00',
            'repayment_method' => RepaymentMethod::EmiReducing,
            'tenure_months' => 12,
            'start_date' => now()->subMonth()->toDateString(),
            'first_due_date' => now()->toDateString(),
            'emi_amount' => '8885.00',
            'processing_fee' => '0.00',
            'status' => LoanStatus::Active,
        ];
    }

    public function overdraft(string $limit = '200000.00'): static
    {
        return $this->state(fn () => [
            'loan_type' => LoanType::Overdraft, 'principal_amount' => $limit, 'repayment_method' => null,
            'tenure_months' => null, 'first_due_date' => null, 'emi_amount' => null,
        ]);
    }
}
