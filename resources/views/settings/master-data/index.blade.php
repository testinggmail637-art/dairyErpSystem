@extends('layouts.app')

@section('title', $labels['plural'])

@section('page-actions')
    <a href="{{ route($routePrefix.'.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>{{ __('Add :item', ['item' => strtolower($labels['singular'])]) }}
    </a>
@endsection

@section('content')
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route($routePrefix.'.index') }}" class="d-flex gap-2" data-allow-resubmit>
            <input type="search" name="search" value="{{ $search }}" class="form-control form-control-sm app-input-narrow"
                   placeholder="{{ __('Name or code') }}" aria-label="{{ __('Search') }}">
            <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-search"></i></button>
            @if ($search !== '')
                <a href="{{ route($routePrefix.'.index') }}" class="btn btn-link btn-sm">{{ __('Clear') }}</a>
            @endif
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Code') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-end">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="fw-semibold">{{ $row->name }}</td>
                        <td><code>{{ $row->code }}</code></td>
                        <td>
                            @if ($row->is_active)
                                <span class="badge text-bg-success">{{ __('Active') }}</span>
                            @else
                                <span class="badge text-bg-secondary">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route($routePrefix.'.edit', $row) }}" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-pencil"></i> <span class="d-none d-sm-inline">{{ __('Edit') }}</span>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-body-secondary py-5">
                            <i class="bi {{ $labels['icon'] }} fs-2 d-block mb-2"></i>
                            {{ __('Nothing here yet.') }}
                            <div class="mt-2">
                                <a href="{{ route($routePrefix.'.create') }}" class="btn btn-primary btn-sm">
                                    {{ __('Add first :item', ['item' => strtolower($labels['singular'])]) }}
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $rows->links() }}</div>
<p class="form-text">{{ __('Entries are never deleted, so old records keep their reference. Deactivate an entry to hide it from new forms.') }}</p>
@endsection
