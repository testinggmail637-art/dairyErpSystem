{{-- One-line summary of a chart's rates (list and detail page). --}}
@switch($chart->method)
    @case(\App\Enums\RateMethod::Fixed)
        {{ __(':rate / L', ['rate' => inr($chart->fixed_rate)]) }}
        @break
    @case(\App\Enums\RateMethod::FatRate)
        {{ __('fat × :rate', ['rate' => inr($chart->fat_rate)]) }}
        @break
    @case(\App\Enums\RateMethod::FatSnfRate)
        {{ __('fat × :fat + SNF × :snf', ['fat' => inr($chart->fat_rate), 'snf' => inr($chart->snf_rate)]) }}
        @break
    @default
        {{ __('slab table') }}
@endswitch
@if ($chart->min_fat || $chart->min_snf)
    <div class="text-body-secondary">{{ __('min fat :fat · SNF :snf', ['fat' => $chart->min_fat ?? '—', 'snf' => $chart->min_snf ?? '—']) }}</div>
@endif
