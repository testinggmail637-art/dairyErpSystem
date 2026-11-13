<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Unit a feed item is counted in (spec Phase 5); same values as ExpenseUnit, so a purchase copies it onto the expense. */
enum FeedUnit: string
{
    use HasOptions;

    case Kg = 'kg';
    case Bag = 'bag';
    case Litre = 'litre';
    case Piece = 'piece';

    public function label(): string
    {
        return match ($this) {
            self::Kg => __('kg'),
            self::Bag => __('bag'),
            self::Litre => __('litre'),
            self::Piece => __('piece'),
        };
    }
}
