<?php

namespace App\Enums\Entity;

enum OwnershipType: string
{
    case Personal = 'personal';
    case Business = 'business';

    public function id(): int
    {
        return match ($this) {
            self::Personal => 1,
            self::Business => 2,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Personal => 'Personal',
            self::Business => 'Business',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Personal => 'blue',
            self::Business => 'teal',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Personal => 'bg-blue-200 dark:text-white dark:bg-blue-900',
            self::Business => 'bg-teal-200 dark:text-white dark:bg-teal-900',
        };
    }

    public static function options(): array
    {
        return array_map(fn(self $case) => [
            'id'      => $case->id(),
            'value'   => $case->value,
            'name'    => $case->label(),
            'color'   => $case->color(),
            'bgClass' => $case->bgClass(),
        ], self::cases());
    }
}