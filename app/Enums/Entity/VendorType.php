<?php

namespace App\Enums\Entity;

enum VendorType: string
{
    case Dealer        = 'dealer';
    case Manufacturer  = 'manufacturer';
    case Lender        = 'lender';
    case Service       = 'service';
    case Parts         = 'parts';
    case Other         = 'other';

    public function id(): int
    {
        return match ($this) {
            self::Dealer        => 1,
            self::Manufacturer  => 2,
            self::Lender        => 3,
            self::Service       => 4,
            self::Parts         => 5,
            self::Other         => 6,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Dealer        => 'Dealer',
            self::Manufacturer  => 'Manufacturer',
            self::Lender        => 'Lender',
            self::Service       => 'Service',
            self::Parts         => 'Parts Supplier',
            self::Other         => 'Other',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Dealer        => 'blue',
            self::Manufacturer  => 'purple',
            self::Lender        => 'green',
            self::Service       => 'teal',
            self::Parts         => 'orange',
            self::Other         => 'gray',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Dealer        => 'bg-blue-200 dark:text-white dark:bg-blue-900',
            self::Manufacturer  => 'bg-purple-200 dark:text-white dark:bg-purple-900',
            self::Lender        => 'bg-green-200 dark:text-white dark:bg-green-900',
            self::Service       => 'bg-teal-200 dark:text-white dark:bg-teal-900',
            self::Parts         => 'bg-orange-200 dark:text-white dark:bg-orange-900',
            self::Other         => 'bg-gray-200 dark:text-white dark:bg-gray-900',
        };
    }

    public static function options(): array
    {
        return array_map(fn(self $case) => [
            'id'      => $case->id(),
            'value'   => $case->value,
            'name'    => $case->label(),
            'color'   => $case->color(),
            'bgClass' => $case->bgClass(),
        ], self::cases());
    }
}
