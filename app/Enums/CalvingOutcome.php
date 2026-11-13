<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** How a calving ended; only "alive" creates a calf animal. */
enum CalvingOutcome: string
{
    use HasOptions;

    case Alive = 'alive';
    case Stillborn = 'stillborn';
    case DiedAfterBirth = 'died_after_birth';

    public function label(): string
    {
        return match ($this) {
            self::Alive => __('Calf alive'),
            self::Stillborn => __('Stillborn'),
            self::DiedAfterBirth => __('Died after birth'),
        };
    }
}
