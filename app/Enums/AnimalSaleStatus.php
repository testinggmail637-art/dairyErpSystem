<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** A sale stays on record when cancelled (spec Phase 6: cancel with reason reverts the animals to active). */
enum AnimalSaleStatus: string
{
    use HasOptions;

    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }
}
