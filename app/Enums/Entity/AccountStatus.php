<?php

namespace App\Enums\Entity;

use App\Enums\Entity\Concerns\ResolvesStringEnumFromIdOrValue;

enum AccountStatus: string
{
    use ResolvesStringEnumFromIdOrValue;

    case Active = 'active';
    case Prospect = 'prospect';
    case Inactive = 'inactive';
    case Blocked = 'blocked';
    case Archived = 'archived';

    public function id(): int
    {
        return match ($this) {
            self::Active => 1,
            self::Prospect => 2,
            self::Inactive => 3,
            self::Blocked => 4,
            self::Archived => 5,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Prospect => 'Prospect',
            self::Inactive => 'Inactive',
            self::Blocked => 'Blocked',
            self::Archived => 'Archived',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    public function isInactive(): bool
    {
        return in_array($this, [
            self::Inactive,
            self::Archived,
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
