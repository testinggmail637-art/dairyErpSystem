<?php

namespace App\Http\Requests\Sales;

use App\Enums\MilkShift;
use App\Enums\MilkSource;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('subscriptions.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'quantity' => str_replace([',', ' '], '', trim((string) $this->input('quantity'))),
            'end_date' => $this->filled('end_date') ? $this->input('end_date') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $farmId = app(CurrentFarm::class)->id();
        $editing = $this->route('subscription') instanceof Subscription;

        return [
            'customer_id' => ['required', 'integer',
                Rule::exists('customers', 'id')->where('farm_id', $farmId)->whereNull('deleted_at')],
            'product_id' => ['required', 'integer',
                Rule::exists('products', 'id')->where('farm_id', $farmId)->whereNull('deleted_at')],
            'shift' => ['required', Rule::enum(MilkShift::class)],
            'quantity' => ['required', 'decimal:0,2', 'gt:0', 'max:99999.99'],
            'stock_source' => ['required', Rule::enum(MilkSource::class)],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'status' => [$editing ? 'required' : 'prohibited', Rule::enum(SubscriptionStatus::class)],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $current = $this->route('subscription');
            $active = ! $current instanceof Subscription || $this->input('status') === SubscriptionStatus::Active->value;

            if ($active && app(SubscriptionService::class)->hasOverlap(
                (int) $this->input('customer_id'), (int) $this->input('product_id'), $this->input('shift'),
                $this->input('start_date'), $this->input('end_date'), $current?->id,
            )) {
                $validator->errors()->add('start_date', __('This customer already has an active subscription for this product and shift in these dates. Edit or stop that one instead.'));
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.gt' => __('Enter a quantity above 0.'),
            'quantity.decimal' => __('Enter litres with up to 2 decimals.'),
            'end_date.after_or_equal' => __('The end date cannot be before the start date.'),
        ];
    }
}
