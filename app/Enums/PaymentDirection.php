<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Money between us and an animal trading party (spec Phase 6). */
enum PaymentDirection: string
{
    use HasOptions;

    case Paid = 'paid';         // we paid the party (usually for a purchase)
    case Received = 'received'; // the party paid us (usually for a sale)

    public function label(): string
    {
        return match ($this) {
            self::Paid => __('Paid to party'),
            self::Received => __('Received from party'),
        };
    }
}
