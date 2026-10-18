<?php

namespace App\Imports;

use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Throwable;

/**
 * Reads an uploaded expense sheet (xlsx / xls / csv, spec 7.2) into plain string rows keyed by
 * {@see ExpenseImportColumns} keys. Uses the "Expenses" sheet when present, otherwise the first sheet.
 * Headers are matched case-insensitively, ignoring "(…)" hints, so "Date", "date (DD-MM-YYYY)" and "DATE" all work.
 * Numeric date cells (Excel serial dates) are turned into DD-MM-YYYY text; numbers never pass through as floats.
 */
final class ExpenseImportReader
{
    public const MAX_ROWS = 5000;

    /** Columns the file must have; the others may be left out. */
    public const REQUIRED_COLUMNS = ['date', 'amount', 'category', 'section'];

    /**
     * @return array<int, array<string, string>> spreadsheet row number => [column key => trimmed text]
     *
     * @throws ValidationException
     */
    public function read(string $path, string $field = 'file'): array
    {
        try {
            $cells = strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'csv' ? $this->csvCells($path) : $this->sheetCells($path);
        } catch (Throwable) {
            throw ValidationException::withMessages([$field => __('This file could not be read. Upload the filled template as xlsx, xls or csv.')]);
        }

        $map = $this->mapHeaders(array_shift($cells) ?? [], $field);
        $rows = [];

        foreach ($cells as $i => $values) {
            $row = [];
            foreach ($map as $index => $key) {
                $row[$key] = $this->text($values[$index] ?? null, $key);
            }

            if (implode('', $row) === '') {
                continue; // blank line (the template has dropdowns on empty rows)
            }

            if (count($rows) === self::MAX_ROWS) {
                throw ValidationException::withMessages([$field => __('The file has more than :max rows. Split it into smaller files.', ['max' => number_format(self::MAX_ROWS)])]);
            }

            $rows[$i + 2] = $row + array_fill_keys(array_keys(ExpenseImportColumns::COLUMNS), '');
        }

        if ($rows === []) {
            throw ValidationException::withMessages([$field => __('The file has no expense rows below the header.')]);
        }

        return $rows;
    }

    /**
     * xlsx / xls through PhpSpreadsheet (values only; dates stay Excel serial numbers).
     *
     * @return list<array<int, mixed>>
     */
    private function sheetCells(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);

        $book = $reader->load($path);
        $sheet = $book->getSheetByName('Expenses') ?? $book->getSheet(0);
        $cells = $sheet->rangeToArray('A1:'.$sheet->getHighestDataColumn().$sheet->getHighestDataRow(), null, true, false, false);
        $book->disconnectWorksheets();

        return $cells;
    }

    /**
     * CSV with plain fgetcsv — about 10× faster than PhpSpreadsheet for 5,000 rows, and every value stays text.
     * Accepts a UTF-8 BOM, Windows-1252 files (Excel "CSV" on Windows) and ; as separator.
     *
     * @return list<array<int, string>>
     */
    private function csvCells(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content;
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $firstLine = strtok($content, "\r\n") ?: '';
        $separator = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $cells = [];
        $limit = self::MAX_ROWS + 1000; // enough to find the header, blank lines and one row too many
        while (($line = fgetcsv($stream, null, $separator, '"', '')) !== false && count($cells) <= $limit) {
            $cells[] = $line === [null] ? [] : $line;
        }
        fclose($stream);

        return $cells;
    }

    /**
     * @param  array<int, mixed>  $headers
     * @return array<int, string> column index => column key
     */
    private function mapHeaders(array $headers, string $field): array
    {
        $known = [];
        foreach (ExpenseImportColumns::COLUMNS as $key => $header) {
            $known[self::normalizeHeader($header)] = $key;
            $known[self::normalizeHeader($key)] = $key;
        }

        $map = [];
        foreach ($headers as $index => $header) {
            $key = $known[self::normalizeHeader((string) $header)] ?? null;
            if ($key !== null && ! in_array($key, $map, true)) {
                $map[$index] = $key;
            }
        }

        $missing = array_diff(self::REQUIRED_COLUMNS, $map);
        if ($missing !== []) {
            throw ValidationException::withMessages([$field => __('The header row is missing: :columns. Use the downloaded template.', [
                'columns' => implode(', ', array_map(fn ($k) => ExpenseImportColumns::COLUMNS[$k], $missing)),
            ])]);
        }

        return $map;
    }

    /** "Date (DD-MM-YYYY)" → "date", "Payment Mode" → "payment_mode". */
    public static function normalizeHeader(string $header): string
    {
        $header = mb_strtolower(trim(preg_replace('/\(.*?\)/u', '', $header) ?? ''));

        return preg_replace('/[\s_-]+/u', '_', $header) ?? '';
    }

    private function text(mixed $value, string $key): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if (is_int($value) || is_float($value)) {
            if ($key === 'date') {
                return $this->serialDate($value);
            }

            // 1250.5 → "1250.5", never "1250.4999999" — 10 decimals is far below the 2 that are allowed
            $text = number_format((float) $value, 10, '.', '');

            return str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text;
        }

        return trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
    }

    /** Excel serial date (45945 → 15-10-2025); out-of-range numbers are kept as typed so validation reports them. */
    private function serialDate(int|float $serial): string
    {
        if ($serial < 1 || $serial > 2958465) {
            return (string) $serial;
        }

        return Date::excelToDateTimeObject((int) floor($serial))->format('d-m-Y');
    }
}
