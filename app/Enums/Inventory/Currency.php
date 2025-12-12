<?php

namespace App\Enums\Invoice;

enum Currency: string
{
    case USD = 'USD'; // United States Dollar
    case EUR = 'EUR'; // Euro
    case GBP = 'GBP'; // British Pound Sterling
    case JPY = 'JPY'; // Japanese Yen
    case CHF = 'CHF'; // Swiss Franc
    case CAD = 'CAD'; // Canadian Dollar
    case CNY = 'CNY'; // Chinese Yuan
    case INR = 'INR'; // Indian Rupee
    case AUD = 'AUD'; // Australian Dollar
    case NZD = 'NZD'; // New Zealand Dollar

    /**
     * Numeric ID for each currency (optional, useful for select fields).
     */
    public function id(): int
    {
        return match ($this) {
            self::USD => 1,
            self::EUR => 2,
            self::GBP => 3,
            self::JPY => 4,
            self::CHF => 5,
            self::CAD => 6,
            self::CNY => 7,
            self::INR => 8,
            self::AUD => 9,
            self::NZD => 10,
        };
    }

    /**
     * Human-readable label for each currency.
     */
    public function label(): string
    {
        return match ($this) {
            self::USD => 'United States Dollar (USD)',
            self::EUR => 'Euro (EUR)',
            self::GBP => 'British Pound (GBP)',
            self::JPY => 'Japanese Yen (JPY)',
            self::CHF => 'Swiss Franc (CHF)',
            self::CAD => 'Canadian Dollar (CAD)',
            self::CNY => 'Chinese Yuan (CNY)',
            self::INR => 'Indian Rupee (INR)',
            self::AUD => 'Australian Dollar (AUD)',
            self::NZD => 'New Zealand Dollar (NZD)',
        };
    }

    /**
     * Return all options as array for selects or APIs.
     */
    public static function options(): array
    {
        return array_map(fn(self $case) => [
            'id'    => $case->id(),
            'value' => $case->value,
            'name'  => $case->label(),
        ], self::cases());
    }
}
