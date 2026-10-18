<?php

namespace App\Http\Requests\Expenses;

use Illuminate\Foundation\Http\FormRequest;

/** Row filter of the import preview: all / errors / warnings / valid. */
class ExpenseImportPreviewRequest extends FormRequest
{
    public const SHOW = ['errors', 'warnings', 'valid'];

    public function authorize(): bool
    {
        return $this->user()->can('expenses.import');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'show' => ['nullable', 'in:'.implode(',', self::SHOW)],
        ];
    }

    public function show(): string
    {
        return (string) $this->validated('show', '');
    }
}
