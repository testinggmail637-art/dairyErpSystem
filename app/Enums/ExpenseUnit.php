<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Unit of an expense quantity (spec Section 6, Phase 1). */
enum ExpenseUnit: string
{
    use HasOptions;

    case Kg = 'kg';
    case Litre = 'litre';
    case Piece = 'piece';
    case Bag = 'bag';
    case Dose = 'dose';
    case Hour = 'hour';
    case Day = 'day';
    case Trip = 'trip';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Kg => __('kg'),
            self::Litre => __('litre'),
            self::Piece => __('piece'),
            self::Bag => __('bag'),
            self::Dose => __('dose'),
            self::Hour => __('hour'),
            self::Day => __('day'),
            self::Trip => __('trip'),
            self::Other => __('other'),
        };
    }
}
