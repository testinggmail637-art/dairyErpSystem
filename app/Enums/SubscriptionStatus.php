<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** A daily delivery plan is running or stopped (spec Section 6, subscriptions.status). */
enum SubscriptionStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Stopped = 'stopped';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Stopped => __('Stopped'),
        };
    }
}
