<?php

namespace App\Enums\ServiceItem;

enum BillingType: int
{
    case Hourly    = 1;
    case Flat      = 2;
    case Quantity  = 3;

    public function id(): int
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Hourly   => 'Hourly',
            self::Flat     => 'Flat Rate',
            self::Quantity => 'Per Quantity',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Hourly   => 'Charged per hour worked',
            self::Flat     => 'Fixed price regardless of time',
            self::Quantity => 'Charged per unit/item',
        };
    }

    public static function options(): array
    {
        return array_map(
            fn(self $case) => [
                'id'          => $case->value,
                'value'       => $case->value,
                'name'        => $case->label(),
                'description' => $case->description(),
            ],
            self::cases()
        );
    }
}
