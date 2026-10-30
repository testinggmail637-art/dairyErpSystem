<?php

namespace App\Http\Requests\Settings;

use App\Services\AppearanceService;
use App\Support\Color;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Settings → Appearance (spec 4.11). */
class UpdateAppearanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('appearance.manage');
    }

    /** Colours that may be left empty (= automatic). */
    private const OPTIONAL_COLORS = ['pdf_accent_color', 'login_panel_color', 'login_text_color'];

    protected function prepareForValidation(): void
    {
        $colors = [];

        foreach ([...AppearanceService::COLOR_KEYS, ...self::OPTIONAL_COLORS] as $key) {
            $value = trim((string) $this->input($key));
            $colors[$key] = $value === '' ? null : strtoupper($value);
        }

        $this->merge($colors);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $hex = 'regex:'.Color::HEX_PATTERN;
        $rules = [
            'app_name' => ['required', 'string', 'max:60'],
            'pdf_accent_color' => ['nullable', $hex],
            'login_panel_color' => ['nullable', $hex],
            'login_text_color' => ['nullable', $hex],
            'login_title' => ['nullable', 'string', 'max:60'],
            'font_family' => ['required', Rule::in(array_keys(AppearanceService::FONTS))],
            'base_font_size' => ['required', 'integer', 'between:13,16'],
            'theme_mode' => ['required', Rule::in(AppearanceService::THEMES)],
            'border_radius' => ['required', 'integer', 'between:0,12'],
            'table_density' => ['required', Rule::in(AppearanceService::DENSITIES)],
            'login_welcome_text' => ['nullable', 'string', 'max:150'],
            'print_header_text' => ['nullable', 'string', 'max:300'],
            'print_footer_text' => ['nullable', 'string', 'max:300'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
            'logo_dark' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
            'logo_mobile' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
            'logo_mobile_dark' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
            'favicon' => ['nullable', 'file', 'extensions:png,ico', 'max:512'],
            'login_background' => ['nullable', 'file', 'mimes:jpg,jpeg,webp', 'max:3072'],
            'login_logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
            'remove' => ['array'],
            'remove.*' => [Rule::in(array_keys(AppearanceService::UPLOADS))],
        ];

        foreach (AppearanceService::COLOR_KEYS as $key) {
            $rules[$key] = ['required', $hex];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return collect([...AppearanceService::COLOR_KEYS, ...self::OPTIONAL_COLORS])
            ->mapWithKeys(fn ($key) => ["{$key}.regex" => __('Use a colour like #1F6F5C (# and 6 hex digits).')])
            ->all();
    }

    /**
     * Scalar settings only (files are handled separately).
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return collect($this->validated())
            ->only(array_keys(AppearanceService::DEFAULTS))
            ->map(fn ($value, $key) => in_array($key, ['base_font_size', 'border_radius'], true) ? (int) $value : $value)
            ->all();
    }
}
