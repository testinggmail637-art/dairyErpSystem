<?php

namespace App\Support;

/**
 * The single list of permissions (spec Section 5.1), grouped by module for the permission matrix.
 * Names are `module.action`. Add new permissions here, then run `php artisan db:seed --class=RolesAndPermissionsSeeder`.
 */
final class Permissions
{
    /** module label => permission names */
    public const GROUPS = [
        'Administration' => [
            'farms.manage', 'users.manage', 'roles.manage', 'settings.manage', 'appearance.manage',
            'audit_logs.view', 'monitoring.view',
        ],
        'Dashboard' => ['dashboard.view', 'dashboard.financial'],
        'Expenses' => ['expenses.view', 'expenses.create', 'expenses.edit', 'expenses.delete', 'expenses.import'],
        'Vendors' => ['vendors.view', 'vendors.manage', 'vendor_payments.create', 'vendor_payments.edit'],
        'Animals' => ['animals.view', 'animals.manage'],
        'Milk production' => ['milk_production.view', 'milk_production.create', 'milk_production.edit'],
        'Milk stock' => ['milk_stock.view', 'milk_stock.adjust'],
        'Customers' => ['customers.view', 'customers.manage', 'subscriptions.manage'],
        'Deliveries' => ['deliveries.view', 'deliveries.update', 'deliveries.view_all_routes'],
        'Bills' => ['bills.view', 'bills.generate', 'bills.finalize', 'bills.cancel'],
        'Customer payments' => ['customer_payments.create', 'customer_payments.edit', 'customer_adjustments.create'],
        'Farmers' => ['farmers.view', 'farmers.manage', 'rate_charts.manage'],
        'Collections' => ['collections.view', 'collections.create', 'collections.edit'],
        'Farmer payments' => ['farmer_payments.view', 'farmer_payments.manage', 'farmer_advances.manage'],
        'Health & breeding' => ['health.view', 'health.manage', 'breeding.manage'],
        'Feed' => ['feed.view', 'feed.manage'],
        'Animal trading' => ['animal_trading.view', 'animal_trading.manage'],
        'Staff' => ['staff.view', 'staff.manage', 'attendance.manage', 'salary.manage'],
        'Reports' => ['reports.view', 'reports.financial'],
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return array_merge(...array_values(self::GROUPS));
    }

    /** "vendor_payments.create" → "Vendor payments: create" (for the permission matrix). */
    public static function label(string $name): string
    {
        [$module, $action] = explode('.', $name, 2);

        return __(ucfirst(str_replace('_', ' ', $module)).': '.str_replace('_', ' ', $action));
    }

    /**
     * Expand patterns to permission names: "bills.*" → every bills.… permission; exact names pass through.
     *
     * @param  list<string>  $patterns
     * @return list<string>
     */
    public static function expand(array $patterns): array
    {
        $all = self::all();
        $names = [];

        foreach ($patterns as $pattern) {
            if (str_ends_with($pattern, '.*')) {
                $prefix = substr($pattern, 0, -1);
                $names = array_merge($names, array_filter($all, fn (string $name) => str_starts_with($name, $prefix)));

                continue;
            }

            if (! in_array($pattern, $all, true)) {
                throw new \InvalidArgumentException("Unknown permission: {$pattern}");
            }

            $names[] = $pattern;
        }

        return array_values(array_unique($names));
    }
}
