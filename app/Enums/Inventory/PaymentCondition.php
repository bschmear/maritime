<?php

namespace App\Enums\Invoice;

enum PaymentCondition: string
{
    case CashInAdvance   = 'CA';
    case CashOnDelivery  = 'CD';
    case AdvancePayment  = 'AP';
    case Credit          = 'CR';
    case Net30           = 'NET30';
    case Net60           = 'NET60';
    case Deposit         = 'DEPOSIT';
    case Installment     = 'INSTALLMENT';
    case CreditCard      = 'CC';
    case BankTransfer    = 'WIRE';
    case ACH             = 'ACH';

    /**
     * Get numeric ID for each payment condition.
     */
    public function id(): int
    {
        return match ($this) {
            self::CashInAdvance   => 1,
            self::CashOnDelivery  => 2,
            self::AdvancePayment  => 3,
            self::Credit          => 4,
            self::Net30           => 5,
            self::Net60           => 6,
            self::Deposit         => 7,
            self::Installment     => 8,
            self::CreditCard      => 9,
            self::BankTransfer    => 10,
            self::ACH             => 11,
        };
    }

    /**
     * Get human-readable label for each condition.
     */
    public function label(): string
    {
        return match ($this) {
            self::CashInAdvance   => 'Cash in Advance',
            self::CashOnDelivery  => 'Cash on Delivery',
            self::AdvancePayment  => 'Advance Payment',
            self::Credit          => 'Credit',
            self::Net30           => 'Net 30',
            self::Net60           => 'Net 60',
            self::Deposit         => 'Deposit',
            self::Installment     => 'Installment',
            self::CreditCard      => 'Credit Card',
            self::BankTransfer    => 'Bank Transfer / Wire',
            self::ACH             => 'ACH / Direct Debit',
        };
    }

    /**
     * Return all options as an array for selects or APIs.
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
