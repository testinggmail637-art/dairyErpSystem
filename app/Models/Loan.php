<?php

namespace App\Models;

use App\Enums\InstallmentFrequency;
use App\Enums\LoanFamily;
use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Enums\MoratoriumInterest;
use App\Enums\RepaymentMethod;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Database\Factories\LoanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Money the business has borrowed (spec Phase 3A): a loan with an EMI / interest schedule, or an overdraft
 * (principal_amount = sanctioned limit, no schedule).
 */
class Loan extends Model
{
    /** @use HasFactory<LoanFactory> */
    use Auditable, BelongsToFarm, HasFactory, SoftDeletes, TracksUser;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'loan_no',
        'loan_type',
        'lender_name',
        'lender_phone',
        'account_no',
        'principal_amount',
        'interest_rate',
        'repayment_method',
        'installment_frequency',
        'tenure_months',
        'moratorium_months',
        'moratorium_interest',
        'start_date',
        'first_due_date',
        'broken_period_interest',
        'emi_amount',
        'processing_fee',
        'od_renewal_date',
        'interest_debit_cycle',
        'statement_day',
        'due_day',
        'status',
        'closed_on',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'loan_type' => LoanType::class,
            'repayment_method' => RepaymentMethod::class,
            'installment_frequency' => InstallmentFrequency::class,
            'moratorium_interest' => MoratoriumInterest::class,
            'interest_debit_cycle' => InstallmentFrequency::class,
            'status' => LoanStatus::class,
            'principal_amount' => 'decimal:2',
            'interest_rate' => 'decimal:2',
            'emi_amount' => 'decimal:2',
            'broken_period_interest' => 'decimal:2',
            'moratorium_months' => 'integer',
            'statement_day' => 'integer',
            'due_day' => 'integer',
            'processing_fee' => 'decimal:2',
            'tenure_months' => 'integer',
            'start_date' => 'date',
            'first_due_date' => 'date',
            'od_renewal_date' => 'date',
            'closed_on' => 'date',
        ];
    }

    /**
     * @return HasMany<LoanInstallment, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class)->orderBy('installment_no');
    }

    /**
     * @return HasMany<LoanTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(LoanTransaction::class);
    }

    /**
     * @return HasMany<LoanRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(LoanRevision::class);
    }

    public function family(): LoanFamily
    {
        return $this->loan_type->family();
    }

    /** Instalment loans have a schedule; revolving accounts and credit cards do not. */
    public function hasSchedule(): bool
    {
        return $this->family() === LoanFamily::Instalment;
    }

    public function isRevolving(): bool
    {
        return $this->family() === LoanFamily::Revolving;
    }

    public function isCard(): bool
    {
        return $this->family() === LoanFamily::CreditCard;
    }
}
