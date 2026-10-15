<?php

use App\Support\Format;
use Illuminate\Support\Carbon;

it('formats money with Indian grouping', function (mixed $input, string $expected) {
    expect(Format::money($input))->toBe($expected);
})->with([
    ['125000', '₹1,25,000.00'],
    ['1250000.5', '₹12,50,000.50'],
    ['999', '₹999.00'],
    ['1000', '₹1,000.00'],
    ['100000', '₹1,00,000.00'],
    ['10000000', '₹1,00,00,000.00'],
    ['0', '₹0.00'],
    [null, '₹0.00'],
    ['', '₹0.00'],
    ['-3100', '-₹3,100.00'],
    [3600, '₹3,600.00'],
    ['1,25,000', '₹1,25,000.00'],
]);

it('rounds half up to 2 decimals without float errors', function (string $input, string $expected) {
    expect(Format::money($input, false))->toBe($expected);
})->with([
    ['2.675', '2.68'],     // float math would give 2.67
    ['1.005', '1.01'],
    ['1.004', '1.00'],
    ['-1.005', '-1.01'],
    ['-0.001', '0.00'],    // no "-0.00"
    ['.5', '0.50'],
]);

it('formats quantities', function () {
    expect(Format::qty('1250.5'))->toBe('1,250.50')
        ->and(Format::qty('43'))->toBe('43.00')
        ->and(Format::number('1234567', 0))->toBe('12,34,567');
});

it('rejects values that are not numbers', function () {
    Format::money('abc');
})->throws(InvalidArgumentException::class);

it('formats dates as DD-MM-YYYY', function () {
    expect(Format::date('2026-04-01'))->toBe('01-04-2026')
        ->and(Format::date(Carbon::create(2027, 10, 8)))->toBe('08-10-2027')
        ->and(Format::date(null))->toBe('')
        ->and(fmt_date('2026-12-31'))->toBe('31-12-2026');
});

it('formats date-times in IST', function () {
    expect(Format::dateTime(Carbon::parse('2026-10-15 03:30:00', 'UTC')))->toBe('15-10-2026 09:00 AM');
});

it('exposes global helpers', function () {
    expect(inr('125000'))->toBe('₹1,25,000.00')
        ->and(fmt_qty('8.5'))->toBe('8.50');
});
