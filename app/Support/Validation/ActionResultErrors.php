<?php

namespace App\Support\Validation;

final class ActionResultErrors
{
    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, array<string, mixed>>  $fieldsSchema
     * @return array{errors: array<string, string|array<int, string>>, message: string}
     */
    public static function normalize(array $result, array $fieldsSchema, string $fallbackTitle = 'record'): array
    {
        $errors = $result['errors'] ?? [];

        if (is_array($errors) && $errors !== []) {
            $bag = self::flattenErrorBag($errors);

            return [
                'errors' => self::sanitizeSqlInBag($bag, $fieldsSchema),
                'message' => (string) ($result['message'] ?? self::firstError($bag)),
            ];
        }

        $message = (string) ($result['message'] ?? '');

        if ($message !== '' && FriendlyDatabaseErrors::looksLikeSqlError($message)) {
            return FriendlyDatabaseErrors::fromMessage($message, $fieldsSchema);
        }

        if ($message === '') {
            $text = "Failed to save {$fallbackTitle}.";

            return [
                'errors' => ['general' => $text],
                'message' => $text,
            ];
        }

        return [
            'errors' => ['general' => $message],
            'message' => $message,
        ];
    }

    /**
     * @param  array<string, string|array<int, string>>  $errors
     * @return array<string, string|array<int, string>>
     */
    private static function sanitizeSqlInBag(array $errors, array $fieldsSchema): array
    {
        $sanitized = [];

        foreach ($errors as $key => $value) {
            $text = is_array($value) ? (string) ($value[0] ?? '') : (string) $value;

            if ($text !== '' && FriendlyDatabaseErrors::looksLikeSqlError($text)) {
                $friendly = FriendlyDatabaseErrors::fromMessage($text, $fieldsSchema);
                $sanitized = array_merge($sanitized, $friendly['errors']);

                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized !== [] ? $sanitized : ['general' => 'Could not save. Please check required fields and try again.'];
    }

    /**
     * @param  array<string, string|array<int, string>>  $errors
     */
    private static function firstError(array $errors): string
    {
        foreach ($errors as $value) {
            if (is_array($value)) {
                $text = (string) ($value[0] ?? '');
                if ($text !== '') {
                    return $text;
                }
            } elseif ((string) $value !== '') {
                return (string) $value;
            }
        }

        return 'Validation failed.';
    }

    /**
     * @param  array<string, mixed>  $errors
     * @return array<string, string|array<int, string>>
     */
    private static function flattenErrorBag(array $errors): array
    {
        $bag = [];

        foreach ($errors as $key => $value) {
            if (is_array($value) && array_is_list($value)) {
                $bag[$key] = $value;

                continue;
            }

            if (is_array($value)) {
                foreach ($value as $nestedKey => $nestedValue) {
                    $bag[(string) $nestedKey] = $nestedValue;
                }

                continue;
            }

            $bag[$key] = $value;
        }

        return $bag;
    }
}
