<?php

namespace App\Enums\Inventory;

enum UnitStatus: string
{
    case Available    = 'available';
    case Pending      = 'pending';
    case Sold         = 'sold';
    case Inbound      = 'inbound';
    case Consignment  = 'consignment';
    case Reserved     = 'reserved';
    case Unavailable  = 'unavailable';

    public function id(): int
    {
        return match ($this) {
            self::Available    => 1,
            self::Pending      => 2,
            self::Sold         => 3,
            self::Inbound      => 4,
            self::Consignment  => 5,
            self::Reserved     => 6,
            self::Unavailable  => 7,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Available    => 'Available',
            self::Pending      => 'Pending',
            self::Sold         => 'Sold',
            self::Inbound      => 'Inbound',
            self::Consignment  => 'Consignment',
            self::Reserved     => 'Reserved',
            self::Unavailable  => 'Unavailable',
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
