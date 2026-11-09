<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Money movements of a loan (spec Phase 3A loan_transactions.type). */
enum LoanTxnType: string
{
    use HasOptions;

    case EmiPayment = 'emi_payment';
    case InterestPayment = 'interest_payment';
    case Prepayment = 'prepayment';
    case OdWithdrawal = 'od_withdrawal';
    case OdRepayment = 'od_repayment';
    case OdInterest = 'od_interest';
    case Charge = 'charge';
    case SubventionCredit = 'subvention_credit';

    public function label(): string
    {
        return match ($this) {
            self::EmiPayment => __('EMI payment'),
            self::InterestPayment => __('Interest payment'),
            self::Prepayment => __('Prepayment'),
            self::OdWithdrawal => __('Withdrawal'),
            self::OdRepayment => __('Repayment / bill payment'),
            self::OdInterest => __('Interest debited'),
            self::Charge => __('Bank charge / fee'),
            self::SubventionCredit => __('Interest subvention / refund'),
        };
    }

    /** Money we pay out to the lender — needs a payment mode (spec). */
    public function isPayment(): bool
    {
        return in_array($this, [self::EmiPayment, self::InterestPayment, self::Prepayment, self::OdRepayment], true);
    }
}
