<?php

namespace App\Http\Requests\Trading;

use App\Http\Requests\Concerns\RequiresReason;
use Illuminate\Foundation\Http\FormRequest;

/** Cancelling a sale needs a reason (spec Phase 6); it is kept on the sale and in the audit log. */
class CancelAnimalSaleRequest extends FormRequest
{
    use RequiresReason;

    public function authorize(): bool
    {
        return $this->user()->can('animal_trading.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->reasonRules();
    }
}
