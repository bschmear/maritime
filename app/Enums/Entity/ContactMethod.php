<?php

namespace App\Enums\Entity;

use App\Enums\Entity\Concerns\ResolvesStringEnumFromIdOrValue;

enum ContactMethod: string
{
    use ResolvesStringEnumFromIdOrValue;

    case Email = 'email';
    case Phone = 'phone';
    case Text = 'text';

    /**
     * Legacy numeric id (e.g. migrated data). Prefer {@see value} for storage and forms.
     */
    public function id(): int
    {
        return match ($this) {
            self::Email => 1,
            self::Phone => 2,
            self::Text => 3,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Phone => 'Phone Call',
            self::Text => 'Text Message',
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
