<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** A farmer payment cycle: draft (bonus / deductions can change) or finalized (read-only, ready to pay). */
enum CycleStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Finalized = 'finalized';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Finalized => __('Finalized'),
        };
    }
}
