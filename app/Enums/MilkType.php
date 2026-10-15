<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Milk type — stock is kept separately per type (spec Section 2). */
enum MilkType: string
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
