<?php

namespace App\Enums\Payments;

use App\Enums\Entity\Concerns\ResolvesStringEnumFromIdOrValue;

enum Currency: string
{
    use ResolvesStringEnumFromIdOrValue;

    case USD = 'USD';

    public function label(): string
    {
        return match ($this) {
            self::USD => 'US Dollar',
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::USD => '$',
        };
    }
}