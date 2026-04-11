<?php

namespace App\Enums\Entity;

use App\Enums\Entity\Concerns\ResolvesStringEnumFromIdOrValue;

enum ContactStatus: string
{
    use ResolvesStringEnumFromIdOrValue;

    case Active = 'active';
    case Inactive = 'inactive';
    case Blocked = 'blocked';

    /**
     * Legacy numeric id (e.g. migrated data). Prefer {@see value} for storage and forms.
     */
    public function id(): int
    {
        return match ($this) {
            self::Active => 1,
            self::Inactive => 2,
            self::Blocked => 3,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Blocked => 'Blocked',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    /**
     * Select options: {@see id} is the string backing value so forms and DB stay string columns.
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'id' => $case->value,
            'value' => $case->value,
            'name' => $case->label(),
        ], self::cases());
    }
}
