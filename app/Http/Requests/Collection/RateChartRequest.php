<?php

namespace App\Http\Requests\Collection;

use App\Enums\MilkType;
use App\Enums\RateMethod;
use App\Http\Requests\Concerns\RequiresReason;
use App\Models\RateChart;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Add / edit a rate chart (spec Phase 4). The method decides which rates are needed; a slab chart needs non-overlapping
 * rows. Only one default chart per milk type may be in force on any date. Charts are audit-logged: edits need a reason.
 */
class RateChartRequest extends FormRequest
{
    use RequiresReason;

    private const POINT = ['nullable', 'decimal:0,2', 'between:0,20'];

    public function authorize(): bool
    {
        return $this->user()->can('rate_charts.manage');
    }

    protected function prepareForValidation(): void
    {
        $clean = fn ($v) => ($v = str_replace([',', ' ', '₹'], '', trim((string) $v))) === '' ? null : $v;

        $slabs = collect((array) $this->input('slabs', []))
            ->map(fn ($row) => array_map($clean, array_intersect_key((array) $row, array_flip(['fat_from', 'fat_to', 'snf_from', 'snf_to', 'rate_per_litre']))))
            ->reject(fn ($row) => array_filter($row, fn ($v) => $v !== null) === []) // drop empty rows
            ->values()->all();

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'fixed_rate' => $clean($this->input('fixed_rate')),
            'fat_rate' => $clean($this->input('fat_rate')),
            'snf_rate' => $clean($this->input('snf_rate')),
            'min_fat' => $clean($this->input('min_fat')),
            'min_snf' => $clean($this->input('min_snf')),
            'effective_to' => $this->input('effective_to') ?: null,
            'is_default' => $this->boolean('is_default'),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
            'slabs' => $slabs,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $method = RateMethod::tryFrom((string) $this->input('method'));
        $rate = ['decimal:0,2', 'gt:0', 'max:99999999.99'];
        $slab = $method === RateMethod::Slab;

        return ($this->chart() ? $this->reasonRules() : []) + [
            'name' => ['required', 'string', 'max:100'],
            'milk_type' => ['required', Rule::enum(MilkType::class)],
            'method' => ['required', Rule::enum(RateMethod::class)],
            'fixed_rate' => [$method === RateMethod::Fixed ? 'required' : 'nullable', ...$rate],
            'fat_rate' => [in_array($method, [RateMethod::FatRate, RateMethod::FatSnfRate], true) ? 'required' : 'nullable', ...$rate],
            'snf_rate' => [$method === RateMethod::FatSnfRate ? 'required' : 'nullable', ...$rate],
            'min_fat' => self::POINT,
            'min_snf' => self::POINT,
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'slabs' => [$slab ? 'required' : 'nullable', 'array', 'max:200'],
            'slabs.*.fat_from' => [$slab ? 'required' : 'nullable', ...array_slice(self::POINT, 1)],
            'slabs.*.fat_to' => [$slab ? 'required' : 'nullable', ...array_slice(self::POINT, 1), 'gte:slabs.*.fat_from'],
            'slabs.*.snf_from' => ['nullable', 'required_with:slabs.*.snf_to', ...array_slice(self::POINT, 1)],
            'slabs.*.snf_to' => ['nullable', 'required_with:slabs.*.snf_from', ...array_slice(self::POINT, 1), 'gte:slabs.*.snf_from'],
            'slabs.*.rate_per_litre' => [$slab ? 'required' : 'nullable', ...$rate],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if ($this->input('method') === RateMethod::Slab->value && ($clash = $this->overlappingSlabs())) {
                $validator->errors()->add('slabs', __('Rows :a and :b overlap. Each fat / SNF value must fall in one row only (touching ends are fine).', $clash));
            }
            if ($this->boolean('is_default') && $this->boolean('is_active') && ($other = $this->otherDefault())) {
                $validator->errors()->add('is_default', __('":name" is already the default :milk chart for part of this period. End it first (date to) or untick default.',
                    ['name' => $other->name, 'milk' => mb_strtolower($other->milk_type->label())]));
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slabs.required' => __('Add at least one slab row.'),
            'slabs.*.fat_to.gte' => __('"Fat to" must not be below "fat from".'),
            'slabs.*.snf_to.gte' => __('"SNF to" must not be below "SNF from".'),
            'effective_to.after_or_equal' => __('"Valid to" cannot be before "valid from".'),
        ];
    }

    public function chart(): ?RateChart
    {
        $chart = $this->route('rate_chart');

        return $chart instanceof RateChart ? $chart : null;
    }

    /** @return array{a: int, b: int}|null row numbers (1-based) of the first two overlapping slabs */
    private function overlappingSlabs(): ?array
    {
        $rows = $this->input('slabs');
        // ranges overlap when each starts before the other ends; touching ends (3.5 – 4.0 and 4.0 – 4.5) do not count
        $overlap = fn ($af, $at, $bf, $bt) => Money::compare($af, $bt) < 0 && Money::compare($bf, $at) < 0;
        $noSnf = ['0', '99'];  // a row without SNF covers every SNF

        foreach ($rows as $i => $a) {
            foreach (array_slice($rows, $i + 1, null, true) as $j => $b) {
                [$asf, $ast] = ($a['snf_from'] ?? null) === null ? $noSnf : [$a['snf_from'], $a['snf_to']];
                [$bsf, $bst] = ($b['snf_from'] ?? null) === null ? $noSnf : [$b['snf_from'], $b['snf_to']];
                if ($overlap($a['fat_from'], $a['fat_to'], $b['fat_from'], $b['fat_to']) && $overlap($asf, $ast, $bsf, $bst)) {
                    return ['a' => $i + 1, 'b' => $j + 1];
                }
            }
        }

        return null;
    }

    private function otherDefault(): ?RateChart
    {
        $from = $this->input('effective_from');
        $to = $this->input('effective_to');

        return RateChart::query()->where('milk_type', $this->input('milk_type'))->where('is_default', true)->where('is_active', true)
            ->when($this->chart(), fn ($q, $chart) => $q->whereKeyNot($chart->id))
            ->when($to, fn ($q) => $q->where('effective_from', '<=', $to))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $from))
            ->first();
    }
}
