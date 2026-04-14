<?php

namespace App\Enums\Entity;

use App\Enums\Entity\Concerns\ResolvesStringEnumFromIdOrValue;

enum CustomerSegment: string
{
    use ResolvesStringEnumFromIdOrValue;

    case Standard = 'standard';
    case VIP = 'vip';
    case Repeat = 'repeat';
    case Wholesale = 'wholesale';

    public function id(): int
    {
        return match ($this) {
            self::Standard => 1,
            self::VIP => 2,
            self::Repeat => 3,
            self::Wholesale => 4,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::VIP => 'VIP',
            self::Repeat => 'Repeat Customer',
            self::Wholesale => 'Wholesale',
        };
    }

    public function isHighValue(): bool
    {
        return in_array($this, [
            self::VIP,
            self::Repeat,
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
