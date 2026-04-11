<?php

namespace App\Enums\Entity;

use App\Enums\Entity\Concerns\ResolvesStringEnumFromIdOrValue;

enum ContactType: string
{
    use ResolvesStringEnumFromIdOrValue;

    case Person = 'person';
    case Company = 'company';

    /**
     * Get the numeric ID for the contact type.
     */
    public function id(): int
    {
        return match ($this) {
            self::Person => 1,
            self::Company => 2,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Person => 'Person',
            self::Company => 'Company',
        };
    }

    /**
     * Return all enum options as an array for select fields or APIs.
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'id' => $case->id(),
            'value' => $case->value,
            'name' => $case->label(),
        ], self::cases());
    }
}
