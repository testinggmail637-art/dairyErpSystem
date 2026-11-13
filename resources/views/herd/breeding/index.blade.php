@extends('layouts.app')

@section('title', __('Breeding'))

@section('page-actions')
    <a href="{{ route('breeding.create', array_filter(['animal_id' => $filters['animal_id']])) }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Add event') }}</a>
@endsection

@section('content')
<div class="card mb-3">
    <div class="card-header fw-semibold"><i class="bi bi-calendar-heart me-1"></i>{{ __('Expected calvings') }}
        <span class="small fw-normal text-body-secondary">{{ __('up to 30 days late and the next 90 days') }}</span></div>
    @if ($expected->isEmpty())
        <div class="card-body text-body-secondary small">{{ __('No calvings expected.') }}</div>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>{{ __('Animal') }}</th><th>{{ __('Expected on') }}</th><th>{{ __('Based on') }}</th><th class="text-end">{{ __('Days') }}</th></tr></thead>
                <tbody>
                    @foreach ($expected as $row)
                        @php($days = (int) today()->diffInDays(\Illuminate\Support\Carbon::parse($row->expected_calving_date), false))
                        <tr>
                            <td><a href="{{ route('animals.show', $row->animal_id) }}">{{ $row->tag_no }}@if ($row->name) · {{ $row->name }}@endif</a></td>
                            <td>{{ fmt_date($row->expected_calving_date) }}</td>
                            <td class="small">{{ \App\Enums\BreedingEventType::from($row->event_type) === \App\Enums\BreedingEventType::PregnancyCheck ? __('Confirmed pregnant') : __('Service, not yet confirmed') }}</td>
                            <td class="text-end"><span @class(['badge', 'text-bg-danger' => $days < 0, 'text-bg-warning' => $days >= 0 && $days <= 15, 'text-bg-light' => $days > 15])>{{ $days < 0 ? __(':n late', ['n' => -$days]) : $days }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('breeding.index') }}" class="row g-2 align-items-end" data-allow-resubmit data-ajax-list="#breeding-list">
            <div class="col-6 col-md-2">
                <x-form.date name="from" :label="__('From')" :value="$filters['from']" max="today" group-class="mb-0" class="form-control-sm" />
            </div>
            <div class="col-6 col-md-2">
                <x-form.date name="to" :label="__('To')" :value="$filters['to']" max="today" group-class="mb-0" class="form-control-sm" />
            </div>
            <div class="col-6 col-md-3">
                <x-form.select name="event_type" id="filter_event_type" :label="__('Event')" :options="$types" :selected="$filters['event_type']"
                               :placeholder="__('All')" group-class="mb-0" class="form-select-sm" />
            </div>
            <div class="col-6 col-md-3">
                <x-form.select name="animal_id" id="filter_animal_id" :label="__('Animal')" :options="$selectedAnimal" :selected="$filters['animal_id']"
                               :placeholder="__('All')" select2 :data-ajax-url="route('animals.search', ['gender' => 'female'])" group-class="mb-0" />
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1"><i class="bi bi-funnel"></i></button>
                <a href="{{ route('breeding.index') }}" class="btn btn-link btn-sm" data-ajax-clear>{{ __('Clear') }}</a>
            </div>
        </form>
    </div>

    <div id="breeding-list" data-ajax-results>
    @fragment('results')
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th><th>{{ __('Animal') }}</th><th>{{ __('Event') }}</th><th>{{ __('Details') }}</th>
                    <th>{{ __('Expected calving') }}</th><th class="text-end">{{ __('Cost') }}</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($events as $e)
                    <tr>
                        <td class="text-nowrap">{{ fmt_date($e->event_date) }}</td>
                        <td><a href="{{ route('animals.show', $e->animal_id) }}">{{ $e->animal->label() }}</a></td>
                        <td>
                            {{ $e->event_type->label() }}
                            @if ($e->result)
                                <span @class(['badge', 'text-bg-success' => $e->result === \App\Enums\PregnancyResult::Positive, 'text-bg-secondary' => $e->result !== \App\Enums\PregnancyResult::Positive])>{{ $e->result->label() }}</span>
                            @endif
                        </td>
                        <td class="small">{{ collect([$e->sire_info, $e->technician, $e->notes])->filter()->implode(' · ') }}</td>
                        <td class="text-nowrap">{{ $e->expected_calving_date ? fmt_date($e->expected_calving_date) : '' }}</td>
                        <td class="text-end text-nowrap">
                            @unless (\App\Support\Money::isZero($e->cost)){{ inr($e->cost) }}@endunless
                            @if ($e->expense_id)
                                <a href="{{ route('expenses.show', $e->expense_id) }}" class="ms-1" title="{{ __('Recorded as expense') }}" aria-label="{{ __('Recorded as expense') }}"><i class="bi bi-receipt"></i></a>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('breeding.edit', $e) }}" class="btn btn-link btn-sm p-0" aria-label="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                            <x-confirm-form :action="route('breeding.destroy', $e)" reason :message="__('Delete this :event?', ['event' => $e->event_type->label()])" />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-body-secondary py-4">{{ __('No breeding events found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($events->hasPages())<div class="card-footer">{{ $events->links() }}</div>@endif
    @endfragment
    </div>
</div>
@endsection
