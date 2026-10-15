@extends('layouts.app')

@section('title', $role->exists ? __('Role: :name', ['name' => role_label($role->name)]) : __('Add role'))

@section('page-actions')
    <a href="{{ route('roles.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Back to roles') }}</a>
@endsection

@section('content')
@php($seeded = $role->exists && \App\Enums\RoleName::tryFrom($role->name) !== null)

@if ($locked)
    <div class="alert alert-info"><i class="bi bi-lock-fill me-1"></i>{{ __('The owner role is locked: it always has every permission and cannot be changed.') }}</div>
@endif

<form method="POST" action="{{ $role->exists ? route('roles.update', $role) : route('roles.store') }}" id="role-form">
    @csrf
    @if ($role->exists) @method('PUT') @endif

    <div class="card mb-3">
        <div class="card-body">
            <label for="name" class="form-label">{{ __('Role code') }} <span class="text-danger">*</span></label>
            <input type="text" id="name" name="name" value="{{ old('name', $role->name) }}" maxlength="50"
                   class="form-control @error('name') is-invalid @enderror" @readonly($seeded) @disabled($locked) required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">
                {{ $seeded ? __('Built-in role: the code cannot be changed.') : __('Lower-case letters, numbers and underscores, e.g. milk_supervisor.') }}
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="fw-semibold">{{ __('Permissions') }}</span>
            @unless ($locked)
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-link btn-sm p-0" data-check-all="1">{{ __('Select all') }}</button>
                    <button type="button" class="btn btn-link btn-sm p-0" data-check-all="0">{{ __('Clear all') }}</button>
                </div>
            @endunless
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <tbody>
                    @foreach ($groups as $group => $permissions)
                        <tr>
                            <th scope="row" class="text-nowrap app-col-shrink">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" id="group_{{ $loop->index }}"
                                           data-group-toggle="{{ $loop->index }}" @disabled($locked)>
                                    <label class="form-check-label" for="group_{{ $loop->index }}">{{ __($group) }}</label>
                                </div>
                            </th>
                            <td>
                                <div class="d-flex flex-wrap gap-3">
                                    @foreach ($permissions as $permission)
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission }}"
                                                   id="perm_{{ str_replace('.', '_', $permission) }}" data-group="{{ $loop->parent->index }}"
                                                   @checked($locked || in_array($permission, $selected, true)) @disabled($locked)>
                                            <label class="form-check-label" for="perm_{{ str_replace('.', '_', $permission) }}">
                                                {{ \App\Support\Permissions::label($permission) }}
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @error('permissions.*')<div class="text-danger small mt-2">{{ $message }}</div>@enderror

    @unless ($locked)
        <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
            <a href="{{ route('roles.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        </div>
    @endunless
</form>
@endsection

@push('scripts')
<script>
    $(function () {
        const $form = $('#role-form');

        function syncGroups() {
            $form.find('[data-group-toggle]').each(function () {
                const $items = $form.find('[data-group="' + $(this).data('group-toggle') + '"]');
                const checked = $items.filter(':checked').length;
                this.checked = checked === $items.length;
                this.indeterminate = checked > 0 && checked < $items.length;
            });
        }

        $form.on('change', '[data-group-toggle]', function () {
            $form.find('[data-group="' + $(this).data('group-toggle') + '"]').prop('checked', this.checked);
        });
        $form.on('change', '[data-group]', syncGroups);
        $('[data-check-all]').on('click', function () {
            $form.find('[data-group]').prop('checked', $(this).data('check-all') === 1);
            syncGroups();
        });

        syncGroups();
    });
</script>
@endpush
