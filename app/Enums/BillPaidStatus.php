<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Computed from payment allocations, never stored (spec Section 6). */
enum BillPaidStatus: string
{
    use HasOptions;

    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => __('Unpaid'),
            self::PartiallyPaid => __('Part paid'),
            self::Paid => __('Paid'),
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Unpaid => 'text-bg-danger',
            self::PartiallyPaid => 'text-bg-warning',
            self::Paid => 'text-bg-success',
        };
    }
}
