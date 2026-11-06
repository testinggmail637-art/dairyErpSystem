<?php

namespace App\Http\Requests\Sales;

use App\Enums\DeliveryStatus;
use App\Http\Requests\Concerns\RequiresReason;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Admin edit of one delivery (status, litres, rate until billed). Audit-logged with a reason (spec 4.4). */
class UpdateDeliveryRequest extends FormRequest
{
    use RequiresReason;

    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('delivery'));
    }

    protected function prepareForValidation(): void
    {
        $clean = fn (string $key) => str_replace([',', ' ', '₹'], '', trim((string) $this->input($key)));

        $this->merge(['delivered_qty' => $clean('delivered_qty'), 'rate' => $clean('rate')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->reasonRules() + [
            'status' => ['required', Rule::enum(DeliveryStatus::class)],
            'delivered_qty' => ['exclude_unless:status,'.DeliveryStatus::Delivered->value, 'required', 'decimal:0,2', 'gt:0', 'max:99999.99'],
            'rate' => ['required', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'skip_reason' => ['exclude_unless:status,'.DeliveryStatus::Skipped->value, 'required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
