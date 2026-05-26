<?php

namespace App\Support;

use App\Enums\Transaction\TransactionStatus;

class TransactionEnumMapper
{
    public static function statusToValue(mixed $input): string
    {
        if ($input === null || $input === '') {
            return TransactionStatus::Pending->value;
        }

        if (is_string($input) && ! is_numeric($input)) {
            $normalized = strtolower($input);
            // Legacy string values (pre migration / external payloads)
            $legacyStrings = [
                'open' => TransactionStatus::Pending->value,
                'active' => TransactionStatus::Processing->value,
                'won' => TransactionStatus::Completed->value,
                'lost' => TransactionStatus::Failed->value,
            ];
            if (isset($legacyStrings[$normalized])) {
                return $legacyStrings[$normalized];
            }

            foreach (TransactionStatus::cases() as $case) {
                if ($case->value === $input) {
                    return $case->value;
                }
            }

            return TransactionStatus::Pending->value;
        }

        $id = (int) $input;

        // Resolve current enum numeric ids first (Inertia forms send option `id` values).
        // A legacy id map used to run here before this loop; it collided with the new id scheme
        // (e.g. id 3 = Completed now, but was "Lost" under the old numbering) and mis-saved deals as failed.
        foreach (TransactionStatus::cases() as $case) {
            if ($case->id() === $id) {
                return $case->value;
            }
        }

        return TransactionStatus::Pending->value;
    }
}
