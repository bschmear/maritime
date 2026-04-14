<?php

namespace App\Enums\Entity;

use App\Enums\Entity\Concerns\ResolvesStringEnumFromIdOrValue;

enum CustomerType: string
{
    use ResolvesStringEnumFromIdOrValue;

    case Individual = 'individual';
    case Business = 'business';
    case Dealer = 'dealer';
    case Broker = 'broker';

    public function id(): int
    {
        return match ($this) {
            self::Individual => 1,
            self::Business => 2,
            self::Dealer => 3,
            self::Broker => 4,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Individual',
            self::Business => 'Business',
            self::Dealer => 'Dealer',
            self::Broker => 'Broker',
        };
    }

    public function isCommercial(): bool
    {
        return in_array($this, [
            self::Business,
            self::Dealer,
            self::Broker,
        ]);
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'id' => $case->value,
            'value' => $case->value,
            'name' => $case->label(),
        ], self::cases());
    }
}
