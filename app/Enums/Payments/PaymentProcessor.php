<?php

namespace App\Enums\Payments;

/**
 * Values align with {@code payments.processor}.
 */
enum PaymentProcessor: string
{
    case Manual = 'manual';
    case Stripe = 'stripe';
    case Quickbooks = 'quickbooks';

    public function id(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual / logged',
            self::Stripe => 'Stripe',
            self::Quickbooks => 'QuickBooks',
        };
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'id' => $case->value,
            'value' => $case->value,
            'name' => $case->label(),
        ], self::cases());
    }
}
