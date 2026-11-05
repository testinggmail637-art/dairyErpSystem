<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Kind of business buyer (spec Section 6, customers.b2b_category). */
enum B2bCategory: string
{
    use HasOptions;

    case Hotel = 'hotel';
    case Shop = 'shop';
    case SweetShop = 'sweet_shop';
    case DairyPlant = 'dairy_plant';
    case Distributor = 'distributor';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Hotel => __('Hotel'),
            self::Shop => __('Shop'),
            self::SweetShop => __('Sweet shop'),
            self::DairyPlant => __('Dairy plant'),
            self::Distributor => __('Distributor'),
            self::Other => __('Other'),
        };
    }
}
