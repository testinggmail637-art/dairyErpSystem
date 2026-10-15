<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Small colour helpers for Settings → Appearance: shades, tints, readable text colour and WCAG contrast.
 * Colours are "#RRGGBB" strings.
 */
final class Color
{
    public const HEX_PATTERN = '/^#[0-9A-Fa-f]{6}$/';

    /** @return array{0: int, 1: int, 2: int} */
    public static function rgb(string $hex): array
    {
        if (! preg_match(self::HEX_PATTERN, $hex)) {
            throw new InvalidArgumentException("Not a #RRGGBB colour: {$hex}");
        }

        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }

    /** "31, 111, 92" — for Bootstrap's --bs-*-rgb variables. */
    public static function rgbString(string $hex): string
    {
        return implode(', ', self::rgb($hex));
    }

    public static function hex(int $r, int $g, int $b): string
    {
        return sprintf('#%02X%02X%02X', max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)));
    }

    /** Change HSL lightness by $percent points (−10 = 10 % darker), e.g. hover/active shades. */
    public static function adjustLightness(string $hex, float $percent): string
    {
        [$h, $s, $l] = self::toHsl($hex);

        return self::fromHsl($h, $s, max(0.0, min(1.0, $l + $percent / 100)));
    }

    /** Mix $hex with $with; $weight = share of $with (0.8 = 80 % white for a "subtle" background). */
    public static function mix(string $hex, string $with, float $weight): string
    {
        [$r1, $g1, $b1] = self::rgb($hex);
        [$r2, $g2, $b2] = self::rgb($with);

        return self::hex(
            (int) round($r1 + ($r2 - $r1) * $weight),
            (int) round($g1 + ($g2 - $g1) * $weight),
            (int) round($b1 + ($b2 - $b1) * $weight),
        );
    }

    /** White or black, whichever is easier to read on $background. */
    public static function readableTextOn(string $background): string
    {
        return self::contrastRatio('#FFFFFF', $background) >= self::contrastRatio('#000000', $background)
            ? '#FFFFFF'
            : '#000000';
    }

    /** WCAG 2 contrast ratio, 1 (none) … 21 (black on white). Body text should be ≥ 4.5. */
    public static function contrastRatio(string $foreground, string $background): float
    {
        $l1 = self::luminance($foreground);
        $l2 = self::luminance($background);

        return round((max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05), 2);
    }

    private static function luminance(string $hex): float
    {
        $channels = array_map(function (int $c) {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /** @return array{0: float, 1: float, 2: float} hue 0–1, saturation 0–1, lightness 0–1 */
    private static function toHsl(string $hex): array
    {
        [$r, $g, $b] = array_map(fn (int $c) => $c / 255, self::rgb($hex));
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            return [0.0, 0.0, $l];
        }

        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        $h = match ($max) {
            $r => ($g - $b) / $d + ($g < $b ? 6 : 0),
            $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        };

        return [$h / 6, $s, $l];
    }

    private static function fromHsl(float $h, float $s, float $l): string
    {
        if ($s == 0.0) {
            $v = (int) round($l * 255);

            return self::hex($v, $v, $v);
        }

        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;

        $channel = function (float $t) use ($p, $q): int {
            $t += $t < 0 ? 1 : ($t > 1 ? -1 : 0);
            $v = match (true) {
                $t < 1 / 6 => $p + ($q - $p) * 6 * $t,
                $t < 1 / 2 => $q,
                $t < 2 / 3 => $p + ($q - $p) * (2 / 3 - $t) * 6,
                default => $p,
            };

            return (int) round($v * 255);
        };

        return self::hex($channel($h + 1 / 3), $channel($h), $channel($h - 1 / 3));
    }
}
