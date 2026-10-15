@extends('layouts.app')

@section('title', __('Roles'))

@section('page-actions')
    <a href="{{ route('roles.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Add role') }}</a>
@endsection

@section('content')
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Role') }}</th>
                    <th>{{ __('Code') }}</th>
                    <th class="text-end">{{ __('Permissions') }}</th>
                    <th class="text-end">{{ __('Users') }}</th>
                    <th class="text-end">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($roles as $role)
                    @php($locked = \App\Services\RoleService::isLocked($role))
                    <tr>
                        <td class="fw-semibold">
                            {{ role_label($role->name) }}
                            @if ($locked)
                                <i class="bi bi-lock-fill text-body-secondary ms-1" title="{{ __('Locked: always has every permission') }}"></i>
                            @endif
                        </td>
                        <td><code>{{ $role->name }}</code></td>
                        <td class="text-end">{{ $role->permissions_count }} / {{ $totalPermissions }}</td>
                        <td class="text-end">{{ $role->users_count }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('roles.edit', $role) }}" class="btn btn-outline-primary btn-sm">
                                <i class="bi {{ $locked ? 'bi-eye' : 'bi-pencil' }}"></i>
                                <span class="d-none d-sm-inline">{{ $locked ? __('View') : __('Edit') }}</span>
                            </a>
                            @unless ($locked || $role->users_count > 0)
                                <form method="POST" action="{{ route('roles.destroy', $role) }}" class="d-inline"
                                      data-confirm="{{ __('Delete the role :name?', ['name' => role_label($role->name)]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
<p class="form-text mt-2">{{ __('A role can be deleted only when no user has it. The owner role is locked.') }}</p>
@endsection
