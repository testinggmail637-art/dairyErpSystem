@extends('layouts.app')

@section('title', __('Treatments'))

@section('page-actions')
    @if ($canManage)
        <a href="{{ route('health.treatments.create', array_filter(['animal_id' => $filters['animal_id']])) }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Add treatment') }}</a>
    @endif
@endsection

@section('content')
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('health.treatments.index') }}" class="row g-2 align-items-end" data-allow-resubmit data-ajax-list="#treatment-list">
            <div class="col-6 col-md-2">
                <x-form.date name="from" :label="__('From')" :value="$filters['from']" max="today" group-class="mb-0" class="form-control-sm" />
            </div>
            <div class="col-6 col-md-2">
                <x-form.date name="to" :label="__('To')" :value="$filters['to']" max="today" group-class="mb-0" class="form-control-sm" />
            </div>
            <div class="col-12 col-md-4">
                <x-form.select name="animal_id" id="filter_animal_id" :label="__('Animal')" :options="$selectedAnimal" :selected="$filters['animal_id']"
                               :placeholder="__('All')" select2 :data-ajax-url="route('animals.search')" group-class="mb-0" />
            </div>
            <div class="col-8 col-md-2">
                <x-form.switch name="withdrawal" :label="__('Under milk withdrawal now')" :checked="$filters['withdrawal'] === '1'" group-class="mb-0" />
            </div>
            <div class="col-4 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1"><i class="bi bi-funnel"></i></button>
                <a href="{{ route('health.treatments.index') }}" class="btn btn-link btn-sm" data-ajax-clear>{{ __('Clear') }}</a>
            </div>
        </form>
    </div>

    <div id="treatment-list" data-ajax-results>
    @fragment('results')
    <div class="card-body border-bottom small d-flex gap-4">
        <div>{{ __('Treatments') }}: <span class="fw-semibold">{{ $count }}</span></div>
        <div>{{ __('Cost') }}: <span class="fw-semibold"><x-money :value="$total" /></span></div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th><th>{{ __('Animal') }}</th><th>{{ __('Diagnosis') }}</th><th>{{ __('Medicine / dose') }}</th>
                    <th>{{ __('Vet') }}</th><th class="text-end">{{ __('Cost') }}</th><th>{{ __('Milk withdrawal') }}</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($treatments as $t)
                    <tr>
                        <td class="text-nowrap">{{ fmt_date($t->treatment_date) }}</td>
                        <td><a href="{{ route('animals.show', $t->animal_id) }}">{{ $t->animal->label() }}</a></td>
                        <td>{{ $t->diagnosis }}</td>
                        <td class="small">{{ $t->medicine }}@if ($t->dose) · {{ $t->dose }}@endif</td>
                        <td class="small">{{ $t->vet_name }}</td>
                        <td class="text-end text-nowrap">
                            {{ inr($t->cost) }}
                            @if ($t->expense_id)
                                <a href="{{ route('expenses.show', $t->expense_id) }}" class="ms-1" title="{{ __('Recorded as expense') }}" aria-label="{{ __('Recorded as expense') }}"><i class="bi bi-receipt"></i></a>
                            @endif
                        </td>
                        <td class="text-nowrap">
                            @if ($t->milk_withdrawal_until)
                                <span @class(['badge', 'text-bg-warning' => $t->isUnderWithdrawal(), 'text-bg-light' => ! $t->isUnderWithdrawal()])>{{ __('until :date', ['date' => fmt_date($t->milk_withdrawal_until)]) }}</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @if ($canManage)
                                <a href="{{ route('health.treatments.edit', $t) }}" class="btn btn-link btn-sm p-0" aria-label="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                                <x-confirm-form :action="route('health.treatments.destroy', $t)" reason
                                                :message="$t->expense_id ? __('Delete this treatment and its expense of :amount?', ['amount' => inr($t->cost)]) : __('Delete this treatment?')" />
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-body-secondary py-4">{{ __('No treatments found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($treatments->hasPages())<div class="card-footer">{{ $treatments->links() }}</div>@endif
    @endfragment
    </div>
</div>
@endsection
