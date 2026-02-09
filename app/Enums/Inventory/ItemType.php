<?php

namespace App\Enums\Inventory;

enum ItemType: string
{
    case Part        = 'part';
    case Accessory   = 'accessory';
    case Electronics = 'electronics';
    case Gear        = 'gear';
    case Apparel     = 'apparel';
    case Package     = 'package';
    case Other       = 'other';

    public function id(): int
    {
        return match ($this) {
            self::Part        => 1,
            self::Accessory   => 2,
            self::Package     => 3,
            self::Electronics => 4,
            self::Gear        => 5,
            self::Apparel     => 6,
            self::Other       => 7,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Part        => 'Part',
            self::Accessory   => 'Accessory',
            self::Package     => 'Package',
            self::Electronics => 'Electronics',
            self::Gear        => 'Gear',
            self::Apparel     => 'Apparel',
            self::Other       => 'Other',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'id'       => $case->id(),
            'value'    => $case->value,
            'name'     => $case->label(),
        ], self::cases());
    }

    /**
     * Helper for controller-level filtering
     */
    public static function valuesByCategory(string $category): array
    {
        return array_values(
            array_map(
                fn (self $case) => $case->value,
                array_filter(
                    self::cases(),
                    fn (self $case) => $case->category() === $category
                )
            )
        );
    }
}
