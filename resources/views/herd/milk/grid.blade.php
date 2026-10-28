@extends('layouts.app')

@section('title', __('Milk entry'))

@section('page-actions')
    <a href="{{ route('milk-production.entries') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-list-ul me-1"></i>{{ __('All entries') }}</a>
    @if ($canCreate)
        <a href="{{ route('milk-production.create', ['date' => $date, 'shift' => $shift->value]) }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>{{ __('Single entry') }}
        </a>
    @endif
@endsection

@section('content')
@php
    $prev = \Illuminate\Support\Carbon::parse($date)->subDay()->toDateString();
    $next = \Illuminate\Support\Carbon::parse($date)->addDay()->toDateString();
    $isToday = $date === today()->toDateString();
    $groups = $animals->groupBy(fn ($a) => $a->animal_type->value);
@endphp

{{-- Date + shift --}}
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-2 align-items-end">
        <form method="GET" action="{{ route('milk-production.index') }}" class="d-flex flex-wrap gap-2 align-items-end" id="milk-date-form">
            <input type="hidden" name="shift" value="{{ $shift->value }}">
            <a href="{{ route('milk-production.index', ['date' => $prev, 'shift' => $shift->value]) }}" class="btn btn-outline-secondary" aria-label="{{ __('Previous day') }}">
                <i class="bi bi-chevron-left"></i>
            </a>
            <div>
                <label for="date" class="form-label small mb-1">{{ __('Date') }}</label>
                <x-form.date name="date" :value="$date" max="today" group-class="mb-0" />
            </div>
            @unless ($isToday)
                <a href="{{ route('milk-production.index', ['date' => $next, 'shift' => $shift->value]) }}" class="btn btn-outline-secondary" aria-label="{{ __('Next day') }}">
                    <i class="bi bi-chevron-right"></i>
                </a>
            @endunless
        </form>
        <div class="btn-group" role="group" aria-label="{{ __('Shift') }}">
            @foreach (\App\Enums\MilkShift::cases() as $option)
                <a href="{{ route('milk-production.index', ['date' => $date, 'shift' => $option->value]) }}"
                   @class(['btn', 'btn-primary' => $option === $shift, 'btn-outline-primary' => $option !== $shift])
                   @if ($option === $shift) aria-current="true" @endif>
                    <i class="bi {{ $option === \App\Enums\MilkShift::Morning ? 'bi-sunrise' : 'bi-sunset' }} me-1"></i>{{ $option->label() }}
                </a>
            @endforeach
        </div>
        <div class="ms-auto text-end">
            <div class="small text-body-secondary">{{ __(':shift total', ['shift' => $shift->label()]) }}</div>
            <div class="fs-4 fw-semibold"><span data-milk-total="all">0.00</span> L</div>
        </div>
    </div>
</div>

<form method="POST" action="{{ route('milk-production.bulk') }}" id="milk-grid" autocomplete="off">
    @csrf
    <input type="hidden" name="production_date" value="{{ $date }}">
    <input type="hidden" name="shift" value="{{ $shift->value }}">

    @if ($errors->any())
        <div class="alert alert-danger">{{ __('Please check the highlighted rows.') }} @error('qty'){{ $message }}@enderror</div>
    @endif

    @forelse ($groups as $type => $rows)
        @php $typeEnum = \App\Enums\AnimalType::from($type); @endphp
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold">{{ $typeEnum === \App\Enums\AnimalType::Cow ? __('Cows') : __('Buffaloes') }} ({{ $rows->count() }})</span>
                <span class="fw-semibold"><span data-milk-total="{{ $type }}">0.00</span> L</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="small">
                        <tr>
                            <th>{{ __('Animal') }}</th>
                            <th class="d-none d-md-table-cell">{{ __('Last 3 :shift', ['shift' => strtolower($shift->label())]) }}</th>
                            <th class="text-end app-col-qty">{{ __('Litres') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $animal)
                            @php
                                $entry = $entries->get($animal->id);
                                $value = old('qty.'.$animal->id, $entry?->quantity_litre);
                                $hint = $hints[$animal->id] ?? [];
                            @endphp
                            <tr>
                                <td>
                                    <span class="fw-semibold">{{ $animal->tag_no }}</span>
                                    @if ($animal->name)<span class="text-body-secondary">· {{ $animal->name }}</span>@endif
                                    <div class="small text-body-secondary">
                                        {{ $animal->breed?->name ?? $typeEnum->label() }}
                                        @if ($animal->lifecycle_stage !== \App\Enums\LifecycleStage::Milking || $animal->status !== \App\Enums\AnimalStatus::Active)
                                            · <span class="text-warning-emphasis">{{ $animal->status !== \App\Enums\AnimalStatus::Active ? $animal->status->label() : $animal->lifecycle_stage->label() }}</span>
                                        @endif
                                    </div>
                                    @if ($hint)
                                        <div class="small text-body-secondary d-md-none">{{ collect($hint)->pluck('litres')->map(fn ($l) => fmt_qty($l))->implode(' · ') }}</div>
                                    @endif
                                </td>
                                <td class="d-none d-md-table-cell small text-body-secondary">
                                    @forelse ($hint as $h)
                                        <span class="me-2" title="{{ fmt_date($h['date']) }}">{{ fmt_qty($h['litres']) }}</span>
                                    @empty — @endforelse
                                </td>
                                <td class="text-end">
                                    <input type="text" inputmode="decimal" name="qty[{{ $animal->id }}]" value="{{ $value }}"
                                           class="form-control text-end app-input-qty @error('qty.'.$animal->id) is-invalid @enderror @if ($entry) app-input-saved @endif"
                                           data-milk-type="{{ $type }}" @if ($entry) data-saved="{{ $entry->quantity_litre }}" @endif
                                           pattern="\d{1,2}(\.\d{1,2})?" title="{{ __('Litres, e.g. 8.5') }}"
                                           aria-label="{{ __('Litres for :tag', ['tag' => $animal->tag_no]) }}" @disabled(! $canCreate)>
                                    @error('qty.'.$animal->id)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="card"><div class="card-body text-center text-body-secondary py-5">
            <i class="bi bi-droplet fs-2 d-block mb-2"></i>
            {{ __('No milking animals. Only active female animals with stage "Milking" appear here.') }}
            @can('animals.view')<div class="mt-2"><a href="{{ route('animals.index') }}">{{ __('Go to animals') }}</a></div>@endcan
        </div></div>
    @endforelse

    @if ($canCreate && $animals->isNotEmpty())
        <div class="card mb-3 @unless ($errors->has('reason')) d-none @endunless" data-reason-box>
            <div class="card-body pb-0">
                <x-form.textarea name="reason" :label="__('Reason for changing saved entries')" rows="2" maxlength="500"
                                 :placeholder="__('e.g. Wrong litres typed in the morning')"
                                 :help="__('Needed only when you change litres that were already saved (audit log).')" />
            </div>
        </div>
        <div class="app-sticky-actions d-flex flex-wrap gap-2 align-items-center">
            <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check2-all me-1"></i>{{ __('Save all') }}</button>
            <span class="small text-body-secondary">{{ __('Empty boxes are skipped. Boxes with a coloured border are already saved.') }}</span>
        </div>
    @endif
</form>
@endsection

@push('scripts')
    <script src="{{ asset_v('js/modules/milk-grid.js') }}"></script>
@endpush
