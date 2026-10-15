<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Settings → Farm: farm details plus the `farms.settings` keys (spec Section 6, Phase 0).
 * Appearance keys are edited on their own screen (Task 12) and are not touched here.
 */
class UpdateFarmSettingsRequest extends FormRequest
{
    /** Setting key => validation rules. Keys must match Farm::DEFAULT_SETTINGS. */
    public const SETTING_RULES = [
        'milk_drop_alert_percent' => ['required', 'integer', 'between:1,100'],
        'allow_negative_milk_stock' => ['boolean'],
        'farmer_payment_cycle_days' => ['required', 'integer', 'between:1,31'],
        'cow_gestation_days' => ['required', 'integer', 'between:250,320'],
        'buffalo_gestation_days' => ['required', 'integer', 'between:280,340'],
        'vaccination_reminder_days' => ['required', 'integer', 'between:0,60'],
        'calving_reminder_days' => ['required', 'integer', 'between:0,60'],
        'staff_same_day_edit_only' => ['boolean'],
        'bill_due_days' => ['required', 'integer', 'between:0,90'],
        'delivery_sheet_generate_time' => ['required', 'date_format:H:i'],
    ];

    public function authorize(): bool
    {
        return $this->user()->can('settings.manage');
    }

    protected function prepareForValidation(): void
    {
        $settings = (array) $this->input('settings', []);

        foreach (['allow_negative_milk_stock', 'staff_same_day_edit_only'] as $key) {
            $settings[$key] = filter_var($settings[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        $this->merge([
            'gstin' => strtoupper(trim((string) $this->input('gstin'))) ?: null,
            'settings' => $settings,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:20'],
            // 15-character GSTIN: 2-digit state code + PAN + entity + Z + checksum.
            'gstin' => ['nullable', 'string', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'settings' => ['required', 'array'],
        ];

        foreach (self::SETTING_RULES as $key => $keyRules) {
            $rules["settings.{$key}"] = $keyRules;
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect(self::SETTING_RULES)
            ->mapWithKeys(fn ($rules, $key) => ["settings.{$key}" => __(str_replace('_', ' ', $key))])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['gstin.regex' => __('Enter a valid 15-character GSTIN, e.g. 27ABCDE1234F1Z5.')];
    }
}
