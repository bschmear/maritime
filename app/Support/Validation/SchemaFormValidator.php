<?php

namespace App\Support\Validation;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class SchemaFormValidator
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $formSchema
     * @param  array<string, array<string, mixed>>  $fieldsSchema
     * @return array{success: false, message: string, errors: array<string, array<int, string>>}|null
     */
    public static function validate(array $data, ?array $formSchema, array $fieldsSchema): ?array
    {
        $requiredKeys = self::collectRequiredKeys($formSchema, $fieldsSchema);
        if ($requiredKeys === []) {
            return null;
        }

        $rules = [];
        $messages = [];

        foreach ($requiredKeys as $key) {
            $def = $fieldsSchema[$key] ?? [];
            $label = $def['label'] ?? Str::headline(str_replace('_', ' ', $key));
            $rules[$key] = self::rulesForRequired($def);
            $message = self::requiredMessage($label, $def);
            $messages["{$key}.required"] = $message;
            $messages["{$key}.filled"] = $message;
        }

        $validator = Validator::make($data, $rules, $messages);

        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()->toArray(),
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $formSchema
     * @param  array<string, array<string, mixed>>  $fieldsSchema
     * @return list<string>
     */
    private static function collectRequiredKeys(?array $formSchema, array $fieldsSchema): array
    {
        $keys = [];
        $form = $formSchema['form'] ?? $formSchema ?? [];

        if (is_array($form)) {
            foreach ($form as $group) {
                if (! is_array($group)) {
                    continue;
                }

                foreach ($group['fields'] ?? [] as $field) {
                    if (! is_array($field)) {
                        continue;
                    }

                    $key = $field['key'] ?? null;
                    if (! $key || ($field['hidden'] ?? false) || ($field['readOnly'] ?? false)) {
                        continue;
                    }

                    if ($field['required'] ?? false) {
                        $keys[$key] = true;
                    }
                }
            }
        }

        foreach ($fieldsSchema as $key => $def) {
            if (! is_array($def)) {
                continue;
            }

            if ($def['required'] ?? false) {
                $keys[$key] = true;
            }
        }

        return array_keys($keys);
    }

    /**
     * @param  array<string, mixed>  $def
     */
    private static function rulesForRequired(array $def): string|array
    {
        $type = $def['type'] ?? 'text';

        return match ($type) {
            'boolean' => ['required', 'boolean'],
            'date', 'datetime' => ['required', 'date'],
            'number', 'currency' => ['required', 'numeric'],
            'record' => ['required'],
            'select' => isset($def['enum']) ? ['required', 'integer'] : ['required'],
            default => ['required', 'string'],
        };
    }

    /**
     * @param  array<string, mixed>  $def
     */
    private static function requiredMessage(string $label, array $def): string
    {
        $type = $def['type'] ?? 'text';

        if (in_array($type, ['record', 'select'], true)) {
            return "Please select {$label}.";
        }

        return "The {$label} field is required.";
    }
}
