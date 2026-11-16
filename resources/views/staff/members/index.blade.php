@extends('layouts.app')

@section('title', __('Staff'))

@php($canManage = auth()->user()->can('staff.manage'))
@php($filtered = $filters['search'] !== '' || $filters['status'] !== '')

@section('page-actions')
    @if ($canManage)
        <a href="{{ route('staff.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Add staff member') }}</a>
    @endif
@endsection

@section('content')
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('staff.index') }}" class="row g-2 align-items-end" data-allow-resubmit data-ajax-list="#staff-results">
            <div class="col-12 col-md-5">
                <label for="search" class="form-label small mb-1">{{ __('Search') }}</label>
                <input type="search" id="search" name="search" value="{{ $filters['search'] }}" class="form-control form-control-sm"
                       placeholder="{{ __('Name, designation or phone') }}">
            </div>
            <div class="col-6 col-md-3">
                <label for="status" class="form-label small mb-1">{{ __('Status') }}</label>
                <select id="status" name="status" class="form-select form-select-sm">
                    <option value="">{{ __('Active') }}</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>{{ __('Inactive') }}</option>
                    <option value="all" @selected($filters['status'] === 'all')>{{ __('All') }}</option>
                </select>
            </div>
            <div class="col-6 col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>{{ __('Filter') }}</button>
                <a href="{{ route('staff.index') }}" class="btn btn-link btn-sm" data-ajax-clear>{{ __('Clear') }}</a>
            </div>
        </form>
    </div>

    <div id="staff-results" data-ajax-results>
    @fragment('results')
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Designation') }}</th>
                    <th>{{ __('Phone') }}</th>
                    <th class="text-end">{{ __('Monthly salary') }}</th>
                    <th>{{ __('Joined') }}</th>
                    <th>{{ __('Status') }}</th>
                    @if ($canManage)<th class="text-end">{{ __('Actions') }}</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($staff as $member)
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ $member->name }}</span>
                            @if ($member->user)<div class="small text-body-secondary"><i class="bi bi-person-badge"></i> {{ $member->user->name }}</div>@endif
                        </td>
                        <td>{{ $member->designation }}</td>
                        <td class="text-nowrap">{{ $member->phone ?: '—' }}</td>
                        <td class="text-end"><x-money :value="$member->monthly_salary" /></td>
                        <td class="text-nowrap">{{ fmt_date($member->joining_date) }}</td>
                        <td>
                            @if ($member->is_active)
                                <span class="badge text-bg-success">{{ __('Active') }}</span>
                            @else
                                <span class="badge text-bg-secondary">{{ __('Inactive') }}</span>
                                @if ($member->leaving_date)<div class="small text-body-secondary">{{ __('Left :date', ['date' => fmt_date($member->leaving_date)]) }}</div>@endif
                            @endif
                        </td>
                        @if ($canManage)
                            <td class="text-end">
                                <a href="{{ route('staff.edit', $member) }}" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-pencil"></i> <span class="d-none d-sm-inline">{{ __('Edit') }}</span>
                                </a>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canManage ? 7 : 6 }}" class="text-center text-body-secondary py-5">
                            <i class="bi bi-people fs-2 d-block mb-2"></i>
                            {{ $filtered ? __('No staff members match these filters.') : __('No staff members yet.') }}
                            @if ($canManage && ! $filtered)
                                <div class="mt-2"><a href="{{ route('staff.create') }}" class="btn btn-primary btn-sm">{{ __('Add first staff member') }}</a></div>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($staff->hasPages())<div class="card-footer">{{ $staff->links() }}</div>@endif
    @endfragment
    </div>
</div>
@endsection
