<?php

namespace App\Enums\Inventory;

enum ItemType: string
{
    case Boat        = 'boat';
    case Engine      = 'engine';
    case Trailer     = 'trailer';

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
            self::Boat        => 1,
            self::Engine      => 2,
            self::Trailer     => 3,
            self::Part        => 4,
            self::Accessory   => 5,
            self::Package     => 6,
            self::Electronics => 7,
            self::Gear        => 8,
            self::Apparel     => 9,
            self::Other       => 10,
        };
    }

    /**
     * High-level inventory category for filtering & UI grouping
     */
    public function category(): string
    {
        return match ($this) {
            self::Boat,
            self::Engine,
            self::Trailer
                => 'asset',

            default
                => 'inventory',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Boat        => 'Boat',
            self::Engine      => 'Engine',
            self::Trailer     => 'Trailer',
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
            'category' => $case->category(),
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
