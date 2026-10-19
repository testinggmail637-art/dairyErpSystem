<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Whether the animal is still on the farm (spec Section 6, Phase 2). */
enum AnimalStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Sold = 'sold';
    case Deceased = 'deceased';
    case Culled = 'culled';
    case Transferred = 'transferred';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Sold => __('Sold'),
            self::Deceased => __('Deceased'),
            self::Culled => __('Culled'),
            self::Transferred => __('Transferred'),
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Active => 'text-bg-success',
            self::Sold => 'text-bg-primary',
            self::Deceased => 'text-bg-dark',
            self::Culled, self::Transferred => 'text-bg-secondary',
        };
    }
}
