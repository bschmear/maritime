<?php

namespace App\Support;

use App\Enums\Transaction\TransactionStatus;

class TransactionEnumMapper
{
    public static function statusToValue(mixed $input): string
    {
        if ($input === null || $input === '') {
            return TransactionStatus::Active->value;
        }

        if (is_string($input) && ! is_numeric($input)) {
            $normalized = strtolower($input);
            // Legacy DB / API values before rename Open → Active
            if ($normalized === 'open') {
                return TransactionStatus::Active->value;
            }

            foreach (TransactionStatus::cases() as $case) {
                if ($case->value === $input) {
                    return $case->value;
                }
            }

            return TransactionStatus::Active->value;
        }

        $id = (int) $input;
        foreach (TransactionStatus::cases() as $case) {
            if ($case->id() === $id) {
                return $case->value;
            }
        }

        return TransactionStatus::Active->value;
    }
}
