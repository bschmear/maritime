<?php

namespace App\Enums\Asset;

enum Condition: int
{
    case New   = 1;
    case Good  = 2;
    case Fair  = 3;
    case Poor  = 4;

    public function label(): string
    {
        return match ($this) {
            self::New   => 'New',
            self::Good  => 'Good',
            self::Fair  => 'Fair',
            self::Poor  => 'Poor',
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