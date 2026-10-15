@extends('layouts.app')

@section('title', __('My profile'))

@section('content')
<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold">{{ __('Profile') }}</div>
            <div class="card-body">
                <form method="POST" action="{{ route('profile.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="name" class="form-label">{{ __('Name') }}</label>
                        <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" maxlength="100"
                               class="form-control @error('name') is-invalid @enderror" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <dl class="row small mb-3">
                        <dt class="col-4 text-body-secondary fw-normal">{{ __('Email') }}</dt>
                        <dd class="col-8">{{ $user->email ?: '—' }}</dd>
                        <dt class="col-4 text-body-secondary fw-normal">{{ __('Phone') }}</dt>
                        <dd class="col-8">{{ $user->phone ?: '—' }}</dd>
                        <dt class="col-4 text-body-secondary fw-normal">{{ __('Last login') }}</dt>
                        <dd class="col-8">{{ fmt_datetime($user->last_login_at) ?: '—' }}</dd>
                    </dl>
                    <p class="form-text">{{ __('To change your email or phone, ask the owner or admin.') }}</p>

                    <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold">{{ __('Change password') }}</div>
            <div class="card-body">
                <form method="POST" action="{{ route('profile.password') }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="current_password" class="form-label">{{ __('Current password') }}</label>
                        <input type="password" id="current_password" name="current_password" autocomplete="current-password"
                               class="form-control @error('current_password') is-invalid @enderror" required>
                        @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">{{ __('New password') }}</label>
                        <input type="password" id="password" name="password" autocomplete="new-password" minlength="8"
                               class="form-control @error('password') is-invalid @enderror" required>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">{{ __('At least 8 characters.') }}</div>
                    </div>

                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label">{{ __('Confirm new password') }}</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password"
                               class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-primary">{{ __('Change password') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
