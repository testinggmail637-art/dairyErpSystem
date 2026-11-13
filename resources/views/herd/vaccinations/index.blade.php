@extends('layouts.app')

@section('title', __('Vaccinations'))

@section('page-actions')
    @if ($canManage)
        <a href="{{ route('health.vaccinations.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Give vaccine') }}</a>
    @endif
@endsection

@section('content')
@include('herd.health._tabs')
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('health.vaccinations.index') }}" class="row g-2 align-items-end" data-allow-resubmit data-ajax-list="#vaccination-list">
            <div class="col-6 col-md-2">
                <x-form.date name="from" :label="__('Given from')" :value="$filters['from']" max="today" group-class="mb-0" class="form-control-sm" />
            </div>
            <div class="col-6 col-md-2">
                <x-form.date name="to" :label="__('To')" :value="$filters['to']" max="today" group-class="mb-0" class="form-control-sm" />
            </div>
            <div class="col-6 col-md-2">
                <x-form.select name="vaccine_type_id" id="filter_vaccine" :label="__('Vaccine')" :options="$vaccineTypes" :selected="$filters['vaccine_type_id']"
                               :placeholder="__('All')" group-class="mb-0" class="form-select-sm" />
            </div>
            <div class="col-6 col-md-3">
                <x-form.select name="animal_id" id="filter_animal_id" :label="__('Animal')" :options="$selectedAnimal" :selected="$filters['animal_id']"
                               :placeholder="__('All')" select2 :data-ajax-url="route('animals.search')" group-class="mb-0" />
            </div>
            <div class="col-6 col-md-2">
                <x-form.select name="due" id="filter_due" :label="__('Next dose')" :options="['30' => __('Due within 30 days'), 'overdue' => __('Overdue')]"
                               :selected="$filters['due']" :placeholder="__('Any (by date given)')" group-class="mb-0" class="form-select-sm" />
            </div>
            <div class="col-6 col-md-1 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1"><i class="bi bi-funnel"></i></button>
                <a href="{{ route('health.vaccinations.index') }}" class="btn btn-link btn-sm" data-ajax-clear>{{ __('Clear') }}</a>
            </div>
        </form>
    </div>

    <div id="vaccination-list" data-ajax-results>
    @fragment('results')
    <div class="card-body border-bottom small d-flex gap-4">
        <div>{{ __('Vaccinations') }}: <span class="fw-semibold">{{ $count }}</span></div>
        <div>{{ __('Cost') }}: <span class="fw-semibold"><x-money :value="$total" /></span></div>
        @if ($filters['due'])<div class="text-body-secondary">{{ __('Latest dose of each animal and vaccine; any date given.') }}</div>@endif
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Given on') }}</th><th>{{ __('Animal') }}</th><th>{{ __('Vaccine') }}</th>
                    <th>{{ __('Next due') }}</th><th class="text-end">{{ __('Cost') }}</th><th>{{ __('Notes') }}</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($vaccinations as $v)
                    <tr>
                        <td class="text-nowrap">{{ fmt_date($v->given_on) }}</td>
                        <td><a href="{{ route('animals.show', $v->animal_id) }}">{{ $v->animal->label() }}</a></td>
                        <td>{{ $v->vaccineType->name }}</td>
                        <td class="text-nowrap">
                            @if ($v->next_due_on)
                                <span @class(['badge', 'text-bg-danger' => $v->isOverdue(), 'text-bg-light' => ! $v->isOverdue()])>{{ fmt_date($v->next_due_on) }}</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            {{ inr($v->cost) }}
                            @if ($v->expense_id)
                                <a href="{{ route('expenses.show', $v->expense_id) }}" class="ms-1" title="{{ __('Recorded as expense') }}" aria-label="{{ __('Recorded as expense') }}"><i class="bi bi-receipt"></i></a>
                            @endif
                        </td>
                        <td class="small">{{ $v->notes }}</td>
                        <td class="text-end text-nowrap">
                            @if ($canManage)
                                <a href="{{ route('health.vaccinations.edit', $v) }}" class="btn btn-link btn-sm p-0" aria-label="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                                <x-confirm-form :action="route('health.vaccinations.destroy', $v)" reason
                                                :message="$v->expense_id ? __('Delete this vaccination and its expense of :amount?', ['amount' => inr($v->cost)]) : __('Delete this vaccination?')" />
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-body-secondary py-4">{{ __('No vaccinations found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($vaccinations->hasPages())<div class="card-footer">{{ $vaccinations->links() }}</div>@endif
    @endfragment
    </div>
</div>
@endsection
