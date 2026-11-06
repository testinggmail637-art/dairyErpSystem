<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Kind of money the business has borrowed (spec Phase 3A). Overdraft has a limit instead of a schedule. */
enum LoanType: string
{
    use HasOptions;

    case PersonalLoan = 'personal_loan';
    case BusinessLoan = 'business_loan';
    case VehicleLoan = 'vehicle_loan';
    case GoldLoan = 'gold_loan';
    case Overdraft = 'overdraft';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PersonalLoan => __('Personal loan'),
            self::BusinessLoan => __('Business loan'),
            self::VehicleLoan => __('Vehicle loan'),
            self::GoldLoan => __('Gold loan'),
            self::Overdraft => __('Overdraft (OD)'),
            self::Other => __('Other loan'),
        };
    }

    public function isOverdraft(): bool
    {
        return $this === self::Overdraft;
    }
}
