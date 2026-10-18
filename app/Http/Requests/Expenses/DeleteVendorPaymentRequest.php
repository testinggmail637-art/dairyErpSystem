<?php

namespace App\Http\Requests\Expenses;

use App\Http\Requests\Concerns\RequiresReason;
use Illuminate\Foundation\Http\FormRequest;

/** Deleting a vendor payment needs a reason for the audit log (spec 4.4). */
class DeleteVendorPaymentRequest extends FormRequest
{
    use RequiresReason;

    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('vendor_payment'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->reasonRules();
    }
}
