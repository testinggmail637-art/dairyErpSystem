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
                    <div class="mb-3">
                        <label for="name" class="form-label">{{ __('Name') }} <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" maxlength="100"
                               class="form-control @error('name') is-invalid @enderror" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="row g-3 mb-1">
                        <div class="col-12 col-md-6">
                            <label for="email" class="form-label">{{ __('Email') }}</label>
                            <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" maxlength="150"
                                   class="form-control @error('email') is-invalid @enderror" autocomplete="off">
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="phone" class="form-label">{{ __('Mobile number') }}</label>
                            <input type="tel" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" inputmode="numeric" maxlength="16"
                                   class="form-control @error('phone') is-invalid @enderror" autocomplete="off">
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <p class="form-text mb-3">{{ __('Enter an email, a mobile number, or both. The user logs in with either one.') }}</p>

                    <div class="row g-3 mb-1">
                        <div class="col-12 col-md-6">
                            <label for="password" class="form-label">
                                {{ $user->exists ? __('New password') : __('Password') }}
                                @unless ($user->exists)<span class="text-danger">*</span>@endunless
                            </label>
                            <input type="password" id="password" name="password" autocomplete="new-password" minlength="8"
                                   class="form-control @error('password') is-invalid @enderror" @required(! $user->exists)>
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="password_confirmation" class="form-label">{{ __('Confirm password') }}</label>
                            <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password"
                                   class="form-control" @required(! $user->exists)>
                        </div>
                    </div>
                    <p class="form-text mb-3">
                        {{ $user->exists
                            ? __('Leave blank to keep the current password. A new password signs the user out of all devices.')
                            : __('At least 8 characters. Share it with the user; they can change it from their profile.') }}
                    </p>

                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input @error('is_active') is-invalid @enderror" type="checkbox" role="switch"
                               id="is_active" name="is_active" value="1" @checked(old('is_active', $user->is_active))>
                        <label class="form-check-label" for="is_active">{{ __('Active (can log in)') }}</label>
                        @error('is_active')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
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

    <div class="mt-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
