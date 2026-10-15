<?php

namespace App\Enums\Concerns;

/**
 * Shared helpers for string-backed enums: a translated label and value → label options for dropdowns.
 * Each enum implements label(); everything else comes from here.
 */
trait HasOptions
{
    /** @return array<string, string> value => label, in declaration order */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
