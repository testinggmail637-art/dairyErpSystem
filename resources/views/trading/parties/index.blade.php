@extends('layouts.app')

@section('title', __('Parties'))

@php($canManage = auth()->user()->can('animal_trading.manage'))
@php($filtered = $filters['search'] !== '' || $filters['type'] !== '' || $filters['status'] !== '')

@section('page-actions')
    @if ($canManage)
        <a href="{{ route('parties.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Add party') }}</a>
    @endif
@endsection

@section('content')
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('parties.index') }}" class="row g-2 align-items-end" data-allow-resubmit data-ajax-list="#parties-results">
            <div class="col-12 col-md-5">
                <label for="search" class="form-label small mb-1">{{ __('Search') }}</label>
                <input type="search" id="search" name="search" value="{{ $filters['search'] }}" class="form-control form-control-sm"
                       placeholder="{{ __('Name, village or phone') }}">
            </div>
            <div class="col-6 col-md-2">
                <label for="type" class="form-label small mb-1">{{ __('Type') }}</label>
                <select id="type" name="type" class="form-select form-select-sm">
                    <option value="">{{ __('All') }}</option>
                    @foreach (\App\Enums\PartyType::options() as $value => $label)
                        <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="status" class="form-label small mb-1">{{ __('Status') }}</label>
                <select id="status" name="status" class="form-select form-select-sm">
                    <option value="">{{ __('All') }}</option>
                    <option value="active" @selected($filters['status'] === 'active')>{{ __('Active') }}</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>{{ __('Inactive') }}</option>
                </select>
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>{{ __('Filter') }}</button>
                <a href="{{ route('parties.index') }}" class="btn btn-link btn-sm" data-ajax-clear>{{ __('Clear') }}</a>
            </div>
        </form>
    </div>

    <div id="parties-results" data-ajax-results>
    @fragment('results')
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Party') }}</th>
                    <th>{{ __('Phone') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th class="text-end">{{ __('Opening balance') }}</th>
                    <th>{{ __('Status') }}</th>
                    @if ($canManage)<th class="text-end">{{ __('Actions') }}</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($parties as $party)
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ $party->name }}</span>
                            @if ($party->village)<div class="small text-body-secondary">{{ $party->village }}</div>@endif
                        </td>
                        <td class="text-nowrap">{{ $party->phone ?: '—' }}</td>
                        <td>{{ $party->party_type->label() }}</td>
                        <td class="text-end"><x-money :value="$party->opening_balance" /></td>
                        <td>
                            @if ($party->is_active)
                                <span class="badge text-bg-success">{{ __('Active') }}</span>
                            @else
                                <span class="badge text-bg-secondary">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        @if ($canManage)
                            <td class="text-end text-nowrap">
                                <a href="{{ route('parties.edit', $party) }}" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-pencil"></i> <span class="d-none d-sm-inline">{{ __('Edit') }}</span>
                                </a>
                                <x-confirm-form :action="route('parties.destroy', $party)"
                                                :message="__('Delete the party :name?', ['name' => $party->name])" />
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canManage ? 6 : 5 }}" class="text-center text-body-secondary py-5">
                            <i class="bi bi-people fs-2 d-block mb-2"></i>
                            {{ $filtered ? __('No parties match these filters.') : __('No parties yet.') }}
                            @if ($canManage && ! $filtered)
                                <div class="mt-2"><a href="{{ route('parties.create') }}" class="btn btn-primary btn-sm">{{ __('Add first party') }}</a></div>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($parties->hasPages())<div class="card-footer">{{ $parties->links() }}</div>@endif
    @endfragment
    </div>
</div>
<p class="form-text mt-2">{{ __('Opening balance: + they owe us, − we owe them.') }}</p>
@endsection
