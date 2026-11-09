<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** How often a loan instalment (or a revolving account's interest debit) falls due (spec Phase 3A). */
enum InstallmentFrequency: string
{
    use HasOptions;

    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case HalfYearly = 'half_yearly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => __('Monthly'),
            self::Quarterly => __('Quarterly (every 3 months)'),
            self::HalfYearly => __('Half-yearly (every 6 months)'),
            self::Yearly => __('Yearly'),
        };
    }

    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Quarterly => 3,
            self::HalfYearly => 6,
            self::Yearly => 12,
        };
    }
}
