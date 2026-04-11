<?php

namespace App\Enums\Entity;

use App\Enums\Entity\Concerns\ResolvesStringEnumFromIdOrValue;

enum ContactTimePreference: string
{
    use ResolvesStringEnumFromIdOrValue;

    case Morning = 'morning';
    case Afternoon = 'afternoon';
    case Evening = 'evening';

    /**
     * Legacy numeric id (e.g. migrated data). Prefer {@see value} for storage and forms.
     */
    public function id(): int
    {
        return match ($this) {
            self::Morning => 1,
            self::Afternoon => 2,
            self::Evening => 3,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Morning => 'Morning',
            self::Afternoon => 'Afternoon',
            self::Evening => 'Evening',
        };
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
