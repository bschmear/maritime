<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Turnstile;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidTurnstile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Turnstile::isConfigured()) {
            return;
        }

        if (! Turnstile::verify(is_string($value) ? $value : null, request()->ip())) {
            $fail('We could not verify you are human. Please complete the security check and try again.');
        }
    }
}
