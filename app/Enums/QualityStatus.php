<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Milk brought by a farmer: accepted (paid, goes to stock) or rejected (amount 0, no stock). */
enum QualityStatus: string
{
    use HasOptions;

    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Accepted => __('Accepted'),
            self::Rejected => __('Rejected'),
        };
    }
}
