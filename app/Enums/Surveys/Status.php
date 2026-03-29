<?php

namespace App\Enums\Surveys;

enum Status: string
{
    case Active = 'active';
    case Draft = 'draft';
    case Archived = 'archived';

    public function id(): int
    {
        return match ($this) {
            self::Active => 1,
            self::Draft => 2,
            self::Archived => 3,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Draft => 'Draft',
            self::Archived => 'Archived',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Draft => 'gray',
            self::Archived => 'red',
        };
    }

    public function bgClass(): string
    {
        return match ($this) {
            self::Active => 'bg-green-200 dark:text-white dark:bg-green-900',
            self::Draft => 'bg-gray-200 dark:text-white dark:bg-gray-900',
            self::Archived => 'bg-red-200 dark:text-white dark:bg-red-900',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'id' => $case->id(),
            'value' => $case->value,
            'name' => $case->label(),
            'label' => $case->label(),
            'color' => $case->color(),
            'bgClass' => $case->bgClass(),
        ], self::cases());
    }
}
