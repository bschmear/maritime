<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

abstract class PortalLoginRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @param  callable(): bool  $attempt
     *
     * @throws ValidationException
     */
    protected function attemptLogin(callable $attempt): void
    {
        $this->ensureIsNotRateLimited();

        if (! $attempt()) {
            RateLimiter::hit($this->throttleKey());

            $message = __('These credentials do not match our records.');

            throw ValidationException::withMessages([
                'email' => $message,
                'password' => $message,
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        $message = trans('auth.throttle', [
            'seconds' => $seconds,
            'minutes' => (int) ceil($seconds / 60),
        ]);

        throw ValidationException::withMessages([
            'email' => $message,
            'password' => $message,
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower($this->guardName().'|'.$this->input('email')).'|'.$this->ip()
        );
    }

    abstract protected function guardName(): string;
}
