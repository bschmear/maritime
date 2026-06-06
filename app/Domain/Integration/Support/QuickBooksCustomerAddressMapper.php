<?php

declare(strict_types=1);

namespace App\Domain\Integration\Support;

final class QuickBooksCustomerAddressMapper
{
    /**
     * Map QuickBooks Customer BillAddr / ShipAddr to contact_addresses rows.
     *
     * @param  array<string, mixed>  $row
     * @return list<array{
     *     label: string,
     *     is_primary: bool,
     *     address_line_1: string,
     *     address_line_2: ?string,
     *     city: ?string,
     *     state: ?string,
     *     postal_code: ?string,
     *     country: ?string
     * }>
     */
    public static function addressesFromCustomerRow(array $row): array
    {
        $billing = self::mapAddr($row['BillAddr'] ?? null, 'Billing', true);
        $shipping = self::mapAddr($row['ShipAddr'] ?? null, 'Shipping', false);

        $addresses = [];

        if ($billing !== null) {
            $addresses[] = $billing;
        }

        if ($shipping !== null) {
            if ($billing === null) {
                $shipping['is_primary'] = true;
            }
            $addresses[] = $shipping;
        }

        if ($addresses !== [] && ! self::hasPrimary($addresses)) {
            $addresses[0]['is_primary'] = true;
        }

        return $addresses;
    }

    /**
     * @param  array<string, mixed>|null  $addr
     * @return array{
     *     label: string,
     *     is_primary: bool,
     *     address_line_1: string,
     *     address_line_2: ?string,
     *     city: ?string,
     *     state: ?string,
     *     postal_code: ?string,
     *     country: ?string
     * }|null
     */
    private static function mapAddr(mixed $addr, string $label, bool $isPrimary): ?array
    {
        if (! is_array($addr)) {
            return null;
        }

        $line1 = trim((string) ($addr['Line1'] ?? ''));
        if ($line1 === '') {
            return null;
        }

        return [
            'label' => $label,
            'is_primary' => $isPrimary,
            'address_line_1' => $line1,
            'address_line_2' => self::optionalString($addr['Line2'] ?? null),
            'city' => self::optionalString($addr['City'] ?? null),
            'state' => self::optionalString($addr['CountrySubDivisionCode'] ?? $addr['State'] ?? null),
            'postal_code' => self::optionalString($addr['PostalCode'] ?? null),
            'country' => self::optionalString($addr['Country'] ?? null),
        ];
    }

    /**
     * @param  list<array{is_primary: bool}>  $addresses
     */
    private static function hasPrimary(array $addresses): bool
    {
        foreach ($addresses as $address) {
            if ($address['is_primary']) {
                return true;
            }
        }

        return false;
    }

    private static function optionalString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
