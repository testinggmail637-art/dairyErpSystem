<?php

use App\Models\User;

it('normalizes Indian phone numbers', function (?string $input, ?string $expected) {
    expect(User::normalizePhone($input))->toBe($expected);
})->with([
    ['9876543210', '9876543210'],
    ['+91 98765 43210', '9876543210'],
    ['919876543210', '9876543210'],
    ['09876543210', '9876543210'],
    ['', null],
    [null, null],
]);
