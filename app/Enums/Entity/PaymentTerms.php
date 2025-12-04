<?php

namespace App\Enums\Entity;

enum PaymentTerms: string
{
    case DueOnReceipt = 'due-on-receipt';
    case Net15        = 'net-15';
    case Net30        = 'net-30';
    case Net45        = 'net-45';
    case Net60        = 'net-60';
    case Prepaid      = 'prepaid';
    case COD          = 'cod';

    public function id(): int
    {
        return match($this) {
            self::DueOnReceipt => 1,
            self::Net15        => 2,
            self::Net30        => 3,
            self::Net45        => 4,
            self::Net60        => 5,
            self::Prepaid      => 6,
            self::COD          => 7,
        };
    }

    public function label(): string
    {
        return match($this) {
            self::DueOnReceipt => 'Due on Receipt',
            self::Net15        => 'Net 15',
            self::Net30        => 'Net 30',
            self::Net45        => 'Net 45',
            self::Net60        => 'Net 60',
            self::Prepaid      => 'Prepaid',
            self::COD          => 'Cash on Delivery',
        };
    }

    public static function options(): array
    {
        return array_map(fn(self $case) => [
            'id'    => $case->id(),
            'value' => $case->value,
            'name'  => $case->label(),
        ], self::cases());
    }
}
