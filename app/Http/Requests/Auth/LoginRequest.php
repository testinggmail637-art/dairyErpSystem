<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /** Failed attempts allowed per login + IP before a lockout. */
    public const MAX_ATTEMPTS = 5;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string'],
        ];
    }

    public function attributes(): array
    {
        return ['login' => __('email or phone')];
    }

    /**
     * Credentials for Auth::attempt(): an "@" means email, anything else is treated as a phone number.
     *
     * @return array<string, string>
     */
    public function credentials(): array
    {
        $login = trim((string) $this->input('login'));

        $field = str_contains($login, '@') ? 'email' : 'phone';
        $value = $field === 'email' ? strtolower($login) : (string) User::normalizePhone($login);

        return [$field => $value, 'password' => (string) $this->input('password')];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->credentials(), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => __('These credentials do not match our records.'),
            ]);
        }

        if (! Auth::user()->is_active) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => __('Your account is inactive. Please contact the owner.'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => __('Too many login attempts. Please try again in :seconds seconds.', ['seconds' => $seconds]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower(trim((string) $this->input('login'))).'|'.$this->ip());
    }
}
