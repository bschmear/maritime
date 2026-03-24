<?php

namespace App\Casts;

use App\Enums\Payments\Terms;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Normalizes DB values that may include PostgreSQL cast noise (e.g. 'value'::character varying)
 * and maps them to {@see Terms} without throwing ValueError.
 */
class PaymentTermsCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): Terms
    {
        if ($value === null || $value === '') {
            return Terms::DueOnReceipt;
        }

        $normalized = self::normalizeRaw((string) $value);

        return Terms::tryFrom($normalized)
            ?? Terms::tryFrom(strtolower($normalized))
            ?? Terms::DueOnReceipt;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return $model->getTable() === 'contracts'
                ? Terms::DueOnReceipt->value
                : null;
        }

        if ($value instanceof Terms) {
            return $value->value;
        }

        $normalized = self::normalizeRaw((string) $value);

        return Terms::tryFrom($normalized)?->value
            ?? Terms::tryFrom(strtolower($normalized))?->value
            ?? Terms::DueOnReceipt->value;
    }

    private static function normalizeRaw(string $value): string
    {
        $v = trim($value);

        if (str_contains($v, '::')) {
            $v = explode('::', $v, 2)[0];
            $v = trim($v);
        }

        return trim($v, "'\" \t\n\r\0\x0B");
    }
}
