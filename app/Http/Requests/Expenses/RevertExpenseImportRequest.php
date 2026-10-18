<?php

namespace App\Http\Requests\Expenses;

use App\Http\Requests\Concerns\RequiresReason;
use Illuminate\Foundation\Http\FormRequest;

/** Reverting an import deletes its expenses: needs import and delete rights, and a reason for the audit log (spec 7.2, 4.4). */
class RevertExpenseImportRequest extends FormRequest
{
    use RequiresReason;

    public function authorize(): bool
    {
        return $this->user()->can('expenses.import') && $this->user()->can('expenses.delete');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->reasonRules();
    }
}
