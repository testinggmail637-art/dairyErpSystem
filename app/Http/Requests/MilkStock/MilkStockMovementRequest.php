<?php

namespace App\Http\Requests\MilkStock;

use App\Enums\MilkMovementType;
use App\Enums\MilkShift;
use App\Enums\MilkSource;
use App\Enums\MilkType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Record usage" (wastage / home use / calf feeding, `milk_stock.view`) and "Adjustment" (in / out,
 * `milk_stock.adjust`) — spec 7.4. Litres above 0 with up to 2 decimals, no future date, reason always required.
 */
class MilkStockMovementRequest extends FormRequest
{
    public const MAX_LITRES = '100000';

    public function authorize(): bool
    {
        return $this->user()->can($this->isAdjustment() ? 'milk_stock.adjust' : 'milk_stock.view');
    }

    protected function prepareForValidation(): void
    {
        $quantity = str_replace([',', ' '], '', trim((string) $this->input('quantity')));

        $this->merge([
            'quantity' => $quantity === '' ? null : $quantity,
            'reason' => trim((string) $this->input('reason')) ?: null,
            'shift' => $this->input('shift') ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'movement_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'shift' => ['nullable', Rule::enum(MilkShift::class)],
            'stock_source' => ['required', Rule::enum(MilkSource::class)],
            'milk_type' => ['required', Rule::enum(MilkType::class)],
            'movement_type' => ['required', Rule::in(array_map(fn (MilkMovementType $t) => $t->value, self::types($this->isAdjustment())))],
            'quantity' => ['required', 'decimal:0,2', 'gt:0', 'max:'.self::MAX_LITRES],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'movement_date.before_or_equal' => __('Milk stock cannot be recorded for a future date.'),
            'quantity.decimal' => __('Enter litres with up to 2 decimals.'),
            'quantity.gt' => __('Enter litres above zero.'),
            'reason.required' => __('Write why, e.g. "Spilled while pouring" or "Recount in the evening".'),
        ];
    }

    public function isAdjustment(): bool
    {
        return $this->routeIs('milk-stock.adjustments.*');
    }

    /** @return list<MilkMovementType> */
    public static function types(bool $adjustment): array
    {
        return $adjustment ? [MilkMovementType::AdjustmentIn, MilkMovementType::AdjustmentOut] : MilkMovementType::usageTypes();
    }
}
