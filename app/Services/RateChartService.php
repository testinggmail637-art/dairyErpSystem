<?php

namespace App\Services;

use App\Enums\MilkType;
use App\Enums\RateMethod;
use App\Models\Farmer;
use App\Models\RateChart;
use App\Support\Money;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Rate charts for farmer milk (spec Phase 4). calculate() prices a litre from fat / SNF; resolve() picks the chart for
 * a collection: the farmer's own chart if set (active, in force on the date), else the default chart of that milk type.
 * Collections copy the rate (snapshot), so changing a chart never changes past amounts.
 */
class RateChartService
{
    /**
     * @return array{rate: string, below_standard: bool}
     *
     * @throws DomainException when fat / SNF needed by the chart is missing or no slab matches
     */
    public function calculate(RateChart $chart, ?string $fat, ?string $snf): array
    {
        $fat = filled($fat) ? Money::round2($fat) : null;
        $snf = filled($snf) ? Money::round2($snf) : null;

        if ($chart->method !== RateMethod::Fixed && $fat === null) {
            throw new DomainException(__('Enter the fat % — this rate chart needs it.'));
        }
        if ($chart->method === RateMethod::FatSnfRate && $snf === null) {
            throw new DomainException(__('Enter the SNF % — this rate chart needs it.'));
        }

        $rate = match ($chart->method) {
            RateMethod::Fixed => $chart->fixed_rate,
            RateMethod::FatRate => bcmul($fat, $chart->fat_rate, 4),
            // one rounding at the end, not per part
            RateMethod::FatSnfRate => bcadd(bcmul($fat, $chart->fat_rate, 4), bcmul($snf, $chart->snf_rate, 4), 4),
            RateMethod::Slab => $this->slabRate($chart, $fat, $snf),
        };

        $below = ($chart->min_fat !== null && $fat !== null && Money::compare($fat, $chart->min_fat) < 0)
            || ($chart->min_snf !== null && $snf !== null && Money::compare($snf, $chart->min_snf) < 0);

        return ['rate' => Money::round2($rate), 'below_standard' => $below];
    }

    /** The chart that prices this farmer's milk of this type on this date, or null when there is none. */
    public function resolve(?Farmer $farmer, MilkType $milkType, string $date): ?RateChart
    {
        $inForce = fn ($q) => $q->where('is_active', true)->where('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date));

        if ($farmer?->rate_chart_id) {
            $own = RateChart::query()->whereKey($farmer->rate_chart_id)->where('milk_type', $milkType)->where($inForce)->first();
            if ($own) {
                return $own;
            }
        }

        return RateChart::query()->where('milk_type', $milkType)->where('is_default', true)->where($inForce)
            ->latest('effective_from')->first();
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data): RateChart
    {
        return DB::transaction(function () use ($data) {
            $chart = RateChart::create($this->attributes($data));
            $this->saveSlabs($chart, $data);

            return $chart;
        });
    }

    /** @param  array<string, mixed>  $data */
    public function update(RateChart $chart, array $data): RateChart
    {
        return DB::transaction(function () use ($chart, $data) {
            $chart->update($this->attributes($data));
            $chart->slabs()->delete();
            $this->saveSlabs($chart, $data);

            return $chart;
        });
    }

    public function delete(RateChart $chart): void
    {
        if (Farmer::query()->where('rate_chart_id', $chart->id)->exists()) {
            throw new DomainException(__('Some farmers use this rate chart. Give them another chart first.'));
        }
        $chart->delete();
    }

    private function slabRate(RateChart $chart, string $fat, ?string $snf): string
    {
        foreach ($chart->slabs as $slab) {
            $fatOk = Money::compare($fat, $slab->fat_from) >= 0 && Money::compare($fat, $slab->fat_to) <= 0;
            $snfOk = $slab->snf_from === null
                || ($snf !== null && Money::compare($snf, $slab->snf_from) >= 0 && Money::compare($snf, $slab->snf_to) <= 0);
            if ($fatOk && $snfOk) {
                return $slab->rate_per_litre;
            }
        }

        throw new DomainException(__('No slab of this rate chart covers fat :fat %:snf.', [
            'fat' => $fat, 'snf' => $snf !== null ? __(' / SNF :snf %', ['snf' => $snf]) : '',
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $method = RateMethod::from($data['method']);
        $uses = fn (string $field) => match ($field) {
            'fixed_rate' => $method === RateMethod::Fixed,
            'fat_rate' => in_array($method, [RateMethod::FatRate, RateMethod::FatSnfRate], true),
            'snf_rate' => $method === RateMethod::FatSnfRate,
        };

        return [
            'name' => $data['name'],
            'milk_type' => $data['milk_type'],
            'method' => $method,
            'fixed_rate' => $uses('fixed_rate') ? Money::round2($data['fixed_rate']) : null,
            'fat_rate' => $uses('fat_rate') ? Money::round2($data['fat_rate']) : null,
            'snf_rate' => $uses('snf_rate') ? Money::round2($data['snf_rate']) : null,
            'min_fat' => filled($data['min_fat'] ?? null) ? Money::round2($data['min_fat']) : null,
            'min_snf' => filled($data['min_snf'] ?? null) ? Money::round2($data['min_snf']) : null,
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
            'is_default' => (bool) ($data['is_default'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function saveSlabs(RateChart $chart, array $data): void
    {
        if ($chart->method !== RateMethod::Slab) {
            return;
        }
        foreach ($data['slabs'] as $slab) {
            $chart->slabs()->create([
                'fat_from' => $slab['fat_from'], 'fat_to' => $slab['fat_to'],
                'snf_from' => filled($slab['snf_from'] ?? null) ? $slab['snf_from'] : null,
                'snf_to' => filled($slab['snf_to'] ?? null) ? $slab['snf_to'] : null,
                'rate_per_litre' => Money::round2($slab['rate_per_litre']),
            ]);
        }
        $chart->unsetRelation('slabs');
    }
}
