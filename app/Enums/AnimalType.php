<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Cow or buffalo — of an animal and of a breed (spec Section 6, Phase 2). */
enum AnimalType: string
{
    use HasOptions;

    case Cow = 'cow';
    case Buffalo = 'buffalo';

    public function label(): string
    {
        return match ($this) {
            self::Cow => __('Cow'),
            self::Buffalo => __('Buffalo'),
        };
    }
}
