<?php

namespace App\Http\Requests\Expenses;

use App\Http\Requests\Concerns\RequiresReason;
use Illuminate\Foundation\Http\FormRequest;

/** Deleting an expense needs a reason for the audit log (spec 4.4); who may delete is the ExpensePolicy's job. */
class DeleteExpenseRequest extends FormRequest
{
    use RequiresReason;

    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('expense'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->reasonRules();
    }
}
