<?php

declare(strict_types=1);

namespace App\Support\QuickBooks;

final class QuickBooksRowMapper
{
    /**
     * @param  array<string, mixed>|null  $ref
     */
    public static function refValue(?array $ref): string
    {
        if ($ref === null) {
            return '';
        }

        $value = $ref['value'] ?? null;
        if ($value === null || $value === '') {
            return '';
        }

        return (string) $value;
    }

    /**
     * @param  array<string, mixed>|null  $ref
     */
    public static function refName(?array $ref): string
    {
        if ($ref === null) {
            return '';
        }

        return self::normalizeString($ref['name'] ?? null);
    }

    public static function normalizeString(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return trim($value);
    }

    public static function normalizeEmail(mixed $value): ?string
    {
        $email = self::normalizeString($value);
        if ($email === '') {
            return null;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    public static function parseMoney(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return round((float) $value, 2);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function normalizeList(mixed $rows): array
    {
        if (! is_array($rows) || $rows === []) {
            return [];
        }

        return array_is_list($rows) ? $rows : [$rows];
    }

    /**
     * @param  array<string, mixed>  $fault
     */
    public static function faultMessage(array $fault): string
    {
        $errors = $fault['Error'] ?? [];
        if (! is_array($errors)) {
            return '';
        }
        if ($errors !== [] && ! array_is_list($errors)) {
            $errors = [$errors];
        }
        $parts = [];
        foreach ($errors as $err) {
            if (is_array($err) && ! empty($err['Message'])) {
                $parts[] = (string) $err['Message'];
            }
        }

        return implode('; ', $parts);
    }
}
