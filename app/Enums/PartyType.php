<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** What an animal trading party does with us (spec Phase 6). */
enum PartyType: string
{
    use HasOptions;

    case Seller = 'seller';
    case Buyer = 'buyer';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Seller => __('Seller'),
            self::Buyer => __('Buyer'),
            self::Both => __('Buyer & seller'),
        };
    }
}
