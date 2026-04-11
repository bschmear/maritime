<?php

namespace App\Enums\Entity;

use App\Enums\Entity\Concerns\ResolvesStringEnumFromIdOrValue;

enum ContactStage: string
{
    use ResolvesStringEnumFromIdOrValue;

    case Contact = 'contact';     // no profile yet
    case Lead = 'lead';
    case Customer = 'customer';
    case VendorContact = 'vendor_contact';

    public function id(): int
    {
        return match ($this) {
            self::Contact => 1,
            self::Lead => 2,
            self::Customer => 3,
            self::VendorContact => 4,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Contact => 'Contact',
            self::Lead => 'Lead',
            self::Customer => 'Customer',
            self::VendorContact => 'Vendor Contact',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'id' => $case->id(),
            'value' => $case->value,
            'name' => $case->label(),
        ], self::cases());
    }

    /**
     * Persisted column is tinyint ({@see id()}), not the string backing value.
     */
    public static function toStoredId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $enum = $value instanceof self ? $value : self::tryFromStored($value);

        return $enum?->id();
    }
}
