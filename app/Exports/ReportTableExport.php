<?php

namespace App\Exports;

use App\Reports\ReportTable;
use App\Support\Format;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Any ReportTable → Excel: title and period on top, headings, rows, totals. Money columns are real numbers
 * formatted with two decimals (so they can be summed in Excel); dates as DD-MM-YYYY text.
 */
class ReportTableExport implements FromArray, ShouldAutoSize, WithColumnFormatting, WithTitle
{
    public function __construct(private readonly ReportTable $table) {}

    /** @return list<list<mixed>> */
    public function array(): array
    {
        $cells = fn (array $row) => array_map(
            fn ($value, $i) => $this->table->columns[$i]['type'] === ReportTable::DATE && ReportTable::isIsoDate($value) ? Format::date($value) : $value,
            $row,
            array_keys($row),
        );

        return [
            [$this->table->title],
            [$this->table->period],
            [],
            array_column($this->table->columns, 'label'),
            ...array_map($cells, $this->table->rows),
            ...($this->table->totals ? [$cells($this->table->totals)] : []),
            ...($this->table->notes ? [[], ...array_map(fn ($n) => [$n], $this->table->notes)] : []),
        ];
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        $formats = [];

        foreach ($this->table->columns as $i => $column) {
            if (in_array($column['type'], [ReportTable::MONEY, ReportTable::PERCENT], true)) {
                $formats[Coordinate::stringFromColumnIndex($i + 1)] = '#,##0.00';
            }
        }

        return $formats;
    }

    public function title(): string
    {
        return mb_substr($this->table->title, 0, 31);
    }
}
