<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Milking shift (spec Section 6, Phase 2). */
enum MilkShift: string
{
    use HasOptions;

    case Morning = 'morning';
    case Evening = 'evening';

    public function label(): string
    {
        return match ($this) {
            self::Morning => __('Morning'),
            self::Evening => __('Evening'),
        };
    }

    /** The shift being milked now (IST): morning until 2 pm, evening after. */
    public static function current(): self
    {
        return now()->hour < 14 ? self::Morning : self::Evening;
    }
}
