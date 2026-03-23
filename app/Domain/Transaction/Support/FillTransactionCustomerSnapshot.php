<?php

namespace App\Domain\Transaction\Support;

use App\Domain\Customer\Models\Customer;

/**
 * Ensures customer_name / customer_email / customer_phone are persisted when the
 * form only sends customer_id (common with RecordSelect).
 */
final class FillTransactionCustomerSnapshot
{
    public static function merge(array $payload): array
    {
        $customerId = $payload['customer_id'] ?? null;
        if (! $customerId) {
            return $payload;
        }

        $customer = Customer::query()->find($customerId);
        if (! $customer) {
            return $payload;
        }

        $map = [
            'customer_name' => self::customerDisplayName($customer),
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
        ];

        foreach ($map as $field => $value) {
            $current = $payload[$field] ?? null;
            $isEmpty = $current === null
                || $current === ''
                || (is_string($current) && trim($current) === '');

            if ($isEmpty && $value !== null && $value !== '') {
                $payload[$field] = $value;
            }
        }

        return $payload;
    }

    private static function customerDisplayName(Customer $customer): ?string
    {
        $d = $customer->display_name;
        if (is_string($d) && trim($d) !== '') {
            return trim($d);
        }

        $parts = array_filter([$customer->first_name, $customer->last_name]);
        if ($parts !== []) {
            return implode(' ', $parts);
        }

        $email = $customer->email;

        return is_string($email) && $email !== '' ? $email : null;
    }
}
