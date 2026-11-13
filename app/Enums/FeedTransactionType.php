<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Feed stock movement. Stock = opening + purchase + adjustment in − consumption − adjustment out. */
enum FeedTransactionType: string
{
    use HasOptions;

    case Opening = 'opening';
    case Purchase = 'purchase';
    case Consumption = 'consumption';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';

    public function label(): string
    {
        return match ($this) {
            self::Opening => __('Opening stock'),
            self::Purchase => __('Purchase'),
            self::Consumption => __('Consumption'),
            self::AdjustmentIn => __('Adjustment in'),
            self::AdjustmentOut => __('Adjustment out'),
        };
    }

    public function isIn(): bool
    {
        return in_array($this, [self::Opening, self::Purchase, self::AdjustmentIn], true);
    }

    /** @return list<string> */
    public static function inValues(): array
    {
        return [self::Opening->value, self::Purchase->value, self::AdjustmentIn->value];
    }
}
