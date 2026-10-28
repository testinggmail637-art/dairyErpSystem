<?php

namespace App\Contracts;

use App\Enums\MilkMovementType;
use App\Enums\MilkShift;
use App\Enums\MilkSource;
use App\Enums\MilkType;

/**
 * A record that moves milk in or out of stock (production entry, collection, delivery). It owns exactly one
 * milk_stock_movements row, kept in step by MilkStockService::syncForReference().
 */
interface MovesMilkStock
{
    /**
     * Where the record's milk goes, as of its current attributes.
     *
     * @return array{source: MilkSource, milk_type: MilkType, date: string, shift: ?MilkShift, type: MilkMovementType}
     */
    public function milkStockTarget(): array;

    /** Litres the record moves now ("0" = no movement). */
    public function milkStockQuantity(): string;
}
