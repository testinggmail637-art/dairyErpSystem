<?php

use App\Support\Money;

it('adds, subtracts, multiplies and divides as 2-decimal strings', function () {
    expect(Money::add('0.10', '0.20'))->toBe('0.30')          // 0.1 + 0.2 != 0.3 in floats
        ->and(Money::add('100', '25.5', 3))->toBe('128.50')
        ->and(Money::add())->toBe('0.00')
        ->and(Money::sub('1000', '1250.75'))->toBe('-250.75')
        ->and(Money::mul('25.50', '40.25'))->toBe('1026.38')  // 1026.375 → half up
        ->and(Money::mul('8.5', '0'))->toBe('0.00')
        ->and(Money::div('1000', '3'))->toBe('333.33')
        ->and(Money::div('2', '3'))->toBe('0.67');
});

it('rounds half up away from zero', function (string $input, string $expected) {
    expect(Money::round2($input))->toBe($expected);
})->with([
    ['2.675', '2.68'],
    ['1.005', '1.01'],
    ['1.004', '1.00'],
    ['-1.005', '-1.01'],
    ['-0.004', '0.00'],   // never "-0.00"
    ['10', '10.00'],
]);

it('rounds to any precision', function () {
    expect(Money::round('6.45', 1))->toBe('6.5')
        ->and(Money::round('1234.5', 0))->toBe('1235');
});

it('sums a list or collection', function () {
    expect(Money::sum(['10.10', '20.20', null, 5]))->toBe('35.30')
        ->and(Money::sum(collect(['0.01', '0.01', '0.01'])))->toBe('0.03');
});

it('compares values', function () {
    expect(Money::compare('10.00', '10'))->toBe(0)
        ->and(Money::compare('9.99', '10'))->toBe(-1)
        ->and(Money::compare('10.01', '10'))->toBe(1)
        ->and(Money::isZero('0.004'))->toBeTrue()
        ->and(Money::isNegative('-0.01'))->toBeTrue()
        ->and(Money::isNegative('0'))->toBeFalse();
});

it('accepts formatted input and formats output in Indian style', function () {
    expect(Money::add('₹1,25,000', '0.5'))->toBe('125000.50')
        ->and(Money::format('125000.505'))->toBe('₹1,25,000.51')
        ->and(Money::format('-3100', false))->toBe('-3,100.00');
});

it('rejects floats', function () {
    Money::add(0.1, '0.2');
})->throws(InvalidArgumentException::class, 'Floats are not allowed');

it('rejects non-numbers', function () {
    Money::add('12abc');
})->throws(InvalidArgumentException::class);

it('refuses division by zero', function () {
    Money::div('10', '0');
})->throws(DivisionByZeroError::class);
