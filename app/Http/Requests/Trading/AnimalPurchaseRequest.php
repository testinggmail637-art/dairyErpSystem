<?php

namespace App\Http\Requests\Trading;

use App\Enums\AnimalGender;
use App\Enums\AnimalType;
use App\Enums\LifecycleStage;
use App\Enums\OwnershipType;
use App\Enums\PartyType;
use App\Models\AnimalBreed;
use App\Support\CurrentFarm;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** New animal purchase with its animals created inline (spec Phase 6). */
class AnimalPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('animal_trading.manage');
    }

    protected function prepareForValidation(): void
    {
        $clean = fn ($v) => ($v = str_replace([',', ' ', '₹'], '', trim((string) $v))) === '' ? null : $v;

        $rows = collect((array) $this->input('animals'))
            ->map(fn ($row) => is_array($row) ? $row : [])
            // a row the user left completely empty is ignored
            ->reject(fn ($row) => collect($row)->only(['tag_no', 'price'])->filter(fn ($v) => trim((string) $v) !== '')->isEmpty())
            ->map(fn ($row) => [
                'tag_no' => trim(preg_replace('/\s+/u', ' ', (string) ($row['tag_no'] ?? '')) ?? ''),
                'name' => trim((string) ($row['name'] ?? '')) ?: null,
                'animal_type' => $row['animal_type'] ?? null,
                'breed_id' => ($row['breed_id'] ?? null) ?: null,
                'gender' => $row['gender'] ?? null,
                'age_months' => $clean($row['age_months'] ?? null),
                'lifecycle_stage' => $row['lifecycle_stage'] ?? null,
                'ownership_type' => $row['ownership_type'] ?? null,
                'price' => $clean($row['price'] ?? null),
            ])
            ->values()
            ->all();

        $this->merge([
            'transport_cost' => $clean($this->input('transport_cost')) ?? '0',
            'commission' => $clean($this->input('commission')) ?? '0',
            'other_cost' => $clean($this->input('other_cost')) ?? '0',
            'animals' => $rows,
            'initial_payment' => $clean($this->input('initial_payment')),
            'initial_reference_no' => trim((string) $this->input('initial_reference_no')) ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $farmId = app(CurrentFarm::class)->id();
        $money = ['required', 'decimal:0,2', 'min:0', 'max:9999999999.99'];

        return [
            'party_id' => ['required', 'integer', Rule::exists('parties', 'id')
                ->where('farm_id', $farmId)->where('is_active', true)->whereNull('deleted_at')
                ->whereIn('party_type', [PartyType::Seller->value, PartyType::Both->value])],
            'purchase_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'transport_cost' => $money,
            'commission' => $money,
            'other_cost' => $money,
            'notes' => ['nullable', 'string', 'max:1000'],
            'animals' => ['required', 'array', 'min:1', 'max:50'],
            'animals.*.tag_no' => ['required', 'string', 'max:30', 'distinct:ignore_case',
                Rule::unique('animals', 'tag_no')->where('farm_id', $farmId)],
            'animals.*.name' => ['nullable', 'string', 'max:60'],
            'animals.*.animal_type' => ['required', Rule::enum(AnimalType::class)],
            'animals.*.breed_id' => ['nullable', 'integer', Rule::exists('animal_breeds', 'id')->where('farm_id', $farmId)->where('is_active', true)],
            'animals.*.gender' => ['required', Rule::enum(AnimalGender::class)],
            'animals.*.age_months' => ['nullable', 'integer', 'min:0', 'max:300'],
            'animals.*.lifecycle_stage' => ['required', Rule::enum(LifecycleStage::class)],
            'animals.*.ownership_type' => ['required', Rule::enum(OwnershipType::class)],
            'animals.*.price' => ['required', 'decimal:0,2', 'min:1', 'max:9999999999.99'],
            // optional first installment paid to the seller with the purchase
            'initial_payment' => ['nullable', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'initial_payment_mode_id' => ['nullable', 'required_with:initial_payment', 'integer',
                Rule::exists('payment_modes', 'id')->where('farm_id', $farmId)->where('is_active', true)],
            'initial_reference_no' => ['nullable', 'string', 'max:50'],
        ];
    }

    /** Per-row checks that depend on other fields of the same row: breed type and stage for the gender. */
    public function after(): array
    {
        return [function (Validator $validator) {
            $breeds = AnimalBreed::query()->pluck('animal_type', 'id');

            foreach ((array) $this->input('animals') as $i => $row) {
                $gender = AnimalGender::tryFrom((string) $row['gender']);

                if ($row['breed_id'] && isset($breeds[$row['breed_id']]) && $breeds[$row['breed_id']]->value !== $row['animal_type']) {
                    $validator->errors()->add("animals.{$i}.breed_id", __('Choose a breed of the same animal type.'));
                }

                if ($gender && ($stage = LifecycleStage::tryFrom((string) $row['lifecycle_stage']))
                    && ! in_array($stage, LifecycleStage::forGender($gender), true)) {
                    $validator->errors()->add("animals.{$i}.lifecycle_stage", __('This stage does not fit the chosen gender.'));
                }
            }

            if ($this->input('initial_payment') !== null && $validator->errors()->isEmpty()) {
                $total = Money::add(Money::sum(array_column((array) $this->input('animals'), 'price')),
                    $this->input('transport_cost'), $this->input('commission'), $this->input('other_cost'));

                if (Money::compare($this->input('initial_payment'), $total) > 0) {
                    $validator->errors()->add('initial_payment', __('The payment cannot be more than the purchase total.'));
                }
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'party_id.exists' => __('Choose an active party who sells animals.'),
            'animals.required' => __('Add at least one animal.'),
            'animals.*.tag_no.unique' => __('This tag number is already used in this farm (also by deleted animals).'),
            'animals.*.tag_no.distinct' => __('The same tag number is entered twice.'),
            'animals.*.price.min' => __('Enter the price of each animal.'),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'animals.*.tag_no' => __('tag number'),
            'animals.*.animal_type' => __('type'),
            'animals.*.breed_id' => __('breed'),
            'animals.*.gender' => __('gender'),
            'animals.*.age_months' => __('age (months)'),
            'animals.*.lifecycle_stage' => __('stage'),
            'animals.*.ownership_type' => __('ownership'),
            'animals.*.price' => __('price'),
        ];
    }
}
