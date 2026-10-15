<?php

use App\Support\FinancialYear;
use Illuminate\Support\Carbon;

it('labels the April–March financial year', function (string $date, string $expected) {
    expect(FinancialYear::label(Carbon::parse($date)))->toBe($expected);
})->with([
    ['2026-04-01', '2026-27'],
    ['2026-10-15', '2026-27'],
    ['2027-03-31', '2026-27'],
    ['2027-04-01', '2027-28'],
    ['2099-12-31', '2099-00'],
]);

it('gives start and end dates', function () {
    $date = Carbon::parse('2027-01-10');

    expect(FinancialYear::start($date)->toDateString())->toBe('2026-04-01')
        ->and(FinancialYear::end($date)->toDateString())->toBe('2027-03-31');
});

it('uses today when no date is given', function () {
    Carbon::setTestNow('2026-03-15 10:00:00');

    expect(FinancialYear::label())->toBe('2025-26');

    Carbon::setTestNow();
});
