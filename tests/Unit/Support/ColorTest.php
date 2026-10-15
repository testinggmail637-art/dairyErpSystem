<?php

use App\Support\Color;

it('converts hex to an rgb string', function () {
    expect(Color::rgbString('#1F6F5C'))->toBe('31, 111, 92');
});

it('makes hover shades by changing lightness', function () {
    expect(Color::adjustLightness('#808080', -10))->toBe('#676767') // L 50.2% → 40.2%
        ->and(Color::adjustLightness('#FFFFFF', 10))->toBe('#FFFFFF')
        ->and(Color::adjustLightness('#1F6F5C', -10))->not->toBe('#1F6F5C');
});

it('mixes colours for subtle backgrounds', function () {
    expect(Color::mix('#000000', '#FFFFFF', 0.5))->toBe('#808080')
        ->and(Color::mix('#1F6F5C', '#FFFFFF', 0))->toBe('#1F6F5C');
});

it('picks readable text and measures contrast', function () {
    expect(Color::readableTextOn('#12332B'))->toBe('#FFFFFF')
        ->and(Color::readableTextOn('#FFC107'))->toBe('#000000')
        ->and(Color::contrastRatio('#000000', '#FFFFFF'))->toBe(21.0)
        ->and(Color::contrastRatio('#777777', '#777777'))->toBe(1.0);
});

it('rejects colours that are not #RRGGBB', function () {
    Color::rgb('red');
})->throws(InvalidArgumentException::class);
