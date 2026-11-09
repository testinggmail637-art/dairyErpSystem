<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** How a rate chart prices a litre of milk (spec Phase 4). */
enum RateMethod: string
{
    use HasOptions;

    case Fixed = 'fixed';
    case FatRate = 'fat_rate';
    case FatSnfRate = 'fat_snf_rate';
    case Slab = 'slab';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => __('Fixed rate per litre'),
            self::FatRate => __('By fat (fat × rate)'),
            self::FatSnfRate => __('By fat + SNF'),
            self::Slab => __('Slab table (fat / SNF ranges)'),
        };
    }
}
