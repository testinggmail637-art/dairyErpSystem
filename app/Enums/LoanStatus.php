<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum LoanStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Closed => __('Closed'),
        };
    }
}
