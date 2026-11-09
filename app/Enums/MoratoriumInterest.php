<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** What happens to interest during a loan moratorium (spec 7.4A). */
enum MoratoriumInterest: string
{
    use HasOptions;

    case Paid = 'paid';
    case Capitalised = 'capitalised';

    public function label(): string
    {
        return match ($this) {
            self::Paid => __('Interest paid every period (only principal waits)'),
            self::Capitalised => __('Interest added to the loan'),
        };
    }
}
