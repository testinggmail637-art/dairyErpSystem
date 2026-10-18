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

    public function label(): string
    {
        return match ($this) {
            self::Previewed => __('Checked, not imported yet'),
            self::Imported => __('Imported'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Previewed => 'text-bg-warning',
            self::Imported => 'text-bg-success',
            self::Cancelled => 'text-bg-secondary',
        };
    }
}
