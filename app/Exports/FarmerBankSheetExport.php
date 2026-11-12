<?php

namespace App\Exports;

use App\Reports\ReportTable;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/** Bank sheet Excel: text columns stay text, so long account numbers are not turned into 1.23E+11 or lose leading zeros. */
class FarmerBankSheetExport extends ReportTableExport implements WithCustomValueBinder
{
    private readonly DefaultValueBinder $default;

    public function __construct(private readonly ReportTable $sheet)
    {
        parent::__construct($sheet);
        $this->default = new DefaultValueBinder;
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        $type = $this->sheet->columns[Coordinate::columnIndexFromString($cell->getColumn()) - 1]['type'] ?? ReportTable::TEXT;

        if (is_string($value) && $type === ReportTable::TEXT) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return $this->default->bindValue($cell, $value);
    }
}
