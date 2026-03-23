<?php

namespace App\Domain\Transaction\Support;

/**
 * Applies deal-level tax % to line bases and add-on bases when marked taxable.
 */
final class ComputeTransactionLineTax
{
    public static function boolish(mixed $value, bool $default = true): bool
    {
        if ($value === null) {
            return $default;
        }
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 0 || $value === '0' || $value === 'false' || $value === false) {
            return false;
        }
        if ($value === 1 || $value === '1' || $value === 'true' || $value === true) {
            return true;
        }

        return (bool) $value;
    }

    public static function amount(float $base, bool $taxable, float $ratePercent): float
    {
        if (! $taxable || $ratePercent <= 0 || $base <= 0) {
            return 0.0;
        }

        return round($base * $ratePercent / 100, 2);
    }
}
