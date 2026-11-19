<?php

namespace App\Support;

use App\Models\Farm;
use Illuminate\Support\Facades\Route;

/**
 * Public website helpers: which farm it shows, its settings (config defaults overridden by
 * farms.settings.website), locale-aware links and the day / night theme.
 */
final class Website
{
    public const LOCALES = ['hi', 'en'];

    public const DEFAULT_LOCALE = 'hi';

    public const THEME_COOKIE = 'w_theme';

    /** Pages besides home; each has a route in both languages. */
    public const PAGES = [
        'about', 'our-herd', 'products', 'home-delivery', 'bulk-orders', 'farmers', 'quality',
        'blog', 'faq', 'contact', 'thank-you', 'privacy', 'terms', 'refund-policy',
    ];

    public static function farm(): ?Farm
    {
        return self::memo('farm', fn () => Farm::query()
            ->where('is_active', true)
            ->when(config('website.farm_id'), fn ($q, $id) => $q->whereKey($id))
            ->orderBy('id')
            ->first());
    }

    public static function settings(): array
    {
        return self::memo('settings', fn () => array_replace_recursive(
            config('website.defaults'),
            (array) data_get(self::farm()?->settings, 'website', []),
        ));
    }

    /** A setting; values stored as ['hi' => …, 'en' => …] return the current language. */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = data_get(self::settings(), $key, $default);

        return is_array($value) && array_key_exists(app()->getLocale(), $value) ? $value[app()->getLocale()] : $value;
    }

    public static function route(string $page, array $params = [], ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return route($locale === self::DEFAULT_LOCALE ? "website.{$page}" : "website.{$locale}.{$page}", $params);
    }

    /** Same page in the other language (for the हिं / EN switch and hreflang). */
    public static function alternateUrls(): array
    {
        $current = Route::current();
        $page = $current?->defaults['page'] ?? 'home';
        $params = $current?->parameters() ?? [];
        unset($params['page']);

        return collect(self::LOCALES)->mapWithKeys(fn ($l) => [$l => self::route($page, $params, $l)])->all();
    }

    /** Cached for the current request only, so a saved setting shows on the next request. */
    private static function memo(string $key, \Closure $resolve): mixed
    {
        $attributes = request()->attributes;
        if (! $attributes->has("website.{$key}")) {
            $attributes->set("website.{$key}", $resolve());
        }

        return $attributes->get("website.{$key}");
    }

    /** light | dark | auto (auto = follow the device until the visitor chooses). */
    public static function theme(?string $cookie): string
    {
        return in_array($cookie, ['light', 'dark'], true) ? $cookie : 'auto';
    }

    public static function telLink(): string
    {
        return 'tel:'.preg_replace('/[^0-9+]/', '', (string) self::get('phone'));
    }

    public static function whatsappLink(?string $text = null): string
    {
        $text ??= __('website.whatsapp_text');

        return 'https://wa.me/'.preg_replace('/\D/', '', (string) self::get('whatsapp')).'?text='.rawurlencode($text);
    }
}
