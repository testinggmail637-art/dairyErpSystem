<?php

namespace App\Http\Requests\Herd;

use App\Enums\AnimalGender;
use App\Enums\AnimalStatus;
use App\Enums\CalvingOutcome;
use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Record a calving: the mother, date, outcome; an alive calf needs its gender and a new tag number. */
class CalvingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('breeding.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'calf_tag_no' => trim(preg_replace('/\s+/u', ' ', (string) $this->input('calf_tag_no')) ?? '') ?: null,
            'calf_name' => trim((string) $this->input('calf_name')) ?: null,
            'calf_gender' => $this->input('calf_gender') ?: null,
            'notes' => trim((string) $this->input('notes')) ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $farmId = app(CurrentFarm::class)->id();
        $alive = $this->input('outcome') === CalvingOutcome::Alive->value;

        return [
            'mother_id' => ['required', 'integer', Rule::exists('animals', 'id')->where('farm_id', $farmId)
                ->where('gender', AnimalGender::Female->value)->where('status', AnimalStatus::Active->value)->whereNull('deleted_at'),
                Rule::unique('calvings', 'mother_id')->where('calving_date', $this->input('calving_date'))],
            'calving_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'outcome' => ['required', Rule::enum(CalvingOutcome::class)],
            'calf_gender' => [Rule::requiredIf($alive), 'nullable', Rule::enum(AnimalGender::class)],
            'calf_tag_no' => [Rule::requiredIf($alive), 'nullable', 'string', 'max:30', Rule::unique('animals', 'tag_no')->where('farm_id', $farmId)],
            'calf_name' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mother_id.exists' => __('Choose an active female animal of this farm.'),
            'mother_id.unique' => __('A calving of this animal on this date is already recorded. Add a twin as a new animal with this mother.'),
            'calving_date.before_or_equal' => __('The date cannot be in the future.'),
            'calf_gender.required' => __('Choose the calf\'s gender.'),
            'calf_tag_no.required' => __('Give the calf a tag number.'),
            'calf_tag_no.unique' => __('This tag number is already used (also by deleted animals).'),
        ];
    }
}
