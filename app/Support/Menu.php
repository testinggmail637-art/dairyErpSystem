<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Route;

/**
 * Builds the sidebar from config/menu.php for one user: drops items whose route does not exist yet
 * or that the user may not see, drops empty groups, and marks the active item/group.
 */
final class Menu
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function for(?Authenticatable $user): array
    {
        if ($user === null) {
            return [];
        }

        $menu = [];

        foreach (config('menu', []) as $entry) {
            if (isset($entry['items'])) {
                $items = array_values(array_filter(
                    array_map(fn (array $item) => self::resolve($item, $user), $entry['items']),
                ));

                if ($items === []) {
                    continue;
                }

                $menu[] = [
                    'label' => __($entry['label']),
                    'icon' => $entry['icon'] ?? 'bi-circle',
                    'items' => $items,
                    'active' => in_array(true, array_column($items, 'active'), true),
                ];

                continue;
            }

            if ($item = self::resolve($entry, $user)) {
                $menu[] = $item;
            }
        }

        return $menu;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private static function resolve(array $item, Authenticatable $user): ?array
    {
        if (! Route::has($item['route'])) {
            return null;
        }

        $permissions = (array) ($item['permission'] ?? []);

        if ($permissions !== [] && ! collect($permissions)->contains(fn (string $p) => $user->can($p))) {
            return null;
        }

        return [
            'label' => __($item['label']),
            'icon' => $item['icon'] ?? null,
            'url' => route($item['route']),
            'active' => request()->routeIs($item['active'] ?? $item['route']),
        ];
    }
}
