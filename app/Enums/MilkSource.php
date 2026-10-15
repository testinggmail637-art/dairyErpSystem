<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Milk stock source — own herd vs. collection center; never mixed except by manual adjustment (spec Section 2). */
enum MilkSource: string
{
    use HasOptions;

    case OwnFarm = 'own_farm';
    case Collection = 'collection';

    public function label(): string
    {
        return match ($this) {
            self::OwnFarm => __('Own Farm'),
            self::Collection => __('Collection Center'),
        };
    }
}
