<?php

namespace App\Enums\Payments;

/**
 * Values align with {@code payment_methods_config.code} and {@code payments.payment_method_code}.
 */
enum PaymentMethod: string
{
    case Ach = 'ach';
    case Cash = 'cash';
    case Check = 'check';
    case CreditCard = 'credit_card';
    case Financing = 'financing';
    case Wire = 'wire';

    public function id(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Ach => 'ACH / Bank Transfer',
            self::Cash => 'Cash',
            self::Check => 'Check',
            self::CreditCard => 'Credit / Debit Card',
            self::Financing => 'Financing',
            self::Wire => 'Wire Transfer',
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
