<?php

namespace App\Http\Requests\Herd;

use App\Enums\AnimalType;
use App\Models\AnimalBreed;
use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Add / edit a breed: type + name, unique per farm and type (capital letters do not matter). */
class AnimalBreedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('animals.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim(preg_replace('/\s+/u', ' ', (string) $this->input('name')) ?? ''),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'animal_type' => ['required', Rule::enum(AnimalType::class)],
            'name' => ['required', 'string', 'max:60',
                Rule::unique('animal_breeds', 'name')
                    ->where('farm_id', app(CurrentFarm::class)->id())
                    ->where('animal_type', (string) $this->input('animal_type'))
                    ->ignore($this->breed()?->id)],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => __('This breed already exists for this animal type.'),
        ];
    }

    public function breed(): ?AnimalBreed
    {
        $breed = $this->route('animal_breed');

        return $breed instanceof AnimalBreed ? $breed : null;
    }
}
