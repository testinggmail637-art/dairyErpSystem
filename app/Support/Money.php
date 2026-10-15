<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Decimal math for money, quantities, fat/SNF and rates — strings + bcmath, never floats.
 *
 * Calculations run at scale 4 and every result is rounded half up (away from zero) to 2 decimals,
 * so `Money::mul('25.50', '40.25')` → "1026.38". Use `round()` when a different precision is needed.
 */
final class Money
{
    public const SCALE = 4;

    /** a + b + c … */
    public static function add(mixed ...$values): string
    {
        $total = '0';

        foreach ($values as $value) {
            $total = bcadd($total, self::normalize($value), self::SCALE);
        }

        return self::round2($total);
    }

    public static function sub(mixed $a, mixed $b): string
    {
        return self::round2(bcsub(self::normalize($a), self::normalize($b), self::SCALE));
    }

    public static function mul(mixed $a, mixed $b): string
    {
        return self::round2(bcmul(self::normalize($a), self::normalize($b), self::SCALE));
    }

    /** @throws \DivisionByZeroError when $b is zero */
    public static function div(mixed $a, mixed $b): string
    {
        return self::round2(bcdiv(self::normalize($a), self::normalize($b), self::SCALE));
    }

    /** Sum of a list (e.g. a collection's pluck('amount')). */
    public static function sum(iterable $values): string
    {
        return self::add(...(is_array($values) ? array_values($values) : iterator_to_array($values, false)));
    }

    public static function round2(mixed $value): string
    {
        return self::round($value, 2);
    }

    /** Round half up (away from zero): 2.675 → 2.68, -1.005 → -1.01. Never returns "-0.00". */
    public static function round(mixed $value, int $decimals = 2): string
    {
        $value = self::normalize($value);
        $half = '0.'.str_repeat('0', $decimals).'5';

        $rounded = str_starts_with($value, '-')
            ? bcsub($value, $half, $decimals)
            : bcadd($value, $half, $decimals);

        return bccomp($rounded, '0', $decimals) === 0 ? bcadd('0', '0', $decimals) : $rounded;
    }

    /** -1, 0 or 1 (compared at 2 decimals after rounding). */
    public static function compare(mixed $a, mixed $b): int
    {
        return bccomp(self::round2($a), self::round2($b), 2);
    }

    public static function isZero(mixed $value): bool
    {
        return self::compare($value, '0') === 0;
    }

    public static function isNegative(mixed $value): bool
    {
        return self::compare($value, '0') < 0;
    }

    /** ₹1,25,000.00 */
    public static function format(mixed $value, bool $symbol = true): string
    {
        return Format::money(self::round2($value), $symbol);
    }

    /**
     * Turns null/''/int/numeric string into a plain decimal string. Floats are rejected on purpose:
     * they must never carry money or quantities (cast DB decimals as strings, read request input as strings).
     */
    public static function normalize(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        if (is_float($value)) {
            throw new InvalidArgumentException('Floats are not allowed for money/quantity math; pass a string.');
        }

        if (is_int($value)) {
            return (string) $value;
        }

        $value = str_replace([',', ' ', '₹'], '', trim((string) $value));

        if (! preg_match('/^[+-]?(\d+(\.\d*)?|\.\d+)$/', $value)) {
            throw new InvalidArgumentException("Not a number: {$value}");
        }

        $value = ltrim($value, '+');

        return str_ends_with($value, '.') ? $value.'0' : $value;
    }
}
