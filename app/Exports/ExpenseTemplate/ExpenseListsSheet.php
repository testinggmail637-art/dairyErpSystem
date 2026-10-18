<?php

namespace App\Exports\ExpenseTemplate;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Sheet 2 "Lists": one valid-values column per list, header in row 1. */
class ExpenseListsSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public const TITLE = 'Lists';

    /** @param  array<string, list<string>>  $lists */
    public function __construct(private readonly array $lists) {}

    /** @return list<list<string|null>> */
    public function array(): array
    {
        $columns = array_values($this->lists);
        $height = max(array_map('count', $columns) ?: [0]);
        $rows = [array_keys($this->lists)];

        for ($i = 0; $i < $height; $i++) {
            $rows[] = array_map(fn (array $column) => $column[$i] ?? null, $columns);
        }

        return $rows;
    }

    public function title(): string
    {
        return self::TITLE;
    }

    /** @return array<int, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    /** "Lists!$A$2:$A$10" range of a list, for dropdown validation on the Expenses sheet (null when empty). */
    public static function range(array $lists, string $name): ?string
    {
        $index = array_search($name, array_keys($lists), true);
        $count = count($lists[$name] ?? []);

        if ($index === false || $count === 0) {
            return null;
        }

        $letter = chr(ord('A') + $index);

        return sprintf("'%s'!\$%s\$2:\$%s\$%d", self::TITLE, $letter, $letter, $count + 1);
    }
}
