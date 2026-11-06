<?php

namespace App\Http\Requests\Sales;

use App\Enums\MilkShift;
use App\Enums\MilkSource;
use App\Models\Delivery;
use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** One-off / B2B dispatch (spec Section 6): a delivered row without subscription, with optional fat / SNF readings. */
class DispatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('deliveries.update') && $this->user()->can('deliveries.view_all_routes');
    }

    protected function prepareForValidation(): void
    {
        $clean = fn (string $key) => str_replace([',', ' ', '₹'], '', trim((string) $this->input($key)));

        $this->merge([
            'delivered_qty' => $clean('delivered_qty'),
            'rate' => $clean('rate') === '' ? null : $clean('rate'),
            'client_uuid' => $this->input('client_uuid') ?: null,
        ] + self::cleanReadings($this));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $farmId = app(CurrentFarm::class)->id();

        return [
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('farm_id', $farmId)->whereNull('deleted_at')],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('farm_id', $farmId)->whereNull('deleted_at')],
            'delivery_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'shift' => ['required', Rule::enum(MilkShift::class)],
            'stock_source' => ['required', Rule::enum(MilkSource::class)],
            'delivered_qty' => ['required', 'decimal:0,2', 'gt:0', 'max:99999.99'],
            'rate' => ['nullable', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'notes' => ['nullable', 'string', 'max:500'],
            'client_uuid' => ['nullable', 'uuid'],
        ] + self::readingRules();
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || ($this->input('client_uuid')
                && Delivery::query()->where('client_uuid', $this->input('client_uuid'))->exists())) {
                return; // a repeated submit returns the first record in the controller
            }

            $taken = Delivery::query()->where([
                'customer_id' => $this->input('customer_id'),
                'product_id' => $this->input('product_id'),
                'delivery_date' => $this->input('delivery_date'),
                'shift' => $this->input('shift'),
            ])->exists();

            if ($taken) {
                $validator->errors()->add('customer_id', __('This customer already has a delivery of this product in this shift on this date. Change it in the delivery grid instead.'));
            }
        }];
    }

    /** @return array<string, list<string>> */
    public static function readingRules(): array
    {
        return [
            'fat' => ['nullable', 'decimal:0,2', 'between:0,15'],
            'snf' => ['nullable', 'decimal:0,2', 'between:0,15'],
            'buyer_measured_qty' => ['nullable', 'decimal:0,2', 'gt:0', 'max:99999.99'],
            'buyer_fat' => ['nullable', 'decimal:0,2', 'between:0,15'],
            'buyer_snf' => ['nullable', 'decimal:0,2', 'between:0,15'],
        ];
    }

    /** @return array<string, ?string> empty reading fields become null */
    public static function cleanReadings(Request $request): array
    {
        return collect(Delivery::READINGS)->mapWithKeys(function ($k) use ($request) {
            $v = str_replace([',', ' '], '', trim((string) $request->input($k)));

            return [$k => $v === '' ? null : $v];
        })->all();
    }
}
