<?php

namespace App\Support\Validation;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use ReflectionEnum;

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
        $data = self::mergeFieldDefaults($data, $formSchema, $fieldsSchema);

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
     * Fill missing keys from fields.json defaults so quick-create payloads (e.g. deal contract modal) validate.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $formSchema
     * @param  array<string, array<string, mixed>>  $fieldsSchema
     * @return array<string, mixed>
     */
    public static function mergeFieldDefaults(array $data, ?array $formSchema, array $fieldsSchema): array
    {
        foreach ($fieldsSchema as $key => $def) {
            if (! is_array($def) || ! array_key_exists('default', $def)) {
                continue;
            }

            if (self::isPresentValue($data[$key] ?? null)) {
                continue;
            }

            $data[$key] = self::resolveDefaultForValidation($def, $def['default']);
        }

        return $data;
    }

    private static function isPresentValue(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return ! (is_array($value) && $value === []);
    }

    /**
     * @param  array<string, mixed>  $def
     */
    private static function resolveDefaultForValidation(array $def, mixed $default): mixed
    {
        $type = $def['type'] ?? 'text';
        if ($type !== 'select' || ! isset($def['enum']) || ! is_string($def['enum'])) {
            return $default;
        }

        $enumClass = $def['enum'];
        if (! class_exists($enumClass) || ! method_exists($enumClass, 'options')) {
            return $default;
        }

        $options = $enumClass::options();
        if (! is_array($options) || $options === [] || ! is_string($default)) {
            return $default;
        }

        foreach ($options as $option) {
            if (! is_array($option)) {
                continue;
            }

            if (($option['value'] ?? null) === $default) {
                return $option['id'] ?? $default;
            }
        }

        return $default;
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
            'multi_enum' => ['required', 'array', 'min:1'],
            'select' => isset($def['enum']) ? self::enumSelectRules($def) : ['required'],
            default => ['required', 'string'],
        };
    }

    /**
     * @param  array<string, mixed>  $def
     * @return list<string|Rule>
     */
    private static function enumSelectRules(array $def): array
    {
        $enumClass = $def['enum'] ?? null;
        if (! is_string($enumClass) || ! class_exists($enumClass)) {
            return ['required', 'integer'];
        }

        if (method_exists($enumClass, 'options')) {
            $options = $enumClass::options();
            if (is_array($options) && $options !== [] && isset($options[0]['id'])) {
                $ids = array_map(static fn (array $option) => $option['id'], $options);
                $allNumeric = array_reduce(
                    $ids,
                    static fn (bool $carry, mixed $id) => $carry && (is_int($id) || (is_string($id) && $id !== '' && ctype_digit($id))),
                    true,
                );

                if ($allNumeric) {
                    $numericIds = array_map(static fn (mixed $id) => (int) $id, $ids);
                    $stringValues = array_values(array_filter(array_map(
                        static fn (array $option) => isset($option['value']) ? (string) $option['value'] : null,
                        $options,
                    )));

                    try {
                        $reflection = new ReflectionEnum($enumClass);
                        $isStringBacked = $reflection->isBacked()
                            && $reflection->getBackingType()?->getName() === 'string';
                    } catch (\Throwable) {
                        $isStringBacked = false;
                    }

                    // String-backed enums (e.g. invoice status) persist option `value` but forms may submit numeric `id`.
                    if ($isStringBacked && $stringValues !== []) {
                        return ['required', function (string $attribute, mixed $value, \Closure $fail) use ($numericIds, $stringValues): void {
                            if (is_numeric($value) && in_array((int) $value, $numericIds, true)) {
                                return;
                            }

                            if (is_string($value) && in_array($value, $stringValues, true)) {
                                return;
                            }

                            $fail(__('validation.in', ['attribute' => $attribute]));
                        }];
                    }

                    return ['required', 'integer', Rule::in($numericIds)];
                }

                $stringIds = array_map(static fn (mixed $id) => (string) $id, $ids);

                return ['required', 'string', Rule::in($stringIds)];
            }
        }

        try {
            $reflection = new ReflectionEnum($enumClass);
        } catch (\Throwable) {
            return ['required', 'integer'];
        }

        if (! $reflection->isBacked()) {
            return ['required', 'integer'];
        }

        $backingType = $reflection->getBackingType()?->getName();
        $values = array_map(static fn ($case) => $case->value, $enumClass::cases());

        if ($backingType === 'string') {
            return ['required', 'string', Rule::in($values)];
        }

        return ['required', 'integer', Rule::in($values)];
    }

    /**
     * @param  array<string, mixed>  $def
     */
    private static function requiredMessage(string $label, array $def): string
    {
        $type = $def['type'] ?? 'text';

        if (in_array($type, ['record', 'select', 'multi_enum'], true)) {
            return "Please select {$label}.";
        }

        return "The {$label} field is required.";
    }
}
