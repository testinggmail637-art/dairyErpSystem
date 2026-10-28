<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Why milk entered or left a stock (spec Section 6, milk_stock_movements). */
enum MilkMovementType: string
{
    use HasOptions;

    case Production = 'production';
    case Collection = 'collection';
    case Delivery = 'delivery';
    case Wastage = 'wastage';
    case HomeUse = 'home_use';
    case CalfFeeding = 'calf_feeding';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';

    public function label(): string
    {
        return match ($this) {
            self::Production => __('Production'),
            self::Collection => __('Collection'),
            self::Delivery => __('Delivery'),
            self::Wastage => __('Wastage'),
            self::HomeUse => __('Home use'),
            self::CalfFeeding => __('Calf feeding'),
            self::AdjustmentIn => __('Adjustment in'),
            self::AdjustmentOut => __('Adjustment out'),
        };
    }

    /** Milk comes in (quantity_in) for these types, goes out (quantity_out) for the rest. */
    public function isIn(): bool
    {
        return in_array($this, [self::Production, self::Collection, self::AdjustmentIn], true);
    }

    /** Manual movements must say why (spec: reason required for wastage / home use / calf feeding / adjustment). */
    public function requiresReason(): bool
    {
        return in_array($this, [self::Wastage, self::HomeUse, self::CalfFeeding, self::AdjustmentIn, self::AdjustmentOut], true);
    }

    /** The types the "Record usage" form offers (spec 7.4). */
    public static function usageTypes(): array
    {
        return [self::Wastage, self::HomeUse, self::CalfFeeding];
    }
}
