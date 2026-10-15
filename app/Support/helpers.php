<?php

use App\Enums\RoleName;
use App\Support\Format;
use Illuminate\Support\Str;

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

if (! function_exists('role_label')) {
    /** "herd_staff" → "Herd Staff" (translated label for seeded roles, headline for custom ones). */
    function role_label(string $name): string
    {
        return RoleName::tryFrom($name)?->label() ?? Str::headline($name);
    }
}

if (! function_exists('audit_model_label')) {
    /** "App\Models\PaymentMode" → "Payment Mode" (for the audit log). */
    function audit_model_label(?string $type): string
    {
        return $type === null ? '—' : Str::headline(class_basename($type));
    }
}

if (! function_exists('audit_value')) {
    /** A logged attribute value as display text: booleans as Yes/No, lists comma-separated, null as —. */
    function audit_value(mixed $value): string
    {
        return match (true) {
            $value === null || $value === '' => '—',
            is_bool($value) => $value ? __('Yes') : __('No'),
            is_array($value) => array_is_list($value)
                ? implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $value))
                : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default => (string) $value,
        };
    }
}

if (! function_exists('fmt_datetime')) {
    /** DD-MM-YYYY hh:mm AM — see App\Support\Format::dateTime(). */
    function fmt_datetime(mixed $value): string
    {
        return Format::dateTime($value);
    }
}
