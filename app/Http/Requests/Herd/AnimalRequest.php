<?php

namespace App\Http\Requests\Herd;

use App\Enums\AnimalGender;
use App\Enums\AnimalSource;
use App\Enums\AnimalType;
use App\Enums\LifecycleStage;
use App\Enums\OwnershipType;
use App\Http\Requests\Concerns\RequiresReason;
use App\Models\Animal;
use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add / edit an animal (spec Section 6, Phase 2). Tag unique per farm (soft-deleted animals included), breed of the
 * same type, mother a female of the same type, males only calf or bull. Status is not edited here — it changes
 * through the status actions (Task 2.3). Editing is audit-logged and needs a reason.
 */
class AnimalRequest extends FormRequest
{
    use RequiresReason;

    public function authorize(): bool
    {
        return $this->user()->can('animals.manage');
    }

    protected function prepareForValidation(): void
    {
        $clean = fn ($v) => ($v = str_replace([',', ' ', '₹'], '', trim((string) $v))) === '' ? null : $v;
        $male = $this->input('gender') === AnimalGender::Male->value;

        $this->merge([
            'tag_no' => trim(preg_replace('/\s+/u', ' ', (string) $this->input('tag_no')) ?? ''),
            'name' => trim((string) $this->input('name')) ?: null,
            'breed_id' => $this->input('breed_id') ?: null,
            'mother_id' => $this->input('mother_id') ?: null,
            'dob_is_estimated' => $this->boolean('dob_is_estimated'),
            // opening value only for animals that were already on the farm at go-live
            'opening_value' => $this->input('source') === AnimalSource::Opening->value ? $clean($this->input('opening_value')) : null,
            'lactation_number' => $male ? 0 : ($this->input('lactation_number') === null || $this->input('lactation_number') === '' ? 0 : $this->input('lactation_number')),
            'remove_photo' => $this->boolean('remove_photo'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $farmId = app(CurrentFarm::class)->id();
        $animal = $this->animal();
        $type = (string) $this->input('animal_type');
        $gender = AnimalGender::tryFrom((string) $this->input('gender'));

        return ($animal ? $this->reasonRules() : []) + [
            'tag_no' => ['required', 'string', 'max:30',
                Rule::unique('animals', 'tag_no')->where('farm_id', $farmId)->ignore($animal?->id)],
            'name' => ['nullable', 'string', 'max:60'],
            'animal_type' => ['required', Rule::enum(AnimalType::class)],
            'breed_id' => ['nullable', 'integer', Rule::exists('animal_breeds', 'id')
                ->where('farm_id', $farmId)
                ->where('animal_type', $type)
                ->where(fn ($q) => $q->where('is_active', true)->when($animal?->breed_id, fn ($q, $id) => $q->orWhere('id', $id)))],
            'gender' => ['required', Rule::enum(AnimalGender::class)],
            'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'dob_is_estimated' => ['boolean'],
            'source' => ['required', Rule::enum(AnimalSource::class)],
            'mother_id' => ['nullable', 'integer', Rule::exists('animals', 'id')
                ->where('farm_id', $farmId)
                ->where('gender', AnimalGender::Female->value)
                ->where('animal_type', $type)
                ->whereNull('deleted_at')
                ->when($animal, fn ($rule) => $rule->whereNot('id', $animal->id))],
            'sire_info' => ['nullable', 'string', 'max:150'],
            'ownership_type' => ['required', Rule::enum(OwnershipType::class)],
            'lifecycle_stage' => ['required', Rule::enum(LifecycleStage::class)
                ->only($gender ? LifecycleStage::forGender($gender) : LifecycleStage::cases())],
            'opening_value' => ['nullable', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'color_marks' => ['nullable', 'string', 'max:100'],
            'lactation_number' => ['integer', 'min:0', 'max:30'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_photo' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tag_no.unique' => __('This tag number is already used in this farm (also by deleted animals).'),
            'breed_id.exists' => __('Choose an active breed of the same animal type.'),
            'mother_id.exists' => __('The mother must be a female animal of the same type.'),
            'lifecycle_stage.enum' => $this->input('gender') === AnimalGender::Male->value
                ? __('A male animal can only be a calf or a bull.')
                : __('A female animal cannot be a bull.'),
            'date_of_birth.before_or_equal' => __('The date of birth cannot be in the future.'),
            'photo.image' => __('The photo must be a JPG, PNG or WebP image.'),
            'photo.max' => __('The photo may not be larger than 5 MB.'),
        ];
    }

    public function animal(): ?Animal
    {
        $animal = $this->route('animal');

        return $animal instanceof Animal ? $animal : null;
    }

    /**
     * Validated attributes for the model (no photo / reason fields).
     *
     * @return array<string, mixed>
     */
    public function animalData(): array
    {
        return collect($this->validated())->except(['photo', 'remove_photo', 'reason'])->all();
    }
}
