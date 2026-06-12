<?php

namespace App\Support\Validation;

use Illuminate\Support\Str;

final class FriendlyDatabaseErrors
{
    public static function looksLikeSqlError(string $message): bool
    {
        return str_contains($message, 'SQLSTATE')
            || str_contains($message, 'violates not-null constraint')
            || str_contains($message, 'violates unique constraint')
            || str_contains($message, 'violates foreign key constraint');
    }

    /**
     * @param  array<string, array<string, mixed>>  $fieldsSchema
     * @return array{errors: array<string, string>, message: string}
     */
    public static function fromMessage(string $message, array $fieldsSchema): array
    {
        if (preg_match('/null value in column "([^"]+)"/i', $message, $matches)) {
            $field = $matches[1];
            $text = self::requiredMessage($field, $fieldsSchema);

            return [
                'errors' => [$field => $text],
                'message' => $text,
            ];
        }

        if (preg_match('/duplicate key value violates unique constraint.*Key \(([^)]+)\)/i', $message, $matches)) {
            $field = trim(explode(',', $matches[1])[0], " \t\n\r\0\x0B\"");
            $label = self::label($field, $fieldsSchema);
            $text = "The {$label} has already been taken.";

            return [
                'errors' => [$field => $text],
                'message' => $text,
            ];
        }

        if (preg_match('/violates foreign key constraint/i', $message)) {
            $text = 'A selected related record is invalid or no longer available.';

            return [
                'errors' => ['general' => $text],
                'message' => $text,
            ];
        }

        return [
            'errors' => ['general' => 'Could not save. Please check required fields and try again.'],
            'message' => 'Could not save. Please check required fields and try again.',
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $fieldsSchema
     */
    private static function requiredMessage(string $field, array $fieldsSchema): string
    {
        $label = self::label($field, $fieldsSchema);
        $type = $fieldsSchema[$field]['type'] ?? 'text';

        if (in_array($type, ['record', 'select'], true)) {
            return "Please select {$label}.";
        }

        return "The {$label} field is required.";
    }

    /**
     * @param  array<string, array<string, mixed>>  $fieldsSchema
     */
    private static function label(string $field, array $fieldsSchema): string
    {
        return $fieldsSchema[$field]['label'] ?? Str::headline(str_replace('_', ' ', $field));
    }
}
