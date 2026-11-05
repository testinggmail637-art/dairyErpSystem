<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** B2C = home delivery, B2B = bulk buyers (spec Section 1). */
enum CustomerType: string
{
    use HasOptions;

    case B2c = 'b2c';
    case B2b = 'b2b';

    public function label(): string
    {
        return match ($this) {
            self::B2c => __('Home (B2C)'),
            self::B2b => __('Business (B2B)'),
        };
    }
}
