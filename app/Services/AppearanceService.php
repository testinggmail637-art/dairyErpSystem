<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\User;
use App\Support\Color;
use App\Support\SvgSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

/**
 * Settings → Appearance (spec 4.11). Everything visual comes from here: stored per farm in
 * `farms.settings.appearance`, merged over DEFAULTS, cached as `appearance:{farm_id}`, and turned into
 * CSS variables for the layout, login page, charts and PDFs.
 */
class AppearanceService
{
    public const DEFAULTS = [
        'app_name' => 'Dairy ERP',
        'logo_path' => null,
        'logo_dark_path' => null,
        'favicon_path' => null,
        'primary_color' => '#1F6F5C',
        'secondary_color' => '#6C757D',
        'success_color' => '#198754',
        'danger_color' => '#DC3545',
        'warning_color' => '#FFC107',
        'sidebar_bg' => '#12332B',
        'sidebar_text' => '#FFFFFF',
        'topbar_bg' => '#FFFFFF',
        'body_bg' => '#F5F7F6',
        'font_family' => 'Inter',
        'base_font_size' => 14,
        'theme_mode' => 'light',
        'border_radius' => 6,
        'table_density' => 'normal',
        'login_background_path' => null,
        'login_welcome_text' => 'Welcome to Dairy ERP',
        'print_header_text' => null,   // null = farm name + address
        'print_footer_text' => null,
        'pdf_accent_color' => null,    // null = primary colour
    ];

    public const COLOR_KEYS = [
        'primary_color', 'secondary_color', 'success_color', 'danger_color', 'warning_color',
        'sidebar_bg', 'sidebar_text', 'topbar_bg', 'body_bg',
    ];

    /** Font name => CSS font stack. Files live in public/vendor/fonts (no CDN). */
    public const FONTS = [
        'Inter' => "'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif",
        'Roboto' => "'Roboto', system-ui, -apple-system, 'Segoe UI', sans-serif",
        'Poppins' => "'Poppins', system-ui, -apple-system, 'Segoe UI', sans-serif",
        'Noto Sans' => "'Noto Sans', system-ui, -apple-system, 'Segoe UI', sans-serif",
        'System default' => "system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif",
    ];

    public const THEMES = ['light', 'dark', 'auto'];

    public const DENSITIES = ['normal', 'compact'];

    /** Upload key => [setting key, folder file name]. */
    public const UPLOADS = [
        'logo' => 'logo_path',
        'logo_dark' => 'logo_dark_path',
        'favicon' => 'favicon_path',
        'login_background' => 'login_background_path',
    ];

    /** Ready-made colour sets that fill the form (spec 4.11). */
    public const PRESETS = [
        'green_dairy' => [
            'label' => 'Green Dairy',
            'primary_color' => '#1F6F5C', 'secondary_color' => '#6C757D', 'success_color' => '#198754',
            'danger_color' => '#DC3545', 'warning_color' => '#FFC107', 'sidebar_bg' => '#12332B',
            'sidebar_text' => '#FFFFFF', 'topbar_bg' => '#FFFFFF', 'body_bg' => '#F5F7F6',
        ],
        'blue_professional' => [
            'label' => 'Blue Professional',
            'primary_color' => '#1D4ED8', 'secondary_color' => '#64748B', 'success_color' => '#15803D',
            'danger_color' => '#B91C1C', 'warning_color' => '#F59E0B', 'sidebar_bg' => '#0F172A',
            'sidebar_text' => '#E2E8F0', 'topbar_bg' => '#FFFFFF', 'body_bg' => '#F1F5F9',
        ],
        'warm_orange' => [
            'label' => 'Warm Orange',
            'primary_color' => '#C2410C', 'secondary_color' => '#78716C', 'success_color' => '#15803D',
            'danger_color' => '#B91C1C', 'warning_color' => '#EAB308', 'sidebar_bg' => '#431407',
            'sidebar_text' => '#FFF7ED', 'topbar_bg' => '#FFFFFF', 'body_bg' => '#FFFBF5',
        ],
        'dark_slate' => [
            'label' => 'Dark Slate',
            'primary_color' => '#0EA5E9', 'secondary_color' => '#94A3B8', 'success_color' => '#22C55E',
            'danger_color' => '#EF4444', 'warning_color' => '#F59E0B', 'sidebar_bg' => '#020617',
            'sidebar_text' => '#CBD5E1', 'topbar_bg' => '#1E293B', 'body_bg' => '#0F172A',
        ],
    ];

    /**
     * Appearance of $farm (defaults when null, e.g. a farm-less page). Cached per farm; the cache is
     * cleared whenever the farm is saved (Farm::booted).
     *
     * @return array<string, mixed>
     */
    public function get(?Farm $farm): array
    {
        if ($farm === null) {
            return $this->withDerived(self::DEFAULTS, null);
        }

        return Cache::rememberForever(self::cacheKey($farm), function () use ($farm) {
            $saved = array_intersect_key((array) $farm->setting('appearance', []), self::DEFAULTS);

            return $this->withDerived(array_replace(self::DEFAULTS, $saved), $farm);
        });
    }

    /**
     * Saved values over the defaults, without derived fallbacks — what the Appearance form edits.
     *
     * @return array<string, mixed>
     */
    public function stored(Farm $farm): array
    {
        return array_replace(self::DEFAULTS, array_intersect_key((array) $farm->setting('appearance', []), self::DEFAULTS));
    }

    public static function cacheKey(Farm $farm): string
    {
        return "appearance:{$farm->getKey()}";
    }

    /** light / dark / auto for this user: their profile choice wins over the farm default. */
    public function themeFor(?User $user, array $appearance): string
    {
        $own = $user?->preferences['theme_mode'] ?? null;

        return in_array($own, self::THEMES, true) ? $own : $appearance['theme_mode'];
    }

    /**
     * CSS variables for the layout: Bootstrap 5.3 variables + app variables. Hover/active shades are ±10 % lightness.
     *
     * @return array{root: array<string, string>, light: array<string, string>, dark: array<string, string>}
     */
    public function cssVariables(array $a): array
    {
        $root = [
            '--bs-body-font-family' => self::FONTS[$a['font_family']] ?? self::FONTS['Inter'],
            '--bs-body-font-size' => ((int) $a['base_font_size']).'px',
            '--bs-border-radius' => ((int) $a['border_radius']).'px',
            '--bs-border-radius-sm' => max(0, (int) $a['border_radius'] - 2).'px',
            '--bs-border-radius-lg' => ((int) $a['border_radius'] + 2).'px',
            '--sidebar-bg' => $a['sidebar_bg'],
            '--sidebar-text' => $a['sidebar_text'],
            '--app-table-cell-py' => $a['table_density'] === 'compact' ? '0.3rem' : '0.55rem',
            '--app-table-cell-px' => $a['table_density'] === 'compact' ? '0.4rem' : '0.6rem',
            '--app-pdf-accent' => $a['pdf_accent_color'],
        ];

        $light = ['--bs-body-bg' => $a['body_bg'], '--topbar-bg' => $a['topbar_bg']];
        $dark = ['--topbar-bg' => 'var(--bs-tertiary-bg)'];

        foreach (['primary', 'secondary', 'success', 'danger', 'warning'] as $name) {
            $hex = $a[$name.'_color'];
            $root += [
                "--bs-{$name}" => $hex,
                "--bs-{$name}-rgb" => Color::rgbString($hex),
                "--app-{$name}-hover" => Color::adjustLightness($hex, -10),
                "--app-{$name}-active" => Color::adjustLightness($hex, -15),
                "--app-on-{$name}" => Color::readableTextOn($hex),
            ];
            // Bootstrap's alert/badge "subtle" palette, derived the same way Bootstrap does.
            $light += [
                "--bs-{$name}-text-emphasis" => Color::mix($hex, '#000000', 0.6),
                "--bs-{$name}-bg-subtle" => Color::mix($hex, '#FFFFFF', 0.8),
                "--bs-{$name}-border-subtle" => Color::mix($hex, '#FFFFFF', 0.6),
            ];
            $dark += [
                "--bs-{$name}-text-emphasis" => Color::mix($hex, '#FFFFFF', 0.4),
                "--bs-{$name}-bg-subtle" => Color::mix($hex, '#000000', 0.8),
                "--bs-{$name}-border-subtle" => Color::mix($hex, '#000000', 0.4),
            ];
        }

        $root += [
            '--bs-link-color' => 'var(--bs-primary)',
            '--bs-link-color-rgb' => 'var(--bs-primary-rgb)',
            '--bs-link-hover-color' => 'var(--app-primary-hover)',
            '--bs-focus-ring-color' => 'rgba(var(--bs-primary-rgb), 0.25)',
        ];

        return ['root' => $root, 'light' => $light, 'dark' => $dark];
    }

    /** The `<style>` contents the layouts print in <head>. */
    public function styleBlock(array $a): string
    {
        $vars = $this->cssVariables($a);
        $render = fn (array $list) => collect($list)->map(fn ($v, $k) => "    {$k}: {$v};")->implode("\n");

        return ":root {\n{$render($vars['root'])}\n}\n"
            ."[data-bs-theme=\"light\"] {\n{$render($vars['light'])}\n}\n"
            ."[data-bs-theme=\"dark\"] {\n{$render($vars['dark'])}\n}\n";
    }

    /** Public URL of an uploaded branding file (null when not set). */
    public function url(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    /**
     * Readability problems (contrast below 4.5:1) — shown as warnings, never blocking (spec 4.11).
     *
     * @return list<string>
     */
    public function contrastWarnings(array $a): array
    {
        $checks = [
            [__('Sidebar text on sidebar background'), $a['sidebar_text'], $a['sidebar_bg']],
            [__('Page text on page background'), Color::readableTextOn($a['body_bg']) === '#FFFFFF' ? '#FFFFFF' : '#212529', $a['body_bg']],
            [__('Button text on primary colour'), Color::readableTextOn($a['primary_color']), $a['primary_color']],
        ];

        $warnings = [];

        foreach ($checks as [$label, $fg, $bg]) {
            $ratio = Color::contrastRatio($fg, $bg);

            if ($ratio < 4.5) {
                $warnings[] = __(':what is hard to read (contrast :ratio:1, should be at least 4.5:1).', ['what' => $label, 'ratio' => $ratio]);
            }
        }

        return $warnings;
    }

    /**
     * Save the form. Uploaded files replace old ones; "remove" flags clear them. Old files are deleted only after
     * the database change is committed.
     *
     * @param  array<string, mixed>  $values  validated scalar settings
     * @param  array<string, UploadedFile|null>  $uploads  keyed like self::UPLOADS
     * @param  list<string>  $remove  upload keys to clear
     */
    public function save(Farm $farm, array $values, array $uploads = [], array $remove = []): void
    {
        $current = (array) $farm->setting('appearance', []);
        $next = array_replace($current, array_intersect_key($values, self::DEFAULTS));
        $obsolete = [];
        $stored = [];

        foreach (self::UPLOADS as $key => $settingKey) {
            if (($uploads[$key] ?? null) instanceof UploadedFile) {
                $stored[] = $next[$settingKey] = $this->storeUpload($farm, $key, $uploads[$key]);
                $obsolete[] = $current[$settingKey] ?? null;
            } elseif (in_array($key, $remove, true)) {
                $obsolete[] = $current[$settingKey] ?? null;
                $next[$settingKey] = null;
            }
        }

        try {
            DB::transaction(function () use ($farm, $next) {
                $settings = $farm->settings ?? [];
                $settings['appearance'] = $next;
                $farm->update(['settings' => $settings]);
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($stored);

            throw $e;
        }

        Storage::disk('public')->delete(array_values(array_filter($obsolete)));
    }

    /** Back to the defaults: removes the stored appearance and the uploaded files of this farm. */
    public function reset(Farm $farm): void
    {
        $paths = array_values(array_filter(array_map(
            fn (string $key) => $farm->setting("appearance.{$key}"),
            self::UPLOADS,
        )));

        DB::transaction(function () use ($farm) {
            $settings = $farm->settings ?? [];
            unset($settings['appearance']);
            $farm->update(['settings' => $settings]);
        });

        Storage::disk('public')->delete($paths);
    }

    /** Store one branding upload under branding/{farm_id}/ (spec 4.11 sizes); SVGs are sanitized. */
    private function storeUpload(Farm $farm, string $key, UploadedFile $file): string
    {
        $folder = "branding/{$farm->getKey()}";
        $name = $key.'-'.Str::lower(Str::random(8));
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $disk = Storage::disk('public');

        if ($extension === 'svg') {
            $disk->put("{$folder}/{$name}.svg", SvgSanitizer::sanitize((string) file_get_contents($file->getRealPath())));

            return "{$folder}/{$name}.svg";
        }

        if ($extension === 'ico') {
            return $file->storeAs($folder, "{$name}.ico", 'public');
        }

        $image = ImageManager::gd()->read($file->getRealPath());

        [$path, $encoded] = match ($key) {
            'favicon' => ["{$folder}/{$name}.png", $image->cover(64, 64)->toPng()],
            'login_background' => ["{$folder}/{$name}.jpg", $image->scaleDown(width: 1920)->toJpeg(82)],
            default => ["{$folder}/{$name}.png", $image->scaleDown(width: 400)->toPng()],
        };

        $disk->put($path, (string) $encoded);

        return $path;
    }

    /**
     * @param  array<string, mixed>  $a
     * @return array<string, mixed>
     */
    private function withDerived(array $a, ?Farm $farm): array
    {
        $a['print_header_text'] = filled($a['print_header_text'])
            ? $a['print_header_text']
            : trim(($farm?->name ?? $a['app_name']).($farm?->address ? "\n".$farm->address : ''));
        $a['pdf_accent_color'] = filled($a['pdf_accent_color']) ? $a['pdf_accent_color'] : $a['primary_color'];
        $a['logo_dark_path'] = $a['logo_dark_path'] ?: $a['logo_path'];
        $a['base_font_size'] = (int) $a['base_font_size'];
        $a['border_radius'] = (int) $a['border_radius'];

        return $a;
    }
}
