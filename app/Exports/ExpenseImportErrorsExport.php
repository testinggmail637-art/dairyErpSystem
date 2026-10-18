<?php

namespace App\Exports;

use App\Imports\ExpenseImportColumns;
use App\Models\ExpenseImportBatch;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * "Download error rows" (spec 7.2 step 4): the invalid rows of a batch, as read, in the template's columns plus an
 * `errors` column. The importer ignores the extra column, so the user fixes the cells and uploads this file again.
 */
class ExpenseImportErrorsExport implements FromArray, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    public function __construct(private readonly ExpenseImportBatch $batch) {}

    /** @return list<list<string>> */
    public function array(): array
    {
        $headers = ExpenseImportColumns::COLUMNS;
        $sheet = [[...array_values($headers), 'errors']];

        $this->batch->rows()->where('is_valid', false) // ids follow row order (rows are inserted in order)
            ->select(['id', 'row_number', 'payload', 'errors'])
            ->lazyById(500)
            ->each(function ($row) use (&$sheet, $headers) {
                $messages = [];
                foreach ((array) $row->errors as $key => $list) {
                    foreach ($list as $message) {
                        $messages[] = ($headers[$key] ?? $key).': '.$message;
                    }
                }

                $sheet[] = [...array_map(fn ($key) => (string) ($row->payload[$key] ?? ''), array_keys($headers)), implode(' | ', $messages)];
            });

        return $sheet;
    }

    public function title(): string
    {
        return 'Expenses';
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return [ExpenseImportColumns::letter('date') => NumberFormat::FORMAT_TEXT];
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        return ['A' => 18, 'B' => 12, 'C' => 20, 'D' => 22, 'E' => 18, 'F' => 16, 'G' => 22, 'H' => 20, 'I' => 16, 'J' => 10, 'K' => 10, 'L' => 30, 'M' => 70];
    }

    /** @return array<int, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    /** @return array<class-string, callable> */
    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $sheet->freezePane('A2');
            $sheet->getStyle('M:M')->getAlignment()->setWrapText(true);
        }];
    }
}
