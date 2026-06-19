<?php

declare(strict_types=1);

namespace App\Support\Enum;

/**
 * Normalize enum operands between API/forms (numeric option ids) and DB string values.
 */
final class StoredEnumNormalizer
{
    /**
     * @param  class-string  $enumClass
     */
    public static function normalizeForField(mixed $value, string $field, array $fieldsSchema): mixed
    {
        $enumClass = $fieldsSchema[$field]['enum'] ?? null;

        if (! is_string($enumClass) || $enumClass === '' || ! class_exists($enumClass)) {
            return $value;
        }

        return self::normalizeForEnum($value, $enumClass);
    }

    /**
     * @param  class-string  $enumClass
     */
    public static function normalizeForEnum(mixed $value, string $enumClass): mixed
    {
        if (! method_exists($enumClass, 'toStoredValue')) {
            return $value;
        }

        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $item) {
                $stored = $enumClass::toStoredValue($item);
                if ($stored !== null) {
                    $normalized[] = $stored;
                }
            }

            return $normalized;
        }

        $stored = $enumClass::toStoredValue($value);

        return $stored ?? $value;
    }
}
