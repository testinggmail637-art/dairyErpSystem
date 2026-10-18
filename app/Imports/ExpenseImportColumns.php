<?php

namespace App\Imports;

/**
 * Columns of the expense Excel template and import (spec 7.2), in order. The template writes these headers;
 * the importer (Task 1.8) reads rows by them. Header text is matched case-insensitively.
 */
final class ExpenseImportColumns
{
    /** key => header text shown in the template */
    public const COLUMNS = [
        'date' => 'date (DD-MM-YYYY)',
        'amount' => 'amount',
        'category' => 'category',
        'subcategory' => 'subcategory',
        'section' => 'section',
        'payment_mode' => 'payment_mode',
        'vendor' => 'vendor',
        'paid_to' => 'paid_to',
        'credit' => 'credit (yes/no)',
        'quantity' => 'quantity',
        'unit' => 'unit',
        'note' => 'note',
    ];

    /** Help text for each column (template header comments / Help on the import page). */
    public static function help(): array
    {
        return [
            'date' => __('Required. DD-MM-YYYY, e.g. 15-10-2026 (Excel dates also work). Not in the future.'),
            'amount' => __('Required. Rupees, up to 2 decimals, e.g. 1250.50.'),
            'category' => __('Required. A main category from the Lists sheet.'),
            'subcategory' => __('Optional. Must belong to the category (Lists sheet, column B).'),
            'section' => __('Required. From the Lists sheet.'),
            'payment_mode' => __('Required when credit = no. Leave empty for credit.'),
            'vendor' => __('Required when credit = yes. Must already exist (Lists sheet).'),
            'paid_to' => __('Optional free text when there is no vendor.'),
            'credit' => __('yes = not paid yet (adds to the vendor balance), no = paid now. Empty = no.'),
            'quantity' => __('Optional number, e.g. 5.'),
            'unit' => __('Required with a quantity: kg, litre, piece, bag, dose, hour, day, trip, other.'),
            'note' => __('Optional.'),
        ];
    }

    /** Excel column letter of a key (A = date …). */
    public static function letter(string $key): string
    {
        return chr(ord('A') + array_search($key, array_keys(self::COLUMNS), true));
    }
}
