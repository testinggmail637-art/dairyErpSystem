@extends('layouts.guest')

@section('title', __('Login'))

@section('content')
<div class="auth-page">
    <div class="auth-panel @if ($appearance['login_background_path']) auth-panel-photo @endif"
         @if ($appearance['login_background_path']) style="background-image: url('{{ branding_url($appearance['login_background_path']) }}')" @endif>
        <div class="auth-panel-inner">
            {{-- Login logo, else the dark logo, else the logo. Heading: login title, else the app name when no logo. --}}
            @if ($appearance['login_logo_path'])
                <img src="{{ branding_url($appearance['login_logo_path']) }}" alt="{{ $appearance['app_name'] }}" class="auth-logo">
            @else
                <span class="app-brand-mark auth-mark"><i class="bi bi-droplet-half"></i></span>
            @endif
            @if (filled($appearance['login_title']) || ! $appearance['login_logo_path'])
                <p class="auth-panel-name">{{ filled($appearance['login_title']) ? $appearance['login_title'] : $appearance['app_name'] }}</p>
            @endif
            @if ($appearance['login_welcome_text'])
                <p class="auth-panel-text">{{ $appearance['login_welcome_text'] }}</p>
            @endif
        </div>
    </div>

    <div class="auth-form-side">
        <div class="auth-card">
            <h1 class="h3 mb-4">{{ __('Log in') }}</h1>

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
                    <i class="bi bi-box-arrow-in-right me-1"></i> {{ __('Log in') }}
                </button>
            </form>

            <p class="text-body-secondary small text-center mt-4 mb-0">
                {{ __('Forgot your password? Ask the owner or admin to reset it.') }}
            </p>
        </div>
    </div>
</div>
@endsection
