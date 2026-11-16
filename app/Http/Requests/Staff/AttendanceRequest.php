<?php

namespace App\Http\Requests\Staff;

use App\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Save one day's attendance for all staff (spec Phase 6). */
class AttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('attendance.manage');
    }

    protected function prepareForValidation(): void
    {
        $rows = collect((array) $this->input('attendance'))
            ->map(fn ($row) => [
                'status' => (is_array($row) ? ($row['status'] ?? null) : null) ?: null,
                'note' => trim((string) (is_array($row) ? ($row['note'] ?? '') : '')) ?: null,
            ])
            ->all();

        $this->merge(['attendance' => $rows]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'attendance' => ['array', 'max:500'],
            'attendance.*.status' => ['nullable', Rule::enum(AttendanceStatus::class)],
            'attendance.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
