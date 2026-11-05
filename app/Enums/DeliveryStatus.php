<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Delivery row state (spec Section 6, deliveries.status). */
enum DeliveryStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Delivered = 'delivered';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Delivered => __('Delivered'),
            self::Skipped => __('Skipped'),
        };
    }
}
