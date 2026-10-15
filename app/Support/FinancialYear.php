<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Indian financial year, April to March. 15-10-2026 and 31-03-2027 both belong to "2026-27".
 */
final class FinancialYear
{
    /** "2026-27" for any date from 01-04-2026 to 31-03-2027 (today when null). */
    public static function label(?CarbonInterface $date = null): string
    {
        $startYear = self::startYear($date);

        return $startYear.'-'.substr((string) ($startYear + 1), -2);
    }

    /** 01-04 of the year the financial year starts in. */
    public static function start(?CarbonInterface $date = null): CarbonImmutable
    {
        return CarbonImmutable::create(self::startYear($date), 4, 1, 0, 0, 0, config('app.timezone'));
    }

    /** 31-03 of the following year. */
    public static function end(?CarbonInterface $date = null): CarbonImmutable
    {
        return self::start($date)->addYear()->subDay();
    }

    private static function startYear(?CarbonInterface $date): int
    {
        $date ??= CarbonImmutable::now(config('app.timezone'));

        return $date->month >= 4 ? $date->year : $date->year - 1;
    }
}
