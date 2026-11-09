<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** One farmer's statement in a cycle: pending until the money is paid. */
enum FarmerPaymentStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Paid => __('Paid'),
        };
    }
}
