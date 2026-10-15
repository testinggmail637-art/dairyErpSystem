<?php

use App\Support\Format;

if (! function_exists('asset_v')) {
    /**
     * Asset URL with a cache-busting version taken from the file's modification time.
     * Example: asset_v('css/app.css') → http://…/css/app.css?v=1760512345
     */
    function asset_v(string $path): string
    {
        $file = public_path($path);

        return asset($path).(is_file($file) ? '?v='.filemtime($file) : '');
    }
}

if (! function_exists('inr')) {
    /** ₹1,25,000.00 — see App\Support\Format::money(). */
    function inr(mixed $value, bool $symbol = true): string
    {
        return Format::money($value, $symbol);
    }
}

if (! function_exists('fmt_qty')) {
    /** 1,250.50 — see App\Support\Format::qty(). */
    function fmt_qty(mixed $value, int $decimals = 2): string
    {
        return Format::qty($value, $decimals);
    }
}

if (! function_exists('fmt_date')) {
    /** DD-MM-YYYY — see App\Support\Format::date(). */
    function fmt_date(mixed $value): string
    {
        return Format::date($value);
    }
}

if (! function_exists('fmt_datetime')) {
    /** DD-MM-YYYY hh:mm AM — see App\Support\Format::dateTime(). */
    function fmt_datetime(mixed $value): string
    {
        return Format::dateTime($value);
    }
}
