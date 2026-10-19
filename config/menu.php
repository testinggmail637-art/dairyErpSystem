<?php

/*
|--------------------------------------------------------------------------
| Sidebar menu (spec Section 9)
|--------------------------------------------------------------------------
| An item is shown only when its route exists AND the user has the permission
| (string, or array = any of them). Modules therefore appear automatically as
| their routes are built. `active` is the route-name pattern that highlights it.
| Hiding a menu is not security: every route still checks permissions itself.
*/

return [
    [
        'label' => 'Dashboard',
        'icon' => 'bi-speedometer2',
        'route' => 'dashboard',
        'active' => 'dashboard',
        'permission' => 'dashboard.view',
    ],

    [
        'label' => 'Herd',
        'icon' => 'bi-clipboard2-pulse',
        'items' => [
            ['label' => 'Animals', 'route' => 'animals.index', 'active' => 'animals.*', 'permission' => 'animals.view'],
            ['label' => 'Breeds', 'route' => 'animal-breeds.index', 'active' => 'animal-breeds.*', 'permission' => 'animals.view'],
            ['label' => 'Milk Entry', 'route' => 'milk-production.index', 'active' => 'milk-production.*', 'permission' => 'milk_production.view'],
            ['label' => 'Health', 'route' => 'health.index', 'active' => 'health.*', 'permission' => 'health.view'],
            ['label' => 'Breeding', 'route' => 'breeding.index', 'active' => 'breeding.*', 'permission' => 'breeding.manage'],
            ['label' => 'Feed', 'route' => 'feed.index', 'active' => 'feed.*', 'permission' => 'feed.view'],
        ],
    ],

    [
        'label' => 'Collection',
        'icon' => 'bi-droplet',
        'items' => [
            ['label' => 'Farmers', 'route' => 'farmers.index', 'active' => 'farmers.*', 'permission' => 'farmers.view'],
            ['label' => 'Collection Entry', 'route' => 'collections.index', 'active' => 'collections.*', 'permission' => 'collections.view'],
            ['label' => 'Rate Charts', 'route' => 'rate-charts.index', 'active' => 'rate-charts.*', 'permission' => 'rate_charts.manage'],
            ['label' => 'Farmer Payments', 'route' => 'farmer-payments.index', 'active' => 'farmer-payments.*', 'permission' => 'farmer_payments.view'],
        ],
    ],

    [
        'label' => 'Sales',
        'icon' => 'bi-truck',
        'items' => [
            ['label' => 'Customers', 'route' => 'customers.index', 'active' => 'customers.*', 'permission' => 'customers.view'],
            ['label' => 'Subscriptions', 'route' => 'subscriptions.index', 'active' => 'subscriptions.*', 'permission' => 'subscriptions.manage'],
            ['label' => 'Deliveries', 'route' => 'deliveries.index', 'active' => 'deliveries.*', 'permission' => 'deliveries.view'],
            ['label' => 'Bills', 'route' => 'bills.index', 'active' => 'bills.*', 'permission' => 'bills.view'],
            ['label' => 'Payments', 'route' => 'customer-payments.index', 'active' => 'customer-payments.*', 'permission' => 'customer_payments.create'],
        ],
    ],

    [
        'label' => 'Milk Stock',
        'icon' => 'bi-box-seam',
        'route' => 'milk-stock.index',
        'active' => 'milk-stock.*',
        'permission' => 'milk_stock.view',
    ],

    [
        'label' => 'Expenses',
        'icon' => 'bi-wallet2',
        'items' => [
            ['label' => 'Expenses', 'route' => 'expenses.index', 'active' => 'expenses.*', 'permission' => ['expenses.view', 'expenses.create']],
            ['label' => 'Vendors', 'route' => 'vendors.index', 'active' => 'vendors.*', 'permission' => 'vendors.view'],
            ['label' => 'Categories', 'route' => 'expense-categories.index', 'active' => 'expense-categories.*', 'permission' => 'expenses.view'],
            ['label' => 'Import', 'route' => 'expense-imports.index', 'active' => 'expense-imports.*', 'permission' => 'expenses.import'],
        ],
    ],

    [
        'label' => 'Trading',
        'icon' => 'bi-arrow-left-right',
        'items' => [
            ['label' => 'Purchases', 'route' => 'animal-purchases.index', 'active' => 'animal-purchases.*', 'permission' => 'animal_trading.view'],
            ['label' => 'Sales', 'route' => 'animal-sales.index', 'active' => 'animal-sales.*', 'permission' => 'animal_trading.view'],
            ['label' => 'Parties', 'route' => 'parties.index', 'active' => 'parties.*', 'permission' => 'animal_trading.view'],
        ],
    ],

    [
        'label' => 'Staff',
        'icon' => 'bi-people',
        'route' => 'staff.index',
        'active' => 'staff.*',
        'permission' => 'staff.view',
    ],

    [
        'label' => 'Reports',
        'icon' => 'bi-bar-chart-line',
        'route' => 'reports.index',
        'active' => 'reports.*',
        'permission' => 'reports.view',
    ],

    [
        'label' => 'Settings',
        'icon' => 'bi-gear',
        'items' => [
            ['label' => 'Farm', 'route' => 'settings.farm.edit', 'active' => 'settings.farm.*', 'permission' => 'settings.manage'],
            ['label' => 'Users', 'route' => 'users.index', 'active' => 'users.*', 'permission' => 'users.manage'],
            ['label' => 'Roles', 'route' => 'roles.index', 'active' => 'roles.*', 'permission' => 'roles.manage'],
            ['label' => 'Appearance', 'route' => 'settings.appearance.edit', 'active' => 'settings.appearance.*', 'permission' => 'appearance.manage'],
            ['label' => 'Sections', 'route' => 'sections.index', 'active' => 'sections.*', 'permission' => 'settings.manage'],
            ['label' => 'Payment Modes', 'route' => 'payment-modes.index', 'active' => 'payment-modes.*', 'permission' => 'settings.manage'],
            ['label' => 'Audit Log', 'route' => 'audit-logs.index', 'active' => 'audit-logs.*', 'permission' => 'audit_logs.view'],
            ['label' => 'System Monitor', 'route' => 'pulse', 'active' => 'pulse', 'permission' => 'monitoring.view'],
        ],
    ],
];
