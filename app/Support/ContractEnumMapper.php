<?php

namespace App\Support;

use App\Enums\Contract\ContractPaymentStatus;
use App\Enums\Contract\ContractStatus;

class ContractEnumMapper
{
    public static function statusToValue(mixed $input): string
    {
        return self::toBackedValue(ContractStatus::class, $input, ContractStatus::Draft->value);
    }

    public static function paymentStatusToValue(mixed $input): string
    {
        return self::toBackedValue(ContractPaymentStatus::class, $input, ContractPaymentStatus::Pending->value);
    }

    /**
     * @param  class-string<ContractStatus|ContractPaymentStatus>  $enumClass
     */
    public static function toBackedValue(string $enumClass, mixed $input, string $default): string
    {
        if ($input === null || $input === '') {
            return $default;
        }

        if (is_string($input) && ! is_numeric($input)) {
            foreach ($enumClass::cases() as $case) {
                if ($case->value === $input) {
                    return $case->value;
                }
            }

            return $default;
        }

        $id = (int) $input;
        foreach ($enumClass::cases() as $case) {
            if ($case->id() === $id) {
                return $case->value;
            }
        }

        return $default;
    }
}
