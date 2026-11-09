<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** How a (non-overdraft) loan is paid back (spec 7.4A). */
enum RepaymentMethod: string
{
    use HasOptions;

    case EmiReducing = 'emi_reducing';
    case EmiFlat = 'emi_flat';
    case InterestOnly = 'interest_only';
    case Bullet = 'bullet';

    public function label(): string
    {
        return match ($this) {
            self::EmiReducing => __('EMI — reducing balance (bank)'),
            self::EmiFlat => __('EMI — flat rate'),
            self::InterestOnly => __('Interest only, principal at the end'),
            self::Bullet => __('Bullet — everything at the end (gold loan)'),
        };
    }
}
