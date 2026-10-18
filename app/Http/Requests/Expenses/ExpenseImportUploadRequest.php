<?php

namespace App\Http\Requests\Expenses;

use Illuminate\Foundation\Http\FormRequest;

/** Upload a filled expense sheet (spec 7.2): xlsx / xls / csv, at most 5 MB. Row limit is checked while reading. */
class ExpenseImportUploadRequest extends FormRequest
{
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
            'file' => ['required', 'file', 'extensions:xlsx,xls,csv', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => __('Choose the filled Excel file.'),
            'file.extensions' => __('Upload an xlsx, xls or csv file.'),
            'file.max' => __('The file may not be larger than 5 MB.'),
        ];
    }
}
