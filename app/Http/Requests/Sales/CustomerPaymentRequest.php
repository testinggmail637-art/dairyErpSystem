<?php

namespace App\Http\Requests\Sales;

use App\Http\Requests\Concerns\RequiresReason;
use App\Models\CustomerPayment;
use App\Models\DeliveryRoute;
use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/** Record / edit a customer payment. Edits are audit-logged with a reason (spec 4.4). */
class CustomerPaymentRequest extends FormRequest
{
    use RequiresReason;

    public function authorize(): bool
    {
        $payment = $this->payment();

        return $payment ? $this->user()->can('update', $payment) : $this->user()->can('create', CustomerPayment::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => str_replace([',', ' ', '₹'], '', trim((string) $this->input('amount'))),
            'reference_no' => trim((string) $this->input('reference_no')) ?: null,
            'client_uuid' => $this->input('client_uuid') ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $farmId = app(CurrentFarm::class)->id();
        $payment = $this->payment();
        $currentMode = $payment?->payment_mode_id;

        return ($payment ? $this->reasonRules() : []) + [
            'customer_id' => $payment ? ['prohibited'] : ['required', 'integer', $this->customerRule($farmId)],
            'payment_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'payment_mode_id' => ['required', 'integer', Rule::exists('payment_modes', 'id')->where('farm_id', $farmId)
                ->where(fn ($q) => $q->where('is_active', true)->when($currentMode, fn ($q) => $q->orWhere('id', $currentMode)))],
            'reference_no' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:500'],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }

    /** Users without customers.view (delivery person) may only take money from customers on their own routes. */
    private function customerRule(?int $farmId): Exists
    {
        $rule = Rule::exists('customers', 'id')->where('farm_id', $farmId)->whereNull('deleted_at');

        if (! $this->user()->can('customers.view')) {
            $rule->whereIn('route_id', DeliveryRoute::query()->where('delivery_user_id', $this->user()->id)->pluck('id')->all());
        }

        return $rule;
    }

    private function payment(): ?CustomerPayment
    {
        $payment = $this->route('customer_payment');

        return $payment instanceof CustomerPayment ? $payment : null;
    }
}
