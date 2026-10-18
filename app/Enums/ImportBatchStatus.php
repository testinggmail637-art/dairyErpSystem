<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** State of an Excel import batch (spec Section 6, Phase 1). */
enum ImportBatchStatus: string
{
    use HasOptions;

    case Previewed = 'previewed';
    case Imported = 'imported';
    case Cancelled = 'cancelled';
    case Reverted = 'reverted'; // imported, then undone (spec 7.2 step 5)

    public function label(): string
    {
        return match ($this) {
            self::Previewed => __('Checked, not imported yet'),
            self::Imported => __('Imported'),
            self::Cancelled => __('Cancelled'),
            self::Reverted => __('Reverted'),
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Previewed => 'text-bg-warning',
            self::Imported => 'text-bg-success',
            self::Cancelled => 'text-bg-secondary',
            self::Reverted => 'text-bg-dark',
        };
    }
}
