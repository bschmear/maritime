<?php

namespace App\Enums\Inventory;

enum UnitCondition: string
{
    case BrandNew    = 'new';
    case Used        = 'used';
    case Refurbished = 'refurbished';

    public function id(): int
    {
        return match ($this) {
            self::BrandNew    => 1,
            self::Used        => 2,
            self::Refurbished => 3,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::BrandNew    => 'New',
            self::Used        => 'Used',
            self::Refurbished => 'Refurbished',
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
