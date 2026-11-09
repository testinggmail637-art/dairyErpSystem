@extends('layouts.app')

@section('title', __('Collection entry'))

@php
    $prev = \Illuminate\Support\Carbon::parse($date)->subDay()->toDateString();
    $next = \Illuminate\Support\Carbon::parse($date)->addDay()->toDateString();
    $isToday = $date === today()->toDateString();
@endphp

@section('content')
{{-- Date + shift --}}
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-2 align-items-end">
        <form method="GET" action="{{ route('collections.index') }}" class="d-flex flex-wrap gap-2 align-items-end">
            <input type="hidden" name="shift" value="{{ $shift->value }}">
            <a href="{{ route('collections.index', ['date' => $prev, 'shift' => $shift->value]) }}" class="btn btn-outline-secondary" aria-label="{{ __('Previous day') }}"><i class="bi bi-chevron-left"></i></a>
            <div>
                <label for="date" class="form-label small mb-1">{{ __('Date') }}</label>
                <x-form.date name="date" :value="$date" max="today" group-class="mb-0" onchange="this.form.submit()" />
            </div>
            @unless ($isToday)
                <a href="{{ route('collections.index', ['date' => $next, 'shift' => $shift->value]) }}" class="btn btn-outline-secondary" aria-label="{{ __('Next day') }}"><i class="bi bi-chevron-right"></i></a>
            @endunless
        </form>
        <div class="btn-group" role="group" aria-label="{{ __('Shift') }}">
            @foreach (\App\Enums\MilkShift::cases() as $option)
                <a href="{{ route('collections.index', ['date' => $date, 'shift' => $option->value]) }}"
                   @class(['btn', 'btn-primary' => $option === $shift, 'btn-outline-primary' => $option !== $shift]) @if ($option === $shift) aria-current="true" @endif>
                    <i class="bi {{ $option === \App\Enums\MilkShift::Morning ? 'bi-sunrise' : 'bi-sunset' }} me-1"></i>{{ $option->label() }}
                </a>
            @endforeach
        </div>
        <div class="ms-auto small text-body-secondary">{{ __('Keys: type the code → Enter → litres → Enter → fat → Enter → SNF → Enter saves.') }}</div>
    </div>
</div>

@if ($canCreate)
    <div class="card mb-3 border-primary">
        <div class="card-body">
            <form method="POST" action="{{ route('collections.store') }}" id="collection-form" autocomplete="off"
                  data-search-url="{{ route('farmers.search') }}" data-quote-url="{{ route('collections.quote') }}"
                  data-results-url="{{ route('collections.index', ['date' => $date, 'shift' => $shift->value]) }}"
                  data-texts='@json(['notFound' => __('No active farmer found.'), 'below' => __('below standard'), 'error' => __('Could not save. Check the entry.')])'>
                @csrf
                <input type="hidden" name="collection_date" value="{{ $date }}">
                <input type="hidden" name="shift" value="{{ $shift->value }}">
                <input type="hidden" name="farmer_id" value="{{ old('farmer_id') }}">
                <input type="hidden" name="client_uuid" value="{{ old('client_uuid') }}" data-client-uuid>
                <input type="hidden" name="accept_below" value="0">
                <input type="hidden" name="quality_status" value="accepted">

                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-3">
                        <label for="farmer_code" class="form-label">{{ __('Farmer code') }}</label>
                        <input type="text" id="farmer_code" class="form-control form-control-lg" autofocus
                               placeholder="{{ __('e.g. 7 or name') }}" data-next="#quantity_litre">
                        <div class="small mt-1" data-farmer-name aria-live="polite"></div>
                        <div class="list-group mt-1 d-none" data-farmer-choices></div>
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="milk_type" class="form-label">{{ __('Milk') }}</label>
                        <select id="milk_type" name="milk_type" class="form-select form-select-lg" tabindex="-1">
                            @foreach (\App\Enums\MilkType::options() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="quantity_litre" class="form-label">{{ __('Litres') }}</label>
                        <input type="text" id="quantity_litre" name="quantity_litre" inputmode="decimal" class="form-control form-control-lg" data-next="#fat">
                    </div>
                    <div class="col-4 col-md-1">
                        <label for="fat" class="form-label">{{ __('Fat %') }}</label>
                        <input type="text" id="fat" name="fat" inputmode="decimal" class="form-control form-control-lg" data-next="#snf">
                    </div>
                    <div class="col-4 col-md-1">
                        <label for="snf" class="form-label">{{ __('SNF %') }}</label>
                        <input type="text" id="snf" name="snf" inputmode="decimal" class="form-control form-control-lg" data-next="submit">
                    </div>
                    <div class="col-4 col-md-1">
                        <label for="clr" class="form-label">{{ __('CLR') }}</label>
                        <input type="text" id="clr" name="clr" inputmode="decimal" class="form-control form-control-lg" tabindex="-1">
                    </div>
                    <div class="col-12 col-md-2 text-md-end">
                        <div class="small text-body-secondary">{{ __('Rate · amount') }}</div>
                        <div class="fs-5 fw-semibold" data-quote="rate">—</div>
                        <div class="fs-4 fw-bold text-primary" data-quote="amount">—</div>
                    </div>
                </div>

                <div class="small text-body-secondary mt-1" data-quote="chart"></div>
                <div class="alert alert-danger py-2 mt-2 d-none" data-form-error role="alert"></div>
                <div class="alert alert-success py-2 mt-2 d-none" data-form-success role="status"></div>

                {{-- below standard: the operator decides --}}
                <div class="alert alert-warning py-2 mt-2 d-none" data-below role="alert">
                    <div data-below-text class="mb-2"></div>
                    <button type="button" class="btn btn-warning btn-sm" data-accept-below><i class="bi bi-check2 me-1"></i>{{ __('Accept at this rate') }}</button>
                    <button type="button" class="btn btn-outline-danger btn-sm" data-reject data-reason="{{ __('Below standard fat / SNF') }}"><i class="bi bi-x-lg me-1"></i>{{ __('Reject') }}</button>
                </div>

                {{-- reject for another reason (sour, adulterated …) --}}
                <div class="row g-2 mt-1 d-none" data-reject-box>
                    <div class="col-12 col-md-6">
                        <input type="text" name="rejection_reason" class="form-control" maxlength="255" placeholder="{{ __('Why is the milk rejected? e.g. sour, water added') }}">
                    </div>
                    <div class="col-12 col-md-3"><button type="button" class="btn btn-danger" data-confirm-reject>{{ __('Save as rejected') }}</button></div>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check2 me-1"></i>{{ __('Save') }} <span class="small opacity-75">(Enter)</span></button>
                    <button type="button" class="btn btn-outline-danger" data-show-reject><i class="bi bi-x-octagon me-1"></i>{{ __('Reject milk') }}</button>
                    <button type="reset" class="btn btn-link">{{ __('Clear') }} <span class="small">(Esc)</span></button>
                </div>
            </form>
        </div>
    </div>
@endif

<div class="card" id="collection-results" data-ajax-results>
    @fragment('results')
    <div class="card-header d-flex flex-wrap gap-3 align-items-center">
        <span class="fw-semibold">{{ __(':shift · :date', ['shift' => $shift->label(), 'date' => fmt_date($date)]) }}</span>
        <span>{{ __('Farmers') }} <strong>{{ $totals['count'] }}</strong></span>
        <span>{{ __('Litres') }} <strong>{{ fmt_qty($totals['litres']) }}</strong></span>
        <span>{{ __('Avg fat') }} <strong>{{ $totals['fat'] ?? '—' }}</strong></span>
        <span>{{ __('Avg SNF') }} <strong>{{ $totals['snf'] ?? '—' }}</strong></span>
        <span>{{ __('Amount') }} <strong><x-money :value="$totals['amount']" /></strong></span>
        @if (\App\Support\Money::compare($totals['rejected'], '0') > 0)
            <span class="text-danger">{{ __('Rejected :qty L', ['qty' => fmt_qty($totals['rejected'])]) }}</span>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Farmer') }}</th>
                    <th>{{ __('Milk') }}</th>
                    <th class="text-end">{{ __('Litres') }}</th>
                    <th class="text-end">{{ __('Fat') }}</th>
                    <th class="text-end">{{ __('SNF') }}</th>
                    <th class="text-end">{{ __('Rate') }}</th>
                    <th class="text-end">{{ __('Amount') }}</th>
                    <th>{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr @class(['table-danger' => ! $entry->isAccepted()])>
                        <td><span class="font-monospace small">{{ $entry->farmer->code }}</span> {{ $entry->farmer->name }}</td>
                        <td>{{ $entry->milk_type->label() }}</td>
                        <td class="text-end">{{ fmt_qty($entry->quantity_litre) }}</td>
                        <td class="text-end">{{ $entry->fat ?? '—' }}</td>
                        <td class="text-end">{{ $entry->snf ?? '—' }}</td>
                        <td class="text-end">{{ inr($entry->rate_per_litre) }}</td>
                        <td class="text-end fw-semibold">{{ inr($entry->amount) }}</td>
                        <td>
                            @if ($entry->isAccepted())
                                <span class="badge text-bg-success">{{ __('Accepted') }}</span>
                            @else
                                <span class="badge text-bg-danger" title="{{ $entry->rejection_reason }}">{{ __('Rejected') }}</span>
                                <div class="small text-body-secondary">{{ $entry->rejection_reason }}</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-body-secondary py-4">{{ __('No milk collected in this shift yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endfragment
</div>
@endsection

@push('scripts')
    <script src="{{ asset_v('js/modules/collection-entry.js') }}"></script>
@endpush
