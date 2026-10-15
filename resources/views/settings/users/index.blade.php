@extends('layouts.app')

@section('title', __('Users'))

@section('page-actions')
    <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-person-plus me-1"></i>{{ __('Add user') }}</a>
@endsection

@section('content')
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('users.index') }}" class="row g-2 align-items-end" data-allow-resubmit>
            <div class="col-12 col-md-5">
                <label for="search" class="form-label small mb-1">{{ __('Search') }}</label>
                <input type="search" id="search" name="search" value="{{ $filters['search'] }}" class="form-control form-control-sm"
                       placeholder="{{ __('Name, email or phone') }}">
            </div>
            <div class="col-6 col-md-3">
                <label for="role" class="form-label small mb-1">{{ __('Role') }}</label>
                <select id="role" name="role" class="form-select form-select-sm">
                    <option value="">{{ __('All roles') }}</option>
                    @foreach ($roles as $name => $label)
                        <option value="{{ $name }}" @selected($filters['role'] === $name)>{{ $label }}</option>
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
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>{{ __('Filter') }}</button>
                <a href="{{ route('users.index') }}" class="btn btn-link btn-sm">{{ __('Clear') }}</a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Email') }}</th>
                    <th>{{ __('Phone') }}</th>
                    <th>{{ __('Roles') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Last login') }}</th>
                    <th class="text-end">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td class="fw-semibold">{{ $user->name }}</td>
                        <td>{{ $user->email ?: '—' }}</td>
                        <td>{{ $user->phone ?: '—' }}</td>
                        <td>
                            @foreach ($user->roles as $role)
                                <span class="badge text-bg-secondary">{{ role_label($role->name) }}</span>
                            @endforeach
                        </td>
                        <td>
                            @if ($user->is_active)
                                <span class="badge text-bg-success">{{ __('Active') }}</span>
                            @else
                                <span class="badge text-bg-danger">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td class="small">{{ fmt_datetime($user->last_login_at) ?: '—' }}</td>
                        <td class="text-end">
                            <a href="{{ route('users.edit', $user) }}" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-pencil"></i> <span class="d-none d-sm-inline">{{ __('Edit') }}</span>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-body-secondary py-5">
                            <i class="bi bi-people fs-2 d-block mb-2"></i>
                            {{ __('No users found.') }}
                            <div class="mt-2"><a href="{{ route('users.create') }}" class="btn btn-primary btn-sm">{{ __('Add first user') }}</a></div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $users->links() }}</div>
@endsection
