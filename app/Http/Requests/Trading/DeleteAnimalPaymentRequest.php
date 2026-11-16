<?php

namespace App\Http\Requests\Trading;

use App\Http\Requests\Concerns\RequiresReason;
use Illuminate\Foundation\Http\FormRequest;

/** Deleting a party payment needs a reason for the audit log (spec 4.4). */
class DeleteAnimalPaymentRequest extends FormRequest
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
