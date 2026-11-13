<?php

namespace App\Http\Requests\Herd;

use App\Support\AuditReason;
use Illuminate\Foundation\Http\FormRequest;

/** Dry off / mark as heifer (herd quick actions): the date it happened, an optional note for the history. */
class QuickStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('breeding.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['changed_on' => $this->input('changed_on') ?: today()->toDateString()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'changed_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function passedValidation(): void
    {
        app(AuditReason::class)->set($this->input('reason'));
    }
}
