<?php

namespace App\Reports;

use Illuminate\Support\Str;

/**
 * A finished report as a simple table, rendered three ways from the same data: the screen
 * (reports/show), Excel (ReportTableExport) and PDF (pdf/report). Money cells are decimal strings.
 */
final class ReportTable
{
    public const TEXT = 'text';

    public const MONEY = 'money';

    public const NUMBER = 'number';

    public const PERCENT = 'percent';

    public const DATE = 'date';

    /**
     * @param  list<array{label: string, type: string}>  $columns
     * @param  list<list<mixed>>  $rows
     * @param  list<mixed>|null  $totals  same width as $columns; null = no totals row
     * @param  list<string>  $notes  small print under the table
     */
    public function __construct(
        public readonly string $title,
        public readonly string $period,
        public readonly array $columns,
        public readonly array $rows,
        public readonly ?array $totals = null,
        public readonly array $notes = [],
    ) {}

    public static function column(string $label, string $type = self::TEXT): array
    {
        return ['label' => $label, 'type' => $type];
    }

    public function isNumeric(int $index): bool
    {
        return in_array($this->columns[$index]['type'] ?? self::TEXT, [self::MONEY, self::NUMBER, self::PERCENT], true);
    }

    /** Cell as display text (₹1,25,000.00, 12.5 %, DD-MM-YYYY …). */
    public function display(int $index, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($this->columns[$index]['type'] ?? self::TEXT) {
            self::MONEY => inr($value),
            self::NUMBER => fmt_qty($value, 0),
            self::PERCENT => fmt_qty($value, 1).' %',
            self::DATE => self::isIsoDate($value) ? fmt_date($value) : (string) $value, // e.g. "Total" in the totals row
            default => (string) $value,
        };
    }

    public static function isIsoDate(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1;
    }

    public function fileName(): string
    {
        return Str::slug($this->title.' '.$this->period);
    }
}
