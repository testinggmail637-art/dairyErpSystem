<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Own herd (kept for milk/breeding) or resale (bought to sell), spec Section 6 Phase 2. */
enum OwnershipType: string
{
    use HasOptions;

    case OwnHerd = 'own_herd';
    case Resale = 'resale';

    public function label(): string
    {
        return match ($this) {
            self::OwnHerd => __('Own herd'),
            self::Resale => __('For resale'),
        };
    }
}
