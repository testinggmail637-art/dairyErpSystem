{{--
    Milk drop alerts (spec 7.3) as a warning panel. $alerts from MilkDropAlertService::alerts().
    compact = the milk entry grid (one shift, no empty state).
--}}
@php $compact ??= false; @endphp

@if ($alerts->isNotEmpty())
    <div class="alert alert-warning mb-3" role="alert" data-milk-alerts>
        <div class="fw-semibold mb-1">
            <i class="bi bi-exclamation-triangle me-1"></i>{{ trans_choice(':count animal gave much less milk than usual|:count animals gave much less milk than usual', $alerts->count()) }}
        </div>
        <ul class="mb-1 ps-3">
            @foreach ($alerts as $alert)
                <li>
                    @can('animals.view')
                        <a href="{{ route('animals.show', ['animal' => $alert->animal_id, 'tab' => 'milk']) }}" class="alert-link">{{ $alert->animal?->label() }}</a>
                    @else
                        <span class="fw-semibold">{{ $alert->animal?->label() }}</span>
                    @endcan
                    @unless ($compact) · {{ $alert->shift->label() }} @endunless
                    — {{ __(':today L against an average of :average L (last :entries entries): down :drop %', [
                        'today' => fmt_qty($alert->today), 'average' => fmt_qty($alert->average), 'entries' => $alert->entries, 'drop' => $alert->drop,
                    ]) }}
                </li>
            @endforeach
        </ul>
        <div class="small">{{ __('Check health — this is a signal, not a diagnosis.') }}</div>
    </div>
@endif
