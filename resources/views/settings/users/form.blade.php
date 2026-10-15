@extends('layouts.app')

@section('title', $user->exists ? __('Edit user') : __('Add user'))

@section('page-actions')
    <a href="{{ route('users.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Back to users') }}</a>
@endsection

@section('content')
<form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}">
    @csrf
    @if ($user->exists) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <div class="card h-100">
                <div class="card-header fw-semibold">{{ __('Account') }}</div>
                <div class="card-body">
                    <x-form.input name="name" :label="__('Name')" :value="$user->name" maxlength="100" required />

                    <div class="row g-3 mb-1">
                        <div class="col-12 col-md-6">
                            <x-form.input name="email" type="email" :label="__('Email')" :value="$user->email" maxlength="150"
                                          autocomplete="off" group-class="mb-0" />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.input name="phone" type="tel" :label="__('Mobile number')" :value="$user->phone" maxlength="16"
                                          inputmode="numeric" autocomplete="off" group-class="mb-0" />
                        </div>
                    </div>
                    <p class="form-text mb-3">{{ __('Enter an email, a mobile number, or both. The user logs in with either one.') }}</p>

                    <div class="row g-3 mb-1">
                        <div class="col-12 col-md-6">
                            <x-form.input name="password" type="password" :label="$user->exists ? __('New password') : __('Password')"
                                          :required="! $user->exists" minlength="8" autocomplete="new-password" group-class="mb-0" />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.input name="password_confirmation" type="password" :label="__('Confirm password')"
                                          :required="! $user->exists" autocomplete="new-password" group-class="mb-0" />
                        </div>
                    </div>
                    <p class="form-text mb-3">
                        {{ $user->exists
                            ? __('Leave blank to keep the current password. A new password signs the user out of all devices.')
                            : __('At least 8 characters. Share it with the user; they can change it from their profile.') }}
                    </p>

                    <x-form.switch name="is_active" :label="__('Active (can log in)')" :checked="$user->is_active" group-class="mb-0" />
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="card mb-3">
                <div class="card-header fw-semibold">{{ __('Roles') }} <span class="text-danger">*</span></div>
                <div class="card-body">
                    @foreach ($roles as $name => $label)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $name }}" id="role_{{ $name }}"
                                   @checked(in_array($name, $selectedRoles, true))>
                            <label class="form-check-label" for="role_{{ $name }}">{{ $label }}</label>
                        </div>
                    @endforeach
                    @error('roles')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    @error('roles.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="card">
                <div class="card-header fw-semibold">{{ __('Farms') }} <span class="text-danger">*</span></div>
                <div class="card-body">
                    @foreach ($farms as $farm)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="farms[]" value="{{ $farm->id }}" id="farm_{{ $farm->id }}"
                                   @checked(in_array($farm->id, $selectedFarms, true))>
                            <label class="form-check-label" for="farm_{{ $farm->id }}">{{ $farm->name }} <small class="text-body-secondary">({{ $farm->code }})</small></label>
                        </div>
                    @endforeach
                    @error('farms')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    @error('farms.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    @if ($user->exists)
        <div class="card mt-3">
            <div class="card-body pb-0">
                <x-form.reason />
            </div>
        </div>
    @endif

    <div class="mt-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
