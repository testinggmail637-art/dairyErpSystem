<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Customer bill state (spec Section 6, bills.status). */
enum BillStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Finalized = 'finalized';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Finalized => __('Finalized'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'text-bg-warning',
            self::Finalized => 'text-bg-success',
            self::Cancelled => 'text-bg-secondary',
        };
    }
}
