@extends('layouts.app')

@section('title', __('Milk entries'))

@section('page-actions')
    <a href="{{ route('milk-production.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-grid-3x3 me-1"></i>{{ __('Entry grid') }}</a>
    @can('create', \App\Models\MilkProductionRecord::class)
        <a href="{{ route('milk-production.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Single entry') }}</a>
    @endcan
@endsection

@section('content')
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('milk-production.entries') }}" class="row g-2 align-items-end" data-allow-resubmit data-ajax-list="#milk-entries">
            <div class="col-6 col-md-3 col-xl-2">
                <x-form.date name="from" :label="__('From')" :value="$filters['from']" max="today" group-class="mb-0" class="form-control-sm" />
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <x-form.date name="to" :label="__('To')" :value="$filters['to']" max="today" group-class="mb-0" class="form-control-sm" />
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <x-form.select name="shift" :label="__('Shift')" :options="\App\Enums\MilkShift::options()" :selected="$filters['shift']"
                               :placeholder="__('Both')" group-class="mb-0" class="form-select-sm" />
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <x-form.select name="type" :label="__('Milk type')" :options="\App\Enums\AnimalType::options()" :selected="$filters['type']"
                               :placeholder="__('All')" group-class="mb-0" class="form-select-sm" />
            </div>
            <div class="col-12 col-md-6 col-xl-2">
                <x-form.select name="animal_id" :label="__('Animal')" :options="$selectedAnimal" :selected="$filters['animal_id']"
                               :placeholder="__('All')" select2 :data-ajax-url="route('animals.search')" group-class="mb-0" />
            </div>
            <div class="col-12 col-md-6 col-xl-2 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>{{ __('Filter') }}</button>
                <a href="{{ route('milk-production.entries') }}" class="btn btn-link btn-sm" data-ajax-clear>{{ __('Clear') }}</a>
            </div>
        </form>
    </div>

    <div id="milk-entries" data-ajax-results>
    @fragment('results')
    {{-- Litres of the whole filtered set (one SQL query), not only this page --}}
    <div class="card-body border-bottom d-flex flex-wrap gap-4 small">
        @foreach (\App\Enums\AnimalType::cases() as $type)
            @php $t = $totals->get($type->value); @endphp
            <div>{{ $type->label() }}: <span class="fw-semibold">{{ fmt_qty($t->litres ?? 0) }} L</span>
                <span class="text-body-secondary">({{ $t->entries ?? 0 }})</span></div>
        @endforeach
        <div>{{ __('Total') }}: <span class="fw-semibold">{{ fmt_qty($grandTotal) }} L</span></div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Shift') }}</th>
                    <th>{{ __('Animal') }}</th>
                    <th class="text-end">{{ __('Litres') }}</th>
                    <th class="text-end d-none d-md-table-cell">{{ __('Fat / SNF') }}</th>
                    <th class="d-none d-lg-table-cell">{{ __('Entered by') }}</th>
                    <th class="text-end">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr>
                        <td class="text-nowrap">{{ fmt_date($record->production_date) }}</td>
                        <td>{{ $record->shift->label() }}</td>
                        <td>
                            {{ $record->animal?->label() }}
                            <div class="small text-body-secondary">{{ $record->animal?->animal_type->label() }}</div>
                        </td>
                        <td class="text-end fw-semibold">{{ fmt_qty($record->quantity_litre) }}</td>
                        <td class="text-end d-none d-md-table-cell small">
                            {{ $record->fat !== null ? fmt_qty($record->fat) : '—' }} / {{ $record->snf !== null ? fmt_qty($record->snf) : '—' }}
                        </td>
                        <td class="d-none d-lg-table-cell small">{{ $record->creator?->name ?? '—' }}</td>
                        <td class="text-end text-nowrap">
                            @can('update', $record)
                                <a href="{{ route('milk-production.edit', $record) }}" class="btn btn-outline-primary btn-sm" aria-label="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                                <x-confirm-form :action="route('milk-production.destroy', $record)" reason
                                                :message="__('Delete the :shift entry of :tag on :date?', ['shift' => strtolower($record->shift->label()), 'tag' => $record->animal?->tag_no, 'date' => fmt_date($record->production_date)])" />
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-body-secondary py-5">{{ __('No milk entries for these filters.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($records->hasPages())<div class="card-footer">{{ $records->links() }}</div>@endif
    @endfragment
    </div>
</div>
@endsection
