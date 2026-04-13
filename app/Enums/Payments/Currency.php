<?php

namespace App\Enums\Payments;

use App\Enums\Entity\Concerns\ResolvesStringEnumFromIdOrValue;

enum Currency: string
{
    use ResolvesStringEnumFromIdOrValue;

    case USD = 'USD';

    public function id(): int
    {
        return match ($this) {
            self::USD => 1,
        };
    }

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

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'id' => $case->id(),
            'value' => $case->value,
            'name' => $case->label(),
        ], self::cases());
    }
}
