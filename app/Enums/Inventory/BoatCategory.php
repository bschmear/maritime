<?php

namespace App\Enums\Inventory;

enum BoatCategory: string
{
    case Inflatable = 'inflatable';
    case Jet        = 'jet';

    public function label(): string
    {
        return match ($this) {
            self::Inflatable => 'Inflatable Boats',
            self::Jet        => 'Jet Boats',
        };
    }
}
