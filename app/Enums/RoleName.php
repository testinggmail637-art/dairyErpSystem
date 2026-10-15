<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Support\Permissions;

/** Seeded roles (spec Section 5.2). The owner can change their permissions later from the Roles screen. */
enum RoleName: string
{
    use HasOptions;

    case Owner = 'owner';
    case Admin = 'admin';
    case Accountant = 'accountant';
    case HerdStaff = 'herd_staff';
    case CollectionOperator = 'collection_operator';
    case DeliveryBoy = 'delivery_boy';

    public function label(): string
    {
        return match ($this) {
            self::Owner => __('Owner'),
            self::Admin => __('Admin'),
            self::Accountant => __('Accountant'),
            self::HerdStaff => __('Herd Staff'),
            self::CollectionOperator => __('Collection Operator'),
            self::DeliveryBoy => __('Delivery Boy'),
        };
    }

    /**
     * Permissions given when the role is first created.
     *
     * @return list<string>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::Owner => Permissions::all(),
            self::Admin => array_values(array_diff(Permissions::all(), ['farms.manage', 'roles.manage', 'monitoring.view'])),
            self::Accountant => Permissions::expand([
                'dashboard.*', 'expenses.*', 'vendors.*', 'vendor_payments.*', 'customers.view', 'bills.*',
                'customer_payments.*', 'customer_adjustments.create', 'farmers.view', 'collections.view',
                'farmer_payments.*', 'farmer_advances.manage', 'animal_trading.view', 'staff.view', 'salary.manage',
                'reports.*',
            ]),
            self::HerdStaff => Permissions::expand([
                'dashboard.view', 'animals.view', 'milk_production.view', 'milk_production.create', 'milk_stock.view',
                'health.view', 'health.manage', 'breeding.manage', 'feed.view', 'feed.manage', 'expenses.create',
            ]),
            self::CollectionOperator => Permissions::expand([
                'dashboard.view', 'farmers.view', 'collections.view', 'collections.create', 'milk_stock.view',
            ]),
            self::DeliveryBoy => Permissions::expand([
                'deliveries.view', 'deliveries.update', 'customer_payments.create',
            ]),
        };
    }
}
