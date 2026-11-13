<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Result of a pregnancy check. */
enum PregnancyResult: string
{
    use HasOptions;

    case Positive = 'positive';
    case Negative = 'negative';

    public function label(): string
    {
        return match ($this) {
            self::Positive => __('Pregnant'),
            self::Negative => __('Not pregnant'),
        };
    }
}
