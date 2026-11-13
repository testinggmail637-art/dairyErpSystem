<?php

namespace App\Http\Requests\Herd;

use App\Enums\FeedUnit;
use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Add / change a feed item: name (unique on the farm), unit, reorder level, active. */
class FeedItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('feed.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => preg_replace('/\s+/', ' ', trim((string) $this->input('name'))),
            'reorder_level' => str_replace([',', ' '], '', trim((string) $this->input('reorder_level'))) ?: '0',
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $item = $this->route('item');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('feed_items', 'name')
                ->where('farm_id', app(CurrentFarm::class)->id())->ignore($item)],
            // the unit cannot change once the item has stock entries (their quantities are in it)
            'unit' => [$item?->transactions()->exists() ? Rule::in([$item->unit->value]) : 'required', Rule::enum(FeedUnit::class)],
            'reorder_level' => ['required', 'decimal:0,2', 'gte:0', 'max:99999999.99'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['unit.in' => __('The unit cannot change once the item has stock entries.')];
    }
}
