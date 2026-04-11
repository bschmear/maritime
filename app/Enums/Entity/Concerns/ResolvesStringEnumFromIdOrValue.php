<?php

namespace App\Enums\Entity\Concerns;

/**
 * For string-backed enums that also expose {@see id()} for API / form options.
 * Accepts stored string values, numeric ids (int or string), or the enum instance.
 */
trait ResolvesStringEnumFromIdOrValue
{
    public static function tryFromStored(mixed $value): ?static
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof static) {
            return $value;
        }

        if (is_numeric($value)) {
            $id = (int) $value;
            foreach (static::cases() as $case) {
                if ($case->id() === $id) {
                    return $case;
                }
            }
        }

        $str = is_string($value) ? trim($value) : (string) $value;

        return static::tryFrom($str);
    }

    public static function toStoredValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $enum = $value instanceof static ? $value : static::tryFromStored($value);

        return $enum?->value;
    }

    /**
     * Laravel validation closure helper: nullable; accepts backing string or legacy numeric id.
     */
    public static function assertValidForValidation(mixed $value, \Closure $fail, string $attribute): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (static::tryFromStored($value) === null) {
            $fail(__('validation.in', ['attribute' => $attribute]));
        }
    }
}
