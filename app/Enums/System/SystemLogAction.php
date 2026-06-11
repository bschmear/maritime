<?php

namespace App\Enums\System;

enum SystemLogAction: int
{
    case Created = 1;
    case Updated = 2;
    case Deleted = 3;

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Created',
            self::Updated => 'Updated',
            self::Deleted => 'Deleted',
        };
    }

    /**
     * @return list<array{id: int, value: int, name: string}>
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
