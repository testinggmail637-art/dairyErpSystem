@extends('layouts.guest')

@section('title', __('Login'))

@section('content')
<div class="auth-page" @if ($appearance['login_background_path']) style="background-image: url('{{ branding_url($appearance['login_background_path']) }}')" @endif>
    <div class="card auth-card shadow border-0">
        <div class="card-body p-4 p-sm-5">
            <div class="text-center mb-4">
                @if ($appearance['logo_path'])
                    <img src="{{ branding_url($appearance['logo_path']) }}" alt="{{ $appearance['app_name'] }}" class="auth-logo mb-3">
                @endif
                <h1 class="h4 fw-semibold text-primary mb-1">{{ $appearance['app_name'] }}</h1>
                @if ($appearance['login_welcome_text'])
                    <p class="text-body-secondary mb-0">{{ $appearance['login_welcome_text'] }}</p>
                @endif
            </div>

            <form method="POST" action="{{ route('login.store') }}" novalidate>
                @csrf

                <div class="mb-3">
                    <label for="login" class="form-label">{{ __('Email or phone') }}</label>
                    <input type="text" id="login" name="login" value="{{ old('login') }}"
                           class="form-control form-control-lg @error('login') is-invalid @enderror"
                           autocomplete="username" autofocus required>
                    @error('login')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">{{ __('Password') }}</label>
                    <input type="password" id="password" name="password"
                           class="form-control form-control-lg @error('password') is-invalid @enderror"
                           autocomplete="current-password" required>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1" @checked(old('remember'))>
                    <label class="form-check-label" for="remember">{{ __('Remember me') }}</label>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="bi bi-box-arrow-in-right me-1"></i> {{ __('Login') }}
                </button>
            </form>

            <p class="text-body-secondary small text-center mt-4 mb-0">
                {{ __('Forgot your password? Ask the owner or admin to reset it.') }}
            </p>
        </div>
    </div>
</div>
@endsection
