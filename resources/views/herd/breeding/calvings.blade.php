@extends('layouts.app')

@section('title', __('Calvings'))

@section('page-actions')
    <a href="{{ route('breeding.calvings.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Record calving') }}</a>
@endsection

@section('content')
@include('herd.breeding._tabs')
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('breeding.calvings.index') }}" class="row g-2 align-items-end" data-allow-resubmit data-ajax-list="#calving-list">
            <div class="col-6 col-md-3">
                <x-form.date name="from" :label="__('From')" :value="$filters['from']" max="today" group-class="mb-0" class="form-control-sm" />
            </div>
            <div class="col-6 col-md-3">
                <x-form.date name="to" :label="__('To')" :value="$filters['to']" max="today" group-class="mb-0" class="form-control-sm" />
            </div>
            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-funnel"></i></button>
            </div>
        </form>
    </div>

    <div id="calving-list" data-ajax-results>
    @fragment('results')
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead>
                <tr><th>{{ __('Date') }}</th><th>{{ __('Mother') }}</th><th>{{ __('Outcome') }}</th><th>{{ __('Calf') }}</th><th>{{ __('Notes') }}</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($calvings as $c)
                    <tr>
                        <td class="text-nowrap">{{ fmt_date($c->calving_date) }}</td>
                        <td><a href="{{ route('animals.show', $c->mother_id) }}">{{ $c->mother->label() }}</a></td>
                        <td>{{ $c->outcome->label() }}</td>
                        <td>
                            @if ($c->calf)
                                <a href="{{ route('animals.show', $c->calf_id) }}">{{ $c->calf->label() }}</a>
                            @endif
                            @if ($c->calf_gender)<span class="small text-body-secondary">{{ $c->calf_gender->label() }}</span>@endif
                        </td>
                        <td class="small">{{ $c->notes }}</td>
                        <td class="text-end">
                            <x-confirm-form :action="route('breeding.calvings.destroy', $c)" reason
                                            :message="__('Undo this calving? The lactation number goes back by one and the calf record is removed.')" />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-body-secondary py-4">{{ __('No calvings in this period.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($calvings->hasPages())<div class="card-footer">{{ $calvings->links() }}</div>@endif
    @endfragment
    </div>
</div>
@endsection
