<?php

namespace App\Http\Requests\Staff;

use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Record an advance paid to a staff member (spec Phase 6). */
class StaffAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('salary.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => str_replace([',', ' ', '₹'], '', trim((string) $this->input('amount'))),
            'note' => trim((string) $this->input('note')) ?: null,
            'client_uuid' => $this->input('client_uuid') ?: null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $farmId = app(CurrentFarm::class)->id();

        return [
            'staff_member_id' => ['required', 'integer', Rule::exists('staff_members', 'id')->where('farm_id', $farmId)->where('is_active', true)],
            'advance_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'payment_mode_id' => ['required', 'integer', Rule::exists('payment_modes', 'id')->where('farm_id', $farmId)->where('is_active', true)],
            'note' => ['nullable', 'string', 'max:500'],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['staff_member_id.exists' => __('Choose an active staff member.')];
    }
}
