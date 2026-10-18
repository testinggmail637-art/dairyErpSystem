<?php

namespace App\Exports\ExpenseTemplate;

use App\Imports\ExpenseImportColumns;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet 1 "Expenses": the header row only (spec 7.2), a note on each header, the date column as text
 * DD-MM-YYYY, and dropdowns (from the Lists sheet) on category, section, payment mode, vendor, credit and unit.
 */
class ExpenseTemplateSheet implements FromArray, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    public const TITLE = 'Expenses';

    /** template column key => list name on the Lists sheet */
    private const DROPDOWNS = [
        'category' => 'Categories',
        'section' => 'Sections',
        'payment_mode' => 'Payment modes',
        'vendor' => 'Vendors',
        'credit' => 'Credit',
        'unit' => 'Units',
    ];

    /** @param  array<string, list<string>>  $lists */
    public function __construct(private readonly array $lists) {}

    /** @return list<list<string>> */
    public function array(): array
    {
        return [array_values(ExpenseImportColumns::COLUMNS)];
    }

    public function title(): string
    {
        return self::TITLE;
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        return ['A' => 18, 'B' => 12, 'C' => 20, 'D' => 22, 'E' => 18, 'F' => 16, 'G' => 22, 'H' => 20, 'I' => 16, 'J' => 10, 'K' => 10, 'L' => 30];
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        // Text, so Excel keeps "15-10-2026" as typed (the importer also accepts real Excel dates).
        return [ExpenseImportColumns::letter('date') => NumberFormat::FORMAT_TEXT];
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

            foreach (ExpenseImportColumns::help() as $key => $text) {
                $sheet->getComment(ExpenseImportColumns::letter($key).'1')->getText()->createText($text);
            }

            foreach (self::DROPDOWNS as $key => $list) {
                $range = ExpenseListsSheet::range($this->lists, $list);

                if ($range === null) {
                    continue;
                }

                $letter = ExpenseImportColumns::letter($key);
                $validation = $sheet->getCell($letter.'2')->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST)
                    ->setErrorStyle(DataValidation::STYLE_STOP)
                    ->setAllowBlank(true)
                    ->setShowDropDown(true)
                    ->setShowErrorMessage(true)
                    ->setErrorTitle(__('Not in the list'))
                    ->setError(__('Choose a value from the list (see the Lists sheet).'))
                    ->setFormula1($range);
                $validation->setSqref($letter.'2:'.$letter.ExpenseTemplateExport::ROWS + 1);
            }
        }];
    }
}
