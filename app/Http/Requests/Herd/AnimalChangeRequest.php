<?php

namespace App\Http\Requests\Herd;

use App\Enums\AnimalStatus;
use App\Enums\LifecycleStage;
use App\Enums\OwnershipType;
use App\Http\Requests\Concerns\RequiresReason;
use App\Models\Animal;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Change one of status / lifecycle_stage / ownership_type with the date it happened and a reason (Task 2.3).
 * "Sold" is not offered — it is set (and undone) by the animal sale screens of Phase 6.
 */
class AnimalChangeRequest extends FormRequest
{
    use RequiresReason;

    public function authorize(): bool
    {
        return $this->user()->can('animals.manage');
    }

    /** Statuses that can be chosen by hand. */
    public static function manualStatuses(): array
    {
        return [AnimalStatus::Active, AnimalStatus::Deceased, AnimalStatus::Culled, AnimalStatus::Transferred];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $animal = $this->animal();
        $field = (string) $this->input('field');
        $current = $animal->getAttribute($field);
        $current = $current instanceof \BackedEnum ? $current->value : $current;

        $choices = match ($field) {
            'status' => self::manualStatuses(),
            'lifecycle_stage' => LifecycleStage::forGender($animal->gender),
            'ownership_type' => OwnershipType::cases(),
            default => [],
        };

        return $this->reasonRules() + [
            'field' => ['required', Rule::in(['status', 'lifecycle_stage', 'ownership_type'])],
            'value' => ['required', 'string',
                Rule::in(array_map(fn ($c) => $c->value, $choices)),
                Rule::notIn([$current]),
                function (string $attribute, mixed $value, Closure $fail) use ($field, $animal) {
                    if ($field === 'status' && $value === AnimalStatus::Active->value && $animal->status === AnimalStatus::Sold) {
                        $fail(__('A sold animal becomes active again only by cancelling its sale.'));
                    }
                }],
            'changed_on' => array_filter(['required', 'date_format:Y-m-d', 'before_or_equal:today',
                $animal->date_of_birth ? 'after_or_equal:'.$animal->date_of_birth->toDateString() : null]),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'value.not_in' => __('The animal already has this value.'),
            'value.in' => $this->input('field') === 'lifecycle_stage'
                ? __('This stage does not fit the animal (males are only calf or bull).')
                : __('Choose one of the listed values.'),
            'changed_on.before_or_equal' => __('The date cannot be in the future.'),
            'changed_on.after_or_equal' => __('The date cannot be before the animal was born.'),
        ];
    }

    public function animal(): Animal
    {
        return $this->route('animal');
    }
}
