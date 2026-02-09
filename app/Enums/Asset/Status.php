<?php

namespace App\Enums\Asset;

enum Status: int
{
    case Active       = 0;
    case InService    = 1;
    case OutOfService = 2;
    case Retired      = 3;
    case Sold         = 4;

    public function label(): string
    {
        return match ($this) {
            self::Active       => 'Active',
            self::InService    => 'In Service',
            self::OutOfService => 'Out of Service',
            self::Retired      => 'Retired',
            self::Sold         => 'Sold',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'id'    => $case->value,
            'value' => $case->value,
            'name'  => $case->label(),
        ], self::cases());
    }
}