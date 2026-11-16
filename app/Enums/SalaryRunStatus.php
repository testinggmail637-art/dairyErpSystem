<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** A salary run is edited as a draft, then finalized (locked, expenses created) — spec Phase 6. */
enum SalaryRunStatus: string
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
