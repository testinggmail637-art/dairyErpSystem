<?php

namespace App\Http\Requests\Settings;

use App\Support\CurrentFarm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Name + code (unique per farm, upper-case) + active flag, for sections and payment modes.
 */
abstract class MasterDataRequest extends FormRequest
{
    abstract protected function table(): string;

    abstract protected function routeParameter(): string;

    public function authorize(): bool
    {
        return $this->user()->can('settings.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $row = $this->route($this->routeParameter());

        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'required', 'string', 'max:20', 'regex:/^[A-Z0-9_]+$/',
                Rule::unique($this->table(), 'code')
                    ->where('farm_id', app(CurrentFarm::class)->id())
                    ->ignore($row instanceof Model ? $row->getKey() : null),
            ],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['code.regex' => __('Use capital letters, numbers and underscores only (e.g. UPI, BANK_2).')];
    }
}
