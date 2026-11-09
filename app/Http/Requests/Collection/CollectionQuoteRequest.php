<?php

namespace App\Http\Requests\Collection;

use App\Enums\MilkType;
use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Live rate / amount on the collection entry screen. */
class CollectionQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('collections.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'farmer_id' => ['required', 'integer', Rule::exists('farmers', 'id')->where('farm_id', app(CurrentFarm::class)->id())],
            'milk_type' => ['required', Rule::enum(MilkType::class)],
            'collection_date' => ['required', 'date_format:Y-m-d'],
            'quantity_litre' => ['nullable', 'decimal:0,2', 'between:0,500'],
            'fat' => ['nullable', 'decimal:0,2', 'between:0.1,15'],
            'snf' => ['nullable', 'decimal:0,2', 'between:0.1,15'],
        ];
    }
}
