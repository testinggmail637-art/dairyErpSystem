@extends('layouts.app')

@section('title', __('Staff advances'))

@php($filtered = $filters['staff_member_id'] || $filters['from'] !== '' || $filters['to'] !== '')

@section('page-actions')
    <a href="{{ route('staff-advances.create', array_filter(['staff_member_id' => $filters['staff_member_id']])) }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>{{ __('Give advance') }}
    </a>
@endsection

@section('content')
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('staff-advances.index') }}" class="row g-2 align-items-end" data-allow-resubmit data-ajax-list="#advances-results">
            <div class="col-12 col-md-4">
                <x-form.select name="staff_member_id" :label="__('Staff member')" :options="$staffOptions" :selected="$filters['staff_member_id']"
                               :placeholder="__('All staff')" group-class="mb-0" class="form-select-sm" />
            </div>
            <div class="col-6 col-md-2">
                <x-form.date name="from" :label="__('From')" :value="$filters['from']" max="today" group-class="mb-0" class="form-control-sm" />
            </div>
            <div class="col-6 col-md-2">
                <x-form.date name="to" :label="__('To')" :value="$filters['to']" max="today" group-class="mb-0" class="form-control-sm" />
            </div>
            <div class="col-12 col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>{{ __('Filter') }}</button>
                <a href="{{ route('staff-advances.index') }}" class="btn btn-link btn-sm" data-ajax-clear>{{ __('Clear') }}</a>
            </div>
        </form>
    </div>

    <div id="advances-results" data-ajax-results>
    @fragment('results')
    <div class="card-body border-bottom small d-flex flex-wrap gap-4">
        <span>{{ __('Total in list') }}: <strong><x-money :value="$total" /></strong></span>
        @if ($outstanding !== null)
            <span>{{ __('Still to recover from salary') }}: <strong><x-money :value="$outstanding" /></strong></span>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Staff member') }}</th>
                    <th>{{ __('Mode') }}</th>
                    <th>{{ __('Note') }}</th>
                    <th class="text-end">{{ __('Amount') }}</th>
                    <th class="text-end">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($advances as $advance)
                    <tr>
                        <td class="text-nowrap">{{ fmt_date($advance->advance_date) }}</td>
                        <td>{{ $advance->staffMember->name }}</td>
                        <td>{{ $advance->paymentMode->name }}</td>
                        <td>{{ $advance->note ?: '—' }}</td>
                        <td class="text-end fw-semibold"><x-money :value="$advance->amount" /></td>
                        <td class="text-end">
                            <x-confirm-form :action="route('staff-advances.destroy', $advance)" reason
                                            :message="__('Delete this advance of :amount?', ['amount' => inr($advance->amount)])" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-body-secondary py-5">
                            <i class="bi bi-cash fs-2 d-block mb-2"></i>
                            {{ $filtered ? __('No advances match these filters.') : __('No advances given yet.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($advances->hasPages())<div class="card-footer">{{ $advances->links() }}</div>@endif
    @endfragment
    </div>
</div>
<p class="form-text mt-2">{{ __('Advances are recovered from salary in the salary run. They are not expenses.') }}</p>
@endsection
