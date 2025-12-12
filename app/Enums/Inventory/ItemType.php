<?php

namespace App\Enums\Inventory;

enum ItemType: string
{
    case Boat       = 'boat';
    case Engine     = 'engine';
    case Trailer    = 'trailer';
    case Part       = 'part';
    case Accessory  = 'accessory';
    case Service    = 'service';
    case Package    = 'package';
    case Electronics = 'electronics';
    case Gear       = 'gear';
    case Apparel    = 'apparel';
    case Other      = 'other';

    public function id(): int
    {
        return match ($this) {
            self::Boat        => 1,
            self::Engine      => 2,
            self::Trailer     => 3,
            self::Part        => 4,
            self::Accessory   => 5,
            self::Service     => 6,
            self::Package     => 7,
            self::Electronics => 8,
            self::Gear        => 9,
            self::Apparel     => 10,
            self::Other       => 11,
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
            self::Service     => 'Service',
            self::Package     => 'Package',
            self::Electronics => 'Electronics',
            self::Gear        => 'Gear',
            self::Apparel     => 'Apparel',
            self::Other       => 'Other',
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
