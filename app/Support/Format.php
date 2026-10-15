<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Display formatting for India: ₹1,25,000.00 and DD-MM-YYYY.
 * Numbers are handled as strings with bcmath (round half up) — never as floats.
 */
final class Format
{
    public const DATE = 'd-m-Y';

    public const DATE_TIME = 'd-m-Y h:i A';

    /** ₹1,25,000.00 (or 1,25,000.00 without the symbol). Null/empty → ₹0.00. */
    public static function money(mixed $value, bool $symbol = true): string
    {
        $formatted = self::number($value, 2);

        if (! $symbol) {
            return $formatted;
        }

        return str_starts_with($formatted, '-') ? '-₹'.substr($formatted, 1) : '₹'.$formatted;
    }

    /** Quantity with 2 decimals and Indian grouping: 1,250.50 */
    public static function qty(mixed $value, int $decimals = 2): string
    {
        return self::number($value, $decimals);
    }

    /** Indian digit grouping (12,34,567.89), rounded half up to $decimals. */
    public static function number(mixed $value, int $decimals = 2): string
    {
        $rounded = self::round(self::toDecimalString($value), $decimals);

        $negative = str_starts_with($rounded, '-');
        $unsigned = ltrim($rounded, '-');

        [$int, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');

        $last3 = substr($int, -3);
        $rest = substr($int, 0, -3);
        $grouped = $rest === ''
            ? $last3
            : preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest).','.$last3;

        $result = $decimals > 0 ? $grouped.'.'.$fraction : $grouped;

        // Avoid "-0.00".
        return $negative && preg_match('/[1-9]/', $result) ? '-'.$result : $result;
    }

    /** DD-MM-YYYY, or '' for null/empty. Accepts Carbon/DateTime or a parseable string. */
    public static function date(mixed $value): string
    {
        return self::carbon($value)?->format(self::DATE) ?? '';
    }

    /** DD-MM-YYYY hh:mm AM/PM in the app timezone, or ''. */
    public static function dateTime(mixed $value): string
    {
        return self::carbon($value)?->timezone(config('app.timezone'))->format(self::DATE_TIME) ?? '';
    }

    private static function carbon(mixed $value): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        return Carbon::parse((string) $value);
    }

    /** Round half up (away from zero) to $decimals, returning a plain decimal string. */
    private static function round(string $value, int $decimals): string
    {
        $half = $decimals > 0 ? '0.'.str_repeat('0', $decimals).'5' : '0.5';

        return str_starts_with($value, '-')
            ? bcsub($value, $half, $decimals)
            : bcadd($value, $half, $decimals);
    }

    private static function toDecimalString(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        if (is_float($value)) {
            // Floats should not reach here; format without scientific notation if they do.
            $value = rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
        }

        $value = str_replace([',', ' ', '₹'], '', trim((string) $value));

        if (! preg_match('/^-?\d*(\.\d+)?$/', $value) || $value === '' || $value === '-') {
            throw new \InvalidArgumentException("Not a number: {$value}");
        }

        return $value;
    }
}
