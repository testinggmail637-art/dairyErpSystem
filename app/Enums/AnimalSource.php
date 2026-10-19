<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** How the animal came to the farm (spec Section 6, Phase 2). */
enum AnimalSource: string
{
    use HasOptions;

    case BornOnFarm = 'born_on_farm';
    case Purchased = 'purchased';
    case Opening = 'opening';

    public function label(): string
    {
        return match ($this) {
            self::BornOnFarm => __('Born on farm'),
            self::Purchased => __('Purchased'),
            self::Opening => __('Existing at go-live'),
        };
    }
}
